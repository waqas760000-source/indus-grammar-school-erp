<?php
/**
 * Indus Grammar School ERP - Attendance Register Submodule
 * Version 4.0.0 (Premium ERP Design System)
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('attendance_view');

$db = Database::getConnection();

// 2. Retrieve & Sanitize Filters
$search_admission     = sanitize($_GET['search_admission'] ?? '');
$search_name          = sanitize($_GET['search_name'] ?? '');
$filter_session       = sanitize($_GET['session'] ?? '');
$filter_campus        = sanitize($_GET['campus'] ?? '');
$search_academic_type = sanitize($_GET['search_academic_type'] ?? '');
$search_class         = sanitize($_GET['search_class'] ?? '');
$search_section       = sanitize($_GET['search_section'] ?? '');
$filter_status        = sanitize($_GET['status'] ?? '');
$filter_search        = sanitize($_GET['search'] ?? '');
$from_date            = sanitize($_GET['from_date'] ?? date('Y-m-01'));
$to_date              = sanitize($_GET['to_date'] ?? date('Y-m-d'));
$view_student_id      = (int)($_GET['view_student_id'] ?? 0);

// 3. Look up target student profile if view_student_id or exact admission is selected
$profileStudent = null;
$studentStats = [];

if ($view_student_id > 0 || !empty($search_admission)) {
    try {
        $stQuery = "
            SELECT s.id, s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section,
                   c.class_name, c.section, d.doc_student_photo, d.roll_no, d.father_name, COALESCE(d.campus, 'Main Campus') as campus_name
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN student_registration_details d ON s.id = d.student_id
            WHERE 1=1
        ";
        if ($view_student_id > 0) {
            $stQuery .= " AND s.id = ?";
            $stStmt = $db->prepare($stQuery);
            $stStmt->execute([$view_student_id]);
        } else {
            $stQuery .= " AND s.admission_no = ?";
            $stStmt = $db->prepare($stQuery);
            $stStmt->execute([$search_admission]);
        }
        $profileStudent = $stStmt->fetch(PDO::FETCH_ASSOC);

        if ($profileStudent) {
            $statStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(status = 'Present') as present,
                    SUM(status = 'Absent') as absent,
                    SUM(status = 'Leave') as leave_days,
                    SUM(status = 'Late') as late
                FROM attendance
                WHERE student_id = ? AND date BETWEEN ? AND ?
            ");
            $statStmt->execute([$profileStudent['id'], $from_date, $to_date]);
            $studentStats = $statStmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error fetching student profile dossier: " . $e->getMessage());
    }
}

// 4. Dynamic Lists for Filter Dropdowns
$sessionsList = [];
try {
    $sessionsList = $db->query("SELECT DISTINCT academic_session FROM student_registration_details WHERE academic_session IS NOT NULL AND academic_session != '' ORDER BY academic_session DESC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$campusesList = [];
try {
    $campusesList = $db->query("SELECT DISTINCT campus FROM student_registration_details WHERE campus IS NOT NULL AND campus != '' ORDER BY campus ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
if (empty($campusesList)) {
    $campusesList = ['Main Campus', 'Boys Campus', 'Girls Campus'];
}

$classesList = [];
try {
    $classesList = $db->query("SELECT DISTINCT class_name FROM classes WHERE class_name != '' ORDER BY class_name ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
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

// 5. Pagination Setup
$limit = 15;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 6. Build Query Conditions for Register Logs
$where = " WHERE 1=1";
$params = [];

if ($view_student_id > 0) {
    $where .= " AND a.student_id = :view_student_id";
    $params['view_student_id'] = $view_student_id;
} else {
    if ($search_admission !== '') {
        $where .= " AND s.admission_no = :admission";
        $params['admission'] = $search_admission;
    }
    if ($search_name !== '') {
        $where .= " AND (s.first_name LIKE :name OR s.last_name LIKE :name)";
        $params['name'] = '%' . $search_name . '%';
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
    if ($search_academic_type !== '') {
        $where .= " AND s.academic_type = :academic_type";
        $params['academic_type'] = $search_academic_type;
    }
    if ($search_class !== '') {
        $where .= " AND (c.class_name = :class1 OR s.school_class = :class2)";
        $params['class1'] = $search_class;
        $params['class2'] = $search_class;
    }
    if ($search_section !== '') {
        $where .= " AND (c.section = :section1 OR s.school_section = :section2)";
        $params['section1'] = $search_section;
        $params['section2'] = $search_section;
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
}

if ($from_date !== '' && $to_date !== '') {
    $where .= " AND a.date BETWEEN :from_date AND :to_date";
    $params['from_date'] = $from_date;
    $params['to_date'] = $to_date;
}

$logs = [];
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
    $totalSum     = $presentCount + $absentCount + $lateCount + $leaveCount;

    if ($totalSum > 0) {
        $attendancePercentage = round((($presentCount + $lateCount) / $totalSum) * 100, 1);
        $presentPct = round(($presentCount / $totalSum) * 100, 1);
        $absentPct  = round(($absentCount / $totalSum) * 100, 1);
        $latePct    = round(($lateCount / $totalSum) * 100, 1);
        $leavePct   = round(($leaveCount / $totalSum) * 100, 1);
    }

    // C. Fetch Data Logs
    $stmtData = $db->prepare("
        SELECT a.*, s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section, s.guardian_name, s.guardian_phone,
               c.class_name, c.section, d.doc_student_photo, d.roll_no, COALESCE(d.campus, 'Main Campus') as campus_name, d.father_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY a.date DESC, s.admission_no ASC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmtData->bindValue($k, $v);
    }
    $stmtData->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();

    $logs = $stmtData->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Attendance register querying error: " . $e->getMessage());
    $errorMessage = "Unable to load attendance register. Please try again.";
}

$totalPages = ceil($totalEntries / $limit);
if ($totalPages < 1) $totalPages = 1;

// 7. Include Layout Header & Embedded Custom ERP Styles
$pageTitle = 'Attendance Register';
$breadcrumbActive = 'Attendance';

$extraCSS = '
<style>
:root {
    --primary-navy: #0F172A;
    --primary-blue: #1D4ED8;
    --secondary-blue: #2563EB;
    --light-blue: #EFF6FF;
    --page-background: #F1F5F9;
    --card-white: #FFFFFF;
    --border-color: #E2E8F0;
    --main-text: #1E293B;
    --muted-text: #64748B;

    --present-color: #16A34A;
    --present-bg: #F0FDF4;

    --absent-color: #DC2626;
    --absent-bg: #FEF2F2;

    --late-color: #D97706;
    --late-bg: #FFFBEB;

    --leave-color: #7C3AED;
    --leave-bg: #F5F3FF;

    --unmarked-color: #64748B;
    --unmarked-bg: #F8FAFC;
}

body {
    background-color: var(--page-background) !important;
    color: var(--main-text);
}

/* Hero Gradient Header Banner (Matching Student Registration) */
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
    content: "";
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

