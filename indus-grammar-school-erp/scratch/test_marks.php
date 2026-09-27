<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();
echo "Student Marks Count: " . $db->query("SELECT COUNT(*) FROM student_marks")->fetchColumn() . "\n";
echo "Students Count: " . $db->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn() . "\n";

$exam = $db->query("SELECT id FROM exam_types LIMIT 1")->fetchColumn();
$class = $db->query("SELECT id FROM classes LIMIT 1")->fetchColumn();
$subject = $db->query("SELECT id FROM subjects LIMIT 1")->fetchColumn();

if ($exam && $class && $subject) {
    $marks = StudentMark::getClassMarksBySubject($exam, $class, $subject);
    echo "Loaded " . count($marks) . " student marks for Exam ID {$exam}, Class ID {$class}, Subject ID {$subject}.\n";
}
