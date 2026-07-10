<?php
/**
 * Indus Grammar School ERP - PermissionMiddleware
 * Dynamic permission-based route guard.
 * Version 1.0.0
 */

class PermissionMiddleware {

    /**
     * Require one or more permissions (any match grants access)
     *
     * @param string|array $permissions
     * @param string $redirectTo
     */
    public static function require($permissions, string $redirectTo = ''): void {
        AuthMiddleware::requireLogin();
        $permissions = (array)$permissions;
        foreach ($permissions as $perm) {
            if (hasPermission($perm)) return;
        }
        $_SESSION['flash_error'] = 'You do not have permission to access that resource.';
        redirect($redirectTo ?: APP_URL . '/dashboard.php');
    }

    /**
     * Require ALL listed permissions
     *
     * @param array $permissions
     */
    public static function requireAll(array $permissions): void {
        AuthMiddleware::requireLogin();
        foreach ($permissions as $perm) {
            if (!hasPermission($perm)) {
                $_SESSION['flash_error'] = 'You do not have permission to access that resource.';
                redirect(APP_URL . '/dashboard.php');
            }
        }
    }
}
