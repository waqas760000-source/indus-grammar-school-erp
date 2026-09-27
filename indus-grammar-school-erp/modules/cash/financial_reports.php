<?php
/**
 * Indus Grammar School ERP - Premium Financial Reports & Analytics Suite
 * Version 5.0.0
 */

require_once 'e:/Xampo/htdocs/indus-grammar-school-erp/indus-grammar-school-erp/config/app.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// Date Filters & Presets
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

$method = sanitize($_GET['method'] ?? '');
$catId  = (int)($_GET['category_id'] ?? 0);

// Fetch categories for dropdown
$categories = [];
try {
    $categories = $db->query("SELECT * FROM expense_categories WHERE status = 'Active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Initialize Report Datasets
$incomes = [];
$expenses = [];
$outstanding = [];
$categoryBreakdown = [];
$plSummary = [
    'fee_income'     => 0.00,
    'other_income'   => 0.00,
    'total_income'   => 0.00,
    'total_expenses' => 0.00,
    'net_profit'     => 0.00
];

try {
    // 1. INCOMES LIST
    $incWhere = " WHERE i.income_date BETWEEN :from AND :to";
    $incParams = ['from' => $fromDate, 'to' => $toDate];
    if ($method !== '') {
        $incWhere .= " AND i.payment_method = :method";
        $incParams['method'] = $method;
    }
    $stmt = $db->prepare("
        SELECT i.*, s.full_name as student_name, s.admission_no, u.username as received_by_name
        FROM income i
        LEFT JOIN students s ON i.student_id = s.id
        LEFT JOIN users u ON i.received_by = u.id
        $incWhere
        ORDER BY i.income_date DESC
    ");
    $stmt->execute($incParams);
    $incomes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. EXPENSES LIST
    $expWhere = " WHERE e.expense_date BETWEEN :from AND :to";
    $expParams = ['from' => $fromDate, 'to' => $toDate];
    if ($method !== '') {
        $expWhere .= " AND e.payment_method = :method";
        $expParams['method'] = $method;
    }
    if ($catId > 0) {
        $expWhere .= " AND e.category_id = :cat";
        $expParams['cat'] = $catId;
    }
    $stmt = $db->prepare("
        SELECT e.*, c.name as category_name, u.username as paid_by_name
        FROM expenses e
        LEFT JOIN expense_categories c ON e.category_id = c.id
        LEFT JOIN users u ON e.paid_by = u.id
        $expWhere
        ORDER BY e.expense_date DESC
    ");
    $stmt->execute($expParams);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. OUTSTANDING FEES LIST
    $outstanding = $db->query("
        SELECT l.*, s.full_name as student_name, s.admission_no, c.class_name, c.section
        FROM fee_ledger l
        JOIN students s ON l.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        WHERE l.status != 'Paid'
        ORDER BY c.class_name ASC, s.full_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 4. CATEGORY BREAKDOWN
    $stmt = $db->prepare("
        SELECT c.name as category_name, SUM(e.amount) as total_amount
        FROM expenses e
        JOIN expense_categories c ON e.category_id = c.id
        WHERE e.expense_date BETWEEN :from AND :to
        GROUP BY c.id
        ORDER BY total_amount DESC
    ");
    $stmt->execute(['from' => $fromDate, 'to' => $toDate]);
    $categoryBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. PROFIT & LOSS METRICS
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount),0) FROM income 
        WHERE income_date BETWEEN :from AND :to AND source = 'Student Fee Collection'
    ");
    $stmt->execute(['from' => $fromDate, 'to' => $toDate]);
    $plSummary['fee_income'] = (float)$stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount),0) FROM income 
        WHERE income_date BETWEEN :from AND :to AND source != 'Student Fee Collection'
    ");
    $stmt->execute(['from' => $fromDate, 'to' => $toDate]);
    $plSummary['other_income'] = (float)$stmt->fetchColumn();
    $plSummary['total_income'] = $plSummary['fee_income'] + $plSummary['other_income'];

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount),0) FROM expenses 
        WHERE expense_date BETWEEN :from AND :to
    ");
    $stmt->execute(['from' => $fromDate, 'to' => $toDate]);
    $plSummary['total_expenses'] = (float)$stmt->fetchColumn();
    $plSummary['net_profit'] = $plSummary['total_income'] - $plSummary['total_expenses'];

} catch (Exception $e) {
    error_log("Error generating financial reports: " . $e->getMessage());
}

// Compute total values for UI summaries
$totalIncomeVal = array_sum(array_column($incomes, 'amount'));
$totalExpenseVal = array_sum(array_column($expenses, 'amount'));
$totalOutstandingVal = 0.00;
foreach ($outstanding as $o) {
    $totalOutstandingVal += ($o['total_payable'] - $o['paid_amount']);
}

$totalVolume = $plSummary['total_income'] + $plSummary['total_expenses'];
$incomePct  = $totalVolume > 0 ? round(($plSummary['total_income'] / $totalVolume) * 100, 1) : 50;
$expensePct = $totalVolume > 0 ? round(($plSummary['total_expenses'] / $totalVolume) * 100, 1) : 50;

