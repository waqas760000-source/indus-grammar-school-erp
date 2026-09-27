<?php
/**
 * Indus Grammar School ERP - Premium Cash Book Daily Ledger
 * Version 5.0.0
 */

require_once 'e:/Xampo/htdocs/indus-grammar-school-erp/indus-grammar-school-erp/config/app.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// Date Filters Handling & Presets
$preset = sanitize($_GET['preset'] ?? '');
if ($preset === 'today') {
    $fromDate = date('Y-m-d');
    $toDate   = date('Y-m-d');
} elseif ($preset === 'week') {
    $fromDate = date('Y-m-d', strtotime('-6 days'));
    $toDate   = date('Y-m-d');
} elseif ($preset === 'month') {
    $fromDate = date('Y-m-01');
    $toDate   = date('Y-m-t');
} elseif ($preset === 'last_month') {
    $fromDate = date('Y-m-01', strtotime('first day of last month'));
    $toDate   = date('Y-m-t', strtotime('last day of last month'));
} elseif ($preset === 'year') {
    $fromDate = date('Y-01-01');
    $toDate   = date('Y-12-31');
} else {
    $fromDate = sanitize($_GET['from_date'] ?? date('Y-m-01'));
    $toDate   = sanitize($_GET['to_date'] ?? date('Y-m-d'));
}

// Query Cash Book Records
$records = [];
try {
    $stmt = $db->prepare("
        SELECT cb.* 
        FROM cash_book cb
        WHERE cb.date BETWEEN :from AND :to
        ORDER BY cb.date DESC
    ");
    $stmt->execute(['from' => $fromDate, 'to' => $toDate]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading cash book: " . $e->getMessage());
}

// Calculate Summary Analytics
$totalIncome = 0.00;
$totalExpenses = 0.00;
$balancedDays = 0;
$shortageDays = 0;
$surplusDays = 0;
$totalDiscrepancyAmount = 0.00;

foreach ($records as $r) {
    $totalIncome += (float)$r['income'];
    $totalExpenses += (float)$r['expenses'];
    $expected = ((float)$r['opening_cash'] + (float)$r['income']) - (float)$r['expenses'];
    $diff = (float)$r['closing_cash'] - $expected;

    if (abs($diff) < 0.01) {
        $balancedDays++;
    } elseif ($diff < 0) {
        $shortageDays++;
        $totalDiscrepancyAmount += abs($diff);
    } else {
        $surplusDays++;
        $totalDiscrepancyAmount += $diff;
    }
}

$netCashFlow = $totalIncome - $totalExpenses;
$totalTransactionsVolume = $totalIncome + $totalExpenses;
$incomePercentage = $totalTransactionsVolume > 0 ? round(($totalIncome / $totalTransactionsVolume) * 100, 1) : 50;
$expensePercentage = $totalTransactionsVolume > 0 ? round(($totalExpenses / $totalTransactionsVolume) * 100, 1) : 50;

// Handle Export CSV Action
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cashbook_ledger_' . $fromDate . '_to_' . $toDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, [SCHOOL_NAME . ' - DAILY CASH BOOK LEDGER STATEMENT']);
    fputcsv($output, ['Filter Period:', $fromDate . ' to ' . $toDate]);
    fputcsv($output, ['Generated On:', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, ['Date', 'Opening Cash (Rs.)', 'Income / Inflows (Rs.)', 'Expenses / Outflows (Rs.)', 'Expected Closing (Rs.)', 'Counter Counted Closing (Rs.)', 'Audit Variance (Rs.)', 'Audit Status']);
    
    foreach ($records as $r) {
        $expected = ((float)$r['opening_cash'] + (float)$r['income']) - (float)$r['expenses'];
        $diff = (float)$r['closing_cash'] - $expected;
        $statusStr = (abs($diff) < 0.01) ? 'Balanced' : (($diff < 0) ? 'Shortage (-' . number_format(abs($diff), 2) . ')' : 'Surplus (+' . number_format($diff, 2) . ')');
        
        fputcsv($output, [
            date('d M Y', strtotime($r['date'])),
            number_format($r['opening_cash'], 2, '.', ''),
            number_format($r['income'], 2, '.', ''),
            number_format($r['expenses'], 2, '.', ''),
            number_format($expected, 2, '.', ''),
            number_format($r['closing_cash'], 2, '.', ''),
            number_format($diff, 2, '.', ''),
            $statusStr
        ]);
    }
    fclose($output);
    exit;
}

$pageTitle = 'Cash Book Daily Ledger';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Premium Inline Custom CSS -->
<style>
.cb-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e1b4b 100%);
    border-radius: 20px;
    color: #ffffff;
    box-shadow: 0 15px 35px rgba(15, 23, 42, 0.25);
    position: relative;
    overflow: hidden;
}
.cb-hero-card::before {
    content: "";
    position: absolute;
    top: -50%;
    right: -20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}
