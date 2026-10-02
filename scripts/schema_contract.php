<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$sqlPath = $root . '/roster_pro_db.sql';

if (!is_file($sqlPath)) {
    fwrite(STDERR, "Schema dump not found: roster_pro_db.sql\n");
    exit(1);
}

$sql = file_get_contents($sqlPath);
if ($sql === false) {
    fwrite(STDERR, "Unable to read schema dump.\n");
    exit(1);
}

$required = [
    'users' => ['id','hospital_id','username','password','role','is_active','is_deleted','deleted_at','display_order','show_in_roster','pay_rate_id','signature_path'],
    'hospitals' => ['id','hospital_code','name','is_active','deleted_at','display_order'],
    'pay_rates' => ['id','name','group_name','keywords','rate_y','rate_b','rate_r'],
    'shifts' => ['id','hospital_id','user_id','shift_date','shift_type'],
    'roster_status' => ['id','hospital_id','month_year','status','pay_summary','creator_id','reviewer_id','director_id'],
    'shift_swaps' => ['id','hospital_id','requestor_id','target_user_id','requestor_date','target_date','requestor_shift','target_shift','status'],
    'leave_requests' => ['id','user_id','leave_type_id','start_date','end_date','num_days','status','approved_by','approved_at'],
    'leave_balances' => ['id','user_id','budget_year','leave_type_id','quota_days','carried_over_days','used_days'],
    'leave_quotas' => ['id','leave_type','max_days','calculation_type'],
    'notifications' => ['id','user_id','type','title','message','link','is_read'],
    'logs' => ['id','user_id','action','details','ip_address','created_at'],
];

$errors = [];

foreach ($required as $table => $columns) {
    $pattern = '/CREATE TABLE `' . preg_quote($table, '/') . '` \\((.*?)\\n\\) ENGINE=/s';
    if (!preg_match($pattern, $sql, $match)) {
        $errors[] = 'Missing table: ' . $table;
        continue;
    }
    preg_match_all('/^\\s*`([^`]+)`/m', $match[1], $columnMatches);
    $actual = array_flip($columnMatches[1] ?? []);
    foreach ($columns as $column) {
        if (!isset($actual[$column])) {
            $errors[] = 'Missing column: ' . $table . '.' . $column;
        }
    }
}

if ($errors) {
    fwrite(STDERR, "Schema contract failed:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

echo "Schema contract OK.\n";
