<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

try {
    $db = Database::getConnection();

    $classes = $db->query("SELECT * FROM classes")->fetchAll(PDO::FETCH_ASSOC);
    $students = $db->query("SELECT id, admission_no, roll_no, first_name, last_name, class_id, academic_type, school_class, school_section, status FROM students")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'classes' => $classes,
        'student_count' => count($students),
        'students' => $students
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
