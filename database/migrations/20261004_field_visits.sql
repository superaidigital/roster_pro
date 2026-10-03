-- Field Visit / Community Work module
-- Generated 2026-10-04

CREATE TABLE IF NOT EXISTS field_visits (
  id BIGINT NOT NULL AUTO_INCREMENT,
  hospital_id INT NOT NULL,
  created_by INT NOT NULL,
  visit_date DATE NOT NULL,
  patient_ref VARCHAR(50) NOT NULL,
  patient_name VARCHAR(150) NULL,
  patient_age SMALLINT NULL,
  visit_type VARCHAR(40) NOT NULL DEFAULT 'HOME_VISIT',
  chief_concern VARCHAR(255) NULL,
  systolic SMALLINT NULL,
  diastolic SMALLINT NULL,
  pulse SMALLINT NULL,
  temperature DECIMAL(4,1) NULL,
  spo2 TINYINT UNSIGNED NULL,
  weight DECIMAL(6,2) NULL,
  height DECIMAL(6,2) NULL,
  symptoms TEXT NULL,
  assessment TEXT NULL,
  care_plan TEXT NULL,
  risk_level VARCHAR(20) NOT NULL DEFAULT 'ROUTINE',
  follow_up_date DATE NULL,
  follow_up_status VARCHAR(20) NOT NULL DEFAULT 'NONE',
  referral_required TINYINT(1) NOT NULL DEFAULT 0,
  referral_note VARCHAR(500) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  accuracy_m DECIMAL(10,2) NULL,
  address_note VARCHAR(255) NULL,
  photo_consent TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_field_hospital_date (hospital_id, visit_date),
  KEY idx_field_creator_date (created_by, visit_date),
  KEY idx_field_status (status),
  KEY idx_field_risk (risk_level),
  KEY idx_field_followup (follow_up_status, follow_up_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS field_visit_photos (
  id BIGINT NOT NULL AUTO_INCREMENT,
  field_visit_id BIGINT NOT NULL,
  stored_path VARCHAR(500) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_field_photo_visit (field_visit_id),
  CONSTRAINT fk_field_photo_visit
    FOREIGN KEY (field_visit_id) REFERENCES field_visits(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
