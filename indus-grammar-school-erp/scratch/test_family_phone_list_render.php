<?php
/**
 * Test script for family phone list printable template & module
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';
$_SESSION['permissions'] = ['student_view'];

ob_start();
include __DIR__ . '/../templates/family_phone_list.php';
$htmlTemplate = ob_get_clean();

ob_start();
include __DIR__ . '/../modules/students/family_phone_list.php';
$htmlModule = ob_get_clean();

echo "templates/family_phone_list.php rendered successfully! Length: " . strlen($htmlTemplate) . " bytes.\n";
echo "modules/students/family_phone_list.php rendered successfully! Length: " . strlen($htmlModule) . " bytes.\n";

if (str_contains($htmlTemplate, 'FAMILY PHONE NUMBERS & PARENT CONTACT DIRECTORY')) {
    echo "Found title in standalone family phone list template!\n";
}
if (str_contains($htmlModule, 'Print A4 Sheet')) {
    echo "Found 'Print A4 Sheet' button in module header!\n";
}
