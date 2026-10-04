-- Performance indexes for Roster Pro
-- Date: 2026-10-04
-- Target: MariaDB 10.11+ / XAMPP MariaDB
-- Safe to run repeatedly on MariaDB versions supporting IF NOT EXISTS.

CREATE INDEX IF NOT EXISTS idx_holidays_hospital_date
    ON holidays (hospital_id, holiday_date);

CREATE INDEX IF NOT EXISTS idx_leave_user_status_dates
    ON leave_requests (user_id, status, start_date, end_date);

CREATE INDEX IF NOT EXISTS idx_leave_status_dates
    ON leave_requests (status, start_date, end_date);

CREATE INDEX IF NOT EXISTS idx_logs_login_rate
    ON logs (user_id, action, ip_address, created_at);

CREATE INDEX IF NOT EXISTS idx_notifications_user_read_created
    ON notifications (user_id, is_read, created_at);

CREATE INDEX IF NOT EXISTS idx_shifts_hospital_date
    ON shifts (hospital_id, shift_date);

CREATE INDEX IF NOT EXISTS idx_shifts_hospital_user_date
    ON shifts (hospital_id, user_id, shift_date);

CREATE INDEX IF NOT EXISTS idx_shift_swaps_hospital_status_created
    ON shift_swaps (hospital_id, status, created_at);

CREATE INDEX IF NOT EXISTS idx_users_hospital_roster
    ON users (hospital_id, is_active, is_deleted, show_in_roster, display_order);
