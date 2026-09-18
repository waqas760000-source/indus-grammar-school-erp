<?php
/**
 * Indus Grammar School ERP - Fee Fines Management Redirect
 * Requests to this endpoint automatically redirect to Fee Ledger Sheets.
 */
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();

// Safe redirect to Fee Ledger Sheets
header("Location: " . APP_URL . "/modules/fees/challan.php", true, 302);
exit;
?>