.erp-card {
    background-color: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
}

.context-bar {
    background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
    color: #FFFFFF;
    border-radius: 12px;
    padding: 1rem 1.25rem;
}

.kpi-card {
    border-radius: 16px;
    border: 1px solid var(--border-color);
    background: var(--card-white);
    padding: 1.2rem;
    position: relative;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(15, 23, 42, 0.06);
}

.kpi-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.kpi-total::before { background-color: var(--primary-navy); }
.kpi-present::before { background-color: var(--present-color); }
.kpi-absent::before { background-color: var(--absent-color); }
.kpi-late::before { background-color: var(--late-color); }
.kpi-leave::before { background-color: var(--leave-color); }
.kpi-rate::before { background-color: var(--primary-blue); }

.kpi-icon-wrapper {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.kpi-total .kpi-icon-wrapper { background-color: #F1F5F9; color: var(--primary-navy); }
.kpi-present .kpi-icon-wrapper { background-color: var(--present-bg); color: var(--present-color); }
.kpi-absent .kpi-icon-wrapper { background-color: var(--absent-bg); color: var(--absent-color); }
.kpi-late .kpi-icon-wrapper { background-color: var(--late-bg); color: var(--late-color); }
.kpi-leave .kpi-icon-wrapper { background-color: var(--leave-bg); color: var(--leave-color); }
.kpi-rate .kpi-icon-wrapper { background-color: var(--light-blue); color: var(--primary-blue); }

.status-badge {
    padding: 6px 14px;
    border-radius: 50rem;
    font-weight: 600;
    font-size: 0.82rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    line-height: 1;
}

.status-present { background-color: var(--present-bg); color: var(--present-color); border: 1px solid rgba(22, 163, 74, 0.25); }
.status-absent { background-color: var(--absent-bg); color: var(--absent-color); border: 1px solid rgba(220, 38, 38, 0.25); }
.status-late { background-color: var(--late-bg); color: var(--late-color); border: 1px solid rgba(217, 119, 6, 0.25); }
.status-leave { background-color: var(--leave-bg); color: var(--leave-color); border: 1px solid rgba(124, 58, 237, 0.25); }

.table-erp {
    margin-bottom: 0;
}

.table-erp th {
    background-color: #F8FAFC;
    color: var(--muted-text);
    font-weight: 600;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.9rem 1.1rem;
    border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}

.table-erp td {
    padding: 0.95rem 1.1rem;
    color: var(--main-text);
    border-bottom: 1px solid #F1F5F9;
    vertical-align: middle;
    font-size: 0.9rem;
}

.table-erp tbody tr:hover {
    background-color: #F8FAFC;
}

.btn-erp-primary {
    background-color: var(--primary-blue);
    color: #FFFFFF;
    border: none;
    font-weight: 600;
    border-radius: 8px;
    padding: 0.55rem 1.35rem;
    transition: background-color 0.15s ease;
}

.btn-erp-primary:hover {
    background-color: #1E40AF;
    color: #FFFFFF;
}

.btn-erp-outline {
    background-color: transparent;
    color: var(--main-text);
    border: 1px solid var(--border-color);
    font-weight: 500;
    border-radius: 8px;
    padding: 0.55rem 1.1rem;
    transition: all 0.15s ease;
}

.btn-erp-outline:hover {
    background-color: #F8FAFC;
    border-color: #CBD5E1;
    color: var(--primary-navy);
}

.progress-bar-stack {
    height: 10px;
    border-radius: 6px;
    overflow: hidden;
    background-color: #E2E8F0;
    display: flex;
}

.empty-state-card {
    padding: 3.5rem 1.5rem;
    text-align: center;
}

.empty-state-icon {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background-color: var(--light-blue);
    color: var(--primary-blue);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin-bottom: 1.25rem;
}

@media print {
    body {
        background-color: #ffffff !important;
        color: #000000 !important;
    }
    .d-print-none, #sidebar, .navbar, .breadcrumb-wrapper, .filter-card, .btn, .pagination, footer {
        display: none !important;
    }
    .container-fluid {
        padding: 0 !important;
        margin: 0 !important;
    }
    .erp-card {
        border: none !important;
        box-shadow: none !important;
    }
    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 20px;
        border-bottom: 2px solid #0F172A;
        padding-bottom: 12px;
    }
    .table-erp th {
        background-color: #F1F5F9 !important;
        color: #0F172A !important;
        border: 1px solid #CBD5E1 !important;
    }
    .table-erp td {
        border: 1px solid #E2E8F0 !important;
        color: #000000 !important;
    }
    tr {
        page-break-inside: avoid;
    }
}
</style>
';

