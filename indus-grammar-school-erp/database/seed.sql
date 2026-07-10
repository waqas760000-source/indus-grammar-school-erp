-- Indus Grammar School ERP - Database Seed File
-- Version 1.1.0

USE `indus_grammar_school`;

-- --------------------------------------------------------
-- Seed Roles
-- --------------------------------------------------------
INSERT INTO `roles` (`id`, `name`, `code`, `description`) VALUES
(1, 'Super Admin', 'super_admin', 'School Owner (Saeed) with unrestricted administrative permissions'),
(2, 'School Admin', 'school_admin', 'Principal or Admin overseeing general school operations'),
(3, 'Accountant / Cashier', 'accountant', 'Financial operations manager handling collections, cash, and bookkeeping')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- --------------------------------------------------------
-- Seed Permissions
-- --------------------------------------------------------
INSERT INTO `permissions` (`id`, `code`, `module`, `description`) VALUES
(1, 'dashboard_view', 'Dashboard', 'Ability to view dashboard metrics'),
(2, 'student_view', 'Student Management', 'Ability to view students directory'),
(3, 'student_create', 'Student Management', 'Ability to add new students'),
(4, 'student_edit', 'Student Management', 'Ability to edit student records'),
(5, 'student_delete', 'Student Management', 'Ability to archive/delete student records'),
(6, 'admission_view', 'Admission', 'Ability to view admissions'),
(7, 'admission_create', 'Admission', 'Ability to process new applications'),
(8, 'attendance_view', 'Attendance', 'Ability to view student and staff attendance'),
(9, 'attendance_mark', 'Attendance', 'Ability to mark daily attendance'),
(10, 'fee_view', 'Fee & Accounts', 'Ability to view fee structures and payment logs'),
(11, 'fee_collect', 'Fee & Accounts', 'Ability to collect fees and record payments'),
(12, 'fee_invoice', 'Fee & Accounts', 'Ability to generate fee invoices'),
(13, 'exam_view', 'Examination', 'Ability to view exam listings and results'),
(14, 'exam_marks_entry', 'Examination', 'Ability to enter and edit exam marks'),
(15, 'staff_view', 'HR / Staff', 'Ability to view school staff profiles'),
(16, 'staff_manage', 'HR / Staff', 'Ability to add, edit or terminate staff records'),
(17, 'communication_send', 'Communication', 'Ability to send SMS or Email notifications'),
(18, 'report_view', 'Reports', 'Ability to generate and download academic and financial reports'),
(19, 'cash_view', 'Cash', 'Ability to view cash counters and flow logs'),
(20, 'cash_transaction', 'Cash', 'Ability to record office cash transactions'),
(21, 'system_settings', 'Administration', 'Ability to update ERP configuration settings')
ON DUPLICATE KEY UPDATE `code` = VALUES(`code`), `module` = VALUES(`module`), `description` = VALUES(`description`);

-- --------------------------------------------------------
-- Seed Role Permissions Mappings
-- --------------------------------------------------------
DELETE FROM `role_permissions`;
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `id` IN (1, 2, 3, 4, 5, 6, 7, 8, 9, 13, 14, 15, 16, 17, 18);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions` WHERE `id` IN (1, 10, 11, 12, 18, 19, 20);

-- --------------------------------------------------------
-- Seed Users (Passwords: Super Admin='Password123!', School Admin='Admin123!', Accountant='Cashier123!')
-- --------------------------------------------------------
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role_id`, `is_active`) VALUES
(1, 'saeed', 'saeed@indus.edu.pk', '$2y$10$BsWNxIp/ng63.STTmWus5eLGOUoTfKAKOy4F3A2Tlm39M6dOgUiQ.', 1, 1),
(2, 'admin', 'admin@indus.edu.pk', '$2y$10$5mfy9QZwxvcNHznC4Mei5uWV7bsLD7QG9C2LirpP7bIu0O4qNp3CG', 2, 1),
(3, 'cashier', 'cashier@indus.edu.pk', '$2y$10$OoJw9i4n9h/RHsCLIUr2b.mBrTjnQyjIl9ktLMAJlrhkY4NkQ39Uq', 3, 1)
ON DUPLICATE KEY UPDATE 
  `username` = VALUES(`username`),
  `email` = VALUES(`email`),
  `password_hash` = VALUES(`password_hash`),
  `role_id` = VALUES(`role_id`),
  `is_active` = VALUES(`is_active`);

-- ========================================================
-- NEW PHASE 2 SEED DATA
-- ========================================================

