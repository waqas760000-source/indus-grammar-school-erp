<?php
/**
 * Indus Grammar School ERP - Comprehensive Accounts & Finance Ledger Reports
 * Version 4.0.0
 */

$pageTitle = 'Accounts Reports Panel';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Selectors
$categories = $db->query("SELECT id, name FROM expense_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$paymentMethods = ['Cash', 'Bank', 'Cheque', 'Online'];

// Filter values
$selectedReport = sanitize($_GET['report_type'] ?? 'income_report');
$selectedCategory = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$selectedMethod  = sanitize($_GET['payment_method'] ?? '');
$dateFrom        = sanitize($_GET['date_from'] ?? date('Y-m-01'));
$dateTo          = sanitize($_GET['date_to'] ?? date('Y-m-d'));

$reportTitle = "Accounts Report";
$reportData = [];

try {
    switch ($selectedReport) {
        
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
            $sql .= " ORDER BY inc.income_date DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'expense_report':
            $reportTitle = "Expense Ledger Report";
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
            $sql .= " ORDER BY exp.expense_date DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'cash_book':
            $reportTitle = "General Cash Book Logs";
            $sql = "SELECT * FROM cash_book WHERE date BETWEEN :from AND :to ORDER BY date DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'cash_opening':
            $reportTitle = "Cash Opening Balances Register";
            $sql = "SELECT * FROM cash_opening WHERE date BETWEEN :from AND :to ORDER BY date DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'cash_closing':
            $reportTitle = "Daily Cash Closing Register & Reconciliations";
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
            $sql = "SELECT * FROM bank_transactions WHERE date BETWEEN :from AND :to ORDER BY date DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
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

        case 'profit_loss':
            $reportTitle = "Consolidated Profit & Loss Summary";
            
            // Fee Collection
            $stmtFee = $db->prepare("SELECT SUM(amount_paid) FROM fee_payments WHERE payment_date BETWEEN :from AND :to");
            $stmtFee->execute(['from' => $dateFrom, 'to' => $dateTo]);
            $feeCollected = (float)$stmtFee->fetchColumn();

            // General Income
            $stmtInc = $db->prepare("SELECT SUM(amount) FROM income WHERE income_date BETWEEN :from AND :to");
            $stmtInc->execute(['from' => $dateFrom, 'to' => $dateTo]);
            $genIncome = (float)$stmtInc->fetchColumn();

            // General Expenses
            $stmtExp = $db->prepare("SELECT SUM(amount) FROM expenses WHERE expense_date BETWEEN :from AND :to");
            $stmtExp->execute(['from' => $dateFrom, 'to' => $dateTo]);
            $genExpenses = (float)$stmtExp->fetchColumn();

            // Salaries paid inside payroll_details / salary_details
            $stmtSal = $db->prepare("
                SELECT SUM(sd.net_salary) 
                FROM salary_details sd 
                JOIN salary_processing sp ON sd.processing_id = sp.id
                WHERE sd.payment_status = 'Paid' AND sd.payment_date BETWEEN :from AND :to
            ");
            $stmtSal->execute(['from' => $dateFrom, 'to' => $dateTo]);
            $salariesPaid = (float)$stmtSal->fetchColumn();

            $reportData = [
                'fees_collected' => $feeCollected,
                'general_income' => $genIncome,
                'total_income'   => $feeCollected + $genIncome,
                
                'general_expenses' => $genExpenses,
                'salaries_paid'    => $salariesPaid,
                'total_expenses'   => $genExpenses + $salariesPaid,
                
                'net_profit' => ($feeCollected + $genIncome) - ($genExpenses + $salariesPaid)
            ];
            break;
    }
} catch (Exception $e) {
    error_log("Accounts report error: " . $e->getMessage());
}

?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-scale-balanced text-primary me-2"></i>Accounts Reports</h3>
        <p class="text-muted small mb-0">Monitor school ledger sheets, daily opening & closing cash drawers, bank transfers and Profit & Loss.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-outline-primary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Ledger</button>
        <button class="btn btn-outline-success px-3 ms-2" onclick="exportToExcel()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
        <a href="dashboard.php" class="btn btn-outline-secondary px-3 ms-2"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Filters Form -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Select Ledger Type</label>
                <select class="form-select" name="report_type" onchange="this.form.submit()">
                    <option value="income_report" <?php echo $selectedReport === 'income_report' ? 'selected' : ''; ?>>Income Report</option>
                    <option value="expense_report" <?php echo $selectedReport === 'expense_report' ? 'selected' : ''; ?>>Expense Report</option>
                    <option value="cash_book" <?php echo $selectedReport === 'cash_book' ? 'selected' : ''; ?>>Cash Book</option>
                    <option value="cash_opening" <?php echo $selectedReport === 'cash_opening' ? 'selected' : ''; ?>>Cash Opening</option>
                    <option value="cash_closing" <?php echo $selectedReport === 'cash_closing' ? 'selected' : ''; ?>>Cash Closing</option>
                    <option value="bank_transactions" <?php echo $selectedReport === 'bank_transactions' ? 'selected' : ''; ?>>Bank Transactions</option>
                    <option value="expense_category" <?php echo $selectedReport === 'expense_category' ? 'selected' : ''; ?>>Expense Category Report</option>
                    <option value="profit_loss" <?php echo $selectedReport === 'profit_loss' ? 'selected' : ''; ?>>Profit & Loss Summary</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Expense Category</label>
                <select class="form-select" name="category_id" <?php echo ($selectedReport !== 'expense_report') ? 'disabled' : ''; ?>>
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $selectedCategory === (int)$cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Payment Method</label>
                <select class="form-select" name="payment_method" <?php echo !in_array($selectedReport, ['income_report', 'expense_report']) ? 'disabled' : ''; ?>>
                    <option value="">All Methods</option>
                    <?php foreach ($paymentMethods as $pm): ?>
                        <option value="<?php echo $pm; ?>" <?php echo $selectedMethod === $pm ? 'selected' : ''; ?>><?php echo htmlspecialchars($pm); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Billing Date Range</label>
                <div class="input-group">
                    <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                    <span class="input-group-text">to</span>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                </div>
            </div>

            <div class="col-12 text-end mt-3">
                <a href="accounts.php" class="btn btn-outline-secondary px-4 py-2 me-2"><i class="fa-solid fa-rotate-left me-2"></i>Reset</a>
                <button type="submit" class="btn btn-primary px-5 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Output -->
<div class="card border-0 shadow-sm" style="border-radius:12px;" id="reportPrintArea">
    <!-- Print Header -->
    <div class="card-header bg-white border-0 pt-4 px-4 text-center d-none d-print-block border-bottom pb-3">
        <h3 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h3>
        <h5 class="text-secondary fw-semibold mb-1"><?php echo htmlspecialchars($reportTitle); ?></h5>
        <div class="text-muted small">
            Date: <?php echo date('d-M-Y H:i'); ?> | Generated By: <?php echo htmlspecialchars($_SESSION['username'] ?? 'ERP Admin'); ?>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                
                <!-- INCOME REPORT -->
                <?php if ($selectedReport === 'income_report'): ?>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Source</th>
                            <th>Reference No.</th>
                            <th>Description</th>
                            <th>Method</th>
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
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['source']); ?></td>
                                <td><code class="text-muted"><?php echo htmlspecialchars($row['reference_no'] ?: '—'); ?></code></td>
                                <td class="small"><?php echo htmlspecialchars($row['description'] ?: '—'); ?></td>
                                <td><span class="badge bg-info-soft text-info rounded-pill px-3 py-1 fw-bold"><?php echo htmlspecialchars($row['payment_method']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['received_by_name'] ?: 'System'); ?></td>
                                <td class="text-end fw-bold text-success">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="6">Total Realized Income:</td>
                                <td class="text-end text-success fs-5">Rs. <?php echo number_format($total, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- EXPENSE REPORT -->
                <?php elseif ($selectedReport === 'expense_report'): ?>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Title / Vendor</th>
                            <th>Category</th>
                            <th>Invoice / Ref</th>
                            <th>Method</th>
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
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['title']); ?></div>
                                    <div class="text-muted text-xs">To: <?php echo htmlspecialchars($row['vendor_supplier'] ?: '—'); ?></div>
                                </td>
                                <td><span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($row['category_name']); ?></span></td>
                                <td><code class="text-muted"><?php echo htmlspecialchars($row['invoice_number'] ?: '—'); ?></code></td>
                                <td><span class="badge bg-secondary-soft text-secondary rounded-pill px-3 py-1 fw-bold"><?php echo htmlspecialchars($row['payment_method']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['paid_by_name'] ?: 'System'); ?></td>
                                <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="6">Total Expenses disbursed:</td>
                                <td class="text-end text-danger fs-5">Rs. <?php echo number_format($total, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- CASH BOOK TABLE -->
                <?php elseif ($selectedReport === 'cash_book'): ?>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Opening Cash</th>
                            <th class="text-end text-success">Income</th>
                            <th class="text-end text-danger">Expenses</th>
                            <th class="text-end">Closing Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No cash book history logged.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo date('d-M-Y', strtotime($row['date'])); ?></td>
                                <td class="text-end text-muted">Rs. <?php echo number_format($row['opening_cash'], 2); ?></td>
                                <td class="text-end text-success fw-semibold">Rs. <?php echo number_format($row['income'], 2); ?></td>
                                <td class="text-end text-danger fw-semibold">Rs. <?php echo number_format($row['expenses'], 2); ?></td>
                                <td class="text-end fw-bold text-primary">Rs. <?php echo number_format($row['closing_cash'], 2); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- DAILY CASH CLOSINGS -->
                <?php elseif ($selectedReport === 'cash_closing'): ?>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Collections</th>
                            <th class="text-end">Expenses</th>
                            <th class="text-end">Closing Balance</th>
                            <th class="text-end">Cash In Hand</th>
                            <th class="text-center">Difference</th>
                            <th>Verified By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No closing reports compiled.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo date('d-M-Y', strtotime($row['date'])); ?></td>
                                <td class="text-end text-success fw-semibold">Rs. <?php echo number_format($row['fee_collection'] + $row['other_income'], 2); ?></td>
                                <td class="text-end text-danger fw-semibold">Rs. <?php echo number_format($row['expenses'], 2); ?></td>
                                <td class="text-end text-muted">Rs. <?php echo number_format($row['closing_balance'], 2); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($row['cash_in_hand'], 2); ?></td>
                                <td class="text-center">
                                    <?php if ((float)$row['difference'] === 0.00): ?>
                                        <span class="badge bg-success-soft rounded-pill px-3 py-1 fw-bold text-xs">Balanced</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger text-white rounded-pill px-3 py-1 fw-bold text-xs">Rs. <?php echo number_format($row['difference'], 2); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['verified_by_name'] ?: 'System'); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- BANK TRANSACTIONS -->
                <?php elseif ($selectedReport === 'bank_transactions'): ?>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Bank Account</th>
                            <th>Ref Number</th>
                            <th class="text-center">Type</th>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No bank logs found.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="small fw-semibold"><?php echo date('d-M-Y', strtotime($row['date'])); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['bank_name']); ?></div>
                                    <div class="text-muted text-xs">A/C: <?php echo htmlspecialchars($row['account_number']); ?></div>
                                </td>
                                <td><code class="text-muted"><?php echo htmlspecialchars($row['reference_number'] ?: '—'); ?></code></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['transaction_type'] === 'Deposit' ? 'success' : 'danger'; ?>-soft px-3 py-1 rounded-pill fw-bold text-xs"><?php echo $row['transaction_type']; ?></span>
                                </td>
                                <td class="small"><?php echo htmlspecialchars($row['description'] ?: '—'); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- EXPENSE BY CATEGORY -->
                <?php elseif ($selectedReport === 'expense_category'): ?>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="text-center">Vouchers Count</th>
                            <th class="text-end">Total Spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): $total = 0; ?>
                            <tr><td colspan="3" class="text-center py-5 text-muted">No expense distributions found.</td></tr>
                        <?php else: $total = 0; foreach ($reportData as $row): $total += (float)$row['total_spent']; ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['category_name']); ?></td>
                                <td class="text-center text-muted fw-bold"><?php echo $row['expense_count']; ?> vouchers</td>
                                <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($row['total_spent'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="2">Total category expenses realized:</td>
                                <td class="text-end text-danger fs-5">Rs. <?php echo number_format($total, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- PROFIT & LOSS STATEMENT -->
                <?php elseif ($selectedReport === 'profit_loss'): ?>
                    <thead>
                        <tr class="table-light">
                            <th>Account Head Type</th>
                            <th>Ledger Subtype</th>
                            <th class="text-end">Amount Summary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Income Heads -->
                        <tr>
                            <td rowspan="2" class="fw-bold text-success align-middle text-uppercase"><i class="fa-solid fa-arrow-trend-up me-2"></i>Income (A)</td>
                            <td class="fw-semibold">Student Fees Realized Payments</td>
                            <td class="text-end text-success fw-bold">Rs. <?php echo number_format($reportData['fees_collected'], 2); ?></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">General Income Receipts</td>
                            <td class="text-end text-success fw-bold">Rs. <?php echo number_format($reportData['general_income'], 2); ?></td>
                        </tr>
                        <tr class="table-success fw-bold text-dark" style="border-bottom: 2px solid #555;">
                            <td colspan="2">TOTAL INCOMES REALIZED (A):</td>
                            <td class="text-end text-success fs-5">Rs. <?php echo number_format($reportData['total_income'], 2); ?></td>
                        </tr>
                        
                        <!-- Expense Heads -->
                        <tr>
                            <td rowspan="2" class="fw-bold text-danger align-middle text-uppercase"><i class="fa-solid fa-arrow-trend-down me-2"></i>Expenses (B)</td>
                            <td class="fw-semibold">General Operation Expenses</td>
                            <td class="text-end text-danger fw-bold">Rs. <?php echo number_format($reportData['general_expenses'], 2); ?></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Monthly Employee Payroll Salaries</td>
                            <td class="text-end text-danger fw-bold">Rs. <?php echo number_format($reportData['salaries_paid'], 2); ?></td>
                        </tr>
                        <tr class="table-danger fw-bold text-dark" style="border-bottom: 2px solid #555;">
                            <td colspan="2">TOTAL OPERATIONAL EXPENSES (B):</td>
                            <td class="text-end text-danger fs-5">Rs. <?php echo number_format($reportData['total_expenses'], 2); ?></td>
                        </tr>

                        <!-- Summary NET Profit/Loss -->
                        <?php $net = $reportData['net_profit']; ?>
                        <tr class="table-<?php echo $net >= 0 ? 'primary' : 'warning'; ?> fw-bold text-dark fs-5">
                            <td colspan="2"><i class="fa-solid fa-piggy-bank me-2"></i>CONSOLIDATED NET CASH FLOW (A - B):</td>
                            <td class="text-end text-<?php echo $net >= 0 ? 'primary' : 'danger'; ?> fw-bold">Rs. <?php echo number_format($net, 2); ?></td>
                        </tr>
                    </tbody>
                <?php endif; ?>

            </table>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #reportPrintArea, #reportPrintArea * {
        visibility: visible;
    }
    #reportPrintArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none !important;
        border: 0 !important;
    }
    .custom-table th, .custom-table td {
        border: 1px solid #000 !important;
        padding: 10px !important;
        font-size: 11px !important;
    }
}
</style>

<?php $extraJS = '<script>
function exportToExcel() {
    let table = document.getElementById("reportDataTable");
    let html = table.outerHTML;
    let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
    let link = document.createElement("a");
    link.download = "' . strtolower(str_replace(' ', '_', $reportTitle)) . '_' . date('Ymd') . '.xls";
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
