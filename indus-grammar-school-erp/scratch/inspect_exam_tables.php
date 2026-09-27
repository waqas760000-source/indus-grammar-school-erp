<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

$tables = ['exam_types', 'exam_schedule', 'exam_results', 'student_marks', 'grade_setup', 'promotions'];

foreach ($tables as $t) {
    echo "=== Table: $t ===\n";
    try {
        $stmt = $db->query("DESCRIBE $t");
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
