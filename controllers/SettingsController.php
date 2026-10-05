<?php
// ที่อยู่ไฟล์: controllers/SettingsController.php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'controllers/LogsController.php';
require_once 'lib/MaintenanceMode.php';
require_once 'lib/ReleaseIdentity.php';

class SettingsController {

    // ========================================================
    // 🛡️ ส่วนที่ 1: ระบบจัดการสิทธิ์ (Access Control Helpers)
    // ========================================================

    private function requireAccess($allowed_roles = []) {
        security_start_session();

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
        security_start_session();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $_SESSION['error_msg'] = "คำขอไม่ถูกต้อง กรุณาทำรายการผ่านแบบฟอร์มในระบบ";
            header("Location: index.php?c=dashboard&a=index");
            exit;
        }

        if (!security_is_valid_post_csrf()) {
            http_response_code(403);
            $_SESSION['error_msg'] = "คำขอหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่";
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

        $hospital_id = (int)($_SESSION['user']['hospital_id'] ?? 0);
        if (isset($_GET['id']) && in_array($_SESSION['user']['role'], ['SUPERADMIN', 'ADMIN'], true)) {
            $hospital_id = max(0, (int)$_GET['id']);
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

        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));
        $isGlobalAdmin = in_array($role, ['SUPERADMIN', 'ADMIN'], true);
        $sessionHospitalId = (int)($_SESSION['user']['hospital_id'] ?? 0);

