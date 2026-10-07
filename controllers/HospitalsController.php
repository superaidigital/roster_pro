<?php
// ที่อยู่ไฟล์: controllers/HospitalsController.php

require_once 'config/database.php';
require_once 'models/HospitalModel.php';
require_once 'controllers/LogsController.php';

class HospitalsController {
    
    // ==========================================
    // 🛡️ ฟังก์ชันตรวจสอบสิทธิ์ (Authorization)
    // ==========================================
    private function requireAccess($allowed_roles = []) {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        
        // ถ้ายังไม่ได้ล็อกอิน ให้เด้งไปหน้าล็อกอิน
        if (!isset($_SESSION['user'])) { 
            header("Location: index.php?c=auth&a=index"); 
            exit; 
        }
        
        // ถ้ามีการจำกัดสิทธิ์ และบทบาทของผู้ใช้ไม่ได้อยู่ในรายการที่อนุญาต ให้เด้งกลับ
        if (!empty($allowed_roles) && !in_array(strtoupper($_SESSION['user']['role']), $allowed_roles)) {
            $_SESSION['error_msg'] = "ปฏิเสธการเข้าถึง: คุณไม่มีสิทธิ์ใช้งานเมนูนี้";
            header("Location: index.php?c=dashboard"); 
            exit;
        }
    }

    // ==========================================
    // 🌟 1. โหลดหน้าตารางรายชื่อ รพ.สต. ทั้งหมด
    // ==========================================
    public function index() {
        // อนุญาตให้ ผอ. (DIRECTOR) เข้ามาดูหน้ารวมได้ด้วย นอกเหนือจาก ADMIN/SUPERADMIN
        $this->requireAccess(['SUPERADMIN', 'ADMIN', 'DIRECTOR', 'HR']);
        
        $db = (new Database())->getConnection();
        $hospitalModel = new HospitalModel($db);
        
        $hospitals_list = $hospitalModel->getAllHospitals();

        require_once 'views/layouts/header.php';
        require_once 'views/layouts/sidebar.php';
        require_once 'views/hospitals/index.php';
        echo "</main></div></body></html>";
    }

