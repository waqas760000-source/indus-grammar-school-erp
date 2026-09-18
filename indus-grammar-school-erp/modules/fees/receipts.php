<?php
/**
 * Indus Grammar School ERP - Payment Receipts Ledger & Transaction History
 * Redesigned Premium UI & Financial Receipts Workspace
 * Version 4.0.0
 */

require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('fee_view');

$pageTitle = 'Payment Receipts Ledger';
$breadcrumbActive = 'Payment Receipts';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/navbar.php';

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

$rangeTotalSum = array_sum(array_column($collections, 'amount_paid'));
$avgReceiptVal = count($collections) > 0 ? ($rangeTotalSum / count($collections)) : 0.00;

// Fetch today's collection metrics
$todayCollectionTotal = 0.00;
try {
    $db = Database::getConnection();
    $todayCollectionTotal = (float)($db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE payment_date = CURDATE()")->fetchColumn() ?? 0);
} catch (Exception $e) {}
?>

<!-- Custom Premium ERP Receipts Theme -->
<style>
:root {
    --primary-navy: #0F172A;
    --primary-blue: #1D4ED8;
    --secondary-blue: #2563EB;
    --hover-blue: #1E40AF;
    --light-blue: #EFF6FF;
    --page-background: #F1F5F9;
    --card-white: #FFFFFF;
    --border-color: #E2E8F0;
    --main-text: #1E293B;
    --muted-text: #64748B;
    --success: #16A34A;
    --success-bg: #F0FDF4;
    --warning: #D97706;
    --warning-bg: #FFFBEB;
    --danger: #DC2626;
    --danger-bg: #FEF2F2;
    --purple: #7C3AED;
    --purple-bg: #F5F3FF;
}

/* Page Canvas Wrapper */
.receipts-wrapper {
    background-color: var(--page-background);
    border-radius: 18px;
    padding: 1.5rem;
    min-height: calc(100vh - 90px);
}

/* Hero Banner */
.adv-hero-banner {
    background: linear-gradient(135deg, var(--primary-navy) 0%, #1e3a8a 55%, var(--secondary-blue) 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2);
    position: relative;
    overflow: hidden;
}

.adv-hero-banner::after {
    content: '';
    position: absolute;
    right: -40px;
    bottom: -40px;
    width: 260px;
    height: 260px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
    pointer-events: none;
}

.hero-icon-box {
    width: 58px;
    height: 58px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: #ffffff;
}

/* Top KPI Cards */
.top-kpi-card {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    transition: all 0.25s ease;
    height: 100%;
}

.top-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.07);
}

.kpi-icon-avatar {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}

/* Standard Styled Cards */
.card-custom {
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    background: var(--card-white);
    transition: all 0.25s ease-in-out;
}

.card-header-custom {
    background: transparent;
    border-bottom: 1px solid #f1f5f9;
    padding: 1.25rem 1.5rem;
}

/* Table Custom Styling */
.table-custom-header th {
    background-color: var(--primary-navy);
    color: #f8fafc;
    font-weight: 600;
    font-size: 0.825rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.9rem 1rem;
    border: none;
}

.table-custom-body td {
    padding: 0.9rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.925rem;
}

.student-avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e2e8f0;
}
.student-avatar-placeholder-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e3a8a, #2563eb);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 700;
}

