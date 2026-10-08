<?php
require_once __DIR__ . '/../models/Data43RecordModel.php';
require_once __DIR__ . '/Data43CryptoService.php';
require_once __DIR__ . '/Data43ValidationService.php';
require_once __DIR__ . '/Data43FormRegistry.php';
require_once __DIR__ . '/Data43StorageService.php';

final class Data43RegistryService
{
    private Data43RecordModel $model;
    private ?Data43CryptoService $crypto = null;

    public function __construct(private PDO $db)
    {
        $this->model = new Data43RecordModel($db);
    }

    private function crypto(): Data43CryptoService
    {
        if ($this->crypto === null) {
            $this->crypto = new Data43CryptoService();
        }
        return $this->crypto;
    }

    public function schemaReady(): bool
    {
        return $this->model->schemaReady();
    }

    public function getHospitalDefaults(int $hospitalId): array
    {
        $hasCode9=false;
        try{
            $column=$this->db->query("SHOW COLUMNS FROM hospitals LIKE 'hospital_code9'")->fetch(PDO::FETCH_ASSOC);
            $hasCode9=(bool)$column;
        }catch(Throwable $e){$hasCode9=false;}
        $sql=$hasCode9
            ? "SELECT hospital_code,hospital_code9 FROM hospitals WHERE id=? AND deleted_at IS NULL LIMIT 1"
            : "SELECT hospital_code,NULL AS hospital_code9 FROM hospitals WHERE id=? AND deleted_at IS NULL LIMIT 1";
        $stmt=$this->db->prepare($sql);
        $stmt->execute([$hospitalId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $hospcode=trim((string)($row['hospital_code'] ?? ''));
        if($hospcode!=='' && ctype_digit($hospcode)) {
            $hospcode=str_pad($hospcode,5,'0',STR_PAD_LEFT);
        }
        $hospcode9=trim((string)($row['hospital_code9'] ?? ''));
        if($hospcode9!=='' && ctype_digit($hospcode9)) {
            $hospcode9=str_pad($hospcode9,9,'0',STR_PAD_LEFT);
        }

        return [
            'HOSPCODE'=>$hospcode,
            'HOSPCODE9'=>$hospcode9,
        ];
    }

    public function save(int $hospitalId,int $userId,string $fileCode,array $input,?int $recordId=null): array
    {
        $schema=Data43FormRegistry::get($fileCode);
        if(!$schema) throw new RuntimeException('ยังไม่รองรับแบบฟอร์มแฟ้ม '.$fileCode);

        $defaults=$this->getHospitalDefaults($hospitalId);
        $existing=null;
        if($recordId){
            $existing=$this->model->find($recordId,$hospitalId);
            if(!$existing) throw new RuntimeException('ไม่พบรายการที่ต้องการแก้ไข');
            if(strtoupper((string)$existing['file_code'])!==$fileCode) {
                throw new RuntimeException('ไม่สามารถเปลี่ยนชนิดแฟ้มของรายการเดิมได้');
            }
        }

        $data=[];
        foreach($schema['fields'] as $field){
            $name=$field['name'];
            $data[$name]=isset($input[$name]) ? trim((string)$input[$name]) : '';
        }

        $data['HOSPCODE']=$defaults['HOSPCODE'];
        if(array_key_exists('HOSPCODE9',$data)) $data['HOSPCODE9']=$defaults['HOSPCODE9'];
        $data['D_UPDATE']=date('YmdHis');

        if($recordId && $existing){
            $original=$this->decodeRow($existing)['data'];

            // Primary-key fields identify the logical record. Changing them through
            // an edit would silently turn one record into another and can break
            // child references. Create a new record instead if the key must change.
            foreach((array)($schema['pk'] ?? []) as $pkField){
                if(array_key_exists($pkField,$original)){
                    $data[$pkField]=(string)$original[$pkField];
                }
            }
        }

        if($fileCode==='PERSON' && $data['PID']==='') $data['PID']=$this->model->generatePid($hospitalId);
        if($fileCode==='HOME' && $data['HID']==='') $data['HID']=$this->model->generateHid($hospitalId);

        foreach($data as $name=>$value){
            if(str_starts_with($name,'DATE_') || in_array($name,['BIRTH','DDISCHARGE','DDEATH','DATE_DIAG','DATE_SERV','DATE_DETECT','DATE_DISAB','LMP','EDC','OUTDATE'],true)){
                if($value!=='' && !preg_match('/^\d{8}$/',$value)){
                    $converted=Data43ValidationService::buddhistToDate8($value);
                    if($converted!==null) $data[$name]=$converted;
                }
            }
        }
        if(isset($data['VILLAGE'])) $data['VILLAGE']=Data43ValidationService::normalizeVillage($data['VILLAGE']) ?? '';

        foreach (['DIAGCODE','CHRONIC','CDEATH_A','CDEATH_B','CDEATH_C','CDEATH_D','ODISEASE','CDEATH'] as $icdField) {
            if (!empty($data[$icdField])) {
                $data[$icdField] = strtoupper(str_replace('.', '', trim((string)$data[$icdField])));
            }
        }

        if(in_array($fileCode,['ADDRESS','CHRONIC','DEATH','DISABILITY','SERVICE','NCDSCREEN','PRENATAL','ANC'],true) && !empty($data['PID'])){
            $person=$this->findPersonByPid($hospitalId,$data['PID']);
            if(!$person) throw new RuntimeException('ไม่พบ PERSON ของ PID ที่เลือก');
            if(array_key_exists('CID',$data)) $data['CID']=(string)($person['CID'] ?? '');
        }

        $errors=Data43ValidationService::validateRecord($fileCode,$data);
        $errors=array_merge($errors,Data43ValidationService::validateSchemaFields($schema,$data));
        foreach($schema['fields'] as $field){
            if(!empty($field['required']) && trim((string)($data[$field['name']] ?? ''))===''){
                $errors[$field['name']]='กรุณากรอกข้อมูล';
            }
        }
        if($errors) throw new InvalidArgumentException(json_encode($errors,JSON_UNESCAPED_UNICODE));

        $recordKey=$this->recordKey($hospitalId,$schema['pk'],$data);
        if($this->model->existsRecordKey($hospitalId,$fileCode,$recordKey,$recordId)) {
            throw new RuntimeException('พบ Primary Key ซ้ำในแฟ้ม '.$fileCode);
        }

        $cidHash=$this->crypto()->cidHash($data['CID'] ?? null);
        if($fileCode==='PERSON' && $cidHash && $this->model->existsCidHash($hospitalId,$cidHash,$recordId)){
            throw new RuntimeException('พบ CID ซ้ำในทะเบียน PERSON');
        }

        $encrypted=$this->crypto()->encrypt($data);
        $pidRef=trim((string)($data['PID'] ?? '')) ?: null;
        $hidRef=trim((string)($data['HID'] ?? '')) ?: null;
        $row=[
            ':record_key_hash'=>$recordKey,
            ':pid_ref'=>$pidRef,
            ':hid_ref'=>$hidRef,
            ':cid_hash'=>$cidHash,
            ':payload_ciphertext'=>$encrypted['ciphertext'],
            ':payload_nonce'=>$encrypted['nonce'],
            ':payload_algorithm'=>$encrypted['algorithm'],
            ':payload_sha256'=>$encrypted['sha256'],
            ':updated_by'=>$userId,
        ];

        $this->db->beginTransaction();
        try{
            $changedFields=array_keys($data);
            if($recordId && $existing){
                $before=$this->decodeRow($existing)['data'];
                $changedFields=[];
                foreach($data as $key=>$value){
                    if((string)($before[$key] ?? '') !== (string)$value) $changedFields[]=$key;
                }
            }
            if($recordId){
                $this->model->update($recordId,$hospitalId,$row);
                $id=$recordId;
                $action='UPDATE';
            }else{
                $row[':hospital_id']=$hospitalId;
                $row[':file_code']=$fileCode;
                $row[':created_by']=$userId;
                $id=$this->model->insert($row);
                $action='CREATE';
            }
            $this->refreshSearchTokens($id,$fileCode,$data);
            $this->model->addAudit($id,$hospitalId,$fileCode,$action,$userId,$recordKey,$changedFields);

            if($fileCode==='DEATH'){
                $this->syncPersonDeath($hospitalId,$userId,(string)$data['PID'],(string)$data['DDEATH']);
            }

            $this->db->commit();
            return ['id'=>$id,'data'=>$data,'action'=>$action];
        }catch(Throwable $e){
            if($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function getRecord(int $hospitalId,int $id): ?array
    {
        $row=$this->model->find($id,$hospitalId);
        return $row ? $this->decodeRow($row) : null;
    }

    public function listFile(int $hospitalId,string $fileCode,int $limit=100): array
    {
        $rows=$this->model->listByFile($hospitalId,$fileCode,$limit);
        foreach($rows as &$row){
            $payload=$this->decodeRow($row)['data'];
            $row['display']=$this->displayLabel($fileCode,$payload);
        }
        unset($row);
        return $rows;
    }

    public function search(int $hospitalId,string $type,string $query): array
    {
        $type=strtoupper($type)==='HOME'?'HOME':'PERSON';
        $query=trim($query);
        if(mb_strlen($query,'UTF-8')<2) return [];
        $token=$this->crypto()->blindToken($query);
        $rows=$this->model->searchByToken($hospitalId,$type,$token,20);
        $out=[];
        foreach($rows as $row){
            $decoded=$this->decodeRow($row);
            $data=$decoded['data'];
            $out[]=[
                'id'=>(int)$row['id'],
                'pid'=>$data['PID'] ?? null,
                'hid'=>$data['HID'] ?? null,
                'label'=>$this->displayLabel($type,$data),
            ];
        }
        return $out;
    }

    public function personProfile(int $hospitalId,string $pid): array
    {
        $rows=$this->model->listByPid($hospitalId,$pid);
        $grouped=[];
        $homeIds=[];

        foreach($rows as $row){
            $decoded=$this->decodeRow($row);
            $grouped[$row['file_code']][]=$decoded;

            if($row['file_code']==='PERSON'){
                $hid=trim((string)($decoded['data']['HID'] ?? ''));
                if($hid!=='') $homeIds[$hid]=true;
            }
        }

        foreach(array_keys($homeIds) as $hid){
            foreach($this->model->listByHid($hospitalId,$hid) as $homeRow){
                if($homeRow['file_code']!=='HOME') continue;
                $grouped['HOME'][]=$this->decodeRow($homeRow);
            }
        }

        return $grouped;
    }

    public function overview(int $hospitalId): array
    {
        $people=$this->model->listForExport($hospitalId,'PERSON');
        $chronic=$this->model->listForExport($hospitalId,'CHRONIC');
        $summary=[
            'people'=>0,
            'typearea'=>['1'=>0,'2'=>0,'3'=>0,'4'=>0,'5'=>0],
            'age'=>['0-14'=>0,'15-59'=>0,'60+'=>0,'unknown'=>0],
            'chronic_people'=>0,
            'dm'=>0,
            'ht'=>0,
            'missing_home'=>0,
        ];
        $chronicPids=[];$dm=[];$ht=[];
        foreach($people as $row){
            $data=$this->decodeRow($row)['data'];
            $summary['people']++;
            $type=(string)($data['TYPEAREA']??'');
            if(isset($summary['typearea'][$type])) $summary['typearea'][$type]++;
            $birth=(string)($data['BIRTH']??'');
            $age=null;
            if(preg_match('/^(\d{4})(\d{2})(\d{2})$/',$birth,$m) && checkdate((int)$m[2],(int)$m[3],(int)$m[1])){
                $age=(int)(new DateTimeImmutable($m[1].'-'.$m[2].'-'.$m[3]))->diff(new DateTimeImmutable('today'))->y;
            }
            if($age===null)$summary['age']['unknown']++;
            elseif($age<15)$summary['age']['0-14']++;
            elseif($age<60)$summary['age']['15-59']++;
            else $summary['age']['60+']++;
            if(empty($data['HID']))$summary['missing_home']++;
        }
        foreach($chronic as $row){
            $data=$this->decodeRow($row)['data'];
            $pid=(string)($data['PID']??'');
            $diag=strtoupper((string)($data['CHRONIC']??''));
            if($pid==='')continue;
            $chronicPids[$pid]=true;
            if(preg_match('/^E1[0-4]/',$diag))$dm[$pid]=true;
            if(preg_match('/^I1[0-5]/',$diag))$ht[$pid]=true;
        }
        $summary['chronic_people']=count($chronicPids);
        $summary['dm']=count($dm);
        $summary['ht']=count($ht);
        return $summary;
    }

    public function exportFile(int $hospitalId,int $userId,string $fileCode,string $format,string $workDir): string
    {
        $headers=Data43FormRegistry::exportHeaders($fileCode);
        if(!$headers) throw new RuntimeException('แฟ้มนี้ยังไม่มี Export Schema');
        $rows=$this->model->listForExport($hospitalId,$fileCode);
        $invalidRecords = [];
        foreach ($rows as $row) {
            $payload = $this->decodeRow($row)['data'];
            $errors = $this->validatePayloadAgainstSchema($fileCode, $payload);
            if ($errors) {
                $invalidRecords[] = [
                    'id' => (int)$row['id'],
                    'fields' => array_keys($errors),
                ];
                if (count($invalidRecords) >= 5) break;
            }
        }
        if ($invalidRecords) {
            $sample = implode(', ', array_map(
                static fn(array $item): string => '#' . $item['id'] . '(' . implode('/', $item['fields']) . ')',
                $invalidRecords
            ));
            throw new RuntimeException(
                'ไม่สามารถส่งออก ' . $fileCode . ' ได้ เนื่องจากมีข้อมูลไม่ผ่านมาตรฐาน กรุณาแก้ไขรายการ: ' . $sample
            );
        }

        if(!is_dir($workDir) && !mkdir($workDir,0750,true) && !is_dir($workDir)) throw new RuntimeException('สร้างพื้นที่ส่งออกไม่สำเร็จ');

        $ext=$format==='csv'?'csv':'txt';
        $path=$workDir.DIRECTORY_SEPARATOR.$fileCode.'.'.$ext;
        $fh=fopen($path,'wb');
        if(!$fh) throw new RuntimeException('สร้างไฟล์ส่งออกไม่สำเร็จ');

        try{
            if($format==='csv'){
                fputcsv($fh,$headers,',','"','\\');
                foreach($rows as $row){
                    $data=$this->decodeRow($row)['data'];
                    fputcsv($fh,array_map(fn($h)=>(string)($data[$h]??''),$headers),',','"','\\');
                }
            }else{
                fwrite($fh,implode('|',$headers)."\r\n");
                foreach($rows as $row){
                    $data=$this->decodeRow($row)['data'];
                    $vals=array_map(static fn($h)=>str_replace(["\r","\n","|"],' ',(string)($data[$h]??'')),$headers);
                    fwrite($fh,implode('|',$vals)."\r\n");
                }
            }
        }finally{fclose($fh);}

        $this->model->addAudit(null,$hospitalId,$fileCode,'EXPORT',$userId,null,[]);
        return $path;
    }

    public function exportZip(int $hospitalId,int $userId,array $fileCodes,string $format,string $workDir): string
    {
        if(!class_exists('ZipArchive')) throw new RuntimeException('PHP Zip extension ยังไม่ได้เปิดใช้งาน');
        if(!is_dir($workDir) && !mkdir($workDir,0750,true) && !is_dir($workDir)) throw new RuntimeException('สร้างพื้นที่ส่งออกไม่สำเร็จ');
        $format=$format==='csv'?'csv':'txt';
        $zipPath=$workDir.DIRECTORY_SEPARATOR.'DATA43_'.date('Ymd_His').'.zip';
        $zip=new ZipArchive();
        if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('สร้าง ZIP ไม่สำเร็จ');

        try{
            foreach($fileCodes as $fileCode){
                if(!Data43FormRegistry::get($fileCode)) continue;
                $path=$this->exportFile($hospitalId,$userId,$fileCode,$format,$workDir);
                $zip->addFile($path,basename($path));
            }
        }finally{$zip->close();}

        $this->model->addAudit(null,$hospitalId,'ALL','EXPORT',$userId,null,[]);
        return $zipPath;
    }

    public function delete(int $hospitalId,int $userId,int $id): void
    {
        $row=$this->model->find($id,$hospitalId);
        if(!$row) throw new RuntimeException('ไม่พบข้อมูล');

        $fileCode=(string)$row['file_code'];
        $decoded=$this->decodeRow($row);
        $data=$decoded['data'];

        if($fileCode==='PERSON'){
            $pid=trim((string)($data['PID'] ?? ''));
            if($pid!=='' && $this->model->countPidReferences($hospitalId,$pid,$id)>0){
                throw new RuntimeException('ไม่สามารถลบ PERSON ได้ เนื่องจากยังมีข้อมูลแฟ้มลูกอ้างอิง PID นี้');
            }
        }

        if($fileCode==='HOME'){
            $hid=trim((string)($data['HID'] ?? ''));
            if($hid!=='' && $this->model->countHidReferences($hospitalId,$hid,$id)>0){
                throw new RuntimeException('ไม่สามารถลบ HOME ได้ เนื่องจากยังมี PERSON อ้างอิง HID นี้');
            }
        }

        $this->model->softDelete($id,$hospitalId,$userId);
        $this->model->addAudit($id,$hospitalId,$fileCode,'DELETE',$userId,(string)$row['record_key_hash'],[]);
    }

    private function validatePayloadAgainstSchema(string $fileCode, array $data): array
    {
        $schema = Data43FormRegistry::get($fileCode);
        if (!$schema) return ['FILE_CODE' => 'ไม่รู้จักโครงสร้างแฟ้ม'];

        $errors = Data43ValidationService::validateRecord($fileCode, $data);
        $errors = array_merge($errors, Data43ValidationService::validateSchemaFields($schema, $data));

        foreach ((array)($schema['fields'] ?? []) as $field) {
            $name = (string)($field['name'] ?? '');
            if ($name === '') continue;
            if (!empty($field['required']) && trim((string)($data[$name] ?? '')) === '') {
                $errors[$name] = 'ข้อมูลจำเป็นว่าง';
            }
        }

        return $errors;
    }

    private function findPersonByPid(int $hospitalId,string $pid): ?array
    {
        $rows=$this->model->listByPid($hospitalId,$pid);
        foreach($rows as $row){
            if($row['file_code']==='PERSON') return $this->decodeRow($row)['data'];
        }
        return null;
    }

    private function syncPersonDeath(int $hospitalId,int $userId,string $pid,string $deathDate): void
    {
        $rows=$this->model->listByPid($hospitalId,$pid);
        foreach($rows as $row){
            if($row['file_code']!=='PERSON') continue;
            $decoded=$this->decodeRow($row);
            $data=$decoded['data'];
            $data['DISCHARGE']='1';
            $data['DDISCHARGE']=$deathDate;
            $data['D_UPDATE']=date('YmdHis');
            $enc=$this->crypto()->encrypt($data);
            $schema=Data43FormRegistry::get('PERSON');
            $recordKey=$this->recordKey($hospitalId,$schema['pk'],$data);
            $this->model->update((int)$row['id'],$hospitalId,[
                ':record_key_hash'=>$recordKey,':pid_ref'=>$pid,':hid_ref'=>$data['HID']??null,
                ':cid_hash'=>$this->crypto()->cidHash($data['CID']??null),':payload_ciphertext'=>$enc['ciphertext'],
                ':payload_nonce'=>$enc['nonce'],':payload_algorithm'=>$enc['algorithm'],':payload_sha256'=>$enc['sha256'],':updated_by'=>$userId,
            ]);
            $this->model->addAudit((int)$row['id'],$hospitalId,'PERSON','UPDATE',$userId,$recordKey,['DISCHARGE','DDISCHARGE','D_UPDATE']);
            break;
        }
    }

    private function refreshSearchTokens(int $recordId,string $fileCode,array $data): void
    {
        $tokens=[];
        if($fileCode==='PERSON'){
            foreach([(string)($data['PID']??''),(string)($data['CID']??''),trim(($data['NAME']??'').' '.($data['LNAME']??''))] as $value){
                $tokens=array_merge($tokens,$this->crypto()->prefixTokens($value));
            }
            $this->model->replaceSearchTokens($recordId,'PERSON',$tokens);
        }elseif($fileCode==='HOME'){
            foreach([(string)($data['HID']??''),(string)($data['HOUSE']??'')] as $value){
                $tokens=array_merge($tokens,$this->crypto()->prefixTokens($value));
            }
            $this->model->replaceSearchTokens($recordId,'HOME',$tokens);
        }
    }

    private function recordKey(int $hospitalId,array $pk,array $data): string
    {
        $parts=[$hospitalId];
        foreach($pk as $name) $parts[]=strtoupper(trim((string)($data[$name]??'')));
        return hash('sha256',implode('|',$parts));
    }

    private function decodeRow(array $row): array
    {
        return [
            'id'=>(int)$row['id'],
            'file_code'=>(string)$row['file_code'],
            'data'=>$this->crypto()->decrypt((string)$row['payload_ciphertext'],(string)$row['payload_nonce'],(string)$row['payload_algorithm']),
            'created_at'=>$row['created_at']??null,
            'updated_at'=>$row['updated_at']??null,
        ];
    }

    private function displayLabel(string $fileCode,array $data): string
    {
        return match($fileCode){
            'PERSON'=>trim(($data['PRENAME']??'').' '.($data['NAME']??'').' '.($data['LNAME']??'')).' · PID '.($data['PID']??''),
            'HOME'=>'บ้าน '.($data['HOUSE']??'-').' · HID '.($data['HID']??''),
            'CHRONIC'=>($data['CHRONIC']??'').' · PID '.($data['PID']??''),
            'DEATH'=>'PID '.($data['PID']??'').' · '.($data['DDEATH']??''),
            'DISABILITY'=>'ประเภท '.($data['DISABTYPE']??'-').' · PID '.($data['PID']??''),
            'SERVICE'=>'SEQ '.($data['SEQ']??'-').' · PID '.($data['PID']??''),
            'NCDSCREEN'=>'คัดกรอง '.($data['DATE_SERV']??'-').' · PID '.($data['PID']??''),
            'PRENATAL'=>'ครรภ์ '.($data['GRAVIDA']??'-').' · PID '.($data['PID']??''),
            'ANC'=>'ANC '.($data['DATE_SERV']??'-').' · PID '.($data['PID']??''),
            'VILLAGE'=>'VID '.($data['VID']??'-'),
            default=>$fileCode.' · '.($data['PID']??$data['HID']??''),
        };
    }
}
?>