include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Breadcrumb Navigation (Matching Student Registration) -->
<nav aria-label="breadcrumb" class="adv-breadcrumb d-print-none">
    <ol class="breadcrumb mb-2">
        <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/index.php"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
        <li class="breadcrumb-item"><a href="#">Attendance</a></li>
        <li class="breadcrumb-item active" aria-current="page">Attendance Register</li>
    </ol>
</nav>

<!-- Hero Blue Gradient Header Banner (Matching Student Registration) -->
<div class="adv-hero-banner mb-4 d-print-none">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
        <div class="d-flex align-items-center gap-3">
            <div class="hero-icon-box">
                <i class="fa-solid fa-book"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h3 class="fw-bold mb-0 text-white fs-3">Attendance Register</h3>
                    <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">History Dossier</span>
                </div>
                <p class="text-white-50 small mb-0 fs-6">View, review, and analyze student attendance records by date, class, section, campus, and academic session.</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-sm btn-light text-primary rounded-2 px-3 py-2 fw-semibold shadow-sm" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print Register</button>
            <button type="button" class="btn btn-sm btn-outline-light rounded-2 px-3 py-2 fw-semibold" onclick="exportCSV()"><i class="fa-solid fa-file-excel me-1"></i>Export CSV</button>
        </div>
    </div>
</div>

<!-- Display Technical Error Alert if Query Failed -->
<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center gap-3 mb-4" style="border-radius:12px;">
        <i class="fa-solid fa-triangle-exclamation fs-4"></i>
        <div>
            <strong>Database Error:</strong> <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    </div>
<?php endif; ?>

