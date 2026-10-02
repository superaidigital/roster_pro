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
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = (new Database())->getConnection();
            $swapModel = new SwapModel($db);

            $data = [
                'hospital_id' => $_SESSION['user']['hospital_id'],
                'requestor_id' => $_SESSION['user']['id'],
                'requestor_date' => $_POST['requestor_date'],
                'requestor_shift' => $_POST['requestor_shift'],
                'target_user_id' => $_POST['target_user_id'],
                'target_date' => $_POST['target_date'],
                'target_shift' => $_POST['target_shift'],
                'reason' => trim($_POST['reason'])
            ];

            if ($data['requestor_id'] == $data['target_user_id']) {
                $_SESSION['error_msg'] = "ไม่สามารถขอแลกเวรกับตัวเองได้";
            } else if ($swapModel->createRequest($data)) {
                $_SESSION['success_msg'] = "ส่งคำขอแลกเวรเรียบร้อยแล้ว รอการยืนยันจากเพื่อนร่วมงาน";

                // 🔔 ส่งแจ้งเตือนหา "เพื่อน" ที่ถูกขอแลกเวร
                if (class_exists('NotificationModel')) {
                    $notifModel = new NotificationModel($db);
                    $req_name = explode(' ', $_SESSION['user']['name'])[0];
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
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึกคำขอ (คุณอาจจะกำลังกดส่งคำขอซ้ำซ้อน)";
            }
        }
        header("Location: index.php?c=swap&a=index");
        exit;
    }

    // 🌟 จัดการสถานะการกดปุ่ม (ยอมรับ/ปฏิเสธ/อนุมัติ/ยกเลิก)
    public function action() {
        $this->checkAuth();
        if (isset($_GET['id']) && isset($_GET['act'])) {
            $db = (new Database())->getConnection();
            $swapModel = new SwapModel($db);
            
            $swap_id = $_GET['id'];
            $action = $_GET['act'];
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
                        $swapModel->updateStatus($swap_id, 'APPROVED');
                        // สลับเวรในตารางข้อมูลจริง
                        $swapModel->executeSwapInRoster($swap_id);
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