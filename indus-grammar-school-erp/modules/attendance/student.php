<?php
/**
 * Indus Grammar School ERP - Student Attendance Marking Submodule
 * Version 5.0.0 - Commercial ERP Redesign & Complete Attendance Center
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('attendance_mark');

$db = Database::getConnection();
$message = '';
$error = '';

// Load flash messages
if (!empty($_SESSION['flash_success'])) {
    $message = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Retrieve filter selections
$selectedDate = sanitize($_GET['date'] ?? date('Y-m-d'));
$academic_type = sanitize($_GET['academic_type'] ?? 'School');
$selected_session = sanitize($_GET['academic_session'] ?? '');
$selected_campus = sanitize($_GET['campus'] ?? '');
$class_name = sanitize($_GET['class'] ?? '');
$section_name = sanitize($_GET['section'] ?? '');

// Fetch unique class names and sections dynamically from classes table
$classesList = [];
$sectionsList = [];
try {
    $classesList = $db->query("SELECT class_name FROM classes GROUP BY class_name ORDER BY MIN(id) ASC")->fetchAll(PDO::FETCH_COLUMN);
    $sectionsList = $db->query("SELECT DISTINCT section FROM classes ORDER BY section ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error fetching classes/sections in student.php: " . $e->getMessage());
}

if (empty($classesList)) {
    $classesList = ['Playgroup', 'Nursery', 'Prep', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10'];
}
if (empty($sectionsList)) {
    $sectionsList = ['A', 'B', 'C', 'D'];
}

// Fetch Academic Sessions
$academicSessions = [];
try {
    $academicSessions = $db->query("SELECT session_name FROM academic_sessions ORDER BY id DESC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $academicSessions = ['2026-2027', '2025-2026'];
}
if (empty($selected_session) && !empty($academicSessions)) {
    $selected_session = $academicSessions[0];
}

// Fixed Campus Options
$campusOptions = ['Main Campus', 'City Campus', 'Boys Campus'];

$students = [];
$alreadyMarked = false;
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 2. Handle POST Request to Save/Update Attendance
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    try {
        $postDate = sanitize($_POST['date'] ?? date('Y-m-d'));
        $postType = sanitize($_POST['academic_type'] ?? 'School');
        $postSession = sanitize($_POST['academic_session'] ?? '');
        $postCampus = sanitize($_POST['campus'] ?? '');
        $postClass = sanitize($_POST['class'] ?? '');
        $postSection = sanitize($_POST['section'] ?? '');
        $records = $_POST['records'] ?? [];
        $markedBy = $_SESSION['user_id'] ?? null;

        if (empty($postClass) || empty($postSection)) {
            throw new Exception("Class and Section are required to save attendance.");
        }

        // Look up class_id from classes database
        $stmtCls = $db->prepare("SELECT id FROM classes WHERE class_name = ? AND section = ?");
        $stmtCls->execute([$postClass, $postSection]);
        $class_id = $stmtCls->fetchColumn();
        if (!$class_id) {
            $stmtInsertCls = $db->prepare("INSERT INTO classes (class_name, section) VALUES (?, ?)");
            $stmtInsertCls->execute([$postClass, $postSection]);
            $class_id = (int)$db->lastInsertId();
        }

        if (empty($records)) {
            throw new Exception("No student attendance records detected.");
        }

        $db->beginTransaction();

        $successCount = 0;
        foreach ($records as $r) {
            $studentId = (int)($r['student_id'] ?? 0);
            $status = sanitize($r['status'] ?? 'Present');
            $remarks = sanitize($r['remarks'] ?? '');

            if ($studentId <= 0) continue;

            // Save / Update Attendance using prepared statement
            $stmtMark = $db->prepare("
                INSERT INTO attendance (student_id, class_id, date, status, remarks, marked_by)
                VALUES (:student_id, :class_id, :date, :status, :remarks, :marked_by)
                ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), marked_by = VALUES(marked_by)
            ");
            $stmtMark->execute([
                'student_id' => $studentId,
                'class_id' => $class_id,
                'date' => $postDate,
                'status' => $status,
                'remarks' => $remarks ?: null,
                'marked_by' => $markedBy
            ]);
            $successCount++;
        }

        // Audit Trail Log
        $logDesc = "Marked Attendance for Class $postClass ($postSection) on $postDate ($successCount students)";
        $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmtLog->execute([$markedBy, 'Attendance Saved', $logDesc, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

        $db->commit();
        $_SESSION['flash_success'] = "Attendance successfully recorded for $successCount students on " . date('d-M-Y', strtotime($postDate)) . ".";
        
        $redirectUrl = "student.php?date=$postDate&academic_type=$postType&academic_session=" . urlencode($postSession) . "&campus=" . urlencode($postCampus) . "&class=" . urlencode($postClass) . "&section=" . urlencode($postSection);
        header("Location: $redirectUrl");
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION['flash_error'] = $e->getMessage();
        $redirectUrl = "student.php?date=$selectedDate&academic_type=$academic_type&academic_session=" . urlencode($selected_session) . "&campus=" . urlencode($selected_campus) . "&class=" . urlencode($class_name) . "&section=" . urlencode($section_name);
        header("Location: $redirectUrl");
        exit;
    }
}

// 3. Load students list if Class and Section filters are set
if (!empty($class_name) && !empty($section_name)) {
    try {
        $params = [
            'date' => $selectedDate,
            'academic_type' => $academic_type,
            'class1' => $class_name,
            'class2' => $class_name,
            'section1' => $section_name,
            'section2' => $section_name
        ];

        $whereConds = [
            "s.status = 'Active'",
            "s.academic_type = :academic_type",
            "(c.class_name = :class1 OR s.school_class = :class2)",
            "(c.section = :section1 OR s.school_section = :section2)"
        ];

        if (!empty($selected_campus)) {
            $whereConds[] = "(d.campus = :campus OR d.campus IS NULL OR d.campus = '')";
            $params['campus'] = $selected_campus;
        }

        if (!empty($selected_session)) {
            $whereConds[] = "(d.academic_session = :session OR d.academic_session IS NULL OR d.academic_session = '')";
            $params['session'] = $selected_session;
        }

        $whereSql = implode(" AND ", $whereConds);

        $qSql = "
            SELECT s.id, s.admission_no, s.first_name, s.last_name, s.status as student_status,
                   d.roll_no, d.father_name, d.doc_student_photo, d.campus, d.academic_session,
                   a.status as current_status, a.remarks as current_remarks
            FROM students s
            LEFT JOIN student_registration_details d ON s.id = d.student_id
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN attendance a ON a.student_id = s.id AND a.date = :date
            WHERE {$whereSql}
            ORDER BY CAST(d.roll_no AS UNSIGNED) ASC, s.admission_no ASC
        ";
        
        $stmt = $db->prepare($qSql);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if attendance is already recorded for this date & class
        foreach ($students as $st) {
            if ($st['current_status'] !== null) {
                $alreadyMarked = true;
                break;
            }
        }
    } catch (Exception $e) {
        $error = "Error loading Student Register: " . $e->getMessage();
    }
}

// Calculate Summary Counts for loaded student list
$totalStudents = count($students);
$presentCount = 0;
$absentCount = 0;
$leaveCount = 0;
$lateCount = 0;
$unmarkedCount = 0;

foreach ($students as $st) {
    $cur = $st['current_status'] ?? 'Present';
    if ($st['current_status'] === null) {
        $unmarkedCount++;
    }
    if ($cur === 'Present') $presentCount++;
    elseif ($cur === 'Absent') $absentCount++;
    elseif ($cur === 'Leave') $leaveCount++;
    elseif ($cur === 'Late') $lateCount++;
}

// Layout Header
$pageTitle = 'Mark Student Attendance';
$breadcrumbActive = 'Mark Attendance';
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

/* Hero Gradient Header Banner - Exact Match to Student Registration */
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

