<?php
/**
 * Indus Grammar School ERP - Staff Leave Management Console
 * Version 5.0.0 - Executive Absence Records & Approval Workflow
 */

$pageTitle = 'Staff Leave Management';
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
    $staffList   = $db->query("SELECT id, employee_no, first_name, last_name, department, designation FROM staff WHERE status = 'Active' ORDER BY employee_no ASC")->fetchAll(PDO::FETCH_ASSOC);
    $departments = $db->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
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

// Compute leave summary statistics
$leaveStats = [
    'total'    => count($leaves),
    'pending'  => 0,
    'approved' => 0,
    'rejected' => 0,
    'days'     => 0
];

foreach ($leaves as $l) {
    if ($l['status'] === 'Pending')   $leaveStats['pending']++;
    elseif ($l['status'] === 'Approved') {
        $leaveStats['approved']++;
        $leaveStats['days'] += (int)$l['total_days'];
    }
    elseif ($l['status'] === 'Rejected') $leaveStats['rejected']++;
}

$approvalRate = $leaveStats['total'] > 0 ? round(($leaveStats['approved'] / $leaveStats['total']) * 100, 1) : 0;
?>

<style>
.leave-row:hover { background-color: rgba(13, 110, 253, 0.025); }
.attachment-pill { font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 12px; }
</style>

