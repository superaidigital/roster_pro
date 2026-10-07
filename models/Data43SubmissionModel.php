<?php
require_once __DIR__ . '/../services/Data43MetricRegistry.php';
require_once __DIR__ . '/../services/Data43PrivacyService.php';
class Data43SubmissionModel {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->ensureCompatibilitySchema();
    }

    /**
     * Keep older Data43 installations compatible with additive metadata fields.
     * This only adds missing nullable columns; it never drops/renames user data.
     * If the DB account cannot ALTER TABLE, the normal migration warning is used.
     */
    private function ensureCompatibilitySchema(): void {
        try {
            $table = $this->db->query("SHOW TABLES LIKE 'data43_submissions'")->fetchColumn();
            if (!$table) return;

            $stmt = $this->db->query("SHOW COLUMNS FROM data43_submissions");
            $columns = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            $alters = [];
            if (!in_array('standard_version', $columns, true)) {
                $alters[] = "ADD COLUMN standard_version VARCHAR(20) NULL AFTER purpose_code";
            }
            if (!in_array('profile_code', $columns, true)) {
                $after = in_array('standard_version', $columns, true) ? 'standard_version' : 'purpose_code';
                $alters[] = "ADD COLUMN profile_code VARCHAR(40) NULL AFTER {$after}";
            }

            if ($alters) {
                $this->db->exec("ALTER TABLE data43_submissions " . implode(', ', $alters));
            }
        } catch (Throwable $e) {
            error_log('Data43 compatibility schema check failed: ' . $e->getMessage());
        }
    }

    public function schemaReady(): bool {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'data43_submissions'");
            if (!(bool)$stmt->fetchColumn()) return false;

            $stmt = $this->db->query("SHOW COLUMNS FROM data43_submissions");
            $columns = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            foreach (['standard_version','profile_code'] as $required) {
                if (!in_array($required, $columns, true)) return false;
            }
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function findDuplicateArchive(int $hospitalId, string $reportMonth, string $archiveSha256): ?array {
        $stmt = $this->db->prepare("
            SELECT id, status, uploaded_at
            FROM data43_submissions
            WHERE hospital_id = ?
              AND report_month = ?
              AND archive_sha256 = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$hospitalId, $reportMonth, $archiveSha256]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createSubmission(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO data43_submissions
                (hospital_id, report_month, original_filename, archive_sha256,
                 purpose_code, standard_version, profile_code,
                 expected_files, detected_files, total_rows,
                 status, uploaded_by, client_ip_hash)
            VALUES
                (:hospital_id, :report_month, :original_filename, :archive_sha256,
                 :purpose_code, :standard_version, :profile_code,
                 :expected_files, 0, 0,
                 'PROCESSING', :uploaded_by, :client_ip_hash)
        ");
        $stmt->execute([
            ':hospital_id' => (int)$data['hospital_id'],
            ':report_month' => $data['report_month'],
            ':original_filename' => $data['original_filename'],
            ':archive_sha256' => $data['archive_sha256'],
            ':purpose_code' => $data['purpose_code'] ?? 'PUBLIC_HEALTH_REPORTING',
            ':standard_version' => $data['standard_version'] ?? null,
            ':profile_code' => $data['profile_code'] ?? null,
            ':expected_files' => (int)($data['expected_files'] ?? 45),
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

    public function qualitySchemaReady(): bool {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'data43_quality_summary'");
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function saveQualitySummary(int $submissionId, array $summary): void {
        if (!$this->qualitySchemaReady()) return;

        $stmt = $this->db->prepare("
            INSERT INTO data43_quality_summary
                (submission_id, standard_version, profile_code, catalog_count,
                 expected_files, detected_expected_files, linked_people, linked_homes,
                 unresolved_people, address_only_people, unknown_files_count,
                 header_issue_files, invalid_expected_files,
                 missing_codes_json, invalid_expected_codes_json, unknown_files_json, header_issues_json)
            VALUES
                (:submission_id, :standard_version, :profile_code, :catalog_count,
                 :expected_files, :detected_expected_files, :linked_people, :linked_homes,
                 :unresolved_people, :address_only_people, :unknown_files_count,
                 :header_issue_files, :invalid_expected_files,
                 :missing_codes_json, :invalid_expected_codes_json, :unknown_files_json, :header_issues_json)
            ON DUPLICATE KEY UPDATE
                standard_version = VALUES(standard_version),
                profile_code = VALUES(profile_code),
                catalog_count = VALUES(catalog_count),
                expected_files = VALUES(expected_files),
                detected_expected_files = VALUES(detected_expected_files),
                linked_people = VALUES(linked_people),
                linked_homes = VALUES(linked_homes),
                unresolved_people = VALUES(unresolved_people),
                address_only_people = VALUES(address_only_people),
                unknown_files_count = VALUES(unknown_files_count),
                header_issue_files = VALUES(header_issue_files),
                invalid_expected_files = VALUES(invalid_expected_files),
                missing_codes_json = VALUES(missing_codes_json),
                invalid_expected_codes_json = VALUES(invalid_expected_codes_json),
                unknown_files_json = VALUES(unknown_files_json),
                header_issues_json = VALUES(header_issues_json)
        ");
        $stmt->execute([
            ':submission_id' => $submissionId,
            ':standard_version' => (string)($summary['standard_version'] ?? '2.4.1'),
            ':profile_code' => (string)($summary['profile_code'] ?? 'RPHST_V241'),
            ':catalog_count' => (int)($summary['catalog_count'] ?? 0),
            ':expected_files' => (int)($summary['expected_files'] ?? 0),
            ':detected_expected_files' => (int)($summary['detected_expected_files'] ?? 0),
            ':linked_people' => (int)($summary['linked_people'] ?? 0),
            ':linked_homes' => (int)($summary['linked_homes'] ?? 0),
            ':unresolved_people' => (int)($summary['unresolved_people'] ?? 0),
            ':address_only_people' => (int)($summary['address_only_people'] ?? 0),
            ':unknown_files_count' => count((array)($summary['unknown_files'] ?? [])),
            ':header_issue_files' => count((array)($summary['header_issues'] ?? [])),
            ':invalid_expected_files' => count((array)($summary['invalid_expected_codes'] ?? [])),
            ':missing_codes_json' => json_encode(array_values((array)($summary['missing_expected_codes'] ?? [])), JSON_UNESCAPED_UNICODE),
            ':invalid_expected_codes_json' => json_encode(array_values((array)($summary['invalid_expected_codes'] ?? [])), JSON_UNESCAPED_UNICODE),
            ':unknown_files_json' => json_encode(array_values((array)($summary['unknown_files'] ?? [])), JSON_UNESCAPED_UNICODE),
            ':header_issues_json' => json_encode((array)($summary['header_issues'] ?? []), JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function getQualitySummary(int $submissionId): ?array {
        if (!$this->qualitySchemaReady()) return null;
        $stmt = $this->db->prepare("SELECT * FROM data43_quality_summary WHERE submission_id = ? LIMIT 1");
        $stmt->execute([$submissionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        foreach (['missing_codes_json','invalid_expected_codes_json','unknown_files_json','header_issues_json'] as $key) {
            $row[$key] = json_decode((string)($row[$key] ?? '[]'), true) ?: [];
        }
        return $row;
    }

    public function qualityIssueSchemaReady(): bool {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'data43_quality_issues'");
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function saveQualityIssues(int $submissionId, array $issues): void {
        if (!$this->qualityIssueSchemaReady()) return;

        $this->db->prepare("DELETE FROM data43_quality_issues WHERE submission_id = ?")->execute([$submissionId]);
        if (!$issues) return;

        $stmt = $this->db->prepare("
            INSERT INTO data43_quality_issues
                (submission_id, file_code, severity, rule_code, field_name, issue_count, sample_rows_json)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($issues as $issue) {
            $stmt->execute([
                $submissionId,
                (string)($issue['file_code'] ?? ''),
                (string)($issue['severity'] ?? 'WARNING'),
                (string)($issue['rule_code'] ?? 'UNKNOWN'),
                $issue['field_name'] ?? null,
                (int)($issue['issue_count'] ?? 0),
                json_encode(array_values((array)($issue['sample_rows'] ?? [])), JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function getQualityIssues(int $submissionId): array {
        if (!$this->qualityIssueSchemaReady()) return [];
        $stmt = $this->db->prepare("
            SELECT file_code, severity, rule_code, field_name, issue_count, sample_rows_json
            FROM data43_quality_issues
            WHERE submission_id = ?
            ORDER BY FIELD(severity,'ERROR','WARNING','INFO'), file_code, rule_code
        ");
        $stmt->execute([$submissionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['sample_rows'] = json_decode((string)($row['sample_rows_json'] ?? '[]'), true) ?: [];
            unset($row['sample_rows_json']);
        }
        unset($row);
        return $rows;
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
        $limit = max(1, min($limit, 52));

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


    public function spatialSchemaReady(): bool {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'data43_area_metrics'");
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function addSpatialMetrics(
        int $submissionId,
        int $hospitalId,
        string $reportMonth,
        array $metrics
    ): int {
        if (empty($metrics) || !$this->spatialSchemaReady()) {
            return 0;
        }

        $stmt = $this->db->prepare("
            INSERT INTO data43_area_metrics
                (submission_id, hospital_id, report_month, area_level,
                 changwat_code, ampur_code, tambon_code, village_code,
                 source_file_code, metric_code, metric_value,
                 centroid_lat, centroid_lng, geo_point_count)
            VALUES
                (:submission_id, :hospital_id, :report_month, :area_level,
                 :changwat_code, :ampur_code, :tambon_code, :village_code,
                 :source_file_code, :metric_code, :metric_value,
                 :centroid_lat, :centroid_lng, :geo_point_count)
        ");

        $count = 0;
        $this->db->beginTransaction();
        try {
            foreach ($metrics as $metric) {
                $stmt->execute([
                    ':submission_id' => $submissionId,
                    ':hospital_id' => $hospitalId,
                    ':report_month' => $reportMonth,
                    ':area_level' => $metric['area_level'],
                    ':changwat_code' => $metric['changwat_code'],
                    ':ampur_code' => $metric['ampur_code'],
                    ':tambon_code' => $metric['tambon_code'],
                    ':village_code' => $metric['village_code'],
                    ':source_file_code' => $metric['source_file_code'],
                    ':metric_code' => $metric['metric_code'],
                    ':metric_value' => (int)$metric['metric_value'],
                    ':centroid_lat' => $metric['centroid_lat'],
                    ':centroid_lng' => $metric['centroid_lng'],
                    ':geo_point_count' => (int)$metric['geo_point_count'],
                ]);
                $count++;
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $count;
    }

    public function getSpatialMetricOptions(string $reportMonth, ?int $hospitalId = null): array {
        if (!$this->spatialSchemaReady()) return [];

        $sql = "
            SELECT DISTINCT m.metric_code, m.source_file_code
            FROM data43_area_metrics m
            JOIN (
                SELECT hospital_id, report_month, MAX(id) AS latest_id
                FROM data43_submissions
                WHERE report_month = ?
                  AND status IN ('COMPLETE','INCOMPLETE')
                GROUP BY hospital_id, report_month
            ) latest ON latest.latest_id = m.submission_id
            WHERE m.report_month = ?
              AND m.metric_code IN ('DM','HT','NCD','ANC','ELDERLY','DISABLED','SERVICE','NCD_SCREEN','POPULATION','NCD_SCREEN_TARGET')
        ";
        $params = [$reportMonth, $reportMonth];

        if ($hospitalId !== null) {
            $sql .= " AND m.hospital_id = ? ";
            $params[] = $hospitalId;
        }

        $sql .= " ORDER BY FIELD(m.metric_code,'DM','HT','NCD','ELDERLY','DISABLED','ANC','SERVICE','NCD_SCREEN','POPULATION','NCD_SCREEN_TARGET'), m.source_file_code";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSpatialSummary(
        string $reportMonth,
        string $areaLevel,
        string $metricCode = 'DM',
        ?int $hospitalId = null,
        ?string $ampurCode = null,
        ?string $tambonCode = null
    ): array {
        if (!$this->spatialSchemaReady()) return [];

        $allowedLevels = ['CHANGWAT','AMPUR','TAMBON','VILLAGE'];
        if (!in_array($areaLevel, $allowedLevels, true)) $areaLevel = 'CHANGWAT';

        $metricDef = Data43MetricRegistry::get($metricCode) ?? Data43MetricRegistry::get('DM');
        $metricCode = $metricDef['code'];
        $denominatorCode = $metricDef['denominator'];

        $sql = "
            SELECT
                m.changwat_code, m.ampur_code, m.tambon_code, m.village_code,
                SUM(CASE WHEN m.metric_code = ? THEN m.metric_value ELSE 0 END) AS metric_value,
                SUM(CASE WHEN m.metric_code = 'POPULATION' THEN m.metric_value ELSE 0 END) AS population_value,
                SUM(CASE WHEN m.metric_code = ? THEN m.metric_value ELSE 0 END) AS denominator_value,
                SUM(CASE WHEN m.metric_code IN (?, 'GEO_REFERENCE') THEN m.geo_point_count ELSE 0 END) AS geo_point_count,
                CASE
                    WHEN SUM(CASE WHEN m.metric_code IN (?, 'GEO_REFERENCE') THEN m.geo_point_count ELSE 0 END) > 0
                    THEN SUM(CASE WHEN m.metric_code IN (?, 'GEO_REFERENCE') THEN COALESCE(m.centroid_lat,0) * m.geo_point_count ELSE 0 END)
                         / SUM(CASE WHEN m.metric_code IN (?, 'GEO_REFERENCE') THEN m.geo_point_count ELSE 0 END)
                    ELSE NULL
                END AS centroid_lat,
                CASE
                    WHEN SUM(CASE WHEN m.metric_code IN (?, 'GEO_REFERENCE') THEN m.geo_point_count ELSE 0 END) > 0
                    THEN SUM(CASE WHEN m.metric_code IN (?, 'GEO_REFERENCE') THEN COALESCE(m.centroid_lng,0) * m.geo_point_count ELSE 0 END)
                         / SUM(CASE WHEN m.metric_code IN (?, 'GEO_REFERENCE') THEN m.geo_point_count ELSE 0 END)
                    ELSE NULL
                END AS centroid_lng,
                COUNT(DISTINCT CASE WHEN m.metric_code = ? THEN m.hospital_id END) AS hospital_count,
                COUNT(DISTINCT CASE WHEN m.metric_code = ? THEN m.source_file_code END) AS source_file_count
            FROM data43_area_metrics m
            JOIN (
                SELECT hospital_id, report_month, MAX(id) AS latest_id
                FROM data43_submissions
                WHERE report_month = ? AND status IN ('COMPLETE','INCOMPLETE')
                GROUP BY hospital_id, report_month
            ) latest ON latest.latest_id = m.submission_id
            WHERE m.report_month = ?
              AND m.area_level = ?
              AND (m.metric_code = ? OR m.metric_code IN ('POPULATION','NCD_SCREEN_TARGET','GEO_REFERENCE'))
        ";
        $denParam = $denominatorCode ?? '__NO_DENOMINATOR__';
        $params = [
            $metricCode, $denParam,
            $metricCode, $metricCode, $metricCode, $metricCode,
            $metricCode, $metricCode, $metricCode,
            $metricCode, $metricCode,
            $reportMonth, $reportMonth, $areaLevel, $metricCode
        ];

        if ($hospitalId !== null) { $sql .= " AND m.hospital_id = ? "; $params[] = $hospitalId; }
        if ($ampurCode !== null && $ampurCode !== '') { $sql .= " AND m.ampur_code = ? "; $params[] = $ampurCode; }
        if ($tambonCode !== null && $tambonCode !== '') { $sql .= " AND m.tambon_code = ? "; $params[] = $tambonCode; }

        $sql .= "
            GROUP BY m.changwat_code,m.ampur_code,m.tambon_code,m.village_code
            HAVING metric_value > 0 OR population_value > 0 OR denominator_value > 0
            ORDER BY metric_value DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $value = (int)($row['metric_value'] ?? 0);
            $denominator = $denominatorCode === null ? null : (int)($row['denominator_value'] ?? 0);
            $row['display_value'] = Data43MetricRegistry::calculate($metricCode, $value, $denominator);
            $row['display_unit'] = $metricDef['unit'];
            $row['calculation'] = $metricDef['calculation'];
            $row['rate_per_1000'] = $metricDef['calculation'] === 'RATE_PER_1000' ? $row['display_value'] : null;
        }
        unset($row);

        return Data43PrivacyService::suppressSpatialRows(
            $rows,
            'metric_value',
            $denominatorCode !== null ? 'denominator_value' : null,
            $denominatorCode !== null ? Data43PrivacyService::DEFAULT_MIN_DENOMINATOR : 0
        );
    }

    public function getSpatialCoverageByHospital(
        string $reportMonth,
        string $areaLevel,
        string $metricCode = 'DM',
        ?int $hospitalId = null
    ): array {
        if (!$this->spatialSchemaReady()) return [];

        $allowedLevels = ['CHANGWAT','AMPUR','TAMBON','VILLAGE'];
        if (!in_array($areaLevel, $allowedLevels, true)) {
            $areaLevel = 'CHANGWAT';
        }

        $allowedMetrics = Data43MetricRegistry::allowedCodes();
        if (!in_array($metricCode, $allowedMetrics, true)) {
            $metricCode = 'DM';
        }

        $sql = "
            SELECT
                h.id AS hospital_id,
                h.hospital_code,
                h.name AS hospital_name,
                COUNT(DISTINCT CONCAT_WS(
                    '-',
                    COALESCE(m.changwat_code,''),
                    COALESCE(m.ampur_code,''),
                    COALESCE(m.tambon_code,''),
                    COALESCE(m.village_code,'')
                )) AS area_count,
                SUM(m.metric_value) AS metric_value
            FROM data43_area_metrics m
            JOIN (
                SELECT hospital_id, report_month, MAX(id) AS latest_id
                FROM data43_submissions
                WHERE report_month = ?
                  AND status IN ('COMPLETE','INCOMPLETE')
                GROUP BY hospital_id, report_month
            ) latest ON latest.latest_id = m.submission_id
            JOIN hospitals h ON h.id = m.hospital_id
            WHERE m.report_month = ?
              AND m.area_level = ?
              AND m.metric_code = ?
        ";
        $params = [$reportMonth, $reportMonth, $areaLevel, $metricCode];

        if ($hospitalId !== null) {
            $sql .= " AND m.hospital_id = ? ";
            $params[] = $hospitalId;
        }

        $sql .= "
            GROUP BY h.id, h.hospital_code, h.name
            ORDER BY metric_value DESC, h.name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return Data43PrivacyService::suppressRows($stmt->fetchAll(PDO::FETCH_ASSOC));
    }


    public function deleteSubmission(int $id): bool {
        if ($id <= 0) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            // Explicit deletes keep this compatible with databases where
            // foreign-key cascade rules may not have been applied yet.
            if ($this->spatialSchemaReady()) {
                $stmt = $this->db->prepare("DELETE FROM data43_area_metrics WHERE submission_id = ?");
                $stmt->execute([$id]);
            }

            $stmt = $this->db->prepare("DELETE FROM data43_submission_files WHERE submission_id = ?");
            $stmt->execute([$id]);

            $stmt = $this->db->prepare("DELETE FROM data43_submissions WHERE id = ?");
            $stmt->execute([$id]);

            $deleted = $stmt->rowCount() === 1;

            if ($deleted) {
                $this->db->commit();
                return true;
            }

            $this->db->rollBack();
            return false;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
?>