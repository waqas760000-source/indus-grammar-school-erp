<?php
/**
 * Indus Grammar School ERP - Monthly Staff Attendance Matrix & Audit Report
 * Version 5.0.0 - Executive Monthly Calendar & Check-In Audit Console
 */

$pageTitle = 'Monthly Staff Attendance Report';
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

// Default parameters
$filterMonth = sanitize($_GET['month'] ?? date('m'));
$filterYear  = sanitize($_GET['year'] ?? date('Y'));
$filterDept  = sanitize($_GET['department'] ?? '');
$filterDesig = sanitize($_GET['designation'] ?? '');

$filterMonth = str_pad($filterMonth, 2, '0', STR_PAD_LEFT);
$daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, (int)$filterMonth, (int)$filterYear);
$monthPeriod = date('F Y', strtotime("$filterYear-$filterMonth-01"));

// Fetch unique dropdown values
$departments  = [];
$designations = [];
try {
    $departments  = $db->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
    $designations = $db->query("SELECT DISTINCT designation FROM staff WHERE designation IS NOT NULL AND designation != '' ORDER BY designation ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Load active staff members matching department and designation filters
$where = " WHERE s.status = 'Active'";
$params = [];
if ($filterDept !== '') {
    $where .= " AND s.department = :dept";
    $params['dept'] = $filterDept;
}
if ($filterDesig !== '') {
    $where .= " AND s.designation = :desig";
    $params['desig'] = $filterDesig;
}

$staffList = [];
try {
    $stmt = $db->prepare("SELECT s.id, s.first_name, s.last_name, s.employee_no, s.department, s.designation FROM staff s $where ORDER BY s.employee_no ASC");
    $stmt->execute($params);
    $staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Load all attendance records for this month to index in PHP
$attendanceLookup = [];
$checkinLookup    = [];
$checkoutLookup   = [];
$remarksLookup    = [];
$monthStats       = ['total_p' => 0, 'total_a' => 0, 'total_l' => 0, 'total_lt' => 0, 'total_hd' => 0];

if (!empty($staffList)) {
    $startDate = "$filterYear-$filterMonth-01";
    $endDate   = "$filterYear-$filterMonth-$daysInMonth";
    
    try {
        $stmt = $db->prepare("
            SELECT staff_id, date, status, check_in_time, check_out_time, remarks
            FROM staff_attendance 
            WHERE date BETWEEN :start AND :end
        ");
        $stmt->execute(['start' => $startDate, 'end' => $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $day = (int)date('d', strtotime($row['date']));
            $sid = $row['staff_id'];
            $st  = $row['status'];

            $attendanceLookup[$sid][$day] = $st;
            $checkinLookup[$sid][$day]    = $row['check_in_time'];
            $checkoutLookup[$sid][$day]   = $row['check_out_time'];
            $remarksLookup[$sid][$day]    = $row['remarks'];

            if ($st === 'Present') $monthStats['total_p']++;
            elseif ($st === 'Absent') $monthStats['total_a']++;
            elseif ($st === 'Leave') $monthStats['total_l']++;
            elseif ($st === 'Late') $monthStats['total_lt']++;
            elseif ($st === 'Half Day') $monthStats['total_hd']++;
        }
    } catch (Exception $e) {}
}

$totalLogs = $monthStats['total_p'] + $monthStats['total_a'] + $monthStats['total_l'] + $monthStats['total_lt'] + $monthStats['total_hd'];
$attendedLogs = $monthStats['total_p'] + $monthStats['total_lt'] + $monthStats['total_hd'];
$overallRate = $totalLogs > 0 ? round(($attendedLogs / $totalLogs) * 100, 1) : 0;

// Map status symbols and styling
$statusSymbols = [
    'Present'  => '<span class="badge bg-success-soft text-success px-1" title="Present">P</span>',
    'Absent'   => '<span class="badge bg-danger-soft text-danger px-1" title="Absent">A</span>',
    'Late'     => '<span class="badge bg-warning-soft text-warning px-1" title="Late Arrival">LT</span>',
    'Leave'    => '<span class="badge bg-info-soft text-info px-1" title="On Leave">L</span>',
    'Half Day' => '<span class="badge bg-primary-soft text-primary px-1" title="Half Day">HD</span>',
];

// Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="monthly_staff_attendance_register_' . $filterYear . '_' . $filterMonth . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, [SCHOOL_NAME . ' - MONTHLY STAFF ATTENDANCE REGISTER']);
    fputcsv($output, ['Period:', $monthPeriod]);
    fputcsv($output, ['Department Filter:', $filterDept ?: 'All Departments']);
    fputcsv($output, ['Overall Attendance Turnout:', $overallRate . '%']);
    fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    $headerRow = ['Emp ID', 'Employee Name', 'Department', 'Designation'];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $headerRow[] = "Day " . $d;
    }
    $headerRow = array_merge($headerRow, ['Present (P)', 'Absent (A)', 'Leave (L)', 'Late (LT)', 'Half Day (HD)', 'Turnout Rate %']);
    fputcsv($output, $headerRow);
    
    foreach ($staffList as $st) {
        $row = [
            $st['employee_no'],
            $st['first_name'] . ' ' . $st['last_name'],
            $st['department'],
            $st['designation']
        ];
        $p = 0; $a = 0; $l = 0; $lt = 0; $hd = 0;
        
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $status = $attendanceLookup[$st['id']][$day] ?? '';
            if ($status === 'Present') { $row[] = 'P'; $p++; }
            elseif ($status === 'Absent') { $row[] = 'A'; $a++; }
            elseif ($status === 'Leave') { $row[] = 'L'; $l++; }
            elseif ($status === 'Late') { $row[] = 'LT'; $lt++; }
            elseif ($status === 'Half Day') { $row[] = 'HD'; $hd++; }
            else { $row[] = '—'; }
        }
        $totalMarked = $p + $a + $l + $lt + $hd;
        $presentDays = $p + $lt + $hd;
        $rate = $totalMarked > 0 ? round(($presentDays / $totalMarked) * 100, 1) : 0;
        
        $row = array_merge($row, [$p, $a, $l, $lt, $hd, $rate . '%']);
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
?>

<style>
.matrix-col-sticky { position: sticky; left: 0; background: #ffffff; z-index: 10; border-right: 2px solid #e2e8f0; }
.weekend-cell { background-color: #f8fafc !important; }
.audit-row-clickable { cursor: pointer; transition: background-color 0.15s ease-in-out; }
.audit-row-clickable:hover { background-color: rgba(13, 110, 253, 0.04) !important; }
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
                    <span class="fw-bold uppercase tracking-wider" style="font-size: 0.72rem; letter-spacing: 1px; color: #60a5fa;">MONTHLY ATTENDANCE REGISTER</span>
                </div>
                <h2 class="fw-extrabold text-white mb-2 tracking-tight d-flex align-items-center gap-2">
                    <i class="fa-solid fa-calendar-week text-warning fs-2 me-2"></i>Monthly Staff Attendance Report
                </h2>
                <p class="mb-0 leading-relaxed" style="font-size: 0.95rem; color: #94a3b8;">
                    Generate complete monthly calendars, audit employee check-ins, and export registers.
                </p>
            </div>
            
            <div class="col-lg-5 text-lg-end">
                <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end">
                    <button class="btn btn-outline-light px-3 py-2 shadow-sm rounded-pill fw-semibold" id="btnPrintReport">
                        <i class="fa-solid fa-print me-1 text-warning"></i>Print Monthly Register
                    </button>
                    <a href="?action=export&month=<?php echo $filterMonth; ?>&year=<?php echo $filterYear; ?>&department=<?php echo urlencode($filterDept); ?>&designation=<?php echo urlencode($filterDesig); ?>" class="btn btn-success px-4 py-2 shadow-sm rounded-pill fw-semibold">
                        <i class="fa-solid fa-file-csv me-1"></i>Export Register CSV
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 6 Monthly KPI Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center bg-white border-start border-primary border-4">
            <div class="small text-muted fw-semibold text-uppercase" style="font-size:0.7rem;">Active Staff</div>
            <div class="fs-3 fw-bold text-dark my-1"><?php echo count($staffList); ?></div>
            <div class="small text-muted" style="font-size:0.7rem;"><?php echo $monthPeriod; ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center" style="background-color: #f0fdf4;">
            <div class="small text-success fw-semibold text-uppercase" style="font-size:0.7rem;">Present Days</div>
            <div class="fs-3 fw-bold text-success my-1"><?php echo $monthStats['total_p']; ?></div>
            <div class="small text-success-50" style="font-size:0.7rem;">Total Present Logs</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center" style="background-color: #fef2f2;">
            <div class="small text-danger fw-semibold text-uppercase" style="font-size:0.7rem;">Absent Days</div>
            <div class="fs-3 fw-bold text-danger my-1"><?php echo $monthStats['total_a']; ?></div>
            <div class="small text-danger-50" style="font-size:0.7rem;">Total Unexcused</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center" style="background-color: #fffbeb;">
            <div class="small text-warning fw-semibold text-uppercase" style="font-size:0.7rem;">Late Incidents</div>
            <div class="fs-3 fw-bold text-warning my-1"><?php echo $monthStats['total_lt']; ?></div>
            <div class="small text-warning-50" style="font-size:0.7rem;">Shift Delays</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center" style="background-color: #ecfeff;">
            <div class="small text-info fw-semibold text-uppercase" style="font-size:0.7rem;">Leaves Logged</div>
            <div class="fs-3 fw-bold text-info my-1"><?php echo $monthStats['total_l']; ?></div>
            <div class="small text-info-50" style="font-size:0.7rem;">Approved Leaves</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center text-white" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6);">
            <div class="small text-white-50 fw-semibold text-uppercase" style="font-size:0.7rem;">Monthly Turnout</div>
            <div class="fs-3 fw-bold text-white my-1"><?php echo $overallRate; ?>%</div>
            <div class="small text-white-50" style="font-size:0.7rem;">Overall Rate</div>
        </div>
    </div>
</div>

<!-- Legend Badge Bar -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-3 d-flex flex-wrap gap-3 align-items-center bg-white justify-content-between">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="small fw-bold text-secondary me-2"><i class="fa-solid fa-tags me-1 text-primary"></i>Legend Index:</span>
            <span class="badge bg-success-soft text-success px-3 py-2 rounded-pill"><strong class="me-1">P</strong> Present</span>
            <span class="badge bg-danger-soft text-danger px-3 py-2 rounded-pill"><strong class="me-1">A</strong> Absent</span>
            <span class="badge bg-info-soft text-info px-3 py-2 rounded-pill"><strong class="me-1">L</strong> On Leave</span>
            <span class="badge bg-warning-soft text-warning px-3 py-2 rounded-pill"><strong class="me-1">LT</strong> Late</span>
            <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill"><strong class="me-1">HD</strong> Half Day</span>
        </div>
        <div class="small text-muted fst-italic">
            <i class="fa-solid fa-circle-info me-1 text-primary"></i>Click on any employee row to inspect complete check-in audit logs.
        </div>
    </div>
</div>

<!-- Roster Filters Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-sliders me-2 text-primary"></i>Period & Roster Filters</h6>
        <form method="GET" class="row g-3 align-items-end" id="monthlyFilterForm">
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Select Month</label>
                <select class="form-select form-select-sm" name="month">
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $val = str_pad($m, 2, '0', STR_PAD_LEFT);
                    ?>
                        <option value="<?php echo $val; ?>" <?php echo ($filterMonth === $val) ? 'selected' : ''; ?>><?php echo date('F', strtotime("2026-$val-01")); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Select Year</label>
                <select class="form-select form-select-sm" name="year">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 2; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($filterYear == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Department</label>
                <select class="form-select form-select-sm" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($filterDept === $d) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Designation</label>
                <select class="form-select form-select-sm" name="designation">
                    <option value="">All Designations</option>
                    <?php foreach ($designations as $ds): ?>
                        <option value="<?php echo htmlspecialchars($ds); ?>" <?php echo ($filterDesig === $ds) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ds); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100 py-1 fw-semibold">
                    <i class="fa-solid fa-rotate me-1"></i>Generate Matrix
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Matrix Table Register Card -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px; overflow:hidden;" id="reportSection">
    <div class="card-header bg-white p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span class="fw-bold text-dark"><i class="fa-solid fa-table-cells me-2 text-primary"></i>Monthly Calendar Matrix — <?php echo $monthPeriod; ?> (<span id="visibleMonthlyCount"><?php echo count($staffList); ?></span> Staff)</span>
        <div class="position-relative" style="width: 250px;">
            <input type="text" class="form-control form-control-sm ps-4 rounded-pill" id="matrixSearchInput" placeholder="Filter employee by name or ID...">
            <i class="fa-solid fa-magnifying-glass position-absolute start-0 top-50 translate-middle-y ms-2 text-muted small"></i>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered custom-table table-hover align-middle mb-0 text-center" id="monthlyMatrixTable" style="font-size:0.75rem;">
            <thead class="bg-light">
                <tr>
                    <th class="text-start ps-3 matrix-col-sticky" style="font-size:0.8rem; min-width: 180px;">Employee Name</th>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                        $dayDate = sprintf('%04d-%02d-%02d', $filterYear, $filterMonth, $d);
                        $dayOfWeek = date('D', strtotime($dayDate));
                        $isWeekend = ($dayOfWeek === 'Sun');
                    ?>
                        <th width="32" class="<?php echo $isWeekend ? 'weekend-cell text-danger' : ''; ?>">
                            <div style="font-size:0.68rem;" class="text-muted"><?php echo substr($dayOfWeek, 0, 1); ?></div>
                            <div class="fw-bold"><?php echo $d; ?></div>
                        </th>
                    <?php endfor; ?>
                    <th class="bg-light fw-bold text-success" width="34" title="Present">P</th>
                    <th class="bg-light fw-bold text-danger" width="34" title="Absent">A</th>
                    <th class="bg-light fw-bold text-info" width="34" title="Leave">L</th>
                    <th class="bg-light fw-bold text-warning" width="34" title="Late">LT</th>
                    <th class="bg-light fw-bold text-primary" width="34" title="Half Day">HD</th>
                    <th class="bg-primary text-white fw-bold" width="60">Turnout</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staffList)): ?>
                    <tr>
                        <td colspan="<?php echo $daysInMonth + 7; ?>" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-users-slash fs-1 d-block mb-2 opacity-50"></i>
                            <h6 class="fw-bold mb-1">No Active Staff Members Found</h6>
                            <p class="small mb-0">No employees match the filter criteria for <?php echo $monthPeriod; ?>.</p>
                        </td>
                    </tr>
                <?php else: foreach ($staffList as $st): 
                    $fullName = trim($st['first_name'] . ' ' . $st['last_name']);
                    $avatarBg = getAvatarColor($fullName);
                    $initials = strtoupper(substr($st['first_name'] ?? 'S', 0, 1) . substr($st['last_name'] ?? 'M', 0, 1));
                    $p = 0; $a = 0; $l = 0; $lt = 0; $hd = 0;
                ?>
                    <tr class="audit-row-clickable matrix-row" 
                        data-staff-id="<?php echo $st['id']; ?>" 
                        data-search="<?php echo htmlspecialchars(strtolower($st['employee_no'] . ' ' . $fullName . ' ' . $st['department'] . ' ' . $st['designation'])); ?>"
                        onclick="inspectStaffAudit(<?php echo $st['id']; ?>)">
                        <td class="text-start ps-3 matrix-col-sticky">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle text-white d-flex align-items-center justify-content-center me-2 fw-bold shadow-sm flex-shrink-0" style="width:28px; height:28px; background-color: <?php echo $avatarBg; ?>; font-size: 0.72rem;">
                                    <?php echo $initials; ?>
                                </div>
                                <div class="text-truncate">
                                    <div class="fw-bold text-dark mb-0 text-truncate" style="max-width:130px; font-size:0.78rem;"><?php echo sanitize($fullName); ?></div>
                                    <div class="small text-muted font-monospace" style="font-size:0.65rem;"><?php echo sanitize($st['employee_no']); ?></div>
                                </div>
                            </div>
                        </td>
                        <?php for ($day = 1; $day <= $daysInMonth; $day++): 
                            $status = $attendanceLookup[$st['id']][$day] ?? '';
                            $dayDate = sprintf('%04d-%02d-%02d', $filterYear, $filterMonth, $day);
                            $isSun = (date('D', strtotime($dayDate)) === 'Sun');

                            if ($status === 'Present') $p++;
                            elseif ($status === 'Absent') $a++;
                            elseif ($status === 'Leave') $l++;
                            elseif ($status === 'Late') $lt++;
                            elseif ($status === 'Half Day') $hd++;
                        ?>
                            <td class="<?php echo ($isSun && empty($status)) ? 'weekend-cell' : ''; ?>">
                                <?php echo $status ? ($statusSymbols[$status] ?? '') : ($isSun ? '<span class="text-muted opacity-50" style="font-size:0.65rem;">Sun</span>' : '<span class="text-muted opacity-25">—</span>'); ?>
                            </td>
                        <?php endfor; 
                            $totalMarked = $p + $a + $l + $lt + $hd;
                            $presentCount = $p + $lt + $hd;
                            $rate = $totalMarked > 0 ? round(($presentCount / $totalMarked) * 100, 1) : 0;
                        ?>
                        <td class="fw-bold text-success bg-light-soft"><?php echo $p; ?></td>
                        <td class="fw-bold text-danger bg-light-soft"><?php echo $a; ?></td>
                        <td class="fw-bold text-info bg-light-soft"><?php echo $l; ?></td>
                        <td class="fw-bold text-warning bg-light-soft"><?php echo $lt; ?></td>
                        <td class="fw-bold text-primary bg-light-soft"><?php echo $hd; ?></td>
                        <td class="fw-bold bg-primary-soft text-primary"><?php echo $rate; ?>%</td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Employee Check-In Audit Inspector Modal -->
<div class="modal fade" id="auditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-top-left-radius:16px; border-top-right-radius:16px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary-soft p-2 d-flex align-items-center justify-content-center text-primary" style="width:42px; height:42px;">
                        <i class="fa-solid fa-user-check fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="auditStaffName">Employee Attendance Audit</h5>
                        <p class="small text-slate-300 mb-0" id="auditStaffMeta">Check-in and check-out logs inspection</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="auditModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary me-2"></div>
                    <span class="text-muted fw-semibold">Fetching employee check-in audit logs...</span>
                </div>
            </div>
            <div class="modal-footer bg-light border-top-0" style="border-bottom-left-radius:16px; border-bottom-right-radius:16px;">
                <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Printable Monthly Register Template -->
<div id="printReportTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 20px; color: #1e293b; max-width: 1000px; margin: 0 auto;">
        <div style="text-align: center; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; margin-bottom: 15px;">
            <h2 style="margin: 0; text-transform: uppercase; color: #0d6efd; font-size: 22px; font-weight: bold;"><?php echo SCHOOL_NAME; ?></h2>
            <p style="margin: 4px 0 0 0; font-size: 11px; color: #64748b;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 10px 0 0 0; background: #f1f5f9; color: #0f172a; padding: 5px 12px; border-radius: 6px; letter-spacing: 1px; display: inline-block; font-size: 13px;">MONTHLY STAFF ATTENDANCE REGISTER</h4>
        </div>
        
        <table style="width: 100%; font-size: 11px; margin-bottom: 12px; border-collapse: collapse;">
            <tr>
                <td style="padding: 3px 0;"><strong>Period:</strong> <?php echo $monthPeriod; ?></td>
                <td style="padding: 3px 0;"><strong>Department:</strong> <?php echo $filterDept ?: 'All Departments'; ?></td>
                <td style="padding: 3px 0; text-align: right;"><strong>Monthly Turnout Rate:</strong> <?php echo $overallRate; ?>%</td>
            </tr>
        </table>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 9px; text-align: center;" border="1" cellpadding="4" cellspacing="0">
            <thead>
                <tr style="background: #e2e8f0; color: #0f172a;">
                    <th style="text-align: left; font-weight: bold; width: 140px;">Employee Name</th>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                        <th><?php echo $d; ?></th>
                    <?php endfor; ?>
                    <th>P</th>
                    <th>A</th>
                    <th>L</th>
                    <th>LT</th>
                    <th>HD</th>
                    <th>Turnout</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffList as $st): 
                    $p = 0; $a = 0; $l = 0; $lt = 0; $hd = 0;
                ?>
                    <tr>
                        <td style="text-align: left; font-weight: bold;"><?php echo sanitize($st['first_name'] . ' ' . $st['last_name']); ?></td>
                        <?php for ($day = 1; $day <= $daysInMonth; $day++): 
                            $status = $attendanceLookup[$st['id']][$day] ?? '';
                            if ($status === 'Present') { echo '<td>P</td>'; $p++; }
                            elseif ($status === 'Absent') { echo '<td>A</td>'; $a++; }
                            elseif ($status === 'Leave') { echo '<td>L</td>'; $l++; }
                            elseif ($status === 'Late') { echo '<td>LT</td>'; $lt++; }
                            elseif ($status === 'Half Day') { echo '<td>HD</td>'; $hd++; }
                            else { echo '<td style="color:#cbd5e1;">—</td>'; }
                        endfor;
                        $totalMarked = $p + $a + $l + $lt + $hd;
                        $presentCount = $p + $lt + $hd;
                        $rate = $totalMarked > 0 ? round(($presentCount / $totalMarked) * 100, 1) : 0;
                        ?>
                        <td><strong><?php echo $p; ?></strong></td>
                        <td><strong><?php echo $a; ?></strong></td>
                        <td><strong><?php echo $l; ?></strong></td>
                        <td><strong><?php echo $lt; ?></strong></td>
                        <td><strong><?php echo $hd; ?></strong></td>
                        <td><strong><?php echo $rate; ?>%</strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <table style="width: 100%; margin-top: 40px; font-size: 11px; text-align: center;">
            <tr>
                <td style="width: 33%;"><div style="border-top: 1px solid #000; width: 140px; margin: 0 auto; padding-top: 5px;">HR / Attendance Officer</div></td>
                <td style="width: 33%;"><div style="border-top: 1px solid #000; width: 140px; margin: 0 auto; padding-top: 5px;">Finance Officer</div></td>
                <td style="width: 33%;"><div style="border-top: 1px solid #000; width: 140px; margin: 0 auto; padding-top: 5px;">Principal Approval</div></td>
            </tr>
        </table>
    </div>
</div>

<?php $extraJS = '<script>
function inspectStaffAudit(staffId) {
    const modalEl = document.getElementById("auditModal");
    const modal   = new bootstrap.Modal(modalEl);
    const body    = document.getElementById("auditModalBody");
    const month   = "' . $filterMonth . '";
    const year    = "' . $filterYear . '";

    body.innerHTML = \'<div class="text-center py-5"><div class="spinner-border text-primary me-2"></div><span class="text-muted fw-semibold">Fetching employee check-in audit logs...</span></div>\';
    modal.show();

    const fd = new FormData();
    fd.append("action", "get_staff_monthly_audit");
    fd.append("csrf_token", "' . csrfToken() . '");
    fd.append("staff_id", staffId);
    fd.append("month", month);
    fd.append("year", year);

    fetch("../../ajax/staff_attendance.php", { method: "POST", body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                body.innerHTML = \'<div class="alert alert-danger mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>\' + data.message + \'</div>\';
                return;
            }

            document.getElementById("auditStaffName").textContent = data.staff.first_name + " " + data.staff.last_name + " (" + data.staff.employee_no + ")";
            document.getElementById("auditStaffMeta").textContent = data.staff.department + " | " + data.staff.designation + " | Period: " + data.period;

            if (!data.logs || data.logs.length === 0) {
                body.innerHTML = \'<div class="text-center py-4 text-muted"><i class="fa-solid fa-clock-rotate-left fs-1 d-block mb-2 text-secondary opacity-50"></i>No check-in logs found for this period.</div>\';
                return;
            }

            let html = \'<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0" style="font-size:0.83rem;"><thead class="bg-light"><tr><th>Date</th><th>Status</th><th>Check In</th><th>Check Out</th><th>Duration</th><th>Remarks</th></tr></thead><tbody>\';
            
            data.logs.forEach(l => {
                let badge = "bg-light text-muted border";
                if (l.status === "Present") badge = "bg-success-soft text-success";
                else if (l.status === "Absent") badge = "bg-danger-soft text-danger";
                else if (l.status === "Late") badge = "bg-warning-soft text-warning";
                else if (l.status === "Leave") badge = "bg-info-soft text-info";
                else if (l.status === "Half Day") badge = "bg-primary-soft text-primary";

                let duration = "—";
                if (l.check_in_time && l.check_out_time) {
                    let tIn = new Date("1970-01-01T" + l.check_in_time);
                    let tOut = new Date("1970-01-01T" + l.check_out_time);
                    let diffMs = tOut - tIn;
                    if (diffMs > 0) {
                        let hrs = Math.floor(diffMs / 3600000);
                        let mins = Math.floor((diffMs % 3600000) / 60000);
                        duration = hrs + "h " + mins + "m";
                    }
                }

                html += \'<tr>\' +
                    \'<td class="fw-bold">\' + l.date + \'</td>\' +
                    \'<td><span class="badge \' + badge + \' px-2 py-1 rounded-pill">\' + l.status + \'</span></td>\' +
                    \'<td>\' + (l.check_in_time || "—") + \'</td>\' +
                    \'<td>\' + (l.check_out_time || "—") + \'</td>\' +
                    \'<td class="font-monospace text-muted">\' + duration + \'</td>\' +
                    \'<td class="small text-muted">\' + (l.remarks || "—") + \'</td>\' +
                \'</tr>\';
            });

            html += \'</tbody></table></div>\';
            body.innerHTML = html;
        })
        .catch(() => {
            body.innerHTML = \'<div class="alert alert-danger mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>Network communication error.</div>\';
        });
}

document.addEventListener("DOMContentLoaded", function() {
    // Search instant filter for monthly table
    const searchInput = document.getElementById("matrixSearchInput");
    if (searchInput) {
        searchInput.addEventListener("keyup", function() {
            const query = this.value.toLowerCase().trim();
            let visible = 0;
            document.querySelectorAll(".matrix-row").forEach(row => {
                const searchData = row.dataset.search || "";
                if (searchData.includes(query)) {
                    row.style.display = "";
                    visible++;
                } else {
                    row.style.display = "none";
                }
            });
            const visCount = document.getElementById("visibleMonthlyCount");
            if (visCount) visCount.textContent = visible;
        });
    }

    // Print Report Button
    const printBtn = document.getElementById("btnPrintReport");
    if (printBtn) {
        printBtn.addEventListener("click", function() {
            const printContent = document.getElementById("printReportTemplate").innerHTML;
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Monthly Staff Attendance Register</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