-- --------------------------------------------------------
-- Seed Classes
-- --------------------------------------------------------
INSERT INTO `classes` (`id`, `class_name`, `section`) VALUES
(1, 'Playgroup', 'A'),
(2, 'Nursery', 'A'),
(3, 'Prep', 'A'),
(4, 'Class 1', 'A'),
(5, 'Class 1', 'B'),
(6, 'Class 2', 'A'),
(7, 'Class 2', 'B'),
(8, 'Class 3', 'A'),
(9, 'Class 4', 'A'),
(10, 'Class 5', 'A')
ON DUPLICATE KEY UPDATE `class_name` = VALUES(`class_name`), `section` = VALUES(`section`);

-- --------------------------------------------------------
-- Seed Students
-- --------------------------------------------------------
INSERT INTO `students` (`id`, `admission_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `enrollment_date`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`) VALUES
(1, 'IGS-2026-0001', 'Zain', 'Khan', 'Male', '2019-04-12', '2026-03-01', 4, 'Active', 'Asif Khan', '03215551234', 'asif@gmail.com', 'Flat C-4, Gulshan-e-Iqbal, Karachi'),
(2, 'IGS-2026-0002', 'Ayesha', 'Ahmed', 'Female', '2020-09-22', '2026-03-01', 3, 'Active', 'Farhan Ahmed', '03334445566', 'farhan@outlook.com', 'House 42, Block 13-D, Gulshan, Karachi'),
(3, 'IGS-2026-0003', 'Mustafa', 'Raza', 'Male', '2018-01-05', '2026-03-05', 6, 'Suspended', 'Raza Ali', '03129998877', NULL, 'Plot 115, Sector 11-A, North Karachi')
ON DUPLICATE KEY UPDATE 
  `admission_no` = VALUES(`admission_no`),
  `first_name` = VALUES(`first_name`),
  `last_name` = VALUES(`last_name`),
  `gender` = VALUES(`gender`),
  `date_of_birth` = VALUES(`date_of_birth`),
  `enrollment_date` = VALUES(`enrollment_date`),
  `class_id` = VALUES(`class_id`),
  `status` = VALUES(`status`),
  `guardian_name` = VALUES(`guardian_name`),
  `guardian_phone` = VALUES(`guardian_phone`),
  `guardian_email` = VALUES(`guardian_email`),
  `address` = VALUES(`address`);

-- --------------------------------------------------------
-- Seed Admissions (Applications)
-- --------------------------------------------------------
INSERT INTO `admissions` (`id`, `application_no`, `first_name`, `last_name`, `gender`, `date_of_birth`, `class_id`, `status`, `guardian_name`, `guardian_phone`, `guardian_email`, `address`, `application_date`, `notes`) VALUES
(1, 'APP-2026-0001', 'Fatima', 'Bilal', 'Female', '2021-02-14', 2, 'Pending', 'Bilal Tariq', '03451112233', 'bilal@gmail.com', 'House 5, Sector 5-B, Surjani, Karachi', '2026-07-01', 'Parents requested morning shift'),
(2, 'APP-2026-0002', 'Zain', 'Khan', 'Male', '2019-04-12', 4, 'Approved', 'Asif Khan', '03215551234', 'asif@gmail.com', 'Flat C-4, Gulshan-e-Iqbal, Karachi', '2026-06-25', 'Transferred from another school'),
(3, 'APP-2026-0003', 'Bilal', 'Siddiqui', 'Male', '2015-08-30', 9, 'Rejected', 'Siddique Shah', '03009998887', 'siddique@yahoo.com', 'House 9A, Block 6, PECHS, Karachi', '2026-06-28', 'Did not clear the entrance exam criteria')
ON DUPLICATE KEY UPDATE 
  `application_no` = VALUES(`application_no`),
  `first_name` = VALUES(`first_name`),
  `last_name` = VALUES(`last_name`),
  `gender` = VALUES(`gender`),
  `date_of_birth` = VALUES(`date_of_birth`),
  `class_id` = VALUES(`class_id`),
  `status` = VALUES(`status`),
  `guardian_name` = VALUES(`guardian_name`),
  `guardian_phone` = VALUES(`guardian_phone`),
  `guardian_email` = VALUES(`guardian_email`),
  `address` = VALUES(`address`),
  `application_date` = VALUES(`application_date`),
  `notes` = VALUES(`notes`);

-- ========================================================
-- PHASE 3 SEED: ATTENDANCE
-- ========================================================

