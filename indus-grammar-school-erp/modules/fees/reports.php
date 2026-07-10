<?php
/**
 * Indus Grammar School ERP - Financial Reports
 * Version 1.0.0
 */

$pageTitle = 'Financial Reports';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('fee_view'); // Account/Super Admin only

$dateFrom = sanitize($_GET['from'] ?? date('Y-m-01'));
$dateTo   = sanitize($_GET['to'] ?? date('Y-m-d'));

$cashSummary = Cash::getSummaryForRange($dateFrom, $dateTo);
$totalCollections = (float)($cashSummary['total_collections'] ?? 0);
$totalExpenses    = (float)($cashSummary['total_expenses'] ?? 0);
$netProfit        = $totalCollections - $totalExpenses;

// Recent register history for the range
try {
    $db = Database::getConnection();
    $stmt = $db->prepare("
        SELECT cr.*, u.username as closed_by_name 
        FROM cash_register cr 
        LEFT JOIN users u ON cr.closed_by = u.id 
        WHERE date BETWEEN :from AND :to 
        ORDER BY date DESC
    ");
    $stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
    $registerHistory = $stmt->fetchAll();
} catch (Exception $e) {
    $registerHistory = [];
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-chart-line me-2 text-primary"></i>Financial Reports</h3>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">From Date</label>
                <input type="date" class="form-control" name="from" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">To Date</label>
                <input type="date" class="form-control" name="to" value="<?php echo $dateTo; ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-sync me-2"></i>Generate</button>
            </div>
        </form>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-4 mb-4">
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #f0fdf4);">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="rounded-circle bg-success-soft p-3 me-3 text-success fs-3"><i class="fa-solid fa-arrow-trend-up"></i></div>
                <div>
                    <div class="small text-muted fw-semibold mb-1">Total Fee Collections</div>
                    <h3 class="fw-bold text-success mb-0">Rs. <?php echo number_format($totalCollections, 2); ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #fef5f5);">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="rounded-circle bg-danger-soft p-3 me-3 text-danger fs-3"><i class="fa-solid fa-arrow-trend-down"></i></div>
                <div>
                    <div class="small text-muted fw-semibold mb-1">Total Expenses</div>
                    <h3 class="fw-bold text-danger mb-0">Rs. <?php echo number_format($totalExpenses, 2); ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, <?php echo $netProfit >= 0 ? '#eef2ff' : '#fef5f5'; ?>);">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="rounded-circle bg-<?php echo $netProfit >= 0 ? 'primary' : 'danger'; ?>-soft p-3 me-3 text-<?php echo $netProfit >= 0 ? 'primary' : 'danger'; ?> fs-3">
                    <i class="fa-solid <?php echo $netProfit >= 0 ? 'fa-piggy-bank' : 'fa-scale-unbalanced'; ?>"></i>
                </div>
                <div>
                    <div class="small text-muted fw-semibold mb-1">Net Cash Flow</div>
                    <h3 class="fw-bold text-<?php echo $netProfit >= 0 ? 'primary' : 'danger'; ?> mb-0">Rs. <?php echo number_format($netProfit, 2); ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0 mb-4">
    <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-secondary">Daily Cash Register History</h5>
        <span class="badge bg-light text-dark border"><?php echo date('d M Y', strtotime($dateFrom)); ?> to <?php echo date('d M Y', strtotime($dateTo)); ?></span>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Date</th>
                    <th class="text-end">Opening Bal.</th>
                    <th class="text-end text-success">Collections In</th>
                    <th class="text-end text-danger">Expenses Out</th>
                    <th class="text-end">Closing Bal.</th>
                    <th>Status</th>
                    <th>Closed By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($registerHistory)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No register history for the selected date range.</td></tr>
                <?php else: foreach ($registerHistory as $r): 
                    $sysClosing = $r['opening_balance'] + $r['total_collections'] - $r['total_expenses'];
                    $actClosing = $r['closing_balance'] ?? $sysClosing;
                ?>
                    <tr>
                        <td class="fw-semibold text-dark"><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                        <td class="text-end">Rs. <?php echo number_format($r['opening_balance'], 2); ?></td>
                        <td class="text-end text-success fw-bold">+ <?php echo number_format($r['total_collections'], 2); ?></td>
                        <td class="text-end text-danger fw-bold">- <?php echo number_format($r['total_expenses'], 2); ?></td>
                        <td class="text-end fw-bold">
                            Rs. <?php echo number_format($actClosing, 2); ?>
                            <?php if ($r['status'] === 'Closed' && $actClosing != $sysClosing): ?>
                                <br><small class="text-danger"><i class="fa-solid fa-triangle-exclamation"></i> Var: <?php echo number_format($actClosing - $sysClosing, 2); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-soft-<?php echo $r['status'] === 'Open' ? 'success' : 'secondary'; ?> px-3 py-2 rounded-pill">
                                <?php echo sanitize($r['status']); ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?php echo sanitize($r['closed_by_name'] ?: '-'); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
