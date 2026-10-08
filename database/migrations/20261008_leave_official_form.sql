-- Roster Pro: optional official-style leave form details. Back up MySQL first.
CREATE TABLE IF NOT EXISTS leave_official_form_details (
 leave_request_id INT NOT NULL PRIMARY KEY,
 contact_address VARCHAR(500) NULL,
 contact_phone VARCHAR(40) NULL,
 delegate_name VARCHAR(180) NULL,
 delegate_position VARCHAR(180) NULL,
 delegate_duties VARCHAR(500) NULL,
 hr_review_note VARCHAR(500) NULL,
 hr_reviewed_by INT NULL,
 hr_reviewed_at DATETIME NULL,
 supervisor_opinion VARCHAR(500) NULL,
 supervisor_opinion_by INT NULL,
 supervisor_opinion_at DATETIME NULL,
 CONSTRAINT fk_leave_official_request FOREIGN KEY (leave_request_id)
 REFERENCES leave_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
