<?php
/**
 * Indus Grammar School ERP - Daily Attendance Report Submodule
 * Version 5.0.0 - Commercial ERP Redesign & Complete Analytics Center
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('attendance_view');

$db = Database::getConnection();

// 2. Retrieve & Sanitize Filters
$filter_date          = sanitize($_GET['date'] ?? date('Y-m-d'));
$filter_session       = sanitize($_GET['academic_session'] ?? '');
$filter_campus        = sanitize($_GET['campus'] ?? '');
$filter_academic_type = sanitize($_GET['academic_type'] ?? '');
$filter_class         = sanitize($_GET['class'] ?? '');
$filter_section       = sanitize($_GET['section'] ?? '');
$filter_status        = sanitize($_GET['status'] ?? '');
$filter_search        = sanitize($_GET['search'] ?? '');

// 3. Dynamic Lists for Filter Dropdowns
$campusesList = ['Main Campus', 'City Campus', 'Boys Campus'];

$academicSessions = [];
try {
    $academicSessions = $db->query("SELECT session_name FROM academic_sessions ORDER BY id DESC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $academicSessions = ['2026-2027', '2025-2026'];
}

$classesList = [];
try {
    $classesList = $db->query("SELECT class_name FROM classes GROUP BY class_name ORDER BY MIN(id) ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error fetching classes in daily.php: " . $e->getMessage());
}
if (empty($classesList)) {
    $classesList = ['Playgroup', 'Nursery', 'Prep', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10'];
}

$sectionsList = ['A', 'B', 'C', 'D', 'E'];
try {
    $dbSecs = $db->query("SELECT DISTINCT section FROM classes WHERE section IS NOT NULL AND section != '' ORDER BY section ASC")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dbSecs as $ds) {
        if (!in_array($ds, $sectionsList)) {
            $sectionsList[] = $ds;
        }
    }
} catch (Exception $e) {}

// 4. Pagination Setup
$limit = 25;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 5. Build Prepared Query Clauses & Parameters
$where = " WHERE 1=1";
$params = [];

if ($filter_date !== '') {
    $where .= " AND a.date = :date";
    $params['date'] = $filter_date;
}

if ($filter_session !== '') {
    $where .= " AND d.academic_session = :session";
    $params['session'] = $filter_session;
}

if ($filter_campus !== '') {
    $where .= " AND (d.campus = :campus OR (:campus_check = 'Main Campus' AND (d.campus IS NULL OR d.campus = '')))";
    $params['campus'] = $filter_campus;
    $params['campus_check'] = $filter_campus;
}

if ($filter_academic_type !== '') {
    $where .= " AND s.academic_type = :academic_type";
    $params['academic_type'] = $filter_academic_type;
}

if ($filter_class !== '') {
    $where .= " AND (c.class_name = :class1 OR s.school_class = :class2)";
    $params['class1'] = $filter_class;
    $params['class2'] = $filter_class;
}

if ($filter_section !== '') {
    $where .= " AND (c.section = :section1 OR s.school_section = :section2)";
    $params['section1'] = $filter_section;
    $params['section2'] = $filter_section;
}

if ($filter_status !== '') {
    $where .= " AND a.status = :status";
    $params['status'] = $filter_status;
}

if ($filter_search !== '') {
    $where .= " AND (s.first_name LIKE :search1 OR s.last_name LIKE :search2 OR CONCAT(s.first_name, ' ', s.last_name) LIKE :search3 OR s.admission_no LIKE :search4 OR d.roll_no LIKE :search5)";
    $params['search1'] = '%' . $filter_search . '%';
    $params['search2'] = '%' . $filter_search . '%';
    $params['search3'] = '%' . $filter_search . '%';
    $params['search4'] = '%' . $filter_search . '%';
    $params['search5'] = '%' . $filter_search . '%';
}

// Data Containers
$records = [];
$totalEntries = 0;
$presentCount = 0;
$absentCount = 0;
$lateCount = 0;
$leaveCount = 0;

$presentPct = 0.0;
$absentPct = 0.0;
$latePct = 0.0;
$leavePct = 0.0;
$attendancePercentage = 0.0;
$errorMessage = '';

try {
    // A. Count Total Entries
    $stmtCount = $db->prepare("
        SELECT COUNT(*) 
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
    ");
    $stmtCount->execute($params);
    $totalEntries = (int)$stmtCount->fetchColumn();

    // B. Calculate KPI Summaries Grouped by Status
    $stmtSum = $db->prepare("
        SELECT a.status, COUNT(*) as cnt 
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        GROUP BY a.status
    ");
    $stmtSum->execute($params);
    $sums = $stmtSum->fetchAll(PDO::FETCH_KEY_PAIR);

    $presentCount = (int)($sums['Present'] ?? 0);
    $absentCount  = (int)($sums['Absent'] ?? 0);
    $lateCount    = (int)($sums['Late'] ?? 0);
    $leaveCount   = (int)($sums['Leave'] ?? 0);

    $totalSum = $presentCount + $absentCount + $lateCount + $leaveCount;

    if ($totalSum > 0) {
        $attendancePercentage = round((($presentCount + $lateCount) / $totalSum) * 100, 1);
        $presentPct = round(($presentCount / $totalSum) * 100, 1);
        $absentPct  = round(($absentCount / $totalSum) * 100, 1);
        $latePct    = round(($lateCount / $totalSum) * 100, 1);
        $leavePct   = round(($leaveCount / $totalSum) * 100, 1);
    }

    // C. Fetch Attendance Data Records
    $stmtData = $db->prepare("
        SELECT a.*, s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section, s.guardian_name, s.guardian_phone,
               c.class_name, c.section, d.roll_no, COALESCE(d.campus, 'Main Campus') as campus_name, d.father_name, d.doc_student_photo
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY s.admission_no ASC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmtData->bindValue($k, $v);
    }
    $stmtData->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();

    $records = $stmtData->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Daily attendance summary query error: " . $e->getMessage());
    $errorMessage = "Unable to load attendance records. Please try again.";
}

$totalPages = ceil($totalEntries / $limit);
if ($totalPages < 1) $totalPages = 1;

// 6. Include Layout Header
$pageTitle = 'Daily Attendance Report';
$breadcrumbActive = 'Daily Attendance Report';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Scoped Advanced ERP Workspace Styles -->
<style>
/* Page Canvas Background */
.adv-page-wrapper {
    background-color: #f5f7fb;
    border-radius: 18px;
    padding: 1.5rem;
    min-height: calc(100vh - 110px);
}

