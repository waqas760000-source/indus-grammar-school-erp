<?php
/**
 * Indus Grammar School ERP - Student Fee Discounts & Concessions Submodule
 * Submode of Fee Collection Module
 * Version 5.0.0
 */

require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('fee_view');

$db = Database::getConnection();
$classes = SchoolClass::all();

$pageTitle = 'Fee Discounts & Concessions Desk';
$breadcrumbActive = 'Discount Fee';

// Get URL filters
$filterClass    = isset($_GET['filter_class']) ? (int)$_GET['filter_class'] : 0;
$filterType     = sanitize($_GET['filter_type'] ?? '');
$searchQuery    = sanitize($_GET['search'] ?? '');
$initialStudent = (int)($_GET['student_id'] ?? 0);

$filters = [
    'class_id'      => $filterClass,
    'discount_type' => $filterType,
    'search'        => $searchQuery
];

// Fetch active discounted students
$discountedStudents = Fee::getDiscountedStudents($filters);

// Summary Statistics Metrics
$totalDiscounted = count($discountedStudents);
$totalMonthlyConcessions = 0.00;
$pctCount = 0;
$flatCount = 0;

foreach ($discountedStudents as $ds) {
    $tuition = (float)($ds['tuition_fee'] ?? 0);
    $pct = (float)($ds['discount_percentage'] ?? 0);
    $flat = (float)($ds['discount_flat'] ?? 0);

    if ($pct > 0) {
        $pctCount++;
        $totalMonthlyConcessions += ($tuition * $pct) / 100;
    } elseif ($flat > 0) {
        $flatCount++;
        $totalMonthlyConcessions += $flat;
    }
}

// Global Pending Ledger Concession Sum
$pendingLedgerConcessions = 0.00;
try {
    $pendingLedgerConcessions = (float)($db->query("
        SELECT COALESCE(SUM(discount_amount), 0) 
        FROM fee_ledger 
        WHERE status IN ('Pending', 'Partial') AND discount_amount > 0
    ")->fetchColumn() ?? 0);
} catch (Exception $e) {}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<!-- Custom Premium ERP Discount Desk Theme -->
<style>
:root {
    --primary-navy: #0F172A;
    --primary-blue: #1D4ED8;
    --secondary-blue: #2563EB;
    --hover-blue: #1E40AF;
    --light-blue: #EFF6FF;
    --page-background: #F8FAFC;
    --card-white: #FFFFFF;
    --border-color: #E2E8F0;
    --main-text: #1E293B;
    --muted-text: #64748B;
    --success: #16A34A;
    --success-bg: #F0FDF4;
    --warning: #D97706;
    --warning-bg: #FFFBEB;
    --purple: #7C3AED;
    --purple-bg: #F5F3FF;
}

.discount-desk-wrapper {
    background-color: var(--page-background);
    border-radius: 20px;
    padding: 1.5rem;
    min-height: calc(100vh - 90px);
}

.adv-hero-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 45%, #312e81 100%);
    border-radius: 18px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.25);
}

.adv-hero-banner::before {
    content: "";
    position: absolute;
    top: -50%;
    right: -10%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(124, 58, 237, 0.25) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.kpi-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid var(--border-color);
    padding: 1.25rem;
    transition: all 0.25s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 20px -5px rgba(0,0,0,0.08);
}

.kpi-icon-squircle {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}

.nav-tabs-custom {
    border-bottom: 2px solid #e2e8f0;
    gap: 0.5rem;
}

.nav-tabs-custom .nav-link {
    border: none;
    color: var(--muted-text);
    font-weight: 600;
    padding: 0.75rem 1.25rem;
    border-radius: 10px 10px 0 0;
    transition: all 0.2s ease;
    background: transparent;
}

.nav-tabs-custom .nav-link:hover {
    color: var(--primary-blue);
    background: #f1f5f9;
}

.nav-tabs-custom .nav-link.active {
    color: var(--primary-blue);
    background: #ffffff;
    border-bottom: 3px solid var(--primary-blue);
    box-shadow: 0 -2px 10px rgba(0,0,0,0.03);
}

.table-custom {
    border-collapse: separate;
    border-spacing: 0;
}

.table-custom th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.table-custom td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.9rem;
}

.table-custom tbody tr:hover {
    background-color: #f8fafc;
}

.student-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
    background-color: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: #475569;
}

.search-results-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1050;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    max-height: 320px;
    overflow-y: auto;
    display: none;
}

.search-result-item {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background 0.15s ease;
}

.search-result-item:hover {
    background-color: #eff6ff;
}

.badge-discount-pct {
    background-color: #dbeafe;
    color: #1e40af;
    font-weight: 700;
    padding: 0.35em 0.75em;
    border-radius: 8px;
}

