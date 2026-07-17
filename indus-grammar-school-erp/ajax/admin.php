<?php
/**
 * Indus Grammar School ERP - Central Admin AJAX Controller
 * Version 1.0.0
 */

require_once __DIR__ . '/../config/app.php';

// Secure GET download endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'download_backup') {
    if (!isLoggedIn()) {
        die("Unauthorized.");
    }
    AuthMiddleware::requirePermission('system_settings');
    $id = (int)($_GET['id'] ?? 0);
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM backup_history WHERE id = ?");
        $stmt->execute([$id]);
        $backup = $stmt->fetch();
        if ($backup && file_exists(DIR_ROOT . '/' . $backup['filepath'])) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($backup['backup_name']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize(DIR_ROOT . '/' . $backup['filepath']));
            readfile(DIR_ROOT . '/' . $backup['filepath']);
            exit;
        }
    } catch (Exception $e) {}
    die("Backup file not found.");
}

// Secure GET audit logs export
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'export_audit_logs') {
    if (!isLoggedIn()) {
        die("Unauthorized.");
    }
    AuthMiddleware::requirePermission('system_settings');
    
    $filterAction = sanitize($_GET['action_filter'] ?? '');
    $filterUser   = sanitize($_GET['user_filter'] ?? '');
    $fromDate     = sanitize($_GET['from_date'] ?? '');
    $toDate       = sanitize($_GET['to_date'] ?? '');
    
    try {
        $db = Database::getConnection();
        $sql = "SELECT a.id, u.username, r.name as role_name, a.action, a.description, a.ip_address, a.created_at
                FROM audit_logs a 
                LEFT JOIN users u ON a.user_id = u.id 
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE 1=1";
        $params = [];
        if ($filterAction !== '') {
            $sql .= " AND a.action LIKE :act";
            $params['act'] = '%' . $filterAction . '%';
        }
        if ($filterUser !== '') {
            $sql .= " AND u.username LIKE :usr";
            $params['usr'] = '%' . $filterUser . '%';
        }
        if ($fromDate !== '') {
            $sql .= " AND DATE(a.created_at) >= :from";
            $params['from'] = $fromDate;
        }
        if ($toDate !== '') {
            $sql .= " AND DATE(a.created_at) <= :to";
            $params['to'] = $toDate;
        }
        $sql .= " ORDER BY a.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit_logs_export_' . date('Ymd_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Username', 'Role', 'Action', 'Description', 'IP Address', 'Timestamp']);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    } catch (Exception $e) {
        die("Error exporting logs: " . $e->getMessage());
    }
}

// Authentication and Authorization Checks
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Security token expired.'], 403);
}

AuthMiddleware::requirePermission('system_settings');

$action = $_POST['action'] ?? '';
$db = Database::getConnection();

