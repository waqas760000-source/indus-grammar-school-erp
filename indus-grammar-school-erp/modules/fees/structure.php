<?php
/**
 * Indus Grammar School ERP - Fee Structure
 * Version 1.0.0
 */

$pageTitle = 'Fee Structure';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('fee_view');

$classes = SchoolClass::all();
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$structures = [];
$totalFee = 0;

if ($selectedClass > 0) {
    $structures = Fee::getStructuresByClass($selectedClass);
    $totalFee = array_sum(array_column($structures, 'amount'));
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-list-ul me-2 text-primary"></i>Fee Structure</h3>
    </div>
</div>

<div class="row g-4">
    <!-- Select Class -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <form method="GET" action="">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Select Class</label>
                        <select class="form-select" name="class_id" onchange="this.form.submit()">
                            <option value="">— Select Class —</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($selectedClass == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                
                <?php if ($selectedClass > 0 && hasPermission('fee_manage')): ?>
                    <hr class="my-4">
                    <h6 class="fw-bold text-secondary mb-3">Add Fee Head</h6>
                    <form id="addFeeForm">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action" value="save_structure">
                        <input type="hidden" name="class_id" value="<?php echo $selectedClass; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Fee Type</label>
                            <input type="text" class="form-control" name="fee_type" required placeholder="e.g. Tuition Fee">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Amount (Rs.)</label>
                            <input type="number" class="form-control" name="amount" min="0" step="0.01" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" id="btnSaveFee">
                            <i class="fa-solid fa-plus me-2"></i>Add Fee
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Fee Structure Table -->
    <div class="col-lg-8">
        <?php if ($selectedClass > 0): ?>
            <div class="custom-table-card shadow-sm border-0">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-secondary">
                        <?php 
                            $cInfo = SchoolClass::findById($selectedClass);
                            echo sanitize($cInfo['class_name'].' - '.$cInfo['section']);
                        ?> Fee Structure
                    </h5>
                    <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill fs-6">
                        Total: Rs. <?php echo number_format($totalFee, 2); ?>
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table custom-table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Fee Head / Type</th>
                                <th>Amount</th>
                                <th>Academic Year</th>
                                <?php if (hasPermission('fee_manage')): ?>
                                <th class="text-end">Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($structures)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No fee heads defined for this class.</td>
                                </tr>
                            <?php else: foreach ($structures as $s): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo sanitize($s['fee_type']); ?></td>
                                    <td>Rs. <?php echo number_format($s['amount'], 2); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo sanitize($s['academic_year']); ?></span></td>
                                    <?php if (hasPermission('fee_manage')): ?>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-danger btn-delete-fee" data-id="<?php echo $s['id']; ?>" title="Delete">
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
        <?php else: ?>
            <div class="card border-0 shadow-sm" style="border-radius:12px; height: 100%;">
                <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-5">
                    <i class="fa-solid fa-hand-pointer fs-1 text-muted opacity-50 mb-3"></i>
                    <h5 class="text-muted">Select a class to view or manage its fee structure.</h5>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="feeToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="feeToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("feeToast");
    const m = document.getElementById("feeToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("addFeeForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSaveFee");
            btn.disabled = true;
            btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Saving...\';
            
            fetch("../../ajax/fees.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = \'<i class="fa-solid fa-plus me-2"></i>Add Fee\'; }
                })
                .catch(() => {
                    showToast("Network error.", false);
                    btn.disabled = false; btn.innerHTML = \'<i class="fa-solid fa-plus me-2"></i>Add Fee\';
                });
        });
    }

    document.querySelectorAll(".btn-delete-fee").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this fee head?")) return;
            const id = this.dataset.id;
            const fd = new FormData();
            fd.append("action", "delete_structure");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/fees.php", { method: "POST", body: fd })
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
