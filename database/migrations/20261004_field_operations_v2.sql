-- Field Work operational improvements v2
-- Duplicate-check index + menu registration
-- Generated 2026-10-04

CREATE INDEX IF NOT EXISTS idx_field_patient_date
  ON field_visits (hospital_id, patient_ref, visit_date);

INSERT INTO system_menus
  (menu_name, icon, controller, action, allowed_roles, display_order, is_active, is_core, sort_order)
SELECT
  'เยี่ยมบ้าน / งานชุมชน',
  'bi-house-heart-fill',
  'field',
  'index',
  'SUPERADMIN,ADMIN,DIRECTOR,SCHEDULER,STAFF',
  35,
  1,
  0,
  35
WHERE NOT EXISTS (
  SELECT 1
  FROM system_menus
  WHERE controller = 'field'
    AND action = 'index'
);
