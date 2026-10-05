<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/SimpleCache.php';

$errors = [];
$passes = [];

function perfPass(array &$passes, string $message): void {
    $passes[] = $message;
    echo "PASS: {$message}\n";
}

function perfError(array &$errors, string $message): void {
    $errors[] = $message;
    fwrite(STDERR, "FAIL: {$message}\n");
}

try {
    $db = (new Database())->getConnectionOrThrow();

    $requiredIndexes = [
        'shifts' => [
            'idx_shifts_hospital_date',
            'idx_shifts_date_hospital_user',
        ],
        'logs' => [
            'idx_logs_created_id',
            'idx_logs_created_action_user',
        ],
        'roster_status' => [
            'idx_roster_status_month_status_hospital',
        ],
        'shift_swaps' => [
            'idx_shift_swaps_hospital_status_created',
            'idx_shift_swaps_status_hospital_created',
        ],
        'users' => [
            'idx_users_hospital_roster',
            'idx_users_active_scope',
        ],
    ];

    $indexStmt = $db->prepare(
        "SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND INDEX_NAME = ?"
    );

    foreach ($requiredIndexes as $table => $indexes) {
        foreach ($indexes as $index) {
            $indexStmt->execute([$table, $index]);
            if ((int)$indexStmt->fetchColumn() > 0) {
                perfPass($passes, "{$table}.{$index} exists");
            } else {
                perfError($errors, "{$table}.{$index} is missing");
            }
        }
    }

    $planChecks = [
        [
            'name' => 'shift date-range query exposes date index',
            'sql' => "EXPLAIN SELECT user_id
                      FROM shifts
                      WHERE shift_date >= '2026-10-01'
                        AND shift_date < '2026-11-01'",
            'index' => 'idx_shifts_date_hospital_user',
        ],
        [
            'name' => 'daily logs query exposes created_at indexes',
            'sql' => "EXPLAIN SELECT id, action
                      FROM logs
                      WHERE created_at >= '2026-10-05 00:00:00'
                        AND created_at < '2026-10-06 00:00:00'
                      ORDER BY created_at DESC, id DESC
                      LIMIT 50",
            'index' => 'idx_logs_created_id',
        ],
        [
            'name' => 'monthly roster query exposes month index',
            'sql' => "EXPLAIN SELECT hospital_id, status
                      FROM roster_status
                      WHERE month_year = '2026-10'",
            'index' => 'idx_roster_status_month_status_hospital',
        ],
    ];

    foreach ($planChecks as $check) {
        $rows = $db->query($check['sql'])->fetchAll(PDO::FETCH_ASSOC);
        $possible = [];
        foreach ($rows as $row) {
            foreach (explode(',', (string)($row['possible_keys'] ?? '')) as $key) {
                $key = trim($key);
                if ($key !== '') $possible[$key] = true;
            }
        }

        if (isset($possible[$check['index']])) {
            perfPass($passes, $check['name']);
        } else {
            perfError(
                $errors,
                $check['name'] . ' (expected possible key ' . $check['index']
                . '; got ' . implode(',', array_keys($possible)) . ')'
            );
        }
    }

    $dashboardSource = (string)file_get_contents(__DIR__ . '/../models/DashboardMetricsModel.php');
    foreach ([
        "shift_date >= ?" => 'dashboard uses range predicate for shifts',
        "created_at >= ?" => 'dashboard uses range predicate for logs',
        "COALESCE(SUM(" => 'dashboard budget is aggregated in SQL',
    ] as $token => $message) {
        if (strpos($dashboardSource, $token) !== false) {
            perfPass($passes, $message);
        } else {
            perfError($errors, $message);
        }
    }

    if (preg_match('/DATE\s*\(\s*l\.created_at\s*\)/i', $dashboardSource)) {
        perfError($errors, 'dashboard must not wrap logs.created_at in DATE()');
    } else {
        perfPass($passes, 'dashboard avoids DATE(logs.created_at) index blocker');
    }

    $cacheDir = sys_get_temp_dir() . '/roster_perf_cache_' . bin2hex(random_bytes(5));
    $cache = new SimpleCache($cacheDir);
    $producerCalls = 0;

    $first = $cache->remember('contract:key', 30, static function() use (&$producerCalls): array {
        $producerCalls++;
        return ['value' => 42];
    });
    $second = $cache->remember('contract:key', 30, static function() use (&$producerCalls): array {
        $producerCalls++;
        return ['value' => 99];
    });

    if (($first['hit'] ?? true) === false
        && ($second['hit'] ?? false) === true
        && ($second['value']['value'] ?? null) === 42
        && $producerCalls === 1) {
        perfPass($passes, 'aggregate cache returns deterministic hit without recompute');
    } else {
        perfError($errors, 'aggregate cache hit behavior is incorrect');
    }

    foreach (glob($cacheDir . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($cacheDir);

    echo "Performance contract: passes=" . count($passes) . " failures=" . count($errors) . "\n";
    exit($errors ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "Performance contract failed: {$e->getMessage()}\n");
    exit(1);
}