// Dynamic CSV File Exports
if (isset($_GET['export_type'])) {
    $type = sanitize($_GET['export_type']);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $type . '_' . date('Ymd') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if ($type === 'income') {
        fputcsv($output, [SCHOOL_NAME . ' - INCOME JOURNAL STATEMENT']);
        fputcsv($output, ['Filter Period:', $fromDate . ' to ' . $toDate]);
        fputcsv($output, []);
        fputcsv($output, ['Voucher No', 'Date', 'Source', 'Student Context', 'Description', 'Amount (Rs.)', 'Payment Method']);
        foreach ($incomes as $i) {
            fputcsv($output, [
                $i['reference_no'],
                date('d M Y', strtotime($i['income_date'])),
                $i['source'],
                $i['student_name'] ?: 'N/A',
                $i['description'] ?: '—',
                number_format($i['amount'], 2, '.', ''),
                $i['payment_method']
            ]);
        }
    } elseif ($type === 'expense') {
        fputcsv($output, [SCHOOL_NAME . ' - EXPENSES LEDGER STATEMENT']);
        fputcsv($output, ['Filter Period:', $fromDate . ' to ' . $toDate]);
        fputcsv($output, []);
        fputcsv($output, ['Voucher No', 'Date', 'Category', 'Expense Title', 'Vendor / Supplier', 'Amount (Rs.)', 'Payment Method']);
        foreach ($expenses as $e) {
            fputcsv($output, [
                'EXP-' . str_pad($e['id'], 6, '0', STR_PAD_LEFT),
                date('d M Y', strtotime($e['expense_date'])),
                $e['category_name'],
                $e['title'],
                $e['vendor_supplier'] ?: '—',
                number_format($e['amount'], 2, '.', ''),
                $e['payment_method']
            ]);
        }
    } elseif ($type === 'outstanding') {
        fputcsv($output, [SCHOOL_NAME . ' - OUTSTANDING SCHOOL FEES REPORT']);
        fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        fputcsv($output, ['Admission No', 'Student Name', 'Class & Section', 'Ledger Month', 'Academic Session', 'Payable Amount (Rs.)', 'Paid Amount (Rs.)', 'Outstanding Dues (Rs.)']);
        foreach ($outstanding as $o) {
            $due = $o['total_payable'] - $o['paid_amount'];
            fputcsv($output, [
                $o['admission_no'],
                $o['student_name'],
                $o['class_name'] . ' (' . $o['section'] . ')',
                $o['month'],
                $o['academic_year'],
                number_format($o['total_payable'], 2, '.', ''),
                number_format($o['paid_amount'], 2, '.', ''),
                number_format($due, 2, '.', '')
            ]);
        }
    }
    
    fclose($output);
    exit;
}

$pageTitle = 'Financial Reports & Analytics';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Premium Custom Inline CSS -->
<style>
.fin-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #311b92 100%);
    border-radius: 20px;
    color: #ffffff;
    box-shadow: 0 15px 35px rgba(15, 23, 42, 0.25);
    position: relative;
    overflow: hidden;
}
.fin-hero-card::before {
    content: "";
    position: absolute;
    top: -40%;
    right: -10%;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(129, 140, 248, 0.2) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}
