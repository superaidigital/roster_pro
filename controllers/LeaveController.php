<?php
// ที่อยู่ไฟล์: controllers/LeaveController.php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'models/LeaveModel.php';
require_once 'models/UserModel.php';
require_once 'models/HolidayModel.php';
require_once 'models/NotificationModel.php';
require_once 'controllers/LogsController.php'; // 🌟 ระบบ Log
require_once 'lib/SecureUpload.php';

class LeaveController {

    private function requireMutation(): void {
        security_start_session();

        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }

        if (!security_is_valid_post_csrf()) {
            http_response_code(403);
            $_SESSION['error_msg'] = "คำขอไม่ถูกต้องหรือหมดอายุ กรุณาลองใหม่";
            header("Location: index.php?c=leave");
            exit;
        }
    }

    // ==========================================
    // 🌟 ฟังก์ชันช่วยเหลือ (Helper Functions)
    // ==========================================
    
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

    private function sendLineNotify($db, $message) {
        try {
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'line_notify_token'");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $line_token = $row ? $row['setting_value'] : null;

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
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $result;
        } catch (Exception $e) { return false; }
    }

    // ==========================================
    // 🌟 หน้าจอหลัก (ยื่นใบลา และ ประวัติการลาของฉัน)
    // ==========================================
    public function index() {
        if (!isset($_SESSION['user'])) { header("Location: index.php?c=auth&a=index"); exit; }

        $db = (new Database())->getConnection();
$leaveModel = class_exists('LeaveModel') ? new LeaveModel($db) : null;
        
        $user_id = $_SESSION['user']['id'];
        $hospital_id = $_SESSION['user']['hospital_id'];
        $role = $_SESSION['user']['role'];
        $budget_year = $this->getCurrentBudgetYear();
        
        $selected_month = trim((string)($_GET['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selected_month)) {
            $selected_month = date('Y-m');
        }

        $stmt_emp = $db->prepare("SELECT employee_type FROM users WHERE id = ?");
        $stmt_emp->execute([$user_id]);
        $employee_type = $stmt_emp->fetchColumn() ?: '';

        $leave_balances = []; $my_leaves = [];
        if ($leaveModel) {
            $leave_balances = $leaveModel->getUserLeaveBalances($user_id, $budget_year);
            
            $stmt_my = $db->prepare("
                SELECT lr.*, lq.leave_type, u.name as user_name 
                FROM leave_requests lr
                JOIN users u ON lr.user_id = u.id
                JOIN leave_quotas lq ON lr.leave_type_id = lq.id
                WHERE lr.user_id = :uid 
                ORDER BY lr.created_at DESC
            ");
            $stmt_my->execute([':uid' => $user_id]);
            $my_leaves = $stmt_my->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt_types = $db->query("SELECT * FROM leave_quotas");
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
        $this->requireMutation();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
$leaveModel = new LeaveModel($db);
            $notifModel = new NotificationModel($db);
            
            $user_id = $_SESSION['user']['id'];
            $hospital_id = $_SESSION['user']['hospital_id'];
            $user_name = $_SESSION['user']['name'];
            $role = $_SESSION['user']['role'];
            $budget_year = $this->getCurrentBudgetYear();
            
            $leave_type_id = (int)($_POST['leave_type_id'] ?? 0);
            $start_date = trim((string)($_POST['start_date'] ?? ''));
            $end_date = trim((string)($_POST['end_date'] ?? ''));
            $reason = trim((string)($_POST['reason'] ?? ''));

            $startObj = DateTime::createFromFormat('Y-m-d', $start_date);
            $endObj = DateTime::createFromFormat('Y-m-d', $end_date);
            $validStart = $startObj && $startObj->format('Y-m-d') === $start_date;
            $validEnd = $endObj && $endObj->format('Y-m-d') === $end_date;

            if ($leave_type_id <= 0 || !$validStart || !$validEnd) {
                $_SESSION['error_msg'] = "ข้อมูลประเภทการลาหรือวันที่ลาไม่ถูกต้อง";
                header("Location: index.php?c=leave&a=index");
                exit;
            }

            if ($reason === '' || mb_strlen($reason, 'UTF-8') > 1000) {
                $_SESSION['error_msg'] = "กรุณาระบุเหตุผลการลาไม่เกิน 1,000 ตัวอักษร";
                header("Location: index.php?c=leave&a=index");
                exit;
            }

            if ($startObj > $endObj) {
                $_SESSION['error_msg'] = "วันที่สิ้นสุดการลา ต้องไม่น้อยกว่าวันที่เริ่มต้น";
                header("Location: index.php?c=leave&a=index"); exit;
            }

            $stmt_overlap = $db->prepare("
                SELECT id FROM leave_requests 
                WHERE user_id = ? AND status IN ('PENDING', 'APPROVED', 'CANCEL_REQUESTED') 
                AND start_date <= ? AND end_date >= ?
            ");
            $stmt_overlap->execute([$user_id, $end_date, $start_date]);
            if ($stmt_overlap->rowCount() > 0) {
                $_SESSION['error_msg'] = "คุณมียื่นใบลาในช่วงเวลาดังกล่าวไว้แล้ว (รอพิจารณา หรือ อนุมัติแล้ว)";
                header("Location: index.php?c=leave&a=index"); exit;
            }
            
            $actual_working_days = $leaveModel->calculateWorkingDays($start_date, $end_date, $hospital_id);

            $balances = $leaveModel->getUserLeaveBalances($user_id, $budget_year);
            $remaining = 0; $leave_name = '';
            foreach ($balances as $b) {
                if ($b['leave_type_id'] == $leave_type_id) { 
                    $remaining = $b['remaining']; 
                    $leave_name = $b['leave_type_name']; 
                    break; 
                }
            }

            $stmt_emp = $db->prepare("SELECT employee_type, start_date FROM users WHERE id = ?");
            $stmt_emp->execute([$user_id]);
            $emp_data = $stmt_emp->fetch(PDO::FETCH_ASSOC);
            $emp_type = $emp_data['employee_type'] ?? '';
            $start_date_emp = $emp_data['start_date'];

            $is_official = (strpos($emp_type, 'ข้าราชการ') !== false || strpos($emp_type, 'พนักงานส่วนท้องถิ่น') !== false);
            $is_mission = (strpos($emp_type, 'ภารกิจ') !== false);
            $is_general = (strpos($emp_type, 'ทั่วไป') !== false);

            $today = new DateTime(date('Y-m-d'));
            $leave_start_dt = new DateTime($start_date);
            $leave_end_dt = new DateTime($end_date);
            
            $advance_notice_days = $today->diff($leave_start_dt)->invert ? -$today->diff($leave_start_dt)->days : $today->diff($leave_start_dt)->days;

            if ($leave_name === 'ลากิจส่วนตัว') {
                if ($is_general) {
                    $_SESSION['error_msg'] = "ระเบียบการลา: พนักงานจ้างทั่วไป ไม่มีสิทธิลากิจส่วนตัว";
                    header("Location: index.php?c=leave&a=index"); exit;
                }
            } 
            elseif ($leave_name === 'ลาพักผ่อน') {
                if ($is_general) {
                    $_SESSION['error_msg'] = "ระเบียบการลา: พนักงานจ้างทั่วไป ไม่มีสิทธิลาพักผ่อน";
                    header("Location: index.php?c=leave&a=index"); exit;
                }
                if (!$is_official && $start_date_emp) {
                    $emp_start = new DateTime($start_date_emp);
                    $months_worked = $today->diff($emp_start)->m + ($today->diff($emp_start)->y * 12);
                    if ($months_worked < 6) {
                        $_SESSION['error_msg'] = "ระเบียบการลา: ต้องปฏิบัติงานครบ 6 เดือนก่อน จึงจะมีสิทธิลาพักผ่อน";
                        header("Location: index.php?c=leave&a=index"); exit;
                    }
                }
            } 
            elseif (strpos($leave_name, 'อุปสมบท') !== false || strpos($leave_name, 'ฮัจย์') !== false) {
                if ($is_general) {
                    $_SESSION['error_msg'] = "ระเบียบการลา: พนักงานจ้างทั่วไป ไม่มีสิทธิลาอุปสมบท/ประกอบพิธีฮัจย์";
                    header("Location: index.php?c=leave&a=index"); exit;
                }
                if ($advance_notice_days < 60) {
                    $_SESSION['error_msg'] = "ระเบียบการลา: การลาอุปสมบท/ฮัจย์ ต้องยื่นล่วงหน้าไม่น้อยกว่า 60 วัน";
                    header("Location: index.php?c=leave&a=index"); exit;
                }
            } 
            elseif (strpos($leave_name, 'ภริยาคลอด') !== false) {
                if (!$is_official) {
                    $_SESSION['error_msg'] = "ระเบียบการลา: สิทธิลาไปช่วยเหลือภริยาคลอดบุตร เฉพาะข้าราชการ/พนักงานส่วนท้องถิ่นเท่านั้น";
                    header("Location: index.php?c=leave&a=index"); exit;
                }
                if ($actual_working_days > 15) {
                    $_SESSION['error_msg'] = "ระเบียบการลา: ลาไปช่วยเหลือภริยาคลอดบุตร ติดต่อกันได้ไม่เกิน 15 วันทำการ";
                    header("Location: index.php?c=leave&a=index"); exit;
                }
            } 
            elseif (strpos($leave_name, 'คลอดบุตร') !== false) {
                $total_days = $leave_start_dt->diff($leave_end_dt)->days + 1; 
                if ($total_days > 90) {
                    $_SESSION['error_msg'] = "ระเบียบการลา: ลาคลอดบุตร ลาได้ไม่เกิน 90 วัน (นับรวมวันหยุดราชการแล้ว)";
                    header("Location: index.php?c=leave&a=index"); exit;
                }
                $actual_working_days = $total_days;
            } 
            elseif (strpos($leave_name, 'เตรียมพล') !== false || strpos($leave_name, 'คัดเลือก') !== false) {
                if ($advance_notice_days < 2) {
                    $_SESSION['error_msg'] = "ระเบียบการลา: ลาเข้ารับการคัดเลือก/เตรียมพล ต้องรายงานตัวยื่นล่วงหน้าไม่น้อยกว่า 48 ชั่วโมง";
                    header("Location: index.php?c=leave&a=index"); exit;
                }
            }

            if ($actual_working_days <= 0) {
                $_SESSION['error_msg'] = "ช่วงเวลาที่คุณเลือกตรงกับวันหยุดทั้งหมด ไม่จำเป็นต้องยื่นใบลา";
                header("Location: index.php?c=leave&a=index"); exit;
            }

            if (in_array($leave_name, ['ลาพักผ่อน', 'ลากิจส่วนตัว', 'ลาป่วย']) && $actual_working_days > $remaining) {
                $_SESSION['error_msg'] = "โควตา $leave_name ของคุณไม่เพียงพอ (เหลือ $remaining วัน แต่ขอลา $actual_working_days วัน)";
                header("Location: index.php?c=leave&a=index"); exit;
            }

            $has_med_cert = 0;
            $med_cert_path = null;

            if ($leave_name === 'ลาป่วย') {
                $upload = $_FILES['med_cert_file'] ?? null;
                $hasUpload = is_array($upload)
                    && (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

                if ($actual_working_days >= 3 && !$hasUpload) {
                    $_SESSION['error_msg'] = "การลาป่วยติดต่อกัน 3 วันทำการขึ้นไป ต้องอัปโหลดไฟล์ใบรับรองแพทย์ด้วย";
                    header("Location: index.php?c=leave&a=index");
                    exit;
                }

                if ($hasUpload) {
                    try {
                        $stored = SecureUpload::store(
                            $upload,
                            'storage/private/med_certs',
                            [
                                'image/jpeg' => 'jpg',
                                'image/png' => 'png',
                                'application/pdf' => 'pdf',
                            ],
                            5 * 1024 * 1024,
                            'cert_' . $user_id,
                            true
                        );

                        $has_med_cert = 1;
                        $med_cert_path = (string)$stored['path'];
                    } catch (Throwable $e) {
                        error_log('Medical certificate upload rejected: ' . $e->getMessage());
                        $_SESSION['error_msg'] = "อัปโหลดใบรับรองแพทย์ไม่สำเร็จ รองรับเฉพาะ JPG, PNG หรือ PDF ขนาดไม่เกิน 5 MB";
                        header("Location: index.php?c=leave&a=index");
                        exit;
                    }
                }
            }

            if ($leaveModel->addLeaveRequest([
                'user_id' => $user_id, 'leave_type_id' => $leave_type_id, 
                'start_date' => $start_date, 'end_date' => $end_date, 
                'num_days' => $actual_working_days, 'reason' => $reason, 
                'has_med_cert' => $has_med_cert, 'med_cert_path' => $med_cert_path
            ])) {
                // 🌟 บันทึก Log: ส่งคำขอลา
                LogsController::addLog($db, $user_id, LogsController::ACTION_CREATE, "ยื่นคำร้องขอ{$leave_name} จำนวน {$actual_working_days} วัน");
                $_SESSION['success_msg'] = "ยื่นใบลาสำเร็จ จำนวน $actual_working_days วัน (รอการพิจารณา)";
                
                $stmt = $db->prepare("SELECT id FROM users WHERE hospital_id = ? AND role IN ('DIRECTOR', 'ADMIN', 'SUPERADMIN')");
                $stmt->execute([$hospital_id]);
                $approvers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach($approvers as $a) {
                    $notifModel->addNotification($a['id'], 'INFO', 'ใบลาใหม่รออนุมัติ', "{$user_name} ขอ{$leave_name} {$actual_working_days} วัน", "index.php?c=leave&a=approvals");
                }
                $this->sendLineNotify($db, "\n📝 ใบลาใหม่รออนุมัติ\nจาก: {$user_name}\nประเภท: {$leave_name}\nจำนวน: {$actual_working_days} วัน");

            } else {
                if ($med_cert_path && is_file($med_cert_path)) {
                    @unlink($med_cert_path);
                }
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลลงระบบ";
            }
        }
        header("Location: index.php?c=leave&a=index"); exit;
    }

    // ==========================================
    // 🔒 ดูใบรับรองแพทย์ผ่าน Authorization เท่านั้น
    // ==========================================
    public function medical_certificate() {
        security_start_session();

        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }

        $leaveId = (int)($_GET['id'] ?? 0);
        if ($leaveId <= 0) {
            http_response_code(404);
            exit('File not found.');
        }

        $db = (new Database())->getConnection();
        $stmt = $db->prepare("
            SELECT lr.id, lr.user_id, lr.med_cert_path, u.hospital_id
            FROM leave_requests lr
            JOIN users u ON u.id = lr.user_id
            WHERE lr.id = ?
            LIMIT 1
        ");
        $stmt->execute([$leaveId]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$leave || empty($leave['med_cert_path'])) {
            http_response_code(404);
            exit('File not found.');
        }

        $viewerId = (int)($_SESSION['user']['id'] ?? 0);
        $viewerRole = strtoupper((string)($_SESSION['user']['role'] ?? ''));
        $viewerHospital = (int)($_SESSION['user']['hospital_id'] ?? 0);
        $ownerId = (int)$leave['user_id'];
        $ownerHospital = (int)$leave['hospital_id'];

        $canView = $viewerId === $ownerId;
        if (in_array($viewerRole, ['DIRECTOR', 'SCHEDULER'], true) && $viewerHospital === $ownerHospital) {
            $canView = true;
        }
        if (in_array($viewerRole, ['ADMIN', 'SUPERADMIN', 'HR'], true)) {
            $canView = true;
        }

        if (!$canView) {
            http_response_code(403);
            exit('Forbidden.');
        }

        $storedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$leave['med_cert_path']);
        $allowedRoots = [
            realpath('storage/private/med_certs'),
            realpath('uploads/med_certs'),
        ];

        $realFile = realpath($storedPath);
        $pathAllowed = false;
        if ($realFile !== false) {
            foreach ($allowedRoots as $root) {
                if ($root !== false && ($realFile === $root || str_starts_with($realFile, $root . DIRECTORY_SEPARATOR))) {
                    $pathAllowed = true;
                    break;
                }
            }
        }

        if (!$pathAllowed || !is_file($realFile) || !is_readable($realFile)) {
            http_response_code(404);
            exit('File not found.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($realFile);
        $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($mime, $allowedMime, true)) {
            http_response_code(415);
            exit('Unsupported file type.');
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($realFile));
        header('Content-Disposition: inline; filename="medical-certificate-' . $leaveId . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($realFile);
        exit;
    }

    // ==========================================
    // 🌟 ยกเลิกใบลา (ปรับปรุง: ลบทิ้งเพื่อล้างประวัติ)
    // ==========================================
    public function cancel() {
        $this->requireMutation();
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_id'])) {
            $db = (new Database())->getConnection();
$notifModel = new NotificationModel($db);
            
            $request_id = $_POST['request_id'];
            $user_id = $_SESSION['user']['id'];
            $user_name = $_SESSION['user']['name'];
            $hospital_id = $_SESSION['user']['hospital_id'];

            $stmt = $db->prepare("SELECT lr.*, lq.leave_type FROM leave_requests lr JOIN leave_quotas lq ON lr.leave_type_id = lq.id WHERE lr.id = ? AND lr.user_id = ?");
            $stmt->execute([$request_id, $user_id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($leave) {
                if ($leave['status'] == 'PENDING') {
                    // 🌟 ถ้ารออนุมัติอยู่ -> ลบข้อมูลออกจากประวัติ (ฐานข้อมูล) ทันที
                    $stmt_del = $db->prepare("DELETE FROM leave_requests WHERE id = ?");
                    $stmt_del->execute([$request_id]);
                    
                    // 🌟 บันทึก Log: ลบคำขอที่ยังไม่อนุมัติ
                    LogsController::addLog($db, $user_id, LogsController::ACTION_DELETE, "ยกเลิกและลบคำขอใบ{$leave['leave_type']} (ID: {$request_id}) ด้วยตนเอง");
                    $_SESSION['success_msg'] = "ยกเลิกใบลาและล้างประวัติการลาเรียบร้อยแล้ว";
                    
                } 
                elseif ($leave['status'] == 'APPROVED') {
                    // 🌟 ถ้าอนุมัติไปแล้ว -> เปลี่ยนเป็น CANCEL_REQUESTED เพื่อรอให้ ผอ. คืนโควตา
                    $stmt_up = $db->prepare("UPDATE leave_requests SET status = 'CANCEL_REQUESTED' WHERE id = ?");
                    $stmt_up->execute([$request_id]);
                    
                    // 🌟 บันทึก Log: ส่งคำขอยกเลิก
                    LogsController::addLog($db, $user_id, LogsController::ACTION_UPDATE, "ส่งคำขอยกเลิกใบ{$leave['leave_type']}ที่อนุมัติแล้ว (ID: {$request_id})");
                    $_SESSION['success_msg'] = "ส่งคำขอยกเลิกใบลาแล้ว กรุณารอหัวหน้าพิจารณาเพื่อคืนโควตาวันลา";
                    
                    $stmt_app = $db->prepare("SELECT id FROM users WHERE hospital_id = ? AND role IN ('DIRECTOR', 'ADMIN', 'SUPERADMIN')");
                    $stmt_app->execute([$hospital_id]);
                    $approvers = $stmt_app->fetchAll(PDO::FETCH_ASSOC);
                    foreach($approvers as $a) {
                        $notifModel->addNotification($a['id'], 'WARNING', 'มีคำขอยกเลิกใบลา', "{$user_name} ขอยกเลิกใบ{$leave['leave_type']} ที่เคยอนุมัติไปแล้ว", "index.php?c=leave&a=approvals");
                    }
                    $this->sendLineNotify($db, "\n⚠️ มีคำขอยกเลิกใบลา\nพนักงาน: {$user_name}\nรายการ: ใบ{$leave['leave_type']}\nโปรดเข้าสู่ระบบเพื่อพิจารณาการยกเลิก");
                }
            } else {
                $_SESSION['error_msg'] = "ไม่สามารถดำเนินการได้ หรือไม่พบข้อมูลใบลานี้";
            }
        }
        header("Location: index.php?c=leave&a=index"); exit;
    }

    // ==========================================
    // 🌟 หน้าอนุมัติการลา (Approvals)
    // ==========================================
    public function approvals() {
        if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['SUPERADMIN', 'ADMIN', 'DIRECTOR', 'SCHEDULER'])) {
            header("Location: index.php?c=leave"); exit;
        }
        $db = (new Database())->getConnection();
$role = $_SESSION['user']['role'];
        $hospital_id = $_SESSION['user']['hospital_id'];

        $query = "SELECT lr.*, lq.leave_type, u.name as user_name, u.employee_type, h.name as hospital_name
                  FROM leave_requests lr
                  JOIN users u ON lr.user_id = u.id 
                  JOIN leave_quotas lq ON lr.leave_type_id = lq.id
                  LEFT JOIN hospitals h ON u.hospital_id = h.id
                  WHERE lr.status IN ('PENDING', 'CANCEL_REQUESTED') ";

        if (!in_array($role, ['SUPERADMIN', 'ADMIN'])) {
            $query .= " AND u.hospital_id = :hosp_id ";
        }
        $query .= " ORDER BY lr.created_at ASC";

        $stmt = $db->prepare($query);
        if (!in_array($role, ['SUPERADMIN', 'ADMIN'])) {
            $stmt->bindValue(':hosp_id', $hospital_id);
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
        $this->requireMutation();
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
            $db = (new Database())->getConnection();
$notifModel = new NotificationModel($db);

            $request_id = $_POST['request_id']; 
            $action = $_POST['action']; 
            $approver_id = $_SESSION['user']['id'];
            
            // ดึงชื่อคนที่ยื่นเรื่องมาเพื่อเก็บ Log ให้อ่านง่าย
            $stmt = $db->prepare("SELECT lr.*, u.name as request_user_name FROM leave_requests lr LEFT JOIN users u ON lr.user_id = u.id WHERE lr.id = ?");
            $stmt->execute([$request_id]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($req) {
                $stmt_type = $db->prepare("SELECT leave_type FROM leave_quotas WHERE id = ?");
                $stmt_type->execute([$req['leave_type_id']]);
                $leave_type_name = $stmt_type->fetchColumn() ?: 'การลา';
                $request_user_name = $req['request_user_name'] ?? ('User ID: ' . $req['user_id']);

                if ($req['status'] === 'PENDING') {
                    if ($action === 'APPROVED') {
                        $db->prepare("UPDATE leave_requests SET status = 'APPROVED', approved_by = ?, approved_at = NOW() WHERE id = ?")->execute([$approver_id, $request_id]);
                        $db->prepare("UPDATE leave_balances SET used_days = used_days + ? WHERE user_id = ? AND budget_year = ? AND leave_type_id = ?")
                           ->execute([$req['num_days'], $req['user_id'], $this->getCurrentBudgetYear(), $req['leave_type_id']]);
                        
                        // 🌟 บันทึก Log: อนุมัติใบลา
                        LogsController::addLog($db, $approver_id, LogsController::ACTION_UPDATE, "อนุมัติใบ{$leave_type_name} ของคุณ {$request_user_name} (Ref ID: {$request_id})");
                        
                        $_SESSION['success_msg'] = "อนุมัติใบลาและตัดยอดคงเหลือเรียบร้อยแล้ว";
                        $notifModel->addNotification($req['user_id'], 'SUCCESS', "ผลการพิจารณาใบลา", "ใบ{$leave_type_name} ของคุณได้รับคำสั่ง: อนุมัติแล้ว", "index.php?c=leave");
                        
                    } elseif ($action === 'REJECTED') {
                        $db->prepare("UPDATE leave_requests SET status = 'REJECTED', approved_by = ?, approved_at = NOW() WHERE id = ?")->execute([$approver_id, $request_id]);
                        
                        // 🌟 บันทึก Log: ปฏิเสธใบลา
                        LogsController::addLog($db, $approver_id, LogsController::ACTION_UPDATE, "ไม่อนุมัติใบ{$leave_type_name} ของคุณ {$request_user_name} (Ref ID: {$request_id})");
                        
                        $_SESSION['success_msg'] = "ปฏิเสธใบลาเรียบร้อยแล้ว"; 
                        $notifModel->addNotification($req['user_id'], 'DANGER', "ผลการพิจารณาใบลา", "ใบ{$leave_type_name} ของคุณได้รับคำสั่ง: ไม่อนุมัติ", "index.php?c=leave");
                    }
                } 
                elseif ($req['status'] === 'CANCEL_REQUESTED') {
                    if ($action === 'APPROVE_CANCEL') {
                        // 🌟 อนุมัติให้ยกเลิก -> ลบประวัติใบลาออกจากฐานข้อมูล และคืนโควตา
                        $db->prepare("DELETE FROM leave_requests WHERE id = ?")->execute([$request_id]);
                        $db->prepare("UPDATE leave_balances SET used_days = used_days - ? WHERE user_id = ? AND budget_year = ? AND leave_type_id = ?")
                           ->execute([$req['num_days'], $req['user_id'], $this->getCurrentBudgetYear(), $req['leave_type_id']]);
                        
                        // 🌟 บันทึก Log: อนุมัติให้ยกเลิกและคืนโควตา
                        LogsController::addLog($db, $approver_id, LogsController::ACTION_DELETE, "อนุมัติให้ยกเลิกและลบประวัติใบ{$leave_type_name} ของคุณ {$request_user_name} พร้อมคืนโควตาวันลา (Ref ID: {$request_id})");
                        
                        $_SESSION['success_msg'] = "อนุมัติการยกเลิก คืนโควตา และล้างประวัติวันลาเรียบร้อยแล้ว";
                        $notifModel->addNotification($req['user_id'], 'INFO', "แจ้งผลการยกเลิกใบลา", "หัวหน้าอนุมัติการยกเลิกใบ{$leave_type_name} คืนสิทธิ์ให้คุณและล้างประวัติแล้ว", "index.php?c=leave");
                        
                    } elseif ($action === 'REJECT_CANCEL') {
                        $db->prepare("UPDATE leave_requests SET status = 'APPROVED' WHERE id = ?")->execute([$request_id]);
                        
                        // 🌟 บันทึก Log: ไม่อนุมัติให้ยกเลิก
                        LogsController::addLog($db, $approver_id, LogsController::ACTION_UPDATE, "ไม่อนุมัติคำขอยกเลิกใบ{$leave_type_name} ของคุณ {$request_user_name} (Ref ID: {$request_id})");
                        
                        $_SESSION['success_msg'] = "ปฏิเสธการยกเลิกใบลา (ใบลาคงสถานะอนุมัติตามเดิม)";
                        $notifModel->addNotification($req['user_id'], 'WARNING', "แจ้งผลการยกเลิกใบลา", "หัวหน้า ไม่อนุมัติ การยกเลิกใบ{$leave_type_name} ของคุณ", "index.php?c=leave");
                    }
                }
            } else { $_SESSION['error_msg'] = "ไม่พบข้อมูลใบลา"; }
        }
        header("Location: index.php?c=leave&a=approvals"); exit;
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
        $role = $_SESSION['user']['role'];
        $budget_year = isset($_GET['year']) ? $_GET['year'] : $this->getCurrentBudgetYear();
        
        $staffs = in_array($role, ['SUPERADMIN', 'ADMIN']) ? $userModel->getAllStaff() : $userModel->getUsersByHospital($hospital_id);
        
        // 🌟 แก้ไข: ดึงข้อมูลพนักงานที่แก้ไขจากฐานข้อมูล (ป้องกันบัคเปลี่ยนคนตอนบันทึก)
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_balance') {
            $this->requireMutation();
            $stmt_bal = $db->prepare("SELECT user_id, budget_year FROM leave_balances WHERE id = ?");
            $stmt_bal->execute([$_POST['balance_id']]);
            $bal = $stmt_bal->fetch(PDO::FETCH_ASSOC);
            
            $target_user_id = $bal ? $bal['user_id'] : (count($staffs) > 0 ? $staffs[0]['id'] : null);
            $target_budget_year = $bal ? $bal['budget_year'] : $budget_year;
            
            $target_user = $target_user_id ? $userModel->getUserById($target_user_id) : null;
            $user_name = $target_user ? $target_user['name'] : '';

            $leaveModel->updateLeaveBalance($_POST['balance_id'], floatval($_POST['quota_days']), floatval($_POST['carried_over_days']), floatval($_POST['used_days']));
            
            // 🌟 บันทึก Log: จัดการแก้โควตาด้วยตัวเอง
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "แก้ไขโควตาวันลาด้วยมือให้ {$user_name} (Balance ID: {$_POST['balance_id']})");
            
            $_SESSION['success_msg'] = "อัปเดตข้อมูลวันลาของ {$user_name} เรียบร้อยแล้ว"; 
            
            header("Location: index.php?c=leave&a=manage&user_id={$target_user_id}&year={$target_budget_year}"); 
            exit;
        }

        $target_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : (count($staffs) > 0 ? $staffs[0]['id'] : null);
        $balances = []; $target_user = null;

        if ($target_user_id) {
            $target_user = $userModel->getUserById($target_user_id);
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
        $role = $_SESSION['user']['role'];
        $budget_year = $this->getCurrentBudgetYear();

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
        $this->requireMutation();
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user'])) {
            $db = (new Database())->getConnection(); 
            $leaveModel = new LeaveModel($db);
            
            $user_id = $_POST['user_id'];
            $brought_forward = floatval($_POST['brought_forward']);
            $budget_year = $this->getCurrentBudgetYear();
            
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
        $this->requireMutation();
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user'])) {
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
                error_log('LeaveController error: ' . $e->getMessage());
                $_SESSION['error_msg'] = "ไม่สามารถดำเนินการข้อมูลวันลาได้ กรุณาลองใหม่";
            }
        }
        header("Location: index.php?c=leave&a=balances"); exit;
    }

    // ==========================================
    // 🌟 หน้าอื่นๆ (ตั้งค่าและรายงาน)
    // ==========================================
    public function settings() {
        if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['SUPERADMIN', 'ADMIN'])) { header("Location: index.php?c=leave"); exit; }
        
        $db = (new Database())->getConnection(); $leaveModel = new LeaveModel($db);
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['quotas'])) {
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

    public function print() {
        if (!isset($_SESSION['user']) || empty($_GET['id'])) { 
            header("Location: index.php?c=leave"); exit; 
        }
        $db = (new Database())->getConnection();
        $request_id = $_GET['id'];
        $stmt = $db->prepare("SELECT lr.*, lq.leave_type as leave_type_name, u.name as user_name, u.employee_type, h.name as hospital_name 
                              FROM leave_requests lr JOIN leave_quotas lq ON lr.leave_type_id = lq.id JOIN users u ON lr.user_id = u.id JOIN hospitals h ON u.hospital_id = h.id WHERE lr.id = ?");
        $stmt->execute([$request_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$leave) { die("ไม่พบข้อมูลใบลา"); }
        if ($leave['user_id'] != $_SESSION['user']['id'] && !in_array($_SESSION['user']['role'], ['SUPERADMIN', 'ADMIN', 'DIRECTOR', 'SCHEDULER'])) {
            die("คุณไม่มีสิทธิ์เข้าถึงเอกสารนี้");
        }

        $leaveModel = new LeaveModel($db);
        $budget_year = $this->getCurrentBudgetYear();
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