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
            header("Location: index.php?c=auth");
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
    // 🌟 หน้าภาพรวมเครือข่าย (Network Overview)
    // ==========================================
    public function overview() {
        $this->checkAuth();
        $db = (new Database())->getConnection();

        $role = strtoupper($_SESSION['user']['role']);
        
        // 🔒 อนุญาตให้เข้าถึงเฉพาะระดับผู้บริหารหรือผู้จัดเวรเท่านั้น
        if (!in_array($role, ['SUPERADMIN', 'ADMIN', 'DIRECTOR', 'SCHEDULER'])) {
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
}
?>