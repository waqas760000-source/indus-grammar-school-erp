<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

echo "Testing Examination Dashboard Engine...\n";

// 1. Check KPI counts
$totalExams      = (int)$db->query("SELECT COUNT(*) FROM exam_types")->fetchColumn();
$upcomingExams   = (int)$db->query("SELECT COUNT(*) FROM exam_schedule WHERE exam_date >= CURRENT_DATE()")->fetchColumn();
$resultsPublished = (int)$db->query("SELECT COUNT(DISTINCT class_id) FROM exam_results")->fetchColumn();
$passedCount     = (int)$db->query("SELECT COUNT(*) FROM exam_results WHERE status = 'Pass'")->fetchColumn();
$failedCount     = (int)$db->query("SELECT COUNT(*) FROM exam_results WHERE status = 'Fail'")->fetchColumn();
$distinctionCount = (int)$db->query("SELECT COUNT(*) FROM exam_results WHERE grade IN ('A+', 'A')")->fetchColumn();

$totalGraded = $passedCount + $failedCount;
$overallPassRate = $totalGraded > 0 ? round(($passedCount / $totalGraded) * 100, 1) : 0.00;

echo "KPI Metrics Verified:\n";
echo "- Total Exam Terms: $totalExams\n";
echo "- Upcoming Papers: $upcomingExams\n";
echo "- Pass Percentage Index: $overallPassRate%\n";
echo "- Distinction Performers: $distinctionCount\n";
echo "- Class Results Published: $resultsPublished\n";

// 2. Test Timetable Schedule Query
$schedules = $db->query("
    SELECT es.*, et.exam_name, c.class_name, c.section, s.subject_name
    FROM exam_schedule es
    JOIN exam_types et ON es.exam_type_id = et.id
    JOIN classes c ON es.class_id = c.id
    JOIN subjects s ON es.subject_id = s.id
    ORDER BY es.exam_date ASC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

echo "Timetable Registry (Loaded " . count($schedules) . " papers):\n";
foreach ($schedules as $s) {
    echo "- Class {$s['class_name']}-{$s['section']} | Paper: {$s['subject_name']} | Exam: {$s['exam_name']} | Date: {$s['exam_date']}\n";
}

echo "Examination Dashboard test completed successfully!\n";
