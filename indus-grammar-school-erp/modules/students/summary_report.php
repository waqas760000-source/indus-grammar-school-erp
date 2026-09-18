<?php
/**
 * Indus Grammar School ERP - Student Summary Report
 * Version 5.0.0 - Premium UI/UX Redesign
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('student_view');

$userRole = $_SESSION['role_code'] ?? '';
$db = Database::getConnection();

// Load filter dropdown options from database
$classesList = [];
try {
    $classesList = $db->query("SELECT * FROM classes ORDER BY class_name ASC, section ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$campusesList = [];
try {
    $campusesList = $db->query("SELECT DISTINCT campus FROM student_registration_details WHERE campus IS NOT NULL AND campus != '' ORDER BY campus ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$sessionsList = [];
try {
    $sessionsList = $db->query("SELECT DISTINCT academic_session FROM student_registration_details WHERE academic_session IS NOT NULL AND academic_session != '' ORDER BY academic_session DESC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Retrieve filter parameters safely
$search_session = sanitize($_GET['search_session'] ?? '');
$search_campus = sanitize($_GET['search_campus'] ?? '');
$search_academic_type = sanitize($_GET['search_academic_type'] ?? '');
$search_class = (int)($_GET['search_class'] ?? 0);
$search_section = sanitize($_GET['search_section'] ?? '');
$search_status = sanitize($_GET['search_status'] ?? '');

// Build dynamic WHERE clause with unique parameter bindings
$where = " WHERE 1=1";
$params = [];

if ($search_session !== '') {
    $where .= " AND d.academic_session = :session";
    $params['session'] = $search_session;
}
if ($search_campus !== '') {
    $where .= " AND d.campus = :campus";
    $params['campus'] = $search_campus;
}
if ($search_academic_type !== '') {
    $where .= " AND s.academic_type = :academic_type";
    $params['academic_type'] = $search_academic_type;
}
if ($search_class > 0) {
    $where .= " AND s.class_id = :class_id";
    $params['class_id'] = $search_class;
}
if ($search_section !== '') {
    $where .= " AND (c.section = :section1 OR s.school_section = :section2)";
    $params['section1'] = $search_section;
    $params['section2'] = $search_section;
}
if ($search_status !== '') {
    $where .= " AND s.status = :status";
    $params['status'] = $search_status;
}

// Calculate Summary Metrics
$totalStudents = 0;
$totalActive = 0;
$totalInactive = 0;
$totalBoys = 0;
$totalGirls = 0;
$totalSchool = 0;
$totalAcademy = 0;

$classBreakdown = [];
$genderChartData = ['Male' => 0, 'Female' => 0, 'Other' => 0];
$classChartLabels = [];
$classChartCounts = [];
$admissionTrendData = [];

try {
    // Single aggregated metrics query (Fast & MySQL ONLY_FULL_GROUP_BY Compliant)
    $sqlMetrics = "
        SELECT 
            COUNT(DISTINCT s.id) as total_students,
            SUM(CASE WHEN s.status = 'Active' THEN 1 ELSE 0 END) as total_active,
            SUM(CASE WHEN s.status = 'Inactive' OR s.status = 'Suspended' THEN 1 ELSE 0 END) as total_inactive,
            SUM(CASE WHEN s.gender = 'Male' THEN 1 ELSE 0 END) as total_boys,
            SUM(CASE WHEN s.gender = 'Female' THEN 1 ELSE 0 END) as total_girls,
            SUM(CASE WHEN s.gender NOT IN ('Male', 'Female') OR s.gender IS NULL THEN 1 ELSE 0 END) as total_other,
            SUM(CASE WHEN s.academic_type = 'School' THEN 1 ELSE 0 END) as total_school,
            SUM(CASE WHEN s.academic_type = 'Academy' THEN 1 ELSE 0 END) as total_academy
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
    ";
    $stmtMetrics = $db->prepare($sqlMetrics);
    $stmtMetrics->execute($params);
    $metrics = $stmtMetrics->fetch(PDO::FETCH_ASSOC);

    if ($metrics) {
        $totalStudents = (int)($metrics['total_students'] ?? 0);
        $totalActive = (int)($metrics['total_active'] ?? 0);
        $totalInactive = (int)($metrics['total_inactive'] ?? 0);
        $totalBoys = (int)($metrics['total_boys'] ?? 0);
        $totalGirls = (int)($metrics['total_girls'] ?? 0);
        $totalSchool = (int)($metrics['total_school'] ?? 0);
        $totalAcademy = (int)($metrics['total_academy'] ?? 0);

        $genderChartData['Male'] = $totalBoys;
        $genderChartData['Female'] = $totalGirls;
        $genderChartData['Other'] = (int)($metrics['total_other'] ?? 0);
    }

    // Class & Section Breakdown query (MySQL ONLY_FULL_GROUP_BY Compliant)
    $sqlBreakdown = "
        SELECT 
            COALESCE(c.class_name, s.school_class, 'Unassigned') as class_name,
            COALESCE(c.section, s.school_section, 'A') as section_name,
            COALESCE(d.campus, 'Main Campus') as campus_name,
            COALESCE(s.academic_type, 'School') as academic_type,
            COUNT(DISTINCT s.id) as total_students,
            SUM(CASE WHEN s.gender = 'Male' THEN 1 ELSE 0 END) as boys_count,
            SUM(CASE WHEN s.gender = 'Female' THEN 1 ELSE 0 END) as girls_count,
            SUM(CASE WHEN s.status = 'Active' THEN 1 ELSE 0 END) as active_count
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        GROUP BY 
            COALESCE(c.class_name, s.school_class, 'Unassigned'),
            COALESCE(c.section, s.school_section, 'A'),
            COALESCE(d.campus, 'Main Campus'),
            COALESCE(s.academic_type, 'School')
        ORDER BY class_name ASC, section_name ASC
    ";
    $stmtBreakdown = $db->prepare($sqlBreakdown);
    $stmtBreakdown->execute($params);
    $classBreakdown = $stmtBreakdown->fetchAll(PDO::FETCH_ASSOC);

    foreach ($classBreakdown as $cb) {
        $classChartLabels[] = $cb['class_name'] . ' - ' . $cb['section_name'];
        $classChartCounts[] = (int)$cb['total_students'];
    }

    // Admission trend stats (grouped by month)
    $sqlTrend = "
        SELECT DATE_FORMAT(s.enrollment_date, '%b %Y') as month_year, COUNT(DISTINCT s.id) as count
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where AND s.enrollment_date IS NOT NULL
        GROUP BY DATE_FORMAT(s.enrollment_date, '%Y-%m'), DATE_FORMAT(s.enrollment_date, '%b %Y')
        ORDER BY MIN(s.enrollment_date) ASC
        LIMIT 12
    ";
    $stmtTrend = $db->prepare($sqlTrend);
    $stmtTrend->execute($params);
    $admissionTrendData = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Summary Report error: " . $e->getMessage());
}

$pageTitle = 'Student Summary Report';
$breadcrumbActive = 'Student Summary Report';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Premium Design System Tokens -->
<style>
:root {
    --igs-navy: #0F172A;
    --igs-royal: #1D4ED8;
    --igs-blue: #2563EB;
    --igs-light-bg: #F8FAFC;
    --igs-border: #E2E8F0;
    --igs-text: #0F172A;
    --igs-muted: #64748B;
    --igs-success: #16A34A;
    --igs-warning: #D97706;
    --igs-danger: #DC2626;
}

body {
    background-color: var(--igs-light-bg);
    color: var(--igs-text);
}

.page-header-banner {
    background: linear-gradient(135deg, #0F172A 0%, #1D4ED8 60%, #2563EB 100%);
    color: #ffffff;
    border-radius: 12px;
    padding: 24px 28px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.12);
    margin-bottom: 24px;
}

/* Metric Cards */
.metric-card {
    background: #ffffff;
    border: 1px solid var(--igs-border);
    border-radius: 12px;
    padding: 18px 20px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    height: 100%;
    display: flex;
    align-items: center;
    gap: 16px;
}

