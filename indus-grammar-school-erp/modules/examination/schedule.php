<?php
/**
 * Indus Grammar School ERP - Examination Schedules & Supervision Coordinator
 * Version 4.0.0
 */

$pageTitle = 'Exam Timetable & Supervision';
$breadcrumbActive = 'Examination';
include_once __DIR__ . '/../../includes/header.php';

// Restricted to Super Admin, School Admin, Exam Controller, Teachers, Students
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
$isStaff = in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'teacher', 'exam_controller']);

$db = Database::getConnection();

// Fetch filter values
$selectedExam  = isset($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : 0;
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedRoom  = isset($_GET['room']) ? trim($_GET['room']) : '';

// Load selectors
$examTypes = $db->query("SELECT * FROM exam_types ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$classes   = SchoolClass::all();

// Staff list for supervision assignment dropdown
$staffList = [];
try {
    $staffList = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, designation, department FROM staff WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $staffList = [];
}

// Load schedules matching filters
$schedules = ExamSchedule::all($selectedExam, $selectedClass);

// Optional room filter
if ($selectedRoom !== '') {
    $schedules = array_filter($schedules, function($item) use ($selectedRoom) {
        return stripos($item['room'] ?? '', $selectedRoom) !== false;
    });
}

// Compute KPI Metrics
$totalScheduled = count($schedules);
$upcomingCount  = 0;
$completedCount = 0;
$roomsMap       = [];
$supervisorsMap = [];
$classesMap     = [];
$todayStr       = date('Y-m-d');

foreach ($schedules as $s) {
    if ($s['exam_date'] >= $todayStr) {
        $upcomingCount++;
    } else {
        $completedCount++;
    }
    if (!empty($s['room'])) {
        $roomsMap[$s['room']] = true;
    }
    if (!empty($s['supervisor'])) {
        $supervisorsMap[$s['supervisor']] = true;
    }
    if (!empty($s['class_id'])) {
        $classesMap[$s['class_id']] = true;
    }
}
$roomsCount       = count($roomsMap);
$supervisorsCount = count($supervisorsMap);
$classesCount     = count($classesMap);

// Pre-load all subjects mapped by class_id to feed the dynamic selector in modal
$rawSubjects = Subject::all();
$subjectsMap = [];
foreach ($rawSubjects as $s) {
    $subjectsMap[$s['class_id']][] = [
        'id'   => $s['id'],
        'name' => $s['subject_name'] . ' (' . ($s['subject_code'] ?: 'No Code') . ')'
    ];
}

// Prepare active exam title for print header
$activeExamTitle = 'All Examination Terms';
if ($selectedExam > 0) {
    foreach ($examTypes as $et) {
        if ((int)$et['id'] === $selectedExam) {
            $activeExamTitle = $et['exam_name'];
            break;
        }
    }
}
$activeClassTitle = 'All Classes';
if ($selectedClass > 0) {
    foreach ($classes as $c) {
        if ((int)$c['id'] === $selectedClass) {
            $activeClassTitle = $c['class_name'] . ' - ' . $c['section'];
            break;
        }
    }
}
?>

<!-- Custom Styling -->
<style>
.exam-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f2744 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 2.2rem 2rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
    border-bottom: 3px solid #38bdf8;
    position: relative;
    overflow: hidden;
}
.exam-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(56, 189, 248, 0.12) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
}
.kpi-card-schedule {
    border: none;
    border-radius: 14px;
    background: #ffffff;
    transition: all 0.25s ease-in-out;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
}
.kpi-card-schedule:hover {
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
.badge-soft-cyan { background-color: rgba(6, 182, 212, 0.12); color: #0891b2; }
.badge-soft-emerald { background-color: rgba(16, 185, 129, 0.12); color: #059669; }
.badge-soft-amber { background-color: rgba(245, 158, 11, 0.12); color: #d97706; }
.badge-soft-indigo { background-color: rgba(99, 102, 241, 0.12); color: #4f46e5; }
.badge-soft-rose { background-color: rgba(244, 63, 94, 0.12); color: #e11d48; }

.table-schedule thead th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    font-weight: 700;
    padding: 1rem 0.85rem;
    border-bottom: 2px solid #e2e8f0;
}
.table-schedule tbody td {
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
.supervision-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #334155;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
}
@media print {
    body * { visibility: hidden; }
    #schedulePrintArea, #schedulePrintArea * { visibility: visible; }
    #schedulePrintArea {
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

<!-- Executive Hero Header -->
<div class="exam-hero mb-4 d-print-none">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-info bg-opacity-25 text-info px-3 py-1 rounded-pill fw-semibold small">
                    <i class="fa-solid fa-calendar-days me-1"></i> Academic Session 2025-2026
                </span>
                <span class="badge bg-warning bg-opacity-25 text-warning px-3 py-1 rounded-pill fw-semibold small">
                    <i class="fa-solid fa-clock me-1"></i> Active Schedules
                </span>
            </div>
            <h2 class="fw-bold text-white mb-2">
                <i class="fa-solid fa-calendar-check text-info me-2"></i>Exam Timetable & Supervision
            </h2>
            <p class="text-white-50 mb-0">
                Create and display examination dates, room settings, and invigilator supervision tables with real-time clash checking and printable date sheets.
            </p>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                <?php if ($isStaff): ?>
                    <button class="btn btn-info text-dark fw-bold px-3 py-2 shadow-sm rounded-3" data-bs-toggle="modal" data-bs-target="#scheduleModal" onclick="resetForm()">
                        <i class="fa-solid fa-plus-circle me-1"></i> Arrange Exam Date
                    </button>
                <?php endif; ?>
                <button class="btn btn-light fw-semibold px-3 py-2 shadow-sm rounded-3" onclick="exportScheduleCSV()">
                    <i class="fa-solid fa-file-csv me-1 text-success"></i> Export CSV
                </button>
                <button class="btn btn-outline-light fw-semibold px-3 py-2 rounded-3" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Print Date Sheet
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary text-white border-secondary px-3 py-2 rounded-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Executive KPI Summary Cards -->
<div class="row g-3 mb-4 d-print-none">
    <!-- Card 1: Total Scheduled Papers -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-schedule p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Scheduled</span>
                    <h3 class="fw-bold text-dark mb-0"><?php echo $totalScheduled; ?></h3>
                    <small class="text-cyan fw-semibold"><i class="fa-solid fa-file-lines me-1"></i>Exam Papers</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-cyan">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Upcoming Papers -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-schedule p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Upcoming</span>
                    <h3 class="fw-bold text-amber mb-0" style="color:#d97706;"><?php echo $upcomingCount; ?></h3>
                    <small class="text-amber fw-semibold" style="color:#d97706;"><i class="fa-solid fa-clock-rotate-left me-1"></i>To be held</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-amber">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Halls & Rooms Allocated -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-schedule p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Halls & Rooms</span>
                    <h3 class="fw-bold text-emerald mb-0" style="color:#059669;"><?php echo $roomsCount; ?></h3>
                    <small class="text-emerald fw-semibold" style="color:#059669;"><i class="fa-solid fa-door-open me-1"></i>Assigned</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-emerald">
                    <i class="fa-solid fa-building-user"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: Invigilator Supervisors -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-schedule p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Invigilators</span>
                    <h3 class="fw-bold text-indigo mb-0" style="color:#4f46e5;"><?php echo $supervisorsCount; ?></h3>
                    <small class="text-indigo fw-semibold" style="color:#4f46e5;"><i class="fa-solid fa-user-shield me-1"></i>Staff Assigned</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-indigo">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 5: Classes Mapped -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-schedule p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Classes Mapped</span>
                    <h3 class="fw-bold text-rose mb-0" style="color:#e11d48;"><?php echo $classesCount; ?></h3>
                    <small class="text-rose fw-semibold" style="color:#e11d48;"><i class="fa-solid fa-graduation-cap me-1"></i>Active Grades</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-rose">
                    <i class="fa-solid fa-school"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 6: Completed Papers -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-schedule p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Conducted</span>
                    <h3 class="fw-bold text-secondary mb-0"><?php echo $completedCount; ?></h3>
                    <small class="text-muted fw-semibold"><i class="fa-solid fa-check-double me-1"></i>Finished</small>
                </div>
                <div class="kpi-icon-wrapper bg-light text-secondary">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Mode Navigation Tabs & Filters -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:14px;">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <ul class="nav nav-pills nav-pills-custom gap-2" id="scheduleTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-timetable-btn" data-bs-toggle="pill" data-bs-target="#tab-timetable" type="button">
                        <i class="fa-solid fa-calendar-days me-2"></i>Date Sheet Timetable
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-supervision-btn" data-bs-toggle="pill" data-bs-target="#tab-supervision" type="button">
                        <i class="fa-solid fa-user-shield me-2"></i>Supervision Roster
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-rooms-btn" data-bs-toggle="pill" data-bs-target="#tab-rooms" type="button">
                        <i class="fa-solid fa-door-open me-2"></i>Room Allocation Grid
                    </button>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 250px;">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" id="liveSearchInput" class="form-control bg-light border-start-0" placeholder="Filter timetable..." onkeyup="filterTable()">
                </div>
            </div>
        </div>

        <hr class="my-3 text-muted opacity-25">

        <!-- Server Filter Form -->
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Filter by Exam Term</label>
                <select class="form-select form-select-sm" name="exam_type_id">
                    <option value="0">All Exam Terms</option>
                    <?php foreach ($examTypes as $et): ?>
                        <option value="<?php echo $et['id']; ?>" <?php echo $selectedExam === (int)$et['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($et['exam_name']); ?> (<?php echo htmlspecialchars($et['academic_session']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Filter by Class</label>
                <select class="form-select form-select-sm" name="class_id">
                    <option value="0">All Classes & Sections</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Filter by Hall / Room</label>
                <input type="text" name="room" class="form-control form-control-sm" placeholder="e.g. Hall A" value="<?php echo htmlspecialchars($selectedRoom); ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 py-1 fw-semibold">
                    <i class="fa-solid fa-filter me-1"></i> Apply
                </button>
                <a href="schedule.php" class="btn btn-light btn-sm px-3 py-1 fw-semibold text-muted" title="Reset Filters">
                    <i class="fa-solid fa-rotate"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tab Content Container -->
<div class="tab-content" id="scheduleTabContent">

    <!-- ── TAB 1: DATE SHEET TIMETABLE ──────────────────────────────── -->
    <div class="tab-pane fade show active" id="tab-timetable" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius:14px;" id="schedulePrintArea">
            
            <!-- Print-Only Header Banner -->
            <div class="card-header bg-white border-0 pt-4 px-4 d-none d-print-block text-center border-bottom pb-3">
                <div class="d-flex align-items-center justify-content-center gap-3 mb-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-0">INDUS GRAMMAR SCHOOL</h2>
                        <h5 class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($activeExamTitle); ?></h5>
                        <p class="text-muted small mb-0">Class: <strong><?php echo htmlspecialchars($activeClassTitle); ?></strong> | Issued: <?php echo date('d-M-Y'); ?></p>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-schedule table-hover align-middle mb-0 table-print-clean" id="scheduleMainTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th>Exam Term</th>
                                <th>Class & Section</th>
                                <th>Subject Code & Title</th>
                                <th class="text-center">Exam Date & Day</th>
                                <th class="text-center">Time Window</th>
                                <th class="text-center">Hall / Room</th>
                                <th>Invigilator Supervisor</th>
                                <?php if ($isStaff): ?>
                                    <th class="text-end d-print-none" style="width: 140px;">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($schedules)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="py-4">
                                            <i class="fa-solid fa-calendar-xmark text-muted fa-3x mb-3"></i>
                                            <h5 class="fw-bold text-dark">No Exam Schedules Found</h5>
                                            <p class="text-muted small mb-3">There are no examination dates mapped matching your selected filter criteria.</p>
                                            <?php if ($isStaff): ?>
                                                <button class="btn btn-primary btn-sm px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#scheduleModal" onclick="resetForm()">
                                                    <i class="fa-solid fa-plus-circle me-1"></i> Schedule First Exam Paper
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: 
                                $idx = 1;
                                foreach ($schedules as $s): 
                                    $isPast = ($s['exam_date'] < $todayStr);
                                    $isToday = ($s['exam_date'] === $todayStr);
                                    $dayName = date('l', strtotime($s['exam_date']));
                                    $startTimeFormatted = !empty($s['start_time']) ? date('h:i A', strtotime($s['start_time'])) : 'N/A';
                                    $endTimeFormatted   = !empty($s['end_time']) ? date('h:i A', strtotime($s['end_time'])) : 'N/A';
                                    
                                    // Duration calculation
                                    $durationStr = '—';
                                    if (!empty($s['start_time']) && !empty($s['end_time'])) {
                                        $t1 = strtotime($s['start_time']);
                                        $t2 = strtotime($s['end_time']);
                                        $diffMins = round(abs($t2 - $t1) / 60);
                                        $hrs = floor($diffMins / 60);
                                        $mins = $diffMins % 60;
                                        $durationStr = ($hrs > 0 ? "{$hrs}h " : '') . ($mins > 0 ? "{$mins}m" : '');
                                    }
                            ?>
                                <tr>
                                    <td class="text-center text-muted fw-bold small"><?php echo $idx++; ?></td>
                                    <td>
                                        <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($s['exam_name']); ?></span>
                                        <?php if ($isToday): ?>
                                            <span class="badge bg-danger text-white px-2 py-0 small"><i class="fa-solid fa-bullseye me-1"></i>Today</span>
                                        <?php elseif ($isPast): ?>
                                            <span class="badge bg-light text-muted border px-2 py-0 small">Conducted</span>
                                        <?php else: ?>
                                            <span class="badge bg-info bg-opacity-10 text-info px-2 py-0 small">Upcoming</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1.5 rounded-pill fw-semibold">
                                            <i class="fa-solid fa-graduation-cap me-1"></i><?php echo htmlspecialchars($s['class_name'] . ' - ' . $s['section']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">
                                            <i class="fa-solid fa-book-open text-primary me-1.5"></i><?php echo htmlspecialchars($s['subject_name']); ?>
                                        </div>
                                        <small class="text-muted d-block text-xs font-monospace">
                                            CODE: <?php echo htmlspecialchars($s['subject_code'] ?: 'N/A'); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark d-block"><?php echo date('d-M-Y', strtotime($s['exam_date'])); ?></span>
                                        <span class="badge bg-light text-dark border px-2 py-0.5 fw-normal small"><?php echo $dayName; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="fw-semibold text-dark small">
                                            <i class="fa-regular fa-clock text-primary me-1"></i><?php echo $startTimeFormatted; ?> – <?php echo $endTimeFormatted; ?>
                                        </div>
                                        <span class="text-muted text-xs d-block">(Duration: <?php echo $durationStr; ?>)</span>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty($s['room'])): ?>
                                            <span class="badge bg-dark text-white px-3 py-1.5 rounded-3 fw-bold">
                                                <i class="fa-solid fa-door-open me-1 text-warning"></i><?php echo htmlspecialchars($s['room']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($s['supervisor'])): ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="supervision-avatar">
                                                    <?php echo strtoupper(substr($s['supervisor'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold text-dark d-block small"><?php echo htmlspecialchars($s['supervisor']); ?></span>
                                                    <span class="text-muted text-xs"><i class="fa-solid fa-user-shield me-1 text-info"></i>Invigilator</span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-light text-secondary border">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($isStaff): ?>
                                        <td class="text-end d-print-none">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary rounded-pill px-2.5 me-1" title="Edit Schedule" onclick='editSchedule(<?php echo json_encode($s); ?>)'>
                                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                                </button>
                                                <button class="btn btn-outline-danger btn-delete rounded-pill px-2.5" title="Delete Schedule" data-id="<?php echo $s['id']; ?>">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Print Footer Signatures (Visible only on print) -->
            <div class="card-footer bg-white border-0 pt-5 pb-4 d-none d-print-block">
                <div class="row text-center mt-4">
                    <div class="col-4">
                        <div class="border-top pt-2 mx-3">
                            <span class="fw-bold text-dark small">Invigilator / Supervisor</span>
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

    <!-- ── TAB 2: SUPERVISION ROSTER ──────────────────────────────────── -->
    <div class="tab-pane fade" id="tab-supervision" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius:14px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-user-shield me-2 text-primary"></i>Invigilator Supervision Roster</h5>
                <p class="text-muted small mb-0">Summary of teachers and staff assigned for hall invigilation duty.</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Invigilator Name</th>
                                <th class="text-center">Total Duties Assigned</th>
                                <th>Assigned Halls & Rooms</th>
                                <th>Assigned Dates & Subjects</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Group schedules by supervisor
                            $supervisionRoster = [];
                            foreach ($schedules as $s) {
                                $sup = trim($s['supervisor'] ?? '');
                                if (empty($sup)) $sup = 'Unassigned';
                                $supervisionRoster[$sup][] = $s;
                            }
                            if (empty($supervisionRoster)):
                            ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No invigilators assigned yet.</td></tr>
                            <?php else: foreach ($supervisionRoster as $supName => $duties): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="supervision-avatar bg-primary text-white">
                                                <?php echo strtoupper(substr($supName, 0, 1)); ?>
                                            </div>
                                            <div>
                                                <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($supName); ?></span>
                                                <span class="text-muted text-xs"><?php echo $supName === 'Unassigned' ? 'Requires Duty Allocation' : 'Staff Member'; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center fw-bold">
                                        <span class="badge bg-primary rounded-pill px-3 py-1.5 fs-6"><?php echo count($duties); ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $roomsAssigned = array_unique(array_filter(array_column($duties, 'room')));
                                        if (empty($roomsAssigned)) {
                                            echo '<span class="text-muted small">No Room Specified</span>';
                                        } else {
                                            foreach ($roomsAssigned as $r) {
                                                echo '<span class="badge bg-dark text-white me-1 mb-1 px-2.5 py-1">' . htmlspecialchars($r) . '</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($duties as $d): ?>
                                                <span class="badge bg-light text-dark border p-2 text-start" style="font-size:11px;">
                                                    <i class="fa-solid fa-calendar-day text-primary me-1"></i><?php echo date('d-M', strtotime($d['exam_date'])); ?>:
                                                    <strong><?php echo htmlspecialchars($d['subject_name']); ?></strong> (<?php echo htmlspecialchars($d['class_name']); ?>)
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TAB 3: ROOM ALLOCATION GRID ───────────────────────────────── -->
    <div class="tab-pane fade" id="tab-rooms" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius:14px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-door-open me-2 text-success"></i>Room Settings & Hall Allocation Matrix</h5>
                <p class="text-muted small mb-0">Overview of exam hall utilization to prevent double-booking and room capacity overflow.</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Hall / Room Designation</th>
                                <th class="text-center">Scheduled Sessions</th>
                                <th>Assigned Classes & Subjects</th>
                                <th class="text-center">Supervisor On Duty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Group schedules by room
                            $roomGrid = [];
                            foreach ($schedules as $s) {
                                $rm = trim($s['room'] ?? '');
                                if (empty($rm)) $rm = 'Unassigned Room';
                                $roomGrid[$rm][] = $s;
                            }
                            if (empty($roomGrid)):
                            ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No rooms assigned yet.</td></tr>
                            <?php else: foreach ($roomGrid as $roomName => $items): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="kpi-icon-wrapper badge-soft-emerald" style="width:40px; height:40px; font-size:1.1rem;">
                                                <i class="fa-solid fa-building"></i>
                                            </div>
                                            <div>
                                                <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($roomName); ?></span>
                                                <span class="text-muted text-xs">Exam Hall Venue</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center fw-bold">
                                        <span class="badge bg-success bg-opacity-15 text-success rounded-pill px-3 py-1.5 fs-6"><?php echo count($items); ?> Papers</span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($items as $it): ?>
                                                <span class="badge bg-light text-dark border p-2 text-start" style="font-size:11px;">
                                                    <strong><?php echo htmlspecialchars($it['class_name']); ?></strong> - <?php echo htmlspecialchars($it['subject_name']); ?>
                                                    <span class="text-muted d-block"><?php echo date('d-M-Y', strtotime($it['exam_date'])); ?> (<?php echo date('h:i A', strtotime($it['start_time'])); ?>)</span>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                        $supsInRoom = array_unique(array_filter(array_column($items, 'supervisor')));
                                        if (empty($supsInRoom)) {
                                            echo '<span class="text-muted small">None</span>';
                                        } else {
                                            foreach ($supsInRoom as $sp) {
                                                echo '<span class="badge bg-dark text-white d-block mb-1 py-1 px-2 small">' . htmlspecialchars($sp) . '</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Modal Form: Arrange Exam Date -->
<?php if ($isStaff): ?>
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-0 pt-4 px-4 bg-light rounded-top">
                <h5 class="modal-title fw-bold text-dark" id="modalTitle">
                    <i class="fa-solid fa-calendar-plus me-2 text-primary"></i>Arrange Exam Date & Room
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="scheduleForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_exam_schedule">
                    <input type="hidden" name="schedule_id" id="scheduleId" value="0">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Select Exam Term *</label>
                            <select class="form-select" name="exam_type_id" id="scheduleExamTypeId" required>
                                <option value="">-- Choose Exam Term --</option>
                                <?php foreach ($examTypes as $et): ?>
                                    <option value="<?php echo $et['id']; ?>"><?php echo htmlspecialchars($et['exam_name']); ?> (<?php echo htmlspecialchars($et['academic_session']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Class & Section *</label>
                            <select class="form-select" name="class_id" id="scheduleClassId" required onchange="populateSubjects()">
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Subject *</label>
                            <select class="form-select" name="subject_id" id="scheduleSubjectId" required disabled>
                                <option value="">-- Choose Class First --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Examination Date *</label>
                            <input type="date" class="form-control" name="exam_date" id="scheduleDate" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Start Time *</label>
                            <input type="time" class="form-control" name="start_time" id="scheduleStart" required value="09:00" onchange="calcDuration()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">End Time *</label>
                            <input type="time" class="form-control" name="end_time" id="scheduleEnd" required value="12:00" onchange="calcDuration()">
                        </div>
                    </div>

                    <div class="alert alert-light border d-flex align-items-center justify-content-between p-2.5 mb-3" style="border-radius:10px;">
                        <span class="small text-muted"><i class="fa-solid fa-clock me-1 text-primary"></i>Calculated Exam Duration:</span>
                        <span class="fw-bold text-dark small" id="calculatedDurationBadge">3 Hours 0 Mins</span>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Hall / Room Number</label>
                            <input type="text" class="form-control" name="room" id="scheduleRoom" placeholder="e.g. Hall A, Room 101, Lab 2" list="roomSuggestions">
                            <datalist id="roomSuggestions">
                                <option value="Hall A">
                                <option value="Hall B">
                                <option value="Main Auditorium">
                                <option value="Room 101">
                                <option value="Room 102">
                                <option value="Science Lab">
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Invigilator / Supervisor</label>
                            <input type="text" class="form-control" name="supervisor" id="scheduleSupervisor" placeholder="e.g. Mr. Aslam / Choose Staff" list="staffSuggestions">
                            <datalist id="staffSuggestions">
                                <?php foreach ($staffList as $st): ?>
                                    <option value="<?php echo htmlspecialchars($st['full_name']); ?>"> (<?php echo htmlspecialchars($st['designation']); ?>)</option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 bg-light rounded-bottom">
                <button type="button" class="btn btn-outline-secondary px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="scheduleForm" class="btn btn-primary px-4 fw-bold" id="btnSave">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Schedule
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="scheduleToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="scheduleToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php 
// Convert subject preloads to JSON string for JS usage
$subjectsJson = json_encode($subjectsMap);

$extraJS = '<script>
const subjectsData = ' . $subjectsJson . ';
const modalObj = new bootstrap.Modal(document.getElementById("scheduleModal"));

function showToast(msg, ok) {
    const t = document.getElementById("scheduleToast");
    const m = document.getElementById("scheduleToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function calcDuration() {
    const st = document.getElementById("scheduleStart").value;
    const et = document.getElementById("scheduleEnd").value;
    const badge = document.getElementById("calculatedDurationBadge");
    if (!st || !et) {
        badge.textContent = "N/A";
        return;
    }
    const d1 = new Date("2000-01-01 " + st);
    const d2 = new Date("2000-01-01 " + et);
    let diff = (d2 - d1) / 1000 / 60;
    if (diff < 0) diff += 24 * 60;
    const h = Math.floor(diff / 60);
    const m = diff % 60;
    badge.textContent = `${h} Hours ${m} Mins`;
}

function populateSubjects(selectedSubId = 0) {
    const classId = document.getElementById("scheduleClassId").value;
    const subSelect = document.getElementById("scheduleSubjectId");
    subSelect.innerHTML = \'<option value="">-- Choose Subject --</option>\';
    
    if (classId && subjectsData[classId]) {
        subSelect.disabled = false;
        subjectsData[classId].forEach(sub => {
            const opt = document.createElement("option");
            opt.value = sub.id;
            opt.textContent = sub.name;
            if(parseInt(sub.id) === parseInt(selectedSubId)) opt.selected = true;
            subSelect.appendChild(opt);
        });
    } else {
        subSelect.disabled = true;
    }
}

function resetForm() {
    document.getElementById("scheduleForm").reset();
    document.getElementById("scheduleId").value = "0";
    document.getElementById("scheduleSubjectId").disabled = true;
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-calendar-plus me-2 text-primary"></i>Arrange Exam Date & Room\';
    document.getElementById("btnSave").innerHTML = \'<i class="fa-solid fa-floppy-disk me-1"></i> Save Schedule\';
    calcDuration();
}

function editSchedule(data) {
    resetForm();
    document.getElementById("scheduleId").value = data.id;
    document.getElementById("scheduleExamTypeId").value = data.exam_type_id;
    document.getElementById("scheduleClassId").value = data.class_id;
    
    populateSubjects(data.subject_id);
    
    document.getElementById("scheduleDate").value = data.exam_date;
    document.getElementById("scheduleStart").value = data.start_time;
    document.getElementById("scheduleEnd").value = data.end_time;
    document.getElementById("scheduleRoom").value = data.room;
    document.getElementById("scheduleSupervisor").value = data.supervisor;
    
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit Exam Schedule\';
    document.getElementById("btnSave").innerHTML = \'<i class="fa-solid fa-rotate me-1"></i> Update Schedule\';
    calcDuration();
    modalObj.show();
}

function filterTable() {
    const query = document.getElementById("liveSearchInput").value.toLowerCase();
    const rows = document.querySelectorAll("#scheduleMainTable tbody tr");
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = text.includes(query) ? "" : "none";
    });
}

function exportScheduleCSV() {
    let csv = [];
    let rows = document.querySelectorAll("#scheduleMainTable tr");
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
    downloadLink.download = "exam_schedule_" + new Date().toISOString().slice(0,10) + ".csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

document.addEventListener("DOMContentLoaded", function() {
    calcDuration();

    // Form Save
    const form = document.getElementById("scheduleForm");
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
                        btn.innerHTML = document.getElementById("scheduleId").value !== "0" ? \'<i class="fa-solid fa-rotate me-1"></i> Update Schedule\' : \'<i class="fa-solid fa-floppy-disk me-1"></i> Save Schedule\';
                    }
                })
                .catch(() => {
                    showToast("System connection error.", false);
                    btn.disabled = false; 
                    btn.innerHTML = \'<i class="fa-solid fa-floppy-disk me-1"></i> Save Schedule\';
                });
        });
    }

    // Delete Trigger
    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to remove this examination timetable entry?")) return;
            const id = this.dataset.id;
            this.disabled = true;

            const fd = new FormData();
            fd.append("action", "delete_exam_schedule");
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
