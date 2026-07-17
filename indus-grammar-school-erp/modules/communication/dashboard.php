<?php
/**
 * Indus Grammar School ERP - Communication Hub Central Dashboard
 * Version 4.0.0
 */

$pageTitle = 'Communication Dashboard';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('communication_send');

$db = Database::getConnection();
$today = date('Y-m-d');

// Calculations for Dashboard Stats
$smsSentToday = (int)$db->query("SELECT COUNT(*) FROM sms_history WHERE DATE(created_at) = '$today' AND status = 'Sent'")->fetchColumn();
$emailSentToday = (int)$db->query("SELECT COUNT(*) FROM email_history WHERE DATE(created_at) = '$today' AND status = 'Sent'")->fetchColumn();
$activeAnnouncements = (int)$db->query("SELECT COUNT(*) FROM announcements WHERE status = 'Active' AND '$today' BETWEEN start_date AND end_date")->fetchColumn();
$activeCirculars = (int)$db->query("SELECT COUNT(*) FROM circulars WHERE '$today' BETWEEN issue_date AND expiry_date")->fetchColumn();

// Scheduled count (scheduled in the future)
$smsScheduled = (int)$db->query("SELECT COUNT(*) FROM sms_history WHERE status = 'Scheduled'")->fetchColumn();
$emailScheduled = (int)$db->query("SELECT COUNT(*) FROM email_history WHERE status = 'Scheduled'")->fetchColumn();
$scheduledTotal = $smsScheduled + $emailScheduled;

// Failed count
$smsFailed = (int)$db->query("SELECT COUNT(*) FROM sms_history WHERE status = 'Failed'")->fetchColumn();
$emailFailed = (int)$db->query("SELECT COUNT(*) FROM email_history WHERE status = 'Failed'")->fetchColumn();
$failedTotal = $smsFailed + $emailFailed;

?>

<style>
:root {
    --royal-blue: #1e3a8a;
    --royal-blue-light: #3b82f6;
    --royal-blue-soft: #eff6ff;
}
.quick-comm-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: 12px;
    border: 1px solid rgba(226, 232, 240, 0.8);
}
.quick-comm-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 20px rgba(30, 58, 138, 0.08);
    border-color: var(--royal-blue-light);
}
.comm-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
}
</style>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-comments text-primary me-2"></i>Communication Hub</h3>
        <p class="text-muted small mb-0">Centralized console to dispatch notifications, schedule SMS/Emails, and broadcast news bulletins.</p>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <!-- Stat 1 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-primary-soft p-3 rounded-3 me-3 text-primary">
                    <i class="fa-solid fa-message fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-1">SMS Sent Today</h6>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo $smsSentToday; ?></h3>
                </div>
            </div>
        </div>
    </div>
    <!-- Stat 2 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-success-soft p-3 rounded-3 me-3 text-success">
                    <i class="fa-solid fa-envelope-open-text fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-1">Email Sent Today</h6>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo $emailSentToday; ?></h3>
                </div>
            </div>
        </div>
    </div>
    <!-- Stat 3 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-info-soft p-3 rounded-3 me-3 text-info">
                    <i class="fa-solid fa-bullhorn fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-1">Active Notices</h6>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo $activeAnnouncements + $activeCirculars; ?></h3>
                    <small class="text-muted text-xs"><?php echo $activeAnnouncements; ?> announcements | <?php echo $activeCirculars; ?> circulars</small>
                </div>
            </div>
        </div>
    </div>
    <!-- Stat 4 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-warning-soft p-3 rounded-3 me-3 text-warning">
                    <i class="fa-solid fa-clock-rotate-left fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-1">Scheduled Queue</h6>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo $scheduledTotal; ?></h3>
                    <small class="text-danger text-xs"><?php echo $failedTotal; ?> failed dispatches</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Quick Links Menu Grid -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <h5 class="fw-bold text-secondary mb-4"><i class="fa-solid fa-compass me-2 text-primary"></i>Communication Submodules</h5>
        
        <div class="row g-3">
            <!-- SMS Composer -->
            <div class="col-md-6 col-xl-4">
                <div class="card quick-comm-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="comm-icon-box bg-primary-soft text-primary me-3">
                            <i class="fa-solid fa-mobile-screen"></i>
                        </div>
                        <div>
                            <a href="sms.php" class="fw-bold text-dark text-decoration-none d-block">SMS Dispatcher</a>
                            <small class="text-muted text-xs">Send text alerts to classes or custom phone numbers</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email Composer -->
            <div class="col-md-6 col-xl-4">
                <div class="card quick-comm-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="comm-icon-box bg-success-soft text-success me-3">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <div>
                            <a href="email.php" class="fw-bold text-dark text-decoration-none d-block">Email Broadcast</a>
                            <small class="text-muted text-xs">Dispatch professional emails with file attachments</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Announcements -->
            <div class="col-md-6 col-xl-4">
                <div class="card quick-comm-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="comm-icon-box bg-info-soft text-info me-3">
                            <i class="fa-solid fa-bullhorn"></i>
                        </div>
                        <div>
                            <a href="announcements.php" class="fw-bold text-dark text-decoration-none d-block">ERP Announcements</a>
                            <small class="text-muted text-xs">Post notices that appear directly on dashboard logins</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Circulars -->
            <div class="col-md-6 col-xl-4">
                <div class="card quick-comm-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="comm-icon-box bg-purple-soft text-purple me-3">
                            <i class="fa-solid fa-file-pdf"></i>
                        </div>
                        <div>
                            <a href="circulars.php" class="fw-bold text-dark text-decoration-none d-block">Printable Circulars</a>
                            <small class="text-muted text-xs">Issue formal PDF school notices for classes</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- History -->
            <div class="col-md-6 col-xl-4">
                <div class="card quick-comm-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="comm-icon-box bg-secondary-soft text-secondary me-3">
                            <i class="fa-solid fa-history"></i>
                        </div>
                        <div>
                            <a href="history.php" class="fw-bold text-dark text-decoration-none d-block">Communication History</a>
                            <small class="text-muted text-xs">Review outbox logs of all dispatches</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Templates -->
            <div class="col-md-6 col-xl-4">
                <div class="card quick-comm-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="comm-icon-box bg-warning-soft text-warning-dark me-3">
                            <i class="fa-solid fa-paste"></i>
                        </div>
                        <div>
                            <a href="templates.php" class="fw-bold text-dark text-decoration-none d-block">Message Templates</a>
                            <small class="text-muted text-xs">Define reusable outlines for fee/exam alerts</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings -->
            <div class="col-md-6 col-xl-4">
                <div class="card quick-comm-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="comm-icon-box bg-danger-soft text-danger me-3">
                            <i class="fa-solid fa-sliders"></i>
                        </div>
                        <div>
                            <a href="settings.php" class="fw-bold text-dark text-decoration-none d-block">Gateway Settings</a>
                            <small class="text-muted text-xs">Configure SMS gateway credentials and SMTP details</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
