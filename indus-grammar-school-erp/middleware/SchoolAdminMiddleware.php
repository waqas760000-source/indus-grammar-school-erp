<?php
/**
 * Indus Grammar School ERP - SchoolAdminMiddleware
 * Restricts access to Super Admin and School Admin roles.
 * Version 1.0.0
 */

class SchoolAdminMiddleware {

    public static function handle(): void {
        AuthMiddleware::requireLogin();
        $role = $_SESSION['role_code'] ?? '';
        if (!in_array($role, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN])) {
            $_SESSION['flash_error'] = 'Access denied. This section requires School Admin or higher privileges.';
            redirect(APP_URL . '/dashboard.php');
        }
    }
}
