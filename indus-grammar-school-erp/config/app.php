<?php
/**
 * Indus Grammar School ERP - Application Bootstrap
 * Version 1.0.0
 */

// Load basic configuration files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../helpers/helpers.php';

// Class Autoloader
spl_autoload_register(function ($className) {
    $directories = [
        DIR_MODELS,
        DIR_CONTROLLERS,
        DIR_SERVICES,
        DIR_MIDDLEWARE,
        DIR_HELPERS
    ];

    foreach ($directories as $directory) {
        $file = $directory . '/' . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Configure Secure Session Parameters
if (session_status() === PHP_SESSION_NONE) {
    // Set custom session name
    session_name(SESSION_NAME);

    // Secure cookie parameters
    $cookieParams = [
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => SECURE_SESSION, // true only on HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    session_set_cookie_params($cookieParams);
    session_start();
}

// Generate or renew CSRF token if not exists or expired
if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time']) || (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRE)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

// Global Exception Handler
set_exception_handler(function ($exception) {
    error_log("Unhandled Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    if (APP_ENV === 'development') {
        echo "<div style='padding: 20px; background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; font-family: monospace;'>";
        echo "<h3>Unhandled Exception</h3>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
        echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
        echo "<p><strong>Trace:</strong></p><pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        echo "</div>";
    } else {
        http_response_code(500);
        include_once DIR_ROOT . '/500.php'; // Fallback page
        exit();
    }
});
