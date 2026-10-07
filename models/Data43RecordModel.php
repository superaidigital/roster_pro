<?php
class Data43RecordModel
{
    public function __construct(private PDO $db) {}

    public function schemaReady(): bool
    {
        try {
            foreach (['data43_records','data43_search_tokens','data43_record_audit'] as $table) {
                $stmt = $this->db->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                if (!$stmt->fetchColumn()) return false;
            }
            return true;
        } catch (Throwable $e) { return false; }
    }

    public function existsRecordKey(int $hospitalId, string $fileCode, string $recordKeyHash, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM data43_records WHERE hospital_id=? AND file_code=? AND record_key_hash=? AND deleted_at IS NULL";
        $params = [$hospitalId,$fileCode,$recordKeyHash];
        if ($excludeId) { $sql .= " AND id<>?"; $params[]=$excludeId; }
        $stmt=$this->db->prepare($sql); $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function existsCidHash(int $hospitalId, string $cidHash, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM data43_records WHERE hospital_id=? AND file_code='PERSON' AND cid_hash=? AND deleted_at IS NULL";
        $params=[$hospitalId,$cidHash];
        if($excludeId){$sql.=" AND id<>?";$params[]=$excludeId;}
        $stmt=$this->db->prepare($sql);$stmt->execute($params);
        return (int)$stmt->fetchColumn()>0;
    }

    public function insert(array $row): int
    {
        $stmt=$this->db->prepare("
            INSERT INTO data43_records
            (hospital_id,file_code,record_key_hash,pid_ref,hid_ref,cid_hash,payload_ciphertext,payload_nonce,payload_algorithm,payload_sha256,created_by,updated_by)
            VALUES (:hospital_id,:file_code,:record_key_hash,:pid_ref,:hid_ref,:cid_hash,:payload_ciphertext,:payload_nonce,:payload_algorithm,:payload_sha256,:created_by,:updated_by)
        ");
        $stmt->execute($row);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $hospitalId, array $row): void
    {
        $row[':id']=$id; $row[':hospital_id']=$hospitalId;
        $stmt=$this->db->prepare("
            UPDATE data43_records SET
                record_key_hash=:record_key_hash,pid_ref=:pid_ref,hid_ref=:hid_ref,cid_hash=:cid_hash,
                payload_ciphertext=:payload_ciphertext,payload_nonce=:payload_nonce,payload_algorithm=:payload_algorithm,
                payload_sha256=:payload_sha256,updated_by=:updated_by
            WHERE id=:id AND hospital_id=:hospital_id AND deleted_at IS NULL
        ");
        $stmt->execute($row);
        if($stmt->rowCount()===0) throw new RuntimeException('ไม่พบรายการที่ต้องการแก้ไข');
    }

    public function find(int $id, int $hospitalId): ?array
    {
        $stmt=$this->db->prepare("SELECT * FROM data43_records WHERE id=? AND hospital_id=? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$id,$hospitalId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listByFile(int $hospitalId, string $fileCode, int $limit=100): array
    {
        $limit=max(1,min(500,$limit));
        $stmt=$this->db->prepare("SELECT * FROM data43_records WHERE hospital_id=? AND file_code=? AND deleted_at IS NULL ORDER BY updated_at DESC,id DESC LIMIT {$limit}");
        $stmt->execute([$hospitalId,$fileCode]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listByPid(int $hospitalId, string $pid): array
    {
        $stmt=$this->db->prepare("SELECT * FROM data43_records WHERE hospital_id=? AND pid_ref=? AND deleted_at IS NULL ORDER BY file_code,id");
        $stmt->execute([$hospitalId,$pid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listByHid(int $hospitalId, string $hid): array
    {
        $stmt=$this->db->prepare("SELECT * FROM data43_records WHERE hospital_id=? AND hid_ref=? AND deleted_at IS NULL ORDER BY file_code,id");
        $stmt->execute([$hospitalId,$hid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listForExport(int $hospitalId, string $fileCode): array
    {
        $stmt=$this->db->prepare("SELECT * FROM data43_records WHERE hospital_id=? AND file_code=? AND deleted_at IS NULL ORDER BY id");
        $stmt->execute([$hospitalId,$fileCode]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function replaceSearchTokens(int $recordId, string $type, array $tokens): void
    {
        $this->db->prepare("DELETE FROM data43_search_tokens WHERE record_id=?")->execute([$recordId]);
        if(!$tokens) return;
        $stmt=$this->db->prepare("INSERT IGNORE INTO data43_search_tokens(record_id,token_type,token_hash) VALUES(?,?,?)");
        foreach(array_unique($tokens) as $token) $stmt->execute([$recordId,$type,$token]);
    }

    public function searchByToken(int $hospitalId, string $type, string $tokenHash, int $limit=20): array
    {
        $limit=max(1,min(50,$limit));
        $stmt=$this->db->prepare("
            SELECT DISTINCT r.*
            FROM data43_search_tokens s
            JOIN data43_records r ON r.id=s.record_id
            WHERE s.token_type=? AND s.token_hash=? AND r.hospital_id=? AND r.deleted_at IS NULL
            ORDER BY r.updated_at DESC LIMIT {$limit}
        ");
        $stmt->execute([$type,$tokenHash,$hospitalId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPidReferences(int $hospitalId, string $pid, ?int $excludeId = null): int
    {
        $sql = "SELECT COUNT(*) FROM data43_records
                WHERE hospital_id=? AND pid_ref=? AND file_code<>'PERSON' AND deleted_at IS NULL";
        $params = [$hospitalId, $pid];
        if ($excludeId) { $sql .= " AND id<>?"; $params[] = $excludeId; }
        $stmt=$this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function countHidReferences(int $hospitalId, string $hid, ?int $excludeId = null): int
    {
        $sql = "SELECT COUNT(*) FROM data43_records
                WHERE hospital_id=? AND hid_ref=? AND file_code='PERSON' AND deleted_at IS NULL";
        $params = [$hospitalId, $hid];
        if ($excludeId) { $sql .= " AND id<>?"; $params[] = $excludeId; }
        $stmt=$this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function softDelete(int $id,int $hospitalId,int $userId): void
    {
        // Rotate unique/searchable hashes so a logically deleted PK/CID can be
        // re-created without colliding with the tombstone row.
        $stmt=$this->db->prepare("
            UPDATE data43_records
            SET deleted_at=NOW(),
                updated_by=?,
                cid_hash=NULL,
                record_key_hash=SHA2(CONCAT(record_key_hash,'|DELETED|',id,'|',NOW(6)),256)
            WHERE id=? AND hospital_id=? AND deleted_at IS NULL
        ");
        $stmt->execute([$userId,$id,$hospitalId]);
        if($stmt->rowCount()===0) throw new RuntimeException('ไม่พบข้อมูลที่ต้องการลบ');
        $this->db->prepare("DELETE FROM data43_search_tokens WHERE record_id=?")->execute([$id]);
    }

    public function addAudit(?int $recordId,int $hospitalId,string $fileCode,string $action,int $userId,?string $recordKeyHash,array $changedFields=[]): void
    {
        $stmt=$this->db->prepare("
            INSERT INTO data43_record_audit(record_id,hospital_id,file_code,action,actor_user_id,record_key_hash,changed_fields_json)
            VALUES(?,?,?,?,?,?,?)
        ");
        $stmt->execute([$recordId,$hospitalId,$fileCode,$action,$userId,$recordKeyHash,
            $changedFields ? json_encode(array_values($changedFields),JSON_UNESCAPED_UNICODE) : null]);
    }

    public function generatePid(int $hospitalId): string
    {
        return strtoupper(substr(hash('sha256','PID|'.$hospitalId.'|'.microtime(true).'|'.random_bytes(8)),0,15));
    }

    public function generateHid(int $hospitalId): string
    {
        return strtoupper(substr(hash('sha256','HID|'.$hospitalId.'|'.microtime(true).'|'.random_bytes(8)),0,14));
    }
}
?>