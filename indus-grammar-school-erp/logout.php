<?php
/**
 * Indus Grammar School ERP - Logout Handler
 * Version 1.0.0
 */

// Boot application
require_once __DIR__ . '/config/app.php';

// Instantiate auth controller and trigger logout
$authController = new AuthController();
$authController->logout();

// Redirect back to login screen
redirect(APP_URL . '/login.php');
