<?php
/**
 * Indus Grammar School ERP - Reports AJAX Handler
 * Version 1.0.0
 */

require_once __DIR__ . '/../config/app.php';

if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
if (!validateCsrf($_POST['csrf_token'] ?? '')) jsonResponse(['success' => false, 'message' => 'Security token expired.'], 403);

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_attendance_report':
        AuthMiddleware::requirePermission('report_view');
        $from = sanitize($_POST['from'] ?? date('Y-m-01'));
        $to   = sanitize($_POST['to'] ?? date('Y-m-d'));
        $stats = Report::getAttendanceStats($from, $to);
        jsonResponse(['success' => true, 'data' => $stats]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown report action.']);
}
