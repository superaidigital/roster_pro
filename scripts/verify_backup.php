<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../lib/BackupVerifier.php';

$options = getopt('', ['file:', 'help']);
if (isset($options['help']) || empty($options['file'])) {
    echo "Usage: php scripts/verify_backup.php --file=storage/backups/file.sql.gz\n";
    exit(isset($options['help']) ? 0 : 2);
}

try {
    $result = BackupVerifier::verify((string)$options['file']);

    echo "BACKUP_VALID\n";
    echo "file={$result['file']}\n";
    echo "sha256={$result['sha256']}\n";
    echo "size_bytes={$result['size_bytes']}\n";

    $manifest = $result['manifest'];
    if ($manifest !== []) {
        echo "format_version=" . (int)($manifest['format_version'] ?? 1) . "\n";
        echo "restore_scope=" . (string)($manifest['restore_scope'] ?? 'legacy') . "\n";
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "BACKUP_INVALID\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(2);
}
