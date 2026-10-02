<?php
// ที่อยู่ไฟล์: controllers/ProfileController.php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'models/UserModel.php';
require_once 'models/ProfileModel.php';
require_once 'controllers/LogsController.php'; 

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

    // ====================================================
    // 🌟 1. โหลดหน้า Dashboard แฟ้มประวัติ (Profile View)
    // ====================================================
    public function index() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        
        $userModel = new UserModel($db);
        $profileModel = new ProfileModel($db);

        // ตรวจสอบว่าจะดูประวัติใคร (ถ้าไม่ส่ง id มา ให้ดึงของตัวเอง)
        $target_user_id = isset($_POST['id']) ? (int)$_POST['id'] : $_SESSION['user']['id'];
        
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
        
        $userModel = new UserModel($db);
        $profileModel = new ProfileModel($db);

        $user_id = $_SESSION['user']['id'];
        $selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
        
        // ดึงข้อมูลพื้นฐานและตารางเวรส่วนตัว
        $target_user = $userModel->getUserById($user_id);
        $shifts = $profileModel->getUserShifts($user_id, $selected_month);

        // โหลด View
        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/profile/schedule.php';
        echo "</main></div></body></html>";
    }

    // ====================================================
    // 💾 2. บันทึกข้อมูลส่วนตัวและที่อยู่ (General Profile)
    // ====================================================
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
?>