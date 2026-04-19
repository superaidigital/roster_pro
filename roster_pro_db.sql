-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 19, 2026 at 06:58 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

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
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) DEFAULT NULL COMMENT 'NULL = วันหยุดส่วนกลาง',
  `status` enum('PENDING','APPROVED') DEFAULT 'APPROVED',
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`id`, `hospital_id`, `status`, `holiday_date`, `holiday_name`) VALUES
(1, NULL, 'APPROVED', '2026-01-01', 'วันขึ้นปีใหม่'),
(2, NULL, 'APPROVED', '2026-03-03', 'วันมาฆบูชา'),
(3, NULL, 'APPROVED', '2026-04-06', 'วันจักรี'),
(4, NULL, 'APPROVED', '2026-04-13', 'วันสงกรานต์'),
(5, NULL, 'APPROVED', '2026-04-14', 'วันสงกรานต์'),
(6, NULL, 'APPROVED', '2026-04-15', 'วันสงกรานต์');

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
  `logo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hospitals`
--

INSERT INTO `hospitals` (`id`, `hospital_code`, `name`, `is_active`, `address`, `sub_district`, `district`, `province`, `zipcode`, `latitude`, `longitude`, `hospital_size`, `phone`, `morning_shift`, `afternoon_shift`, `night_shift`, `created_at`, `email`, `logo`) VALUES
(1, '00000', 'กองสาธารณสุข อบจ.ศรีสะเกษ (ส่วนกลาง)', 1, 'ศาลากลางจังหวัด', 'หนองครก', 'เมืองศรีสะเกษ', 'ศรีสะเกษ', '33000', NULL, NULL, 'XL', '045-888-888', '08:30 - 16:30', '16:30 - 00:30', '00:30 - 08:30', '2026-03-12 22:27:09', NULL, NULL),
(2, '04123', 'รพ.สต. เฉลิมพระเกียรติ 60 พรรษาฯ บ้านภูมิซรอล', 1, '123 ม.1', 'เสาธงชัย', 'กันทรลักษ์', 'ศรีสะเกษ', '33110', '14.942318', '104.400040', 'M', '045-111-222', '08:30 - 16:30', '16:30 - 00:30', '00:30 - 08:30', '2026-03-12 22:27:09', NULL, NULL),
(3, '04124', 'รพ.สต. บ้านชำเม็ง', 1, '456 ม.2', 'พยุห์', 'พยุห์', 'ศรีสะเกษ', '33230', '15.232788', '104.861371', 'S', '045-333-444', '08:30 - 16:30', '16:30 - 00:30', '00:30 - 08:30', '2026-03-12 22:27:09', NULL, NULL);

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
-- Dumping data for table `leave_balances`
--

