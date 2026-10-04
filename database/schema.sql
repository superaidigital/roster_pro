-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 29, 2026 at 09:13 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `roster_pro_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `employee_education`
--

CREATE TABLE `employee_education` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `degree_level` varchar(100) NOT NULL COMMENT 'ระดับการศึกษา (เช่น ป.ตรี, ป.โท, วุฒิบัตรเฉพาะทาง)',
  `degree_name` varchar(150) NOT NULL COMMENT 'ชื่อวุฒิการศึกษา (เช่น พย.บ., ส.บ.)',
  `major` varchar(150) DEFAULT NULL COMMENT 'สาขาวิชาเอก',
  `institution` varchar(200) NOT NULL COMMENT 'สถาบันการศึกษา',
  `graduation_year` varchar(4) DEFAULT NULL COMMENT 'ปี พ.ศ. ที่สำเร็จการศึกษา',
  `gpa` decimal(3,2) DEFAULT NULL COMMENT 'เกรดเฉลี่ย',
  `document_path` varchar(255) DEFAULT NULL COMMENT 'ไฟล์แนบ (สแกนใบปริญญา/ทรานสคริปต์)',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_licenses`
--

CREATE TABLE `employee_licenses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `license_name` varchar(200) NOT NULL COMMENT 'ชื่อใบอนุญาต (เช่น ใบอนุญาตประกอบวิชาชีพเวชกรรม)',
  `license_no` varchar(100) NOT NULL COMMENT 'เลขที่ใบอนุญาต',
  `council_name` varchar(150) DEFAULT NULL COMMENT 'สภาวิชาชีพที่ออกให้ (แพทยสภา, สภาการพยาบาล)',
  `issue_date` date DEFAULT NULL COMMENT 'วันที่ออกใบอนุญาต',
  `expire_date` date DEFAULT NULL COMMENT 'วันหมดอายุ (ใช้สำหรับทำระบบแจ้งเตือน HR)',
  `status` enum('ACTIVE','EXPIRED','REVOKED') DEFAULT 'ACTIVE' COMMENT 'สถานะใบอนุญาต',
  `document_path` varchar(255) DEFAULT NULL COMMENT 'ไฟล์แนบสแกนใบประกอบฯ',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_profiles`
--

