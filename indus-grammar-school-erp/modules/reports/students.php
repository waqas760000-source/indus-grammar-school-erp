<?php
/**
 * Indus Grammar School ERP - Student Reports & Demographics Suite
 * Version 4.0.0 (Executive Demographics & Admissions Intelligence)
 */

$pageTitle = 'Student Reports Panel';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Selectors
$classes = SchoolClass::all();

// Filter values
$selectedReport  = sanitize($_GET['report_type'] ?? 'student_list');
$selectedClass   = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedType    = sanitize($_GET['academic_type'] ?? '');
$selectedStatus  = sanitize($_GET['status'] ?? '');
$dateFrom        = sanitize($_GET['date_from'] ?? '');
$dateTo          = sanitize($_GET['date_to'] ?? '');
$searchKeyword   = sanitize($_GET['q'] ?? '');

// Base SQL query
$sql = "
    SELECT st.*, c.class_name, c.section,
           TIMESTAMPDIFF(YEAR, st.date_of_birth, CURDATE()) as age
    FROM students st
    LEFT JOIN classes c ON st.class_id = c.id
    WHERE 1=1
";
$params = [];

if ($selectedClass > 0) {
    $sql .= " AND st.class_id = :cid";
    $params['cid'] = $selectedClass;
}
if ($selectedType !== '') {
    $sql .= " AND st.academic_type = :atype";
    $params['atype'] = $selectedType;
}
if ($selectedStatus !== '') {
    $sql .= " AND st.status = :status";
    $params['status'] = $selectedStatus;
}
if ($dateFrom !== '') {
    $sql .= " AND st.enrollment_date >= :from";
    $params['from'] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= " AND st.enrollment_date <= :to";
    $params['to'] = $dateTo;
}
if ($searchKeyword !== '') {
    $sql .= " AND (st.first_name LIKE :q OR st.last_name LIKE :q OR st.admission_no LIKE :q OR st.guardian_name LIKE :q OR st.guardian_phone LIKE :q)";
    $params['q'] = '%' . $searchKeyword . '%';
}

// Sub-report title & ordering logic
$reportTitle = "Student Roster Report";
switch ($selectedReport) {
    case 'admission_register':
        $reportTitle = "Student Admissions Register Log";
        $sql .= " ORDER BY st.enrollment_date DESC, st.admission_no DESC";
        break;
    case 'class_wise':
        $reportTitle = "Class-Wise Student Distribution Roster";
        $sql .= " ORDER BY c.class_name ASC, c.section ASC, st.first_name ASC";
        break;
    case 'school_students':
        $reportTitle = "Regular School Program Roster";
        $sql .= " AND (st.academic_type = 'School' OR st.academic_type IS NULL OR st.academic_type = '') ORDER BY st.first_name ASC";
        break;
    case 'academy_students':
        $reportTitle = "Academy Coaching Program Roster";
        $sql .= " AND st.academic_type = 'Academy' ORDER BY st.first_name ASC";
        break;
    case 'gender_report':
        $reportTitle = "Gender Demographics Distribution";
        $sql .= " ORDER BY st.gender ASC, st.first_name ASC";
        break;
    case 'age_report':
        $reportTitle = "Age Demographics & Profile Breakdown";
        $sql .= " ORDER BY age DESC, st.first_name ASC";
        break;
    case 'new_admissions':
        $reportTitle = "Recent New Admissions (Last 90 Days)";
        $sql .= " AND st.enrollment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) ORDER BY st.enrollment_date DESC";
        break;
    case 'inactive_students':
        $reportTitle = "Inactive & Archived Students Directory";
        $sql .= " AND st.status = 'Inactive' ORDER BY st.first_name ASC";
        break;
    case 'suspended_students':
        $reportTitle = "Suspended Students Registry";
        $sql .= " AND st.status = 'Suspended' ORDER BY st.first_name ASC";
        break;
    default:
        $reportTitle = "Comprehensive Student Directory Roster";
        $sql .= " ORDER BY st.first_name ASC";
        break;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Summary Metrics Calculation ──────────────────────────────────
$totalCount = count($reportData);
$maleCount = 0;
$femaleCount = 0;
$otherGenderCount = 0;
$totalAgeSum = 0;
$ageValidCount = 0;
$activeCount = 0;
$inactiveCount = 0;
$suspendedCount = 0;

$ageBrackets = [
    'Under 5 yrs' => 0,
    '5 - 8 yrs'  => 0,
    '9 - 12 yrs' => 0,
    '13 - 16 yrs' => 0,
    '16+ yrs'    => 0
];

$classBreakdownSummary = [];

foreach ($reportData as $row) {
    // Gender
    $g = strtolower(trim($row['gender'] ?? ''));
    if ($g === 'male' || $g === 'm') $maleCount++;
    elseif ($g === 'female' || $g === 'f') $femaleCount++;
    else $otherGenderCount++;

    // Status
    if ($row['status'] === 'Active') $activeCount++;
    elseif ($row['status'] === 'Inactive') $inactiveCount++;
    elseif ($row['status'] === 'Suspended') $suspendedCount++;

    // Age
    $age = (int)($row['age'] ?? 0);
    if ($age > 0) {
        $totalAgeSum += $age;
        $ageValidCount++;

        if ($age < 5) $ageBrackets['Under 5 yrs']++;
        elseif ($age <= 8) $ageBrackets['5 - 8 yrs']++;
        elseif ($age <= 12) $ageBrackets['9 - 12 yrs']++;
        elseif ($age <= 16) $ageBrackets['13 - 16 yrs']++;
        else $ageBrackets['16+ yrs']++;
    }

    // Class Summary
    $cName = $row['class_name'] ? ($row['class_name'] . ' - ' . $row['section']) : 'Unassigned';
    if (!isset($classBreakdownSummary[$cName])) {
        $classBreakdownSummary[$cName] = 0;
    }
    $classBreakdownSummary[$cName]++;
}

$avgAge = $ageValidCount > 0 ? round($totalAgeSum / $ageValidCount, 1) : 0;
$malePct = $totalCount > 0 ? round(($maleCount / $totalCount) * 100, 1) : 0;
$femalePct = $totalCount > 0 ? round(($femaleCount / $totalCount) * 100, 1) : 0;
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

:root {
    --st-font: 'Outfit', sans-serif;
    --st-primary: #3b82f6;
    --st-indigo: #4f46e5;
    --st-dark: #0f172a;
    --st-card-bg: #ffffff;
    --st-border: #e2e8f0;
    --st-radius: 16px;
    --st-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.04), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
}

