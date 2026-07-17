<?php
/**
 * Indus Grammar School ERP - Accounts & Cash Dashboard
 * Version 4.0.0
 */

$pageTitle = 'Accounts Dashboard';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// 1. Force daily opening cash counter register setup if not initialized
if (class_exists('Cash')) {
    $register = Cash::getOpenRegister();
    if (!$register) {
        echo "<script>window.location.href='opening_balance.php';</script>";
        exit;
    }
}

// 2. Fetch daily/monthly aggregates
$today = date('Y-m-d');
$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');

$todayIncome = 0.00;
$todayExpenses = 0.00;
$monthlyIncome = 0.00;
$monthlyExpenses = 0.00;
$outstandingFees = 0.00;
$openingCash = 0.00;
$cashInHand = 0.00;

try {
    // Today's Income
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM income WHERE income_date = :dt");
    $stmt->execute(['dt' => $today]);
    $todayIncome = (float)$stmt->fetchColumn();

    // Today's Expenses
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = :dt");
    $stmt->execute(['dt' => $today]);
    $todayExpenses = (float)$stmt->fetchColumn();

    // Monthly Income
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM income WHERE income_date BETWEEN :start AND :end");
    $stmt->execute(['start' => $startOfMonth, 'end' => $endOfMonth]);
    $monthlyIncome = (float)$stmt->fetchColumn();

    // Monthly Expenses
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN :start AND :end");
    $stmt->execute(['start' => $startOfMonth, 'end' => $endOfMonth]);
    $monthlyExpenses = (float)$stmt->fetchColumn();

    // Outstanding Fees (Pending and Partial payments)
    $outstandingFees = (float)$db->query("
        SELECT COALESCE(SUM(total_payable - paid_amount), 0) 
        FROM fee_ledger 
        WHERE status != 'Paid'
    ")->fetchColumn();

    // Today's Opening Cash Balance
    $stmt = $db->prepare("SELECT COALESCE(SUM(opening_cash), 0) FROM cash_opening WHERE date = :dt");
    $stmt->execute(['dt' => $today]);
    $openingCash = (float)$stmt->fetchColumn();

} catch (Exception $e) {
    error_log("Accounts dashboard queries exception: " . $e->getMessage());
}

// Calculate Net metrics
$todayCash = ($openingCash + $todayIncome) - $todayExpenses;
$netProfit = $monthlyIncome - $monthlyExpenses;
$cashInHand = $todayCash; // Dynamic expected balance in cash register today
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-wallet me-2 text-primary"></i>Accounts Dashboard</h3>
        <p class="text-muted small mb-0">Financial summary indicators for the current month and daily cash desk counter status.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="closing.php" class="btn btn-danger px-4">
            <i class="fa-solid fa-lock me-2"></i>Close Daily Counter
        </a>
    </div>
</div>

<!-- 8 Dashboard Summary Cards -->
<div class="row g-3 mb-4">
    <!-- Today's Income -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #198754, #157347);">
            <div class="card-body p-3 text-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h6 class="text-white-50 text-uppercase small fw-bold mb-1" style="font-size:0.75rem;">Today's Income</h6>
                        <h4 class="fw-bold mb-0">Rs. <?php echo number_format($todayIncome, 2); ?></h4>
                    </div>
                    <div class="p-2 bg-white bg-opacity-10 rounded">
                        <i class="fa-solid fa-circle-arrow-down fs-4"></i>
                    </div>
                </div>
                <div class="small text-white-50" style="font-size:0.75rem;">Fees + manual income</div>
            </div>
        </div>
    </div>

    <!-- Today's Expenses -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #dc3545, #bb2d3b);">
            <div class="card-body p-3 text-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h6 class="text-white-50 text-uppercase small fw-bold mb-1" style="font-size:0.75rem;">Today's Expenses</h6>
                        <h4 class="fw-bold mb-0">Rs. <?php echo number_format($todayExpenses, 2); ?></h4>
                    </div>
                    <div class="p-2 bg-white bg-opacity-10 rounded">
                        <i class="fa-solid fa-circle-arrow-up fs-4"></i>
                    </div>
                </div>
                <div class="small text-white-50" style="font-size:0.75rem;">Recorded cash/bank payments</div>
            </div>
        </div>
    </div>

    <!-- Today's Expected Cash -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #0d6efd, #0b5ed7);">
            <div class="card-body p-3 text-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h6 class="text-white-50 text-uppercase small fw-bold mb-1" style="font-size:0.75rem;">Today's Cash</h6>
                        <h4 class="fw-bold mb-0">Rs. <?php echo number_format($todayCash, 2); ?></h4>
                    </div>
                    <div class="p-2 bg-white bg-opacity-10 rounded">
                        <i class="fa-solid fa-money-bill-1-wave fs-4"></i>
                    </div>
                </div>
                <div class="small text-white-50" style="font-size:0.75rem;">Opening + Inflows - Outflows</div>
            </div>
        </div>
    </div>

    <!-- Cash In Hand -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #6f42c1, #59359a);">
            <div class="card-body p-3 text-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h6 class="text-white-50 text-uppercase small fw-bold mb-1" style="font-size:0.75rem;">Cash in Hand</h6>
                        <h4 class="fw-bold mb-0">Rs. <?php echo number_format($cashInHand, 2); ?></h4>
                    </div>
                    <div class="p-2 bg-white bg-opacity-10 rounded">
                        <i class="fa-solid fa-wallet fs-4"></i>
                    </div>
                </div>
                <div class="small text-white-50" style="font-size:0.75rem;">Physical counter currency</div>
            </div>
        </div>
    </div>

    <!-- Monthly Income -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: #20c997;">
            <div class="card-body p-3 text-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h6 class="text-white-50 text-uppercase small fw-bold mb-1" style="font-size:0.75rem;">Monthly Income</h6>
                        <h4 class="fw-bold mb-0">Rs. <?php echo number_format($monthlyIncome, 2); ?></h4>
                    </div>
                    <div class="p-2 bg-white bg-opacity-10 rounded">
                        <i class="fa-solid fa-chart-line fs-4"></i>
                    </div>
                </div>
                <div class="small text-white-50" style="font-size:0.75rem;">Current month cumulative</div>
            </div>
        </div>
    </div>

    <!-- Monthly Expenses -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: #fd7e14;">
            <div class="card-body p-3 text-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h6 class="text-white-50 text-uppercase small fw-bold mb-1" style="font-size:0.75rem;">Monthly Expenses</h6>
                        <h4 class="fw-bold mb-0">Rs. <?php echo number_format($monthlyExpenses, 2); ?></h4>
                    </div>
                    <div class="p-2 bg-white bg-opacity-10 rounded">
                        <i class="fa-solid fa-receipt fs-4"></i>
                    </div>
                </div>
                <div class="small text-white-50" style="font-size:0.75rem;">Monthly billings cumulative</div>
            </div>
        </div>
    </div>

    <!-- Net Profit -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #0dcaf0, #0baccc);">
            <div class="card-body p-3 text-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h6 class="text-white-50 text-uppercase small fw-bold mb-1" style="font-size:0.75rem;">Net Profit</h6>
                        <h4 class="fw-bold mb-0">Rs. <?php echo number_format($netProfit, 2); ?></h4>
                    </div>
                    <div class="p-2 bg-white bg-opacity-10 rounded">
                        <i class="fa-solid fa-scale-balanced fs-4"></i>
                    </div>
                </div>
                <div class="small text-white-50" style="font-size:0.75rem;">Income - Expenses</div>
            </div>
        </div>
    </div>

    <!-- Outstanding Fees -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #ffc107, #ffb300);">
            <div class="card-body p-3 text-dark">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h6 class="text-dark-50 text-uppercase small fw-bold mb-1" style="font-size:0.75rem;">Outstanding Fees</h6>
                        <h4 class="fw-bold mb-0">Rs. <?php echo number_format($outstandingFees, 2); ?></h4>
                    </div>
                    <div class="p-2 bg-dark bg-opacity-10 rounded">
                        <i class="fa-solid fa-clock fs-4"></i>
                    </div>
                </div>
                <div class="small text-dark-50" style="font-size:0.75rem;">Receivable school dues</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Quick Access Gateways -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-4"><i class="fa-solid fa-shuffle me-2"></i>Accounts Operations Panel</h5>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <a href="income.php" class="card text-decoration-none border border-light p-3 h-100 text-center hover-card" style="border-radius: 8px;">
                            <i class="fa-solid fa-circle-down fs-3 text-success mb-2"></i>
                            <h6 class="fw-bold text-dark mb-0 small">Record Income</h6>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="expenses.php" class="card text-decoration-none border border-light p-3 h-100 text-center hover-card" style="border-radius: 8px;">
                            <i class="fa-solid fa-circle-up fs-3 text-danger mb-2"></i>
                            <h6 class="fw-bold text-dark mb-0 small">Record Expenses</h6>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="cashbook.php" class="card text-decoration-none border border-light p-3 h-100 text-center hover-card" style="border-radius: 8px;">
                            <i class="fa-solid fa-book-bookmark fs-3 text-primary mb-2"></i>
                            <h6 class="fw-bold text-dark mb-0 small">Cash Book Ledger</h6>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="financial_reports.php" class="card text-decoration-none border border-light p-3 h-100 text-center hover-card" style="border-radius: 8px;">
                            <i class="fa-solid fa-chart-pie fs-3 text-warning mb-2"></i>
                            <h6 class="fw-bold text-dark mb-0 small">Financial Statements</h6>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Day Status Reconciliations info -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-clock-rotate-left me-2"></i>Counter Session</h5>
                
                <table class="table table-borderless align-middle mb-0">
                    <tbody>
                        <tr class="border-bottom border-light">
                            <td class="text-muted py-2">Counter Status</td>
                            <td class="text-end fw-bold text-success"><span class="badge bg-success rounded-pill px-3">Open / Collecting</span></td>
                        </tr>
                        <tr class="border-bottom border-light">
                            <td class="text-muted py-2">Opening Time Today</td>
                            <td class="text-end fw-bold text-dark"><?php echo date('d M Y, h:i A', strtotime($register['created_at'])); ?></td>
                        </tr>
                        <tr class="border-bottom border-light">
                            <td class="text-muted py-2">Opening Cash Register</td>
                            <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($openingCash, 2); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted py-2">Total Collections Today</td>
                            <td class="text-end fw-bold text-success">+ Rs. <?php echo number_format($todayIncome, 2); ?></td>
                        </tr>
                    </tbody>
                </table>

                <div class="d-grid gap-2 mt-4">
                    <a href="../fees/collection.php" class="btn btn-outline-primary"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Fee Collection Screen</a>
                    <a href="bank.php" class="btn btn-outline-secondary"><i class="fa-solid fa-building-columns me-2"></i>Bank Deposits / Withdrawals</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hover-card {
    transition: all 0.25s ease;
}
.hover-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border-color: #0d6efd !important;
}
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
