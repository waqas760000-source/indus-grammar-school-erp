<?php
/**
 * Indus Grammar School ERP - Consolidated Examination Performance Reports
 * Version 4.0.0 (Academic Intelligence & Rank Standings Suite)
 */

$pageTitle = 'Examination Performance Reports';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Selectors
$examTypes = [];
try {
    $examTypes = $db->query("SELECT * FROM exam_types ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$classes = SchoolClass::all();

// Filter values
$selectedReport = sanitize($_GET['report_type'] ?? 'result_summary');
$selectedExam   = isset($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : 0;
$selectedClass  = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$searchKeyword  = sanitize($_GET['q'] ?? '');

// Auto-select latest exam if none specified
if ($selectedExam === 0 && !empty($examTypes)) {
    $selectedExam = (int)$examTypes[0]['id'];
}

$reportTitle = "Examination Performance Audit";
$reportData = [];

// Overall Scope KPIs
$kpiTotalGraded = 0;
$kpiPassed = 0;
$kpiFailed = 0;
$kpiAvgPct = 0;
$kpiTopStudent = 'N/A';
$kpiTopScorePct = 0;

try {
    if ($selectedExam > 0) {
        $kpiSql = "
            SELECT 
                COUNT(*) as total_graded,
                SUM(CASE WHEN status = 'Pass' THEN 1 ELSE 0 END) as total_pass,
                SUM(CASE WHEN status = 'Fail' THEN 1 ELSE 0 END) as total_fail,
                AVG(percentage) as avg_pct
            FROM exam_results
            WHERE exam_type_id = :etid
        ";
        $kpiParams = ['etid' => $selectedExam];
        if ($selectedClass > 0) {
            $kpiSql .= " AND class_id = :cid";
            $kpiParams['cid'] = $selectedClass;
        }
        $kpiStmt = $db->prepare($kpiSql);
        $kpiStmt->execute($kpiParams);
        $kpiRes = $kpiStmt->fetch(PDO::FETCH_ASSOC);

        if ($kpiRes) {
            $kpiTotalGraded = (int)$kpiRes['total_graded'];
            $kpiPassed      = (int)$kpiRes['total_pass'];
            $kpiFailed      = (int)$kpiRes['total_fail'];
            $kpiAvgPct      = round((float)$kpiRes['avg_pct'], 1);
        }

        // Top student in scope
        $topSql = "
            SELECT st.first_name, st.last_name, er.percentage
            FROM exam_results er
            JOIN students st ON er.student_id = st.id
            WHERE er.exam_type_id = :etid
        ";
        $topParams = ['etid' => $selectedExam];
        if ($selectedClass > 0) {
            $topSql .= " AND er.class_id = :cid";
            $topParams['cid'] = $selectedClass;
        }
        $topSql .= " ORDER BY er.percentage DESC LIMIT 1";
        $topStmt = $db->prepare($topSql);
        $topStmt->execute($topParams);
        $topRow = $topStmt->fetch(PDO::FETCH_ASSOC);
        if ($topRow) {
            $kpiTopStudent = $topRow['first_name'] . ' ' . $topRow['last_name'];
            $kpiTopScorePct = round((float)$topRow['percentage'], 1);
        }
    }

    switch ($selectedReport) {
        
        case 'result_summary':
            $reportTitle = "Result Pass/Fail Summary Report";
            if ($selectedExam > 0) {
                $sql = "
                    SELECT c.class_name, c.section,
                           SUM(CASE WHEN er.status = 'Pass' THEN 1 ELSE 0 END) as passed,
                           SUM(CASE WHEN er.status = 'Fail' THEN 1 ELSE 0 END) as failed,
                           COUNT(er.id) as total_graded,
                           AVG(er.percentage) as class_avg_pct
                    FROM exam_results er
                    JOIN classes c ON er.class_id = c.id
                    WHERE er.exam_type_id = :etid
                ";
                $params = ['etid' => $selectedExam];
                if ($selectedClass > 0) {
                    $sql .= " AND er.class_id = :cid";
                    $params['cid'] = $selectedClass;
                }
                $sql .= " GROUP BY c.id ORDER BY c.class_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'subject_wise':
            $reportTitle = "Subject-Wise Performance & Averages Breakdown";
            if ($selectedExam > 0) {
                $sql = "
                    SELECT s.subject_name, s.subject_code, s.total_marks, s.passing_marks, c.class_name, c.section,
                           COUNT(sm.id) as total_papers,
                           SUM(CASE WHEN sm.status = 'Present' AND sm.marks_obtained >= s.passing_marks THEN 1 ELSE 0 END) as passed,
                           SUM(CASE WHEN sm.status = 'Present' AND sm.marks_obtained < s.passing_marks THEN 1 ELSE 0 END) as failed,
                           MAX(sm.marks_obtained) as highest_score,
                           AVG(sm.marks_obtained) as average_score
                    FROM subjects s
                    JOIN classes c ON s.class_id = c.id
                    LEFT JOIN student_marks sm ON sm.subject_id = s.id AND sm.exam_type_id = :etid
                    WHERE s.status = 'Active'
                ";
                $params = ['etid' => $selectedExam];
                if ($selectedClass > 0) {
                    $sql .= " AND s.class_id = :cid";
                    $params['cid'] = $selectedClass;
                }
                if ($searchKeyword !== '') {
                    $sql .= " AND (s.subject_name LIKE :q OR s.subject_code LIKE :q)";
                    $params['q'] = '%' . $searchKeyword . '%';
                }
                $sql .= " GROUP BY s.id ORDER BY c.class_name ASC, s.subject_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'class_wise':
            $reportTitle = "Class Complete Grade Sheet & Rank Ledger";
            if ($selectedExam > 0 && $selectedClass > 0) {
                $sql = "
                    SELECT st.first_name, st.last_name, st.admission_no,
                           er.total_marks, er.obtained_marks, er.percentage, er.grade, er.position, er.status
                    FROM students st
                    LEFT JOIN exam_results er ON er.student_id = st.id AND er.exam_type_id = :etid
                    WHERE st.class_id = :cid AND st.status = 'Active'
                ";
                $params = ['etid' => $selectedExam, 'cid' => $selectedClass];
                if ($searchKeyword !== '') {
                    $sql .= " AND (st.first_name LIKE :q OR st.last_name LIKE :q OR st.admission_no LIKE :q)";
                    $params['q'] = '%' . $searchKeyword . '%';
                }
                $sql .= " ORDER BY er.position ASC, st.first_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'pass_report':
            $reportTitle = "Passed Students List (Merit Stands)";
            if ($selectedExam > 0) {
                $sql = "
                    SELECT er.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section
                    FROM exam_results er
                    JOIN students st ON er.student_id = st.id
                    JOIN classes c ON er.class_id = c.id
                    WHERE er.exam_type_id = :etid AND er.status = 'Pass'
                ";
                $params = ['etid' => $selectedExam];
                if ($selectedClass > 0) {
                    $sql .= " AND er.class_id = :cid";
                    $params['cid'] = $selectedClass;
                }
                if ($searchKeyword !== '') {
                    $sql .= " AND (st.first_name LIKE :q OR st.last_name LIKE :q OR st.admission_no LIKE :q)";
                    $params['q'] = '%' . $searchKeyword . '%';
                }
                $sql .= " ORDER BY c.class_name ASC, er.position ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'fail_report':
            $reportTitle = "Failed Students List (Defaulters Roster)";
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
                if ($searchKeyword !== '') {
                    $sql .= " AND (st.first_name LIKE :q OR st.last_name LIKE :q OR st.admission_no LIKE :q)";
                    $params['q'] = '%' . $searchKeyword . '%';
                }
                $sql .= " ORDER BY c.class_name ASC, st.first_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'top_positions':
            $reportTitle = "Merit Top Ranks Achievement Report";
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
                if ($searchKeyword !== '') {
                    $sql .= " AND (st.first_name LIKE :q OR st.last_name LIKE :q OR st.admission_no LIKE :q)";
                    $params['q'] = '%' . $searchKeyword . '%';
                }
                $sql .= " ORDER BY c.class_name ASC, p.position_no ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'grade_distribution':
            $reportTitle = "Grade Scales Count Breakdown";
            if ($selectedExam > 0) {
                $sql = "
                    SELECT grade, COUNT(*) as student_count, AVG(percentage) as avg_percentage
                    FROM exam_results 
                    WHERE exam_type_id = :etid
                ";
                $params = ['etid' => $selectedExam];
                if ($selectedClass > 0) {
                    $sql .= " AND class_id = :cid";
                    $params['cid'] = $selectedClass;
                }
                $sql .= " GROUP BY grade ORDER BY avg_percentage DESC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
    }
} catch (Exception $e) {
    error_log("Exam reports error: " . $e->getMessage());
}

$kpiPassRatePct = $kpiTotalGraded > 0 ? round(($kpiPassed / $kpiTotalGraded) * 100, 1) : 0;
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

:root {
    --ex-font: 'Outfit', sans-serif;
    --ex-purple: #8b5cf6;
    --ex-indigo: #4f46e5;
    --ex-dark: #0f172a;
    --ex-card-bg: #ffffff;
    --ex-border: #e2e8f0;
    --ex-radius: 16px;
    --ex-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.04), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
}

body {
    font-family: var(--ex-font);
    background-color: #f8fafc;
}

.ex-hero-card {
    background: linear-gradient(135deg, #2e1065 0%, #5b21b6 50%, #7c3aed 100%);
    border-radius: var(--ex-radius);
    padding: 2.25rem 2rem;
    color: #ffffff;
    box-shadow: 0 20px 25px -5px rgba(124, 58, 237, 0.25);
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
}

.ex-hero-card::before {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.ex-kpi-card {
    background: var(--ex-card-bg);
    border: 1px solid var(--ex-border);
    border-radius: var(--ex-radius);
    padding: 1.35rem 1.25rem;
    box-shadow: var(--ex-shadow);
    height: 100%;
    transition: transform 0.2s ease;
}

.ex-kpi-card:hover {
    transform: translateY(-3px);
}

.ex-kpi-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.ex-kpi-val {
    font-weight: 700;
    font-size: 1.55rem;
    color: var(--ex-dark);
    line-height: 1.2;
    margin-top: 0.35rem;
}

.custom-table-card {
    background: var(--ex-card-bg);
    border: 1px solid var(--ex-border);
    border-radius: var(--ex-radius);
    box-shadow: var(--ex-shadow);
    overflow: hidden;
}

.custom-table th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--ex-border);
}

.custom-table td {
    padding: 1.1rem 1.25rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.925rem;
}

.custom-table tbody tr:hover {
    background-color: #f8fafc;
}

@media print {
    body { background: #fff !important; }
    .no-print, .btn, nav, header, sidebar { display: none !important; }
    .ex-hero-card { background: #2e1065 !important; color: #fff !important; }
    #reportPrintArea { position: static !important; }
}
</style>

<div class="container-fluid px-0">

    <!-- Executive Hero Header -->
    <div class="ex-hero-card">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small fw-semibold">
                        <i class="fa-solid fa-graduation-cap me-1 text-warning"></i> Academic Evaluation Audit
                    </span>
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small">
                        <?php echo number_format($kpiTotalGraded); ?> Graded Candidates
                    </span>
                </div>
                <h1 class="fw-bold mb-2" style="font-size:1.95rem; letter-spacing:-0.02em;"><?php echo sanitize($reportTitle); ?></h1>
                <p class="text-white-50 mb-0 leading-relaxed" style="max-width: 650px;">
                    Consolidate subject averages, verify class position standings, and check pass/fail rate distributions across academic evaluation terms.
                </p>
            </div>
            <div class="col-lg-5 text-lg-end mt-4 mt-lg-0 no-print">
                <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end">
                    <button class="btn btn-light fw-bold text-dark px-4 py-2 rounded-3 shadow-sm" onclick="exportToExcel()">
                        <i class="fa-solid fa-file-excel text-success me-2"></i>Export Excel / CSV
                    </button>
                    <button class="btn btn-outline-light px-3 py-2 rounded-3" onclick="window.print()">
                        <i class="fa-solid fa-print me-2"></i>Print Ledger
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-light px-3 py-2 rounded-3">
                        <i class="fa-solid fa-arrow-left me-2"></i>Hub
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end" id="filterForm">
                
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-dark">Report Focus / Analysis</label>
                    <select class="form-select" name="report_type" onchange="this.form.submit()">
                        <option value="result_summary" <?php echo $selectedReport === 'result_summary' ? 'selected' : ''; ?>>Pass / Fail Summary</option>
                        <option value="subject_wise" <?php echo $selectedReport === 'subject_wise' ? 'selected' : ''; ?>>Subject Averages Distribution</option>
                        <option value="class_wise" <?php echo $selectedReport === 'class_wise' ? 'selected' : ''; ?>>Class Complete Grade Sheet</option>
                        <option value="pass_report" <?php echo $selectedReport === 'pass_report' ? 'selected' : ''; ?>>Passed Merit List</option>
                        <option value="fail_report" <?php echo $selectedReport === 'fail_report' ? 'selected' : ''; ?>>Failed Defaulters Roster</option>
                        <option value="top_positions" <?php echo $selectedReport === 'top_positions' ? 'selected' : ''; ?>>Top Ranks Position Holders</option>
                        <option value="grade_distribution" <?php echo $selectedReport === 'grade_distribution' ? 'selected' : ''; ?>>Grade Scales Breakdown</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-dark">Exam Term *</label>
                    <select class="form-select" name="exam_type_id" required>
                        <option value="">— Choose Exam Term —</option>
                        <?php foreach ($examTypes as $et): ?>
                            <option value="<?php echo $et['id']; ?>" <?php echo $selectedExam === (int)$et['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($et['exam_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-dark">Class & Section</label>
                    <select class="form-select" name="class_id">
                        <option value="0">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-dark">Search Keyword</label>
                    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="Student name, admission #, subject...">
                </div>

                <div class="col-12 text-end">
                    <a href="examination.php" class="btn btn-outline-secondary px-4 me-2"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
                    <button type="submit" class="btn btn-purple text-white px-5 fw-bold" style="background:#7c3aed; border-color:#7c3aed;">
                        <i class="fa-solid fa-magnifying-glass me-2"></i>Compile Exam Analytics
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- Academic KPI Analytics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="ex-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Evaluated Candidates</span>
                    <div class="ex-kpi-icon bg-purple bg-opacity-10 text-purple" style="color:#7c3aed;">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                </div>
                <div class="ex-kpi-val"><?php echo number_format($kpiTotalGraded); ?></div>
                <div class="mt-2 text-muted small">
                    <span class="badge bg-success bg-opacity-10 text-success fw-bold"><?php echo $kpiPassed; ?> Passed</span>
                    <?php if ($kpiFailed > 0): ?>
                        <span class="badge bg-danger bg-opacity-10 text-danger fw-bold ms-1"><?php echo $kpiFailed; ?> Failed</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="ex-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Term Pass Rate</span>
                    <div class="ex-kpi-icon bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-award"></i>
                    </div>
                </div>
                <div class="ex-kpi-val text-success"><?php echo $kpiPassRatePct; ?>%</div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-chart-line me-1 text-success"></i> Passed evaluation threshold
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="ex-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Mean Percentage</span>
                    <div class="ex-kpi-icon bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                </div>
                <div class="ex-kpi-val text-info"><?php echo $kpiAvgPct; ?>%</div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-square-poll-vertical me-1 text-info"></i> Average score across subjects
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="ex-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Top Position Rank 1</span>
                    <div class="ex-kpi-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-crown"></i>
                    </div>
                </div>
                <div class="ex-kpi-val text-truncate" style="font-size:1.35rem;" title="<?php echo sanitize($kpiTopStudent); ?>">
                    <?php echo sanitize($kpiTopStudent); ?>
                </div>
                <div class="mt-2 text-muted small">
                    <span class="fw-bold text-dark"><?php echo $kpiTopScorePct; ?>%</span> Highest percentage
                </div>
            </div>
        </div>
    </div>

    <!-- Main Output Card -->
    <div class="custom-table-card shadow-sm mb-4" id="reportPrintArea">
        
        <!-- Print Header -->
        <div class="p-4 text-center d-none d-print-block border-bottom">
            <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h2>
            <h4 class="text-secondary fw-semibold mb-1"><?php echo sanitize($reportTitle); ?></h4>
            <div class="text-muted small">
                Printed Date: <?php echo date('d-M-Y H:i'); ?> | Graded Candidates: <?php echo number_format($kpiTotalGraded); ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                
                <!-- 1. RESULT SUMMARY -->
                <?php if ($selectedReport === 'result_summary'): ?>
                    <thead>
                        <tr>
                            <th>Class & Section</th>
                            <th class="text-center">Graded Candidates</th>
                            <th class="text-center text-success">Passed</th>
                            <th class="text-center text-danger">Failed</th>
                            <th class="text-end">Class Mean Score</th>
                            <th class="text-center">Pass Rate %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No result summaries compiled. Choose an exam term and click compile.</td></tr>
                        <?php else: foreach ($reportData as $row): 
                            $rate = $row['total_graded'] > 0 ? ($row['passed'] / $row['total_graded']) * 100 : 0.00;
                            ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo sanitize($row['class_name'] . ' - ' . $row['section']); ?></td>
                                <td class="text-center fw-semibold text-muted"><?php echo (int)$row['total_graded']; ?> Students</td>
                                <td class="text-center fw-bold text-success"><?php echo (int)$row['passed']; ?></td>
                                <td class="text-center fw-bold text-danger"><?php echo (int)$row['failed']; ?></td>
                                <td class="text-end fw-semibold text-dark"><?php echo number_format((float)$row['class_avg_pct'], 1); ?>%</td>
                                <td class="text-center fw-bold">
                                    <span class="badge bg-<?php echo $rate >= 75 ? 'success' : ($rate >= 50 ? 'warning' : 'danger'); ?> bg-opacity-10 text-<?php echo $rate >= 75 ? 'success' : ($rate >= 50 ? 'warning-dark' : 'danger'); ?> px-3 py-1 rounded-pill">
                                        <?php echo number_format($rate, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 2. SUBJECT PERFORMANCE -->
                <?php elseif ($selectedReport === 'subject_wise'): ?>
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Subject & Code</th>
                            <th class="text-center">Total Papers</th>
                            <th class="text-center text-success">Passed</th>
                            <th class="text-center text-danger">Failed</th>
                            <th class="text-end">Highest Score</th>
                            <th class="text-end">Average Score</th>
                            <th class="text-center">Pass Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">Choose an exam and class section to display subject metrics.</td></tr>
                        <?php else: foreach ($reportData as $row): 
                            $rate = $row['total_papers'] > 0 ? ($row['passed'] / $row['total_papers']) * 100 : 0.00;
                            ?>
                            <tr>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill"><?php echo sanitize($row['class_name'] . ' - ' . $row['section']); ?></span></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo sanitize($row['subject_name']); ?></div>
                                    <small class="text-muted text-xs"><?php echo sanitize($row['subject_code']); ?></small>
                                </td>
                                <td class="text-center text-muted fw-bold"><?php echo (int)$row['total_papers']; ?> Papers</td>
                                <td class="text-center text-success fw-bold"><?php echo (int)$row['passed']; ?></td>
                                <td class="text-center text-danger fw-bold"><?php echo (int)$row['failed']; ?></td>
                                <td class="text-end fw-bold text-dark"><?php echo $row['highest_score'] !== null ? number_format((float)$row['highest_score'], 1) . ' / ' . $row['total_marks'] : '—'; ?></td>
                                <td class="text-end fw-semibold text-muted"><?php echo $row['average_score'] !== null ? number_format((float)$row['average_score'], 1) . ' / ' . $row['total_marks'] : '—'; ?></td>
                                <td class="text-center fw-bold">
                                    <span class="badge bg-<?php echo $rate >= 75 ? 'success' : ($rate >= 50 ? 'warning' : 'danger'); ?> bg-opacity-10 text-<?php echo $rate >= 75 ? 'success' : ($rate >= 50 ? 'warning-dark' : 'danger'); ?> px-3 py-1 rounded-pill">
                                        <?php echo number_format($rate, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 3. CLASS WISE COMPLETE ROSTER -->
                <?php elseif ($selectedReport === 'class_wise'): ?>
                    <thead>
                        <tr>
                            <th class="text-center" width="80">Position</th>
                            <th>Admission #</th>
                            <th>Student Name</th>
                            <th class="text-end">Total Marks</th>
                            <th class="text-end">Obtained Marks</th>
                            <th class="text-center">Percentage</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">Choose exam term and class to load grade sheet rosters.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="text-center">
                                    <?php if ($row['status'] === 'Pass'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-1 rounded-pill fw-bold">Rank #<?php echo (int)$row['position']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border px-3 py-1 rounded-pill">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><code class="fw-bold text-primary">#<?php echo sanitize($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-end text-muted"><?php echo number_format((float)$row['total_marks'], 0); ?></td>
                                <td class="text-end fw-bold text-dark"><?php echo number_format((float)$row['obtained_marks'], 1); ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format((float)$row['percentage'], 1); ?>%</td>
                                <td class="text-center fw-bold fs-5 text-secondary"><?php echo sanitize($row['grade']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Pass' ? 'success' : 'danger'; ?> bg-opacity-10 text-<?php echo $row['status'] === 'Pass' ? 'success' : 'danger'; ?> px-3 py-1 rounded-pill fw-bold">
                                        <?php echo sanitize($row['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 4. PASS/FAIL REPORT LIST -->
                <?php elseif (in_array($selectedReport, ['pass_report', 'fail_report'])): ?>
                    <thead>
                        <tr>
                            <th>Class & Section</th>
                            <th>Admission #</th>
                            <th>Student Name</th>
                            <th class="text-center">Position Rank</th>
                            <th class="text-center">Percentage</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No student records found matching this outcome.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="fw-bold text-secondary"><?php echo sanitize($row['class_name'] . ' - ' . $row['section']); ?></td>
                                <td><code class="fw-bold text-primary">#<?php echo sanitize($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-center fw-semibold">Rank #<?php echo (int)$row['position']; ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format((float)$row['percentage'], 1); ?>%</td>
                                <td class="text-center fw-bold fs-5 text-secondary"><?php echo sanitize($row['grade']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Pass' ? 'success' : 'danger'; ?> bg-opacity-10 text-<?php echo $row['status'] === 'Pass' ? 'success' : 'danger'; ?> px-3 py-1 rounded-pill fw-bold">
                                        <?php echo sanitize($row['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 5. TOP POSITIONS -->
                <?php elseif ($selectedReport === 'top_positions'): ?>
                    <thead>
                        <tr>
                            <th>Class Section</th>
                            <th class="text-center">Merit Rank</th>
                            <th>Admission #</th>
                            <th>Student Name</th>
                            <th class="text-center">Percentage</th>
                            <th class="text-center">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">Choose an exam term and click compile.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="fw-bold text-secondary"><?php echo sanitize($row['class_name'] . ' - ' . $row['section']); ?></td>
                                <td class="text-center">
                                    <?php 
                                        $rank = (int)$row['position_no'];
                                        if ($rank === 1) echo '<span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold"><i class="fa-solid fa-crown me-1"></i>1st Position</span>';
                                        else if ($rank === 2) echo '<span class="badge bg-secondary text-white px-3 py-1 rounded-pill fw-bold">2nd Position</span>';
                                        else if ($rank === 3) echo '<span class="badge bg-danger text-white px-3 py-1 rounded-pill fw-bold">3rd Position</span>';
                                    ?>
                                </td>
                                <td><code class="fw-bold text-primary">#<?php echo sanitize($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format((float)$row['percentage'], 1); ?>%</td>
                                <td class="text-center fw-bold fs-5 text-secondary"><?php echo sanitize($row['grade']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 6. GRADE DISTRIBUTION -->
                <?php elseif ($selectedReport === 'grade_distribution'): ?>
                    <thead>
                        <tr>
                            <th>Grade Scale</th>
                            <th class="text-center">Students Count</th>
                            <th class="text-center">Average Percentage</th>
                            <th class="text-end">Share %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">No grade scale distributions found.</td></tr>
                        <?php else: foreach ($reportData as $row): 
                            $gCount = (int)$row['student_count'];
                            $gPct = $kpiTotalGraded > 0 ? ($gCount / $kpiTotalGraded) * 100 : 0;
                        ?>
                            <tr>
                                <td class="fw-bold text-dark fs-5"><?php echo sanitize($row['grade']); ?></td>
                                <td class="text-center fw-bold text-muted"><?php echo $gCount; ?> Candidates</td>
                                <td class="text-center fw-semibold text-primary"><?php echo number_format((float)$row['avg_percentage'], 1); ?>%</td>
                                <td class="text-end">
                                    <span class="badge bg-purple bg-opacity-10 text-purple px-3 py-1 rounded-pill fw-bold" style="color:#7c3aed;">
                                        <?php echo number_format($gPct, 1); ?>% Share
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                <?php endif; ?>

            </table>
        </div>
    </div>

</div>

<script>
function exportToExcel() {
    let table = document.getElementById("reportDataTable");
    if (!table) return;
    let html = table.outerHTML;
    let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
    let link = document.createElement("a");
    link.download = "<?php echo strtolower(str_replace(' ', '_', $reportTitle)); ?>_<?php echo date('Ymd'); ?>.xls";
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>