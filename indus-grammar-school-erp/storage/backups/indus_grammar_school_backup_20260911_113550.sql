-- Indus Grammar School ERP SQL Backup
-- Generated: 2026-09-11 11:35:50
-- Database: indus_grammar_school

SET FOREIGN_KEY_CHECKS=0;



CREATE TABLE `academic_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_name` (`session_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `academic_sessions` (`id`, `session_name`, `is_active`, `created_at`) VALUES ('1', '2025-2026', '0', '2026-07-12 13:38:23');
INSERT INTO `academic_sessions` (`id`, `session_name`, `is_active`, `created_at`) VALUES ('2', '2026-2027', '1', '2026-07-12 13:38:23');
INSERT INTO `academic_sessions` (`id`, `session_name`, `is_active`, `created_at`) VALUES ('3', '2027-2028', '0', '2026-07-12 13:38:23');


CREATE TABLE `admissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `application_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` enum('Male','Female','Other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_birth` date NOT NULL,
  `class_id` int NOT NULL,
  `status` enum('Pending','Approved','Rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `guardian_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guardian_phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guardian_email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `application_date` date NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_no` (`application_no`),
  KEY `idx_admissions_class` (`class_id`),
  KEY `idx_admissions_status` (`status`),
  KEY `idx_admissions_no` (`application_no`),
  CONSTRAINT `admissions_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admissions` (`id`, `application_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `application_date`, `notes`, `created_at`, `updated_at`) VALUES ('1', 'APP-2026-0001', 'Fatima', 'Bilal', 'Female', '2021-02-14', '2', 'Rejected', 'Bilal Tariq', '03451112233', 'bilal@gmail.com', 'House 5, Sector 5-B, Surjani, Karachi', '2026-07-01', 'Parents requested morning shift', '2026-07-10 12:16:49', '2026-07-10 12:29:03');
INSERT INTO `admissions` (`id`, `application_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `application_date`, `notes`, `created_at`, `updated_at`) VALUES ('2', 'APP-2026-0002', 'Zain', 'Khan', 'Male', '2019-04-12', '4', 'Approved', 'Asif Khan', '03215551234', 'asif@gmail.com', 'Flat C-4, Gulshan-e-Iqbal, Karachi', '2026-06-25', 'Transferred from another school', '2026-07-10 12:16:49', '2026-07-10 12:16:49');
INSERT INTO `admissions` (`id`, `application_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `application_date`, `notes`, `created_at`, `updated_at`) VALUES ('3', 'APP-2026-0003', 'Bilal', 'Siddiqui', 'Male', '2015-08-30', '9', 'Rejected', 'Siddique Shah', '03009998887', 'siddique@yahoo.com', 'House 9A, Block 6, PECHS, Karachi', '2026-06-28', 'Did not clear the entrance exam criteria', '2026-07-10 12:16:49', '2026-07-10 12:16:49');


CREATE TABLE `advance_salary` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `advance_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` text NOT NULL,
  `installments` int NOT NULL DEFAULT '1',
  `installment_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `remaining_balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('Pending','Recovered') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `advance_salary_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `allowances` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `allowance_type` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `allowances_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `announcements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `audience` enum('Students','Parents','Teachers','Staff','Everyone') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Everyone',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `priority` enum('Normal','Important','Urgent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Normal',
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `published_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `published_by` (`published_by`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `class_id` int NOT NULL,
  `date` date NOT NULL,
  `status` enum('Present','Absent','Late','Leave') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Present',
  `remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marked_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_student_date` (`student_id`,`date`),
  UNIQUE KEY `student_date_unique` (`student_id`,`date`),
  KEY `marked_by` (`marked_by`),
  KEY `idx_attendance_class_date` (`class_id`,`date`),
  KEY `idx_attendance_status` (`status`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('1', '1', '4', '2026-07-05', 'Present', NULL, '2', '2026-07-10 12:16:49');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('2', '1', '4', '2026-07-06', 'Present', NULL, '2', '2026-07-10 12:16:49');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('3', '1', '4', '2026-07-07', 'Late', 'Arrived 10 min late', '2', '2026-07-10 12:16:49');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('4', '1', '4', '2026-07-08', 'Present', NULL, '2', '2026-07-10 12:16:49');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('5', '1', '4', '2026-07-09', 'Present', NULL, '2', '2026-07-10 12:16:49');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('6', '2', '3', '2026-07-05', 'Present', NULL, '2', '2026-07-10 12:16:49');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('7', '2', '3', '2026-07-06', 'Absent', 'Sick leave', '2', '2026-07-10 12:16:49');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('8', '2', '3', '2026-07-07', 'Present', NULL, '2', '2026-07-10 12:16:49');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('9', '2', '3', '2026-07-08', 'Present', NULL, '2', '2026-07-10 12:16:49');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('10', '2', '3', '2026-07-09', 'Leave', 'Family event', '2', '2026-07-10 12:16:49');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('11', '3', '6', '2026-07-05', 'Absent', NULL, '2', '2026-07-10 12:16:49');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('12', '3', '6', '2026-07-06', 'Absent', NULL, '2', '2026-07-10 12:16:49');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('13', '3', '6', '2026-07-07', 'Present', NULL, '2', '2026-07-10 12:16:49');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('14', '3', '6', '2026-07-08', 'Present', NULL, '2', '2026-07-10 12:16:49');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('15', '3', '6', '2026-07-09', 'Present', NULL, '2', '2026-07-10 12:16:49');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('16', '1', '4', '2026-07-11', 'Absent', NULL, '4', '2026-07-11 18:41:08');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('17', '10', '13', '2026-07-17', 'Present', NULL, '4', '2026-07-17 13:44:58');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('18', '7', '3', '2026-07-17', 'Present', 'h', '4', '2026-07-17 13:45:33');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('19', '2', '3', '2026-07-17', 'Late', NULL, '4', '2026-07-17 13:45:33');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('20', '4', '3', '2026-07-17', 'Absent', NULL, '4', '2026-07-17 13:45:33');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('24', '12', '16', '2026-09-11', 'Leave', NULL, '4', '2026-09-11 10:09:21');
INSERT INTO `attendance` (`id`, `student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`, `created_at`) VALUES ('25', '13', '16', '2026-09-11', 'Absent', NULL, '4', '2026-09-11 10:09:21');


CREATE TABLE `attendance_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `school_start_time` time NOT NULL DEFAULT '08:00:00',
  `late_arrival_time` time NOT NULL DEFAULT '08:15:00',
  `working_days` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
  `weekend_days` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Sunday',
  `default_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Present',
  `allow_edit` tinyint(1) NOT NULL DEFAULT '1',
  `lock_hours` int NOT NULL DEFAULT '24',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `attendance_settings` (`id`, `school_start_time`, `late_arrival_time`, `working_days`, `weekend_days`, `default_status`, `allow_edit`, `lock_hours`, `created_at`, `updated_at`) VALUES ('1', '08:00:00', '08:15:00', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', 'Sunday', 'Present', '1', '24', '2026-07-11 22:06:01', '2026-07-11 22:06:01');


CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_user` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('1', NULL, 'Login Failed', 'Failed login attempt for account: saeed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 12:17:54');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('2', NULL, 'Login Failed', 'Failed login attempt for account: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 12:18:29');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('3', NULL, 'System Initialize', 'Created first Super Admin account for Waqas Ali (waqas7600).', 'UNKNOWN', 'UNKNOWN', '2026-07-10 12:24:38');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('4', NULL, 'Login Failed', 'Failed login attempt for account: waqas7600', 'UNKNOWN', 'UNKNOWN', '2026-07-10 12:24:38');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('5', NULL, 'Login Failed', 'Failed login attempt for account: waqas760000@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 12:26:47');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('6', '4', 'Login Success', 'User logged in successfully', 'UNKNOWN', 'UNKNOWN', '2026-07-10 12:27:06');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('7', '4', 'Login Success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 12:27:39');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('8', '4', 'Student Registration', 'Registered new student: Waqas Al (Admission No: IGS-2026-0004)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 12:28:41');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('9', '4', 'Admission Rejected', 'Rejected application APP ID: 1 for: Fatima Bilal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 12:29:03');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('10', '4', 'Daily Diary Created', 'Diary: yu for class ID 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 18:07:53');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('11', '4', 'Student Registration Save', 'Student: Mubeen Jutt | Admission No: IGS-AD-2026-0005', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 18:14:18');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('12', '4', 'Login Failed', 'Invalid password attempt for user: waqas7600', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 18:37:48');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('13', '4', 'Login Failed', 'Invalid password attempt for user: waqas7600', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 18:41:05');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('14', '4', 'Login Failed', 'Invalid password attempt for user: waqas7600', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 18:49:05');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('15', '4', 'Login Success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-11 18:35:31');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('16', '4', 'Attendance Marked', 'Marked attendance for class ID: 4 on 2026-07-11 (1 students)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-11 18:41:08');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('17', '4', 'Student Registered', 'Student: Waqas Al | Admission No: ADM-2026-0006', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-11 18:52:30');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('18', '4', 'Student Registered', 'Student: amir Al | Admission No: IGS-2026-0005 | Type: Academy', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-11 21:16:11');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('19', '4', 'Diary Created', 'Created Daily Diary: fcgjhbkjlk;l | Class: Class 3 (B) | Type: Classwork', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-11 21:43:54');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('20', '4', 'Leave Rejected', 'Leave application ID 1 was Rejected', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-11 22:07:16');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('21', '4', 'Settings Updated', 'Updated global student attendance settings configurations.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-11 22:13:30');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('22', '4', 'Logout', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 06:19:33');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('23', '4', 'Login Success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 06:19:56');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('24', '4', 'Login Success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 06:24:31');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('25', '4', 'Student Registered', 'Student: Sajid Ali | Admission No: ADM-2026-0009 | Type: Academy', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 06:28:33');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('26', '4', 'Diary Created', 'Created Daily Diary: School work | Class: Class 2 (A) | Type: Homework', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 06:30:57');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('27', '4', 'Fee Structure Saved', 'Fee structure saved for class ID 4 (Type: Academy)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 11:14:31');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('28', '4', 'Fine Settings Updated', 'Late payment fine parameters modified.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 11:23:37');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('29', '4', 'Fee Structure Deleted', 'Structure ID 1 deleted.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 12:32:04');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('30', '4', 'Staff Attendance', 'Marked staff attendance for user ID 1: Absent on 2026-07-12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 12:43:44');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('31', '4', 'Salary Paid', 'Salary paid to Ali Raza (ID: 9)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 12:44:26');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('32', '4', 'Salary Paid', 'Salary paid to Asma Khan (ID: 6)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 12:44:34');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('33', '4', 'Salary Paid', 'Salary paid to Imran Hussain (ID: 7)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 12:44:50');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('34', '4', 'Fine Settings Updated', 'Late fine properties modified.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 13:15:52');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('35', '4', 'Ledger Fine Added', 'Rs. 100 fine added to ledger entry ID 1.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 13:20:47');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('36', '4', 'Ledger Fine Waived', 'Fine of Rs. 100 waived from ledger entry ID 1.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 13:20:57');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('37', '4', 'Database Backup', 'Backup generated: indus_grammar_school_backup_20260712_132611.sql', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 13:26:12');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('38', '4', 'Database Backup', 'Backup generated: indus_grammar_school_backup_20260712_132617.sql', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 13:26:17');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('39', '4', 'Database Backup', 'Backup generated: indus_grammar_school_backup_20260712_132623.sql', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 13:26:24');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('40', '4', 'Database Backup', 'Backup generated: indus_grammar_school_backup_20260712_132821.sql', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 13:28:21');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('41', '4', 'Category Created', 'Created expense category: Ghulam Nabi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 17:48:36');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('42', '4', 'Category Updated', 'Updated expense category ID #24 to Ghulam Nabi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 17:48:52');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('43', '4', 'Cash Register Closed', 'Closed daily cash counter. Expected: 11,200.00 | Counted: 6.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 17:51:26');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('44', '4', 'Salary Paid', 'Salary paid to Fatima Noor (ID: 8)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 18:19:47');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('45', '4', 'Salary Paid', 'Salary paid to Sana Tariq (ID: 10)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 18:23:01');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('46', '4', 'Payroll Generated', 'Payroll generated for 7/2026. Success: 0, Errors: 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 18:30:26');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('47', '4', 'Payroll Generated', 'Payroll generated for 8/2026. Success: 0, Errors: 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 18:30:38');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('48', '4', 'Login Success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 14:54:49');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('49', '4', 'Salary Setup Saved', 'Updated salary structure details for Staff ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 15:07:26');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('50', '4', 'SMS Dispatched', 'Recipients Count: 2 | Msg: Dear Parent, your child was marked absent today from Indus Grammar School. Please contact the class teacher for details.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 16:21:27');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('51', '4', 'SMS Dispatched', 'Recipients Count: 2 | Msg: Dear Parent, your child was marked absent today from Indus Grammar School. Please contact the class teacher for details.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 16:21:31');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('52', '4', 'SMS Dispatched', 'Recipients Count: 2 | Msg: Dear Parents/Students, the date sheet for the upcoming Final Term examinations has been published. Schedules are accessible on the portal.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 16:21:44');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('53', '1', 'SMS Test Triggered', 'To: 03066544806 | Result: Success', NULL, NULL, '2026-07-16 16:24:08');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('54', '4', 'Student Registered', 'Student: Abudul Razzaq | Admission No: IGS-2026-0011 | Type: School', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 18:44:00');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('55', '4', 'Diary Created', 'Created Daily Diary: Math work | Class: Class 11 (A) | Type: Classwork', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 18:52:21');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('56', '4', 'Attendance Saved', 'Marked Attendance for Class Class 9 (A) on 2026-07-17 (1 students)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 13:44:58');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('57', '4', 'Attendance Saved', 'Marked Attendance for Class Prep (A) on 2026-07-17 (3 students)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 13:45:33');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('58', '4', 'Attendance Saved', 'Marked Attendance for Class Prep (A) on 2026-07-17 (3 students)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 14:08:02');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('59', '4', 'Login Success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 07:41:35');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('60', '4', 'Marks Entered', 'Saved marks for Exam ID 1, Subject ID 1. Records: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 07:47:33');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('61', '4', 'Promotion Processed', 'Promoted 1 students from Class ID 9 to 10 for session 2026-2027.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 08:01:20');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('62', '4', 'School Settings Saved', 'Updated school configuration parameters.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 08:03:50');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('63', '4', 'School Settings Saved', 'Updated school configuration parameters.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 08:04:34');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('64', '4', 'School Settings Saved', 'Updated school configuration parameters.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 08:04:47');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('65', '4', 'Password Reset', 'Password reset for user ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 08:06:06');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('66', '4', 'Logout', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 08:07:09');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('67', '3', 'Login Failed', 'Invalid password attempt for user: cashier', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 08:07:17');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('68', '4', 'Login Success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 08:07:24');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('69', '4', 'Role Deleted', 'Deleted role ID: 7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 08:09:07');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('70', '4', 'Role Deleted', 'Deleted role ID: 9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 08:09:16');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('71', '4', 'Database Backup Created', 'Backup exported successfully: indus_grammar_school_backup_20260718_080937.sql', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 08:09:37');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('72', '4', 'Auto-Login', 'User automatically logged in via remember-me cookie', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 14:36:25');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('73', '4', 'Circular Saved', 'Title: S', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 14:40:10');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('74', '4', 'Student Registered', 'Student: Ali Ahmed | Admission No: IGS-2026-0013 | Type: School', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 15:20:07');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('75', '4', 'Auto-Login', 'User automatically logged in via remember-me cookie', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-26 10:15:05');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('76', '4', 'Student Registered', 'Student: Amir khan | Admission No: IGS-2026-0012 | Type: School', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-26 10:26:24');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('77', NULL, 'Login Failed', 'Failed login attempt for account: root', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 12:55:37');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('78', '4', 'Login Success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 12:55:41');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('79', NULL, 'Login Failed', 'Failed login attempt for account: root', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-10 10:38:30');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('80', '4', 'Login Success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-10 10:38:34');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('81', '4', 'Student Registered', 'Student: Jawad Ali | Admission No: IGS-2026-0016 | Type: School', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 09:59:59');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('82', '4', 'Diary Created', 'Created Daily Diary: table of 2 | Class: Class 2 (A) | Type: Homework', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 10:03:57');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('83', '4', 'Diary Created', 'Created Daily Diary: table of 2 | Class: Class 2 (A) | Type: Homework', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 10:04:00');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('84', '4', 'Diary Created', 'Created Daily Diary: table of 2 | Class: Class 2 (A) | Type: Homework', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 10:04:03');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('85', '4', 'Diary Created', 'Created Daily Diary: table of 2 | Class: Class 2 (A) | Type: Homework', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 10:04:04');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('86', '4', 'Diary Created', 'Created Daily Diary: table of 2 | Class: Class 2 (A) | Type: Homework', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 10:04:06');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('87', '4', 'Attendance Saved', 'Marked Attendance for Class Class 7 (A) on 2026-09-11 (2 students)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 10:09:21');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('88', '4', 'Login Success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:22:21');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('89', '4', 'Attendance Saved', 'Marked Attendance for Class Class 7 (A) on 2026-09-11 (2 students)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:26:26');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('90', '4', 'Ledger Fine Added', 'Rs. 1.03 fine added to ledger entry ID 8.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:34:17');


CREATE TABLE `bank_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `transaction_type` enum('Deposit','Withdrawal') NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `bonuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `bonus_type` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `date_earned` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `bonuses_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `cash_book` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `opening_cash` decimal(10,2) NOT NULL DEFAULT '0.00',
  `income` decimal(10,2) NOT NULL DEFAULT '0.00',
  `expenses` decimal(10,2) NOT NULL DEFAULT '0.00',
  `closing_cash` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `cash_book` (`id`, `date`, `opening_cash`, `income`, `expenses`, `closing_cash`, `balance`, `created_at`) VALUES ('1', '2026-07-12', '1500.00', '9700.00', '0.00', '6.00', '6.00', '2026-07-12 17:51:26');


CREATE TABLE `cash_closing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `opening_cash` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fee_collection` decimal(10,2) NOT NULL DEFAULT '0.00',
  `other_income` decimal(10,2) NOT NULL DEFAULT '0.00',
  `expenses` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cash_in_hand` decimal(10,2) NOT NULL DEFAULT '0.00',
  `closing_balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `difference` decimal(10,2) NOT NULL DEFAULT '0.00',
  `verified_by` int DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `cash_closing_ibfk_1` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `cash_closing` (`id`, `date`, `opening_cash`, `fee_collection`, `other_income`, `expenses`, `cash_in_hand`, `closing_balance`, `difference`, `verified_by`, `remarks`, `created_at`) VALUES ('1', '2026-07-12', '1500.00', '9700.00', '0.00', '0.00', '11200.00', '6.00', '-11194.00', '4', '', '2026-07-12 17:51:26');


CREATE TABLE `cash_opening` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `opening_cash` decimal(10,2) NOT NULL DEFAULT '0.00',
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `cash_opening` (`id`, `date`, `opening_cash`, `remarks`, `created_at`) VALUES ('1', '2026-07-12', '1500.00', 'Test Setup', '2026-07-12 17:49:01');


CREATE TABLE `cash_register` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `opening_balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_collections` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_expenses` decimal(10,2) NOT NULL DEFAULT '0.00',
  `closing_balance` decimal(10,2) DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `status` enum('Open','Closed') COLLATE utf8mb4_unicode_ci DEFAULT 'Open',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`),
  KEY `closed_by` (`closed_by`),
  CONSTRAINT `cash_register_ibfk_1` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `cash_register` (`id`, `date`, `opening_balance`, `total_collections`, `total_expenses`, `closing_balance`, `closed_by`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('1', '2026-07-09', '5000.00', '3500.00', '18500.00', '-10000.00', '3', 'Closed', 'Heavy expense day', '2026-07-10 12:16:49', '2026-07-10 12:16:49');
INSERT INTO `cash_register` (`id`, `date`, `opening_balance`, `total_collections`, `total_expenses`, `closing_balance`, `closed_by`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('2', '2026-07-10', '5000.00', '0.00', '0.00', NULL, NULL, 'Open', NULL, '2026-07-10 12:16:49', '2026-07-10 12:16:49');


CREATE TABLE `circulars` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `audience` enum('School','Academy','Everyone') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Everyone',
  `class_id` int DEFAULT NULL,
  `attachment_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `published_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `published_by` (`published_by`),
  CONSTRAINT `circulars_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `circulars_ibfk_2` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `circulars` (`id`, `title`, `description`, `audience`, `class_id`, `attachment_path`, `issue_date`, `expiry_date`, `published_by`, `created_at`, `updated_at`) VALUES ('1', 'S', 'hbje', 'Everyone', NULL, '', '2026-07-19', '2026-08-03', '4', '2026-07-19 14:40:10', '2026-07-19 14:40:10');


CREATE TABLE `classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `section` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_class_section` (`class_name`,`section`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('1', 'Playgroup', 'A', '2026-07-10 12:16:49');
INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('2', 'Nursery', 'A', '2026-07-10 12:16:49');
INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('3', 'Prep', 'A', '2026-07-10 12:16:49');
INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('4', 'Class 1', 'A', '2026-07-10 12:16:49');
INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('5', 'Class 1', 'B', '2026-07-10 12:16:49');
INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('6', 'Class 2', 'A', '2026-07-10 12:16:49');
INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('7', 'Class 2', 'B', '2026-07-10 12:16:49');
INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('8', 'Class 3', 'A', '2026-07-10 12:16:49');
INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('9', 'Class 4', 'A', '2026-07-10 12:16:49');
INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('10', 'Class 5', 'A', '2026-07-10 12:16:49');
INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('11', 'Class 3', 'B', '2026-07-11 21:43:54');
INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('12', 'Class 6', 'A', '2026-07-12 06:28:33');
INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('13', 'Class 9', 'A', '2026-07-16 18:44:00');
INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('14', 'Class 11', 'A', '2026-07-16 18:52:21');
INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('15', 'Class 8', 'A', '2026-07-19 15:20:07');
INSERT INTO `classes` (`id`, `class_name`, `section`, `created_at`) VALUES ('16', 'Class 7', 'A', '2026-07-26 10:26:24');


CREATE TABLE `communication_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sms_gateway_api_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sms_sender_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sms_default_lang` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'English',
  `smtp_host` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_port` int DEFAULT NULL,
  `smtp_username` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_encryption` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'tls',
  `default_sender_email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_sender_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sms_provider` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'test_mode',
  `sms_api_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `communication_settings` (`id`, `sms_gateway_api_key`, `sms_sender_id`, `sms_default_lang`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_encryption`, `default_sender_email`, `default_sender_name`, `updated_at`, `sms_provider`, `sms_api_secret`) VALUES ('1', 'IGS-SIMULATED-GATEWAY-KEY-998822', 'IndusSchool', 'English', 'smtp.mailtrap.io', '2525', 'api-user', 'api-secret', 'tls', 'no-reply@indus.edu.pk', 'Indus Grammar School', '2026-07-16 16:07:09', 'test_mode', NULL);


CREATE TABLE `communication_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('Fee Reminder','Attendance Alert','Exam Notification','Holiday Notice','Meeting Invitation','Emergency Notice','General Information') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `communication_templates` (`id`, `name`, `type`, `subject`, `message`, `created_at`, `updated_at`) VALUES ('1', 'Fee Outstanding Reminder', 'Fee Reminder', 'Pending Tuition Fees Reminder', 'Dear Parent, this is a reminder that your child\'s school fees remain pending. Please clear outstanding dues by the due date to avoid late penalty charges. Thank you.', '2026-07-16 16:07:09', '2026-07-16 16:07:09');
INSERT INTO `communication_templates` (`id`, `name`, `type`, `subject`, `message`, `created_at`, `updated_at`) VALUES ('2', 'Daily Absence Alert', 'Attendance Alert', 'Student Absence Notification', 'Dear Parent, your child was marked absent today from Indus Grammar School. Please contact the class teacher for details.', '2026-07-16 16:07:09', '2026-07-16 16:07:09');
INSERT INTO `communication_templates` (`id`, `name`, `type`, `subject`, `message`, `created_at`, `updated_at`) VALUES ('3', 'Final Term Date Sheet Alert', 'Exam Notification', 'Examination Term Schedule Announcement', 'Dear Parents/Students, the date sheet for the upcoming Final Term examinations has been published. Schedules are accessible on the portal.', '2026-07-16 16:07:09', '2026-07-16 16:07:09');
INSERT INTO `communication_templates` (`id`, `name`, `type`, `subject`, `message`, `created_at`, `updated_at`) VALUES ('4', 'Summer Vacation Notification', 'Holiday Notice', 'Summer Vacation Holidays Announcement', 'Dear Parents and Staff, Indus Grammar School will remain closed for summer vacations starting next week. Classes will resume next month. Happy holidays!', '2026-07-16 16:07:09', '2026-07-16 16:07:09');


CREATE TABLE `daily_diaries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class_id` int DEFAULT NULL,
  `subject_id` int DEFAULT NULL,
  `teacher_id` int DEFAULT NULL,
  `diary_date` date NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `academic_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'School',
  `class` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `diary_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Homework',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `daily_diaries_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `daily_diaries_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `daily_diaries_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `daily_diaries` (`id`, `class_id`, `subject_id`, `teacher_id`, `diary_date`, `title`, `description`, `attachment_path`, `is_published`, `created_at`, `updated_at`, `academic_type`, `class`, `section`, `subject`, `diary_type`, `status`, `attachment`, `created_by`) VALUES ('1', '6', '7', '4', '2026-07-10', 'yu', 'jhg', '', '1', '2026-07-10 18:07:53', '2026-07-10 18:07:53', 'School', NULL, NULL, NULL, 'Homework', 'Active', NULL, NULL);
INSERT INTO `daily_diaries` (`id`, `class_id`, `subject_id`, `teacher_id`, `diary_date`, `title`, `description`, `attachment_path`, `is_published`, `created_at`, `updated_at`, `academic_type`, `class`, `section`, `subject`, `diary_type`, `status`, `attachment`, `created_by`) VALUES ('2', '11', NULL, NULL, '2026-07-11', 'fcgjhbkjlk;l', '<p>fdgfhgjkhjll</p>', NULL, '1', '2026-07-11 21:43:54', '2026-07-11 21:43:54', 'Academy', 'Class 3', 'B', 'math', 'Classwork', 'Active', NULL, '4');
INSERT INTO `daily_diaries` (`id`, `class_id`, `subject_id`, `teacher_id`, `diary_date`, `title`, `description`, `attachment_path`, `is_published`, `created_at`, `updated_at`, `academic_type`, `class`, `section`, `subject`, `diary_type`, `status`, `attachment`, `created_by`) VALUES ('3', '6', NULL, NULL, '2026-07-12', 'School work', '<h2>Do on time&nbsp;</h2>', NULL, '1', '2026-07-12 06:30:57', '2026-07-12 06:30:57', 'Academy', 'Class 2', 'A', 'English', 'Homework', 'Active', NULL, '4');
INSERT INTO `daily_diaries` (`id`, `class_id`, `subject_id`, `teacher_id`, `diary_date`, `title`, `description`, `attachment_path`, `is_published`, `created_at`, `updated_at`, `academic_type`, `class`, `section`, `subject`, `diary_type`, `status`, `attachment`, `created_by`) VALUES ('4', '14', NULL, NULL, '2026-07-16', 'Math work', '<p>hgjbhkjlk;fdgbgb</p>', NULL, '1', '2026-07-16 18:52:21', '2026-07-16 18:52:21', 'School', 'Class 11', 'A', 'math', 'Classwork', 'Active', NULL, '4');
INSERT INTO `daily_diaries` (`id`, `class_id`, `subject_id`, `teacher_id`, `diary_date`, `title`, `description`, `attachment_path`, `is_published`, `created_at`, `updated_at`, `academic_type`, `class`, `section`, `subject`, `diary_type`, `status`, `attachment`, `created_by`) VALUES ('5', '6', NULL, NULL, '2026-09-11', 'table of 2', '<p>Write table of 2</p>', NULL, '1', '2026-09-11 10:03:57', '2026-09-11 10:03:57', 'School', 'Class 2', 'A', 'Math', 'Homework', 'Active', NULL, '4');
INSERT INTO `daily_diaries` (`id`, `class_id`, `subject_id`, `teacher_id`, `diary_date`, `title`, `description`, `attachment_path`, `is_published`, `created_at`, `updated_at`, `academic_type`, `class`, `section`, `subject`, `diary_type`, `status`, `attachment`, `created_by`) VALUES ('6', '6', NULL, NULL, '2026-09-11', 'table of 2', '<p>Write table of 2</p>', NULL, '1', '2026-09-11 10:04:00', '2026-09-11 10:04:00', 'School', 'Class 2', 'A', 'Math', 'Homework', 'Active', NULL, '4');
INSERT INTO `daily_diaries` (`id`, `class_id`, `subject_id`, `teacher_id`, `diary_date`, `title`, `description`, `attachment_path`, `is_published`, `created_at`, `updated_at`, `academic_type`, `class`, `section`, `subject`, `diary_type`, `status`, `attachment`, `created_by`) VALUES ('7', '6', NULL, NULL, '2026-09-11', 'table of 2', '<p>Write table of 2</p>', NULL, '1', '2026-09-11 10:04:01', '2026-09-11 10:04:01', 'School', 'Class 2', 'A', 'Math', 'Homework', 'Active', NULL, '4');
INSERT INTO `daily_diaries` (`id`, `class_id`, `subject_id`, `teacher_id`, `diary_date`, `title`, `description`, `attachment_path`, `is_published`, `created_at`, `updated_at`, `academic_type`, `class`, `section`, `subject`, `diary_type`, `status`, `attachment`, `created_by`) VALUES ('8', '6', NULL, NULL, '2026-09-11', 'table of 2', '<p>Write table of 2</p>', NULL, '1', '2026-09-11 10:04:04', '2026-09-11 10:04:04', 'School', 'Class 2', 'A', 'Math', 'Homework', 'Active', NULL, '4');
INSERT INTO `daily_diaries` (`id`, `class_id`, `subject_id`, `teacher_id`, `diary_date`, `title`, `description`, `attachment_path`, `is_published`, `created_at`, `updated_at`, `academic_type`, `class`, `section`, `subject`, `diary_type`, `status`, `attachment`, `created_by`) VALUES ('9', '6', NULL, NULL, '2026-09-11', 'table of 2', '<p>Write table of 2</p>', NULL, '1', '2026-09-11 10:04:06', '2026-09-11 10:04:06', 'School', 'Class 2', 'A', 'Math', 'Homework', 'Active', NULL, '4');


CREATE TABLE `deductions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `deduction_type` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` text,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `deductions_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `departments` (`id`, `name`, `code`, `status`, `created_at`) VALUES ('1', 'Science Department', 'SCI', 'Active', '2026-07-12 13:38:23');
INSERT INTO `departments` (`id`, `name`, `code`, `status`, `created_at`) VALUES ('2', 'Arts & Humanities', 'ART', 'Active', '2026-07-12 13:38:23');
INSERT INTO `departments` (`id`, `name`, `code`, `status`, `created_at`) VALUES ('3', 'Information Technology', 'IT', 'Active', '2026-07-12 13:38:23');
INSERT INTO `departments` (`id`, `name`, `code`, `status`, `created_at`) VALUES ('4', 'Administration', 'ADMIN', 'Active', '2026-07-12 13:38:23');


CREATE TABLE `diary_edit_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `diary_id` int NOT NULL,
  `edited_by` int NOT NULL,
  `edited_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `old_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_description` text COLLATE utf8mb4_unicode_ci,
  `new_description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `diary_id` (`diary_id`),
  KEY `edited_by` (`edited_by`),
  CONSTRAINT `diary_edit_history_ibfk_1` FOREIGN KEY (`diary_id`) REFERENCES `daily_diaries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `diary_edit_history_ibfk_2` FOREIGN KEY (`edited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `email_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `recipient_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_count` int NOT NULL DEFAULT '1',
  `recipients_list` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` enum('Normal','High') COLLATE utf8mb4_unicode_ci DEFAULT 'Normal',
  `scheduled_time` datetime DEFAULT NULL,
  `sent_by` int DEFAULT NULL,
  `status` enum('Sent','Scheduled','Failed','Draft') COLLATE utf8mb4_unicode_ci DEFAULT 'Sent',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sent_by` (`sent_by`),
  CONSTRAINT `email_history_ibfk_1` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `exam_results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_type_id` int NOT NULL,
  `student_id` int NOT NULL,
  `class_id` int NOT NULL,
  `total_marks` decimal(10,2) NOT NULL DEFAULT '0.00',
  `obtained_marks` decimal(10,2) NOT NULL DEFAULT '0.00',
  `percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `grade` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int DEFAULT NULL,
  `status` enum('Pass','Fail') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Fail',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_exam_result_unique` (`exam_type_id`,`student_id`),
  KEY `student_id` (`student_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `exam_results_ibfk_1` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_results_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_results_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `exam_schedule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_type_id` int NOT NULL,
  `class_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `exam_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `room` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supervisor` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `exam_type_id` (`exam_type_id`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `exam_schedule_ibfk_1` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_schedule_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_schedule_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `exam_schedule` (`id`, `exam_type_id`, `class_id`, `subject_id`, `exam_date`, `start_time`, `end_time`, `room`, `supervisor`, `created_at`) VALUES ('1', '1', '3', '7', '2026-07-19', '09:00:00', '12:00:00', 'Any', 'waq', '2026-07-18 07:47:02');


CREATE TABLE `exam_schedules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `class_id` int NOT NULL,
  `exam_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `total_marks` int NOT NULL DEFAULT '100',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  KEY `subject_id` (`subject_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `exam_schedules_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_schedules_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_schedules_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `exam_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `academic_session` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `academic_type` enum('School','Academy') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'School',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_marks` int NOT NULL DEFAULT '100',
  `passing_percentage` decimal(5,2) NOT NULL DEFAULT '40.00',
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `exam_types` (`id`, `exam_name`, `academic_session`, `academic_type`, `start_date`, `end_date`, `total_marks`, `passing_percentage`, `status`, `created_at`, `updated_at`) VALUES ('1', 'Monthly', '2026-2027', 'School', '2026-07-19', '2026-07-19', '100', '40.00', 'Active', '2026-07-18 07:46:23', '2026-07-18 07:46:23');


CREATE TABLE `exams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exam_type` enum('Mid-Term','Final','Test','Quiz') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Mid-Term',
  `academic_year` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Upcoming','Active','Completed') COLLATE utf8mb4_unicode_ci DEFAULT 'Upcoming',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `exams` (`id`, `exam_name`, `exam_type`, `academic_year`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`) VALUES ('2', 'Class Test - July 2026', 'Test', '2026-2027', '2026-07-20', '2026-07-20', 'Upcoming', '2026-07-10 12:16:49', '2026-07-10 12:16:49');


CREATE TABLE `expense_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('1', 'Electricity Bill', 'Monthly utility electricity charges.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('2', 'Gas Bill', 'Monthly utility gas charges.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('3', 'Water Bill', 'Water supply charges.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('4', 'Internet Bill', 'Broadband internet fee.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('5', 'Telephone Bill', 'Office landline and mobile bills.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('6', 'Office Rent', 'Monthly premises lease rent.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('7', 'Staff Tea & Refreshments', 'Tea, snacks, and catering.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('8', 'Cleaning Supplies', 'Detergents, disinfectants, soap.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('9', 'Stationery', 'Pens, markers, paper supplies.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('10', 'Printing & Photocopy', 'Exam papers printing, leaflets.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('11', 'Maintenance', 'Electrical and plumbing repairs.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('12', 'Building Repair', 'Masonry, paint work.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('13', 'Furniture', 'Desks, chairs, whiteboards.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('14', 'Computer Equipment', 'PCs, printers, accessories.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('15', 'Lab Equipment', 'Chemicals, physics apparatus.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('16', 'Sports Equipment', 'Footballs, bats, nets.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('17', 'Transport Fuel', 'School bus diesel/petrol.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('18', 'Vehicle Maintenance', 'School bus tuning, parts replacement.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('19', 'Generator Fuel', 'Backup power generator diesel.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('20', 'Security Expense', 'Security guard salaries, gear.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('21', 'Marketing', 'Banners, brochures, Facebook ads.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('22', 'Event Expense', 'Annual day, sports gala, exams.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('23', 'Miscellaneous', 'Other minor operational costs.', 'Active', '2026-07-12 16:23:43');
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES ('24', 'Ghulam Nabi', 'jghcf', 'Active', '2026-07-12 17:48:36');


CREATE TABLE `expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `expense_date` date NOT NULL,
  `category_id` int DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `vendor_supplier` varchar(255) DEFAULT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_method` enum('Cash','Bank','Cheque') NOT NULL DEFAULT 'Cash',
  `paid_by` int DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `paid_by` (`paid_by`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `fee_ledger` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `month` varchar(30) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `admission_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tuition_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `computer_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `exam_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `transport_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `annual_charges` decimal(10,2) NOT NULL DEFAULT '0.00',
  `security_deposit` decimal(10,2) NOT NULL DEFAULT '0.00',
  `other_charges` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fine_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_payable` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `due_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_month_year` (`student_id`,`month`,`academic_year`),
  CONSTRAINT `fee_ledger_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('1', '1', 'July 2026', '2026-2027', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', '0.00', '0.00', '10600.00', '10700.00', 'Paid', '2026-07-15', '2026-07-12 11:51:45', '2026-07-12 17:49:01');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('2', '2', 'June 2026', '2026-2027', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', '0.00', '0.00', '10600.00', '10600.00', 'Paid', '2026-07-15', '2026-07-12 11:52:05', '2026-07-12 13:01:20');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('3', '4', 'June 2026', '2026-2027', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', '0.00', '0.00', '10600.00', '10600.00', 'Paid', '2026-07-15', '2026-07-12 11:52:05', '2026-07-12 12:38:16');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('4', '7', 'June 2026', '2026-2027', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', '0.00', '0.00', '10600.00', '10600.00', 'Paid', '2026-07-15', '2026-07-12 11:52:05', '2026-07-12 12:33:13');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('5', '2', 'July 2026', '2026-2027', '0.00', '3500.00', '500.00', '400.00', '1000.00', '0.00', '0.00', '200.00', '0.00', '0.00', '5600.00', '5600.00', 'Paid', '2026-07-15', '2026-07-12 12:03:58', '2026-07-12 12:34:26');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('6', '4', 'July 2026', '2026-2027', '0.00', '3500.00', '500.00', '400.00', '1000.00', '0.00', '0.00', '200.00', '0.00', '0.00', '5600.00', '5600.00', 'Paid', '2026-07-15', '2026-07-12 12:03:58', '2026-07-16 15:15:26');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('7', '7', 'July 2026', '2026-2027', '0.00', '3500.00', '500.00', '400.00', '1000.00', '0.00', '0.00', '200.00', '0.00', '0.00', '5600.00', '5600.00', 'Paid', '2026-07-15', '2026-07-12 12:03:58', '2026-07-12 12:32:40');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('8', '12', 'September 2026', '2026-2027', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '1.03', '0.00', '1.03', '0.00', 'Pending', '2026-09-15', '2026-09-11 11:32:29', '2026-09-11 11:34:17');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('9', '13', 'September 2026', '2026-2027', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '1.04', 'Paid', '2026-09-15', '2026-09-11 11:32:29', '2026-09-11 11:32:52');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('10', '2', 'September 2026', '2026-2027', '0.00', '3500.00', '500.00', '400.00', '1000.00', '0.00', '0.00', '200.00', '0.00', '0.00', '5600.00', '5600.00', 'Paid', '2026-09-15', '2026-09-11 11:33:21', '2026-09-11 11:33:39');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('11', '4', 'September 2026', '2026-2027', '0.00', '3500.00', '500.00', '400.00', '1000.00', '0.00', '0.00', '200.00', '0.00', '0.00', '5600.00', '0.00', 'Pending', '2026-09-15', '2026-09-11 11:33:21', '2026-09-11 11:33:21');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('12', '7', 'September 2026', '2026-2027', '0.00', '3500.00', '500.00', '400.00', '1000.00', '0.00', '0.00', '200.00', '0.00', '0.00', '5600.00', '0.00', 'Pending', '2026-09-15', '2026-09-11 11:33:21', '2026-09-11 11:33:21');


CREATE TABLE `fee_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ledger_id` int NOT NULL,
  `student_id` int NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(30) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `cash_register_id` int DEFAULT NULL,
  `collected_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ledger_id` (`ledger_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `fee_payments_ibfk_1` FOREIGN KEY (`ledger_id`) REFERENCES `fee_ledger` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fee_payments_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('1', '7', '7', '5600.00', '2026-07-12', 'Cash', '', '', '2', '4', '2026-07-12 12:32:40');
INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('2', '4', '7', '10600.00', '2026-07-12', 'Cash', '', '', '2', '4', '2026-07-12 12:33:13');
INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('3', '5', '2', '5600.00', '2026-07-12', 'Cash', '', '', '2', '4', '2026-07-12 12:34:26');
INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('4', '1', '1', '1000.00', '2026-07-12', 'Cash', 'TEST-1234', 'Unit test automatic cashier transaction', '2', NULL, '2026-07-12 12:36:35');
INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('5', '3', '4', '10600.00', '2026-07-12', 'Cash', '', '', '2', '4', '2026-07-12 12:38:16');
INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('6', '2', '2', '10600.00', '2026-07-12', 'Cash', '', '', '2', '4', '2026-07-12 13:01:20');
INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('7', '1', '1', '9600.00', '2026-07-12', 'Cash', '', '', '2', '4', '2026-07-12 16:37:00');
INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('8', '1', '1', '100.00', '2026-07-12', 'Cash', 'TEST-REF-1783860541', 'Verification automation test', '2', NULL, '2026-07-12 17:49:01');
INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('9', '6', '4', '5600.00', '2026-07-16', 'Cash', '', '', '2', '4', '2026-07-16 15:15:26');
INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('10', '9', '13', '1.04', '2026-09-11', 'Cash', '', '', '2', '4', '2026-09-11 11:32:52');
INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('11', '10', '2', '5600.00', '2026-09-11', 'Cash', '', '', '2', '4', '2026-09-11 11:33:39');


CREATE TABLE `fee_receipts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(50) NOT NULL,
  `payment_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_no` (`receipt_no`),
  KEY `payment_id` (`payment_id`),
  CONSTRAINT `fee_receipts_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `fee_payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('1', 'REC-20260712-00001', '1', '2026-07-12 12:32:40');
INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('2', 'REC-20260712-00002', '2', '2026-07-12 12:33:13');
INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('3', 'REC-20260712-00003', '3', '2026-07-12 12:34:26');
INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('4', 'REC-20260712-00004', '4', '2026-07-12 12:36:35');
INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('5', 'REC-20260712-00005', '5', '2026-07-12 12:38:16');
INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('6', 'REC-20260712-00006', '6', '2026-07-12 13:01:20');
INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('7', 'REC-20260712-00007', '7', '2026-07-12 16:37:00');
INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('8', 'REC-20260712-00008', '8', '2026-07-12 17:49:01');
INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('9', 'REC-20260716-00009', '9', '2026-07-16 15:15:26');
INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('10', 'REC-20260911-00010', '10', '2026-09-11 11:32:52');
INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('11', 'REC-20260911-00011', '11', '2026-09-11 11:33:39');


CREATE TABLE `fee_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `late_fine_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `grace_days` int NOT NULL DEFAULT '0',
  `fine_type` varchar(20) NOT NULL DEFAULT 'Fixed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `fee_settings` (`id`, `late_fine_amount`, `grace_days`, `fine_type`, `created_at`, `updated_at`) VALUES ('2', '100.00', '5', 'Daily', '2026-07-12 13:15:52', '2026-07-12 13:15:52');


CREATE TABLE `fee_structure` (
  `id` int NOT NULL AUTO_INCREMENT,
  `academic_type` varchar(50) NOT NULL DEFAULT 'School',
  `class_id` int NOT NULL,
  `admission_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tuition_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `computer_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `exam_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `transport_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `annual_charges` decimal(10,2) NOT NULL DEFAULT '0.00',
  `security_deposit` decimal(10,2) NOT NULL DEFAULT '0.00',
  `other_charges` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `academic_year` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_academic_year` (`academic_type`,`class_id`,`academic_year`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `fee_structure_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('2', 'Academy', '4', '1500.00', '2000.00', '0.00', '300.00', '0.00', '1000.00', '0.00', '100.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('3', 'School', '5', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('4', 'Academy', '5', '1500.00', '2000.00', '0.00', '300.00', '0.00', '1000.00', '0.00', '100.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('5', 'School', '6', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('6', 'Academy', '6', '1500.00', '2000.00', '0.00', '300.00', '0.00', '1000.00', '0.00', '100.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('7', 'School', '7', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('8', 'Academy', '7', '1500.00', '2000.00', '0.00', '300.00', '0.00', '1000.00', '0.00', '100.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('9', 'School', '8', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('10', 'Academy', '8', '1500.00', '2000.00', '0.00', '300.00', '0.00', '1000.00', '0.00', '100.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('11', 'School', '11', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('12', 'Academy', '11', '1500.00', '2000.00', '0.00', '300.00', '0.00', '1000.00', '0.00', '100.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('13', 'School', '9', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('14', 'Academy', '9', '1500.00', '2000.00', '0.00', '300.00', '0.00', '1000.00', '0.00', '100.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('15', 'School', '10', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('16', 'Academy', '10', '1500.00', '2000.00', '0.00', '300.00', '0.00', '1000.00', '0.00', '100.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('17', 'School', '12', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('18', 'Academy', '12', '1500.00', '2000.00', '0.00', '300.00', '0.00', '1000.00', '0.00', '100.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('19', 'School', '2', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('20', 'Academy', '2', '1500.00', '2000.00', '0.00', '300.00', '0.00', '1000.00', '0.00', '100.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('21', 'School', '1', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('22', 'Academy', '1', '1500.00', '2000.00', '0.00', '300.00', '0.00', '1000.00', '0.00', '100.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('23', 'School', '3', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('24', 'Academy', '3', '1500.00', '2000.00', '0.00', '300.00', '0.00', '1000.00', '0.00', '100.00', 'Active', '2026-2027', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('25', 'School', '4', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Active', '2026-2027', '2026-07-12 13:15:56', '2026-07-12 13:15:56');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('26', 'School', '13', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Active', '2026-2027', '2026-07-17 12:27:04', '2026-07-17 12:27:04');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('27', 'School', '15', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Active', '2026-2027', '2026-09-05 12:57:06', '2026-09-05 12:57:06');
INSERT INTO `fee_structure` (`id`, `academic_type`, `class_id`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `status`, `academic_year`, `created_at`, `updated_at`) VALUES ('28', 'School', '16', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Active', '2026-2027', '2026-09-05 12:57:06', '2026-09-05 12:57:06');


CREATE TABLE `grade_scales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grade` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_percentage` decimal(5,2) NOT NULL,
  `max_percentage` decimal(5,2) NOT NULL,
  `remarks` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `grade_scales` (`id`, `grade`, `min_percentage`, `max_percentage`, `remarks`, `created_at`) VALUES ('1', 'A+', '90.00', '100.00', 'Outstanding', '2026-07-10 12:16:49');
INSERT INTO `grade_scales` (`id`, `grade`, `min_percentage`, `max_percentage`, `remarks`, `created_at`) VALUES ('2', 'A', '80.00', '89.99', 'Excellent', '2026-07-10 12:16:49');
INSERT INTO `grade_scales` (`id`, `grade`, `min_percentage`, `max_percentage`, `remarks`, `created_at`) VALUES ('3', 'B+', '70.00', '79.99', 'Very Good', '2026-07-10 12:16:49');
INSERT INTO `grade_scales` (`id`, `grade`, `min_percentage`, `max_percentage`, `remarks`, `created_at`) VALUES ('4', 'B', '60.00', '69.99', 'Good', '2026-07-10 12:16:49');
INSERT INTO `grade_scales` (`id`, `grade`, `min_percentage`, `max_percentage`, `remarks`, `created_at`) VALUES ('5', 'C', '50.00', '59.99', 'Satisfactory', '2026-07-10 12:16:49');
INSERT INTO `grade_scales` (`id`, `grade`, `min_percentage`, `max_percentage`, `remarks`, `created_at`) VALUES ('6', 'D', '40.00', '49.99', 'Below Average', '2026-07-10 12:16:49');
INSERT INTO `grade_scales` (`id`, `grade`, `min_percentage`, `max_percentage`, `remarks`, `created_at`) VALUES ('7', 'F', '0.00', '39.99', 'Fail', '2026-07-10 12:16:49');


CREATE TABLE `grade_setup` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grade` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_percentage` decimal(5,2) NOT NULL,
  `max_percentage` decimal(5,2) NOT NULL,
  `grade_point` decimal(3,2) NOT NULL DEFAULT '0.00',
  `remarks` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grade` (`grade`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `grade_setup` (`id`, `grade`, `min_percentage`, `max_percentage`, `grade_point`, `remarks`, `created_at`) VALUES ('1', 'A+', '90.00', '100.00', '4.00', 'Outstanding', '2026-07-16 15:26:36');
INSERT INTO `grade_setup` (`id`, `grade`, `min_percentage`, `max_percentage`, `grade_point`, `remarks`, `created_at`) VALUES ('2', 'A', '80.00', '89.99', '3.75', 'Excellent', '2026-07-16 15:26:36');
INSERT INTO `grade_setup` (`id`, `grade`, `min_percentage`, `max_percentage`, `grade_point`, `remarks`, `created_at`) VALUES ('3', 'B', '70.00', '79.99', '3.00', 'Very Good', '2026-07-16 15:26:36');
INSERT INTO `grade_setup` (`id`, `grade`, `min_percentage`, `max_percentage`, `grade_point`, `remarks`, `created_at`) VALUES ('4', 'C', '60.00', '69.99', '2.50', 'Good', '2026-07-16 15:26:36');
INSERT INTO `grade_setup` (`id`, `grade`, `min_percentage`, `max_percentage`, `grade_point`, `remarks`, `created_at`) VALUES ('5', 'D', '50.00', '59.99', '2.00', 'Satisfactory', '2026-07-16 15:26:36');
INSERT INTO `grade_setup` (`id`, `grade`, `min_percentage`, `max_percentage`, `grade_point`, `remarks`, `created_at`) VALUES ('6', 'F', '0.00', '49.99', '0.00', 'Fail', '2026-07-16 15:26:36');


CREATE TABLE `income` (
  `id` int NOT NULL AUTO_INCREMENT,
  `income_date` date NOT NULL,
  `source` varchar(100) NOT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `student_id` int DEFAULT NULL,
  `description` text,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_method` enum('Cash','Bank','Online','Cheque') NOT NULL DEFAULT 'Cash',
  `received_by` int DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `income_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  CONSTRAINT `income_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `income` (`id`, `income_date`, `source`, `reference_no`, `student_id`, `description`, `amount`, `payment_method`, `received_by`, `remarks`, `created_at`) VALUES ('1', '2026-07-12', 'Student Fee Collection', 'REC-20260712-00007', '1', 'Fee collection for month: July 2026 (2026-2027)', '9600.00', 'Cash', '4', '', '2026-07-12 16:37:01');
INSERT INTO `income` (`id`, `income_date`, `source`, `reference_no`, `student_id`, `description`, `amount`, `payment_method`, `received_by`, `remarks`, `created_at`) VALUES ('2', '2026-07-12', 'Student Fee Collection', 'REC-20260712-00008', '1', 'Fee collection for month: July 2026 (2026-2027)', '100.00', 'Cash', NULL, 'Verification automation test', '2026-07-12 17:49:01');
INSERT INTO `income` (`id`, `income_date`, `source`, `reference_no`, `student_id`, `description`, `amount`, `payment_method`, `received_by`, `remarks`, `created_at`) VALUES ('3', '2026-07-16', 'Student Fee Collection', 'REC-20260716-00009', '4', 'Fee collection for month: July 2026 (2026-2027)', '5600.00', 'Cash', '4', '', '2026-07-16 15:15:26');
INSERT INTO `income` (`id`, `income_date`, `source`, `reference_no`, `student_id`, `description`, `amount`, `payment_method`, `received_by`, `remarks`, `created_at`) VALUES ('4', '2026-09-11', 'Student Fee Collection', 'REC-20260911-00010', '13', 'Fee collection for month: September 2026 (2026-2027)', '1.04', 'Cash', '4', '', '2026-09-11 11:32:52');
INSERT INTO `income` (`id`, `income_date`, `source`, `reference_no`, `student_id`, `description`, `amount`, `payment_method`, `received_by`, `remarks`, `created_at`) VALUES ('5', '2026-09-11', 'Student Fee Collection', 'REC-20260911-00011', '2', 'Fee collection for month: September 2026 (2026-2027)', '5600.00', 'Cash', '4', '', '2026-09-11 11:33:39');


CREATE TABLE `leave_applications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `applicant_type` enum('student','staff') COLLATE utf8mb4_unicode_ci NOT NULL,
  `applicant_id` int NOT NULL,
  `leave_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Casual',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Pending','Approved','Rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `approved_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_days` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_leave_type` (`applicant_type`,`applicant_id`),
  KEY `idx_leave_status` (`status`),
  CONSTRAINT `leave_applications_ibfk_1` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `leave_applications` (`id`, `applicant_type`, `applicant_id`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by`, `created_at`, `updated_at`, `remarks`, `attachment`, `total_days`) VALUES ('1', 'student', '2', 'Sick Leave', '2026-07-12', '2026-07-14', 'Doctor advised rest due to flu', 'Rejected', '4', '2026-07-10 12:16:49', '2026-07-11 22:07:16', NULL, NULL, NULL);


CREATE TABLE `marks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `student_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `marks_obtained` decimal(5,2) NOT NULL DEFAULT '0.00',
  `total_marks` int NOT NULL DEFAULT '100',
  `remarks` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_marks_unique` (`exam_id`,`student_id`,`subject_id`),
  KEY `student_id` (`student_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `marks_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marks_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marks_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `payroll_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `salary_payment_date` int NOT NULL DEFAULT '10',
  `working_days_per_month` int NOT NULL DEFAULT '26',
  `late_deduction_rule` decimal(5,2) NOT NULL DEFAULT '0.25',
  `half_day_rule` decimal(5,2) NOT NULL DEFAULT '0.50',
  `absent_deduction_rule` decimal(5,2) NOT NULL DEFAULT '1.00',
  `overtime_rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(10) NOT NULL DEFAULT 'Rs.',
  `default_payment_method` enum('Cash','Bank Transfer','Cheque') NOT NULL DEFAULT 'Bank Transfer',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `payroll_settings` (`id`, `salary_payment_date`, `working_days_per_month`, `late_deduction_rule`, `half_day_rule`, `absent_deduction_rule`, `overtime_rate`, `currency`, `default_payment_method`) VALUES ('1', '10', '26', '0.25', '0.50', '1.00', '0.00', 'Rs.', 'Bank Transfer');


CREATE TABLE `permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('1', 'dashboard_view', 'Dashboard', 'View permission for Dashboard module', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('2', 'student_view', 'Student Registration', 'View permission for Student Registration module', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('3', 'student_create', 'Student Management', 'Ability to add new students', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('4', 'student_edit', 'Student Registration', 'Edit permission for Student Registration module', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('5', 'student_delete', 'Student Registration', 'Delete permission for Student Registration module', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('6', 'admission_view', 'Admission', 'Ability to view admissions', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('7', 'admission_create', 'Admission', 'Ability to process new applications', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('8', 'attendance_view', 'Student Attendance', 'View permission for Student Attendance module', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('9', 'attendance_mark', 'Attendance', 'Ability to mark daily attendance', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('10', 'fee_view', 'Fee Collection', 'View permission for Fee Collection module', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('11', 'fee_collect', 'Fee & Accounts', 'Ability to collect fees and record payments', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('12', 'fee_invoice', 'Fee & Accounts', 'Ability to generate fee invoices', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('13', 'exam_view', 'Examination', 'View permission for Examination module', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('14', 'exam_marks_entry', 'Examination', 'Ability to enter and edit exam marks', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('15', 'staff_view', 'HR / Staff', 'Ability to view school staff profiles', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('16', 'staff_manage', 'HR / Staff', 'Ability to add, edit or terminate staff records', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('17', 'communication_send', 'Communication', 'Ability to send SMS or Email notifications', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('18', 'report_view', 'Reports', 'View permission for Reports module', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('19', 'cash_view', 'Cash', 'Ability to view cash counters and flow logs', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('20', 'cash_transaction', 'Cash', 'Ability to record office cash transactions', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('21', 'system_settings', 'Administration', 'Ability to update ERP configuration settings', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('23', 'dashboard_add', 'Dashboard', 'Add permission for Dashboard module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('24', 'dashboard_edit', 'Dashboard', 'Edit permission for Dashboard module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('25', 'dashboard_delete', 'Dashboard', 'Delete permission for Dashboard module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('26', 'dashboard_print', 'Dashboard', 'Print permission for Dashboard module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('27', 'dashboard_export', 'Dashboard', 'Export permission for Dashboard module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('29', 'student_add', 'Student Registration', 'Add permission for Student Registration module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('32', 'student_print', 'Student Registration', 'Print permission for Student Registration module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('33', 'student_export', 'Student Registration', 'Export permission for Student Registration module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('35', 'attendance_add', 'Student Attendance', 'Add permission for Student Attendance module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('36', 'attendance_edit', 'Student Attendance', 'Edit permission for Student Attendance module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('37', 'attendance_delete', 'Student Attendance', 'Delete permission for Student Attendance module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('38', 'attendance_print', 'Student Attendance', 'Print permission for Student Attendance module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('39', 'attendance_export', 'Student Attendance', 'Export permission for Student Attendance module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('41', 'fee_add', 'Fee Collection', 'Add permission for Fee Collection module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('42', 'fee_edit', 'Fee Collection', 'Edit permission for Fee Collection module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('43', 'fee_delete', 'Fee Collection', 'Delete permission for Fee Collection module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('44', 'fee_print', 'Fee Collection', 'Print permission for Fee Collection module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('45', 'fee_export', 'Fee Collection', 'Export permission for Fee Collection module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('47', 'exam_add', 'Examination', 'Add permission for Examination module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('48', 'exam_edit', 'Examination', 'Edit permission for Examination module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('49', 'exam_delete', 'Examination', 'Delete permission for Examination module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('50', 'exam_print', 'Examination', 'Print permission for Examination module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('51', 'exam_export', 'Examination', 'Export permission for Examination module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('52', 'payroll_view', 'Payroll', 'View permission for Payroll module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('53', 'payroll_add', 'Payroll', 'Add permission for Payroll module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('54', 'payroll_edit', 'Payroll', 'Edit permission for Payroll module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('55', 'payroll_delete', 'Payroll', 'Delete permission for Payroll module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('56', 'payroll_print', 'Payroll', 'Print permission for Payroll module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('57', 'payroll_export', 'Payroll', 'Export permission for Payroll module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('58', 'accounts_view', 'Accounts', 'View permission for Accounts module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('59', 'accounts_add', 'Accounts', 'Add permission for Accounts module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('60', 'accounts_edit', 'Accounts', 'Edit permission for Accounts module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('61', 'accounts_delete', 'Accounts', 'Delete permission for Accounts module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('62', 'accounts_print', 'Accounts', 'Print permission for Accounts module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('63', 'accounts_export', 'Accounts', 'Export permission for Accounts module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('65', 'report_add', 'Reports', 'Add permission for Reports module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('66', 'report_edit', 'Reports', 'Edit permission for Reports module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('67', 'report_delete', 'Reports', 'Delete permission for Reports module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('68', 'report_print', 'Reports', 'Print permission for Reports module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('69', 'report_export', 'Reports', 'Export permission for Reports module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('70', 'communication_view', 'Communication', 'View permission for Communication module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('71', 'communication_add', 'Communication', 'Add permission for Communication module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('72', 'communication_edit', 'Communication', 'Edit permission for Communication module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('73', 'communication_delete', 'Communication', 'Delete permission for Communication module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('74', 'communication_print', 'Communication', 'Print permission for Communication module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('75', 'communication_export', 'Communication', 'Export permission for Communication module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('76', 'settings_view', 'Settings', 'View permission for Settings module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('77', 'settings_add', 'Settings', 'Add permission for Settings module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('78', 'settings_edit', 'Settings', 'Edit permission for Settings module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('79', 'settings_delete', 'Settings', 'Delete permission for Settings module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('80', 'settings_print', 'Settings', 'Print permission for Settings module', '2026-07-12 13:38:23');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('81', 'settings_export', 'Settings', 'Export permission for Settings module', '2026-07-12 13:38:23');


CREATE TABLE `positions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_type_id` int NOT NULL,
  `class_id` int NOT NULL,
  `student_id` int NOT NULL,
  `percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `grade` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position_no` int NOT NULL,
  `status` enum('Pass','Fail') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Fail',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_positions_unique` (`exam_type_id`,`class_id`,`student_id`),
  KEY `class_id` (`class_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `positions_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `positions_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `promotions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `from_class_id` int NOT NULL,
  `to_class_id` int NOT NULL,
  `academic_session` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `promotion_date` date NOT NULL,
  `status` enum('Promoted','Retained','Rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'Promoted',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `from_class_id` (`from_class_id`),
  KEY `to_class_id` (`to_class_id`),
  CONSTRAINT `promotions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotions_ibfk_2` FOREIGN KEY (`from_class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotions_ibfk_3` FOREIGN KEY (`to_class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `promotions` (`id`, `student_id`, `from_class_id`, `to_class_id`, `academic_session`, `promotion_date`, `status`, `created_at`) VALUES ('1', '5', '9', '10', '2026-2027', '2026-07-18', 'Promoted', '2026-07-18 08:01:20');


CREATE TABLE `report_cards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_type_id` int NOT NULL,
  `student_id` int NOT NULL,
  `teacher_remarks` text COLLATE utf8mb4_unicode_ci,
  `principal_remarks` text COLLATE utf8mb4_unicode_ci,
  `attendance_percentage` decimal(5,2) DEFAULT NULL,
  `promotion_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_report_cards_unique` (`exam_type_id`,`student_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `report_cards_ibfk_1` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `report_cards_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `student_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `marks_obtained` decimal(5,2) DEFAULT NULL,
  `remarks` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Present','Absent') COLLATE utf8mb4_unicode_ci DEFAULT 'Present',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_result_unique` (`exam_id`,`student_id`,`subject_id`),
  KEY `student_id` (`student_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `results_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `role_permissions` (
  `role_id` int NOT NULL,
  `permission_id` int NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '1');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '1');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('3', '1');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '2');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '2');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '3');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '3');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '4');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '4');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '5');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '5');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '6');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '6');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '7');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '7');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '8');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '8');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '9');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '9');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '10');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('3', '10');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '11');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('3', '11');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '12');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('3', '12');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '13');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '13');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '14');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '14');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '15');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '15');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '16');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '16');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '17');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '17');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '18');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '18');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('3', '18');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '19');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('3', '19');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '20');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('3', '20');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '21');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '23');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '24');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '25');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '26');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '27');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '29');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '32');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '33');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '35');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '36');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '37');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '38');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '39');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '41');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '42');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '43');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '44');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '45');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '47');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '48');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '49');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '50');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '51');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '52');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '53');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '54');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '55');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '56');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '57');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '58');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '59');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '60');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '61');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '62');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '63');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '65');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '66');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '67');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '68');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '69');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '70');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '71');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '72');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '73');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '74');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '75');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '76');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '77');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '78');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '79');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '80');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '81');


CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `name`, `code`, `description`, `created_at`, `updated_at`) VALUES ('1', 'Super Admin', 'super_admin', 'School Owner (Saeed) with unrestricted administrative permissions', '2026-07-10 12:16:49', '2026-07-10 12:16:49');
INSERT INTO `roles` (`id`, `name`, `code`, `description`, `created_at`, `updated_at`) VALUES ('2', 'School Admin', 'school_admin', 'Principal or Admin overseeing general school operations', '2026-07-10 12:16:49', '2026-07-10 12:16:49');
INSERT INTO `roles` (`id`, `name`, `code`, `description`, `created_at`, `updated_at`) VALUES ('3', 'Accountant / Cashier', 'accountant', 'Financial operations manager handling collections, cash, and bookkeeping', '2026-07-10 12:16:49', '2026-07-10 12:16:49');
INSERT INTO `roles` (`id`, `name`, `code`, `description`, `created_at`, `updated_at`) VALUES ('8', 'Receptionist', 'receptionist', 'Front desk officer managing admissions and basic logs', '2026-07-12 13:38:23', '2026-07-12 13:38:23');


CREATE TABLE `salary_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `processing_id` int NOT NULL,
  `staff_id` int NOT NULL,
  `working_days` int NOT NULL,
  `present_days` int NOT NULL,
  `absent_days` int NOT NULL,
  `late_days` int NOT NULL,
  `leave_days` int NOT NULL,
  `half_days` int NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `allowances` decimal(10,2) NOT NULL,
  `deductions` decimal(10,2) NOT NULL,
  `advance_salary_deduction` decimal(10,2) NOT NULL DEFAULT '0.00',
  `bonus` decimal(10,2) NOT NULL DEFAULT '0.00',
  `net_salary` decimal(10,2) NOT NULL,
  `payment_status` enum('Pending','Paid') NOT NULL DEFAULT 'Pending',
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `processing_id` (`processing_id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `salary_details_ibfk_1` FOREIGN KEY (`processing_id`) REFERENCES `salary_processing` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_details_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `salary_processing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `month` int NOT NULL,
  `year` int NOT NULL,
  `status` enum('Pending','Processed','Paid') NOT NULL DEFAULT 'Pending',
  `generated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_month_year` (`month`,`year`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `salary_setup` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL DEFAULT '0.00',
  `hra` decimal(10,2) NOT NULL DEFAULT '0.00',
  `medical_allowance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `transport_allowance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `other_allowances` decimal(10,2) NOT NULL DEFAULT '0.00',
  `provident_fund` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_deduction` decimal(10,2) NOT NULL DEFAULT '0.00',
  `eobi` decimal(10,2) NOT NULL DEFAULT '0.00',
  `other_deductions` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_method` enum('Cash','Bank Transfer','Cheque') NOT NULL DEFAULT 'Bank Transfer',
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_staff_setup` (`staff_id`),
  CONSTRAINT `salary_setup_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `salary_setup` (`id`, `staff_id`, `basic_salary`, `hra`, `medical_allowance`, `transport_allowance`, `other_allowances`, `provident_fund`, `tax_deduction`, `eobi`, `other_deductions`, `payment_method`, `bank_name`, `account_number`, `status`, `updated_at`) VALUES ('1', '1', '150000.00', '2000.00', '1000.00', '1500.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Bank Transfer', NULL, NULL, 'Active', '2026-07-12 18:29:51');
INSERT INTO `salary_setup` (`id`, `staff_id`, `basic_salary`, `hra`, `medical_allowance`, `transport_allowance`, `other_allowances`, `provident_fund`, `tax_deduction`, `eobi`, `other_deductions`, `payment_method`, `bank_name`, `account_number`, `status`, `updated_at`) VALUES ('2', '2', '80000.00', '2000.00', '1000.00', '1500.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Bank Transfer', NULL, NULL, 'Active', '2026-07-12 18:29:51');
INSERT INTO `salary_setup` (`id`, `staff_id`, `basic_salary`, `hra`, `medical_allowance`, `transport_allowance`, `other_allowances`, `provident_fund`, `tax_deduction`, `eobi`, `other_deductions`, `payment_method`, `bank_name`, `account_number`, `status`, `updated_at`) VALUES ('3', '3', '45000.00', '2000.00', '1000.00', '1500.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Bank Transfer', NULL, NULL, 'Active', '2026-07-12 18:29:51');
INSERT INTO `salary_setup` (`id`, `staff_id`, `basic_salary`, `hra`, `medical_allowance`, `transport_allowance`, `other_allowances`, `provident_fund`, `tax_deduction`, `eobi`, `other_deductions`, `payment_method`, `bank_name`, `account_number`, `status`, `updated_at`) VALUES ('4', '4', '40000.00', '2000.00', '1000.00', '1500.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Bank Transfer', NULL, NULL, 'Active', '2026-07-12 18:29:51');
INSERT INTO `salary_setup` (`id`, `staff_id`, `basic_salary`, `hra`, `medical_allowance`, `transport_allowance`, `other_allowances`, `provident_fund`, `tax_deduction`, `eobi`, `other_deductions`, `payment_method`, `bank_name`, `account_number`, `status`, `updated_at`) VALUES ('5', '5', '38000.00', '2000.00', '1000.00', '1500.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Bank Transfer', NULL, NULL, 'Active', '2026-07-12 18:29:51');
INSERT INTO `salary_setup` (`id`, `staff_id`, `basic_salary`, `hra`, `medical_allowance`, `transport_allowance`, `other_allowances`, `provident_fund`, `tax_deduction`, `eobi`, `other_deductions`, `payment_method`, `bank_name`, `account_number`, `status`, `updated_at`) VALUES ('6', '6', '42000.00', '2000.00', '1000.00', '1500.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'Bank Transfer', NULL, NULL, 'Active', '2026-07-12 18:29:51');


CREATE TABLE `salary_slips` (
  `id` int NOT NULL AUTO_INCREMENT,
  `salary_detail_id` int NOT NULL,
  `slip_number` varchar(50) NOT NULL,
  `prepared_by` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_detail` (`salary_detail_id`),
  UNIQUE KEY `idx_slip` (`slip_number`),
  KEY `prepared_by` (`prepared_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `salary_slips_ibfk_1` FOREIGN KEY (`salary_detail_id`) REFERENCES `salary_details` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_slips_ibfk_2` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salary_slips_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `school_houses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `house_color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `school_houses` (`id`, `name`, `house_color`, `status`, `created_at`) VALUES ('1', 'Jinnah House', '#dc3545', 'Active', '2026-07-12 13:38:23');
INSERT INTO `school_houses` (`id`, `name`, `house_color`, `status`, `created_at`) VALUES ('2', 'Iqbal House', '#0d6efd', 'Active', '2026-07-12 13:38:23');
INSERT INTO `school_houses` (`id`, `name`, `house_color`, `status`, `created_at`) VALUES ('3', 'Liaquat House', '#198754', 'Active', '2026-07-12 13:38:23');


CREATE TABLE `school_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `school_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `school_logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `school_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `whatsapp_number` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `principal_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registration_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_academic_session` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '2026-2027',
  `timezone` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asia/Karachi',
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PKR',
  `date_format` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Y-m-d',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `school_settings` (`id`, `school_name`, `school_logo`, `school_address`, `city`, `phone_number`, `whatsapp_number`, `email`, `website`, `principal_name`, `owner_name`, `registration_number`, `current_academic_session`, `timezone`, `currency`, `date_format`, `created_at`, `updated_at`) VALUES ('1', 'Indus Grammar School', 'storage/logos/school_logo_1784343830.jpg', 'Main Campus, Lahore, Pakistan', 'Lahore', '03334916860', '03334916860', 'info@indus.edu.pk', 'www.indus.edu.pk', 'Dr. Sajid Ali', 'Saeed', 'REG-IGS-2026', '2026-2027', 'Lahore', 'PKR', 'Y-m-d', '2026-07-12 13:38:23', '2026-07-18 08:04:47');


CREATE TABLE `sms_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `recipient_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_count` int NOT NULL DEFAULT '1',
  `recipients_list` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `scheduled_time` datetime DEFAULT NULL,
  `sent_by` int DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'Test Sent',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sent_by` (`sent_by`),
  CONSTRAINT `sms_history_ibfk_1` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sms_history` (`id`, `recipient_type`, `recipient_count`, `recipients_list`, `message`, `scheduled_time`, `sent_by`, `status`, `created_at`) VALUES ('1', 'Single Student', '2', '{\"03334445566\":\"Ayesha Ahmed\",\"03066544806\":\"Waqas Al\"}', 'Dear Parent, your child was marked absent today from Indus Grammar School. Please contact the class teacher for details.', NULL, '4', 'Sent', '2026-07-16 16:21:27');
INSERT INTO `sms_history` (`id`, `recipient_type`, `recipient_count`, `recipients_list`, `message`, `scheduled_time`, `sent_by`, `status`, `created_at`) VALUES ('2', 'Single Student', '2', '{\"03334445566\":\"Ayesha Ahmed\",\"03066544806\":\"Waqas Al\"}', 'Dear Parent, your child was marked absent today from Indus Grammar School. Please contact the class teacher for details.', NULL, '4', 'Sent', '2026-07-16 16:21:31');
INSERT INTO `sms_history` (`id`, `recipient_type`, `recipient_count`, `recipients_list`, `message`, `scheduled_time`, `sent_by`, `status`, `created_at`) VALUES ('3', 'Single Student', '2', '{\"03334445566\":\"Ayesha Ahmed\",\"03066544806\":\"Waqas Al\"}', 'Dear Parents/Students, the date sheet for the upcoming Final Term examinations has been published. Schedules are accessible on the portal.', NULL, '4', 'Sent', '2026-07-16 16:21:44');


CREATE TABLE `staff` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` enum('Male','Female') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Male',
  `designation` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Academic',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `date_of_joining` date NOT NULL,
  `salary` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('Active','Inactive','Terminated') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_no` (`employee_no`),
  KEY `user_id` (`user_id`),
  KEY `idx_staff_status` (`status`),
  KEY `idx_staff_dept` (`department`),
  CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `staff` (`id`, `employee_no`, `user_id`, `first_name`, `last_name`, `gender`, `designation`, `department`, `phone`, `email`, `address`, `date_of_joining`, `salary`, `status`, `created_at`, `updated_at`) VALUES ('1', 'EMP-2024-001', '1', 'Saeed', 'Ahmed', 'Male', 'Owner / Director', 'Management', '03001234567', 'saeed@indus.edu.pk', 'DHA Phase 5, Karachi', '2024-01-01', '150000.00', 'Active', '2026-07-10 12:16:49', '2026-07-10 12:16:49');
INSERT INTO `staff` (`id`, `employee_no`, `user_id`, `first_name`, `last_name`, `gender`, `designation`, `department`, `phone`, `email`, `address`, `date_of_joining`, `salary`, `status`, `created_at`, `updated_at`) VALUES ('2', 'EMP-2024-002', '2', 'Asma', 'Khan', 'Male', 'Principal', 'Administration', '03112223344', 'admin@indus.edu.pk', 'Gulshan-e-Iqbal, Karachi', '2024-03-15', '80000.00', 'Active', '2026-07-10 12:16:49', '2026-07-10 12:16:49');
INSERT INTO `staff` (`id`, `employee_no`, `user_id`, `first_name`, `last_name`, `gender`, `designation`, `department`, `phone`, `email`, `address`, `date_of_joining`, `salary`, `status`, `created_at`, `updated_at`) VALUES ('3', 'EMP-2024-003', '3', 'Imran', 'Hussain', 'Male', 'Accountant', 'Finance', '03211112233', 'cashier@indus.edu.pk', 'North Nazimabad, Karachi', '2024-06-01', '45000.00', 'Active', '2026-07-10 12:16:49', '2026-07-10 12:16:49');
INSERT INTO `staff` (`id`, `employee_no`, `user_id`, `first_name`, `last_name`, `gender`, `designation`, `department`, `phone`, `email`, `address`, `date_of_joining`, `salary`, `status`, `created_at`, `updated_at`) VALUES ('4', 'EMP-2025-004', NULL, 'Fatima', 'Noor', 'Male', 'English Teacher', 'Academic', '03331234567', 'fatima.noor@gmail.com', 'PECHS Block 2, Karachi', '2025-01-10', '40000.00', 'Active', '2026-07-10 12:16:49', '2026-07-10 12:16:49');
INSERT INTO `staff` (`id`, `employee_no`, `user_id`, `first_name`, `last_name`, `gender`, `designation`, `department`, `phone`, `email`, `address`, `date_of_joining`, `salary`, `status`, `created_at`, `updated_at`) VALUES ('5', 'EMP-2025-005', NULL, 'Ali', 'Raza', 'Male', 'Math Teacher', 'Academic', '03441234567', NULL, 'Nazimabad Block 3, Karachi', '2025-02-01', '38000.00', 'Active', '2026-07-10 12:16:49', '2026-07-10 12:16:49');
INSERT INTO `staff` (`id`, `employee_no`, `user_id`, `first_name`, `last_name`, `gender`, `designation`, `department`, `phone`, `email`, `address`, `date_of_joining`, `salary`, `status`, `created_at`, `updated_at`) VALUES ('6', 'EMP-2025-006', NULL, 'Sana', 'Tariq', 'Male', 'Science Teacher', 'Academic', '03551234567', 'sana.tariq@gmail.com', 'Clifton Block 5, Karachi', '2025-04-01', '42000.00', 'Active', '2026-07-10 12:16:49', '2026-07-10 12:16:49');


CREATE TABLE `staff_attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `date` date NOT NULL,
  `status` enum('Present','Absent','Late','Leave','Half Day') NOT NULL DEFAULT 'Present',
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_staff_date` (`staff_id`,`date`),
  UNIQUE KEY `staff_date_unique` (`staff_id`,`date`),
  KEY `idx_staff_att_date` (`date`),
  CONSTRAINT `staff_attendance_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `staff_attendance` (`id`, `staff_id`, `date`, `status`, `check_in_time`, `check_out_time`, `remarks`, `created_at`) VALUES ('1', '1', '2026-07-09', 'Present', '07:45:00', '14:30:00', NULL, '2026-07-12 18:02:59');
INSERT INTO `staff_attendance` (`id`, `staff_id`, `date`, `status`, `check_in_time`, `check_out_time`, `remarks`, `created_at`) VALUES ('2', '2', '2026-07-09', 'Present', '07:50:00', '14:15:00', NULL, '2026-07-12 18:02:59');
INSERT INTO `staff_attendance` (`id`, `staff_id`, `date`, `status`, `check_in_time`, `check_out_time`, `remarks`, `created_at`) VALUES ('3', '3', '2026-07-09', 'Late', '08:25:00', '14:00:00', 'Traffic delay', '2026-07-12 18:02:59');
INSERT INTO `staff_attendance` (`id`, `staff_id`, `date`, `status`, `check_in_time`, `check_out_time`, `remarks`, `created_at`) VALUES ('4', '1', '2026-07-12', 'Absent', NULL, NULL, NULL, '2026-07-12 18:02:59');


CREATE TABLE `staff_attendance_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `attendance_id` int NOT NULL,
  `log_type` enum('Check In','Check Out') NOT NULL,
  `timestamp` datetime NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendance_id` (`attendance_id`),
  CONSTRAINT `staff_attendance_details_ibfk_1` FOREIGN KEY (`attendance_id`) REFERENCES `staff_attendance` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `staff_attendance_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `office_start_time` time NOT NULL DEFAULT '08:00:00',
  `office_end_time` time NOT NULL DEFAULT '14:00:00',
  `late_arrival_time` time NOT NULL DEFAULT '08:15:00',
  `half_day_time` time NOT NULL DEFAULT '11:00:00',
  `working_days` varchar(100) NOT NULL DEFAULT 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
  `weekend_days` varchar(100) NOT NULL DEFAULT 'Sunday',
  `attendance_lock_time` time NOT NULL DEFAULT '23:59:59',
  `allow_attendance_editing` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `staff_attendance_settings` (`id`, `office_start_time`, `office_end_time`, `late_arrival_time`, `half_day_time`, `working_days`, `weekend_days`, `attendance_lock_time`, `allow_attendance_editing`, `updated_at`) VALUES ('1', '08:00:00', '14:00:00', '08:15:00', '11:00:00', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', 'Sunday', '23:59:59', '1', '2026-07-12 18:02:59');


CREATE TABLE `staff_leave` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `leave_type` enum('Casual Leave','Medical Leave','Annual Leave','Emergency Leave') NOT NULL DEFAULT 'Casual Leave',
  `leave_from` date NOT NULL,
  `leave_to` date NOT NULL,
  `total_days` int NOT NULL,
  `reason` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approved_by` int DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `staff_leave_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_leave_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `student_complaints` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `class_id` int DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Medium',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `complaint_date` date NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `assigned_to` int DEFAULT NULL,
  `resolution` text COLLATE utf8mb4_unicode_ci,
  `attachment_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `complaint_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_taken` text COLLATE utf8mb4_unicode_ci,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `class_id` (`class_id`),
  KEY `assigned_to` (`assigned_to`),
  CONSTRAINT `student_complaints_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_complaints_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_complaints_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `student_complaints` (`id`, `student_id`, `class_id`, `category`, `priority`, `title`, `description`, `complaint_date`, `status`, `assigned_to`, `resolution`, `attachment_path`, `created_at`, `updated_at`, `complaint_number`, `action_taken`, `remarks`, `created_by`) VALUES ('1', '1', '4', 'Discipline', 'Medium', 'Test Title', 'Test Desc', '2026-07-17', 'Pending', NULL, NULL, NULL, '2026-07-17 14:17:10', '2026-07-17 14:17:10', 'TEST-001', 'Action', 'Remarks', '1');


CREATE TABLE `student_fee_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `fee_structure_id` int NOT NULL,
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `discount_flat` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_reason` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_assignment` (`student_id`),
  KEY `fee_structure_id` (`fee_structure_id`),
  CONSTRAINT `student_fee_assignments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_fee_assignments_ibfk_2` FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structure` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('2', '2', '23', '0.00', '0.00', '', 'Active', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('3', '4', '23', '0.00', '0.00', '', 'Active', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('4', '5', '13', '0.00', '0.00', '', 'Active', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('5', '7', '23', '0.00', '0.00', '', 'Active', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('6', '8', '4', '0.00', '0.00', '', 'Active', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('7', '9', '18', '0.00', '0.00', '', 'Active', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('8', '1', '25', '0.00', '0.00', '', 'Active', '2026-07-12 13:15:56', '2026-07-12 13:15:56');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('9', '10', '26', '0.00', '0.00', '', 'Active', '2026-07-17 12:27:04', '2026-07-17 12:27:04');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('10', '11', '27', '0.00', '0.00', '', 'Active', '2026-09-05 12:57:06', '2026-09-05 12:57:06');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('11', '12', '28', '0.00', '0.00', '', 'Active', '2026-09-05 12:57:06', '2026-09-05 12:57:06');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('12', '13', '28', '0.00', '0.00', '', 'Active', '2026-09-11 11:32:29', '2026-09-11 11:32:29');


CREATE TABLE `student_marks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_type_id` int NOT NULL,
  `student_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `marks_obtained` decimal(5,2) DEFAULT NULL,
  `remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Present','Absent','Leave','Exempt') COLLATE utf8mb4_unicode_ci DEFAULT 'Present',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_student_marks_unique` (`exam_type_id`,`student_id`,`subject_id`),
  KEY `student_id` (`student_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `student_marks_ibfk_1` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_marks_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_marks_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `student_marks` (`id`, `exam_type_id`, `student_id`, `subject_id`, `marks_obtained`, `remarks`, `status`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '1', '70.00', '', 'Present', '2026-07-18 07:47:33', '2026-07-18 07:47:33');


CREATE TABLE `student_registration_details` (
  `student_id` int NOT NULL,
  `roll_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admission_date` date DEFAULT NULL,
  `academic_session` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `campus` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blood_group` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `religion` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationality` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cnic_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_cert_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_place` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `student_mobile` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `student_email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_cnic` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_mobile` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_occupation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_office` text COLLATE utf8mb4_unicode_ci,
  `father_income` decimal(10,2) DEFAULT '0.00',
  `father_email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_cnic` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_mobile` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_occupation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_relationship` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_cnic` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_address` text COLLATE utf8mb4_unicode_ci,
  `current_address` text COLLATE utf8mb4_unicode_ci,
  `permanent_address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prev_school` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prev_class` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prev_roll_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prev_result` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `leaving_cert_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `test_marks` decimal(5,2) DEFAULT '0.00',
  `medical_condition` text COLLATE utf8mb4_unicode_ci,
  `allergies` text COLLATE utf8mb4_unicode_ci,
  `disability` text COLLATE utf8mb4_unicode_ci,
  `emergency_contact` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doctor_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doctor_contact` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transport_required` tinyint(1) DEFAULT '0',
  `transport_route` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_point` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `drop_point` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transport_vehicle` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transport_driver` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sponsor_required` tinyint(1) DEFAULT '0',
  `sponsor_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sponsor_org` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sponsor_contact` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sponsor_amount` decimal(10,2) DEFAULT '0.00',
  `fee_plan` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fee_admission` decimal(10,2) DEFAULT '0.00',
  `fee_monthly` decimal(10,2) DEFAULT '0.00',
  `fee_discount` decimal(10,2) DEFAULT '0.00',
  `fee_scholarship` decimal(10,2) DEFAULT '0.00',
  `fee_fine` decimal(10,2) DEFAULT '0.00',
  `fee_security` decimal(10,2) DEFAULT '0.00',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `special_notes` text COLLATE utf8mb4_unicode_ci,
  `doc_student_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doc_father_cnic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doc_mother_cnic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doc_bform` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doc_birth_cert` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doc_leaving_cert` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doc_prev_result` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doc_medical_cert` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doc_other` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `academic_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'School',
  `school_class` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `school_section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `academy_program` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `academy_batch` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `roll_no` (`roll_no`),
  UNIQUE KEY `cnic_no` (`cnic_no`),
  CONSTRAINT `student_registration_details_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `student_registration_details` (`student_id`, `roll_no`, `admission_date`, `academic_session`, `campus`, `blood_group`, `religion`, `nationality`, `cnic_no`, `birth_cert_no`, `birth_place`, `student_mobile`, `student_email`, `father_name`, `father_cnic`, `father_mobile`, `father_occupation`, `father_office`, `father_income`, `father_email`, `father_photo`, `mother_name`, `mother_cnic`, `mother_mobile`, `mother_occupation`, `mother_email`, `mother_photo`, `guardian_relationship`, `guardian_cnic`, `guardian_address`, `current_address`, `permanent_address`, `city`, `province`, `postal_code`, `country`, `prev_school`, `prev_class`, `prev_roll_no`, `prev_result`, `leaving_cert_no`, `test_marks`, `medical_condition`, `allergies`, `disability`, `emergency_contact`, `doctor_name`, `doctor_contact`, `transport_required`, `transport_route`, `pickup_point`, `drop_point`, `transport_vehicle`, `transport_driver`, `sponsor_required`, `sponsor_name`, `sponsor_org`, `sponsor_contact`, `sponsor_amount`, `fee_plan`, `fee_admission`, `fee_monthly`, `fee_discount`, `fee_scholarship`, `fee_fine`, `fee_security`, `remarks`, `special_notes`, `doc_student_photo`, `doc_father_cnic`, `doc_mother_cnic`, `doc_bform`, `doc_birth_cert`, `doc_leaving_cert`, `doc_prev_result`, `doc_medical_cert`, `doc_other`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('5', 'IGS-ROLL-2026-0005', '2026-07-10', '2026-2027', 'Main Campus', 'A+', 'Islam', 'Pakistani', '3530176003815', '1234', 'Lahore', '0406654489', 'waqas760000@gmail.com', 'Salem', '3530176003815', '03066544806', 'Jutt', 'Colloge road Township lahore', '1000.00', 'waqas760000@gmail.com', '', 'Sharifa', '12345678676453421', '03066544806', 'Lady', 'waqas760000@gmail.com', '', 'Amir', '35301760032454', 'Colloge road Township lahore', 'Lajore', 'Lahore', 'Lahore', 'Punjab', '54000', 'Pakistan', 'No', 'No', 'No', 'No', 'No', '1.00', 'Nothin', 'No', 'No', '03066544806', 'No', 'No', '0', 'No', '', '', '', '', '0', '', '', '', '0.00', 'Regular Plan', '5000.00', '3000.00', '0.00', '0.00', '0.00', '0.00', '', '', '', '', '', '', '', '', '', '', '', '2026-07-10 18:14:18', '2026-07-10 18:14:18', 'School', NULL, NULL, NULL, NULL);
INSERT INTO `student_registration_details` (`student_id`, `roll_no`, `admission_date`, `academic_session`, `campus`, `blood_group`, `religion`, `nationality`, `cnic_no`, `birth_cert_no`, `birth_place`, `student_mobile`, `student_email`, `father_name`, `father_cnic`, `father_mobile`, `father_occupation`, `father_office`, `father_income`, `father_email`, `father_photo`, `mother_name`, `mother_cnic`, `mother_mobile`, `mother_occupation`, `mother_email`, `mother_photo`, `guardian_relationship`, `guardian_cnic`, `guardian_address`, `current_address`, `permanent_address`, `city`, `province`, `postal_code`, `country`, `prev_school`, `prev_class`, `prev_roll_no`, `prev_result`, `leaving_cert_no`, `test_marks`, `medical_condition`, `allergies`, `disability`, `emergency_contact`, `doctor_name`, `doctor_contact`, `transport_required`, `transport_route`, `pickup_point`, `drop_point`, `transport_vehicle`, `transport_driver`, `sponsor_required`, `sponsor_name`, `sponsor_org`, `sponsor_contact`, `sponsor_amount`, `fee_plan`, `fee_admission`, `fee_monthly`, `fee_discount`, `fee_scholarship`, `fee_fine`, `fee_security`, `remarks`, `special_notes`, `doc_student_photo`, `doc_father_cnic`, `doc_mother_cnic`, `doc_bform`, `doc_birth_cert`, `doc_leaving_cert`, `doc_prev_result`, `doc_medical_cert`, `doc_other`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('7', 'ROLL-2026-0006', '2026-07-11', '2026-2027', 'Main Campus', 'A+', 'Islam', 'Pakistani', '35301-7600381-5', '1234', 'Lahore', '03066544806', 'waqas760000@gmail.com', 'Salem', '35301-7600381-7', '03066544806', 'Jutt', 'Colloge road Township lahore', '10000.00', 'waqas760000@gmail.com', 'uploads/students/fcnic_7_1783777950_6a524a9e986f4.jpeg', 'Sharifa', '35301-2165408-4', '03066544806', 'Lady', 'waqas760000@gmail.com', 'uploads/students/mcnic_7_1783777950_6a524a9e98a24.jpeg', 'Amir', '35301-76003245-4', 'Colloge road Township lahore', 'Colloge road Township lahore', 'lahore', 'Lahore', 'Punjab', '54000', 'Pakistan', 'No', 'No', 'No', 'No', 'No', '99.00', 'Nothin', 'No', 'No', '03066544806', 'No', '03066544806', '0', '', '', '', '', '', '0', '', '', '', '0.00', 'Regular Plan', '5000.00', '3000.00', '700.00', '800.00', '80.00', '0.00', '', '', 'uploads/students/std_7_1783777950_6a524a9e9842a.jpeg', 'uploads/students/fcnic_7_1783777950_6a524a9e986f4.jpeg', 'uploads/students/mcnic_7_1783777950_6a524a9e98a24.jpeg', 'uploads/students/bform_7_1783777950_6a524a9e98bc2.jpeg', 'uploads/students/birth_7_1783777950_6a524a9e98e50.jpeg', 'uploads/students/leaving_7_1783777950_6a524a9e98f9b.jpeg', 'uploads/students/result_7_1783777950_6a524a9e991e1.jpeg', 'uploads/students/medical_7_1783777950_6a524a9e99409.jpeg', 'uploads/students/other_7_1783777950_6a524a9e99562.jpeg', '2026-07-11 18:52:30', '2026-07-11 18:52:30', 'School', NULL, NULL, NULL, NULL);
INSERT INTO `student_registration_details` (`student_id`, `roll_no`, `admission_date`, `academic_session`, `campus`, `blood_group`, `religion`, `nationality`, `cnic_no`, `birth_cert_no`, `birth_place`, `student_mobile`, `student_email`, `father_name`, `father_cnic`, `father_mobile`, `father_occupation`, `father_office`, `father_income`, `father_email`, `father_photo`, `mother_name`, `mother_cnic`, `mother_mobile`, `mother_occupation`, `mother_email`, `mother_photo`, `guardian_relationship`, `guardian_cnic`, `guardian_address`, `current_address`, `permanent_address`, `city`, `province`, `postal_code`, `country`, `prev_school`, `prev_class`, `prev_roll_no`, `prev_result`, `leaving_cert_no`, `test_marks`, `medical_condition`, `allergies`, `disability`, `emergency_contact`, `doctor_name`, `doctor_contact`, `transport_required`, `transport_route`, `pickup_point`, `drop_point`, `transport_vehicle`, `transport_driver`, `sponsor_required`, `sponsor_name`, `sponsor_org`, `sponsor_contact`, `sponsor_amount`, `fee_plan`, `fee_admission`, `fee_monthly`, `fee_discount`, `fee_scholarship`, `fee_fine`, `fee_security`, `remarks`, `special_notes`, `doc_student_photo`, `doc_father_cnic`, `doc_mother_cnic`, `doc_bform`, `doc_birth_cert`, `doc_leaving_cert`, `doc_prev_result`, `doc_medical_cert`, `doc_other`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('8', 'ROLL-2026-0005', '2026-07-11', '2026-2027', 'Main Campus', NULL, NULL, NULL, '35301-7600381-1', NULL, NULL, '03066544806', 'waqas760000@gmail.com', 'Waqas Ali', '35301-7600381-9', '03066544806', NULL, NULL, '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Brother', '', '', 'Colloge road Township lahore', 'Lahore', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0.00', 'Regular Plan', '5000.00', '3000.00', '0.00', '0.00', '0.00', '0.00', '', NULL, 'uploads/students/std_8_1783786571_6a526c4b59d8f.jpeg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-11 21:16:11', '2026-07-11 21:16:11', 'Academy', 'Class 1', 'B', NULL, NULL);
INSERT INTO `student_registration_details` (`student_id`, `roll_no`, `admission_date`, `academic_session`, `campus`, `blood_group`, `religion`, `nationality`, `cnic_no`, `birth_cert_no`, `birth_place`, `student_mobile`, `student_email`, `father_name`, `father_cnic`, `father_mobile`, `father_occupation`, `father_office`, `father_income`, `father_email`, `father_photo`, `mother_name`, `mother_cnic`, `mother_mobile`, `mother_occupation`, `mother_email`, `mother_photo`, `guardian_relationship`, `guardian_cnic`, `guardian_address`, `current_address`, `permanent_address`, `city`, `province`, `postal_code`, `country`, `prev_school`, `prev_class`, `prev_roll_no`, `prev_result`, `leaving_cert_no`, `test_marks`, `medical_condition`, `allergies`, `disability`, `emergency_contact`, `doctor_name`, `doctor_contact`, `transport_required`, `transport_route`, `pickup_point`, `drop_point`, `transport_vehicle`, `transport_driver`, `sponsor_required`, `sponsor_name`, `sponsor_org`, `sponsor_contact`, `sponsor_amount`, `fee_plan`, `fee_admission`, `fee_monthly`, `fee_discount`, `fee_scholarship`, `fee_fine`, `fee_security`, `remarks`, `special_notes`, `doc_student_photo`, `doc_father_cnic`, `doc_mother_cnic`, `doc_bform`, `doc_birth_cert`, `doc_leaving_cert`, `doc_prev_result`, `doc_medical_cert`, `doc_other`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('9', 'ROLL-2026-0009', '2026-07-12', '2026-2027', 'Main Campus', NULL, NULL, NULL, '35301-5876340-9', NULL, NULL, '03227247059', 'waqas760000@gmail.com', 'Sabir ali', '35301-7444555-9', '03144273870', NULL, NULL, '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Father', '', '', 'Thokar', 'Fattiana', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0.00', 'Regular Plan', '5000.00', '2000.00', '0.00', '0.00', '0.00', '0.00', 'Okat', NULL, 'uploads/students/std_9_1783819713_6a52edc1e66c3.jpeg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-12 06:28:33', '2026-07-12 06:28:33', 'Academy', 'Class 6', 'A', NULL, NULL);
INSERT INTO `student_registration_details` (`student_id`, `roll_no`, `admission_date`, `academic_session`, `campus`, `blood_group`, `religion`, `nationality`, `cnic_no`, `birth_cert_no`, `birth_place`, `student_mobile`, `student_email`, `father_name`, `father_cnic`, `father_mobile`, `father_occupation`, `father_office`, `father_income`, `father_email`, `father_photo`, `mother_name`, `mother_cnic`, `mother_mobile`, `mother_occupation`, `mother_email`, `mother_photo`, `guardian_relationship`, `guardian_cnic`, `guardian_address`, `current_address`, `permanent_address`, `city`, `province`, `postal_code`, `country`, `prev_school`, `prev_class`, `prev_roll_no`, `prev_result`, `leaving_cert_no`, `test_marks`, `medical_condition`, `allergies`, `disability`, `emergency_contact`, `doctor_name`, `doctor_contact`, `transport_required`, `transport_route`, `pickup_point`, `drop_point`, `transport_vehicle`, `transport_driver`, `sponsor_required`, `sponsor_name`, `sponsor_org`, `sponsor_contact`, `sponsor_amount`, `fee_plan`, `fee_admission`, `fee_monthly`, `fee_discount`, `fee_scholarship`, `fee_fine`, `fee_security`, `remarks`, `special_notes`, `doc_student_photo`, `doc_father_cnic`, `doc_mother_cnic`, `doc_bform`, `doc_birth_cert`, `doc_leaving_cert`, `doc_prev_result`, `doc_medical_cert`, `doc_other`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('10', 'ROLL-2026-0011', '2026-07-16', '2026-2027', 'Main Campus', NULL, NULL, NULL, '35301-5960795-9', NULL, NULL, '03066544806', 'waqas760000@gmail.com', 'Muhammad Yasin', '35301-5055484-7', '03014586572', NULL, NULL, '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'father', '', '', 'Colloge road Township lahore', 'Okara', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0.00', 'Regular Plan', '5000.00', '3000.00', '0.00', '0.00', '0.00', '0.00', 'Good', NULL, 'uploads/students/std_10_1784209440_6a58e020de093.png', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-16 18:44:00', '2026-07-16 18:44:00', 'School', 'Class 9', 'A', NULL, NULL);
INSERT INTO `student_registration_details` (`student_id`, `roll_no`, `admission_date`, `academic_session`, `campus`, `blood_group`, `religion`, `nationality`, `cnic_no`, `birth_cert_no`, `birth_place`, `student_mobile`, `student_email`, `father_name`, `father_cnic`, `father_mobile`, `father_occupation`, `father_office`, `father_income`, `father_email`, `father_photo`, `mother_name`, `mother_cnic`, `mother_mobile`, `mother_occupation`, `mother_email`, `mother_photo`, `guardian_relationship`, `guardian_cnic`, `guardian_address`, `current_address`, `permanent_address`, `city`, `province`, `postal_code`, `country`, `prev_school`, `prev_class`, `prev_roll_no`, `prev_result`, `leaving_cert_no`, `test_marks`, `medical_condition`, `allergies`, `disability`, `emergency_contact`, `doctor_name`, `doctor_contact`, `transport_required`, `transport_route`, `pickup_point`, `drop_point`, `transport_vehicle`, `transport_driver`, `sponsor_required`, `sponsor_name`, `sponsor_org`, `sponsor_contact`, `sponsor_amount`, `fee_plan`, `fee_admission`, `fee_monthly`, `fee_discount`, `fee_scholarship`, `fee_fine`, `fee_security`, `remarks`, `special_notes`, `doc_student_photo`, `doc_father_cnic`, `doc_mother_cnic`, `doc_bform`, `doc_birth_cert`, `doc_leaving_cert`, `doc_prev_result`, `doc_medical_cert`, `doc_other`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('11', 'IGS-2026-0011', '2026-07-19', '2026-2027', 'Main Campus', NULL, NULL, NULL, '35301-7070999-7', NULL, NULL, '03027711111', 'waqas760000@gmail.com', 'Ashiq', '35301-7474888-9', '03066544806', NULL, NULL, '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'father', '', '', 'Main Campus, Lahore, Pakistan', 'Lahore', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0.00', 'Regular Plan', '5000.00', '5000.00', '0.00', '0.00', '0.00', '0.00', '', NULL, 'uploads/students/std_11_1784456407_6a5ca4d7431d0.jpeg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-19 15:20:07', '2026-07-19 15:20:07', 'School', 'Class 8', 'A', NULL, NULL);
INSERT INTO `student_registration_details` (`student_id`, `roll_no`, `admission_date`, `academic_session`, `campus`, `blood_group`, `religion`, `nationality`, `cnic_no`, `birth_cert_no`, `birth_place`, `student_mobile`, `student_email`, `father_name`, `father_cnic`, `father_mobile`, `father_occupation`, `father_office`, `father_income`, `father_email`, `father_photo`, `mother_name`, `mother_cnic`, `mother_mobile`, `mother_occupation`, `mother_email`, `mother_photo`, `guardian_relationship`, `guardian_cnic`, `guardian_address`, `current_address`, `permanent_address`, `city`, `province`, `postal_code`, `country`, `prev_school`, `prev_class`, `prev_roll_no`, `prev_result`, `leaving_cert_no`, `test_marks`, `medical_condition`, `allergies`, `disability`, `emergency_contact`, `doctor_name`, `doctor_contact`, `transport_required`, `transport_route`, `pickup_point`, `drop_point`, `transport_vehicle`, `transport_driver`, `sponsor_required`, `sponsor_name`, `sponsor_org`, `sponsor_contact`, `sponsor_amount`, `fee_plan`, `fee_admission`, `fee_monthly`, `fee_discount`, `fee_scholarship`, `fee_fine`, `fee_security`, `remarks`, `special_notes`, `doc_student_photo`, `doc_father_cnic`, `doc_mother_cnic`, `doc_bform`, `doc_birth_cert`, `doc_leaving_cert`, `doc_prev_result`, `doc_medical_cert`, `doc_other`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('12', 'IGS-2026-0012', '2026-07-26', '2026-2027', 'Main Campus', NULL, NULL, NULL, '35301-4455666-9', NULL, NULL, '03044443222', 'waqas760000@gmail.com', 'Jawad Ahmed', '35301-5432128-9', '03076544999', NULL, NULL, '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Father', '', '', 'Main Campus, Lahore, Pakistan', 'Lahore', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0.00', 'Regular Plan', '4000.00', '2500.00', '0.00', '0.00', '0.00', '0.00', '', NULL, 'uploads/students/std_12_1785043584_6a659a8007451.jpg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-26 10:26:24', '2026-07-26 10:26:24', 'School', 'Class 7', 'A', NULL, NULL);
INSERT INTO `student_registration_details` (`student_id`, `roll_no`, `admission_date`, `academic_session`, `campus`, `blood_group`, `religion`, `nationality`, `cnic_no`, `birth_cert_no`, `birth_place`, `student_mobile`, `student_email`, `father_name`, `father_cnic`, `father_mobile`, `father_occupation`, `father_office`, `father_income`, `father_email`, `father_photo`, `mother_name`, `mother_cnic`, `mother_mobile`, `mother_occupation`, `mother_email`, `mother_photo`, `guardian_relationship`, `guardian_cnic`, `guardian_address`, `current_address`, `permanent_address`, `city`, `province`, `postal_code`, `country`, `prev_school`, `prev_class`, `prev_roll_no`, `prev_result`, `leaving_cert_no`, `test_marks`, `medical_condition`, `allergies`, `disability`, `emergency_contact`, `doctor_name`, `doctor_contact`, `transport_required`, `transport_route`, `pickup_point`, `drop_point`, `transport_vehicle`, `transport_driver`, `sponsor_required`, `sponsor_name`, `sponsor_org`, `sponsor_contact`, `sponsor_amount`, `fee_plan`, `fee_admission`, `fee_monthly`, `fee_discount`, `fee_scholarship`, `fee_fine`, `fee_security`, `remarks`, `special_notes`, `doc_student_photo`, `doc_father_cnic`, `doc_mother_cnic`, `doc_bform`, `doc_birth_cert`, `doc_leaving_cert`, `doc_prev_result`, `doc_medical_cert`, `doc_other`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('13', 'IGS-2026-0016', '2026-09-11', '2026-2027', 'Main Campus', NULL, NULL, NULL, '35101-5055490-5', NULL, NULL, '03066544806', 'waqas760000@gmail.com', 'Muammad ali', '35101-4323290-7', '03066544806', NULL, NULL, '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Brother', '', '', 'Main Campus, Lahore, Pakistan', 'Hujara', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0.00', NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0.00', 'Regular Plan', '5000.00', '3000.00', '0.00', '0.00', '0.00', '0.00', 'Hi', NULL, 'uploads/students/std_13_1789102798_6aa38aceb475a.jpeg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-11 09:59:58', '2026-09-11 09:59:58', 'School', 'Class 7', 'A', NULL, NULL);


CREATE TABLE `student_sponsors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sponsor_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `student_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `duration` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_sponsors_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admission_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` enum('Male','Female','Other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_birth` date NOT NULL,
  `enrollment_date` date NOT NULL,
  `class_id` int DEFAULT NULL,
  `status` enum('Active','Inactive','Suspended') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `guardian_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guardian_phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guardian_email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `academic_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'School',
  `school_class` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `school_section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `academy_program` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `academy_batch` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admission_no` (`admission_no`),
  KEY `idx_students_class` (`class_id`),
  KEY `idx_students_status` (`status`),
  KEY `idx_students_name` (`first_name`,`last_name`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('1', 'IGS-2026-0001', 'Zain', 'Khan', 'Male', '2019-04-12', '2026-03-01', '4', 'Active', 'Asif Khan', '03215551234', 'asif@gmail.com', 'Flat C-4, Gulshan-e-Iqbal, Karachi', '2026-07-10 12:16:49', '2026-07-10 12:16:49', 'School', NULL, NULL, NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('2', 'IGS-2026-0002', 'Ayesha', 'Ahmed', 'Female', '2020-09-22', '2026-03-01', '3', 'Active', 'Farhan Ahmed', '03334445566', 'farhan@outlook.com', 'House 42, Block 13-D, Gulshan, Karachi', '2026-07-10 12:16:49', '2026-07-10 12:16:49', 'School', NULL, NULL, NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('3', 'IGS-2026-0003', 'Mustafa', 'Raza', 'Male', '2018-01-05', '2026-03-05', '6', 'Suspended', 'Raza Ali', '03129998877', NULL, 'Plot 115, Sector 11-A, North Karachi', '2026-07-10 12:16:49', '2026-07-10 12:16:49', 'School', NULL, NULL, NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('4', 'IGS-2026-0004', 'Waqas', 'Al', 'Male', '2000-12-12', '2026-07-10', '3', 'Active', 'Waqas Al', '03066544806', 'waqas760000@gmail.com', 'Colloge road Township lahore', '2026-07-10 12:28:41', '2026-07-10 12:28:41', 'School', NULL, NULL, NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('5', 'IGS-AD-2026-0005', 'Mubeen', 'Jutt', 'Male', '2000-12-12', '2026-07-10', '10', 'Active', 'Waqas Al', '03066544806', '', 'Lajore', '2026-07-10 18:14:18', '2026-07-18 08:01:20', 'School', NULL, NULL, NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('7', 'ADM-2026-0006', 'Waqas', 'Al', 'Female', '2024-02-10', '2026-07-11', '3', 'Active', 'Waqas Al', '03066544806', '', 'Colloge road Township lahore', '2026-07-11 18:52:30', '2026-07-11 18:52:30', 'School', NULL, NULL, NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('8', 'IGS-2026-0005', 'amir', 'Al', 'Male', '2022-06-24', '2026-07-11', '5', 'Active', 'Ali', '03066544806', '', 'Colloge road Township lahore', '2026-07-11 21:16:11', '2026-07-11 21:16:11', 'Academy', 'Class 1', 'B', NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('9', 'ADM-2026-0009', 'Sajid', 'Ali', 'Male', '2015-07-18', '2026-07-12', '12', 'Active', 'Sabir', '03144273870', '', 'Thokar', '2026-07-12 06:28:33', '2026-07-12 06:28:33', 'Academy', 'Class 6', 'A', NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('10', 'IGS-2026-0011', 'Abudul', 'Razzaq', 'Male', '2006-12-12', '2026-07-16', '13', 'Active', 'Muhammad yasin', '03014586572', '', 'Colloge road Township lahore', '2026-07-16 18:44:00', '2026-07-16 18:44:00', 'School', 'Class 9', 'A', NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('11', 'IGS-2026-0013', 'Ali', 'Ahmed', 'Male', '2009-12-12', '2026-07-19', '15', 'Active', 'Ashiq', '03066544806', '', 'Main Campus, Lahore, Pakistan', '2026-07-19 15:20:07', '2026-07-19 15:20:07', 'School', 'Class 8', 'A', NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('12', 'IGS-2026-0012', 'Amir', 'khan', 'Male', '2010-12-12', '2026-07-26', '16', 'Active', 'Jawad Ahmed', '03076544999', '', 'Main Campus, Lahore, Pakistan', '2026-07-26 10:26:24', '2026-07-26 10:26:24', 'School', 'Class 7', 'A', NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('13', 'IGS-2026-0016', 'Jawad', 'Ali', 'Male', '2010-10-12', '2026-09-11', '16', 'Active', 'Jawad Ahmed', '03066544806', '', 'Main Campus, Lahore, Pakistan', '2026-09-11 09:59:58', '2026-09-11 09:59:58', 'School', 'Class 7', 'A', NULL, NULL);


CREATE TABLE `subjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subject_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `class_id` int NOT NULL,
  `total_marks` int NOT NULL DEFAULT '100',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `academic_type` enum('School','Academy') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'School',
  `passing_marks` int NOT NULL DEFAULT '40',
  `teacher_id` int DEFAULT NULL,
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_subject_class` (`subject_code`,`class_id`),
  KEY `class_id` (`class_id`),
  KEY `fk_subject_teacher` (`teacher_id`),
  CONSTRAINT `fk_subject_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`, `academic_type`, `passing_marks`, `teacher_id`, `status`) VALUES ('1', 'English', 'ENG', '4', '100', '2026-07-10 12:16:49', 'School', '40', NULL, 'Active');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`, `academic_type`, `passing_marks`, `teacher_id`, `status`) VALUES ('2', 'Urdu', 'URD', '4', '100', '2026-07-10 12:16:49', 'School', '40', NULL, 'Active');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`, `academic_type`, `passing_marks`, `teacher_id`, `status`) VALUES ('3', 'Mathematics', 'MATH', '4', '100', '2026-07-10 12:16:49', 'School', '40', NULL, 'Active');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`, `academic_type`, `passing_marks`, `teacher_id`, `status`) VALUES ('4', 'Science', 'SCI', '4', '100', '2026-07-10 12:16:49', 'School', '40', NULL, 'Active');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`, `academic_type`, `passing_marks`, `teacher_id`, `status`) VALUES ('5', 'Islamiat', 'ISL', '4', '100', '2026-07-10 12:16:49', 'School', '40', NULL, 'Active');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`, `academic_type`, `passing_marks`, `teacher_id`, `status`) VALUES ('6', 'Social Studies', 'SST', '4', '100', '2026-07-10 12:16:49', 'School', '40', NULL, 'Active');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`, `academic_type`, `passing_marks`, `teacher_id`, `status`) VALUES ('7', 'English', 'ENG', '3', '100', '2026-07-10 12:16:49', 'School', '40', NULL, 'Active');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`, `academic_type`, `passing_marks`, `teacher_id`, `status`) VALUES ('8', 'Urdu', 'URD', '3', '100', '2026-07-10 12:16:49', 'School', '40', NULL, 'Active');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`, `academic_type`, `passing_marks`, `teacher_id`, `status`) VALUES ('9', 'Mathematics', 'MATH', '3', '100', '2026-07-10 12:16:49', 'School', '40', NULL, 'Active');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`, `academic_type`, `passing_marks`, `teacher_id`, `status`) VALUES ('10', 'Science', 'SCI', '3', '100', '2026-07-10 12:16:49', 'School', '40', NULL, 'Active');


CREATE TABLE `user_remember_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `selector` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  UNIQUE KEY `selector` (`selector`),
  KEY `user_id` (`user_id`),
  KEY `idx_tokens_selector` (`selector`),
  KEY `idx_tokens_expiry` (`expires_at`),
  CONSTRAINT `user_remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_remember_tokens` (`id`, `user_id`, `token_hash`, `selector`, `expires_at`, `created_at`) VALUES ('1', '4', '6409e0fd948f493e2e54194599b0f1e5b43557db1fbd4e45a469997e18efe1ad', '7de04c0bb065061fabc5b5dd', '2026-08-11 06:19:56', '2026-07-12 06:19:56');
INSERT INTO `user_remember_tokens` (`id`, `user_id`, `token_hash`, `selector`, `expires_at`, `created_at`) VALUES ('4', '4', '1c522ee2bdaf750979ec9d579130f9f64180c9c79f354a6bf83dcd88464e4ea1', '4b74f4ae09ce95dc4fc81f07', '2026-08-25 10:15:05', '2026-07-26 10:15:05');


CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_active` (`is_active`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `mobile_no`, `profile_photo`, `password_hash`, `role_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES ('1', 'saeed', 'Super Admin User', 'saeed@indus.edu.pk', NULL, NULL, '$2y$10$BsWNxIp/ng63.STTmWus5eLGOUoTfKAKOy4F3A2Tlm39M6dOgUiQ.', '1', '1', NULL, '2026-07-10 12:16:49', '2026-07-12 13:38:23');
INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `mobile_no`, `profile_photo`, `password_hash`, `role_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES ('2', 'admin', 'School Principal', 'admin@indus.edu.pk', NULL, NULL, '$2y$10$pdahsja5A8/TDc1u19URLeX8arBzfM9ZB5ZrFx3sdYKizmNYBucTq', '2', '1', NULL, '2026-07-10 12:16:49', '2026-07-18 08:06:06');
INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `mobile_no`, `profile_photo`, `password_hash`, `role_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES ('3', 'cashier', 'Accounts Officer', 'cashier@indus.edu.pk', NULL, NULL, '$2y$10$OoJw9i4n9h/RHsCLIUr2b.mBrTjnQyjIl9ktLMAJlrhkY4NkQ39Uq', '3', '1', NULL, '2026-07-10 12:16:49', '2026-07-12 13:38:23');
INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `mobile_no`, `profile_photo`, `password_hash`, `role_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES ('4', 'waqas7600', NULL, 'waqasaliwaqas7600@gmail.com', NULL, NULL, '$2y$10$ygmCmkXhjW6CYQXaHltR0.W3DSImRRf8t3fpDcptXQgkhRyAM6.hq', '1', '1', '2026-09-11 11:22:21', '2026-07-10 12:24:38', '2026-09-11 11:22:21');

SET FOREIGN_KEY_CHECKS=1;