/* Card Styling */
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
    width: 42px;
    height: 42px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.88rem;
    background: #e2e8f0;
    color: #334155;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

/* Segmented Control Status Pills */
.status-pill-group {
    display: inline-flex;
    gap: 4px;
    background: #f8fafc;
    padding: 4px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}

.status-pill-item {
    position: relative;
    margin: 0;
}

.status-pill-item input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.status-pill-label {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 13px;
    font-size: 0.8125rem;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    color: #475569;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    user-select: none;
    transition: all 0.15s ease-in-out;
}

.status-pill-item input[type="radio"]:focus + .status-pill-label {
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25);
}

/* Selected Status States */
.status-pill-item input[value="Present"]:checked + .status-pill-label {
    background-color: #f0fdf4;
    color: #166534;
    border-color: #86efac;
    box-shadow: 0 2px 4px rgba(22, 163, 74, 0.12);
}

.status-pill-item input[value="Absent"]:checked + .status-pill-label {
    background-color: #fef2f2;
    color: #991b1b;
    border-color: #fca5a5;
    box-shadow: 0 2px 4px rgba(220, 38, 38, 0.12);
}

.status-pill-item input[value="Late"]:checked + .status-pill-label {
    background-color: #fffbeb;
    color: #92400e;
    border-color: #fcd34d;
    box-shadow: 0 2px 4px rgba(217, 119, 6, 0.12);
}

