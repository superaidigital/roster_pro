<?php
// ที่อยู่ไฟล์: models/HrModel.php

class HrModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ==========================================
    // 1. ดึงสถิติภาพรวมสำหรับ Dashboard
    // ==========================================
    public function getDashboardStats($hospital_id = 0) {
        $hosp_condition = ($hospital_id != 0) ? " AND hospital_id = :hosp_id " : "";
        
        $stats = [
            'total_active' => 0,
            'total_inactive' => 0,
            'total_civil_servant' => 0,
            'total_contractor' => 0
        ];

        $q1 = "SELECT COUNT(*) FROM users WHERE is_active = 1 $hosp_condition";
        $stmt1 = $this->conn->prepare($q1);
        if($hospital_id != 0) $stmt1->bindParam(':hosp_id', $hospital_id);
        $stmt1->execute();
        $stats['total_active'] = $stmt1->fetchColumn();

        $q2 = "SELECT COUNT(*) FROM users WHERE is_active = 0 $hosp_condition";
        $stmt2 = $this->conn->prepare($q2);
        if($hospital_id != 0) $stmt2->bindParam(':hosp_id', $hospital_id);
        $stmt2->execute();
        $stats['total_inactive'] = $stmt2->fetchColumn();

        $q3 = "SELECT COUNT(*) FROM users WHERE is_active = 1 AND employee_type = 'ข้าราชการ' $hosp_condition";
        $stmt3 = $this->conn->prepare($q3);
        if($hospital_id != 0) $stmt3->bindParam(':hosp_id', $hospital_id);
        $stmt3->execute();
        $stats['total_civil_servant'] = $stmt3->fetchColumn();

        $q4 = "SELECT COUNT(*) FROM users WHERE is_active = 1 AND employee_type != 'ข้าราชการ' $hosp_condition";
        $stmt4 = $this->conn->prepare($q4);
        if($hospital_id != 0) $stmt4->bindParam(':hosp_id', $hospital_id);
        $stmt4->execute();
        $stats['total_contractor'] = $stmt4->fetchColumn();

        return $stats;
    }

    // ==========================================
    // 2. ดึงข้อมูลวันเกิดพนักงานในเดือนนี้
    // ==========================================
    public function getBirthdaysThisMonth($hospital_id = 0) {
        $hosp_condition = ($hospital_id != 0) ? " AND u.hospital_id = :hosp_id " : "";
        $query = "SELECT u.name, p.birth_date, h.short_name 
                  FROM users u 
                  JOIN employee_profiles p ON u.id = p.user_id 
                  LEFT JOIN hospitals h ON u.hospital_id = h.id
                  WHERE MONTH(p.birth_date) = MONTH(CURRENT_DATE) AND u.is_active = 1 $hosp_condition 
                  ORDER BY DAY(p.birth_date) ASC";
        $stmt = $this->conn->prepare($query);
        if($hospital_id != 0) $stmt->bindParam(':hosp_id', $hospital_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==========================================
    // 3. ดึงสถิติจำนวนบุคลากรแยกตามเพศ
    // ==========================================
    public function getGenderStats($hospital_id = 0) {
        $hosp_condition = ($hospital_id != 0) ? " AND u.hospital_id = :hosp_id " : "";
        $query = "SELECT p.gender, COUNT(*) as count 
                  FROM users u 
                  JOIN employee_profiles p ON u.id = p.user_id 
                  WHERE u.is_active = 1 $hosp_condition 
                  GROUP BY p.gender";
        $stmt = $this->conn->prepare($query);
        if($hospital_id != 0) $stmt->bindParam(':hosp_id', $hospital_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // ==========================================
    // 4. ดึงรายชื่อผู้พ้นสภาพ (ลาออก, เกษียณ, เสียชีวิต)
    // ==========================================
    public function getInactiveStaff($hospital_id = 0) {
        $hosp_condition = ($hospital_id != 0) ? " AND u.hospital_id = :hosp_id " : "";
        
        // จัดเรียงลำดับโดยใช้ u.id ป้องกันบัคกรณีฐานข้อมูลยังไม่มีคอลัมน์ inactive_date
        $query = "SELECT u.*, h.name as hospital_name 
                  FROM users u 
                  LEFT JOIN hospitals h ON u.hospital_id = h.id 
                  WHERE u.is_active = 0 $hosp_condition 
                  ORDER BY u.id DESC";
                  
        $stmt = $this->conn->prepare($query);
        if($hospital_id != 0) $stmt->bindParam(':hosp_id', $hospital_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==========================================
    // 🌟 5. ดึงข้อมูลและคำนวณความสมบูรณ์ของโปรไฟล์ (Profile Completeness)
    // อัปเดตใหม่: ดึง h.name และเช็คข้อมูลจากตาราง users ให้ครบถ้วน
    // ==========================================
    public function getProfileCompleteness($hospital_id = 0) {
        $hosp_condition = ($hospital_id != 0) ? " AND u.hospital_id = :hosp_id " : "";
        
        // ใช้ h.name เพื่อให้ได้ชื่อเต็มของ รพ.สต.
        $query = "
            SELECT 
                u.id as user_id, u.name, u.position, u.phone, u.id_card, u.start_date, h.name as hosp_name,
                p.title_name, p.first_name_th, p.last_name_th, p.gender, p.birth_date, p.blood_group,
                p.address_current, p.emergency_contact_name, p.emergency_contact_phone,
                p.bank_name, p.bank_account_no,
                (SELECT COUNT(*) FROM employee_education edu WHERE edu.user_id = u.id) as edu_count,
                (SELECT COUNT(*) FROM employee_licenses lic WHERE lic.user_id = u.id) as lic_count
            FROM users u
            LEFT JOIN employee_profiles p ON u.id = p.user_id
            LEFT JOIN hospitals h ON u.hospital_id = h.id
            WHERE u.is_active = 1 $hosp_condition
            ORDER BY u.hospital_id, u.name
        ";
        
        $stmt = $this->conn->prepare($query);
        if($hospital_id != 0) $stmt->bindParam(':hosp_id', $hospital_id);
        $stmt->execute();
        $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        
        foreach ($raw_data as $row) {
            $score = 0;
            $missing = [];
            
            // 🌟 หมวดที่ 1. ข้อมูลการจ้างงาน/ระบบ (จากหน้าเพิ่มบุคลากร) (20%)
            $emp_score = 0;
            if (!empty($row['id_card'])) $emp_score += 10;
            if (!empty($row['phone'])) $emp_score += 5;
            if (!empty($row['start_date']) && $row['start_date'] != '0000-00-00') $emp_score += 5;
            
            $score += $emp_score;
            if ($emp_score < 20) {
                $miss_emp = [];
                if(empty($row['id_card'])) $miss_emp[] = "เลขบัตรปชช.";
                if(empty($row['phone'])) $miss_emp[] = "เบอร์โทร";
                if(empty($row['start_date']) || $row['start_date'] == '0000-00-00') $miss_emp[] = "วันที่เริ่มงาน";
                $missing[] = implode("/", $miss_emp);
            }

            // หมวดที่ 2. ข้อมูลส่วนตัว (20%)
            $basic_score = 0;
            if (!empty($row['title_name']) && !empty($row['first_name_th'])) $basic_score += 10;
            if (!empty($row['gender'])) $basic_score += 5;
            if (!empty($row['birth_date']) && $row['birth_date'] != '0000-00-00') $basic_score += 5;
            
            $score += $basic_score;
            if ($basic_score < 20) $missing[] = "ข้อมูลพื้นฐาน(ชื่อ/วันเกิด/เพศ)";

            // หมวดที่ 3. การติดต่อและฉุกเฉิน (20%)
            $contact_score = 0;
            if (!empty($row['address_current'])) $contact_score += 10;
            if (!empty($row['emergency_contact_name']) && !empty($row['emergency_contact_phone'])) $contact_score += 10;
            
            $score += $contact_score;
            if ($contact_score < 20) $missing[] = "ที่อยู่/ผู้ติดต่อฉุกเฉิน";

            // หมวดที่ 4. บัญชีรับเงินเดือน (20%)
            $bank_score = 0;
            if (!empty($row['bank_name']) && !empty($row['bank_account_no'])) $bank_score += 20;
            
            $score += $bank_score;
            if ($bank_score < 20) $missing[] = "บัญชีรับเงินค่าเวร";

            // หมวดที่ 5. การศึกษาและใบประกอบวิชาชีพ (20%)
            if ($row['edu_count'] > 0 || $row['lic_count'] > 0) {
                $score += 20; // ให้เต็ม 20% ถ้ามีอย่างน้อย 1 อย่าง
            } else {
                $missing[] = "ประวัติการศึกษา/ใบประกอบฯ";
            }

            $row['completeness_score'] = $score;
            $row['missing_items'] = $missing;
            $results[] = $row;
        }
        
        return $results;
    }
}
?>