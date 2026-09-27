<?php
/**
 * Test script to verify printable fee challan rendering
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

// Mock session if needed
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';

// Buffer output from templates/challan.php
ob_start();
include __DIR__ . '/../templates/challan.php';
$html = ob_get_clean();

echo "Fee Challan HTML Rendered Successfully!\n";
echo "HTML Output Byte Length: " . strlen($html) . " bytes.\n";
if (str_contains($html, 'INDUS GRAMMAR SCHOOL')) {
    echo "Found 'INDUS GRAMMAR SCHOOL' header in output!\n";
}
if (str_contains($html, 'BANK COPY')) {
    echo "Found 3-Copy BANK COPY tag in output!\n";
}
if (str_contains($html, 'logo.svg') || str_contains($html, 'school-logo-img')) {
    echo "School logo correctly embedded in output!\n";
}
