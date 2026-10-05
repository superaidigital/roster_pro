-- Public verification code for immutable roster revisions
-- Date: 2026-10-05
-- The code is derived from the immutable content hash so existing revisions can be backfilled.

ALTER TABLE roster_revisions
  ADD COLUMN IF NOT EXISTS verification_code CHAR(32) NULL AFTER content_hash;

UPDATE roster_revisions
SET verification_code = UPPER(SUBSTRING(SHA2(CONCAT('roster-verify|', content_hash), 256), 1, 32))
WHERE verification_code IS NULL OR verification_code = '';

ALTER TABLE roster_revisions
  MODIFY verification_code CHAR(32) NOT NULL,
  ADD UNIQUE KEY IF NOT EXISTS uq_roster_revision_verification_code (verification_code);
