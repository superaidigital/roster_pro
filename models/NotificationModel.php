<?php
// ที่อยู่ไฟล์: models/NotificationModel.php

class NotificationModel {
    private $conn;
    private $table_name = "notifications";

    public function __construct($db) {
        $this->conn = $db;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 🌟 ตรวจสอบและสร้างตารางอัตโนมัติเมื่อเรียกใช้ Model ครั้งแรก
        $this->checkAndCreateTable();
    }

    /**
     * 🌟 ระบบสร้างตารางอัตโนมัติ (Auto-Migration)
     */
    private function checkAndCreateTable() {
        $query = "
            CREATE TABLE IF NOT EXISTS `" . $this->table_name . "` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `type` VARCHAR(50) DEFAULT 'INFO',
                `title` VARCHAR(255) NOT NULL,
                `message` TEXT NOT NULL,
                `link` VARCHAR(255) NULL,
                `is_read` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (`user_id`),
                INDEX (`is_read`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        try {
            $this->conn->exec($query);
        } catch (PDOException $e) {
            error_log("Notification Table Creation Error: " . $e->getMessage());
        }
    }

    /**
     * 🌟 1. เพิ่มการแจ้งเตือนใหม่ลงในระบบ
     * @param int $user_id รหัสผู้ใช้งานที่ต้องการแจ้งเตือน
     * @param string $type ประเภทแจ้งเตือน (INFO, SUCCESS, WARNING, DANGER, SWAP, LEAVE)
     * @param string $title หัวข้อการแจ้งเตือน
     * @param string $message รายละเอียด
     * @param string|null $link ลิงก์สำหรับกดเข้าไปดู (Option)
     */
    public function addNotification($user_id, $type, $title, $message, $link = null) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, type, title, message, link, is_read) 
                  VALUES (:user_id, :type, :title, :message, :link, 0)";
        
        $stmt = $this->conn->prepare($query);
        try {
            return $stmt->execute([
                ':user_id' => $user_id,
                ':type' => strtoupper($type),
                ':title' => $title,
                ':message' => $message,
                ':link' => $link
            ]);
        } catch (PDOException $e) {
            error_log("Add Notification Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🌟 2. ดึงการแจ้งเตือนทั้งหมดของ User (เรียงจากใหม่ไปเก่า)
     */
    public function getUserNotifications($user_id, $limit = 50) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        
        try {
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get User Notifications Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 🌟 3. นับจำนวนการแจ้งเตือนที่ยังไม่อ่าน
     */
    public function getUnreadCount($user_id) {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE user_id = :user_id AND is_read = 0";
        $stmt = $this->conn->prepare($query);
        try {
            $stmt->execute([':user_id' => $user_id]);
            return $stmt->fetchColumn() ?: 0;
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * 🌟 4. ทำเครื่องหมายว่า "อ่านแล้ว" เฉพาะรายการที่เลือก
     */
    public function markAsRead($id, $user_id) {
        $query = "UPDATE " . $this->table_name . " SET is_read = 1 WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        try {
            return $stmt->execute([':id' => $id, ':user_id' => $user_id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * 🌟 5. ทำเครื่องหมายว่า "อ่านแล้วทั้งหมด"
     */
    public function markAllAsRead($user_id) {
        $query = "UPDATE " . $this->table_name . " SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
        $stmt = $this->conn->prepare($query);
        try {
            return $stmt->execute([':user_id' => $user_id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * 🌟 6. ลบการแจ้งเตือนที่เก่าเกินไป (เก็บกวาดข้อมูล / Cleanup)
     * ตัวอย่างการใช้งาน: รันอัตโนมัติเมื่อครบเดือน เพื่อไม่ให้ตารางหนักเกินไป
     */
    public function deleteOldNotifications($days = 30) {
        $query = "DELETE FROM " . $this->table_name . " WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = $this->conn->prepare($query);
        try {
            return $stmt->execute([':days' => $days]);
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>