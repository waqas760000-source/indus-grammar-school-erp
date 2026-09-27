<?php
/**
 * Indus Grammar School ERP - Advance Salary & Employee Loans Management
 * Version 4.0.0 (Premium Redesign)
 */

$pageTitle = 'Advance Salary Management';
$breadcrumbActive = 'Payroll';
include_once __DIR__ . '/../../includes/header.php';

// Access Control
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'accountant'])) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to access the advance salary module.';
    redirect(APP_URL . '/dashboard.php');
}

$db = Database::getConnection();

// Search and filter parameters
$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

$where = " WHERE 1=1";
$params = [];
if (!empty($search)) {
    $where .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.employee_no LIKE :search OR a.reason LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if (!empty($statusFilter)) {
    $where .= " AND a.status = :status";
    $params['status'] = $statusFilter;
}

// Load active staff members for modal dropdown
$staffList = $db->query("SELECT id, employee_no, first_name, last_name, department, designation FROM staff WHERE status = 'Active' ORDER BY employee_no ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch advances list with staff details
$advances = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
        FROM advance_salary a
        JOIN staff s ON a.staff_id = s.id
        $where
        ORDER BY a.id DESC
    ");
    $stmt->execute($params);
    $advances = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading advance salaries: " . $e->getMessage());
}

// Calculate Executive Advance Metrics
$totalAdvanceCount = count($advances);
$pendingCount = 0;
$recoveredCount = 0;
$sumDisbursed = 0.0;
$sumRecovered = 0.0;
$sumOutstanding = 0.0;

foreach ($advances as $a) {
    $amt = (float)$a['amount'];
    $paid = (float)$a['paid_amount'];
    $rem  = (float)$a['remaining_balance'];

    $sumDisbursed += $amt;
    $sumRecovered += $paid;
    $sumOutstanding += $rem;

    if ($a['status'] === 'Recovered') $recoveredCount++;
    else $pendingCount++;
}

// Color generator helper for avatar badges
if (!function_exists('getAvatarColor')) {
    function getAvatarColor($name) {
        $colors = ['#1d4ed8', '#0d9488', '#b91c1c', '#c2410c', '#6d28d9', '#0369a1', '#be185d', '#4d7c0f'];
        $hash = crc32($name);
        return $colors[abs($hash) % count($colors)];
    }
}
?>

<style>
.adv-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.5rem 1.75rem;
    position: relative;
    overflow: hidden;
}
.adv-hero-card::after {
    content: "";
    position: absolute;
    top: -50%;
    right: -10%;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(147, 51, 234, 0.18) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.kpi-adv-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.15rem;
    transition: all 0.25s ease;
}
.kpi-adv-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

.staff-avatar-sm {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    color: #ffffff;
}

