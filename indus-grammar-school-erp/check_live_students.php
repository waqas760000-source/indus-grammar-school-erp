<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

try {
    $db = Database::getConnection();

    $tables = ['students', 'student_registration_details', 'fee_ledger', 'classes', 'subjects', 'attendance', 'marks', 'users'];
    $counts = [];
    foreach ($tables as $t) {
        try {
            $counts[$t] = (int)$db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        } catch (Exception $e) {
            $counts[$t] = -1;
        }
    }

    $classes = $db->query("SELECT * FROM classes")->fetchAll(PDO::FETCH_ASSOC);
    $students = $db->query("SELECT s.id, s.admission_no, s.first_name, s.last_name, s.class_id, s.status, d.roll_no, d.academic_type, d.school_class, d.school_section FROM students s LEFT JOIN student_registration_details d ON s.id = d.student_id")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'table_counts' => $counts,
        'classes_count' => count($classes),
        'student_count' => count($students),
        'students_sample' => array_slice($students, 0, 10)
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
