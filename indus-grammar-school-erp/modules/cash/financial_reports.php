<?php
/**
 * Indus Grammar School ERP - Financial Reports & Statements
 * Version 4.0.0
 */

$pageTitle = 'Financial Reports';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// Default date filters
$fromDate = sanitize($_GET['from_date'] ?? date('Y-m-01'));
$toDate   = sanitize($_GET['to_date'] ?? date('Y-m-d'));
$method   = sanitize($_GET['method'] ?? '');
$catId    = (int)($_GET['category_id'] ?? 0);

// Fetch categories for dropdown
$categories = [];
try {
    $categories = $db->query("SELECT * FROM expense_categories WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}

// Setup reports queries
$incomes = [];
$expenses = [];
$outstanding = [];
$categoryBreakdown = [];
$plSummary = [
    'fee_income' => 0.00,
    'other_income' => 0.00,
    'total_income' => 0.00,
    'total_expenses' => 0.00,
    'net_profit' => 0.00
];

try {
    // 1. INCOMES LIST (matching filters)
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
        ORDER BY i.income_date ASC
    ");
    $stmt->execute($incParams);
    $incomes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. EXPENSES LIST (matching filters)
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
        ORDER BY e.expense_date ASC
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

    // 4. CATEGORY BREAKDOWN GROUPING
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

// Handle dynamic CSV file download exports
if (isset($_GET['export_type'])) {
    $type = sanitize($_GET['export_type']);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $type . '_' . date('Ymd') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if ($type === 'income') {
        fputcsv($output, ['INCOME JOURNAL REPORT', 'Period:', $fromDate . ' to ' . $toDate]);
        fputcsv($output, ['Voucher No', 'Date', 'Source', 'Student Name', 'Description', 'Amount (Rs.)', 'Payment Method']);
        foreach ($incomes as $i) {
            fputcsv($output, [
                $i['reference_no'],
                $i['income_date'],
                $i['source'],
                $i['student_name'] ?: 'N/A',
                $i['description'],
                number_format($i['amount'], 2, '.', ''),
                $i['payment_method']
            ]);
        }
    } elseif ($type === 'expense') {
        fputcsv($output, ['EXPENSES JOURNAL REPORT', 'Period:', $fromDate . ' to ' . $toDate]);
        fputcsv($output, ['Voucher No', 'Date', 'Category', 'Expense Title', 'Vendor / Supplier', 'Amount (Rs.)', 'Payment Method']);
        foreach ($expenses as $e) {
            fputcsv($output, [
                'EXP-' . str_pad($e['id'], 6, '0', STR_PAD_LEFT),
                $e['expense_date'],
                $e['category_name'],
                $e['title'],
                $e['vendor_supplier'],
                number_format($e['amount'], 2, '.', ''),
                $e['payment_method']
            ]);
        }
    } elseif ($type === 'outstanding') {
        fputcsv($output, ['OUTSTANDING SCHOOL FEES REPORT']);
        fputcsv($output, ['Admission No', 'Student Name', 'Class / Section', 'Month', 'Academic Session', 'Dues Payable (Rs.)', 'Dues Remaining (Rs.)']);
        foreach ($outstanding as $o) {
            fputcsv($output, [
                $o['admission_no'],
                $o['student_name'],
                $o['class_name'] . ' (' . $o['section'] . ')',
                $o['month'],
                $o['academic_year'],
                number_format($o['total_payable'], 2, '.', ''),
                number_format($o['total_payable'] - $o['paid_amount'], 2, '.', '')
            ]);
        }
    }
    
    fclose($output);
    exit;
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Financial Reports</h3>
        <p class="text-muted small mb-0">Generate P&L statements, verify ledger journals, track categories breakdowns, and download outstanding fees lists.</p>
    </div>
</div>

<!-- Global Filter Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Date & Parameters</h6>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">From Date</label>
                <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">To Date</label>
                <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Payment Mode</label>
                <select class="form-select form-select-sm" name="method">
                    <option value="">All Modes</option>
                    <option value="Cash" <?php echo ($method === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                    <option value="Bank" <?php echo ($method === 'Bank') ? 'selected' : ''; ?>>Bank</option>
                    <option value="Cheque" <?php echo ($method === 'Cheque') ? 'selected' : ''; ?>>Cheque</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Expense Category</label>
                <select class="form-select form-select-sm" name="category_id">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($catId == $cat['id']) ? 'selected' : ''; ?>><?php echo sanitize($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Generate</button>
            </div>
        </form>
    </div>
</div>

<!-- Tabbed Panel -->
<div class="card border-0 shadow-sm" style="border-radius:12px;">
    <div class="card-header border-0 bg-white p-0">
        <ul class="nav nav-tabs nav-fill border-0 px-3 pt-3" id="reportTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold py-3 text-secondary" id="pl-tab" data-bs-toggle="tab" data-bs-target="#plPane" type="button" role="tab">
                    <i class="fa-solid fa-scale-balanced me-2 text-primary"></i>Profit & Loss
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-3 text-secondary" id="inc-tab" data-bs-toggle="tab" data-bs-target="#incPane" type="button" role="tab">
                    <i class="fa-solid fa-circle-down me-2 text-success"></i>Income Journal
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-3 text-secondary" id="exp-tab" data-bs-toggle="tab" data-bs-target="#expPane" type="button" role="tab">
                    <i class="fa-solid fa-circle-up me-2 text-danger"></i>Expense Journal
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-3 text-secondary" id="out-tab" data-bs-toggle="tab" data-bs-target="#outPane" type="button" role="tab">
                    <i class="fa-solid fa-user-clock me-2 text-warning"></i>Outstanding Dues
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-3 text-secondary" id="break-tab" data-bs-toggle="tab" data-bs-target="#breakPane" type="button" role="tab">
                    <i class="fa-solid fa-chart-bar me-2 text-info"></i>Categories Breakdown
                </button>
            </li>
        </ul>
    </div>
    
    <div class="tab-content card-body p-4" id="reportTabsContent">
        
        <!-- TAB 1: PROFIT & LOSS SUMMARY -->
        <div class="tab-pane fade show active" id="plPane" role="tabpanel">
            <div class="row align-items-center mb-4">
                <div class="col-sm-6">
                    <h5 class="fw-bold mb-0 text-secondary">School Profit & Loss Statement Summary</h5>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <button class="btn btn-sm btn-outline-primary px-3" onclick="printReport('printPLTemplate')">
                        <i class="fa-solid fa-print me-1"></i>Print P&L Statement
                    </button>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="p-4 border rounded bg-light h-100">
                        <h6 class="fw-bold text-success border-bottom pb-2 mb-3"><i class="fa-solid fa-circle-arrow-down me-2"></i>Total Income streams</h6>
                        <table class="table table-borderless table-sm mb-0">
                            <tr>
                                <td class="text-muted">Student Fees Collections:</td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($plSummary['fee_income'], 2); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Other Inflow Income:</td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($plSummary['other_income'], 2); ?></td>
                            </tr>
                            <tr class="border-top pt-2">
                                <td class="fw-bold text-success">Total Gross Inflows (A):</td>
                                <td class="text-end fw-bold text-success">Rs. <?php echo number_format($plSummary['total_income'], 2); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="p-4 border rounded bg-light h-100">
                        <h6 class="fw-bold text-danger border-bottom pb-2 mb-3"><i class="fa-solid fa-circle-arrow-up me-2"></i>Total Operational debits</h6>
                        <table class="table table-borderless table-sm mb-0">
                            <tr>
                                <td class="text-muted">Home & Office Bills:</td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($plSummary['total_expenses'], 2); ?></td>
                            </tr>
                            <tr class="border-top pt-2">
                                <td class="fw-bold text-danger">Total Outflows (B):</td>
                                <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($plSummary['total_expenses'], 2); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="col-12 mt-4 text-center">
                    <div class="p-4 rounded-3 border d-inline-block text-center shadow-sm" style="min-width: 400px; background: <?php echo ($plSummary['net_profit'] < 0) ? '#fdf2f2' : '#f0fdf4'; ?>;">
                        <h5 class="fw-semibold text-muted text-uppercase mb-2">Net Income Balance Statement</h5>
                        <h2 class="fw-bold mb-1 <?php echo ($plSummary['net_profit'] < 0) ? 'text-danger' : 'text-success'; ?>">
                            Rs. <?php echo number_format($plSummary['net_profit'], 2); ?>
                        </h2>
                        <span class="badge bg-<?php echo ($plSummary['net_profit'] < 0) ? 'danger' : 'success'; ?> rounded-pill px-4 py-2 mt-2">
                            <?php echo ($plSummary['net_profit'] < 0) ? 'DEFICIT / LOSS' : 'SURPLUS / PROFIT'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- TAB 2: INCOME JOURNAL -->
        <div class="tab-pane fade" id="incPane" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-secondary mb-0">Income Journal Records</h5>
                <div>
                    <button class="btn btn-sm btn-outline-primary px-3 me-2" onclick="printReport('printIncomeTemplate')">
                        <i class="fa-solid fa-print me-1"></i>Print List
                    </button>
                    <a href="?export_type=income&from_date=<?php echo $fromDate; ?>&to_date=<?php echo $toDate; ?>&method=<?php echo $method; ?>" class="btn btn-sm btn-primary px-3">
                        <i class="fa-solid fa-file-excel me-1"></i>Export CSV
                    </a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
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
                                <td><code><?php echo sanitize($i['reference_no']); ?></code></td>
                                <td><?php echo date('d M Y', strtotime($i['income_date'])); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo sanitize($i['source']); ?></span></td>
                                <td><?php echo $i['student_id'] ? sanitize($i['student_name']) : '—'; ?></td>
                                <td class="fw-bold text-dark">Rs. <?php echo number_format($i['amount'], 2); ?></td>
                                <td><span class="badge bg-success-soft text-success rounded-pill px-3"><?php echo $i['payment_method']; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="4" class="text-end">Total Sum:</td>
                            <td colspan="2" class="text-success">Rs. <?php echo number_format($totalIncomeVal, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- TAB 3: EXPENSE JOURNAL -->
        <div class="tab-pane fade" id="expPane" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-secondary mb-0">Operational Expense Ledger</h5>
                <div>
                    <button class="btn btn-sm btn-outline-primary px-3 me-2" onclick="printReport('printExpenseTemplate')">
                        <i class="fa-solid fa-print me-1"></i>Print List
                    </button>
                    <a href="?export_type=expense&from_date=<?php echo $fromDate; ?>&to_date=<?php echo $toDate; ?>&method=<?php echo $method; ?>&category_id=<?php echo $catId; ?>" class="btn btn-sm btn-primary px-3">
                        <i class="fa-solid fa-file-excel me-1"></i>Export CSV
                    </a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
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
                                <td><code>EXP-<?php echo str_pad($e['id'], 6, '0', STR_PAD_LEFT); ?></code></td>
                                <td><?php echo date('d M Y', strtotime($e['expense_date'])); ?></td>
                                <td><span class="badge bg-danger-soft text-danger"><?php echo sanitize($e['category_name']); ?></span></td>
                                <td><?php echo sanitize($e['title']); ?></td>
                                <td class="fw-bold text-danger">Rs. <?php echo number_format($e['amount'], 2); ?></td>
                                <td><span class="badge bg-light text-dark border px-3"><?php echo $e['payment_method']; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="4" class="text-end">Total Sum:</td>
                            <td colspan="2" class="text-danger">Rs. <?php echo number_format($totalExpenseVal, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- TAB 4: OUTSTANDING DUES -->
        <div class="tab-pane fade" id="outPane" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-secondary mb-0">Outstanding School Dues</h5>
                <div>
                    <button class="btn btn-sm btn-outline-primary px-3 me-2" onclick="printReport('printOutstandingTemplate')">
                        <i class="fa-solid fa-print me-1"></i>Print List
                    </button>
                    <a href="?export_type=outstanding" class="btn btn-sm btn-primary px-3">
                        <i class="fa-solid fa-file-excel me-1"></i>Export CSV
                    </a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Adm No</th>
                            <th>Student Name</th>
                            <th>Class Section</th>
                            <th>Ledger Month</th>
                            <th>Session</th>
                            <th class="text-end">Payable</th>
                            <th class="text-end">Outstanding Dues</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($outstanding)): ?>
                            <tr><td colspan="7" class="text-center text-muted">No students with outstanding fee balances found.</td></tr>
                        <?php else: foreach ($outstanding as $o): 
                            $due = $o['total_payable'] - $o['paid_amount'];
                        ?>
                            <tr>
                                <td><code><?php echo sanitize($o['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo sanitize($o['student_name']); ?></td>
                                <td><?php echo sanitize($o['class_name'] . ' (' . $o['section'] . ')'); ?></td>
                                <td><?php echo sanitize($o['month']); ?></td>
                                <td><?php echo sanitize($o['academic_year']); ?></td>
                                <td class="text-end">Rs. <?php echo number_format($o['total_payable'], 2); ?></td>
                                <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($due, 2); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="6" class="text-end">Total Dues Receivable:</td>
                            <td class="text-end text-danger">Rs. <?php echo number_format($totalOutstandingVal, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- TAB 5: CATEGORIES BREAKDOWN -->
        <div class="tab-pane fade" id="breakPane" role="tabpanel">
            <h5 class="fw-bold text-secondary mb-4 border-bottom pb-2">Expenses breakdown by Category</h5>
            
            <div class="row align-items-center">
                <div class="col-md-7">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="table-light">
                                    <th>Category Name</th>
                                    <th class="text-end">Total Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($categoryBreakdown)): ?>
                                    <tr><td colspan="2" class="text-center text-muted py-3">No expenses recorded in this date period.</td></tr>
                                <?php else: foreach ($categoryBreakdown as $c): ?>
                                    <tr>
                                        <td class="fw-bold text-secondary"><i class="fa-solid fa-folder me-2 text-danger"></i><?php echo sanitize($c['category_name']); ?></td>
                                        <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($c['total_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="col-md-5 text-center bg-light rounded-3 p-4 border border-light">
                    <i class="fa-solid fa-chart-pie fs-1 text-primary-soft mb-3"></i>
                    <h6 class="fw-bold text-muted text-uppercase mb-1">Total Bill Spending</h6>
                    <h2 class="fw-bold text-danger">Rs. <?php echo number_format($totalExpenseVal, 2); ?></h2>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- ─────────────────────────────────────────────────────────────────────────
     PRINT LAYOUTS (Hidden, popped up via JS print)
     ───────────────────────────────────────────────────────────────────────── -->

<!-- 1. P&L Print -->
<div id="printPLTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 30px; border: 1px solid #ccc; width: 650px; margin: 0 auto; border-radius:10px;">
        <div style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px;">
            <h2 style="margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h2>
            <p style="margin: 5px 0 0 0; font-size: 11px; color: #555;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 15px 0 0 0; background: #eee; padding: 6px; border-radius: 4px;">PROFIT & LOSS STATEMENT SUMMARY</h4>
        </div>
        <table style="width: 100%; font-size: 13px; margin-bottom: 20px;">
            <tr>
                <td><strong>Period Range:</strong> <?php echo $fromDate; ?> to <?php echo $toDate; ?></td>
                <td style="text-align: right;"><strong>Date Generated:</strong> <?php echo date('d M Y'); ?></td>
            </tr>
        </table>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;" border="1" cellpadding="8">
            <tr style="background: #f9f9f9; font-weight: bold;">
                <td colspan="2">1. REVENUE INCOME STREAMS</td>
            </tr>
            <tr>
                <td>Student Fee Collections:</td>
                <td style="text-align: right;">Rs. <?php echo number_format($plSummary['fee_income'], 2); ?></td>
            </tr>
            <tr>
                <td>Other Manual Inflow:</td>
                <td style="text-align: right;">Rs. <?php echo number_format($plSummary['other_income'], 2); ?></td>
            </tr>
            <tr style="font-weight: bold; color: #2e7d32;">
                <td>Total Inflow Income (A):</td>
                <td style="text-align: right;">Rs. <?php echo number_format($plSummary['total_income'], 2); ?></td>
            </tr>
            <tr style="background: #f9f9f9; font-weight: bold;">
                <td colspan="2">2. OPERATIONAL DEBITS</td>
            </tr>
            <tr>
                <td>Bills and Rent Expense:</td>
                <td style="text-align: right;">Rs. <?php echo number_format($plSummary['total_expenses'], 2); ?></td>
            </tr>
            <tr style="font-weight: bold; color: #c62828;">
                <td>Total Outflow Expenses (B):</td>
                <td style="text-align: right;">Rs. <?php echo number_format($plSummary['total_expenses'], 2); ?></td>
            </tr>
            <tr style="background: #eee; font-weight: bold; font-size: 14px;">
                <td>NET BALANCE PROFIT / (LOSS):</td>
                <td style="text-align: right; color: <?php echo ($plSummary['net_profit'] < 0) ? '#c62828' : '#2e7d32'; ?>;">
                    Rs. <?php echo number_format($plSummary['net_profit'], 2); ?>
                </td>
            </tr>
        </table>
        <table style="width: 100%; margin-top: 60px; font-size: 11px; text-align: center;">
            <tr>
                <td><div style="border-top: 1px solid #000; width: 150px; margin: 0 auto;">Accountant Signature</div></td>
                <td><div style="border-top: 1px solid #000; width: 150px; margin: 0 auto;">Principal Officer</div></td>
            </tr>
        </table>
    </div>
</div>

<!-- 2. Income List Print -->
<div id="printIncomeTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 25px; border: 1px solid #ccc; width: 750px; margin: 0 auto;">
        <h3 style="text-align: center; margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h3>
        <h4 style="text-align: center; margin: 5px 0 20px 0; background: #eee; padding: 5px;">INCOME JOURNAL LOG REPORT</h4>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;" border="1" cellpadding="6">
            <thead>
                <tr style="background: #f0f0f0;">
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
                    <td colspan="4" style="text-align: right;">Total Sum:</td>
                    <td colspan="2">Rs. <?php echo number_format($totalIncomeVal, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 3. Expense List Print -->
<div id="printExpenseTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 25px; border: 1px solid #ccc; width: 750px; margin: 0 auto;">
        <h3 style="text-align: center; margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h3>
        <h4 style="text-align: center; margin: 5px 0 20px 0; background: #eee; padding: 5px;">OPERATIONAL EXPENSE LEDGER REPORT</h4>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;" border="1" cellpadding="6">
            <thead>
                <tr style="background: #f0f0f0;">
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
                    <td colspan="4" style="text-align: right;">Total Sum:</td>
                    <td colspan="2">Rs. <?php echo number_format($totalExpenseVal, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 4. Outstanding Dues Print -->
<div id="printOutstandingTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 25px; border: 1px solid #ccc; width: 750px; margin: 0 auto;">
        <h3 style="text-align: center; margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h3>
        <h4 style="text-align: center; margin: 5px 0 20px 0; background: #eee; padding: 5px;">OUTSTANDING DUES COLLECTION LIST</h4>
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;" border="1" cellpadding="6">
            <thead>
                <tr style="background: #f0f0f0;">
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
                        <td style="text-align: right; color:#c62828;">Rs. <?php echo number_format($o['total_payable'] - $o['paid_amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="font-weight: bold; background: #f9f9f9;">
                    <td colspan="6" style="text-align: right;">Total Dues Receivable:</td>
                    <td style="text-align: right; color:#c62828;">Rs. <?php echo number_format($totalOutstandingVal, 2); ?></td>
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
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
