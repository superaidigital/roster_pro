<?php
// ที่อยู่ไฟล์: controllers/StaffController.php

require_once 'config/database.php';
require_once 'models/UserModel.php';
require_once 'controllers/LogsController.php'; // 🌟 ดึงระบบ Logs มาใช้

class StaffController {
    
    // 🌟 ระบบตรวจสอบสิทธิ์ (ป้องกันพนักงานทั่วไปแอบเข้าถึง)
    private function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth");
            exit;
        }
        if (strtoupper($_SESSION['user']['role']) === 'STAFF') {
            $_SESSION['error_msg'] = "คุณไม่มีสิทธิ์เข้าถึงเมนูจัดการเจ้าหน้าที่";
            header("Location: index.php?c=dashboard");
            exit;
        }
    }

    // ==========================================
    // 🌟 1. โหลดหน้าจอจัดการบุคลากร (เฉพาะใน รพ.สต. ตัวเอง)
    // ==========================================
    public function index() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        
        $hospital_id = !empty($_SESSION['user']['hospital_id']) ? $_SESSION['user']['hospital_id'] : null;
        $current_role = strtoupper($_SESSION['user']['role']);

        // ดึงชื่อ รพ.สต.
        if ($hospital_id) {
            $stmt = $db->prepare("SELECT name FROM hospitals WHERE id = ?");
            $stmt->execute([$hospital_id]);
            $hospital_name = $stmt->fetchColumn() ?: 'ส่วนกลาง (ศูนย์ควบคุม)';
        } else {
            $hospital_name = 'ส่วนกลาง (ศูนย์ควบคุม)';
        }

        // ดึงรายชื่อบุคลากรเฉพาะในสังกัด
        $query = "
            SELECT u.*, pr.name as pay_rate_name 
            FROM users u 
            LEFT JOIN pay_rates pr ON u.pay_rate_id = pr.id 
        ";
        
        $params = [];
        if ($hospital_id) {
            $query .= " WHERE u.hospital_id = ? ";
            $params[] = $hospital_id;
        } else {
            $query .= " WHERE (u.hospital_id IS NULL OR u.hospital_id = 0) ";
        }
        
        // ซ่อน SUPERADMIN จากสายตาผู้จัดเวรธรรมดา
        if ($current_role !== 'SUPERADMIN') {
            $query .= " AND u.role != 'SUPERADMIN' ";
        }
        
        // เรียงลำดับตามที่ถูกจัดไว้ (sort_order)
        $query .= " ORDER BY u.sort_order ASC, u.id ASC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ดึงกลุ่มสายงาน
        $pay_rates = $db->query("SELECT * FROM pay_rates ORDER BY display_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

        // โหลด View
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/staff/index.php';
        echo "</main></div></body></html>";
    }
    
    // ==========================================
    // 🌟 2. เพิ่มบุคลากรใหม่ (Add)
    // ==========================================
    public function add() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            
            $hospital_id = !empty($_SESSION['user']['hospital_id']) ? $_SESSION['user']['hospital_id'] : null;
            
            // เตรียมข้อมูลส่งให้ Model
            $data = [
                'hospital_id' => $hospital_id,
                'name' => trim($_POST['name'] ?? ''),
                'username' => trim($_POST['username'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'role' => $_POST['role'] ?? 'STAFF',
                'pay_rate_id' => !empty($_POST['pay_rate_id']) ? $_POST['pay_rate_id'] : null,
                'type' => trim($_POST['type'] ?? ''),
                'employee_type' => trim($_POST['employee_type'] ?? 'ข้าราชการ/พนักงานท้องถิ่น'),
                'id_card' => trim($_POST['id_card'] ?? ''),
                'position_number' => trim($_POST['position_number'] ?? ''),
                'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                'phone' => trim($_POST['phone'] ?? ''),
                'color_theme' => $_POST['color_theme'] ?? 'primary'
            ];

            if ($userModel->checkUsernameExists($data['username'])) {
                $_SESSION['error_msg'] = "Username นี้มีผู้ใช้งานแล้ว โปรดใช้ชื่ออื่น";
            } else {
                if ($userModel->addUser($data)) {
                    LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "เพิ่มบุคลากรใหม่(รพ.สต.): " . $data['name']);
                    $_SESSION['success_msg'] = "เพิ่มข้อมูลบุคลากรสำเร็จ";
                } else {
                    $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
                }
            }
        }
        header("Location: index.php?c=staff");
        exit;
    }

    // ==========================================
    // 🌟 3. แก้ไขข้อมูลบุคลากร (Edit)
    // ==========================================
    public function edit() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            
            $id = $_POST['id'];
            $hospital_id = !empty($_SESSION['user']['hospital_id']) ? $_SESSION['user']['hospital_id'] : null;

            // ตรวจสอบความปลอดภัย ป้องกันการแอบแก้ไขคนนอก รพ.สต.
            $targetUser = $userModel->getUserById($id);
            if (!$targetUser || ($hospital_id !== null && $targetUser['hospital_id'] != $hospital_id)) {
                $_SESSION['error_msg'] = "❌ ไม่อนุญาต! คุณไม่มีสิทธิ์แก้ไขข้อมูลบุคลากรนอกสังกัด";
                header("Location: index.php?c=staff");
                exit;
            }

            $data = [
                'id' => $id,
                'hospital_id' => $hospital_id,
                'name' => trim($_POST['name'] ?? ''),
                'role' => $_POST['role'] ?? 'STAFF',
                'pay_rate_id' => !empty($_POST['pay_rate_id']) ? $_POST['pay_rate_id'] : null,
                'type' => trim($_POST['type'] ?? ''),
                'employee_type' => trim($_POST['employee_type'] ?? 'ข้าราชการ/พนักงานท้องถิ่น'),
                'id_card' => trim($_POST['id_card'] ?? ''),
                'position_number' => trim($_POST['position_number'] ?? ''),
                'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                'phone' => trim($_POST['phone'] ?? ''),
                'color_theme' => $_POST['color_theme'] ?? 'primary',
                'password' => !empty($_POST['password']) ? $_POST['password'] : null
            ];

            if ($userModel->updateUser($data)) {
                LogsController::addLog($db, $_SESSION['user']['id'], 'UPDATE', "แก้ไขข้อมูลบุคลากร(รพ.สต.): " . $data['name']);
                $_SESSION['success_msg'] = "อัปเดตข้อมูลบุคลากรสำเร็จ";
            } else {
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล";
            }
        }
        header("Location: index.php?c=staff");
        exit;
    }

    // ==========================================
    // 🌟 4. รับค่า JSON ลากสลับตำแหน่ง (Drag & Drop)
    // ==========================================
    public function update_order() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['order']) && is_array($data['order'])) {
                $db = (new Database())->getConnection();
                $userModel = new UserModel($db);
                $hospital_id = !empty($_SESSION['user']['hospital_id']) ? $_SESSION['user']['hospital_id'] : null;

                $success = true;
                foreach ($data['order'] as $item) {
                    if (isset($item['id']) && isset($item['order'])) {
                        $res = $userModel->updateSortOrder($item['id'], $item['order'], $hospital_id);
                        if(!$res) $success = false;
                    }
                }
                
                echo json_encode(['status' => $success ? 'success' : 'error']);
                exit;
            }
        }
        echo json_encode(['status' => 'error']);
        exit;
    }

    // ==========================================
    // 🌟 5. ลบบุคลากร (Delete)
    // ==========================================
    public function delete() {
        $this->checkAuth();
        if (isset($_GET['id'])) {
            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);
            
            $id = $_GET['id'];
            $hospital_id = !empty($_SESSION['user']['hospital_id']) ? $_SESSION['user']['hospital_id'] : null;

            // ป้องกันการลบตัวเอง
            if ($id == $_SESSION['user']['id']) {
                $_SESSION['error_msg'] = "ไม่สามารถลบบัญชีของตัวเองได้";
            } else {
                // ตรวจสอบความปลอดภัย ป้องกันลบคนนอกสังกัด
                $targetUser = $userModel->getUserById($id);
                if (!$targetUser || ($hospital_id !== null && $targetUser['hospital_id'] != $hospital_id)) {
                    $_SESSION['error_msg'] = "❌ ไม่อนุญาต! คุณไม่มีสิทธิ์ลบข้อมูลบุคลากรนอกสังกัด";
                } else {
                    if ($userModel->deleteUser($id)) {
                        LogsController::addLog($db, $_SESSION['user']['id'], 'DELETE', "ลบข้อมูลบุคลากร(รพ.สต.) ID: $id");
                        $_SESSION['success_msg'] = "ลบข้อมูลบุคลากรสำเร็จ";
                    } else {
                        $_SESSION['error_msg'] = "ไม่สามารถลบข้อมูลได้ หรือมีข้อมูลผูกพันอยู่ในระบบ";
                    }
                }
            }
        }
        header("Location: index.php?c=staff");
        exit;
    }
}
?>