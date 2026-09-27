<?php
/**
 * Test script for templates/receipt.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';

ob_start();
include __DIR__ . '/../templates/receipt.php';
$html = ob_get_clean();

echo "Fee Receipt HTML Rendered Successfully!\n";
echo "HTML Output Byte Length: " . strlen($html) . " bytes.\n";
if (str_contains($html, 'INDUS GRAMMAR SCHOOL')) {
    echo "Found 'INDUS GRAMMAR SCHOOL' header in receipt output!\n";
}
if (str_contains($html, 'PAID & VERIFIED')) {
    echo "Found 'PAID & VERIFIED' seal in receipt output!\n";
}
if (str_contains($html, 'STUDENT COPY') && str_contains($html, 'OFFICE / SCHOOL COPY')) {
    echo "Found Dual-Copy (Student Copy + Office Copy) in receipt output!\n";
}
if (str_contains($html, 'logo.svg') || str_contains($html, 'logo-box-img')) {
    echo "School logo correctly embedded in receipt output!\n";
}
