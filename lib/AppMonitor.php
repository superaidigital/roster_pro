<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/AppEventModel.php';

final class AppMonitor {
    private static bool $registered = false;
    private static ?string $requestId = null;
    private static bool $handling = false;

    public static function register(): void {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        self::$requestId = self::makeRequestId();
        if (!headers_sent()) {
            header('X-Request-ID: ' . self::$requestId);
        }

        set_error_handler(
            static function(int $severity, string $message, string $file, int $line): bool {
                if (!(error_reporting() & $severity)) {
                    return false;
                }

                $map = [
                    E_WARNING => 'WARNING',
                    E_USER_WARNING => 'WARNING',
                    E_RECOVERABLE_ERROR => 'ERROR',
                    E_USER_ERROR => 'ERROR',
                    E_NOTICE => 'INFO',
                    E_USER_NOTICE => 'INFO',
                    E_DEPRECATED => 'INFO',
                    E_USER_DEPRECATED => 'INFO',
                ];

                self::record(
                    $map[$severity] ?? 'WARNING',
                    'PHP_ERROR',
                    $message,
                    [
                        'exception_class' => 'PHPError:' . $severity,
                        'source_file' => $file,
                        'source_line' => $line,
                    ]
                );

                return false;
            }
        );

        set_exception_handler(
            static function(Throwable $e): void {
                self::recordThrowable($e, 'UNCAUGHT_EXCEPTION');

                if (!headers_sent()) {
                    http_response_code(500);
                    header('Content-Type: text/html; charset=utf-8');
                    header('Cache-Control: no-store');
                }

                $requestId = htmlspecialchars((string)self::$requestId, ENT_QUOTES, 'UTF-8');
                echo "<!doctype html><html lang='th'><head><meta charset='utf-8'>"
                    . "<meta name='viewport' content='width=device-width,initial-scale=1'>"
                    . "<title>ระบบขัดข้องชั่วคราว</title></head>"
                    . "<body style='font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#f8fafc;color:#334155;padding:24px'>"
                    . "<main style='max-width:640px;margin:12vh auto;background:#fff;padding:28px;border-radius:18px;border:1px solid #e2e8f0'>"
                    . "<h1 style='color:#b42318'>ระบบขัดข้องชั่วคราว</h1>"
                    . "<p>ระบบบันทึกเหตุการณ์ไว้แล้ว กรุณาลองใหม่อีกครั้งหรือติดต่อผู้ดูแลระบบ</p>"
                    . "<p style='font-size:13px;color:#64748b'>Request ID: {$requestId}</p>"
                    . "</main></body></html>";
            }
        );

        register_shutdown_function(
            static function(): void {
                $last = error_get_last();
                if (!$last) {
                    return;
                }

                $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
                if (!in_array((int)$last['type'], $fatalTypes, true)) {
                    return;
                }

                self::record(
                    'CRITICAL',
                    'PHP_FATAL',
                    (string)$last['message'],
                    [
                        'exception_class' => 'PHPFatal:' . (int)$last['type'],
                        'source_file' => (string)$last['file'],
                        'source_line' => (int)$last['line'],
                    ]
                );
            }
        );
    }

    public static function requestId(): string {
        if (self::$requestId === null) {
            self::$requestId = self::makeRequestId();
        }
        return self::$requestId;
    }

    public static function recordThrowable(Throwable $e, string $category = 'EXCEPTION', array $context = []): void {
        $context['exception_class'] = get_class($e);
        $context['source_file'] = $e->getFile();
        $context['source_line'] = $e->getLine();
        $context['exception_code'] = $e->getCode();

        self::record(
            $e instanceof Error ? 'CRITICAL' : 'ERROR',
            $category,
            $e->getMessage(),
            $context
        );
    }

