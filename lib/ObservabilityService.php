<?php
declare(strict_types=1);

require_once __DIR__ . '/MigrationManager.php';
require_once __DIR__ . '/../models/AppEventModel.php';
require_once __DIR__ . '/../models/BackgroundJobModel.php';
require_once __DIR__ . '/../models/DisasterRecoveryDrillModel.php';

final class ObservabilityService {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function currentSummary(): array {
        $migration = (new MigrationManager($this->db))->summary();
        $queue = (new BackgroundJobModel($this->db))->summary();
        $events = (new AppEventModel($this->db))->summary();
        $dr = $this->disasterRecoverySummary();

        $diskFree = @disk_free_space(dirname(__DIR__) . '/storage');
        $diskFreeMb = ($diskFree === false) ? null : (int)floor($diskFree / 1024 / 1024);

        $overall = 'OK';
        if (
            (int)$migration['blocking'] > 0
            || (int)$events['critical_open'] > 0
            || (($dr['enforced'] ?? false) && ($dr['status'] ?? 'UNKNOWN') === 'FAIL')
        ) {
            $overall = 'UNHEALTHY';
        } elseif (
            (int)$migration['pending'] > 0
            || (int)$queue['failed'] > 0
            || (int)$queue['delayed'] > 0
            || (int)$events['error_open'] > 0
            || ($diskFreeMb !== null && $diskFreeMb < 512)
            || (($dr['enforced'] ?? false) && ($dr['status'] ?? 'UNKNOWN') !== 'PASS')
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
            'disaster_recovery' => $dr,
        ];
    }

    public function captureHealthSnapshot(): int {
        $summary = $this->currentSummary();

        $stmt = $this->db->prepare(
            "INSERT INTO system_health_snapshots
                (overall_status, db_status, migration_pending, migration_blocking,
                 queue_pending, queue_failed, open_errors_24h, disk_free_mb,
                 dr_status, dr_age_hours, dr_rpo_seconds, dr_rto_ms, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
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
            (string)($summary['disaster_recovery']['status'] ?? 'UNKNOWN'),
            $summary['disaster_recovery']['last_success_age_hours'] ?? null,
            $summary['disaster_recovery']['latest_successful']['rpo_seconds'] ?? null,
            $summary['disaster_recovery']['latest_successful']['rto_ms'] ?? null,
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


    public function recentRecoveryDrills(int $limit = 20): array {
        $model = new DisasterRecoveryDrillModel($this->db);
        if (!$model->tableExists()) {
            return [];
        }
        return $model->recent($limit);
    }

    private function disasterRecoverySummary(): array {
        $model = new DisasterRecoveryDrillModel($this->db);
        $maxAgeDays = max(1, min(365, (int)(getenv('DR_MAX_DRILL_AGE_DAYS') ?: 7)));
        $maxRpoSeconds = max(60, min(2592000, (int)(getenv('DR_MAX_RPO_SECONDS') ?: 86400)));
        $maxRtoSeconds = max(10, min(86400, (int)(getenv('DR_MAX_RTO_SECONDS') ?: 900)));
        $enforced = filter_var(getenv('DR_ENFORCE_HEALTH') ?: '0', FILTER_VALIDATE_BOOLEAN);

        if (!$model->tableExists()) {
            return [
                'status' => 'UNKNOWN',
                'enforced' => $enforced,
                'max_drill_age_days' => $maxAgeDays,
                'rpo_target_seconds' => $maxRpoSeconds,
                'rto_target_ms' => $maxRtoSeconds * 1000,
                'latest' => null,
                'latest_successful' => null,
                'last_success_age_hours' => null,
                'total' => 0,
                'failed_30d' => 0,
            ];
        }

        $summary = $model->summary();
        $latest = $summary['latest'];
        $latestSuccessful = $summary['latest_successful'];
        $ageHours = null;

        if (is_array($latestSuccessful) && !empty($latestSuccessful['completed_at'])) {
            $completed = new DateTimeImmutable((string)$latestSuccessful['completed_at']);
            $ageHours = max(0, (int)floor((time() - $completed->getTimestamp()) / 3600));
        }

        $status = 'PASS';
        if (!is_array($latestSuccessful)) {
            $status = 'UNKNOWN';
        } elseif ($ageHours !== null && $ageHours > ($maxAgeDays * 24)) {
            $status = 'STALE';
        } elseif ((int)($latestSuccessful['rpo_seconds'] ?? PHP_INT_MAX) > $maxRpoSeconds) {
            $status = 'RPO_EXCEEDED';
        } elseif ((int)($latestSuccessful['rto_ms'] ?? PHP_INT_MAX) > ($maxRtoSeconds * 1000)) {
            $status = 'RTO_EXCEEDED';
        }

        if (is_array($latest) && strtoupper((string)($latest['status'] ?? '')) !== 'PASS') {
            $status = 'FAIL';
        }

        return [
            'status' => $status,
            'enforced' => $enforced,
            'max_drill_age_days' => $maxAgeDays,
            'rpo_target_seconds' => $maxRpoSeconds,
            'rto_target_ms' => $maxRtoSeconds * 1000,
            'latest' => $latest,
            'latest_successful' => $latestSuccessful,
            'last_success_age_hours' => $ageHours,
            'total' => (int)($summary['total'] ?? 0),
            'failed_30d' => (int)($summary['failed_30d'] ?? 0),
        ];
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
