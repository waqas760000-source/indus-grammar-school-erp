<?php
/**
 * Indus Grammar School ERP - Student Report Card
 * Version 1.0.0
 */

$pageTitle = 'Report Card';
$breadcrumbActive = 'Examination';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('academic_view');

$exams = Exam::all();
$classes = SchoolClass::all();

$selectedExam  = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedStudent = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

$students = [];
$reportData = [];
$studentInfo = null;

if ($selectedClass > 0) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, first_name, last_name, admission_no FROM students WHERE class_id = :cid AND status = 'Active' ORDER BY first_name ASC");
        $stmt->execute(['cid' => $selectedClass]);
        $students = $stmt->fetchAll();
    } catch (Exception $e) {}
}

if ($selectedExam > 0 && $selectedStudent > 0) {
    $studentInfo = Student::findById($selectedStudent);
    $reportData  = Result::getStudentMarks($selectedExam, $selectedStudent);
}

// Calculate totals
$grandTotalMax = 0;
$grandTotalObt = 0;
$allPresent = true;
foreach ($reportData as $row) {
    $grandTotalMax += $row['total_marks'];
    if ($row['status'] === 'Present') {
        $grandTotalObt += $row['marks_obtained'];
    } else {
        $allPresent = false;
    }
}
$overallPercentage = $grandTotalMax > 0 ? ($grandTotalObt / $grandTotalMax) * 100 : 0;
$overallGrading = ExamService::getGrading($overallPercentage);
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-graduation-cap me-2 text-primary"></i>Student Report Card</h3>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Select Exam</label>
                <select class="form-select" name="exam_id" onchange="document.getElementById('filterForm').submit()">
                    <option value="">— Select Exam —</option>
                    <?php foreach ($exams as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo ($selectedExam == $e['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($e['exam_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Select Class</label>
                <select class="form-select" name="class_id" onchange="document.getElementById('filterForm').submit()">
                    <option value="">— Select Class —</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($selectedClass == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Select Student</label>
                <select class="form-select" name="student_id" onchange="document.getElementById('filterForm').submit()" <?php echo empty($students) ? 'disabled' : ''; ?>>
                    <option value="">— Select Student —</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo ($selectedStudent == $s['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['admission_no'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedExam > 0 && $selectedStudent > 0 && $studentInfo): ?>
    
    <!-- Action Bar for Printing -->
    <div class="text-end mb-3">
        <button class="btn btn-outline-primary" onclick="window.print()">
            <i class="fa-solid fa-print me-2"></i>Print Report Card
        </button>
    </div>

    <!-- Report Card View (Printable) -->
    <div class="card border-0 shadow" style="border-radius:0; border-top: 5px solid var(--primary-color) !important;" id="reportCardPrintArea">
        <div class="card-body p-5">
            <!-- Header -->
            <div class="text-center mb-5 pb-3 border-bottom">
                <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h2>
                <p class="text-muted mb-3">Excellence in Education</p>
                <h4 class="fw-semibold text-secondary text-uppercase border d-inline-block px-4 py-2 bg-light">
                    <?php
                        $exInfo = array_filter($exams, fn($e) => $e['id'] == $selectedExam);
                        $exInfo = reset($exInfo);
                        echo sanitize($exInfo['exam_name'] . ' - ' . $exInfo['academic_year']);
                    ?>
                </h4>
            </div>

            <!-- Student Info -->
            <div class="row mb-5 g-4">
                <div class="col-sm-6">
                    <table class="table table-borderless table-sm mb-0">
                        <tr><td class="text-muted fw-semibold" width="120">Student Name:</td><td class="fw-bold fs-5"><?php echo sanitize($studentInfo['first_name'] . ' ' . $studentInfo['last_name']); ?></td></tr>
                        <tr><td class="text-muted fw-semibold">Admission No:</td><td class="fw-semibold text-dark"><?php echo sanitize($studentInfo['admission_no']); ?></td></tr>
                        <tr><td class="text-muted fw-semibold">Date of Birth:</td><td><?php echo date('d M Y', strtotime($studentInfo['date_of_birth'])); ?></td></tr>
                    </table>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <table class="table table-borderless table-sm mb-0 ms-auto text-sm-end" style="width: auto;">
                        <tr><td class="text-muted fw-semibold text-start">Class:</td><td class="fw-bold text-end">
                            <?php 
                                $cInfo = SchoolClass::findById($studentInfo['class_id']); 
                                echo sanitize($cInfo['class_name'] . ' - ' . $cInfo['section']); 
                            ?>
                        </td></tr>
                        <tr><td class="text-muted fw-semibold text-start">Issue Date:</td><td class="text-end"><?php echo date('d M Y'); ?></td></tr>
                    </table>
                </div>
            </div>

            <!-- Marks Table -->
            <div class="table-responsive mb-5">
                <table class="table table-bordered border-dark custom-table mb-0" style="border-width: 2px;">
                    <thead class="table-light border-dark">
                        <tr>
                            <th>Subject</th>
                            <th class="text-center" width="15%">Total Marks</th>
                            <th class="text-center" width="15%">Obtained</th>
                            <th class="text-center" width="15%">Percentage</th>
                            <th class="text-center" width="15%">Grade</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="border-dark">
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="6" class="text-center py-4">No marks recorded for this exam yet.</td></tr>
                        <?php else: foreach ($reportData as $row): 
                            $isAbsent = $row['status'] !== 'Present';
                            $pct = $isAbsent ? 0 : ($row['total_marks'] > 0 ? ($row['marks_obtained'] / $row['total_marks']) * 100 : 0);
                            $grading = ExamService::getGrading($pct);
                        ?>
                            <tr>
                                <td class="fw-semibold text-dark"><?php echo sanitize($row['subject_name']); ?></td>
                                <td class="text-center"><?php echo $row['total_marks']; ?></td>
                                <td class="text-center fw-bold <?php echo $isAbsent ? 'text-danger' : ''; ?>">
                                    <?php echo $isAbsent ? sanitize($row['status']) : $row['marks_obtained']; ?>
                                </td>
                                <td class="text-center"><?php echo $isAbsent ? '-' : round($pct, 1) . '%'; ?></td>
                                <td class="text-center fw-bold <?php echo $isAbsent ? 'text-danger' : ($grading['grade'] === 'F' ? 'text-danger' : 'text-success'); ?>">
                                    <?php echo $isAbsent ? '-' : $grading['grade']; ?>
                                </td>
                                <td class="text-muted small"><?php echo sanitize($row['remarks']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <?php if (!empty($reportData)): ?>
                    <tfoot class="table-light border-dark fw-bold">
                        <tr>
                            <td class="text-end text-uppercase">Grand Total</td>
                            <td class="text-center fs-5"><?php echo $grandTotalMax; ?></td>
                            <td class="text-center fs-5 text-primary"><?php echo $grandTotalObt; ?></td>
                            <td class="text-center fs-5"><?php echo round($overallPercentage, 1); ?>%</td>
                            <td class="text-center fs-5 text-<?php echo $overallGrading['grade'] === 'F' ? 'danger' : 'success'; ?>"><?php echo $overallGrading['grade']; ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Signatures -->
            <div class="row mt-5 pt-5 text-center">
                <div class="col-4">
                    <hr class="border-dark mx-auto" style="width:70%; opacity:1;">
                    <div class="fw-semibold">Class Teacher</div>
                </div>
                <div class="col-4">
                    <hr class="border-dark mx-auto" style="width:70%; opacity:1;">
                    <div class="fw-semibold">Principal</div>
                </div>
                <div class="col-4">
                    <hr class="border-dark mx-auto" style="width:70%; opacity:1;">
                    <div class="fw-semibold">Parent / Guardian</div>
                </div>
            </div>
            
            <div class="mt-4 pt-3 border-top text-center small text-muted">
                <strong>Grading Scale:</strong>
                A+ (80% & Above) &nbsp;|&nbsp; A (70% - 79%) &nbsp;|&nbsp; B (60% - 69%) &nbsp;|&nbsp; C (50% - 59%) &nbsp;|&nbsp; D (40% - 49%) &nbsp;|&nbsp; F (Below 40%)
            </div>
        </div>
    </div>
    
    <!-- Print Styles -->
    <style>
        @media print {
            body * { visibility: hidden; }
            #reportCardPrintArea, #reportCardPrintArea * { visibility: visible; }
            #reportCardPrintArea { position: absolute; left: 0; top: 0; width: 100%; border: none !important; box-shadow: none !important; }
            .card-body { padding: 0 !important; }
            .table-light { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; }
            .text-primary, .text-success, .text-danger { color: #000 !important; }
        }
    </style>

<?php endif; ?>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
