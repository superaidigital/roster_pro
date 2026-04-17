<?php
// ที่อยู่ไฟล์: models/HospitalModel.php

class HospitalModel {
    private $conn;
    private $table_name = "hospitals";

    public function __construct($db) {
        $this->conn = $db;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 🌟 เรียกใช้ฟังก์ชันตรวจสอบและเพิ่มคอลัมน์ที่ขาดหายไปโดยอัตโนมัติ
        $this->checkAndCreateColumns();
    }

    /**
     * 🌟 ฟังก์ชันตรวจสอบและสร้างคอลัมน์ที่จำเป็นโดยอัตโนมัติ (Auto-Migration)
     * ป้องกันปัญหาคอลัมน์ขาดหายหรือเพิ่มซ้ำ
     */
    private function checkAndCreateColumns() {
        try {
            // ดึงรายชื่อคอลัมน์ทั้งหมดในตาราง hospitals
            $stmt = $this->conn->query("SHOW COLUMNS FROM " . $this->table_name);
            $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // รายการคอลัมน์ที่ต้องมี (ชื่อคอลัมน์ => ชนิดข้อมูล)
            $required_columns = [
                'email' => 'VARCHAR(100) NULL',
                'phone' => 'VARCHAR(20) NULL',
                'address' => 'VARCHAR(255) NULL',
                'sub_district' => 'VARCHAR(100) NULL',
                'district' => 'VARCHAR(100) NULL',
                'province' => 'VARCHAR(100) NULL',
                'zipcode' => 'VARCHAR(10) NULL',
                'morning_shift' => 'VARCHAR(50) NULL',
                'afternoon_shift' => 'VARCHAR(50) NULL',
                'night_shift' => 'VARCHAR(50) NULL',
                'logo' => 'VARCHAR(255) NULL'
            ];

            $columns_to_add = [];
            foreach ($required_columns as $column_name => $column_type) {
                // ถ้าคอลัมน์นี้ยังไม่มีในฐานข้อมูล ให้เพิ่มเข้าไปในรายการที่ต้องสร้าง
                if (!in_array($column_name, $existing_columns)) {
                    $columns_to_add[] = "ADD COLUMN `$column_name` $column_type";
                }
            }

            // ถ้ามีคอลัมน์ต้องสร้าง ให้รันคำสั่ง ALTER TABLE ทีเดียว
            if (!empty($columns_to_add)) {
                $alter_query = "ALTER TABLE `" . $this->table_name . "` " . implode(', ', $columns_to_add);
                $this->conn->exec($alter_query);
                error_log("Auto-migrated columns in hospitals table: " . implode(', ', array_keys($columns_to_add)));
            }

        } catch (PDOException $e) {
            // ไม่ต้องทำอะไร ปล่อยผ่านไป (อาจจะไม่มีสิทธิ์ ALTER TABLE)
            error_log("Auto-migration failed: " . $e->getMessage());
        }
    }

