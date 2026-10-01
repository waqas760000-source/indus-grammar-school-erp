-- Migration: Seed 9th Computer Science Green Students & Classes
-- Created At: 2026-10-01

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
    `address`, `academic_type`, `school_class`, `school_section`
)
SELECT 
    'IGS1254', '0', 'ADIL', 'MUNEER', 'Male', '2025-10-16', '2025-10-16',
    COALESCE((SELECT id FROM classes WHERE (`class_name` LIKE '%Pre 9th%Comp%' OR `class_name` LIKE '%9th%Com%') AND `section` = 'Green' LIMIT 1), 1),
    'Active', 'MUNEER AHMAD', '03477073016', '03477073016', 'THOKAR NIAZ BAIG LHR', 'School', 'Pre 9th Comp', 'Green'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `admission_no` = 'IGS1254' OR `first_name` = 'ADIL');

INSERT INTO `students` (
    `admission_no`, `roll_no`, `first_name`, `last_name`, `gender`, `date_of_birth`,
    `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_cnic`, `guardian_phone`,
    `address`, `academic_type`, `school_class`, `school_section`
)
SELECT 
    'IGS1303', '0', 'AHTISHAM', 'HASSAN', 'Male', '2009-10-25', '2025-10-25',
    COALESCE((SELECT id FROM classes WHERE (`class_name` LIKE '%Pre 9th%Comp%' OR `class_name` LIKE '%9th%Com%') AND `section` = 'Green' LIMIT 1), 1),
    'Active', 'HAFIZ GHULAM HASSAN', '03004313506', '03004313506', 'MAIN BAZAR THOKAR NIAZ BAIG LHR', 'School', 'Pre 9th Comp', 'Green'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `admission_no` = 'IGS1303' OR `first_name` = 'AHTISHAM');

INSERT INTO `students` (
    `admission_no`, `roll_no`, `first_name`, `last_name`, `gender`, `date_of_birth`,
    `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_cnic`, `guardian_phone`,
    `address`, `academic_type`, `school_class`, `school_section`
)
SELECT 
    'IGS1304', '0', 'ALI', 'HAIDER', 'Male', '2010-05-12', '2025-10-26',
    COALESCE((SELECT id FROM classes WHERE (`class_name` LIKE '%Pre 9th%Comp%' OR `class_name` LIKE '%9th%Com%') AND `section` = 'Green' LIMIT 1), 1),
    'Active', 'GHULAM HAIDER', '03214567890', '03214567890', 'THOKAR NIAZ BAIG LHR', 'School', 'Pre 9th Comp', 'Green'
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
