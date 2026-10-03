<?php
// ที่อยู่ไฟล์: models/PayRateModel.php

class PayRateModel {
    private $conn;
    private $table_name = "pay_rates";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllRates() {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table_name . " ORDER BY display_order ASC, id ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateRate($id, array $data) {
        $name = trim((string)($data['name'] ?? ''));
        $keywords = trim((string)($data['keywords'] ?? ''));
        if ($name === '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $keywords))));
            $name = $parts[0] ?? 'กลุ่มค่าตอบแทน';
        }

        $columns = $this->conn->query("SHOW COLUMNS FROM " . $this->table_name)->fetchAll(PDO::FETCH_COLUMN);
        $sets = [];
        $params = [];

        if (in_array('name', $columns, true)) { $sets[] = "name = ?"; $params[] = $name; }
        if (in_array('group_name', $columns, true)) { $sets[] = "group_name = ?"; $params[] = $name; }
        $sets[] = "keywords = ?"; $params[] = $keywords;
        $sets[] = "rate_r = ?"; $params[] = (int)($data['rate_r'] ?? 0);
        $sets[] = "rate_y = ?"; $params[] = (int)($data['rate_y'] ?? 0);
        $sets[] = "rate_b = ?"; $params[] = (int)($data['rate_b'] ?? 0);
        $params[] = (int)$id;

        $stmt = $this->conn->prepare("UPDATE " . $this->table_name . " SET " . implode(', ', $sets) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    public function addRate(array $data) {
        $name = trim((string)($data['name'] ?? ''));
        $keywords = trim((string)($data['keywords'] ?? ''));
        if ($name === '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $keywords))));
            $name = $parts[0] ?? 'กลุ่มค่าตอบแทน';
        }

        $columns = $this->conn->query("SHOW COLUMNS FROM " . $this->table_name)->fetchAll(PDO::FETCH_COLUMN);
        $fields = [];
        $values = [];
        $params = [];

        if (in_array('name', $columns, true)) { $fields[] = 'name'; $values[] = '?'; $params[] = $name; }
        if (in_array('group_name', $columns, true)) { $fields[] = 'group_name'; $values[] = '?'; $params[] = $name; }
        if (in_array('group_level', $columns, true)) {
            $nextLevel = (int)$this->conn->query("SELECT COALESCE(MAX(group_level), 0) + 1 FROM " . $this->table_name)->fetchColumn();
            $fields[] = 'group_level'; $values[] = '?'; $params[] = $nextLevel;
        }

        $fields = array_merge($fields, ['keywords', 'rate_r', 'rate_y', 'rate_b']);
        $values = array_merge($values, ['?', '?', '?', '?']);
        $params[] = $keywords;
        $params[] = (int)($data['rate_r'] ?? 0);
        $params[] = (int)($data['rate_y'] ?? 0);
        $params[] = (int)($data['rate_b'] ?? 0);

        if (in_array('display_order', $columns, true)) {
            $nextOrder = (int)$this->conn->query("SELECT COALESCE(MAX(display_order), 0) + 1 FROM " . $this->table_name)->fetchColumn();
            $fields[] = 'display_order'; $values[] = '?'; $params[] = $nextOrder;
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO " . $this->table_name . " (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")"
        );
        return $stmt->execute($params);
    }

    public function deleteRate($id) {
        $stmt = $this->conn->prepare("DELETE FROM " . $this->table_name . " WHERE id = ?");
        return $stmt->execute([(int)$id]);
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