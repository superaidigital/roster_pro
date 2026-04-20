<?php
// ที่อยู่ไฟล์: controllers/UsersController.php

require_once 'config/database.php';
require_once 'models/UserModel.php';
require_once 'models/HospitalModel.php';
require_once 'models/PayRateModel.php';
require_once 'controllers/LogsController.php'; 

class UsersController {
    
    // ====================================================
    // 🛡️ ตรวจสอบสิทธิ์การเข้าใช้งาน (HR, ADMIN, SUPERADMIN เท่านั้น)
    // ====================================================
    private function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        if (!isset($_SESSION['user']) || !in_array(strtoupper($_SESSION['user']['role']), ['ADMIN', 'SUPERADMIN', 'HR'])) {
            $_SESSION['error_msg'] = "คุณไม่มีสิทธิ์เข้าถึงส่วนการจัดการผู้ใช้งานเครือข่าย";
            header("Location: index.php?c=dashboard");
            exit;
        }
    }

    // ==========================================
    // 🌟 ตรวจสอบสิทธิ์การจัดการรายบุคคล
    // ==========================================
    private function canManageUser($target_user_role, $target_hospital_id = null) {
        $current_role = strtoupper($_SESSION['user']['role']);
        
        // ADMIN และ HR ห้ามแก้ไข/ลบ SUPERADMIN (เพื่อความปลอดภัยสูงสุด)
        if (in_array($current_role, ['ADMIN', 'HR']) && strtoupper($target_user_role) === 'SUPERADMIN') {
            return false;
        }
        
        return true;
    }

    // ====================================================
    // 🌟 1. หน้าหลัก: ดึงข้อมูลผู้ใช้งานตามสิทธิ์
    // ====================================================
    public function index() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        
        $userModel = new UserModel($db);
        $hospitalModel = new HospitalModel($db);
        $payRateModel = new PayRateModel($db);

        $current_role = strtoupper($_SESSION['user']['role']);
        $is_superadmin = ($current_role === 'SUPERADMIN');
        $is_admin_or_hr = in_array($current_role, ['ADMIN', 'HR']);
        $my_hosp_id = $_SESSION['user']['hospital_id'];

        // ดึงรายชื่อผู้ใช้งาน (HR และ ADMIN เห็นทุกคนในเครือข่าย)
        // เรียงลำดับตาม display_order ASC (สำคัญมากสำหรับการลากวาง) และตามด้วย id
        $sql = "SELECT u.*, h.name as hospital_name, pr.name as pay_rate_name 
                FROM users u 
                LEFT JOIN hospitals h ON u.hospital_id = h.id 
                LEFT JOIN pay_rates pr ON u.pay_rate_id = pr.id
                ORDER BY u.display_order ASC, u.id ASC";
        $stmt = $db->query($sql);
        $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $users_list = [];
        foreach($all_users as $u) {
            if ($is_superadmin || $is_admin_or_hr || $u['hospital_id'] == $my_hosp_id) {
                $users_list[] = $u;
            }
        }

        // ดึงรายชื่อหน่วยบริการสำหรับ Dropdown
        $all_hospitals = $hospitalModel->getAllHospitals();
        $hospitals_list = [];
        foreach($all_hospitals as $h) {
            if ($is_superadmin || $is_admin_or_hr || $h['id'] == $my_hosp_id) {
                $hospitals_list[] = $h;
            }
        }

        // ดึงกลุ่มเรทค่าตอบแทน
        $pay_rates = $payRateModel->getAllRates();

        // โหลด View
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/users/index.php';
        echo "</main></div></body></html>";
    }
    
    // ====================================================
    // 🌟 2. เพิ่มผู้ใช้งานใหม่
    // ====================================================
    public function add() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            
            $data = [
                'hospital_id' => !empty($_POST['hospital_id']) ? $_POST['hospital_id'] : null,
                'name' => trim($_POST['name'] ?? ''),
                'username' => trim($_POST['username'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'role' => strtoupper($_POST['role'] ?? 'STAFF'),
                'pay_rate_id' => !empty($_POST['pay_rate_id']) ? $_POST['pay_rate_id'] : null,
                'type' => trim($_POST['type'] ?? ''),
                'employee_type' => trim($_POST['employee_type'] ?? ''),
                'id_card' => trim($_POST['id_card'] ?? ''),
                'position_number' => trim($_POST['position_number'] ?? ''),
                'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                'phone' => trim($_POST['phone'] ?? ''),
                'color_theme' => $_POST['color_theme'] ?? 'primary'
            ];

            // ป้องกันการแอบอ้างสิทธิ์ SUPERADMIN
            if (strtoupper($_SESSION['user']['role']) !== 'SUPERADMIN' && $data['role'] === 'SUPERADMIN') {
                $data['role'] = 'STAFF'; 
            }

            if ($userModel->checkUsernameExists($data['username'])) {
                $_SESSION['error_msg'] = "Username นี้มีผู้ใช้งานแล้ว โปรดใช้ชื่ออื่น";
            } elseif (!empty($data['id_card']) && $userModel->checkDuplicateField('id_card', $data['id_card'])) {
                $_SESSION['error_msg'] = "เลขบัตรประชาชนนี้มีอยู่ในระบบแล้ว";
            } else {
                if ($userModel->addUser($data)) {
                    LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "เพิ่มบุคลากรใหม่: " . $data['name']);
                    $_SESSION['success_msg'] = "เพิ่มข้อมูลบุคลากรสำเร็จ";
                } else {
                    $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
                }
            }
        }
        header("Location: index.php?c=users");
        exit;
    }

    // ====================================================
    // 🌟 3. แก้ไขข้อมูลผู้ใช้งาน
    // ====================================================
    public function edit() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            
            $id = $_POST['id'];
            $data = [
                'id' => $id,
                'hospital_id' => !empty($_POST['hospital_id']) ? $_POST['hospital_id'] : null,
                'name' => trim($_POST['name'] ?? ''),
                'role' => strtoupper($_POST['role'] ?? 'STAFF'),
                'pay_rate_id' => !empty($_POST['pay_rate_id']) ? $_POST['pay_rate_id'] : null,
                'type' => trim($_POST['type'] ?? ''),
                'employee_type' => trim($_POST['employee_type'] ?? ''),
                'id_card' => trim($_POST['id_card'] ?? ''),
                'position_number' => trim($_POST['position_number'] ?? ''),
                'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                'phone' => trim($_POST['phone'] ?? ''),
                'color_theme' => $_POST['color_theme'] ?? 'primary',
                'password' => !empty($_POST['password']) ? $_POST['password'] : null
            ];

            try {
                $existing_user = $userModel->getUserById($id);
                if (!$existing_user) {
                    $_SESSION['error_msg'] = "ไม่พบข้อมูลผู้ใช้งานที่ต้องการแก้ไข";
                    header("Location: index.php?c=users");
                    exit;
                }

                if (!$this->canManageUser($existing_user['role'], $existing_user['hospital_id'])) {
                    $_SESSION['error_msg'] = "⛔ ปฏิเสธ: คุณไม่มีสิทธิ์แก้ไขข้อมูลผู้ดูแลระบบสูงสุด";
                } elseif (!empty($data['id_card']) && $userModel->checkDuplicateField('id_card', $data['id_card'], $id)) {
                    $_SESSION['error_msg'] = "เลขบัตรประชาชนนี้ถูกใช้งานโดยบุคคลอื่นแล้ว";
                } else {
                    if ($userModel->updateUser($data)) {
                        LogsController::addLog($db, $_SESSION['user']['id'], 'UPDATE', "แก้ไขข้อมูล: " . $data['name']);
                        $_SESSION['success_msg'] = "อัปเดตข้อมูลสำเร็จ";
                    } else {
                        $_SESSION['error_msg'] = "ไม่สามารถบันทึกข้อมูลได้";
                    }
                }
            } catch (Exception $e) {
                $_SESSION['error_msg'] = "Error: " . $e->getMessage();
            }
        }
        header("Location: index.php?c=users");
        exit;
    }

    // ====================================================
    // 🌟 4. ลบผู้ใช้งาน
    // ====================================================
    public function delete() {
        $this->checkAuth();
        if (isset($_GET['id'])) {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            $id = $_GET['id'];

            try {
                $target = $userModel->getUserById($id);
                if (!$target) {
                    $_SESSION['error_msg'] = "ไม่พบข้อมูลผู้ใช้งานที่ต้องการลบ";
                    header("Location: index.php?c=users");
                    exit;
                }

                if (!$this->canManageUser($target['role'], $target['hospital_id'])) {
                    $_SESSION['error_msg'] = "⛔ ปฏิเสธ: ไม่สามารถลบผู้ดูแลระบบสูงสุดได้";
                } elseif ($id == $_SESSION['user']['id']) {
                    $_SESSION['error_msg'] = "คุณไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้";
                } else {
                    if ($userModel->deleteUser($id)) {
                        LogsController::addLog($db, $_SESSION['user']['id'], 'DELETE', "ลบผู้ใช้ ID: $id");
                        $_SESSION['success_msg'] = "ลบข้อมูลสำเร็จ";
                    } else {
                        $_SESSION['error_msg'] = "ไม่สามารถลบได้ (อาจมีข้อมูลผูกพันในตารางเวร)";
                    }
                }
            } catch (Exception $e) {
                $_SESSION['error_msg'] = "Error deleting user.";
            }
        }
        header("Location: index.php?c=users");
        exit;
    }

    // ====================================================
    // 🌟 5. สลับสถานะ ระงับ/เปิดใช้งาน (Toggle Active Status)
    // ====================================================
    public function toggle() {
        $this->checkAuth();
        if (isset($_GET['id']) && isset($_GET['status'])) {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            $id = $_GET['id'];
            $status = (int)$_GET['status'];

            try {
                $target = $userModel->getUserById($id);
                if (!$target) {
                    $_SESSION['error_msg'] = "ไม่พบข้อมูลผู้ใช้งานในระบบ";
                    header("Location: index.php?c=users");
                    exit;
                }

                if (!$this->canManageUser($target['role'], $target['hospital_id'])) {
                    $_SESSION['error_msg'] = "ไม่สามารถจัดการบัญชีผู้ดูแลระบบสูงสุดได้";
                } elseif ($id == $_SESSION['user']['id']) {
                    $_SESSION['error_msg'] = "ไม่สามารถระงับบัญชีตนเองได้";
                } else {
                    $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                    if ($stmt->execute([$status, $id])) {
                        $txt = ($status === 1) ? "เปิดใช้งาน" : "ระงับ";
                        LogsController::addLog($db, $_SESSION['user']['id'], 'UPDATE', "$txt บัญชี ID: $id");
                        $_SESSION['success_msg'] = "อัปเดตสถานะ {$target['name']} สำเร็จ";
                    }
                }
            } catch (Exception $e) { 
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาดทางเทคนิค: " . $e->getMessage(); 
            }
        }
        header("Location: index.php?c=users");
        exit;
    }

    // ====================================================
    // 🌟 6. บันทึกลำดับการจัดเรียงใหม่ (AJAX Update Order)
    // ====================================================
    public function update_order() {
        // ต้องมีสิทธิ์จัดการถึงจะเปลี่ยนลำดับได้
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        // ส่ง Header เป็น JSON เพื่อความถูกต้องของ AJAX
        header('Content-Type: application/json');

        if (!isset($_SESSION['user']) || !in_array(strtoupper($_SESSION['user']['role']), ['ADMIN', 'SUPERADMIN', 'HR'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }

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
                $db->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
        }
        exit;
    }

    // ====================================================
    // 🌟 7. ระบบนำเข้าข้อมูล (CSV Import)
    // ====================================================
    public function download_template() {
        $this->checkAuth();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=template_import_users.csv');
        $output = fopen('php://output', 'w');
        fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM สำหรับ Excel
        fputcsv($output, ['Hospital_ID', 'Name', 'Username', 'Password', 'ID_Card', 'Employee_Type', 'Position', 'Pos_Number', 'Phone', 'Role']);
        fputcsv($output, ['0', 'นาย สมชาย ใจดี', 'somchai_test', '123456', '1100000000000', 'ข้าราชการ', 'พยาบาลวิชาชีพ', '1234', '0812345678', 'STAFF']);
        fclose($output);
        exit;
    }

    public function import() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['import_file'])) {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            $handle = fopen($_FILES['import_file']['tmp_name'], "r");
            
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($handle);
            fgetcsv($handle); 
            
            $ok = 0; $fail = 0;
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (empty($row[1]) || empty($row[2])) continue;

                $username = trim($row[2]);
                if ($userModel->checkUsernameExists($username)) { $fail++; continue; }

                $importData = [
                    'hospital_id' => ($row[0] == '0' || empty($row[0])) ? null : (int)$row[0],
                    'name' => trim($row[1]),
                    'username' => $username,
                    'password' => trim($row[3] ?? '123456'),
                    'id_card' => trim($row[4] ?? ''),
                    'employee_type' => trim($row[5] ?? 'ข้าราชการ'),
                    'type' => trim($row[6] ?? ''),
                    'position_number' => trim($row[7] ?? ''),
                    'phone' => trim($row[8] ?? ''),
                    'role' => strtoupper(trim($row[9] ?? 'STAFF')),
                    'color_theme' => 'primary'
                ];

                if ($userModel->addUser($importData)) $ok++; else $fail++;
            }
            fclose($handle);
            $_SESSION['success_msg'] = "นำเข้าสำเร็จ $ok รายการ, ข้าม/ผิดพลาด $fail รายการ";
            LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "นำเข้าบุคลากรผ่าน CSV สำเร็จ $ok คน");
        }
        header("Location: index.php?c=users");
        exit;
    }
}