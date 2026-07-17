<?php
/**
 * Indus Grammar School ERP - Daily Staff Attendance Report
 * Version 4.0.0
 */

$pageTitle = 'Daily Staff Attendance Report';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('attendance_view');

$db = Database::getConnection();

// Filters
$filterDate  = sanitize($_GET['date'] ?? date('Y-m-d'));
$filterDept  = sanitize($_GET['department'] ?? '');
$filterDesig = sanitize($_GET['designation'] ?? '');
$filterStatus = sanitize($_GET['status'] ?? '');

// Fetch unique filters dynamically
$departments = [];
$designations = [];
try {
    $departments = $db->query("SELECT DISTINCT department FROM staff ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
    $designations = $db->query("SELECT DISTINCT designation FROM staff ORDER BY designation ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Setup query
$where = " WHERE sa.date = :date";
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
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    // Present, Late, and Half Day are physically present
    $presentCount = $stats['present'] + $stats['late'] + $stats['halfday'];
    $stats['percent'] = round(($presentCount / $stats['total']) * 100, 2);
}

// Handle Export CSV
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="daily_staff_attendance_' . $filterDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, [SCHOOL_NAME . ' - DAILY STAFF ATTENDANCE REPORT']);
    fputcsv($output, ['Report Date:', $filterDate]);
    fputcsv($output, ['Department:', $filterDept ?: 'All']);
    fputcsv($output, ['Attendance Rate:', $stats['percent'] . '%']);
    fputcsv($output, []);
    fputcsv($output, ['Employee ID', 'Employee Name', 'Department', 'Designation', 'Time In', 'Time Out', 'Status', 'Remarks']);
    
    foreach ($records as $r) {
        fputcsv($output, [
            $r['employee_no'],
            $r['first_name'] . ' ' . $r['last_name'],
            $r['department'],
            $r['designation'],
            $r['check_in_time'] ? date('h:i A', strtotime($r['check_in_time'])) : '—',
            $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '—',
            $r['status'],
            $r['remarks'] ?: ''
        ]);
    }
    fclose($output);
    exit;
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-chart-line me-2 text-primary"></i>Daily Staff Attendance Report</h3>
        <p class="text-muted small mb-0">View single-day status rosters, filter by shift tags, and download CSV log sheets.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-outline-primary px-3 me-2" id="btnPrintReport">
            <i class="fa-solid fa-print me-1"></i>Print Report
        </button>
        <a href="?action=export&date=<?php echo $filterDate; ?>&department=<?php echo $filterDept; ?>&designation=<?php echo $filterDesig; ?>&status=<?php echo $filterStatus; ?>" class="btn btn-primary px-3">
            <i class="fa-solid fa-file-excel me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- 7 Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4 col-lg border-end">
        <div class="card border-0 shadow-sm text-center p-3 h-100" style="border-radius:10px; background:#f8f9fa;">
            <h6 class="text-muted small fw-semibold text-uppercase mb-1">Total Staff</h6>
            <h4 class="fw-bold text-dark mb-0"><?php echo $stats['total']; ?></h4>
        </div>
    </div>
    <div class="col-md-4 col-lg border-end">
        <div class="card border-0 shadow-sm text-center p-3 h-100" style="border-radius:10px; background:#e8f5e9;">
            <h6 class="text-success small fw-semibold text-uppercase mb-1">Present</h6>
            <h4 class="fw-bold text-success mb-0"><?php echo $stats['present']; ?></h4>
        </div>
    </div>
    <div class="col-md-4 col-lg border-end">
        <div class="card border-0 shadow-sm text-center p-3 h-100" style="border-radius:10px; background:#ffebee;">
            <h6 class="text-danger small fw-semibold text-uppercase mb-1">Absent</h6>
            <h4 class="fw-bold text-danger mb-0"><?php echo $stats['absent']; ?></h4>
        </div>
    </div>
    <div class="col-md-4 col-lg border-end">
        <div class="card border-0 shadow-sm text-center p-3 h-100" style="border-radius:10px; background:#fff8e1;">
            <h6 class="text-warning small fw-semibold text-uppercase mb-1">Late</h6>
            <h4 class="fw-bold text-warning mb-0"><?php echo $stats['late']; ?></h4>
        </div>
    </div>
    <div class="col-md-4 col-lg border-end">
        <div class="card border-0 shadow-sm text-center p-3 h-100" style="border-radius:10px; background:#e0f7fa;">
            <h6 class="text-info small fw-semibold text-uppercase mb-1">Leave</h6>
            <h4 class="fw-bold text-info mb-0"><?php echo $stats['leave']; ?></h4>
        </div>
    </div>
    <div class="col-md-4 col-lg border-end">
        <div class="card border-0 shadow-sm text-center p-3 h-100" style="border-radius:10px; background:#f3e5f5;">
            <h6 class="text-primary small fw-semibold text-uppercase mb-1">Half Day</h6>
            <h4 class="fw-bold text-primary mb-0"><?php echo $stats['halfday']; ?></h4>
        </div>
    </div>
    <div class="col-md-4 col-lg">
        <div class="card border-0 shadow-sm text-center p-3 h-100" style="border-radius:10px; background: linear-gradient(135deg, #1e3a8a, #3b82f6);">
            <h6 class="text-white-50 small fw-semibold text-uppercase mb-1">Rate %</h6>
            <h4 class="fw-bold text-white mb-0"><?php echo $stats['percent']; ?>%</h4>
        </div>
    </div>
</div>

<!-- Filters Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter daily logs</h6>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Attendance Date</label>
                <input type="date" class="form-control form-control-sm" name="date" value="<?php echo htmlspecialchars($filterDate); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Department</label>
                <select class="form-select form-select-sm" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo ($filterDept === $d) ? 'selected' : ''; ?>><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Designation</label>
                <select class="form-select form-select-sm" name="designation">
                    <option value="">All Designations</option>
                    <?php foreach ($designations as $ds): ?>
                        <option value="<?php echo $ds; ?>" <?php echo ($filterDesig === $ds) ? 'selected' : ''; ?>><?php echo $ds; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Attendance Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Statuses</option>
                    <option value="Present" <?php echo ($filterStatus === 'Present') ? 'selected' : ''; ?>>Present</option>
                    <option value="Absent" <?php echo ($filterStatus === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                    <option value="Late" <?php echo ($filterStatus === 'Late') ? 'selected' : ''; ?>>Late</option>
                    <option value="Leave" <?php echo ($filterStatus === 'Leave') ? 'selected' : ''; ?>>Leave</option>
                    <option value="Half Day" <?php echo ($filterStatus === 'Half Day') ? 'selected' : ''; ?>>Half Day</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-primary w-100 py-2">Search</button>
            </div>
        </form>
    </div>
</div>

<!-- Table View Grid -->
<div class="custom-table-card shadow-sm border-0 mb-4" id="reportSection">
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Emp ID</th>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No attendance logs available for this date.</td></tr>
                <?php else: foreach ($records as $r): ?>
                    <tr>
                        <td><code><?php echo sanitize($r['employee_no']); ?></code></td>
                        <td><span class="fw-bold text-dark"><?php echo sanitize($r['first_name'] . ' ' . $r['last_name']); ?></span></td>
                        <td><?php echo sanitize($r['department']); ?></td>
                        <td><?php echo sanitize($r['designation']); ?></td>
                        <td><span class="small fw-semibold"><?php echo $r['check_in_time'] ? date('h:i A', strtotime($r['check_in_time'])) : '—'; ?></span></td>
                        <td><span class="small fw-semibold"><?php echo $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '—'; ?></span></td>
                        <td>
                            <?php
                            $badge = 'bg-light text-muted border';
                            if ($r['status'] === 'Present') $badge = 'bg-success-soft text-success';
                            elseif ($r['status'] === 'Absent') $badge = 'bg-danger-soft text-danger';
                            elseif ($r['status'] === 'Late') $badge = 'bg-warning-soft text-warning';
                            elseif ($r['status'] === 'Leave') $badge = 'bg-info-soft text-info';
                            elseif ($r['status'] === 'Half Day') $badge = 'bg-primary-soft text-primary';
                            ?>
                            <span class="badge <?php echo $badge; ?> px-3 py-2 rounded-pill fw-semibold"><?php echo $r['status']; ?></span>
                        </td>
                        <td class="text-muted small"><?php echo sanitize($r['remarks'] ?: '—'); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Printable template -->
<div id="printReportTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 25px; border: 1px solid #ccc; width: 800px; margin: 0 auto; border-radius:8px;">
        <div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px;">
            <h2 style="margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h2>
            <p style="margin: 5px 0 0 0; font-size: 11px; color:#555;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 15px 0 0 0; background: #eee; padding: 5px; border-radius: 4px; letter-spacing: 1px;">DAILY STAFF ATTENDANCE REPORT</h4>
        </div>
        
        <table style="width: 100%; font-size: 12px; margin-bottom: 20px;">
            <tr>
                <td><strong>Report Date:</strong> <?php echo date('d M Y', strtotime($filterDate)); ?></td>
                <td><strong>Department:</strong> <?php echo $filterDept ?: 'All'; ?></td>
                <td style="text-align: right;"><strong>Attendance Rate:</strong> <?php echo $stats['percent']; ?>%</td>
            </tr>
        </table>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 11px; text-align: left;" border="1" cellpadding="6">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th>Emp ID</th>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="8" style="text-align:center;">No records available.</td></tr>
                <?php else: foreach ($records as $r): ?>
                    <tr>
                        <td><code><?php echo $r['employee_no']; ?></code></td>
                        <td><strong><?php echo $r['first_name'] . ' ' . $r['last_name']; ?></strong></td>
                        <td><?php echo $r['department']; ?></td>
                        <td><?php echo $r['designation']; ?></td>
                        <td><?php echo $r['check_in_time'] ? date('h:i A', strtotime($r['check_in_time'])) : '—'; ?></td>
                        <td><?php echo $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '—'; ?></td>
                        <td><?php echo $r['status']; ?></td>
                        <td><?php echo $r['remarks'] ?: ''; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $extraJS = '<script>
document.addEventListener("DOMContentLoaded", function() {
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
