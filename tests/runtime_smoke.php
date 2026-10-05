<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/HospitalModel.php';
require_once __DIR__ . '/../models/ShiftModel.php';
require_once __DIR__ . '/../models/SwapModel.php';
require_once __DIR__ . '/../models/LeaveModel.php';
require_once __DIR__ . '/../models/NotificationModel.php';
require_once __DIR__ . '/../models/RosterModel.php';
require_once __DIR__ . '/../models/RosterSnapshotModel.php';
require_once __DIR__ . '/../models/RosterAuditModel.php';
require_once __DIR__ . '/../models/RosterRevisionModel.php';
require_once __DIR__ . '/../models/FieldVisitModel.php';
require_once __DIR__ . '/../models/AppEventModel.php';
require_once __DIR__ . '/../models/BackgroundJobModel.php';
require_once __DIR__ . '/../lib/ObservabilityService.php';
require_once __DIR__ . '/../lib/BackupRetention.php';
require_once __DIR__ . '/../lib/AppMonitor.php';
require_once __DIR__ . '/../controllers/StaffController.php';
require_once __DIR__ . '/../controllers/ProfileController.php';

function ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

$db = (new Database())->getConnection();
ok($db instanceof PDO, 'database connection');

ok(security_is_valid_route_token('roster'), 'valid route token accepted');
ok(!security_is_valid_route_token('../config'), 'path traversal route token rejected');
ok(
    security_safe_local_redirect('https://evil.example/phish', 'index.php?c=dashboard') === 'index.php?c=dashboard',
    'external notification redirect rejected'
);
ok(
    security_safe_local_redirect('index.php?c=roster&a=index', 'index.php?c=dashboard') === 'index.php?c=roster&a=index',
    'local application redirect accepted'
);

putenv('APP_BASE_URL=https://roster.example.test/app');
ok(
    security_absolute_app_url('index.php?c=verify&a=revision&code=ABC') ===
        'https://roster.example.test/app/index.php?c=verify&a=revision&code=ABC',
    'absolute app URL helper honors configured public base URL'
);
putenv('APP_BASE_URL');

$db->exec("INSERT INTO hospitals (hospital_code, name, short_name, is_active) VALUES ('T001', 'Synthetic Test Hospital', 'TEST', 1)");
$hospitalId = (int)$db->lastInsertId();
ok($hospitalId > 0, 'synthetic hospital created');

$hospitalModel = new HospitalModel($db);
$hospital = $hospitalModel->getHospitalById($hospitalId);
ok(
    is_array($hospital) && (int)$hospital['id'] === $hospitalId,
    'HospitalModel can read synthetic hospital without runtime schema mutation'
);

$users = new UserModel($db);
$baseUser = [
    'hospital_id' => $hospitalId,
    'password' => 'SmokePass!2026',
    'phone' => null,
    'role' => 'STAFF',
    'type' => 'Test Staff',
    'position' => 'Test Position',
    'color_theme' => 'primary',
    'employee_type' => 'ข้าราชการ/พนักงานท้องถิ่น',
    'start_date' => '2020-01-01',
    'id_card' => null,
    'position_number' => null,
    'pay_rate_id' => null,
];

$u1 = $baseUser;
$u1['username'] = 'smoke_user_1';
$u1['name'] = 'Synthetic User One';
ok($users->addUser($u1), 'first synthetic user created');
$user1 = $users->login('smoke_user_1', 'SmokePass!2026');
ok(is_array($user1) && (int)$user1['id'] > 0, 'UserModel login verifies password hash');

$u2 = $baseUser;
$u2['username'] = 'smoke_user_2';
$u2['name'] = 'Synthetic User Two';
ok($users->addUser($u2), 'second synthetic user created');
$user2 = $users->login('smoke_user_2', 'SmokePass!2026');
ok(is_array($user2) && (int)$user2['id'] > 0, 'second user login');

$uid1 = (int)$user1['id'];
$uid2 = (int)$user2['id'];

// Authorization regression: local managers must never mutate/read personnel in another hospital.
$db->exec("INSERT INTO hospitals (hospital_code, name, short_name, is_active) VALUES ('T002', 'Synthetic Other Hospital', 'TEST2', 1)");
$otherHospitalId = (int)$db->lastInsertId();
ok($otherHospitalId > 0, 'second synthetic hospital created');

