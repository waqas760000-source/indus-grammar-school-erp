<?php
/**
 * Indus Grammar School ERP - Consolidated Examination Reports Hub
 * Version 4.0.0
 */

$pageTitle = 'Examination Reports & Analytics';
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
$sessions  = $db->query("SELECT DISTINCT academic_session FROM exam_types UNION SELECT '" . CURRENT_ACADEMIC_YEAR . "'")->fetchAll(PDO::FETCH_COLUMN);

// Filter values
$selectedReport  = sanitize($_GET['report_type'] ?? 'result_summary');
$selectedExam    = isset($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : 0;
$selectedClass   = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedSession = sanitize($_GET['academic_session'] ?? CURRENT_ACADEMIC_YEAR);

$reportTitle = "Examination Report";
$reportData  = [];

try {
    switch ($selectedReport) {
        
        case 'result_summary':
            $reportTitle = "Result Pass / Fail Summary";
            if ($selectedExam > 0) {
                $sql = "
                    SELECT c.class_name, c.section,
                           SUM(CASE WHEN er.status = 'Pass' THEN 1 ELSE 0 END) as passed,
                           SUM(CASE WHEN er.status = 'Fail' THEN 1 ELSE 0 END) as failed,
                           COUNT(er.id) as total_graded
                    FROM exam_results er
                    JOIN classes c ON er.class_id = c.id
                    WHERE er.exam_type_id = :etid
                ";
                $params = ['etid' => $selectedExam];
                if ($selectedClass > 0) {
                    $sql .= " AND er.class_id = :cid";
                    $params['cid'] = $selectedClass;
                }
                $sql .= " GROUP BY c.id, c.class_name, c.section ORDER BY c.class_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'subject_wise':
            $reportTitle = "Subject-Wise Performance Analysis";
            if ($selectedExam > 0 && $selectedClass > 0) {
                $stmt = $db->prepare("
                    SELECT s.subject_name, s.subject_code, s.total_marks, s.passing_marks,
                           COUNT(sm.id) as total_papers,
                           SUM(CASE WHEN sm.status = 'Present' AND sm.marks_obtained >= s.passing_marks THEN 1 ELSE 0 END) as passed,
                           SUM(CASE WHEN sm.status = 'Present' AND sm.marks_obtained < s.passing_marks THEN 1 ELSE 0 END) as failed,
                           MAX(sm.marks_obtained) as highest_score,
                           AVG(sm.marks_obtained) as average_score
                    FROM subjects s
                    LEFT JOIN student_marks sm ON sm.subject_id = s.id AND sm.exam_type_id = :etid
                    WHERE s.class_id = :cid AND s.status = 'Active'
                    GROUP BY s.id, s.subject_name, s.subject_code, s.total_marks, s.passing_marks
                    ORDER BY s.subject_name ASC
                ");
                $stmt->execute(['etid' => $selectedExam, 'cid' => $selectedClass]);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'class_wise':
            $reportTitle = "Class-Wise Complete Roster Sheet";
            if ($selectedExam > 0 && $selectedClass > 0) {
                $stmt = $db->prepare("
                    SELECT st.first_name, st.last_name, st.admission_no,
                           er.total_marks, er.obtained_marks, er.percentage, er.grade, er.position, er.status
                    FROM students st
                    LEFT JOIN exam_results er ON er.student_id = st.id AND er.exam_type_id = :etid
                    WHERE st.class_id = :cid AND st.status = 'Active'
                    ORDER BY er.position ASC, st.first_name ASC
                ");
                $stmt->execute(['etid' => $selectedExam, 'cid' => $selectedClass]);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'pass_fail':
            $reportTitle = "Student Pass/Fail Status List";
            if ($selectedExam > 0) {
                $sql = "
                    SELECT er.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section
                    FROM exam_results er
                    JOIN students st ON er.student_id = st.id
                    JOIN classes c ON er.class_id = c.id
                    WHERE er.exam_type_id = :etid
                ";
                $params = ['etid' => $selectedExam];
                if ($selectedClass > 0) {
                    $sql .= " AND er.class_id = :cid";
                    $params['cid'] = $selectedClass;
                }
                $sql .= " ORDER BY c.class_name ASC, er.position ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'top_positions':
            $reportTitle = "Top Merit Achievers Report";
            if ($selectedExam > 0) {
                $sql = "
                    SELECT p.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section
                    FROM positions p
                    JOIN students st ON p.student_id = st.id
                    JOIN classes c ON p.class_id = c.id
                    WHERE p.exam_type_id = :etid AND p.position_no <= 3
                ";
                $params = ['etid' => $selectedExam];
                if ($selectedClass > 0) {
                    $sql .= " AND p.class_id = :cid";
                    $params['cid'] = $selectedClass;
                }
                $sql .= " ORDER BY c.class_name ASC, p.position_no ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'failure_report':
            $reportTitle = "Student Failure Records Report";
            if ($selectedExam > 0) {
                $sql = "
                    SELECT er.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section
                    FROM exam_results er
                    JOIN students st ON er.student_id = st.id
                    JOIN classes c ON er.class_id = c.id
                    WHERE er.exam_type_id = :etid AND er.status = 'Fail'
                ";
                $params = ['etid' => $selectedExam];
                if ($selectedClass > 0) {
                    $sql .= " AND er.class_id = :cid";
                    $params['cid'] = $selectedClass;
                }
                $sql .= " ORDER BY c.class_name ASC, st.first_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'promotions_report':
            $reportTitle = "Academic Promotion Registry Journal ($selectedSession)";
            $reportData = Promotion::all($selectedSession);
            break;
    }
} catch (Exception $e) {
    error_log("Reports load error: " . $e->getMessage());
}

$activeExamTitle = 'All Exams';
if ($selectedExam > 0) {
    foreach ($examTypes as $et) {
        if ((int)$et['id'] === $selectedExam) {
            $activeExamTitle = $et['exam_name'];
            break;
        }
    }
}
?>

<!-- Custom CSS Styling -->
<style>
.reports-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e3a8a 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 2.2rem 2rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
    border-bottom: 3px solid #38bdf8;
    position: relative;
    overflow: hidden;
}
.reports-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(56, 189, 248, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
}
.table-reports thead th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    font-weight: 700;
    padding: 0.9rem;
    border-bottom: 2px solid #e2e8f0;
}
.table-reports tbody td {
    padding: 0.85rem 0.9rem;
    vertical-align: middle;
}
@media print {
    body * { visibility: hidden; }
    #reportPrintArea, #reportPrintArea * { visibility: visible; }
    #reportPrintArea {
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
<div class="reports-hero mb-4 d-print-none">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-info bg-opacity-25 text-info px-3 py-1 rounded-pill fw-semibold small" style="color:#7dd3fc;">
                    <i class="fa-solid fa-chart-line me-1"></i> Examination Analytics Hub
                </span>
                <span class="badge bg-success bg-opacity-25 text-success px-3 py-1 rounded-pill fw-semibold small" style="color:#6ee7b7;">
                    <i class="fa-solid fa-file-invoice me-1"></i> Official Report Registers
                </span>
            </div>
            <h2 class="fw-bold text-white mb-2">
                <i class="fa-solid fa-file-invoice text-info me-2" style="color:#38bdf8;"></i>Examination Reports & Analytics
            </h2>
            <p class="text-white-50 mb-0">
                Generate class summaries, merit rankings, pass rates, subject performance analytics, and promotion registry journals.
            </p>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                <button class="btn btn-light fw-semibold px-3 py-2 shadow-sm rounded-3" onclick="exportReportsCSV()">
                    <i class="fa-solid fa-file-csv me-1 text-success"></i> Export CSV
                </button>
                <button class="btn btn-outline-light fw-semibold px-3 py-2 rounded-3" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Print Report
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary text-white border-secondary px-3 py-2 rounded-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Report Generator Filter Card -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:14px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="reportForm">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-dark">Select Report Category *</label>
                <select class="form-select form-select-sm fw-bold" name="report_type" id="reportSelect" onchange="toggleFilterFields()">
                    <option value="result_summary" <?php echo $selectedReport === 'result_summary' ? 'selected' : ''; ?>>1. Result Pass / Fail Summary</option>
                    <option value="subject_wise" <?php echo $selectedReport === 'subject_wise' ? 'selected' : ''; ?>>2. Subject-Wise Performance Analysis</option>
                    <option value="class_wise" <?php echo $selectedReport === 'class_wise' ? 'selected' : ''; ?>>3. Class-Wise Complete Roster Sheet</option>
                    <option value="pass_fail" <?php echo $selectedReport === 'pass_fail' ? 'selected' : ''; ?>>4. Student Pass/Fail Status List</option>
                    <option value="top_positions" <?php echo $selectedReport === 'top_positions' ? 'selected' : ''; ?>>5. Top Merit Achievers Report</option>
                    <option value="failure_report" <?php echo $selectedReport === 'failure_report' ? 'selected' : ''; ?>>6. Student Failure Records Report</option>
                    <option value="promotions_report" <?php echo $selectedReport === 'promotions_report' ? 'selected' : ''; ?>>7. Promotion Registry Journal</option>
                </select>
            </div>

            <div class="col-md-3 filter-field" id="examField">
                <label class="form-label small fw-bold text-dark">Exam Term</label>
                <select class="form-select form-select-sm" name="exam_type_id">
                    <option value="0">All Exam Terms</option>
                    <?php foreach ($examTypes as $et): ?>
                        <option value="<?php echo $et['id']; ?>" <?php echo $selectedExam === (int)$et['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($et['exam_name']); ?> (<?php echo htmlspecialchars($et['academic_session']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 filter-field" id="classField">
                <label class="form-label small fw-bold text-dark">Class & Section</label>
                <select class="form-select form-select-sm" name="class_id">
                    <option value="0">All Classes & Sections</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 filter-field" id="sessionField" style="display:none;">
                <label class="form-label small fw-bold text-dark">Academic Session</label>
                <select class="form-select form-select-sm" name="academic_session">
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $selectedSession === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 py-1.5 fw-bold">
                    <i class="fa-solid fa-arrows-rotate me-1"></i> Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Report Output Container -->
<div class="card border-0 shadow-sm mb-5" style="border-radius:14px;" id="reportPrintArea">
    
    <!-- Print Header -->
    <div class="card-header bg-white border-0 pt-4 px-4 d-none d-print-block text-center border-bottom pb-3">
        <h2 class="fw-bold text-dark mb-0">INDUS GRAMMAR SCHOOL</h2>
        <h5 class="text-primary fw-bold mb-1" id="printReportTitle"><?php echo htmlspecialchars($reportTitle); ?></h5>
        <p class="text-muted small mb-0">
            Term: <strong><?php echo htmlspecialchars($activeExamTitle); ?></strong> | 
            Date: <?php echo date('d-M-Y H:i'); ?>
        </p>
    </div>

    <!-- Onscreen Header -->
    <div class="card-header bg-white border-0 pt-4 px-4 pb-3 d-flex flex-wrap align-items-center justify-content-between gap-3 d-print-none">
        <div>
            <h5 class="fw-bold text-dark mb-0" id="reportTitle"><i class="fa-solid fa-file-chart-column me-2 text-primary"></i><?php echo htmlspecialchars($reportTitle); ?></h5>
            <small class="text-muted">Generated: <?php echo date('d-M-Y H:i'); ?> | Records Returned: <strong><?php echo count($reportData); ?></strong></small>
        </div>
        <div class="input-group input-group-sm" style="width: 250px;">
            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
            <input type="text" id="liveSearchInput" class="form-control bg-light border-start-0" placeholder="Search generated report..." onkeyup="filterReportsTable()">
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-reports table-hover align-middle mb-0 table-print-clean" id="reportDataTable">
                
                <!-- 1. RESULT SUMMARY TABLE -->
                <?php if ($selectedReport === 'result_summary'): ?>
                    <thead>
                        <tr>
                            <th>Class & Section</th>
                            <th class="text-center">Total Graded Students</th>
                            <th class="text-center text-success">Passed Candidates</th>
                            <th class="text-center text-danger">Failed Candidates</th>
                            <th class="text-center">Class Pass Rate (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No summary data found. Select an exam term and click Generate Report.</td></tr>
                        <?php else: foreach ($reportData as $row): 
                            $rate = $row['total_graded'] > 0 ? ($row['passed'] / $row['total_graded']) * 100 : 0.00;
                            ?>
                            <tr>
                                <td class="fw-bold text-dark">
                                    <i class="fa-solid fa-graduation-cap text-primary me-2"></i><?php echo htmlspecialchars($row['class_name'] . ' - ' . $row['section']); ?>
                                </td>
                                <td class="text-center fw-bold text-dark"><?php echo $row['total_graded']; ?></td>
                                <td class="text-center fw-bold text-success fs-6"><?php echo $row['passed']; ?></td>
                                <td class="text-center fw-bold text-danger fs-6"><?php echo $row['failed']; ?></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-primary border px-3 py-1.5 fw-bold fs-6">
                                        <?php echo number_format($rate, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 2. SUBJECT WISE RESULT TABLE -->
                <?php elseif ($selectedReport === 'subject_wise'): ?>
                    <thead>
                        <tr>
                            <th>Subject Code & Title</th>
                            <th class="text-center">Total Papers</th>
                            <th class="text-center text-success">Passed</th>
                            <th class="text-center text-danger">Failed</th>
                            <th class="text-end">Highest Score</th>
                            <th class="text-end">Average Score</th>
                            <th class="text-center">Pass Rate (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">Choose an exam and class section to view subject analysis.</td></tr>
                        <?php else: foreach ($reportData as $row): 
                            $rate = $row['total_papers'] > 0 ? ($row['passed'] / $row['total_papers']) * 100 : 0.00;
                            ?>
                            <tr>
                                <td class="fw-bold text-dark">
                                    <i class="fa-solid fa-book-open text-primary me-2"></i><?php echo htmlspecialchars($row['subject_name']); ?>
                                    <small class="text-muted d-block text-xs font-monospace">CODE: <?php echo htmlspecialchars($row['subject_code']); ?></small>
                                </td>
                                <td class="text-center fw-bold"><?php echo $row['total_papers']; ?></td>
                                <td class="text-center text-success fw-bold fs-6"><?php echo $row['passed']; ?></td>
                                <td class="text-center text-danger fw-bold fs-6"><?php echo $row['failed']; ?></td>
                                <td class="text-end fw-bold text-dark"><?php echo $row['highest_score'] !== null ? number_format((float)$row['highest_score'], 1) . ' / ' . $row['total_marks'] : '—'; ?></td>
                                <td class="text-end fw-bold text-muted"><?php echo $row['average_score'] !== null ? number_format((float)$row['average_score'], 1) . ' / ' . $row['total_marks'] : '—'; ?></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-primary border px-3 py-1.5 fw-bold fs-6">
                                        <?php echo number_format($rate, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 3. CLASS WISE RESULT TABLE -->
                <?php elseif ($selectedReport === 'class_wise'): ?>
                    <thead>
                        <tr>
                            <th style="width: 70px;" class="text-center">Rank</th>
                            <th>Admission No</th>
                            <th>Student Full Name</th>
                            <th class="text-end">Total Marks</th>
                            <th class="text-end">Obtained</th>
                            <th class="text-center">Percentage (%)</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">Choose an exam and class section to populate the roster.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="text-center fw-bold"><?php echo $row['position'] ? '#' . $row['position'] : '—'; ?></td>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-end fw-bold text-muted"><?php echo $row['total_marks'] !== null ? number_format((float)$row['total_marks'], 1) : '—'; ?></td>
                                <td class="text-end fw-bold text-dark"><?php echo $row['obtained_marks'] !== null ? number_format((float)$row['obtained_marks'], 1) : '—'; ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo $row['percentage'] !== null ? number_format((float)$row['percentage'], 2) . '%' : '—'; ?></td>
                                <td class="text-center fw-bold fs-6"><span class="badge bg-dark text-white px-2 py-0.5"><?php echo htmlspecialchars($row['grade'] ?: '—'); ?></span></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo strcasecmp($row['status'], 'Pass') === 0 ? 'success' : 'danger'; ?> bg-opacity-15 text-<?php echo strcasecmp($row['status'], 'Pass') === 0 ? 'success' : 'danger'; ?> px-3 py-1 rounded-pill fw-bold">
                                        <?php echo htmlspecialchars($row['status'] ?: 'Fail'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 4. PASS/FAIL REPORT TABLE -->
                <?php elseif ($selectedReport === 'pass_fail'): ?>
                    <thead>
                        <tr>
                            <th>Class & Section</th>
                            <th>Admission No</th>
                            <th>Student Full Name</th>
                            <th class="text-center">Rank</th>
                            <th class="text-center">Percentage (%)</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">Choose an exam term to load pass/fail roster.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['class_name'] . ' - ' . $row['section']); ?></td>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-center fw-semibold"><?php echo $row['position'] ? '#' . $row['position'] : '—'; ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format((float)$row['percentage'], 2); ?>%</td>
                                <td class="text-center fw-bold"><span class="badge bg-dark text-white px-2 py-0.5"><?php echo htmlspecialchars($row['grade']); ?></span></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo strcasecmp($row['status'], 'Pass') === 0 ? 'success' : 'danger'; ?> bg-opacity-15 text-<?php echo strcasecmp($row['status'], 'Pass') === 0 ? 'success' : 'danger'; ?> px-3 py-1 rounded-pill fw-bold">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 5. TOP POSITION REPORT TABLE -->
                <?php elseif ($selectedReport === 'top_positions'): ?>
                    <thead>
                        <tr>
                            <th>Class & Section</th>
                            <th style="width: 80px;" class="text-center">Rank</th>
                            <th>Admission No</th>
                            <th>Student Full Name</th>
                            <th class="text-center">Percentage (%)</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">Select an exam term to view top merit holders.</td></tr>
                        <?php else: foreach ($reportData as $row): 
                            $rank = (int)$row['position_no'];
                            $rankClass = 'rank-other';
                            if ($rank === 1) $rankClass = 'rank-1-gold';
                            elseif ($rank === 2) $rankClass = 'rank-2-silver';
                            elseif ($rank === 3) $rankClass = 'rank-3-bronze';
                        ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['class_name'] . ' - ' . $row['section']); ?></td>
                                <td class="text-center">
                                    <div class="rank-badge-crown <?php echo $rankClass; ?>" style="width:34px; height:34px; font-size:0.85rem;">
                                        <?php echo $rank; ?>
                                    </div>
                                </td>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format((float)$row['percentage'], 2); ?>%</td>
                                <td class="text-center fw-bold"><span class="badge bg-dark text-white px-2 py-0.5"><?php echo htmlspecialchars($row['grade']); ?></span></td>
                                <td class="text-center">
                                    <span class="badge bg-success bg-opacity-15 text-success px-3 py-1 rounded-pill fw-bold">PASS</span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 6. FAILURE REPORT TABLE -->
                <?php elseif ($selectedReport === 'failure_report'): ?>
                    <thead>
                        <tr>
                            <th>Class & Section</th>
                            <th>Admission No</th>
                            <th>Student Full Name</th>
                            <th class="text-center">Obtained Percentage (%)</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No failure records found for selected criteria.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['class_name'] . ' - ' . $row['section']); ?></td>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-danger"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format((float)$row['percentage'], 2); ?>%</td>
                                <td class="text-center fw-bold fs-6 text-danger"><?php echo htmlspecialchars($row['grade']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-danger bg-opacity-15 text-danger px-3 py-1 rounded-pill fw-bold">FAIL</span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 7. PROMOTIONS REPORT TABLE -->
                <?php elseif ($selectedReport === 'promotions_report'): ?>
                    <thead>
                        <tr>
                            <th>Admission No</th>
                            <th>Student Full Name</th>
                            <th class="text-center">From Class Section</th>
                            <th class="text-center">Promoted To Class</th>
                            <th class="text-center">Promotion Date</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No student promotion records found in session <?php echo htmlspecialchars($selectedSession); ?>.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-center"><span class="badge bg-light text-muted border px-2.5 py-1"><?php echo htmlspecialchars($row['from_class'] . ' - ' . $row['from_section']); ?></span></td>
                                <td class="text-center"><span class="badge bg-primary text-white px-2.5 py-1"><?php echo htmlspecialchars($row['to_class'] . ' - ' . $row['to_section']); ?></span></td>
                                <td class="text-center small fw-semibold"><?php echo date('d-M-Y', strtotime($row['promotion_date'])); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success bg-opacity-15 text-success px-3 py-1 rounded-pill fw-bold"><?php echo htmlspecialchars($row['status']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                <?php endif; ?>

            </table>
        </div>
    </div>

    <!-- Print Footer -->
    <div class="card-footer bg-white border-0 pt-5 pb-4 d-none d-print-block">
        <div class="row text-center mt-4">
            <div class="col-6">
                <div class="border-top pt-2 mx-5">
                    <span class="fw-bold text-dark small">Controller of Examinations</span>
                </div>
            </div>
            <div class="col-6">
                <div class="border-top pt-2 mx-5">
                    <span class="fw-bold text-dark small">Principal Signature</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function toggleFilterFields() {
    const reportType = document.getElementById("reportSelect").value;
    const examField = document.getElementById("examField");
    const classField = document.getElementById("classField");
    const sessionField = document.getElementById("sessionField");

    examField.style.display = "block";
    classField.style.display = "block";
    sessionField.style.display = "none";

    if (reportType === "promotions_report") {
        examField.style.display = "none";
        classField.style.display = "none";
        sessionField.style.display = "block";
    }
}

function filterReportsTable() {
    const query = document.getElementById("liveSearchInput").value.toLowerCase();
    const rows = document.querySelectorAll("#reportDataTable tbody tr");
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = text.includes(query) ? "" : "none";
    });
}

function exportReportsCSV() {
    let csv = [];
    let rows = document.querySelectorAll("#reportDataTable tr");
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
            let text = cols[j].innerText.replace(/(\\r\\n|\\n|\\r)/gm, " ").replace(/\\s+/g, " ").trim();
            row.push(\'"\' + text + \'"\');
        }
        if (row.length > 0) csv.push(row.join(","));
    }
    let csvFile = new Blob([csv.join("\\n")], { type: "text/csv" });
    let downloadLink = document.createElement("a");
    downloadLink.download = "exam_report_" + new Date().toISOString().slice(0,10) + ".csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

document.addEventListener("DOMContentLoaded", function() {
    toggleFilterFields();
});
</script>';

include_once __DIR__ . '/../../includes/footer.php'; ?>
