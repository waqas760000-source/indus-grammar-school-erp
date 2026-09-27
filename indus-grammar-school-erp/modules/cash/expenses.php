<?php
/**
 * Indus Grammar School ERP - Home & Operational Expenses Panel
 * Redesigned Commercial ERP Expenditure Workspace
 * Version 4.0.0
 */

$pageTitle = 'Home & Operational Expenses';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// Filters
$filterCategory = (int)($_GET['category_filter'] ?? 0);
$filterMethod   = sanitize($_GET['method_filter'] ?? '');
$fromDate       = sanitize($_GET['from_date'] ?? '');
$toDate         = sanitize($_GET['to_date'] ?? '');

$where = " WHERE 1=1";
$params = [];

if ($filterCategory > 0) {
    $where .= " AND e.category_id = :cat";
    $params['cat'] = $filterCategory;
}
if ($filterMethod !== '') {
    $where .= " AND e.payment_method = :method";
    $params['method'] = $filterMethod;
}
if ($fromDate !== '') {
    $where .= " AND e.expense_date >= :from";
    $params['from'] = $fromDate;
}
if ($toDate !== '') {
    $where .= " AND e.expense_date <= :to";
    $params['to'] = $toDate;
}

$expenses = [];
try {
    $stmt = $db->prepare("
        SELECT e.*, c.name as category_name, u.username as paid_by_name
        FROM expenses e
        LEFT JOIN expense_categories c ON e.category_id = c.id
        LEFT JOIN users u ON e.paid_by = u.id
        $where
        ORDER BY e.expense_date DESC, e.id DESC
    ");
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading expenses: " . $e->getMessage());
}

// Fetch all active categories
$categories = [];
try {
    $categories = $db->query("SELECT * FROM expense_categories WHERE status = 'Active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Summary KPI calculations
$filteredExpensesSum = array_sum(array_column($expenses, 'amount'));
$totalExpensesCount = count($expenses);
$avgExpenseVal = $totalExpensesCount > 0 ? ($filteredExpensesSum / $totalExpensesCount) : 0.00;

// Fetch today's total operational expenses
$today = date('Y-m-d');
$todayExpensesSum = 0.00;
try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = :dt");
    $stmt->execute(['dt' => $today]);
    $todayExpensesSum = (float)$stmt->fetchColumn();
} catch (Exception $e) {}
?>

<style>
/* ERP Theme Custom Styling */
.hero-expense-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e40af 100%);
    border-radius: 16px;
    position: relative;
    overflow: hidden;
}
.hero-expense-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 450px;
    height: 450px;
    background: radial-gradient(circle, rgba(239,68,68,0.18) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}
.kpi-expense-card {
    border-radius: 14px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid rgba(226, 232, 240, 0.8);
    background: #ffffff;
}
.kpi-expense-card:hover {
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
    padding: 14px 16px;
    border-bottom: 2px solid #e2e8f0;
}
.custom-table-container tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
}
.custom-table-container tbody tr:last-child td {
    border-bottom: none;
}
.form-select, .form-control {
    border-radius: 8px;
    border: 1px solid #cbd5e1;
}
.form-select:focus, .form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
}
</style>

