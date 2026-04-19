<?php
// ที่อยู่ไฟล์: controllers/ReportController.php

require_once 'config/database.php';

class ReportController {

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
        header("Location: index.php?c=report&a=overview");
        exit;
    }

    // ==========================================
    // 🌟 1. หน้าภาพรวมเครือข่าย (Network Overview)
    // ==========================================
    public function overview() {
        $this->checkAuth();
        $db = (new Database())->getConnection();

        $role = strtoupper($_SESSION['user']['role']);
        
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

        try {
            // 🌟 1. คิวรี่ดึงข้อมูลภาพรวม รพ.สต. ทั้งหมด (รวมสถิติต่างๆ ในคำสั่งเดียวเพื่อความรวดเร็ว)
            $query = "
                SELECT 
                    h.id as hospital_id, 
                    h.name as hospital_name, 
                    h.district,
                    
                    -- นับจำนวนพนักงานในสังกัด
                    (SELECT COUNT(*) FROM users WHERE hospital_id = h.id AND role NOT IN ('SUPERADMIN', 'ADMIN')) as total_staff,
                    
                    -- สถานะตารางเวรเดือนที่เลือก
                    IFNULL((SELECT status FROM roster_status WHERE hospital_id = h.id AND month_year = :month_year), 'NOT_STARTED') as schedule_status,
                    
                    -- ข้อมูลการจ่ายเงินที่ถูกบันทึกไว้ตอนอนุมัติ (Snapshot JSON)
                    IFNULL((SELECT pay_summary FROM roster_status WHERE hospital_id = h.id AND month_year = :month_year_pay), NULL) as pay_summary_json,
                    
                    -- จำนวนคนขึ้นเวรวันนี้
                    (SELECT COUNT(DISTINCT user_id) FROM shifts WHERE hospital_id = h.id AND shift_date = :today AND shift_type NOT IN ('', 'L', 'O', 'OFF', 'ย')) as on_duty_today,
                    
                    -- จำนวนคนลางานวันนี้
                    (SELECT COUNT(DISTINCT lr.user_id) FROM leave_requests lr JOIN users u ON lr.user_id = u.id WHERE u.hospital_id = h.id AND lr.status = 'APPROVED' AND :today_leave BETWEEN lr.start_date AND lr.end_date) as on_leave_today
                    
                FROM hospitals h
                WHERE h.id != 0 AND h.name NOT LIKE '%ส่วนกลาง%'
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
                // ข. ถ้ายังไม่อนุมัติ (Draft, Pending) ให้ "ประเมินราคาคร่าวๆ (Estimate)" จากตารางเวรที่กำลังจัด
                else if (in_array($row['schedule_status'], ['SUBMITTED', 'PENDING', 'DRAFT', 'REQUEST_EDIT'])) {
                    $stmt_shifts = $db->prepare("
                        SELECT s.shift_type, u.pay_rate_id 
                        FROM shifts s 
                        JOIN users u ON s.user_id = u.id 
                        WHERE s.hospital_id = ? AND s.shift_date LIKE ?
                    ");
                    $stmt_shifts->execute([$row['hospital_id'], $month_year . '-%']);
                    $monthly_shifts = $stmt_shifts->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($monthly_shifts as $s) {
                        $val = trim(strtoupper($s['shift_type']));
                        $pr_id = $s['pay_rate_id'];
                        
                        // หยอดเรทค่าเวรตามกลุ่มของพนักงาน
                        $rate_r = isset($pay_rates[$pr_id]) ? (float)$pay_rates[$pr_id]['rate_r'] : 0;
                        $rate_y = isset($pay_rates[$pr_id]) ? (float)$pay_rates[$pr_id]['rate_y'] : 0;
                        $rate_b = isset($pay_rates[$pr_id]) ? (float)$pay_rates[$pr_id]['rate_b'] : 0;
                        
                        // คำนวณเงิน
                        if ($val === 'ร' || $val === 'N') $cost += $rate_r;
                        elseif ($val === 'ย' || $val === 'O') $cost += $rate_y;
                        elseif ($val === 'บ' || $val === 'A') $cost += $rate_b;
                        elseif ($val === 'บ/ร') $cost += ($rate_b + $rate_r);
                        elseif ($val === 'ย/บ') $cost += ($rate_y + $rate_b);
                    }
                }
                
                // แนบค่าที่คำนวณได้กลับเข้าไปใน Array เพื่อส่งให้ View
                $row['total_estimated_cost'] = $cost;
                $hospitals_data[] = $row;
            }

        } catch (Exception $e) {
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการดึงข้อมูลรายงาน: " . $e->getMessage();
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
    public function payroll() {
        $this->checkAuth();
        $role = strtoupper($_SESSION['user']['role']);
        
        // อนุญาตเฉพาะผู้จัดการขึ้นไป
        if (!in_array($role, ['SUPERADMIN', 'ADMIN', 'HR', 'DIRECTOR', 'SCHEDULER'])) {
            $_SESSION['error_msg'] = "⛔ คุณไม่มีสิทธิ์เข้าถึงหน้ารายงานค่าตอบแทน";
            header("Location: index.php?c=dashboard");
            exit;
        }

        $db = (new Database())->getConnection();
        
        // รับค่าตัวกรอง
        $selected_month = isset($_GET['month']) ? $_GET['month'] : date('m');
        $selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
        $month_year = $selected_year . '-' . str_pad($selected_month, 2, '0', STR_PAD_LEFT);
        
        $my_hospital_id = $_SESSION['user']['hospital_id'];
        $is_admin = in_array($role, ['SUPERADMIN', 'ADMIN', 'HR']);
        $filter_hospital = isset($_GET['hospital_id']) ? $_GET['hospital_id'] : ($is_admin ? 'all' : $my_hospital_id);

        // ดึงข้อมูล Snapshot ค่าตอบแทนจาก roster_status 
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

        // ดึงข้อมูลบุคลากรเพื่อมา Map ชื่อและตำแหน่ง
        $stmt_u = $db->query("SELECT id, name, type, employee_type, position_number FROM users");
        $users_map = [];
        while ($u = $stmt_u->fetch(PDO::FETCH_ASSOC)) {
            $users_map[$u['id']] = $u;
        }

        // แปลง JSON ให้เป็น Array สำหรับแสดงผล
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
                        if ($pay > 0) { // เอาเฉพาะคนที่มีค่าตอบแทน > 0
                            $user_info = $users_map[$uid] ?? ['name' => 'ไม่ทราบชื่อ', 'type' => '-', 'position_number' => '-'];
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

        // เรียงลำดับตาม รพ.สต. และ ชื่อ
        usort($payroll_data, function($a, $b) {
            if ($a['hospital_name'] === $b['hospital_name']) {
                return strcmp($a['user_name'], $b['user_name']);
            }
            return strcmp($a['hospital_name'], $b['hospital_name']);
        });

        // ดึงรายชื่อ รพ.สต. สำหรับ Dropdown (ส่วนกลาง)
        $hospitals_list = [];
        if ($is_admin) {
            $hospitals_list = $db->query("SELECT id, name FROM hospitals WHERE id != 0 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/reports/payroll.php';
        echo "</main></div></body></html>";
    }
}
?>