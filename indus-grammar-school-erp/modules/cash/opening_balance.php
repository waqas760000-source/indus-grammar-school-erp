<?php
/**
 * Indus Grammar School ERP - Open Cash Register
 * Version 1.0.0
 */

$pageTitle = 'Open Cash Register';
$breadcrumbActive = 'Cash Desk';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_manage');

$register = Cash::getOpenRegister();
if ($register) {
    echo "<script>window.location.href='dashboard.php';</script>";
    exit;
}
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-5">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-5 text-center">
                <div class="d-inline-block p-4 rounded-circle bg-primary-soft text-primary fs-1 mb-4">
                    <i class="fa-solid fa-wallet"></i>
                </div>
                <h4 class="fw-bold text-secondary mb-2">Open Daily Cash Register</h4>
                <p class="text-muted small mb-4">Before recording payments or expenses, you must initialize the register with the opening cash balance in hand.</p>
                
                <form id="openRegisterForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="open_register">
                    
                    <div class="mb-4 text-start">
                        <label class="form-label small fw-semibold text-muted">Opening Cash Balance (PKR)</label>
                        <input type="number" class="form-control form-control-lg text-center fw-bold fs-4 text-primary" name="opening_balance" min="0" value="0.00" step="0.01" required>
                    </div>

                    <div class="mb-4 text-start">
                        <label class="form-label small fw-semibold text-muted">Notes / Drawer Memo (Optional)</label>
                        <input type="text" class="form-control" name="notes" placeholder="e.g. Counter drawer A startup">
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 py-3 rounded-pill fw-bold" id="btnOpenReg">
                        <i class="fa-solid fa-lock-open me-2"></i>Open Register
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="obToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="obToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("obToast");
    const m = document.getElementById("obToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("openRegisterForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnOpenReg");
            btn.disabled = true; btn.innerHTML = "Opening Register...";
            
            fetch("../../ajax/cash.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => window.location.href="dashboard.php", 1000);
                    else { btn.disabled = false; btn.innerHTML = "Open Register"; }
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
