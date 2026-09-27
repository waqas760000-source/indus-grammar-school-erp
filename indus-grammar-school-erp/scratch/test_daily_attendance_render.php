<?php
/**
 * Test script for daily attendance report printable template & module
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';
$_SESSION['permissions'] = ['attendance_view'];

ob_start();
include __DIR__ . '/../templates/daily_attendance.php';
$htmlTemplate = ob_get_clean();

ob_start();
include __DIR__ . '/../modules/attendance/daily.php';
$htmlModule = ob_get_clean();

echo "templates/daily_attendance.php rendered successfully! Length: " . strlen($htmlTemplate) . " bytes.\n";
echo "modules/attendance/daily.php rendered successfully! Length: " . strlen($htmlModule) . " bytes.\n";

if (str_contains($htmlTemplate, 'DAILY STUDENT ATTENDANCE REPORT')) {
    echo "Found title in standalone daily attendance template!\n";
}
if (str_contains($htmlModule, 'Print A4 Sheet')) {
    echo "Found 'Print A4 Sheet' button in daily attendance header!\n";
}
