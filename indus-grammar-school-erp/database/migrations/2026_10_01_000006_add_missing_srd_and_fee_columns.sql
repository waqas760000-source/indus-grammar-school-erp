-- Migration: Add missing columns to student_registration_details, students, and fee_settings tables
-- Created At: 2026-10-01

-- 1. Ensure student_registration_details table exists
CREATE TABLE IF NOT EXISTS `student_registration_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `roll_no` varchar(20) DEFAULT '0',
  `admission_date` date DEFAULT NULL,
  `academic_session` varchar(50) DEFAULT '2025-2026',
  `campus` varchar(100) DEFAULT 'Main Campus',
  `cnic_no` varchar(25) DEFAULT NULL,
  `student_mobile` varchar(20) DEFAULT NULL,
  `student_email` varchar(100) DEFAULT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `father_cnic` varchar(25) DEFAULT NULL,
  `father_mobile` varchar(20) DEFAULT NULL,
  `guardian_relationship` varchar(50) DEFAULT 'Father',
  `guardian_cnic` varchar(25) DEFAULT NULL,
  `guardian_address` text DEFAULT NULL,
  `current_address` text DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `fee_plan` varchar(50) DEFAULT 'Regular Plan',
  `fee_admission` decimal(10,2) DEFAULT '0.00',
  `fee_monthly` decimal(10,2) DEFAULT '0.00',
  `fee_discount` decimal(10,2) DEFAULT '0.00',
  `tuition_fee` decimal(10,2) DEFAULT '0.00',
  `remarks` text DEFAULT NULL,
  `doc_student_photo` varchar(255) DEFAULT NULL,
  `academic_type` varchar(50) DEFAULT 'School',
  `school_class` varchar(50) DEFAULT NULL,
  `school_section` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_srd_student_id` (`student_id`),
  CONSTRAINT `srd_fk_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add columns to student_registration_details if table pre-existed with older schema
ALTER TABLE `student_registration_details` ADD COLUMN `fee_plan` varchar(50) DEFAULT 'Regular Plan';
ALTER TABLE `student_registration_details` ADD COLUMN `fee_admission` decimal(10,2) DEFAULT '0.00';
ALTER TABLE `student_registration_details` ADD COLUMN `fee_monthly` decimal(10,2) DEFAULT '0.00';
ALTER TABLE `student_registration_details` ADD COLUMN `fee_discount` decimal(10,2) DEFAULT '0.00';
ALTER TABLE `student_registration_details` ADD COLUMN `tuition_fee` decimal(10,2) DEFAULT '0.00';
ALTER TABLE `student_registration_details` ADD COLUMN `remarks` text DEFAULT NULL;
ALTER TABLE `student_registration_details` ADD COLUMN `doc_student_photo` varchar(255) DEFAULT NULL;
ALTER TABLE `student_registration_details` ADD COLUMN `academic_type` varchar(50) DEFAULT 'School';
ALTER TABLE `student_registration_details` ADD COLUMN `school_class` varchar(50) DEFAULT NULL;
ALTER TABLE `student_registration_details` ADD COLUMN `school_section` varchar(20) DEFAULT NULL;

-- 3. Add tuition_fee to students table if missing
ALTER TABLE `students` ADD COLUMN `tuition_fee` decimal(10,2) DEFAULT '0.00';

-- 4. Ensure fee_settings columns exist
ALTER TABLE `fee_settings` ADD COLUMN `auto_fee_enabled` tinyint(1) NOT NULL DEFAULT '1';
ALTER TABLE `fee_settings` ADD COLUMN `auto_fee_day` int NOT NULL DEFAULT '1';
ALTER TABLE `fee_settings` ADD COLUMN `last_auto_fee_run` varchar(20) DEFAULT NULL;
