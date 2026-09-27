<?php
/**
 * Indus Grammar School ERP - Accounts & Cash Dashboard
 * Redesigned Commercial ERP Financial Workspace
 * Version 4.0.0
 */

$pageTitle = 'Accounts Dashboard';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// 1. Optional cash register session (non-blocking)
$register = false;
if (class_exists('Cash')) {
    try {
        $register = Cash::getOpenRegister();
    } catch (Exception $e) {
        $register = false;
    }
}

// 2. Fetch daily & monthly financial aggregates
$today = date('Y-m-d');
$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');

$todayIncome = 0.00;
$todayFeePayments = 0.00;
$todayExpenses = 0.00;
$monthlyIncome = 0.00;
$monthlyFeeCollections = 0.00;
$monthlyExpenses = 0.00;
$outstandingFees = 0.00;
$openingCash = 0.00;
$bankLiquidity = 0.00;
$recentInflows = [];
$recentExpenses = [];

try {
    // Today's Manual Income
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM income WHERE income_date = :dt");
    $stmt->execute(['dt' => $today]);
    $todayIncome = (float)$stmt->fetchColumn();

    // Today's Fee Payments Collection
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE payment_date = :dt");
    $stmt->execute(['dt' => $today]);
    $todayFeePayments = (float)$stmt->fetchColumn();

    // Today's Operational Expenses
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = :dt");
    $stmt->execute(['dt' => $today]);
    $todayExpenses = (float)$stmt->fetchColumn();

    // Monthly Manual Income
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM income WHERE income_date BETWEEN :start AND :end");
    $stmt->execute(['start' => $startOfMonth, 'end' => $endOfMonth]);
    $monthlyIncome = (float)$stmt->fetchColumn();

    // Monthly Fee Collections
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE payment_date BETWEEN :start AND :end");
    $stmt->execute(['start' => $startOfMonth, 'end' => $endOfMonth]);
    $monthlyFeeCollections = (float)$stmt->fetchColumn();

    // Monthly Expenses
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN :start AND :end");
    $stmt->execute(['start' => $startOfMonth, 'end' => $endOfMonth]);
    $monthlyExpenses = (float)$stmt->fetchColumn();

    // Outstanding Student Fees Receivables
    $outstandingFees = (float)$db->query("
        SELECT COALESCE(SUM(total_payable - paid_amount), 0) 
        FROM fee_ledger 
        WHERE status != 'Paid'
    ")->fetchColumn();

    // Net Bank Deposits Liquidity
    $bankLiquidity = (float)$db->query("
        SELECT COALESCE(SUM(CASE WHEN transaction_type = 'Deposit' THEN amount ELSE -amount END), 0) 
        FROM bank_transactions
    ")->fetchColumn();

    // Today's Opening Cash Balance
    if ($register && isset($register['opening_balance'])) {
        $openingCash = (float)$register['opening_balance'];
    } else {
        $stmt = $db->prepare("SELECT COALESCE(SUM(opening_cash), 0) FROM cash_opening WHERE date = :dt");
        $stmt->execute(['dt' => $today]);
        $openingCash = (float)$stmt->fetchColumn();
    }

    // Fetch Recent Inflows (Last 5 Fee Payments)
    $recentInflows = $db->query("
        SELECT fp.payment_date as txn_date, fp.amount_paid as amount, 'Fee Payment' as category, 
               CONCAT(s.first_name, ' ', s.last_name) as title, fp.payment_method, fr.receipt_no
        FROM fee_payments fp
        JOIN students s ON fp.student_id = s.id
        LEFT JOIN fee_receipts fr ON fr.payment_id = fp.id
        ORDER BY fp.payment_date DESC, fp.id DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Recent Expenses (Last 5 Vouchers)
    $recentExpenses = $db->query("
        SELECT e.expense_date as txn_date, e.amount, c.name as category_name, e.title, e.payment_method, e.invoice_number
        FROM expenses e
        LEFT JOIN expense_categories c ON e.category_id = c.id
        ORDER BY e.expense_date DESC, e.id DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Accounts dashboard queries exception: " . $e->getMessage());
}

// Net financial computations
$todayTotalInflow = $todayIncome + $todayFeePayments;
$todayExpectedCash = ($openingCash + $todayTotalInflow) - $todayExpenses;
$monthlyTotalGrossIncome = $monthlyIncome + $monthlyFeeCollections;
$netProfit = $monthlyTotalGrossIncome - $monthlyExpenses;
$profitMarginPct = $monthlyTotalGrossIncome > 0 ? round(($netProfit / $monthlyTotalGrossIncome) * 100, 1) : 0;
?>

<style>
/* ERP Theme Custom Styling */
.hero-accounts-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e40af 100%);
    border-radius: 16px;
    position: relative;
    overflow: hidden;
}
.hero-accounts-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 450px;
    height: 450px;
    background: radial-gradient(circle, rgba(59,130,246,0.18) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}
.kpi-stat-card {
    border-radius: 14px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid rgba(226, 232, 240, 0.8);
    background: #ffffff;
}
.kpi-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08) !important;
}
.icon-shape {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}
.gateway-op-card {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    transition: all 0.25s ease;
    background: #ffffff;
    text-decoration: none !important;
}
.gateway-op-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.08);
    border-color: #3b82f6 !important;
}
.custom-table-container {
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    background: #ffffff;
}
.custom-table-container thead th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 12px 16px;
    border-bottom: 2px solid #e2e8f0;
}
.custom-table-container tbody td {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
}
.custom-table-container tbody tr:last-child td {
    border-bottom: none;
}
</style>

