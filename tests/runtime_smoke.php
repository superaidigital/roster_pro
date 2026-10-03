<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/ShiftModel.php';
require_once __DIR__ . '/../models/SwapModel.php';
require_once __DIR__ . '/../models/LeaveModel.php';
require_once __DIR__ . '/../models/NotificationModel.php';
require_once __DIR__ . '/../models/RosterModel.php';
require_once __DIR__ . '/../models/FieldVisitModel.php';

function ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

$db = (new Database())->getConnection();
ok($db instanceof PDO, 'database connection');

$db->exec("INSERT INTO hospitals (hospital_code, name, short_name, is_active) VALUES ('T001', 'Synthetic Test Hospital', 'TEST', 1)");
$hospitalId = (int)$db->lastInsertId();
ok($hospitalId > 0, 'synthetic hospital created');

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

$shiftModel = new ShiftModel($db);
$shift1 = $shiftModel->addShift('2026-10-10', 'บ', $uid1, $hospitalId);
$shift2 = $shiftModel->addShift('2026-10-11', 'ร', $uid2, $hospitalId);
ok((bool)$shift1 && (bool)$shift2, 'two shifts created');
ok($shiftModel->getRosterStatus($hospitalId, '2026-10') === 'DRAFT', 'roster status initialized');

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
