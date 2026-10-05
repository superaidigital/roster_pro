<?php
// ที่อยู่ไฟล์: controllers/NotificationController.php

require_once 'config/database.php';
require_once 'models/NotificationModel.php';

class NotificationController {
    private $db;
    private $notifModel;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 🛡️ ตรวจสอบสิทธิ์การเข้าใช้งาน (ต้องล็อกอินก่อน)
        if (!isset($_SESSION['user'])) {
            $_SESSION['error_msg'] = "กรุณาเข้าสู่ระบบก่อนใช้งาน";
            header("Location: index.php?c=auth&a=login");
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
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Method Not Allowed');
        }

        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $user_id = (int)$_SESSION['user']['id'];

        if (!$id) {
            header("Location: index.php?c=notification");
            exit;
        }

        $notification = $this->notifModel->getByIdForUser($id, $user_id);
        if ($notification) {
            $this->notifModel->markAsRead($id, $user_id);
        }

        // Redirect only to app-local relative links stored by the server.
        $target = is_array($notification) ? (string)($notification['link'] ?? '') : '';
        if (
            $target !== ''
            && !str_contains($target, '://')
            && !str_starts_with($target, '//')
            && str_starts_with($target, 'index.php')
        ) {
            header("Location: " . $target);
        } else {
            header("Location: index.php?c=notification");
        }
        exit;
    }

    // ==========================================
    // 🌟 3. ทำเครื่องหมายว่าอ่านทั้งหมด (กรณีเรียกจาก Header Dropdown)
    // ==========================================
    public function read_all() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Method Not Allowed');
        }

        $user_id = (int)$_SESSION['user']['id'];
        $this->notifModel->markAllAsRead($user_id);
        $_SESSION['success_msg'] = "ทำเครื่องหมายว่าอ่านแล้วทั้งหมดเรียบร้อย";

        // Fixed local redirect avoids Host/Referer based open redirects.
        header("Location: index.php?c=notification");
        exit;
    }
}
?>