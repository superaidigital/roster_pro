<?php
// ที่อยู่ไฟล์: models/ProfileModel.php

class ProfileModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // ==============================================================================
    // 1. ตาราง: employee_profiles (ข้อมูลส่วนตัวและที่อยู่)
    // ==============================================================================
    
    // ดึงข้อมูลส่วนตัว (ดึงชื่อ/นามสกุลจากตาราง users มาประกอบด้วย)
    public function getProfileByUserId($user_id) {
        $query = "SELECT p.*, u.name as system_name, u.phone as system_phone, u.id_card as system_id_card 
                  FROM employee_profiles p 
                  RIGHT JOIN users u ON p.user_id = u.id 
                  WHERE u.id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // บันทึก/อัปเดต ข้อมูลส่วนตัว
    public function saveProfile($data) {
        $checkStmt = $this->conn->prepare("SELECT user_id FROM employee_profiles WHERE user_id = :user_id");
        $checkStmt->execute([':user_id' => $data['user_id']]);
        
        if ($checkStmt->fetch()) {
            $query = "UPDATE employee_profiles SET 
                        title_name = :title_name, first_name_th = :first_name_th, last_name_th = :last_name_th,
                        first_name_en = :first_name_en, last_name_en = :last_name_en, gender = :gender,
                        birth_date = :birth_date, blood_group = :blood_group, marital_status = :marital_status,
                        nationality = :nationality, religion = :religion, address_permanent = :address_permanent,
                        address_current = :address_current, emergency_contact_name = :emergency_contact_name,
                        emergency_contact_relation = :emergency_contact_relation, emergency_contact_phone = :emergency_contact_phone,
                        bank_name = :bank_name, bank_branch = :bank_branch, bank_account_no = :bank_account_no
                      WHERE user_id = :user_id";
        } else {
            $query = "INSERT INTO employee_profiles 
                        (user_id, title_name, first_name_th, last_name_th, first_name_en, last_name_en, gender, birth_date, 
                         blood_group, marital_status, nationality, religion, address_permanent, address_current, 
                         emergency_contact_name, emergency_contact_relation, emergency_contact_phone, bank_name, bank_branch, bank_account_no) 
                      VALUES 
                        (:user_id, :title_name, :first_name_th, :last_name_th, :first_name_en, :last_name_en, :gender, :birth_date, 
                         :blood_group, :marital_status, :nationality, :religion, :address_permanent, :address_current, 
                         :emergency_contact_name, :emergency_contact_relation, :emergency_contact_phone, :bank_name, :bank_branch, :bank_account_no)";
        }
        
        $stmt = $this->conn->prepare($query);
        try {
            return $stmt->execute($data);
        } catch(PDOException $e) {
            error_log("Save Profile Error: " . $e->getMessage());
            return false;
        }
    }

    // ==============================================================================
    // 2. ตาราง: employee_education (ประวัติการศึกษา)
    // ==============================================================================
    
    public function getEducationByUserId($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM employee_education WHERE user_id = :user_id ORDER BY graduation_year DESC, id DESC");
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addEducation($data) {
        $query = "INSERT INTO employee_education (user_id, degree_level, degree_name, major, institution, graduation_year, gpa) 
                  VALUES (:user_id, :degree_level, :degree_name, :major, :institution, :graduation_year, :gpa)";
        $stmt = $this->conn->prepare($query);
        try { return $stmt->execute($data); } catch(PDOException $e) { return false; }
    }

    public function deleteEducation($id, $user_id) {
        $stmt = $this->conn->prepare("DELETE FROM employee_education WHERE id = :id AND user_id = :user_id");
        return $stmt->execute([':id' => $id, ':user_id' => $user_id]);
    }

    // ==============================================================================
    // 3. ตาราง: employee_licenses (ใบประกอบวิชาชีพ)
    // ==============================================================================
    
    public function getLicensesByUserId($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM employee_licenses WHERE user_id = :user_id ORDER BY expire_date DESC");
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addLicense($data) {
        $query = "INSERT INTO employee_licenses (user_id, license_name, license_no, council_name, issue_date, expire_date, status) 
                  VALUES (:user_id, :license_name, :license_no, :council_name, :issue_date, :expire_date, :status)";
        $stmt = $this->conn->prepare($query);
        try { return $stmt->execute($data); } catch(PDOException $e) { return false; }
    }

    public function deleteLicense($id, $user_id) {
        $stmt = $this->conn->prepare("DELETE FROM employee_licenses WHERE id = :id AND user_id = :user_id");
        return $stmt->execute([':id' => $id, ':user_id' => $user_id]);
    }

    // ==============================================================================
    // 4. ตาราง: employee_work_history (ประวัติการทำงาน)
    // ==============================================================================
    
    public function getWorkHistoryByUserId($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM employee_work_history WHERE user_id = :user_id ORDER BY start_date DESC");
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addWorkHistory($data) {
        $query = "INSERT INTO employee_work_history (user_id, company_name, position, start_date, end_date, salary, reason_for_leave, reference_contact) 
                  VALUES (:user_id, :company_name, :position, :start_date, :end_date, :salary, :reason_for_leave, :reference_contact)";
        $stmt = $this->conn->prepare($query);
        try { return $stmt->execute($data); } catch(PDOException $e) { return false; }
    }

    public function deleteWorkHistory($id, $user_id) {
        $stmt = $this->conn->prepare("DELETE FROM employee_work_history WHERE id = :id AND user_id = :user_id");
        return $stmt->execute([':id' => $id, ':user_id' => $user_id]);
    }

    // ==============================================================================
    // 5. ตาราง: employee_trainings (ประวัติการอบรม/ดูงาน)
    // ==============================================================================
    
    public function getTrainingsByUserId($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM employee_trainings WHERE user_id = :user_id ORDER BY start_date DESC");
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addTraining($data) {
        $query = "INSERT INTO employee_trainings (user_id, course_name, organizer, start_date, end_date, cpe_credits) 
                  VALUES (:user_id, :course_name, :organizer, :start_date, :end_date, :cpe_credits)";
        $stmt = $this->conn->prepare($query);
        try { return $stmt->execute($data); } catch(PDOException $e) { return false; }
    }

    public function deleteTraining($id, $user_id) {
        $stmt = $this->conn->prepare("DELETE FROM employee_trainings WHERE id = :id AND user_id = :user_id");
        return $stmt->execute([':id' => $id, ':user_id' => $user_id]);
    }
    
    // ==============================================================================
    // 6. ดึงข้อมูลตารางเวรส่วนตัว (My Schedule) - ฟังก์ชันที่หายไป
    // ==============================================================================
    public function getUserShifts($user_id, $month_year) {
        try {
            $like_month = $month_year . '-%';
            // ลองใช้คอลัมน์ shift_date (มาตรฐานส่วนใหญ่)
            $query = "SELECT * FROM shifts WHERE user_id = :user_id AND shift_date LIKE :month";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':user_id' => $user_id,
                ':month' => $like_month
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback เผื่อฐานข้อมูลใช้ชื่อคอลัมน์ว่า duty_date แทน shift_date
            try {
                $query = "SELECT id, user_id, duty_date as shift_date, shift_type FROM shifts WHERE user_id = :user_id AND duty_date LIKE :month";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':month' => $like_month
                ]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $ex) {
                return []; // ข้ามไปหากไม่พบตารางเลย
            }
        }
    }

    // ==============================================================================
    // ฟังก์ชันเสริม: สรุปภาพรวมสำหรับ Dashboard
    // ==============================================================================
    
    public function calculateAge($birth_date) {
        if (empty($birth_date) || $birth_date == '0000-00-00') return '-';
        try {
            $bday = new DateTime($birth_date);
            $today = new DateTime('today');
            return $bday->diff($today)->y; 
        } catch (Exception $e) {
            return '-';
        }
    }

    public function getExpiringLicenses($hospital_id) {
        $query = "SELECT l.license_name, l.expire_date, u.name as user_name, DATEDIFF(l.expire_date, CURDATE()) as days_left
                  FROM employee_licenses l
                  JOIN users u ON l.user_id = u.id
                  WHERE u.hospital_id = :hospital_id 
                  AND l.expire_date IS NOT NULL 
                  AND l.status = 'ACTIVE'
                  AND DATEDIFF(l.expire_date, CURDATE()) <= 60
                  AND DATEDIFF(l.expire_date, CURDATE()) >= 0
                  ORDER BY l.expire_date ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':hospital_id' => $hospital_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>