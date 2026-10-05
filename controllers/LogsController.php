<?php
// ที่อยู่ไฟล์: controllers/LogsController.php

require_once 'config/database.php';

class LogsController {

    // ====================================================
    // 📌 รายการประเภทกิจกรรม (Action Constants) เพื่อความเป็นมาตรฐาน
    // สามารถเรียกใช้จากที่อื่นได้ เช่น LogsController::ACTION_LOGIN
    // ====================================================
    const ACTION_LOGIN   = 'LOGIN';
    const ACTION_LOGOUT  = 'LOGOUT';
    const ACTION_CREATE  = 'CREATE';
    const ACTION_UPDATE  = 'UPDATE';
    const ACTION_DELETE  = 'DELETE';
    const ACTION_RESTORE = 'RESTORE';
    const ACTION_IMPORT  = 'IMPORT';
    const ACTION_EXPORT  = 'EXPORT';

    // ====================================================
    // 🌟 1. ฟังก์ชันคงที่ (Static) สำหรับบันทึกประวัติการใช้งาน
    // ====================================================
    public static function addLog($db, $user_id, $action, $details, $ip_address = null) {
        try {
            // ดึง IP Address อัตโนมัติหากไม่มีการส่งค่ามา
            if ($ip_address === null) {
                $ip_address = self::getRealIpAddr();
            }
            
            $stmt = $db->prepare("INSERT INTO logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
            return $stmt->execute([$user_id, $action, $details, $ip_address]);
        } catch (Exception $e) {
            // บันทึก Error ลงไฟล์ log ของเซิร์ฟเวอร์เพื่อตรวจสอบภายหลัง
            error_log("System Log Insert Failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ฟังก์ชันช่วยดึง IP Address ที่แท้จริง (รองรับ Proxy / Cloudflare)
     */
    private static function getRealIpAddr() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // กรณีมีหลาย IP คั่นด้วยลูกน้ำ ให้นำตัวแรกสุดมาใช้
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
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

        // 1. รับค่าการค้นหาจากฟอร์ม พร้อมตัดช่องว่าง
        $search_keyword = trim($_GET['search'] ?? '');
        $action_filter  = trim($_GET['action'] ?? '');
        $date_filter    = trim($_GET['date'] ?? '');

        // 2. ตั้งค่าการแบ่งหน้า (Pagination)
        $current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 50; // แสดง 50 รายการต่อหน้า

        // 3. สร้างเงื่อนไข Query (Dynamic SQL)
        $sql_base = "FROM logs l 
                     LEFT JOIN users u ON l.user_id = u.id 
                     LEFT JOIN hospitals h ON u.hospital_id = h.id 
                     WHERE 1=1";
        $params = [];

        if ($search_keyword !== '') {
            $sql_base .= " AND (u.name LIKE :search OR l.details LIKE :search OR l.ip_address LIKE :search)";
            $params[':search'] = "%$search_keyword%";
        }

        if ($action_filter !== '') {
            $sql_base .= " AND l.action = :action";
            $params[':action'] = $action_filter;
        }

        if ($date_filter !== '') {
            $dateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $date_filter);
            if ($dateObj && $dateObj->format('Y-m-d') === $date_filter) {
                $sql_base .= " AND l.created_at >= :date_start AND l.created_at < :date_end";
                $params[':date_start'] = $dateObj->format('Y-m-d 00:00:00');
                $params[':date_end'] = $dateObj->modify('+1 day')->format('Y-m-d 00:00:00');
            } else {
                $date_filter = '';
            }
        }

        // 4. นับจำนวนแถวทั้งหมดเพื่อคำนวณหน้า (Pagination)
        $total_rows = 0;
        $total_pages = 1;
        
        try {
            $stmt_count = $db->prepare("SELECT COUNT(*) " . $sql_base);
            $stmt_count->execute($params);
            $total_rows = (int)$stmt_count->fetchColumn();
            
            if ($total_rows > 0) {
                $total_pages = ceil($total_rows / $limit);
            }
        } catch (Exception $e) {
            error_log("Log Pagination Error: " . $e->getMessage());
        }

        // ป้องกันกรณีใส่เลขหน้าเกินความจริงใน URL
        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }
        $offset = ($current_page - 1) * $limit;

        // 5. ดึงข้อมูลจริงเพื่อนำไปแสดงผล (ใช้ BindValue เพื่อความปลอดภัย)
        $logs = [];
        try {
            $sql = "SELECT l.*, u.name as user_name, u.role, h.name as hospital_name " . 
                   $sql_base . 
                   " ORDER BY l.created_at DESC, l.id DESC LIMIT :limit OFFSET :offset";
                   
            $stmt = $db->prepare($sql);
            
            // แนบพารามิเตอร์การค้นหา
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            
            // แนบค่า Limit และ Offset เป็น INT เสมอ
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC); 
            
        } catch (Exception $e) {
            error_log("Log Fetch Error: " . $e->getMessage());
        }

        // เตรียมตัวแปรสำหรับส่งไปให้ View ใช้งาน
        $data = [
            'logs' => $logs,
            'total_rows' => $total_rows,
            'total_pages' => $total_pages,
            'current_page' => $current_page,
            'search_keyword' => $search_keyword,
            'action_filter' => $action_filter,
            'date_filter' => $date_filter
        ];

        // โหลด Layout ส่วนหัวและเมนูด้านข้าง
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        
        // โหลดหน้า View พร้อมตรวจสอบความถูกต้องของไฟล์
        if (file_exists('views/logs/index.php')) {
            // แตกตัวแปร $data ออกมาเพื่อให้ View เรียกใช้ได้ง่าย
            extract($data);
            require_once 'views/logs/index.php';
        } else {
            echo "<div class='container mt-4'>
                    <div class='alert alert-danger text-center shadow-sm rounded-4'>
                        <i class='bi bi-exclamation-triangle-fill me-2'></i> 
                        <strong>ไม่พบไฟล์ View:</strong> ไม่สามารถโหลดไฟล์ <code>views/logs/index.php</code> ได้ กรุณาตรวจสอบว่ามีไฟล์นี้อยู่จริง
                    </div>
                  </div>";
        }
        
        echo "</main></div></body></html>";
    }
}
?>