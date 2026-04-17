<?php
// ที่อยู่ไฟล์: controllers/AuthController.php
// ชื่อไฟล์: AuthController.php

require_once 'config/database.php';
require_once 'models/UserModel.php';

class AuthController {
    
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

            $username = trim($_POST['username']);
            $password = trim($_POST['password']);

            // ตรวจสอบค่าว่างเบื้องต้น
            if (empty($username) || empty($password)) {
                $_SESSION['login_error'] = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
                header("Location: index.php?c=auth&a=index");
                exit;
            }

            // เรียกใช้งานฟังก์ชัน login จาก UserModel
            $user = $userModel->login($username, $password);

            if($user) {
                // ล็อกอินสำเร็จ: บันทึกข้อมูลลง Session
                $_SESSION['user'] = $user;
                unset($_SESSION['login_error']); // ล้างค่า Error
                
                // 🌟 แยกหน้าแรกที่เข้าถึงตามสิทธิ์
                if (in_array($user['role'], ['SUPERADMIN', 'ADMIN'])) {
                    header("Location: index.php?c=dashboard&a=index"); // ส่วนกลางไปแดชบอร์ด
                } else {
                    header("Location: index.php?c=roster&a=index"); // รพ.สต. ไปหน้าตารางเวร
                }
                exit;
            } else {
                // ล็อกอินไม่สำเร็จ: ตรวจสอบว่าใน DB รหัสผ่านถูก Hash หรือยัง
                $_SESSION['login_error'] = "ชื่อผู้ใช้ หรือ รหัสผ่านไม่ถูกต้อง (กรุณาตรวจสอบว่ารหัสใน DB ถูกเข้ารหัสแล้ว)";
                header("Location: index.php?c=auth&a=index");
                exit;
            }
        }
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();
        header("Location: index.php?c=auth&a=index");
        exit;
    }
}
?>