<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$sqlPath = $root . '/database/schema.sql';

if (!is_file($sqlPath)) {
    fwrite(STDERR, "Schema file not found: database/schema.sql\n");
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
    'field_visits' => ['id','hospital_id','created_by','visit_date','patient_ref','visit_type','status','latitude','longitude','photo_consent','created_at','updated_at'],
    'field_visit_photos' => ['id','field_visit_id','stored_path','original_name','mime_type','file_size','created_at'],
    'schema_migrations' => ['version','checksum','status','batch','applied_by','started_at','applied_at','execution_ms','error_message'],
    'roster_snapshots' => ['id','hospital_id','month_year','snapshot_kind','status_snapshot','shift_count','shifts_json','checksum','is_protected','created_by','created_at'],
    'roster_audit_logs' => ['id','hospital_id','month_year','actor_user_id','action_type','entity_type','before_json','after_json','metadata_json','created_at'],
    'roster_revisions' => ['id','hospital_id','hospital_name','month_year','revision_no','revision_code','snapshot_id','prepared_by','reviewed_by','approved_by','staff_json','holidays_json','shifts_json','pay_summary_json','content_hash','verification_code','created_at'],
    'observability_events' => ['id','fingerprint','severity','category','message','exception_class','source_file','source_line','route','request_id','user_id','hospital_id','context_json','occurrence_count','status','first_seen_at','last_seen_at','resolved_at','resolved_by'],
    'background_jobs' => ['id','job_type','dedupe_key','payload_json','status','priority','attempts','max_attempts','available_at','locked_at','lock_token','last_error','created_at','updated_at','completed_at'],
    'system_health_snapshots' => ['id','overall_status','db_status','migration_pending','migration_blocking','queue_pending','queue_failed','open_errors_24h','disk_free_mb','created_at'],
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
