<?php
// ที่อยู่ไฟล์: controllers/UsersController.php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'models/UserModel.php';
require_once 'models/HospitalModel.php';
require_once 'models/PayRateModel.php';
require_once 'controllers/LogsController.php'; 

class UsersController {

    private function requireMutation(): void {
        security_start_session();

        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }

        if (!security_is_valid_post_csrf()) {
            http_response_code(403);
            $_SESSION['error_msg'] = "คำขอไม่ถูกต้องหรือหมดอายุ กรุณาลองใหม่";
            header("Location: index.php?c=users");
            exit;
        }
    }

    
    // ====================================================
    // 🛡️ ตรวจสอบสิทธิ์การเข้าใช้งาน (HR, ADMIN, SUPERADMIN เท่านั้น)
    // ====================================================
    private function checkAuth() {
        security_start_session();
        
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
        // 🌟 แก้ไข: เพิ่ม WHERE u.is_deleted = 0 เพื่อซ่อนผู้ใช้ที่ถูก Soft Delete ออกจากตารางหลัก
        $sql = "SELECT u.*, h.name as hospital_name, pr.name as pay_rate_name 
                FROM users u 
                LEFT JOIN hospitals h ON u.hospital_id = h.id 
                LEFT JOIN pay_rates pr ON u.pay_rate_id = pr.id
                WHERE u.is_deleted = 0
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
        $this->requireMutation();
        $this->checkAuth();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header("Location: index.php?c=users");
            exit;
        }

        $db = (new Database())->getConnection();
        $userModel = new UserModel($db);

        $name = trim((string)($_POST['name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $currentRole = strtoupper((string)($_SESSION['user']['role'] ?? ''));

        if ($name === '' || $username === '') {
            $_SESSION['error_msg'] = "กรุณากรอกชื่อและ Username ให้ครบถ้วน";
            header("Location: index.php?c=users");
            exit;
        }

        if (mb_strlen($password, 'UTF-8') < 8) {
            $_SESSION['error_msg'] = "รหัสผ่านสำหรับบัญชีใหม่ต้องมีอย่างน้อย 8 ตัวอักษร";
            header("Location: index.php?c=users");
            exit;
        }

        $role = strtoupper(trim((string)($_POST['role'] ?? 'STAFF')));
        $allowedRoles = ['SUPERADMIN', 'ADMIN', 'DIRECTOR', 'SCHEDULER', 'STAFF', 'HR'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'STAFF';
        }
        if ($currentRole !== 'SUPERADMIN' && $role === 'SUPERADMIN') {
            $role = 'STAFF';
        }

        $data = [
            'hospital_id' => !empty($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : null,
            'name' => $name,
            'username' => $username,
            'password' => $password,
            'role' => $role,
            'pay_rate_id' => !empty($_POST['pay_rate_id']) ? (int)$_POST['pay_rate_id'] : null,
            'position' => trim((string)($_POST['position'] ?? '')),
            'type' => trim((string)($_POST['type'] ?? '')),
            'employee_type' => trim((string)($_POST['employee_type'] ?? 'ข้าราชการ/พนักงานท้องถิ่น')),
            'id_card' => trim((string)($_POST['id_card'] ?? '')),
            'position_number' => trim((string)($_POST['position_number'] ?? '')),
            'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'phone' => trim((string)($_POST['phone'] ?? '')),
            'color_theme' => trim((string)($_POST['color_theme'] ?? 'primary')),
        ];

        if ($userModel->checkUsernameExists($username)) {
            $_SESSION['error_msg'] = "Username นี้มีผู้ใช้งานแล้ว โปรดใช้ชื่ออื่น";
        } elseif (!empty($data['id_card']) && $userModel->checkDuplicateField('id_card', $data['id_card'])) {
            $_SESSION['error_msg'] = "เลขบัตรประชาชนนี้มีอยู่ในระบบแล้ว";
        } elseif ($userModel->addUser($data)) {
            LogsController::addLog(
                $db,
                (int)$_SESSION['user']['id'],
                LogsController::ACTION_CREATE,
                "เพิ่มบุคลากรใหม่: " . $data['name']
            );
            $_SESSION['success_msg'] = "เพิ่มข้อมูลบุคลากรสำเร็จ";
        } else {
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
        }

        header("Location: index.php?c=users");
        exit;
    }

    // ====================================================
    // 🌟 3. แก้ไขข้อมูลผู้ใช้งาน
    // ====================================================
    public function edit() {
        $this->requireMutation();
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
                'position' => trim($_POST['position'] ?? ''),
                'type' => trim($_POST['type'] ?? ''),
                'employee_type' => trim($_POST['employee_type'] ?? ''),
                'id_card' => trim($_POST['id_card'] ?? ''),
                'position_number' => trim($_POST['position_number'] ?? ''),
                'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                'phone' => trim($_POST['phone'] ?? ''),
                'color_theme' => $_POST['color_theme'] ?? 'primary',
                'password' => !empty($_POST['password']) ? $_POST['password'] : null
            ];

            if (!empty($data['password']) && mb_strlen((string)$data['password'], 'UTF-8') < 8) {
                $_SESSION['error_msg'] = "รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร";
                header("Location: index.php?c=users");
                exit;
            }

            try {
                // 🌟 แก้ไข: ดึงข้อมูลโดยตรงเพื่อป้องกันปัญหา UserModel ซ่อนบัญชีที่ถูกระงับ
                $stmtCheck = $db->prepare("SELECT id, role, hospital_id, name FROM users WHERE id = ?");
                $stmtCheck->execute([$id]);
                $existing_user = $stmtCheck->fetch(PDO::FETCH_ASSOC);

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
                        LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "แก้ไขข้อมูล: " . $data['name']);
                        $_SESSION['success_msg'] = "อัปเดตข้อมูลสำเร็จ";
                    } else {
                        $_SESSION['error_msg'] = "ไม่สามารถบันทึกข้อมูลได้";
                    }
                }
            } catch (Exception $e) {
                error_log('UsersController error: ' . $e->getMessage());
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ";
            }
        }
        header("Location: index.php?c=users");
        exit;
    }

    // ====================================================
    // 🌟 4. ลบผู้ใช้งาน (ปรับเปลี่ยนเป็นรับ POST เพื่อความปลอดภัย)
    // ====================================================
    public function delete() {
        $this->requireMutation();
        $this->checkAuth();
        
        // เปลี่ยนการตรวจสอบมารับค่า $_POST
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
            $db = (new Database())->getConnection();
            $id = trim($_POST['id']);

            try {
                // 🌟 แก้ไข: ใช้ Query ตรงเพื่อดึงข้อมูลข้ามข้อจำกัดของ UserModel
                $stmtCheck = $db->prepare("SELECT id, role, hospital_id, name FROM users WHERE id = ?");
                $stmtCheck->execute([$id]);
                $target = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if (!$target) {
                    $_SESSION['error_msg'] = "ไม่พบข้อมูลผู้ใช้งานที่ต้องการลบ (อาจถูกลบไปก่อนหน้านี้)";
                    header("Location: index.php?c=users");
                    exit;
                }

                if (!$this->canManageUser($target['role'], $target['hospital_id'])) {
                    $_SESSION['error_msg'] = "⛔ ปฏิเสธ: ไม่สามารถลบผู้ดูแลระบบสูงสุดได้";
                } elseif ($id == $_SESSION['user']['id']) {
                    $_SESSION['error_msg'] = "คุณไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้";
                } else {
                    
                    // 🌟 1. ตรวจสอบว่ามีข้อมูลในตารางเวรหรือไม่
                    // (อัปเดตเปลี่ยน schedules เป็น shifts ตามฐานข้อมูลของระบบ)
                    try {
                        $checkStmt = $db->prepare("SELECT COUNT(*) FROM shifts WHERE user_id = ?");
                        $checkStmt->execute([$id]);
                        $hasSchedule = $checkStmt->fetchColumn() > 0;
                    } catch (PDOException $e) {
                        // หากตารางไม่มีในระบบ ให้ข้ามการเช็คไปเลย (ถือว่าไม่มีประวัติ)
                        $hasSchedule = false; 
                    }

                    if ($hasSchedule) {
                        // 🌟 2A. มีข้อมูลเวร -> ทำการ Soft Delete (ซ่อนจากตารางและระงับบัญชี)
                        $stmt = $db->prepare("UPDATE users SET is_deleted = 1, is_active = 0 WHERE id = ?");
                        $stmt->execute([$id]);
                        
                        LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_DELETE, "ระงับและซ่อนบัญชีผู้ใช้ ID: $id (ผูกตารางเวร)");
                        $_SESSION['success_msg'] = "ซ่อนบัญชีผู้ใช้งานสำเร็จ (ข้อมูลไม่ถูกลบถาวรเนื่องจากมีประวัติการเข้าเวร)";
                    } else {
                        // 🌟 2B. ไม่มีข้อมูลเวร -> ทำการ Hard Delete (ลบถาวร)
                        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$id]);
                        
                        if ($stmt->rowCount() > 0) {
                            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_DELETE, "ลบผู้ใช้ ID: $id ถาวร");
                            $_SESSION['success_msg'] = "ลบข้อมูลบุคลากรสำเร็จ";
                        } else {
                            $_SESSION['error_msg'] = "ไม่สามารถลบได้ เกิดข้อผิดพลาดทางข้อมูล";
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('UsersController error: ' . $e->getMessage());
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ";
            }
        } else {
             // ดักจับกรณีผู้ใช้เผลอกดลิงก์มาแบบ GET หรือไม่มีค่า id ส่งมา
             $_SESSION['error_msg'] = "คำสั่งลบไม่ถูกต้อง กรุณาทำรายการผ่านปุ่มในระบบเท่านั้น";
        }
        
        header("Location: index.php?c=users");
        exit;
    }

    // ====================================================
    // 🌟 5. สลับสถานะ ระงับ/เปิดใช้งาน (อัปเดตเก็บสาเหตุการระงับ)
    // ====================================================
    public function toggle() {
        $this->requireMutation();
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id']) && isset($_POST['status'])) {
            $db = (new Database())->getConnection();
            $id = trim($_POST['id']);
            $status = (int)$_POST['status'];

            // รับค่าจาก Popup
            $inactive_reason = !empty($_POST['inactive_reason']) ? $_POST['inactive_reason'] : null;
            $inactive_date = !empty($_POST['inactive_date']) ? $_POST['inactive_date'] : null;
            $inactive_note = !empty($_POST['inactive_note']) ? trim($_POST['inactive_note']) : null;

            try {
                $stmtCheck = $db->prepare("SELECT id, role, hospital_id, name FROM users WHERE id = ?");
                $stmtCheck->execute([$id]);
                $target = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if (!$target) {
                    $_SESSION['error_msg'] = "ไม่พบข้อมูลผู้ใช้งานในระบบ";
                } elseif (!$this->canManageUser($target['role'], $target['hospital_id'])) {
                    $_SESSION['error_msg'] = "ไม่สามารถจัดการบัญชีผู้ดูแลระบบสูงสุดได้";
                } elseif ($id == $_SESSION['user']['id']) {
                    $_SESSION['error_msg'] = "ไม่สามารถระงับบัญชีตนเองได้";
                } else {
                    
                    // กระบวนการอัปเดตข้อมูล (พร้อม Auto-migration หากไม่มีคอลัมน์)
                    $this->executeUpdateStatus($db, $id, $status, $inactive_reason, $inactive_date, $inactive_note, $target['name']);
                    
                }
            } catch (Exception $e) { 
                error_log('UsersController error: ' . $e->getMessage());
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ"; 
            }
        }
        header("Location: index.php?c=users");
        exit;
    }

    // ฟังก์ชันช่วยย่อยสำหรับอัปเดตสถานะและสร้างคอลัมน์อัตโนมัติ
    private function executeUpdateStatus($db, $id, $status, $reason, $date, $note, $target_name) {
        try {
            if ((int)$status === 0) {
                $stmt = $db->prepare(
                    "UPDATE users
                     SET is_active = 0,
                         inactive_reason = ?,
                         inactive_date = ?,
                         inactive_note = ?
                     WHERE id = ?"
                );
                $stmt->execute([$reason, $date ?: null, $note, $id]);
                $logTxt = "ระงับบัญชี (เหตุผล: {$reason})";
            } else {
                $stmt = $db->prepare(
                    "UPDATE users
                     SET is_active = 1,
                         inactive_reason = NULL,
                         inactive_date = NULL,
                         inactive_note = NULL
                     WHERE id = ?"
                );
                $stmt->execute([$id]);
                $logTxt = "เปิดใช้งาน";
            }

            LogsController::addLog(
                $db,
                $_SESSION['user']['id'],
                LogsController::ACTION_UPDATE,
                "{$logTxt} บัญชี ID: {$id}"
            );
            $_SESSION['success_msg'] = "อัปเดตสถานะ {$target_name} สำเร็จ";
        } catch (Throwable $e) {
            error_log('Users status update failed: ' . $e->getMessage());
            $_SESSION['error_msg'] = "ไม่สามารถอัปเดตสถานะผู้ใช้งานได้ กรุณาตรวจสอบฐานข้อมูลและลองใหม่";
        }
    }

    public function update_order() {
        $this->requireMutation();
        security_start_session();
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
                error_log('Users AJAX operation failed: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'ไม่สามารถบันทึกข้อมูลผู้ใช้งานได้']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
        }
        exit;
    }
    
    // ====================================================
    // 🌟 7. ลบหลายรายการพร้อมกัน (Bulk Delete)
    // ====================================================
    public function bulk_delete() {
        $this->requireMutation();
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ids'])) {
            $db = (new Database())->getConnection();
            
            // รับค่า JSON string แปลงกลับเป็น Array
            $ids = json_decode($_POST['ids'], true);
            $success_count = 0;
            $fail_count = 0;
            
            if (is_array($ids) && count($ids) > 0) {
                // เตรียมคำสั่งตรวจสอบและลบ
                $stmtGetUser = $db->prepare("SELECT id, role, hospital_id FROM users WHERE id = ?");
                $stmtSoftDelete = $db->prepare("UPDATE users SET is_deleted = 1, is_active = 0 WHERE id = ?");
                $stmtHardDelete = $db->prepare("DELETE FROM users WHERE id = ?");
                
                foreach ($ids as $id) {
                    try {
                        $stmtGetUser->execute([$id]);
                        $target = $stmtGetUser->fetch(PDO::FETCH_ASSOC);
                        
                        // ข้ามคนที่หาไม่เจอ, คนที่เป็นตัวเอง, หรือคนที่ไม่มีสิทธิ์ลบ
                        if (!$target || $id == $_SESSION['user']['id'] || !$this->canManageUser($target['role'], $target['hospital_id'])) {
                            $fail_count++;
                            continue;
                        }
                        
                        // ตรวจสอบข้อมูลในตารางเวร shifts (แบบป้องกัน Error กรณีไม่มีตาราง)
                        $hasSchedule = false;
                        try {
                            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM shifts WHERE user_id = ?");
                            $stmtCheck->execute([$id]);
                            $hasSchedule = $stmtCheck->fetchColumn() > 0;
                        } catch (PDOException $e) { }

                        if ($hasSchedule) {
                            // Soft Delete (ซ่อน)
                            $stmtSoftDelete->execute([$id]);
                            $success_count++;
                        } else {
                            // Hard Delete (ลบถาวร)
                            $stmtHardDelete->execute([$id]);
                            if ($stmtHardDelete->rowCount() > 0) {
                                $success_count++;
                            } else {
                                $fail_count++;
                            }
                        }
                    } catch (Exception $e) {
                        $fail_count++;
                    }
                }
                
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_DELETE, "ลบ/ซ่อนผู้ใช้เป็นกลุ่มจำนวน $success_count รายการ");
                
                if ($fail_count > 0) {
                    $_SESSION['success_msg'] = "ลบ/ซ่อนสำเร็จ $success_count รายการ, ข้าม $fail_count รายการ (อาจลบตัวเองหรือติดเงื่อนไข)";
                } else {
                    $_SESSION['success_msg'] = "ลบ/ซ่อนข้อมูลสำเร็จทั้งหมด $success_count รายการ";
                }
            }
        }
        header("Location: index.php?c=users");
        exit;
    }

    // ====================================================
    // 🌟 8. ระบบนำเข้าข้อมูล (CSV Import)
    // ====================================================
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
        header('Content-Disposition: attachment; filename=template_import_users.csv');

        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, [
            'Hospital_ID', 'Name', 'Username', 'Password', 'ID_Card',
            'Employee_Type', 'Position', 'Pos_Number', 'Phone', 'Role'
        ]);
        fputcsv($output, [
            '0',
            'นาย ตัวอย่าง ใจดี',
            'user_example',
            '',
            '',
            'ข้าราชการ/พนักงานท้องถิ่น',
            'พยาบาลวิชาชีพ',
            '',
            '0812345678',
            'STAFF'
        ]);
        fclose($output);
        exit;
    }

    public function import() {
        $this->requireMutation();
        $this->checkAuth();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || empty($_FILES['import_file'])) {
            header("Location: index.php?c=users");
            exit;
        }

        $file = $_FILES['import_file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            $_SESSION['error_msg'] = "ไม่สามารถอ่านไฟล์ CSV ที่อัปโหลดได้";
            header("Location: index.php?c=users");
            exit;
        }

        if ((int)($file['size'] ?? 0) > 2 * 1024 * 1024) {
            $_SESSION['error_msg'] = "ไฟล์ CSV ต้องมีขนาดไม่เกิน 2 MB";
            header("Location: index.php?c=users");
            exit;
        }

        $db = (new Database())->getConnection();
        $userModel = new UserModel($db);
        $handle = fopen($file['tmp_name'], 'rb');

        if ($handle === false) {
            $_SESSION['error_msg'] = "ไม่สามารถเปิดไฟล์ CSV ได้";
            header("Location: index.php?c=users");
            exit;
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        $expected = [
            'Hospital_ID','Name','Username','Password','ID_Card',
            'Employee_Type','Position','Pos_Number','Phone','Role'
        ];
        $normalizedHeader = array_map(static fn($v) => trim((string)$v), is_array($header) ? $header : []);

        if ($normalizedHeader !== $expected) {
            fclose($handle);
            $_SESSION['error_msg'] = "รูปแบบ CSV ไม่ถูกต้อง กรุณาดาวน์โหลดแม่แบบล่าสุด";
            header("Location: index.php?c=users");
            exit;
        }

        $currentRole = strtoupper((string)($_SESSION['user']['role'] ?? ''));
        $allowedRoles = ['SUPERADMIN', 'ADMIN', 'DIRECTOR', 'SCHEDULER', 'STAFF', 'HR'];
        $ok = 0;
        $fail = 0;
        $failReasons = [];
        $rowNo = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNo++;
            if (!array_filter($row, static fn($value) => trim((string)$value) !== '')) {
                continue;
            }

            if (count($row) < 10) {
                $fail++;
                $failReasons[] = "แถว {$rowNo}: จำนวนคอลัมน์ไม่ครบ";
                continue;
            }

            $name = trim((string)$row[1]);
            $username = trim((string)$row[2]);
            $password = (string)$row[3];
            $role = strtoupper(trim((string)$row[9]));

            if ($name === '' || $username === '') {
                $fail++;
                $failReasons[] = "แถว {$rowNo}: ชื่อหรือ Username ว่าง";
                continue;
            }

            if (mb_strlen($password, 'UTF-8') < 8) {
                $fail++;
                $failReasons[] = "แถว {$rowNo}: Password ต้องอย่างน้อย 8 ตัวอักษร";
                continue;
            }

            if (!in_array($role, $allowedRoles, true)) {
                $fail++;
                $failReasons[] = "แถว {$rowNo}: Role ไม่ถูกต้อง";
                continue;
            }

            if ($currentRole !== 'SUPERADMIN' && $role === 'SUPERADMIN') {
                $fail++;
                $failReasons[] = "แถว {$rowNo}: ไม่มีสิทธิ์สร้าง SUPERADMIN";
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

            $hospitalIdRaw = trim((string)$row[0]);
            $hospitalId = ($hospitalIdRaw === '' || $hospitalIdRaw === '0') ? null : (int)$hospitalIdRaw;

            $data = [
                'hospital_id' => $hospitalId,
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'id_card' => $idCard,
                'employee_type' => trim((string)$row[5]) ?: 'ข้าราชการ/พนักงานท้องถิ่น',
                'position' => trim((string)$row[6]),
                'position_number' => trim((string)$row[7]),
                'phone' => trim((string)$row[8]),
                'role' => $role,
                'type' => '',
                'pay_rate_id' => null,
                'start_date' => null,
                'color_theme' => 'primary',
            ];

            if ($userModel->addUser($data)) {
                $ok++;
            } else {
                $fail++;
                $failReasons[] = "แถว {$rowNo}: บันทึกไม่สำเร็จ";
            }
        }

        fclose($handle);

        $summary = "นำเข้าสำเร็จ {$ok} รายการ, ข้าม/ผิดพลาด {$fail} รายการ";
        LogsController::addLog(
            $db,
            (int)$_SESSION['user']['id'],
            LogsController::ACTION_IMPORT,
            $summary
        );

        if ($fail > 0) {
            $_SESSION['error_msg'] = $summary . (!empty($failReasons)
                ? " — " . implode('; ', array_slice($failReasons, 0, 3))
                : '');
        } else {
            $_SESSION['success_msg'] = $summary;
        }

        header("Location: index.php?c=users");
        exit;
    }

    // ====================================================
    // 🌟 9. ปลดล็อกบัญชีที่ถูกซ่อน (Restore / Undelete)
    // ====================================================
    public function restore() {
        $this->requireMutation();
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
            $db = (new Database())->getConnection();
            $id = $_POST['id'];

            try {
                // เปลี่ยนสถานะ is_deleted กลับมาเป็น 0 และเปิดใช้งานบัญชี
                $stmt = $db->prepare("UPDATE users SET is_deleted = 0, is_active = 1 WHERE id = ?");
                if ($stmt->execute([$id])) {
                    LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_RESTORE, "ปลดล็อกบัญชี ID: $id กลับมาใช้งานใหม่");
                    $_SESSION['success_msg'] = "ปลดล็อกและกู้คืนบัญชีผู้ใช้งานสำเร็จ";
                } else {
                    $_SESSION['error_msg'] = "ไม่สามารถปลดล็อกบัญชีได้";
                }
            } catch (Exception $e) {
                error_log('UsersController error: ' . $e->getMessage());
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ";
            }
        }
        header("Location: index.php?c=users");
        exit;
    }
}
?>