.kpi-card-premium {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}
.kpi-card-premium:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
    border-color: #cbd5e1;
}
.kpi-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}
.preset-btn {
    border-radius: 30px;
    font-size: 0.825rem;
    font-weight: 600;
    padding: 6px 16px;
    transition: all 0.2s ease;
}
.preset-btn.active {
    background-color: #2563eb;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
.ledger-table-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.04);
}
.ledger-table thead th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 16px 20px;
    border-bottom: 2px solid #e2e8f0;
}
.ledger-table tbody td {
    padding: 16px 20px;
    font-size: 0.925rem;
    border-bottom: 1px solid #f1f5f9;
}
.ledger-table tbody tr:hover {
    background-color: #f8fafc;
}
.badge-soft-success {
    background-color: #dcfce7;
    color: #15803d;
}
.badge-soft-danger {
    background-color: #ffe4e6;
    color: #be123c;
}
.badge-soft-warning {
    background-color: #fef3c7;
    color: #b45309;
}
.badge-soft-info {
    background-color: #e0f2fe;
    color: #0369a1;
}
.progress-bar-custom {
    height: 10px;
    border-radius: 6px;
    overflow: hidden;
    background-color: #e2e8f0;
}
</style>

<!-- Hero Executive Header -->
<div class="cb-hero-card p-4 p-lg-5 mb-4">
    <div class="row align-items-center g-4 position-relative" style="z-index: 2;">
        <div class="col-lg-7">
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-white bg-opacity-10 backdrop-blur text-warning small fw-bold mb-3 border border-white border-opacity-10">
                <i class="fa-solid fa-shield-halved"></i> Audit Verified Daily Ledger
            </div>
            <h2 class="fw-extrabold text-white mb-2 display-6">Cash Book Daily Ledger</h2>
            <p class="text-slate-300 mb-0 opacity-90 leading-relaxed fs-6">
                Real-time chronological financial statement tracking opening funds, daily fee/income collections, operational expense outflows, physical counter audits, and closing cash balances.
            </p>
        </div>
        <div class="col-lg-5 text-lg-end">
            <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                <button class="btn btn-light btn-lg px-4 rounded-3 fw-bold shadow-sm" id="btnPrintLedger">
                    <i class="fa-solid fa-print me-2 text-primary"></i>Print Statement
                </button>
                <a href="?action=export&from_date=<?php echo $fromDate; ?>&to_date=<?php echo $toDate; ?>" class="btn btn-primary btn-lg px-4 rounded-3 fw-bold shadow-sm" style="background-color: #4f46e5; border-color: #4f46e5;">
                    <i class="fa-solid fa-file-excel me-2"></i>Export CSV
                </a>
            </div>
            <div class="mt-3 text-slate-300 small">
                <i class="fa-solid fa-calendar-days me-1 text-warning"></i> Filtered: <strong class="text-white"><?php echo date('d M Y', strtotime($fromDate)); ?></strong> to <strong class="text-white"><?php echo date('d M Y', strtotime($toDate)); ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- Executive Financial KPIs -->
