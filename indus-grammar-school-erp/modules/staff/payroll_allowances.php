<?php
/**
 * Indus Grammar School ERP - Allowance Management
 * Version 4.0.0
 */

$pageTitle = 'Allowances Management';
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

// Fetch allowances list with staff details
$allowances = $db->query("
    SELECT a.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
    FROM allowances a
    JOIN staff s ON a.staff_id = s.id
    ORDER BY a.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-circle-plus me-2 text-success"></i>Allowances Management</h3>
        <p class="text-muted small mb-0">Manage special incentives, transport, and teaching allowances assigned to individual employees.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#allowanceModal" onclick="resetForm()">
            <i class="fa-solid fa-plus me-2"></i>Assign Allowance
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
                        <th>Allowance Type</th>
                        <th class="text-end">Amount</th>
                        <th>Description</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allowances)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No custom allowances logged in system. Click "Assign Allowance" to register one.</td></tr>
                    <?php else: foreach ($allowances as $a): ?>
                        <tr>
                            <td class="fw-bold text-secondary"><?php echo htmlspecialchars($a['employee_no']); ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($a['designation'] . ' | ' . $a['department']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-success-soft text-success px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($a['allowance_type']); ?></span>
                            </td>
                            <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($a['amount'], 2); ?></td>
                            <td class="small text-muted"><?php echo htmlspecialchars($a['description'] ?: '—'); ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $a['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill"><?php echo $a['status']; ?></span>
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
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary" id="modalTitle"><i class="fa-solid fa-circle-plus me-2 text-success"></i>Assign Custom Allowance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="allowanceForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_allowance">
                    <input type="hidden" name="id" id="allowanceId" value="0">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Employee *</label>
                        <select class="form-select" name="staff_id" id="allowanceStaffId" required>
                            <option value="">-- Choose Staff --</option>
                            <?php foreach ($staffList as $st): ?>
                                <option value="<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['employee_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['designation'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Allowance Type *</label>
                        <select class="form-select" name="allowance_type" id="allowanceType" required>
                            <option value="House Rent">House Rent</option>
                            <option value="Medical">Medical</option>
                            <option value="Transport">Transport</option>
                            <option value="Food">Food</option>
                            <option value="Mobile">Mobile</option>
                            <option value="Teaching Allowance">Teaching Allowance</option>
                            <option value="Performance Allowance">Performance Allowance</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Amount (Rs.) *</label>
                        <input type="number" step="0.01" class="form-control" name="amount" id="allowanceAmount" required min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description / Remarks</label>
                        <textarea class="form-control" name="description" id="allowanceDesc" rows="2" placeholder="Optional comments..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status" id="allowanceStatus">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="allowanceForm" class="btn btn-success px-4" id="btnSave">Assign Allowance</button>
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
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-circle-plus me-2 text-success"></i>Assign Custom Allowance\';
    document.getElementById("btnSave").innerHTML = "Assign Allowance";
}

function editAllowance(data) {
    resetForm();
    document.getElementById("allowanceId").value = data.id;
    document.getElementById("allowanceStaffId").value = data.staff_id;
    document.getElementById("allowanceType").value = data.allowance_type;
    document.getElementById("allowanceAmount").value = data.amount;
    document.getElementById("allowanceDesc").value = data.description;
    document.getElementById("allowanceStatus").value = data.status;
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit Custom Allowance\';
    document.getElementById("btnSave").innerHTML = "Update Allowance";
    modalObj.show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Form Save
    const form = document.getElementById("allowanceForm");
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
                        btn.disabled = false; btn.innerHTML = document.getElementById("allowanceId").value !== "0" ? "Update Allowance" : "Assign Allowance";
                    }
                })
                .catch(() => {
                    showToast("System error.", false);
                    btn.disabled = false; btn.innerHTML = "Assign Allowance";
                });
        });
    }

    // Delete Trigger
    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this allowance? This action is permanent and affects payroll totals.")) return;
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
                    showToast("Error.", false);
                    this.disabled = false;
                });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
