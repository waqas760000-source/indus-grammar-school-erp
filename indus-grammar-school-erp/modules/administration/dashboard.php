<?php
/**
 * Indus Grammar School ERP - Admin Dashboard Submodule
 * Version 1.0.0
 */

$pageTitle = 'Admin Dashboard';
$breadcrumbActive = 'Administration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

$db = Database::getConnection();

// Fetch metrics
$totalUsers = 0;
$activeUsers = 0;
$inactiveUsers = 0;
$todayLogins = 0;
$latestBackupStr = 'None';

try {
    $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $activeUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
    $inactiveUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active = 0")->fetchColumn();
    
    // Count today's login activities from audit logs
    $todayLogins = (int)$db->query("
        SELECT COUNT(*) FROM audit_logs 
        WHERE (action = 'User Login' OR action LIKE '%login%') 
          AND DATE(created_at) = CURRENT_DATE
    ")->fetchColumn();
    
    // Fetch latest backup details
    $latestBackup = $db->query("SELECT created_at, backup_name FROM backup_history ORDER BY created_at DESC LIMIT 1")->fetch();
    if ($latestBackup) {
        $latestBackupStr = date('d M Y, h:i A', strtotime($latestBackup['created_at']));
    }
} catch (Exception $e) {
    error_log("Error in admin dashboard queries: " . $e->getMessage());
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-gauge-high me-2 text-primary"></i>Admin Dashboard</h3>
        <p class="text-muted small mb-0">Overview of the system users, health status, and administrative configurations.</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Total Users -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #0d6efd, #0b5ed7);">
            <div class="card-body p-4 text-white">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="text-white-50 text-uppercase small fw-bold mb-1">Total Users</h6>
                        <h2 class="fw-bold mb-0"><?php echo $totalUsers; ?></h2>
                    </div>
                    <div class="p-3 bg-white bg-opacity-10 rounded-3">
                        <i class="fa-solid fa-users fs-3"></i>
                    </div>
                </div>
                <div class="small text-white-50">Authorized accounts</div>
            </div>
        </div>
    </div>

    <!-- Active Users -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #198754, #157347);">
            <div class="card-body p-4 text-white">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="text-white-50 text-uppercase small fw-bold mb-1">Active Users</h6>
                        <h2 class="fw-bold mb-0"><?php echo $activeUsers; ?></h2>
                    </div>
                    <div class="p-3 bg-white bg-opacity-10 rounded-3">
                        <i class="fa-solid fa-user-check fs-3"></i>
                    </div>
                </div>
                <div class="small text-white-50">Active logins enabled</div>
            </div>
        </div>
    </div>

    <!-- Inactive Users -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #dc3545, #bb2d3b);">
            <div class="card-body p-4 text-white">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="text-white-50 text-uppercase small fw-bold mb-1">Inactive Users</h6>
                        <h2 class="fw-bold mb-0"><?php echo $inactiveUsers; ?></h2>
                    </div>
                    <div class="p-3 bg-white bg-opacity-10 rounded-3">
                        <i class="fa-solid fa-user-slash fs-3"></i>
                    </div>
                </div>
                <div class="small text-white-50">Archived/suspended accounts</div>
            </div>
        </div>
    </div>

    <!-- Today's Logins -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #ffc107, #ffb300);">
            <div class="card-body p-4 text-dark">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="text-dark-50 text-uppercase small fw-bold mb-1">Today's Logins</h6>
                        <h2 class="fw-bold mb-0"><?php echo $todayLogins; ?></h2>
                    </div>
                    <div class="p-3 bg-dark bg-opacity-10 rounded-3">
                        <i class="fa-solid fa-right-to-bracket fs-3"></i>
                    </div>
                </div>
                <div class="small text-dark-50">Unique activity counts</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- System Status & Details -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-4"><i class="fa-solid fa-server me-2"></i>System Status</h5>
                <div class="table-responsive">
                    <table class="table table-borderless align-middle mb-0">
                        <tbody>
                            <tr class="border-bottom border-light">
                                <td class="text-muted py-3">Environment State</td>
                                <td class="text-end fw-bold text-dark"><span class="badge bg-primary px-3 py-2 rounded-pill"><?php echo strtoupper(APP_ENV); ?></span></td>
                            </tr>
                            <tr class="border-bottom border-light">
                                <td class="text-muted py-3">Current Academic Session</td>
                                <td class="text-end fw-bold text-dark"><?php echo CURRENT_ACADEMIC_YEAR; ?></td>
                            </tr>
                            <tr class="border-bottom border-light">
                                <td class="text-muted py-3">Database Host Status</td>
                                <td class="text-end fw-bold text-success"><i class="fa-solid fa-circle-check me-1"></i>Connected (MySQL)</td>
                            </tr>
                            <tr class="border-bottom border-light">
                                <td class="text-muted py-3">System Timezone</td>
                                <td class="text-end fw-bold text-dark"><?php echo date_default_timezone_get(); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted py-3">Latest Snapshot Backup</td>
                                <td class="text-end fw-bold text-secondary"><?php echo $latestBackupStr; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Navigation Panels -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-4"><i class="fa-solid fa-circle-nodes me-2"></i>Administration Gateways</h5>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <a href="users.php" class="card text-decoration-none border border-light p-3 h-100 text-center hover-card" style="border-radius: 8px;">
                            <i class="fa-solid fa-users-cog fs-3 text-primary mb-2"></i>
                            <h6 class="fw-bold text-dark mb-0 small">User Management</h6>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="roles.php" class="card text-decoration-none border border-light p-3 h-100 text-center hover-card" style="border-radius: 8px;">
                            <i class="fa-solid fa-user-shield fs-3 text-danger mb-2"></i>
                            <h6 class="fw-bold text-dark mb-0 small">Roles & Permissions</h6>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="settings.php" class="card text-decoration-none border border-light p-3 h-100 text-center hover-card" style="border-radius: 8px;">
                            <i class="fa-solid fa-sliders fs-3 text-success mb-2"></i>
                            <h6 class="fw-bold text-dark mb-0 small">School Settings</h6>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="academic.php" class="card text-decoration-none border border-light p-3 h-100 text-center hover-card" style="border-radius: 8px;">
                            <i class="fa-solid fa-graduation-cap fs-3 text-warning mb-2"></i>
                            <h6 class="fw-bold text-dark mb-0 small">Academic Settings</h6>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hover-card {
    transition: all 0.25s ease;
}
.hover-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border-color: #0d6efd !important;
}
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
