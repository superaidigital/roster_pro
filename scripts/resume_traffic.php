<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../lib/MaintenanceMode.php';
require_once __DIR__ . '/../lib/CommandRunner.php';

$options = getopt('', ['confirm:', 'skip-recovery', 'help']);
if (isset($options['help'])) {
    echo "Usage: php scripts/resume_traffic.php --confirm=RESUME [--skip-recovery]\n";
    exit(0);
}

if (($options['confirm'] ?? '') !== 'RESUME') {
    fwrite(STDERR, "RESUME_REFUSED: re-run with --confirm=RESUME.\n");
    exit(2);
}

try {
    $gate = [PHP_BINARY, 'scripts/go_live_check.php', '--allow-maintenance'];
    if (isset($options['skip-recovery'])) $gate[] = '--skip-recovery';
    CommandRunner::run('Safe resume gate', $gate);

    MaintenanceMode::disable();

    $final = [PHP_BINARY, 'scripts/go_live_check.php'];
    if (isset($options['skip-recovery'])) $final[] = '--skip-recovery';
    CommandRunner::run('Post-resume gate', $final);

    echo "TRAFFIC_RESUMED_OK\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "TRAFFIC_RESUME_BLOCKED: {$e->getMessage()}\n");
    exit(2);
}
