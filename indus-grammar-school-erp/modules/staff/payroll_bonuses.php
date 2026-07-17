<?php
/**
 * Indus Grammar School ERP - Bonus & Incentive Management
 * Version 4.0.0
 */

$pageTitle = 'Bonuses & Incentives';
$breadcrumbActive = 'Payroll';
include_once __DIR__ . '/../../includes/header.php';

// Restricted to Super Admin, School Admin, and Accountant
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'accountant'])) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permissions to access the payroll module.';
    redirect(APP_URL . '/dashboard.php');
}

$db = Database::getConnection();

// Load active staff members for dropdown
$staffList = $db->query("SELECT id, employee_no, first_name, last_name, department, designation FROM staff WHERE status = 'Active' ORDER BY employee_no ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch bonuses list with staff details
$bonuses = $db->query("
    SELECT b.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
    FROM bonuses b
    JOIN staff s ON b.staff_id = s.id
    ORDER BY b.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-gift me-2 text-warning"></i>Bonuses & Incentives</h3>
        <p class="text-muted small mb-0">Record and manage one-time employee rewards such as Eid bonuses, performance incentives, and annual bonuses.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-warning text-dark fw-semibold px-4" data-bs-toggle="modal" data-bs-target="#bonusModal" onclick="resetForm()">
            <i class="fa-solid fa-plus me-2"></i>Log Bonus/Incentive
        </button>
        <a href="payroll.php" class="btn btn-outline-secondary px-4 ms-2"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Table List -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Staff Name</th>
                        <th>Bonus Type</th>
                        <th class="text-center">Earned Date</th>
                        <th class="text-end">Amount</th>
                        <th>Description</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bonuses)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No bonuses or incentives logged. Click "Log Bonus/Incentive" to add one.</td></tr>
                    <?php else: foreach ($bonuses as $b): ?>
                        <tr>
                            <td class="fw-bold text-secondary"><?php echo htmlspecialchars($b['employee_no']); ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($b['first_name'] . ' ' . $b['last_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($b['designation'] . ' | ' . $b['department']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-warning-soft text-warning-dark px-3 py-1 rounded-pill fw-semibold" style="color: #856404; background-color: #fff3cd;"><?php echo htmlspecialchars($b['bonus_type']); ?></span>
                            </td>
                            <td class="text-center small"><?php echo date('d-M-Y', strtotime($b['date_earned'])); ?></td>
                            <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($b['amount'], 2); ?></td>
                            <td class="small text-muted"><?php echo htmlspecialchars($b['description'] ?: '—'); ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $b['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill"><?php echo $b['status']; ?></span>
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
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary" id="modalTitle"><i class="fa-solid fa-gift me-2 text-warning"></i>Assign Bonus / Incentive</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="bonusForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_bonus">
                    <input type="hidden" name="id" id="bonusId" value="0">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Employee *</label>
                        <select class="form-select" name="staff_id" id="bonusStaffId" required>
                            <option value="">-- Choose Staff --</option>
                            <?php foreach ($staffList as $st): ?>
                                <option value="<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['employee_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['designation'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Bonus Type *</label>
                        <select class="form-select" name="bonus_type" id="bonusType" required>
                            <option value="Eid Bonus">Eid Bonus</option>
                            <option value="Performance Bonus">Performance Bonus</option>
                            <option value="Annual Bonus">Annual Bonus</option>
                            <option value="Festival Bonus">Festival Bonus</option>
                            <option value="Special Incentive">Special Incentive</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Amount (Rs.) *</label>
                        <input type="number" step="0.01" class="form-control" name="amount" id="bonusAmount" required min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Date Earned *</label>
                        <input type="date" class="form-control" name="date_earned" id="bonusDateEarned" required value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description / Remarks</label>
                        <textarea class="form-control" name="description" id="bonusDesc" rows="2" placeholder="Describe the reason for this reward..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status" id="bonusStatus">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="bonusForm" class="btn btn-success px-4" id="btnSave">Save Bonus</button>
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
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-gift me-2 text-warning"></i>Assign Bonus / Incentive\';
    document.getElementById("btnSave").innerHTML = "Save Bonus";
}

function editBonus(data) {
    resetForm();
    document.getElementById("bonusId").value = data.id;
    document.getElementById("bonusStaffId").value = data.staff_id;
    document.getElementById("bonusType").value = data.bonus_type;
    document.getElementById("bonusAmount").value = data.amount;
    document.getElementById("bonusDateEarned").value = data.date_earned;
    document.getElementById("bonusDesc").value = data.description;
    document.getElementById("bonusStatus").value = data.status;
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-pen-to-square me-2 text-warning"></i>Edit Bonus / Incentive\';
    document.getElementById("btnSave").innerHTML = "Update Bonus";
    modalObj.show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Form Save
    const form = document.getElementById("bonusForm");
    if(form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSave");
            btn.disabled = true; btn.innerHTML = "Saving...";

            fetch("../../ajax/payroll.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        modalObj.hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        btn.disabled = false; btn.innerHTML = document.getElementById("bonusId").value !== "0" ? "Update Bonus" : "Save Bonus";
                    }
                })
                .catch(() => {
                    showToast("System error.", false);
                    btn.disabled = false; btn.innerHTML = "Save Bonus";
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
                    showToast("Error.", false);
                    this.disabled = false;
                });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
