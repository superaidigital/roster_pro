<?php
/**
 * Facility-scoped patient access from a Data43 map.
 * This queries the MANUAL encrypted PERSON/ADDRESS registry, NOT raw ZIP aggregates.
 * All patient data is returned only in server-rendered, authenticated POST responses.
 */
final class Data43MapPatientService
{
    public function __construct(private PDO $db) {}

    public function schemaReady(): bool
    {
        try {
            foreach (['data43_records','data43_patient_view_grants','data43_patient_area_index','data43_patient_access_audit'] as $table) {
                $stmt=$this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
                $stmt->execute([$table]);
                if((int)$stmt->fetchColumn()!==1)return false;
            }
            return true;
        } catch (Throwable $e) {return false;}
    }

    public static function parseArea(string $level,string $code): array
    {
        $widths=['CHANGWAT'=>2,'AMPUR'=>4,'TAMBON'=>6];
        if(!isset($widths[$level])||!preg_match('/^[0-9]{'.$widths[$level].'}$/D',$code)) {
            throw new InvalidArgumentException('รหัสพื้นที่จังหวัด/อำเภอ/ตำบลไม่ถูกต้อง');
        }
        return [$level,$code];
    }

    public static function addressCode(array $data): ?string
    {
        $fields=['CHANGWAT','AMPUR','TAMBON'];
        $parts=[];
        foreach($fields as $name){
            $part=trim((string)($data[$name]??''));
            if(!preg_match('/^[0-9]{1,2}$/D',$part))return null;
            $parts[]=str_pad($part,2,'0',STR_PAD_LEFT);
        }
        return implode('',$parts);
    }

