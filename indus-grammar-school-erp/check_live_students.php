<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

try {
    $db = Database::getConnection();
    
    // Fetch all classes
    $classesStmt = $db->query("SELECT id, class_name, section FROM classes");
    $classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all students
    $stmt = $db->query("SELECT id, admission_no, first_name, last_name, school_class, school_section FROM students");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'classes' => $classes,
        'student_count' => count($students),
        'students' => $students
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
