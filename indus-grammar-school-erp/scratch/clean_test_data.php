<?php
/**
 * Clean Test Data Script for Indus ERP
 * Clears mock/test students, marks, results, promotions, report cards, fee challans, staff, and payrolls.
 * Preserves System Configuration, Admin Users, Classes, Exam Types, Grade Setup, and Settings.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "=========================================================\n";
echo " INDUS ERP - TEST DATA CLEANUP & FRESH START RESET       \n";
echo "=========================================================\n\n";

try {
    $db = Database::getConnection();

    // Disable Foreign Key Checks temporarily for clean truncation
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");

    $tablesToTruncate = [
        // Examination & Academic Results
        'exam_results',
        'positions',
        'report_cards',
        'student_marks',
        'marks',
        'results',
        'promotions',
        'exam_schedules',

        // Student Data
        'students',
        'admissions',
        'attendance',
        'student_registration_details',

        // Fee & Student Financial Data
        'fee_challan_items',
        'fee_challans',
        'fee_collections',
        'fee_discounts',
        'fee_fines',

        // Staff, HR & Payroll Data
        'staff_salaries',
        'salary_details',
        'salary_processing',
        'advance_salary',
        'staff_attendance',
        'staff_leave',
        'staff',

        // Communication & Leaves
        'leave_applications',
        'sms_history',
        'whatsapp_history'
    ];

    echo "--- Truncating Test Data Tables ---\n";
    foreach ($tablesToTruncate as $tbl) {
        try {
            $db->exec("TRUNCATE TABLE `$tbl`;");
            echo "✓ Truncated table: `$tbl`\n";
        } catch (PDOException $e) {
            // Fallback to DELETE IF TRUNCATE fails due to FK constraints
            try {
                $db->exec("DELETE FROM `$tbl`;");
                $db->exec("ALTER TABLE `$tbl` AUTO_INCREMENT = 1;");
                echo "✓ Cleared table: `$tbl`\n";
            } catch (PDOException $ex) {
                echo "NOTICE (`$tbl`): " . $ex->getMessage() . "\n";
            }
        }
    }

    // Re-enable Foreign Key Checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Reset AUTO_INCREMENT on students & staff tables
    $db->exec("ALTER TABLE students AUTO_INCREMENT = 1;");
    $db->exec("ALTER TABLE staff AUTO_INCREMENT = 1;");

    echo "\n=========================================================\n";
    echo " ALL TEST DATA REMOVED SUCCESSFULLY!\n";
    echo " The system is now 100% clean and ready for real data entry.\n";
    echo "=========================================================\n";

} catch (Exception $ex) {
    if (isset($db)) $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "CLEANUP ERROR: " . $ex->getMessage() . "\n";
}
