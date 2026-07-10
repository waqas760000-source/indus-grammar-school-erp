<?php
/**
 * Indus Grammar School ERP - Cash Desk Dashboard
 * Version 1.0.0
 */

$pageTitle = 'Cash Desk Dashboard';
$breadcrumbActive = 'Cash Desk';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$register = Cash::getOpenRegister();

if (!$register) {
    // If no register is open for today, redirect to opening balance setup
    echo "<script>window.location.href='opening_balance.php';</script>";
    exit;
}

$expectedClosing = $register['opening_balance'] + $register['total_collections'] - $register['total_expenses'];
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-wallet me-2 text-primary"></i>Cash Desk</h3>
        <small class="text-muted">Register Date: <strong><?php echo date('d M Y', strtotime($register['date'])); ?></strong></small>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="closing.php" class="btn btn-danger px-4">
            <i class="fa-solid fa-lock me-2"></i>Close Register / End Day
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Opening Balance -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius:12px;">
            <h6 class="text-muted fw-semibold small mb-2">Opening Balance</h6>
            <h3 class="fw-bold text-dark mb-0">Rs. <?php echo number_format($register['opening_balance'], 2); ?></h3>
        </div>
    </div>
    <!-- Collections Today -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #f0fdf4);">
            <h6 class="text-muted fw-semibold small mb-2">Collections Today</h6>
            <h3 class="fw-bold text-success mb-0">+ Rs. <?php echo number_format($register['total_collections'], 2); ?></h3>
            <a href="collection.php" class="small text-success mt-2 d-inline-block text-decoration-none">View Details <i class="fa-solid fa-arrow-right ms-1"></i></a>
        </div>
    </div>
    <!-- Expenses Today -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #fef5f5);">
            <h6 class="text-muted fw-semibold small mb-2">Expenses Today</h6>
            <h3 class="fw-bold text-danger mb-0">- Rs. <?php echo number_format($register['total_expenses'], 2); ?></h3>
            <a href="expenses.php" class="small text-danger mt-2 d-inline-block text-decoration-none">Record Expense <i class="fa-solid fa-plus ms-1"></i></a>
        </div>
    </div>
    <!-- Expected Balance -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #eef2ff);">
            <h6 class="text-muted fw-semibold small mb-2">Expected Closing Balance</h6>
            <h3 class="fw-bold text-primary mb-0">Rs. <?php echo number_format($expectedClosing, 2); ?></h3>
            <a href="reconciliation.php" class="small text-primary mt-2 d-inline-block text-decoration-none">Reconcile Counter <i class="fa-solid fa-scale-balanced ms-1"></i></a>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-circle-info me-2"></i>Day Operations Info</h5>
                <p class="small text-muted mb-3">All fee collection transactions performed through the **Fee & Accounts** modules will automatically post increments here.</p>
                <div class="d-grid gap-2">
                    <a href="../fees/collection.php" class="btn btn-outline-primary"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Go to Fee Collection Screen</a>
                    <a href="reports.php" class="btn btn-outline-secondary"><i class="fa-solid fa-clock-rotate-left me-2"></i>View Historical Cash Registers</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
