<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();

echo "=== SEEDING 8 SUBJECTS & DUMMY MARKS ===\n";

$studentId = 1;
$classId   = 1;

// 1. Ensure Exam Type exists
$stmt = $db->prepare("SELECT id FROM exam_types WHERE id = 1");
$stmt->execute();
$examExists = $stmt->fetch();

if (!$examExists) {
    $db->exec("
        INSERT INTO exam_types (id, exam_name, academic_session, academic_type, start_date, end_date, total_marks, passing_percentage, status)
        VALUES (1, 'Term-1 Progress Examination', '2026-2027', 'School', '2026-09-01', '2026-09-30', 800, 40.00, 'Active')
    ");
    $examTypeId = 1;
} else {
    $examTypeId = 1;
    $db->exec("UPDATE exam_types SET exam_name = 'Term-1 Progress Examination', academic_session = '2026-2027', total_marks = 800 WHERE id = 1");
}

// Ensure exams table has entry for id = 1
try {
    $db->exec("
        INSERT INTO exams (id, exam_name, academic_year, start_date, end_date, is_active)
        VALUES (1, 'Term-1 Progress Examination', '2026-2027', '2026-09-01', '2026-09-30', 1)
        ON DUPLICATE KEY UPDATE exam_name = 'Term-1 Progress Examination', academic_year = '2026-2027'
    ");
} catch (Exception $e) {}

echo "Exam Type ID: $examTypeId\n";

// 2. Define 8 Subjects
$eightSubjects = [
    ['name' => 'English Language', 'code' => 'ENG-101', 'total' => 100, 'pass' => 33, 'marks' => 88.5, 'remarks' => 'Excellent proficiency'],
    ['name' => 'Mathematics', 'code' => 'MATH-101', 'total' => 100, 'pass' => 33, 'marks' => 94.0, 'remarks' => 'Outstanding problem solving'],
    ['name' => 'General Science (EVS)', 'code' => 'SCI-101', 'total' => 100, 'pass' => 33, 'marks' => 82.0, 'remarks' => 'Very good scientific grasp'],
    ['name' => 'Urdu Literature', 'code' => 'URD-101', 'total' => 100, 'pass' => 33, 'marks' => 78.5, 'remarks' => 'Good comprehension'],
    ['name' => 'Islamiyat & Ethics', 'code' => 'ISL-101', 'total' => 100, 'pass' => 33, 'marks' => 91.0, 'remarks' => 'Commendable performance'],
    ['name' => 'Computer Science & IT', 'code' => 'CS-101', 'total' => 100, 'pass' => 33, 'marks' => 89.0, 'remarks' => 'Strong practical skills'],
    ['name' => 'Social Studies (History & Geo)', 'code' => 'SST-101', 'total' => 100, 'pass' => 33, 'marks' => 84.5, 'remarks' => 'Good historical analytical skills'],
    ['name' => 'General Knowledge', 'code' => 'GK-101', 'total' => 100, 'pass' => 33, 'marks' => 93.0, 'remarks' => 'Superior awareness']
];

$subjectIds = [];
foreach ($eightSubjects as $sub) {
    // Check if subject exists
    $stmt = $db->prepare("SELECT id FROM subjects WHERE class_id = :cid AND subject_name = :sname");
    $stmt->execute(['cid' => $classId, 'sname' => $sub['name']]);
    $subRow = $stmt->fetch();

    if ($subRow) {
        $subId = $subRow['id'];
        $stmtUp = $db->prepare("UPDATE subjects SET subject_code = :code, total_marks = :tot, passing_marks = :pass WHERE id = :id");
        $stmtUp->execute(['code' => $sub['code'], 'tot' => $sub['total'], 'pass' => $sub['pass'], 'id' => $subId]);
    } else {
        $stmtIns = $db->prepare("INSERT INTO subjects (subject_name, subject_code, class_id, total_marks, passing_marks, academic_type, status) VALUES (:name, :code, :cid, :tot, :pass, 'School', 'Active')");
        $stmtIns->execute(['name' => $sub['name'], 'code' => $sub['code'], 'cid' => $classId, 'tot' => $sub['total'], 'pass' => $sub['pass']]);
        $subId = $db->lastInsertId();
    }

    // Insert or update mark in student_marks
    $stmtMark = $db->prepare("
        INSERT INTO student_marks (exam_type_id, student_id, subject_id, marks_obtained, remarks, status)
        VALUES (:etid, :sid, :subid, :marks, :remarks, 'Present')
        ON DUPLICATE KEY UPDATE marks_obtained = :marks2, remarks = :remarks2, status = 'Present'
    ");
    $stmtMark->execute([
        'etid' => $examTypeId,
        'sid' => $studentId,
        'subid' => $subId,
        'marks' => $sub['marks'],
        'remarks' => $sub['remarks'],
        'marks2' => $sub['marks'],
        'remarks2' => $sub['remarks']
    ]);

    // Also update results table for compatibility
    try {
        $stmtRes = $db->prepare("
            INSERT INTO results (exam_id, student_id, subject_id, marks_obtained, remarks, status)
            VALUES (:eid, :sid, :subid, :marks, :remarks, 'Present')
            ON DUPLICATE KEY UPDATE marks_obtained = :marks2, remarks = :remarks2, status = 'Present'
        ");
        $stmtRes->execute([
            'eid' => $examTypeId,
            'sid' => $studentId,
            'subid' => $subId,
            'marks' => $sub['marks'],
            'remarks' => $sub['remarks'],
            'marks2' => $sub['marks'],
            'remarks2' => $sub['remarks']
        ]);
    } catch (Exception $e) {}

    echo "  - Added/Updated Subject: {$sub['name']} (Mark: {$sub['marks']})\n";
}

// 3. Save Report Card Observations & Remarks
ReportCard::saveRemarks([
    'exam_type_id' => $examTypeId,
    'student_id' => $studentId,
    'attendance_percentage' => 96.5,
    'teacher_remarks' => 'Outstanding academic performance and exemplary conduct throughout the term.',
    'principal_remarks' => 'Promoted with distinction to the next grade level.',
    'promotion_status' => 'Promoted'
]);

echo "\n8 Subjects & Dummy Marks Seeded Successfully!\n";
