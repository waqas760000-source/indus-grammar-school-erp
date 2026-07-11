<?php
/**
 * Indus Grammar School ERP - Dashboard AJAX Controller
 * Version 1.0.0
 */

require_once __DIR__ . '/../config/app.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$range = sanitize($_GET['range'] ?? $_POST['range'] ?? 'This Month');

switch ($action) {
    case 'get_analytics':
        // Retrieve dynamic metrics based on user role
        $role = currentUserRole();
        $data = [];

        if ($role === ROLE_SUPER_ADMIN || $role === ROLE_SCHOOL_ADMIN) {
            $data['admissionsTrend'] = Report::getAdmissionsTrend($range);
            $data['attendanceTrend'] = Report::getAttendanceTrend($range);
            $data['genderDistribution'] = Report::getGenderDistribution();
            $data['classwiseEnrollment'] = Report::getClasswiseEnrollment();
        }

        if ($role === ROLE_SUPER_ADMIN || $role === ROLE_ACCOUNTANT) {
            $data['feeCollectionsTrend'] = Report::getFeeCollectionsTrend($range);
            $data['incomeExpensesTrend'] = Report::getIncomeExpensesTrend($range);
        }

        jsonResponse(['success' => true, 'data' => $data]);
        break;

    case 'mark_all_read':
        // Simulates marking alerts as read (saving state to database or session)
        $_SESSION['notifications_read_time'] = time();
        jsonResponse(['success' => true, 'message' => 'All notifications marked as read.']);
        break;

    case 'diary_history':
        $diaryId = (int)($_GET['diary_id'] ?? 0);
        if ($diaryId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid diary ID.'], 400);
        }
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT h.*, u.username 
                FROM diary_edit_history h
                JOIN users u ON h.edited_by = u.id
                WHERE h.diary_id = ?
                ORDER BY h.edited_at DESC
            ");
            $stmt->execute([$diaryId]);
            $logs = $stmt->fetchAll();
            jsonResponse(['success' => true, 'logs' => $logs]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action.']);
}