.badge-discount-flat {
    background-color: #fef3c7;
    color: #92400e;
    font-weight: 700;
    padding: 0.35em 0.75em;
    border-radius: 8px;
}
</style>

<div class="container-fluid discount-desk-wrapper">
    <!-- Hero Banner -->
    <div class="adv-hero-banner mb-4">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2 opacity-75">
                        <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php" class="text-white text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/modules/fees/collection.php" class="text-white text-decoration-none">Fee Collection</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page">Discount Fee Submode</li>
                    </ol>
                </nav>
                <h2 class="fw-bold mb-1 text-white"><i class="fa-solid fa-percent me-2 text-warning"></i>Fee Discount & Concession Manager</h2>
                <p class="mb-0 text-white-50">Assign, edit, and track student fee concessions, sibling discounts, merit scholarships, and ad-hoc ledger waivers easily.</p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <button class="btn btn-warning btn-lg fw-bold rounded-3 shadow-sm px-4" onclick="switchToTab('applyDiscountTab')">
                    <i class="fa-solid fa-plus-circle me-2"></i>Apply New Discount
                </button>
            </div>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <!-- KPI 1: Total Discounted Students -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card d-flex align-items-center">
                <div class="kpi-icon-squircle bg-primary text-white me-3">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small">Discounted Students</div>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($totalDiscounted); ?></h3>
                    <small class="text-muted"><?php echo $pctCount; ?> Percentage | <?php echo $flatCount; ?> Flat rate</small>
                </div>
            </div>
        </div>

        <!-- KPI 2: Total Monthly Concessions -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card d-flex align-items-center">
                <div class="kpi-icon-squircle bg-success text-white me-3">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small">Monthly Concession Value</div>
                    <h3 class="fw-bold mb-0 text-success">Rs. <?php echo number_format($totalMonthlyConcessions, 2); ?></h3>
                    <small class="text-muted">Total recurring discount per month</small>
                </div>
            </div>
        </div>

        <!-- KPI 3: Pending Ledger Discounts -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card d-flex align-items-center">
                <div class="kpi-icon-squircle bg-warning text-white me-3">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small">Unpaid Ledger Concessions</div>
                    <h3 class="fw-bold mb-0 text-warning">Rs. <?php echo number_format($pendingLedgerConcessions, 2); ?></h3>
                    <small class="text-muted">Applied to current pending bills</small>
                </div>
            </div>
        </div>

        <!-- KPI 4: Active Class Coverage -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card d-flex align-items-center">
                <div class="kpi-icon-squircle bg-purple text-white me-3">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small">Class Categories</div>
                    <h3 class="fw-bold mb-0 text-purple"><?php echo count($classes); ?> Classes</h3>
                    <small class="text-muted">Available for discount assignment</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs nav-tabs-custom mb-4" id="discountTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="directory-tab" data-bs-toggle="tab" data-bs-target="#directoryPane" type="button" role="tab">
                <i class="fa-solid fa-list-check me-2"></i>Discounted Students Directory
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="apply-tab" data-bs-toggle="tab" data-bs-target="#applyPane" type="button" role="tab">
                <i class="fa-solid fa-user-plus me-2"></i>Assign Discount to Student
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="bulk-tab" data-bs-toggle="tab" data-bs-target="#bulkPane" type="button" role="tab">
                <i class="fa-solid fa-users-gear me-2"></i>Bulk Class Discounting
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledgerPane" type="button" role="tab">
                <i class="fa-solid fa-receipt me-2"></i>Monthly Ledger Waiver
            </button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content" id="discountTabContent">
        
        <!-- ================= TAB 1: DIRECTORY ================= -->
        <div class="tab-pane fade show active" id="directoryPane" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <!-- Filters Bar -->
                    <form method="GET" class="row g-3 mb-4 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-secondary small">Search Student</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search name, admission #, GR #...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-secondary small">Filter by Class</label>
                            <select class="form-select" name="filter_class">
                                <option value="0">All Classes & Sections</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($filterClass == $c['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['class_name'] . ($c['section'] ? ' - ' . $c['section'] : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-secondary small">Discount Type</label>
                            <select class="form-select" name="filter_type">
                                <option value="">All Discount Types</option>
                                <option value="percentage" <?php echo ($filterType === 'percentage') ? 'selected' : ''; ?>>Percentage (%) Concession</option>
                                <option value="flat" <?php echo ($filterType === 'flat') ? 'selected' : ''; ?>>Flat Rate (Rs.) Concession</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-filter me-1"></i>Filter</button>
                            <a href="discounts.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
                        </div>
                    </form>

                    <!-- Student Discounts Table -->
                    <div class="table-responsive">
                        <table class="table table-custom align-middle">
                            <thead>
                                <tr>
                                    <th>Student Details</th>
                                    <th>Class & Section</th>
                                    <th class="text-end">Base Monthly Fee</th>
                                    <th class="text-center">Concession / Discount</th>
                                    <th class="text-end">Net Monthly Fee</th>
                                    <th>Reason / Category</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($discountedStudents)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fa-solid fa-user-slash fa-3x mb-3 text-secondary opacity-50"></i>
                                                <h5 class="fw-bold text-secondary">No Discounted Students Found</h5>
                                                <p class="small mb-3">No student matches your filter criteria or has an active discount set.</p>
                                                <button class="btn btn-sm btn-primary fw-bold" onclick="switchToTab('applyDiscountTab')">
                                                    <i class="fa-solid fa-plus me-1"></i>Assign Discount Now
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($discountedStudents as $st): ?>
                                        <?php 
                                        $baseFee = (float)($st['tuition_fee'] ?? 0);
                                        $pct = (float)($st['discount_percentage'] ?? 0);
                                        $flat = (float)($st['discount_flat'] ?? 0);
                                        $discVal = 0.00;
                                        if ($pct > 0) {
                                            $discVal = ($baseFee * $pct) / 100;
                                        } elseif ($flat > 0) {
                                            $discVal = $flat;
                                        }
                                        $netFee = max(0, $baseFee - $discVal);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="student-avatar me-3">
                                                        <?php echo strtoupper(substr($st['first_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></div>
                                                        <div class="small text-muted">Adm #: <span class="fw-semibold text-primary"><?php echo htmlspecialchars($st['admission_no']); ?></span> <?php if (!empty($st['father_name'])) echo ' | S/D of ' . htmlspecialchars($st['father_name']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border px-2 py-1 fs-7">
                                                    <?php echo htmlspecialchars(($st['class_name'] ?? 'N/A') . ($st['section'] ? ' (' . $st['section'] . ')' : '')); ?>
                                                </span>
                                            </td>
                                            <td class="text-end font-monospace fw-semibold">
                                                Rs. <?php echo number_format($baseFee, 2); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($pct > 0): ?>
                                                    <span class="badge badge-discount-pct"><i class="fa-solid fa-percent me-1"></i><?php echo number_format($pct, 1); ?>% Off</span>
                                                    <div class="small text-muted mt-1">(-Rs. <?php echo number_format($discVal, 2); ?>)</div>
                                                <?php elseif ($flat > 0): ?>
                                                    <span class="badge badge-discount-flat"><i class="fa-solid fa-tag me-1"></i>Rs. <?php echo number_format($flat, 2); ?> Flat</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end font-monospace fw-bold text-success">
                                                Rs. <?php echo number_format($netFee, 2); ?>
                                            </td>
                                            <td>
                                                <span class="text-dark small fw-semibold">
                                                    <?php echo htmlspecialchars($st['discount_reason'] ?: 'Special Concession'); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" title="Edit Discount" onclick="openEditDiscountModal(<?php echo htmlspecialchars(json_encode($st)); ?>)">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <button class="btn btn-outline-success" title="Sync Pending Ledgers" onclick="syncStudentLedgers(<?php echo $st['student_id']; ?>)">
                                                        <i class="fa-solid fa-arrows-rotate"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" title="Clear Discount" onclick="confirmClearDiscount(<?php echo $st['id']; ?>, '<?php echo htmlspecialchars(addslashes($st['first_name'] . ' ' . $st['last_name'])); ?>')">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 2: ASSIGN DISCOUNT TO STUDENT ================= -->
        <div class="tab-pane fade" id="applyPane" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <h4 class="fw-bold text-dark mb-3"><i class="fa-solid fa-user-plus text-primary me-2"></i>Assign / Modify Student Fee Discount</h4>
                            <p class="text-muted small mb-4">Search a student by Name, Admission # or Roll # to configure their monthly concession or fee waiver.</p>

                            <form id="assignDiscountForm">
                                <input type="hidden" name="action" value="apply_discount">
                                <input type="hidden" name="student_id" id="assign_student_id" value="<?php echo $initialStudent; ?>">

                                <!-- Live Autocomplete Search -->
                                <div class="mb-4 position-relative">
                                    <label class="form-label fw-bold text-dark">Search Student <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-primary"></i></span>
                                        <input type="text" class="form-control border-start-0" id="student_search_input" autocomplete="off" placeholder="Type student name, admission # or roll #...">
                                    </div>
                                    <div id="search_results_container" class="search-results-dropdown"></div>
                                </div>

                                <!-- Selected Student Summary Box -->
                                <div id="selected_student_box" class="p-3 mb-4 rounded-3 bg-light border" style="display: none;">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <div class="student-avatar bg-primary text-white me-3" id="sel_avatar">S</div>
                                            <div>
                                                <h6 class="fw-bold mb-0 text-dark" id="sel_name">Student Name</h6>
                                                <small class="text-muted">Adm #: <span class="fw-semibold text-primary" id="sel_adm">0000</span> | Class: <span class="fw-semibold" id="sel_class">N/A</span></small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="small text-muted">Standard Monthly Fee</div>
                                            <div class="fw-bold text-dark fs-5" id="sel_base_fee">Rs. 0.00</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Discount Type Selector -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark">Discount Type <span class="text-danger">*</span></label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="discount_type_mode" id="dtype_pct" value="percentage" checked onclick="toggleDiscountTypeInputs()">
                                        <label class="btn btn-outline-primary py-2 fw-bold" for="dtype_pct">
                                            <i class="fa-solid fa-percent me-2"></i>Percentage (%) Discount
                                        </label>

                                        <input type="radio" class="btn-check" name="discount_type_mode" id="dtype_flat" value="flat" onclick="toggleDiscountTypeInputs()">
                                        <label class="btn btn-outline-warning py-2 fw-bold" for="dtype_flat">
                                            <i class="fa-solid fa-tag me-2"></i>Flat Amount (Rs.) Discount
                                        </label>
                                    </div>
                                </div>

                                <!-- Input Fields for Percentage & Flat -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6" id="input_box_pct">
                                        <label class="form-label fw-bold text-dark">Discount Percentage (%)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" max="100" class="form-control form-control-lg" name="percentage" id="input_percentage" placeholder="e.g. 25" oninput="recalculateDiscountPreview()">
                                            <span class="input-group-text fw-bold">%</span>
                                        </div>
                                        <small class="text-muted">e.g. 50% for half fee waiver, 100% for full scholarship.</small>
                                    </div>

                                    <div class="col-md-6" id="input_box_flat" style="display: none;">
                                        <label class="form-label fw-bold text-dark">Flat Discount Amount (Rs.)</label>
                                        <div class="input-group">
                                            <span class="input-group-text fw-bold">Rs.</span>
                                            <input type="number" step="0.01" min="0" class="form-control form-control-lg" name="flat_amount" id="input_flat_amount" placeholder="e.g. 1000" oninput="recalculateDiscountPreview()">
                                        </div>
                                        <small class="text-muted">Fixed amount subtracted from monthly tuition fee.</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-dark">Concession Category / Preset</label>
                                        <select class="form-select form-select-lg" id="preset_reason" onchange="selectPresetReason(this.value)">
                                            <option value="">-- Select Category --</option>
                                            <option value="Sibling Concession">Sibling Concession</option>
                                            <option value="Need-Based Financial Aid">Need-Based Financial Aid</option>
                                            <option value="Merit Scholarship">Merit Scholarship</option>
                                            <option value="Staff Child Waiver">Staff Child Waiver</option>
                                            <option value="Orphan / Special Concession">Orphan / Special Concession</option>
                                            <option value="Management Special Waiver">Management Special Waiver</option>
                                            <option value="Custom">Other Custom Reason</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Reason Description -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark">Concession Remarks / Justification</label>
                                    <textarea class="form-control" name="reason" id="input_reason" rows="2" placeholder="e.g. 2nd child sibling discount approved by principal..."></textarea>
                                </div>

                                <!-- Checkbox to sync existing unpaid ledgers -->
                                <div class="form-check form-switch mb-4 p-3 bg-light rounded-3 border ms-0">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="sync_ledgers" id="chk_sync_ledgers" value="1" checked style="width: 2.5em; height: 1.25em;">
                                    <label class="form-check-input-label fw-bold text-dark" for="chk_sync_ledgers">
                                        Recalculate & Sync Unpaid Fee Bills for Current Academic Year
                                        <div class="small text-muted fw-normal">Automatically updates pending/partial monthly fee ledgers with the new discount.</div>
                                    </label>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm" id="btn_save_discount">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>Save & Apply Concession
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Live Fee Preview Calculation Card -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 bg-gradient-navy text-white" style="background: linear-gradient(145deg, #0f172a 0%, #1e293b 100%);">
                        <div class="card-body p-4 text-white">
                            <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-calculator text-warning me-2"></i>Live Fee Concession Calculator</h5>
                            <p class="text-white-50 small mb-4">Calculated net monthly tuition fee breakdown for selected student.</p>

                            <div class="p-3 bg-white bg-opacity-10 rounded-3 mb-3 border border-white border-opacity-10">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-white-50">Standard Base Tuition:</span>
                                    <span class="fw-bold font-monospace" id="calc_base_fee">Rs. 0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2 text-warning">
                                    <span>Concession Applied:</span>
                                    <span class="fw-bold font-monospace" id="calc_discount_amt">- Rs. 0.00</span>
                                </div>
                                <hr class="border-white border-opacity-25 my-2">
                                <div class="d-flex justify-content-between text-success">
                                    <span class="fw-bold fs-6">Net Monthly Tuition Fee:</span>
                                    <span class="fw-bold fs-5 font-monospace" id="calc_net_fee">Rs. 0.00</span>
                                </div>
                            </div>

                            <div class="alert alert-info border-0 text-white bg-primary bg-opacity-25 small mb-0">
                                <i class="fa-solid fa-info-circle me-2"></i>
                                <strong>Note:</strong> Applying a concession updates the student's recurring fee profile. Future monthly fee generation will automatically incorporate this discount.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 3: BULK CLASS DISCOUNTING ================= -->
        <div class="tab-pane fade" id="bulkPane" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="max-w-700 mx-auto" style="max-width: 800px;">
                        <h4 class="fw-bold text-dark mb-2"><i class="fa-solid fa-users-gear text-purple me-2"></i>Bulk Class Discount Manager</h4>
                        <p class="text-muted small mb-4">Apply a uniform discount or concession rate to all active students in a specific class or section at once.</p>

                        <form id="bulkDiscountForm">
                            <input type="hidden" name="action" value="bulk_apply_discount">

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Target Class <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-lg" name="class_id" required>
                                        <option value="">-- Select Class --</option>
                                        <?php foreach ($classes as $c): ?>
                                            <option value="<?php echo $c['id']; ?>">
                                                <?php echo htmlspecialchars($c['class_name'] . ($c['section'] ? ' - ' . $c['section'] : '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Concession Category</label>
                                    <input type="text" class="form-control form-control-lg" name="reason" placeholder="e.g. Class Batch Concession 2026..." required>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Percentage Discount (%)</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" max="100" class="form-control form-control-lg" name="percentage" placeholder="e.g. 15">
                                        <span class="input-group-text fw-bold">%</span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">OR Flat Discount (Rs.)</label>
                                    <div class="input-group">
                                        <span class="input-group-text fw-bold">Rs.</span>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-lg" name="flat_amount" placeholder="e.g. 500">
                                    </div>
                                </div>
                            </div>

                            <div class="form-check form-switch mb-4 p-3 bg-light rounded-3 border ms-0">
                                <input class="form-check-input ms-0 me-3" type="checkbox" name="sync_ledgers" id="chk_bulk_sync" value="1" checked style="width: 2.5em; height: 1.25em;">
                                <label class="form-check-input-label fw-bold text-dark" for="chk_bulk_sync">
                                    Sync and Update All Unpaid Pending Bills for Selected Class
                                </label>
                            </div>

                            <button type="submit" class="btn btn-purple btn-lg w-100 fw-bold shadow-sm text-white" style="background-color: var(--purple);" id="btn_save_bulk_discount">
                                <i class="fa-solid fa-bolt me-2"></i>Apply Bulk Concession to Class
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 4: MONTHLY LEDGER AD-HOC WAIVER ================= -->
        <div class="tab-pane fade" id="ledgerPane" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold text-dark mb-2"><i class="fa-solid fa-receipt text-warning me-2"></i>Single Month Fee Ledger Discounting</h4>
                    <p class="text-muted small mb-4">Search a student to view their unpaid monthly bills and adjust discount directly on a specific month.</p>

                    <!-- Student Search Bar for Ledger -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-8 position-relative">
                            <label class="form-label fw-bold text-dark">Search Student for Ledger Adjustment</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-warning"></i></span>
                                <input type="text" class="form-control" id="ledger_student_search" autocomplete="off" placeholder="Search student name or admission #...">
                            </div>
                            <div id="ledger_search_results" class="search-results-dropdown"></div>
                        </div>
                    </div>

                    <!-- Ledger Table Container -->
                    <div id="student_ledger_container" style="display: none;">
                        <div class="p-3 bg-light rounded-3 mb-4 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark" id="ledger_st_name">Student Name</h6>
                                <small class="text-muted">Adm #: <span class="fw-semibold text-primary" id="ledger_st_adm">0000</span> | Class: <span id="ledger_st_class">Class</span></small>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-custom align-middle">
                                <thead>
                                    <tr>
                                        <th>Month / Academic Year</th>
                                        <th class="text-end">Tuition Fee</th>
                                        <th class="text-end">Other Charges</th>
                                        <th class="text-end">Late Fine</th>
                                        <th class="text-end text-warning">Current Discount</th>
                                        <th class="text-end text-success">Net Total Payable</th>
                                        <th class="text-center">Bill Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="ledger_rows_body">
                                    <!-- Dynamic Rows Loaded via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal: Edit Student Assignment Discount -->
<div class="modal fade" id="editDiscountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-header-title fw-bold mb-0 text-white"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Student Concession</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editDiscountModalForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="apply_discount">
                    <input type="hidden" name="student_id" id="edit_student_id">

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">Student Name</label>
                        <input type="text" class="form-control bg-light" id="edit_student_name" readonly>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Discount (%)</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control" name="percentage" id="edit_percentage">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Flat Amount (Rs.)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="flat_amount" id="edit_flat_amount">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">Concession Reason / Category</label>
                        <input type="text" class="form-control" name="reason" id="edit_reason" placeholder="e.g. Sibling concession...">
                    </div>

                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="sync_ledgers" id="edit_sync_ledgers" value="1" checked>
                        <label class="form-check-label fw-semibold text-dark" for="edit_sync_ledgers">
                            Sync changes with existing pending fee ledgers
                        </label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Update Concession</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Adjust Single Ledger Discount -->
<div class="modal fade" id="adjustLedgerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-warning text-dark border-0 py-3">
                <h5 class="modal-header-title fw-bold mb-0 text-dark"><i class="fa-solid fa-receipt me-2"></i>Adjust Single Month Bill Discount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="adjustLedgerModalForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="update_ledger_discount">
                    <input type="hidden" name="ledger_id" id="adj_ledger_id">

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">Month / Academic Year</label>
                        <input type="text" class="form-control bg-light" id="adj_month_name" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">Direct Discount Amount (Rs.)</label>
                        <div class="input-group">
                            <span class="input-group-text fw-bold">Rs.</span>
                            <input type="number" step="0.01" min="0" class="form-control form-control-lg" name="discount_amount" id="adj_discount_amount" required>
                        </div>
                        <small class="text-muted">This specific discount will be subtracted from this month's total bill.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning px-4 fw-bold">Update Month Bill</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
let activeStudentData = null;

function switchToTab(tabId) {
    const tabEl = document.querySelector(`button[data-bs-target="#${tabId.replace('Tab', 'Pane')}"]`);
    if (tabEl) {
        const tab = new bootstrap.Tab(tabEl);
        tab.show();
    }
}

// Live Autocomplete for Assign Discount Tab
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('student_search_input');
    const resultsContainer = document.getElementById('search_results_container');

    if (searchInput) {
        let debounceTimeout = null;
        searchInput.addEventListener('keyup', function() {
            clearTimeout(debounceTimeout);
            const query = this.value.trim();
            if (query.length < 2) {
                resultsContainer.style.display = 'none';
                return;
            }

            debounceTimeout = setTimeout(() => {
                fetchStudentSearchResults(query, resultsContainer, selectStudentForDiscount);
            }, 300);
        });
    }

    // Ledger Tab Live Search
    const ledgerSearchInput = document.getElementById('ledger_student_search');
    const ledgerResultsContainer = document.getElementById('ledger_search_results');

    if (ledgerSearchInput) {
        let debounceTimeout = null;
        ledgerSearchInput.addEventListener('keyup', function() {
            clearTimeout(debounceTimeout);
            const query = this.value.trim();
            if (query.length < 2) {
                ledgerResultsContainer.style.display = 'none';
                return;
            }

            debounceTimeout = setTimeout(() => {
                fetchStudentSearchResults(query, ledgerResultsContainer, loadStudentLedgerForDiscount);
            }, 300);
        });
    }

    // Preselected student from URL if any
    const initId = <?php echo $initialStudent; ?>;
    if (initId > 0) {
        switchToTab('applyDiscountTab');
        loadStudentDiscountInfo(initId);
    }
});

function fetchStudentSearchResults(query, container, selectCallback) {
    const formData = new FormData();
    formData.append('action', 'search_student');
    formData.append('q', query);

    fetch('../../ajax/fees.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.students && data.students.length > 0) {
            let html = '';
            data.students.forEach(st => {
                html += `
                    <div class="search-result-item" onclick='${selectCallback.name}(${JSON.stringify(st)})'>
                        <div class="fw-bold text-dark">${st.first_name} ${st.last_name}</div>
                        <div class="small text-muted">Adm #: <span class="text-primary font-monospace">${st.admission_no}</span> | Class: ${st.class_name || 'N/A'} ${st.section || ''} ${st.father_name ? '| Father: ' + st.father_name : ''}</div>
                    </div>
                `;
            });
            container.innerHTML = html;
            container.style.display = 'block';
        } else {
            container.innerHTML = '<div class="p-3 text-center text-muted small">No active students found.</div>';
            container.style.display = 'block';
        }
    });
}

function selectStudentForDiscount(st) {
    document.getElementById('search_results_container').style.display = 'none';
    document.getElementById('student_search_input').value = `${st.first_name} ${st.last_name} (${st.admission_no})`;
    document.getElementById('assign_student_id').value = st.id;

    loadStudentDiscountInfo(st.id);
}

function loadStudentDiscountInfo(sid) {
    const formData = new FormData();
    formData.append('action', 'get_student_discount_info');
    formData.append('student_id', sid);

    fetch('../../ajax/fees.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.student) {
            const st = data.student;
            activeStudentData = st;

            document.getElementById('selected_student_box').style.display = 'block';
            document.getElementById('sel_name').innerText = `${st.first_name} ${st.last_name}`;
            document.getElementById('sel_adm').innerText = st.admission_no;
            document.getElementById('sel_class').innerText = `${st.class_name || 'N/A'} ${st.section || ''}`;
            document.getElementById('sel_avatar').innerText = (st.first_name || 'S').charAt(0).toUpperCase();

            const baseFee = parseFloat(st.tuition_fee || 0);
            document.getElementById('sel_base_fee').innerText = `Rs. ${baseFee.toFixed(2)}`;

            // Populate existing discount values if any
            const pct = parseFloat(st.discount_percentage || 0);
            const flat = parseFloat(st.discount_flat || 0);

            if (pct > 0) {
                document.getElementById('dtype_pct').checked = true;
                toggleDiscountTypeInputs();
                document.getElementById('input_percentage').value = pct;
                document.getElementById('input_flat_amount').value = '';
            } else if (flat > 0) {
                document.getElementById('dtype_flat').checked = true;
                toggleDiscountTypeInputs();
                document.getElementById('input_flat_amount').value = flat;
                document.getElementById('input_percentage').value = '';
            } else {
                document.getElementById('input_percentage').value = '';
                document.getElementById('input_flat_amount').value = '';
            }

            document.getElementById('input_reason').value = st.discount_reason || '';
            recalculateDiscountPreview();
        }
    });
}

function toggleDiscountTypeInputs() {
    const isPct = document.getElementById('dtype_pct').checked;
    document.getElementById('input_box_pct').style.display = isPct ? 'block' : 'none';
    document.getElementById('input_box_flat').style.display = isPct ? 'none' : 'block';
    recalculateDiscountPreview();
}

function selectPresetReason(val) {
    if (val && val !== 'Custom') {
        document.getElementById('input_reason').value = val;
    }
}

function recalculateDiscountPreview() {
    if (!activeStudentData) {
        document.getElementById('calc_base_fee').innerText = 'Rs. 0.00';
        document.getElementById('calc_discount_amt').innerText = '- Rs. 0.00';
        document.getElementById('calc_net_fee').innerText = 'Rs. 0.00';
        return;
    }

    const baseFee = parseFloat(activeStudentData.tuition_fee || 0);
    const isPct = document.getElementById('dtype_pct').checked;
    let discVal = 0.00;

    if (isPct) {
        const pct = parseFloat(document.getElementById('input_percentage').value || 0);
        discVal = (baseFee * pct) / 100;
    } else {
        discVal = parseFloat(document.getElementById('input_flat_amount').value || 0);
    }

    const netFee = Math.max(0, baseFee - discVal);

    document.getElementById('calc_base_fee').innerText = `Rs. ${baseFee.toFixed(2)}`;
    document.getElementById('calc_discount_amt').innerText = `- Rs. ${discVal.toFixed(2)}`;
    document.getElementById('calc_net_fee').innerText = `Rs. ${netFee.toFixed(2)}`;
}

// Save Single Student Discount Form Submit
document.getElementById('assignDiscountForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const sid = parseInt(document.getElementById('assign_student_id').value || 0);
    if (sid <= 0) {
        alert('Please search and select a valid student first.');
        return;
    }

    const formData = new FormData(this);
    const btn = document.getElementById('btn_save_discount');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Saving...';

    fetch('../../ajax/fees.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Save & Apply Concession';
        alert(data.message);
        if (data.success) {
            window.location.reload();
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Save & Apply Concession';
    });
});

// Save Bulk Class Discount Form Submit
document.getElementById('bulkDiscountForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (!confirm('Are you sure you want to apply this discount to all students in the selected class?')) {
        return;
    }

    const formData = new FormData(this);
    const btn = document.getElementById('btn_save_bulk_discount');
    btn.disabled = true;

    fetch('../../ajax/fees.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        alert(data.message);
        if (data.success) {
            window.location.reload();
        }
    })
    .catch(() => {
        btn.disabled = false;
    });
});

