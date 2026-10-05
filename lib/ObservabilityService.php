<?php
declare(strict_types=1);

require_once __DIR__ . '/MigrationManager.php';
require_once __DIR__ . '/../models/AppEventModel.php';
require_once __DIR__ . '/../models/BackgroundJobModel.php';

final class ObservabilityService {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function currentSummary(): array {
        $migration = (new MigrationManager($this->db))->summary();
        $queue = (new BackgroundJobModel($this->db))->summary();
        $events = (new AppEventModel($this->db))->summary();

        $diskFree = @disk_free_space(dirname(__DIR__) . '/storage');
        $diskFreeMb = ($diskFree === false) ? null : (int)floor($diskFree / 1024 / 1024);

        $overall = 'OK';
        if (
            (int)$migration['blocking'] > 0
            || (int)$events['critical_open'] > 0
        ) {
            $overall = 'UNHEALTHY';
        } elseif (
            (int)$migration['pending'] > 0
            || (int)$queue['failed'] > 0
            || (int)$queue['delayed'] > 0
            || (int)$events['error_open'] > 0
            || ($diskFreeMb !== null && $diskFreeMb < 512)
        ) {
            $overall = 'DEGRADED';
        }

        return [
            'overall_status' => $overall,
            'db_status' => 'OK',
            'migration' => $migration,
            'queue' => $queue,
            'events' => $events,
            'disk_free_mb' => $diskFreeMb,
        ];
    }

    public function captureHealthSnapshot(): int {
        $summary = $this->currentSummary();

        $stmt = $this->db->prepare(
            "INSERT INTO system_health_snapshots
                (overall_status, db_status, migration_pending, migration_blocking,
                 queue_pending, queue_failed, open_errors_24h, disk_free_mb, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $summary['overall_status'],
            $summary['db_status'],
            (int)$summary['migration']['pending'],
            (int)$summary['migration']['blocking'],
            (int)$summary['queue']['pending'],
            (int)$summary['queue']['failed'],
            (int)$summary['events']['open_24h'],
            $summary['disk_free_mb'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function recentSnapshots(int $limit = 48): array {
        $limit = max(1, min(500, $limit));
        $stmt = $this->db->prepare(
            "SELECT *
             FROM system_health_snapshots
             ORDER BY created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cleanup(int $eventRetentionDays = 90, int $jobRetentionDays = 30): array {
        $events = new AppEventModel($this->db);
        $jobs = new BackgroundJobModel($this->db);

        $eventRetentionDays = max(7, min(3650, $eventRetentionDays));
        $jobRetentionDays = max(7, min(3650, $jobRetentionDays));

        $eventDeleted = $events->deleteResolvedOlderThan($eventRetentionDays);
        $jobDeleted = $jobs->deleteCompletedOlderThan($jobRetentionDays);

        $snapshotThreshold = date('Y-m-d H:i:s', time() - (max(30, $eventRetentionDays) * 86400));
        $stmt = $this->db->prepare(
            "DELETE FROM system_health_snapshots WHERE created_at < ?"
        );
        $stmt->execute([$snapshotThreshold]);

        return [
            'events_deleted' => $eventDeleted,
            'jobs_deleted' => $jobDeleted,
            'snapshots_deleted' => $stmt->rowCount(),
        ];
    }
}
