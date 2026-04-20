-- ==========================================================
-- ระบบสำรองฐานข้อมูล Roster Pro (Backup Data)
-- วันที่สร้างไฟล์: 2026-04-19 12:09:56
-- ==========================================================

SET FOREIGN_KEY_CHECKS=0;

-- โครงสร้างตาราง `holidays`
DROP TABLE IF EXISTS `holidays`;
CREATE TABLE `holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT NULL COMMENT 'NULL = วันหยุดส่วนกลาง',
  `status` enum('PENDING','APPROVED') DEFAULT 'APPROVED',
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ข้อมูลตาราง `holidays`
INSERT INTO `holidays` VALUES("1",NULL,"APPROVED","2026-01-01","วันขึ้นปีใหม่");
INSERT INTO `holidays` VALUES("2",NULL,"APPROVED","2026-03-03","วันมาฆบูชา");
INSERT INTO `holidays` VALUES("3",NULL,"APPROVED","2026-04-06","วันจักรี");
INSERT INTO `holidays` VALUES("4",NULL,"APPROVED","2026-04-13","วันสงกรานต์");
INSERT INTO `holidays` VALUES("5",NULL,"APPROVED","2026-04-14","วันสงกรานต์");
INSERT INTO `holidays` VALUES("6",NULL,"APPROVED","2026-04-15","วันสงกรานต์");

-- โครงสร้างตาราง `hospitals`
DROP TABLE IF EXISTS `hospitals`;
CREATE TABLE `hospitals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ข้อมูลตาราง `hospitals`
INSERT INTO `hospitals` VALUES("1","00000","กองสาธารณสุข อบจ.ศรีสะเกษ (ส่วนกลาง)","1","ศาลากลางจังหวัด","หนองครก","เมืองศรีสะเกษ","ศรีสะเกษ","33000",NULL,NULL,"XL","045-888-888","08:30 - 16:30","16:30 - 00:30","00:30 - 08:30","2026-03-12 22:27:09",NULL,NULL);
INSERT INTO `hospitals` VALUES("2","04123","รพ.สต. เฉลิมพระเกียรติ 60 พรรษาฯ บ้านภูมิซรอล","1","123 ม.1","เสาธงชัย","กันทรลักษ์","ศรีสะเกษ","33110","14.942318","104.400040","M","045-111-222","08:30 - 16:30","16:30 - 00:30","00:30 - 08:30","2026-03-12 22:27:09",NULL,NULL);
INSERT INTO `hospitals` VALUES("3","04124","รพ.สต. บ้านชำเม็ง","1","456 ม.2","พยุห์","พยุห์","ศรีสะเกษ","33230","15.232788","104.861371","S","045-333-444","08:30 - 16:30","16:30 - 00:30","00:30 - 08:30","2026-03-12 22:27:09",NULL,NULL);

