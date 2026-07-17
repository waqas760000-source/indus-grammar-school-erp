<?php
/**
 * Indus Grammar School ERP - Bank Transactions Submodule
 * Version 4.0.0
 */

$pageTitle = 'Bank Transactions';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// Filters
$filterType = sanitize($_GET['type_filter'] ?? '');
$fromDate   = sanitize($_GET['from_date'] ?? '');
$toDate     = sanitize($_GET['to_date'] ?? '');

$where = " WHERE 1=1";
$params = [];

if ($filterType !== '') {
    $where .= " AND transaction_type = :type";
    $params['type'] = $filterType;
}
if ($fromDate !== '') {
    $where .= " AND date >= :from";
    $params['from'] = $fromDate;
}
if ($toDate !== '') {
    $where .= " AND date <= :to";
    $params['to'] = $toDate;
}

$transactions = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM bank_transactions
        $where
        ORDER BY date DESC, id DESC
    ");
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error loading bank transactions: " . $e->getMessage());
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-building-columns me-2 text-primary"></i>Bank Transactions</h3>
        <p class="text-muted small mb-0">Record all bank deposits and withdrawals to maintain school checkbooks and accounts.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#addBankModal">
            <i class="fa-solid fa-plus me-2"></i>Log Bank Transaction
        </button>
    </div>
</div>

<!-- Filters Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Transactions</h6>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Transaction Type</label>
                <select class="form-select form-select-sm" name="type_filter">
                    <option value="">All Types</option>
                    <option value="Deposit" <?php echo ($filterType === 'Deposit') ? 'selected' : ''; ?>>Deposits (+)</option>
                    <option value="Withdrawal" <?php echo ($filterType === 'Withdrawal') ? 'selected' : ''; ?>>Withdrawals (-)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">From Date</label>
                <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">To Date</label>
                <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i>Filter</button>
                <a href="bank.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table Grid -->
<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Ref ID</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Bank Name</th>
                    <th>Account Number</th>
                    <th>Reference No</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No bank transactions found matching filters.</td></tr>
                <?php else: foreach ($transactions as $tx): ?>
                    <tr>
                        <td><code class="fw-bold">TX-<?php echo str_pad($tx['id'], 6, '0', STR_PAD_LEFT); ?></code></td>
                        <td><span class="small fw-semibold"><?php echo date('d M Y', strtotime($tx['date'])); ?></span></td>
                        <td>
                            <?php
                            $badge = ($tx['transaction_type'] === 'Deposit') ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger';
                            $prefix = ($tx['transaction_type'] === 'Deposit') ? 'IN' : 'OUT';
                            ?>
                            <span class="badge <?php echo $badge; ?> px-3 py-2 rounded-pill fw-semibold"><?php echo sanitize($tx['transaction_type']); ?></span>
                        </td>
                        <td class="fw-bold text-dark"><?php echo sanitize($tx['bank_name']); ?></td>
                        <td><code><?php echo sanitize($tx['account_number']); ?></code></td>
                        <td><code><?php echo sanitize($tx['reference_number'] ?: '—'); ?></code></td>
                        <td class="fw-bold text-dark">Rs. <?php echo number_format($tx['amount'], 2); ?></td>
                        <td class="text-muted small" style="max-width:180px; text-wrap:balance;"><?php echo sanitize($tx['description'] ?: '—'); ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary btn-print-voucher" 
                                    data-ref="TX-<?php echo str_pad($tx['id'], 6, '0', STR_PAD_LEFT); ?>"
                                    data-date="<?php echo date('d M Y', strtotime($tx['date'])); ?>"
                                    data-type="<?php echo htmlspecialchars($tx['transaction_type']); ?>"
                                    data-bank="<?php echo htmlspecialchars($tx['bank_name']); ?>"
                                    data-account="<?php echo htmlspecialchars($tx['account_number']); ?>"
                                    data-refno="<?php echo htmlspecialchars($tx['reference_number'] ?? '—'); ?>"
                                    data-amount="<?php echo number_format($tx['amount'], 2); ?>"
                                    data-desc="<?php echo htmlspecialchars($tx['description'] ?? ''); ?>"
                                    title="Print Transaction Slip">
                                <i class="fa-solid fa-print"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary btn-edit-bank" 
                                    data-id="<?php echo $tx['id']; ?>"
                                    data-date="<?php echo $tx['date']; ?>"
                                    data-type="<?php echo htmlspecialchars($tx['transaction_type']); ?>"
                                    data-bank="<?php echo htmlspecialchars($tx['bank_name']); ?>"
                                    data-account="<?php echo htmlspecialchars($tx['account_number']); ?>"
                                    data-refno="<?php echo htmlspecialchars($tx['reference_number'] ?? ''); ?>"
                                    data-amount="<?php echo $tx['amount']; ?>"
                                    data-desc="<?php echo htmlspecialchars($tx['description'] ?? ''); ?>"
                                    title="Edit Transaction">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger btn-delete-bank" data-id="<?php echo $tx['id']; ?>" title="Delete">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Log Bank Transaction Modal -->
