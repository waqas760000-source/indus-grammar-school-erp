<?php
/**
 * Indus Grammar School ERP - Operational Expense Categories Manager
 * Version 4.0.0 (Premium Financial Audit Suite)
 */

$pageTitle = 'Expense Categories';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// Fetch all categories with total expenses recorded and transaction counts
$categories = [];
$totalDisbursedOverall = 0;
$activeCount = 0;
$inactiveCount = 0;
$topCategoryName = 'N/A';
$topCategorySpent = 0;

try {
    $categories = $db->query("
        SELECT 
            c.*, 
            COUNT(e.id) as expense_count, 
            COALESCE(SUM(e.amount), 0) as total_spent,
            MAX(e.expense_date) as last_expense_date
        FROM expense_categories c
        LEFT JOIN expenses e ON c.id = e.category_id
        GROUP BY c.id
        ORDER BY c.name ASC
    ")->fetchAll();

    foreach ($categories as $cat) {
        $totalDisbursedOverall += (float)$cat['total_spent'];
        if ($cat['status'] === 'Active') {
            $activeCount++;
        } else {
            $inactiveCount++;
        }

        if ((float)$cat['total_spent'] > $topCategorySpent) {
            $topCategorySpent = (float)$cat['total_spent'];
            $topCategoryName = $cat['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error loading expense categories: " . $e->getMessage());
}

$totalCategories = count($categories);
$avgSpendPerCat = $totalCategories > 0 ? ($totalDisbursedOverall / $totalCategories) : 0;
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

:root {
    --cat-font-family: 'Outfit', sans-serif;
    --cat-primary: #4f46e5;
    --cat-primary-dark: #4338ca;
    --cat-primary-light: #eef2ff;
    --cat-success: #10b981;
    --cat-danger: #ef4444;
    --cat-warning: #f59e0b;
    --cat-dark: #0f172a;
    --cat-muted: #64748b;
    --cat-card-bg: #ffffff;
    --cat-border: #e2e8f0;
    --cat-radius: 16px;
    --cat-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.04), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
}

body {
    font-family: var(--cat-font-family);
    background-color: #f8fafc;
}

.cat-hero-card {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4338ca 100%);
    border-radius: var(--cat-radius);
    padding: 2.25rem 2rem;
    color: #ffffff;
    box-shadow: 0 20px 25px -5px rgba(49, 46, 129, 0.25);
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
}

.cat-hero-card::before {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.cat-hero-title {
    font-weight: 700;
    font-size: 1.85rem;
    letter-spacing: -0.025em;
}

.cat-kpi-card {
    background: var(--cat-card-bg);
    border: 1px solid var(--cat-border);
    border-radius: var(--cat-radius);
    padding: 1.35rem 1.25rem;
    box-shadow: var(--cat-shadow);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    height: 100%;
}

.cat-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 30px -5px rgba(15, 23, 42, 0.08);
}

.cat-kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.cat-kpi-val {
    font-weight: 700;
    font-size: 1.55rem;
    color: var(--cat-dark);
    line-height: 1.2;
    margin-top: 0.35rem;
}

.cat-quick-pill {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 30px;
    padding: 0.4rem 1rem;
    font-size: 0.85rem;
    font-weight: 500;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.cat-quick-pill:hover {
    background: var(--cat-primary-light);
    border-color: var(--cat-primary);
    color: var(--cat-primary-dark);
    transform: scale(1.03);
}

.cat-card-grid {
    background: #ffffff;
    border: 1px solid var(--cat-border);
    border-radius: var(--cat-radius);
    padding: 1.5rem;
    box-shadow: var(--cat-shadow);
    transition: all 0.25s ease;
    position: relative;
    overflow: hidden;
}

.cat-card-grid:hover {
    border-color: #cbd5e1;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.06);
}

.cat-badge-status {
    padding: 0.3rem 0.75rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.cat-badge-active {
    background-color: #d1fae5;
    color: #065f46;
}

.cat-badge-inactive {
    background-color: #f1f5f9;
    color: #64748b;
}

.progress-thin {
    height: 6px;
    border-radius: 4px;
    background-color: #e2e8f0;
}

.custom-table-card {
    background: var(--cat-card-bg);
    border: 1px solid var(--cat-border);
    border-radius: var(--cat-radius);
    box-shadow: var(--cat-shadow);
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
    border-bottom: 1px solid var(--cat-border);
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

.btn-view-toggle {
    border: 1px solid var(--cat-border);
    background: #ffffff;
    color: #64748b;
    padding: 0.45rem 0.85rem;
    font-weight: 500;
}

.btn-view-toggle.active {
    background: var(--cat-primary);
    color: #ffffff;
    border-color: var(--cat-primary);
}

.cat-modal .modal-content {
    border-radius: var(--cat-radius);
    border: none;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.cat-modal .modal-header {
    background: #f8fafc;
    border-bottom: 1px solid var(--cat-border);
    padding: 1.25rem 1.5rem;
}

@media print {
    body { background: #fff !important; }
    .no-print, .btn, .cat-quick-pill, nav, header, sidebar { display: none !important; }
    .cat-hero-card { background: #1e1b4b !important; color: #fff !important; }
    .cat-card-grid { break-inside: avoid; }
}
</style>

<div class="container-fluid px-0">

    <!-- Executive Hero Header -->
    <div class="cat-hero-card">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small fw-semibold">
                        <i class="fa-solid fa-shield-halved me-1 text-warning"></i> Audit Compliance Suite
                    </span>
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small">
                        <?php echo $totalCategories; ?> Standard Categories
                    </span>
                </div>
                <h1 class="cat-hero-title mb-2">Operational Expense Categories</h1>
                <p class="text-white-50 mb-0 leading-relaxed" style="max-width: 650px;">
                    Define, classify, and audit all school expenditure classes (e.g., Utility Bills, Campus Leases, Stationery & Printing, Staff Welfare) for transparent financial reporting and budgeting.
                </p>
            </div>
            <div class="col-lg-5 text-lg-end mt-4 mt-lg-0 no-print">
                <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end">
                    <button class="btn btn-light fw-bold text-dark px-4 py-2 rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fa-solid fa-plus text-primary me-2"></i>Create Category
                    </button>
                    <button class="btn btn-outline-light px-3 py-2 rounded-3" onclick="exportCategoriesCSV()">
                        <i class="fa-solid fa-file-csv me-2"></i>Export CSV
                    </button>
                    <button class="btn btn-outline-light px-3 py-2 rounded-3" onclick="window.print()">
                        <i class="fa-solid fa-print me-2"></i>Print Audit Sheet
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Operational Class Seeders / Presets -->
    <div class="mb-4 no-print">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="text-uppercase text-muted fw-bold small" style="letter-spacing:0.05em;">
                <i class="fa-solid fa-bolt text-warning me-1"></i> Quick Presets for Audit Classification
            </span>
            <small class="text-muted">Click to quickly register standard school expense classes</small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="cat-quick-pill" onclick="quickFillCategory('Utility Bills', 'Electricity, Gas, Water & Internet monthly utility obligations.')">
                <i class="fa-solid fa-lightbulb text-warning"></i> Utility Bills
            </button>
            <button type="button" class="cat-quick-pill" onclick="quickFillCategory('Building Rent & Leases', 'Monthly campus premises rent and facility lease costs.')">
                <i class="fa-solid fa-building-user text-primary"></i> Rent & Leases
            </button>
            <button type="button" class="cat-quick-pill" onclick="quickFillCategory('Office Stationery & Printing', 'Printing paper, exam answer sheets, toner, registers & office supplies.')">
                <i class="fa-solid fa-print text-info"></i> Stationery & Printing
            </button>
            <button type="button" class="cat-quick-pill" onclick="quickFillCategory('Building Repair & Maintenance', 'Electrical repairs, plumbing, painting, furniture maintenance, and HVAC service.')">
                <i class="fa-solid fa-wrench text-secondary"></i> Repairs & Maintenance
            </button>
            <button type="button" class="cat-quick-pill" onclick="quickFillCategory('Vehicle Fuel & Fleet', 'School van diesel, petrol, driver allowances, and vehicle servicing.')">
                <i class="fa-solid fa-bus text-success"></i> Vehicle Fuel & Fleet
            </button>
            <button type="button" class="cat-quick-pill" onclick="quickFillCategory('IT & Software Subscriptions', 'Domain hosting, ERP cloud servers, SMS gateway, software licenses & hardware.')">
                <i class="fa-solid fa-laptop-code text-purple" style="color:#8b5cf6;"></i> IT & Software
            </button>
            <button type="button" class="cat-quick-pill" onclick="quickFillCategory('Staff Welfare & Tea Hospitality', 'Daily tea, coffee, staff meeting refreshments, and emergency welfare.')">
                <i class="fa-solid fa-mug-hot text-danger"></i> Staff Welfare
            </button>
            <button type="button" class="cat-quick-pill" onclick="quickFillCategory('Science Lab & Sports Supplies', 'Chemistry re-agents, physics apparatus, sports kits, and lab consumables.')">
                <i class="fa-solid fa-flask text-teal" style="color:#0d9488;"></i> Lab & Sports
            </button>
        </div>
    </div>

    <!-- KPI Analytics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="cat-kpi-card">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="text-muted small fw-semibold">Defined Categories</span>
                    <div class="cat-kpi-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-folder-tree"></i>
                    </div>
                </div>
                <div class="cat-kpi-val"><?php echo number_format($totalCategories); ?></div>
                <div class="mt-2 d-flex align-items-center gap-2 small text-muted">
                    <span class="badge bg-success bg-opacity-10 text-success fw-bold"><?php echo $activeCount; ?> Active</span>
                    <?php if ($inactiveCount > 0): ?>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold"><?php echo $inactiveCount; ?> Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="cat-kpi-card">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="text-muted small fw-semibold">Total Disbursed Spend</span>
                    <div class="cat-kpi-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                </div>
                <div class="cat-kpi-val text-danger">Rs. <?php echo number_format($totalDisbursedOverall, 2); ?></div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-receipt me-1 text-secondary"></i> Sum of all posted expense vouchers
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="cat-kpi-card">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="text-muted small fw-semibold">Highest Cost Class</span>
                    <div class="cat-kpi-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-trophy"></i>
                    </div>
                </div>
                <div class="cat-kpi-val text-truncate" style="font-size:1.3rem;" title="<?php echo sanitize($topCategoryName); ?>">
                    <?php echo sanitize($topCategoryName); ?>
                </div>
                <div class="mt-2 text-muted small">
                    <span class="fw-bold text-dark">Rs. <?php echo number_format($topCategorySpent, 2); ?></span> recorded
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="cat-kpi-card">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="text-muted small fw-semibold">Avg Spend / Category</span>
                    <div class="cat-kpi-icon bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-chart-pie"></i>
                    </div>
                </div>
                <div class="cat-kpi-val text-info">Rs. <?php echo number_format($avgSpendPerCat, 2); ?></div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-calculator me-1"></i> Mean expenditure distribution
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar & View Options -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="catSearchInput" class="form-control border-start-0 ps-0" placeholder="Search category name, description, or ID...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="statusFilter" class="form-select">
                        <option value="ALL">All Category Statuses</option>
                        <option value="Active" selected>Active Categories Only</option>
                        <option value="Inactive">Inactive Categories Only</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="sortOrder" class="form-select">
                        <option value="name_asc">Name (A to Z)</option>
                        <option value="spent_desc">Highest Spent First</option>
                        <option value="count_desc">Most Expenses Recorded</option>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <div class="btn-group w-100" role="group">
                        <button type="button" class="btn btn-view-toggle active" id="btnGridView" onclick="switchView('grid')">
                            <i class="fa-solid fa-grid-2 me-1"></i> Cards
                        </button>
                        <button type="button" class="btn btn-view-toggle" id="btnTableView" onclick="switchView('table')">
                            <i class="fa-solid fa-list me-1"></i> Table
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- GRID CARDS VIEW -->
    <div id="gridContainer" class="row g-3 mb-4">
        <?php if (empty($categories)): ?>
            <div class="col-12 text-center py-5">
                <div class="p-5 bg-white rounded-4 border">
                    <i class="fa-solid fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
                    <h5 class="fw-bold text-dark">No Expense Categories Found</h5>
                    <p class="text-muted small mb-3">Define operational expense classes like Utility Bills, Rent, or Stationery to organize vouchers.</p>
                    <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fa-solid fa-plus me-2"></i>Create First Category
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($categories as $cat): 
                $spent = (float)$cat['total_spent'];
                $pct = $totalDisbursedOverall > 0 ? ($spent / $totalDisbursedOverall) * 100 : 0;
                $statusClass = $cat['status'] === 'Active' ? 'cat-badge-active' : 'cat-badge-inactive';
                
                $catLower = strtolower($cat['name']);
                $iconClass = 'fa-folder';
                $iconBg = 'bg-primary';
                if (strpos($catLower, 'utilit') !== false || strpos($catLower, 'electric') !== false || strpos($catLower, 'bill') !== false) {
                    $iconClass = 'fa-lightbulb'; $iconBg = 'bg-warning';
                } elseif (strpos($catLower, 'rent') !== false || strpos($catLower, 'lease') !== false || strpos($catLower, 'building') !== false) {
                    $iconClass = 'fa-building-user'; $iconBg = 'bg-primary';
                } elseif (strpos($catLower, 'station') !== false || strpos($catLower, 'print') !== false || strpos($catLower, 'paper') !== false) {
                    $iconClass = 'fa-print'; $iconBg = 'bg-info';
                } elseif (strpos($catLower, 'repair') !== false || strpos($catLower, 'maint') !== false) {
                    $iconClass = 'fa-wrench'; $iconBg = 'bg-secondary';
                } elseif (strpos($catLower, 'fuel') !== false || strpos($catLower, 'vehicle') !== false || strpos($catLower, 'transport') !== false) {
                    $iconClass = 'fa-bus'; $iconBg = 'bg-success';
                } elseif (strpos($catLower, 'tea') !== false || strpos($catLower, 'welfare') !== false || strpos($catLower, 'refresh') !== false) {
                    $iconClass = 'fa-mug-hot'; $iconBg = 'bg-danger';
                } elseif (strpos($catLower, 'software') !== false || strpos($catLower, 'it') !== false || strpos($catLower, 'domain') !== false) {
                    $iconClass = 'fa-laptop-code'; $iconBg = 'bg-dark';
                }
            ?>
                <div class="col-xl-4 col-md-6 cat-item-wrapper" 
                     data-name="<?php echo strtolower(htmlspecialchars($cat['name'])); ?>"
                     data-desc="<?php echo strtolower(htmlspecialchars($cat['description'] ?? '')); ?>"
                     data-status="<?php echo htmlspecialchars($cat['status']); ?>"
                     data-spent="<?php echo $spent; ?>"
                     data-count="<?php echo $cat['expense_count']; ?>">
                    <div class="cat-card-grid h-100">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="cat-kpi-icon <?php echo $iconBg; ?> text-white shadow-sm">
                                    <i class="fa-solid <?php echo $iconClass; ?>"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark mb-0"><?php echo sanitize($cat['name']); ?></h5>
                                    <span class="text-muted small">ID: <code>#<?php echo sprintf('%03d', $cat['id']); ?></code></span>
                                </div>
                            </div>
                            <span class="cat-badge-status <?php echo $statusClass; ?>">
                                <?php echo sanitize($cat['status']); ?>
                            </span>
                        </div>

                        <p class="text-muted small mb-3 text-truncate-2" style="min-height: 38px;">
                            <?php echo sanitize($cat['description'] ?: 'No operational description attached for this category.'); ?>
                        </p>

                        <div class="bg-light p-3 rounded-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small fw-semibold">Total Disbursed:</span>
                                <span class="fw-bold text-danger">Rs. <?php echo number_format($spent, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Recorded Expenses:</span>
                                <span class="badge bg-white text-dark border px-2 py-1"><?php echo number_format($cat['expense_count']); ?> Vouchers</span>
                            </div>
                            <div class="progress progress-thin">
                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo min(100, max(4, $pct)); ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <small class="text-muted" style="font-size:0.75rem;">Budget Weight</small>
                                <small class="fw-bold text-dark" style="font-size:0.75rem;"><?php echo number_format($pct, 1); ?>% of Total</small>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between pt-2 border-top no-print">
                            <span class="text-muted small">
                                <i class="fa-regular fa-clock me-1"></i>
                                <?php echo $cat['last_expense_date'] ? date('d M Y', strtotime($cat['last_expense_date'])) : 'No expenses yet'; ?>
                            </span>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-primary btn-edit-cat px-2"
                                        data-id="<?php echo $cat['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($cat['name']); ?>"
                                        data-desc="<?php echo htmlspecialchars($cat['description'] ?? ''); ?>"
                                        data-status="<?php echo htmlspecialchars($cat['status']); ?>"
                                        title="Edit Category">
                                    <i class="fa-solid fa-pen me-1"></i> Edit
                                </button>
                                <?php if ($cat['expense_count'] == 0): ?>
                                    <button class="btn btn-sm btn-outline-danger btn-delete-cat px-2" 
                                            data-id="<?php echo $cat['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($cat['name']); ?>"
                                            title="Delete Category">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary px-2" 
                                            onclick="viewCategoryAudit('<?php echo htmlspecialchars($cat['name']); ?>', <?php echo $cat['id']; ?>)"
                                            title="View Expense Audit">
                                        <i class="fa-solid fa-receipt"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- DETAILED TABLE VIEW (Hidden by default, toggleable) -->
    <div id="tableContainer" class="custom-table-card shadow-sm mb-4 d-none">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="70">ID</th>
                        <th>Category Name</th>
                        <th>Audit Description</th>
                        <th class="text-center">Vouchers Count</th>
                        <th>Total Spend Disbursed</th>
                        <th>Share %</th>
                        <th>Status</th>
                        <th class="text-end no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No expense categories registered.</td></tr>
                    <?php else: foreach ($categories as $cat): 
                        $spent = (float)$cat['total_spent'];
                        $pct = $totalDisbursedOverall > 0 ? ($spent / $totalDisbursedOverall) * 100 : 0;
                    ?>
                        <tr class="cat-item-wrapper"
                            data-name="<?php echo strtolower(htmlspecialchars($cat['name'])); ?>"
                            data-desc="<?php echo strtolower(htmlspecialchars($cat['description'] ?? '')); ?>"
                            data-status="<?php echo htmlspecialchars($cat['status']); ?>"
                            data-spent="<?php echo $spent; ?>"
                            data-count="<?php echo $cat['expense_count']; ?>">
                            <td><code class="fw-bold">#<?php echo sprintf('%03d', $cat['id']); ?></code></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo sanitize($cat['name']); ?></div>
                                <small class="text-muted">Last entry: <?php echo $cat['last_expense_date'] ? date('d M Y', strtotime($cat['last_expense_date'])) : 'None'; ?></small>
                            </td>
                            <td class="text-muted small" style="max-width: 250px;"><?php echo sanitize($cat['description'] ?: '—'); ?></td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border px-3 py-1 fw-bold"><?php echo $cat['expense_count']; ?></span>
                            </td>
                            <td class="fw-bold text-danger">Rs. <?php echo number_format($spent, 2); ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress progress-thin flex-grow-1" style="width: 60px;">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo min(100, max(5, $pct)); ?>%"></div>
                                    </div>
                                    <span class="small fw-semibold text-muted"><?php echo number_format($pct, 1); ?>%</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo $cat['status'] === 'Active' ? 'bg-success' : 'bg-secondary'; ?> bg-opacity-10 text-<?php echo $cat['status'] === 'Active' ? 'success' : 'secondary'; ?> px-3 py-1 rounded-pill">
                                    <?php echo sanitize($cat['status']); ?>
                                </span>
                            </td>
                            <td class="text-end no-print">
                                <button class="btn btn-sm btn-outline-secondary btn-edit-cat me-1" 
                                        data-id="<?php echo $cat['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($cat['name']); ?>"
                                        data-desc="<?php echo htmlspecialchars($cat['description'] ?? ''); ?>"
                                        data-status="<?php echo htmlspecialchars($cat['status']); ?>"
                                        title="Edit Category">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <?php if ($cat['expense_count'] == 0): ?>
                                    <button class="btn btn-sm btn-outline-danger btn-delete-cat" 
                                            data-id="<?php echo $cat['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($cat['name']); ?>"
                                            title="Delete Category">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                <?php else: ?>
                                    <a href="expenses.php?category_id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Expenses">
                                        <i class="fa-solid fa-arrow-right-long"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal: Add Expense Category -->
<div class="modal fade cat-modal" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="fa-solid fa-folder-plus text-primary me-2"></i>Create Operational Expense Class
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="addCatForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_category">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Expense Class Name *</label>
                        <input type="text" class="form-control form-control-lg" name="name" id="add_cat_name" required placeholder="e.g. Utility Bills, Campus Rent, Stationery">
                        <div class="form-text small">Use standardized accounting names for clear financial audit trails.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Audit Description & Scope</label>
                        <textarea class="form-control" name="description" id="add_cat_desc" rows="3" placeholder="Provide operational guidelines on what items belong under this category..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top bg-light p-3">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addCatForm" class="btn btn-primary px-4 fw-bold" id="btnAddCat">
                    <i class="fa-solid fa-check me-2"></i>Save Category
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Edit Expense Category -->
<div class="modal fade cat-modal" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Expense Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="editCatForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Category Name *</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Audit Description</label>
                        <textarea class="form-control" name="description" id="edit_desc" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Category Status</label>
                        <select class="form-select" name="status" id="edit_status">
                            <option value="Active">Active (Accepting New Vouchers)</option>
                            <option value="Inactive">Inactive (Archived from new entries)</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top bg-light p-3">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editCatForm" class="btn btn-primary px-4 fw-bold" id="btnEditCat">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="catToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="catToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = "<?php echo csrfToken(); ?>";

function showToast(msg, ok) {
    const t = document.getElementById("catToast");
    const m = document.getElementById("catToastMsg");
    t.classList.remove("bg-success", "bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function quickFillCategory(name, desc) {
    document.getElementById("add_cat_name").value = name;
    document.getElementById("add_cat_desc").value = desc;
    new bootstrap.Modal(document.getElementById("addCategoryModal")).show();
}

function switchView(view) {
    const grid = document.getElementById("gridContainer");
    const tbl = document.getElementById("tableContainer");
    const btnGrid = document.getElementById("btnGridView");
    const btnTbl = document.getElementById("btnTableView");

    if (view === "table") {
        grid.classList.add("d-none");
        tbl.classList.remove("d-none");
        btnGrid.classList.remove("active");
        btnTbl.classList.add("active");
    } else {
        tbl.classList.add("d-none");
        grid.classList.remove("d-none");
        btnTbl.classList.remove("active");
        btnGrid.classList.add("active");
    }
}

function filterCategories() {
    const q = document.getElementById("catSearchInput").value.toLowerCase().trim();
    const status = document.getElementById("statusFilter").value;
    const wrappers = document.querySelectorAll(".cat-item-wrapper");

    wrappers.forEach(w => {
        const name = w.dataset.name || "";
        const desc = w.dataset.desc || "";
        const catStatus = w.dataset.status || "";

        const matchesQuery = !q || name.includes(q) || desc.includes(q);
        const matchesStatus = status === "ALL" || catStatus === status;

        if (matchesQuery && matchesStatus) {
            w.classList.remove("d-none");
        } else {
            w.classList.add("d-none");
        }
    });
}

function sortCategories() {
    const order = document.getElementById("sortOrder").value;
    const grid = document.getElementById("gridContainer");
    const wrappers = Array.from(grid.querySelectorAll(".cat-item-wrapper"));

    wrappers.sort((a, b) => {
        if (order === "spent_desc") {
            return parseFloat(b.dataset.spent) - parseFloat(a.dataset.spent);
        } else if (order === "count_desc") {
            return parseInt(b.dataset.count) - parseInt(a.dataset.count);
        } else {
            return (a.dataset.name || "").localeCompare(b.dataset.name || "");
        }
    });

    wrappers.forEach(w => grid.appendChild(w));
}

function exportCategoriesCSV() {
    let csv = "Category ID,Category Name,Description,Status,Expense Count,Total Spent (PKR)\n";
    const wrappers = document.querySelectorAll("#tableContainer tbody tr.cat-item-wrapper");
    
    wrappers.forEach(w => {
        const cols = w.querySelectorAll("td");
        if(cols.length >= 7) {
            const id = cols[0].innerText.trim().replace("#", "");
            const name = `"${cols[1].querySelector(".fw-bold").innerText.replace(/"/g, '""')}"`;
            const desc = `"${cols[2].innerText.replace(/"/g, '""')}"`;
            const count = cols[3].innerText.trim();
            const spent = cols[4].innerText.replace("Rs. ", "").replace(/,/g, "").trim();
            const status = cols[6].innerText.trim();
            csv += `${id},${name},${desc},${status},${count},${spent}\n`;
        }
    });

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", `Expense_Categories_Audit_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function viewCategoryAudit(name, catId) {
    window.location.href = `expenses.php?category_id=${catId}`;
}

document.addEventListener("DOMContentLoaded", function() {
    // Search & Filter Listeners
    document.getElementById("catSearchInput").addEventListener("input", filterCategories);
    document.getElementById("statusFilter").addEventListener("change", filterCategories);
    document.getElementById("sortOrder").addEventListener("change", sortCategories);

    // Add Form AJAX
    const addForm = document.getElementById("addCatForm");
    if (addForm) {
        addForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnAddCat");
            btn.disabled = true; 
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Saving...';

            fetch("../../ajax/accounts.php", { method: "POST", body: new FormData(addForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) {
                        setTimeout(() => location.reload(), 800);
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-check me-2"></i>Save Category';
                    }
                })
                .catch(() => {
                    showToast("Network connection error.", false);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check me-2"></i>Save Category';
                });
        });
    }

    // Edit Modal Binding
    document.querySelectorAll(".btn-edit-cat").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("edit_id").value = this.dataset.id;
            document.getElementById("edit_name").value = this.dataset.name;
            document.getElementById("edit_desc").value = this.dataset.desc;
            document.getElementById("edit_status").value = this.dataset.status;

            new bootstrap.Modal(document.getElementById("editCategoryModal")).show();
        });
    });

    // Edit Form AJAX
    const editForm = document.getElementById("editCatForm");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnEditCat");
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Saving...';

            fetch("../../ajax/accounts.php", { method: "POST", body: new FormData(editForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) {
                        setTimeout(() => location.reload(), 800);
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Save Changes';
                    }
                })
                .catch(() => {
                    showToast("Network connection error.", false);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Save Changes';
                });
        });
    }

    // Delete Category Event
    document.querySelectorAll(".btn-delete-cat").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            const name = this.dataset.name || "this category";
            if (!confirm(`Are you sure you want to permanently delete "${name}"?`)) return;

            const fd = new FormData();
            fd.append("action", "delete_category");
            fd.append("csrf_token", CSRF_TOKEN);
            fd.append("id", id);

            fetch("../../ajax/accounts.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 800);
                })
                .catch(() => showToast("Failed to delete category.", false));
        });
    });
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
