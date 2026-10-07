-- Phase 13.4: Spatial analytics for 43-file submissions
-- Stores aggregate statistics only; no citizen identifiers or household-level coordinates.

CREATE TABLE IF NOT EXISTS data43_area_metrics (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id BIGINT UNSIGNED NOT NULL,
    hospital_id INT NOT NULL,
    report_month CHAR(7) NOT NULL,
    area_level ENUM('AMPUR','TAMBON','VILLAGE') NOT NULL,
    changwat_code CHAR(2) NULL,
    ampur_code CHAR(2) NULL,
    tambon_code CHAR(2) NULL,
    village_code CHAR(2) NULL,
    source_file_code VARCHAR(100) NOT NULL,
    metric_code VARCHAR(120) NOT NULL,
    metric_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    centroid_lat DECIMAL(10,7) NULL,
    centroid_lng DECIMAL(10,7) NULL,
    geo_point_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_data43_area_metric (
        submission_id,
        area_level,
        changwat_code,
        ampur_code,
        tambon_code,
        village_code,
        source_file_code,
        metric_code
    ),
    KEY idx_data43_area_month (report_month, area_level),
    KEY idx_data43_area_hospital (hospital_id, report_month),
    KEY idx_data43_area_metric_code (metric_code, report_month),
    CONSTRAINT fk_data43_area_submission
        FOREIGN KEY (submission_id) REFERENCES data43_submissions(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_data43_area_hospital
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
