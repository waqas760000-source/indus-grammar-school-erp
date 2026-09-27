<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

$_SESSION['user_id'] = 1;
$_SESSION['role_code'] = 'super_admin';
$_SESSION['role_name'] = 'Super Admin';

$detail = $db->query("SELECT id FROM salary_details LIMIT 1")->fetch();

if ($detail) {
    $_GET['id'] = $detail['id'];
    echo "Testing rendering for salary_details ID {$detail['id']}...\n";
    ob_start();
    try {
        include __DIR__ . '/../modules/staff/payroll_slips.php';
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
    $html = ob_get_clean();
    echo "Rendered HTML length: " . strlen($html) . " bytes\n";
    if (str_contains($html, 'SALARY PAYSLIP') && str_contains($html, 'school-logo-img')) {
        echo "SUCCESS: School logo and voucher header correctly included in output!\n";
    }
} else {
    echo "No salary_details record found to test.\n";
}
