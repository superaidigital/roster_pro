<?php
// ที่อยู่ไฟล์: controllers/LogsController.php

require_once 'config/database.php';

class LogsController {

    // ====================================================
    // 🌟 1. ฟังก์ชันคงที่ (Static) สำหรับบันทึกประวัติการใช้งาน
    // ====================================================
    public static function addLog($db, $user_id, $action, $details, $ip_address = null) {
        try {
            if ($ip_address === null) {
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            }
            $stmt = $db->prepare("INSERT INTO logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
            return $stmt->execute([$user_id, $action, $details, $ip_address]);
        } catch (Exception $e) {
            return false;
        }
    }

    // ====================================================
    // 🌟 2. ฟังก์ชันแสดงผลหน้าจอ (Index)
    // ====================================================
    public function index() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        // 🔒 ตรวจสอบสิทธิ์ (อนุญาตเฉพาะ SUPERADMIN หรือ ADMIN)
        if (!isset($_SESSION['user']) || !in_array(strtoupper($_SESSION['user']['role']), ['ADMIN', 'SUPERADMIN'])) {
            $_SESSION['error_msg'] = "คุณไม่มีสิทธิ์เข้าถึงหน้าประวัติการทำงาน";
            header("Location: index.php?c=dashboard");
            exit;
        }

        $db = (new Database())->getConnection();

        // 1. รับค่าการค้นหาจากฟอร์ม (ชื่อตัวแปรให้ตรงกับหน้า View)
        $search_keyword = $_GET['search'] ?? '';
        $action_filter = $_GET['action'] ?? '';
        $date_filter = $_GET['date'] ?? '';

        // 2. ตั้งค่าการแบ่งหน้า (Pagination)
        $current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 50; // แสดง 50 รายการต่อหน้า
        $offset = ($current_page - 1) * $limit;

        // 3. สร้างเงื่อนไข Query (Dynamic SQL)
        $sql_base = "FROM logs l 
                     LEFT JOIN users u ON l.user_id = u.id 
                     LEFT JOIN hospitals h ON u.hospital_id = h.id 
                     WHERE 1=1";
        $params = [];

        if (!empty($search_keyword)) {
            $sql_base .= " AND (u.name LIKE :search OR l.details LIKE :search OR l.ip_address LIKE :search)";
            $params[':search'] = "%$search_keyword%";
        }

        if (!empty($action_filter)) {
            $sql_base .= " AND l.action = :action";
            $params[':action'] = $action_filter;
        }

        if (!empty($date_filter)) {
            // ตัดเอาเฉพาะ ค.ศ. (YYYY-MM-DD) ไปค้นหา
            $sql_base .= " AND DATE(l.created_at) = :date";
            $params[':date'] = $date_filter;
        }

        // 4. นับจำนวนหน้าทั้งหมด (สำหรับ Pagination)
        try {
            $stmt_count = $db->prepare("SELECT COUNT(*) " . $sql_base);
            $stmt_count->execute($params);
            $total_rows = $stmt_count->fetchColumn();
            $total_pages = ceil($total_rows / $limit);
            if ($total_pages < 1) $total_pages = 1;
        } catch (Exception $e) {
            $total_pages = 1;
        }

        // 5. ดึงข้อมูลจริงเพื่อนำไปแสดงผล (LIMIT & OFFSET)
        try {
            $sql = "SELECT l.*, u.name as user_name, u.role, h.name as hospital_name " . $sql_base . " ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            // 🌟 สำคัญ: เก็บใส่ตัวแปร $logs เพื่อให้ตรงกับที่ View เรียกใช้
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC); 
        } catch (Exception $e) {
            $logs = [];
        }

        // โหลด Layout ส่วนหัวและเมนูด้านข้าง
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        
        // โหลดหน้า View
        if (file_exists('views/logs/index.php')) {
            require_once 'views/logs/index.php';
        } else {
            echo "<div class='p-4 text-center text-danger'>ไม่พบไฟล์ views/logs/index.php</div>";
        }
        
        echo "</main></div></body></html>";
    }
}
?>