<!-- Filter Panel Card (Hidden in Print) -->
<div class="erp-card p-4 mb-4 filter-card d-print-none">
    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">
        <div>
            <h6 class="fw-bold mb-0" style="color: var(--primary-navy);"><i class="fa-solid fa-sliders me-2 text-primary"></i>Attendance Register Filters</h6>
            <span class="text-muted small">Select the attendance period and academic details to view the register.</span>
        </div>
        <?php if ($view_student_id > 0): ?>
            <a href="reports.php?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" class="badge bg-secondary text-white text-decoration-none px-3 py-2 rounded-pill small">
                <i class="fa-solid fa-xmark me-1"></i>Clear Student Dossier Filter
            </a>
        <?php endif; ?>
    </div>

    <form method="GET" action="reports.php" id="searchForm">
        <div class="row g-3">
            <!-- Date Range From -->
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-muted">From Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" required>
            </div>

            <!-- Date Range To -->
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-muted">To Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" required>
            </div>

            <!-- Academic Session -->
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-muted">Academic Session</label>
                <select class="form-select form-select-sm" name="session">
                    <option value="">All Sessions</option>
                    <?php foreach ($sessionsList as $ses): ?>
                        <option value="<?php echo htmlspecialchars($ses); ?>" <?php echo ($filter_session === $ses) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ses); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Campus -->
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-muted">Campus</label>
                <select class="form-select form-select-sm" name="campus">
                    <option value="">All Campuses</option>
                    <?php foreach ($campusesList as $cmp): ?>
                        <option value="<?php echo htmlspecialchars($cmp); ?>" <?php echo ($filter_campus === $cmp) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cmp); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Academic Type -->
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-muted">Academic Type</label>
                <select class="form-select form-select-sm" name="search_academic_type">
                    <option value="">All Types</option>
                    <option value="School" <?php echo ($search_academic_type === 'School') ? 'selected' : ''; ?>>School</option>
                    <option value="Academy" <?php echo ($search_academic_type === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                </select>
            </div>

            <!-- Class -->
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-muted">Class</label>
                <select class="form-select form-select-sm" name="search_class">
                    <option value="">All Classes</option>
                    <?php foreach ($classesList as $cls): ?>
                        <option value="<?php echo htmlspecialchars($cls); ?>" <?php echo ($search_class === $cls) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cls); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Section -->
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-muted">Section</label>
                <select class="form-select form-select-sm" name="search_section">
                    <option value="">All Sections</option>
                    <?php foreach ($sectionsList as $sec): ?>
                        <option value="<?php echo htmlspecialchars($sec); ?>" <?php echo ($search_section === $sec) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sec); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Attendance Status -->
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-muted">Attendance Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Statuses</option>
                    <option value="Present" <?php echo ($filter_status === 'Present') ? 'selected' : ''; ?>>Present</option>
                    <option value="Absent" <?php echo ($filter_status === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                    <option value="Late" <?php echo ($filter_status === 'Late') ? 'selected' : ''; ?>>Late</option>
                    <option value="Leave" <?php echo ($filter_status === 'Leave') ? 'selected' : ''; ?>>Leave</option>
                </select>
            </div>

            <!-- Search Field -->
            <div class="col-12 col-md-8 col-lg-8">
                <label class="form-label small fw-semibold text-muted">Search Student</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control form-control-sm border-start-0 ps-0" name="search" placeholder="Search by student name, roll number, or admission number..." value="<?php echo htmlspecialchars($filter_search); ?>">
                </div>
            </div>

            <!-- Form Buttons -->
            <div class="col-12 col-md-4 col-lg-4 d-flex align-items-end justify-content-end gap-2">
                <a href="reports.php" class="btn btn-erp-outline btn-sm">Reset</a>
                <button type="submit" id="searchBtn" class="btn btn-erp-primary btn-sm px-4">
                    <i class="fa-solid fa-arrows-spin me-1"></i>Generate Register
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Report Context Summary Bar -->
<div class="context-bar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 shadow-sm">
    <div class="d-flex align-items-center gap-3">
        <i class="fa-solid fa-circle-info fs-5 text-info"></i>
        <div>
            <span class="fw-bold d-block" style="font-size:0.95rem;">Attendance Register Dossier Overview</span>
            <span class="small opacity-75">
                Period: <?php echo date('M d, Y', strtotime($from_date)); ?> to <?php echo date('M d, Y', strtotime($to_date)); ?>
                | <?php echo $filter_campus ? htmlspecialchars($filter_campus) : 'All Campuses'; ?>
                | <?php echo $search_academic_type ? htmlspecialchars($search_academic_type) : 'All Academic Types'; ?>
                | <?php echo $search_class ? htmlspecialchars($search_class . ($search_section ? ' - '.$search_section : '')) : 'All Classes'; ?>
            </span>
        </div>
    </div>
    <div>
        <span class="badge bg-white text-dark px-3 py-2 fw-semibold fs-6">
            <i class="fa-solid fa-list-check me-1 text-primary"></i><?php echo number_format($totalEntries); ?> Total Register Logs
        </span>
    </div>
</div>

<!-- Student Profile Dossier Summary Card (Visible when specific student is selected) -->
<?php if ($profileStudent): 
    $tot = (int)($studentStats['total'] ?? 0);
    $pres = (int)($studentStats['present'] ?? 0);
    $abs = (int)($studentStats['absent'] ?? 0);
    $lve = (int)($studentStats['leave_days'] ?? 0);
    $lat = (int)($studentStats['late'] ?? 0);
    
    $perc = 0.0;
    if ($tot > 0) {
        $perc = round((($pres + $lat) / $tot) * 100, 1);
    }
?>
    <div class="erp-card p-4 mb-4" style="border-left: 5px solid var(--primary-blue) !important;">
        <div class="row align-items-center g-3">
            <div class="col-md-2 text-center border-end-md">
                <div class="avatar-medium border rounded-circle mx-auto mb-2 bg-light d-flex align-items-center justify-content-center" style="width: 84px; height: 84px; overflow:hidden;">
                    <?php if (!empty($profileStudent['doc_student_photo'])): ?>
                        <img src="<?php echo APP_URL . '/' . $profileStudent['doc_student_photo']; ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <i class="fa-solid fa-user fs-2 text-muted"></i>
                    <?php endif; ?>
                </div>
                <h6 class="fw-bold text-dark mb-0"><?php echo sanitize($profileStudent['first_name'] . ' ' . $profileStudent['last_name']); ?></h6>
                <span class="small text-muted font-monospace"><?php echo sanitize($profileStudent['admission_no']); ?></span>
                <?php if (!empty($profileStudent['roll_no'])): ?>
                    <br><span class="badge bg-light text-secondary border small font-monospace">Roll: <?php echo sanitize($profileStudent['roll_no']); ?></span>
                <?php endif; ?>
            </div>
            
            <div class="col-md-10">
                <div class="row text-center text-md-start g-3">
                    <div class="col-6 col-md-3">
                        <span class="text-muted small d-block">Academic Placement</span>
                        <strong class="text-dark"><?php echo sanitize(($profileStudent['class_name'] ?? $profileStudent['school_class'] ?? '—') . ' (' . ($profileStudent['section'] ?? $profileStudent['school_section'] ?? 'A') . ')'); ?></strong>
                        <br><span class="badge bg-secondary-subtle text-secondary mt-1"><?php echo sanitize($profileStudent['academic_type']); ?></span>
                    </div>
                    <div class="col-6 col-md-2">
                        <span class="text-muted small d-block">Attendance Rate %</span>
                        <h3 class="fw-bold text-primary mb-0"><?php echo $perc; ?>%</h3>
                    </div>
                    <div class="col-3 col-md-2">
                        <span class="text-muted small d-block">Present Days</span>
                        <h4 class="fw-bold text-success mb-0"><?php echo $pres; ?></h4>
                    </div>
                    <div class="col-3 col-md-2">
                        <span class="text-muted small d-block">Absent Days</span>
                        <h4 class="fw-bold text-danger mb-0"><?php echo $abs; ?></h4>
                    </div>
                    <div class="col-3 col-md-2">
                        <span class="text-muted small d-block">Late Days</span>
                        <h4 class="fw-bold text-warning mb-0"><?php echo $lat; ?></h4>
                    </div>
                    <div class="col-3 col-md-1">
                        <span class="text-muted small d-block">Leave</span>
                        <h4 class="fw-bold text-secondary mb-0"><?php echo $lve; ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 6 KPI Summary Cards Section -->
<div class="row g-3 mb-4">
    <!-- Card 1: Total Records -->
    <div class="col-12 col-sm-6 col-md-4 col-lg-2">
        <div class="kpi-card kpi-total">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Total Logs</span>
                <div class="kpi-icon-wrapper">
                    <i class="fa-solid fa-list-check"></i>
                </div>
            </div>
            <div>
                <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($totalEntries); ?></h3>
                <span class="text-muted small" style="font-size:0.75rem;">Recorded Logs</span>
            </div>
        </div>
    </div>

    <!-- Card 2: Present -->
    <div class="col-12 col-sm-6 col-md-4 col-lg-2">
        <div class="kpi-card kpi-present">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Present</span>
                <div class="kpi-icon-wrapper">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--present-color);"><?php echo number_format($presentCount); ?></h3>
                <span class="text-muted small" style="font-size:0.75rem;"><?php echo $presentPct; ?>% of total</span>
            </div>
        </div>
    </div>

    <!-- Card 3: Absent -->
    <div class="col-12 col-sm-6 col-md-4 col-lg-2">
        <div class="kpi-card kpi-absent">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Absent</span>
                <div class="kpi-icon-wrapper">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
            </div>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--absent-color);"><?php echo number_format($absentCount); ?></h3>
                <span class="text-muted small" style="font-size:0.75rem;"><?php echo $absentPct; ?>% of total</span>
            </div>
        </div>
    </div>

    <!-- Card 4: Late -->
    <div class="col-12 col-sm-6 col-md-4 col-lg-2">
        <div class="kpi-card kpi-late">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Late</span>
                <div class="kpi-icon-wrapper">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--late-color);"><?php echo number_format($lateCount); ?></h3>
                <span class="text-muted small" style="font-size:0.75rem;"><?php echo $latePct; ?>% of total</span>
            </div>
        </div>
    </div>

    <!-- Card 5: Leave -->
    <div class="col-12 col-sm-6 col-md-4 col-lg-2">
        <div class="kpi-card kpi-leave">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Leave</span>
                <div class="kpi-icon-wrapper">
                    <i class="fa-solid fa-calendar-minus"></i>
                </div>
            </div>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--leave-color);"><?php echo number_format($leaveCount); ?></h3>
                <span class="text-muted small" style="font-size:0.75rem;"><?php echo $leavePct; ?>% of total</span>
            </div>
        </div>
    </div>

    <!-- Card 6: Attendance Rate -->
    <div class="col-12 col-sm-6 col-md-4 col-lg-2">
        <div class="kpi-card kpi-rate">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Overall Rate</span>
                <div class="kpi-icon-wrapper">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--primary-blue);"><?php echo $attendancePercentage; ?>%</h3>
                <span class="text-muted small" style="font-size:0.75rem;">Completeness Rate</span>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Distribution Stack (Hidden in Print) -->
