<?php
/**
 * Indus Grammar School ERP
 * Safe Redirect for Obsolete Student Leave Management Submodule
 */
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();

header("Location: " . APP_URL . "/modules/attendance/student.php");
exit;
