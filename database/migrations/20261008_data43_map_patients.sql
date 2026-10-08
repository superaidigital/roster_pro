-- Data43 map patient details: independent, least-privilege permissions.
-- Never grant by roster role alone. User must have an explicit active per-facility grant.
CREATE TABLE IF NOT EXISTS data43_patient_view_grants (
  user_id INT NOT NULL,
  hospital_id INT NOT NULL,
  purpose_code ENUM('CARE','PUBLIC_HEALTH') NOT NULL,
  granted_by INT NOT NULL,
  granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at TIMESTAMP NULL,
  PRIMARY KEY (user_id,hospital_id,purpose_code),
  KEY idx_d43_grants_hospital (hospital_id,purpose_code,revoked_at),
  CONSTRAINT fk_d43_patient_grant_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_d43_patient_grant_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
  CONSTRAINT fk_d43_patient_grant_admin FOREIGN KEY (granted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Derived from encrypted ADDRESS registry payload, not from raw ZIP aggregates.
-- Linking uses data43_records.pid_ref, already scoped by hospital, never CID.
CREATE TABLE IF NOT EXISTS data43_patient_area_index (
  address_record_id BIGINT UNSIGNED NOT NULL,
  hospital_id INT NOT NULL,
  area_code CHAR(6) NOT NULL,
  indexed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (address_record_id),
  KEY idx_d43_patient_area (hospital_id,area_code),
  CONSTRAINT fk_d43_patient_area_record FOREIGN KEY (address_record_id) REFERENCES data43_records(id) ON DELETE CASCADE,
  CONSTRAINT fk_d43_patient_area_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS data43_patient_access_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  hospital_id INT NOT NULL,
  area_code VARCHAR(6) NOT NULL,
  purpose_code ENUM('CARE','PUBLIC_HEALTH') NOT NULL,
  action_code ENUM('AREA_LIST','PATIENT_PROFILE') NOT NULL,
  person_record_id BIGINT UNSIGNED NULL,
  accessed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_d43_patient_audit_user (user_id,accessed_at),
  KEY idx_d43_patient_audit_hospital (hospital_id,accessed_at),
  CONSTRAINT fk_d43_patient_audit_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_d43_patient_audit_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
