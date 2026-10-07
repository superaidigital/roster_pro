-- MOPH health-data structure Version 2.4.1 metadata/profile upgrade.
-- The module keeps the familiar "43 แฟ้ม" name, while the attached official manual
-- contains 52 structures. For the รพ.สต. profile, 45 structures are marked as
-- recordable by รพ.สต.; 7 hospital-only structures are excluded from completeness.

ALTER TABLE data43_submissions
    ADD COLUMN IF NOT EXISTS standard_version VARCHAR(20) NULL AFTER purpose_code,
    ADD COLUMN IF NOT EXISTS profile_code VARCHAR(40) NULL AFTER standard_version;

ALTER TABLE data43_submissions
    MODIFY expected_files SMALLINT UNSIGNED NOT NULL DEFAULT 45;
