<?php
// ที่อยู่ไฟล์: controllers/DashboardController.php

require_once 'config/database.php';
require_once 'models/DashboardMetricsModel.php';
require_once 'lib/SimpleCache.php';

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
                $stmt_swaps = $db->prepare(
                    "SELECT COUNT(*)
                     FROM shift_swaps
                     WHERE (requestor_id = ? OR target_user_id = ?)
                       AND status IN ('PENDING_TARGET', 'PENDING_DIRECTOR')"
                );
                $stmt_swaps->execute([$my_user_id, $my_user_id]);
                $my_pending_swaps = (int)$stmt_swaps->fetchColumn();
            } catch (Exception $e) {
                $my_pending_swaps = 0;
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
        $is_global = in_array($role, ['ADMIN', 'SUPERADMIN'], true);
        $scope_hospital_id = $is_global ? null : (int)$my_hosp_id;

        $metricsModel = new DashboardMetricsModel($db);
        $dashboardCacheTtl = max(5, min(300, (int)(getenv('DASHBOARD_CACHE_TTL') ?: 20)));
        $cacheKey = 'dashboard:executive:v2:'
            . ($scope_hospital_id === null ? 'global' : 'hospital-' . $scope_hospital_id)
            . ':' . $current_month
            . ':' . $today;

        $dashboardStartedAt = microtime(true);
        $dashboardCacheHit = false;

        try {
            $cache = new SimpleCache(getenv('PERFORMANCE_CACHE_DIR') ?: null);
            $cached = $cache->remember(
                $cacheKey,
                $dashboardCacheTtl,
                static fn(): array => $metricsModel->getExecutiveAggregates(
                    $scope_hospital_id,
                    $current_month,
                    $today
                )
            );
            $aggregates = is_array($cached['value'] ?? null) ? $cached['value'] : [];
            $dashboardCacheHit = (bool)($cached['hit'] ?? false);
        } catch (Throwable $e) {
            error_log('Dashboard aggregate cache fallback: ' . $e->getMessage());
            $aggregates = $metricsModel->getExecutiveAggregates(
                $scope_hospital_id,
                $current_month,
                $today
            );
        }

        // Keep personal/recent activity out of shared file cache.
        $liveDetails = $metricsModel->getExecutiveLiveDetails(
            $scope_hospital_id,
            $current_month
        );

        $total_hospitals = (int)($aggregates['total_hospitals'] ?? 0);
        $total_staff = (int)($aggregates['total_staff'] ?? 0);
        $on_duty_today = (int)($aggregates['on_duty_today'] ?? 0);
        $pending_leaves = (int)($aggregates['pending_leaves'] ?? 0);
        $pending_swaps = (int)($aggregates['pending_swaps'] ?? 0);
        $estimated_budget = (float)($aggregates['estimated_budget'] ?? 0);
        $status_counts = $aggregates['status_counts'] ?? ['APPROVED' => 0, 'SUBMITTED' => 0, 'DRAFT' => 0, 'WAITING' => 0];
        $waiting_hospitals = $aggregates['waiting_hospitals'] ?? [];
        $workload_data = $aggregates['workload_data'] ?? [];
        $leave_trends_labels = $aggregates['leave_trends_labels'] ?? [];
        $leave_trends_data = $aggregates['leave_trends_data'] ?? [];
        $risk_hospitals = $aggregates['risk_hospitals'] ?? [];
        $today_usages = $aggregates['today_usages'] ?? [];

        $fatigue_staff = $liveDetails['fatigue_staff'] ?? [];
        $recent_leaves = $liveDetails['recent_leaves'] ?? [];
        $recent_logs = $liveDetails['recent_logs'] ?? [];

        $dashboardDurationMs = round((microtime(true) - $dashboardStartedAt) * 1000, 1);
        if (!headers_sent()) {
            header('X-Dashboard-Cache: ' . ($dashboardCacheHit ? 'HIT' : 'MISS'));
            header('Server-Timing: dashboard;dur=' . $dashboardDurationMs);
        }

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