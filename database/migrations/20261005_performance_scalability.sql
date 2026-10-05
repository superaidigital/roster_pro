-- Performance & Scalability Phase 7
-- Date: 2026-10-05
-- Adds indexes for range-based dashboard, logs, workflow and HR queries.

CREATE INDEX IF NOT EXISTS idx_shifts_date_hospital_user
    ON shifts (shift_date, hospital_id, user_id);

CREATE INDEX IF NOT EXISTS idx_logs_created_id
    ON logs (created_at, id);

CREATE INDEX IF NOT EXISTS idx_logs_created_action_user
    ON logs (created_at, action, user_id);

CREATE INDEX IF NOT EXISTS idx_roster_status_month_status_hospital
    ON roster_status (month_year, status, hospital_id);

CREATE INDEX IF NOT EXISTS idx_shift_swaps_status_hospital_created
    ON shift_swaps (status, hospital_id, created_at);

CREATE INDEX IF NOT EXISTS idx_users_active_scope
    ON users (is_deleted, deleted_at, hospital_id, role);

CREATE INDEX IF NOT EXISTS idx_employee_licenses_status_expire_user
    ON employee_licenses (status, expire_date, user_id);