INSERT INTO `leave_balances` (`id`, `user_id`, `budget_year`, `leave_type_id`, `quota_days`, `carried_over_days`, `used_days`, `updated_at`) VALUES
(76, 6, 2026, 9, 1460, 0, 0.0, '2026-03-19 09:35:30'),
(77, 6, 2026, 10, 15, 0, 0.0, '2026-03-19 09:35:30'),
(78, 6, 2026, 11, 365, 0, 0.0, '2026-03-19 09:35:30'),
(79, 7, 2026, 7, 0, 0, 0.0, '2026-03-19 09:35:31'),
(80, 7, 2026, 8, 365, 0, 0.0, '2026-03-19 09:35:31'),
(81, 7, 2026, 9, 1460, 0, 0.0, '2026-03-19 09:35:31'),
(82, 7, 2026, 10, 15, 0, 0.0, '2026-03-19 09:35:31'),
(83, 7, 2026, 11, 365, 0, 0.0, '2026-03-19 09:35:31'),
(84, 3, 2027, 1, 60, 0, 0.0, '2026-03-19 14:15:09'),
(85, 3, 2027, 2, 90, 0, 0.0, '2026-03-19 14:15:09'),
(86, 3, 2027, 3, 15, 0, 0.0, '2026-03-19 14:15:09'),
(87, 3, 2027, 4, 10, 0, 0.0, '2026-04-18 17:42:25'),
(88, 3, 2027, 5, 120, 0, 0.0, '2026-03-19 14:15:09'),
(89, 3, 2027, 6, 30, 0, 0.0, '2026-03-19 14:15:09'),
(90, 3, 2027, 7, 0, 0, 0.0, '2026-03-19 14:15:09'),
(91, 3, 2027, 8, 365, 0, 0.0, '2026-03-19 14:15:09'),
(92, 3, 2027, 9, 1460, 0, 0.0, '2026-03-19 14:15:09'),
(93, 3, 2027, 10, 15, 0, 0.0, '2026-03-19 14:15:09'),
(94, 3, 2027, 11, 365, 0, 0.0, '2026-03-19 14:15:09'),
(95, 3, 2024, 1, 60, 0, 0.0, '2026-03-19 14:15:11'),
(96, 3, 2024, 2, 90, 0, 0.0, '2026-03-19 14:15:11'),
(97, 3, 2024, 3, 45, 0, 0.0, '2026-03-19 14:15:11'),
(98, 3, 2024, 4, 10, 0, 0.0, '2026-03-19 14:15:11'),
(99, 3, 2024, 5, 120, 0, 0.0, '2026-03-19 14:15:11'),
(100, 3, 2024, 6, 30, 0, 0.0, '2026-03-19 14:15:11'),
(111, 4, 2026, 1, 60, 0, 0.0, '2026-03-22 04:07:50'),
(112, 4, 2026, 2, 90, 0, 0.0, '2026-03-20 13:40:52'),
(113, 4, 2026, 3, 15, 0, 0.0, '2026-03-20 13:40:52'),
(114, 4, 2026, 4, 10, 0, 0.0, '2026-04-18 17:42:25'),
(115, 4, 2026, 5, 120, 0, 0.0, '2026-03-20 13:40:52'),
(116, 4, 2026, 6, 30, 0, 0.0, '2026-03-20 13:40:52'),
(117, 4, 2026, 7, 0, 0, 0.0, '2026-03-20 13:40:52'),
(118, 4, 2026, 8, 365, 0, 0.0, '2026-03-20 13:40:52'),
(119, 4, 2026, 9, 1460, 0, 0.0, '2026-03-20 13:40:52'),
(120, 4, 2026, 10, 15, 0, 0.0, '2026-03-20 13:40:52'),
(121, 4, 2026, 11, 365, 0, 0.0, '2026-03-20 13:40:52'),
(122, 3, 2026, 1, 60, 0, 0.0, '2026-03-20 13:40:55'),
(123, 3, 2026, 2, 90, 0, 0.0, '2026-03-20 13:40:55'),
(124, 3, 2026, 3, 15, 0, 0.0, '2026-03-20 13:40:55'),
(125, 3, 2026, 4, 10, 0, 0.0, '2026-04-18 17:42:25'),
(126, 3, 2026, 5, 120, 0, 0.0, '2026-03-20 13:40:55'),
(127, 3, 2026, 6, 30, 0, 0.0, '2026-03-20 13:40:55'),
(128, 3, 2026, 7, 0, 0, 0.0, '2026-03-20 13:40:55'),
(129, 3, 2026, 8, 365, 0, 0.0, '2026-03-20 13:40:55'),
(130, 3, 2026, 9, 1460, 0, 0.0, '2026-03-20 13:40:55'),
(131, 3, 2026, 10, 15, 0, 0.0, '2026-03-20 13:40:55'),
(132, 3, 2026, 11, 365, 0, 0.0, '2026-03-20 13:40:55'),
(133, 2, 2026, 1, 60, 0, 0.0, '2026-03-20 13:40:57'),
(134, 2, 2026, 2, 90, 0, 0.0, '2026-03-20 13:40:57'),
(135, 2, 2026, 3, 15, 0, 0.0, '2026-03-20 13:40:57'),
(136, 2, 2026, 4, 10, 0, 0.0, '2026-04-18 17:42:25'),
(137, 2, 2026, 5, 120, 0, 0.0, '2026-03-20 13:40:57'),
(138, 2, 2026, 6, 30, 0, 0.0, '2026-03-20 13:40:57'),
(139, 2, 2026, 7, 0, 0, 0.0, '2026-03-20 13:40:57'),
(140, 2, 2026, 8, 365, 0, 0.0, '2026-03-20 13:40:57'),
(141, 2, 2026, 9, 1460, 0, 0.0, '2026-03-20 13:40:57'),
(142, 2, 2026, 10, 15, 0, 0.0, '2026-03-20 13:40:57'),
(143, 2, 2026, 11, 365, 0, 0.0, '2026-03-20 13:40:57'),
(144, 1, 2026, 1, 60, 0, 0.0, '2026-03-20 13:40:58'),
(145, 1, 2026, 2, 90, 0, 0.0, '2026-03-20 13:40:58'),
(146, 1, 2026, 3, 15, 0, 0.0, '2026-03-20 13:40:58'),
(147, 1, 2026, 4, 10, 0, 0.0, '2026-03-20 13:40:58'),
(148, 1, 2026, 5, 120, 0, 0.0, '2026-03-20 13:40:58'),
(149, 1, 2026, 6, 30, 0, 0.0, '2026-03-20 13:40:58'),
(150, 1, 2026, 7, 0, 0, 0.0, '2026-03-20 13:40:58'),
(151, 1, 2026, 8, 365, 0, 0.0, '2026-03-20 13:40:58'),
(152, 1, 2026, 9, 1460, 0, 0.0, '2026-03-20 13:40:58'),
(153, 1, 2026, 10, 15, 0, 0.0, '2026-03-20 13:40:58'),
(154, 1, 2026, 11, 365, 0, 0.0, '2026-03-20 13:40:58'),
(155, 8, 2026, 1, 60, 0, 0.0, '2026-03-20 14:28:01'),
(156, 8, 2026, 2, 90, 0, 0.0, '2026-03-20 14:28:01'),
(157, 8, 2026, 3, 15, 0, 0.0, '2026-03-20 14:28:01'),
(158, 8, 2026, 4, 10, 0, 0.0, '2026-03-22 03:55:20'),
(159, 8, 2026, 5, 120, 0, 0.0, '2026-03-20 14:28:01'),
(160, 8, 2026, 6, 30, 0, 0.0, '2026-03-20 14:28:01'),
(161, 8, 2026, 7, 0, 0, 0.0, '2026-03-20 14:28:01'),
(162, 8, 2026, 8, 365, 0, 0.0, '2026-03-20 14:28:01'),
(163, 8, 2026, 9, 1460, 0, 0.0, '2026-03-20 14:28:01'),
(164, 8, 2026, 10, 15, 0, 0.0, '2026-03-20 14:28:01'),
(165, 8, 2026, 11, 365, 0, 0.0, '2026-03-20 14:28:01'),
(166, 5, 2026, 1, 60, 0, 0.0, '2026-03-21 11:48:34'),
(167, 5, 2026, 2, 90, 0, 0.0, '2026-03-21 11:48:34'),
(168, 5, 2026, 3, 15, 0, 0.0, '2026-03-21 11:48:35'),
(169, 5, 2026, 4, 10, 0, 0.0, '2026-04-18 17:42:25'),
(170, 5, 2026, 5, 120, 0, 0.0, '2026-03-21 11:48:35'),
(171, 5, 2026, 6, 30, 0, 0.0, '2026-03-21 11:48:35'),
(172, 5, 2026, 7, 0, 0, 0.0, '2026-03-21 11:48:35'),
(173, 5, 2026, 8, 365, 0, 0.0, '2026-03-21 11:48:35'),
(174, 5, 2026, 9, 1460, 0, 0.0, '2026-03-21 11:48:35'),
(175, 5, 2026, 10, 15, 0, 0.0, '2026-03-21 11:48:35'),
(176, 5, 2026, 11, 365, 0, 0.0, '2026-03-21 11:48:35'),
(177, 6, 2026, 1, 60, 0, 0.0, '2026-03-21 11:48:38'),
(178, 6, 2026, 2, 90, 0, 0.0, '2026-03-21 11:48:38'),
(179, 6, 2026, 3, 15, 0, 0.0, '2026-03-21 11:48:38'),
(180, 6, 2026, 4, 10, 0, 0.0, '2026-03-21 11:48:38'),
(181, 6, 2026, 5, 120, 0, 0.0, '2026-03-21 11:48:38'),
(182, 6, 2026, 6, 30, 0, 0.0, '2026-03-21 11:48:38'),
(183, 6, 2026, 7, 0, 0, 0.0, '2026-03-21 11:48:38'),
(184, 6, 2026, 8, 365, 0, 0.0, '2026-03-21 11:48:38'),
(185, 7, 2026, 1, 60, 0, 0.0, '2026-03-21 11:57:02'),
(186, 7, 2026, 2, 90, 0, 0.0, '2026-03-21 11:57:02'),
(187, 7, 2026, 3, 15, 0, 0.0, '2026-03-21 11:57:02'),
(188, 7, 2026, 4, 10, 0, 0.0, '2026-03-21 11:57:02'),
(189, 7, 2026, 5, 120, 0, 0.0, '2026-03-21 11:57:02'),
(190, 7, 2026, 6, 30, 0, 0.0, '2026-03-21 11:57:02'),
(191, 2, 2025, 1, 60, 0, 0.0, '2026-03-22 03:50:16'),
(192, 2, 2025, 2, 90, 0, 0.0, '2026-03-22 03:50:16'),
(193, 2, 2025, 3, 45, 0, 0.0, '2026-03-22 03:50:16'),
(194, 2, 2025, 4, 10, 0, 0.0, '2026-03-22 03:50:16'),
(195, 2, 2025, 5, 120, 0, 0.0, '2026-03-22 03:50:16'),
(196, 2, 2025, 6, 30, 0, 0.0, '2026-03-22 03:50:16'),
(197, 2, 2025, 7, 0, 0, 0.0, '2026-03-22 03:50:16'),
(198, 2, 2025, 8, 365, 0, 0.0, '2026-03-22 03:50:16'),
(199, 2, 2025, 9, 1460, 0, 0.0, '2026-03-22 03:50:16'),
(200, 2, 2025, 10, 15, 0, 0.0, '2026-03-22 03:50:16'),
(201, 2, 2025, 11, 365, 0, 0.0, '2026-03-22 03:50:16'),
(202, 5, 2025, 1, 60, 0, 0.0, '2026-03-22 03:50:16'),
(203, 5, 2025, 2, 90, 0, 0.0, '2026-03-22 03:50:16'),
(204, 5, 2025, 3, 45, 0, 0.0, '2026-03-22 03:50:17'),
(205, 5, 2025, 4, 10, 0, 0.0, '2026-03-22 03:50:17'),
(206, 5, 2025, 5, 120, 0, 0.0, '2026-03-22 03:50:17'),
(207, 5, 2025, 6, 30, 0, 0.0, '2026-03-22 03:50:17'),
(208, 5, 2025, 7, 0, 0, 0.0, '2026-03-22 03:50:17'),
(209, 5, 2025, 8, 365, 0, 0.0, '2026-03-22 03:50:17'),
(210, 5, 2025, 9, 1460, 0, 0.0, '2026-03-22 03:50:17'),
(211, 5, 2025, 10, 15, 0, 0.0, '2026-03-22 03:50:17'),
(212, 5, 2025, 11, 365, 0, 0.0, '2026-03-22 03:50:17'),
(213, 3, 2025, 1, 60, 0, 0.0, '2026-03-22 03:50:17'),
(214, 3, 2025, 2, 90, 0, 0.0, '2026-03-22 03:50:17'),
(215, 3, 2025, 3, 45, 0, 0.0, '2026-03-22 03:50:17'),
(216, 3, 2025, 4, 10, 0, 0.0, '2026-04-18 17:42:25'),
(217, 3, 2025, 5, 120, 0, 0.0, '2026-03-22 03:50:17'),
(218, 3, 2025, 6, 30, 0, 0.0, '2026-03-22 03:50:17'),
(219, 3, 2025, 7, 0, 0, 0.0, '2026-03-22 03:50:17'),
(220, 3, 2025, 8, 365, 0, 0.0, '2026-03-22 03:50:17'),
(221, 3, 2025, 9, 1460, 0, 0.0, '2026-03-22 03:50:17'),
(222, 3, 2025, 10, 15, 0, 0.0, '2026-03-22 03:50:17'),
(223, 3, 2025, 11, 365, 0, 0.0, '2026-03-22 03:50:17'),
(224, 4, 2025, 1, 60, 0, 0.0, '2026-03-22 03:50:17'),
(225, 4, 2025, 2, 90, 0, 0.0, '2026-03-22 03:50:17'),
(226, 4, 2025, 3, 45, 0, 0.0, '2026-03-22 03:50:17'),
(227, 4, 2025, 4, 10, 0, 0.0, '2026-03-22 03:50:17'),
(228, 4, 2025, 5, 120, 0, 0.0, '2026-03-22 03:50:17'),
(229, 4, 2025, 6, 30, 0, 0.0, '2026-03-22 03:50:17'),
(230, 4, 2025, 7, 0, 0, 0.0, '2026-03-22 03:50:17'),
(231, 4, 2025, 8, 365, 0, 0.0, '2026-03-22 03:50:17'),
(232, 4, 2025, 9, 1460, 0, 0.0, '2026-03-22 03:50:17'),
(233, 4, 2025, 10, 15, 0, 0.0, '2026-03-22 03:50:17'),
(234, 4, 2025, 11, 365, 0, 0.0, '2026-03-22 03:50:17'),
(235, 6, 2025, 1, 60, 0, 0.0, '2026-03-22 03:50:17'),
(236, 6, 2025, 2, 90, 0, 0.0, '2026-03-22 03:50:17'),
(237, 6, 2025, 3, 45, 0, 0.0, '2026-03-22 03:50:17'),
(238, 6, 2025, 4, 10, 0, 0.0, '2026-03-22 03:50:17'),
(239, 6, 2025, 5, 120, 0, 0.0, '2026-03-22 03:50:17'),
(240, 6, 2025, 6, 30, 0, 0.0, '2026-03-22 03:50:17'),
(241, 6, 2025, 7, 0, 0, 0.0, '2026-03-22 03:50:17'),
(242, 6, 2025, 8, 365, 0, 0.0, '2026-03-22 03:50:17'),
(243, 6, 2025, 9, 1460, 0, 0.0, '2026-03-22 03:50:17'),
(244, 6, 2025, 10, 15, 0, 0.0, '2026-03-22 03:50:17'),
(245, 6, 2025, 11, 365, 0, 0.0, '2026-03-22 03:50:17'),
(246, 7, 2025, 1, 60, 0, 0.0, '2026-03-22 03:50:17'),
(247, 7, 2025, 2, 90, 0, 0.0, '2026-03-22 03:50:17'),
(248, 7, 2025, 3, 45, 0, 0.0, '2026-03-22 03:50:17'),
(249, 7, 2025, 4, 10, 0, 0.0, '2026-03-22 03:50:17'),
(250, 7, 2025, 5, 120, 0, 0.0, '2026-03-22 03:50:17'),
(251, 7, 2025, 6, 30, 0, 0.0, '2026-03-22 03:50:17'),
(252, 7, 2025, 7, 0, 0, 0.0, '2026-03-22 03:50:17'),
(253, 7, 2025, 8, 365, 0, 0.0, '2026-03-22 03:50:17'),
(254, 7, 2025, 9, 1460, 0, 0.0, '2026-03-22 03:50:17'),
(255, 7, 2025, 10, 15, 0, 0.0, '2026-03-22 03:50:17'),
(256, 7, 2025, 11, 365, 0, 0.0, '2026-03-22 03:50:17'),
(257, 2, 2024, 1, 60, 0, 0.0, '2026-03-22 03:50:18'),
(258, 2, 2024, 2, 90, 0, 0.0, '2026-03-22 03:50:18'),
(259, 2, 2024, 3, 45, 0, 0.0, '2026-03-22 03:50:18'),
(260, 2, 2024, 4, 10, 0, 0.0, '2026-03-22 03:50:18'),
(261, 2, 2024, 5, 120, 0, 0.0, '2026-03-22 03:50:18'),
(262, 2, 2024, 6, 30, 0, 0.0, '2026-03-22 03:50:18'),
(263, 2, 2024, 7, 0, 0, 0.0, '2026-03-22 03:50:19'),
(264, 2, 2024, 8, 365, 0, 0.0, '2026-03-22 03:50:19'),
(265, 2, 2024, 9, 1460, 0, 0.0, '2026-03-22 03:50:19'),
(266, 2, 2024, 10, 15, 0, 0.0, '2026-03-22 03:50:19'),
(267, 2, 2024, 11, 365, 0, 0.0, '2026-03-22 03:50:19'),
(268, 5, 2024, 1, 60, 0, 0.0, '2026-03-22 03:50:19'),
(269, 5, 2024, 2, 90, 0, 0.0, '2026-03-22 03:50:19'),
(270, 5, 2024, 3, 45, 0, 0.0, '2026-03-22 03:50:19'),
(271, 5, 2024, 4, 10, 0, 0.0, '2026-03-22 03:50:19'),
(272, 5, 2024, 5, 120, 0, 0.0, '2026-03-22 03:50:19'),
(273, 5, 2024, 6, 30, 0, 0.0, '2026-03-22 03:50:19'),
(274, 5, 2024, 7, 0, 0, 0.0, '2026-03-22 03:50:19'),
(275, 5, 2024, 8, 365, 0, 0.0, '2026-03-22 03:50:19'),
(276, 5, 2024, 9, 1460, 0, 0.0, '2026-03-22 03:50:19'),
(277, 5, 2024, 10, 15, 0, 0.0, '2026-03-22 03:50:19'),
(278, 5, 2024, 11, 365, 0, 0.0, '2026-03-22 03:50:19'),
(279, 3, 2024, 7, 0, 0, 0.0, '2026-03-22 03:50:19'),
(280, 3, 2024, 8, 365, 0, 0.0, '2026-03-22 03:50:19'),
(281, 3, 2024, 9, 1460, 0, 0.0, '2026-03-22 03:50:19'),
(282, 3, 2024, 10, 15, 0, 0.0, '2026-03-22 03:50:19'),
(283, 3, 2024, 11, 365, 0, 0.0, '2026-03-22 03:50:19'),
(284, 4, 2024, 1, 60, 0, 0.0, '2026-03-22 03:50:19'),
(285, 4, 2024, 2, 90, 0, 0.0, '2026-03-22 03:50:19'),
(286, 4, 2024, 3, 45, 0, 0.0, '2026-03-22 03:50:19'),
(287, 4, 2024, 4, 10, 0, 0.0, '2026-03-22 03:50:19'),
(288, 4, 2024, 5, 120, 0, 0.0, '2026-03-22 03:50:19'),
(289, 4, 2024, 6, 30, 0, 0.0, '2026-03-22 03:50:19'),
(290, 4, 2024, 7, 0, 0, 0.0, '2026-03-22 03:50:19'),
(291, 4, 2024, 8, 365, 0, 0.0, '2026-03-22 03:50:19'),
(292, 4, 2024, 9, 1460, 0, 0.0, '2026-03-22 03:50:19'),
(293, 4, 2024, 10, 15, 0, 0.0, '2026-03-22 03:50:19'),
(294, 4, 2024, 11, 365, 0, 0.0, '2026-03-22 03:50:19'),
(295, 6, 2024, 1, 60, 0, 0.0, '2026-03-22 03:50:19'),
(296, 6, 2024, 2, 90, 0, 0.0, '2026-03-22 03:50:19'),
(297, 6, 2024, 3, 45, 0, 0.0, '2026-03-22 03:50:19'),
(298, 6, 2024, 4, 10, 0, 0.0, '2026-03-22 03:50:19'),
(299, 6, 2024, 5, 120, 0, 0.0, '2026-03-22 03:50:19'),
(300, 6, 2024, 6, 30, 0, 0.0, '2026-03-22 03:50:19'),
(301, 6, 2024, 7, 0, 0, 0.0, '2026-03-22 03:50:19'),
(302, 6, 2024, 8, 365, 0, 0.0, '2026-03-22 03:50:19'),
(303, 6, 2024, 9, 1460, 0, 0.0, '2026-03-22 03:50:19'),
(304, 6, 2024, 10, 15, 0, 0.0, '2026-03-22 03:50:19'),
(305, 6, 2024, 11, 365, 0, 0.0, '2026-03-22 03:50:19'),
(306, 7, 2024, 1, 60, 0, 0.0, '2026-03-22 03:50:19'),
(307, 7, 2024, 2, 90, 0, 0.0, '2026-03-22 03:50:19'),
(308, 7, 2024, 3, 45, 0, 0.0, '2026-03-22 03:50:19'),
(309, 7, 2024, 4, 10, 0, 0.0, '2026-03-22 03:50:19'),
(310, 7, 2024, 5, 120, 0, 0.0, '2026-03-22 03:50:19'),
(311, 7, 2024, 6, 30, 0, 0.0, '2026-03-22 03:50:19'),
(312, 7, 2024, 7, 0, 0, 0.0, '2026-03-22 03:50:19'),
(313, 7, 2024, 8, 365, 0, 0.0, '2026-03-22 03:50:19'),
(314, 7, 2024, 9, 1460, 0, 0.0, '2026-03-22 03:50:19'),
(315, 7, 2024, 10, 15, 0, 0.0, '2026-03-22 03:50:19'),
(316, 7, 2024, 11, 365, 0, 0.0, '2026-03-22 03:50:19'),
(317, 9, 2026, 1, 60, 0, 0.0, '2026-04-18 17:37:35'),
(318, 9, 2026, 2, 90, 0, 0.0, '2026-04-18 17:37:35'),
(319, 9, 2026, 3, 15, 0, 0.0, '2026-04-18 17:37:35'),
(320, 9, 2026, 4, 10, 0, 0.0, '2026-04-18 17:37:36'),
(321, 9, 2026, 5, 120, 0, 0.0, '2026-04-18 17:37:36'),
(322, 9, 2026, 6, 30, 0, 0.0, '2026-04-18 17:37:36'),
(323, 9, 2026, 7, 0, 0, 0.0, '2026-04-18 17:37:36'),
(324, 9, 2026, 8, 365, 0, 0.0, '2026-04-18 17:37:36'),
(325, 9, 2026, 9, 1460, 0, 0.0, '2026-04-18 17:37:36'),
(326, 9, 2026, 10, 15, 0, 0.0, '2026-04-18 17:37:36'),
(327, 9, 2026, 11, 365, 0, 0.0, '2026-04-18 17:37:36');

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
-- Dumping data for table `leave_quotas`
--

