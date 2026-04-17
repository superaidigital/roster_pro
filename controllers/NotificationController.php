<?php
// ที่อยู่ไฟล์: controllers/NotificationController.php

require_once 'config/database.php';
require_once 'models/NotificationModel.php';

class NotificationController {
    
    private function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if(!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }
    }

    // 🌟 1. แสดงหน้ารายการแจ้งเตือนทั้งหมด
    public function index() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        $notifModel = new NotificationModel($db);

        $user_id = $_SESSION['user']['id'];
        
        // ดึงการแจ้งเตือนทั้งหมดของ User นี้
        $notifications = $notifModel->getUserNotifications($user_id);

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/notification/index.php';
        echo "</main></div></body></html>"; 
    }

    // 🌟 2. อ่านแจ้งเตือน 1 รายการ แล้ว Redirect ไปยังลิงก์เป้าหมาย
    public function read() {
        $this->checkAuth();
        if (isset($_GET['id'])) {
            $db = (new Database())->getConnection();
            $notifModel = new NotificationModel($db);
            
            $id = $_GET['id'];
            $user_id = $_SESSION['user']['id'];
            
            // อัปเดตสถานะเป็นอ่านแล้ว
            $notifModel->markAsRead($id, $user_id);
            
            // ดึง URL เป้าหมายเพื่อเด้งไป
            $link = isset($_GET['url']) ? urldecode($_GET['url']) : 'index.php?c=notification';
            header("Location: " . $link);
            exit;
        }
        header("Location: index.php?c=notification");
        exit;
    }

    // 🌟 3. อ่านแจ้งเตือนทั้งหมด (Mark all as read)
    public function read_all() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        $notifModel = new NotificationModel($db);
        
        $user_id = $_SESSION['user']['id'];
        $notifModel->markAllAsRead($user_id);
        
        $_SESSION['success_msg'] = "ทำเครื่องหมายอ่านแล้วทั้งหมดเรียบร้อย";
        header("Location: index.php?c=notification");
        exit;
    }
}
?>