<?php
/**
 * Indus Grammar School ERP - Payroll Management
 * Version 1.0.0
 */

$pageTitle = 'Payroll Management';
$breadcrumbActive = 'HR & Staff';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('hr_view');

$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedStatus = $_GET['status'] ?? '';

$filters = [
    'month' => $selectedMonth,
    'year'  => $selectedYear
];
if ($selectedStatus) $filters['status'] = sanitize($selectedStatus);

$salaries = Payroll::all($filters, 500);

$totalGross = array_sum(array_column($salaries, 'basic_salary')) + array_sum(array_column($salaries, 'allowances'));
$totalDeductions = array_sum(array_column($salaries, 'deductions'));
$totalNet = array_sum(array_column($salaries, 'net_salary'));
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-money-check-dollar me-2 text-primary"></i>Payroll Management</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if (hasPermission('hr_manage')): ?>
        <button class="btn btn-primary px-4" id="btnGenPayroll">
            <i class="fa-solid fa-gears me-2"></i>Generate Payroll
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Month</label>
                <select class="form-select" name="month" onchange="document.getElementById('filterForm').submit()">
                    <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $selectedMonth == $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Year</label>
                <select class="form-select" name="year" onchange="document.getElementById('filterForm').submit()">
                    <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $selectedYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Payment Status</label>
                <select class="form-select" name="status" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?php echo $selectedStatus === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Paid" <?php echo $selectedStatus === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #f8f9fa);">
            <div class="card-body p-4 text-center">
                <div class="small text-muted fw-semibold mb-1">Total Gross Salary</div>
                <h3 class="fw-bold text-dark mb-0">Rs. <?php echo number_format($totalGross, 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #fef5f5);">
            <div class="card-body p-4 text-center">
                <div class="small text-muted fw-semibold mb-1">Total Deductions</div>
                <h3 class="fw-bold text-danger mb-0">Rs. <?php echo number_format($totalDeductions, 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #f0fdf4);">
            <div class="card-body p-4 text-center">
                <div class="small text-muted fw-semibold mb-1">Total Net Payable</div>
                <h3 class="fw-bold text-success mb-0">Rs. <?php echo number_format($totalNet, 2); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Designation</th>
                    <th class="text-end">Basic Salary</th>
                    <th class="text-end text-success">+ Allow.</th>
                    <th class="text-end text-danger">- Deduct.</th>
                    <th class="text-end fw-bold">Net Salary</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($salaries)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No payroll data found for <?php echo date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear)); ?>. Click "Generate Payroll" to create records.</td></tr>
                <?php else: foreach ($salaries as $s): ?>
                    <tr>
                        <td class="fw-semibold text-dark">
                            <?php echo sanitize($s['first_name'] . ' ' . $s['last_name']); ?>
                            <br><small class="text-muted"><?php echo sanitize($s['employee_no']); ?></small>
                        </td>
                        <td><?php echo sanitize($s['designation']); ?></td>
                        <td class="text-end">Rs. <?php echo number_format($s['basic_salary'], 2); ?></td>
                        <td class="text-end text-success">Rs. <?php echo number_format($s['allowances'], 2); ?></td>
                        <td class="text-end text-danger">Rs. <?php echo number_format($s['deductions'], 2); ?></td>
                        <td class="text-end fw-bold fs-6">Rs. <?php echo number_format($s['net_salary'], 2); ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $s['payment_status'] === 'Paid' ? 'success' : 'warning text-dark'; ?>-soft px-3 py-2 rounded-pill">
                                <?php echo sanitize($s['payment_status']); ?>
                            </span>
                            <?php if ($s['payment_status'] === 'Paid'): ?>
                                <br><small class="text-muted" style="font-size:0.7rem;"><?php echo date('d M Y', strtotime($s['payment_date'])); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="salary_slip.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="Print Slip">
                                <i class="fa-solid fa-print"></i>
                            </a>
                            <?php if ($s['payment_status'] === 'Pending' && hasPermission('hr_manage')): ?>
                            <button class="btn btn-sm btn-success ms-1 btn-pay" data-id="<?php echo $s['id']; ?>" title="Mark as Paid">
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

<!-- Pay Salary Modal -->
<?php if (hasPermission('hr_manage')): ?>
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-money-check-dollar me-2 text-success"></i>Process Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="payForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="pay_salary">
                    <input type="hidden" name="salary_id" id="paySalaryId">
                    
                    <div class="alert alert-info border-0 shadow-sm small py-2 mb-4">
                        <i class="fa-solid fa-info-circle me-2"></i>If paid via Cash, this will automatically record an expense in the active cash register.
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Payment Date</label>
                        <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Payment Method</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="Bank">Bank Transfer</option>
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
<?php endif; ?>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="prToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="prToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("prToast");
    const m = document.getElementById("prToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    
    // Generate Payroll
    const btnGen = document.getElementById("btnGenPayroll");
    if (btnGen) {
        btnGen.addEventListener("click", function() {
            if(!confirm("Are you sure you want to generate payroll for ' . date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear)) . '?\\nThis will create salary records for all active staff.")) return;
            
            btnGen.disabled = true;
            btnGen.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Generating...\';
            
            const fd = new FormData();
            fd.append("action", "generate_payroll");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("month", "' . $selectedMonth . '");
            fd.append("year", "' . $selectedYear . '");
            
            fetch("../../ajax/staff.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1500);
                    else { btnGen.disabled = false; btnGen.innerHTML = \'<i class="fa-solid fa-gears me-2"></i>Generate Payroll\'; }
                });
        });
    }

    // Process Payment Modal
    let payModal;
    document.querySelectorAll(".btn-pay").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("paySalaryId").value = this.dataset.id;
            payModal = new bootstrap.Modal(document.getElementById("payModal"));
            payModal.show();
        });
    });

    const form = document.getElementById("payForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnConfirmPay");
            btn.disabled = true; btn.innerHTML = "Processing...";
            
            fetch("../../ajax/staff.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Confirm Payment"; }
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
