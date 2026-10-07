<?php
class LeaveTemplateModel {
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function schemaReady(): bool {
        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE 'leave_form_templates'");
            return (bool)$stmt->fetchColumn();
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
                   u.name AS created_by_name
            FROM leave_form_templates t
            LEFT JOIN leave_quotas lq ON t.leave_type_id = lq.id
            LEFT JOIN hospitals h ON t.hospital_id = h.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.archived_at IS NULL
            ORDER BY t.is_active DESC, t.updated_at DESC, t.id DESC
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
        return (int)$this->conn->lastInsertId();
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
            SELECT *
            FROM leave_form_templates
            WHERE is_active = 1
              AND archived_at IS NULL
              AND file_type = 'DOCX'
              AND mapping_status = 'READY'
              AND (leave_type_id = ? OR leave_type_id IS NULL)
              AND (hospital_id = ? OR hospital_id IS NULL)
            ORDER BY
              (hospital_id IS NOT NULL) DESC,
              (leave_type_id IS NOT NULL) DESC,
              version DESC,
              id DESC
            LIMIT 1
        ");
        $stmt->execute([$leaveTypeId, $hospitalId]);
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