-- Indus Grammar School ERP - Database Schema
-- Version 1.1.0

CREATE DATABASE IF NOT EXISTS `indus_grammar_school` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `indus_grammar_school`;

-- --------------------------------------------------------
-- Table structure for table `roles`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `code` VARCHAR(30) NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `permissions`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `module` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `role_permissions`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role_id` INT NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_active ON users(is_active);

-- --------------------------------------------------------
-- Table structure for table `user_remember_tokens`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_remember_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL UNIQUE,
  `selector` VARCHAR(24) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_tokens_selector ON user_remember_tokens(selector);
CREATE INDEX idx_tokens_expiry ON user_remember_tokens(expires_at);

-- --------------------------------------------------------
-- Table structure for table `audit_logs`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_audit_created ON audit_logs(created_at);
CREATE INDEX idx_audit_user ON audit_logs(user_id);

-- ========================================================
-- NEW PHASE 2 TABLES
-- ========================================================

-- --------------------------------------------------------
-- Table structure for table `classes`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `classes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_name` VARCHAR(50) NOT NULL,
  `section` VARCHAR(10) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_class_section` (`class_name`, `section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `students`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admission_no` VARCHAR(30) NOT NULL UNIQUE,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `enrollment_date` DATE NOT NULL,
  `class_id` INT NOT NULL,
  `status` ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
  `guardian_name` VARCHAR(100) NOT NULL,
  `guardian_phone` VARCHAR(20) NOT NULL,
  `guardian_email` VARCHAR(100) NULL,
  `address` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_students_class ON students(class_id);
CREATE INDEX idx_students_status ON students(status);
CREATE INDEX idx_students_name ON students(first_name, last_name);

-- --------------------------------------------------------
-- Table structure for table `admissions`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_no` VARCHAR(30) NOT NULL UNIQUE,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `class_id` INT NOT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
  `guardian_name` VARCHAR(100) NOT NULL,
  `guardian_phone` VARCHAR(20) NOT NULL,
  `guardian_email` VARCHAR(100) NULL,
  `address` TEXT NOT NULL,
  `application_date` DATE NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_admissions_class ON admissions(class_id);
CREATE INDEX idx_admissions_status ON admissions(status);
CREATE INDEX idx_admissions_no ON admissions(application_no);

-- ========================================================
-- PHASE 3 TABLES: ATTENDANCE
-- ========================================================

-- --------------------------------------------------------
-- Table structure for table `attendance`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `status` ENUM('Present', 'Absent', 'Late', 'Leave') NOT NULL DEFAULT 'Present',
  `remarks` VARCHAR(255) NULL,
  `marked_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  UNIQUE KEY `idx_student_date` (`student_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_attendance_class_date ON attendance(class_id, date);
CREATE INDEX idx_attendance_status ON attendance(status);

-- --------------------------------------------------------
-- Table structure for table `staff_attendance`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `staff_attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `status` ENUM('Present', 'Absent', 'Late', 'Leave') NOT NULL DEFAULT 'Present',
  `check_in_time` TIME NULL,
  `check_out_time` TIME NULL,
  `remarks` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_staff_date` (`user_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_staff_att_date ON staff_attendance(date);

-- --------------------------------------------------------
-- Table structure for table `leave_applications`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leave_applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `applicant_type` ENUM('student', 'staff') NOT NULL,
  `applicant_id` INT NOT NULL,
  `leave_type` VARCHAR(50) NOT NULL DEFAULT 'Casual',
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
  `approved_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_leave_type ON leave_applications(applicant_type, applicant_id);
CREATE INDEX idx_leave_status ON leave_applications(status);

-- ========================================================
-- PHASE 4 TABLES: FEE & ACCOUNTS + CASH
-- ========================================================

-- --------------------------------------------------------
-- Table structure for table `fee_structures`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fee_structures` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `fee_type` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `academic_year` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_class_fee_year` (`class_id`, `fee_type`, `academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `fee_discounts`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fee_discounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `discount_type` VARCHAR(50) NOT NULL,
  `percentage` DECIMAL(5,2) NULL DEFAULT 0.00,
  `flat_amount` DECIMAL(10,2) NULL DEFAULT 0.00,
  `reason` VARCHAR(255) NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `fee_challans`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fee_challans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `challan_no` VARCHAR(30) NOT NULL UNIQUE,
  `month` VARCHAR(20) NOT NULL,
  `academic_year` VARCHAR(20) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `fine_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `net_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `due_date` DATE NOT NULL,
  `status` ENUM('Unpaid', 'Paid', 'Partial', 'Overdue') DEFAULT 'Unpaid',
  `generated_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_challan_student ON fee_challans(student_id);
CREATE INDEX idx_challan_status ON fee_challans(status);

-- --------------------------------------------------------
-- Table structure for table `fee_challan_items`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fee_challan_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `challan_id` INT NOT NULL,
  `fee_type` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (`challan_id`) REFERENCES `fee_challans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `fee_collections`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fee_collections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `challan_id` INT NULL,
  `student_id` INT NOT NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_date` DATE NOT NULL,
  `payment_method` ENUM('Cash', 'Bank', 'Online') NOT NULL DEFAULT 'Cash',
  `receipt_no` VARCHAR(30) NOT NULL UNIQUE,
  `collected_by` INT NULL,
  `remarks` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`challan_id`) REFERENCES `fee_challans` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_collection_date ON fee_collections(payment_date);
CREATE INDEX idx_collection_student ON fee_collections(student_id);

-- --------------------------------------------------------
-- Table structure for table `fee_fines`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fee_fines` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `fine_type` VARCHAR(50) NOT NULL DEFAULT 'Late Payment',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `reason` VARCHAR(255) NULL,
  `status` ENUM('Pending', 'Paid', 'Waived') DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `expenses`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category` VARCHAR(50) NOT NULL,
  `description` TEXT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `expense_date` DATE NOT NULL,
  `paid_to` VARCHAR(100) NULL,
  `receipt_reference` VARCHAR(50) NULL,
  `recorded_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_expense_date ON expenses(expense_date);
CREATE INDEX idx_expense_category ON expenses(category);

-- --------------------------------------------------------
-- Table structure for table `cash_register`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cash_register` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `date` DATE NOT NULL UNIQUE,
  `opening_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_collections` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_expenses` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `closing_balance` DECIMAL(10,2) NULL DEFAULT NULL,
  `closed_by` INT NULL,
  `status` ENUM('Open', 'Closed') DEFAULT 'Open',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- PHASE 5 TABLES: EXAMINATION
-- ========================================================

-- --------------------------------------------------------
-- Table structure for table `subjects`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_name` VARCHAR(100) NOT NULL,
  `subject_code` VARCHAR(20) NOT NULL,
  `class_id` INT NOT NULL,
  `total_marks` INT NOT NULL DEFAULT 100,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_subject_class` (`subject_code`, `class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `exams`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `exams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_name` VARCHAR(100) NOT NULL,
  `exam_type` ENUM('Mid-Term', 'Final', 'Test', 'Quiz') NOT NULL DEFAULT 'Mid-Term',
  `academic_year` VARCHAR(20) NOT NULL,
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `status` ENUM('Upcoming', 'Active', 'Completed') DEFAULT 'Upcoming',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `exam_schedules`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `class_id` INT NOT NULL,
  `exam_date` DATE NOT NULL,
  `start_time` TIME NULL,
  `end_time` TIME NULL,
  `total_marks` INT NOT NULL DEFAULT 100,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `marks`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `marks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `marks_obtained` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `total_marks` INT NOT NULL DEFAULT 100,
  `remarks` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_marks_unique` (`exam_id`, `student_id`, `subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `grade_scales`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `grade_scales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `grade` VARCHAR(5) NOT NULL,
  `min_percentage` DECIMAL(5,2) NOT NULL,
  `max_percentage` DECIMAL(5,2) NOT NULL,
  `remarks` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- PHASE 6 TABLES: STAFF / HR
-- ========================================================

-- --------------------------------------------------------
-- Table structure for table `staff`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `staff` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_no` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT NULL,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `designation` VARCHAR(100) NOT NULL,
  `department` VARCHAR(50) NOT NULL DEFAULT 'Academic',
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `date_of_joining` DATE NOT NULL,
  `salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('Active', 'Inactive', 'Terminated') DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_staff_status ON staff(status);
CREATE INDEX idx_staff_dept ON staff(department);

-- --------------------------------------------------------
-- Table structure for table `staff_salaries`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `staff_salaries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `month` TINYINT NOT NULL,
  `year` SMALLINT NOT NULL,
  `basic_salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `allowances` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `deductions` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `net_salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_date` DATE NULL,
  `payment_status` ENUM('Pending', 'Paid') DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_salary_unique` (`staff_id`, `month`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `results` (marks entry with status)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `results` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `marks_obtained` DECIMAL(5,2) NULL DEFAULT NULL,
  `remarks` VARCHAR(100) NULL,
  `status` ENUM('Present', 'Absent') DEFAULT 'Present',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_result_unique` (`exam_id`, `student_id`, `subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

