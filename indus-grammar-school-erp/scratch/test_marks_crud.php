<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();

$exam = $db->query("SELECT id FROM exam_types LIMIT 1")->fetchColumn();
$subject = $db->query("SELECT id FROM subjects LIMIT 1")->fetchColumn();
$student = $db->query("SELECT id FROM students WHERE status = 'Active' LIMIT 1")->fetchColumn();

if (!$exam || !$subject || !$student) {
    echo "Prerequisite data missing.\n";
    exit;
}

$saveOk = StudentMark::saveMarks([
    'exam_type_id'   => $exam,
    'student_id'     => $student,
    'subject_id'     => $subject,
    'marks_obtained' => 85.5,
    'remarks'        => 'Excellent performance in test',
    'status'         => 'Present'
]);

echo "Save Marks Status: " . ($saveOk ? 'Success' : 'Failed') . "\n";

$marksList = StudentMark::getStudentMarks($exam, $student);
echo "Fetched " . count($marksList) . " mark records for student ID {$student}.\n";
foreach ($marksList as $m) {
    echo "Subject: {$m['subject_name']} | Score: {$m['marks_obtained']} / {$m['total_marks']} | Status: {$m['status']} | Remarks: {$m['remarks']}\n";
}
