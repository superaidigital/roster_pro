CREATE TABLE IF NOT EXISTS system_settings_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id INT NOT NULL,
  section_name VARCHAR(40) NOT NULL,
  setting_key VARCHAR(100) NOT NULL,
  previous_value TEXT NULL,
  current_value TEXT NULL,
  is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_settings_audit_date (changed_at),
  KEY idx_settings_audit_actor (actor_user_id,changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
