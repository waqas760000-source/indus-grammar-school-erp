<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

echo "Checking Examination DB Tables...\n";
$tables = ['exam_types', 'exam_schedule', 'exam_results', 'student_marks', 'grade_setup', 'student_promotions'];

foreach ($tables as $t) {
    try {
        $count = (int)$db->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "- Table '$t': $count records\n";
    } catch (Exception $e) {
        echo "- Table '$t': MISSING or Error: " . $e->getMessage() . "\n";
    }
}
