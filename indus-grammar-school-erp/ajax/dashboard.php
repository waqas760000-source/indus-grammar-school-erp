<?php
/**
 * Indus Grammar School ERP - Dashboard & User AJAX Handler
 * Version 1.0.0
 */

require_once __DIR__ . '/../config/app.php';

if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
if (!validateCsrf($_POST['csrf_token'] ?? '')) jsonResponse(['success' => false, 'message' => 'Security token expired.'], 403);

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'create_user':
        AuthMiddleware::requirePermission('system_settings');
        
        $username = sanitize($_POST['username'] ?? '');
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId   = (int)($_POST['role_id'] ?? 0);

        if (empty($username) || empty($email) || empty($password) || $roleId <= 0) {
            jsonResponse(['success' => false, 'message' => 'All fields are required.']);
        }

        // Verify if username/email already exists
        if (User::findByUsernameOrEmail($username) || User::findByUsernameOrEmail($email)) {
            jsonResponse(['success' => false, 'message' => 'Username or Email already registered.']);
        }

        $userId = User::create([
            'username'  => $username,
            'email'     => $email,
            'password'  => $password,
            'role_id'   => $roleId,
            'is_active' => 1
        ]);

        if ($userId) {
            auditLog('User Created', "Created system user: $username (ID: $userId)");
            jsonResponse(['success' => true, 'message' => 'User created successfully.']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Failed to create user.']);
        }
        break;

    case 'toggle_user_status':
        AuthMiddleware::requirePermission('system_settings');
        
        $userId   = (int)($_POST['user_id'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 0);

        if ($userId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid user ID.']);
        }

        if ($userId === (int)$_SESSION['user_id']) {
            jsonResponse(['success' => false, 'message' => 'You cannot change your own status.']);
        }

        $ok = User::updateStatus($userId, $isActive);

        if ($ok) {
            $statusText = $isActive ? 'enabled' : 'disabled';
            auditLog('User Status Toggle', "User ID $userId has been $statusText.");
            jsonResponse(['success' => true, 'message' => "User status successfully updated to $statusText."]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Failed to update status.']);
        }
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown dashboard action.']);
}
