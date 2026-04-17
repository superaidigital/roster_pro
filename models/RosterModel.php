<?php
// ที่อยู่ไฟล์: models/RosterModel.php

class RosterModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // =========================================================
    // 🌟 ส่วนที่เพิ่มเข้ามาใหม่สำหรับเชื่อมโยงระบบวันลา (Leave Integration)
    // =========================================================

    /**
     * ตรวจสอบว่าพนักงานคนนี้ ติด "ใบลาที่อนุมัติแล้ว" ในวันที่ระบุหรือไม่
     * (ใช้สำหรับดักจับตอนหัวหน้ากดปุ่ม Save เวร)
     */
    public function checkUserOnLeave($user_id, $date) {
        $query = "SELECT lr.id, lq.leave_type 
                  FROM leave_requests lr
                  JOIN leave_quotas lq ON lr.leave_type_id = lq.id
                  WHERE lr.user_id = :uid 
                  AND lr.status = 'APPROVED'
                  AND (:check_date BETWEEN lr.start_date AND lr.end_date)";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':uid' => $user_id,
            ':check_date' => $date
        ]);
        
        // ถ้าเจอข้อมูล แปลว่าติดลา จะส่งชื่อประเภทการลากลับไป (เช่น 'ลาพักผ่อน')
        // ถ้าไม่เจอ จะส่งค่า false กลับไป
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['leave_type'] : false;
    }

    /**
     * ดึงข้อมูลการลา "ทั้งหมด" ของทุกคนใน รพ.สต. ภายในเดือนนั้น
     * (ใช้สำหรับเอาไปวาดโชว์เป็นแถบสี ในหน้าปฏิทินตารางเวร)
     */
    public function getApprovedLeavesForMonth($hospital_id, $month, $year) {
        $start_date = "$year-$month-01";
        $end_date = date('Y-m-t', strtotime($start_date));

        $query = "SELECT lr.user_id, lr.start_date, lr.end_date, lq.leave_type 
                  FROM leave_requests lr
                  JOIN users u ON lr.user_id = u.id
                  JOIN leave_quotas lq ON lr.leave_type_id = lq.id
                  WHERE u.hospital_id = :hid 
                  AND lr.status = 'APPROVED'
                  AND (lr.start_date <= :end_date AND lr.end_date >= :start_date)";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':hid' => $hospital_id,
            ':start_date' => $start_date,
            ':end_date' => $end_date
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>