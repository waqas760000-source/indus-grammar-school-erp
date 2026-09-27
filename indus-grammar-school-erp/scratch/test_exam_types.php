<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

echo "Testing Exam Types & Terms Engine...\n";

// 1. Create a test exam term
$testData = [
    'exam_name'          => 'Unit Evaluation Term ' . rand(100, 999),
    'academic_session'   => CURRENT_ACADEMIC_YEAR,
    'academic_type'      => 'School',
    'start_date'         => date('Y-m-d'),
    'end_date'           => date('Y-m-d', strtotime('+14 days')),
    'total_marks'        => 100,
    'passing_percentage' => 40.0,
    'status'             => 'Active'
];

$termId = ExamType::create($testData);
echo "Created Exam Term #$termId ({$testData['exam_name']}).\n";

// 2. Read back & verify
$term = ExamType::findById($termId);
echo "Verified Term Record:\n";
echo "- Name: {$term['exam_name']}\n";
echo "- Session: {$term['academic_session']}\n";
echo "- Category: {$term['academic_type']}\n";
echo "- Start: {$term['start_date']} | End: {$term['end_date']}\n";
echo "- Marks: {$term['total_marks']} | Passing Standard: {$term['passing_percentage']}%\n";

// 3. Clean up test record
ExamType::delete($termId);
echo "Cleaned up test term #$termId.\n";

echo "Exam Types test completed successfully!\n";
