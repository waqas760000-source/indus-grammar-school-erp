<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['email'] = 'admin@indusgrammar.edu.pk';
$_SESSION['role_id'] = 1;
$_SESSION['role_code'] = 'super_admin';
$_SESSION['role_name'] = 'Super Administrator';

require_once __DIR__ . '/../config/app.php';

echo "Testing Database Queries for Report Card...\n";
$db = Database::getConnection();

$examTypes = $db->query("SELECT id FROM exam_types LIMIT 1")->fetchAll();
$students  = $db->query("SELECT id, class_id FROM students WHERE status='Active' LIMIT 1")->fetchAll();

if (empty($examTypes) || empty($students)) {
    die("No sample exam types or active students found to test.\n");
}

$examId    = $examTypes[0]['id'];
$studentId = $students[0]['id'];
$classId   = $students[0]['class_id'];

$sInfo = Student::findById($studentId);
echo "Student Photo Path: " . ($sInfo['doc_student_photo'] ?? 'NONE') . "\n";

$_GET = [
    'exam_type_id' => $examId,
    'class_id' => $classId,
    'student_id' => $studentId
];

ob_start();
try {
    include __DIR__ . '/../modules/examination/report_cards.php';
    $out = ob_get_clean();
    echo "modules/examination/report_cards.php rendered successfully! Length: " . strlen($out) . " bytes.\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "ERROR in report_cards.php: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

$_GET = [
    'student_id' => $studentId,
    'exam_type_id' => $examId
];

ob_start();
try {
    include __DIR__ . '/../templates/report_card.php';
    $out2 = ob_get_clean();
    echo "templates/report_card.php rendered successfully! Length: " . strlen($out2) . " bytes.\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "ERROR in templates/report_card.php: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
