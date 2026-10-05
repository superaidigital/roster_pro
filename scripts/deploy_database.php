<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', [
    'confirm:',
    'legacy-bridge',
    'retry-failed',
    'help',
]);

if (isset($options['help'])) {
    echo <<<TXT
Roster Pro database deployment safety workflow

Usage:
  php scripts/deploy_database.php --confirm=DEPLOY
  php scripts/deploy_database.php --confirm=DEPLOY --legacy-bridge
  php scripts/deploy_database.php --confirm=DEPLOY --retry-failed

Order:
  1. Production preflight
  2. Pre-deploy compressed backup + SHA-256
  3. Optional idempotent legacy bridge
  4. Tracked SQL migrations
  5. Deployment health check

TXT;
    exit(0);
}

if (($options['confirm'] ?? '') !== 'DEPLOY') {
    fwrite(STDERR, "Deployment refused. Re-run with --confirm=DEPLOY.\n");
    exit(2);
}

$root = dirname(__DIR__);
$php = PHP_BINARY;

$run = static function(string $label, array $command) use ($root): void {
    echo "\n=== {$label} ===\n";
    $descriptors = [
        0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException("Unable to start step: {$label}");
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    while (true) {
        $status = proc_get_status($process);

        foreach ([1 => STDOUT, 2 => STDERR] as $index => $target) {
            $chunk = stream_get_contents($pipes[$index]);
            if ($chunk !== false && $chunk !== '') {
                fwrite($target, $chunk);
            }
        }

        if (!$status['running']) {
            break;
        }
        usleep(50_000);
    }

    foreach ([1 => STDOUT, 2 => STDERR] as $index => $target) {
        $chunk = stream_get_contents($pipes[$index]);
        if ($chunk !== false && $chunk !== '') {
            fwrite($target, $chunk);
        }
        fclose($pipes[$index]);
    }

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException("Step failed: {$label} (exit {$exitCode})");
    }
};

try {
    $run('Preflight', [$php, 'scripts/preflight.php']);
    $run('Pre-deploy backup', [$php, 'scripts/backup_database.php', '--label=predeploy']);

    if (isset($options['legacy-bridge'])) {
        $run('Legacy compatibility bridge', [$php, 'scripts/migrate_legacy.php']);
    }

    $migrationCommand = [$php, 'scripts/migrate.php'];
    if (isset($options['retry-failed'])) {
        $migrationCommand[] = '--retry-failed';
    }
    $run('Tracked database migrations', $migrationCommand);
    $run('Post-deploy health check', [$php, 'scripts/health_check.php']);

    echo "\nDEPLOY_DATABASE_OK\n";
    echo "Database deployment safety workflow completed successfully.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\nDEPLOY_DATABASE_FAILED: {$e->getMessage()}\n");
    fwrite(
        STDERR,
        "Stop deployment. Do not continue automatically. Verify the pre-deploy backup and inspect migration status before retrying or restoring.\n"
    );
    exit(1);
}
