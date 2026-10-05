<?php
declare(strict_types=1);

require_once __DIR__ . '/AppMonitor.php';

final class PerformanceMonitor {
    private static bool $registered = false;
    private static float $startedAt = 0.0;

    public static function register(): void {
        if (self::$registered) return;
        self::$registered = true;
        self::$startedAt = microtime(true);

        register_shutdown_function(static function(): void {
            $elapsedMs = (microtime(true) - self::$startedAt) * 1000;
            $thresholdMs = (int)(getenv('SLOW_REQUEST_THRESHOLD_MS') ?: 1500);
            $thresholdMs = max(250, min(30000, $thresholdMs));

            if ($elapsedMs < $thresholdMs) {
                return;
            }

            $controller = strtolower(trim((string)($_GET['c'] ?? '')));
            $action = strtolower(trim((string)($_GET['a'] ?? 'index')));
            $route = $controller !== '' ? $controller . '::' . ($action !== '' ? $action : 'index') : '';

            AppMonitor::record(
                'WARNING',
                'PERFORMANCE_SLOW_REQUEST',
                sprintf('Slow request detected on %s: %.1f ms', $route !== '' ? $route : 'unknown', $elapsedMs),
                [
                    'route' => $route,
                    'duration_ms' => round($elapsedMs, 1),
                    'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                    'threshold_ms' => $thresholdMs,
                ]
            );
        });
    }
}
