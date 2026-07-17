<?php
/**
 * Indus Grammar School ERP - Staff Leave Management Submodule
 * Version 4.0.0
 */

$pageTitle = 'Staff Leave Management';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('attendance_mark');

$db = Database::getConnection();

// Filter values
$filterDept   = sanitize($_GET['dept'] ?? '');
$filterStaff  = (int)($_GET['staff_id'] ?? 0);
$filterType   = sanitize($_GET['leave_type'] ?? '');
$filterStatus = sanitize($_GET['status'] ?? '');
$fromDate     = sanitize($_GET['from_date'] ?? '');
$toDate       = sanitize($_GET['to_date'] ?? '');

// Fetch active staff roster for form & filtering options
$staffList = [];
$departments = [];
try {
    $staffList = $db->query("SELECT id, employee_no, first_name, last_name, department, designation FROM staff WHERE status = 'Active' ORDER BY employee_no ASC")->fetchAll(PDO::FETCH_ASSOC);
    $departments = $db->query("SELECT DISTINCT department FROM staff ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Construct query filters
$where = " WHERE 1=1";
$params = [];

if ($filterDept !== '') {
    $where .= " AND s.department = :dept";
    $params['dept'] = $filterDept;
}
if ($filterStaff > 0) {
    $where .= " AND l.staff_id = :staff_id";
    $params['staff_id'] = $filterStaff;
}
if ($filterType !== '') {
    $where .= " AND l.leave_type = :type";
    $params['type'] = $filterType;
}
if ($filterStatus !== '') {
    $where .= " AND l.status = :status";
    $params['status'] = $filterStatus;
}
if ($fromDate !== '') {
    $where .= " AND l.leave_from >= :from";
    $params['from'] = $fromDate;
}
if ($toDate !== '') {
    $where .= " AND l.leave_to <= :to";
    $params['to'] = $toDate;
}

$leaves = [];
try {
    $stmt = $db->prepare("
        SELECT l.*, s.employee_no, s.first_name, s.last_name, s.department, s.designation, u.username as approved_by_name
        FROM staff_leave l
        JOIN staff s ON l.staff_id = s.id
        LEFT JOIN users u ON l.approved_by = u.id
        $where
        ORDER BY l.id DESC
    ");
    $stmt->execute($params);
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading leave applications: " . $e->getMessage());
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-hospital-user me-2 text-primary"></i>Staff Leave Management</h3>
        <p class="text-muted small mb-0">Record employee absences, manage approvals, check attachments, and update logs.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Submit Leave Request Form -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-file-pen me-2 text-primary"></i>Apply for Leave</h5>
                
                <form id="applyLeaveForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="apply_staff_leave">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Employee *</label>
                        <select class="form-select form-select-sm" name="staff_id" id="form_staff_select" required>
                            <option value="">— Select Staff Member —</option>
                            <?php foreach ($staffList as $st): ?>
                                <option value="<?php echo $st['id']; ?>" 
                                        data-dept="<?php echo htmlspecialchars($st['department']); ?>"
                                        data-desig="<?php echo htmlspecialchars($st['designation']); ?>">
                                    <?php echo htmlspecialchars($st['employee_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted">Department</label>
                            <input type="text" class="form-control form-control-sm" id="form_dept" disabled placeholder="Department">
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted">Designation</label>
                            <input type="text" class="form-control form-control-sm" id="form_desig" disabled placeholder="Designation">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Leave Type *</label>
                        <select class="form-select form-select-sm" name="leave_type" required>
                            <option value="Casual Leave">Casual Leave</option>
                            <option value="Medical Leave">Medical Leave</option>
                            <option value="Annual Leave">Annual Leave</option>
                            <option value="Emergency Leave">Emergency Leave</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Leave From *</label>
                            <input type="date" class="form-control form-control-sm" name="leave_from" id="form_date_from" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Leave To *</label>
                            <input type="date" class="form-control form-control-sm" name="leave_to" id="form_date_to" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Total Calendar Days</label>
                        <input type="text" class="form-control form-control-sm text-center fw-bold bg-light" id="form_total_days" readonly value="0 days">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reason for Absence *</label>
                        <textarea class="form-control form-control-sm" name="reason" rows="2" placeholder="Describe reason for leave request..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Attachment (Medical Certificate/Application)</label>
                        <input type="file" class="form-control form-control-sm" name="attachment" accept=".pdf,.png,.jpg,.jpeg">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Internal Remarks / Office Notes</label>
                        <textarea class="form-control form-control-sm" name="remarks" rows="2" placeholder="Admin notes (Optional)"></textarea>
                    </div>

                    <button type="submit" class="btn btn-sm btn-primary w-100 py-2" id="btnApplyLeave">
                        <i class="fa-solid fa-paper-plane me-2"></i>Submit Application
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Filter & History List -->
    <div class="col-lg-8">
        <!-- Leave Report Filter Box -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-body p-4">
                <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter leave applications</h6>
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Department</label>
                        <select class="form-select form-select-sm" name="dept">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d; ?>" <?php echo ($filterDept === $d) ? 'selected' : ''; ?>><?php echo $d; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Leave Type</label>
                        <select class="form-select form-select-sm" name="leave_type">
                            <option value="">All Leave Types</option>
                            <option value="Casual Leave" <?php echo ($filterType === 'Casual Leave') ? 'selected' : ''; ?>>Casual Leave</option>
                            <option value="Medical Leave" <?php echo ($filterType === 'Medical Leave') ? 'selected' : ''; ?>>Medical Leave</option>
                            <option value="Annual Leave" <?php echo ($filterType === 'Annual Leave') ? 'selected' : ''; ?>>Annual Leave</option>
                            <option value="Emergency Leave" <?php echo ($filterType === 'Emergency Leave') ? 'selected' : ''; ?>>Emergency Leave</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Status</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo ($filterStatus === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo ($filterStatus === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo ($filterStatus === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-sm btn-primary w-100 py-2">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- History grid list table -->
        <div class="custom-table-card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table custom-table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Staff Member</th>
                            <th>Leave details</th>
                            <th>Date Duration</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leaves)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No leave applications recorded in history.</td></tr>
                        <?php else: foreach ($leaves as $l): ?>
                            <tr>
                                <td><code><?php echo sanitize($l['employee_no']); ?></code></td>
                                <td>
                                    <span class="fw-bold text-dark d-block"><?php echo sanitize($l['first_name'] . ' ' . $l['last_name']); ?></span>
                                    <span class="small text-muted"><?php echo sanitize($l['designation']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?php echo sanitize($l['leave_type']); ?></span>
                                    <span class="small d-block text-muted text-truncate" style="max-width: 140px;"><?php echo sanitize($l['reason']); ?></span>
                                </td>
                                <td>
                                    <span class="small fw-semibold"><?php echo date('d M Y', strtotime($l['leave_from'])) . ' - ' . date('d M Y', strtotime($l['leave_to'])); ?></span>
                                    <span class="badge bg-primary-soft text-primary d-block mt-1"><?php echo $l['total_days']; ?> Day(s)</span>
                                </td>
                                <td>
                                    <?php
                                    $badge = 'bg-warning-soft text-warning';
                                    if ($l['status'] === 'Approved') $badge = 'bg-success-soft text-success';
                                    elseif ($l['status'] === 'Rejected') $badge = 'bg-danger-soft text-danger';
                                    ?>
                                    <span class="badge <?php echo $badge; ?> px-3 py-2 rounded-pill fw-semibold"><?php echo $l['status']; ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <?php if ($l['attachment']): ?>
                                            <a href="<?php echo APP_URL . '/' . $l['attachment']; ?>" target="_blank" class="btn btn-sm btn-outline-info" title="View Attachment File">
                                                <i class="fa-solid fa-paperclip"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-primary btn-view-leave"
                                                data-id="<?php echo $l['id']; ?>"
                                                data-empid="<?php echo htmlspecialchars($l['employee_no']); ?>"
                                                data-name="<?php echo htmlspecialchars($l['first_name'] . ' ' . $l['last_name']); ?>"
                                                data-type="<?php echo htmlspecialchars($l['leave_type']); ?>"
                                                data-from="<?php echo date('d M Y', strtotime($l['leave_from'])); ?>"
                                                data-to="<?php echo date('d M Y', strtotime($l['leave_to'])); ?>"
                                                data-days="<?php echo $l['total_days']; ?>"
                                                data-reason="<?php echo htmlspecialchars($l['reason']); ?>"
                                                data-status="<?php echo htmlspecialchars($l['status']); ?>"
                                                data-by="<?php echo htmlspecialchars($l['approved_by_name'] ?: '—'); ?>"
                                                data-remarks="<?php echo htmlspecialchars($l['remarks'] ?? ''); ?>"
                                                title="View/Review Leave Details">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger btn-delete-leave" data-id="<?php echo $l['id']; ?>" title="Remove Application">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Leave Review Modal -->
<div class="modal fade" id="reviewLeaveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-shield-halved me-2 text-primary"></i>Review Leave Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pt-0">
                <table class="table table-sm table-borderless small mb-4">
                    <tr>
                        <td class="text-muted" width="130">Employee ID / Name:</td>
                        <td class="fw-bold text-dark"><span id="rv_empid"></span> - <span id="rv_name"></span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Leave Type:</td>
                        <td class="fw-bold text-dark" id="rv_type"></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Duration Period:</td>
                        <td class="fw-bold text-dark"><span id="rv_from"></span> to <span id="rv_to"></span> (<span id="rv_days"></span> days)</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Application Reason:</td>
                        <td class="text-secondary" id="rv_reason" style="white-space: pre-wrap;"></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Decision Action By:</td>
                        <td class="fw-bold text-dark" id="rv_by"></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Current status:</td>
                        <td><span class="badge fw-semibold" id="rv_status"></span></td>
                    </tr>
                </table>

                <form id="actionLeaveForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="update_leave_status">
                    <input type="hidden" name="id" id="rv_id">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Approver Decision Remarks</label>
                        <textarea class="form-control form-control-sm" name="remarks" id="rv_remarks" rows="2" placeholder="Write decision notes..."></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success flex-fill py-2" id="btnApproveLeave"><i class="fa-solid fa-check me-2"></i>Approve Application</button>
                        <button type="button" class="btn btn-danger flex-fill py-2" id="btnRejectLeave"><i class="fa-solid fa-xmark me-2"></i>Reject Application</button>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="leaveToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="leaveToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("leaveToast");
    const m = document.getElementById("leaveToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    
    // Auto populate department and designation on select change
    const staffSelect = document.getElementById("form_staff_select");
    if(staffSelect) {
        staffSelect.addEventListener("change", function() {
            const opt = this.options[this.selectedIndex];
            if(opt && opt.value !== "") {
                document.getElementById("form_dept").value = opt.dataset.dept;
                document.getElementById("form_desig").value = opt.dataset.desig;
            } else {
                document.getElementById("form_dept").value = "";
                document.getElementById("form_desig").value = "";
            }
        });
    }

    // Auto calculate date range duration
    const dateFrom = document.getElementById("form_date_from");
    const dateTo = document.getElementById("form_date_to");
    const totalDays = document.getElementById("form_total_days");

    function calcDays() {
        if(dateFrom.value && dateTo.value) {
            const start = new Date(dateFrom.value);
            const end = new Date(dateTo.value);
            const diffTime = end - start;
            if(diffTime >= 0) {
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                totalDays.value = diffDays + " day(s)";
            } else {
                totalDays.value = "Invalid date range";
            }
        }
    }
    if(dateFrom) dateFrom.addEventListener("change", calcDays);
    if(dateTo) dateTo.addEventListener("change", calcDays);

    // Form Submit
    const applyForm = document.getElementById("applyLeaveForm");
    if (applyForm) {
        applyForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnApplyLeave");
            btn.disabled = true; btn.innerHTML = "Submitting application...";
            
            fetch("../../ajax/staff_attendance.php", { method: "POST", body: new FormData(applyForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-paper-plane me-2\"></i>Submit Application"; }
                })
                .catch(() => { showToast("Communication error.", false); btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-paper-plane me-2\"></i>Submit Application"; });
        });
    }

    // Bind Review Modal details
    document.querySelectorAll(".btn-view-leave").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("rv_id").value = this.dataset.id;
            document.getElementById("rv_empid").textContent = this.dataset.empid;
            document.getElementById("rv_name").textContent = this.dataset.name;
            document.getElementById("rv_type").textContent = this.dataset.type;
            document.getElementById("rv_from").textContent = this.dataset.from;
            document.getElementById("rv_to").textContent = this.dataset.to;
            document.getElementById("rv_days").textContent = this.dataset.days;
            document.getElementById("rv_reason").textContent = this.dataset.reason;
            document.getElementById("rv_by").textContent = this.dataset.by;
            document.getElementById("rv_remarks").value = this.dataset.remarks;
            
            const badge = document.getElementById("rv_status");
            badge.textContent = this.dataset.status;
            badge.className = "badge px-3 py-2 rounded-pill fw-semibold";
            if (this.dataset.status === "Approved") badge.classList.add("bg-success-soft","text-success");
            else if (this.dataset.status === "Rejected") badge.classList.add("bg-danger-soft","text-danger");
            else badge.classList.add("bg-warning-soft","text-warning");

            new bootstrap.Modal(document.getElementById("reviewLeaveModal")).show();
        });
    });

    // Handle Leave Approvals/Rejections inside modal
    function makeDecision(status) {
        const id = document.getElementById("rv_id").value;
        const remarks = document.getElementById("rv_remarks").value;
        const fd = new FormData();
        fd.append("action", "update_leave_status");
        fd.append("csrf_token", "' . csrfToken() . '");
        fd.append("id", id);
        fd.append("new_status", status);
        fd.append("remarks", remarks);

        fetch("../../ajax/staff_attendance.php", { method: "POST", body: fd })
            .then(r => r.json())
            .then(data => {
                showToast(data.message, data.success);
                if(data.success) setTimeout(() => location.reload(), 1000);
            });
    }

    const appBtn = document.getElementById("btnApproveLeave");
    if(appBtn) appBtn.addEventListener("click", () => makeDecision("Approved"));

    const rejBtn = document.getElementById("btnRejectLeave");
    if(rejBtn) rejBtn.addEventListener("click", () => makeDecision("Rejected"));

    // Delete application
    document.querySelectorAll(".btn-delete-leave").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            if(!confirm("Are you sure you want to permanently delete this leave application?")) return;
            const fd = new FormData();
            fd.append("action", "delete_leave");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/staff_attendance.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 1000);
                });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
