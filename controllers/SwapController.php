<?php
// ที่อยู่ไฟล์: controllers/SwapController.php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'models/SwapModel.php';
require_once 'models/UserModel.php';

// นำเข้าระบบแจ้งเตือน
if (file_exists('models/NotificationModel.php')) {
    require_once 'models/NotificationModel.php';
}

class SwapController {
    
    private function checkAuth() {
        security_start_session();
        if(!isset($_SESSION['user'])) {
            header("Location: index.php?c=auth&a=index");
            exit;
        }
    }

    private function requireMutation() {
        if (!security_is_valid_post_csrf()) {
            http_response_code(403);
            $_SESSION['error_msg'] = "คำขอไม่ถูกต้องหรือหมดอายุ กรุณาลองใหม่";
            header("Location: index.php?c=swap");
            exit;
        }
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
        $this->requireMutation();
        $this->checkAuth();

        $db = (new Database())->getConnection();
        $swapModel = new SwapModel($db);

        $hospitalId = (int)($_SESSION['user']['hospital_id'] ?? 0);
        $requestorId = (int)($_SESSION['user']['id'] ?? 0);
        $requestorDate = trim((string)($_POST['requestor_date'] ?? ''));
        $requestorShift = trim((string)($_POST['requestor_shift'] ?? ''));
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $targetDate = trim((string)($_POST['target_date'] ?? ''));
        $targetShift = trim((string)($_POST['target_shift'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        $isValidDate = static function (string $date): bool {
            $obj = DateTime::createFromFormat('Y-m-d', $date);
            return $obj && $obj->format('Y-m-d') === $date;
        };

        if ($hospitalId <= 0 || $requestorId <= 0 || $targetUserId <= 0
            || !$isValidDate($requestorDate) || !$isValidDate($targetDate)) {
            $_SESSION['error_msg'] = "ข้อมูลการขอแลกเวรไม่ครบถ้วนหรือวันที่ไม่ถูกต้อง";
            header("Location: index.php?c=swap&a=index");
            exit;
        }

        if ($requestorId === $targetUserId) {
            $_SESSION['error_msg'] = "ไม่สามารถขอแลกเวรกับตัวเองได้";
            header("Location: index.php?c=swap&a=index");
            exit;
        }

        if ($reason === '' || mb_strlen($reason, 'UTF-8') > 500) {
            $_SESSION['error_msg'] = "กรุณาระบุเหตุผลการแลกเวรไม่เกิน 500 ตัวอักษร";
            header("Location: index.php?c=swap&a=index");
            exit;
        }

        // เป้าหมายต้องเป็นบุคลากรที่ยังใช้งานและสังกัดหน่วยเดียวกันเท่านั้น
        $stmtTarget = $db->prepare("
            SELECT id
            FROM users
            WHERE id = ?
              AND hospital_id = ?
              AND is_active = 1
              AND is_deleted = 0
              AND role NOT IN ('ADMIN', 'SUPERADMIN')
            LIMIT 1
        ");
        $stmtTarget->execute([$targetUserId, $hospitalId]);
        if (!$stmtTarget->fetchColumn()) {
            $_SESSION['error_msg'] = "เลือกแลกเวรได้เฉพาะบุคลากรที่อยู่ในสังกัดเดียวกัน";
            header("Location: index.php?c=swap&a=index");
            exit;
        }

        // อย่าเชื่อค่ากะจาก hidden inputs: ต้องตรงกับตารางเวรจริงทั้งสองฝ่าย
        $stmtShift = $db->prepare("
            SELECT shift_type
            FROM shifts
            WHERE hospital_id = ? AND user_id = ? AND shift_date = ?
            LIMIT 1
        ");

        $stmtShift->execute([$hospitalId, $requestorId, $requestorDate]);
        $actualRequestorShift = $stmtShift->fetchColumn();

        $stmtShift->execute([$hospitalId, $targetUserId, $targetDate]);
        $actualTargetShift = $stmtShift->fetchColumn();

        if ($actualRequestorShift === false || $actualTargetShift === false) {
            $_SESSION['error_msg'] = "ไม่พบเวรต้นทางหรือเวรของผู้ที่ต้องการแลก กรุณารีเฟรชข้อมูลแล้วลองใหม่";
            header("Location: index.php?c=swap&a=index");
            exit;
        }

        if ($requestorShift !== '' && !hash_equals((string)$actualRequestorShift, $requestorShift)) {
            $_SESSION['error_msg'] = "ข้อมูลเวรของคุณมีการเปลี่ยนแปลง กรุณารีเฟรชแล้วลองใหม่";
            header("Location: index.php?c=swap&a=index");
            exit;
        }
        if ($targetShift !== '' && !hash_equals((string)$actualTargetShift, $targetShift)) {
            $_SESSION['error_msg'] = "ข้อมูลเวรของผู้ที่ต้องการแลกมีการเปลี่ยนแปลง กรุณารีเฟรชแล้วลองใหม่";
            header("Location: index.php?c=swap&a=index");
            exit;
        }

        $data = [
            'hospital_id' => $hospitalId,
            'requestor_id' => $requestorId,
            'requestor_date' => $requestorDate,
            'requestor_shift' => (string)$actualRequestorShift,
            'target_user_id' => $targetUserId,
            'target_date' => $targetDate,
            'target_shift' => (string)$actualTargetShift,
            'reason' => $reason,
        ];

        if ($swapModel->createRequest($data)) {
            $_SESSION['success_msg'] = "ส่งคำขอแลกเวรเรียบร้อยแล้ว รอการยืนยันจากเพื่อนร่วมงาน";

            if (class_exists('NotificationModel')) {
                $notifModel = new NotificationModel($db);
                $reqName = trim((string)($_SESSION['user']['name'] ?? 'เพื่อนร่วมงาน'));
                $reqName = preg_split('/\s+/', $reqName)[0] ?? $reqName;
                $targetDateTh = date('d/m/Y', strtotime($targetDate));

                $notifModel->addNotification(
                    $targetUserId,
                    'SWAP',
                    'มีคำขอแลกเวรใหม่',
                    "คุณ {$reqName} ส่งคำขอแลกเวรกับคุณ ในวันที่ {$targetDateTh} โปรดตรวจสอบรายละเอียด",
                    'index.php?c=swap'
                );
            }
        } else {
            $_SESSION['error_msg'] = "บันทึกคำขอไม่สำเร็จ หรือมีคำขอแลกเวรนี้อยู่ระหว่างดำเนินการแล้ว";
        }

        header("Location: index.php?c=swap&a=index");
        exit;
    }

    // 🌟 จัดการสถานะการกดปุ่ม (ยอมรับ/ปฏิเสธ/อนุมัติ/ยกเลิก)
    public function action() {
        $this->requireMutation();
        $this->checkAuth();
        if (isset($_POST['id']) && isset($_POST['act'])) {
            $db = (new Database())->getConnection();
            $swapModel = new SwapModel($db);
            
            $swap_id = $_POST['id'];
            $action = $_POST['act'];
            $user_id = $_SESSION['user']['id'];
            $role = strtoupper($_SESSION['user']['role']);
            
            $swap = $swapModel->getSwapById($swap_id);
            
            // เตรียมระบบแจ้งเตือน
            $notifModel = class_exists('NotificationModel') ? new NotificationModel($db) : null;
            
            if ($swap && $swap['hospital_id'] == $_SESSION['user']['hospital_id']) {
                
                // ========================================================
                // 1. กรณีคนถูกขอแลก (Target) กด "ยอมรับ" หรือ "ปฏิเสธ"
                // ========================================================
                if ($action === 'accept' && $swap['target_user_id'] == $user_id && $swap['status'] === 'PENDING_TARGET') {
                    $swapModel->updateStatus($swap_id, 'PENDING_DIRECTOR');
                    $_SESSION['success_msg'] = "คุณได้ยืนยันการแลกเวรแล้ว (รอหัวหน้า/ผู้จัดเวรอนุมัติ)";

                    // 🔔 แจ้งเตือนไปยัง ผอ. หรือผู้จัดเวร เพื่อให้อนุมัติ
                    if ($notifModel) {
                        $stmt = $db->prepare("SELECT id FROM users WHERE hospital_id = ? AND role IN ('DIRECTOR', 'SCHEDULER', 'ADMIN')");
                        $stmt->execute([$swap['hospital_id']]);
                        while ($manager = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $notifModel->addNotification(
                                $manager['id'], 'WARNING', 'รออนุมัติแลกเวร',
                                "มีรายการตกลงแลกเวรระหว่างเจ้าหน้าที่เสร็จสิ้นแล้ว โปรดตรวจสอบและอนุมัติ",
                                'index.php?c=swap'
                            );
                        }
                    }
                } 
                else if ($action === 'reject' && $swap['target_user_id'] == $user_id && $swap['status'] === 'PENDING_TARGET') {
                    $swapModel->updateStatus($swap_id, 'REJECTED');
                    $_SESSION['error_msg'] = "คุณได้ปฏิเสธการขอแลกเวรนี้แล้ว";

                    // 🔔 แจ้งเตือนคนขอแลกว่าโดนปฏิเสธ
                    if ($notifModel) {
                        $notifModel->addNotification($swap['requestor_id'], 'DANGER', 'คำขอแลกเวรถูกปฏิเสธ', "เพื่อนร่วมงานได้ปฏิเสธคำขอแลกเวรของคุณแล้ว", 'index.php?c=swap');
                    }
                }
                
                // ========================================================
                // 2. กรณี ผอ./ผู้จัดเวร กด "อนุมัติ" หรือ "ไม่อนุมัติ"
                // ========================================================
                else if (in_array($role, ['DIRECTOR', 'SCHEDULER', 'ADMIN', 'SUPERADMIN']) && ($action === 'approve' || $action === 'decline')) {
                    if ($action === 'approve' && $swap['status'] === 'PENDING_DIRECTOR') {
                        // สลับเวรและอัปเดตสถานะ APPROVED ภายใน Transaction เดียวกัน
                        if (!$swapModel->executeSwapInRoster($swap_id)) {
                            $_SESSION['error_msg'] = "ไม่สามารถอนุมัติการแลกเวรได้ เนื่องจากข้อมูลตารางเวรเปลี่ยนแปลงหรือมีเวรซ้ำ";
                            header("Location: index.php?c=swap&a=index");
                            exit;
                        }
                        $_SESSION['success_msg'] = "อนุมัติการแลกเวรเรียบร้อย ระบบได้สลับตารางเวรให้แล้ว";

                        // 🔔 แจ้งเตือนทั้งสองฝ่ายว่าสำเร็จแล้ว
                        if ($notifModel) {
                            $notifModel->addNotification($swap['requestor_id'], 'SUCCESS', 'แลกเวรสำเร็จ', "คำขอแลกเวรได้รับการอนุมัติ และสลับตารางให้แล้ว", 'index.php?c=swap');
                            $notifModel->addNotification($swap['target_user_id'], 'SUCCESS', 'แลกเวรสำเร็จ', "คำขอแลกเวรได้รับการอนุมัติ และสลับตารางให้แล้ว", 'index.php?c=swap');
                        }
                    } 
                    else if ($action === 'decline' && $swap['status'] === 'PENDING_DIRECTOR') {
                        $swapModel->updateStatus($swap_id, 'REJECTED');
                        $_SESSION['error_msg'] = "คำขอแลกเวรนี้ถูกไม่อนุมัติ";

                        // 🔔 แจ้งเตือนทั้งสองฝ่ายว่าไม่ผ่านอนุมัติ
                        if ($notifModel) {
                            $notifModel->addNotification($swap['requestor_id'], 'DANGER', 'แลกเวรไม่อนุมัติ', "ผู้จัดเวร/ผอ. ไม่อนุมัติการแลกเวรของคุณ", 'index.php?c=swap');
                            $notifModel->addNotification($swap['target_user_id'], 'DANGER', 'แลกเวรไม่อนุมัติ', "ผู้จัดเวร/ผอ. ไม่อนุมัติการแลกเวรที่คุณเพิ่งตกลงไป", 'index.php?c=swap');
                        }
                    }
                }

                // ========================================================
                // 3. กรณีคนขอแลกเวร ต้องการ "ยกเลิก/ลบคำขอ" ของตัวเอง
                // ========================================================
                else if ($action === 'cancel' && $swap['requestor_id'] == $user_id) {
                    if (in_array($swap['status'], ['PENDING_TARGET', 'PENDING_DIRECTOR'])) {
                        // ลบคำขอออกจากระบบทันที
                        $stmt = $db->prepare("DELETE FROM shift_swaps WHERE id = ?");
                        $stmt->execute([$swap_id]);
                        $_SESSION['success_msg'] = "ยกเลิกและลบคำขอแลกเวรเรียบร้อยแล้ว";
                    } else {
                        $_SESSION['error_msg'] = "ไม่สามารถยกเลิกคำขอที่ดำเนินการเสร็จสิ้นแล้วได้";
                    }
                }
            }
        }
        header("Location: index.php?c=swap&a=index");
        exit;
    }
}
?>