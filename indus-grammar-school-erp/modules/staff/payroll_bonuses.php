<?php
/**
 * Indus Grammar School ERP - Bonuses & Rewards Management
 * Version 4.0.0 (Premium Redesign)
 */

$pageTitle = 'Bonuses & Incentives';
$breadcrumbActive = 'Payroll';
include_once __DIR__ . '/../../includes/header.php';

// Access Control
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'accountant'])) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to access the payroll bonuses module.';
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
    $where .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.employee_no LIKE :search OR b.description LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if (!empty($typeFilter)) {
    $where .= " AND b.bonus_type = :type";
    $params['type'] = $typeFilter;
}
if (!empty($statusFilter)) {
    $where .= " AND b.status = :status";
    $params['status'] = $statusFilter;
}

// Load active staff members for modal dropdown
$staffList = $db->query("SELECT id, employee_no, first_name, last_name, department, designation FROM staff WHERE status = 'Active' ORDER BY employee_no ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch bonuses list with staff details
$bonuses = [];
try {
    $stmt = $db->prepare("
        SELECT b.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
        FROM bonuses b
        JOIN staff s ON b.staff_id = s.id
        $where
        ORDER BY b.id DESC
    ");
    $stmt->execute($params);
    $bonuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading bonuses: " . $e->getMessage());
}

// Calculate Executive Bonus Metrics
$totalBonusCount = count($bonuses);
$activeCount = 0;
$sumTotal = 0.0;
$sumEidFestival = 0.0;
$sumPerformance = 0.0;
$sumAnnualSpecial = 0.0;

foreach ($bonuses as $b) {
    if ($b['status'] === 'Active') {
        $activeCount++;
        $amt = (float)$b['amount'];
        $sumTotal += $amt;
        
        $t = strtolower($b['bonus_type']);
        if (str_contains($t, 'eid') || str_contains($t, 'festiv')) $sumEidFestival += $amt;
        elseif (str_contains($t, 'perform') || str_contains($t, 'merit')) $sumPerformance += $amt;
        else $sumAnnualSpecial += $amt;
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
.bonus-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.5rem 1.75rem;
    position: relative;
    overflow: hidden;
}
.bonus-hero-card::after {
    content: "";
    position: absolute;
    top: -50%;
    right: -10%;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(245, 158, 11, 0.18) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.kpi-bonus-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.15rem;
    transition: all 0.25s ease;
}
.kpi-bonus-card:hover {
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

.badge-soft-warning { background-color: #fef9c3; color: #a16207; }
.badge-soft-success { background-color: #dcfce7; color: #15803d; }
.badge-soft-info    { background-color: #e0f2fe; color: #0369a1; }
.badge-soft-purple  { background-color: #f3e8ff; color: #6b21a8; }
</style>

<!-- Hero Title Header -->
<div class="bonus-hero-card shadow-sm mb-4">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-warning bg-opacity-20 rounded-3 text-warning">
                    <i class="fa-solid fa-gift fs-2"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-1">Bonuses & Performance Rewards</h3>
                    <p class="text-white-50 mb-0 small">
                        Record and manage one-time employee rewards such as Eid bonuses, performance incentives, annual gifts, and merit rewards.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <a href="payroll.php" class="btn btn-outline-light btn-sm px-3 rounded-pill me-1">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
            </a>
            <?php if (hasPermission('hr_manage')): ?>
            <button class="btn btn-warning btn-sm px-3 rounded-pill fw-semibold text-dark" data-bs-toggle="modal" data-bs-target="#bonusModal" onclick="resetForm()">
                <i class="fa-solid fa-plus me-1"></i> Log Bonus / Incentive
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 5 Bonus Executive Micro-Cards -->
<div class="row g-3 mb-4">
    <!-- Active Rewards Count -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-bonus-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">ACTIVE REWARDS</span>
                <span class="badge bg-warning-soft text-warning p-2 rounded-circle"><i class="fa-solid fa-gift"></i></span>
            </div>
            <h4 class="fw-bold text-dark mb-0"><?php echo $activeCount; ?> Active</h4>
            <small class="text-muted"><?php echo $totalBonusCount; ?> total logged</small>
        </div>
    </div>

    <!-- Total Bonus Amount -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-bonus-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">TOTAL REWARD SUM</span>
                <span class="badge bg-success-soft text-success p-2 rounded-circle"><i class="fa-solid fa-money-bill-wave"></i></span>
            </div>
            <h4 class="fw-bold text-success mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumTotal, 0); ?></h4>
            <small class="text-muted">Total bonus incentives</small>
        </div>
    </div>

    <!-- Eid & Festival Bonuses -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-bonus-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">EID & FESTIVALS</span>
                <span class="badge bg-warning-soft text-warning p-2 rounded-circle"><i class="fa-solid fa-moon"></i></span>
            </div>
            <h4 class="fw-bold text-warning mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumEidFestival, 0); ?></h4>
            <small class="text-muted">Eid & festival rewards</small>
        </div>
    </div>

    <!-- Performance & Merit -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-bonus-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">PERFORMANCE REWARDS</span>
                <span class="badge bg-success-soft text-success p-2 rounded-circle"><i class="fa-solid fa-trophy"></i></span>
            </div>
            <h4 class="fw-bold text-success mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumPerformance, 0); ?></h4>
            <small class="text-muted">Merit & excellence perks</small>
        </div>
    </div>

    <!-- Annual & Special Incentives -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-bonus-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">ANNUAL & SPECIAL</span>
                <span class="badge bg-purple-soft text-purple p-2 rounded-circle"><i class="fa-solid fa-star"></i></span>
            </div>
            <h4 class="fw-bold text-purple mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumAnnualSpecial, 0); ?></h4>
            <small class="text-muted">Annual & special gifts</small>
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
                    <option value="">All Bonus Types</option>
                    <option value="Eid Bonus" <?php echo $typeFilter === 'Eid Bonus' ? 'selected' : ''; ?>>Eid Bonus</option>
                    <option value="Performance Bonus" <?php echo $typeFilter === 'Performance Bonus' ? 'selected' : ''; ?>>Performance Bonus</option>
                    <option value="Annual Bonus" <?php echo $typeFilter === 'Annual Bonus' ? 'selected' : ''; ?>>Annual Bonus</option>
                    <option value="Festival Bonus" <?php echo $typeFilter === 'Festival Bonus' ? 'selected' : ''; ?>>Festival Bonus</option>
                    <option value="Special Incentive" <?php echo $typeFilter === 'Special Incentive' ? 'selected' : ''; ?>>Special Incentive</option>
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
                <a href="payroll_bonuses.php" class="btn btn-outline-secondary w-100 rounded-pill"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Bonuses Register Roster Table -->
<div class="card border-0 shadow-sm mb-5" style="border-radius:14px;">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
        <h5 class="fw-bold text-secondary mb-0">
            <i class="fa-solid fa-receipt me-2 text-warning"></i>Bonuses & Incentives Register
        </h5>
        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
            Total Incentives Disbursed: <strong class="text-success">+Rs. <?php echo number_format($sumTotal, 2); ?></strong>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Staff Member</th>
                        <th>EMP ID</th>
                        <th>Reward Type</th>
                        <th class="text-center">Date Earned</th>
                        <th class="text-end">Bonus Amount</th>
                        <th>Description / Remarks</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bonuses)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-gift fs-1 text-muted mb-2 d-block opacity-50"></i>
                                No bonus rewards logged matching criteria. Click <strong>"Log Bonus / Incentive"</strong> above to record one.
                            </td>
                        </tr>
                    <?php else: foreach ($bonuses as $b): 
                        $fullName = trim($b['first_name'] . ' ' . $b['last_name']);
                        $initials = strtoupper(substr($b['first_name'], 0, 1) . substr($b['last_name'], 0, 1));
                        $bgColor  = getAvatarColor($fullName);
                        
                        $type = $b['bonus_type'];
                        $typeBadge = 'badge-soft-warning';
                        if (str_contains(strtolower($type), 'perform')) $typeBadge = 'badge-soft-success';
                        elseif (str_contains(strtolower($type), 'annual') || str_contains(strtolower($type), 'special')) $typeBadge = 'badge-soft-purple';
                        elseif (str_contains(strtolower($type), 'eid') || str_contains(strtolower($type), 'festiv')) $typeBadge = 'badge-soft-warning';
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="staff-avatar-sm" style="background-color: <?php echo $bgColor; ?>;">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark mb-0"><?php echo sanitize($fullName); ?></div>
                                        <small class="text-muted"><?php echo sanitize($b['designation'] . ' | ' . $b['department']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><code><?php echo sanitize($b['employee_no']); ?></code></td>
                            <td>
                                <span class="badge <?php echo $typeBadge; ?> px-3 py-1.5 rounded-pill fw-semibold">
                                    <i class="fa-solid fa-gift me-1"></i><?php echo sanitize($b['bonus_type']); ?>
                                </span>
                            </td>
                            <td class="text-center small fw-semibold">
                                <?php echo date('d M Y', strtotime($b['date_earned'])); ?>
                            </td>
                            <td class="text-end fw-bold text-success fs-6">
                                +Rs. <?php echo number_format($b['amount'], 2); ?>
                            </td>
                            <td class="small text-muted" style="max-width:260px;">
                                <?php echo sanitize($b['description'] ?: '—'); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $b['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill">
                                    <?php echo $b['status']; ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" onclick='editBonus(<?php echo json_encode($b); ?>)'>
                                    <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                                </button>
                                <?php if (hasPermission('hr_manage')): ?>
                                    <button class="btn btn-sm btn-outline-danger btn-delete rounded-pill px-3" data-id="<?php echo $b['id']; ?>">
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
<div class="modal fade" id="bonusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;">
            <div class="modal-header border-0 bg-dark text-white pt-4 px-4" style="border-top-left-radius:18px; border-top-right-radius:18px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 bg-warning bg-opacity-20 rounded text-warning fs-3">
                        <i class="fa-solid fa-gift"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="modalTitle">Assign Bonus / Reward</h5>
                        <div class="small text-white-50">Log Eid Bonus, Performance Incentive, or Special Perk</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="bonusForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_bonus">
                    <input type="hidden" name="id" id="bonusId" value="0">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Employee <span class="text-danger">*</span></label>
                        <select class="form-select" name="staff_id" id="bonusStaffId" required>
                            <option value="">-- Choose Staff Member --</option>
                            <?php foreach ($staffList as $st): ?>
                                <option value="<?php echo $st['id']; ?>">
                                    <?php echo htmlspecialchars($st['employee_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['designation'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reward Category <span class="text-danger">*</span></label>
                        <select class="form-select" name="bonus_type" id="bonusType" required>
                            <option value="Eid Bonus">Eid Bonus</option>
                            <option value="Performance Bonus">Performance / Merit Bonus</option>
                            <option value="Annual Bonus">Annual Bonus</option>
                            <option value="Festival Bonus">Festival Bonus</option>
                            <option value="Special Incentive">Special Incentive</option>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Reward Amount (Rs.) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light small">Rs.</span>
                                <input type="number" step="0.01" class="form-control fw-bold text-success fs-5" name="amount" id="bonusAmount" required min="1" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Date Earned <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_earned" id="bonusDateEarned" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description / Remarks</label>
                        <textarea class="form-control" name="description" id="bonusDesc" rows="2" placeholder="State reason or event details..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reward Status</label>
                        <select class="form-select" name="status" id="bonusStatus">
                            <option value="Active">Active (Include in Monthly Payroll)</option>
                            <option value="Inactive">Inactive (Hold)</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 bg-light" style="border-bottom-left-radius:18px; border-bottom-right-radius:18px;">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="bonusForm" class="btn btn-warning rounded-pill px-4 text-dark fw-semibold" id="btnSave">
                    <i class="fa-solid fa-check me-1"></i> Save Bonus
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="bonusToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="bonusToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
const modalObj = new bootstrap.Modal(document.getElementById("bonusModal"));

function showToast(msg, ok) {
    const t = document.getElementById("bonusToast");
    const m = document.getElementById("bonusToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function resetForm() {
    document.getElementById("bonusForm").reset();
    document.getElementById("bonusId").value = "0";
    document.getElementById("modalTitle").textContent = "Assign Bonus / Reward";
    document.getElementById("btnSave").innerHTML = `<i class="fa-solid fa-check me-1"></i> Save Bonus`;
}

function editBonus(data) {
    resetForm();
    document.getElementById("bonusId").value = data.id;
    document.getElementById("bonusStaffId").value = data.staff_id;
    document.getElementById("bonusType").value = data.bonus_type;
    document.getElementById("bonusAmount").value = data.amount;
    document.getElementById("bonusDateEarned").value = data.date_earned;
    document.getElementById("bonusDesc").value = data.description || "";
    document.getElementById("bonusStatus").value = data.status;
    document.getElementById("modalTitle").textContent = "Edit Bonus / Reward";
    document.getElementById("btnSave").innerHTML = `<i class="fa-solid fa-check me-1"></i> Update Bonus`;
    modalObj.show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Form Save
    const form = document.getElementById("bonusForm");
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
                        btn.innerHTML = `<i class="fa-solid fa-check me-1"></i> Save Bonus`;
                    }
                })
                .catch(() => {
                    showToast("System error.", false);
                    btn.disabled = false; 
                    btn.innerHTML = `<i class="fa-solid fa-check me-1"></i> Save Bonus`;
                });
        });
    }

    // Delete Trigger
    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this bonus ledger? This action cannot be undone.")) return;
            const id = this.dataset.id;
            this.disabled = true;

            const fd = new FormData();
            fd.append("action", "delete_bonus");
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
