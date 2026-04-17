<?php
// ที่อยู่ไฟล์: models/PayRateModel.php

class PayRateModel {
    private $conn;
    private $table_name = "pay_rates";

    public function __construct($db) {
        $this->conn = $db;
        $this->checkAndCreateTable();
    }

    private function checkAndCreateTable() {
        $query = "CREATE TABLE IF NOT EXISTS `" . $this->table_name . "` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `keywords` TEXT NULL,
            `rate_r` INT DEFAULT 0,
            `rate_y` INT DEFAULT 0,
            `rate_b` INT DEFAULT 0,
            `display_order` INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        try {
            $this->conn->exec($query);
            $cols = $this->conn->query("SHOW COLUMNS FROM " . $this->table_name)->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('name', $cols)) {
                $this->conn->exec("ALTER TABLE `" . $this->table_name . "` ADD COLUMN `name` VARCHAR(100) NULL AFTER `id`");
                $this->conn->exec("UPDATE `" . $this->table_name . "` SET `name` = `keywords` WHERE `name` IS NULL");
            }
            if (!in_array('display_order', $cols)) {
                $this->conn->exec("ALTER TABLE `" . $this->table_name . "` ADD COLUMN `display_order` INT DEFAULT 0");
            }
            
            $stmtCount = $this->conn->query("SELECT COUNT(*) FROM " . $this->table_name);
            if ($stmtCount->fetchColumn() == 0) {
                $this->conn->exec("INSERT INTO `" . $this->table_name . "` (`name`, `keywords`, `rate_r`, `rate_y`, `rate_b`, `display_order`) VALUES 
                ('แพทย์', 'แพทย์,หมอ,พญ,นพ', 1200, 1200, 1200, 1),
                ('พยาบาลวิชาชีพ', 'พยาบาลวิชาชีพ,พยาบาล,วิชาชีพ', 800, 800, 800, 2),
                ('เจ้าพนักงาน', 'เจ้าพนักงาน,จพง,จพ.,ลูกจ้าง,พนักงาน', 600, 600, 600, 3)");
            }
        } catch(PDOException $e) {}
    }

    public function getAllRates() {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table_name . " ORDER BY display_order ASC, id ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 🌟 ระบบบันทึกแบบฉลาด (อัปเดตอันเก่า เพิ่มอันใหม่ ลบอันที่ถูกกากบาททิ้ง)
    public function saveAllRates($ratesData) {
        try {
            $this->conn->beginTransaction();
            $existing_ids = [];
            $order = 1;

            $stmtUpdate = $this->conn->prepare("UPDATE " . $this->table_name . " SET name=?, keywords=?, rate_r=?, rate_y=?, rate_b=?, display_order=? WHERE id=?");
            $stmtInsert = $this->conn->prepare("INSERT INTO " . $this->table_name . " (name, keywords, rate_r, rate_y, rate_b, display_order) VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($ratesData as $r) {
                if(!empty(trim($r['name']))) {
                    if (!empty($r['id'])) {
                        // อัปเดตข้อมูลเดิมที่มีอยู่แล้ว
                        $stmtUpdate->execute([trim($r['name']), trim($r['keywords']), (int)$r['rate_r'], (int)$r['rate_y'], (int)$r['rate_b'], $order++, $r['id']]);
                        $existing_ids[] = $r['id'];
                    } else {
                        // สร้างรายการใหม่
                        $stmtInsert->execute([trim($r['name']), trim($r['keywords']), (int)$r['rate_r'], (int)$r['rate_y'], (int)$r['rate_b'], $order++]);
                        $existing_ids[] = $this->conn->lastInsertId();
                    }
                }
            }

            // ลบแถวที่ถูกผู้ใช้งานกดลบทิ้ง
            if (!empty($existing_ids)) {
                $placeholders = implode(',', array_fill(0, count($existing_ids), '?'));
                $stmtDel = $this->conn->prepare("DELETE FROM " . $this->table_name . " WHERE id NOT IN ($placeholders)");
                $stmtDel->execute($existing_ids);
            } else {
                $this->conn->exec("DELETE FROM " . $this->table_name);
            }

            $this->conn->commit();
            return true;
        } catch(PDOException $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
?>