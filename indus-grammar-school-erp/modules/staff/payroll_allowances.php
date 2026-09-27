<?php
/**
 * Indus Grammar School ERP - Allowances & Incentives Management
 * Version 4.0.0 (Premium Redesign)
 */

$pageTitle = 'Allowances Management';
$breadcrumbActive = 'Payroll';
include_once __DIR__ . '/../../includes/header.php';

// Access Control
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'accountant'])) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to access the payroll allowances module.';
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
    $where .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.employee_no LIKE :search OR a.description LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if (!empty($typeFilter)) {
    $where .= " AND a.allowance_type = :type";
    $params['type'] = $typeFilter;
}
if (!empty($statusFilter)) {
    $where .= " AND a.status = :status";
    $params['status'] = $statusFilter;
}

// Load active staff members for modal dropdown
$staffList = $db->query("SELECT id, employee_no, first_name, last_name, department, designation FROM staff WHERE status = 'Active' ORDER BY employee_no ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch allowances list with staff details
$allowances = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
        FROM allowances a
        JOIN staff s ON a.staff_id = s.id
        $where
        ORDER BY a.id DESC
    ");
    $stmt->execute($params);
    $allowances = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading allowances: " . $e->getMessage());
}

// Calculate Executive Allowances Metrics
$totalAllowancesCount = count($allowances);
$activeCount = 0;
$sumTotal = 0.0;
$sumTeaching = 0.0;
$sumTransport = 0.0;
$sumHousingMedical = 0.0;

