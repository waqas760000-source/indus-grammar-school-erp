<?php
/**
 * Indus Grammar School ERP - WhatsApp Settings Console
 * Version 4.0.0
 */

$pageTitle = 'WhatsApp Settings';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('communication_send');

// Load model
require_once __DIR__ . '/../../models/WhatsAppSetting.php';
$settings = WhatsAppSetting::get();
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-sliders text-success me-2"></i>WhatsApp Settings</h3>
        <p class="text-muted small mb-0">Configure WhatsApp integration mode, Click-to-Chat parameters, or Official Cloud API credentials.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="whatsapp.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>WhatsApp Dispatcher</a>
    </div>
</div>

<div id="waSettingsAlertContainer" class="mb-3"></div>

<div class="row g-4">
    <div class="col-lg-8">
        <form id="waSettingsForm">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

            <!-- MODE SELECTOR CARD -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold text-secondary mb-0"><i class="fa-brands fa-whatsapp me-2 text-success"></i>WhatsApp Communication Mode</h5>
                </div>
                <div class="card-body p-4 pt-2">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Integration Dispatch Mode</label>
                        <select class="form-select" name="mode" id="waModeSelect" onchange="toggleWaModeFields()">
                            <option value="click_to_chat" <?php echo ($settings['mode'] ?? 'click_to_chat') === 'click_to_chat' ? 'selected' : ''; ?>>Free Click-to-Chat (Default - Zero Cost)</option>
                            <option value="official_api" <?php echo ($settings['mode'] ?? '') === 'official_api' ? 'selected' : ''; ?>>Official WhatsApp Business Cloud API (Meta API)</option>
                        </select>
                        <small class="text-muted text-xs d-block mt-2">
                            <i class="fa-solid fa-circle-info text-info me-1"></i>
                            <strong>Click-to-Chat:</strong> Uses official <code>https://wa.me</code> protocol without monthly charges or third-party TOS violations.<br>
                            <strong>Official API:</strong> Uses Meta WhatsApp Business Cloud API. Enter Meta developer app credentials below when ready.
                        </small>
                    </div>
                </div>
            </div>

            <!-- OFFICIAL API CREDENTIALS CARD -->
            <div class="card border-0 shadow-sm mb-4" id="officialApiCard" style="border-radius:12px; display:none;">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-key me-2 text-primary"></i>Official WhatsApp Business Cloud API Credentials</h5>
                </div>
                <div class="card-body p-4 pt-2">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Permanent Access Token</label>
                        <textarea class="form-control font-monospace text-xs" name="access_token" rows="3" placeholder="EAAG... (Meta System User Permanent Access Token)"><?php echo htmlspecialchars($settings['access_token'] ?? ''); ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Phone Number ID</label>
                            <input type="text" class="form-control" name="phone_number_id" value="<?php echo htmlspecialchars($settings['phone_number_id'] ?? ''); ?>" placeholder="e.g. 104592837482910">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">WhatsApp Business Account ID</label>
                            <input type="text" class="form-control" name="business_account_id" value="<?php echo htmlspecialchars($settings['business_account_id'] ?? ''); ?>" placeholder="e.g. 109827349102837">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold text-muted">Webhook Callback URL</label>
                            <input type="text" class="form-control" name="webhook_url" value="<?php echo htmlspecialchars($settings['webhook_url'] ?? (APP_URL . '/api/whatsapp_webhook.php')); ?>" placeholder="https://your-domain.com/api/whatsapp_webhook.php">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Graph API Version</label>
                            <input type="text" class="form-control" name="api_version" value="<?php echo htmlspecialchars($settings['api_version'] ?? 'v18.0'); ?>" placeholder="v18.0">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SUBMIT ACTION -->
            <div class="text-end mb-4">
                <button type="submit" class="btn btn-success px-5 py-2 fw-semibold"><i class="fa-solid fa-save me-2"></i>Save WhatsApp Settings</button>
            </div>
        </form>
    </div>

    <!-- Instructions Column -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-circle-question me-2 text-success"></i>Mode Explanation</h5>
            </div>
            <div class="card-body p-4 pt-2 text-xs">
                <div class="mb-3">
                    <h6 class="fw-bold text-success mb-1"><i class="fa-solid fa-check-circle me-1"></i>Free Click-to-Chat (Active)</h6>
                    <p class="text-muted mb-0">Generates instant pre-encoded <code>https://wa.me/92XXXXXXXXXX</code> links. Works on desktop WhatsApp Web or WhatsApp Mobile app with no gateway charges.</p>
                </div>
                <hr>
                <div>
                    <h6 class="fw-bold text-primary mb-1"><i class="fa-solid fa-cloud me-1"></i>WhatsApp Cloud API</h6>
                    <p class="text-muted mb-0">For automated background delivery directly through Meta Cloud servers. Enter your Meta App credentials when your business account is verified.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function toggleWaModeFields() {
    const mode = document.getElementById("waModeSelect").value;
    const card = document.getElementById("officialApiCard");
    card.style.display = (mode === "official_api") ? "block" : "none";
}

function showAlert(type, message) {
    const container = document.getElementById("waSettingsAlertContainer");
    container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid ${type === "success" ? "fa-circle-check" : "fa-triangle-exclamation"} me-2 fs-5"></i>
            <strong>${type === "success" ? "Success!" : "Notice:"}</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    window.scrollTo({ top: 0, behavior: "smooth" });
}

document.getElementById("waSettingsForm").addEventListener("submit", function(e) {
    e.preventDefault();
    let formData = new FormData(this);

    fetch("../../ajax/whatsapp.php", { method: "POST", body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.status === "success") {
                showAlert("success", res.message || "WhatsApp settings saved successfully.");
            } else {
                showAlert("danger", res.message || "Failed to save settings.");
            }
        })
        .catch(err => {
            showAlert("danger", "Network error while saving settings.");
        });
});

document.addEventListener("DOMContentLoaded", function() {
    toggleWaModeFields();
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
