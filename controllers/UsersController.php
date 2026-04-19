<?php
// ที่อยู่ไฟล์: controllers/UsersController.php

require_once 'config/database.php';
require_once 'models/UserModel.php';
require_once 'models/HospitalModel.php';
require_once 'models/PayRateModel.php';
require_once 'controllers/LogsController.php'; 

class UsersController {
    
    // ====================================================
    // 🛡️ ตรวจสอบสิทธิ์การเข้าใช้งาน (เพิ่มสิทธิ์ HR)
    // ====================================================
    private function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        // 🌟 อนุญาตให้ HR, ADMIN, SUPERADMIN เข้าจัดการบุคลากรเครือข่ายได้
        if (!isset($_SESSION['user']) || !in_array(strtoupper($_SESSION['user']['role']), ['ADMIN', 'SUPERADMIN', 'HR'])) {
            $_SESSION['error_msg'] = "คุณไม่มีสิทธิ์เข้าถึงหน้านี้";
            header("Location: index.php?c=dashboard");
            exit;
        }
    }

    // ==========================================
    // 🌟 ตรวจสอบสิทธิ์การจัดการ
    // ==========================================
    private function canManageUser($target_user_role, $target_hospital_id = null) {
        $current_role = strtoupper($_SESSION['user']['role']);
        
        // ADMIN และ HR ห้ามแก้ไข/ลบ SUPERADMIN ส่วนกลาง (เพื่อความปลอดภัย)
        if (in_array($current_role, ['ADMIN', 'HR']) && strtoupper($target_user_role) === 'SUPERADMIN') {
            return false;
        }
        
        // ให้จัดการหน่วยงานอื่นได้อิสระ (รวมถึง HR)
        return true;
    }

    // ====================================================
    // 🌟 1. โหลดหน้าจอหลักและดึงข้อมูลตามสิทธิ์
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

        // 🌟 ดึงและคัดกรองบุคลากร (HR และ ADMIN ดูได้ทุกคน)
        $all_users = $userModel->getAllUsers();
        $users_list = [];
        foreach($all_users as $u) {
            if ($is_superadmin || $is_admin_or_hr || $u['hospital_id'] == $my_hosp_id) {
                $users_list[] = $u;
            }
        }

        // 🌟 ดึงและคัดกรองรายชื่อหน่วยบริการ (Dropdown)
        $all_hospitals = $hospitalModel->getAllHospitals();
        $hospitals_list = [];
        foreach($all_hospitals as $h) {
            if ($is_superadmin || $is_admin_or_hr || $h['id'] == $my_hosp_id) {
                $hospitals_list[] = $h;
            }
        }

        $pay_rates = $payRateModel->getAllRates();

        // โหลดหน้าจอ
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/users/index.php';
        echo "</main></div></body></html>";
    }
    
    // ====================================================
    // 🌟 2. ฟังก์ชันเพิ่มข้อมูลผู้ใช้ใหม่
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

            // 🛑 ป้องกันการเสกตัวเองเป็น SUPERADMIN หากผู้เพิ่มไม่ได้เป็น SUPERADMIN
            if (strtoupper($_SESSION['user']['role']) !== 'SUPERADMIN') {
                if ($data['role'] === 'SUPERADMIN') {
                    $data['role'] = 'STAFF'; // เตะกลับเป็น STAFF หากพยายามแฮกผ่าน HTML
                }
            }

            if ($userModel->checkUsernameExists($data['username'])) {
                $_SESSION['error_msg'] = "Username นี้มีผู้ใช้งานแล้ว โปรดใช้ชื่ออื่น";
            } elseif (!empty($data['id_card']) && $userModel->checkDuplicateField('id_card', $data['id_card'])) {
                $_SESSION['error_msg'] = "เลขบัตรประชาชนนี้มีอยู่ในระบบแล้ว โปรดตรวจสอบอีกครั้ง";
            } else {
                if ($userModel->addUser($data)) {
                    LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "เพิ่มบุคลากรใหม่: " . $data['name']);
                    $_SESSION['success_msg'] = "เพิ่มข้อมูลบุคลากรสำเร็จ";
                } else {
                    $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลลงฐานข้อมูล";
                }
            }
        }
        header("Location: index.php?c=users");
        exit;
    }

    // ====================================================
    // 🌟 3. ฟังก์ชันแก้ไขข้อมูล
    // ====================================================
    public function edit() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            
            $data = [
                'id' => $_POST['id'],
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
                $existing_user = $userModel->getUserById($data['id']);
                
                // 🛑 ตรวจสอบสิทธิ์ (ป้องกัน HR/ADMIN แก้ไขข้อมูล SUPERADMIN)
                if (!$this->canManageUser($existing_user['role'], $existing_user['hospital_id'])) {
                    $_SESSION['error_msg'] = "⛔ ไม่อนุญาต: คุณไม่สามารถแก้ไขข้อมูลผู้ดูแลระบบส่วนกลาง (SUPERADMIN) ได้";
                    header("Location: index.php?c=users");
                    exit;
                }

                // 🛑 ป้องกันการปรับสิทธิ์เป็น SUPERADMIN 
                if (strtoupper($_SESSION['user']['role']) !== 'SUPERADMIN') {
                    if ($data['role'] === 'SUPERADMIN') {
                        $data['role'] = $existing_user['role']; // คืนค่าเป็นบทบาทเดิม
                    }
                }

                if (!empty($data['id_card']) && $userModel->checkDuplicateField('id_card', $data['id_card'], $data['id'])) {
                    $_SESSION['error_msg'] = "ไม่สามารถบันทึกได้! เลขบัตรประชาชนนี้ถูกใช้งานโดยบุคคลอื่นในระบบแล้ว";
                } else {
                    if ($userModel->updateUser($data)) {
                        LogsController::addLog($db, $_SESSION['user']['id'], 'UPDATE', "แก้ไขข้อมูลบุคลากร: " . $data['name']);
                        $_SESSION['success_msg'] = "อัปเดตข้อมูลบุคลากรสำเร็จ";
                    } else {
                        $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล";
                    }
                }
            } catch (Exception $e) {
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
        header("Location: index.php?c=users");
        exit;
    }

    // ====================================================
    // 🌟 4. ฟังก์ชันลบข้อมูล
    // ====================================================
    public function delete() {
        $this->checkAuth();
        if (isset($_GET['id'])) {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            $id = $_GET['id'];

            try {
                $target_user = $userModel->getUserById($id);
                
                // 🛑 ตรวจสอบสิทธิ์
                if (!$this->canManageUser($target_user['role'], $target_user['hospital_id'])) {
                    $_SESSION['error_msg'] = "⛔ ไม่อนุญาต: คุณไม่สามารถลบข้อมูลผู้ดูแลระบบส่วนกลาง (SUPERADMIN) ได้";
                    header("Location: index.php?c=users");
                    exit;
                }

                if ($id == $_SESSION['user']['id']) {
                    $_SESSION['error_msg'] = "ไม่สามารถลบบัญชีของตัวเองได้";
                } else {
                    if ($userModel->deleteUser($id)) {
                        LogsController::addLog($db, $_SESSION['user']['id'], 'DELETE', "ลบข้อมูลบุคลากร ID: $id");
                        $_SESSION['success_msg'] = "ลบข้อมูลบุคลากรสำเร็จ";
                    } else {
                        $_SESSION['error_msg'] = "ไม่สามารถลบข้อมูลได้ หรือมีข้อมูลผูกพันอยู่ในระบบ";
                    }
                }
            } catch (Exception $e) {
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการลบข้อมูล";
            }
        }
        header("Location: index.php?c=users");
        exit;
    }

    // ====================================================
    // 🌟 5. ระบบนำเข้าข้อมูลแบบหลายคน (Bulk Import CSV)
    // ====================================================
    public function download_template() {
        $this->checkAuth();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=import_users_template.csv');
        $output = fopen('php://output', 'w');
        // เพิ่ม BOM ป้องกันปัญหาภาษาไทยเพี้ยนใน Excel
        fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['Hospital_ID (รหัส รพ.สต. ใส่ 0 ถ้าอยู่ส่วนกลาง)', 'Name (ชื่อ-นามสกุล)', 'Username (ชื่อล็อกอิน)', 'Password (รหัสผ่าน - เว้นว่างได้จะถูกตั้งเป็น 123456)', 'ID_Card (เลขบัตร ปชช)', 'Employee_Type (เช่น ข้าราชการ/พนักงานท้องถิ่น)', 'Type (ตำแหน่ง/วิชาชีพ)', 'Position_Number (เลขตำแหน่ง)', 'Phone (เบอร์โทร)', 'Role (สิทธิ์: STAFF, SCHEDULER, DIRECTOR, HR, ADMIN)']);
        fputcsv($output, ['0', 'นาย สมชาย ใจดี', 'somchai', '123456', '1100000000000', 'ข้าราชการ/พนักงานท้องถิ่น', 'นักทรัพยากรบุคคลปฏิบัติการ', '1234', '0801112222', 'HR']);
        fclose($output);
        exit;
    }

    public function import() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['import_file'])) {
            $file = $_FILES['import_file'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            if (strtolower($ext) !== 'csv') {
                $_SESSION['error_msg'] = "กรุณาอัปโหลดไฟล์นามสกุล .csv เท่านั้น";
                header("Location: index.php?c=users");
                exit;
            }

            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            $handle = fopen($file['tmp_name'], "r");
            
            // ตรวจจับและข้าม BOM ถ้ามี
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($handle);
            fgetcsv($handle, 1000, ","); // ข้ามแถว Header
            
            $success_count = 0; $duplicate_username_count = 0; $duplicate_idcard_count = 0; $error_count = 0;

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // ข้ามหากไม่มีชื่อหรือ Username
                if (empty($data[1]) || empty($data[2])) continue; 

                $hosp_raw = trim($data[0]);
                $hosp_id = preg_match('/^(\d+)/', $hosp_raw, $matches) ? $matches[1] : '0';
                
                $username = trim($data[2]);
                $id_card = trim($data[4] ?? '');

                // ตรวจสอบความซ้ำซ้อน
                if ($userModel->checkUsernameExists($username)) { $duplicate_username_count++; continue; }
                if (!empty($id_card) && $userModel->checkDuplicateField('id_card', $id_card)) { $duplicate_idcard_count++; continue; }

                $importData = [
                    'hospital_id' => ($hosp_id === '0' || empty($hosp_id)) ? null : (int)$hosp_id,
                    'name' => trim($data[1]),
                    'username' => $username,
                    'password' => trim($data[3] ?? '123456'),
                    'id_card' => $id_card,
                    'employee_type' => trim($data[5] ?? 'ข้าราชการ/พนักงานท้องถิ่น'),
                    'type' => trim($data[6] ?? ''),
                    'position_number' => trim($data[7] ?? ''),
                    'phone' => trim($data[8] ?? ''),
                    'role' => strtoupper(trim($data[9] ?? 'STAFF')),
                    'color_theme' => 'primary',
                    'pay_rate_id' => null, 
                    'start_date' => null
                ];

                // 🌟 ตรวจสอบและกรอง Role ให้ถูกต้อง
                $allowed_roles = ['STAFF', 'SCHEDULER', 'DIRECTOR', 'HR', 'ADMIN'];
                if (strtoupper($_SESSION['user']['role']) === 'SUPERADMIN') $allowed_roles[] = 'SUPERADMIN';
                
                if (!in_array($importData['role'], $allowed_roles)) {
                    $importData['role'] = 'STAFF'; // ค่าเริ่มต้นถ้าใส่ผิด
                }

                if ($userModel->addUser($importData)) {
                    $success_count++; 
                } else {
                    $error_count++;
                }
            }
            fclose($handle);

            // สรุปผลลัพธ์การนำเข้า
            $msg = "<b>สรุปการนำเข้าข้อมูล:</b><br>✅ นำเข้าสำเร็จ: <b class='text-success'>$success_count</b> รายการ<br>";
            if ($duplicate_username_count > 0) $msg .= "⚠️ ข้ามข้อมูล (Username ซ้ำ): $duplicate_username_count รายการ<br>";
            if ($duplicate_idcard_count > 0) $msg .= "⚠️ ข้ามข้อมูล (เลขบัตร ปชช. ซ้ำ): $duplicate_idcard_count รายการ<br>";
            if ($error_count > 0) $msg .= "❌ บันทึกผิดพลาด: $error_count รายการ";

            if ($success_count > 0) {
                LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "นำเข้าบุคลากรเครือข่าย CSV สำเร็จ $success_count คน");
                $_SESSION['success_msg'] = $msg;
            } else {
                $_SESSION['error_msg'] = "ไม่มีข้อมูลถูกนำเข้า<br>" . $msg;
            }
        }
        header("Location: index.php?c=users");
        exit;
    }
}
?>