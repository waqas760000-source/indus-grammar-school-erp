<?php
/**
 * Indus Grammar School ERP - Daily Staff Attendance Report
 * Version 5.0.0 - Premium Single-Day Roster & CSV Log Console
 */

$pageTitle = 'Daily Staff Attendance Report';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('attendance_view');

$db = Database::getConnection();

// Avatar helper wrapper
if (!function_exists('getAvatarColor')) {
    function getAvatarColor($name) {
        $colors = ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0'];
        $hash = crc32($name);
        return $colors[abs($hash) % count($colors)];
    }
}

// Fetch shift settings defaults for late/early calculation
$officeStart = '08:00:00';
$officeEnd   = '14:00:00';
$lateTime    = '08:15:00';
try {
    $settings = $db->query("SELECT office_start_time, office_end_time, late_arrival_time FROM staff_attendance_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($settings) {
        if (!empty($settings['office_start_time'])) $officeStart = $settings['office_start_time'];
        if (!empty($settings['office_end_time']))   $officeEnd   = $settings['office_end_time'];
        if (!empty($settings['late_arrival_time'])) $lateTime    = $settings['late_arrival_time'];
    }
} catch (Exception $e) {}

// Filters
$filterDate   = sanitize($_GET['date'] ?? date('Y-m-d'));
$filterDept   = sanitize($_GET['department'] ?? '');
$filterDesig  = sanitize($_GET['designation'] ?? '');
$filterStatus = sanitize($_GET['status'] ?? '');
$filterShift  = sanitize($_GET['shift_tag'] ?? ''); // 'on_time', 'late_arrival', 'half_day', 'overtime'

