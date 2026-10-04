-- Structured Roster Audit Trail
-- Date: 2026-10-04
-- Immutable before/after history for roster changes.

CREATE TABLE IF NOT EXISTS roster_audit_logs (
  id BIGINT NOT NULL AUTO_INCREMENT,
  hospital_id INT NOT NULL,
  month_year VARCHAR(7) NOT NULL,
  actor_user_id INT NULL,
  action_type VARCHAR(40) NOT NULL,
  entity_type VARCHAR(30) NOT NULL DEFAULT 'ROSTER',
  target_user_id INT NULL,
  shift_date DATE NULL,
  before_json LONGTEXT NULL,
  after_json LONGTEXT NULL,
  metadata_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_roster_audit_month (hospital_id, month_year, created_at),
  KEY idx_roster_audit_actor (actor_user_id, created_at),
  KEY idx_roster_audit_target (target_user_id, shift_date),
  KEY idx_roster_audit_action (action_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
