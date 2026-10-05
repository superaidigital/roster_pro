<?php
declare(strict_types=1);

require_once __DIR__ . '/MigrationManager.php';

final class DeploymentHealth {
    public static function check(PDO $db): array {
        $checks = [
            'database' => 'unknown',
            'migrations' => 'unknown',
        ];

        try {
            $db->query('SELECT 1')->fetchColumn();
            $checks['database'] = 'ok';
        } catch (Throwable $e) {
            $checks['database'] = 'failed';
            return [
                'status' => 'unhealthy',
                'checks' => $checks,
                'migration_summary' => null,
            ];
        }

        try {
            $manager = new MigrationManager($db);
            $summary = $manager->summary();
            $checks['migrations'] = ($summary['blocking'] === 0 && $summary['pending'] === 0)
                ? 'ok'
                : 'attention';

            return [
                'status' => $checks['migrations'] === 'ok' ? 'ok' : 'degraded',
                'checks' => $checks,
                'migration_summary' => [
                    'applied' => (int)$summary['applied'],
                    'pending' => (int)$summary['pending'],
                    'blocking' => (int)$summary['blocking'],
                ],
            ];
        } catch (Throwable $e) {
            $checks['migrations'] = 'failed';
            return [
                'status' => 'unhealthy',
                'checks' => $checks,
                'migration_summary' => null,
            ];
        }
    }
}
