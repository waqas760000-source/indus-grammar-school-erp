<?php
/**
 * Indus Grammar School ERP - Subjects & Curriculum Management
 * Version 4.0.0
 */

$pageTitle = 'Subjects Setup & Curriculum';
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

// Load filters
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedType  = isset($_GET['academic_type']) ? trim($_GET['academic_type']) : '';

$classes  = SchoolClass::all();
$subjects = Subject::all($selectedClass);

// Optional Academic Type filter
if ($selectedType !== '') {
    $subjects = array_filter($subjects, function($item) use ($selectedType) {
        return strcasecmp($item['academic_type'] ?? '', $selectedType) === 0;
    });
}

// Load active faculty teachers list
$teachers = [];
try {
    $teachers = $db->query("
        SELECT id, employee_no, first_name, last_name, designation, department 
        FROM staff 
        WHERE status = 'Active' 
        ORDER BY first_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $teachers = [];
}

// Compute Metrics
$totalSubjects  = count($subjects);
$activeCount    = 0;
$assignedFaculty = 0;
$unassignedCount = 0;
$classesSet     = [];
$passRatioSum   = 0;

foreach ($subjects as $s) {
    if (($s['status'] ?? 'Active') === 'Active') {
        $activeCount++;
    }
    if (!empty($s['teacher_id'])) {
        $assignedFaculty++;
    } else {
        $unassignedCount++;
    }
    if (!empty($s['class_id'])) {
        $classesSet[$s['class_id']] = true;
    }
    $maxM = max(1, (float)($s['total_marks'] ?? 100));
    $passM = (float)($s['passing_marks'] ?? 40);
    $passRatioSum += ($passM / $maxM) * 100;
}

$classesCount = count($classesSet);
$avgPassRatio = $totalSubjects > 0 ? round($passRatioSum / $totalSubjects, 1) : 40.0;

// Filter text title for print
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

<!-- Custom CSS Styling -->
<style>
.subject-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #064e3b 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 2.2rem 2rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
    border-bottom: 3px solid #10b981;
    position: relative;
    overflow: hidden;
}
.subject-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
}
.kpi-card-subject {
    border: none;
    border-radius: 14px;
    background: #ffffff;
    transition: all 0.25s ease-in-out;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
}
.kpi-card-subject:hover {
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
.badge-soft-emerald { background-color: rgba(16, 185, 129, 0.12); color: #059669; }
.badge-soft-blue { background-color: rgba(37, 99, 235, 0.12); color: #2563eb; }
.badge-soft-amber { background-color: rgba(245, 158, 11, 0.12); color: #d97706; }
.badge-soft-purple { background-color: rgba(147, 51, 234, 0.12); color: #7e22ce; }
.badge-soft-rose { background-color: rgba(244, 63, 94, 0.12); color: #e11d48; }

.table-subject thead th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    font-weight: 700;
    padding: 1rem 0.85rem;
    border-bottom: 2px solid #e2e8f0;
}
.table-subject tbody td {
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
.faculty-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #0f172a;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
}
@media print {
    body * { visibility: hidden; }
    #subjectPrintArea, #subjectPrintArea * { visibility: visible; }
    #subjectPrintArea {
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
<div class="subject-hero mb-4 d-print-none">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-emerald bg-opacity-25 text-emerald px-3 py-1 rounded-pill fw-semibold small" style="color:#6ee7b7;">
                    <i class="fa-solid fa-graduation-cap me-1"></i> Academic Curriculum 2025-2026
                </span>
                <span class="badge bg-info bg-opacity-25 text-info px-3 py-1 rounded-pill fw-semibold small">
                    <i class="fa-solid fa-shield-halved me-1"></i> Standard Passing Rules
                </span>
            </div>
            <h2 class="fw-bold text-white mb-2">
                <i class="fa-solid fa-book-open text-emerald me-2" style="color:#34d399;"></i>Subjects Management & Curriculum
            </h2>
            <p class="text-white-50 mb-0">
                Define class subjects, set passing criteria, allocate total marks, and designate faculty teachers across school and academy programs.
            </p>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                <button class="btn btn-emerald fw-bold text-white px-3 py-2 shadow-sm rounded-3" style="background-color:#10b981; border:none;" data-bs-toggle="modal" data-bs-target="#subjectModal" onclick="resetForm()">
                    <i class="fa-solid fa-plus-circle me-1"></i> Add New Subject
                </button>
                <button class="btn btn-light fw-semibold px-3 py-2 shadow-sm rounded-3" onclick="exportSubjectsCSV()">
                    <i class="fa-solid fa-file-csv me-1 text-success"></i> Export CSV
                </button>
                <button class="btn btn-outline-light fw-semibold px-3 py-2 rounded-3" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Print Curriculum
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary text-white border-secondary px-3 py-2 rounded-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Executive KPI Metrics Bar -->
<div class="row g-3 mb-4 d-print-none">
    <!-- Card 1: Total Subjects -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-subject p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Total Subjects</span>
                    <h3 class="fw-bold text-dark mb-0"><?php echo $totalSubjects; ?></h3>
                    <small class="text-emerald fw-semibold" style="color:#059669;"><i class="fa-solid fa-book me-1"></i>Defined Subjects</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-emerald">
                    <i class="fa-solid fa-book-bookmark"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Active Subjects -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-subject p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Active Courses</span>
                    <h3 class="fw-bold text-blue mb-0" style="color:#2563eb;"><?php echo $activeCount; ?></h3>
                    <small class="text-blue fw-semibold" style="color:#2563eb;"><i class="fa-solid fa-circle-check me-1"></i>In Curriculum</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-blue">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Assigned Faculty -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-subject p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Assigned Faculty</span>
                    <h3 class="fw-bold text-purple mb-0" style="color:#7e22ce;"><?php echo $assignedFaculty; ?></h3>
                    <small class="text-muted fw-semibold"><?php echo $unassignedCount; ?> Unassigned</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-purple">
                    <i class="fa-solid fa-chalkboard-user"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: Classes Covered -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-subject p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Classes Covered</span>
                    <h3 class="fw-bold text-amber mb-0" style="color:#d97706;"><?php echo $classesCount; ?></h3>
                    <small class="text-amber fw-semibold" style="color:#d97706;"><i class="fa-solid fa-school me-1"></i>Academic Grades</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-amber">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 5: Passing Standard Index -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-subject p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Passing Ratio</span>
                    <h3 class="fw-bold text-rose mb-0" style="color:#e11d48;"><?php echo $avgPassRatio; ?>%</h3>
                    <small class="text-rose fw-semibold" style="color:#e11d48;"><i class="fa-solid fa-chart-line me-1"></i>Avg Pass Cutoff</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-rose">
                    <i class="fa-solid fa-percent"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 6: Academy Courses -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card kpi-card-subject p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Programs</span>
                    <h3 class="fw-bold text-secondary mb-0">School / Acad.</h3>
                    <small class="text-muted fw-semibold">Dual Curricula</small>
                </div>
                <div class="kpi-icon-wrapper bg-light text-secondary">
                    <i class="fa-solid fa-shapes"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs Navigation & Filter Bar -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:14px;">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <ul class="nav nav-pills nav-pills-custom gap-2" id="subjectTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-subjects-btn" data-bs-toggle="pill" data-bs-target="#tab-subjects" type="button">
                        <i class="fa-solid fa-list-check me-2"></i>Subject Directory
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-workload-btn" data-bs-toggle="pill" data-bs-target="#tab-workload" type="button">
                        <i class="fa-solid fa-user-gear me-2"></i>Faculty Workload Matrix
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-passing-btn" data-bs-toggle="pill" data-bs-target="#tab-passing" type="button">
                        <i class="fa-solid fa-sliders me-2"></i>Passing Parameters Audit
                    </button>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 250px;">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" id="liveSearchInput" class="form-control bg-light border-start-0" placeholder="Search subjects..." onkeyup="filterSubjectsTable()">
                </div>
            </div>
        </div>

        <hr class="my-3 text-muted opacity-25">

        <!-- Server Side Filter Form -->
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted">Filter by Class & Section</label>
                <select class="form-select form-select-sm" name="class_id" onchange="this.form.submit()">
                    <option value="0">All Classes & Sections</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Academic Program Type</label>
                <select class="form-select form-select-sm" name="academic_type" onchange="this.form.submit()">
                    <option value="">All Programs (School & Academy)</option>
                    <option value="School" <?php echo $selectedType === 'School' ? 'selected' : ''; ?>>School Standard</option>
                    <option value="Academy" <?php echo $selectedType === 'Academy' ? 'selected' : ''; ?>>Academy Program</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <a href="subjects.php" class="btn btn-light btn-sm w-100 py-1 fw-semibold text-muted" title="Reset Filters">
                    <i class="fa-solid fa-rotate me-1"></i> Reset Filters
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tab Content Container -->
<div class="tab-content" id="subjectTabContent">

    <!-- ── TAB 1: SUBJECT DIRECTORY ─────────────────────────────────── -->
    <div class="tab-pane fade show active" id="tab-subjects" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius:14px;" id="subjectPrintArea">
            
            <!-- Print Header -->
            <div class="card-header bg-white border-0 pt-4 px-4 d-none d-print-block text-center border-bottom pb-3">
                <h2 class="fw-bold text-dark mb-0">INDUS GRAMMAR SCHOOL</h2>
                <h5 class="text-emerald fw-bold mb-0" style="color:#059669;">Academic Curriculum Directory & Passing Parameters</h5>
                <p class="text-muted small mb-0">Class: <strong><?php echo htmlspecialchars($activeClassTitle); ?></strong> | Date: <?php echo date('d-M-Y'); ?></p>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-subject table-hover align-middle mb-0 table-print-clean" id="subjectMainTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th>Subject Code & Title</th>
                                <th>Class & Section</th>
                                <th class="text-center">Program Type</th>
                                <th class="text-end">Max Marks</th>
                                <th class="text-end">Passing Marks</th>
                                <th class="text-center">Pass % Ratio</th>
                                <th>Assigned Faculty Teacher</th>
                                <th class="text-center">Status</th>
                                <th class="text-end d-print-none" style="width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subjects)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <div class="py-4">
                                            <i class="fa-solid fa-book-open-reader text-muted fa-3x mb-3"></i>
                                            <h5 class="fw-bold text-dark">No Subjects Registered</h5>
                                            <p class="text-muted small mb-3">No academic subjects found matching your selected class criteria.</p>
                                            <button class="btn btn-emerald text-white btn-sm px-4 fw-bold" style="background-color:#10b981; border:none;" data-bs-toggle="modal" data-bs-target="#subjectModal" onclick="resetForm()">
                                                <i class="fa-solid fa-plus-circle me-1"></i> Register First Subject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: 
                                $idx = 1;
                                foreach ($subjects as $s): 
                                    $maxM = max(1, (float)$s['total_marks']);
                                    $passM = (float)$s['passing_marks'];
                                    $pct = round(($passM / $maxM) * 100, 1);
                                    $isInactive = ($s['status'] ?? 'Active') === 'Inactive';
                            ?>
                                <tr>
                                    <td class="text-center text-muted fw-bold small"><?php echo $idx++; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="kpi-icon-wrapper badge-soft-emerald" style="width:36px; height:36px; font-size:0.95rem;">
                                                <i class="fa-solid fa-book"></i>
                                            </div>
                                            <div>
                                                <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($s['subject_name']); ?></span>
                                                <span class="badge bg-light text-secondary border font-monospace px-2 py-0 text-xs">
                                                    <?php echo htmlspecialchars($s['subject_code'] ?: 'NO-CODE'); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1.5 rounded-pill fw-semibold">
                                            <i class="fa-solid fa-graduation-cap me-1"></i><?php echo htmlspecialchars($s['class_name'] . ' - ' . $s['section']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $s['academic_type'] === 'Academy' ? 'warning text-dark' : 'info text-white'; ?> px-3 py-1 rounded-pill fw-bold">
                                            <?php echo htmlspecialchars($s['academic_type']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold text-dark fs-6"><?php echo (int)$s['total_marks']; ?></td>
                                    <td class="text-end fw-bold text-danger fs-6"><?php echo (int)$s['passing_marks']; ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-2.5 py-1 fw-bold">
                                            <?php echo $pct; ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($s['teacher_id'])): ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="faculty-avatar">
                                                    <?php echo strtoupper(substr($s['teacher_first'] ?? 'T', 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <span class="fw-bold text-dark d-block small"><?php echo htmlspecialchars(($s['teacher_first'] ?? '') . ' ' . ($s['teacher_last'] ?? '')); ?></span>
                                                    <span class="text-muted text-xs"><i class="fa-solid fa-chalkboard-user text-primary me-1"></i>Faculty Member</span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-warning bg-opacity-15 text-warning border border-warning px-2.5 py-1 rounded-2 text-dark small">
                                                <i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i>Unassigned
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $isInactive ? 'secondary' : 'success'; ?> bg-opacity-15 text-<?php echo $isInactive ? 'secondary' : 'success'; ?> px-3 py-1 rounded-pill fw-semibold">
                                            <?php echo htmlspecialchars($s['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end d-print-none">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary rounded-pill px-2.5 me-1" title="Edit Subject" onclick='editSubject(<?php echo json_encode($s); ?>)'>
                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                            </button>
                                            <?php if (hasPermission('academic_manage')): ?>
                                                <button class="btn btn-outline-danger btn-delete rounded-pill px-2.5" title="Delete Subject" data-id="<?php echo $s['id']; ?>">
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
                            <span class="fw-bold text-dark small">Academic Supervisor</span>
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

    <!-- ── TAB 2: FACULTY WORKLOAD MATRIX ───────────────────────────── -->
    <div class="tab-pane fade" id="tab-workload" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius:14px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-user-gear me-2 text-primary"></i>Faculty Teaching Workload Matrix</h5>
                <p class="text-muted small mb-0">Distribution of subject teaching load per faculty member.</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Faculty Teacher Name</th>
                                <th class="text-center">Subjects Assigned</th>
                                <th>Assigned Subjects & Classes</th>
                                <th class="text-center">Total Max Marks Managed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Group subjects by teacher_id
                            $workloadMap = [];
                            foreach ($subjects as $s) {
                                $tid = $s['teacher_id'] ?: 0;
                                $name = !empty($s['teacher_first']) ? ($s['teacher_first'] . ' ' . $s['teacher_last']) : 'Unassigned Faculty';
                                $workloadMap[$tid]['name'] = $name;
                                $workloadMap[$tid]['items'][] = $s;
                            }
                            if (empty($workloadMap)):
                            ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No faculty assignments.</td></tr>
                            <?php else: foreach ($workloadMap as $tid => $data): 
                                $items = $data['items'];
                                $sumMarks = array_sum(array_column($items, 'total_marks'));
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="faculty-avatar <?php echo $tid === 0 ? 'bg-warning text-dark' : 'bg-primary text-white'; ?>">
                                                <?php echo strtoupper(substr($data['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($data['name']); ?></span>
                                                <span class="text-muted text-xs"><?php echo $tid === 0 ? 'Requires Teacher Designation' : 'Active Educator'; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center fw-bold">
                                        <span class="badge bg-primary rounded-pill px-3 py-1.5 fs-6"><?php echo count($items); ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($items as $it): ?>
                                                <span class="badge bg-light text-dark border p-2 text-start" style="font-size:11px;">
                                                    <i class="fa-solid fa-book-open text-emerald me-1" style="color:#059669;"></i>
                                                    <strong><?php echo htmlspecialchars($it['subject_name']); ?></strong> (<?php echo htmlspecialchars($it['class_name'] . ' - ' . $it['section']); ?>)
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="text-center fw-bold text-dark fs-6">
                                        <?php echo $sumMarks; ?> Marks
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TAB 3: PASSING PARAMETERS AUDIT ──────────────────────────── -->
    <div class="tab-pane fade" id="tab-passing" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius:14px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-sliders me-2 text-danger"></i>Passing Criteria & Academic Rigor Audit</h5>
                <p class="text-muted small mb-0">Audit passing parameters to ensure standard minimum pass percentages (e.g. 40%).</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Subject Title</th>
                                <th>Class & Section</th>
                                <th class="text-end">Max Marks</th>
                                <th class="text-end">Passing Marks</th>
                                <th class="text-center">Pass % Cutoff</th>
                                <th class="text-center">Rigor Assessment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subjects)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No data.</td></tr>
                            <?php else: foreach ($subjects as $s): 
                                $maxM = max(1, (float)$s['total_marks']);
                                $passM = (float)$s['passing_marks'];
                                $pct = round(($passM / $maxM) * 100, 1);
                            ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($s['subject_name']); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($s['class_name'] . ' - ' . $s['section']); ?></span></td>
                                    <td class="text-end fw-bold"><?php echo (int)$s['total_marks']; ?></td>
                                    <td class="text-end fw-bold text-danger"><?php echo (int)$s['passing_marks']; ?></td>
                                    <td class="text-center fw-bold fs-6">
                                        <span class="badge bg-<?php echo $pct >= 50 ? 'danger' : ($pct >= 40 ? 'success' : 'warning text-dark'); ?> px-3 py-1">
                                            <?php echo $pct; ?>%
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($pct >= 50): ?>
                                            <span class="badge bg-danger bg-opacity-15 text-danger px-3 py-1 rounded-pill"><i class="fa-solid fa-fire me-1"></i>High Rigor (Strict)</span>
                                        <?php elseif ($pct >= 40): ?>
                                            <span class="badge bg-success bg-opacity-15 text-success px-3 py-1 rounded-pill"><i class="fa-solid fa-check me-1"></i>Standard Threshold</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning bg-opacity-15 text-warning px-3 py-1 rounded-pill text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i>Low Threshold</span>
                                        <?php endif; ?>
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

<!-- Modal Form: Add / Edit Subject -->
<div class="modal fade" id="subjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-0 pt-4 px-4 bg-light rounded-top">
                <h5 class="modal-title fw-bold text-dark" id="modalTitle">
                    <i class="fa-solid fa-book-open me-2 text-emerald" style="color:#10b981;"></i>Define Class Subject
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="subjectForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_subject">
                    <input type="hidden" name="subject_id" id="subjectId" value="0">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Class & Section *</label>
                            <select class="form-select" name="class_id" id="subjectClassId" required>
                                <option value="">-- Choose Target Class --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Academic Program</label>
                            <select class="form-select" name="academic_type" id="subjectAcademicType">
                                <option value="School">School Standard</option>
                                <option value="Academy">Academy Program</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Subject Title *</label>
                            <input type="text" class="form-control" name="subject_name" id="subjectName" required placeholder="e.g. English Literature, Mathematics">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Subject Code</label>
                            <input type="text" class="form-control" name="subject_code" id="subjectCode" placeholder="e.g. ENG-101, MATH-20">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Maximum Total Marks *</label>
                            <input type="number" class="form-control" name="total_marks" id="subjectMaxMarks" required min="1" value="100" oninput="calcPassRatio()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Passing Threshold Marks *</label>
                            <input type="number" class="form-control" name="passing_marks" id="subjectPassingMarks" required min="1" value="40" oninput="calcPassRatio()">
                        </div>
                    </div>

                    <!-- Ratio Banner -->
                    <div class="alert alert-light border d-flex align-items-center justify-content-between p-2.5 mb-3" style="border-radius:10px;">
                        <span class="small text-muted"><i class="fa-solid fa-calculator me-1 text-primary"></i>Calculated Passing Percentage:</span>
                        <span class="fw-bold text-dark small" id="calcRatioBadge">40.0% Minimum Required</span>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Designated Faculty Teacher</label>
                            <select class="form-select" name="teacher_id" id="subjectTeacherId">
                                <option value="0">-- Unassigned (Select Faculty) --</option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?php echo $t['id']; ?>">
                                        <?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name'] . ' (' . ($t['designation'] ?: 'Teacher') . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Status</label>
                            <select class="form-select" name="status" id="subjectStatus">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 bg-light rounded-bottom">
                <button type="button" class="btn btn-outline-secondary px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="subjectForm" class="btn btn-emerald text-white px-4 fw-bold" style="background-color:#10b981; border:none;" id="btnSave">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Subject
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="subjectToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="subjectToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
const modalObj = new bootstrap.Modal(document.getElementById("subjectModal"));

function showToast(msg, ok) {
    const t = document.getElementById("subjectToast");
    const m = document.getElementById("subjectToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function calcPassRatio() {
    const maxM = parseFloat(document.getElementById("subjectMaxMarks").value) || 100;
    const passM = parseFloat(document.getElementById("subjectPassingMarks").value) || 40;
    const pct = ((passM / (maxM || 1)) * 100).toFixed(1);
    document.getElementById("calcRatioBadge").textContent = `${pct}% Minimum Required`;
}

function resetForm() {
    document.getElementById("subjectForm").reset();
    document.getElementById("subjectId").value = "0";
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-book-open me-2 text-emerald" style="color:#10b981;"></i>Define Class Subject\';
    document.getElementById("btnSave").innerHTML = \'<i class="fa-solid fa-floppy-disk me-1"></i> Save Subject\';
    calcPassRatio();
}

function editSubject(data) {
    resetForm();
    document.getElementById("subjectId").value = data.id;
    document.getElementById("subjectClassId").value = data.class_id;
    document.getElementById("subjectName").value = data.subject_name;
    document.getElementById("subjectCode").value = data.subject_code;
    document.getElementById("subjectMaxMarks").value = data.total_marks;
    document.getElementById("subjectPassingMarks").value = data.passing_marks;
    document.getElementById("subjectAcademicType").value = data.academic_type;
    document.getElementById("subjectTeacherId").value = data.teacher_id ? data.teacher_id : "0";
    document.getElementById("subjectStatus").value = data.status;
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit Subject Details\';
    document.getElementById("btnSave").innerHTML = \'<i class="fa-solid fa-rotate me-1"></i> Update Subject\';
    calcPassRatio();
    modalObj.show();
}

function filterSubjectsTable() {
    const query = document.getElementById("liveSearchInput").value.toLowerCase();
    const rows = document.querySelectorAll("#subjectMainTable tbody tr");
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = text.includes(query) ? "" : "none";
    });
}

function exportSubjectsCSV() {
    let csv = [];
    let rows = document.querySelectorAll("#subjectMainTable tr");
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
    downloadLink.download = "curriculum_subjects_" + new Date().toISOString().slice(0,10) + ".csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

document.addEventListener("DOMContentLoaded", function() {
    calcPassRatio();

    // Form Save
    const form = document.getElementById("subjectForm");
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
                        btn.innerHTML = document.getElementById("subjectId").value !== "0" ? \'<i class="fa-solid fa-rotate me-1"></i> Update Subject\' : \'<i class="fa-solid fa-floppy-disk me-1"></i> Save Subject\';
                    }
                })
                .catch(() => {
                    showToast("System connection error.", false);
                    btn.disabled = false; 
                    btn.innerHTML = \'<i class="fa-solid fa-floppy-disk me-1"></i> Save Subject\';
                });
        });
    }

    // Delete Trigger
    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this subject? Result records linked to this subject will be purged!")) return;
            const id = this.dataset.id;
            this.disabled = true;

            const fd = new FormData();
            fd.append("action", "delete_subject");
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
