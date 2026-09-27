<?php
require_once __DIR__ . '/../config/app.php';

$exam = 1;
$student = 1;

$saveOk = ReportCard::saveRemarks([
    'exam_type_id'          => $exam,
    'student_id'            => $student,
    'teacher_remarks'       => 'Outstanding academic performance and exemplary conduct in class.',
    'principal_remarks'     => 'Promoted with honors to the next grade.',
    'attendance_percentage' => 96.5,
    'promotion_status'      => 'Promoted'
]);

echo "Save Remarks Status: " . ($saveOk ? 'Success' : 'Failed') . "\n";

$remarks = ReportCard::find($exam, $student);
echo "Fetched Remarks:\n";
print_r($remarks);
