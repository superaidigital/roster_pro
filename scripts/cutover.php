<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../lib/MaintenanceMode.php';
require_once __DIR__ . '/../lib/ReleaseIdentity.php';
require_once __DIR__ . '/../lib/CommandRunner.php';

$options = getopt('', [
    'confirm:',
    'release:',
    'reason:',
    'retry-after:',
    'legacy-bridge',
    'retry-failed',
    'skip-recovery',
    'help',
]);

if (isset($options['help'])) {
    echo "Usage: php scripts/cutover.php --confirm=CUTOVER --release=<id> [--legacy-bridge] [--retry-failed]\n";
    exit(0);
}

if (($options['confirm'] ?? '') !== 'CUTOVER') {
    fwrite(STDERR, "CUTOVER_REFUSED: re-run with --confirm=CUTOVER.\n");
    exit(2);
}

$releaseId = trim((string)($options['release'] ?? ReleaseIdentity::current()));
if ($releaseId === '' || $releaseId === 'unknown') {
    fwrite(STDERR, "CUTOVER_REFUSED: release id is required.\n");
    exit(2);
}

$reason = trim((string)($options['reason'] ?? ('Production cutover to ' . $releaseId)));
$retryAfter = (int)($options['retry-after'] ?? (getenv('MAINTENANCE_RETRY_AFTER') ?: 120));
$actor = (string)(getenv('DEPLOY_ACTOR') ?: get_current_user());
$maintenanceEnabledByThisRun = false;

try {
    $preGate = [PHP_BINARY, 'scripts/cutover_precheck.php'];
    if (isset($options['skip-recovery'])) $preGate[] = '--skip-recovery';
    CommandRunner::run('Pre-cutover gate', $preGate);

    MaintenanceMode::enable($reason, $retryAfter, $releaseId, $actor);
    $maintenanceEnabledByThisRun = true;
    echo "MAINTENANCE_ENABLED_FOR_CUTOVER\n";

    $deploy = [PHP_BINARY, 'scripts/deploy_database.php', '--confirm=DEPLOY'];
    if (isset($options['legacy-bridge'])) $deploy[] = '--legacy-bridge';
    if (isset($options['retry-failed'])) $deploy[] = '--retry-failed';
    CommandRunner::run('Database deployment', $deploy);

    CommandRunner::run('Post-deploy strict health', [PHP_BINARY, 'scripts/health_check.php']);
    CommandRunner::run('Post-deploy performance contract', [PHP_BINARY, 'scripts/performance_check.php']);

    $maintenanceGate = [PHP_BINARY, 'scripts/go_live_check.php', '--allow-maintenance'];
    if (isset($options['skip-recovery'])) $maintenanceGate[] = '--skip-recovery';
    CommandRunner::run('Pre-resume go-live gate', $maintenanceGate);

    MaintenanceMode::disable();
    $maintenanceEnabledByThisRun = false;
    echo "MAINTENANCE_DISABLED_AFTER_SUCCESS\n";

    $finalGate = [PHP_BINARY, 'scripts/go_live_check.php'];
    if (isset($options['skip-recovery'])) $finalGate[] = '--skip-recovery';
    CommandRunner::run('Final traffic gate', $finalGate);

    echo "CUTOVER_OK\n";
    echo "release_id={$releaseId}\n";
    exit(0);
} catch (Throwable $e) {
    if ($maintenanceEnabledByThisRun || MaintenanceMode::isEnabled()) {
        fwrite(STDERR, "CUTOVER_FAILED_MAINTENANCE_REMAINS_ON\n");
    }
    fwrite(STDERR, "CUTOVER_FAILED: {$e->getMessage()}\n");
    fwrite(STDERR, "Do not resume traffic until the issue is resolved and scripts/resume_traffic.php passes.\n");
    exit(1);
}
