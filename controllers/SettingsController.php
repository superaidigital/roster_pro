<?php
// ที่อยู่ไฟล์: controllers/SettingsController.php

require_once 'config/database.php';
require_once 'controllers/LogsController.php';

class SettingsController {

    // ========================================================
    // 🛡️ ส่วนที่ 1: ระบบจัดการสิทธิ์ (Access Control Helpers)
    // ========================================================

    private function requireAccess($allowed_roles = []) {
        if (session_status() === PHP_SESSION_NONE) { 
            session_start(); 
        }

        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }

        $user_role = $_SESSION['user']['role'];
        if (!empty($allowed_roles) && !in_array($user_role, $allowed_roles)) {
            $_SESSION['error_msg'] = "ปฏิเสธการเข้าถึง: คุณไม่มีสิทธิ์ใช้งานเมนูนี้";
            header("Location: index.php?c=dashboard&a=index");
            exit;
        }
    }

    private function requirePost() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_msg'] = "คำขอไม่ถูกต้อง (Invalid Request Method)";
            header("Location: index.php?c=dashboard&a=index");
            exit;
        }
    }

    // ========================================================
    // 🚦 ส่วนที่ 2: ระบบนำทางหลัก (Router)
    // ========================================================
    
    public function index() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN', 'DIRECTOR']);
        
        $role = $_SESSION['user']['role'];
        if (in_array($role, ['SUPERADMIN', 'ADMIN'])) {
            header("Location: index.php?c=settings&a=system"); 
        } else {
            header("Location: index.php?c=settings&a=hospital");
        }
        exit;
    }

    // ========================================================
    // 🏢 ส่วนที่ 3: ระดับผู้อำนวยการขึ้นไป (DIRECTOR, ADMIN, SUPERADMIN)
    // ========================================================

    public function hospital() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN', 'DIRECTOR']);
        
        $db = (new Database())->getConnection();
        require_once 'models/HospitalModel.php';
        $hospitalModel = new HospitalModel($db);

        $hospital_id = $_SESSION['user']['hospital_id'];
        if (isset($_GET['id']) && in_array($_SESSION['user']['role'], ['SUPERADMIN', 'ADMIN'])) {
            $hospital_id = $_GET['id'];
        }
        
        $hospital = $hospitalModel->getHospitalById($hospital_id);
        
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/settings/hospital.php';
        echo "</main></div></body></html>";
    }

    public function save_hospital() {
        $this->requirePost(); 
        $this->requireAccess(['SUPERADMIN', 'ADMIN', 'DIRECTOR']);

        $db = (new Database())->getConnection();
        require_once 'models/HospitalModel.php';
        $hospitalModel = new HospitalModel($db);

        $id = $_POST['id'] ?? null;

        $name = $_POST['name'] ?? '';
        $hospital_code = $_POST['hospital_code'] ?? null;
        $hospital_size = $_POST['hospital_size'] ?? 'S';
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;
        $email = $_POST['email'] ?? null;
        $phone = $_POST['phone'] ?? null;
        $address = $_POST['address'] ?? null;
        $sub_district = $_POST['sub_district'] ?? null;
        $district = $_POST['district'] ?? null;
        $province = $_POST['province'] ?? null;
        $zipcode = $_POST['zipcode'] ?? null;
        $morning = $_POST['morning_shift'] ?? null;
        $afternoon = $_POST['afternoon_shift'] ?? null;
        $night = $_POST['night_shift'] ?? null;
        
        $logo_path = null;

        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['logo']['tmp_name'];
            $fileName = $_FILES['logo']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadDir = 'public/uploads/logos/';
                if (!is_dir($uploadDir)) { 
                    mkdir($uploadDir, 0777, true); 
                }
                
                $newFileName = 'logo_' . ($id ? $id : 'new') . '_' . time() . '.' . $fileExtension;
                $destPath = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $logo_path = $destPath;
                    
                    if (!empty($id)) {
                        $current = $hospitalModel->getHospitalById($id);
                        if ($current && !empty($current['logo']) && file_exists($current['logo'])) {
                            if (strpos($current['logo'], 'default') === false) {
                                unlink($current['logo']);
                            }
                        }
                    }
                }
            } else {
                $_SESSION['error_msg'] = "ชนิดไฟล์รูปภาพไม่ถูกต้อง (อนุญาตเฉพาะ JPG และ PNG)";
                header("Location: index.php?c=settings&a=hospital" . ($id ? "&id=" . urlencode($id) : ""));
                exit;
            }
        }

        if (!empty($id)) {
            $result = $hospitalModel->updateHospital(
                $id, $name, $hospital_code, $hospital_size, 
                $latitude, $longitude, $email, $phone, 
                $address, $sub_district, $district, $province, $zipcode, 
                $morning, $afternoon, $night, $logo_path
            );
            
            if ($result) {
                LogsController::addLog($db, $_SESSION['user']['id'], 'UPDATE', "แก้ไขข้อมูล รพ.สต. ID: {$id} ({$name})");
                $_SESSION['success_msg'] = "บันทึกข้อมูลหน่วยบริการสำเร็จ";
            } else {
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลลงฐานข้อมูล";
            }
        } else {
            $new_id = $hospitalModel->addHospital(
                $name, $hospital_code, $hospital_size, 
                $latitude, $longitude, $email, $phone, 
                $address, $sub_district, $district, $province, $zipcode, 
                $morning, $afternoon, $night, $logo_path
            );

            if ($new_id) {
                $id = $new_id;
                LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "เพิ่มหน่วยบริการใหม่ ({$name})");
                $_SESSION['success_msg'] = "เพิ่มข้อมูลหน่วยบริการสำเร็จ";
            } else {
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการสร้างหน่วยบริการใหม่";
            }
        }

        if (isset($_POST['redirect_to']) && $_POST['redirect_to'] == 'hospitals') {
            header("Location: index.php?c=hospitals");
        } else {
            header("Location: index.php?c=settings&a=hospital&id=" . urlencode($id));
        }
        exit;
    }

    // ========================================================
    // ⚙️ ส่วนที่ 4: ระดับผู้ดูแลระบบขึ้นไป (ADMIN, SUPERADMIN)
    // ========================================================

    public function system() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        $db = (new Database())->getConnection();
        
        $settings = [];
        try {
            $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {}

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/settings/system.php';
        echo "</main></div></body></html>";
    }

    public function system_status() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        $db = (new Database())->getConnection();
        $status_data = [];

        try {
            $db_name = 'roster_pro_db'; 
            $stmt = $db->prepare("SELECT SUM(data_length + index_length) / 1024 / 1024 AS size FROM information_schema.TABLES WHERE table_schema = ?");
            $stmt->execute([$db_name]);
            $status_data['db_size'] = round($stmt->fetchColumn(), 2);

            $free_space = disk_free_space("/");
            $total_space = disk_total_space("/");
            $status_data['disk_free'] = round($free_space / 1024 / 1024 / 1024, 2); 
            $status_data['disk_total'] = round($total_space / 1024 / 1024 / 1024, 2); 
            $status_data['disk_usage_percent'] = round((($total_space - $free_space) / $total_space) * 100, 2);

            $status_data['php_version'] = PHP_VERSION;
            $status_data['os'] = PHP_OS;
            $status_data['db_server'] = $db->getAttribute(PDO::ATTR_SERVER_INFO);
            $status_data['db_status'] = 'Online';

        } catch (Exception $e) {
            $status_data['db_status'] = 'Offline / Error: ' . $e->getMessage();
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/settings/system_status.php';
        echo "</main></div></body></html>";
    }

    public function update_system() {
        $this->requirePost();
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);

        $db = (new Database())->getConnection();
        $section = $_POST['section'] ?? '';
        $settings_data = $_POST['settings'] ?? [];

        try {
            $db->beginTransaction();

            $check_stmt = $db->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
            $insert_stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
            $update_stmt = $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");

            if ($section === 'line_notify') {
                $settings_data['line_notify_on_submit'] = isset($settings_data['line_notify_on_submit']) ? '1' : '0';
                $settings_data['line_notify_on_request'] = isset($settings_data['line_notify_on_request']) ? '1' : '0';
                $settings_data['line_notify_on_holiday'] = isset($settings_data['line_notify_on_holiday']) ? '1' : '0';
            } elseif ($section === 'general') {
                $settings_data['maintenance_mode'] = isset($settings_data['maintenance_mode']) ? '1' : '0';
            }

            foreach ($settings_data as $key => $value) {
                $check_stmt->execute([$key]);
                $exists = $check_stmt->fetchColumn();

                if ($exists > 0) {
                    $update_stmt->execute([$value, $key]);
                } else {
                    $insert_stmt->execute([$key, $value]);
                }
            }

            $db->commit();
            $section_name = ($section === 'general') ? 'ข้อมูลทั่วไป' : 'LINE Notify';
            LogsController::addLog($db, $_SESSION['user']['id'], 'UPDATE', "อัปเดตตั้งค่าระบบส่วนกลาง ({$section_name})");
            $_SESSION['success_msg'] = "บันทึกการตั้งค่าเรียบร้อยแล้ว";
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }

        header("Location: index.php?c=settings&a=system");
        exit;
    }

    public function test_line() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);

        $db = (new Database())->getConnection();
        $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'line_notify_token'");
        $token = $stmt->fetchColumn();

        if (!empty($token)) {
            $url = "https://notify-api.line.me/api/notify";
            $message = "🟢 ทดสอบการเชื่อมต่อระบบ Roster Pro\nเวลา: " . date('d/m/Y H:i:s') . " น.\nข้อความนี้ส่งจากการกดทดสอบระบบ";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['message' => $message]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/x-www-form-urlencoded",
                "Authorization: Bearer " . $token
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200) {
                $_SESSION['success_msg'] = "ส่งข้อความทดสอบสำเร็จ! โปรดตรวจสอบในแอปพลิเคชัน LINE";
            } else {
                $_SESSION['error_msg'] = "ไม่สามารถส่งข้อความได้ (HTTP Code: $http_code)";
            }
        } else {
            $_SESSION['error_msg'] = "กรุณาตั้งค่า Token ก่อนทำการทดสอบ";
        }
        header("Location: index.php?c=settings&a=system");
        exit;
    }

    public function holidays() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        $db = (new Database())->getConnection();
        require_once 'models/HolidayModel.php';
        $holidayModel = new HolidayModel($db);
        $holidays = $holidayModel->getAllHolidays();

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/settings/holidays.php';
        echo "</main></div></body></html>";
    }

    public function save_holiday() {
        $this->requirePost();
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        $db = (new Database())->getConnection();
        require_once 'models/HolidayModel.php';
        $holidayModel = new HolidayModel($db);
        
        if (!empty($_POST['holiday_date']) && !empty($_POST['holiday_name'])) {
            $holidayModel->addHoliday($_POST['holiday_date'], $_POST['holiday_name']);
            LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "เพิ่มวันหยุดนักขัตฤกษ์: " . $_POST['holiday_name']);
            $_SESSION['success_msg'] = "เพิ่มวันหยุดเรียบร้อยแล้ว";
        }
        header("Location: index.php?c=settings&a=holidays");
        exit;
    }

    public function delete_holiday() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        $db = (new Database())->getConnection();
        require_once 'models/HolidayModel.php';
        $holidayModel = new HolidayModel($db);
        
        if (isset($_GET['id'])) {
            $holidayModel->deleteHoliday($_GET['id']);
            LogsController::addLog($db, $_SESSION['user']['id'], 'DELETE', "ลบวันหยุดนักขัตฤกษ์ ID: " . $_GET['id']);
            $_SESSION['success_msg'] = "ลบวันหยุดเรียบร้อยแล้ว";
        }
        header("Location: index.php?c=settings&a=holidays");
        exit;
    }

    public function sync_api() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
        $db = (new Database())->getConnection();
        require_once 'models/HolidayModel.php';
        $holidayModel = new HolidayModel($db);
        $result = $holidayModel->syncHolidaysFromAPI($year);

        if($result['success']) {
            LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "ซิงค์วันหยุดจาก API ปี {$year} สำเร็จ ({$result['added']} วัน)");
            $_SESSION['success_msg'] = "ดึงข้อมูลสำเร็จ! เพิ่มวันหยุดใหม่ {$result['added']} วัน (ข้ามวันซ้ำ {$result['skipped']} วัน)";
        } else {
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาด: " . $result['message'];
        }
        header("Location: index.php?c=settings&a=holidays");
        exit;
    }

    public function shift_types() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        $db = (new Database())->getConnection();
        require_once 'models/PayRateModel.php';
        $payRateModel = new PayRateModel($db);
        $pay_rates = $payRateModel->getAllRates();

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/settings/shift_types.php';
        echo "</main></div></body></html>";
    }

    public function pay_rates() {
        $this->shift_types();
    }

    public function save_payrate() {
        $this->requirePost();
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        $db = (new Database())->getConnection();
        require_once 'models/PayRateModel.php';
        $payRateModel = new PayRateModel($db);
        
        if (!empty($_POST['keywords'])) {
            $data = [
                'keywords' => $_POST['keywords'],
                'rate_r' => $_POST['rate_r'] ?? 0,
                'rate_y' => $_POST['rate_y'] ?? 0,
                'rate_b' => $_POST['rate_b'] ?? 0
            ];
            
            if (!empty($_POST['id'])) {
                $payRateModel->updateRate($_POST['id'], $data);
                LogsController::addLog($db, $_SESSION['user']['id'], 'UPDATE', "แก้ไขเรทค่าตอบแทน ID: " . $_POST['id']);
                $_SESSION['success_msg'] = "แก้ไขเรทค่าตอบแทนเรียบร้อยแล้ว";
            } else {
                $payRateModel->addRate($data);
                LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "เพิ่มเรทค่าตอบแทนใหม่");
                $_SESSION['success_msg'] = "เพิ่มเรทค่าตอบแทนเรียบร้อยแล้ว";
            }
        }
        header("Location: index.php?c=settings&a=shift_types");
        exit;
    }

    public function delete_payrate() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        $db = (new Database())->getConnection();
        require_once 'models/PayRateModel.php';
        $payRateModel = new PayRateModel($db);
        
        if (isset($_GET['id'])) {
            $payRateModel->deleteRate($_GET['id']);
            LogsController::addLog($db, $_SESSION['user']['id'], 'DELETE', "ลบเรทค่าตอบแทน ID: " . $_GET['id']);
            $_SESSION['success_msg'] = "ลบเรทค่าตอบแทนเรียบร้อยแล้ว";
        }
        header("Location: index.php?c=settings&a=shift_types");
        exit;
    }

    public function system_logs() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        header("Location: index.php?c=logs&a=index");
        exit;
    }

    public function menus() {
        $this->requireAccess(['SUPERADMIN']);
        $db = (new Database())->getConnection();
        
        $system_roles = ['STAFF', 'SCHEDULER', 'DIRECTOR', 'ADMIN', 'SUPERADMIN'];
        $menus = [];
        
        try {
            $stmt = $db->query("SELECT * FROM system_menus ORDER BY display_order ASC, id ASC");
            $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $_SESSION['error_msg'] = "ไม่สามารถดึงข้อมูลเมนูได้: " . $e->getMessage();
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/settings/menus.php';
        echo "</main></div></body></html>";
    }

    public function save_menus() {
        $this->requirePost();
        $this->requireAccess(['SUPERADMIN']);
        
        $db = (new Database())->getConnection();
        $menu_data = $_POST['menu_data'] ?? [];

        if (!empty($menu_data)) {
            try {
                $db->beginTransaction();
                $update_stmt = $db->prepare("UPDATE system_menus SET is_active = ?, allowed_roles = ? WHERE id = ?");
                $check_stmt = $db->prepare("SELECT * FROM system_menus WHERE id = ?");

                foreach ($menu_data as $menu_id => $data) {
                    $roles_array = $data['roles'] ?? [];
                    $is_active = isset($data['is_active']) ? 1 : 0;
                    
                    $check_stmt->execute([$menu_id]);
                    $menu_row = $check_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $menu_link = $menu_row['menu_link'] ?? $menu_row['url'] ?? $menu_row['menu_url'] ?? $menu_row['link'] ?? '';
                    
                    if (strpos($menu_link, 'c=settings') !== false) {
                        $is_active = 1; 
                        if (!in_array('SUPERADMIN', $roles_array)) {
                            $roles_array[] = 'SUPERADMIN'; 
                        }
                    }

                    $allowed_roles_string = implode(',', $roles_array);
                    $update_stmt->execute([$is_active, $allowed_roles_string, $menu_id]);
                }
                
                $db->commit();
                LogsController::addLog($db, $_SESSION['user']['id'], 'UPDATE', "ปรับปรุงสิทธิ์การเข้าถึงเมนูระบบ (Permission Matrix)");
                $_SESSION['success_msg'] = "บันทึกการกำหนดสิทธิ์การเข้าถึงเมนูเรียบร้อยแล้ว";

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_msg'] = "ไม่มีข้อมูลส่งมาบันทึก";
        }
        
        header("Location: index.php?c=settings&a=menus");
        exit;
    }

    // ========================================================
    // 🌟 ระบบสำรองฐานข้อมูล (Database Backup & Auto Backup)
    // ========================================================

    public function backup() {
        $this->requireAccess(['SUPERADMIN']); 
        $db = (new Database())->getConnection();

        $db_stats = [];
        try {
            $stmt = $db->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $db_stats['total_tables'] = count($tables);
            
            $stmt_history = $db->query("
                SELECT l.created_at, l.ip_address, u.name 
                FROM logs l 
                LEFT JOIN users u ON l.user_id = u.id 
                WHERE l.action = 'BACKUP' 
                ORDER BY l.created_at DESC LIMIT 10
            ");
            $backup_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $db_stats['total_tables'] = 0;
            $backup_history = [];
        }

        // ดึงรายการไฟล์ Backup ที่อยู่ในเซิร์ฟเวอร์
        $server_backups = [];
        $backup_dir = 'public/uploads/Backup/';
        if (is_dir($backup_dir)) {
            $files = scandir($backup_dir);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                    $filepath = $backup_dir . $file;
                    $server_backups[] = [
                        'filename' => $file,
                        'size' => round(filesize($filepath) / 1024, 2), // KB
                        'date' => date("d/m/Y H:i:s", filemtime($filepath)),
                        'path' => $filepath
                    ];
                }
            }
            // เรียงจากใหม่ไปเก่า
            usort($server_backups, function($a, $b) {
                return strtotime(str_replace('/', '-', $b['date'])) - strtotime(str_replace('/', '-', $a['date']));
            });
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/settings/backup.php';
        echo "</main></div></body></html>";
    }

    // ฟังก์ชันช่วยสร้าง String SQL สำหรับ Backup
    private function generateSqlScript($db) {
        $tables = [];
        $sqlScript = "-- ==========================================================\n";
        $sqlScript .= "-- ระบบสำรองฐานข้อมูล Roster Pro (Backup Data)\n";
        $sqlScript .= "-- วันที่สร้างไฟล์: " . date('Y-m-d H:i:s') . "\n";
        $sqlScript .= "-- ==========================================================\n\n";
        $sqlScript .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $query = $db->query('SHOW TABLES');
        while($row = $query->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        foreach ($tables as $table) {
            $query = $db->query("SHOW CREATE TABLE `$table`");
            $row = $query->fetch(PDO::FETCH_NUM);
            $sqlScript .= "-- โครงสร้างตาราง `$table`\n";
            $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
            $sqlScript .= $row[1] . ";\n\n";

            $query = $db->query("SELECT * FROM `$table`");
            $columnCount = $query->columnCount();
            $rowCount = $query->rowCount();

            if ($rowCount > 0) {
                $sqlScript .= "-- ข้อมูลตาราง `$table`\n";
                while ($row = $query->fetch(PDO::FETCH_NUM)) {
                    $sqlScript .= "INSERT INTO `$table` VALUES(";
                    for ($j = 0; $j < $columnCount; $j++) {
                        if (isset($row[$j])) {
                            $row[$j] = addslashes($row[$j]);
                            $row[$j] = str_replace("\n", "\\n", $row[$j]);
                            $sqlScript .= '"' . $row[$j] . '"';
                        } else {
                            $sqlScript .= 'NULL'; 
                        }
                        if ($j < ($columnCount - 1)) {
                            $sqlScript .= ',';
                        }
                    }
                    $sqlScript .= ");\n";
                }
                $sqlScript .= "\n";
            }
        }
        $sqlScript .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $sqlScript;
    }

    // ดาวน์โหลดทันที (ของเดิม)
    public function do_backup() {
        $this->requirePost();
        $this->requireAccess(['SUPERADMIN']); 
        
        set_time_limit(300); 
        ini_set('memory_limit', '256M');

        $db = (new Database())->getConnection();

        try {
            $sqlScript = $this->generateSqlScript($db);
            LogsController::addLog($db, $_SESSION['user']['id'], 'BACKUP', "ส่งออกไฟล์สำรองฐานข้อมูล (.sql)");

            $backup_file_name = 'roster_pro_backup_' . date('Ymd_His') . '.sql';
            
            header('Content-Type: application/octet-stream');
            header("Content-Transfer-Encoding: Binary"); 
            header("Content-disposition: attachment; filename=\"".$backup_file_name."\""); 
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Expires: 0');
            
            echo $sqlScript;
            exit;

        } catch (Exception $e) {
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการสำรองข้อมูล: " . $e->getMessage();
            header("Location: index.php?c=settings&a=backup");
            exit;
        }
    }

    // ฟังก์ชันใหม่: สร้างไฟล์ Backup บันทึกลง Server โดยแอดมินกดเอง
    public function do_server_backup() {
        $this->requirePost();
        $this->requireAccess(['SUPERADMIN']);
        
        set_time_limit(300); 
        ini_set('memory_limit', '256M');

        $db = (new Database())->getConnection();
        $backup_dir = 'public/uploads/Backup/';

        try {
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0777, true);
            }

            $sqlScript = $this->generateSqlScript($db);
            
            // ชื่อไฟล์ระบุปีและเดือน เพื่อให้เก็บรายเดือน
            $backup_file_name = 'roster_pro_monthly_' . date('Y_m_d_His') . '.sql';
            $filepath = $backup_dir . $backup_file_name;

            if (file_put_contents($filepath, $sqlScript) !== false) {
                LogsController::addLog($db, $_SESSION['user']['id'], 'BACKUP', "สำรองข้อมูลจัดเก็บลงเซิร์ฟเวอร์ ({$backup_file_name})");
                $_SESSION['success_msg'] = "บันทึกไฟล์สำรองข้อมูลลงเซิร์ฟเวอร์เรียบร้อยแล้ว";
            } else {
                $_SESSION['error_msg'] = "ไม่สามารถเขียนไฟล์ลงในโฟลเดอร์ public/uploads/Backup/ ได้ โปรดตรวจสอบ Permission (CHMOD 777)";
            }

        } catch (Exception $e) {
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }

        header("Location: index.php?c=settings&a=backup");
        exit;
    }

    // ฟังก์ชันใหม่: ลบไฟล์ Backup ใน Server
    public function delete_server_backup() {
        $this->requireAccess(['SUPERADMIN']);
        $filename = $_GET['file'] ?? '';
        $filepath = 'public/uploads/Backup/' . basename($filename);

        if (!empty($filename) && file_exists($filepath)) {
            unlink($filepath);
            $db = (new Database())->getConnection();
            LogsController::addLog($db, $_SESSION['user']['id'], 'DELETE', "ลบไฟล์สำรองข้อมูลในเซิร์ฟเวอร์ ({$filename})");
            $_SESSION['success_msg'] = "ลบไฟล์ {$filename} เรียบร้อยแล้ว";
        } else {
            $_SESSION['error_msg'] = "ไม่พบไฟล์ที่ต้องการลบ";
        }
        header("Location: index.php?c=settings&a=backup");
        exit;
    }

    // ฟังก์ชันใหม่: URL สำหรับให้ Cron Job เรียกใช้งาน (ไม่ต้อง Login)
    // การตั้งค่า Cron: curl -s "http://yourdomain.com/index.php?c=settings&a=cron_monthly_backup&key=YOUR_SECRET"
    public function cron_monthly_backup() {
        // กำหนด Key เพื่อป้องกันคนอื่นเรียก URL นี้เล่น
        $secret_key = "ROSTER_PRO_CRON_2026"; 
        $provided_key = $_GET['key'] ?? '';

        if ($provided_key !== $secret_key) {
            die("Access Denied: Invalid Cron Key.");
        }

        set_time_limit(300); 
        ini_set('memory_limit', '256M');

        $db = (new Database())->getConnection();
        $backup_dir = 'public/uploads/Backup/';

        try {
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0777, true);
            }

            // เช็คว่าเดือนนี้มีไฟล์แล้วหรือยัง (ถ้าไม่อยากให้ทับกันรายวัน)
            $current_month_prefix = 'roster_pro_autobackup_' . date('Y_m_');
            $files = scandir($backup_dir);
            $already_backed_up = false;
            foreach ($files as $file) {
                if (strpos($file, $current_month_prefix) !== false) {
                    $already_backed_up = true;
                    break;
                }
            }

            if (!$already_backed_up) {
                $sqlScript = $this->generateSqlScript($db);
                $backup_file_name = $current_month_prefix . date('d_His') . '.sql';
                $filepath = $backup_dir . $backup_file_name;

                if (file_put_contents($filepath, $sqlScript) !== false) {
                    LogsController::addLog($db, 0, 'BACKUP', "[CRON JOB] สำรองข้อมูลอัตโนมัติประจำเดือน ({$backup_file_name})");
                    echo "Cron Backup Success: {$backup_file_name}";
                } else {
                    echo "Cron Backup Failed: Cannot write file.";
                }
            } else {
                echo "Cron Backup Skipped: Already backed up this month.";
            }

        } catch (Exception $e) {
            echo "Cron Backup Error: " . $e->getMessage();
        }
        exit;
    }

    // ========================================================
    // ⚠️ ระบบล้างข้อมูล (Factory Reset)
    // ========================================================

    public function factory_reset() {
        $this->requirePost();
        $this->requireAccess(['SUPERADMIN']); // อนุญาตเฉพาะ SUPERADMIN เท่านั้น

        $confirm_code = trim($_POST['confirm_code'] ?? '');
        
        if ($confirm_code !== 'RESET-CONFIRM') {
            $_SESSION['error_msg'] = "รหัสยืนยันไม่ถูกต้อง การล้างข้อมูลถูกยกเลิก";
            header("Location: index.php?c=settings&a=system");
            exit;
        }

        $db = (new Database())->getConnection();

        try {
            $db->exec("SET FOREIGN_KEY_CHECKS=0;");

            $tables_to_clear = [
                'shifts', 'rosters', 'roster_details', 'roster_status', 
                'leaves', 'leave_requests', 'shift_swaps', 'logs', 
                'system_logs', 'notifications'
            ];

            foreach ($tables_to_clear as $table) {
                $stmt = $db->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    $db->exec("TRUNCATE TABLE `$table`");
                }
            }

            $stmt = $db->query("SHOW TABLES LIKE 'leave_balances'");
            if ($stmt->rowCount() > 0) {
                $db->exec("UPDATE leave_balances SET used_days = 0, carried_over_days = 0");
            }

            $db->exec("SET FOREIGN_KEY_CHECKS=1;");
            LogsController::addLog($db, $_SESSION['user']['id'], 'DELETE', "FACTORY RESET: ล้างข้อมูลระบบปฏิบัติการทั้งหมดเริ่มต้นรอบปีใหม่");
            $_SESSION['success_msg'] = "ล้างข้อมูลตารางเวรและประวัติต่างๆ เรียบร้อยแล้ว ระบบพร้อมสำหรับการเริ่มต้นใหม่";

        } catch (Exception $e) {
            $db->exec("SET FOREIGN_KEY_CHECKS=1;"); 
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการล้างข้อมูล: " . $e->getMessage();
        }

        header("Location: index.php?c=settings&a=system");
        exit;
    }
}
?>