.kpi-stat-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}
.kpi-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
}
.preset-btn-pill {
    border-radius: 30px;
    font-size: 0.825rem;
    font-weight: 600;
    padding: 6px 16px;
}
.preset-btn-pill.active {
    background-color: #2563eb;
    color: #ffffff !important;
}
.fin-nav-tabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    font-weight: 700;
    color: #64748b;
    padding: 16px 20px;
    transition: all 0.2s ease;
}
.fin-nav-tabs .nav-link.active {
    border-bottom-color: #2563eb;
    color: #2563eb !important;
    background-color: transparent;
}
.fin-table thead th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 16px 20px;
    border-bottom: 2px solid #e2e8f0;
}
.fin-table tbody td {
    padding: 16px 20px;
    font-size: 0.925rem;
    border-bottom: 1px solid #f1f5f9;
}
.badge-soft-success { background-color: #dcfce7; color: #15803d; }
.badge-soft-danger { background-color: #ffe4e6; color: #be123c; }
.badge-soft-warning { background-color: #fef3c7; color: #b45309; }
.badge-soft-info { background-color: #e0f2fe; color: #0369a1; }
.progress-bar-custom {
    height: 10px;
    border-radius: 6px;
    overflow: hidden;
    background-color: #e2e8f0;
}
</style>

<!-- Hero Executive Header -->
<div class="fin-hero-card p-4 p-lg-5 mb-4">
    <div class="row align-items-center g-4 position-relative" style="z-index: 2;">
        <div class="col-lg-7">
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-white bg-opacity-10 backdrop-blur text-warning small fw-bold mb-3 border border-white border-opacity-10">
                <i class="fa-solid fa-chart-pie"></i> Executive Financial Audit Suite
            </div>
            <h2 class="fw-extrabold text-white mb-2 display-6">Financial Reports & Statements</h2>
            <p class="text-slate-300 mb-0 opacity-90 leading-relaxed fs-6">
                Comprehensive Profit & Loss (P&L) statements, Income journals, Operational expense ledgers, Category spending breakdowns, and Student fee outstanding receivers.
            </p>
        </div>
        <div class="col-lg-5 text-lg-end">
            <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                <button class="btn btn-light btn-lg px-4 rounded-3 fw-bold shadow-sm" onclick="printActiveTab()">
                    <i class="fa-solid fa-print me-2 text-primary"></i>Print Active Statement
                </button>
            </div>
            <div class="mt-3 text-slate-300 small">
                <i class="fa-solid fa-calendar-days me-1 text-warning"></i> Period Range: <strong class="text-white"><?php echo date('d M Y', strtotime($fromDate)); ?></strong> to <strong class="text-white"><?php echo date('d M Y', strtotime($toDate)); ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- Executive Financial KPIs -->
<div class="row g-3 mb-4">
    <!-- Gross Income -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-stat-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase tracking-wider">Gross Income</span>
                <div class="p-3 rounded-3 bg-success-soft text-success fs-4">
                    <i class="fa-solid fa-arrow-down-left"></i>
                </div>
            </div>
            <h3 class="fw-extrabold text-dark mb-1">Rs. <?php echo number_format($plSummary['total_income'], 2); ?></h3>
            <div class="d-flex align-items-center gap-1 text-success small fw-semibold">
                <i class="fa-solid fa-circle-check"></i>
                <span>Fees & Other Inflows</span>
            </div>
        </div>
    </div>

    <!-- Gross Expenses -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-stat-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase tracking-wider">Gross Outflows</span>
                <div class="p-3 rounded-3 bg-danger-soft text-danger fs-4">
                    <i class="fa-solid fa-arrow-up-right"></i>
                </div>
            </div>
            <h3 class="fw-extrabold text-dark mb-1">Rs. <?php echo number_format($plSummary['total_expenses'], 2); ?></h3>
            <div class="d-flex align-items-center gap-1 text-danger small fw-semibold">
                <i class="fa-solid fa-receipt"></i>
                <span>Operational Expenses</span>
            </div>
        </div>
    </div>

    <!-- Net Profit / Loss -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-stat-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase tracking-wider">Net P&L Balance</span>
                <div class="p-3 rounded-3 <?php echo $plSummary['net_profit'] >= 0 ? 'bg-primary-soft text-primary' : 'bg-danger-soft text-danger'; ?> fs-4">
                    <i class="fa-solid <?php echo $plSummary['net_profit'] >= 0 ? 'fa-scale-balanced' : 'fa-arrow-trend-down'; ?>"></i>
                </div>
            </div>
            <h3 class="fw-extrabold <?php echo $plSummary['net_profit'] >= 0 ? 'text-primary' : 'text-danger'; ?> mb-1">
                <?php echo $plSummary['net_profit'] >= 0 ? '+' : ''; ?>Rs. <?php echo number_format($plSummary['net_profit'], 2); ?>
            </h3>
            <div class="d-flex align-items-center gap-1 text-muted small fw-semibold">
                <span class="badge bg-<?php echo $plSummary['net_profit'] >= 0 ? 'success' : 'danger'; ?> px-2 py-1 rounded-pill">
                    <?php echo $plSummary['net_profit'] >= 0 ? 'Surplus' : 'Deficit'; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Outstanding Dues Receivable -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-stat-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase tracking-wider">Outstanding Dues</span>
                <div class="p-3 rounded-3 bg-warning-soft text-warning fs-4">
                    <i class="fa-solid fa-user-clock"></i>
                </div>
            </div>
            <h3 class="fw-extrabold text-dark mb-1">Rs. <?php echo number_format($totalOutstandingVal, 2); ?></h3>
            <div class="d-flex align-items-center gap-1 text-warning small fw-semibold">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?php echo count($outstanding); ?> Unpaid Student Accounts</span>
            </div>
        </div>
    </div>
</div>

<!-- Global Filter Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
    <div class="card-body p-4">
        <!-- Quick Presets -->
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="small fw-bold text-muted me-2"><i class="fa-solid fa-bolt text-warning me-1"></i>Quick Period:</span>
            <a href="?preset=today" class="btn btn-outline-secondary preset-btn-pill <?php echo $preset === 'today' ? 'active' : ''; ?>">Today</a>
            <a href="?preset=week" class="btn btn-outline-secondary preset-btn-pill <?php echo $preset === 'week' ? 'active' : ''; ?>">Last 7 Days</a>
            <a href="?preset=month" class="btn btn-outline-secondary preset-btn-pill <?php echo ($preset === 'month' || (empty($preset) && $fromDate === date('Y-m-01'))) ? 'active' : ''; ?>">This Month</a>
            <a href="?preset=last_month" class="btn btn-outline-secondary preset-btn-pill <?php echo $preset === 'last_month' ? 'active' : ''; ?>">Last Month</a>
            <a href="?preset=year" class="btn btn-outline-secondary preset-btn-pill <?php echo $preset === 'year' ? 'active' : ''; ?>">This Year</a>
        </div>

        <form method="GET" class="row g-3 align-items-end pt-2 border-top">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">From Date</label>
                <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">To Date</label>
                <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Payment Mode</label>
                <select class="form-select form-select-sm" name="method">
                    <option value="">All Modes</option>
                    <option value="Cash" <?php echo ($method === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                    <option value="Bank" <?php echo ($method === 'Bank') ? 'selected' : ''; ?>>Bank</option>
                    <option value="Cheque" <?php echo ($method === 'Cheque') ? 'selected' : ''; ?>>Cheque</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Category</label>
                <select class="form-select form-select-sm" name="category_id">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($catId == $cat['id']) ? 'selected' : ''; ?>><?php echo sanitize($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1 py-2 rounded-3 fw-bold">
                    <i class="fa-solid fa-filter me-1"></i>Generate
                </button>
                <a href="financial_reports.php" class="btn btn-sm btn-outline-secondary py-2 rounded-3" title="Reset Filters">
                    <i class="fa-solid fa-rotate-right"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabbed Report Panel Card -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:18px; overflow:hidden;">
    <div class="card-header border-bottom bg-white p-0">
        <ul class="nav nav-tabs fin-nav-tabs nav-fill border-0 px-3 pt-2" id="reportTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pl-tab" data-bs-toggle="tab" data-bs-target="#plPane" type="button" role="tab">
                    <i class="fa-solid fa-scale-balanced me-2 text-primary"></i>Profit & Loss (P&L)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="inc-tab" data-bs-toggle="tab" data-bs-target="#incPane" type="button" role="tab">
                    <i class="fa-solid fa-circle-down me-2 text-success"></i>Income Journal
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="exp-tab" data-bs-toggle="tab" data-bs-target="#expPane" type="button" role="tab">
                    <i class="fa-solid fa-circle-up me-2 text-danger"></i>Expense Ledger
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="out-tab" data-bs-toggle="tab" data-bs-target="#outPane" type="button" role="tab">
                    <i class="fa-solid fa-user-clock me-2 text-warning"></i>Outstanding Fees
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="break-tab" data-bs-toggle="tab" data-bs-target="#breakPane" type="button" role="tab">
                    <i class="fa-solid fa-chart-pie me-2 text-info"></i>Category Breakdown
                </button>
            </li>
        </ul>
    </div>
    
    <div class="tab-content card-body p-4" id="reportTabsContent">
        
        <!-- TAB 1: PROFIT & LOSS STATEMENT -->
        <div class="tab-pane fade show active" id="plPane" role="tabpanel">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <div>
                    <h5 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-scale-balanced me-2 text-primary"></i>Institutional Profit & Loss Statement</h5>
                    <p class="text-muted small mb-0">Period Revenue vs Expenditure Summary</p>
                </div>
                <button class="btn btn-sm btn-outline-primary px-3 rounded-3 fw-bold" onclick="printReport('printPLTemplate')">
                    <i class="fa-solid fa-print me-1"></i>Print P&L Statement
                </button>
            </div>

            <!-- Volume Ratio Progress Bar -->
            <div class="mb-4 p-3 bg-light rounded-3 border">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-bold text-dark"><i class="fa-solid fa-chart-line me-2 text-primary"></i>Financial Revenue / Expense Volume Ratio</span>
                    <span class="small text-muted font-monospace">
                        Income: <strong class="text-success"><?php echo $incomePct; ?>%</strong> | Expense: <strong class="text-danger"><?php echo $expensePct; ?>%</strong>
                    </span>
                </div>
                <div class="progress-bar-custom d-flex">
                    <div class="bg-success" style="width: <?php echo $incomePct; ?>%;"></div>
                    <div class="bg-danger" style="width: <?php echo $expensePct; ?>%;"></div>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Income Streams -->
                <div class="col-md-6">
                    <div class="p-4 border rounded-3 bg-white h-100 shadow-sm">
                        <h6 class="fw-bold text-success border-bottom pb-3 mb-3 d-flex align-items-center justify-content-between">
                            <span><i class="fa-solid fa-circle-arrow-down me-2"></i>Revenue Income Streams</span>
                            <span class="badge bg-success-soft text-success rounded-pill px-3">INFLOWS</span>
                        </h6>
                        <table class="table table-borderless table-sm mb-0">
                            <tr class="py-2">
                                <td class="text-muted">Student Fees Collection:</td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($plSummary['fee_income'], 2); ?></td>
                            </tr>
                            <tr class="py-2">
                                <td class="text-muted">Other Inflow Income:</td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($plSummary['other_income'], 2); ?></td>
                            </tr>
                            <tr class="border-top pt-3 fs-6">
                                <td class="fw-bold text-success">Total Gross Inflows (A):</td>
                                <td class="text-end fw-extrabold text-success">Rs. <?php echo number_format($plSummary['total_income'], 2); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Expenses Debits -->
                <div class="col-md-6">
                    <div class="p-4 border rounded-3 bg-white h-100 shadow-sm">
                        <h6 class="fw-bold text-danger border-bottom pb-3 mb-3 d-flex align-items-center justify-content-between">
                            <span><i class="fa-solid fa-circle-arrow-up me-2"></i>Operational Expenses Debits</span>
                            <span class="badge bg-danger-soft text-danger rounded-pill px-3">OUTFLOWS</span>
                        </h6>
                        <table class="table table-borderless table-sm mb-0">
                            <tr class="py-2">
                                <td class="text-muted">Operational & Bills Expenses:</td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($plSummary['total_expenses'], 2); ?></td>
                            </tr>
                            <tr class="border-top pt-3 fs-6">
                                <td class="fw-bold text-danger">Total Gross Outflows (B):</td>
                                <td class="text-end fw-extrabold text-danger">Rs. <?php echo number_format($plSummary['total_expenses'], 2); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Net Result Banner -->
                <div class="col-12 mt-4 text-center">
                    <div class="p-4 rounded-4 border d-inline-block text-center shadow-sm" style="min-width: 420px; background: <?php echo ($plSummary['net_profit'] < 0) ? '#ffe4e6' : '#dcfce7'; ?>;">
                        <span class="small fw-bold text-uppercase tracking-wider text-muted d-block mb-1">Net Period Financial Balance</span>
                        <h2 class="fw-extrabold mb-1 <?php echo ($plSummary['net_profit'] < 0) ? 'text-danger' : 'text-success'; ?>">
                            <?php echo ($plSummary['net_profit'] >= 0) ? '+' : ''; ?>Rs. <?php echo number_format($plSummary['net_profit'], 2); ?>
                        </h2>
                        <span class="badge bg-<?php echo ($plSummary['net_profit'] < 0) ? 'danger' : 'success'; ?> rounded-pill px-4 py-2 mt-2 fw-bold fs-7">
                            <i class="fa-solid <?php echo ($plSummary['net_profit'] < 0) ? 'fa-triangle-exclamation' : 'fa-circle-check'; ?> me-1"></i>
                            <?php echo ($plSummary['net_profit'] < 0) ? 'DEFICIT STATEMENT' : 'SURPLUS STATEMENT'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- TAB 2: INCOME JOURNAL -->
        <div class="tab-pane fade" id="incPane" role="tabpanel">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-circle-down text-success me-2"></i>Audit Income Journal Logs</h5>
                    <p class="text-muted small mb-0">Total Inflows Sum: <strong class="text-success">Rs. <?php echo number_format($totalIncomeVal, 2); ?></strong></p>
                </div>
                <div class="d-flex gap-2">
                    <input type="text" id="incSearchInput" class="form-control form-control-sm rounded-pill px-3" placeholder="Search income log..." style="width: 200px;">
                    <button class="btn btn-sm btn-outline-primary px-3 rounded-3 fw-bold" onclick="printReport('printIncomeTemplate')">
                        <i class="fa-solid fa-print me-1"></i>Print
                    </button>
                    <a href="?export_type=income&from_date=<?php echo $fromDate; ?>&to_date=<?php echo $toDate; ?>&method=<?php echo $method; ?>" class="btn btn-sm btn-success px-3 rounded-3 fw-bold">
                        <i class="fa-solid fa-file-excel me-1"></i>Export CSV
                    </a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table fin-table align-middle mb-0" id="incomeTable">
                    <thead>
                        <tr>
                            <th>Voucher</th>
                            <th>Date</th>
                            <th>Source</th>
                            <th>Student Context</th>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($incomes)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No income transactions found for this period.</td></tr>
                        <?php else: foreach ($incomes as $i): ?>
                            <tr class="inc-row" data-search="<?php echo strtolower($i['reference_no'] . ' ' . $i['source'] . ' ' . $i['student_name'] . ' ' . $i['payment_method']); ?>">
                                <td><code class="fw-bold text-primary"><?php echo sanitize($i['reference_no']); ?></code></td>
                                <td><?php echo date('d M Y', strtotime($i['income_date'])); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo sanitize($i['source']); ?></span></td>
                                <td class="fw-semibold text-dark"><?php echo $i['student_id'] ? sanitize($i['student_name']) : '—'; ?></td>
                                <td class="text-muted small" style="max-width: 200px;"><?php echo sanitize($i['description'] ?: '—'); ?></td>
                                <td class="text-end fw-extrabold text-success">+ Rs. <?php echo number_format($i['amount'], 2); ?></td>
                                <td class="text-center"><span class="badge badge-soft-success rounded-pill px-3 py-1.5 fw-bold"><?php echo $i['payment_method']; ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- TAB 3: EXPENSE JOURNAL -->
        <div class="tab-pane fade" id="expPane" role="tabpanel">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-circle-up text-danger me-2"></i>Audit Expense Ledger Logs</h5>
                    <p class="text-muted small mb-0">Total Outflows Sum: <strong class="text-danger">Rs. <?php echo number_format($totalExpenseVal, 2); ?></strong></p>
                </div>
                <div class="d-flex gap-2">
                    <input type="text" id="expSearchInput" class="form-control form-control-sm rounded-pill px-3" placeholder="Search expense log..." style="width: 200px;">
                    <button class="btn btn-sm btn-outline-primary px-3 rounded-3 fw-bold" onclick="printReport('printExpenseTemplate')">
                        <i class="fa-solid fa-print me-1"></i>Print
                    </button>
                    <a href="?export_type=expense&from_date=<?php echo $fromDate; ?>&to_date=<?php echo $toDate; ?>&method=<?php echo $method; ?>&category_id=<?php echo $catId; ?>" class="btn btn-sm btn-danger px-3 rounded-3 fw-bold">
                        <i class="fa-solid fa-file-excel me-1"></i>Export CSV
                    </a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table fin-table align-middle mb-0" id="expenseTable">
                    <thead>
                        <tr>
                            <th>Voucher No</th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Expense Title</th>
                            <th>Vendor / Supplier</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No expense transactions found for this period.</td></tr>
                        <?php else: foreach ($expenses as $e): ?>
                            <tr class="exp-row" data-search="<?php echo strtolower($e['id'] . ' ' . $e['category_name'] . ' ' . $e['title'] . ' ' . $e['vendor_supplier'] . ' ' . $e['payment_method']); ?>">
                                <td><code class="fw-bold text-danger">EXP-<?php echo str_pad($e['id'], 6, '0', STR_PAD_LEFT); ?></code></td>
                                <td><?php echo date('d M Y', strtotime($e['expense_date'])); ?></td>
                                <td><span class="badge badge-soft-danger px-2.5 py-1 rounded-pill fw-bold"><?php echo sanitize($e['category_name']); ?></span></td>
                                <td class="fw-bold text-dark"><?php echo sanitize($e['title']); ?></td>
                                <td class="text-muted small"><?php echo sanitize($e['vendor_supplier'] ?: '—'); ?></td>
                                <td class="text-end fw-extrabold text-danger">- Rs. <?php echo number_format($e['amount'], 2); ?></td>
                                <td class="text-center"><span class="badge bg-light text-dark border px-3 py-1.5"><?php echo $e['payment_method']; ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- TAB 4: OUTSTANDING DUES -->
        <div class="tab-pane fade" id="outPane" role="tabpanel">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-user-clock text-warning me-2"></i>Outstanding School Fees Receivable</h5>
                    <p class="text-muted small mb-0">Total Receivables: <strong class="text-danger">Rs. <?php echo number_format($totalOutstandingVal, 2); ?></strong></p>
                </div>
                <div class="d-flex gap-2">
                    <input type="text" id="outSearchInput" class="form-control form-control-sm rounded-pill px-3" placeholder="Search student or class..." style="width: 200px;">
                    <button class="btn btn-sm btn-outline-primary px-3 rounded-3 fw-bold" onclick="printReport('printOutstandingTemplate')">
                        <i class="fa-solid fa-print me-1"></i>Print List
                    </button>
                    <a href="?export_type=outstanding" class="btn btn-sm btn-warning px-3 rounded-3 fw-bold text-dark">
                        <i class="fa-solid fa-file-excel me-1"></i>Export CSV
                    </a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table fin-table align-middle mb-0" id="outstandingTable">
                    <thead>
                        <tr>
                            <th>Adm No</th>
                            <th>Student Name</th>
                            <th>Class & Section</th>
                            <th>Ledger Month</th>
                            <th>Session</th>
                            <th class="text-end">Payable</th>
                            <th class="text-end">Outstanding Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($outstanding)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No students with outstanding fee balances found.</td></tr>
                        <?php else: foreach ($outstanding as $o): 
                            $due = $o['total_payable'] - $o['paid_amount'];
                        ?>
                            <tr class="out-row" data-search="<?php echo strtolower($o['admission_no'] . ' ' . $o['student_name'] . ' ' . $o['class_name'] . ' ' . $o['section'] . ' ' . $o['month']); ?>">
                                <td><code class="fw-bold text-primary"><?php echo sanitize($o['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo sanitize($o['student_name']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo sanitize($o['class_name'] . ' (' . $o['section'] . ')'); ?></span></td>
                                <td><?php echo sanitize($o['month']); ?></td>
                                <td><?php echo sanitize($o['academic_year']); ?></td>
                                <td class="text-end fw-semibold">Rs. <?php echo number_format($o['total_payable'], 2); ?></td>
                                <td class="text-end fw-extrabold text-danger">Rs. <?php echo number_format($due, 2); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- TAB 5: CATEGORY BREAKDOWN -->
        <div class="tab-pane fade" id="breakPane" role="tabpanel">
            <h5 class="fw-bold text-dark mb-4"><i class="fa-solid fa-chart-pie me-2 text-info"></i>Expense Breakdown by Account Category</h5>
            
            <div class="row g-4 align-items-center">
                <div class="col-md-7">
                    <div class="table-responsive border rounded-3 overflow-hidden">
                        <table class="table fin-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Expense Category</th>
                                    <th class="text-end">Total Amount Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categoryBreakdown)): ?>
                                    <tr><td colspan="2" class="text-center py-4 text-muted">No expenses recorded in this period.</td></tr>
                                <?php else: foreach ($categoryBreakdown as $c): ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><i class="fa-solid fa-folder me-2 text-danger"></i><?php echo sanitize($c['category_name']); ?></td>
                                        <td class="text-end fw-extrabold text-danger">Rs. <?php echo number_format($c['total_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="col-md-5">
                    <div class="p-5 text-center bg-light rounded-4 border">
                        <i class="fa-solid fa-chart-pie fs-1 text-primary mb-3"></i>
                        <h6 class="fw-bold text-muted text-uppercase mb-1">Total Period Spending</h6>
                        <h2 class="fw-extrabold text-danger mb-0">Rs. <?php echo number_format($totalExpenseVal, 2); ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- PRINT TEMPLATES (Hidden) -->
<div id="printPLTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 30px; border: 2px solid #0f172a; width: 650px; margin: 0 auto; border-radius:12px; color: #1e293b;">
        <div style="text-align: center; border-bottom: 2px double #0f172a; padding-bottom: 12px; margin-bottom: 20px;">
            <h1 style="margin: 0; font-size: 22px; text-transform: uppercase; color: #0f172a; font-weight: 800;"><?php echo SCHOOL_NAME; ?></h1>
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b;"><?php echo SCHOOL_ADDRESS; ?></p>
            <div style="margin-top: 12px; background: #0f172a; color: #fff; padding: 6px 18px; border-radius: 20px; display: inline-block; font-size: 13px; font-weight: bold; letter-spacing: 1px;">
                PROFIT & LOSS STATEMENT SUMMARY
            </div>
        </div>
        <table style="width: 100%; font-size: 13px; margin-bottom: 20px; background: #f8fafc; padding: 10px; border-radius: 6px;">
            <tr>
                <td><strong>Period Range:</strong> <?php echo date('d M Y', strtotime($fromDate)); ?> to <?php echo date('d M Y', strtotime($toDate)); ?></td>
                <td style="text-align: right;"><strong>Date Generated:</strong> <?php echo date('d M Y, h:i A'); ?></td>
            </tr>
        </table>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 30px;" border="1" cellpadding="10">
            <tr style="background: #0f172a; color: #fff; font-weight: bold;">
                <td colspan="2">1. REVENUE INCOME STREAMS</td>
            </tr>
            <tr>
                <td>Student Fee Collections:</td>
                <td style="text-align: right; color: #16a34a; font-weight: bold;">Rs. <?php echo number_format($plSummary['fee_income'], 2); ?></td>
            </tr>
            <tr>
                <td>Other Manual Inflow:</td>
                <td style="text-align: right; color: #16a34a; font-weight: bold;">Rs. <?php echo number_format($plSummary['other_income'], 2); ?></td>
            </tr>
            <tr style="font-weight: bold; background: #f0fdf4;">
                <td>Total Inflow Income (A):</td>
                <td style="text-align: right; color: #16a34a;">Rs. <?php echo number_format($plSummary['total_income'], 2); ?></td>
            </tr>
            <tr style="background: #0f172a; color: #fff; font-weight: bold;">
                <td colspan="2">2. OPERATIONAL DEBITS</td>
            </tr>
            <tr>
                <td>Operational & Bills Expense:</td>
                <td style="text-align: right; color: #dc2626; font-weight: bold;">Rs. <?php echo number_format($plSummary['total_expenses'], 2); ?></td>
            </tr>
            <tr style="font-weight: bold; background: #ffe4e6;">
                <td>Total Outflow Expenses (B):</td>
                <td style="text-align: right; color: #dc2626;">Rs. <?php echo number_format($plSummary['total_expenses'], 2); ?></td>
            </tr>
            <tr style="background: #f1f5f9; font-weight: bold; font-size: 15px;">
                <td>NET BALANCE PROFIT / (LOSS):</td>
                <td style="text-align: right; color: <?php echo ($plSummary['net_profit'] < 0) ? '#dc2626' : '#16a34a'; ?>;">
                    Rs. <?php echo number_format($plSummary['net_profit'], 2); ?>
                </td>
            </tr>
        </table>
        
        <table style="width: 100%; margin-top: 50px; font-size: 11px; text-align: center;">
            <tr>
                <td><div style="border-top: 1px solid #334155; width: 160px; margin: 0 auto; padding-top: 6px;">Prepared By Accountant</div></td>
                <td><div style="border-top: 1px solid #334155; width: 160px; margin: 0 auto; padding-top: 6px;">Principal Approval</div></td>
            </tr>
        </table>
    </div>
</div>

<div id="printIncomeTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 25px; border: 1px solid #ccc; width: 750px; margin: 0 auto;">
        <h3 style="text-align: center; margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h3>
        <h4 style="text-align: center; margin: 5px 0 20px 0; background: #0f172a; color:#fff; padding: 8px;">INCOME JOURNAL LOG REPORT</h4>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;" border="1" cellpadding="8">
            <thead>
                <tr style="background: #f1f5f9;">
                    <th>Voucher</th>
                    <th>Date</th>
                    <th>Source</th>
                    <th>Student Context</th>
                    <th>Amount</th>
                    <th>Method</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incomes as $i): ?>
                    <tr>
                        <td><?php echo $i['reference_no']; ?></td>
                        <td><?php echo date('d M Y', strtotime($i['income_date'])); ?></td>
                        <td><?php echo $i['source']; ?></td>
                        <td><?php echo $i['student_name'] ?: '—'; ?></td>
                        <td>Rs. <?php echo number_format($i['amount'], 2); ?></td>
                        <td><?php echo $i['payment_method']; ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="font-weight: bold; background: #f9f9f9;">
                    <td colspan="4" style="text-align: right;">Total Income Sum:</td>
                    <td colspan="2" style="color: #16a34a;">Rs. <?php echo number_format($totalIncomeVal, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div id="printExpenseTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 25px; border: 1px solid #ccc; width: 750px; margin: 0 auto;">
        <h3 style="text-align: center; margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h3>
        <h4 style="text-align: center; margin: 5px 0 20px 0; background: #0f172a; color:#fff; padding: 8px;">OPERATIONAL EXPENSE LEDGER REPORT</h4>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;" border="1" cellpadding="8">
            <thead>
                <tr style="background: #f1f5f9;">
                    <th>Voucher No</th>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Title Description</th>
                    <th>Amount</th>
                    <th>Method</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $e): ?>
                    <tr>
                        <td>EXP-<?php echo str_pad($e['id'], 6, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo date('d M Y', strtotime($e['expense_date'])); ?></td>
                        <td><?php echo $e['category_name']; ?></td>
                        <td><?php echo $e['title']; ?></td>
                        <td>Rs. <?php echo number_format($e['amount'], 2); ?></td>
                        <td><?php echo $e['payment_method']; ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="font-weight: bold; background: #f9f9f9;">
                    <td colspan="4" style="text-align: right;">Total Expense Sum:</td>
                    <td colspan="2" style="color: #dc2626;">Rs. <?php echo number_format($totalExpenseVal, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div id="printOutstandingTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 25px; border: 1px solid #ccc; width: 750px; margin: 0 auto;">
        <h3 style="text-align: center; margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h3>
        <h4 style="text-align: center; margin: 5px 0 20px 0; background: #0f172a; color:#fff; padding: 8px;">OUTSTANDING DUES RECEIVABLE LIST</h4>
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;" border="1" cellpadding="8">
            <thead>
                <tr style="background: #f1f5f9;">
                    <th>Adm No</th>
                    <th>Student Name</th>
                    <th>Class Section</th>
                    <th>Ledger Month</th>
                    <th>Session</th>
                    <th style="text-align: right;">Payable</th>
                    <th style="text-align: right;">Dues Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($outstanding as $o): ?>
                    <tr>
                        <td><?php echo $o['admission_no']; ?></td>
                        <td><strong><?php echo $o['student_name']; ?></strong></td>
                        <td><?php echo $o['class_name'] . ' (' . $o['section'] . ')'; ?></td>
                        <td><?php echo $o['month']; ?></td>
                        <td><?php echo $o['academic_year']; ?></td>
                        <td style="text-align: right;">Rs. <?php echo number_format($o['total_payable'], 2); ?></td>
                        <td style="text-align: right; color:#dc2626;">Rs. <?php echo number_format($o['total_payable'] - $o['paid_amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="font-weight: bold; background: #f9f9f9;">
                    <td colspan="6" style="text-align: right;">Total Dues Receivable:</td>
                    <td style="text-align: right; color:#dc2626;">Rs. <?php echo number_format($totalOutstandingVal, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php $extraJS = '<script>
function printReport(templateId) {
    const printContent = document.getElementById(templateId).innerHTML;
    const w = window.open("", "_blank");
    w.document.write("<html><head><title>Financial Report Print</title></head><body onload=\"window.print(); window.close();\">");
    w.document.write(printContent);
    w.document.write("</body></html>");
    w.document.close();
}

function printActiveTab() {
    const activeTab = document.querySelector("#reportTabs .nav-link.active");
    if (!activeTab) return;
    const id = activeTab.id;
    if (id === "pl-tab") printReport("printPLTemplate");
    else if (id === "inc-tab") printReport("printIncomeTemplate");
    else if (id === "exp-tab") printReport("printExpenseTemplate");
    else if (id === "out-tab") printReport("printOutstandingTemplate");
    else if (id === "break-tab") printReport("printExpenseTemplate");
}

document.addEventListener("DOMContentLoaded", function() {
    // Instant Search Filters for Tables
    const setupSearch = (inputId, rowClass) => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener("keyup", function() {
                const query = this.value.toLowerCase().trim();
                document.querySelectorAll("." + rowClass).forEach(row => {
                    const text = row.getAttribute("data-search") || "";
                    row.style.display = text.includes(query) ? "" : "none";
                });
            });
        }
    };

    setupSearch("incSearchInput", "inc-row");
    setupSearch("expSearchInput", "exp-row");
    setupSearch("outSearchInput", "out-row");
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>