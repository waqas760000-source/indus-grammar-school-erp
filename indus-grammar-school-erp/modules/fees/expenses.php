<?php
/**
 * Indus Grammar School ERP - Expenses Management
 * Version 1.0.0
 */

$pageTitle = 'School Expenses';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

// Filters
$filters = [
    'category' => sanitize($_GET['category'] ?? ''),
    'from'     => sanitize($_GET['from'] ?? date('Y-m-01')),
    'to'       => sanitize($_GET['to'] ?? date('Y-m-d')),
];

$expenses = Expense::all($filters, 100);
$totalInView = array_sum(array_column($expenses, 'amount'));
$categories = Expense::getCategories();
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-money-bill-transfer me-2 text-primary"></i>School Expenses</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if (hasPermission('cash_manage')): ?>
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="fa-solid fa-plus me-2"></i>Record Expense
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Category</label>
                <select class="form-select" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat; ?>" <?php echo ($filters['category'] === $cat) ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">From Date</label>
                <input type="date" class="form-control" name="from" value="<?php echo $filters['from']; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">To Date</label>
                <input type="date" class="form-control" name="to" value="<?php echo $filters['to']; ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-filter me-2"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="custom-table-card shadow-sm border-0 h-100">
            <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-secondary">Expense Records</h5>
            </div>
            <div class="table-responsive">
                <table class="table custom-table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Paid To</th>
                            <th class="text-end">Amount (Rs.)</th>
                            <?php if (hasPermission('cash_manage')): ?>
                            <th class="text-center">Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No expenses recorded for the selected period.</td></tr>
                        <?php else: foreach ($expenses as $e): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($e['expense_date'])); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo sanitize($e['category']); ?></span></td>
                                <td class="fw-semibold text-dark"><?php echo sanitize($e['description']); ?></td>
                                <td><?php echo sanitize($e['paid_to'] ?: '-'); ?></td>
                                <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($e['amount'], 2); ?></td>
                                <?php if (hasPermission('cash_manage')): ?>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-danger btn-delete-expense" data-id="<?php echo $e['id']; ?>" title="Delete Record">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #fef5f5);">
            <div class="card-body p-4 d-flex flex-column justify-content-center text-center">
                <div class="mb-4">
                    <div class="d-inline-block p-4 rounded-circle bg-danger-soft mb-3">
                        <i class="fa-solid fa-chart-pie fs-1 text-danger"></i>
                    </div>
                    <h5 class="text-muted fw-semibold">Total Expenses</h5>
                    <p class="small text-muted mb-0">For selected period</p>
                </div>
                <h2 class="fw-bold text-danger mb-0 display-5">Rs. <?php echo number_format($totalInView, 2); ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Record Expense Modal -->
<?php if (hasPermission('cash_manage')): ?>
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-money-bill-transfer me-2 text-primary"></i>Record Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <div class="alert alert-warning border-0 shadow-sm small py-2 mb-4">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>This will be automatically deducted from the open cash register.
                </div>
                <form id="addExpenseForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="record_expense">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Date</label>
                            <input type="date" class="form-control" name="expense_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Category</label>
                            <select class="form-select" name="category" required>
                                <option value="">— Select Category —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Amount (Rs.)</label>
                        <input type="number" class="form-control fw-bold text-danger fs-5" name="amount" min="1" step="0.01" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description</label>
                        <input type="text" class="form-control" name="description" placeholder="What was this expense for?" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Paid To (Optional)</label>
                            <input type="text" class="form-control" name="paid_to" placeholder="Person / Vendor">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Ref / Receipt # (Optional)</label>
                            <input type="text" class="form-control" name="receipt_reference" placeholder="Ref No">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addExpenseForm" class="btn btn-primary px-4" id="btnSaveExp">Record Expense</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="expToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="expToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("expToast");
    const m = document.getElementById("expToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Form Submission
    const form = document.getElementById("addExpenseForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSaveExp");
            btn.disabled = true;
            btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Recording...\';
            
            fetch("../../ajax/cash.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Record Expense"; }
                })
                .catch(() => {
                    showToast("Network error.", false);
                    btn.disabled = false; btn.innerHTML = "Record Expense";
                });
        });
    }

    // Delete Expense
    document.querySelectorAll(".btn-delete-expense").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this expense record? This will adjust the cash register accordingly.")) return;
            const id = this.dataset.id;
            const fd = new FormData();
            fd.append("action", "delete_expense");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/cash.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
