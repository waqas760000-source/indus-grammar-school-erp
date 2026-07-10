<?php
/**
 * Indus Grammar School ERP - Financial Summary Reports
 * Version 1.0.0
 */

$pageTitle = 'Financial Summary Report';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('report_view');

$dateFrom = sanitize($_GET['from'] ?? date('Y-m-01'));
$dateTo   = sanitize($_GET['to'] ?? date('Y-m-d'));

$summary = Report::getFinanceSummary($dateFrom, $dateTo);
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-chart-line me-2 text-primary"></i>Financial Summary Report</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-outline-primary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Report</button>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">From Date</label>
                <input type="date" class="form-control" name="from" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">To Date</label>
                <input type="date" class="form-control" name="to" value="<?php echo $dateTo; ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-sync me-2"></i>Generate Report</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Collections -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #f0fdf4);">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-muted fw-semibold small mb-0">Total Collections</h5>
                <i class="fa-solid fa-hand-holding-dollar text-success fs-3"></i>
            </div>
            <h2 class="fw-bold text-success">Rs. <?php echo number_format($summary['collections'], 2); ?></h2>
        </div>
    </div>
    <!-- Expenses -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #fef5f5);">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-muted fw-semibold small mb-0">Total Expenses</h5>
                <i class="fa-solid fa-money-bill-transfer text-danger fs-3"></i>
            </div>
            <h2 class="fw-bold text-danger">Rs. <?php echo number_format($summary['expenses'], 2); ?></h2>
        </div>
    </div>
    <!-- Net Profit -->
    <?php $net = $summary['collections'] - $summary['expenses']; ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, <?php echo $net >= 0 ? '#eef2ff' : '#fef5f5'; ?>);">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-muted fw-semibold small mb-0">Net Cash Flow</h5>
                <i class="fa-solid <?php echo $net >= 0 ? 'fa-piggy-bank text-primary' : 'fa-scale-unbalanced text-danger'; ?> fs-3"></i>
            </div>
            <h2 class="fw-bold text-<?php echo $net >= 0 ? 'primary' : 'danger'; ?>">Rs. <?php echo number_format($net, 2); ?></h2>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-receipt me-2"></i>Outstanding Outstanding Dues (Total Outstanding)</h5>
        <h3 class="fw-bold text-warning">Rs. <?php echo number_format($summary['outstanding'], 2); ?></h3>
        <p class="text-muted small mb-0">This represents all generated unpaid or overdue studentchallans remaining in system.</p>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