// Fetch unique dropdown filters dynamically
$departments  = [];
$designations = [];
try {
    $departments  = $db->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
    $designations = $db->query("SELECT DISTINCT designation FROM staff WHERE designation IS NOT NULL AND designation != '' ORDER BY designation ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Query building
$where  = " WHERE sa.date = :date";
$params = ['date' => $filterDate];

if ($filterDept !== '') {
    $where .= " AND s.department = :dept";
    $params['dept'] = $filterDept;
}
if ($filterDesig !== '') {
    $where .= " AND s.designation = :desig";
    $params['desig'] = $filterDesig;
}
if ($filterStatus !== '') {
    $where .= " AND sa.status = :status";
    $params['status'] = $filterStatus;
}

$records = [];
try {
    $stmt = $db->prepare("
        SELECT sa.id, sa.date, sa.status, sa.check_in_time, sa.check_out_time, sa.remarks,
               s.employee_no, s.first_name, s.last_name, s.department, s.designation
        FROM staff_attendance sa
        JOIN staff s ON sa.staff_id = s.id
        $where
        ORDER BY s.employee_no ASC
    ");
    $stmt->execute($params);
    $allRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Apply Shift Tag Filter in PHP layer if specified
    foreach ($allRecords as $r) {
        $cin  = $r['check_in_time'];
        $cout = $r['check_out_time'];
        $st   = $r['status'];

        $shiftTag = 'standard';
        if ($st === 'Late' || ($cin && strtotime($cin) > strtotime($lateTime))) {
            $shiftTag = 'late_arrival';
        } elseif ($st === 'Half Day') {
            $shiftTag = 'half_day';
        } elseif ($cin && strtotime($cin) <= strtotime($officeStart)) {
            $shiftTag = 'on_time';
        }

        if ($filterShift !== '') {
            if ($filterShift !== $shiftTag) {
                continue; // Skip records not matching shift tag
            }
        }
        $r['shift_tag'] = $shiftTag;
        $records[] = $r;
    }
} catch (Exception $e) {
    error_log("Error loading daily staff attendance report: " . $e->getMessage());
}

// Compute statistics
$stats = ['total' => count($records), 'present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0, 'halfday' => 0, 'percent' => 0.00];
foreach ($records as $r) {
    if ($r['status'] === 'Present') $stats['present']++;
    elseif ($r['status'] === 'Absent') $stats['absent']++;
    elseif ($r['status'] === 'Late') $stats['late']++;
    elseif ($r['status'] === 'Leave') $stats['leave']++;
    elseif ($r['status'] === 'Half Day') $stats['halfday']++;
}
if ($stats['total'] > 0) {
    $presentCount = $stats['present'] + $stats['late'] + $stats['halfday'];
    $stats['percent'] = round(($presentCount / $stats['total']) * 100, 1);
}

// Handle CSV Log Sheet Export
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="daily_staff_attendance_log_' . $filterDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, [SCHOOL_NAME . ' - DAILY STAFF ATTENDANCE REPORT LOG']);
    fputcsv($output, ['Report Date:', $filterDate]);
    fputcsv($output, ['Department Filter:', $filterDept ?: 'All Departments']);
    fputcsv($output, ['Shift Filter:', $filterShift ?: 'All Shifts']);
    fputcsv($output, ['Overall Attendance Rate:', $stats['percent'] . '%']);
    fputcsv($output, ['Export Timestamp:', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, ['Emp ID', 'Employee Name', 'Department', 'Designation', 'Attendance Status', 'Shift Tag', 'Check In', 'Check Out', 'Hours Worked', 'Remarks']);
    
    foreach ($records as $r) {
        $cin  = $r['check_in_time'] ? date('h:i A', strtotime($r['check_in_time'])) : '—';
        $cout = $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '—';
        
        $durationStr = '—';
        if ($r['check_in_time'] && $r['check_out_time']) {
            $diff = strtotime($r['check_out_time']) - strtotime($r['check_in_time']);
            if ($diff > 0) {
                $hrs  = floor($diff / 3600);
                $mins = floor(($diff % 3600) / 60);
                $durationStr = "{$hrs}h {$mins}m";
            }
        }

        fputcsv($output, [
            $r['employee_no'],
            $r['first_name'] . ' ' . $r['last_name'],
            $r['department'],
            $r['designation'],
            $r['status'],
            strtoupper(str_replace('_', ' ', $r['shift_tag'] ?? 'Standard')),
            $cin,
            $cout,
            $durationStr,
            $r['remarks'] ?: ''
        ]);
    }
    fclose($output);
    exit;
}
?>

<style>
.kpi-report-card { border-radius: 12px; transition: transform 0.15s ease-in-out; }
.kpi-report-card:hover { transform: translateY(-2px); }
.shift-tag-badge { font-size: 0.72rem; padding: 0.25rem 0.6rem; border-radius: 20px; font-weight: 600; }
.tag-on-time { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
.tag-late { background-color: #fff8e1; color: #f57f17; border: 1px solid #ffe082; }
.tag-half-day { background-color: #f3e5f5; color: #7b1fa2; border: 1px solid #e1bee7; }
.tag-standard { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
</style>

<!-- Page Header Executive Hero Banner -->
<div class="card border-0 shadow-lg mb-4 overflow-hidden" style="border-radius: 16px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f2744 100%);">
    <div class="card-body p-4 p-md-5 position-relative">
        <!-- Decorative glowing orb backdrop -->
        <div class="position-absolute end-0 top-0 translate-middle-y me-5 mt-4" style="width: 300px; height: 300px; background: radial-gradient(circle, rgba(13, 110, 253, 0.25) 0%, rgba(0, 0, 0, 0) 70%); pointer-events: none; filter: blur(40px);"></div>
        
        <div class="row align-items-center position-relative" style="z-index: 2;">
            <div class="col-lg-7 mb-3 mb-lg-0">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(13, 110, 253, 0.15); border: 1px solid rgba(13, 110, 253, 0.3);">
                    <span class="pulse-dot bg-primary rounded-circle d-inline-block" style="width: 8px; height: 8px;"></span>
                    <span class="fw-bold uppercase tracking-wider" style="font-size: 0.72rem; letter-spacing: 1px; color: #60a5fa;">ATTENDANCE ANALYTICS CONSOLE</span>
                </div>
                <h2 class="fw-extrabold text-white mb-2 tracking-tight d-flex align-items-center gap-2">
                    <i class="fa-solid fa-chart-line text-warning fs-2 me-2"></i>Daily Staff Attendance Report
                </h2>
                <p class="mb-0 leading-relaxed" style="font-size: 0.95rem; color: #94a3b8;">
                    View single-day status rosters, filter by shift tags, and download CSV log sheets.
                </p>
            </div>
            
            <div class="col-lg-5 text-lg-end">
                <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end">
                    <button class="btn btn-outline-light px-3 py-2 shadow-sm rounded-pill fw-semibold" id="btnPrintReport">
                        <i class="fa-solid fa-print me-1 text-warning"></i>Print Report
                    </button>
                    <a href="?action=export&date=<?php echo $filterDate; ?>&department=<?php echo urlencode($filterDept); ?>&designation=<?php echo urlencode($filterDesig); ?>&status=<?php echo urlencode($filterStatus); ?>&shift_tag=<?php echo urlencode($filterShift); ?>" class="btn btn-success px-4 py-2 shadow-sm rounded-pill fw-semibold">
                        <i class="fa-solid fa-file-csv me-1"></i>Download CSV Log Sheet
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 7 Summary Metric KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 kpi-report-card text-center bg-white border-start border-primary border-4">
            <div class="small text-muted fw-semibold text-uppercase" style="font-size:0.7rem;">Total Logs</div>
            <div class="fs-3 fw-bold text-dark my-1"><?php echo $stats['total']; ?></div>
            <div class="small text-muted" style="font-size:0.7rem;">Roster Entries</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 kpi-report-card text-center" style="background-color: #f0fdf4;">
            <div class="small text-success fw-semibold text-uppercase" style="font-size:0.7rem;">Present</div>
            <div class="fs-3 fw-bold text-success my-1"><?php echo $stats['present']; ?></div>
            <div class="small text-success-50" style="font-size:0.7rem;">On Duty</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 kpi-report-card text-center" style="background-color: #fef2f2;">
            <div class="small text-danger fw-semibold text-uppercase" style="font-size:0.7rem;">Absent</div>
            <div class="fs-3 fw-bold text-danger my-1"><?php echo $stats['absent']; ?></div>
            <div class="small text-danger-50" style="font-size:0.7rem;">Unexcused</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 kpi-report-card text-center" style="background-color: #fffbeb;">
            <div class="small text-warning fw-semibold text-uppercase" style="font-size:0.7rem;">Late Arrivals</div>
            <div class="fs-3 fw-bold text-warning my-1"><?php echo $stats['late']; ?></div>
            <div class="small text-warning-50" style="font-size:0.7rem;">Delayed Shift</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 kpi-report-card text-center" style="background-color: #ecfeff;">
            <div class="small text-info fw-semibold text-uppercase" style="font-size:0.7rem;">On Leave</div>
            <div class="fs-3 fw-bold text-info my-1"><?php echo $stats['leave']; ?></div>
            <div class="small text-info-50" style="font-size:0.7rem;">Approved</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 kpi-report-card text-center" style="background-color: #faf5ff;">
            <div class="small text-primary fw-semibold text-uppercase" style="font-size:0.7rem;">Half Day</div>
            <div class="fs-3 fw-bold text-primary my-1"><?php echo $stats['halfday']; ?></div>
            <div class="small text-primary-50" style="font-size:0.7rem;">Short Shift</div>
        </div>
    </div>
    <div class="col-12 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 kpi-report-card text-center text-white" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6);">
            <div class="small text-white-50 fw-semibold text-uppercase" style="font-size:0.7rem;">Attendance Rate</div>
            <div class="fs-3 fw-bold text-white my-1"><?php echo $stats['percent']; ?>%</div>
            <div class="small text-white-50" style="font-size:0.7rem;">Turnout Percentage</div>
        </div>
    </div>
</div>

<!-- Roster Filters Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2 text-primary"></i>Daily Log Filters & Shift Parameters</h6>
        <form method="GET" class="row g-3 align-items-end" id="dailyReportFilterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Attendance Date</label>
                <div class="input-group input-group-sm">
                    <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($filterDate); ?>" required>
                    <button type="button" class="btn btn-outline-secondary" onclick="document.querySelector('input[name=date]').value='<?php echo date('Y-m-d'); ?>';">Today</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="document.querySelector('input[name=date]').value='<?php echo date('Y-m-d', strtotime('-1 day')); ?>';">Yest.</button>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Department</label>
                <select class="form-select form-select-sm" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($filterDept === $d) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Designation</label>
                <select class="form-select form-select-sm" name="designation">
                    <option value="">All Designations</option>
                    <?php foreach ($designations as $ds): ?>
                        <option value="<?php echo htmlspecialchars($ds); ?>" <?php echo ($filterDesig === $ds) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ds); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Statuses</option>
                    <option value="Present" <?php echo ($filterStatus === 'Present') ? 'selected' : ''; ?>>Present</option>
                    <option value="Absent" <?php echo ($filterStatus === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                    <option value="Late" <?php echo ($filterStatus === 'Late') ? 'selected' : ''; ?>>Late</option>
                    <option value="Leave" <?php echo ($filterStatus === 'Leave') ? 'selected' : ''; ?>>On Leave</option>
                    <option value="Half Day" <?php echo ($filterStatus === 'Half Day') ? 'selected' : ''; ?>>Half Day</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Shift Tag</label>
                <select class="form-select form-select-sm" name="shift_tag">
                    <option value="">All Shift Tags</option>
                    <option value="on_time" <?php echo ($filterShift === 'on_time') ? 'selected' : ''; ?>>On Time Arrival</option>
                    <option value="late_arrival" <?php echo ($filterShift === 'late_arrival') ? 'selected' : ''; ?>>Late Arrival</option>
                    <option value="half_day" <?php echo ($filterShift === 'half_day') ? 'selected' : ''; ?>>Half Day Shift</option>
                    <option value="standard" <?php echo ($filterShift === 'standard') ? 'selected' : ''; ?>>Standard Shift</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-primary w-100 py-1 fw-semibold">
                    <i class="fa-solid fa-magnifying-glass me-1"></i>Search
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Table View Roster Card -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px; overflow:hidden;" id="reportSection">
    <div class="card-header bg-white p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span class="fw-bold text-dark"><i class="fa-solid fa-list-check me-2 text-primary"></i>Single-Day Status Roster (<span id="visibleReportCount"><?php echo count($records); ?></span> Logs)</span>
        <div class="position-relative" style="width: 250px;">
            <input type="text" class="form-control form-control-sm ps-4 rounded-pill" id="reportSearchInput" placeholder="Filter roster by keyword...">
            <i class="fa-solid fa-magnifying-glass position-absolute start-0 top-50 translate-middle-y ms-2 text-muted small"></i>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0" id="dailyReportTable">
            <thead class="bg-light">
                <tr>
                    <th width="110" class="ps-4">Emp ID</th>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th width="120">Time In</th>
                    <th width="120">Time Out</th>
                    <th width="110">Duration</th>
                    <th width="120">Shift Tag</th>
                    <th width="130">Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5">
                            <div class="text-muted">
                                <i class="fa-solid fa-folder-open fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                <h6 class="fw-bold mb-1">No Daily Attendance Logs Found</h6>
                                <p class="small mb-0">No attendance logs match the filter criteria for date <strong><?php echo date('d M Y', strtotime($filterDate)); ?></strong>.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($records as $r): 
                    $fullName  = trim($r['first_name'] . ' ' . $r['last_name']);
                    $avatarBg  = getAvatarColor($fullName);
                    $initials  = strtoupper(substr($r['first_name'] ?? 'S', 0, 1) . substr($r['last_name'] ?? 'M', 0, 1));
                    
                    // Duration calculation
                    $durationStr = '—';
                    if ($r['check_in_time'] && $r['check_out_time']) {
                        $diff = strtotime($r['check_out_time']) - strtotime($r['check_in_time']);
                        if ($diff > 0) {
                            $hrs  = floor($diff / 3600);
                            $mins = floor(($diff % 3600) / 60);
                            $durationStr = "{$hrs}h {$mins}m";
                        }
                    }

                    // Shift tag formatting
                    $sTag = $r['shift_tag'] ?? 'standard';
                    $tagClass = 'tag-standard';
                    $tagText  = 'Standard';
                    if ($sTag === 'on_time')      { $tagClass = 'tag-on-time';  $tagText = 'On Time'; }
                    elseif ($sTag === 'late_arrival') { $tagClass = 'tag-late';     $tagText = 'Late Arrival'; }
                    elseif ($sTag === 'half_day')     { $tagClass = 'tag-half-day'; $tagText = 'Half Day'; }

                    // Status badge
                    $badgeClass = 'bg-light text-muted border';
                    if ($r['status'] === 'Present')    $badgeClass = 'bg-success-soft text-success';
                    elseif ($r['status'] === 'Absent')   $badgeClass = 'bg-danger-soft text-danger';
                    elseif ($r['status'] === 'Late')     $badgeClass = 'bg-warning-soft text-warning';
                    elseif ($r['status'] === 'Leave')    $badgeClass = 'bg-info-soft text-info';
                    elseif ($r['status'] === 'Half Day') $badgeClass = 'bg-primary-soft text-primary';
                ?>
                    <tr class="report-row" data-search="<?php echo htmlspecialchars(strtolower($r['employee_no'] . ' ' . $fullName . ' ' . $r['department'] . ' ' . $r['designation'] . ' ' . $r['status'])); ?>">
                        <td class="ps-4">
                            <span class="badge bg-light text-dark border font-monospace"><?php echo sanitize($r['employee_no']); ?></span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle text-white d-flex align-items-center justify-content-center me-2 fw-bold shadow-sm" style="width:32px; height:32px; background-color: <?php echo $avatarBg; ?>; font-size: 0.78rem;">
                                    <?php echo $initials; ?>
                                </div>
                                <span class="fw-bold text-dark"><?php echo sanitize($fullName); ?></span>
                            </div>
                        </td>
                        <td><span class="badge bg-light text-secondary border"><?php echo sanitize($r['department']); ?></span></td>
                        <td><span class="small text-muted"><?php echo sanitize($r['designation']); ?></span></td>
                        <td>
                            <span class="small fw-bold text-dark">
                                <?php echo $r['check_in_time'] ? date('h:i A', strtotime($r['check_in_time'])) : '—'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="small fw-bold text-dark">
                                <?php echo $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '—'; ?>
                            </span>
                        </td>
                        <td><span class="small text-muted font-monospace"><?php echo $durationStr; ?></span></td>
                        <td><span class="shift-tag-badge <?php echo $tagClass; ?>"><?php echo $tagText; ?></span></td>
                        <td>
                            <span class="badge <?php echo $badgeClass; ?> px-3 py-2 rounded-pill fw-bold" style="font-size:0.75rem;">
                                <?php echo $r['status']; ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?php echo sanitize($r['remarks'] ?: '—'); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Hidden Printable Executive Report Template -->
<div id="printReportTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 25px; color: #1e293b; max-width: 900px; margin: 0 auto;">
        <div style="text-align: center; border-bottom: 2px solid #0d6efd; padding-bottom: 12px; margin-bottom: 20px;">
            <h2 style="margin: 0; text-transform: uppercase; color: #0d6efd; font-size: 24px; font-weight: bold;"><?php echo SCHOOL_NAME; ?></h2>
            <p style="margin: 4px 0 0 0; font-size: 12px; color: #64748b;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 14px 0 0 0; background: #f1f5f9; color: #0f172a; padding: 6px 14px; border-radius: 6px; letter-spacing: 1px; display: inline-block; font-size: 14px;">DAILY STAFF ATTENDANCE EXECUTIVE REPORT</h4>
        </div>
        
        <table style="width: 100%; font-size: 12px; margin-bottom: 15px; border-collapse: collapse;">
            <tr>
                <td style="padding: 4px 0;"><strong>Report Date:</strong> <?php echo date('d M Y (l)', strtotime($filterDate)); ?></td>
                <td style="padding: 4px 0;"><strong>Department:</strong> <?php echo $filterDept ?: 'All Departments'; ?></td>
                <td style="padding: 4px 0; text-align: right;"><strong>Turnout Rate:</strong> <?php echo $stats['percent']; ?>%</td>
            </tr>
        </table>

        <!-- Summary micro boxes -->
        <table style="width: 100%; font-size: 11px; margin-bottom: 20px; border-collapse: collapse; text-align: center;" border="1" cellpadding="6">
            <tr style="background: #f8fafc;">
                <td><strong>Total Logs:</strong> <?php echo $stats['total']; ?></td>
                <td><strong style="color:#15803d;">Present:</strong> <?php echo $stats['present']; ?></td>
                <td><strong style="color:#b91c1c;">Absent:</strong> <?php echo $stats['absent']; ?></td>
                <td><strong style="color:#b45309;">Late:</strong> <?php echo $stats['late']; ?></td>
                <td><strong style="color:#0369a1;">Leave:</strong> <?php echo $stats['leave']; ?></td>
                <td><strong style="color:#6b21a8;">Half Day:</strong> <?php echo $stats['halfday']; ?></td>
            </tr>
        </table>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 11px; text-align: left;" border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr style="background: #e2e8f0; color: #0f172a;">
                    <th width="80">Emp ID</th>
                    <th>Staff Name</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th width="75">Time In</th>
                    <th width="75">Time Out</th>
                    <th width="70">Duration</th>
                    <th width="85">Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="9" style="text-align:center;">No records logged for this date.</td></tr>
                <?php else: foreach ($records as $r): 
                    $cin  = $r['check_in_time'] ? date('h:i A', strtotime($r['check_in_time'])) : '—';
                    $cout = $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '—';
                    $durationStr = '—';
                    if ($r['check_in_time'] && $r['check_out_time']) {
                        $diff = strtotime($r['check_out_time']) - strtotime($r['check_in_time']);
                        if ($diff > 0) {
                            $hrs  = floor($diff / 3600);
                            $mins = floor(($diff % 3600) / 60);
                            $durationStr = "{$hrs}h {$mins}m";
                        }
                    }
                ?>
                    <tr>
                        <td><code><?php echo sanitize($r['employee_no']); ?></code></td>
                        <td><strong><?php echo sanitize($r['first_name'] . ' ' . $r['last_name']); ?></strong></td>
                        <td><?php echo sanitize($r['department']); ?></td>
                        <td><?php echo sanitize($r['designation']); ?></td>
                        <td><?php echo $cin; ?></td>
                        <td><?php echo $cout; ?></td>
                        <td><?php echo $durationStr; ?></td>
                        <td><strong><?php echo $r['status']; ?></strong></td>
                        <td><?php echo sanitize($r['remarks'] ?: ''); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        
        <table style="width: 100%; margin-top: 50px; font-size: 11px; text-align: center;">
            <tr>
                <td style="width: 50%;"><div style="border-top: 1px solid #000; width: 160px; margin: 0 auto; padding-top: 5px;">HR / Attendance Officer</div></td>
                <td style="width: 50%;"><div style="border-top: 1px solid #000; width: 160px; margin: 0 auto; padding-top: 5px;">Principal / Director Approval</div></td>
            </tr>
        </table>
    </div>
</div>

<?php $extraJS = '<script>
document.addEventListener("DOMContentLoaded", function() {
    // Instant search filter on daily report table
    const searchInput = document.getElementById("reportSearchInput");
    if (searchInput) {
        searchInput.addEventListener("keyup", function() {
            const query = this.value.toLowerCase().trim();
            let visible = 0;
            document.querySelectorAll(".report-row").forEach(row => {
                const searchData = row.dataset.search || "";
                if (searchData.includes(query)) {
                    row.style.display = "";
                    visible++;
                } else {
                    row.style.display = "none";
                }
            });
            const visCount = document.getElementById("visibleReportCount");
            if (visCount) visCount.textContent = visible;
        });
    }

    // Print Report Button
    const printBtn = document.getElementById("btnPrintReport");
    if (printBtn) {
        printBtn.addEventListener("click", function() {
            const printContent = document.getElementById("printReportTemplate").innerHTML;
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Daily Attendance Report</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