/* Hero Gradient Header Banner - Exact Match to Student Registration Header */
.adv-hero-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 55%, #2563eb 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.18);
    position: relative;
    overflow: hidden;
}

.adv-hero-banner::after {
    content: '';
    position: absolute;
    right: -40px;
    bottom: -40px;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
    pointer-events: none;
}

.hero-icon-box {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.25);
}

/* Breadcrumb Styling */
.adv-breadcrumb {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 0.6rem;
}

.adv-breadcrumb a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
}

/* General Card Styling */
.adv-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 4px 14px -2px rgba(15, 23, 42, 0.04);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.adv-card-header {
    background-color: #ffffff;
    border-bottom: 1px solid #f1f5f9;
    padding: 1.15rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.adv-card-title {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
}

.adv-card-title i {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background-color: #eff6ff;
    color: #2563eb;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    font-size: 0.98rem;
}

/* Summary Metric Mini Cards */
.summary-mini-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem 1.15rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.03);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    height: 100%;
}

.summary-mini-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(15, 23, 42, 0.08);
}

.summary-mini-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

/* Inputs & Labels */
.adv-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 0.45rem;
}

.adv-required {
    color: #ef4444;
    font-weight: 700;
    margin-left: 2px;
}

.adv-control, .adv-select {
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    padding: 0.6rem 0.9rem;
    font-size: 0.9rem;
    color: #0f172a;
    background-color: #ffffff;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.adv-control:focus, .adv-select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

/* Context Meta Bar */
.context-meta-bar {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-left: 4px solid #2563eb;
    border-radius: 12px;
    padding: 0.9rem 1.25rem;
}

/* Table Styling */
.table-attendance th {
    background-color: #0f172a;
    color: #ffffff;
    font-weight: 700;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem 1.1rem;
    border-bottom: none;
}

.table-attendance td {
    padding: 0.9rem 1.1rem;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0;
}

.table-attendance tbody tr:hover {
    background-color: #f8fafc;
}

.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    background: #e2e8f0;
    color: #334155;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
}

