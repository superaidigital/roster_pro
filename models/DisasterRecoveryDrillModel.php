<?php
declare(strict_types=1);

final class DisasterRecoveryDrillModel {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function tableExists(): bool {
        $stmt = $this->db->query(
            "SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'disaster_recovery_drills'"
        );
        return (int)$stmt->fetchColumn() > 0;
    }

    public function record(array $data): int {
        $stmt = $this->db->prepare(
            "INSERT INTO disaster_recovery_drills
                (backup_filename, backup_sha256, backup_created_at,
                 source_database, restore_database, status,
                 started_at, completed_at, rpo_seconds, rto_ms,
                 tables_verified, critical_tables_verified,
                 failure_code, failure_message)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            mb_substr((string)$data['backup_filename'], 0, 255, 'UTF-8'),
            strtolower((string)$data['backup_sha256']),
            $data['backup_created_at'] ?? null,
            mb_substr((string)$data['source_database'], 0, 64, 'UTF-8'),
            mb_substr((string)$data['restore_database'], 0, 64, 'UTF-8'),
            strtoupper((string)$data['status']),
            (string)$data['started_at'],
            (string)$data['completed_at'],
            isset($data['rpo_seconds']) ? max(0, (int)$data['rpo_seconds']) : null,
            isset($data['rto_ms']) ? max(0, (int)$data['rto_ms']) : null,
            max(0, (int)($data['tables_verified'] ?? 0)),
            max(0, (int)($data['critical_tables_verified'] ?? 0)),
            isset($data['failure_code'])
                ? mb_substr((string)$data['failure_code'], 0, 80, 'UTF-8')
                : null,
            isset($data['failure_message'])
                ? mb_substr((string)$data['failure_message'], 0, 1000, 'UTF-8')
                : null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function latest(): ?array {
        $stmt = $this->db->query(
            "SELECT *
             FROM disaster_recovery_drills
             ORDER BY completed_at DESC, id DESC
             LIMIT 1"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function latestSuccessful(): ?array {
        $stmt = $this->db->query(
            "SELECT *
             FROM disaster_recovery_drills
             WHERE status = 'PASS'
             ORDER BY completed_at DESC, id DESC
             LIMIT 1"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function recent(int $limit = 20): array {
        $limit = max(1, min(200, $limit));
        $stmt = $this->db->prepare(
            "SELECT *
             FROM disaster_recovery_drills
             ORDER BY completed_at DESC, id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function summary(): array {
        if (!$this->tableExists()) {
            return [
                'latest' => null,
                'latest_successful' => null,
                'total' => 0,
                'failed_30d' => 0,
            ];
        }

        $latest = $this->latest();
        $latestSuccessful = $this->latestSuccessful();

        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total_drills,
                SUM(CASE
                    WHEN status <> 'PASS'
                     AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    THEN 1 ELSE 0
                END) AS failed_30d
             FROM disaster_recovery_drills"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'latest' => $latest,
            'latest_successful' => $latestSuccessful,
            'total' => (int)($row['total_drills'] ?? 0),
            'failed_30d' => (int)($row['failed_30d'] ?? 0),
        ];
    }
}
