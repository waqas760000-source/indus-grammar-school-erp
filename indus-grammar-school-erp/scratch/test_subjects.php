<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();
echo "Subjects Count: " . $db->query("SELECT COUNT(*) FROM subjects")->fetchColumn() . "\n";

$subjects = Subject::all();
foreach ($subjects as $s) {
    echo "ID: {$s['id']} | Name: {$s['subject_name']} | Code: {$s['subject_code']} | Class: {$s['class_name']} {$s['section']} | Max: {$s['total_marks']} | Pass: {$s['passing_marks']} | Teacher: " . ($s['teacher_first'] ? $s['teacher_first'] . ' ' . $s['teacher_last'] : 'Unassigned') . "\n";
}
