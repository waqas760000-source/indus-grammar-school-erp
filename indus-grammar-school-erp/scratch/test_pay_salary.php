<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

$_SESSION['user_id'] = 1;
$_SESSION['role_code'] = 'super_admin';
$_SESSION['role_name'] = 'Super Admin';
$_SERVER['REQUEST_METHOD'] = 'POST';

$salary = $db->query("SELECT id FROM salary_details WHERE payment_status = 'Pending' LIMIT 1")->fetch();

if ($salary) {
    echo "Found pending salary record ID: " . $salary['id'] . "\n";
    $_POST['action'] = 'pay_salary';
    $_POST['salary_id'] = $salary['id'];
    $_POST['payment_date'] = date('Y-m-d');
    $_POST['payment_method'] = 'Bank Transfer';
    $_POST['csrf_token'] = csrfToken();

    echo "Running pay_salary AJAX action...\n";
    ob_start();
    try {
        include __DIR__ . '/../ajax/payroll.php';
    } catch (Exception $e) {
        echo "Caught Exception: " . $e->getMessage() . "\n";
    }
    $out = ob_get_clean();
    echo "Output: $out\n";
} else {
    echo "No pending salary details record found to test.\n";
}
