<?php
// ที่อยู่ไฟล์: controllers/ReportController.php

require_once 'config/database.php';

class ReportController { // 🌟 แก้ไขตรงนี้ ตัดตัว s ออกให้ตรงกับชื่อไฟล์

    // ==========================================
    // 🛡️ ตรวจสอบสิทธิ์การเข้าใช้งาน
    // ==========================================
    private function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=login");
            exit;
        }
    }

    // ==========================================
    // 🚦 นำทางเริ่มต้น
    // ==========================================
    public function index() {
        $this->checkAuth();
        
        // 🌟 ดึงข้อมูลหน้าศูนย์รวมรายงาน (เข้าจาก c=report)
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/reports/index.php';
        echo "</main></div></body></html>";
    }

    // ==========================================
    // 🌟 1. หน้าภาพรวมเครือข่าย (Network Overview)
    // ==========================================
    public function overview() {
        $this->checkAuth();
        $db = (new Database())->getConnection();

        $role = strtoupper($_SESSION['user']['role']);
        $my_hospital_id = $_SESSION['user']['hospital_id'] ?? 0;
        $is_admin = in_array($role, ['ADMIN', 'SUPERADMIN', 'HR']);
        
        // 🔒 อนุญาตให้เข้าถึงเฉพาะระดับผู้บริหารหรือผู้จัดเวรเท่านั้น
        if (!in_array($role, ['SUPERADMIN', 'ADMIN', 'HR', 'DIRECTOR', 'SCHEDULER'])) {
            $_SESSION['error_msg'] = "คุณไม่มีสิทธิ์เข้าถึงหน้ารายงานภาพรวมเครือข่าย";
            header("Location: index.php?c=dashboard");
            exit;
        }

        // 📅 รับค่าเดือนและปี หรือใช้ค่าปัจจุบันถ้าไม่ได้เลือกมา
        $selected_month = isset($_GET['month']) ? str_pad($_GET['month'], 2, '0', STR_PAD_LEFT) : date('m');
        $selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
        $month_year = $selected_year . '-' . $selected_month;
        $today = date('Y-m-d');

        // เตรียมข้อมูลส่งให้ View
        $hospitals_data = [];
        $yearly_data = [];

        try {
            // 🌟 1. คิวรี่ดึงข้อมูลภาพรวม รพ.สต. ทั้งหมด หรือเฉพาะ รพ. ตัวเอง
            $hosp_condition = $is_admin ? "h.id != 0 AND h.name NOT LIKE '%ส่วนกลาง%'" : "h.id = " . (int)$my_hospital_id;

            $query = "
                SELECT 
                    h.id as hospital_id, 
                    h.name as hospital_name, 
                    h.district,
                    
                    -- นับจำนวนพนักงานในสังกัด
                    (SELECT COUNT(*) FROM users WHERE hospital_id = h.id AND role NOT IN ('SUPERADMIN', 'ADMIN') AND deleted_at IS NULL) as total_staff,
                    
                    -- สถานะตารางเวรเดือนที่เลือก
                    IFNULL((SELECT status FROM roster_status WHERE hospital_id = h.id AND month_year = :month_year), 'NOT_STARTED') as schedule_status,
                    
                    -- ข้อมูลการจ่ายเงินที่ถูกบันทึกไว้ตอนอนุมัติ (Snapshot JSON)
                    IFNULL((SELECT pay_summary FROM roster_status WHERE hospital_id = h.id AND month_year = :month_year_pay), NULL) as pay_summary_json,
                    
                    -- จำนวนคนขึ้นเวรวันนี้
                    (SELECT COUNT(DISTINCT user_id) FROM shifts WHERE hospital_id = h.id AND shift_date = :today AND shift_type NOT IN ('', 'L', 'O', 'OFF', 'ย')) as on_duty_today,
                    
                    -- จำนวนคนลางานวันนี้
                    (SELECT COUNT(DISTINCT lr.user_id) FROM leave_requests lr JOIN users u ON lr.user_id = u.id WHERE u.hospital_id = h.id AND lr.status = 'APPROVED' AND :today_leave BETWEEN lr.start_date AND lr.end_date) as on_leave_today
                    
                FROM hospitals h
                WHERE $hosp_condition AND h.deleted_at IS NULL AND h.is_active = 1
                ORDER BY h.name ASC
            ";

            $stmt = $db->prepare($query);
            $stmt->execute([
                ':month_year' => $month_year,
                ':month_year_pay' => $month_year,
                ':today' => $today,
                ':today_leave' => $today
            ]);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 🌟 2. ดึงเรทค่าเวรทั้งหมดมาไว้คำนวณแบบ Real-time
            $stmt_rates = $db->query("SELECT * FROM pay_rates");
            $pay_rates = [];
            while ($r = $stmt_rates->fetch(PDO::FETCH_ASSOC)) {
                $pay_rates[$r['id']] = $r;
            }

            // 🌟 3. วนลูปเพื่อคำนวณงบประมาณค่าตอบแทน
            foreach ($results as $row) {
                $cost = 0;
                
                // ก. ถ้ายืนยันตารางเวรแล้ว (APPROVED) ให้ดึงยอดเงินที่บันทึกไว้มาแสดง เพื่อความแม่นยำสูงสุด
                if ($row['schedule_status'] == 'APPROVED' && !empty($row['pay_summary_json'])) {
                    $pay_data = json_decode($row['pay_summary_json'], true);
                    if (is_array($pay_data)) {
                        foreach ($pay_data as $p) {
                            $cost += isset($p['pay']) ? (float)$p['pay'] : 0;
                        }
                    }
                } 
                // ข. ถ้ายังไม่อนุมัติ ให้ "ประเมินราคาคร่าวๆ (Estimate)" จากตารางเวรที่กำลังจัด
                else if (in_array($row['schedule_status'], ['SUBMITTED', 'PENDING', 'DRAFT', 'REQUEST_EDIT'])) {
                    $stmt_shifts = $db->prepare("
                        SELECT s.shift_type, u.pay_rate_id 
                        FROM shifts s 
                        JOIN users u ON s.user_id = u.id 
                        WHERE s.hospital_id = ? AND s.shift_date LIKE ? AND s.shift_type NOT IN ('', 'OFF', 'L', 'O')
                    ");
                    $stmt_shifts->execute([$row['hospital_id'], $month_year . '-%']);
                    $monthly_shifts = $stmt_shifts->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($monthly_shifts as $s) {
                        $val = trim(strtoupper($s['shift_type']));
                        $pr_id = $s['pay_rate_id'];
                        
                        $rate_r = isset($pay_rates[$pr_id]) ? (float)$pay_rates[$pr_id]['rate_r'] : 0;
                        $rate_y = isset($pay_rates[$pr_id]) ? (float)$pay_rates[$pr_id]['rate_y'] : 0;
                        $rate_b = isset($pay_rates[$pr_id]) ? (float)$pay_rates[$pr_id]['rate_b'] : 0;
                        
                        if ($val === 'ร' || $val === 'N') $cost += $rate_r;
                        elseif ($val === 'ย' || $val === 'O') $cost += $rate_y;
                        elseif ($val === 'บ' || $val === 'A') $cost += $rate_b;
                        elseif ($val === 'บ/ร') $cost += ($rate_b + $rate_r);
                        elseif ($val === 'ย/บ') $cost += ($rate_y + $rate_b);
                    }
                }
                
                $row['total_estimated_cost'] = $cost;
                $hospitals_data[] = $row;
            }

            // 🌟 4. ดึงสถานะการส่งเวร 12 เดือน (สำหรับ รพ.สต. เท่านั้นที่จะใช้ตรงนี้)
            if (!$is_admin) {
                $stmt_yearly = $db->prepare("SELECT month_year, status FROM roster_status WHERE hospital_id = ? AND month_year LIKE ?");
                $stmt_yearly->execute([$my_hospital_id, "$selected_year-%"]);
                $db_statuses = $stmt_yearly->fetchAll(PDO::FETCH_KEY_PAIR);
                
                for ($m = 1; $m <= 12; $m++) {
                    $my = $selected_year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
                    $yearly_data[$m] = ['month_year' => $my, 'status' => $db_statuses[$my] ?? 'NOT_STARTED'];
                }
            }

        } catch (Exception $e) {
            error_log('ReportController error: ' . $e->getMessage());
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ";
        }

        // โหลด View ไปแสดงผล
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/reports/overview.php';
        echo "</main></div></body></html>";
    }

    // ==========================================
    // 💰 2. หน้าสรุปค่าตอบแทน (Payroll / เบิกจ่าย)
    // ==========================================
    public function pay_summary() {
        $this->checkAuth();
        $role = strtoupper($_SESSION['user']['role']);
        
        // อนุญาตเฉพาะผู้จัดการขึ้นไป
        if (!in_array($role, ['SUPERADMIN', 'ADMIN', 'HR', 'DIRECTOR', 'SCHEDULER'])) {
            $_SESSION['error_msg'] = "⛔ คุณไม่มีสิทธิ์เข้าถึงหน้ารายงานค่าตอบแทน";
            header("Location: index.php?c=dashboard");
            exit;
        }

        $db = (new Database())->getConnection();
        
        $selected_month = isset($_GET['month']) ? $_GET['month'] : date('m');
        $selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
        $month_year = $selected_year . '-' . str_pad($selected_month, 2, '0', STR_PAD_LEFT);
        
        $my_hospital_id = $_SESSION['user']['hospital_id'];
        $is_admin = in_array($role, ['SUPERADMIN', 'ADMIN', 'HR']);
        $filter_hospital = isset($_GET['hospital_id']) ? $_GET['hospital_id'] : ($is_admin ? 'all' : $my_hospital_id);

        $sql = "SELECT rs.hospital_id, h.name as hospital_name, rs.status, rs.pay_summary, rs.updated_at 
                FROM roster_status rs 
                JOIN hospitals h ON rs.hospital_id = h.id 
                WHERE rs.month_year = ?";
        $params = [$month_year];

        if ($filter_hospital !== 'all') {
            $sql .= " AND rs.hospital_id = ?";
            $params[] = $filter_hospital;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $roster_statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt_u = $db->query("SELECT id, name, type, employee_type, position_number FROM users WHERE deleted_at IS NULL");
        $users_map = [];
        while ($u = $stmt_u->fetch(PDO::FETCH_ASSOC)) {
            $users_map[$u['id']] = $u;
        }

        $payroll_data = [];
        $total_network_budget = 0;
        $total_staff_paid = 0;

        foreach ($roster_statuses as $rs) {
            // จะเบิกจ่ายได้ ตารางเวรต้องเป็นสถานะ 'APPROVED' เท่านั้น
            if ($rs['status'] === 'APPROVED' && !empty($rs['pay_summary'])) {
                $pay_details = json_decode($rs['pay_summary'], true);
                
                if (is_array($pay_details)) {
                    foreach ($pay_details as $uid => $data) {
                        $pay = $data['pay'] ?? 0;
                        if ($pay > 0) { 
                            $user_info = $users_map[$uid] ?? ['name' => 'ไม่ทราบชื่อ (ถูกลบ)', 'type' => '-', 'position_number' => '-'];
                            $payroll_data[] = [
                                'hospital_name' => $rs['hospital_name'],
                                'user_name' => $user_info['name'],
                                'type' => $user_info['type'],
                                'position_number' => $user_info['position_number'],
                                'pay' => $pay
                            ];
                            $total_network_budget += $pay;
                            $total_staff_paid++;
                        }
                    }
                }
            }
        }

        usort($payroll_data, function($a, $b) {
            if ($a['hospital_name'] === $b['hospital_name']) {
                return strcmp($a['user_name'], $b['user_name']);
            }
            return strcmp($a['hospital_name'], $b['hospital_name']);
        });

        $hospitals_list = [];
        if ($is_admin) {
            $hospitals_list = $db->query("SELECT id, name FROM hospitals WHERE id != 0 AND deleted_at IS NULL ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/reports/payroll.php';
        echo "</main></div></body></html>";
    }

    // ==========================================
    // 📊 3. หน้าสรุปภาระงาน (Workload)
    // ==========================================
    public function workload() {
        $this->checkAuth();
        $role = strtoupper($_SESSION['user']['role']);
        
        // อนุญาตเฉพาะระดับบริหาร/จัดการ
        if (!in_array($role, ['SUPERADMIN', 'ADMIN', 'HR', 'DIRECTOR', 'SCHEDULER'])) {
            $_SESSION['error_msg'] = "⛔ คุณไม่มีสิทธิ์เข้าถึงหน้ารายงานภาระงาน";
            header("Location: index.php?c=dashboard");
            exit;
        }

        $db = (new Database())->getConnection();
        
        $selected_month = isset($_GET['month']) ? $_GET['month'] : date('m');
        $selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
        $month_year = $selected_year . '-' . str_pad($selected_month, 2, '0', STR_PAD_LEFT);
        
        $my_hospital_id = $_SESSION['user']['hospital_id'];
        $is_admin = in_array($role, ['SUPERADMIN', 'ADMIN', 'HR']);
        $filter_hospital = isset($_GET['hospital_id']) ? $_GET['hospital_id'] : ($is_admin ? 'all' : $my_hospital_id);

        // 1. ดึงรายชื่อบุคลากรทั้งหมด
        $sql_users = "SELECT u.id, u.name, u.type, u.position_number, h.name as hospital_name 
                      FROM users u 
                      JOIN hospitals h ON u.hospital_id = h.id 
                      WHERE u.deleted_at IS NULL AND u.role NOT IN ('SUPERADMIN', 'ADMIN')";
        if ($filter_hospital !== 'all') {
            $sql_users .= " AND u.hospital_id = " . (int)$filter_hospital;
        }
        $users = $db->query($sql_users)->fetchAll(PDO::FETCH_ASSOC);

        // 2. ดึงข้อมูลตารางเวรในเดือนที่เลือก
        $sql_shifts = "SELECT user_id, shift_type FROM shifts WHERE shift_date LIKE ?";
        $params_shifts = [$month_year . '-%'];
        if ($filter_hospital !== 'all') {
            $sql_shifts .= " AND hospital_id = ?";
            $params_shifts[] = (int)$filter_hospital;
        }
        $stmt_shifts = $db->prepare($sql_shifts);
        $stmt_shifts->execute($params_shifts);
        $shifts = $stmt_shifts->fetchAll(PDO::FETCH_ASSOC);

        // 3. ประมวลผลนับจำนวนกะแยกตามประเภท
        $workload_data = [];
        foreach ($users as $u) {
            $uid = $u['id'];
            $count_m = 0; // เช้า (ร)
            $count_a = 0; // บ่าย (บ)
            $count_n = 0; // ดึก (ย)
            $count_total = 0;

            foreach ($shifts as $s) {
                if ($s['user_id'] == $uid) {
                    $val = trim(strtoupper($s['shift_type']));
                    
                    if ($val === 'ร' || $val === 'N') { $count_m++; $count_total++; }
                    elseif ($val === 'บ' || $val === 'A') { $count_a++; $count_total++; }
                    elseif ($val === 'ย' || $val === 'O' || $val === 'ด') { $count_n++; $count_total++; }
                    // กรณีควงเวร 2 กะ
                    elseif ($val === 'บ/ร' || $val === 'ร/บ') { $count_a++; $count_m++; $count_total+=2; }
                    elseif ($val === 'ย/บ' || $val === 'บ/ย') { $count_n++; $count_a++; $count_total+=2; }
                    elseif ($val === 'ย/ร' || $val === 'ร/ย') { $count_n++; $count_m++; $count_total+=2; }
                }
            }

            // เก็บเฉพาะคนที่มีการขึ้นเวร หรือถ้าอยากให้แสดงทุกคนให้เอา if ออก
            if ($count_total > 0) {
                $workload_data[] = [
                    'hospital_name' => $u['hospital_name'],
                    'user_name' => $u['name'],
                    'type' => $u['type'],
                    'position_number' => $u['position_number'],
                    'shift_m' => $count_m,
                    'shift_a' => $count_a,
                    'shift_n' => $count_n,
                    'total' => $count_total
                ];
            }
        }

        // เรียงลำดับตามจำนวนเวรรวม จากมากไปน้อย (หาคนทำงานหนัก)
        usort($workload_data, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        // ดึงข้อมูลสำหรับ Dropdown ส่วนกลาง
        $hospitals_list = [];
        if ($is_admin) {
            $hospitals_list = $db->query("SELECT id, name FROM hospitals WHERE id != 0 AND deleted_at IS NULL ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/reports/workload.php'; // เรียกไฟล์ View
        echo "</main></div></body></html>";
    }
    
    // ==========================================
    // 📅 4. หน้าประวัติการลาหยุด (Leave History)
    // ==========================================
    public function leave() {
        $this->checkAuth();
        $role = strtoupper($_SESSION['user']['role']);
        
        // อนุญาตเฉพาะระดับบริหาร/จัดการ
        if (!in_array($role, ['SUPERADMIN', 'ADMIN', 'HR', 'DIRECTOR', 'SCHEDULER'])) {
            $_SESSION['error_msg'] = "⛔ คุณไม่มีสิทธิ์เข้าถึงหน้ารายงานประวัติการลาหยุด";
            header("Location: index.php?c=dashboard");
            exit;
        }

        $db = (new Database())->getConnection();
        
        $selected_month = isset($_GET['month']) ? $_GET['month'] : date('m');
        $selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
        $month_year = $selected_year . '-' . str_pad($selected_month, 2, '0', STR_PAD_LEFT);
        
        // หาวันแรกและวันสุดท้ายของเดือนที่เลือก เพื่อให้ครอบคลุมการลาที่คาบเกี่ยวเดือน
        $start_of_month = $month_year . '-01';
        $end_of_month = date('Y-m-t', strtotime($start_of_month));
        
        $my_hospital_id = $_SESSION['user']['hospital_id'];
        $is_admin = in_array($role, ['SUPERADMIN', 'ADMIN', 'HR']);
        $filter_hospital = isset($_GET['hospital_id']) ? $_GET['hospital_id'] : ($is_admin ? 'all' : $my_hospital_id);

        // 1. ดึงข้อมูลประวัติการลาหยุด
        $sql = "SELECT lr.*, u.name as user_name, u.type as user_type, h.name as hospital_name, lq.leave_type,
                       lr.num_days as leave_days
                FROM leave_requests lr
                JOIN users u ON lr.user_id = u.id
                JOIN hospitals h ON u.hospital_id = h.id
                JOIN leave_quotas lq ON lr.leave_type_id = lq.id
                WHERE lr.start_date <= ? AND lr.end_date >= ? AND u.deleted_at IS NULL";
        
        $params = [$end_of_month, $start_of_month]; // เช็คช่วงเวลาที่คาบเกี่ยวกัน

        if ($filter_hospital !== 'all') {
            $sql .= " AND u.hospital_id = ?";
            $params[] = (int)$filter_hospital;
        }
        
        $sql .= " ORDER BY lr.start_date DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $leave_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. ประมวลผลข้อมูลสำหรับกราฟและ KPI
        $total_leave_days = 0;
        $pending_requests = 0;
        $users_on_leave = [];
        $leave_type_counts = [];

        foreach ($leave_records as $record) {
            // นับเฉพาะใบลาที่ "อนุมัติแล้ว" ไปรวมในสถิติวันลา
            if ($record['status'] === 'APPROVED') {
                $total_leave_days += $record['leave_days'];
                $users_on_leave[$record['user_id']] = true;
                
                $type = $record['leave_type'];
                if (!isset($leave_type_counts[$type])) {
                    $leave_type_counts[$type] = 0;
                }
                $leave_type_counts[$type]++;
            } 
            // นับใบลาที่รออนุมัติ
            elseif ($record['status'] === 'PENDING') {
                $pending_requests++;
            }
        }
        
        $total_users_on_leave = count($users_on_leave);
        
        // หาประเภทการลาที่ถูกใช้เยอะสุด
        arsort($leave_type_counts);
        $top_leave_type = !empty($leave_type_counts) ? array_key_first($leave_type_counts) : '-';

        // ดึงข้อมูลสำหรับ Dropdown ส่วนกลาง
        $hospitals_list = [];
        if ($is_admin) {
            $hospitals_list = $db->query("SELECT id, name FROM hospitals WHERE id != 0 AND deleted_at IS NULL ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/reports/leave.php'; // เรียกไฟล์ View
        echo "</main></div></body></html>";
    }
    
    // ==========================================
    // 👥 5. หน้าทะเบียนข้อมูลบุคลากร (Staff Registry)
    // ==========================================
    public function staff() {
        $this->checkAuth();
        $role = strtoupper($_SESSION['user']['role']);
        
        // อนุญาตเฉพาะระดับบริหาร/จัดการ
        if (!in_array($role, ['SUPERADMIN', 'ADMIN', 'HR', 'DIRECTOR', 'SCHEDULER'])) {
            $_SESSION['error_msg'] = "⛔ คุณไม่มีสิทธิ์เข้าถึงหน้ารายงานทะเบียนบุคลากร";
            header("Location: index.php?c=dashboard");
            exit;
        }

        $db = (new Database())->getConnection();
        
        $my_hospital_id = $_SESSION['user']['hospital_id'];
        $is_admin = in_array($role, ['SUPERADMIN', 'ADMIN', 'HR']);
        $filter_hospital = isset($_GET['hospital_id']) ? $_GET['hospital_id'] : ($is_admin ? 'all' : $my_hospital_id);

        // 1. ดึงข้อมูลบุคลากร (ซ่อน Superadmin เพื่อความปลอดภัย)
        $sql = "SELECT u.id, u.name, u.type, u.employee_type, u.position_number, u.role as system_role, h.name as hospital_name 
                FROM users u 
                LEFT JOIN hospitals h ON u.hospital_id = h.id 
                WHERE u.deleted_at IS NULL AND u.role NOT IN ('SUPERADMIN')";
        
        $params = [];
        if ($filter_hospital !== 'all') {
            $sql .= " AND u.hospital_id = ?";
            $params[] = (int)$filter_hospital;
        }
        $sql .= " ORDER BY h.name ASC, u.name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $staff_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. ประมวลผลสถิติภาพรวม (KPIs)
        $total_staff = count($staff_records);
        $type_counts = []; // นับตามวิชาชีพ/ตำแหน่ง
        $emp_type_counts = []; // นับตามประเภทการจ้างงาน

        foreach ($staff_records as $staff) {
            // นับตามตำแหน่ง
            $type = empty(trim($staff['type'])) ? 'ไม่ระบุตำแหน่ง' : trim($staff['type']);
            $type_counts[$type] = ($type_counts[$type] ?? 0) + 1;

            // นับตามประเภทการจ้างงาน
            $emp_type = empty(trim($staff['employee_type'])) ? 'ไม่ระบุประเภทจ้างงาน' : trim($staff['employee_type']);
            $emp_type_counts[$emp_type] = ($emp_type_counts[$emp_type] ?? 0) + 1;
        }

        // เรียงลำดับจากมากไปน้อย
        arsort($type_counts);
        arsort($emp_type_counts);

        // ดึงข้อมูลสำหรับ Dropdown ส่วนกลาง
        $hospitals_list = [];
        if ($is_admin) {
            $hospitals_list = $db->query("SELECT id, name FROM hospitals WHERE id != 0 AND deleted_at IS NULL ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/reports/staff.php'; // เรียกไฟล์ View
        echo "</main></div></body></html>";
    }
}
?>