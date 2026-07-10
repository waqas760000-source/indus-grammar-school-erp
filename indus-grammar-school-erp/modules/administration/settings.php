<?php
/**
 * Indus Grammar School ERP - School Settings
 * Version 1.0.0
 */

$pageTitle = 'School Settings';
$breadcrumbActive = 'Administration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-sliders me-2 text-primary"></i>School Settings</h3>
    </div>
</div>

<div class="row g-4">
    <!-- School Info Card -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-4"><i class="fa-solid fa-school-flag me-2"></i>School Information</h5>
                <form id="settingsForm" onsubmit="return false;">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">School Name</label>
                            <input type="text" class="form-control" value="<?php echo SCHOOL_NAME; ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Short Name / Code</label>
                            <input type="text" class="form-control" value="<?php echo SCHOOL_SHORT_NAME; ?>" readonly>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Owner / Director</label>
                            <input type="text" class="form-control" value="<?php echo SCHOOL_OWNER; ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Academic Year</label>
                            <input type="text" class="form-control" value="<?php echo CURRENT_ACADEMIC_YEAR; ?>" readonly>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email</label>
                            <input type="email" class="form-control" value="<?php echo SCHOOL_EMAIL; ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone</label>
                            <input type="text" class="form-control" value="<?php echo SCHOOL_PHONE; ?>" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Address</label>
                        <input type="text" class="form-control" value="<?php echo SCHOOL_ADDRESS; ?>" readonly>
                    </div>
                    <div class="alert alert-info border-0 shadow-sm small py-2 mt-4 mb-0">
                        <i class="fa-solid fa-circle-info me-2"></i>To update school settings, edit <code>config/constants.php</code> in the project files. Future versions will allow UI-based changes.
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-4"><i class="fa-solid fa-bolt me-2"></i>Quick Navigation</h5>
                <div class="d-grid gap-2">
                    <a href="users.php" class="btn btn-outline-primary text-start"><i class="fa-solid fa-users-cog me-2"></i>User Management</a>
                    <a href="logs.php" class="btn btn-outline-primary text-start"><i class="fa-solid fa-clipboard-list me-2"></i>Audit Logs</a>
                    <a href="backup.php" class="btn btn-outline-primary text-start"><i class="fa-solid fa-database me-2"></i>Database Backup</a>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm" style="border-radius:12px; background: linear-gradient(135deg, #fff, #f0fdf4);">
            <div class="card-body p-4 text-center">
                <div class="d-inline-block p-3 rounded-circle bg-success-soft mb-3">
                    <i class="fa-solid fa-shield-halved fs-2 text-success"></i>
                </div>
                <h5 class="fw-bold text-success mb-1">System Health</h5>
                <p class="small text-muted mb-2">All systems operational</p>
                <span class="badge bg-success rounded-pill px-3 py-2">Online</span>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