<div class="erp-card p-4 mb-4 d-print-none">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="small fw-semibold text-muted">Register Status Distribution Overview</span>
        <span class="small text-muted"><?php echo number_format($totalEntries); ?> Total Register Entries</span>
    </div>

    <?php if ($totalEntries > 0): ?>
        <div class="progress-bar-stack mb-3">
            <div class="progress-bar" role="progressbar" style="width: <?php echo $presentPct; ?>%; background-color: var(--present-color);" title="Present: <?php echo $presentPct; ?>%"></div>
            <div class="progress-bar" role="progressbar" style="width: <?php echo $latePct; ?>%; background-color: var(--late-color);" title="Late: <?php echo $latePct; ?>%"></div>
            <div class="progress-bar" role="progressbar" style="width: <?php echo $leavePct; ?>%; background-color: var(--leave-color);" title="Leave: <?php echo $leavePct; ?>%"></div>
            <div class="progress-bar" role="progressbar" style="width: <?php echo $absentPct; ?>%; background-color: var(--absent-color);" title="Absent: <?php echo $absentPct; ?>%"></div>
        </div>

        <div class="d-flex flex-wrap gap-3 small">
            <div class="d-flex align-items-center gap-1">
                <span class="d-inline-block rounded-circle" style="width: 8px; height: 8px; background-color: var(--present-color);"></span>
                <span class="text-muted">Present: <strong><?php echo $presentCount; ?></strong> (<?php echo $presentPct; ?>%)</span>
            </div>
            <div class="d-flex align-items-center gap-1">
                <span class="d-inline-block rounded-circle" style="width: 8px; height: 8px; background-color: var(--late-color);"></span>
                <span class="text-muted">Late: <strong><?php echo $lateCount; ?></strong> (<?php echo $latePct; ?>%)</span>
            </div>
            <div class="d-flex align-items-center gap-1">
                <span class="d-inline-block rounded-circle" style="width: 8px; height: 8px; background-color: var(--leave-color);"></span>
                <span class="text-muted">Leave: <strong><?php echo $leaveCount; ?></strong> (<?php echo $leavePct; ?>%)</span>
            </div>
            <div class="d-flex align-items-center gap-1">
                <span class="d-inline-block rounded-circle" style="width: 8px; height: 8px; background-color: var(--absent-color);"></span>
                <span class="text-muted">Absent: <strong><?php echo $absentCount; ?></strong> (<?php echo $absentPct; ?>%)</span>
            </div>
        </div>
    <?php else: ?>
        <div class="text-muted small py-2">No attendance logs recorded for the selected criteria.</div>
    <?php endif; ?>
