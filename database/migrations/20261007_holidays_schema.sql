-- Roster Pro: upgrade legacy holidays table
-- Safe to run on databases created before holiday_type/is_active were introduced.

ALTER TABLE holidays
    ADD COLUMN IF NOT EXISTS holiday_type ENUM('REGULAR','COMPENSATION','SPECIAL')
        NOT NULL DEFAULT 'REGULAR' AFTER holiday_name,
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1)
        NOT NULL DEFAULT 1 AFTER holiday_type;

UPDATE holidays
SET holiday_type = 'REGULAR'
WHERE holiday_type IS NULL OR holiday_type = '';

UPDATE holidays
SET is_active = 1
WHERE is_active IS NULL;
