<?php
/**
 * Indus Grammar School ERP - Fee Structure Submodule
 * Version 5.0.0 - Commercial ERP Redesign & Complete Setup Center
 */

// 1. App Bootstrap & Authorization
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('fee_view');

$db = Database::getConnection();
$classes = SchoolClass::all();

// Academic Sessions List
$academicSessions = [];
try {
    $academicSessions = $db->query("SELECT session_name FROM academic_sessions ORDER BY id DESC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $academicSessions = ['2026-2027', '2025-2026'];
}
$activeSession = !empty($academicSessions) ? $academicSessions[0] : (defined('CURRENT_ACADEMIC_YEAR') ? CURRENT_ACADEMIC_YEAR : date('Y'));

// Filter values
$filterType    = sanitize($_GET['filter_type'] ?? '');
$filterClass   = isset($_GET['filter_class']) ? (int)$_GET['filter_class'] : 0;
$filterSession = sanitize($_GET['filter_session'] ?? '');

$filters = [];
if ($filterType !== '') $filters['academic_type'] = $filterType;
if ($filterClass > 0) $filters['class_id'] = $filterClass;
if ($filterSession !== '') $filters['academic_year'] = $filterSession;

$structures = Fee::allStructures($filters);

// Summary Metric Counters
$totalStructures = count($structures);
$totalTuitionBase = 0.0;
$configuredClasses = [];

foreach ($structures as $st) {
    $totalTuitionBase += (float)($st['tuition_fee'] ?? 0);
    if (!empty($st['class_id'])) {
        $configuredClasses[$st['class_id']] = true;
    }
}
$classesCount = count($configuredClasses);

// Layout Header
$pageTitle = 'Fee Structure';
$breadcrumbActive = 'Fee Structure';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Scoped Advanced ERP Workspace Styles -->
<style>
/* Page Canvas Background */
.adv-page-wrapper {
    background-color: #f5f7fb;
    border-radius: 18px;
    padding: 1.5rem;
    min-height: calc(100vh - 110px);
}

/* Hero Gradient Header Banner - Exact Match to Student Registration Header */
.adv-hero-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 55%, #2563eb 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.18);
    position: relative;
    overflow: hidden;
}

.adv-hero-banner::after {
    content: '';
    position: absolute;
    right: -40px;
    bottom: -40px;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
    pointer-events: none;
}

.hero-icon-box {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.25);
}

/* Breadcrumb Styling */
.adv-breadcrumb {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 0.6rem;
}

.adv-breadcrumb a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
}

/* General Card Styling */
.adv-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 4px 14px -2px rgba(15, 23, 42, 0.04);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.adv-card-header {
    background-color: #ffffff;
    border-bottom: 1px solid #f1f5f9;
    padding: 1.15rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.adv-card-title {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
}

.adv-card-title i {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background-color: #eff6ff;
    color: #2563eb;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    font-size: 0.98rem;
}

.adv-card-body {
    padding: 1.5rem;
}

/* Summary Metric Mini Cards */
.summary-mini-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem 1.15rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.03);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    height: 100%;
}

.summary-mini-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(15, 23, 42, 0.08);
}

.summary-mini-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

/* Controls & Labels */
.adv-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 0.45rem;
}

.adv-required {
    color: #ef4444;
    font-weight: 700;
    margin-left: 2px;
}

.adv-control, .adv-select {
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    padding: 0.6rem 0.9rem;
    font-size: 0.9rem;
    color: #0f172a;
    background-color: #ffffff;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.adv-control:focus, .adv-select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

/* Total Calculation Box */
.total-calc-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-left: 4px solid #2563eb;
    border-radius: 12px;
    padding: 1rem 1.25rem;
}

/* Table Styling */
.table-attendance th {
    background-color: #0f172a;
    color: #ffffff;
    font-weight: 700;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem 1.1rem;
    border-bottom: none;
}

.table-attendance td {
    padding: 0.9rem 1.1rem;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0;
}

.table-attendance tbody tr:hover {
    background-color: #f8fafc;
}
</style>

