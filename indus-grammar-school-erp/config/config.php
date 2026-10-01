<?php
/**
 * Indus Grammar School ERP
 * Application Configuration
 */

// Application
define('APP_NAME', getenv('APP_NAME') ?: 'Indus Grammar School ERP');
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_TIMEZONE', getenv('APP_TIMEZONE') ?: 'Asia/Karachi');


date_default_timezone_set(APP_TIMEZONE);

// System Paths
define('DIR_ROOT', dirname(__DIR__));
define('DIR_CONFIG', DIR_ROOT . '/config');
define('DIR_CONTROLLERS', DIR_ROOT . '/controllers');
define('DIR_MODELS', DIR_ROOT . '/models');
define('DIR_SERVICES', DIR_ROOT . '/services');
define('DIR_MIDDLEWARE', DIR_ROOT . '/middleware');
define('DIR_HELPERS', DIR_ROOT . '/helpers');
define('DIR_INCLUDES', DIR_ROOT . '/includes');
define('DIR_STORAGE', DIR_ROOT . '/storage');
define('DIR_LOGS', DIR_STORAGE . '/logs');

// Application Base URL (Dynamic detection for local XAMPP/ports, Docker, and VPS)
if (getenv('APP_URL')) {
    define('APP_URL', rtrim(getenv('APP_URL'), '/'));
} else {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
              (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    
    if (!empty($host)) {
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';
        $dirRoot = str_replace('\\', '/', realpath(DIR_ROOT) ?: DIR_ROOT);
        $basePath = '';
        if (!empty($docRoot) && str_starts_with($dirRoot, $docRoot)) {
            $basePath = substr($dirRoot, strlen($docRoot));
        }
        define('APP_URL', rtrim($scheme . '://' . $host . $basePath, '/'));
    } else {
        define('APP_URL', 'http://localhost/indus-grammar-school-erp/indus-grammar-school-erp');
    }
}


// Database
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'indus_grammar_school');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'rH36@u2t');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// Security settings
define('SECURE_SESSION', false); 
define('CSRF_TOKEN_EXPIRE', 7200); 
define('REMEMBER_ME_EXPIRE', 86400 * 30); 
define('REMEMBER_ME_COOKIE', 'igs_remember_token');

// Session configuration
define('SESSION_LIFETIME', 86400); 
define('SESSION_NAME', 'IGS_ERP_SESSION');