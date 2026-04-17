<?php
// ที่อยู่ไฟล์: controllers/AjaxController.php

require_once 'config/database.php';
require_once 'models/ShiftModel.php';
require_once 'models/NotificationModel.php';
require_once 'models/UserModel.php';
require_once 'models/LeaveModel.php';
require_once 'controllers/LogsController.php'; // 🌟 นำเข้า Log Controller เพื่อบันทึกประวัติการใช้งาน

class AjaxController {

    // ==========================================
    // ⚙️ Helper: ดึงค่า Config จากฐานข้อมูล
    // ==========================================
    private function getSystemSetting($db, $key) {
        try {
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['setting_value'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    // ==========================================
    // 💬 Helper: ฟังก์ชันส่งแจ้งเตือนผ่าน LINE Notify
    // ==========================================
    private function sendLineNotify($db, $message) {
        $line_token = $this->getSystemSetting($db, 'line_notify_token');
        if (empty($line_token)) return false;

        $url = "https://notify-api.line.me/api/notify";
        $data = ['message' => $message];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/x-www-form-urlencoded",
            "Authorization: Bearer " . $line_token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return $result;
    }

    // ==========================================
    // 🛡️ Helper: ตรวจสอบสิทธิ์การจัดการตารางเวร
    // ==========================================
    private function canEditRoster($hospital_id, $month_year) {
        $role = $_SESSION['user']['role'];
        if ($role === 'STAFF' || $role === 'ADMIN') return false;

        $db = (new Database())->getConnection();
        $shiftModel = new ShiftModel($db);
        $status = $shiftModel->getRosterStatus($hospital_id, $month_year);
        
        if ($status === 'SUBMITTED' || $status === 'APPROVED' || $status === 'REQUEST_EDIT') return false;
        return true; 
    }

    // ==========================================
    // 💰 Helper: ฟังก์ชันอ่านเรทราคาแบบไดนามิก (Snapshot)
    // ==========================================
    private function getDynamicPayRates($staff_type, $pay_rates_db) {
        $type = $staff_type ?? '';
        foreach ($pay_rates_db as $group) {
            $keywords = explode(',', $group['keywords']);
            foreach ($keywords as $kw) {
                $kw = trim($kw);
                if (!empty($kw) && mb_strpos($type, $kw) !== false) {
                    return ['ย' => $group['rate_y'], 'บ' => $group['rate_b'], 'ร' => $group['rate_r']];
                }
            }
        }
        $last = end($pay_rates_db);
        if ($last) return ['ย' => $last['rate_y'], 'บ' => $last['rate_b'], 'ร' => $last['rate_r']];
        return ['ย' => 0, 'บ' => 0, 'ร' => 0];
    }

    // ==========================================
    // 🌟 API: ทดสอบ LINE Notify
    // ==========================================
    public function test_line_notify() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['SUPERADMIN', 'ADMIN'])) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
        }

        $data = json_decode(file_get_contents("php://input"));
        $token = $data->token ?? '';

        if(empty($token)) { echo json_encode(['status' => 'error', 'message' => 'Token is empty']); exit; }

