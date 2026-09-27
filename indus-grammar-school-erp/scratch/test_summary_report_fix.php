<?php
/**
 * Test script for summary report fix
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';
$_SESSION['permissions'] = ['student_view'];

ob_start();
include __DIR__ . '/../templates/summary_report.php';
$htmlTemplate = ob_get_clean();

ob_start();
include __DIR__ . '/../modules/students/summary_report.php';
$htmlModule = ob_get_clean();

echo "templates/summary_report.php rendered successfully! Length: " . strlen($htmlTemplate) . " bytes.\n";
echo "modules/students/summary_report.php rendered successfully! Length: " . strlen($htmlModule) . " bytes.\n";

if (str_contains($htmlTemplate, 'INDUS GRAMMAR SCHOOL & ACADEMY')) {
    echo "Found header in standalone summary template!\n";
}
if (str_contains($htmlModule, '#printReportSection')) {
    echo "Found #printReportSection in module print section!\n";
}
