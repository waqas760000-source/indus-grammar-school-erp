<?php
/**
 * Indus Grammar School ERP - Diary Report & Dashboard Panel
 * Version 4.0.0 (Premium UI Redesign)
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

// 2. Retrieve Filter Parameters
$from_date = sanitize($_GET['from_date'] ?? '');
$to_date = sanitize($_GET['to_date'] ?? '');
$filter_academic_type = sanitize($_GET['filter_academic_type'] ?? '');
$filter_class = sanitize($_GET['filter_class'] ?? '');
$filter_section = sanitize($_GET['filter_section'] ?? '');
$filter_subject = sanitize($_GET['filter_subject'] ?? '');
$filter_diary_type = sanitize($_GET['filter_diary_type'] ?? '');
$filter_status = sanitize($_GET['filter_status'] ?? '');

$limit = 10;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Build query conditions
$where = " WHERE 1=1";
$params = [];

if ($from_date !== '') {
    $where .= " AND d.diary_date >= :from_date";
    $params['from_date'] = $from_date;
}
if ($to_date !== '') {
    $where .= " AND d.diary_date <= :to_date";
    $params['to_date'] = $to_date;
}
if ($filter_academic_type !== '') {
    $where .= " AND d.academic_type = :filter_academic_type";
    $params['filter_academic_type'] = $filter_academic_type;
}
if ($filter_class !== '') {
    $where .= " AND d.class = :filter_class";
    $params['filter_class'] = $filter_class;
}
if ($filter_section !== '') {
    $where .= " AND d.section = :filter_section";
    $params['filter_section'] = $filter_section;
}
if ($filter_subject !== '') {
    $where .= " AND d.subject LIKE :filter_subject";
    $params['filter_subject'] = '%' . $filter_subject . '%';
}
if ($filter_diary_type !== '') {
    $where .= " AND d.diary_type = :filter_diary_type";
    $params['filter_diary_type'] = $filter_diary_type;
}
if ($filter_status !== '') {
    $where .= " AND d.status = :filter_status";
    $params['filter_status'] = $filter_status;
}

$diaries = [];
$totalEntries = 0;
$todayCount = 0;
$homeworkCount = 0;
$assignmentCount = 0;
$noticeCount = 0;
$weekCount = 0;
$activeClassesCount = 0;
$testReminderCount = 0;

try {
    // Total count query
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM daily_diaries d $where");
    $stmtCount->execute($params);
    $totalEntries = (int)$stmtCount->fetchColumn();

    // Today count query (matching active filters)
    $stmtToday = $db->prepare("SELECT COUNT(*) FROM daily_diaries d $where AND d.diary_date = CURRENT_DATE");
    $stmtToday->execute($params);
    $todayCount = (int)$stmtToday->fetchColumn();

    // Week count query (matching active filters)
    $stmtWeek = $db->prepare("SELECT COUNT(*) FROM daily_diaries d $where AND YEARWEEK(d.diary_date, 1) = YEARWEEK(CURRENT_DATE, 1)");
    $stmtWeek->execute($params);
    $weekCount = (int)$stmtWeek->fetchColumn();

    // Active classes count query
    $stmtClasses = $db->prepare("SELECT COUNT(DISTINCT d.class) FROM daily_diaries d $where AND d.class IS NOT NULL AND d.class != ''");
    $stmtClasses->execute($params);
    $activeClassesCount = (int)$stmtClasses->fetchColumn();

    // Homework count query
    $stmtHomework = $db->prepare("SELECT COUNT(*) FROM daily_diaries d $where AND d.diary_type = 'Homework'");
    $stmtHomework->execute($params);
    $homeworkCount = (int)$stmtHomework->fetchColumn();

    // Assignment count query
    $stmtAssignment = $db->prepare("SELECT COUNT(*) FROM daily_diaries d $where AND d.diary_type = 'Assignment'");
    $stmtAssignment->execute($params);
    $assignmentCount = (int)$stmtAssignment->fetchColumn();

    // Notice count query
    $stmtNotice = $db->prepare("SELECT COUNT(*) FROM daily_diaries d $where AND d.diary_type = 'General Notice'");
    $stmtNotice->execute($params);
    $noticeCount = (int)$stmtNotice->fetchColumn();

    // Test Reminder count query
    $stmtTest = $db->prepare("SELECT COUNT(*) FROM daily_diaries d $where AND d.diary_type = 'Test Reminder'");
    $stmtTest->execute($params);
    $testReminderCount = (int)$stmtTest->fetchColumn();

    // Main records query
    $stmtData = $db->prepare("
        SELECT d.*, u.username as creator_name 
        FROM daily_diaries d 
        LEFT JOIN users u ON d.created_by = u.id 
        $where 
        ORDER BY d.diary_date DESC, d.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($params as $k => $v) {
        $stmtData->bindValue($k, $v);
    }
    $stmtData->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    
    $diaries = $stmtData->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Diary Report main queries error: " . $e->getMessage());
}

$totalPages = ceil($totalEntries / $limit);
if ($totalPages < 1) $totalPages = 1;

// Load unique sections list
$sectionsList = ['A', 'B', 'C', 'D', 'E'];
try {
    $dbSecs = $db->query("SELECT DISTINCT section FROM classes WHERE section != '' ORDER BY section ASC")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dbSecs as $ds) {
        if (!in_array($ds, $sectionsList)) {
            $sectionsList[] = $ds;
        }
    }
} catch (Exception $e) {}

// Setup Layout Header
$viewMode = sanitize($_GET['view'] ?? 'report');
if ($viewMode === 'dashboard') {
    $pageTitle = 'Diary Dashboard';
    $breadcrumbActive = 'Diary Dashboard';
    $headerIcon = 'fa-gauge-high';
    $headerTitle = 'Diary Dashboard Panel';
} elseif ($viewMode === 'analysis') {
    $pageTitle = 'Diary Analysis';
    $breadcrumbActive = 'Diary Analysis';
    $headerIcon = 'fa-chart-pie';
    $headerTitle = 'Diary Analysis & Statistics';
} else {
    $pageTitle = 'Diary Report Panel';
    $breadcrumbActive = 'Diary Report';
    $headerIcon = 'fa-chart-line';
    $headerTitle = 'Diary Report';
}
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Premium Custom CSS Styles for Diary Dashboard -->
<style>
:root {
    --erp-navy: #0F172A;
    --erp-blue: #1D4ED8;
    --erp-light-bg: #F8FAFC;
    --erp-card-bg: #FFFFFF;
    --erp-border: #E2E8F0;
    --erp-text-dark: #1E293B;
    --erp-text-muted: #64748B;
}

.diary-hero-card {
    background: linear-gradient(135deg, #0F172A 0%, #1E293B 55%, #1D4ED8 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 24px 30px;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15);
}

.stat-card-premium {
    background: #ffffff;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 20px;
    transition: all 0.2s ease-in-out;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
}

.stat-card-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
}

.stat-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.quick-action-card {
    background: #ffffff;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 16px 20px;
    text-decoration: none;
    color: #1E293B;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: all 0.2s ease;
}

.quick-action-card:hover {
    border-color: #1D4ED8;
    background: #F8FAFC;
    color: #1D4ED8;
    transform: translateY(-1px);
}

.table-custom-premium {
    border-collapse: separate;
    border-spacing: 0;
}

.table-custom-premium thead th {
    background-color: #F8FAFC;
    color: #475569;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 14px 16px;
    border-bottom: 2px solid #E2E8F0;
}

.table-custom-premium tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid #F1F5F9;
    vertical-align: middle;
}

@media print {
    .left-sidebar, .header-navbar, .d-print-none, .breadcrumb-card, .footer-container {
        display: none !important;
    }
    body, .main-content-container, .card, .card-body {
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
    }
    .table-responsive {
        overflow: visible !important;
    }
    .custom-table {
        width: 100% !important;
        border-collapse: collapse !important;
    }
    .custom-table th, .custom-table td {
        border: 1px solid #dee2e6 !important;
        padding: 6px !important;
    }
}
</style>

<!-- Title Hero Banner -->
<div class="diary-hero-card mb-4 d-print-none">
    <div class="row align-items-center">
        <div class="col-lg-8 mb-3 mb-lg-0">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white bg-opacity-10 p-3 rounded-3">
                    <i class="fa-solid <?php echo $headerIcon; ?> fs-3 text-warning"></i>
                </div>
                <div>
                    <h2 class="fw-bold mb-1 text-white"><?php echo $headerTitle; ?></h2>
                    <p class="text-white-50 mb-0 small">Monitor, manage, and analyze student daily diaries across all classes & subjects</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 text-lg-end">
            <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end align-items-center">
                <span class="badge bg-white bg-opacity-10 text-white px-3 py-2 border border-white border-opacity-20 rounded-pill small">
                    <i class="fa-regular fa-calendar me-1"></i><?php echo date('M d, Y'); ?>
                </span>
                <span class="badge bg-white bg-opacity-10 text-white px-3 py-2 border border-white border-opacity-20 rounded-pill small">
                    <i class="fa-solid fa-graduation-cap me-1"></i>Session 2026-2027
                </span>
                <?php if (hasPermission('student_view')): ?>
                <a href="daily_diary.php" class="btn btn-warning text-dark fw-bold btn-sm px-3 shadow-sm rounded-pill mt-1">
                    <i class="fa-solid fa-plus me-1"></i>New Entry
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Summary Statistics Cards Section -->
<div class="row g-3 mb-4 d-print-none">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="stat-card-premium h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase tracking-wider">Total Entries</span>
                    <h2 class="fw-bold text-dark mt-2 mb-1"><?php echo number_format($totalEntries); ?></h2>
                    <span class="small text-muted"><i class="fa-solid fa-layer-group me-1 text-primary"></i>All recorded entries</span>
                </div>
                <div class="stat-icon-wrapper bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-book-bookmark"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="stat-card-premium h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase tracking-wider">Today's Entries</span>
                    <h2 class="fw-bold text-dark mt-2 mb-1"><?php echo number_format($todayCount); ?></h2>
                    <span class="small text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Created today</span>
                </div>
                <div class="stat-icon-wrapper bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-calendar-day"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="stat-card-premium h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase tracking-wider">This Week</span>
                    <h2 class="fw-bold text-dark mt-2 mb-1"><?php echo number_format($weekCount); ?></h2>
                    <span class="small text-warning fw-semibold"><i class="fa-solid fa-clock-rotate-left me-1"></i>Current week log</span>
                </div>
                <div class="stat-icon-wrapper bg-warning bg-opacity-10 text-warning">
                    <i class="fa-solid fa-calendar-week"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="stat-card-premium h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase tracking-wider">Active Classes</span>
                    <h2 class="fw-bold text-dark mt-2 mb-1"><?php echo number_format($activeClassesCount); ?></h2>
                    <span class="small text-info fw-semibold"><i class="fa-solid fa-school me-1"></i>Classes with diaries</span>
                </div>
                <div class="stat-icon-wrapper bg-info bg-opacity-10 text-info">
                    <i class="fa-solid fa-users-rectangle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Action Workspace -->
<div class="row g-3 mb-4 d-print-none">
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-white p-3" style="border-radius: 14px;">
            <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-bolt me-2 text-warning"></i>Quick Action Workspace</h6>
                <span class="badge bg-light text-muted border">Student Diary Shortcuts</span>
            </div>
            <div class="row g-2">
                <div class="col-6 col-md-3">
                    <a href="daily_diary.php" class="quick-action-card">
                        <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary">
                            <i class="fa-solid fa-circle-plus fs-5"></i>
                        </div>
                        <div>
                            <strong class="d-block text-dark small">Create Daily Diary</strong>
                            <span class="text-muted" style="font-size: 0.75rem;">Add new task entry</span>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="edit_diary.php" class="quick-action-card">
                        <div class="p-2 rounded-3 bg-warning bg-opacity-10 text-warning">
                            <i class="fa-solid fa-pen-to-square fs-5"></i>
                        </div>
                        <div>
                            <strong class="d-block text-dark small">Edit Diary</strong>
                            <span class="text-muted" style="font-size: 0.75rem;">Modify existing logs</span>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="diary_report.php" class="quick-action-card">
                        <div class="p-2 rounded-3 bg-info bg-opacity-10 text-info">
                            <i class="fa-solid fa-file-invoice fs-5"></i>
                        </div>
                        <div>
                            <strong class="d-block text-dark small">Diary Report</strong>
                            <span class="text-muted" style="font-size: 0.75rem;">Filter & print records</span>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="diary_report.php?view=analysis" class="quick-action-card">
                        <div class="p-2 rounded-3 bg-opacity-10" style="color: #8b5cf6; background: rgba(139, 92, 246, 0.1);">
                            <i class="fa-solid fa-chart-pie fs-5"></i>
                        </div>
                        <div>
                            <strong class="d-block text-dark small">Diary Analysis</strong>
                            <span class="text-muted" style="font-size: 0.75rem;">Category breakdown</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Distribution Breakdown -->
<div class="card border-0 shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius: 14px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-chart-bar me-2 text-primary"></i>Diary Category Distribution</h6>
        <span class="small text-muted">Based on <?php echo $totalEntries; ?> total entries</span>
    </div>
    <div class="row g-3">
        <?php 
        $hwPct = $totalEntries > 0 ? round(($homeworkCount / $totalEntries) * 100) : 0;
        $asPct = $totalEntries > 0 ? round(($assignmentCount / $totalEntries) * 100) : 0;
        $ntPct = $totalEntries > 0 ? round(($noticeCount / $totalEntries) * 100) : 0;
        $trPct = $totalEntries > 0 ? round(($testReminderCount / $totalEntries) * 100) : 0;
        ?>
        <div class="col-6 col-md-3">
            <div class="p-3 rounded-3 bg-light border">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold text-muted">Homework</span>
                    <span class="badge bg-primary"><?php echo $homeworkCount; ?></span>
                </div>
                <div class="progress my-2" style="height: 6px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $hwPct; ?>%"></div>
                </div>
                <span class="text-muted" style="font-size:0.75rem;"><?php echo $hwPct; ?>% of total entries</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="p-3 rounded-3 bg-light border">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold text-muted">Assignments</span>
                    <span class="badge bg-info text-dark"><?php echo $assignmentCount; ?></span>
                </div>
                <div class="progress my-2" style="height: 6px;">
                    <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $asPct; ?>%"></div>
                </div>
                <span class="text-muted" style="font-size:0.75rem;"><?php echo $asPct; ?>% of total entries</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="p-3 rounded-3 bg-light border">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold text-muted">General Notices</span>
                    <span class="badge bg-danger"><?php echo $noticeCount; ?></span>
                </div>
                <div class="progress my-2" style="height: 6px;">
                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $ntPct; ?>%"></div>
                </div>
                <span class="text-muted" style="font-size:0.75rem;"><?php echo $ntPct; ?>% of total entries</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="p-3 rounded-3 bg-light border">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold text-muted">Test Reminders</span>
                    <span class="badge bg-warning text-dark"><?php echo $testReminderCount; ?></span>
                </div>
                <div class="progress my-2" style="height: 6px;">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $trPct; ?>%"></div>
                </div>
                <span class="text-muted" style="font-size:0.75rem;"><?php echo $trPct; ?>% of total entries</span>
            </div>
        </div>
    </div>
</div>

<!-- Filters Panel Card -->
<div class="card border-0 shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:14px;">
    <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-filter me-2 text-primary"></i>Filter Diary Entries</h6>
    <form method="GET" action="diary_report.php" id="filterForm" class="row g-3">
        <?php if (!empty($viewMode) && $viewMode !== 'report'): ?>
            <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewMode); ?>">
        <?php endif; ?>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">From Date</label>
            <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">To Date</label>
            <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Academic Type</label>
            <select class="form-select form-select-sm" name="filter_academic_type">
                <option value="">All Types</option>
                <option value="School" <?php echo ($filter_academic_type === 'School') ? 'selected' : ''; ?>>School</option>
                <option value="Academy" <?php echo ($filter_academic_type === 'Academy') ? 'selected' : ''; ?>>Academy</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Class</label>
            <select class="form-select form-select-sm" name="filter_class">
                <option value="">All Classes</option>
                <?php foreach (['Play Group', 'Nursery', 'Prep', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10', 'Class 11', 'Class 12'] as $cls): ?>
                    <option value="<?php echo $cls; ?>" <?php echo ($filter_class === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Section</label>
            <select class="form-select form-select-sm" name="filter_section">
                <option value="">All Sections</option>
                <?php foreach ($sectionsList as $sec): ?>
                    <option value="<?php echo $sec; ?>" <?php echo ($filter_section === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Subject</label>
            <input type="text" class="form-control form-control-sm" name="filter_subject" value="<?php echo htmlspecialchars($filter_subject); ?>" placeholder="e.g. Mathematics">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Diary Type</label>
            <select class="form-select form-select-sm" name="filter_diary_type">
                <option value="">All Categories</option>
                <option value="Homework" <?php echo ($filter_diary_type === 'Homework') ? 'selected' : ''; ?>>Homework</option>
                <option value="Classwork" <?php echo ($filter_diary_type === 'Classwork') ? 'selected' : ''; ?>>Classwork</option>
                <option value="Assignment" <?php echo ($filter_diary_type === 'Assignment') ? 'selected' : ''; ?>>Assignment</option>
                <option value="Test Reminder" <?php echo ($filter_diary_type === 'Test Reminder') ? 'selected' : ''; ?>>Test Reminder</option>
                <option value="General Notice" <?php echo ($filter_diary_type === 'General Notice') ? 'selected' : ''; ?>>General Notice</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Status</label>
            <select class="form-select form-select-sm" name="filter_status">
                <option value="">All</option>
                <option value="Active" <?php echo ($filter_status === 'Active') ? 'selected' : ''; ?>>Active</option>
                <option value="Draft" <?php echo ($filter_status === 'Draft') ? 'selected' : ''; ?>>Draft</option>
            </select>
        </div>
        
        <div class="col-12 text-end mt-4">
            <button type="submit" id="searchBtn" class="btn btn-sm btn-primary px-4"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
            <a href="diary_report.php<?php echo $viewMode !== 'report' ? '?view='.$viewMode : ''; ?>" class="btn btn-sm btn-outline-secondary px-3">Reset</a>
            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Report</button>
            <button type="button" class="btn btn-sm btn-outline-danger px-3" onclick="window.print()"><i class="fa-solid fa-file-pdf me-2"></i>Export PDF</button>
            <button type="button" class="btn btn-sm btn-outline-success px-3" onclick="exportExcel()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
        </div>
    </form>
</div>

<!-- Report Printing Header (Visible ONLY on print layout) -->
<div class="d-none d-print-block text-center mb-4 border-bottom pb-3">
    <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
    <h5 class="text-secondary mb-3">Daily Diaries & Homework Log Report</h5>
    <div class="small text-muted py-2">
        Report Generated: <strong><?php echo date('M d, Y h:i A'); ?></strong>
        <?php if ($from_date || $to_date): ?> | Date Range: <strong><?php echo $from_date ?: 'Start'; ?> to <?php echo $to_date ?: 'End'; ?></strong><?php endif; ?>
        <?php if ($filter_class): ?> | Class: <strong><?php echo htmlspecialchars($filter_class); ?></strong><?php endif; ?>
        <?php if ($filter_section): ?> | Section: <strong><?php echo htmlspecialchars($filter_section); ?></strong><?php endif; ?>
        <?php if ($filter_subject): ?> | Subject: <strong><?php echo htmlspecialchars($filter_subject); ?></strong><?php endif; ?>
    </div>
</div>

<!-- Diary Records Table Card -->
<div class="card border-0 shadow-sm bg-white" style="border-radius: 14px; overflow: hidden;">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-list-check me-2 text-primary"></i>Diary Records Activity Log</h6>
        <span class="badge bg-light text-muted border"><?php echo number_format($totalEntries); ?> entries found</span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom-premium table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Diary Date</th>
                    <th>Academic Type</th>
                    <th>Class</th>
                    <th>Section</th>
                    <th>Subject</th>
                    <th>Diary Type</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Created Date</th>
                    <th class="text-end d-print-none">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($diaries)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-5 text-muted">
                            <div class="py-4">
                                <i class="fa-solid fa-book-open-reader d-block fs-1 mb-3 text-secondary opacity-50"></i>
                                <h6 class="fw-bold text-dark">No Diary Entries Found</h6>
                                <p class="small text-muted mb-3">No student diary records match your selected filters.</p>
                                <?php if (hasPermission('student_view')): ?>
                                    <a href="daily_diary.php" class="btn btn-sm btn-primary px-3 rounded-pill">
                                        <i class="fa-solid fa-plus me-1"></i>Create Daily Diary
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($diaries as $row): ?>
                    <tr>
                        <td><strong class="text-primary"><?php echo date('M d, Y', strtotime($row['diary_date'])); ?></strong></td>
                        <td><span class="badge bg-light text-dark border"><?php echo sanitize($row['academic_type']); ?></span></td>
                        <td><?php echo displayValue($row['class']); ?></td>
                        <td><?php echo displayValue($row['section']); ?></td>
                        <td><strong class="text-dark"><?php echo displayValue($row['subject']); ?></strong></td>
                        <td>
                            <?php
                            $type = $row['diary_type'];
                            $badge = 'bg-secondary';
                            if ($type === 'Homework') $badge = 'bg-primary';
                            elseif ($type === 'Assignment') $badge = 'bg-info text-dark';
                            elseif ($type === 'Test Reminder') $badge = 'bg-warning text-dark';
                            elseif ($type === 'General Notice') $badge = 'bg-danger';
                            ?>
                            <span class="badge <?php echo $badge; ?>"><?php echo sanitize($type); ?></span>
                        </td>
                        <td><strong class="text-dark"><?php echo sanitize($row['title']); ?></strong></td>
                        <td>
                            <span class="badge <?php echo ($row['status'] === 'Active') ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-light text-secondary border'; ?>">
                                <?php echo sanitize($row['status']); ?>
                            </span>
                        </td>
                        <td><span class="small text-muted"><i class="fa-regular fa-user me-1"></i><?php echo displayValue($row['creator_name']); ?></span></td>
                        <td><span class="small text-muted"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></span></td>
                        <td class="text-end d-print-none">
                            <div class="btn-group">
                                <button type="button" onclick='viewDiaryDetails(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' class="btn btn-outline-secondary btn-sm" title="View Details">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                                <a href="print_diary.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-outline-primary btn-sm" title="Print Entry">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                                <a href="edit_diary.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-warning btn-sm" title="Edit Entry">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination layout -->
<?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4 mb-4 d-print-none">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $viewMode !== 'report' ? '&view='.$viewMode : ''; ?><?php echo $from_date ? '&from_date='.$from_date : ''; ?><?php echo $to_date ? '&to_date='.$to_date : ''; ?><?php echo $filter_academic_type ? '&filter_academic_type='.$filter_academic_type : ''; ?><?php echo $filter_class ? '&filter_class='.$filter_class : ''; ?><?php echo $filter_section ? '&filter_section='.$filter_section : ''; ?><?php echo $filter_subject ? '&filter_subject='.$filter_subject : ''; ?><?php echo $filter_diary_type ? '&filter_diary_type='.$filter_diary_type : ''; ?><?php echo $filter_status ? '&filter_status='.$filter_status : ''; ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $viewMode !== 'report' ? '&view='.$viewMode : ''; ?><?php echo $from_date ? '&from_date='.$from_date : ''; ?><?php echo $to_date ? '&to_date='.$to_date : ''; ?><?php echo $filter_academic_type ? '&filter_academic_type='.$filter_academic_type : ''; ?><?php echo $filter_class ? '&filter_class='.$filter_class : ''; ?><?php echo $filter_section ? '&filter_section='.$filter_section : ''; ?><?php echo $filter_subject ? '&filter_subject='.$filter_subject : ''; ?><?php echo $filter_diary_type ? '&filter_diary_type='.$filter_diary_type : ''; ?><?php echo $filter_status ? '&filter_status='.$filter_status : ''; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $viewMode !== 'report' ? '&view='.$viewMode : ''; ?><?php echo $from_date ? '&from_date='.$from_date : ''; ?><?php echo $to_date ? '&to_date='.$to_date : ''; ?><?php echo $filter_academic_type ? '&filter_academic_type='.$filter_academic_type : ''; ?><?php echo $filter_class ? '&filter_class='.$filter_class : ''; ?><?php echo $filter_section ? '&filter_section='.$filter_section : ''; ?><?php echo $filter_subject ? '&filter_subject='.$filter_subject : ''; ?><?php echo $filter_diary_type ? '&filter_diary_type='.$filter_diary_type : ''; ?><?php echo $filter_status ? '&filter_status='.$filter_status : ''; ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<!-- Report Printing Footer (Visible ONLY on print layout) -->
<div class="d-none d-print-block mt-5 text-center border-top pt-3">
    <span class="small text-muted">Page 1 of 1 | Indus Grammar School ERP System © <?php echo date('Y'); ?></span>
</div>

<!-- Modal View Details Panel -->
<div class="modal fade" id="viewDiaryModal" tabindex="-1" aria-labelledby="viewDiaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:14px;">
            <div class="modal-header bg-light border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="viewDiaryModalLabel"><i class="fa-solid fa-book-open text-primary me-2"></i>Diary Entry Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="row g-3" id="printableSingleArea">
                    <div class="col-md-6"><span class="text-muted small d-block">Diary Date</span><strong class="text-dark" id="v-date"></strong></div>
                    <div class="col-md-6"><span class="text-muted small d-block">Academic Type</span><strong class="text-dark" id="v-type"></strong></div>
                    <div class="col-md-4"><span class="text-muted small d-block">Class</span><strong class="text-dark" id="v-class"></strong></div>
                    <div class="col-md-4"><span class="text-muted small d-block">Section</span><strong class="text-dark" id="v-section"></strong></div>
                    <div class="col-md-4"><span class="text-muted small d-block">Subject</span><strong class="text-dark" id="v-subject"></strong></div>
                    <div class="col-md-4"><span class="text-muted small d-block">Diary Type</span><span class="badge bg-primary" id="v-category"></span></div>
                    <div class="col-md-4"><span class="text-muted small d-block">Status</span><span class="badge" id="v-status"></span></div>
                    <div class="col-md-4"><span class="text-muted small d-block">Created By</span><strong class="text-muted" id="v-author"></strong></div>
                    <div class="col-md-6"><span class="text-muted small d-block">Created Date</span><span class="text-dark" id="v-created"></span></div>
                    <div class="col-md-6"><span class="text-muted small d-block">Last Updated</span><span class="text-dark" id="v-updated"></span></div>
                    <div class="col-12"><span class="text-muted small d-block mb-1">Title</span><strong class="text-dark fs-5" id="v-title"></strong></div>
                    <div class="col-12">
                        <span class="text-muted small d-block mb-1">Description</span>
                        <div class="p-3 border rounded bg-light" id="v-desc" style="min-height: 100px; max-height: 400px; overflow-y: auto;"></div>
                    </div>
                    <div class="col-12" id="v-attachment-row">
                        <span class="text-muted small d-block mb-1">Attachment File</span>
                        <a href="#" target="_blank" class="btn btn-sm btn-outline-primary" id="v-attachment-link"><i class="fa-solid fa-paperclip me-2"></i>Download File</a>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" onclick="printSingleArea()"><i class="fa-solid fa-print me-2"></i>Print Entry</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Search loading spinner activation
document.getElementById('filterForm').addEventListener('submit', function() {
    const btn = document.getElementById('searchBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Searching...';
});

const viewModal = new bootstrap.Modal(document.getElementById("viewDiaryModal"));
let activeDiaryObject = null;

function viewDiaryDetails(d) {
    activeDiaryObject = d;
    document.getElementById("v-date").textContent = d.diary_date;
    document.getElementById("v-type").textContent = d.academic_type;
    document.getElementById("v-class").textContent = d.class;
    document.getElementById("v-section").textContent = d.section;
    document.getElementById("v-subject").textContent = d.subject;
    document.getElementById("v-title").textContent = d.title;
    document.getElementById("v-author").textContent = d.creator_name ? d.creator_name : 'System';
    document.getElementById("v-created").textContent = d.created_at;
    document.getElementById("v-updated").textContent = d.updated_at;
    
    // Category badge
    const cat = document.getElementById("v-category");
    cat.textContent = d.diary_type;
    cat.className = "badge " + (d.diary_type === 'Homework' ? 'bg-primary' : (d.diary_type === 'General Notice' ? 'bg-danger' : 'bg-info text-dark'));

    // Status badge
    const stat = document.getElementById("v-status");
    stat.textContent = d.status;
    stat.className = "badge " + (d.status === 'Active' ? 'bg-success' : 'bg-secondary');

    // Description HTML inject
    document.getElementById("v-desc").innerHTML = d.description;

    // Attachment row
    const attRow = document.getElementById("v-attachment-row");
    if (d.attachment) {
        attRow.style.display = "block";
        document.getElementById("v-attachment-link").href = "<?php echo APP_URL; ?>/" + d.attachment;
    } else {
        attRow.style.display = "none";
    }

    viewModal.show();
}

// Print single entry from modal
function printSingleArea() {
    const printContent = document.getElementById("printableSingleArea").innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <div style="padding:40px; font-family: sans-serif;">
            <h2 style="text-align:center; margin-bottom: 2px;">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
            <h4 style="text-align:center; color: #555; border-bottom: 2px solid #333; padding-bottom: 10px; margin-top: 0;">Daily Diary Task Details</h4>
            ${printContent}
        </div>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload();
}

// CSV / Excel exporter logic
function exportExcel() {
    let csv = "Diary Date,Academic Type,Class,Section,Subject,Diary Type,Title,Status,Created By,Created Date\n";
    <?php if (!empty($diaries)): foreach ($diaries as $row): ?>
        csv += "<?php echo $row['diary_date']; ?>,<?php echo $row['academic_type']; ?>,<?php echo $row['class']; ?>,<?php echo $row['section']; ?>,<?php echo $row['subject']; ?>,<?php echo $row['diary_type']; ?>,<?php echo $row['title']; ?>,<?php echo $row['status']; ?>,<?php echo $row['creator_name'] ?: 'System'; ?>,<?php echo $row['created_at']; ?>\n";
    <?php endforeach; endif; ?>

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "daily_diary_report_" + new Date().toISOString().slice(0,10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
