-- Field work follow-up / risk / referral extension
-- Safe incremental migration for existing MariaDB/MySQL installations.
-- Generated 2026-10-04.

ALTER TABLE field_visits
  ADD COLUMN IF NOT EXISTS risk_level VARCHAR(20) NOT NULL DEFAULT 'ROUTINE' AFTER care_plan,
  ADD COLUMN IF NOT EXISTS follow_up_date DATE NULL AFTER risk_level,
  ADD COLUMN IF NOT EXISTS follow_up_status VARCHAR(20) NOT NULL DEFAULT 'NONE' AFTER follow_up_date,
  ADD COLUMN IF NOT EXISTS referral_required TINYINT(1) NOT NULL DEFAULT 0 AFTER follow_up_status,
  ADD COLUMN IF NOT EXISTS referral_note VARCHAR(500) NULL AFTER referral_required;

CREATE INDEX IF NOT EXISTS idx_field_risk
  ON field_visits (risk_level);

CREATE INDEX IF NOT EXISTS idx_field_followup
  ON field_visits (follow_up_status, follow_up_date);

UPDATE field_visits
SET follow_up_status = CASE
  WHEN follow_up_date IS NULL THEN 'NONE'
  WHEN follow_up_status IS NULL OR follow_up_status = '' THEN 'PENDING'
  ELSE follow_up_status
END;
