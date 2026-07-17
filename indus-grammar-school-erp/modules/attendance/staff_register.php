<?php
/**
 * Indus Grammar School ERP - Staff Attendance Register Ledger
 * Version 4.0.0
 */

$pageTitle = 'Staff Attendance Register';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('attendance_view');

$db = Database::getConnection();

// Parameters
$filterEmpId   = sanitize($_GET['employee_id'] ?? '');
$filterEmpName = sanitize($_GET['employee_name'] ?? '');
$filterDept    = sanitize($_GET['department'] ?? '');
$filterDesig   = sanitize($_GET['designation'] ?? '');
$fromDate      = sanitize($_GET['from_date'] ?? date('Y-m-01'));
$toDate        = sanitize($_GET['to_date'] ?? date('Y-m-d'));

// Fetch departments & designations for filter selections
$departments = [];
$designations = [];
try {
    $departments = $db->query("SELECT DISTINCT department FROM staff ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
    $designations = $db->query("SELECT DISTINCT designation FROM staff ORDER BY designation ASC")->fetchAll(PDO::FETCH_COLUMN);
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

// Check if a single employee details summary panel can be shown
$singleEmployee = null;
if ($filterEmpId !== '' && !empty($records)) {
    // Collect stats for this specific employee
    $singleEmployee = [
        'employee_no' => $records[0]['employee_no'],
        'name' => $records[0]['first_name'] . ' ' . $records[0]['last_name'],
        'department' => $records[0]['department'],
        'designation' => $records[0]['designation'],
        'joining_date' => $records[0]['date_of_joining'],
        'present' => 0,
        'absent' => 0,
        'late' => 0,
        'leave' => 0,
        'halfday' => 0,
        'total' => 0,
        'percent' => 0.00
    ];
    
    foreach ($records as $r) {
        if ($r['status'] === 'Present') $singleEmployee['present']++;
        elseif ($r['status'] === 'Absent') $singleEmployee['absent']++;
        elseif ($r['status'] === 'Late') $singleEmployee['late']++;
        elseif ($r['status'] === 'Leave') $singleEmployee['leave']++;
        elseif ($r['status'] === 'Half Day') $singleEmployee['halfday']++;
    }
    
    $singleEmployee['total'] = $singleEmployee['present'] + $singleEmployee['absent'] + $singleEmployee['leave'] + $singleEmployee['late'] + $singleEmployee['halfday'];
    if ($singleEmployee['total'] > 0) {
        $physPresent = $singleEmployee['present'] + $singleEmployee['late'] + $singleEmployee['halfday'];
        $singleEmployee['percent'] = round(($physPresent / $singleEmployee['total']) * 100, 2);
    }
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-address-card me-2 text-primary"></i>Staff Attendance Register</h3>
        <p class="text-muted small mb-0">Track cumulative logs, view physical check-in clock-outs, and verify employee summaries.</p>
    </div>
</div>

<!-- Filters Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Register Logs</h6>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Employee ID</label>
                <input type="text" class="form-control form-control-sm text-center font-monospace" name="employee_id" value="<?php echo htmlspecialchars($filterEmpId); ?>" placeholder="EMP-2024-001">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Employee Name</label>
                <input type="text" class="form-control form-control-sm" name="employee_name" value="<?php echo htmlspecialchars($filterEmpName); ?>" placeholder="Search name...">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Department</label>
                <select class="form-select form-select-sm" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo ($filterDept === $d) ? 'selected' : ''; ?>><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Designation</label>
                <select class="form-select form-select-sm" name="designation">
                    <option value="">All Designations</option>
                    <?php foreach ($designations as $ds): ?>
                        <option value="<?php echo $ds; ?>" <?php echo ($filterDesig === $ds) ? 'selected' : ''; ?>><?php echo $ds; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Date From</label>
                <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Date To</label>
                <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>
            <div class="col-12 text-end mt-3">
                <a href="staff_register.php" class="btn btn-sm btn-outline-secondary px-3 me-2">Reset Filters</a>
                <button type="submit" class="btn btn-sm btn-primary px-4 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Search Register</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Single Employee Summary Widget (Only shown when filtered by a specific Employee ID) -->
    <?php if ($singleEmployee): ?>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm text-center p-4 mb-4" style="border-radius:12px;">
                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center border mx-auto mb-3" style="width:100px; height:100px;">
                    <i class="fa-solid fa-user text-secondary" style="font-size:2.8rem;"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1"><?php echo sanitize($singleEmployee['name']); ?></h5>
                <span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill mb-3"><?php echo sanitize($singleEmployee['employee_no']); ?></span>
                
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
                        <td class="fw-bold text-dark text-end"><?php echo date('d M Y', strtotime($singleEmployee['joining_date'])); ?></td>
                    </tr>
                </table>

                <h6 class="fw-bold text-secondary text-uppercase small text-start border-bottom pb-2 mb-3">Cumulative Ratios</h6>
                <div class="row g-2 mb-4">
                    <div class="col-4">
                        <div class="p-2 border rounded bg-light">
                            <span class="small text-muted d-block">Present</span>
                            <strong class="text-success"><?php echo $singleEmployee['present']; ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-light">
                            <span class="small text-muted d-block">Absent</span>
                            <strong class="text-danger"><?php echo $singleEmployee['absent']; ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-light">
                            <span class="small text-muted d-block">Late</span>
                            <strong class="text-warning"><?php echo $singleEmployee['late']; ?></strong>
                        </div>
                    </div>
                    <div class="col-4 mt-2">
                        <div class="p-2 border rounded bg-light">
                            <span class="small text-muted d-block">Leave</span>
                            <strong class="text-info"><?php echo $singleEmployee['leave']; ?></strong>
                        </div>
                    </div>
                    <div class="col-4 mt-2">
                        <div class="p-2 border rounded bg-light">
                            <span class="small text-muted d-block">Half Day</span>
                            <strong class="text-primary"><?php echo $singleEmployee['halfday']; ?></strong>
                        </div>
                    </div>
                    <div class="col-4 mt-2">
                        <div class="p-2 border rounded bg-light">
                            <span class="small text-muted d-block">Rate</span>
                            <strong class="text-primary"><?php echo $singleEmployee['percent']; ?>%</strong>
                        </div>
                    </div>
                </div>

                <div class="bg-primary text-white p-3 rounded shadow-sm">
                    <h6 class="small mb-1 opacity-75">ATTENDANCE RATING RATE</h6>
                    <h3 class="fw-bold mb-0"><?php echo $singleEmployee['percent']; ?>%</h3>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Right Column: Ledger Log Table -->
    <div class="<?php echo $singleEmployee ? 'col-lg-8' : 'col-12'; ?>">
        <div class="custom-table-card shadow-sm border-0">
            <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-light">
                <h6 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-list-check me-2 text-primary"></i>Daily Log Registrations</h6>
                <span class="badge bg-primary text-white rounded-pill px-3"><?php echo count($records); ?> records</span>
            </div>
            
            <div class="table-responsive">
                <table class="table custom-table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Emp ID</th>
                            <th>Employee Name</th>
                            <th>Shifts Clock</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No attendance register entries found matching criteria.</td></tr>
                        <?php else: foreach ($records as $r): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                                <td><code><?php echo sanitize($r['employee_no']); ?></code></td>
                                <td><span class="fw-bold text-dark"><?php echo sanitize($r['first_name'] . ' ' . $r['last_name']); ?></span></td>
                                <td>
                                    <?php if ($r['check_in_time'] || $r['check_out_time']): ?>
                                        <span class="small"><i class="fa-regular fa-clock me-1"></i><?php echo $r['check_in_time'] ? date('h:i A', strtotime($r['check_in_time'])) : '—'; ?> - <?php echo $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '—'; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">Check-ins unrecorded</span>
                                    <?php endif; ?>
                                </td>
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
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary btn-print-slip"
                                            data-ref="ATT-<?php echo str_pad($r['id'], 6, '0', STR_PAD_LEFT); ?>"
                                            data-date="<?php echo date('d M Y', strtotime($r['date'])); ?>"
                                            data-empid="<?php echo htmlspecialchars($r['employee_no']); ?>"
                                            data-name="<?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?>"
                                            data-dept="<?php echo htmlspecialchars($r['department']); ?>"
                                            data-desig="<?php echo htmlspecialchars($r['designation']); ?>"
                                            data-status="<?php echo htmlspecialchars($r['status']); ?>"
                                            data-cin="<?php echo $r['check_in_time'] ? date('h:i A', strtotime($r['check_in_time'])) : '—'; ?>"
                                            data-cout="<?php echo $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '—'; ?>"
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

<!-- Print Slip layout -->
<div id="printSlipTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 25px; border: 2px solid #333; border-radius:10px; width: 500px; margin: 0 auto;">
        <div style="text-align: center; border-bottom: 2px double #000; padding-bottom: 8px; margin-bottom: 15px;">
            <h3 style="margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h3>
            <p style="margin: 3px 0 0 0; font-size: 11px; color:#555;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 10px 0 0 0; background: #eee; padding: 4px; border-radius: 4px;">ATTENDANCE RECORD VOUCHER</h4>
        </div>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;" cellpadding="6">
            <tr style="border-bottom: 1px solid #ddd;">
                <td><strong>Voucher Ref ID:</strong></td>
                <td style="text-align: right;" id="p_ref"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td><strong>Attendance Date:</strong></td>
                <td style="text-align: right; font-weight: bold;" id="p_date"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td><strong>Employee ID:</strong></td>
                <td style="text-align: right;" id="p_empid"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td><strong>Employee Name:</strong></td>
                <td style="text-align: right; font-weight: bold;" id="p_name"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td><strong>Department / Designation:</strong></td>
                <td style="text-align: right;" id="p_dept_desig"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td><strong>Attendance Status:</strong></td>
                <td style="text-align: right; font-weight: bold; text-transform: uppercase;" id="p_status"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td><strong>Check In Time:</strong></td>
                <td style="text-align: right;" id="p_cin"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td><strong>Check Out Time:</strong></td>
                <td style="text-align: right;" id="p_cout"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td><strong>Remarks / Notes:</strong></td>
                <td style="text-align: right;" id="p_remarks"></td>
            </tr>
        </table>
        
        <table style="width: 100%; margin-top: 50px; font-size: 11px; text-align: center;">
            <tr>
                <td><div style="border-top: 1px solid #000; width: 140px; margin: 0 auto; padding-top: 3px;">Staff Member</div></td>
                <td><div style="border-top: 1px solid #000; width: 140px; margin: 0 auto; padding-top: 3px;">HR Manager</div></td>
            </tr>
        </table>
    </div>
</div>

<?php $extraJS = '<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".btn-print-slip").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("p_ref").textContent = this.dataset.ref;
            document.getElementById("p_date").textContent = this.dataset.date;
            document.getElementById("p_empid").textContent = this.dataset.empid;
            document.getElementById("p_name").textContent = this.dataset.name;
            document.getElementById("p_dept_desig").textContent = this.dataset.dept + " / " + this.dataset.desig;
            document.getElementById("p_status").textContent = this.dataset.status;
            document.getElementById("p_cin").textContent = this.dataset.cin;
            document.getElementById("p_cout").textContent = this.dataset.cout;
            document.getElementById("p_remarks").textContent = this.dataset.remarks || "—";
            
            const printContent = document.getElementById("printSlipTemplate").innerHTML;
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Attendance Voucher</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
