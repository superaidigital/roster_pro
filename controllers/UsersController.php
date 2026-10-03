<?php
// ที่อยู่ไฟล์: controllers/UsersController.php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'models/UserModel.php';
require_once 'models/HospitalModel.php';
require_once 'models/PayRateModel.php';
require_once 'controllers/LogsController.php'; 

class UsersController {
    
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
                'position' => trim($_POST['position'] ?? ''),
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
                    LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_CREATE, "เพิ่มบุคลากรใหม่: " . $data['name']);
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
                $_SESSION['error_msg'] = "Error: " . $e->getMessage();
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
                $_SESSION['error_msg'] = "Error deleting user: " . $e->getMessage();
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
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาดทางเทคนิค: " . $e->getMessage(); 
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
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
        LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_EXPORT, "ดาวน์โหลดแม่แบบนำเข้าบุคลากร (CSV)");
        
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
        $this->requireMutation();
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
                    'position' => trim($row[6] ?? ''), 
                    'position_number' => trim($row[7] ?? ''),
                    'phone' => trim($row[8] ?? ''),
                    'role' => strtoupper(trim($row[9] ?? 'STAFF')),
                    'color_theme' => 'primary'
                ];

                if ($userModel->addUser($importData)) $ok++; else $fail++;
            }
            fclose($handle);
            $_SESSION['success_msg'] = "นำเข้าสำเร็จ $ok รายการ, ข้าม/ผิดพลาด $fail รายการ";
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_IMPORT, "นำเข้าบุคลากรผ่าน CSV สำเร็จ $ok คน");
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
                $_SESSION['error_msg'] = "Error restoring user: " . $e->getMessage();
            }
        }
        header("Location: index.php?c=users");
        exit;
    }
}
?>