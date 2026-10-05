<?php
declare(strict_types=1);

final class SimpleCache {
    private string $directory;

    public function __construct(?string $directory = null) {
        $root = realpath(dirname(__DIR__));
        if ($root === false) {
            throw new RuntimeException('Unable to resolve application root.');
        }

        $directory = $directory ?: ($root . '/storage/cache');
        if (!str_starts_with($directory, DIRECTORY_SEPARATOR)
            && !preg_match('/^[A-Za-z]:[\\\\\/]/', $directory)) {
            $directory = $root . '/' . ltrim($directory, '/\\');
        }

        $public = realpath($root . '/public');
        $normalized = rtrim(str_replace('\\', '/', $directory), '/');
        if ($public !== false) {
            $publicNormalized = rtrim(str_replace('\\', '/', $public), '/');
            if ($normalized === $publicNormalized || str_starts_with($normalized, $publicNormalized . '/')) {
                throw new RuntimeException('Cache directory must not be inside public/.');
            }
        }

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create cache directory.');
        }

        @chmod($directory, 0700);
        $this->directory = $directory;
    }

    /**
     * @return array{value:mixed,hit:bool,created_at:int}
     */
    public function remember(string $key, int $ttlSeconds, callable $producer): array {
        $ttlSeconds = max(1, min(3600, $ttlSeconds));

        $cached = $this->readFresh($key);
        if ($cached !== null) {
            return [
                'value' => $cached['payload'],
                'hit' => true,
                'created_at' => (int)$cached['created_at'],
            ];
        }

        $lockPath = $this->lockPath($key);
        $lock = fopen($lockPath, 'c+');
        if ($lock !== false) {
            @chmod($lockPath, 0600);
        }
        if ($lock === false) {
            $value = $producer();
            return ['value' => $value, 'hit' => false, 'created_at' => time()];
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                $value = $producer();
                return ['value' => $value, 'hit' => false, 'created_at' => time()];
            }

            $cached = $this->readFresh($key);
            if ($cached !== null) {
                return [
                    'value' => $cached['payload'],
                    'hit' => true,
                    'created_at' => (int)$cached['created_at'],
                ];
            }

            $value = $producer();
            $createdAt = time();
            $this->write(
                $key,
                [
                    'version' => 1,
                    'created_at' => $createdAt,
                    'expires_at' => $createdAt + $ttlSeconds,
                    'payload' => $value,
                ]
            );

            return [
                'value' => $value,
                'hit' => false,
                'created_at' => $createdAt,
            ];
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function forget(string $key): void {
        $path = $this->cachePath($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function clearExpired(): int {
        $deleted = 0;
        foreach (glob($this->directory . '/*.json') ?: [] as $path) {
            if (!is_file($path)) continue;
            $raw = @file_get_contents($path);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($decoded) || (int)($decoded['expires_at'] ?? 0) < time()) {
                if (@unlink($path)) $deleted++;
            }
        }

        $staleLockThreshold = time() - (7 * 86400);
        foreach (glob($this->directory . '/*.lock') ?: [] as $lockPath) {
            if (!is_file($lockPath)) continue;
            $mtime = (int)(filemtime($lockPath) ?: 0);
            if ($mtime > 0 && $mtime < $staleLockThreshold && @unlink($lockPath)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function readFresh(string $key): ?array {
        $path = $this->cachePath($key);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)
            || (int)($decoded['version'] ?? 0) !== 1
            || !array_key_exists('payload', $decoded)
            || (int)($decoded['expires_at'] ?? 0) <= time()) {
            @unlink($path);
            return null;
        }

        return $decoded;
    }

    private function write(string $key, array $record): void {
        $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to encode cache payload.');
        }

        $path = $this->cachePath($key);
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(6));

        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write cache file.');
        }

        @chmod($tmp, 0600);

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to publish cache file atomically.');
        }

        @chmod($path, 0600);
    }

    private function cachePath(string $key): string {
        return $this->directory . '/' . hash('sha256', 'cache:' . $key) . '.json';
    }

    private function lockPath(string $key): string {
        return $this->directory . '/' . hash('sha256', 'lock:' . $key) . '.lock';
    }
}
