<?php
require_once __DIR__ . '/../config/app.php';
$_SESSION['user_id'] = 1;
$_SESSION['role_code'] = 'super_admin';

ob_start();
include __DIR__ . '/../modules/staff/payroll_process.php';
$outProc = ob_get_clean();

ob_start();
include __DIR__ . '/../modules/staff/payroll_deductions.php';
$outDed = ob_get_clean();

echo "SUCCESS: Salary Processing HTML bytes: " . strlen($outProc) . "\n";
echo "SUCCESS: Deductions Management HTML bytes: " . strlen($outDed) . "\n";
