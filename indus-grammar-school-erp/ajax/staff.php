<?php
/**
 * Indus Grammar School ERP - Staff & HR AJAX Handler
 * Version 1.0.0
 */

require_once __DIR__ . '/../config/app.php';

if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
if (!validateCsrf($_POST['csrf_token'] ?? '')) jsonResponse(['success' => false, 'message' => 'Security token expired.'], 403);

$action = $_POST['action'] ?? '';
$staffService = new StaffService();

switch ($action) {

    // ── Save Staff ──
    case 'save_staff':
        AuthMiddleware::requirePermission('hr_manage');
        $id = (int)($_POST['staff_id'] ?? 0);
        $data = [
            'employee_no'     => sanitize($_POST['employee_no'] ?? ''),
            'user_id'         => !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null,
            'first_name'      => sanitize($_POST['first_name'] ?? ''),
            'last_name'       => sanitize($_POST['last_name'] ?? ''),
            'designation'     => sanitize($_POST['designation'] ?? ''),
            'department'      => sanitize($_POST['department'] ?? 'Academic'),
            'phone'           => sanitize($_POST['phone'] ?? ''),
            'email'           => sanitize($_POST['email'] ?? ''),
            'address'         => sanitize($_POST['address'] ?? ''),
            'date_of_joining' => sanitize($_POST['date_of_joining'] ?? ''),
            'salary'          => (float)($_POST['salary'] ?? 0),
            'status'          => sanitize($_POST['status'] ?? 'Active')
        ];
        
        if ($id > 0) {
            $ok = Staff::update($id, $data);
            $msg = $ok ? 'Staff member updated successfully.' : 'Update failed.';
        } else {
            $ok = Staff::create($data);
            $msg = $ok ? 'Staff member registered successfully.' : 'Registration failed.';
        }
        jsonResponse(['success' => (bool)$ok, 'message' => $msg]);
        break;

    // ── Generate Payroll ──
    case 'generate_payroll':
        AuthMiddleware::requirePermission('hr_manage');
        $month = (int)($_POST['month'] ?? 0);
        $year  = (int)($_POST['year'] ?? 0);
        $res   = $staffService->generatePayroll($month, $year);
        jsonResponse(['success' => $res['status'], 'message' => $res['message']]);
        break;

    // ── Pay Salary ──
    case 'pay_salary':
        AuthMiddleware::requirePermission('hr_manage');
        $id = (int)($_POST['salary_id'] ?? 0);
        $paymentDate = sanitize($_POST['payment_date'] ?? date('Y-m-d'));
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'Bank');
        $res = $staffService->paySalary($id, $paymentDate, $paymentMethod);
        jsonResponse(['success' => $res['status'], 'message' => $res['message']]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.']);
}
