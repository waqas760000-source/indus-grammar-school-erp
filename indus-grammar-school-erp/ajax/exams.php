<?php
/**
 * Indus Grammar School ERP - Examination AJAX Handler
 * Version 4.0.0
 */

require_once __DIR__ . '/../config/app.php';

// Force authentication
if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
if (!validateCsrf($_POST['csrf_token'] ?? '')) jsonResponse(['success' => false, 'message' => 'Security token expired.'], 403);

// Verify permission
$userRole = $_SESSION['role_code'] ?? '';
$isTeacherOrAdmin = in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'teacher', 'exam_controller']);
if (!$isTeacherOrAdmin) {
    jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
}

$action = $_POST['action'] ?? '';
$db = Database::getConnection();

switch ($action) {

    // ── 1. EXAM TYPES CRUD ────────────────────────────────────
    case 'save_exam':
        $id = (int)($_POST['exam_id'] ?? 0);
        $data = [
            'exam_name'          => sanitize($_POST['exam_name'] ?? ''),
            'academic_session'   => sanitize($_POST['academic_session'] ?? CURRENT_ACADEMIC_YEAR),
            'academic_type'      => sanitize($_POST['academic_type'] ?? 'School'),
            'start_date'         => sanitize($_POST['start_date'] ?? ''),
            'end_date'           => sanitize($_POST['end_date'] ?? ''),
            'total_marks'        => (int)($_POST['total_marks'] ?? 100),
            'passing_percentage' => (float)($_POST['passing_percentage'] ?? 40.00),
            'status'             => sanitize($_POST['status'] ?? 'Active')
        ];

        if (empty($data['exam_name']) || empty($data['start_date']) || empty($data['end_date'])) {
            jsonResponse(['success' => false, 'message' => 'Mandatory fields are required.'], 400);
        }

        if ($id > 0) {
            $ok = ExamType::update($id, $data);
            $msg = $ok ? 'Exam term successfully updated.' : 'Failed to update exam term.';
        } else {
            $ok = ExamType::create($data);
            $msg = $ok ? 'Exam term successfully created.' : 'Failed to create exam term.';
        }
        jsonResponse(['success' => (bool)$ok, 'message' => $msg]);
        break;

    case 'delete_exam':
        $id = (int)($_POST['id'] ?? 0);
        $ok = ExamType::delete($id);
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Exam term deleted.' : 'Deletion failed.']);
        break;


    // ── 2. EXAM SCHEDULE CRUD ─────────────────────────────────
    case 'save_exam_schedule':
        $id = (int)($_POST['schedule_id'] ?? 0);
        $data = [
            'exam_type_id' => (int)($_POST['exam_type_id'] ?? 0),
            'class_id'     => (int)($_POST['class_id'] ?? 0),
            'subject_id'   => (int)($_POST['subject_id'] ?? 0),
            'exam_date'    => sanitize($_POST['exam_date'] ?? ''),
            'start_time'   => sanitize($_POST['start_time'] ?? ''),
            'end_time'     => sanitize($_POST['end_time'] ?? ''),
            'room'         => sanitize($_POST['room'] ?? ''),
            'supervisor'   => sanitize($_POST['supervisor'] ?? '')
        ];

        if ($data['exam_type_id'] <= 0 || $data['class_id'] <= 0 || $data['subject_id'] <= 0 || empty($data['exam_date'])) {
            jsonResponse(['success' => false, 'message' => 'Mandatory fields are required.'], 400);
        }

        if ($id > 0) {
            $ok = ExamSchedule::update($id, $data);
            $msg = $ok ? 'Exam schedule updated.' : 'Failed to update schedule.';
        } else {
            $ok = ExamSchedule::create($data);
            $msg = $ok ? 'Exam schedule logged.' : 'Failed to create schedule.';
        }
        jsonResponse(['success' => (bool)$ok, 'message' => $msg]);
        break;

    case 'delete_exam_schedule':
        $id = (int)($_POST['id'] ?? 0);
        $ok = ExamSchedule::delete($id);
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Exam schedule deleted.' : 'Deletion failed.']);
        break;


    // ── 3. SUBJECTS CRUD ──────────────────────────────────────
    case 'save_subject':
        $id = (int)($_POST['subject_id'] ?? 0);
        $data = [
            'class_id'      => (int)($_POST['class_id'] ?? 0),
            'subject_name'  => sanitize($_POST['subject_name'] ?? ''),
            'subject_code'  => sanitize($_POST['subject_code'] ?? ''),
            'total_marks'   => (int)($_POST['total_marks'] ?? 100),
            'passing_marks' => (int)($_POST['passing_marks'] ?? 40),
            'academic_type' => sanitize($_POST['academic_type'] ?? 'School'),
            'teacher_id'    => (int)($_POST['teacher_id'] ?? 0),
            'status'        => sanitize($_POST['status'] ?? 'Active')
        ];

        if ($data['class_id'] <= 0 || empty($data['subject_name'])) {
            jsonResponse(['success' => false, 'message' => 'Mandatory fields are required.'], 400);
        }

        if ($id > 0) {
            $ok = Subject::update($id, $data);
            $msg = $ok ? 'Subject details updated.' : 'Failed to update subject.';
        } else {
            $ok = Subject::create($data);
            $msg = $ok ? 'Subject successfully created and assigned.' : 'Failed to create subject.';
        }
        jsonResponse(['success' => (bool)$ok, 'message' => $msg]);
        break;

    case 'delete_subject':
        $id = (int)($_POST['id'] ?? 0);
        $ok = Subject::delete($id);
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Subject deleted.' : 'Deletion failed.']);
        break;


    // ── 4. MARKS ENTRY SAVE ───────────────────────────────────
    case 'save_student_marks':
        $examTypeId = (int)($_POST['exam_type_id'] ?? 0);
        $subjectId  = (int)($_POST['subject_id'] ?? 0);
        $marksData  = $_POST['marks'] ?? []; // Array formatted as [student_id => [marks => X, remarks => Y, status => Z]]

        if ($examTypeId <= 0 || $subjectId <= 0 || empty($marksData)) {
            jsonResponse(['success' => false, 'message' => 'Invalid exam, subject, or student data.'], 400);
        }

        $subject = Subject::findById($subjectId);
        if (!$subject) {
            jsonResponse(['success' => false, 'message' => 'Subject not found.'], 404);
        }

        $maxMarks = (float)$subject['total_marks'];
        $successCount = 0;

        try {
            $db->beginTransaction();

            foreach ($marksData as $studentId => $row) {
                $status = sanitize($row['status'] ?? 'Present');
                $marksObtained = null;

                if ($status === 'Present') {
                    $marksObtained = (float)($row['marks'] ?? 0.00);
                    if ($marksObtained < 0) $marksObtained = 0.00;
                    if ($marksObtained > $maxMarks) $marksObtained = $maxMarks;
                }

                $ok = StudentMark::saveMarks([
                    'exam_type_id'   => $examTypeId,
                    'student_id'     => $studentId,
                    'subject_id'     => $subjectId,
                    'marks_obtained' => $marksObtained,
                    'remarks'        => sanitize($row['remarks'] ?? ''),
                    'status'         => $status
                ]);

                if ($ok) $successCount++;
            }

            $db->commit();
            auditLog('Marks Entered', "Saved marks for Exam ID $examTypeId, Subject ID $subjectId. Records: $successCount");
            jsonResponse(['success' => true, 'message' => "Marks saved successfully. ($successCount student entries registered)"]);

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;


    // ── 5. GRADE SETUP CRUD ───────────────────────────────────
    case 'save_grade':
        $id = (int)($_POST['grade_id'] ?? 0);
        $data = [
            'grade'          => sanitize($_POST['grade'] ?? ''),
            'min_percentage' => (float)($_POST['min_percentage'] ?? 0.00),
            'max_percentage' => (float)($_POST['max_percentage'] ?? 0.00),
            'grade_point'    => (float)($_POST['grade_point'] ?? 0.00),
            'remarks'        => sanitize($_POST['remarks'] ?? '')
        ];

        if (empty($data['grade'])) {
            jsonResponse(['success' => false, 'message' => 'Grade name is required.'], 400);
        }

        if ($id > 0) {
            $ok = GradeSetup::update($id, $data);
            $msg = $ok ? 'Grade parameters updated.' : 'Failed to update grade.';
        } else {
            $ok = GradeSetup::create($data);
            $msg = $ok ? 'Grade parameters created.' : 'Failed to create grade.';
        }
        jsonResponse(['success' => (bool)$ok, 'message' => $msg]);
        break;

    case 'delete_grade':
        $id = (int)($_POST['id'] ?? 0);
        $ok = GradeSetup::delete($id);
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Grade deleted.' : 'Deletion failed.']);
        break;


    // ── 6. GENERATE RESULTS ───────────────────────────────────
    case 'generate_results':
        $examTypeId = (int)($_POST['exam_type_id'] ?? 0);
        $classId    = (int)($_POST['class_id'] ?? 0);

        if ($examTypeId <= 0 || $classId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Please select an exam term and class.'], 400);
        }

        $res = ExamResult::generateClassResults($examTypeId, $classId);
        jsonResponse($res);
        break;


    // ── 7. REPORT CARD REMARKS ────────────────────────────────
    case 'save_report_remarks':
        $examTypeId = (int)($_POST['exam_type_id'] ?? 0);
        $studentId  = (int)($_POST['student_id'] ?? 0);
        $data = [
            'exam_type_id'          => $examTypeId,
            'student_id'            => $studentId,
            'teacher_remarks'       => sanitize($_POST['teacher_remarks'] ?? ''),
            'principal_remarks'     => sanitize($_POST['principal_remarks'] ?? ''),
            'attendance_percentage' => $_POST['attendance_percentage'] !== '' ? (float)$_POST['attendance_percentage'] : '',
            'promotion_status'      => sanitize($_POST['promotion_status'] ?? '')
        ];

        if ($examTypeId <= 0 || $studentId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid parameters.'], 400);
        }

        $ok = ReportCard::saveRemarks($data);
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Observations and remarks saved.' : 'Save failed.']);
        break;


    // ── 9. PROMOTIONS ─────────────────────────────────────────
    case 'promote_students':
        $studentIds  = $_POST['student_ids'] ?? [];
        $fromClassId = (int)($_POST['from_class_id'] ?? 0);
        $toClassId   = (int)($_POST['to_class_id'] ?? 0);
        $session     = sanitize($_POST['academic_session'] ?? CURRENT_ACADEMIC_YEAR);

        if (empty($studentIds)) {
            jsonResponse(['success' => false, 'message' => 'Please select at least one student to promote.'], 400);
        }
        if ($fromClassId <= 0 || $toClassId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Please configure valid class transitions.'], 400);
        }

        $res = Promotion::promoteStudents($studentIds, $fromClassId, $toClassId, $session);
        jsonResponse($res);
        break;

    case 'rollback_promotion':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid ID.'], 400);
        }

        $ok = Promotion::rollBackPromotion($id);
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Promotion rolled back and student reverted.' : 'Rollback failed.']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.']);
}
