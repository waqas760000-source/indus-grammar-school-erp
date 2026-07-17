<?php
/**
 * Indus Grammar School ERP - Payroll Configuration Settings
 * Version 4.0.0
 */

$pageTitle = 'Payroll Settings';
$breadcrumbActive = 'Payroll';
include_once __DIR__ . '/../../includes/header.php';

// Restricted to Super Admin, School Admin
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN])) {
    $_SESSION['flash_error'] = 'Access denied. Only system administrators can configure payroll settings.';
    redirect(APP_URL . '/modules/staff/payroll.php');
}

$db = Database::getConnection();

// Fetch settings
try {
    $settings = $db->query("SELECT * FROM payroll_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        // Seed default if empty
        $db->exec("
            INSERT INTO payroll_settings (id, salary_payment_date, working_days_per_month, late_deduction_rule, half_day_rule, absent_deduction_rule, overtime_rate, currency, default_payment_method)
            VALUES (1, 10, 26, 0.25, 0.50, 1.00, 0.00, 'Rs.', 'Bank Transfer')
        ");
        $settings = $db->query("SELECT * FROM payroll_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $settings = [];
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-sliders me-2 text-secondary"></i>Payroll Settings</h3>
        <p class="text-muted small mb-0">Configure operational rules, monthly cycles, late/absent penalty ratios, and currency defaults.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="payroll.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 col-xl-6 mx-auto">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-gears me-2 text-primary"></i>Operational Rules & Ratios</h5>
            </div>
            <div class="card-body p-4">
                <form id="settingsForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_payroll_settings">

                    <!-- Working Cycle Configuration -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Default Payment Date (Day of Month)</label>
                            <select class="form-select" name="salary_payment_date" id="salary_payment_date" required>
                                <?php for($d=1; $d<=28; $d++): ?>
                                    <option value="<?php echo $d; ?>" <?php echo (int)($settings['salary_payment_date'] ?? 10) === $d ? 'selected' : ''; ?>><?php echo $d; ?>th of Month</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Standard Working Days/Month</label>
                            <input type="number" class="form-control" name="working_days_per_month" id="working_days_per_month" min="1" max="31" value="<?php echo (int)($settings['working_days_per_month'] ?? 26); ?>" required>
                        </div>
                    </div>

                    <!-- Fine Rules & Deductions -->
                    <h6 class="fw-bold text-primary mb-3 border-bottom pb-1"><i class="fa-solid fa-circle-minus me-2 text-danger"></i>Penalty Ratios (Per Day Base Wage)</h6>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Absent Deduction Rule</label>
                        <div class="input-group">
                            <input type="number" step="0.05" class="form-control" name="absent_deduction_rule" id="absent_deduction_rule" value="<?php echo (float)($settings['absent_deduction_rule'] ?? 1.00); ?>" required>
                            <span class="input-group-text small">x daily salary / absent day</span>
                        </div>
                        <small class="text-muted text-xs">E.g., 1.00 means deduct 1 full day wage per absent day. 1.50 means deduct 1.5 day wages.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Late Check-In Deduction Rule</label>
                        <div class="input-group">
                            <input type="number" step="0.05" class="form-control" name="late_deduction_rule" id="late_deduction_rule" value="<?php echo (float)($settings['late_deduction_rule'] ?? 0.25); ?>" required>
                            <span class="input-group-text small">x daily salary / late check-in</span>
                        </div>
                        <small class="text-muted text-xs">E.g., 0.25 means deduct 1/4th day wage per late check-in.</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Half-Day Departure Deduction Rule</label>
                        <div class="input-group">
                            <input type="number" step="0.05" class="form-control" name="half_day_rule" id="half_day_rule" value="<?php echo (float)($settings['half_day_rule'] ?? 0.50); ?>" required>
                            <span class="input-group-text small">x daily salary / half-day checkout</span>
                        </div>
                        <small class="text-muted text-xs">E.g., 0.50 means deduct 1/2 day wage per half-day occurrence.</small>
                    </div>

                    <!-- General Settings -->
                    <h6 class="fw-bold text-primary mb-3 border-bottom pb-1"><i class="fa-solid fa-coins me-2"></i>General Defaults</h6>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Currency Code</label>
                            <input type="text" class="form-control" name="currency" id="currency" value="<?php echo htmlspecialchars($settings['currency'] ?? 'Rs.'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Default Payment Method</label>
                            <select class="form-select" name="default_payment_method" id="default_payment_method" required>
                                <option value="Bank Transfer" <?php echo ($settings['default_payment_method'] ?? '') === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="Cash" <?php echo ($settings['default_payment_method'] ?? '') === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="Cheque" <?php echo ($settings['default_payment_method'] ?? '') === 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-muted">Overtime Hourly Rate (Rs.)</label>
                            <input type="number" step="0.01" class="form-control" name="overtime_rate" id="overtime_rate" value="<?php echo (float)($settings['overtime_rate'] ?? 0.00); ?>">
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-flex justify-content-between pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary px-4" id="btnReset"><i class="fa-solid fa-rotate-left me-1"></i>Reset defaults</button>
                        <button type="submit" class="btn btn-primary px-5 fw-bold" id="btnSave"><i class="fa-solid fa-circle-check me-2"></i>Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="settingsToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="settingsToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("settingsToast");
    const m = document.getElementById("settingsToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Save Form Trigger
    const form = document.getElementById("settingsForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSave");
            btn.disabled = true; btn.innerHTML = "Saving...";

            fetch("../../ajax/payroll.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    btn.disabled = false; btn.innerHTML = \'<i class=\"fa-solid fa-circle-check me-2\"></i>Save Settings\';
                })
                .catch(() => {
                    showToast("Failed to save operational settings due to server issue.", false);
                    btn.disabled = false; btn.innerHTML = \'<i class=\"fa-solid fa-circle-check me-2\"></i>Save Settings\';
                });
        });
    }

    // Reset Trigger
    const btnReset = document.getElementById("btnReset");
    if (btnReset) {
        btnReset.addEventListener("click", function() {
            if(!confirm("Are you sure you want to reset settings to default ratios? This will populate standard ERP settings (e.g. 10th of month, 26 work days, 0.25/0.50/1.00 fine rules).")) return;
            
            document.getElementById("salary_payment_date").value = "10";
            document.getElementById("working_days_per_month").value = "26";
            document.getElementById("absent_deduction_rule").value = "1.00";
            document.getElementById("late_deduction_rule").value = "0.25";
            document.getElementById("half_day_rule").value = "0.50";
            document.getElementById("currency").value = "Rs.";
            document.getElementById("default_payment_method").value = "Bank Transfer";
            document.getElementById("overtime_rate").value = "0.00";
            
            showToast("Form fields reset. Click Save Settings to persist defaults.", true);
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
