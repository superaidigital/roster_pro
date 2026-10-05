<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['label:', 'help']);
if (isset($options['help'])) {
    echo "Usage: php scripts/backup_database.php [--label=predeploy]\n";
    exit(0);
}

if (!function_exists('gzopen')) {
    fwrite(STDERR, "Backup failed: PHP zlib extension is required.\n");
    exit(1);
}

$root = dirname(__DIR__);
$label = strtolower(trim((string)($options['label'] ?? 'predeploy')));
if (!preg_match('/^[a-z0-9_-]{2,40}$/', $label)) {
    fwrite(STDERR, "Backup failed: invalid label.\n");
    exit(1);
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'roster_pro_db';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPasswordRaw = getenv('DB_PASSWORD');
$dbPassword = $dbPasswordRaw !== false ? $dbPasswordRaw : '';

$backupDirEnv = trim((string)(getenv('BACKUP_DIR') ?: ''));
$backupDir = $backupDirEnv !== ''
    ? $backupDirEnv
    : $root . '/storage/backups';

if (!str_starts_with($backupDir, DIRECTORY_SEPARATOR)
    && !preg_match('/^[A-Za-z]:[\\\\\/]/', $backupDir)) {
    $backupDir = $root . '/' . ltrim($backupDir, '/\\');
}

if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Backup failed: unable to create backup directory.\n");
    exit(1);
}

$realBackupDir = realpath($backupDir);
$realPublicDir = realpath($root . '/public');
if ($realBackupDir === false) {
    fwrite(STDERR, "Backup failed: unable to resolve backup directory.\n");
    exit(1);
}
if ($realPublicDir !== false
    && ($realBackupDir === $realPublicDir
        || str_starts_with($realBackupDir, $realPublicDir . DIRECTORY_SEPARATOR))) {
    fwrite(STDERR, "Backup refused: backup directory must never be inside public/.\n");
    exit(1);
}

if (!is_writable($realBackupDir)) {
    fwrite(STDERR, "Backup failed: backup directory is not writable.\n");
    exit(1);
}

$binaryOverride = trim((string)(getenv('DB_DUMP_BIN') ?: ''));
$candidates = $binaryOverride !== ''
    ? [$binaryOverride]
    : ['mariadb-dump', 'mysqldump'];

$timestamp = date('Ymd_His');
$safeDb = preg_replace('/[^A-Za-z0-9_-]+/', '_', $dbName) ?: 'database';
$filename = "{$safeDb}_{$label}_{$timestamp}.sql.gz";
$finalPath = $realBackupDir . DIRECTORY_SEPARATOR . $filename;
$tempPath = $finalPath . '.partial';

$baseArgs = [
    '--protocol=tcp',
    '--host=' . $host,
    '--port=' . $port,
    '--user=' . $dbUser,
    '--single-transaction',
    '--quick',
    '--skip-lock-tables',
    '--routines',
    '--triggers',
    '--events',
    '--hex-blob',
    '--default-character-set=utf8mb4',
    '--no-tablespaces',
    $dbName,
];

$previousMysqlPwd = getenv('MYSQL_PWD');
putenv('MYSQL_PWD=' . $dbPassword);

$success = false;
$lastError = '';
$usedBinary = '';

foreach ($candidates as $binary) {
    $command = array_merge([$binary], $baseArgs);
    $descriptors = [
        0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open($command, $descriptors, $pipes, $root);
    if (!is_resource($process)) {
        $lastError = "Unable to execute {$binary}";
        continue;
    }

    $gz = gzopen($tempPath, 'wb9');
    if ($gz === false) {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) fclose($pipe);
        }
        proc_terminate($process);
        proc_close($process);
        $lastError = 'Unable to open compressed backup output.';
        break;
    }

    stream_set_blocking($pipes[1], true);
    while (!feof($pipes[1])) {
        $chunk = fread($pipes[1], 1024 * 1024);
        if ($chunk === false) {
            $lastError = 'Unable to read database dump output.';
            break;
        }
        if ($chunk !== '') {
            gzwrite($gz, $chunk);
        }
    }

    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    gzclose($gz);

    $exitCode = proc_close($process);
    if ($exitCode === 0 && is_file($tempPath) && filesize($tempPath) > 0) {
        $success = true;
        $usedBinary = $binary;
        break;
    }

    @unlink($tempPath);
    $lastError = trim((string)$stderr);
    if ($lastError === '') {
        $lastError = "{$binary} exited with code {$exitCode}";
    }
}

if ($previousMysqlPwd === false) {
    putenv('MYSQL_PWD');
} else {
    putenv('MYSQL_PWD=' . $previousMysqlPwd);
}

if (!$success) {
    @unlink($tempPath);
    fwrite(STDERR, "Backup failed: {$lastError}\n");
    fwrite(STDERR, "Set DB_DUMP_BIN to the full path of mariadb-dump/mysqldump if it is not in PATH.\n");
    exit(1);
}

if (!rename($tempPath, $finalPath)) {
    @unlink($tempPath);
    fwrite(STDERR, "Backup failed: unable to finalize backup file.\n");
    exit(1);
}

@chmod($finalPath, 0600);
$checksum = hash_file('sha256', $finalPath);
if (!is_string($checksum) || $checksum === '') {
    @unlink($finalPath);
    fwrite(STDERR, "Backup failed: unable to calculate checksum.\n");
    exit(1);
}

$checksumPath = $finalPath . '.sha256';
file_put_contents($checksumPath, $checksum . '  ' . $filename . PHP_EOL, LOCK_EX);
@chmod($checksumPath, 0600);

$manifest = [
    'format_version' => 2,
    'restore_scope' => 'database_contents',
    'filename' => $filename,
    'sha256' => $checksum,
    'size_bytes' => filesize($finalPath),
    'created_at' => date(DATE_ATOM),
    'database' => $dbName,
    'label' => $label,
    'dump_binary' => basename($usedBinary),
    'contains_database_ddl' => false,
];
$manifestPath = $finalPath . '.json';
file_put_contents(
    $manifestPath,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    LOCK_EX
);
@chmod($manifestPath, 0600);

echo "BACKUP_OK\n";
echo "file={$finalPath}\n";
echo "sha256={$checksum}\n";
echo "size_bytes=" . filesize($finalPath) . "\n";
