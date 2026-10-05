-- Roster Pro Security/Performance migration
-- Apply once to production after taking a verified backup.

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identity_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_identity_time (identity_hash, attempted_at),
    KEY idx_login_ip_time (ip_hash, attempted_at),
    KEY idx_login_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE shifts
    ADD KEY idx_shifts_hospital_date (hospital_id, shift_date);

ALTER TABLE leave_requests
    ADD KEY idx_leave_user_status_dates (user_id, status, start_date, end_date);

ALTER TABLE notifications
    ADD KEY idx_notifications_user_read_created (user_id, is_read, created_at);

ALTER TABLE system_logs
    ADD KEY idx_system_logs_created_at (created_at),
    ADD KEY idx_system_logs_user_created (user_id, created_at);
