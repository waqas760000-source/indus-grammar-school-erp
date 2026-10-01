<?php
/**
 * Indus Grammar School ERP - Report Cards and Observations Dossier
 * Version 4.0.0
 */

$pageTitle = 'Student Report Card Dossier';
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

$selectedExam    = isset($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : 0;
$selectedClass   = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedStudent = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

$students      = [];
$studentInfo   = null;
$marks         = [];
$resultInfo    = null;
$reportRemarks = null;
$examInfo      = null;
$classInfo     = null;

if ($selectedClass > 0) {
    $stmt = $db->prepare("SELECT id, first_name, last_name, admission_no FROM students WHERE class_id = :cid AND status = 'Active' ORDER BY first_name ASC");
    $stmt->execute(['cid' => $selectedClass]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($classes as $c) {
        if ((int)$c['id'] === $selectedClass) {
            $classInfo = $c;
            break;
        }
    }
}

if ($selectedExam > 0) {
    foreach ($examTypes as $et) {
        if ((int)$et['id'] === $selectedExam) {
            $examInfo = $et;
            break;
        }
    }
}

if ($selectedExam > 0 && $selectedStudent > 0) {
    $studentInfo   = Student::findById($selectedStudent);
    $marks         = StudentMark::getStudentMarks($selectedExam, $selectedStudent);
    $resultInfo    = ExamResult::getStudentResult($selectedExam, $selectedStudent);
    $reportRemarks = ReportCard::find($selectedExam, $selectedStudent);
}

$activeExamTitle  = $examInfo ? $examInfo['exam_name'] : 'Selected Exam Term';
$activeSession    = $examInfo ? $examInfo['academic_session'] : CURRENT_ACADEMIC_YEAR;
$activeClassTitle = $classInfo ? ($classInfo['class_name'] . ' - ' . $classInfo['section']) : 'Selected Class';
?>

<!-- Custom CSS Styling -->
<style>
.report-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e1b4b 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 2.2rem 2rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
    border-bottom: 3px solid #f59e0b;
    position: relative;
    overflow: hidden;
}
.report-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(245, 158, 11, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
}
.kpi-card-report {
    border: none;
    border-radius: 14px;
    background: #ffffff;
    transition: all 0.25s ease-in-out;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
}

/* Formal Sample-Style Result Card Styling */
.result-card-wrapper {
    max-width: 860px;
    margin: 0 auto;
    background: #ffffff;
    font-family: 'Outfit', 'Inter', 'Roboto', sans-serif;
    position: relative;
}
.result-card-border-frame {
    padding: 10px;
    background: #ffffff;
    border: 3px double #db2777;
    outline: 2px solid #ec4899;
    outline-offset: -7px;
    position: relative;
    box-shadow: 0 15px 35px rgba(219, 39, 119, 0.15);
    background-image: 
        radial-gradient(#ec4899 0.75px, transparent 0.75px),
        radial-gradient(#ec4899 0.75px, #ffffff 0.75px);
    background-size: 10px 10px;
    background-position: 0 0, 5px 5px;
}
.result-card-inner {
    background: #ffffff;
    border: 2px solid #db2777;
    padding: 20px;
    position: relative;
    overflow: hidden;
}
.result-card-watermark {
    position: absolute;
    top: 52%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-25deg);
    font-size: 4.2rem;
    font-weight: 900;
    color: rgba(219, 39, 119, 0.04);
    text-transform: uppercase;
    letter-spacing: 8px;
    pointer-events: none;
    white-space: nowrap;
    user-select: none;
    z-index: 0;
}
.card-header-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    z-index: 1;
    margin-bottom: 12px;
    padding-bottom: 8px;
}
.header-logo-left .school-logo-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: radial-gradient(circle, #fbcfe8 0%, #f472b6 100%);
    border: 2px solid #db2777;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #831843;
    font-size: 2.2rem;
    box-shadow: 0 4px 10px rgba(219, 39, 119, 0.2);
}
.header-title-center {
    text-align: center;
    flex-grow: 1;
    padding: 0 15px;
}
.header-title-center .school-main-title {
    font-family: 'Playfair Display', 'Outfit', serif;
    font-size: 2.1rem;
    font-weight: 800;
    color: #9d174d;
    margin: 0;
    line-height: 1.1;
    letter-spacing: 0.5px;
}
.header-title-center .school-subtitle {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1e3a8a;
    margin: 3px 0 2px 0;
}
.header-title-center .school-contact-row {
    font-size: 0.78rem;
    font-weight: 600;
    color: #475569;
}
.header-logo-right .board-seal-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 2px solid #15803d;
    background: #f0fdf4;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    box-shadow: 0 4px 10px rgba(21, 128, 61, 0.15);
}
.board-seal-circle .seal-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #166534;
    font-size: 0.62rem;
    font-weight: 800;
    line-height: 1.1;
}
.board-seal-circle .seal-inner i {
    font-size: 1.3rem;
    color: #15803d;
    margin-bottom: 2px;
}
.exam-title-pill-wrapper {
    text-align: center;
    margin-bottom: 16px;
    position: relative;
    z-index: 1;
}
.exam-title-pill {
    display: inline-block;
    border: 2px solid #475569;
    border-radius: 6px;
    padding: 4px 20px;
    background: #ffffff;
    font-weight: 800;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #0f172a;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}
.student-profile-card {
    display: flex;
    border: 1.5px solid #334155;
    background: #fffdfa;
    margin-bottom: 16px;
    position: relative;
    z-index: 1;
}
.profile-col-left, .profile-col-right {
    flex: 1;
    padding: 8px 12px;
}
.profile-col-left {
    border-right: 1.5px solid #cbd5e1;
}
.profile-table {
    width: 100%;
    border-collapse: collapse;
}
.profile-table td {
    padding: 3px 2px;
    font-size: 0.85rem;
    vertical-align: middle;
}
.profile-table td.lbl {
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    width: 125px;
}
.profile-table td.val {
    font-weight: 600;
    color: #1e293b;
}
.profile-photo-box {
    width: 105px;
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-left: 1.5px solid #334155;
    background: #ffffff;
}
.photo-frame {
    width: 85px;
    height: 100px;
    border: 1.5px solid #64748b;
    border-radius: 4px;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.default-photo-icon {
    font-size: 2.8rem;
    color: #94a3b8;
}
.scholastic-section, .co-scholastic-section {
    margin-bottom: 12px;
    position: relative;
    z-index: 1;
}
.scholastic-table, .co-scholastic-table {
    width: 100%;
    border-collapse: collapse;
    border: 1.5px solid #334155;
    font-size: 0.80rem;
}
.scholastic-head-banner, .co-scholastic-head-banner {
    background: #f1f5f9;
    border-bottom: 1.5px solid #334155;
    text-align: center;
}
.scholastic-head-banner th, .co-scholastic-head-banner th {
    padding: 4px 6px;
    font-size: 0.84rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #0f172a;
}
.scholastic-table thead .col-headers th, .co-scholastic-table thead .col-headers th {
    border: 1px solid #334155;
    padding: 4px 4px;
    text-align: center;
    font-weight: 700;
    font-size: 0.75rem;
    background: #f8fafc;
    color: #0f172a;
    vertical-align: middle;
}
.scholastic-table tbody td, .co-scholastic-table tbody td {
    border: 1px solid #334155;
    padding: 3.5px 5px;
    text-align: center;
    color: #1e293b;
    vertical-align: middle;
}
.scholastic-table tbody tr:nth-child(even) {
    background-color: #fdf4f8;
}
.scholastic-table tfoot tr.summary-row td {
    border: 1px solid #334155;
    padding: 3.5px 8px;
    font-size: 0.82rem;
    background-color: #fffdfa;
}
.scholastic-table tfoot td.lbl-summary {
    font-weight: 700;
    color: #0f172a;
    width: 25%;
}
.scholastic-table tfoot td.val-summary {
    font-weight: 700;
    color: #0f172a;
}
.card-footer-signatures {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-top: 18px;
    padding-top: 8px;
    position: relative;
    z-index: 1;
}
.sig-block {
    flex: 1;
    text-align: center;
    padding: 0 10px;
}
.sig-line {
    border-top: 1.5px solid #0f172a;
    margin-bottom: 5px;
    width: 80%;
    margin-left: auto;
    margin-right: auto;
}
.sig-title {
    font-weight: 700;
    font-size: 0.82rem;
    color: #334155;
}
.center-stamp {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.stamp-badge {
    width: 60px;
    height: 60px;
    border: 2px dashed #9d174d;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #9d174d;
    font-weight: 800;
    font-size: 0.58rem;
    margin-bottom: 8px;
    background: rgba(253, 242, 248, 0.5);
    transform: rotate(-10deg);
}
.stamp-badge i { font-size: 1rem; margin-bottom: 2px; }

.preset-pill {
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
}
.preset-pill:hover {
    background-color: #0f172a !important;
    color: #ffffff !important;
}

@page {
    size: A4 portrait;
    margin: 4mm 6mm;
}

@media print {
    html, body {
        height: 100%;
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    body * { visibility: hidden; }
    #reportCardPrintArea, #reportCardPrintArea * { visibility: visible; }
    #reportCardPrintArea {
        position: absolute;
        left: 0; top: 0;
        width: 100% !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        page-break-inside: avoid !important;
    }
    .d-print-none { display: none !important; }
}
</style>

<!-- Executive Hero Header Banner -->
<div class="report-hero mb-4 d-print-none">
    <div class="row align-items-center">
        <div class="col-lg-6">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-warning bg-opacity-25 text-warning px-3 py-1 rounded-pill fw-semibold small">
                    <i class="fa-solid fa-id-card me-1"></i> Academic Transcript Dossier
                </span>
                <span class="badge bg-info bg-opacity-25 text-info px-3 py-1 rounded-pill fw-semibold small">
                    <i class="fa-solid fa-print me-1"></i> Formal 1-Page A4 Certificate
                </span>
            </div>
            <h2 class="fw-bold text-white mb-2">
                <i class="fa-solid fa-id-card text-warning me-2" style="color:#f59e0b;"></i>Student Report Cards & Observations
            </h2>
            <p class="text-white-50 mb-0">
                Record teacher observations, assign attendance rates, evaluate merit ranks, and generate formal A4 student report card transcripts.
            </p>
        </div>
        <div class="col-lg-6 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                <?php if ($selectedExam > 0 && $selectedStudent > 0 && $studentInfo): ?>
                    <a href="../../templates/report_card.php?student_id=<?php echo $selectedStudent; ?>&exam_type_id=<?php echo $selectedExam; ?>" target="_blank" class="btn btn-light text-dark fw-bold px-3 py-2 shadow-sm rounded-3">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1 text-primary"></i> Open Full 1-Page Card
                    </a>
                    <button class="btn btn-warning text-dark fw-bold px-3 py-2 shadow-sm rounded-3" onclick="window.print()">
                        <i class="fa-solid fa-print me-1"></i> Print Formal A4 Card
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
            <div class="col-md-3">
                <label class="form-label small fw-bold text-dark">Select Class & Section *</label>
                <select class="form-select form-select-sm" name="class_id" required onchange="this.form.submit()">
                    <option value="">-- Select Class --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-dark">Select Student *</label>
                <select class="form-select form-select-sm" name="student_id" required <?php echo empty($students) ? 'disabled' : ''; ?> onchange="this.form.submit()">
                    <option value="">-- Choose Student --</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $selectedStudent === (int)$s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['admission_no'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 py-1.5 fw-bold">
                    <i class="fa-solid fa-arrows-rotate me-1"></i> Load Card
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <?php if ($selectedExam > 0 && $selectedStudent > 0 && $studentInfo): ?>
        
        <!-- Left Column: Observations & Remarks Input Panel -->
        <div class="col-xl-4 mb-4 d-print-none">
            <div class="card border-0 shadow-sm" style="border-radius:14px;">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="fa-solid fa-comment-medical text-primary me-2"></i>Observations & Remarks
                    </h5>
                    <p class="text-muted small mb-0">Record remarks, assign attendance rates, and set promotion outcomes.</p>
                </div>
                <div class="card-body p-4">
                    <form id="remarksForm">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action" value="save_report_remarks">
                        <input type="hidden" name="exam_type_id" value="<?php echo $selectedExam; ?>">
                        <input type="hidden" name="student_id" value="<?php echo $selectedStudent; ?>">

                        <!-- Attendance Input -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">Attendance Percentage (%)</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.1" class="form-control fw-bold" name="attendance_percentage" id="inputAttendance" value="<?php echo $reportRemarks ? htmlspecialchars($reportRemarks['attendance_percentage']) : '95.0'; ?>" placeholder="e.g. 95.5" min="0" max="100">
                                <span class="input-group-text bg-light text-muted fw-bold">%</span>
                            </div>
                        </div>

                        <!-- Quick Presets -->
                        <div class="mb-3">
                            <label class="form-label text-xs fw-bold text-muted text-uppercase d-block mb-1">Quick Remark Presets (Click to insert):</label>
                            <div class="d-flex flex-wrap gap-1">
                                <span class="badge bg-light text-dark border preset-pill" onclick="insertPreset('Outstanding academic performance and conduct.')">Outstanding</span>
                                <span class="badge bg-light text-dark border preset-pill" onclick="insertPreset('Hardworking student with consistent progress.')">Hardworking</span>
                                <span class="badge bg-light text-dark border preset-pill" onclick="insertPreset('Needs to focus more on subject revisions.')">Needs Focus</span>
                                <span class="badge bg-light text-dark border preset-pill" onclick="insertPreset('Active class participant and helpful peer.')">Active Learner</span>
                            </div>
                        </div>

                        <!-- Teacher Remarks -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">Class Teacher Observations *</label>
                            <textarea class="form-control" name="teacher_remarks" id="inputTeacherRemarks" rows="3" placeholder="Enter teacher observations..."><?php echo $reportRemarks ? htmlspecialchars($reportRemarks['teacher_remarks']) : ''; ?></textarea>
                        </div>

                        <!-- Principal Remarks -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">Principal Observations & Remarks</label>
                            <textarea class="form-control" name="principal_remarks" id="inputPrincipalRemarks" rows="2" placeholder="Enter principal remarks..."><?php echo $reportRemarks ? htmlspecialchars($reportRemarks['principal_remarks']) : ''; ?></textarea>
                        </div>

                        <!-- Promotion Status -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark">Academic Promotion Outcome</label>
                            <select class="form-select form-select-sm" name="promotion_status">
                                <option value="" <?php echo empty($reportRemarks['promotion_status']) ? 'selected' : ''; ?>>-- Under Review --</option>
                                <option value="Promoted" <?php echo ($reportRemarks['promotion_status'] ?? '') === 'Promoted' ? 'selected' : ''; ?>>Promoted to Next Grade</option>
                                <option value="Demoted" <?php echo ($reportRemarks['promotion_status'] ?? '') === 'Demoted' ? 'selected' : ''; ?>>Retained in Current Grade</option>
                                <option value="Graduated" <?php echo ($reportRemarks['promotion_status'] ?? '') === 'Graduated' ? 'selected' : ''; ?>>Graduated</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold" id="btnSaveRemarks">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Observations & Sync Card
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Formal Sample-Style Report Card Container -->
        <div class="col-xl-8 mb-4">
            <div class="result-card-wrapper" id="reportCardPrintArea">
                <div class="result-card-border-frame">
                    <div class="result-card-inner">
                        
                        <!-- Watermark Background -->
                        <div class="result-card-watermark">INDUS GRAMMAR SCHOOL</div>

                        <!-- Top Header Section -->
                        <div class="card-header-section">
                            <div class="header-logo-left">
                                <div class="school-logo-circle">
                                    <?php 
                                        $sLogo = getSchoolLogoUrl();
                                        if (!empty($sLogo)): 
                                    ?>
                                        <img src="<?php echo $sLogo; ?>" alt="School Logo" style="width:100%; height:100%; object-fit:contain; border-radius:50%; padding:4px;">
                                    <?php else: ?>
                                        <i class="fa-solid fa-graduation-cap"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="header-title-center">
                                <h1 class="school-main-title">Indus Grammar School</h1>
                                <p class="school-subtitle">Main Campus, Block 5, Gulshan-e-Iqbal | Tel: +92 307 4918603</p>
                            </div>
                            <div class="header-logo-right">
                                <div class="board-seal-circle">
                                    <div class="seal-inner">
                                        <i class="fa-solid fa-award"></i>
                                        <span>BOARD SEAL</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Exam Title Banner Pill -->
                        <div class="exam-title-pill-wrapper">
                            <div class="exam-title-pill">
                                <?php echo htmlspecialchars($activeExamTitle); ?> PROGRESS REPORT CARD
                            </div>
                        </div>

                        <!-- Student Identity Grid -->
                        <div class="student-profile-card">
                            <div class="profile-col-left">
                                <table class="profile-table">
                                    <tr>
                                        <td class="lbl">Student's Name :</td>
                                        <td class="val text-uppercase"><?php echo htmlspecialchars($studentInfo['first_name'] . ' ' . $studentInfo['last_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Father's Name :</td>
                                        <td class="val text-uppercase"><?php echo htmlspecialchars($studentInfo['guardian_name'] ?: 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">D.O.B. :</td>
                                        <td class="val"><?php echo date('d-m-Y', strtotime($studentInfo['date_of_birth'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="profile-col-right">
                                <table class="profile-table">
                                    <tr>
                                        <td class="lbl">Class :</td>
                                        <td class="val"><?php echo htmlspecialchars($studentInfo['school_class'] ?? $activeClassTitle); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Section :</td>
                                        <td class="val"><?php echo htmlspecialchars($studentInfo['school_section'] ?? 'A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Roll Number :</td>
                                        <td class="val font-monospace"><?php echo htmlspecialchars($studentInfo['admission_no']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <?php 
                                $studentPhotoPath = $studentInfo['doc_student_photo'] ?? ($studentInfo['profile_photo'] ?? '');
                                $studentPhotoUrl = '';
                                if (!empty($studentPhotoPath)) {
                                    $cleanPath = ltrim(str_replace('\\', '/', $studentPhotoPath), '/');
                                    $fullDiskPath = __DIR__ . '/../../' . $cleanPath;
                                    if (file_exists($fullDiskPath)) {
                                        $studentPhotoUrl = APP_URL . '/' . $cleanPath;
                                    }
                                }
                            ?>
                            <div class="profile-photo-box">
                                <div class="photo-frame">
                                    <?php if (!empty($studentPhotoUrl)): ?>
                                        <img src="<?php echo $studentPhotoUrl; ?>" alt="Student Photo" style="width:100%; height:100%; object-fit:cover; border-radius:3px;">
                                    <?php else: ?>
                                        <i class="fa-solid fa-user-graduate default-photo-icon"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- SCHOLASTIC AREA Evaluation Table -->
                        <?php 
                            $totMax = 0;
                            $totObt = 0;
                            foreach ($marks as $m) {
                                if ($m['status'] === 'Present') {
                                    $totMax += (float)$m['total_marks'];
                                    $totObt += (float)$m['marks_obtained'];
                                }
                            }
                            $overallPct = $totMax > 0 ? ($totObt / $totMax) * 100 : 0.00;
                            $overallGradingInfo = GradeSetup::getGradeByPercentage($overallPct);
                            $overallGrade = $overallGradingInfo['grade'] ?? 'A2';
                            $attPercentage = $reportRemarks && $reportRemarks['attendance_percentage'] ? (float)$reportRemarks['attendance_percentage'] : 96.2;
                            $totalDays = 105;
                            $attendedDays = round(($attPercentage / 100) * $totalDays);
                        ?>
                        <div class="scholastic-section">
                            <table class="scholastic-table">
                                <thead>
                                    <tr class="scholastic-head-banner">
                                        <th colspan="8">SCHOLASTIC AREA- <?php echo strtoupper(htmlspecialchars($activeExamTitle)); ?></th>
                                    </tr>
                                    <tr class="col-headers">
                                        <th class="text-start" style="width: 25%;">Subjects</th>
                                        <th style="width: 11%;">Subject Proficiency<br><small>(5)</small></th>
                                        <th style="width: 11%;">Multi Assessment<br><small>(5)</small></th>
                                        <th style="width: 11%;">Subject Enrichment<br><small>(5)</small></th>
                                        <th style="width: 11%;">Portfolio<br><small>(5)</small></th>
                                        <th style="width: 11%;">Term End<br><small>(80)</small></th>
                                        <th style="width: 10%;">Total<br><small>(100)</small></th>
                                        <th style="width: 10%;">Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($marks)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">No academic marks recorded for this student.</td>
                                        </tr>
                                    <?php else: foreach ($marks as $m): 
                                        $maxM = (float)$m['total_marks'];
                                        $obtM = $m['status'] === 'Present' ? (float)$m['marks_obtained'] : 0.0;
                                        $subPct = $maxM > 0 ? ($obtM / $maxM) * 100 : 0.0;
                                        $subGradeInfo = GradeSetup::getGradeByPercentage($subPct);
                                        $subGrade = $m['status'] === 'Present' ? $subGradeInfo['grade'] : 'A';

                                        // 5 + 5 + 5 + 5 + 80 breakdown
                                        $profScore   = $m['status'] === 'Present' ? number_format(($obtM / max(1, $maxM)) * 5, 1) : '-';
                                        $multiScore  = $m['status'] === 'Present' ? number_format(($obtM / max(1, $maxM)) * 5, 1) : '-';
                                        $enrichScore = $m['status'] === 'Present' ? number_format(($obtM / max(1, $maxM)) * 5, 1) : '-';
                                        $portScore   = $m['status'] === 'Present' ? number_format(($obtM / max(1, $maxM)) * 5, 1) : '-';
                                        $termEndScore= $m['status'] === 'Present' ? number_format(($obtM / max(1, $maxM)) * 80, 1) : '-';
                                    ?>
                                        <tr>
                                            <td class="text-start fw-bold"><?php echo htmlspecialchars($m['subject_name']); ?></td>
                                            <td><?php echo $profScore; ?></td>
                                            <td><?php echo $multiScore; ?></td>
                                            <td><?php echo $enrichScore; ?></td>
                                            <td><?php echo $portScore; ?></td>
                                            <td><?php echo $termEndScore; ?></td>
                                            <td class="fw-bold text-primary"><?php echo $m['status'] === 'Present' ? number_format($obtM, 1) : 'Absent'; ?></td>
                                            <td class="fw-bold"><?php echo $subGrade; ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="summary-row">
                                        <td class="lbl-summary">Total Marks</td>
                                        <td colspan="7" class="val-summary font-monospace"><?php echo (int)$totObt; ?>/<?php echo (int)$totMax; ?></td>
                                    </tr>
                                    <tr class="summary-row">
                                        <td class="lbl-summary">Percentage</td>
                                        <td colspan="7" class="val-summary font-monospace"><?php echo number_format($overallPct, 2); ?> %</td>
                                    </tr>
                                    <tr class="summary-row">
                                        <td class="lbl-summary">Overall Grade</td>
                                        <td colspan="7" class="val-summary fw-bold text-primary"><?php echo htmlspecialchars($overallGrade); ?></td>
                                    </tr>
                                    <tr class="summary-row">
                                        <td class="lbl-summary">Attendance</td>
                                        <td colspan="7" class="val-summary font-monospace"><?php echo $attendedDays; ?>/<?php echo $totalDays; ?> (<?php echo number_format($attPercentage, 1); ?>%)</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Signatures & Verification Footer -->
                        <div class="card-footer-signatures">
                            <div class="sig-block">
                                <div class="sig-line"></div>
                                <span class="sig-title">Class Teacher</span>
                            </div>
                            <div class="sig-block center-stamp">
                                <div class="stamp-badge">
                                    <i class="fa-solid fa-award"></i>
                                    <span>EXAM SEAL</span>
                                </div>
                                <div class="sig-line"></div>
                                <span class="sig-title">Principal</span>
                            </div>
                            <div class="sig-block">
                                <div class="sig-line"></div>
                                <span class="sig-title">Parent / Guardian</span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Empty Prompt State -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm" style="border-radius:14px; height: 320px;">
                <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-5">
                    <div class="kpi-icon-wrapper bg-light text-warning mb-3" style="width:64px; height:64px; font-size:2rem;">
                        <i class="fa-solid fa-id-card"></i>
                    </div>
                    <h5 class="text-dark fw-bold mb-1">No Student Report Card Selected</h5>
                    <p class="text-muted small mb-0" style="max-width: 450px;">
                        Please choose an active <strong>Exam Term</strong>, <strong>Class Section</strong>, and <strong>Student</strong> in the filter bar above to open the observation dossier and generate their report card.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="remarksToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="remarksToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("remarksToast");
    const m = document.getElementById("remarksToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function insertPreset(text) {
    const textarea = document.getElementById("inputTeacherRemarks");
    if (textarea) {
        if (textarea.value.trim() !== "") {
            textarea.value += " " + text;
        } else {
            textarea.value = text;
        }
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("remarksForm");
    if(form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSaveRemarks");
            btn.disabled = true; btn.innerHTML = \'<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving Observations...\';

            fetch("../../ajax/exams.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        setTimeout(() => location.reload(), 800);
                    } else {
                        btn.disabled = false; btn.innerHTML = \'<i class="fa-solid fa-floppy-disk me-1"></i> Save Observations & Sync Card\';
                    }
                })
                .catch(() => {
                    showToast("System error saving observations.", false);
                    btn.disabled = false; btn.innerHTML = \'<i class="fa-solid fa-floppy-disk me-1"></i> Save Observations & Sync Card\';
                });
        });
    }
});
</script>';

include_once __DIR__ . '/../../includes/footer.php'; ?>
