<?php
/**
 * Indus Grammar School ERP - Fee Ledger Sheets Viewer & Generator
 * Version 4.0.0
 */

$pageTitle = 'Fee Ledger Sheets';
$breadcrumbActive = 'Fee Ledger Sheets';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('fee_view');

// Fetch classes for dropdown filters
$classes = SchoolClass::all();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$filters = [
    'status'        => sanitize($_GET['status'] ?? ''),
    'academic_type' => sanitize($_GET['academic_type'] ?? ''),
    'class_id'      => isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0,
    'month'         => sanitize($_GET['month'] ?? ''),
    'search'        => sanitize($_GET['search'] ?? '')
];

// Fetch Data & Summary
$ledgerEntries = Fee::allChallans($filters, $limit, $offset);
$totalEntries  = Fee::countChallans($filters);
$summaryStats  = Fee::getFilteredLedgerSummary($filters);
$totalPages    = ceil($totalEntries / $limit);

$statusBadge = [
    'Pending' => ['class' => 'badge-pending', 'label' => 'Pending'],
    'Paid'    => ['class' => 'badge-paid', 'label' => 'Paid'],
    'Partial' => ['class' => 'badge-partial', 'label' => 'Partial']
];
?>

<style>
:root {
    --primary-navy: #0F172A;
    --primary-blue: #1D4ED8;
    --secondary-blue: #2563EB;
    --light-blue: #EFF6FF;
    --page-background: #F1F5F9;
    --card-white: #FFFFFF;
    --border-color: #E2E8F0;
    --main-text: #1E293B;
    --muted-text: #64748B;
    --success-color: #16A34A;
    --danger-color: #DC2626;
    --warning-color: #D97706;
}

body {
    background-color: var(--page-background);
    color: var(--main-text);
}

.ledger-hero-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 55%, #2563eb 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 2rem 2.25rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.25);
    position: relative;
    overflow: hidden;
}

.ledger-hero-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.ledger-hero-header .breadcrumb {
    background: transparent;
    padding: 0;
    margin-bottom: 0.75rem;
}

.ledger-hero-header .breadcrumb-item,
.ledger-hero-header .breadcrumb-item a {
    color: rgba(255, 255, 255, 0.75);
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
}

.ledger-hero-header .breadcrumb-item.active {
    color: #ffffff;
    font-weight: 600;
}

.ledger-hero-header .breadcrumb-item + .breadcrumb-item::before {
    color: rgba(255, 255, 255, 0.5);
}

