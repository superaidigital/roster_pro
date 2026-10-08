-- Run AFTER 20261007_data43_submissions.sql and 20261007_data43_spatial_analytics.sql
-- Administrative boundaries must come from a verified Thai administrative GeoJSON dataset.
CREATE TABLE IF NOT EXISTS data43_geo_boundaries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  area_level ENUM('PROVINCE','AMPUR','TAMBON') NOT NULL,
  area_code CHAR(6) NOT NULL,
  parent_code VARCHAR(4) NULL,
  name_th VARCHAR(180) NOT NULL,
  geometry_json MEDIUMTEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_data43_geo_level_code (area_level,area_code),
  KEY idx_data43_geo_parent (area_level,parent_code),
  CONSTRAINT chk_data43_geo_json CHECK (JSON_VALID(geometry_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One facility may be responsible for multiple tambons; no individual health data is stored here.
CREATE TABLE IF NOT EXISTS data43_hospital_service_areas (
  hospital_id INT NOT NULL,
  tambon_code CHAR(6) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (hospital_id,tambon_code),
  KEY idx_data43_service_area (tambon_code),
  CONSTRAINT fk_data43_service_hospital FOREIGN KEY (hospital_id)
    REFERENCES hospitals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