$u3 = $baseUser;
$u3['hospital_id'] = $otherHospitalId;
$u3['username'] = 'smoke_user_other_unit';
$u3['name'] = 'Synthetic Other Unit User';
ok($users->addUser($u3), 'cross-unit synthetic user created');
$user3 = $users->login('smoke_user_other_unit', 'SmokePass!2026');
ok(is_array($user3) && (int)$user3['id'] > 0, 'cross-unit user login');
$uid3 = (int)$user3['id'];

security_start_session();

$staffController = new StaffController();
$staffScope = new ReflectionMethod(StaffController::class, 'canManageTargetUser');
$staffScope->setAccessible(true);

$_SESSION['user'] = [
    'id' => $uid1,
    'role' => 'SCHEDULER',
    'hospital_id' => $hospitalId,
    'name' => 'Synthetic Scheduler',
];
ok(
    $staffScope->invoke($staffController, ['id' => $uid2, 'role' => 'STAFF', 'hospital_id' => $hospitalId]) === true,
    'scheduler can manage staff in own hospital'
);
ok(
    $staffScope->invoke($staffController, ['id' => $uid3, 'role' => 'STAFF', 'hospital_id' => $otherHospitalId]) === false,
    'scheduler cannot manage staff in another hospital'
);
ok(
    $staffScope->invoke($staffController, ['id' => 99991, 'role' => 'DIRECTOR', 'hospital_id' => $hospitalId]) === false,
    'scheduler cannot manage director'
);

$_SESSION['user']['role'] = 'DIRECTOR';
ok(
    $staffScope->invoke($staffController, ['id' => $uid2, 'role' => 'STAFF', 'hospital_id' => $hospitalId]) === true,
    'director can manage staff in own hospital'
);
ok(
    $staffScope->invoke($staffController, ['id' => $uid3, 'role' => 'STAFF', 'hospital_id' => $otherHospitalId]) === false,
    'director cannot manage staff in another hospital'
);

$profileController = new ProfileController();
$profileScope = new ReflectionMethod(ProfileController::class, 'canManageProfile');
$profileScope->setAccessible(true);
ok(
    $profileScope->invoke($profileController, $uid2) === true,
    'director can read profile in own hospital'
);
ok(
    $profileScope->invoke($profileController, $uid3) === false,
    'director cannot read profile in another hospital'
);

// Restore a normal staff session for the rest of the smoke flow.
$_SESSION['user'] = $user1;

// Production observability + reliability regression.
$eventModel = new AppEventModel($db);
$eventFingerprint = hash('sha256', 'runtime-observability-dedupe');
$eventPayload = [
    'fingerprint' => $eventFingerprint,
    'severity' => 'ERROR',
    'category' => 'RUNTIME_TEST',
    'message' => 'Synthetic repeated runtime event',
    'exception_class' => 'RuntimeException',
    'source_file' => 'tests/runtime_smoke.php',
    'source_line' => __LINE__,
    'route' => 'runtime::smoke',
    'request_id' => 'runtime-smoke-request',
    'user_id' => $uid1,
    'hospital_id' => $hospitalId,
    'context' => ['fixture' => true],
];
$eventId1 = $eventModel->record($eventPayload);
$eventId2 = $eventModel->record($eventPayload);
ok($eventId1 > 0 && $eventId1 === $eventId2, 'observability event deduplicates by fingerprint');

$eventStmt = $db->prepare("SELECT occurrence_count, status FROM observability_events WHERE id = ?");
$eventStmt->execute([$eventId1]);
$eventRow = $eventStmt->fetch(PDO::FETCH_ASSOC);
ok((int)($eventRow['occurrence_count'] ?? 0) === 2, 'observability event increments occurrence count');
ok(($eventRow['status'] ?? '') === 'OPEN', 'observability event is open before resolution');
ok($eventModel->resolve($eventId1, $uid1), 'observability event can be resolved');

AppMonitor::record(
    'ERROR',
    'RUNTIME_PRIVACY',
    'token=runtime-secret-value',
    [
        'password' => 'super-secret-password',
        'source_file' => __FILE__,
        'source_line' => __LINE__,
    ]
);
$privacyStmt = $db->query(
    "SELECT id, message, context_json
     FROM observability_events
     WHERE category = 'RUNTIME_PRIVACY'
     ORDER BY id DESC LIMIT 1"
);
$privacyEvent = $privacyStmt->fetch(PDO::FETCH_ASSOC);
$privacyContext = json_decode((string)($privacyEvent['context_json'] ?? '{}'), true);
ok(str_contains((string)($privacyEvent['message'] ?? ''), '[REDACTED]'), 'app monitor redacts secrets from error messages');
ok(
    (($privacyContext['context']['password'] ?? '') === '[REDACTED]'),
    'app monitor redacts sensitive context values'
);
ok($eventModel->resolve((int)$privacyEvent['id'], $uid1), 'privacy test event resolved');

