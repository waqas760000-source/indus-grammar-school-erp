<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

$count = (int)$db->query("SELECT COUNT(*) FROM payroll_settings WHERE id = 1")->fetchColumn();
if ($count === 0) {
    $db->exec("
        INSERT INTO payroll_settings 
        (id, salary_payment_date, working_days_per_month, late_deduction_rule, half_day_rule, absent_deduction_rule, overtime_rate, currency, default_payment_method, auto_issue_enabled, auto_issue_day, auto_issue_status)
        VALUES 
        (1, 10, 26, 0.25, 0.50, 1.00, 0.00, 'Rs.', 'Bank Transfer', 1, 10, 'Pending')
    ");
    echo "Inserted default payroll settings row (id = 1).\n";
} else {
    echo "Default payroll settings row exists.\n";
}

$settings = $db->query("SELECT * FROM payroll_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
print_r($settings);