INSERT INTO `leave_quotas` (`id`, `leave_type`, `max_days`, `calculation_type`, `description`) VALUES
(1, 'ลาป่วย', 60.0, 'WORKING_DAYS', 'ลาป่วยตามจริง (รับเงินเดือนไม่เกิน 60 วัน)'),
(2, 'ลาคลอดบุตร', 90.0, 'WORKING_DAYS', 'ลาคลอดบุตร (นับรวมวันหยุด)'),
(3, 'ลากิจส่วนตัว', 45.0, 'WORKING_DAYS', 'ลากิจส่วนตัว (ปีแรก 15 วัน)'),
(4, 'ลาพักผ่อน', 10.0, 'WORKING_DAYS', 'ลาพักผ่อนประจำปี (สะสมได้ตามระเบียบ)'),
(5, 'ลาอุปสมบท/ฮัจย์', 120.0, 'WORKING_DAYS', 'ต้องทำงานมาแล้วไม่น้อยกว่า 1 ปี'),
(6, 'ลาเข้ารับการคัดเลือก/เตรียมพล', 30.0, 'WORKING_DAYS', 'ลาได้ตามระยะเวลาที่เข้าฝึก (ไม่เกิน 30 วัน)'),
(7, 'ลาไปศึกษา ฝึกอบรม ดูงาน', 0.0, 'WORKING_DAYS', 'พิจารณาอนุญาตเป็นรายกรณีโดยผู้มีอำนาจ'),
(8, 'ลาไปปฏิบัติงานในองค์การระหว่างประเทศ', 365.0, 'WORKING_DAYS', 'มีสิทธิลาได้ไม่เกิน 1 ปี'),
(9, 'ลาติดตามคู่สมรส', 1460.0, 'WORKING_DAYS', 'ลาได้ 2 ปี แต่ไม่เกิน 4 ปี (1,460 วัน)'),
(10, 'ลาไปช่วยเหลือภริยาคลอดบุตร', 15.0, 'WORKING_DAYS', 'เฉพาะข้าราชการชาย (ภายใน 90 วันหลังคลอด)'),
(11, 'ลาไปฟื้นฟูสมรรถภาพด้านอาชีพ', 365.0, 'WORKING_DAYS', 'ไม่เกิน 12 เดือน (กรณีบาดเจ็บจากการปฏิบัติหน้าที่)');

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
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 8, 'DELETE', 'FACTORY RESET: ล้างข้อมูลระบบปฏิบัติการทั้งหมดเริ่มต้นรอบปีใหม่', '::1', '2026-04-19 00:42:25'),
(2, 2, 'DOWNLOAD', 'ดาวน์โหลดตารางเวรรูปแบบ Word เดือน 2026-04', '::1', '2026-04-19 11:53:35');

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
-- Dumping data for table `pay_rates`
--

