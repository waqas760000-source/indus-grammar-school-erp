<?php
/**
 * Test script for modules/students/profile_report.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';
$_SESSION['permissions'] = ['student_view'];

$_GET['id'] = 1;

ob_start();
include __DIR__ . '/../modules/students/profile_report.php';
$html = ob_get_clean();

echo "modules/students/profile_report.php rendered successfully! Length: " . strlen($html) . " bytes.\n";
