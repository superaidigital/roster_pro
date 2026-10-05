<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/DeploymentHealth.php';
require_once __DIR__ . '/../lib/MaintenanceMode.php';
require_once __DIR__ . '/../lib/ReleaseIdentity.php';
require_once __DIR__ . '/../lib/CommandRunner.php';

$options = getopt('', ['allow-maintenance', 'skip-recovery', 'help']);
if (isset($options['help'])) {
    echo "Usage: php scripts/go_live_check.php [--allow-maintenance] [--skip-recovery]\n";
    exit(0);
}

$production = strtolower((string)(getenv('APP_ENV') ?: 'development')) === 'production';
$allowMaintenance = isset($options['allow-maintenance']);
$skipRecovery = isset($options['skip-recovery']);

if ($production && $skipRecovery) {
    fwrite(STDERR, "GO_LIVE_FAILED: recovery checks cannot be skipped in production.\n");
    exit(2);
}

$errors = [];
$checks = [];

try {
    $maintenance = MaintenanceMode::status();
    if (($maintenance['enabled'] ?? false) && !$allowMaintenance) {
        $errors[] = 'maintenance mode is active';
        $checks['maintenance'] = 'FAIL';
    } else {
        $checks['maintenance'] = ($maintenance['enabled'] ?? false) ? 'ALLOWED' : 'PASS';
    }

    $releaseId = ReleaseIdentity::current();
    if ($production && $releaseId === 'unknown') {
        $errors[] = 'APP_RELEASE_ID is required in production';
        $checks['release_id'] = 'FAIL';
    } else {
        $checks['release_id'] = $releaseId;
    }

    $db = (new Database())->getConnectionOrThrow();
    $health = DeploymentHealth::check($db);
    if (($health['status'] ?? '') !== 'ok') {
        $errors[] = 'deployment health must be OK, got ' . (string)($health['status'] ?? 'unknown');
        $checks['deployment_health'] = strtoupper((string)($health['status'] ?? 'unknown'));
    } else {
        $checks['deployment_health'] = 'PASS';
    }

    CommandRunner::run(
        'Production preflight',
        [PHP_BINARY, 'scripts/preflight.php', ...($production ? ['--strict'] : [])]
    );
    $checks['preflight'] = 'PASS';

    CommandRunner::run('Performance contract', [PHP_BINARY, 'scripts/performance_check.php']);
    $checks['performance'] = 'PASS';

    CommandRunner::run(
        'Security compliance',
        [PHP_BINARY, '-d', 'display_errors=0', '-d', 'expose_php=0', 'scripts/security_check.php', '--strict']
    );
    $checks['security'] = 'PASS';

    if (!$skipRecovery) {
        CommandRunner::run('Recovery readiness', [PHP_BINARY, 'scripts/recovery_check.php']);
        $checks['recovery'] = 'PASS';
    } else {
        $checks['recovery'] = 'SKIPPED_NON_PRODUCTION';
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

$result = [
    'status' => $errors === [] ? 'PASS' : 'FAIL',
    'release_id' => ReleaseIdentity::current(),
    'checks' => $checks,
    'errors' => $errors,
    'timestamp' => date(DATE_ATOM),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo $errors === [] ? "GO_LIVE_CHECK_OK\n" : "GO_LIVE_CHECK_FAILED\n";
exit($errors === [] ? 0 : 2);