<div class="adv-page-wrapper">
    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="adv-breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/index.php"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item"><a href="collection.php">Fee Collection</a></li>
            <li class="breadcrumb-item active" aria-current="page">Fee Structure</li>
        </ol>
    </nav>

    <!-- Hero Header Banner - Exact Match to Student Registration Header -->
    <div class="adv-hero-banner mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid fa-list-ul"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold mb-0 text-white fs-3">Fee Structure</h3>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">Structure Setup</span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">Create and manage fee structures for school and academy students.</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <a href="collection.php" class="btn btn-sm btn-light text-primary rounded-2 px-3 py-2 fw-semibold shadow-sm"><i class="fa-solid fa-money-bill-wave me-1"></i>Collect Fee</a>
                <a href="challan.php" class="btn btn-sm btn-outline-light rounded-2 px-3 py-2 fw-semibold"><i class="fa-solid fa-file-invoice me-1"></i>Fee Challan</a>
                <a href="dues.php" class="btn btn-sm btn-outline-light rounded-2 px-3 py-2 fw-semibold"><i class="fa-solid fa-clock-rotate-left me-1"></i>Pending Dues</a>
            </div>
        </div>
    </div>

    <!-- Summary KPI Mini Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="summary-mini-card">
                <div class="summary-mini-icon bg-primary-subtle text-primary">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block">Active Structures</span>
                    <h4 class="fw-bold text-dark mb-0"><?php echo number_format($totalStructures); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-mini-card">
                <div class="summary-mini-icon bg-success-subtle text-success">
                    <i class="fa-solid fa-money-bill-trend-up"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block text-success">Tuition Base</span>
                    <h4 class="fw-bold text-success mb-0">Rs. <?php echo number_format($totalTuitionBase, 0); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-mini-card">
                <div class="summary-mini-icon bg-info-subtle text-info">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block text-info">Configured Classes</span>
                    <h4 class="fw-bold text-dark mb-0"><?php echo number_format($classesCount); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-mini-card">
                <div class="summary-mini-icon bg-warning-subtle text-warning">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold d-block text-warning">Active Session</span>
                    <h4 class="fw-bold text-dark mb-0 fs-6"><?php echo htmlspecialchars($activeSession); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Workspace Row -->
    <div class="row g-4">

        <!-- Left Side: Fee Structure Setup Form Card -->
        <div class="col-lg-5">
            <div class="adv-card">
                <div class="adv-card-header">
                    <h5 class="adv-card-title">
                        <i class="fa-solid fa-circle-plus"></i><span id="formHeader">Add Fee Structure</span>
                    </h5>
                    <span class="badge bg-light text-secondary border px-2.5 py-1 small fw-semibold" id="formBadge">New Plan</span>
                </div>
                <div class="adv-card-body">
                    <form id="feeStructureForm">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action" value="save_structure">
                        <input type="hidden" name="id" id="structureId" value="">

                        <!-- Academic Details Section -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="adv-label">Academic Type <span class="adv-required">*</span></label>
                                <select class="form-select adv-select" name="academic_type" id="structType" required>
                                    <option value="School">School System</option>
                                    <option value="Academy">Academy Program</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="adv-label">Academic Session <span class="adv-required">*</span></label>
                                <select class="form-select adv-select" name="academic_year" id="structYear" required>
                                    <?php foreach ($academicSessions as $sess): ?>
                                        <option value="<?php echo htmlspecialchars($sess); ?>" <?php echo ($activeSession === $sess) ? 'selected' : ''; ?>><?php echo htmlspecialchars($sess); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="adv-label">Class <span class="adv-required">*</span></label>
                                <select class="form-select adv-select" name="class_id" id="structClass" required>
                                    <option value="">— Select Class —</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>">
                                            <?php echo sanitize($c['class_name'] . ' (' . $c['section'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <hr class="my-3 text-muted">
                        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-coins text-primary me-2"></i>Fee Component Amounts (Rs.)</h6>

                        <!-- Fee Component Amounts Grid -->
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="adv-label">Tuition Fee <span class="adv-required">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">Rs</span>
                                    <input type="number" class="form-control adv-control fw-bold text-primary fee-calc-input" name="tuition_fee" id="valTuition" min="0" value="0" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="adv-label">Admission Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">Rs</span>
                                    <input type="number" class="form-control adv-control fee-calc-input" name="admission_fee" id="valAdmission" min="0" value="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="adv-label">Annual Charges</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">Rs</span>
                                    <input type="number" class="form-control adv-control fee-calc-input" name="annual_charges" id="valAnnual" min="0" value="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="adv-label">Exam Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">Rs</span>
                                    <input type="number" class="form-control adv-control fee-calc-input" name="exam_fee" id="valExam" min="0" value="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="adv-label">Computer Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">Rs</span>
                                    <input type="number" class="form-control adv-control fee-calc-input" name="computer_fee" id="valComputer" min="0" value="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="adv-label">Transport Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">Rs</span>
                                    <input type="number" class="form-control adv-control fee-calc-input" name="transport_fee" id="valTransport" min="0" value="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="adv-label">Security Deposit</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">Rs</span>
                                    <input type="number" class="form-control adv-control fee-calc-input" name="security_deposit" id="valSecurity" min="0" value="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="adv-label">Other Charges</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">Rs</span>
                                    <input type="number" class="form-control adv-control fee-calc-input" name="other_charges" id="valOther" min="0" value="0" step="0.01">
                                </div>
                            </div>
                        </div>

                        <!-- Real-time Calculated Total Box -->
                        <div class="total-calc-box mb-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold d-block">Calculated Total Fee</span>
                                <span class="small text-muted">Sum of all entered fee heads</span>
                            </div>
                            <h4 class="fw-bold text-primary mb-0" id="displayTotalFee">Rs. 0.00</h4>
                        </div>

                        <div class="mb-4">
                            <label class="adv-label">Status</label>
                            <select class="form-select adv-select" name="status" id="structStatus">
                                <option value="Active">Active (Available for Fee Collection)</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2 border-top pt-3">
                            <button type="button" class="btn btn-outline-secondary rounded-2 px-3 py-2 fw-semibold w-50" id="btnReset">
                                <i class="fa-solid fa-rotate-left me-1"></i>Reset
                            </button>
                            <button type="submit" class="btn btn-primary rounded-2 px-4 py-2 fw-semibold w-100 shadow-sm" id="btnSave" style="background-color: #1d4ed8; border-color: #1d4ed8;">
                                <i class="fa-solid fa-check me-2"></i>Save Plan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Filter Panel & Saved Fee Structures Table -->
        <div class="col-lg-7">

            <!-- Filter Panel Card -->
            <div class="adv-card">
                <div class="adv-card-header">
                    <h5 class="adv-card-title">
                        <i class="fa-solid fa-filter"></i>Filter Saved Structures
                    </h5>
                    <?php if ($filterType !== '' || $filterClass > 0 || $filterSession !== ''): ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 small fw-semibold">Filtered</span>
                    <?php else: ?>
                        <span class="badge bg-light text-secondary border px-2 py-1 small">Filter Controls</span>
                    <?php endif; ?>
                </div>
                <div class="adv-card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4 col-sm-6">
                            <label class="adv-label">Academic Type</label>
                            <select class="form-select adv-select" name="filter_type">
                                <option value="">All Types</option>
                                <option value="School" <?php echo ($filterType === 'School') ? 'selected' : ''; ?>>School System</option>
                                <option value="Academy" <?php echo ($filterType === 'Academy') ? 'selected' : ''; ?>>Academy Program</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label class="adv-label">Class</label>
                            <select class="form-select adv-select" name="filter_class">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($filterClass == $c['id']) ? 'selected' : ''; ?>>
                                        <?php echo sanitize($c['class_name'] . ' (' . $c['section'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-12 text-end">
                            <a href="structure.php" class="btn btn-outline-secondary rounded-2 px-3 py-2 fw-semibold small me-1"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
                            <button type="submit" class="btn btn-primary rounded-2 px-3 py-2 fw-semibold small shadow-sm" style="background-color: #1d4ed8; border-color: #1d4ed8;">
                                <i class="fa-solid fa-magnifying-glass me-1"></i>Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Saved Fee Structures Data Table -->
            <div class="adv-card">
                <div class="adv-card-header">
                    <h5 class="adv-card-title">
                        <i class="fa-solid fa-list-check"></i>Configured Fee Plans
                    </h5>
                    <span class="badge bg-light text-dark border px-2.5 py-1 small fw-semibold"><?php echo count($structures); ?> Structures</span>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-attendance" id="structureTable">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Class & Section</th>
                                <th>Session</th>
                                <th>Tuition Fee</th>
                                <th>Admission</th>
                                <th>Exam</th>
                                <th>Total Fee</th>
                                <th>Status</th>
                                <?php if (hasPermission('fee_manage')): ?>
                                <th class="text-end">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($structures)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <div class="py-4">
                                            <i class="fa-solid fa-folder-open fs-1 text-secondary opacity-25 mb-3 d-block"></i>
                                            <h5 class="fw-bold text-dark">No Fee Structures Configured</h5>
                                            <p class="small text-muted mb-0">Use the form on the left to set up a new fee plan.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: foreach ($structures as $s): 
                                $totalAmount = (float)$s['tuition_fee'] + (float)$s['admission_fee'] + (float)$s['computer_fee'] 
                                             + (float)$s['exam_fee'] + (float)$s['transport_fee'] + (float)$s['annual_charges'] 
                                             + (float)$s['security_deposit'] + (float)$s['other_charges'];
                            ?>
                                <tr id="row-<?php echo $s['id']; ?>"
                                    data-id="<?php echo $s['id']; ?>"
                                    data-type="<?php echo sanitize($s['academic_type']); ?>"
                                    data-year="<?php echo sanitize($s['academic_year']); ?>"
                                    data-class="<?php echo $s['class_id']; ?>"
                                    data-admission="<?php echo $s['admission_fee']; ?>"
                                    data-tuition="<?php echo $s['tuition_fee']; ?>"
                                    data-computer="<?php echo $s['computer_fee']; ?>"
                                    data-exam="<?php echo $s['exam_fee']; ?>"
                                    data-transport="<?php echo $s['transport_fee']; ?>"
                                    data-annual="<?php echo $s['annual_charges']; ?>"
                                    data-security="<?php echo $s['security_deposit']; ?>"
                                    data-other="<?php echo $s['other_charges']; ?>"
                                    data-status="<?php echo sanitize($s['status']); ?>"
                                >
                                    <td>
                                        <span class="badge bg-<?php echo $s['academic_type'] === 'School' ? 'primary-subtle text-primary border border-primary-subtle' : 'success-subtle text-success border border-success-subtle'; ?> px-2.5 py-1 rounded-pill small fw-semibold">
                                            <?php echo sanitize($s['academic_type']); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-dark"><?php echo sanitize($s['class_name'] . ' (' . $s['section'] . ')'); ?></td>
                                    <td><span class="small font-monospace text-muted"><?php echo sanitize($s['academic_year'] ?? $activeSession); ?></span></td>
                                    <td class="fw-bold text-primary">Rs. <?php echo number_format($s['tuition_fee'], 0); ?></td>
                                    <td class="small text-muted">Rs. <?php echo number_format($s['admission_fee'], 0); ?></td>
                                    <td class="small text-muted">Rs. <?php echo number_format($s['exam_fee'], 0); ?></td>
                                    <td><strong class="text-dark">Rs. <?php echo number_format($totalAmount, 0); ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?php echo $s['status'] === 'Active' ? 'success-subtle text-success border border-success-subtle' : 'secondary-subtle text-secondary border'; ?> rounded-pill px-2.5 py-1 small fw-semibold">
                                            <?php echo sanitize($s['status']); ?>
                                        </span>
                                    </td>
                                    <?php if (hasPermission('fee_manage')): ?>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-start-2 px-2.5 btn-edit-structure" title="Edit Plan">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-end-2 px-2.5 btn-delete-structure" title="Delete Plan">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Toast Feedback Banner -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="structToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="structToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- JavaScript Interactivity -->
<script>
function showToast(msg, ok) {
    const t = document.getElementById("structToast");
    const m = document.getElementById("structToastMsg");
    if (!t || !m) return;
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

// Calculate Live Total Fee
function recalculateTotalFee() {
    let total = 0;
    document.querySelectorAll(".fee-calc-input").forEach(inp => {
        const val = parseFloat(inp.value) || 0;
        if (val > 0) total += val;
    });
    const displayEl = document.getElementById("displayTotalFee");
    if (displayEl) {
        displayEl.textContent = "Rs. " + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("feeStructureForm");
    const formHeader = document.getElementById("formHeader");
    const formBadge = document.getElementById("formBadge");
    const structureId = document.getElementById("structureId");
    const btnSave = document.getElementById("btnSave");
    const btnReset = document.getElementById("btnReset");

    // Recalculate on input change
    document.querySelectorAll(".fee-calc-input").forEach(inp => {
        inp.addEventListener("input", recalculateTotalFee);
    });
    recalculateTotalFee();

    // Form Save/Update Submit
    if (form && btnSave) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            btnSave.disabled = true;
            btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving Plan...';

            fetch("../../ajax/fees.php", {
                method: "POST",
                body: new FormData(form)
            })
            .then(r => r.json())
            .then(data => {
                showToast(data.message, data.success);
                if (data.success) {
                    setTimeout(() => location.reload(), 1000);
                } else {
                    btnSave.disabled = false;
                    btnSave.innerHTML = '<i class="fa-solid fa-check me-2"></i>Save Plan';
                }
            })
            .catch(() => {
                showToast("Network connection error.", false);
                btnSave.disabled = false;
                btnSave.innerHTML = '<i class="fa-solid fa-check me-2"></i>Save Plan';
            });
        });
    }

    // Populate Edit values
    document.querySelectorAll(".btn-edit-structure").forEach(btn => {
        btn.addEventListener("click", function() {
            const tr = this.closest("tr");

            if (formHeader) formHeader.textContent = "Edit Fee Structure";
            if (formBadge) {
                formBadge.textContent = "Update Mode";
                formBadge.className = "badge bg-warning-subtle text-warning border border-warning-subtle px-2.5 py-1 small fw-semibold";
            }
            if (structureId) structureId.value = tr.dataset.id;

            const typeEl = document.getElementById("structType");
            const yearEl = document.getElementById("structYear");
            const classEl = document.getElementById("structClass");

            if (typeEl && tr.dataset.type) typeEl.value = tr.dataset.type;
            if (yearEl && tr.dataset.year) yearEl.value = tr.dataset.year;
            if (classEl && tr.dataset.class) classEl.value = tr.dataset.class;

            document.getElementById("valAdmission").value = tr.dataset.admission || 0;
            document.getElementById("valTuition").value = tr.dataset.tuition || 0;
            document.getElementById("valComputer").value = tr.dataset.computer || 0;
            document.getElementById("valExam").value = tr.dataset.exam || 0;
            document.getElementById("valTransport").value = tr.dataset.transport || 0;
            document.getElementById("valAnnual").value = tr.dataset.annual || 0;
            document.getElementById("valSecurity").value = tr.dataset.security || 0;
            document.getElementById("valOther").value = tr.dataset.other || 0;
            document.getElementById("structStatus").value = tr.dataset.status || 'Active';

            recalculateTotalFee();

            if (btnSave) btnSave.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Update Plan';
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
    });

    // Reset Form
    btnReset?.addEventListener("click", function() {
        form.reset();
        if (formHeader) formHeader.textContent = "Add Fee Structure";
        if (formBadge) {
            formBadge.textContent = "New Plan";
            formBadge.className = "badge bg-light text-secondary border px-2.5 py-1 small fw-semibold";
        }
        if (structureId) structureId.value = "";
        if (btnSave) btnSave.innerHTML = '<i class="fa-solid fa-check me-2"></i>Save Plan';
        recalculateTotalFee();
    });

    // Delete Structure Action
    document.querySelectorAll(".btn-delete-structure").forEach(btn => {
        btn.addEventListener("click", function() {
            const tr = this.closest("tr");
            const id = tr.dataset.id;

            if (!confirm("Are you sure you want to delete this fee structure? This action cannot be undone.")) return;

            const fd = new FormData();
            fd.append("action", "delete_structure");
            fd.append("csrf_token", "<?php echo csrfToken(); ?>");
            fd.append("id", id);

            fetch("../../ajax/fees.php", {
                method: "POST",
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                showToast(data.message, data.success);
                if (data.success) {
                    tr.style.transition = "all 0.4s ease";
                    tr.style.opacity = 0;
                    setTimeout(() => tr.remove(), 400);
                }
            });
        });
    });
});
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
