<?php
/**
 * Indus Grammar School ERP - Marks Evaluator & Grading Console
 * Version 4.0.0
 */

$pageTitle = 'Marks Entry & Evaluation';
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
$examTypes = $db->query("SELECT * FROM exam_types ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$classes   = SchoolClass::all();

$selectedExam         = isset($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : 0;
$selectedClass        = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedSubject      = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$selectedAcademicType = sanitize($_GET['academic_type'] ?? 'School');

$subjectsForClass = [];
$studentsMarks    = [];
$subjectInfo      = null;
$examInfo         = null;

if ($selectedClass > 0) {
    // Filter subjects by class and academic type
    $stmt = $db->prepare("SELECT * FROM subjects WHERE class_id = :cid AND academic_type = :atype AND status = 'Active' ORDER BY subject_name ASC");
    $stmt->execute(['cid' => $selectedClass, 'atype' => $selectedAcademicType]);
    $subjectsForClass = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($selectedExam > 0 && $selectedClass > 0 && $selectedSubject > 0) {
    $studentsMarks = StudentMark::getClassMarksBySubject($selectedExam, $selectedClass, $selectedSubject);
    $subjectInfo   = Subject::findById($selectedSubject);
    
    // Fetch exam info
    foreach ($examTypes as $et) {
        if ((int)$et['id'] === $selectedExam) {
            $examInfo = $et;
            break;
        }
    }
}

// Compute Sheet Metrics
$totalEnrolled  = count($studentsMarks);
$presentCount   = 0;
$absentCount    = 0;
$passedCount    = 0;
$failedCount    = 0;
$marksSum       = 0;
$enteredMarksCount = 0;

$maxMarksVal  = $subjectInfo ? (float)$subjectInfo['total_marks'] : 100;
$passMarksVal = $subjectInfo ? (float)$subjectInfo['passing_marks'] : 40;

if ($studentsMarks && $subjectInfo) {
    foreach ($studentsMarks as $sm) {
        $st = $sm['status'] ?? 'Present';
        if ($st === 'Present' || empty($st)) {
            $presentCount++;
            if ($sm['marks_obtained'] !== null && $sm['marks_obtained'] !== '') {
                $val = (float)$sm['marks_obtained'];
                $marksSum += $val;
                $enteredMarksCount++;
                if ($val >= $passMarksVal) {
                    $passedCount++;
                } else {
                    $failedCount++;
                }
            }
        } else {
            $absentCount++;
        }
    }
}

$classAvgScore = $enteredMarksCount > 0 ? round($marksSum / $enteredMarksCount, 1) : 0;

$activeClassTitle = 'Unselected Class';
if ($selectedClass > 0) {
    foreach ($classes as $c) {
        if ((int)$c['id'] === $selectedClass) {
            $activeClassTitle = $c['class_name'] . ' - ' . $c['section'];
            break;
        }
    }
}
?>

<!-- Custom CSS Styling -->
<style>
.marks-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #4338ca 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 2.2rem 2rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
    border-bottom: 3px solid #6366f1;
    position: relative;
    overflow: hidden;
}
.marks-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
}
.kpi-card-marks {
    border: none;
    border-radius: 14px;
    background: #ffffff;
    transition: all 0.25s ease-in-out;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
}
.kpi-card-marks:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
}
.kpi-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}
.badge-soft-indigo { background-color: rgba(99, 102, 241, 0.12); color: #4f46e5; }
.badge-soft-emerald { background-color: rgba(16, 185, 129, 0.12); color: #059669; }
.badge-soft-rose { background-color: rgba(244, 63, 94, 0.12); color: #e11d48; }
.badge-soft-amber { background-color: rgba(245, 158, 11, 0.12); color: #d97706; }
.badge-soft-cyan { background-color: rgba(6, 182, 212, 0.12); color: #0891b2; }

.table-marks thead th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    font-weight: 700;
    padding: 1rem 0.85rem;
    border-bottom: 2px solid #e2e8f0;
}
.table-marks tbody td {
    padding: 0.85rem;
    vertical-align: middle;
}
.marks-val-input {
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 700;
    text-align: right;
    transition: all 0.2s ease;
}
.marks-val-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
}
.marks-val-input.is-invalid {
    border-color: #ef4444 !important;
    background-color: #fef2f2 !important;
}
@media print {
    body * { visibility: hidden; }
    #marksPrintArea, #marksPrintArea * { visibility: visible; }
    #marksPrintArea {
        position: absolute;
        left: 0; top: 0;
        width: 100%;
        box-shadow: none !important;
        border: none !important;
    }
    .d-print-none { display: none !important; }
    .table-print-clean {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .table-print-clean th, .table-print-clean td {
        border: 1px solid #cbd5e1 !important;
        padding: 8px 12px !important;
        font-size: 11px !important;
    }
}
</style>

<!-- Executive Hero Header Banner -->
<div class="marks-hero mb-4 d-print-none">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-indigo bg-opacity-25 text-indigo px-3 py-1 rounded-pill fw-semibold small" style="color:#a5b4fc;">
                    <i class="fa-solid fa-pen-nib me-1"></i> Marks Evaluator Engine
                </span>
                <span class="badge bg-success bg-opacity-25 text-success px-3 py-1 rounded-pill fw-semibold small" style="color:#6ee7b7;">
                    <i class="fa-solid fa-shield-check me-1"></i> Boundary Validation Active
                </span>
            </div>
            <h2 class="fw-bold text-white mb-2">
                <i class="fa-solid fa-file-pen text-indigo me-2" style="color:#818cf8;"></i>Marks Entry & Student Evaluation
            </h2>
            <p class="text-white-50 mb-0">
                Record and update student obtained scores. Real-time maximum boundary checks and pass/fail indicators are computed live upon typing.
            </p>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                <?php if ($selectedExam > 0 && $selectedClass > 0 && $selectedSubject > 0 && !empty($studentsMarks)): ?>
                    <button type="submit" form="marksForm" class="btn btn-indigo fw-bold text-white px-3 py-2 shadow-sm rounded-3" style="background-color:#6366f1; border:none;" id="btnSaveMarksTop">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save & Compute Results
                    </button>
                    <button class="btn btn-light fw-semibold px-3 py-2 shadow-sm rounded-3" onclick="exportMarksCSV()">
                        <i class="fa-solid fa-file-csv me-1 text-success"></i> Export CSV
                    </button>
                    <button class="btn btn-outline-light fw-semibold px-3 py-2 rounded-3" onclick="window.print()">
                        <i class="fa-solid fa-print me-1"></i> Print Sheet
                    </button>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-outline-secondary text-white border-secondary px-3 py-2 rounded-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Selector Filter Card -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:14px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-dark">Select Exam Term *</label>
                <select class="form-select form-select-sm" name="exam_type_id" required onchange="this.form.submit()">
                    <option value="">-- Choose Exam Term --</option>
                    <?php foreach ($examTypes as $et): ?>
                        <option value="<?php echo $et['id']; ?>" <?php echo $selectedExam === (int)$et['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($et['exam_name']); ?> (<?php echo htmlspecialchars($et['academic_session']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label small fw-bold text-dark">Program Type</label>
                <select class="form-select form-select-sm" name="academic_type" onchange="this.form.submit()">
                    <option value="School" <?php echo $selectedAcademicType === 'School' ? 'selected' : ''; ?>>School</option>
                    <option value="Academy" <?php echo $selectedAcademicType === 'Academy' ? 'selected' : ''; ?>>Academy</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold text-dark">Class & Section *</label>
                <select class="form-select form-select-sm" name="class_id" required onchange="this.form.submit()">
                    <option value="">-- Select Class --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold text-dark">Subject *</label>
                <select class="form-select form-select-sm" name="subject_id" required <?php echo empty($subjectsForClass) ? 'disabled' : ''; ?> onchange="this.form.submit()">
                    <option value="">-- Choose Subject --</option>
                    <?php foreach ($subjectsForClass as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $selectedSubject === (int)$s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['subject_name']); ?> (Max: <?php echo $s['total_marks']; ?>, Pass: <?php echo $s['passing_marks']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100 py-1.5 fw-bold" title="Load Marksheet">
                    <i class="fa-solid fa-arrows-rotate"></i> Load
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedExam > 0 && $selectedClass > 0 && $selectedSubject > 0 && $subjectInfo): ?>

    <!-- KPI Summary Bar for Active Sheet -->
    <div class="row g-3 mb-4 d-print-none">
        <!-- KPI 1: Enrolled -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card-marks p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Enrolled</span>
                        <h3 class="fw-bold text-dark mb-0" id="statEnrolled"><?php echo $totalEnrolled; ?></h3>
                        <small class="text-indigo fw-semibold" style="color:#4f46e5;"><i class="fa-solid fa-users me-1"></i>Students</small>
                    </div>
                    <div class="kpi-icon-wrapper badge-soft-indigo">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 2: Present Turnout -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card-marks p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Present</span>
                        <h3 class="fw-bold text-emerald mb-0" style="color:#059669;" id="statPresent"><?php echo $presentCount; ?></h3>
                        <small class="text-emerald fw-semibold" style="color:#059669;"><i class="fa-solid fa-user-check me-1"></i>Appeared</small>
                    </div>
                    <div class="kpi-icon-wrapper badge-soft-emerald">
                        <i class="fa-solid fa-clipboard-user"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 3: Absent / Exempt -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card-marks p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Absent</span>
                        <h3 class="fw-bold text-amber mb-0" style="color:#d97706;" id="statAbsent"><?php echo $absentCount; ?></h3>
                        <small class="text-amber fw-semibold" style="color:#d97706;"><i class="fa-solid fa-user-xmark me-1"></i>Absentees</small>
                    </div>
                    <div class="kpi-icon-wrapper badge-soft-amber">
                        <i class="fa-solid fa-user-slash"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 4: Passed -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card-marks p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Passed</span>
                        <h3 class="fw-bold text-success mb-0" id="statPassed"><?php echo $passedCount; ?></h3>
                        <small class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Passed Exam</small>
                    </div>
                    <div class="kpi-icon-wrapper badge-soft-emerald">
                        <i class="fa-solid fa-award"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 5: Failed -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card-marks p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Failed</span>
                        <h3 class="fw-bold text-rose mb-0" style="color:#e11d48;" id="statFailed"><?php echo $failedCount; ?></h3>
                        <small class="text-rose fw-semibold" style="color:#e11d48;"><i class="fa-solid fa-circle-xmark me-1"></i>Below Cutoff</small>
                    </div>
                    <div class="kpi-icon-wrapper badge-soft-rose">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 6: Class Average Score -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card-marks p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Class Avg</span>
                        <h3 class="fw-bold text-cyan mb-0" style="color:#0891b2;" id="statAvg"><?php echo $classAvgScore; ?></h3>
                        <small class="text-muted fw-semibold">Out of <?php echo $maxMarksVal; ?></small>
                    </div>
                    <div class="kpi-icon-wrapper badge-soft-cyan">
                        <i class="fa-solid fa-chart-simple"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Evaluator Sheet Card -->
    <?php if (empty($studentsMarks)): ?>
        <div class="card border-0 shadow-sm p-5 text-center mb-4" style="border-radius:14px;">
            <i class="fa-solid fa-users-slash text-muted fa-3x mb-3 opacity-50"></i>
            <h5 class="fw-bold text-dark mb-1">No Active Students Found</h5>
            <p class="text-muted small mb-0">There are no active students enrolled in <?php echo htmlspecialchars($activeClassTitle); ?> to evaluate.</p>
        </div>
    <?php else: ?>
        <form id="marksForm">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="action" value="save_student_marks">
            <input type="hidden" name="exam_type_id" value="<?php echo $selectedExam; ?>">
            <input type="hidden" name="subject_id" value="<?php echo $selectedSubject; ?>">

            <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;" id="marksPrintArea">
                
                <!-- Print Header -->
                <div class="card-header bg-white border-0 pt-4 px-4 d-none d-print-block text-center border-bottom pb-3">
                    <h2 class="fw-bold text-dark mb-0">INDUS GRAMMAR SCHOOL</h2>
                    <h5 class="text-primary fw-bold mb-0">Official Examination Marksheet</h5>
                    <p class="text-muted small mb-0">
                        Exam: <strong><?php echo htmlspecialchars($examInfo['exam_name'] ?? ''); ?></strong> | 
                        Class: <strong><?php echo htmlspecialchars($activeClassTitle); ?></strong> | 
                        Subject: <strong><?php echo htmlspecialchars($subjectInfo['subject_name']); ?> (<?php echo htmlspecialchars($subjectInfo['subject_code']); ?>)</strong>
                    </p>
                    <small class="text-muted">Maximum Marks: <strong><?php echo $maxMarksVal; ?></strong> | Passing Cutoff: <strong><?php echo $passMarksVal; ?> Marks</strong></small>
                </div>

                <!-- Evaluator Toolbar (D-Print-None) -->
                <div class="card-header bg-white border-0 pt-4 px-4 pb-3 d-flex flex-wrap align-items-center justify-content-between gap-3 d-print-none">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">
                            <i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Marks Evaluator Sheet: <?php echo htmlspecialchars($subjectInfo['subject_name']); ?>
                        </h5>
                        <p class="text-muted small mb-0">
                            Class: <strong><?php echo htmlspecialchars($activeClassTitle); ?></strong> | 
                            Max Limit: <strong class="text-dark"><?php echo $maxMarksVal; ?> Marks</strong> | 
                            Pass Cutoff: <strong class="text-danger"><?php echo $passMarksVal; ?> Marks</strong>
                        </p>
                    </div>

                    <!-- Batch Actions -->
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-success btn-sm fw-semibold" onclick="batchSetStatus('Present')">
                            <i class="fa-solid fa-user-check me-1"></i> All Present
                        </button>
                        <button type="button" class="btn btn-outline-warning btn-sm fw-semibold" onclick="batchSetStatus('Absent')">
                            <i class="fa-solid fa-user-xmark me-1"></i> All Absent
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold" onclick="clearAllMarks()">
                            <i class="fa-solid fa-eraser me-1"></i> Clear Marks
                        </button>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-marks table-hover align-middle mb-0 table-print-clean" id="marksMainTable">
                            <thead>
                                <tr>
                                    <th style="width: 50px;" class="text-center">#</th>
                                    <th>Admission No.</th>
                                    <th>Student Name</th>
                                    <th style="width: 150px;" class="text-center">Attendance Status</th>
                                    <th style="width: 180px;" class="text-end">Obtained Marks</th>
                                    <th style="width: 120px;" class="text-center">Pass / Fail</th>
                                    <th>Evaluator Remarks (Optional)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $idx = 1;
                                foreach ($studentsMarks as $s): 
                                    $sid = $s['student_id'];
                                    $currStatus = $s['status'] ?? 'Present';
                                    $currMarks  = $s['marks_obtained'] !== null ? (float)$s['marks_obtained'] : '';
                                    $isPass = ($currMarks !== '' && $currMarks >= $passMarksVal);
                                    $isFail = ($currMarks !== '' && $currMarks < $passMarksVal);
                                ?>
                                    <tr id="row_<?php echo $sid; ?>">
                                        <td class="text-center text-muted fw-bold small"><?php echo $idx++; ?></td>
                                        <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($s['admission_no']); ?></code></td>
                                        <td class="fw-bold text-dark">
                                            <i class="fa-solid fa-user-graduate text-primary me-2 opacity-75"></i>
                                            <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>
                                        </td>
                                        <td class="text-center">
                                            <select class="form-select form-select-sm status-select fw-semibold" name="marks[<?php echo $sid; ?>][status]" data-sid="<?php echo $sid; ?>" onchange="handleStatusChange(<?php echo $sid; ?>)">
                                                <option value="Present" <?php echo ($currStatus === 'Present' || !$currStatus) ? 'selected' : ''; ?>>Present</option>
                                                <option value="Absent" <?php echo $currStatus === 'Absent' ? 'selected' : ''; ?>>Absent</option>
                                                <option value="Leave" <?php echo $currStatus === 'Leave' ? 'selected' : ''; ?>>Leave</option>
                                                <option value="Exempt" <?php echo $currStatus === 'Exempt' ? 'selected' : ''; ?>>Exempt</option>
                                            </select>
                                        </td>
                                        <td class="text-end">
                                            <div class="input-group input-group-sm ms-auto" style="width: 150px;">
                                                <input type="number" step="0.5" min="0" max="<?php echo $maxMarksVal; ?>" 
                                                       class="form-control text-end fw-bold marks-val-input" 
                                                       name="marks[<?php echo $sid; ?>][marks]" 
                                                       id="marks_input_<?php echo $sid; ?>" 
                                                       value="<?php echo $currMarks !== '' ? $currMarks : ''; ?>" 
                                                       data-sid="<?php echo $sid; ?>"
                                                       oninput="validateAndRecalc(<?php echo $sid; ?>)"
                                                       <?php echo ($currStatus && $currStatus !== 'Present') ? 'disabled' : ''; ?>>
                                                <span class="input-group-text bg-light text-muted fw-bold">/ <?php echo (int)$maxMarksVal; ?></span>
                                            </div>
                                            <div class="text-danger text-xs fw-bold mt-1 d-none" id="err_badge_<?php echo $sid; ?>">Exceeds Max!</div>
                                        </td>
                                        <td class="text-center" id="result_badge_col_<?php echo $sid; ?>">
                                            <?php if ($currStatus !== 'Present' && $currStatus !== ''): ?>
                                                <span class="badge bg-secondary px-2.5 py-1 fw-bold"><?php echo strtoupper($currStatus); ?></span>
                                            <?php elseif ($currMarks !== ''): ?>
                                                <?php if ($isPass): ?>
                                                    <span class="badge bg-success bg-opacity-15 text-success px-2.5 py-1 rounded-pill fw-bold"><i class="fa-solid fa-check me-1"></i>PASS</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger bg-opacity-15 text-danger px-2.5 py-1 rounded-pill fw-bold"><i class="fa-solid fa-xmark me-1"></i>FAIL</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted text-xs">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" name="marks[<?php echo $sid; ?>][remarks]" value="<?php echo htmlspecialchars($s['remarks'] ?? ''); ?>" placeholder="Add observations...">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-light border-0 py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <button type="button" class="btn btn-outline-secondary fw-semibold d-print-none" onclick="window.print()">
                        <i class="fa-solid fa-print me-2"></i>Print Marksheet Voucher
                    </button>
                    <button type="submit" class="btn btn-primary px-5 fw-bold" id="btnSaveMarks">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Save Scores & Compute Results
                    </button>
                </div>

                <!-- Print Signatures -->
                <div class="card-footer bg-white border-0 pt-5 pb-4 d-none d-print-block">
                    <div class="row text-center mt-4">
                        <div class="col-4">
                            <div class="border-top pt-2 mx-3">
                                <span class="fw-bold text-dark small">Evaluator Teacher</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border-top pt-2 mx-3">
                                <span class="fw-bold text-dark small">Controller of Examinations</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border-top pt-2 mx-3">
                                <span class="fw-bold text-dark small">Principal Signature</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>

<?php else: ?>
    <!-- Empty Prompt State -->
    <div class="card border-0 shadow-sm" style="border-radius:14px; height: 320px;">
        <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-5">
            <div class="kpi-icon-wrapper badge-soft-indigo mb-3" style="width:64px; height:64px; font-size:2rem;">
                <i class="fa-solid fa-pen-nib"></i>
            </div>
            <h5 class="text-dark fw-bold mb-1">No Evaluator Marksheet Loaded</h5>
            <p class="text-muted small mb-0" style="max-width: 450px;">
                Please select an active <strong>Exam Term</strong>, <strong>Class Section</strong>, and <strong>Subject</strong> in the filter bar above to open the grading marksheet.
            </p>
        </div>
    </div>
<?php endif; ?>

<!-- Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="marksToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="marksToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php 
$maxMarksJs  = $maxMarksVal;
$passMarksJs = $passMarksVal;

$extraJS = '<script>
const MAX_MARKS = ' . $maxMarksJs . ';
const PASS_MARKS = ' . $passMarksJs . ';

function showToast(msg, ok) {
    const t = document.getElementById("marksToast");
    const m = document.getElementById("marksToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function handleStatusChange(sid) {
    const sel = document.querySelector(`select[name="marks[${sid}][status]"]`);
    const input = document.getElementById("marks_input_" + sid);
    const badgeCol = document.getElementById("result_badge_col_" + sid);

    if (sel.value === "Present") {
        input.disabled = false;
    } else {
        input.disabled = true;
        input.value = "";
        input.classList.remove("is-invalid");
        document.getElementById("err_badge_" + sid).classList.add("d-none");
        badgeCol.innerHTML = `<span class="badge bg-secondary px-2.5 py-1 fw-bold">${sel.value.toUpperCase()}</span>`;
    }
    recalculateSheetStats();
}

function validateAndRecalc(sid) {
    const input = document.getElementById("marks_input_" + sid);
    const errBadge = document.getElementById("err_badge_" + sid);
    const badgeCol = document.getElementById("result_badge_col_" + sid);
    const valStr = input.value.trim();

    if (valStr === "") {
        input.classList.remove("is-invalid");
        errBadge.classList.add("d-none");
        badgeCol.innerHTML = \'<span class="text-muted text-xs">—</span>\';
        recalculateSheetStats();
        return;
    }

    const val = parseFloat(valStr);
    if (isNaN(val) || val < 0 || val > MAX_MARKS) {
        input.classList.add("is-invalid");
        errBadge.classList.remove("d-none");
        badgeCol.innerHTML = \'<span class="badge bg-danger px-2.5 py-1 fw-bold">INVALID</span>\';
    } else {
        input.classList.remove("is-invalid");
        errBadge.classList.add("d-none");
        if (val >= PASS_MARKS) {
            badgeCol.innerHTML = \'<span class="badge bg-success bg-opacity-15 text-success px-2.5 py-1 rounded-pill fw-bold"><i class="fa-solid fa-check me-1"></i>PASS</span>\';
        } else {
            badgeCol.innerHTML = \'<span class="badge bg-danger bg-opacity-15 text-danger px-2.5 py-1 rounded-pill fw-bold"><i class="fa-solid fa-xmark me-1"></i>FAIL</span>\';
        }
    }

    recalculateSheetStats();
}

function recalculateSheetStats() {
    let enrolled = 0, present = 0, absent = 0, passed = 0, failed = 0, sum = 0, entered = 0;
    
    document.querySelectorAll(".status-select").forEach(sel => {
        enrolled++;
        const sid = sel.dataset.sid;
        const input = document.getElementById("marks_input_" + sid);
        
        if (sel.value === "Present") {
            present++;
            if (input && !input.disabled && input.value.trim() !== "") {
                const val = parseFloat(input.value);
                if (!isNaN(val) && val >= 0 && val <= MAX_MARKS) {
                    sum += val;
                    entered++;
                    if (val >= PASS_MARKS) passed++;
                    else failed++;
                }
            }
        } else {
            absent++;
        }
    });

    const avg = entered > 0 ? (sum / entered).toFixed(1) : "0";

    const elEnrolled = document.getElementById("statEnrolled");
    const elPresent  = document.getElementById("statPresent");
    const elAbsent   = document.getElementById("statAbsent");
    const elPassed   = document.getElementById("statPassed");
    const elFailed   = document.getElementById("statFailed");
    const elAvg      = document.getElementById("statAvg");

    if (elEnrolled) elEnrolled.textContent = enrolled;
    if (elPresent)  elPresent.textContent  = present;
    if (elAbsent)   elAbsent.textContent   = absent;
    if (elPassed)   elPassed.textContent   = passed;
    if (elFailed)   elFailed.textContent   = failed;
    if (elAvg)      elAvg.textContent      = avg;
}

function batchSetStatus(status) {
    document.querySelectorAll(".status-select").forEach(sel => {
        sel.value = status;
        const sid = sel.dataset.sid;
        handleStatusChange(sid);
    });
}

function clearAllMarks() {
    if (!confirm("Are you sure you want to clear all entered marks?")) return;
    document.querySelectorAll(".marks-val-input").forEach(input => {
        if (!input.disabled) {
            input.value = "";
            const sid = input.dataset.sid;
            validateAndRecalc(sid);
        }
    });
}

function exportMarksCSV() {
    let csv = [];
    let rows = document.querySelectorAll("#marksMainTable tr");
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length - 1; j++) {
            let text = cols[j].innerText.replace(/(\\r\\n|\\n|\\r)/gm, " ").replace(/\\s+/g, " ").trim();
            row.push(\'"\' + text + \'"\');
        }
        if (row.length > 0) csv.push(row.join(","));
    }
    let csvFile = new Blob([csv.join("\\n")], { type: "text/csv" });
    let downloadLink = document.createElement("a");
    downloadLink.download = "marksheet_export_" + new Date().toISOString().slice(0,10) + ".csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("marksForm");
    if(form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            // Validate all inputs before sending
            let invalidCount = 0;
            document.querySelectorAll(".marks-val-input").forEach(input => {
                if (!input.disabled && input.value.trim() !== "") {
                    const val = parseFloat(input.value);
                    if (isNaN(val) || val < 0 || val > MAX_MARKS) {
                        input.classList.add("is-invalid");
                        invalidCount++;
                    }
                }
            });

            if (invalidCount > 0) {
                showToast(`Cannot submit: ${invalidCount} mark entry(s) exceed maximum boundary limits (${MAX_MARKS}).`, false);
                return;
            }

            const btn1 = document.getElementById("btnSaveMarks");
            const btn2 = document.getElementById("btnSaveMarksTop");
            
            if (btn1) { btn1.disabled = true; btn1.innerHTML = \'<i class="fa-solid fa-spinner fa-spin me-2"></i> Saving Marksheet...\'; }
            if (btn2) { btn2.disabled = true; btn2.innerHTML = \'<i class="fa-solid fa-spinner fa-spin me-2"></i> Saving...\'; }

            fetch("../../ajax/exams.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        setTimeout(() => location.reload(), 800);
                    } else {
                        if (btn1) { btn1.disabled = false; btn1.innerHTML = \'<i class="fa-solid fa-floppy-disk me-2"></i> Save Scores & Compute Results\'; }
                        if (btn2) { btn2.disabled = false; btn2.innerHTML = \'<i class="fa-solid fa-floppy-disk me-1"></i> Save & Compute Results\'; }
                    }
                })
                .catch(() => {
                    showToast("Failed to save student mark entries.", false);
                    if (btn1) { btn1.disabled = false; btn1.innerHTML = \'<i class="fa-solid fa-floppy-disk me-2"></i> Save Scores & Compute Results\'; }
                    if (btn2) { btn2.disabled = false; btn2.innerHTML = \'<i class="fa-solid fa-floppy-disk me-1"></i> Save & Compute Results\'; }
                });
        });
    }
});
</script>';

include_once __DIR__ . '/../../includes/footer.php'; ?>
