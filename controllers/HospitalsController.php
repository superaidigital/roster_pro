<?php
// ที่อยู่ไฟล์: controllers/HospitalsController.php

require_once 'config/database.php';
require_once 'models/HospitalModel.php';
require_once 'controllers/LogsController.php';

class HospitalsController {
    
    // 🛡️ ฟังก์ชันตรวจสอบสิทธิ์
    private function requireAccess($allowed_roles = []) {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if (!isset($_SESSION['user'])) { header("Location: index.php?c=auth&a=index"); exit; }
        if (!empty($allowed_roles) && !in_array(strtoupper($_SESSION['user']['role']), $allowed_roles)) {
            $_SESSION['error_msg'] = "ปฏิเสธการเข้าถึง: คุณไม่มีสิทธิ์ใช้งานเมนูนี้";
            header("Location: index.php?c=dashboard"); exit;
        }
    }

    // ==========================================
    // 🌟 โหลดหน้าตารางรายชื่อ รพ.สต. ทั้งหมด
    // ==========================================
    public function index() {
        // 🌟 อนุญาตให้ ผอ. (DIRECTOR) เข้ามาดูหน้ารวมได้
        $this->requireAccess(['SUPERADMIN', 'ADMIN', 'DIRECTOR']);
        
        $db = (new Database())->getConnection();
        $hospitalModel = new HospitalModel($db);
        
        $hospitals = $hospitalModel->getAllHospitals();

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/hospitals/index.php';
        echo "</main></div></body></html>";
    }

    // ==========================================
    // 🌟 เพิ่ม รพ.สต. ใหม่ (Add) - ให้เฉพาะ Admin
    // ==========================================
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);

        $db = (new Database())->getConnection();
        $hospitalModel = new HospitalModel($db);

        $name = $_POST['name'] ?? '';
        $hospital_code = $_POST['hospital_code'] ?? null; 
        
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;

        $hospitalModel->addHospital($name, $hospital_code, 'S', $latitude, $longitude);
        LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "เพิ่ม รพ.สต. ใหม่ ({$name})");
        $_SESSION['success_msg'] = "เพิ่มหน่วยบริการใหม่เรียบร้อยแล้ว";

        header("Location: index.php?c=hospitals");
        exit;
    }

    // ==========================================
    // 🌟 แก้ไข รพ.สต. (Edit)
    // ==========================================
    public function edit() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;
        // 🌟 อนุญาตให้ ผอ. ใช้งานฟังก์ชันแก้ไขได้
        $this->requireAccess(['SUPERADMIN', 'ADMIN', 'DIRECTOR']);

        $db = (new Database())->getConnection();
        $hospitalModel = new HospitalModel($db);

        $id = $_POST['id'] ?? null;
        
        // 🛡️ ดักจับความปลอดภัย: ผอ. แก้ไขได้เฉพาะ รพ. ของตัวเองเท่านั้น
        if (strtoupper($_SESSION['user']['role']) === 'DIRECTOR' && $id != $_SESSION['user']['hospital_id']) {
            $_SESSION['error_msg'] = "ปฏิเสธการเข้าถึง: คุณไม่มีสิทธิ์แก้ไขข้อมูลหน่วยบริการอื่น";
            header("Location: index.php?c=hospitals");
            exit;
        }

        $name = $_POST['name'] ?? '';
        $hospital_code = $_POST['hospital_code'] ?? null; 
        
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;

        if (!empty($id)) {
            $hospitalModel->updateHospital($id, $name, $hospital_code, 'S', $latitude, $longitude);
            LogsController::addLog($db, $_SESSION['user']['id'], 'UPDATE', "แก้ไขข้อมูล รพ.สต. ID: {$id} เป็น ({$name})");
            $_SESSION['success_msg'] = "แก้ไขข้อมูลหน่วยบริการสำเร็จ";
        }

        header("Location: index.php?c=hospitals");
        exit;
    }

    // ==========================================
    // 🌟 ลบ รพ.สต. - ให้เฉพาะ Admin
    // ==========================================
    public function delete() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        $db = (new Database())->getConnection();
        $hospitalModel = new HospitalModel($db);
        
        $id = $_GET['id'] ?? null;
        
        if ($id && $id != 1) { 
            if($hospitalModel->deleteHospital($id)) {
                LogsController::addLog($db, $_SESSION['user']['id'], 'DELETE', "ลบหน่วยบริการ ID: {$id}");
                $_SESSION['success_msg'] = "ลบหน่วยบริการเรียบร้อยแล้ว";
            } else {
                $_SESSION['error_msg'] = "ไม่สามารถลบได้ เนื่องจากมีพนักงานอยู่ในหน่วยบริการนี้";
            }
        } else {
            $_SESSION['error_msg'] = "ไม่อนุญาตให้ลบหน่วยงานส่วนกลางหลักของระบบได้";
        }
        
        header("Location: index.php?c=hospitals");
        exit;
    }

    // ==========================================
    // 🌟 ระบบนำเข้าไฟล์ Excel (CSV)
    // ==========================================
    public function download_template() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=hospital_template.csv');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['รหัสอ้างอิง (ID)', 'รหัสหน่วยบริการ (5 หลัก)', 'ชื่อหน่วยบริการ']);
        fputcsv($output, ['h990', '09990', 'รพ.สต. ตัวอย่างที่ 1']);
        fputcsv($output, ['h991', '09991', 'รพ.สต. ตัวอย่างที่ 2']);
        fclose($output);
        exit;
    }

    public function import_csv() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_csv'])) {
            $file = $_FILES['file_csv'];
            
            if ($file['error'] == UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
                
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                if (strtolower($ext) !== 'csv') {
                    $_SESSION['error_msg'] = "กรุณาอัปโหลดไฟล์นามสกุล .csv เท่านั้น";
                    header("Location: index.php?c=hospitals&a=index");
                    exit;
                }

                $db = (new Database())->getConnection();
                $hospitalModel = new HospitalModel($db);
                
                $handle = fopen($file['tmp_name'], "r");
                
                $bom = fread($handle, 3);
                if ($bom !== b"\xEF\xBB\xBF") {
                    rewind($handle); 
                }

                $row_count = 0;
                $success_count = 0;
                $error_count = 0;

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $row_count++;
                    if ($row_count == 1) continue; 
                    if (empty($data[0]) && empty($data[1]) && empty($data[2])) continue;

                    $id = trim($data[0] ?? '');
                    $code = trim($data[1] ?? '');
                    $name = trim($data[2] ?? '');

                    if (!empty($name)) {
                        if ($hospitalModel->addHospital($name, $code)) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    } else {
                        $error_count++;
                    }
                }
                fclose($handle);

                if ($success_count > 0) {
                    LogsController::addLog($db, $_SESSION['user']['id'], 'CREATE', "นำเข้าข้อมูล รพ.สต. จาก CSV สำเร็จ $success_count แห่ง");
                    $_SESSION['success_msg'] = "นำเข้าข้อมูลสำเร็จ $success_count แห่ง (ล้มเหลว/ข้อมูลไม่ครบ $error_count แห่ง)";
                } else {
                    $_SESSION['error_msg'] = "ไม่สามารถนำเข้าข้อมูลได้ (รูปแบบไฟล์ไม่ถูกต้อง หรือไม่มีข้อมูลใหม่)";
                }

            } else {
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
            }
        }
        header("Location: index.php?c=hospitals&a=index");
        exit;
    }
}
?>