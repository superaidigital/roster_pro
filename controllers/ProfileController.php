<?php
// ที่อยู่ไฟล์: controllers/ProfileController.php

require_once 'config/database.php';
require_once 'models/UserModel.php';

class ProfileController {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function index() {
        if (!isset($_SESSION['user'])) { header("Location: index.php?c=auth&a=login"); exit; }
        $userModel = new UserModel($this->db);
        $user_info = $userModel->getUserById($_SESSION['user']['id']);
        
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/profile/index.php';
        echo "</main></div></body></html>";
    }

    public function update_info() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user'])) {
            $userModel = new UserModel($this->db);
            $user_info = $userModel->getUserById($_SESSION['user']['id']);
            $data = [
                'id' => $_SESSION['user']['id'],
                'name' => $user_info['name'],
                'role' => $user_info['role'],
                'hospital_id' => $user_info['hospital_id'],
                'type' => $user_info['type'],
                'position_number' => $user_info['position_number'],
                'color_theme' => $user_info['color_theme'],
                'employee_type' => $user_info['employee_type'],
                'start_date' => $user_info['start_date'],
                'phone' => $_POST['phone'] ?? $user_info['phone']
            ];
            if ($userModel->updateUser($data)) { $_SESSION['success_msg'] = "อัปเดตข้อมูลเบอร์โทรศัพท์เรียบร้อยแล้ว"; } 
            else { $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล"; }
        }
        header("Location: index.php?c=profile&a=index"); exit;
    }

    public function change_password() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user'])) {
            $userModel = new UserModel($this->db);
            $user_info = $userModel->getUserById($_SESSION['user']['id']);
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if (!password_verify($current_password, $user_info['password'])) { $_SESSION['error_msg'] = "รหัสผ่านปัจจุบันไม่ถูกต้อง"; } 
            elseif ($new_password !== $confirm_password) { $_SESSION['error_msg'] = "รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน"; } 
            elseif (strlen($new_password) < 6) { $_SESSION['error_msg'] = "รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร"; } 
            else {
                $data = $user_info;
                $data['password'] = $new_password; 
                if ($userModel->updateUser($data)) { $_SESSION['success_msg'] = "เปลี่ยนรหัสผ่านใหม่เรียบร้อยแล้ว"; } 
                else { $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน"; }
            }
        }
        header("Location: index.php?c=profile&a=index"); exit;
    }

    // ==========================================
    // 🌟 ฟังก์ชันโหลดหน้า ปฏิทินเวรของฉัน
    // ==========================================
    public function schedule() {
        if (!isset($_SESSION['user'])) { header("Location: index.php?c=auth&a=login"); exit; }

        $user_id = $_SESSION['user']['id'];
        $selected_ym = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

        $my_shifts = [];
        $my_leaves = [];
        $raw_leaves = []; // เก็บใบลาแบบรวบยอด (ช่วงวันที่) เพื่อไปโชว์ฝั่งขวา
        $holidays = [];
        $summary = ['บ' => 0, 'ร' => 0, 'ย' => 0, 'pay' => 0];

        // 1. ดึงวันหยุดนักขัตฤกษ์
        $stmt = $this->db->prepare("SELECT holiday_date, holiday_name FROM holidays WHERE holiday_date LIKE ?");
        $stmt->execute(["$selected_ym-%"]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $holidays[$row['holiday_date']] = $row['holiday_name']; }

        // 2. ดึงข้อมูลประวัติการลา 
        $first_day = "$selected_ym-01";
        $last_day = date('Y-m-t', strtotime($first_day));
        
        // 🌟 แก้ไข: กลับมาใช้ JOIN ตาราง leave_quotas และเรียกฟิลด์ lq.leave_type ให้ตรงกับ Database ของคุณ
        $stmt = $this->db->prepare("
            SELECT lr.start_date, lr.end_date, lq.leave_type, lr.status 
            FROM leave_requests lr
            JOIN leave_quotas lq ON lr.leave_type_id = lq.id
            WHERE lr.user_id = ? 
            AND (lr.start_date LIKE ? OR lr.end_date LIKE ? OR (lr.start_date <= ? AND lr.end_date >= ?))
        ");
        $ym_like = "$selected_ym-%";
        $stmt->execute([$user_id, $ym_like, $ym_like, $last_day, $first_day]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $raw_leaves[] = $row; // เก็บข้อมูลช่วงการลาเพื่อส่งให้ List View (ฝั่งขวา)

            $begin = new DateTime($row['start_date']);
            $end = new DateTime($row['end_date']);
            $end->modify('+1 day'); 
            $period = new DatePeriod($begin, DateInterval::createFromDateString('1 day'), $end);
            
            foreach ($period as $dt) {
                $d_str = $dt->format("Y-m-d");
                if (strpos($d_str, $selected_ym) === 0) {
                    $my_leaves[$d_str] = ['type' => $row['leave_type'], 'status' => $row['status']];
                }
            }
        }

        // 3. ดึงเรทค่าตอบแทน
        $rates = ['ร' => 0, 'ย' => 0, 'บ' => 0];
        $stmt = $this->db->prepare("SELECT employee_type FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $staff_type = $stmt->fetch(PDO::FETCH_ASSOC)['employee_type'] ?? '';

        $stmt = $this->db->query("SELECT * FROM pay_rates");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $group) {
            $keywords = explode(',', $group['keywords']);
            foreach ($keywords as $kw) {
                if (trim($kw) !== '' && mb_strpos($staff_type, trim($kw)) !== false) {
                    $rates = ['ร' => $group['rate_r'], 'ย' => $group['rate_y'], 'บ' => $group['rate_b']];
                    break 2;
                }
            }
        }

        // 4. ดึงกะเวร
        $stmt = $this->db->prepare("SELECT shift_date, shift_type FROM shifts WHERE user_id = ? AND shift_date LIKE ? AND shift_type != ''");
        $stmt->execute([$user_id, "$selected_ym-%"]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $d_str = $row['shift_date'];
            $val = $row['shift_type'];
            $my_shifts[$d_str] = $val;
            $leave_status = isset($my_leaves[$d_str]) ? $my_leaves[$d_str]['status'] : null;
            
            if ($leave_status !== 'APPROVED') {
                if ($val === 'ร') { $summary['ร']++; $summary['pay'] += $rates['ร']; }
                elseif ($val === 'ย') { $summary['ย']++; $summary['pay'] += $rates['ย']; }
                elseif ($val === 'บ') { $summary['บ']++; $summary['pay'] += $rates['บ']; }
                elseif ($val === 'บ/ร' || $val === 'ร/บ') { $summary['บ']++; $summary['ร']++; $summary['pay'] += ($rates['บ'] + $rates['ร']); }
                elseif ($val === 'ย/บ' || $val === 'บ/ย') { $summary['ย']++; $summary['บ']++; $summary['pay'] += ($rates['ย'] + $rates['บ']); }
            }
        }

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/profile/schedule.php';
        echo "</main></div></body></html>";
    }
}
?>