</div>

<!-- Printable Header (Visible ONLY in Print Mode) -->
<div class="d-none d-print-block print-header">
    <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
    <h5 class="text-secondary mb-2">Attendance History Dossier Register</h5>
    <div class="small text-muted border-top border-bottom py-2 my-2">
        Date Range: <strong><?php echo date('M d, Y', strtotime($from_date)); ?> to <?php echo date('M d, Y', strtotime($to_date)); ?></strong>
        <?php if ($filter_campus): ?> | Campus: <strong><?php echo htmlspecialchars($filter_campus); ?></strong><?php endif; ?>
        <?php if ($search_class): ?> | Class: <strong><?php echo htmlspecialchars($search_class); ?></strong><?php endif; ?>
        <?php if ($search_section): ?> | Section: <strong><?php echo htmlspecialchars($search_section); ?></strong><?php endif; ?>
        <?php if ($search_academic_type): ?> | Type: <strong><?php echo htmlspecialchars($search_academic_type); ?></strong><?php endif; ?>
        <br>
        Total Logs: <strong><?php echo $totalEntries; ?></strong> | Present: <strong><?php echo $presentCount; ?></strong> | Absent: <strong><?php echo $absentCount; ?></strong> | Late: <strong><?php echo $lateCount; ?></strong> | Leave: <strong><?php echo $leaveCount; ?></strong>
    </div>