<div class="row g-3 mb-4">
    <!-- Total Inflow / Collections -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card-premium p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase tracking-wider">Total Inflows</span>
                <div class="kpi-icon-box bg-success-soft text-success">
                    <i class="fa-solid fa-arrow-down-left me-0"></i>
                </div>
            </div>
            <h3 class="fw-extrabold text-dark mb-1">Rs. <?php echo number_format($totalIncome, 2); ?></h3>
            <div class="d-flex align-items-center gap-1 text-success small fw-semibold">
                <i class="fa-solid fa-circle-check"></i>
                <span>Received Collections</span>
            </div>
        </div>
    </div>

    <!-- Total Outflow / Expenses -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card-premium p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase tracking-wider">Total Outflows</span>
                <div class="kpi-icon-box bg-danger-soft text-danger">
                    <i class="fa-solid fa-arrow-up-right me-0"></i>
                </div>
            </div>
            <h3 class="fw-extrabold text-dark mb-1">Rs. <?php echo number_format($totalExpenses, 2); ?></h3>
            <div class="d-flex align-items-center gap-1 text-danger small fw-semibold">
                <i class="fa-solid fa-receipt"></i>
                <span>School Expenses</span>
            </div>
        </div>
    </div>

    <!-- Net Cash Flow -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card-premium p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase tracking-wider">Net Cash Flow</span>
                <div class="kpi-icon-box <?php echo $netCashFlow >= 0 ? 'bg-primary-soft text-primary' : 'bg-danger-soft text-danger'; ?>">
                    <i class="fa-solid <?php echo $netCashFlow >= 0 ? 'fa-chart-line' : 'fa-chart-line-down'; ?>"></i>
                </div>
            </div>
            <h3 class="fw-extrabold <?php echo $netCashFlow >= 0 ? 'text-primary' : 'text-danger'; ?> mb-1">
                <?php echo $netCashFlow >= 0 ? '+' : ''; ?>Rs. <?php echo number_format($netCashFlow, 2); ?>
            </h3>
            <div class="d-flex align-items-center gap-1 text-muted small fw-semibold">
                <i class="fa-solid fa-scale-balanced"></i>
                <span>Inflows minus Outflows</span>
            </div>
        </div>
    </div>

    <!-- Audit Reconciliation Summary -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card-premium p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase tracking-wider">Drawer Audits</span>
                <div class="kpi-icon-box bg-info-soft text-info">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
            </div>
            <h3 class="fw-extrabold text-dark mb-1"><?php echo count($records); ?> <span class="fs-6 text-muted font-normal">Days</span></h3>
            <div class="d-flex align-items-center gap-2 text-muted small fw-semibold">
                <span class="badge badge-soft-success rounded-pill px-2 py-1"><?php echo $balancedDays; ?> Balanced</span>
                <?php if ($shortageDays > 0): ?>
                    <span class="badge badge-soft-danger rounded-pill px-2 py-1"><?php echo $shortageDays; ?> Short</span>
                <?php endif; ?>
                <?php if ($surplusDays > 0): ?>
                    <span class="badge badge-soft-warning rounded-pill px-2 py-1"><?php echo $surplusDays; ?> Surplus</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Cash Flow Ratio & Filter Toolbar Card -->
