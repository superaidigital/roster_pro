<?php
// ที่อยู่ไฟล์: models/UserModel.php

require_once __DIR__ . '/../lib/ElectronicSignature.php';

class UserModel {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // รันฟังก์ชันตรวจสอบและสร้างคอลัมน์อัตโนมัติเมื่อมีการเรียกใช้ Model
        $this->checkAndCreateColumns();
    }

    /**
     * 🌟 ระบบ Auto-Migration (อัปเดตโครงสร้างฐานข้อมูลอัตโนมัติ)
     */
    private function checkAndCreateColumns() {
        try {
            $stmt = $this->conn->query("SHOW COLUMNS FROM " . $this->table_name);
            $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $required_columns = [
                'phone' => 'VARCHAR(20) NULL',
                'type' => 'VARCHAR(100) NULL', 
                'position' => 'VARCHAR(255) NULL',
                'color_theme' => "VARCHAR(20) DEFAULT 'primary'",
                'employee_type' => "VARCHAR(100) DEFAULT 'ข้าราชการ/พนักงานท้องถิ่น'",
                'start_date' => 'DATE NULL',
                'sort_order' => 'INT DEFAULT 0',
                'id_card' => 'VARCHAR(13) NULL',
                'position_number' => 'VARCHAR(50) NULL',
                'pay_rate_id' => 'INT NULL',
                'is_active' => "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Active, 0=Suspended'",
                'display_order' => "INT(11) DEFAULT 0",
                'deleted_at' => "DATETIME NULL DEFAULT NULL COMMENT 'เวลาที่ถูกลบ (Soft Delete)'",
                'show_in_roster' => "TINYINT(1) DEFAULT 1 COMMENT '1=Show in roster, 0=Hide'",
                // 🌟 เพิ่มคอลัมน์ Signature Path สำหรับเก็บลายเซ็นอิเล็กทรอนิกส์ (Base64)
                'signature_path' => "LONGTEXT NULL COMMENT 'เก็บลายมือชื่ออิเล็กทรอนิกส์ (Base64)'",
                'signature_sha256' => "CHAR(64) NULL",
                'signature_method' => "VARCHAR(20) NULL",
                'signature_updated_at' => "DATETIME NULL",
                'signature_pdpa_notice_version' => "VARCHAR(50) NULL",
                'signature_pdpa_ack_at' => "DATETIME NULL"
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
                error_log("Auto-migrated columns in users table: " . implode(', ', array_keys($columns_to_add)));
            }
        } catch (PDOException $e) {
            error_log("User Auto-migration failed: " . $e->getMessage());
        }
    }

    // ====================================================
    // 🌟 1. ระบบเข้าสู่ระบบ (ห้ามคนที่ถูกลบเข้าระบบ)
    // ====================================================
    public function login($username, $password) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE username = :username AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    public function getUserByUsername($username) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE username = :username AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ====================================================
    // 🌟 2. ระบบดึงข้อมูลผู้ใช้งาน (ดึงเฉพาะคนที่ไม่ถูกลบ)
    // ====================================================
    public function getUserById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUsersByHospital($hospital_id) {
        $query = "SELECT u.*, p.name as pay_rate_name 
                  FROM " . $this->table_name . " u 
                  LEFT JOIN pay_rates p ON u.pay_rate_id = p.id ";
                  
        if (empty($hospital_id)) {
            $query .= "WHERE (u.hospital_id IS NULL OR u.hospital_id = 0) AND u.deleted_at IS NULL ";
        } else {
            $query .= "WHERE u.hospital_id = :hospital_id AND u.deleted_at IS NULL ";
        }
        
        $query .= "ORDER BY u.display_order ASC, u.id ASC";
        
        $stmt = $this->conn->prepare($query);
        if (!empty($hospital_id)) {
            $stmt->bindParam(':hospital_id', $hospital_id);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllUsers() {
        $query = "SELECT u.*, h.name as hospital_name, p.name as pay_rate_name
                  FROM " . $this->table_name . " u 
                  LEFT JOIN hospitals h ON u.hospital_id = h.id 
                  LEFT JOIN pay_rates p ON u.pay_rate_id = p.id
                  WHERE u.deleted_at IS NULL
                  ORDER BY u.hospital_id ASC, u.display_order ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllStaff($hospital_id = null) {
        if ($hospital_id) {
            return $this->getUsersByHospital($hospital_id);
        } else {
            return $this->getAllUsers();
        }
    }
    
    // ดึงรายชื่อคนที่จะจัดเวร (ใช้งานเฉพาะคนที่ show_in_roster = 1)
    public function getActiveStaffForSchedule($hospital_id = null) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE is_active = 1 
                  AND deleted_at IS NULL 
                  AND (show_in_roster = 1 OR show_in_roster IS NULL) ";
                  
        if ($hospital_id !== null) {
            if ($hospital_id == 0) {
                $query .= "AND (hospital_id IS NULL OR hospital_id = 0) ";
            } else {
                $query .= "AND hospital_id = :hospital_id ";
            }
        }
        $query .= "ORDER BY display_order ASC, name ASC";
        
        $stmt = $this->conn->prepare($query);
        if ($hospital_id !== null && $hospital_id != 0) {
            $stmt->bindParam(':hospital_id', $hospital_id);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ====================================================
    // 🌟 3. ระบบตรวจสอบข้อมูลซ้ำ
    // ====================================================
    public function checkDuplicateField($field, $value, $exclude_id = null) {
        $allowed_fields = ['username', 'id_card', 'position_number'];
        if (!in_array($field, $allowed_fields)) { return false; }

        $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE $field = :value AND deleted_at IS NULL";
        if ($exclude_id) { $query .= " AND id != :exclude_id"; }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':value', $value);
        if ($exclude_id) { $stmt->bindParam(':exclude_id', $exclude_id); }
        
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    public function checkUsernameExists($username, $exclude_id = null) {
        return $this->checkDuplicateField('username', $username, $exclude_id);
    }

    // ====================================================
    // 🌟 4. ระบบจัดการข้อมูล (เพิ่มและแก้ไข)
    // ====================================================
    public function addUser($data) { 
        $query = "INSERT INTO " . $this->table_name . " 
                  (hospital_id, username, password, name, phone, role, type, position, color_theme, employee_type, start_date, id_card, position_number, pay_rate_id) 
                  VALUES 
                  (:hospital_id, :username, :password, :name, :phone, :role, :type, :position, :color_theme, :employee_type, :start_date, :id_card, :position_number, :pay_rate_id)";

        $stmt = $this->conn->prepare($query);
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt->bindValue(':hospital_id', empty($data['hospital_id']) ? null : $data['hospital_id'], PDO::PARAM_INT);
        $stmt->bindValue(':pay_rate_id', empty($data['pay_rate_id']) ? null : $data['pay_rate_id'], PDO::PARAM_INT);
        $stmt->bindValue(':start_date', empty($data['start_date']) ? null : $data['start_date'], PDO::PARAM_STR);
        $stmt->bindValue(':phone', empty($data['phone']) ? null : $data['phone'], PDO::PARAM_STR);
        $stmt->bindValue(':type', empty($data['type']) ? null : $data['type'], PDO::PARAM_STR);
        $stmt->bindValue(':position', empty($data['position']) ? null : $data['position'], PDO::PARAM_STR);
        $stmt->bindValue(':id_card', empty($data['id_card']) ? null : $data['id_card'], PDO::PARAM_STR);
        $stmt->bindValue(':position_number', empty($data['position_number']) ? null : $data['position_number'], PDO::PARAM_STR);

        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':role', $data['role']);
        $stmt->bindParam(':color_theme', $data['color_theme']);
        $stmt->bindParam(':employee_type', $data['employee_type']);

        try { return $stmt->execute(); } catch (PDOException $e) { return false; }
    }

    public function updateUser($data) { 
        $query = "UPDATE " . $this->table_name . " SET 
                  hospital_id = :hospital_id, name = :name, phone = :phone, role = :role, 
                  type = :type, position = :position, color_theme = :color_theme, employee_type = :employee_type,
                  start_date = :start_date, id_card = :id_card, position_number = :position_number,
                  pay_rate_id = :pay_rate_id";

        if (!empty($data['password'])) { $query .= ", password = :password"; }
        $query .= " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $data['id']);
        
        $stmt->bindValue(':hospital_id', empty($data['hospital_id']) ? null : $data['hospital_id'], PDO::PARAM_INT);
        $stmt->bindValue(':pay_rate_id', empty($data['pay_rate_id']) ? null : $data['pay_rate_id'], PDO::PARAM_INT);
        $stmt->bindValue(':start_date', empty($data['start_date']) ? null : $data['start_date'], PDO::PARAM_STR);
        $stmt->bindValue(':phone', empty($data['phone']) ? null : $data['phone'], PDO::PARAM_STR);
        $stmt->bindValue(':type', empty($data['type']) ? null : $data['type'], PDO::PARAM_STR);
        $stmt->bindValue(':position', empty($data['position']) ? null : $data['position'], PDO::PARAM_STR);
        $stmt->bindValue(':id_card', empty($data['id_card']) ? null : $data['id_card'], PDO::PARAM_STR);
        $stmt->bindValue(':position_number', empty($data['position_number']) ? null : $data['position_number'], PDO::PARAM_STR);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':role', $data['role']);
        $stmt->bindParam(':color_theme', $data['color_theme']);
        $stmt->bindParam(':employee_type', $data['employee_type']);

        if (!empty($data['password'])) {
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt->bindParam(':password', $hashed_password);
        }

        try { return $stmt->execute(); } catch (PDOException $e) { return false; }
    }

    public function deleteUser($id) {
        $query = "UPDATE " . $this->table_name . " SET deleted_at = NOW(), is_active = 0 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Delete User Error: " . $e->getMessage());
            return false;
        }
    }

    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " SET is_active = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function updateOrder($id, $order) {
        $query = "UPDATE " . $this->table_name . " SET display_order = :sort_order WHERE id = :id ";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':sort_order', $order, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        try { return $stmt->execute(); } catch (PDOException $e) { return false; }
    }

    // ====================================================
    // 🌟 5. ลายเซ็นอิเล็กทรอนิกส์
    // ====================================================
    public function updateSignature(
        $id,
        $signature_base64,
        string $method,
        string $privacyNoticeVersion
    ) {
        try {
            if ($privacyNoticeVersion !== ElectronicSignature::PRIVACY_NOTICE_VERSION) {
                throw new InvalidArgumentException('Electronic signature privacy notice version is invalid.');
            }

            $signature = ElectronicSignature::normalize((string)$signature_base64, $method);

            $query = "UPDATE " . $this->table_name . "
                      SET signature_path = :signature,
                          signature_sha256 = :sha256,
                          signature_method = :method,
                          signature_updated_at = NOW(),
                          signature_pdpa_notice_version = :notice_version,
                          signature_pdpa_ack_at = NOW()
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':signature', $signature['data_url'], PDO::PARAM_STR);
            $stmt->bindValue(':sha256', $signature['sha256'], PDO::PARAM_STR);
            $stmt->bindValue(':method', $signature['method'], PDO::PARAM_STR);
            $stmt->bindValue(':notice_version', $privacyNoticeVersion, PDO::PARAM_STR);
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Throwable $e) {
            error_log("Update Signature Error: " . $e->getMessage());
            return false;
        }
    }

    public function getSignatureRecord(int $id): ?array {
        $query = "SELECT signature_path, signature_sha256, signature_method, signature_updated_at,
                         signature_pdpa_notice_version, signature_pdpa_ack_at
                  FROM " . $this->table_name . "
                  WHERE id = :id AND deleted_at IS NULL
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function clearSignature(int $id): bool {
        $query = "UPDATE " . $this->table_name . "
                  SET signature_path = NULL,
                      signature_sha256 = NULL,
                      signature_method = NULL,
                      signature_updated_at = NOW()
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Clear Signature Error: " . $e->getMessage());
            return false;
        }
    }
}
?>