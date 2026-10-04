-- Roster Snapshot / Version History
-- Date: 2026-10-04
-- Stores immutable point-in-time copies of one hospital/month roster.

CREATE TABLE IF NOT EXISTS roster_snapshots (
  id BIGINT NOT NULL AUTO_INCREMENT,
  hospital_id INT NOT NULL,
  month_year VARCHAR(7) NOT NULL,
  snapshot_kind VARCHAR(30) NOT NULL DEFAULT 'MANUAL',
  label VARCHAR(160) NULL,
  status_snapshot VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
  shift_count INT NOT NULL DEFAULT 0,
  shifts_json LONGTEXT NOT NULL,
  checksum CHAR(64) NOT NULL,
  is_protected TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_roster_snapshot_month (hospital_id, month_year, created_at),
  KEY idx_roster_snapshot_creator (created_by, created_at),
  KEY idx_roster_snapshot_checksum (hospital_id, month_year, checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
