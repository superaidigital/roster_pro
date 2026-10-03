<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$db = (new Database())->getConnection();

function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function addColumnIfMissing(PDO $db, string $table, string $column, string $definition): void {
    if (columnExists($db, $table, $column)) {
        echo "SKIP  {$table}.{$column} already exists\n";
        return;
    }

    $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
    $db->exec($sql);
    echo "ADD   {$table}.{$column}\n";
}

echo "Roster Pro legacy database migration\n";
echo "Database: " . (getenv('DB_NAME') ?: 'roster_pro_db') . "\n\n";

$db->beginTransaction();
try {
    // users
    addColumnIfMissing($db, 'users', 'phone', "VARCHAR(50) NULL");
    addColumnIfMissing($db, 'users', 'type', "VARCHAR(100) NULL");
    addColumnIfMissing($db, 'users', 'position', "VARCHAR(100) NULL");
    addColumnIfMissing($db, 'users', 'color_theme', "VARCHAR(20) DEFAULT 'primary'");
    addColumnIfMissing($db, 'users', 'employee_type', "VARCHAR(100) NOT NULL DEFAULT 'ข้าราชการ/พนักงานท้องถิ่น'");
    addColumnIfMissing($db, 'users', 'start_date', "DATE NULL");
    addColumnIfMissing($db, 'users', 'sort_order', "INT DEFAULT 0");
    addColumnIfMissing($db, 'users', 'id_card', "VARCHAR(13) NULL");
    addColumnIfMissing($db, 'users', 'position_number', "VARCHAR(50) NULL");
    addColumnIfMissing($db, 'users', 'pay_rate_id', "INT NULL");
    addColumnIfMissing($db, 'users', 'is_active', "TINYINT(1) NOT NULL DEFAULT 1");
    addColumnIfMissing($db, 'users', 'inactive_date', "DATE NULL");
    addColumnIfMissing($db, 'users', 'inactive_reason', "VARCHAR(100) NULL");
    addColumnIfMissing($db, 'users', 'inactive_note', "TEXT NULL");
    addColumnIfMissing($db, 'users', 'display_order', "INT NOT NULL DEFAULT 0");
    addColumnIfMissing($db, 'users', 'deleted_at', "DATETIME NULL DEFAULT NULL");
    addColumnIfMissing($db, 'users', 'is_deleted', "TINYINT(1) DEFAULT 0");
    addColumnIfMissing($db, 'users', 'show_in_roster', "TINYINT(1) DEFAULT 1");
    addColumnIfMissing($db, 'users', 'signature_path', "LONGTEXT NULL");

    // hospitals
    addColumnIfMissing($db, 'hospitals', 'hospital_code', "VARCHAR(10) NULL");
    addColumnIfMissing($db, 'hospitals', 'short_name', "VARCHAR(100) NULL");
    addColumnIfMissing($db, 'hospitals', 'is_active', "TINYINT(1) NOT NULL DEFAULT 1");
    addColumnIfMissing($db, 'hospitals', 'address', "VARCHAR(255) NULL");
    addColumnIfMissing($db, 'hospitals', 'sub_district', "VARCHAR(100) NULL");
    addColumnIfMissing($db, 'hospitals', 'district', "VARCHAR(100) NULL");
    addColumnIfMissing($db, 'hospitals', 'province', "VARCHAR(100) NULL");
    addColumnIfMissing($db, 'hospitals', 'zipcode', "VARCHAR(10) NULL");
    addColumnIfMissing($db, 'hospitals', 'latitude', "VARCHAR(50) NULL");
    addColumnIfMissing($db, 'hospitals', 'longitude', "VARCHAR(50) NULL");
    addColumnIfMissing($db, 'hospitals', 'hospital_size', "VARCHAR(10) DEFAULT 'S'");
    addColumnIfMissing($db, 'hospitals', 'phone', "VARCHAR(50) NULL");
    addColumnIfMissing($db, 'hospitals', 'morning_shift', "VARCHAR(50) DEFAULT '08:30 - 16:30'");
    addColumnIfMissing($db, 'hospitals', 'afternoon_shift', "VARCHAR(50) DEFAULT '16:30 - 00:30'");
    addColumnIfMissing($db, 'hospitals', 'night_shift', "VARCHAR(50) DEFAULT '00:30 - 08:30'");
    addColumnIfMissing($db, 'hospitals', 'email', "VARCHAR(100) NULL");
    addColumnIfMissing($db, 'hospitals', 'logo', "VARCHAR(255) NULL");
    addColumnIfMissing($db, 'hospitals', 'director_name', "VARCHAR(255) NULL");
    addColumnIfMissing($db, 'hospitals', 'shift_m_start', "TIME DEFAULT '08:00:00'");
    addColumnIfMissing($db, 'hospitals', 'shift_m_end', "TIME DEFAULT '16:00:00'");
    addColumnIfMissing($db, 'hospitals', 'shift_a_start', "TIME DEFAULT '16:00:00'");
    addColumnIfMissing($db, 'hospitals', 'shift_a_end', "TIME DEFAULT '00:00:00'");
    addColumnIfMissing($db, 'hospitals', 'shift_n_start', "TIME DEFAULT '00:00:00'");
    addColumnIfMissing($db, 'hospitals', 'shift_n_end', "TIME DEFAULT '08:00:00'");
    addColumnIfMissing($db, 'hospitals', 'display_order', "INT NOT NULL DEFAULT 0");
    addColumnIfMissing($db, 'hospitals', 'deleted_at', "DATETIME NULL DEFAULT NULL");

    // Fill short_name for legacy rows so dashboards have a label immediately.
    $db->exec("UPDATE hospitals SET short_name = name WHERE (short_name IS NULL OR short_name = '')");

    // Normalize legacy leave request status column once during migration.
    if ($db->query("SHOW TABLES LIKE 'leave_requests'")->fetchColumn()) {
        $stmtStatus = $db->query("SHOW COLUMNS FROM leave_requests LIKE 'status'");
        $statusColumn = $stmtStatus ? $stmtStatus->fetch(PDO::FETCH_ASSOC) : false;
        if ($statusColumn && stripos((string)$statusColumn['Type'], 'varchar(50)') === false) {
            $db->exec("ALTER TABLE leave_requests MODIFY COLUMN status VARCHAR(50) DEFAULT 'PENDING'");
            echo "ALTER leave_requests.status -> VARCHAR(50)\n";
        }
        $db->exec("UPDATE leave_requests SET status = 'PENDING' WHERE status IS NULL OR status = ''");
    }

    // roster_status
    if ($db->query("SHOW TABLES LIKE 'roster_status'")->fetchColumn()) {
        addColumnIfMissing($db, 'roster_status', 'reviewer_id', "INT NULL");
        addColumnIfMissing($db, 'roster_status', 'remark', "TEXT NULL");
        addColumnIfMissing($db, 'roster_status', 'pay_summary', "TEXT NULL");
        addColumnIfMissing($db, 'roster_status', 'submitted_at', "DATETIME NULL");
        addColumnIfMissing($db, 'roster_status', 'updated_at', "DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        addColumnIfMissing($db, 'roster_status', 'creator_id', "INT NULL");
        addColumnIfMissing($db, 'roster_status', 'director_id', "INT NULL");
    }

    // pay_rates
    if (!$db->query("SHOW TABLES LIKE 'pay_rates'")->fetchColumn()) {
        $db->exec(
            "CREATE TABLE pay_rates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                group_name VARCHAR(100) NULL,
                keywords TEXT NULL,
                rate_y DECIMAL(10,2) NOT NULL DEFAULT 0,
                rate_b DECIMAL(10,2) NOT NULL DEFAULT 0,
                rate_r DECIMAL(10,2) NOT NULL DEFAULT 0,
                display_order INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "CREATE pay_rates table\n";
    }

    if ($db->query("SHOW TABLES LIKE 'pay_rates'")->fetchColumn()) {
        addColumnIfMissing($db, 'pay_rates', 'group_name', "VARCHAR(100) NULL");
        addColumnIfMissing($db, 'pay_rates', 'keywords', "TEXT NULL");
        addColumnIfMissing($db, 'pay_rates', 'rate_y', "DECIMAL(10,2) NOT NULL DEFAULT 0");
        addColumnIfMissing($db, 'pay_rates', 'rate_b', "DECIMAL(10,2) NOT NULL DEFAULT 0");
        addColumnIfMissing($db, 'pay_rates', 'rate_r', "DECIMAL(10,2) NOT NULL DEFAULT 0");
        addColumnIfMissing($db, 'pay_rates', 'display_order', "INT NOT NULL DEFAULT 0");
    }

    // notification + shift swap compatibility
    $db->exec(
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            type VARCHAR(50) DEFAULT 'INFO',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            link VARCHAR(255) NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_notifications_user (user_id),
            KEY idx_notifications_read (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS shift_swaps (
            id INT NOT NULL AUTO_INCREMENT,
            hospital_id INT NOT NULL,
            requestor_id INT NOT NULL,
            requestor_date DATE NOT NULL,
            requestor_shift VARCHAR(50) NOT NULL,
            target_user_id INT NOT NULL,
            target_date DATE NOT NULL,
            target_shift VARCHAR(50) NOT NULL,
            reason TEXT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'PENDING_TARGET',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_swap_hospital (hospital_id),
            KEY idx_swap_requestor (requestor_id),
            KEY idx_swap_target (target_user_id),
            KEY idx_swap_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "ENSURE notifications + shift_swaps tables\n";

    if ($db->query("SHOW TABLES LIKE 'notifications'")->fetchColumn()) {
        addColumnIfMissing($db, 'notifications', 'type', "VARCHAR(50) DEFAULT 'INFO'");
        addColumnIfMissing($db, 'notifications', 'title', "VARCHAR(255) NULL");
        addColumnIfMissing($db, 'notifications', 'message', "TEXT NULL");
        addColumnIfMissing($db, 'notifications', 'link', "VARCHAR(255) NULL");
        addColumnIfMissing($db, 'notifications', 'is_read', "TINYINT(1) DEFAULT 0");
        addColumnIfMissing($db, 'notifications', 'created_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
    }

    if ($db->query("SHOW TABLES LIKE 'shift_swaps'")->fetchColumn()) {
        addColumnIfMissing($db, 'shift_swaps', 'hospital_id', "INT NULL");
        addColumnIfMissing($db, 'shift_swaps', 'requestor_id', "INT NULL");
        addColumnIfMissing($db, 'shift_swaps', 'requestor_date', "DATE NULL");
        addColumnIfMissing($db, 'shift_swaps', 'requestor_shift', "VARCHAR(50) NULL");
        addColumnIfMissing($db, 'shift_swaps', 'target_user_id', "INT NULL");
        addColumnIfMissing($db, 'shift_swaps', 'target_date', "DATE NULL");
        addColumnIfMissing($db, 'shift_swaps', 'target_shift', "VARCHAR(50) NULL");
        addColumnIfMissing($db, 'shift_swaps', 'reason', "TEXT NULL");
        addColumnIfMissing($db, 'shift_swaps', 'status', "VARCHAR(50) DEFAULT 'PENDING_TARGET'");
        addColumnIfMissing($db, 'shift_swaps', 'created_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
        addColumnIfMissing($db, 'shift_swaps', 'updated_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    // field visit / community work
    $db->exec(
        "CREATE TABLE IF NOT EXISTS field_visits (
            id BIGINT NOT NULL AUTO_INCREMENT,
            hospital_id INT NOT NULL,
            created_by INT NOT NULL,
            visit_date DATE NOT NULL,
            patient_ref VARCHAR(50) NOT NULL,
            patient_name VARCHAR(150) NULL,
            patient_age SMALLINT NULL,
            visit_type VARCHAR(40) NOT NULL DEFAULT 'HOME_VISIT',
            chief_concern VARCHAR(255) NULL,
            systolic SMALLINT NULL,
            diastolic SMALLINT NULL,
            pulse SMALLINT NULL,
            temperature DECIMAL(4,1) NULL,
            spo2 TINYINT UNSIGNED NULL,
            weight DECIMAL(6,2) NULL,
            height DECIMAL(6,2) NULL,
            symptoms TEXT NULL,
            assessment TEXT NULL,
            care_plan TEXT NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            accuracy_m DECIMAL(10,2) NULL,
            address_note VARCHAR(255) NULL,
            photo_consent TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_field_hospital_date (hospital_id, visit_date),
            KEY idx_field_creator_date (created_by, visit_date),
            KEY idx_field_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS field_visit_photos (
            id BIGINT NOT NULL AUTO_INCREMENT,
            field_visit_id BIGINT NOT NULL,
            stored_path VARCHAR(500) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_field_photo_visit (field_visit_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "ENSURE field_visits + field_visit_photos tables\n";

    $db->exec("UPDATE users SET is_deleted = 0 WHERE is_deleted IS NULL");
    $db->exec("UPDATE users SET is_active = 1 WHERE is_active IS NULL");
    $db->exec("UPDATE users SET show_in_roster = 1 WHERE show_in_roster IS NULL");
    $db->exec("UPDATE hospitals SET is_active = 1 WHERE is_active IS NULL");

    $db->commit();
    echo "\nMigration completed successfully.\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "\nMigration failed: " . $e->getMessage() . "\n");
    exit(1);
}
