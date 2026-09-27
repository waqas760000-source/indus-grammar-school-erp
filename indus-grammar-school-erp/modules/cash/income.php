<?php
/**
 * Indus Grammar School ERP - Income Management Ledger
 * Redesigned Commercial ERP Income & Revenue Workspace
 * Version 4.0.0
 */

$pageTitle = 'Income Management & Revenue Ledger';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// Filters
$filterSource = sanitize($_GET['source_filter'] ?? '');
$filterMethod = sanitize($_GET['method_filter'] ?? '');
$fromDate     = sanitize($_GET['from_date'] ?? '');
$toDate       = sanitize($_GET['to_date'] ?? '');

$where = " WHERE 1=1";
$params = [];

if ($filterSource !== '') {
    $where .= " AND i.source = :source";
    $params['source'] = $filterSource;
}
if ($filterMethod !== '') {
    $where .= " AND i.payment_method = :method";
    $params['method'] = $filterMethod;
}
if ($fromDate !== '') {
    $where .= " AND i.income_date >= :from";
    $params['from'] = $fromDate;
}
if ($toDate !== '') {
    $where .= " AND i.income_date <= :to";
    $params['to'] = $toDate;
}

$incomes = [];
try {
    $stmt = $db->prepare("
        SELECT i.*, u.username as received_by_name, s.first_name, s.last_name, s.admission_no
        FROM income i
        LEFT JOIN users u ON i.received_by = u.id
        LEFT JOIN students s ON i.student_id = s.id
        $where
        ORDER BY i.income_date DESC, i.id DESC
    ");
    $stmt->execute($params);
    $incomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading income list: " . $e->getMessage());
}

// Fetch students for reference link dropdown
$students = [];
try {
    $students = $db->query("SELECT id, admission_no, CONCAT(first_name, ' ', last_name) as student_name FROM students WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$sources = [
    'Student Fee Collection', 'Admission Fee', 'Registration Fee', 'Examination Fee', 
    'Transport Fee', 'Uniform Sale', 'Books & Stationery', 'Donations', 'Miscellaneous Income'
];

// Summary KPI calculations
$filteredTotalSum = array_sum(array_column($incomes, 'amount'));
$totalRecordsCount = count($incomes);
$avgIncomeVal = $totalRecordsCount > 0 ? ($filteredTotalSum / $totalRecordsCount) : 0.00;

// Fetch today's collection total across all income sources
$today = date('Y-m-d');
$todayIncomeSum = 0.00;
try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM income WHERE income_date = :dt");
    $stmt->execute(['dt' => $today]);
    $todayIncomeSum = (float)$stmt->fetchColumn();
} catch (Exception $e) {}
?>

<style>
/* ERP Theme Custom Styling */
.hero-income-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e40af 100%);
    border-radius: 16px;
    position: relative;
    overflow: hidden;
}
.hero-income-banner::before {
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
.kpi-income-card {
    border-radius: 14px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid rgba(226, 232, 240, 0.8);
    background: #ffffff;
}
.kpi-income-card:hover {
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
<div class="hero-income-banner text-white p-4 p-lg-5 mb-4 shadow-sm">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center mb-2">
                <div class="p-3 bg-white bg-opacity-10 rounded-3 me-3 text-warning">
                    <i class="fa-solid fa-circle-down fs-2"></i>
                </div>
                <div>
                    <h2 class="fw-bold mb-1 text-white">Income Management & Revenue Ledger</h2>
                    <p class="mb-0 text-white-50 fs-6">
                        Record school collections, uniform/book sales, donations, and audit automated student fee receipts.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex flex-wrap gap-2 justify-content-lg-end align-items-center">
                <button class="btn btn-success fw-bold px-4 py-2 shadow-sm rounded-3" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
                    <i class="fa-solid fa-plus me-2"></i>Record Manual Income
                </button>
                <button onclick="exportIncomeCSV()" class="btn btn-light fw-bold text-dark px-3 py-2 shadow-sm rounded-3">
                    <i class="fa-solid fa-file-csv me-2 text-success"></i>Export CSV
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Top KPI Overview Cards -->
<div class="row g-3 mb-4">
    <!-- Filtered Realized Income Total -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-income-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-success-soft text-success me-3 fs-4">
                    <i class="fa-solid fa-sack-dollar"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Total Filtered Income</div>
                    <h4 class="fw-bold text-success mb-0">Rs. <?php echo number_format($filteredTotalSum, 2); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Realized Income -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-income-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-primary-soft text-primary me-3 fs-4">
                    <i class="fa-solid fa-calendar-day"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Today's Inflows Total</div>
                    <h4 class="fw-bold text-primary mb-0">Rs. <?php echo number_format($todayIncomeSum, 2); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Records Count -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-income-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-info-soft text-info me-3 fs-4">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Total Voucher Entries</div>
                    <h4 class="fw-bold text-dark mb-0"><?php echo number_format($totalRecordsCount); ?> Receipts</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Average Collection Amount -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-income-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-warning-soft text-warning me-3 fs-4">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Average Income / Voucher</div>
                    <h4 class="fw-bold text-dark mb-0">Rs. <?php echo number_format($avgIncomeVal, 2); ?></h4>
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
            <h5 class="fw-bold text-dark mb-0">Filter Income Receipts & Sources</h5>
        </div>
        
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-bold text-muted text-uppercase">Income Source Category</label>
                <select class="form-select fw-bold text-primary" name="source_filter">
                    <option value="">All Sources</option>
                    <?php foreach ($sources as $src): ?>
                        <option value="<?php echo $src; ?>" <?php echo ($filterSource === $src) ? 'selected' : ''; ?>><?php echo $src; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-12 col-md-3">
                <label class="form-label small fw-bold text-muted text-uppercase">Payment Mode</label>
                <select class="form-select" name="method_filter">
                    <option value="">All Payment Modes</option>
                    <option value="Cash" <?php echo ($filterMethod === 'Cash') ? 'selected' : ''; ?>>Cash Counter 💵</option>
                    <option value="Bank" <?php echo ($filterMethod === 'Bank') ? 'selected' : ''; ?>>Bank Transfer 🏦</option>
                    <option value="Online" <?php echo ($filterMethod === 'Online') ? 'selected' : ''; ?>>Online 📱</option>
                    <option value="Cheque" <?php echo ($filterMethod === 'Cheque') ? 'selected' : ''; ?>>Cheque 📝</option>
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
                <a href="income.php" class="btn btn-outline-secondary fw-semibold py-2">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Income Records Data Table Container -->
<div class="custom-table-container shadow-sm mb-5">
    <div class="p-3 p-md-4 bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-list-check text-primary me-2"></i>Income Receipts Register
            </h5>
            <small class="text-muted">Showing all matching income vouchers & fee collection receipts.</small>
        </div>
        
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="max-width: 260px;">
                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                <input type="text" id="incomeSearchInput" class="form-control border-start-0 bg-light" placeholder="Filter rows in view..." onkeyup="filterIncomeTable()">
            </div>
            <span class="badge bg-light text-dark border px-3 py-2 fw-semibold">
                Total Records: <?php echo count($incomes); ?>
            </span>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0" id="incomeDataTable">
            <thead>
                <tr>
                    <th>Voucher Ref</th>
                    <th>Date Collected</th>
                    <th>Source Category</th>
                    <th>Student / Payer Context</th>
                    <th>Income Description</th>
                    <th>Amount Paid</th>
                    <th>Payment Mode</th>
                    <th>Received By</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($incomes)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <i class="fa-solid fa-folder-open text-muted fs-1 mb-2 d-block opacity-50"></i>
                            <span class="text-muted fw-semibold">No income records matching your search filters.</span>
                        </td>
                    </tr>
                <?php else: foreach ($incomes as $inc): ?>
                    <tr>
                        <td><code class="fw-bold text-primary fs-6"><?php echo sanitize($inc['reference_no']); ?></code></td>
                        <td><i class="fa-regular fa-calendar-check me-1 text-muted"></i><?php echo date('d M Y', strtotime($inc['income_date'])); ?></td>
                        <td>
                            <?php
                            $badge = ($inc['source'] === 'Student Fee Collection') ? 'bg-primary-soft text-primary' : 'bg-success-soft text-success';
                            ?>
                            <span class="badge <?php echo $badge; ?> px-3 py-1 rounded-pill fw-bold"><?php echo sanitize($inc['source']); ?></span>
                        </td>
                        <td>
                            <?php if (!empty($inc['first_name'])): ?>
                                <div class="fw-bold text-dark"><?php echo sanitize($inc['first_name'] . ' ' . $inc['last_name']); ?></div>
                                <code class="text-muted" style="font-size:0.75rem;">Adm: <?php echo sanitize($inc['admission_no']); ?></code>
                            <?php else: ?>
                                <span class="text-muted small">— General —</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-secondary small" style="max-width:220px; white-space:normal;"><?php echo sanitize($inc['description'] ?: 'No details provided'); ?></td>
                        <td class="fw-bold text-success fs-6">Rs. <?php echo number_format($inc['amount'], 2); ?></td>
                        <td>
                            <?php
                            $mCls = ['Cash' => 'success', 'Bank' => 'info', 'Online' => 'primary', 'Cheque' => 'warning'];
                            $c = $mCls[$inc['payment_method']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $c; ?>-soft text-<?php echo $c; ?> px-3 py-1 rounded-pill fw-bold"><?php echo sanitize($inc['payment_method']); ?></span>
                        </td>
                        <td><code class="text-dark small"><?php echo sanitize($inc['received_by_name'] ?: 'System Admin'); ?></code></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary btn-print-voucher rounded-circle p-2" style="width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center;"
                                    data-ref="<?php echo htmlspecialchars($inc['reference_no']); ?>"
                                    data-date="<?php echo date('d M Y', strtotime($inc['income_date'])); ?>"
                                    data-source="<?php echo htmlspecialchars($inc['source']); ?>"
                                    data-desc="<?php echo htmlspecialchars($inc['description'] ?? ''); ?>"
                                    data-amount="<?php echo number_format($inc['amount'], 2); ?>"
                                    data-method="<?php echo htmlspecialchars($inc['payment_method']); ?>"
                                    data-by="<?php echo htmlspecialchars($inc['received_by_name'] ?: 'System Admin'); ?>"
                                    data-remarks="<?php echo htmlspecialchars($inc['remarks'] ?? ''); ?>"
                                    title="Print Income Voucher">
                                <i class="fa-solid fa-print"></i>
                            </button>
                            <?php if ($inc['source'] !== 'Student Fee Collection'): ?>
                                <button class="btn btn-sm btn-outline-secondary btn-edit-income rounded-circle p-2 ms-1" style="width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center;"
                                        data-id="<?php echo $inc['id']; ?>"
                                        data-date="<?php echo $inc['income_date']; ?>"
                                        data-source="<?php echo htmlspecialchars($inc['source']); ?>"
                                        data-ref="<?php echo htmlspecialchars($inc['reference_no']); ?>"
                                        data-desc="<?php echo htmlspecialchars($inc['description'] ?? ''); ?>"
                                        data-amount="<?php echo $inc['amount']; ?>"
                                        data-method="<?php echo htmlspecialchars($inc['payment_method']); ?>"
                                        data-remarks="<?php echo htmlspecialchars($inc['remarks'] ?? ''); ?>"
                                        title="Edit Record">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-delete-income rounded-circle p-2 ms-1" style="width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center;" data-id="<?php echo $inc['id']; ?>" title="Delete Entry">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr class="table-light fw-bold text-dark">
                        <td colspan="5" class="text-end text-uppercase" style="letter-spacing:0.05em;">Filtered Income Total:</td>
                        <td colspan="4" class="text-success fs-5">Rs. <?php echo number_format($filteredTotalSum, 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Record Income Modal -->
<div class="modal fade" id="addIncomeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-success-soft text-success rounded-3">
                        <i class="fa-solid fa-circle-down fs-4"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark">Record Manual Income</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="addIncomeForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_income">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Income Date *</label>
                            <input type="date" class="form-control" name="income_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Voucher / Ref No</label>
                            <input type="text" class="form-control" name="reference_no" placeholder="Auto-generated if empty">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Income Source Category *</label>
                        <select class="form-select fw-semibold" name="source" required>
                            <?php foreach ($sources as $src): if ($src === 'Student Fee Collection') continue; ?>
                                <option value="<?php echo $src; ?>"><?php echo $src; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Amount Received (Rs.) *</label>
                            <input type="number" class="form-control fw-bold text-success" name="amount" step="0.01" min="1" placeholder="0.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Payment Mode *</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="Cash">Cash Counter 💵</option>
                                <option value="Bank">Bank Deposit 🏦</option>
                                <option value="Online">Online Transfer 📱</option>
                                <option value="Cheque">Bank Cheque 📝</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Income Title / Description</label>
                        <input type="text" class="form-control" name="description" placeholder="e.g. Uniform sale of 2 items or Book set">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Internal Audit Remarks</label>
                        <textarea class="form-control" name="remarks" rows="2" placeholder="Optional audit notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary fw-semibold px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addIncomeForm" class="btn btn-success fw-bold px-4" id="btnAddIncome">
                    <i class="fa-solid fa-save me-1"></i> Save Income
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Income Modal -->
<div class="modal fade" id="editIncomeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-soft text-primary rounded-3">
                        <i class="fa-solid fa-pen fs-4"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark">Modify Income Record</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="editIncomeForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="edit_income">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Income Date *</label>
                            <input type="date" class="form-control" name="income_date" id="edit_date" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Voucher / Ref No</label>
                            <input type="text" class="form-control" name="reference_no" id="edit_ref" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Income Source Category *</label>
                        <select class="form-select fw-semibold" name="source" id="edit_source" required>
                            <?php foreach ($sources as $src): if ($src === 'Student Fee Collection') continue; ?>
                                <option value="<?php echo $src; ?>"><?php echo $src; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Amount (Rs.) *</label>
                            <input type="number" class="form-control fw-bold text-success" name="amount" id="edit_amount" step="0.01" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Payment Mode *</label>
                            <select class="form-select" name="payment_method" id="edit_method" required>
                                <option value="Cash">Cash Counter 💵</option>
                                <option value="Bank">Bank Deposit 🏦</option>
                                <option value="Online">Online Transfer 📱</option>
                                <option value="Cheque">Bank Cheque 📝</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Income Title / Description</label>
                        <input type="text" class="form-control" name="description" id="edit_desc">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Internal Audit Remarks</label>
                        <textarea class="form-control" name="remarks" id="edit_remarks" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary fw-semibold px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editIncomeForm" class="btn btn-primary fw-bold px-4" id="btnEditIncome">
                    <i class="fa-solid fa-save me-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Print Voucher Template (Hidden by default, printed via JS popup) -->
<div id="printVoucherSection" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 30px; border: 2px solid #0f172a; width: 620px; margin: 0 auto; border-radius:12px; background:#fff;">
        <div style="text-align: center; border-bottom: 2px double #0f172a; padding-bottom: 12px; margin-bottom: 20px;">
            <h2 style="margin: 0; text-transform: uppercase; color:#0f172a; font-weight: bold;"><?php echo SCHOOL_NAME; ?></h2>
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 12px 0 0 0; background: #f1f5f9; padding: 6px; border-radius: 6px; color:#1e293b; letter-spacing:0.05em;">INCOME RECEIPT VOUCHER</h4>
        </div>
        
        <table style="width: 100%; margin-bottom: 20px; font-size: 13px;">
            <tr>
                <td style="width: 50%;"><strong>Voucher No:</strong> <span id="v_ref" style="font-family: monospace; color:#1d4ed8; font-weight:bold;"></span></td>
                <td style="width: 50%; text-align: right;"><strong>Date:</strong> <span id="v_date"></span></td>
            </tr>
        </table>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px;">
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 10px 0; color:#64748b;"><strong>Income Source:</strong></td>
                <td style="padding: 10px 0; text-align: right; font-weight:bold; color:#0f172a;" id="v_source"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 10px 0; color:#64748b;"><strong>Description:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="v_desc"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 10px 0; color:#64748b;"><strong>Payment Mode:</strong></td>
                <td style="padding: 10px 0; text-align: right; font-weight:bold;" id="v_method"></td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 10px 0; color:#64748b;"><strong>Collected By:</strong></td>
                <td style="padding: 10px 0; text-align: right;" id="v_by"></td>
            </tr>
        </table>
        
        <div style="background: #f0fdf4; border:1px solid #bbf7d0; padding: 15px; text-align: center; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="margin: 0; color: #16a34a; font-weight: bold;">TOTAL AMOUNT: Rs. <span id="v_amount"></span></h3>
        </div>
        
        <table style="width: 100%; margin-top: 50px; font-size: 12px; text-align: center;">
            <tr>
                <td style="width: 50%;"><div style="border-top: 1px solid #0f172a; width: 160px; margin: 0 auto; padding-top: 6px; font-weight:semibold;">Depositor / Cashier</div></td>
                <td style="width: 50%;"><div style="border-top: 1px solid #0f172a; width: 160px; margin: 0 auto; padding-top: 6px; font-weight:semibold;">Authorized Officer</div></td>
            </tr>
        </table>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="incToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="incToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function filterIncomeTable() {
    const input = document.getElementById("incomeSearchInput");
    const filter = input.value.toLowerCase();
    const table = document.getElementById("incomeDataTable");
    const trs = table.querySelectorAll("tbody tr");

    trs.forEach(tr => {
        if (tr.classList.contains("table-light")) return;
        const text = tr.textContent.toLowerCase();
        tr.style.display = text.includes(filter) ? "" : "none";
    });
}

function exportIncomeCSV() {
    const table = document.getElementById("incomeDataTable");
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
    link.setAttribute("download", "income_ledger_" + new Date().toISOString().slice(0,10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function showToast(msg, ok) {
    const t = document.getElementById("incToast");
    const m = document.getElementById("incToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Record Income Form
    const addForm = document.getElementById("addIncomeForm");
    if (addForm) {
        addForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnAddIncome");
            btn.disabled = true; btn.innerHTML = "Saving...";
            
            fetch("../../ajax/accounts.php", { method: "POST", body: new FormData(addForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Save Income"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "Save Income"; });
        });
    }

    // Bind Edit Modal
    document.querySelectorAll(".btn-edit-income").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("edit_id").value = this.dataset.id;
            document.getElementById("edit_date").value = this.dataset.date;
            document.getElementById("edit_ref").value = this.dataset.ref;
            document.getElementById("edit_source").value = this.dataset.source;
            document.getElementById("edit_amount").value = this.dataset.amount;
            document.getElementById("edit_method").value = this.dataset.method;
            document.getElementById("edit_desc").value = this.dataset.desc;
            document.getElementById("edit_remarks").value = this.dataset.remarks;
            
            new bootstrap.Modal(document.getElementById("editIncomeModal")).show();
        });
    });

    // Save Edit Form
    const editForm = document.getElementById("editIncomeForm");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnEditIncome");
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

    // Delete Income Action
    document.querySelectorAll(".btn-delete-income").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            if(!confirm("Are you sure you want to delete this income record?")) return;
            const fd = new FormData();
            fd.append("action", "delete_income");
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
            document.getElementById("v_ref").textContent = this.dataset.ref;
            document.getElementById("v_date").textContent = this.dataset.date;
            document.getElementById("v_source").textContent = this.dataset.source;
            document.getElementById("v_desc").textContent = this.dataset.desc || "Operational collection";
            document.getElementById("v_method").textContent = this.dataset.method;
            document.getElementById("v_by").textContent = this.dataset.by;
            document.getElementById("v_amount").textContent = this.dataset.amount;
            
            const printContent = document.getElementById("printVoucherSection").innerHTML;
            
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Income Voucher Print</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