.btn-purple {
    background-color: #7c3aed;
    border-color: #7c3aed;
    color: #ffffff;
}
.btn-purple:hover {
    background-color: #6d28d9;
    border-color: #6d28d9;
    color: #ffffff;
}
.badge-soft-purple  { background-color: #f3e8ff; color: #6b21a8; }
.badge-soft-success { background-color: #dcfce7; color: #15803d; }
.badge-soft-warning { background-color: #fef9c3; color: #a16207; }

.progress-thin {
    height: 6px;
    border-radius: 3px;
    background-color: #e2e8f0;
}
</style>

<!-- Hero Title Header -->
<div class="adv-hero-card shadow-sm mb-4">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-purple bg-opacity-20 rounded-3 text-warning">
                    <i class="fa-solid fa-comments-dollar fs-2 text-warning"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-1">Advance Salary & Employee Loans</h3>
                    <p class="text-white-50 mb-0 small">
                        Record salary cash advances disbursed to staff. The system automatically calculates monthly installment recoveries during bulk payroll execution.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <a href="payroll.php" class="btn btn-outline-light btn-sm px-3 rounded-pill me-1">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
            </a>
            <?php if (hasPermission('hr_manage')): ?>
            <button class="btn btn-purple btn-sm px-3 rounded-pill fw-semibold" data-bs-toggle="modal" data-bs-target="#advanceModal" onclick="resetForm()">
                <i class="fa-solid fa-plus me-1"></i> Disburse Advance
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 5 Advance Executive Micro-Cards -->
<div class="row g-3 mb-4">
    <!-- Active Loans Count -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-adv-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">PENDING LOANS</span>
                <span class="badge bg-purple-soft text-purple p-2 rounded-circle"><i class="fa-solid fa-comments-dollar"></i></span>
            </div>
            <h4 class="fw-bold text-dark mb-0"><?php echo $pendingCount; ?> Active</h4>
            <small class="text-muted"><?php echo $recoveredCount; ?> fully recovered</small>
        </div>
    </div>

    <!-- Total Disbursed Advances -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-adv-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">TOTAL DISBURSED</span>
                <span class="badge bg-info-soft text-info p-2 rounded-circle"><i class="fa-solid fa-hand-holding-dollar"></i></span>
            </div>
            <h4 class="fw-bold text-info mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumDisbursed, 0); ?></h4>
            <small class="text-muted">Total principal loans</small>
        </div>
    </div>

    <!-- Total Recovered -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-adv-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">RECOVERED PAYMENTS</span>
                <span class="badge bg-success-soft text-success p-2 rounded-circle"><i class="fa-solid fa-circle-check"></i></span>
            </div>
            <h4 class="fw-bold text-success mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumRecovered, 0); ?></h4>
            <small class="text-muted"><?php echo $sumDisbursed > 0 ? round(($sumRecovered/$sumDisbursed)*100) : 0; ?>% paid back</small>
        </div>
    </div>

    <!-- Outstanding Balance -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-adv-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">OUTSTANDING BALANCE</span>
                <span class="badge bg-danger-soft text-danger p-2 rounded-circle"><i class="fa-solid fa-hourglass-half"></i></span>
            </div>
            <h4 class="fw-bold text-danger mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumOutstanding, 0); ?></h4>
            <small class="text-muted">Remaining to collect</small>
        </div>
    </div>

    <!-- Monthly Recovery Rate -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-adv-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">AUTO RECOVERY</span>
                <span class="badge bg-warning-soft text-warning p-2 rounded-circle"><i class="fa-solid fa-gears"></i></span>
            </div>
            <h4 class="fw-bold text-warning mb-0" style="font-size: 1.15rem;">Payroll Sync</h4>
            <small class="text-muted">Auto-deducted monthly</small>
        </div>
    </div>
</div>

<!-- Search & Filtering Toolbar -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center" id="filterForm">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control bg-light border-start-0" name="search" placeholder="Search staff name, EMP ID, or reason..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select bg-light" name="status" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Loan Statuses</option>
                    <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending (Active Loan)</option>
                    <option value="Recovered" <?php echo $statusFilter === 'Recovered' ? 'selected' : ''; ?>>Fully Recovered</option>
                </select>
            </div>
            <div class="col-md-3 text-end">
                <a href="payroll_advance.php" class="btn btn-outline-secondary w-100 rounded-pill"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Advances Register Roster Table -->
<div class="card border-0 shadow-sm mb-5" style="border-radius:14px;">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
        <h5 class="fw-bold text-secondary mb-0">
            <i class="fa-solid fa-receipt me-2 text-purple"></i>Advance Loans Register
        </h5>
        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
            Outstanding Loans: <strong class="text-danger">Rs. <?php echo number_format($sumOutstanding, 2); ?></strong>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Staff Member</th>
                        <th>EMP ID</th>
                        <th class="text-center">Disbursed Date</th>
                        <th class="text-end">Principal Loan</th>
                        <th class="text-center">Installments</th>
                        <th class="text-end">Monthly Deduction</th>
                        <th class="text-end text-success">Paid Amount</th>
                        <th class="text-end text-danger">Remaining Balance</th>
                        <th class="text-center">Recovery Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($advances)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-comments-dollar fs-1 text-muted mb-2 d-block opacity-50"></i>
                                No advance salary records logged. Click <strong>"Disburse Advance"</strong> above to record a loan.
                            </td>
                        </tr>
                    <?php else: foreach ($advances as $a): 
                        $fullName = trim($a['first_name'] . ' ' . $a['last_name']);
                        $initials = strtoupper(substr($a['first_name'], 0, 1) . substr($a['last_name'], 0, 1));
                        $bgColor  = getAvatarColor($fullName);
                        
                        $amt = (float)$a['amount'];
                        $paid = (float)$a['paid_amount'];
                        $rem  = (float)$a['remaining_balance'];
                        $pct  = $amt > 0 ? min(100, round(($paid / $amt) * 100)) : 0;
                        $isRecovered = $a['status'] === 'Recovered';
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="staff-avatar-sm" style="background-color: <?php echo $bgColor; ?>;">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark mb-0"><?php echo sanitize($fullName); ?></div>
                                        <small class="text-muted"><?php echo sanitize($a['designation'] . ' | ' . $a['department']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><code><?php echo sanitize($a['employee_no']); ?></code></td>
                            <td class="text-center small fw-semibold">
                                <?php echo date('d M Y', strtotime($a['advance_date'])); ?>
                            </td>
                            <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($amt, 2); ?></td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border"><?php echo $a['installments']; ?> Months</span>
                            </td>
                            <td class="text-end fw-semibold text-purple">
                                Rs. <?php echo number_format($a['installment_amount'], 2); ?> / mo
                            </td>
                            <td class="text-end text-success fw-semibold">
                                Rs. <?php echo number_format($paid, 2); ?>
                            </td>
                            <td class="text-end text-danger fw-bold">
                                Rs. <?php echo number_format($rem, 2); ?>
                                <div class="progress progress-thin mt-1 ms-auto" style="max-width:100px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $pct; ?>%;"></div>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($isRecovered): ?>
                                    <span class="badge bg-success-soft text-success px-3 py-1 rounded-pill"><i class="fa-solid fa-circle-check me-1"></i>Fully Recovered</span>
                                <?php else: ?>
                                    <span class="badge bg-warning-soft text-warning px-3 py-1 rounded-pill"><i class="fa-solid fa-hourglass-half me-1"></i>Pending (<?php echo $pct; ?>%)</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" onclick='editAdvance(<?php echo json_encode($a); ?>)'>
                                    <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                                </button>
                                <?php if (hasPermission('hr_manage')): ?>
                                    <button class="btn btn-sm btn-outline-danger btn-delete rounded-pill px-3" data-id="<?php echo $a['id']; ?>">
                                        <i class="fa-solid fa-trash-can me-1"></i>Delete
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="advanceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;">
            <div class="modal-header border-0 bg-dark text-white pt-4 px-4" style="border-top-left-radius:18px; border-top-right-radius:18px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 bg-purple bg-opacity-20 rounded text-warning fs-3">
                        <i class="fa-solid fa-comments-dollar"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="modalTitle">Disburse Salary Advance</h5>
                        <div class="small text-white-50">Log Principal Loan & Installment Schedule</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="advanceForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_advance_salary">
                    <input type="hidden" name="id" id="advanceId" value="0">

                    <!-- Calculated Monthly Installment Preview Box -->
                    <div class="p-3 bg-light rounded-3 border mb-4 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted fw-bold text-uppercase d-block mb-1">Calculated Monthly Installment</small>
                            <h4 class="fw-bold text-purple mb-0" id="previewInstallment">Rs. 0.00 / month</h4>
                        </div>
                        <span class="badge bg-purple-soft text-purple p-2 rounded-circle fs-5"><i class="fa-solid fa-calculator"></i></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Employee <span class="text-danger">*</span></label>
                        <select class="form-select" name="staff_id" id="advanceStaffId" required>
                            <option value="">-- Choose Staff Member --</option>
                            <?php foreach ($staffList as $st): ?>
                                <option value="<?php echo $st['id']; ?>">
                                    <?php echo htmlspecialchars($st['employee_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['designation'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Disbursement Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="advance_date" id="advanceDate" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Loan Principal Amount (Rs.) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light small">Rs.</span>
                                <input type="number" step="0.01" class="form-control fw-bold text-purple fs-5" name="amount" id="advanceAmount" required min="100" placeholder="5000.00" oninput="recalcInstallment()">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Recovery Tenure (Installment Months) <span class="text-danger">*</span></label>
                        <select class="form-select" name="installments" id="advanceInstallments" required onchange="recalcInstallment()">
                            <option value="1">1 Month (Full Deduct in Next Run)</option>
                            <option value="2">2 Months</option>
                            <option value="3" selected>3 Months</option>
                            <option value="4">4 Months</option>
                            <option value="6">6 Months</option>
                            <option value="12">12 Months (1 Year)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reason for Advance <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="reason" id="advanceReason" rows="2" placeholder="e.g. Medical emergency, family expenses" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 bg-light" style="border-bottom-left-radius:18px; border-bottom-right-radius:18px;">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="advanceForm" class="btn btn-purple rounded-pill px-4" id="btnSave">
                    <i class="fa-solid fa-check me-1"></i> Disburse Advance
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="advToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="advToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
const modalObj = new bootstrap.Modal(document.getElementById("advanceModal"));

function showToast(msg, ok) {
    const t = document.getElementById("advToast");
    const m = document.getElementById("advToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function recalcInstallment() {
    const amt = parseFloat(document.getElementById("advanceAmount").value) || 0;
    const inst = parseInt(document.getElementById("advanceInstallments").value) || 1;
    const instAmt = inst > 0 ? (amt / inst) : 0;
    document.getElementById("previewInstallment").textContent = "Rs. " + instAmt.toLocaleString("en-US", {minimumFractionDigits: 2}) + " / month";
}

function resetForm() {
    document.getElementById("advanceForm").reset();
    document.getElementById("advanceId").value = "0";
    document.getElementById("modalTitle").textContent = "Disburse Salary Advance";
    document.getElementById("btnSave").innerHTML = `<i class="fa-solid fa-check me-1"></i> Disburse Advance`;
    recalcInstallment();
}

function editAdvance(data) {
    resetForm();
    document.getElementById("advanceId").value = data.id;
    document.getElementById("advanceStaffId").value = data.staff_id;
    document.getElementById("advanceDate").value = data.advance_date;
    document.getElementById("advanceAmount").value = data.amount;
    document.getElementById("advanceInstallments").value = data.installments;
    document.getElementById("advanceReason").value = data.reason;
    document.getElementById("modalTitle").textContent = "Edit Salary Advance";
    document.getElementById("btnSave").innerHTML = `<i class="fa-solid fa-check me-1"></i> Update Advance`;
    recalcInstallment();
    modalObj.show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Form Save
    const form = document.getElementById("advanceForm");
    if(form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSave");
            btn.disabled = true; 
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...`;

            fetch("../../ajax/payroll.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        modalObj.hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        btn.disabled = false; 
                        btn.innerHTML = `<i class="fa-solid fa-check me-1"></i> Disburse Advance`;
                    }
                })
                .catch(() => {
                    showToast("System error.", false);
                    btn.disabled = false; 
                    btn.innerHTML = `<i class="fa-solid fa-check me-1"></i> Disburse Advance`;
                });
        });
    }

    // Delete Trigger
    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this advance application? Outstanding recovery balances will be discarded.")) return;
            const id = this.dataset.id;
            this.disabled = true;

            const fd = new FormData();
            fd.append("action", "delete_advance_salary");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);

            fetch("../../ajax/payroll.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else this.disabled = false;
                })
                .catch(() => {
                    showToast("System error.", false);
                    this.disabled = false;
                });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
