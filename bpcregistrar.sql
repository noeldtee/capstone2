-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 27, 2025 at 08:27 AM
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
-- Database: `bpcregistrar`
--

-- --------------------------------------------------------

--
-- Table structure for table `action_logs`
--

CREATE TABLE `action_logs` (
  `id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `performed_by` int(11) NOT NULL,
  `target` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `name`, `code`, `description`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'Bachelor of Science in Information Systems', 'BSIS', '', '2025-04-20 09:06:51', '2025-04-20 09:06:51', 1);

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `form_needed` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = Needed, 0 = Not Needed',
  `requirements` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `restrict_per_semester` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `name`, `description`, `unit_price`, `form_needed`, `requirements`, `is_active`, `created_at`, `updated_at`, `restrict_per_semester`) VALUES
(1, 'Certificate of Registration', 'COR', 0.00, 0, NULL, 1, '2025-04-20 09:09:33', '2025-04-20 09:12:56', 1),
(2, 'Certificate of Grades', 'COG', 50.00, 0, NULL, 1, '2025-04-20 09:13:09', '2025-04-20 09:13:16', 0),
(3, 'Transcript of Records', 'TOR', 50.00, 1, 'Clearance Form', 1, '2025-04-20 09:13:35', '2025-04-27 06:25:02', 0);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `link` varchar(255) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status` enum('PENDING','PAID','FAILED') NOT NULL DEFAULT 'PENDING',
  `description` text NOT NULL,
  `payment_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `amount` int(11) NOT NULL,
  `payment_id` varchar(100) DEFAULT NULL,
  `payment_link_id` varchar(100) DEFAULT NULL,
  `status` enum('Pending','In Process','Ready to Pickup','To Release','Completed','Rejected') NOT NULL DEFAULT 'Pending',
  `archived` tinyint(1) DEFAULT 0,
  `file_path` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `requested_date` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `payment_status` enum('unpaid','Pending','Awaiting Payment','paid','failed') NOT NULL DEFAULT 'unpaid',
  `pickup_token` varchar(64) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `year_id` int(11) DEFAULT NULL,
  `semester` varchar(50) NOT NULL DEFAULT '',
  `payment_method` enum('cash','online') NOT NULL DEFAULT 'cash'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_years`
--

CREATE TABLE `school_years` (
  `id` int(11) NOT NULL,
  `year` varchar(10) NOT NULL COMMENT 'Format: YYYY-YYYY (e.g., 2024-2025)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('Current','Past','Inactive') NOT NULL DEFAULT 'Current'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_years`
--

INSERT INTO `school_years` (`id`, `year`, `created_at`, `updated_at`, `status`) VALUES
(1, '2024-2025', '2025-04-20 09:07:03', '2025-04-20 09:07:03', 'Current'),
(2, '2021-2022', '2025-04-20 09:07:11', '2025-04-20 09:07:11', 'Past');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `school_year_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year') NOT NULL,
  `section` varchar(50) NOT NULL COMMENT 'e.g., Section A',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('Current','Past','Inactive') NOT NULL DEFAULT 'Current'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `school_year_id`, `course_id`, `year_level`, `section`, `created_at`, `updated_at`, `status`) VALUES
(1, 1, 1, '1st Year', 'A', '2025-04-20 09:07:28', '2025-04-26 04:40:29', 'Current'),
(2, 1, 1, '2nd Year', 'A', '2025-04-20 09:07:35', '2025-04-27 06:26:00', 'Current'),
(3, 1, 1, '3rd Year', 'A', '2025-04-20 09:07:42', '2025-04-27 06:26:04', 'Current'),
(4, 1, 1, '4th Year', 'A', '2025-04-20 09:07:49', '2025-04-27 06:26:09', 'Current'),
(5, 2, 1, '1st Year', 'A', '2025-04-20 09:08:03', '2025-04-27 06:26:13', 'Past'),
(6, 2, 1, '2nd Year', 'A', '2025-04-20 09:08:10', '2025-04-27 06:26:17', 'Past'),
(7, 2, 1, '3rd Year', 'A', '2025-04-20 09:08:18', '2025-04-27 06:26:22', 'Past'),
(8, 2, 1, '4th Year', 'A', '2025-04-20 09:08:25', '2025-04-27 06:26:26', 'Past');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'terms_and_conditions', '<h6>1. Acceptance of Terms</h6>\r\n<p>By registering for the BPC Document Request System, you agree to comply with and be bound by the following terms and conditions. If you do not agree, please do not use this system.</p>\r\n<h6>2. User Responsibilities</h6>\r\n<p>You are responsible for maintaining the confidentiality of your account and password and for restricting access to your computer to prevent unauthorized access to your account.</p>', '2025-04-17 14:56:21'),
(22, 'current_semester', '2nd Sem 2024-2025', '2025-04-26 02:12:15');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `studentid` varchar(20) DEFAULT NULL,
  `firstname` varchar(50) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `number` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile` varchar(255) DEFAULT NULL,
  `gender` enum('Male','Female','Prefer not to say') NOT NULL,
  `birthdate` date NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `year_id` int(11) DEFAULT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year') DEFAULT NULL,
  `role` varchar(20) NOT NULL,
  `terms` tinyint(1) NOT NULL DEFAULT 0,
  `is_ban` tinyint(1) NOT NULL DEFAULT 0,
  `verify_token` varchar(200) DEFAULT NULL,
  `verify_status` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reset_token` varchar(32) DEFAULT NULL,
  `reset_expires_at` datetime DEFAULT NULL,
  `verify_expires_at` datetime DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `studentid`, `firstname`, `lastname`, `middlename`, `email`, `number`, `password`, `profile`, `gender`, `birthdate`, `section_id`, `course_id`, `year_id`, `year_level`, `role`, `terms`, `is_ban`, `verify_token`, `verify_status`, `created_at`, `updated_at`, `reset_token`, `reset_expires_at`, `verify_expires_at`, `suffix`) VALUES
(2, NULL, 'Staff', '1', NULL, 'staff@gmail.com', '09991234567', '$2y$10$4n/wCAdcm.3J1LDX/R5OnOU/./uivRm.jKWoD8opTLGw52m86tre6', 'assets/images/profile_2_1745129524.jpg', 'Male', '0000-00-00', NULL, NULL, NULL, NULL, 'staff', 1, 0, NULL, 1, '2025-04-09 07:54:12', '2025-04-26 04:27:37', NULL, NULL, NULL, NULL),
(11, NULL, 'Registrar', '1', NULL, 'registrar@gmail.com', '09991234566', '$2y$10$kv2Xmto7DeYczkGZeJXBk.Vuyb6vF/uvHng2GCrINx/6D801k9.y6', '', 'Male', '0000-00-00', NULL, NULL, NULL, '', 'registrar', 1, 0, NULL, 1, '2025-04-18 04:42:59', '2025-04-26 04:27:22', NULL, NULL, NULL, NULL),
(30, NULL, 'Cashier', '1', NULL, 'cashier@gmail.com', '09991234556', '$2y$10$oIcR9Qi1wI4ZIsD6e8I31OTEvSENEFKvVkm7LBurxtmk03qiAu0Y6', '', 'Male', '0000-00-00', NULL, NULL, NULL, NULL, 'cashier', 0, 0, NULL, 1, '2025-04-20 07:34:31', '2025-04-26 04:30:14', NULL, NULL, NULL, NULL),
(31, 'MA21910321', 'Noel Christopher', 'Tee', 'Donarber', 'noeldtee@gmail.com', '', '$2y$10$aBAq5oj8gouOdErpK4j00.yURKhDQaVHibpyntXZartwca43KMJQ6', NULL, 'Male', '0000-00-00', 4, 1, 1, '4th Year', 'student', 1, 0, NULL, 1, '2025-04-26 01:59:38', '2025-04-26 02:01:21', NULL, NULL, '2025-04-27 03:59:38', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `action_logs`
--
ALTER TABLE `action_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `year_id` (`year_id`);

--
-- Indexes for table `school_years`
--
ALTER TABLE `school_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `year` (`year`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_section` (`school_year_id`,`course_id`,`year_level`,`section`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `studentid` (`studentid`),
  ADD UNIQUE KEY `verify_token` (`verify_token`),
  ADD KEY `fk_section_id` (`section_id`),
  ADD KEY `fk_course_id` (`course_id`),
  ADD KEY `fk_year_id` (`year_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `action_logs`
--
ALTER TABLE `action_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_years`
--
ALTER TABLE `school_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `action_logs`
--
ALTER TABLE `action_logs`
  ADD CONSTRAINT `action_logs_ibfk_1` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD CONSTRAINT `admin_notifications_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `requests_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `requests_ibfk_4` FOREIGN KEY (`year_id`) REFERENCES `school_years` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`),
  ADD CONSTRAINT `sections_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_course_id` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  ADD CONSTRAINT `fk_section_id` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  ADD CONSTRAINT `fk_year_id` FOREIGN KEY (`year_id`) REFERENCES `school_years` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