    public function syncAddress(int $recordId,int $hospitalId,array $address): void
    {
        if(!$this->schemaReady())return; // Migration is optional until installed.
        $code=self::addressCode($address);
        if($code===null){
            $this->deleteAddress($recordId,$hospitalId);
            return;
        }
        $stmt=$this->db->prepare("
            INSERT INTO data43_patient_area_index(address_record_id,hospital_id,area_code)
            VALUES(?,?,?) ON DUPLICATE KEY UPDATE
            hospital_id=VALUES(hospital_id),area_code=VALUES(area_code)");
        $stmt->execute([$recordId,$hospitalId,$code]);
    }

    public function deleteAddress(int $recordId,int $hospitalId): void
    {
        if(!$this->schemaReady())return;
        $stmt=$this->db->prepare("DELETE FROM data43_patient_area_index WHERE address_record_id=? AND hospital_id=?");
        $stmt->execute([$recordId,$hospitalId]);
    }

    public function grantedHospitals(int $userId,string $role,int $sessionHospital): array
    {
        if(!$this->schemaReady()||$userId<=0)return [];
        $sql="SELECT DISTINCT h.id,h.name,h.hospital_code
              FROM data43_patient_view_grants g
              JOIN hospitals h ON h.id=g.hospital_id AND h.deleted_at IS NULL AND h.is_active=1
              WHERE g.user_id=? AND g.revoked_at IS NULL";
        $params=[$userId];
        if(!in_array($role,['ADMIN','SUPERADMIN'],true)){
            if($sessionHospital<=0)return [];
            $sql.=" AND g.hospital_id=?";
            $params[]=$sessionHospital;
        }
        $sql.=" ORDER BY h.name ASC";
        $stmt=$this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assertGrant(int $userId,int $hospitalId,string $role,int $sessionHospital,string $purpose): void
    {
        if(!in_array($purpose,['CARE','PUBLIC_HEALTH'],true))throw new InvalidArgumentException('กรุณาระบุวัตถุประสงค์การเข้าถึง');
        if($hospitalId<=0||$userId<=0||(!in_array($role,['ADMIN','SUPERADMIN'],true)&&$sessionHospital!==$hospitalId)){
            throw new RuntimeException('ไม่มีสิทธิ์เข้าถึงข้อมูลผู้ป่วยของหน่วยบริการนี้',403);
        }
        if(!$this->schemaReady())throw new RuntimeException('ยังไม่ได้ติดตั้งฐานข้อมูลสิทธิ์ดูผู้ป่วย');
        $stmt=$this->db->prepare("
          SELECT 1 FROM data43_patient_view_grants g
          JOIN hospitals h ON h.id=g.hospital_id AND h.is_active=1 AND h.deleted_at IS NULL
          WHERE g.user_id=? AND g.hospital_id=? AND g.purpose_code=? AND g.revoked_at IS NULL LIMIT 1");
        $stmt->execute([$userId,$hospitalId,$purpose]);
        if(!$stmt->fetchColumn())throw new RuntimeException('ไม่มีสิทธิ์ดูข้อมูลผู้ป่วยสำหรับวัตถุประสงค์นี้',403);
    }

    public function patientsInArea(int $hospitalId,string $code,int $limit=50): array
    {
        $len=strlen($code);
        if(!in_array($len,[2,4,6],true))throw new InvalidArgumentException('รหัสพื้นที่ไม่ถูกต้อง');
        $limit=max(1,min($limit,50));
        $stmt=$this->db->prepare("
            SELECT p.id,p.pid_ref,p.payload_ciphertext,p.payload_nonce,p.payload_algorithm
            FROM data43_patient_area_index idx
            JOIN data43_records a ON a.id=idx.address_record_id AND a.hospital_id=idx.hospital_id
               AND a.file_code='ADDRESS' AND a.deleted_at IS NULL
            JOIN data43_records p ON p.hospital_id=idx.hospital_id AND p.file_code='PERSON'
               AND p.pid_ref=a.pid_ref AND p.deleted_at IS NULL
            WHERE idx.hospital_id=:hospital AND LEFT(idx.area_code,:len)=:area
            GROUP BY p.id,p.pid_ref,p.payload_ciphertext,p.payload_nonce,p.payload_algorithm
            ORDER BY p.id DESC LIMIT {$limit}");
        $stmt->bindValue(':hospital',$hospitalId,PDO::PARAM_INT);
        $stmt->bindValue(':len',$len,PDO::PARAM_INT);
        $stmt->bindValue(':area',$code);
        $stmt->execute();
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $crypto=new Data43CryptoService();
        $out=[];
        foreach($rows as $row){
            $data=$crypto->decrypt((string)$row['payload_ciphertext'],(string)$row['payload_nonce'],(string)$row['payload_algorithm']);
            $out[]=[
                'id'=>(int)$row['id'],
                'name'=>trim((string)($data['PRENAME']??'').' '.(string)($data['NAME']??'').' '.(string)($data['LNAME']??'')),
                'sex'=>(string)($data['SEX']??''),
                'birth'=>(string)($data['BIRTH']??'')
            ];
        }
        return $out;
    }

    /** Confirm this person is actively linked to an address within the authorized area. */
    public function assertPersonInArea(int $hospitalId,string $code,int $personId): string
    {
        $stmt=$this->db->prepare("
          SELECT p.pid_ref FROM data43_records p
          JOIN data43_records a ON a.hospital_id=p.hospital_id AND a.pid_ref=p.pid_ref
              AND a.file_code='ADDRESS' AND a.deleted_at IS NULL
          JOIN data43_patient_area_index i ON i.address_record_id=a.id AND i.hospital_id=p.hospital_id
          WHERE p.id=? AND p.hospital_id=? AND p.file_code='PERSON' AND p.deleted_at IS NULL
              AND LEFT(i.area_code,?)=? LIMIT 1");
        $stmt->execute([$personId,$hospitalId,strlen($code),$code]);
        $pid=$stmt->fetchColumn();
        if(!is_string($pid)||$pid==='')throw new RuntimeException('ไม่พบผู้ป่วยที่ได้รับอนุญาตในพื้นที่นี้',404);
        return $pid;
    }

    public function audit(int $userId,int $hospitalId,string $code,string $purpose,string $action,?int $personRecordId=null): void
    {
        $stmt=$this->db->prepare("
          INSERT INTO data43_patient_access_audit
          (user_id,hospital_id,area_code,purpose_code,action_code,person_record_id)
          VALUES(?,?,?,?,?,?)");
        $stmt->execute([$userId,$hospitalId,$code,$purpose,$action,$personRecordId]);
    }
}