-- โครงสร้างตาราง `leave_balances`
DROP TABLE IF EXISTS `leave_balances`;
CREATE TABLE `leave_balances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'รหัสพนักงาน',
  `budget_year` int(4) NOT NULL COMMENT 'ปีงบประมาณ (เช่น 2024, 2025)',
  `leave_type_id` int(11) NOT NULL COMMENT 'อ้างอิง ID จากตาราง leave_quotas',
  `quota_days` int(11) NOT NULL COMMENT 'โควตาฐานของปีนี้ (เช่น 10 วัน)',
  `carried_over_days` int(11) NOT NULL DEFAULT 0 COMMENT 'วันลายกยอดมาจากปีก่อน (สะสม)',
  `used_days` decimal(4,1) NOT NULL DEFAULT 0.0 COMMENT 'จำนวนวันที่ใช้ไปแล้วในปีนี้',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_year_leave` (`user_id`,`budget_year`,`leave_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=328 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ข้อมูลตาราง `leave_balances`
INSERT INTO `leave_balances` VALUES("76","6","2026","9","1460","0","0.0","2026-03-19 16:35:30");
INSERT INTO `leave_balances` VALUES("77","6","2026","10","15","0","0.0","2026-03-19 16:35:30");
INSERT INTO `leave_balances` VALUES("78","6","2026","11","365","0","0.0","2026-03-19 16:35:30");
INSERT INTO `leave_balances` VALUES("79","7","2026","7","0","0","0.0","2026-03-19 16:35:31");
INSERT INTO `leave_balances` VALUES("80","7","2026","8","365","0","0.0","2026-03-19 16:35:31");
INSERT INTO `leave_balances` VALUES("81","7","2026","9","1460","0","0.0","2026-03-19 16:35:31");
INSERT INTO `leave_balances` VALUES("82","7","2026","10","15","0","0.0","2026-03-19 16:35:31");
INSERT INTO `leave_balances` VALUES("83","7","2026","11","365","0","0.0","2026-03-19 16:35:31");
INSERT INTO `leave_balances` VALUES("84","3","2027","1","60","0","0.0","2026-03-19 21:15:09");
INSERT INTO `leave_balances` VALUES("85","3","2027","2","90","0","0.0","2026-03-19 21:15:09");
INSERT INTO `leave_balances` VALUES("86","3","2027","3","15","0","0.0","2026-03-19 21:15:09");
INSERT INTO `leave_balances` VALUES("87","3","2027","4","10","0","0.0","2026-04-19 00:42:25");
INSERT INTO `leave_balances` VALUES("88","3","2027","5","120","0","0.0","2026-03-19 21:15:09");
INSERT INTO `leave_balances` VALUES("89","3","2027","6","30","0","0.0","2026-03-19 21:15:09");
INSERT INTO `leave_balances` VALUES("90","3","2027","7","0","0","0.0","2026-03-19 21:15:09");
INSERT INTO `leave_balances` VALUES("91","3","2027","8","365","0","0.0","2026-03-19 21:15:09");
INSERT INTO `leave_balances` VALUES("92","3","2027","9","1460","0","0.0","2026-03-19 21:15:09");
INSERT INTO `leave_balances` VALUES("93","3","2027","10","15","0","0.0","2026-03-19 21:15:09");
INSERT INTO `leave_balances` VALUES("94","3","2027","11","365","0","0.0","2026-03-19 21:15:09");
INSERT INTO `leave_balances` VALUES("95","3","2024","1","60","0","0.0","2026-03-19 21:15:11");
INSERT INTO `leave_balances` VALUES("96","3","2024","2","90","0","0.0","2026-03-19 21:15:11");
INSERT INTO `leave_balances` VALUES("97","3","2024","3","45","0","0.0","2026-03-19 21:15:11");
INSERT INTO `leave_balances` VALUES("98","3","2024","4","10","0","0.0","2026-03-19 21:15:11");
INSERT INTO `leave_balances` VALUES("99","3","2024","5","120","0","0.0","2026-03-19 21:15:11");
INSERT INTO `leave_balances` VALUES("100","3","2024","6","30","0","0.0","2026-03-19 21:15:11");
INSERT INTO `leave_balances` VALUES("111","4","2026","1","60","0","0.0","2026-03-22 11:07:50");
INSERT INTO `leave_balances` VALUES("112","4","2026","2","90","0","0.0","2026-03-20 20:40:52");
INSERT INTO `leave_balances` VALUES("113","4","2026","3","15","0","0.0","2026-03-20 20:40:52");
INSERT INTO `leave_balances` VALUES("114","4","2026","4","10","0","0.0","2026-04-19 00:42:25");
INSERT INTO `leave_balances` VALUES("115","4","2026","5","120","0","0.0","2026-03-20 20:40:52");
INSERT INTO `leave_balances` VALUES("116","4","2026","6","30","0","0.0","2026-03-20 20:40:52");
INSERT INTO `leave_balances` VALUES("117","4","2026","7","0","0","0.0","2026-03-20 20:40:52");
INSERT INTO `leave_balances` VALUES("118","4","2026","8","365","0","0.0","2026-03-20 20:40:52");
INSERT INTO `leave_balances` VALUES("119","4","2026","9","1460","0","0.0","2026-03-20 20:40:52");
INSERT INTO `leave_balances` VALUES("120","4","2026","10","15","0","0.0","2026-03-20 20:40:52");
INSERT INTO `leave_balances` VALUES("121","4","2026","11","365","0","0.0","2026-03-20 20:40:52");
INSERT INTO `leave_balances` VALUES("122","3","2026","1","60","0","0.0","2026-03-20 20:40:55");
INSERT INTO `leave_balances` VALUES("123","3","2026","2","90","0","0.0","2026-03-20 20:40:55");
INSERT INTO `leave_balances` VALUES("124","3","2026","3","15","0","0.0","2026-03-20 20:40:55");
INSERT INTO `leave_balances` VALUES("125","3","2026","4","10","0","0.0","2026-04-19 00:42:25");
INSERT INTO `leave_balances` VALUES("126","3","2026","5","120","0","0.0","2026-03-20 20:40:55");
INSERT INTO `leave_balances` VALUES("127","3","2026","6","30","0","0.0","2026-03-20 20:40:55");
INSERT INTO `leave_balances` VALUES("128","3","2026","7","0","0","0.0","2026-03-20 20:40:55");
INSERT INTO `leave_balances` VALUES("129","3","2026","8","365","0","0.0","2026-03-20 20:40:55");
INSERT INTO `leave_balances` VALUES("130","3","2026","9","1460","0","0.0","2026-03-20 20:40:55");
INSERT INTO `leave_balances` VALUES("131","3","2026","10","15","0","0.0","2026-03-20 20:40:55");
INSERT INTO `leave_balances` VALUES("132","3","2026","11","365","0","0.0","2026-03-20 20:40:55");
INSERT INTO `leave_balances` VALUES("133","2","2026","1","60","0","0.0","2026-03-20 20:40:57");
INSERT INTO `leave_balances` VALUES("134","2","2026","2","90","0","0.0","2026-03-20 20:40:57");
INSERT INTO `leave_balances` VALUES("135","2","2026","3","15","0","0.0","2026-03-20 20:40:57");
INSERT INTO `leave_balances` VALUES("136","2","2026","4","10","0","0.0","2026-04-19 00:42:25");
INSERT INTO `leave_balances` VALUES("137","2","2026","5","120","0","0.0","2026-03-20 20:40:57");
INSERT INTO `leave_balances` VALUES("138","2","2026","6","30","0","0.0","2026-03-20 20:40:57");
INSERT INTO `leave_balances` VALUES("139","2","2026","7","0","0","0.0","2026-03-20 20:40:57");
INSERT INTO `leave_balances` VALUES("140","2","2026","8","365","0","0.0","2026-03-20 20:40:57");
INSERT INTO `leave_balances` VALUES("141","2","2026","9","1460","0","0.0","2026-03-20 20:40:57");
INSERT INTO `leave_balances` VALUES("142","2","2026","10","15","0","0.0","2026-03-20 20:40:57");
INSERT INTO `leave_balances` VALUES("143","2","2026","11","365","0","0.0","2026-03-20 20:40:57");
INSERT INTO `leave_balances` VALUES("144","1","2026","1","60","0","0.0","2026-03-20 20:40:58");
INSERT INTO `leave_balances` VALUES("145","1","2026","2","90","0","0.0","2026-03-20 20:40:58");
INSERT INTO `leave_balances` VALUES("146","1","2026","3","15","0","0.0","2026-03-20 20:40:58");
INSERT INTO `leave_balances` VALUES("147","1","2026","4","10","0","0.0","2026-03-20 20:40:58");
INSERT INTO `leave_balances` VALUES("148","1","2026","5","120","0","0.0","2026-03-20 20:40:58");
INSERT INTO `leave_balances` VALUES("149","1","2026","6","30","0","0.0","2026-03-20 20:40:58");
INSERT INTO `leave_balances` VALUES("150","1","2026","7","0","0","0.0","2026-03-20 20:40:58");
INSERT INTO `leave_balances` VALUES("151","1","2026","8","365","0","0.0","2026-03-20 20:40:58");
INSERT INTO `leave_balances` VALUES("152","1","2026","9","1460","0","0.0","2026-03-20 20:40:58");
INSERT INTO `leave_balances` VALUES("153","1","2026","10","15","0","0.0","2026-03-20 20:40:58");
INSERT INTO `leave_balances` VALUES("154","1","2026","11","365","0","0.0","2026-03-20 20:40:58");
INSERT INTO `leave_balances` VALUES("155","8","2026","1","60","0","0.0","2026-03-20 21:28:01");
INSERT INTO `leave_balances` VALUES("156","8","2026","2","90","0","0.0","2026-03-20 21:28:01");
INSERT INTO `leave_balances` VALUES("157","8","2026","3","15","0","0.0","2026-03-20 21:28:01");
INSERT INTO `leave_balances` VALUES("158","8","2026","4","10","0","0.0","2026-03-22 10:55:20");
INSERT INTO `leave_balances` VALUES("159","8","2026","5","120","0","0.0","2026-03-20 21:28:01");
INSERT INTO `leave_balances` VALUES("160","8","2026","6","30","0","0.0","2026-03-20 21:28:01");
INSERT INTO `leave_balances` VALUES("161","8","2026","7","0","0","0.0","2026-03-20 21:28:01");
INSERT INTO `leave_balances` VALUES("162","8","2026","8","365","0","0.0","2026-03-20 21:28:01");
INSERT INTO `leave_balances` VALUES("163","8","2026","9","1460","0","0.0","2026-03-20 21:28:01");
INSERT INTO `leave_balances` VALUES("164","8","2026","10","15","0","0.0","2026-03-20 21:28:01");
INSERT INTO `leave_balances` VALUES("165","8","2026","11","365","0","0.0","2026-03-20 21:28:01");
INSERT INTO `leave_balances` VALUES("166","5","2026","1","60","0","0.0","2026-03-21 18:48:34");
INSERT INTO `leave_balances` VALUES("167","5","2026","2","90","0","0.0","2026-03-21 18:48:34");
INSERT INTO `leave_balances` VALUES("168","5","2026","3","15","0","0.0","2026-03-21 18:48:35");
INSERT INTO `leave_balances` VALUES("169","5","2026","4","10","0","0.0","2026-04-19 00:42:25");
INSERT INTO `leave_balances` VALUES("170","5","2026","5","120","0","0.0","2026-03-21 18:48:35");
INSERT INTO `leave_balances` VALUES("171","5","2026","6","30","0","0.0","2026-03-21 18:48:35");
INSERT INTO `leave_balances` VALUES("172","5","2026","7","0","0","0.0","2026-03-21 18:48:35");
INSERT INTO `leave_balances` VALUES("173","5","2026","8","365","0","0.0","2026-03-21 18:48:35");
INSERT INTO `leave_balances` VALUES("174","5","2026","9","1460","0","0.0","2026-03-21 18:48:35");
INSERT INTO `leave_balances` VALUES("175","5","2026","10","15","0","0.0","2026-03-21 18:48:35");
INSERT INTO `leave_balances` VALUES("176","5","2026","11","365","0","0.0","2026-03-21 18:48:35");
INSERT INTO `leave_balances` VALUES("177","6","2026","1","60","0","0.0","2026-03-21 18:48:38");
INSERT INTO `leave_balances` VALUES("178","6","2026","2","90","0","0.0","2026-03-21 18:48:38");
INSERT INTO `leave_balances` VALUES("179","6","2026","3","15","0","0.0","2026-03-21 18:48:38");
INSERT INTO `leave_balances` VALUES("180","6","2026","4","10","0","0.0","2026-03-21 18:48:38");
INSERT INTO `leave_balances` VALUES("181","6","2026","5","120","0","0.0","2026-03-21 18:48:38");
INSERT INTO `leave_balances` VALUES("182","6","2026","6","30","0","0.0","2026-03-21 18:48:38");
INSERT INTO `leave_balances` VALUES("183","6","2026","7","0","0","0.0","2026-03-21 18:48:38");
INSERT INTO `leave_balances` VALUES("184","6","2026","8","365","0","0.0","2026-03-21 18:48:38");
INSERT INTO `leave_balances` VALUES("185","7","2026","1","60","0","0.0","2026-03-21 18:57:02");
INSERT INTO `leave_balances` VALUES("186","7","2026","2","90","0","0.0","2026-03-21 18:57:02");
INSERT INTO `leave_balances` VALUES("187","7","2026","3","15","0","0.0","2026-03-21 18:57:02");
INSERT INTO `leave_balances` VALUES("188","7","2026","4","10","0","0.0","2026-03-21 18:57:02");
INSERT INTO `leave_balances` VALUES("189","7","2026","5","120","0","0.0","2026-03-21 18:57:02");
INSERT INTO `leave_balances` VALUES("190","7","2026","6","30","0","0.0","2026-03-21 18:57:02");
INSERT INTO `leave_balances` VALUES("191","2","2025","1","60","0","0.0","2026-03-22 10:50:16");
INSERT INTO `leave_balances` VALUES("192","2","2025","2","90","0","0.0","2026-03-22 10:50:16");
INSERT INTO `leave_balances` VALUES("193","2","2025","3","45","0","0.0","2026-03-22 10:50:16");
INSERT INTO `leave_balances` VALUES("194","2","2025","4","10","0","0.0","2026-03-22 10:50:16");
INSERT INTO `leave_balances` VALUES("195","2","2025","5","120","0","0.0","2026-03-22 10:50:16");
INSERT INTO `leave_balances` VALUES("196","2","2025","6","30","0","0.0","2026-03-22 10:50:16");
INSERT INTO `leave_balances` VALUES("197","2","2025","7","0","0","0.0","2026-03-22 10:50:16");
INSERT INTO `leave_balances` VALUES("198","2","2025","8","365","0","0.0","2026-03-22 10:50:16");
INSERT INTO `leave_balances` VALUES("199","2","2025","9","1460","0","0.0","2026-03-22 10:50:16");
INSERT INTO `leave_balances` VALUES("200","2","2025","10","15","0","0.0","2026-03-22 10:50:16");
INSERT INTO `leave_balances` VALUES("201","2","2025","11","365","0","0.0","2026-03-22 10:50:16");
INSERT INTO `leave_balances` VALUES("202","5","2025","1","60","0","0.0","2026-03-22 10:50:16");
INSERT INTO `leave_balances` VALUES("203","5","2025","2","90","0","0.0","2026-03-22 10:50:16");
INSERT INTO `leave_balances` VALUES("204","5","2025","3","45","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("205","5","2025","4","10","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("206","5","2025","5","120","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("207","5","2025","6","30","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("208","5","2025","7","0","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("209","5","2025","8","365","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("210","5","2025","9","1460","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("211","5","2025","10","15","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("212","5","2025","11","365","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("213","3","2025","1","60","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("214","3","2025","2","90","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("215","3","2025","3","45","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("216","3","2025","4","10","0","0.0","2026-04-19 00:42:25");
INSERT INTO `leave_balances` VALUES("217","3","2025","5","120","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("218","3","2025","6","30","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("219","3","2025","7","0","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("220","3","2025","8","365","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("221","3","2025","9","1460","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("222","3","2025","10","15","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("223","3","2025","11","365","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("224","4","2025","1","60","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("225","4","2025","2","90","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("226","4","2025","3","45","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("227","4","2025","4","10","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("228","4","2025","5","120","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("229","4","2025","6","30","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("230","4","2025","7","0","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("231","4","2025","8","365","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("232","4","2025","9","1460","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("233","4","2025","10","15","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("234","4","2025","11","365","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("235","6","2025","1","60","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("236","6","2025","2","90","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("237","6","2025","3","45","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("238","6","2025","4","10","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("239","6","2025","5","120","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("240","6","2025","6","30","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("241","6","2025","7","0","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("242","6","2025","8","365","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("243","6","2025","9","1460","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("244","6","2025","10","15","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("245","6","2025","11","365","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("246","7","2025","1","60","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("247","7","2025","2","90","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("248","7","2025","3","45","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("249","7","2025","4","10","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("250","7","2025","5","120","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("251","7","2025","6","30","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("252","7","2025","7","0","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("253","7","2025","8","365","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("254","7","2025","9","1460","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("255","7","2025","10","15","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("256","7","2025","11","365","0","0.0","2026-03-22 10:50:17");
INSERT INTO `leave_balances` VALUES("257","2","2024","1","60","0","0.0","2026-03-22 10:50:18");
INSERT INTO `leave_balances` VALUES("258","2","2024","2","90","0","0.0","2026-03-22 10:50:18");
INSERT INTO `leave_balances` VALUES("259","2","2024","3","45","0","0.0","2026-03-22 10:50:18");
INSERT INTO `leave_balances` VALUES("260","2","2024","4","10","0","0.0","2026-03-22 10:50:18");
INSERT INTO `leave_balances` VALUES("261","2","2024","5","120","0","0.0","2026-03-22 10:50:18");
INSERT INTO `leave_balances` VALUES("262","2","2024","6","30","0","0.0","2026-03-22 10:50:18");
INSERT INTO `leave_balances` VALUES("263","2","2024","7","0","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("264","2","2024","8","365","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("265","2","2024","9","1460","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("266","2","2024","10","15","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("267","2","2024","11","365","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("268","5","2024","1","60","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("269","5","2024","2","90","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("270","5","2024","3","45","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("271","5","2024","4","10","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("272","5","2024","5","120","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("273","5","2024","6","30","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("274","5","2024","7","0","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("275","5","2024","8","365","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("276","5","2024","9","1460","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("277","5","2024","10","15","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("278","5","2024","11","365","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("279","3","2024","7","0","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("280","3","2024","8","365","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("281","3","2024","9","1460","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("282","3","2024","10","15","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("283","3","2024","11","365","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("284","4","2024","1","60","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("285","4","2024","2","90","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("286","4","2024","3","45","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("287","4","2024","4","10","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("288","4","2024","5","120","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("289","4","2024","6","30","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("290","4","2024","7","0","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("291","4","2024","8","365","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("292","4","2024","9","1460","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("293","4","2024","10","15","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("294","4","2024","11","365","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("295","6","2024","1","60","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("296","6","2024","2","90","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("297","6","2024","3","45","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("298","6","2024","4","10","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("299","6","2024","5","120","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("300","6","2024","6","30","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("301","6","2024","7","0","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("302","6","2024","8","365","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("303","6","2024","9","1460","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("304","6","2024","10","15","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("305","6","2024","11","365","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("306","7","2024","1","60","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("307","7","2024","2","90","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("308","7","2024","3","45","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("309","7","2024","4","10","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("310","7","2024","5","120","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("311","7","2024","6","30","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("312","7","2024","7","0","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("313","7","2024","8","365","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("314","7","2024","9","1460","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("315","7","2024","10","15","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("316","7","2024","11","365","0","0.0","2026-03-22 10:50:19");
INSERT INTO `leave_balances` VALUES("317","9","2026","1","60","0","0.0","2026-04-19 00:37:35");
INSERT INTO `leave_balances` VALUES("318","9","2026","2","90","0","0.0","2026-04-19 00:37:35");
INSERT INTO `leave_balances` VALUES("319","9","2026","3","15","0","0.0","2026-04-19 00:37:35");
INSERT INTO `leave_balances` VALUES("320","9","2026","4","10","0","0.0","2026-04-19 00:37:36");
INSERT INTO `leave_balances` VALUES("321","9","2026","5","120","0","0.0","2026-04-19 00:37:36");
INSERT INTO `leave_balances` VALUES("322","9","2026","6","30","0","0.0","2026-04-19 00:37:36");
INSERT INTO `leave_balances` VALUES("323","9","2026","7","0","0","0.0","2026-04-19 00:37:36");
INSERT INTO `leave_balances` VALUES("324","9","2026","8","365","0","0.0","2026-04-19 00:37:36");
INSERT INTO `leave_balances` VALUES("325","9","2026","9","1460","0","0.0","2026-04-19 00:37:36");
INSERT INTO `leave_balances` VALUES("326","9","2026","10","15","0","0.0","2026-04-19 00:37:36");
INSERT INTO `leave_balances` VALUES("327","9","2026","11","365","0","0.0","2026-04-19 00:37:36");

-- โครงสร้างตาราง `leave_quotas`
DROP TABLE IF EXISTS `leave_quotas`;
CREATE TABLE `leave_quotas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `leave_type` varchar(100) NOT NULL,
  `max_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `calculation_type` enum('WORKING_DAYS','CALENDAR_DAYS') NOT NULL DEFAULT 'WORKING_DAYS' COMMENT 'นับวันทำการ หรือ นับรวมวันหยุด',
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ข้อมูลตาราง `leave_quotas`
INSERT INTO `leave_quotas` VALUES("1","ลาป่วย","60.0","WORKING_DAYS","ลาป่วยตามจริง (รับเงินเดือนไม่เกิน 60 วัน)");
INSERT INTO `leave_quotas` VALUES("2","ลาคลอดบุตร","90.0","WORKING_DAYS","ลาคลอดบุตร (นับรวมวันหยุด)");
INSERT INTO `leave_quotas` VALUES("3","ลากิจส่วนตัว","45.0","WORKING_DAYS","ลากิจส่วนตัว (ปีแรก 15 วัน)");
INSERT INTO `leave_quotas` VALUES("4","ลาพักผ่อน","10.0","WORKING_DAYS","ลาพักผ่อนประจำปี (สะสมได้ตามระเบียบ)");
INSERT INTO `leave_quotas` VALUES("5","ลาอุปสมบท/ฮัจย์","120.0","WORKING_DAYS","ต้องทำงานมาแล้วไม่น้อยกว่า 1 ปี");
INSERT INTO `leave_quotas` VALUES("6","ลาเข้ารับการคัดเลือก/เตรียมพล","30.0","WORKING_DAYS","ลาได้ตามระยะเวลาที่เข้าฝึก (ไม่เกิน 30 วัน)");
INSERT INTO `leave_quotas` VALUES("7","ลาไปศึกษา ฝึกอบรม ดูงาน","0.0","WORKING_DAYS","พิจารณาอนุญาตเป็นรายกรณีโดยผู้มีอำนาจ");
INSERT INTO `leave_quotas` VALUES("8","ลาไปปฏิบัติงานในองค์การระหว่างประเทศ","365.0","WORKING_DAYS","มีสิทธิลาได้ไม่เกิน 1 ปี");
INSERT INTO `leave_quotas` VALUES("9","ลาติดตามคู่สมรส","1460.0","WORKING_DAYS","ลาได้ 2 ปี แต่ไม่เกิน 4 ปี (1,460 วัน)");
INSERT INTO `leave_quotas` VALUES("10","ลาไปช่วยเหลือภริยาคลอดบุตร","15.0","WORKING_DAYS","เฉพาะข้าราชการชาย (ภายใน 90 วันหลังคลอด)");
INSERT INTO `leave_quotas` VALUES("11","ลาไปฟื้นฟูสมรรถภาพด้านอาชีพ","365.0","WORKING_DAYS","ไม่เกิน 12 เดือน (กรณีบาดเจ็บจากการปฏิบัติหน้าที่)");

-- โครงสร้างตาราง `leave_requests`
DROP TABLE IF EXISTS `leave_requests`;
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- โครงสร้างตาราง `logs`
DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL COMMENT 'ประเภท เช่น LOGIN, CREATE, UPDATE, DELETE',
  `details` text DEFAULT NULL COMMENT 'รายละเอียดสิ่งที่ทำ',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ข้อมูลตาราง `logs`
INSERT INTO `logs` VALUES("1","8","DELETE","FACTORY RESET: ล้างข้อมูลระบบปฏิบัติการทั้งหมดเริ่มต้นรอบปีใหม่","::1","2026-04-19 00:42:25");
INSERT INTO `logs` VALUES("2","2","DOWNLOAD","ดาวน์โหลดตารางเวรรูปแบบ Word เดือน 2026-04","::1","2026-04-19 11:53:35");

-- โครงสร้างตาราง `notifications`
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(20) DEFAULT 'INFO',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- โครงสร้างตาราง `pay_rates`
DROP TABLE IF EXISTS `pay_rates`;
CREATE TABLE `pay_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `group_level` int(11) NOT NULL COMMENT 'ระดับกลุ่ม 1,2,3',
  `group_name` varchar(255) NOT NULL COMMENT 'ชื่อกลุ่มสายงาน',
  `keywords` text NOT NULL COMMENT 'คำค้นหาตำแหน่ง (คั่นด้วยลูกน้ำ)',
  `rate_y` int(11) NOT NULL DEFAULT 0 COMMENT 'เรทวันหยุด (ย)',
  `rate_b` int(11) NOT NULL DEFAULT 0 COMMENT 'เรทเวรบ่าย (บ)',
  `rate_r` int(11) NOT NULL DEFAULT 0 COMMENT 'เรทเวรดึก/On Call (ร)',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `display_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ข้อมูลตาราง `pay_rates`
INSERT INTO `pay_rates` VALUES("1","พยาบาล,แพทย์,นัก,ปริญญาตรี,สหวิชาชีพ,ป.ตรี","1","กลุ่มที่ 1: สายงานวิชาชีพ / ป.ตรี","พยาบาล,แพทย์,นัก,ปริญญาตรี,สหวิชาชีพ,ป.ตรี","650","320","150","2026-04-18 22:52:34","0");
INSERT INTO `pay_rates` VALUES("2","เจ้าพนักงาน","2","กลุ่มที่ 2: สายงานเจ้าพนักงาน","เจ้าพนักงาน","520","240","0","2026-04-18 22:52:34","0");
INSERT INTO `pay_rates` VALUES("3","ลูกจ้าง,พนักงานกระทรวง,ช่วยเหลือคนไข้","3","กลุ่มที่ 3: เจ้าหน้าที่อื่นๆ","ลูกจ้าง,พนักงานกระทรวง,ช่วยเหลือคนไข้","330","165","0","2026-04-18 22:52:34","0");

-- โครงสร้างตาราง `roster_status`
DROP TABLE IF EXISTS `roster_status`;
CREATE TABLE `roster_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL,
  `month_year` varchar(7) NOT NULL COMMENT 'YYYY-MM',
  `status` enum('NOT_STARTED','DRAFT','SUBMITTED','REQUEST_EDIT','APPROVED') NOT NULL DEFAULT 'DRAFT',
  `reviewer_id` int(11) DEFAULT NULL COMMENT 'รหัสแอดมินผู้ตรวจ/อนุมัติ',
  `remark` text DEFAULT NULL COMMENT 'เหตุผลกรณีขอให้แก้ไข (REQUEST_EDIT)',
  `pay_summary` text DEFAULT NULL COMMENT 'เก็บ JSON Snapshot ยอดเงินตอนกดอนุมัติ',
  `submitted_at` datetime DEFAULT NULL COMMENT 'เวลาที่กดส่งเวรล่าสุด',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `hosp_month_unique` (`hospital_id`,`month_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- โครงสร้างตาราง `shifts`
DROP TABLE IF EXISTS `shifts`;
CREATE TABLE `shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shift_date` date NOT NULL,
  `shift_type` varchar(20) NOT NULL COMMENT 'ร, ย, บ, บ/ร',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_date_unique` (`user_id`,`shift_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- โครงสร้างตาราง `system_logs`
DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'รหัสผู้ใช้งาน (ถ้ามี)',
  `action` varchar(50) NOT NULL COMMENT 'ประเภทการกระทำ เช่น LOGIN, UPDATE, DELETE',
  `description` text DEFAULT NULL COMMENT 'รายละเอียดการกระทำ',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'ไอพีแอดเดรส',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันเวลาที่บันทึก',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- โครงสร้างตาราง `system_menus`
DROP TABLE IF EXISTS `system_menus`;
CREATE TABLE `system_menus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_name` varchar(100) NOT NULL COMMENT 'ชื่อเมนู',
  `icon` varchar(50) DEFAULT NULL COMMENT 'คลาสของไอคอน (เช่น bi-calendar)',
  `controller` varchar(50) NOT NULL COMMENT 'ชื่อ Controller ที่เรียกใช้งาน',
  `action` varchar(50) NOT NULL DEFAULT 'index' COMMENT 'ชื่อ Action (default: index)',
  `allowed_roles` varchar(255) NOT NULL DEFAULT 'ADMIN' COMMENT 'สิทธิ์ที่มองเห็น (คั่นด้วยลูกน้ำ)',
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = ปิด, 1 = เปิด',
  `is_core` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = ห้ามลบ/ห้ามปิด (สำหรับเมนูหลักของ Admin)',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'ลำดับการแสดงผล',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ข้อมูลตาราง `system_menus`
INSERT INTO `system_menus` VALUES("1","แดชบอร์ดสถิติ","bi-pie-chart-fill","dashboard","index","SUPERADMIN,ADMIN,DIRECTOR,SCHEDULER,STAFF,HR","0","1","0","10");
INSERT INTO `system_menus` VALUES("2","ตารางปฏิบัติงาน","bi-calendar-event-fill","roster","index","DIRECTOR,SCHEDULER,STAFF","0","1","0","20");
INSERT INTO `system_menus` VALUES("3","ติดตามการส่งเวร","bi-bar-chart-line-fill","report","overview","SUPERADMIN,ADMIN,DIRECTOR,SCHEDULER,HR","0","1","0","30");
INSERT INTO `system_menus` VALUES("4","ระบบจัดการวันลา","bi-calendar-x-fill","leave","index","SUPERADMIN,ADMIN,DIRECTOR,SCHEDULER,STAFF,HR","0","1","0","40");
INSERT INTO `system_menus` VALUES("5","จัดการบุคลากร","bi-people-fill","staff","index","SUPERADMIN,ADMIN,DIRECTOR,SCHEDULER,HR","0","1","0","50");
INSERT INTO `system_menus` VALUES("6","ตั้งค่าระบบส่วนกลาง","bi-gear-wide-connected","settings","system","SUPERADMIN,SUPERADMIN","0","1","1","99");
INSERT INTO `system_menus` VALUES("7","ประวัติการแจ้งเตือน","bi-bell-fill","notification","index","","0","1","0","80");
INSERT INTO `system_menus` VALUES("8","ฐานข้อมูลบุคลากร","bi-people-fill","users","index","SUPERADMIN,SUPERADMIN,ADMIN,HR","0","1","0","45");
INSERT INTO `system_menus` VALUES("9","จัดการ รพ.สต.","bi-hospital-fill","hospitals","index","SUPERADMIN,ADMIN","0","1","0","35");
INSERT INTO `system_menus` VALUES("11","ปฏิทินเวรของฉัน","bi-calendar-heart","profile","schedule","ADMIN,DIRECTOR,SCHEDULER,STAFF,HR","0","1","0","15");
INSERT INTO `system_menus` VALUES("12","โปรไฟล์และการตั้งค่า","bi-person-badge","profile","index","ADMIN,DIRECTOR,SCHEDULER,STAFF,HR","0","1","0","90");

-- โครงสร้างตาราง `system_settings`
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ข้อมูลตาราง `system_settings`
INSERT INTO `system_settings` VALUES("app_name","Roster Pro",NULL);
INSERT INTO `system_settings` VALUES("contact_email","",NULL);
INSERT INTO `system_settings` VALUES("contact_phone","",NULL);
INSERT INTO `system_settings` VALUES("line_notify_on_holiday","0","เปิดแจ้งเตือนเมื่อขอเพิ่มวันหยุด (1=เปิด, 0=ปิด)");
INSERT INTO `system_settings` VALUES("line_notify_on_request","0","เปิดแจ้งเตือนเมื่อขอปลดล็อคแก้ไข (1=เปิด, 0=ปิด)");
INSERT INTO `system_settings` VALUES("line_notify_on_submit","0","เปิดแจ้งเตือนเมื่อ รพ.สต. ส่งเวร (1=เปิด, 0=ปิด)");
INSERT INTO `system_settings` VALUES("line_notify_token","","Token สำหรับส่งแจ้งเตือนเข้ากลุ่มส่วนกลาง");
INSERT INTO `system_settings` VALUES("log_retention_days","90",NULL);
INSERT INTO `system_settings` VALUES("maintenance_mode","0",NULL);
INSERT INTO `system_settings` VALUES("system_announcement","",NULL);

-- โครงสร้างตาราง `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT NULL COMMENT 'รหัสหน่วยบริการ (NULL = แอดมินส่วนกลาง)',
  `name` varchar(100) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('SUPERADMIN','ADMIN','DIRECTOR','SCHEDULER','STAFF','HR') NOT NULL DEFAULT 'STAFF',
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ข้อมูลตาราง `users`
INSERT INTO `users` VALUES("1","1","ผู้ดูแลระบบ ส่วนกลาง","","admin","$2y$10$iw3RmD6E5Y6QJ/7n4DnoSu6QyC6yj7MRVNOwX2IPUG.5ILpbYuHKu","ADMIN","ข้าราชการ/พนักงานท้องถิ่น",NULL,"นักวิชาการคอมพิวเตอร์",NULL,"primary","0","2026-03-12 22:27:09","0",NULL,NULL);
INSERT INTO `users` VALUES("2","2","นางเขมจิรา จันทร",NULL,"director1","$2a$12$LwO6GporWNzUqyMvZqCIWeisQIDAOgjIajwtkuK0o9GYpOQAgEEQK","DIRECTOR","ข้าราชการ/พนักงานท้องถิ่น",NULL,"ผู้อำนวยการ รพ.สต.",NULL,"primary","1","2026-03-12 22:27:09","1",NULL,"1");
INSERT INTO `users` VALUES("3","2","นางนภัส สิทธิโชค",NULL,"scheduler1","$2a$12$LwO6GporWNzUqyMvZqCIWeisQIDAOgjIajwtkuK0o9GYpOQAgEEQK","SCHEDULER","ข้าราชการ/พนักงานท้องถิ่น",NULL,"พยาบาลวิชาชีพ",NULL,"primary","2","2026-03-12 22:27:09","3",NULL,"1");
INSERT INTO `users` VALUES("4","2","นางสาวสุภาพร ศรีชำนาญชาญชัย",NULL,"staff1","$2a$12$LwO6GporWNzUqyMvZqCIWeisQIDAOgjIajwtkuK0o9GYpOQAgEEQK","STAFF","ข้าราชการ/พนักงานท้องถิ่น","2020-02-03","แพทย์แผนไทย",NULL,"primary","4","2026-03-12 22:27:09","4",NULL,"3");
INSERT INTO `users` VALUES("5","2","นายชนินทร์ แสงนวล",NULL,"staff2","$2a$12$LwO6GporWNzUqyMvZqCIWeisQIDAOgjIajwtkuK0o9GYpOQAgEEQK","STAFF","ข้าราชการ/พนักงานท้องถิ่น",NULL,"นักวิชาการสาธารณสุข",NULL,"primary","3","2026-03-12 22:27:09","2",NULL,"1");
INSERT INTO `users` VALUES("6","3","นายสมชาย ใจดี",NULL,"director2","$2a$12$LwO6GporWNzUqyMvZqCIWeisQIDAOgjIajwtkuK0o9GYpOQAgEEQK","DIRECTOR","ข้าราชการ/พนักงานท้องถิ่น",NULL,"ผู้อำนวยการ รพ.สต.",NULL,"primary","0","2026-03-12 22:27:09","0",NULL,NULL);
INSERT INTO `users` VALUES("7","3","นางสาวสมหญิง รักงาน",NULL,"scheduler2","$2a$12$LwO6GporWNzUqyMvZqCIWeisQIDAOgjIajwtkuK0o9GYpOQAgEEQK","SCHEDULER","ข้าราชการ/พนักงานท้องถิ่น",NULL,"พยาบาลวิชาชีพ",NULL,"primary","0","2026-03-12 22:27:09","0",NULL,NULL);
INSERT INTO `users` VALUES("8",NULL,"ปฐวีกานต์ ศรีคราม","0981051534","superadmin","$2y$10$nkwGdKN4doGa/BPs4YVoIe.i0QlihclzXeNe5X6uo6XS0urQZ9eTC","SUPERADMIN","ข้าราชการ/พนักงานท้องถิ่น",NULL,"นักวิชาการคอมพิวเตอร์ปฏิบัติการ",NULL,"primary","0","2026-03-14 13:56:50","0",NULL,NULL);
INSERT INTO `users` VALUES("9",NULL,"นางสาวเกศรินทร โอวัฒนานวคุณ",NULL,"admin3","$2y$10$Kt7m.8HkO5/t31dbUBhYnuH8pxpZyvIkitTT7ydkFGgp9GraaOsUO","HR","ข้าราชการ/พนักงานท้องถิ่น",NULL,"นักทรัพยากรบุคคลปฏิบัติการ",NULL,"primary","0","2026-04-19 00:29:23","0",NULL,NULL);

SET FOREIGN_KEY_CHECKS=1;
