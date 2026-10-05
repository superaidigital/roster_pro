<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/DeploymentHealth.php';
require_once __DIR__ . '/../lib/MaintenanceMode.php';
require_once __DIR__ . '/../lib/ReleaseIdentity.php';
require_once __DIR__ . '/../lib/CommandRunner.php';

$options = getopt('', ['skip-recovery', 'help']);
if (isset($options['help'])) {
    echo "Usage: php scripts/cutover_precheck.php [--skip-recovery]\n";
    exit(0);
}

$production = strtolower((string)(getenv('APP_ENV') ?: 'development')) === 'production';
$skipRecovery = isset($options['skip-recovery']);

if ($production && $skipRecovery) {
    fwrite(STDERR, "CUTOVER_PRECHECK_FAILED: recovery checks cannot be skipped in production.\n");
    exit(2);
}

$errors = [];
$checks = [];

try {
    $maintenance = MaintenanceMode::safeStatus();
    if (!empty($maintenance['enabled'])) {
        $errors[] = 'maintenance mode is already active';
        $checks['maintenance'] = 'FAIL';
    } else {
        $checks['maintenance'] = 'PASS';
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
    $migration = $health['migration_summary'] ?? ['pending' => 0, 'blocking' => 0];
    $reliability = $health['reliability'] ?? [];

    if ((int)($migration['blocking'] ?? 0) > 0) {
        $errors[] = 'migration state is blocked';
    }
    if ((int)($reliability['queue_failed'] ?? 0) > 0
        || (int)($reliability['queue_delayed'] ?? 0) > 0
        || (int)($reliability['critical_open'] ?? 0) > 0
        || (int)($reliability['error_open'] ?? 0) > 0) {
        $errors[] = 'reliability backlog/errors must be cleared before cutover';
    }
    if (($health['checks']['database'] ?? '') !== 'ok') {
        $errors[] = 'database health is not OK';
    }

    $checks['deployment_health'] = (string)($health['status'] ?? 'unknown');
    $checks['pending_migrations'] = (int)($migration['pending'] ?? 0);

    $preflight = [PHP_BINARY, 'scripts/preflight.php', '--allow-pending'];
    if ($production) $preflight[] = '--strict';
    CommandRunner::run('Cutover preflight', $preflight);
    $checks['preflight'] = 'PASS';

    if (!$skipRecovery) {
        CommandRunner::run('Recovery readiness', [PHP_BINARY, 'scripts/recovery_check.php']);
        $checks['recovery'] = 'PASS';
    } else {
        $checks['recovery'] = 'SKIPPED_NON_PRODUCTION';
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

echo json_encode([
    'status' => $errors === [] ? 'PASS' : 'FAIL',
    'release_id' => ReleaseIdentity::current(),
    'checks' => $checks,
    'errors' => $errors,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

echo $errors === [] ? "CUTOVER_PRECHECK_OK\n" : "CUTOVER_PRECHECK_FAILED\n";
exit($errors === [] ? 0 : 2);
