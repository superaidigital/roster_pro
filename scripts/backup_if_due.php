<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../lib/BackupVerifier.php';

$options = getopt('', ['max-age-seconds:', 'label:', 'help']);
if (isset($options['help'])) {
    echo "Usage: php scripts/backup_if_due.php [--max-age-seconds=21600] [--label=scheduled]\n";
    exit(0);
}

$interval = isset($options['max-age-seconds'])
    ? (int)$options['max-age-seconds']
    : (int)(getenv('BACKUP_INTERVAL_SECONDS') ?: 21600);
$interval = max(300, min(604800, $interval));

$label = strtolower(trim((string)($options['label'] ?? (getenv('DR_BACKUP_LABEL') ?: 'scheduled'))));
if (!preg_match('/^[a-z0-9_-]{2,40}$/', $label)) {
    fwrite(STDERR, "Invalid backup label.\n");
    exit(2);
}

$latest = null;
try {
    $latest = BackupVerifier::latest($label);
} catch (Throwable $e) {
    // Missing backup directory is handled by backup_database.php when a backup is due.
}

if ($latest !== null) {
    $age = max(0, time() - (int)(filemtime($latest) ?: 0));
    if ($age < $interval) {
        echo "BACKUP_NOT_DUE\n";
        echo "label={$label}\n";
        echo "age_seconds={$age}\n";
        echo "interval_seconds={$interval}\n";
        exit(0);
    }
}

$command = [
    PHP_BINARY,
    __DIR__ . '/backup_database.php',
    '--label=' . $label,
];

$descriptors = [
    0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = @proc_open($command, $descriptors, $pipes, dirname(__DIR__));
if (!is_resource($process)) {
    fwrite(STDERR, "Unable to start backup process.\n");
    exit(2);
}

$stdout = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if ($stdout !== '') echo $stdout;
if ($stderr !== '') fwrite(STDERR, $stderr);

if ($exitCode !== 0) {
    fwrite(STDERR, "Scheduled backup failed.\n");
    exit($exitCode);
}

echo "BACKUP_DUE_COMPLETED\n";
exit(0);
