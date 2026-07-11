<?php
/**
 * Indus Grammar School ERP - Printable Student ID Card
 * Version 1.0.0
 */

require_once __DIR__ . '/../config/app.php';
AuthMiddleware::requireLogin();

$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if ($studentId <= 0) {
    die("Student ID required.");
}

try {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT s.*, c.class_name, c.section FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = :sid");
    $stmt->execute(['sid' => $studentId]);
    $student = $stmt->fetch();
} catch (Exception $e) {
    die("Database error.");
}

if (!$student) {
    die("Student not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student ID Card - <?php echo htmlspecialchars($student['first_name']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; font-family: 'Outfit', sans-serif; }
        .id-card { width: 320px; height: 480px; background-color: #fff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden; position: relative; border: 1px solid #e5e7eb; }
        .card-header-banner { height: 120px; background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); padding: 20px; text-align: center; color: #fff; }
        .school-name { font-size: 1.1rem; font-weight: 700; letter-spacing: 0.5px; }
        .avatar-container { width: 100px; height: 100px; border-radius: 50%; border: 4px solid #fff; background-color: #f3f4f6; margin: -50px auto 15px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        .avatar-placeholder { font-size: 2.5rem; color: #9ca3af; font-weight: bold; }
        .details-container { padding: 0 25px 25px; text-align: center; }
        .student-name { font-size: 1.3rem; font-weight: 700; color: #1f2937; margin-bottom: 2px; }
        .role-badge { display: inline-block; background-color: #e0e7ff; color: #4f46e5; font-size: 0.75rem; font-weight: 600; padding: 4px 12px; border-radius: 50px; margin-bottom: 20px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.85rem; border-bottom: 1px solid #f3f4f6; padding-bottom: 6px; }
        .info-label { color: #6b7280; font-weight: 500; }
        .info-value { color: #1f2937; font-weight: 600; }
        .card-footer-banner { position: absolute; bottom: 0; left: 0; width: 100%; height: 35px; background-color: #1f2937; color: #fff; text-align: center; line-height: 35px; font-size: 0.75rem; font-weight: 500; }
        @media print {
            body { background: #fff; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>

<div class="position-absolute top-0 end-0 p-4 btn-print">
    <button onclick="window.print()" class="btn btn-primary shadow-sm">Print ID Card</button>
</div>

<div class="id-card">
    <div class="card-header-banner">
        <div class="school-name">INDUS GRAMMAR SCHOOL</div>
        <div style="font-size: 0.7rem; opacity: 0.8;">Karachi, Pakistan</div>
    </div>
    
    <div class="avatar-container shadow-sm">
        <div class="avatar-placeholder">
            <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
        </div>
    </div>

    <div class="details-container">
        <div class="student-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
        <span class="role-badge">STUDENT</span>

        <div class="info-row">
            <span class="info-label">Admission No:</span>
            <span class="info-value"><?php echo htmlspecialchars($student['admission_no']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Class:</span>
            <span class="info-value"><?php echo htmlspecialchars(($student['class_name'] ?? $student['school_class'] ?? '-') . ' - ' . ($student['section'] ?? $student['school_section'] ?? 'A')); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Guardian Phone:</span>
            <span class="info-value"><?php echo htmlspecialchars($student['guardian_phone'] ?: '-'); ?></span>
        </div>
    </div>

    <div class="card-footer-banner">
        ACADEMIC SESSION <?php echo CURRENT_ACADEMIC_YEAR; ?>
    </div>
</div>

</body>
</html>