CREATE TABLE `employee_profiles` (
  `user_id` int(11) NOT NULL COMMENT 'รหัสอ้างอิงจากตาราง users',
  `title_name` varchar(50) DEFAULT NULL COMMENT 'คำนำหน้า (นาย, นาง, นางสาว, นพ., ทพ. ฯลฯ)',
  `first_name_th` varchar(100) DEFAULT NULL COMMENT 'ชื่อจริง (ไทย)',
  `last_name_th` varchar(100) DEFAULT NULL COMMENT 'นามสกุล (ไทย)',
  `first_name_en` varchar(100) DEFAULT NULL COMMENT 'ชื่อจริง (อังกฤษ)',
  `last_name_en` varchar(100) DEFAULT NULL COMMENT 'นามสกุล (อังกฤษ)',
  `gender` enum('M','F','O') DEFAULT NULL COMMENT 'เพศ (ชาย, หญิง, อื่นๆ)',
  `birth_date` date DEFAULT NULL COMMENT 'วัน/เดือน/ปีเกิด',
  `blood_group` varchar(5) DEFAULT NULL COMMENT 'กรุ๊ปเลือด (A, B, AB, O, Rh-)',
  `marital_status` varchar(50) DEFAULT NULL COMMENT 'สถานภาพ (โสด, สมรส, หย่าร้าง)',
  `nationality` varchar(50) DEFAULT 'ไทย' COMMENT 'สัญชาติ',
  `religion` varchar(50) DEFAULT 'พุทธ' COMMENT 'ศาสนา',
  `address_permanent` text DEFAULT NULL COMMENT 'ที่อยู่ตามทะเบียนบ้าน',
  `address_current` text DEFAULT NULL COMMENT 'ที่อยู่ปัจจุบัน',
  `emergency_contact_name` varchar(150) DEFAULT NULL COMMENT 'ชื่อผู้ติดต่อฉุกเฉิน',
  `emergency_contact_relation` varchar(50) DEFAULT NULL COMMENT 'ความสัมพันธ์ผู้ติดต่อฉุกเฉิน',
  `emergency_contact_phone` varchar(20) DEFAULT NULL COMMENT 'เบอร์โทรฉุกเฉิน',
  `bank_name` varchar(100) DEFAULT NULL COMMENT 'ชื่อธนาคาร',
  `bank_branch` varchar(100) DEFAULT NULL COMMENT 'สาขาธนาคาร',
  `bank_account_no` varchar(50) DEFAULT NULL COMMENT 'เลขที่บัญชี',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_trainings`
--

CREATE TABLE `employee_trainings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_name` varchar(255) NOT NULL COMMENT 'ชื่อหลักสูตรที่อบรม',
  `organizer` varchar(200) DEFAULT NULL COMMENT 'หน่วยงานที่จัดอบรม',
  `start_date` date NOT NULL COMMENT 'วันที่เริ่มอบรม',
  `end_date` date NOT NULL COMMENT 'วันที่สิ้นสุดอบรม',
  `cpe_credits` decimal(5,2) DEFAULT 0.00 COMMENT 'หน่วยกิตการศึกษาต่อเนื่อง (CPE/CME)',
  `certificate_path` varchar(255) DEFAULT NULL COMMENT 'ไฟล์สแกนใบประกาศนียบัตร',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_work_history`
--

CREATE TABLE `employee_work_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(200) NOT NULL COMMENT 'ชื่อหน่วยงาน/องค์กร',
  `position` varchar(150) NOT NULL COMMENT 'ตำแหน่งที่ดำรง',
  `start_date` date NOT NULL COMMENT 'วันที่เริ่มงาน',
  `end_date` date DEFAULT NULL COMMENT 'วันที่สิ้นสุดการทำงาน (NULL = งานปัจจุบัน)',
  `salary` decimal(10,2) DEFAULT NULL COMMENT 'เงินเดือน',
  `reason_for_leave` text DEFAULT NULL COMMENT 'สาเหตุที่ออก',
  `reference_contact` varchar(255) DEFAULT NULL COMMENT 'บุคคลอ้างอิง',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) DEFAULT NULL COMMENT 'NULL = วันหยุดส่วนกลาง',
  `status` enum('PENDING','APPROVED') DEFAULT 'APPROVED',
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(255) NOT NULL,
  `holiday_type` enum('REGULAR','COMPENSATION','SPECIAL') DEFAULT 'REGULAR',
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `hospitals`
--

CREATE TABLE `hospitals` (
  `id` int(11) NOT NULL,
  `hospital_code` varchar(10) NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `address` varchar(255) DEFAULT NULL,
  `sub_district` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `zipcode` varchar(10) DEFAULT NULL,
  `latitude` varchar(50) DEFAULT NULL,
  `longitude` varchar(50) DEFAULT NULL,
  `hospital_size` enum('S','M','L','XL') DEFAULT 'S',
  `phone` varchar(50) DEFAULT NULL,
  `morning_shift` varchar(50) DEFAULT '08:30 - 16:30',
  `afternoon_shift` varchar(50) DEFAULT '16:30 - 00:30',
  `night_shift` varchar(50) DEFAULT '00:30 - 08:30',
  `created_at` datetime DEFAULT current_timestamp(),
  `email` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `director_name` varchar(255) DEFAULT NULL,
  `shift_m_start` time DEFAULT '08:00:00',
  `shift_m_end` time DEFAULT '16:00:00',
  `shift_a_start` time DEFAULT '16:00:00',
  `shift_a_end` time DEFAULT '00:00:00',
  `shift_n_start` time DEFAULT '00:00:00',
  `shift_n_end` time DEFAULT '08:00:00',
  `deleted_at` datetime DEFAULT NULL COMMENT 'เวลาที่ถูกลบ (Soft Delete)',
  `short_name` varchar(100) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `leave_balances`
--

CREATE TABLE `leave_balances` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'รหัสพนักงาน',
  `budget_year` int(4) NOT NULL COMMENT 'ปีงบประมาณ (เช่น 2024, 2025)',
  `leave_type_id` int(11) NOT NULL COMMENT 'อ้างอิง ID จากตาราง leave_quotas',
  `quota_days` int(11) NOT NULL COMMENT 'โควตาฐานของปีนี้ (เช่น 10 วัน)',
  `carried_over_days` int(11) NOT NULL DEFAULT 0 COMMENT 'วันลายกยอดมาจากปีก่อน (สะสม)',
  `used_days` decimal(4,1) NOT NULL DEFAULT 0.0 COMMENT 'จำนวนวันที่ใช้ไปแล้วในปีนี้',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `leave_quotas`
--

CREATE TABLE `leave_quotas` (
  `id` int(11) NOT NULL,
  `leave_type` varchar(100) NOT NULL,
  `max_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `calculation_type` enum('WORKING_DAYS','CALENDAR_DAYS') NOT NULL DEFAULT 'WORKING_DAYS' COMMENT 'นับวันทำการ หรือ นับรวมวันหยุด',
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'รหัสพนักงาน',
  `leave_type_id` int(11) NOT NULL COMMENT 'อ้างอิงไอดีประเภทการลา',
  `start_date` date NOT NULL COMMENT 'วันที่เริ่มลา',
  `end_date` date NOT NULL COMMENT 'ถึงวันที่',
  `num_days` decimal(4,1) NOT NULL COMMENT 'จำนวนวันลา (รองรับครึ่งวัน 0.5)',
  `reason` text NOT NULL COMMENT 'เหตุผลการลา',
  `has_med_cert` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=มีใบรับรองแพทย์',
  `med_cert_path` varchar(255) DEFAULT NULL COMMENT 'ที่อยู่ไฟล์ใบรับรองแพทย์',
  `status` varchar(50) DEFAULT 'PENDING',
  `approved_by` int(11) DEFAULT NULL COMMENT 'ผู้อนุมัติ',
  `approved_at` datetime DEFAULT NULL COMMENT 'เวลาที่อนุมัติ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL COMMENT 'ประเภท เช่น LOGIN, CREATE, UPDATE, DELETE',
  `details` text DEFAULT NULL COMMENT 'รายละเอียดสิ่งที่ทำ',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(20) DEFAULT 'INFO',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `pay_rates`
--

CREATE TABLE `pay_rates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `group_level` int(11) NOT NULL COMMENT 'ระดับกลุ่ม 1,2,3',
  `group_name` varchar(255) NOT NULL COMMENT 'ชื่อกลุ่มสายงาน',
  `keywords` text NOT NULL COMMENT 'คำค้นหาตำแหน่ง (คั่นด้วยลูกน้ำ)',
  `rate_y` int(11) NOT NULL DEFAULT 0 COMMENT 'เรทวันหยุด (ย)',
  `rate_b` int(11) NOT NULL DEFAULT 0 COMMENT 'เรทเวรบ่าย (บ)',
  `rate_r` int(11) NOT NULL DEFAULT 0 COMMENT 'เรทเวรดึก/On Call (ร)',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `display_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `roster_status`
--

CREATE TABLE `roster_status` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `month_year` varchar(7) NOT NULL COMMENT 'YYYY-MM',
  `status` enum('NOT_STARTED','DRAFT','SUBMITTED','REQUEST_EDIT','APPROVED') NOT NULL DEFAULT 'DRAFT',
  `reviewer_id` int(11) DEFAULT NULL COMMENT 'รหัสแอดมินผู้ตรวจ/อนุมัติ',
  `remark` text DEFAULT NULL COMMENT 'เหตุผลกรณีขอให้แก้ไข (REQUEST_EDIT)',
  `pay_summary` text DEFAULT NULL COMMENT 'เก็บ JSON Snapshot ยอดเงินตอนกดอนุมัติ',
  `submitted_at` datetime DEFAULT NULL COMMENT 'เวลาที่กดส่งเวรล่าสุด',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `creator_id` int(11) DEFAULT NULL,
  `director_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shift_date` date NOT NULL,
  `shift_type` varchar(20) NOT NULL COMMENT 'ร, ย, บ, บ/ร',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `shift_swaps`
--

CREATE TABLE `shift_swaps` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `requestor_id` int(11) NOT NULL,
  `requestor_date` date NOT NULL,
  `requestor_shift` varchar(50) NOT NULL,
  `target_user_id` int(11) NOT NULL,
  `target_date` date NOT NULL,
  `target_shift` varchar(50) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('PENDING_TARGET','PENDING_DIRECTOR','APPROVED','REJECTED') DEFAULT 'PENDING_TARGET',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'รหัสผู้ใช้งาน (ถ้ามี)',
  `action` varchar(50) NOT NULL COMMENT 'ประเภทการกระทำ เช่น LOGIN, UPDATE, DELETE',
  `description` text DEFAULT NULL COMMENT 'รายละเอียดการกระทำ',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'ไอพีแอดเดรส',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันเวลาที่บันทึก'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_menus`
--

CREATE TABLE `system_menus` (
  `id` int(11) NOT NULL,
  `menu_name` varchar(100) NOT NULL COMMENT 'ชื่อเมนู',
  `icon` varchar(50) DEFAULT NULL COMMENT 'คลาสของไอคอน (เช่น bi-calendar)',
  `controller` varchar(50) NOT NULL COMMENT 'ชื่อ Controller ที่เรียกใช้งาน',
  `action` varchar(50) NOT NULL DEFAULT 'index' COMMENT 'ชื่อ Action (default: index)',
  `allowed_roles` varchar(255) NOT NULL DEFAULT 'ADMIN' COMMENT 'สิทธิ์ที่มองเห็น (คั่นด้วยลูกน้ำ)',
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = ปิด, 1 = เปิด',
  `is_core` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = ห้ามลบ/ห้ามปิด (สำหรับเมนูหลักของ Admin)',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'ลำดับการแสดงผล'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--


-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) DEFAULT NULL COMMENT 'รหัสหน่วยบริการ (NULL = แอดมินส่วนกลาง)',
  `name` varchar(100) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('SUPERADMIN','ADMIN','DIRECTOR','SCHEDULER','STAFF','HR') NOT NULL DEFAULT 'STAFF',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Active, 0=Suspended',
  `inactive_date` date DEFAULT NULL,
  `inactive_reason` varchar(100) DEFAULT NULL,
  `inactive_note` text DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL COMMENT 'ตำแหน่งการปฏิบัติงาน',
  `employee_type` enum('ข้าราชการ/พนักงานท้องถิ่น','พนักงานจ้างตามภารกิจ','พนักงานจ้างทั่วไป') NOT NULL DEFAULT 'ข้าราชการ/พนักงานท้องถิ่น',
  `start_date` date DEFAULT NULL COMMENT 'วันที่บรรจุ/เริ่มงาน',
  `type` varchar(100) DEFAULT NULL COMMENT 'วิชาชีพ/ตำแหน่ง',
  `position_number` varchar(50) DEFAULT NULL,
  `color_theme` varchar(20) DEFAULT 'primary',
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `display_order` int(11) NOT NULL DEFAULT 0,
  `id_card` varchar(13) DEFAULT NULL,
  `pay_rate_id` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL COMMENT 'เวลาที่ถูกลบ (Soft Delete)',
  `is_deleted` tinyint(1) DEFAULT 0,
  `show_in_roster` tinyint(1) DEFAULT 1,
  `signature_path` longtext DEFAULT NULL COMMENT 'เก็บลายมือชื่ออิเล็กทรอนิกส์ (Base64)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--



-- --------------------------------------------------------

--
-- Table structure for table `field_visits`
--

CREATE TABLE `field_visits` (
  `id` bigint NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `visit_date` date NOT NULL,
  `patient_ref` varchar(50) NOT NULL,
  `patient_name` varchar(150) DEFAULT NULL,
  `patient_age` smallint DEFAULT NULL,
  `visit_type` varchar(40) NOT NULL DEFAULT 'HOME_VISIT',
  `chief_concern` varchar(255) DEFAULT NULL,
  `systolic` smallint DEFAULT NULL,
  `diastolic` smallint DEFAULT NULL,
  `pulse` smallint DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `spo2` tinyint unsigned DEFAULT NULL,
  `weight` decimal(6,2) DEFAULT NULL,
  `height` decimal(6,2) DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `assessment` text DEFAULT NULL,
  `care_plan` text DEFAULT NULL,
  `risk_level` varchar(20) NOT NULL DEFAULT 'ROUTINE',
  `follow_up_date` date DEFAULT NULL,
  `follow_up_status` varchar(20) NOT NULL DEFAULT 'NONE',
  `referral_required` tinyint(1) NOT NULL DEFAULT 0,
  `referral_note` varchar(500) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `accuracy_m` decimal(10,2) DEFAULT NULL,
  `address_note` varchar(255) DEFAULT NULL,
  `photo_consent` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `field_visit_photos`
--

CREATE TABLE `field_visit_photos` (
  `id` bigint NOT NULL,
  `field_visit_id` bigint NOT NULL,
  `stored_path` varchar(500) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `employee_education`
--
ALTER TABLE `employee_education`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `employee_licenses`
--
ALTER TABLE `employee_licenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `employee_trainings`
--
ALTER TABLE `employee_trainings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `employee_work_history`
--
ALTER TABLE `employee_work_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_holidays_hospital_date` (`hospital_id`,`holiday_date`);

--
-- Indexes for table `hospitals`
--
ALTER TABLE `hospitals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_year_leave` (`user_id`,`budget_year`,`leave_type_id`);

--
-- Indexes for table `leave_quotas`
--
ALTER TABLE `leave_quotas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_leave_user_status_dates` (`user_id`,`status`,`start_date`,`end_date`),
  ADD KEY `idx_leave_status_dates` (`status`,`start_date`,`end_date`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_logs_login_rate` (`user_id`,`action`,`ip_address`,`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user_read_created` (`user_id`,`is_read`,`created_at`);

--
-- Indexes for table `pay_rates`
--
ALTER TABLE `pay_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roster_status`
--
ALTER TABLE `roster_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hosp_month_unique` (`hospital_id`,`month_year`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_date_unique` (`user_id`,`shift_date`),
  ADD KEY `idx_shifts_hospital_date` (`hospital_id`,`shift_date`),
  ADD KEY `idx_shifts_hospital_user_date` (`hospital_id`,`user_id`,`shift_date`);

--
-- Indexes for table `shift_swaps`
--
ALTER TABLE `shift_swaps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hospital_id` (`hospital_id`),
  ADD KEY `requestor_id` (`requestor_id`),
  ADD KEY `target_user_id` (`target_user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_shift_swaps_hospital_status_created` (`hospital_id`,`status`,`created_at`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_menus`
--
ALTER TABLE `system_menus`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_users_hospital_roster` (`hospital_id`,`is_active`,`is_deleted`,`show_in_roster`,`display_order`);


--
-- Indexes for table `field_visits`
--
ALTER TABLE `field_visits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_field_hospital_date` (`hospital_id`,`visit_date`),
  ADD KEY `idx_field_creator_date` (`created_by`,`visit_date`),
  ADD KEY `idx_field_status` (`status`),
  ADD KEY `idx_field_risk` (`risk_level`),
  ADD KEY `idx_field_followup` (`follow_up_status`,`follow_up_date`),
  ADD KEY `idx_field_patient_date` (`hospital_id`,`patient_ref`,`visit_date`);

--
-- Indexes for table `field_visit_photos`
--
ALTER TABLE `field_visit_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_field_photo_visit` (`field_visit_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `employee_education`
--
ALTER TABLE `employee_education`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_licenses`
--
ALTER TABLE `employee_licenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_trainings`
--
ALTER TABLE `employee_trainings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_work_history`
--
ALTER TABLE `employee_work_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `hospitals`
--
ALTER TABLE `hospitals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `leave_quotas`
--
ALTER TABLE `leave_quotas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `pay_rates`
--
ALTER TABLE `pay_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `roster_status`
--
ALTER TABLE `roster_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `shift_swaps`
--
ALTER TABLE `shift_swaps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_menus`
--
ALTER TABLE `system_menus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;


--
-- AUTO_INCREMENT for table `field_visits`
--
ALTER TABLE `field_visits`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `field_visit_photos`
--
ALTER TABLE `field_visit_photos`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employee_education`
--
ALTER TABLE `employee_education`
  ADD CONSTRAINT `employee_education_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_licenses`
--
ALTER TABLE `employee_licenses`
  ADD CONSTRAINT `employee_licenses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  ADD CONSTRAINT `employee_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_trainings`
--
ALTER TABLE `employee_trainings`
  ADD CONSTRAINT `employee_trainings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_work_history`
--
ALTER TABLE `employee_work_history`
  ADD CONSTRAINT `employee_work_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `field_visits`
--
ALTER TABLE `field_visits`
  ADD CONSTRAINT `fk_field_visit_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`),
  ADD CONSTRAINT `fk_field_visit_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `field_visit_photos`
--
ALTER TABLE `field_visit_photos`
  ADD CONSTRAINT `fk_field_photo_visit` FOREIGN KEY (`field_visit_id`) REFERENCES `field_visits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
