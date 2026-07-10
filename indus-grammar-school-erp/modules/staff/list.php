<?php
/**
 * Indus Grammar School ERP - Staff Directory
 * Version 1.0.0
 */

$pageTitle = 'Staff Directory';
$breadcrumbActive = 'HR & Staff';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('hr_view');

// Fetch staff data
$staffList = Staff::all();
$empNo = Staff::generateEmployeeNo();
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-users-gear me-2 text-primary"></i>Staff Directory</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if (hasPermission('hr_manage')): ?>
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#staffModal" onclick="openStaffModal()">
            <i class="fa-solid fa-user-plus me-2"></i>Add Staff Member
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-0 text-secondary">Registered Staff</h5>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Emp. No</th>
                    <th>Name</th>
                    <th>Designation</th>
                    <th>Department</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <?php if (hasPermission('hr_manage')): ?>
                    <th class="text-end">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staffList)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No staff members found.</td></tr>
                <?php else: foreach ($staffList as $s): ?>
                    <tr>
                        <td><code class="text-muted"><?php echo sanitize($s['employee_no']); ?></code></td>
                        <td class="fw-semibold text-dark">
                            <?php echo sanitize($s['first_name'] . ' ' . $s['last_name']); ?>
                            <?php if ($s['user_id']): ?>
                                <br><small class="text-primary"><i class="fa-solid fa-user-shield me-1"></i>System User</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo sanitize($s['designation']); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo sanitize($s['department']); ?></span></td>
                        <td><?php echo sanitize($s['phone']); ?></td>
                        <td>
                            <?php 
                                $bg = ['Active' => 'success', 'Inactive' => 'warning', 'Terminated' => 'danger'];
                                $c = $bg[$s['status']] ?? 'secondary';
                            ?>
                            <span class="badge badge-soft-<?php echo $c; ?> px-3 py-2 rounded-pill"><?php echo sanitize($s['status']); ?></span>
                        </td>
                        <?php if (hasPermission('hr_manage')): ?>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" title="Edit" 
                                onclick="openStaffModal(<?php echo htmlspecialchars(json_encode($s)); ?>)">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Staff Modal -->
<?php if (hasPermission('hr_manage')): ?>
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="modalTitle"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Add Staff Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="staffForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_staff">
                    <input type="hidden" name="staff_id" id="staffId" value="0">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">First Name</label>
                            <input type="text" class="form-control" name="first_name" id="f_first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Last Name</label>
                            <input type="text" class="form-control" name="last_name" id="f_last_name" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Employee No</label>
                            <input type="text" class="form-control bg-light" name="employee_no" id="f_employee_no" value="<?php echo $empNo; ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Date of Joining</label>
                            <input type="date" class="form-control" name="date_of_joining" id="f_date_of_joining" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Designation</label>
                            <input type="text" class="form-control" name="designation" id="f_designation" required placeholder="e.g. Senior Teacher">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Department</label>
                            <select class="form-select" name="department" id="f_department" required>
                                <option value="Academic">Academic</option>
                                <option value="Administration">Administration</option>
                                <option value="Accounts">Accounts</option>
                                <option value="Support Staff">Support Staff</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone Number</label>
                            <input type="text" class="form-control" name="phone" id="f_phone" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address (Optional)</label>
                            <input type="email" class="form-control" name="email" id="f_email">
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Basic Salary (Rs.)</label>
                            <input type="number" class="form-control fw-bold" name="salary" id="f_salary" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Status</label>
                            <select class="form-select" name="status" id="f_status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Terminated">Terminated</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Address (Optional)</label>
                        <textarea class="form-control" name="address" id="f_address" rows="2"></textarea>
                    </div>

                    <!-- Connect to System User -->
                    <div class="card border border-primary-subtle bg-primary-soft shadow-sm mt-4">
                        <div class="card-body p-3">
                            <label class="form-label small fw-bold text-primary mb-1"><i class="fa-solid fa-link me-2"></i>Link to System User (Optional)</label>
                            <p class="small text-muted mb-2">If this staff member needs to login to the system, enter their User ID.</p>
                            <input type="number" class="form-control form-control-sm" name="user_id" id="f_user_id" placeholder="System User ID">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="staffForm" class="btn btn-primary px-4" id="btnSave">Save Staff</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="stToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="stToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("stToast");
    const m = document.getElementById("stToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

const defaultEmpNo = "' . $empNo . '";
const defaultDate = "' . date('Y-m-d') . '";

function openStaffModal(data = null) {
    if (data) {
        document.getElementById("modalTitle").innerHTML = `<i class="fa-solid fa-pen me-2 text-primary"></i>Edit Staff`;
        document.getElementById("staffId").value = data.id;
        document.getElementById("f_first_name").value = data.first_name;
        document.getElementById("f_last_name").value = data.last_name;
        document.getElementById("f_employee_no").value = data.employee_no;
        document.getElementById("f_date_of_joining").value = data.date_of_joining;
        document.getElementById("f_designation").value = data.designation;
        document.getElementById("f_department").value = data.department;
        document.getElementById("f_phone").value = data.phone;
        document.getElementById("f_email").value = data.email;
        document.getElementById("f_salary").value = data.salary;
        document.getElementById("f_status").value = data.status;
        document.getElementById("f_address").value = data.address;
        document.getElementById("f_user_id").value = data.user_id || "";
    } else {
        document.getElementById("modalTitle").innerHTML = `<i class="fa-solid fa-user-plus me-2 text-primary"></i>Add Staff Member`;
        document.getElementById("staffForm").reset();
        document.getElementById("staffId").value = "0";
        document.getElementById("f_employee_no").value = defaultEmpNo;
        document.getElementById("f_date_of_joining").value = defaultDate;
    }
    new bootstrap.Modal(document.getElementById("staffModal")).show();
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("staffForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSave");
            btn.disabled = true; btn.innerHTML = "Saving...";
            
            fetch("../../ajax/staff.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Save Staff"; }
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