<div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-body p-4">
        
        <!-- Cash Ratio Visual Indicator -->
        <div class="mb-4 p-3 bg-light rounded-3 border">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-bold text-dark"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Period Cash Flow Volume Distribution</span>
                <span class="small text-muted font-monospace">
                    Inflow: <strong class="text-success"><?php echo $incomePercentage; ?>%</strong> | Outflow: <strong class="text-danger"><?php echo $expensePercentage; ?>%</strong>
                </span>
            </div>
            <div class="progress-bar-custom d-flex">
                <div class="bg-success" style="width: <?php echo $incomePercentage; ?>%;"></div>
                <div class="bg-danger" style="width: <?php echo $expensePercentage; ?>%;"></div>
            </div>
        </div>

        <!-- Quick Date Presets -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div class="d-flex align-items-center gap-2">
                <span class="small fw-bold text-muted me-1"><i class="fa-solid fa-bolt text-warning me-1"></i>Quick Presets:</span>
                <a href="?preset=today" class="btn btn-outline-secondary preset-btn <?php echo $preset === 'today' ? 'active' : ''; ?>">Today</a>
                <a href="?preset=week" class="btn btn-outline-secondary preset-btn <?php echo $preset === 'week' ? 'active' : ''; ?>">Last 7 Days</a>
                <a href="?preset=month" class="btn btn-outline-secondary preset-btn <?php echo ($preset === 'month' || (empty($preset) && $fromDate === date('Y-m-01'))) ? 'active' : ''; ?>">This Month</a>
                <a href="?preset=last_month" class="btn btn-outline-secondary preset-btn <?php echo $preset === 'last_month' ? 'active' : ''; ?>">Last Month</a>
                <a href="?preset=year" class="btn btn-outline-secondary preset-btn <?php echo $preset === 'year' ? 'active' : ''; ?>">This Year</a>
            </div>

            <!-- Instant Search Input -->
            <div class="position-relative" style="min-width: 250px;">
                <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                <input type="text" id="tableSearchInput" class="form-control form-control-sm ps-5 rounded-pill" placeholder="Search by date or status...">
            </div>
        </div>

        <!-- Date Custom Filter Form -->
        <form method="GET" class="row g-3 align-items-end pt-2 border-top">
            <div class="col-md-4 col-lg-4">
                <label class="form-label small fw-bold text-muted">From Date</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-calendar-day text-primary"></i></span>
                    <input type="date" class="form-control" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" required>
                </div>
            </div>
            <div class="col-md-4 col-lg-4">
                <label class="form-label small fw-bold text-muted">To Date</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-calendar-day text-primary"></i></span>
                    <input type="date" class="form-control" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" required>
                </div>
            </div>
            <div class="col-md-4 col-lg-4 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1 py-2 rounded-3 fw-bold">
                    <i class="fa-solid fa-filter me-2"></i>Apply Custom Dates
                </button>
                <a href="cashbook.php" class="btn btn-sm btn-outline-secondary py-2 rounded-3" title="Reset Filters">
                    <i class="fa-solid fa-rotate-right"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Ledger Data Table Card -->
