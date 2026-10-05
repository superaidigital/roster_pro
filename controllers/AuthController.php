<?php
// ที่อยู่ไฟล์: controllers/AuthController.php
// ชื่อไฟล์: AuthController.php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'models/UserModel.php';
require_once 'controllers/LogsController.php'; // 🌟 นำเข้าระบบบันทึกประวัติ

class AuthController {
    
    public function index() {
        // เช็คสถานะ Session ก่อนเริ่ม
        security_start_session();
        
        // ถ้าล็อกอินค้างไว้แล้ว ให้แยกทางเดินตามสิทธิ์
        if(isset($_SESSION['user'])) {
            if (in_array($_SESSION['user']['role'], ['SUPERADMIN', 'ADMIN'])) {
                header("Location: index.php?c=dashboard&a=index");
            } else {
                header("Location: index.php?c=roster&a=index");
            }
            exit;
        }
        require_once 'views/auth/login.php';
    }

    public function login() {
        security_start_session();

        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (!security_verify_csrf($_POST['_csrf'] ?? null)) {
                $_SESSION['login_error'] = "คำขอหมดอายุหรือไม่ถูกต้อง กรุณาลองเข้าสู่ระบบใหม่";
                header("Location: index.php?c=auth&a=index");
                exit;
            }
            $database = new Database();
            $db = $database->getConnection();
            $userModel = new UserModel($db);

            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            // ตรวจสอบค่าว่างเบื้องต้น
            if (empty($username) || empty($password)) {
                $_SESSION['login_error'] = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
                header("Location: index.php?c=auth&a=index");
                exit;
            }

            // จำกัดจำนวนการลองรหัสผ่านผิด เพื่อลดความเสี่ยง brute-force
            $rateLimit = security_check_login_rate_limit($db, $username);
            if (!$rateLimit['allowed']) {
                $minutes = max(1, (int)ceil(((int)$rateLimit['retry_after']) / 60));
                $_SESSION['login_error'] = "มีการพยายามเข้าสู่ระบบหลายครั้งเกินไป กรุณารอประมาณ {$minutes} นาทีแล้วลองใหม่";
                header("Location: index.php?c=auth&a=index");
                exit;
            }

            // เรียกใช้งานฟังก์ชัน login จาก UserModel
            $user = $userModel->login($username, $password);

            if($user) {
                // 🌟 ตรวจสอบสถานะบัญชี (is_active) ถ้าเป็น 0 ไม่อนุญาตให้เข้าระบบ
                if (isset($user['is_active']) && $user['is_active'] == 0) {
                    
                    // 📝 บันทึก Log: พยายามเข้าสู่ระบบด้วยบัญชีที่ถูกระงับ
                    LogsController::addLog($db, $user['id'], LogsController::ACTION_LOGIN, "พยายามเข้าสู่ระบบล้มเหลว (บัญชีถูกระงับการใช้งาน)");
                    
                    $_SESSION['login_error'] = "⛔ บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ";
                    header("Location: index.php?c=auth&a=index");
                    exit;
                }

                // ป้องกัน Session Fixation ก่อนยกระดับเป็น session ที่ล็อกอินแล้ว
                security_regenerate_session();

                // ล็อกอินสำเร็จ: บันทึกข้อมูลลง Session
                $_SESSION['user'] = $user;
                security_mark_authenticated_session();
                unset($_SESSION['login_error']); // ล้างค่า Error
                
                // 📝 บันทึก Log: เข้าสู่ระบบสำเร็จ
                LogsController::addLog($db, $user['id'], LogsController::ACTION_LOGIN, "เข้าสู่ระบบสำเร็จ");
                
                // 🌟 แยกหน้าแรกที่เข้าถึงตามสิทธิ์
                if (in_array($user['role'], ['SUPERADMIN', 'ADMIN'])) {
                    header("Location: index.php?c=dashboard&a=index"); // ส่วนกลางไปแดชบอร์ด
                } else {
                    header("Location: index.php?c=roster&a=index"); // รพ.สต. ไปหน้าตารางเวร
                }
                exit;
            } else {
                // ล็อกอินไม่สำเร็จ: ตรวจสอบว่าใน DB รหัสผ่านถูก Hash หรือยัง
                
                // 📝 บันทึก Log: พยายามเข้าสู่ระบบล้มเหลว (ใช้ ID = 0 สำหรับคนแปลกหน้า)
                LogsController::addLog(
                    $db,
                    0,
                    LogsController::ACTION_LOGIN,
                    security_login_failure_details($username),
                    security_client_ip()
                );
                
                $_SESSION['login_error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
                header("Location: index.php?c=auth&a=index");
                exit;
            }
        }
    }

    public function logout() {
        security_start_session();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !security_is_valid_post_csrf()) {
            http_response_code(405);
            $_SESSION['error_msg'] = "คำขอออกจากระบบไม่ถูกต้อง กรุณาลองใหม่";
            header("Location: index.php?c=dashboard");
            exit;
        }

        // 📝 บันทึก Log: ออกจากระบบด้วยตนเอง
        if (isset($_SESSION['user'])) {
            $database = new Database();
            $db = $database->getConnection();
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_LOGOUT, "ออกจากระบบด้วยตนเอง");
        }

        security_destroy_session();
        header("Location: index.php?c=auth&a=index");
        exit;
    }
}
?>