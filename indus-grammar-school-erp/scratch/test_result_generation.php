<?php
require_once __DIR__ . '/../config/app.php';

$exam = 1;
$class = 3;

echo "Testing result generation for Exam ID {$exam}, Class ID {$class} (Prep A)...\n";
$res = ExamResult::generateClassResults($exam, $class);
echo "Result generation output: " . json_encode($res) . "\n";

$compiledResults = ExamResult::getClassResults($exam, $class);
echo "Compiled Results Count: " . count($compiledResults) . "\n";
foreach ($compiledResults as $r) {
    echo "Rank #{$r['position']} | Student: {$r['first_name']} {$r['last_name']} ({$r['admission_no']}) | Obt: {$r['obtained_marks']} / {$r['total_marks']} | Pct: {$r['percentage']}% | Grade: {$r['grade']} | Status: {$r['status']}\n";
}
