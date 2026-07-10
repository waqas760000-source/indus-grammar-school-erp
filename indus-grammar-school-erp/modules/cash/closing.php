<?php
/**
 * Indus Grammar School ERP - Close Cash Register
 * Version 1.0.0
 */

$pageTitle = 'Close Cash Register';
$breadcrumbActive = 'Cash Desk';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_manage');

$register = Cash::getOpenRegister();
if (!$register) {
    echo "<script>window.location.href='opening_balance.php';</script>";
    exit;
}

$expectedClosing = $register['opening_balance'] + $register['total_collections'] - $register['total_expenses'];
?>

<div class="row justify-content-center mt-4">
    <div class="col-md-7 col-lg-6">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <div class="d-inline-block p-4 rounded-circle bg-danger-soft text-danger fs-1 mb-3">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <h4 class="fw-bold text-secondary mb-1">Close Cash Register / End Day</h4>
                    <p class="text-muted small">Verify actual physical drawer cash counter balance against expected system metrics.</p>
                </div>

                <div class="bg-light p-4 rounded mb-4 border d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-muted">Expected System Balance:</span>
                    <span class="fw-bold fs-4 text-primary">Rs. <?php echo number_format($expectedClosing, 2); ?></span>
                </div>

                <form id="closeRegisterForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="close_register">
                    
                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Actual Physical Cash Counted (PKR)</label>
                        <input type="number" class="form-control form-control-lg text-center fw-bold fs-3 text-success" name="physical_cash" min="0" step="0.01" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Closing Notes / Variance Reason (Optional)</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Describe any drawer cash variances or notes..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-danger btn-lg w-100 py-3 rounded-pill fw-bold" id="btnCloseReg">
                        <i class="fa-solid fa-power-off me-2"></i>Close Register & Save
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="clToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="clToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("clToast");
    const m = document.getElementById("clToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("closeRegisterForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnCloseReg");
            btn.disabled = true; btn.innerHTML = "Closing Register...";
            
            fetch("../../ajax/cash.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        setTimeout(() => window.location.href="reports.php", 1200);
                    } else { 
                        btn.disabled = false; btn.innerHTML = "Close Register & Save"; 
                    }
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
