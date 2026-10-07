-- Phase 12.1: Leave Form Template Manager
-- Run once on the Roster Pro database.

CREATE TABLE IF NOT EXISTS leave_form_templates (
    id INT NOT NULL AUTO_INCREMENT,
    template_name VARCHAR(180) NOT NULL,
    leave_type_id INT NULL,
    hospital_id INT NULL,
    file_type ENUM('DOCX','PDF') NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    version INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    mapping_status ENUM('READY','PENDING') NOT NULL DEFAULT 'PENDING',
    notes VARCHAR(500) NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_leave_template_type (leave_type_id, is_active),
    KEY idx_leave_template_hospital (hospital_id, is_active),
    CONSTRAINT fk_leave_template_leave_type
        FOREIGN KEY (leave_type_id) REFERENCES leave_quotas(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_leave_template_hospital
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_form_fields (
    id INT NOT NULL AUTO_INCREMENT,
    template_id INT NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    page_number INT NOT NULL DEFAULT 1,
    x DECIMAL(10,2) NULL,
    y DECIMAL(10,2) NULL,
    width DECIMAL(10,2) NULL,
    height DECIMAL(10,2) NULL,
    font_size DECIMAL(6,2) NULL,
    font_family VARCHAR(100) NULL,
    alignment ENUM('LEFT','CENTER','RIGHT') NOT NULL DEFAULT 'LEFT',
    format_rule VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_leave_form_field (template_id, field_key, page_number),
    CONSTRAINT fk_leave_form_field_template
        FOREIGN KEY (template_id) REFERENCES leave_form_templates(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_generated_documents (
    id INT NOT NULL AUTO_INCREMENT,
    leave_request_id INT NOT NULL,
    template_id INT NOT NULL,
    template_version INT NOT NULL,
    document_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    document_hash CHAR(64) NOT NULL,
    document_status ENUM('DRAFT','FINAL') NOT NULL DEFAULT 'DRAFT',
    generated_by INT NOT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalized_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_leave_generated_request (leave_request_id, generated_at),
    CONSTRAINT fk_leave_generated_request
        FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_leave_generated_template
        FOREIGN KEY (template_id) REFERENCES leave_form_templates(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
