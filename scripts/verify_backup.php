<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['file:', 'help']);
if (isset($options['help']) || empty($options['file'])) {
    echo "Usage: php scripts/verify_backup.php --file=storage/backups/file.sql.gz\n";
    exit(isset($options['help']) ? 0 : 2);
}

$root = dirname(__DIR__);
$file = (string)$options['file'];
if (!str_starts_with($file, DIRECTORY_SEPARATOR)
    && !preg_match('/^[A-Za-z]:[\\\\\/]/', $file)) {
    $file = $root . '/' . ltrim($file, '/\\');
}

$realFile = realpath($file);
if ($realFile === false || !is_file($realFile)) {
    fwrite(STDERR, "Backup file not found.\n");
    exit(1);
}

$realBackupRoot = realpath($root . '/storage/backups');
if ($realBackupRoot !== false
    && !($realFile === $realBackupRoot || str_starts_with($realFile, $realBackupRoot . DIRECTORY_SEPARATOR))) {
    fwrite(STDERR, "Backup verification refused outside storage/backups.\n");
    exit(1);
}

$checksumPath = $realFile . '.sha256';
if (!is_file($checksumPath)) {
    fwrite(STDERR, "Checksum file not found: {$checksumPath}\n");
    exit(1);
}

$line = trim((string)file_get_contents($checksumPath));
if (!preg_match('/^([A-Fa-f0-9]{64})\s+/', $line, $match)) {
    fwrite(STDERR, "Invalid checksum file.\n");
    exit(1);
}

$expected = strtolower($match[1]);
$actual = strtolower((string)hash_file('sha256', $realFile));

if (!hash_equals($expected, $actual)) {
    fwrite(STDERR, "BACKUP_INVALID\n");
    fwrite(STDERR, "expected={$expected}\nactual={$actual}\n");
    exit(2);
}

echo "BACKUP_VALID\n";
echo "file={$realFile}\n";
echo "sha256={$actual}\n";
echo "size_bytes=" . filesize($realFile) . "\n";