        $id = (int)($_POST['id'] ?? 0);
        if (!$isGlobalAdmin) {
            if ($sessionHospitalId <= 0) {
                $_SESSION['error_msg'] = "ไม่พบหน่วยบริการของบัญชีผู้ใช้งาน";
                header("Location: index.php?c=settings&a=hospital");
                exit;
            }
            $id = $sessionHospitalId;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $hospital_code = trim((string)($_POST['hospital_code'] ?? ''));
        $hospital_size = strtoupper(trim((string)($_POST['hospital_size'] ?? 'S')));
        $latitudeRaw = trim((string)($_POST['latitude'] ?? ''));
        $longitudeRaw = trim((string)($_POST['longitude'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $sub_district = trim((string)($_POST['sub_district'] ?? ''));
        $district = trim((string)($_POST['district'] ?? ''));
        $province = trim((string)($_POST['province'] ?? ''));
        $zipcode = trim((string)($_POST['zipcode'] ?? ''));
        $morning = trim((string)($_POST['morning_shift'] ?? ''));
        $afternoon = trim((string)($_POST['afternoon_shift'] ?? ''));
        $night = trim((string)($_POST['night_shift'] ?? ''));

        if ($name === '' || mb_strlen($name, 'UTF-8') > 255) {
            $_SESSION['error_msg'] = "กรุณาระบุชื่อหน่วยบริการให้ถูกต้อง";
            header("Location: index.php?c=settings&a=hospital" . ($id > 0 ? "&id=" . $id : ""));
            exit;
        }

        if (!in_array($hospital_size, ['S', 'M', 'L', 'XL'], true)) {
            $hospital_size = 'S';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error_msg'] = "รูปแบบอีเมลไม่ถูกต้อง";
            header("Location: index.php?c=settings&a=hospital" . ($id > 0 ? "&id=" . $id : ""));
            exit;
        }

        $latitude = null;
        $longitude = null;
        if ($latitudeRaw !== '') {
            if (!is_numeric($latitudeRaw) || (float)$latitudeRaw < -90 || (float)$latitudeRaw > 90) {
                $_SESSION['error_msg'] = "ค่าละติจูดต้องอยู่ระหว่าง -90 ถึง 90";
                header("Location: index.php?c=settings&a=hospital" . ($id > 0 ? "&id=" . $id : ""));
                exit;
            }
            $latitude = (string)(float)$latitudeRaw;
        }
        if ($longitudeRaw !== '') {
            if (!is_numeric($longitudeRaw) || (float)$longitudeRaw < -180 || (float)$longitudeRaw > 180) {
                $_SESSION['error_msg'] = "ค่าลองจิจูดต้องอยู่ระหว่าง -180 ถึง 180";
                header("Location: index.php?c=settings&a=hospital" . ($id > 0 ? "&id=" . $id : ""));
                exit;
            }
            $longitude = (string)(float)$longitudeRaw;
        }

        $hospital_code = $hospital_code !== '' ? mb_substr($hospital_code, 0, 20, 'UTF-8') : null;
        $email = $email !== '' ? mb_substr($email, 0, 150, 'UTF-8') : null;
        $phone = $phone !== '' ? mb_substr($phone, 0, 50, 'UTF-8') : null;
        $address = $address !== '' ? mb_substr($address, 0, 255, 'UTF-8') : null;
        $sub_district = $sub_district !== '' ? mb_substr($sub_district, 0, 100, 'UTF-8') : null;
        $district = $district !== '' ? mb_substr($district, 0, 100, 'UTF-8') : null;
        $province = $province !== '' ? mb_substr($province, 0, 100, 'UTF-8') : null;
        $zipcode = $zipcode !== '' ? mb_substr($zipcode, 0, 10, 'UTF-8') : null;
        $morning = $morning !== '' ? mb_substr($morning, 0, 100, 'UTF-8') : null;
        $afternoon = $afternoon !== '' ? mb_substr($afternoon, 0, 100, 'UTF-8') : null;
        $night = $night !== '' ? mb_substr($night, 0, 100, 'UTF-8') : null;

        $logo_path = null;
        $old_logo_path = null;

        if (isset($_FILES['logo']) && (int)($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['logo'];
            $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($uploadError !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
                $_SESSION['error_msg'] = "อัปโหลดโลโก้ไม่สำเร็จ กรุณาลองใหม่";
                header("Location: index.php?c=settings&a=hospital" . ($id > 0 ? "&id=" . $id : ""));
                exit;
            }

            if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 2 * 1024 * 1024) {
                $_SESSION['error_msg'] = "ไฟล์โลโก้ต้องมีขนาดไม่เกิน 2 MB";
                header("Location: index.php?c=settings&a=hospital" . ($id > 0 ? "&id=" . $id : ""));
                exit;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file((string)$file['tmp_name']);
            $allowedMime = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
            ];

            if (!isset($allowedMime[$mime])) {
                $_SESSION['error_msg'] = "ชนิดไฟล์โลโก้ไม่ถูกต้อง อนุญาตเฉพาะ JPG และ PNG";
                header("Location: index.php?c=settings&a=hospital" . ($id > 0 ? "&id=" . $id : ""));
                exit;
            }

            $uploadDir = 'public/uploads/logos/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                $_SESSION['error_msg'] = "ไม่สามารถเตรียมพื้นที่อัปโหลดโลโก้ได้";
                header("Location: index.php?c=settings&a=hospital" . ($id > 0 ? "&id=" . $id : ""));
                exit;
            }

            try {
                $randomPart = bin2hex(random_bytes(12));
            } catch (Throwable $e) {
                $randomPart = hash('sha256', uniqid('', true));
            }

            $newFileName = 'logo_' . $randomPart . '.' . $allowedMime[$mime];
            $destPath = $uploadDir . $newFileName;

            if (!move_uploaded_file((string)$file['tmp_name'], $destPath)) {
                $_SESSION['error_msg'] = "ไม่สามารถบันทึกไฟล์โลโก้ได้";
                header("Location: index.php?c=settings&a=hospital" . ($id > 0 ? "&id=" . $id : ""));
                exit;
            }

            $logo_path = $destPath;

            if ($id > 0) {
                $current = $hospitalModel->getHospitalById($id);
                $candidateOldLogo = (string)($current['logo'] ?? '');
                if ($candidateOldLogo !== '' && strpos($candidateOldLogo, 'default') === false) {
                    $old_logo_path = $candidateOldLogo;
                }
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
                if ($logo_path && $old_logo_path) {
                    $logoRoot = realpath('public/uploads/logos/');
                    $oldReal = realpath($old_logo_path);
                    if ($logoRoot !== false && $oldReal !== false && str_starts_with($oldReal, $logoRoot . DIRECTORY_SEPARATOR) && is_file($oldReal)) {
                        @unlink($oldReal);
                    }
                }

                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "แก้ไขข้อมูล รพ.สต. ID: {$id} ({$name})");
                $_SESSION['success_msg'] = "บันทึกข้อมูลหน่วยบริการสำเร็จ";
            } else {
                if ($logo_path && is_file($logo_path)) {
                    @unlink($logo_path);
                }
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลลงฐานข้อมูล";
            }
        } else {
            if (!$isGlobalAdmin) {
                $_SESSION['error_msg'] = "คุณไม่มีสิทธิ์เพิ่มหน่วยบริการใหม่";
                header("Location: index.php?c=settings&a=hospital");
                exit;
            }

            $new_id = $hospitalModel->addHospital(
                $name, $hospital_code, $hospital_size, 
                $latitude, $longitude, $email, $phone, 
                $address, $sub_district, $district, $province, $zipcode, 
                $morning, $afternoon, $night, $logo_path
            );

            if ($new_id) {
                $id = $new_id;
                // 🌟 บันทึก Log: สร้างหน่วยบริการใหม่
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_CREATE, "เพิ่มหน่วยบริการใหม่ ({$name})");
                $_SESSION['success_msg'] = "เพิ่มข้อมูลหน่วยบริการสำเร็จ";
            } else {
                if ($logo_path && is_file($logo_path)) {
                    @unlink($logo_path);
                }
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

        $maintenance_state = MaintenanceMode::safeStatus();
        $release_id = ReleaseIdentity::current();

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
            $db_name = getenv('DB_NAME') ?: 'roster_pro_db'; 
            $stmt = $db->prepare("SELECT SUM(data_length + index_length) / 1024 / 1024 AS size FROM information_schema.TABLES WHERE table_schema = ?");
            $stmt->execute([$db_name]);
            $status_data['db_size'] = round($stmt->fetchColumn(), 2);

            $free_space = @disk_free_space("/");
            $total_space = @disk_total_space("/");
            if ($free_space !== false && $total_space !== false && $total_space > 0) {
                $status_data['disk_free'] = round($free_space / 1024 / 1024 / 1024, 2);
                $status_data['disk_total'] = round($total_space / 1024 / 1024 / 1024, 2);
                $status_data['disk_usage_percent'] = round((($total_space - $free_space) / $total_space) * 100, 2);
            } else {
                $status_data['disk_free'] = null;
                $status_data['disk_total'] = null;
                $status_data['disk_usage_percent'] = null;
            }

            $status_data['php_version'] = PHP_VERSION;
            $status_data['os'] = PHP_OS;
            $status_data['db_server'] = $db->getAttribute(PDO::ATTR_SERVER_INFO);
            $status_data['db_status'] = 'Online';

            $status_data['dashboard_cache_ttl'] = max(5, min(300, (int)(getenv('DASHBOARD_CACHE_TTL') ?: 20)));
            $status_data['slow_request_threshold_ms'] = max(250, min(30000, (int)(getenv('SLOW_REQUEST_THRESHOLD_MS') ?: 1500)));
            $status_data['opcache_enabled'] = filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN)
                ? 'Enabled'
                : 'Disabled';

            $cacheDir = getenv('PERFORMANCE_CACHE_DIR') ?: 'storage/cache';
            $projectRoot = realpath(dirname(__DIR__));
            if ($projectRoot !== false
                && !str_starts_with($cacheDir, DIRECTORY_SEPARATOR)
                && !preg_match('/^[A-Za-z]:[\\\\\/]/', $cacheDir)) {
                $cacheDir = $projectRoot . '/' . ltrim($cacheDir, '/\\');
            }

            $cacheFiles = is_dir($cacheDir) ? (glob(rtrim($cacheDir, '/\\') . '/*.json') ?: []) : [];
            $cacheBytes = 0;
            foreach ($cacheFiles as $cacheFile) {
                if (is_file($cacheFile)) {
                    $cacheBytes += (int)(filesize($cacheFile) ?: 0);
                }
            }
            $status_data['performance_cache_files'] = count($cacheFiles);
            $status_data['performance_cache_mb'] = round($cacheBytes / 1024 / 1024, 2);
            $maintenanceState = MaintenanceMode::safeStatus();
            $status_data['maintenance_enabled'] = (bool)($maintenanceState['enabled'] ?? false);
            $status_data['maintenance_started_at'] = (string)($maintenanceState['started_at'] ?? '');
            $status_data['release_id'] = ReleaseIdentity::current();

        } catch (Exception $e) {
            error_log('System status check failed: ' . $e->getMessage());
            $status_data['db_status'] = 'Offline / Error';
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
                unset($settings_data['maintenance_mode']);
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
            
            // 🌟 บันทึก Log: อัปเดตตั้งค่าส่วนกลาง
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "อัปเดตตั้งค่าระบบส่วนกลาง ({$section_name})");
            $_SESSION['success_msg'] = "บันทึกการตั้งค่าเรียบร้อยแล้ว";
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Settings update failed: " . $e->getMessage());
            $_SESSION['error_msg'] = "ไม่สามารถบันทึกการตั้งค่าได้ กรุณาลองใหม่อีกครั้ง";
        }