<!-- Hero Banner Header -->
<div class="hero-accounts-banner text-white p-4 p-lg-5 mb-4 shadow-sm">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center mb-2">
                <div class="p-3 bg-white bg-opacity-10 rounded-3 me-3 text-warning">
                    <i class="fa-solid fa-wallet fs-2"></i>
                </div>
                <div>
                    <h2 class="fw-bold mb-1 text-white">Accounts & Financial Management Dashboard</h2>
                    <p class="mb-0 text-white-50 fs-6">
                        Real-time revenue monitoring, daily counter cash balance, expenses audit, and liquidity health.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex flex-wrap gap-2 justify-content-lg-end align-items-center">
                <span class="badge bg-success bg-opacity-25 text-success border border-success-subtle px-3 py-2 rounded-pill fw-bold">
                    <i class="fa-solid fa-circle-dot me-1"></i>COUNTER REGISTER OPEN
                </span>
                <a href="../fees/collection.php" class="btn btn-primary fw-bold px-3 py-2 shadow-sm rounded-3">
                    <i class="fa-solid fa-hand-holding-dollar me-2"></i>Fee Collection
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Primary Financial KPI Cards -->
<div class="row g-3 mb-4">
    <!-- Today's Net Counter Cash -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-stat-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-primary-soft text-primary me-3 fs-4">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Today's Counter Cash</div>
                    <h4 class="fw-bold text-primary mb-0">Rs. <?php echo number_format($todayExpectedCash, 2); ?></h4>
                    <small class="text-muted" style="font-size: 0.75rem;">Opening + Inflows - Outflows</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Gross Revenue -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-stat-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-success-soft text-success me-3 fs-4">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Monthly Gross Revenue</div>
                    <h4 class="fw-bold text-success mb-0">Rs. <?php echo number_format($monthlyTotalGrossIncome, 2); ?></h4>
                    <small class="text-muted" style="font-size: 0.75rem;">Fees + Manual Income</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Expenses -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-stat-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-danger-soft text-danger me-3 fs-4">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Monthly Expenditures</div>
                    <h4 class="fw-bold text-danger mb-0">Rs. <?php echo number_format($monthlyExpenses, 2); ?></h4>
                    <small class="text-muted" style="font-size: 0.75rem;">Total Operating Bills</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Net Profit -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-stat-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-info-soft text-info me-3 fs-4">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Monthly Net Profit</div>
                    <h4 class="fw-bold <?php echo $netProfit >= 0 ? 'text-info' : 'text-danger'; ?> mb-0">Rs. <?php echo number_format($netProfit, 2); ?></h4>
                    <small class="fw-bold <?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>" style="font-size: 0.75rem;">
                        Margin: <?php echo $profitMarginPct; ?>%
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Operational Metrics -->
<div class="row g-3 mb-4">
    <!-- Outstanding Student Fees Receivables -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-stat-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-warning-soft text-warning me-3 fs-4">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Outstanding Receivables</div>
                    <h5 class="fw-bold text-dark mb-0">Rs. <?php echo number_format($outstandingFees, 2); ?></h5>
                    <small class="text-muted" style="font-size: 0.75rem;">Pending Defaulters Dues</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bank Liquidity -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-stat-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-primary-soft text-primary me-3 fs-4">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Bank Net Liquidity</div>
                    <h5 class="fw-bold text-dark mb-0">Rs. <?php echo number_format($bankLiquidity, 2); ?></h5>
                    <small class="text-muted" style="font-size: 0.75rem;">Deposits - Withdrawals</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Fee Realization -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-stat-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-success-soft text-success me-3 fs-4">
                    <i class="fa-solid fa-cash-register"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Today's Fee Collection</div>
                    <h5 class="fw-bold text-success mb-0">Rs. <?php echo number_format($todayFeePayments, 2); ?></h5>
                    <small class="text-muted" style="font-size: 0.75rem;">Realized Student Payments</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Register Opening Cash -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-stat-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-secondary-soft text-secondary me-3 fs-4">
                    <i class="fa-solid fa-vault"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Counter Opening Cash</div>
                    <h5 class="fw-bold text-dark mb-0">Rs. <?php echo number_format($openingCash, 2); ?></h5>
                    <small class="text-muted" style="font-size: 0.75rem;">Today's Starting Vault</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Split Section: Recent Activity & Counter Session Details -->
