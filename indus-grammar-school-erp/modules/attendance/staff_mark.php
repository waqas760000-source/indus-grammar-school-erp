<?php
/**
 * Indus Grammar School ERP - Daily Staff Attendance Management
 * Version 5.0.0 - Premium Roster & Shift Adjuster Engine
 */

$pageTitle = 'Daily Staff Attendance';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('attendance_mark');

$db = Database::getConnection();

// Avatar helper wrapper
if (!function_exists('getAvatarColor')) {
    function getAvatarColor($name) {
        $colors = ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0'];
        $hash = crc32($name);
        return $colors[abs($hash) % count($colors)];
    }
}

// Fetch shift settings defaults
$officeStart = '08:00';
$officeEnd   = '14:00';
$lateTime    = '08:15';
try {
    $settings = $db->query("SELECT office_start_time, office_end_time, late_arrival_time FROM staff_attendance_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($settings) {
        if (!empty($settings['office_start_time'])) $officeStart = date('H:i', strtotime($settings['office_start_time']));
        if (!empty($settings['office_end_time']))   $officeEnd   = date('H:i', strtotime($settings['office_end_time']));
        if (!empty($settings['late_arrival_time'])) $lateTime    = date('H:i', strtotime($settings['late_arrival_time']));
    }
} catch (Exception $e) {}

// Initial filter settings
$selectedDate   = sanitize($_GET['date'] ?? date('Y-m-d'));
$selectedDept   = sanitize($_GET['department'] ?? '');
$selectedDesig  = sanitize($_GET['designation'] ?? '');
$selectedType   = sanitize($_GET['staff_type'] ?? '');
$selectedStatus = sanitize($_GET['status'] ?? 'Active');

