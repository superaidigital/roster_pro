<?php
require_once 'lib/ElectronicSignature.php';
// ที่อยู่ไฟล์: controllers/AjaxController.php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'models/ShiftModel.php';
require_once 'models/NotificationModel.php';
require_once 'models/UserModel.php';
require_once 'models/LeaveModel.php';
require_once 'models/RosterSnapshotModel.php';
require_once 'models/RosterAuditModel.php';
require_once 'models/RosterRevisionModel.php';
require_once 'controllers/LogsController.php'; // 🌟 นำเข้า Logs Controller

class AjaxController {

    private function requireAjaxMutation(): void {
        security_start_session();
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบใหม่'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!security_is_valid_post_csrf()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'คำขอไม่ถูกต้องหรือหมดอายุ กรุณาลองใหม่'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }


    // ==========================================
    // ⚙️ Helper: ดึงค่า Config จากฐานข้อมูล
    // ==========================================
    private function getSystemSetting($db, $key) {
        try {
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['setting_value'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    // ==========================================
    // 💬 Helper: ฟังก์ชันส่งแจ้งเตือนผ่าน LINE Notify
    // ==========================================
    private function sendLineNotify($db, $message) {
        $line_token = $this->getSystemSetting($db, 'line_notify_token');
        if (empty($line_token)) return false;

        $url = "https://notify-api.line.me/api/notify";
        $data = ['message' => $message];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/x-www-form-urlencoded",
            "Authorization: Bearer " . $line_token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return $result;
    }

    // ==========================================
    // 🛡️ Helper: ตรวจสอบสิทธิ์การจัดการตารางเวร
    // ==========================================
    private function canEditRoster($hospital_id, $month_year) {
        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));
        if (!in_array($role, ['SCHEDULER', 'DIRECTOR', 'ADMIN', 'SUPERADMIN'], true)) {
            return false;
        }

        $hospital_id = (int)$hospital_id;
        $month_year = trim((string)$month_year);
        if ($hospital_id <= 0 || !preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month_year)) {
            return false;
        }

        $db = (new Database())->getConnection();
        $shiftModel = new ShiftModel($db);
        $status = strtoupper((string)$shiftModel->getRosterStatus($hospital_id, $month_year));

        return in_array($status, ['DRAFT', 'NOT_STARTED'], true);
    }

    // ==========================================
    // 💰 Helper: ฟังก์ชันอ่านเรทราคาแบบไดนามิก (Snapshot)
    // ==========================================
    private function getDynamicPayRates($staff_type, $pay_rates_db) {
        $type = $staff_type ?? '';
        foreach ($pay_rates_db as $group) {
            $keywords = explode(',', $group['keywords']);
            foreach ($keywords as $kw) {
                $kw = trim($kw);
                if (!empty($kw) && mb_strpos($type, $kw) !== false) {
                    return ['ย' => $group['rate_y'], 'บ' => $group['rate_b'], 'ร' => $group['rate_r']];
                }
            }
        }
        $last = end($pay_rates_db);
        if ($last) return ['ย' => $last['rate_y'], 'บ' => $last['rate_b'], 'ร' => $last['rate_r']];
        return ['ย' => 0, 'บ' => 0, 'ร' => 0];
    }

    // ==========================================
    // 🌟 API: ทดสอบ LINE Notify
    // ==========================================
    public function test_line_notify() {
        $this->requireAjaxMutation();
        error_reporting(0); // 🌟 ปิด Warning ไม่ให้แทรก JSON
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['SUPERADMIN', 'ADMIN'])) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
        }

        $data = json_decode(file_get_contents("php://input"));
        $token = $data->token ?? '';

        if(empty($token)) { echo json_encode(['status' => 'error', 'message' => 'Token is empty']); exit; }

        $url = "https://notify-api.line.me/api/notify";
        $message = "🟢 ทดสอบการเชื่อมต่อระบบ Roster Pro\nเวลา: " . date('Y-m-d H:i:s') . "\nหากคุณเห็นข้อความนี้ แสดงว่าระบบพร้อมส่งแจ้งเตือนแล้ว!";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['message' => $message]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded", "Authorization: Bearer " . $token]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'LINE API Returned Code: ' . $http_code]);
        }
        exit;
    }

    // ==========================================
    // 🌟 API: บันทึกเวร (Save Shift)
    // ==========================================
    public function save_shift() {
        $this->requireAjaxMutation();
        error_reporting(0);
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents("php://input"));
        if (!is_object($data)) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'รูปแบบข้อมูลไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = (new Database())->getConnection();
        $shiftModel = new ShiftModel($db);
        $notifModel = new NotificationModel($db);
        $leaveModel = class_exists('LeaveModel') ? new LeaveModel($db) : null;

        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));
        $hospital_id = (int)($_SESSION['user']['hospital_id'] ?? 0);
        if (isset($data->hosp_id) && $data->hosp_id !== '' && in_array($role, ['ADMIN', 'SUPERADMIN'], true)) {
            $hospital_id = (int)$data->hosp_id;
        }

        $user_id = (int)($data->user_id ?? 0);
        $date = trim((string)($data->date ?? ''));
        $shift_input = trim((string)($data->shift_type ?? ''));

        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        $validDate = $dateObj && $dateObj->format('Y-m-d') === $date;
        if ($hospital_id <= 0 || $user_id <= 0 || !$validDate) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลบุคลากร วันที่ หรือหน่วยบริการไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $month_year = substr($date, 0, 7);
        if (!$this->canEditRoster($hospital_id, $month_year)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => '⛔ คุณไม่มีสิทธิ์จัดเวร หรือตารางเดือนนี้ถูกล็อคแล้ว'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt_user = $db->prepare("
            SELECT id, hospital_id, role, is_active, is_deleted
            FROM users
            WHERE id = ? AND hospital_id = ?
            LIMIT 1
        ");
        $stmt_user->execute([$user_id, $hospital_id]);
        $targetUser = $stmt_user->fetch(PDO::FETCH_ASSOC);
        if (!$targetUser || (int)$targetUser['is_active'] !== 1 || (int)$targetUser['is_deleted'] === 1 || in_array(strtoupper((string)$targetUser['role']), ['ADMIN', 'SUPERADMIN'], true)) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'จัดเวรได้เฉพาะบุคลากรที่สังกัดหน่วยบริการนี้เท่านั้น'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $aliases = ['A' => 'บ', 'N' => 'ร', 'O' => 'ย', 'M' => 'ช'];
        $shift_array = [];
        if ($shift_input !== '') {
            $parts = preg_split('/[\\/,\\s]+/', $shift_input) ?: [];
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') continue;
                $shift_array[] = $aliases[$part] ?? $part;
            }
            $shift_array = array_values(array_unique($shift_array));

            $allowed = ['ช', 'บ', 'ร', 'ย'];
            foreach ($shift_array as $type) {
                if (!in_array($type, $allowed, true)) {
                    http_response_code(422);
                    echo json_encode(['status' => 'error', 'message' => 'รูปแบบกะปฏิบัติงานไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            if (count($shift_array) > 2) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => '⚠️ จัดเวรไม่ได้: 1 คนขึ้นเวรได้ไม่เกิน 2 กะต่อวัน'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (in_array('ช', $shift_array, true) && in_array('ร', $shift_array, true)) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => '🚨 ผิดกฎพักผ่อน: ห้ามจัดเวรเช้าควบเวรดึกในวันเดียวกัน'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $canonical_shift = implode('/', $shift_array);

        if ($canonical_shift !== '' && $leaveModel) {
            try {
                $all_leaves = $leaveModel->getLeavesByHospitalAndMonth($hospital_id, $month_year);
                $current_ts = strtotime($date);
                foreach ($all_leaves as $leave) {
                    if ((int)$leave['user_id'] === $user_id && ($leave['status'] ?? '') === 'APPROVED') {
                        $start_ts = strtotime($leave['start_date']);
                        $end_ts = strtotime($leave['end_date']);
                        if ($current_ts >= $start_ts && $current_ts <= $end_ts) {
                            echo json_encode(['status' => 'error', 'message' => "⛔ จัดเวรไม่ได้: เจ้าหน้าที่ติด '{$leave['leave_type']}'"], JSON_UNESCAPED_UNICODE);
                            exit;
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Leave validation failed during save_shift: ' . $e->getMessage());
            }
        }

        try {
            $db->beginTransaction();

            $stmtBefore = $db->prepare(
                "SELECT shift_type FROM shifts WHERE user_id = ? AND shift_date = ? AND hospital_id = ? ORDER BY id ASC"
            );
            $stmtBefore->execute([$user_id, $date, $hospital_id]);
            $beforeTypes = array_values(array_filter(
                array_map('strval', $stmtBefore->fetchAll(PDO::FETCH_COLUMN)),
                static fn(string $value): bool => $value !== ''
            ));
            $beforeShift = $beforeTypes ? implode('/', $beforeTypes) : null;

            $stmt = $db->prepare("DELETE FROM shifts WHERE user_id = ? AND shift_date = ? AND hospital_id = ?");
            $stmt->execute([$user_id, $date, $hospital_id]);

            if ($canonical_shift !== '') {
                $last_id = $shiftModel->addShift($date, $canonical_shift, $user_id, $hospital_id);

                if ($user_id !== (int)$_SESSION['user']['id']) {
                    $thai_date = date('d/m/Y', strtotime($date));
                    $notifModel->addNotification($user_id, 'INFO', 'ตารางเวรอัปเดต', "คุณถูกจัดเวร '{$canonical_shift}' ในวันที่ {$thai_date}", "index.php?c=profile&a=schedule");
                }

                LogsController::addLog($db, $_SESSION['user']['id'], 'UPDATE', "จัดเวร '{$canonical_shift}' ให้ผู้ใช้ ID:{$user_id} วันที่ {$date}");

                $auditModel = new RosterAuditModel($db);
                $auditModel->record(
                    $hospital_id,
                    $month_year,
                    (int)$_SESSION['user']['id'],
                    'SHIFT_SET',
                    ['shift_type' => $beforeShift],
                    ['shift_type' => $canonical_shift],
                    ['source' => 'ROSTER_BOARD'],
                    $user_id,
                    $date,
                    'SHIFT'
                );

                $db->commit();
                echo json_encode(['status' => 'success', 'shift_id' => $last_id, 'shift_type' => $canonical_shift], JSON_UNESCAPED_UNICODE);
            } else {
                LogsController::addLog($db, $_SESSION['user']['id'], 'DELETE', "ลบเวรของผู้ใช้ ID:{$user_id} ในวันที่ {$date}");

                $auditModel = new RosterAuditModel($db);
                $auditModel->record(
                    $hospital_id,
                    $month_year,
                    (int)$_SESSION['user']['id'],
                    'SHIFT_DELETE',
                    ['shift_type' => $beforeShift],
                    ['shift_type' => null],
                    ['source' => 'ROSTER_BOARD'],
                    $user_id,
                    $date,
                    'SHIFT'
                );

                $db->commit();
                echo json_encode(['status' => 'success', 'message' => 'Deleted'], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Ajax operation failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถดำเนินการได้ กรุณาลองใหม่'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // ==========================================
    // 🌟 API: อัปเดตลำดับรายชื่อ (Drag & Drop)
    // ==========================================
    public function update_order() {
        $this->requireAjaxMutation();
        header('Content-Type: application/json');
        
        // อนุญาตเฉพาะ POST Request และต้องล็อกอิน
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user'])) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }

        // รับข้อมูล JSON จาก Javascript Fetch API
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));
        $month_year = trim((string)($data['month_year'] ?? ''));
        $hospital_id = in_array($role, ['ADMIN', 'SUPERADMIN'], true)
            ? (int)($data['hosp_id'] ?? $_SESSION['user']['hospital_id'] ?? 0)
            : (int)($_SESSION['user']['hospital_id'] ?? 0);

        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month_year) || !$this->canEditRoster($hospital_id, $month_year)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์จัดลำดับบุคลากรในสถานะตารางปัจจุบัน']);
            exit;
        }

        if (isset($data['order']) && is_array($data['order'])) {
            $db = (new Database())->getConnection();
            
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("UPDATE users SET display_order = ? WHERE id = ? AND hospital_id = ?");
                
                foreach ($data['order'] as $item) {
                    if (isset($item['id']) && isset($item['order'])) {
                        // บวก 1 เพื่อให้ลำดับใน Database เริ่มที่ 1
                        $display_order = (int)$item['order'] + 1;
                        $stmt->execute([$display_order, (int)$item['id'], $hospital_id]);
                    }
                }
                
                $db->commit();
                echo json_encode(['status' => 'success', 'message' => 'บันทึกลำดับเรียบร้อยแล้ว']);
            } catch (PDOException $e) {
                $db->rollBack();
                error_log('Ajax database operation failed: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
        }
        exit;
    }

    // ==========================================
    // 🌟 API: คัดลอกตารางจากเดือนก่อน (Copy Previous Month)
    // ==========================================
    public function copy_roster_previous() {
        $this->requireAjaxMutation();
        error_reporting(0);
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents("php://input"));
        $target_month = trim((string)($data->target_month ?? ''));
        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));

        $hospital_id = (int)($_SESSION['user']['hospital_id'] ?? 0);
        if (isset($data->hosp_id) && $data->hosp_id !== '' && in_array($role, ['ADMIN', 'SUPERADMIN'], true)) {
            $hospital_id = (int)$data->hosp_id;
        }

        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $target_month) || $hospital_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลเดือนหรือหน่วยบริการไม่ถูกต้อง']);
            exit;
        }

        if (!$this->canEditRoster($hospital_id, $target_month)) {
            echo json_encode(['status' => 'error', 'message' => '⛔ คุณไม่มีสิทธิ์จัดการ หรือตารางเดือนนี้ถูกล็อคแล้ว']);
            exit;
        }

        $db = (new Database())->getConnection();
        $prev_month = date('Y-m', strtotime($target_month . '-01 -1 month'));

        try {
            $start_prev = $prev_month . '-01';
            $end_prev = date('Y-m-t', strtotime($start_prev));
            $stmt_get = $db->prepare("
                SELECT s.user_id, s.shift_date, s.shift_type
                FROM shifts s
                JOIN users u ON u.id = s.user_id
                WHERE s.hospital_id = ?
                  AND u.hospital_id = ?
                  AND s.shift_date BETWEEN ? AND ?
            ");
            $stmt_get->execute([$hospital_id, $hospital_id, $start_prev, $end_prev]);
            $prev_shifts = $stmt_get->fetchAll(PDO::FETCH_ASSOC);

            // Important: never delete the current month until source data is confirmed.
            if (empty($prev_shifts)) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีข้อมูลตารางเวรในเดือนก่อนหน้า จึงไม่ได้เปลี่ยนแปลงตารางเดือนปัจจุบัน']);
                exit;
            }

            $db->beginTransaction();

            $start_curr = $target_month . '-01';
            $end_curr = date('Y-m-t', strtotime($start_curr));
            $beforeCountStmt = $db->prepare("SELECT COUNT(*) FROM shifts WHERE hospital_id = ? AND shift_date BETWEEN ? AND ?");
            $beforeCountStmt->execute([$hospital_id, $start_curr, $end_curr]);
            $beforeCopyCount = (int)$beforeCountStmt->fetchColumn();

            $snapshotModel = new RosterSnapshotModel($db);
            $snapshotId = $snapshotModel->createSnapshot(
                $hospital_id,
                $target_month,
                (int)$_SESSION['user']['id'],
                'BEFORE_COPY',
                "สำรองก่อนคัดลอกจากเดือน {$prev_month}"
            );
            $stmt_del = $db->prepare("DELETE FROM shifts WHERE hospital_id = ? AND shift_date BETWEEN ? AND ?");
            $stmt_del->execute([$hospital_id, $start_curr, $end_curr]);

            $stmt_in = $db->prepare("INSERT INTO shifts (user_id, hospital_id, shift_date, shift_type) VALUES (?, ?, ?, ?)");
            $days_in_curr = (int)date('t', strtotime($start_curr));
            $copied = 0;

            foreach ($prev_shifts as $ps) {
                $day_num = (int)date('d', strtotime($ps['shift_date']));
                if ($day_num <= $days_in_curr) {
                    $new_date = $target_month . '-' . str_pad((string)$day_num, 2, '0', STR_PAD_LEFT);
                    $stmt_in->execute([(int)$ps['user_id'], $hospital_id, $new_date, $ps['shift_type']]);
                    $copied++;
                }
            }

            LogsController::addLog(
                $db,
                $_SESSION['user']['id'],
                'CREATE',
                "คัดลอกเวรจากเดือน {$prev_month} ไปยังเดือน {$target_month} จำนวน {$copied} รายการ"
            );

            $auditModel = new RosterAuditModel($db);
            $auditModel->record(
                $hospital_id,
                $target_month,
                (int)$_SESSION['user']['id'],
                'ROSTER_COPY_PREVIOUS',
                ['shift_count' => $beforeCopyCount],
                ['shift_count' => $copied],
                ['source_month' => $prev_month, 'snapshot_id' => $snapshotId],
                null,
                null,
                'ROSTER'
            );

            $db->commit();
            echo json_encode(['status' => 'success', 'copied' => $copied]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Ajax operation failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถคัดลอกตารางได้ กรุณาลองใหม่']);
        }
        exit;
    }

    // ==========================================
    // 🌟 API: ขอแลกเวร/เปลี่ยนเวร (Shift Swap Request)
    // ==========================================
    public function request_swap() {
        $this->requireAjaxMutation();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user'])) {
            header("Location: index.php?c=roster"); exit;
        }

        $db = (new Database())->getConnection();
        $notifModel = new NotificationModel($db);

        $my_shift_id = $_POST['my_shift_id'];
        $target_user_id = $_POST['target_user_id'];
        $target_date = $_POST['target_date'];
        $reason = $_POST['reason'];
        $month_year = $_POST['month_year'] ?? date('Y-m');
        $hospital_id = $_SESSION['user']['hospital_id'];
        $my_name = $_SESSION['user']['name'];

        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE hospital_id = ? AND role IN ('SCHEDULER', 'DIRECTOR')");
            $stmt->execute([$hospital_id]);
            $approvers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $msg = "{$my_name} ขอแลกเวรกับเจ้าหน้าที่ ID: {$target_user_id} ในวันที่ {$target_date} เหตุผล: {$reason}";
            $link = "index.php?c=roster&a=index&month={$month_year}";

            foreach ($approvers as $a) {
                $notifModel->addNotification($a['id'], 'WARNING', 'คำขอแลกเปลี่ยนเวรใหม่', $msg, $link);
            }

            $notifModel->addNotification($target_user_id, 'INFO', 'มีเพื่อนขอแลกเวรด้วย', "{$my_name} เสนอขอแลกเวรกับคุณในวันที่ {$target_date} กรุณาตกลงกับผู้จัดเวร", $link);

            LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "ส่งคำขอแลกเวร (Shift ID: {$my_shift_id}) กับ User ID: {$target_user_id}");

            $_SESSION['success_msg'] = "ส่งคำขอแลกเวรเรียบร้อยแล้ว กรุณารอการพิจารณาจากผู้จัดเวรหรือผู้อำนวยการ";

        } catch (Exception $e) {
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการส่งคำขอ";
        }

        header("Location: index.php?c=roster&a=index&month={$month_year}");
        exit;
    }

    // ==========================================
    // 🌟 เปลี่ยนสถานะตารางเวร (Workflow)
    // ==========================================
    public function change_status() {
        $this->requireAjaxMutation();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user'])) {
            header("Location: index.php?c=roster"); exit;
        }

        $db = (new Database())->getConnection();
        $shiftModel = new ShiftModel($db);
        $notifModel = new NotificationModel($db);

        $month_year = trim((string)($_POST['month_year'] ?? ''));
        $new_status = strtoupper(trim((string)($_POST['status'] ?? '')));
        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));

        $is_admin = in_array($role, ['ADMIN', 'SUPERADMIN'], true);
        $hospital_id = $is_admin
            ? (int)($_POST['hospital_id'] ?? $_SESSION['user']['hospital_id'] ?? 0)
            : (int)($_SESSION['user']['hospital_id'] ?? 0);

        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month_year) || $hospital_id <= 0) {
            $_SESSION['error_msg'] = 'ข้อมูลเดือนหรือหน่วยบริการไม่ถูกต้อง';
            header("Location: index.php?c=roster");
            exit;
        }

        if (!in_array($new_status, ['DRAFT', 'SUBMITTED', 'APPROVED'], true)) {
            $_SESSION['error_msg'] = 'สถานะตารางเวรไม่ถูกต้อง';
            header("Location: index.php?c=roster&month=" . urlencode($month_year));
            exit;
        }

        if (in_array($new_status, ['SUBMITTED', 'APPROVED'], true)) {
            $signatureStmt = $db->prepare("SELECT signature_path FROM users WHERE id = ? LIMIT 1");
            $signatureStmt->execute([(int)$_SESSION['user']['id']]);
            $actorSignature = trim((string)($signatureStmt->fetchColumn() ?: ''));
            if (!ElectronicSignature::isValid($actorSignature)) {
                $_SESSION['error_msg'] = 'กรุณาบันทึกลายเซ็นอิเล็กทรอนิกส์ที่ถูกต้องในโปรไฟล์ก่อนส่งหรืออนุมัติตารางเวร';
                header("Location: index.php?c=profile#nav-signature");
                exit;
            }
        }

        $current_status = strtoupper((string)$shiftModel->getRosterStatus($hospital_id, $month_year));
        if ($current_status === 'NOT_STARTED') {
            $current_status = 'DRAFT';
        }

        $allowed_transitions = [
            'SCHEDULER' => [
                'DRAFT' => ['SUBMITTED'],
            ],
            'DIRECTOR' => [
                'DRAFT' => ['SUBMITTED'],
                'SUBMITTED' => ['DRAFT', 'APPROVED'],
            ],
            'ADMIN' => [
                'DRAFT' => ['SUBMITTED'],
                'SUBMITTED' => ['DRAFT', 'APPROVED'],
                'APPROVED' => ['DRAFT'],
                'REQUEST_EDIT' => ['DRAFT', 'APPROVED'],
            ],
            'SUPERADMIN' => [
                'DRAFT' => ['SUBMITTED'],
                'SUBMITTED' => ['DRAFT', 'APPROVED'],
                'APPROVED' => ['DRAFT'],
                'REQUEST_EDIT' => ['DRAFT', 'APPROVED'],
            ],
        ];

        $allowed_for_role = $allowed_transitions[$role][$current_status] ?? [];
        if (!in_array($new_status, $allowed_for_role, true)) {
            $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์เปลี่ยนสถานะตารางเวรในขั้นตอนนี้';
            header("Location: index.php?c=roster&month=" . urlencode($month_year));
            exit;
        }

        $stmt_hosp = $db->prepare("SELECT name FROM hospitals WHERE id = ?");
        $stmt_hosp->execute([$hospital_id]);
        $hospital_name = $stmt_hosp->fetch(PDO::FETCH_ASSOC)['name'] ?? 'รพ.สต.';

        try {
            $db->beginTransaction();
            $approvedVersionId = null;
            $approvedRevisionId = null;
            $approvedRevisionCode = null;

            $shiftModel->updateRosterStatus($hospital_id, $month_year, $new_status);

            if ($new_status === 'SUBMITTED') {
                $workflowStmt = $db->prepare(
                    "UPDATE roster_status
                     SET creator_id = ?, submitted_at = NOW(), reviewer_id = NULL, director_id = NULL
                     WHERE hospital_id = ? AND month_year = ?"
                );
                $workflowStmt->execute([(int)$_SESSION['user']['id'], $hospital_id, $month_year]);
            } elseif ($new_status === 'APPROVED') {
                if ($role === 'DIRECTOR') {
                    $workflowStmt = $db->prepare(
                        "UPDATE roster_status
                         SET reviewer_id = ?, director_id = ?
                         WHERE hospital_id = ? AND month_year = ?"
                    );
                    $workflowStmt->execute([
                        (int)$_SESSION['user']['id'],
                        (int)$_SESSION['user']['id'],
                        $hospital_id,
                        $month_year
                    ]);
                } else {
                    $workflowStmt = $db->prepare(
                        "UPDATE roster_status
                         SET reviewer_id = ?
                         WHERE hospital_id = ? AND month_year = ?"
                    );
                    $workflowStmt->execute([(int)$_SESSION['user']['id'], $hospital_id, $month_year]);
                }
            }

            LogsController::addLog($db, $_SESSION['user']['id'], 'APPROVE', "เปลี่ยนสถานะตารางเวร รพ.สต. {$hospital_name} เดือน {$month_year} เป็น {$new_status}");

            if ($new_status === 'APPROVED') {
                require_once 'models/PayRateModel.php';
                $payRateModel = new PayRateModel($db);
                $userModel = new UserModel($db);
                
                $rates_db = $payRateModel->getAllRates();
                $staffs = $userModel->getAllStaff();
                
                $start_date = $month_year . '-01';
                $end_date = date('Y-m-t', strtotime($start_date));
                $shifts = $shiftModel->getShiftsByWeek($hospital_id, $start_date, $end_date);
                
                $snapshot_data = [];
                foreach ($staffs as $staff) {
                    $is_external = ($staff['hospital_id'] != $hospital_id);
                    $has_shift = false;
                    $sum_r = 0; $sum_y = 0; $sum_b = 0;
                    
                    foreach ($shifts as $s) {
                        if ($s['user_id'] == $staff['id']) {
                            $has_shift = true;
                            $val = $s['shift_type'];
                            if($val === 'ร') $sum_r++;
                            elseif($val === 'ย') $sum_y++;
                            elseif($val === 'บ') $sum_b++;
                            elseif($val === 'บ/ร' || $val === 'ร/บ') { $sum_b++; $sum_r++; }
                            elseif($val === 'ย/บ' || $val === 'บ/ย') { $sum_y++; $sum_b++; }
                        }
                    }
                    if ($is_external && !$has_shift) continue;
                    
                    $rates = $this->getDynamicPayRates($staff['type'], $rates_db);
                    $pay = ($sum_r * $rates['ร']) + ($sum_y * $rates['ย']) + ($sum_b * $rates['บ']);
                    $snapshot_data[$staff['id']] = ['pay' => $pay];
                }
                
                $json_snapshot = json_encode($snapshot_data, JSON_UNESCAPED_UNICODE);
                $stmt_snap = $db->prepare("UPDATE roster_status SET pay_summary = ? WHERE hospital_id = ? AND month_year = ?");
                $stmt_snap->execute([$json_snapshot, $hospital_id, $month_year]);

                $rosterSnapshotModel = new RosterSnapshotModel($db);
                $approvedVersionId = $rosterSnapshotModel->createSnapshot(
                    $hospital_id,
                    $month_year,
                    (int)$_SESSION['user']['id'],
                    'APPROVED',
                    'เวอร์ชันที่อนุมัติแล้ว',
                    true
                );
                LogsController::addLog(
                    $db,
                    $_SESSION['user']['id'],
                    'APPROVE',
                    "เก็บ Approved Roster Snapshot #{$approvedVersionId} เดือน {$month_year}"
                );

                $revisionModel = new RosterRevisionModel($db);
                $approvedRevisionId = $revisionModel->createApprovedRevision(
                    $hospital_id,
                    $month_year,
                    $approvedVersionId,
                    (int)$_SESSION['user']['id']
                );
                $approvedRevision = $revisionModel->getRevision($approvedRevisionId, $hospital_id);
                $approvedRevisionCode = (string)($approvedRevision['revision_code'] ?? '');

                LogsController::addLog(
                    $db,
                    $_SESSION['user']['id'],
                    'APPROVE',
                    "สร้างฉบับตารางเวรทางการ {$approvedRevisionCode} (Revision ID: {$approvedRevisionId})"
                );
                
            } elseif ($new_status === 'DRAFT' || $new_status === 'REQUEST_EDIT') {
                $stmt_snap = $db->prepare("UPDATE roster_status SET pay_summary = NULL WHERE hospital_id = ? AND month_year = ?");
                $stmt_snap->execute([$hospital_id, $month_year]);
            }

            $auditModel = new RosterAuditModel($db);
            $auditMetadata = [];
            if ($approvedVersionId !== null) {
                $auditMetadata['approved_snapshot_id'] = (int)$approvedVersionId;
            }
            if ($approvedRevisionId !== null) {
                $auditMetadata['approved_revision_id'] = (int)$approvedRevisionId;
                $auditMetadata['revision_code'] = $approvedRevisionCode;
            }
            $auditModel->record(
                $hospital_id,
                $month_year,
                (int)$_SESSION['user']['id'],
                'ROSTER_STATUS_CHANGE',
                ['status' => $current_status],
                ['status' => $new_status],
                $auditMetadata,
                null,
                null,
                'WORKFLOW'
            );
            $db->commit();

            $thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            $m = (int)substr($month_year, 5, 2);
            $month_name = $thai_months[$m] . " " . (substr($month_year, 0, 4) + 543);
            $target_link = "index.php?c=roster&a=index&month={$month_year}";

            if ($new_status === 'SUBMITTED') {
                $msg = "{$hospital_name} ส่งตารางเวรเดือน {$month_name} มาให้พิจารณาอนุมัติ";
                $this->notifyRole($hospital_id, 'DIRECTOR', 'INFO', 'มีตารางเวรรออนุมัติ', $msg, $target_link);
                $_SESSION['success_msg'] = "ส่งตารางเวรขอพิจารณาอนุมัติสำเร็จ";
                
                if ($this->getSystemSetting($db, 'line_notify_on_submit') === '1') {
                    $this->sendLineNotify($db, "\n📝 มีตารางเวรส่งมาใหม่\nหน่วยบริการ: {$hospital_name}\nเดือน: {$month_name}\nโปรดเข้าสู่ระบบเพื่อตรวจสอบครับ");
                }
                
            } elseif ($new_status === 'DRAFT') {
                if (in_array($_SESSION['user']['role'], ['ADMIN', 'SUPERADMIN'])) {
                    $msg = "ส่วนกลางอนุมัติคำขอแก้ไขตารางเวรเดือน {$month_name} แล้ว";
                    $this->notifyRole($hospital_id, 'SCHEDULER', 'SUCCESS', 'คำขอแก้ไขได้รับการอนุมัติ', $msg, $target_link);
                    $_SESSION['success_msg'] = "อนุมัติให้ {$hospital_name} แก้ไขตารางเวรเรียบร้อยแล้ว";
                } else {
                    $msg = "ตารางเวรเดือน {$month_name} ถูกส่งกลับให้ตรวจสอบและแก้ไขใหม่";
                    $this->notifyRole($hospital_id, 'SCHEDULER', 'ALERT', 'ตารางเวรถูกส่งกลับแก้ไข', $msg, $target_link);
                    $_SESSION['success_msg'] = "ตีกลับให้ผู้จัดเวรแก้ไขเรียบร้อยแล้ว";
                }
            } elseif ($new_status === 'APPROVED') {
                $msg = "ตารางเวรเดือน {$month_name} ได้รับการอนุมัติเรียบร้อยแล้ว";
                $this->notifyRole($hospital_id, 'SCHEDULER', 'SUCCESS', 'อนุมัติตารางเวรแล้ว', $msg, $target_link);
                $this->notifyRole($hospital_id, 'STAFF', 'SUCCESS', 'ประกาศตารางเวรใหม่', $msg, "index.php?c=profile&a=schedule");
                $_SESSION['success_msg'] = "อนุมัติตารางเวรเดือน {$month_name} เรียบร้อยแล้ว" . ($approvedRevisionCode ? " · {$approvedRevisionCode}" : "");
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('AjaxController error: ' . $e->getMessage());
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ";
        }

        $redirect = (in_array($_SESSION['user']['role'], ['ADMIN', 'SUPERADMIN'])) ? "index.php?c=report&a=overview&month=".$month_year : "index.php?c=roster&month=".$month_year;
        header("Location: " . $redirect);
        exit;
    }

    // ==========================================
    // 🌟 ขอแก้ไขตาราง (Request Edit)
    // ==========================================
    public function request_edit() {
        $this->requireAjaxMutation();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user'])) { header("Location: index.php?c=roster"); exit; }

        $month_year = trim((string)($_POST['month_year'] ?? ''));
        $hospital_id = (int)($_SESSION['user']['hospital_id'] ?? 0);
        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));

        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month_year) || $hospital_id <= 0) {
            $_SESSION['error_msg'] = 'ข้อมูลเดือนหรือหน่วยบริการไม่ถูกต้อง';
            header("Location: index.php?c=roster");
            exit;
        }

        if (!in_array($role, ['SCHEDULER', 'DIRECTOR'], true)) {
            $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์ขอแก้ไขตารางเวร';
            header("Location: index.php?c=roster&month=" . urlencode($month_year));
            exit;
        }
        
        $db = (new Database())->getConnection();
        $stmt_hosp = $db->prepare("SELECT name FROM hospitals WHERE id = ?");
        $stmt_hosp->execute([$hospital_id]);
        $hospital_name = $stmt_hosp->fetch(PDO::FETCH_ASSOC)['name'] ?? 'รพ.สต.';

        $shiftModel = new ShiftModel($db);
        $notifModel = new NotificationModel($db);

        if (strtoupper((string)$shiftModel->getRosterStatus($hospital_id, $month_year)) !== 'APPROVED') {
            $_SESSION['error_msg'] = 'ขอแก้ไขได้เฉพาะตารางที่อนุมัติแล้วเท่านั้น';
            header("Location: index.php?c=roster&month=" . urlencode($month_year));
            exit;
        }

        try {
            $shiftModel->updateRosterStatus($hospital_id, $month_year, 'REQUEST_EDIT');
            
            LogsController::addLog($db, $_SESSION['user']['id'], 'UPDATE', "ส่งคำขอแก้ไขตารางเวรที่อนุมัติแล้ว เดือน {$month_year}");

            $auditModel = new RosterAuditModel($db);
            $auditModel->record(
                $hospital_id,
                $month_year,
                (int)$_SESSION['user']['id'],
                'ROSTER_EDIT_REQUEST',
                ['status' => 'APPROVED'],
                ['status' => 'REQUEST_EDIT'],
                [],
                null,
                null,
                'WORKFLOW'
            );

            $stmt = $db->query("SELECT id FROM users WHERE role IN ('ADMIN', 'SUPERADMIN')");
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            $m = (int)substr($month_year, 5, 2);
            $month_text = $thai_months[$m] . " " . (substr($month_year, 0, 4) + 543);
            $link = "index.php?c=report&a=overview&month={$month_year}";

            foreach ($admins as $admin) {
                $notifModel->addNotification($admin['id'], 'WARNING', "คำขอแก้ไขเวร: {$hospital_name}", "ขอยกเลิกสถานะอนุมัติเพื่อแก้ไขตารางเดือน {$month_text}", $link);
            }
            $_SESSION['success_msg'] = "ส่งคำขอแก้ไขตารางเวรไปยังส่วนกลางแล้ว กรุณารอการปลดล็อค";

            if ($this->getSystemSetting($db, 'line_notify_on_request') === '1') {
                $this->sendLineNotify($db, "\n🔓 มีคำขอปลดล็อคตารางเวร\nหน่วยบริการ: {$hospital_name}\nเดือน: {$month_text}\nโปรดเข้าสู่ระบบเพื่อพิจารณาอนุมัติครับ");
            }

        } catch (Exception $e) {
            error_log('AjaxController request_edit error: ' . $e->getMessage());
            $_SESSION['error_msg'] = 'ไม่สามารถส่งคำขอแก้ไขตารางเวรได้ กรุณาลองใหม่';
        }

        header("Location: index.php?c=roster&month=" . urlencode($month_year));
        exit;
    }

    // ==========================================
    // 🌟 เสนอเพิ่มวันหยุดใหม่ (Request Holiday)
    // ==========================================
    public function request_holiday() {
        $this->requireAjaxMutation();
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"));
        
        if (!isset($_SESSION['user'])) { echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }

        $db = (new Database())->getConnection();
        require_once 'models/HolidayModel.php';
        $holidayModel = new HolidayModel($db);
        $notifModel = new NotificationModel($db);

        $hospital_id = $_SESSION['user']['hospital_id'];
        if (isset($data->hosp_id) && $data->hosp_id !== '' && in_array(strtoupper($_SESSION['user']['role']), ['ADMIN', 'SUPERADMIN'])) {
            $hospital_id = $data->hosp_id;
        }
        
        if(!empty($data->date) && !empty($data->name)) {
            try {
                $result = $holidayModel->requestHoliday($data->date, $data->name, $hospital_id);
                if ($result === "SUCCESS") {
                    
                    LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "เสนอวันหยุดใหม่: {$data->name} ({$data->date})");

                    $stmt = $db->query("SELECT id FROM users WHERE role IN ('ADMIN', 'SUPERADMIN')");
                    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $thai_date = date('d/m/Y', strtotime($data->date));
                    foreach ($admins as $admin) {
                        $notifModel->addNotification($admin['id'], 'WARNING', "คำขอเพิ่มวันหยุด", "มีเสนอเพิ่มวันหยุด '{$data->name}' ในวันที่ {$thai_date}", "index.php?c=settings&a=holidays");
                    }
                    
                    if ($this->getSystemSetting($db, 'line_notify_on_holiday') === '1') {
                        $stmt_hosp = $db->prepare("SELECT name FROM hospitals WHERE id = ?");
                        $stmt_hosp->execute([$hospital_id]);
                        $hosp_name = $stmt_hosp->fetch(PDO::FETCH_ASSOC)['name'] ?? '';
                        $this->sendLineNotify($db, "\n🗓️ เสนอวันหยุดใหม่\nหน่วยบริการ: {$hosp_name}\nวันหยุด: {$data->name}\nวันที่: {$thai_date}");
                    }

                    echo json_encode(['status' => 'success']);
                } else if ($result === "EXISTS") {
                    echo json_encode(['status' => 'error', 'message' => 'วันที่นี้เป็นวันหยุดในระบบอยู่แล้ว']);
                } else if ($result === "PENDING") {
                    echo json_encode(['status' => 'error', 'message' => 'มีการเสนอวันหยุดนี้ไปแล้ว อยู่ระหว่างรออนุมัติ']);
                }
            } catch (Exception $e) { echo json_encode(['status' => 'error', 'message' => 'DB Error']); }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
        }
        exit;
    }

    // ==========================================
    // 🌟 1. ตรวจสอบความผิดปกติของตารางเวร (Advanced Validation)
    // ==========================================
    public function validate_roster() {
        error_reporting(0);
        header('Content-Type: application/json; charset=utf-8');
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = (new Database())->getConnection();
        $month_year = trim((string)($_GET['month'] ?? date('Y-m')));

        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month_year)) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'รูปแบบเดือนไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));
        $hospital_id = (int)($_SESSION['user']['hospital_id'] ?? 0);
        if (isset($_GET['hosp_id']) && $_GET['hosp_id'] !== '' && in_array($role, ['ADMIN', 'SUPERADMIN'], true)) {
            $hospital_id = (int)$_GET['hosp_id'];
        }
        if ($hospital_id <= 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'กรุณาเลือกหน่วยบริการก่อนตรวจสอบตาราง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $warnings = [];
        $errors = [];

        $normalizeCode = static function (string $code): string {
            $code = trim($code);
            $aliases = ['A' => 'บ', 'N' => 'ร', 'O' => 'ย', 'M' => 'ช'];
            return $aliases[$code] ?? $code;
        };
        $parseShift = static function ($value) use ($normalizeCode): array {
            $parts = preg_split('/[\\/,\\s]+/', trim((string)$value)) ?: [];
            $parts = array_map($normalizeCode, $parts);
            return array_values(array_filter(array_unique($parts), static fn($v) => $v !== ''));
        };
        $hasWorkShift = static function (array $types): bool {
            return count(array_intersect($types, ['ช', 'บ', 'ร', 'ย'])) > 0;
        };

        try {
            $start_date = $month_year . '-01';
            $max_days = (int)date('t', strtotime($start_date));

            $stmt_shifts = $db->prepare("
                SELECT s.shift_date, s.shift_type, s.user_id, u.name, u.type, u.employee_type
                FROM shifts s
                JOIN users u ON s.user_id = u.id
                WHERE s.hospital_id = ?
                  AND u.hospital_id = ?
                  AND s.shift_date LIKE ?
                ORDER BY s.user_id, s.shift_date ASC
            ");
            $stmt_shifts->execute([$hospital_id, $hospital_id, "$month_year-%"]);
            $shifts = $stmt_shifts->fetchAll(PDO::FETCH_ASSOC);

            if (empty($shifts)) {
                $errors[] = "ตารางเวรว่างเปล่า: ยังไม่มีการจัดเจ้าหน้าที่ลงในตารางเวร กรุณาจัดเวรก่อนส่งอนุมัติ";
                echo json_encode([
                    'status' => 'success',
                    'warnings' => $errors,
                    'errors' => $errors,
                    'advisories' => [],
                    'has_error' => true
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $user_schedules = [];
            $user_names = [];
            $shift_roster = [];
            $shift_counts = [];

            foreach ($shifts as $s) {
                $uid = (int)$s['user_id'];
                $user_schedules[$uid][$s['shift_date']] = (string)$s['shift_type'];
                $user_names[$uid] = htmlspecialchars((string)$s['name'], ENT_QUOTES, 'UTF-8');
                $shift_counts[$uid] = $shift_counts[$uid] ?? 0;

                $types = $parseShift($s['shift_type']);
                foreach ($types as $st) {
                    if (in_array($st, ['ช', 'บ', 'ร', 'ย'], true)) {
                        if (in_array($st, ['ช', 'บ', 'ร'], true)) {
                            $shift_roster[$s['shift_date']][$st][] = $s;
                        }
                        $shift_counts[$uid]++;
                    }
                }
            }

            $stmt_holidays = $db->prepare("SELECT holiday_date FROM holidays WHERE hospital_id IN (0, ?) AND holiday_date LIKE ?");
            $stmt_holidays->execute([$hospital_id, "$month_year-%"]);
            $holidays = $stmt_holidays->fetchAll(PDO::FETCH_COLUMN);

            for ($i = 1; $i <= $max_days; $i++) {
                $date = "$month_year-" . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                $is_holiday = in_array($date, $holidays, true);
                $is_weekend = (date('N', strtotime($date)) >= 6);
                $is_normal_day = (!$is_weekend && !$is_holiday);

                if ($is_normal_day && !isset($shift_roster[$date])) {
                    $errors[] = "ความครอบคลุม: วันที่ $i เป็นวันทำการปกติ แต่ยังไม่มีเจ้าหน้าที่ปฏิบัติงานเลย";
                }

                foreach (['บ', 'ร'] as $req_shift) {
                    $staff_in_shift = $shift_roster[$date][$req_shift] ?? [];
                    if (count($staff_in_shift) > 0) {
                        $has_professional = false;
                        foreach ($staff_in_shift as $staff) {
                            $type_str = ($staff['type'] ?? '') . ' ' . ($staff['employee_type'] ?? '');
                            if (mb_strpos($type_str, 'ผู้ช่วย') === false) {
                                $has_professional = true;
                                break;
                            }
                        }
                        if (!$has_professional) {
                            $errors[] = "Skill Mix: วันที่ $i กะ '<b>$req_shift</b>' มีแต่ผู้ช่วย (ขาดเจ้าหน้าที่วิชาชีพ)";
                        }
                    } else {
                        $warnings[] = "ความครอบคลุม: วันที่ $i ไม่มีเจ้าหน้าที่เข้ากะ '<b>$req_shift</b>'";
                    }
                }
            }

            foreach ($user_schedules as $uid => $dates) {
                $consecutive_nights = 0;
                $consecutive_work = 0;

                for ($i = 1; $i <= $max_days; $i++) {
                    $curr_date = "$month_year-" . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                    $curr_shift = $dates[$curr_date] ?? '';
                    $tomorrow_date = date('Y-m-d', strtotime($curr_date . ' +1 day'));
                    $tomorrow_shift = $dates[$tomorrow_date] ?? '';
                    $curr_types = $parseShift($curr_shift);
                    $tomorrow_types = $parseShift($tomorrow_shift);
                    $has_night = in_array('ร', $curr_types, true);
                    $works_today = $hasWorkShift($curr_types);
                    $works_tomorrow = $hasWorkShift($tomorrow_types);

                    if (in_array('บ', $curr_types, true) && in_array('ร', $curr_types, true)) {
                        $warnings[] = "กะควบ: <b>{$user_names[$uid]}</b> มีเวรบ่าย-ดึก (บ/ร) ในวันที่ $i ควรตรวจสอบภาระงานและเวลาพัก";
                    }

                    if ($has_night) {
                        $consecutive_nights++;
                        $consecutive_work++;

                        if ($works_tomorrow) {
                            $errors[] = "ไม่ได้พัก: <b>{$user_names[$uid]}</b> ลงดึก (ร) วันที่ $i แล้วมีเวรอีกในวันที่ " . ($i + 1) . " ($tomorrow_shift)";
                        }
                    } elseif ($works_today) {
                        $consecutive_work++;
                        $consecutive_nights = 0;
                    } else {
                        $consecutive_work = 0;
                        $consecutive_nights = 0;
                    }

                    if ($consecutive_nights > 3) {
                        $errors[] = "ขีดจำกัดความเหนื่อย: <b>{$user_names[$uid]}</b> เข้าเวรดึก (ร) ติดต่อกันเกิน 3 วัน (เจอที่วันที่ $i)";
                    }
                    if ($consecutive_work > 6) {
                        $warnings[] = "ภาระงานหนัก: <b>{$user_names[$uid]}</b> ปฏิบัติงานติดต่อกันเกิน 6 วัน (เจอที่วันที่ $i)";
                    }
                }
            }

            if (count($shift_counts) > 0) {
                $avg_shifts = array_sum($shift_counts) / count($shift_counts);
                foreach ($shift_counts as $uid => $total) {
                    if ($total > ($avg_shifts + 3)) {
                        $warnings[] = "ความยุติธรรม: <b>{$user_names[$uid]}</b> มีจำนวนเวร ($total กะ) มากกว่าค่าเฉลี่ยอย่างมีนัยสำคัญ";
                    }
                }
            }

            $errors = array_values(array_unique($errors));
            $warnings = array_values(array_unique($warnings));
            $all_issues = array_values(array_unique(array_merge($errors, $warnings)));

            echo json_encode([
                'status' => 'success',
                'warnings' => $all_issues,
                'errors' => $errors,
                'advisories' => $warnings,
                'has_error' => count($errors) > 0,
                'summary' => [
                    'errors' => count($errors),
                    'warnings' => count($warnings),
                    'total' => count($all_issues)
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('Ajax operation failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถดำเนินการได้ กรุณาลองใหม่'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // ==========================================
    // 🌟 สุ่มจัดเวรอัตโนมัติ (Auto-Schedule) 
    // ==========================================
    public function auto_schedule() {
        $this->requireAjaxMutation();
        error_reporting(0); // 🌟 ปิด Warning
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user'])) { 
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; 
        }

        $data = json_decode(file_get_contents("php://input"));
        $month_year = $data->month_year ?? '';
        
        $hospital_id = $_SESSION['user']['hospital_id'];
        if (isset($data->hosp_id) && $data->hosp_id !== '' && in_array(strtoupper($_SESSION['user']['role']), ['ADMIN', 'SUPERADMIN'])) {
            $hospital_id = $data->hosp_id;
        }

        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', (string)$month_year)) {
            echo json_encode(['status' => 'error', 'message' => 'รูปแบบเดือนไม่ถูกต้อง']);
            exit;
        }
        if (!$this->canEditRoster($hospital_id, $month_year)) {
            echo json_encode(['status' => 'error', 'message' => 'ตารางเดือนนี้ถูกล็อคแล้ว ไม่สามารถจัดเวรอัตโนมัติได้']); exit;
        }

        $db = (new Database())->getConnection();

        try {
            $db->beginTransaction();

            $snapshotModel = new RosterSnapshotModel($db);
            $snapshotId = $snapshotModel->createSnapshot(
                (int)$hospital_id,
                (string)$month_year,
                (int)$_SESSION['user']['id'],
                'BEFORE_AUTO_SCHEDULE',
                'สำรองอัตโนมัติก่อนจัดเวรด้วย Rule Engine'
            );

            $start_date = $month_year . '-01';
            $max_days = (int)date('t', strtotime($start_date));

            $stmt_users = $db->prepare("
                SELECT id, type, employee_type
                FROM users
                WHERE hospital_id = ?
                  AND role NOT IN ('SUPERADMIN', 'ADMIN')
                  AND is_deleted = 0
                  AND is_active = 1
                  AND (show_in_roster = 1 OR show_in_roster IS NULL)
                ORDER BY display_order ASC, name ASC
            ");
            $stmt_users->execute([$hospital_id]);
            $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

            if (empty($users)) throw new Exception("ไม่มีรายชื่อเจ้าหน้าที่ในหน่วยงานนี้");

            $officers = [];
            $assistants = [];
            foreach ($users as $u) {
                $is_assistant = (mb_strpos(($u['type'] ?? '') . ' ' . ($u['employee_type'] ?? ''), 'ผู้ช่วย') !== false);
                if ($is_assistant) $assistants[] = $u['id'];
                else $officers[] = $u['id'];
            }

            $schedule = []; 
            $counts = []; 
            foreach($users as $u) $counts[$u['id']] = ['บ'=>0, 'ร'=>0, 'worked_weekends'=>0];

            $stmt_exist = $db->prepare("
                SELECT s.user_id, s.shift_date, s.shift_type
                FROM shifts s
                JOIN users u ON u.id = s.user_id
                WHERE s.hospital_id = ? AND u.hospital_id = ? AND s.shift_date LIKE ?
            ");
            $stmt_exist->execute([$hospital_id, $hospital_id, "$month_year-%"]);
            $existingShifts = $stmt_exist->fetchAll(PDO::FETCH_ASSOC);
            $beforeAutoCount = count($existingShifts);
            foreach ($existingShifts as $es) {
                $schedule[$es['shift_date']][$es['user_id']] = $es['shift_type'];
                if (isset($counts[$es['user_id']])) {
                    if (strpos($es['shift_type'], 'บ') !== false) $counts[$es['user_id']]['บ']++;
                    if (strpos($es['shift_type'], 'ร') !== false) $counts[$es['user_id']]['ร']++;
                    if (date('N', strtotime($es['shift_date'])) >= 6) $counts[$es['user_id']]['worked_weekends']++;
                }
            }

            $stmt_insert = $db->prepare("INSERT INTO shifts (user_id, hospital_id, shift_date, shift_type) VALUES (?, ?, ?, ?)"); 
            $added_count = 0;

            for ($i = 1; $i <= $max_days; $i++) {
                $date = "$month_year-" . str_pad($i, 2, '0', STR_PAD_LEFT);
                $is_weekend = (date('N', strtotime($date)) >= 6);

                foreach (['บ', 'ร'] as $shift_val) {
                    foreach (['officer', 'assistant'] as $role_type) {
                        $candidates = ($role_type == 'officer') ? $officers : $assistants;
                        $valid_candidates = [];

                        foreach ($candidates as $uid) {
                            if (isset($schedule[$date][$uid])) continue;

                            $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
                            if (isset($schedule[$yesterday][$uid]) && strpos($schedule[$yesterday][$uid], 'ร') !== false) continue; 
                            
                            if ($shift_val === 'ร') {
                                $tomorrow = date('Y-m-d', strtotime($date . ' +1 day'));
                                if (isset($schedule[$tomorrow][$uid])) continue; 
                            }

                            $consecutive = 0;
                            for ($b = 1; $b <= 4; $b++) {
                                $back_date = date('Y-m-d', strtotime($date . " -$b day"));
                                if (isset($schedule[$back_date][$uid]) && !in_array($schedule[$back_date][$uid], ['ย', 'OFF'])) {
                                    $consecutive++;
                                } else break; 
                            }
                            if ($consecutive >= 4) continue; 

                            $valid_candidates[] = $uid;
                        }

                        if (!empty($valid_candidates)) {
                            usort($valid_candidates, function($a, $b) use ($counts, $shift_val, $is_weekend) {
                                if ($counts[$a][$shift_val] != $counts[$b][$shift_val]) {
                                    return $counts[$a][$shift_val] <=> $counts[$b][$shift_val]; 
                                }
                                if ($is_weekend) {
                                    return $counts[$a]['worked_weekends'] <=> $counts[$b]['worked_weekends']; 
                                }
                                return 0;
                            });

                            $chosen = $valid_candidates[0];
                            $schedule[$date][$chosen] = $shift_val;
                            $counts[$chosen][$shift_val]++;
                            if ($is_weekend) $counts[$chosen]['worked_weekends']++;

                            $stmt_insert->execute([$chosen, $hospital_id, $date, $shift_val]);
                            $added_count++;
                        }
                    }
                }
            }

            LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "ใช้งานระบบจัดการเวรอัตโนมัติ เดือน $month_year (จัดเพิ่ม $added_count กะ)");

            $auditModel = new RosterAuditModel($db);
            $auditModel->record(
                (int)$hospital_id,
                (string)$month_year,
                (int)$_SESSION['user']['id'],
                'ROSTER_AUTO_SCHEDULE',
                ['shift_count' => $beforeAutoCount],
                ['shift_count' => $beforeAutoCount + $added_count],
                [
                    'added_count' => $added_count,
                    'snapshot_id' => $snapshotId,
                    'engine' => 'RULE_ENGINE',
                ],
                null,
                null,
                'ROSTER'
            );

            $db->commit();
            echo json_encode(['status' => 'success', 'message' => "ดำเนินการจัดเวรตามกฎสำเร็จ (เพิ่ม $added_count กะ)", 'added' => $added_count]);

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Ajax operation failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถดำเนินการได้ กรุณาลองใหม่']);
        }
        exit;
    }

    // ==========================================
    // 🔔 API แจ้งเตือนกระดิ่ง (Notifications)
    // ==========================================
    private function notifyRole($hosp_id, $role, $type, $title, $msg, $link = null) {
        $db = (new Database())->getConnection();
        $notif = new NotificationModel($db);
        $stmt = $db->prepare("SELECT id FROM users WHERE hospital_id = ? AND role = ?");
        $stmt->execute([$hosp_id, $role]);
        while($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notif->addNotification($u['id'], $type, $title, $msg, $link);
        }
    }

    public function check_new_notif() {
        error_reporting(0);
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }

        $userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'];
        $db = (new Database())->getConnection();
        $notificationModel = new NotificationModel($db);

        echo json_encode([
            'status' => 'success',
            'unread_count' => $notificationModel->getUnreadCount($userId)
        ]);
        exit;
    }

    public function getUnreadNotifications() {
        error_reporting(0); header('Content-Type: application/json');
        if(!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']); return;
        }
        $userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'];
        $db = (new Database())->getConnection();
        $notificationModel = new NotificationModel($db);
        $notifications = $notificationModel->getUserNotifications($userId, 5); 
        $count = $notificationModel->getUnreadCount($userId);
        echo json_encode(['status' => 'success', 'count' => $count, 'data' => $notifications]); exit;
    }

    public function read_notif() {
        $this->requireAjaxMutation();
        $payload = json_decode((string)file_get_contents('php://input'), true);
        $id = (int)($payload['id'] ?? $_POST['id'] ?? 0);
        $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);

        if ($id <= 0 || $userId <= 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลการแจ้งเตือนไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = (new Database())->getConnection();
        $notificationModel = new NotificationModel($db);
        $result = $notificationModel->markAsRead($id, $userId);
        echo json_encode(['status' => $result ? 'success' : 'error'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function read_all_notif() {
        $this->requireAjaxMutation();
        $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
        $db = (new Database())->getConnection();
        $notificationModel = new NotificationModel($db);
        $result = $notificationModel->markAllAsRead($userId);
        echo json_encode(['status' => $result ? 'success' : 'error'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function delete_notif() {
        $this->requireAjaxMutation();
        $payload = json_decode((string)file_get_contents('php://input'), true);
        $id = (int)($payload['id'] ?? $_POST['id'] ?? 0);
        $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);

        if ($id <= 0 || $userId <= 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลการแจ้งเตือนไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = (new Database())->getConnection();
        $notificationModel = new NotificationModel($db);
        $result = $notificationModel->deleteNotification($id, $userId);
        echo json_encode(['status' => $result ? 'success' : 'error'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function delete_all_notif() {
        $this->requireAjaxMutation();
        $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
        $db = (new Database())->getConnection();
        $notificationModel = new NotificationModel($db);
        $result = $notificationModel->deleteAllForUser($userId);
        echo json_encode(['status' => $result ? 'success' : 'error'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function markNotificationAsRead() {
        $this->requireAjaxMutation();
        error_reporting(0); header('Content-Type: application/json');
        if(!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) { echo json_encode(['status' => 'error']); return; }
        if (!isset($_POST['noti_id'])) { echo json_encode(['status' => 'error', 'message' => 'Missing ID']); return; }
        $userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'];
        $notiId = $_POST['noti_id'];
        $db = (new Database())->getConnection();
        $notificationModel = new NotificationModel($db);
        $result = $notificationModel->markAsRead($notiId, $userId);
        echo json_encode(['status' => $result ? 'success' : 'error']); exit;
    }
}
?>