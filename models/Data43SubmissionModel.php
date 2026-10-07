<?php
class Data43SubmissionModel {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function schemaReady(): bool {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'data43_submissions'");
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function createSubmission(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO data43_submissions
                (hospital_id, report_month, original_filename, archive_sha256,
                 purpose_code, expected_files, detected_files, total_rows,
                 status, uploaded_by, client_ip_hash)
            VALUES
                (:hospital_id, :report_month, :original_filename, :archive_sha256,
                 :purpose_code, :expected_files, 0, 0,
                 'PROCESSING', :uploaded_by, :client_ip_hash)
        ");
        $stmt->execute([
            ':hospital_id' => (int)$data['hospital_id'],
            ':report_month' => $data['report_month'],
            ':original_filename' => $data['original_filename'],
            ':archive_sha256' => $data['archive_sha256'],
            ':purpose_code' => $data['purpose_code'] ?? 'PUBLIC_HEALTH_REPORTING',
            ':expected_files' => (int)($data['expected_files'] ?? 43),
            ':uploaded_by' => (int)$data['uploaded_by'],
            ':client_ip_hash' => $data['client_ip_hash'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function addFile(int $submissionId, array $file): void {
        $stmt = $this->db->prepare("
            INSERT INTO data43_submission_files
                (submission_id, file_code, original_filename, extension,
                 file_sha256, row_count, file_size, status, error_message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $submissionId,
            $file['file_code'],
            $file['original_filename'],
            $file['extension'],
            $file['file_sha256'],
            $file['row_count'],
            $file['file_size'],
            $file['status'],
            $file['error_message'],
        ]);
    }

    public function finishSubmission(
        int $id,
        string $status,
        int $detectedFiles,
        int $totalRows,
        ?string $errorSummary = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE data43_submissions
            SET status = ?,
                detected_files = ?,
                total_rows = ?,
                error_summary = ?,
                completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $detectedFiles, $totalRows, $errorSummary, $id]);
    }

    public function getHistory(?int $hospitalId, int $limit = 100): array {
        $limit = max(1, min($limit, 500));

        $sql = "
            SELECT s.*, h.name AS hospital_name, h.hospital_code, u.name AS uploaded_by_name
            FROM data43_submissions s
            JOIN hospitals h ON s.hospital_id = h.id
            JOIN users u ON s.uploaded_by = u.id
        ";
        $params = [];

        if ($hospitalId !== null) {
            $sql .= " WHERE s.hospital_id = ? ";
            $params[] = $hospitalId;
        }

        $sql .= " ORDER BY s.uploaded_at DESC, s.id DESC LIMIT {$limit}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSubmission(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT s.*, h.name AS hospital_name, h.hospital_code, u.name AS uploaded_by_name
            FROM data43_submissions s
            JOIN hospitals h ON s.hospital_id = h.id
            JOIN users u ON s.uploaded_by = u.id
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getFiles(int $submissionId): array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM data43_submission_files
            WHERE submission_id = ?
            ORDER BY file_code ASC, id ASC
        ");
        $stmt->execute([$submissionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>