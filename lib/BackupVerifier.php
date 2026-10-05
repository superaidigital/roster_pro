<?php
declare(strict_types=1);

final class BackupVerifier {
    public static function backupRoot(): string {
        $root = realpath(dirname(__DIR__));
        if ($root === false) {
            throw new RuntimeException('Unable to resolve application root.');
        }

        $configured = trim((string)(getenv('BACKUP_DIR') ?: 'storage/backups'));
        $path = $configured;
        if (!str_starts_with($path, DIRECTORY_SEPARATOR)
            && !preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $path = $root . '/' . ltrim($path, '/\\');
        }

        $real = realpath($path);
        if ($real === false || !is_dir($real)) {
            throw new RuntimeException('Backup directory does not exist.');
        }

        $public = realpath($root . '/public');
        if ($public !== false
            && ($real === $public || str_starts_with($real, $public . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Backup directory must not be inside public/.');
        }

        return $real;
    }

    public static function resolve(string $file): string {
        $root = realpath(dirname(__DIR__));
        if ($root === false) {
            throw new RuntimeException('Unable to resolve application root.');
        }

        if (!str_starts_with($file, DIRECTORY_SEPARATOR)
            && !preg_match('/^[A-Za-z]:[\\\\\/]/', $file)) {
            $file = $root . '/' . ltrim($file, '/\\');
        }

        $realFile = realpath($file);
        if ($realFile === false || !is_file($realFile)) {
            throw new RuntimeException('Backup file not found.');
        }

        $backupRoot = self::backupRoot();
        if (!($realFile === $backupRoot
            || str_starts_with($realFile, $backupRoot . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Backup file is outside the configured backup directory.');
        }

        return $realFile;
    }

    public static function verify(string $file, bool $requireManifest = false): array {
        $realFile = self::resolve($file);

        $checksumPath = $realFile . '.sha256';
        if (!is_file($checksumPath)) {
            throw new RuntimeException('Backup checksum sidecar is missing.');
        }

        $line = trim((string)file_get_contents($checksumPath));
        if (!preg_match('/^([A-Fa-f0-9]{64})\\s+/', $line, $match)) {
            throw new RuntimeException('Backup checksum sidecar is invalid.');
        }

        $expected = strtolower($match[1]);
        $actual = strtolower((string)hash_file('sha256', $realFile));
        if ($actual === '' || !hash_equals($expected, $actual)) {
            throw new RuntimeException('Backup checksum does not match.');
        }

        $manifest = [];
        $manifestPath = $realFile . '.json';
        if (is_file($manifestPath)) {
            $decoded = json_decode((string)file_get_contents($manifestPath), true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Backup manifest is invalid JSON.');
            }
            $manifest = $decoded;

            $manifestSha = strtolower((string)($manifest['sha256'] ?? ''));
            if ($manifestSha !== '' && !hash_equals($actual, $manifestSha)) {
                throw new RuntimeException('Backup manifest checksum does not match the backup.');
            }

            $manifestName = (string)($manifest['filename'] ?? '');
            if ($manifestName !== '' && $manifestName !== basename($realFile)) {
                throw new RuntimeException('Backup manifest filename does not match the backup.');
            }
        } elseif ($requireManifest) {
            throw new RuntimeException('Backup manifest is required.');
        }

        return [
            'file' => $realFile,
            'sha256' => $actual,
            'size_bytes' => (int)(filesize($realFile) ?: 0),
            'manifest' => $manifest,
        ];
    }

    public static function latest(?string $label = null): ?string {
        $root = self::backupRoot();
        $files = [];

        foreach (glob($root . '/*.sql.gz') ?: [] as $path) {
            if (!is_file($path)) continue;

            if ($label !== null && $label !== '') {
                $manifestPath = $path . '.json';
                if (!is_file($manifestPath)) continue;
                $manifest = json_decode((string)file_get_contents($manifestPath), true);
                if (!is_array($manifest) || (string)($manifest['label'] ?? '') !== $label) {
                    continue;
                }
            }

            $files[] = [
                'path' => $path,
                'mtime' => (int)(filemtime($path) ?: 0),
            ];
        }

        usort($files, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
        return $files[0]['path'] ?? null;
    }

    public static function assertRestoreSafeDump(string $file): void {
        $realFile = self::resolve($file);
        $gz = gzopen($realFile, 'rb');
        if ($gz === false) {
            throw new RuntimeException('Unable to open compressed backup.');
        }

        $lineNumber = 0;
        try {
            while (!gzeof($gz)) {
                $line = gzgets($gz);
                if ($line === false) break;
                $lineNumber++;

                if (preg_match('/^\\s*(CREATE|DROP|ALTER)\\s+DATABASE\\b/i', $line)
                    || preg_match('/^\\s*USE\\s+/i', $line)) {
                    throw new RuntimeException(
                        'Restore drill refused a dump containing database-selection DDL at line '
                        . $lineNumber
                    );
                }
            }
        } finally {
            gzclose($gz);
        }
    }
}
