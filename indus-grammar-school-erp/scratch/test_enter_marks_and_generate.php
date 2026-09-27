<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();
$exam = 1;
$class = 3;

$subjects = $db->query("SELECT id, subject_name, total_marks, passing_marks FROM subjects WHERE class_id = {$class} AND status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
$students = $db->query("SELECT id, first_name, last_name FROM students WHERE class_id = {$class} AND status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);

echo "Subjects Count: " . count($subjects) . " | Students Count: " . count($students) . "\n";

// Student 1 (Ayesha): All high marks (90+ out of 100) -> Should get A+ (PASS)
// Student 2 (Waqas ADM-0004): Good marks (75+ out of 100) -> Should get B (PASS)
// Student 3 (Waqas ADM-0006): Low marks (30 out of 100) -> Should get F (FAIL)

$mockScores = [
    $students[0]['id'] => [92, 95, 88, 94],
    $students[1]['id'] => [78, 75, 72, 80],
    $students[2]['id'] => [30, 25, 40, 35]
];

foreach ($students as $sIdx => $st) {
    $sid = $st['id'];
    $scores = $mockScores[$sid] ?? [50, 50, 50, 50];
    foreach ($subjects as $subIdx => $sub) {
        $subId = $sub['id'];
        $score = $scores[$subIdx];
        StudentMark::saveMarks([
            'exam_type_id'   => $exam,
            'student_id'     => $sid,
            'subject_id'     => $subId,
            'marks_obtained' => $score,
            'remarks'        => 'Evaluated',
            'status'         => 'Present'
        ]);
    }
}

echo "Entered test marks for all 3 students.\n";

// Now run result generation
$genRes = ExamResult::generateClassResults($exam, $class);
echo "Generate Result Output: " . json_encode($genRes) . "\n\n";

$compiledResults = ExamResult::getClassResults($exam, $class);
foreach ($compiledResults as $r) {
    echo "Rank #{$r['position']} | Student: {$r['first_name']} {$r['last_name']} ({$r['admission_no']}) | Total Obt: {$r['obtained_marks']} / {$r['total_marks']} | Pct: {$r['percentage']}% | Grade: {$r['grade']} | Overall Status: {$r['status']}\n";
}
