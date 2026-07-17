<?php
/**
 * Indus Grammar School ERP - Deductions Management
 * Version 4.0.0
 */

$pageTitle = 'Deductions Management';
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

// Fetch deductions list with staff details
$deductions = $db->query("
    SELECT d.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
    FROM deductions d
    JOIN staff s ON d.staff_id = s.id
    ORDER BY d.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-circle-minus me-2 text-danger"></i>Deductions Management</h3>
        <p class="text-muted small mb-0">Configure structural tax withholdings, custom cash deductions, or penalty fines applied to staff.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-danger px-4" data-bs-toggle="modal" data-bs-target="#deductionModal" onclick="resetForm()">
            <i class="fa-solid fa-plus me-2"></i>Assign Deduction
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
                        <th>Deduction Type</th>
                        <th class="text-end">Amount</th>
                        <th>Reason / Notes</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deductions)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No custom deductions assigned in system. Click "Assign Deduction" to register one.</td></tr>
                    <?php else: foreach ($deductions as $d): ?>
                        <tr>
                            <td class="fw-bold text-secondary"><?php echo htmlspecialchars($d['employee_no']); ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($d['first_name'] . ' ' . $d['last_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($d['designation'] . ' | ' . $d['department']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-danger-soft text-danger px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($d['deduction_type']); ?></span>
                            </td>
                            <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($d['amount'], 2); ?></td>
                            <td class="small text-muted"><?php echo htmlspecialchars($d['reason'] ?: '—'); ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $d['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill"><?php echo $d['status']; ?></span>
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
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary" id="modalTitle"><i class="fa-solid fa-circle-minus me-2 text-danger"></i>Assign Custom Deduction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="deductionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_deduction">
                    <input type="hidden" name="id" id="deductionId" value="0">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Employee *</label>
                        <select class="form-select" name="staff_id" id="deductionStaffId" required>
                            <option value="">-- Choose Staff --</option>
                            <?php foreach ($staffList as $st): ?>
                                <option value="<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['employee_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['designation'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Deduction Type *</label>
                        <select class="form-select" name="deduction_type" id="deductionType" required>
                            <option value="Income Tax">Income Tax</option>
                            <option value="Provident Fund">Provident Fund</option>
                            <option value="Loan">Loan</option>
                            <option value="Late Fine">Late Fine</option>
                            <option value="Absence Deduction">Absence Deduction</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Amount (Rs.) *</label>
                        <input type="number" step="0.01" class="form-control" name="amount" id="deductionAmount" required min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reason / Description</label>
                        <textarea class="form-control" name="reason" id="deductionReason" rows="2" placeholder="Optional comments..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status" id="deductionStatus">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deductionForm" class="btn btn-danger px-4" id="btnSave">Assign Deduction</button>
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
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-circle-minus me-2 text-danger"></i>Assign Custom Deduction\';
    document.getElementById("btnSave").innerHTML = "Assign Deduction";
}

function editDeduction(data) {
    resetForm();
    document.getElementById("deductionId").value = data.id;
    document.getElementById("deductionStaffId").value = data.staff_id;
    document.getElementById("deductionType").value = data.deduction_type;
    document.getElementById("deductionAmount").value = data.amount;
    document.getElementById("deductionReason").value = data.reason;
    document.getElementById("deductionStatus").value = data.status;
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit Custom Deduction\';
    document.getElementById("btnSave").innerHTML = "Update Deduction";
    modalObj.show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Form Save
    const form = document.getElementById("deductionForm");
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
                        btn.disabled = false; btn.innerHTML = document.getElementById("deductionId").value !== "0" ? "Update Deduction" : "Assign Deduction";
                    }
                })
                .catch(() => {
                    showToast("System error.", false);
                    btn.disabled = false; btn.innerHTML = "Assign Deduction";
                });
        });
    }

    // Delete Trigger
    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this deduction record? This changes payroll net payable totals.")) return;
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
                    showToast("Error.", false);
                    this.disabled = false;
                });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