<div class="ledger-table-card mb-4" id="ledgerReportSection">
    <div class="p-4 border-bottom d-flex flex-wrap justify-content-between align-items-center bg-white gap-3">
        <div>
            <h5 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-list-check me-2 text-primary"></i>Daily Cash Ledger Logs</h5>
            <p class="text-muted small mb-0">Showing <?php echo count($records); ?> daily statement log entries</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill fw-bold fs-7">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> Chronological Statements
            </span>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table ledger-table align-middle mb-0" id="ledgerDataTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th class="text-end">Opening Cash</th>
                    <th class="text-end">Cash Inflows (+)</th>
                    <th class="text-end">Cash Outflows (-)</th>
                    <th class="text-end">Expected Balance</th>
                    <th class="text-end">Counted Closing</th>
                    <th class="text-center">Audit Variance</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="py-4">
                                <i class="fa-solid fa-folder-open fs-1 text-muted opacity-50 mb-3 d-block"></i>
                                <h6 class="fw-bold text-secondary">No Ledger Records Found</h6>
                                <p class="text-muted small mb-0">There are no daily cash statements registered for the selected period.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($records as $r): 
                    $opening  = (float)$r['opening_cash'];
                    $income   = (float)$r['income'];
                    $expenses = (float)$r['expenses'];
                    $closing  = (float)$r['closing_cash'];
                    $expected = ($opening + $income) - $expenses;
                    $diff     = $closing - $expected;
                    $rowDateFormatted = date('d M Y', strtotime($r['date']));
                ?>
                    <tr class="ledger-row" data-search="<?php echo strtolower($rowDateFormatted . ' ' . $r['date'] . ' ' . ($diff < 0 ? 'shortage short' : ($diff > 0 ? 'surplus' : 'balanced'))); ?>">
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 rounded-3 bg-light border text-primary fw-bold text-center" style="min-width: 48px;">
                                    <span class="d-block lh-1 small uppercase text-muted"><?php echo date('M', strtotime($r['date'])); ?></span>
                                    <span class="fs-5 lh-1 text-dark"><?php echo date('d', strtotime($r['date'])); ?></span>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-0"><?php echo date('l', strtotime($r['date'])); ?></h6>
                                    <small class="text-muted"><?php echo date('d M Y', strtotime($r['date'])); ?></small>
                                </div>
                            </div>
                        </td>
                        <td class="text-end fw-semibold text-secondary">
                            Rs. <?php echo number_format($opening, 2); ?>
                        </td>
                        <td class="text-end fw-bold text-success">
                            + Rs. <?php echo number_format($income, 2); ?>
                        </td>
                        <td class="text-end fw-bold text-danger">
                            - Rs. <?php echo number_format($expenses, 2); ?>
                        </td>
                        <td class="text-end fw-bold text-primary">
                            Rs. <?php echo number_format($expected, 2); ?>
                        </td>
                        <td class="text-end fw-extrabold text-dark">
                            Rs. <?php echo number_format($closing, 2); ?>
                        </td>
                        <td class="text-center">
                            <?php if (abs($diff) < 0.01): ?>
                                <span class="badge badge-soft-success px-3 py-2 rounded-pill fw-bold">
                                    <i class="fa-solid fa-check-circle me-1"></i>Balanced
                                </span>
                            <?php elseif ($diff < 0): ?>
                                <span class="badge badge-soft-danger px-3 py-2 rounded-pill fw-bold">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i>Short: Rs. <?php echo number_format(abs($diff), 2); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-soft-warning px-3 py-2 rounded-pill fw-bold">
                                    <i class="fa-solid fa-arrow-trend-up me-1"></i>Surplus: Rs. <?php echo number_format($diff, 2); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary rounded-circle p-2 btn-view-detail" 
                                    data-date="<?php echo $rowDateFormatted; ?>"
                                    data-opening="<?php echo number_format($opening, 2); ?>"
                                    data-income="<?php echo number_format($income, 2); ?>"
                                    data-expenses="<?php echo number_format($expenses, 2); ?>"
                                    data-expected="<?php echo number_format($expected, 2); ?>"
                                    data-closing="<?php echo number_format($closing, 2); ?>"
                                    data-diff="<?php echo number_format($diff, 2); ?>"
                                    title="View Statement Details">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Statement Detail Modal -->
<div class="modal fade" id="statementDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
            <div class="modal-header bg-dark text-white p-4" style="border-top-left-radius: 18px; border-top-right-radius: 18px;">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="modalDateTitle"><i class="fa-solid fa-file-invoice-dollar me-2 text-warning"></i>Daily Cash Audit Detail</h5>
                    <small class="text-slate-300">Detailed breakdown statement log</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light p-3 rounded-3 border mb-4">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="text-muted small">Opening Cash Drawer:</td>
                            <td class="text-end fw-bold text-dark" id="modalOpening">Rs. 0.00</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Today Collections (+):</td>
                            <td class="text-end fw-bold text-success" id="modalIncome">+ Rs. 0.00</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Today Expenses (-):</td>
                            <td class="text-end fw-bold text-danger" id="modalExpenses">- Rs. 0.00</td>
                        </tr>
                        <tr class="border-top">
                            <td class="fw-bold text-primary">System Expected Balance:</td>
                            <td class="text-end fw-extrabold text-primary" id="modalExpected">Rs. 0.00</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-dark">Counted Physical Closing:</td>
                            <td class="text-end fw-extrabold text-dark" id="modalClosing">Rs. 0.00</td>
                        </tr>
                    </table>
                </div>

                <div class="p-3 rounded-3 text-center" id="modalVarianceBox">
                    <span class="small fw-bold d-block mb-1 text-uppercase tracking-wider">Audit Result</span>
                    <h4 class="fw-extrabold mb-0" id="modalVarianceText">Balanced</h4>
                </div>
            </div>
            <div class="modal-footer p-3 border-0 bg-light rounded-bottom-4">
                <button type="button" class="btn btn-secondary w-100 rounded-3 fw-bold" data-bs-dismiss="modal">Close Statement</button>
            </div>
        </div>
    </div>
