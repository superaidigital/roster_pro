<?php
// ที่อยู่ไฟล์: controllers/AuthController.php
// ชื่อไฟล์: AuthController.php

require_once 'config/database.php';
require_once 'models/UserModel.php';
require_once 'controllers/LogsController.php'; // 🌟 นำเข้าระบบบันทึกประวัติ

class AuthController {

    private const LOGIN_WINDOW_SECONDS = 900; // 15 minutes
    private const LOGIN_MAX_IDENTITY_FAILURES = 5;
    private const LOGIN_MAX_IP_FAILURES = 20;

    private function getClientIpHash() {
        // Use REMOTE_ADDR only. Forwarded headers are forgeable unless the
        // application is configured with a trusted reverse proxy list.
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return hash('sha256', $ip);
    }

    private function getIdentityHash($username) {
        return hash('sha256', mb_strtolower((string) $username, 'UTF-8'));
    }

    private function isLoginRateLimited($db, $identityHash, $ipHash) {
        try {
            $stmt = $db->prepare("
                SELECT
                    SUM(identity_hash = ?) AS identity_failures,
                    SUM(ip_hash = ?) AS ip_failures
                FROM login_attempts
                WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $stmt->execute([$identityHash, $ipHash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return (
                (int) ($row['identity_failures'] ?? 0) >= self::LOGIN_MAX_IDENTITY_FAILURES
                || (int) ($row['ip_failures'] ?? 0) >= self::LOGIN_MAX_IP_FAILURES
            );
        } catch (Throwable $e) {
            // Graceful fallback until the migration has been applied.
            $bucket = $_SESSION['login_rate_limit'] ?? ['started_at' => time(), 'failures' => 0];

            if ((time() - (int) ($bucket['started_at'] ?? 0)) > self::LOGIN_WINDOW_SECONDS) {
                $bucket = ['started_at' => time(), 'failures' => 0];
                $_SESSION['login_rate_limit'] = $bucket;
            }

            return (int) ($bucket['failures'] ?? 0) >= self::LOGIN_MAX_IDENTITY_FAILURES;
        }
    }

    private function recordFailedLogin($db, $identityHash, $ipHash) {
        try {
            $stmt = $db->prepare("
                INSERT INTO login_attempts (identity_hash, ip_hash)
                VALUES (?, ?)
            ");
            $stmt->execute([$identityHash, $ipHash]);

            // Keep the rate-limit table bounded.
            $db->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        } catch (Throwable $e) {
            $bucket = $_SESSION['login_rate_limit'] ?? ['started_at' => time(), 'failures' => 0];

            if ((time() - (int) ($bucket['started_at'] ?? 0)) > self::LOGIN_WINDOW_SECONDS) {
                $bucket = ['started_at' => time(), 'failures' => 0];
            }

            $bucket['failures'] = (int) ($bucket['failures'] ?? 0) + 1;
            $_SESSION['login_rate_limit'] = $bucket;
        }
    }

    private function clearLoginFailures($db, $identityHash) {
        try {
            $stmt = $db->prepare("DELETE FROM login_attempts WHERE identity_hash = ?");
            $stmt->execute([$identityHash]);
        } catch (Throwable $e) {
            // Migration may not have been applied yet; session fallback is enough.
        }

        unset($_SESSION['login_rate_limit']);
    }
    
    public function index() {
        // เช็คสถานะ Session ก่อนเริ่ม
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $database = new Database();
            $db = $database->getConnection();
            $userModel = new UserModel($db);

            // Security: validate missing/array input and DO NOT trim passwords.
            // Trimming silently changes a legitimate password containing spaces.
            $rawUsername = $_POST['username'] ?? '';
            $rawPassword = $_POST['password'] ?? '';

            $username = is_string($rawUsername) ? trim($rawUsername) : '';
            $password = is_string($rawPassword) ? $rawPassword : '';

            // Defensive length limits reduce abusive oversized requests.
            if (
                $username === ''
                || $password === ''
                || mb_strlen($username, 'UTF-8') > 100
                || strlen($password) > 4096
            ) {
                $_SESSION['login_error'] = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
                header("Location: index.php?c=auth&a=index");
                exit;
            }

            $identityHash = $this->getIdentityHash($username);
            $ipHash = $this->getClientIpHash();

            if ($this->isLoginRateLimited($db, $identityHash, $ipHash)) {
                // Use a generic response so attackers cannot infer whether the
                // username exists. Retry-After is useful for API-aware clients.
                header('Retry-After: 900');
                $_SESSION['login_error'] = "มีการพยายามเข้าสู่ระบบหลายครั้งเกินไป กรุณารอสักครู่แล้วลองใหม่";
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
                    $this->recordFailedLogin($db, $identityHash, $ipHash);
                    
                    $_SESSION['login_error'] = "⛔ บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ";
                    header("Location: index.php?c=auth&a=index");
                    exit;
                }

                // Security: rotate the session ID after authentication to prevent
                // session fixation attacks.
                session_regenerate_id(true);

                // Store only the authenticated user record after the ID rotation.
                $_SESSION['user'] = $user;
                $_SESSION['login_at'] = time();
                $this->clearLoginFailures($db, $identityHash);
                unset($_SESSION['login_error']);
                
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
                // Do not write the raw username to application logs. A short hash is
                // enough to correlate repeated failures without storing identifiers.
                $usernameFingerprint = substr(hash('sha256', mb_strtolower($username, 'UTF-8')), 0, 12);
                LogsController::addLog(
                    $db,
                    0,
                    LogsController::ACTION_LOGIN,
                    "พยายามเข้าสู่ระบบล้มเหลว (credential mismatch, ref: {$usernameFingerprint})"
                );
                $this->recordFailedLogin($db, $identityHash, $ipHash);
                
                // Generic message avoids leaking authentication implementation details.
                $_SESSION['login_error'] = "ชื่อผู้ใช้ หรือรหัสผ่านไม่ถูกต้อง";
                header("Location: index.php?c=auth&a=index");
                exit;
            }
        }
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Logout changes session state, so accept POST only. CSRF is validated
        // centrally by index.php before this action runs.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Method Not Allowed');
        }
        
        // 📝 บันทึก Log: ออกจากระบบด้วยตนเอง
        if (isset($_SESSION['user'])) {
            $database = new Database();
            $db = $database->getConnection();
            LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_LOGOUT, "ออกจากระบบด้วยตนเอง");
        }
        
        session_unset();
        session_destroy();

        // Expire the session cookie as well as destroying server-side session data.
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        header("Location: index.php?c=auth&a=index");
        exit;
    }
}
?>