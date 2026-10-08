-- Preserve existing templates; their former single leave_type_id remains the compatibility fallback.
ALTER TABLE leave_form_templates ADD COLUMN sort_order INT NOT NULL DEFAULT 1000;
CREATE TABLE IF NOT EXISTS leave_template_types (
 template_id INT NOT NULL,
 leave_type_id INT NOT NULL,
 PRIMARY KEY(template_id,leave_type_id),
 KEY idx_type(leave_type_id,template_id),
 CONSTRAINT fk_ltt_template FOREIGN KEY(template_id) REFERENCES leave_form_templates(id) ON DELETE CASCADE,
 CONSTRAINT fk_ltt_leave_type FOREIGN KEY(leave_type_id) REFERENCES leave_quotas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO leave_template_types(template_id,leave_type_id)
SELECT id,leave_type_id FROM leave_form_templates WHERE leave_type_id IS NOT NULL;
UPDATE leave_form_templates SET sort_order=id WHERE sort_order=1000;
