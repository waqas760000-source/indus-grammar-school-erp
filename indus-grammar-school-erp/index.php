<?php
/**
 * Indus Grammar School ERP - Application Entry Point
 * Version 1.0.0
 */

// Boot application
require_once __DIR__ . '/config/app.php';

// Route logic
if (isLoggedIn()) {
    redirect(APP_URL . '/dashboard.php');
} else {
    // Attempt automatic cookie re-authentication; if it succeeds, redirect to dashboard.
    // The requireLogin function automatically attempts cookie login.
    AuthMiddleware::requireLogin();
    redirect(APP_URL . '/dashboard.php');
}
