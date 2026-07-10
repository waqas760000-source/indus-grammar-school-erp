<?php
/**
 * Indus Grammar School ERP - Email Composer Placeholder
 * Version 1.0.0
 */

$pageTitle = 'Email Dispatcher';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('communication_send');

if (isset($_POST['send_email'])) {
    if (!validateCsrf($_POST['csrf_token'] ?? '')) {
        $msg = 'CSRF Token expired.';
    } else {
        $to = sanitize($_POST['to_email'] ?? '');
        $sub = sanitize($_POST['subject'] ?? '');
        $msg = sanitize($_POST['message'] ?? '');
        
        auditLog('Email Sent', "To: $to | Subject: $sub");
        echo "<script>alert('Email successfully dispatched (simulated)! Logged to security audit logs.'); window.location.href='email.php';</script>";
        exit;
    }
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-envelope me-2 text-primary"></i>Email Composer</h3>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Recipient Email</label>
                        <input type="email" class="form-control" name="to_email" placeholder="e.g. parent@example.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Subject</label>
                        <input type="text" class="form-control" name="subject" placeholder="e.g. Attendance alert / Notification" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Message</label>
                        <textarea class="form-control" name="message" rows="5" placeholder="Type email message body here..." required></textarea>
                    </div>
                    <button type="submit" name="send_email" class="btn btn-primary px-4 py-2 mt-2">
                        <i class="fa-solid fa-paper-plane me-2"></i>Send Email (Simulation)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
