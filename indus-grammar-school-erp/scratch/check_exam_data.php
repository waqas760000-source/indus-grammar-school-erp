<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();

echo "=== EXAM TYPES ===\n";
$examTypes = $db->query("SELECT * FROM exam_types")->fetchAll(PDO::FETCH_ASSOC);
print_r($examTypes);

echo "\n=== STUDENT 1 INFO ===\n";
$s = $db->query("SELECT * FROM students WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
print_r($s);

echo "\n=== SUBJECTS FOR CLASS {$s['class_id']} ===\n";
$subs = $db->query("SELECT * FROM subjects WHERE class_id = " . (int)$s['class_id'])->fetchAll(PDO::FETCH_ASSOC);
print_r($subs);
