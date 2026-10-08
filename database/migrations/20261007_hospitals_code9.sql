-- Hospital/facility master only, independent of retired 43-file module.
-- Never derive an official 9-digit facility code by padding a 5-digit code.
ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS hospital_code9 VARCHAR(9) NULL AFTER hospital_code;
