<?php
/**
 * Indus Grammar School ERP - Home Expenses Panel (Normalized)
 * Version 4.0.0
 */

$pageTitle = 'Home Expenses';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// Filters
$filterCategory = (int)($_GET['category_filter'] ?? 0);
$filterMethod   = sanitize($_GET['method_filter'] ?? '');
$fromDate       = sanitize($_GET['from_date'] ?? '');
$toDate         = sanitize($_GET['to_date'] ?? '');

$where = " WHERE 1=1";
$params = [];

if ($filterCategory > 0) {
    $where .= " AND e.category_id = :cat";
    $params['cat'] = $filterCategory;
}
if ($filterMethod !== '') {
    $where .= " AND e.payment_method = :method";
    $params['method'] = $filterMethod;
}
if ($fromDate !== '') {
    $where .= " AND e.expense_date >= :from";
    $params['from'] = $fromDate;
}
if ($toDate !== '') {
    $where .= " AND e.expense_date <= :to";
    $params['to'] = $toDate;
}

$expenses = [];
try {
    $stmt = $db->prepare("
        SELECT e.*, c.name as category_name, u.username as paid_by_name
        FROM expenses e
        LEFT JOIN expense_categories c ON e.category_id = c.id
        LEFT JOIN users u ON e.paid_by = u.id
        $where
        ORDER BY e.expense_date DESC, e.id DESC
    ");
    $stmt->execute($params);
    $expenses = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error loading expenses: " . $e->getMessage());
}

// Fetch all active categories
$categories = [];
try {
    $categories = $db->query("SELECT * FROM expense_categories WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-circle-up me-2 text-danger"></i>Home & Operational Expenses</h3>
        <p class="text-muted small mb-0">Record and review school bills, rent, stationery, maintenance repairs, and support file attachments.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-danger px-4" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="fa-solid fa-plus me-2"></i>Record Operational Expense
        </button>
    </div>
</div>

<!-- Filters Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Expenses</h6>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Expense Category</label>
                <select class="form-select form-select-sm" name="category_filter">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($filterCategory == $cat['id']) ? 'selected' : ''; ?>><?php echo sanitize($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Payment Mode</label>
                <select class="form-select form-select-sm" name="method_filter">
                    <option value="">All Methods</option>
                    <option value="Cash" <?php echo ($filterMethod === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                    <option value="Bank" <?php echo ($filterMethod === 'Bank') ? 'selected' : ''; ?>>Bank</option>
                    <option value="Cheque" <?php echo ($filterMethod === 'Cheque') ? 'selected' : ''; ?>>Cheque</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">From Date</label>
                <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">To Date</label>
                <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i>Filter</button>
                <a href="expenses.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Expenses Logs Table -->
<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Voucher No</th>
                    <th>Expense Date</th>
                    <th>Category</th>
                    <th>Expense Title</th>
                    <th>Vendor / Invoice</th>
                    <th>Amount</th>
                    <th>Payment Mode</th>
                    <th class="text-center">Attach</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No operational expenses logged matching filters.</td></tr>
                <?php else: foreach ($expenses as $exp): ?>
                    <tr>
                        <td><code class="fw-bold text-danger">EXP-<?php echo str_pad($exp['id'], 6, '0', STR_PAD_LEFT); ?></code></td>
                        <td><span class="small fw-semibold text-dark"><?php echo date('d M Y', strtotime($exp['expense_date'])); ?></span></td>
                        <td>
                            <span class="badge bg-danger-soft text-danger px-3 py-2 rounded-pill fw-semibold"><?php echo sanitize($exp['category_name'] ?: 'General'); ?></span>
                        </td>
                        <td class="fw-bold text-dark text-wrap" style="max-width:180px;"><?php echo sanitize($exp['title']); ?></td>
                        <td>
                            <div class="small fw-semibold"><?php echo sanitize($exp['vendor_supplier'] ?: '—'); ?></div>
                            <small class="text-muted" style="font-size:0.7rem;">Inv: <?php echo sanitize($exp['invoice_number'] ?: '—'); ?></small>
                        </td>
                        <td class="fw-bold text-danger">Rs. <?php echo number_format($exp['amount'], 2); ?></td>
                        <td>
                            <?php
                            $mCls = ['Cash' => 'success', 'Bank' => 'info', 'Cheque' => 'warning'];
                            $c = $mCls[$exp['payment_method']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $c; ?>-soft px-3 py-2 rounded-pill"><?php echo sanitize($exp['payment_method']); ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($exp['attachment']): ?>
                                <a href="<?php echo APP_URL . '/' . $exp['attachment']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary p-1" title="View Document">
                                    <i class="fa-solid fa-file-invoice"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary btn-print-voucher" 
                                    data-ref="EXP-<?php echo str_pad($exp['id'], 6, '0', STR_PAD_LEFT); ?>"
                                    data-date="<?php echo date('d M Y', strtotime($exp['expense_date'])); ?>"
                                    data-category="<?php echo htmlspecialchars($exp['category_name'] ?: 'General'); ?>"
                                    data-title="<?php echo htmlspecialchars($exp['title']); ?>"
                                    data-vendor="<?php echo htmlspecialchars($exp['vendor_supplier'] ?: '—'); ?>"
                                    data-invoice="<?php echo htmlspecialchars($exp['invoice_number'] ?: '—'); ?>"
                                    data-amount="<?php echo number_format($exp['amount'], 2); ?>"
                                    data-method="<?php echo htmlspecialchars($exp['payment_method']); ?>"
                                    data-by="<?php echo htmlspecialchars($exp['paid_by_name'] ?: 'System'); ?>"
                                    data-remarks="<?php echo htmlspecialchars($exp['remarks'] ?? ''); ?>"
                                    title="Print Voucher">
                                <i class="fa-solid fa-print"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary btn-edit-expense" 
                                    data-id="<?php echo $exp['id']; ?>"
                                    data-date="<?php echo $exp['expense_date']; ?>"
                                    data-category="<?php echo $exp['category_id']; ?>"
                                    data-title="<?php echo htmlspecialchars($exp['title']); ?>"
                                    data-vendor="<?php echo htmlspecialchars($exp['vendor_supplier'] ?? ''); ?>"
                                    data-invoice="<?php echo htmlspecialchars($exp['invoice_number'] ?? ''); ?>"
                                    data-amount="<?php echo $exp['amount']; ?>"
                                    data-method="<?php echo htmlspecialchars($exp['payment_method']); ?>"
                                    data-attach="<?php echo htmlspecialchars($exp['attachment'] ?? ''); ?>"
                                    data-remarks="<?php echo htmlspecialchars($exp['remarks'] ?? ''); ?>"
                                    title="Edit Expense">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger btn-delete-expense" data-id="<?php echo $exp['id']; ?>" title="Delete">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Record Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-circle-up me-2 text-danger"></i>Record Operational Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="addExpenseForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_expense">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Expense Date *</label>
                            <input type="date" class="form-control form-control-sm" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Expense Category *</label>
                            <select class="form-select form-select-sm" name="category_id" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo sanitize($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Expense Title *</label>
                        <input type="text" class="form-control form-control-sm" name="title" placeholder="e.g. Purchase of Whiteboard Markers" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Vendor / Supplier</label>
                            <input type="text" class="form-control form-control-sm" name="vendor_supplier" placeholder="e.g. Allied Book Depot">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Invoice Number</label>
                            <input type="text" class="form-control form-control-sm" name="invoice_number" placeholder="e.g. INV-1002">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Amount Paid (Rs.) *</label>
                            <input type="number" class="form-control form-control-sm" name="amount" step="0.01" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Payment Mode *</label>
                            <select class="form-select form-select-sm" name="payment_method" required>
                                <option value="Cash">Cash Drawer</option>
                                <option value="Bank">Bank Account</option>
                                <option value="Cheque">Bank Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Bill Attachment (PDF/Image)</label>
                        <input type="file" class="form-control form-control-sm" name="attachment" accept="image/*,application/pdf">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Internal Remarks / Description</label>
                        <textarea class="form-control form-control-sm" name="remarks" rows="2" placeholder="Administrative notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addExpenseForm" class="btn btn-sm btn-danger px-4" id="btnAddExpense">Save Expense</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Expense Modal -->
<div class="modal fade" id="editExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-pen me-2 text-primary"></i>Modify Expense Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="editExpenseForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="edit_expense">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="existing_attachment" id="edit_existing_attach">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Expense Date *</label>
                            <input type="date" class="form-control form-control-sm" name="expense_date" id="edit_date" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Expense Category *</label>
                            <select class="form-select form-select-sm" name="category_id" id="edit_category" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo sanitize($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Expense Title *</label>
                        <input type="text" class="form-control form-control-sm" name="title" id="edit_title" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Vendor / Supplier</label>
                            <input type="text" class="form-control form-control-sm" name="vendor_supplier" id="edit_vendor">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Invoice Number</label>
                            <input type="text" class="form-control form-control-sm" name="invoice_number" id="edit_invoice">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Amount Paid (Rs.) *</label>
                            <input type="number" class="form-control form-control-sm" name="amount" id="edit_amount" step="0.01" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Payment Mode *</label>
                            <select class="form-select form-select-sm" name="payment_method" id="edit_method" required>
                                <option value="Cash">Cash Drawer</option>
                                <option value="Bank">Bank Account</option>
                                <option value="Cheque">Bank Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Update Bill Attachment (Optional)</label>
                        <input type="file" class="form-control form-control-sm" name="attachment" accept="image/*,application/pdf">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Internal Remarks / Description</label>
                        <textarea class="form-control form-control-sm" name="remarks" id="edit_remarks" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editExpenseForm" class="btn btn-sm btn-primary px-4" id="btnEditExpense">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Print Voucher Template (Hidden, printed via JS popup) -->
<div id="printExpenseVoucherSection" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 30px; border: 2px solid #000; width: 600px; margin: 0 auto; border-radius:10px;">
        <div style="text-align: center; border-bottom: 2px double #000; padding-bottom: 10px; margin-bottom: 20px;">
            <h2 style="margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h2>
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #555;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 10px 0 0 0; background: #eee; padding: 5px; border-radius: 5px;">EXPENSE PAYMENT DEBIT VOUCHER</h4>
        </div>
        
        <table style="width: 100%; margin-bottom: 20px; font-size: 14px;">
            <tr>
                <td style="width: 50%;"><strong>Voucher No:</strong> <span id="ev_ref"></span></td>
                <td style="width: 50%; text-align: right;"><strong>Date:</strong> <span id="ev_date"></span></td>
            </tr>
        </table>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;">
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Category:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="ev_category"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Expense Title:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="ev_title"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Vendor / Supplier:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="ev_vendor"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Invoice Reference:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="ev_invoice"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Payment Method:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="ev_method"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Disbursed By:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="ev_by"></td>
            </tr>
        </table>
        
        <div style="background: #fdf2f2; padding: 15px; text-align: center; border-radius: 5px; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #c62828;">DEBIT AMOUNT: Rs. <span id="ev_amount"></span></h3>
        </div>
        
        <table style="width: 100%; margin-top: 50px; font-size: 12px; text-align: center;">
            <tr>
                <td style="width: 33%;"><div style="border-top: 1px solid #000; width: 120px; margin: 0 auto; padding-top: 5px;">Receiver Signature</div></td>
                <td style="width: 33%;"><div style="border-top: 1px solid #000; width: 120px; margin: 0 auto; padding-top: 5px;">Verified Accountant</div></td>
                <td style="width: 33%;"><div style="border-top: 1px solid #000; width: 120px; margin: 0 auto; padding-top: 5px;">Principal / Admin</div></td>
            </tr>
        </table>
    </div>
</div>

<!-- Toast Feedback -->
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
    // Record Expense
    const addForm = document.getElementById("addExpenseForm");
    if (addForm) {
        addForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnAddExpense");
            btn.disabled = true; btn.innerHTML = "Saving...";
            
            fetch("../../ajax/accounts.php", { method: "POST", body: new FormData(addForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Save Expense"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "Save Expense"; });
        });
    }

    // Bind Edit Modal
    document.querySelectorAll(".btn-edit-expense").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("edit_id").value = this.dataset.id;
            document.getElementById("edit_date").value = this.dataset.date;
            document.getElementById("edit_category").value = this.dataset.category;
            document.getElementById("edit_title").value = this.dataset.title;
            document.getElementById("edit_vendor").value = this.dataset.vendor;
            document.getElementById("edit_invoice").value = this.dataset.invoice;
            document.getElementById("edit_amount").value = this.dataset.amount;
            document.getElementById("edit_method").value = this.dataset.method;
            document.getElementById("edit_existing_attach").value = this.dataset.attach;
            document.getElementById("edit_remarks").value = this.dataset.remarks;
            
            new bootstrap.Modal(document.getElementById("editExpenseModal")).show();
        });
    });

    // Save Edit Form
    const editForm = document.getElementById("editExpenseForm");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnEditExpense");
            btn.disabled = true; btn.innerHTML = "Saving...";
            
            fetch("../../ajax/accounts.php", { method: "POST", body: new FormData(editForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Save Changes"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "Save Changes"; });
        });
    }

    // Delete Expense Action
    document.querySelectorAll(".btn-delete-expense").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            if(!confirm("Are you sure you want to permanently delete this expense log?")) return;
            const fd = new FormData();
            fd.append("action", "delete_expense");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/accounts.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 1000);
                });
        });
    });

    // Print Voucher Action
    document.querySelectorAll(".btn-print-voucher").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("ev_ref").textContent = this.dataset.ref;
            document.getElementById("ev_date").textContent = this.dataset.date;
            document.getElementById("ev_category").textContent = this.dataset.category;
            document.getElementById("ev_title").textContent = this.dataset.title;
            document.getElementById("ev_vendor").textContent = this.dataset.vendor;
            document.getElementById("ev_invoice").textContent = this.dataset.invoice;
            document.getElementById("ev_method").textContent = this.dataset.method;
            document.getElementById("ev_by").textContent = this.dataset.by;
            document.getElementById("ev_amount").textContent = this.dataset.amount;
            
            const printContent = document.getElementById("printExpenseVoucherSection").innerHTML;
            
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Expense Debit Voucher</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
