<?php
// ไฟล์: controllers/StaffController.php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'models/UserModel.php';
require_once 'models/HospitalModel.php';
require_once 'models/PayRateModel.php';
require_once 'controllers/LogsController.php'; 

class StaffController {
    
    private function requireMutation() {
        if (!security_is_valid_post_csrf()) {
            http_response_code(403);
            $_SESSION['error_msg'] = "คำขอไม่ถูกต้องหรือหมดอายุ กรุณาลองใหม่";
            header("Location: index.php?c=staff");
            exit;
        }
    }

    // ตรวจสอบสิทธิ์ (SCHEDULER ขึ้นไปสามารถใช้งานส่วนนี้ได้)
    private function checkAuth() {
        security_start_session();
        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=login");
            exit;
        }
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
        
        $is_admin_level = in_array($current_role, ['ADMIN', 'SUPERADMIN', 'HR'], true);

        // หากเป็นผู้จัดเวร ให้เห็นเฉพาะบุคลากรในหน่วยงานตัวเอง
        if ($is_admin_level) {
            $staff_list = $userModel->getAllUsers();
            $hospitals_list = $hospitalModel->getAllHospitals();
        } else {
            $staff_list = $userModel->getUsersByHospital($my_hosp_id);
            $hospitals_list = [$hospitalModel->getHospitalById($my_hosp_id)];
        }

