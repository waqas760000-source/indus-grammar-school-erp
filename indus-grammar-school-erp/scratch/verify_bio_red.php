<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

echo "=== VERIFYING 9TH BIOLOGY RED STUDENTS ===\n\n";

$stmt = $db->query("SELECT s.id, s.admission_no, s.first_name, s.last_name, s.guardian_name, s.guardian_phone, s.school_class, s.school_section, d.father_name, d.father_cnic FROM students s LEFT JOIN student_registration_details d ON s.id = d.student_id WHERE s.class_id = 12 OR (s.school_class LIKE '%Bio%' AND s.school_section LIKE '%Red%') ORDER BY s.id ASC");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total Students Count: " . count($students) . "\n\n";
printf("%-6s | %-10s | %-16s | %-16s | %-15s | %-10s\n", "ID", "Adm No", "Name", "Father Name", "Guardian Phone", "Section");
echo str_repeat("-", 85) . "\n";

foreach ($students as $st) {
    printf("%-6d | %-10s | %-16s | %-16s | %-15s | %-10s\n", $st['id'], $st['admission_no'], $st['first_name'] . ' ' . $st['last_name'], $st['father_name'], $st['guardian_phone'], $st['school_section']);
}

// Check Tuition Fee field integrity
echo "\n=== CHECKING TUITION FEE FIELD INTEGRITY ===\n";
$tuitionCheck = $db->query("SELECT id, first_name, tuition_fee FROM students LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($tuitionCheck);
