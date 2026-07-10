<?php
/**
 * Indus Grammar School ERP - School-wide Announcements
 * Version 1.0.0
 */

$pageTitle = 'School Announcements';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('communication_send');

// Fetch announcements (using audit logs with action 'Announcement Created' as a database backing or simple file logs)
$announcements = [];
try {
    $db = Database::getConnection();
    $stmt = $db->query("SELECT * FROM audit_logs WHERE action = 'Announcement Send' ORDER BY created_at DESC LIMIT 30");
    $announcements = $stmt->fetchAll();
} catch (Exception $e) {}

if (isset($_POST['send_announcement'])) {
    if (!validateCsrf($_POST['csrf_token'] ?? '')) {
        $msg = 'CSRF Token expired.';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $body = sanitize($_POST['body'] ?? '');
        
        auditLog('Announcement Send', "Title: $title | Message: $body");
        echo "<script>alert('Announcement broadcasted successfully to all users!'); window.location.href='announcements.php';</script>";
        exit;
    }
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-bullhorn me-2 text-primary"></i>Announcements</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#announceModal">
            <i class="fa-solid fa-plus me-2"></i>New Announcement
        </button>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="custom-table-card shadow-sm border-0">
            <div class="p-4 border-bottom">
                <h5 class="fw-bold mb-0 text-secondary">Broadcast History</h5>
            </div>
            <div class="p-4">
                <?php if (empty($announcements)): ?>
                    <p class="text-muted text-center py-4 mb-0">No announcements posted yet.</p>
                <?php else: foreach ($announcements as $a): ?>
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <h6 class="fw-bold mb-1 text-dark"><?php 
                                preg_match('/Title: (.*?) \|/', $a['description'], $matches);
                                echo sanitize($matches[1] ?? 'ERP Notice');
                            ?></h6>
                            <small class="text-muted"><?php echo date('d M Y, h:i A', strtotime($a['created_at'])); ?></small>
                        </div>
                        <p class="text-muted small mb-0"><?php 
                            preg_match('/Message: (.*)/', $a['description'], $matches);
                            echo sanitize($matches[1] ?? $a['description']);
                        ?></p>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius:12px; background: linear-gradient(135deg, #fff, #eef2ff);">
            <div class="card-body p-4">
                <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-circle-info me-2"></i>ERP Broadcast Info</h5>
                <p class="small text-muted mb-0">Announcements created here will be logged to security audit trail logs and notified to staff members upon logging in next time.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="announceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-bullhorn me-2 text-primary"></i>Post Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="announceForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Title / Subject</label>
                        <input type="text" class="form-control" name="title" required placeholder="e.g. Summer Vacation Holidays">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Message Body</label>
                        <textarea class="form-control" name="body" rows="4" required placeholder="Write details here..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="announceForm" name="send_announcement" class="btn btn-primary px-4">Publish Announcement</button>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
