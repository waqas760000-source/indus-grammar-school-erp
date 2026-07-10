<?php
/**
 * Indus Grammar School ERP - AJAX Admission Actions Handler
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
$admissionService = new AdmissionService();

switch ($action) {
    case 'create':
        if (!hasPermission('admission_create')) {
            jsonResponse(['success' => false, 'message' => 'Permission denied.']);
        }
        
        $input = [
            'first_name' => sanitize($_POST['first_name'] ?? ''),
            'last_name' => sanitize($_POST['last_name'] ?? ''),
            'gender' => sanitize($_POST['gender'] ?? ''),
            'date_of_birth' => sanitize($_POST['date_of_birth'] ?? ''),
            'class_id' => (int)($_POST['class_id'] ?? 0),
            'guardian_name' => sanitize($_POST['guardian_name'] ?? ''),
            'guardian_phone' => sanitize($_POST['guardian_phone'] ?? ''),
            'guardian_email' => sanitize($_POST['guardian_email'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'notes' => sanitize($_POST['notes'] ?? '')
        ];
        
        $result = $admissionService->registerApplicant($input);
        if ($result['status'] === true) {
            jsonResponse(['success' => true, 'message' => $result['message'], 'id' => $result['id']]);
        } else {
            jsonResponse(['success' => false, 'message' => $result['message']]);
        }
        break;

    case 'approve':
        // Only Super Admin or School Admin can approve admission to enroll students
        if (!hasPermission('student_create') || !hasPermission('admission_create')) {
            jsonResponse(['success' => false, 'message' => 'Permission denied. Only academic administrators can enroll students.']);
        }
        
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid Application ID.']);
        }
        
        $result = $admissionService->approveApplication($id);
        if ($result['status'] === true) {
            jsonResponse(['success' => true, 'message' => $result['message'], 'student_id' => $result['student_id']]);
        } else {
            jsonResponse(['success' => false, 'message' => $result['message']]);
        }
        break;

    case 'reject':
        if (!hasPermission('admission_create')) {
            jsonResponse(['success' => false, 'message' => 'Permission denied.']);
        }
        
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid Application ID.']);
        }
        
        $notes = sanitize($_POST['notes'] ?? '');
        
        $result = $admissionService->rejectApplication($id, $notes);
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
