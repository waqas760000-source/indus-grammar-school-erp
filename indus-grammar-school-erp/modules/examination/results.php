<?php
/**
 * Indus Grammar School ERP - Result Processing & Class Position Ledgers
 * Version 4.0.0
 */

$pageTitle = 'Class Results & Ranking Ledger';
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

$selectedExam  = isset($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : 0;
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

$results = [];
$examInfo = null;
$classInfo = null;

if ($selectedExam > 0 && $selectedClass > 0) {
    $results = ExamResult::getClassResults($selectedExam, $selectedClass);
    
    foreach ($examTypes as $et) {
        if ((int)$et['id'] === $selectedExam) {
            $examInfo = $et;
            break;
        }
    }
    foreach ($classes as $c) {
        if ((int)$c['id'] === $selectedClass) {
            $classInfo = $c;
            break;
        }
    }
}

// Compute Metrics
$totalStudents = count($results);
$passCount     = 0;
$failCount     = 0;
$topStudent    = null;
$pctSum        = 0;
$highestPct    = 0.00;

foreach ($results as $r) {
    $st = $r['status'] ?? 'Fail';
    $pct = (float)($r['percentage'] ?? 0.00);
    $pctSum += $pct;
    
    if (strcasecmp($st, 'Pass') === 0) {
        $passCount++;
    } else {
        $failCount++;
    }

    if ($pct > $highestPct || $topStudent === null) {
        $highestPct = $pct;
        if (strcasecmp($st, 'Pass') === 0) {
            $topStudent = $r;
        }
    }
}

$passRate = $totalStudents > 0 ? round(($passCount / $totalStudents) * 100, 1) : 0;
$avgPct   = $totalStudents > 0 ? round($pctSum / $totalStudents, 1) : 0;

$activeExamTitle = $examInfo ? $examInfo['exam_name'] : 'Select Exam Term';
$activeClassTitle = $classInfo ? ($classInfo['class_name'] . ' - ' . $classInfo['section']) : 'Select Class';
?>

<!-- Custom CSS Styling -->
<style>
.results-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0369a1 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 2.2rem 2rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
    border-bottom: 3px solid #38bdf8;
    position: relative;
    overflow: hidden;
}
.results-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(56, 189, 248, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
}
.kpi-card-results {
    border: none;
    border-radius: 14px;
    background: #ffffff;
    transition: all 0.25s ease-in-out;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
}
.kpi-card-results:hover {
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
.badge-soft-sky { background-color: rgba(56, 189, 248, 0.12); color: #0284c7; }
.badge-soft-emerald { background-color: rgba(16, 185, 129, 0.12); color: #059669; }
.badge-soft-amber { background-color: rgba(245, 158, 11, 0.12); color: #d97706; }
.badge-soft-rose { background-color: rgba(244, 63, 94, 0.12); color: #e11d48; }
.badge-soft-purple { background-color: rgba(168, 85, 247, 0.12); color: #9333ea; }

.table-results thead th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    font-weight: 700;
    padding: 1rem 0.85rem;
    border-bottom: 2px solid #e2e8f0;
}
.table-results tbody td {
    padding: 1rem 0.85rem;
    vertical-align: middle;
}
.nav-pills-custom .nav-link {
    color: #64748b;
    font-weight: 600;
    border-radius: 10px;
    padding: 0.55rem 1.2rem;
    transition: all 0.2s ease;
}
.nav-pills-custom .nav-link.active {
    background-color: #0f172a;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
}
.rank-badge-crown {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.9rem;
}
.rank-1-gold { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #ffffff; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3); }
.rank-2-silver { background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%); color: #ffffff; }
.rank-3-bronze { background: linear-gradient(135deg, #d97706 0%, #b45309 100%); color: #ffffff; }
.rank-other { background: #e2e8f0; color: #475569; }

@media print {
    body * { visibility: hidden; }
    #resultLedgerArea, #resultLedgerArea * { visibility: visible; }
    #resultLedgerArea {
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
<div class="results-hero mb-4 d-print-none">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-info bg-opacity-25 text-info px-3 py-1 rounded-pill fw-semibold small" style="color:#7dd3fc;">
                    <i class="fa-solid fa-square-poll-vertical me-1"></i> Class Result Ledger Engine
                </span>
                <span class="badge bg-warning bg-opacity-25 text-warning px-3 py-1 rounded-pill fw-semibold small">
                    <i class="fa-solid fa-crown me-1"></i> Position Rankings Automated
                </span>
            </div>
            <h2 class="fw-bold text-white mb-2">
                <i class="fa-solid fa-trophy text-info me-2" style="color:#38bdf8;"></i>Class Results & Position Ledger
            </h2>
            <p class="text-white-50 mb-0">
                Compile student examination marks, evaluate class rankings, compute percentage grades, and publish official result ledgers.
            </p>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                <?php if ($selectedExam > 0 && $selectedClass > 0): ?>
                    <button class="btn btn-warning text-dark fw-bold px-3 py-2 shadow-sm rounded-3" id="btnCompile" onclick="compileResults()">
                        <i class="fa-solid fa-arrows-rotate me-1"></i> Calculate & Rank Results
                    </button>
                    <button class="btn btn-light fw-semibold px-3 py-2 shadow-sm rounded-3" onclick="exportResultsCSV()">
                        <i class="fa-solid fa-file-csv me-1 text-success"></i> Export CSV
                    </button>
                    <button class="btn btn-outline-light fw-semibold px-3 py-2 rounded-3" onclick="window.print()">
                        <i class="fa-solid fa-print me-1"></i> Print Ledger
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
            <div class="col-md-5">
                <label class="form-label small fw-bold text-dark">Select Exam Term *</label>
                <select class="form-select form-select-sm" name="exam_type_id" required onchange="this.form.submit()">
                    <option value="">-- Select Exam Term --</option>
                    <?php foreach ($examTypes as $et): ?>
                        <option value="<?php echo $et['id']; ?>" <?php echo $selectedExam === (int)$et['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($et['exam_name']); ?> (<?php echo htmlspecialchars($et['academic_session']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
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
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 py-1.5 fw-bold">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Load Ledger
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedExam > 0 && $selectedClass > 0): ?>

    <!-- KPI Summary Bar for Active Class Result -->
    <div class="row g-3 mb-4 d-print-none">
        <!-- KPI 1: Total Processed -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card-results p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Enrolled</span>
                        <h3 class="fw-bold text-dark mb-0"><?php echo $totalStudents; ?></h3>
                        <small class="text-sky fw-semibold" style="color:#0284c7;"><i class="fa-solid fa-users me-1"></i>Students</small>
                    </div>
                    <div class="kpi-icon-wrapper badge-soft-sky">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 2: Passed Count & Rate -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card-results p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Passed Students</span>
                        <h3 class="fw-bold text-emerald mb-0" style="color:#059669;"><?php echo $passCount; ?></h3>
                        <small class="text-emerald fw-semibold" style="color:#059669;"><i class="fa-solid fa-circle-check me-1"></i><?php echo $passRate; ?>% Pass Rate</small>
                    </div>
                    <div class="kpi-icon-wrapper badge-soft-emerald">
                        <i class="fa-solid fa-award"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 3: Failed Count -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card-results p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Failed Students</span>
                        <h3 class="fw-bold text-rose mb-0" style="color:#e11d48;"><?php echo $failCount; ?></h3>
                        <small class="text-rose fw-semibold" style="color:#e11d48;"><i class="fa-solid fa-circle-xmark me-1"></i>Requires Retake</small>
                    </div>
                    <div class="kpi-icon-wrapper badge-soft-rose">
                        <i class="fa-solid fa-user-minus"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 4: Class Top Score -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card-results p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Highest Score</span>
                        <h3 class="fw-bold text-amber mb-0" style="color:#d97706;"><?php echo number_format($highestPct, 1); ?>%</h3>
                        <small class="text-amber fw-semibold" style="color:#d97706;"><i class="fa-solid fa-crown me-1"></i>Top Mark</small>
                    </div>
                    <div class="kpi-icon-wrapper badge-soft-amber">
                        <i class="fa-solid fa-trophy"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 5: Class Mean Score -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card-results p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Class Average</span>
                        <h3 class="fw-bold text-purple mb-0" style="color:#9333ea;"><?php echo $avgPct; ?>%</h3>
                        <small class="text-purple fw-semibold" style="color:#9333ea;"><i class="fa-solid fa-chart-line me-1"></i>Mean Score</small>
                    </div>
                    <div class="kpi-icon-wrapper badge-soft-purple">
                        <i class="fa-solid fa-chart-pie"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 6: Position 1 Holder -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card-results p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">1st Position</span>
                        <h6 class="fw-bold text-dark mb-0 text-truncate" style="max-width:110px;">
                            <?php echo $topStudent ? htmlspecialchars($topStudent['first_name'] . ' ' . $topStudent['last_name']) : 'None'; ?>
                        </h6>
                        <small class="text-warning fw-bold"><i class="fa-solid fa-crown me-1"></i>Class Topper</small>
                    </div>
                    <div class="kpi-icon-wrapper rank-1-gold" style="width:40px; height:40px; font-size:1.1rem;">
                        <i class="fa-solid fa-award"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation & Live Filter -->
    <div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:14px;">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <ul class="nav nav-pills nav-pills-custom gap-2" id="resultTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-ledger-btn" data-bs-toggle="pill" data-bs-target="#tab-ledger" type="button">
                            <i class="fa-solid fa-list-ol me-2"></i>Official Position Ledger
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-toppers-btn" data-bs-toggle="pill" data-bs-target="#tab-toppers" type="button">
                            <i class="fa-solid fa-trophy me-2 text-warning"></i>Honor Roll & Toppers
                        </button>
                    </li>
                </ul>

                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="liveSearchInput" class="form-control bg-light border-start-0" placeholder="Search result ledger..." onkeyup="filterResultsTable()">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Content Container -->
    <div class="tab-content" id="resultTabContent">

        <!-- ── TAB 1: OFFICIAL POSITION LEDGER ────────────────────────── -->
        <div class="tab-pane fade show active" id="tab-ledger" role="tabpanel">
            <div class="card border-0 shadow-sm mb-5" style="border-radius:14px;" id="resultLedgerArea">
                
                <!-- Print Header -->
                <div class="card-header bg-white border-0 pt-4 px-4 text-center d-none d-print-block border-bottom pb-3">
                    <h2 class="fw-bold text-dark mb-0">INDUS GRAMMAR SCHOOL</h2>
                    <h5 class="text-primary fw-bold mb-1">Official Examination Result & Position Ledger</h5>
                    <p class="text-muted small mb-0">
                        Exam: <strong><?php echo htmlspecialchars($activeExamTitle); ?></strong> | 
                        Class: <strong><?php echo htmlspecialchars($activeClassTitle); ?></strong> | 
                        Issued Date: <?php echo date('d-M-Y'); ?>
                    </p>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-results table-hover align-middle mb-0 table-print-clean" id="resultMainTable">
                            <thead>
                                <tr>
                                    <th style="width: 80px;" class="text-center">Rank</th>
                                    <th>Admission No.</th>
                                    <th>Student Full Name</th>
                                    <th class="text-end">Max Total</th>
                                    <th class="text-end">Marks Obtained</th>
                                    <th class="text-center">Percentage (%)</th>
                                    <th class="text-center">Grade</th>
                                    <th class="text-center">Overall Status</th>
                                    <th class="text-end d-print-none" style="width: 140px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($results)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5">
                                            <div class="py-4">
                                                <i class="fa-solid fa-calculator text-muted fa-3x mb-3 opacity-50"></i>
                                                <h5 class="fw-bold text-dark">No Result Ledger Compiled Yet</h5>
                                                <p class="text-muted small mb-3">Click the "Calculate & Rank Results" button above to evaluate marks and compile rankings.</p>
                                                <button class="btn btn-warning text-dark fw-bold btn-sm px-4" onclick="compileResults()">
                                                    <i class="fa-solid fa-arrows-rotate me-1"></i> Calculate & Rank Results
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: foreach ($results as $r): 
                                    $pos = (int)$r['position'];
                                    $isPass = (strcasecmp($r['status'], 'Pass') === 0);
                                    
                                    $rankClass = 'rank-other';
                                    if ($isPass) {
                                        if ($pos === 1) $rankClass = 'rank-1-gold';
                                        elseif ($pos === 2) $rankClass = 'rank-2-silver';
                                        elseif ($pos === 3) $rankClass = 'rank-3-bronze';
                                    }
                                ?>
                                    <tr>
                                        <td class="text-center">
                                            <?php if ($isPass): ?>
                                                <div class="rank-badge-crown <?php echo $rankClass; ?>">
                                                    <?php if ($pos <= 3): ?><i class="fa-solid fa-crown text-warning me-0.5"></i><?php endif; ?>
                                                    <?php echo $pos; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-secondary text-white px-3 py-1 rounded-pill fw-bold">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($r['admission_no']); ?></code></td>
                                        <td class="fw-bold text-dark">
                                            <i class="fa-solid fa-user-graduate text-primary me-2 opacity-75"></i>
                                            <?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?>
                                        </td>
                                        <td class="text-end fw-bold text-muted"><?php echo number_format((float)$r['total_marks'], 1); ?></td>
                                        <td class="text-end fw-bold text-dark fs-6"><?php echo number_format((float)$r['obtained_marks'], 1); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-primary border px-3 py-1.5 fw-bold fs-6">
                                                <?php echo number_format((float)$r['percentage'], 2); ?>%
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-dark text-white px-3 py-1 fw-bold fs-6"><?php echo htmlspecialchars($r['grade']); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($isPass): ?>
                                                <span class="badge bg-success bg-opacity-15 text-success px-3 py-1 rounded-pill fw-bold">
                                                    <i class="fa-solid fa-check me-1"></i>PASS
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger bg-opacity-15 text-danger px-3 py-1 rounded-pill fw-bold">
                                                    <i class="fa-solid fa-xmark me-1"></i>FAIL
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end d-print-none">
                                            <a href="report_cards.php?exam_type_id=<?php echo $selectedExam; ?>&class_id=<?php echo $selectedClass; ?>&student_id=<?php echo $r['student_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary rounded-pill px-3" title="View Student Report Card">
                                                <i class="fa-solid fa-id-card me-1"></i> Report Card
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Print Footer -->
                <div class="card-footer bg-white border-0 pt-5 pb-4 d-none d-print-block">
                    <div class="row text-center mt-4">
                        <div class="col-4">
                            <div class="border-top pt-2 mx-3">
                                <span class="fw-bold text-dark small">Class In-Charge Teacher</span>
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
        </div>

        <!-- ── TAB 2: HONOR ROLL & TOPPERS ───────────────────────────── -->
        <div class="tab-pane fade" id="tab-toppers" role="tabpanel">
            <div class="card border-0 shadow-sm" style="border-radius:14px;">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-trophy me-2 text-warning"></i>Class Honor Roll & Position Holders</h5>
                    <p class="text-muted small mb-0">Top performing academic achievers in <?php echo htmlspecialchars($activeClassTitle); ?>.</p>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4 justify-content-center">
                        <?php 
                        $passResults = array_filter($results, fn($item) => strcasecmp($item['status'], 'Pass') === 0);
                        usort($passResults, fn($a, $b) => (int)$a['position'] <=> (int)$b['position']);
                        $top3 = array_slice($passResults, 0, 3);
                        
                        if (empty($top3)):
                        ?>
                            <div class="col-12 text-center py-4 text-muted">No passing toppers compiled yet.</div>
                        <?php else: foreach ($top3 as $tp): 
                            $pNo = (int)$tp['position'];
                            $cardBorder = $pNo === 1 ? 'border-warning' : ($pNo === 2 ? 'border-secondary' : 'border-amber');
                            $crownColor = $pNo === 1 ? 'text-warning' : ($pNo === 2 ? 'text-secondary' : 'text-danger');
                        ?>
                            <div class="col-md-4">
                                <div class="card border border-2 <?php echo $cardBorder; ?> shadow-sm h-100 p-4 text-center" style="border-radius:16px;">
                                    <div class="mb-3">
                                        <i class="fa-solid fa-crown fa-3x <?php echo $crownColor; ?>"></i>
                                    </div>
                                    <span class="badge bg-dark text-white px-3 py-1 rounded-pill mx-auto mb-2 fw-bold">Position #<?php echo $pNo; ?></span>
                                    <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($tp['first_name'] . ' ' . $tp['last_name']); ?></h4>
                                    <small class="text-muted d-block mb-3">ADM: <?php echo htmlspecialchars($tp['admission_no']); ?></small>

                                    <div class="p-3 bg-light rounded-3 mb-3">
                                        <h3 class="fw-bold text-primary mb-0"><?php echo number_format((float)$tp['percentage'], 2); ?>%</h3>
                                        <small class="text-muted">Obtained <?php echo number_format((float)$tp['obtained_marks'], 1); ?> / <?php echo number_format((float)$tp['total_marks'], 1); ?></small>
                                    </div>

                                    <span class="badge bg-success bg-opacity-15 text-success px-3 py-1.5 rounded-pill fw-bold fs-6">
                                        Grade: <?php echo htmlspecialchars($tp['grade']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

<?php else: ?>
    <!-- Empty Prompt State -->
    <div class="card border-0 shadow-sm" style="border-radius:14px; height: 320px;">
        <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-5">
            <div class="kpi-icon-wrapper badge-soft-sky mb-3" style="width:64px; height:64px; font-size:2rem;">
                <i class="fa-solid fa-square-poll-vertical"></i>
            </div>
            <h5 class="text-dark fw-bold mb-1">No Result Ledger Loaded</h5>
            <p class="text-muted small mb-0" style="max-width: 450px;">
                Please choose an active <strong>Exam Term</strong> and <strong>Class Section</strong> in the filter bar above to display or compile examination result ledgers.
            </p>
        </div>
    </div>
<?php endif; ?>

<!-- Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="resultsToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="resultsToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("resultsToast");
    const m = document.getElementById("resultsToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function compileResults() {
    const btn = document.getElementById("btnCompile");
    if (btn) { btn.disabled = true; btn.innerHTML = \'<i class="fa-solid fa-spinner fa-spin me-1"></i> Processing Rankings...\'; }

    const fd = new FormData();
    fd.append("action", "generate_results");
    fd.append("csrf_token", "' . csrfToken() . '");
    fd.append("exam_type_id", "' . $selectedExam . '");
    fd.append("class_id", "' . $selectedClass . '");

    fetch("../../ajax/exams.php", { method: "POST", body: fd })
        .then(r => r.json())
        .then(data => {
            showToast(data.message, data.success);
            if(data.success) {
                setTimeout(() => location.reload(), 800);
            } else {
                if (btn) { btn.disabled = false; btn.innerHTML = \'<i class="fa-solid fa-arrows-rotate me-1"></i> Calculate & Rank Results\'; }
            }
        })
        .catch(() => {
            showToast("System error processing results.", false);
            if (btn) { btn.disabled = false; btn.innerHTML = \'<i class="fa-solid fa-arrows-rotate me-1"></i> Calculate & Rank Results\'; }
        });
}

function filterResultsTable() {
    const query = document.getElementById("liveSearchInput").value.toLowerCase();
    const rows = document.querySelectorAll("#resultMainTable tbody tr");
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = text.includes(query) ? "" : "none";
    });
}

function exportResultsCSV() {
    let csv = [];
    let rows = document.querySelectorAll("#resultMainTable tr");
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
    downloadLink.download = "result_ledger_class_' . $selectedClass . '_exam_' . $selectedExam . '.csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>';

include_once __DIR__ . '/../../includes/footer.php'; ?>