<!-- Top Executive Hero Banner Header -->
<div class="card border-0 shadow-lg mb-4 overflow-hidden" style="border-radius: 16px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f2744 100%);">
    <div class="card-body p-4 p-md-5 position-relative">
        <!-- Decorative glowing orb backdrop -->
        <div class="position-absolute end-0 top-0 translate-middle-y me-5 mt-4" style="width: 300px; height: 300px; background: radial-gradient(circle, rgba(13, 110, 253, 0.25) 0%, rgba(0, 0, 0, 0) 70%); pointer-events: none; filter: blur(40px);"></div>
        
        <div class="row align-items-center position-relative" style="z-index: 2;">
            <div class="col-lg-8 mb-3 mb-lg-0">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(13, 110, 253, 0.15); border: 1px solid rgba(13, 110, 253, 0.3);">
                    <span class="pulse-dot bg-primary rounded-circle d-inline-block" style="width: 8px; height: 8px;"></span>
                    <span class="fw-bold uppercase tracking-wider" style="font-size: 0.72rem; letter-spacing: 1px; color: #60a5fa;">LEAVE WORKFLOW CONSOLE</span>
                </div>
                <h2 class="fw-extrabold text-white mb-2 tracking-tight d-flex align-items-center gap-2">
                    <i class="fa-solid fa-hospital-user text-warning fs-2 me-2"></i>Staff Leave Management
                </h2>
                <p class="mb-0 leading-relaxed" style="font-size: 0.95rem; color: #94a3b8;">
                    Record employee absences, manage approvals, check attachments, and update logs.
                </p>
            </div>
            
            <div class="col-lg-4 text-lg-end">
                <div class="d-inline-flex align-items-center gap-3 p-3 rounded-3 shadow-sm border" style="background: rgba(255, 255, 255, 0.07); backdrop-filter: blur(12px); border-color: rgba(255, 255, 255, 0.15) !important;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-warning" style="width: 42px; height: 42px; background: rgba(245, 158, 11, 0.15);">
                        <i class="fa-solid fa-clock-rotate-left fs-4"></i>
                    </div>
                    <div class="text-start">
                        <div class="text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.8px; color: #94a3b8;">PENDING REVIEWS</div>
                        <div class="fw-bold text-warning fs-5 mb-0"><?php echo $leaveStats['pending']; ?> Request(s)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 5 Summary KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center bg-white border-start border-primary border-4">
            <div class="small text-muted fw-semibold text-uppercase" style="font-size:0.7rem;">Applications</div>
            <div class="fs-3 fw-bold text-dark my-1"><?php echo $leaveStats['total']; ?></div>
            <div class="small text-muted" style="font-size:0.7rem;">Total Submitted</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center" style="background-color: #fffbeb;">
            <div class="small text-warning fw-semibold text-uppercase" style="font-size:0.7rem;">Pending Review</div>
            <div class="fs-3 fw-bold text-warning my-1"><?php echo $leaveStats['pending']; ?></div>
            <div class="small text-warning-50" style="font-size:0.7rem;">Awaiting Decision</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center" style="background-color: #f0fdf4;">
            <div class="small text-success fw-semibold text-uppercase" style="font-size:0.7rem;">Approved</div>
            <div class="fs-3 fw-bold text-success my-1"><?php echo $leaveStats['approved']; ?></div>
            <div class="small text-success-50" style="font-size:0.7rem;">Authorized Leaves</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center" style="background-color: #fef2f2;">
            <div class="small text-danger fw-semibold text-uppercase" style="font-size:0.7rem;">Rejected</div>
            <div class="fs-3 fw-bold text-danger my-1"><?php echo $leaveStats['rejected']; ?></div>
            <div class="small text-danger-50" style="font-size:0.7rem;">Declined Requests</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center" style="background-color: #faf5ff;">
            <div class="small text-primary fw-semibold text-uppercase" style="font-size:0.7rem;">Authorized Days</div>
            <div class="fs-3 fw-bold text-primary my-1"><?php echo $leaveStats['days']; ?> <span class="fs-6 font-monospace">days</span></div>
            <div class="small text-primary-50" style="font-size:0.7rem;">Total Days Off</div>
        </div>
    </div>
    <div class="col-12 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 rounded-3 text-center text-white" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6);">
            <div class="small text-white-50 fw-semibold text-uppercase" style="font-size:0.7rem;">Approval Rate</div>
            <div class="fs-3 fw-bold text-white my-1"><?php echo $approvalRate; ?>%</div>
            <div class="small text-white-50" style="font-size:0.7rem;">Authorization Index</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Submit / Record Leave Request Form -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius:14px;">
            <div class="card-header bg-white p-3 border-bottom">
                <h6 class="fw-bold text-secondary mb-0">
                    <i class="fa-solid fa-file-pen me-2 text-primary"></i>Record Staff Absence / Apply Leave
                </h6>
            </div>
            <div class="card-body p-4">
                <form id="applyLeaveForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="apply_staff_leave">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Select Employee *</label>
                        <select class="form-select form-select-sm" name="staff_id" id="form_staff_select" required>
                            <option value="">— Select Employee —</option>
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
                            <label class="form-label small text-muted mb-1">Department</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="form_dept" disabled placeholder="Department">
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted mb-1">Designation</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="form_desig" disabled placeholder="Designation">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Leave Category *</label>
                        <select class="form-select form-select-sm" name="leave_type" required>
                            <option value="Casual Leave">Casual Leave</option>
                            <option value="Medical Leave">Medical Leave</option>
                            <option value="Annual Leave">Annual Leave</option>
                            <option value="Emergency Leave">Emergency Leave</option>
                            <option value="Unpaid Leave">Unpaid Leave</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Leave From *</label>
                            <input type="date" class="form-control form-control-sm" name="leave_from" id="form_date_from" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Leave To *</label>
                            <input type="date" class="form-control form-control-sm" name="leave_to" id="form_date_to" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Total Calendar Days</label>
                        <input type="text" class="form-control form-control-sm text-center fw-bold bg-light" id="form_total_days" readonly value="0 days">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Reason for Absence *</label>
                        <textarea class="form-control form-control-sm" name="reason" rows="2" placeholder="State clear reason for absence..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Attachment (Medical Cert / Application)</label>
                        <input type="file" class="form-control form-control-sm" name="attachment" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Internal Office Remarks</label>
                        <textarea class="form-control form-control-sm" name="remarks" rows="2" placeholder="Admin decision notes (Optional)"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm" id="btnApplyLeave">
                        <i class="fa-solid fa-paper-plane me-2"></i>Submit Leave Application
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Filter & History Applications Console -->
    <div class="col-lg-8">
        <!-- Leave Report Filter Card -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
            <div class="card-body p-4">
                <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-sliders me-2 text-primary"></i>Filter Applications Roster</h6>
                <form method="GET" class="row g-3 align-items-end" id="leaveFilterForm">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Department</label>
                        <select class="form-select form-select-sm" name="dept">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($filterDept === $d) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Leave Category</label>
                        <select class="form-select form-select-sm" name="leave_type">
                            <option value="">All Categories</option>
                            <option value="Casual Leave" <?php echo ($filterType === 'Casual Leave') ? 'selected' : ''; ?>>Casual Leave</option>
                            <option value="Medical Leave" <?php echo ($filterType === 'Medical Leave') ? 'selected' : ''; ?>>Medical Leave</option>
                            <option value="Annual Leave" <?php echo ($filterType === 'Annual Leave') ? 'selected' : ''; ?>>Annual Leave</option>
                            <option value="Emergency Leave" <?php echo ($filterType === 'Emergency Leave') ? 'selected' : ''; ?>>Emergency Leave</option>
                            <option value="Unpaid Leave" <?php echo ($filterType === 'Unpaid Leave') ? 'selected' : ''; ?>>Unpaid Leave</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo ($filterStatus === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo ($filterStatus === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo ($filterStatus === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-sm btn-primary w-100 py-1 fw-semibold">
                            <i class="fa-solid fa-filter me-1"></i>Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- History Applications Roster Table Card -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius:14px; overflow:hidden;">
            <div class="card-header bg-white p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span class="fw-bold text-dark"><i class="fa-solid fa-list-check me-2 text-primary"></i>Leave Applications Register (<span id="visibleLeaveCount"><?php echo count($leaves); ?></span> Requests)</span>
                <div class="position-relative" style="width: 250px;">
                    <input type="text" class="form-control form-control-sm ps-4 rounded-pill" id="leaveSearchInput" placeholder="Filter requests by keyword...">
                    <i class="fa-solid fa-magnifying-glass position-absolute start-0 top-50 translate-middle-y ms-2 text-muted small"></i>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table custom-table table-hover align-middle mb-0" id="leaveApplicationsTable">
                    <thead class="bg-light">
                        <tr>
                            <th width="110" class="ps-4">Emp ID</th>
                            <th>Staff Member</th>
                            <th>Category / Reason</th>
                            <th width="170">Date Duration</th>
                            <th width="110">Status</th>
                            <th width="100" class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leaves)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-folder-open fs-1 d-block mb-2 opacity-50"></i>
                                    <h6 class="fw-bold mb-1">No Leave Applications Found</h6>
                                    <p class="small mb-0">No staff leave applications match the selected filter criteria.</p>
                                </td>
                            </tr>
                        <?php else: foreach ($leaves as $l): 
                            $fullName = trim($l['first_name'] . ' ' . $l['last_name']);
                            $avatarBg = getAvatarColor($fullName);
                            $initials = strtoupper(substr($l['first_name'] ?? 'S', 0, 1) . substr($l['last_name'] ?? 'M', 0, 1));
                            
                            $badgeClass = 'bg-warning-soft text-warning';
                            if ($l['status'] === 'Approved') $badgeClass = 'bg-success-soft text-success';
                            elseif ($l['status'] === 'Rejected') $badgeClass = 'bg-danger-soft text-danger';
                        ?>
                            <tr class="leave-row" data-search="<?php echo htmlspecialchars(strtolower($l['employee_no'] . ' ' . $fullName . ' ' . $l['department'] . ' ' . $l['designation'] . ' ' . $l['leave_type'] . ' ' . $l['status'])); ?>">
                                <td class="ps-4">
                                    <span class="badge bg-light text-dark border font-monospace"><?php echo sanitize($l['employee_no']); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle text-white d-flex align-items-center justify-content-center me-2 fw-bold shadow-sm flex-shrink-0" style="width:32px; height:32px; background-color: <?php echo $avatarBg; ?>; font-size: 0.78rem;">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark mb-0 text-truncate" style="max-width:140px;"><?php echo sanitize($fullName); ?></div>
                                            <div class="small text-muted" style="font-size:0.7rem;"><?php echo sanitize($l['designation']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border mb-1"><?php echo sanitize($l['leave_type']); ?></span>
                                    <div class="small text-muted text-truncate" style="max-width: 160px;" title="<?php echo htmlspecialchars($l['reason']); ?>">
                                        <?php echo sanitize($l['reason']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="small fw-bold text-dark d-block">
                                        <?php echo date('d M Y', strtotime($l['leave_from'])) . ' - ' . date('d M Y', strtotime($l['leave_to'])); ?>
                                    </span>
                                    <span class="badge bg-primary-soft text-primary mt-1 font-monospace"><?php echo $l['total_days']; ?> Day(s)</span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $badgeClass; ?> px-3 py-2 rounded-pill fw-bold" style="font-size:0.75rem;">
                                        <?php echo $l['status']; ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <?php if ($l['attachment']): ?>
                                            <a href="<?php echo APP_URL . '/' . $l['attachment']; ?>" target="_blank" class="btn btn-sm btn-outline-info rounded-circle" title="View Uploaded Attachment Document">
                                                <i class="fa-solid fa-paperclip"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-primary btn-view-leave rounded-circle"
                                                data-id="<?php echo $l['id']; ?>"
                                                data-empid="<?php echo htmlspecialchars($l['employee_no']); ?>"
                                                data-name="<?php echo htmlspecialchars($fullName); ?>"
                                                data-type="<?php echo htmlspecialchars($l['leave_type']); ?>"
                                                data-from="<?php echo date('d M Y', strtotime($l['leave_from'])); ?>"
                                                data-to="<?php echo date('d M Y', strtotime($l['leave_to'])); ?>"
                                                data-days="<?php echo $l['total_days']; ?>"
                                                data-reason="<?php echo htmlspecialchars($l['reason']); ?>"
                                                data-status="<?php echo htmlspecialchars($l['status']); ?>"
                                                data-by="<?php echo htmlspecialchars($l['approved_by_name'] ?: '—'); ?>"
                                                data-remarks="<?php echo htmlspecialchars($l['remarks'] ?? ''); ?>"
                                                data-attachment="<?php echo $l['attachment'] ? APP_URL . '/' . $l['attachment'] : ''; ?>"
                                                title="Review & Manage Approval">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger btn-delete-leave rounded-circle" data-id="<?php echo $l['id']; ?>" title="Remove Application">
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

<!-- Interactive Leave Review & Approval Modal -->
<div class="modal fade" id="reviewLeaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-top-left-radius:16px; border-top-right-radius:16px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary-soft p-2 d-flex align-items-center justify-content-center text-primary" style="width:40px; height:40px;">
                        <i class="fa-solid fa-shield-halved fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0">Review Leave Application</h5>
                        <p class="small text-slate-300 mb-0">Authorize or reject absence request</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <table class="table table-sm table-borderless small mb-4">
                    <tr class="border-bottom">
                        <td class="text-muted" width="140">Employee:</td>
                        <td class="fw-bold text-dark"><span id="rv_empid"></span> — <span id="rv_name"></span></td>
                    </tr>
                    <tr class="border-bottom">
                        <td class="text-muted">Leave Category:</td>
                        <td class="fw-bold text-dark" id="rv_type"></td>
                    </tr>
                    <tr class="border-bottom">
                        <td class="text-muted">Duration Period:</td>
                        <td class="fw-bold text-dark"><span id="rv_from"></span> to <span id="rv_to"></span> (<span id="rv_days"></span> days)</td>
                    </tr>
                    <tr class="border-bottom">
                        <td class="text-muted">Absence Reason:</td>
                        <td class="text-secondary" id="rv_reason" style="white-space: pre-wrap;"></td>
                    </tr>
                    <tr class="border-bottom" id="rv_attach_row" style="display:none;">
                        <td class="text-muted">Attachment Document:</td>
                        <td><a href="#" id="rv_attach_link" target="_blank" class="btn btn-xs btn-outline-info rounded-pill px-3"><i class="fa-solid fa-paperclip me-1"></i>View File</a></td>
                    </tr>
                    <tr class="border-bottom">
                        <td class="text-muted">Last Decision By:</td>
                        <td class="fw-bold text-dark" id="rv_by"></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Current Status:</td>
                        <td><span class="badge fw-bold" id="rv_status"></span></td>
                    </tr>
                </table>

                <form id="actionLeaveForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="update_leave_status">
                    <input type="hidden" name="id" id="rv_id">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Approver Decision Remarks / Notes</label>
                        <textarea class="form-control form-control-sm" name="remarks" id="rv_remarks" rows="2" placeholder="Write decision notes or approval conditions..."></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success flex-fill py-2 fw-bold shadow-sm" id="btnApproveLeave">
                            <i class="fa-solid fa-check me-2"></i>Approve Application
                        </button>
                        <button type="button" class="btn btn-danger flex-fill py-2 fw-bold shadow-sm" id="btnRejectLeave">
                            <i class="fa-solid fa-xmark me-2"></i>Reject Application
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light border-top-0" style="border-bottom-left-radius:16px; border-bottom-right-radius:16px;">
                <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="leaveToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert">
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
    // Auto populate department & designation
    const staffSelect = document.getElementById("form_staff_select");
    if (staffSelect) {
        staffSelect.addEventListener("change", function() {
            const opt = this.options[this.selectedIndex];
            if (opt && opt.value !== "") {
                document.getElementById("form_dept").value  = opt.dataset.dept || "";
                document.getElementById("form_desig").value = opt.dataset.desig || "";
            } else {
                document.getElementById("form_dept").value  = "";
                document.getElementById("form_desig").value = "";
            }
        });
    }

    // Auto calculate calendar days duration
    const dateFrom  = document.getElementById("form_date_from");
    const dateTo    = document.getElementById("form_date_to");
    const totalDays = document.getElementById("form_total_days");

    function calcDays() {
        if (dateFrom.value && dateTo.value) {
            const start = new Date(dateFrom.value);
            const end   = new Date(dateTo.value);
            const diffTime = end - start;
            if (diffTime >= 0) {
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                totalDays.value = diffDays + " day(s)";
            } else {
                totalDays.value = "Invalid date range";
            }
        }
    }
    if (dateFrom) dateFrom.addEventListener("change", calcDays);
    if (dateTo)   dateTo.addEventListener("change", calcDays);

    // Instant search filter on leave history table
    const searchInput = document.getElementById("leaveSearchInput");
    if (searchInput) {
        searchInput.addEventListener("keyup", function() {
            const query = this.value.toLowerCase().trim();
            let visible = 0;
            document.querySelectorAll(".leave-row").forEach(row => {
                const searchData = row.dataset.search || "";
                if (searchData.includes(query)) {
                    row.style.display = "";
                    visible++;
                } else {
                    row.style.display = "none";
                }
            });
            const visCount = document.getElementById("visibleLeaveCount");
            if (visCount) visCount.textContent = visible;
        });
    }

    // Submit Leave Application Form via AJAX
    const applyForm = document.getElementById("applyLeaveForm");
    if (applyForm) {
        applyForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnApplyLeave");
            btn.disabled = true; 
            btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Submitting Application...\';
            
            fetch("../../ajax/staff_attendance.php", { method: "POST", body: new FormData(applyForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) {
                        setTimeout(() => location.reload(), 1000);
                    } else { 
                        btn.disabled = false; 
                        btn.innerHTML = \'<i class="fa-solid fa-paper-plane me-2"></i>Submit Leave Application\'; 
                    }
                })
                .catch(() => { 
                    showToast("Network communication error.", false); 
                    btn.disabled = false; 
                    btn.innerHTML = \'<i class="fa-solid fa-paper-plane me-2"></i>Submit Leave Application\'; 
                });
        });
    }

    // Bind Review Modal details
    document.querySelectorAll(".btn-view-leave").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("rv_id").value          = this.dataset.id;
            document.getElementById("rv_empid").textContent   = this.dataset.empid;
            document.getElementById("rv_name").textContent    = this.dataset.name;
            document.getElementById("rv_type").textContent    = this.dataset.type;
            document.getElementById("rv_from").textContent    = this.dataset.from;
            document.getElementById("rv_to").textContent      = this.dataset.to;
            document.getElementById("rv_days").textContent    = this.dataset.days;
            document.getElementById("rv_reason").textContent  = this.dataset.reason;
            document.getElementById("rv_by").textContent      = this.dataset.by;
            document.getElementById("rv_remarks").value       = this.dataset.remarks;
            
            const attachRow  = document.getElementById("rv_attach_row");
            const attachLink = document.getElementById("rv_attach_link");
            if (this.dataset.attachment) {
                attachLink.href = this.dataset.attachment;
                attachRow.style.display = "";
            } else {
                attachRow.style.display = "none";
            }

            const badge = document.getElementById("rv_status");
            badge.textContent = this.dataset.status;
            badge.className = "badge px-3 py-2 rounded-pill fw-bold";
            if (this.dataset.status === "Approved") badge.classList.add("bg-success-soft","text-success");
            else if (this.dataset.status === "Rejected") badge.classList.add("bg-danger-soft","text-danger");
            else badge.classList.add("bg-warning-soft","text-warning");

            new bootstrap.Modal(document.getElementById("reviewLeaveModal")).show();
        });
    });

    // Make Approval or Rejection Decision
    function makeDecision(status) {
        const id      = document.getElementById("rv_id").value;
        const remarks = document.getElementById("rv_remarks").value;
        const fd      = new FormData();
        fd.append("action", "update_leave_status");
        fd.append("csrf_token", "' . csrfToken() . '");
        fd.append("id", id);
        fd.append("new_status", status);
        fd.append("remarks", remarks);

        fetch("../../ajax/staff_attendance.php", { method: "POST", body: fd })
            .then(r => r.json())
            .then(data => {
                showToast(data.message, data.success);
                if (data.success) setTimeout(() => location.reload(), 1000);
            });
    }

    const appBtn = document.getElementById("btnApproveLeave");
    if (appBtn) appBtn.addEventListener("click", () => makeDecision("Approved"));

    const rejBtn = document.getElementById("btnRejectLeave");
    if (rejBtn) rejBtn.addEventListener("click", () => makeDecision("Rejected"));

    // Delete application
    document.querySelectorAll(".btn-delete-leave").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            if (!confirm("Are you sure you want to permanently delete this leave application?")) return;
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
