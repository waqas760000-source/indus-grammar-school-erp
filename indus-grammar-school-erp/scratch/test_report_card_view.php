<?php
require_once __DIR__ . '/../config/app.php';

$exam = 1;
$student = 1;

$studentInfo = Student::findById($student);
$marks = StudentMark::getStudentMarks($exam, $student);
$resultInfo = ExamResult::getStudentResult($exam, $student);
$reportRemarks = ReportCard::find($exam, $student);

echo "Student: {$studentInfo['first_name']} {$studentInfo['last_name']} ({$studentInfo['admission_no']})\n";
echo "Loaded Marks Entries: " . count($marks) . "\n";
echo "Exam Result: " . json_encode($resultInfo) . "\n";
echo "Report Remarks: " . json_encode($reportRemarks) . "\n";
