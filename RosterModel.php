<?php
// ที่อยู่ไฟล์: models/RosterModel.php

class RosterModel {
    private $conn;
    private $table_name = "shifts";

    public function __construct($db) {
        $this->conn = $db;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 🌟 รันฟังก์ชันตรวจสอบและสร้างตาราง/คอลัมน์อัตโนมัติ
        $this->checkAndCreateTable();
    }

    /**
     * 🌟 ระบบ Auto-Migration: สร้างตาราง shifts อัตโนมัติ
     */
    private function checkAndCreateTable() {
        try {
            $query = "CREATE TABLE IF NOT EXISTS `" . $this->table_name . "` (
                `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
                `hospital_id` INT(11) NOT NULL COMMENT 'รหัส รพ.สต.',
                `user_id` INT(11) NOT NULL COMMENT 'รหัสพนักงาน',
                `shift_date` DATE NOT NULL COMMENT 'วันที่ขึ้นเวร',
                `shift_type` VARCHAR(10) NOT NULL COMMENT 'ประเภทเวร (M, A, N, OFF)',
                `is_holiday` TINYINT(1) DEFAULT 0 COMMENT '1=เป็นวันหยุดนักขัตฤกษ์',
                `status` ENUM('DRAFT', 'PUBLISHED') DEFAULT 'DRAFT' COMMENT 'สถานะเวร',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_user_date` (`user_id`, `shift_date`) COMMENT 'ป้องกันการจัดเวรซ้ำวันเดิม'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            $this->conn->exec($query);
        } catch (PDOException $e) {
            error_log("Roster Auto-migration failed: " . $e->getMessage());
        }
    }

    /**
     * ดึงข้อมูลเวรทั้งหมดของเดือนและปีที่กำหนด (อ้างอิงตาม รพ.สต.)
     */
    public function getShiftsByMonth($hospital_id, $year, $month) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE hospital_id = :hospital_id 
                  AND YEAR(shift_date) = :year 
                  AND MONTH(shift_date) = :month";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':hospital_id', $hospital_id);
        $stmt->bindParam(':year', $year);
        $stmt->bindParam(':month', $month);
        $stmt->execute();
        
        // จัดรูปแบบให้อ่านง่าย: $result[user_id][date] = shift_type
        $shifts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = date('j', strtotime($row['shift_date'])); // เอาแค่วันที่ 1-31
            $shifts[$row['user_id']][$date] = [
                'type' => $row['shift_type'],
                'status' => $row['status'],
                'is_holiday' => $row['is_holiday']
            ];
        }
        return $shifts;
    }

    /**
     * บันทึกหรืออัปเดตเวร (Upsert) - รองรับการบันทึกทีละหลายรายการ
     */
    public function saveShifts($hospital_id, $user_id, $shifts_data) {
        try {
            $this->conn->beginTransaction();
            
            // ใช้คำสั่ง INSERT ... ON DUPLICATE KEY UPDATE (ถ้ามีข้อมูลวันนั้นแล้วให้อัปเดต)
            $query = "INSERT INTO " . $this->table_name . " 
                      (hospital_id, user_id, shift_date, shift_type, status) 
                      VALUES (:hospital_id, :user_id, :shift_date, :shift_type, :status)
                      ON DUPLICATE KEY UPDATE shift_type = VALUES(shift_type), status = VALUES(status)";
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($shifts_data as $date => $type) {
                // ถ้าค่าว่าง ให้ทำการลบเวรของวันนั้นทิ้ง
                if (empty($type)) {
                    $del_stmt = $this->conn->prepare("DELETE FROM " . $this->table_name . " WHERE user_id = ? AND shift_date = ?");
                    $del_stmt->execute([$user_id, $date]);
                    continue;
                }

                $stmt->bindValue(':hospital_id', $hospital_id);
                $stmt->bindValue(':user_id', $user_id);
                $stmt->bindValue(':shift_date', $date);
                $stmt->bindValue(':shift_type', strtoupper($type));
                $stmt->bindValue(':status', 'DRAFT'); // ตอนจัดครั้งแรกให้เป็นฉบับร่างก่อน
                $stmt->execute();
            }
            
            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Save Shifts Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * อนุมัติและประกาศใช้ตารางเวร (เปลี่ยน DRAFT เป็น PUBLISHED)
     */
    public function publishRoster($hospital_id, $year, $month) {
        $query = "UPDATE " . $this->table_name . " SET status = 'PUBLISHED' 
                  WHERE hospital_id = :hospital_id AND YEAR(shift_date) = :year AND MONTH(shift_date) = :month";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':hospital_id', $hospital_id);
        $stmt->bindParam(':year', $year);
        $stmt->bindParam(':month', $month);
        try { return $stmt->execute(); } catch (PDOException $e) { return false; }
    }
}
?>