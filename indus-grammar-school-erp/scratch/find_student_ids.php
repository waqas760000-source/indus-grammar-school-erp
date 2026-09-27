<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();
$students = $db->query("SELECT id, first_name, last_name, admission_no FROM students LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($students);
