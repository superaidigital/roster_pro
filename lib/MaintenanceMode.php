<?php
declare(strict_types=1);

final class MaintenanceMode {
    public static function status(): array {
        $path = self::statePath();
        if (!is_file($path)) {
            return self::disabledState();
        }

        $raw = @file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded) || ($decoded['enabled'] ?? false) !== true) {
            return self::disabledState();
        }

        return [
            'enabled' => true,
            'reason' => mb_substr(trim((string)($decoded['reason'] ?? 'ระบบอยู่ระหว่างปรับปรุง')), 0, 300, 'UTF-8'),
            'started_at' => (string)($decoded['started_at'] ?? ''),
            'retry_after' => self::normalizeRetryAfter((int)($decoded['retry_after'] ?? 120)),
            'release_id' => mb_substr(trim((string)($decoded['release_id'] ?? '')), 0, 120, 'UTF-8'),
            'actor' => mb_substr(trim((string)($decoded['actor'] ?? '')), 0, 120, 'UTF-8'),
        ];
    }

    public static function isEnabled(): bool {
        return (bool)(self::status()['enabled'] ?? false);
    }

    public static function enable(
        string $reason,
        int $retryAfter = 120,
        string $releaseId = '',
        string $actor = ''
    ): array {
        $reason = trim($reason);
        if ($reason === '') {
            $reason = 'ระบบอยู่ระหว่างปรับปรุง';
        }

        $state = [
            'enabled' => true,
            'reason' => mb_substr($reason, 0, 300, 'UTF-8'),
            'started_at' => date(DATE_ATOM),
            'retry_after' => self::normalizeRetryAfter($retryAfter),
            'release_id' => mb_substr(trim($releaseId), 0, 120, 'UTF-8'),
            'actor' => mb_substr(trim($actor), 0, 120, 'UTF-8'),
        ];

        self::writeState($state);
        return $state;
    }

    public static function disable(): void {
        $path = self::statePath();
        if (is_file($path) && !@unlink($path)) {
            throw new RuntimeException('Unable to disable maintenance mode.');
        }
    }

    public static function renderUnavailable(?array $state = null): never {
        $state = $state ?? self::status();
        $retryAfter = self::normalizeRetryAfter((int)($state['retry_after'] ?? 120));

        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
            header('Retry-After: ' . $retryAfter);
            header('X-Robots-Tag: noindex, nofollow, noarchive');
        }

        $reason = htmlspecialchars(
            (string)($state['reason'] ?? 'ระบบอยู่ระหว่างปรับปรุง'),
            ENT_QUOTES,
            'UTF-8'
        );

        echo "<!doctype html><html lang='th'><head><meta charset='utf-8'>"
            . "<meta name='viewport' content='width=device-width,initial-scale=1'>"
            . "<title>ระบบอยู่ระหว่างปรับปรุง</title>"
            . "<style>"
            . "body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;background:#f8fafc;color:#334155}"
            . ".wrap{min-height:100vh;display:grid;place-items:center;padding:24px}"
            . ".card{width:min(620px,100%);box-sizing:border-box;background:#fff;border:1px solid #e2e8f0;border-radius:22px;padding:32px;box-shadow:0 22px 60px rgba(15,23,42,.09)}"
            . ".icon{width:58px;height:58px;border-radius:16px;display:grid;place-items:center;background:#fff7ed;color:#c2410c;font-size:28px;margin-bottom:18px}"
            . "h1{font-size:clamp(1.6rem,5vw,2.4rem);margin:0 0 10px;color:#0f172a}"
            . "p{line-height:1.7;margin:8px 0;color:#64748b}.meta{font-size:13px;color:#94a3b8;margin-top:20px}"
            . "</style></head><body><main class='wrap'><section class='card'>"
            . "<div class='icon'>⚙</div><h1>ระบบอยู่ระหว่างปรับปรุง</h1>"
            . "<p>{$reason}</p>"
            . "<p>กรุณาลองใหม่อีกครั้งในอีกประมาณ {$retryAfter} วินาที</p>"
            . "<div class='meta'>HTTP 503 · Maintenance Mode</div>"
            . "</section></main></body></html>";
        exit;
    }

    public static function statePath(): string {
        return self::runtimeDirectory() . DIRECTORY_SEPARATOR . 'maintenance.json';
    }

    private static function runtimeDirectory(): string {
        $root = realpath(dirname(__DIR__));
        if ($root === false) {
            throw new RuntimeException('Unable to resolve application root.');
        }

        $configured = trim((string)(getenv('MAINTENANCE_STATE_DIR') ?: 'storage/runtime'));
        $directory = $configured;
        if (!str_starts_with($directory, DIRECTORY_SEPARATOR)
            && !preg_match('/^[A-Za-z]:[\\\\\/]/', $directory)) {
            $directory = $root . '/' . ltrim($directory, '/\\');
        }

        $public = realpath($root . '/public');
        $normalized = rtrim(str_replace('\\', '/', $directory), '/');
        if ($public !== false) {
            $publicNormalized = rtrim(str_replace('\\', '/', $public), '/');
            if ($normalized === $publicNormalized || str_starts_with($normalized, $publicNormalized . '/')) {
                throw new RuntimeException('Maintenance state directory must not be inside public/.');
            }
        }

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create maintenance runtime directory.');
        }

        @chmod($directory, 0700);
        return $directory;
    }

    private static function writeState(array $state): void {
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to encode maintenance state.');
        }

        $path = self::statePath();
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write maintenance state.');
        }
        @chmod($tmp, 0600);

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to publish maintenance state atomically.');
        }
        @chmod($path, 0600);
    }

    private static function normalizeRetryAfter(int $seconds): int {
        return max(30, min(3600, $seconds));
    }

    private static function disabledState(): array {
        return [
            'enabled' => false,
            'reason' => '',
            'started_at' => '',
            'retry_after' => 120,
            'release_id' => '',
            'actor' => '',
        ];
    }
}
