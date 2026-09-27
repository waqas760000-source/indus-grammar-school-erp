<?php
/**
 * Test script for modules/fees/receipts.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';
$_SESSION['permissions'] = ['fee_view'];

ob_start();
include __DIR__ . '/../modules/fees/receipts.php';
$html = ob_get_clean();

echo "modules/fees/receipts.php rendered successfully! Length: " . strlen($html) . " bytes.\n";
