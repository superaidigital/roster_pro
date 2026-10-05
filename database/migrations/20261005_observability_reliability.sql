-- Production Observability & Reliability
-- Date: 2026-10-05
-- Durable event monitoring, retryable background jobs, and health snapshots.

CREATE TABLE IF NOT EXISTS observability_events (
  id BIGINT NOT NULL AUTO_INCREMENT,
  fingerprint CHAR(64) NOT NULL,
  severity VARCHAR(20) NOT NULL DEFAULT 'ERROR',
  category VARCHAR(50) NOT NULL DEFAULT 'APPLICATION',
  message VARCHAR(1000) NOT NULL,
  exception_class VARCHAR(190) NULL,
  source_file VARCHAR(500) NULL,
  source_line INT NULL,
  route VARCHAR(190) NULL,
  request_id VARCHAR(64) NULL,
  user_id INT NULL,
  hospital_id INT NULL,
  context_json LONGTEXT NULL,
  occurrence_count INT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  resolved_by INT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_observability_fingerprint (fingerprint),
  KEY idx_observability_status_seen (status, last_seen_at),
  KEY idx_observability_severity_seen (severity, last_seen_at),
  KEY idx_observability_category_seen (category, last_seen_at),
  KEY idx_observability_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS background_jobs (
  id BIGINT NOT NULL AUTO_INCREMENT,
  job_type VARCHAR(80) NOT NULL,
  dedupe_key VARCHAR(190) NULL,
  payload_json LONGTEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
  priority SMALLINT NOT NULL DEFAULT 100,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  locked_at DATETIME NULL,
  lock_token CHAR(32) NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_background_job_dedupe (dedupe_key),
  KEY idx_background_jobs_pick (status, available_at, priority, id),
  KEY idx_background_jobs_failed (status, updated_at),
  KEY idx_background_jobs_type (job_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_health_snapshots (
  id BIGINT NOT NULL AUTO_INCREMENT,
  overall_status VARCHAR(20) NOT NULL,
  db_status VARCHAR(20) NOT NULL,
  migration_pending INT UNSIGNED NOT NULL DEFAULT 0,
  migration_blocking INT UNSIGNED NOT NULL DEFAULT 0,
  queue_pending INT UNSIGNED NOT NULL DEFAULT 0,
  queue_failed INT UNSIGNED NOT NULL DEFAULT 0,
  open_errors_24h INT UNSIGNED NOT NULL DEFAULT 0,
  disk_free_mb BIGINT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_health_snapshot_created (created_at),
  KEY idx_health_snapshot_status (overall_status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
