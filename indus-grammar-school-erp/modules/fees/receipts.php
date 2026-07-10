<?php
/**
 * Indus Grammar School ERP - Payment Receipts (Collections)
 * Version 1.0.0
 */

$pageTitle = 'Payment Receipts';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('fee_view');

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$filters = [
    'from' => sanitize($_GET['from'] ?? date('Y-m-01')),
    'to'   => sanitize($_GET['to'] ?? date('Y-m-d')),
];

$collections = Fee::allCollections($filters, $limit, $offset);
$totalInView = array_sum(array_column($collections, 'amount_paid'));
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-receipt me-2 text-primary"></i>Payment Receipts</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if (hasPermission('fee_collect')): ?>
        <a href="collection.php" class="btn btn-primary px-4">
            <i class="fa-solid fa-plus me-2"></i>New Collection
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">From Date</label>
                <input type="date" class="form-control" name="from" value="<?php echo $filters['from']; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">To Date</label>
                <input type="date" class="form-control" name="to" value="<?php echo $filters['to']; ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-2"></i>Filter</button>
            </div>
            <div class="col-md-4 text-md-end">
                <h5 class="fw-bold text-success mb-0 mt-3 mt-md-0">Period Total: Rs. <?php echo number_format($totalInView, 2); ?></h5>
            </div>
        </form>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Date</th>
                    <th>Student Name</th>
                    <th>Admission No</th>
                    <th>Method</th>
                    <th class="text-end">Amount Paid</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($collections)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No fee collections found for the selected period.</td></tr>
                <?php else: foreach ($collections as $c): ?>
                    <tr>
                        <td><span class="fw-bold text-dark"><?php echo sanitize($c['receipt_no']); ?></span></td>
                        <td><?php echo date('d M Y', strtotime($c['payment_date'])); ?></td>
                        <td class="fw-semibold text-dark"><?php echo sanitize($c['first_name'] . ' ' . $c['last_name']); ?></td>
                        <td><code class="text-muted"><?php echo sanitize($c['admission_no']); ?></code></td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <i class="fa-solid <?php echo $c['payment_method'] === 'Cash' ? 'fa-money-bill' : 'fa-building-columns'; ?> me-1 text-muted"></i>
                                <?php echo sanitize($c['payment_method']); ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold text-success">Rs. <?php echo number_format($c['amount_paid'], 2); ?></td>
                        <td class="text-end">
                            <a href="../../templates/receipt.php?receipt_no=<?php echo urlencode($c['receipt_no']); ?>" class="btn btn-sm btn-outline-primary" target="_blank" title="Print Receipt">
                                <i class="fa-solid fa-print"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Basic Pagination -->
    <?php if (count($collections) == $limit || $page > 1): ?>
        <div class="p-3 border-top bg-light d-flex justify-content-between align-items-center">
            <span class="text-muted small">Showing page <?php echo $page; ?></span>
            <div class="btn-group">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&from=<?php echo $filters['from']; ?>&to=<?php echo $filters['to']; ?>" class="btn btn-sm btn-outline-secondary">Previous</a>
                <?php endif; ?>
                <?php if (count($collections) == $limit): ?>
                    <a href="?page=<?php echo $page + 1; ?>&from=<?php echo $filters['from']; ?>&to=<?php echo $filters['to']; ?>" class="btn btn-sm btn-outline-secondary">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
