<?php
define('APP_ROOT', __DIR__ . '/..');

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/indus-grammar-school-erp/templates/monthly_attendance.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once APP_ROOT . '/config/app.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'Admin';
$_SESSION['permissions'] = ['attendance_view'];

ob_start();
include APP_ROOT . '/templates/monthly_attendance.php';
$outTpl = ob_get_clean();

ob_start();
include APP_ROOT . '/modules/attendance/monthly.php';
$outMod = ob_get_clean();

file_put_contents(__DIR__ . '/render_result.txt', "TPL LEN: " . strlen($outTpl) . "\nMOD LEN: " . strlen($outMod) . "\n");
echo "DONE! TPL LEN: " . strlen($outTpl) . " | MOD LEN: " . strlen($outMod) . "\n";
