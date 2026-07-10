<?php
/**
 * Indus Grammar School ERP - AccountantMiddleware
 * Restricts access to accountant/cashier-specific routes.
 * Version 1.0.0
 */

class AccountantMiddleware {

    /**
     * Allow only Super Admin and Accountant roles
     */
    public static function handle(): void {
        AuthMiddleware::requireLogin();
        $role = $_SESSION['role_code'] ?? '';
        if (!in_array($role, [ROLE_SUPER_ADMIN, ROLE_ACCOUNTANT])) {
            $_SESSION['flash_error'] = 'Access denied. This section is restricted to Finance staff only.';
            redirect(APP_URL . '/dashboard.php');
        }
    }
}
