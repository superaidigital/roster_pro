<?php
// ที่อยู่ไฟล์: models/ShiftModel.php

class ShiftModel {
    private $conn;
    private $table_name = "shifts";
    private $status_table = "roster_status"; // 🌟 แก้ไข: ใช้ roster_status (ไม่มี s) เพื่อให้ตรงกับฐานข้อมูล

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getShiftsByWeek($hospital_id, $start_date, $end_date) {
        $query = "SELECT s.*, u.name as user_name, u.color_theme 
                  FROM " . $this->table_name . " s
                  JOIN users u ON s.user_id = u.id
                  WHERE s.hospital_id = :h_id 
                  AND s.shift_date BETWEEN :start AND :end
                  ORDER BY s.shift_type, u.name"; 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":h_id", $hospital_id);
        $stmt->bindParam(":start", $start_date);
        $stmt->bindParam(":end", $end_date);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- แก้ไข: เพิ่มพารามิเตอร์ $shift_type เพื่อให้เช็คว่าซ้ำกะเดียวกันหรือไม่ ---
    public function checkUserShiftExists($user_id, $date, $shift_type) {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE user_id = :uid AND shift_date = :date AND shift_type = :type LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":uid", $user_id);
        $stmt->bindParam(":date", $date);
        $stmt->bindParam(":type", $shift_type);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
    // -------------------------------------------------------------

    public function addShift($date, $shift_type, $user_id, $hospital_id) {
        try {
            $query = "INSERT INTO " . $this->table_name . " (shift_date, shift_type, user_id, hospital_id) VALUES (:date, :type, :uid, :hid)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":date", $date);
            $stmt->bindParam(":type", $shift_type);
            $stmt->bindParam(":uid", $user_id);
            $stmt->bindParam(":hid", $hospital_id);
            
            if($stmt->execute()){
                $last_id = $this->conn->lastInsertId();
                $month_year = date('Y-m', strtotime($date));
                $this->updateRosterStatus($hospital_id, $month_year, 'DRAFT');
                
                if(!$last_id || $last_id == 0) throw new PDOException("บันทึกสำเร็จ แต่ไม่ได้ค่า ID กลับมา");
                return $last_id;
            }
            return false;
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage());
        }
    }

    public function deleteShift($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }

    public function clearShiftsByDateRange($hospital_id, $start_date, $end_date) {
        $query = "DELETE FROM " . $this->table_name . " WHERE hospital_id = :hid AND shift_date BETWEEN :start AND :end";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":hid", $hospital_id);
        $stmt->bindParam(":start", $start_date);
        $stmt->bindParam(":end", $end_date);
        return $stmt->execute();
    }

    public function getRosterStatus($hospital_id, $month_year) {
        $query = "SELECT status FROM " . $this->status_table . " WHERE hospital_id = :hid AND month_year = :my LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":hid", $hospital_id);
        $stmt->bindParam(":my", $month_year);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['status'] : 'NOT_STARTED';
    }

    public function updateRosterStatus($hospital_id, $month_year, $status) {
        $current = $this->getRosterStatus($hospital_id, $month_year);
        if($current === 'NOT_STARTED') {
            $query = "INSERT INTO " . $this->status_table . " (hospital_id, month_year, status) VALUES (:hid, :my, :status)";
        } else {
            $query = "UPDATE " . $this->status_table . " SET status = :status WHERE hospital_id = :hid AND month_year = :my";
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":hid", $hospital_id);
        $stmt->bindParam(":my", $month_year);
        $stmt->bindParam(":status", $status);
        return $stmt->execute();
    }

    // เพิ่มฟังก์ชันนี้ใน models/ShiftModel.php
    public function getAllHospitalsRosterStatus($month_year) {
        // ใช้ LEFT JOIN เพื่อดึง รพ.สต. ทั้งหมด และประกบด้วยสถานะของเดือนนั้นๆ 
        $query = "SELECT h.id, h.name, 
                        COALESCE(rs.status, 'NOT_STARTED') as status, 
                        rs.pay_summary, 
                        rs.updated_at
                FROM hospitals h
                LEFT JOIN roster_status rs ON h.id = rs.hospital_id AND rs.month_year = :my
                ORDER BY h.id ASC";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":my", $month_year);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>