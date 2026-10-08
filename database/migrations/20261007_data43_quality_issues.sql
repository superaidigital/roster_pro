-- Data43 v2 row-level quality findings (aggregate only, no PII values).
CREATE TABLE IF NOT EXISTS data43_quality_issues (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id BIGINT UNSIGNED NOT NULL,
    file_code VARCHAR(60) NOT NULL,
    severity ENUM('ERROR','WARNING','INFO') NOT NULL,
    rule_code VARCHAR(80) NOT NULL,
    field_name VARCHAR(80) NULL,
    issue_count INT UNSIGNED NOT NULL DEFAULT 0,
    sample_rows_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_data43_quality_issue_submission (submission_id, severity),
    KEY idx_data43_quality_issue_rule (file_code, rule_code),
    CONSTRAINT fk_data43_quality_issue_submission
        FOREIGN KEY (submission_id) REFERENCES data43_submissions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
