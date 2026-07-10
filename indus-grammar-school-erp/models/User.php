<?php
/**
 * Indus Grammar School ERP - User Model
 * Version 1.0.0
 */

class User {
    
    /**
     * Find user by username or email address
     *
     * @param string $login
     * @return array|bool
     */
    public static function findByUsernameOrEmail(string $login): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT u.*, r.code as role_code, r.name as role_name 
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE (u.username = :login1 OR u.email = :login2)
            ");
            $stmt->execute(['login1' => $login, 'login2' => $login]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("User::findByUsernameOrEmail error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find user by ID
     *
     * @param int $id
     * @return array|bool
     */
    public static function findById(int $id): array|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT u.*, r.code as role_code, r.name as role_name 
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = :id
            ");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("User::findById error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch all permission codes associated with a specific role
     *
     * @param int $roleId
     * @return array
     */
    public static function getPermissions(int $roleId): array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT p.code 
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = :role_id
            ");
            $stmt->execute(['role_id' => $roleId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("User::getPermissions error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update the user's last login timestamp
     *
     * @param int $id
     * @return bool
     */
    public static function updateLastLogin(int $id): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("User::updateLastLogin error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Store a remember-me token
     *
     * @param int $userId
     * @param string $selector
     * @param string $tokenHash
     * @param string $expiresAt Datetime string (Y-m-d H:i:s)
     * @return bool
     */
    public static function createRememberToken(int $userId, string $selector, string $tokenHash, string $expiresAt): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO user_remember_tokens (user_id, selector, token_hash, expires_at)
                VALUES (:user_id, :selector, :token_hash, :expires_at)
            ");
            return $stmt->execute([
                'user_id' => $userId,
                'selector' => $selector,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt
            ]);
        } catch (PDOException $e) {
            error_log("User::createRememberToken error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an active remember-me token by selector
     *
     * @param string $selector
     * @return bool
     */
    public static function deleteRememberToken(string $selector): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM user_remember_tokens WHERE selector = :selector");
            return $stmt->execute(['selector' => $selector]);
        } catch (PDOException $e) {
            error_log("User::deleteRememberToken error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new user account
     *
     * @param array $data
     * @return int|bool
     */
    public static function create(array $data): int|bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password_hash, role_id, is_active)
                VALUES (:username, :email, :password_hash, :role_id, :is_active)
            ");
            $ok = $stmt->execute([
                'username' => sanitize($data['username']),
                'email' => sanitize($data['email']),
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'role_id' => (int)$data['role_id'],
                'is_active' => (int)($data['is_active'] ?? 1)
            ]);
            return $ok ? (int)$db->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log("User::create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user active status
     *
     * @param int $id
     * @param int $isActive
     * @return bool
     */
    public static function updateStatus(int $id, int $isActive): bool {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE users SET is_active = :status WHERE id = :id");
            return $stmt->execute(['status' => $isActive, 'id' => $id]);
        } catch (PDOException $e) {
            error_log("User::updateStatus error: " . $e->getMessage());
            return false;
        }
    }
}

