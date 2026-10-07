<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

try {
    $db = Database::getConnection();

    $classes = $db->query("SELECT * FROM classes")->fetchAll(PDO::FETCH_ASSOC);
    $students = $db->query("SELECT s.id, s.admission_no, s.first_name, s.last_name, s.class_id, s.status, d.roll_no, d.academic_type, d.school_class, d.school_section FROM students s LEFT JOIN student_registration_details d ON s.id = d.student_id")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'classes' => $classes,
        'student_count' => count($students),
        'students' => $students
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
