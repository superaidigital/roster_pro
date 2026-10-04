<?php
// ที่อยู่ไฟล์: controllers/RosterController.php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'controllers/LogsController.php';
require_once 'models/RosterSnapshotModel.php';
require_once 'models/RosterAuditModel.php';

class RosterController {

    private function requireMutation(): void {
        security_start_session();

        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }

        if (!security_is_valid_post_csrf()) {
            http_response_code(403);
            $_SESSION['error_msg'] = "คำขอไม่ถูกต้องหรือหมดอายุ กรุณาลองใหม่";
            header("Location: index.php?c=roster");
            exit;
        }
    }

    
    // ====================================================
    // 🛡️ ตรวจสอบสิทธิ์การเข้าใช้งาน
    // ====================================================
    private function checkAuth() {
        security_start_session();
        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }
    }

    // ====================================================
    // 🌟 1. โหลดหน้าจอกระดานตารางเวรหลัก (Roster Board)
    // ====================================================
    private function resolveTargetHospitalId(?int $requestedHospitalId = null): int {
        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));
        if (in_array($role, ['ADMIN', 'SUPERADMIN'], true) && ($requestedHospitalId ?? 0) > 0) {
            return (int)$requestedHospitalId;
        }
        return (int)($_SESSION['user']['hospital_id'] ?? 0);
    }

    private function canManageRosterVersions(): bool {
        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));
        return in_array($role, ['SCHEDULER', 'DIRECTOR', 'ADMIN', 'SUPERADMIN'], true);
    }

    public function index() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        
        $current_user = $_SESSION['user'];
        $isAdmin = in_array($current_user['role'], ['ADMIN', 'SUPERADMIN']);
        
        // กำหนด Hospital ID (ถ้าเป็นแอดมิน ให้ดึงจาก Dropdown ค้นหา)
        $hospital_id = $current_user['hospital_id'];
        if ($isAdmin && isset($_GET['hospital_id']) && !empty($_GET['hospital_id'])) {
            $hospital_id = $_GET['hospital_id'];
        }
        
        $selected_month = trim((string)($_GET['month'] ?? date('Y-m')));
        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $selected_month)) {
            $selected_month = date('Y-m');
            $_SESSION['error_msg'] = 'รูปแบบเดือนไม่ถูกต้อง ระบบจึงแสดงเดือนปัจจุบันแทน';
        }

        // 🏥 1. ดึงรายชื่อหน่วยบริการทั้งหมด
        $stmt_hosp = $db->query("SELECT id, name FROM hospitals WHERE is_active = 1 AND name NOT LIKE '%ส่วนกลาง%' ORDER BY id ASC");
        $hospitals_list = $stmt_hosp->fetchAll(PDO::FETCH_ASSOC);

        // ดึงชื่อหน่วยบริการปัจจุบัน
        $hospital_name = 'ส่วนกลาง (ศูนย์ควบคุม)';
        foreach ($hospitals_list as $h) {
            if ($h['id'] == $hospital_id) {
                $hospital_name = $h['name'];
                break;
            }
        }

        // 👥 2. แสดงเฉพาะบุคลากรที่สังกัดหน่วยบริการที่กำลังเปิดดูเท่านั้น
        $hosp_id_safe = !empty($hospital_id) ? (int)$hospital_id : 0;
        $query_all_staff = "
            SELECT u.*, p.rate_r, p.rate_y, p.rate_b, p.name as pay_rate_name
            FROM users u
            LEFT JOIN pay_rates p ON u.pay_rate_id = p.id
            WHERE u.hospital_id = :hosp_id
              AND u.role NOT IN ('SUPERADMIN', 'ADMIN')
              AND u.is_deleted = 0
              AND u.is_active = 1
              AND (u.show_in_roster = 1 OR u.show_in_roster IS NULL)
            ORDER BY u.display_order ASC, u.name ASC
        ";
        $stmt_all = $db->prepare($query_all_staff);
        $stmt_all->execute([':hosp_id' => $hosp_id_safe]);
        $all_staff_for_sidebar = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

        // 📅 3. ดึงข้อมูลกะปฏิบัติงาน (Shifts) ของเดือนนี้
        $stmt_shifts = $db->prepare("
            SELECT s.*
            FROM shifts s
            JOIN users u ON u.id = s.user_id
            WHERE s.hospital_id = ?
              AND u.hospital_id = ?
              AND s.shift_date LIKE ?
        ");
        $stmt_shifts->execute([$hospital_id, $hospital_id, $selected_month . '-%']);
        $shifts = $stmt_shifts->fetchAll(PDO::FETCH_ASSOC);

        // 📝 4. ดึงข้อมูลวันลา (Leaves)
        $stmt_leaves = $db->prepare("
            SELECT lr.*, lq.leave_type 
            FROM leave_requests lr
            JOIN leave_quotas lq ON lr.leave_type_id = lq.id
            JOIN users u ON lr.user_id = u.id
            WHERE u.hospital_id = ?
            AND lr.status IN ('APPROVED', 'PENDING', 'CANCEL_REQUESTED')
            AND (DATE_FORMAT(lr.start_date, '%Y-%m') = ? OR DATE_FORMAT(lr.end_date, '%Y-%m') = ?)
        ");
        $stmt_leaves->execute([$hospital_id, $selected_month, $selected_month]);
        $leaves = $stmt_leaves->fetchAll(PDO::FETCH_ASSOC);

        // 🚦 5. ดึงสถานะตารางเวร
        $stmt_status = $db->prepare("SELECT * FROM roster_status WHERE hospital_id = ? AND month_year = ?");
        $stmt_status->execute([$hospital_id, $selected_month]);
        $roster_status_data = $stmt_status->fetch(PDO::FETCH_ASSOC);
        $roster_status = $roster_status_data ? $roster_status_data['status'] : 'DRAFT';
        
        $pay_snapshot = [];
        if ($roster_status === 'APPROVED' && !empty($roster_status_data['pay_summary'])) {
            $pay_snapshot = json_decode($roster_status_data['pay_summary'], true);
        }

        // 💸 6. ดึงฐานข้อมูลเรทค่าตอบแทน
        $stmt_rates = $db->query("SELECT * FROM pay_rates");
        $pay_rates_db = $stmt_rates->fetchAll(PDO::FETCH_ASSOC);

        // 🏖️ 7. ดึงวันหยุดนักขัตฤกษ์
        require_once 'models/HolidayModel.php';
        $holidayModel = new HolidayModel($db);

        $roster_snapshots = [];
        if ($hosp_id_safe > 0) {
            try {
                $snapshotModel = new RosterSnapshotModel($db);
                $roster_snapshots = $snapshotModel->listSnapshots($hosp_id_safe, $selected_month, 12);
            } catch (Throwable $e) {
                error_log('Roster snapshot list failed: ' . $e->getMessage());
            }
        }

        $roster_audit_events = [];
        if ($hosp_id_safe > 0 && $this->canManageRosterVersions()) {
            try {
                $auditModel = new RosterAuditModel($db);
                $roster_audit_events = $auditModel->listEvents($hosp_id_safe, $selected_month, 50);
            } catch (Throwable $e) {
                error_log('Roster audit list failed: ' . $e->getMessage());
            }
        }

        // โหลด View หน้ากระดานจัดเวร
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/roster/index.php';
        echo "</main></div></body></html>";
    }

    // ====================================================
    // 🖨️ 2. ฟังก์ชันส่งออกตารางเวรเป็นไฟล์ Microsoft Word (.doc)
    // ====================================================
    public function export_word() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        
        $hospital_id = $_SESSION['user']['hospital_id'];
        if (in_array($_SESSION['user']['role'], ['ADMIN', 'SUPERADMIN']) && isset($_GET['hospital_id'])) {
            $hospital_id = $_GET['hospital_id'];
        }
        $selected_month = trim((string)($_GET['month'] ?? date('Y-m')));
        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $selected_month)) {
            $selected_month = date('Y-m');
        }
        
        // ดึงข้อมูลวันที่ไทยและจำนวนวันในเดือน
        $exp = explode('-', $selected_month);
        $thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        $month_text = $thai_months[(int)$exp[1]];
        $thai_year = $exp[0] + 543;
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $exp[1], $exp[0]);

        // ดึงข้อมูลหน่วยบริการและชื่อผู้อำนวยการ
        $stmt_hosp = $db->prepare("
            SELECT h.*, u.name as director_name, u.type as director_position 
            FROM hospitals h 
            LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'DIRECTOR' AND u.is_deleted = 0
            WHERE h.id = ? LIMIT 1
        ");
        $stmt_hosp->execute([$hospital_id]);
        $hospital_info = $stmt_hosp->fetch(PDO::FETCH_ASSOC);
        $hospital_name = $hospital_info ? $hospital_info['name'] : 'หน่วยบริการ';

        // 🌟 Export เฉพาะบุคลากรที่สังกัดหน่วยบริการนี้
        $stmt_staff = $db->prepare("
            SELECT u.*
            FROM users u
            WHERE u.hospital_id = ?
              AND u.role NOT IN ('SUPERADMIN', 'ADMIN')
              AND u.is_deleted = 0
              AND u.is_active = 1
              AND (u.show_in_roster = 1 OR u.show_in_roster IS NULL)
            ORDER BY u.display_order ASC, u.id ASC
        ");
        $month_like = $selected_month . '-%';
        $stmt_staff->execute([$hospital_id]);
        $staffs = $stmt_staff->fetchAll(PDO::FETCH_ASSOC);

        // ดึงเวร (Shifts)
        $stmt_shifts = $db->prepare("
            SELECT s.*
            FROM shifts s
            JOIN users u ON u.id = s.user_id
            WHERE s.hospital_id = ? AND u.hospital_id = ? AND s.shift_date LIKE ?
        ");
        $stmt_shifts->execute([$hospital_id, $hospital_id, $month_like]);
        $shifts = $stmt_shifts->fetchAll(PDO::FETCH_ASSOC);

        // ดึงวันลา (Leaves) เพื่อขีด ล. ในตาราง
        $stmt_leaves = $db->prepare("
            SELECT lr.*, lq.leave_type 
            FROM leave_requests lr
            JOIN leave_quotas lq ON lr.leave_type_id = lq.id
            JOIN users u ON lr.user_id = u.id
            WHERE u.hospital_id = ?
            AND lr.status IN ('APPROVED', 'PENDING', 'CANCEL_REQUESTED')
            AND (DATE_FORMAT(lr.start_date, '%Y-%m') = ? OR DATE_FORMAT(lr.end_date, '%Y-%m') = ?)
        ");
        $stmt_leaves->execute([$hospital_id, $selected_month, $selected_month]);
        $leaves = $stmt_leaves->fetchAll(PDO::FETCH_ASSOC);

        // ดึงเรทค่าตอบแทน
        $stmt_rates = $db->query("SELECT * FROM pay_rates");
        $pay_rates_db = $stmt_rates->fetchAll(PDO::FETCH_ASSOC);

        // บันทึก Log การดาวน์โหลด
        LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_EXPORT, "ดาวน์โหลดตารางเวรรูปแบบ Word ของ {$hospital_name} เดือน {$selected_month}");

        // เรียกไฟล์ View สำหรับ Export
        require_once 'views/roster/export_word.php';
    }

    // ====================================================
    // 🗑️ 3. ฟังก์ชันล้างตารางเวรทั้งหมดของเดือนนั้น
    // ====================================================
    public function clear_roster() {
        $this->requireMutation();
        $this->checkAuth();
        $db = (new Database())->getConnection();
        
        $hospital_id = $this->resolveTargetHospitalId(isset($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : null);
        $month = trim((string)($_POST['month'] ?? ''));
        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));

        if (!in_array($role, ['SCHEDULER', 'DIRECTOR', 'ADMIN', 'SUPERADMIN'], true)) {
            $_SESSION['error_msg'] = "คุณไม่มีสิทธิ์ล้างตารางเวร";
            header("Location: index.php?c=roster");
            exit;
        }

        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month) || $hospital_id <= 0) {
            $_SESSION['error_msg'] = "ข้อมูลเดือนหรือหน่วยบริการไม่ถูกต้อง";
            header("Location: index.php?c=roster");
            exit;
        }

        $stmt_status = $db->prepare("SELECT status FROM roster_status WHERE hospital_id = ? AND month_year = ? LIMIT 1");
        $stmt_status->execute([$hospital_id, $month]);
        $status = strtoupper((string)($stmt_status->fetchColumn() ?: 'DRAFT'));

        if ($status !== 'DRAFT') {
            $_SESSION['error_msg'] = "ตารางเวรอยู่ในสถานะล็อก ไม่สามารถล้างข้อมูลได้";
            header("Location: index.php?c=roster&month=" . urlencode($month));
            exit;
        }

        $month_like = $month . '-%';
        
        try {
            $db->beginTransaction();

            $beforeCountStmt = $db->prepare("SELECT COUNT(*) FROM shifts WHERE hospital_id = ? AND shift_date LIKE ?");
            $beforeCountStmt->execute([$hospital_id, $month_like]);
            $beforeShiftCount = (int)$beforeCountStmt->fetchColumn();

            $snapshotModel = new RosterSnapshotModel($db);
            $snapshotId = $snapshotModel->createSnapshot(
                $hospital_id,
                $month,
                (int)$_SESSION['user']['id'],
                'BEFORE_CLEAR',
                'สำรองอัตโนมัติก่อนล้างตาราง'
            );

            $stmt = $db->prepare("DELETE FROM shifts WHERE hospital_id = ? AND shift_date LIKE ?");
            $stmt->execute([$hospital_id, $month_like]);
            
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_DELETE, "ล้างข้อมูลตารางเวรทั้งหมดของเดือน {$month}");

            $auditModel = new RosterAuditModel($db);
            $auditModel->record(
                $hospital_id,
                $month,
                (int)$_SESSION['user']['id'],
                'ROSTER_CLEAR',
                ['shift_count' => $beforeShiftCount],
                ['shift_count' => 0],
                [
                    'snapshot_id' => $snapshotId,
                    'deleted_count' => $stmt->rowCount(),
                ],
                null,
                null,
                'ROSTER'
            );

            $db->commit();
            
            $_SESSION['success_msg'] = "ล้างข้อมูลตารางเวรของเดือน {$month} เรียบร้อยแล้ว เริ่มจัดใหม่ได้ทันที";
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('RosterController error: ' . $e->getMessage());
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ";
        }
        
        header("Location: index.php?c=roster&month={$month}&hospital_id={$hospital_id}");
        exit;
    }

    // ====================================================
    // 🎲 4. ฟังก์ชันสุ่มจัดเวรอัตโนมัติ (Automated Randomize)
    // ====================================================
    public function randomize_roster() {
        $this->requireMutation();
        $this->checkAuth();
        $db = (new Database())->getConnection();
        
        $hospital_id = $this->resolveTargetHospitalId(isset($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : null);
        $month = trim((string)($_POST['month'] ?? ''));
        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));

        if (!in_array($role, ['SCHEDULER', 'DIRECTOR', 'ADMIN', 'SUPERADMIN'], true)) {
            $_SESSION['error_msg'] = "คุณไม่มีสิทธิ์จัดตารางเวร";
            header("Location: index.php?c=roster");
            exit;
        }

        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month) || $hospital_id <= 0) {
            $_SESSION['error_msg'] = "ข้อมูลเดือนหรือหน่วยบริการไม่ถูกต้อง";
            header("Location: index.php?c=roster");
            exit;
        }

        $stmt_status = $db->prepare("SELECT status FROM roster_status WHERE hospital_id = ? AND month_year = ? LIMIT 1");
        $stmt_status->execute([$hospital_id, $month]);
        $status = strtoupper((string)($stmt_status->fetchColumn() ?: 'DRAFT'));
        if ($status !== 'DRAFT') {
            $_SESSION['error_msg'] = "ตารางเวรอยู่ในสถานะล็อก ไม่สามารถสุ่มจัดเวรได้";
            header("Location: index.php?c=roster&month=" . urlencode($month));
            exit;
        }

        $month_like = $month . '-%';
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, (int)substr($month, 5, 2), (int)substr($month, 0, 4));
        
        try {
            $db->beginTransaction();

            $snapshotModel = new RosterSnapshotModel($db);
            $snapshotModel->createSnapshot(
                $hospital_id,
                $month,
                (int)$_SESSION['user']['id'],
                'BEFORE_RANDOMIZE',
                'สำรองอัตโนมัติก่อนสุ่มตารางใหม่'
            );

            $stmt_del = $db->prepare("DELETE FROM shifts WHERE hospital_id = ? AND shift_date LIKE ?");
            $stmt_del->execute([$hospital_id, $month_like]);
            
            // 🌟 เพิ่มเงื่อนไข show_in_roster = 1 (ไม่สุ่มเวรให้คนที่โดนซ่อนชื่อ)
            $stmt_staff = $db->prepare("
                SELECT id FROM users 
                WHERE hospital_id = ? 
                AND role NOT IN ('SUPERADMIN', 'ADMIN', 'DIRECTOR') 
                AND is_deleted = 0 
                AND is_active = 1
                AND (show_in_roster = 1 OR show_in_roster IS NULL)
            ");
            $stmt_staff->execute([$hospital_id]);
            $staffs = $stmt_staff->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($staffs)) {
                $shift_types = ['บ', 'ร', 'ย'];
                $insert_stmt = $db->prepare("INSERT INTO shifts (hospital_id, user_id, shift_date, shift_type) VALUES (?, ?, ?, ?)");
                
                foreach ($staffs as $uid) {
                    for ($d = 1; $d <= $days_in_month; $d++) {
                        if (rand(1, 100) <= 25) {
                            $date_str = $month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                            $type = $shift_types[array_rand($shift_types)];
                            $insert_stmt->execute([$hospital_id, $uid, $date_str, $type]);
                        }
                    }
                }
            }

            $db->commit();
            
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_CREATE, "ใช้ระบบสุ่มจัดเวรอัตโนมัติ (Randomize) เดือน {$month}");
            
            $_SESSION['success_msg'] = "สุ่มตารางเวรเดือน {$month} สำเร็จแล้ว! โปรดตรวจสอบและปรับแก้ (ย้าย/ลบ) เพื่อความเหมาะสมอีกครั้ง";

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('RosterController error: ' . $e->getMessage());
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ";
        }
        
        header("Location: index.php?c=roster&month={$month}&hospital_id={$hospital_id}");
        exit;
    }

    // ====================================================
    // 🔄 5. ฟังก์ชันอัปเดตลำดับบุคลากรในตารางเวร (Drag & Drop)
    // ====================================================
    public function create_snapshot() {
        $this->requireMutation();
        $this->checkAuth();

        if (!$this->canManageRosterVersions()) {
            http_response_code(403);
            $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์สร้างเวอร์ชันตารางเวร';
            header("Location: index.php?c=roster");
            exit;
        }

        $month = trim((string)($_POST['month'] ?? ''));
        $hospitalId = $this->resolveTargetHospitalId(isset($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : null);
        $label = trim((string)($_POST['label'] ?? 'บันทึกเวอร์ชันด้วยตนเอง'));

        if ($hospitalId <= 0 || !preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $_SESSION['error_msg'] = 'ข้อมูลเดือนหรือหน่วยบริการไม่ถูกต้อง';
            header("Location: index.php?c=roster");
            exit;
        }

        try {
            $db = (new Database())->getConnection();
            $snapshotModel = new RosterSnapshotModel($db);
            $id = $snapshotModel->createSnapshot(
                $hospitalId, $month, (int)$_SESSION['user']['id'], 'MANUAL', $label
            );
            LogsController::addLog(
                $db, $_SESSION['user']['id'], LogsController::ACTION_CREATE,
                "สร้าง Snapshot ตารางเวรเดือน {$month} (Version #{$id})"
            );
            $_SESSION['success_msg'] = "บันทึกเวอร์ชันตารางเวรเรียบร้อยแล้ว (#{$id})";
        } catch (Throwable $e) {
            error_log('Roster snapshot create failed: ' . $e->getMessage());
            $_SESSION['error_msg'] = 'ไม่สามารถบันทึกเวอร์ชันตารางเวรได้';
        }

        header("Location: index.php?c=roster&month=" . urlencode($month) . "&hospital_id=" . $hospitalId);
        exit;
    }

    public function restore_snapshot() {
        $this->requireMutation();
        $this->checkAuth();

        if (!$this->canManageRosterVersions()) {
            http_response_code(403);
            $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์ย้อนเวอร์ชันตารางเวร';
            header("Location: index.php?c=roster");
            exit;
        }

        $snapshotId = (int)($_POST['snapshot_id'] ?? 0);
        $month = trim((string)($_POST['month'] ?? ''));
        $hospitalId = $this->resolveTargetHospitalId(isset($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : null);

        if ($snapshotId <= 0 || $hospitalId <= 0 || !preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $_SESSION['error_msg'] = 'ข้อมูลเวอร์ชันที่ต้องการย้อนกลับไม่ถูกต้อง';
            header("Location: index.php?c=roster");
            exit;
        }

        $db = (new Database())->getConnection();
        $statusStmt = $db->prepare("SELECT status FROM roster_status WHERE hospital_id = ? AND month_year = ? LIMIT 1");
        $statusStmt->execute([$hospitalId, $month]);
        $currentStatus = strtoupper((string)($statusStmt->fetchColumn() ?: 'DRAFT'));
        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));

        if ($currentStatus !== 'DRAFT' && !in_array($role, ['ADMIN', 'SUPERADMIN'], true)) {
            $_SESSION['error_msg'] = 'ตารางที่ส่งตรวจหรืออนุมัติแล้ว ต้องให้ผู้ดูแลระบบปลดสถานะก่อนย้อนเวอร์ชัน';
            header("Location: index.php?c=roster&month=" . urlencode($month));
            exit;
        }

        try {
            $snapshotModel = new RosterSnapshotModel($db);
            $result = $snapshotModel->restoreSnapshot(
                $snapshotId, $hospitalId, $month, (int)$_SESSION['user']['id']
            );
            LogsController::addLog(
                $db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE,
                "ย้อนตารางเวรเดือน {$month} กลับ Version #{$snapshotId} จำนวน {$result['restored_shift_count']} รายการ"
            );
            $_SESSION['success_msg'] = 'ย้อนเวอร์ชันสำเร็จ และเปลี่ยนสถานะกลับเป็น DRAFT เพื่อให้ตรวจสอบอีกครั้ง';
        } catch (Throwable $e) {
            error_log('Roster snapshot restore failed: ' . $e->getMessage());
            $_SESSION['error_msg'] = 'ไม่สามารถย้อนเวอร์ชันได้: ข้อมูลบุคลากรหรือ Snapshot อาจไม่สอดคล้องกับระบบปัจจุบัน';
        }

        header("Location: index.php?c=roster&month=" . urlencode($month) . "&hospital_id=" . $hospitalId);
        exit;
    }

    public function update_order() {
        $this->requireMutation();
        $this->checkAuth();
        header('Content-Type: application/json');

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (isset($data['order']) && is_array($data['order'])) {
            $db = (new Database())->getConnection();
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("UPDATE users SET display_order = ? WHERE id = ?");
                foreach ($data['order'] as $item) {
                    $stmt->execute([$item['order'], $item['id']]);
                }
                
                $count = count($data['order']);
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "ปรับปรุงลำดับการแสดงผลบุคลากรในตารางเวร จำนวน {$count} รายการ");
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'อัปเดตลำดับสำเร็จ']);
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log('Roster update_order failed: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Server error']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
        }
        exit;
    }

    // ====================================================
    // 🌟 6. บันทึกข้อมูลผู้ลงนามในตารางเวร (E-Signature Setup)
    // ====================================================
    public function save_signatures() {
        $this->requireMutation();
        $this->checkAuth();
        header('Content-Type: application/json');

        $db = (new Database())->getConnection();
        $hospital_id = isset($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : 0;
        $month_year = !empty($_POST['month']) ? trim($_POST['month']) : date('Y-m');
        $creator_id = !empty($_POST['sig_creator_id']) ? (int)$_POST['sig_creator_id'] : null;
        $reviewer_id = !empty($_POST['sig_reviewer_id']) ? (int)$_POST['sig_reviewer_id'] : null;
        $director_id = !empty($_POST['sig_director_id']) ? (int)$_POST['sig_director_id'] : null;

        if ($hospital_id <= 0 || !preg_match('/^\d{4}-\d{2}$/', $month_year)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'ข้อมูลเดือนหรือหน่วยบริการไม่ถูกต้อง']);
            exit;
        }

        try {
            $stmtCheck = $db->prepare(
                "SELECT id FROM roster_status WHERE hospital_id = ? AND month_year = ?"
            );
            $stmtCheck->execute([$hospital_id, $month_year]);
            $exists = $stmtCheck->fetchColumn();

            if ($exists) {
                $stmt = $db->prepare(
                    "UPDATE roster_status
                     SET creator_id = ?, reviewer_id = ?, director_id = ?
                     WHERE id = ?"
                );
                $stmt->execute([$creator_id, $reviewer_id, $director_id, $exists]);
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO roster_status
                        (hospital_id, month_year, status, creator_id, reviewer_id, director_id)
                     VALUES (?, ?, 'DRAFT', ?, ?, ?)"
                );
                $stmt->execute([$hospital_id, $month_year, $creator_id, $reviewer_id, $director_id]);
            }

            LogsController::addLog(
                $db,
                $_SESSION['user']['id'],
                LogsController::ACTION_UPDATE,
                "ตั้งค่าผู้ลงนามในตารางเวรเดือน {$month_year}"
            );

            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            error_log('Roster save_signatures failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'ไม่สามารถบันทึกผู้ลงนามได้ กรุณาตรวจสอบฐานข้อมูลและลองใหม่'
            ]);
        }
        exit;
    }

    public function get_signatures() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        $hospital_id = $_GET['hospital_id'] ?? 0;
        $month_year = $_GET['month'] ?? date('Y-m');
        
        $db = (new Database())->getConnection();
        try {
            $stmt = $db->prepare("SELECT creator_id, reviewer_id, director_id FROM roster_status WHERE hospital_id = ? AND month_year = ?");
            $stmt->execute([$hospital_id, $month_year]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data) {
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                echo json_encode(['success' => true, 'data' => ['creator_id' => '', 'reviewer_id' => '', 'director_id' => '']]);
            }
        } catch (Exception $e) {
            // กรณีคอลัมน์ยังไม่ถูกสร้าง
            echo json_encode(['success' => true, 'data' => ['creator_id' => '', 'reviewer_id' => '', 'director_id' => '']]);
        }
        exit;
    }
}
?>