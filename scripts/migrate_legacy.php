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
    addColumnIfMissing($db, 'hospitals', 'is_active', "TINYINT(1) NOT NULL DEFAULT 1");
    addColumnIfMissing($db, 'hospitals', 'display_order', "INT NOT NULL DEFAULT 0");
    addColumnIfMissing($db, 'hospitals', 'deleted_at', "DATETIME NULL DEFAULT NULL");

    // pay_rates
    if ($db->query("SHOW TABLES LIKE 'pay_rates'")->fetchColumn()) {
        addColumnIfMissing($db, 'pay_rates', 'group_name', "VARCHAR(100) NULL");
        addColumnIfMissing($db, 'pay_rates', 'keywords', "TEXT NULL");
        addColumnIfMissing($db, 'pay_rates', 'rate_y', "DECIMAL(10,2) NOT NULL DEFAULT 0");
        addColumnIfMissing($db, 'pay_rates', 'rate_b', "DECIMAL(10,2) NOT NULL DEFAULT 0");
        addColumnIfMissing($db, 'pay_rates', 'rate_r', "DECIMAL(10,2) NOT NULL DEFAULT 0");
        addColumnIfMissing($db, 'pay_rates', 'display_order', "INT NOT NULL DEFAULT 0");
    }

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