// Fetch dropdown filters dynamically
$departments  = [];
$designations = [];
try {
    $departments  = $db->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
    $designations = $db->query("SELECT DISTINCT designation FROM staff WHERE designation IS NOT NULL AND designation != '' ORDER BY designation ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Load staff roster matching parameters
$where = " WHERE 1=1";
$params = ['date' => $selectedDate];

if ($selectedDept !== '') {
    $where .= " AND s.department = :dept";
    $params['dept'] = $selectedDept;
}
if ($selectedDesig !== '') {
    $where .= " AND s.designation = :desig";
    $params['desig'] = $selectedDesig;
}
if ($selectedStatus !== '') {
    $where .= " AND s.status = :status";
    $params['status'] = $selectedStatus;
}
if ($selectedType !== '') {
    if ($selectedType === 'Teaching Staff') {
        $where .= " AND (s.department = 'Academic' OR s.designation LIKE '%Teacher%' OR s.designation LIKE '%Faculty%')";
    } else {
        $where .= " AND (s.department != 'Academic' AND s.designation NOT LIKE '%Teacher%' AND s.designation NOT LIKE '%Faculty%')";
    }
}

$staffList = [];
try {
    $stmt = $db->prepare("
        SELECT s.id, s.employee_no, s.first_name, s.last_name, s.department, s.designation, s.status as employee_status,
               sa.status as current_status, sa.check_in_time, sa.check_out_time, sa.remarks
        FROM staff s
        LEFT JOIN staff_attendance sa ON sa.staff_id = s.id AND sa.date = :date
        $where
        ORDER BY s.employee_no ASC
    ");
    $stmt->execute($params);
    $staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading staff marking list: " . $e->getMessage());
}

// Calculate summary counts
$summary = ['total' => count($staffList), 'present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0, 'halfday' => 0, 'unmarked' => 0];
foreach ($staffList as $st) {
    $stVal = $st['current_status'] ?: '';
    if ($stVal === 'Present') $summary['present']++;
    elseif ($stVal === 'Absent') $summary['absent']++;
    elseif ($stVal === 'Late') $summary['late']++;
    elseif ($stVal === 'Leave') $summary['leave']++;
    elseif ($stVal === 'Half Day') $summary['halfday']++;
    else $summary['unmarked']++;
}
$attendedCount  = $summary['present'] + $summary['late'] + $summary['halfday'];
$attendanceRate = $summary['total'] > 0 ? round(($attendedCount / $summary['total']) * 100, 1) : 0;
?>

<style>
.att-card-gradient-primary { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); color: #fff; }
.att-card-gradient-success { background: linear-gradient(135deg, #198754 0%, #146c43 100%); color: #fff; }
.att-card-gradient-danger  { background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%); color: #fff; }
.att-card-gradient-warning { background: linear-gradient(135deg, #ffc107 0%, #d39e00 100%); color: #212529; }
.att-card-gradient-info    { background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%); color: #fff; }
.att-card-gradient-purple  { background: linear-gradient(135deg, #6f42c1 0%, #522789 100%); color: #fff; }

.status-select-Present { background-color: #e8f5e9 !important; color: #2e7d32 !important; border-color: #a5d6a7 !important; }
.status-select-Absent  { background-color: #ffebee !important; color: #c62828 !important; border-color: #ef9a9a !important; }
.status-select-Late    { background-color: #fff8e1 !important; color: #f57f17 !important; border-color: #ffe082 !important; }
.status-select-Leave   { background-color: #e0f7fa !important; color: #00838f !important; border-color: #80deea !important; }
.status-select-HalfDay { background-color: #f3e5f5 !important; color: #6a1b9a !important; border-color: #ce93d8 !important; }

.roster-row:hover { background-color: rgba(13, 110, 253, 0.025); }
.quick-tag-chip { cursor: pointer; font-size: 0.72rem; transition: all 0.15s ease-in-out; }
.quick-tag-chip:hover { opacity: 0.85; transform: translateY(-1px); }
</style>

<!-- Top Executive Hero Banner Header -->
<div class="card border-0 shadow-lg mb-4 overflow-hidden" style="border-radius: 16px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f2744 100%);">
    <div class="card-body p-4 p-md-5 position-relative">
        <!-- Decorative glowing orb backdrop -->
        <div class="position-absolute end-0 top-0 translate-middle-y me-5 mt-4" style="width: 300px; height: 300px; background: radial-gradient(circle, rgba(13, 110, 253, 0.25) 0%, rgba(0, 0, 0, 0) 70%); pointer-events: none; filter: blur(40px);"></div>
        
        <div class="row align-items-center position-relative" style="z-index: 2;">
            <div class="col-lg-7 mb-3 mb-lg-0">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(13, 110, 253, 0.15); border: 1px solid rgba(13, 110, 253, 0.3);">
                    <span class="pulse-dot bg-primary rounded-circle d-inline-block" style="width: 8px; height: 8px;"></span>
                    <span class="fw-bold uppercase tracking-wider" style="font-size: 0.72rem; letter-spacing: 1px; color: #60a5fa;">STAFF ATTENDANCE CONSOLE</span>
                </div>
                <h2 class="fw-extrabold text-white mb-2 tracking-tight d-flex align-items-center gap-2">
                    <i class="fa-solid fa-user-clock text-warning fs-2 me-2"></i>Daily Staff Attendance
                </h2>
                <p class="mb-0 leading-relaxed" style="font-size: 0.95rem; color: #94a3b8;">
                    Load employees, log daily status, adjust shift check-in/out times, and print or export daily rosters.
                </p>
            </div>
            
            <div class="col-lg-5 text-lg-end">
                <div class="d-inline-flex flex-column align-items-lg-end gap-2">
                    <div class="d-inline-flex align-items-center gap-3 p-3 rounded-3 shadow-sm border" style="background: rgba(255, 255, 255, 0.07); backdrop-filter: blur(12px); border-color: rgba(255, 255, 255, 0.15) !important;">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-warning" style="width: 44px; height: 44px; background: rgba(245, 158, 11, 0.15);">
                            <i class="fa-solid fa-calendar-day fs-4"></i>
                        </div>
                        <div class="text-start">
                            <div class="text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.8px; color: #94a3b8;">ROSTER ACTIVE DATE</div>
                            <div class="fw-bold text-white fs-5 mb-0"><?php echo date('D, d M Y', strtotime($selectedDate)); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 6 KPI Metric Cards + Attendance Gauge -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 att-card-gradient-primary rounded-3 text-center position-relative overflow-hidden">
            <div class="small text-white-50 fw-semibold text-uppercase" style="font-size:0.72rem;">Total Staff</div>
            <div class="fs-2 fw-bold text-white my-1" id="kpiTotal"><?php echo $summary['total']; ?></div>
            <div class="small text-white-50" style="font-size:0.7rem;">Active Roster</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 att-card-gradient-success rounded-3 text-center">
            <div class="small text-white-50 fw-semibold text-uppercase" style="font-size:0.72rem;">Present</div>
            <div class="fs-2 fw-bold text-white my-1" id="kpiPresent"><?php echo $summary['present']; ?></div>
            <div class="small text-white-50" style="font-size:0.7rem;">On Duty</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 att-card-gradient-danger rounded-3 text-center">
            <div class="small text-white-50 fw-semibold text-uppercase" style="font-size:0.72rem;">Absent</div>
            <div class="fs-2 fw-bold text-white my-1" id="kpiAbsent"><?php echo $summary['absent']; ?></div>
            <div class="small text-white-50" style="font-size:0.7rem;">Not Reported</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 att-card-gradient-warning rounded-3 text-center">
            <div class="small text-dark-50 fw-semibold text-uppercase" style="font-size:0.72rem;">Late Arrivals</div>
            <div class="fs-2 fw-bold text-dark my-1" id="kpiLate"><?php echo $summary['late']; ?></div>
            <div class="small text-dark-50" style="font-size:0.7rem;">After Shift Start</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 att-card-gradient-info rounded-3 text-center">
            <div class="small text-white-50 fw-semibold text-uppercase" style="font-size:0.72rem;">On Leave</div>
            <div class="fs-2 fw-bold text-white my-1" id="kpiLeave"><?php echo $summary['leave']; ?></div>
            <div class="small text-white-50" style="font-size:0.7rem;">Approved Absences</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 att-card-gradient-purple rounded-3 text-center">
            <div class="small text-white-50 fw-semibold text-uppercase" style="font-size:0.72rem;">Half Day</div>
            <div class="fs-2 fw-bold text-white my-1" id="kpiHalfDay"><?php echo $summary['halfday']; ?></div>
            <div class="small text-white-50" style="font-size:0.7rem;">Partial Hours</div>
        </div>
    </div>
</div>

<!-- Filters & Roster Parameter Bar -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <h6 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-sliders me-2 text-primary"></i>Roster Parameters & Filters</h6>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted fw-semibold">Turnout Rate:</span>
                <div class="progress me-2" style="width: 120px; height: 10px; border-radius: 6px; background-color: #e9ecef;">
                    <div class="progress-bar bg-success" id="rateProgressBar" role="progressbar" style="width: <?php echo $attendanceRate; ?>%;"></div>
                </div>
                <span class="badge bg-success-soft text-success fw-bold px-2 py-1" id="rateBadge"><?php echo $attendanceRate; ?>%</span>
            </div>
        </div>

        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Attendance Date</label>
                <div class="input-group input-group-sm">
                    <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>" required>
                    <button type="button" class="btn btn-outline-secondary" title="Set Today" onclick="document.querySelector('input[name=date]').value='<?php echo date('Y-m-d'); ?>';">Today</button>
                    <button type="button" class="btn btn-outline-secondary" title="Set Yesterday" onclick="document.querySelector('input[name=date]').value='<?php echo date('Y-m-d', strtotime('-1 day')); ?>';">Yest.</button>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Department</label>
                <select class="form-select form-select-sm" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($selectedDept === $d) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Designation</label>
                <select class="form-select form-select-sm" name="designation">
                    <option value="">All Designations</option>
                    <?php foreach ($designations as $ds): ?>
                        <option value="<?php echo htmlspecialchars($ds); ?>" <?php echo ($selectedDesig === $ds) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ds); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Staff Category</label>
                <select class="form-select form-select-sm" name="staff_type">
                    <option value="">All Categories</option>
                    <option value="Teaching Staff" <?php echo ($selectedType === 'Teaching Staff') ? 'selected' : ''; ?>>Teaching Staff</option>
                    <option value="Non-Teaching Staff" <?php echo ($selectedType === 'Non-Teaching Staff') ? 'selected' : ''; ?>>Non-Teaching Staff</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Employment Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="Active" <?php echo ($selectedStatus === 'Active') ? 'selected' : ''; ?>>Active Staff</option>
                    <option value="Inactive" <?php echo ($selectedStatus === 'Inactive') ? 'selected' : ''; ?>>Inactive Staff</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-primary w-100 py-1 fw-semibold">
                    <i class="fa-solid fa-filter me-1"></i>Load
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Main Attendance Form & Roster Table -->
<form id="saveAttendanceForm" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
    <input type="hidden" name="action" value="save_staff_attendance_bulk">
    <input type="hidden" name="date" value="<?php echo $selectedDate; ?>">

    <div class="card border-0 shadow-sm mb-4" style="border-radius:14px; overflow:hidden;">
        <!-- Action Header Toolbar -->
        <div class="card-header bg-white p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-bold text-dark me-2"><i class="fa-solid fa-clipboard-user me-2 text-primary"></i>Staff Roster (<span id="visibleCount"><?php echo count($staffList); ?></span>)</span>
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" id="btnMarkAllPresent">
                    <i class="fa-solid fa-check-double me-1"></i>Mark All Present
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" id="btnMarkAllAbsent">
                    <i class="fa-solid fa-xmark me-1"></i>Mark All Absent
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="btnApplyShiftHours">
                    <i class="fa-solid fa-clock me-1"></i>Apply Shift (<?php echo $officeStart; ?>-<?php echo $officeEnd; ?>)
                </button>
            </div>

            <!-- Instant Search Input -->
            <div class="position-relative" style="width: 250px;">
                <input type="text" class="form-control form-control-sm ps-4 rounded-pill" id="rosterSearchInput" placeholder="Filter employee by name or ID...">
                <i class="fa-solid fa-magnifying-glass position-absolute start-0 top-50 translate-middle-y ms-2 text-muted small"></i>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0" id="markRosterTable">
                <thead class="bg-light">
                    <tr>
                        <th width="110" class="ps-4">Emp ID</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th width="160">Status</th>
                        <th width="130">Check In</th>
                        <th width="130">Check Out</th>
                        <th>Remarks / Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffList)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="fa-solid fa-users-slash fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    <h6 class="fw-bold mb-1">No Employees Found</h6>
                                    <p class="small mb-0">No staff members match the selected filter criteria for this roster date.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: $i = 0; foreach ($staffList as $st): 
                        $statusVal = $st['current_status'] ?: 'Present';
                        $fullName  = trim($st['first_name'] . ' ' . $st['last_name']);
                        $avatarBg  = getAvatarColor($fullName);
                        $initials  = strtoupper(substr($st['first_name'] ?? 'S', 0, 1) . substr($st['last_name'] ?? 'M', 0, 1));
                        
                        // Shift time formatters
                        $cinVal  = $st['check_in_time'] ? date('H:i', strtotime($st['check_in_time'])) : ($statusVal === 'Present' || $statusVal === 'Late' ? $officeStart : '');
                        $coutVal = $st['check_out_time'] ? date('H:i', strtotime($st['check_out_time'])) : ($statusVal === 'Present' || $statusVal === 'Late' ? $officeEnd : '');
                    ?>
                        <tr class="roster-row" data-id="<?php echo $st['id']; ?>" data-search="<?php echo htmlspecialchars(strtolower($st['employee_no'] . ' ' . $fullName . ' ' . $st['department'] . ' ' . $st['designation'])); ?>">
                            <td class="ps-4">
                                <span class="badge bg-light text-dark border font-monospace"><?php echo sanitize($st['employee_no']); ?></span>
                                <input type="hidden" name="records[<?php echo $i; ?>][staff_id]" value="<?php echo $st['id']; ?>">
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle text-white d-flex align-items-center justify-content-center me-2 fw-bold shadow-sm" style="width:34px; height:34px; background-color: <?php echo $avatarBg; ?>; font-size: 0.8rem;">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark mb-0 text-truncate" style="max-width:180px;"><?php echo sanitize($fullName); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-secondary border"><?php echo sanitize($st['department']); ?></span></td>
                            <td><span class="small text-muted"><?php echo sanitize($st['designation']); ?></span></td>
                            <td>
                                <select class="form-select form-select-sm status-select fw-bold status-select-<?php echo str_replace(' ', '', $statusVal); ?>" 
                                        name="records[<?php echo $i; ?>][status]" 
                                        data-row-idx="<?php echo $i; ?>" 
                                        style="font-size:0.82rem; border-radius: 8px;">
                                    <option value="Present" <?php echo ($statusVal === 'Present') ? 'selected' : ''; ?>>Present</option>
                                    <option value="Absent" <?php echo ($statusVal === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                                    <option value="Late" <?php echo ($statusVal === 'Late') ? 'selected' : ''; ?>>Late</option>
                                    <option value="Leave" <?php echo ($statusVal === 'Leave') ? 'selected' : ''; ?>>On Leave</option>
                                    <option value="Half Day" <?php echo ($statusVal === 'Half Day') ? 'selected' : ''; ?>>Half Day</option>
                                </select>
                            </td>
                            <td>
                                <input type="time" class="form-control form-control-sm time-in-input" 
                                       name="records[<?php echo $i; ?>][check_in]" 
                                       value="<?php echo $cinVal; ?>"
                                       <?php echo in_array($statusVal, ['Absent', 'Leave']) ? 'disabled' : ''; ?>>
                            </td>
                            <td>
                                <input type="time" class="form-control form-control-sm time-out-input" 
                                       name="records[<?php echo $i; ?>][check_out]" 
                                       value="<?php echo $coutVal; ?>"
                                       <?php echo in_array($statusVal, ['Absent', 'Leave']) ? 'disabled' : ''; ?>>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control form-control-sm remarks-input" 
                                           name="records[<?php echo $i; ?>][remarks]" 
                                           value="<?php echo htmlspecialchars($st['remarks'] ?? ''); ?>" 
                                           placeholder="Optional remarks..." style="font-size:0.8rem;">
                                </div>
                            </td>
                        </tr>
                    <?php $i++; endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($staffList)): ?>
    <div class="card border-0 shadow-sm p-3 mb-5" style="border-radius:14px; background: #ffffff;">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <button type="button" class="btn btn-outline-secondary px-3" onclick="location.reload();">
                    <i class="fa-solid fa-arrow-rotate-left me-1"></i>Reset Form
                </button>
                <button type="button" class="btn btn-outline-primary px-3 ms-2" id="btnPrintSheet">
                    <i class="fa-solid fa-print me-1"></i>Print Attendance Sheet
                </button>
                <a href="../../ajax/staff_attendance.php?action=export&date=<?php echo $selectedDate; ?>" class="btn btn-outline-success px-3 ms-2 d-none d-lg-inline-block">
                    <i class="fa-solid fa-file-excel me-1"></i>Export Roster
                </a>
            </div>
            <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
                <button type="submit" class="btn btn-success px-5 py-2 fw-bold shadow-sm" id="btnSaveRoster">
                    <i class="fa-solid fa-cloud-arrow-up me-2"></i>Save Daily Attendance Roster
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</form>

<!-- Hidden Printable Roster Template -->
<div id="printSheetSection" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 25px; color: #333; max-width: 900px; margin: 0 auto;">
        <div style="text-align: center; border-bottom: 2px solid #0d6efd; padding-bottom: 12px; margin-bottom: 20px;">
            <h2 style="margin: 0; text-transform: uppercase; color: #0d6efd; font-size: 24px; font-weight: bold;"><?php echo SCHOOL_NAME; ?></h2>
            <p style="margin: 4px 0 0 0; font-size: 12px; color: #666;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 14px 0 0 0; background: #f0f4f9; color: #1e293b; padding: 6px 12px; border-radius: 6px; letter-spacing: 1px; display: inline-block; font-size: 14px;">DAILY STAFF ATTENDANCE ROSTER SHEET</h4>
        </div>
        
        <table style="width: 100%; font-size: 13px; margin-bottom: 20px; border-collapse: collapse;">
            <tr>
                <td style="padding: 4px 0;"><strong>Roster Date:</strong> <?php echo date('d M Y (l)', strtotime($selectedDate)); ?></td>
                <td style="padding: 4px 0;"><strong>Department:</strong> <?php echo $selectedDept ?: 'All Departments'; ?></td>
                <td style="padding: 4px 0; text-align: right;"><strong>Total Staff Loaded:</strong> <?php echo count($staffList); ?></td>
            </tr>
        </table>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 11px; text-align: left;" border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr style="background: #e2e8f0; color: #0f172a;">
                    <th width="80">Emp ID</th>
                    <th>Staff Employee Name</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th width="90">Status</th>
                    <th width="70">Time In</th>
                    <th width="70">Time Out</th>
                    <th width="100">Signature</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffList as $st): 
                    $curSt = $st['current_status'] ?: 'Present';
                ?>
                    <tr>
                        <td><code><?php echo sanitize($st['employee_no']); ?></code></td>
                        <td><strong><?php echo sanitize($st['first_name'] . ' ' . $st['last_name']); ?></strong></td>
                        <td><?php echo sanitize($st['department']); ?></td>
                        <td><?php echo sanitize($st['designation']); ?></td>
                        <td><strong><?php echo $curSt; ?></strong></td>
                        <td><?php echo $st['check_in_time'] ? date('h:i A', strtotime($st['check_in_time'])) : '—'; ?></td>
                        <td><?php echo $st['check_out_time'] ? date('h:i A', strtotime($st['check_out_time'])) : '—'; ?></td>
                        <td style="height: 32px;"></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <table style="width: 100%; margin-top: 60px; font-size: 11px; text-align: center;">
            <tr>
                <td style="width: 33%;"><div style="border-top: 1px solid #000; width: 140px; margin: 0 auto; padding-top: 5px;">Prepared By (Attendance Supervisor)</div></td>
                <td style="width: 33%;"><div style="border-top: 1px solid #000; width: 140px; margin: 0 auto; padding-top: 5px;">HR / Admin Verifier</div></td>
                <td style="width: 33%;"><div style="border-top: 1px solid #000; width: 140px; margin: 0 auto; padding-top: 5px;">Principal Approval</div></td>
            </tr>
        </table>
    </div>
</div>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="markToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="markToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("markToast");
    const m = document.getElementById("markToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    const defaultShiftStart = "' . $officeStart . '";
    const defaultShiftEnd   = "' . $officeEnd . '";

    // Recalculate summary metrics dynamically in DOM
    function updateLiveSummary() {
        let present = 0, absent = 0, late = 0, leave = 0, halfday = 0;
        const rows = document.querySelectorAll(".roster-row");
        const total = rows.length;

        rows.forEach(row => {
            const sel = row.querySelector(".status-select");
            if (!sel) return;
            const val = sel.value;
            if (val === "Present") present++;
            else if (val === "Absent") absent++;
            else if (val === "Late") late++;
            else if (val === "Leave") leave++;
            else if (val === "Half Day") halfday++;
        });

        document.getElementById("kpiTotal").textContent   = total;
        document.getElementById("kpiPresent").textContent = present;
        document.getElementById("kpiAbsent").textContent  = absent;
        document.getElementById("kpiLate").textContent    = late;
        document.getElementById("kpiLeave").textContent   = leave;
        document.getElementById("kpiHalfDay").textContent = halfday;

        const attended = present + late + halfday;
        const rate = total > 0 ? ((attended / total) * 100).toFixed(1) : 0;
        
        const rateBar = document.getElementById("rateProgressBar");
        const rateBadge = document.getElementById("rateBadge");
        if (rateBar) rateBar.style.width = rate + "%";
        if (rateBadge) rateBadge.textContent = rate + "%";
    }

    // Update status select classes and time input availability
    function applyStatusRowRules(sel) {
        const val = sel.value;
        const row = sel.closest("tr");
        const tIn  = row.querySelector(".time-in-input");
        const tOut = row.querySelector(".time-out-input");

        // Remove old status class
        sel.className = sel.className.replace(/status-select-\w+/g, "").trim();
        sel.classList.add("status-select-" + val.replace(/\s+/g, ""));

        if (val === "Absent" || val === "Leave") {
            tIn.disabled = true; tIn.value = "";
            tOut.disabled = true; tOut.value = "";
        } else {
            tIn.disabled = false;
            tOut.disabled = false;
            if (!tIn.value)  tIn.value  = defaultShiftStart;
            if (!tOut.value) tOut.value = defaultShiftEnd;
        }
        updateLiveSummary();
    }

    // Attach listener to all status selects
    document.querySelectorAll(".status-select").forEach(sel => {
        sel.addEventListener("change", function() {
            applyStatusRowRules(this);
        });
    });

    // Mark All Present Button
    const btnPresent = document.getElementById("btnMarkAllPresent");
    if (btnPresent) {
        btnPresent.addEventListener("click", function() {
            document.querySelectorAll(".roster-row").forEach(row => {
                if (row.style.display !== "none") {
                    const sel = row.querySelector(".status-select");
                    if (sel) {
                        sel.value = "Present";
                        applyStatusRowRules(sel);
                    }
                }
            });
            showToast("Marked all visible staff as Present.", true);
        });
    }

    // Mark All Absent Button
    const btnAbsent = document.getElementById("btnMarkAllAbsent");
    if (btnAbsent) {
        btnAbsent.addEventListener("click", function() {
            document.querySelectorAll(".roster-row").forEach(row => {
                if (row.style.display !== "none") {
                    const sel = row.querySelector(".status-select");
                    if (sel) {
                        sel.value = "Absent";
                        applyStatusRowRules(sel);
                    }
                }
            });
            showToast("Marked all visible staff as Absent.", true);
        });
    }

    // Apply Shift Hours Button
    const btnShift = document.getElementById("btnApplyShiftHours");
    if (btnShift) {
        btnShift.addEventListener("click", function() {
            document.querySelectorAll(".roster-row").forEach(row => {
                if (row.style.display !== "none") {
                    const sel = row.querySelector(".status-select");
                    if (sel && (sel.value === "Present" || sel.value === "Late" || sel.value === "Half Day")) {
                        const tIn = row.querySelector(".time-in-input");
                        const tOut = row.querySelector(".time-out-input");
                        if (tIn) tIn.value = defaultShiftStart;
                        if (tOut) tOut.value = defaultShiftEnd;
                    }
                }
            });
            showToast("Applied standard shift times (" + defaultShiftStart + " - " + defaultShiftEnd + ") to on-duty staff.", true);
        });
    }

    // Roster Instant Client Search Filter
    const searchInput = document.getElementById("rosterSearchInput");
    if (searchInput) {
        searchInput.addEventListener("keyup", function() {
            const query = this.value.toLowerCase().trim();
            let visible = 0;
            document.querySelectorAll(".roster-row").forEach(row => {
                const searchData = row.dataset.search || "";
                if (searchData.includes(query)) {
                    row.style.display = "";
                    visible++;
                } else {
                    row.style.display = "none";
                }
            });
            const visCount = document.getElementById("visibleCount");
            if (visCount) visCount.textContent = visible;
        });
    }

    // Save Bulk Attendance Form AJAX
    const saveForm = document.getElementById("saveAttendanceForm");
    if (saveForm) {
        saveForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSaveRoster");
            btn.disabled = true; 
            btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Saving Daily Roster...\';
            
            fetch("../../ajax/staff_attendance.php", { method: "POST", body: new FormData(saveForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) {
                        setTimeout(() => location.reload(), 1200);
                    } else { 
                        btn.disabled = false; 
                        btn.innerHTML = \'<i class="fa-solid fa-cloud-arrow-up me-2"></i>Save Daily Attendance Roster\'; 
                    }
                })
                .catch(() => { 
                    showToast("Network communication error. Please try again.", false); 
                    btn.disabled = false; 
                    btn.innerHTML = \'<i class="fa-solid fa-cloud-arrow-up me-2"></i>Save Daily Attendance Roster\'; 
                });
        });
    }

    // Print Attendance Sheet
    const printBtn = document.getElementById("btnPrintSheet");
    if (printBtn) {
        printBtn.addEventListener("click", function() {
            const printContent = document.getElementById("printSheetSection").innerHTML;
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Staff Attendance Roster</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
