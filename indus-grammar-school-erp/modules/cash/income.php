<?php
/**
 * Indus Grammar School ERP - Income Management Ledger
 * Version 4.0.0
 */

$pageTitle = 'Income Ledger';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// Filters
$filterSource = sanitize($_GET['source_filter'] ?? '');
$filterMethod = sanitize($_GET['method_filter'] ?? '');
$fromDate     = sanitize($_GET['from_date'] ?? '');
$toDate       = sanitize($_GET['to_date'] ?? '');

$where = " WHERE 1=1";
$params = [];

if ($filterSource !== '') {
    $where .= " AND i.source = :source";
    $params['source'] = $filterSource;
}
if ($filterMethod !== '') {
    $where .= " AND i.payment_method = :method";
    $params['method'] = $filterMethod;
}
if ($fromDate !== '') {
    $where .= " AND i.income_date >= :from";
    $params['from'] = $fromDate;
}
if ($toDate !== '') {
    $where .= " AND i.income_date <= :to";
    $params['to'] = $toDate;
}

$incomes = [];
try {
    $stmt = $db->prepare("
        SELECT i.*, u.username as received_by_name, s.full_name as student_name, s.admission_no
        FROM income i
        LEFT JOIN users u ON i.received_by = u.id
        LEFT JOIN students s ON i.student_id = s.id
        $where
        ORDER BY i.income_date DESC, i.id DESC
    ");
    $stmt->execute($params);
    $incomes = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error loading income list: " . $e->getMessage());
}

// Fetch students for reference link dropdown
$students = [];
try {
    $students = $db->query("SELECT id, admission_no, full_name FROM students WHERE status = 'Active' ORDER BY full_name ASC")->fetchAll();
} catch (Exception $e) {}

$sources = [
    'Student Fee Collection', 'Admission Fee', 'Registration Fee', 'Examination Fee', 
    'Transport Fee', 'Uniform Sale', 'Books & Stationery', 'Donations', 'Miscellaneous Income'
];
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-circle-down me-2 text-success"></i>Income Ledger</h3>
        <p class="text-muted small mb-0">Record school collections, uniform/book sales, donations, and view automated student fees receipts.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-success px-4" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
            <i class="fa-solid fa-plus me-2"></i>Record Manual Income
        </button>
    </div>
</div>

<!-- Filters Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Incomes</h6>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Income Source</label>
                <select class="form-select form-select-sm" name="source_filter">
                    <option value="">All Sources</option>
                    <?php foreach ($sources as $src): ?>
                        <option value="<?php echo $src; ?>" <?php echo ($filterSource === $src) ? 'selected' : ''; ?>><?php echo $src; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Payment Method</label>
                <select class="form-select form-select-sm" name="method_filter">
                    <option value="">All Methods</option>
                    <option value="Cash" <?php echo ($filterMethod === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                    <option value="Bank" <?php echo ($filterMethod === 'Bank') ? 'selected' : ''; ?>>Bank</option>
                    <option value="Online" <?php echo ($filterMethod === 'Online') ? 'selected' : ''; ?>>Online</option>
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
                <a href="income.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Income Records Table -->
<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Voucher No</th>
                    <th>Date</th>
                    <th>Source</th>
                    <th>Student Context</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                    <th>Received By</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($incomes)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No income records matching filters.</td></tr>
                <?php else: foreach ($incomes as $inc): ?>
                    <tr>
                        <td><code class="fw-bold text-primary"><?php echo sanitize($inc['reference_no']); ?></code></td>
                        <td><span class="small fw-semibold"><?php echo date('d M Y', strtotime($inc['income_date'])); ?></span></td>
                        <td>
                            <?php
                            $badge = ($inc['source'] === 'Student Fee Collection') ? 'bg-primary-soft text-primary' : 'bg-success-soft text-success';
                            ?>
                            <span class="badge <?php echo $badge; ?> px-3 py-2 rounded-pill fw-semibold"><?php echo sanitize($inc['source']); ?></span>
                        </td>
                        <td>
                            <?php if ($inc['student_id']): ?>
                                <span class="small fw-semibold text-dark"><?php echo sanitize($inc['student_name']); ?></span>
                                <br><small class="text-muted" style="font-size:0.7rem;">Adm: <?php echo sanitize($inc['admission_no']); ?></small>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small" style="max-width:200px; white-space:normal;"><?php echo sanitize($inc['description'] ?: 'No details'); ?></td>
                        <td class="fw-bold text-dark">Rs. <?php echo number_format($inc['amount'], 2); ?></td>
                        <td>
                            <?php
                            $mCls = ['Cash' => 'success', 'Bank' => 'info', 'Online' => 'primary', 'Cheque' => 'warning'];
                            $c = $mCls[$inc['payment_method']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $c; ?>-soft px-3 py-2 rounded-pill"><?php echo sanitize($inc['payment_method']); ?></span>
                        </td>
                        <td><code class="text-muted"><?php echo sanitize($inc['received_by_name'] ?: 'System'); ?></code></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary btn-print-voucher" 
                                    data-ref="<?php echo htmlspecialchars($inc['reference_no']); ?>"
                                    data-date="<?php echo date('d M Y', strtotime($inc['income_date'])); ?>"
                                    data-source="<?php echo htmlspecialchars($inc['source']); ?>"
                                    data-desc="<?php echo htmlspecialchars($inc['description'] ?? ''); ?>"
                                    data-amount="<?php echo number_format($inc['amount'], 2); ?>"
                                    data-method="<?php echo htmlspecialchars($inc['payment_method']); ?>"
                                    data-by="<?php echo htmlspecialchars($inc['received_by_name'] ?: 'System'); ?>"
                                    data-remarks="<?php echo htmlspecialchars($inc['remarks'] ?? ''); ?>"
                                    title="Print Voucher">
                                <i class="fa-solid fa-print"></i>
                            </button>
                            <?php if ($inc['source'] !== 'Student Fee Collection'): ?>
                                <button class="btn btn-sm btn-outline-secondary btn-edit-income" 
                                        data-id="<?php echo $inc['id']; ?>"
                                        data-date="<?php echo $inc['income_date']; ?>"
                                        data-source="<?php echo htmlspecialchars($inc['source']); ?>"
                                        data-ref="<?php echo htmlspecialchars($inc['reference_no']); ?>"
                                        data-desc="<?php echo htmlspecialchars($inc['description'] ?? ''); ?>"
                                        data-amount="<?php echo $inc['amount']; ?>"
                                        data-method="<?php echo htmlspecialchars($inc['payment_method']); ?>"
                                        data-remarks="<?php echo htmlspecialchars($inc['remarks'] ?? ''); ?>"
                                        title="Edit Income">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-delete-income" data-id="<?php echo $inc['id']; ?>" title="Delete">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Record Income Modal -->
<div class="modal fade" id="addIncomeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-circle-down me-2 text-success"></i>Record Manual Income</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="addIncomeForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_income">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Income Date *</label>
                            <input type="date" class="form-control form-control-sm" name="income_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Reference / Receipt No</label>
                            <input type="text" class="form-control form-control-sm" name="reference_no" placeholder="Leave blank for Auto-Gen">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Income Source Type *</label>
                        <select class="form-select form-select-sm" name="source" required>
                            <!-- Filter out Fee collection source since fees are automatic -->
                            <?php foreach ($sources as $src): if ($src === 'Student Fee Collection') continue; ?>
                                <option value="<?php echo $src; ?>"><?php echo $src; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Amount Received (Rs.) *</label>
                            <input type="number" class="form-control form-control-sm" name="amount" step="0.01" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Payment Mode *</label>
                            <select class="form-select form-select-sm" name="payment_method" required>
                                <option value="Cash">Cash Counter</option>
                                <option value="Bank">Bank Deposit</option>
                                <option value="Online">Online Transfer</option>
                                <option value="Cheque">Bank Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Income Title Description</label>
                        <input type="text" class="form-control form-control-sm" name="description" placeholder="e.g. Uniform sale of 2 items">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Internal Remarks</label>
                        <textarea class="form-control form-control-sm" name="remarks" rows="2" placeholder="Audit notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addIncomeForm" class="btn btn-sm btn-success px-4" id="btnAddIncome">Save Income</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Income Modal -->
<div class="modal fade" id="editIncomeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-pen me-2 text-primary"></i>Modify Income Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="editIncomeForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="edit_income">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Income Date *</label>
                            <input type="date" class="form-control form-control-sm" name="income_date" id="edit_date" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Reference / Voucher No</label>
                            <input type="text" class="form-control form-control-sm" name="reference_no" id="edit_ref" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Income Source Type *</label>
                        <select class="form-select form-select-sm" name="source" id="edit_source" required>
                            <?php foreach ($sources as $src): if ($src === 'Student Fee Collection') continue; ?>
                                <option value="<?php echo $src; ?>"><?php echo $src; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Amount (Rs.) *</label>
                            <input type="number" class="form-control form-control-sm" name="amount" id="edit_amount" step="0.01" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Payment Mode *</label>
                            <select class="form-select form-select-sm" name="payment_method" id="edit_method" required>
                                <option value="Cash">Cash Counter</option>
                                <option value="Bank">Bank Deposit</option>
                                <option value="Online">Online Transfer</option>
                                <option value="Cheque">Bank Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Income Title Description</label>
                        <input type="text" class="form-control form-control-sm" name="description" id="edit_desc">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Internal Remarks</label>
                        <textarea class="form-control form-control-sm" name="remarks" id="edit_remarks" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editIncomeForm" class="btn btn-sm btn-primary px-4" id="btnEditIncome">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Print Voucher Template (Hidden by default, printed via JS popup) -->
<div id="printVoucherSection" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 30px; border: 2px solid #000; width: 600px; margin: 0 auto; border-radius:10px;">
        <div style="text-align: center; border-bottom: 2px double #000; padding-bottom: 10px; margin-bottom: 20px;">
            <h2 style="margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h2>
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #555;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 10px 0 0 0; background: #eee; padding: 5px; border-radius: 5px;">INCOME RECEIPT VOUCHER</h4>
        </div>
        
        <table style="width: 100%; margin-bottom: 20px; font-size: 14px;">
            <tr>
                <td style="width: 50%;"><strong>Voucher No:</strong> <span id="v_ref"></span></td>
                <td style="width: 50%; text-align: right;"><strong>Date:</strong> <span id="v_date"></span></td>
            </tr>
        </table>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;">
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Income Source:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="v_source"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Description:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="v_desc"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Payment Method:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="v_method"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Collected By:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="v_by"></td>
            </tr>
            <?php if (!empty($settings['whatsapp_number'])): ?>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Helpline:</strong></td>
                <td style="padding: 10px 0; text-align: right;"><?php echo $settings['whatsapp_number']; ?></td>
            </tr>
            <?php endif; ?>
        </table>
        
        <div style="background: #f9f9f9; padding: 15px; text-align: center; border-radius: 5px; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #2e7d32;">TOTAL AMOUNT: Rs. <span id="v_amount"></span></h3>
        </div>
        
        <table style="width: 100%; margin-top: 50px; font-size: 12px; text-align: center;">
            <tr>
                <td style="width: 50%;"><div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">Depositor / Cashier</div></td>
                <td style="width: 50%;"><div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">Authorized Officer</div></td>
            </tr>
        </table>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="incToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="incToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("incToast");
    const m = document.getElementById("incToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Record Income
    const addForm = document.getElementById("addIncomeForm");
    if (addForm) {
        addForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnAddIncome");
            btn.disabled = true; btn.innerHTML = "Saving...";
            
            fetch("../../ajax/accounts.php", { method: "POST", body: new FormData(addForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Save Income"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "Save Income"; });
        });
    }

    // Bind Edit Modal
    document.querySelectorAll(".btn-edit-income").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("edit_id").value = this.dataset.id;
            document.getElementById("edit_date").value = this.dataset.date;
            document.getElementById("edit_ref").value = this.dataset.ref;
            document.getElementById("edit_source").value = this.dataset.source;
            document.getElementById("edit_amount").value = this.dataset.amount;
            document.getElementById("edit_method").value = this.dataset.method;
            document.getElementById("edit_desc").value = this.dataset.desc;
            document.getElementById("edit_remarks").value = this.dataset.remarks;
            
            new bootstrap.Modal(document.getElementById("editIncomeModal")).show();
        });
    });

    // Save Edit Form
    const editForm = document.getElementById("editIncomeForm");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnEditIncome");
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

    // Delete Income Action
    document.querySelectorAll(".btn-delete-income").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            if(!confirm("Are you sure you want to delete this income record?")) return;
            const fd = new FormData();
            fd.append("action", "delete_income");
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
            document.getElementById("v_ref").textContent = this.dataset.ref;
            document.getElementById("v_date").textContent = this.dataset.date;
            document.getElementById("v_source").textContent = this.dataset.source;
            document.getElementById("v_desc").textContent = this.dataset.desc || "Operational collection";
            document.getElementById("v_method").textContent = this.dataset.method;
            document.getElementById("v_by").textContent = this.dataset.by;
            document.getElementById("v_amount").textContent = this.dataset.amount;
            
            const printContent = document.getElementById("printVoucherSection").innerHTML;
            const originalContent = document.body.innerHTML;
            
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Income Voucher Print</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
