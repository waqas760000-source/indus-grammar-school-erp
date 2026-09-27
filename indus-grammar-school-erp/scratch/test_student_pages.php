<?php
/**
 * Test script for student registration & list pages
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';
$_SESSION['permissions'] = ['student_view', 'student_edit'];

ob_start();
include __DIR__ . '/../modules/students/registration.php';
$htmlReg = ob_get_clean();

ob_start();
include __DIR__ . '/../modules/students/list.php';
$htmlList = ob_get_clean();

echo "registration.php rendered successfully! Length: " . strlen($htmlReg) . " bytes.\n";
echo "list.php rendered successfully! Length: " . strlen($htmlList) . " bytes.\n";
