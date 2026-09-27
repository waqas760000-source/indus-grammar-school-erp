<?php
require_once __DIR__ . '/../config/app.php';

$exam = 1;
$class = 3;

$results = ExamResult::getClassResults($exam, $class);
echo "Fetched " . count($results) . " result ledger records for Exam ID {$exam}, Class ID {$class}.\n";

foreach ($results as $r) {
    echo "Rank #{$r['position']} | Student: {$r['first_name']} {$r['last_name']} ({$r['admission_no']}) | Total Marks: {$r['total_marks']} | Obtained: {$r['obtained_marks']} | Percentage: {$r['percentage']}% | Grade: {$r['grade']} | Status: {$r['status']}\n";
}
