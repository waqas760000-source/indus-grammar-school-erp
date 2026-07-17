<?php
/**
 * Indus Grammar School ERP - Advance Salary Management
 * Version 4.0.0
 */

$pageTitle = 'Advance Salary Management';
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

// Fetch advances
$advances = $db->query("
    SELECT a.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
    FROM advance_salary a
    JOIN staff s ON a.staff_id = s.id
    ORDER BY a.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-comments-dollar me-2 text-purple"></i>Advance Salary Management</h3>
        <p class="text-muted small mb-0">Record salary cash advances disbursed to staff. The system automatically processes partial recovery in monthly runs.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-purple text-white px-4" data-bs-toggle="modal" data-bs-target="#advanceModal" onclick="resetForm()">
            <i class="fa-solid fa-plus me-2"></i>Record Advance
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
                        <th class="text-center">Advance Date</th>
                        <th class="text-end">Advance Amount</th>
                        <th class="text-center">Installments</th>
                        <th class="text-end">Installment Amount</th>
                        <th class="text-end">Paid Amount</th>
                        <th class="text-end">Remaining Balance</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($advances)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted">No advance salary records logged. Click "Record Advance" to log a disbursement.</td></tr>
                    <?php else: foreach ($advances as $a): ?>
                        <tr>
                            <td class="fw-bold text-secondary"><?php echo htmlspecialchars($a['employee_no']); ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($a['designation']); ?></small>
                            </td>
                            <td class="text-center small"><?php echo date('d-M-Y', strtotime($a['advance_date'])); ?></td>
                            <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($a['amount'], 2); ?></td>
                            <td class="text-center"><?php echo $a['installments']; ?> Months</td>
                            <td class="text-end text-muted">Rs. <?php echo number_format($a['installment_amount'], 2); ?></td>
                            <td class="text-end text-success fw-semibold">Rs. <?php echo number_format($a['paid_amount'], 2); ?></td>
                            <td class="text-end text-danger fw-bold">Rs. <?php echo number_format($a['remaining_balance'], 2); ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $a['status'] === 'Recovered' ? 'success' : 'warning text-dark'; ?>-soft px-3 py-1 rounded-pill"><?php echo $a['status']; ?></span>
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
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary" id="modalTitle"><i class="fa-solid fa-hand-holding-dollar me-2 text-purple"></i>Disburse Salary Advance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="advanceForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_advance_salary">
                    <input type="hidden" name="id" id="advanceId" value="0">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Employee *</label>
                        <select class="form-select" name="staff_id" id="advanceStaffId" required>
                            <option value="">-- Choose Staff --</option>
                            <?php foreach ($staffList as $st): ?>
                                <option value="<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['employee_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['designation'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Disbursement Date *</label>
                            <input type="date" class="form-control" name="advance_date" id="advanceDate" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Advance Amount (Rs.) *</label>
                            <input type="number" step="0.01" class="form-control" name="amount" id="advanceAmount" required min="100">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Recovery Installment Count (Months) *</label>
                        <select class="form-select" name="installments" id="advanceInstallments" required>
                            <option value="1">1 Month (Full Deduct)</option>
                            <option value="2">2 Months</option>
                            <option value="3">3 Months</option>
                            <option value="4">4 Months</option>
                            <option value="6">6 Months</option>
                            <option value="12">12 Months (1 Year)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reason for Advance *</label>
                        <textarea class="form-control" name="reason" id="advanceReason" rows="2" placeholder="e.g. Medical emergency, family expenses" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="advanceForm" class="btn btn-purple text-white px-4" id="btnSave">Disburse Advance</button>
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

<style>
.btn-purple {
    background-color: #6f42c1;
    border-color: #6f42c1;
}
.btn-purple:hover {
    background-color: #59359a;
    border-color: #59359a;
}
.bg-purple-soft {
    background-color: #f1f0ff;
    color: #6f42c1;
}
.text-purple {
    color: #6f42c1;
}
</style>

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

function resetForm() {
    document.getElementById("advanceForm").reset();
    document.getElementById("advanceId").value = "0";
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-hand-holding-dollar me-2 text-purple"></i>Disburse Salary Advance\';
    document.getElementById("btnSave").innerHTML = "Disburse Advance";
}

function editAdvance(data) {
    resetForm();
    document.getElementById("advanceId").value = data.id;
    document.getElementById("advanceStaffId").value = data.staff_id;
    document.getElementById("advanceDate").value = data.advance_date;
    document.getElementById("advanceAmount").value = data.amount;
    document.getElementById("advanceInstallments").value = data.installments;
    document.getElementById("advanceReason").value = data.reason;
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-pen-to-square me-2 text-purple"></i>Edit Salary Advance\';
    document.getElementById("btnSave").innerHTML = "Update Advance";
    modalObj.show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Form Save
    const form = document.getElementById("advanceForm");
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
                        btn.disabled = false; btn.innerHTML = document.getElementById("advanceId").value !== "0" ? "Update Advance" : "Disburse Advance";
                    }
                })
                .catch(() => {
                    showToast("System error.", false);
                    btn.disabled = false; btn.innerHTML = "Disburse Advance";
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
                    showToast("Error.", false);
                    this.disabled = false;
                });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
