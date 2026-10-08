<?php
class LeaveTemplateModel {
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function schemaReady(): bool {
        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE 'leave_form_templates'");
            if (!$stmt->fetchColumn()) return false;
            $table=$this->conn->query("SHOW TABLES LIKE 'leave_template_types'");
            $column=$this->conn->query("SHOW COLUMNS FROM leave_form_templates LIKE 'sort_order'");
            return (bool)$table->fetchColumn() && (bool)$column->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function getAllTemplates(): array {
        if (!$this->schemaReady()) return [];

        $stmt = $this->conn->query("
            SELECT t.*,
                   lq.leave_type,
                   h.name AS hospital_name,
                   u.name AS created_by_name,
                   (SELECT GROUP_CONCAT(tt.leave_type_id ORDER BY tt.leave_type_id SEPARATOR ',')
                    FROM leave_template_types tt WHERE tt.template_id=t.id) AS leave_type_ids,
                   (SELECT GROUP_CONCAT(lqt.leave_type ORDER BY lqt.id SEPARATOR ' • ')
                    FROM leave_template_types tt JOIN leave_quotas lqt ON lqt.id=tt.leave_type_id
                    WHERE tt.template_id=t.id) AS leave_types_label
            FROM leave_form_templates t
            LEFT JOIN leave_quotas lq ON t.leave_type_id = lq.id
            LEFT JOIN hospitals h ON t.hospital_id = h.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.archived_at IS NULL
            ORDER BY t.sort_order ASC, t.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createTemplate(array $data): int {
        $stmt = $this->conn->prepare("
            INSERT INTO leave_form_templates
                (template_name, leave_type_id, hospital_id, file_type,
                 original_filename, stored_path, version, is_active,
                 mapping_status, notes, created_by)
            VALUES
                (:template_name, :leave_type_id, :hospital_id, :file_type,
                 :original_filename, :stored_path, :version, 1,
                 :mapping_status, :notes, :created_by)
        ");
        $this->conn->beginTransaction();
        try {
        $stmt->execute([
            ':template_name' => $data['template_name'],
            ':leave_type_id' => $data['leave_type_id'] ?: null,
            ':hospital_id' => $data['hospital_id'] ?: null,
            ':file_type' => $data['file_type'],
            ':original_filename' => $data['original_filename'],
            ':stored_path' => $data['stored_path'],
            ':version' => (int)$data['version'],
            ':mapping_status' => $data['mapping_status'],
            ':notes' => $data['notes'] ?: null,
            ':created_by' => (int)$data['created_by'],
        ]);
        $id=(int)$this->conn->lastInsertId();
        $this->syncTypes($id,$data['leave_type_ids'] ?? []);
        $this->conn->commit();
        return $id;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    public function getNextVersion(?int $leaveTypeId, ?int $hospitalId, string $templateName): int {
        $stmt = $this->conn->prepare("
            SELECT COALESCE(MAX(version), 0) + 1
            FROM leave_form_templates
            WHERE template_name = ?
              AND (leave_type_id <=> ?)
              AND (hospital_id <=> ?)
        ");
        $stmt->execute([$templateName, $leaveTypeId, $hospitalId]);
        return max(1, (int)$stmt->fetchColumn());
    }

    public function findById(int $id): ?array {
        if (!$this->schemaReady()) return null;

        $stmt = $this->conn->prepare("
            SELECT t.*, lq.leave_type, h.name AS hospital_name
            FROM leave_form_templates t
            LEFT JOIN leave_quotas lq ON t.leave_type_id = lq.id
            LEFT JOIN hospitals h ON t.hospital_id = h.id
            WHERE t.id = ? AND t.archived_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function resolveActiveTemplate(int $leaveTypeId, ?int $hospitalId): ?array {
        if (!$this->schemaReady()) return null;

        $stmt = $this->conn->prepare("
            SELECT t.* FROM leave_form_templates t
            WHERE t.is_active = 1 AND t.archived_at IS NULL
              AND t.file_type = 'DOCX' AND t.mapping_status = 'READY'
              AND (t.hospital_id = ? OR t.hospital_id IS NULL)
              AND (
                  EXISTS (SELECT 1 FROM leave_template_types mt WHERE mt.template_id=t.id AND mt.leave_type_id=?)
                  OR (NOT EXISTS (SELECT 1 FROM leave_template_types mt WHERE mt.template_id=t.id)
                      AND (t.leave_type_id IS NULL OR t.leave_type_id=?))
              )
            ORDER BY (t.hospital_id IS NOT NULL) DESC,
                     (CASE WHEN EXISTS
                         (SELECT 1 FROM leave_template_types mt WHERE mt.template_id=t.id AND mt.leave_type_id=?)
                         OR t.leave_type_id=? THEN 1 ELSE 0 END) DESC,
                     t.sort_order ASC, t.version DESC, t.id DESC
            LIMIT 1
        ");
        $stmt->execute([$hospitalId,$leaveTypeId,$leaveTypeId,$leaveTypeId,$leaveTypeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function toggleActive(int $id, bool $active): bool {
        $stmt = $this->conn->prepare("
            UPDATE leave_form_templates
            SET is_active = ?, updated_at = NOW()
            WHERE id = ? AND archived_at IS NULL
        ");
        return $stmt->execute([$active ? 1 : 0, $id]);
    }

    public function archive(int $id): bool {
        $stmt = $this->conn->prepare("
            UPDATE leave_form_templates
            SET is_active = 0, archived_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }


    /** Empty selection means all leave types. Uses an atomic mapping replacement. */
    private function syncTypes(int $templateId,array $ids): void {
        $ids=array_values(array_unique(array_map('intval',$ids)));
        if (count($ids)>100) throw new InvalidArgumentException('เลือกประเภทการลามากเกินไป');
        foreach ($ids as $id) {
            if($id<=0) throw new InvalidArgumentException('ประเภทการลาไม่ถูกต้อง');
            $check=$this->conn->prepare('SELECT 1 FROM leave_quotas WHERE id=? LIMIT 1');
            $check->execute([$id]);
            if(!$check->fetchColumn()) throw new InvalidArgumentException('ไม่มีประเภทการลาที่เลือก');
        }
        $this->conn->prepare('DELETE FROM leave_template_types WHERE template_id=?')->execute([$templateId]);
        $insert=$this->conn->prepare('INSERT INTO leave_template_types(template_id,leave_type_id) VALUES (?,?)');
        foreach($ids as $id)$insert->execute([$templateId,$id]);
    }

    public function editTemplate(int $id,string $name,?int $hospitalId,string $notes,array $types): void {
        if(trim($name)==='' || mb_strlen($name,'UTF-8')>180 || mb_strlen($notes,'UTF-8')>500)
            throw new InvalidArgumentException('ชื่อหรือหมายเหตุไม่ถูกต้อง');
        $this->conn->beginTransaction();
        try{
            $stmt=$this->conn->prepare('UPDATE leave_form_templates SET template_name=?,hospital_id=?,leave_type_id=NULL,notes=? WHERE id=? AND archived_at IS NULL');
            $stmt->execute([$name,$hospitalId,$notes?:null,$id]);
            if(!$this->findById($id))throw new InvalidArgumentException('ไม่พบแบบฟอร์ม');
            $this->syncTypes($id,$types);
            $this->conn->commit();
        }catch(Throwable $e){if($this->conn->inTransaction())$this->conn->rollBack();throw $e;}
    }

    public function reorder(array $ids): void {
        $ids=array_values(array_map('intval',$ids));
        if(!$ids||count($ids)>500||count($ids)!==count(array_unique($ids)))
            throw new InvalidArgumentException('ลำดับแบบฟอร์มไม่ถูกต้อง');
        $existing=array_map('intval',$this->conn->query('SELECT id FROM leave_form_templates WHERE archived_at IS NULL')->fetchAll(PDO::FETCH_COLUMN));
        sort($existing);$given=$ids;sort($given);
        if($existing!==$given)throw new InvalidArgumentException('รายการเรียงไม่ตรงกับข้อมูลปัจจุบัน กรุณาโหลดหน้าใหม่');
        $this->conn->beginTransaction();
        try{
            $stmt=$this->conn->prepare('UPDATE leave_form_templates SET sort_order=? WHERE id=? AND archived_at IS NULL');
            foreach($ids as $i=>$id)$stmt->execute([$i+1,$id]);
            $this->conn->commit();
        }catch(Throwable $e){if($this->conn->inTransaction())$this->conn->rollBack();throw $e;}
    }

    public function getFields(int $id): array {
        $stmt=$this->conn->prepare('SELECT field_key,page_number,x,y,width,height,font_size,alignment FROM leave_form_fields WHERE template_id=? ORDER BY page_number,id');
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function replaceFields(int $id,array $fields,array $allowedKeys): void {
        if(count($fields)>120)throw new InvalidArgumentException('จำนวนฟิลด์เกิน 120 รายการ');
        $seen=[];$validated=[];
        foreach($fields as $field) {
            if(!is_array($field))throw new InvalidArgumentException('ฟิลด์ไม่ถูกต้อง');
            $key=(string)($field['field_key']??'');
            $page=filter_var($field['page_number']??null,FILTER_VALIDATE_INT);
            if(!in_array($key,$allowedKeys,true)||$page===false||$page<1||$page>100)
                throw new InvalidArgumentException('ชื่อฟิลด์หรือหน้ากระดาษไม่ถูกต้อง');
            $dup=$key.'|'.$page;
            if(isset($seen[$dup]))throw new InvalidArgumentException('ฟิลด์เดียวกันซ้ำในหน้าเดียวกัน');
            $seen[$dup]=true;
            $values=[];
            foreach(['x','y','width','height'] as $name){
                $v=$field[$name]??null;
                if(!is_numeric($v)||!is_finite((float)$v))throw new InvalidArgumentException('ตำแหน่งฟิลด์ไม่ถูกต้อง');
                $values[$name]=round((float)$v,2);
            }
            if($values['x']<0||$values['y']<0||$values['width']<2||$values['height']<1||
               $values['x']+$values['width']>100.01||$values['y']+$values['height']>100.01)
                throw new InvalidArgumentException('ฟิลด์อยู่นอกขอบกระดาษ');
            $validated[]=[$id,$key,$page,$values['x'],$values['y'],$values['width'],$values['height']];
        }
        $this->conn->beginTransaction();
        try {
            $this->conn->prepare('DELETE FROM leave_form_fields WHERE template_id=?')->execute([$id]);
            $stmt=$this->conn->prepare('INSERT INTO leave_form_fields(template_id,field_key,page_number,x,y,width,height) VALUES(?,?,?,?,?,?,?)');
            foreach($validated as $values)$stmt->execute($values);
            $this->conn->commit();
        }catch(Throwable $e){if($this->conn->inTransaction())$this->conn->rollBack();throw $e;}
    }

    public function recordGenerated(array $data): int {
        $stmt = $this->conn->prepare("
            INSERT INTO leave_generated_documents
                (leave_request_id, template_id, template_version,
                 document_path, original_filename, document_hash,
                 document_status, generated_by, finalized_at)
            VALUES
                (:leave_request_id, :template_id, :template_version,
                 :document_path, :original_filename, :document_hash,
                 :document_status, :generated_by, :finalized_at)
        ");
        $stmt->execute([
            ':leave_request_id' => (int)$data['leave_request_id'],
            ':template_id' => (int)$data['template_id'],
            ':template_version' => (int)$data['template_version'],
            ':document_path' => $data['document_path'],
            ':original_filename' => $data['original_filename'],
            ':document_hash' => $data['document_hash'],
            ':document_status' => $data['document_status'],
            ':generated_by' => (int)$data['generated_by'],
            ':finalized_at' => $data['finalized_at'] ?? null,
        ]);
        return (int)$this->conn->lastInsertId();
    }

    public function getGeneratedById(int $id): ?array {
        if (!$this->schemaReady()) return null;

        $stmt = $this->conn->prepare("
            SELECT d.*, lr.user_id, u.hospital_id
            FROM leave_generated_documents d
            JOIN leave_requests lr ON d.leave_request_id = lr.id
            JOIN users u ON lr.user_id = u.id
            WHERE d.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getLatestGeneratedForRequest(int $requestId): ?array {
        if (!$this->schemaReady()) return null;

        $stmt = $this->conn->prepare("
            SELECT *
            FROM leave_generated_documents
            WHERE leave_request_id = ?
            ORDER BY generated_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
?>