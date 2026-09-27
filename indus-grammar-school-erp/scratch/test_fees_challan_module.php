<?php
/**
 * Test script for modules/fees/challan.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';
$_SESSION['permissions'] = ['fee_view', 'fee_collect'];

ob_start();
include __DIR__ . '/../modules/fees/challan.php';
$html = ob_get_clean();

echo "modules/fees/challan.php rendered successfully! Length: " . strlen($html) . " bytes.\n";
