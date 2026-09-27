<?php
require_once __DIR__ . '/../config/app.php';
$_SESSION['user_id'] = 1;
$_SESSION['role_code'] = 'super_admin';

$db = Database::getConnection();

// Create sample processing run if not exists
$db->exec("INSERT INTO salary_processing (id, month, year, status) VALUES (999, 9, 2026, 'Processed') ON DUPLICATE KEY UPDATE status='Processed'");
$db->exec("INSERT INTO salary_details (id, processing_id, staff_id, working_days, present_days, absent_days, late_days, leave_days, half_days, basic_salary, allowances, deductions, advance_salary_deduction, bonus, net_salary, payment_status, payment_date, payment_method) 
VALUES (999, 999, 1, 26, 24, 1, 2, 1, 0, 45000.00, 5000.00, 1500.00, 2000.00, 3000.00, 49500.00, 'Paid', '2026-09-15', 'Bank Transfer')
ON DUPLICATE KEY UPDATE payment_status='Paid'");

$_GET['id'] = 999;

ob_start();
include __DIR__ . '/../modules/staff/payroll_slips.php';
$output = ob_get_clean();

echo "SUCCESS: Salary Slips page executed cleanly. Total HTML bytes generated: " . strlen($output) . "\n";
