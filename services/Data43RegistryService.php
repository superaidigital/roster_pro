<?php
require_once __DIR__ . '/../models/Data43RecordModel.php';
require_once __DIR__ . '/Data43CryptoService.php';
require_once __DIR__ . '/Data43ValidationService.php';
require_once __DIR__ . '/Data43FormRegistry.php';

final class Data43RegistryService
{
    private Data43RecordModel $model;
    private Data43CryptoService $crypto;

    public function __construct(private PDO $db)
    {
        $this->model = new Data43RecordModel($db);
        $this->crypto = new Data43CryptoService();
    }

    public function schemaReady(): bool
    {
        return $this->model->schemaReady();
    }

    public function getHospitalDefaults(int $hospitalId): array
    {
        $stmt=$this->db->prepare("SELECT hospital_code,hospital_code9 FROM hospitals WHERE id=? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$hospitalId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'HOSPCODE'=>trim((string)($row['hospital_code'] ?? '')),
            'HOSPCODE9'=>trim((string)($row['hospital_code9'] ?? '')),
        ];
    }

    public function save(int $hospitalId,int $userId,string $fileCode,array $input,?int $recordId=null): array
    {
        $schema=Data43FormRegistry::get($fileCode);
        if(!$schema) throw new RuntimeException('ยังไม่รองรับแบบฟอร์มแฟ้ม '.$fileCode);

        $defaults=$this->getHospitalDefaults($hospitalId);
        $data=[];
        foreach($schema['fields'] as $field){
            $name=$field['name'];
            $data[$name]=isset($input[$name]) ? trim((string)$input[$name]) : '';
        }

        $data['HOSPCODE']=$defaults['HOSPCODE'];
        if(array_key_exists('HOSPCODE9',$data)) $data['HOSPCODE9']=$defaults['HOSPCODE9'];
        $data['D_UPDATE']=date('YmdHis');

        if($fileCode==='PERSON' && $data['PID']==='') $data['PID']=$this->model->generatePid($hospitalId);
        if($fileCode==='HOME' && $data['HID']==='') $data['HID']=$this->model->generateHid($hospitalId);

        foreach($data as $name=>$value){
            if(str_starts_with($name,'DATE_') || in_array($name,['BIRTH','DDISCHARGE','DDEATH','DATE_DIAG','DATE_SERV','DATE_DETECT'],true)){
                if($value!=='' && !preg_match('/^\d{8}$/',$value)){
                    $converted=Data43ValidationService::buddhistToDate8($value);
                    if($converted!==null) $data[$name]=$converted;
                }
            }
        }
        if(isset($data['VILLAGE'])) $data['VILLAGE']=Data43ValidationService::normalizeVillage($data['VILLAGE']) ?? '';

        if(in_array($fileCode,['ADDRESS','CHRONIC','DEATH'],true) && !empty($data['PID'])){
            $person=$this->findPersonByPid($hospitalId,$data['PID']);
            if(!$person) throw new RuntimeException('ไม่พบ PERSON ของ PID ที่เลือก');
            if(array_key_exists('CID',$data)) $data['CID']=(string)($person['CID'] ?? '');
        }

        $errors=Data43ValidationService::validateRecord($fileCode,$data);
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

        $cidHash=$this->crypto->cidHash($data['CID'] ?? null);
        if($fileCode==='PERSON' && $cidHash && $this->model->existsCidHash($hospitalId,$cidHash,$recordId)){
            throw new RuntimeException('พบ CID ซ้ำในทะเบียน PERSON');
        }

        $encrypted=$this->crypto->encrypt($data);
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
            $full=$this->model->find((int)$row['id'],$hospitalId);
            $payload=$full ? $this->decodeRow($full)['data'] : [];
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
        $token=$this->crypto->blindToken($query);
        $rows=$this->model->searchByToken($hospitalId,$type,$token,20);
        $out=[];
        foreach($rows as $row){
            $decoded=$this->decodeRow($row);
            $data=$decoded['data'];
            $out[]=[
                'id'=>(int)$row['id'],
                'pid'=>$data['PID'] ?? null,
                'hid'=>$data['HID'] ?? null,
                'cid'=>$type==='PERSON' ? ($data['CID'] ?? null) : null,
                'label'=>$this->displayLabel($type,$data),
            ];
        }
        return $out;
    }

    public function personProfile(int $hospitalId,string $pid): array
    {
        $rows=$this->model->listByPid($hospitalId,$pid);
        $grouped=[];
        foreach($rows as $row){
            $decoded=$this->decodeRow($row);
            $grouped[$row['file_code']][]=$decoded;
        }
        return $grouped;
    }

    public function exportFile(int $hospitalId,int $userId,string $fileCode,string $format,string $workDir): string
    {
        $headers=Data43FormRegistry::exportHeaders($fileCode);
        if(!$headers) throw new RuntimeException('แฟ้มนี้ยังไม่มี Export Schema');
        $rows=$this->model->listForExport($hospitalId,$fileCode);
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

    public function delete(int $hospitalId,int $userId,int $id): void
    {
        $row=$this->model->find($id,$hospitalId);
        if(!$row) throw new RuntimeException('ไม่พบข้อมูล');
        $this->model->softDelete($id,$hospitalId,$userId);
        $this->model->addAudit($id,$hospitalId,(string)$row['file_code'],'DELETE',$userId,(string)$row['record_key_hash'],[]);
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
            $enc=$this->crypto->encrypt($data);
            $schema=Data43FormRegistry::get('PERSON');
            $recordKey=$this->recordKey($hospitalId,$schema['pk'],$data);
            $this->model->update((int)$row['id'],$hospitalId,[
                ':record_key_hash'=>$recordKey,':pid_ref'=>$pid,':hid_ref'=>$data['HID']??null,
                ':cid_hash'=>$this->crypto->cidHash($data['CID']??null),':payload_ciphertext'=>$enc['ciphertext'],
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
                $tokens=array_merge($tokens,$this->crypto->prefixTokens($value));
            }
            $this->model->replaceSearchTokens($recordId,'PERSON',$tokens);
        }elseif($fileCode==='HOME'){
            foreach([(string)($data['HID']??''),(string)($data['HOUSE']??'')] as $value){
                $tokens=array_merge($tokens,$this->crypto->prefixTokens($value));
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
            'data'=>$this->crypto->decrypt((string)$row['payload_ciphertext'],(string)$row['payload_nonce'],(string)$row['payload_algorithm']),
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
            default=>$fileCode.' · '.($data['PID']??$data['HID']??''),
        };
    }
}
?>