switch ($action) {

    // ─────────────────────────────────────────────────────────────────────────
    // USER MANAGEMENT CRUD
    // ─────────────────────────────────────────────────────────────────────────

    case 'create_user':
        $username = sanitize($_POST['username'] ?? '');
        $email    = sanitize($_POST['email'] ?? '');
        $fullName = sanitize($_POST['full_name'] ?? '');
        $mobileNo = sanitize($_POST['mobile_no'] ?? '');
        $roleId   = (int)($_POST['role_id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $isActive = (int)($_POST['is_active'] ?? 1);

        if (empty($username) || empty($email) || empty($password) || $roleId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Username, Email, Password, and Role are required.']);
        }

        // Verify if username/email already exists
        $chk = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $chk->execute([$username, $email]);
        if ($chk->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Username or Email is already registered.']);
        }

        // Handle Profile Photo Upload
        $photoPath = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = DIR_STORAGE . '/users';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $fileName = 'user_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadDir . '/' . $fileName)) {
                $photoPath = 'storage/users/' . $fileName;
            }
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password_hash, role_id, is_active, full_name, mobile_no, profile_photo)
                VALUES (:username, :email, :pass, :role, :active, :name, :mobile, :photo)
            ");
            $ok = $stmt->execute([
                'username' => $username,
                'email'    => $email,
                'pass'     => password_hash($password, PASSWORD_DEFAULT),
                'role'     => $roleId,
                'active'   => $isActive,
                'name'     => $fullName,
                'mobile'   => $mobileNo,
                'photo'    => $photoPath
            ]);

            if ($ok) {
                auditLog('User Created', "Created system user: $username");
                jsonResponse(['success' => true, 'message' => 'User created successfully.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        jsonResponse(['success' => false, 'message' => 'Failed to create user.']);
        break;

    case 'edit_user':
        $id       = (int)($_POST['id'] ?? 0);
        $email    = sanitize($_POST['email'] ?? '');
        $fullName = sanitize($_POST['full_name'] ?? '');
        $mobileNo = sanitize($_POST['mobile_no'] ?? '');
        $roleId   = (int)($_POST['role_id'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 1);
        $password = $_POST['password'] ?? '';

        if ($id <= 0 || empty($email) || $roleId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Email and Role are required.']);
        }

        // Handle Profile Photo Upload
        $photoQuery = "";
        $photoParam = [];
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = DIR_STORAGE . '/users';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $fileName = 'user_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadDir . '/' . $fileName)) {
                $photoQuery = ", profile_photo = :photo";
                $photoParam['photo'] = 'storage/users/' . $fileName;
            }
        }

        // Password change logic
        $passQuery = "";
        $passParam = [];
        if (!empty($password)) {
            $passQuery = ", password_hash = :pass";
            $passParam['pass'] = password_hash($password, PASSWORD_DEFAULT);
        }

        try {
            $sql = "UPDATE users SET email = :email, full_name = :name, mobile_no = :mobile, role_id = :role, is_active = :active $photoQuery $passQuery WHERE id = :id";
            $stmt = $db->prepare($sql);
            
            $binds = array_merge([
                'email'  => $email,
                'name'   => $fullName,
                'mobile' => $mobileNo,
                'role'   => $roleId,
                'active' => $isActive,
                'id'     => $id
            ], $photoParam, $passParam);

            if ($stmt->execute($binds)) {
                auditLog('User Updated', "Updated user profile details for ID: $id");
                jsonResponse(['success' => true, 'message' => 'User updated successfully.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        jsonResponse(['success' => false, 'message' => 'Failed to update user.']);
        break;

    case 'delete_user':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid User ID.']);
        }
        
        // Prevent disabling Super Admin ID 1
        if ($id === 1) {
            jsonResponse(['success' => false, 'message' => 'The system root Super Admin account cannot be disabled or deleted.']);
        }

        try {
            // Automation rule: Mark account as inactive, do not delete related rows
            $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            if ($stmt->execute([$id])) {
                auditLog('User Archived', "Marked user ID $id as Inactive.");
                jsonResponse(['success' => true, 'message' => 'User successfully archived (marked as Inactive).']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'reset_password':
        $id = (int)($_POST['id'] ?? 0);
        $password = $_POST['password'] ?? '';
        if ($id <= 0 || empty($password)) {
            jsonResponse(['success' => false, 'message' => 'Invalid parameters.']);
        }
        try {
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id])) {
                auditLog('Password Reset', "Password reset for user ID: $id");
                jsonResponse(['success' => true, 'message' => 'Password reset successfully.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'toggle_user_status':
        $id = (int)($_POST['user_id'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid User ID.']);
        }
        if ($id === 1) {
            jsonResponse(['success' => false, 'message' => 'The root Super Admin cannot be disabled.']);
        }
        try {
            $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            if ($stmt->execute([$isActive, $id])) {
                $statusStr = $isActive ? 'Enabled' : 'Disabled';
                auditLog('User Status Toggled', "Toggled user ID $id status to: $statusStr");
                jsonResponse(['success' => true, 'message' => "User status successfully toggled to $statusStr."]);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;


    // ─────────────────────────────────────────────────────────────────────────
    // ROLE MANAGEMENT CRUD
    // ─────────────────────────────────────────────────────────────────────────

    case 'add_role':
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $code = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name));

        if (empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Role Name is required.']);
        }
        try {
            $stmt = $db->prepare("INSERT INTO roles (name, code, description) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $code, $description])) {
                auditLog('Role Created', "Created role: $name");
                jsonResponse(['success' => true, 'message' => 'Role created successfully.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'edit_role':
        $id = (int)($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        if ($id <= 0 || empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Invalid inputs.']);
        }
        try {
            $stmt = $db->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
            if ($stmt->execute([$name, $description, $id])) {
                auditLog('Role Updated', "Updated role ID: $id");
                jsonResponse(['success' => true, 'message' => 'Role updated successfully.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_role':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 3) {
            jsonResponse(['success' => false, 'message' => 'Core system roles cannot be deleted.']);
        }
        try {
            $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
            if ($stmt->execute([$id])) {
                auditLog('Role Deleted', "Deleted role ID: $id");
                jsonResponse(['success' => true, 'message' => 'Role deleted successfully.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;


    // ─────────────────────────────────────────────────────────────────────────
    // SCHOOL SETTINGS MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────

    case 'save_school_settings':
        $schoolName      = sanitize($_POST['school_name'] ?? '');
        $schoolAddress   = sanitize($_POST['school_address'] ?? '');
        $city            = sanitize($_POST['city'] ?? '');
        $phoneNumber     = sanitize($_POST['phone_number'] ?? '');
        $whatsappNumber  = sanitize($_POST['whatsapp_number'] ?? '');
        $email           = sanitize($_POST['email'] ?? '');
        $website         = sanitize($_POST['website'] ?? '');
        $principalName   = sanitize($_POST['principal_name'] ?? '');
        $ownerName       = sanitize($_POST['owner_name'] ?? '');
        $regNumber       = sanitize($_POST['registration_number'] ?? '');
        $academicSession = sanitize($_POST['current_academic_session'] ?? '2026-2027');
        $timezone        = sanitize($_POST['timezone'] ?? 'Asia/Karachi');
        $currency        = sanitize($_POST['currency'] ?? 'PKR');
        $dateFormat      = sanitize($_POST['date_format'] ?? 'Y-m-d');

        if (empty($schoolName) || empty($schoolAddress) || empty($phoneNumber) || empty($email)) {
            jsonResponse(['success' => false, 'message' => 'School Name, Address, Phone, and Email are required.']);
        }

        // Handle logo upload
        $logoPath = $_POST['existing_logo'] ?? null;
        if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = DIR_STORAGE . '/logos';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['school_logo']['name'], PATHINFO_EXTENSION);
            $fileName = 'school_logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['school_logo']['tmp_name'], $uploadDir . '/' . $fileName)) {
                $logoPath = 'storage/logos/' . $fileName;
            }
        }

        try {
            $stmt = $db->query("SELECT id FROM school_settings LIMIT 1");
            $id = $stmt->fetchColumn();

            if ($id) {
                $update = $db->prepare("
                    UPDATE school_settings SET 
                        school_name = ?, school_logo = ?, school_address = ?, city = ?, phone_number = ?, 
                        whatsapp_number = ?, email = ?, website = ?, principal_name = ?, owner_name = ?, 
                        registration_number = ?, current_academic_session = ?, timezone = ?, currency = ?, date_format = ?
                    WHERE id = ?
                ");
                $ok = $update->execute([
                    $schoolName, $logoPath, $schoolAddress, $city, $phoneNumber,
                    $whatsappNumber, $email, $website, $principalName, $ownerName,
                    $regNumber, $academicSession, $timezone, $currency, $dateFormat, $id
                ]);
            } else {
                $insert = $db->prepare("
                    INSERT INTO school_settings 
                        (school_name, school_logo, school_address, city, phone_number, whatsapp_number, email, website, principal_name, owner_name, registration_number, current_academic_session, timezone, currency, date_format)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ok = $insert->execute([
                    $schoolName, $logoPath, $schoolAddress, $city, $phoneNumber,
                    $whatsappNumber, $email, $website, $principalName, $ownerName,
                    $regNumber, $academicSession, $timezone, $currency, $dateFormat
                ]);
            }

            if ($ok) {
                auditLog('School Settings Saved', "Updated school configuration parameters.");
                jsonResponse(['success' => true, 'message' => 'School settings saved successfully.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        jsonResponse(['success' => false, 'message' => 'Failed to save settings.']);
        break;


    // ─────────────────────────────────────────────────────────────────────────
    // ACADEMIC SETTINGS CRUD
    // ─────────────────────────────────────────────────────────────────────────

    // --- Academic Sessions ---
    case 'add_session':
        $name = sanitize($_POST['session_name'] ?? '');
        if (empty($name)) jsonResponse(['success' => false, 'message' => 'Session Name is required.']);
        try {
            $stmt = $db->prepare("INSERT INTO academic_sessions (session_name) VALUES (?)");
            if ($stmt->execute([$name])) {
                auditLog('Session Added', "Added academic session: $name");
                jsonResponse(['success' => true, 'message' => 'Academic session added.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'toggle_session':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid session.']);
        try {
            $db->beginTransaction();
            // Reset all sessions to inactive
            $db->exec("UPDATE academic_sessions SET is_active = 0");
            // Set target session to active
            $stmt = $db->prepare("UPDATE academic_sessions SET is_active = 1 WHERE id = ?");
            $stmt->execute([$id]);
            $db->commit();
            auditLog('Session Activated', "Set academic session ID $id as current active session");
            jsonResponse(['success' => true, 'message' => 'Active session updated.']);
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_session':
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $db->prepare("DELETE FROM academic_sessions WHERE id = ? AND is_active = 0");
            if ($stmt->execute([$id])) {
                auditLog('Session Deleted', "Deleted inactive academic session ID: $id");
                jsonResponse(['success' => true, 'message' => 'Session deleted successfully.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    // --- Classes & Sections ---
    case 'add_class':
        $name = sanitize($_POST['class_name'] ?? '');
        $section = sanitize($_POST['section'] ?? 'A');
        if (empty($name)) jsonResponse(['success' => false, 'message' => 'Class Name is required.']);
        try {
            $stmt = $db->prepare("INSERT INTO classes (class_name, section) VALUES (?, ?)");
            if ($stmt->execute([$name, $section])) {
                auditLog('Class Created', "Created class-section pair: $name - $section");
                jsonResponse(['success' => true, 'message' => 'Class added successfully.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_class':
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $db->prepare("DELETE FROM classes WHERE id = ?");
            if ($stmt->execute([$id])) {
                auditLog('Class Deleted', "Deleted class-section ID: $id");
                jsonResponse(['success' => true, 'message' => 'Class deleted successfully.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    // --- Subjects ---
    case 'add_subject':
        $name = sanitize($_POST['subject_name'] ?? '');
        $code = sanitize($_POST['subject_code'] ?? '');
        $classId = (int)($_POST['class_id'] ?? 0);
        $marks = (int)($_POST['total_marks'] ?? 100);

        if (empty($name) || empty($code) || $classId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Subject Name, Code, and Class are required.']);
        }
        try {
            $stmt = $db->prepare("INSERT INTO subjects (subject_name, subject_code, class_id, total_marks) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$name, $code, $classId, $marks])) {
                auditLog('Subject Added', "Added subject: $name ($code) for class ID $classId");
                jsonResponse(['success' => true, 'message' => 'Subject added.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_subject':
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $db->prepare("DELETE FROM subjects WHERE id = ?");
            if ($stmt->execute([$id])) {
                auditLog('Subject Deleted', "Deleted subject ID: $id");
                jsonResponse(['success' => true, 'message' => 'Subject deleted successfully.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    // --- Departments ---
    case 'add_department':
        $name = sanitize($_POST['name'] ?? '');
        $code = sanitize($_POST['code'] ?? '');
        if (empty($name) || empty($code)) jsonResponse(['success' => false, 'message' => 'Fields are required.']);
        try {
            $stmt = $db->prepare("INSERT INTO departments (name, code) VALUES (?, ?)");
            if ($stmt->execute([$name, $code])) {
                auditLog('Department Added', "Added department: $name");
                jsonResponse(['success' => true, 'message' => 'Department added successfully.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_department':
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
            if ($stmt->execute([$id])) {
                auditLog('Department Deleted', "Deleted department ID: $id");
                jsonResponse(['success' => true, 'message' => 'Department deleted.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    // --- School Houses ---
    case 'add_house':
        $name = sanitize($_POST['name'] ?? '');
        $color = sanitize($_POST['house_color'] ?? '#000000');
        if (empty($name)) jsonResponse(['success' => false, 'message' => 'House Name is required.']);
        try {
            $stmt = $db->prepare("INSERT INTO school_houses (name, house_color) VALUES (?, ?)");
            if ($stmt->execute([$name, $color])) {
                auditLog('House Added', "Added school house: $name");
                jsonResponse(['success' => true, 'message' => 'School house added.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_house':
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $db->prepare("DELETE FROM school_houses WHERE id = ?");
            if ($stmt->execute([$id])) {
                auditLog('House Deleted', "Deleted school house ID: $id");
                jsonResponse(['success' => true, 'message' => 'House deleted.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;


    // ─────────────────────────────────────────────────────────────────────────
    // BACKUP & RESTORE
    // ─────────────────────────────────────────────────────────────────────────

    case 'create_backup':
        $backupDir = DIR_STORAGE . '/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }
        
        $filename = DB_NAME . '_backup_' . date('Ymd_His') . '.sql';
        $filepath = $backupDir . '/' . $filename;

        try {
            $tables = [];
            $result = $db->query("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
            $sqlDump = "-- Indus Grammar School ERP SQL Backup\n";
            $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sqlDump .= "-- Database: " . DB_NAME . "\n\n";
            $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                // Ignore backup_history to avoid backup growth loops
                if ($table === 'backup_history') continue;

                $createTableStmt = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
                $sqlDump .= "\n\n" . $createTableStmt['Create Table'] . ";\n\n";
                
                $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $keys = array_keys($row);
                        $escapedKeys = array_map(fn($k) => "`$k`", $keys);
                        $values = array_values($row);
                        $escapedValues = array_map(function($v) use ($db) {
                            if ($v === null) return 'NULL';
                            return $db->quote($v);
                        }, $values);
                        
                        $sqlDump .= "INSERT INTO `$table` (" . implode(', ', $escapedKeys) . ") VALUES (" . implode(', ', $escapedValues) . ");\n";
                    }
                }
            }
            $sqlDump .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
            
            file_put_contents($filepath, $sqlDump);
            $sizeKb = round(filesize($filepath) / 1024, 2);

            // Register in backup_history
            $ins = $db->prepare("INSERT INTO backup_history (backup_name, filepath, size_kb, created_by) VALUES (?, ?, ?, ?)");
            $ins->execute([$filename, 'storage/backups/' . $filename, $sizeKb, $_SESSION['user_id']]);

            auditLog('Database Backup Created', "Backup exported successfully: $filename");
            jsonResponse(['success' => true, 'message' => "Backup generated successfully: $filename ($sizeKb KB)"]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Backup export failed: ' . $e->getMessage()]);
        }
        break;

    case 'delete_backup':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid backup ID.']);
        try {
            $stmt = $db->prepare("SELECT * FROM backup_history WHERE id = ?");
            $stmt->execute([$id]);
            $backup = $stmt->fetch();
            if ($backup) {
                $absPath = DIR_ROOT . '/' . $backup['filepath'];
                if (file_exists($absPath)) {
                    unlink($absPath);
                }
                $del = $db->prepare("DELETE FROM backup_history WHERE id = ?");
                $del->execute([$id]);
                auditLog('Backup Deleted', "Deleted backup archive: {$backup['backup_name']}");
                jsonResponse(['success' => true, 'message' => 'Backup file deleted successfully.']);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    case 'restore_backup':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid backup ID.']);
        try {
            $stmt = $db->prepare("SELECT * FROM backup_history WHERE id = ?");
            $stmt->execute([$id]);
            $backup = $stmt->fetch();
            if (!$backup) {
                jsonResponse(['success' => false, 'message' => 'Backup record not found in system directory.']);
            }

            $absPath = DIR_ROOT . '/' . $backup['filepath'];
            if (!file_exists($absPath)) {
                jsonResponse(['success' => false, 'message' => 'Physical SQL file not found on disk.']);
            }

            $sql = file_get_contents($absPath);
            
            // Execute backup restoration commands
            $db->exec("SET FOREIGN_KEY_CHECKS=0;");
            $db->exec($sql);
            $db->exec("SET FOREIGN_KEY_CHECKS=1;");
            
            auditLog('Database Restored', "Restored database snapshot from: {$backup['backup_name']}");
            jsonResponse(['success' => true, 'message' => 'Database successfully restored to the target snapshot.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Restoration failed: ' . $e->getMessage()]);
        }
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid or unknown action parameters.']);
        break;
}