    /**
     * ดึงข้อมูลหน่วยบริการทั้งหมด
     * เชื่อมกับตาราง users เพื่อหาคนที่เป็น DIRECTOR ของแต่ละแห่ง
     */
    public function getAllHospitals() {
        $query = "SELECT h.*, u.name as director_name, u.type as director_position 
                  FROM " . $this->table_name . " h
                  LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'DIRECTOR'
                  ORDER BY h.id ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ดึงข้อมูลหน่วยบริการตาม ID
     * ดึงข้อมูลผู้บริหารจากตาราง users โดยอัตโนมัติ
     */
    public function getHospitalById($id) {
        $query = "SELECT h.*, u.name as director_name, u.type as director_position 
                  FROM " . $this->table_name . " h
                  LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'DIRECTOR'
                  WHERE h.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Helper สำหรับจัดการโลโก้
     * 🌟 ถ้าไม่มีรูปโลโก้ ให้สร้างรูปภาพ SVG แบบ Base64 ที่เป็นตัวอักษรย่อให้โดยอัตโนมัติ
     */
    public function getHospitalLogo($hospital) {
        // 1. ถ้ามีรูปโลโก้ และไฟล์มีอยู่จริง ให้ส่งคืนที่อยู่ไฟล์
        if (!empty($hospital['logo']) && file_exists($hospital['logo'])) {
            return $hospital['logo'];
        }
        
        // 2. ถ้าไม่มีรูป ให้เอาชื่อมาตัดคำเพื่อทำตัวอักษรย่อ (ลบคำนำหน้าออกก่อน)
        $name = $hospital['name'] ?? 'H';
        $cleanName = str_replace(['รพ.สต.', 'โรงพยาบาลส่งเสริมสุขภาพตำบล', 'โรงพยาบาล', ' '], '', $name);
        $initial = mb_substr(trim($cleanName), 0, 1, 'UTF-8');
        if (empty($initial)) $initial = 'H';
        
        // 3. สร้างรูปภาพ SVG ที่มีตัวหนังสืออยู่ตรงกลาง
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect width="200" height="200" fill="#0d6efd"/><text x="50%%" y="50%%" dominant-baseline="central" text-anchor="middle" font-family="sans-serif" font-size="90" font-weight="bold" fill="#ffffff">%s</text></svg>',
            htmlspecialchars($initial)
        );
        
        // 4. คืนค่าเป็น Base64 Data URI เพื่อให้นำไปใส่ใน <img src="..."> ได้ทันที
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * เพิ่มหน่วยบริการใหม่ (ฉบับสมบูรณ์)
     */
    public function addHospital($name, $hospital_code, $hospital_size = 'S', $latitude = null, $longitude = null, $email = null, $phone = null, $address = null, $sub_district = null, $district = null, $province = null, $zipcode = null, $morning = null, $afternoon = null, $night = null, $logo_path = null) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (name, hospital_code, hospital_size, latitude, longitude, email, phone, address, sub_district, district, province, zipcode, morning_shift, afternoon_shift, night_shift, logo) 
                  VALUES (:name, :hospital_code, :hospital_size, :latitude, :longitude, :email, :phone, :address, :sub_district, :district, :province, :zipcode, :morning_shift, :afternoon_shift, :night_shift, :logo)";
        
        $stmt = $this->conn->prepare($query);
        
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
        $stmt->bindParam(':morning_shift', $morning);
        $stmt->bindParam(':afternoon_shift', $afternoon);
        $stmt->bindParam(':night_shift', $night);
        $stmt->bindParam(':logo', $logo_path);

        try {
            if($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
        } catch(PDOException $e) {
            error_log("Add Hospital Error: " . $e->getMessage());
            return false;
        }
        return false;
    }

    /**
     * อัปเดตข้อมูลหน่วยบริการ (ฉบับสมบูรณ์)
     */
    public function updateHospital($id, $name, $hospital_code, $hospital_size = 'S', $latitude = null, $longitude = null, $email = null, $phone = null, $address = null, $sub_district = null, $district = null, $province = null, $zipcode = null, $morning = null, $afternoon = null, $night = null, $logo_path = null) {
        
        $query = "UPDATE " . $this->table_name . " SET 
                  name = :name, 
                  hospital_code = :hospital_code,
                  hospital_size = :hospital_size,
                  latitude = :latitude,
                  longitude = :longitude,
                  email = :email,
                  phone = :phone,
                  address = :address,
                  sub_district = :sub_district,
                  district = :district,
                  province = :province,
                  zipcode = :zipcode,
                  morning_shift = :morning_shift,
                  afternoon_shift = :afternoon_shift,
                  night_shift = :night_shift";

        if($logo_path) {
            $query .= ", logo = :logo";
        }

        $query .= " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);

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
        $stmt->bindParam(':morning_shift', $morning);
        $stmt->bindParam(':afternoon_shift', $afternoon);
        $stmt->bindParam(':night_shift', $night);

        if($logo_path) {
            $stmt->bindParam(':logo', $logo_path);
        }

        try {
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Update Hospital Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ลบหน่วยบริการ
     */
    public function deleteHospital($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        try {
            return $stmt->execute();
        } catch(PDOException $e) {
            return false;
        }
    }
}
?>