</div>

<!-- Print Printable Layout Template -->
<div id="printLedgerTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 30px; color: #1e293b; max-width: 900px; margin: 0 auto;">
        <!-- Header -->
        <div style="border-bottom: 3px double #0f172a; padding-bottom: 15px; margin-bottom: 25px; text-align: center;">
            <h1 style="margin: 0; font-size: 24px; color: #0f172a; text-transform: uppercase; font-weight: 800;"><?php echo SCHOOL_NAME; ?></h1>
            <p style="margin: 5px 0 0 0; font-size: 13px; color: #64748b;"><?php echo SCHOOL_ADDRESS; ?></p>
            <div style="margin-top: 15px; display: inline-block; background: #0f172a; color: #ffffff; padding: 6px 20px; border-radius: 20px; font-size: 13px; font-weight: bold; letter-spacing: 1px;">
                DAILY CASH BOOK LEDGER STATEMENT
            </div>
        </div>

        <!-- Period Info Bar -->
        <table style="width: 100%; font-size: 13px; margin-bottom: 20px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <tr>
                <td><strong>Statement Period:</strong> <?php echo date('d M Y', strtotime($fromDate)) . ' to ' . date('d M Y', strtotime($toDate)); ?></td>
                <td style="text-align: right;"><strong>Printed On:</strong> <?php echo date('d M Y, h:i A'); ?></td>
            </tr>
        </table>

        <!-- Table Data -->
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 30px;" border="1" cellpadding="10" cellspacing="0">
            <thead>
                <tr style="background-color: #0f172a; color: #ffffff;">
                    <th style="text-align: left;">Date</th>
                    <th style="text-align: right;">Opening Cash</th>
                    <th style="text-align: right;">Inflows (+)</th>
                    <th style="text-align: right;">Outflows (-)</th>
                    <th style="text-align: right;">Expected Balance</th>
                    <th style="text-align: right;">Counted Closing</th>
                    <th style="text-align: center;">Audit Variance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 20px;">No transactions recorded for this period.</td></tr>
                <?php else: foreach ($records as $r): 
                    $op   = (float)$r['opening_cash'];
                    $inc  = (float)$r['income'];
                    $exp  = (float)$r['expenses'];
                    $cls  = (float)$r['closing_cash'];
                    $expc = ($op + $inc) - $exp;
                    $diff = $cls - $expc;
                ?>
                    <tr>
                        <td><strong><?php echo date('d M Y', strtotime($r['date'])); ?></strong></td>
                        <td style="text-align: right;">Rs. <?php echo number_format($op, 2); ?></td>
                        <td style="text-align: right; color: #16a34a; font-weight: bold;">+ Rs. <?php echo number_format($inc, 2); ?></td>
                        <td style="text-align: right; color: #dc2626; font-weight: bold;">- Rs. <?php echo number_format($exp, 2); ?></td>
                        <td style="text-align: right; color: #2563eb; font-weight: bold;">Rs. <?php echo number_format($expc, 2); ?></td>
                        <td style="text-align: right; font-weight: bold;">Rs. <?php echo number_format($cls, 2); ?></td>
                        <td style="text-align: center; font-weight: bold; color: <?php echo abs($diff) < 0.01 ? '#16a34a' : ($diff < 0 ? '#dc2626' : '#d97706'); ?>;">
                            <?php echo abs($diff) < 0.01 ? 'Balanced' : ($diff < 0 ? 'Short (-' . number_format(abs($diff), 2) . ')' : 'Surplus (+' . number_format($diff, 2) . ')'); ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <!-- Totals Summary Table -->
        <table style="width: 100%; font-size: 13px; margin-bottom: 50px; background: #f1f5f9; padding: 15px; border-radius: 8px;">
            <tr>
                <td style="width: 33%;"><strong>Total Inflows:</strong> <span style="color: #16a34a;">Rs. <?php echo number_format($totalIncome, 2); ?></span></td>
                <td style="width: 33%;"><strong>Total Outflows:</strong> <span style="color: #dc2626;">Rs. <?php echo number_format($totalExpenses, 2); ?></span></td>
                <td style="width: 33%;"><strong>Net Cash Flow:</strong> <span style="color: #2563eb;">Rs. <?php echo number_format($netCashFlow, 2); ?></span></td>
            </tr>
        </table>

        <!-- Signatures Block -->
        <table style="width: 100%; margin-top: 60px; font-size: 12px; text-align: center;">
            <tr>
                <td style="width: 33%;">
                    <div style="border-top: 1px solid #475569; width: 160px; margin: 0 auto; padding-top: 8px;">
                        <strong>Prepared By</strong><br><span style="color: #64748b; font-size: 11px;">Accounts Cashier</span>
                    </div>
                </td>
                <td style="width: 33%;">
                    <div style="border-top: 1px solid #475569; width: 160px; margin: 0 auto; padding-top: 8px;">
                        <strong>Audited By</strong><br><span style="color: #64748b; font-size: 11px;">Internal School Auditor</span>
                    </div>
                </td>
                <td style="width: 33%;">
                    <div style="border-top: 1px solid #475569; width: 160px; margin: 0 auto; padding-top: 8px;">
                        <strong>Approved By</strong><br><span style="color: #64748b; font-size: 11px;">Principal / Director</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>

