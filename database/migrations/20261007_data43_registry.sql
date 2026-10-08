-- Data43 operational registry for manual survey/data-entry workflows.
-- Sensitive field payloads are encrypted at application level.
-- Search tokens are blind HMAC prefixes; CID is never stored in plaintext columns.

CREATE TABLE IF NOT EXISTS data43_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    file_code VARCHAR(60) NOT NULL,
    record_key_hash CHAR(64) NOT NULL,
    pid_ref VARCHAR(30) NULL,
    hid_ref VARCHAR(30) NULL,
    cid_hash CHAR(64) NULL,
    payload_ciphertext LONGTEXT NOT NULL,
    payload_nonce VARCHAR(128) NOT NULL,
    payload_algorithm VARCHAR(32) NOT NULL DEFAULT 'SODIUM_SECRETBOX',
    payload_sha256 CHAR(64) NOT NULL,
    created_by INT NOT NULL,
    updated_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_data43_record_key (hospital_id, file_code, record_key_hash),
    KEY idx_data43_record_pid (hospital_id, pid_ref, file_code),
    KEY idx_data43_record_hid (hospital_id, hid_ref, file_code),
    KEY idx_data43_record_cid (hospital_id, cid_hash),
    KEY idx_data43_record_file (hospital_id, file_code, deleted_at),
    CONSTRAINT fk_data43_record_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE RESTRICT,
    CONSTRAINT fk_data43_record_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_data43_record_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data43_search_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    record_id BIGINT UNSIGNED NOT NULL,
    token_type ENUM('PERSON','HOME') NOT NULL,
    token_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_data43_search_token (record_id, token_type, token_hash),
    KEY idx_data43_search_lookup (token_type, token_hash),
    CONSTRAINT fk_data43_search_record FOREIGN KEY (record_id) REFERENCES data43_records(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data43_record_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    record_id BIGINT UNSIGNED NULL,
    hospital_id INT NOT NULL,
    file_code VARCHAR(60) NOT NULL,
    action ENUM('CREATE','UPDATE','DELETE','EXPORT','VIEW') NOT NULL,
    actor_user_id INT NOT NULL,
    record_key_hash CHAR(64) NULL,
    changed_fields_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_data43_audit_hospital (hospital_id, created_at),
    KEY idx_data43_audit_record (record_id, created_at),
    CONSTRAINT fk_data43_audit_record FOREIGN KEY (record_id) REFERENCES data43_records(id) ON DELETE SET NULL,
    CONSTRAINT fk_data43_audit_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE RESTRICT,
    CONSTRAINT fk_data43_audit_user FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
