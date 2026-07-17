<?php
/**
 * Indus Grammar School ERP - Report Cards and Observations Console
 * Version 4.0.0
 */

$pageTitle = 'Student Report Card';
$breadcrumbActive = 'Examination';
include_once __DIR__ . '/../../includes/header.php';

// Restricted to Super Admin, School Admin, Exam Controller, Teachers
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'teacher', 'exam_controller'])) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permissions to access the examination module.';
    redirect(APP_URL . '/dashboard.php');
}

$db = Database::getConnection();

// Selectors
$examTypes = $db->query("SELECT * FROM exam_types WHERE status = 'Active' ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$classes   = SchoolClass::all();

$selectedExam = isset($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : 0;
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedStudent = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

$students = [];
$studentInfo = null;
$marks = [];
$resultInfo = null;
$reportRemarks = null;

if ($selectedClass > 0) {
    $stmt = $db->prepare("SELECT id, first_name, last_name, admission_no FROM students WHERE class_id = :cid AND status = 'Active' ORDER BY first_name ASC");
    $stmt->execute(['cid' => $selectedClass]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($selectedExam > 0 && $selectedStudent > 0) {
    $studentInfo = Student::findById($selectedStudent);
    $marks = StudentMark::getStudentMarks($selectedExam, $selectedStudent);
    $resultInfo = ExamResult::getStudentResult($selectedExam, $selectedStudent);
    $reportRemarks = ReportCard::find($selectedExam, $selectedStudent);
}
?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-id-card text-primary me-2"></i>Report Cards</h3>
        <p class="text-muted small mb-0">Record teacher observations, assign attendance rates, and print formal A4 student report cards.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if ($selectedExam > 0 && $selectedStudent > 0 && $studentInfo): ?>
            <button class="btn btn-outline-primary px-4" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Report Card</button>
        <?php endif; ?>
        <a href="dashboard.php" class="btn btn-outline-secondary px-4 ms-2"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Select Exam Term</label>
                <select class="form-select" name="exam_type_id" required onchange="document.getElementById('filterForm').submit()">
                    <option value="">— Choose Exam —</option>
                    <?php foreach ($examTypes as $et): ?>
                        <option value="<?php echo $et['id']; ?>" <?php echo $selectedExam === (int)$et['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($et['exam_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Select Class & Section</label>
                <select class="form-select" name="class_id" required onchange="document.getElementById('filterForm').submit()">
                    <option value="">— Choose Class —</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Select Student</label>
                <select class="form-select" name="student_id" required <?php echo empty($students) ? 'disabled' : ''; ?> onchange="document.getElementById('filterForm').submit()">
                    <option value="">— Select Student —</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $selectedStudent === (int)$s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['admission_no'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-arrows-spin me-2"></i>Reload</button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <!-- Left Column: Observations/Remarks input panel -->
    <?php if ($selectedExam > 0 && $selectedStudent > 0 && $studentInfo): ?>
        <div class="col-xl-4 mb-4 d-print-none">
            <div class="card border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-comment-medical text-primary me-2"></i>Observations & Remarks</h5>
                </div>
                <div class="card-body p-4">
                    <form id="remarksForm">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action" value="save_report_remarks">
                        <input type="hidden" name="exam_type_id" value="<?php echo $selectedExam; ?>">
                        <input type="hidden" name="student_id" value="<?php echo $selectedStudent; ?>">

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Attendance Rate (%)</label>
                            <input type="number" step="0.1" class="form-control" name="attendance_percentage" value="<?php echo $reportRemarks ? htmlspecialchars($reportRemarks['attendance_percentage']) : ''; ?>" placeholder="e.g. 95.5" min="0" max="100">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Teacher Remarks</label>
                            <textarea class="form-control" name="teacher_remarks" rows="3" placeholder="Teacher observations..."><?php echo $reportRemarks ? htmlspecialchars($reportRemarks['teacher_remarks']) : ''; ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Principal Remarks</label>
                            <textarea class="form-control" name="principal_remarks" rows="2" placeholder="Principal remarks..."><?php echo $reportRemarks ? htmlspecialchars($reportRemarks['principal_remarks']) : ''; ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-muted">Promotion Status</label>
                            <select class="form-select" name="promotion_status">
                                <option value="" <?php echo empty($reportRemarks['promotion_status']) ? 'selected' : ''; ?>>-- Under Review --</option>
                                <option value="Promoted" <?php echo ($reportRemarks['promotion_status'] ?? '') === 'Promoted' ? 'selected' : ''; ?>>Promoted</option>
                                <option value="Demoted" <?php echo ($reportRemarks['promotion_status'] ?? '') === 'Demoted' ? 'selected' : ''; ?>>Demoted / Retained</option>
                                <option value="Withdrawn" <?php echo ($reportRemarks['promotion_status'] ?? '') === 'Withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold" id="btnSaveRemarks"><i class="fa-solid fa-circle-check me-2"></i>Save Observations</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Printable A4 Report Card mockup -->
        <div class="col-xl-8 mb-4">
            <div class="card border border-2 shadow" style="border-radius:0; border-top: 6px solid var(--royal-blue) !important; max-width: 800px; margin: 0 auto; background-color: #fff;" id="reportCardPrintArea">
                <div class="card-body p-4 p-md-5">
                    
                    <!-- School Header -->
                    <div class="text-center mb-4 pb-3 border-bottom border-secondary">
                        <h2 class="fw-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif; letter-spacing: 1px;">INDUS GRAMMAR SCHOOL</h2>
                        <div class="text-muted small mb-3">Main Campus, Karachi | Phone: +92 300 1234567</div>
                        
                        <h5 class="fw-bold text-uppercase d-inline-block border border-secondary bg-light px-4 py-2 mt-2" style="letter-spacing: 0.5px; border-radius: 4px;">
                            <?php 
                                $examDetail = array_filter($examTypes, fn($e) => (int)$e['id'] === $selectedExam);
                                $examDetail = reset($examDetail);
                                echo htmlspecialchars($examDetail['exam_name'] . ' — ' . $examDetail['academic_session']);
                            ?>
                        </h5>
                    </div>

                    <!-- Student Metadata -->
                    <div class="row mb-4 g-3">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted small fw-bold" width="130">Student Name:</td>
                                    <td class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($studentInfo['first_name'] . ' ' . $studentInfo['last_name']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted small fw-bold">Admission No:</td>
                                    <td class="fw-bold text-dark"><code class="text-dark fw-bold"><?php echo htmlspecialchars($studentInfo['admission_no']); ?></code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted small fw-bold">Gender / DOB:</td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($studentInfo['gender']); ?> | <?php echo date('d-M-Y', strtotime($studentInfo['date_of_birth'])); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <table class="table table-sm table-borderless mb-0 ms-auto" style="width: auto;">
                                <tr>
                                    <td class="text-muted small fw-bold text-start" width="120">Class & Section:</td>
                                    <td class="fw-bold text-end">
                                        <?php 
                                            $classDetail = array_filter($classes, fn($c) => (int)$c['id'] === $selectedClass);
                                            $classDetail = reset($classDetail);
                                            echo htmlspecialchars($classDetail['class_name'] . ' - ' . $classDetail['section']);
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted small fw-bold text-start">Attendance:</td>
                                    <td class="fw-bold text-end text-primary"><?php echo $reportRemarks && $reportRemarks['attendance_percentage'] ? number_format($reportRemarks['attendance_percentage'], 1) . '%' : '—'; ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted small fw-bold text-start">Date of Issue:</td>
                                    <td class="text-muted small text-end"><?php echo date('d-M-Y'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Marks Breakdown Table -->
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered border-secondary text-center align-middle mb-0">
                            <thead class="table-light border-secondary">
                                <tr>
                                    <th class="text-start">Subject Name</th>
                                    <th width="100">Max Marks</th>
                                    <th width="110">Passing Marks</th>
                                    <th width="120">Obtained Marks</th>
                                    <th width="110">Percentage</th>
                                    <th width="80">Grade</th>
                                    <th width="90">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($marks)): ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted small">No marks entries logged for this student.</td></tr>
                                <?php else: 
                                    $totMax = $totObt = 0;
                                    foreach ($marks as $m): 
                                        $totMax += $m['total_marks'];
                                        $pct = 0.00;
                                        if ($m['status'] === 'Present') {
                                            $totObt += (float)$m['marks_obtained'];
                                            $pct = $m['total_marks'] > 0 ? ((float)$m['marks_obtained'] / $m['total_marks']) * 100 : 0.00;
                                        }
                                        $gInfo = GradeSetup::getGradeByPercentage($pct);
                                        $pass = ($m['status'] === 'Present' && (float)$m['marks_obtained'] >= (float)$m['passing_marks']);
                                    ?>
                                    <tr>
                                        <td class="text-start fw-bold text-dark"><?php echo htmlspecialchars($m['subject_name']); ?></td>
                                        <td class="fw-semibold text-muted"><?php echo $m['total_marks']; ?></td>
                                        <td class="fw-semibold text-danger"><?php echo $m['passing_marks']; ?></td>
                                        <td class="fw-bold text-dark">
                                            <?php 
                                                if ($m['status'] === 'Present') echo number_format($m['marks_obtained'], 1);
                                                else echo '<span class="text-danger">' . $m['status'] . '</span>';
                                            ?>
                                        </td>
                                        <td class="fw-bold text-primary"><?php echo $m['status'] === 'Present' ? number_format($pct, 1) . '%' : '—'; ?></td>
                                        <td class="fw-bold fs-6"><?php echo $m['status'] === 'Present' ? $gInfo['grade'] : '—'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $pass ? 'success' : 'danger'; ?>-soft rounded-pill px-3 py-1 text-xs fw-bold">
                                                <?php echo $pass ? 'Pass' : 'Fail'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                    <!-- Grand Totals Row -->
                                    <tr class="table-light fw-bold border-secondary text-dark">
                                        <td class="text-start">Grand Totals:</td>
                                        <td><?php echo $totMax; ?></td>
                                        <td></td>
                                        <td><?php echo number_format($totObt, 1); ?></td>
                                        <td class="text-primary"><?php echo $totMax > 0 ? number_format(($totObt / $totMax) * 100, 1) . '%' : '0.0%'; ?></td>
                                        <td class="fs-5">
                                            <?php 
                                                $overallPct = $totMax > 0 ? ($totObt / $totMax) * 100 : 0.00;
                                                $ogInfo = GradeSetup::getGradeByPercentage($overallPct);
                                                echo htmlspecialchars($ogInfo['grade']);
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo ($resultInfo && $resultInfo['status'] === 'Pass') ? 'success' : 'danger'; ?>-soft px-3 py-1 rounded-pill fw-bold">
                                                <?php echo $resultInfo ? $resultInfo['status'] : 'Fail'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Rankings and Status Summary -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <div class="p-3 border border-secondary text-center" style="border-radius: 4px; background-color: #fafafa;">
                                <div class="text-muted text-uppercase text-xs fw-bold mb-1">Merit Rank / Position</div>
                                <h4 class="fw-bold text-primary mb-0"><?php echo ($resultInfo && $resultInfo['status'] === 'Pass') ? 'Rank #' . $resultInfo['position'] : '—'; ?></h4>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 border border-secondary text-center" style="border-radius: 4px; background-color: #fafafa;">
                                <div class="text-muted text-uppercase text-xs fw-bold mb-1">Promotion Outcome</div>
                                <h4 class="fw-bold text-<?php echo ($reportRemarks && $reportRemarks['promotion_status'] === 'Promoted') ? 'success' : 'danger'; ?> mb-0">
                                    <?php echo $reportRemarks && $reportRemarks['promotion_status'] ? htmlspecialchars($reportRemarks['promotion_status']) : 'Under Review'; ?>
                                </h4>
                            </div>
                        </div>
                    </div>

                    <!-- Remarks Section -->
                    <div class="row mb-5 border-top border-secondary pt-3">
                        <div class="col-12 mb-3">
                            <div class="small fw-bold text-muted text-uppercase mb-1">Teacher Observations & Comments:</div>
                            <div class="p-3 border border-secondary text-dark" style="min-height: 50px; font-style: italic; background-color: #fafafa; border-radius: 4px;">
                                <?php echo $reportRemarks && $reportRemarks['teacher_remarks'] ? htmlspecialchars($reportRemarks['teacher_remarks']) : '—'; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="small fw-bold text-muted text-uppercase mb-1">Principal Observations & Remarks:</div>
                            <div class="p-3 border border-secondary text-dark" style="min-height: 40px; font-style: italic; background-color: #fafafa; border-radius: 4px;">
                                <?php echo $reportRemarks && $reportRemarks['principal_remarks'] ? htmlspecialchars($reportRemarks['principal_remarks']) : '—'; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Signatures Block -->
                    <div class="row text-center pt-4 g-3">
                        <div class="col-4">
                            <div style="border-top: 1px solid #777; width: 80%; margin: 0 auto;" class="pt-2 text-muted small fw-bold">Class Teacher Signature</div>
                        </div>
                        <div class="col-4">
                            <div style="border-top: 1px solid #777; width: 80%; margin: 0 auto;" class="pt-2 text-muted small fw-bold">Examiner Signature</div>
                        </div>
                        <div class="col-4">
                            <div style="border-top: 1px solid #777; width: 80%; margin: 0 auto;" class="pt-2 text-muted small fw-bold">Principal Signature</div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm" style="border-radius:12px; height: 320px;">
                <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-5">
                    <i class="fa-solid fa-id-card fs-1 text-muted opacity-25 mb-3"></i>
                    <h5 class="text-muted fw-bold">No Report Card Selected</h5>
                    <p class="text-muted small mb-0">Choose an active exam term, class, and student to generate their observation sheets and report cards.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="remarksToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="remarksToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-toast></button>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #reportCardPrintArea, #reportCardPrintArea * {
        visibility: visible;
    }
    #reportCardPrintArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none !important;
        border: 0 !important;
    }
    .table th, .table td {
        border-color: #000 !important;
    }
}
</style>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("remarksToast");
    const m = document.getElementById("remarksToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("remarksForm");
    if(form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSaveRemarks");
            btn.disabled = true; btn.innerHTML = "Saving Remarks...";

            fetch("../../ajax/exams.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        btn.disabled = false; btn.innerHTML = \'<i class=\"fa-solid fa-circle-check me-2\"></i>Save Observations\';
                    }
                })
                .catch(() => {
                    showToast("System error saving observations.", false);
                    btn.disabled = false; btn.innerHTML = \'<i class=\"fa-solid fa-circle-check me-2\"></i>Save Observations\';
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
