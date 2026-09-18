<?php
/**
 * Indus Grammar School ERP - Attendance Settings Submodule
 * Version 4.0.0 - Premium ERP Redesign & Complete Configuration Center
 */

// 1. Bootstrap App & Authorization Check (Admin Only)
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('system_settings'); // Strictly Admins

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

// 2. Fetch active settings row with fallback seeding
$settings = null;
try {
    $settings = $db->query("SELECT * FROM attendance_settings ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        // Fallback seed if table is completely empty
        $db->exec("
            INSERT INTO attendance_settings (school_start_time, late_arrival_time, working_days, weekend_days, default_status, allow_edit, lock_hours)
            VALUES ('08:00:00', '08:15:00', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', 'Sunday', 'Present', 1, 24)
        ");
        $settings = $db->query("SELECT * FROM attendance_settings ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = "Error loading settings database: " . $e->getMessage();
}

$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$workingDaysArray = explode(',', $settings['working_days'] ?? 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
$weekendDaysArray = explode(',', $settings['weekend_days'] ?? 'Sunday');

// 3. Handle POST request to Update Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    try {
        $school_start_time = sanitize($_POST['school_start_time'] ?? '08:00:00');
        $late_arrival_time = sanitize($_POST['late_arrival_time'] ?? '08:15:00');
        $default_status = sanitize($_POST['default_status'] ?? 'Present');
        $allow_edit = isset($_POST['allow_edit']) ? 1 : 0;
        $lock_hours = max(1, (int)($_POST['lock_hours'] ?? 24));

        $postedWorking = $_POST['working_days'] ?? [];
        $postedWeekend = $_POST['weekend_days'] ?? [];

        if (empty($postedWorking)) {
            throw new Exception("Please select at least one working day for the school calendar.");
        }

        $working_days_str = implode(',', array_map('sanitize', $postedWorking));
        $weekend_days_str = implode(',', array_map('sanitize', $postedWeekend));

        // Update settings in database
        $stmtUp = $db->prepare("
            UPDATE attendance_settings SET
                school_start_time = :school_start_time,
                late_arrival_time = :late_arrival_time,
                working_days = :working_days,
                weekend_days = :weekend_days,
                default_status = :default_status,
                allow_edit = :allow_edit,
                lock_hours = :lock_hours,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmtUp->execute([
            'school_start_time' => $school_start_time,
            'late_arrival_time' => $late_arrival_time,
            'working_days' => $working_days_str,
            'weekend_days' => $weekend_days_str,
            'default_status' => $default_status,
            'allow_edit' => $allow_edit,
            'lock_hours' => $lock_hours,
            'id' => $settings['id']
        ]);

        // Audit Log Entry
        try {
            $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([
                $_SESSION['user_id'] ?? null,
                'Attendance Settings Updated',
                'Updated global attendance rules, timings (' . $school_start_time . '), and working days.',
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $logEx) {
            // Non-critical audit logging fallback
        }

        $_SESSION['flash_success'] = "Attendance settings updated and saved successfully!";
        header("Location: settings.php");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 4. Load Layout Header
$pageTitle = 'Attendance Settings';
$breadcrumbActive = 'Attendance Settings';
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

/* Settings Summary Cards */
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

/* General Form Card Styling */
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

.adv-card-body {
    padding: 1.5rem;
}

/* Controls & Labels */
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

.form-check-input:checked {
    background-color: #2563eb;
    border-color: #2563eb;
}

/* Status Reference Badges */
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
.status-unmark  { background-color: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }

/* Sticky Footer Bar */
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
            <li class="breadcrumb-item"><a href="student.php">Attendance</a></li>
            <li class="breadcrumb-item active" aria-current="page">Attendance Settings</li>
        </ol>
    </nav>

    <!-- Hero Header Banner - Exact Match to Student Registration Header -->
    <div class="adv-hero-banner mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid fa-sliders"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold mb-0 text-white fs-3">Attendance Settings</h3>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">Configuration Center</span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">Configure attendance rules, status options, working days, and reporting preferences for the school ERP.</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <a href="student.php" class="btn btn-sm btn-light text-primary rounded-2 px-3 py-2 fw-semibold shadow-sm"><i class="fa-solid fa-clipboard-user me-1"></i>Mark Attendance</a>
                <a href="reports.php" class="btn btn-sm btn-outline-light rounded-2 px-3 py-2 fw-semibold"><i class="fa-solid fa-book me-1"></i>Attendance Register</a>
            </div>
        </div>
    </div>

    <!-- Alert Banners -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-center rounded-3 p-3" style="background-color: #f0fdf4; border-left: 4px solid #16a34a !important;">
            <i class="fa-solid fa-circle-check fs-5 text-success me-3"></i>
            <div>
                <strong class="text-success d-block">Success</strong>
                <span class="text-secondary small"><?php echo htmlspecialchars($message); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-center rounded-3 p-3" style="background-color: #fef2f2; border-left: 4px solid #dc2626 !important;">
            <i class="fa-solid fa-circle-xmark fs-5 text-danger me-3"></i>
            <div>
                <strong class="text-danger d-block">Configuration Error</strong>
                <span class="text-secondary small"><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- System Status Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="summary-mini-card">
                <div class="summary-mini-icon bg-primary-subtle text-primary">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block">System Status</span>
                    <h6 class="fw-bold text-dark mb-0 mt-1"><span class="badge bg-success text-white px-2 py-1 rounded-pill">Configured</span></h6>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-mini-card">
                <div class="summary-mini-icon bg-info-subtle text-info">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block">Last Updated</span>
                    <h6 class="fw-bold text-dark mb-0 mt-1 fs-7">
                        <?php echo !empty($settings['updated_at']) ? date('M d, Y - h:i A', strtotime($settings['updated_at'])) : 'Default Configuration'; ?>
                    </h6>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-mini-card">
                <div class="summary-mini-icon bg-warning-subtle text-warning">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block">Edit Lock Window</span>
                    <h6 class="fw-bold text-dark mb-0 mt-1"><?php echo htmlspecialchars($settings['lock_hours'] ?? '24'); ?> Hours</h6>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-mini-card">
                <div class="summary-mini-icon bg-success-subtle text-success">
                    <i class="fa-solid fa-user-gear"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block">Past Record Edit</span>
                    <h6 class="fw-bold text-dark mb-0 mt-1">
                        <?php echo (!empty($settings['allow_edit'])) ? '<span class="text-success"><i class="fa-solid fa-check me-1"></i>Allowed</span>' : '<span class="text-danger"><i class="fa-solid fa-xmark me-1"></i>Restricted</span>'; ?>
                    </h6>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Configuration Form -->
    <form id="attendanceSettingsForm" method="POST">
        <input type="hidden" name="action" value="save_settings">

        <div class="row">
            <!-- Left Column: Timings, Rules & General Scope -->
            <div class="col-lg-6">
                
                <!-- Card 1: School Timings & Attendance Thresholds -->
                <div class="adv-card">
                    <div class="adv-card-header">
                        <h5 class="adv-card-title">
                            <i class="fa-solid fa-clock"></i>School Timings & Thresholds
                        </h5>
                        <span class="badge bg-light text-secondary border px-2 py-1 small">Time Rules</span>
                    </div>
                    <div class="adv-card-body">
                        <div class="mb-3">
                            <label class="adv-label">School Start Time <span class="adv-required">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="fa-regular fa-clock"></i></span>
                                <input type="time" class="form-control adv-control" name="school_start_time" value="<?php echo htmlspecialchars($settings['school_start_time'] ?? '08:00:00'); ?>" required>
                            </div>
                            <span class="form-text text-muted small">Standard official start time for morning school assembly & attendance.</span>
                        </div>

                        <div class="mb-3">
                            <label class="adv-label">Late Arrival Threshold <span class="adv-required">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-warning"><i class="fa-solid fa-user-clock"></i></span>
                                <input type="time" class="form-control adv-control" name="late_arrival_time" value="<?php echo htmlspecialchars($settings['late_arrival_time'] ?? '08:15:00'); ?>" required>
                            </div>
                            <span class="form-text text-muted small">Arrivals logged after this timestamp will be automatically tagged as <strong>Late</strong>.</span>
                        </div>

                        <div class="mb-0">
                            <label class="adv-label">Default Attendance Status <span class="adv-required">*</span></label>
                            <select class="form-select adv-select" name="default_status" required>
                                <option value="Present" <?php echo (($settings['default_status'] ?? '') === 'Present') ? 'selected' : ''; ?>>Present (Default for unmarked students)</option>
                                <option value="Absent" <?php echo (($settings['default_status'] ?? '') === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                                <option value="Late" <?php echo (($settings['default_status'] ?? '') === 'Late') ? 'selected' : ''; ?>>Late</option>
                                <option value="Leave" <?php echo (($settings['default_status'] ?? '') === 'Leave') ? 'selected' : ''; ?>>Leave</option>
                            </select>
                            <span class="form-text text-muted small">Pre-selected status when initializing new daily attendance mark sheets.</span>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Permissions & Lock Policies -->
                <div class="adv-card">
                    <div class="adv-card-header">
                        <h5 class="adv-card-title">
                            <i class="fa-solid fa-user-shield"></i>Permissions & Edit Locks
                        </h5>
                        <span class="badge bg-light text-secondary border px-2 py-1 small">Security</span>
                    </div>
                    <div class="adv-card-body">
                        <div class="mb-4 p-3 bg-light rounded-3 border">
                            <div class="form-check form-switch d-flex align-items-center justify-content-between ps-0">
                                <div>
                                    <label class="form-check-label fw-bold text-dark d-block mb-1" for="allowEditSwitch">
                                        <i class="fa-solid fa-pen-to-square text-primary me-2"></i>Allow Past Attendance Editing
                                    </label>
                                    <span class="text-muted small d-block">When enabled, authorized staff can modify attendance records for past dates.</span>
                                </div>
                                <input class="form-check-input ms-3 fs-5" type="checkbox" role="switch" name="allow_edit" id="allowEditSwitch" <?php echo (!empty($settings['allow_edit'])) ? 'checked' : ''; ?>>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="adv-label">Attendance Lock Window (Hours) <span class="adv-required">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-hourglass-half"></i></span>
                                <input type="number" class="form-control adv-control" name="lock_hours" value="<?php echo htmlspecialchars($settings['lock_hours'] ?? 24); ?>" min="1" max="720" required>
                                <span class="input-group-text bg-light text-secondary fw-semibold">Hours</span>
                            </div>
                            <span class="form-text text-muted small">Time window after submission during which regular teachers can edit records before hard lock.</span>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Report Preferences & Data Integrity -->
                <div class="adv-card">
                    <div class="adv-card-header">
                        <h5 class="adv-card-title">
                            <i class="fa-solid fa-sliders"></i>Report Preferences & Integrity
                        </h5>
                        <span class="badge bg-light text-secondary border px-2 py-1 small">Preferences</span>
                    </div>
                    <div class="adv-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="adv-label">Default Report Sorting</label>
                                <input type="text" class="form-control adv-control bg-light" value="Roll Number / Student Name" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="adv-label">Duplicate Record Guard</label>
                                <input type="text" class="form-control adv-control bg-light text-success fw-bold" value="Strictly Enforced (UNIQUE)" readonly>
                            </div>
                            <div class="col-12">
                                <div class="p-3 rounded-3 bg-primary-subtle text-primary border border-primary-subtle d-flex align-items-center gap-3">
                                    <i class="fa-solid fa-circle-info fs-4 flex-shrink-0"></i>
                                    <div class="small">
                                        <strong>Multi-Campus & Academic Type Support:</strong>
                                        Attendance configuration applies seamlessly across all 3 active campuses (Main Campus, City Campus, Boys Campus) and both Academic Types (School & Academy).
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Calendar Working Days & Status Reference -->
            <div class="col-lg-6">

                <!-- Card 4: School Working Days & Calendar -->
                <div class="adv-card">
                    <div class="adv-card-header">
                        <h5 class="adv-card-title">
                            <i class="fa-solid fa-calendar-days"></i>Working Days & School Calendar
                        </h5>
                        <span class="badge bg-light text-secondary border px-2 py-1 small">Calendar</span>
                    </div>
                    <div class="adv-card-body">
                        <div class="mb-4">
                            <label class="adv-label mb-2 d-block"><i class="fa-solid fa-briefcase text-primary me-2"></i>Official Working Days <span class="adv-required">*</span></label>
                            <div class="row g-2">
                                <?php foreach ($daysOfWeek as $day): ?>
                                    <div class="col-6 col-sm-4">
                                        <div class="form-check p-2 border rounded-3 bg-light hover-shadow transition">
                                            <input class="form-check-input ms-1 me-2" type="checkbox" name="working_days[]" value="<?php echo $day; ?>" id="work_<?php echo $day; ?>" <?php echo in_array($day, $workingDaysArray) ? 'checked' : ''; ?>>
                                            <label class="form-check-label text-dark small fw-semibold" for="work_<?php echo $day; ?>"><?php echo $day; ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <span class="form-text text-muted small d-block mt-2">Days when daily student attendance marking is active and counted in percentage.</span>
                        </div>

                        <div class="mb-0 border-top pt-3">
                            <label class="adv-label mb-2 d-block"><i class="fa-solid fa-umbrella-beach text-warning me-2"></i>Weekend / Non-Academic Off Days</label>
                            <div class="row g-2">
                                <?php foreach ($daysOfWeek as $day): ?>
                                    <div class="col-6 col-sm-4">
                                        <div class="form-check p-2 border rounded-3 bg-light">
                                            <input class="form-check-input ms-1 me-2" type="checkbox" name="weekend_days[]" value="<?php echo $day; ?>" id="week_<?php echo $day; ?>" <?php echo in_array($day, $weekendDaysArray) ? 'checked' : ''; ?>>
                                            <label class="form-check-label text-dark small fw-semibold" for="week_<?php echo $day; ?>"><?php echo $day; ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <span class="form-text text-muted small d-block mt-2">Designated off days (e.g. Sunday) are excluded from missing attendance warnings.</span>
                        </div>
                    </div>
                </div>

                <!-- Card 5: Attendance Status Options & System Rules -->
                <div class="adv-card">
                    <div class="adv-card-header">
                        <h5 class="adv-card-title">
                            <i class="fa-solid fa-tags"></i>Attendance Status Options & System Rules
                        </h5>
                        <span class="badge bg-light text-secondary border px-2 py-1 small">Read-Only Core</span>
                    </div>
                    <div class="adv-card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Status</th>
                                        <th>Code</th>
                                        <th>System Color</th>
                                        <th class="pe-3">Calculation Impact</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="ps-3">
                                            <span class="status-pill status-present"><i class="fa-solid fa-circle-check"></i>Present</span>
                                        </td>
                                        <td><code class="fw-bold text-success">P</code></td>
                                        <td><span class="badge" style="background:#16a34a;">Green #16A34A</span></td>
                                        <td class="pe-3 text-muted">Counted as Attended (100% weight)</td>
                                    </tr>
                                    <tr>
                                        <td class="ps-3">
                                            <span class="status-pill status-absent"><i class="fa-solid fa-circle-xmark"></i>Absent</span>
                                        </td>
                                        <td><code class="fw-bold text-danger">A</code></td>
                                        <td><span class="badge" style="background:#dc2626;">Red #DC2626</span></td>
                                        <td class="pe-3 text-muted">Counted as Unattended (0% weight)</td>
                                    </tr>
                                    <tr>
                                        <td class="ps-3">
                                            <span class="status-pill status-late"><i class="fa-solid fa-clock"></i>Late</span>
                                        </td>
                                        <td><code class="fw-bold text-warning">L</code></td>
                                        <td><span class="badge" style="background:#d97706;">Amber #D97706</span></td>
                                        <td class="pe-3 text-muted">Counted as Attended with late flag</td>
                                    </tr>
                                    <tr>
                                        <td class="ps-3">
                                            <span class="status-pill status-leave"><i class="fa-solid fa-envelope-open-text"></i>Leave</span>
                                        </td>
                                        <td><code class="fw-bold text-primary">LV</code></td>
                                        <td><span class="badge" style="background:#7c3aed;">Purple #7C3AED</span></td>
                                        <td class="pe-3 text-muted">Excused absence (Excluded/Excused)</td>
                                    </tr>
                                    <tr>
                                        <td class="ps-3">
                                            <span class="status-pill status-unmark"><i class="fa-solid fa-minus"></i>Not Marked</span>
                                        </td>
                                        <td><code class="fw-bold text-secondary">N/M</code></td>
                                        <td><span class="badge" style="background:#64748b;">Gray #64748B</span></td>
                                        <td class="pe-3 text-muted">Pending sheet submission</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 bg-light border-top">
                            <span class="text-muted small">
                                <i class="fa-solid fa-shield-cat text-primary me-1"></i>
                                <strong>System Note:</strong> Status names and codes are standardized across Mark Attendance, Attendance Register, Daily Report, and Monthly Report to ensure zero data corruption or broken history.
                            </span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Sticky Floating Action Bar -->
        <div class="action-sticky-bar mt-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-circle-info text-primary fs-5"></i>
                <span class="text-dark small fw-semibold">Ensure all time formats and working calendar selections are confirmed before saving.</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="settings.php" class="btn btn-outline-secondary rounded-2 px-3 py-2 fw-semibold small"><i class="fa-solid fa-rotate-left me-1"></i>Reset Form</a>
                <button type="submit" id="saveSettingsBtn" class="btn btn-primary rounded-2 px-4 py-2 fw-semibold shadow-sm" style="background-color: #1d4ed8; border-color: #1d4ed8;">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Save Configuration
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Form Submission Loader JavaScript -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("attendanceSettingsForm");
    const saveBtn = document.getElementById("saveSettingsBtn");

    if (form && saveBtn) {
        form.addEventListener("submit", function(e) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving Changes...';
        });
    }
});
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
