<?php
// ที่อยู่ไฟล์: controllers/HrController.php

require_once 'config/database.php';
require_once 'models/UserModel.php';
require_once 'models/ProfileModel.php';
require_once 'models/HrModel.php';
require_once 'models/PayRateModel.php';
require_once 'models/HospitalModel.php';

class HrController {
    
    // ==========================================
    // 🛡️ ตรวจสอบสิทธิ์การเข้าใช้งาน
    // ==========================================
    private function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $allowed_roles = ['ADMIN', 'SUPERADMIN', 'HR', 'DIRECTOR'];
        if (!isset($_SESSION['user']) || !in_array(strtoupper($_SESSION['user']['role']), $allowed_roles)) {
            $_SESSION['error_msg'] = "คุณไม่มีสิทธิ์เข้าถึงระบบบริหารงานบุคคล (HR)";
            header("Location: index.php?c=dashboard");
            exit;
        }
    }

    // ==========================================
    // 📊 1. หน้า Dashboard & ภาพรวม (HR Dashboard)
    // ==========================================
    public function dashboard() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        
        $hrModel = new HrModel($db);
        $profileModel = new ProfileModel($db);

        $is_superadmin = in_array(strtoupper($_SESSION['user']['role']), ['ADMIN', 'SUPERADMIN', 'HR']);
        $my_hosp_id = $is_superadmin ? 0 : $_SESSION['user']['hospital_id'];

        // ดึงข้อมูลสถิติภาพรวม
        $stats = $hrModel->getDashboardStats($my_hosp_id);
        $expiring_licenses = $profileModel->getExpiringLicenses($my_hosp_id);
        $birthdays = $hrModel->getBirthdaysThisMonth($my_hosp_id);
        $gender_stats = $hrModel->getGenderStats($my_hosp_id);

        // 🌟 ดึงข้อมูลความสมบูรณ์ประวัติสำหรับแสดงในหน้า Dashboard
        $staff_comp_data = $hrModel->getProfileCompleteness($my_hosp_id);
        $total_staff_comp = count($staff_comp_data);
        $perfect_staff_comp = 0;
        $total_score_comp = 0;

        foreach($staff_comp_data as $s) {
            $total_score_comp += $s['completeness_score'];
            if($s['completeness_score'] == 100) $perfect_staff_comp++;
        }

        $avg_score_comp = 0;
        if($total_staff_comp > 0) {
            $avg_score_comp = round($total_score_comp / $total_staff_comp);
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/hr/dashboard.php';
        echo "</main></div></body></html>";
    }

    // ==========================================
    // 🗃️ 2. หน้าทำเนียบผู้พ้นสภาพ (Inactive Staff)
    // ==========================================
    public function inactive() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        $hrModel = new HrModel($db);

        $is_superadmin = in_array(strtoupper($_SESSION['user']['role']), ['ADMIN', 'SUPERADMIN', 'HR']);
        $my_hosp_id = $is_superadmin ? 0 : $_SESSION['user']['hospital_id'];

        $inactive_staff = $hrModel->getInactiveStaff($my_hosp_id);

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/hr/inactive.php';
        echo "</main></div></body></html>";
    }

    // ==========================================
    // 💰 3. หน้ารายงานค่าตอบแทนเวร (Payroll Export)
    // ==========================================
    public function payroll() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        
        $userModel = new UserModel($db);
        $payRateModel = new PayRateModel($db);
        $hospitalModel = new HospitalModel($db);

        $is_superadmin = in_array(strtoupper($_SESSION['user']['role']), ['ADMIN', 'SUPERADMIN', 'HR']);
        
        $selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
        $filter_hosp = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : ($is_superadmin ? 0 : $_SESSION['user']['hospital_id']);

        // ดึงพนักงาน
        if ($filter_hosp == 0) {
            $staff_list = $userModel->getAllUsers();
        } else {
            $staff_list = $userModel->getUsersByHospital($filter_hosp);
        }
        
        $hospitals_list = $hospitalModel->getAllHospitals();
        $pay_rates = $payRateModel->getAllRates();

        // ดึงเวรของเดือนนั้น
        $like_month = $selected_month . '-%';
        $stmtShifts = $db->prepare("SELECT user_id, shift_type FROM shifts WHERE shift_date LIKE :month");
        $stmtShifts->execute([':month' => $like_month]);
        $all_shifts = $stmtShifts->fetchAll(PDO::FETCH_ASSOC);

        // คำนวณค่าตอบแทนให้แต่ละคน
        $payroll_data = [];
        $total_budget = 0;

        foreach ($staff_list as $staff) {
            if ($staff['is_active'] == 0) continue; // ข้ามคนที่พ้นสภาพ

            $sum_r = 0; $sum_y = 0; $sum_b = 0;
            foreach ($all_shifts as $s) {
                if ($s['user_id'] == $staff['id']) {
                    $val = $s['shift_type'];
                    if ($val === 'ร' || $val === 'N') $sum_r++;
                    elseif ($val === 'ย' || $val === 'O') $sum_y++;
                    elseif ($val === 'บ' || $val === 'A') $sum_b++;
                    elseif ($val === 'บ/ร') { $sum_b++; $sum_r++; }
                    elseif ($val === 'ย/บ') { $sum_y++; $sum_b++; }
                }
            }

            if (($sum_r + $sum_y + $sum_b) > 0) {
                // หาเรทค่าตอบแทน
                $rate_r = 0; $rate_y = 0; $rate_b = 0; $group_name = 'ไม่ได้จัดกลุ่ม';
                foreach ($pay_rates as $pr) {
                    if ($pr['id'] == $staff['pay_rate_id']) {
                        $rate_r = $pr['rate_r'];
                        $rate_y = $pr['rate_y'];
                        $rate_b = $pr['rate_b'];
                        $group_name = $pr['name'] ?? $pr['keywords'] ?? 'กลุ่มที่ ' . $pr['id'];
                        break;
                    }
                }

                $total_pay = ($sum_r * $rate_r) + ($sum_y * $rate_y) + ($sum_b * $rate_b);
                $total_budget += $total_pay;

                $payroll_data[] = [
                    'id' => $staff['id'],
                    'name' => $staff['name'],
                    'position' => $staff['position'],
                    'employee_type' => $staff['employee_type'],
                    'group_name' => $group_name,
                    'sum_r' => $sum_r, 'rate_r' => $rate_r,
                    'sum_y' => $sum_y, 'rate_y' => $rate_y,
                    'sum_b' => $sum_b, 'rate_b' => $rate_b,
                    'total_pay' => $total_pay
                ];
            }
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/hr/payroll.php';
        echo "</main></div></body></html>";
    }

    // ==========================================
    // 📋 4. หน้าตรวจสอบความสมบูรณ์ข้อมูลบุคลากร (Completeness)
    // ==========================================
    public function completeness() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        $hrModel = new HrModel($db);

        $is_superadmin = in_array(strtoupper($_SESSION['user']['role']), ['ADMIN', 'SUPERADMIN', 'HR']);
        $my_hosp_id = $is_superadmin ? 0 : $_SESSION['user']['hospital_id'];

        $staff_data = $hrModel->getProfileCompleteness($my_hosp_id);
        
        // คำนวณสถิติภาพรวม
        $total_staff = count($staff_data);
        $perfect_staff = 0;
        $avg_score = 0;
        $total_score_sum = 0;

        foreach($staff_data as $s) {
            $total_score_sum += $s['completeness_score'];
            if($s['completeness_score'] == 100) $perfect_staff++;
        }

        if($total_staff > 0) {
            $avg_score = round($total_score_sum / $total_staff);
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/hr/completeness.php';
        echo "</main></div></body></html>";
    }
}
?>