</div>

<!-- Attendance History Log Table Card -->
<div class="erp-card mb-4 overflow-hidden">
    <div class="d-flex align-items-center justify-content-between p-3 border-bottom d-print-none">
        <h6 class="fw-bold mb-0" style="color: var(--primary-navy);"><i class="fa-solid fa-table me-2 text-primary"></i>Attendance Register History Logs</h6>
        <span class="badge bg-light text-secondary border px-3 py-2 fw-semibold">
            Showing <?php echo count($logs); ?> of <?php echo $totalEntries; ?> Records
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-erp align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">Photo</th>
                    <th>Admission No</th>
                    <th>Roll No</th>
                    <th>Student Name</th>
                    <th>Father / Guardian</th>
                    <th>Class & Section</th>
                    <th>Campus</th>
                    <th>Attendance Date</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th class="text-end d-print-none">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="11" class="p-0">
                            <div class="empty-state-card">
                                <div class="empty-state-icon">
                                    <i class="fa-solid fa-clipboard-question"></i>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">No Attendance Register Logs Found</h5>
                                <p class="text-muted small mb-3">No attendance records match the selected date range or filter criteria.</p>
                                <a href="reports.php" class="btn btn-erp-outline btn-sm">
                                    <i class="fa-solid fa-rotate-left me-1"></i>Reset Filters
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($logs as $row): 
                    $status = $row['status'];
                    $badgeClass = 'status-present';
                    $statusIcon = 'fa-check';

                    if ($status === 'Absent') {
                        $badgeClass = 'status-absent';
                        $statusIcon = 'fa-xmark';
                    } elseif ($status === 'Late') {
                        $badgeClass = 'status-late';
                        $statusIcon = 'fa-clock';
                    } elseif ($status === 'Leave') {
                        $badgeClass = 'status-leave';
                        $statusIcon = 'fa-calendar-minus';
                    }

                    $cName = $row['class_name'] ?: ($row['school_class'] ?: '—');
                    $sName = $row['section'] ?: ($row['school_section'] ?: '—');
                    $fName = $row['father_name'] ?: ($row['guardian_name'] ?: '—');
                ?>
                    <tr>
                        <td>
                            <div class="avatar-small border rounded-circle d-flex align-items-center justify-content-center bg-light" style="width: 38px; height: 38px; overflow:hidden;">
                                <?php if (!empty($row['doc_student_photo'])): ?>
                                    <img src="<?php echo APP_URL . '/' . $row['doc_student_photo']; ?>" style="width:100%; height:100%; object-fit:cover;">
                                <?php else: ?>
                                    <i class="fa-solid fa-user text-muted small"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><strong class="text-primary font-monospace"><?php echo sanitize($row['admission_no']); ?></strong></td>
                        <td><span class="small font-monospace text-secondary"><?php echo sanitize($row['roll_no'] ?: '—'); ?></span></td>
                        <td class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td class="text-muted small"><?php echo sanitize($fName); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo sanitize($cName . ' (' . $sName . ')'); ?></span></td>
                        <td><span class="small text-muted"><?php echo sanitize($row['campus_name']); ?></span></td>
                        <td style="white-space:nowrap;"><strong><?php echo date('M d, Y', strtotime($row['date'])); ?></strong></td>
                        <td>
                            <span class="status-badge <?php echo $badgeClass; ?>">
                                <i class="fa-solid <?php echo $statusIcon; ?>"></i>
                                <?php echo sanitize($status); ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?php echo displayValue($row['remarks']); ?></td>
                        <td class="text-end d-print-none">
                            <div class="btn-group">
                                <a href="reports.php?view_student_id=<?php echo $row['student_id']; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" class="btn btn-outline-secondary btn-sm" title="View Student Dossier"><i class="fa-regular fa-eye"></i></a>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printRowReport(<?php echo htmlspecialchars(json_encode($row)); ?>)" title="Print Daily Voucher"><i class="fa-solid fa-print"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination (Hidden in print) -->