// Edit Modal Handler
function openEditDiscountModal(st) {
    document.getElementById('edit_student_id').value = st.student_id;
    document.getElementById('edit_student_name').value = `${st.first_name} ${st.last_name} (${st.admission_no})`;
    document.getElementById('edit_percentage').value = parseFloat(st.discount_percentage || 0);
    document.getElementById('edit_flat_amount').value = parseFloat(st.discount_flat || 0);
    document.getElementById('edit_reason').value = st.discount_reason || '';

    const modal = new bootstrap.Modal(document.getElementById('editDiscountModal'));
    modal.show();
}

document.getElementById('editDiscountModalForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('../../ajax/fees.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            window.location.reload();
        }
    });
});

// Sync Student Pending Ledgers Directly
function syncStudentLedgers(sid) {
    const formData = new FormData();
    formData.append('action', 'apply_discount');
    formData.append('student_id', sid);
    formData.append('sync_ledgers', '1');

    fetch('../../ajax/fees.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        alert('Student pending fee bills synced successfully.');
        window.location.reload();
    });
}

// Clear Discount Handler
function confirmClearDiscount(asgnId, stName) {
    if (confirm(`Are you sure you want to remove the discount for ${stName}?`)) {
        const formData = new FormData();
        formData.append('action', 'delete_discount');
        formData.append('id', asgnId);
        formData.append('sync_ledgers', '1');

        fetch('../../ajax/fees.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                window.location.reload();
            }
        });
    }
}

