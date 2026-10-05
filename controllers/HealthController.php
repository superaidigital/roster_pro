<?php
declare(strict_types=1);

require_once 'config/database.php';
require_once 'lib/DeploymentHealth.php';

class HealthController {
    public function index(): void {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
            header('X-Robots-Tag: noindex, nofollow, noarchive');
        }

        try {
            $db = (new Database())->getConnectionOrThrow();
            $result = DeploymentHealth::check($db);
        } catch (Throwable $e) {
            $result = [
                'status' => 'unhealthy',
                'checks' => [
                    'database' => 'failed',
                    'migrations' => 'unknown',
                ],
                'migration_summary' => null,
            ];
        }

        $healthy = ($result['status'] ?? '') === 'ok';
        http_response_code($healthy ? 200 : 503);

        echo json_encode([
            'status' => $result['status'],
            'checks' => $result['checks'],
            'migrations' => $result['migration_summary'],
            'reliability' => $result['reliability'] ?? null,
            'timestamp' => date(DATE_ATOM),
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
