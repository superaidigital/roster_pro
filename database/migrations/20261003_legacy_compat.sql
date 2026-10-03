-- Roster Pro legacy database compatibility migration
-- Safe for existing XAMPP/MariaDB databases. Adds only missing columns.
-- Generated 2026-10-03.

SET NAMES utf8mb4;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL AFTER name,
    ADD COLUMN IF NOT EXISTS type VARCHAR(100) NULL AFTER start_date,
    ADD COLUMN IF NOT EXISTS position VARCHAR(100) NULL AFTER inactive_note,
    ADD COLUMN IF NOT EXISTS color_theme VARCHAR(20) DEFAULT 'primary' AFTER position_number,
    ADD COLUMN IF NOT EXISTS employee_type VARCHAR(100) NOT NULL DEFAULT 'ข้าราชการ/พนักงานท้องถิ่น' AFTER position,
    ADD COLUMN IF NOT EXISTS start_date DATE NULL AFTER employee_type,
    ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0 AFTER color_theme,
    ADD COLUMN IF NOT EXISTS id_card VARCHAR(13) NULL AFTER display_order,
    ADD COLUMN IF NOT EXISTS position_number VARCHAR(50) NULL AFTER type,
    ADD COLUMN IF NOT EXISTS pay_rate_id INT NULL AFTER id_card,
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
    ADD COLUMN IF NOT EXISTS inactive_date DATE NULL AFTER is_active,
    ADD COLUMN IF NOT EXISTS inactive_reason VARCHAR(100) NULL AFTER inactive_date,
    ADD COLUMN IF NOT EXISTS inactive_note TEXT NULL AFTER inactive_reason,
    ADD COLUMN IF NOT EXISTS display_order INT NOT NULL DEFAULT 0 AFTER created_at,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL AFTER pay_rate_id,
    ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0 AFTER deleted_at,
    ADD COLUMN IF NOT EXISTS show_in_roster TINYINT(1) DEFAULT 1 AFTER is_deleted,
    ADD COLUMN IF NOT EXISTS signature_path LONGTEXT NULL AFTER show_in_roster;

ALTER TABLE hospitals
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS display_order INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;

ALTER TABLE pay_rates
    ADD COLUMN IF NOT EXISTS group_name VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS keywords TEXT NULL,
    ADD COLUMN IF NOT EXISTS rate_y DECIMAL(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS rate_b DECIMAL(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS rate_r DECIMAL(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS display_order INT NOT NULL DEFAULT 0;

-- Normalize NULL flags on existing records.
UPDATE users SET is_deleted = 0 WHERE is_deleted IS NULL;
UPDATE users SET is_active = 1 WHERE is_active IS NULL;
UPDATE users SET show_in_roster = 1 WHERE show_in_roster IS NULL;
UPDATE hospitals SET is_active = 1 WHERE is_active IS NULL;
