<?php
declare(strict_types=1);

require_once 'config/database.php';
require_once 'lib/DeploymentHealth.php';
require_once 'lib/MaintenanceMode.php';
require_once 'lib/ReleaseIdentity.php';

class HealthController {
    public function index(): void {
        $this->ready();
    }

    public function live(): void {
        $this->sendHeaders();

        $maintenance = MaintenanceMode::safeStatus();

        http_response_code(200);
        echo json_encode([
            'status' => 'alive',
            'release_id' => ReleaseIdentity::current(),
            'maintenance' => (bool)($maintenance['enabled'] ?? false),
            'control_plane_ok' => empty($maintenance['control_plane_error']),
            'timestamp' => date(DATE_ATOM),
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function ready(): void {
        $this->sendHeaders();

        $maintenance = MaintenanceMode::safeStatus();
        if (($maintenance['enabled'] ?? false) === true) {
            http_response_code(503);
            header('Retry-After: ' . (int)$maintenance['retry_after']);
            echo json_encode([
                'status' => 'maintenance',
                'ready' => false,
                'release_id' => ReleaseIdentity::current(),
                'checks' => [
                    'maintenance' => 'active',
                    'database' => 'not_checked',
                    'migrations' => 'not_checked',
                ],
                'timestamp' => date(DATE_ATOM),
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }

        try {
            $db = (new Database())->getConnectionOrThrow();
            $result = DeploymentHealth::check($db);
        } catch (Throwable $e) {
            $result = [
                'status' => 'unhealthy',
                'checks' => [
                    'maintenance' => 'inactive',
                    'database' => 'failed',
                    'migrations' => 'unknown',
                ],
                'migration_summary' => null,
                'reliability' => null,
            ];
        }

        $ready = ($result['status'] ?? '') !== 'unhealthy';
        http_response_code($ready ? 200 : 503);

        $checks = $result['checks'] ?? [];
        $checks['maintenance'] = 'inactive';

        echo json_encode([
            'status' => $result['status'],
            'ready' => $ready,
            'release_id' => ReleaseIdentity::current(),
            'checks' => $checks,
            'migrations' => $result['migration_summary'],
            'reliability' => $result['reliability'] ?? null,
            'timestamp' => date(DATE_ATOM),
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function sendHeaders(): void {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
            header('X-Robots-Tag: noindex, nofollow, noarchive');
            header('X-Release-ID: ' . ReleaseIdentity::current());
        }
    }
}
