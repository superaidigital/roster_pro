-- Minimal legacy database fixture for migration-upgrade CI.
-- Intentionally omits modern columns/tables so the legacy bridge + tracked migrations must add them.

CREATE TABLE users (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'STAFF',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hospitals (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pay_rates (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_menus (
  id INT NOT NULL AUTO_INCREMENT,
  menu_name VARCHAR(100) NOT NULL,
  icon VARCHAR(100) DEFAULT NULL,
  controller VARCHAR(50) NOT NULL,
  action VARCHAR(50) NOT NULL DEFAULT 'index',
  allowed_roles VARCHAR(255) NOT NULL DEFAULT 'ADMIN',
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_core TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE holidays (
  id INT NOT NULL AUTO_INCREMENT,
  hospital_id INT DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'APPROVED',
  holiday_date DATE NOT NULL,
  holiday_name VARCHAR(255) NOT NULL,
  holiday_type VARCHAR(30) NOT NULL DEFAULT 'REGULAR',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leave_requests (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  status VARCHAR(20) DEFAULT 'PENDING',
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL DEFAULT 0,
  action VARCHAR(50) NOT NULL,
  details TEXT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roster_status (
  id INT NOT NULL AUTO_INCREMENT,
  hospital_id INT NOT NULL,
  month_year VARCHAR(7) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
  PRIMARY KEY (id),
  UNIQUE KEY uq_roster_status_month (hospital_id, month_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shifts (
  id BIGINT NOT NULL AUTO_INCREMENT,
  hospital_id INT NOT NULL,
  user_id INT NOT NULL,
  shift_date DATE NOT NULL,
  shift_type VARCHAR(20) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
