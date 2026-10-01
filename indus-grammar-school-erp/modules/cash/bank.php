<?php
/**
 * Indus Grammar School ERP - Premium Bank Transactions & Checkbooks Submodule
 * Version 5.0.0
 */

require_once 'e:/Xampo/htdocs/indus-grammar-school-erp/indus-grammar-school-erp/config/app.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// Filters & Presets
$filterType = sanitize($_GET['type_filter'] ?? '');
$fromDate   = sanitize($_GET['from_date'] ?? '');
$toDate     = sanitize($_GET['to_date'] ?? '');
$preset     = sanitize($_GET['preset'] ?? '');

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
}

$where = " WHERE 1=1";
$params = [];

if ($filterType !== '') {
    $where .= " AND transaction_type = :type";
    $params['type'] = $filterType;
}
if ($fromDate !== '') {
    $where .= " AND date >= :from";
    $params['from'] = $fromDate;
}
if ($toDate !== '') {
    $where .= " AND date <= :to";
    $params['to'] = $toDate;
}

$transactions = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM bank_transactions
        $where
        ORDER BY date DESC, id DESC
    ");
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading bank transactions: " . $e->getMessage());
}

// Calculate Analytics
$totalDeposits = 0.00;
$totalWithdrawals = 0.00;
$bankBalances = [];

foreach ($transactions as $tx) {
    $amt = (float)$tx['amount'];
    $bankKey = trim($tx['bank_name']) . ' (' . trim($tx['account_number']) . ')';
    
    if (!isset($bankBalances[$bankKey])) {
        $bankBalances[$bankKey] = [
            'bank_name' => $tx['bank_name'],
            'account_number' => $tx['account_number'],
            'deposits' => 0.00,
            'withdrawals' => 0.00,
            'balance' => 0.00
        ];
    }

    if ($tx['transaction_type'] === 'Deposit') {
        $totalDeposits += $amt;
        $bankBalances[$bankKey]['deposits'] += $amt;
        $bankBalances[$bankKey]['balance'] += $amt;
    } else {
        $totalWithdrawals += $amt;
        $bankBalances[$bankKey]['withdrawals'] += $amt;
        $bankBalances[$bankKey]['balance'] -= $amt;
    }
}

$netBankPosition = $totalDeposits - $totalWithdrawals;

// CSV Export Action
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bank_transactions_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, [SCHOOL_NAME . ' - BANK TRANSACTIONS LEDGER']);
    fputcsv($output, ['Exported On:', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, ['Ref ID', 'Date', 'Type', 'Bank Name', 'Account Number', 'Reference / Cheque No', 'Amount (Rs.)', 'Description']);
    
    foreach ($transactions as $tx) {
        fputcsv($output, [
            'TX-' . str_pad($tx['id'], 6, '0', STR_PAD_LEFT),
            date('d M Y', strtotime($tx['date'])),
            $tx['transaction_type'],
            $tx['bank_name'],
            $tx['account_number'],
            $tx['reference_number'] ?: '—',
            number_format($tx['amount'], 2, '.', ''),
            $tx['description'] ?: '—'
        ]);
    }
    fclose($output);
    exit;
}

$pageTitle = 'Bank Transactions & Checkbooks';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Premium Inline Styles -->
<style>
.bank-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #312e81 100%);
    border-radius: 20px;
    color: #ffffff;
    box-shadow: 0 15px 35px rgba(15, 23, 42, 0.25);
    position: relative;
    overflow: hidden;
}
.bank-hero-card::before {
    content: "";
    position: absolute;
    top: -50%;
    right: -10%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.2) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}
