<?php
/**
 * Indus Grammar School ERP - SMS Composer Placeholder
 * Version 1.0.0
 */

$pageTitle = 'SMS Dispatcher';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('communication_send');

if (isset($_POST['send_sms'])) {
    if (!validateCsrf($_POST['csrf_token'] ?? '')) {
        $msg = 'CSRF Token expired.';
    } else {
        $to = sanitize($_POST['to_phone'] ?? '');
        $msg = sanitize($_POST['message'] ?? '');
        
        auditLog('SMS Sent', "To: $to | Message: $msg");
        echo "<script>alert('SMS successfully dispatched (simulated)! Logged to security audit logs.'); window.location.href='sms.php';</script>";
        exit;
    }
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-message me-2 text-primary"></i>SMS Composer</h3>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Recipient Mobile Number</label>
                        <input type="text" class="form-control" name="to_phone" placeholder="e.g. 03215551234" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">SMS Message</label>
                        <textarea class="form-control" name="message" rows="4" maxlength="160" placeholder="Type SMS body here (max 160 characters)..." required></textarea>
                    </div>
                    <button type="submit" name="send_sms" class="btn btn-primary px-4 py-2 mt-2">
                        <i class="fa-solid fa-paper-plane me-2"></i>Send SMS (Simulation)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
