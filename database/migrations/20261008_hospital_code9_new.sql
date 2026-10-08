-- Roster Pro / Data43: distinguish the former numeric 9-digit hospital code
-- from the new mixed-format 9-character code (MOPH, since 2024).
-- Run once after 20261007_data43_hospital_code9.sql.
ALTER TABLE hospitals
  ADD COLUMN hospital_code9_new VARCHAR(9) NULL AFTER hospital_code9;

-- Existing hospital_code9 values are preserved; do NOT auto-derive codes from HOSPCODE (5 digits).
