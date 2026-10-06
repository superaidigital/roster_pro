<?php
require_once 'config/database.php';
require_once 'models/NotificationModel.php';

class NotificationController {
    private $db;
    private $notifModel;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user'])) {
            $_SESSION['error_msg'] = "กรุณาเข้าสู่ระบบก่อนใช้งาน";
            header("Location: index.php?c=auth&a=index");
            exit;
        }

        $this->db = (new Database())->getConnection();
        $this->notifModel = new NotificationModel($this->db);
    }

    private function getCsrfToken() {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private function verifyCsrf() {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $postedToken = $_POST['csrf_token'] ?? '';

        if (!is_string($sessionToken) || !is_string($postedToken) ||
            $sessionToken === '' || $postedToken === '' ||
            !hash_equals($sessionToken, $postedToken)) {
            $this->respondError('คำขอหมดอายุหรือไม่ถูกต้อง', 419);
        }
    }

    private function requirePost() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respondError('Method not allowed', 405);
        }
    }

    private function isAjaxRequest() {
        return ($_POST['ajax'] ?? '') === '1'
            || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    private function safeInternalUrl($url, $fallback = 'index.php?c=notification') {
        if (!is_string($url)) return $fallback;

        $url = trim($url);
        if ($url === '' || preg_match('/[\r\n]/', $url)) return $fallback;

        // Only allow application-internal index.php routes.
        if (preg_match('/^index\.php(?:\?.*)?$/', $url)) {
            return $url;
        }

        return $fallback;
    }

    private function respondJson($payload, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function respondError($message, $status = 400) {
        if ($this->isAjaxRequest()) {
            $this->respondJson(['status' => 'error', 'message' => $message], $status);
        }

        http_response_code($status);
        $_SESSION['error_msg'] = $message;
        header("Location: index.php?c=notification");
        exit;
    }

    public function index() {
        $csrf_token = $this->getCsrfToken();

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/notification/index.php';
        echo "</main></div></body></html>";
    }

    public function read() {
        $this->requirePost();
        $this->verifyCsrf();

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $user_id = (int)$_SESSION['user']['id'];
        $target = $this->safeInternalUrl($_POST['url'] ?? '', 'index.php?c=notification');

        if (!$id) {
            $this->respondError('ไม่พบรหัสการแจ้งเตือน');
        }

        $ok = $this->notifModel->markAsRead($id, $user_id);

        if ($this->isAjaxRequest()) {
            $this->respondJson([
                'status' => $ok ? 'success' : 'error',
                'url' => $target
            ], $ok ? 200 : 400);
        }

        header("Location: " . $target);
        exit;
    }

    public function read_all() {
        $this->requirePost();
        $this->verifyCsrf();

        $user_id = (int)$_SESSION['user']['id'];
        $ok = $this->notifModel->markAllAsRead($user_id);

        if ($this->isAjaxRequest()) {
            $this->respondJson(['status' => $ok ? 'success' : 'error'], $ok ? 200 : 400);
        }

        $_SESSION['success_msg'] = $ok ? "ทำเครื่องหมายว่าอ่านแล้วทั้งหมดเรียบร้อย" : "ไม่สามารถอัปเดตการแจ้งเตือนได้";
        $returnTo = $this->safeInternalUrl($_POST['return_to'] ?? '', 'index.php?c=notification');
        header("Location: " . $returnTo);
        exit;
    }

    public function delete() {
        $this->requirePost();
        $this->verifyCsrf();

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            $this->respondError('ไม่พบรหัสการแจ้งเตือน');
        }

        $ok = $this->notifModel->deleteNotification($id, (int)$_SESSION['user']['id']);
        $this->respondJson(['status' => $ok ? 'success' : 'error'], $ok ? 200 : 400);
    }

    public function delete_all() {
        $this->requirePost();
        $this->verifyCsrf();

        $ok = $this->notifModel->deleteAllForUser((int)$_SESSION['user']['id']);
        $this->respondJson(['status' => $ok ? 'success' : 'error'], $ok ? 200 : 400);
    }

    public function count() {
        $count = $this->notifModel->getUnreadCount((int)$_SESSION['user']['id']);
        $this->respondJson(['status' => 'success', 'unread_count' => (int)$count]);
    }
}
?>