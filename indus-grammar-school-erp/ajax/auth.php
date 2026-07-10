<?php
/**
 * Indus Grammar School ERP - AJAX Authentication Handler
 * Version 1.0.0
 */

// Boot the application configuration and services
require_once __DIR__ . '/../config/app.php';

// Force POST request only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// Check CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrf($csrfToken)) {
    jsonResponse(['success' => false, 'message' => 'Security token expired. Please refresh the page and try again.'], 403);
}

// Retrieve and sanitize credentials
$login = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';

// Validate required fields
if (empty($login) || empty($password)) {
    jsonResponse(['success' => false, 'message' => 'Username/Email and Password are required.']);
}

// Authenticate via AuthController
$authController = new AuthController();
$result = $authController->login($login, $password, $rememberMe);

if ($result['status'] === true) {
    // Check if there was a redirected URL stored in session
    $redirectUrl = APP_URL . '/dashboard.php';
    if (!empty($_SESSION['redirect_url'])) {
        $redirectUrl = $_SESSION['redirect_url'];
        unset($_SESSION['redirect_url']);
    }
    
    jsonResponse([
        'success' => true, 
        'message' => $result['message'],
        'redirect' => $redirectUrl
    ]);
} else {
    jsonResponse([
        'success' => false, 
        'message' => $result['message']
    ]);
}
