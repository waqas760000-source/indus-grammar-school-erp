<?php
/**
 * Indus Grammar School ERP - Authentication Middleware
 * Version 1.0.0
 */

class AuthMiddleware {
    
    /**
     * Protect page - require user to be logged in
     *
     * @return void
     */
    public static function requireLogin(): void {
        if (!isLoggedIn()) {
            // Try to authenticate via remember-me cookie
            if (self::attemptCookieLogin()) {
                return;
            }
            
            // Redirect to login page and remember original destination
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            redirect(APP_URL . '/login.php');
        }
    }

    /**
     * Guest access only (e.g. login page)
     *
     * @return void
     */
    public static function requireGuest(): void {
        if (isLoggedIn()) {
            redirect(APP_URL . '/dashboard.php');
        } else {
            // Attempt to login via cookie, if successful redirect to dashboard
            if (self::attemptCookieLogin()) {
                redirect(APP_URL . '/dashboard.php');
            }
        }
    }

    /**
     * Protect page - require specific permission
     *
     * @param string $permissionCode
     * @return void
     */
    public static function requirePermission(string $permissionCode): void {
        self::requireLogin();

        if (!hasPermission($permissionCode)) {
            // Save flash error and redirect to dashboard
            $_SESSION['flash_error'] = "You do not have permission to access that resource.";
            redirect(APP_URL . '/dashboard.php');
        }
    }

    /**
     * Protect page - require one of the specified roles
     *
     * @param array $roles List of acceptable role codes (e.g., ['super_admin', 'school_admin'])
     * @return void
     */
    public static function requireRole(array $roles): void {
        self::requireLogin();

        $userRole = $_SESSION['role_code'] ?? '';
        if (!in_array($userRole, $roles)) {
            $_SESSION['flash_error'] = "Unauthorized access. Your role does not permit viewing that page.";
            redirect(APP_URL . '/dashboard.php');
        }
    }

    /**
     * Process auto-login if remember-me cookie exists
     *
     * @return bool
     */
    private static function attemptCookieLogin(): bool {
        if (empty($_COOKIE[REMEMBER_ME_COOKIE])) {
            return false;
        }

        $cookieValue = $_COOKIE[REMEMBER_ME_COOKIE];
        $parts = explode(':', $cookieValue);
        
        if (count($parts) !== 2) {
            self::clearRememberCookie();
            return false;
        }

        list($selector, $validator) = $parts;

        try {
            $db = Database::getConnection();
            
            // Find token by selector
            $stmt = $db->prepare("SELECT * FROM user_remember_tokens WHERE selector = :selector AND expires_at > NOW()");
            $stmt->execute(['selector' => $selector]);
            $tokenRecord = $stmt->fetch();

            if (!$tokenRecord) {
                self::clearRememberCookie();
                return false;
            }

            // Verify hash of validator
            if (hash_equals($tokenRecord['token_hash'], hash('sha256', $validator))) {
                // Token matches, fetch User details
                $userStmt = $db->prepare("
                    SELECT u.*, r.code as role_code, r.name as role_name 
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE u.id = :user_id AND u.is_active = 1
                ");
                $userStmt->execute(['user_id' => $tokenRecord['user_id']]);
                $user = $userStmt->fetch();

                if ($user) {
                    // Start authenticated session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['role_code'] = $user['role_code'];
                    $_SESSION['role_name'] = $user['role_name'];

                    // Load user permissions
                    $permStmt = $db->prepare("
                        SELECT p.code 
                        FROM permissions p
                        JOIN role_permissions rp ON p.id = rp.permission_id
                        WHERE rp.role_id = :role_id
                    ");
                    $permStmt->execute(['role_id' => $user['role_id']]);
                    $_SESSION['permissions'] = $permStmt->fetchAll(PDO::FETCH_COLUMN);

                    // Update last login
                    $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
                    $updateStmt->execute(['id' => $user['id']]);

                    // Audit Log
                    auditLog('Auto-Login', 'User automatically logged in via remember-me cookie', $user['id']);

                    // Rotate remember-me token (delete old, insert new)
                    $deleteStmt = $db->prepare("DELETE FROM user_remember_tokens WHERE id = :id");
                    $deleteStmt->execute(['id' => $tokenRecord['id']]);

                    // Generate new token
                    $newSelector = bin2hex(random_bytes(12));
                    $newValidator = bin2hex(random_bytes(32));
                    $newExpiry = date('Y-m-d H:i:s', time() + REMEMBER_ME_EXPIRE);

                    $insertStmt = $db->prepare("
                        INSERT INTO user_remember_tokens (user_id, selector, token_hash, expires_at)
                        VALUES (:user_id, :selector, :token_hash, :expires_at)
                    ");
                    $insertStmt->execute([
                        'user_id' => $user['id'],
                        'selector' => $newSelector,
                        'token_hash' => hash('sha256', $newValidator),
                        'expires_at' => $newExpiry
                    ]);

                    // Set cookie
                    setcookie(
                        REMEMBER_ME_COOKIE,
                        $newSelector . ':' . $newValidator,
                        time() + REMEMBER_ME_EXPIRE,
                        '/',
                        $_SERVER['HTTP_HOST'] ?? '',
                        SECURE_SESSION,
                        true
                    );

                    return true;
                }
            }
        } catch (Exception $e) {
            error_log("Remember me cookie login failed: " . $e->getMessage());
        }

        self::clearRememberCookie();
        return false;
    }

    /**
     * Clear remember-me cookies from system
     *
     * @return void
     */
    public static function clearRememberCookie(): void {
        if (isset($_COOKIE[REMEMBER_ME_COOKIE])) {
            // Delete token from database if we can parse it
            $parts = explode(':', $_COOKIE[REMEMBER_ME_COOKIE]);
            if (count($parts) === 2) {
                try {
                    $db = Database::getConnection();
                    $stmt = $db->prepare("DELETE FROM user_remember_tokens WHERE selector = :selector");
                    $stmt->execute(['selector' => $parts[0]]);
                } catch (Exception $e) {
                    error_log("Failed to delete token from DB: " . $e->getMessage());
                }
            }

            // Clear cookie in browser
            setcookie(REMEMBER_ME_COOKIE, '', time() - 3600, '/', $_SERVER['HTTP_HOST'] ?? '', SECURE_SESSION, true);
            unset($_COOKIE[REMEMBER_ME_COOKIE]);
        }
    }
}
