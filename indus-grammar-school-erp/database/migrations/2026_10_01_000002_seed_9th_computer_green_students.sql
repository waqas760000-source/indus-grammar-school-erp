-- Migration: Seed 9th Computer Science Green Students & Classes
-- Created At: 2026-10-01

-- 0. Ensure student_registration_details table exists
CREATE TABLE IF NOT EXISTS `student_registration_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `roll_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `admission_date` date DEFAULT NULL,
  `academic_session` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '2025-2026',
  `campus` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Main Campus',
  `cnic_no` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `student_mobile` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `student_email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_cnic` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `father_mobile` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_relationship` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Father',
  `guardian_cnic` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permanent_address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fee_plan` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Regular Plan',
  `fee_admission` decimal(10,2) DEFAULT '0.00',
  `fee_monthly` decimal(10,2) DEFAULT '0.00',
  `fee_discount` decimal(10,2) DEFAULT '0.00',
  `tuition_fee` decimal(10,2) DEFAULT '0.00',
  `remarks` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doc_student_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `academic_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'School',
  `school_class` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `school_section` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_srd_student_id` (`student_id`),
  CONSTRAINT `srd_fk_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1. Ensure Class '9th Comp' (Section 'Green') exists
INSERT INTO `classes` (`class_name`, `section`)
SELECT '9th Comp', 'Green'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `classes` WHERE (`class_name` LIKE '%Pre 9th%Comp%' OR `class_name` LIKE '%9th%Com%') AND `section` = 'Green'
);

-- 2. Insert Students dynamically using existing or created Class ID
INSERT INTO `students` (
    `admission_no`, `roll_no`, `first_name`, `last_name`, `gender`, `date_of_birth`,
    `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_cnic`, `guardian_phone`,
    `address`
)
SELECT 
    'IGS1254', '0', 'ADIL', 'MUNEER', 'Male', '2025-10-16', '2025-10-16',
    COALESCE((SELECT id FROM classes WHERE (`class_name` LIKE '%Pre 9th%Comp%' OR `class_name` LIKE '%9th%Com%') AND `section` = 'Green' LIMIT 1), 1),
    'Active', 'MUNEER AHMAD', '03477073016', '03477073016', 'THOKAR NIAZ BAIG LHR'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `admission_no` = 'IGS1254' OR `first_name` = 'ADIL');

INSERT INTO `students` (
    `admission_no`, `roll_no`, `first_name`, `last_name`, `gender`, `date_of_birth`,
    `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_cnic`, `guardian_phone`,
    `address`
)
SELECT 
    'IGS1303', '0', 'AHTISHAM', 'HASSAN', 'Male', '2009-10-25', '2025-10-25',
    COALESCE((SELECT id FROM classes WHERE (`class_name` LIKE '%Pre 9th%Comp%' OR `class_name` LIKE '%9th%Com%') AND `section` = 'Green' LIMIT 1), 1),
    'Active', 'HAFIZ GHULAM HASSAN', '03004313506', '03004313506', 'MAIN BAZAR THOKAR NIAZ BAIG LHR'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `admission_no` = 'IGS1303' OR `first_name` = 'AHTISHAM');

INSERT INTO `students` (
    `admission_no`, `roll_no`, `first_name`, `last_name`, `gender`, `date_of_birth`,
    `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_cnic`, `guardian_phone`,
    `address`
)
SELECT 
    'IGS1304', '0', 'ALI', 'HAIDER', 'Male', '2010-05-12', '2025-10-26',
    COALESCE((SELECT id FROM classes WHERE (`class_name` LIKE '%Pre 9th%Comp%' OR `class_name` LIKE '%9th%Com%') AND `section` = 'Green' LIMIT 1), 1),
    'Active', 'GHULAM HAIDER', '03214567890', '03214567890', 'THOKAR NIAZ BAIG LHR'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `admission_no` = 'IGS1304' OR `first_name` = 'ALI');

-- 3. Insert Student Registration Details
INSERT INTO `student_registration_details` (
    `student_id`, `roll_no`, `admission_date`, `academic_session`, `campus`,
    `student_mobile`, `father_name`, `father_cnic`, `father_mobile`,
    `guardian_relationship`, `guardian_cnic`, `guardian_address`, `current_address`, `permanent_address`,
    `academic_type`, `school_class`, `school_section`
)
SELECT 
    s.id, '0', '2025-10-16', '2025-2026', 'Main Campus', '03477073016', 'MUNEER AHMAD', '03477073016', '03477073016', 'Father', '03477073016', 'THOKAR NIAZ BAIG LHR', 'THOKAR NIAZ BAIG LHR', 'THOKAR NIAZ BAIG LHR', 'School', 'Pre 9th Comp', 'Green'
FROM `students` s WHERE s.first_name LIKE '%ADIL%' AND NOT EXISTS (SELECT 1 FROM `student_registration_details` WHERE `student_id` = s.id);

INSERT INTO `student_registration_details` (
    `student_id`, `roll_no`, `admission_date`, `academic_session`, `campus`,
    `student_mobile`, `father_name`, `father_cnic`, `father_mobile`,
    `guardian_relationship`, `guardian_cnic`, `guardian_address`, `current_address`, `permanent_address`,
    `academic_type`, `school_class`, `school_section`
)
SELECT 
    s.id, '0', '2025-10-25', '2025-2026', 'Main Campus', '03004313506', 'HAFIZ GHULAM HASSAN', '03004313506', '03004313506', 'Father', '03004313506', 'MAIN BAZAR THOKAR NIAZ BAIG LHR', 'MAIN BAZAR THOKAR NIAZ BAIG LHR', 'MAIN BAZAR THOKAR NIAZ BAIG LHR', 'School', 'Pre 9th Comp', 'Green'
FROM `students` s WHERE s.first_name LIKE '%AHTISHAM%' AND NOT EXISTS (SELECT 1 FROM `student_registration_details` WHERE `student_id` = s.id);

INSERT INTO `student_registration_details` (
    `student_id`, `roll_no`, `admission_date`, `academic_session`, `campus`,
    `student_mobile`, `father_name`, `father_cnic`, `father_mobile`,
    `guardian_relationship`, `guardian_cnic`, `guardian_address`, `current_address`, `permanent_address`,
    `academic_type`, `school_class`, `school_section`
)
SELECT 
    s.id, '0', '2025-10-26', '2025-2026', 'Main Campus', '03214567890', 'GHULAM HAIDER', '03214567890', '03214567890', 'Father', '03214567890', 'THOKAR NIAZ BAIG LHR', 'THOKAR NIAZ BAIG LHR', 'THOKAR NIAZ BAIG LHR', 'School', 'Pre 9th Comp', 'Green'
FROM `students` s WHERE s.first_name LIKE '%ALI%' AND NOT EXISTS (SELECT 1 FROM `student_registration_details` WHERE `student_id` = s.id);