<div class="row g-4 mb-4">
    <!-- Recent Financial Inflows & Collections -->
    <div class="col-12 col-lg-7">
        <div class="custom-table-container shadow-sm h-100">
            <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-arrow-down-long text-success me-2"></i>Recent Cash Inflows & Fee Receipts</h6>
                <a href="../fees/receipts.php" class="btn btn-sm btn-outline-primary fw-semibold px-3 rounded-pill">View All Receipts</a>
            </div>
            <div class="table-responsive">
                <table class="table custom-table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Receipt / Ref</th>
                            <th>Student / Source</th>
                            <th>Method</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentInflows)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No recent inflows recorded today.</td></tr>
                        <?php else: foreach ($recentInflows as $row): ?>
                            <tr>
                                <td><strong class="text-primary"><?php echo sanitize($row['receipt_no'] ?: '—'); ?></strong></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo sanitize($row['title']); ?></div>
                                    <small class="text-muted" style="font-size:0.75rem;"><?php echo date('d M Y', strtotime($row['txn_date'])); ?></small>
                                </td>
                                <td><span class="badge bg-info-soft text-info rounded-pill px-3 py-1 fw-bold"><?php echo sanitize($row['payment_method']); ?></span></td>
                                <td class="text-end fw-bold text-success">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Counter Session Overview Card -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 14px;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-cash-register me-2 text-primary"></i>Cash Counter Register Details</h6>
                    <span class="badge bg-success-soft text-success px-3 py-1 rounded-pill fw-bold">Active Counter</span>
                </div>
                
                <table class="table table-borderless align-middle mb-0">
                    <tbody>
                        <tr class="border-bottom border-light">
                            <td class="text-muted py-2">Opening Timestamp</td>
                            <td class="text-end fw-bold text-dark">
                                <?php echo ($register && isset($register['created_at'])) ? date('d M Y, h:i A', strtotime($register['created_at'])) : date('d M Y, 08:00 AM'); ?>
                            </td>
                        </tr>
                        <tr class="border-bottom border-light">
                            <td class="text-muted py-2">Starting Opening Cash</td>
                            <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($openingCash, 2); ?></td>
                        </tr>
                        <tr class="border-bottom border-light">
                            <td class="text-muted py-2">Total Collections Billed</td>
                            <td class="text-end fw-bold text-success">+ Rs. <?php echo number_format($todayTotalInflow, 2); ?></td>
                        </tr>
                        <tr class="border-bottom border-light">
                            <td class="text-muted py-2">Total Expenses Disbursed</td>
                            <td class="text-end fw-bold text-danger">- Rs. <?php echo number_format($todayExpenses, 2); ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-dark py-2 fs-6">Expected Cash in Hand</td>
                            <td class="text-end fw-bold text-primary fs-5">Rs. <?php echo number_format($todayExpectedCash, 2); ?></td>
                        </tr>
                    </tbody>
                </table>


            </div>
        </div>
    </div>
