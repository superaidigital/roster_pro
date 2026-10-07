-- Data43 v2 quality summary/audit layer.
-- Stores aggregate validation results only; no raw PID/CID/HID values.

CREATE TABLE IF NOT EXISTS data43_quality_summary (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id BIGINT UNSIGNED NOT NULL,
    standard_version VARCHAR(20) NOT NULL,
    profile_code VARCHAR(40) NOT NULL,
    catalog_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    expected_files SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    detected_expected_files SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    linked_people BIGINT UNSIGNED NOT NULL DEFAULT 0,
    linked_homes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    unresolved_people BIGINT UNSIGNED NOT NULL DEFAULT 0,
    address_only_people BIGINT UNSIGNED NOT NULL DEFAULT 0,
    unknown_files_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    header_issue_files SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    missing_codes_json JSON NULL,
    unknown_files_json JSON NULL,
    header_issues_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_data43_quality_submission (submission_id),
    CONSTRAINT fk_data43_quality_submission
        FOREIGN KEY (submission_id) REFERENCES data43_submissions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
