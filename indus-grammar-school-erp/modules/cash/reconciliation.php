<?php
/**
 * Indus Grammar School ERP - Cash Reconciliation Summary
 * Version 1.0.0
 */

$pageTitle = 'Cash Desk Reconciliation';
$breadcrumbActive = 'Cash Desk';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$register = Cash::getOpenRegister();
if (!$register) {
    echo "<script>window.location.href='opening_balance.php';</script>";
    exit;
}

$expectedClosing = $register['opening_balance'] + $register['total_collections'] - $register['total_expenses'];
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-scale-balanced me-2 text-primary"></i>Reconciliation Summary</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="dashboard.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>Back to Desk</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-4">Reconciliation Ledger (Today)</h5>
                
                <table class="table table-borderless custom-table">
                    <tbody>
                        <tr class="border-bottom">
                            <td class="text-muted fw-semibold">Opening Balance (Startup)</td>
                            <td class="text-end fw-semibold">Rs. <?php echo number_format($register['opening_balance'], 2); ?></td>
                        </tr>
                        <tr class="border-bottom text-success">
                            <td class="fw-semibold">Total Payments Collected In (+)</td>
                            <td class="text-end fw-bold">+ Rs. <?php echo number_format($register['total_collections'], 2); ?></td>
                        </tr>
                        <tr class="border-bottom text-danger">
                            <td class="fw-semibold">Total Deducted Expenses Out (-)</td>
                            <td class="text-end fw-bold">- Rs. <?php echo number_format($register['total_expenses'], 2); ?></td>
                        </tr>
                        <tr class="bg-light">
                            <td class="fw-bold text-dark pt-3 pb-3">Expected Closing Cash Balance</td>
                            <td class="text-end fw-bold text-primary pt-3 pb-3 fs-5">Rs. <?php echo number_format($expectedClosing, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="mt-4 alert alert-warning border-0 shadow-sm small">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>Verify the drawer contents. Any actual cash differences will be recorded as variance upon completing day closure.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