    // ==========================================
    // 🌟 2. เพิ่ม รพ.สต. ใหม่ (Add)
    // ==========================================
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?c=hospitals"); exit;
        }
        
        // สงวนสิทธิ์การเพิ่มให้เฉพาะ ADMIN ขึ้นไป
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);

        $db = (new Database())->getConnection();
        $hospitalModel = new HospitalModel($db);

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'hospital_code' => trim($_POST['hospital_code'] ?? ''),
            'hospital_code9' => trim($_POST['hospital_code9'] ?? ''),
            'short_name' => trim($_POST['short_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? '')
        ];

        if (!preg_match('/^\d{5}$/', $data['hospital_code'])) {
            $_SESSION['error_msg'] = "รหัสหน่วยบริการต้องเป็นตัวเลข 5 หลัก";
            header("Location: index.php?c=hospitals");
            exit;
        }
        if (!preg_match('/^\d{9}$/', $data['hospital_code9'])) {
            $_SESSION['error_msg'] = "HOSPCODE9 ต้องเป็นตัวเลข 9 หลัก";
            header("Location: index.php?c=hospitals");
            exit;
        }

        if ($hospitalModel->checkNameExists($data['name'])) {
            $_SESSION['error_msg'] = "ชื่อหน่วยบริการนี้มีอยู่ในระบบแล้ว";
        } else {
            // ใช้ฟังก์ชันที่รองรับการรับแบบ Array ของเรา
            if ($hospitalModel->addHospital($data)) {
                // 🌟 บันทึก Log
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_CREATE, "เพิ่มหน่วยบริการใหม่: " . $data['name']);
                $_SESSION['success_msg'] = "เพิ่มข้อมูลหน่วยบริการสำเร็จ";
            } else {
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
            }
        }

        header("Location: index.php?c=hospitals");
        exit;
    }

    // ==========================================
    // 🌟 3. แก้ไข รพ.สต. (Edit)
    // ==========================================
    public function edit() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?c=hospitals"); exit;
        }
        
        // อนุญาตให้ ผอ. ใช้งานฟังก์ชันแก้ไขได้ด้วย
        $this->requireAccess(['SUPERADMIN', 'ADMIN', 'DIRECTOR']);

        $db = (new Database())->getConnection();
        $hospitalModel = new HospitalModel($db);

        $id = $_POST['id'] ?? null;
        
        // 🛡️ ดักจับความปลอดภัย: ผอ. แก้ไขได้เฉพาะ รพ.สต. ของตัวเองเท่านั้น
        if (strtoupper($_SESSION['user']['role']) === 'DIRECTOR' && $id != $_SESSION['user']['hospital_id']) {
            $_SESSION['error_msg'] = "ปฏิเสธการเข้าถึง: คุณไม่มีสิทธิ์แก้ไขข้อมูลหน่วยบริการของที่อื่นได้";
            header("Location: index.php?c=hospitals");
            exit;
        }

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'hospital_code' => trim($_POST['hospital_code'] ?? ''),
            'hospital_code9' => trim($_POST['hospital_code9'] ?? ''),
            'short_name' => trim($_POST['short_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? '')
        ];

        if (!preg_match('/^\d{5}$/', $data['hospital_code'])) {
            $_SESSION['error_msg'] = "รหัสหน่วยบริการต้องเป็นตัวเลข 5 หลัก";
            header("Location: index.php?c=hospitals");
            exit;
        }
        if (!preg_match('/^\d{9}$/', $data['hospital_code9'])) {
            $_SESSION['error_msg'] = "HOSPCODE9 ต้องเป็นตัวเลข 9 หลัก";
            header("Location: index.php?c=hospitals");
            exit;
        }

        if (!empty($id)) {
            if ($hospitalModel->checkNameExists($data['name'], $id)) {
                $_SESSION['error_msg'] = "ชื่อหน่วยบริการนี้ถูกใช้งานโดย รพ.สต. อื่นแล้ว";
            } else {
                if ($hospitalModel->updateHospital($id, $data)) {
                    // 🌟 บันทึก Log
                    LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "แก้ไขข้อมูล รพ.สต. ID: {$id} เป็น ({$data['name']})");
                    $_SESSION['success_msg'] = "แก้ไขข้อมูลหน่วยบริการสำเร็จ";
                } else {
                    $_SESSION['error_msg'] = "ไม่สามารถอัปเดตข้อมูลได้";
                }
            }
        }

        header("Location: index.php?c=hospitals");
        exit;
    }

    // ==========================================
    // 🌟 4. ลบ รพ.สต. แบบ Soft Delete (ลบเดี่ยว)
    // ==========================================
    public function delete() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        $db = (new Database())->getConnection();
        $hospitalModel = new HospitalModel($db);
        
        $id = $_GET['id'] ?? null;
        
        if ($id && $id != 0 && $id != '0') { 
            // ดึงชื่อ รพ.สต. มาเก็บไว้บันทึก Log ให้ชัดเจน
            $stmt = $db->prepare("SELECT name FROM hospitals WHERE id = ?");
            $stmt->execute([$id]);
            $hosp_name = $stmt->fetchColumn() ?: "ID: $id";

            if ($hospitalModel->deleteHospital($id)) {
                // 🌟 บันทึก Log
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_DELETE, "ลบหน่วยบริการ: {$hosp_name} (Soft Delete)");
                $_SESSION['success_msg'] = "ลบหน่วยบริการเรียบร้อยแล้ว (ข้อมูลถูกซ่อนจากระบบหลัก)";
            } else {
                // แจ้งเตือนสาเหตุที่ลบไม่ได้ เช่น มีบุคลากรผูกอยู่
                $_SESSION['error_msg'] = "ไม่สามารถลบได้! เนื่องจากยังมีรายชื่อพนักงานหรือข้อมูลอื่นๆ ผูกอยู่กับหน่วยบริการนี้";
            }
        } else {
            $_SESSION['error_msg'] = "ไม่อนุญาตให้ลบหน่วยงาน 'ส่วนกลาง' หลักของระบบได้";
        }
        
        header("Location: index.php?c=hospitals");
        exit;
    }

    // ==========================================
    // 🌟 5. ลบหลายรายการ (Bulk Delete)
    // ==========================================
    public function bulk_delete() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = (new Database())->getConnection();
            $hospitalModel = new HospitalModel($db);
            
            $ids = json_decode($_POST['ids'] ?? '[]');
            
            if (is_array($ids) && count($ids) > 0) {
                $successCount = 0;
                $failCount = 0;
                
                foreach ($ids as $id) {
                    if ($id != 0 && $id != '0') { // ข้ามส่วนกลาง
                        if ($hospitalModel->deleteHospital($id)) { 
                            $successCount++;
                        } else {
                            $failCount++;
                        }
                    }
                }
                
                if ($successCount > 0) {
                    $failMsg = $failCount > 0 ? " (และไม่สามารถลบได้ {$failCount} แห่งเนื่องจากยังมีพนักงานสังกัดอยู่)" : "";
                    $_SESSION['success_msg'] = "ลบข้อมูลสำเร็จจำนวน {$successCount} รายการ" . $failMsg;
                    // 🌟 บันทึก Log
                    LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_DELETE, "ลบหน่วยบริการหลายรายการ (จำนวน {$successCount})");
                } else {
                    $_SESSION['error_msg'] = "ไม่สามารถลบข้อมูลได้เลย เนื่องจากหน่วยบริการที่เลือกยังมีพนักงานสังกัดอยู่";
                }
            }
            
            header("Location: index.php?c=hospitals");
            exit();
        }
    }

    // ==========================================
    // 🌟 6. เปิด/ปิด การใช้งานหน่วยบริการ (Toggle)
    // ==========================================
    public function toggle() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        
        if (isset($_GET['id']) && isset($_GET['status'])) {
            $id = $_GET['id'];
            $status = (int)$_GET['status'];
            
            if ($id != 0 && $id != '0') { // ป้องกันปิดส่วนกลาง
                $db = (new Database())->getConnection();
                $hospitalModel = new HospitalModel($db);
                
                if ($hospitalModel->updateStatus($id, $status)) {
                    $actionTxt = $status === 1 ? "เปิด" : "ปิด";
                    
                    // ดึงชื่อ รพ.สต.
                    $stmt = $db->prepare("SELECT name FROM hospitals WHERE id = ?");
                    $stmt->execute([$id]);
                    $hosp_name = $stmt->fetchColumn() ?: "ID: $id";

                    // 🌟 บันทึก Log
                    LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "{$actionTxt}การใช้งานหน่วยบริการ: {$hosp_name}");
                    $_SESSION['success_msg'] = "เปลี่ยนสถานะการใช้งานสำเร็จ";
                }
            } else {
                $_SESSION['error_msg'] = "ไม่สามารถระงับการใช้งานส่วนกลางได้";
            }
        }
        
        header("Location: index.php?c=hospitals");
        exit;
    }

    // ==========================================
    // 🌟 7. อัปเดตลำดับจากการลากวางตาราง (Drag & Drop)
    // ==========================================
    public function update_order() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);
        header('Content-Type: application/json');
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (isset($data['order']) && is_array($data['order'])) {
            $db = (new Database())->getConnection();
            try {
                $db->beginTransaction();
                $hospitalModel = new HospitalModel($db);
                
                foreach ($data['order'] as $item) {
                    $hospitalModel->updateOrder($item['id'], $item['order']);
                }
                
                // 🌟 บันทึก Log: อัปเดตลำดับพนักงาน
                $count = count($data['order']);
                LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_UPDATE, "จัดลำดับการแสดงผลหน่วยบริการใหม่ จำนวน {$count} รายการ");

                $db->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'รูปแบบข้อมูลไม่ถูกต้อง']);
        }
        exit;
    }

    // ==========================================
    // 🌟 8. ระบบนำเข้าไฟล์ Excel (CSV)
    // ==========================================
    public function download_template() {
        $this->requireAccess(['SUPERADMIN', 'ADMIN']);

        // 🌟 บันทึก Log
        $db = (new Database())->getConnection();
        LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_EXPORT, "ดาวน์โหลดไฟล์แม่แบบนำเข้าหน่วยบริการ (CSV)");

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=hospital_template.csv');

        $output = fopen('php://output', 'w');
        // ใส่ BOM สำหรับให้ Excel รองรับภาษาไทยสมบูรณ์
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
                    header("Location: index.php?c=hospitals");
                    exit;
                }

                $db = (new Database())->getConnection();
                $hospitalModel = new HospitalModel($db);
                
                $handle = fopen($file['tmp_name'], "r");
                
                // ตรวจสอบและข้าม BOM ถ้ามี
                $bom = fread($handle, 3);
                if ($bom !== b"\xEF\xBB\xBF") {
                    rewind($handle); 
                }

                $row_count = 0;
                $success_count = 0;
                $error_count = 0;

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $row_count++;
                    if ($row_count == 1) continue; // ข้ามแถว Header
                    
                    if (empty($data[0]) && empty($data[1]) && empty($data[2])) continue;

                    $id = trim($data[0] ?? '');
                    $code = trim($data[1] ?? '');
                    $name = trim($data[2] ?? '');

                    if (!empty($name)) {
                        // ป้องกันชื่อซ้ำก่อนนำเข้า
                        if (!$hospitalModel->checkNameExists($name)) {
                            // เรียกใช้ addHospital แบบ Parameter แยก (รองรับโครงสร้างแบบเก่าของไฟล์ CSV)
                            if ($hospitalModel->addHospital($name, $code)) {
                                $success_count++;
                            } else {
                                $error_count++;
                            }
                        } else {
                            $error_count++; // ข้ามถ้าชื่อซ้ำ
                        }
                    } else {
                        $error_count++;
                    }
                }
                fclose($handle);

                if ($success_count > 0) {
                    // 🌟 บันทึก Log: นำเข้าไฟล์ CSV
                    LogsController::addLog($db, $_SESSION['user']['id'], LogsController::ACTION_IMPORT, "นำเข้าข้อมูลหน่วยบริการ รพ.สต. จากไฟล์ CSV สำเร็จ $success_count แห่ง");
                    $_SESSION['success_msg'] = "นำเข้าข้อมูลสำเร็จ $success_count แห่ง (ล้มเหลว/ซ้ำ/ข้อมูลไม่ครบ $error_count แห่ง)";
                } else {
                    $_SESSION['error_msg'] = "ไม่สามารถนำเข้าข้อมูลได้ (อาจจะไม่มีข้อมูลใหม่เลย หรือชื่อซ้ำทั้งหมด)";
                }

            } else {
                $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
            }
        }
        
        header("Location: index.php?c=hospitals");
        exit;
    }
}
?>