.status-pill-item input[value="Leave"]:checked + .status-pill-label {
    background-color: #f5f3ff;
    color: #5b21b6;
    border-color: #c4b5fd;
    box-shadow: 0 2px 4px rgba(124, 58, 237, 0.12);
}

/* Batch Pill Buttons */
.btn-batch-present { color: #166534; border-color: #86efac; background-color: #f0fdf4; }
.btn-batch-present:hover { background-color: #16a34a; color: #fff; }

.btn-batch-absent { color: #991b1b; border-color: #fca5a5; background-color: #fef2f2; }
.btn-batch-absent:hover { background-color: #dc2626; color: #fff; }

.btn-batch-late { color: #92400e; border-color: #fcd34d; background-color: #fffbeb; }
.btn-batch-late:hover { background-color: #d97706; color: #fff; }

.btn-batch-leave { color: #5b21b6; border-color: #c4b5fd; background-color: #f5f3ff; }
.btn-batch-leave:hover { background-color: #7c3aed; color: #fff; }

/* Sticky Action Bar */
.action-sticky-bar {
    position: sticky;
    bottom: 20px;
    z-index: 100;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid #cbd5e1;
    border-radius: 14px;
    padding: 1rem 1.5rem;
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.15);
}
</style>

<div class="adv-page-wrapper">
    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="adv-breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/index.php"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item"><a href="student.php">Student Attendance</a></li>
            <li class="breadcrumb-item active" aria-current="page">Mark Student Attendance</li>
        </ol>
    </nav>

    <!-- Hero Header Banner Banner - Exact Match to Student Registration Header -->
    <div class="adv-hero-banner mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold mb-0 text-white fs-3">Mark Student Attendance</h3>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">Daily Register</span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">Record and manage daily student attendance accurately and efficiently.</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <a href="reports.php" class="btn btn-sm btn-light text-primary rounded-2 px-3 py-2 fw-semibold shadow-sm"><i class="fa-solid fa-book me-1"></i>Attendance Register</a>
                <a href="daily.php" class="btn btn-sm btn-outline-light rounded-2 px-3 py-2 fw-semibold"><i class="fa-solid fa-chart-pie me-1"></i>Daily Report</a>
            </div>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-center rounded-3 p-3 d-print-none" style="background-color: #f0fdf4; border-left: 4px solid #16a34a !important;">
            <i class="fa-solid fa-circle-check fs-5 text-success me-3"></i>
            <div>
                <strong class="text-success d-block">Success Confirmation</strong>
                <span class="text-secondary small"><?php echo htmlspecialchars($message); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-center rounded-3 p-3 d-print-none" style="background-color: #fef2f2; border-left: 4px solid #dc2626 !important;">
            <i class="fa-solid fa-circle-xmark fs-5 text-danger me-3"></i>
            <div>
                <strong class="text-danger d-block">Attention Required</strong>
                <span class="text-secondary small"><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- 1. Selection Criteria Panel Card -->
    <div class="adv-card d-print-none">
        <div class="adv-card-header">
            <h5 class="adv-card-title">
                <i class="fa-solid fa-sliders"></i>Select Attendance Details
            </h5>
            <span class="badge bg-light text-secondary border px-2 py-1 small fw-semibold">Step 1: Criteria</span>
        </div>
        <div class="adv-card-body">
            <p class="text-muted small mb-3">Choose the date and class information to load students for attendance.</p>

            <form method="GET" action="student.php" id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-2 col-sm-6">
                    <label class="adv-label">Attendance Date <span class="adv-required">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-primary"><i class="fa-regular fa-calendar"></i></span>
                        <input type="date" class="form-control adv-control font-monospace fw-bold" name="date" value="<?php echo $selectedDate; ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="adv-label">Academic Session</label>
                    <select class="form-select adv-select" name="academic_session">
                        <option value="">All Sessions</option>
                        <?php foreach ($academicSessions as $sess): ?>
                            <option value="<?php echo $sess; ?>" <?php echo ($selected_session === $sess) ? 'selected' : ''; ?>><?php echo $sess; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="adv-label">Campus</label>
                    <select class="form-select adv-select" name="campus">
                        <option value="">All Campuses</option>
                        <?php foreach ($campusOptions as $camp): ?>
                            <option value="<?php echo $camp; ?>" <?php echo ($selected_campus === $camp) ? 'selected' : ''; ?>><?php echo $camp; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="adv-label">Academic Type <span class="adv-required">*</span></label>
                    <select class="form-select adv-select" name="academic_type" required>
                        <option value="School" <?php echo ($academic_type === 'School') ? 'selected' : ''; ?>>School System</option>
                        <option value="Academy" <?php echo ($academic_type === 'Academy') ? 'selected' : ''; ?>>Academy Program</option>
                    </select>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="adv-label">Class <span class="adv-required">*</span></label>
                    <select class="form-select adv-select" name="class" required>
                        <option value="">— Select Class —</option>
                        <?php foreach ($classesList as $cls): ?>
                            <option value="<?php echo $cls; ?>" <?php echo ($class_name === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 col-sm-6">
                    <label class="adv-label">Section <span class="adv-required">*</span></label>
                    <select class="form-select adv-select" name="section" required>
                        <option value="">— Select Section —</option>
                        <?php foreach ($sectionsList as $sec): ?>
                            <option value="<?php echo $sec; ?>" <?php echo ($section_name === $sec) ? 'selected' : ''; ?>>Section <?php echo $sec; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 text-end border-top pt-3 mt-3">
                    <button type="submit" id="loadBtn" class="btn btn-primary rounded-2 px-4 py-2 fw-semibold shadow-sm" style="background-color: #1d4ed8; border-color: #1d4ed8;">
                        <i class="fa-solid fa-users-viewfinder me-2"></i>Load Students
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Print Header (Visible ONLY during print layout) -->
    <div class="d-none d-print-block text-center mb-4 py-2">
        <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
        <h5 class="text-secondary mb-3">Official Student Attendance Register</h5>
        <div class="small text-muted border-top border-bottom py-2 d-flex justify-content-around">
            <span>Date: <strong><?php echo date('d-M-Y (l)', strtotime($selectedDate)); ?></strong></span>
            <span>Class: <strong><?php echo htmlspecialchars($class_name . ' - ' . $section_name); ?></strong></span>
            <span>Academic Type: <strong><?php echo htmlspecialchars($academic_type); ?></strong></span>
            <span>Total Students: <strong><?php echo $totalStudents; ?></strong></span>
        </div>
    </div>

    <?php if (!empty($class_name) && !empty($section_name)): ?>

        <!-- 2. Selected Attendance Context Meta Bar -->
        <div class="context-meta-bar mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3 d-print-none">
            <div class="d-flex align-items-center flex-wrap gap-3 small text-dark">
                <span><i class="fa-regular fa-calendar-check text-primary me-1"></i> Date: <strong><?php echo date('l, d-M-Y', strtotime($selectedDate)); ?></strong></span>
                <span class="text-muted">|</span>
                <span><i class="fa-solid fa-graduation-cap text-primary me-1"></i> Class: <strong><?php echo htmlspecialchars($class_name . ' - ' . $section_name); ?></strong></span>
                <span class="text-muted">|</span>
                <span><i class="fa-solid fa-school text-primary me-1"></i> Type: <strong><?php echo htmlspecialchars($academic_type); ?></strong></span>
                <?php if (!empty($selected_campus)): ?>
                    <span class="text-muted">|</span>
                    <span><i class="fa-solid fa-building text-primary me-1"></i> Campus: <strong><?php echo htmlspecialchars($selected_campus); ?></strong></span>
                <?php endif; ?>
                <?php if (!empty($selected_session)): ?>
                    <span class="text-muted">|</span>
                    <span><i class="fa-solid fa-calendar-days text-primary me-1"></i> Session: <strong><?php echo htmlspecialchars($selected_session); ?></strong></span>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($alreadyMarked): ?>
                    <span class="badge bg-success text-white px-3 py-1.5 rounded-pill fs-7 fw-semibold"><i class="fa-solid fa-database me-1"></i>Attendance Previously Saved</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark px-3 py-1.5 rounded-pill fs-7 fw-semibold"><i class="fa-solid fa-hourglass-start me-1"></i>New Entry Pending</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3. Attendance Summary Cards -->
        <div class="row g-3 mb-4 d-print-none">
            <div class="col-6 col-md-2">
                <div class="summary-mini-card">
                    <div class="summary-mini-icon bg-primary-subtle text-primary">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-semibold d-block">Total</span>
                        <h4 class="fw-bold text-dark mb-0" id="stat-total"><?php echo $totalStudents; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="summary-mini-card">
                    <div class="summary-mini-icon bg-success-subtle text-success">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-semibold d-block text-success">Present</span>
                        <h4 class="fw-bold text-success mb-0" id="stat-present"><?php echo $presentCount; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="summary-mini-card">
                    <div class="summary-mini-icon bg-danger-subtle text-danger">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-semibold d-block text-danger">Absent</span>
                        <h4 class="fw-bold text-danger mb-0" id="stat-absent"><?php echo $absentCount; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="summary-mini-card">
                    <div class="summary-mini-icon bg-warning-subtle text-warning">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-semibold d-block text-warning">Late</span>
                        <h4 class="fw-bold text-warning mb-0" id="stat-late"><?php echo $lateCount; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="summary-mini-card">
                    <div class="summary-mini-icon bg-purple-subtle text-purple" style="background:#f5f3ff; color:#7c3aed;">
                        <i class="fa-solid fa-plane-departure"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-semibold d-block" style="color:#7c3aed;">Leave</span>
                        <h4 class="fw-bold mb-0" style="color:#7c3aed;" id="stat-leave"><?php echo $leaveCount; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="summary-mini-card">
                    <div class="summary-mini-icon bg-secondary-subtle text-secondary">
                        <i class="fa-solid fa-circle-minus"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-semibold d-block">Unmarked</span>
                        <h4 class="fw-bold text-secondary mb-0" id="stat-unmarked"><?php echo $unmarkedCount; ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Attendance Table & Form Container -->
        <form id="attendanceForm" method="POST" action="student.php">
            <input type="hidden" name="action" value="save_attendance">
            <input type="hidden" name="date" value="<?php echo $selectedDate; ?>">
            <input type="hidden" name="academic_type" value="<?php echo $academic_type; ?>">
            <input type="hidden" name="academic_session" value="<?php echo htmlspecialchars($selected_session); ?>">
            <input type="hidden" name="campus" value="<?php echo htmlspecialchars($selected_campus); ?>">
            <input type="hidden" name="class" value="<?php echo $class_name; ?>">
            <input type="hidden" name="section" value="<?php echo $section_name; ?>">

            <div class="adv-card">
                <!-- Register Header & Instant Search -->
                <div class="adv-card-header flex-wrap gap-3 d-print-none">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="adv-card-title">
                            <i class="fa-solid fa-list-check"></i>Student Attendance Register
                        </h5>
                        <span class="badge bg-light text-dark border px-2.5 py-1 small fw-semibold"><?php echo $totalStudents; ?> Loaded</span>
                    </div>

                    <!-- Instant Search Input -->
                    <div class="search-box">
                        <div class="input-group input-group-sm" style="width: 270px;">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="tableSearch" class="form-control border-start-0 adv-control" placeholder="Search student name, roll..." onkeyup="filterStudentTable()">
                        </div>
                    </div>
                </div>

                <!-- Batch Operations Bar -->
                <div class="p-3 bg-light border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3 d-print-none">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="small fw-bold me-2 text-secondary"><i class="fa-solid fa-wand-magic-sparkles text-primary me-1"></i> Mark All Loaded As:</span>
                        <button type="button" class="btn btn-sm btn-batch-present rounded-pill px-3 fw-bold" onclick="markAllStatus('Present')">
                            <i class="fa-solid fa-check me-1"></i> All Present
                        </button>
                        <button type="button" class="btn btn-sm btn-batch-absent rounded-pill px-3 fw-bold" onclick="markAllStatus('Absent')">
                            <i class="fa-solid fa-xmark me-1"></i> All Absent
                        </button>
                        <button type="button" class="btn btn-sm btn-batch-late rounded-pill px-3 fw-bold" onclick="markAllStatus('Late')">
                            <i class="fa-solid fa-clock me-1"></i> All Late
                        </button>
                        <button type="button" class="btn btn-sm btn-batch-leave rounded-pill px-3 fw-bold" onclick="markAllStatus('Leave')">
                            <i class="fa-solid fa-plane-departure me-1"></i> All Leave
                        </button>
                    </div>

                    <!-- Status Color Legend -->
                    <div class="d-flex align-items-center gap-3 small text-muted">
                        <span class="d-flex align-items-center gap-1"><span class="badge rounded-circle p-1" style="background:#16a34a;"></span> Present</span>
                        <span class="d-flex align-items-center gap-1"><span class="badge rounded-circle p-1" style="background:#dc2626;"></span> Absent</span>
                        <span class="d-flex align-items-center gap-1"><span class="badge rounded-circle p-1" style="background:#d97706;"></span> Late</span>
                        <span class="d-flex align-items-center gap-1"><span class="badge rounded-circle p-1" style="background:#7c3aed;"></span> Leave</span>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-attendance" id="studentsTable">
                        <thead>
                            <tr>
                                <th width="45" class="text-center">#</th>
                                <th width="60" class="d-print-none">Photo</th>
                                <th width="130">Admission No</th>
                                <th width="90">Roll No</th>
                                <th>Student & Father Name</th>
                                <th width="90" class="d-print-none">Status</th>
                                <th width="360">Attendance Status Selector</th>
                                <th width="200">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <div class="py-4">
                                            <i class="fa-solid fa-users-slash fs-1 text-secondary opacity-25 mb-3 d-block"></i>
                                            <h5 class="fw-bold text-dark">No Active Students Found</h5>
                                            <p class="small text-muted mb-0">No active student records matched class <strong><?php echo htmlspecialchars($class_name . ' - ' . $section_name); ?></strong>.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: foreach ($students as $idx => $st): 
                                $statusVal = $st['current_status'] ?? 'Present';
                                $fullName = trim($st['first_name'] . ' ' . $st['last_name']);
                                $initials = strtoupper(substr($st['first_name'] ?? 'S', 0, 1) . substr($st['last_name'] ?? '', 0, 1));
                            ?>
                                <tr class="student-row">
                                    <td class="text-center small fw-semibold text-muted"><?php echo $idx + 1; ?></td>
                                    <td class="d-print-none">
                                        <?php if (!empty($st['doc_student_photo'])): ?>
                                            <img src="<?php echo APP_URL . '/' . $st['doc_student_photo']; ?>" class="avatar-circle">
                                        <?php else: ?>
                                            <div class="avatar-circle">
                                                <?php echo htmlspecialchars($initials); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2.5 py-1">
                                            <?php echo sanitize($st['admission_no']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="font-monospace text-dark"><?php echo displayValue($st['roll_no']); ?></strong>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark student-name-target"><?php echo sanitize($fullName); ?></div>
                                        <div class="small text-muted">Father: <?php echo displayValue($st['father_name']); ?></div>
                                    </td>
                                    <td class="d-print-none">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Active</span>
                                    </td>

                                    <td>
                                        <!-- Embedded student ID -->
                                        <input type="hidden" name="records[<?php echo $idx; ?>][student_id]" value="<?php echo $st['id']; ?>">

                                        <!-- Segmented Status Radio Group -->
                                        <div class="status-pill-group">
                                            <div class="status-pill-item">
                                                <input type="radio" class="att-radio-btn" name="records[<?php echo $idx; ?>][status]" id="p_<?php echo $st['id']; ?>" value="Present" <?php echo ($statusVal === 'Present') ? 'checked' : ''; ?> onchange="calculateRealtimeStats()">
                                                <label for="p_<?php echo $st['id']; ?>" class="status-pill-label">
                                                    <i class="fa-solid fa-check me-1"></i> Present
                                                </label>
                                            </div>
                                            <div class="status-pill-item">
                                                <input type="radio" class="att-radio-btn" name="records[<?php echo $idx; ?>][status]" id="a_<?php echo $st['id']; ?>" value="Absent" <?php echo ($statusVal === 'Absent') ? 'checked' : ''; ?> onchange="calculateRealtimeStats()">
                                                <label for="a_<?php echo $st['id']; ?>" class="status-pill-label">
                                                    <i class="fa-solid fa-xmark me-1"></i> Absent
                                                </label>
                                            </div>
                                            <div class="status-pill-item">
                                                <input type="radio" class="att-radio-btn" name="records[<?php echo $idx; ?>][status]" id="l_<?php echo $st['id']; ?>" value="Late" <?php echo ($statusVal === 'Late') ? 'checked' : ''; ?> onchange="calculateRealtimeStats()">
                                                <label for="l_<?php echo $st['id']; ?>" class="status-pill-label">
                                                    <i class="fa-solid fa-clock me-1"></i> Late
                                                </label>
                                            </div>
                                            <div class="status-pill-item">
                                                <input type="radio" class="att-radio-btn" name="records[<?php echo $idx; ?>][status]" id="lv_<?php echo $st['id']; ?>" value="Leave" <?php echo ($statusVal === 'Leave') ? 'checked' : ''; ?> onchange="calculateRealtimeStats()">
                                                <label for="lv_<?php echo $st['id']; ?>" class="status-pill-label">
                                                    <i class="fa-solid fa-plane-departure me-1"></i> Leave
                                                </label>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <input type="text" class="form-control form-control-sm adv-control" name="records[<?php echo $idx; ?>][remarks]" value="<?php echo htmlspecialchars($st['current_remarks'] ?? ''); ?>" placeholder="Add note...">
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Bar -->
                <?php if (!empty($students)): ?>
                    <div class="p-3 bg-light border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 d-print-none">
                        <button type="button" class="btn btn-outline-secondary rounded-2 px-3 py-2 fw-semibold small" onclick="window.print()">
                            <i class="fa-solid fa-print me-2"></i>Print Attendance Sheet
                        </button>
                        <div class="d-flex align-items-center gap-2">
                            <a href="student.php?date=<?php echo $selectedDate; ?>&academic_type=<?php echo $academic_type; ?>&academic_session=<?php echo urlencode($selected_session); ?>&campus=<?php echo urlencode($selected_campus); ?>&class=<?php echo urlencode($class_name); ?>&section=<?php echo urlencode($section_name); ?>" class="btn btn-outline-secondary rounded-2 px-3 py-2 fw-semibold small">
                                <i class="fa-solid fa-rotate-left me-1"></i> Reset
                            </a>
                            <button type="submit" id="saveAttBtn" class="btn btn-primary rounded-2 px-4 py-2 fw-semibold shadow-sm" style="background-color: #1d4ed8; border-color: #1d4ed8;">
                                <i class="fa-solid fa-floppy-disk me-2"></i>Save Attendance
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </form>

    <?php else: ?>
        <!-- Guidance panel before selecting class -->
        <div class="adv-card p-5 text-center d-print-none my-4">
            <div class="py-4">
                <div class="mb-3">
                    <i class="fa-solid fa-clipboard-question text-primary opacity-25" style="font-size: 4rem;"></i>
                </div>
                <h4 class="fw-bold mb-2 text-dark">Ready to Mark Attendance</h4>
                <p class="text-muted mb-0" style="max-width: 520px; margin: 0 auto;">Select the attendance details above and click <strong>Load Students</strong> to begin.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Dynamic Interactivity JavaScript -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Filter loading spinner
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function() {
            const btn = document.getElementById('loadBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading...';
            }
        });
    }

    // Save loading spinner
    const attForm = document.getElementById('attendanceForm');
    if (attForm) {
        attForm.addEventListener('submit', function() {
            const btn = document.getElementById('saveAttBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving Attendance...';
            }
        });
    }
});

// Bulk status selector
function markAllStatus(status) {
    document.querySelectorAll(".att-radio-btn[value='" + status + "']").forEach(radio => {
        radio.checked = true;
    });
    calculateRealtimeStats();
}

// Dynamic counter calculation
function calculateRealtimeStats() {
    let present = 0;
    let absent = 0;
    let late = 0;
    let leave = 0;
    let unmarked = 0;

    const rows = document.querySelectorAll('#studentsTable tbody tr.student-row');
    rows.forEach(row => {
        const checked = row.querySelector(".att-radio-btn:checked");
        if (checked) {
            const val = checked.value;
            if (val === 'Present') present++;
            else if (val === 'Absent') absent++;
            else if (val === 'Late') late++;
            else if (val === 'Leave') leave++;
        } else {
            unmarked++;
        }
    });

    const presentEl = document.getElementById('stat-present');
    const absentEl = document.getElementById('stat-absent');
    const lateEl = document.getElementById('stat-late');
    const leaveEl = document.getElementById('stat-leave');
    const unmarkedEl = document.getElementById('stat-unmarked');

    if (presentEl) presentEl.textContent = present;
    if (absentEl) absentEl.textContent = absent;
    if (lateEl) lateEl.textContent = late;
    if (leaveEl) leaveEl.textContent = leave;
    if (unmarkedEl) unmarkedEl.textContent = unmarked;
}

// Instant client-side search
function filterStudentTable() {
    const input = document.getElementById('tableSearch');
    if (!input) return;
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll('#studentsTable tbody tr.student-row');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.indexOf(filter) > -1) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
