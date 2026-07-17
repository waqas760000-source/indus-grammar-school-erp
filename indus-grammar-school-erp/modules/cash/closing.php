<?php
/**
 * Indus Grammar School ERP - Daily Cash Closing Ledger
 * Version 4.0.0
 */

$pageTitle = 'Cash Counter Closing';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// Fetch daily counter status
$today = date('Y-m-d');
$register = null;
$closingRecord = null;
try {
    if (class_exists('Cash')) {
        $register = Cash::getByDate($today);
    }
    $stmt = $db->prepare("SELECT c.*, u.username as verifier_name FROM cash_closing c LEFT JOIN users u ON c.verified_by = u.id WHERE c.date = :dt");
    $stmt->execute(['dt' => $today]);
    $closingRecord = $stmt->fetch();
} catch (Exception $e) {}

// Calculate expected balances
$openingCash = 0.00;
$feeIncome = 0.00;
$otherIncome = 0.00;
$expensesTotal = 0.00;
$expectedCashInHand = 0.00;

try {
    // 1. Opening Cash
    $openingCash = (float)$db->query("SELECT COALESCE(SUM(opening_cash),0) FROM cash_opening WHERE date = '$today'")->fetchColumn();

    // 2. Fee Collection
    $feeIncome = (float)$db->query("
        SELECT COALESCE(SUM(amount), 0) FROM income 
        WHERE income_date = '$today' AND source = 'Student Fee Collection'
    ")->fetchColumn();

    // 3. Other Inflow Incomes (excl fee, only cash mode)
    $otherIncome = (float)$db->query("
        SELECT COALESCE(SUM(amount), 0) FROM income 
        WHERE income_date = '$today' AND source != 'Student Fee Collection' AND payment_method = 'Cash'
    ")->fetchColumn();

    // 4. Today's Cash Expenses
    $expensesTotal = (float)$db->query("
        SELECT COALESCE(SUM(amount), 0) FROM expenses 
        WHERE expense_date = '$today' AND payment_method = 'Cash'
    ")->fetchColumn();

    $expectedCashInHand = ($openingCash + $feeIncome + $otherIncome) - $expensesTotal;
} catch (Exception $e) {}

// Determine status
$isClosed = ($register && $register['status'] === 'Closed');
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-lock me-2 text-danger"></i>Cash Drawer Closing</h3>
        <p class="text-muted small mb-0">Close the daily cash counter register and log physical cash drawer balances.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-body p-4">
                
                <?php if ($isClosed && $closingRecord): ?>
                    <!-- Closed Register Info -->
                    <div class="text-center py-4">
                        <div class="d-inline-block p-3 rounded-circle bg-danger-soft mb-3">
                            <i class="fa-solid fa-lock-open fs-2 text-danger"></i>
                        </div>
                        <h5 class="fw-bold text-danger mb-1">Cash Desk Closed for Today</h5>
                        <p class="text-muted small">The daily register session is locked. All collections are archived.</p>
                        
                        <div class="bg-light p-3 rounded border text-start mb-4">
                            <table class="table table-borderless table-sm mb-0 align-middle">
                                <tr class="border-bottom border-light">
                                    <td class="text-muted py-2">Session Date:</td>
                                    <td class="text-end fw-bold text-dark"><?php echo date('d M Y', strtotime($closingRecord['date'])); ?></td>
                                </tr>
                                <tr class="border-bottom border-light">
                                    <td class="text-muted py-2">Opening Cash:</td>
                                    <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($closingRecord['opening_cash'], 2); ?></td>
                                </tr>
                                <tr class="border-bottom border-light">
                                    <td class="text-muted py-2">Today's Fee Inflow:</td>
                                    <td class="text-end fw-bold text-success">+ Rs. <?php echo number_format($closingRecord['fee_collection'], 2); ?></td>
                                </tr>
                                <tr class="border-bottom border-light">
                                    <td class="text-muted py-2">Today's Other Inflow (Cash):</td>
                                    <td class="text-end fw-bold text-success">+ Rs. <?php echo number_format($closingRecord['other_income'], 2); ?></td>
                                </tr>
                                <tr class="border-bottom border-light">
                                    <td class="text-muted py-2">Today's Cash Outflow:</td>
                                    <td class="text-end fw-bold text-danger">- Rs. <?php echo number_format($closingRecord['expenses'], 2); ?></td>
                                </tr>
                                <tr class="border-bottom border-light">
                                    <td class="text-muted py-2">Expected Cash:</td>
                                    <td class="text-end fw-bold text-primary">Rs. <?php echo number_format($closingRecord['cash_in_hand'], 2); ?></td>
                                </tr>
                                <tr class="border-bottom border-light">
                                    <td class="text-muted py-2">Physical Cash Counted:</td>
                                    <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($closingRecord['closing_balance'], 2); ?></td>
                                </tr>
                                <tr class="border-bottom border-light">
                                    <td class="text-muted py-2">Counter Difference:</td>
                                    <?php $dCls = ($closingRecord['difference'] < 0) ? 'text-danger' : (($closingRecord['difference'] > 0) ? 'text-success' : 'text-muted'); ?>
                                    <td class="text-end fw-bold <?php echo $dCls; ?>">Rs. <?php echo number_format($closingRecord['difference'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted py-2">Closed / Verified By:</td>
                                    <td class="text-end fw-semibold text-dark"><?php echo sanitize($closingRecord['verifier_name'] ?: 'System Admin'); ?></td>
                                </tr>
                            </table>
                        </div>

                        <button class="btn btn-outline-primary px-4 py-2 me-2" id="btnPrintClosing">
                            <i class="fa-solid fa-print me-2"></i>Print Closing Report
                        </button>
                        <a href="dashboard.php" class="btn btn-primary px-4 py-2">
                            Accounts Dashboard
                        </a>
                    </div>

                <?php else: ?>
                    <!-- Closing Calculator Setup -->
                    <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-calculator me-2"></i>Daily Register Reconciliation</h5>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light">
                                <small class="text-muted fw-semibold">Opening Cash (A)</small>
                                <h5 class="fw-bold text-dark mb-0">Rs. <?php echo number_format($openingCash, 2); ?></h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light">
                                <small class="text-muted fw-semibold">Expected In Hand (B)</small>
                                <h5 class="fw-bold text-primary mb-0">Rs. <?php echo number_format($expectedCashInHand, 2); ?></h5>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 border rounded bg-light">
                                <small class="text-muted fw-semibold">Fee Collection</small>
                                <h6 class="fw-bold text-success mb-0">+ Rs. <?php echo number_format($feeIncome, 2); ?></h6>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 border rounded bg-light">
                                <small class="text-muted fw-semibold">Other Income (Cash)</small>
                                <h6 class="fw-bold text-success mb-0">+ Rs. <?php echo number_format($otherIncome, 2); ?></h6>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 border rounded bg-light">
                                <small class="text-muted fw-semibold">Cash Expenses</small>
                                <h6 class="fw-bold text-danger mb-0">- Rs. <?php echo number_format($expensesTotal, 2); ?></h6>
                            </div>
                        </div>
                    </div>

                    <form id="closingCashForm" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action" value="save_closing_cash">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Actual Physical Cash Counted (Rs.) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">Rs.</span>
                                <input type="number" class="form-control form-control-lg fw-bold" name="closing_balance" id="closing_balance" step="0.01" min="0" required placeholder="0.00">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Reconciliation Difference</label>
                            <input type="text" class="form-control bg-light fw-bold" id="cash_diff_display" value="Rs. 0.00" readonly>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-muted">Audit Verification Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2" placeholder="e.g. Counter counts verified and matches expected ledger."></textarea>
                        </div>

                        <button type="submit" class="btn btn-danger btn-lg w-100 fs-6 py-2" id="btnCloseCounter">
                            <i class="fa-solid fa-lock me-2"></i>Save & Close Daily Counter
                        </button>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- Print Report Template (Hidden, printed via JS popup) -->
<div id="printClosingSection" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 30px; border: 2px solid #000; width: 600px; margin: 0 auto; border-radius:10px;">
        <div style="text-align: center; border-bottom: 2px double #000; padding-bottom: 10px; margin-bottom: 20px;">
            <h2 style="margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h2>
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #555;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 10px 0 0 0; background: #eee; padding: 5px; border-radius: 5px;">DAILY CASH COUNTER CLOSING STATEMENT</h4>
        </div>
        
        <table style="width: 100%; margin-bottom: 20px; font-size: 14px;">
            <tr>
                <td><strong>Report Date:</strong> <?php echo date('d M Y'); ?></td>
                <td style="text-align: right;"><strong>Status:</strong> CLOSED</td>
            </tr>
        </table>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;">
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Opening Cash Drawer:</strong></td>
                <td style="padding: 10px 0; text-align: right;">Rs. <?php echo number_format($closingRecord['opening_cash'] ?? $openingCash, 2); ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Today Student Fees Collected (Cash):</strong></td>
                <td style="padding: 10px 0; text-align: right;">Rs. <?php echo number_format($closingRecord['fee_collection'] ?? $feeIncome, 2); ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Today Other manual Incomes (Cash):</strong></td>
                <td style="padding: 10px 0; text-align: right;">Rs. <?php echo number_format($closingRecord['other_income'] ?? $otherIncome, 2); ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Today Operational Expenses (Cash):</strong></td>
                <td style="padding: 10px 0; text-align: right; color:#c62828;">- Rs. <?php echo number_format($closingRecord['expenses'] ?? $expensesTotal, 2); ?></td>
            </tr>
            <tr style="border-bottom: 2px solid #000; background: #f9f9f9;">
                <td style="padding: 10px; font-weight: bold;">Expected Ending Cash:</td>
                <td style="padding: 10px; text-align: right; font-weight: bold;">Rs. <?php echo number_format($closingRecord['cash_in_hand'] ?? $expectedCashInHand, 2); ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Physical Counted Cash:</strong></td>
                <td style="padding: 10px 0; text-align: right; font-weight: bold;">Rs. <?php echo number_format($closingRecord['closing_balance'] ?? 0, 2); ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 10px 0;"><strong>Drawer Discrepancy Difference:</strong></td>
                <td style="padding: 10px 0; text-align: right; font-weight: bold;" id="p_diff">Rs. <?php echo number_format($closingRecord['difference'] ?? 0, 2); ?></td>
            </tr>
        </table>
        
        <div style="font-size: 13px; color: #555; margin-bottom: 40px;">
            <strong>Audit Remarks:</strong> <?php echo sanitize($closingRecord['remarks'] ?? 'Daily counter closed.'); ?>
        </div>
        
        <table style="width: 100%; margin-top: 50px; font-size: 12px; text-align: center;">
            <tr>
                <td style="width: 50%;"><div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">Cashier Signature</div></td>
                <td style="width: 50%;"><div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">Audited & Verified By</div></td>
            </tr>
        </table>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="clsToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="clsToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("clsToast");
    const m = document.getElementById("clsToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    const expected = ' . $expectedCashInHand . ';
    const valInput = document.getElementById("closing_balance");
    const diffDisplay = document.getElementById("cash_diff_display");

    // Dynamic difference calculation
    if (valInput && diffDisplay) {
        valInput.addEventListener("input", function() {
            const val = parseFloat(this.value) || 0;
            const diff = val - expected;
            const formatted = diff.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (diff < 0) {
                diffDisplay.value = "Shortage: - Rs. " + Math.abs(diff).toFixed(2);
                diffDisplay.style.color = "#dc3545";
            } else if (diff > 0) {
                diffDisplay.value = "Excess: + Rs. " + diff.toFixed(2);
                diffDisplay.style.color = "#198754";
            } else {
                diffDisplay.value = "Balanced: Rs. 0.00";
                diffDisplay.style.color = "#555";
            }
        });
    }

    // Submit Closing Form
    const form = document.getElementById("closingCashForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnCloseCounter");
            if(!confirm("Are you sure you want to lock the counter register? All subsequent collections will require starting a new register.")) return;
            btn.disabled = true; btn.innerHTML = "Closing Drawer...";
            
            fetch("../../ajax/accounts.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-lock me-2\"></i>Save & Close Daily Counter"; }
                })
                .catch(() => { showToast("Network communication error.", false); btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-lock me-2\"></i>Save & Close Daily Counter"; });
        });
    }

    // Print Closing Report Action
    const printBtn = document.getElementById("btnPrintClosing");
    if (printBtn) {
        printBtn.addEventListener("click", function() {
            const printContent = document.getElementById("printClosingSection").innerHTML;
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Closing Statement Print</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