body {
    font-family: var(--st-font);
    background-color: #f8fafc;
}

.st-hero-card {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #3b82f6 100%);
    border-radius: var(--st-radius);
    padding: 2.25rem 2rem;
    color: #ffffff;
    box-shadow: 0 20px 25px -5px rgba(37, 99, 235, 0.3);
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
}

.st-hero-card::before {
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

.st-kpi-card {
    background: var(--st-card-bg);
    border: 1px solid var(--st-border);
    border-radius: var(--st-radius);
    padding: 1.35rem 1.25rem;
    box-shadow: var(--st-shadow);
    height: 100%;
    transition: transform 0.2s ease;
}

.st-kpi-card:hover {
    transform: translateY(-3px);
}

.st-kpi-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.st-kpi-val {
    font-weight: 700;
    font-size: 1.55rem;
    color: var(--st-dark);
    line-height: 1.2;
    margin-top: 0.35rem;
}

.progress-thin {
    height: 6px;
    border-radius: 4px;
    background-color: #e2e8f0;
}

.custom-table-card {
    background: var(--st-card-bg);
    border: 1px solid var(--st-border);
    border-radius: var(--st-radius);
    box-shadow: var(--st-shadow);
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
    border-bottom: 1px solid var(--st-border);
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
    .st-hero-card { background: #1e3a8a !important; color: #fff !important; }
    #reportPrintArea { position: static !important; }
}
</style>

<div class="container-fluid px-0">

    <!-- Executive Hero Header -->
    <div class="st-hero-card">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small fw-semibold">
                        <i class="fa-solid fa-chart-pie me-1 text-warning"></i> Demographics Intelligence
                    </span>
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small">
                        <?php echo number_format($totalCount); ?> Records Compiled
                    </span>
                </div>
                <h1 class="fw-bold mb-2" style="font-size:1.95rem; letter-spacing:-0.02em;"><?php echo sanitize($reportTitle); ?></h1>
                <p class="text-white-50 mb-0 leading-relaxed" style="max-width: 650px;">
                    Construct demographics summaries, admissions registers, age profile analyses, gender distributions, and class breakdowns in real time.
                </p>
            </div>
            <div class="col-lg-5 text-lg-end mt-4 mt-lg-0 no-print">
                <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end">
                    <button class="btn btn-light fw-bold text-dark px-4 py-2 rounded-3 shadow-sm" onclick="exportToExcel()">
                        <i class="fa-solid fa-file-excel text-success me-2"></i>Export Excel / CSV
                    </button>
                    <button class="btn btn-outline-light px-3 py-2 rounded-3" onclick="window.print()">
                        <i class="fa-solid fa-print me-2"></i>Print Roster
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
                    <label class="form-label small fw-bold text-dark">Report Focus / Sub-type</label>
                    <select class="form-select" name="report_type" onchange="this.form.submit()">
                        <option value="student_list" <?php echo $selectedReport === 'student_list' ? 'selected' : ''; ?>>All Student Roster</option>
                        <option value="admission_register" <?php echo $selectedReport === 'admission_register' ? 'selected' : ''; ?>>Admissions Register Log</option>
                        <option value="class_wise" <?php echo $selectedReport === 'class_wise' ? 'selected' : ''; ?>>Class-Wise Breakdown</option>
                        <option value="gender_report" <?php echo $selectedReport === 'gender_report' ? 'selected' : ''; ?>>Gender Demographics</option>
                        <option value="age_report" <?php echo $selectedReport === 'age_report' ? 'selected' : ''; ?>>Age Profile Analysis</option>
                        <option value="new_admissions" <?php echo $selectedReport === 'new_admissions' ? 'selected' : ''; ?>>New Admissions (Recent)</option>
                        <option value="school_students" <?php echo $selectedReport === 'school_students' ? 'selected' : ''; ?>>School Program Only</option>
                        <option value="academy_students" <?php echo $selectedReport === 'academy_students' ? 'selected' : ''; ?>>Academy Program Only</option>
                        <option value="inactive_students" <?php echo $selectedReport === 'inactive_students' ? 'selected' : ''; ?>>Inactive Directory</option>
                        <option value="suspended_students" <?php echo $selectedReport === 'suspended_students' ? 'selected' : ''; ?>>Suspended Registry</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-bold text-dark">Class & Section</label>
                    <select class="form-select" name="class_id">
                        <option value="0">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-bold text-dark">Academic Program</label>
                    <select class="form-select" name="academic_type">
                        <option value="">All Programs</option>
                        <option value="School" <?php echo $selectedType === 'School' ? 'selected' : ''; ?>>School</option>
                        <option value="Academy" <?php echo $selectedType === 'Academy' ? 'selected' : ''; ?>>Academy</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-bold text-dark">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="Active" <?php echo $selectedStatus === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo $selectedStatus === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="Suspended" <?php echo $selectedStatus === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-12">
                    <label class="form-label small fw-bold text-dark">Search Keyword</label>
                    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="Student name, admission #, guardian phone...">
                </div>

                <div class="col-lg-6 col-md-6">
                    <label class="form-label small fw-bold text-dark">Enrollment Date Range</label>
                    <div class="input-group">
                        <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                        <span class="input-group-text bg-white">to</span>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                </div>

                <div class="col-lg-6 col-md-6 text-lg-end">
                    <a href="students.php" class="btn btn-outline-secondary px-4 me-2"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
                    <button type="submit" class="btn btn-primary px-5 fw-bold"><i class="fa-solid fa-magnifying-glass me-2"></i>Filter Roster</button>
                </div>

            </form>
        </div>
    </div>

    <!-- Executive KPI Analytics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="st-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Filtered Students</span>
                    <div class="st-kpi-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                </div>
                <div class="st-kpi-val"><?php echo number_format($totalCount); ?></div>
                <div class="mt-2 text-muted small">
                    <span class="badge bg-success bg-opacity-10 text-success fw-bold"><?php echo $activeCount; ?> Active</span>
                    <?php if ($inactiveCount > 0): ?>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold ms-1"><?php echo $inactiveCount; ?> Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="st-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Gender Demographics</span>
                    <div class="st-kpi-icon bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-venus-mars"></i>
                    </div>
                </div>
                <div class="st-kpi-val text-info"><?php echo $maleCount; ?> M / <?php echo $femaleCount; ?> F</div>
                <div class="mt-2 text-muted small">
                    <div class="progress progress-thin mb-1">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $malePct; ?>%"></div>
                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $femalePct; ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between text-xs text-muted">
                        <span>Male: <?php echo $malePct; ?>%</span>
                        <span>Female: <?php echo $femalePct; ?>%</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="st-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Average Age</span>
                    <div class="st-kpi-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-cake-candles"></i>
                    </div>
                </div>
                <div class="st-kpi-val text-warning-dark"><?php echo $avgAge; ?> <span class="fs-6 fw-normal text-muted">Years</span></div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-chart-bar me-1"></i> Based on DOB profile entries
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="st-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Age Distribution Top</span>
                    <div class="st-kpi-icon bg-purple bg-opacity-10 text-purple" style="color:#8b5cf6;">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
                <?php 
                    arsort($ageBrackets);
                    $topBracket = array_key_first($ageBrackets);
                    $topBracketVal = reset($ageBrackets);
                ?>
                <div class="st-kpi-val text-truncate" style="font-size:1.35rem; color:#8b5cf6;"><?php echo $topBracket; ?></div>
                <div class="mt-2 text-muted small">
                    <span class="fw-bold text-dark"><?php echo $topBracketVal; ?> Students</span> in this bracket
                </div>
            </div>
        </div>
    </div>

    <!-- Demographics & Age Profile Summary Panel -->
    <div class="row g-4 mb-4">
        <!-- Age Profile Breakdown -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="fa-solid fa-bars-staggered text-primary me-2"></i>Age Bracket Profile Breakdown
                    </h6>
                    <?php foreach ($ageBrackets as $bName => $bCount): 
                        $bPct = $totalCount > 0 ? ($bCount / $totalCount) * 100 : 0;
                    ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold text-dark small"><?php echo $bName; ?></span>
                                <span class="small fw-bold text-dark"><?php echo $bCount; ?> Students (<?php echo number_format($bPct, 1); ?>%)</span>
                            </div>
                            <div class="progress progress-thin">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo min(100, max(4, $bPct)); ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Class Wise Breakdown Summary -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="fa-solid fa-building-columns text-success me-2"></i>Top Class Density Roster
                    </h6>
                    <?php 
                        arsort($classBreakdownSummary);
                        $topClasses = array_slice($classBreakdownSummary, 0, 5, true);
                    ?>
                    <?php if (empty($topClasses)): ?>
                        <p class="text-muted small">No class distribution available.</p>
                    <?php else: foreach ($topClasses as $cLabel => $cHeadCount): 
                        $cPct = $totalCount > 0 ? ($cHeadCount / $totalCount) * 100 : 0;
                    ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold text-dark small"><?php echo sanitize($cLabel); ?></span>
                                <span class="small fw-bold text-success"><?php echo $cHeadCount; ?> Students (<?php echo number_format($cPct, 1); ?>%)</span>
                            </div>
                            <div class="progress progress-thin">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, max(4, $cPct)); ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Roster Data Table Card -->
    <div class="custom-table-card shadow-sm mb-4" id="reportPrintArea">
        
        <!-- Print Header -->
        <div class="p-4 text-center d-none d-print-block border-bottom">
            <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h2>
            <h4 class="text-secondary fw-semibold mb-1"><?php echo sanitize($reportTitle); ?></h4>
            <div class="text-muted small">
                Printed Date: <?php echo date('d-M-Y H:i'); ?> | Total Students: <?php echo number_format($totalCount); ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                <thead>
                    <tr>
                        <th width="110">Admission #</th>
                        <th>Student Full Name</th>
                        <th>Class & Section</th>
                        <th>Program</th>
                        <th class="text-center">Gender</th>
                        <th class="text-center">Age</th>
                        <th>Enrollment Date</th>
                        <th>Guardian Contact</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No student records found matching the specified filters.</td></tr>
                    <?php else: foreach ($reportData as $row): ?>
                        <tr>
                            <td><code class="fw-bold text-primary">#<?php echo sanitize($row['admission_no']); ?></code></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                <small class="text-muted">DOB: <?php echo $row['date_of_birth'] ? date('d-M-Y', strtotime($row['date_of_birth'])) : '—'; ?></small>
                            </td>
                            <td>
                                <?php if ($row['class_name']): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill fw-semibold">
                                        <?php echo sanitize($row['class_name'] . ' - ' . $row['section']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo sanitize($row['academic_type'] ?: 'School'); ?></span></td>
                            <td class="text-center small fw-semibold"><?php echo sanitize($row['gender']); ?></td>
                            <td class="text-center fw-bold text-dark"><?php echo (int)$row['age']; ?> yrs</td>
                            <td class="small"><?php echo $row['enrollment_date'] ? date('d-M-Y', strtotime($row['enrollment_date'])) : '—'; ?></td>
                            <td>
                                <div class="fw-semibold small text-dark"><?php echo sanitize($row['guardian_name'] ?: '—'); ?></div>
                                <div class="text-muted text-xs"><?php echo sanitize($row['guardian_phone'] ?: ''); ?></div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?php 
                                    echo $row['status'] === 'Active' ? 'success' : ($row['status'] === 'Suspended' ? 'warning' : 'secondary'); 
                                ?> bg-opacity-10 text-<?php 
                                    echo $row['status'] === 'Active' ? 'success' : ($row['status'] === 'Suspended' ? 'warning-dark' : 'secondary'); 
                                ?> px-3 py-1 rounded-pill fw-semibold">
                                    <?php echo sanitize($row['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
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