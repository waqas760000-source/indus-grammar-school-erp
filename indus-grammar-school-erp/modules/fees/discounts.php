<?php
/**
 * Indus Grammar School ERP - Student Fee Discounts Fallback Redirect
 * The standalone Student Fee Discounts page has been safely deprecated and removed from navigation.
 * Requests to this endpoint automatically redirect to the primary Fee Collection workspace.
 */

require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();

// Safe redirect to Collect Fee page
header("Location: " . APP_URL . "/modules/fees/collection.php", true, 302);
exit;
