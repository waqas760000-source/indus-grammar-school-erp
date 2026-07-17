-- Indus Grammar School ERP SQL Backup
-- Generated: 2026-07-12 13:26:12
-- Database: indus_grammar_school

SET FOREIGN_KEY_CHECKS=0;



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
  KEY `marked_by` (`marked_by`),
  KEY `idx_attendance_class_date` (`class_id`,`date`),
  KEY `idx_attendance_status` (`status`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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


CREATE TABLE `classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `section` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_class_section` (`class_name`,`section`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `daily_diaries` (`id`, `class_id`, `subject_id`, `teacher_id`, `diary_date`, `title`, `description`, `attachment_path`, `is_published`, `created_at`, `updated_at`, `academic_type`, `class`, `section`, `subject`, `diary_type`, `status`, `attachment`, `created_by`) VALUES ('1', '6', '7', '4', '2026-07-10', 'yu', 'jhg', '', '1', '2026-07-10 18:07:53', '2026-07-10 18:07:53', 'School', NULL, NULL, NULL, 'Homework', 'Active', NULL, NULL);
INSERT INTO `daily_diaries` (`id`, `class_id`, `subject_id`, `teacher_id`, `diary_date`, `title`, `description`, `attachment_path`, `is_published`, `created_at`, `updated_at`, `academic_type`, `class`, `section`, `subject`, `diary_type`, `status`, `attachment`, `created_by`) VALUES ('2', '11', NULL, NULL, '2026-07-11', 'fcgjhbkjlk;l', '<p>fdgfhgjkhjll</p>', NULL, '1', '2026-07-11 21:43:54', '2026-07-11 21:43:54', 'Academy', 'Class 3', 'B', 'math', 'Classwork', 'Active', NULL, '4');
INSERT INTO `daily_diaries` (`id`, `class_id`, `subject_id`, `teacher_id`, `diary_date`, `title`, `description`, `attachment_path`, `is_published`, `created_at`, `updated_at`, `academic_type`, `class`, `section`, `subject`, `diary_type`, `status`, `attachment`, `created_by`) VALUES ('3', '6', NULL, NULL, '2026-07-12', 'School work', '<h2>Do on time&nbsp;</h2>', NULL, '1', '2026-07-12 06:30:57', '2026-07-12 06:30:57', 'Academy', 'Class 2', 'A', 'English', 'Homework', 'Active', NULL, '4');


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


CREATE TABLE `expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `expense_date` date NOT NULL,
  `paid_to` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_reference` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `idx_expense_date` (`expense_date`),
  KEY `idx_expense_category` (`category`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `expenses` (`id`, `category`, `description`, `amount`, `expense_date`, `paid_to`, `receipt_reference`, `recorded_by`, `created_at`) VALUES ('1', 'Utilities', 'Electricity bill for June 2026', '15000.00', '2026-07-02', 'K-Electric', 'KE-2026-JUN', '3', '2026-07-10 12:16:49');
INSERT INTO `expenses` (`id`, `category`, `description`, `amount`, `expense_date`, `paid_to`, `receipt_reference`, `recorded_by`, `created_at`) VALUES ('2', 'Stationery', 'Board markers and chart papers', '3500.00', '2026-07-03', 'National Stationery', NULL, '3', '2026-07-10 12:16:49');
INSERT INTO `expenses` (`id`, `category`, `description`, `amount`, `expense_date`, `paid_to`, `receipt_reference`, `recorded_by`, `created_at`) VALUES ('3', 'Salaries', 'Salary paid to Ali Raza for 7/2026', '39000.00', '2026-07-12', 'Ali Raza', 'SAL-9', '4', '2026-07-12 12:44:26');


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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('1', '1', 'July 2026', '2026-2027', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', '0.00', '0.00', '10600.00', '1000.00', 'Partial', '2026-07-15', '2026-07-12 11:51:45', '2026-07-12 13:20:57');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('2', '2', 'June 2026', '2026-2027', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', '0.00', '0.00', '10600.00', '10600.00', 'Paid', '2026-07-15', '2026-07-12 11:52:05', '2026-07-12 13:01:20');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('3', '4', 'June 2026', '2026-2027', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', '0.00', '0.00', '10600.00', '10600.00', 'Paid', '2026-07-15', '2026-07-12 11:52:05', '2026-07-12 12:38:16');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('4', '7', 'June 2026', '2026-2027', '2500.00', '3500.00', '500.00', '400.00', '1000.00', '1500.00', '1000.00', '200.00', '0.00', '0.00', '10600.00', '10600.00', 'Paid', '2026-07-15', '2026-07-12 11:52:05', '2026-07-12 12:33:13');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('5', '2', 'July 2026', '2026-2027', '0.00', '3500.00', '500.00', '400.00', '1000.00', '0.00', '0.00', '200.00', '0.00', '0.00', '5600.00', '5600.00', 'Paid', '2026-07-15', '2026-07-12 12:03:58', '2026-07-12 12:34:26');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('6', '4', 'July 2026', '2026-2027', '0.00', '3500.00', '500.00', '400.00', '1000.00', '0.00', '0.00', '200.00', '0.00', '0.00', '5600.00', '0.00', 'Pending', '2026-07-15', '2026-07-12 12:03:58', '2026-07-12 12:03:58');
INSERT INTO `fee_ledger` (`id`, `student_id`, `month`, `academic_year`, `admission_fee`, `tuition_fee`, `computer_fee`, `exam_fee`, `transport_fee`, `annual_charges`, `security_deposit`, `other_charges`, `fine_amount`, `discount_amount`, `total_payable`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES ('7', '7', 'July 2026', '2026-2027', '0.00', '3500.00', '500.00', '400.00', '1000.00', '0.00', '0.00', '200.00', '0.00', '0.00', '5600.00', '5600.00', 'Paid', '2026-07-15', '2026-07-12 12:03:58', '2026-07-12 12:32:40');


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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('1', '7', '7', '5600.00', '2026-07-12', 'Cash', '', '', '2', '4', '2026-07-12 12:32:40');
INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('2', '4', '7', '10600.00', '2026-07-12', 'Cash', '', '', '2', '4', '2026-07-12 12:33:13');
INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('3', '5', '2', '5600.00', '2026-07-12', 'Cash', '', '', '2', '4', '2026-07-12 12:34:26');
INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('4', '1', '1', '1000.00', '2026-07-12', 'Cash', 'TEST-1234', 'Unit test automatic cashier transaction', '2', NULL, '2026-07-12 12:36:35');
INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('5', '3', '4', '10600.00', '2026-07-12', 'Cash', '', '', '2', '4', '2026-07-12 12:38:16');
INSERT INTO `fee_payments` (`id`, `ledger_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `reference_number`, `remarks`, `cash_register_id`, `collected_by`, `created_at`) VALUES ('6', '2', '2', '10600.00', '2026-07-12', 'Cash', '', '', '2', '4', '2026-07-12 13:01:20');


CREATE TABLE `fee_receipts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(50) NOT NULL,
  `payment_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_no` (`receipt_no`),
  KEY `payment_id` (`payment_id`),
  CONSTRAINT `fee_receipts_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `fee_payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('1', 'REC-20260712-00001', '1', '2026-07-12 12:32:40');
INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('2', 'REC-20260712-00002', '2', '2026-07-12 12:33:13');
INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('3', 'REC-20260712-00003', '3', '2026-07-12 12:34:26');
INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('4', 'REC-20260712-00004', '4', '2026-07-12 12:36:35');
INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('5', 'REC-20260712-00005', '5', '2026-07-12 12:38:16');
INSERT INTO `fee_receipts` (`id`, `receipt_no`, `payment_id`, `created_at`) VALUES ('6', 'REC-20260712-00006', '6', '2026-07-12 13:01:20');


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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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



CREATE TABLE `permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('1', 'dashboard_view', 'Dashboard', 'Ability to view dashboard metrics', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('2', 'student_view', 'Student Management', 'Ability to view students directory', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('3', 'student_create', 'Student Management', 'Ability to add new students', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('4', 'student_edit', 'Student Management', 'Ability to edit student records', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('5', 'student_delete', 'Student Management', 'Ability to archive/delete student records', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('6', 'admission_view', 'Admission', 'Ability to view admissions', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('7', 'admission_create', 'Admission', 'Ability to process new applications', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('8', 'attendance_view', 'Attendance', 'Ability to view student and staff attendance', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('9', 'attendance_mark', 'Attendance', 'Ability to mark daily attendance', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('10', 'fee_view', 'Fee & Accounts', 'Ability to view fee structures and payment logs', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('11', 'fee_collect', 'Fee & Accounts', 'Ability to collect fees and record payments', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('12', 'fee_invoice', 'Fee & Accounts', 'Ability to generate fee invoices', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('13', 'exam_view', 'Examination', 'Ability to view exam listings and results', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('14', 'exam_marks_entry', 'Examination', 'Ability to enter and edit exam marks', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('15', 'staff_view', 'HR / Staff', 'Ability to view school staff profiles', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('16', 'staff_manage', 'HR / Staff', 'Ability to add, edit or terminate staff records', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('17', 'communication_send', 'Communication', 'Ability to send SMS or Email notifications', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('18', 'report_view', 'Reports', 'Ability to generate and download academic and financial reports', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('19', 'cash_view', 'Cash', 'Ability to view cash counters and flow logs', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('20', 'cash_transaction', 'Cash', 'Ability to record office cash transactions', '2026-07-10 12:16:49');
INSERT INTO `permissions` (`id`, `code`, `module`, `description`, `created_at`) VALUES ('21', 'system_settings', 'Administration', 'Ability to update ERP configuration settings', '2026-07-10 12:16:49');


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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `name`, `code`, `description`, `created_at`, `updated_at`) VALUES ('1', 'Super Admin', 'super_admin', 'School Owner (Saeed) with unrestricted administrative permissions', '2026-07-10 12:16:49', '2026-07-10 12:16:49');
INSERT INTO `roles` (`id`, `name`, `code`, `description`, `created_at`, `updated_at`) VALUES ('2', 'School Admin', 'school_admin', 'Principal or Admin overseeing general school operations', '2026-07-10 12:16:49', '2026-07-10 12:16:49');
INSERT INTO `roles` (`id`, `name`, `code`, `description`, `created_at`, `updated_at`) VALUES ('3', 'Accountant / Cashier', 'accountant', 'Financial operations manager handling collections, cash, and bookkeeping', '2026-07-10 12:16:49', '2026-07-10 12:16:49');


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
  `user_id` int NOT NULL,
  `date` date NOT NULL,
  `status` enum('Present','Absent','Late','Leave') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Present',
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_staff_date` (`user_id`,`date`),
  KEY `idx_staff_att_date` (`date`),
  CONSTRAINT `staff_attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `staff_attendance` (`id`, `user_id`, `date`, `status`, `check_in_time`, `check_out_time`, `remarks`, `created_at`) VALUES ('1', '1', '2026-07-09', 'Present', '07:45:00', '14:30:00', NULL, '2026-07-10 12:16:49');
INSERT INTO `staff_attendance` (`id`, `user_id`, `date`, `status`, `check_in_time`, `check_out_time`, `remarks`, `created_at`) VALUES ('2', '2', '2026-07-09', 'Present', '07:50:00', '14:15:00', NULL, '2026-07-10 12:16:49');
INSERT INTO `staff_attendance` (`id`, `user_id`, `date`, `status`, `check_in_time`, `check_out_time`, `remarks`, `created_at`) VALUES ('3', '3', '2026-07-09', 'Late', '08:25:00', '14:00:00', 'Traffic delay', '2026-07-10 12:16:49');
INSERT INTO `staff_attendance` (`id`, `user_id`, `date`, `status`, `check_in_time`, `check_out_time`, `remarks`, `created_at`) VALUES ('4', '1', '2026-07-12', 'Absent', NULL, NULL, NULL, '2026-07-12 12:43:44');


CREATE TABLE `staff_salaries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `month` tinyint NOT NULL,
  `year` smallint NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL DEFAULT '0.00',
  `allowances` decimal(10,2) NOT NULL DEFAULT '0.00',
  `deductions` decimal(10,2) NOT NULL DEFAULT '0.00',
  `net_salary` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_date` date DEFAULT NULL,
  `payment_status` enum('Pending','Paid') COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_salary_unique` (`staff_id`,`month`,`year`),
  CONSTRAINT `staff_salaries_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `staff_salaries` (`id`, `staff_id`, `month`, `year`, `basic_salary`, `allowances`, `deductions`, `net_salary`, `payment_date`, `payment_status`, `created_at`) VALUES ('1', '2', '6', '2026', '80000.00', '5000.00', '2000.00', '83000.00', '2026-06-30', 'Paid', '2026-07-10 12:16:49');
INSERT INTO `staff_salaries` (`id`, `staff_id`, `month`, `year`, `basic_salary`, `allowances`, `deductions`, `net_salary`, `payment_date`, `payment_status`, `created_at`) VALUES ('2', '3', '6', '2026', '45000.00', '3000.00', '1000.00', '47000.00', '2026-06-30', 'Paid', '2026-07-10 12:16:49');
INSERT INTO `staff_salaries` (`id`, `staff_id`, `month`, `year`, `basic_salary`, `allowances`, `deductions`, `net_salary`, `payment_date`, `payment_status`, `created_at`) VALUES ('3', '4', '6', '2026', '40000.00', '2000.00', '1000.00', '41000.00', '2026-06-30', 'Paid', '2026-07-10 12:16:49');
INSERT INTO `staff_salaries` (`id`, `staff_id`, `month`, `year`, `basic_salary`, `allowances`, `deductions`, `net_salary`, `payment_date`, `payment_status`, `created_at`) VALUES ('4', '5', '6', '2026', '38000.00', '2000.00', '1000.00', '39000.00', '2026-06-30', 'Paid', '2026-07-10 12:16:49');
INSERT INTO `staff_salaries` (`id`, `staff_id`, `month`, `year`, `basic_salary`, `allowances`, `deductions`, `net_salary`, `payment_date`, `payment_status`, `created_at`) VALUES ('5', '6', '6', '2026', '42000.00', '2500.00', '1000.00', '43500.00', '2026-06-30', 'Paid', '2026-07-10 12:16:49');
INSERT INTO `staff_salaries` (`id`, `staff_id`, `month`, `year`, `basic_salary`, `allowances`, `deductions`, `net_salary`, `payment_date`, `payment_status`, `created_at`) VALUES ('6', '2', '7', '2026', '80000.00', '5000.00', '2000.00', '83000.00', '2026-07-12', 'Paid', '2026-07-10 12:16:49');
INSERT INTO `staff_salaries` (`id`, `staff_id`, `month`, `year`, `basic_salary`, `allowances`, `deductions`, `net_salary`, `payment_date`, `payment_status`, `created_at`) VALUES ('7', '3', '7', '2026', '45000.00', '3000.00', '1000.00', '47000.00', '2026-07-12', 'Paid', '2026-07-10 12:16:49');
INSERT INTO `staff_salaries` (`id`, `staff_id`, `month`, `year`, `basic_salary`, `allowances`, `deductions`, `net_salary`, `payment_date`, `payment_status`, `created_at`) VALUES ('8', '4', '7', '2026', '40000.00', '2000.00', '1000.00', '41000.00', NULL, 'Pending', '2026-07-10 12:16:49');
INSERT INTO `staff_salaries` (`id`, `staff_id`, `month`, `year`, `basic_salary`, `allowances`, `deductions`, `net_salary`, `payment_date`, `payment_status`, `created_at`) VALUES ('9', '5', '7', '2026', '38000.00', '2000.00', '1000.00', '39000.00', '2026-07-12', 'Paid', '2026-07-10 12:16:49');
INSERT INTO `staff_salaries` (`id`, `staff_id`, `month`, `year`, `basic_salary`, `allowances`, `deductions`, `net_salary`, `payment_date`, `payment_status`, `created_at`) VALUES ('10', '6', '7', '2026', '42000.00', '2500.00', '1000.00', '43500.00', NULL, 'Pending', '2026-07-10 12:16:49');


CREATE TABLE `student_complaints` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `class_id` int DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('2', '2', '23', '0.00', '0.00', '', 'Active', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('3', '4', '23', '0.00', '0.00', '', 'Active', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('4', '5', '13', '0.00', '0.00', '', 'Active', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('5', '7', '23', '0.00', '0.00', '', 'Active', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('6', '8', '4', '0.00', '0.00', '', 'Active', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('7', '9', '18', '0.00', '0.00', '', 'Active', '2026-07-12 11:34:18', '2026-07-12 11:34:18');
INSERT INTO `student_fee_assignments` (`id`, `student_id`, `fee_structure_id`, `discount_percentage`, `discount_flat`, `discount_reason`, `status`, `created_at`, `updated_at`) VALUES ('8', '1', '25', '0.00', '0.00', '', 'Active', '2026-07-12 13:15:56', '2026-07-12 13:15:56');


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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('1', 'IGS-2026-0001', 'Zain', 'Khan', 'Male', '2019-04-12', '2026-03-01', '4', 'Active', 'Asif Khan', '03215551234', 'asif@gmail.com', 'Flat C-4, Gulshan-e-Iqbal, Karachi', '2026-07-10 12:16:49', '2026-07-10 12:16:49', 'School', NULL, NULL, NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('2', 'IGS-2026-0002', 'Ayesha', 'Ahmed', 'Female', '2020-09-22', '2026-03-01', '3', 'Active', 'Farhan Ahmed', '03334445566', 'farhan@outlook.com', 'House 42, Block 13-D, Gulshan, Karachi', '2026-07-10 12:16:49', '2026-07-10 12:16:49', 'School', NULL, NULL, NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('3', 'IGS-2026-0003', 'Mustafa', 'Raza', 'Male', '2018-01-05', '2026-03-05', '6', 'Suspended', 'Raza Ali', '03129998877', NULL, 'Plot 115, Sector 11-A, North Karachi', '2026-07-10 12:16:49', '2026-07-10 12:16:49', 'School', NULL, NULL, NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('4', 'IGS-2026-0004', 'Waqas', 'Al', 'Male', '2000-12-12', '2026-07-10', '3', 'Active', 'Waqas Al', '03066544806', 'waqas760000@gmail.com', 'Colloge road Township lahore', '2026-07-10 12:28:41', '2026-07-10 12:28:41', 'School', NULL, NULL, NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('5', 'IGS-AD-2026-0005', 'Mubeen', 'Jutt', 'Male', '2000-12-12', '2026-07-10', '9', 'Active', 'Waqas Al', '03066544806', '', 'Lajore', '2026-07-10 18:14:18', '2026-07-10 18:14:18', 'School', NULL, NULL, NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('7', 'ADM-2026-0006', 'Waqas', 'Al', 'Female', '2024-02-10', '2026-07-11', '3', 'Active', 'Waqas Al', '03066544806', '', 'Colloge road Township lahore', '2026-07-11 18:52:30', '2026-07-11 18:52:30', 'School', NULL, NULL, NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('8', 'IGS-2026-0005', 'amir', 'Al', 'Male', '2022-06-24', '2026-07-11', '5', 'Active', 'Ali', '03066544806', '', 'Colloge road Township lahore', '2026-07-11 21:16:11', '2026-07-11 21:16:11', 'Academy', 'Class 1', 'B', NULL, NULL);
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `created_at`, `updated_at`, `academic_type`, `school_class`, `school_section`, `academy_program`, `academy_batch`) VALUES ('9', 'ADM-2026-0009', 'Sajid', 'Ali', 'Male', '2015-07-18', '2026-07-12', '12', 'Active', 'Sabir', '03144273870', '', 'Thokar', '2026-07-12 06:28:33', '2026-07-12 06:28:33', 'Academy', 'Class 6', 'A', NULL, NULL);


CREATE TABLE `subjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subject_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `class_id` int NOT NULL,
  `total_marks` int NOT NULL DEFAULT '100',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_subject_class` (`subject_code`,`class_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`) VALUES ('1', 'English', 'ENG', '4', '100', '2026-07-10 12:16:49');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`) VALUES ('2', 'Urdu', 'URD', '4', '100', '2026-07-10 12:16:49');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`) VALUES ('3', 'Mathematics', 'MATH', '4', '100', '2026-07-10 12:16:49');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`) VALUES ('4', 'Science', 'SCI', '4', '100', '2026-07-10 12:16:49');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`) VALUES ('5', 'Islamiat', 'ISL', '4', '100', '2026-07-10 12:16:49');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`) VALUES ('6', 'Social Studies', 'SST', '4', '100', '2026-07-10 12:16:49');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`) VALUES ('7', 'English', 'ENG', '3', '100', '2026-07-10 12:16:49');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`) VALUES ('8', 'Urdu', 'URD', '3', '100', '2026-07-10 12:16:49');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`) VALUES ('9', 'Mathematics', 'MATH', '3', '100', '2026-07-10 12:16:49');
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`, `total_marks`, `created_at`) VALUES ('10', 'Science', 'SCI', '3', '100', '2026-07-10 12:16:49');


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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_remember_tokens` (`id`, `user_id`, `token_hash`, `selector`, `expires_at`, `created_at`) VALUES ('1', '4', '6409e0fd948f493e2e54194599b0f1e5b43557db1fbd4e45a469997e18efe1ad', '7de04c0bb065061fabc5b5dd', '2026-08-11 06:19:56', '2026-07-12 06:19:56');


CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
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

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES ('1', 'saeed', 'saeed@indus.edu.pk', '$2y$10$BsWNxIp/ng63.STTmWus5eLGOUoTfKAKOy4F3A2Tlm39M6dOgUiQ.', '1', '1', NULL, '2026-07-10 12:16:49', '2026-07-10 12:16:49');
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES ('2', 'admin', 'admin@indus.edu.pk', '$2y$10$5mfy9QZwxvcNHznC4Mei5uWV7bsLD7QG9C2LirpP7bIu0O4qNp3CG', '2', '1', NULL, '2026-07-10 12:16:49', '2026-07-10 12:16:49');
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES ('3', 'cashier', 'cashier@indus.edu.pk', '$2y$10$OoJw9i4n9h/RHsCLIUr2b.mBrTjnQyjIl9ktLMAJlrhkY4NkQ39Uq', '3', '1', NULL, '2026-07-10 12:16:49', '2026-07-10 12:16:49');
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES ('4', 'waqas7600', 'waqasaliwaqas7600@gmail.com', '$2y$10$ygmCmkXhjW6CYQXaHltR0.W3DSImRRf8t3fpDcptXQgkhRyAM6.hq', '1', '1', '2026-07-12 06:24:31', '2026-07-10 12:24:38', '2026-07-12 06:24:31');

SET FOREIGN_KEY_CHECKS=1;
