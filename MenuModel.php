<?php
// ที่อยู่ไฟล์: models/MenuModel.php

class MenuModel {
    private $conn;
    private $table_name = "system_menus";

    public function __construct($db) {
        $this->conn = $db;
        // เปิดการแจ้งเตือน Error ของ PDO
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * 🌟 ดึงเมนูเฉพาะที่เปิดใช้งานและตรงกับสิทธิ์ของผู้ใช้ปัจจุบัน
     * (ใช้สำหรับสร้าง Sidebar เมนูด้านซ้าย)
     */
    public function getMenusForUserRole($user_role) {
        try {
            // ดึงเมนูที่ is_active = 1 เท่านั้น โดยเรียงตาม display_order
            $query = "SELECT * FROM " . $this->table_name . " WHERE is_active = 1 ORDER BY display_order ASC, id ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $all_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $allowed_menus = [];

            foreach ($all_menus as $menu) {
                // ดึง String สิทธิ์ เช่น "ADMIN,SUPERADMIN" มาแยกเป็น Array
                $roles_array = explode(',', $menu['allowed_roles']);
                
                // เช็คว่า Role ของผู้ใช้ปัจจุบัน อยู่ใน Array หรือไม่
                if (in_array($user_role, $roles_array)) {
                    $allowed_menus[] = $menu;
                }
            }

            return $allowed_menus;

        } catch (PDOException $e) {
            error_log("MenuModel Error (getMenusForUserRole): " . $e->getMessage());
            return [];
        }
    }

    /**
     * 🌟 ดึงข้อมูลเมนูทั้งหมดในระบบ 
     * (ใช้สำหรับหน้าตั้งค่าสิทธิ์การเข้าถึงของ SUPERADMIN)
     */
    public function getAllMenus() {
        try {
            $query = "SELECT * FROM " . $this->table_name . " ORDER BY display_order ASC, id ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("MenuModel Error (getAllMenus): " . $e->getMessage());
            return [];
        }
    }

    /**
     * 🌟 ดึงข้อมูลเมนูตาม ID
     */
    public function getMenuById($id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("MenuModel Error (getMenuById): " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🌟 อัปเดตสถานะและสิทธิ์การเข้าถึงเมนู
     * (ใช้ตอนที่ SUPERADMIN กดบันทึกในหน้าจัดการเมนู)
     */
    public function updateMenuPermission($id, $is_active, $allowed_roles_string) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET is_active = :is_active, allowed_roles = :allowed_roles 
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':is_active', $is_active);
            $stmt->bindParam(':allowed_roles', $allowed_roles_string);
            $stmt->bindParam(':id', $id);
            
            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("MenuModel Error (updateMenuPermission): " . $e->getMessage());
            return false;
        }
    }
}
?>