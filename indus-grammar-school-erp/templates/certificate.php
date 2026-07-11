<?php
/**
 * Indus Grammar School ERP - Character / Leaving Certificate Template
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
    <title>Certificate - <?php echo htmlspecialchars($student['first_name']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; font-family: 'Georgia', serif; }
        .cert-container { width: 850px; height: 600px; background-color: #fff; border: 15px double #b58d3d; padding: 40px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.15); position: relative; }
        .school-title { font-size: 2.2rem; font-weight: 700; color: #1e3a8a; letter-spacing: 1px; }
        .cert-subtitle { font-size: 1.1rem; text-transform: uppercase; color: #b58d3d; letter-spacing: 3px; font-weight: bold; margin-bottom: 30px; }
        .cert-heading { font-size: 2.6rem; font-family: 'Georgia', serif; font-style: italic; color: #111827; margin-bottom: 25px; }
        .cert-body { font-size: 1.15rem; line-height: 1.8; color: #374151; padding: 0 40px; }
        .student-highlight { font-weight: bold; text-decoration: underline; color: #111827; }
        .footer-sigs { display: flex; justify-content: space-between; margin-top: 60px; padding: 0 60px; }
        .sig-line { width: 180px; border-top: 1px solid #777; padding-top: 8px; font-size: 0.9rem; font-weight: bold; color: #555; }
        @media print {
            body { background: #fff; }
            .btn-print { display: none; }
            .cert-container { box-shadow: none !important; margin: 0; border: 15px double #b58d3d; }
        }
    </style>
</head>
<body>

<div class="position-absolute top-0 end-0 p-4 btn-print">
    <button onclick="window.print()" class="btn btn-primary shadow-sm"><i class="fa-solid fa-print me-2"></i>Print Certificate</button>
</div>

<div class="cert-container">
    <div class="school-title">INDUS GRAMMAR SCHOOL</div>
    <div class="cert-subtitle">Karachi, Pakistan</div>
    
    <div class="cert-heading">Certificate of Achievement</div>
    
    <div class="cert-body">
        This is to certify that <span class="student-highlight"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>, 
        son/daughter of <span class="fw-bold"><?php echo htmlspecialchars($student['guardian_name'] ?: 'N/A'); ?></span>, 
        bearing Admission No: <span class="fw-semibold"><?php echo htmlspecialchars($student['admission_no']); ?></span>, 
        has successfully completed studies in <span class="fw-semibold"><?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section']); ?></span> 
        at this institution. 
        <br><br>
        During this period, their conduct has been observed to be exemplary and we wish them success in all future endeavors.
    </div>

    <div class="footer-sigs">
        <div>
            <div class="sig-line">Class Teacher</div>
        </div>
        <div>
            <div class="sig-line">Date of Issue</div>
        </div>
        <div>
            <div class="sig-line">Principal</div>
        </div>
    </div>
</div>

</body>
</html>
