<?php
/**
 * Indus Grammar School ERP - Class Positions & Merit Rankings Registry
 * Version 4.0.0
 */

$pageTitle = 'Class Merit Positions & Rankings';
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

$rankings  = [];
$examInfo  = null;
$classInfo = null;

if ($selectedExam > 0 && $selectedClass > 0) {
    // Fetch rankings from positions table, ordered by position_no ascending
    $stmt = $db->prepare("
        SELECT p.*, st.first_name, st.last_name, st.admission_no, st.roll_no, st.cnic_bform, st.guardian_cnic
        FROM positions p
        JOIN students st ON p.student_id = st.id
        WHERE p.exam_type_id = :etid AND p.class_id = :cid
        ORDER BY p.position_no ASC, st.first_name ASC
    ");
    $stmt->execute(['etid' => $selectedExam, 'cid' => $selectedClass]);
    $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
$totalRanked = count($rankings);
$passRanked  = 0;
$top1        = null;
$top2        = null;
$top3        = null;
$pctSum      = 0;

foreach ($rankings as $r) {
    $st = $r['status'] ?? 'Fail';
    $pNo = (int)$r['position_no'];
    $pct = (float)($r['percentage'] ?? 0.00);
    $pctSum += $pct;

    if (strcasecmp($st, 'Pass') === 0) {
        $passRanked++;
        if ($pNo === 1) $top1 = $r;
        elseif ($pNo === 2) $top2 = $r;
        elseif ($pNo === 3) $top3 = $r;
    }
}

$meanScorePct = $totalRanked > 0 ? round($pctSum / $totalRanked, 1) : 0;

$activeExamTitle  = $examInfo ? $examInfo['exam_name'] : 'Selected Exam Term';
$activeClassTitle = $classInfo ? ($classInfo['class_name'] . ' - ' . $classInfo['section']) : 'Selected Class';
?>

<!-- Custom CSS Styling -->
<style>
.positions-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #78350f 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 2.2rem 2rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
    border-bottom: 3px solid #f59e0b;
    position: relative;
    overflow: hidden;
}
.positions-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(245, 158, 11, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
}
.kpi-card-pos {
    border: none;
    border-radius: 14px;
    background: #ffffff;
    transition: all 0.25s ease-in-out;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
}
.kpi-card-pos:hover {
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
.badge-soft-amber { background-color: rgba(245, 158, 11, 0.12); color: #d97706; }
.badge-soft-emerald { background-color: rgba(16, 185, 129, 0.12); color: #059669; }
.badge-soft-blue { background-color: rgba(37, 99, 235, 0.12); color: #2563eb; }
.badge-soft-purple { background-color: rgba(168, 85, 247, 0.12); color: #9333ea; }

.table-pos thead th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    font-weight: 700;
    padding: 1rem 0.85rem;
    border-bottom: 2px solid #e2e8f0;
}
.table-pos tbody td {
    padding: 1rem 0.85rem;
    vertical-align: middle;
}
.rank-badge-crown {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.95rem;
}
.rank-1-gold { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #ffffff; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3); }
.rank-2-silver { background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%); color: #ffffff; }
.rank-3-bronze { background: linear-gradient(135deg, #d97706 0%, #b45309 100%); color: #ffffff; }
.rank-other { background: #e2e8f0; color: #475569; }

@media print {
    body * { visibility: hidden; }
    #positionsPrintArea, #positionsPrintArea * { visibility: visible; }
    #positionsPrintArea {
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
<div class="positions-hero mb-4 d-print-none">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-warning bg-opacity-25 text-warning px-3 py-1 rounded-pill fw-semibold small">
                    <i class="fa-solid fa-crown me-1"></i> Class Merit Standings
                </span>
                <span class="badge bg-info bg-opacity-25 text-info px-3 py-1 rounded-pill fw-semibold small">
                    <i class="fa-solid fa-trophy me-1"></i> Honor Roll Achievers
                </span>
            </div>
            <h2 class="fw-bold text-white mb-2">
                <i class="fa-solid fa-list-ol text-warning me-2" style="color:#f59e0b;"></i>Class Positions & Merit Rankings
            </h2>
            <p class="text-white-50 mb-0">
                Display official class rank listings, merit percentages, letter grades, and top academic achievers.
            </p>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                <?php if ($selectedExam > 0 && $selectedClass > 0): ?>
                    <button class="btn btn-light fw-semibold px-3 py-2 shadow-sm rounded-3" onclick="exportPositionsCSV()">
                        <i class="fa-solid fa-file-csv me-1 text-success"></i> Export CSV
                    </button>
                    <button class="btn btn-outline-light fw-semibold px-3 py-2 rounded-3" onclick="window.print()">
                        <i class="fa-solid fa-print me-1"></i> Print Merit List
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
                    <option value="">-- Choose Exam Term --</option>
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
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Display Standings
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedExam > 0 && $selectedClass > 0): ?>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4 d-print-none">
        <!-- KPI 1: Ranked Students -->
        <div class="col-xl-3 col-md-6">
            <div class="card kpi-card-pos p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Ranked Students</span>
                        <h3 class="fw-bold text-dark mb-0"><?php echo $totalRanked; ?></h3>
                        <small class="text-emerald fw-semibold" style="color:#059669;"><i class="fa-solid fa-user-check me-1"></i><?php echo $passRanked; ?> Passing Candidates</small>
                    </div>
                    <div class="kpi-icon-wrapper badge-soft-emerald">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 2: 1st Position Topper -->
        <div class="col-xl-3 col-md-6">
            <div class="card kpi-card-pos p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">1st Position (Gold)</span>
                        <h5 class="fw-bold text-dark mb-0 text-truncate" style="max-width:140px;">
                            <?php echo $top1 ? htmlspecialchars($top1['first_name'] . ' ' . $top1['last_name']) : 'N/A'; ?>
                        </h5>
                        <small class="text-warning fw-bold"><i class="fa-solid fa-crown me-1"></i><?php echo $top1 ? number_format((float)$top1['percentage'], 1) . '%' : '—'; ?></small>
                    </div>
                    <div class="kpi-icon-wrapper rank-1-gold" style="width:44px; height:44px;">
                        <i class="fa-solid fa-crown"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 3: 2nd Position Silver -->
        <div class="col-xl-3 col-md-6">
            <div class="card kpi-card-pos p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">2nd Position (Silver)</span>
                        <h5 class="fw-bold text-dark mb-0 text-truncate" style="max-width:140px;">
                            <?php echo $top2 ? htmlspecialchars($top2['first_name'] . ' ' . $top2['last_name']) : 'N/A'; ?>
                        </h5>
                        <small class="text-secondary fw-bold"><i class="fa-solid fa-medal me-1"></i><?php echo $top2 ? number_format((float)$top2['percentage'], 1) . '%' : '—'; ?></small>
                    </div>
                    <div class="kpi-icon-wrapper rank-2-silver" style="width:44px; height:44px;">
                        <i class="fa-solid fa-medal"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 4: Class Mean Score % -->
        <div class="col-xl-3 col-md-6">
            <div class="card kpi-card-pos p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Class Mean Score</span>
                        <h3 class="fw-bold text-purple mb-0" style="color:#9333ea;"><?php echo $meanScorePct; ?>%</h3>
                        <small class="text-muted fw-semibold">Class Avg %</small>
                    </div>
                    <div class="kpi-icon-wrapper badge-soft-purple">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Positions Table Card -->
    <div class="card border-0 shadow-sm mb-5" style="border-radius:14px;" id="positionsPrintArea">
        
        <!-- Print Header -->
        <div class="card-header bg-white border-0 pt-4 px-4 d-none d-print-block text-center border-bottom pb-3">
            <h2 class="fw-bold text-dark mb-0">INDUS GRAMMAR SCHOOL</h2>
            <h5 class="text-warning fw-bold mb-1" style="color:#d97706;">Official Class Merit Positions & Rankings</h5>
            <p class="text-muted small mb-0">
                Exam: <strong><?php echo htmlspecialchars($activeExamTitle); ?></strong> | 
                Class: <strong><?php echo htmlspecialchars($activeClassTitle); ?></strong> | 
                Date: <?php echo date('d-M-Y'); ?>
            </p>
        </div>

        <!-- Filter Search Bar -->
        <div class="card-header bg-white border-0 pt-4 px-4 pb-3 d-flex flex-wrap align-items-center justify-content-between gap-3 d-print-none">
            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-list-ol me-2 text-warning"></i>Merit Standing Registry</h5>
            <div class="input-group input-group-sm" style="width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                <input type="text" id="liveSearchInput" class="form-control bg-light border-start-0" placeholder="Filter positions..." onkeyup="filterPositionsTable()">
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-pos table-hover align-middle mb-0 table-print-clean" id="positionsMainTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 70px;">Rank</th>
                            <th>Unique ID</th>
                            <th>Roll No</th>
                            <th>Student Full Name</th>
                            <th>B-Form / CNIC</th>
                            <th class="text-center">Percentage Score (%)</th>
                            <th class="text-center">Grade Scale</th>
                            <th class="text-center">Overall Status</th>
                            <th class="text-end d-print-none" style="width: 140px;">Report Card</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rankings)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="py-4">
                                        <i class="fa-solid fa-trophy text-muted fa-3x mb-3 opacity-50"></i>
                                        <h5 class="fw-bold text-dark">No Positions Compiled Yet</h5>
                                        <p class="text-muted small mb-3">Please go to "Class Results" and click "Calculate & Rank Results" for this class.</p>
                                        <a href="results.php?exam_type_id=<?php echo $selectedExam; ?>&class_id=<?php echo $selectedClass; ?>" class="btn btn-warning text-dark fw-bold btn-sm px-4">
                                            <i class="fa-solid fa-square-poll-vertical me-1"></i> Open Class Results
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: foreach ($rankings as $r): 
                            $pos = (int)$r['position_no'];
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
                                <td><span class="badge bg-primary-subtle text-primary fw-bold font-monospace"><?php echo htmlspecialchars($r['roll_no'] ?: 'N/A'); ?></span></td>
                                <td class="fw-bold text-dark">
                                    <i class="fa-solid fa-user-graduate text-primary me-2 opacity-75"></i>
                                    <?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?>
                                </td>
                                <td><small class="font-monospace text-secondary fw-semibold"><?php echo htmlspecialchars($r['cnic_bform'] ?: 'N/A'); ?></small></td>
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
                                       class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="fa-solid fa-id-card me-1"></i> Report Card
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Print Footer Signatures -->
        <div class="card-footer bg-white border-0 pt-5 pb-4 d-none d-print-block">
            <div class="row text-center mt-4">
                <div class="col-6">
                    <div class="border-top pt-2 mx-5">
                        <span class="fw-bold text-dark small">Class Teacher Signature</span>
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

<?php else: ?>
    <!-- Empty Prompt State -->
    <div class="card border-0 shadow-sm" style="border-radius:14px; height: 320px;">
        <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-5">
            <div class="kpi-icon-wrapper badge-soft-amber mb-3" style="width:64px; height:64px; font-size:2rem;">
                <i class="fa-solid fa-list-ol"></i>
            </div>
            <h5 class="text-dark fw-bold mb-1">No Class Merit Rankings Loaded</h5>
            <p class="text-muted small mb-0" style="max-width: 450px;">
                Please select an active <strong>Exam Term</strong> and <strong>Class Section</strong> in the filter bar above to display student merit position standings.
            </p>
        </div>
    </div>
<?php endif; ?>

<?php $extraJS = '<script>
function filterPositionsTable() {
    const query = document.getElementById("liveSearchInput").value.toLowerCase();
    const rows = document.querySelectorAll("#positionsMainTable tbody tr");
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = text.includes(query) ? "" : "none";
    });
}

function exportPositionsCSV() {
    let csv = [];
    let rows = document.querySelectorAll("#positionsMainTable tr");
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
    downloadLink.download = "class_positions_' . $selectedClass . '_exam_' . $selectedExam . '.csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>';

include_once __DIR__ . '/../../includes/footer.php'; ?>
