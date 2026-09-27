<?php
/**
 * Indus Grammar School ERP - Staff Attendance Register Ledger
 * Version 5.0.0 - Executive Cumulative Logs & Physical Clock-Out Verifier
 */

$pageTitle = 'Staff Attendance Register';
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

// Parameters
$filterEmpId   = sanitize($_GET['employee_id'] ?? '');
$filterEmpName = sanitize($_GET['employee_name'] ?? '');
$filterDept    = sanitize($_GET['department'] ?? '');
$filterDesig   = sanitize($_GET['designation'] ?? '');
$fromDate      = sanitize($_GET['from_date'] ?? date('Y-m-01'));
$toDate        = sanitize($_GET['to_date'] ?? date('Y-m-d'));

// Fetch departments & designations for filter selections
$departments  = [];
$designations = [];
try {
    $departments  = $db->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
    $designations = $db->query("SELECT DISTINCT designation FROM staff WHERE designation IS NOT NULL AND designation != '' ORDER BY designation ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Construct query filters
$where = " WHERE sa.date BETWEEN :from AND :to";
$params = ['from' => $fromDate, 'to' => $toDate];

if ($filterEmpId !== '') {
    $where .= " AND s.employee_no = :emp_id";
    $params['emp_id'] = $filterEmpId;
}
if ($filterEmpName !== '') {
    $where .= " AND (s.first_name LIKE :name OR s.last_name LIKE :name)";
    $params['name'] = '%' . $filterEmpName . '%';
}
if ($filterDept !== '') {
    $where .= " AND s.department = :dept";
    $params['dept'] = $filterDept;
}
if ($filterDesig !== '') {
    $where .= " AND s.designation = :desig";
    $params['desig'] = $filterDesig;
}

$records = [];
try {
    $stmt = $db->prepare("
        SELECT sa.id, sa.date, sa.status, sa.check_in_time, sa.check_out_time, sa.remarks,
               s.id as staff_record_id, s.employee_no, s.first_name, s.last_name, s.department, s.designation, s.date_of_joining
        FROM staff_attendance sa
        JOIN staff s ON sa.staff_id = s.id
        $where
        ORDER BY sa.date DESC, s.employee_no ASC
    ");
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading attendance register: " . $e->getMessage());
}

// Compute cumulative summary metrics across fetched logs
$cumStats = [
    'total_logs'  => count($records),
    'clock_ins'   => 0,
    'clock_outs'  => 0,
    'total_secs'  => 0,
    'present'     => 0,
    'absent'      => 0,
    'late'        => 0,
    'leave'       => 0,
    'halfday'     => 0
];

foreach ($records as $r) {
    if ($r['check_in_time'])  $cumStats['clock_ins']++;
    if ($r['check_out_time']) $cumStats['clock_outs']++;
    
    if ($r['check_in_time'] && $r['check_out_time']) {
        $diff = strtotime($r['check_out_time']) - strtotime($r['check_in_time']);
        if ($diff > 0) $cumStats['total_secs'] += $diff;
    }

    if ($r['status'] === 'Present') $cumStats['present']++;
    elseif ($r['status'] === 'Absent') $cumStats['absent']++;
    elseif ($r['status'] === 'Late') $cumStats['late']++;
    elseif ($r['status'] === 'Leave') $cumStats['leave']++;
    elseif ($r['status'] === 'Half Day') $cumStats['halfday']++;
}

$cumTotalHours = round($cumStats['total_secs'] / 3600, 1);
$attendedLogs  = $cumStats['present'] + $cumStats['late'] + $cumStats['halfday'];
$punctualityPct = $cumStats['total_logs'] > 0 ? round(($attendedLogs / $cumStats['total_logs']) * 100, 1) : 0;

// Single Employee Summary Dossier Panel if filtered by a specific Employee ID
$singleEmployee = null;
if ($filterEmpId !== '' && !empty($records)) {
    $singleEmployee = [
        'employee_no'  => $records[0]['employee_no'],
        'name'         => trim($records[0]['first_name'] . ' ' . $records[0]['last_name']),
        'department'   => $records[0]['department'],
        'designation'  => $records[0]['designation'],
        'joining_date' => $records[0]['date_of_joining'],
        'present'      => 0,
        'absent'       => 0,
        'late'         => 0,
        'leave'        => 0,
        'halfday'      => 0,
        'total_hours'  => 0,
        'total_logs'   => 0,
        'percent'      => 0.0
    ];

    $empSecs = 0;
    foreach ($records as $r) {
        if ($r['status'] === 'Present') $singleEmployee['present']++;
        elseif ($r['status'] === 'Absent') $singleEmployee['absent']++;
        elseif ($r['status'] === 'Late') $singleEmployee['late']++;
        elseif ($r['status'] === 'Leave') $singleEmployee['leave']++;
        elseif ($r['status'] === 'Half Day') $singleEmployee['halfday']++;

        if ($r['check_in_time'] && $r['check_out_time']) {
            $diff = strtotime($r['check_out_time']) - strtotime($r['check_in_time']);
            if ($diff > 0) $empSecs += $diff;
        }
    }
    
    $singleEmployee['total_logs']  = count($records);
    $singleEmployee['total_hours'] = round($empSecs / 3600, 1);
    $physPresent = $singleEmployee['present'] + $singleEmployee['late'] + $singleEmployee['halfday'];
    $singleEmployee['percent']     = $singleEmployee['total_logs'] > 0 ? round(($physPresent / $singleEmployee['total_logs']) * 100, 1) : 0;
}

// Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="staff_attendance_register_' . $fromDate . '_to_' . $toDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, [SCHOOL_NAME . ' - STAFF ATTENDANCE REGISTER LOGS']);
    fputcsv($output, ['Period:', "$fromDate to $toDate"]);
    fputcsv($output, ['Total Cumulative Hours Worked:', $cumTotalHours . ' hrs']);
    fputcsv($output, ['Punctuality Index:', $punctualityPct . '%']);
    fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, ['Log Date', 'Voucher Ref', 'Emp ID', 'Employee Name', 'Department', 'Designation', 'Status', 'Check In', 'Check Out', 'Hours Worked', 'Remarks']);
    
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
            $r['date'],
            'ATT-' . str_pad($r['id'], 6, '0', STR_PAD_LEFT),
            $r['employee_no'],
            $r['first_name'] . ' ' . $r['last_name'],
            $r['department'],
            $r['designation'],
            $r['status'],
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
.clock-badge { font-size: 0.78rem; font-family: monospace; }
.register-row:hover { background-color: rgba(13, 110, 253, 0.025); }
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
                    <span class="fw-bold uppercase tracking-wider" style="font-size: 0.72rem; letter-spacing: 1px; color: #60a5fa;">CUMULATIVE ATTENDANCE LEDGER</span>
                </div>
                <h2 class="fw-extrabold text-white mb-2 tracking-tight d-flex align-items-center gap-2">
                    <i class="fa-solid fa-address-card text-warning fs-2 me-2"></i>Staff Attendance Register
                </h2>
                <p class="mb-0 leading-relaxed" style="font-size: 0.95rem; color: #94a3b8;">
                    Track cumulative logs, view physical check-in clock-outs, and verify employee summaries.
                </p>
            </div>
            
            <div class="col-lg-5 text-lg-end">
                <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end">
                    <a href="?action=export&employee_id=<?php echo urlencode($filterEmpId); ?>&employee_name=<?php echo urlencode($filterEmpName); ?>&department=<?php echo urlencode($filterDept); ?>&designation=<?php echo urlencode($filterDesig); ?>&from_date=<?php echo $fromDate; ?>&to_date=<?php echo $toDate; ?>" class="btn btn-success px-4 py-2 shadow-sm rounded-pill fw-semibold">
                        <i class="fa-solid fa-file-csv me-1"></i>Export Register CSV
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 6 Cumulative Summary Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center bg-white border-start border-primary border-4">
            <div class="small text-muted fw-semibold text-uppercase" style="font-size:0.7rem;">Cumulative Logs</div>
            <div class="fs-3 fw-bold text-dark my-1"><?php echo $cumStats['total_logs']; ?></div>
            <div class="small text-muted" style="font-size:0.7rem;">Total Register Entries</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center" style="background-color: #f0fdf4;">
            <div class="small text-success fw-semibold text-uppercase" style="font-size:0.7rem;">Physical Check-Ins</div>
            <div class="fs-3 fw-bold text-success my-1"><?php echo $cumStats['clock_ins']; ?></div>
            <div class="small text-success-50" style="font-size:0.7rem;">Verified In-Time</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center" style="background-color: #ecfeff;">
            <div class="small text-info fw-semibold text-uppercase" style="font-size:0.7rem;">Clock-Outs Logged</div>
            <div class="fs-3 fw-bold text-info my-1"><?php echo $cumStats['clock_outs']; ?></div>
            <div class="small text-info-50" style="font-size:0.7rem;">Shift Exits Verified</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center" style="background-color: #faf5ff;">
            <div class="small text-primary fw-semibold text-uppercase" style="font-size:0.7rem;">Cumulative Hours</div>
            <div class="fs-3 fw-bold text-primary my-1"><?php echo $cumTotalHours; ?> <span class="fs-6 font-monospace">hrs</span></div>
            <div class="small text-primary-50" style="font-size:0.7rem;">Total On-Duty Time</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center" style="background-color: #fffbeb;">
            <div class="small text-warning fw-semibold text-uppercase" style="font-size:0.7rem;">Late Arrivals</div>
            <div class="fs-3 fw-bold text-warning my-1"><?php echo $cumStats['late']; ?></div>
            <div class="small text-warning-50" style="font-size:0.7rem;">Shift Delays</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center text-white" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6);">
            <div class="small text-white-50 fw-semibold text-uppercase" style="font-size:0.7rem;">Punctuality Index</div>
            <div class="fs-3 fw-bold text-white my-1"><?php echo $punctualityPct; ?>%</div>
            <div class="small text-white-50" style="font-size:0.7rem;">Turnout Percentage</div>
        </div>
    </div>
</div>

<!-- Filters Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-sliders me-2 text-primary"></i>Register Search & Date Range Parameters</h6>
        <form method="GET" class="row g-3 align-items-end" id="registerFilterForm">
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Employee ID</label>
                <input type="text" class="form-control form-control-sm font-monospace" name="employee_id" value="<?php echo htmlspecialchars($filterEmpId); ?>" placeholder="EMP-2024-001">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Employee Name</label>
                <input type="text" class="form-control form-control-sm" name="employee_name" value="<?php echo htmlspecialchars($filterEmpName); ?>" placeholder="Search name...">
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
                <label class="form-label small fw-semibold text-muted mb-1">Date From</label>
                <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Date To</label>
                <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>
            <div class="col-12 text-end mt-3">
                <a href="staff_register.php" class="btn btn-sm btn-outline-secondary px-3 me-2 rounded-pill">Reset Filters</a>
                <button type="submit" class="btn btn-sm btn-primary px-4 py-2 rounded-pill fw-semibold">
                    <i class="fa-solid fa-magnifying-glass me-2"></i>Search Register
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Single Employee Summary Dossier (Shown when filtered by specific Employee ID) -->
    <?php if ($singleEmployee): 
        $fullName = $singleEmployee['name'];
        $avatarBg = getAvatarColor($fullName);
        $initials = strtoupper(substr($fullName, 0, 1));
    ?>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm text-center p-4 mb-4" style="border-radius:14px; background:#fff;">
                <div class="rounded-circle text-white d-flex align-items-center justify-content-center me-auto ms-auto mb-3 fw-bold shadow" style="width:80px; height:80px; background-color: <?php echo $avatarBg; ?>; font-size: 2rem;">
                    <?php echo $initials; ?>
                </div>
                <h5 class="fw-bold text-dark mb-1"><?php echo sanitize($singleEmployee['name']); ?></h5>
                <span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill mb-3 font-monospace"><?php echo sanitize($singleEmployee['employee_no']); ?></span>
                
                <table class="table table-borderless table-sm text-start small mb-4 border-top pt-3">
                    <tr>
                        <td class="text-muted">Department:</td>
                        <td class="fw-bold text-dark text-end"><?php echo sanitize($singleEmployee['department']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Designation:</td>
                        <td class="fw-bold text-dark text-end"><?php echo sanitize($singleEmployee['designation']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Date of Joining:</td>
                        <td class="fw-bold text-dark text-end"><?php echo !empty($singleEmployee['joining_date']) ? date('d M Y', strtotime($singleEmployee['joining_date'])) : '—'; ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Cumulative Duty Hours:</td>
                        <td class="fw-bold text-primary text-end"><?php echo $singleEmployee['total_hours']; ?> hrs</td>
                    </tr>
                </table>

                <h6 class="fw-bold text-secondary text-uppercase small text-start border-bottom pb-2 mb-3">Cumulative Ratios</h6>
                <div class="row g-2 mb-4">
                    <div class="col-4">
                        <div class="p-2 border rounded bg-light">
                            <span class="small text-muted d-block" style="font-size:0.7rem;">Present</span>
                            <strong class="text-success"><?php echo $singleEmployee['present']; ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-light">
                            <span class="small text-muted d-block" style="font-size:0.7rem;">Absent</span>
                            <strong class="text-danger"><?php echo $singleEmployee['absent']; ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-light">
                            <span class="small text-muted d-block" style="font-size:0.7rem;">Late</span>
                            <strong class="text-warning"><?php echo $singleEmployee['late']; ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-light">
                            <span class="small text-muted d-block" style="font-size:0.7rem;">Leave</span>
                            <strong class="text-info"><?php echo $singleEmployee['leave']; ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-light">
                            <span class="small text-muted d-block" style="font-size:0.7rem;">Half Day</span>
                            <strong class="text-primary"><?php echo $singleEmployee['halfday']; ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-light">
                            <span class="small text-muted d-block" style="font-size:0.7rem;">Rate</span>
                            <strong class="text-primary"><?php echo $singleEmployee['percent']; ?>%</strong>
                        </div>
                    </div>
                </div>

                <div class="bg-primary text-white p-3 rounded-3 shadow-sm">
                    <div class="small text-white-50 text-uppercase fw-semibold mb-1" style="font-size:0.7rem;">PUNCTUALITY RATING</div>
                    <h3 class="fw-bold mb-0"><?php echo $singleEmployee['percent']; ?>%</h3>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Right Column: Register Ledger Table -->
    <div class="<?php echo $singleEmployee ? 'col-lg-8' : 'col-12'; ?>">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:14px; overflow:hidden;">
            <div class="card-header bg-white p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span class="fw-bold text-dark"><i class="fa-solid fa-list-check me-2 text-primary"></i>Daily Log Registrations (<span id="visibleRegisterCount"><?php echo count($records); ?></span> Records)</span>
                <div class="position-relative" style="width: 250px;">
                    <input type="text" class="form-control form-control-sm ps-4 rounded-pill" id="registerSearchInput" placeholder="Filter register by keyword...">
                    <i class="fa-solid fa-magnifying-glass position-absolute start-0 top-50 translate-middle-y ms-2 text-muted small"></i>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table custom-table table-hover align-middle mb-0" id="registerLogsTable">
                    <thead class="bg-light">
                        <tr>
                            <th width="120" class="ps-4">Date</th>
                            <th width="110">Emp ID</th>
                            <th>Employee Name</th>
                            <th width="200">Physical Clock In / Out</th>
                            <th width="100">Duration</th>
                            <th width="120">Status</th>
                            <th>Remarks</th>
                            <th width="70" class="text-end pe-4">Voucher</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-folder-open fs-1 d-block mb-2 opacity-50"></i>
                                    <h6 class="fw-bold mb-1">No Attendance Register Entries Found</h6>
                                    <p class="small mb-0">No attendance logs match the filter parameters for period <?php echo "$fromDate to $toDate"; ?>.</p>
                                </td>
                            </tr>
                        <?php else: foreach ($records as $r): 
                            $fullName = trim($r['first_name'] . ' ' . $r['last_name']);
                            $avatarBg = getAvatarColor($fullName);
                            $initials = strtoupper(substr($r['first_name'] ?? 'S', 0, 1) . substr($r['last_name'] ?? 'M', 0, 1));
                            
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

                            $badgeClass = 'bg-light text-muted border';
                            if ($r['status'] === 'Present')    $badgeClass = 'bg-success-soft text-success';
                            elseif ($r['status'] === 'Absent')   $badgeClass = 'bg-danger-soft text-danger';
                            elseif ($r['status'] === 'Late')     $badgeClass = 'bg-warning-soft text-warning';
                            elseif ($r['status'] === 'Leave')    $badgeClass = 'bg-info-soft text-info';
                            elseif ($r['status'] === 'Half Day') $badgeClass = 'bg-primary-soft text-primary';
                        ?>
                            <tr class="register-row" data-search="<?php echo htmlspecialchars(strtolower($r['employee_no'] . ' ' . $fullName . ' ' . $r['department'] . ' ' . $r['designation'] . ' ' . $r['status'])); ?>">
                                <td class="ps-4 fw-bold text-dark"><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                                <td><span class="badge bg-light text-dark border font-monospace"><?php echo sanitize($r['employee_no']); ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle text-white d-flex align-items-center justify-content-center me-2 fw-bold shadow-sm flex-shrink-0" style="width:30px; height:30px; background-color: <?php echo $avatarBg; ?>; font-size: 0.75rem;">
                                            <?php echo $initials; ?>
                                        </div>
                                        <span class="fw-bold text-dark"><?php echo sanitize($fullName); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($r['check_in_time'] || $r['check_out_time']): ?>
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="badge bg-success-soft text-success clock-badge" title="Check In">
                                                <i class="fa-solid fa-arrow-right-to-bracket me-1"></i><?php echo $cin; ?>
                                            </span>
                                            <i class="fa-solid fa-ellipsis text-muted small"></i>
                                            <span class="badge bg-info-soft text-info clock-badge" title="Check Out">
                                                <i class="fa-solid fa-arrow-right-from-bracket me-1"></i><?php echo $cout; ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">Clock-ins unrecorded</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="small font-monospace text-muted"><?php echo $durationStr; ?></span></td>
                                <td>
                                    <span class="badge <?php echo $badgeClass; ?> px-3 py-2 rounded-pill fw-bold" style="font-size:0.75rem;">
                                        <?php echo $r['status']; ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?php echo sanitize($r['remarks'] ?: '—'); ?></td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-primary btn-print-slip rounded-circle"
                                            title="Print Attendance Record Voucher"
                                            data-ref="ATT-<?php echo str_pad($r['id'], 6, '0', STR_PAD_LEFT); ?>"
                                            data-date="<?php echo date('d M Y', strtotime($r['date'])); ?>"
                                            data-empid="<?php echo htmlspecialchars($r['employee_no']); ?>"
                                            data-name="<?php echo htmlspecialchars($fullName); ?>"
                                            data-dept="<?php echo htmlspecialchars($r['department']); ?>"
                                            data-desig="<?php echo htmlspecialchars($r['designation']); ?>"
                                            data-status="<?php echo htmlspecialchars($r['status']); ?>"
                                            data-cin="<?php echo $cin; ?>"
                                            data-cout="<?php echo $cout; ?>"
                                            data-worked="<?php echo $durationStr; ?>"
                                            data-remarks="<?php echo htmlspecialchars($r['remarks'] ?? ''); ?>">
                                        <i class="fa-solid fa-print"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Printable Slip Voucher Template -->
<div id="printSlipTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 25px; border: 2px solid #0d6efd; border-radius:12px; width: 520px; margin: 0 auto; color: #1e293b;">
        <div style="text-align: center; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; margin-bottom: 18px;">
            <h3 style="margin: 0; text-transform: uppercase; color: #0d6efd; font-size: 20px; font-weight: bold;"><?php echo SCHOOL_NAME; ?></h3>
            <p style="margin: 3px 0 0 0; font-size: 11px; color:#64748b;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 12px 0 0 0; background: #f1f5f9; color: #0f172a; padding: 5px 12px; border-radius: 6px; letter-spacing: 1px; display: inline-block; font-size: 12px;">ATTENDANCE RECORD VOUCHER</h4>
        </div>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;" cellpadding="6">
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="color: #64748b;"><strong>Voucher Ref ID:</strong></td>
                <td style="text-align: right; font-weight: bold; color: #0d6efd;" id="p_ref"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="color: #64748b;"><strong>Attendance Date:</strong></td>
                <td style="text-align: right; font-weight: bold;" id="p_date"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="color: #64748b;"><strong>Employee ID:</strong></td>
                <td style="text-align: right; font-family: monospace;" id="p_empid"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="color: #64748b;"><strong>Employee Name:</strong></td>
                <td style="text-align: right; font-weight: bold;" id="p_name"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="color: #64748b;"><strong>Department / Designation:</strong></td>
                <td style="text-align: right;" id="p_dept_desig"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="color: #64748b;"><strong>Attendance Status:</strong></td>
                <td style="text-align: right; font-weight: bold; text-transform: uppercase;" id="p_status"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="color: #64748b;"><strong>Physical Check In:</strong></td>
                <td style="text-align: right;" id="p_cin"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="color: #64748b;"><strong>Physical Check Out:</strong></td>
                <td style="text-align: right;" id="p_cout"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="color: #64748b;"><strong>Duty Hours Worked:</strong></td>
                <td style="text-align: right; font-weight: bold; color: #059669;" id="p_worked"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="color: #64748b;"><strong>Remarks / Notes:</strong></td>
                <td style="text-align: right;" id="p_remarks"></td>
            </tr>
        </table>
        
        <table style="width: 100%; margin-top: 50px; font-size: 11px; text-align: center;">
            <tr>
                <td style="width: 50%;"><div style="border-top: 1px solid #000; width: 140px; margin: 0 auto; padding-top: 4px;">Staff Member Signature</div></td>
                <td style="width: 50%;"><div style="border-top: 1px solid #000; width: 140px; margin: 0 auto; padding-top: 4px;">HR / Verifier Approval</div></td>
            </tr>
        </table>
    </div>
</div>

<?php $extraJS = '<script>
document.addEventListener("DOMContentLoaded", function() {
    // Search instant filter for register table
    const searchInput = document.getElementById("registerSearchInput");
    if (searchInput) {
        searchInput.addEventListener("keyup", function() {
            const query = this.value.toLowerCase().trim();
            let visible = 0;
            document.querySelectorAll(".register-row").forEach(row => {
                const searchData = row.dataset.search || "";
                if (searchData.includes(query)) {
                    row.style.display = "";
                    visible++;
                } else {
                    row.style.display = "none";
                }
            });
            const visCount = document.getElementById("visibleRegisterCount");
            if (visCount) visCount.textContent = visible;
        });
    }

    // Print Voucher Slips
    document.querySelectorAll(".btn-print-slip").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("p_ref").textContent        = this.dataset.ref;
            document.getElementById("p_date").textContent       = this.dataset.date;
            document.getElementById("p_empid").textContent      = this.dataset.empid;
            document.getElementById("p_name").textContent       = this.dataset.name;
            document.getElementById("p_dept_desig").textContent = this.dataset.dept + " / " + this.dataset.desig;
            document.getElementById("p_status").textContent     = this.dataset.status;
            document.getElementById("p_cin").textContent        = this.dataset.cin;
            document.getElementById("p_cout").textContent       = this.dataset.cout;
            document.getElementById("p_worked").textContent     = this.dataset.worked;
            document.getElementById("p_remarks").textContent    = this.dataset.remarks || "—";
            
            const printContent = document.getElementById("printSlipTemplate").innerHTML;
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Attendance Record Voucher</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
