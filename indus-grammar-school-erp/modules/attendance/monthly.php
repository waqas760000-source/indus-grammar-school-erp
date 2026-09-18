<?php
/**
 * Indus Grammar School ERP - Monthly Attendance Report Submodule
 * Version 4.0.0 (Premium ERP Analytics & Matrix Design)
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('attendance_view');

$db = Database::getConnection();

// 2. Retrieve & Sanitize Filters
$selectedMonth        = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if ($selectedMonth < 1 || $selectedMonth > 12) $selectedMonth = (int)date('n');

$selectedYear         = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($selectedYear < 2000 || $selectedYear > 2100) $selectedYear = (int)date('Y');

$filter_session       = sanitize($_GET['session'] ?? '');
$filter_campus        = sanitize($_GET['campus'] ?? '');
$filter_academic_type = sanitize($_GET['academic_type'] ?? '');
$filter_class         = sanitize($_GET['class'] ?? '');
$filter_section       = sanitize($_GET['section'] ?? '');
$filter_status        = sanitize($_GET['student_status'] ?? 'Active');
$filter_search        = sanitize($_GET['search'] ?? '');

// Days in selected month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
$monthStart  = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
$monthEnd    = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $daysInMonth);

$monthsList = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
    7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// 3. Dynamic Select Dropdown Lists
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
    $classesList = $db->query("SELECT class_name FROM classes GROUP BY class_name ORDER BY MIN(id) ASC")->fetchAll(PDO::FETCH_COLUMN);
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

// 4. Pagination Setup
$limit = 25;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 5. Build Filter Clause for Students
$where = " WHERE 1=1";
$params = [];

if ($filter_status !== '' && $filter_status !== 'All') {
    $where .= " AND s.status = :student_status";
    $params['student_status'] = $filter_status;
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

if ($filter_search !== '') {
    $where .= " AND (s.first_name LIKE :search1 OR s.last_name LIKE :search2 OR CONCAT(s.first_name, ' ', s.last_name) LIKE :search3 OR s.admission_no LIKE :search4 OR d.roll_no LIKE :search5)";
    $params['search1'] = '%' . $filter_search . '%';
    $params['search2'] = '%' . $filter_search . '%';
    $params['search3'] = '%' . $filter_search . '%';
    $params['search4'] = '%' . $filter_search . '%';
    $params['search5'] = '%' . $filter_search . '%';
}

// Data Storage Containers
$allMatchedStudents = [];
$students = [];
$totalStudentsCount = 0;
$attendanceMap = []; // [student_id][day] => status
$dailyTrends = []; // [day] => ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Leave' => 0, 'TotalMarked' => 0]
$workingDaysSet = [];

$overallPresent = 0;
$overallAbsent = 0;
$overallLate = 0;
$overallLeave = 0;
$overallRate = 0.0;
$lowAttendanceRiskCount = 0;
$errorMessage = '';

try {
    // A. Query All Matching Students (for calculations & summaries)
    $stmtAllSt = $db->prepare("
        SELECT s.id, s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section,
               c.class_name, c.section, d.roll_no, COALESCE(d.campus, 'Main Campus') as campus_name, d.father_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY s.admission_no ASC
    ");
    $stmtAllSt->execute($params);
    $allMatchedStudents = $stmtAllSt->fetchAll(PDO::FETCH_ASSOC);
    $totalStudentsCount = count($allMatchedStudents);

    // Paginated subset for table display
    $students = array_slice($allMatchedStudents, $offset, $limit);

    if (!empty($allMatchedStudents)) {
        $allStudentIds = array_column($allMatchedStudents, 'id');
        $inQuery = implode(',', array_fill(0, count($allStudentIds), '?'));

        $sqlAtt = "
            SELECT student_id, date, status 
            FROM attendance
            WHERE date BETWEEN ? AND ? 
              AND student_id IN ($inQuery)
            ORDER BY date ASC
        ";

        $stmtAtt = $db->prepare($sqlAtt);
        $queryArgs = array_merge([$monthStart, $monthEnd], $allStudentIds);
        $stmtAtt->execute($queryArgs);
        $attRows = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);

        // Initialize daily trend array
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dailyTrends[$d] = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Leave' => 0, 'TotalMarked' => 0];
        }

        foreach ($attRows as $r) {
            $stId = (int)$r['student_id'];
            $day = (int)date('j', strtotime($r['date']));
            $status = $r['status'];

            $attendanceMap[$stId][$day] = $status;
            $workingDaysSet[$r['date']] = true;

            if (isset($dailyTrends[$day])) {
                if (isset($dailyTrends[$day][$status])) {
                    $dailyTrends[$day][$status]++;
                }
                $dailyTrends[$day]['TotalMarked']++;
            }

            if ($status === 'Present') $overallPresent++;
            elseif ($status === 'Absent') $overallAbsent++;
            elseif ($status === 'Late') $overallLate++;
            elseif ($status === 'Leave') $overallLeave++;
        }

        // Calculate Low Attendance Risk Count (< 75% attendance)
        foreach ($allMatchedStudents as $st) {
            $stId = $st['id'];
            $p = 0; $lt = 0; $marked = 0;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $stStatus = $attendanceMap[$stId][$d] ?? '';
                if ($stStatus !== '') {
                    $marked++;
                    if ($stStatus === 'Present') $p++;
                    elseif ($stStatus === 'Late') $lt++;
                }
            }
            if ($marked > 0) {
                $stRate = (($p + $lt) / $marked) * 100;
                if ($stRate < 75.0) {
                    $lowAttendanceRiskCount++;
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Monthly attendance report query error: " . $e->getMessage());
    $errorMessage = "Unable to load monthly attendance records. Please try again.";
}

$workingDaysCount = count($workingDaysSet);
$totalRecordsMarked = $overallPresent + $overallAbsent + $overallLate + $overallLeave;

if ($totalRecordsMarked > 0) {
    $overallRate = round((($overallPresent + $overallLate) / $totalRecordsMarked) * 100, 1);
    $presentPct  = round(($overallPresent / $totalRecordsMarked) * 100, 1);
    $absentPct   = round(($overallAbsent / $totalRecordsMarked) * 100, 1);
    $latePct     = round(($overallLate / $totalRecordsMarked) * 100, 1);
    $leavePct    = round(($overallLeave / $totalRecordsMarked) * 100, 1);
} else {
    $presentPct = 0.0; $absentPct = 0.0; $latePct = 0.0; $leavePct = 0.0;
}

$totalPages = ceil($totalStudentsCount / $limit);
if ($totalPages < 1) $totalPages = 1;

// 6. Layout Header & Extra CSS System
$pageTitle = 'Monthly Attendance Report';
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

.report-header-card {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
}

.report-title-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background-color: var(--light-blue);
    color: var(--primary-blue);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
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
.kpi-working::before { background-color: var(--secondary-blue); }
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
.kpi-working .kpi-icon-wrapper { background-color: var(--light-blue); color: var(--secondary-blue); }
.kpi-present .kpi-icon-wrapper { background-color: var(--present-bg); color: var(--present-color); }
.kpi-absent .kpi-icon-wrapper { background-color: var(--absent-bg); color: var(--absent-color); }
.kpi-late .kpi-icon-wrapper { background-color: var(--late-bg); color: var(--late-color); }
.kpi-leave .kpi-icon-wrapper { background-color: var(--leave-bg); color: var(--leave-color); }
.kpi-rate .kpi-icon-wrapper { background-color: var(--light-blue); color: var(--primary-blue); }

.matrix-table {
    border-collapse: separate;
    border-spacing: 0;
}

.matrix-table th, .matrix-table td {
    padding: 0.65rem 0.5rem;
    font-size: 0.8rem;
    text-align: center;
    border-bottom: 1px solid var(--border-color);
    border-right: 1px solid var(--border-color);
    vertical-align: middle;
}

.matrix-table th {
    background-color: #F8FAFC;
    color: var(--muted-text);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.73rem;
    letter-spacing: 0.03em;
}

.sticky-col {
    position: sticky;
    left: 0;
    background-color: #FFFFFF !important;
    z-index: 10;
    text-align: left !important;
    border-right: 2px solid var(--border-color) !important;
    min-width: 200px;
    max-width: 220px;
}

.sticky-header {
    position: sticky;
    left: 0;
    background-color: #F8FAFC !important;
    z-index: 11;
    text-align: left !important;
    border-right: 2px solid var(--border-color) !important;
}

.badge-att {
    display: inline-block;
    width: 24px;
    height: 24px;
    line-height: 24px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.72rem;
    text-align: center;
}

.badge-att-p { background-color: var(--present-bg); color: var(--present-color); border: 1px solid rgba(22, 163, 74, 0.3); }
.badge-att-a { background-color: var(--absent-bg); color: var(--absent-color); border: 1px solid rgba(220, 38, 38, 0.3); }
.badge-att-l { background-color: var(--leave-bg); color: var(--leave-color); border: 1px solid rgba(124, 58, 237, 0.3); }
.badge-att-lt { background-color: var(--late-bg); color: var(--late-color); border: 1px solid rgba(217, 119, 6, 0.3); }
.badge-att-off { background-color: var(--unmarked-bg); color: #94A3B8; border: 1px dashed #CBD5E1; }

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
    .matrix-table th {
        background-color: #F1F5F9 !important;
        color: #0F172A !important;
        border: 1px solid #CBD5E1 !important;
    }
    .matrix-table td {
        border: 1px solid #E2E8F0 !important;
        color: #000000 !important;
    }
    .sticky-col {
        position: static !important;
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
        <li class="breadcrumb-item active" aria-current="page">Monthly Attendance Report</li>
    </ol>
</nav>

<!-- Hero Blue Gradient Header Banner (Matching Student Registration) -->
<div class="adv-hero-banner mb-4 d-print-none">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
        <div class="d-flex align-items-center gap-3">
            <div class="hero-icon-box">
                <i class="fa-solid fa-calendar-days"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h3 class="fw-bold mb-0 text-white fs-3">Monthly Attendance Report</h3>
                    <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">Attendance Analytics</span>
                </div>
                <p class="text-white-50 small mb-0 fs-6">Review monthly student attendance, daily trends, attendance percentages, and student-wise performance.</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-sm btn-light text-primary rounded-2 px-3 py-2 fw-semibold shadow-sm" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print Report</button>
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
            <h6 class="fw-bold mb-0" style="color: var(--primary-navy);"><i class="fa-solid fa-sliders me-2 text-primary"></i>Monthly Attendance Report Filters</h6>
            <span class="text-muted small">Select the month, year, class, and campus to analyze attendance records.</span>
        </div>
    </div>

    <form method="GET" action="monthly.php" id="filterForm">
        <div class="row g-3">
            <!-- Month -->
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-muted">Month <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="month" required>
                    <?php foreach ($monthsList as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php echo ($selectedMonth === $num) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Year -->
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-muted">Year <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="year" required>
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($selectedYear === $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
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
                <select class="form-select form-select-sm" name="academic_type">
                    <option value="">All Types</option>
                    <option value="School" <?php echo ($filter_academic_type === 'School') ? 'selected' : ''; ?>>School</option>
                    <option value="Academy" <?php echo ($filter_academic_type === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                </select>
            </div>

            <!-- Class -->
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-muted">Class</label>
                <select class="form-select form-select-sm" name="class">
                    <option value="">All Classes</option>
                    <?php foreach ($classesList as $cls): ?>
                        <option value="<?php echo htmlspecialchars($cls); ?>" <?php echo ($filter_class === $cls) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cls); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Section -->
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-muted">Section</label>
                <select class="form-select form-select-sm" name="section">
                    <option value="">All Sections</option>
                    <?php foreach ($sectionsList as $sec): ?>
                        <option value="<?php echo htmlspecialchars($sec); ?>" <?php echo ($filter_section === $sec) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sec); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Student Status -->
            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label class="form-label small fw-semibold text-muted">Student Status</label>
                <select class="form-select form-select-sm" name="student_status">
                    <option value="Active" <?php echo ($filter_status === 'Active') ? 'selected' : ''; ?>>Active Only</option>
                    <option value="Inactive" <?php echo ($filter_status === 'Inactive') ? 'selected' : ''; ?>>Inactive Only</option>
                    <option value="All" <?php echo ($filter_status === 'All') ? 'selected' : ''; ?>>All Students</option>
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
                <a href="monthly.php" class="btn btn-erp-outline btn-sm">Reset</a>
                <button type="submit" id="genBtn" class="btn btn-erp-primary btn-sm px-4">
                    <i class="fa-solid fa-arrows-spin me-1"></i>Generate Report
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
            <span class="fw-bold d-block" style="font-size:0.95rem;">Monthly Attendance Overview</span>
            <span class="small opacity-75">
                <?php echo $monthsList[$selectedMonth] . ' ' . $selectedYear; ?>
                | <?php echo $filter_campus ? htmlspecialchars($filter_campus) : 'All Campuses'; ?>
                | <?php echo $filter_academic_type ? htmlspecialchars($filter_academic_type) : 'All Academic Types'; ?>
                | <?php echo $filter_class ? htmlspecialchars($filter_class . ($filter_section ? ' - '.$filter_section : '')) : 'All Classes'; ?>
            </span>
        </div>
    </div>
    <div>
        <span class="badge bg-white text-dark px-3 py-2 fw-semibold fs-6">
            <i class="fa-solid fa-users me-1 text-primary"></i><?php echo number_format($totalStudentsCount); ?> Enrolled Students
        </span>
    </div>
</div>

<!-- 7 KPI Summary Cards Section -->
<div class="row g-3 mb-4">
    <!-- Card 1: Total Students -->
    <div class="col-12 col-sm-6 col-md-3 col-lg-1-7" style="flex: 1 0 0%;">
        <div class="kpi-card kpi-total">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Total Students</span>
                <div class="kpi-icon-wrapper">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <div>
                <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($totalStudentsCount); ?></h3>
                <span class="text-muted small" style="font-size:0.75rem;">Enrolled</span>
            </div>
        </div>
    </div>

    <!-- Card 2: Total Working Days -->
    <div class="col-12 col-sm-6 col-md-3 col-lg-1-7" style="flex: 1 0 0%;">
        <div class="kpi-card kpi-working">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Working Days</span>
                <div class="kpi-icon-wrapper">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
            </div>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--secondary-blue);"><?php echo number_format($workingDaysCount); ?></h3>
                <span class="text-muted small" style="font-size:0.75rem;">Marked Days</span>
            </div>
        </div>
    </div>

    <!-- Card 3: Present Records -->
    <div class="col-12 col-sm-6 col-md-3 col-lg-1-7" style="flex: 1 0 0%;">
        <div class="kpi-card kpi-present">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Total Present</span>
                <div class="kpi-icon-wrapper">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--present-color);"><?php echo number_format($overallPresent); ?></h3>
                <span class="text-muted small" style="font-size:0.75rem;"><?php echo $presentPct; ?>% of total</span>
            </div>
        </div>
    </div>

    <!-- Card 4: Absent Records -->
    <div class="col-12 col-sm-6 col-md-3 col-lg-1-7" style="flex: 1 0 0%;">
        <div class="kpi-card kpi-absent">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Total Absent</span>
                <div class="kpi-icon-wrapper">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
            </div>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--absent-color);"><?php echo number_format($overallAbsent); ?></h3>
                <span class="text-muted small" style="font-size:0.75rem;"><?php echo $absentPct; ?>% of total</span>
            </div>
        </div>
    </div>

    <!-- Card 5: Late Records -->
    <div class="col-12 col-sm-6 col-md-3 col-lg-1-7" style="flex: 1 0 0%;">
        <div class="kpi-card kpi-late">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Total Late</span>
                <div class="kpi-icon-wrapper">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--late-color);"><?php echo number_format($overallLate); ?></h3>
                <span class="text-muted small" style="font-size:0.75rem;"><?php echo $latePct; ?>% of total</span>
            </div>
        </div>
    </div>

    <!-- Card 6: Leave Records -->
    <div class="col-12 col-sm-6 col-md-3 col-lg-1-7" style="flex: 1 0 0%;">
        <div class="kpi-card kpi-leave">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Total Leave</span>
                <div class="kpi-icon-wrapper">
                    <i class="fa-solid fa-calendar-minus"></i>
                </div>
            </div>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--leave-color);"><?php echo number_format($overallLeave); ?></h3>
                <span class="text-muted small" style="font-size:0.75rem;"><?php echo $leavePct; ?>% of total</span>
            </div>
        </div>
    </div>

    <!-- Card 7: Overall Rate -->
    <div class="col-12 col-sm-6 col-md-3 col-lg-1-7" style="flex: 1 0 0%;">
        <div class="kpi-card kpi-rate">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold">Overall Rate</span>
                <div class="kpi-icon-wrapper">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--primary-blue);"><?php echo $overallRate; ?>%</h3>
                <span class="text-muted small" style="font-size:0.75rem;">Month Average</span>
            </div>
        </div>
    </div>
</div>

<!-- Performance Distribution & Low Attendance Risk Overview Section -->
<div class="row g-3 mb-4 d-print-none">
    <div class="col-12 col-lg-8">
        <div class="erp-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                <div>
                    <h6 class="fw-bold mb-0" style="color: var(--primary-navy);"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Attendance Performance Distribution</h6>
                    <span class="text-muted small">Status breakdown across all attendance logs for <?php echo $monthsList[$selectedMonth] . ' ' . $selectedYear; ?>.</span>
                </div>
                <span class="badge bg-primary-subtle text-primary px-3 py-2 fw-semibold fs-6">
                    <?php echo number_format($totalRecordsMarked); ?> Total Entries
                </span>
            </div>

            <?php if ($totalRecordsMarked > 0): ?>
                <div class="progress-bar-stack mb-3" style="height: 14px;">
                    <div class="progress-bar" role="progressbar" style="width: <?php echo $presentPct; ?>%; background-color: var(--present-color);" title="Present: <?php echo $presentPct; ?>%"></div>
                    <div class="progress-bar" role="progressbar" style="width: <?php echo $latePct; ?>%; background-color: var(--late-color);" title="Late: <?php echo $latePct; ?>%"></div>
                    <div class="progress-bar" role="progressbar" style="width: <?php echo $leavePct; ?>%; background-color: var(--leave-color);" title="Leave: <?php echo $leavePct; ?>%"></div>
                    <div class="progress-bar" role="progressbar" style="width: <?php echo $absentPct; ?>%; background-color: var(--absent-color);" title="Absent: <?php echo $absentPct; ?>%"></div>
                </div>

                <div class="row g-2 text-center small">
                    <div class="col-6 col-sm-3">
                        <div class="p-2 rounded border" style="background-color: var(--present-bg);">
                            <span class="d-block text-muted small">Present</span>
                            <span class="fw-bold" style="color: var(--present-color);"><?php echo number_format($overallPresent); ?> (<?php echo $presentPct; ?>%)</span>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="p-2 rounded border" style="background-color: var(--late-bg);">
                            <span class="d-block text-muted small">Late</span>
                            <span class="fw-bold" style="color: var(--late-color);"><?php echo number_format($overallLate); ?> (<?php echo $latePct; ?>%)</span>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="p-2 rounded border" style="background-color: var(--leave-bg);">
                            <span class="d-block text-muted small">Leave</span>
                            <span class="fw-bold" style="color: var(--leave-color);"><?php echo number_format($overallLeave); ?> (<?php echo $leavePct; ?>%)</span>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="p-2 rounded border" style="background-color: var(--absent-bg);">
                            <span class="d-block text-muted small">Absent</span>
                            <span class="fw-bold" style="color: var(--absent-color);"><?php echo number_format($overallAbsent); ?> (<?php echo $absentPct; ?>%)</span>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-muted small py-3 text-center">No attendance logs available for the selected period.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Low Attendance Risk Card -->
    <div class="col-12 col-lg-4">
        <div class="erp-card p-4 h-100 d-flex flex-column justify-content-between" style="border-left: 4px solid var(--absent-color) !important;">
            <div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-bold mb-0 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Attendance Risk Alert</h6>
                    <span class="badge bg-danger text-white rounded-circle p-2" style="width:28px; height:28px; line-height:12px; font-size:0.75rem;"><?php echo $lowAttendanceRiskCount; ?></span>
                </div>
                <p class="text-muted small mb-3">Students with attendance rate falling below the 75% school compliance threshold this month.</p>
            </div>
            <div>
                <h2 class="fw-bold text-danger mb-0"><?php echo number_format($lowAttendanceRiskCount); ?></h2>
                <span class="text-muted small">Students need attention & counselor follow-up</span>
            </div>
        </div>
    </div>
</div>

<!-- Daily Attendance Trends Accordion (Hidden in Print) -->
<?php if (!empty($workingDaysSet)): ?>
    <div class="accordion mb-4 d-print-none" id="trendsAccordion">
        <div class="accordion-item erp-card border-0 shadow-sm overflow-hidden">
            <h2 class="accordion-header" id="headingTrends">
                <button class="accordion-button collapsed bg-white fw-bold" style="color: var(--primary-navy);" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTrends" aria-expanded="false" aria-controls="collapseTrends">
                    <i class="fa-solid fa-chart-line me-2 text-primary"></i>Daily Attendance Trends Breakdown (<?php echo $workingDaysCount; ?> Marked Days)
                </button>
            </h2>
            <div id="collapseTrends" class="accordion-collapse collapse" aria-labelledby="headingTrends" data-bs-parent="#trendsAccordion">
                <div class="accordion-body p-0">
                    <div class="table-responsive">
                        <table class="table table-erp table-hover align-middle text-center mb-0 small">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Day</th>
                                    <th>Present</th>
                                    <th>Absent</th>
                                    <th>Late</th>
                                    <th>Leave</th>
                                    <th>Total Marked</th>
                                    <th>Daily Attendance Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                for ($d = 1; $d <= $daysInMonth; $d++):
                                    $dTrend = $dailyTrends[$d];
                                    if ($dTrend['TotalMarked'] == 0) continue;

                                    $dDateStr = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $d);
                                    $dayName = date('D', strtotime($dDateStr));
                                    $dRate = round((($dTrend['Present'] + $dTrend['Late']) / $dTrend['TotalMarked']) * 100, 1);
                                ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($dDateStr)); ?></td>
                                        <td><span class="badge bg-light text-secondary border"><?php echo $dayName; ?></span></td>
                                        <td class="fw-bold" style="color: var(--present-color);"><?php echo $dTrend['Present']; ?></td>
                                        <td class="fw-bold" style="color: var(--absent-color);"><?php echo $dTrend['Absent']; ?></td>
                                        <td class="fw-bold" style="color: var(--late-color);"><?php echo $dTrend['Late']; ?></td>
                                        <td class="fw-bold" style="color: var(--leave-color);"><?php echo $dTrend['Leave']; ?></td>
                                        <td class="fw-bold text-dark"><?php echo $dTrend['TotalMarked']; ?></td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary px-3 py-1 fw-bold"><?php echo $dRate; ?>%</span>
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Printable Header (Visible ONLY in Print Mode) -->
<div class="d-none d-print-block print-header">
    <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
    <h5 class="text-secondary mb-2">Monthly Student Attendance Matrix Report</h5>
    <div class="small text-muted border-top border-bottom py-2 my-2">
        Month: <strong><?php echo $monthsList[$selectedMonth] . ' ' . $selectedYear; ?></strong>
        <?php if ($filter_campus): ?> | Campus: <strong><?php echo htmlspecialchars($filter_campus); ?></strong><?php endif; ?>
        <?php if ($filter_class): ?> | Class: <strong><?php echo htmlspecialchars($filter_class); ?></strong><?php endif; ?>
        <?php if ($filter_section): ?> | Section: <strong><?php echo htmlspecialchars($filter_section); ?></strong><?php endif; ?>
        <?php if ($filter_academic_type): ?> | Type: <strong><?php echo htmlspecialchars($filter_academic_type); ?></strong><?php endif; ?>
        <br>
        Enrolled Students: <strong><?php echo $totalStudentsCount; ?></strong> | Working Days: <strong><?php echo $workingDaysCount; ?></strong> | Present: <strong><?php echo $overallPresent; ?></strong> | Absent: <strong><?php echo $overallAbsent; ?></strong> | Overall Rate: <strong><?php echo $overallRate; ?>%</strong>
    </div>
</div>

<!-- Legend Indicators (Hidden in print) -->
<?php if (!empty($students)): ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 d-print-none">
        <div class="d-flex flex-wrap gap-2">
            <span class="badge-att badge-att-p" style="width:auto; padding: 2px 10px;">P = Present</span>
            <span class="badge-att badge-att-a" style="width:auto; padding: 2px 10px;">A = Absent</span>
            <span class="badge-att badge-att-lt" style="width:auto; padding: 2px 10px;">LT = Late</span>
            <span class="badge-att badge-att-l" style="width:auto; padding: 2px 10px;">L = Leave</span>
            <span class="badge-att badge-att-off" style="width:auto; padding: 2px 10px;">- = Unmarked</span>
        </div>
        <div class="small text-muted">
            Showing <?php echo count($students); ?> of <?php echo $totalStudentsCount; ?> Students
        </div>
    </div>
<?php endif; ?>

<!-- Student-Wise Monthly Matrix Table Card -->
<div class="erp-card mb-4 overflow-hidden">
    <div class="table-responsive">
        <table class="matrix-table w-100">
            <thead>
                <tr>
                    <th class="sticky-header">Student Name & Info</th>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                        <th style="min-width: 28px;"><?php echo $d; ?></th>
                    <?php endfor; ?>
                    <th style="background-color: var(--present-bg); color: var(--present-color); min-width: 36px;" title="Present Days">P</th>
                    <th style="background-color: var(--absent-bg); color: var(--absent-color); min-width: 36px;" title="Absent Days">A</th>
                    <th style="background-color: var(--late-bg); color: var(--late-color); min-width: 36px;" title="Late Days">LT</th>
                    <th style="background-color: var(--leave-bg); color: var(--leave-color); min-width: 36px;" title="Leave Days">L</th>
                    <th style="background-color: var(--light-blue); color: var(--primary-blue); min-width: 60px;">Rate %</th>
                    <th style="min-width: 100px;">Health Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="<?php echo $daysInMonth + 7; ?>" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-clipboard-question fs-2 mb-2 opacity-50 d-block text-primary"></i>
                            <h5 class="fw-bold text-dark mb-1">No Student Attendance Records Found</h5>
                            <p class="small text-muted mb-3">No students match the selected date, month, class, or search criteria.</p>
                            <a href="monthly.php" class="btn btn-erp-outline btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reset Filters</a>
                        </td>
                    </tr>
                <?php else: foreach ($students as $st): 
                    $pDays = 0; $aDays = 0; $lDays = 0; $ltDays = 0; $totalMarked = 0;
                    $stId = $st['id'];
                ?>
                    <tr>
                        <!-- Sticky Student Details -->
                        <td class="sticky-col">
                            <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></span>
                            <span class="small text-muted font-monospace" style="font-size:0.7rem;">
                                ID: <?php echo htmlspecialchars($st['admission_no']); ?>
                                <?php if (!empty($st['roll_no'])): ?> | Roll: <?php echo htmlspecialchars($st['roll_no']); ?><?php endif; ?>
                            </span>
                            <span class="d-block small text-muted" style="font-size:0.68rem;">
                                <?php echo htmlspecialchars(($st['class_name'] ?: $st['school_class']) . ' (' . ($st['section'] ?: $st['school_section']) . ')'); ?>
                            </span>
                        </td>

                        <!-- Monthly Days Loop -->
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                            $status = $attendanceMap[$stId][$d] ?? '';
                            $badgeClass = 'badge-att-off';
                            $symbol = '-';

                            if ($status === 'Present') {
                                $badgeClass = 'badge-att-p'; $symbol = 'P'; $pDays++; $totalMarked++;
                            } elseif ($status === 'Absent') {
                                $badgeClass = 'badge-att-a'; $symbol = 'A'; $aDays++; $totalMarked++;
                            } elseif ($status === 'Late') {
                                $badgeClass = 'badge-att-lt'; $symbol = 'LT'; $ltDays++; $totalMarked++;
                            } elseif ($status === 'Leave') {
                                $badgeClass = 'badge-att-l'; $symbol = 'L'; $lDays++; $totalMarked++;
                            }
                        ?>
                            <td>
                                <span class="badge-att <?php echo $badgeClass; ?>"><?php echo $symbol; ?></span>
                            </td>
                        <?php endfor; ?>

                        <!-- Summary Totals -->
                        <?php 
                        $rate = 0.0;
                        if ($totalMarked > 0) {
                            $rate = round((($pDays + $ltDays) / $totalMarked) * 100, 1);
                        }

                        $healthBadge = 'bg-success-subtle text-success border border-success-subtle';
                        $healthText  = 'Good';
                        if ($rate < 50.0 && $totalMarked > 0) {
                            $healthBadge = 'bg-danger-subtle text-danger border border-danger-subtle';
                            $healthText  = 'Low';
                        } elseif ($rate < 75.0 && $totalMarked > 0) {
                            $healthBadge = 'bg-warning-subtle text-warning border border-warning-subtle';
                            $healthText  = 'Needs Attention';
                        } elseif ($totalMarked == 0) {
                            $healthBadge = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                            $healthText  = 'Unmarked';
                        }
                        ?>
                        <td class="fw-bold" style="color: var(--present-color); background-color: var(--present-bg);"><?php echo $pDays; ?></td>
                        <td class="fw-bold" style="color: var(--absent-color); background-color: var(--absent-bg);"><?php echo $aDays; ?></td>
                        <td class="fw-bold" style="color: var(--late-color); background-color: var(--late-bg);"><?php echo $ltDays; ?></td>
                        <td class="fw-bold" style="color: var(--leave-color); background-color: var(--leave-bg);"><?php echo $lDays; ?></td>
                        <td class="fw-bold text-primary" style="background-color: var(--light-blue);"><?php echo $rate; ?>%</td>
                        <td>
                            <span class="badge <?php echo $healthBadge; ?> px-2 py-1 fw-semibold" style="font-size:0.7rem;"><?php echo $healthText; ?></span>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination Layout (Hidden in Print) -->
<?php if ($totalPages > 1): ?>
    <nav aria-label="Monthly attendance pagination" class="mb-4 d-print-none">
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

<!-- Printable Footer (Visible ONLY in Print Mode) -->
<div class="d-none d-print-block text-center mt-5 border-top pt-3">
    <span class="small text-muted">Indus Grammar School ERP System © <?php echo date('Y'); ?> | Generated on <?php echo date('M d, Y h:i A'); ?></span>
</div>

<script>
// Filter form submit spinner
document.getElementById('filterForm').addEventListener('submit', function() {
    const btn = document.getElementById('genBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generating...';
    }
});

// Export CSV function logic
function exportCSV() {
    let csv = "Student Name,Student ID,Roll No,Class,Section,";
    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
        csv += "<?php echo $d; ?>,";
    <?php endfor; ?>
    csv += "P,A,LT,L,Rate %,Health Status\n";

    <?php if (!empty($allMatchedStudents)): foreach ($allMatchedStudents as $st): 
        $stId = $st['id'];
        $pDays = 0; $aDays = 0; $lDays = 0; $ltDays = 0; $totalMarked = 0;
        $stName = str_replace('"', '""', $st['first_name'] . ' ' . $st['last_name']);
        $cName  = str_replace('"', '""', $st['class_name'] ?: ($st['school_class'] ?: '—'));
        $sName  = str_replace('"', '""', $st['section'] ?: ($st['school_section'] ?: '—'));
    ?>
        csv += '"<?php echo $stName; ?>","<?php echo $st['admission_no']; ?>","<?php echo $st['roll_no']; ?>","<?php echo $cName; ?>","<?php echo $sName; ?>",';
        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
            $status = $attendanceMap[$stId][$d] ?? '';
            $symbol = '-';
            if ($status === 'Present') { $symbol = 'P'; $pDays++; $totalMarked++; }
            elseif ($status === 'Absent') { $symbol = 'A'; $aDays++; $totalMarked++; }
            elseif ($status === 'Late') { $symbol = 'LT'; $ltDays++; $totalMarked++; }
            elseif ($status === 'Leave') { $symbol = 'L'; $lDays++; $totalMarked++; }
        ?>
            csv += '"<?php echo $symbol; ?>",';
        <?php endfor; 
        $rate = 0.0;
        if ($totalMarked > 0) $rate = round((($pDays + $ltDays) / $totalMarked) * 100, 1);
        $healthText = 'Good';
        if ($rate < 50.0 && $totalMarked > 0) $healthText = 'Low';
        elseif ($rate < 75.0 && $totalMarked > 0) $healthText = 'Needs Attention';
        elseif ($totalMarked == 0) $healthText = 'Unmarked';
        ?>
        csv += '"<?php echo $pDays; ?>","<?php echo $aDays; ?>","<?php echo $ltDays; ?>","<?php echo $lDays; ?>","<?php echo $rate; ?>%","<?php echo $healthText; ?>"\n';
    <?php endforeach; endif; ?>

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const fileName = "monthly_attendance_report_" + "<?php echo sprintf('%04d_%02d', $selectedYear, $selectedMonth); ?>" + ".csv";
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
