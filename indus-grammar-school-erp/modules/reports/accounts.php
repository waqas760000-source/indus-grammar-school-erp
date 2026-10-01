<?php
/**
 * Indus Grammar School ERP - Comprehensive Accounts & Finance Ledger Reports
 * Version 4.0.0 (Executive Accounting Audit & P&L Suite)
 */

$pageTitle = 'Accounts Reports Panel';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Selectors
$categories = [];
try {
    $categories = $db->query("SELECT id, name FROM expense_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$paymentMethods = ['Cash', 'Bank', 'Cheque', 'Online'];

// Filter parameters
$selectedReport   = sanitize($_GET['report_type'] ?? 'profit_loss');
$selectedCategory = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$selectedMethod   = sanitize($_GET['payment_method'] ?? '');
$dateFrom         = sanitize($_GET['date_from'] ?? date('Y-m-01'));
$dateTo           = sanitize($_GET['date_to'] ?? date('Y-m-d'));
$searchKeyword    = sanitize($_GET['q'] ?? '');

$reportTitle = "Financial Audit Statement";
$reportData = [];

// Overall Financial KPI Aggregates (Range-wide)
$kpiTotalIncome = 0;
$kpiTotalExpenses = 0;
$kpiNetProfit = 0;
$kpiBankNetTransfers = 0;

try {
    // Range Income Total
    $feeSum = (float)$db->query("SELECT COALESCE(SUM(amount_paid),0) FROM fee_payments WHERE payment_date BETWEEN '$dateFrom' AND '$dateTo'")->fetchColumn();
    $incSum = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM income WHERE income_date BETWEEN '$dateFrom' AND '$dateTo'")->fetchColumn();
    $kpiTotalIncome = $feeSum + $incSum;

    // Range Expenses Total
    $expSum = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN '$dateFrom' AND '$dateTo'")->fetchColumn();
    $salSum = (float)$db->query("
        SELECT COALESCE(SUM(sd.net_salary),0) 
        FROM salary_details sd 
        JOIN salary_processing sp ON sd.processing_id = sp.id
        WHERE sd.payment_status = 'Paid' AND sd.payment_date BETWEEN '$dateFrom' AND '$dateTo'
    ")->fetchColumn();
    $kpiTotalExpenses = $expSum + $salSum;

    $kpiNetProfit = $kpiTotalIncome - $kpiTotalExpenses;

    // Bank Net Position in Range
    $bankDep = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM bank_transactions WHERE transaction_type = 'Deposit' AND date BETWEEN '$dateFrom' AND '$dateTo'")->fetchColumn();
    $bankWth = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM bank_transactions WHERE transaction_type = 'Withdrawal' AND date BETWEEN '$dateFrom' AND '$dateTo'")->fetchColumn();
    $kpiBankNetTransfers = $bankDep - $bankWth;

    switch ($selectedReport) {
        
        case 'profit_loss':
            $reportTitle = "Consolidated Profit & Loss Summary";
            
            // Fee Collection Details
            $stmtFee = $db->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM fee_payments WHERE payment_date BETWEEN :from AND :to");
            $stmtFee->execute(['from' => $dateFrom, 'to' => $dateTo]);
            $feeCollected = (float)$stmtFee->fetchColumn();

            // General Income Receipts
            $stmtInc = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM income WHERE income_date BETWEEN :from AND :to");
            $stmtInc->execute(['from' => $dateFrom, 'to' => $dateTo]);
            $genIncome = (float)$stmtInc->fetchColumn();

            // General Operating Expenses
            $stmtExp = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN :from AND :to");
            $stmtExp->execute(['from' => $dateFrom, 'to' => $dateTo]);
            $genExpenses = (float)$stmtExp->fetchColumn();

            // Salaries paid inside payroll_details / salary_details
            $stmtSal = $db->prepare("
                SELECT COALESCE(SUM(sd.net_salary),0) 
                FROM salary_details sd 
                JOIN salary_processing sp ON sd.processing_id = sp.id
                WHERE sd.payment_status = 'Paid' AND sd.payment_date BETWEEN :from AND :to
            ");
            $stmtSal->execute(['from' => $dateFrom, 'to' => $dateTo]);
            $salariesPaid = (float)$stmtSal->fetchColumn();

            $reportData = [
                'fees_collected'   => $feeCollected,
                'general_income'   => $genIncome,
                'total_income'     => $feeCollected + $genIncome,
                'general_expenses' => $genExpenses,
                'salaries_paid'    => $salariesPaid,
                'total_expenses'   => $genExpenses + $salariesPaid,
                'net_profit'       => ($feeCollected + $genIncome) - ($genExpenses + $salariesPaid)
            ];
            break;

        case 'income_report':
            $reportTitle = "General Income Statement Ledger";
            $sql = "
                SELECT inc.*, u.username as received_by_name
                FROM income inc
                LEFT JOIN users u ON inc.received_by = u.id
                WHERE inc.income_date BETWEEN :from AND :to
            ";
            $params = ['from' => $dateFrom, 'to' => $dateTo];
            if ($selectedMethod !== '') {
                $sql .= " AND inc.payment_method = :method";
                $params['method'] = $selectedMethod;
            }
            if ($searchKeyword !== '') {
                $sql .= " AND (inc.source LIKE :q OR inc.reference_no LIKE :q OR inc.description LIKE :q)";
                $params['q'] = '%' . $searchKeyword . '%';
            }
            $sql .= " ORDER BY inc.income_date DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'expense_report':
            $reportTitle = "Expense Ledger Audit Log";
            $sql = "
                SELECT exp.*, ec.name as category_name, u.username as paid_by_name
                FROM expenses exp
                JOIN expense_categories ec ON exp.category_id = ec.id
                LEFT JOIN users u ON exp.paid_by = u.id
                WHERE exp.expense_date BETWEEN :from AND :to
            ";
            $params = ['from' => $dateFrom, 'to' => $dateTo];
            if ($selectedCategory > 0) {
                $sql .= " AND exp.category_id = :cat";
                $params['cat'] = $selectedCategory;
            }
            if ($selectedMethod !== '') {
                $sql .= " AND exp.payment_method = :method";
                $params['method'] = $selectedMethod;
            }
            if ($searchKeyword !== '') {
                $sql .= " AND (exp.title LIKE :q OR exp.vendor_supplier LIKE :q OR exp.invoice_number LIKE :q)";
                $params['q'] = '%' . $searchKeyword . '%';
            }
            $sql .= " ORDER BY exp.expense_date DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'cash_book':
            $reportTitle = "General Cash Book Ledger";
            $sql = "SELECT * FROM cash_book WHERE date BETWEEN :from AND :to ORDER BY date DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'cash_opening':
            $reportTitle = "Daily Cash Opening Register";
            $sql = "SELECT * FROM cash_opening WHERE date BETWEEN :from AND :to ORDER BY date DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'cash_closing':
            $reportTitle = "Daily Cash Closing & Reconciliations Register";
            $sql = "
                SELECT cc.*, u.username as verified_by_name
                FROM cash_closing cc
                LEFT JOIN users u ON cc.verified_by = u.id
                WHERE cc.date BETWEEN :from AND :to
                ORDER BY cc.date DESC
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'bank_transactions':
            $reportTitle = "Bank Transactions Audit Log";
            $sql = "SELECT * FROM bank_transactions WHERE date BETWEEN :from AND :to";
            $params = ['from' => $dateFrom, 'to' => $dateTo];
            if ($searchKeyword !== '') {
                $sql .= " AND (bank_name LIKE :q OR account_number LIKE :q OR reference_number LIKE :q OR description LIKE :q)";
                $params['q'] = '%' . $searchKeyword . '%';
            }
            $sql .= " ORDER BY date DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'expense_category':
            $reportTitle = "Expenses Summarized by Category";
            $sql = "
                SELECT ec.name as category_name, COUNT(exp.id) as expense_count, SUM(exp.amount) as total_spent
                FROM expenses exp
                JOIN expense_categories ec ON exp.category_id = ec.id
                WHERE exp.expense_date BETWEEN :from AND :to
                GROUP BY exp.category_id
                ORDER BY total_spent DESC
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch (Exception $e) {
    error_log("Accounts report error: " . $e->getMessage());
}

$profitMarginPct = $kpiTotalIncome > 0 ? round(($kpiNetProfit / $kpiTotalIncome) * 100, 1) : 0;
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

:root {
    --acc-font: 'Outfit', sans-serif;
    --acc-primary: #4f46e5;
    --acc-dark: #0f172a;
    --acc-card-bg: #ffffff;
    --acc-border: #e2e8f0;
    --acc-radius: 16px;
    --acc-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.04), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
}

body {
    font-family: var(--acc-font);
    background-color: #f8fafc;
}

.acc-hero-card {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4338ca 100%);
    border-radius: var(--acc-radius);
    padding: 2.25rem 2rem;
    color: #ffffff;
    box-shadow: 0 20px 25px -5px rgba(49, 46, 129, 0.25);
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
}

.acc-hero-card::before {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.acc-kpi-card {
    background: var(--acc-card-bg);
    border: 1px solid var(--acc-border);
    border-radius: var(--acc-radius);
    padding: 1.35rem 1.25rem;
    box-shadow: var(--acc-shadow);
    height: 100%;
    transition: transform 0.2s ease;
}

.acc-kpi-card:hover {
    transform: translateY(-3px);
}

.acc-kpi-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.acc-kpi-val {
    font-weight: 700;
    font-size: 1.55rem;
    color: var(--acc-dark);
    line-height: 1.2;
    margin-top: 0.35rem;
}

.custom-table-card {
    background: var(--acc-card-bg);
    border: 1px solid var(--acc-border);
    border-radius: var(--acc-radius);
    box-shadow: var(--acc-shadow);
    overflow: hidden;
}

.custom-table th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--acc-border);
}

.custom-table td {
    padding: 1.1rem 1.25rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.925rem;
}

.custom-table tbody tr:hover {
    background-color: #f8fafc;
}

@media print {
    body { background: #fff !important; }
    .no-print, .btn, nav, header, sidebar { display: none !important; }
    .acc-hero-card { background: #1e1b4b !important; color: #fff !important; }
    #reportPrintArea { position: static !important; }
}
</style>

<div class="container-fluid px-0">

    <!-- Executive Hero Header -->
    <div class="acc-hero-card">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small fw-semibold">
                        <i class="fa-solid fa-scale-balanced me-1 text-warning"></i> Audit Ledger Suite
                    </span>
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small">
                        <?php echo date('d M Y', strtotime($dateFrom)); ?> — <?php echo date('d M Y', strtotime($dateTo)); ?>
                    </span>
                </div>
                <h1 class="fw-bold mb-2" style="font-size:1.95rem; letter-spacing:-0.02em;"><?php echo sanitize($reportTitle); ?></h1>
                <p class="text-white-50 mb-0 leading-relaxed" style="max-width: 650px;">
                    Monitor school general ledgers, cash books, daily opening & closing cash counter reconciliations, bank transfers, and Profit & Loss statements.
                </p>
            </div>
            <div class="col-lg-5 text-lg-end mt-4 mt-lg-0 no-print">
                <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end">
                    <button class="btn btn-light fw-bold text-dark px-4 py-2 rounded-3 shadow-sm" onclick="exportToExcel()">
                        <i class="fa-solid fa-file-excel text-success me-2"></i>Export Excel / CSV
                    </button>
                    <button class="btn btn-outline-light px-3 py-2 rounded-3" onclick="window.print()">
                        <i class="fa-solid fa-print me-2"></i>Print Ledger
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-light px-3 py-2 rounded-3">
                        <i class="fa-solid fa-arrow-left me-2"></i>Hub
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end" id="filterForm">
                
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-dark">Ledger / Report Focus</label>
                    <select class="form-select" name="report_type" onchange="this.form.submit()">
                        <option value="profit_loss" <?php echo $selectedReport === 'profit_loss' ? 'selected' : ''; ?>>Profit & Loss Statement</option>
                        <option value="cash_book" <?php echo $selectedReport === 'cash_book' ? 'selected' : ''; ?>>General Cash Book</option>
                        <option value="income_report" <?php echo $selectedReport === 'income_report' ? 'selected' : ''; ?>>Income Register</option>
                        <option value="expense_report" <?php echo $selectedReport === 'expense_report' ? 'selected' : ''; ?>>Expense Ledger</option>
                        <option value="cash_opening" <?php echo $selectedReport === 'cash_opening' ? 'selected' : ''; ?>>Cash Opening Register</option>
                        <option value="cash_closing" <?php echo $selectedReport === 'cash_closing' ? 'selected' : ''; ?>>Cash Closing Reconciliations</option>
                        <option value="bank_transactions" <?php echo $selectedReport === 'bank_transactions' ? 'selected' : ''; ?>>Bank Transactions Audit</option>
                        <option value="expense_category" <?php echo $selectedReport === 'expense_category' ? 'selected' : ''; ?>>Expense Category Summary</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-bold text-dark">Expense Category</label>
                    <select class="form-select" name="category_id" <?php echo ($selectedReport !== 'expense_report') ? 'disabled' : ''; ?>>
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $selectedCategory === (int)$cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-bold text-dark">Payment Method</label>
                    <select class="form-select" name="payment_method" <?php echo !in_array($selectedReport, ['income_report', 'expense_report']) ? 'disabled' : ''; ?>>
                        <option value="">All Methods</option>
                        <?php foreach ($paymentMethods as $pm): ?>
                            <option value="<?php echo $pm; ?>" <?php echo $selectedMethod === $pm ? 'selected' : ''; ?>><?php echo htmlspecialchars($pm); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-dark">Search Keyword</label>
                    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="Ref #, vendor, bank name...">
                </div>

                <div class="col-lg-6 col-md-6">
                    <label class="form-label small fw-bold text-dark">Date Range Filter</label>
                    <div class="input-group">
                        <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                        <span class="input-group-text bg-white">to</span>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                </div>

                <div class="col-lg-6 col-md-6 text-lg-end">
                    <a href="accounts.php" class="btn btn-outline-secondary px-4 me-2"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
                    <button type="submit" class="btn btn-primary px-5 fw-bold"><i class="fa-solid fa-magnifying-glass me-2"></i>Compile Ledger</button>
                </div>

            </form>
        </div>
    </div>

    <!-- Range Financial KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="acc-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Realized Incomes</span>
                    <div class="acc-kpi-icon bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-arrow-trend-up"></i>
                    </div>
                </div>
                <div class="acc-kpi-val text-success">Rs. <?php echo number_format($kpiTotalIncome, 0); ?></div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-receipt me-1 text-success"></i> Sum of student fees & income
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="acc-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Disbursed Expenses</span>
                    <div class="acc-kpi-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fa-solid fa-arrow-trend-down"></i>
                    </div>
                </div>
                <div class="acc-kpi-val text-danger">Rs. <?php echo number_format($kpiTotalExpenses, 0); ?></div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-money-bill-transfer me-1 text-danger"></i> Expenses & paid payroll
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="acc-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Net Operating Cashflow</span>
                    <div class="acc-kpi-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-piggy-bank"></i>
                    </div>
                </div>
                <div class="acc-kpi-val <?php echo $kpiNetProfit >= 0 ? 'text-primary' : 'text-danger'; ?>">
                    Rs. <?php echo number_format($kpiNetProfit, 0); ?>
                </div>
                <div class="mt-2 text-muted small">
                    <span class="badge bg-primary bg-opacity-10 text-primary fw-bold"><?php echo $profitMarginPct; ?>% Profit Margin</span>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="acc-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Bank Net Movement</span>
                    <div class="acc-kpi-icon bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                </div>
                <div class="acc-kpi-val text-info">Rs. <?php echo number_format($kpiBankNetTransfers, 0); ?></div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-vault me-1"></i> Net bank position in range
                </div>
            </div>
        </div>
    </div>

    <!-- Main Output Card -->
    <div class="custom-table-card shadow-sm mb-4" id="reportPrintArea">
        
        <!-- Print Header -->
        <div class="p-4 text-center d-none d-print-block border-bottom">
            <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h2>
            <h4 class="text-secondary fw-semibold mb-1"><?php echo sanitize($reportTitle); ?></h4>
            <div class="text-muted small">
                Printed Date: <?php echo date('d-M-Y H:i'); ?> | Date Range: <?php echo date('d M Y', strtotime($dateFrom)); ?> to <?php echo date('d M Y', strtotime($dateTo)); ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                
                <!-- 1. PROFIT & LOSS STATEMENT -->
                <?php if ($selectedReport === 'profit_loss'): ?>
                    <thead>
                        <tr class="table-light">
                            <th>Account Head Type</th>
                            <th>Subtype / Operational Source</th>
                            <th class="text-end">Realized Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Income Heads -->
                        <tr>
                            <td rowspan="2" class="fw-bold text-success align-middle text-uppercase"><i class="fa-solid fa-arrow-trend-up me-2"></i>Revenue (A)</td>
                            <td class="fw-semibold">Student Fees Realized Payments</td>
                            <td class="text-end text-success fw-bold">Rs. <?php echo number_format($reportData['fees_collected'], 2); ?></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">General Income Receipts</td>
                            <td class="text-end text-success fw-bold">Rs. <?php echo number_format($reportData['general_income'], 2); ?></td>
                        </tr>
                        <tr class="table-success fw-bold text-dark" style="border-bottom: 2px solid #555;">
                            <td colspan="2">TOTAL REVENUE REALIZED (A):</td>
                            <td class="text-end text-success fs-5">Rs. <?php echo number_format($reportData['total_income'], 2); ?></td>
                        </tr>
                        
                        <!-- Expense Heads -->
                        <tr>
                            <td rowspan="2" class="fw-bold text-danger align-middle text-uppercase"><i class="fa-solid fa-arrow-trend-down me-2"></i>Expenses (B)</td>
                            <td class="fw-semibold">General Operational Expenses</td>
                            <td class="text-end text-danger fw-bold">Rs. <?php echo number_format($reportData['general_expenses'], 2); ?></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Disbursed Employee Staff Payroll</td>
                            <td class="text-end text-danger fw-bold">Rs. <?php echo number_format($reportData['salaries_paid'], 2); ?></td>
                        </tr>
                        <tr class="table-danger fw-bold text-dark" style="border-bottom: 2px solid #555;">
                            <td colspan="2">TOTAL OPERATIONAL EXPENSES DISBURSED (B):</td>
                            <td class="text-end text-danger fs-5">Rs. <?php echo number_format($reportData['total_expenses'], 2); ?></td>
                        </tr>

                        <!-- Summary NET Profit/Loss -->
                        <?php $net = $reportData['net_profit']; ?>
                        <tr class="table-<?php echo $net >= 0 ? 'primary' : 'warning'; ?> fw-bold text-dark fs-5">
                            <td colspan="2"><i class="fa-solid fa-piggy-bank me-2"></i>CONSOLIDATED NET PROFIT / SURPLUS (A - B):</td>
                            <td class="text-end text-<?php echo $net >= 0 ? 'primary' : 'danger'; ?> fw-bold">Rs. <?php echo number_format($net, 2); ?></td>
                        </tr>
                    </tbody>

                <!-- 2. INCOME STATEMENT REPORT -->
                <?php elseif ($selectedReport === 'income_report'): ?>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Source</th>
                            <th>Reference No.</th>
                            <th>Description</th>
                            <th>Payment Method</th>
                            <th>Received By</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): $total = 0; ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No income records registered for this range.</td></tr>
                        <?php else: $total = 0; foreach ($reportData as $row): $total += (float)$row['amount']; ?>
                            <tr>
                                <td class="small fw-semibold"><?php echo date('d-M-Y', strtotime($row['income_date'])); ?></td>
                                <td class="fw-bold text-dark"><?php echo sanitize($row['source']); ?></td>
                                <td><code class="text-primary fw-bold"><?php echo sanitize($row['reference_no'] ?: '—'); ?></code></td>
                                <td class="small"><?php echo sanitize($row['description'] ?: '—'); ?></td>
                                <td><span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-1 fw-bold"><?php echo sanitize($row['payment_method']); ?></span></td>
                                <td><?php echo sanitize($row['received_by_name'] ?: 'System'); ?></td>
                                <td class="text-end fw-bold text-success">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="6">Total Realized Income Receipts:</td>
                                <td class="text-end text-success fs-5">Rs. <?php echo number_format($total, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 3. EXPENSE REPORT -->
                <?php elseif ($selectedReport === 'expense_report'): ?>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Title / Vendor</th>
                            <th>Category</th>
                            <th>Invoice / Ref</th>
                            <th>Payment Method</th>
                            <th>Paid By</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): $total = 0; ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No expense records found.</td></tr>
                        <?php else: $total = 0; foreach ($reportData as $row): $total += (float)$row['amount']; ?>
                            <tr>
                                <td class="small fw-semibold"><?php echo date('d-M-Y', strtotime($row['expense_date'])); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo sanitize($row['title']); ?></div>
                                    <div class="text-muted text-xs">Vendor: <?php echo sanitize($row['vendor_supplier'] ?: '—'); ?></div>
                                </td>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill fw-semibold"><?php echo sanitize($row['category_name']); ?></span></td>
                                <td><code class="text-muted"><?php echo sanitize($row['invoice_number'] ?: '—'); ?></code></td>
                                <td><span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1 fw-bold"><?php echo sanitize($row['payment_method']); ?></span></td>
                                <td><?php echo sanitize($row['paid_by_name'] ?: 'System'); ?></td>
                                <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="6">Total Expenses Disbursed:</td>
                                <td class="text-end text-danger fs-5">Rs. <?php echo number_format($total, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 4. GENERAL CASH BOOK -->
                <?php elseif ($selectedReport === 'cash_book'): ?>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Opening Cash</th>
                            <th class="text-end text-success">Income Receipts</th>
                            <th class="text-end text-danger">Expense Payments</th>
                            <th class="text-end">Closing Cash</th>
                            <th class="text-end fw-bold">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No cash book history logged.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo date('d-M-Y', strtotime($row['date'])); ?></td>
                                <td class="text-end text-muted">Rs. <?php echo number_format($row['opening_cash'], 2); ?></td>
                                <td class="text-end text-success fw-semibold">Rs. <?php echo number_format($row['income'], 2); ?></td>
                                <td class="text-end text-danger fw-semibold">Rs. <?php echo number_format($row['expenses'], 2); ?></td>
                                <td class="text-end text-dark">Rs. <?php echo number_format($row['closing_cash'], 2); ?></td>
                                <td class="text-end fw-bold text-primary">Rs. <?php echo number_format($row['balance'], 2); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 5. CASH OPENING REGISTER -->
                <?php elseif ($selectedReport === 'cash_opening'): ?>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Opening Cash Count</th>
                            <th>Operational Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="3" class="text-center py-5 text-muted">No cash opening entries recorded.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo date('d-M-Y', strtotime($row['date'])); ?></td>
                                <td class="text-end fw-bold text-success fs-6">Rs. <?php echo number_format($row['opening_cash'], 2); ?></td>
                                <td class="small text-muted"><?php echo sanitize($row['remarks'] ?: 'Counter starting balance initialized.'); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 6. DAILY CASH CLOSING & RECONCILIATIONS -->
                <?php elseif ($selectedReport === 'cash_closing'): ?>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Realized Collections</th>
                            <th class="text-end">Disbursed Expenses</th>
                            <th class="text-end">Expected Cash</th>
                            <th class="text-end">Counted Physical Cash</th>
                            <th class="text-center">Reconciliation Status</th>
                            <th>Verified By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No cash closing reports compiled.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo date('d-M-Y', strtotime($row['date'])); ?></td>
                                <td class="text-end text-success fw-semibold">Rs. <?php echo number_format($row['fee_collection'] + $row['other_income'], 2); ?></td>
                                <td class="text-end text-danger fw-semibold">Rs. <?php echo number_format($row['expenses'], 2); ?></td>
                                <td class="text-end text-muted">Rs. <?php echo number_format($row['cash_in_hand'], 2); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($row['closing_balance'], 2); ?></td>
                                <td class="text-center">
                                    <?php if ((float)$row['difference'] === 0.00): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold">Balanced</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger text-white rounded-pill px-3 py-1 fw-bold">Diff: Rs. <?php echo number_format($row['difference'], 2); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo sanitize($row['verified_by_name'] ?: 'System'); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 7. BANK TRANSACTIONS -->
                <?php elseif ($selectedReport === 'bank_transactions'): ?>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Bank Name</th>
                            <th>Account #</th>
                            <th>Reference # / Check</th>
                            <th class="text-center">Type</th>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No bank transaction logs found.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="small fw-semibold"><?php echo date('d-M-Y', strtotime($row['date'])); ?></td>
                                <td class="fw-bold text-dark"><?php echo sanitize($row['bank_name']); ?></td>
                                <td><code class="text-muted"><?php echo sanitize($row['account_number']); ?></code></td>
                                <td><code class="text-primary fw-bold"><?php echo sanitize($row['reference_number'] ?: '—'); ?></code></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['transaction_type'] === 'Deposit' ? 'success' : 'danger'; ?> bg-opacity-10 text-<?php echo $row['transaction_type'] === 'Deposit' ? 'success' : 'danger'; ?> px-3 py-1 rounded-pill fw-bold">
                                        <?php echo sanitize($row['transaction_type']); ?>
                                    </span>
                                </td>
                                <td class="small"><?php echo sanitize($row['description'] ?: '—'); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 8. EXPENSE BY CATEGORY -->
                <?php elseif ($selectedReport === 'expense_category'): ?>
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th class="text-center">Vouchers Count</th>
                            <th class="text-end">Total Spent</th>
                            <th class="text-end">Expenditure Share %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): $total = 0; ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">No category expense distributions found.</td></tr>
                        <?php else: $total = 0; foreach ($reportData as $row): $total += (float)$row['total_spent']; endforeach; ?>
                        <?php foreach ($reportData as $row): 
                            $cSpent = (float)$row['total_spent'];
                            $cPct = $total > 0 ? ($cSpent / $total) * 100 : 0;
                        ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo sanitize($row['category_name']); ?></td>
                                <td class="text-center text-muted fw-bold"><?php echo (int)$row['expense_count']; ?> Vouchers</td>
                                <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($cSpent, 2); ?></td>
                                <td class="text-end">
                                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill fw-bold">
                                        <?php echo number_format($cPct, 1); ?>% Share
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="2">Total Operational Category Expenses:</td>
                                <td class="text-end text-danger fs-5">Rs. <?php echo number_format($total, 2); ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                <?php endif; ?>

            </table>
        </div>
    </div>

</div>

<script>
function exportToExcel() {
    let table = document.getElementById("reportDataTable");
    if (!table) return;
    let html = table.outerHTML;
    let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
    let link = document.createElement("a");
    link.download = "<?php echo strtolower(str_replace(' ', '_', $reportTitle)); ?>_<?php echo date('Ymd'); ?>.xls";
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