        $url = "https://notify-api.line.me/api/notify";
        $message = "🟢 ทดสอบการเชื่อมต่อระบบ Roster Pro\nเวลา: " . date('Y-m-d H:i:s') . "\nหากคุณเห็นข้อความนี้ แสดงว่าระบบพร้อมส่งแจ้งเตือนแล้ว!";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['message' => $message]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded", "Authorization: Bearer " . $token]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'LINE API Returned Code: ' . $http_code]);
        }
        exit;
    }

    // ==========================================
    // 🌟 API: บันทึกเวร (Save Shift)
    // ==========================================
    public function save_shift() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"));
        
        if (!isset($_SESSION['user'])) { echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }

        $db = (new Database())->getConnection();
        $shiftModel = new ShiftModel($db);
        $notifModel = new NotificationModel($db);
        $leaveModel = class_exists('LeaveModel') ? new LeaveModel($db) : null;
        $hospital_id = $_SESSION['user']['hospital_id'];

        if(!empty($data->user_id) && !empty($data->date)) {
            $month_year = substr($data->date, 0, 7);

            if (!$this->canEditRoster($hospital_id, $month_year)) {
                echo json_encode(['status' => 'error', 'message' => '⛔ คุณไม่มีสิทธิ์จัดเวร หรือตารางเดือนนี้ถูกล็อคแล้ว']); exit;
            }
            
            if (!empty($data->shift_type)) {
                $shift_input = trim($data->shift_type);
                $shift_array = preg_split('/[\/\,\s]+/', $shift_input);
                $shift_array = array_filter($shift_array); 

                if (count($shift_array) > 2) {
                    echo json_encode(['status' => 'error', 'message' => '⚠️ จัดเวรไม่ได้: 1 คนขึ้นเวรได้ไม่เกิน 2 กะต่อวัน']); exit;
                }
                if (in_array('ช', $shift_array) && in_array('ด', $shift_array)) {
                    echo json_encode(['status' => 'error', 'message' => '🚨 ผิดกฎพักผ่อน: ห้ามจัดเวร "เช้า" ควบ "ดึก" ในวันเดียวกัน']); exit;
                }

                // ตรวจสอบการลา
                if ($leaveModel) {
                    try {
                        $all_leaves = $leaveModel->getLeavesByHospitalAndMonth($hospital_id, $month_year);
                        $current_ts = strtotime($data->date);
                        foreach ($all_leaves as $leave) {
                            if ($leave['user_id'] == $data->user_id && $leave['status'] == 'APPROVED') {
                                $start_ts = strtotime($leave['start_date']);
                                $end_ts = strtotime($leave['end_date']);
                                if ($current_ts >= $start_ts && $current_ts <= $end_ts) {
                                    echo json_encode(['status' => 'error', 'message' => "⛔ จัดเวรไม่ได้: เจ้าหน้าที่ติด '{$leave['leave_type']}'"]); exit;
                                }
                            }
                        }
                    } catch (Exception $e) {}
                }
            }

            // บันทึก/ลบกะการทำงาน
            try {
                $stmt = $db->prepare("DELETE FROM shifts WHERE user_id = ? AND shift_date = ? AND hospital_id = ?");
                $stmt->execute([$data->user_id, $data->date, $hospital_id]);

                if (!empty($data->shift_type)) {
                    $last_id = $shiftModel->addShift($data->date, $data->shift_type, $data->user_id, $hospital_id);
                    
                    if ($data->user_id != $_SESSION['user']['id']) {
                        $thai_date = date('d/m/Y', strtotime($data->date));
                        $notifModel->addNotification($data->user_id, 'INFO', 'ตารางเวรอัปเดต', "คุณถูกจัดเวร '{$data->shift_type}' ในวันที่ {$thai_date}", "index.php?c=profile&a=schedule");
                    }
                    
                    // 🌟 บันทึกประวัติ (System Log)
                    LogController::addLog($db, $_SESSION['user']['id'], 'UPDATE', "จัดเวร '{$data->shift_type}' ให้ผู้ใช้ ID:{$data->user_id} วันที่ {$data->date}");
                    
                    echo json_encode(['status' => 'success', 'shift_id' => $last_id]);
                } else {
                    // 🌟 บันทึกประวัติ (System Log)
                    LogController::addLog($db, $_SESSION['user']['id'], 'DELETE', "ลบเวรของผู้ใช้ ID:{$data->user_id} ในวันที่ {$data->date}");
                    
                    echo json_encode(['status' => 'success', 'message' => 'Deleted']);
                }
                exit;
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit;
            }
        }
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    }

    // ==========================================
    // 🌟 API: อัปเดตลำดับรายชื่อ (Drag & Drop)
    // ==========================================
    public function update_order() {
        header('Content-Type: application/json');
        
        // อนุญาตเฉพาะ POST Request และต้องล็อกอิน
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user'])) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }

        // รับข้อมูล JSON จาก Javascript Fetch API
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        if (isset($data['order']) && is_array($data['order'])) {
            $db = (new Database())->getConnection();
            
            try {
                $db->beginTransaction();
                
                // 🌟 แก้ไขบัค: อัปเดตตาม ID ผู้ใช้งานโดยตรง ไม่ต้องเช็ค hospital_id ของ Session 
                // เพื่อให้ ADMIN ส่วนกลางสามารถลากสลับชื่อให้ รพ.สต. อื่นได้
                $stmt = $db->prepare("UPDATE users SET display_order = ? WHERE id = ?");
                
                foreach ($data['order'] as $item) {
                    if (isset($item['id']) && isset($item['order'])) {
                        // บวก 1 เพื่อให้ลำดับใน Database เริ่มที่ 1
                        $display_order = (int)$item['order'] + 1;
                        $stmt->execute([$display_order, $item['id']]);
                    }
                }
                
                $db->commit();
                echo json_encode(['status' => 'success', 'message' => 'บันทึกลำดับเรียบร้อยแล้ว']);
            } catch (PDOException $e) {
                $db->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
        }
        exit;
    }

    // ==========================================
    // 🌟 API: คัดลอกตารางจากเดือนก่อน (Copy Previous Month)
    // ==========================================
    public function copy_roster_previous() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user'])) { echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }

        $data = json_decode(file_get_contents("php://input"));
        $target_month = $data->target_month ?? ''; // e.g., '2024-05'
        
        if(empty($target_month)) { echo json_encode(['status' => 'error', 'message' => 'ไม่มีข้อมูลเดือน']); exit; }

        $hospital_id = $_SESSION['user']['hospital_id'];
        
        if (!$this->canEditRoster($hospital_id, $target_month)) {
            echo json_encode(['status' => 'error', 'message' => '⛔ คุณไม่มีสิทธิ์จัดการ หรือตารางเดือนนี้ถูกล็อคแล้ว']); exit;
        }

        $db = (new Database())->getConnection();
        
        // หาวันที่ของเดือนก่อนหน้า
        $prev_month = date('Y-m', strtotime($target_month . '-01 -1 month'));
        
        try {
            // ล้างข้อมูลเดือนปัจจุบันก่อน
            $start_curr = $target_month . '-01';
            $end_curr = date('Y-m-t', strtotime($start_curr));
            $stmt_del = $db->prepare("DELETE FROM shifts WHERE hospital_id = ? AND shift_date BETWEEN ? AND ?");
            $stmt_del->execute([$hospital_id, $start_curr, $end_curr]);

            // ดึงข้อมูลเดือนก่อน
            $start_prev = $prev_month . '-01';
            $end_prev = date('Y-m-t', strtotime($start_prev));
            $stmt_get = $db->prepare("SELECT user_id, shift_date, shift_type FROM shifts WHERE hospital_id = ? AND shift_date BETWEEN ? AND ?");
            $stmt_get->execute([$hospital_id, $start_prev, $end_prev]);
            $prev_shifts = $stmt_get->fetchAll(PDO::FETCH_ASSOC);

            if(empty($prev_shifts)) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีข้อมูลตารางเวรในเดือนก่อนหน้า']); exit;
            }

            // นำข้อมูลเดือนก่อน มายัดใส่เดือนปัจจุบัน (จับคู่วันที่ 1 ต่อ 1)
            $stmt_in = $db->prepare("INSERT INTO shifts (user_id, hospital_id, shift_date, shift_type) VALUES (?, ?, ?, ?)");
            $days_in_curr = (int)date('t', strtotime($start_curr));

            foreach ($prev_shifts as $ps) {
                $day_num = (int)date('d', strtotime($ps['shift_date']));
                if ($day_num <= $days_in_curr) {
                    $new_date = $target_month . '-' . str_pad($day_num, 2, '0', STR_PAD_LEFT);
                    $stmt_in->execute([$ps['user_id'], $hospital_id, $new_date, $ps['shift_type']]);
                }
            }

            // 🌟 บันทึกประวัติ (System Log)
            LogController::addLog($db, $_SESSION['user']['id'], 'CREATE', "คัดลอกเวรจากเดือน {$prev_month} ไปยังเดือน {$target_month}");

            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ==========================================
    // 🌟 API: ขอแลกเวร/เปลี่ยนเวร (Shift Swap Request)
    // ==========================================
    public function request_swap() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user'])) {
            header("Location: index.php?c=roster"); exit;
        }

        $db = (new Database())->getConnection();
        $notifModel = new NotificationModel($db);

        $my_shift_id = $_POST['my_shift_id'];
        $target_user_id = $_POST['target_user_id'];
        $target_date = $_POST['target_date'];
        $reason = $_POST['reason'];
        $month_year = $_POST['month_year'] ?? date('Y-m');
        $hospital_id = $_SESSION['user']['hospital_id'];
        $my_name = $_SESSION['user']['name'];

        // เนื่องจากไม่ได้มีการออกแบบตาราง shift_swaps ตรงๆ ให้ยิงเป็น Notif ให้ผู้จัดเวร/ผอ. ทราบ
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE hospital_id = ? AND role IN ('SCHEDULER', 'DIRECTOR')");
            $stmt->execute([$hospital_id]);
            $approvers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $msg = "{$my_name} ขอแลกเวรกับเจ้าหน้าที่ ID: {$target_user_id} ในวันที่ {$target_date} เหตุผล: {$reason}";
            $link = "index.php?c=roster&a=index&month={$month_year}";

            foreach ($approvers as $a) {
                $notifModel->addNotification($a['id'], 'WARNING', 'คำขอแลกเปลี่ยนเวรใหม่', $msg, $link);
            }

            // แจ้งเตือนเพื่อนที่เราจะขอแลกด้วย
            $notifModel->addNotification($target_user_id, 'INFO', 'มีเพื่อนขอแลกเวรด้วย', "{$my_name} เสนอขอแลกเวรกับคุณในวันที่ {$target_date} กรุณาตกลงกับผู้จัดเวร", $link);

            // 🌟 บันทึกประวัติ (System Log)
            LogController::addLog($db, $_SESSION['user']['id'], 'CREATE', "ส่งคำขอแลกเวร (Shift ID: {$my_shift_id}) กับ User ID: {$target_user_id}");

            $_SESSION['success_msg'] = "ส่งคำขอแลกเวรเรียบร้อยแล้ว กรุณารอการพิจารณาจากผู้จัดเวรหรือผู้อำนวยการ";

        } catch (Exception $e) {
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการส่งคำขอ";
        }

        header("Location: index.php?c=roster&a=index&month={$month_year}");
        exit;
    }

    // ==========================================
    // 🌟 เปลี่ยนสถานะตารางเวร (Workflow)
    // ==========================================
    public function change_status() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user'])) {
            header("Location: index.php?c=roster"); exit;
        }

        $db = (new Database())->getConnection();
        $shiftModel = new ShiftModel($db);
        $notifModel = new NotificationModel($db);

        $month_year = $_POST['month_year'];
        $new_status = $_POST['status']; 
        $hospital_id = $_POST['hospital_id'] ?? $_SESSION['user']['hospital_id'];

        $stmt_hosp = $db->prepare("SELECT name FROM hospitals WHERE id = ?");
        $stmt_hosp->execute([$hospital_id]);
        $hospital_name = $stmt_hosp->fetch(PDO::FETCH_ASSOC)['name'] ?? 'รพ.สต.';

        try {
            $shiftModel->updateRosterStatus($hospital_id, $month_year, $new_status);

            // 🌟 บันทึกประวัติ (System Log)
            LogController::addLog($db, $_SESSION['user']['id'], 'APPROVE', "เปลี่ยนสถานะตารางเวร รพ.สต. {$hospital_name} เดือน {$month_year} เป็น {$new_status}");

            // 🛡️ SNAPSHOT SYSTEM (บันทึกยอดเงินเมื่ออนุมัติ)
            if ($new_status === 'APPROVED') {
                require_once 'models/PayRateModel.php';
                $payRateModel = new PayRateModel($db);
                $userModel = new UserModel($db);
                
                $rates_db = $payRateModel->getAllRates();
                $staffs = $userModel->getAllStaff();
                
                $start_date = $month_year . '-01';
                $end_date = date('Y-m-t', strtotime($start_date));
                $shifts = $shiftModel->getShiftsByWeek($hospital_id, $start_date, $end_date);
                
                $snapshot_data = [];
                foreach ($staffs as $staff) {
                    $is_external = ($staff['hospital_id'] != $hospital_id);
                    $has_shift = false;
                    $sum_r = 0; $sum_y = 0; $sum_b = 0;
                    
                    foreach ($shifts as $s) {
                        if ($s['user_id'] == $staff['id']) {
                            $has_shift = true;
                            $val = $s['shift_type'];
                            if($val === 'ร') $sum_r++;
                            elseif($val === 'ย') $sum_y++;
                            elseif($val === 'บ') $sum_b++;
                            elseif($val === 'บ/ร' || $val === 'ร/บ') { $sum_b++; $sum_r++; }
                            elseif($val === 'ย/บ' || $val === 'บ/ย') { $sum_y++; $sum_b++; }
                        }
                    }
                    if ($is_external && !$has_shift) continue;
                    
                    $rates = $this->getDynamicPayRates($staff['type'], $rates_db);
                    $pay = ($sum_r * $rates['ร']) + ($sum_y * $rates['ย']) + ($sum_b * $rates['บ']);
                    $snapshot_data[$staff['id']] = ['pay' => $pay];
                }
                
                $json_snapshot = json_encode($snapshot_data, JSON_UNESCAPED_UNICODE);
                $stmt_snap = $db->prepare("UPDATE roster_status SET pay_summary = ? WHERE hospital_id = ? AND month_year = ?");
                $stmt_snap->execute([$json_snapshot, $hospital_id, $month_year]);
                
            } elseif ($new_status === 'DRAFT' || $new_status === 'REQUEST_EDIT') {
                $stmt_snap = $db->prepare("UPDATE roster_status SET pay_summary = NULL WHERE hospital_id = ? AND month_year = ?");
                $stmt_snap->execute([$hospital_id, $month_year]);
            }

            // แจ้งเตือนกระดิ่ง และ LINE
            $thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            $m = (int)substr($month_year, 5, 2);
            $month_name = $thai_months[$m] . " " . (substr($month_year, 0, 4) + 543);
            $target_link = "index.php?c=roster&a=index&month={$month_year}";

            if ($new_status === 'SUBMITTED') {
                $msg = "{$hospital_name} ส่งตารางเวรเดือน {$month_name} มาให้พิจารณาอนุมัติ";
                $this->notifyRole($hospital_id, 'DIRECTOR', 'INFO', 'มีตารางเวรรออนุมัติ', $msg, $target_link);
                $_SESSION['success_msg'] = "ส่งตารางเวรขอพิจารณาอนุมัติสำเร็จ";
                
                if ($this->getSystemSetting($db, 'line_notify_on_submit') === '1') {
                    $this->sendLineNotify($db, "\n📝 มีตารางเวรส่งมาใหม่\nหน่วยบริการ: {$hospital_name}\nเดือน: {$month_name}\nโปรดเข้าสู่ระบบเพื่อตรวจสอบครับ");
                }
                
            } elseif ($new_status === 'DRAFT') {
                if (in_array($_SESSION['user']['role'], ['ADMIN', 'SUPERADMIN'])) {
                    $msg = "ส่วนกลางอนุมัติคำขอแก้ไขตารางเวรเดือน {$month_name} แล้ว";
                    $this->notifyRole($hospital_id, 'SCHEDULER', 'SUCCESS', 'คำขอแก้ไขได้รับการอนุมัติ', $msg, $target_link);
                    $_SESSION['success_msg'] = "อนุมัติให้ {$hospital_name} แก้ไขตารางเวรเรียบร้อยแล้ว";
                } else {
                    $msg = "ตารางเวรเดือน {$month_name} ถูกส่งกลับให้ตรวจสอบและแก้ไขใหม่";
                    $this->notifyRole($hospital_id, 'SCHEDULER', 'ALERT', 'ตารางเวรถูกส่งกลับแก้ไข', $msg, $target_link);
                    $_SESSION['success_msg'] = "ตีกลับให้ผู้จัดเวรแก้ไขเรียบร้อยแล้ว";
                }
            } elseif ($new_status === 'APPROVED') {
                $msg = "ตารางเวรเดือน {$month_name} ได้รับการอนุมัติเรียบร้อยแล้ว";
                $this->notifyRole($hospital_id, 'SCHEDULER', 'SUCCESS', 'อนุมัติตารางเวรแล้ว', $msg, $target_link);
                $this->notifyRole($hospital_id, 'STAFF', 'SUCCESS', 'ประกาศตารางเวรใหม่', $msg, "index.php?c=profile&a=schedule");
                $_SESSION['success_msg'] = "อนุมัติตารางเวรเดือน {$month_name} เรียบร้อยแล้ว";
            }
        } catch (Exception $e) {
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }

        $redirect = (in_array($_SESSION['user']['role'], ['ADMIN', 'SUPERADMIN'])) ? "index.php?c=report&a=overview&month=".$month_year : "index.php?c=roster&month=".$month_year;
        header("Location: " . $redirect);
    }

    // ==========================================
    // 🌟 ขอแก้ไขตาราง (Request Edit)
    // ==========================================
    public function request_edit() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user'])) { header("Location: index.php?c=roster"); exit; }

        $month_year = $_POST['month_year'];
        $hospital_id = $_SESSION['user']['hospital_id'];
        
        $db = (new Database())->getConnection();
        $stmt_hosp = $db->prepare("SELECT name FROM hospitals WHERE id = ?");
        $stmt_hosp->execute([$hospital_id]);
        $hospital_name = $stmt_hosp->fetch(PDO::FETCH_ASSOC)['name'] ?? 'รพ.สต.';

        $shiftModel = new ShiftModel($db);
        $notifModel = new NotificationModel($db);

        try {
            $shiftModel->updateRosterStatus($hospital_id, $month_year, 'REQUEST_EDIT');
            
            // 🌟 บันทึกประวัติ (System Log)
            LogController::addLog($db, $_SESSION['user']['id'], 'UPDATE', "ส่งคำขอแก้ไขตารางเวรที่อนุมัติแล้ว เดือน {$month_year}");

            $stmt = $db->query("SELECT id FROM users WHERE role IN ('ADMIN', 'SUPERADMIN')");
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            $m = (int)substr($month_year, 5, 2);
            $month_text = $thai_months[$m] . " " . (substr($month_year, 0, 4) + 543);
            $link = "index.php?c=report&a=overview&month={$month_year}";

            foreach ($admins as $admin) {
                $notifModel->addNotification($admin['id'], 'WARNING', "คำขอแก้ไขเวร: {$hospital_name}", "ขอยกเลิกสถานะอนุมัติเพื่อแก้ไขตารางเดือน {$month_text}", $link);
            }
            $_SESSION['success_msg'] = "ส่งคำขอแก้ไขตารางเวรไปยังส่วนกลางแล้ว กรุณารอการปลดล็อค";

            if ($this->getSystemSetting($db, 'line_notify_on_request') === '1') {
                $this->sendLineNotify($db, "\n🔓 มีคำขอปลดล็อคตารางเวร\nหน่วยบริการ: {$hospital_name}\nเดือน: {$month_text}\nโปรดเข้าสู่ระบบเพื่อพิจารณาอนุมัติครับ");
            }

        } catch (Exception $e) {}

        header("Location: index.php?c=roster&month=" . $month_year);
    }

    // ==========================================
    // 🌟 เสนอเพิ่มวันหยุดใหม่ (Request Holiday)
    // ==========================================
    public function request_holiday() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"));
        
        if (!isset($_SESSION['user'])) { echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }

        $db = (new Database())->getConnection();
        require_once 'models/HolidayModel.php';
        $holidayModel = new HolidayModel($db);
        $notifModel = new NotificationModel($db);

        $hospital_id = $_SESSION['user']['hospital_id'];
        
        if(!empty($data->date) && !empty($data->name)) {
            try {
                $result = $holidayModel->requestHoliday($data->date, $data->name, $hospital_id);
                if ($result === "SUCCESS") {
                    
                    // 🌟 บันทึกประวัติ (System Log)
                    LogController::addLog($db, $_SESSION['user']['id'], 'CREATE', "เสนอวันหยุดใหม่: {$data->name} ({$data->date})");

                    $stmt = $db->query("SELECT id FROM users WHERE role IN ('ADMIN', 'SUPERADMIN')");
                    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $thai_date = date('d/m/Y', strtotime($data->date));
                    foreach ($admins as $admin) {
                        $notifModel->addNotification($admin['id'], 'WARNING', "คำขอเพิ่มวันหยุด", "มีเสนอเพิ่มวันหยุด '{$data->name}' ในวันที่ {$thai_date}", "index.php?c=settings&a=holidays");
                    }
                    
                    if ($this->getSystemSetting($db, 'line_notify_on_holiday') === '1') {
                        $stmt_hosp = $db->prepare("SELECT name FROM hospitals WHERE id = ?");
                        $stmt_hosp->execute([$hospital_id]);
                        $hosp_name = $stmt_hosp->fetch(PDO::FETCH_ASSOC)['name'] ?? '';
                        $this->sendLineNotify($db, "\n🗓️ เสนอวันหยุดใหม่\nหน่วยบริการ: {$hosp_name}\nวันหยุด: {$data->name}\nวันที่: {$thai_date}");
                    }

                    echo json_encode(['status' => 'success']);
                } else if ($result === "EXISTS") {
                    echo json_encode(['status' => 'error', 'message' => 'วันที่นี้เป็นวันหยุดในระบบอยู่แล้ว']);
                } else if ($result === "PENDING") {
                    echo json_encode(['status' => 'error', 'message' => 'มีการเสนอวันหยุดนี้ไปแล้ว อยู่ระหว่างรออนุมัติ']);
                }
            } catch (Exception $e) { echo json_encode(['status' => 'error', 'message' => 'DB Error']); }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
        }
        exit;
    }

    // ==========================================
    // 🔔 API แจ้งเตือนต่างๆ
    // ==========================================
    private function notifyRole($hosp_id, $role, $type, $title, $msg, $link = null) {
        $db = (new Database())->getConnection();
        $notif = new NotificationModel($db);
        $stmt = $db->prepare("SELECT id FROM users WHERE hospital_id = ? AND role = ?");
        $stmt->execute([$hosp_id, $role]);
        while($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notif->addNotification($u['id'], $type, $title, $msg, $link);
        }
    }

    public function read_notif() {
        if (!isset($_SESSION['user']) || !isset($_GET['id'])) exit;
        $notif = new NotificationModel((new Database())->getConnection());
        $notif->markAsRead($_GET['id'], $_SESSION['user']['id']);
        echo json_encode(['status' => 'success']);
    }
    
    public function read_all_notif() {
        if (!isset($_SESSION['user'])) exit;
        $notif = new NotificationModel((new Database())->getConnection());
        $notif->markAllAsRead($_SESSION['user']['id']);
        echo json_encode(['status' => 'success']);
    }
    
    public function check_new_notif() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user'])) { echo json_encode(['status' => 'error', 'unread_count' => 0]); exit; }
        $count = (new NotificationModel((new Database())->getConnection()))->getUnreadCount($_SESSION['user']['id']);
        echo json_encode(['status' => 'success', 'unread_count' => $count]);
    }
    
    public function delete_notif() {
        if (!isset($_SESSION['user']) || !isset($_GET['id'])) exit;
        $notif = new NotificationModel((new Database())->getConnection());
        $notif->deleteNotification($_GET['id'], $_SESSION['user']['id']);
        echo json_encode(['status' => 'success']);
    }
    
    public function delete_all_notif() {
        if (!isset($_SESSION['user'])) exit;
        $notif = new NotificationModel((new Database())->getConnection());
        $notif->deleteAllNotifications($_SESSION['user']['id']);
        echo json_encode(['status' => 'success']);
    }
}
?>