@media print {
    body { background: #ffffff !important; }
    .receipts-wrapper { padding: 0 !important; background: transparent !important; }
    .adv-hero-banner, .top-kpi-card, nav, .search-filter-card, .btn-print-hide, .pagination { display: none !important; }
    .card-custom { border: none !important; box-shadow: none !important; }
}
</style>

<div class="receipts-wrapper">
    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3 btn-print-hide">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/index.php" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item text-muted">Fee Management</li>
            <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Payment Receipts Ledger</li>
        </ol>
    </nav>

    <!-- Hero Header Banner -->
    <div class="adv-hero-banner mb-4 btn-print-hide">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold mb-0 text-white fs-3">Payment Receipts Ledger</h3>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">Transactions Log</span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">Search, filter, and inspect historical fee payment receipts and financial collection transaction logs.</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if (hasPermission('fee_collect')): ?>
                    <a href="collection.php" class="btn btn-sm btn-light text-primary rounded-2 px-3 py-2 fw-semibold shadow-sm"><i class="fa-solid fa-plus me-1"></i>New Fee Collection</a>
                <?php endif; ?>
                <button type="button" id="btnExportCSV" class="btn btn-sm btn-outline-light rounded-2 px-3 py-2 fw-semibold"><i class="fa-solid fa-file-csv me-1"></i>Export CSV</button>
                <button type="button" onclick="window.print();" class="btn btn-sm btn-outline-light rounded-2 px-3 py-2 fw-semibold"><i class="fa-solid fa-print me-1"></i>Print Ledger</button>
            </div>
        </div>
    </div>

    <!-- Top Executive KPI Metric Cards -->
    <div class="row g-3 mb-4 btn-print-hide">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="top-kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Filtered Collection Total</span>
                    <h3 class="fw-bold text-success mb-0 fs-4">Rs. <?php echo number_format($rangeTotalSum, 2); ?></h3>
                    <span class="badge bg-success-subtle text-success px-2 py-0.5 rounded-pill small mt-1 fw-semibold"><i class="fa-solid fa-arrow-trend-up me-1"></i>In-View Ledger</span>
                </div>
                <div class="kpi-icon-avatar bg-success-subtle text-success">
                    <i class="fa-solid fa-vault"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="top-kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Total Receipts Issued</span>
                    <h3 class="fw-bold text-dark mb-0 fs-4"><?php echo $totalCollections; ?> Receipts</h3>
                    <span class="badge bg-primary-subtle text-primary px-2 py-0.5 rounded-pill small mt-1 fw-semibold"><i class="fa-solid fa-receipt me-1"></i>Receipts Count</span>
                </div>
                <div class="kpi-icon-avatar bg-primary-subtle text-primary">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="top-kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Today's Live Collection</span>
                    <h3 class="fw-bold text-dark mb-0 fs-4">Rs. <?php echo number_format($todayCollectionTotal, 2); ?></h3>
                    <span class="badge bg-warning-subtle text-warning px-2 py-0.5 rounded-pill small mt-1 fw-semibold"><i class="fa-solid fa-calendar-day me-1"></i>Current Shift</span>
                </div>
                <div class="kpi-icon-avatar bg-warning-subtle text-warning">
                    <i class="fa-solid fa-cash-register"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="top-kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Avg Receipt Value</span>
                    <h3 class="fw-bold text-dark mb-0 fs-4">Rs. <?php echo number_format($avgReceiptVal, 2); ?></h3>
                    <span class="badge bg-purple-subtle text-purple px-2 py-0.5 rounded-pill small mt-1 fw-semibold"><i class="fa-solid fa-calculator me-1"></i>Per Receipt Avg</span>
                </div>
                <div class="kpi-icon-avatar bg-purple-subtle text-purple">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls Card -->
    <div class="card card-custom mb-4 search-filter-card btn-print-hide">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label small fw-bold text-dark">From Date</label>
                    <input type="date" class="form-control" name="from" value="<?php echo htmlspecialchars($filters['from']); ?>">
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label small fw-bold text-dark">To Date</label>
                    <input type="date" class="form-control" name="to" value="<?php echo htmlspecialchars($filters['to']); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="col-12 col-sm-6 col-md-2">
                    <label class="form-label small fw-bold text-dark">Payment Method</label>
                    <select class="form-select" name="payment_method">
                        <option value="">All Methods</option>
                        <option value="Cash" <?php echo ($filters['payment_method'] === 'Cash') ? 'selected' : ''; ?>>Cash Desk 💵</option>
                        <option value="Bank" <?php echo ($filters['payment_method'] === 'Bank') ? 'selected' : ''; ?>>Bank Transfer 🏦</option>
                        <option value="Online" <?php echo ($filters['payment_method'] === 'Online') ? 'selected' : ''; ?>>Online 📱</option>
                        <option value="Cheque" <?php echo ($filters['payment_method'] === 'Cheque') ? 'selected' : ''; ?>>Cheque 📝</option>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label small fw-bold text-dark">Search Query</label>
                    <input type="text" class="form-control" name="search" placeholder="Receipt #, Student Name, Admission #..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>

                <div class="col-12 col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold" title="Filter Receipts"><i class="fa-solid fa-magnifying-glass"></i></button>
                    <a href="receipts.php" class="btn btn-outline-secondary fw-semibold" title="Reset Filters"><i class="fa-solid fa-rotate-right"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Receipts Data Table Card -->
    <div class="card card-custom">
        <div class="card-header-custom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-history me-2 text-primary"></i>Historical Receipts & Collections Log</h5>
                <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill fw-semibold"><?php echo $totalCollections; ?> Total Receipts</span>
            </div>
            <div class="text-end text-success fw-bold fs-6 btn-print-hide">
                Period Total: Rs. <?php echo number_format($totalInView, 2); ?>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="receiptsTable">
                    <thead class="table-custom-header">
                        <tr>
                            <th>Receipt Number</th>
                            <th>Date Collected</th>
                            <th>Student Record</th>
                            <th>Admission No</th>
                            <th>Fee Month / Challan</th>
                            <th>Payment Method</th>
                            <th class="text-end">Amount Paid</th>
                            <th class="text-center btn-print-hide">Action</th>
                        </tr>
                    </thead>
                    <tbody class="table-custom-body">
                        <?php if (empty($collections)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-receipt fs-1 text-muted d-block mb-3"></i>
                                    <h5 class="fw-bold text-dark mb-1">No Payment Receipts Found</h5>
                                    <p class="text-muted small mb-0">No fee collection receipts match the selected filters or date range.</p>
                                </td>
                            </tr>
                        <?php else: foreach ($collections as $c): ?>
                            <?php
                                $mBadge = '<span class="badge bg-light text-dark border px-2.5 py-1">' . htmlspecialchars($c['payment_method']) . '</span>';
                                if ($c['payment_method'] === 'Cash') {
                                    $mBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 fw-semibold"><i class="fa-solid fa-money-bill-wave me-1"></i>Cash Desk</span>';
                                } elseif ($c['payment_method'] === 'Cheque') {
                                    $mBadge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2.5 py-1 fw-semibold"><i class="fa-solid fa-money-check me-1"></i>Cheque</span>';
                                } else {
                                    $mBadge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 fw-semibold"><i class="fa-solid fa-building-columns me-1"></i>' . htmlspecialchars($c['payment_method']) . '</span>';
                                }

                                $firstLetter1 = !empty($c['first_name']) ? strtoupper(substr($c['first_name'], 0, 1)) : '';
                                $firstLetter2 = !empty($c['last_name']) ? strtoupper(substr($c['last_name'], 0, 1)) : '';
                                $initials = $firstLetter1 . $firstLetter2;
                                if (empty($initials)) $initials = 'ST';
                            ?>
                            <tr>
                                <td><code class="fw-bold text-primary fs-6"><?php echo htmlspecialchars($c['receipt_no']); ?></code></td>
                                <td class="small text-muted fw-semibold"><?php echo date('d M Y', strtotime($c['payment_date'])); ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2.5">
                                        <div class="student-avatar-placeholder-sm"><?php echo $initials; ?></div>
                                        <div>
                                            <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><code class="fw-bold text-dark"><?php echo htmlspecialchars($c['admission_no']); ?></code></td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2.5 py-1 fw-semibold">
                                        <i class="fa-solid fa-calendar-check me-1 text-primary"></i><?php echo htmlspecialchars($c['month'] ?: 'Direct Fee Payment'); ?>
                                    </span>
                                </td>
                                <td><?php echo $mBadge; ?></td>
                                <td class="text-end fw-bold text-success fs-6">Rs. <?php echo number_format($c['amount_paid'], 2); ?></td>
                                <td class="text-center btn-print-hide">
                                    <a href="../../templates/receipt.php?receipt_no=<?php echo urlencode($c['receipt_no']); ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold shadow-sm" title="Print Official Receipt">
                                        <i class="fa-solid fa-print me-1"></i>Print Receipt
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="p-3 border-top bg-light d-flex justify-content-between align-items-center rounded-bottom-4 btn-print-hide">
                    <span class="text-muted small fw-semibold">Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> &middot; (Total Receipts: <?php echo $totalCollections; ?>)</span>
                    <nav aria-label="Page navigation">
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
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // CSV Export Handler
    document.getElementById("btnExportCSV")?.addEventListener("click", function() {
        const table = document.getElementById("receiptsTable");
        if (!table) return;

        let csv = [];
        const rows = table.querySelectorAll("tr");
        
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll("td, th");
            // Skip action column (index 7)
            for (let j = 0; j < cols.length - 1; j++) {
                let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/\s+/g, " ").trim();
                text = text.replace(/"/g, '""');
                row.push('"' + text + '"');
            }
            if (row.length > 0) csv.push(row.join(","));
        }

        const csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
        const downloadLink = document.createElement("a");
        downloadLink.download = "payment_receipts_ledger_" + new Date().toISOString().slice(0,10) + ".csv";
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = "none";
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
