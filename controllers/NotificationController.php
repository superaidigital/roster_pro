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
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $user_id = $_SESSION['user']['id'];
            
            // อัปเดตสถานะในฐานข้อมูลว่า "อ่านแล้ว"
            $this->notifModel->markAsRead($id, $user_id);
        }

        // ตรวจสอบว่ามีลิงก์แนบมาด้วยหรือไม่ ถ้ามีให้วิ่งไปที่ลิงก์นั้น
        if (!empty($_GET['url'])) {
            $target_url = urldecode($_GET['url']);
            header("Location: " . $target_url);
        } else {
            // ถ้าไม่มีลิงก์ ให้กลับไปที่หน้ารวมการแจ้งเตือน
            header("Location: index.php?c=notification");
        }
        exit;
    }

    // ==========================================
    // 🌟 3. ทำเครื่องหมายว่าอ่านทั้งหมด (กรณีเรียกจาก Header Dropdown)
    // ==========================================
    public function read_all() {
        $user_id = $_SESSION['user']['id'];
        
        // สั่งเคลียร์ให้อ่านทั้งหมด
        $this->notifModel->markAllAsRead($user_id);
        
        // พยายามพากลับไปหน้าเดิมที่ผู้ใช้กด (Referer) ถ้าไม่มีให้ไปหน้า Dashboard
        $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php?c=dashboard';
        
        // ใส่ Alert แจ้งเตือนความสำเร็จ (ถ้ามีระบบรองรับใน View)
        $_SESSION['success_msg'] = "ทำเครื่องหมายว่าอ่านแล้วทั้งหมดเรียบร้อย";
        
        header("Location: " . $referer);
        exit;
    }
}
?>