        header("Location: index.php?c=settings&a=system");
        exit;
    }

    public function test_line() {
        $this->requirePost();
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
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200) {
                // 🌟 บันทึก Log: แจ้งเตือนการทดสอบ LINE
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "ทดสอบระบบส่งข้อความ LINE Notify สำเร็จ");
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
            $holidayModel->addHoliday($_POST['holiday_date'], $_POST['holiday_name'], $_POST['holiday_type'] ?? 'REGULAR');
            
            // 🌟 บันทึก Log: เพิ่มวันหยุด
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_CREATE, "เพิ่มวันหยุดนักขัตฤกษ์ด้วยตนเอง: " . $_POST['holiday_name']);
            $_SESSION['success_msg'] = "เพิ่มวันหยุดเรียบร้อยแล้ว";
        }
        header("Location: index.php?c=settings&a=holidays");
        exit;
    }

    public function toggle_holiday() {
        $this->requirePost();
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        $db = (new Database())->getConnection();
        require_once 'models/HolidayModel.php';
        $holidayModel = new HolidayModel($db);

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $status = isset($_POST['status']) && (int)$_POST['status'] === 1 ? 1 : 0;

        if ($id > 0 && $holidayModel->toggleStatus($id, $status)) {
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "เปลี่ยนสถานะวันหยุด ID: " . $id);
            $_SESSION['success_msg'] = $status ? "เปิดใช้งานวันหยุดเรียบร้อยแล้ว" : "ปิดใช้งานวันหยุดเรียบร้อยแล้ว";
        }

        header("Location: index.php?c=settings&a=holidays");
        exit;
    }

    public function delete_holiday() {
        $this->requirePost();
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        $db = (new Database())->getConnection();
        require_once 'models/HolidayModel.php';
        $holidayModel = new HolidayModel($db);
        
        if (isset($_POST['id'])) {
            // ดึงชื่อวันหยุดมาเพื่อบันทึก Log ให้ชัดเจน
            $stmt = $db->prepare("SELECT holiday_name FROM holidays WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $holiday_name = $stmt->fetchColumn() ?: "ID: " . $_POST['id'];
            
            $holidayModel->deleteHoliday($_POST['id']);
            
            // 🌟 บันทึก Log: ลบวันหยุด
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_DELETE, "ลบวันหยุดนักขัตฤกษ์: {$holiday_name}");
            $_SESSION['success_msg'] = "ลบวันหยุดเรียบร้อยแล้ว";
        }
        header("Location: index.php?c=settings&a=holidays");
        exit;
    }

    public function sync_api() {
        $this->requirePost();
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        $year = isset($_POST['year']) ? $_POST['year'] : date('Y');
        $db = (new Database())->getConnection();
        require_once 'models/HolidayModel.php';
        $holidayModel = new HolidayModel($db);
        $result = $holidayModel->syncHolidaysFromAPI($year);

        if($result['success']) {
            // 🌟 บันทึก Log: ซิงค์ API
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_CREATE, "ซิงค์วันหยุดจาก BOT API ปี {$year} สำเร็จ ({$result['added']} วัน)");
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

    public function save_pay_rates() {
        $this->requirePost();
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);

        $db = (new Database())->getConnection();
        require_once 'models/PayRateModel.php';
        $payRateModel = new PayRateModel($db);
        $rates = $payRateModel->getAllRates();

        foreach ($rates as $rate) {
            $id = (int)$rate['id'];
            $data = [
                'name' => $rate['name'] ?? ($rate['group_name'] ?? ''),
                'keywords' => $rate['keywords'] ?? '',
                'rate_y' => $_POST['rate_y_' . $id] ?? $rate['rate_y'],
                'rate_b' => $_POST['rate_b_' . $id] ?? $rate['rate_b'],
                'rate_r' => $_POST['rate_r_' . $id] ?? $rate['rate_r'],
            ];
            $payRateModel->updateRate($id, $data);
        }

        LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "ปรับปรุงอัตราค่าตอบแทน");
        $_SESSION['success_msg'] = "บันทึกอัตราค่าตอบแทนเรียบร้อยแล้ว";
        header("Location: index.php?c=settings&a=shift_types");
        exit;
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
                
                // 🌟 บันทึก Log: แก้ไขเรทค่าตอบแทน
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "แก้ไขหมวดเรทค่าตอบแทน ID: " . $_POST['id']);
                $_SESSION['success_msg'] = "แก้ไขเรทค่าตอบแทนเรียบร้อยแล้ว";
            } else {
                $payRateModel->addRate($data);
                
                // 🌟 บันทึก Log: เพิ่มเรทค่าตอบแทน
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_CREATE, "เพิ่มหมวดเรทค่าตอบแทนใหม่ ({$_POST['keywords']})");
                $_SESSION['success_msg'] = "เพิ่มเรทค่าตอบแทนเรียบร้อยแล้ว";
            }
        }
        header("Location: index.php?c=settings&a=shift_types");
        exit;
    }

    public function delete_payrate() {
        $this->requirePost();
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        $db = (new Database())->getConnection();
        require_once 'models/PayRateModel.php';
        $payRateModel = new PayRateModel($db);
        
        if (isset($_POST['id'])) {
            $payRateModel->deleteRate($_POST['id']);
            
            // 🌟 บันทึก Log: ลบเรทค่าตอบแทน
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_DELETE, "ลบหมวดเรทค่าตอบแทน ID: " . $_POST['id']);
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
        
        // 🌟 แก้ไข: เพิ่มสิทธิ์ HR เข้าไปในระบบ Matrix เมนู
        $system_roles = ['STAFF', 'SCHEDULER', 'DIRECTOR', 'HR', 'ADMIN', 'SUPERADMIN'];
        $menus = [];
        
        try {
            $stmt = $db->query("SELECT * FROM system_menus ORDER BY display_order ASC, id ASC");
            $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('SettingsController error: ' . $e->getMessage());
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ";
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
                
                // 🌟 บันทึก Log: อัปเดตสิทธิ์เมนู
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "ปรับปรุงสิทธิ์การเข้าถึงเมนูระบบ (Permission Matrix)");
                $_SESSION['success_msg'] = "บันทึกการกำหนดสิทธิ์การเข้าถึงเมนูเรียบร้อยแล้ว";

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('SettingsController error: ' . $e->getMessage());
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ";
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
                WHERE l.action = 'BACKUP' OR l.action = 'EXPORT'
                ORDER BY l.created_at DESC LIMIT 10
            ");
            $backup_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $db_stats['total_tables'] = 0;
            $backup_history = [];
        }

        // ดึงรายการไฟล์ Backup ที่อยู่ในเซิร์ฟเวอร์
        $server_backups = [];
        $backup_dir = 'storage/backups/';
        if (is_dir($backup_dir)) {
            $files = scandir($backup_dir);
            foreach ($files as $file) {
                $lower = strtolower($file);
                $isSql = str_ends_with($lower, '.sql');
                $isGzipSql = str_ends_with($lower, '.sql.gz');
                if (!$isSql && !$isGzipSql) {
                    continue;
                }

                $filepath = $backup_dir . $file;
                if (!is_file($filepath)) {
                    continue;
                }

                $server_backups[] = [
                    'filename' => $file,
                    'size' => round(filesize($filepath) / 1024, 2),
                    'date' => date("d/m/Y H:i:s", filemtime($filepath)),
                    'download_url' => 'index.php?c=settings&a=download_server_backup&file=' . rawurlencode($file),
                    'has_checksum' => is_file($filepath . '.sha256'),
                ];
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

    // ดาวน์โหลดทันที
    public function do_backup() {
        $this->requirePost();
        $this->requireAccess(['SUPERADMIN']); 
        
        set_time_limit(300); 
        ini_set('memory_limit', '256M');

        $db = (new Database())->getConnection();

        try {
            $sqlScript = $this->generateSqlScript($db);
            
            // 🌟 บันทึก Log: ดาวน์โหลดไฟล์สำรอง
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_EXPORT, "ส่งออกและดาวน์โหลดไฟล์สำรองฐานข้อมูล (.sql)");

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
            error_log('SettingsController error: ' . $e->getMessage());
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ";
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
        $backup_dir = 'storage/backups/';

        try {
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0700, true);
            }

            $sqlScript = $this->generateSqlScript($db);
            
            // ชื่อไฟล์ระบุปีและเดือน เพื่อให้เก็บรายเดือน
            $backup_file_name = 'roster_pro_monthly_' . date('Y_m_d_His') . '.sql';
            $filepath = $backup_dir . $backup_file_name;

            if (file_put_contents($filepath, $sqlScript) !== false) {
                // 🌟 บันทึก Log: สำรองข้อมูลลงเซิร์ฟเวอร์
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_EXPORT, "สำรองข้อมูลจัดเก็บลงเซิร์ฟเวอร์ ({$backup_file_name})");
                $_SESSION['success_msg'] = "บันทึกไฟล์สำรองข้อมูลลงเซิร์ฟเวอร์เรียบร้อยแล้ว";
            } else {
                $_SESSION['error_msg'] = "ไม่สามารถเขียนไฟล์ลงในโฟลเดอร์ storage/backups/ ได้ โปรดตรวจสอบ Permission";
            }

        } catch (Exception $e) {
            error_log('SettingsController error: ' . $e->getMessage());
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ";
        }

        header("Location: index.php?c=settings&a=backup");
        exit;
    }

    public function download_server_backup() {
        $this->requireAccess(['SUPERADMIN']);

        $filename = basename((string)($_GET['file'] ?? ''));
        $filepath = 'storage/backups/' . $filename;
        $lower = strtolower($filename);
        $isAllowed = str_ends_with($lower, '.sql') || str_ends_with($lower, '.sql.gz');

        if ($filename === '' || !$isAllowed || !is_file($filepath)) {
            http_response_code(404);
            exit('Backup file not found.');
        }

        $db = (new Database())->getConnection();
        LogsController::addLog(
            $db,
            $_SESSION['user']['id'],
            LogsController::ACTION_EXPORT,
            "ดาวน์โหลดไฟล์สำรองข้อมูลในเซิร์ฟเวอร์ ({$filename})"
        );

        header('Content-Type: ' . (str_ends_with($lower, '.gz') ? 'application/gzip' : 'application/sql'));
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Content-Length: ' . filesize($filepath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($filepath);
        exit;
    }

    public function delete_server_backup() {
        $this->requirePost();
        $this->requireAccess(['SUPERADMIN']);
        $filename = $_POST['file'] ?? '';
        $filepath = 'storage/backups/' . basename($filename);

        if (!empty($filename) && file_exists($filepath)) {
            unlink($filepath);
            foreach (['.sha256', '.json'] as $sidecar) {
                $sidecarPath = $filepath . $sidecar;
                if (is_file($sidecarPath)) {
                    unlink($sidecarPath);
                }
            }
            $db = (new Database())->getConnection();
            
            // 🌟 บันทึก Log: ลบไฟล์สำรองข้อมูล
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_DELETE, "ลบไฟล์สำรองข้อมูลในเซิร์ฟเวอร์ ({$filename})");
            $_SESSION['success_msg'] = "ลบไฟล์ {$filename} เรียบร้อยแล้ว";
        } else {
            $_SESSION['error_msg'] = "ไม่พบไฟล์ที่ต้องการลบ";
        }
        header("Location: index.php?c=settings&a=backup");
        exit;
    }

    // ฟังก์ชันใหม่: URL สำหรับให้ Cron Job เรียกใช้งาน (ไม่ต้อง Login)
    public function cron_monthly_backup() {
        $secret_key = (string)(getenv('ROSTER_CRON_KEY') ?: ''); 
        $provided_key = $_GET['key'] ?? '';

        if ($secret_key === '' || !hash_equals($secret_key, (string)$provided_key)) {
            die("Access Denied: Invalid Cron Key.");
        }

        set_time_limit(300); 
        ini_set('memory_limit', '256M');

        $db = (new Database())->getConnection();
        $backup_dir = 'storage/backups/';

        try {
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0700, true);
            }

            // เช็คว่าเดือนนี้มีไฟล์แล้วหรือยัง
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
                    // 🌟 บันทึก Log: การรัน Cron Job (ใช้ ID 0)
                    LogsController::addLog($db, 0, LogsController::ACTION_EXPORT, "[CRON JOB] สำรองข้อมูลอัตโนมัติประจำเดือน ({$backup_file_name})");
                    echo "Cron Backup Success: {$backup_file_name}";
                } else {
                    echo "Cron Backup Failed: Cannot write file.";
                }
            } else {
                echo "Cron Backup Skipped: Already backed up this month.";
            }

        } catch (Exception $e) {
            error_log("Cron backup failed: " . $e->getMessage());
            http_response_code(500);
            echo "Cron Backup Error";
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
                'shifts',
                'roster_status',
                'leave_requests',
                'shift_swaps',
                'logs',
                'system_logs',
                'notifications'
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
            
            // 🌟 ย้าย Log มาไว้บรรทัดล่างสุด (หลังจากตาราง logs ถูก Truncate ไปแล้ว) 
            // เพื่อให้ผู้ดูแลเห็นประวัตินี้เหลืออยู่เป็นอันแรกในระบบใหม่
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_DELETE, "FACTORY RESET: ล้างข้อมูลระบบปฏิบัติการทั้งหมดเริ่มต้นรอบปีใหม่");
            
            $_SESSION['success_msg'] = "ล้างข้อมูลตารางเวรและประวัติต่างๆ เรียบร้อยแล้ว ระบบพร้อมสำหรับการเริ่มต้นใหม่";

        } catch (Exception $e) {
            $db->exec("SET FOREIGN_KEY_CHECKS=1;"); 
            error_log("Factory reset failed: " . $e->getMessage());
            $_SESSION['error_msg'] = "ไม่สามารถล้างข้อมูลระบบได้ กรุณาตรวจสอบ Log และลองใหม่";
        }

        header("Location: index.php?c=settings&a=system");
        exit;
    }
}
?>