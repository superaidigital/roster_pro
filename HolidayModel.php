<?php
// ที่อยู่ไฟล์: models/HolidayModel.php

class HolidayModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ดึงวันหยุดทั้งหมด (กรองตามปีได้)
    public function getAllHolidays($year = null) {
        $query = "SELECT * FROM holidays ";
        if ($year) {
            $query .= "WHERE YEAR(holiday_date) = :year ";
        }
        $query .= "ORDER BY holiday_date ASC";
        
        $stmt = $this->conn->prepare($query);
        if ($year) {
            $stmt->bindParam(':year', $year);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================
    // 🛠️ ส่วนที่เพิ่มเข้ามาเพื่อแก้ไข Fatal Error: isHoliday()
    // =========================================================
    
    // ตรวจสอบว่าวันที่ระบุเป็นวันหยุดหรือไม่ (ส่งกลับค่า true / false)
    public function isHoliday($date) {
        try {
            // เช็คว่าเป็นวันหยุดและเปิดใช้งานอยู่ (is_active = 1)
            $stmt = $this->conn->prepare("SELECT * FROM holidays WHERE holiday_date = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$date]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? true : false;
        } catch (PDOException $e) {
            // Fallback เผื่อกรณีที่ฐานข้อมูลยังไม่ได้อัปเดตคอลัมน์ is_active
            $stmt = $this->conn->prepare("SELECT * FROM holidays WHERE holiday_date = ? LIMIT 1");
            $stmt->execute([$date]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? true : false;
        }
    }

    // ดึงวันหยุดตามเดือน-ปี (เอาไว้ใช้ตรวจสอบรวดเดียวตอนจัดตารางเวร)
    public function getHolidaysByMonth($year, $month) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM holidays WHERE YEAR(holiday_date) = ? AND MONTH(holiday_date) = ? AND is_active = 1");
            $stmt->execute([$year, $month]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // =========================================================

    // เพิ่มวันหยุดแบบ Manual
    public function addHoliday($date, $name, $type = 'REGULAR') {
        try {
            $stmt = $this->conn->prepare("INSERT INTO holidays (holiday_date, holiday_name, holiday_type, is_active) VALUES (?, ?, ?, 1)");
            return $stmt->execute([$date, $name, $type]);
        } catch (PDOException $e) {
            return false;
        }
    }

    // ลบวันหยุด
    public function deleteHoliday($id) {
        $stmt = $this->conn->prepare("DELETE FROM holidays WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // เปิด-ปิด การใช้วันหยุด
    public function toggleStatus($id, $status) {
        $stmt = $this->conn->prepare("UPDATE holidays SET is_active = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    // ซิงค์ข้อมูลวันหยุดจาก API อัตโนมัติ (Nager.Date API)
    public function syncHolidaysFromAPI($year) {
        $url = "https://date.nager.at/api/v3/PublicHolidays/{$year}/TH";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || !$response) {
            return ['success' => false, 'message' => 'ไม่สามารถเชื่อมต่อ API ได้ (HTTP ' . $http_code . ')'];
        }

        $api_holidays = json_decode($response, true);
        if (!is_array($api_holidays)) {
            return ['success' => false, 'message' => 'รูปแบบข้อมูลจาก API ไม่ถูกต้อง'];
        }

        $added = 0;
        $skipped = 0;

        $existing_stmt = $this->conn->prepare("SELECT holiday_date FROM holidays WHERE YEAR(holiday_date) = ?");
        $existing_stmt->execute([$year]);
        $existing_dates = $existing_stmt->fetchAll(PDO::FETCH_COLUMN);

        $insert_stmt = $this->conn->prepare("INSERT INTO holidays (holiday_date, holiday_name, holiday_type, is_active) VALUES (?, ?, ?, 1)");

        foreach ($api_holidays as $day) {
            $date = $day['date'];
            $name = $day['localName']; 
            
            $type = 'REGULAR';
            if (mb_strpos($name, 'ชดเชย') !== false) {
                $type = 'COMPENSATION';
            }

            if (!in_array($date, $existing_dates)) {
                if ($insert_stmt->execute([$date, $name, $type])) {
                    $added++;
                }
            } else {
                $skipped++;
            }
        }

        return [
            'success' => true,
            'added' => $added,
            'skipped' => $skipped
        ];
    }
}
?>