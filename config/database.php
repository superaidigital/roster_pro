<?php
// ที่อยู่ไฟล์: config/database.php
// ชื่อไฟล์: database.php

class Database {
    // กำหนดค่าการเชื่อมต่อฐานข้อมูล
    private $host = "localhost";
    private $db_name = "roster_pro_db";
    private $username = "root"; // เปลี่ยนเป็น username ของคุณ (ค่าเริ่มต้น xampp คือ root)
    private $password = "";     // เปลี่ยนเป็น password ของคุณ (ค่าเริ่มต้น xampp คือ ว่างเปล่า)
    public $conn;

    // ฟังก์ชันสำหรับเรียกใช้งานการเชื่อมต่อ
    public function getConnection() {
        $this->conn = null;
        try {
            // เชื่อมต่อด้วย PDO
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            // บังคับให้ใช้ Charset UTF-8 เพื่อรองรับภาษาไทย
            $this->conn->exec("set names utf8");
            // ตั้งค่าให้แสดง Error หากมีข้อผิดพลาดใน SQL
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>