<?php if ($totalPages > 1): ?>
    <nav aria-label="Attendance register pagination" class="mb-4 d-print-none">
        <ul class="pagination justify-content-center">
            <?php $queryParams = $_GET; ?>
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <?php $queryParams['page'] = $page - 1; ?>
                <a class="page-link" href="?<?php echo http_build_query($queryParams); ?>">
                    <i class="fa-solid fa-chevron-left me-1"></i>Previous
                </a>
            </li>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php $queryParams['page'] = $i; ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query($queryParams); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                <?php $queryParams['page'] = $page + 1; ?>
                <a class="page-link" href="?<?php echo http_build_query($queryParams); ?>">
                    Next<i class="fa-solid fa-chevron-right ms-1"></i>
                </a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<!-- Print Report Footer (Visible in print layout ONLY) -->
<div class="d-none d-print-block mt-5 text-center border-top pt-3">
    <span class="small text-muted">Indus Grammar School ERP System © <?php echo date('Y'); ?> | Generated on <?php echo date('M d, Y h:i A'); ?></span>
</div>

<script>
// Search loading spinner
document.getElementById('searchForm').addEventListener('submit', function() {
    const btn = document.getElementById('searchBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generating...';
    }
});

// Single row student daily voucher printer
function printRowReport(row) {
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <div style="padding:40px; font-family: sans-serif; background-color: #fff;">
            <h2 style="text-align:center; margin-bottom: 2px;">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
            <h4 style="text-align:center; color: #555; border-bottom: 2px solid #333; padding-bottom: 10px; margin-top: 0;">Student Daily Attendance Voucher</h4>
            <div style="margin-top:20px; line-height: 1.8;">
                <p>Admission Number: <strong>${row.admission_no}</strong></p>
                <p>Student Name: <strong>${row.first_name} ${row.last_name}</strong></p>
                <p>Class & Section: <strong>${row.class_name ? row.class_name : (row.school_class ? row.school_class : '—')} - ${row.section ? row.section : (row.school_section ? row.school_section : '—')}</strong></p>
                <p>Attendance Date: <strong>${row.date}</strong></p>
                <p>Attendance Status: <strong>${row.status}</strong></p>
                <p>Remarks: <strong>${row.remarks ? row.remarks : '—'}</strong></p>
                <p>Logged Time: <strong>${new Date(row.created_at).toLocaleString()}</strong></p>
            </div>
            <div style="margin-top: 60px; text-align: right;">
                <span style="border-top: 1px solid #777; padding-top: 5px; font-weight: bold;">Authorized Administrator Signature</span>
            </div>
        </div>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload();
}

// Export CSV function logic
function exportCSV() {
    let csv = "Admission No,Roll No,Student Name,Father/Guardian Name,Class,Section,Campus,Date,Status,Remarks\n";
    <?php if (!empty($logs)): foreach ($logs as $row): 
        $stName = str_replace('"', '""', $row['first_name'] . ' ' . $row['last_name']);
        $fName  = str_replace('"', '""', $row['father_name'] ?: ($row['guardian_name'] ?: '—'));
        $cName  = str_replace('"', '""', $row['class_name'] ?: ($row['school_class'] ?: '—'));
        $sName  = str_replace('"', '""', $row['section'] ?: ($row['school_section'] ?: '—'));
        $cmpName = str_replace('"', '""', $row['campus_name']);
        $rem = str_replace('"', '""', $row['remarks'] ?: '');
    ?>
        csv += '"<?php echo $row['admission_no']; ?>","<?php echo $row['roll_no']; ?>","<?php echo $stName; ?>","<?php echo $fName; ?>","<?php echo $cName; ?>","<?php echo $sName; ?>","<?php echo $cmpName; ?>","<?php echo $row['date']; ?>","<?php echo $row['status']; ?>","<?php echo $rem; ?>"\n';
    <?php endforeach; endif; ?>

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const fileName = "attendance_register_" + "<?php echo $from_date; ?>_to_<?php echo $to_date; ?>" + ".csv";
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