INSERT INTO `pay_rates` (`id`, `name`, `group_level`, `group_name`, `keywords`, `rate_y`, `rate_b`, `rate_r`, `updated_at`, `display_order`) VALUES
(1, 'พยาบาล,แพทย์,นัก,ปริญญาตรี,สหวิชาชีพ,ป.ตรี', 1, 'กลุ่มที่ 1: สายงานวิชาชีพ / ป.ตรี', 'พยาบาล,แพทย์,นัก,ปริญญาตรี,สหวิชาชีพ,ป.ตรี', 650, 320, 150, '2026-04-18 15:52:34', 0),
(2, 'เจ้าพนักงาน', 2, 'กลุ่มที่ 2: สายงานเจ้าพนักงาน', 'เจ้าพนักงาน', 520, 240, 0, '2026-04-18 15:52:34', 0),
(3, 'ลูกจ้าง,พนักงานกระทรวง,ช่วยเหลือคนไข้', 3, 'กลุ่มที่ 3: เจ้าหน้าที่อื่นๆ', 'ลูกจ้าง,พนักงานกระทรวง,ช่วยเหลือคนไข้', 330, 165, 0, '2026-04-18 15:52:34', 0);

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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Dumping data for table `system_menus`
--

INSERT INTO `system_menus` (`id`, `menu_name`, `icon`, `controller`, `action`, `allowed_roles`, `display_order`, `is_active`, `is_core`, `sort_order`) VALUES
(1, 'แดชบอร์ดสถิติ', 'bi-pie-chart-fill', 'dashboard', 'index', 'SUPERADMIN,ADMIN,DIRECTOR,SCHEDULER,STAFF,HR', 0, 1, 0, 10),
(2, 'ตารางปฏิบัติงาน', 'bi-calendar-event-fill', 'roster', 'index', 'DIRECTOR,SCHEDULER,STAFF', 0, 1, 0, 20),
(3, 'ติดตามการส่งเวร', 'bi-bar-chart-line-fill', 'report', 'overview', 'SUPERADMIN,ADMIN,DIRECTOR,SCHEDULER,HR', 0, 1, 0, 30),
(4, 'ระบบจัดการวันลา', 'bi-calendar-x-fill', 'leave', 'index', 'SUPERADMIN,ADMIN,DIRECTOR,SCHEDULER,STAFF,HR', 0, 1, 0, 40),
(5, 'จัดการบุคลากร', 'bi-people-fill', 'staff', 'index', 'SUPERADMIN,ADMIN,DIRECTOR,SCHEDULER,HR', 0, 1, 0, 50),
(6, 'ตั้งค่าระบบส่วนกลาง', 'bi-gear-wide-connected', 'settings', 'system', 'SUPERADMIN,SUPERADMIN', 0, 1, 1, 99),
(7, 'ประวัติการแจ้งเตือน', 'bi-bell-fill', 'notification', 'index', '', 0, 1, 0, 80),
(8, 'ฐานข้อมูลบุคลากร', 'bi-people-fill', 'users', 'index', 'SUPERADMIN,SUPERADMIN,ADMIN,HR', 0, 1, 0, 45),
(9, 'จัดการ รพ.สต.', 'bi-hospital-fill', 'hospitals', 'index', 'SUPERADMIN,ADMIN', 0, 1, 0, 35),
(11, 'ปฏิทินเวรของฉัน', 'bi-calendar-heart', 'profile', 'schedule', 'ADMIN,DIRECTOR,SCHEDULER,STAFF,HR', 0, 1, 0, 15),
(12, 'โปรไฟล์และการตั้งค่า', 'bi-person-badge', 'profile', 'index', 'ADMIN,DIRECTOR,SCHEDULER,STAFF,HR', 0, 1, 0, 90);

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
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('app_name', 'Roster Pro', NULL),
('contact_email', '', NULL),
('contact_phone', '', NULL),
('line_notify_on_holiday', '0', 'เปิดแจ้งเตือนเมื่อขอเพิ่มวันหยุด (1=เปิด, 0=ปิด)'),
('line_notify_on_request', '0', 'เปิดแจ้งเตือนเมื่อขอปลดล็อคแก้ไข (1=เปิด, 0=ปิด)'),
('line_notify_on_submit', '0', 'เปิดแจ้งเตือนเมื่อ รพ.สต. ส่งเวร (1=เปิด, 0=ปิด)'),
('line_notify_token', '', 'Token สำหรับส่งแจ้งเตือนเข้ากลุ่มส่วนกลาง'),
('log_retention_days', '90', NULL),
('maintenance_mode', '0', NULL),
('system_announcement', '', NULL);

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
  `employee_type` enum('ข้าราชการ/พนักงานท้องถิ่น','พนักงานจ้างตามภารกิจ','พนักงานจ้างทั่วไป') NOT NULL DEFAULT 'ข้าราชการ/พนักงานท้องถิ่น',
  `start_date` date DEFAULT NULL COMMENT 'วันที่บรรจุ/เริ่มงาน',
  `type` varchar(100) DEFAULT NULL COMMENT 'วิชาชีพ/ตำแหน่ง',
  `position_number` varchar(50) DEFAULT NULL,
  `color_theme` varchar(20) DEFAULT 'primary',
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `display_order` int(11) NOT NULL DEFAULT 0,
  `id_card` varchar(13) DEFAULT NULL,
  `pay_rate_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `hospital_id`, `name`, `phone`, `username`, `password`, `role`, `employee_type`, `start_date`, `type`, `position_number`, `color_theme`, `sort_order`, `created_at`, `display_order`, `id_card`, `pay_rate_id`) VALUES
(1, 1, 'ผู้ดูแลระบบ ส่วนกลาง', '', 'admin', '$2y$10$iw3RmD6E5Y6QJ/7n4DnoSu6QyC6yj7MRVNOwX2IPUG.5ILpbYuHKu', 'ADMIN', 'ข้าราชการ/พนักงานท้องถิ่น', NULL, 'นักวิชาการคอมพิวเตอร์', NULL, 'primary', 0, '2026-03-12 22:27:09', 0, NULL, NULL),
(2, 2, 'นางเขมจิรา จันทร', NULL, 'director1', '$2a$12$LwO6GporWNzUqyMvZqCIWeisQIDAOgjIajwtkuK0o9GYpOQAgEEQK', 'DIRECTOR', 'ข้าราชการ/พนักงานท้องถิ่น', NULL, 'ผู้อำนวยการ รพ.สต.', NULL, 'primary', 1, '2026-03-12 22:27:09', 1, NULL, 1),
(3, 2, 'นางนภัส สิทธิโชค', NULL, 'scheduler1', '$2a$12$LwO6GporWNzUqyMvZqCIWeisQIDAOgjIajwtkuK0o9GYpOQAgEEQK', 'SCHEDULER', 'ข้าราชการ/พนักงานท้องถิ่น', NULL, 'พยาบาลวิชาชีพ', NULL, 'primary', 2, '2026-03-12 22:27:09', 3, NULL, 1),
(4, 2, 'นางสาวสุภาพร ศรีชำนาญชาญชัย', NULL, 'staff1', '$2a$12$LwO6GporWNzUqyMvZqCIWeisQIDAOgjIajwtkuK0o9GYpOQAgEEQK', 'STAFF', 'ข้าราชการ/พนักงานท้องถิ่น', '2020-02-03', 'แพทย์แผนไทย', NULL, 'primary', 4, '2026-03-12 22:27:09', 4, NULL, 3),
(5, 2, 'นายชนินทร์ แสงนวล', NULL, 'staff2', '$2a$12$LwO6GporWNzUqyMvZqCIWeisQIDAOgjIajwtkuK0o9GYpOQAgEEQK', 'STAFF', 'ข้าราชการ/พนักงานท้องถิ่น', NULL, 'นักวิชาการสาธารณสุข', NULL, 'primary', 3, '2026-03-12 22:27:09', 2, NULL, 1),
(6, 3, 'นายสมชาย ใจดี', NULL, 'director2', '$2a$12$LwO6GporWNzUqyMvZqCIWeisQIDAOgjIajwtkuK0o9GYpOQAgEEQK', 'DIRECTOR', 'ข้าราชการ/พนักงานท้องถิ่น', NULL, 'ผู้อำนวยการ รพ.สต.', NULL, 'primary', 0, '2026-03-12 22:27:09', 0, NULL, NULL),
(7, 3, 'นางสาวสมหญิง รักงาน', NULL, 'scheduler2', '$2a$12$LwO6GporWNzUqyMvZqCIWeisQIDAOgjIajwtkuK0o9GYpOQAgEEQK', 'SCHEDULER', 'ข้าราชการ/พนักงานท้องถิ่น', NULL, 'พยาบาลวิชาชีพ', NULL, 'primary', 0, '2026-03-12 22:27:09', 0, NULL, NULL),
(8, NULL, 'ปฐวีกานต์ ศรีคราม', '0981051534', 'superadmin', '$2y$10$nkwGdKN4doGa/BPs4YVoIe.i0QlihclzXeNe5X6uo6XS0urQZ9eTC', 'SUPERADMIN', 'ข้าราชการ/พนักงานท้องถิ่น', NULL, 'นักวิชาการคอมพิวเตอร์ปฏิบัติการ', NULL, 'primary', 0, '2026-03-14 13:56:50', 0, NULL, NULL),
(9, NULL, 'นางสาวเกศรินทร โอวัฒนานวคุณ', NULL, 'admin3', '$2y$10$Kt7m.8HkO5/t31dbUBhYnuH8pxpZyvIkitTT7ydkFGgp9GraaOsUO', 'HR', 'ข้าราชการ/พนักงานท้องถิ่น', NULL, 'นักทรัพยากรบุคคลปฏิบัติการ', NULL, 'primary', 0, '2026-04-19 00:29:23', 0, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`);

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
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

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
  ADD UNIQUE KEY `user_date_unique` (`user_id`,`shift_date`);

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
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `hospitals`
--
ALTER TABLE `hospitals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=328;

--
-- AUTO_INCREMENT for table `leave_quotas`
--
ALTER TABLE `leave_quotas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pay_rates`
--
ALTER TABLE `pay_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `roster_status`
--
ALTER TABLE `roster_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