<?php $extraJS = '<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Live Client-side Table Filter
    const searchInput = document.getElementById("tableSearchInput");
    if (searchInput) {
        searchInput.addEventListener("keyup", function() {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll("#ledgerDataTable tbody tr.ledger-row");
            rows.forEach(row => {
                const text = row.getAttribute("data-search") || "";
                row.style.display = text.includes(query) ? "" : "none";
            });
        });
    }

    // 2. View Detail Modal Handling
    const modalElement = document.getElementById("statementDetailModal");
    if (modalElement) {
        const bsModal = new bootstrap.Modal(modalElement);
        document.querySelectorAll(".btn-view-detail").forEach(btn => {
            btn.addEventListener("click", function() {
                document.getElementById("modalDateTitle").innerHTML = "<i class=\"fa-solid fa-file-invoice-dollar me-2 text-warning\"></i>Audit Detail: " + this.dataset.date;
                document.getElementById("modalOpening").textContent = "Rs. " + this.dataset.opening;
                document.getElementById("modalIncome").textContent = "+ Rs. " + this.dataset.income;
                document.getElementById("modalExpenses").textContent = "- Rs. " + this.dataset.expenses;
                document.getElementById("modalExpected").textContent = "Rs. " + this.dataset.expected;
                document.getElementById("modalClosing").textContent = "Rs. " + this.dataset.closing;

                const diffVal = parseFloat(this.dataset.diff.replace(/,/g, ""));
                const varBox = document.getElementById("modalVarianceBox");
                const varText = document.getElementById("modalVarianceText");

                varBox.className = "p-3 rounded-3 text-center ";
                if (Math.abs(diffVal) < 0.01) {
                    varBox.classList.add("bg-success-soft", "text-success");
                    varText.textContent = "✓ Balanced (Zero Variance)";
                } else if (diffVal < 0) {
                    varBox.classList.add("bg-danger-soft", "text-danger");
                    varText.textContent = "⚠ Shortage: Rs. " + Math.abs(diffVal).toFixed(2);
                } else {
                    varBox.classList.add("bg-warning-soft", "text-warning");
                    varText.textContent = "▲ Surplus: Rs. " + diffVal.toFixed(2);
                }

                bsModal.show();
            });
        });
    }

    // 3. Print Action
    const printBtn = document.getElementById("btnPrintLedger");
    if (printBtn) {
        printBtn.addEventListener("click", function() {
            const printContent = document.getElementById("printLedgerTemplate").innerHTML;
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Cash Book Statement Summary</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>