<!-- Hero Banner Header -->
<div class="hero-expense-banner text-white p-4 p-lg-5 mb-4 shadow-sm">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center mb-2">
                <div class="p-3 bg-white bg-opacity-10 rounded-3 me-3 text-warning">
                    <i class="fa-solid fa-circle-up fs-2 text-danger"></i>
                </div>
                <div>
                    <h2 class="fw-bold mb-1 text-white">Home & Operational Expenses Ledger</h2>
                    <p class="mb-0 text-white-50 fs-6">
                        Record and review school bills, rent, stationery, maintenance repairs, and support file attachments.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex flex-wrap gap-2 justify-content-lg-end align-items-center">
                <button class="btn btn-danger fw-bold px-4 py-2 shadow-sm rounded-3" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                    <i class="fa-solid fa-plus me-2"></i>Record Operational Expense
                </button>
                <button onclick="exportExpenseCSV()" class="btn btn-light fw-bold text-dark px-3 py-2 shadow-sm rounded-3">
                    <i class="fa-solid fa-file-csv me-2 text-danger"></i>Export CSV
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Top KPI Overview Cards -->
<div class="row g-3 mb-4">
    <!-- Filtered Total Expenditures -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-expense-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-danger-soft text-danger me-3 fs-4">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Total Filtered Expenses</div>
                    <h4 class="fw-bold text-danger mb-0">Rs. <?php echo number_format($filteredExpensesSum, 2); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Outflows Total -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-expense-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-warning-soft text-warning me-3 fs-4">
                    <i class="fa-solid fa-calendar-day"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Today's Outflows Total</div>
                    <h4 class="fw-bold text-dark mb-0">Rs. <?php echo number_format($todayExpensesSum, 2); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Expense Vouchers -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-expense-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-primary-soft text-primary me-3 fs-4">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Total Expense Vouchers</div>
                    <h4 class="fw-bold text-dark mb-0"><?php echo number_format($totalExpensesCount); ?> Vouchers</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Average Expense Voucher -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-expense-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-info-soft text-info me-3 fs-4">
                    <i class="fa-solid fa-calculator"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Average Cost / Voucher</div>
                    <h4 class="fw-bold text-dark mb-0">Rs. <?php echo number_format($avgExpenseVal, 2); ?></h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Filter Toolbar Card -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-4">
        <div class="d-flex align-items-center mb-3">
            <i class="fa-solid fa-sliders text-primary fs-5 me-2"></i>
            <h5 class="fw-bold text-dark mb-0">Filter Expense Records & Categories</h5>
        </div>
        
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-bold text-muted text-uppercase">Expense Category</label>
                <select class="form-select fw-bold text-primary" name="category_filter">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($filterCategory == $cat['id']) ? 'selected' : ''; ?>><?php echo sanitize($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-12 col-md-3">
                <label class="form-label small fw-bold text-muted text-uppercase">Payment Mode</label>
                <select class="form-select" name="method_filter">
                    <option value="">All Payment Modes</option>
                    <option value="Cash" <?php echo ($filterMethod === 'Cash') ? 'selected' : ''; ?>>Cash Drawer 💵</option>
                    <option value="Bank" <?php echo ($filterMethod === 'Bank') ? 'selected' : ''; ?>>Bank Account 🏦</option>
                    <option value="Cheque" <?php echo ($filterMethod === 'Cheque') ? 'selected' : ''; ?>>Bank Cheque 📝</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-muted text-uppercase">From Date</label>
                <input type="date" class="form-control" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-muted text-uppercase">To Date</label>
                <input type="date" class="form-control" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>

            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm py-2">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Apply
                </button>
                <a href="expenses.php" class="btn btn-outline-secondary fw-semibold py-2">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Expenses Records Data Table Container -->
<div class="custom-table-container shadow-sm mb-5">
    <div class="p-3 p-md-4 bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-list-check text-danger me-2"></i>Operational Expenditures Register
            </h5>
            <small class="text-muted">Showing logged expense vouchers, vendor bills, and attached documents.</small>
        </div>
        
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="max-width: 260px;">
                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                <input type="text" id="expenseSearchInput" class="form-control border-start-0 bg-light" placeholder="Filter rows in view..." onkeyup="filterExpenseTable()">
            </div>
            <span class="badge bg-light text-dark border px-3 py-2 fw-semibold">
                Total Vouchers: <?php echo count($expenses); ?>
            </span>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0" id="expenseDataTable">
            <thead>
                <tr>
                    <th>Voucher Ref</th>
                    <th>Expense Date</th>
                    <th>Category</th>
                    <th>Expense Title</th>
                    <th>Vendor / Invoice</th>
                    <th>Amount Paid</th>
                    <th>Payment Mode</th>
                    <th class="text-center">Bill Document</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <i class="fa-solid fa-folder-open text-muted fs-1 mb-2 d-block opacity-50"></i>
                            <span class="text-muted fw-semibold">No operational expenses logged matching your search filters.</span>
                        </td>
                    </tr>
                <?php else: foreach ($expenses as $exp): ?>
                    <tr>
                        <td><code class="fw-bold text-danger fs-6">EXP-<?php echo str_pad($exp['id'], 6, '0', STR_PAD_LEFT); ?></code></td>
                        <td><i class="fa-regular fa-calendar-check me-1 text-muted"></i><?php echo date('d M Y', strtotime($exp['expense_date'])); ?></td>
                        <td>
                            <span class="badge bg-danger-soft text-danger px-3 py-1 rounded-pill fw-bold"><?php echo sanitize($exp['category_name'] ?: 'General'); ?></span>
                        </td>
                        <td class="fw-bold text-dark text-wrap" style="max-width:180px;"><?php echo sanitize($exp['title']); ?></td>
                        <td>
                            <div class="fw-bold text-dark small"><?php echo sanitize($exp['vendor_supplier'] ?: '—'); ?></div>
                            <code class="text-muted" style="font-size:0.75rem;">Inv: <?php echo sanitize($exp['invoice_number'] ?: '—'); ?></code>
                        </td>
                        <td class="fw-bold text-danger fs-6">Rs. <?php echo number_format($exp['amount'], 2); ?></td>
                        <td>
                            <?php
                            $mCls = ['Cash' => 'success', 'Bank' => 'info', 'Cheque' => 'warning'];
                            $c = $mCls[$exp['payment_method']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $c; ?>-soft text-<?php echo $c; ?> px-3 py-1 rounded-pill fw-bold"><?php echo sanitize($exp['payment_method']); ?></span>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($exp['attachment'])): ?>
                                <a href="<?php echo APP_URL . '/' . $exp['attachment']; ?>" target="_blank" class="btn btn-sm btn-light border text-primary rounded-circle p-2 shadow-sm" style="width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center;" title="View Attached Bill Document">
                                    <i class="fa-solid fa-paperclip"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary btn-print-voucher rounded-circle p-2" style="width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center;"
                                    data-ref="EXP-<?php echo str_pad($exp['id'], 6, '0', STR_PAD_LEFT); ?>"
                                    data-date="<?php echo date('d M Y', strtotime($exp['expense_date'])); ?>"
                                    data-category="<?php echo htmlspecialchars($exp['category_name'] ?: 'General'); ?>"
                                    data-title="<?php echo htmlspecialchars($exp['title']); ?>"
                                    data-vendor="<?php echo htmlspecialchars($exp['vendor_supplier'] ?: '—'); ?>"
                                    data-invoice="<?php echo htmlspecialchars($exp['invoice_number'] ?: '—'); ?>"
                                    data-amount="<?php echo number_format($exp['amount'], 2); ?>"
                                    data-method="<?php echo htmlspecialchars($exp['payment_method']); ?>"
                                    data-by="<?php echo htmlspecialchars($exp['paid_by_name'] ?: 'System Admin'); ?>"
                                    data-remarks="<?php echo htmlspecialchars($exp['remarks'] ?? ''); ?>"
                                    title="Print Debit Voucher">
                                <i class="fa-solid fa-print"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary btn-edit-expense rounded-circle p-2 ms-1" style="width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center;"
                                    data-id="<?php echo $exp['id']; ?>"
                                    data-date="<?php echo $exp['expense_date']; ?>"
                                    data-category="<?php echo $exp['category_id']; ?>"
                                    data-title="<?php echo htmlspecialchars($exp['title']); ?>"
                                    data-vendor="<?php echo htmlspecialchars($exp['vendor_supplier'] ?? ''); ?>"
                                    data-invoice="<?php echo htmlspecialchars($exp['invoice_number'] ?? ''); ?>"
                                    data-amount="<?php echo $exp['amount']; ?>"
                                    data-method="<?php echo htmlspecialchars($exp['payment_method']); ?>"
                                    data-attach="<?php echo htmlspecialchars($exp['attachment'] ?? ''); ?>"
                                    data-remarks="<?php echo htmlspecialchars($exp['remarks'] ?? ''); ?>"
                                    title="Edit Expense">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger btn-delete-expense rounded-circle p-2 ms-1" style="width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center;" data-id="<?php echo $exp['id']; ?>" title="Delete Entry">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr class="table-light fw-bold text-dark">
                        <td colspan="5" class="text-end text-uppercase" style="letter-spacing:0.05em;">Filtered Expenditures Total:</td>
                        <td colspan="4" class="text-danger fs-5">Rs. <?php echo number_format($filteredExpensesSum, 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Record Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-danger-soft text-danger rounded-3">
                        <i class="fa-solid fa-circle-up fs-4"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark">Record Operational Expense</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="addExpenseForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_expense">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Expense Date *</label>
                            <input type="date" class="form-control" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Expense Category *</label>
                            <select class="form-select fw-semibold" name="category_id" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo sanitize($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Expense Title *</label>
                        <input type="text" class="form-control" name="title" placeholder="e.g. Purchase of Whiteboard Markers or Electricity Bill" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Vendor / Supplier</label>
                            <input type="text" class="form-control" name="vendor_supplier" placeholder="e.g. Allied Book Depot / K-Electric">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Invoice Number</label>
                            <input type="text" class="form-control" name="invoice_number" placeholder="e.g. INV-1002">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Amount Paid (Rs.) *</label>
                            <input type="number" class="form-control fw-bold text-danger" name="amount" step="0.01" min="1" placeholder="0.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Payment Mode *</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="Cash">Cash Drawer 💵</option>
                                <option value="Bank">Bank Account 🏦</option>
                                <option value="Cheque">Bank Cheque 📝</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Bill Attachment (PDF/Image)</label>
                        <input type="file" class="form-control" name="attachment" accept="image/*,application/pdf">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Internal Administrative Remarks</label>
                        <textarea class="form-control" name="remarks" rows="2" placeholder="Administrative audit notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary fw-semibold px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addExpenseForm" class="btn btn-danger fw-bold px-4" id="btnAddExpense">
                    <i class="fa-solid fa-save me-1"></i> Save Expense
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Expense Modal -->
<div class="modal fade" id="editExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-soft text-primary rounded-3">
                        <i class="fa-solid fa-pen fs-4"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark">Modify Expense Entry</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="editExpenseForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="edit_expense">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="existing_attachment" id="edit_existing_attach">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Expense Date *</label>
                            <input type="date" class="form-control" name="expense_date" id="edit_date" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Expense Category *</label>
                            <select class="form-select fw-semibold" name="category_id" id="edit_category" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo sanitize($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Expense Title *</label>
                        <input type="text" class="form-control" name="title" id="edit_title" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Vendor / Supplier</label>
                            <input type="text" class="form-control" name="vendor_supplier" id="edit_vendor">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Invoice Number</label>
                            <input type="text" class="form-control" name="invoice_number" id="edit_invoice">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Amount Paid (Rs.) *</label>
                            <input type="number" class="form-control fw-bold text-danger" name="amount" id="edit_amount" step="0.01" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Payment Mode *</label>
                            <select class="form-select" name="payment_method" id="edit_method" required>
                                <option value="Cash">Cash Drawer 💵</option>
                                <option value="Bank">Bank Account 🏦</option>
                                <option value="Cheque">Bank Cheque 📝</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Update Bill Attachment (Optional)</label>
                        <input type="file" class="form-control" name="attachment" accept="image/*,application/pdf">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Internal Administrative Remarks</label>
                        <textarea class="form-control" name="remarks" id="edit_remarks" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary fw-semibold px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editExpenseForm" class="btn btn-primary fw-bold px-4" id="btnEditExpense">
                    <i class="fa-solid fa-save me-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Print Voucher Template (Hidden, printed via JS popup) -->
<div id="printExpenseVoucherSection" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 30px; border: 2px solid #0f172a; width: 620px; margin: 0 auto; border-radius:12px; background:#fff;">
        <div style="text-align: center; border-bottom: 2px double #0f172a; padding-bottom: 12px; margin-bottom: 20px;">
            <h2 style="margin: 0; text-transform: uppercase; color:#0f172a; font-weight: bold;"><?php echo SCHOOL_NAME; ?></h2>
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 12px 0 0 0; background: #f1f5f9; padding: 6px; border-radius: 6px; color:#1e293b; letter-spacing:0.05em;">EXPENSE PAYMENT DEBIT VOUCHER</h4>
        </div>
        
        <table style="width: 100%; margin-bottom: 20px; font-size: 13px;">
            <tr>
                <td style="width: 50%;"><strong>Voucher No:</strong> <span id="ev_ref" style="font-family: monospace; color:#dc2626; font-weight:bold;"></span></td>
                <td style="width: 50%; text-align: right;"><strong>Date:</strong> <span id="ev_date"></span></td>
            </tr>
        </table>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px;">
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 10px 0; color:#64748b;"><strong>Category:</strong></td>
                <td style="padding: 10px 0; text-align: right; font-weight:bold; color:#0f172a;" id="ev_category"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 10px 0; color:#64748b;"><strong>Expense Title:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="ev_title"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 10px 0; color:#64748b;"><strong>Vendor / Supplier:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="ev_vendor"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 10px 0; color:#64748b;"><strong>Invoice Reference:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="ev_invoice"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 10px 0; color:#64748b;"><strong>Payment Mode:</strong></td>
                <td style="padding: 10px 0; text-align: right; font-weight:bold;" id="ev_method"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 10px 0; color:#64748b;"><strong>Disbursed By:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="ev_by"></td>
            </tr>
        </table>
        
        <div style="background: #fef2f2; border:1px solid #fecaca; padding: 15px; text-align: center; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="margin: 0; color: #dc2626; font-weight: bold;">DEBIT AMOUNT: Rs. <span id="ev_amount"></span></h3>
        </div>
        
        <table style="width: 100%; margin-top: 50px; font-size: 12px; text-align: center;">
            <tr>
                <td style="width: 33%;"><div style="border-top: 1px solid #0f172a; width: 130px; margin: 0 auto; padding-top: 6px; font-weight:semibold;">Receiver Signature</div></td>
                <td style="width: 33%;"><div style="border-top: 1px solid #0f172a; width: 130px; margin: 0 auto; padding-top: 6px; font-weight:semibold;">Verified Accountant</div></td>
                <td style="width: 33%;"><div style="border-top: 1px solid #0f172a; width: 130px; margin: 0 auto; padding-top: 6px; font-weight:semibold;">Principal / Admin</div></td>
            </tr>
        </table>
    </div>
</div>

<!-- Toast Feedback -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="expToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="expToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function filterExpenseTable() {
    const input = document.getElementById("expenseSearchInput");
    const filter = input.value.toLowerCase();
    const table = document.getElementById("expenseDataTable");
    const trs = table.querySelectorAll("tbody tr");

    trs.forEach(tr => {
        if (tr.classList.contains("table-light")) return;
        const text = tr.textContent.toLowerCase();
        tr.style.display = text.includes(filter) ? "" : "none";
    });
}

function exportExpenseCSV() {
    const table = document.getElementById("expenseDataTable");
    let csv = [];
    
    // Headers
    const headers = Array.from(table.querySelectorAll("thead th")).slice(0, -1).map(th => `"${th.textContent.trim().replace(/"/g, \'""\')}"`);
    csv.push(headers.join(","));
    
    // Rows
    const rows = Array.from(table.querySelectorAll("tbody tr"));
    rows.forEach(row => {
        const cells = Array.from(row.querySelectorAll("td")).slice(0, -1);
        if(cells.length === 1 && cells[0].getAttribute("colspan")) return;
        
        const line = cells.map(td => {
            let txt = td.textContent.trim().replace(/\s+/g, " ").replace(/"/g, \'""\');
            return `"${txt}"`;
        });
        csv.push(line.join(","));
    });
    
    const blob = new Blob([csv.join("\\n")], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "operational_expenses_" + new Date().toISOString().slice(0,10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function showToast(msg, ok) {
    const t = document.getElementById("expToast");
    const m = document.getElementById("expToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Record Expense Form
    const addForm = document.getElementById("addExpenseForm");
    if (addForm) {
        addForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnAddExpense");
            btn.disabled = true; btn.innerHTML = "Saving...";
            
            fetch("../../ajax/accounts.php", { method: "POST", body: new FormData(addForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Save Expense"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "Save Expense"; });
        });
    }

    // Bind Edit Modal
    document.querySelectorAll(".btn-edit-expense").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("edit_id").value = this.dataset.id;
            document.getElementById("edit_date").value = this.dataset.date;
            document.getElementById("edit_category").value = this.dataset.category;
            document.getElementById("edit_title").value = this.dataset.title;
            document.getElementById("edit_vendor").value = this.dataset.vendor;
            document.getElementById("edit_invoice").value = this.dataset.invoice;
            document.getElementById("edit_amount").value = this.dataset.amount;
            document.getElementById("edit_method").value = this.dataset.method;
            document.getElementById("edit_existing_attach").value = this.dataset.attach;
            document.getElementById("edit_remarks").value = this.dataset.remarks;
            
            new bootstrap.Modal(document.getElementById("editExpenseModal")).show();
        });
    });

    // Save Edit Form
    const editForm = document.getElementById("editExpenseForm");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnEditExpense");
            btn.disabled = true; btn.innerHTML = "Saving...";
            
            fetch("../../ajax/accounts.php", { method: "POST", body: new FormData(editForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Save Changes"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "Save Changes"; });
        });
    }

    // Delete Expense Action
    document.querySelectorAll(".btn-delete-expense").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            if(!confirm("Are you sure you want to permanently delete this expense log?")) return;
            const fd = new FormData();
            fd.append("action", "delete_expense");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/accounts.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 1000);
                });
        });
    });

    // Print Voucher Action
    document.querySelectorAll(".btn-print-voucher").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("ev_ref").textContent = this.dataset.ref;
            document.getElementById("ev_date").textContent = this.dataset.date;
            document.getElementById("ev_category").textContent = this.dataset.category;
            document.getElementById("ev_title").textContent = this.dataset.title;
            document.getElementById("ev_vendor").textContent = this.dataset.vendor;
            document.getElementById("ev_invoice").textContent = this.dataset.invoice;
            document.getElementById("ev_method").textContent = this.dataset.method;
            document.getElementById("ev_by").textContent = this.dataset.by;
            document.getElementById("ev_amount").textContent = this.dataset.amount;
            
            const printContent = document.getElementById("printExpenseVoucherSection").innerHTML;
            
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Expense Debit Voucher</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
