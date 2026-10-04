<?php
// ที่อยู่ไฟล์: controllers/NotificationController.php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'models/NotificationModel.php';

class NotificationController {
    private $db;
    private $notifModel;

    public function __construct() {
        security_start_session();

        // 🛡️ ตรวจสอบสิทธิ์การเข้าใช้งาน (ต้องล็อกอินก่อน)
        if (!isset($_SESSION['user'])) {
            $_SESSION['error_msg'] = "กรุณาเข้าสู่ระบบก่อนใช้งาน";
            header("Location: index.php?c=auth&a=index");
            exit;
        }

        // เชื่อมต่อฐานข้อมูลและเรียกใช้ Model
        $this->db = (new Database())->getConnection();
        $this->notifModel = new NotificationModel($this->db);
    }

    // ==========================================
    // 🌟 1. โหลดหน้าจอหลักการแจ้งเตือน (index)
    // ==========================================
    public function index() {
        // ข้อมูลถูกดึงอยู่แล้วในไฟล์ View แต่เราโหลด Layout ให้ครบถ้วน
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/notification/index.php';
        
        // ปิด Tag โครงสร้าง HTML (ปรับให้ตรงกับโครงสร้างเทมเพลตของคุณ)
        echo "</main></div></body></html>";
    }

    // ==========================================
    // 🌟 2. อ่านการแจ้งเตือน 1 รายการและเปลี่ยนหน้า (Redirect)
    // ==========================================
    public function read() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !security_is_valid_post_csrf()) {
            http_response_code(405);
            $_SESSION['error_msg'] = 'คำขอไม่ถูกต้อง กรุณาลองใหม่';
            header('Location: index.php?c=notification');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $user_id = (int)($_SESSION['user']['id'] ?? 0);
        $notification = $id > 0 ? $this->notifModel->getNotificationById($id, $user_id) : null;

        if ($notification) {
            $this->notifModel->markAsRead($id, $user_id);
        }

        $target = security_safe_local_redirect(
            $notification['link'] ?? null,
            'index.php?c=notification'
        );

        header('Location: ' . $target);
        exit;
    }

    // ==========================================
    // 🌟 3. ทำเครื่องหมายว่าอ่านทั้งหมด
    // ==========================================
    public function read_all() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !security_is_valid_post_csrf()) {
            http_response_code(405);
            $_SESSION['error_msg'] = 'คำขอไม่ถูกต้อง กรุณาลองใหม่';
            header('Location: index.php?c=notification');
            exit;
        }

        $user_id = (int)($_SESSION['user']['id'] ?? 0);
        $this->notifModel->markAllAsRead($user_id);

        $_SESSION['success_msg'] = 'ทำเครื่องหมายว่าอ่านแล้วทั้งหมดเรียบร้อย';

        $returnTo = security_safe_local_redirect(
            $_POST['return_to'] ?? null,
            'index.php?c=notification'
        );

        header('Location: ' . $returnTo);
        exit;
    }

}
?>