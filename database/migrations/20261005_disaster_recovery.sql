-- Disaster Recovery & Business Continuity
-- Date: 2026-10-05

CREATE TABLE IF NOT EXISTS disaster_recovery_drills (
  id BIGINT NOT NULL AUTO_INCREMENT,
  backup_filename VARCHAR(255) NOT NULL,
  backup_sha256 CHAR(64) NOT NULL,
  backup_created_at DATETIME NULL,
  source_database VARCHAR(64) NOT NULL,
  restore_database VARCHAR(64) NOT NULL,
  status VARCHAR(20) NOT NULL,
  started_at DATETIME NOT NULL,
  completed_at DATETIME NOT NULL,
  rpo_seconds INT UNSIGNED NULL,
  rto_ms INT UNSIGNED NULL,
  tables_verified INT UNSIGNED NOT NULL DEFAULT 0,
  critical_tables_verified INT UNSIGNED NOT NULL DEFAULT 0,
  failure_code VARCHAR(80) NULL,
  failure_message VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dr_drills_completed (completed_at),
  KEY idx_dr_drills_status_completed (status, completed_at),
  KEY idx_dr_drills_backup_created (backup_created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE system_health_snapshots
  ADD COLUMN IF NOT EXISTS dr_status VARCHAR(20) NULL AFTER disk_free_mb,
  ADD COLUMN IF NOT EXISTS dr_age_hours INT UNSIGNED NULL AFTER dr_status,
  ADD COLUMN IF NOT EXISTS dr_rpo_seconds INT UNSIGNED NULL AFTER dr_age_hours,
  ADD COLUMN IF NOT EXISTS dr_rto_ms INT UNSIGNED NULL AFTER dr_rpo_seconds;
