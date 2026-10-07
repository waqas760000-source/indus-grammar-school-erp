-- Migration: Seed 21 9th Biology Red Students & Class
-- Created At: 2026-10-01

-- 1. Ensure Class 'Pre 9th Bio' (Section 'Red') exists
INSERT INTO `classes` (`class_name`, `section`)
SELECT 'Pre 9th Bio', 'Red'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `classes` WHERE (`class_name` LIKE '%Pre 9th%Bio%' OR `class_name` LIKE '%9th%Bio%') AND (`section` = 'Red' OR `section` = 'red')
);

-- 2. Insert 9th Bio Red Students Dynamically
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 338, 'AAS-338', 'ALIHA', 'SAJID', 'Female', '2011-12-23', '2024-03-27', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'SAJID ALI', '03440459724', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 338 OR `admission_no` = 'AAS-338');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 341, 'AAS-341', 'AROOJ', 'FATIMA', 'Female', '2012-01-02', '2023-03-11', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'MUZZAFAR ABBAS', '03007546524', 'THOKAR NIAZ BAIGLHR.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 341 OR `admission_no` = 'AAS-341');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 342, 'AAS-342', 'BATOOL', 'FATIMA', 'Female', '2012-01-18', '2023-03-03', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'AKBAR ALI', '03014224801', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 342 OR `admission_no` = 'AAS-342');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 346, 'AAS-346', 'HALEEMA', 'SADIA', 'Female', '2013-06-30', '2024-02-15', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'MUHAMMAD TARIQ ISMAEL', '03064224191', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 346 OR `admission_no` = 'AAS-346');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 350, 'AAS-350', 'KINZA', 'NAVEED', 'Female', '2011-12-12', '2023-03-11', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'NAVEED AHMAD', '03466940781', 'THOKAR NIAZ BAIG LHR.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 350 OR `admission_no` = 'AAS-350');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 351, 'AAS-351', 'MAHNOOR', '', 'Female', '2011-07-27', '2024-03-27', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'KHUDA BUKHSH', '03084767918', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 351 OR `admission_no` = 'AAS-351');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 368, 'AAS-368', 'RIMSHA', 'SHAKEEL', 'Female', '2011-05-16', '2024-03-25', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'SHAKEELHASSAN KHAN', '03215557664', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 368 OR `admission_no` = 'AAS-368');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 369, 'AAS-369', 'RUBAB', 'ZAHRA', 'Female', '2011-01-10', '2024-03-26', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'NADEEM RAZA SHAD', '03097275279', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 369 OR `admission_no` = 'AAS-369');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 552, 'AAS552', 'AFSHEEN', 'ZAHRA', 'Female', '2011-05-07', '2024-05-07', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'BISHARAT ALI', '03221545054', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 552 OR `admission_no` = 'AAS552');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 596, 'AAS596', 'RAFIA', '', 'Female', '2024-08-16', '2024-08-16', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'ZIA-UL-HAQ', '03027242032', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 596 OR `admission_no` = 'AAS596');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 631, 'AAS631', 'KIRAN', 'FATIMA', 'Female', '2024-09-02', '2024-09-02', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'MUHAMMAD AKRAM', '03045240796', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 631 OR `admission_no` = 'AAS631');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 632, 'AAS632', 'IRAM', 'FATIMA', 'Female', '2024-09-02', '2024-09-02', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'MUHAMMAD AKRAM', '03045240796', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 632 OR `admission_no` = 'AAS632');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 661, 'AAS661', 'MEERAB', 'SHOAIB', 'Female', '2014-09-01', '2024-09-09', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'MUHAMMAD SHOAIB', '03334495772', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 661 OR `admission_no` = 'AAS661');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 720, 'AAS720', 'SUMMAYYA', 'TUFAIL', 'Female', '2012-11-06', '2024-11-02', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'MUHMMAD TUFAIL', '03007579728', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 720 OR `admission_no` = 'AAS720');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 877, 'AAS877', 'ZAINAB', 'JAHANGIR', 'Female', '2016-01-01', '2025-03-05', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'JAHANGIR ABBAS', '03034272786', 'THOKAR NIAZ BAIG LHR.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 877 OR `admission_no` = 'AAS877');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 906, 'AAS906', 'BISMA', 'AFZAL', 'Female', '2011-08-01', '2025-03-08', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'MUHAMMAD AFZAL', '03034699137', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 906 OR `admission_no` = 'AAS906');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 1213, 'IGS1213', 'AYESHA', 'NADEEM', 'Female', '2008-10-02', '2025-09-01', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'NADEEM HUSSAIN', '03220424498', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 1213 OR `admission_no` = 'IGS1213');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 1314, 'IGS1314', 'ZAINAB', 'IMDAD', 'Female', '2012-11-12', '2025-11-01', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'IMDAD ALI', '03194994951', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 1314 OR `admission_no` = 'IGS1314');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 1335, 'IGS1335', 'MUNIZA', 'IBRAR', 'Female', '2015-12-15', '2025-12-01', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'MUHAMMAD IBRAR', '03126956758', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 1335 OR `admission_no` = 'IGS1335');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 1462, 'IGS1462', 'RIDA', 'FATIMA', 'Female', '2009-01-02', '2026-04-03', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'MUHAMMAD YOUSAF', '03004257915', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 1462 OR `admission_no` = 'IGS1462');

INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `address`)
SELECT 1504, 'IGS1504', 'AYESHA', 'BILAL', 'Female', '2012-12-27', '2026-04-11', (SELECT id FROM classes WHERE (class_name LIKE '%Pre 9th%Bio%' OR class_name LIKE '%9th%Bio%') AND (section = 'Red' OR section = 'red') LIMIT 1), 'Active', 'MUHAMMAD BILAL', '03184788442', 'THOKAR NIAZ BAIG LHR' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `students` WHERE `id` = 1504 OR `admission_no` = 'IGS1504');

-- 3. Insert Student Registration Details
INSERT INTO `student_registration_details` (`student_id`, `roll_no`, `admission_date`, `academic_session`, `campus`, `student_mobile`, `father_name`, `father_cnic`, `father_mobile`, `guardian_relationship`, `guardian_cnic`, `guardian_address`, `current_address`, `permanent_address`, `academic_type`, `school_class`, `school_section`)
SELECT s.id, '0', s.enrollment_date, '2025-2026', 'Main Campus', s.guardian_phone, s.guardian_name, NULL, s.guardian_phone, 'Father', NULL, s.address, s.address, s.address, 'School', 'Pre 9th Bio', 'Red'
FROM `students` s 
JOIN `classes` c ON s.class_id = c.id
WHERE (c.class_name LIKE '%Pre 9th%Bio%' OR c.class_name LIKE '%9th%Bio%') AND (c.section = 'Red' OR c.section = 'red')
  AND NOT EXISTS (SELECT 1 FROM `student_registration_details` WHERE `student_id` = s.id);