$jobModel = new BackgroundJobModel($db);
$retryJobId = $jobModel->enqueue(
    'RUNTIME_RETRY_TEST',
    ['fixture' => true],
    1,
    10,
    null,
    'runtime-retry-job'
);
ok(is_int($retryJobId) && $retryJobId > 0, 'retryable background job enqueued');
ok(
    $jobModel->enqueue('RUNTIME_RETRY_TEST', ['fixture' => true], 1, 10, null, 'runtime-retry-job') === null,
    'background job dedupe key prevents duplicate enqueue'
);

$claimedRetry = $jobModel->claimNext();
ok(
    is_array($claimedRetry) && (int)$claimedRetry['id'] === $retryJobId,
    'background queue claims next available job'
);
$retryStatus = $jobModel->fail(
    (int)$claimedRetry['id'],
    (string)$claimedRetry['lock_token'],
    'Synthetic worker failure'
);
ok($retryStatus === 'FAILED', 'job reaches FAILED after max attempts');
ok($jobModel->retryFailed($retryJobId), 'failed job can be manually returned to retry queue');

$claimedRetryAgain = $jobModel->claimNext();
ok(
    is_array($claimedRetryAgain) && (int)$claimedRetryAgain['id'] === $retryJobId,
    'manually retried job becomes claimable'
);
ok(
    $jobModel->complete((int)$claimedRetryAgain['id'], (string)$claimedRetryAgain['lock_token']),
    'retried background job can complete'
);

$notificationModel = new NotificationModel($db);
$queuedNotificationId = $notificationModel->queueNotification(
    $uid1,
    'INFO',
    'Queued runtime notification',
    'Synthetic queued notification payload',
    'index.php?c=notification',
    'runtime-notification-' . $uid1
);
ok(is_int($queuedNotificationId) && $queuedNotificationId > 0, 'notification retry queue API enqueues durable job');

$notificationJob = $jobModel->claimNext();
ok(
    is_array($notificationJob)
        && (int)$notificationJob['id'] === $queuedNotificationId
        && ($notificationJob['job_type'] ?? '') === 'IN_APP_NOTIFICATION',
    'queued notification is claimed as an IN_APP_NOTIFICATION job'
);
$notificationPayload = $notificationJob['payload'] ?? [];
ok(
    $notificationModel->addNotification(
        (int)$notificationPayload['user_id'],
        (string)$notificationPayload['type'],
        (string)$notificationPayload['title'],
        (string)$notificationPayload['message'],
        (string)$notificationPayload['link']
    ),
    'queued notification handler persists in-app notification'
);
ok(
    $jobModel->complete((int)$notificationJob['id'], (string)$notificationJob['lock_token']),
    'queued notification job marked complete'
);
$userNotifications = $notificationModel->getUserNotifications($uid1, 10);
ok(
    (bool)array_filter(
        $userNotifications,
        static fn(array $row): bool => ($row['title'] ?? '') === 'Queued runtime notification'
    ),
    'queued notification becomes visible to target user'
);

$observabilityService = new ObservabilityService($db);
$healthSnapshotId = $observabilityService->captureHealthSnapshot();
ok($healthSnapshotId > 0, 'system health snapshot captured');
$healthSnapshots = $observabilityService->recentSnapshots(5);
ok(
    count($healthSnapshots) >= 1 && (int)$healthSnapshots[0]['id'] === $healthSnapshotId,
    'health snapshot history returns newest snapshot first'
);

