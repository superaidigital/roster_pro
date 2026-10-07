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


    public function getActiveHospitalCount(?int $hospitalId = null): int {
        $sql = "
            SELECT COUNT(*)
            FROM hospitals
            WHERE is_active = 1
              AND deleted_at IS NULL
              AND COALESCE(hospital_code, '') <> '0'
        ";
        $params = [];

        if ($hospitalId !== null) {
            $sql .= " AND id = ?";
            $params[] = $hospitalId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getLatestStatusRows(string $reportMonth, ?int $hospitalId = null): array {
        $sql = "
            SELECT s.*,
                   h.name AS hospital_name,
                   h.hospital_code,
                   u.name AS uploaded_by_name,
                   COALESCE(rc.submission_count, 1) AS submission_count
            FROM data43_submissions s
            JOIN (
                SELECT hospital_id, report_month, MAX(id) AS latest_id
                FROM data43_submissions
                WHERE report_month = ?
                GROUP BY hospital_id, report_month
            ) latest ON latest.latest_id = s.id
            JOIN hospitals h ON s.hospital_id = h.id
            JOIN users u ON s.uploaded_by = u.id
            LEFT JOIN (
                SELECT hospital_id, report_month, COUNT(*) AS submission_count
                FROM data43_submissions
                WHERE report_month = ?
                GROUP BY hospital_id, report_month
            ) rc
              ON rc.hospital_id = s.hospital_id
             AND rc.report_month = s.report_month
            WHERE h.is_active = 1
              AND h.deleted_at IS NULL
              AND COALESCE(h.hospital_code, '') <> '0'
        ";
        $params = [$reportMonth, $reportMonth];

        if ($hospitalId !== null) {
            $sql .= " AND s.hospital_id = ? ";
            $params[] = $hospitalId;
        }

        $sql .= " ORDER BY h.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHospitalTracking(string $reportMonth, ?int $hospitalId = null): array {
        $sql = "
            SELECT h.id AS hospital_id,
                   h.hospital_code,
                   h.name AS hospital_name,
                   s.id AS submission_id,
                   s.status,
                   s.detected_files,
                   s.expected_files,
                   s.total_rows,
                   s.uploaded_at,
                   s.completed_at,
                   s.error_summary,
                   u.name AS uploaded_by_name,
                   COALESCE(rc.submission_count, 0) AS submission_count
            FROM hospitals h
            LEFT JOIN (
                SELECT ds.*
                FROM data43_submissions ds
                JOIN (
                    SELECT hospital_id, report_month, MAX(id) AS latest_id
                    FROM data43_submissions
                    WHERE report_month = ?
                    GROUP BY hospital_id, report_month
                ) latest ON latest.latest_id = ds.id
            ) s
              ON s.hospital_id = h.id
             AND s.report_month = ?
            LEFT JOIN users u ON s.uploaded_by = u.id
            LEFT JOIN (
                SELECT hospital_id, report_month, COUNT(*) AS submission_count
                FROM data43_submissions
                WHERE report_month = ?
                GROUP BY hospital_id, report_month
            ) rc
              ON rc.hospital_id = h.id
             AND rc.report_month = ?
            WHERE h.is_active = 1
              AND h.deleted_at IS NULL
              AND COALESCE(h.hospital_code, '') <> '0'
        ";
        $params = [$reportMonth, $reportMonth, $reportMonth, $reportMonth];

        if ($hospitalId !== null) {
            $sql .= " AND h.id = ? ";
            $params[] = $hospitalId;
        }

        $sql .= " ORDER BY
                    CASE WHEN s.id IS NULL THEN 0 ELSE 1 END ASC,
                    CASE s.status
                        WHEN 'FAILED' THEN 1
                        WHEN 'INCOMPLETE' THEN 2
                        WHEN 'PROCESSING' THEN 3
                        WHEN 'COMPLETE' THEN 4
                        ELSE 0
                    END ASC,
                    h.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTrend(?int $hospitalId = null, int $months = 12): array {
        $months = max(1, min($months, 24));

        $sql = "
            SELECT s.report_month,
                   COUNT(*) AS submitted,
                   SUM(s.status = 'COMPLETE') AS complete_count,
                   SUM(s.status = 'INCOMPLETE') AS incomplete_count,
                   SUM(s.status = 'FAILED') AS failed_count,
                   COALESCE(SUM(s.detected_files), 0) AS detected_files_sum,
                   COALESCE(SUM(s.expected_files), 0) AS expected_files_sum,
                   COALESCE(SUM(s.total_rows), 0) AS total_rows
            FROM data43_submissions s
            JOIN (
                SELECT hospital_id, report_month, MAX(id) AS latest_id
                FROM data43_submissions
                GROUP BY hospital_id, report_month
            ) latest ON latest.latest_id = s.id
            JOIN hospitals h ON s.hospital_id = h.id
            WHERE h.is_active = 1
              AND h.deleted_at IS NULL
              AND COALESCE(h.hospital_code, '') <> '0'
        ";
        $params = [];

        if ($hospitalId !== null) {
            $sql .= " AND s.hospital_id = ? ";
            $params[] = $hospitalId;
        }

        $sql .= " GROUP BY s.report_month
                  ORDER BY s.report_month DESC
                  LIMIT {$months}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getLowestFileCoverage(string $reportMonth, ?int $hospitalId = null, int $limit = 10): array {
        $limit = max(1, min($limit, 43));

        $sql = "
            SELECT f.file_code,
                   COUNT(DISTINCT s.hospital_id) AS hospital_count,
                   SUM(CASE WHEN f.status = 'VALID' THEN 1 ELSE 0 END) AS valid_entries
            FROM data43_submission_files f
            JOIN data43_submissions s ON f.submission_id = s.id
            JOIN (
                SELECT hospital_id, report_month, MAX(id) AS latest_id
                FROM data43_submissions
                WHERE report_month = ?
                GROUP BY hospital_id, report_month
            ) latest ON latest.latest_id = s.id
            WHERE s.report_month = ?
        ";
        $params = [$reportMonth, $reportMonth];

        if ($hospitalId !== null) {
            $sql .= " AND s.hospital_id = ? ";
            $params[] = $hospitalId;
        }

        $sql .= "
            GROUP BY f.file_code
            ORDER BY hospital_count ASC, f.file_code ASC
            LIMIT {$limit}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMonthSubmissionAttempts(string $reportMonth, ?int $hospitalId = null): int {
        $sql = "
            SELECT COUNT(*)
            FROM data43_submissions s
            JOIN hospitals h ON s.hospital_id = h.id
            WHERE s.report_month = ?
              AND h.is_active = 1
              AND h.deleted_at IS NULL
              AND COALESCE(h.hospital_code, '') <> '0'
        ";
        $params = [$reportMonth];

        if ($hospitalId !== null) {
            $sql .= " AND s.hospital_id = ? ";
            $params[] = $hospitalId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
?>