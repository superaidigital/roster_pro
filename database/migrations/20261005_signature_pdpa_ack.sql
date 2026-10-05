ALTER TABLE users
  ADD COLUMN IF NOT EXISTS signature_pdpa_notice_version VARCHAR(50) DEFAULT NULL AFTER signature_updated_at,
  ADD COLUMN IF NOT EXISTS signature_pdpa_ack_at DATETIME DEFAULT NULL AFTER signature_pdpa_notice_version;
