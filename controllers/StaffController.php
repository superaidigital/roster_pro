<?php
// ไฟล์: controllers/StaffController.php

require_once 'config/database.php';
require_once 'models/UserModel.php';
require_once 'models/HospitalModel.php';
require_once 'models/PayRateModel.php';
require_once 'controllers/LogsController.php'; 

class StaffController {
    
    // ตรวจสอบสิทธิ์การจัดการบุคลากร
    private function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));
        $allowed = ['SCHEDULER', 'DIRECTOR', 'HR', 'ADMIN', 'SUPERADMIN'];

        if (!isset($_SESSION['user']) || !in_array($role, $allowed, true)) {
            $_SESSION['error_msg'] = "คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการบุคลากร";
            header("Location: index.php?c=dashboard");
            exit;
        }
    }

    private function getCsrfToken() {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private function verifyCsrf($redirect = 'index.php?c=staff') {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $postedToken = $_POST['csrf_token'] ?? '';

        if (!is_string($sessionToken) || !is_string($postedToken) ||
            $sessionToken === '' || $postedToken === '' ||
            !hash_equals($sessionToken, $postedToken)) {
            $_SESSION['error_msg'] = "คำขอหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่";
            header("Location: " . $redirect);
            exit;
        }
    }

    private function currentRole() {
        return strtoupper((string)($_SESSION['user']['role'] ?? ''));
    }

    private function isGlobalAdmin() {
        return in_array($this->currentRole(), ['ADMIN', 'SUPERADMIN', 'HR'], true);
    }

    private function canManageTarget(array $target) {
        if ($this->isGlobalAdmin()) return true;

        $myHospital = (int)($_SESSION['user']['hospital_id'] ?? 0);
        if ((int)($target['hospital_id'] ?? 0) !== $myHospital) return false;

        $targetRole = strtoupper((string)($target['role'] ?? ''));
        $role = $this->currentRole();

        if ($role === 'SCHEDULER' && in_array($targetRole, ['DIRECTOR', 'HR', 'ADMIN', 'SUPERADMIN'], true)) {
            return false;
        }

        if ($role === 'DIRECTOR' && in_array($targetRole, ['HR', 'ADMIN', 'SUPERADMIN'], true)) {
            return false;
        }

        return true;
    }

    private function jsonCsrfValid(array $data) {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $postedToken = $data['csrf_token'] ?? '';
        return is_string($sessionToken) && is_string($postedToken)
            && $sessionToken !== '' && $postedToken !== ''
            && hash_equals($sessionToken, $postedToken);
    }

    // หน้าหลัก
    public function index() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        
        $userModel = new UserModel($db);
        $hospitalModel = new HospitalModel($db);
        $payRateModel = new PayRateModel($db);

        $current_role = strtoupper($_SESSION['user']['role']);
        $my_hosp_id = $_SESSION['user']['hospital_id'];
        
        $is_admin_level = in_array($current_role, ['ADMIN', 'SUPERADMIN', 'HR', 'DIRECTOR']);

        // หากเป็นผู้จัดเวร ให้เห็นเฉพาะบุคลากรในหน่วยงานตัวเอง
        if ($is_admin_level) {
            $staff_list = $userModel->getAllUsers();
            $hospitals_list = $hospitalModel->getAllHospitals();
        } else {
            $staff_list = $userModel->getUsersByHospital($my_hosp_id);
            $hospitals_list = [$hospitalModel->getHospitalById($my_hosp_id)];
        }

        $pay_rates = $payRateModel->getAllRates();
        $csrf_token = $this->getCsrfToken();

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/staff/index.php';
        echo "</main></div></body></html>";
    }
    
    // เพิ่มบุคลากร
    public function add() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->verifyCsrf();
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            
            $current_role = strtoupper($_SESSION['user']['role']);
            $is_global_admin = in_array($current_role, ['ADMIN', 'SUPERADMIN', 'HR']);

            $data = [
                'hospital_id' => $is_global_admin ? ($_POST['hospital_id'] ?? null) : $_SESSION['user']['hospital_id'],
                'name' => trim($_POST['name'] ?? ''),
                'username' => trim($_POST['username'] ?? ''),
                'password' => !empty($_POST['password']) ? $_POST['password'] : '123456', // รหัสเริ่มต้น
                'role' => strtoupper($_POST['role'] ?? 'STAFF'),
                'pay_rate_id' => !empty($_POST['pay_rate_id']) ? $_POST['pay_rate_id'] : null,
                'position' => trim($_POST['position'] ?? ''), 
                'employee_type' => trim($_POST['employee_type'] ?? ''),
                'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                'phone' => trim($_POST['phone'] ?? ''),
                
                // 🌟 เพิ่มการรับค่า 3 ช่องที่หายไป
                'id_card' => trim($_POST['id_card'] ?? ''),
                'position_number' => trim($_POST['position_number'] ?? ''),
                'type' => trim($_POST['type'] ?? ''),

                'color_theme' => 'success'
            ];

            // ป้องกันการแอบอ้างสิทธิ์ (Privilege Escalation)
            if (!$is_global_admin) {
                // ผอ. และ ผู้จัดเวร ไม่สามารถตั้งค่าใครเป็นแอดมินส่วนกลางได้
                if (in_array($data['role'], ['ADMIN', 'SUPERADMIN', 'HR'])) {
                    $data['role'] = 'STAFF'; 
                }
                // ผู้จัดเวร ไม่สามารถตั้งค่าใครเป็น ผอ. ได้
                if ($current_role === 'SCHEDULER' && $data['role'] === 'DIRECTOR') {
                    $data['role'] = 'STAFF';
                }
            }

            if (!empty($data['username']) && $userModel->checkUsernameExists($data['username'])) {
                $_SESSION['error_msg'] = "Username นี้มีผู้ใช้งานแล้ว โปรดใช้ชื่ออื่น";
            } else {
                if ($userModel->addUser($data)) {
                    // 🌟 บันทึก Log
                    LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_CREATE, "เพิ่มบุคลากรภายในหน่วยงาน: " . $data['name']);
                    $_SESSION['success_msg'] = "เพิ่มข้อมูลบุคลากรสำเร็จ";
                } else {
                    $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
                }
            }
        }
        header("Location: index.php?c=staff");
        exit;
    }

    // 🌟 แก้ไขบุคลากร (ปรับปรุงสิทธิ์การเข้าถึงให้ ผอ.)
    public function edit() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->verifyCsrf();
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            
            $id = $_POST['id'];
            $current_role = strtoupper($_SESSION['user']['role']);
            $is_global_admin = in_array($current_role, ['ADMIN', 'SUPERADMIN', 'HR']);

            $data = [
                'id' => $id,
                'hospital_id' => $is_global_admin ? ($_POST['hospital_id'] ?? null) : $_SESSION['user']['hospital_id'],
                'name' => trim($_POST['name'] ?? ''),
                'role' => strtoupper($_POST['role'] ?? 'STAFF'),
                'pay_rate_id' => !empty($_POST['pay_rate_id']) ? $_POST['pay_rate_id'] : null,
                'position' => trim($_POST['position'] ?? ''), 
                'employee_type' => trim($_POST['employee_type'] ?? ''),
                'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                'phone' => trim($_POST['phone'] ?? ''),
                
                // 🌟 เพิ่มการรับค่า 3 ช่องที่หายไป
                'id_card' => trim($_POST['id_card'] ?? ''),
                'position_number' => trim($_POST['position_number'] ?? ''),
                'type' => trim($_POST['type'] ?? ''),

                'color_theme' => 'success'
            ];

            if (!empty($_POST['username'])) {
                $data['username'] = trim($_POST['username']);
            }
            if (!empty($_POST['password'])) {
                $data['password'] = $_POST['password'];
            }

            try {
                $existing_user = $userModel->getUserById($id);
                if (!$existing_user) {
                    $_SESSION['error_msg'] = "ไม่พบข้อมูล";
                    header("Location: index.php?c=staff");
                    exit;
                }

                $can_edit = $this->canManageTarget($existing_user);
                if (!$can_edit) {
                    $_SESSION['error_msg'] = "ปฏิเสธ: คุณไม่มีสิทธิ์แก้ไขบุคลากรรายนี้";
                }
                
                // ตรวจสอบสิทธิ์เฉพาะผู้ที่ไม่ใช่แอดมินส่วนกลาง
                if (!$is_global_admin) {
                    // ผู้จัดเวร (SCHEDULER) ห้ามแก้ระดับที่สูงกว่า
                    if ($current_role === 'SCHEDULER' && in_array($existing_user['role'], ['ADMIN', 'SUPERADMIN', 'HR', 'DIRECTOR'])) {
                        $can_edit = false;
                        $_SESSION['error_msg'] = "ปฏิเสธ: ผู้จัดเวรไม่สามารถแก้ไขข้อมูลผู้อำนวยการ/แอดมินได้";
                    } 
                    // ผอ. (DIRECTOR) ห้ามแก้แอดมินส่วนกลาง แต่แก้ ผอ. ด้วยกัน (ตัวเอง) หรือจัดเวรได้
                    elseif ($current_role === 'DIRECTOR' && in_array($existing_user['role'], ['ADMIN', 'SUPERADMIN', 'HR'])) {
                        $can_edit = false;
                        $_SESSION['error_msg'] = "ปฏิเสธ: ผู้อำนวยการไม่สามารถแก้ไขข้อมูลแอดมินส่วนกลางได้";
                    }

                    // ป้องกันการแอบเปลี่ยน Role เป็น Admin
                    if (in_array($data['role'], ['ADMIN', 'SUPERADMIN', 'HR'])) {
                        $data['role'] = $existing_user['role']; // บังคับคืนค่าเดิม
                    }
                }

                if ($can_edit) {
                    if ($userModel->updateUser($data)) {
                        // 🌟 บันทึก Log
                        LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "แก้ไขข้อมูลบุคลากร: " . $data['name']);
                        $_SESSION['success_msg'] = "อัปเดตข้อมูลสำเร็จ";
                    } else {
                        $_SESSION['error_msg'] = "ไม่สามารถบันทึกข้อมูลได้";
                    }
                }
            } catch (Exception $e) {
                error_log("Staff edit error: " . $e->getMessage());
                $_SESSION['error_msg'] = "ไม่สามารถอัปเดตข้อมูลได้";
            }
        }
        header("Location: index.php?c=staff");
        exit;
    }

    // ลบเดี่ยว
    public function delete() {
        $this->checkAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?c=staff");
            exit;
        }

        $this->verifyCsrf();

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id || $id === (int)$_SESSION['user']['id']) {
            $_SESSION['error_msg'] = "ไม่สามารถลบบัญชีนี้ได้";
            header("Location: index.php?c=staff");
            exit;
        }

        $db = (new Database())->getConnection();
        $userModel = new UserModel($db);

        try {
            $target = $userModel->getUserById($id);
            if (!$target || !$this->canManageTarget($target)) {
                $_SESSION['error_msg'] = "ไม่พบข้อมูล หรือคุณไม่มีสิทธิ์ลบบุคลากรรายนี้";
            } elseif ($userModel->deleteUser($id)) {
                LogsController::addLog(
                    $db,
                    $_SESSION['user']['id'],
                    LogsController::ACTION_DELETE,
                    "ลบข้อมูลบุคลากร: " . ($target['name'] ?? "ID: $id")
                );
                $_SESSION['success_msg'] = "ลบข้อมูลสำเร็จ";
            } else {
                $_SESSION['error_msg'] = "ไม่สามารถลบข้อมูลได้";
            }
        } catch (Throwable $e) {
            error_log("Staff delete error: " . $e->getMessage());
            $_SESSION['error_msg'] = "ไม่สามารถลบข้อมูลได้";
        }

        header("Location: index.php?c=staff");
        exit;
    }

    // ลบหลายรายการ
    public function bulk_delete() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            $ids = json_decode($_POST['ids'] ?? '[]');
            
            $current_role = strtoupper($_SESSION['user']['role']);
            $is_global_admin = in_array($current_role, ['ADMIN', 'SUPERADMIN', 'HR']);

            if (is_array($ids) && count($ids) > 0) {
                $successCount = 0;
                foreach ($ids as $id) {
                    if ($id != $_SESSION['user']['id']) { 
                        $target = $userModel->getUserById($id);
                        $can_delete = $target && $this->canManageTarget($target);
                        
                        if (!$is_global_admin && $target) {
                            if ($current_role === 'SCHEDULER' && in_array($target['role'], ['ADMIN', 'SUPERADMIN', 'HR', 'DIRECTOR'])) $can_delete = false;
                            if ($current_role === 'DIRECTOR' && in_array($target['role'], ['ADMIN', 'SUPERADMIN', 'HR'])) $can_delete = false;
                        }

                        if ($can_delete && $userModel->deleteUser($id)) {
                            $successCount++;
                        }
                    }
                }
                if ($successCount > 0) {
                    // 🌟 บันทึก Log
                    LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_DELETE, "ลบข้อมูลบุคลากรแบบกลุ่ม จำนวน {$successCount} รายการ");
                    $_SESSION['success_msg'] = "ลบข้อมูลสำเร็จจำนวน {$successCount} รายการ";
                } else {
                    $_SESSION['error_msg'] = "ไม่สามารถลบรายการที่เลือกได้";
                }
            }
            header("Location: index.php?c=staff");
            exit();
        }
    }

    // เปิด/ปิด การใช้งาน (บัญชี)
    public function toggle() {
        $this->checkAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?c=staff");
            exit;
        }

        $this->verifyCsrf();

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);

        if (!$id || !in_array($status, [0, 1], true) || $id === (int)$_SESSION['user']['id']) {
            $_SESSION['error_msg'] = "คำขอเปลี่ยนสถานะไม่ถูกต้อง";
            header("Location: index.php?c=staff");
            exit;
        }

        $db = (new Database())->getConnection();
        $userModel = new UserModel($db);
        $target = $userModel->getUserById($id);

        if (!$target || !$this->canManageTarget($target)) {
            $_SESSION['error_msg'] = "ไม่พบข้อมูล หรือคุณไม่มีสิทธิ์เปลี่ยนสถานะบัญชีนี้";
        } elseif ($userModel->updateStatus($id, $status)) {
            $actionTxt = $status === 1 ? "เปิด" : "ระงับ";
            LogsController::addLog(
                $db,
                $_SESSION['user']['id'],
                LogsController::ACTION_UPDATE,
                "{$actionTxt}การใช้งานบัญชีบุคลากร ID: {$id}"
            );
            $_SESSION['success_msg'] = "เปลี่ยนสถานะสำเร็จ";
        } else {
            $_SESSION['error_msg'] = "ไม่สามารถเปลี่ยนสถานะได้";
        }

        header("Location: index.php?c=staff");
        exit;
    }

    // 🌟 ฟังก์ชันใหม่: เปิด/ปิด การแสดงชื่อในตารางเวร (Show in Roster)
    public function toggle_roster_status() {
        $this->checkAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->verifyCsrf();
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);

            if (!$id || !in_array($status, [0, 1], true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }

            $target = $userModel->getUserById($id);
            if (!$target || !$this->canManageTarget($target)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }

            try {
                // บันทึกลงฐานข้อมูล (คอลัมน์ show_in_roster)
                $stmt = $db->prepare("UPDATE users SET show_in_roster = ? WHERE id = ?");
                $success = $stmt->execute([$status, $id]);

                if ($success) {
                    // บันทึก Log
                    $actionText = $status == 1 ? "แสดง" : "ซ่อน";
                    LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "{$actionText}ชื่อในตารางเวร สำหรับบุคลากร ID: {$id}");
                    
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'ไม่สามารถอัปเดตฐานข้อมูลได้']);
                }
            } catch (Exception $e) {
                // กรณีที่คอลัมน์ show_in_roster ยังไม่มีในฐานข้อมูล จะส่ง Error กลับไป
                error_log("Staff roster status error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'ไม่สามารถอัปเดตข้อมูลได้']);
            }
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid Request']);
        exit;
    }

    // อัปเดตลำดับลากวาง
    public function update_order() {
        $this->checkAuth();
        header('Content-Type: application/json');
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!is_array($data) || !$this->jsonCsrfValid($data)) {
            http_response_code(419);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }

        if (isset($data['order']) && is_array($data['order'])) {
            $db = (new Database())->getConnection();
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("UPDATE users SET display_order = ? WHERE id = ?");
                $userModel = new UserModel($db);
                foreach ($data['order'] as $item) {
                    $id = (int)($item['id'] ?? 0);
                    $order = (int)($item['order'] ?? 0);
                    $target = $userModel->getUserById($id);
                    if (!$id || !$target || !$this->canManageTarget($target)) {
                        throw new RuntimeException('Forbidden roster order update');
                    }
                    $stmt->execute([$order, $id]);
                }
                
                // 🌟 บันทึก Log
                $count = count($data['order']);
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "จัดลำดับรายชื่อบุคลากรใหม่ จำนวน {$count} รายการ");
                
                $db->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                echo json_encode(['success' => false]);
            }
        }
        exit;
    }

    // โหลด Template CSV
    public function download_template() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_EXPORT, "ดาวน์โหลดแม่แบบนำเข้าบุคลากร (CSV)");
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=template_staff.csv');
        $output = fopen('php://output', 'w');
        fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // รูปแบบใหม่ (7 คอลัมน์)
        fputcsv($output, ['Hospital_ID', 'Name', 'Username', 'ID_Card', 'Position', 'Employee_Type', 'Phone']);
        fputcsv($output, [$_SESSION['user']['hospital_id'], 'นาย ทดสอบ จัดเวร', 'test_staff_01', '1330000000000', 'พยาบาลวิชาชีพ', 'ข้าราชการ', '0812345678']);
        fclose($output);
        exit;
    }

    // นำเข้า CSV
    public function import() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['import_file'])) {
            $this->verifyCsrf();

            $upload = $_FILES['import_file'];
            if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
                !is_uploaded_file($upload['tmp_name'] ?? '') ||
                (int)($upload['size'] ?? 0) <= 0 ||
                (int)($upload['size'] ?? 0) > 2 * 1024 * 1024) {
                $_SESSION['error_msg'] = "ไฟล์ CSV ไม่ถูกต้อง หรือมีขนาดเกิน 2 MB";
                header("Location: index.php?c=staff");
                exit;
            }

            $extension = strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION));
            if ($extension !== 'csv') {
                $_SESSION['error_msg'] = "รองรับเฉพาะไฟล์ .csv";
                header("Location: index.php?c=staff");
                exit;
            }

            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            $handle = fopen($upload['tmp_name'], "r");
            
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($handle);
            
            $header = fgetcsv($handle); // ข้าม Header และใช้นับจำนวนคอลัมน์
            $col_count = is_array($header) ? count($header) : 0;
            
            $ok = 0; $fail = 0;
            $fail_reasons = []; // เก็บเหตุผลที่ Error
            $my_hosp = (int)($_SESSION['user']['hospital_id'] ?? 0);
            $is_global_admin = $this->isGlobalAdmin();
            
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (empty($row[1])) {
                    $fail++;
                    continue; // ชื่อห้ามว่าง
                }
                
                $name = trim($row[1]);
                
                // ตรวจสอบว่าเป็นไฟล์ CSV รูปแบบเก่า (5 คอลัมน์) หรือรูปแบบใหม่ (7 คอลัมน์ขึ้นไป)
                if ($col_count <= 5 || !isset($row[6])) {
                    $position = trim($row[2] ?? '');
                    $employee_type = trim($row[3] ?? 'พนักงานทั่วไป');
                    $phone = trim($row[4] ?? '');
                    $username = '';
                    $id_card = '';
                } else {
                    $username = trim($row[2] ?? '');
                    $id_card = trim($row[3] ?? '');
                    $position = trim($row[4] ?? '');
                    $employee_type = trim($row[5] ?? 'พนักงานทั่วไป');
                    $phone = trim($row[6] ?? '');
                }
                
                if (empty($username)) {
                    $phone_clean = preg_replace('/[^0-9]/', '', $phone);
                    if (!empty($phone_clean)) {
                        $username = 'u' . $phone_clean;
                    } else {
                        $username = 'user_' . uniqid(); // สุ่มไอดี
                    }
                }

                if ($userModel->checkUsernameExists($username)) { 
                    $fail++; 
                    $fail_reasons[] = "Username ซ้ำ ($username)";
                    continue; 
                }

                $importData = [
                    'hospital_id' => ($is_global_admin && !empty($row[0])) ? (int)$row[0] : $my_hosp,
                    'name' => $name,
                    'username' => $username,
                    'id_card' => $id_card,
                    'position' => $position,
                    'employee_type' => $employee_type,
                    'phone' => $phone,
                    'password' => bin2hex(random_bytes(6)), // รหัสชั่วคราวแบบสุ่ม
                    'role' => 'STAFF',
                    'is_active' => 1,
                    'color_theme' => 'success'
                ];

                if ($userModel->addUser($importData)) {
                    $ok++;
                } else {
                    $fail++;
                    $fail_reasons[] = "เกิดข้อผิดพลาดในการบันทึก ($name)";
                }
            }
            fclose($handle);
            
            // บันทึก Log พร้อมเหตุผล
            $log_details = "นำเข้าบุคลากรผ่านไฟล์ CSV สำเร็จ {$ok} คน, ล้มเหลว {$fail} คน";
            if ($fail > 0 && count($fail_reasons) > 0) {
                $log_details .= " (สาเหตุเบื้องต้น: " . implode(', ', array_slice($fail_reasons, 0, 3)) . ")";
            }
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_IMPORT, $log_details);
            
            $_SESSION['success_msg'] = "นำเข้าบุคลากรสำเร็จ $ok คน, ล้มเหลว $fail คน" . ($fail > 0 ? " (โปรดตรวจสอบ Username หรือโหลด Template ใหม่ล่าสุดไปใช้งาน)" : "");
        }
        header("Location: index.php?c=staff");
        exit;
    }

    // ==========================================
    // 🌟 ฟังก์ชันใหม่: บันทึกลายเซ็นอิเล็กทรอนิกส์ (E-Signature)
    // ==========================================
    public function save_signature() {
        $this->checkAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->verifyCsrf();
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            
            $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $signature_base64 = trim((string)($_POST['signature_base64'] ?? ''));

            if(empty($user_id) || empty($signature_base64)) {
                echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
                exit;
            }

            $target = $userModel->getUserById($user_id);
            if (!$target || !$this->canManageTarget($target)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์จัดการลายเซ็นนี้']);
                exit;
            }

            if (!preg_match('/^data:image\/(png|jpeg);base64,([A-Za-z0-9+\/=]+)$/', $signature_base64, $matches)) {
                echo json_encode(['success' => false, 'message' => 'รูปแบบลายเซ็นไม่ถูกต้อง']);
                exit;
            }

            $decoded = base64_decode($matches[2], true);
            if ($decoded === false || strlen($decoded) > 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'ไฟล์ลายเซ็นต้องมีขนาดไม่เกิน 1 MB']);
                exit;
            }

            // นำไปบันทึกด้วยฟังก์ชัน updateSignature() ใน UserModel
            if ($userModel->updateSignature($user_id, $signature_base64)) {
                
                // 📝 สิ่งสำคัญที่สุด: เก็บ Log ตามมาตรา 9 และ 26 พ.ร.บ. ว่าด้วยธุรกรรมทางอิเล็กทรอนิกส์
                $current_user_name = $_SESSION['user']['name'];
                $logMsg = "สร้าง/แก้ไข ลายมือชื่ออิเล็กทรอนิกส์ (E-Signature) ของบุคลากร ID: {$user_id} (ยินยอมให้มีผลผูกพันทางกฎหมาย โดย {$current_user_name})";
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, $logMsg);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'ไม่สามารถบันทึกลงฐานข้อมูลได้']);
            }
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Invalid Request']);
        exit;
    }
}
?>