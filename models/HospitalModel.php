<?php
// ที่อยู่ไฟล์: models/HospitalModel.php

class HospitalModel {
    private $conn;
    private $table_name = "hospitals";

    public function __construct($db) {
        $this->conn = $db;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 🌟 เรียกใช้ฟังก์ชันตรวจสอบและเพิ่มคอลัมน์
        $this->checkAndCreateColumns();
    }

    private function checkAndCreateColumns() {
        try {
            $stmt = $this->conn->query("SHOW COLUMNS FROM " . $this->table_name);
            $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $required_columns = [
                'email' => 'VARCHAR(100) NULL',
                'phone' => 'VARCHAR(20) NULL',
                'address' => 'VARCHAR(255) NULL',
                'sub_district' => 'VARCHAR(100) NULL',
                'district' => 'VARCHAR(100) NULL',
                'province' => 'VARCHAR(100) NULL',
                'zipcode' => 'VARCHAR(10) NULL',
                'logo' => 'VARCHAR(255) NULL',
                'director_name' => 'VARCHAR(255) NULL',
                'hospital_code' => 'VARCHAR(20) NULL',
                'hospital_size' => "VARCHAR(10) DEFAULT 'S'",
                'latitude' => "VARCHAR(50) NULL",
                'longitude' => "VARCHAR(50) NULL",
                'shift_m_start' => "TIME DEFAULT '08:00:00'",
                'shift_m_end' => "TIME DEFAULT '16:00:00'",
                'shift_a_start' => "TIME DEFAULT '16:00:00'",
                'shift_a_end' => "TIME DEFAULT '00:00:00'",
                'shift_n_start' => "TIME DEFAULT '00:00:00'",
                'shift_n_end' => "TIME DEFAULT '08:00:00'",
                // 🌟 เพิ่มคอลัมน์ Soft Delete
                'deleted_at' => "DATETIME NULL DEFAULT NULL COMMENT 'เวลาที่ถูกลบ (Soft Delete)'"
            ];

            $columns_to_add = [];
            foreach ($required_columns as $column_name => $column_type) {
                if (!in_array($column_name, $existing_columns)) {
                    $columns_to_add[] = "ADD COLUMN `$column_name` $column_type";
                }
            }

            if (!empty($columns_to_add)) {
                $alter_query = "ALTER TABLE `" . $this->table_name . "` " . implode(', ', $columns_to_add);
                $this->conn->exec($alter_query);
            }
        } catch (PDOException $e) {
            error_log("Hospital Auto-migration failed: " . $e->getMessage());
        }
    }

    public function getHospitalLogo($hospitalData) {
        if (!empty($hospitalData['logo']) && file_exists($hospitalData['logo'])) {
            return $hospitalData['logo'];
        }
        return 'assets/images/default_hospital.png';
    }

    // ====================================================
    // 🌟 ดึงข้อมูล (เฉพาะ รพ. ที่ไม่ถูกลบ)
    // ====================================================
    public function getAllHospitals() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE deleted_at IS NULL ORDER BY id ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHospitalById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addHospital($name, $hospital_code = null, $hospital_size = 'S', $latitude = null, $longitude = null) {
        $query = "INSERT INTO " . $this->table_name . " (name, hospital_code, hospital_size, latitude, longitude) VALUES (:name, :hospital_code, :hospital_size, :latitude, :longitude)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':hospital_code', $hospital_code);
        $stmt->bindParam(':hospital_size', $hospital_size);
        $stmt->bindParam(':latitude', $latitude);
        $stmt->bindParam(':longitude', $longitude);
        try { return $stmt->execute(); } catch (PDOException $e) { return false; }
    }

    public function updateHospital($id, $name, $hospital_code = null, $hospital_size = 'S', $latitude = null, $longitude = null) {
        $query = "UPDATE " . $this->table_name . " SET name = :name, hospital_code = :hospital_code, hospital_size = :hospital_size, latitude = :latitude, longitude = :longitude WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':hospital_code', $hospital_code);
        $stmt->bindParam(':hospital_size', $hospital_size);
        $stmt->bindParam(':latitude', $latitude);
        $stmt->bindParam(':longitude', $longitude);
        try { return $stmt->execute(); } catch (PDOException $e) { return false; }
    }

    public function updateHospitalFull($id, $name, $hospital_code, $hospital_size, $latitude, $longitude, $email, $phone, $address, $sub_district, $district, $province, $zipcode, $morning, $afternoon, $night, $logo_path = null) {
        $query = "UPDATE " . $this->table_name . " SET 
                  name = :name, hospital_code = :hospital_code, hospital_size = :hospital_size, 
                  latitude = :latitude, longitude = :longitude, email = :email, phone = :phone, 
                  address = :address, sub_district = :sub_district, district = :district, 
                  province = :province, zipcode = :zipcode,
                  shift_m_start = :morning_shift_start, shift_m_end = :morning_shift_end,
                  shift_a_start = :afternoon_shift_start, shift_a_end = :afternoon_shift_end,
                  shift_n_start = :night_shift_start, shift_n_end = :night_shift_end";

        if ($logo_path) { $query .= ", logo = :logo"; }
        $query .= " WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $m_arr = explode('-', $morning);
        $a_arr = explode('-', $afternoon);
        $n_arr = explode('-', $night);

        $m_s = trim($m_arr[0] ?? '08:00'); $m_e = trim($m_arr[1] ?? '16:00');
        $a_s = trim($a_arr[0] ?? '16:00'); $a_e = trim($a_arr[1] ?? '00:00');
        $n_s = trim($n_arr[0] ?? '00:00'); $n_e = trim($n_arr[1] ?? '08:00');

        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':hospital_code', $hospital_code);
        $stmt->bindParam(':hospital_size', $hospital_size);
        $stmt->bindParam(':latitude', $latitude);
        $stmt->bindParam(':longitude', $longitude);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':sub_district', $sub_district);
        $stmt->bindParam(':district', $district);
        $stmt->bindParam(':province', $province);
        $stmt->bindParam(':zipcode', $zipcode);

        $stmt->bindParam(':morning_shift_start', $m_s);
        $stmt->bindParam(':morning_shift_end', $m_e);
        $stmt->bindParam(':afternoon_shift_start', $a_s);
        $stmt->bindParam(':afternoon_shift_end', $a_e);
        $stmt->bindParam(':night_shift_start', $n_s);
        $stmt->bindParam(':night_shift_end', $n_e);

        if($logo_path) { $stmt->bindParam(':logo', $logo_path); }

        try { return $stmt->execute(); } catch(PDOException $e) { return false; }
    }

    /**
     * 🌟 ระบบ Soft Delete ของโรงพยาบาล
     * หมายเหตุ: ระบบเช็คความปลอดภัย หากยังมีพนักงานที่ไม่ได้ถูกลบอยู่ใน รพ. นี้ จะลบไม่ได้
     */
    public function deleteHospital($id) {
        // 1. ตรวจสอบก่อนว่ายังมีผู้ใช้งานค้างอยู่ในระบบหรือไม่
        $stmt_check = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE hospital_id = :id AND deleted_at IS NULL");
        $stmt_check->execute([':id' => $id]);
        $active_users = $stmt_check->fetchColumn();

        if ($active_users > 0) {
            return false; // ไม่อนุญาตให้ลบ
        }

        // 2. ถ้าไม่มีผู้ใช้แล้ว ให้ทำ Soft Delete
        $query = "UPDATE " . $this->table_name . " SET deleted_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Delete Hospital Error: " . $e->getMessage());
            return false;
        }
    }
}
?>