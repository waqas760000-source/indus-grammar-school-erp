<?php
/**
 * Indus Grammar School ERP - Communication settings Console
 * Version 4.0.0
 */

$pageTitle = 'Communication Settings';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('communication_send');

// Load model
require_once __DIR__ . '/../../models/CommSetting.php';

$settings = CommSetting::get();

?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-sliders text-primary me-2"></i>Gateway Settings</h3>
        <p class="text-muted small mb-0">Configure outbound SMTP mail server channels and SMS bulk API dispatch credentials.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="dashboard.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<div class="row g-4">
    <!-- Configuration Column -->
    <div class="col-lg-8">
        <form id="settingsForm" method="POST" action="../../ajax/communication.php">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

            <!-- SMS SECTION -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-mobile-screen me-2 text-primary"></i>SMS Gateway Configuration</h5>
                </div>
                <div class="card-body p-4 pt-2">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Active SMS Provider / Gateway</label>
                            <select class="form-select" name="sms_provider" id="smsProviderSelect">
                                <option value="test_mode" <?php echo ($settings['sms_provider'] ?? 'test_mode') === 'test_mode' ? 'selected' : ''; ?>>Test Mode (Simulated Gateway - No Cost)</option>
                                <option value="twilio" <?php echo ($settings['sms_provider'] ?? '') === 'twilio' ? 'selected' : ''; ?>>Twilio SMS API</option>
                                <option value="vonage" <?php echo ($settings['sms_provider'] ?? '') === 'vonage' ? 'selected' : ''; ?>>Vonage (Nexmo) SMS API</option>
                                <option value="clickatell" <?php echo ($settings['sms_provider'] ?? '') === 'clickatell' ? 'selected' : ''; ?>>Clickatell SMS Gateway</option>
                                <option value="local_pakistan" <?php echo ($settings['sms_provider'] ?? '') === 'local_pakistan' ? 'selected' : ''; ?>>Local Pakistani SMS Gateway (Jazz/Telenor/Veevotech)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Sender Mask / ID</label>
                            <input type="text" class="form-control" name="sms_sender_id" value="<?php echo htmlspecialchars($settings['sms_sender_id'] ?? ''); ?>" placeholder="e.g. INDUS-EDU">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Default Language</label>
                            <select class="form-select" name="sms_default_lang">
                                <option value="English" <?php echo ($settings['sms_default_lang'] ?? '') === 'English' ? 'selected' : ''; ?>>English</option>
                                <option value="Urdu" <?php echo ($settings['sms_default_lang'] ?? '') === 'Urdu' ? 'selected' : ''; ?>>Urdu</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Gateway API Key / Account SID</label>
                            <input type="password" class="form-control" name="sms_gateway_api_key" value="<?php echo htmlspecialchars($settings['sms_gateway_api_key'] ?? ''); ?>" placeholder="Enter API Key / SID">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Gateway API Secret / Auth Token</label>
                            <input type="password" class="form-control" name="sms_api_secret" value="<?php echo htmlspecialchars($settings['sms_api_secret'] ?? ''); ?>" placeholder="Enter API Secret / Auth Token">
                        </div>
                    </div>
                </div>
            </div>

            <!-- EMAIL SECTION -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-envelope me-2 text-success"></i>SMTP Mail Settings</h5>
                </div>
                <div class="card-body p-4 pt-2">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">SMTP Host</label>
                            <input type="text" class="form-control" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" placeholder="smtp.mailtrap.io">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">SMTP Port</label>
                            <input type="number" class="form-control" name="smtp_port" value="<?php echo (int)($settings['smtp_port'] ?? 587); ?>" placeholder="587">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">SMTP Encryption</label>
                            <select class="form-select" name="smtp_encryption">
                                <option value="tls" <?php echo ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">SMTP Username</label>
                            <input type="text" class="form-control" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" placeholder="Enter username">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">SMTP Password</label>
                            <input type="password" class="form-control" name="smtp_password" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>" placeholder="Enter password">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Default Sender Email</label>
                            <input type="email" class="form-control" name="default_sender_email" value="<?php echo htmlspecialchars($settings['default_sender_email'] ?? ''); ?>" placeholder="no-reply@indus.edu.pk">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Default Sender Display Name</label>
                            <input type="text" class="form-control" name="default_sender_name" value="<?php echo htmlspecialchars($settings['default_sender_name'] ?? ''); ?>" placeholder="Indus Grammar School">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACTIONS -->
            <div class="text-end mb-4">
                <button type="submit" class="btn btn-primary px-5 py-2"><i class="fa-solid fa-save me-2"></i>Save Settings</button>
            </div>
        </form>
    </div>

    <!-- Testing Connections panel -->
    <div class="col-lg-4">
        <!-- TEST SMS CARD -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-vial me-2 text-primary"></i>Test SMS gateway</h5>
            </div>
            <div class="card-body p-4 pt-2">
                <form id="testSmsForm" method="POST" action="../../ajax/communication.php">
                    <input type="hidden" name="action" value="test_sms">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Target Test Number</label>
                        <input type="text" class="form-control" name="test_phone" required placeholder="e.g. 03215550099">
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100 py-2"><i class="fa-solid fa-paper-plane me-2"></i>Send Test SMS</button>
                </form>
            </div>
        </div>

        <!-- TEST EMAIL CARD -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-vial me-2 text-success"></i>Test SMTP connection</h5>
            </div>
            <div class="card-body p-4 pt-2">
                <form id="testEmailForm" method="POST" action="../../ajax/communication.php">
                    <input type="hidden" name="action" value="test_email">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Target Test Email</label>
                        <input type="email" class="form-control" name="test_email" required placeholder="e.g. admin@example.com">
                    </div>
                    <button type="submit" class="btn btn-outline-success w-100 py-2"><i class="fa-solid fa-paper-plane me-2"></i>Send Test Email</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
document.getElementById("settingsForm").addEventListener("submit", function(e) {
    e.preventDefault();
    submitTestForm(this);
});

// Handle async form submissions for testing connections
document.getElementById("testSmsForm").addEventListener("submit", function(e) {
    e.preventDefault();
    submitTestForm(this);
});

document.getElementById("testEmailForm").addEventListener("submit", function(e) {
    e.preventDefault();
    submitTestForm(this);
});

function submitTestForm(form) {
    let formData = new FormData(form);
    fetch(form.action, {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
    })
    .catch(err => {
        alert("Verification request failed.");
    });
}
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
