<?php
/**
 * Test script for templates/profile_dossier.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';

// Test with student ID 1
$_GET['id'] = 1;

ob_start();
include __DIR__ . '/../templates/profile_dossier.php';
$html = ob_get_clean();

echo "Profile Dossier HTML Rendered Successfully!\n";
echo "HTML Output Byte Length: " . strlen($html) . " bytes.\n";
if (str_contains($html, 'INDUS GRAMMAR SCHOOL')) {
    echo "Found 'INDUS GRAMMAR SCHOOL' header in dossier output!\n";
}
if (str_contains($html, 'CUMULATIVE STUDENT PROFILE DOSSIER')) {
    echo "Found 'CUMULATIVE STUDENT PROFILE DOSSIER' title in dossier output!\n";
}
if (str_contains($html, 'logo.svg') || str_contains($html, 'school-logo-img')) {
    echo "School logo correctly embedded in dossier output!\n";
}