</div>

<!-- Operations & Gateways Grid -->
<div class="card border-0 shadow-sm mb-4" style="border-radius: 14px;">
    <div class="card-body p-4">
        <h5 class="fw-bold text-dark mb-4"><i class="fa-solid fa-grid-2 me-2 text-primary"></i>Accounts Quick Operations Desk</h5>
        
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="income.php" class="gateway-op-card p-3 d-flex align-items-center h-100">
                    <div class="icon-shape bg-success-soft text-success me-3 fs-4">
                        <i class="fa-solid fa-circle-arrow-down"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Record Income</h6>
                        <small class="text-muted" style="font-size:0.75rem;">Manual cash / bank inflows</small>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="expenses.php" class="gateway-op-card p-3 d-flex align-items-center h-100">
                    <div class="icon-shape bg-danger-soft text-danger me-3 fs-4">
                        <i class="fa-solid fa-circle-arrow-up"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Record Expense</h6>
                        <small class="text-muted" style="font-size:0.75rem;">Operational expense vouchers</small>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="cashbook.php" class="gateway-op-card p-3 d-flex align-items-center h-100">
                    <div class="icon-shape bg-primary-soft text-primary me-3 fs-4">
                        <i class="fa-solid fa-book-bookmark"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Cash Book Ledger</h6>
                        <small class="text-muted" style="font-size:0.75rem;">Daily cash closing history</small>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="financial_reports.php" class="gateway-op-card p-3 d-flex align-items-center h-100">
                    <div class="icon-shape bg-warning-soft text-warning me-3 fs-4">
                        <i class="fa-solid fa-chart-pie"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Financial Statements</h6>
                        <small class="text-muted" style="font-size:0.75rem;">Profit/Loss & Balance sheet</small>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="bank.php" class="gateway-op-card p-3 d-flex align-items-center h-100">
                    <div class="icon-shape bg-info-soft text-info me-3 fs-4">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Bank Operations</h6>
                        <small class="text-muted" style="font-size:0.75rem;">Deposits & withdrawals</small>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="../fees/collection.php" class="gateway-op-card p-3 d-flex align-items-center h-100">
                    <div class="icon-shape bg-success-soft text-success me-3 fs-4">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Fee Collection Desk</h6>
                        <small class="text-muted" style="font-size:0.75rem;">Collect student monthly fees</small>
                    </div>
                </a>
            </div>

            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <a href="categories.php" class="gateway-op-card p-3 d-flex align-items-center h-100">
                    <div class="icon-shape bg-secondary-soft text-secondary me-3 fs-4">
                        <i class="fa-solid fa-tags"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Expense Categories</h6>
                        <small class="text-muted" style="font-size:0.75rem;">Configure account heads</small>
                    </div>
                </a>
            </div>

        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
