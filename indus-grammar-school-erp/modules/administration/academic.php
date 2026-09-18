<?php
/**
 * Indus Grammar School ERP - Academic Settings Tabbed Portal
 * Version 7.0.0 — Commercial Redesign (Sessions, Classes, Subjects, Departments & Houses)
 */

$pageTitle = 'Academic Settings';
$breadcrumbActive = 'Academic Settings';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

$db = Database::getConnection();

// Fetch Sessions
$sessions = [];
try {
    $sessions = $db->query("SELECT * FROM academic_sessions ORDER BY session_name DESC")->fetchAll();
} catch (Exception $e) {}

// Fetch Classes & Sections
$classes = [];
try {
    $classes = $db->query("SELECT * FROM classes ORDER BY class_name ASC, section ASC")->fetchAll();
} catch (Exception $e) {}

// Fetch Subjects
$subjects = [];
try {
    $subjects = $db->query("SELECT s.*, c.class_name, c.section FROM subjects s JOIN classes c ON s.class_id = c.id ORDER BY c.class_name ASC, s.subject_name ASC")->fetchAll();
} catch (Exception $e) {}

// Fetch Departments
$departments = [];
try {
    $departments = $db->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}

// Fetch School Houses
$houses = [];
try {
    $houses = $db->query("SELECT * FROM school_houses ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}
?>

<!-- Custom Styling for Academic Settings Module -->
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
    --success-color: #16A34A;
    --warning-color: #D97706;
    --danger-color: #DC2626;
    --purple-color: #7C3AED;
}

/* Page Outer Canvas */
.academic-page-container {
    background-color: var(--page-background);
    border-radius: 18px;
    padding: 1.5rem;
    min-height: calc(100vh - 100px);
}

/* Hero Header Banner (Navy/Royal Blue Gradient) */
.academic-hero-banner {
    background: linear-gradient(135deg, var(--primary-navy) 0%, #1e3a8a 55%, var(--secondary-blue) 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.18);
    position: relative;
    overflow: hidden;
}

.academic-hero-banner::after {
    content: '';
    position: absolute;
    right: -40px;
    bottom: -40px;
    width: 240px;
    height: 240px;
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

/* KPI Summary Cards */
.academic-kpi-card {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.35rem 1.5rem;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.academic-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -6px rgba(15, 23, 42, 0.09);
    border-color: #cbd5e1;
}

.kpi-accent-amber  { border-top: 4px solid var(--warning-color); }
.kpi-accent-blue   { border-top: 4px solid var(--primary-blue); }
.kpi-accent-purple { border-top: 4px solid var(--purple-color); }
.kpi-accent-green  { border-top: 4px solid var(--success-color); }
.kpi-accent-red    { border-top: 4px solid var(--danger-color); }

.kpi-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.kpi-icon-amber  { background-color: #fffbeb; color: var(--warning-color); }
.kpi-icon-blue   { background-color: var(--light-blue); color: var(--primary-blue); }
.kpi-icon-purple { background-color: #f3e8ff; color: var(--purple-color); }
.kpi-icon-green  { background-color: #f0fdf4; color: var(--success-color); }
.kpi-icon-red    { background-color: #fef2f2; color: var(--danger-color); }

.kpi-number {
    font-size: 1.95rem;
    font-weight: 800;
    color: var(--primary-navy);
    line-height: 1.2;
    margin-top: 0.4rem;
    margin-bottom: 0.2rem;
}

.kpi-label-text {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--muted-text);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Glass Card & Tab Navigation Styling */
.glass-panel {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.academic-tabs .nav-link {
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--muted-text);
    padding: 1rem 1.25rem;
    border: none;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
    background: transparent;
}

.academic-tabs .nav-link:hover {
    color: var(--primary-blue);
    border-bottom-color: #cbd5e1;
}

.academic-tabs .nav-link.active {
    color: var(--primary-blue);
    background: #ffffff;
    border-bottom: 3px solid var(--primary-blue);
}

.academic-table th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.95rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
}

.academic-table td {
    padding: 0.95rem 1.25rem;
    vertical-align: middle;
    font-size: 0.88rem;
    color: var(--main-text);
    border-bottom: 1px solid #f1f5f9;
}

.academic-table tr:last-child td {
    border-bottom: none;
}

.code-pill {
    background-color: #f1f5f9;
    color: #334155;
    font-family: var(--bs-font-monospace);
    font-size: 0.8rem;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}
</style>

<div class="academic-page-container">

    <!-- 1. Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item text-muted">Administration</li>
            <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Academic Settings</li>
        </ol>
    </nav>

    <!-- 2. Hero Header Banner -->
    <div class="academic-hero-banner mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h2 class="fw-bold mb-0 text-white fs-3">Academic Settings</h2>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">Academic Governance</span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">Configure academic sessions, register classes, sections, subjects, staff departments, and school houses.</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="<?php echo APP_URL; ?>/modules/administration/settings.php" class="btn btn-light btn-sm fw-bold shadow-sm rounded-3 text-primary px-3 py-2">
                    <i class="fa-solid fa-sliders me-1"></i> School Settings
                </a>
            </div>
        </div>
    </div>

    <!-- 3. Real MySQL KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <!-- Sessions Count -->
        <div class="col-sm-6 col-lg-3">
            <div class="academic-kpi-card kpi-accent-amber">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Academic Sessions</span>
                    <div class="kpi-icon kpi-icon-amber">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format(count($sessions)); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-circle-check text-warning"></i>
                        <span>Sessions registered</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Classes & Sections -->
        <div class="col-sm-6 col-lg-3">
            <div class="academic-kpi-card kpi-accent-blue">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Classes & Sections</span>
                    <div class="kpi-icon kpi-icon-blue">
                        <i class="fa-solid fa-school"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format(count($classes)); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-layer-group text-primary"></i>
                        <span>Class section units</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subjects Registry -->
        <div class="col-sm-6 col-lg-3">
            <div class="academic-kpi-card kpi-accent-purple">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Curriculum Subjects</span>
                    <div class="kpi-icon kpi-icon-purple">
                        <i class="fa-solid fa-book"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format(count($subjects)); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-book-open text-purple"></i>
                        <span>Assigned subjects</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Departments & Houses -->
        <div class="col-sm-6 col-lg-3">
            <div class="academic-kpi-card kpi-accent-green">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Dept & Houses</span>
                    <div class="kpi-icon kpi-icon-green">
                        <i class="fa-solid fa-building-user"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format(count($departments) + count($houses)); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-house-flag text-success"></i>
                        <span><?php echo count($departments); ?> Depts / <?php echo count($houses); ?> Houses</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Tab Navigation Header -->
    <div class="glass-panel mb-4">
        <div class="border-bottom bg-light px-2 pt-2">
            <ul class="nav nav-tabs nav-fill academic-tabs border-0" id="academicTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="sessions-tab" data-bs-toggle="tab" data-bs-target="#sessionsPane" type="button" role="tab">
                        <i class="fa-solid fa-calendar-days me-2"></i>Academic Sessions
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="classes-tab" data-bs-toggle="tab" data-bs-target="#classesPane" type="button" role="tab">
                        <i class="fa-solid fa-school me-2"></i>Classes & Sections
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#subjectsPane" type="button" role="tab">
                        <i class="fa-solid fa-book me-2"></i>Subjects Directory
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="departments-tab" data-bs-toggle="tab" data-bs-target="#departmentsPane" type="button" role="tab">
                        <i class="fa-solid fa-building-user me-2"></i>Staff Departments
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="houses-tab" data-bs-toggle="tab" data-bs-target="#housesPane" type="button" role="tab">
                        <i class="fa-solid fa-house-flag me-2"></i>School Houses
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- 5. Tab Content Panes -->
    <div class="tab-content" id="academicTabsContent">

        <!-- TAB 1: ACADEMIC SESSIONS -->
        <div class="tab-pane fade show active" id="sessionsPane" role="tabpanel">
            <div class="glass-panel">
                <div class="p-4 border-bottom d-flex align-items-center justify-content-between bg-white">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark fs-6 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-calendar-days text-primary"></i> Registered Academic Sessions
                        </h5>
                        <p class="text-muted small mb-0 mt-0.5">Manage operational academic years and mark the currently active session.</p>
                    </div>
                    <button class="btn btn-primary btn-sm fw-bold px-3 rounded-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addSessionModal">
                        <i class="fa-solid fa-plus-circle me-1"></i> Add Academic Session
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table academic-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="80">ID</th>
                                <th>Session Name</th>
                                <th>Active Status</th>
                                <th class="text-end" width="220">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($sessions)): ?>
                                <?php foreach ($sessions as $s): ?>
                                    <tr>
                                        <td class="fw-bold text-muted">#<?php echo $s['id']; ?></td>
                                        <td class="fw-bold text-dark fs-6"><?php echo sanitize($s['session_name']); ?></td>
                                        <td>
                                            <?php if ($s['is_active']): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill fw-bold">
                                                    <i class="fa-solid fa-circle-check me-1"></i> Active Session
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary px-3 py-1.5 rounded-pill fw-semibold">
                                                    Inactive Session
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if (!$s['is_active']): ?>
                                                <button class="btn btn-sm btn-outline-success fw-semibold btn-activate-session me-1 rounded-2" data-id="<?php echo $s['id']; ?>" title="Set as Active Session">
                                                    <i class="fa-solid fa-toggle-on me-1"></i> Set Active
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger btn-delete-session rounded-2" data-id="<?php echo $s['id']; ?>" title="Delete Session">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border px-3 py-1.5 rounded-pill">Current System Active</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No academic sessions found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: CLASSES & SECTIONS -->
        <div class="tab-pane fade" id="classesPane" role="tabpanel">
            <div class="glass-panel">
                <div class="p-4 border-bottom d-flex align-items-center justify-content-between bg-white">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark fs-6 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-school text-primary"></i> Registered Classes & Section Codes
                        </h5>
                        <p class="text-muted small mb-0 mt-0.5">Register class levels and section combinations for student enrollment.</p>
                    </div>
                    <button class="btn btn-primary btn-sm fw-bold px-3 rounded-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addClassModal">
                        <i class="fa-solid fa-plus-circle me-1"></i> Add Class & Section
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table academic-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="80">ID</th>
                                <th>Class Title</th>
                                <th>Section Code</th>
                                <th class="text-end" width="160">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($classes)): ?>
                                <?php foreach ($classes as $c): ?>
                                    <tr>
                                        <td class="fw-bold text-muted">#<?php echo $c['id']; ?></td>
                                        <td class="fw-bold text-dark fs-6"><?php echo sanitize($c['class_name']); ?></td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill fw-bold">
                                                Section <?php echo sanitize($c['section']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-danger btn-delete-class rounded-2" data-id="<?php echo $c['id']; ?>" title="Delete Class Section">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No classes or sections registered yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 3: SUBJECTS DIRECTORY -->
        <div class="tab-pane fade" id="subjectsPane" role="tabpanel">
            <div class="glass-panel">
                <div class="p-4 border-bottom d-flex align-items-center justify-content-between bg-white">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark fs-6 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-book text-primary"></i> Academic Curriculum Subjects Directory
                        </h5>
                        <p class="text-muted small mb-0 mt-0.5">Register course subjects assigned to specific class sections with total marks.</p>
                    </div>
                    <button class="btn btn-primary btn-sm fw-bold px-3 rounded-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                        <i class="fa-solid fa-plus-circle me-1"></i> Register Subject
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table academic-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="80">ID</th>
                                <th>Subject Code</th>
                                <th>Subject Title</th>
                                <th>Assigned Class Section</th>
                                <th class="text-center" width="140">Total Marks</th>
                                <th class="text-end" width="160">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($subjects)): ?>
                                <?php foreach ($subjects as $sb): ?>
                                    <tr>
                                        <td class="fw-bold text-muted">#<?php echo $sb['id']; ?></td>
                                        <td><span class="code-pill"><?php echo sanitize($sb['subject_code']); ?></span></td>
                                        <td class="fw-bold text-dark fs-6"><?php echo sanitize($sb['subject_name']); ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-3 py-1.5 rounded-pill fw-semibold">
                                                <?php echo sanitize($sb['class_name'] . ' (' . $sb['section'] . ')'); ?>
                                            </span>
                                        </td>
                                        <td class="text-center fw-bold text-primary"><?php echo (int)$sb['total_marks']; ?></td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-danger btn-delete-subject rounded-2" data-id="<?php echo $sb['id']; ?>" title="Delete Subject">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No curriculum subjects registered yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 4: STAFF DEPARTMENTS -->
        <div class="tab-pane fade" id="departmentsPane" role="tabpanel">
            <div class="glass-panel">
                <div class="p-4 border-bottom d-flex align-items-center justify-content-between bg-white">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark fs-6 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-building-user text-primary"></i> Staff Operational Departments
                        </h5>
                        <p class="text-muted small mb-0 mt-0.5">Define faculty and administrative staff departments.</p>
                    </div>
                    <button class="btn btn-primary btn-sm fw-bold px-3 rounded-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                        <i class="fa-solid fa-plus-circle me-1"></i> Add Department
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table academic-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="80">ID</th>
                                <th>Department Name</th>
                                <th>Department Code</th>
                                <th>Status</th>
                                <th class="text-end" width="160">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($departments)): ?>
                                <?php foreach ($departments as $d): ?>
                                    <tr>
                                        <td class="fw-bold text-muted">#<?php echo $d['id']; ?></td>
                                        <td class="fw-bold text-dark fs-6"><?php echo sanitize($d['name']); ?></td>
                                        <td><span class="code-pill"><?php echo sanitize($d['code']); ?></span></td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill fw-bold">
                                                <?php echo sanitize($d['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-danger btn-delete-department rounded-2" data-id="<?php echo $d['id']; ?>" title="Delete Department">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No staff departments configured.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 5: SCHOOL HOUSES -->
        <div class="tab-pane fade" id="housesPane" role="tabpanel">
            <div class="glass-panel">
                <div class="p-4 border-bottom d-flex align-items-center justify-content-between bg-white">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark fs-6 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-house-flag text-primary"></i> Student School Houses
                        </h5>
                        <p class="text-muted small mb-0 mt-0.5">Register sports and extracurricular school house teams with custom color themes.</p>
                    </div>
                    <button class="btn btn-primary btn-sm fw-bold px-3 rounded-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addHouseModal">
                        <i class="fa-solid fa-plus-circle me-1"></i> Add School House
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table academic-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="80">ID</th>
                                <th>House Title</th>
                                <th>Color Theme</th>
                                <th>Status</th>
                                <th class="text-end" width="160">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($houses)): ?>
                                <?php foreach ($houses as $h): ?>
                                    <tr>
                                        <td class="fw-bold text-muted">#<?php echo $h['id']; ?></td>
                                        <td class="fw-bold text-dark fs-6"><?php echo sanitize($h['name']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle border shadow-sm" style="width: 24px; height: 24px; background-color: <?php echo htmlspecialchars($h['house_color']); ?>;"></div>
                                                <span class="code-pill"><?php echo sanitize($h['house_color']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill fw-bold">
                                                <?php echo sanitize($h['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-danger btn-delete-house rounded-2" data-id="<?php echo $h['id']; ?>" title="Delete House">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No school houses registered yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- ─────────────────────────────────────────────────────────────────────────
     MODALS
     ───────────────────────────────────────────────────────────────────────── -->

<!-- Add Session Modal -->
<div class="modal fade" id="addSessionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light" style="border-top-left-radius:16px; border-top-right-radius:16px;">
                <h5 class="modal-title fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-calendar-plus text-primary"></i> Add Academic Session
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="addSessionForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_session">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Session Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="session_name" placeholder="e.g. 2027-2028" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top bg-light px-4 py-3" style="border-bottom-left-radius:16px; border-bottom-right-radius:16px;">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addSessionForm" class="btn btn-sm btn-primary px-4 fw-bold">Create Session</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light" style="border-top-left-radius:16px; border-top-right-radius:16px;">
                <h5 class="modal-title fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-school text-primary"></i> Add Class & Section
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="addClassForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_class">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Class Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="class_name" placeholder="e.g. Class 6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Section Letter/Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="section" value="A" required placeholder="e.g. A, B, Blue">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top bg-light px-4 py-3" style="border-bottom-left-radius:16px; border-bottom-right-radius:16px;">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addClassForm" class="btn btn-sm btn-primary px-4 fw-bold">Register Class</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light" style="border-top-left-radius:16px; border-top-right-radius:16px;">
                <h5 class="modal-title fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-book text-primary"></i> Register Curriculum Subject
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="addSubjectForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_subject">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Subject Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="subject_code" placeholder="e.g. ENG-101" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Subject Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="subject_name" placeholder="e.g. English Grammar" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Target Class Section <span class="text-danger">*</span></label>
                            <select class="form-select" name="class_id" required>
                                <?php foreach ($classes as $cl): ?>
                                    <option value="<?php echo $cl['id']; ?>"><?php echo sanitize($cl['class_name'] . ' (' . $cl['section'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Total Exam Marks <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="total_marks" value="100" min="1" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top bg-light px-4 py-3" style="border-bottom-left-radius:16px; border-bottom-right-radius:16px;">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addSubjectForm" class="btn btn-sm btn-primary px-4 fw-bold">Register Subject</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light" style="border-top-left-radius:16px; border-top-right-radius:16px;">
                <h5 class="modal-title fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-building-user text-primary"></i> Add Staff Department
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="addDepartmentForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_department">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Science Department" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Department Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="code" placeholder="e.g. SCI" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top bg-light px-4 py-3" style="border-bottom-left-radius:16px; border-bottom-right-radius:16px;">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addDepartmentForm" class="btn btn-sm btn-primary px-4 fw-bold">Create Dept</button>
            </div>
        </div>
    </div>
</div>

<!-- Add House Modal -->
<div class="modal fade" id="addHouseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light" style="border-top-left-radius:16px; border-top-right-radius:16px;">
                <h5 class="modal-title fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-house-flag text-primary"></i> Add School House
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="addHouseForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_house">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">House Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Iqbal House" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">House Color Theme</label>
                        <input type="color" class="form-control form-control-color w-100" name="house_color" value="#0d6efd">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top bg-light px-4 py-3" style="border-bottom-left-radius:16px; border-bottom-right-radius:16px;">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addHouseForm" class="btn btn-sm btn-primary px-4 fw-bold">Add House</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="acadToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="acadToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("acadToast");
    const m = document.getElementById("acadToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function handleFormSubmit(formId, modalId) {
    const form = document.getElementById(formId);
    if(form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            fetch("../../ajax/admin.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
                        setTimeout(() => location.reload(), 900);
                    }
                })
                .catch(() => showToast("Network connection error.", false));
        });
    }
}

function handleDeleteAction(buttonClass, actionName) {
    document.querySelectorAll("." + buttonClass).forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this academic entity? This action is permanent and may fail if linked to existing records.")) return;
            const id = this.dataset.id;
            const fd = new FormData();
            fd.append("action", actionName);
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/admin.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 900);
                });
        });
    });
}

document.addEventListener("DOMContentLoaded", function() {
    // Session Active Toggle
    document.querySelectorAll(".btn-activate-session").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            const fd = new FormData();
            fd.append("action", "toggle_session");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/admin.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 900);
                });
        });
    });

    // Form Submissions
    handleFormSubmit("addSessionForm", "addSessionModal");
    handleFormSubmit("addClassForm", "addClassModal");
    handleFormSubmit("addSubjectForm", "addSubjectModal");
    handleFormSubmit("addDepartmentForm", "addDepartmentModal");
    handleFormSubmit("addHouseForm", "addHouseModal");

    // Delete Operations
    handleDeleteAction("btn-delete-session", "delete_session");
    handleDeleteAction("btn-delete-class", "delete_class");
    handleDeleteAction("btn-delete-subject", "delete_subject");
    handleDeleteAction("btn-delete-department", "delete_department");
    handleDeleteAction("btn-delete-house", "delete_house");
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
