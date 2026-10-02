<?php
// ที่อยู่ไฟล์: models/LeaveModel.php

class LeaveModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // 🌟 คำนวณวันทำการ (หักเสาร์-อาทิตย์ และวันหยุดนักขัตฤกษ์)
    public function calculateWorkingDays($start_date, $end_date, $hospital_id) {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $end->modify('+1 day'); // เพื่อให้วนลูปครอบคลุมวันสุดท้าย
        
        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($start, $interval, $end);
        
        // ดึงวันหยุดนักขัตฤกษ์ของปี/เดือนนั้นๆ
        $query = "SELECT holiday_date FROM holidays WHERE hospital_id = :hid OR hospital_id IS NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':hid' => $hospital_id]);
        $holidays = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $working_days = 0;
        foreach($daterange as $date){
            $date_str = $date->format("Y-m-d");
            $day_of_week = $date->format("w"); // 0=อาทิตย์, 6=เสาร์
            // เช็คว่าไม่ใช่วันเสาร์-อาทิตย์ และ ไม่ใช่วันหยุดนักขัตฤกษ์
            if($day_of_week != 0 && $day_of_week != 6 && !in_array($date_str, $holidays)){
                $working_days++;
            }
        }
        return $working_days;
    }

    // 🌟 คำนวณและดึงกระเป๋าวันลาคงเหลือของบุคลากร (ตามระเบียบประเภทพนักงาน)
    public function getUserLeaveBalances($user_id, $budget_year) {
        // 1. ดึงข้อมูล User (ประเภทพนักงาน และ วันที่บรรจุ)
        $stmt = $this->conn->prepare("SELECT employee_type, start_date FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return [];

        $emp_type = $user['employee_type'] ?? '';
        $is_official = (strpos($emp_type, 'ข้าราชการ') !== false || strpos($emp_type, 'พนักงานส่วนท้องถิ่น') !== false);
        $is_mission = (strpos($emp_type, 'ภารกิจ') !== false);
        $is_general = (strpos($emp_type, 'ทั่วไป') !== false);

        // นับอายุงานจากวันที่บรรจุ ถึง 1 ต.ค. ของปีงบประมาณนั้น (เพื่อหาเพดานสะสมวันลาพักผ่อน)
        $budget_start_date = new DateTime(($budget_year - 1) . "-10-01"); 
        $start_date = $user['start_date'] ? new DateTime($user['start_date']) : new DateTime();
        $years_of_service = $budget_start_date->diff($start_date)->y;

        // ดึงประเภทวันลาทั้งหมดจากฐานข้อมูล (จากตาราง leave_quotas)
        $stmt_q = $this->conn->query("SELECT * FROM leave_quotas");
        $quotas = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

        $balances = [];

        foreach ($quotas as $q) {
            $leave_type_id = $q['id'];
            $leave_name = $q['leave_type'];
            $base_quota = floatval($q['max_days']);
            $max_carry_over = 0;

            // ==========================================
            // กำหนดโควตาพื้นฐานและเพดานสะสมตามระเบียบ
            // ==========================================
            if ($leave_name === 'ลาพักผ่อน') {
                if ($is_official) { $max_carry_over = ($years_of_service >= 10) ? 30 : 20; } 
                elseif ($is_mission) { $max_carry_over = 15; } 
                elseif ($is_general) { $max_carry_over = 0; } 
                else { $base_quota = 0; }
            } elseif ($leave_name === 'ลากิจส่วนตัว') {
                if ($is_official || $is_mission) { $base_quota = ($years_of_service < 1) ? 15 : 45; } 
                else { $base_quota = 0; }
            } elseif ($leave_name === 'ลาป่วย') {
                if ($is_general) { $base_quota = 15; }
            } elseif ($leave_name === 'ลาไปช่วยเหลือภริยาคลอด' || strpos($leave_name, 'อุปสมบท') !== false) {
                if ($is_general) { $base_quota = 0; }
            }

            // ตรวจสอบว่ามียอดของปีงบประมาณนี้ในตาราง leave_balances แล้วหรือยัง
            $stmt_b = $this->conn->prepare("SELECT * FROM leave_balances WHERE user_id = ? AND budget_year = ? AND leave_type_id = ?");
            $stmt_b->execute([$user_id, $budget_year, $leave_type_id]);
            $current_balance = $stmt_b->fetch(PDO::FETCH_ASSOC);

            // ถ้ายังไม่มี (เริ่มปีงบประมาณใหม่) ให้ระบบสร้างรายการใหม่และดึงยอดสะสมมาให้
            if (!$current_balance) {
                $carried_over_days = 0;
                
                // ถ้าระเบียบอนุญาตให้ยกยอดมาได้ (เช่น ลาพักผ่อน ข้าราชการ)
                if ($max_carry_over > $base_quota) {
                    $last_year = $budget_year - 1;
                    $stmt_last = $this->conn->prepare("SELECT (quota_days + carried_over_days - used_days) as remaining FROM leave_balances WHERE user_id = ? AND budget_year = ? AND leave_type_id = ?");
                    $stmt_last->execute([$user_id, $last_year, $leave_type_id]);
                    $last_year_data = $stmt_last->fetch(PDO::FETCH_ASSOC);

                    if ($last_year_data && $last_year_data['remaining'] > 0) {
                        // วันลายกยอด (ต้องไม่เกินเพดานที่หักฐานปีนี้ออกแล้ว)
                        $allowable_carry = $max_carry_over - $base_quota;
                        $carried_over_days = min($last_year_data['remaining'], $allowable_carry);
                    }
                }
                
                // บันทึกรายการตั้งต้นของปีงบประมาณนี้
                $insert = "INSERT INTO leave_balances (user_id, budget_year, leave_type_id, quota_days, carried_over_days, used_days) VALUES (?, ?, ?, ?, ?, 0)";
                $stmt_in = $this->conn->prepare($insert);
                $stmt_in->execute([$user_id, $budget_year, $leave_type_id, $base_quota, $carried_over_days]);

                $current_balance = [
                    'id' => $this->conn->lastInsertId(), 'quota_days' => $base_quota, 'carried_over_days' => $carried_over_days, 'used_days' => 0
                ];
            }

            // สรุปยอดโควตาทั้งหมดที่ลาได้ในปีนี้
            $total_allowable = $current_balance['quota_days'] + $current_balance['carried_over_days'];
            $remaining = $total_allowable - $current_balance['used_days'];

            $balances[] = [
                'id' => $current_balance['id'], 
                'leave_type_id' => $leave_type_id, 
                'leave_type_name' => $leave_name,
                'calculation_type' => $q['calculation_type'] ?? 'WORKING_DAYS', 
                'quota_days' => $current_balance['quota_days'],
                'carried_over_days' => $current_balance['carried_over_days'], 
                'total_allowable' => $total_allowable,
                'used_days' => $current_balance['used_days'], 
                'remaining' => $remaining
            ];
        }
        return $balances;
    }

    // 🌟 ยื่นขอลา (รองรับการเก็บ path ไฟล์ใบรับรองแพทย์)
    public function addLeaveRequest($data) {
        $query = "INSERT INTO leave_requests (user_id, leave_type_id, start_date, end_date, num_days, reason, has_med_cert, med_cert_path, status) 
                  VALUES (:user_id, :leave_type_id, :start_date, :end_date, :num_days, :reason, :has_med_cert, :med_cert_path, 'PENDING')";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':user_id' => $data['user_id'], 
            ':leave_type_id' => $data['leave_type_id'], 
            ':start_date' => $data['start_date'],
            ':end_date' => $data['end_date'], 
            ':num_days' => $data['num_days'], 
            ':reason' => $data['reason'], 
            ':has_med_cert' => $data['has_med_cert'] ?? 0,
            ':med_cert_path' => $data['med_cert_path'] ?? null
        ]);
    }

    // 🌟 ดึงข้อมูลการลาทั้งหมดประจำเดือนของ รพ.สต. (อ้างอิงจากตาราง leave_quotas)
    public function getLeavesByHospitalAndMonth($hospital_id, $month_year) {
        $start_date = $month_year . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));

        $query = "SELECT lr.*, lq.leave_type, u.name as user_name 
                  FROM leave_requests lr
                  JOIN users u ON lr.user_id = u.id
                  JOIN leave_quotas lq ON lr.leave_type_id = lq.id
                  WHERE u.hospital_id = :hid 
                  AND (lr.start_date <= :end_date AND lr.end_date >= :start_date)
                  ORDER BY lr.start_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':hid' => $hospital_id, ':start_date' => $start_date, ':end_date' => $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 🌟 ดึงข้อมูลใบลาที่ยังรออนุมัติ (อ้างอิงจากตาราง leave_quotas)
    public function getPendingLeavesByHospital($hospital_id) {
        $query = "SELECT lr.*, lq.leave_type, u.name as user_name, u.employee_type 
                  FROM leave_requests lr
                  JOIN users u ON lr.user_id = u.id
                  JOIN leave_quotas lq ON lr.leave_type_id = lq.id
                  WHERE u.hospital_id = :hid AND lr.status = 'PENDING'
                  ORDER BY lr.created_at ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':hid' => $hospital_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeaveRequestById($id) {
        $query = "SELECT * FROM leave_requests WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 🌟 อัปเดตสถานะใบลา
    public function updateLeaveStatus($request_id, $status, $approved_by) {
        $query = "UPDATE leave_requests SET status = :status, approved_by = :approved_by, approved_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':status' => $status, ':approved_by' => $approved_by, ':id' => $request_id]);
    }

    // 🌟 ตัดยอดวันลาคงเหลือ (เรียกใช้เมื่อ ผอ. กดอนุมัติ)
    public function deductLeaveBalance($user_id, $budget_year, $leave_type_id, $days_to_deduct) {
        $query = "UPDATE leave_balances SET used_days = used_days + :days WHERE user_id = :uid AND budget_year = :year AND leave_type_id = :ltid";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':days' => $days_to_deduct, ':uid' => $user_id, ':year' => $budget_year, ':ltid' => $leave_type_id]);
    }

    // 🌟 ยกเลิกใบลา
    public function cancelLeaveRequest($request_id, $user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM leave_requests WHERE id = ? AND user_id = ?");
        $stmt->execute([$request_id, $user_id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);

        // ยกเลิกได้เฉพาะใบลาที่ยังรอ หรืออนุมัติแล้วเท่านั้น
        if ($req && in_array($req['status'], ['PENDING', 'APPROVED'])) {
            $update = $this->conn->prepare("UPDATE leave_requests SET status = 'CANCELLED' WHERE id = ?");
            $update->execute([$request_id]);

            // หากใบลาถูกอนุมัติไปแล้วและโดนหักวันลาไปแล้ว ให้ดึงคืน
            if ($req['status'] === 'APPROVED') {
                $start_month = (int)date('m', strtotime($req['start_date']));
                $start_year = (int)date('Y', strtotime($req['start_date']));
                $budget_year = ($start_month >= 10) ? $start_year + 1 : $start_year;

                $this->refundLeaveBalance($user_id, $budget_year, $req['leave_type_id'], $req['num_days']);
            }
            return true;
        }
        return false;
    }

    // 🌟 คืนยอดวันลาคงเหลือ (เรียกใช้เมื่อมีการยกเลิกใบลาที่อนุมัติแล้ว)
    public function refundLeaveBalance($user_id, $budget_year, $leave_type_id, $days_to_refund) {
        $query = "UPDATE leave_balances SET used_days = used_days - :days WHERE user_id = :uid AND budget_year = :year AND leave_type_id = :ltid";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':days' => $days_to_refund, ':uid' => $user_id, ':year' => $budget_year, ':ltid' => $leave_type_id]);
    }

    // 🌟 ดึงข้อมูลคนที่กำลัง "ลางาน" ในวันนี้ (ใช้แสดงบนหน้า Dashboard)
    public function getTodayLeaves($hospital_id, $date) {
        $query = "SELECT lr.*, lq.leave_type, u.name as user_name, u.employee_type 
                  FROM leave_requests lr
                  JOIN users u ON lr.user_id = u.id
                  JOIN leave_quotas lq ON lr.leave_type_id = lq.id
                  WHERE u.hospital_id = :hid 
                  AND lr.status = 'APPROVED'
                  AND :today BETWEEN lr.start_date AND lr.end_date";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':hid' => $hospital_id, ':today' => $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 🌟 ดึงประเภทการลาทั้งหมดเพื่อไปแสดงตอนตั้งค่า
    public function getAllLeaveQuotas() {
        return $this->conn->query("SELECT * FROM leave_quotas ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 🌟 อัปเดตตารางตั้งค่าโควตาส่วนกลาง
    public function updateLeaveQuota($id, $max_days, $description, $calc_type) {
        $stmt = $this->conn->prepare("UPDATE leave_quotas SET max_days = ?, description = ?, calculation_type = ? WHERE id = ?");
        return $stmt->execute([$max_days, $description, $calc_type, $id]);
    }
    
    // 🌟 อัปเดตข้อมูลกระเป๋าวันลารายบุคคล (เมนูแก้ไขโควตารายบุคคล)
    public function updateLeaveBalance($id, $quota_days, $carried_over_days, $used_days) {
        $stmt = $this->conn->prepare("UPDATE leave_balances SET quota_days = ?, carried_over_days = ?, used_days = ? WHERE id = ?");
        return $stmt->execute([$quota_days, $carried_over_days, $used_days, $id]);
    }
}
?>