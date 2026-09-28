-- ==============================================================================
-- INDUS GRAMMAR SCHOOL ERP - CLEAN PRODUCTION DATABASE SCRIPT
-- This script removes all dummy/test operational data while PRESERVING:
--  - System Roles, Permissions, & Admin Users (waqas7600, admin)
--  - Classes & Sections setup
--  - Academic Sessions
--  - Grading Scales & Subject structures
--  - System Settings
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Student & Admission Data
TRUNCATE TABLE `students`;
TRUNCATE TABLE `admissions`;
TRUNCATE TABLE `admission_inquiries`;
TRUNCATE TABLE `student_documents`;
TRUNCATE TABLE `student_promotions`;
TRUNCATE TABLE `student_complaints`;
TRUNCATE TABLE `student_diaries`;
TRUNCATE TABLE `student_sponsors`;

-- 2. Attendance & Leaves
TRUNCATE TABLE `attendance`;
TRUNCATE TABLE `staff_attendance`;
TRUNCATE TABLE `leave_applications`;
TRUNCATE TABLE `staff_leaves`;

-- 3. Fees & Financials
TRUNCATE TABLE `fee_challans`;
TRUNCATE TABLE `fee_challan_items`;
TRUNCATE TABLE `fee_collections`;
TRUNCATE TABLE `fee_payments`;
TRUNCATE TABLE `fee_ledger`;
TRUNCATE TABLE `fee_receipts`;
TRUNCATE TABLE `fee_fines`;
TRUNCATE TABLE `fee_discounts`;
TRUNCATE TABLE `expenses`;
TRUNCATE TABLE `expense_categories`;
TRUNCATE TABLE `income`;
TRUNCATE TABLE `income_categories`;
TRUNCATE TABLE `cash_register`;
TRUNCATE TABLE `bank_accounts`;
TRUNCATE TABLE `bank_transactions`;

-- 4. Examinations & Marks
TRUNCATE TABLE `marks`;
TRUNCATE TABLE `results`;
TRUNCATE TABLE `exam_schedules`;
TRUNCATE TABLE `report_cards`;

-- 5. Staff & Payroll
TRUNCATE TABLE `staff_salaries`;
TRUNCATE TABLE `payroll_transactions`;
TRUNCATE TABLE `payroll_allowances`;
TRUNCATE TABLE `payroll_deductions`;
TRUNCATE TABLE `payroll_bonuses`;
TRUNCATE TABLE `advance_salary`;

-- 6. Communication & History
TRUNCATE TABLE `sms_history`;
TRUNCATE TABLE `whatsapp_history`;
TRUNCATE TABLE `circulars`;
TRUNCATE TABLE `announcements`;

-- 7. Audit Logs & Temp Sessions
TRUNCATE TABLE `audit_logs`;
TRUNCATE TABLE `user_remember_tokens`;

SET FOREIGN_KEY_CHECKS = 1;

-- Finished
SELECT 'DATABASE CLEANED SUCCESSFULLY! All dummy records removed. Ready for live operations.' AS Status;