/* Status Pill Badges */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 600;
}

.status-present { background-color: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.status-absent  { background-color: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.status-late    { background-color: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
.status-leave   { background-color: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe; }

/* Progress Bar Stack */
.progress-bar-stack {
    height: 12px;
    border-radius: 8px;
    overflow: hidden;
    background-color: #e2e8f0;
    display: flex;
}

/* Print CSS Rules */
@media print {
    body {
        background-color: #ffffff !important;
        color: #000000 !important;
    }
    .d-print-none, #sidebar, .navbar, .adv-breadcrumb, .adv-hero-banner, .filter-card, .btn, .pagination, footer {
        display: none !important;
    }
    .adv-page-wrapper {
        padding: 0 !important;
        background: transparent !important;
    }
    .adv-card {
        border: none !important;
        box-shadow: none !important;
    }
    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 20px;
        border-bottom: 2px solid #0f172a;
        padding-bottom: 12px;
    }
    .table-attendance th {
        background-color: #f1f5f9 !important;
        color: #0f172a !important;
        border: 1px solid #cbd5e1 !important;
    }
    .table-attendance td {
        border: 1px solid #e2e8f0 !important;
        color: #000000 !important;
    }
    .status-pill {
        border: 1px solid #64748b !important;
        color: #000000 !important;
        background: transparent !important;
    }
    tr {
        page-break-inside: avoid;
    }
}
</style>

<div class="adv-page-wrapper">
    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="adv-breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/index.php"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item"><a href="student.php">Student Attendance</a></li>
            <li class="breadcrumb-item active" aria-current="page">Daily Attendance Report</li>
        </ol>
    </nav>

    <!-- Hero Header Banner - Exact Match to Student Registration Header -->
    <div class="adv-hero-banner mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold mb-0 text-white fs-3">Daily Attendance Report</h3>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">Daily Analytics</span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">Review, analyze, and print daily student attendance records for Indus Grammar School.</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <a href="student.php" class="btn btn-sm btn-light text-primary rounded-2 px-3 py-2 fw-semibold shadow-sm"><i class="fa-solid fa-clipboard-check me-1"></i>Mark Attendance</a>
                <button type="button" class="btn btn-sm btn-outline-light rounded-2 px-3 py-2 fw-semibold" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print Report</button>
                <button type="button" class="btn btn-sm btn-outline-light rounded-2 px-3 py-2 fw-semibold" onclick="exportCSV()"><i class="fa-solid fa-file-excel me-1"></i>Export CSV</button>
            </div>
        </div>
    </div>

    <!-- Error Alert Banner -->
    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-center rounded-3 p-3" style="background-color: #fef2f2; border-left: 4px solid #dc2626 !important;">
            <i class="fa-solid fa-circle-xmark fs-5 text-danger me-3"></i>
            <div>
                <strong class="text-danger d-block">Query Failure</strong>
                <span class="text-secondary small"><?php echo htmlspecialchars($errorMessage); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- 1. Daily Attendance Filter Panel -->
    <div class="adv-card d-print-none filter-card">
        <div class="adv-card-header">
            <h5 class="adv-card-title">
                <i class="fa-solid fa-sliders"></i>Daily Attendance Filters
            </h5>
            <?php if ($filter_date !== date('Y-m-d') || $filter_session !== '' || $filter_campus !== '' || $filter_academic_type !== '' || $filter_class !== '' || $filter_section !== '' || $filter_status !== '' || $filter_search !== ''): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1.5 small fw-semibold">
                    <i class="fa-solid fa-filter me-1"></i>Active Filters Applied
                </span>
            <?php else: ?>
                <span class="badge bg-light text-secondary border px-2.5 py-1 small fw-semibold">Filter Controls</span>
            <?php endif; ?>
        </div>
        <div class="adv-card-body">
            <p class="text-muted small mb-3">Select the attendance date and student details to generate the daily report.</p>

            <form method="GET" action="daily.php" id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-2 col-sm-6">
                    <label class="adv-label">Attendance Date <span class="adv-required">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-primary"><i class="fa-regular fa-calendar"></i></span>
                        <input type="date" class="form-control adv-control font-monospace fw-bold" name="date" value="<?php echo htmlspecialchars($filter_date); ?>" required>
                    </div>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="adv-label">Academic Session</label>
                    <select class="form-select adv-select" name="academic_session">
                        <option value="">All Sessions</option>
                        <?php foreach ($academicSessions as $sess): ?>
                            <option value="<?php echo htmlspecialchars($sess); ?>" <?php echo ($filter_session === $sess) ? 'selected' : ''; ?>><?php echo htmlspecialchars($sess); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="adv-label">Campus</label>
                    <select class="form-select adv-select" name="campus">
                        <option value="">All Campuses</option>
                        <?php foreach ($campusesList as $cmp): ?>
                            <option value="<?php echo htmlspecialchars($cmp); ?>" <?php echo ($filter_campus === $cmp) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cmp); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="adv-label">Academic Type</label>
                    <select class="form-select adv-select" name="academic_type">
                        <option value="">All Types</option>
                        <option value="School" <?php echo ($filter_academic_type === 'School') ? 'selected' : ''; ?>>School System</option>
                        <option value="Academy" <?php echo ($filter_academic_type === 'Academy') ? 'selected' : ''; ?>>Academy Program</option>
                    </select>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="adv-label">Class</label>
                    <select class="form-select adv-select" name="class">
                        <option value="">All Classes</option>
                        <?php foreach ($classesList as $cls): ?>
                            <option value="<?php echo htmlspecialchars($cls); ?>" <?php echo ($filter_class === $cls) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cls); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="adv-label">Section</label>
                    <select class="form-select adv-select" name="section">
                        <option value="">All Sections</option>
                        <?php foreach ($sectionsList as $sec): ?>
                            <option value="<?php echo htmlspecialchars($sec); ?>" <?php echo ($filter_section === $sec) ? 'selected' : ''; ?>>Section <?php echo htmlspecialchars($sec); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 col-sm-6">
                    <label class="adv-label">Status</label>
                    <select class="form-select adv-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="Present" <?php echo ($filter_status === 'Present') ? 'selected' : ''; ?>>Present</option>
                        <option value="Absent" <?php echo ($filter_status === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                        <option value="Late" <?php echo ($filter_status === 'Late') ? 'selected' : ''; ?>>Late</option>
                        <option value="Leave" <?php echo ($filter_status === 'Leave') ? 'selected' : ''; ?>>Leave</option>
                    </select>
                </div>

                <div class="col-md-5 col-sm-6">
                    <label class="adv-label">Search Student</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" class="form-control adv-control" name="search" placeholder="Search by student name, roll, admission no..." value="<?php echo htmlspecialchars($filter_search); ?>">
                    </div>
                </div>

                <div class="col-md-4 col-sm-12 text-end">
                    <a href="daily.php" class="btn btn-outline-secondary rounded-2 px-3 py-2 fw-semibold small me-2"><i class="fa-solid fa-rotate-left me-1"></i>Reset Filters</a>
                    <button type="submit" id="searchBtn" class="btn btn-primary rounded-2 px-4 py-2 fw-semibold shadow-sm" style="background-color: #1d4ed8; border-color: #1d4ed8;">
                        <i class="fa-solid fa-filter me-2"></i>Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. Report Context Meta Bar -->
    <div class="context-meta-bar mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3 d-print-none">
        <div class="d-flex align-items-center flex-wrap gap-3 small text-dark">
            <span><i class="fa-regular fa-calendar-check text-primary me-1"></i> Report Date: <strong><?php echo date('l, d-M-Y', strtotime($filter_date)); ?></strong></span>
            <?php if ($filter_session): ?><span class="text-muted">|</span><span><i class="fa-solid fa-calendar-days text-primary me-1"></i> Session: <strong><?php echo htmlspecialchars($filter_session); ?></strong></span><?php endif; ?>
            <?php if ($filter_campus): ?><span class="text-muted">|</span><span><i class="fa-solid fa-building text-primary me-1"></i> Campus: <strong><?php echo htmlspecialchars($filter_campus); ?></strong></span><?php endif; ?>
            <?php if ($filter_academic_type): ?><span class="text-muted">|</span><span><i class="fa-solid fa-school text-primary me-1"></i> Type: <strong><?php echo htmlspecialchars($filter_academic_type); ?></strong></span><?php endif; ?>
            <?php if ($filter_class): ?><span class="text-muted">|</span><span><i class="fa-solid fa-graduation-cap text-primary me-1"></i> Class: <strong><?php echo htmlspecialchars($filter_class . ($filter_section ? ' - ' . $filter_section : '')); ?></strong></span><?php endif; ?>
            <?php if ($filter_status): ?><span class="text-muted">|</span><span><i class="fa-solid fa-tag text-primary me-1"></i> Status: <strong><?php echo htmlspecialchars($filter_status); ?></strong></span><?php endif; ?>
        </div>
        <div>
            <span class="badge bg-white text-dark border px-3 py-1.5 rounded-pill fs-7 fw-semibold shadow-sm">
                <i class="fa-solid fa-list text-primary me-1"></i> Total Filtered: <?php echo number_format($totalEntries); ?> Records
            </span>
        </div>
    </div>

    <!-- 3. KPI Summary Cards Section -->
    <div class="row g-3 mb-4 d-print-none">
        <div class="col-6 col-md-2-4">
            <div class="summary-mini-card">
                <div class="summary-mini-icon bg-primary-subtle text-primary">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block">Total Marked</span>
                    <h4 class="fw-bold text-dark mb-0"><?php echo number_format($totalEntries); ?></h4>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-2-4">
            <div class="summary-mini-card">
                <div class="summary-mini-icon bg-success-subtle text-success">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block text-success">Present</span>
                    <h4 class="fw-bold text-success mb-0"><?php echo number_format($presentCount); ?> <span class="fs-7 text-muted font-monospace">(<?php echo $presentPct; ?>%)</span></h4>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-2-4">
            <div class="summary-mini-card">
                <div class="summary-mini-icon bg-danger-subtle text-danger">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block text-danger">Absent</span>
                    <h4 class="fw-bold text-danger mb-0"><?php echo number_format($absentCount); ?> <span class="fs-7 text-muted font-monospace">(<?php echo $absentPct; ?>%)</span></h4>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-2-4">
            <div class="summary-mini-card">
                <div class="summary-mini-icon bg-warning-subtle text-warning">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block text-warning">Late</span>
                    <h4 class="fw-bold text-warning mb-0"><?php echo number_format($lateCount); ?> <span class="fs-7 text-muted font-monospace">(<?php echo $latePct; ?>%)</span></h4>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-2-4">
            <div class="summary-mini-card">
                <div class="summary-mini-icon bg-purple-subtle text-purple" style="background:#f5f3ff; color:#7c3aed;">
                    <i class="fa-solid fa-calendar-minus"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block" style="color:#7c3aed;">Leave</span>
                    <h4 class="fw-bold mb-0" style="color:#7c3aed;"><?php echo number_format($leaveCount); ?> <span class="fs-7 text-muted font-monospace">(<?php echo $leavePct; ?>%)</span></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Attendance Percentage Overview Section -->
    <div class="adv-card p-4 mb-4 d-print-none">
        <div class="row align-items-center g-3">
            <div class="col-md-4 border-end">
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-shrink-0 text-center px-3 py-2 rounded-3 bg-primary-subtle text-primary border border-primary-subtle">
                        <span class="d-block small text-uppercase text-muted fw-bold">Rate</span>
                        <span class="h3 fw-bold text-primary mb-0"><?php echo $attendancePercentage; ?>%</span>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Overall Attendance Rate</h6>
                        <p class="text-muted small mb-0">Includes Present & Late students marked for <?php echo date('M d, Y', strtotime($filter_date)); ?>.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small fw-semibold text-muted">Attendance Status Distribution</span>
                    <span class="small text-muted"><?php echo number_format($totalEntries); ?> Total Students</span>
                </div>

                <?php if ($totalEntries > 0): ?>
                    <div class="progress-bar-stack mb-3">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $presentPct; ?>%; background-color: #16a34a;" title="Present: <?php echo $presentPct; ?>%"></div>
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $latePct; ?>%; background-color: #d97706;" title="Late: <?php echo $latePct; ?>%"></div>
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $leavePct; ?>%; background-color: #7c3aed;" title="Leave: <?php echo $leavePct; ?>%"></div>
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $absentPct; ?>%; background-color: #dc2626;" title="Absent: <?php echo $absentPct; ?>%"></div>
                    </div>

                    <div class="d-flex flex-wrap gap-3 small">
                        <div class="d-flex align-items-center gap-1">
                            <span class="d-inline-block rounded-circle" style="width: 8px; height: 8px; background-color: #16a34a;"></span>
                            <span class="text-muted">Present: <strong><?php echo number_format($presentCount); ?></strong> (<?php echo $presentPct; ?>%)</span>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <span class="d-inline-block rounded-circle" style="width: 8px; height: 8px; background-color: #d97706;"></span>
                            <span class="text-muted">Late: <strong><?php echo number_format($lateCount); ?></strong> (<?php echo $latePct; ?>%)</span>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <span class="d-inline-block rounded-circle" style="width: 8px; height: 8px; background-color: #7c3aed;"></span>
                            <span class="text-muted">Leave: <strong><?php echo number_format($leaveCount); ?></strong> (<?php echo $leavePct; ?>%)</span>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <span class="d-inline-block rounded-circle" style="width: 8px; height: 8px; background-color: #dc2626;"></span>
                            <span class="text-muted">Absent: <strong><?php echo number_format($absentCount); ?></strong> (<?php echo $absentPct; ?>%)</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-muted small py-2">No attendance data recorded for the selected criteria.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Printable Header (Visible ONLY in Print Mode) -->
    <div class="d-none d-print-block print-header">
        <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
        <h5 class="text-secondary mb-2">Daily Student Attendance Report</h5>
        <div class="small text-muted border-top border-bottom py-2 my-2">
            Date: <strong><?php echo date('F d, Y (l)', strtotime($filter_date)); ?></strong>
            <?php if ($filter_campus): ?> | Campus: <strong><?php echo htmlspecialchars($filter_campus); ?></strong><?php endif; ?>
            <?php if ($filter_class): ?> | Class: <strong><?php echo htmlspecialchars($filter_class); ?></strong><?php endif; ?>
            <?php if ($filter_section): ?> | Section: <strong><?php echo htmlspecialchars($filter_section); ?></strong><?php endif; ?>
            <?php if ($filter_academic_type): ?> | Type: <strong><?php echo htmlspecialchars($filter_academic_type); ?></strong><?php endif; ?>
            <br>
            Total Marked: <strong><?php echo number_format($totalEntries); ?></strong> | Present: <strong><?php echo number_format($presentCount); ?></strong> | Absent: <strong><?php echo number_format($absentCount); ?></strong> | Late: <strong><?php echo number_format($lateCount); ?></strong> | Leave: <strong><?php echo number_format($leaveCount); ?></strong> | Attendance Rate: <strong><?php echo $attendancePercentage; ?>%</strong>
        </div>
    </div>

    <!-- 5. Daily Attendance Records Data Table -->
    <div class="adv-card">
        <div class="adv-card-header d-print-none">
            <h5 class="adv-card-title">
                <i class="fa-solid fa-list-check"></i>Daily Attendance Records
            </h5>
            <span class="badge bg-light text-secondary border px-3 py-1.5 small fw-semibold">
                Showing <?php echo count($records); ?> of <?php echo number_format($totalEntries); ?> Records
            </span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 table-attendance" id="dailyReportTable">
                <thead>
                    <tr>
                        <th width="50" class="text-center">Sr #</th>
                        <th width="60" class="d-print-none">Photo</th>
                        <th width="130">Admission No</th>
                        <th width="90">Roll No</th>
                        <th>Student Name</th>
                        <th>Father / Guardian</th>
                        <th width="120">Class & Section</th>
                        <th width="110">Campus</th>
                        <th width="110">Academic Type</th>
                        <th width="140">Attendance Status</th>
                        <th>Remarks</th>
                        <th width="110">Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="12" class="text-center py-5 text-muted">
                                <div class="py-4">
                                    <i class="fa-solid fa-clipboard-question fs-1 text-secondary opacity-25 mb-3 d-block"></i>
                                    <h5 class="fw-bold text-dark">No Attendance Records Found</h5>
                                    <p class="small text-muted mb-3">No attendance records match the selected date or filter parameters.</p>
                                    <a href="daily.php" class="btn btn-outline-secondary rounded-2 px-3 py-2 fw-semibold small">
                                        <i class="fa-solid fa-rotate-left me-1"></i>Reset Filters
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $srNo = $offset + 1;
                        foreach ($records as $row): 
                            $status = $row['status'];
                            $badgeClass = 'status-present';
                            $statusIcon = 'fa-circle-check';

                            if ($status === 'Absent') {
                                $badgeClass = 'status-absent';
                                $statusIcon = 'fa-circle-xmark';
                            } elseif ($status === 'Late') {
                                $badgeClass = 'status-late';
                                $statusIcon = 'fa-clock';
                            } elseif ($status === 'Leave') {
                                $badgeClass = 'status-leave';
                                $statusIcon = 'fa-plane-departure';
                            }

                            $className = $row['class_name'] ?: ($row['school_class'] ?: '—');
                            $secName   = $row['section'] ?: ($row['school_section'] ?: '—');
                            $fatherName = $row['father_name'] ?: ($row['guardian_name'] ?: '—');
                            $timeFormatted = !empty($row['created_at']) ? date('h:i A', strtotime($row['created_at'])) : '—';
                            $fullName = trim($row['first_name'] . ' ' . $row['last_name']);
                            $initials = strtoupper(substr($row['first_name'] ?? 'S', 0, 1) . substr($row['last_name'] ?? '', 0, 1));
                        ?>
                            <tr>
                                <td class="text-center small fw-semibold text-muted"><?php echo $srNo++; ?></td>
                                <td class="d-print-none">
                                    <?php if (!empty($row['doc_student_photo'])): ?>
                                        <img src="<?php echo APP_URL . '/' . $row['doc_student_photo']; ?>" class="avatar-circle">
                                    <?php else: ?>
                                        <div class="avatar-circle">
                                            <?php echo htmlspecialchars($initials); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2.5 py-1"><?php echo htmlspecialchars($row['admission_no']); ?></span></td>
                                <td><strong class="font-monospace text-dark"><?php echo displayValue($row['roll_no']); ?></strong></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($fullName); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($fatherName); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($className . ' (' . $secName . ')'); ?></span></td>
                                <td><span class="small text-muted"><?php echo htmlspecialchars($row['campus_name']); ?></span></td>
                                <td><span class="small text-muted"><?php echo htmlspecialchars($row['academic_type'] ?: 'School'); ?></span></td>
                                <td>
                                    <span class="status-pill <?php echo $badgeClass; ?>">
                                        <i class="fa-solid <?php echo $statusIcon; ?>"></i>
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?php echo htmlspecialchars($row['remarks'] ?: '—'); ?></td>
                                <td class="text-muted small" style="white-space: nowrap;">
                                    <i class="fa-solid fa-clock me-1 text-primary"></i><?php echo $timeFormatted; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 6. Pagination Navigation -->
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Daily attendance pagination" class="mb-4 d-print-none">
            <ul class="pagination justify-content-center">
                <?php $queryParams = $_GET; ?>
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <?php $queryParams['page'] = $page - 1; ?>
                    <a class="page-link rounded-2 me-1" href="?<?php echo http_build_query($queryParams); ?>">
                        <i class="fa-solid fa-chevron-left me-1"></i>Previous
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php $queryParams['page'] = $i; ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link rounded-2 mx-0.5" href="?<?php echo http_build_query($queryParams); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                    <?php $queryParams['page'] = $page + 1; ?>
                    <a class="page-link rounded-2 ms-1" href="?<?php echo http_build_query($queryParams); ?>">
                        Next<i class="fa-solid fa-chevron-right ms-1"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

    <!-- Printable Footer -->
    <div class="d-none d-print-block text-center mt-5 border-top pt-3">
        <span class="small text-muted">Indus Grammar School ERP System © <?php echo date('Y'); ?> | Generated on <?php echo date('M d, Y h:i A'); ?></span>
    </div>
</div>

<!-- JavaScript Interactivity & Export -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function() {
            const btn = document.getElementById('searchBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generating...';
            }
        });
    }
});

