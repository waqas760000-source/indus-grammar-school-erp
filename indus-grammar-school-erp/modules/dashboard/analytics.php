<?php
/**
 * Indus Grammar School ERP - Advanced Analytics Dashboard
 * Version 1.0.0
 */

$pageTitle = 'Academic & Operational Analytics';
$breadcrumbActive = 'Dashboard';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('dashboard_view');

$studentStats = Report::getStudentSummary();
$financeStats = Report::getFinanceSummary(date('Y-m-01'), date('Y-m-d'));
$classEnrollment = Report::getClasswiseEnrollment();
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-chart-line me-2 text-primary"></i>Advanced Analytics</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="../../dashboard.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>Back to Main</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Academic Ratios -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-users me-2 text-primary"></i>Student Status Overview</h5>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                        <span class="text-muted small fw-semibold">Active Students</span>
                        <span class="badge bg-success rounded-pill px-3 py-2"><?php echo $studentStats['active_count'] ?? 0; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                        <span class="text-muted small fw-semibold">Suspended Students</span>
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-2"><?php echo $studentStats['suspended_count'] ?? 0; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                        <span class="text-muted small fw-semibold">Graduated Students</span>
                        <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo $studentStats['graduated_count'] ?? 0; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                        <span class="text-muted small fw-semibold">Withdrawn Students</span>
                        <span class="badge bg-danger rounded-pill px-3 py-2"><?php echo $studentStats['withdrawn_count'] ?? 0; ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Financial Ratios -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-scale-balanced me-2 text-success"></i>Financial Performance Ratios</h5>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                        <span class="text-muted small fw-semibold">Monthly Collections Received</span>
                        <span class="fw-bold text-success">Rs. <?php echo number_format($financeStats['collections'], 2); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                        <span class="text-muted small fw-semibold">Monthly Office Expenses Paid</span>
                        <span class="fw-bold text-danger">Rs. <?php echo number_format($financeStats['expenses'], 2); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-3">
                        <span class="text-muted small fw-semibold">Outstanding Challans Value</span>
                        <span class="fw-bold text-warning">Rs. <?php echo number_format($financeStats['outstanding'], 2); ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
