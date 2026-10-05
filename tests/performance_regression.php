<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/DashboardMetricsModel.php';
require_once __DIR__ . '/../lib/SimpleCache.php';

function perfOk(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

function elapsedMs(float $started): float {
    return round((microtime(true) - $started) * 1000, 1);
}

$db = (new Database())->getConnectionOrThrow();
$db->beginTransaction();

try {
    $hospitalIds = [];
    $insertHospital = $db->prepare(
        "INSERT INTO hospitals (hospital_code, name, short_name, is_active)
         VALUES (?, ?, ?, 1)"
    );
    for ($i = 1; $i <= 4; $i++) {
        $insertHospital->execute([
            'P' . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
            'Performance Hospital ' . $i,
            'PERF' . $i,
        ]);
        $hospitalIds[] = (int)$db->lastInsertId();
    }

    $db->exec(
        "INSERT INTO pay_rates
            (name, group_level, group_name, keywords, rate_y, rate_b, rate_r, display_order)
         VALUES ('Performance Rate', 1, 'Performance', 'perf', 500, 600, 700, 1)"
    );
    $payRateId = (int)$db->lastInsertId();

    $db->exec(
        "INSERT INTO leave_quotas (leave_type, max_days, calculation_type, description)
         VALUES ('Performance Leave', 20, 'WORKING_DAYS', 'Synthetic performance fixture')"
    );
    $leaveTypeId = (int)$db->lastInsertId();

    $insertUser = $db->prepare(
        "INSERT INTO users
            (hospital_id, name, username, password, role, is_active, position, type, pay_rate_id, is_deleted, show_in_roster)
         VALUES (?, ?, ?, ?, 'STAFF', 1, 'Performance Staff', 'NURSE', ?, 0, 1)"
    );

    $userIds = [];
    $performancePasswordHash = password_hash('PerfPass!2026', PASSWORD_DEFAULT);
    for ($i = 1; $i <= 80; $i++) {
        $hospitalId = $hospitalIds[($i - 1) % count($hospitalIds)];
        $insertUser->execute([
            $hospitalId,
            'Performance User ' . $i,
            'perf_user_' . $i,
            $performancePasswordHash,
            $payRateId,
        ]);
        $userIds[] = (int)$db->lastInsertId();
    }

    $insertStatus = $db->prepare(
        "INSERT INTO roster_status (hospital_id, month_year, status)
         VALUES (?, '2026-10', ?)"
    );
    $statuses = ['APPROVED', 'SUBMITTED', 'DRAFT', 'REQUEST_EDIT'];
    foreach ($hospitalIds as $index => $hospitalId) {
        $insertStatus->execute([$hospitalId, $statuses[$index]]);
    }

    $insertShift = $db->prepare(
        "INSERT INTO shifts (hospital_id, user_id, shift_date, shift_type)
         VALUES (?, ?, ?, ?)"
    );
    $shiftTypes = ['บ', 'ร', 'บ/ร'];
    foreach ($userIds as $userIndex => $userId) {
        $hospitalId = $hospitalIds[$userIndex % count($hospitalIds)];
        for ($day = 1; $day <= 31; $day++) {
            $insertShift->execute([
                $hospitalId,
                $userId,
                sprintf('2026-10-%02d', $day),
                $shiftTypes[($userIndex + $day) % count($shiftTypes)],
            ]);
        }
    }

    $insertLog = $db->prepare(
        "INSERT INTO logs (user_id, action, details, ip_address, created_at)
         VALUES (?, ?, ?, ?, ?)"
    );
    $actions = ['LOGIN', 'CREATE', 'UPDATE', 'EXPORT'];
    for ($i = 1; $i <= 12000; $i++) {
        $userId = $userIds[$i % count($userIds)];
        $day = 1 + ($i % 5);
        $hour = $i % 24;
        $minute = $i % 60;
        $insertLog->execute([
            $userId,
            $actions[$i % count($actions)],
            'Synthetic performance log ' . $i,
            '10.0.' . (($i % 200) + 1) . '.' . (($i % 240) + 1),
            sprintf('2026-10-%02d %02d:%02d:00', $day, $hour, $minute),
        ]);
    }

    $insertLeave = $db->prepare(
        "INSERT INTO leave_requests
            (user_id, leave_type_id, start_date, end_date, num_days, reason, status, created_at)
         VALUES (?, ?, ?, ?, 1.0, 'Synthetic performance leave', ?, ?)"
    );
    for ($i = 1; $i <= 600; $i++) {
        $day = 1 + ($i % 28);
        $date = sprintf('2026-10-%02d', $day);
        $insertLeave->execute([
            $userIds[$i % count($userIds)],
            $leaveTypeId,
            $date,
            $date,
            ($i % 3 === 0) ? 'PENDING' : 'APPROVED',
            $date . ' 08:00:00',
        ]);
    }

    $insertSwap = $db->prepare(
        "INSERT INTO shift_swaps
            (hospital_id, requestor_id, requestor_date, requestor_shift,
             target_user_id, target_date, target_shift, reason, status)
         VALUES (?, ?, '2026-10-10', 'บ', ?, '2026-10-11', 'ร', 'Synthetic', ?)"
    );
    for ($i = 0; $i < 200; $i++) {
        $requestorIndex = $i % count($userIds);
        $targetIndex = ($i + 1) % count($userIds);
        $hospitalId = $hospitalIds[$requestorIndex % count($hospitalIds)];
        $insertSwap->execute([
            $hospitalId,
            $userIds[$requestorIndex],
            $userIds[$targetIndex],
            ($i % 2 === 0) ? 'PENDING_TARGET' : 'PENDING_DIRECTOR',
        ]);
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $e;
}

foreach (['shifts', 'logs', 'leave_requests', 'roster_status', 'shift_swaps', 'users'] as $table) {
    $db->query('ANALYZE TABLE ' . $table)->fetchAll(PDO::FETCH_ASSOC);
}

$model = new DashboardMetricsModel($db);
$maxDashboardMs = max(500, (int)(getenv('PERF_MAX_DASHBOARD_MS') ?: 5000));
$maxLiveMs = max(500, (int)(getenv('PERF_MAX_LIVE_MS') ?: 3000));
$maxCacheHitMs = max(100, (int)(getenv('PERF_MAX_CACHE_HIT_MS') ?: 1000));

$started = microtime(true);
$global = $model->getExecutiveAggregates(null, '2026-10', '2026-10-05');
$globalMs = elapsedMs($started);

perfOk((int)$global['total_hospitals'] === 4, 'global dashboard counts hospitals correctly');
perfOk((int)$global['total_staff'] === 80, 'global dashboard counts staff correctly');
perfOk((int)$global['on_duty_today'] === 80, 'global dashboard counts current duty correctly');
perfOk((float)$global['estimated_budget'] > 0, 'dashboard budget is aggregated in SQL');
perfOk(count($global['workload_data']) === 4, 'workload aggregation returns hospital groups');
perfOk(count($global['today_usages']) === 4, 'daily log aggregation returns hospital groups');
perfOk($globalMs <= $maxDashboardMs, "global dashboard aggregate stays within {$maxDashboardMs} ms ({$globalMs} ms)");

$started = microtime(true);
$local = $model->getExecutiveAggregates($hospitalIds[0], '2026-10', '2026-10-05');
$localMs = elapsedMs($started);
perfOk((int)$local['total_hospitals'] === 1, 'local dashboard scope remains one hospital');
perfOk((int)$local['total_staff'] === 20, 'local dashboard scopes staff by hospital');
perfOk((int)$local['on_duty_today'] === 20, 'local dashboard scopes current duty by hospital');
perfOk($localMs <= $maxDashboardMs, "local dashboard aggregate stays within {$maxDashboardMs} ms ({$localMs} ms)");

$started = microtime(true);
$live = $model->getExecutiveLiveDetails(null, '2026-10');
$liveMs = elapsedMs($started);
perfOk(count($live['fatigue_staff']) === 5, 'live fatigue query remains bounded');
perfOk(count($live['recent_leaves']) === 5, 'recent leave query remains bounded');
perfOk(count($live['recent_logs']) === 6, 'recent log query remains bounded');
perfOk($liveMs <= $maxLiveMs, "live dashboard detail stays within {$maxLiveMs} ms ({$liveMs} ms)");

$cacheDir = sys_get_temp_dir() . '/roster_perf_regression_' . bin2hex(random_bytes(4));
$cache = new SimpleCache($cacheDir);
$producerCalls = 0;

$first = $cache->remember(
    'dashboard:global:2026-10-05',
    30,
    static function() use (&$producerCalls, $model): array {
        $producerCalls++;
        return $model->getExecutiveAggregates(null, '2026-10', '2026-10-05');
    }
);

$started = microtime(true);
$second = $cache->remember(
    'dashboard:global:2026-10-05',
    30,
    static function() use (&$producerCalls, $model): array {
        $producerCalls++;
        return $model->getExecutiveAggregates(null, '2026-10', '2026-10-05');
    }
);
$cacheHitMs = elapsedMs($started);

perfOk(($first['hit'] ?? true) === false, 'first aggregate cache access is a miss');
perfOk(($second['hit'] ?? false) === true, 'second aggregate cache access is a hit');
perfOk($producerCalls === 1, 'cache hit avoids dashboard recomputation');
perfOk($cacheHitMs <= $maxCacheHitMs, "cache hit stays within {$maxCacheHitMs} ms ({$cacheHitMs} ms)");

$plan = $db->query(
    "EXPLAIN SELECT id, action, created_at
     FROM logs
     WHERE created_at >= '2026-10-05 00:00:00'
       AND created_at < '2026-10-06 00:00:00'
     ORDER BY created_at DESC, id DESC
     LIMIT 50"
)->fetch(PDO::FETCH_ASSOC) ?: [];

$possibleKeys = array_filter(array_map('trim', explode(',', (string)($plan['possible_keys'] ?? ''))));
perfOk(
    in_array('idx_logs_created_id', $possibleKeys, true),
    'log range query exposes created_at ordering index'
);
perfOk(!empty($plan['key']), 'log range query selects an index on populated data');

foreach (glob($cacheDir . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($cacheDir);

echo json_encode([
    'global_dashboard_ms' => $globalMs,
    'local_dashboard_ms' => $localMs,
    'live_detail_ms' => $liveMs,
    'cache_hit_ms' => $cacheHitMs,
    'log_plan_key' => $plan['key'] ?? null,
    'logs_seeded' => 12000,
    'shifts_seeded' => 80 * 31,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;

echo "PERFORMANCE_REGRESSION_OK\n";
