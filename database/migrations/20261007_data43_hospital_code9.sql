-- Optional 9-digit health service code used by Version 2.4.1 structures.
ALTER TABLE hospitals
    ADD COLUMN IF NOT EXISTS hospital_code9 VARCHAR(9) NULL AFTER hospital_code;