// Ledger Tab Loader
function loadStudentLedgerForDiscount(st) {
    document.getElementById('ledger_search_results').style.display = 'none';
    document.getElementById('student_ledger_container').style.display = 'block';
    document.getElementById('ledger_st_name').innerText = `${st.first_name} ${st.last_name}`;
    document.getElementById('ledger_st_adm').innerText = st.admission_no;
    document.getElementById('ledger_st_class').innerText = `${st.class_name || 'N/A'} ${st.section || ''}`;

    const formData = new FormData();
    formData.append('action', 'get_student_discount_info');
    formData.append('student_id', st.id);

    fetch('../../ajax/fees.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        const tbody = document.getElementById('ledger_rows_body');
        if (data.success && data.ledgers && data.ledgers.length > 0) {
            let html = '';
            data.ledgers.forEach(l => {
                const tuition = parseFloat(l.tuition_fee || 0);
                const other = parseFloat(l.admission_fee || 0) + parseFloat(l.computer_fee || 0) + parseFloat(l.exam_fee || 0) + parseFloat(l.transport_fee || 0) + parseFloat(l.annual_charges || 0) + parseFloat(l.other_charges || 0);
                const fine = parseFloat(l.fine_amount || 0);
                const disc = parseFloat(l.discount_amount || 0);
                const payable = parseFloat(l.total_payable || 0);

                html += `
                    <tr>
                        <td class="fw-bold">${l.month} (${l.academic_year})</td>
                        <td class="text-end font-monospace">Rs. ${tuition.toFixed(2)}</td>
                        <td class="text-end font-monospace">Rs. ${other.toFixed(2)}</td>
                        <td class="text-end font-monospace text-danger">Rs. ${fine.toFixed(2)}</td>
                        <td class="text-end font-monospace fw-bold text-warning">Rs. ${disc.toFixed(2)}</td>
                        <td class="text-end font-monospace fw-bold text-success">Rs. ${payable.toFixed(2)}</td>
                        <td class="text-center">
                            <span class="badge ${l.status === 'Paid' ? 'bg-success' : (l.status === 'Partial' ? 'bg-warning' : 'bg-danger')}">${l.status}</span>
                        </td>
                        <td class="text-center">
                            ${l.status !== 'Paid' ? `<button class="btn btn-sm btn-outline-warning fw-bold" onclick="openAdjustLedgerModal(${l.id}, '${l.month} (${l.academic_year})', ${disc})"><i class="fa-solid fa-pen me-1"></i>Adjust Discount</button>` : '<span class="text-muted small">Paid</span>'}
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No fee ledger bills found for this student.</td></tr>';
        }
    });
}

function openAdjustLedgerModal(lid, monthName, currentDisc) {
    document.getElementById('adj_ledger_id').value = lid;
    document.getElementById('adj_month_name').value = monthName;
    document.getElementById('adj_discount_amount').value = currentDisc;

    const modal = new bootstrap.Modal(document.getElementById('adjustLedgerModal'));
    modal.show();
}

document.getElementById('adjustLedgerModalForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('../../ajax/fees.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            window.location.reload();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
