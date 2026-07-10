<?php
/**
 * Indus Grammar School ERP - Examination AJAX Handler
 * Version 1.0.0
 */

require_once __DIR__ . '/../config/app.php';

if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
if (!validateCsrf($_POST['csrf_token'] ?? '')) jsonResponse(['success' => false, 'message' => 'Security token expired.'], 403);

$action = $_POST['action'] ?? '';
$examService = new ExamService();

switch ($action) {

    // ── Save Subject ──
    case 'save_subject':
        AuthMiddleware::requirePermission('academic_manage');
        $id = (int)($_POST['subject_id'] ?? 0);
        $data = [
            'class_id'     => (int)($_POST['class_id'] ?? 0),
            'subject_name' => sanitize($_POST['subject_name'] ?? ''),
            'subject_code' => sanitize($_POST['subject_code'] ?? ''),
            'total_marks'  => (int)($_POST['total_marks'] ?? 100),
        ];
        
        if ($id > 0) {
            $ok = Subject::update($id, $data);
            $msg = $ok ? 'Subject updated successfully.' : 'Update failed.';
        } else {
            $ok = Subject::create($data);
            $msg = $ok ? 'Subject created successfully.' : 'Creation failed.';
        }
        jsonResponse(['success' => (bool)$ok, 'message' => $msg]);
        break;

    // ── Delete Subject ──
    case 'delete_subject':
        AuthMiddleware::requirePermission('academic_manage');
        $id = (int)($_POST['id'] ?? 0);
        $ok = Subject::delete($id);
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Subject deleted.' : 'Deletion failed.']);
        break;

    // ── Save Exam Term ──
    case 'save_exam':
        AuthMiddleware::requirePermission('academic_manage');
        $id = (int)($_POST['exam_id'] ?? 0);
        $data = [
            'exam_name'     => sanitize($_POST['exam_name'] ?? ''),
            'start_date'    => sanitize($_POST['start_date'] ?? ''),
            'end_date'      => sanitize($_POST['end_date'] ?? ''),
            'academic_year' => sanitize($_POST['academic_year'] ?? CURRENT_ACADEMIC_YEAR),
        ];
        
        if ($id > 0) {
            $ok = Exam::update($id, $data);
            $msg = $ok ? 'Exam term updated.' : 'Update failed.';
        } else {
            $ok = Exam::create($data);
            $msg = $ok ? 'Exam term created.' : 'Creation failed.';
        }
        jsonResponse(['success' => (bool)$ok, 'message' => $msg]);
        break;

    // ── Delete Exam Term ──
    case 'delete_exam':
        AuthMiddleware::requirePermission('academic_manage');
        $id = (int)($_POST['id'] ?? 0);
        $ok = Exam::delete($id);
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Exam term deleted.' : 'Deletion failed.']);
        break;

    // ── Save Marks ──
    case 'save_marks':
        AuthMiddleware::requirePermission('academic_manage'); // Teacher/Admin
        $examId = (int)($_POST['exam_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $marksData = $_POST['marks'] ?? []; // Format: [student_id => ['marks' => X, 'remarks' => Y, 'status' => Z]]

        if (!is_array($marksData)) {
            jsonResponse(['success' => false, 'message' => 'Invalid marks data format.']);
        }

        $res = $examService->saveClassMarks($examId, $subjectId, $marksData);
        jsonResponse(['success' => $res['status'], 'message' => $res['message']]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.']);
}
