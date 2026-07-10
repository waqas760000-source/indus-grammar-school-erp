<?php
/**
 * Indus Grammar School ERP - Authentication Controller
 * Version 1.0.0
 */

class AuthController {

    /**
     * Authenticate user credentials and start session
     *
     * @param string $login Username or email
     * @param string $password Clear-text password
     * @param bool $rememberMe Whether to issue a remember-me cookie
     * @return array Response array containing status and message
     */
    public function login(string $login, string $password, bool $rememberMe = false): array {
        $login = trim($login);
        $password = trim($password);

        if (empty($login) || empty($password)) {
            return ['status' => false, 'message' => 'Please enter both username/email and password.'];
        }

        // 1. Verify DB Connection & Tables
        try {
            $db = Database::getConnection();
            
            // Check if tables exist
            $tablesStmt = $db->query("SHOW TABLES");
            $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('users', $tables) || !in_array('roles', $tables)) {
                return [
                    'status' => false,
                    'message' => 'System configuration error: Required database tables (users/roles) are missing. Please import the schema.'
                ];
            }
        } catch (PDOException $e) {
            return [
                'status' => false,
                'message' => 'Database connection failed: ' . $e->getMessage() . '. Please verify config/config.php database credentials.'
            ];
        }

        // 2. Verify/Create Super Admin Waqas Ali
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = 'waqas7600' OR email = 'waqasaliwaqas7600@gmail.com'");
            $stmt->execute();
            $exists = (int)$stmt->fetchColumn() > 0;
            if (!$exists) {
                // Fetch Super Admin role id
                $roleStmt = $db->prepare("SELECT id FROM roles WHERE code = 'super_admin' LIMIT 1");
                $roleStmt->execute();
                $roleId = $roleStmt->fetchColumn() ?: 1;

                // Create Waqas Ali super admin user
                $userStmt = $db->prepare("
                    INSERT INTO users (username, email, password_hash, role_id, is_active)
                    VALUES (:username, :email, :password_hash, :role_id, 1)
                ");
                $userStmt->execute([
                    'username' => 'waqas7600',
                    'email' => 'waqasaliwaqas7600@gmail.com',
                    'password_hash' => password_hash('rH36@u2t', PASSWORD_DEFAULT),
                    'role_id' => $roleId
                ]);
                auditLog('System Initialize', "Created first Super Admin account for Waqas Ali (waqas7600).");
            }
        } catch (Exception $e) {
            error_log("Failed to auto-create Super Admin Waqas Ali: " . $e->getMessage());
        }

        // Search user
        $user = User::findByUsernameOrEmail($login);

        if (!$user) {
            // Log failed login attempt
            auditLog('Login Failed', "Failed login attempt for account: " . sanitize($login));
            return ['status' => false, 'message' => 'Invalid username/email or password.'];
        }

        // Check if user is active
        if ((int)$user['is_active'] !== STATUS_ACTIVE) {
            auditLog('Login Blocked', "Attempted login to disabled account: " . $user['username'], $user['id']);
            return ['status' => false, 'message' => 'Your account has been deactivated. Please contact support.'];
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            auditLog('Login Failed', "Invalid password attempt for user: " . $user['username'], $user['id']);
            return ['status' => false, 'message' => 'Invalid username/email or password.'];
        }

        // Start session and store variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role_code'] = $user['role_code'];
        $_SESSION['role_name'] = $user['role_name'];

        // Load permissions
        $_SESSION['permissions'] = User::getPermissions($user['role_id']);

        // Update last login
        User::updateLastLogin($user['id']);

        // Audit Logging
        auditLog('Login Success', "User logged in successfully", $user['id']);

        // Handle Remember Me token
        if ($rememberMe) {
            $this->createRememberMeCookie($user['id']);
        }

        return ['status' => true, 'message' => 'Login successful.'];
    }

    /**
     * Terminate user session and revoke token
     *
     * @return void
     */
    public function logout(): void {
        if (isLoggedIn()) {
            auditLog('Logout', "User logged out successfully");
            
            // Clear remember me cookie
            AuthMiddleware::clearRememberCookie();

            // Unset session variables
            $_SESSION = [];

            // Delete session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(), 
                    '', 
                    time() - 42000,
                    $params["path"], 
                    $params["domain"],
                    $params["secure"], 
                    $params["httponly"]
                );
            }

            // Destroy session
            session_destroy();
        }
    }

    /**
     * Issue a secure remember me cookie and store in DB
     *
     * @param int $userId
     * @return void
     */
    private function createRememberMeCookie(int $userId): void {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + REMEMBER_ME_EXPIRE);

        // Store hash of validator in DB
        $tokenHash = hash('sha256', $validator);
        
        if (User::createRememberToken($userId, $selector, $tokenHash, $expiresAt)) {
            // Set cookie formatted as selector:validator
            setcookie(
                REMEMBER_ME_COOKIE,
                $selector . ':' . $validator,
                time() + REMEMBER_ME_EXPIRE,
                '/',
                $_SERVER['HTTP_HOST'] ?? '',
                SECURE_SESSION,
                true // HttpOnly
            );
        }
    }
}