foreach ($allowances as $a) {
    if ($a['status'] === 'Active') {
        $activeCount++;
        $amt = (float)$a['amount'];
        $sumTotal += $amt;
        
        $t = strtolower($a['allowance_type']);
        if (str_contains($t, 'teach') || str_contains($t, 'perform')) $sumTeaching += $amt;
        elseif (str_contains($t, 'trans') || str_contains($t, 'convey') || str_contains($t, 'fuel')) $sumTransport += $amt;
        else $sumHousingMedical += $amt;
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
.allow-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.5rem 1.75rem;
    position: relative;
    overflow: hidden;
}
.allow-hero-card::after {
    content: "";
    position: absolute;
    top: -50%;
    right: -10%;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.kpi-allow-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.15rem;
    transition: all 0.25s ease;
}
.kpi-allow-card:hover {
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

.badge-soft-success { background-color: #dcfce7; color: #15803d; }
.badge-soft-info    { background-color: #e0f2fe; color: #0369a1; }
.badge-soft-purple  { background-color: #f3e8ff; color: #6b21a8; }
.badge-soft-warning { background-color: #fef9c3; color: #a16207; }
</style>

<!-- Hero Title Header -->
<div class="allow-hero-card shadow-sm mb-4">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-success bg-opacity-20 rounded-3 text-warning">
                    <i class="fa-solid fa-circle-plus fs-2"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-1">Allowances & Incentives Management</h3>
                    <p class="text-white-50 mb-0 small">
                        Manage structural incentives, transport allowances, teaching bonuses, medical benefits, and performance perks assigned to staff.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <a href="payroll.php" class="btn btn-outline-light btn-sm px-3 rounded-pill me-1">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
            </a>
            <?php if (hasPermission('hr_manage')): ?>
            <button class="btn btn-success btn-sm px-3 rounded-pill fw-semibold" data-bs-toggle="modal" data-bs-target="#allowanceModal" onclick="resetForm()">
                <i class="fa-solid fa-plus me-1"></i> Assign Allowance
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 5 Allowances Executive Micro-Cards -->
<div class="row g-3 mb-4">
    <!-- Active Allowances Count -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-allow-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">ACTIVE ALLOWANCES</span>
                <span class="badge bg-success-soft text-success p-2 rounded-circle"><i class="fa-solid fa-circle-plus"></i></span>
            </div>
            <h4 class="fw-bold text-dark mb-0"><?php echo $activeCount; ?> Active</h4>
            <small class="text-muted"><?php echo $totalAllowancesCount; ?> total assigned</small>
        </div>
    </div>

    <!-- Total Monthly Allowances -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-allow-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">TOTAL ALLOWANCE SUM</span>
                <span class="badge bg-success-soft text-success p-2 rounded-circle"><i class="fa-solid fa-money-bill-wave"></i></span>
            </div>
            <h4 class="fw-bold text-success mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumTotal, 0); ?></h4>
            <small class="text-muted">Monthly additions total</small>
        </div>
    </div>

    <!-- Teaching & Faculty Incentives -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-allow-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">FACULTY INCENTIVES</span>
                <span class="badge bg-info-soft text-info p-2 rounded-circle"><i class="fa-solid fa-chalkboard-user"></i></span>
            </div>
            <h4 class="fw-bold text-info mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumTeaching, 0); ?></h4>
            <small class="text-muted">Teaching & performance perks</small>
        </div>
    </div>

    <!-- Transport & Fuel -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-allow-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">TRANSPORT & CONVEYANCE</span>
                <span class="badge bg-warning-soft text-warning p-2 rounded-circle"><i class="fa-solid fa-car"></i></span>
            </div>
            <h4 class="fw-bold text-warning mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumTransport, 0); ?></h4>
            <small class="text-muted">Travel & conveyance</small>
        </div>
    </div>

    <!-- Housing & Medical Benefits -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-allow-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">HOUSING & MEDICAL</span>
                <span class="badge bg-purple-soft text-purple p-2 rounded-circle"><i class="fa-solid fa-house-medical"></i></span>
            </div>
            <h4 class="fw-bold text-purple mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumHousingMedical, 0); ?></h4>
            <small class="text-muted">HRA & medical coverage</small>
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
                    <input type="text" class="form-control bg-light border-start-0" name="search" placeholder="Search staff name, EMP ID, or description..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select bg-light" name="type" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Allowance Types</option>
                    <option value="Teaching Allowance" <?php echo $typeFilter === 'Teaching Allowance' ? 'selected' : ''; ?>>Teaching Allowance</option>
                    <option value="Performance Allowance" <?php echo $typeFilter === 'Performance Allowance' ? 'selected' : ''; ?>>Performance Allowance</option>
                    <option value="House Rent" <?php echo $typeFilter === 'House Rent' ? 'selected' : ''; ?>>House Rent (HRA)</option>
                    <option value="Medical" <?php echo $typeFilter === 'Medical' ? 'selected' : ''; ?>>Medical Allowance</option>
                    <option value="Transport" <?php echo $typeFilter === 'Transport' ? 'selected' : ''; ?>>Transport / Conveyance</option>
                    <option value="Food" <?php echo $typeFilter === 'Food' ? 'selected' : ''; ?>>Food Allowance</option>
                    <option value="Mobile" <?php echo $typeFilter === 'Mobile' ? 'selected' : ''; ?>>Mobile Allowance</option>
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
                <a href="payroll_allowances.php" class="btn btn-outline-secondary w-100 rounded-pill"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Allowances Register Roster Table -->
<div class="card border-0 shadow-sm mb-5" style="border-radius:14px;">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
        <h5 class="fw-bold text-secondary mb-0">
            <i class="fa-solid fa-list-check me-2 text-success"></i>Allowances Registry
        </h5>
        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
            Total Active Benefits: <strong class="text-success">+Rs. <?php echo number_format($sumTotal, 2); ?></strong>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Staff Member</th>
                        <th>EMP ID</th>
                        <th>Allowance Category</th>
                        <th class="text-end">Allowance Amount</th>
                        <th>Description / Notes</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allowances)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-circle-plus fs-1 text-muted mb-2 d-block opacity-50"></i>
                                No allowance incentives found matching criteria. Click <strong>"Assign Allowance"</strong> above to log one.
                            </td>
                        </tr>
                    <?php else: foreach ($allowances as $a): 
                        $fullName = trim($a['first_name'] . ' ' . $a['last_name']);
                        $initials = strtoupper(substr($a['first_name'], 0, 1) . substr($a['last_name'], 0, 1));
                        $bgColor  = getAvatarColor($fullName);
                        
                        $type = $a['allowance_type'];
                        $typeBadge = 'badge-soft-success';
                        if (str_contains(strtolower($type), 'teach') || str_contains(strtolower($type), 'perform')) $typeBadge = 'badge-soft-success';
                        elseif (str_contains(strtolower($type), 'house') || str_contains(strtolower($type), 'medical')) $typeBadge = 'badge-soft-info';
                        elseif (str_contains(strtolower($type), 'trans') || str_contains(strtolower($type), 'food') || str_contains(strtolower($type), 'mobile')) $typeBadge = 'badge-soft-warning';
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
                            <td>
                                <span class="badge <?php echo $typeBadge; ?> px-3 py-1.5 rounded-pill fw-semibold">
                                    <?php echo sanitize($a['allowance_type']); ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold text-success fs-6">
                                +Rs. <?php echo number_format($a['amount'], 2); ?>
                            </td>
                            <td class="small text-muted" style="max-width:260px;">
                                <?php echo sanitize($a['description'] ?: '—'); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $a['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill">
                                    <?php echo $a['status']; ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" onclick='editAllowance(<?php echo json_encode($a); ?>)'>
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
<div class="modal fade" id="allowanceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;">
            <div class="modal-header border-0 bg-dark text-white pt-4 px-4" style="border-top-left-radius:18px; border-top-right-radius:18px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 bg-success bg-opacity-20 rounded text-warning fs-3">
                        <i class="fa-solid fa-circle-plus"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="modalTitle">Assign Staff Allowance</h5>
                        <div class="small text-white-50">Assign Teaching Perk, Transport or Special Benefit</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="allowanceForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_allowance">
                    <input type="hidden" name="id" id="allowanceId" value="0">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Employee <span class="text-danger">*</span></label>
                        <select class="form-select" name="staff_id" id="allowanceStaffId" required>
                            <option value="">-- Choose Staff Member --</option>
                            <?php foreach ($staffList as $st): ?>
                                <option value="<?php echo $st['id']; ?>">
                                    <?php echo htmlspecialchars($st['employee_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['designation'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Allowance Category <span class="text-danger">*</span></label>
                        <select class="form-select" name="allowance_type" id="allowanceType" required>
                            <option value="Teaching Allowance">Teaching Allowance / Faculty Perk</option>
                            <option value="Performance Allowance">Performance / Honorarium Bonus</option>
                            <option value="House Rent">House Rent Allowance (HRA)</option>
                            <option value="Medical">Medical Allowance</option>
                            <option value="Transport">Transport / Conveyance</option>
                            <option value="Food">Food / Mess Allowance</option>
                            <option value="Mobile">Mobile & Utility Allowance</option>
                            <option value="Other">Other Custom Allowance</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Allowance Amount (Rs.) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light small">Rs.</span>
                            <input type="number" step="0.01" class="form-control fw-bold text-success fs-5" name="amount" id="allowanceAmount" required min="1" placeholder="0.00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description / Remarks</label>
                        <textarea class="form-control" name="description" id="allowanceDesc" rows="2" placeholder="Optional comments..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Allowance Status</label>
                        <select class="form-select" name="status" id="allowanceStatus">
                            <option value="Active">Active (Add to Monthly Payroll)</option>
                            <option value="Inactive">Inactive (Hold)</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 bg-light" style="border-bottom-left-radius:18px; border-bottom-right-radius:18px;">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="allowanceForm" class="btn btn-success rounded-pill px-4" id="btnSave">
                    <i class="fa-solid fa-check me-1"></i> Save Allowance
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="allowToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="allowToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
const modalObj = new bootstrap.Modal(document.getElementById("allowanceModal"));

function showToast(msg, ok) {
    const t = document.getElementById("allowToast");
    const m = document.getElementById("allowToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function resetForm() {
    document.getElementById("allowanceForm").reset();
    document.getElementById("allowanceId").value = "0";
    document.getElementById("modalTitle").textContent = "Assign Staff Allowance";
    document.getElementById("btnSave").innerHTML = `<i class="fa-solid fa-check me-1"></i> Save Allowance`;
}

function editAllowance(data) {
    resetForm();
    document.getElementById("allowanceId").value = data.id;
    document.getElementById("allowanceStaffId").value = data.staff_id;
    document.getElementById("allowanceType").value = data.allowance_type;
    document.getElementById("allowanceAmount").value = data.amount;
    document.getElementById("allowanceDesc").value = data.description || "";
    document.getElementById("allowanceStatus").value = data.status;
    document.getElementById("modalTitle").textContent = "Edit Staff Allowance";
    document.getElementById("btnSave").innerHTML = `<i class="fa-solid fa-check me-1"></i> Update Allowance`;
    modalObj.show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Form Save
    const form = document.getElementById("allowanceForm");
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
                        btn.innerHTML = `<i class="fa-solid fa-check me-1"></i> Save Allowance`;
                    }
                })
                .catch(() => {
                    showToast("System error.", false);
                    btn.disabled = false; 
                    btn.innerHTML = `<i class="fa-solid fa-check me-1"></i> Save Allowance`;
                });
        });
    }

    // Delete Trigger
    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to remove this allowance? This action is permanent and affects payroll totals.")) return;
            const id = this.dataset.id;
            this.disabled = true;

            const fd = new FormData();
            fd.append("action", "delete_allowance");
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
