<?php
// ที่อยู่ไฟล์: controllers/LogsController.php

class LogsController {
    /**
     * ฟังก์ชันสำหรับบันทึกประวัติการใช้งานระบบ (Audit Trail)
     * * @param PDO $db การเชื่อมต่อฐานข้อมูล
     * @param int|null $user_id รหัสผู้ใช้งาน (ถ้ามี)
     * @param string $action ประเภทการกระทำ (เช่น LOGIN, CREATE, UPDATE, DELETE, DOWNLOAD)
     * @param string $details รายละเอียดของการกระทำ
     * @return bool 
     */
    public static function addLog($db, $user_id, $action, $details) {
        try {
            // ดึง IP Address ของผู้ใช้งาน
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

            // บันทึกข้อมูลลงตาราง logs
            $stmt = $db->prepare("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $action, $details, $ip_address]);
            
            return true;
        } catch (Exception $e) {
            // หากบันทึกล้มเหลว (เช่น ฐานข้อมูลมีปัญหา) ให้ข้ามไป เพื่อไม่ให้กระทบการทำงานหลัก
            error_log("Failed to insert log: " . $e->getMessage());
            return false;
        }
    }
}

// 🌟 เพิ่ม Alias ป้องกัน Error: รองรับกรณีไฟล์อื่นๆ (เช่น AjaxController) ยังเรียกใช้ชื่อคลาสเดิม (LogController แบบไม่มี s)
if (!class_exists('LogController')) {
    class_alias('LogsController', 'LogController');
}
?>