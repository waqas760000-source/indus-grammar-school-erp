<?php
/**
 * Indus Grammar School ERP - Cashier Collection Desk
 * Redesigned Premium UI & Full Payment Workflow
 * Version 4.0.0
 */

require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('fee_collect');

$pageTitle = 'Cashier Collection Desk';
$breadcrumbActive = 'Cashier Collection';

// Preselected student ID if passed from URL
$initialStudentId = (int)($_GET['student_id'] ?? 0);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/navbar.php';

// Fetch cashier statistics & financial metrics
$db = Database::getConnection();

// Require FeeService and trigger 1st date automatic fee generation check
require_once __DIR__ . '/../../services/FeeService.php';
$feeService = new FeeService();
$autoFeeRes = $feeService->checkAndRunAutoFee();
if (!empty($autoFeeRes['status']) && empty($autoFeeRes['already_run']) && !empty($autoFeeRes['generated_count'])) {
    $_SESSION['flash_success'] = $autoFeeRes['message'];
}

$todayDate = date('Y-m-d');
$cashierId = $_SESSION['user_id'] ?? 0;

// 1. Today's collections for cashier
$todayStatsStmt = $db->prepare("
    SELECT 
        COALESCE(SUM(amount_paid), 0) as total_today,
        COUNT(id) as receipt_count
    FROM fee_payments
    WHERE payment_date = :tdate AND collected_by = :uid
");
$todayStatsStmt->execute(['tdate' => $todayDate, 'uid' => $cashierId]);
$todayStats = $todayStatsStmt->fetch();

$todayTotal = (float)($todayStats['total_today'] ?? 0);
$todayReceipts = (int)($todayStats['receipt_count'] ?? 0);

// 2. Global school fee metrics for KPI cards
$totalOutstandingDues = 0.00;
$totalActiveStudents = 0;
try {
    $totalOutstandingDues = (float)($db->query("SELECT COALESCE(SUM(total_payable - paid_amount), 0) FROM fee_ledger WHERE status != 'Paid'")->fetchColumn() ?? 0);
    $totalActiveStudents = (int)($db->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn() ?? 0);
} catch (Exception $e) {}

// 3. Fetch recent today's payments for sidebar widget
$recentPaymentsStmt = $db->prepare("
    SELECT fp.*, fr.receipt_no, s.first_name, s.last_name, s.admission_no, fl.month
    FROM fee_payments fp
    JOIN fee_receipts fr ON fr.payment_id = fp.id
    JOIN students s ON fp.student_id = s.id
    JOIN fee_ledger fl ON fp.ledger_id = fl.id
    WHERE fp.payment_date = :tdate AND fp.collected_by = :uid
    ORDER BY fp.created_at DESC
    LIMIT 10
");
$recentPaymentsStmt->execute(['tdate' => $todayDate, 'uid' => $cashierId]);
$recentPayments = $recentPaymentsStmt->fetchAll();
?>

<!-- Custom Premium ERP Cashier Theme -->
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
    --danger: #DC2626;
    --danger-bg: #FEF2F2;
    --purple: #7C3AED;
    --purple-bg: #F5F3FF;
}

/* Page Canvas Wrapper */
.cashier-desk-wrapper {
    background-color: var(--page-background);
    border-radius: 20px;
    padding: 1.5rem;
    min-height: calc(100vh - 90px);
}

/* Hero Banner */
.adv-hero-banner {
    background: linear-gradient(135deg, var(--primary-navy) 0%, #1e3a8a 55%, var(--secondary-blue) 100%);
    border-radius: 18px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2);
    position: relative;
    overflow: hidden;
}

.adv-hero-banner::after {
    content: '';
    position: absolute;
    right: -40px;
    bottom: -40px;
    width: 260px;
    height: 260px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
    pointer-events: none;
}

.hero-icon-box {
    width: 58px;
    height: 58px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: #ffffff;
}

/* Top KPI Cards */
.top-kpi-card {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    transition: all 0.25s ease;
    height: 100%;
}

.top-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.07);
}

.kpi-icon-avatar {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}

/* Standard Styled Cards */
.card-custom {
    border: 1px solid var(--border-color);
    border-radius: 18px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    background: var(--card-white);
    transition: all 0.25s ease-in-out;
}
.card-custom:hover {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
}

.card-header-custom {
    background: transparent;
    border-bottom: 1px solid #f1f5f9;
    padding: 1.25rem 1.5rem;
}

/* Workflow Step Pill */
.step-counter {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary-navy);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    font-weight: 700;
    margin-right: 0.65rem;
    box-shadow: 0 3px 8px rgba(15, 23, 42, 0.2);
}

.step-counter-white {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #ffffff;
    color: var(--primary-navy);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    font-weight: 800;
    margin-right: 0.65rem;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
}

/* Student Profile Executive Card */
.student-profile-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
}

.student-avatar-lg {
    width: 80px;
    height: 80px;
    border-radius: 18px;
    object-fit: cover;
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.2);
    border: 2px solid #ffffff;
}

.student-avatar-placeholder-lg {
    width: 80px;
    height: 80px;
    border-radius: 18px;
    background: linear-gradient(135deg, #1e293b 0%, #3b82f6 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    font-weight: 700;
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.2);
}

/* Executive Financial KPI Cards */
.fee-kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.fee-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
}

