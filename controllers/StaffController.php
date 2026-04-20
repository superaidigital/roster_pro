<?php
// ที่อยู่ไฟล์: controllers/StaffController.php

require_once 'config/database.php';
require_once 'models/UserModel.php';
require_once 'models/HospitalModel.php';
require_once 'models/PayRateModel.php';
require_once 'controllers/LogsController.php';

class StaffController {

    // ====================================================
    // 🛡️ ตรวจสอบสิทธิ์การเข้าใช้งาน
    // ====================================================
    private function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) session_start();

        // อนุญาตให้ SCHEDULER, DIRECTOR, HR, ADMIN, SUPERADMIN เข้าจัดการได้
        $allowed = ['SCHEDULER', 'DIRECTOR', 'HR', 'ADMIN', 'SUPERADMIN'];
        if (!isset($_SESSION['user']) || !in_array(strtoupper($_SESSION['user']['role']), $allowed)) {
            $_SESSION['error_msg'] = "คุณไม่มีสิทธิ์เข้าถึงส่วนการจัดการบุคลากร";
            header("Location: index.php?c=dashboard");
            exit;
        }
    }

    // ====================================================
    // 🌟 1. หน้าหลัก: ดึงข้อมูลบุคลากรในหน่วยบริการของตนเอง
    // ====================================================
    public function index() {
        $this->checkAuth();
        $db = (new Database())->getConnection();

        $userModel = new UserModel($db);
        $hospitalModel = new HospitalModel($db);
        $payRateModel = new PayRateModel($db);

        // บังคับดึงเฉพาะคนในหน่วยบริการเดียวกัน
        $hospital_id = $_SESSION['user']['hospital_id'];
        
        // ดึงชื่อหน่วยบริการ
        $hospital_name = 'ส่วนกลาง (ศูนย์ควบคุม)';
        if (!empty($hospital_id)) {
            $hosp_data = $hospitalModel->getHospitalById($hospital_id);
            if ($hosp_data) {
                $hospital_name = $hosp_data['name'];
            }
        }

        // ดึงรายชื่อบุคลากรในหน่วยบริการ เรียงตาม display_order ASC
        $sql = "SELECT u.*, h.name as hospital_name, pr.name as pay_rate_name 
                FROM users u 
                LEFT JOIN hospitals h ON u.hospital_id = h.id 
                LEFT JOIN pay_rates pr ON u.pay_rate_id = pr.id
                WHERE (u.hospital_id = :hospital_id OR (:hospital_id2 = 0 AND (u.hospital_id IS NULL OR u.hospital_id = 0)))
                ORDER BY u.display_order ASC, u.id ASC";
        
        $stmt = $db->prepare($sql);
        $h_id_param = empty($hospital_id) ? 0 : $hospital_id;
        $stmt->execute([':hospital_id' => $h_id_param, ':hospital_id2' => $h_id_param]);
        $staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ดึงกลุ่มเรทค่าตอบแทน
        $pay_rates = $payRateModel->getAllRates();

        // โหลด View
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/staff/index.php';
        echo "</main></div></body></html>";
    }

    // ====================================================
    // 🌟 2. เพิ่มบุคลากรใหม่ (ล็อกให้อยู่ใน รพ. ตัวเอง)
    // ====================================================
    public function add() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);

            // บังคับให้เพิ่มในหน่วยบริการของตัวเองเท่านั้น
            $hospital_id = $_SESSION['user']['hospital_id'];

            $data = [
                'hospital_id' => empty($hospital_id) ? null : $hospital_id,
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

            // ป้องกันการแอบเพิ่มสิทธิ์เกินตัว
            $current_role = strtoupper($_SESSION['user']['role']);
            if (!in_array($current_role, ['ADMIN', 'SUPERADMIN', 'HR']) && in_array($data['role'], ['ADMIN', 'SUPERADMIN', 'HR'])) {
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
        header("Location: index.php?c=staff");
        exit;
    }

    // ====================================================
    // 🌟 3. แก้ไขข้อมูลบุคลากร
    // ====================================================
    public function edit() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);

            $id = $_POST['id'];
            $hospital_id = $_SESSION['user']['hospital_id'];

            $data = [
                'id' => $id,
                'hospital_id' => empty($hospital_id) ? null : $hospital_id,
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
                    $_SESSION['error_msg'] = "ไม่พบข้อมูลบุคลากรที่ต้องการแก้ไข";
                    header("Location: index.php?c=staff");
                    exit;
                }

                // ป้องกันการแก้ไขคนข้ามหน่วยบริการ (เว้นแต่เป็น ADMIN/HR/SUPERADMIN)
                $current_role = strtoupper($_SESSION['user']['role']);
                if (!in_array($current_role, ['ADMIN', 'SUPERADMIN', 'HR']) && $existing_user['hospital_id'] != $hospital_id) {
                    $_SESSION['error_msg'] = "⛔ ปฏิเสธ: คุณไม่มีสิทธิ์แก้ไขบุคลากรต่างหน่วยบริการ";
                } elseif ($existing_user['role'] === 'SUPERADMIN' && $current_role !== 'SUPERADMIN') {
                    $_SESSION['error_msg'] = "⛔ ปฏิเสธ: คุณไม่มีสิทธิ์แก้ไขผู้ดูแลระบบสูงสุด";
                } elseif (!empty($data['id_card']) && $userModel->checkDuplicateField('id_card', $data['id_card'], $id)) {
                    $_SESSION['error_msg'] = "เลขบัตรประชาชนนี้ถูกใช้งานโดยบุคคลอื่นแล้ว";
                } else {
                    // ป้องกันยกระดับสิทธิ์ตัวเองหรือผู้อื่นเกินกว่าที่อนุญาต
                    if (!in_array($current_role, ['ADMIN', 'SUPERADMIN', 'HR']) && in_array($data['role'], ['ADMIN', 'SUPERADMIN', 'HR'])) {
                        $data['role'] = $existing_user['role'];
                    }

                    if ($userModel->updateUser($data)) {
                        LogsController::addLog($db, $_SESSION['user']['id'], 'UPDATE', "แก้ไขข้อมูลบุคลากร: " . $data['name']);
                        $_SESSION['success_msg'] = "อัปเดตข้อมูลสำเร็จ";
                    } else {
                        $_SESSION['error_msg'] = "ไม่สามารถบันทึกข้อมูลได้";
                    }
                }
            } catch (Exception $e) {
                $_SESSION['error_msg'] = "Error: " . $e->getMessage();
            }
        }
        header("Location: index.php?c=staff");
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
            $hospital_id = $_SESSION['user']['hospital_id'];

            try {
                $target = $userModel->getUserById($id);
                if (!$target) {
                    $_SESSION['error_msg'] = "ไม่พบข้อมูลบุคลากร";
                    header("Location: index.php?c=staff");
                    exit;
                }

                $current_role = strtoupper($_SESSION['user']['role']);
                if (!in_array($current_role, ['ADMIN', 'SUPERADMIN', 'HR']) && $target['hospital_id'] != $hospital_id) {
                    $_SESSION['error_msg'] = "⛔ ปฏิเสธ: คุณไม่มีสิทธิ์ลบบุคลากรต่างหน่วยบริการ";
                } elseif ($target['role'] === 'SUPERADMIN') {
                    $_SESSION['error_msg'] = "⛔ ปฏิเสธ: ไม่สามารถลบผู้ดูแลระบบสูงสุดได้";
                } elseif ($id == $_SESSION['user']['id']) {
                    $_SESSION['error_msg'] = "คุณไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้";
                } else {
                    if ($userModel->deleteUser($id)) {
                        LogsController::addLog($db, $_SESSION['user']['id'], 'DELETE', "ลบบุคลากร ID: $id");
                        $_SESSION['success_msg'] = "ลบข้อมูลสำเร็จ";
                    } else {
                        $_SESSION['error_msg'] = "ไม่สามารถลบได้ (อาจมีข้อมูลผูกพันในตารางเวร)";
                    }
                }
            } catch (Exception $e) {
                $_SESSION['error_msg'] = "Error deleting staff.";
            }
        }
        header("Location: index.php?c=staff");
        exit;
    }

    // ====================================================
    // 🌟 บันทึกลำดับการจัดเรียง (AJAX Update Order)
    // ====================================================
    public function update_order() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        // บังคับส่งค่ากลับเป็น JSON เพื่อให้แจ้งเตือนในหน้าเว็บทำงาน
        header('Content-Type: application/json');

        // 🌟 แก้ไขแล้ว: เพิ่มสิทธิ์ DIRECTOR และ SCHEDULER ให้สามารถจัดเรียงเวรได้
        $allowed_roles = ['ADMIN', 'SUPERADMIN', 'HR', 'DIRECTOR', 'SCHEDULER'];
        
        if (!isset($_SESSION['user']) || !in_array(strtoupper($_SESSION['user']['role']), $allowed_roles)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์จัดการลำดับ']);
            exit;
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (isset($data['order']) && is_array($data['order'])) {
            require_once 'config/database.php';
            $db = (new Database())->getConnection();
            
            try {
                $db->beginTransaction();
                
                // นำค่าที่ลากสลับ มาบันทึกลงฐานข้อมูลทีละแถว
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
            echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
        }
        exit;
    }
}
?>