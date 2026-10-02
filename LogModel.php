<?php
// ที่อยู่ไฟล์: models/LogModel.php

class LogModel {
    private $conn;
    private $table_name = "system_logs";

    public function __construct($db) {
        $this->conn = $db;
    }

    // 🌟 ดึงข้อมูลประวัติการใช้งานล่าสุด 500 รายการ พร้อมชื่อและสิทธิ์ผู้ใช้
    public function getLatestLogs($limit = 500) {
        $query = "SELECT l.*, u.name as user_name, u.role, h.name as hospital_name 
                  FROM " . $this->table_name . " l
                  LEFT JOIN users u ON l.user_id = u.id
                  LEFT JOIN hospitals h ON u.hospital_id = h.id
                  ORDER BY l.created_at DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 🌟 ฟังก์ชันสำหรับให้ระบบเรียกใช้เพื่อ "บันทึก" ประวัติ
    // ตัวอย่างการเรียกใช้: $logModel->addLog($_SESSION['user']['id'], 'LOGIN', 'เข้าสู่ระบบสำเร็จ');
    public function addLog($user_id, $action, $description) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $query = "INSERT INTO " . $this->table_name . " (user_id, action, description, ip_address) 
                  VALUES (:user_id, :action, :description, :ip)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":action", $action);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":ip", $ip_address);
        
        return $stmt->execute();
    }
}
?>