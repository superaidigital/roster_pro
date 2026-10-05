<?php
declare(strict_types=1);

final class BackupRetention {
    public static function cleanup(
        string $backupDir,
        int $retentionDays = 30,
        int $keepMinimum = 5
    ): array {
        $root = realpath(dirname(__DIR__));
        if ($root === false) {
            throw new RuntimeException('Unable to resolve application root.');
        }

        if (!str_starts_with($backupDir, DIRECTORY_SEPARATOR)
            && !preg_match('/^[A-Za-z]:[\\\\\/]/', $backupDir)) {
            $backupDir = $root . '/' . ltrim($backupDir, '/\\');
        }

        $realDir = realpath($backupDir);
        if ($realDir === false || !is_dir($realDir)) {
            return ['deleted' => 0, 'kept' => 0, 'files' => []];
        }

        $public = realpath($root . '/public');
        if ($public !== false
            && ($realDir === $public || str_starts_with($realDir, $public . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Backup retention refused inside public/.');
        }

        $retentionDays = max(1, min(3650, $retentionDays));
        $keepMinimum = max(1, min(500, $keepMinimum));
        $threshold = time() - ($retentionDays * 86400);

        $backups = [];
        foreach (scandir($realDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;

            $lower = strtolower($entry);
            if (!str_ends_with($lower, '.sql') && !str_ends_with($lower, '.sql.gz')) {
                continue;
            }

            $path = $realDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($path)) continue;

            $backups[] = [
                'path' => $path,
                'name' => $entry,
                'mtime' => (int)(filemtime($path) ?: 0),
            ];
        }

        usort(
            $backups,
            static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']
        );

        $deleted = [];
        $kept = 0;

        foreach ($backups as $index => $backup) {
            $mustKeep = $index < $keepMinimum || $backup['mtime'] >= $threshold;
            if ($mustKeep) {
                $kept++;
                continue;
            }

            if (!@unlink($backup['path'])) {
                throw new RuntimeException('Unable to delete expired backup: ' . $backup['name']);
            }

            foreach (['.sha256', '.json'] as $suffix) {
                $sidecar = $backup['path'] . $suffix;
                if (is_file($sidecar)) {
                    @unlink($sidecar);
                }
            }

            $deleted[] = $backup['name'];
        }

        return [
            'deleted' => count($deleted),
            'kept' => $kept,
            'files' => $deleted,
        ];
    }
}