<div class="modal fade" id="addBankModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-building-columns me-2 text-primary"></i>Log Bank Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="addBankForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_bank_transaction">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Transaction Date *</label>
                            <input type="date" class="form-control form-control-sm" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Transaction Type *</label>
                            <select class="form-select form-select-sm" name="transaction_type" required>
                                <option value="Deposit">Deposit (+)</option>
                                <option value="Withdrawal">Withdrawal (-)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Bank Name *</label>
                            <input type="text" class="form-control form-control-sm" name="bank_name" placeholder="e.g. HBL Bank" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Account Number *</label>
                            <input type="text" class="form-control form-control-sm" name="account_number" placeholder="e.g. 1002-790123-01" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Amount (Rs.) *</label>
                            <input type="number" class="form-control form-control-sm" name="amount" step="0.01" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Cheque / Slip Ref Number</label>
                            <input type="text" class="form-control form-control-sm" name="reference_number" placeholder="e.g. CHQ-9011">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Transaction Description</label>
                        <textarea class="form-control form-control-sm" name="description" rows="2" placeholder="Describe the purpose of transfer..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addBankForm" class="btn btn-sm btn-primary px-4" id="btnAddBank">Log Transaction</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Bank Transaction Modal -->
<div class="modal fade" id="editBankModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-pen me-2 text-primary"></i>Modify Bank Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="editBankForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="edit_bank_transaction">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Transaction Date *</label>
                            <input type="date" class="form-control form-control-sm" name="date" id="edit_date" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Transaction Type *</label>
                            <select class="form-select form-select-sm" name="transaction_type" id="edit_type" required>
                                <option value="Deposit">Deposit (+)</option>
                                <option value="Withdrawal">Withdrawal (-)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Bank Name *</label>
                            <input type="text" class="form-control form-control-sm" name="bank_name" id="edit_bank" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Account Number *</label>
                            <input type="text" class="form-control form-control-sm" name="account_number" id="edit_account" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Amount (Rs.) *</label>
                            <input type="number" class="form-control form-control-sm" name="amount" id="edit_amount" step="0.01" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Cheque / Slip Ref Number</label>
                            <input type="text" class="form-control form-control-sm" name="reference_number" id="edit_refno">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Transaction Description</label>
                        <textarea class="form-control form-control-sm" name="description" id="edit_desc" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editBankForm" class="btn btn-sm btn-primary px-4" id="btnEditBank">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Print Slip structure template (Printed via popup Window JS) -->
<div id="printBankTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 30px; border: 2px solid #000; width: 600px; margin: 0 auto; border-radius:10px;">
        <div style="text-align: center; border-bottom: 2px double #000; padding-bottom: 10px; margin-bottom: 20px;">
            <h2 style="margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h2>
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #555;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 10px 0 0 0; background: #eee; padding: 5px; border-radius: 5px;">BANK TRANSACTION RECORD SLIP</h4>
        </div>
        
        <table style="width: 100%; margin-bottom: 20px; font-size: 14px;">
            <tr>
                <td><strong>Ref No:</strong> <span id="bp_ref"></span></td>
                <td style="text-align: right;"><strong>Date:</strong> <span id="bp_date"></span></td>
            </tr>
        </table>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;">
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Transaction Category Type:</strong></td>
                <td style="padding: 10px 0; text-align: right; font-weight: bold;" id="bp_type"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Bank Name:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="bp_bank"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Account Number:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="bp_account"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Slip / Cheque reference:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="bp_refno"></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Description Purpose:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="bp_desc"></td>
            </tr>
        </table>
        
        <div style="background: #f0f4f8; padding: 15px; text-align: center; border-radius: 5px; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #1e3a8a;">TRANSACTION VALUE: Rs. <span id="bp_amount"></span></h3>
        </div>
        
        <table style="width: 100%; margin-top: 50px; font-size: 12px; text-align: center;">
            <tr>
                <td style="width: 50%;"><div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">Disbursed / Handled By</div></td>
                <td style="width: 50%;"><div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">Accounts Manager</div></td>
            </tr>
        </table>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="bnkToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="bnkToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("bnkToast");
    const m = document.getElementById("bnkToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Add transaction
    const addForm = document.getElementById("addBankForm");
    if (addForm) {
        addForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnAddBank");
            btn.disabled = true; btn.innerHTML = "Logging...";
            
            fetch("../../ajax/accounts.php", { method: "POST", body: new FormData(addForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Log Transaction"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "Log Transaction"; });
        });
    }

    // Bind Edit Modal
    document.querySelectorAll(".btn-edit-bank").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("edit_id").value = this.dataset.id;
            document.getElementById("edit_date").value = this.dataset.date;
            document.getElementById("edit_type").value = this.dataset.type;
            document.getElementById("edit_bank").value = this.dataset.bank;
            document.getElementById("edit_account").value = this.dataset.account;
            document.getElementById("edit_refno").value = this.dataset.refno;
            document.getElementById("edit_amount").value = this.dataset.amount;
            document.getElementById("edit_desc").value = this.dataset.desc;
            
            new bootstrap.Modal(document.getElementById("editBankModal")).show();
        });
    });

    // Save Edit Form
    const editForm = document.getElementById("editBankForm");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnEditBank");
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

    // Delete Transaction Action
    document.querySelectorAll(".btn-delete-bank").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            if(!confirm("Are you sure you want to permanently delete this bank transaction log?")) return;
            const fd = new FormData();
            fd.append("action", "delete_bank_transaction");
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

    // Print Slip Action
    document.querySelectorAll(".btn-print-voucher").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("bp_ref").textContent = this.dataset.ref;
            document.getElementById("bp_date").textContent = this.dataset.date;
            document.getElementById("bp_type").textContent = this.dataset.type;
            document.getElementById("bp_bank").textContent = this.dataset.bank;
            document.getElementById("bp_account").textContent = this.dataset.account;
            document.getElementById("bp_refno").textContent = this.dataset.refno;
            document.getElementById("bp_desc").textContent = this.dataset.desc || "Deposit/Withdrawal";
            document.getElementById("bp_amount").textContent = this.dataset.amount;
            
            const printContent = document.getElementById("printBankTemplate").innerHTML;
            
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Bank Slip Print</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
