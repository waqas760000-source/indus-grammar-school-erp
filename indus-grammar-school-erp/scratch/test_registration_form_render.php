<?php
/**
 * Test script for templates/registration_form.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';

// Test with student ID 5 or 7
$_GET['id'] = 5;

ob_start();
include __DIR__ . '/../templates/registration_form.php';
$html = ob_get_clean();

echo "Registration Form HTML Rendered Successfully!\n";
echo "HTML Output Byte Length: " . strlen($html) . " bytes.\n";
if (str_contains($html, 'INDUS GRAMMAR SCHOOL')) {
    echo "Found 'INDUS GRAMMAR SCHOOL' header in registration form output!\n";
}
if (str_contains($html, 'STUDENT REGISTRATION & ADMISSION FORM')) {
    echo "Found 'STUDENT REGISTRATION & ADMISSION FORM' title in output!\n";
}
if (str_contains($html, 'logo.svg') || str_contains($html, 'school-logo-img')) {
    echo "School logo correctly embedded in registration form output!\n";
}
