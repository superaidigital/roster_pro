<?php
// ที่อยู่ไฟล์: controllers/SwapController.php

require_once 'config/database.php';
require_once 'models/SwapModel.php';
require_once 'models/UserModel.php';

// นำเข้าระบบแจ้งเตือน
if (file_exists('models/NotificationModel.php')) {
    require_once 'models/NotificationModel.php';
}

class SwapController {
    
    private function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if(!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }
    }


    private function getCsrfToken() {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private function verifyCsrf($redirect = 'index.php?c=swap&a=index') {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $postedToken = $_POST['csrf_token'] ?? '';

        if (!is_string($sessionToken) || !is_string($postedToken) ||
            $sessionToken === '' || $postedToken === '' ||
            !hash_equals($sessionToken, $postedToken)) {
            $_SESSION['error_msg'] = 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
            header('Location: ' . $redirect);
            exit;
        }
    }

    private function isManagerRole($role) {
        return in_array(strtoupper((string)$role), ['DIRECTOR', 'SCHEDULER', 'ADMIN', 'SUPERADMIN'], true);
    }

    private function validDate($value) {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }

    // 🌟 แสดงหน้าแรกระบบแลกเวร
    public function index() {
        $this->checkAuth();
        $db = (new Database())->getConnection();
        $swapModel = new SwapModel($db);
        $userModel = new UserModel($db);

        $hospital_id = $_SESSION['user']['hospital_id'];
        $user_id = $_SESSION['user']['id'];
        $role = strtoupper($_SESSION['user']['role']);
        $csrf_token = $this->getCsrfToken();

        // ดึงข้อมูลการขอแลกเวรทั้งหมด
        $swaps = $swapModel->getSwaps($hospital_id, $user_id, $role);
        
        // ดึงรายชื่อเพื่อนร่วมงานใน รพ.สต. เพื่อแสดงใน Dropdown ขอแลกเวร
        $staff_list = $userModel->getUsersByHospital($hospital_id);

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/swap/index.php';
        echo "</main></div></body></html>"; 
    }

    // 🌟 สร้างคำขอแลกเวรใหม่
    public function create() {
        $this->checkAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?c=swap&a=index");
            exit;
        }

        $this->verifyCsrf();

        $db = (new Database())->getConnection();
        $swapModel = new SwapModel($db);

        $requestor_date = trim((string)($_POST['requestor_date'] ?? ''));
        $target_date = trim((string)($_POST['target_date'] ?? ''));
        $requestor_shift = trim((string)($_POST['requestor_shift'] ?? ''));
        $target_shift = trim((string)($_POST['target_shift'] ?? ''));
        $target_user_id = filter_input(INPUT_POST, 'target_user_id', FILTER_VALIDATE_INT);
        $reason = trim((string)($_POST['reason'] ?? ''));

        if (!$this->validDate($requestor_date) || !$this->validDate($target_date) ||
            !$target_user_id || $requestor_shift === '' || $target_shift === '') {
            $_SESSION['error_msg'] = "กรุณาระบุข้อมูลเวรให้ครบถ้วน";
            header("Location: index.php?c=swap&a=index");
            exit;
        }

        if (mb_strlen($reason, 'UTF-8') > 1000) {
            $_SESSION['error_msg'] = "เหตุผลต้องไม่เกิน 1,000 ตัวอักษร";
            header("Location: index.php?c=swap&a=index");
            exit;
        }

        $hospital_id = (int)$_SESSION['user']['hospital_id'];
        $requestor_id = (int)$_SESSION['user']['id'];

        if ($requestor_id === (int)$target_user_id) {
            $_SESSION['error_msg'] = "ไม่สามารถขอแลกเวรกับตัวเองได้";
            header("Location: index.php?c=swap&a=index");
            exit;
        }

        // ตรวจว่า target อยู่หน่วยงานเดียวกัน
        $stmt_user = $db->prepare("SELECT 1 FROM users WHERE id = ? AND hospital_id = ? LIMIT 1");
        $stmt_user->execute([(int)$target_user_id, $hospital_id]);
        if (!$stmt_user->fetchColumn()) {
            $_SESSION['error_msg'] = "ไม่พบบุคลากรปลายทางในหน่วยงานเดียวกัน";
            header("Location: index.php?c=swap&a=index");
            exit;
        }

        // ตรวจว่าเวรจริงยังตรงกับสิ่งที่ผู้ใช้เลือก
        $stmt_shift = $db->prepare("
            SELECT shift_type
            FROM shifts
            WHERE hospital_id = ? AND user_id = ? AND shift_date = ?
            LIMIT 1
        ");

        $stmt_shift->execute([$hospital_id, $requestor_id, $requestor_date]);
        $actual_requestor_shift = $stmt_shift->fetchColumn();

        $stmt_shift->execute([$hospital_id, (int)$target_user_id, $target_date]);
        $actual_target_shift = $stmt_shift->fetchColumn();

        if ($actual_requestor_shift === false || $actual_target_shift === false ||
            (string)$actual_requestor_shift !== $requestor_shift ||
            (string)$actual_target_shift !== $target_shift) {
            $_SESSION['error_msg'] = "ตารางเวรเปลี่ยนแปลงแล้ว กรุณาเปิดฟอร์มใหม่และเลือกเวรอีกครั้ง";
            header("Location: index.php?c=swap&a=index");
            exit;
        }

        $data = [
            'hospital_id' => $hospital_id,
            'requestor_id' => $requestor_id,
            'requestor_date' => $requestor_date,
            'requestor_shift' => $requestor_shift,
            'target_user_id' => (int)$target_user_id,
            'target_date' => $target_date,
            'target_shift' => $target_shift,
            'reason' => $reason
        ];

        if ($swapModel->createRequest($data)) {
            $_SESSION['success_msg'] = "ส่งคำขอแลกเวรเรียบร้อยแล้ว รอการยืนยันจากเพื่อนร่วมงาน";

            if (class_exists('NotificationModel')) {
                $notifModel = new NotificationModel($db);
                $req_name = explode(' ', (string)$_SESSION['user']['name'])[0];
                $tar_date_th = date('d/m/Y', strtotime($data['target_date']));

                $notifModel->addNotification(
                    $data['target_user_id'],
                    'SWAP',
                    'มีคำขอแลกเวรใหม่',
                    "คุณ {$req_name} ส่งคำขอแลกเวรกับคุณ ในวันที่ {$tar_date_th} โปรดตรวจสอบรายละเอียด",
                    'index.php?c=swap'
                );
            }
        } else {
            $_SESSION['error_msg'] = "ไม่สามารถบันทึกคำขอได้ อาจมีคำขอซ้ำที่กำลังดำเนินการอยู่";
        }

        header("Location: index.php?c=swap&a=index");
        exit;
    }

    // 🌟 จัดการสถานะการกดปุ่ม (ยอมรับ/ปฏิเสธ/อนุมัติ/ยกเลิก)
    public function action() {
        $this->checkAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?c=swap&a=index");
            exit;
        }

        $this->verifyCsrf();

        $swap_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $action = strtolower(trim((string)($_POST['act'] ?? '')));
        $allowed_actions = ['accept', 'reject', 'approve', 'decline', 'cancel'];

        if (!$swap_id || !in_array($action, $allowed_actions, true)) {
            $_SESSION['error_msg'] = "คำสั่งไม่ถูกต้อง";
            header("Location: index.php?c=swap&a=index");
            exit;
        }

        $db = (new Database())->getConnection();
        $swapModel = new SwapModel($db);

        $user_id = (int)$_SESSION['user']['id'];
        $hospital_id = (int)$_SESSION['user']['hospital_id'];
        $role = strtoupper((string)$_SESSION['user']['role']);
        $swap = $swapModel->getSwapById($swap_id);
        $notifModel = class_exists('NotificationModel') ? new NotificationModel($db) : null;

        if (!$swap || (int)$swap['hospital_id'] !== $hospital_id) {
            $_SESSION['error_msg'] = "ไม่พบคำขอ หรือคุณไม่มีสิทธิ์ดำเนินการ";
            header("Location: index.php?c=swap&a=index");
            exit;
        }

        if ($action === 'accept' &&
            (int)$swap['target_user_id'] === $user_id &&
            $swap['status'] === 'PENDING_TARGET') {

            $swapModel->updateStatus($swap_id, 'PENDING_DIRECTOR');
            $_SESSION['success_msg'] = "ยืนยันการแลกเวรแล้ว รอหัวหน้า/ผู้จัดเวรอนุมัติ";

            if ($notifModel) {
                $stmt = $db->prepare("SELECT id FROM users WHERE hospital_id = ? AND role IN ('DIRECTOR', 'SCHEDULER', 'ADMIN', 'SUPERADMIN')");
                $stmt->execute([$hospital_id]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $manager) {
                    $notifModel->addNotification(
                        $manager['id'],
                        'WARNING',
                        'รออนุมัติแลกเวร',
                        'คู่แลกเวรยืนยันแล้ว โปรดตรวจสอบและอนุมัติ',
                        'index.php?c=swap'
                    );
                }
            }
        } elseif ($action === 'reject' &&
                  (int)$swap['target_user_id'] === $user_id &&
                  $swap['status'] === 'PENDING_TARGET') {

            $swapModel->updateStatus($swap_id, 'REJECTED');
            $_SESSION['success_msg'] = "ปฏิเสธคำขอแลกเวรเรียบร้อยแล้ว";

            if ($notifModel) {
                $notifModel->addNotification(
                    $swap['requestor_id'],
                    'DANGER',
                    'คำขอแลกเวรถูกปฏิเสธ',
                    'เพื่อนร่วมงานปฏิเสธคำขอแลกเวรของคุณ',
                    'index.php?c=swap'
                );
            }
        } elseif ($action === 'approve' &&
                  $this->isManagerRole($role) &&
                  $swap['status'] === 'PENDING_DIRECTOR') {

            if ($swapModel->executeSwapInRoster($swap_id)) {
                $_SESSION['success_msg'] = "อนุมัติการแลกเวรเรียบร้อย และตารางเวรถูกสลับแล้ว";

                if ($notifModel) {
                    $notifModel->addNotification($swap['requestor_id'], 'SUCCESS', 'แลกเวรสำเร็จ', 'คำขอแลกเวรได้รับอนุมัติและสลับตารางแล้ว', 'index.php?c=swap');
                    $notifModel->addNotification($swap['target_user_id'], 'SUCCESS', 'แลกเวรสำเร็จ', 'คำขอแลกเวรได้รับอนุมัติและสลับตารางแล้ว', 'index.php?c=swap');
                }
            } else {
                $_SESSION['error_msg'] = "ไม่สามารถสลับเวรได้ ตารางอาจเปลี่ยนแปลงหรือมีเวรชนกัน กรุณาตรวจสอบอีกครั้ง";
            }
        } elseif ($action === 'decline' &&
                  $this->isManagerRole($role) &&
                  $swap['status'] === 'PENDING_DIRECTOR') {

            $swapModel->updateStatus($swap_id, 'REJECTED');
            $_SESSION['success_msg'] = "ไม่อนุมัติคำขอแลกเวรเรียบร้อยแล้ว";

            if ($notifModel) {
                $notifModel->addNotification($swap['requestor_id'], 'DANGER', 'แลกเวรไม่อนุมัติ', 'ผู้จัดเวร/ผอ. ไม่อนุมัติการแลกเวรของคุณ', 'index.php?c=swap');
                $notifModel->addNotification($swap['target_user_id'], 'DANGER', 'แลกเวรไม่อนุมัติ', 'ผู้จัดเวร/ผอ. ไม่อนุมัติการแลกเวรที่คุณยืนยันไว้', 'index.php?c=swap');
            }
        } elseif ($action === 'cancel' &&
                  (int)$swap['requestor_id'] === $user_id) {

            if ($swapModel->cancelRequest($swap_id, $user_id)) {
                $_SESSION['success_msg'] = "ยกเลิกคำขอแลกเวรเรียบร้อยแล้ว";
            } else {
                $_SESSION['error_msg'] = "คำขอนี้ไม่สามารถยกเลิกได้ในสถานะปัจจุบัน";
            }
        } else {
            $_SESSION['error_msg'] = "คุณไม่มีสิทธิ์ดำเนินการ หรือสถานะคำขอเปลี่ยนแปลงแล้ว";
        }

        header("Location: index.php?c=swap&a=index");
        exit;
    }

}
?>