<?php
/**
 * Indus Grammar School ERP - Deductions & Penalty Fines Management
 * Version 4.0.0 (Premium Redesign)
 */

$pageTitle = 'Deductions Management';
$breadcrumbActive = 'Payroll';
include_once __DIR__ . '/../../includes/header.php';

// Access Control
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'accountant'])) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to access the payroll deductions module.';
    redirect(APP_URL . '/dashboard.php');
}

$db = Database::getConnection();

// Search and filter parameters
$search = sanitize($_GET['search'] ?? '');
$typeFilter = sanitize($_GET['type'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

$where = " WHERE 1=1";
$params = [];
if (!empty($search)) {
    $where .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.employee_no LIKE :search OR d.reason LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if (!empty($typeFilter)) {
    $where .= " AND d.deduction_type = :type";
    $params['type'] = $typeFilter;
}
if (!empty($statusFilter)) {
    $where .= " AND d.status = :status";
    $params['status'] = $statusFilter;
}

// Load active staff members for modal dropdown
$staffList = $db->query("SELECT id, employee_no, first_name, last_name, department, designation FROM staff WHERE status = 'Active' ORDER BY employee_no ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch deductions list with staff details
$deductions = [];
try {
    $stmt = $db->prepare("
        SELECT d.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
        FROM deductions d
        JOIN staff s ON d.staff_id = s.id
        $where
        ORDER BY d.id DESC
    ");
    $stmt->execute($params);
    $deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading deductions: " . $e->getMessage());
}

// Calculate Deductions Metrics
$totalDeductionsCount = count($deductions);
$activeCount = 0;
$sumTotal = 0.0;
$sumTax = 0.0;
$sumPF = 0.0;
$sumFines = 0.0;

foreach ($deductions as $d) {
    if ($d['status'] === 'Active') {
        $activeCount++;
        $amt = (float)$d['amount'];
        $sumTotal += $amt;
        
        $t = strtolower($d['deduction_type']);
        if (str_contains($t, 'tax')) $sumTax += $amt;
        elseif (str_contains($t, 'provident') || str_contains($t, 'pf') || str_contains($t, 'eobi')) $sumPF += $amt;
        else $sumFines += $amt;
    }
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
.ded-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.5rem 1.75rem;
    position: relative;
    overflow: hidden;
}
.ded-hero-card::after {
    content: "";
    position: absolute;
    top: -50%;
    right: -10%;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(239, 68, 68, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.kpi-ded-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.15rem;
    transition: all 0.25s ease;
}
.kpi-ded-card:hover {
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

.badge-soft-danger  { background-color: #fee2e2; color: #b91c1c; }
.badge-soft-purple  { background-color: #f3e8ff; color: #6b21a8; }
.badge-soft-info    { background-color: #e0f2fe; color: #0369a1; }
.badge-soft-warning { background-color: #fef9c3; color: #a16207; }
</style>

<!-- Hero Title Header -->
<div class="ded-hero-card shadow-sm mb-4">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-danger bg-opacity-20 rounded-3 text-warning">
                    <i class="fa-solid fa-circle-minus fs-2"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-1">Deductions & Penalty Fines Management</h3>
                    <p class="text-white-50 mb-0 small">
                        Configure structural tax withholdings, custom cash deductions, Provident Fund allocations, or penalty fines applied to staff salaries.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <a href="payroll.php" class="btn btn-outline-light btn-sm px-3 rounded-pill me-1">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
            </a>
            <?php if (hasPermission('hr_manage')): ?>
            <button class="btn btn-danger btn-sm px-3 rounded-pill fw-semibold" data-bs-toggle="modal" data-bs-target="#deductionModal" onclick="resetForm()">
                <i class="fa-solid fa-plus me-1"></i> Log Deduction
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 5 Deductions Executive Micro-Cards -->
<div class="row g-3 mb-4">
    <!-- Active Deductions Count -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-ded-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">ACTIVE DEDUCTIONS</span>
                <span class="badge bg-danger-soft text-danger p-2 rounded-circle"><i class="fa-solid fa-circle-minus"></i></span>
            </div>
            <h4 class="fw-bold text-dark mb-0"><?php echo $activeCount; ?> Active</h4>
            <small class="text-muted"><?php echo $totalDeductionsCount; ?> total logged</small>
        </div>
    </div>

    <!-- Total Monthly Withholdings -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-ded-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">TOTAL WITHHOLDING</span>
                <span class="badge bg-danger-soft text-danger p-2 rounded-circle"><i class="fa-solid fa-money-bill-wave"></i></span>
            </div>
            <h4 class="fw-bold text-danger mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumTotal, 0); ?></h4>
            <small class="text-muted">Monthly deductions total</small>
        </div>
    </div>

    <!-- Income Tax Withholdings -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-ded-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">TAX WITHHOLDINGS</span>
                <span class="badge bg-info-soft text-info p-2 rounded-circle"><i class="fa-solid fa-building-columns"></i></span>
            </div>
            <h4 class="fw-bold text-info mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumTax, 0); ?></h4>
            <small class="text-muted">Income tax withholdings</small>
        </div>
    </div>

    <!-- Provident Fund & EOBI -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-ded-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">PF & EOBI CONTRIB.</span>
                <span class="badge bg-purple-soft text-purple p-2 rounded-circle"><i class="fa-solid fa-piggy-bank"></i></span>
            </div>
            <h4 class="fw-bold text-purple mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumPF, 0); ?></h4>
            <small class="text-muted">Retirement funds</small>
        </div>
    </div>

    <!-- Fines & Penalties -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-ded-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">FINES & PENALTIES</span>
                <span class="badge bg-warning-soft text-warning p-2 rounded-circle"><i class="fa-solid fa-gavel"></i></span>
            </div>
            <h4 class="fw-bold text-warning mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumFines, 0); ?></h4>
            <small class="text-muted">Custom cash fines & LOPs</small>
        </div>
    </div>
</div>

<!-- Search & Filtering Toolbar -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center" id="filterForm">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control bg-light border-start-0" name="search" placeholder="Search staff name, EMP ID, or reason..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select bg-light" name="type" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Deduction Types</option>
                    <option value="Income Tax" <?php echo $typeFilter === 'Income Tax' ? 'selected' : ''; ?>>Income Tax</option>
                    <option value="Provident Fund" <?php echo $typeFilter === 'Provident Fund' ? 'selected' : ''; ?>>Provident Fund</option>
                    <option value="Loan" <?php echo $typeFilter === 'Loan' ? 'selected' : ''; ?>>Loan Repayment</option>
                    <option value="Late Fine" <?php echo $typeFilter === 'Late Fine' ? 'selected' : ''; ?>>Late Fine</option>
                    <option value="Absence Deduction" <?php echo $typeFilter === 'Absence Deduction' ? 'selected' : ''; ?>>Absence Deduction</option>
                    <option value="Other" <?php echo $typeFilter === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select class="form-select bg-light" name="status" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $statusFilter === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 text-end">
                <a href="payroll_deductions.php" class="btn btn-outline-secondary w-100 rounded-pill"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Deductions Register Roster Table -->
<div class="card border-0 shadow-sm mb-5" style="border-radius:14px;">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
        <h5 class="fw-bold text-secondary mb-0">
            <i class="fa-solid fa-list-check me-2 text-danger"></i>Deductions Registry
        </h5>
        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
            Total Monthly Deductions: <strong>Rs. <?php echo number_format($sumTotal, 2); ?></strong>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Staff Member</th>
                        <th>EMP ID</th>
                        <th>Deduction Category</th>
                        <th class="text-end">Deduction Amount</th>
                        <th>Reason / Description</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deductions)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-circle-minus fs-1 text-muted mb-2 d-block opacity-50"></i>
                                No deduction rules found matching criteria. Click <strong>"Log Deduction"</strong> above to add one.
                            </td>
                        </tr>
                    <?php else: foreach ($deductions as $d): 
                        $fullName = trim($d['first_name'] . ' ' . $d['last_name']);
                        $initials = strtoupper(substr($d['first_name'], 0, 1) . substr($d['last_name'], 0, 1));
                        $bgColor  = getAvatarColor($fullName);
                        
                        $type = $d['deduction_type'];
                        $typeBadge = 'badge-soft-danger';
                        if (str_contains(strtolower($type), 'tax')) $typeBadge = 'badge-soft-info';
                        elseif (str_contains(strtolower($type), 'provident') || str_contains(strtolower($type), 'eobi')) $typeBadge = 'badge-soft-purple';
                        elseif (str_contains(strtolower($type), 'fine') || str_contains(strtolower($type), 'absence')) $typeBadge = 'badge-soft-warning';
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="staff-avatar-sm" style="background-color: <?php echo $bgColor; ?>;">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark mb-0"><?php echo sanitize($fullName); ?></div>
                                        <small class="text-muted"><?php echo sanitize($d['designation'] . ' | ' . $d['department']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><code><?php echo sanitize($d['employee_no']); ?></code></td>
                            <td>
                                <span class="badge <?php echo $typeBadge; ?> px-3 py-1.5 rounded-pill fw-semibold">
                                    <?php echo sanitize($d['deduction_type']); ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold text-danger fs-6">
                                -Rs. <?php echo number_format($d['amount'], 2); ?>
                            </td>
                            <td class="small text-muted" style="max-width:260px;">
                                <?php echo sanitize($d['reason'] ?: '—'); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $d['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill">
                                    <?php echo $d['status']; ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" onclick='editDeduction(<?php echo json_encode($d); ?>)'>
                                    <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                                </button>
                                <?php if (hasPermission('hr_manage')): ?>
                                    <button class="btn btn-sm btn-outline-danger btn-delete rounded-pill px-3" data-id="<?php echo $d['id']; ?>">
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
<div class="modal fade" id="deductionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;">
            <div class="modal-header border-0 bg-dark text-white pt-4 px-4" style="border-top-left-radius:18px; border-top-right-radius:18px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 bg-danger bg-opacity-20 rounded text-warning fs-3">
                        <i class="fa-solid fa-circle-minus"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="modalTitle">Log Staff Deduction</h5>
                        <div class="small text-white-50">Assign Tax, Fine, or Custom Cash Deduction</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="deductionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_deduction">
                    <input type="hidden" name="id" id="deductionId" value="0">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Employee <span class="text-danger">*</span></label>
                        <select class="form-select" name="staff_id" id="deductionStaffId" required>
                            <option value="">-- Choose Staff Member --</option>
                            <?php foreach ($staffList as $st): ?>
                                <option value="<?php echo $st['id']; ?>">
                                    <?php echo htmlspecialchars($st['employee_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['designation'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Deduction Category <span class="text-danger">*</span></label>
                        <select class="form-select" name="deduction_type" id="deductionType" required>
                            <option value="Income Tax">Income Tax Withholding</option>
                            <option value="Provident Fund">Provident Fund (PF)</option>
                            <option value="EOBI Contribution">EOBI Contribution</option>
                            <option value="Loan">Loan / Advance Repayment</option>
                            <option value="Late Fine">Late Fine / Attendance Penalty</option>
                            <option value="Absence Deduction">Absence / LOP Deduction</option>
                            <option value="Other">Other Custom Deduction</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Deduction Amount (Rs.) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light small">Rs.</span>
                            <input type="number" step="0.01" class="form-control fw-bold text-danger fs-5" name="amount" id="deductionAmount" required min="1" placeholder="0.00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reason / Operational Notes</label>
                        <textarea class="form-control" name="reason" id="deductionReason" rows="2" placeholder="State reason or reference ID..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Deduction Status</label>
                        <select class="form-select" name="status" id="deductionStatus">
                            <option value="Active">Active (Apply to Monthly Payroll)</option>
                            <option value="Inactive">Inactive (Hold)</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 bg-light" style="border-bottom-left-radius:18px; border-bottom-right-radius:18px;">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deductionForm" class="btn btn-danger rounded-pill px-4" id="btnSave">
                    <i class="fa-solid fa-check me-1"></i> Save Deduction
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="dedToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="dedToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
const modalObj = new bootstrap.Modal(document.getElementById("deductionModal"));

function showToast(msg, ok) {
    const t = document.getElementById("dedToast");
    const m = document.getElementById("dedToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function resetForm() {
    document.getElementById("deductionForm").reset();
    document.getElementById("deductionId").value = "0";
    document.getElementById("modalTitle").textContent = "Log Staff Deduction";
    document.getElementById("btnSave").innerHTML = `<i class="fa-solid fa-check me-1"></i> Save Deduction`;
}

function editDeduction(data) {
    resetForm();
    document.getElementById("deductionId").value = data.id;
    document.getElementById("deductionStaffId").value = data.staff_id;
    document.getElementById("deductionType").value = data.deduction_type;
    document.getElementById("deductionAmount").value = data.amount;
    document.getElementById("deductionReason").value = data.reason || "";
    document.getElementById("deductionStatus").value = data.status;
    document.getElementById("modalTitle").textContent = "Edit Staff Deduction";
    document.getElementById("btnSave").innerHTML = `<i class="fa-solid fa-check me-1"></i> Update Deduction`;
    modalObj.show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Form Save
    const form = document.getElementById("deductionForm");
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
                        btn.innerHTML = `<i class="fa-solid fa-check me-1"></i> Save Deduction`;
                    }
                })
                .catch(() => {
                    showToast("System error.", false);
                    btn.disabled = false; 
                    btn.innerHTML = `<i class="fa-solid fa-check me-1"></i> Save Deduction`;
                });
        });
    }

    // Delete Trigger
    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to remove this deduction? This will recalculate staff monthly net payable totals.")) return;
            const id = this.dataset.id;
            this.disabled = true;

            const fd = new FormData();
            fd.append("action", "delete_deduction");
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
