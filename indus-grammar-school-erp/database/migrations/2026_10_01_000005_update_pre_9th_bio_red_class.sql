-- Migration: Update 21 Students to Pre 9th Bio Red
-- Created At: 2026-10-01

-- 1. Ensure Class 'Pre 9th Bio' (Section 'Red') exists
INSERT INTO `classes` (`class_name`, `section`)
SELECT 'Pre 9th Bio', 'Red'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `classes` WHERE `class_name` = 'Pre 9th Bio' AND `section` = 'Red'
);

-- 2. Update Students to Pre 9th Bio Red
UPDATE `students` SET 
    `class_id` = (SELECT id FROM classes WHERE `class_name` = 'Pre 9th Bio' AND `section` = 'Red' LIMIT 1)
WHERE `id` IN (338,341,342,346,350,351,368,369,552,596,631,632,661,720,877,906,1213,1314,1335,1462,1504)
   OR `admission_no` IN ('AAS-338','AAS-341','AAS-342','AAS-346','AAS-350','AAS-351','AAS-368','AAS-369','AAS552','AAS596','AAS631','AAS632','AAS661','AAS720','AAS877','AAS906','IGS1213','IGS1314','IGS1335','IGS1462','IGS1504');

-- 3. Update Student Registration Details
UPDATE `student_registration_details` SET 
    `school_class` = 'Pre 9th Bio',
    `school_section` = 'Red'
WHERE `student_id` IN (338,341,342,346,350,351,368,369,552,596,631,632,661,720,877,906,1213,1314,1335,1462,1504);

