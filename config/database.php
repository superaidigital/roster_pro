<?php
// ที่อยู่ไฟล์: config/database.php
// ชื่อไฟล์: database.php

class Database {
    // กำหนดค่าการเชื่อมต่อฐานข้อมูล
    private $host = "localhost";
    private $db_name = "roster_pro_db";
    private $username = "root"; // เปลี่ยนเป็น username ของคุณ
    private $password = "";     // เปลี่ยนเป็น password ของคุณ
    public $conn;

    // ฟังก์ชันสำหรับเรียกใช้งานการเชื่อมต่อ
    public function getConnection() {
        $this->conn = null;
        
        try {
            // 🌟 1. กำหนด DSN พร้อมระบุ charset=utf8mb4 
            // (utf8mb4 ปลอดภัยและรองรับอักขระพิเศษ/อีโมจิได้ดีกว่า utf8 ธรรมดา)
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            // 🌟 2. กำหนด Options พื้นฐานสำหรับ PDO
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // แจ้งเตือน Error เป็น Exception
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // ดึงข้อมูลเป็น Array เสมอ (ไม่ต้องเขียนซ้ำใน Controller)
                PDO::ATTR_EMULATE_PREPARES   => false,                  // ปิดการจำลอง Prepare ป้องกัน SQL Injection ได้ดีขึ้นและให้ DB จัดการเอง
            ];

            // สร้างการเชื่อมต่อ
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
            // 🌟 3. บังคับ Timezone ของ Database ให้เป็นเวลาประเทศไทย (+07:00) เสมอ
            // สำคัญมากสำหรับระบบตารางเวรและการบันทึก created_at ในตาราง Logs
            $tzStmt = $this->conn->prepare("SET time_zone = ?");
            $tzStmt->execute([$this->timezone]);

        } catch(PDOException $exception) {
            // 🌟 4. ความปลอดภัย (Security Focus)
            // บันทึก Error ลงไฟล์ log ของ Server แทนการ echo ออกหน้าจอ
            // เพื่อป้องกันไม่ให้ข้อมูลพาธหรือรหัสผ่านหลุดไปให้ผู้ใช้งานทั่วไปเห็น
            error_log("Database Connection Error: " . $exception->getMessage());
            
            // แสดงข้อความทั่วไปให้ผู้ใช้ทราบ
            die("<div style='font-family: sans-serif; padding: 20px; text-align: center; color: #dc2626;'>
                    <h2>⚠️ เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล</h2>
                    <p>ไม่สามารถติดต่อฐานข้อมูลของระบบ Roster Pro ได้ในขณะนี้ โปรดตรวจสอบการตั้งค่าเซิร์ฟเวอร์</p>
                 </div>");
        }
        
        return $this->conn;
    }
}
?>