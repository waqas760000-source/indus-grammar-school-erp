<?php
/**
 * Indus Grammar School ERP - Mark Staff Attendance Daily
 * Version 4.0.0
 */

$pageTitle = 'Mark Staff Attendance';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('attendance_mark');

$db = Database::getConnection();

// Initial filter settings
$selectedDate = sanitize($_GET['date'] ?? date('Y-m-d'));
$selectedDept = sanitize($_GET['department'] ?? '');
$selectedDesig = sanitize($_GET['designation'] ?? '');
$selectedType = sanitize($_GET['staff_type'] ?? '');
$selectedStatus = sanitize($_GET['status'] ?? 'Active');

// Fetch dropdown filters dynamically
$departments = [];
$designations = [];
try {
    $departments = $db->query("SELECT DISTINCT department FROM staff ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
    $designations = $db->query("SELECT DISTINCT designation FROM staff ORDER BY designation ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Initial load of staff roster matching parameters
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
        $where .= " AND s.department = 'Academic'";
    } else {
        $where .= " AND s.department != 'Academic'";
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
$summary = ['total' => count($staffList), 'present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0, 'halfday' => 0];
foreach ($staffList as $st) {
    $status = $st['current_status'] ?: '';
    if ($status === 'Present') $summary['present']++;
    elseif ($status === 'Absent') $summary['absent']++;
    elseif ($status === 'Late') $summary['late']++;
    elseif ($status === 'Leave') $summary['leave']++;
    elseif ($status === 'Half Day') $summary['halfday']++;
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-user-clock me-2 text-primary"></i>Daily Staff Attendance</h3>
        <p class="text-muted small mb-0">Load employees, log daily status, adjust check-in/out times, and save daily rosters.</p>
    </div>
</div>

<!-- 6 Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm text-center p-3 h-100" style="border-radius:12px; background: linear-gradient(135deg, #0d6efd, #0b5ed7);">
            <h6 class="text-white-50 small fw-semibold text-uppercase mb-1">Total Staff</h6>
            <h3 class="fw-bold text-white mb-0"><?php echo $summary['total']; ?></h3>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm text-center p-3 h-100" style="border-radius:12px; background: linear-gradient(135deg, #198754, #157347);">
            <h6 class="text-white-50 small fw-semibold text-uppercase mb-1">Present</h6>
            <h3 class="fw-bold text-white mb-0"><?php echo $summary['present']; ?></h3>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm text-center p-3 h-100" style="border-radius:12px; background: linear-gradient(135deg, #dc3545, #bb2d3b);">
            <h6 class="text-white-50 small fw-semibold text-uppercase mb-1">Absent</h6>
            <h3 class="fw-bold text-white mb-0"><?php echo $summary['absent']; ?></h3>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm text-center p-3 h-100" style="border-radius:12px; background: linear-gradient(135deg, #ffc107, #ffb300);">
            <h6 class="text-dark-50 small fw-semibold text-uppercase mb-1">Late Arrivals</h6>
            <h3 class="fw-bold text-dark mb-0"><?php echo $summary['late']; ?></h3>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm text-center p-3 h-100" style="border-radius:12px; background: linear-gradient(135deg, #0dcaf0, #0baccc);">
            <h6 class="text-white-50 small fw-semibold text-uppercase mb-1">On Leave</h6>
            <h3 class="fw-bold text-white mb-0"><?php echo $summary['leave']; ?></h3>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm text-center p-3 h-100" style="border-radius:12px; background: linear-gradient(135deg, #6f42c1, #59359a);">
            <h6 class="text-white-50 small fw-semibold text-uppercase mb-1">Half Day</h6>
            <h3 class="fw-bold text-white mb-0"><?php echo $summary['halfday']; ?></h3>
        </div>
    </div>
</div>

<!-- Filters Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Roster Filter Parameters</h6>
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Attendance Date</label>
                <input type="date" class="form-control form-control-sm" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Department</label>
                <select class="form-select form-select-sm" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo ($selectedDept === $d) ? 'selected' : ''; ?>><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Designation</label>
                <select class="form-select form-select-sm" name="designation">
                    <option value="">All Designations</option>
                    <?php foreach ($designations as $ds): ?>
                        <option value="<?php echo $ds; ?>" <?php echo ($selectedDesig === $ds) ? 'selected' : ''; ?>><?php echo $ds; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Staff Type</label>
                <select class="form-select form-select-sm" name="staff_type">
                    <option value="">All Staff</option>
                    <option value="Teaching Staff" <?php echo ($selectedType === 'Teaching Staff') ? 'selected' : ''; ?>>Teaching Staff</option>
                    <option value="Non-Teaching Staff" <?php echo ($selectedType === 'Non-Teaching Staff') ? 'selected' : ''; ?>>Non-Teaching Staff</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Employee Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="Active" <?php echo ($selectedStatus === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo ($selectedStatus === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-primary w-100 py-2">Load</button>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Form -->
<form id="saveAttendanceForm" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
    <input type="hidden" name="action" value="save_staff_attendance_bulk">
    <input type="hidden" name="date" value="<?php echo $selectedDate; ?>">

    <div class="custom-table-card shadow-sm border-0 mb-4">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0" id="markRosterTable">
                <thead>
                    <tr>
                        <th width="100">Emp ID</th>
                        <th>Photo</th>
                        <th>Employee Name</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th width="150">Attendance Status</th>
                        <th width="120">Time In</th>
                        <th width="120">Time Out</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffList)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No employees found matching filter criteria.</td></tr>
                    <?php else: $i = 0; foreach ($staffList as $st): 
                        $statusVal = $st['current_status'] ?: 'Present';
                    ?>
                        <tr class="roster-row" data-id="<?php echo $st['id']; ?>">
                            <td>
                                <code><?php echo sanitize($st['employee_no']); ?></code>
                                <input type="hidden" name="records[<?php echo $i; ?>][staff_id]" value="<?php echo $st['id']; ?>">
                            </td>
                            <td>
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center border" style="width:36px; height:36px;">
                                    <i class="fa-solid fa-user text-secondary" style="font-size:0.9rem;"></i>
                                </div>
                            </td>
                            <td><span class="fw-bold text-dark"><?php echo sanitize($st['first_name'] . ' ' . $st['last_name']); ?></span></td>
                            <td><span class="small"><?php echo sanitize($st['department']); ?></span></td>
                            <td><span class="small text-muted"><?php echo sanitize($st['designation']); ?></span></td>
                            <td>
                                <select class="form-select form-select-sm status-select fw-semibold" name="records[<?php echo $i; ?>][status]" style="font-size:0.8rem;" data-row-idx="<?php echo $i; ?>">
                                    <option value="Present" class="text-success" <?php echo ($statusVal === 'Present') ? 'selected' : ''; ?>>Present</option>
                                    <option value="Absent" class="text-danger" <?php echo ($statusVal === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                                    <option value="Late" class="text-warning" <?php echo ($statusVal === 'Late') ? 'selected' : ''; ?>>Late</option>
                                    <option value="Leave" class="text-info" <?php echo ($statusVal === 'Leave') ? 'selected' : ''; ?>>Leave</option>
                                    <option value="Half Day" class="text-primary" <?php echo ($statusVal === 'Half Day') ? 'selected' : ''; ?>>Half Day</option>
                                </select>
                            </td>
                            <td>
                                <input type="time" class="form-control form-control-sm time-in-input" 
                                       name="records[<?php echo $i; ?>][check_in]" 
                                       value="<?php echo $st['check_in_time'] ? date('H:i', strtotime($st['check_in_time'])) : ''; ?>"
                                       <?php echo in_array($statusVal, ['Absent', 'Leave']) ? 'disabled' : ''; ?>>
                            </td>
                            <td>
                                <input type="time" class="form-control form-control-sm time-out-input" 
                                       name="records[<?php echo $i; ?>][check_out]" 
                                       value="<?php echo $st['check_out_time'] ? date('H:i', strtotime($st['check_out_time'])) : ''; ?>"
                                       <?php echo in_array($statusVal, ['Absent', 'Leave']) ? 'disabled' : ''; ?>>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm remarks-input" 
                                       name="records[<?php echo $i; ?>][remarks]" 
                                       value="<?php echo htmlspecialchars($st['remarks'] ?? ''); ?>" 
                                       placeholder="Remarks/Notes" style="font-size:0.8rem;">
                            </td>
                        </tr>
                    <?php $i++; endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($staffList)): ?>
    <div class="row align-items-center mb-4">
        <div class="col-sm-6">
            <button type="button" class="btn btn-outline-secondary px-3" onclick="location.reload();">
                <i class="fa-solid fa-arrow-rotate-left me-1"></i>Reset
            </button>
            <button type="button" class="btn btn-outline-primary px-3 ms-2" id="btnPrintSheet">
                <i class="fa-solid fa-print me-1"></i>Print Attendance Sheet
            </button>
        </div>
        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
            <button type="submit" class="btn btn-success px-5 py-2 fw-semibold" id="btnSaveRoster">
                <i class="fa-solid fa-cloud-arrow-up me-2"></i>Save Attendance
            </button>
        </div>
    </div>
    <?php endif; ?>
</form>

<!-- Printable sheet structure (Printed via JS popup window) -->
<div id="printSheetSection" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 25px; border: 1px solid #ddd; border-radius:8px; width:800px; margin:0 auto;">
        <div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px;">
            <h2 style="margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h2>
            <p style="margin: 5px 0 0 0; font-size: 11px; color:#555;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 15px 0 0 0; background: #eee; padding: 5px; border-radius: 4px; letter-spacing: 1px;">STAFF ATTENDANCE ROSTER SHEET</h4>
        </div>
        <table style="width: 100%; font-size: 13px; margin-bottom: 20px;">
            <tr>
                <td><strong>Roster Date:</strong> <?php echo date('d M Y', strtotime($selectedDate)); ?></td>
                <td><strong>Department:</strong> <?php echo $selectedDept ?: 'All'; ?></td>
                <td style="text-align: right;"><strong>Status:</strong> ACTIVE</td>
            </tr>
        </table>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; text-align: left;" border="1" cellpadding="6">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th width="100">Employee ID</th>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th width="120">Signature</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffList as $st): ?>
                    <tr>
                        <td><code><?php echo sanitize($st['employee_no']); ?></code></td>
                        <td><strong><?php echo sanitize($st['first_name'] . ' ' . $st['last_name']); ?></strong></td>
                        <td><?php echo sanitize($st['department']); ?></td>
                        <td><?php echo sanitize($st['designation']); ?></td>
                        <td style="height: 35px;"></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <table style="width: 100%; margin-top: 60px; font-size: 11px; text-align: center;">
            <tr>
                <td style="width: 50%;"><div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">Verifier Signature</div></td>
                <td style="width: 50%;"><div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">Principal / Director Approval</div></td>
            </tr>
        </table>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="markToast" class="toast align-items-center text-white border-0" role="alert">
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
    
    // Status select change checks (disable times on Absent/Leave)
    document.querySelectorAll(".status-select").forEach(sel => {
        sel.addEventListener("change", function() {
            const rowIdx = this.dataset.rowIdx;
            const val = this.value;
            const row = this.closest("tr");
            const tIn = row.querySelector(".time-in-input");
            const tOut = row.querySelector(".time-out-input");
            
            if (val === "Absent" || val === "Leave") {
                tIn.disabled = true; tIn.value = "";
                tOut.disabled = true; tOut.value = "";
            } else {
                tIn.disabled = false;
                tOut.disabled = false;
                
                // Fallbacks to default shift hours if empty
                if(tIn.value === "") tIn.value = "08:00";
                if(tOut.value === "") tOut.value = "14:00";
            }
        });
    });

    // Save Bulk Attendance Form
    const saveForm = document.getElementById("saveAttendanceForm");
    if (saveForm) {
        saveForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSaveRoster");
            btn.disabled = true; btn.innerHTML = "Saving daily records...";
            
            fetch("../../ajax/staff_attendance.php", { method: "POST", body: new FormData(saveForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-cloud-arrow-up me-2\"></i>Save Attendance"; }
                })
                .catch(() => { showToast("Network communication error.", false); btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-cloud-arrow-up me-2\"></i>Save Attendance"; });
        });
    }

    // Print Attendance Sheet
    const printBtn = document.getElementById("btnPrintSheet");
    if (printBtn) {
        printBtn.addEventListener("click", function() {
            const printContent = document.getElementById("printSheetSection").innerHTML;
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Staff Attendance sheet</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
