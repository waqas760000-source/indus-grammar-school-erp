<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();

$basePath = dirname(__DIR__);

echo "=== SAMPLE STUDENT RECORDS & PHOTOS ===\n";
$stmt = $db->query("
    SELECT s.id, s.admission_no, s.first_name, s.last_name, s.status, s.academic_type,
           d.roll_no, d.cnic_no, d.doc_student_photo, d.campus, d.academic_session
    FROM students s
    LEFT JOIN student_registration_details d ON s.id = d.student_id
");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($students as $st) {
    echo "ID: {$st['id']} | AdmNo: {$st['admission_no']} | Name: {$st['first_name']} {$st['last_name']} | RollNo: {$st['roll_no']} | CNIC: {$st['cnic_no']} | Photo: {$st['doc_student_photo']}\n";
    if (!empty($st['doc_student_photo'])) {
        $fullPath = $basePath . '/' . ltrim($st['doc_student_photo'], '/\\');
        echo "  -> Exists on disk? " . (file_exists($fullPath) ? "YES ($fullPath)" : "NO ($fullPath)") . "\n";
    }
}

echo "\n=== CHECKING LOGO AND ASSETS ===\n";
$logoPath = $basePath . '/assets/images/logo.png';
echo "Logo path: $logoPath | Exists? " . (file_exists($logoPath) ? "YES ($logoPath)" : "NO ($logoPath)") . "\n";

$defPhoto = $basePath . '/assets/images/default_student.png';
echo "Default photo: $defPhoto | Exists? " . (file_exists($defPhoto) ? "YES ($defPhoto)" : "NO ($defPhoto)") . "\n";
