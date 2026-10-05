<?php
// ที่อยู่ไฟล์: controllers/ProfileController.php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'models/UserModel.php';
require_once 'models/ProfileModel.php';
require_once 'controllers/LogsController.php';
require_once 'lib/ElectronicSignature.php'; 

class ProfileController {
    
    // ====================================================
    // 🛡️ ตรวจสอบสิทธิ์การเข้าใช้งาน
    // ====================================================
    private function checkAuth() {
        security_start_session();
        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=login");
            exit;
        }
    }


    private function canManageProfile(int $targetUserId): bool {
        security_start_session();

        if (!isset($_SESSION['user']) || $targetUserId <= 0) {
            return false;
        }

        $currentUserId = (int)($_SESSION['user']['id'] ?? 0);
        $currentRole = strtoupper((string)($_SESSION['user']['role'] ?? 'STAFF'));

        if ($targetUserId === $currentUserId) {
            return true;
        }

        if (in_array($currentRole, ['SUPERADMIN', 'ADMIN', 'HR'], true)) {
            return true;
        }

        if ($currentRole !== 'DIRECTOR') {
            return false;
        }

        $db = (new Database())->getConnection();
        $stmt = $db->prepare("SELECT hospital_id FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$targetUserId]);
        $targetHospitalId = $stmt->fetchColumn();

        return $targetHospitalId !== false
            && (int)$targetHospitalId === (int)($_SESSION['user']['hospital_id'] ?? 0);
    }

    private function requirePostAndCsrf(): void {
        security_start_session();

        if (!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }

        if (!security_is_valid_post_csrf()) {
            http_response_code(403);
            $_SESSION['error_msg'] = "คำขอไม่ถูกต้องหรือหมดอายุ กรุณาลองใหม่";
            header("Location: index.php?c=profile");
            exit;
        }
    }

    private function requireProfileManagePermission(int $targetUserId): void {
        security_start_session();

        $currentUserId = (int)($_SESSION['user']['id'] ?? 0);
        $currentRole = strtoupper((string)($_SESSION['user']['role'] ?? 'STAFF'));

        $allowed = $targetUserId > 0 && (
            $targetUserId === $currentUserId
            || in_array($currentRole, ['SUPERADMIN', 'ADMIN', 'HR'], true)
        );

        if (!$allowed && $currentRole === 'DIRECTOR' && $targetUserId > 0) {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("SELECT hospital_id FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$targetUserId]);
            $targetHospitalId = $stmt->fetchColumn();
            $allowed = $targetHospitalId !== false
                && (int)$targetHospitalId === (int)($_SESSION['user']['hospital_id'] ?? 0);
        }

        if (!$allowed) {
            http_response_code(403);
            $_SESSION['error_msg'] = "คุณไม่มีสิทธิ์แก้ไขข้อมูลบุคลากรรายนี้";
            header("Location: index.php?c=profile&id=" . $currentUserId);
            exit;
        }
    }
    private function requireSignatureOwner(int $targetUserId): void {
        security_start_session();

        $currentUserId = (int)($_SESSION['user']['id'] ?? 0);
        if ($targetUserId <= 0 || $targetUserId !== $currentUserId) {
            http_response_code(403);
            $_SESSION['error_msg'] = 'ลายเซ็นอิเล็กทรอนิกส์ต้องบันทึกหรือลบโดยเจ้าของบัญชีเท่านั้น';
            header('Location: index.php?c=profile&id=' . $currentUserId . '#nav-signature');
            exit;
        }
    }


    // ====================================================
    // 🌟 1. โหลดหน้า Dashboard แฟ้มประวัติ (Profile View)
    // ====================================================
    public function index() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        
        $userModel = new UserModel($db);
        $profileModel = new ProfileModel($db);

        // ตรวจสอบว่าจะดูประวัติใคร (ถ้าไม่ส่ง id มา ให้ดึงของตัวเอง)
        $target_user_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : (int)$_SESSION['user']['id']);
        
        // ดึงข้อมูลพื้นฐานจากระบบ
        $target_user = $userModel->getUserById($target_user_id);
        if (!$target_user) {
            $_SESSION['error_msg'] = "ไม่พบข้อมูลบุคลากรในระบบ";
            header("Location: index.php?c=staff");
            exit;
        }

        // ตรวจสอบสิทธิ์ (HR, ADMIN, SUPERADMIN, DIRECTOR ดูได้ทุกคน / STAFF ดูได้แค่ของตัวเอง)
        $current_role = strtoupper($_SESSION['user']['role']);
        if (!$this->canManageProfile($target_user_id)) {
            $_SESSION['error_msg'] = "ปฏิเสธการเข้าถึง: คุณสามารถดูได้เฉพาะประวัติของตนเองเท่านั้น";
            header("Location: index.php?c=profile&id=" . $_SESSION['user']['id']);
            exit;
        }

        // ดึงข้อมูลเชิงลึกทั้งหมดผ่าน ProfileModel
        $profile = $profileModel->getProfileByUserId($target_user_id);
        $educations = $profileModel->getEducationByUserId($target_user_id);
        $licenses = $profileModel->getLicensesByUserId($target_user_id);
        $work_histories = $profileModel->getWorkHistoryByUserId($target_user_id);
        $trainings = $profileModel->getTrainingsByUserId($target_user_id);

        // คำนวณอายุ
        $age = '-';
        if ($profile && !empty($profile['birth_date'])) {
            $age = $profileModel->calculateAge($profile['birth_date']);
        }

        // โหลด View หน้าแฟ้มประวัติส่วนบุคคล
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/profile/index.php'; // เราจะสร้างไฟล์นี้ในขั้นตอนถัดไป
        echo "</main></div></body></html>";
    }

    // ====================================================
    // 📅 1.5 โหลดหน้าปฏิทินเวรของฉัน (My Schedule)
    // ====================================================
    public function schedule() {
        $this->checkAuth();
        $db = (new Database())->getConnection();

        $user_id = $_SESSION['user']['id'];
        $selected_ym = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

        $my_shifts = [];
        $my_leaves = [];
        $raw_leaves = []; // เก็บใบลาแบบรวบยอด (ช่วงวันที่) เพื่อไปโชว์ฝั่งขวา
        $holidays = [];
        $summary = ['บ' => 0, 'ร' => 0, 'ย' => 0, 'pay' => 0];

        // 1. ดึงวันหยุดนักขัตฤกษ์
        $stmt = $db->prepare("SELECT holiday_date, holiday_name FROM holidays WHERE holiday_date LIKE ?");
        $stmt->execute(["$selected_ym-%"]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $holidays[$row['holiday_date']] = $row['holiday_name']; }

        // 2. ดึงข้อมูลประวัติการลา 
        $first_day = "$selected_ym-01";
        $last_day = date('Y-m-t', strtotime($first_day));
        
        // 🌟 แก้ไข: กลับมาใช้ JOIN ตาราง leave_quotas และเรียกฟิลด์ lq.leave_type ให้ตรงกับ Database ของคุณ
        $stmt = $db->prepare("
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
        $stmt = $db->prepare("SELECT employee_type FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $staff_type = $stmt->fetch(PDO::FETCH_ASSOC)['employee_type'] ?? '';

        $stmt = $db->query("SELECT * FROM pay_rates");
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
        $stmt = $db->prepare("SELECT shift_date, shift_type FROM shifts WHERE user_id = ? AND shift_date LIKE ? AND shift_type != ''");
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

    public function save_profile() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
            $profileModel = new ProfileModel($db);
            
            $target_user_id = (int)($_POST['user_id'] ?? 0);
            $this->requirePostAndCsrf();
            $this->requireProfileManagePermission($target_user_id);
            
            $data = [
                'user_id' => $target_user_id,
                'title_name' => trim($_POST['title_name'] ?? ''),
                'first_name_th' => trim($_POST['first_name_th'] ?? ''),
                'last_name_th' => trim($_POST['last_name_th'] ?? ''),
                'first_name_en' => trim($_POST['first_name_en'] ?? ''),
                'last_name_en' => trim($_POST['last_name_en'] ?? ''),
                'gender' => trim($_POST['gender'] ?? ''),
                'birth_date' => !empty($_POST['birth_date']) ? $_POST['birth_date'] : null,
                'blood_group' => trim($_POST['blood_group'] ?? ''),
                'marital_status' => trim($_POST['marital_status'] ?? ''),
                'nationality' => trim($_POST['nationality'] ?? 'ไทย'),
                'religion' => trim($_POST['religion'] ?? 'พุทธ'),
                'address_permanent' => trim($_POST['address_permanent'] ?? ''),
                'address_current' => trim($_POST['address_current'] ?? ''),
                'emergency_contact_name' => trim($_POST['emergency_contact_name'] ?? ''),
                'emergency_contact_relation' => trim($_POST['emergency_contact_relation'] ?? ''),
                'emergency_contact_phone' => trim($_POST['emergency_contact_phone'] ?? ''),
                'bank_name' => trim($_POST['bank_name'] ?? ''),
                'bank_branch' => trim($_POST['bank_branch'] ?? ''),
                'bank_account_no' => trim($_POST['bank_account_no'] ?? '')
            ];

            if ($profileModel->saveProfile($data)) {
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "อัปเดตข้อมูลประวัติส่วนตัวของ ID: " . $target_user_id);
                $_SESSION['success_msg'] = "บันทึกข้อมูลประวัติส่วนตัวสำเร็จ";
            } else {
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาด ไม่สามารถบันทึกข้อมูลได้";
            }
            
            header("Location: index.php?c=profile&id=" . $target_user_id);
            exit;
        }
    }

    // ====================================================
    // ✍️ 2.5 ลายเซ็นอิเล็กทรอนิกส์
    // ====================================================
    public function save_signature() {
        $this->checkAuth();
        $this->requirePostAndCsrf();

        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $this->requireSignatureOwner($targetUserId);

        $dataUrl = trim((string)($_POST['signature_data'] ?? ''));
        $method = strtoupper(trim((string)($_POST['signature_method'] ?? 'DRAW')));

        try {
            $signature = ElectronicSignature::normalize($dataUrl, $method);

            $db = (new Database())->getConnection();
            $userModel = new UserModel($db);

            if (!$userModel->updateSignature($targetUserId, $signature['data_url'], $signature['method'])) {
                throw new RuntimeException('Unable to save signature.');
            }

            if ((int)($_SESSION['user']['id'] ?? 0) === $targetUserId) {
                $_SESSION['user']['signature_path'] = $signature['data_url'];
                $_SESSION['user']['signature_sha256'] = $signature['sha256'];
                $_SESSION['user']['signature_method'] = $signature['method'];
                $_SESSION['user']['signature_updated_at'] = date('Y-m-d H:i:s');
            }

            LogsController::addLog(
                $db,
                (int)$_SESSION['user']['id'],
                LogsController::ACTION_UPDATE,
                'บันทึกลายเซ็นอิเล็กทรอนิกส์ user_id=' . $targetUserId
                    . ' method=' . $signature['method']
                    . ' sha256=' . substr($signature['sha256'], 0, 16)
            );

            $_SESSION['success_msg'] = 'บันทึกลายเซ็นอิเล็กทรอนิกส์เรียบร้อยแล้ว';
        } catch (Throwable $e) {
            error_log('Electronic signature save rejected: ' . $e->getMessage());
            $_SESSION['error_msg'] = 'ไม่สามารถบันทึกลายเซ็นได้ กรุณาวาดใหม่หรือใช้ไฟล์ PNG/JPG ที่ถูกต้อง';
        }

        header('Location: index.php?c=profile&id=' . $targetUserId . '#nav-signature');
        exit;
    }

    public function delete_signature() {
        $this->checkAuth();
        $this->requirePostAndCsrf();

        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $this->requireSignatureOwner($targetUserId);

        $db = (new Database())->getConnection();
        $userModel = new UserModel($db);

        if ($userModel->clearSignature($targetUserId)) {
            if ((int)($_SESSION['user']['id'] ?? 0) === $targetUserId) {
                $_SESSION['user']['signature_path'] = null;
                $_SESSION['user']['signature_sha256'] = null;
                $_SESSION['user']['signature_method'] = null;
                $_SESSION['user']['signature_updated_at'] = date('Y-m-d H:i:s');
            }

            LogsController::addLog(
                $db,
                (int)$_SESSION['user']['id'],
                LogsController::ACTION_UPDATE,
                'ลบลายเซ็นอิเล็กทรอนิกส์ user_id=' . $targetUserId
            );
            $_SESSION['success_msg'] = 'ลบลายเซ็นอิเล็กทรอนิกส์แล้ว';
        } else {
            $_SESSION['error_msg'] = 'ไม่สามารถลบลายเซ็นได้';
        }

        header('Location: index.php?c=profile&id=' . $targetUserId . '#nav-signature');
        exit;
    }

    // ====================================================
    // 🎓 3. การศึกษา (Education)
    // ====================================================
    public function add_education() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
            $profileModel = new ProfileModel($db);
            
            $user_id = (int)($_POST['user_id'] ?? 0);
            $this->requirePostAndCsrf();
            $this->requireProfileManagePermission($user_id);
            $data = [
                'user_id' => $user_id,
                'degree_level' => trim($_POST['degree_level'] ?? ''),
                'degree_name' => trim($_POST['degree_name'] ?? ''),
                'major' => trim($_POST['major'] ?? ''),
                'institution' => trim($_POST['institution'] ?? ''),
                'graduation_year' => trim($_POST['graduation_year'] ?? ''),
                'gpa' => !empty($_POST['gpa']) ? $_POST['gpa'] : null
            ];

            if ($profileModel->addEducation($data)) {
                $_SESSION['success_msg'] = "เพิ่มประวัติการศึกษาสำเร็จ";
            } else {
                $_SESSION['error_msg'] = "เพิ่มข้อมูลล้มเหลว";
            }
            header("Location: index.php?c=profile&id=" . $user_id);
            exit;
        }
    }

    public function delete_education() {
        $this->checkAuth();
        $this->requirePostAndCsrf();

        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $this->requireProfileManagePermission($targetUserId);
if (isset($_POST['id']) && isset($_POST['user_id'])) {
            $db = (new Database())->getConnection();
            $profileModel = new ProfileModel($db);
            
            if ($profileModel->deleteEducation($_POST['id'], $_POST['user_id'])) {
                $_SESSION['success_msg'] = "ลบประวัติการศึกษาสำเร็จ";
            } else {
                $_SESSION['error_msg'] = "ไม่สามารถลบข้อมูลได้";
            }
            header("Location: index.php?c=profile&id=" . $_POST['user_id']);
            exit;
        }
    }

    // ====================================================
    // 🪪 4. ใบประกอบวิชาชีพ (Licenses)
    // ====================================================
    public function add_license() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
            $profileModel = new ProfileModel($db);
            
            $user_id = (int)($_POST['user_id'] ?? 0);
            $this->requirePostAndCsrf();
            $this->requireProfileManagePermission($user_id);
            $data = [
                'user_id' => $user_id,
                'license_name' => trim($_POST['license_name'] ?? ''),
                'license_no' => trim($_POST['license_no'] ?? ''),
                'council_name' => trim($_POST['council_name'] ?? ''),
                'issue_date' => !empty($_POST['issue_date']) ? $_POST['issue_date'] : null,
                'expire_date' => !empty($_POST['expire_date']) ? $_POST['expire_date'] : null,
                'status' => trim($_POST['status'] ?? 'ACTIVE')
            ];

            if ($profileModel->addLicense($data)) {
                $_SESSION['success_msg'] = "เพิ่มข้อมูลใบประกอบวิชาชีพสำเร็จ";
            } else {
                $_SESSION['error_msg'] = "เพิ่มข้อมูลล้มเหลว";
            }
            header("Location: index.php?c=profile&id=" . $user_id);
            exit;
        }
    }

    public function delete_license() {
        $this->checkAuth();
        $this->requirePostAndCsrf();

        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $this->requireProfileManagePermission($targetUserId);
if (isset($_POST['id']) && isset($_POST['user_id'])) {
            $db = (new Database())->getConnection();
            $profileModel = new ProfileModel($db);
            
            if ($profileModel->deleteLicense($_POST['id'], $_POST['user_id'])) {
                $_SESSION['success_msg'] = "ลบข้อมูลใบประกอบวิชาชีพสำเร็จ";
            }
            header("Location: index.php?c=profile&id=" . $_POST['user_id']);
            exit;
        }
    }

    // ====================================================
    // 💼 5. ประวัติการทำงาน (Work History)
    // ====================================================
    public function add_work() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
            $profileModel = new ProfileModel($db);
            
            $user_id = (int)($_POST['user_id'] ?? 0);
            $this->requirePostAndCsrf();
            $this->requireProfileManagePermission($user_id);
            $data = [
                'user_id' => $user_id,
                'company_name' => trim($_POST['company_name'] ?? ''),
                'position' => trim($_POST['position'] ?? ''),
                'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
                'salary' => !empty($_POST['salary']) ? str_replace(',', '', $_POST['salary']) : null,
                'reason_for_leave' => trim($_POST['reason_for_leave'] ?? ''),
                'reference_contact' => trim($_POST['reference_contact'] ?? '')
            ];

            if ($profileModel->addWorkHistory($data)) {
                $_SESSION['success_msg'] = "เพิ่มประวัติการทำงานสำเร็จ";
            }
            header("Location: index.php?c=profile&id=" . $user_id);
            exit;
        }
    }

    public function delete_work() {
        $this->checkAuth();
        $this->requirePostAndCsrf();

        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $this->requireProfileManagePermission($targetUserId);
if (isset($_POST['id']) && isset($_POST['user_id'])) {
            $db = (new Database())->getConnection();
            $profileModel = new ProfileModel($db);
            $profileModel->deleteWorkHistory($_POST['id'], $_POST['user_id']);
            $_SESSION['success_msg'] = "ลบประวัติการทำงานสำเร็จ";
            header("Location: index.php?c=profile&id=" . $_POST['user_id']);
            exit;
        }
    }

    // ====================================================
    // 🏆 6. ประวัติการฝึกอบรม (Trainings / CPE)
    // ====================================================
    public function add_training() {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = (new Database())->getConnection();
            $profileModel = new ProfileModel($db);
            
            $user_id = (int)($_POST['user_id'] ?? 0);
            $this->requirePostAndCsrf();
            $this->requireProfileManagePermission($user_id);
            $data = [
                'user_id' => $user_id,
                'course_name' => trim($_POST['course_name'] ?? ''),
                'organizer' => trim($_POST['organizer'] ?? ''),
                'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
                'cpe_credits' => !empty($_POST['cpe_credits']) ? $_POST['cpe_credits'] : 0
            ];

            if ($profileModel->addTraining($data)) {
                $_SESSION['success_msg'] = "เพิ่มประวัติการฝึกอบรมสำเร็จ";
            }
            header("Location: index.php?c=profile&id=" . $user_id);
            exit;
        }
    }

    public function delete_training() {
        $this->checkAuth();
        $this->requirePostAndCsrf();

        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $this->requireProfileManagePermission($targetUserId);
if (isset($_POST['id']) && isset($_POST['user_id'])) {
            $db = (new Database())->getConnection();
            $profileModel = new ProfileModel($db);
            $profileModel->deleteTraining($_POST['id'], $_POST['user_id']);
            $_SESSION['success_msg'] = "ลบประวัติการฝึกอบรมสำเร็จ";
            header("Location: index.php?c=profile&id=" . $_POST['user_id']);
            exit;
        }
    }
}