.kpi-card-bank {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}
.kpi-card-bank:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
}
.kpi-icon-square {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}
.preset-pill {
    border-radius: 30px;
    font-size: 0.825rem;
    font-weight: 600;
    padding: 6px 16px;
}
.preset-pill.active {
    background-color: #2563eb;
    color: #ffffff !important;
}
.bank-table-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.04);
}
.bank-table thead th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 16px 20px;
    border-bottom: 2px solid #e2e8f0;
}
.bank-table tbody td {
    padding: 16px 20px;
    font-size: 0.925rem;
    border-bottom: 1px solid #f1f5f9;
}
.bank-table tbody tr:hover {
    background-color: #f8fafc;
}
.badge-soft-success { background-color: #dcfce7; color: #15803d; }
.badge-soft-danger { background-color: #ffe4e6; color: #be123c; }
.badge-soft-info { background-color: #e0f2fe; color: #0369a1; }
.bank-account-chip {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 12px 16px;
}
</style>

<!-- Hero Executive Header -->
<div class="bank-hero-card p-4 p-lg-5 mb-4">
    <div class="row align-items-center g-4 position-relative" style="z-index: 2;">
        <div class="col-lg-7">
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-white bg-opacity-10 backdrop-blur text-warning small fw-bold mb-3 border border-white border-opacity-10">
                <i class="fa-solid fa-building-columns"></i> Banking & Checkbook Control Hub
            </div>
            <h2 class="fw-extrabold text-white mb-2 display-6">Bank Transactions & Checkbooks</h2>
            <p class="text-slate-300 mb-0 opacity-90 leading-relaxed fs-6">
                Record all institutional bank deposits, cheque disbursements, electronic funds transfers, and account reconciliations in real-time.
            </p>
        </div>
        <div class="col-lg-5 text-lg-end">
            <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                <button class="btn btn-success btn-lg px-4 rounded-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addBankModal" onclick="setTransactionType('Deposit')">
                    <i class="fa-solid fa-plus-circle me-2"></i>Log Deposit (+)
                </button>
                <button class="btn btn-danger btn-lg px-4 rounded-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addBankModal" onclick="setTransactionType('Withdrawal')">
                    <i class="fa-solid fa-minus-circle me-2"></i>Log Withdrawal (-)
                </button>
                <a href="?action=export" class="btn btn-light btn-lg px-3 rounded-3 fw-bold shadow-sm" title="Export CSV">
                    <i class="fa-solid fa-file-csv me-1 text-primary"></i>Export
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary KPIs -->
<div class="row g-3 mb-4">
    <!-- Total Deposits -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card-bank p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase tracking-wider">Total Bank Deposits</span>
                <div class="kpi-icon-square bg-success-soft text-success">
                    <i class="fa-solid fa-arrow-down-left"></i>
                </div>
            </div>
            <h3 class="fw-extrabold text-dark mb-1">Rs. <?php echo number_format($totalDeposits, 2); ?></h3>
            <div class="d-flex align-items-center gap-1 text-success small fw-semibold">
                <i class="fa-solid fa-circle-check"></i>
                <span>Bank Credit Inflows</span>
            </div>
        </div>
    </div>

    <!-- Total Withdrawals -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card-bank p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase tracking-wider">Total Withdrawals</span>
                <div class="kpi-icon-square bg-danger-soft text-danger">
                    <i class="fa-solid fa-arrow-up-right"></i>
                </div>
            </div>
            <h3 class="fw-extrabold text-dark mb-1">Rs. <?php echo number_format($totalWithdrawals, 2); ?></h3>
            <div class="d-flex align-items-center gap-1 text-danger small fw-semibold">
                <i class="fa-solid fa-receipt"></i>
                <span>Cheques & Transfers</span>
            </div>
        </div>
    </div>

    <!-- Net Bank Position -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card-bank p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase tracking-wider">Net Bank Position</span>
                <div class="kpi-icon-square <?php echo $netBankPosition >= 0 ? 'bg-primary-soft text-primary' : 'bg-danger-soft text-danger'; ?>">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
            </div>
            <h3 class="fw-extrabold <?php echo $netBankPosition >= 0 ? 'text-primary' : 'text-danger'; ?> mb-1">
                <?php echo $netBankPosition >= 0 ? '+' : ''; ?>Rs. <?php echo number_format($netBankPosition, 2); ?>
            </h3>
            <div class="d-flex align-items-center gap-1 text-muted small fw-semibold">
                <i class="fa-solid fa-scale-balanced"></i>
                <span>Net Bank Growth</span>
            </div>
        </div>
    </div>

    <!-- Total Transactions Logged -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card-bank p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase tracking-wider">Total Records</span>
                <div class="kpi-icon-square bg-info-soft text-info">
                    <i class="fa-solid fa-list-check"></i>
                </div>
            </div>
            <h3 class="fw-extrabold text-dark mb-1"><?php echo count($transactions); ?> <span class="fs-6 text-muted font-normal">Entries</span></h3>
            <div class="d-flex align-items-center gap-1 text-muted small fw-semibold">
                <i class="fa-solid fa-vault"></i>
                <span>Active School Accounts</span>
            </div>
        </div>
    </div>
</div>

<!-- Bank Accounts Overview Chips (If bank accounts exist) -->
<?php if (!empty($bankBalances)): ?>
<div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-vault me-2 text-primary"></i>Active Bank Accounts Breakdown</h6>
        <div class="row g-3">
            <?php foreach ($bankBalances as $acc): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="bank-account-chip d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold text-dark mb-1"><i class="fa-solid fa-building-columns text-primary me-2"></i><?php echo sanitize($acc['bank_name']); ?></h6>
                            <code class="text-muted small">Acc: <?php echo sanitize($acc['account_number']); ?></code>
                        </div>
                        <div class="text-end">
                            <span class="small text-muted d-block">Net Balance</span>
                            <strong class="<?php echo $acc['balance'] >= 0 ? 'text-success' : 'text-danger'; ?> fs-6">
                                Rs. <?php echo number_format($acc['balance'], 2); ?>
                            </strong>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filter Toolbar Card -->
<div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <!-- Quick Category Filters -->
            <div class="d-flex align-items-center gap-2">
                <span class="small fw-bold text-muted me-1"><i class="fa-solid fa-filter text-primary me-1"></i>Filter Type:</span>
                <a href="?type_filter=" class="btn btn-outline-secondary preset-pill <?php echo $filterType === '' ? 'active' : ''; ?>">All Transactions</a>
                <a href="?type_filter=Deposit" class="btn btn-outline-success preset-pill <?php echo $filterType === 'Deposit' ? 'active' : ''; ?>">Deposits (+)</a>
                <a href="?type_filter=Withdrawal" class="btn btn-outline-danger preset-pill <?php echo $filterType === 'Withdrawal' ? 'active' : ''; ?>">Withdrawals (-)</a>
            </div>

            <!-- Search Input -->
            <div class="position-relative" style="min-width: 260px;">
                <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                <input type="text" id="bankSearchInput" class="form-control form-control-sm ps-5 rounded-pill" placeholder="Search bank, account, ref no...">
            </div>
        </div>

        <!-- Custom Date Range Form -->
        <form method="GET" class="row g-3 align-items-end pt-2 border-top">
            <input type="hidden" name="type_filter" value="<?php echo htmlspecialchars($filterType); ?>">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">From Date</label>
                <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">To Date</label>
                <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1 py-2 rounded-3 fw-bold">
                    <i class="fa-solid fa-calendar-check me-2"></i>Filter Date Period
                </button>
                <a href="bank.php" class="btn btn-sm btn-outline-secondary py-2 rounded-3" title="Reset Filters">
                    <i class="fa-solid fa-rotate-right"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table Grid -->
<div class="bank-table-card mb-4">
    <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-white">
        <div>
            <h5 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-list-ol me-2 text-primary"></i>Bank Statement Logs</h5>
            <p class="text-muted small mb-0">Showing <?php echo count($transactions); ?> transaction entries</p>
        </div>
        <button class="btn btn-sm btn-primary rounded-3 px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#addBankModal">
            <i class="fa-solid fa-plus me-1"></i>New Entry
        </button>
    </div>

    <div class="table-responsive">
        <table class="table bank-table align-middle mb-0" id="bankDataTable">
            <thead>
                <tr>
                    <th>Ref ID</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Bank Name</th>
                    <th>Account Number</th>
                    <th>Ref / Cheque No</th>
                    <th class="text-end">Amount</th>
                    <th>Description</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="py-4">
                                <i class="fa-solid fa-building-columns fs-1 text-muted opacity-50 mb-3 d-block"></i>
                                <h6 class="fw-bold text-secondary">No Bank Transactions Found</h6>
                                <p class="text-muted small mb-0">No deposits or withdrawals match your current filters.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($transactions as $tx): 
                    $isDeposit = ($tx['transaction_type'] === 'Deposit');
                    $rowDateFormatted = date('d M Y', strtotime($tx['date']));
                    $searchTag = strtolower($tx['bank_name'] . ' ' . $tx['account_number'] . ' ' . $tx['reference_number'] . ' ' . $tx['transaction_type'] . ' ' . $tx['description']);
                ?>
                    <tr class="bank-row" data-search="<?php echo htmlspecialchars($searchTag); ?>">
                        <td><code class="fw-bold text-primary">TX-<?php echo str_pad($tx['id'], 6, '0', STR_PAD_LEFT); ?></code></td>
                        <td>
                            <span class="fw-semibold text-dark"><?php echo $rowDateFormatted; ?></span>
                        </td>
                        <td>
                            <?php if ($isDeposit): ?>
                                <span class="badge badge-soft-success px-3 py-2 rounded-pill fw-bold">
                                    <i class="fa-solid fa-arrow-down-left me-1"></i>Deposit
                                </span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger px-3 py-2 rounded-pill fw-bold">
                                    <i class="fa-solid fa-arrow-up-right me-1"></i>Withdrawal
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold text-dark">
                            <i class="fa-solid fa-building-columns text-secondary me-2"></i><?php echo sanitize($tx['bank_name']); ?>
                        </td>
                        <td><code class="bg-light px-2 py-1 rounded text-dark"><?php echo sanitize($tx['account_number']); ?></code></td>
                        <td>
                            <?php if (!empty($tx['reference_number'])): ?>
                                <span class="badge bg-light text-dark border px-2.5 py-1 rounded-3 font-monospace">
                                    <i class="fa-solid fa-money-check me-1 text-primary"></i><?php echo sanitize($tx['reference_number']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-extrabold fs-6 <?php echo $isDeposit ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $isDeposit ? '+' : '-'; ?> Rs. <?php echo number_format($tx['amount'], 2); ?>
                        </td>
                        <td class="text-muted small" style="max-width: 220px; white-space: normal;">
                            <?php echo sanitize($tx['description'] ?: '—'); ?>
                        </td>
                        <td class="text-center">
                            <div class="d-inline-flex gap-1">
                                <button class="btn btn-sm btn-outline-primary rounded-circle p-2 btn-print-voucher" 
                                        data-ref="TX-<?php echo str_pad($tx['id'], 6, '0', STR_PAD_LEFT); ?>"
                                        data-date="<?php echo $rowDateFormatted; ?>"
                                        data-type="<?php echo htmlspecialchars($tx['transaction_type']); ?>"
                                        data-bank="<?php echo htmlspecialchars($tx['bank_name']); ?>"
                                        data-account="<?php echo htmlspecialchars($tx['account_number']); ?>"
                                        data-refno="<?php echo htmlspecialchars($tx['reference_number'] ?: '—'); ?>"
                                        data-amount="<?php echo number_format($tx['amount'], 2); ?>"
                                        data-desc="<?php echo htmlspecialchars($tx['description'] ?? ''); ?>"
                                        title="Print Transaction Slip">
                                    <i class="fa-solid fa-print"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary rounded-circle p-2 btn-edit-bank" 
                                        data-id="<?php echo $tx['id']; ?>"
                                        data-date="<?php echo $tx['date']; ?>"
                                        data-type="<?php echo htmlspecialchars($tx['transaction_type']); ?>"
                                        data-bank="<?php echo htmlspecialchars($tx['bank_name']); ?>"
                                        data-account="<?php echo htmlspecialchars($tx['account_number']); ?>"
                                        data-refno="<?php echo htmlspecialchars($tx['reference_number'] ?? ''); ?>"
                                        data-amount="<?php echo $tx['amount']; ?>"
                                        data-desc="<?php echo htmlspecialchars($tx['description'] ?? ''); ?>"
                                        title="Edit Transaction">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger rounded-circle p-2 btn-delete-bank" 
                                        data-id="<?php echo $tx['id']; ?>" 
                                        title="Delete Transaction">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Log Bank Transaction Modal -->
<div class="modal fade" id="addBankModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;">
            <div class="modal-header bg-dark text-white p-4" style="border-top-left-radius: 18px; border-top-right-radius: 18px;">
                <div>
                    <h5 class="modal-title fw-bold mb-0"><i class="fa-solid fa-building-columns me-2 text-warning"></i>Log Bank Transaction</h5>
                    <small class="text-slate-300">Record school bank deposit or checkbook withdrawal</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="addBankForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_bank_transaction">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Transaction Date *</label>
                            <input type="date" class="form-control" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Transaction Type *</label>
                            <select class="form-select fw-bold" name="transaction_type" id="add_transaction_type" required>
                                <option value="Deposit" class="text-success">Deposit (+)</option>
                                <option value="Withdrawal" class="text-danger">Withdrawal (-)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Bank Name *</label>
                            <input type="text" class="form-control" name="bank_name" placeholder="e.g. HBL / Meezan Bank" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Account Number *</label>
                            <input type="text" class="form-control" name="account_number" placeholder="e.g. 1002-790123-01" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Amount (Rs.) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">Rs.</span>
                                <input type="number" class="form-control fw-bold" name="amount" step="0.01" min="1" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Cheque / Slip Ref No</label>
                            <input type="text" class="form-control" name="reference_number" placeholder="e.g. CHQ-9011 / TRF-402">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Description / Transfer Purpose</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Specify transfer reasons, fee bank deposit, or vendor cheque details..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer p-3 border-0 bg-light rounded-bottom-4">
                <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addBankForm" class="btn btn-primary px-4 rounded-3 fw-bold" id="btnAddBank">
                    <i class="fa-solid fa-save me-2"></i>Save Transaction Log
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Bank Transaction Modal -->
<div class="modal fade" id="editBankModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;">
            <div class="modal-header bg-dark text-white p-4" style="border-top-left-radius: 18px; border-top-right-radius: 18px;">
                <div>
                    <h5 class="modal-title fw-bold mb-0"><i class="fa-solid fa-pen-to-square me-2 text-warning"></i>Modify Bank Transaction</h5>
                    <small class="text-slate-300">Update transaction details</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="editBankForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="edit_bank_transaction">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Transaction Date *</label>
                            <input type="date" class="form-control" name="date" id="edit_date" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Transaction Type *</label>
                            <select class="form-select fw-bold" name="transaction_type" id="edit_type" required>
                                <option value="Deposit">Deposit (+)</option>
                                <option value="Withdrawal">Withdrawal (-)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Bank Name *</label>
                            <input type="text" class="form-control" name="bank_name" id="edit_bank" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Account Number *</label>
                            <input type="text" class="form-control" name="account_number" id="edit_account" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Amount (Rs.) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">Rs.</span>
                                <input type="number" class="form-control fw-bold" name="amount" id="edit_amount" step="0.01" min="1" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Cheque / Slip Ref No</label>
                            <input type="text" class="form-control" name="reference_number" id="edit_refno">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Description</label>
                        <textarea class="form-control" name="description" id="edit_desc" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer p-3 border-0 bg-light rounded-bottom-4">
                <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editBankForm" class="btn btn-primary px-4 rounded-3 fw-bold" id="btnEditBank">
                    <i class="fa-solid fa-save me-2"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Print Voucher Slip Template Structure -->
<div id="printBankTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 30px; border: 2px solid #0f172a; width: 650px; margin: 0 auto; border-radius:12px; color: #1e293b;">
        <div style="text-align: center; border-bottom: 2px double #0f172a; padding-bottom: 12px; margin-bottom: 20px;">
            <h2 style="margin: 0; font-size: 22px; text-transform: uppercase; color: #0f172a; font-weight: 800;"><?php echo SCHOOL_NAME; ?></h2>
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b;"><?php echo SCHOOL_ADDRESS; ?></p>
            <div style="margin-top: 12px; background: #0f172a; color: #fff; padding: 6px 15px; border-radius: 4px; display: inline-block; font-size: 13px; font-weight: bold; letter-spacing: 1px;">
                OFFICIAL BANK TRANSACTION SLIP
            </div>
        </div>
        
        <table style="width: 100%; margin-bottom: 20px; font-size: 13px;">
            <tr>
                <td><strong>Voucher Ref:</strong> <span id="bp_ref" style="font-family: monospace;"></span></td>
                <td style="text-align: right;"><strong>Date:</strong> <span id="bp_date"></span></td>
            </tr>
        </table>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px;" border="1" cellpadding="8">
            <tr style="background: #f8fafc;">
                <td style="width: 40%;"><strong>Transaction Category:</strong></td>
                <td style="text-align: right; font-weight: bold;" id="bp_type"></td>
            </tr>
            <tr>
                <td><strong>Bank Name:</strong></td>
                <td style="text-align: right; font-weight: bold;" id="bp_bank"></td>
            </tr>
            <tr style="background: #f8fafc;">
                <td><strong>Account Number:</strong></td>
                <td style="text-align: right; font-family: monospace;" id="bp_account"></td>
            </tr>
            <tr>
                <td><strong>Cheque / Reference No:</strong></td>
                <td style="text-align: right; font-family: monospace;" id="bp_refno"></td>
            </tr>
            <tr style="background: #f8fafc;">
                <td><strong>Description / Purpose:</strong></td>
                <td style="text-align: right;" id="bp_desc"></td>
            </tr>
        </table>
        
        <div style="background: #f1f5f9; padding: 16px; text-align: center; border-radius: 8px; margin-bottom: 30px; border: 1px solid #cbd5e1;">
            <h3 style="margin: 0; color: #0f172a; font-size: 20px;">TRANSACTION AMOUNT: Rs. <span id="bp_amount"></span></h3>
        </div>
        
        <table style="width: 100%; margin-top: 50px; font-size: 12px; text-align: center;">
            <tr>
                <td style="width: 50%;">
                    <div style="border-top: 1px solid #334155; width: 170px; margin: 0 auto; padding-top: 6px;">
                        <strong>Handled / Disbursed By</strong><br><span style="color:#64748b;">Bank Cashier</span>
                    </div>
                </td>
                <td style="width: 50%;">
                    <div style="border-top: 1px solid #334155; width: 170px; margin: 0 auto; padding-top: 6px;">
                        <strong>Approved By</strong><br><span style="color:#64748b;">Accounts Manager</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="bnkToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="bnkToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("bnkToast");
    const m = document.getElementById("bnkToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function setTransactionType(type) {
    const select = document.getElementById("add_transaction_type");
    if (select) select.value = type;
}

document.addEventListener("DOMContentLoaded", function() {
    // 1. Live Instant Search Filter
    const searchInput = document.getElementById("bankSearchInput");
    if (searchInput) {
        searchInput.addEventListener("keyup", function() {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll("#bankDataTable tbody tr.bank-row");
            rows.forEach(row => {
                const text = row.getAttribute("data-search") || "";
                row.style.display = text.includes(query) ? "" : "none";
            });
        });
    }

    // 2. Add Bank Transaction Form AJAX
    const addForm = document.getElementById("addBankForm");
    if (addForm) {
        addForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnAddBank");
            btn.disabled = true; btn.innerHTML = "<i class=\"fa-solid fa-spinner fa-spin me-2\"></i>Saving...";
            
            fetch("../../ajax/accounts.php", { method: "POST", body: new FormData(addForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 900);
                    else { btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-save me-2\"></i>Save Transaction Log"; }
                })
                .catch(() => { showToast("Network error occurred.", false); btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-save me-2\"></i>Save Transaction Log"; });
        });
    }

    // 3. Bind Edit Modal
    document.querySelectorAll(".btn-edit-bank").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("edit_id").value = this.dataset.id;
            document.getElementById("edit_date").value = this.dataset.date;
            document.getElementById("edit_type").value = this.dataset.type;
            document.getElementById("edit_bank").value = this.dataset.bank;
            document.getElementById("edit_account").value = this.dataset.account;
            document.getElementById("edit_refno").value = this.dataset.refno;
            document.getElementById("edit_amount").value = this.dataset.amount;
            document.getElementById("edit_desc").value = this.dataset.desc;
            
            new bootstrap.Modal(document.getElementById("editBankModal")).show();
        });
    });

    // 4. Save Edit Form AJAX
    const editForm = document.getElementById("editBankForm");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnEditBank");
            btn.disabled = true; btn.innerHTML = "<i class=\"fa-solid fa-spinner fa-spin me-2\"></i>Saving...";
            
            fetch("../../ajax/accounts.php", { method: "POST", body: new FormData(editForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 900);
                    else { btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-save me-2\"></i>Save Changes"; }
                })
                .catch(() => { showToast("Network error occurred.", false); btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-save me-2\"></i>Save Changes"; });
        });
    }

    // 5. Delete Transaction AJAX
    document.querySelectorAll(".btn-delete-bank").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            if(!confirm("Are you sure you want to permanently delete this bank transaction record?")) return;
            const fd = new FormData();
            fd.append("action", "delete_bank_transaction");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/accounts.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 900);
                });
        });
    });

    // 6. Print Voucher Slip Action
    document.querySelectorAll(".btn-print-voucher").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("bp_ref").textContent = this.dataset.ref;
            document.getElementById("bp_date").textContent = this.dataset.date;
            document.getElementById("bp_type").textContent = this.dataset.type;
            document.getElementById("bp_bank").textContent = this.dataset.bank;
            document.getElementById("bp_account").textContent = this.dataset.account;
            document.getElementById("bp_refno").textContent = this.dataset.refno;
            document.getElementById("bp_desc").textContent = this.dataset.desc || "Bank Transfer Deposit / Withdrawal";
            document.getElementById("bp_amount").textContent = this.dataset.amount;
            
            const printContent = document.getElementById("printBankTemplate").innerHTML;
            
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Bank Transaction Slip</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
