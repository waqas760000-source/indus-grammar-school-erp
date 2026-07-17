<?php
/**
 * Indus Grammar School ERP - Salary Processing Ledger
 * Version 4.0.0
 */

$pageTitle = 'Salary Processing';
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

// Default target filters
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedDept  = sanitize($_GET['department'] ?? '');

$where = " WHERE p.month = :month AND p.year = :year";
$params = [
    'month' => $selectedMonth,
    'year'  => $selectedYear
];
if ($selectedDept !== '') {
    $where .= " AND s.department = :dept";
    $params['dept'] = $selectedDept;
}

// Fetch processed salary rows for month & year
$salaries = [];
try {
    $stmt = $db->prepare("
        SELECT d.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
        FROM salary_details d
        JOIN salary_processing p ON d.processing_id = p.id
        JOIN staff s ON d.staff_id = s.id
        $where
        ORDER BY s.employee_no ASC
    ");
    $stmt->execute($params);
    $salaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading processed salaries: " . $e->getMessage());
}

$departments = $db->query("SELECT DISTINCT department FROM staff ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-gears me-2 text-primary"></i>Salary Processing</h3>
        <p class="text-muted small mb-0">Generate monthly salaries automatically based on staff parameters, attendance metrics, loans, and bonuses.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="payroll.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Run Form Controls -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Select Billing Month</label>
                <select class="form-select" name="month" onchange="document.getElementById('filterForm').submit()">
                    <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $selectedMonth === $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Select Year</label>
                <select class="form-select" name="year" onchange="document.getElementById('filterForm').submit()">
                    <?php for($y=date('Y'); $y>=2024; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $selectedYear === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Department</label>
                <select class="form-select" name="department" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo $selectedDept === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3 text-end">
                <?php if (hasPermission('hr_manage')): ?>
                <button type="button" class="btn btn-success w-100 py-2 fw-semibold" id="btnProcess">
                    <i class="fa-solid fa-calculator me-2"></i>Generate Payroll Run
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Processed list -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-header bg-white border-0 pt-4 px-4">
        <h5 class="fw-bold text-secondary mb-0">Processed Salaries Ledger (<?php echo date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear)); ?>)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name & Dept</th>
                        <th class="text-center">Work Days</th>
                        <th class="text-center">Pres/Abs</th>
                        <th class="text-end">Base Salary</th>
                        <th class="text-end text-success">+ Allow.</th>
                        <th class="text-end text-danger">- Deduct.</th>
                        <th class="text-end text-purple">- Advance</th>
                        <th class="text-end text-success">+ Bonus</th>
                        <th class="text-end fw-bold">Net Salary</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($salaries)): ?>
                        <tr><td colspan="12" class="text-center py-5 text-muted">No processed payroll records found for this period. Click "Generate Payroll Run" above to calculate.</td></tr>
                    <?php else: foreach ($salaries as $s): ?>
                        <tr>
                            <td class="fw-bold text-secondary"><?php echo htmlspecialchars($s['employee_no']); ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($s['designation'] . ' | ' . $s['department']); ?></small>
                            </td>
                            <td class="text-center fw-semibold"><?php echo $s['working_days']; ?></td>
                            <td class="text-center small">
                                <span class="text-success fw-bold"><?php echo $s['present_days']; ?></span> / 
                                <span class="text-danger fw-bold"><?php echo $s['absent_days']; ?></span>
                                <?php if($s['late_days'] > 0): ?>
                                    <br><small class="text-warning fw-semibold"><?php echo $s['late_days']; ?> Late</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($s['basic_salary'], 2); ?></td>
                            <td class="text-end text-success fw-semibold">Rs. <?php echo number_format($s['allowances'], 2); ?></td>
                            <td class="text-end text-danger fw-semibold">Rs. <?php echo number_format($s['deductions'], 2); ?></td>
                            <td class="text-end text-purple fw-semibold">Rs. <?php echo number_format($s['advance_salary_deduction'], 2); ?></td>
                            <td class="text-end text-success fw-semibold">Rs. <?php echo number_format($s['bonus'], 2); ?></td>
                            <td class="text-end fw-bold fs-6 text-primary">Rs. <?php echo number_format($s['net_salary'], 2); ?></td>
                            <td class="text-center">
                                <?php if ($s['payment_status'] === 'Paid'): ?>
                                    <span class="badge bg-success-soft text-success px-3 py-1 rounded-pill">Paid</span>
                                    <br><small class="text-muted" style="font-size:0.7rem;"><?php echo date('d M Y', strtotime($s['payment_date'])); ?></small>
                                <?php else: ?>
                                    <span class="badge bg-warning-soft text-warning px-3 py-1 rounded-pill">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="payroll_slips.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="Preview Slip">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                                <?php if ($s['payment_status'] === 'Pending' && hasPermission('hr_manage')): ?>
                                    <button class="btn btn-sm btn-success ms-1 btn-pay" data-id="<?php echo $s['id']; ?>" data-name="<?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>" data-net="<?php echo number_format($s['net_salary'], 2); ?>" title="Pay Salary">
                                        <i class="fa-solid fa-check"></i>
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

