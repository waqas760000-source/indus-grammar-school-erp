<?php
/**
 * Indus Grammar School ERP - Daily Cash Opening Balance Setup
 * Version 4.0.0
 */

$pageTitle = 'Cash Opening Setup';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// Check if a cash register has already been configured/opened today
$today = date('Y-m-d');
$openingRecord = null;
try {
    $stmt = $db->prepare("SELECT * FROM cash_opening WHERE date = :dt");
    $stmt->execute(['dt' => $today]);
    $openingRecord = $stmt->fetch();
} catch (Exception $e) {}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-folder-open me-2 text-primary"></i>Daily Cash Opening</h3>
        <p class="text-muted small mb-0">Initialize or modify the starting physical cash funds in the school drawer today.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card border-0 shadow-sm" style="border-radius: 12px;">
            <div class="card-body p-4">
                
                <?php if ($openingRecord): ?>
                    <!-- Opened State View -->
                    <div class="text-center py-3">
                        <div class="d-inline-block p-3 rounded-circle bg-success-soft mb-3">
                            <i class="fa-solid fa-circle-check fs-2 text-success"></i>
                        </div>
                        <h5 class="fw-bold text-success mb-1">Drawer Register Setup Complete</h5>
                        <p class="text-muted small">Daily register is active and collecting payments.</p>
                        
                        <div class="bg-light p-3 rounded border text-start mb-4">
                            <table class="table table-borderless table-sm mb-0 align-middle">
                                <tr>
                                    <td class="text-muted small">Register Date:</td>
                                    <td class="text-end fw-bold text-dark"><?php echo date('d M Y', strtotime($openingRecord['date'])); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">Drawer Opening Cash:</td>
                                    <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($openingRecord['opening_cash'], 2); ?></td>
                                </tr>
                                <?php if (!empty($openingRecord['remarks'])): ?>
                                <tr>
                                    <td class="text-muted small">Opening Remarks:</td>
                                    <td class="text-end text-muted small"><?php echo sanitize($openingRecord['remarks']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>

                        <!-- Allow modification of opening cash -->
                        <button class="btn btn-outline-primary w-100" id="btnToggleModify">
                            <i class="fa-solid fa-pen-to-square me-2"></i>Modify Starting Balance
                        </button>
                        <a href="dashboard.php" class="btn btn-primary w-100 mt-2">
                            Go to Accounts Dashboard <i class="fa-solid fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Setup Form -->
                <form id="openingCashForm" method="POST" class="<?php echo $openingRecord ? 'd-none' : ''; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_opening_cash">
                    
                    <div class="text-center mb-4">
                        <div class="p-3 bg-primary-soft rounded-circle d-inline-block mb-2">
                            <i class="fa-solid fa-wallet fs-3 text-primary"></i>
                        </div>
                        <h5 class="fw-bold text-secondary mb-1">Set Daily Starting Funds</h5>
                        <p class="text-muted small">Specify starting coins/currency notes inside the school counter today.</p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Register Setup Date</label>
                        <input type="text" class="form-control bg-light" value="<?php echo date('d F Y'); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Opening Cash Balance (Rs.) *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">Rs.</span>
                            <input type="number" class="form-control form-control-lg fw-bold" name="opening_cash" 
                                   value="<?php echo $openingRecord ? (float)$openingRecord['opening_cash'] : '0.00'; ?>" 
                                   step="0.01" min="0" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Internal Remarks / Notes</label>
                        <textarea class="form-control" name="remarks" rows="2" placeholder="e.g. Set counter currency bills"><?php echo $openingRecord ? htmlspecialchars($openingRecord['remarks'] ?? '') : ''; ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fs-6 py-2" id="btnSaveOpening">
                        <i class="fa-solid fa-save me-2"></i>Save & Initialize Desk
                    </button>
                    
                    <?php if ($openingRecord): ?>
                        <button type="button" class="btn btn-outline-secondary w-100 mt-2" id="btnCancelModify">
                            Cancel
                        </button>
                    <?php endif; ?>
                </form>

            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="opToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="opToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("opToast");
    const m = document.getElementById("opToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("openingCashForm");
    const toggleBtn = document.getElementById("btnToggleModify");
    const cancelBtn = document.getElementById("btnCancelModify");
    
    if (toggleBtn && form) {
        toggleBtn.addEventListener("click", function() {
            form.classList.remove("d-none");
            this.parentElement.classList.add("d-none");
        });
    }

    if (cancelBtn && form) {
        cancelBtn.addEventListener("click", function() {
            form.classList.add("d-none");
            document.querySelector(".text-center.py-3").classList.remove("d-none");
        });
    }

    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSaveOpening");
            btn.disabled = true; btn.innerHTML = "Initializing...";
            
            fetch("../../ajax/accounts.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-save me-2\"></i>Save & Initialize Desk"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-save me-2\"></i>Save & Initialize Desk"; });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
