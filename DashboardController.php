<?php
// ที่อยู่ไฟล์: controllers/DashboardController.php

require_once 'config/database.php';

class DashboardController {
    
    private function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }
    }

    public function index() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        
        $role = strtoupper($_SESSION['user']['role'] ?? 'STAFF');
        $my_hosp_id = $_SESSION['user']['hospital_id'] ?? 0;
        $my_user_id = $_SESSION['user']['id'];
        $today = date('Y-m-d');
        $current_month = date('Y-m');

        // ==========================================
        // 🌟 1. แดชบอร์ดสำหรับ HR (ฝ่ายบุคคล)
        // แสดงแดชบอร์ดฝ่ายบุคคลแทน และไม่โชว์สถิติการเข้าเวร
        // ==========================================
        if ($role === 'HR') {
            require_once 'models/HrModel.php';
            require_once 'models/ProfileModel.php';
            
            $hrModel = new HrModel($db);
            $profileModel = new ProfileModel($db);
            
            // HR สามารถดูภาพรวมทั้งหมดได้ (รพ.สต. = 0 คือดูทั้งหมด)
            $hosp_filter_hr = 0; 
            
            // ดึงข้อมูลสถิติภาพรวมเฉพาะของ HR
            $stats = $hrModel->getDashboardStats($hosp_filter_hr);
            $expiring_licenses = $profileModel->getExpiringLicenses($hosp_filter_hr);

            // โหลด View แดชบอร์ดฝ่ายบุคคล
            require_once 'views/layouts/header.php';
            require_once 'views/layouts/sidebar.php';
            require_once 'views/hr/dashboard.php';
            echo "</main></div></body></html>";
            
            exit; // จบการทำงานที่นี่ ไม่ต้องดึงและแสดงผลสถิติของตำแหน่งอื่น
        }

        // ==========================================
        // 🧑‍⚕️ 2. Dashboard สำหรับ STAFF (พนักงานทั่วไป)
        // ==========================================
        if ($role === 'STAFF') {
            
            // 1. ดึงชื่อหน่วยบริการ
            $stmt = $db->prepare("SELECT name FROM hospitals WHERE id = ?");
            $stmt->execute([$my_hosp_id]);
            $hospital_name = $stmt->fetchColumn() ?: 'หน่วยบริการ';

            // 2. ดึงเวรที่กำลังจะถึงของตัวเอง (Upcoming Shifts)
            $upcoming_shifts = [];
            try {
                $stmt_shifts = $db->prepare("SELECT shift_date, shift_type FROM shifts WHERE user_id = ? AND shift_date >= ? AND shift_type NOT IN ('', 'OFF', 'ย') ORDER BY shift_date ASC LIMIT 5");
                $stmt_shifts->execute([$my_user_id, $today]);
                $upcoming_shifts = $stmt_shifts->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}

            // 3. ดึงจำนวนคำขอลาที่รออนุมัติของตัวเอง
            $my_pending_leaves = 0;
            try {
                $stmt_leaves = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND status = 'PENDING'");
                $stmt_leaves->execute([$my_user_id]);
                $my_pending_leaves = $stmt_leaves->fetchColumn() ?: 0;
            } catch(Exception $e) {}

            // 4. ดึงจำนวนคำขอแลกเวร
            $my_pending_swaps = 0;
            try {
                $stmt_swaps = $db->prepare("SELECT COUNT(*) FROM shift_swaps WHERE (request_user_id = ? OR target_user_id = ?) AND status = 'PENDING'");
                $stmt_swaps->execute([$my_user_id, $my_user_id]);
                $my_pending_swaps = $stmt_swaps->fetchColumn() ?: 0;
            } catch (Exception $e) {
                try {
                    $stmt_swaps = $db->prepare("SELECT COUNT(*) FROM shift_swaps WHERE user_id = ? AND status = 'PENDING'");
                    $stmt_swaps->execute([$my_user_id]);
                    $my_pending_swaps = $stmt_swaps->fetchColumn() ?: 0;
                } catch (Exception $ex) { $my_pending_swaps = 0; }
            }

            // 5. ดึงสถานะตารางเวรของหน่วยงานเดือนนี้
            $roster_status = 'NOT_STARTED';
            try {
                $stmt_status = $db->prepare("SELECT status FROM roster_status WHERE hospital_id = ? AND month_year = ?");
                $stmt_status->execute([$my_hosp_id, $current_month]);
                $roster_status = $stmt_status->fetchColumn() ?: 'NOT_STARTED';
            } catch (Exception $e) {}

            // โหลดหน้า Dashboard สำหรับพนักงาน
            require_once 'views/layouts/header.php';
            require_once 'views/layouts/sidebar.php';
            require_once 'views/dashboard/staff_index.php'; 
            echo "</main></div></body></html>";
            exit;
        }

        // ==========================================
        // 👔 3. Dashboard สำหรับผู้บริหาร (ADMIN, DIRECTOR, SCHEDULER)
        // ==========================================
        $is_global = in_array($role, ['ADMIN', 'SUPERADMIN']);
        
        $total_hospitals = 1; 
        if ($is_global) {
            $total_hospitals = $db->query("SELECT COUNT(*) FROM hospitals WHERE id != 0 AND deleted_at IS NULL AND is_active = 1")->fetchColumn() ?: 0;
        }
        
        $query_staff = "SELECT COUNT(*) FROM users WHERE role NOT IN ('SUPERADMIN', 'ADMIN') AND deleted_at IS NULL AND is_deleted = 0";
        if (!$is_global) $query_staff .= " AND hospital_id = " . (int)$my_hosp_id;
        $total_staff = $db->query($query_staff)->fetchColumn() ?: 0;

        $query_duty = "SELECT COUNT(DISTINCT user_id) FROM shifts WHERE shift_date = '$today' AND shift_type NOT IN ('', 'ย', 'OFF', 'L', 'O')";
        if (!$is_global) $query_duty .= " AND hospital_id = " . (int)$my_hosp_id;
        $on_duty_today = $db->query($query_duty)->fetchColumn() ?: 0;

        $query_leave = "SELECT COUNT(*) FROM leave_requests WHERE status = 'PENDING'";
        if (!$is_global) $query_leave .= " AND user_id IN (SELECT id FROM users WHERE hospital_id = " . (int)$my_hosp_id . ")";
        $pending_leaves = $db->query($query_leave)->fetchColumn() ?: 0;

        $pending_swaps = 0;
        try {
            $query_swaps = "SELECT COUNT(*) FROM shift_swaps WHERE status = 'PENDING'";
            if (!$is_global) $query_swaps .= " AND request_user_id IN (SELECT id FROM users WHERE hospital_id = " . (int)$my_hosp_id . ")";
            $pending_swaps = $db->query($query_swaps)->fetchColumn() ?: 0;
        } catch(Exception $e) {
            try {
                $query_swaps = "SELECT COUNT(*) FROM shift_swaps WHERE status = 'PENDING'";
                if (!$is_global) $query_swaps .= " AND user_id IN (SELECT id FROM users WHERE hospital_id = " . (int)$my_hosp_id . ")";
                $pending_swaps = $db->query($query_swaps)->fetchColumn() ?: 0;
            } catch(Exception $ex) { $pending_swaps = 0; }
        }

        $estimated_budget = 0;
        try {
            $query_budget = "
                SELECT s.shift_type, pr.rate_r, pr.rate_y, pr.rate_b
                FROM shifts s
                JOIN users u ON s.user_id = u.id
                JOIN pay_rates pr ON u.pay_rate_id = pr.id
                WHERE s.shift_date LIKE '$current_month-%'
            ";
            if (!$is_global) $query_budget .= " AND s.hospital_id = " . (int)$my_hosp_id;
            
            $budget_data = $db->query($query_budget)->fetchAll(PDO::FETCH_ASSOC);
            foreach($budget_data as $b) {
                $type = $b['shift_type'];
                if ($type === 'ร' || $type === 'N') $estimated_budget += $b['rate_r'];
                elseif ($type === 'บ' || $type === 'A') $estimated_budget += $b['rate_b'];
                elseif ($type === 'ย' || $type === 'O') $estimated_budget += $b['rate_y'];
                elseif ($type === 'บ/ร') $estimated_budget += ($b['rate_b'] + $b['rate_r']);
                elseif ($type === 'ย/บ') $estimated_budget += ($b['rate_y'] + $b['rate_b']);
            }
        } catch(Exception $e) {}

        $status_counts = ['APPROVED' => 0, 'SUBMITTED' => 0, 'DRAFT' => 0, 'WAITING' => 0];
        $query_roster = "
            SELECT h.id as hosp_id, h.name, rs.status 
            FROM hospitals h 
            LEFT JOIN roster_status rs ON h.id = rs.hospital_id AND rs.month_year = '$current_month'
            WHERE h.id != 0 AND h.deleted_at IS NULL AND h.is_active = 1
        ";
        if (!$is_global) $query_roster .= " AND h.id = " . (int)$my_hosp_id;

        $stmt_roster = $db->query($query_roster);
        $roster_data = $stmt_roster->fetchAll(PDO::FETCH_ASSOC);
        $waiting_hospitals = []; 

        foreach ($roster_data as $row) {
            $st = $row['status'];
            if ($st === 'APPROVED') $status_counts['APPROVED']++;
            elseif ($st === 'SUBMITTED') $status_counts['SUBMITTED']++;
            elseif ($st === 'DRAFT' || $st === 'REQUEST_EDIT') $status_counts['DRAFT']++;
            else { $status_counts['WAITING']++; $waiting_hospitals[] = $row['name']; }
        }

        $query_workload = "
            SELECT h.short_name, COUNT(s.id) as total_shifts 
            FROM shifts s JOIN hospitals h ON s.hospital_id = h.id 
            WHERE s.shift_date LIKE '$current_month-%' AND s.shift_type NOT IN ('', 'ย', 'OFF')
        ";
        if (!$is_global) $query_workload .= " AND s.hospital_id = " . (int)$my_hosp_id;
        $query_workload .= " GROUP BY s.hospital_id ORDER BY total_shifts DESC LIMIT 5";
        $workload_data = $db->query($query_workload)->fetchAll(PDO::FETCH_ASSOC);

        $leave_trends_labels = [];
        $leave_trends_data = [];
        try {
            $query_leave_trends = "
                SELECT lq.leave_type, COUNT(lr.id) as count_leave
                FROM leave_requests lr JOIN leave_quotas lq ON lr.leave_type_id = lq.id JOIN users u ON lr.user_id = u.id
                WHERE lr.start_date LIKE '$current_month-%' AND lr.status = 'APPROVED'
            ";
            if (!$is_global) $query_leave_trends .= " AND u.hospital_id = " . (int)$my_hosp_id;
            $query_leave_trends .= " GROUP BY lq.leave_type";

            $leave_trends_result = $db->query($query_leave_trends)->fetchAll(PDO::FETCH_ASSOC);
            foreach($leave_trends_result as $lt) {
                $leave_trends_labels[] = $lt['leave_type'];
                $leave_trends_data[] = $lt['count_leave'];
            }
        } catch(Exception $e) {}

        $fatigue_staff = [];
        try {
            $query_fatigue = "
                SELECT u.name, u.type as position, h.short_name as hosp_name, COUNT(s.id) as shift_count
                FROM shifts s JOIN users u ON s.user_id = u.id JOIN hospitals h ON u.hospital_id = h.id
                WHERE s.shift_date LIKE '$current_month-%' AND s.shift_type NOT IN ('', 'ย', 'OFF', 'O', 'L')
            ";
            if (!$is_global) $query_fatigue .= " AND u.hospital_id = " . (int)$my_hosp_id;
            $query_fatigue .= " GROUP BY s.user_id HAVING shift_count > 24 ORDER BY shift_count DESC LIMIT 5";
            
            $fatigue_staff = $db->query($query_fatigue)->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {}

        $query_risk = "
            SELECT h.name, COUNT(s.id) as on_duty FROM hospitals h 
            LEFT JOIN shifts s ON h.id = s.hospital_id AND s.shift_date = '$today' AND s.shift_type NOT IN ('', 'ย', 'OFF', 'O', 'L')
            WHERE h.id != 0 AND h.deleted_at IS NULL AND h.is_active = 1
        ";
        if (!$is_global) $query_risk .= " AND h.id = " . (int)$my_hosp_id;
        $query_risk .= " GROUP BY h.id HAVING on_duty <= 1";
        $risk_hospitals = $db->query($query_risk)->fetchAll(PDO::FETCH_ASSOC);

        $query_recent_leaves = "
            SELECT lr.*, u.name as user_name, lq.leave_type FROM leave_requests lr
            JOIN users u ON lr.user_id = u.id JOIN leave_quotas lq ON lr.leave_type_id = lq.id
            WHERE lr.status = 'PENDING'
        ";
        if (!$is_global) $query_recent_leaves .= " AND u.hospital_id = " . (int)$my_hosp_id;
        $query_recent_leaves .= " ORDER BY lr.created_at DESC LIMIT 5";
        $recent_leaves = $db->query($query_recent_leaves)->fetchAll(PDO::FETCH_ASSOC);

        $recent_logs = [];
        try {
            $query_logs = "
                SELECT l.*, u.name as user_name 
                FROM logs l JOIN users u ON l.user_id = u.id 
            ";
            if (!$is_global) $query_logs .= " WHERE u.hospital_id = " . (int)$my_hosp_id;
            $query_logs .= " ORDER BY l.created_at DESC LIMIT 6";
            
            $recent_logs = $db->query($query_logs)->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {}

        // รายงานการใช้งานระบบประจำวัน
        $today_usages = [];
        try {
            $query_today_usage = "
                SELECT 
                    h.name AS hospital_name,
                    COUNT(l.id) AS total_actions,
                    SUM(CASE WHEN l.action = 'LOGIN' THEN 1 ELSE 0 END) as count_login,
                    SUM(CASE WHEN l.action IN ('CREATE', 'UPDATE', 'DELETE', 'IMPORT', 'RESTORE') THEN 1 ELSE 0 END) as count_manage,
                    SUM(CASE WHEN l.action = 'EXPORT' THEN 1 ELSE 0 END) as count_export,
                    MAX(l.created_at) AS last_active
                FROM logs l
                JOIN users u ON l.user_id = u.id
                LEFT JOIN hospitals h ON u.hospital_id = h.id
                WHERE DATE(l.created_at) = :today
            ";
            
            if (!$is_global) {
                $query_today_usage .= " AND u.hospital_id = " . (int)$my_hosp_id;
            }
            
            $query_today_usage .= "
                GROUP BY h.id, h.name
                ORDER BY total_actions DESC
                LIMIT 15
            ";
            
            $stmt_usage = $db->prepare($query_today_usage);
            $stmt_usage->execute([':today' => $today]);
            $today_usages = $stmt_usage->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // โหลด View หน้าผู้บริหาร
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/dashboard/admin_index.php'; 
        echo "</main></div></body></html>";
    }

    // ==========================================
    // 🚨 3. หน้าวิเคราะห์เชิงลึก (Alerts & Risk Analysis)
    // ==========================================
    public function alerts() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        
        $role = strtoupper($_SESSION['user']['role'] ?? 'STAFF');
        $my_hosp_id = $_SESSION['user']['hospital_id'] ?? 0;
        $today = date('Y-m-d');
        $current_month = date('Y-m');
        $is_global = in_array($role, ['ADMIN', 'SUPERADMIN', 'DIRECTOR']);

        $fatigue_staff = [];
        try {
            $query_fatigue = "
                SELECT u.name, u.type as position, h.name as hosp_name, COUNT(s.id) as shift_count
                FROM shifts s 
                JOIN users u ON s.user_id = u.id 
                JOIN hospitals h ON u.hospital_id = h.id
                WHERE s.shift_date LIKE '$current_month-%' AND s.shift_type NOT IN ('', 'ย', 'OFF', 'L', 'O')
            ";
            if (!$is_global) $query_fatigue .= " AND u.hospital_id = " . (int)$my_hosp_id;
            $query_fatigue .= " GROUP BY s.user_id HAVING shift_count > 24 ORDER BY shift_count DESC";
            
            $fatigue_staff = $db->query($query_fatigue)->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {}

        $risk_hospitals = [];
        try {
            $query_risk = "
                SELECT h.name, COUNT(s.id) as on_duty 
                FROM hospitals h 
                LEFT JOIN shifts s ON h.id = s.hospital_id AND s.shift_date = '$today' AND s.shift_type NOT IN ('', 'ย', 'OFF', 'L', 'O')
                WHERE h.id != 0 AND h.deleted_at IS NULL AND h.is_active = 1
            ";
            if (!$is_global) $query_risk .= " AND h.id = " . (int)$my_hosp_id;
            $query_risk .= " GROUP BY h.id HAVING on_duty <= 1 ORDER BY on_duty ASC, h.name ASC";
            $risk_hospitals = $db->query($query_risk)->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {}

        $waiting_hospitals = [];
        try {
            $query_roster = "
                SELECT h.id, h.name, rs.status 
                FROM hospitals h 
                LEFT JOIN roster_status rs ON h.id = rs.hospital_id AND rs.month_year = '$current_month'
                WHERE h.id != 0 AND h.deleted_at IS NULL AND h.is_active = 1
            ";
            if (!$is_global) $query_roster .= " AND h.id = " . (int)$my_hosp_id;
            $roster_data = $db->query($query_roster)->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($roster_data as $row) {
                if (empty($row['status']) || !in_array($row['status'], ['APPROVED', 'SUBMITTED', 'DRAFT', 'REQUEST_EDIT'])) {
                    $waiting_hospitals[] = $row;
                }
            }
        } catch(Exception $e) {}

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/dashboard/alerts.php'; 
        echo "</main></div></body></html>";
    }

    // ==========================================
    // 🗺️ 4. หน้าแสดงแผนที่ รพ.สต. (Map View)
    // ==========================================
    public function map_view() {
        $this->checkAuth();
        
        $role = strtoupper($_SESSION['user']['role'] ?? 'STAFF');
        if (!in_array($role, ['ADMIN', 'SUPERADMIN', 'HR', 'DIRECTOR'])) {
            $_SESSION['error_msg'] = "ปฏิเสธการเข้าถึง: คุณไม่มีสิทธิ์เข้าดูแผนที่ภาพรวม";
            header("Location: index.php?c=dashboard");
            exit;
        }

        $db = (new Database())->getConnection();
        $current_month = date('Y-m');
        
        $map_data = [];
        try {
            // ดึงข้อมูล รพ.สต. พร้อมพิกัด, จำนวนพนักงาน และ สถานะตารางเวรปัจจุบัน
            $query = "
                SELECT 
                    h.id, 
                    h.name, 
                    h.latitude, 
                    h.longitude,
                    (SELECT COUNT(id) FROM users u WHERE u.hospital_id = h.id AND u.is_deleted = 0) as staff_count,
                    COALESCE(rs.status, 'NOT_STARTED') as roster_status
                FROM hospitals h
                LEFT JOIN roster_status rs ON h.id = rs.hospital_id AND rs.month_year = :month
                WHERE h.id != 0 AND h.is_active = 1 AND h.deleted_at IS NULL
            ";
            $stmt = $db->prepare($query);
            $stmt->execute([':month' => $current_month]);
            
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // ข้ามที่ไม่มีพิกัด
                if(empty($row['latitude']) || empty($row['longitude'])) continue;
                
                // กำหนดคำอธิบายสถานะ
                $status_text = 'ยังไม่ดำเนินการ';
                $status_color = 'secondary';
                
                if($row['roster_status'] == 'APPROVED') { $status_text = 'ปกติ (อนุมัติแล้ว)'; $status_color = 'success'; }
                elseif($row['roster_status'] == 'SUBMITTED') { $status_text = 'รอตรวจสอบ'; $status_color = 'primary'; }
                elseif($row['roster_status'] == 'DRAFT') { $status_text = 'กำลังจัดทำ'; $status_color = 'warning'; }

                // เช็คว่าขาดแคลนคนไหม (พนักงานน้อยกว่า 3)
                if($row['staff_count'] < 3) {
                    $status_text = 'ขาดแคลนบุคลากร';
                    $status_color = 'danger';
                }

                $map_data[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'lat' => (float)$row['latitude'],
                    'lng' => (float)$row['longitude'],
                    'staff_count' => (int)$row['staff_count'],
                    'status_text' => $status_text,
                    'status_color' => $status_color
                ];
            }
        } catch(Exception $e) {}

        // แปลงเป็น JSON เพื่อส่งให้ Javascript วาด Map
        $map_data_json = json_encode($map_data);

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        
        if (file_exists('views/dashboard/map.php')) {
            require_once 'views/dashboard/map.php';
        } else {
            echo "<div class='container mt-5'><div class='alert alert-warning text-center'><b>ยังไม่มีไฟล์:</b> views/dashboard/map.php</div></div>";
        }
        
        echo "</main></div></body></html>";
    }
}
?>