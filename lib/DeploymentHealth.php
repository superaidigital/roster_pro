<?php
declare(strict_types=1);

require_once __DIR__ . '/MigrationManager.php';

final class DeploymentHealth {
    public static function check(PDO $db): array {
        $checks = [
            'database' => 'unknown',
            'migrations' => 'unknown',
            'queue' => 'unknown',
            'events' => 'unknown',
        ];

        try {
            $db->query('SELECT 1')->fetchColumn();
            $checks['database'] = 'ok';
        } catch (Throwable $e) {
            $checks['database'] = 'failed';
            return self::result('unhealthy', $checks, null, null);
        }

        try {
            $manager = new MigrationManager($db);
            $migration = $manager->summary();
            $checks['migrations'] = ($migration['blocking'] === 0 && $migration['pending'] === 0)
                ? 'ok'
                : 'attention';
        } catch (Throwable $e) {
            $checks['migrations'] = 'failed';
            return self::result('unhealthy', $checks, null, null);
        }

        $reliability = [
            'queue_pending' => 0,
            'queue_failed' => 0,
            'queue_delayed' => 0,
            'critical_open' => 0,
            'error_open' => 0,
        ];

        try {
            if (self::tableExists($db, 'background_jobs')) {
                $row = $db->query(
                    "SELECT
                        SUM(status IN ('PENDING','RETRY')) AS pending,
                        SUM(status = 'FAILED') AS failed,
                        SUM(status IN ('PENDING','RETRY') AND available_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)) AS delayed
                     FROM background_jobs"
                )->fetch(PDO::FETCH_ASSOC) ?: [];

                $reliability['queue_pending'] = (int)($row['pending'] ?? 0);
                $reliability['queue_failed'] = (int)($row['failed'] ?? 0);
                $reliability['queue_delayed'] = (int)($row['delayed'] ?? 0);
                $checks['queue'] = ($reliability['queue_failed'] === 0 && $reliability['queue_delayed'] === 0)
                    ? 'ok'
                    : 'attention';
            } else {
                $checks['queue'] = 'not_installed';
            }

            if (self::tableExists($db, 'observability_events')) {
                $row = $db->query(
                    "SELECT
                        SUM(status = 'OPEN' AND severity = 'CRITICAL') AS critical_open,
                        SUM(status = 'OPEN' AND severity = 'ERROR') AS error_open
                     FROM observability_events"
                )->fetch(PDO::FETCH_ASSOC) ?: [];

                $reliability['critical_open'] = (int)($row['critical_open'] ?? 0);
                $reliability['error_open'] = (int)($row['error_open'] ?? 0);
                $checks['events'] = $reliability['critical_open'] > 0
                    ? 'failed'
                    : ($reliability['error_open'] > 0 ? 'attention' : 'ok');
            } else {
                $checks['events'] = 'not_installed';
            }
        } catch (Throwable $e) {
            $checks['queue'] = $checks['queue'] === 'unknown' ? 'failed' : $checks['queue'];
            $checks['events'] = $checks['events'] === 'unknown' ? 'failed' : $checks['events'];
        }

        $status = 'ok';
        if (
            $checks['database'] === 'failed'
            || $checks['migrations'] === 'failed'
            || $checks['events'] === 'failed'
            || (int)$migration['blocking'] > 0
            || $reliability['critical_open'] > 0
        ) {
            $status = 'unhealthy';
        } elseif (
            $checks['migrations'] !== 'ok'
            || $checks['queue'] === 'attention'
            || $checks['events'] === 'attention'
            || $reliability['queue_failed'] > 0
            || $reliability['queue_delayed'] > 0
        ) {
            $status = 'degraded';
        }

        return self::result(
            $status,
            $checks,
            [
                'applied' => (int)$migration['applied'],
                'pending' => (int)$migration['pending'],
                'blocking' => (int)$migration['blocking'],
            ],
            $reliability
        );
    }

    private static function tableExists(PDO $db, string $table): bool {
        $stmt = $db->prepare(
            "SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function result(
        string $status,
        array $checks,
        ?array $migrationSummary,
        ?array $reliability
    ): array {
        return [
            'status' => $status,
            'checks' => $checks,
            'migration_summary' => $migrationSummary,
            'reliability' => $reliability,
        ];
    }
}