// CSV Export Handler
function exportCSV() {
    let csv = "Sr No,Admission No,Roll No,Student Name,Father/Guardian Name,Class,Section,Campus,Academic Type,Status,Remarks,Time Marked\n";
    <?php if (!empty($records)): 
        $sr = 1;
        foreach ($records as $row): 
            $cName = str_replace('"', '""', $row['class_name'] ?: ($row['school_class'] ?: '—'));
            $sName = str_replace('"', '""', $row['section'] ?: ($row['school_section'] ?: '—'));
            $stName = str_replace('"', '""', $row['first_name'] . ' ' . $row['last_name']);
            $fName = str_replace('"', '""', $row['father_name'] ?: ($row['guardian_name'] ?: '—'));
            $cmpName = str_replace('"', '""', $row['campus_name']);
            $remarksClean = str_replace('"', '""', $row['remarks'] ?: '');
            $timeStr = !empty($row['created_at']) ? date('h:i A', strtotime($row['created_at'])) : '';
    ?>
        csv += '"<?php echo $sr++; ?>","<?php echo $row['admission_no']; ?>","<?php echo $row['roll_no']; ?>","<?php echo $stName; ?>","<?php echo $fName; ?>","<?php echo $cName; ?>","<?php echo $sName; ?>","<?php echo $cmpName; ?>","<?php echo $row['academic_type'] ?: 'School'; ?>","<?php echo $row['status']; ?>","<?php echo $remarksClean; ?>","<?php echo $timeStr; ?>"\n';
    <?php 
        endforeach; 
    endif; 
    ?>

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const fileName = "daily_attendance_report_" + "<?php echo $filter_date; ?>" + ".csv";
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", fileName);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
