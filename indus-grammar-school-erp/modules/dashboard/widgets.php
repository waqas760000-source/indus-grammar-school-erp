<?php
/**
 * Indus Grammar School ERP - Widgets Dashboard Layout
 * Version 1.0.0
 */

$pageTitle = 'Dashboard Widgets';
$breadcrumbActive = 'Dashboard';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('dashboard_view');
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-shapes me-2 text-primary"></i>Dashboard Widgets</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="../../dashboard.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>Back to Main</a>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:12px;">
    <div class="card-body p-4 text-center text-muted">
        <i class="fa-solid fa-shapes fs-1 opacity-25 mb-3"></i>
        <h5>All active dashboard widgets are enabled and visible on the main homepage dashboard view.</h5>
        <a href="../../dashboard.php" class="btn btn-primary mt-3 px-4">Go to Dashboard</a>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
