<?php
/**
 * Indus Grammar School ERP - Payment Receipts (Collections Log)
 * Version 3.0.0
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
    'from'           => sanitize($_GET['from'] ?? date('Y-m-01')),
    'to'             => sanitize($_GET['to'] ?? date('Y-m-d')),
    'payment_method' => sanitize($_GET['payment_method'] ?? ''),
    'search'         => sanitize($_GET['search'] ?? '')
];

$collections = Fee::allCollections($filters, $limit, $offset);
$totalCollections = Fee::countCollections($filters);
$totalPages = ceil($totalCollections / $limit);

$totalInView = array_sum(array_column($collections, 'amount_paid'));
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-receipt me-2 text-primary"></i>Payment Receipts Ledger</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if (hasPermission('fee_collect')): ?>
        <a href="collection.php" class="btn btn-primary px-4 shadow-sm">
            <i class="fa-solid fa-plus me-2"></i>New Collection
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Search & Filters -->
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
                <label class="form-label small fw-semibold text-muted">Method</label>
                <select class="form-select" name="payment_method">
                    <option value="">All Methods</option>
                    <option value="Cash" <?php echo ($filters['payment_method'] === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                    <option value="Bank" <?php echo ($filters['payment_method'] === 'Bank') ? 'selected' : ''; ?>>Bank</option>
                    <option value="Online" <?php echo ($filters['payment_method'] === 'Online') ? 'selected' : ''; ?>>Online</option>
                    <option value="Cheque" <?php echo ($filters['payment_method'] === 'Cheque') ? 'selected' : ''; ?>>Cheque</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Search Query</label>
                <input type="text" class="form-control" name="search" placeholder="Receipt No, Student Name, Admn No..." value="<?php echo $filters['search']; ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-secondary">Historical Payments Log</h5>
        <span class="badge bg-success-soft text-success px-3 py-2 rounded-pill fs-6 fw-bold">
            Period Total: Rs. <?php echo number_format($totalInView, 2); ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Date Collected</th>
                    <th>Student Name</th>
                    <th>Admission No</th>
                    <th>Challan Ref</th>
                    <th>Payment Method</th>
                    <th class="text-end">Amount Paid</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($collections)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No payment collections found matching selection.</td></tr>
                <?php else: foreach ($collections as $c): ?>
                    <tr>
                        <td><span class="fw-bold text-dark"><?php echo sanitize($c['receipt_no']); ?></span></td>
                        <td><?php echo date('d M Y', strtotime($c['payment_date'])); ?></td>
                        <td class="fw-bold text-dark"><?php echo sanitize($c['first_name'] . ' ' . $c['last_name']); ?></td>
                        <td><code><?php echo sanitize($c['admission_no']); ?></code></td>
                        <td><span class="text-muted small"><?php echo sanitize($c['month'] ?: 'Direct Payment'); ?></span></td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <i class="fa-solid <?php 
                                    if ($c['payment_method'] === 'Cash') echo 'fa-money-bill text-success';
                                    elseif ($c['payment_method'] === 'Cheque') echo 'fa-money-check text-warning';
                                    else echo 'fa-building-columns text-primary';
                                ?> me-1"></i>
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

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="p-3 border-top bg-light d-flex justify-content-between align-items-center">
            <span class="text-muted small">Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> &middot; (Total Receipts: <?php echo $totalCollections; ?>)</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?>&from=<?php echo $filters['from']; ?>&to=<?php echo $filters['to']; ?>&payment_method=<?php echo urlencode($filters['payment_method']); ?>&search=<?php echo urlencode($filters['search']); ?>">Previous</a></li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&from=<?php echo $filters['from']; ?>&to=<?php echo $filters['to']; ?>&payment_method=<?php echo urlencode($filters['payment_method']); ?>&search=<?php echo urlencode($filters['search']); ?>"><?php echo $i; ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?>&from=<?php echo $filters['from']; ?>&to=<?php echo $filters['to']; ?>&payment_method=<?php echo urlencode($filters['payment_method']); ?>&search=<?php echo urlencode($filters['search']); ?>">Next</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