$retentionDir = sys_get_temp_dir() . '/roster_backup_retention_' . bin2hex(random_bytes(4));
mkdir($retentionDir, 0700, true);
$retentionFiles = [
    $retentionDir . '/backup_oldest.sql.gz',
    $retentionDir . '/backup_old.sql.gz',
    $retentionDir . '/backup_keep.sql.gz',
];
foreach ($retentionFiles as $index => $file) {
    file_put_contents($file, 'synthetic-backup-' . $index);
    touch($file, time() - ((60 - ($index * 10)) * 86400));
}
file_put_contents($retentionFiles[0] . '.sha256', str_repeat('a', 64) . "  backup_oldest.sql.gz\n");
$retentionResult = BackupRetention::cleanup($retentionDir, 30, 1);
ok((int)$retentionResult['deleted'] === 2, 'backup retention removes expired backups beyond minimum keep count');
ok(is_file($retentionFiles[2]), 'backup retention preserves minimum newest backup');
ok(!is_file($retentionFiles[0] . '.sha256'), 'backup retention removes checksum sidecar with expired backup');
foreach (glob($retentionDir . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($retentionDir);

$jobSummaryAfter = $jobModel->summary();
ok((int)$jobSummaryAfter['failed'] === 0, 'runtime reliability flow leaves no failed jobs');


$shiftModel = new ShiftModel($db);
$shift1 = $shiftModel->addShift('2026-10-10', 'บ', $uid1, $hospitalId);
$shift2 = $shiftModel->addShift('2026-10-11', 'ร', $uid2, $hospitalId);
ok((bool)$shift1 && (bool)$shift2, 'two shifts created');
ok($shiftModel->getRosterStatus($hospitalId, '2026-10') === 'DRAFT', 'roster status initialized');

$auditModel = new RosterAuditModel($db);
$auditId = $auditModel->record(
    $hospitalId,
    '2026-10',
    $uid1,
    'SHIFT_SET',
    ['shift_type' => null],
    ['shift_type' => 'บ'],
    ['source' => 'RUNTIME_SMOKE'],
    $uid1,
    '2026-10-10',
    'SHIFT'
);
ok($auditId > 0, 'structured roster audit event created');

$auditEvents = $auditModel->listEvents($hospitalId, '2026-10', 10);
ok(count($auditEvents) >= 1, 'roster audit timeline returns events');
$firstAudit = $auditEvents[0];
ok((int)$firstAudit['id'] === $auditId, 'roster audit timeline sorts newest event first');
ok(($firstAudit['before']['shift_type'] ?? null) === null, 'roster audit preserves before value');
ok(($firstAudit['after']['shift_type'] ?? null) === 'บ', 'roster audit preserves after value');
ok(($firstAudit['metadata']['source'] ?? '') === 'RUNTIME_SMOKE', 'roster audit preserves metadata');
ok((int)$firstAudit['target_user_id'] === $uid1, 'roster audit preserves target user');
ok($firstAudit['shift_date'] === '2026-10-10', 'roster audit preserves target shift date');
ok(!method_exists($auditModel, 'delete'), 'roster audit model exposes no delete API');

$otherAuditId = $auditModel->record(
    $otherHospitalId,
    '2026-10',
    $uid3,
    'SHIFT_SET',
    null,
    ['shift_type' => 'ร'],
    ['source' => 'OTHER_UNIT'],
    $uid3,
    '2026-10-12',
    'SHIFT'
);
ok($otherAuditId > 0, 'cross-unit audit fixture created');
$auditEventsScoped = $auditModel->listEvents($hospitalId, '2026-10', 20);
ok(
    count(array_filter($auditEventsScoped, static fn(array $event): bool => (int)$event['hospital_id'] === $otherHospitalId)) === 0,
    'roster audit timeline prevents cross-hospital reads'
);

$snapshotModel = new RosterSnapshotModel($db);
$snapshotId = $snapshotModel->createSnapshot(
    $hospitalId,
    '2026-10',
    $uid1,
    'MANUAL',
    'Runtime smoke checkpoint'
);
ok($snapshotId > 0, 'roster snapshot created for current month');

$snapshotList = $snapshotModel->listSnapshots($hospitalId, '2026-10', 10);
ok(
    count($snapshotList) >= 1 && (int)$snapshotList[0]['id'] === $snapshotId,
    'roster snapshot history lists newest version'
);

// Change the roster after the checkpoint, then restore it.
$db->prepare("DELETE FROM shifts WHERE hospital_id = ? AND user_id = ? AND shift_date = ?")
   ->execute([$hospitalId, $uid1, '2026-10-10']);
$shiftModel->addShift('2026-10-12', 'ย', $uid1, $hospitalId);

$restoreResult = $snapshotModel->restoreSnapshot($snapshotId, $hospitalId, '2026-10', $uid1);
ok((int)$restoreResult['restored_shift_count'] === 2, 'roster snapshot restore reports original shift count');

$restoredCount = $db->prepare("SELECT COUNT(*) FROM shifts WHERE hospital_id = ? AND shift_date LIKE '2026-10-%'");
$restoredCount->execute([$hospitalId]);
ok((int)$restoredCount->fetchColumn() === 2, 'roster restore replaces changed month atomically');

$restoredCheck = $db->prepare("SELECT COUNT(*) FROM shifts WHERE hospital_id = ? AND user_id = ? AND shift_date = ? AND shift_type = ?");
$restoredCheck->execute([$hospitalId, $uid1, '2026-10-10', 'บ']);
ok((int)$restoredCheck->fetchColumn() === 1, 'roster restore recovers original requestor shift');
$restoredCheck->execute([$hospitalId, $uid2, '2026-10-11', 'ร']);
ok((int)$restoredCheck->fetchColumn() === 1, 'roster restore recovers original target shift');
ok($shiftModel->getRosterStatus($hospitalId, '2026-10') === 'DRAFT', 'restored roster is forced back to DRAFT');

$versionsAfterRestore = $snapshotModel->listSnapshots($hospitalId, '2026-10', 10);
ok(
    count(array_filter($versionsAfterRestore, static fn(array $v): bool => ($v['snapshot_kind'] ?? '') === 'BEFORE_RESTORE')) >= 1,
    'restore automatically creates a before-restore safety snapshot'
);

$integritySnapshotId = $snapshotModel->createSnapshot(
    $hospitalId,
    '2026-10',
    $uid1,
    'MANUAL',
    'Integrity smoke checkpoint'
);
$db->prepare("UPDATE roster_snapshots SET shifts_json = ? WHERE id = ?")
   ->execute(['[]', $integritySnapshotId]);
$integrityRejected = false;
try {
    $snapshotModel->restoreSnapshot($integritySnapshotId, $hospitalId, '2026-10', $uid1);
} catch (RuntimeException $e) {
    $integrityRejected = str_contains($e->getMessage(), 'integrity');
}
ok($integrityRejected, 'tampered roster snapshot is rejected by checksum verification');

// Immutable approved roster revision regression.
$signatureOne = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
$signatureTwo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2X0sAAAAASUVORK5CYII=';
ok($users->updateSignature($uid1, $signatureOne), 'preparer digital signature fixture saved');
ok($users->updateSignature($uid2, $signatureOne), 'approver digital signature fixture saved');

$shiftModel->addShift('2026-11-05', 'บ', $uid1, $hospitalId);
$shiftModel->addShift('2026-11-06', 'ร', $uid2, $hospitalId);

$db->prepare(
    "INSERT INTO holidays (hospital_id, status, holiday_date, holiday_name, holiday_type, is_active)
     VALUES (?, 'APPROVED', '2026-11-10', 'Synthetic Revision Holiday', 'SPECIAL', 1)"
)->execute([$hospitalId]);

$revisionPay = json_encode([
    $uid1 => ['pay' => 1000],
    $uid2 => ['pay' => 1500],
], JSON_UNESCAPED_UNICODE);

$db->prepare(
    "UPDATE roster_status
     SET status = 'APPROVED',
         creator_id = ?,
         reviewer_id = ?,
         director_id = ?,
         submitted_at = '2026-11-01 08:00:00',
         pay_summary = ?
     WHERE hospital_id = ? AND month_year = '2026-11'"
)->execute([$uid1, $uid2, $uid2, $revisionPay, $hospitalId]);

$approvedSnapshot1 = $snapshotModel->createSnapshot(
    $hospitalId,
    '2026-11',
    $uid2,
    'APPROVED',
    'Runtime approved revision 1',
    true
);

$revisionModel = new RosterRevisionModel($db);
$db->beginTransaction();
$revisionId1 = $revisionModel->createApprovedRevision(
    $hospitalId,
    '2026-11',
    $approvedSnapshot1,
    $uid2
);
$db->commit();

$revision1 = $revisionModel->getRevision($revisionId1, $hospitalId);
ok(is_array($revision1), 'first immutable approved revision loaded');
ok($revision1['revision_code'] === 'REV-2026-11-001', 'immutable approved revision REV-2026-11-001 created');
ok($revisionModel->verifyRevision($revisionId1, $hospitalId), 'first approved revision hash verifies');
$verificationCode1 = (string)($revision1['verification_code'] ?? '');
ok(
    preg_match('/^[A-F0-9]{32}$/', $verificationCode1) === 1,
    'first revision exposes a 32-character public verification code'
);
$publicVerification1 = $revisionModel->getPublicVerification($verificationCode1);
ok(is_array($publicVerification1), 'public verification resolves REV-001 without roster payload');
ok(!empty($publicVerification1['integrity_valid']), 'public verification validates REV-001 integrity');
ok(!empty($publicVerification1['is_latest']), 'REV-001 is latest before a second approval');
ok(!array_key_exists('staff_json', $publicVerification1), 'public verification does not expose staff payload');
ok(!array_key_exists('shifts_json', $publicVerification1), 'public verification does not expose shift payload');
ok(!array_key_exists('pay_summary_json', $publicVerification1), 'public verification does not expose pay payload');
ok(count($revision1['shifts'] ?? []) === 2, 'approved revision freezes shift payload');
ok(count($revision1['holidays'] ?? []) === 1, 'approved revision freezes holiday payload');
ok(($revision1['pay_summary'][$uid1]['pay'] ?? null) === 1000, 'approved revision freezes pay summary');
ok(($revision1['prepared_signature'] ?? '') === $signatureOne, 'approved revision freezes preparer signature');
ok(($revision1['approved_signature'] ?? '') === $signatureOne, 'approved revision freezes approver signature');
ok(!method_exists($revisionModel, 'delete'), 'roster revision model exposes no delete API');
ok(!method_exists($revisionModel, 'update'), 'roster revision model exposes no update API');

// Mutate live data and signatures. REV-001 must remain unchanged.
$db->prepare("DELETE FROM shifts WHERE hospital_id = ? AND shift_date LIKE '2026-11-%'")
   ->execute([$hospitalId]);
$shiftModel->addShift('2026-11-20', 'ย', $uid1, $hospitalId);
ok($users->updateSignature($uid1, $signatureTwo), 'live preparer signature changed after approval');

$revision1AfterLiveEdit = $revisionModel->getRevision($revisionId1, $hospitalId);
ok(count($revision1AfterLiveEdit['shifts'] ?? []) === 2, 'REV-001 remains unchanged after live roster edits');
ok(($revision1AfterLiveEdit['prepared_signature'] ?? '') === $signatureOne, 'REV-001 keeps original signature after profile signature change');
ok($revisionModel->verifyRevision($revisionId1, $hospitalId), 'REV-001 still verifies after live data changes');

// Re-approve the edited live roster and create a new official revision.
$db->prepare(
    "UPDATE roster_status
     SET status = 'APPROVED',
         creator_id = ?,
         reviewer_id = ?,
         director_id = ?,
         submitted_at = '2026-11-15 08:00:00',
         pay_summary = ?
     WHERE hospital_id = ? AND month_year = '2026-11'"
)->execute([$uid1, $uid2, $uid2, $revisionPay, $hospitalId]);

$approvedSnapshot2 = $snapshotModel->createSnapshot(
    $hospitalId,
    '2026-11',
    $uid2,
    'APPROVED',
    'Runtime approved revision 2',
    true
);

$db->beginTransaction();
$revisionId2 = $revisionModel->createApprovedRevision(
    $hospitalId,
    '2026-11',
    $approvedSnapshot2,
    $uid2
);
$db->commit();

$revision2 = $revisionModel->getRevision($revisionId2, $hospitalId);
ok($revision2['revision_code'] === 'REV-2026-11-002', 'second approval creates REV-2026-11-002 instead of overwriting REV-001');
ok(count($revision2['shifts'] ?? []) === 1, 'REV-002 contains newly approved live roster');
ok($revisionModel->verifyRevision($revisionId2, $hospitalId), 'second approved revision hash verifies');
$verificationCode2 = (string)($revision2['verification_code'] ?? '');
ok(
    preg_match('/^[A-F0-9]{32}$/', $verificationCode2) === 1
        && $verificationCode2 !== $verificationCode1,
    'REV-002 receives a distinct verification code'
);
$publicVerificationOld = $revisionModel->getPublicVerification($verificationCode1);
ok(
    is_array($publicVerificationOld)
        && !empty($publicVerificationOld['integrity_valid'])
        && empty($publicVerificationOld['is_latest'])
        && ($publicVerificationOld['latest_revision_code'] ?? '') === 'REV-2026-11-002',
    'REV-001 public verification becomes superseded after REV-002'
);
$publicVerificationLatest = $revisionModel->getPublicVerification($verificationCode2);
ok(
    is_array($publicVerificationLatest)
        && !empty($publicVerificationLatest['integrity_valid'])
        && !empty($publicVerificationLatest['is_latest']),
    'REV-002 public verification is valid and latest'
);

$revisionList = $revisionModel->listRevisions($hospitalId, '2026-11', 10);
ok(count($revisionList) === 2, 'official revision history retains both approvals');
ok($revisionList[0]['revision_code'] === 'REV-2026-11-002', 'official revision history sorts newest revision first');

// Direct database tampering must be detectable.
$db->prepare("UPDATE roster_revisions SET hospital_name = ? WHERE id = ?")
   ->execute(['Tampered Hospital Name', $revisionId1]);
ok(!$revisionModel->verifyRevision($revisionId1, $hospitalId), 'tampered official revision fails SHA-256 verification');
$publicAfterTamper = $revisionModel->getPublicVerification($verificationCode1);
ok(
    is_array($publicAfterTamper) && empty($publicAfterTamper['integrity_valid']),
    'public verification reports tampered revision as invalid'
);

$tamperedVerificationCode = str_repeat('A', 32);
if ($tamperedVerificationCode === $verificationCode2) {
    $tamperedVerificationCode = str_repeat('B', 32);
}
$db->prepare("UPDATE roster_revisions SET verification_code = ? WHERE id = ?")
   ->execute([$tamperedVerificationCode, $revisionId2]);
$publicCodeTamper = $revisionModel->getPublicVerification($tamperedVerificationCode);
ok(
    is_array($publicCodeTamper)
        && empty($publicCodeTamper['verification_code_valid'])
        && empty($publicCodeTamper['integrity_valid']),
    'public verification rejects a database-tampered verification code'
);
ok(
    $revisionModel->getPublicVerification('NOT-A-VALID-CODE') === null,
    'public verification rejects malformed codes without database enumeration'
);


$swapModel = new SwapModel($db);
$swapData = [
    'hospital_id' => $hospitalId,
    'requestor_id' => $uid1,
    'requestor_date' => '2026-10-10',
    'requestor_shift' => 'บ',
    'target_user_id' => $uid2,
    'target_date' => '2026-10-11',
    'target_shift' => 'ร',
    'reason' => 'Synthetic runtime smoke test',
];
ok($swapModel->createRequest($swapData), 'swap request created');
$swapId = (int)$db->lastInsertId();
ok($swapModel->updateStatus($swapId, 'PENDING_DIRECTOR'), 'swap advanced to director approval');
ok($swapModel->executeSwapInRoster($swapId), 'swap executed atomically against shifts');

$check = $db->prepare("SELECT COUNT(*) FROM shifts WHERE user_id = ? AND shift_date = ? AND shift_type = ?");
$check->execute([$uid2, '2026-10-10', 'บ']);
ok((int)$check->fetchColumn() === 1, 'target user received requestor shift');
$check->execute([$uid1, '2026-10-11', 'ร']);
ok((int)$check->fetchColumn() === 1, 'requestor received target shift');
$status = $db->prepare("SELECT status FROM shift_swaps WHERE id = ?");
$status->execute([$swapId]);
ok($status->fetchColumn() === 'APPROVED', 'swap marked approved only after exchange');

$db->exec("INSERT INTO leave_quotas (leave_type, max_days, calculation_type, description) VALUES ('Synthetic Leave', 10.0, 'WORKING_DAYS', 'CI smoke')");
$leaveTypeId = (int)$db->lastInsertId();
$leaveModel = new LeaveModel($db);
ok($leaveModel->addLeaveRequest([
    'user_id' => $uid1,
    'leave_type_id' => $leaveTypeId,
    'start_date' => '2026-10-15',
    'end_date' => '2026-10-15',
    'num_days' => 0.5,
    'reason' => 'Synthetic half-day leave',
    'has_med_cert' => 0,
    'med_cert_path' => null,
]), 'half-day leave request created');
$leaveId = (int)$db->lastInsertId();
$leave = $leaveModel->getLeaveRequestById($leaveId);
ok(is_array($leave) && (float)$leave['num_days'] === 0.5, 'half-day leave duration preserved');

$notificationModel = new NotificationModel($db);
ok($notificationModel->addNotification($uid1, 'INFO', 'Smoke Test', 'Synthetic notification', 'index.php?c=dashboard'), 'notification created');
ok((int)$notificationModel->getUnreadCount($uid1) === 1, 'unread notification counted');
$notif = $notificationModel->getUserNotifications($uid1, 1);
ok(count($notif) === 1, 'notification retrieved');
ok($notificationModel->markAsRead((int)$notif[0]['id'], $uid1), 'notification marked read');
ok((int)$notificationModel->getUnreadCount($uid1) === 0, 'notification unread count cleared');

ok($notificationModel->addNotification($uid1, 'INFO', 'Delete Test', 'Owned notification', 'index.php?c=dashboard'), 'owned notification for delete test created');
$ownedNotif = $notificationModel->getUserNotifications($uid1, 1);
$ownedNotifId = (int)$ownedNotif[0]['id'];
ok($notificationModel->getNotificationById($ownedNotifId, $uid2) === null, 'notification read scope prevents cross-user access');
$notificationModel->deleteNotification($ownedNotifId, $uid2);
ok($notificationModel->getNotificationById($ownedNotifId, $uid1) !== null, 'cross-user delete cannot remove notification');
ok($notificationModel->deleteNotification($ownedNotifId, $uid1), 'owner can delete notification');
ok($notificationModel->getNotificationById($ownedNotifId, $uid1) === null, 'owned notification deletion persisted');

$fieldModel = new FieldVisitModel($db);
$fieldData = [
    'hospital_id' => $hospitalId,
    'created_by' => $uid1,
    'visit_date' => '2026-10-20',
    'patient_ref' => 'SYNTH-HN-001',
    'patient_name' => 'Synthetic Patient',
    'patient_age' => 50,
    'visit_type' => 'HOME_VISIT',
    'chief_concern' => 'Synthetic field visit',
    'systolic' => 120,
    'diastolic' => 80,
    'pulse' => 72,
    'temperature' => 36.7,
    'spo2' => 98,
    'weight' => 60.0,
    'height' => 165.0,
    'symptoms' => 'Smoke test only',
    'assessment' => 'Synthetic assessment',
    'care_plan' => 'Synthetic plan',
    'risk_level' => 'HIGH',
    'follow_up_date' => date('Y-m-d'),
    'follow_up_status' => 'PENDING',
    'referral_required' => 1,
    'referral_note' => 'Synthetic referral note',
    'latitude' => 15.0,
    'longitude' => 104.0,
    'accuracy_m' => 8.0,
    'address_note' => 'Synthetic location',
    'photo_consent' => 0,
    'status' => 'DRAFT',
];

$fieldVisitId = $fieldModel->createVisit($fieldData);
ok($fieldVisitId > 0, 'field visit draft created');

$fieldDraft = $fieldModel->getVisibleVisitById($fieldVisitId, $user1);
ok(is_array($fieldDraft) && $fieldDraft['status'] === 'DRAFT', 'field draft visible to creator');

$fieldData['assessment'] = 'Updated synthetic assessment';
$fieldData['status'] = 'COMPLETED';
ok($fieldModel->updateDraft($fieldVisitId, $fieldData, $user1), 'field draft updated and completed');

$fieldSummary = $fieldModel->getSummary($user1);
ok($fieldSummary['completed_count'] === 1, 'field visit summary scoped to staff user');
ok($fieldSummary['high_risk_count'] === 1, 'high-risk field visit counted');
ok($fieldSummary['followup_due_count'] === 1, 'due follow-up field visit counted');

$fieldRows = $fieldModel->getVisibleVisits($user1, ['risk_level' => 'HIGH'], 10);
ok(count($fieldRows) === 1 && (int)$fieldRows[0]['id'] === $fieldVisitId, 'staff can filter own high-risk field visit');
ok((int)$fieldRows[0]['referral_required'] === 1, 'referral flag preserved');

$dueFollowUps = $fieldModel->getVisibleVisits($user1, ['followup' => 'due'], 10);
ok(count($dueFollowUps) === 1 && (int)$dueFollowUps[0]['id'] === $fieldVisitId, 'due follow-up filter returns pending item');

$duplicateVisit = $fieldModel->findPotentialDuplicate(
    $user1,
    'SYNTH-HN-001',
    '2026-10-20',
    $hospitalId
);
ok(is_array($duplicateVisit) && (int)$duplicateVisit['id'] === $fieldVisitId, 'duplicate field visit detected within user scope');

$followUpQueue = $fieldModel->getFollowUpQueue($user1, 10);
ok(count($followUpQueue) === 1 && (int)$followUpQueue[0]['id'] === $fieldVisitId, 'prioritized follow-up queue returns pending completed visit');

$fieldRowsOtherUser = $fieldModel->getVisibleVisits($user2, [], 10);
ok(count($fieldRowsOtherUser) === 0, 'staff field visit scope prevents cross-user read');

ok($fieldModel->markFollowUpDone($fieldVisitId, $user1), 'field follow-up marked done by owner');
$fieldAfterFollowUp = $fieldModel->getVisibleVisitById($fieldVisitId, $user1);
ok($fieldAfterFollowUp['follow_up_status'] === 'DONE', 'field follow-up completion persisted');

$dueAfterCompletion = $fieldModel->getVisibleVisits($user1, ['followup' => 'due'], 10);
ok(count($dueAfterCompletion) === 0, 'completed follow-up leaves due queue');

$rosterModel = new RosterModel($db);
ok($rosterModel->publishRoster($hospitalId, 2026, 10), 'RosterModel publishes via roster_status');
ok($shiftModel->getRosterStatus($hospitalId, '2026-10') === 'APPROVED', 'published roster status is approved');

echo "Runtime smoke test completed successfully.\n";
