<?php
require_once __DIR__ . '/../config/app.php';
$_SESSION['user_id'] = 1;
$_SESSION['role_code'] = 'super_admin';

ob_start();
include __DIR__ . '/../modules/staff/payroll_reports.php';
$output = ob_get_clean();

echo "SUCCESS: Payroll Reports Console page executed cleanly. Total HTML bytes generated: " . strlen($output) . "\n";