.fee-kpi-card .kpi-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.fee-kpi-card .kpi-value {
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1.2;
    word-break: break-word;
}

/* Color variations for KPI cards */
.fee-kpi-tuition { border-top: 4px solid #3b82f6; }
.fee-kpi-tuition .kpi-value { color: #1e293b; }

.fee-kpi-arrears { border-top: 4px solid #f59e0b; background: #fffbeb; }
.fee-kpi-arrears .kpi-value { color: #d97706; }

.fee-kpi-fines { border-top: 4px solid #ef4444; background: #fef2f2; }
.fee-kpi-fines .kpi-value { color: #dc2626; }

.fee-kpi-discount { border-top: 4px solid #10b981; background: #ecfdf5; }
.fee-kpi-discount .kpi-value { color: #059669; }

.fee-kpi-due { border-top: 4px solid #6366f1; background: #eff6ff; }
.fee-kpi-due .kpi-label { color: #2563eb; }
.fee-kpi-due .kpi-value { color: #1d4ed8; font-size: 1.3rem; }

.fee-kpi-paid { border-top: 4px solid #16a34a; background: #f0fdf4; }
.fee-kpi-paid .kpi-label { color: #16a34a; }
.fee-kpi-paid .kpi-value { color: #15803d; font-size: 1.3rem; }

/* Payment Form Card Header & Controls */
.payment-form-card {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 18px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.payment-form-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    padding: 1.1rem 1.5rem;
    color: #ffffff;
}

.payment-form-card .form-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: #334155;
    margin-bottom: 6px;
}

.payment-form-card .form-control,
.payment-form-card .form-select {
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    padding: 0.65rem 0.9rem;
    font-size: 0.9rem;
    color: #0f172a;
    transition: all 0.2s ease;
}

.payment-form-card .form-control:focus,
.payment-form-card .form-select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.amount-input-group .input-group-text {
    background: #f1f5f9;
    border-color: #cbd5e1;
    font-weight: 800;
    color: #475569;
    border-radius: 10px 0 0 10px;
}

.amount-input-group .form-control {
    border-radius: 0 10px 10px 0;
    font-weight: 800;
    font-size: 1.35rem;
    color: #16a34a;
}

.btn-process-payment {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    border: none;
    color: #ffffff;
    font-weight: 700;
    padding: 0.75rem 2rem;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
    transition: all 0.25s ease;
}

.btn-process-payment:hover {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    transform: translateY(-1px);
    box-shadow: 0 8px 25px rgba(37, 99, 235, 0.45);
    color: #ffffff;
}

/* Autocomplete Search Dropdown */
.search-results-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1050;
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 12px 35px rgba(0,0,0,0.15);
    border: 1px solid var(--border-color);
    max-height: 380px;
    overflow-y: auto;
    display: none;
}
.search-result-item {
    padding: 0.9rem 1.35rem;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background 0.15s ease;
}
.search-result-item:hover {
    background: var(--light-blue);
}
.search-result-item:last-child {
    border-bottom: none;
}

/* Tables */
.table-custom-header th {
    background-color: var(--primary-navy);
    color: #f8fafc;
    font-weight: 600;
    font-size: 0.825rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.9rem 1rem;
    border: none;
}
.table-custom-body td {
    padding: 0.9rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.925rem;
}
</style>

<div class="cashier-desk-wrapper">
    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/index.php" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item text-muted">Fee Management</li>
            <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Cashier Collection Desk</li>
        </ol>
    </nav>

    <!-- Hero Header Banner -->
    <div class="adv-hero-banner mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid fa-cash-register"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold mb-0 text-white fs-3">Cashier Fee Collection Panel</h3>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-bold">Live Counter</span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">Search student, inspect fee details, validate payments, and issue official receipts.</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <a href="challan.php" class="btn btn-sm btn-light text-primary rounded-2 px-3 py-2 fw-semibold shadow-sm"><i class="fa-solid fa-calculator me-1"></i>Fee Ledger Sheets</a>
                <a href="receipts.php" class="btn btn-sm btn-outline-light rounded-2 px-3 py-2 fw-semibold"><i class="fa-solid fa-receipt me-1"></i>Receipts Log</a>
                <a href="dues.php" class="btn btn-sm btn-outline-light rounded-2 px-3 py-2 fw-semibold"><i class="fa-solid fa-clock-rotate-left me-1"></i>Defaulters List</a>
    </div>
</div>

<?php
$fSettings = [];
try {
    $fSettings = $db->query("SELECT * FROM fee_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
$autoFeeEnabled = !empty($fSettings['auto_fee_enabled']);
$autoFeeDay = (int)($fSettings['auto_fee_day'] ?? 1);
$lastAutoFeeRun = $fSettings['last_auto_fee_run'] ?? 'Not executed yet';
$currentMonthName = date('F Y');
?>

<!-- 1-Click Monthly Fee Generation for Whole School & Academy -->
<div class="card border-0 shadow-sm bg-white mb-4 rounded-3 p-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 fs-4">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <strong class="text-dark fs-6">1-Click Monthly Fee Generation (Whole School & Academy)</strong>
                    <span class="badge <?php echo $autoFeeEnabled ? 'bg-success' : 'bg-secondary'; ?> text-white px-2 py-1 rounded-pill small">
                        <?php echo $autoFeeEnabled ? "Auto-Run Scheduled: 1st Date of Every Month" : 'Manual Mode'; ?>
                    </span>
                </div>
                <p class="text-muted small mb-0">
                    Instantly generate monthly fee ledgers & vouchers for ALL active School & Academy students for <strong><?php echo $currentMonthName; ?></strong> in 1-click.
                    Last auto-run: <strong><?php echo htmlspecialchars($lastAutoFeeRun); ?></strong>
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-primary fw-bold px-3 py-2 shadow-sm text-nowrap" id="btnGenerateBulkFees1Click">
                <i class="fa-solid fa-bolt me-1"></i>Generate Fee 1-Click (<?php echo $currentMonthName; ?>)
            </button>
            <a href="challan.php" class="btn btn-sm btn-light border text-secondary px-3 py-2 text-nowrap">
                <i class="fa-solid fa-calculator me-1"></i>Fee Ledger Sheets
            </a>
        </div>
    </div>
</div>

    <!-- Top KPI Executive Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="top-kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Today's Collections</span>
                    <h3 class="fw-bold text-dark mb-0 fs-4">Rs. <?php echo number_format($todayTotal, 2); ?></h3>
                    <span class="badge bg-success-subtle text-success px-2 py-0.5 rounded-pill small mt-1 fw-semibold"><i class="fa-solid fa-arrow-trend-up me-1"></i>Shift Active</span>
                </div>
                <div class="kpi-icon-avatar bg-success-subtle text-success">
                    <i class="fa-solid fa-wallet"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="top-kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Receipts Issued Today</span>
                    <h3 class="fw-bold text-dark mb-0 fs-4"><?php echo $todayReceipts; ?> Receipts</h3>
                    <span class="badge bg-primary-subtle text-primary px-2 py-0.5 rounded-pill small mt-1 fw-semibold"><i class="fa-solid fa-receipt me-1"></i>Cashier Log</span>
                </div>
                <div class="kpi-icon-avatar bg-primary-subtle text-primary">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="top-kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Pending Outstanding Dues</span>
                    <h3 class="fw-bold text-danger mb-0 fs-4">Rs. <?php echo number_format($totalOutstandingDues, 2); ?></h3>
                    <span class="badge bg-danger-subtle text-danger px-2 py-0.5 rounded-pill small mt-1 fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Uncollected</span>
                </div>
                <div class="kpi-icon-avatar bg-danger-subtle text-danger">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="top-kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Total Active Students</span>
                    <h3 class="fw-bold text-dark mb-0 fs-4"><?php echo $totalActiveStudents; ?> Enrolled</h3>
                    <span class="badge bg-purple-subtle text-purple px-2 py-0.5 rounded-pill small mt-1 fw-semibold"><i class="fa-solid fa-users me-1"></i>Active Roster</span>
                </div>
                <div class="kpi-icon-avatar bg-purple-subtle text-purple">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Grid Layout -->
    <div class="row g-4">
        <!-- Left Side: Main Collection Workflow (8 Cols) -->
        <div class="col-lg-8">

            <!-- STEP 1: Search Student Panel -->
            <div class="card card-custom mb-4">
                <div class="card-header-custom d-flex align-items-center justify-content-between">
                    <h5 class="fw-bold text-dark mb-0 d-flex align-items-center">
                        <span class="step-counter">1</span> Search Student Record
                    </h5>
                    <span class="text-muted small">Search by Student Name, Admission No, or Roll No</span>
                </div>
                <div class="card-body p-4 position-relative">
                    <form id="studentSearchForm" autocomplete="off" onsubmit="event.preventDefault();">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <div class="input-group input-group-lg shadow-sm rounded-3">
                            <span class="input-group-text bg-white border-end-0 text-muted ps-3">
                                <i class="fa-solid fa-magnifying-glass text-primary fs-5"></i>
                            </span>
                            <input type="text" id="studentSearchInput" class="form-control border-start-0 ps-2 fs-6" placeholder="Type student name, admission number, or roll number..." value="">
                            <button type="button" id="btnClearSearch" class="btn btn-white border border-start-0 text-muted d-none">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </form>

                    <!-- Search Autocomplete Results -->
                    <div id="searchResultsDropdown" class="search-results-dropdown"></div>
                </div>
            </div>

            <!-- STEP 2 & 3: Student Details & Fee Ledger Panel -->
            <div id="studentDetailsContainer" class="d-none">
                
                <!-- Student Profile Header Card -->
                <div class="student-profile-card mb-4">
                    <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-start gap-4">
                        <div id="studentPhotoWrapper">
                            <div class="student-avatar-placeholder-lg" id="studentAvatarText">ST</div>
                        </div>
                        <div class="flex-grow-1 text-center text-sm-start">
                            <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-sm-start gap-2 mb-3">
                                <h4 class="fw-bold text-dark mb-0" id="stName">Student Name</h4>
                                <span class="badge bg-primary-subtle text-primary px-3 py-1 rounded-pill small fw-semibold" id="stAcademicType">School</span>
                                <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill small fw-semibold" id="stStatus">Active</span>
                            </div>
                            <div class="row g-3 text-muted small">
                                <div class="col-6 col-md-3">
                                    <div class="text-uppercase text-muted" style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.3px;">Admission No</div>
                                    <div id="stAdmissionNo" class="text-dark fw-bold fs-6">—</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-uppercase text-muted" style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.3px;">Roll No</div>
                                    <div id="stRollNo" class="text-dark fw-bold fs-6">—</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-uppercase text-muted" style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.3px;">Father Name</div>
                                    <div id="stFatherName" class="text-dark fw-bold fs-6">—</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-uppercase text-muted" style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.3px;">Class / Section</div>
                                    <div id="stClassSection" class="text-dark fw-bold fs-6">—</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Summary KPI Mini Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-4">
                        <div class="fee-kpi-card fee-kpi-tuition">
                            <div class="kpi-label">Monthly Tuition Fee</div>
                            <div class="kpi-value" id="kpiTuition">Rs. 0.00</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="fee-kpi-card fee-kpi-arrears">
                            <div class="kpi-label">Previous Arrears</div>
                            <div class="kpi-value" id="kpiPrevBalance">Rs. 0.00</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="fee-kpi-card fee-kpi-fines">
                            <div class="kpi-label">Late Fines</div>
                            <div class="kpi-value" id="kpiFines">Rs. 0.00</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="fee-kpi-card fee-kpi-discount">
                            <div class="kpi-label">Concessions / Discount</div>
                            <div class="kpi-value" id="kpiDiscount">Rs. 0.00</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="fee-kpi-card fee-kpi-due">
                            <div class="kpi-label">Total Outstanding Due</div>
                            <div class="kpi-value" id="kpiTotalPayable">Rs. 0.00</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="fee-kpi-card fee-kpi-paid">
                            <div class="kpi-label">Total Settled / Paid</div>
                            <div class="kpi-value" id="kpiPaid">Rs. 0.00</div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: Payment Entry Form Card -->
                <div class="payment-form-card mb-4">
                    <div class="payment-form-header d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0 text-white d-flex align-items-center">
                            <span class="step-counter-white">2</span> Collect Fee Payment
                        </h5>
                        <span class="badge bg-white-subtle text-white border border-white-subtle fw-semibold px-3 py-1 rounded-pill small">Secure Transaction</span>
                    </div>
                    <div class="card-body p-4">
                        <form id="paymentCollectionForm">
                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action" value="collect_payment">
                            <input type="hidden" id="selectedStudentId" name="student_id" value="">

                            <div class="row g-3">
                                <!-- Select Fee Month / Ledger Record -->
                                <div class="col-md-6">
                                    <label class="form-label">Select Fee Month / Challan <span class="text-danger">*</span></label>
                                    <select id="challanSelect" name="challan_id" class="form-select fw-semibold" required>
                                        <option value="">-- Select Pending Month --</option>
                                    </select>
                                </div>

                                <!-- Payment Amount -->
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label mb-0">Amount Received (Rs.) <span class="text-danger">*</span></label>
                                        <button type="button" id="btnFillExact" class="btn btn-sm btn-link text-primary p-0 text-decoration-none small fw-bold"><i class="fa-solid fa-bolt me-1"></i>Pay Month Balance</button>
                                    </div>
                                    <div class="input-group amount-input-group">
                                        <span class="input-group-text">Rs.</span>
                                        <input type="number" step="0.01" min="1" id="amountPaidInput" name="amount_paid" class="form-control" placeholder="0.00" required>
                                    </div>
                                </div>

                                <!-- Payment Date -->
                                <div class="col-md-4">
                                    <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                                    <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>

                                <!-- Payment Method -->
                                <div class="col-md-4">
                                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                    <select name="payment_method" class="form-select fw-semibold" required>
                                        <option value="Cash" selected>Cash Desk 💵</option>
                                        <option value="Bank Transfer">Bank Transfer 🏦</option>
                                        <option value="Online">Online / EasyPaisa / JazzCash 📱</option>
                                        <option value="Cheque">Cheque 📝</option>
                                    </select>
                                </div>

                                <!-- Reference Number -->
                                <div class="col-md-4">
                                    <label class="form-label">Reference / Cheque No</label>
                                    <input type="text" name="reference_number" class="form-control" placeholder="Transaction ref or cheque #">
                                </div>

                                <!-- Remarks -->
                                <div class="col-12">
                                    <label class="form-label">Remarks / Collector Notes</label>
                                    <input type="text" name="remarks" class="form-control" placeholder="e.g. Received via Cashier Desk">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end align-items-center gap-2 mt-4 pt-3 border-top">
                                <button type="button" id="btnResetForm" class="btn btn-outline-secondary px-4 fw-semibold rounded-3">Reset</button>
                                <button type="submit" id="btnSubmitPayment" class="btn btn-process-payment">
                                    <i class="fa-solid fa-circle-check me-2"></i>Process Payment & Issue Receipt
                                </button>
                            </div>
                        </form>
                    </div>
                </div>


                <!-- Month Breakdown Ledger Table (Pending Dues) -->
                <div class="card card-custom mb-4">
                    <div class="card-header-custom d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-list-check me-2 text-primary"></i>Pending Fees Breakdown</h5>
                        <span class="badge bg-danger-subtle text-danger px-3 py-1 rounded-pill fw-semibold" id="pendingCountBadge">0 Unpaid Items</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle">
                                <thead class="table-custom-header">
                                    <tr>
                                        <th>Fee Month</th>
                                        <th>Due Date</th>
                                        <th class="text-end">Monthly Fee</th>
                                        <th class="text-end">Late Fine</th>
                                        <th class="text-end">Discount</th>
                                        <th class="text-end">Paid Amt</th>
                                        <th class="text-end">Balance</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="pendingFeesTableBody" class="table-custom-body">
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">No student selected.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Payment History Log -->
                <div class="card card-custom mb-4">
                    <div class="card-header-custom d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-history me-2 text-primary"></i>Student Transaction & Payment History</h5>
                        <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill fw-semibold" id="paidCountBadge">0 Payments Recorded</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Receipt No</th>
                                        <th>Payment Date</th>
                                        <th>Month</th>
                                        <th>Method</th>
                                        <th>Ref No</th>
                                        <th class="text-end">Amount Paid</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="paymentHistoryTableBody" class="table-custom-body">
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No payment records found.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div> <!-- End Student Details Container -->

            <!-- Prompt when no student is selected -->
            <div id="noStudentPlaceholder" class="card card-custom text-center py-5">
                <div class="card-body">
                    <div class="mb-3 text-primary" style="font-size: 3.5rem;">
                        <i class="fa-solid fa-user-gear"></i>
                    </div>
                    <h4 class="fw-bold text-dark">No Student Selected</h4>
                    <p class="text-muted max-w-md mx-auto fs-6">Use the search box above to find a student by Name, Admission Number, or Roll Number to load their fee record.</p>
                </div>
            </div>

        </div> <!-- End Left 8 Cols -->

        <!-- Right Side: Today's Shift Summary Sidebar (4 Cols) -->
        <div class="col-lg-4">

            <!-- Cashier Shift KPI Widget -->
            <div class="card card-custom mb-4 text-white shadow" style="background: linear-gradient(135deg, var(--primary-navy) 0%, #1e3a8a 100%);">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-white-50 text-uppercase fw-semibold small">Today's Shift Collection</span>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-bold"><?php echo date('d M Y'); ?></span>
                    </div>
                    <h2 class="fw-bold text-white mb-2 fs-2" id="todayTotalText">Rs. <?php echo number_format($todayTotal, 2); ?></h2>
                    <p class="text-white-50 small mb-0"><i class="fa-solid fa-receipt me-1.5"></i><span id="todayReceiptsCount" class="fw-bold text-white"><?php echo $todayReceipts; ?></span> Receipts Issued Today</p>
                </div>
            </div>

            <!-- Recent Today's Payments Widget -->
            <div class="card card-custom">
                <div class="card-header-custom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Today's Recent Receipts</h6>
                    <span class="badge bg-light text-muted border">Shift Stream</span>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="recentPaymentsList">
                        <?php if (empty($recentPayments)): ?>
                            <div class="p-4 text-center text-muted small">No collections recorded today yet.</div>
                        <?php else: foreach ($recentPayments as $rp): ?>
                            <div class="list-group-item p-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-dark small"><?php echo htmlspecialchars($rp['first_name'] . ' ' . $rp['last_name']); ?></span>
                                    <span class="fw-bold text-success">Rs. <?php echo number_format($rp['amount_paid'], 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center text-muted small">
                                    <span><code class="text-primary fw-bold"><?php echo htmlspecialchars($rp['receipt_no']); ?></code></span>
                                    <span><?php echo htmlspecialchars($rp['payment_method']); ?></span>
                                    <a href="../../templates/receipt.php?receipt_no=<?php echo urlencode($rp['receipt_no']); ?>" target="_blank" class="btn btn-sm btn-light text-primary py-0 px-2" title="Print Receipt">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

        </div> <!-- End Right 4 Cols -->
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-print me-2"></i>Official Fee Payment Receipt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="receiptFrame" src="" style="width:100%; height:520px; border:none;"></iframe>
            </div>
            <div class="modal-footer bg-light border-0 py-2.5">
                <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary px-4 fw-bold" onclick="document.getElementById('receiptFrame').contentWindow.print();">
                    <i class="fa-solid fa-print me-2"></i>Print Official Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("studentSearchInput");
    const searchDropdown = document.getElementById("searchResultsDropdown");
    const clearSearchBtn = document.getElementById("btnClearSearch");
    
    const detailsContainer = document.getElementById("studentDetailsContainer");
    const noStudentPlaceholder = document.getElementById("noStudentPlaceholder");
    
    const challanSelect = document.getElementById("challanSelect");
    const amountPaidInput = document.getElementById("amountPaidInput");
    const btnFillExact = document.getElementById("btnFillExact");
    const selectedStudentId = document.getElementById("selectedStudentId");
    const paymentForm = document.getElementById("paymentCollectionForm");

    let currentStudentData = null;
    let debounceTimer = null;

    // Search Autocomplete Handler
    searchInput.addEventListener("input", function() {
        const query = this.value.trim();
        clearTimeout(debounceTimer);
        
        if (query.length > 0) {
            clearSearchBtn.classList.remove("d-none");
        } else {
            clearSearchBtn.classList.add("d-none");
            searchDropdown.style.display = "none";
            return;
        }

        if (query.length < 2) return;

        debounceTimer = setTimeout(() => {
            fetch("<?php echo APP_URL; ?>/ajax/fees.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    action: "search_student",
                    q: query,
                    csrf_token: document.querySelector('input[name="csrf_token"]')?.value || "<?php echo csrfToken(); ?>"
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.new_csrf_token) {
                    document.querySelectorAll('input[name="csrf_token"]').forEach(el => el.value = data.new_csrf_token);
                }
                if (data.success && data.students.length > 0) {
                    renderSearchResults(data.students);
                } else {
                    searchDropdown.innerHTML = `<div class="p-3 text-muted text-center small">No active students found matching "${query}"</div>`;
                    searchDropdown.style.display = "block";
                }
            })
            .catch(err => console.error("Search error:", err));
        }, 300);
    });

    clearSearchBtn.addEventListener("click", function() {
        searchInput.value = "";
        clearSearchBtn.classList.add("d-none");
        searchDropdown.style.display = "none";
    });

    function renderSearchResults(students) {
        let html = "";
        students.forEach(st => {
            const dues = parseFloat(st.dues || 0).toLocaleString("en-PK", { minimumFractionDigits: 2 });
            const dueBadge = parseFloat(st.dues) > 0 
                ? `<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 fw-semibold">Due: Rs. ${dues}</span>`
                : `<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 fw-semibold">Clear</span>`;

            html += `
                <div class="search-result-item d-flex align-items-center justify-content-between" onclick="selectStudent(${st.id})">
                    <div>
                        <div class="fw-bold text-dark">${st.first_name} ${st.last_name}</div>
                        <div class="text-muted small">
                            Adm #: <strong>${st.admission_no || '—'}</strong> | Roll #: <strong>${st.roll_no || '—'}</strong> | ${st.class_name || ''} - ${st.section || ''} (${st.academic_type || 'School'})
                        </div>
                    </div>
                    <div>${dueBadge}</div>
                </div>
            `;
        });
        searchDropdown.innerHTML = html;
        searchDropdown.style.display = "block";
    }

    // Select Student and Load Details
    window.selectStudent = function(studentId) {
        searchDropdown.style.display = "none";
        selectedStudentId.value = studentId;

        fetch("<?php echo APP_URL; ?>/ajax/fees.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "load_student_details",
                student_id: studentId,
                csrf_token: document.querySelector('input[name="csrf_token"]')?.value || "<?php echo csrfToken(); ?>"
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.new_csrf_token) {
                document.querySelectorAll('input[name="csrf_token"]').forEach(el => el.value = data.new_csrf_token);
            }
            if (data.success) {
                currentStudentData = data;
                renderStudentDetails(data);
                noStudentPlaceholder.classList.add("d-none");
                detailsContainer.classList.remove("d-none");
            } else {
                alert(data.message || "Failed to load student details.");
            }
        })
        .catch(err => console.error("Error loading student:", err));
    };

    function renderStudentDetails(data) {
        const st = data.student;
        const sum = data.summary;

        // Populate Header Info
        document.getElementById("stName").textContent = `${st.first_name} ${st.last_name}`;
        document.getElementById("stAdmissionNo").textContent = st.admission_no || "—";
        document.getElementById("stRollNo").textContent = st.roll_no || "—";
        document.getElementById("stFatherName").textContent = st.father_name || "—";
        document.getElementById("stClassSection").textContent = `${st.class_name || ''} - ${st.section || ''}`;
        document.getElementById("stAcademicType").textContent = st.academic_type || "School";
        document.getElementById("stStatus").textContent = st.status || "Active";

        // Avatar
        const photoWrap = document.getElementById("studentPhotoWrapper");
        if (st.doc_student_photo) {
            photoWrap.innerHTML = `<img src="<?php echo APP_URL; ?>/${st.doc_student_photo}" class="student-avatar-lg" alt="Student Photo">`;
        } else {
            const initials = (st.first_name.charAt(0) + st.last_name.charAt(0)).toUpperCase();
            photoWrap.innerHTML = `<div class="student-avatar-placeholder-lg">${initials}</div>`;
        }

        // Summary KPIs
        document.getElementById("kpiTuition").textContent = `Rs. ${parseFloat(sum.tuition_fee || 0).toLocaleString('en-PK', {minimumFractionDigits:2})}`;
        document.getElementById("kpiPrevBalance").textContent = `Rs. ${parseFloat(sum.previous_balance || 0).toLocaleString('en-PK', {minimumFractionDigits:2})}`;
        document.getElementById("kpiFines").textContent = `Rs. ${parseFloat(sum.fine || 0).toLocaleString('en-PK', {minimumFractionDigits:2})}`;
        document.getElementById("kpiDiscount").textContent = `Rs. ${parseFloat(sum.discount || 0).toLocaleString('en-PK', {minimumFractionDigits:2})}`;
        document.getElementById("kpiTotalPayable").textContent = `Rs. ${parseFloat(sum.remaining_balance || 0).toLocaleString('en-PK', {minimumFractionDigits:2})}`;
        document.getElementById("kpiPaid").textContent = `Rs. ${parseFloat(sum.paid_amount || 0).toLocaleString('en-PK', {minimumFractionDigits:2})}`;

        // Populate Challan Select Dropdown
        challanSelect.innerHTML = `<option value="">-- Select Pending Month --</option>`;
        if (data.pending_fees && data.pending_fees.length > 0) {
            data.pending_fees.forEach(pf => {
                const opt = document.createElement("option");
                opt.value = pf.id;
                opt.dataset.balance = pf.remaining_balance;
                const labelStatus = pf.is_upcoming ? 'Advance Option' : pf.status;
                opt.textContent = `${pf.month} — Due: Rs. ${parseFloat(pf.remaining_balance).toFixed(2)} (${labelStatus})`;
                challanSelect.appendChild(opt);
            });
            // Auto select first available pending or advance month
            challanSelect.selectedIndex = 1;
            amountPaidInput.value = parseFloat(challanSelect.options[1].dataset.balance).toFixed(2);
        } else {
            challanSelect.innerHTML = `<option value="">No Dues Available</option>`;
            amountPaidInput.value = "0.00";
        }

        // Update Pending Count Badge
        const pendingCountBadge = document.getElementById("pendingCountBadge");
        if (pendingCountBadge) {
            const pendingCount = (data.pending_fees || []).filter(f => !f.is_upcoming).length;
            pendingCountBadge.textContent = `${pendingCount} Unpaid Items`;
        }

        // Render Month Breakdown Table
        const pendingTbody = document.getElementById("pendingFeesTableBody");
        if (data.pending_fees && data.pending_fees.length > 0) {
            let html = "";
            data.pending_fees.forEach(pf => {
                let statusBadge = `<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 fw-semibold">Pending</span>`;
                if (pf.is_upcoming) {
                    statusBadge = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 fw-semibold">Advance Option</span>`;
                } else if (pf.status === 'Paid') {
                    statusBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 fw-semibold">Paid</span>`;
                } else if (pf.status === 'Partial') {
                    statusBadge = `<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2.5 py-1 fw-semibold">Partial</span>`;
                }

                html += `
                    <tr>
                        <td class="fw-bold text-dark">${pf.month}</td>
                        <td class="text-muted small">${pf.due_date || '—'}</td>
                        <td class="text-end fw-semibold">Rs. ${parseFloat(pf.monthly_fee).toFixed(2)}</td>
                        <td class="text-end text-danger fw-semibold">Rs. ${parseFloat(pf.fine).toFixed(2)}</td>
                        <td class="text-end text-success fw-semibold">Rs. ${parseFloat(pf.discount).toFixed(2)}</td>
                        <td class="text-end text-primary fw-semibold">Rs. ${parseFloat(pf.paid_amount).toFixed(2)}</td>
                        <td class="text-end fw-bold ${pf.is_upcoming ? 'text-primary' : 'text-danger'}">Rs. ${parseFloat(pf.remaining_balance).toFixed(2)}</td>
                        <td class="text-center">${statusBadge}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary py-1 px-3 rounded-pill fw-semibold shadow-sm" onclick="quickSelectMonth('${pf.id}', ${pf.remaining_balance})">
                                <i class="fa-solid fa-hand-holding-dollar me-1"></i>Collect
                            </button>
                        </td>
                    </tr>
                `;
            });
            pendingTbody.innerHTML = html;
        } else {
            pendingTbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-success fw-bold"><i class="fa-solid fa-circle-check me-2"></i>All fee records are fully paid!</td></tr>`;
        }

        // Update Paid Count Badge
        const paidCountBadge = document.getElementById("paidCountBadge");
        if (paidCountBadge) {
            paidCountBadge.textContent = `${(data.payment_history || []).length} Payments Recorded`;
        }

        // Render Payment History
        const histTbody = document.getElementById("paymentHistoryTableBody");
        if (data.payment_history && data.payment_history.length > 0) {
            let html = "";
            data.payment_history.forEach(ph => {
                let methodBadge = `<span class="badge bg-light text-dark border px-2.5 py-1">${ph.payment_method}</span>`;
                if (ph.payment_method === 'Cash') {
                    methodBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1">Cash Desk 💵</span>`;
                } else if (ph.payment_method === 'Bank Transfer') {
                    methodBadge = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1">Bank Transfer 🏦</span>`;
                } else if (ph.payment_method === 'Online') {
                    methodBadge = `<span class="badge bg-purple-subtle text-purple border border-purple-subtle px-2.5 py-1">Online 📱</span>`;
                }

                html += `
                    <tr>
                        <td><code class="fw-bold text-primary fs-6">${ph.receipt_no}</code></td>
                        <td class="small text-muted">${ph.payment_date}</td>
                        <td class="fw-semibold text-dark">${ph.month}</td>
                        <td>${methodBadge}</td>
                        <td><span class="text-muted small">${ph.reference_number || '—'}</span></td>
                        <td class="text-end fw-bold text-success">Rs. ${parseFloat(ph.amount_paid).toFixed(2)}</td>
                        <td class="text-center">
                            <a href="../../templates/receipt.php?receipt_no=${encodeURIComponent(ph.receipt_no)}" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-3 rounded-pill fw-semibold shadow-sm" title="Print Receipt">
                                <i class="fa-solid fa-print me-1"></i>Print Receipt
                            </a>
                        </td>
                    </tr>
                `;
            });
            histTbody.innerHTML = html;
        } else {
            histTbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">No past payments recorded for this student.</td></tr>`;
        }
    };

    // Quick Select Month Helper
    window.quickSelectMonth = function(id, balance) {
        challanSelect.value = id;
        amountPaidInput.value = parseFloat(balance).toFixed(2);
        challanSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    // Fill Exact Balance Button
    btnFillExact?.addEventListener("click", function() {
        const selectedOpt = challanSelect.options[challanSelect.selectedIndex];
        if (selectedOpt && selectedOpt.dataset.balance) {
            amountPaidInput.value = parseFloat(selectedOpt.dataset.balance).toFixed(2);
        }
    });

    // Month Select Change Listener -> Autofill Amount
    challanSelect.addEventListener("change", function() {
        const selectedOpt = this.options[this.selectedIndex];
        if (selectedOpt && selectedOpt.dataset.balance) {
            amountPaidInput.value = parseFloat(selectedOpt.dataset.balance).toFixed(2);
        }
    });

    // Form Submit Handler
    paymentForm.addEventListener("submit", function(e) {
        e.preventDefault();

        const challanId = challanSelect.value;
        const amt = parseFloat(amountPaidInput.value || 0);

        if (!challanId) {
            alert("Please select a pending fee month.");
            return;
        }
        if (amt <= 0) {
            alert("Please enter a valid positive payment amount.");
            return;
        }

        const btnSubmit = document.getElementById("btnSubmitPayment");
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-2"></i>Processing Payment...`;

        const formData = new FormData(paymentForm);

        fetch("<?php echo APP_URL; ?>/ajax/fees.php", {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = `<i class="fa-solid fa-check-circle me-2"></i>Process Payment & Issue Receipt`;

            if (data.new_csrf_token) {
                document.querySelectorAll('input[name="csrf_token"]').forEach(el => el.value = data.new_csrf_token);
            }

            if (data.success) {
                // Reset form fields
                amountPaidInput.value = "";
                const refInput = paymentForm.querySelector('input[name="reference_number"]');
                const remInput = paymentForm.querySelector('input[name="remarks"]');
                if (refInput) refInput.value = "";
                if (remInput) remInput.value = "";

                // Show modal with receipt
                if (data.receipt_no) {
                    const receiptUrl = `../../templates/receipt.php?receipt_no=${encodeURIComponent(data.receipt_no)}`;
                    document.getElementById("receiptFrame").src = receiptUrl;
                    const modal = new bootstrap.Modal(document.getElementById("receiptModal"));
                    modal.show();
                }

                // Refresh Student Data & Pending Months Breakdown (Moves paid month to history!)
                if (selectedStudentId.value) {
                    selectStudent(selectedStudentId.value);
                }

                // Refresh Cashier Sidebar Stats dynamically
                fetch("<?php echo APP_URL; ?>/ajax/fees.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: new URLSearchParams({
                        action: "get_cashier_dashboard",
                        csrf_token: document.querySelector('input[name="csrf_token"]')?.value || "<?php echo csrfToken(); ?>"
                    })
                })
                .then(r => r.json())
                .then(dashData => {
                    if (dashData.success && dashData.dashboard) {
                        const dbd = dashData.dashboard;
                        const todayTxt = document.getElementById("todayTotalText");
                        const todayCnt = document.getElementById("todayReceiptsCount");
                        if (todayTxt) todayTxt.textContent = `Rs. ${parseFloat(dbd.today_collections || 0).toLocaleString('en-PK', {minimumFractionDigits:2})}`;
                        if (todayCnt) todayCnt.textContent = dbd.today_receipts || 0;
                    }
                })
                .catch(e => console.error("Error updating dashboard:", e));

            } else {
                alert(data.message || "Payment collection failed.");
            }
        })
        .catch(err => {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = `<i class="fa-solid fa-check-circle me-2"></i>Process Payment & Issue Receipt`;
            console.error("Payment error:", err);
            alert("An error occurred while saving the payment transaction.");
        });
    });

    // 1-Click Bulk Monthly Fee Generation Handler
    const btnBulkGenerate = document.getElementById("btnGenerateBulkFees1Click");
    if (btnBulkGenerate) {
        btnBulkGenerate.addEventListener("click", function() {
            const currentMonthStr = "<?php echo date('F Y'); ?>";
            if (!confirm(`Are you sure you want to generate 1-Click Monthly Fees for ALL Active School & Academy Students for ${currentMonthStr}?`)) {
                return;
            }

            btnBulkGenerate.disabled = true;
            btnBulkGenerate.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-1"></i>Generating Fees...`;

            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || "<?php echo csrfToken(); ?>";

            fetch("<?php echo APP_URL; ?>/ajax/fees.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    action: "generate_bulk_monthly_fees",
                    month: currentMonthStr,
                    academic_type: "All",
                    csrf_token: csrfToken
                })
            })
            .then(res => res.json())
            .then(data => {
                btnBulkGenerate.disabled = false;
                btnBulkGenerate.innerHTML = `<i class="fa-solid fa-bolt me-1"></i>Generate Fee 1-Click (${currentMonthStr})`;

                if (data.new_csrf_token) {
                    document.querySelectorAll('input[name="csrf_token"]').forEach(el => el.value = data.new_csrf_token);
                }

                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || "Failed to generate monthly fees.");
                }
            })
            .catch(err => {
                btnBulkGenerate.disabled = false;
                btnBulkGenerate.innerHTML = `<i class="fa-solid fa-bolt me-1"></i>Generate Fee 1-Click (${currentMonthStr})`;
                console.error("Bulk fee error:", err);
                alert("An error occurred while generating monthly fees.");
            });
        });
    }

    // Auto-load student if passed via GET parameter
    <?php if ($initialStudentId > 0): ?>
        selectStudent(<?php echo $initialStudentId; ?>);
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
