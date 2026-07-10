<?php
/**
 * Indus Grammar School ERP
 * Application Configuration
 */

// Application
define('APP_NAME', 'Indus Grammar School ERP');
define('APP_ENV', 'development');
define('APP_URL', 'http://localhost/indus-grammar-school-erp/indus-grammar-school-erp');
define('APP_TIMEZONE', 'Asia/Karachi');

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


// Database
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'indus_grammar_school');
define('DB_USER', 'root');
define('DB_PASS', 'rH36@u2t');
define('DB_CHARSET', 'utf8mb4');

// Security settings
define('SECURE_SESSION', false); 
define('CSRF_TOKEN_EXPIRE', 7200); 
define('REMEMBER_ME_EXPIRE', 86400 * 30); 
define('REMEMBER_ME_COOKIE', 'igs_remember_token');

// Session configuration
define('SESSION_LIFETIME', 86400); 
define('SESSION_NAME', 'IGS_ERP_SESSION');