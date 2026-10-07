-- Module: นำส่งข้อมูล 43 แฟ้ม
-- Metadata-only receiving/audit layer. Raw ZIP and extracted files are deleted after processing.

CREATE TABLE IF NOT EXISTS data43_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    report_month CHAR(7) NOT NULL COMMENT 'YYYY-MM',
    original_filename VARCHAR(180) NOT NULL,
    archive_sha256 CHAR(64) NOT NULL,
    purpose_code VARCHAR(64) NOT NULL DEFAULT 'PUBLIC_HEALTH_REPORTING',
    standard_version VARCHAR(20) NULL,
    profile_code VARCHAR(40) NULL,
    expected_files SMALLINT UNSIGNED NOT NULL DEFAULT 45,
    detected_files SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    total_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('PROCESSING','COMPLETE','INCOMPLETE','FAILED') NOT NULL DEFAULT 'PROCESSING',
    uploaded_by INT NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    error_summary VARCHAR(500) NULL,
    client_ip_hash CHAR(64) NULL,
    PRIMARY KEY (id),
    KEY idx_data43_hospital_month (hospital_id, report_month, uploaded_at),
    KEY idx_data43_status (status, uploaded_at),
    KEY idx_data43_uploader (uploaded_by, uploaded_at),
    CONSTRAINT fk_data43_submission_hospital
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_data43_submission_user
        FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data43_submission_files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id BIGINT UNSIGNED NOT NULL,
    file_code VARCHAR(100) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    extension VARCHAR(10) NOT NULL,
    file_sha256 CHAR(64) NOT NULL,
    row_count BIGINT UNSIGNED NULL,
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('VALID','SKIPPED','ERROR') NOT NULL DEFAULT 'VALID',
    error_message VARCHAR(300) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_data43_file_submission (submission_id, file_code),
    CONSTRAINT fk_data43_file_submission
        FOREIGN KEY (submission_id) REFERENCES data43_submissions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