        $pay_rates = $payRateModel->getAllRates();

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/staff/index.php';
        echo "</main></div></body></html>";
    }
    
    // เพิ่มบุคลากร
    public function add() {
        $this->requireMutation();
        $this->checkAuth();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header("Location: index.php?c=staff");
            exit;
        }

        $db = (new Database())->getConnection();
        $userModel = new UserModel($db);

        $currentRole = strtoupper((string)($_SESSION['user']['role'] ?? 'STAFF'));
        $isGlobalAdmin = in_array($currentRole, ['ADMIN', 'SUPERADMIN', 'HR'], true);

        $name = trim((string)($_POST['name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $newPassword = (string)($_POST['password'] ?? '');

        if ($name === '' || $username === '') {
            $_SESSION['error_msg'] = "กรุณากรอกชื่อและ Username ให้ครบถ้วน";
            header("Location: index.php?c=staff");
            exit;
        }

        if (mb_strlen($newPassword, 'UTF-8') < 8) {
            $_SESSION['error_msg'] = "รหัสผ่านสำหรับบัญชีใหม่ต้องมีอย่างน้อย 8 ตัวอักษร";
            header("Location: index.php?c=staff");
            exit;
        }

        $data = [
            'hospital_id' => $isGlobalAdmin
                ? (!empty($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : null)
                : (int)($_SESSION['user']['hospital_id'] ?? 0),
            'name' => $name,
            'username' => $username,
            'password' => $newPassword,
            'role' => strtoupper(trim((string)($_POST['role'] ?? 'STAFF'))),
            'pay_rate_id' => !empty($_POST['pay_rate_id']) ? (int)$_POST['pay_rate_id'] : null,
            'position' => trim((string)($_POST['position'] ?? '')),
            'employee_type' => trim((string)($_POST['employee_type'] ?? 'ข้าราชการ/พนักงานท้องถิ่น')),
            'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'phone' => trim((string)($_POST['phone'] ?? '')),
            'id_card' => trim((string)($_POST['id_card'] ?? '')),
            'position_number' => trim((string)($_POST['position_number'] ?? '')),
            'type' => trim((string)($_POST['type'] ?? '')),
            'color_theme' => 'success',
        ];

        $allowedRoles = ['SUPERADMIN', 'ADMIN', 'HR', 'DIRECTOR', 'SCHEDULER', 'STAFF'];
        if (!in_array($data['role'], $allowedRoles, true)) {
            $data['role'] = 'STAFF';
        }

        if (!$isGlobalAdmin) {
            if (in_array($data['role'], ['SUPERADMIN', 'ADMIN', 'HR'], true)) {
                $data['role'] = 'STAFF';
            }
            if ($currentRole === 'SCHEDULER' && $data['role'] === 'DIRECTOR') {
                $data['role'] = 'STAFF';
            }
        }

        if ($currentRole !== 'SUPERADMIN' && $data['role'] === 'SUPERADMIN') {
            $data['role'] = 'STAFF';
        }

        if ($userModel->checkUsernameExists($username)) {
            $_SESSION['error_msg'] = "Username นี้มีผู้ใช้งานแล้ว โปรดใช้ชื่ออื่น";
        } elseif (!empty($data['id_card']) && $userModel->checkDuplicateField('id_card', $data['id_card'])) {
            $_SESSION['error_msg'] = "เลขบัตรประชาชนนี้มีอยู่ในระบบแล้ว";
        } elseif ($userModel->addUser($data)) {
            LogsController::addLog(
                $db,
                (int)$_SESSION['user']['id'],
                LogsController::ACTION_CREATE,
                "เพิ่มบุคลากรภายในหน่วยงาน: " . $data['name']
            );
            $_SESSION['success_msg'] = "เพิ่มข้อมูลบุคลากรสำเร็จ";
        } else {
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
        }

        header("Location: index.php?c=staff");
        exit;
    }

    public function edit() {
        $this->requireMutation();
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
                $editPassword = (string)$_POST['password'];
                if (mb_strlen($editPassword, 'UTF-8') < 8) {
                    $_SESSION['error_msg'] = "รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร";
                    header("Location: index.php?c=staff");
                    exit;
                }
                $data['password'] = $editPassword;
            }

            try {
                $existing_user = $userModel->getUserById($id);
                if (!$existing_user) {
                    $_SESSION['error_msg'] = "ไม่พบข้อมูล";
                    header("Location: index.php?c=staff");
                    exit;
                }

                $can_edit = true;
                
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
                error_log('StaffController error: ' . $e->getMessage());
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ";
            }
        }
        header("Location: index.php?c=staff");
        exit;
    }

    // ลบเดี่ยว
    public function delete() {
        $this->requireMutation();
        $this->checkAuth();
        if (isset($_POST['id'])) {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            $id = $_POST['id'];
            $current_role = strtoupper($_SESSION['user']['role']);
            $is_global_admin = in_array($current_role, ['ADMIN', 'SUPERADMIN', 'HR']);

            try {
                $target = $userModel->getUserById($id);
                if (!$target) {
                    header("Location: index.php?c=staff");
                    exit;
                }

                $can_delete = true;
                if (!$is_global_admin) {
                    if ($current_role === 'SCHEDULER' && in_array($target['role'], ['ADMIN', 'SUPERADMIN', 'HR', 'DIRECTOR'])) $can_delete = false;
                    if ($current_role === 'DIRECTOR' && in_array($target['role'], ['ADMIN', 'SUPERADMIN', 'HR'])) $can_delete = false;
                }

                if (!$can_delete) {
                    $_SESSION['error_msg'] = "ปฏิเสธ: ไม่มีสิทธิ์ลบบุคลากรระดับสูง";
                } elseif ($id == $_SESSION['user']['id']) {
                    $_SESSION['error_msg'] = "ไม่สามารถลบบัญชีตัวเองได้";
                } else {
                    if ($userModel->deleteUser($id)) {
                        // 🌟 บันทึก Log
                        LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_DELETE, "ลบข้อมูลบุคลากร: " . ($target['name'] ?? "ID: $id"));
                        $_SESSION['success_msg'] = "ลบข้อมูลสำเร็จ";
                    }
                }
            } catch (Exception $e) {
                $_SESSION['error_msg'] = "Error deleting staff.";
            }
        }
        header("Location: index.php?c=staff");
        exit;
    }

    // ลบหลายรายการ
    public function bulk_delete() {
        $this->requireMutation();
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                        $can_delete = true;
                        
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
        $this->requireMutation();
        $this->checkAuth();
        if (isset($_POST['id']) && isset($_POST['status'])) {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            $id = $_POST['id'];
            $status = (int)$_POST['status'];
            
            $current_role = strtoupper($_SESSION['user']['role']);
            $is_global_admin = in_array($current_role, ['ADMIN', 'SUPERADMIN', 'HR']);

            if ($id != $_SESSION['user']['id']) {
                $target = $userModel->getUserById($id);
                $can_toggle = true;
                
                if (!$is_global_admin && $target) {
                    if ($current_role === 'SCHEDULER' && in_array($target['role'], ['ADMIN', 'SUPERADMIN', 'HR', 'DIRECTOR'])) $can_toggle = false;
                    if ($current_role === 'DIRECTOR' && in_array($target['role'], ['ADMIN', 'SUPERADMIN', 'HR'])) $can_toggle = false;
                }

                if ($can_toggle) {
                    $userModel->updateStatus($id, $status);
                    $actionTxt = $status === 1 ? "เปิด" : "ระงับ";
                    
                    // 🌟 บันทึก Log
                    LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "{$actionTxt}การใช้งานบัญชีบุคลากร ID: {$id}");
                    $_SESSION['success_msg'] = "เปลี่ยนสถานะสำเร็จ";
                } else {
                    $_SESSION['error_msg'] = "ปฏิเสธ: ไม่มีสิทธิ์ระงับบัญชีระดับสูง";
                }
            } else {
                $_SESSION['error_msg'] = "ไม่สามารถระงับบัญชีตัวเองได้";
            }
        }
        header("Location: index.php?c=staff");
        exit;
    }

    // 🌟 ฟังก์ชันใหม่: เปิด/ปิด การแสดงชื่อในตารางเวร (Show in Roster)
    public function toggle_roster_status() {
        $this->requireMutation();
        $this->checkAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
            $id = $_POST['id'] ?? 0;
            $status = (int)($_POST['status'] ?? 1);

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
                error_log('Staff operation failed: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'ไม่สามารถอัปเดตข้อมูลบุคลากรได้']);
            }
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid Request']);
        exit;
    }

    // อัปเดตลำดับลากวาง
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

        LogsController::addLog(
            $db,
            (int)$_SESSION['user']['id'],
            LogsController::ACTION_EXPORT,
            "ดาวน์โหลดแม่แบบนำเข้าบุคลากร (CSV)"
        );

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=template_staff.csv');

        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, [
            'Hospital_ID', 'Name', 'Username', 'Password',
            'ID_Card', 'Position', 'Employee_Type', 'Phone'
        ]);
        fputcsv($output, [
            (string)($_SESSION['user']['hospital_id'] ?? ''),
            'นาย ตัวอย่าง บุคลากร',
            'staff_example',
            '',
            '',
            'พยาบาลวิชาชีพ',
            'ข้าราชการ/พนักงานท้องถิ่น',
            '0812345678'
        ]);
        fclose($output);
        exit;
    }

    // นำเข้า CSV - ต้องใช้แม่แบบ 8 คอลัมน์และกำหนด Password เองอย่างน้อย 8 ตัวอักษร
    public function import() {
        $this->requireMutation();
        $this->checkAuth();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || empty($_FILES['import_file'])) {
            header("Location: index.php?c=staff");
            exit;
        }

        $file = $_FILES['import_file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            $_SESSION['error_msg'] = "ไม่สามารถอ่านไฟล์ CSV ที่อัปโหลดได้";
            header("Location: index.php?c=staff");
            exit;
        }

        if ((int)($file['size'] ?? 0) > 2 * 1024 * 1024) {
            $_SESSION['error_msg'] = "ไฟล์ CSV ต้องมีขนาดไม่เกิน 2 MB";
            header("Location: index.php?c=staff");
            exit;
        }

        $db = (new Database())->getConnection();
        $userModel = new UserModel($db);
        $handle = fopen($file['tmp_name'], 'rb');

        if ($handle === false) {
            $_SESSION['error_msg'] = "ไม่สามารถเปิดไฟล์ CSV ได้";
            header("Location: index.php?c=staff");
            exit;
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        $expected = ['Hospital_ID','Name','Username','Password','ID_Card','Position','Employee_Type','Phone'];
        $normalizedHeader = array_map(static fn($v) => trim((string)$v), is_array($header) ? $header : []);

        if ($normalizedHeader !== $expected) {
            fclose($handle);
            $_SESSION['error_msg'] = "รูปแบบ CSV ไม่ถูกต้อง กรุณาดาวน์โหลดแม่แบบล่าสุด";
            header("Location: index.php?c=staff");
            exit;
        }

        $currentRole = strtoupper((string)($_SESSION['user']['role'] ?? 'STAFF'));
        $isGlobalAdmin = in_array($currentRole, ['ADMIN', 'SUPERADMIN', 'HR'], true);
        $sessionHospitalId = (int)($_SESSION['user']['hospital_id'] ?? 0);

        $ok = 0;
        $fail = 0;
        $failReasons = [];
        $rowNo = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNo++;
            if (!array_filter($row, static fn($value) => trim((string)$value) !== '')) {
                continue;
            }

            if (count($row) < 8) {
                $fail++;
                $failReasons[] = "แถว {$rowNo}: จำนวนคอลัมน์ไม่ครบ";
                continue;
            }

            $hospitalId = $isGlobalAdmin
                ? (int)($row[0] ?: $sessionHospitalId)
                : $sessionHospitalId;
            $name = trim((string)$row[1]);
            $username = trim((string)$row[2]);
            $password = (string)$row[3];

            if ($hospitalId <= 0 || $name === '' || $username === '') {
                $fail++;
                $failReasons[] = "แถว {$rowNo}: หน่วยบริการ/ชื่อ/Username ไม่ครบ";
                continue;
            }

            if (mb_strlen($password, 'UTF-8') < 8) {
                $fail++;
                $failReasons[] = "แถว {$rowNo}: Password ต้องอย่างน้อย 8 ตัวอักษร";
                continue;
            }

            if ($userModel->checkUsernameExists($username)) {
                $fail++;
                $failReasons[] = "แถว {$rowNo}: Username ซ้ำ ({$username})";
                continue;
            }

            $idCard = trim((string)$row[4]);
            if ($idCard !== '' && $userModel->checkDuplicateField('id_card', $idCard)) {
                $fail++;
                $failReasons[] = "แถว {$rowNo}: เลขบัตรประชาชนซ้ำ";
                continue;
            }

            $data = [
                'hospital_id' => $hospitalId,
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'id_card' => $idCard,
                'position' => trim((string)$row[5]),
                'employee_type' => trim((string)$row[6]) ?: 'ข้าราชการ/พนักงานท้องถิ่น',
                'phone' => trim((string)$row[7]),
                'role' => 'STAFF',
                'type' => '',
                'position_number' => '',
                'pay_rate_id' => null,
                'start_date' => null,
                'color_theme' => 'success',
            ];

            if ($userModel->addUser($data)) {
                $ok++;
            } else {
                $fail++;
                $failReasons[] = "แถว {$rowNo}: บันทึกไม่สำเร็จ";
            }
        }

        fclose($handle);

        $log = "นำเข้าบุคลากรผ่าน CSV สำเร็จ {$ok} คน, ล้มเหลว {$fail} คน";
        LogsController::addLog(
            $db,
            (int)$_SESSION['user']['id'],
            LogsController::ACTION_IMPORT,
            $log
        );

        if ($fail > 0) {
            $_SESSION['error_msg'] = $log . (!empty($failReasons)
                ? " — " . implode('; ', array_slice($failReasons, 0, 3))
                : '');
        } else {
            $_SESSION['success_msg'] = $log;
        }

        header("Location: index.php?c=staff");
        exit;
    }

    // ==========================================
    // 🌟 ฟังก์ชันใหม่: บันทึกลายเซ็นอิเล็กทรอนิกส์ (E-Signature)
    // ==========================================
    public function save_signature() {
        $this->requireMutation();
        $this->checkAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            
            $user_id = $_POST['user_id'] ?? 0;
            $signature_base64 = $_POST['signature_base64'] ?? '';

            if(empty($user_id) || empty($signature_base64)) {
                echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
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