    public static function record(
        string $severity,
        string $category,
        string $message,
        array $context = []
    ): void {
        if (self::$handling) {
            error_log('[AppMonitor recursion prevented] ' . $message);
            return;
        }

        self::$handling = true;
        try {
            $severity = strtoupper(trim($severity));
            $category = strtoupper(trim($category));
            $message = self::sanitizeMessage($message);

            $sourceFile = (string)($context['source_file'] ?? '');
            $sourceLine = isset($context['source_line']) ? (int)$context['source_line'] : null;
            $exceptionClass = (string)($context['exception_class'] ?? '');

            $route = self::routeName();
            $fingerprint = self::fingerprint(
                $category,
                $exceptionClass,
                $message,
                $sourceFile,
                $sourceLine
            );

            $sessionUser = $_SESSION['user'] ?? [];
            $event = [
                'fingerprint' => $fingerprint,
                'severity' => $severity,
                'category' => $category,
                'message' => $message,
                'exception_class' => $exceptionClass,
                'source_file' => self::relativeSource($sourceFile),
                'source_line' => $sourceLine,
                'route' => $route,
                'request_id' => self::requestId(),
                'user_id' => (int)($sessionUser['id'] ?? 0),
                'hospital_id' => (int)($sessionUser['hospital_id'] ?? 0),
                'context' => self::redact([
                    'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                    'route' => $route,
                    'context' => $context,
                ]),
            ];

            $db = (new Database())->getConnectionOrThrow();
            $model = new AppEventModel($db);
            $model->record($event);
        } catch (Throwable $monitorError) {
            error_log(
                '[AppMonitor fallback] '
                . get_class($monitorError) . ': '
                . $monitorError->getMessage()
                . ' | original=' . $message
            );
        } finally {
            self::$handling = false;
        }
    }

    private static function routeName(): string {
        $controller = strtolower(trim((string)($_GET['c'] ?? '')));
        $action = strtolower(trim((string)($_GET['a'] ?? 'index')));

        if ($controller === '') {
            return '';
        }

        return mb_substr($controller . '::' . ($action !== '' ? $action : 'index'), 0, 190, 'UTF-8');
    }

    private static function fingerprint(
        string $category,
        string $exceptionClass,
        string $message,
        string $sourceFile,
        ?int $sourceLine
    ): string {
        $normalized = preg_replace('/\b[0-9a-f]{8,}\b/i', '{id}', $message) ?? $message;
        $normalized = preg_replace('/\b\d+\b/', '{n}', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? trim($normalized);

        return hash(
            'sha256',
            implode('|', [
                strtoupper($category),
                $exceptionClass,
                basename($sourceFile),
                (string)($sourceLine ?? 0),
                $normalized,
            ])
        );
    }

    private static function sanitizeMessage(string $message): string {
        $message = trim($message);
        $message = preg_replace(
            '/(password|token|secret|authorization|cookie|session|api[_-]?key)\s*[=:]\s*[^\s,;]+/i',
            '$1=[REDACTED]',
            $message
        ) ?? $message;

        return mb_substr($message !== '' ? $message : 'Unknown application event', 0, 1000, 'UTF-8');
    }

    private static function redact(mixed $value, ?string $key = null): mixed {
        if ($key !== null && preg_match(
            '/password|passcode|token|secret|authorization|cookie|session|api[_-]?key|id[_-]?card|bank|account/i',
            $key
        )) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $k => $v) {
                $clean[$k] = self::redact($v, is_string($k) ? $k : null);
            }
            return $clean;
        }

        if (is_object($value)) {
            return self::redact(get_object_vars($value), $key);
        }

        if (is_string($value)) {
            return mb_substr($value, 0, 2000, 'UTF-8');
        }

        return $value;
    }

    private static function relativeSource(string $sourceFile): string {
        if ($sourceFile === '') {
            return '';
        }

        $root = realpath(dirname(__DIR__));
        $real = realpath($sourceFile);
        if ($root !== false && $real !== false && str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            return ltrim(substr($real, strlen($root)), DIRECTORY_SEPARATOR);
        }

        return basename($sourceFile);
    }

    private static function makeRequestId(): string {
        try {
            return bin2hex(random_bytes(12));
        } catch (Throwable $e) {
            return substr(hash('sha256', uniqid('', true)), 0, 24);
        }
    }
}
