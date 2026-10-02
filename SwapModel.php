<?php
// ที่อยู่ไฟล์: models/SwapModel.php

class SwapModel {
    private $conn;
    private $table_name = "shift_swaps";

    public function __construct($db) {
        $this->conn = $db;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->checkAndCreateTable();
    }

    // 🌟 ระบบสร้างตารางอัตโนมัติ (ปรับปรุง: เพิ่ม Index เพื่อความเร็วในการค้นหา)
    private function checkAndCreateTable() {
        $query = "
            CREATE TABLE IF NOT EXISTS `" . $this->table_name . "` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `hospital_id` INT NOT NULL,
                `requestor_id` INT NOT NULL,
                `requestor_date` DATE NOT NULL,
                `requestor_shift` VARCHAR(50) NOT NULL,
                `target_user_id` INT NOT NULL,
                `target_date` DATE NOT NULL,
                `target_shift` VARCHAR(50) NOT NULL,
                `reason` TEXT NULL,
                `status` ENUM('PENDING_TARGET', 'PENDING_DIRECTOR', 'APPROVED', 'REJECTED') DEFAULT 'PENDING_TARGET',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (`hospital_id`),
                INDEX (`requestor_id`),
                INDEX (`target_user_id`),
                INDEX (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        try {
            $this->conn->exec($query);
        } catch (PDOException $e) {
            error_log("Swap Table Creation Error: " . $e->getMessage());
        }
    }

    // 🌟 1. ดึงรายการแลกเวรทั้งหมดใน รพ.สต. (แยกตาม Role)
    public function getSwaps($hospital_id, $user_id, $role) {
        $query = "
            SELECT s.*, 
                   u1.name as requestor_name, u1.color_theme as req_color,
                   u2.name as target_name, u2.color_theme as target_color
            FROM " . $this->table_name . " s
            LEFT JOIN users u1 ON s.requestor_id = u1.id
            LEFT JOIN users u2 ON s.target_user_id = u2.id
            WHERE s.hospital_id = :hospital_id
        ";

        // กรองการมองเห็นข้อมูลตามสิทธิ์
        if (!in_array($role, ['DIRECTOR', 'SCHEDULER', 'ADMIN', 'SUPERADMIN'])) {
            // พนักงานธรรมดาเห็นแค่ของตัวเอง (ทั้งฝั่งขอแลกและถูกขอแลก)
            $query .= " AND (s.requestor_id = :user_id OR s.target_user_id = :user_id) ";
        }

        $query .= " ORDER BY s.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':hospital_id', $hospital_id);
        if (!in_array($role, ['DIRECTOR', 'SCHEDULER', 'ADMIN', 'SUPERADMIN'])) {
            $stmt->bindParam(':user_id', $user_id);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 🌟 2. ดึงข้อมูลคำขอแลกเวร 1 รายการ
    public function getSwapById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 🌟 NEW: ตรวจสอบคำขอซ้ำซ้อน (ป้องกันการกดส่งซ้ำเวลาเน็ตค้าง)
    public function checkDuplicateRequest($data) {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " 
                  WHERE requestor_id = :req_id AND requestor_date = :req_date 
                  AND target_user_id = :tar_id AND target_date = :tar_date
                  AND status IN ('PENDING_TARGET', 'PENDING_DIRECTOR')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':req_id' => $data['requestor_id'],
            ':req_date' => $data['requestor_date'],
            ':tar_id' => $data['target_user_id'],
            ':tar_date' => $data['target_date']
        ]);
        return $stmt->fetchColumn() > 0;
    }

    // 🌟 3. สร้างคำขอแลกเวรใหม่
    public function createRequest($data) {
        // เช็คคำขอซ้ำก่อนบันทึก
        if ($this->checkDuplicateRequest($data)) {
            return false;
        }

        $query = "INSERT INTO " . $this->table_name . " 
                  (hospital_id, requestor_id, requestor_date, requestor_shift, target_user_id, target_date, target_shift, reason, status) 
                  VALUES 
                  (:hospital_id, :requestor_id, :requestor_date, :requestor_shift, :target_user_id, :target_date, :target_shift, :reason, 'PENDING_TARGET')";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':hospital_id' => $data['hospital_id'],
            ':requestor_id' => $data['requestor_id'],
            ':requestor_date' => $data['requestor_date'],
            ':requestor_shift' => $data['requestor_shift'],
            ':target_user_id' => $data['target_user_id'],
            ':target_date' => $data['target_date'],
            ':target_shift' => $data['target_shift'],
            ':reason' => $data['reason'] ?? null
        ]);
    }

    // 🌟 4. อัปเดตสถานะคำขอ
    public function updateStatus($id, $status) {
        $stmt = $this->conn->prepare("UPDATE " . $this->table_name . " SET status = :status WHERE id = :id");
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }

    // 🌟 NEW: ดึงจำนวนคำขอที่รอการอนุมัติ (เพื่อไปทำเลขแจ้งเตือน Notification Badge)
    public function getPendingCount($hospital_id, $user_id, $role) {
        // ถ้าเป็นหัวหน้างาน ให้นับรายการที่รอ ผอ. อนุมัติ
        if (in_array($role, ['DIRECTOR', 'SCHEDULER', 'ADMIN', 'SUPERADMIN'])) {
            $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE hospital_id = :hid AND status = 'PENDING_DIRECTOR'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':hid' => $hospital_id]);
        } else {
            // ถ้าเป็นพนักงานทั่วไป ให้นับรายการที่รอตัวเองกดยืนยันให้เพื่อน
            $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE target_user_id = :uid AND status = 'PENDING_TARGET'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':uid' => $user_id]);
        }
        return $stmt->fetchColumn() ?: 0;
    }

    // 🌟 5. ฟังก์ชันสลับเวรในฐานข้อมูลตารางเวร (เมื่อ ผอ. อนุมัติ)
    public function executeSwapInRoster($swap_id) {
        try {
            $swap = $this->getSwapById($swap_id);
            if (!$swap || $swap['status'] !== 'APPROVED') return false;

            // เริ่มการทำ Transaction (ถ้าคำสั่งใดพัง จะดึงข้อมูลกลับคืนทั้งหมด ไม่ให้ตารางเละ)
            $this->conn->beginTransaction();

            // 1. เปลี่ยนเวรของผู้ขอแลก -> ไปเป็นของผู้ถูกขอแลก
            $stmt1 = $this->conn->prepare("UPDATE roster_details SET user_id = :target_id WHERE user_id = :req_id AND duty_date = :req_date AND shift_type = :req_shift");
            $stmt1->execute([
                ':target_id' => $swap['target_user_id'],
                ':req_id' => $swap['requestor_id'],
                ':req_date' => $swap['requestor_date'],
                ':req_shift' => $swap['requestor_shift']
            ]);

            // 2. เปลี่ยนเวรของผู้ถูกขอแลก -> ไปเป็นของผู้ขอแลก
            $stmt2 = $this->conn->prepare("UPDATE roster_details SET user_id = :req_id WHERE user_id = :target_id AND duty_date = :target_date AND shift_type = :target_shift");
            $stmt2->execute([
                ':req_id' => $swap['requestor_id'],
                ':target_id' => $swap['target_user_id'],
                ':target_date' => $swap['target_date'],
                ':target_shift' => $swap['target_shift']
            ]);

            // บันทึกการเปลี่ยนแปลง
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Execute Swap Error: " . $e->getMessage());
            return false;
        }
    }
}
?>