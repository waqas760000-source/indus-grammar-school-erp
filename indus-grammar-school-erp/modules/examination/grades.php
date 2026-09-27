<?php
/**
 * Indus Grammar School ERP - Grade Setup & GPA Configurator
 * Version 4.0.0
 */

$pageTitle = 'Grading System Setup';
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

// Load grade configurations ordered by min_percentage DESC
$grades = GradeSetup::all();

// Compute Metrics
$totalGrades = count($grades);
$maxGPA      = 0.00;
$minPassPct  = 100.00;
$failMinPct  = 0.00;
$failMaxPct  = 0.00;

foreach ($grades as $g) {
    $gp = (float)$g['grade_point'];
    if ($gp > $maxGPA) $maxGPA = $gp;
    
    $minP = (float)$g['min_percentage'];
    $maxP = (float)$g['max_percentage'];
    
    if (strcasecmp($g['grade'], 'F') === 0 || strcasecmp($g['remarks'], 'Fail') === 0) {
        $failMinPct = $minP;
        $failMaxPct = $maxP;
    } else {
        if ($minP < $minPassPct) {
            $minPassPct = $minP;
        }
    }
}
if ($minPassPct > 100) $minPassPct = 50.00;
?>

<!-- Custom CSS Styling -->
<style>
.grade-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #581c87 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 2.2rem 2rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
    border-bottom: 3px solid #a855f7;
    position: relative;
    overflow: hidden;
}
.grade-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(168, 85, 247, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
}
.kpi-card-grade {
    border: none;
    border-radius: 14px;
    background: #ffffff;
    transition: all 0.25s ease-in-out;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
}
.kpi-card-grade:hover {
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
.badge-soft-purple { background-color: rgba(168, 85, 247, 0.12); color: #9333ea; }
.badge-soft-emerald { background-color: rgba(16, 185, 129, 0.12); color: #059669; }
.badge-soft-amber { background-color: rgba(245, 158, 11, 0.12); color: #d97706; }
.badge-soft-rose { background-color: rgba(244, 63, 94, 0.12); color: #e11d48; }
.badge-soft-cyan { background-color: rgba(6, 182, 212, 0.12); color: #0891b2; }

.table-grade thead th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    font-weight: 700;
    padding: 1rem 0.85rem;
    border-bottom: 2px solid #e2e8f0;
}
.table-grade tbody td {
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
.grade-badge-lg {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: 800;
}
@media print {
    body * { visibility: hidden; }
    #gradePrintArea, #gradePrintArea * { visibility: visible; }
    #gradePrintArea {
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
<div class="grade-hero mb-4 d-print-none">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-purple bg-opacity-25 text-purple px-3 py-1 rounded-pill fw-semibold small" style="color:#d8b4fe;">
                    <i class="fa-solid fa-graduation-cap me-1"></i> Academic Grading Scale
                </span>
                <span class="badge bg-success bg-opacity-25 text-success px-3 py-1 rounded-pill fw-semibold small" style="color:#6ee7b7;">
                    <i class="fa-solid fa-calculator me-1"></i> Automatic Pass/Fail Engine
                </span>
            </div>
            <h2 class="fw-bold text-white mb-2">
                <i class="fa-solid fa-sliders text-purple me-2" style="color:#c084fc;"></i>Grading System & Marks Scale Setup
            </h2>
            <p class="text-white-50 mb-0">
                Configure marks percentage bands, grade scales, and descriptors used in report cards, result compilation engines, and position registries.
            </p>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                <button class="btn btn-purple text-white fw-bold px-3 py-2 shadow-sm rounded-3" style="background-color:#9333ea; border:none;" data-bs-toggle="modal" data-bs-target="#gradeModal" onclick="resetForm()">
                    <i class="fa-solid fa-plus-circle me-1"></i> Add Grade Level
                </button>
                <button class="btn btn-light fw-semibold px-3 py-2 shadow-sm rounded-3" onclick="exportGradesCSV()">
                    <i class="fa-solid fa-file-csv me-1 text-success"></i> Export CSV
                </button>
                <button class="btn btn-outline-light fw-semibold px-3 py-2 rounded-3" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Print Grade Key
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary text-white border-secondary px-3 py-2 rounded-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Executive KPI Summary Bar -->
<div class="row g-3 mb-4 d-print-none">
    <!-- Card 1: Grade Tiers -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-grade p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Grade Tiers</span>
                    <h3 class="fw-bold text-dark mb-0"><?php echo $totalGrades; ?></h3>
                    <small class="text-purple fw-semibold" style="color:#9333ea;"><i class="fa-solid fa-layer-group me-1"></i>Scale Bands</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-purple">
                    <i class="fa-solid fa-ranking-star"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Top Grade Band -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-grade p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Top Grade Tier</span>
                    <h3 class="fw-bold text-emerald mb-0" style="color:#059669;">90% - 100%</h3>
                    <small class="text-emerald fw-semibold" style="color:#059669;"><i class="fa-solid fa-award me-1"></i>Grade A+</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-emerald">
                    <i class="fa-solid fa-medal"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Pass Threshold Cutoff -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-grade p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Pass Cutoff</span>
                    <h3 class="fw-bold text-cyan mb-0" style="color:#0891b2;"><?php echo number_format($minPassPct, 1); ?>%</h3>
                    <small class="text-cyan fw-semibold" style="color:#0891b2;"><i class="fa-solid fa-shield-check me-1"></i>Min Pass %</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-cyan">
                    <i class="fa-solid fa-check-double"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: Failing Band Cutoff -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-grade p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Fail Band</span>
                    <h3 class="fw-bold text-rose mb-0" style="color:#e11d48;">&lt; <?php echo number_format($failMaxPct + 0.01, 1); ?>%</h3>
                    <small class="text-rose fw-semibold" style="color:#e11d48;"><i class="fa-solid fa-circle-xmark me-1"></i>Grade F</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-rose">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 5: Full Range Coverage -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-grade p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Coverage</span>
                    <h3 class="fw-bold text-amber mb-0" style="color:#d97706;">0 - 100%</h3>
                    <small class="text-amber fw-semibold" style="color:#d97706;"><i class="fa-solid fa-ruler-combined me-1"></i>Continuous</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-amber">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 6: Report Sync -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-grade p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Report Sync</span>
                    <h3 class="fw-bold text-secondary mb-0">Active</h3>
                    <small class="text-muted fw-semibold">Auto Synced</small>
                </div>
                <div class="kpi-icon-wrapper bg-light text-secondary">
                    <i class="fa-solid fa-arrows-rotate"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs Navigation & Live Interactive Grade Simulator -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:14px;">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <ul class="nav nav-pills nav-pills-custom gap-2" id="gradeTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-registry-btn" data-bs-toggle="pill" data-bs-target="#tab-registry" type="button">
                        <i class="fa-solid fa-table-list me-2"></i>Grade Scale Registry
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-simulator-btn" data-bs-toggle="pill" data-bs-target="#tab-simulator" type="button">
                        <i class="fa-solid fa-calculator me-2"></i>Grade Range Simulator
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-impact-btn" data-bs-toggle="pill" data-bs-target="#tab-impact" type="button">
                        <i class="fa-solid fa-circle-info me-2"></i>Compilation Rules Audit
                    </button>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 250px;">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" id="liveSearchInput" class="form-control bg-light border-start-0" placeholder="Filter grade bands..." onkeyup="filterGradesTable()">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab Content Container -->
<div class="tab-content" id="gradeTabContent">

    <!-- ── TAB 1: GRADE SCALE REGISTRY ─────────────────────────────── -->
    <div class="tab-pane fade show active" id="tab-registry" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius:14px;" id="gradePrintArea">
            
            <!-- Print Header -->
            <div class="card-header bg-white border-0 pt-4 px-4 d-none d-print-block text-center border-bottom pb-3">
                <h2 class="fw-bold text-dark mb-0">INDUS GRAMMAR SCHOOL</h2>
                <h5 class="text-purple fw-bold mb-0" style="color:#9333ea;">Official Academic Grading Scale & Descriptor Key</h5>
                <p class="text-muted small mb-0">Issued: <?php echo date('d-M-Y'); ?> | Session 2025-2026</p>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-grade table-hover align-middle mb-0 table-print-clean" id="gradeMainTable">
                        <thead>
                            <tr>
                                <th style="width: 80px;" class="text-center">Grade</th>
                                <th class="text-center">Min Percentage</th>
                                <th class="text-center">Max Percentage</th>
                                <th class="text-center">GPA Point</th>
                                <th>Academic Descriptor & Remarks</th>
                                <th class="text-center">Pass / Fail Status</th>
                                <th class="text-end d-print-none" style="width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($grades)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="py-4">
                                            <i class="fa-solid fa-graduation-cap text-muted fa-3x mb-3 opacity-50"></i>
                                            <h5 class="fw-bold text-dark">No Grade Levels Configured</h5>
                                            <p class="text-muted small mb-3">Please configure grade bands to compile results and report cards.</p>
                                            <button class="btn btn-purple text-white btn-sm px-4 fw-bold" style="background-color:#9333ea; border:none;" data-bs-toggle="modal" data-bs-target="#gradeModal" onclick="resetForm()">
                                                <i class="fa-solid fa-plus-circle me-1"></i> Add First Grade Level
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: foreach ($grades as $g): 
                                $gradeName = htmlspecialchars($g['grade']);
                                $isFail = (strcasecmp($g['grade'], 'F') === 0 || strcasecmp($g['remarks'], 'Fail') === 0);
                                
                                // Color assignment
                                $badgeClass = 'bg-primary text-white';
                                if ($gradeName === 'A+' || $gradeName === 'A') $badgeClass = 'bg-success text-white';
                                elseif ($gradeName === 'B') $badgeClass = 'bg-info text-white';
                                elseif ($gradeName === 'C') $badgeClass = 'bg-primary text-white';
                                elseif ($gradeName === 'D') $badgeClass = 'bg-warning text-dark';
                                elseif ($isFail) $badgeClass = 'bg-danger text-white';
                            ?>
                                <tr>
                                    <td class="text-center">
                                        <div class="grade-badge-lg <?php echo $badgeClass; ?> shadow-sm">
                                            <?php echo $gradeName; ?>
                                        </div>
                                    </td>
                                    <td class="text-center fw-bold text-dark fs-6"><?php echo number_format($g['min_percentage'], 2); ?>%</td>
                                    <td class="text-center fw-bold text-dark fs-6"><?php echo number_format($g['max_percentage'], 2); ?>%</td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-3 py-1.5 fw-bold fs-6">
                                            <i class="fa-solid fa-star text-warning me-1"></i><?php echo number_format($g['grade_point'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($g['remarks'] ?: 'Standard Performance'); ?></span>
                                        <span class="text-muted text-xs">Descriptor used in Report Cards</span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($isFail): ?>
                                            <span class="badge bg-danger bg-opacity-15 text-danger px-3 py-1 rounded-pill fw-bold">
                                                <i class="fa-solid fa-xmark me-1"></i>FAIL
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success bg-opacity-15 text-success px-3 py-1 rounded-pill fw-bold">
                                                <i class="fa-solid fa-check me-1"></i>PASS
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end d-print-none">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary rounded-pill px-2.5 me-1" title="Edit Grade Level" onclick='editGrade(<?php echo json_encode($g); ?>)'>
                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                            </button>
                                            <?php if (hasPermission('academic_manage')): ?>
                                                <button class="btn btn-outline-danger btn-delete rounded-pill px-2.5" title="Delete Grade Level" data-id="<?php echo $g['id']; ?>">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
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
    </div>

    <!-- ── TAB 2: GRADE RANGE SIMULATOR ────────────────────────────── -->
    <div class="tab-pane fade" id="tab-simulator" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius:14px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-calculator me-2 text-primary"></i>Interactive Grade & Pass/Fail Simulator</h5>
                <p class="text-muted small mb-0">Test any student percentage or score to simulate instant grade lookup, GPA calculation, and pass/fail evaluation.</p>
            </div>
            <div class="card-body p-4">
                <div class="row g-4 align-items-center">
                    <div class="col-md-5">
                        <div class="p-4 bg-light rounded-3 border">
                            <label class="form-label fw-bold text-dark">Enter Test Percentage (%)</label>
                            <div class="input-group input-group-lg mb-3">
                                <input type="number" step="0.1" min="0" max="100" class="form-control fw-bold" id="simPercentageInput" value="85.5" oninput="runGradeSimulation()">
                                <span class="input-group-text fw-bold">%</span>
                            </div>

                            <label class="form-label fw-bold text-dark">Or Score Out of Total Marks</label>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <input type="number" class="form-control form-control-sm" id="simObtainedInput" placeholder="Obtained" value="425" oninput="runScoreSimulation()">
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control form-control-sm" id="simTotalInput" placeholder="Total Marks" value="500" oninput="runScoreSimulation()">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Simulator Output Screen -->
                    <div class="col-md-7">
                        <div class="p-4 rounded-3 border text-center" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
                            <span class="text-muted text-uppercase fw-bold small d-block mb-1">Simulated Grade Result</span>
                            <div class="display-3 fw-bold text-primary mb-2" id="simGradeBadge">A</div>
                            
                            <div class="d-flex justify-content-center gap-3 mb-3">
                                <span class="badge bg-dark text-white px-3 py-2 fs-6" id="simGpaBadge">Score %</span>
                                <span class="badge bg-success text-white px-3 py-2 fs-6" id="simStatusBadge">PASS</span>
                            </div>

                            <p class="fw-bold text-dark fs-5 mb-1" id="simRemarksText">"Excellent"</p>
                            <small class="text-muted" id="simRangeText">Matched Band: 80.00% – 89.99%</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TAB 3: COMPILATION RULES AUDIT ──────────────────────────── -->
    <div class="tab-pane fade" id="tab-impact" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius:14px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-circle-info me-2 text-info"></i>Automatic Result & Rank Compilation Audit</h5>
                <p class="text-muted small mb-0">System rules governing pass/fail status, overall grade calculation, and position ranking.</p>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="p-3 border rounded-3 h-100 bg-light">
                            <h6 class="fw-bold text-primary"><i class="fa-solid fa-shield-check me-2"></i>1. Individual Subject Pass Rule</h6>
                            <p class="text-muted small mb-0">
                                Every student must score at or above the subject's <strong>Passing Marks Cutoff</strong> in all compulsory subjects. Failing any single subject marks overall status as <strong>FAIL</strong> regardless of total percentage.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded-3 h-100 bg-light">
                            <h6 class="fw-bold text-success"><i class="fa-solid fa-chart-line me-2"></i>2. Overall Term Cutoff Rule</h6>
                            <p class="text-muted small mb-0">
                                The student's aggregated percentage is evaluated against the <strong>Exam Term Passing Percentage</strong>. Falling below the term cutoff sets overall status to <strong>FAIL</strong>.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded-3 h-100 bg-light">
                            <h6 class="fw-bold text-purple"><i class="fa-solid fa-ranking-star me-2" style="color:#9333ea;"></i>3. Position Registry Ranking</h6>
                            <p class="text-muted small mb-0">
                                Position ranks (#1, #2, #3...) are generated by sorting overall percentage descending. Total obtained marks are used as tiebreakers for identical percentages.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Modal Form: Add / Edit Grade Level -->
<div class="modal fade" id="gradeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-0 pt-4 px-4 bg-light rounded-top">
                <h5 class="modal-title fw-bold text-dark" id="modalTitle">
                    <i class="fa-solid fa-graduation-cap me-2 text-purple" style="color:#9333ea;"></i>Configure Grade Level
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="gradeForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_grade">
                    <input type="hidden" name="grade_id" id="gradeId" value="0">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Grade Name *</label>
                            <input type="text" class="form-control fw-bold" name="grade" id="gradeName" required placeholder="e.g. A+, A, B, F">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Grade Point (GPA) *</label>
                            <input type="number" step="0.01" class="form-control" name="grade_point" id="gradePoint" required min="0" max="5" value="4.00">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Minimum Percentage (%) *</label>
                            <input type="number" step="0.01" class="form-control" name="min_percentage" id="gradeMin" required min="0" max="100" value="80">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Maximum Percentage (%) *</label>
                            <input type="number" step="0.01" class="form-control" name="max_percentage" id="gradeMax" required min="0" max="100" value="89.99">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Academic Remarks / Descriptor</label>
                        <input type="text" class="form-control" name="remarks" id="gradeRemarks" placeholder="e.g. Outstanding, Excellent, Fail">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 bg-light rounded-bottom">
                <button type="button" class="btn btn-outline-secondary px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="gradeForm" class="btn btn-purple text-white px-4 fw-bold" style="background-color:#9333ea; border:none;" id="btnSave">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Grade Scale
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="gradeToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="gradeToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php 
$gradesJson = json_encode($grades);

$extraJS = '<script>
const gradesData = ' . $gradesJson . ';
const modalObj = new bootstrap.Modal(document.getElementById("gradeModal"));

function showToast(msg, ok) {
    const t = document.getElementById("gradeToast");
    const m = document.getElementById("gradeToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function runGradeSimulation() {
    const pct = parseFloat(document.getElementById("simPercentageInput").value) || 0;
    
    let matched = null;
    for (let g of gradesData) {
        let minP = parseFloat(g.min_percentage);
        let maxP = parseFloat(g.max_percentage);
        if (pct >= minP && pct <= maxP) {
            matched = g;
            break;
        }
    }

    const gBadge = document.getElementById("simGradeBadge");
    const gpaBadge = document.getElementById("simGpaBadge");
    const statusBadge = document.getElementById("simStatusBadge");
    const remarksText = document.getElementById("simRemarksText");
    const rangeText = document.getElementById("simRangeText");

    if (matched) {
        gBadge.textContent = matched.grade;
        gpaBadge.textContent = "Score: " + pct + "%";
        remarksText.textContent = "\"" + (matched.remarks || "Standard") + "\"";
        rangeText.textContent = "Matched Band: " + parseFloat(matched.min_percentage).toFixed(2) + "% – " + parseFloat(matched.max_percentage).toFixed(2) + "%";

        const isFail = matched.grade.toUpperCase() === "F" || (matched.remarks && matched.remarks.toLowerCase().includes("fail"));
        if (isFail) {
            statusBadge.className = "badge bg-danger text-white px-3 py-2 fs-6";
            statusBadge.textContent = "FAIL";
        } else {
            statusBadge.className = "badge bg-success text-white px-3 py-2 fs-6";
            statusBadge.textContent = "PASS";
        }
    } else {
        gBadge.textContent = "F";
        gpaBadge.textContent = "Score: 0.00%";
        remarksText.textContent = "\"Fail / Unmapped\"";
        rangeText.textContent = "Percentage outside defined ranges";
        statusBadge.className = "badge bg-danger text-white px-3 py-2 fs-6";
        statusBadge.textContent = "FAIL";
    }
}

function runScoreSimulation() {
    const obt = parseFloat(document.getElementById("simObtainedInput").value) || 0;
    const tot = parseFloat(document.getElementById("simTotalInput").value) || 100;
    const pct = tot > 0 ? ((obt / tot) * 100).toFixed(1) : 0;
    document.getElementById("simPercentageInput").value = pct;
    runGradeSimulation();
}

function resetForm() {
    document.getElementById("gradeForm").reset();
    document.getElementById("gradeId").value = "0";
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-graduation-cap me-2 text-purple" style="color:#9333ea;"></i>Configure Grade Level\';
    document.getElementById("btnSave").innerHTML = \'<i class="fa-solid fa-floppy-disk me-1"></i> Save Grade Scale\';
}

function editGrade(data) {
    resetForm();
    document.getElementById("gradeId").value = data.id;
    document.getElementById("gradeName").value = data.grade;
    document.getElementById("gradePoint").value = data.grade_point;
    document.getElementById("gradeMin").value = data.min_percentage;
    document.getElementById("gradeMax").value = data.max_percentage;
    document.getElementById("gradeRemarks").value = data.remarks;
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit Grade Level\';
    document.getElementById("btnSave").innerHTML = \'<i class="fa-solid fa-rotate me-1"></i> Update Grade Scale\';
    modalObj.show();
}

function filterGradesTable() {
    const query = document.getElementById("liveSearchInput").value.toLowerCase();
    const rows = document.querySelectorAll("#gradeMainTable tbody tr");
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = text.includes(query) ? "" : "none";
    });
}

function exportGradesCSV() {
    let csv = [];
    let rows = document.querySelectorAll("#gradeMainTable tr");
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
    downloadLink.download = "grading_scale_" + new Date().toISOString().slice(0,10) + ".csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

document.addEventListener("DOMContentLoaded", function() {
    runGradeSimulation();

    // Form Save
    const form = document.getElementById("gradeForm");
    if(form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSave");
            btn.disabled = true; btn.innerHTML = \'<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...\';

            fetch("../../ajax/exams.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        modalObj.hide();
                        setTimeout(() => location.reload(), 800);
                    } else {
                        btn.disabled = false; 
                        btn.innerHTML = document.getElementById("gradeId").value !== "0" ? \'<i class="fa-solid fa-rotate me-1"></i> Update Grade Scale\' : \'<i class="fa-solid fa-floppy-disk me-1"></i> Save Grade Scale\';
                    }
                })
                .catch(() => {
                    showToast("System connection error.", false);
                    btn.disabled = false; 
                    btn.innerHTML = \'<i class="fa-solid fa-floppy-disk me-1"></i> Save Grade Scale\';
                });
        });
    }

    // Delete Trigger
    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this grade level? This changes overall calculations!")) return;
            const id = this.dataset.id;
            this.disabled = true;

            const fd = new FormData();
            fd.append("action", "delete_grade");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);

            fetch("../../ajax/exams.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 800);
                    else this.disabled = false;
                })
                .catch(() => {
                    showToast("System error on deletion.", false);
                    this.disabled = false;
                });
        });
    });
});
</script>';

include_once __DIR__ . '/../../includes/footer.php'; ?>
