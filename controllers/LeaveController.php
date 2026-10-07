<?php
// ที่อยู่ไฟล์: controllers/LeaveController.php

require_once 'config/database.php';
require_once 'models/LeaveModel.php';
require_once 'models/LeaveTemplateModel.php';
require_once 'services/LeaveDocumentService.php';
require_once 'models/UserModel.php';
require_once 'models/HolidayModel.php';
require_once 'models/NotificationModel.php';
require_once 'services/NotificationService.php';
require_once 'controllers/LogsController.php'; // 🌟 ระบบ Log

class LeaveController {

    const LEAVE_MANAGER_ROLES = ['SUPERADMIN', 'ADMIN', 'DIRECTOR', 'SCHEDULER'];
    const LEAVE_ADMIN_ROLES = ['SUPERADMIN', 'ADMIN'];
    const MED_CERT_MAX_BYTES = 5242880; // 5 MB

    // ==========================================
    // 🌟 ฟังก์ชันช่วยเหลือ (Helper Functions)
    // ==========================================

    private function currentRole() {
        $role = $_SESSION['user']['role'] ?? '';
        return is_string($role) ? strtoupper(trim($role)) : '';
    }

    private function getCsrfToken() {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private function verifyCsrf($redirect = 'index.php?c=leave&a=index') {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $postedToken = $_POST['csrf_token'] ?? '';

        if (!is_string($sessionToken) || !is_string($postedToken) ||
            $sessionToken === '' || $postedToken === '' ||
            !hash_equals($sessionToken, $postedToken)) {
            $_SESSION['error_msg'] = 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
            header('Location: ' . $redirect);
            exit;
        }
    }

    private function requireLeaveManager() {
        if (!isset($_SESSION['user']) || !in_array($this->currentRole(), self::LEAVE_MANAGER_ROLES, true)) {
            $_SESSION['error_msg'] = 'คุณไม่มีสิทธิ์ดำเนินการรายการนี้';
            header('Location: index.php?c=leave&a=index');
            exit;
        }
    }

    // Database schema changes must be handled by migrations, not on every request.
    private function autoPatchDatabase($db) {
        return true;
    }

    private function getCurrentBudgetYear() {
        $month = (int)date('m');
        $year = (int)date('Y');
        return ($month >= 10) ? $year + 1 : $year;
    }

    private function calculateYearsOfService($start_date) {
        if (empty($start_date) || $start_date == '0000-00-00') return 0;
        $start = new DateTime($start_date);
        $today = new DateTime();
        return $today->diff($start)->y;
    }

    // ==========================================
    // 🌟 หน้าจอหลัก (ยื่นใบลา และ ประวัติการลาของฉัน)
    // ==========================================
    public function index() {
        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }

        $db = (new Database())->getConnection();
        $leaveModel = new LeaveModel($db);

        $user_id = (int)$_SESSION['user']['id'];
        $hospital_id = (int)($_SESSION['user']['hospital_id'] ?? 0);
        $role = $this->currentRole();
        $budget_year = $this->getCurrentBudgetYear();

        $selected_month = isset($_GET['month']) && is_string($_GET['month']) ? $_GET['month'] : date('Y-m');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selected_month)) {
            $selected_month = date('Y-m');
        }

        $csrf_token = $this->getCsrfToken();

        $stmt_emp = $db->prepare("SELECT employee_type FROM users WHERE id = ?");
        $stmt_emp->execute([$user_id]);
        $employee_type = $stmt_emp->fetchColumn() ?: '';

        $leave_balances = $leaveModel->getUserLeaveBalances($user_id, $budget_year);

        $stmt_my = $db->prepare("
            SELECT lr.*, lq.leave_type, u.name as user_name
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.id
            JOIN leave_quotas lq ON lr.leave_type_id = lq.id
            WHERE lr.user_id = :uid
              AND (
                  DATE_FORMAT(lr.start_date, '%Y-%m') = :selected_month
                  OR lr.status IN ('PENDING', 'CANCEL_REQUESTED')
              )
            ORDER BY lr.created_at DESC
        ");
        $stmt_my->execute([
            ':uid' => $user_id,
            ':selected_month' => $selected_month
        ]);
        $my_leaves = $stmt_my->fetchAll(PDO::FETCH_ASSOC);

        $stmt_types = $db->query("SELECT * FROM leave_quotas ORDER BY id ASC");
        $leave_types = $stmt_types->fetchAll(PDO::FETCH_ASSOC);

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/leave/index.php';
        echo "</div></div></body></html>";
    }

    // ==========================================
    // 🌟 ส่งคำขอลา (Submit Leave Request)
    // ==========================================
    public function request() {
        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?c=leave&a=index");
            exit;
        }

        $this->verifyCsrf("index.php?c=leave&a=index");

        $db = (new Database())->getConnection();
        $leaveModel = new LeaveModel($db);
        $notifModel = new NotificationModel($db);

        $user_id = (int)$_SESSION['user']['id'];
        $hospital_id = (int)($_SESSION['user']['hospital_id'] ?? 0);
        $user_name = trim((string)($_SESSION['user']['name'] ?? ''));
        $budget_year = $this->getCurrentBudgetYear();

        $leave_type_id = filter_input(INPUT_POST, 'leave_type_id', FILTER_VALIDATE_INT);
        $start_date = trim((string)($_POST['start_date'] ?? ''));
        $end_date = trim((string)($_POST['end_date'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        $start_dt = DateTime::createFromFormat('!Y-m-d', $start_date);
        $end_dt = DateTime::createFromFormat('!Y-m-d', $end_date);
        $valid_start = $start_dt && $start_dt->format('Y-m-d') === $start_date;
        $valid_end = $end_dt && $end_dt->format('Y-m-d') === $end_date;

        if (!$leave_type_id || !$valid_start || !$valid_end) {
            $_SESSION['error_msg'] = "กรุณาระบุประเภทการลาและช่วงวันที่ให้ถูกต้อง";
            header("Location: index.php?c=leave&a=index");
            exit;
        }

        if ($start_dt > $end_dt) {
            $_SESSION['error_msg'] = "วันที่สิ้นสุดการลา ต้องไม่น้อยกว่าวันที่เริ่มต้น";
            header("Location: index.php?c=leave&a=index");
            exit;
        }

        $leave_month = (int)$start_dt->format('m');
        $leave_year = (int)$start_dt->format('Y');
        $budget_year = ($leave_month >= 10) ? $leave_year + 1 : $leave_year;

        if ($reason === '' || mb_strlen($reason, 'UTF-8') > 1000) {
            $_SESSION['error_msg'] = "กรุณาระบุเหตุผลการลา และต้องไม่เกิน 1,000 ตัวอักษร";
            header("Location: index.php?c=leave&a=index");
            exit;
        }

        $stmt_type = $db->prepare("SELECT leave_type FROM leave_quotas WHERE id = ? LIMIT 1");
        $stmt_type->execute([$leave_type_id]);
        $leave_name = $stmt_type->fetchColumn();
        if (!$leave_name) {
            $_SESSION['error_msg'] = "ไม่พบประเภทการลาที่เลือก";
            header("Location: index.php?c=leave&a=index");
            exit;
        }

        $stmt_overlap = $db->prepare("
            SELECT 1 FROM leave_requests
            WHERE user_id = ?
              AND status IN ('PENDING', 'APPROVED', 'CANCEL_REQUESTED')
              AND start_date <= ?
              AND end_date >= ?
            LIMIT 1
        ");
        $stmt_overlap->execute([$user_id, $end_date, $start_date]);
        if ($stmt_overlap->fetchColumn()) {
            $_SESSION['error_msg'] = "คุณมีใบลาในช่วงเวลาดังกล่าวอยู่แล้ว (รอพิจารณา/อนุมัติ/รอยกเลิก)";
            header("Location: index.php?c=leave&a=index");
            exit;
        }

        $actual_working_days = $leaveModel->calculateWorkingDays($start_date, $end_date, $hospital_id);
        $balances = $leaveModel->getUserLeaveBalances($user_id, $budget_year);
        $remaining = 0.0;
        foreach ($balances as $balance) {
            if ((int)$balance['leave_type_id'] === (int)$leave_type_id) {
                $remaining = (float)$balance['remaining'];
                break;
            }
        }

        $stmt_emp = $db->prepare("SELECT employee_type, start_date FROM users WHERE id = ? LIMIT 1");
        $stmt_emp->execute([$user_id]);
        $emp_data = $stmt_emp->fetch(PDO::FETCH_ASSOC) ?: [];
        $emp_type = (string)($emp_data['employee_type'] ?? '');
        $start_date_emp = $emp_data['start_date'] ?? null;

        $is_official = (strpos($emp_type, 'ข้าราชการ') !== false || strpos($emp_type, 'พนักงานส่วนท้องถิ่น') !== false);
        $is_general = (strpos($emp_type, 'ทั่วไป') !== false);

        $today = new DateTime(date('Y-m-d'));
        $advance_diff = $today->diff($start_dt);
        $advance_notice_days = $advance_diff->invert ? -$advance_diff->days : $advance_diff->days;

        if ($leave_name === 'ลากิจส่วนตัว' && $is_general) {
            $_SESSION['error_msg'] = "ระเบียบการลา: พนักงานจ้างทั่วไป ไม่มีสิทธิลากิจส่วนตัว";
            header("Location: index.php?c=leave&a=index"); exit;
        }

        if ($leave_name === 'ลาพักผ่อน') {
            if ($is_general) {
                $_SESSION['error_msg'] = "ระเบียบการลา: พนักงานจ้างทั่วไป ไม่มีสิทธิลาพักผ่อน";
                header("Location: index.php?c=leave&a=index"); exit;
            }
            if (!$is_official && $start_date_emp) {
                $emp_start = DateTime::createFromFormat('!Y-m-d', (string)$start_date_emp);
                if ($emp_start) {
                    $service_diff = $emp_start->diff($today);
                    $months_worked = ($service_diff->y * 12) + $service_diff->m;
                    if ($service_diff->invert === 0 && $months_worked < 6) {
                        $_SESSION['error_msg'] = "ระเบียบการลา: ต้องปฏิบัติงานครบ 6 เดือนก่อน จึงจะมีสิทธิลาพักผ่อน";
                        header("Location: index.php?c=leave&a=index"); exit;
                    }
                }
            }
        }

        if ((strpos($leave_name, 'อุปสมบท') !== false || strpos($leave_name, 'ฮัจย์') !== false)) {
            if ($is_general) {
                $_SESSION['error_msg'] = "ระเบียบการลา: พนักงานจ้างทั่วไป ไม่มีสิทธิลาอุปสมบท/ประกอบพิธีฮัจย์";
                header("Location: index.php?c=leave&a=index"); exit;
            }
            if ($advance_notice_days < 60) {
                $_SESSION['error_msg'] = "ระเบียบการลา: การลาอุปสมบท/ฮัจย์ ต้องยื่นล่วงหน้าไม่น้อยกว่า 60 วัน";
                header("Location: index.php?c=leave&a=index"); exit;
            }
        }

        if (strpos($leave_name, 'ภริยาคลอด') !== false) {
            if (!$is_official) {
                $_SESSION['error_msg'] = "ระเบียบการลา: สิทธิลาไปช่วยเหลือภริยาคลอดบุตร เฉพาะข้าราชการ/พนักงานส่วนท้องถิ่นเท่านั้น";
                header("Location: index.php?c=leave&a=index"); exit;
            }
            if ($actual_working_days > 15) {
                $_SESSION['error_msg'] = "ระเบียบการลา: ลาไปช่วยเหลือภริยาคลอดบุตร ติดต่อกันได้ไม่เกิน 15 วันทำการ";
                header("Location: index.php?c=leave&a=index"); exit;
            }
        } elseif (strpos($leave_name, 'คลอดบุตร') !== false) {
            $total_days = $start_dt->diff($end_dt)->days + 1;
            if ($total_days > 90) {
                $_SESSION['error_msg'] = "ระเบียบการลา: ลาคลอดบุตร ลาได้ไม่เกิน 90 วัน (นับรวมวันหยุดราชการแล้ว)";
                header("Location: index.php?c=leave&a=index"); exit;
            }
            $actual_working_days = $total_days;
        }

        if (strpos($leave_name, 'เตรียมพล') !== false || strpos($leave_name, 'คัดเลือก') !== false) {
            if ($advance_notice_days < 2) {
                $_SESSION['error_msg'] = "ระเบียบการลา: ลาเข้ารับการคัดเลือก/เตรียมพล ต้องรายงานตัวยื่นล่วงหน้าไม่น้อยกว่า 48 ชั่วโมง";
                header("Location: index.php?c=leave&a=index"); exit;
            }
        }

        if ($actual_working_days <= 0) {
            $_SESSION['error_msg'] = "ช่วงเวลาที่คุณเลือกตรงกับวันหยุดทั้งหมด ไม่จำเป็นต้องยื่นใบลา";
            header("Location: index.php?c=leave&a=index"); exit;
        }

        if (in_array($leave_name, ['ลาพักผ่อน', 'ลากิจส่วนตัว', 'ลาป่วย'], true) && $actual_working_days > $remaining) {
            $_SESSION['error_msg'] = "โควตา {$leave_name} ของคุณไม่เพียงพอ (เหลือ {$remaining} วัน แต่ขอลา {$actual_working_days} วัน)";
            header("Location: index.php?c=leave&a=index"); exit;
        }

        $has_med_cert = 0;
        $med_cert_path = null;
        $uploaded_absolute_path = null;

        if ($leave_name === 'ลาป่วย') {
            $upload = $_FILES['med_cert_file'] ?? null;
            $has_upload = is_array($upload) && isset($upload['error']) && $upload['error'] !== UPLOAD_ERR_NO_FILE;

            if ($actual_working_days >= 3 && !$has_upload) {
                $_SESSION['error_msg'] = "การลาป่วยติดต่อกัน 3 วันทำการขึ้นไป ต้องอัปโหลดไฟล์ใบรับรองแพทย์ด้วย";
                header("Location: index.php?c=leave&a=index"); exit;
            }

            if ($has_upload) {
                if ($upload['error'] !== UPLOAD_ERR_OK || empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
                    $_SESSION['error_msg'] = "อัปโหลดใบรับรองแพทย์ไม่สำเร็จ กรุณาลองใหม่";
                    header("Location: index.php?c=leave&a=index"); exit;
                }

                if ((int)$upload['size'] <= 0 || (int)$upload['size'] > self::MED_CERT_MAX_BYTES) {
                    $_SESSION['error_msg'] = "ไฟล์ใบรับรองแพทย์ต้องมีขนาดไม่เกิน 5 MB";
                    header("Location: index.php?c=leave&a=index"); exit;
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($upload['tmp_name']);
                $allowed_mimes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'application/pdf' => 'pdf'
                ];

                if (!isset($allowed_mimes[$mime])) {
                    $_SESSION['error_msg'] = "รองรับเฉพาะไฟล์ JPG, PNG หรือ PDF เท่านั้น";
                    header("Location: index.php?c=leave&a=index"); exit;
                }

                $upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'med_certs';
                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0750, true) && !is_dir($upload_dir)) {
                    $_SESSION['error_msg'] = "ไม่สามารถเตรียมพื้นที่จัดเก็บใบรับรองแพทย์ได้";
                    header("Location: index.php?c=leave&a=index"); exit;
                }

                $new_name = 'cert_' . $user_id . '_' . bin2hex(random_bytes(16)) . '.' . $allowed_mimes[$mime];
                $target_file = $upload_dir . DIRECTORY_SEPARATOR . $new_name;

                if (!move_uploaded_file($upload['tmp_name'], $target_file)) {
                    $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึกไฟล์ใบรับรองแพทย์";
                    header("Location: index.php?c=leave&a=index"); exit;
                }

                $has_med_cert = 1;
                $med_cert_path = 'storage/med_certs/' . $new_name;
                $uploaded_absolute_path = $target_file;
            }
        }

        $saved = $leaveModel->addLeaveRequest([
            'user_id' => $user_id,
            'leave_type_id' => $leave_type_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'num_days' => $actual_working_days,
            'reason' => $reason,
            'has_med_cert' => $has_med_cert,
            'med_cert_path' => $med_cert_path
        ]);

        if ($saved) {
            LogsController::addLog($db, $user_id, LogsController::ACTION_CREATE, "ยื่นคำร้องขอ{$leave_name} จำนวน {$actual_working_days} วัน");
            $_SESSION['success_msg'] = "ยื่นใบลาสำเร็จ จำนวน {$actual_working_days} วัน (รอการพิจารณา)";

            $stmt = $db->prepare("SELECT id FROM users WHERE hospital_id = ? AND role IN ('DIRECTOR', 'ADMIN', 'SUPERADMIN')");
            $stmt->execute([$hospital_id]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $approver) {
                $notifModel->addNotification($approver['id'], 'INFO', 'ใบลาใหม่รออนุมัติ', "{$user_name} ขอ{$leave_name} {$actual_working_days} วัน", "index.php?c=leave&a=approvals");
            }
            (new NotificationService($db))->sendLineEvent(
                'leave',
                "มีใบลาใหม่รออนุมัติ: {$user_name} / {$leave_name} / {$actual_working_days} วัน"
            );
        } else {
            if ($uploaded_absolute_path && is_file($uploaded_absolute_path)) {
                @unlink($uploaded_absolute_path);
            }
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลลงระบบ";
        }

        header("Location: index.php?c=leave&a=index");
        exit;
    }

    // ==========================================
    // 🌟 ยกเลิกใบลา (ปรับปรุง: ลบทิ้งเพื่อล้างประวัติ)
    // ==========================================
    public function cancel() {
        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?c=leave&a=index");
            exit;
        }

        $this->verifyCsrf("index.php?c=leave&a=index");

        $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
        if (!$request_id) {
            $_SESSION['error_msg'] = "ข้อมูลใบลาไม่ถูกต้อง";
            header("Location: index.php?c=leave&a=index");
            exit;
        }

        $db = (new Database())->getConnection();
        $notifModel = new NotificationModel($db);
        $user_id = (int)$_SESSION['user']['id'];
        $user_name = trim((string)($_SESSION['user']['name'] ?? ''));
        $hospital_id = (int)($_SESSION['user']['hospital_id'] ?? 0);

        $stmt = $db->prepare("
            SELECT lr.*, lq.leave_type
            FROM leave_requests lr
            JOIN leave_quotas lq ON lr.leave_type_id = lq.id
            WHERE lr.id = ? AND lr.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$request_id, $user_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$leave) {
            $_SESSION['error_msg'] = "ไม่พบข้อมูลใบลาหรือคุณไม่มีสิทธิ์ดำเนินการ";
            header("Location: index.php?c=leave&a=index");
            exit;
        }

        if ($leave['status'] === 'PENDING') {
            $stmt_up = $db->prepare("UPDATE leave_requests SET status = 'CANCELLED' WHERE id = ? AND user_id = ? AND status = 'PENDING'");
            $stmt_up->execute([$request_id, $user_id]);

            if ($stmt_up->rowCount() === 1) {
                LogsController::addLog($db, $user_id, LogsController::ACTION_UPDATE, "ยกเลิกคำขอใบ{$leave['leave_type']} (ID: {$request_id})");
                $_SESSION['success_msg'] = "ยกเลิกใบลาเรียบร้อยแล้ว";
            } else {
                $_SESSION['error_msg'] = "สถานะใบลาเปลี่ยนแปลงแล้ว กรุณารีเฟรชและลองใหม่";
            }
        } elseif ($leave['status'] === 'APPROVED') {
            $stmt_up = $db->prepare("UPDATE leave_requests SET status = 'CANCEL_REQUESTED' WHERE id = ? AND user_id = ? AND status = 'APPROVED'");
            $stmt_up->execute([$request_id, $user_id]);

            if ($stmt_up->rowCount() === 1) {
                LogsController::addLog($db, $user_id, LogsController::ACTION_UPDATE, "ส่งคำขอยกเลิกใบ{$leave['leave_type']}ที่อนุมัติแล้ว (ID: {$request_id})");
                $_SESSION['success_msg'] = "ส่งคำขอยกเลิกใบลาแล้ว กรุณารอหัวหน้าพิจารณาเพื่อคืนโควตาวันลา";

                $stmt_app = $db->prepare("SELECT id FROM users WHERE hospital_id = ? AND role IN ('DIRECTOR', 'ADMIN', 'SUPERADMIN')");
                $stmt_app->execute([$hospital_id]);
                foreach ($stmt_app->fetchAll(PDO::FETCH_ASSOC) as $approver) {
                    $notifModel->addNotification($approver['id'], 'WARNING', 'มีคำขอยกเลิกใบลา', "{$user_name} ขอยกเลิกใบ{$leave['leave_type']}", "index.php?c=leave&a=approvals");
                }
            } else {
                $_SESSION['error_msg'] = "สถานะใบลาเปลี่ยนแปลงแล้ว กรุณารีเฟรชและลองใหม่";
            }
        } else {
            $_SESSION['error_msg'] = "ใบลารายการนี้ไม่สามารถยกเลิกได้ในสถานะปัจจุบัน";
        }

        header("Location: index.php?c=leave&a=index");
        exit;
    }

    // ==========================================
    // 🌟 หน้าอนุมัติการลา (Approvals)
    // ==========================================
    public function approvals() {
        $this->requireLeaveManager();

        $db = (new Database())->getConnection();
        $role = $this->currentRole();
        $hospital_id = (int)($_SESSION['user']['hospital_id'] ?? 0);
        $csrf_token = $this->getCsrfToken();

        $query = "SELECT lr.*, lq.leave_type, u.name as user_name, u.employee_type, h.name as hospital_name
                  FROM leave_requests lr
                  JOIN users u ON lr.user_id = u.id
                  JOIN leave_quotas lq ON lr.leave_type_id = lq.id
                  LEFT JOIN hospitals h ON u.hospital_id = h.id
                  WHERE lr.status IN ('PENDING', 'CANCEL_REQUESTED') ";

        if (!in_array($role, self::LEAVE_ADMIN_ROLES, true)) {
            $query .= " AND u.hospital_id = :hosp_id ";
        }
        $query .= " ORDER BY lr.created_at ASC";

        $stmt = $db->prepare($query);
        if (!in_array($role, self::LEAVE_ADMIN_ROLES, true)) {
            $stmt->bindValue(':hosp_id', $hospital_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $pending_leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/leave/approvals.php';
        echo "</div></div></body></html>";
    }

    // 🌟 ประมวลผลการอนุมัติ (รวมถึงการอนุมัติให้ยกเลิก)
    public function process_approval() {
        $this->requireLeaveManager();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?c=leave&a=approvals");
            exit;
        }
        $this->verifyCsrf("index.php?c=leave&a=approvals");

        $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
        $action = strtoupper(trim((string)($_POST['action'] ?? '')));
        $allowed_actions = ['APPROVED', 'REJECTED', 'APPROVE_CANCEL', 'REJECT_CANCEL'];

        if (!$request_id || !in_array($action, $allowed_actions, true)) {
            $_SESSION['error_msg'] = "คำสั่งอนุมัติไม่ถูกต้อง";
            header("Location: index.php?c=leave&a=approvals");
            exit;
        }

        $db = (new Database())->getConnection();
        $notifModel = new NotificationModel($db);
        $approver_id = (int)$_SESSION['user']['id'];
        $approver_role = $this->currentRole();
        $approver_hospital_id = (int)($_SESSION['user']['hospital_id'] ?? 0);

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                SELECT lr.*, u.name AS request_user_name, u.hospital_id AS request_hospital_id,
                       lq.leave_type AS leave_type_name
                FROM leave_requests lr
                JOIN users u ON lr.user_id = u.id
                JOIN leave_quotas lq ON lr.leave_type_id = lq.id
                WHERE lr.id = ?
                FOR UPDATE
            ");
            $stmt->execute([$request_id]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                throw new RuntimeException("ไม่พบข้อมูลใบลา");
            }

            if (!in_array($approver_role, self::LEAVE_ADMIN_ROLES, true) &&
                (int)$req['request_hospital_id'] !== $approver_hospital_id) {
                throw new RuntimeException("คุณไม่มีสิทธิ์อนุมัติใบลาของหน่วยงานอื่น");
            }

            $leave_type_name = $req['leave_type_name'] ?: 'การลา';
            $request_user_name = $req['request_user_name'] ?: ('User ID: ' . $req['user_id']);

            $start_ts = strtotime((string)$req['start_date']);
            $start_month = (int)date('m', $start_ts);
            $start_year = (int)date('Y', $start_ts);
            $budget_year = ($start_month >= 10) ? $start_year + 1 : $start_year;

            if ($req['status'] === 'PENDING') {
                if ($action === 'APPROVED') {
                    $stmt_up = $db->prepare("
                        UPDATE leave_requests
                        SET status = 'APPROVED', approved_by = ?, approved_at = NOW()
                        WHERE id = ? AND status = 'PENDING'
                    ");
                    $stmt_up->execute([$approver_id, $request_id]);
                    if ($stmt_up->rowCount() !== 1) {
                        throw new RuntimeException("สถานะใบลาเปลี่ยนแปลงแล้ว กรุณารีเฟรช");
                    }

                    $leaveModel = new LeaveModel($db);
                    $leaveModel->getUserLeaveBalances((int)$req['user_id'], $budget_year);

                    $stmt_bal = $db->prepare("
                        UPDATE leave_balances
                        SET used_days = used_days + ?
                        WHERE user_id = ? AND budget_year = ? AND leave_type_id = ?
                    ");
                    $stmt_bal->execute([$req['num_days'], $req['user_id'], $budget_year, $req['leave_type_id']]);
                    if ($stmt_bal->rowCount() !== 1) {
                        throw new RuntimeException("ไม่พบบัญชีวันลาสำหรับตัดยอด");
                    }

                    LogsController::addLog($db, $approver_id, LogsController::ACTION_UPDATE, "อนุมัติใบ{$leave_type_name} ของ {$request_user_name} (Ref ID: {$request_id})");
                    $notifModel->addNotification($req['user_id'], 'SUCCESS', "ผลการพิจารณาใบลา", "ใบ{$leave_type_name} ของคุณได้รับคำสั่ง: อนุมัติแล้ว", "index.php?c=leave");
                    $_SESSION['success_msg'] = "อนุมัติใบลาและตัดยอดคงเหลือเรียบร้อยแล้ว";
                } elseif ($action === 'REJECTED') {
                    $stmt_up = $db->prepare("
                        UPDATE leave_requests
                        SET status = 'REJECTED', approved_by = ?, approved_at = NOW()
                        WHERE id = ? AND status = 'PENDING'
                    ");
                    $stmt_up->execute([$approver_id, $request_id]);
                    if ($stmt_up->rowCount() !== 1) {
                        throw new RuntimeException("สถานะใบลาเปลี่ยนแปลงแล้ว กรุณารีเฟรช");
                    }

                    LogsController::addLog($db, $approver_id, LogsController::ACTION_UPDATE, "ไม่อนุมัติใบ{$leave_type_name} ของ {$request_user_name} (Ref ID: {$request_id})");
                    $notifModel->addNotification($req['user_id'], 'DANGER', "ผลการพิจารณาใบลา", "ใบ{$leave_type_name} ของคุณได้รับคำสั่ง: ไม่อนุมัติ", "index.php?c=leave");
                    $_SESSION['success_msg'] = "ปฏิเสธใบลาเรียบร้อยแล้ว";
                } else {
                    throw new RuntimeException("คำสั่งไม่ตรงกับสถานะใบลา");
                }
            } elseif ($req['status'] === 'CANCEL_REQUESTED') {
                if ($action === 'APPROVE_CANCEL') {
                    $stmt_up = $db->prepare("
                        UPDATE leave_requests
                        SET status = 'CANCELLED', approved_by = ?, approved_at = NOW()
                        WHERE id = ? AND status = 'CANCEL_REQUESTED'
                    ");
                    $stmt_up->execute([$approver_id, $request_id]);
                    if ($stmt_up->rowCount() !== 1) {
                        throw new RuntimeException("สถานะใบลาเปลี่ยนแปลงแล้ว กรุณารีเฟรช");
                    }

                    $stmt_bal = $db->prepare("
                        UPDATE leave_balances
                        SET used_days = GREATEST(0, used_days - ?)
                        WHERE user_id = ? AND budget_year = ? AND leave_type_id = ?
                    ");
                    $stmt_bal->execute([$req['num_days'], $req['user_id'], $budget_year, $req['leave_type_id']]);

                    LogsController::addLog($db, $approver_id, LogsController::ACTION_UPDATE, "อนุมัติยกเลิกใบ{$leave_type_name} ของ {$request_user_name} และคืนโควตา (Ref ID: {$request_id})");
                    $notifModel->addNotification($req['user_id'], 'INFO', "แจ้งผลการยกเลิกใบลา", "หัวหน้าอนุมัติการยกเลิกใบ{$leave_type_name} และคืนสิทธิ์ให้คุณแล้ว", "index.php?c=leave");
                    $_SESSION['success_msg'] = "อนุมัติการยกเลิกและคืนโควตาเรียบร้อยแล้ว";
                } elseif ($action === 'REJECT_CANCEL') {
                    $stmt_up = $db->prepare("
                        UPDATE leave_requests
                        SET status = 'APPROVED', approved_by = ?, approved_at = NOW()
                        WHERE id = ? AND status = 'CANCEL_REQUESTED'
                    ");
                    $stmt_up->execute([$approver_id, $request_id]);
                    if ($stmt_up->rowCount() !== 1) {
                        throw new RuntimeException("สถานะใบลาเปลี่ยนแปลงแล้ว กรุณารีเฟรช");
                    }

                    LogsController::addLog($db, $approver_id, LogsController::ACTION_UPDATE, "ไม่อนุมัติคำขอยกเลิกใบ{$leave_type_name} ของ {$request_user_name} (Ref ID: {$request_id})");
                    $notifModel->addNotification($req['user_id'], 'WARNING', "แจ้งผลการยกเลิกใบลา", "หัวหน้าไม่อนุมัติการยกเลิกใบ{$leave_type_name} ของคุณ", "index.php?c=leave");
                    $_SESSION['success_msg'] = "ปฏิเสธการยกเลิกใบลา ใบลายังคงสถานะอนุมัติ";
                } else {
                    throw new RuntimeException("คำสั่งไม่ตรงกับสถานะคำขอยกเลิก");
                }
            } else {
                throw new RuntimeException("รายการนี้ถูกดำเนินการไปแล้ว");
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error_msg'] = $e->getMessage();
        }

        header("Location: index.php?c=leave&a=approvals");
        exit;
    }



    // ==========================================
    // 🌟 1. จัดการวันลารายบุคคล (โควตาภาพรวมทั้งหมด)
    // ==========================================
    public function manage() {
        if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['SUPERADMIN', 'ADMIN', 'DIRECTOR', 'SCHEDULER'])) { header("Location: index.php?c=leave"); exit; }
        
        $db = (new Database())->getConnection(); 
        $leaveModel = new LeaveModel($db); 
        $userModel = new UserModel($db);
        
        $hospital_id = $_SESSION['user']['hospital_id']; 
        $role = $this->currentRole();
        $csrf_token = $this->getCsrfToken();
        $budget_year = isset($_GET['year']) ? (int)$_GET['year'] : $this->getCurrentBudgetYear();
        
        $staffs = in_array($role, ['SUPERADMIN', 'ADMIN']) ? $userModel->getAllStaff() : $userModel->getUsersByHospital($hospital_id);
        
        // 🌟 แก้ไข: ดึงข้อมูลพนักงานที่แก้ไขจากฐานข้อมูล (ป้องกันบัคเปลี่ยนคนตอนบันทึก)
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_balance') {
            $this->verifyCsrf("index.php?c=leave&a=manage");
            $balance_id = filter_input(INPUT_POST, 'balance_id', FILTER_VALIDATE_INT);
            if (!$balance_id) {
                $_SESSION['error_msg'] = "ข้อมูลบัญชีวันลาไม่ถูกต้อง";
                header("Location: index.php?c=leave&a=manage");
                exit;
            }

            $sql_bal = "SELECT lb.user_id, lb.budget_year
                        FROM leave_balances lb
                        JOIN users u ON lb.user_id = u.id
                        WHERE lb.id = ?";
            $params_bal = [$balance_id];
            if (!in_array($role, self::LEAVE_ADMIN_ROLES, true)) {
                $sql_bal .= " AND u.hospital_id = ?";
                $params_bal[] = (int)$hospital_id;
            }
            $stmt_bal = $db->prepare($sql_bal);
            $stmt_bal->execute($params_bal);
            $bal = $stmt_bal->fetch(PDO::FETCH_ASSOC);

            if (!$bal) {
                $_SESSION['error_msg'] = "ไม่พบบัญชีวันลา หรือคุณไม่มีสิทธิ์แก้ไขรายการนี้";
                header("Location: index.php?c=leave&a=manage");
                exit;
            }
            
            $target_user_id = $bal ? $bal['user_id'] : (count($staffs) > 0 ? $staffs[0]['id'] : null);
            $target_budget_year = $bal ? $bal['budget_year'] : $budget_year;
            
            $target_user = $target_user_id ? $userModel->getUserById($target_user_id) : null;
            $user_name = $target_user ? $target_user['name'] : '';

            $quota_days = max(0, (float)($_POST['quota_days'] ?? 0));
            $carried_over_days = max(0, (float)($_POST['carried_over_days'] ?? 0));
            $used_days = max(0, (float)($_POST['used_days'] ?? 0));
            $leaveModel->updateLeaveBalance($balance_id, $quota_days, $carried_over_days, $used_days);
            
            // 🌟 บันทึก Log: จัดการแก้โควตาด้วยตัวเอง
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "แก้ไขโควตาวันลาด้วยมือให้ {$user_name} (Balance ID: {$balance_id})");
            
            $_SESSION['success_msg'] = "อัปเดตข้อมูลวันลาของ {$user_name} เรียบร้อยแล้ว"; 
            
            header("Location: index.php?c=leave&a=manage&user_id={$target_user_id}&year={$target_budget_year}"); 
            exit;
        }

        $target_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (count($staffs) > 0 ? (int)$staffs[0]['id'] : null);
        $balances = []; $target_user = null;

        if ($target_user_id) {
            if (!in_array($role, self::LEAVE_ADMIN_ROLES, true)) {
                $allowed_ids = array_map('intval', array_column($staffs, 'id'));
                if (!in_array((int)$target_user_id, $allowed_ids, true)) {
                    $target_user_id = count($staffs) > 0 ? (int)$staffs[0]['id'] : null;
                }
            }

            $target_user = $target_user_id ? $userModel->getUserById($target_user_id) : null;
            $leaveModel->getUserLeaveBalances($target_user_id, $budget_year);
            $stmt = $db->prepare("SELECT lb.*, lq.leave_type as leave_type_name FROM leave_balances lb JOIN leave_quotas lq ON lb.leave_type_id = lq.id WHERE lb.user_id = ? AND lb.budget_year = ?");
            $stmt->execute([$target_user_id, $budget_year]); $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        require_once 'views/layouts/header.php'; require_once 'views/layouts/sidebar.php'; require_once 'views/leave/manage.php'; echo "</div></div></body></html>";
    }


    // ==========================================
    // 🌟 2. จัดการวันลาสะสม (ลาพักผ่อน)
    // ==========================================
    public function balances() {
        if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['SUPERADMIN', 'ADMIN', 'DIRECTOR', 'SCHEDULER'])) {
            header("Location: index.php?c=leave"); exit;
        }

        $db = (new Database())->getConnection();
        $userModel = new UserModel($db);
        $leaveModel = new LeaveModel($db);

        $hospital_id = $_SESSION['user']['hospital_id'];
        $role = $this->currentRole();
        $budget_year = $this->getCurrentBudgetYear();
        $csrf_token = $this->getCsrfToken();

        $staffs = in_array($role, ['SUPERADMIN', 'ADMIN']) ? $userModel->getAllStaff() : $userModel->getUsersByHospital($hospital_id);

        $users_balances = [];
        foreach ($staffs as $staff) {
            $balances = $leaveModel->getUserLeaveBalances($staff['id'], $budget_year);
            $vacation = null;
            foreach ($balances as $b) {
                if (trim($b['leave_type_name']) === 'ลาพักผ่อน') { $vacation = $b; break; }
            }
            $users_balances[] = [
                'id' => $staff['id'],
                'name' => $staff['name'],
                'type' => $staff['type'] ?? '-',
                'hospital_name' => $staff['hospital_name'] ?? 'ส่วนกลาง',
                'start_date' => $staff['start_date'],
                'brought_forward' => $vacation ? floatval($vacation['carried_over_days']) : 0,
                'used_this_year' => $vacation ? floatval($vacation['used_days']) : 0,
                'balance_id' => $vacation ? $vacation['id'] : null
            ];
        }

        require_once 'views/layouts/header.php'; require_once 'views/layouts/sidebar.php'; require_once 'views/leave/balances.php'; echo "</div></div></body></html>";
    }

    public function save_balance() {
        $this->requireLeaveManager();

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->verifyCsrf("index.php?c=leave&a=balances");
            $db = (new Database())->getConnection(); 
            $leaveModel = new LeaveModel($db);
            
            $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $brought_forward = max(0, (float)($_POST['brought_forward'] ?? 0));
            $budget_year = $this->getCurrentBudgetYear();

            if (!$user_id) {
                $_SESSION['error_msg'] = "ข้อมูลบุคลากรไม่ถูกต้อง";
                header("Location: index.php?c=leave&a=balances");
                exit;
            }

            $role = $this->currentRole();
            if (!in_array($role, self::LEAVE_ADMIN_ROLES, true)) {
                $stmt_scope = $db->prepare("SELECT 1 FROM users WHERE id = ? AND hospital_id = ? LIMIT 1");
                $stmt_scope->execute([$user_id, (int)($_SESSION['user']['hospital_id'] ?? 0)]);
                if (!$stmt_scope->fetchColumn()) {
                    $_SESSION['error_msg'] = "คุณไม่มีสิทธิ์แก้ไขวันลาของหน่วยงานอื่น";
                    header("Location: index.php?c=leave&a=balances");
                    exit;
                }
            }
            
            $balances = $leaveModel->getUserLeaveBalances($user_id, $budget_year);
            $balance_id = null; $quota_days = 10; $used_days = 0;
            
            foreach ($balances as $b) {
                if (trim($b['leave_type_name']) === 'ลาพักผ่อน') {
                    $balance_id = $b['id']; $quota_days = $b['quota_days']; $used_days = $b['used_days']; break;
                }
            }
            
            if ($balance_id) {
                $leaveModel->updateLeaveBalance($balance_id, $quota_days, $brought_forward, $used_days);
                
                // ดึงชื่อพนักงานสำหรับบันทึก Log
                $stmt_name = $db->prepare("SELECT name FROM users WHERE id = ?");
                $stmt_name->execute([$user_id]);
                $emp_name = $stmt_name->fetchColumn() ?: "ID: {$user_id}";

                // 🌟 บันทึก Log: ปรับปรุงยอดวันลายกมา
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "ปรับปรุงวันลาพักผ่อนยกมาของ {$emp_name}");
                
                $_SESSION['success_msg'] = "ปรับปรุงยอดวันลายกมาเรียบร้อยแล้ว";
            } else {
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาด: ไม่พบบัญชีวันลาพักผ่อนของบุคลากรท่านนี้";
            }
        }
        header("Location: index.php?c=leave&a=balances"); exit;
    }

    // ==========================================
    // 🌟 3. ประมวลผลตัดยอดวันลาพักผ่อนปีงบประมาณใหม่
    // ==========================================
    public function process_new_year() {
        $this->requireLeaveManager();

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->verifyCsrf("index.php?c=leave&a=balances");
            $db = (new Database())->getConnection();
            $leaveModel = new LeaveModel($db);
            $userModel = new UserModel($db);
            
            $current_budget_year = $this->getCurrentBudgetYear();
            $previous_budget_year = $current_budget_year - 1;
            
            $hospital_id = $_SESSION['user']['hospital_id'];
            $role = $_SESSION['user']['role'];
            $staffs = in_array($role, ['SUPERADMIN', 'ADMIN']) ? $userModel->getAllStaff() : $userModel->getUsersByHospital($hospital_id);
            
            $processed_count = 0;

            try {
                $db->beginTransaction();

                $stmt_q = $db->query("SELECT id FROM leave_quotas WHERE leave_type = 'ลาพักผ่อน' LIMIT 1");
                $vacation_id = $stmt_q->fetchColumn();

                if ($vacation_id) {
                    foreach ($staffs as $staff) {
                        $years_of_service = $this->calculateYearsOfService($staff['start_date']);
                        $max_accumulation = ($years_of_service >= 10) ? 30 : 20;

                        $stmt_prev = $db->prepare("SELECT * FROM leave_balances WHERE user_id = ? AND budget_year = ? AND leave_type_id = ?");
                        $stmt_prev->execute([$staff['id'], $previous_budget_year, $vacation_id]);
                        $prev_balance = $stmt_prev->fetch(PDO::FETCH_ASSOC);

                        $carry_over_days = 0;
                        if ($prev_balance) {
                            $remaining_last_year = ($prev_balance['carried_over_days'] + $prev_balance['quota_days']) - $prev_balance['used_days'];
                            $max_carry_over = $max_accumulation - 10;
                            $carry_over_days = min(max(0, $remaining_last_year), $max_carry_over);
                        }

                        $leaveModel->getUserLeaveBalances($staff['id'], $current_budget_year);
                        $stmt_update = $db->prepare("UPDATE leave_balances SET carried_over_days = ? WHERE user_id = ? AND budget_year = ? AND leave_type_id = ?");
                        $stmt_update->execute([$carry_over_days, $staff['id'], $current_budget_year, $vacation_id]);
                        
                        $processed_count++;
                    }
                }

                $db->commit();
                
                // 🌟 บันทึก Log: ประมวลผลวันลาปีใหม่
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "ประมวลผลตัดยอดวันลาพักผ่อนปีงบประมาณ {$current_budget_year} อัตโนมัติ (สำเร็จ {$processed_count} รายการ)");
                $_SESSION['success_msg'] = "ประมวลผลและคำนวณวันลายกมาปี {$current_budget_year} อัตโนมัติสำเร็จ จำนวน {$processed_count} รายการ";

            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการประมวลผล: " . $e->getMessage();
            }
        }
        header("Location: index.php?c=leave&a=balances"); exit;
    }

    // ==========================================
    // 🌟 หน้าอื่นๆ (ตั้งค่าและรายงาน)
    // ==========================================
    public function settings() {
        if (!isset($_SESSION['user']) || !in_array($this->currentRole(), self::LEAVE_ADMIN_ROLES, true)) { header("Location: index.php?c=leave"); exit; }
        
        $db = (new Database())->getConnection();
        $leaveModel = new LeaveModel($db);
        $csrf_token = $this->getCsrfToken();
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['quotas'])) {
            $this->verifyCsrf("index.php?c=leave&a=settings");
            foreach ($_POST['quotas'] as $id => $q) {
                $calc_type = isset($q['calculation_type']) ? $q['calculation_type'] : 'WORKING_DAYS';
                $leaveModel->updateLeaveQuota($id, $q['max_days'], $q['description'], $calc_type);
            }
            
            // 🌟 บันทึก Log: แก้ไขตั้งค่าระเบียบการลา
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "อัปเดตตั้งค่าฐานระเบียบการลาและโควตาวันลา (ส่วนกลาง)");
            $_SESSION['success_msg'] = "บันทึกการตั้งค่าสิทธิการลาของส่วนกลางเรียบร้อยแล้ว"; 
            header("Location: index.php?c=leave&a=settings"); 
            exit;
        }
        $quotas = $leaveModel->getAllLeaveQuotas();

        require_once 'views/layouts/header.php'; require_once 'views/layouts/sidebar.php'; require_once 'views/leave/settings.php'; echo "</div></div></body></html>";
    }

    public function report() {
        if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['SUPERADMIN', 'ADMIN', 'DIRECTOR', 'SCHEDULER'])) { header("Location: index.php?c=leave"); exit; }
        
        $db = (new Database())->getConnection(); 
        $leaveModel = new LeaveModel($db); 
        $userModel = new UserModel($db);
        
        $hospital_id = $_SESSION['user']['hospital_id']; 
        $role = $_SESSION['user']['role'];
        $budget_year = isset($_GET['year']) ? $_GET['year'] : $this->getCurrentBudgetYear();
        
        $staffs = in_array($role, ['SUPERADMIN', 'ADMIN']) ? $userModel->getAllStaff() : $userModel->getUsersByHospital($hospital_id);
        $leave_types = $leaveModel->getAllLeaveQuotas();
        
        $report_data = [];
        foreach ($staffs as $staff) {
            $balances = $leaveModel->getUserLeaveBalances($staff['id'], $budget_year);
            $report_data[$staff['id']] = [
                'staff' => $staff,
                'balances' => $balances
            ];
        }

        require_once 'views/layouts/header.php'; require_once 'views/layouts/sidebar.php'; require_once 'views/leave/report.php'; echo "</div></div></body></html>";
    }

    // ==========================================
    // Phase 12.1: จัดการแบบฟอร์มวันลา
    // ==========================================
    public function templates() {
        if (!isset($_SESSION['user']) || !in_array($this->currentRole(), self::LEAVE_ADMIN_ROLES, true)) {
            header("Location: index.php?c=leave");
            exit;
        }

        $db = (new Database())->getConnection();
        $templateModel = new LeaveTemplateModel($db);
        $leaveModel = new LeaveModel($db);

        $csrf_token = $this->getCsrfToken();
        $schema_ready = $templateModel->schemaReady();
        $templates = $schema_ready ? $templateModel->getAllTemplates() : [];
        $leave_types = $leaveModel->getAllLeaveQuotas();
        $placeholders = LeaveDocumentService::placeholderCatalog();

        $hospitals = [];
        try {
            $stmt = $db->query("SELECT id, name FROM hospitals WHERE deleted_at IS NULL ORDER BY name ASC");
            $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $stmt = $db->query("SELECT id, name FROM hospitals ORDER BY name ASC");
            $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/leave/templates.php';
        echo "</div></div></body></html>";
    }

    public function template_upload() {
        if (!isset($_SESSION['user']) || !in_array($this->currentRole(), self::LEAVE_ADMIN_ROLES, true)) {
            http_response_code(403);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?c=leave&a=templates");
            exit;
        }

        $this->verifyCsrf("index.php?c=leave&a=templates");

        $db = (new Database())->getConnection();
        $templateModel = new LeaveTemplateModel($db);

        if (!$templateModel->schemaReady()) {
            $_SESSION['error_msg'] = "กรุณารัน migration 20261007_leave_form_templates.sql ก่อน";
            header("Location: index.php?c=leave&a=templates");
            exit;
        }

        $templateName = trim((string)($_POST['template_name'] ?? ''));
        $leaveTypeId = filter_input(INPUT_POST, 'leave_type_id', FILTER_VALIDATE_INT) ?: null;
        $hospitalId = filter_input(INPUT_POST, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
        $notes = trim((string)($_POST['notes'] ?? ''));
        $upload = $_FILES['template_file'] ?? null;

        if ($templateName === '' || mb_strlen($templateName, 'UTF-8') > 180) {
            $_SESSION['error_msg'] = "กรุณาระบุชื่อแบบฟอร์มให้ถูกต้อง";
            header("Location: index.php?c=leave&a=templates");
            exit;
        }

        if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
            empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            $_SESSION['error_msg'] = "กรุณาเลือกไฟล์ DOCX หรือ PDF";
            header("Location: index.php?c=leave&a=templates");
            exit;
        }

        if ((int)($upload['size'] ?? 0) <= 0 || (int)$upload['size'] > 10 * 1024 * 1024) {
            $_SESSION['error_msg'] = "ไฟล์ Template ต้องมีขนาดไม่เกิน 10 MB";
            header("Location: index.php?c=leave&a=templates");
            exit;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($upload['tmp_name']);
        $ext = strtolower(pathinfo((string)$upload['name'], PATHINFO_EXTENSION));

        $fileType = null;
        if ($ext === 'docx' && in_array($mime, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream'
        ], true)) {
            $fileType = 'DOCX';
        } elseif ($ext === 'pdf' && $mime === 'application/pdf') {
            $fileType = 'PDF';
        }

        if (!$fileType) {
            $_SESSION['error_msg'] = "รองรับเฉพาะไฟล์ DOCX หรือ PDF เท่านั้น";
            header("Location: index.php?c=leave&a=templates");
            exit;
        }

        $storageDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'leave_templates';
        if (!is_dir($storageDir) && !mkdir($storageDir, 0750, true) && !is_dir($storageDir)) {
            $_SESSION['error_msg'] = "ไม่สามารถสร้างพื้นที่จัดเก็บ Template ได้";
            header("Location: index.php?c=leave&a=templates");
            exit;
        }

        $version = $templateModel->getNextVersion($leaveTypeId, $hospitalId, $templateName);
        $safeName = 'leave_template_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target = $storageDir . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file($upload['tmp_name'], $target)) {
            $_SESSION['error_msg'] = "ไม่สามารถบันทึกไฟล์ Template ได้";
            header("Location: index.php?c=leave&a=templates");
            exit;
        }

        $mappingStatus = 'PENDING';
        $detectedPlaceholders = [];

        if ($fileType === 'DOCX') {
            $detectedPlaceholders = (new LeaveDocumentService($db))->scanDocxPlaceholders($target);
            $mappingStatus = !empty($detectedPlaceholders) ? 'READY' : 'PENDING';
        }

        try {
            $templateId = $templateModel->createTemplate([
                'template_name' => $templateName,
                'leave_type_id' => $leaveTypeId,
                'hospital_id' => $hospitalId,
                'file_type' => $fileType,
                'original_filename' => basename((string)$upload['name']),
                'stored_path' => 'storage/leave_templates/' . $safeName,
                'version' => $version,
                'mapping_status' => $mappingStatus,
                'notes' => $notes,
                'created_by' => (int)$_SESSION['user']['id'],
            ]);

            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_CREATE, "อัปโหลดแบบฟอร์มวันลา ID {$templateId} ({$fileType})");
            if ($fileType === 'DOCX') {
                $_SESSION['success_msg'] = !empty($detectedPlaceholders)
                    ? "อัปโหลด Word Template สำเร็จ พบ Placeholder " . count($detectedPlaceholders) . " รายการ และพร้อมใช้งาน"
                    : "อัปโหลด Word Template สำเร็จ แต่ยังไม่พบ Placeholder {{...}} กรุณาแก้ไฟล์ Word แล้วอัปโหลดเป็น Version ใหม่";
            } else {
                $_SESSION['success_msg'] = "อัปโหลด PDF Template สำเร็จ และรอ Mapping ตำแหน่งใน Phase 12.2";
            }
        } catch (Throwable $e) {
            @unlink($target);
            error_log("Leave template upload error: " . $e->getMessage());
            $_SESSION['error_msg'] = "ไม่สามารถบันทึกข้อมูล Template ได้";
        }

        header("Location: index.php?c=leave&a=templates");
        exit;
    }

    public function template_toggle() {
        if (!isset($_SESSION['user']) || !in_array($this->currentRole(), self::LEAVE_ADMIN_ROLES, true)) {
            http_response_code(403);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?c=leave&a=templates");
            exit;
        }

        $this->verifyCsrf("index.php?c=leave&a=templates");
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $active = filter_input(INPUT_POST, 'active', FILTER_VALIDATE_INT);

        if (!$id || !in_array($active, [0,1], true)) {
            $_SESSION['error_msg'] = "คำขอไม่ถูกต้อง";
            header("Location: index.php?c=leave&a=templates");
            exit;
        }

        $db = (new Database())->getConnection();
        $model = new LeaveTemplateModel($db);
        $model->toggleActive($id, (bool)$active);
        $_SESSION['success_msg'] = "อัปเดตสถานะ Template แล้ว";
        header("Location: index.php?c=leave&a=templates");
        exit;
    }

    public function template_archive() {
        if (!isset($_SESSION['user']) || !in_array($this->currentRole(), self::LEAVE_ADMIN_ROLES, true)) {
            http_response_code(403);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?c=leave&a=templates");
            exit;
        }

        $this->verifyCsrf("index.php?c=leave&a=templates");
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $db = (new Database())->getConnection();
            (new LeaveTemplateModel($db))->archive($id);
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_DELETE, "เก็บ Template วันลาเข้าคลัง ID {$id}");
            $_SESSION['success_msg'] = "เก็บ Template เข้าคลังแล้ว";
        }
        header("Location: index.php?c=leave&a=templates");
        exit;
    }

    public function template_download() {
        if (!isset($_SESSION['user']) || !in_array($this->currentRole(), self::LEAVE_ADMIN_ROLES, true)) {
            http_response_code(403);
            exit;
        }

        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $db = (new Database())->getConnection();
        $template = $id ? (new LeaveTemplateModel($db))->findById($id) : null;

        if (!$template) {
            http_response_code(404);
            exit("ไม่พบ Template");
        }

        $absolute = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $template['stored_path']), DIRECTORY_SEPARATOR));
        $root = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'leave_templates');
        if (!$absolute || !$root || strpos($absolute, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($absolute)) {
            http_response_code(404);
            exit("ไม่พบไฟล์ Template");
        }

        $mime = $template['file_type'] === 'PDF'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($absolute));
        header('Content-Disposition: attachment; filename="' . rawurlencode($template['original_filename']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($absolute);
        exit;
    }

    public function generate_document() {
        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }

        $requestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$requestId) {
            http_response_code(400);
            exit("คำขอไม่ถูกต้อง");
        }

        $db = (new Database())->getConnection();
        $docService = new LeaveDocumentService($db);
        $templateModel = new LeaveTemplateModel($db);
        $leave = $docService->buildLeaveData($requestId);

        $role = $this->currentRole();
        $owner = (int)$leave['user_id'] === (int)$_SESSION['user']['id'];
        $global = in_array($role, self::LEAVE_ADMIN_ROLES, true);
        $local = in_array($role, ['DIRECTOR','SCHEDULER'], true)
            && (int)$leave['hospital_id'] === (int)($_SESSION['user']['hospital_id'] ?? 0);

        if (!$owner && !$global && !$local) {
            http_response_code(403);
            exit("คุณไม่มีสิทธิ์เข้าถึงเอกสารนี้");
        }

        if (!$templateModel->schemaReady()) {
            $_SESSION['error_msg'] = "ระบบแบบฟอร์มยังไม่ได้ติดตั้งฐานข้อมูล";
            header("Location: index.php?c=leave&a=index");
            exit;
        }

        $template = $templateModel->resolveActiveTemplate((int)$leave['leave_type_id'], (int)$leave['hospital_id']);
        if (!$template) {
            $_SESSION['error_msg'] = "ยังไม่มี Word Template ที่เปิดใช้งานสำหรับประเภทการลานี้";
            header("Location: index.php?c=leave&a=index");
            exit;
        }

        $source = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $template['stored_path']), DIRECTORY_SEPARATOR));
        $root = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'leave_templates');
        if (!$source || !$root || strpos($source, $root . DIRECTORY_SEPARATOR) !== 0) {
            http_response_code(404);
            exit("ไม่พบ Template");
        }

        $status = strtoupper((string)$leave['status']) === 'APPROVED' ? 'FINAL' : 'DRAFT';
        $outDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'leave_documents' . DIRECTORY_SEPARATOR . strtolower($status);
        $filename = 'leave_' . $requestId . '_v' . (int)$template['version'] . '_' . date('YmdHis') . '.docx';
        $output = $outDir . DIRECTORY_SEPARATOR . $filename;

        try {
            $docService->renderDocx($source, $output, $docService->replacementMap($leave));
            $hash = hash_file('sha256', $output);
            $documentId = $templateModel->recordGenerated([
                'leave_request_id' => $requestId,
                'template_id' => (int)$template['id'],
                'template_version' => (int)$template['version'],
                'document_path' => 'storage/leave_documents/' . strtolower($status) . '/' . $filename,
                'original_filename' => $filename,
                'document_hash' => $hash,
                'document_status' => $status,
                'generated_by' => (int)$_SESSION['user']['id'],
                'finalized_at' => $status === 'FINAL' ? date('Y-m-d H:i:s') : null,
            ]);

            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_EXPORT, "สร้างเอกสารใบลา DOCX ID {$documentId} จาก Leave {$requestId}");
            header("Location: index.php?c=leave&a=download_generated&id=" . $documentId);
            exit;
        } catch (Throwable $e) {
            error_log("Generate leave DOCX error: " . $e->getMessage());
            $_SESSION['error_msg'] = "สร้างเอกสารไม่สำเร็จ: " . $e->getMessage();
            header("Location: index.php?c=leave&a=index");
            exit;
        }
    }

    public function download_generated() {
        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }

        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $db = (new Database())->getConnection();
        $doc = $id ? (new LeaveTemplateModel($db))->getGeneratedById($id) : null;

        if (!$doc) {
            http_response_code(404);
            exit("ไม่พบเอกสาร");
        }

        $role = $this->currentRole();
        $allowed = (int)$doc['user_id'] === (int)$_SESSION['user']['id']
            || in_array($role, self::LEAVE_ADMIN_ROLES, true)
            || (in_array($role, ['DIRECTOR','SCHEDULER'], true)
                && (int)$doc['hospital_id'] === (int)($_SESSION['user']['hospital_id'] ?? 0));

        if (!$allowed) {
            http_response_code(403);
            exit;
        }

        $absolute = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $doc['document_path']), DIRECTORY_SEPARATOR));
        $root = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'leave_documents');

        if (!$absolute || !$root || strpos($absolute, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($absolute)) {
            http_response_code(404);
            exit("ไม่พบไฟล์เอกสาร");
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Length: ' . filesize($absolute));
        header('Content-Disposition: attachment; filename="' . rawurlencode($doc['original_filename']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($absolute);
        exit;
    }

    public function download_med_cert() {
        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }

        $request_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$request_id) {
            http_response_code(400);
            exit("คำขอไม่ถูกต้อง");
        }

        $db = (new Database())->getConnection();
        $stmt = $db->prepare("
            SELECT lr.user_id, lr.med_cert_path, u.hospital_id
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.id
            WHERE lr.id = ? AND lr.has_med_cert = 1
            LIMIT 1
        ");
        $stmt->execute([$request_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['med_cert_path'])) {
            http_response_code(404);
            exit("ไม่พบไฟล์ใบรับรองแพทย์");
        }

        $role = $this->currentRole();
        $current_user_id = (int)$_SESSION['user']['id'];
        $current_hospital_id = (int)($_SESSION['user']['hospital_id'] ?? 0);

        $allowed = ((int)$row['user_id'] === $current_user_id)
            || in_array($role, self::LEAVE_ADMIN_ROLES, true)
            || (in_array($role, ['DIRECTOR', 'SCHEDULER'], true)
                && (int)$row['hospital_id'] === $current_hospital_id);

        if (!$allowed) {
            http_response_code(403);
            exit("คุณไม่มีสิทธิ์เข้าถึงเอกสารนี้");
        }

        $relative_path = str_replace('\\', '/', (string)$row['med_cert_path']);
        $project_root = dirname(__DIR__);
        $absolute_path = realpath($project_root . DIRECTORY_SEPARATOR . ltrim($relative_path, '/'));

        $allowed_roots = [];
        foreach (['storage/med_certs', 'uploads/med_certs'] as $allowed_dir) {
            $resolved = realpath($project_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $allowed_dir));
            if ($resolved) {
                $allowed_roots[] = $resolved;
            }
        }

        $inside_allowed_root = false;
        if ($absolute_path && is_file($absolute_path)) {
            foreach ($allowed_roots as $root) {
                if (strpos($absolute_path, $root . DIRECTORY_SEPARATOR) === 0 || $absolute_path === $root) {
                    $inside_allowed_root = true;
                    break;
                }
            }
        }

        if (!$inside_allowed_root) {
            http_response_code(404);
            exit("ไม่พบไฟล์เอกสาร");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($absolute_path) ?: 'application/octet-stream';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
            http_response_code(415);
            exit("ชนิดไฟล์ไม่รองรับ");
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($absolute_path));
        header('Content-Disposition: inline; filename="medical-certificate-' . $request_id . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($absolute_path);
        exit;
    }

    public function print() {
        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }

        $request_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$request_id) {
            header("Location: index.php?c=leave");
            exit;
        }

        $db = (new Database())->getConnection();
        $stmt = $db->prepare("SELECT lr.*, lq.leave_type as leave_type_name, u.name as user_name, u.employee_type, u.hospital_id as user_hospital_id, h.name as hospital_name 
                              FROM leave_requests lr JOIN leave_quotas lq ON lr.leave_type_id = lq.id JOIN users u ON lr.user_id = u.id JOIN hospitals h ON u.hospital_id = h.id WHERE lr.id = ?");
        $stmt->execute([$request_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$leave) { die("ไม่พบข้อมูลใบลา"); }

        $role = $this->currentRole();
        $is_owner = ((int)$leave['user_id'] === (int)$_SESSION['user']['id']);
        $is_global_manager = in_array($role, self::LEAVE_ADMIN_ROLES, true);
        $is_local_manager = in_array($role, ['DIRECTOR', 'SCHEDULER'], true)
            && (int)$leave['user_hospital_id'] === (int)($_SESSION['user']['hospital_id'] ?? 0);

        if (!$is_owner && !$is_global_manager && !$is_local_manager) {
            die("คุณไม่มีสิทธิ์เข้าถึงเอกสารนี้");
        }

        $leaveModel = new LeaveModel($db);
        $start_ts = strtotime((string)$leave['start_date']);
        $start_month = (int)date('m', $start_ts);
        $start_year = (int)date('Y', $start_ts);
        $budget_year = ($start_month >= 10) ? $start_year + 1 : $start_year;
        $balances = $leaveModel->getUserLeaveBalances($leave['user_id'], $budget_year);
        
        $stat = ['quota' => 0, 'carried' => 0, 'used' => 0, 'remaining' => 0];
        foreach($balances as $b) {
            if($b['leave_type_id'] == $leave['leave_type_id']) {
                $stat = ['quota' => $b['quota_days'], 'carried' => $b['carried_over_days'], 'used' => $b['used_days']]; break;
            }
        }
        
        // 🌟 บันทึก Log: ดาวน์โหลด/พิมพ์เอกสาร
        LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_EXPORT, "พิมพ์เอกสารใบ{$leave['leave_type_name']} (Leave Ref ID: {$request_id})");
        
        require_once 'views/leave/print.php';
    }
}
?>