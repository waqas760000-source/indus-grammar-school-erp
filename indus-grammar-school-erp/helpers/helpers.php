<?php
/**
 * Indus Grammar School ERP - Helper Functions
 * Version 1.0.0
 */

/**
 * Sanitize user input to prevent XSS
 *
 * @param mixed $data
 * @return mixed
 */
function sanitize($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize($value);
        }
    } else {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

/**
 * Safe redirect to a specific URL
 *
 * @param string $url
 * @return void
 */
function redirect(string $url): void {
    header("Location: " . $url);
    exit();
}

/**
 * Output JSON response and exit
 *
 * @param array $data
 * @param int $statusCode
 * @return void
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}

/**
 * Get the current CSRF token
 *
 * @return string
 */
function csrfToken(): string {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Validate a CSRF token
 *
 * @param string|null $token
 * @return bool
 */
function validateCsrf(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Check if token has expired (2 hours)
    if (time() - ($_SESSION['csrf_token_time'] ?? 0) > CSRF_TOKEN_EXPIRE) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log an action to the database audit logs
 *
 * @param string $action
 * @param string $description
 * @param int|null $userId
 * @return bool
 */
function auditLog(string $action, string $description, ?int $userId = null): bool {
    try {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

        $sql = "INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) 
                VALUES (:user_id, :action, :description, :ip, :user_agent)";
        
        Database::query($sql, [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'ip' => $ip,
            'user_agent' => substr($userAgent, 0, 255)
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Failed to write audit log: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if the current user has a specific permission
 *
 * @param string $permissionCode
 * @return bool
 */
function hasPermission(string $permissionCode): bool {
    // Super admins always have full access
    if (($_SESSION['role_code'] ?? '') === ROLE_SUPER_ADMIN) {
        return true;
    }

    $permissions = $_SESSION['permissions'] ?? [];
    return in_array($permissionCode, $permissions);
}

/**
 * Check if the user is authenticated
 *
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Return current logged in user details
 *
 * @return array|null
 */
function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'],
        'role_id' => $_SESSION['role_id'],
        'role_code' => $_SESSION['role_code'],
        'role_name' => $_SESSION['role_name']
    ];
}
