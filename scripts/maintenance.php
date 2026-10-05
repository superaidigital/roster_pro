<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../lib/MaintenanceMode.php';
require_once __DIR__ . '/../lib/ReleaseIdentity.php';

$action = strtolower((string)($argv[1] ?? 'status'));
$options = getopt('', [
    'confirm:',
    'reason:',
    'retry-after:',
    'release:',
    'actor:',
    'help',
]);

if (isset($options['help']) || !in_array($action, ['status', 'enable', 'disable'], true)) {
    echo "Usage:\n";
    echo "  php scripts/maintenance.php status\n";
    echo "  php scripts/maintenance.php enable --confirm=MAINTENANCE [--reason='deploy'] [--retry-after=120] [--release=id]\n";
    echo "  php scripts/maintenance.php disable --confirm=RESUME\n";
    exit(isset($options['help']) ? 0 : 2);
}

try {
    if ($action === 'status') {
        echo json_encode(MaintenanceMode::status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    if ($action === 'enable') {
        if (($options['confirm'] ?? '') !== 'MAINTENANCE') {
            throw new RuntimeException('Enable refused. Re-run with --confirm=MAINTENANCE.');
        }

        $reason = (string)($options['reason'] ?? 'Production cutover in progress');
        $retryAfter = (int)($options['retry-after'] ?? (getenv('MAINTENANCE_RETRY_AFTER') ?: 120));
        $releaseId = (string)($options['release'] ?? ReleaseIdentity::current());
        $actor = (string)($options['actor'] ?? (getenv('DEPLOY_ACTOR') ?: get_current_user()));

        $state = MaintenanceMode::enable($reason, $retryAfter, $releaseId, $actor);
        echo json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        echo "MAINTENANCE_ENABLED\n";
        exit(0);
    }

    if (($options['confirm'] ?? '') !== 'RESUME') {
        throw new RuntimeException('Disable refused. Re-run with --confirm=RESUME.');
    }

    MaintenanceMode::disable();
    echo "MAINTENANCE_DISABLED\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "MAINTENANCE_COMMAND_FAILED: {$e->getMessage()}\n");
    exit(2);
}
