<?php
/**
 * Indus Grammar School ERP - AdminMiddleware
 * Restricts access to Super Admin only routes.
 * Version 1.0.0
 */

class AdminMiddleware {

    /**
     * Allow only Super Admin role
     */
    public static function handle(): void {
        AuthMiddleware::requireLogin();
        if (($_SESSION['role_code'] ?? '') !== ROLE_SUPER_ADMIN) {
            $_SESSION['flash_error'] = 'Access denied. Only the System Administrator can access this section.';
            redirect(APP_URL . '/dashboard.php');
        }
    }
}