<!-- Pay Salary Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-wallet me-2 text-success"></i>Disburse Salary Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="payForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="pay_salary">
                    <input type="hidden" name="salary_id" id="paySalaryId">

                    <div class="alert alert-info border-0 shadow-sm small py-2 mb-4">
                        <i class="fa-solid fa-info-circle me-2"></i>Processing this payment will automatically insert an expense posting in the **Accounts Ledger** and update cash/bank registers.
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Employee Name</label>
                        <input type="text" class="form-control bg-light" id="payStaffName" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Net Payable Salary</label>
                        <div class="input-group">
                            <span class="input-group-text small bg-light">Rs.</span>
                            <input type="text" class="form-control bg-light fw-bold text-primary" id="payNetSalary" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Payment Date *</label>
                        <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Payment Method *</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cash">Cash</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="payForm" class="btn btn-success px-4" id="btnConfirmPay">Confirm Payment</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="procToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="procToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
const payModalObj = new bootstrap.Modal(document.getElementById("payModal"));

function showToast(msg, ok) {
    const t = document.getElementById("procToast");
    const m = document.getElementById("procToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Generate Payroll trigger
    const btnProcess = document.getElementById("btnProcess");
    if(btnProcess) {
        btnProcess.addEventListener("click", function() {
            if(!confirm("Are you sure you want to generate/reprocess payroll for ' . date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear)) . '? This will update entries based on active settings.")) return;
            this.disabled = true; this.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2\"></span>Processing...";

            const fd = new FormData();
            fd.append("action", "generate_payroll_bulk");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("month", "' . $selectedMonth . '");
            fd.append("year", "' . $selectedYear . '");
            fd.append("department", "' . $selectedDept . '");

            fetch("../../ajax/payroll.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1200);
                    else { this.disabled = false; this.innerHTML = "<i class=\"fa-solid fa-calculator me-2\"></i>Generate Payroll Run"; }
                })
                .catch(() => {
                    showToast("Communication error.", false);
                    this.disabled = false; this.innerHTML = "<i class=\"fa-solid fa-calculator me-2\"></i>Generate Payroll Run";
                });
        });
    }

    // Modal Pay triggers
    document.querySelectorAll(".btn-pay").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("paySalaryId").value = this.dataset.id;
            document.getElementById("payStaffName").value = this.dataset.name;
            document.getElementById("payNetSalary").value = this.dataset.net;
            payModalObj.show();
        });
    });

    // Form Pay submission
    const payForm = document.getElementById("payForm");
    if(payForm) {
        payForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnConfirmPay");
            btn.disabled = true; btn.innerHTML = "Processing...";

            fetch("../../ajax/payroll.php", { method: "POST", body: new FormData(payForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        payModalObj.hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        btn.disabled = false; btn.innerHTML = "Confirm Payment";
                    }
                })
                .catch(() => {
                    showToast("System error.", false);
                    btn.disabled = false; btn.innerHTML = "Confirm Payment";
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
