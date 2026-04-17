<?php
// ที่อยู่ไฟล์: models/HolidayModel.php

class HolidayModel {
    private $conn;
    private $table_name = "holidays";

    public function __construct($db) {
        $this->conn = $db;
    }

    // ==========================================
    // 🌟 ดึงข้อมูลวันหยุดทั้งหมด (สำหรับหน้าจัดการของ Admin)
    // ==========================================
    public function getAllHolidays() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY holiday_date ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==========================================
    // 🌟 เพิ่มวันหยุดใหม่ (โดย Admin)
    // ==========================================
    public function addHoliday($date, $name, $hospital_id = null, $status = 'APPROVED') {
        // เช็คว่ามีวันที่นี้อยู่แล้วหรือไม่
        $check = "SELECT id FROM " . $this->table_name . " WHERE holiday_date = :date";
        $stmt_check = $this->conn->prepare($check);
        $stmt_check->bindParam(":date", $date);
        $stmt_check->execute();
        
        if($stmt_check->rowCount() > 0) {
            return false;
        }

        $query = "INSERT INTO " . $this->table_name . " (holiday_date, holiday_name, hospital_id, status) VALUES (:date, :name, :hid, :status)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":date", $date);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":hid", $hospital_id);
        $stmt->bindParam(":status", $status);
        
        return $stmt->execute();
    }

    // ==========================================
    // 🌟 แก้ไขข้อมูลวันหยุด (โดย Admin)
    // ==========================================
    public function updateHoliday($id, $date, $name) {
        $query = "UPDATE " . $this->table_name . " SET holiday_date = :date, holiday_name = :name WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":date", $date);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }

    // ==========================================
    // 🌟 ลบข้อมูลวันหยุด (โดย Admin)
    // ==========================================
    public function deleteHoliday($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }

    // ==========================================
    // 🌟 เสนอขอเพิ่มวันหยุดจาก รพ.สต. (สถานะ PENDING)
    // ==========================================
    public function requestHoliday($date, $name, $hospital_id) {
        // เช็คว่ามีวันหยุดนี้แบบอนุมัติแล้วหรือยัง
        if ($this->isHoliday($date)) return "EXISTS";
        
        // เช็คว่ามีคนเสนอไปแล้วและรออนุมัติอยู่หรือไม่
        $query = "SELECT id FROM " . $this->table_name . " WHERE holiday_date = :date AND status = 'PENDING'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":date", $date);
        $stmt->execute();
        if ($stmt->rowCount() > 0) return "PENDING";

        // บันทึกคำขอเข้าสู่ระบบ (รอส่วนกลางอนุมัติ)
        $query = "INSERT INTO " . $this->table_name . " (holiday_date, holiday_name, hospital_id, status) VALUES (:date, :name, :hid, 'PENDING')";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":date", $date);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":hid", $hospital_id);
        
        if ($stmt->execute()) return "SUCCESS";
        return "ERROR";
    }

    // ==========================================
    // 🌟 ตรวจสอบว่าเป็นวันหยุดหรือไม่ (ใช้ตอนจัดเวรและหน้าตาราง)
    // ==========================================
    public function isHoliday($date, $hospital_id = null) {
        // ตรวจสอบเฉพาะที่สถานะเป็น APPROVED หรือข้อมูลเก่าที่ไม่มีสถานะ (NULL)
        $query = "SELECT holiday_name FROM " . $this->table_name . " WHERE holiday_date = :date AND (status = 'APPROVED' OR status IS NULL)";
        
        if ($hospital_id) {
            $query .= " AND (hospital_id = :hid OR hospital_id IS NULL OR hospital_id = '')";
        }
        
        $query .= " LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":date", $date);
        
        if ($hospital_id) {
            $stmt->bindParam(":hid", $hospital_id);
        }
        
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['holiday_name'];
        }
        return false;
    }

    // ==========================================
    // 🌟 ดึงข้อมูลวันหยุดจาก API (จำลอง/ปรับตาม API ของจริง)
    // ==========================================
    public function syncHolidaysFromAPI($year) {
        // หากในอนาคตมีการเชื่อมต่อ API ของธนาคารแห่งประเทศไทย (BOT) หรือปฏิทินไทย
        // สามารถเขียน Logic การดึง cURL ได้ที่นี่ 
        // ปัจจุบันส่งค่า Return หลอกกลับไปก่อนเพื่อให้ไม่เกิด Error ตอนกดปุ่มซิงค์
        
        return [
            'success' => true, 
            'added' => 0, 
            'skipped' => 0, 
            'message' => 'ระบบ API กำลังอยู่ระหว่างการเชื่อมต่อข้อมูลกับกระทรวงฯ'
        ];
    }
}
?>