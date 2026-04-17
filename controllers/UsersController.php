<?php
// ที่อยู่ไฟล์: controllers/UsersController.php

require_once 'config/database.php';
require_once 'models/UserModel.php';
require_once 'models/HospitalModel.php';
require_once 'models/PayRateModel.php';
require_once 'controllers/LogsController.php'; 

class UsersController {
    
    // ====================================================
    // 🛡️ ตรวจสอบสิทธิ์การเข้าใช้งาน
    // ====================================================
    private function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user']) || !in_array(strtoupper($_SESSION['user']['role']), ['ADMIN', 'SUPERADMIN'])) {
            $_SESSION['error_msg'] = "คุณไม่มีสิทธิ์เข้าถึงหน้าฐานข้อมูลบุคลากรส่วนกลาง";
            header("Location: index.php?c=dashboard");
            exit;
        }
    }

    // ====================================================
    // 🌟 1. โหลดหน้าจอหลักและดึงข้อมูลทั้งหมด
    // ====================================================
    public function index() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        
        $userModel = new UserModel($db);
        $hospitalModel = new HospitalModel($db);
        $payRateModel = new PayRateModel($db);

        // ดึงข้อมูลทั้งหมดไปแสดงผลใน View
        $users_list = $userModel->getAllUsers();
        $hospitals_list = $hospitalModel->getAllHospitals();
        $pay_rates = $payRateModel->getAllRates();

        // โหลดหน้าจอ
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/users/index.php';
        echo "</main></div></body></html>";
    }
    
    // ====================================================
    // 🌟 2. ฟังก์ชันเพิ่มข้อมูลผู้ใช้ใหม่ (รายบุคคล)
    // ====================================================
    public function add() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            
            // แมปตัวแปรให้ตรงกับใน Model และ View
            $data = [
                'hospital_id' => !empty($_POST['hospital_id']) ? $_POST['hospital_id'] : null,
                'name' => trim($_POST['name'] ?? ''),
                'username' => trim($_POST['username'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'role' => $_POST['role'] ?? 'STAFF',
                'pay_rate_id' => !empty($_POST['pay_rate_id']) ? $_POST['pay_rate_id'] : null,
                'type' => trim($_POST['type'] ?? ''),
                'employee_type' => trim($_POST['employee_type'] ?? ''),
                'id_card' => trim($_POST['id_card'] ?? ''),
                'position_number' => trim($_POST['position_number'] ?? ''),
                'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                'phone' => trim($_POST['phone'] ?? ''),
                'color_theme' => $_POST['color_theme'] ?? 'primary'
            ];

            // ตรวจสอบ Username ซ้ำก่อนบันทึก
            if ($userModel->checkUsernameExists($data['username'])) {
                $_SESSION['error_msg'] = "Username นี้มีผู้ใช้งานแล้ว โปรดใช้ชื่ออื่น";
            } 
            // ตรวจสอบเลขบัตรประชาชนซ้ำ (ถ้ามีการกรอกมา)
            elseif (!empty($data['id_card']) && $userModel->checkDuplicateField('id_card', $data['id_card'])) {
                $_SESSION['error_msg'] = "เลขบัตรประชาชนนี้มีอยู่ในระบบแล้ว โปรดตรวจสอบอีกครั้ง";
            } 
            // บันทึกข้อมูล
            else {
                if ($userModel->addUser($data)) {
                    LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "เพิ่มบุคลากรใหม่(ส่วนกลาง): " . $data['name']);
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
    // 🌟 3. ฟังก์ชันแก้ไข/อัปเดตข้อมูล
    // ====================================================
    public function edit() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            
            // แมปตัวแปรอัปเดต
            $data = [
                'id' => $_POST['id'],
                'hospital_id' => !empty($_POST['hospital_id']) ? $_POST['hospital_id'] : null,
                'name' => trim($_POST['name'] ?? ''),
                'role' => $_POST['role'] ?? 'STAFF',
                'pay_rate_id' => !empty($_POST['pay_rate_id']) ? $_POST['pay_rate_id'] : null,
                'type' => trim($_POST['type'] ?? ''),
                'employee_type' => trim($_POST['employee_type'] ?? ''),
                'id_card' => trim($_POST['id_card'] ?? ''),
                'position_number' => trim($_POST['position_number'] ?? ''),
                'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                'phone' => trim($_POST['phone'] ?? ''),
                'color_theme' => $_POST['color_theme'] ?? 'primary',
                'password' => !empty($_POST['password']) ? $_POST['password'] : null // รหัสผ่าน (ถ้าว่างจะไม่ถูกเปลี่ยน)
            ];

            // ตรวจสอบเลขบัตรประชาชนซ้ำ โดยยกเว้น ID ของตัวเอง
            if (!empty($data['id_card']) && $userModel->checkDuplicateField('id_card', $data['id_card'], $data['id'])) {
                $_SESSION['error_msg'] = "ไม่สามารถบันทึกได้! เลขบัตรประชาชนนี้ถูกใช้งานโดยบุคคลอื่นในระบบแล้ว";
            } else {
                if ($userModel->updateUser($data)) {
                    LogsController::addLog($db, $_SESSION['user']['id'], 'UPDATE', "แก้ไขข้อมูลบุคลากร(ส่วนกลาง): " . $data['name']);
                    $_SESSION['success_msg'] = "อัปเดตข้อมูลบุคลากรสำเร็จ";
                } else {
                    $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล";
                }
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

            // ป้องกันลบตัวเอง
            if ($id == $_SESSION['user']['id']) {
                $_SESSION['error_msg'] = "ไม่สามารถลบบัญชีของตัวเองได้";
            } else {
                if ($userModel->deleteUser($id)) {
                    LogsController::addLog($db, $_SESSION['user']['id'], 'DELETE', "ลบข้อมูลบุคลากร(ส่วนกลาง) ID: $id");
                    $_SESSION['success_msg'] = "ลบข้อมูลบุคลากรสำเร็จ";
                } else {
                    $_SESSION['error_msg'] = "ไม่สามารถลบข้อมูลได้ หรือมีข้อมูลผูกพันอยู่ในระบบ";
                }
            }
        }
        header("Location: index.php?c=users");
        exit;
    }

    // ====================================================
    // 🌟 5. ระบบนำเข้าข้อมูลแบบหลายคน (Bulk Import) ไฟล์ CSV แท้ 100%
    // ====================================================

    public function download_template() {
        $this->checkAuth();
        
        $filename = "import_users_template.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // บังคับเข้ารหัส UTF-8 BOM เพื่อให้เปิดใน Excel ภาษาไทยไม่เพี้ยน
        fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // หัวตาราง (คำอธิบาย)
        fputcsv($output, [
            'Hospital_ID (รหัส รพ.สต. ดูจากตารางเทียบ ใส่ 0 ถ้าอยู่ส่วนกลาง)', 
            'Name (ชื่อ-นามสกุล)', 
            'Username (ชื่อล็อกอิน)', 
            'Password (รหัสผ่าน - เว้นว่างได้จะถูกตั้งเป็น 123456)', 
            'ID_Card (เลขบัตร ปชช)', 
            'Employee_Type (เช่น ข้าราชการ/พนักงานท้องถิ่น, พนักงานจ้าง)', 
            'Type (ตำแหน่ง/วิชาชีพ)', 
            'Position_Number (เลขตำแหน่ง)', 
            'Phone (เบอร์โทร)', 
            'Role (สิทธิ์: STAFF, SCHEDULER, DIRECTOR, ADMIN)'
        ]);
        
        // ข้อมูลตัวอย่างแถวที่ 1
        fputcsv($output, ['0', 'นาย สมชาย ใจดี', 'somchai', '123456', '1100000000000', 'ข้าราชการ/พนักงานท้องถิ่น', 'พยาบาลวิชาชีพ', '1234', '0801112222', 'STAFF']);
        
        fclose($output);
        exit;
    }

    public function import() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['import_file'])) {
            $file = $_FILES['import_file'];
            
            // ตรวจสอบนามสกุลไฟล์
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            if (strtolower($ext) !== 'csv') {
                $_SESSION['error_msg'] = "กรุณาอัปโหลดไฟล์นามสกุล .csv เท่านั้น";
                header("Location: index.php?c=users");
                exit;
            }

            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            
            $handle = fopen($file['tmp_name'], "r");
            
            // ข้าม 3 ไบต์แรก (BOM) หากมี (เพื่อป้องกันสระภาษาไทยเพี้ยน)
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }
            
            $header = fgetcsv($handle, 1000, ","); // ข้ามแถวหัวตาราง
            
            $success_count = 0;
            $duplicate_username_count = 0;
            $duplicate_idcard_count = 0;
            $error_count = 0;

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // ข้ามบรรทัดที่ไม่ได้ใส่ชื่อ หรือ Username
                if (empty($data[1]) || empty($data[2])) continue; 

                // ดึงรหัสโรงพยาบาล
                $hosp_raw = trim($data[0]);
                if (preg_match('/^(\d+)/', $hosp_raw, $matches)) {
                    $hosp_id = $matches[1];
                } else {
                    $hosp_id = '0'; 
                }

                $username = trim($data[2]);
                $id_card = trim($data[4] ?? '');

                // 🌟 ป้องกัน Username ซ้ำ
                if ($userModel->checkUsernameExists($username)) {
                    $duplicate_username_count++;
                    continue; 
                }

                // 🌟 ป้องกันเลขบัตรประชาชนซ้ำ 
                if (!empty($id_card) && $userModel->checkDuplicateField('id_card', $id_card)) {
                    $duplicate_idcard_count++;
                    continue; 
                }

                $importData = [
                    'hospital_id' => ($hosp_id === '0' || empty($hosp_id)) ? null : (int)$hosp_id,
                    'name' => trim($data[1]),
                    'username' => $username,
                    'password' => trim($data[3] ?? '123456'), // ค่าเริ่มต้น 123456 ถ้ายกเว้นไว้
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

                // ควบคุม Role ไม่ให้คนเพิ่มเสก Superadmin ได้
                $allowed_roles = ['STAFF', 'SCHEDULER', 'DIRECTOR', 'ADMIN'];
                if ($_SESSION['user']['role'] === 'SUPERADMIN') $allowed_roles[] = 'SUPERADMIN';
                if (!in_array($importData['role'], $allowed_roles)) $importData['role'] = 'STAFF';

                // นำเข้าข้อมูล
                if ($userModel->addUser($importData)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
            fclose($handle);

            // สรุปข้อความแจ้งเตือนให้แอดมินเห็นชัดเจน
            $msg = "<b>สรุปการนำเข้าข้อมูล:</b><br>";
            $msg .= "✅ นำเข้าสำเร็จ: <b class='text-success'>$success_count</b> รายการ<br>";
            
            if ($duplicate_username_count > 0) {
                $msg .= "⚠️ ข้ามข้อมูล (<b class='text-warning'>Username ซ้ำ</b>): $duplicate_username_count รายการ<br>";
            }
            if ($duplicate_idcard_count > 0) {
                $msg .= "⚠️ ข้ามข้อมูล (<b class='text-warning'>เลขบัตร ปชช. ซ้ำ</b>): $duplicate_idcard_count รายการ<br>";
            }
            if ($error_count > 0) {
                $msg .= "❌ บันทึกผิดพลาด (ระบบขัดข้อง): $error_count รายการ";
            }

            if ($success_count > 0) {
                LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "นำเข้าบุคลากรด้วยไฟล์ CSV จำนวน $success_count คน");
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