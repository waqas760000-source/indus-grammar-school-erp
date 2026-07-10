<?php
/**
 * Indus Grammar School ERP - Attendance AJAX Handler
 * Version 1.0.0
 */

require_once __DIR__ . '/../config/app.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Security token expired. Please refresh.'], 403);
}

$action  = $_POST['action'] ?? '';
$service = new AttendanceService();

switch ($action) {

    // ── Get students list for a class (for populating the marking form) ──
    case 'get_students_by_class':
        AuthMiddleware::requirePermission('attendance_mark');
        $classId = (int)($_POST['class_id'] ?? 0);
        if ($classId <= 0) jsonResponse(['success' => false, 'message' => 'Invalid class.']);

        try {
            $db = Database::getConnection();
            $date = sanitize($_POST['date'] ?? date('Y-m-d'));

            // Get students in class
            $stmt = $db->prepare("
                SELECT s.id, s.first_name, s.last_name, s.admission_no,
                       a.status as current_status, a.remarks as current_remarks
                FROM students s
                LEFT JOIN attendance a ON a.student_id = s.id AND a.date = :date
                WHERE s.class_id = :cid AND s.status = 'Active'
                ORDER BY s.first_name ASC
            ");
            $stmt->execute(['cid' => $classId, 'date' => $date]);
            $students = $stmt->fetchAll();
            jsonResponse(['success' => true, 'students' => $students]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Failed to load students.']);
        }
        break;

    // ── Bulk-mark student attendance for a class ──
    case 'mark_daily':
        AuthMiddleware::requirePermission('attendance_mark');
        $classId = (int)($_POST['class_id'] ?? 0);
        $date    = sanitize($_POST['date'] ?? '');
        $rawRecords = $_POST['records'] ?? [];

        if (!is_array($rawRecords)) jsonResponse(['success' => false, 'message' => 'Invalid records format.']);

        $records = [];
        foreach ($rawRecords as $r) {
            $records[] = [
                'student_id' => (int)($r['student_id'] ?? 0),
                'status'     => sanitize($r['status'] ?? 'Present'),
                'remarks'    => sanitize($r['remarks'] ?? ''),
            ];
        }

        $result = $service->markDailyAttendance($classId, $date, $records);
        jsonResponse(['success' => $result['status'], 'message' => $result['message']]);
        break;

    // ── Mark staff attendance ──
    case 'mark_staff':
        AuthMiddleware::requirePermission('attendance_mark');
        $userId   = (int)($_POST['user_id'] ?? 0);
        $date     = sanitize($_POST['date'] ?? date('Y-m-d'));
        $status   = sanitize($_POST['status'] ?? 'Present');
        $checkIn  = sanitize($_POST['check_in'] ?? '');
        $checkOut = sanitize($_POST['check_out'] ?? '');
        $remarks  = sanitize($_POST['remarks'] ?? '');

        $result = $service->markStaffAttendance($userId, $date, $status, $checkIn ?: null, $checkOut ?: null, $remarks ?: null);
        jsonResponse(['success' => $result['status'], 'message' => $result['message']]);
        break;

    // ── Apply for leave ──
    case 'apply_leave':
        AuthMiddleware::requirePermission('attendance_mark');
        $data = [
            'applicant_type' => sanitize($_POST['applicant_type'] ?? ''),
            'applicant_id'   => (int)($_POST['applicant_id'] ?? 0),
            'leave_type'     => sanitize($_POST['leave_type'] ?? 'Casual'),
            'start_date'     => sanitize($_POST['start_date'] ?? ''),
            'end_date'       => sanitize($_POST['end_date'] ?? ''),
            'reason'         => sanitize($_POST['reason'] ?? ''),
        ];
        $result = $service->applyLeave($data);
        jsonResponse(['success' => $result['status'], 'message' => $result['message']]);
        break;

    // ── Approve / Reject leave ──
    case 'update_leave':
        AuthMiddleware::requirePermission('attendance_mark');
        $id        = (int)($_POST['id'] ?? 0);
        $newStatus = sanitize($_POST['new_status'] ?? '');
        if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid leave ID.']);
        $result = $service->updateLeaveStatus($id, $newStatus);
        jsonResponse(['success' => $result['status'], 'message' => $result['message']]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.']);
}
