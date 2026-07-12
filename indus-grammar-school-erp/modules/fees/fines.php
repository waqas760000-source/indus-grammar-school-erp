<?php
/**
 * Indus Grammar School ERP - Fee Fines Settings & Ledger Fine Adjustments
 * Version 4.0.0
 */

$pageTitle = 'Fee Fines';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('fee_view');

// Fetch current late fine settings
$settings = Fee::getSettings();

// Fetch pending fee ledger entries for fine adjustments
try {
    $db = Database::getConnection();
    $stmt = $db->query("
        SELECT fl.*, s.first_name, s.last_name, s.admission_no, c.class_name, c.section
        FROM fee_ledger fl
        JOIN students s ON fl.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE fl.status IN ('Pending', 'Partial')
        ORDER BY fl.due_date DESC
        LIMIT 50
    ");
    $pendingLedgers = $stmt->fetchAll();
} catch (Exception $e) {
    $pendingLedgers = [];
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>Fee Fines Management</h3>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Fine Settings Form -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-gear me-2 text-muted"></i>Late Fine Settings</h5>
                <form id="fineSettingsForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="update_fine_settings">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Late Fine Amount (Rs.)</label>
                        <input type="number" class="form-control text-danger fw-bold fs-5" name="late_fine_amount" min="0" step="0.01" value="<?php echo (float)$settings['late_fine_amount']; ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Grace Period (Days)</label>
                        <input type="number" class="form-control" name="grace_days" min="0" value="<?php echo (int)$settings['grace_days']; ?>" required>
                        <small class="text-muted">No late fine will be calculated if payment is within grace days.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Fine Calculation Type</label>
                        <select class="form-select" name="fine_type" required>
                            <option value="Fixed" <?php echo ($settings['fine_type'] === 'Fixed') ? 'selected' : ''; ?>>Fixed One-Time Fine</option>
                            <option value="Daily" <?php echo ($settings['fine_type'] === 'Daily') ? 'selected' : ''; ?>>Accumulate Daily Rate</option>
                            <option value="Monthly" <?php echo ($settings['fine_type'] === 'Monthly') ? 'selected' : ''; ?>>Accumulate Monthly Rate</option>
                        </select>
                    </div>

                    <?php if (hasPermission('fee_manage')): ?>
                    <button type="submit" class="btn btn-primary w-100" id="btnSaveSettings">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Save Settings
                    </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Pending Ledger Dues Fine Adjuster -->
    <div class="col-lg-8">
        <div class="custom-table-card shadow-sm border-0">
            <div class="p-4 border-bottom">
                <h5 class="fw-bold mb-0 text-secondary">Ledger Fine Adjuster</h5>
                <small class="text-muted">Directly apply manual penalties or waive fines on student's monthly ledger sheets.</small>
            </div>
            <div class="table-responsive">
                <table class="table custom-table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Admission No</th>
                            <th>Class</th>
                            <th>Month</th>
                            <th>Billed Fine</th>
                            <th>Payable</th>
                            <th>Status</th>
                            <?php if (hasPermission('fee_manage')): ?>
                            <th class="text-end">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingLedgers)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">No pending student ledger entries found.</td></tr>
                        <?php else: foreach ($pendingLedgers as $f): ?>
                            <tr id="row-<?php echo $f['id']; ?>">
                                <td class="fw-bold text-dark"><?php echo sanitize($f['first_name'] . ' ' . $f['last_name']); ?></td>
                                <td><code><?php echo sanitize($f['admission_no']); ?></code></td>
                                <td><?php echo sanitize($f['class_name'] . ' - ' . $f['section']); ?></td>
                                <td><strong><?php echo sanitize($f['month']); ?></strong></td>
                                <td class="fw-bold text-danger">Rs. <?php echo number_format($f['fine_amount'], 2); ?></td>
                                <td class="fw-bold text-dark">Rs. <?php echo number_format($f['total_payable'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $f['status'] === 'Partial' ? 'warning text-dark' : 'danger'; ?> rounded-pill small">
                                        <?php echo sanitize($f['status']); ?>
                                    </span>
                                </td>
                                <?php if (hasPermission('fee_manage')): ?>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-danger btn-add-penalty" data-id="<?php echo $f['id']; ?>" data-name="<?php echo sanitize($f['first_name'] . ' ' . $f['last_name']); ?>" title="Add Fine">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                        <?php if ((float)$f['fine_amount'] > 0): ?>
                                            <button class="btn btn-sm btn-outline-success btn-waive-penalty" data-id="<?php echo $f['id']; ?>" title="Waive Fine">
                                                <i class="fa-solid fa-gift"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Fine to Ledger -->
<?php if (hasPermission('fee_manage')): ?>
<div class="modal fade" id="addFineModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>Add Fine to Ledger</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="addFineForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="ledger_add_fine">
                    <input type="hidden" name="id" id="ledgerEntryId">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Student</label>
                        <input type="text" class="form-control bg-light" id="targetStudentName" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Penalty / Fine Amount (Rs.) *</label>
                        <input type="number" class="form-control text-danger fw-bold fs-5" name="amount" min="1" step="0.01" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addFineForm" class="btn btn-danger px-4" id="btnApplyPenalty">Add Fine</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="fineToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="fineToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("fineToast");
    const m = document.getElementById("fineToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Open add fine modal
    document.querySelectorAll(".btn-add-penalty").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("ledgerEntryId").value = this.dataset.id;
            document.getElementById("targetStudentName").value = this.dataset.name;
            new bootstrap.Modal(document.getElementById("addFineModal")).show();
        });
    });

    // Form Submission: Add Fine
    const addFineForm = document.getElementById("addFineForm");
    addFineForm?.addEventListener("submit", function(e) {
        e.preventDefault();
        
        const btn = document.getElementById("btnApplyPenalty");
        btn.disabled = true;
        btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Applying...\';

        fetch("../../ajax/fees.php", { method: "POST", body: new FormData(addFineForm) })
        .then(r => r.json())
        .then(data => {
            showToast(data.message, data.success);
            if (data.success) {
                setTimeout(() => location.reload(), 1000);
            } else {
                btn.disabled = false;
                btn.innerHTML = "Add Fine";
            }
        });
    });

    // Form Submission: Save Settings
    const settingsForm = document.getElementById("fineSettingsForm");
    settingsForm?.addEventListener("submit", function(e) {
        e.preventDefault();
        
        const btn = document.getElementById("btnSaveSettings");
        btn.disabled = true;
        btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Saving...\';

        fetch("../../ajax/fees.php", { method: "POST", body: new FormData(settingsForm) })
        .then(r => r.json())
        .then(data => {
            showToast(data.message, data.success);
            btn.disabled = false;
            btn.innerHTML = \'<i class="fa-solid fa-floppy-disk me-2"></i>Save Settings\';
        });
    });

    // Waive Fine
    document.querySelectorAll(".btn-waive-penalty").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            if (!confirm("Are you sure you want to waive all fines for this monthly ledger?")) return;
            
            const fd = new FormData();
            fd.append("action", "ledger_waive_fine");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/fees.php", { method: "POST", body: fd })
            .then(r => r.json())
            .then(data => {
                showToast(data.message, data.success);
                if (data.success) {
                    setTimeout(() => location.reload(), 1000);
                }
            });
        });
    });
});
</script>';

include_once __DIR__ . '/../../includes/footer.php';
?>