.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
}

.metric-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}

.metric-details .lbl {
    font-size: 0.75rem;
    color: var(--igs-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 2px;
}

.metric-details .val {
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1.2;
    color: var(--igs-text);
}

/* Panel Containers */
.panel-card {
    background: #ffffff;
    border: 1px solid var(--igs-border);
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.info-bar-pill {
    background-color: var(--igs-light-bg);
    border: 1px solid var(--igs-border);
    color: var(--igs-muted);
    font-size: 0.78rem;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* Dedicated Print Stylesheet */
@media print {
    body > *:not(#printReportSection),
    .d-print-none,
    .page-header-banner,
    .panel-card,
    header, footer, nav, sidebar, .sidebar, .main-header, .breadcrumb {
        display: none !important;
    }
    
    #printReportSection, #printReportSection * {
        display: block !important;
    }
    
    #printReportSection {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        background: #ffffff !important;
        padding: 20px !important;
        margin: 0 !important;
    }
    
    .print-table {
        width: 100% !important;
        border-collapse: collapse !important;
    }
    
    .print-table th, .print-table td {
        border: 1px solid #cbd5e1 !important;
        padding: 8px 10px !important;
        font-size: 11px !important;
    }

    .print-table th {
        background-color: #f1f5f9 !important;
        color: #0f172a !important;
    }
}
</style>

<!-- Modern Page Header Banner -->
<div class="page-header-banner d-print-none">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <span class="badge bg-white text-primary fw-bold text-uppercase px-2 py-1 mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                <i class="fa-solid fa-school me-1"></i>INDUS GRAMMAR SCHOOL
            </span>
            <h2 class="fw-bold mb-1 text-white"><i class="fa-solid fa-chart-pie me-2"></i>Student Summary Report</h2>
            <p class="mb-0 text-white-50" style="font-size: 0.95rem;">View student enrollment information, academic distribution, and summary statistics.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-light text-primary fw-bold px-3 rounded-pill" onclick="window.print()">
                <i class="fa-solid fa-print me-2"></i>Print Report
            </button>
            <button type="button" class="btn btn-outline-light fw-bold px-3 rounded-pill" onclick="exportSummaryCSV()">
                <i class="fa-solid fa-file-csv me-2"></i>Export CSV
            </button>
        </div>
    </div>
</div>

<!-- Summary Metrics Cards Grid -->
<div class="row g-3 mb-4 d-print-none">
    <div class="col-xl-3 col-md-6">
        <div class="metric-card">
            <div class="metric-icon-wrap bg-primary bg-opacity-10 text-primary">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="metric-details">
                <div class="lbl">Total Strength</div>
                <div class="val text-primary"><?php echo number_format($totalStudents); ?></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="metric-card">
            <div class="metric-icon-wrap bg-success bg-opacity-10 text-success">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div class="metric-details">
                <div class="lbl">Active Enrolled</div>
                <div class="val text-success"><?php echo number_format($totalActive); ?></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="metric-card">
            <div class="metric-icon-wrap bg-info bg-opacity-10 text-info">
                <i class="fa-solid fa-mars"></i>
            </div>
            <div class="metric-details">
                <div class="lbl">Boys Strength</div>
                <div class="val text-info"><?php echo number_format($totalBoys); ?></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="metric-card">
            <div class="metric-icon-wrap bg-danger bg-opacity-10 text-danger">
                <i class="fa-solid fa-venus"></i>
            </div>
            <div class="metric-details">
                <div class="lbl">Girls Strength</div>
                <div class="val text-danger"><?php echo number_format($totalGirls); ?></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="metric-card">
            <div class="metric-icon-wrap bg-primary bg-opacity-10 text-primary">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div class="metric-details">
                <div class="lbl">School Students</div>
                <div class="val"><?php echo number_format($totalSchool); ?></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="metric-card">
            <div class="metric-icon-wrap bg-warning bg-opacity-10 text-warning">
                <i class="fa-solid fa-book-open-reader"></i>
            </div>
            <div class="metric-details">
                <div class="lbl">Academy Students</div>
                <div class="val text-warning"><?php echo number_format($totalAcademy); ?></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="metric-card">
            <div class="metric-icon-wrap bg-secondary bg-opacity-10 text-secondary">
                <i class="fa-solid fa-user-slash"></i>
            </div>
            <div class="metric-details">
                <div class="lbl">Inactive / Alumni</div>
                <div class="val text-secondary"><?php echo number_format($totalInactive); ?></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="metric-card">
            <div class="metric-icon-wrap bg-dark bg-opacity-10 text-dark">
                <i class="fa-solid fa-building-columns"></i>
            </div>
            <div class="metric-details">
                <div class="lbl">Active Campus</div>
                <div class="val text-dark"><?php echo htmlspecialchars($search_campus ?: 'All Campuses'); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Report Filters Panel -->
<div class="panel-card mb-4 d-print-none">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-filter me-2 text-primary"></i>Report Filters</h5>
        <span class="badge bg-light text-muted border">Dynamic Search</span>
    </div>

    <form method="GET" action="summary_report.php" id="filterForm">
        <div class="row g-3">
            <div class="col-lg-2 col-md-4 col-6">
                <label class="form-label small fw-bold text-secondary">Academic Session</label>
                <select class="form-select" name="search_session">
                    <option value="">All Sessions</option>
                    <?php foreach ($sessionsList as $s): ?>
                        <option value="<?php echo htmlspecialchars($s); ?>" <?php echo ($search_session === $s) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-4 col-6">
                <label class="form-label small fw-bold text-secondary">Campus</label>
                <select class="form-select" name="search_campus">
                    <option value="">All Campuses</option>
                    <?php foreach ($campusesList as $cmp): ?>
                        <option value="<?php echo htmlspecialchars($cmp); ?>" <?php echo ($search_campus === $cmp) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cmp); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-4 col-6">
                <label class="form-label small fw-bold text-secondary">Academic Type</label>
                <select class="form-select" name="search_academic_type">
                    <option value="">All Types</option>
                    <option value="School" <?php echo ($search_academic_type === 'School') ? 'selected' : ''; ?>>School</option>
                    <option value="Academy" <?php echo ($search_academic_type === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-4 col-6">
                <label class="form-label small fw-bold text-secondary">Class</label>
                <select class="form-select" name="search_class">
                    <option value="0">All Classes</option>
                    <?php foreach ($classesList as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($search_class == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-4 col-6">
                <label class="form-label small fw-bold text-secondary">Student Status</label>
                <select class="form-select" name="search_status">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo ($search_status === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo ($search_status === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Suspended" <?php echo ($search_status === 'Suspended') ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-4 col-12 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary px-3 flex-grow-1"><i class="fa-solid fa-magnifying-glass me-1"></i>Apply Filters</button>
                <a href="summary_report.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
            </div>
        </div>
    </form>
</div>

<!-- Charts Grid Panel -->
<div class="row g-4 mb-4 d-print-none">
    <div class="col-lg-4 col-md-6">
        <div class="panel-card h-100 text-center">
            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-chart-pie text-primary me-2"></i>Gender Distribution</h6>
            <div style="max-height: 240px; display: flex; justify-content: center;">
                <canvas id="genderChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-6">
        <div class="panel-card h-100 text-center">
            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-chart-column text-primary me-2"></i>Class Strength Breakdown</h6>
            <div style="max-height: 240px; display: flex; justify-content: center;">
                <canvas id="classChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-12">
        <div class="panel-card h-100 text-center">
            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-chart-line text-primary me-2"></i>Admission Trend (Monthly)</h6>
            <div style="max-height: 240px; display: flex; justify-content: center;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Class Breakdown Table Panel -->
<div class="panel-card mb-4 d-print-none">
    <!-- Information Bar -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 border-bottom pb-3 mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-table-list text-primary me-2"></i>Class Wise Enrollment Breakdown</h5>
            <div class="d-flex flex-wrap gap-2 align-items-center mt-1">
                <span class="info-bar-pill"><i class="fa-solid fa-building-columns text-primary"></i>Campus: <?php echo htmlspecialchars($search_campus ?: 'All'); ?></span>
                <span class="info-bar-pill"><i class="fa-solid fa-graduation-cap text-primary"></i>Type: <?php echo htmlspecialchars($search_academic_type ?: 'All'); ?></span>
                <span class="info-bar-pill"><i class="fa-solid fa-calendar text-primary"></i>Session: <?php echo htmlspecialchars($search_session ?: 'All'); ?></span>
            </div>
        </div>
        <div>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill fw-bold">
                Showing <?php echo count($classBreakdown); ?> breakdown rows (<?php echo number_format($totalStudents); ?> total students)
            </span>
        </div>
    </div>

    <!-- Table Container -->
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0" id="summaryBreakdownTable">
            <thead class="table-light">
                <tr>
                    <th width="60">#</th>
                    <th>Class Name</th>
                    <th>Section</th>
                    <th>Campus</th>
                    <th>Academic Type</th>
                    <th class="text-center text-primary">Boys (M)</th>
                    <th class="text-center text-danger">Girls (F)</th>
                    <th class="text-center text-success">Active Enrolled</th>
                    <th class="text-center fw-bold text-dark">Total Strength</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($classBreakdown)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <div class="py-3">
                                <i class="fa-solid fa-folder-open fa-3x text-muted opacity-50 mb-3"></i>
                                <h6 class="fw-bold text-dark">No student records match the selected filters.</h6>
                                <p class="small text-muted mb-0">Try resetting your filter parameters to view the complete summary report.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: 
                    $sr = 1;
                    $sumBoys = 0;
                    $sumGirls = 0;
                    $sumActive = 0;
                    $sumTotal = 0;
                    foreach ($classBreakdown as $row): 
                        $sumBoys += (int)$row['boys_count'];
                        $sumGirls += (int)$row['girls_count'];
                        $sumActive += (int)$row['active_count'];
                        $sumTotal += (int)$row['total_students'];
                ?>
                    <tr>
                        <td class="text-muted fw-bold"><?php echo $sr++; ?></td>
                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['class_name']); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['section_name']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['campus_name']); ?></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['academic_type']); ?></span></td>
                        <td class="text-center text-primary fw-bold"><?php echo number_format((int)$row['boys_count']); ?></td>
                        <td class="text-center text-danger fw-bold"><?php echo number_format((int)$row['girls_count']); ?></td>
                        <td class="text-center text-success fw-bold"><?php echo number_format((int)$row['active_count']); ?></td>
                        <td class="text-center fw-bold text-dark bg-light"><?php echo number_format((int)$row['total_students']); ?></td>
                    </tr>
                <?php endforeach; ?>
                    <!-- Summary Totals Row -->
                    <tr class="table-secondary fw-bold">
                        <td colspan="5" class="text-uppercase text-dark font-monospace">Grand Total:</td>
                        <td class="text-center text-primary font-monospace"><?php echo number_format($sumBoys); ?></td>
                        <td class="text-center text-danger font-monospace"><?php echo number_format($sumGirls); ?></td>
                        <td class="text-center text-success font-monospace"><?php echo number_format($sumActive); ?></td>
                        <td class="text-center text-dark bg-warning bg-opacity-25 font-monospace fs-6"><?php echo number_format($sumTotal); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Isolated Print Section for Browser Print Preview -->
<div id="printReportSection">
    <div style="text-align: center; border-bottom: 2px solid #0F172A; padding-bottom: 12px; margin-bottom: 20px;">
        <h2 style="margin: 0; font-weight: 800; color: #0F172A;">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
        <h4 style="margin: 4px 0 0 0; color: #1D4ED8;">Student Summary Report</h4>
        <p style="margin: 4px 0 0 0; font-size: 11px; color: #64748B;">
            Campus: <?php echo htmlspecialchars($search_campus ?: 'All'); ?> | 
            Type: <?php echo htmlspecialchars($search_academic_type ?: 'All'); ?> | 
            Session: <?php echo htmlspecialchars($search_session ?: 'All'); ?> | 
            Generated On: <?php echo date('d-M-Y H:i'); ?>
        </p>
    </div>

    <!-- Print Summary Metrics Bar -->
    <div style="display: flex; justify-content: space-around; background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px; margin-bottom: 20px; text-align: center; font-size: 12px;">
        <div><strong>Total Strength:</strong> <?php echo number_format($totalStudents); ?></div>
        <div><strong>Active Enrolled:</strong> <?php echo number_format($totalActive); ?></div>
        <div><strong>Boys:</strong> <?php echo number_format($totalBoys); ?></div>
        <div><strong>Girls:</strong> <?php echo number_format($totalGirls); ?></div>
        <div><strong>School:</strong> <?php echo number_format($totalSchool); ?></div>
        <div><strong>Academy:</strong> <?php echo number_format($totalAcademy); ?></div>
    </div>

    <table class="print-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Class Name</th>
                <th>Section</th>
                <th>Campus</th>
                <th>Academic Type</th>
                <th style="text-align: center;">Boys (M)</th>
                <th style="text-align: center;">Girls (F)</th>
                <th style="text-align: center;">Active</th>
                <th style="text-align: center;">Total Strength</th>
            </tr>
        </thead>
        <tbody>
            <?php 
                $psr = 1;
                $pSumBoys = 0; $pSumGirls = 0; $pSumActive = 0; $pSumTotal = 0;
                foreach ($classBreakdown as $pr): 
                    $pSumBoys += (int)$pr['boys_count'];
                    $pSumGirls += (int)$pr['girls_count'];
                    $pSumActive += (int)$pr['active_count'];
                    $pSumTotal += (int)$pr['total_students'];
            ?>
                <tr>
                    <td><?php echo $psr++; ?></td>
                    <td><strong><?php echo htmlspecialchars($pr['class_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($pr['section_name']); ?></td>
                    <td><?php echo htmlspecialchars($pr['campus_name']); ?></td>
                    <td><?php echo htmlspecialchars($pr['academic_type']); ?></td>
                    <td style="text-align: center;"><?php echo number_format((int)$pr['boys_count']); ?></td>
                    <td style="text-align: center;"><?php echo number_format((int)$pr['girls_count']); ?></td>
                    <td style="text-align: center;"><?php echo number_format((int)$pr['active_count']); ?></td>
                    <td style="text-align: center; font-weight: bold;"><?php echo number_format((int)$pr['total_students']); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr style="font-weight: bold; background-color: #e2e8f0;">
                <td colspan="5" style="text-transform: uppercase;">Grand Total:</td>
                <td style="text-align: center;"><?php echo number_format($pSumBoys); ?></td>
                <td style="text-align: center;"><?php echo number_format($pSumGirls); ?></td>
                <td style="text-align: center;"><?php echo number_format($pSumActive); ?></td>
                <td style="text-align: center; font-size: 13px;"><?php echo number_format($pSumTotal); ?></td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Chart.js Libraries -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// 1. Gender Ratio Chart (Pie)
const ctxGender = document.getElementById('genderChart').getContext('2d');
new Chart(ctxGender, {
    type: 'pie',
    data: {
        labels: ['Boys (M)', 'Girls (F)', 'Other'],
        datasets: [{
            data: [<?php echo $genderChartData['Male']; ?>, <?php echo $genderChartData['Female']; ?>, <?php echo $genderChartData['Other']; ?>],
            backgroundColor: ['#2563EB', '#DC2626', '#94A3B8'],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// 2. Class Strength Chart (Doughnut)
const ctxClass = document.getElementById('classChart').getContext('2d');
new Chart(ctxClass, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($classChartLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($classChartCounts); ?>,
            backgroundColor: ['#1D4ED8', '#2563EB', '#3B82F6', '#60A5FA', '#16A34A', '#D97706', '#DC2626', '#8B5CF6'],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// 3. Admission Trend Line Chart
const trendLabels = [];
const trendCounts = [];
<?php foreach ($admissionTrendData as $tr): ?>
    trendLabels.push("<?php echo $tr['month_year']; ?>");
    trendCounts.push(<?php echo $tr['count']; ?>);
<?php endforeach; ?>

const ctxTrend = document.getElementById('trendChart').getContext('2d');
new Chart(ctxTrend, {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Admissions Logged',
            data: trendCounts,
            borderColor: '#1D4ED8',
            backgroundColor: 'rgba(29, 78, 216, 0.1)',
            fill: true,
            tension: 0.35,
            pointBackgroundColor: '#1D4ED8',
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        },
        plugins: {
            legend: { display: false }
        }
    }
});

// CSV Export Helper
function exportSummaryCSV() {
    let csv = "SR #,Class Name,Section Name,Campus Name,Academic Type,Boys (M),Girls (F),Active Enrolled,Total Strength\n";
    const rows = document.querySelectorAll("#summaryBreakdownTable tbody tr");
    rows.forEach(tr => {
        const cols = tr.querySelectorAll("td");
        if (cols.length === 9) {
            let rowData = [];
            cols.forEach(td => {
                rowData.push('"' + td.textContent.trim().replace(/"/g, '""') + '"');
            });
            csv += rowData.join(",") + "\n";
        }
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "Student_Summary_Report_<?php echo date('Ymd_His'); ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