.ledger-hero-icon {
    width: 64px;
    height: 64px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.stat-card {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 1.15rem 1.25rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(15, 23, 42, 0.06);
}

.stat-icon-wrapper {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}

.panel-card {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.panel-card-header {
    background: #ffffff;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.panel-card-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--primary-navy);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.panel-card-body {
    padding: 1.5rem;
}

.custom-table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

.custom-table th {
    background-color: #f8fafc;
    color: var(--muted-text);
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--border-color);
}

.custom-table td {
    padding: 0.85rem 1rem;
    font-size: 0.875rem;
    color: var(--main-text);
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}

.custom-table tr:last-child td {
    border-bottom: none;
}

.badge-paid {
    background-color: #dcfce7;
    color: #166534;
}

.badge-partial {
    background-color: #fef3c7;
    color: #92400e;
}

.badge-pending {
    background-color: #fee2e2;
    color: #991b1b;
}

@media print {
    body { background-color: #ffffff !important; padding: 0 !important; }
    .ledger-hero-header, .no-print, .btn, .modal, .pagination, header, footer, sidebar { display: none !important; }
    .container-fluid { padding: 0 !important; }
    .panel-card { border: none !important; box-shadow: none !important; margin: 0 !important; }
    .custom-table th, .custom-table td { padding: 6px 8px !important; font-size: 11px !important; }
    .print-only-header { display: block !important; }
}

.print-only-header { display: none; }
</style>

<div class="container-fluid px-4 py-3">

    <!-- Print Only Header -->
    <div class="print-only-header mb-4 text-center">
        <h3 class="fw-bold mb-1" style="color: #0F172A;">INDUS GRAMMAR SCHOOL</h3>
        <p class="mb-1 text-muted">Fee Ledger Sheets Report</p>
        <div class="small text-muted">Generated Date: <?php echo date('d M Y, h:i A'); ?></div>
        <hr>
    </div>

    <!-- 1. Page Header (Hero Blue Gradient) -->
    <div class="ledger-hero-header d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="#">Accounts</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Fee Ledger Sheets</li>
                </ol>
            </nav>
            <h2 class="fw-bold mb-1 text-white">Fee Ledger Sheets</h2>
            <p class="mb-0 opacity-75 small">Review, track, and manage student fee ledger records.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button class="btn btn-light text-primary fw-semibold shadow-sm px-3" onclick="window.print()">
                <i class="fa-solid fa-print me-1.5"></i> Print Ledger
            </button>
            <button class="btn btn-outline-light fw-semibold shadow-sm px-3" id="btnExportCSV">
                <i class="fa-solid fa-file-csv me-1.5"></i> Export CSV
            </button>
            <?php if (hasPermission('fee_manage')): ?>
            <button class="btn btn-outline-light fw-semibold shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#generateBatchChallanModal">
                <i class="fa-solid fa-files-medical me-1.5"></i> Batch Class Ledger
            </button>
            <button class="btn btn-light text-primary fw-bold shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#generateChallanModal">
                <i class="fa-solid fa-plus me-1.5"></i> Generate Ledger
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. Financial Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <!-- Total Records -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-wrapper" style="background:#f8fafc; color:#334155;">
                        <i class="fa-solid fa-list-check"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Records</div>
                        <h5 class="fw-bold text-dark mb-0"><?php echo number_format($summaryStats['total_records']); ?></h5>
                    </div>
                </div>
            </div>
        </div>
        <!-- Total Payable -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-wrapper" style="background:#eff6ff; color:#1d4ed8;">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Payable</div>
                        <h5 class="fw-bold text-primary mb-0">Rs. <?php echo number_format($summaryStats['total_payable'], 0); ?></h5>
                    </div>
                </div>
            </div>
        </div>
        <!-- Total Collected -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-wrapper" style="background:#ecfdf5; color:#16a34a;">
                        <i class="fa-solid fa-money-bill-trend-up"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Collected</div>
                        <h5 class="fw-bold text-success mb-0">Rs. <?php echo number_format($summaryStats['total_paid'], 0); ?></h5>
                    </div>
                </div>
            </div>
        </div>
        <!-- Total Outstanding -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-wrapper" style="background:#fef2f2; color:#dc2626;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Outstanding</div>
                        <h5 class="fw-bold text-danger mb-0">Rs. <?php echo number_format($summaryStats['total_outstanding'], 0); ?></h5>
                    </div>
                </div>
            </div>
        </div>
        <!-- Paid Records Count -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-wrapper" style="background:#f0fdf4; color:#16a34a;">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Paid Records</div>
                        <h5 class="fw-bold text-success mb-0"><?php echo number_format($summaryStats['paid_records']); ?></h5>
                    </div>
                </div>
            </div>
        </div>
        <!-- Pending Records Count -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-wrapper" style="background:#fffbeb; color:#d97706;">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Pending Records</div>
                        <h5 class="fw-bold text-warning mb-0"><?php echo number_format($summaryStats['pending_records']); ?></h5>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Filter Section -->
    <div class="panel-card no-print">
        <div class="panel-card-header">
            <h3 class="panel-card-title">
                <i class="fa-solid fa-filter text-primary"></i> Filter Fee Ledger Sheets
            </h3>
        </div>
        <div class="panel-card-body">
            <form method="GET" class="row g-3 align-items-end" id="ledgerFilterForm">
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-dark">Academic Type</label>
                    <select class="form-select" name="academic_type">
                        <option value="">All Types</option>
                        <option value="School" <?php echo ($filters['academic_type'] === 'School') ? 'selected' : ''; ?>>School</option>
                        <option value="Academy" <?php echo ($filters['academic_type'] === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-dark">Class & Section</label>
                    <select class="form-select" name="class_id">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($filters['class_id'] == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-dark">Fee Month</label>
                    <input type="text" class="form-control" name="month" placeholder="e.g. September 2026" value="<?php echo htmlspecialchars($filters['month']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-dark">Payment Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="Pending" <?php echo ($filters['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="Paid" <?php echo ($filters['status'] === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                        <option value="Partial" <?php echo ($filters['status'] === 'Partial') ? 'selected' : ''; ?>>Partial</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-dark">Search Student</label>
                    <input type="text" class="form-control" name="search" placeholder="Name, Admission No..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                <div class="col-12 d-flex gap-2 justify-content-end pt-2">
                    <a href="challan.php" class="btn btn-outline-secondary px-4 fw-semibold">Reset Filters</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-magnifying-glass me-2"></i>Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. Fee Ledger Records Table -->
    <div class="panel-card">
        <div class="panel-card-header no-print">
            <h3 class="panel-card-title">
                <i class="fa-solid fa-receipt text-primary"></i> Student Ledger Statements
            </h3>
            <span class="text-muted small">Showing <?php echo count($ledgerEntries); ?> of <?php echo $totalEntries; ?> records</span>
        </div>
        <div class="panel-card-body p-0">
            <div class="table-responsive">
                <table class="custom-table" id="ledgerTable">
                    <thead>
                        <tr>
                            <th>Ledger ID</th>
                            <th>Student Details</th>
                            <th>Month / Year</th>
                            <th>Due Date</th>
                            <th class="text-end">Net Payable</th>
                            <th class="text-end">Paid Amount</th>
                            <th class="text-end">Balance</th>
                            <th>Status</th>
                            <th class="text-end no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ledgerEntries)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <div class="py-4">
                                        <i class="fa-solid fa-folder-open fs-1 text-muted mb-3 d-block opacity-50"></i>
                                        <h6 class="fw-bold text-dark mb-1">No Fee Ledger Records Found</h6>
                                        <p class="small text-muted mb-3">No matching ledger entries found for the selected filter criteria.</p>
                                        <a href="challan.php" class="btn btn-sm btn-outline-primary px-3 rounded-pill no-print">Reset Filters</a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: foreach ($ledgerEntries as $c): 
                            $netPayable = (float)$c['total_payable'];
                            $paidAmt   = (float)$c['paid_amount'];
                            $balance   = max(0.00, $netPayable - $paidAmt);
                            $bInfo     = $statusBadge[$c['status']] ?? ['class' => 'bg-secondary', 'label' => $c['status']];
                        ?>
                            <tr>
                                <td><span class="fw-bold font-monospace text-primary">#<?php echo str_pad($c['id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo sanitize($c['first_name'] . ' ' . $c['last_name']); ?></div>
                                    <div class="text-muted small">
                                        Admn: <code class="text-primary"><?php echo sanitize($c['admission_no']); ?></code> &middot; <?php echo sanitize(($c['class_name'] ?? '') . ' ' . ($c['section'] ?? '')); ?> &middot; <?php echo sanitize($c['academic_type']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?php echo sanitize($c['month']); ?></div>
                                    <div class="text-muted small"><?php echo sanitize($c['academic_year']); ?></div>
                                </td>
                                <td><?php echo date('d M Y', strtotime($c['due_date'])); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($netPayable, 2); ?></td>
                                <td class="text-end fw-bold text-success">Rs. <?php echo number_format($paidAmt, 2); ?></td>
                                <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($balance, 2); ?></td>
                                <td>
                                    <span class="badge <?php echo $bInfo['class']; ?> px-3 py-1.5 rounded-pill fw-bold small">
                                        <?php echo sanitize($bInfo['label']); ?>
                                    </span>
                                </td>
                                <td class="text-end no-print">
                                    <div class="btn-group">
                                        <?php if ($c['status'] !== 'Paid' && hasPermission('fee_collect')): ?>
                                            <a href="collection.php?student_id=<?php echo $c['student_id']; ?>" class="btn btn-sm btn-success px-2.5" title="Collect Payment">
                                                <i class="fa-solid fa-hand-holding-dollar"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="../../templates/challan.php?id=<?php echo $c['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary px-2.5" title="Print Ledger Slip">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="p-3 border-top bg-light d-flex justify-content-between align-items-center flex-wrap gap-2 no-print">
                    <span class="text-muted small">Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> &middot; (Total Records: <?php echo number_format($totalEntries); ?>)</span>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($filters['status']); ?>&academic_type=<?php echo urlencode($filters['academic_type']); ?>&class_id=<?php echo $filters['class_id']; ?>&month=<?php echo urlencode($filters['month']); ?>&search=<?php echo urlencode($filters['search']); ?>">Previous</a></li>
                            <?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filters['status']); ?>&academic_type=<?php echo urlencode($filters['academic_type']); ?>&class_id=<?php echo $filters['class_id']; ?>&month=<?php echo urlencode($filters['month']); ?>&search=<?php echo urlencode($filters['search']); ?>"><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($filters['status']); ?>&academic_type=<?php echo urlencode($filters['academic_type']); ?>&class_id=<?php echo $filters['class_id']; ?>&month=<?php echo urlencode($filters['month']); ?>&search=<?php echo urlencode($filters['search']); ?>">Next</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Modal 1: Generate Single Student Ledger Entry -->
<?php if (hasPermission('fee_manage')): ?>
<div class="modal fade" id="generateChallanModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-0 pt-4 px-4 pb-0">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Generate Student Ledger Month</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="generateChallanForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="generate_ledger_entry">
                    
                    <div class="mb-3 position-relative">
                        <label class="form-label small fw-bold text-dark">Search Student</label>
                        <input type="text" class="form-control" id="genStudentSearch" placeholder="Enter Student Name or Admission No..." autocomplete="off">
                        <input type="hidden" name="student_id" id="genStudentId">
                        <div id="genSearchResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1000; display:none; max-height:200px; overflow-y:auto; border-radius: 8px;"></div>
                    </div>
                    
                    <div id="selectedStudentInfo" class="mb-3 d-none">
                        <div class="alert alert-secondary py-2 px-3 border-0 mb-0 d-flex justify-content-between align-items-center" style="border-radius:10px;">
                            <span class="fw-bold text-dark small" id="genStudentName"></span>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none fw-semibold" id="clearStudentSelection">Clear</button>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Fee Month</label>
                            <select class="form-select" name="month" required>
                                <?php 
                                    $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                                    $currentMonth = date('F');
                                    foreach ($months as $m) {
                                        $selected = ($m === $currentMonth) ? 'selected' : '';
                                        echo "<option value=\"$m " . date('Y') . "\" $selected>$m " . date('Y') . "</option>";
                                    }
                                ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Due Date</label>
                            <input type="date" class="form-control" name="due_date" value="<?php echo date('Y-m-15'); ?>" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="generateChallanForm" class="btn btn-primary px-4 fw-bold" id="btnGenerate">Generate Ledger</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Generate Batch Class Ledger -->
<div class="modal fade" id="generateBatchChallanModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-0 pt-4 px-4 pb-0">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-files-medical me-2 text-primary"></i>Batch Class Ledger</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="generateBatchChallanForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="generate_class_ledger">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Academic Type</label>
                        <select class="form-select" name="academic_type" required>
                            <option value="School">School</option>
                            <option value="Academy">Academy</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Select Class</label>
                        <select class="form-select" name="class_id" required>
                            <option value="">— Select Class —</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Fee Month</label>
                            <select class="form-select" name="month" required>
                                <?php 
                                    foreach ($months as $m) {
                                        $selected = ($m === $currentMonth) ? 'selected' : '';
                                        echo "<option value=\"$m " . date('Y') . "\" $selected>$m " . date('Y') . "</option>";
                                    }
                                ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Due Date</label>
                            <input type="date" class="form-control" name="due_date" value="<?php echo date('Y-m-15'); ?>" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="generateBatchChallanForm" class="btn btn-primary px-4 fw-bold" id="btnGenerateBatch">Generate Batch</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="challanToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert" style="border-radius:12px;">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="challanToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("challanToast");
    const m = document.getElementById("challanToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // CSV Exporter
    document.getElementById("btnExportCSV")?.addEventListener("click", function() {
        const table = document.getElementById("ledgerTable");
        if (!table) return;
        
        let csv = [];
        const rows = table.querySelectorAll("tr");
        
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll("td, th");
            // Skip action column (index 8)
            for (let j = 0; j < cols.length - 1; j++) {
                let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/\s+/g, " ").trim();
                text = text.replace(/"/g, \'""\');
                row.push(\'"\' + text + \'"\');
            }
            if (row.length > 0) csv.push(row.join(","));
        }

        const csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
        const downloadLink = document.createElement("a");
        downloadLink.download = "fee_ledger_sheets_" + new Date().toISOString().slice(0,10) + ".csv";
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = "none";
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    });

    // Search Student inside Single Generation Modal
    const searchInput = document.getElementById("genStudentSearch");
    const searchResults = document.getElementById("genSearchResults");
    const studentIdInput = document.getElementById("genStudentId");
    const selectedInfo = document.getElementById("selectedStudentInfo");
    const studentName = document.getElementById("genStudentName");
    const clearBtn = document.getElementById("clearStudentSelection");
    
    let searchTimeout;
    searchInput?.addEventListener("input", function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        if (q.length < 2) { searchResults.style.display = "none"; return; }
        
        searchTimeout = setTimeout(() => {
            const fd = new FormData();
            fd.append("action", "search_student");
            const tokenVal = document.querySelector(\'input[name="csrf_token"]\')?.value || \'' . csrfToken() . '\';
            fd.append("csrf_token", tokenVal);
            fd.append("q", q);
            
            fetch("../../ajax/fees.php", { method: "POST", body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.new_csrf_token) {
                    document.querySelectorAll("input[name=\"csrf_token\"]").forEach(el => el.value = data.new_csrf_token);
                }
                searchResults.innerHTML = "";
                if (data.success && data.students.length > 0) {
                    data.students.forEach(s => {
                        const a = document.createElement("a");
                        a.href = "#";
                        a.className = "list-group-item list-group-item-action py-2 px-3";
                        a.innerHTML = `<div class="fw-semibold text-dark">${s.first_name} ${s.last_name}</div><small class="text-muted">Admn: ${s.admission_no} &middot; Class: ${s.class_name || ""} ${s.section || ""}</small>`;
                        a.addEventListener("click", function(e) {
                            e.preventDefault();
                            studentIdInput.value = s.id;
                            studentName.textContent = `${s.first_name} ${s.last_name} (${s.admission_no})`;
                            searchInput.parentElement.classList.add("d-none");
                            selectedInfo.classList.remove("d-none");
                            searchResults.style.display = "none";
                        });
                        searchResults.appendChild(a);
                    });
                    searchResults.style.display = "block";
                } else {
                    searchResults.innerHTML = \'<div class="list-group-item text-muted py-2 text-center small">No active students found.</div>\';
                    searchResults.style.display = "block";
                }
            });
        }, 300);
    });

    clearBtn?.addEventListener("click", function() {
        studentIdInput.value = "";
        searchInput.value = "";
        selectedInfo.classList.add("d-none");
        searchInput.parentElement.classList.remove("d-none");
    });

    // Form Submission: Single Ledger Entry
    const singleForm = document.getElementById("generateChallanForm");
    singleForm?.addEventListener("submit", function(e) {
        e.preventDefault();
        if (!studentIdInput.value) { showToast("Please search and select a student.", false); return; }
        
        const btn = document.getElementById("btnGenerate");
        btn.disabled = true;
        btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Generating...\';
        
        fetch("../../ajax/fees.php", { method: "POST", body: new FormData(singleForm) })
        .then(r => r.json())
        .then(data => {
            if (data.new_csrf_token) {
                document.querySelectorAll("input[name=\"csrf_token\"]").forEach(el => el.value = data.new_csrf_token);
            }
            showToast(data.message, data.success);
            if (data.success) {
                setTimeout(() => location.reload(), 1200);
            } else {
                btn.disabled = false;
                btn.innerHTML = "Generate Ledger";
            }
        })
        .catch(() => {
            showToast("Network connection error.", false);
            btn.disabled = false;
            btn.innerHTML = "Generate Ledger";
        });
    });

    // Form Submission: Batch Ledger Entries
    const batchForm = document.getElementById("generateBatchChallanForm");
    batchForm?.addEventListener("submit", function(e) {
        e.preventDefault();
        const btn = document.getElementById("btnGenerateBatch");
        btn.disabled = true;
        btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Processing...\';
        
        fetch("../../ajax/fees.php", { method: "POST", body: new FormData(batchForm) })
        .then(r => r.json())
        .then(data => {
            if (data.new_csrf_token) {
                document.querySelectorAll("input[name=\"csrf_token\"]").forEach(el => el.value = data.new_csrf_token);
            }
            showToast(data.message, data.success);
            if (data.success) {
                setTimeout(() => location.reload(), 1500);
            } else {
                btn.disabled = false;
                btn.innerHTML = "Generate Batch";
            }
        })
        .catch(() => {
            showToast("Network connection error.", false);
            btn.disabled = false;
            btn.innerHTML = "Generate Batch";
        });
    });
});
</script>';

include_once __DIR__ . '/../../includes/footer.php';
?>