-- Seed student attendance (last 5 school days for student ID 1, 2, 3)
INSERT INTO `attendance` (`student_id`, `class_id`, `date`, `status`, `remarks`, `marked_by`) VALUES
(1, 4, CURDATE() - INTERVAL 5 DAY, 'Present', NULL, 2),
(1, 4, CURDATE() - INTERVAL 4 DAY, 'Present', NULL, 2),
(1, 4, CURDATE() - INTERVAL 3 DAY, 'Late', 'Arrived 10 min late', 2),
(1, 4, CURDATE() - INTERVAL 2 DAY, 'Present', NULL, 2),
(1, 4, CURDATE() - INTERVAL 1 DAY, 'Present', NULL, 2),
(2, 3, CURDATE() - INTERVAL 5 DAY, 'Present', NULL, 2),
(2, 3, CURDATE() - INTERVAL 4 DAY, 'Absent', 'Sick leave', 2),
(2, 3, CURDATE() - INTERVAL 3 DAY, 'Present', NULL, 2),
(2, 3, CURDATE() - INTERVAL 2 DAY, 'Present', NULL, 2),
(2, 3, CURDATE() - INTERVAL 1 DAY, 'Leave', 'Family event', 2),
(3, 6, CURDATE() - INTERVAL 5 DAY, 'Absent', NULL, 2),
(3, 6, CURDATE() - INTERVAL 4 DAY, 'Absent', NULL, 2),
(3, 6, CURDATE() - INTERVAL 3 DAY, 'Present', NULL, 2),
(3, 6, CURDATE() - INTERVAL 2 DAY, 'Present', NULL, 2),
(3, 6, CURDATE() - INTERVAL 1 DAY, 'Present', NULL, 2)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`), `remarks` = VALUES(`remarks`);

-- Seed staff attendance
INSERT INTO `staff_attendance` (`user_id`, `date`, `status`, `check_in_time`, `check_out_time`, `remarks`) VALUES
(1, CURDATE() - INTERVAL 1 DAY, 'Present', '07:45:00', '14:30:00', NULL),
(2, CURDATE() - INTERVAL 1 DAY, 'Present', '07:50:00', '14:15:00', NULL),
(3, CURDATE() - INTERVAL 1 DAY, 'Late', '08:25:00', '14:00:00', 'Traffic delay')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- Seed a leave application
INSERT INTO `leave_applications` (`id`, `applicant_type`, `applicant_id`, `leave_type`, `start_date`, `end_date`, `reason`, `status`) VALUES
(1, 'student', 2, 'Sick Leave', CURDATE() + INTERVAL 2 DAY, CURDATE() + INTERVAL 4 DAY, 'Doctor advised rest due to flu', 'Pending')
ON DUPLICATE KEY UPDATE `reason` = VALUES(`reason`);

-- ========================================================
-- PHASE 4 SEED: FEE & ACCOUNTS + CASH
-- ========================================================

-- Seed fee structures (per class, current academic year)
INSERT INTO `fee_structures` (`id`, `class_id`, `fee_type`, `amount`, `academic_year`) VALUES
(1, 1, 'Tuition', 3000.00, '2026-2027'),
(2, 1, 'Admission', 5000.00, '2026-2027'),
(3, 2, 'Tuition', 3500.00, '2026-2027'),
(4, 2, 'Admission', 5000.00, '2026-2027'),
(5, 3, 'Tuition', 4000.00, '2026-2027'),
(6, 3, 'Admission', 6000.00, '2026-2027'),
(7, 4, 'Tuition', 4500.00, '2026-2027'),
(8, 4, 'Admission', 6000.00, '2026-2027'),
(9, 4, 'Exam Fee', 1500.00, '2026-2027'),
(10, 5, 'Tuition', 4500.00, '2026-2027'),
(11, 6, 'Tuition', 5000.00, '2026-2027'),
(12, 6, 'Admission', 7000.00, '2026-2027'),
(13, 6, 'Exam Fee', 2000.00, '2026-2027'),
(14, 7, 'Tuition', 5000.00, '2026-2027'),
(15, 8, 'Tuition', 5500.00, '2026-2027'),
(16, 9, 'Tuition', 6000.00, '2026-2027'),
(17, 10, 'Tuition', 6500.00, '2026-2027')
ON DUPLICATE KEY UPDATE `amount` = VALUES(`amount`);

-- Seed fee challans
INSERT INTO `fee_challans` (`id`, `student_id`, `challan_no`, `month`, `academic_year`, `total_amount`, `discount_amount`, `fine_amount`, `net_amount`, `due_date`, `status`, `generated_by`) VALUES
(1, 1, 'IGS-FEE-2026-0001', 'July 2026', '2026-2027', 4500.00, 0.00, 0.00, 4500.00, '2026-07-15', 'Unpaid', 3),
(2, 2, 'IGS-FEE-2026-0002', 'July 2026', '2026-2027', 4000.00, 500.00, 0.00, 3500.00, '2026-07-15', 'Paid', 3),
(3, 3, 'IGS-FEE-2026-0003', 'July 2026', '2026-2027', 5000.00, 0.00, 500.00, 5500.00, '2026-07-10', 'Overdue', 3)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- Seed challan items
INSERT INTO `fee_challan_items` (`id`, `challan_id`, `fee_type`, `amount`) VALUES
(1, 1, 'Tuition', 4500.00),
(2, 2, 'Tuition', 4000.00),
(3, 3, 'Tuition', 5000.00)
ON DUPLICATE KEY UPDATE `amount` = VALUES(`amount`);

-- Seed a fee collection (payment for challan 2)
INSERT INTO `fee_collections` (`id`, `challan_id`, `student_id`, `amount_paid`, `payment_date`, `payment_method`, `receipt_no`, `collected_by`, `remarks`) VALUES
(1, 2, 2, 3500.00, '2026-07-05', 'Cash', 'RCP-2026-0001', 3, 'Monthly tuition paid in full')
ON DUPLICATE KEY UPDATE `amount_paid` = VALUES(`amount_paid`);

-- Seed a discount
INSERT INTO `fee_discounts` (`id`, `student_id`, `discount_type`, `percentage`, `flat_amount`, `reason`, `is_active`) VALUES
(1, 2, 'Sibling Discount', 0.00, 500.00, 'Sibling enrolled in Class 1', 1)
ON DUPLICATE KEY UPDATE `reason` = VALUES(`reason`);

-- Seed a fine
INSERT INTO `fee_fines` (`id`, `student_id`, `fine_type`, `amount`, `reason`, `status`) VALUES
(1, 3, 'Late Payment', 500.00, 'Tuition fee paid after due date', 'Pending')
ON DUPLICATE KEY UPDATE `amount` = VALUES(`amount`);

-- Seed expense
INSERT INTO `expenses` (`id`, `category`, `description`, `amount`, `expense_date`, `paid_to`, `receipt_reference`, `recorded_by`) VALUES
(1, 'Utilities', 'Electricity bill for June 2026', 15000.00, '2026-07-02', 'K-Electric', 'KE-2026-JUN', 3),
(2, 'Stationery', 'Board markers and chart papers', 3500.00, '2026-07-03', 'National Stationery', NULL, 3)
ON DUPLICATE KEY UPDATE `amount` = VALUES(`amount`);

-- Seed cash register
INSERT INTO `cash_register` (`id`, `date`, `opening_balance`, `total_collections`, `total_expenses`, `closing_balance`, `closed_by`, `status`, `notes`) VALUES
(1, CURDATE() - INTERVAL 1 DAY, 5000.00, 3500.00, 18500.00, -10000.00, 3, 'Closed', 'Heavy expense day'),
(2, CURDATE(), 5000.00, 0.00, 0.00, NULL, NULL, 'Open', NULL)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- ========================================================
-- PHASE 5 SEED: EXAMINATION
-- ========================================================

-- Seed subjects (for Class 1 - A and Prep - A)
INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `class_id`) VALUES
(1, 'English', 'ENG', 4),
(2, 'Urdu', 'URD', 4),
(3, 'Mathematics', 'MATH', 4),
(4, 'Science', 'SCI', 4),
(5, 'Islamiat', 'ISL', 4),
(6, 'Social Studies', 'SST', 4),
(7, 'English', 'ENG', 3),
(8, 'Urdu', 'URD', 3),
(9, 'Mathematics', 'MATH', 3),
(10, 'Science', 'SCI', 3)
ON DUPLICATE KEY UPDATE `subject_name` = VALUES(`subject_name`);

-- Seed exams
INSERT INTO `exams` (`id`, `exam_name`, `exam_type`, `academic_year`, `start_date`, `end_date`, `status`) VALUES
(1, 'Mid-Term Examination 2026', 'Mid-Term', '2026-2027', '2026-08-15', '2026-08-25', 'Upcoming'),
(2, 'Class Test - July 2026', 'Test', '2026-2027', '2026-07-20', '2026-07-20', 'Upcoming')
ON DUPLICATE KEY UPDATE `exam_name` = VALUES(`exam_name`);

-- Seed grade scale
INSERT INTO `grade_scales` (`id`, `grade`, `min_percentage`, `max_percentage`, `remarks`) VALUES
(1, 'A+', 90.00, 100.00, 'Outstanding'),
(2, 'A', 80.00, 89.99, 'Excellent'),
(3, 'B+', 70.00, 79.99, 'Very Good'),
(4, 'B', 60.00, 69.99, 'Good'),
(5, 'C', 50.00, 59.99, 'Satisfactory'),
(6, 'D', 40.00, 49.99, 'Below Average'),
(7, 'F', 0.00, 39.99, 'Fail')
ON DUPLICATE KEY UPDATE `remarks` = VALUES(`remarks`);

-- ========================================================
-- PHASE 6 SEED: STAFF / HR
-- ========================================================

-- Seed staff records
INSERT INTO `staff` (`id`, `employee_no`, `user_id`, `first_name`, `last_name`, `designation`, `department`, `phone`, `email`, `address`, `date_of_joining`, `salary`, `status`) VALUES
(1, 'EMP-2024-001', 1, 'Saeed', 'Ahmed', 'Owner / Director', 'Management', '03001234567', 'saeed@indus.edu.pk', 'DHA Phase 5, Karachi', '2024-01-01', 150000.00, 'Active'),
(2, 'EMP-2024-002', 2, 'Asma', 'Khan', 'Principal', 'Administration', '03112223344', 'admin@indus.edu.pk', 'Gulshan-e-Iqbal, Karachi', '2024-03-15', 80000.00, 'Active'),
(3, 'EMP-2024-003', 3, 'Imran', 'Hussain', 'Accountant', 'Finance', '03211112233', 'cashier@indus.edu.pk', 'North Nazimabad, Karachi', '2024-06-01', 45000.00, 'Active'),
(4, 'EMP-2025-004', NULL, 'Fatima', 'Noor', 'English Teacher', 'Academic', '03331234567', 'fatima.noor@gmail.com', 'PECHS Block 2, Karachi', '2025-01-10', 40000.00, 'Active'),
(5, 'EMP-2025-005', NULL, 'Ali', 'Raza', 'Math Teacher', 'Academic', '03441234567', NULL, 'Nazimabad Block 3, Karachi', '2025-02-01', 38000.00, 'Active'),
(6, 'EMP-2025-006', NULL, 'Sana', 'Tariq', 'Science Teacher', 'Academic', '03551234567', 'sana.tariq@gmail.com', 'Clifton Block 5, Karachi', '2025-04-01', 42000.00, 'Active')
ON DUPLICATE KEY UPDATE `first_name` = VALUES(`first_name`), `last_name` = VALUES(`last_name`);

-- Seed staff salaries (June 2026)
INSERT INTO `staff_salaries` (`id`, `staff_id`, `month`, `year`, `basic_salary`, `allowances`, `deductions`, `net_salary`, `payment_date`, `payment_status`) VALUES
(1, 2, 6, 2026, 80000.00, 5000.00, 2000.00, 83000.00, '2026-06-30', 'Paid'),
(2, 3, 6, 2026, 45000.00, 3000.00, 1000.00, 47000.00, '2026-06-30', 'Paid'),
(3, 4, 6, 2026, 40000.00, 2000.00, 1000.00, 41000.00, '2026-06-30', 'Paid'),
(4, 5, 6, 2026, 38000.00, 2000.00, 1000.00, 39000.00, '2026-06-30', 'Paid'),
(5, 6, 6, 2026, 42000.00, 2500.00, 1000.00, 43500.00, '2026-06-30', 'Paid'),
(6, 2, 7, 2026, 80000.00, 5000.00, 2000.00, 83000.00, NULL, 'Pending'),
(7, 3, 7, 2026, 45000.00, 3000.00, 1000.00, 47000.00, NULL, 'Pending'),
(8, 4, 7, 2026, 40000.00, 2000.00, 1000.00, 41000.00, NULL, 'Pending'),
(9, 5, 7, 2026, 38000.00, 2000.00, 1000.00, 39000.00, NULL, 'Pending'),
(10, 6, 7, 2026, 42000.00, 2500.00, 1000.00, 43500.00, NULL, 'Pending')
ON DUPLICATE KEY UPDATE `net_salary` = VALUES(`net_salary`);
