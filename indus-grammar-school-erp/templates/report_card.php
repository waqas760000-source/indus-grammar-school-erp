<?php
/**
 * Indus Grammar School ERP - Printable Report Card
 * Version 1.0.0
 */

require_once __DIR__ . '/../config/app.php';
AuthMiddleware::requireLogin();

$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$examId    = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($studentId <= 0 || $examId <= 0) {
    die("Student ID and Exam ID are required.");
}

try {
    $db = Database::getConnection();
    
    // Fetch Student Info
    $stmt = $db->prepare("SELECT s.*, c.class_name, c.section FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = :sid");
    $stmt->execute(['sid' => $studentId]);
    $student = $stmt->fetch();
    
    // Fetch Exam Details
    $stmt = $db->prepare("SELECT * FROM exams WHERE id = :eid");
    $stmt->execute(['eid' => $examId]);
    $exam = $stmt->fetch();
    
    // Fetch Marks/Results
    $stmt = $db->prepare("
        SELECT r.*, s.subject_name, s.subject_code, s.total_marks
        FROM results r
        JOIN subjects s ON r.subject_id = s.id
        WHERE r.exam_id = :eid AND r.student_id = :sid
    ");
    $stmt->execute(['eid' => $examId, 'sid' => $studentId]);
    $results = $stmt->fetchAll();
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

if (!$student || !$exam) {
    die("Student or Exam record not found.");
}

// Calculations
$totalObtained = 0;
$totalPossible = 0;
foreach ($results as $r) {
    if ($r['status'] === 'Present') {
        $totalObtained += (float)$r['marks_obtained'];
    }
    $totalPossible += (int)$r['total_marks'];
}

$percentage = $totalPossible > 0 ? ($totalObtained / $totalPossible) * 100 : 0;
$finalGrade = ExamService::getGrading($percentage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Card - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #fff; padding: 30px; }
        .report-card-container { max-width: 900px; margin: 0 auto; border: 2px solid #333; padding: 50px; }
        @media print {
            body { padding: 0; }
            .report-card-container { border: 2px solid #000; padding: 30px; box-shadow: none !important; }
            .btn-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="text-end mb-4 btn-print">
    <button onclick="window.print()" class="btn btn-primary"><i class="fa-solid fa-print me-2"></i>Print Report Card</button>
</div>

<div class="report-card-container shadow">
    <!-- Header -->
    <div class="text-center mb-5 border-bottom pb-4">
        <h2 class="fw-bold mb-1">INDUS GRAMMAR SCHOOL</h2>
        <p class="text-muted mb-1">Excellence in Education</p>
        <h4 class="fw-bold text-secondary text-uppercase mt-3">PROGRESS REPORT CARD</h4>
        <h5 class="fw-semibold text-dark"><?php echo htmlspecialchars($exam['exam_name']); ?></h5>
    </div>

    <!-- Student details -->
    <div class="row mb-5 g-3">
        <div class="col-6">
            <table class="table table-sm table-borderless">
                <tr><td class="text-muted" width="130">Student Name:</td><td class="fw-bold fs-5"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td></tr>
                <tr><td class="text-muted">Admission No:</td><td class="fw-semibold"><?php echo htmlspecialchars($student['admission_no']); ?></td></tr>
            </table>
        </div>
        <div class="col-6 text-end">
            <table class="table table-sm table-borderless ms-auto" style="width: auto;">
                <tr>
                    <td class="text-muted text-start" width="130">Class-Section:</td>
                    <td class="fw-bold text-end"><?php echo htmlspecialchars(($student['class_name'] ?? $student['school_class'] ?? '-') . ' - ' . ($student['section'] ?? $student['school_section'] ?? 'A')); ?></td>
                </tr>
                <tr><td class="text-muted text-start">Academic Year:</td><td class="fw-semibold text-end"><?php echo htmlspecialchars($exam['academic_year']); ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Results Table -->
    <table class="table table-bordered mb-5">
        <thead class="table-dark">
            <tr>
                <th>Subject</th>
                <th class="text-center">Code</th>
                <th class="text-end">Total Marks</th>
                <th class="text-end">Marks Obtained</th>
                <th class="text-center">Grade</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($results)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No academic records found for this term.</td></tr>
            <?php else: foreach ($results as $r): 
                $subPct = $r['total_marks'] > 0 ? ($r['marks_obtained'] / $r['total_marks']) * 100 : 0;
                $subGrade = $r['status'] === 'Present' ? ExamService::getGrading($subPct) : 'Absent';
            ?>
                <tr>
                    <td class="fw-semibold"><?php echo htmlspecialchars($r['subject_name']); ?></td>
                    <td class="text-center"><code><?php echo htmlspecialchars($r['subject_code']); ?></code></td>
                    <td class="text-end"><?php echo $r['total_marks']; ?></td>
                    <td class="text-end fw-bold text-primary">
                        <?php echo $r['status'] === 'Present' ? number_format($r['marks_obtained'], 2) : 'A'; ?>
                    </td>
                    <td class="text-center fw-bold"><?php echo $subGrade; ?></td>
                    <td><span class="text-muted small"><?php echo htmlspecialchars($r['remarks'] ?: '-'); ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            <tr class="table-light">
                <td colspan="2" class="fw-bold text-end">Grand Total:</td>
                <td class="text-end fw-bold"><?php echo $totalPossible; ?></td>
                <td class="text-end fw-bold text-success"><?php echo number_format($totalObtained, 2); ?></td>
                <td class="text-center fw-bold text-primary fs-5" colspan="2">Grade: <?php echo $finalGrade; ?> (<?php echo round($percentage, 1); ?>%)</td>
            </tr>
        </tbody>
    </table>

    <!-- Comments & Signatures -->
    <div class="row mt-5 pt-5 text-center">
        <div class="col-4">
            <hr class="border-dark">
            <span class="fw-semibold text-muted">Class Teacher</span>
        </div>
        <div class="col-4">
            <hr class="border-dark">
            <span class="fw-semibold text-muted">Principal</span>
        </div>
        <div class="col-4">
            <hr class="border-dark">
            <span class="fw-semibold text-muted">Parent / Guardian</span>
        </div>
    </div>
</div>

</body>
</html>
