<?php
// ที่อยู่ไฟล์: controllers/DashboardController.php

require_once 'config/database.php';

class DashboardController {
    
    private $db;

    public function __construct() {
        // สร้างการเชื่อมต่อฐานข้อมูลตั้งแต่เริ่มเรียกใช้ Controller
        $this->db = (new Database())->getConnection();
    }

    // ====================================================
    // 🛡️ ฟังก์ชันช่วยเหลือ (Helpers)
    // ====================================================

    // ตรวจสอบสิทธิ์การเข้าใช้งาน
    private function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if(!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }
    }

    // ฟังก์ชันแปลงเวลาเป็น "ที่แล้ว" (Time Ago)
    private function timeAgo($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'ปี',
            'm' => 'เดือน',
            'w' => 'สัปดาห์',
            'd' => 'วัน',
            'h' => 'ชั่วโมง',
            'i' => 'นาที',
            's' => 'วินาที',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v;
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . 'ที่แล้ว' : 'เมื่อสักครู่';
    }

    // ====================================================
    // 🚦 ระบบจัดการเส้นทาง (Router)
    // ====================================================
    public function index() {
        $this->checkAuth();
        $role = strtoupper($_SESSION['user']['role'] ?? 'STAFF');

        // แยกหน้าจอตามสิทธิ์การใช้งาน
        if (in_array($role, ['SUPERADMIN', 'ADMIN'])) {
            $this->admin_dashboard();
        } else {
            $this->user_dashboard();
        }
    }

    // ====================================================
    // 👑 1. แดชบอร์ดสำหรับผู้บริหารส่วนกลาง (ADMIN / SUPERADMIN)
    // ====================================================
    private function admin_dashboard() {
        
        // 1. ตั้งค่าตัวแปรเริ่มต้น (ป้องกัน Error Undefined Variable ที่หน้าจอ)
        $stats = [
            'total_hospitals' => 0,
            'total_staff' => 0,
            'staff_on_duty_today' => 0,
            'staff_on_leave_today' => 0
        ];
        $pending_leave_count = 0;
        $hospitals = [];
        $recent_activities = [];

        try {
            // 📊 สถิติ 1: นับจำนวน รพ.สต. ทั้งหมด
            $stmt_hosp = $this->db->query("SELECT COUNT(*) FROM hospitals");
            $stats['total_hospitals'] = $stmt_hosp->fetchColumn() ?: 0;

            // 📊 สถิติ 2: นับจำนวนบุคลากรทั้งหมด
            $stmt_users = $this->db->query("SELECT COUNT(*) FROM users");
            $stats['total_staff'] = $stmt_users->fetchColumn() ?: 0;

            // 📊 สถิติ 3: นับแจ้งเตือนใบลาพักผ่อนที่รออนุมัติ
            try {
                $stmt_leaves = $this->db->query("SELECT COUNT(*) FROM leaves WHERE status = 'PENDING'");
                $pending_leave_count = $stmt_leaves->fetchColumn() ?: 0;
            } catch (Exception $e) {
                $pending_leave_count = 0; 
            }

            // 📊 สถิติ 4: คนลาพักผ่อนวันนี้
            try {
                $today = date('Y-m-d');
                $stmt_on_leave = $this->db->prepare("SELECT COUNT(*) FROM leaves WHERE status = 'APPROVED' AND :today BETWEEN start_date AND end_date");
                $stmt_on_leave->execute([':today' => $today]);
                $stats['staff_on_leave_today'] = $stmt_on_leave->fetchColumn() ?: 0;
            } catch (Exception $e) {
                $stats['staff_on_leave_today'] = 0; 
            }

            // 📊 สถิติ 5: คนขึ้นเวรวันนี้ (เชื่อมฐานข้อมูลจริง)
            try {
                $today = date('Y-m-d');
                $stmt_all_duty = $this->db->prepare("SELECT COUNT(*) FROM roster_details WHERE duty_date = :today");
                $stmt_all_duty->execute([':today' => $today]);
                $stats['staff_on_duty_today'] = $stmt_all_duty->fetchColumn() ?: 0;
            } catch (Exception $e) {
                $stats['staff_on_duty_today'] = 0; 
            }

            // 🏥 ดึงข้อมูล รพ.สต. และหน่วยบริการอื่นๆ ทั้งหมดเพื่อปักหมุดบนแผนที่
            $query_hospitals = "
                SELECT h.id, h.name, h.hospital_code as code, h.hospital_size as size, 
                       h.latitude as lat, h.longitude as lng, h.phone,
                       (SELECT name FROM users WHERE hospital_id = h.id AND role = 'DIRECTOR' LIMIT 1) as director,
                       (SELECT COUNT(*) FROM users WHERE hospital_id = h.id) as total_staff
                FROM hospitals h
                WHERE h.id != 0 AND h.name NOT LIKE '%ส่วนกลาง%'
                ORDER BY h.id ASC
            ";
            $stmt_h = $this->db->query($query_hospitals);
            $fetched_hospitals = $stmt_h->fetchAll(PDO::FETCH_ASSOC);
            
            $next_month = date('m', strtotime('+1 month'));
            $next_year = date('Y', strtotime('+1 month'));
            $today = date('Y-m-d');

            foreach($fetched_hospitals as $h) {
                $h['director'] = $h['director'] ?? 'ยังไม่ระบุ';
                $h['phone'] = $h['phone'] ?? '-';
                
                // 🌟 เชื่อมต่อฐานข้อมูล: ตรวจสอบสถานะการส่งเวรเดือนถัดไป
                try {
                    $stmt_roster = $this->db->prepare("SELECT status FROM rosters WHERE hospital_id = :hid AND month = :m AND year = :y LIMIT 1");
                    $stmt_roster->execute([':hid' => $h['id'], ':m' => $next_month, ':y' => $next_year]);
                    $r_status = $stmt_roster->fetchColumn();
                    $h['roster_status'] = ($r_status && in_array(strtoupper($r_status), ['SUBMITTED', 'APPROVED'])) ? 'submitted' : 'pending';
                } catch (Exception $e) {
                    $h['roster_status'] = 'pending'; // Fallback
                }

                // 🌟 เชื่อมต่อฐานข้อมูล: นับคนขึ้นเวรวันนี้ของ รพ.สต. นั้นๆ
                try {
                    $stmt_duty = $this->db->prepare("SELECT COUNT(*) FROM roster_details rd JOIN rosters r ON rd.roster_id = r.id WHERE r.hospital_id = :hid AND rd.duty_date = :today");
                    $stmt_duty->execute([':hid' => $h['id'], ':today' => $today]);
                    $h['on_duty_today'] = $stmt_duty->fetchColumn() ?: 0;
                } catch (Exception $e) {
                    $h['on_duty_today'] = 0; // Fallback
                }
                
                if(!empty($h['lat']) && !empty($h['lng'])) {
                    $hospitals[] = $h;
                }
            }

            // 📝 ดึงประวัติการเคลื่อนไหวล่าสุด (System Logs)
            try {
                $query_logs = "
                    SELECT l.action_type, l.details, l.created_at, u.name as user_name 
                    FROM system_logs l 
                    LEFT JOIN users u ON l.user_id = u.id 
                    ORDER BY l.created_at DESC 
                    LIMIT 5
                ";
                $stmt_logs = $this->db->query($query_logs);
                $logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);
                
                foreach($logs as $log) {
                    $color = 'secondary'; 
                    $icon = 'bi-info-circle';
                    
                    // กำหนดสีและไอคอนตามประเภทกิจกรรม
                    switch(strtoupper($log['action_type'] ?? '')) {
                        case 'CREATE':   $color = 'success'; $icon = 'bi-plus-circle-fill'; break;
                        case 'UPDATE':   $color = 'primary'; $icon = 'bi-pencil-square'; break;
                        case 'DELETE':   $color = 'danger';  $icon = 'bi-trash-fill'; break;
                        case 'LOGIN':    $color = 'info';    $icon = 'bi-box-arrow-in-right'; break;
                        case 'DOWNLOAD': $color = 'warning'; $icon = 'bi-download'; break;
                    }

                    $recent_activities[] = [
                        'type'  => $log['action_type'],
                        'icon'  => $icon,
                        'color' => $color,
                        'title' => $log['details'],
                        'user'  => $log['user_name'] ?? 'ระบบ (System)',
                        'time'  => $this->timeAgo($log['created_at'])
                    ];
                }
            } catch (Exception $e) {
                error_log("Log Fetch Error: " . $e->getMessage());
            }

        } catch (Exception $e) {
            error_log("Admin Dashboard DB Error: " . $e->getMessage());
        }

        // ==========================================
        // ประมวลผลข้อมูลกราฟและการ์ดสรุป
        // ==========================================
        $submitted_count = 0;
        $pending_count = 0;
        $size_counts = ['S' => 0, 'M' => 0, 'L' => 0, 'XL' => 0];

        foreach($hospitals as $h) {
            // นับสถานะการส่งเวร
            if(($h['roster_status'] ?? '') === 'submitted') {
                $submitted_count++;
            } else {
                $pending_count++;
            }
            
            // นับขนาดหน่วยบริการ (ดักจับค่าว่างหรือค่าที่ไม่อยู่ในเงื่อนไขให้เป็น S)
            $size = strtoupper(trim($h['size'] ?? 'S'));
            if (isset($size_counts[$size])) {
                $size_counts[$size]++;
            } else {
                $size_counts['S']++; 
            }
        }

        // โหลด View ไปแสดงผล (ส่งตัวแปรที่เตรียมไว้ทั้งหมดไป)
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/dashboard/admin_index.php';
        echo "</main></div></body></html>";
    }

    // ====================================================
    // 🏥 2. แดชบอร์ดสำหรับบุคลากรประจำ รพ.สต. (DIRECTOR, SCHEDULER, STAFF)
    // ====================================================
    private function user_dashboard() {
        
        $hospital_id = $_SESSION['user']['hospital_id'];
        
        // ข้อมูลเบื้องต้นสำหรับหน้าแดชบอร์ดผู้ใช้
        $stats = [
            'total_staff' => 0,
            'staff_on_duty_today' => 0, 
            'staff_on_leave_today' => 0
        ];

        try {
            // จำนวนพนักงานในสังกัด
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE hospital_id = :hid");
            $stmt->execute([':hid' => $hospital_id]);
            $stats['total_staff'] = $stmt->fetchColumn() ?: 0;

        } catch (Exception $e) {
            error_log("User Dashboard DB Error: " . $e->getMessage());
        }

        // โหลด View ของ User ปกติ (หมายเหตุ: คุณต้องมีไฟล์ views/dashboard/index.php เตรียมไว้)
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        
        // ตรวจสอบว่ามีไฟล์หน้าแดชบอร์ดปกติไหม ถ้าไม่มีให้ไปหน้าเวร
        if (file_exists('views/dashboard/index.php')) {
            require_once 'views/dashboard/index.php';
        } else {
            // Fallback หากยังไม่ได้สร้างหน้า user dashboard ให้เด้งไปหน้าตารางเวร
            echo "<script>window.location.href = 'index.php?c=roster';</script>";
        }
        
        echo "</main></div></body></html>";
    }

}
?>