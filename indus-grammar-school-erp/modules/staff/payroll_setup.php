<?php
/**
 * Indus Grammar School ERP - Salary Structure Assignments
 * Version 4.0.0
 */

$pageTitle = 'Salary Setup';
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

// Search and filter parameters
$search = sanitize($_GET['search'] ?? '');
$dept   = sanitize($_GET['department'] ?? '');

$where = " WHERE s.status = 'Active'";
$params = [];
if (!empty($search)) {
    $where .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.employee_no LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if (!empty($dept)) {
    $where .= " AND s.department = :dept";
    $params['dept'] = $dept;
}

// Load staff list with setup configurations
$staff = $db->prepare("
    SELECT s.id, s.employee_no, s.first_name, s.last_name, s.designation, s.department,
           ss.basic_salary, ss.hra, ss.medical_allowance, ss.transport_allowance, ss.other_allowances,
           ss.provident_fund, ss.tax_deduction, ss.eobi, ss.other_deductions,
           ss.payment_method, ss.bank_name, ss.account_number, ss.status as setup_status
    FROM staff s
    LEFT JOIN salary_setup ss ON s.id = ss.staff_id
    $where
    ORDER BY s.employee_no ASC
");
$staff->execute($params);
$staffList = $staff->fetchAll(PDO::FETCH_ASSOC);

// Fetch departments for filter
$departments = $db->query("SELECT DISTINCT department FROM staff ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-user-gear me-2 text-primary"></i>Salary Setup</h3>
        <p class="text-muted small mb-0">Assign structural base salaries, benefits allowances, tax withholdings, and disbursement settings to staff members.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="payroll.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Search & Filters -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end" id="filterForm">
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted">Search Employee</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Name, ID or Designation..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Department</label>
                <select class="form-select" name="department" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo $dept === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <a href="payroll_setup.php" class="btn btn-outline-secondary w-100"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Responsive Setup Table -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Staff Member</th>
                        <th>Role & Dept</th>
                        <th class="text-end">Basic Salary</th>
                        <th class="text-end text-success">Allowances</th>
                        <th class="text-end text-danger">Deductions</th>
                        <th class="text-center">Method</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffList)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No employees matching search criteria.</td></tr>
                    <?php else: foreach ($staffList as $st): 
                        $basic = (float)($st['basic_salary'] ?? 0);
                        $allow = (float)($st['hra'] ?? 0) + (float)($st['medical_allowance'] ?? 0) + (float)($st['transport_allowance'] ?? 0) + (float)($st['other_allowances'] ?? 0);
                        $deduct = (float)($st['provident_fund'] ?? 0) + (float)($st['tax_deduction'] ?? 0) + (float)($st['eobi'] ?? 0) + (float)($st['other_deductions'] ?? 0);
                        $method = $st['payment_method'] ?? '—';
                        $status = $st['setup_status'] ?? 'Inactive';
                        ?>
                        <tr>
                            <td class="fw-bold text-secondary"><?php echo htmlspecialchars($st['employee_no']); ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($st['designation']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill"><?php echo htmlspecialchars($st['department']); ?></span>
                            </td>
                            <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($basic, 2); ?></td>
                            <td class="text-end text-success fw-semibold">Rs. <?php echo number_format($allow, 2); ?></td>
                            <td class="text-end text-danger fw-semibold">Rs. <?php echo number_format($deduct, 2); ?></td>
                            <td class="text-center small"><?php echo htmlspecialchars($method); ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $status === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill"><?php echo $status; ?></span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary px-3 rounded-pill" onclick='editSetup(<?php echo json_encode($st); ?>)'>
                                    <i class="fa-solid fa-pen-to-square me-1"></i>Configure
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form Setup -->
<div class="modal fade" id="setupModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-sack-dollar me-2 text-primary"></i>Assign Salary Structure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="setupForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_salary_setup">
                    <input type="hidden" name="staff_id" id="setupStaffId">

                    <!-- Basic employee identity info card -->
                    <div class="alert alert-light border-0 shadow-sm py-2 mb-4 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small">Employee:</span>
                            <strong class="text-dark ms-1" id="setupStaffName">Loading...</strong>
                        </div>
                        <div>
                            <span class="text-muted small">ID:</span>
                            <strong class="text-primary ms-1" id="setupStaffNo">Loading...</strong>
                        </div>
                    </div>

                    <!-- Earnings -->
                    <h6 class="fw-bold text-primary mb-3 border-bottom pb-1"><i class="fa-solid fa-circle-plus me-1 text-success"></i>Monthly Basic & Structural Allowances</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Basic Salary *</label>
                            <div class="input-group">
                                <span class="input-group-text small">Rs.</span>
                                <input type="number" step="0.01" class="form-control" name="basic_salary" id="basic_salary" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">House Rent (HRA)</label>
                            <input type="number" step="0.01" class="form-control" name="hra" id="hra" value="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Medical Allowance</label>
                            <input type="number" step="0.01" class="form-control" name="medical_allowance" id="medical_allowance" value="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Transport Allowance</label>
                            <input type="number" step="0.01" class="form-control" name="transport_allowance" id="transport_allowance" value="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Other Allowances</label>
                            <input type="number" step="0.01" class="form-control" name="other_allowances" id="other_allowances" value="0.00">
                        </div>
                    </div>

                    <!-- Deductions -->
                    <h6 class="fw-bold text-danger mb-3 border-bottom pb-1"><i class="fa-solid fa-circle-minus me-1 text-danger"></i>Structural Withholdings & Deductions</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Provident Fund</label>
                            <input type="number" step="0.01" class="form-control" name="provident_fund" id="provident_fund" value="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Income Tax</label>
                            <input type="number" step="0.01" class="form-control" name="tax_deduction" id="tax_deduction" value="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">EOBI Contrib.</label>
                            <input type="number" step="0.01" class="form-control" name="eobi" id="eobi" value="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Other Deductions</label>
                            <input type="number" step="0.01" class="form-control" name="other_deductions" id="other_deductions" value="0.00">
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <h6 class="fw-bold text-secondary mb-3 border-bottom pb-1"><i class="fa-solid fa-credit-card me-1"></i>Disbursement Settings</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Disbursement Method</label>
                            <select class="form-select" name="payment_method" id="payment_method">
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cash">Cash</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Bank Title</label>
                            <input type="text" class="form-control" name="bank_name" id="bank_name" placeholder="e.g. HBL, Alfalah">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Account Number</label>
                            <input type="text" class="form-control" name="account_number" id="account_number" placeholder="IBAN or Account ID">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Setup Registry Status</label>
                            <select class="form-select" name="status" id="setup_status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="setupForm" class="btn btn-primary px-4" id="btnSave">Save Salary Structure</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="setupToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="setupToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
const modalObj = new bootstrap.Modal(document.getElementById("setupModal"));

function showToast(msg, ok) {
    const t = document.getElementById("setupToast");
    const m = document.getElementById("setupToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function editSetup(st) {
    document.getElementById("setupStaffId").value = st.id;
    document.getElementById("setupStaffName").textContent = st.first_name + " " + st.last_name;
    document.getElementById("setupStaffNo").textContent = st.employee_no;

    document.getElementById("basic_salary").value = st.basic_salary !== null ? st.basic_salary : "25000.00";
    document.getElementById("hra").value = st.hra !== null ? st.hra : "0.00";
    document.getElementById("medical_allowance").value = st.medical_allowance !== null ? st.medical_allowance : "0.00";
    document.getElementById("transport_allowance").value = st.transport_allowance !== null ? st.transport_allowance : "0.00";
    document.getElementById("other_allowances").value = st.other_allowances !== null ? st.other_allowances : "0.00";
    
    document.getElementById("provident_fund").value = st.provident_fund !== null ? st.provident_fund : "0.00";
    document.getElementById("tax_deduction").value = st.tax_deduction !== null ? st.tax_deduction : "0.00";
    document.getElementById("eobi").value = st.eobi !== null ? st.eobi : "0.00";
    document.getElementById("other_deductions").value = st.other_deductions !== null ? st.other_deductions : "0.00";
    
    document.getElementById("payment_method").value = st.payment_method !== null ? st.payment_method : "Bank Transfer";
    document.getElementById("bank_name").value = st.bank_name !== null ? st.bank_name : "";
    document.getElementById("account_number").value = st.account_number !== null ? st.account_number : "";
    document.getElementById("setup_status").value = st.setup_status !== null ? st.setup_status : "Active";

    modalObj.show();
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("setupForm");
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
                        btn.disabled = false; btn.innerHTML = "Save Salary Structure";
                    }
                })
                .catch(() => {
                    showToast("System error.", false);
                    btn.disabled = false; btn.innerHTML = "Save Salary Structure";
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
