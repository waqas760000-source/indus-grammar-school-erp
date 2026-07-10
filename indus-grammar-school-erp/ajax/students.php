<?php
/**
 * Indus Grammar School ERP - AJAX Student Actions Handler
 * Version 1.0.0
 */

// Boot application
require_once __DIR__ . '/../config/app.php';

// Check authentication
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized access. Please log in.'], 401);
}

// Force POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// Verify CSRF Token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrf($csrfToken)) {
    jsonResponse(['success' => false, 'message' => 'Security token expired. Please refresh page.'], 403);
}

// Parse request action
$action = $_POST['action'] ?? '';
$studentService = new StudentService();

switch ($action) {
    case 'create':
        if (!hasPermission('student_create')) {
            jsonResponse(['success' => false, 'message' => 'Permission denied.']);
        }
        
        $input = [
            'first_name' => sanitize($_POST['first_name'] ?? ''),
            'last_name' => sanitize($_POST['last_name'] ?? ''),
            'gender' => sanitize($_POST['gender'] ?? ''),
            'date_of_birth' => sanitize($_POST['date_of_birth'] ?? ''),
            'enrollment_date' => sanitize($_POST['enrollment_date'] ?? ''),
            'class_id' => (int)($_POST['class_id'] ?? 0),
            'guardian_name' => sanitize($_POST['guardian_name'] ?? ''),
            'guardian_phone' => sanitize($_POST['guardian_phone'] ?? ''),
            'guardian_email' => sanitize($_POST['guardian_email'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'status' => sanitize($_POST['status'] ?? 'Active')
        ];
        
        $result = $studentService->registerStudent($input);
        if ($result['status'] === true) {
            jsonResponse(['success' => true, 'message' => $result['message'], 'id' => $result['id']]);
        } else {
            jsonResponse(['success' => false, 'message' => $result['message']]);
        }
        break;

    case 'update':
        if (!hasPermission('student_edit')) {
            jsonResponse(['success' => false, 'message' => 'Permission denied.']);
        }
        
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid Student ID.']);
        }
        
        $input = [
            'first_name' => sanitize($_POST['first_name'] ?? ''),
            'last_name' => sanitize($_POST['last_name'] ?? ''),
            'gender' => sanitize($_POST['gender'] ?? ''),
            'date_of_birth' => sanitize($_POST['date_of_birth'] ?? ''),
            'enrollment_date' => sanitize($_POST['enrollment_date'] ?? ''),
            'class_id' => (int)($_POST['class_id'] ?? 0),
            'guardian_name' => sanitize($_POST['guardian_name'] ?? ''),
            'guardian_phone' => sanitize($_POST['guardian_phone'] ?? ''),
            'guardian_email' => sanitize($_POST['guardian_email'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'status' => sanitize($_POST['status'] ?? 'Active')
        ];
        
        $result = $studentService->updateStudent($id, $input);
        if ($result['status'] === true) {
            jsonResponse(['success' => true, 'message' => $result['message']]);
        } else {
            jsonResponse(['success' => false, 'message' => $result['message']]);
        }
        break;

    case 'delete':
        if (!hasPermission('student_delete')) {
            jsonResponse(['success' => false, 'message' => 'Permission denied.']);
        }
        
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid Student ID.']);
        }
        
        $result = $studentService->deleteStudent($id);
        if ($result['status'] === true) {
            jsonResponse(['success' => true, 'message' => $result['message']]);
        } else {
            jsonResponse(['success' => false, 'message' => $result['message']]);
        }
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action requested.']);
        break;
}
