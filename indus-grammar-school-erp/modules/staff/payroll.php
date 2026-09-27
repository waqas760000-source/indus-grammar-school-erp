<?php
/**
 * Indus Grammar School ERP - School Payroll Dashboard
 * Version 4.0.0 (Premium Redesign)
 */

$pageTitle = 'School Payroll Dashboard';
$breadcrumbActive = 'HR & Staff';
include_once __DIR__ . '/../../includes/header.php';

// Access Control
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'accountant'])) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to access the payroll dashboard.';
    redirect(APP_URL . '/dashboard.php');
}

$db = Database::getConnection();

// Include PayrollService & trigger 10th date automatic salary issuance check
require_once __DIR__ . '/../../services/PayrollService.php';
$autoRunRes = PayrollService::checkAndRunAutoPayroll();
if (!empty($autoRunRes['success']) && empty($autoRunRes['already_run']) && !empty($autoRunRes['count'])) {
    $_SESSION['flash_success'] = $autoRunRes['message'];
}

// Selected cycle parameters
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$monthText = date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));

// ── 1. Calculate Executive Payroll Metrics ──
// Active Staff Count & Base Gross Payroll
$staffStats = $db->query("
    SELECT COUNT(*) as active_count, COALESCE(SUM(salary), 0) as total_base_gross 
    FROM staff 
    WHERE status = 'Active'
")->fetch(PDO::FETCH_ASSOC);

$activeStaffCount = (int)$staffStats['active_count'];
$totalBaseGross   = (float)$staffStats['total_base_gross'];

// Fetch processing run for selected billing cycle
$procStmt = $db->prepare("SELECT id, status FROM salary_processing WHERE month = :m AND year = :y LIMIT 1");
$procStmt->execute(['m' => $selectedMonth, 'y' => $selectedYear]);
$procRun = $procStmt->fetch(PDO::FETCH_ASSOC);
$procId = $procRun ? (int)$procRun['id'] : 0;

// Metric accumulators
$processedCount  = 0;
$pendingCount    = $activeStaffCount;
$totalNetPayable = 0.00;
$totalDisbursed  = 0.00;
$totalPending    = 0.00;
$totalAllowances = 0.00;
$totalDeductions = 0.00;
$cashPostingSum  = 0.00;
$bankPostingSum  = 0.00;

if ($procId > 0) {
    $processedCount = (int)$db->query("SELECT COUNT(*) FROM salary_details WHERE processing_id = $procId")->fetchColumn();
    $pendingCount   = max(0, $activeStaffCount - $processedCount);
    
    $sums = $db->query("
        SELECT 
            COALESCE(SUM(net_salary), 0) as net_sum,
            COALESCE(SUM(allowances), 0) as allow_sum,
            COALESCE(SUM(deductions + advance_salary_deduction), 0) as ded_sum,
            COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN net_salary ELSE 0 END), 0) as disbursed_sum,
            COALESCE(SUM(CASE WHEN payment_status != 'Paid' THEN net_salary ELSE 0 END), 0) as pending_disb_sum,
            COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND payment_method = 'Cash' THEN net_salary ELSE 0 END), 0) as cash_sum,
            COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND payment_method = 'Bank' THEN net_salary ELSE 0 END), 0) as bank_sum
        FROM salary_details 
        WHERE processing_id = $procId
    ")->fetch(PDO::FETCH_ASSOC);

    $totalNetPayable = (float)$sums['net_sum'];
    $totalAllowances = (float)$sums['allow_sum'];
    $totalDeductions = (float)$sums['ded_sum'];
    $totalDisbursed  = (float)$sums['disbursed_sum'];
    $totalPending    = (float)$sums['pending_disb_sum'];
    $cashPostingSum  = (float)$sums['cash_sum'];
    $bankPostingSum  = (float)$sums['bank_sum'];
}

// Outstanding advance loan balance across all staff
$outstandingAdvance = (float)$db->query("SELECT COALESCE(SUM(remaining_balance), 0) FROM advance_salary WHERE status = 'Pending'")->fetchColumn();

// Estimate shift attendance deductions for selected cycle (absences & late arrivals)
$attendanceDeductionEst = (float)$db->query("
    SELECT COALESCE(SUM(deductions), 0) 
    FROM salary_details 
    WHERE processing_id = $procId
")->fetchColumn();

// Historical processing runs list
$runs = $db->query("
    SELECT p.*, 
           (SELECT COUNT(*) FROM salary_details WHERE processing_id = p.id) as total_staff,
           (SELECT SUM(net_salary) FROM salary_details WHERE processing_id = p.id) as total_net,
           (SELECT COUNT(*) FROM salary_details WHERE processing_id = p.id AND payment_status = 'Paid') as paid_count,
           (SELECT SUM(net_salary) FROM salary_details WHERE processing_id = p.id AND payment_status = 'Paid') as paid_amount
    FROM salary_processing p
    ORDER BY p.year DESC, p.month DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.payroll-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.75rem;
    position: relative;
    overflow: hidden;
}
.payroll-hero-card::after {
    content: "";
    position: absolute;
    bottom: -30%;
    right: -5%;
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.18) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.kpi-card-gradient {
    border-radius: 14px;
    border: 1px solid rgba(0,0,0,0.06);
    background: #ffffff;
    transition: all 0.25s ease;
}
.kpi-card-gradient:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08)!important;
}

.submodule-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.25rem;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    display: block;
    height: 100%;
}
.submodule-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.08);
    border-color: #cbd5e1;
}

.submodule-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}

.progress-thin {
    height: 8px;
    border-radius: 4px;
    background-color: #e2e8f0;
}
</style>

<!-- Top Hero & Billing Cycle Switcher -->
<div class="payroll-hero-card shadow-sm mb-4">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-success bg-opacity-20 rounded-3 text-emerald">
                    <i class="fa-solid fa-money-check-dollar fs-1 text-warning"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-1">School Payroll Dashboard</h3>
                    <p class="text-white-50 mb-0 small">
                        Consolidated overview of active employee salaries, shift attendance deductions, cash postings, and payment structures.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <form method="GET" class="d-inline-flex gap-2 justify-content-lg-end align-items-center">
                <select name="month" class="form-select form-select-sm bg-dark text-white border-secondary rounded-pill px-3" style="max-width:130px;" onchange="this.form.submit()">
                    <?php for ($m=1; $m<=12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $m === $selectedMonth ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <select name="year" class="form-select form-select-sm bg-dark text-white border-secondary rounded-pill px-3" style="max-width:110px;" onchange="this.form.submit()">
                    <?php for ($y=date('Y'); $y>=date('Y')-3; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y === $selectedYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <a href="payroll_process.php?month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>" class="btn btn-emerald btn-sm px-3 rounded-pill fw-semibold text-white bg-success">
                    <i class="fa-solid fa-gears me-1"></i> Process Run
                </a>
            </form>
    </div>
</div>

<?php
$pSettings = [];
try {
    $pSettings = $db->query("SELECT * FROM payroll_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
$autoEnabled = !empty($pSettings['auto_issue_enabled']);
$autoDay = (int)($pSettings['auto_issue_day'] ?? 10);
$lastAutoRun = $pSettings['last_auto_issue_run'] ?? 'Not executed yet';
?>

<!-- Automatic Monthly Salary Issuance Banner (10th Date Rule) -->
<div class="card border-0 shadow-sm bg-white mb-4 rounded-3 p-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="p-2 bg-success-subtle text-success rounded-3 fs-4">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <strong class="text-dark fs-6">Automatic Monthly Salary Issuance Engine</strong>
                    <span class="badge <?php echo $autoEnabled ? 'bg-success' : 'bg-secondary'; ?> text-white px-2 py-1 rounded-pill small">
                        <?php echo $autoEnabled ? "Active: Scheduled Every Month on {$autoDay}th Date" : 'Disabled'; ?>
                    </span>
                </div>
                <p class="text-muted small mb-0">
                    System automatically calculates & issues monthly staff salaries for all active staff members on the <strong><?php echo $autoDay; ?>th date</strong> of every month.
                    Last automatic run: <strong><?php echo htmlspecialchars($lastAutoRun); ?></strong>
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-success fw-bold px-3 py-2 shadow-sm text-nowrap" id="btnIssueAutoSalaryNow">
                <i class="fa-solid fa-play me-1"></i>Issue Salary Auto Now (<?php echo $autoDay; ?>th)
            </button>
            <a href="payroll_settings.php" class="btn btn-sm btn-light border text-secondary px-3 py-2 text-nowrap">
                <i class="fa-solid fa-sliders me-1"></i>Configure Rules
            </a>
        </div>
    </div>
</div>

<!-- 8 KPI Micro-Cards Row -->
<div class="row g-3 mb-4">
    <!-- Active Staff Count -->
    <div class="col-6 col-md-3">
        <div class="card kpi-card-gradient shadow-sm h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">ACTIVE EMPLOYEES</span>
                <span class="badge bg-primary-soft text-primary p-2 rounded-circle"><i class="fa-solid fa-users"></i></span>
            </div>
            <h3 class="fw-bold text-dark mb-0"><?php echo $activeStaffCount; ?></h3>
            <small class="text-muted"><?php echo $processedCount; ?> processed / <?php echo $pendingCount; ?> pending</small>
        </div>
    </div>

    <!-- Base Gross Payroll -->
    <div class="col-6 col-md-3">
        <div class="card kpi-card-gradient shadow-sm h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">BASE GROSS PAYROLL</span>
                <span class="badge bg-info-soft text-info p-2 rounded-circle"><i class="fa-solid fa-wallet"></i></span>
            </div>
            <h3 class="fw-bold text-info mb-0" style="font-size: 1.25rem;">Rs. <?php echo number_format($totalBaseGross, 0); ?></h3>
            <small class="text-muted">Monthly basic salaries total</small>
        </div>
    </div>

    <!-- Net Payable Payroll -->
    <div class="col-6 col-md-3">
        <div class="card kpi-card-gradient shadow-sm h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold"><?php echo strtoupper(date('M Y', mktime(0,0,0,$selectedMonth,1,$selectedYear))); ?> NET PAYABLE</span>
                <span class="badge bg-success-soft text-success p-2 rounded-circle"><i class="fa-solid fa-file-invoice-dollar"></i></span>
            </div>
            <h3 class="fw-bold text-success mb-0" style="font-size: 1.25rem;">Rs. <?php echo number_format($totalNetPayable, 0); ?></h3>
            <small class="text-muted">Net calculated disbursal</small>
        </div>
    </div>

    <!-- Disbursed Amount -->
    <div class="col-6 col-md-3">
        <div class="card kpi-card-gradient shadow-sm h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">TOTAL DISBURSED</span>
                <span class="badge bg-emerald-soft text-success p-2 rounded-circle"><i class="fa-solid fa-circle-check"></i></span>
            </div>
            <h3 class="fw-bold text-success mb-0" style="font-size: 1.25rem;">Rs. <?php echo number_format($totalDisbursed, 0); ?></h3>
            <small class="text-muted"><?php echo $totalNetPayable > 0 ? round(($totalDisbursed/$totalNetPayable)*100) : 0; ?>% paid out</small>
        </div>
    </div>

    <!-- Structural Allowances -->
    <div class="col-6 col-md-3">
        <div class="card kpi-card-gradient shadow-sm h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">ALLOWANCES & BONUSES</span>
                <span class="badge bg-warning-soft text-warning p-2 rounded-circle"><i class="fa-solid fa-plus-circle"></i></span>
            </div>
            <h3 class="fw-bold text-warning mb-0" style="font-size: 1.25rem;">Rs. <?php echo number_format($totalAllowances, 0); ?></h3>
            <small class="text-muted">Medical, HRA & Incentives</small>
        </div>
    </div>

    <!-- Shift & Attendance Deductions -->
    <div class="col-6 col-md-3">
        <div class="card kpi-card-gradient shadow-sm h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">SHIFT DEDUCTIONS</span>
                <span class="badge bg-danger-soft text-danger p-2 rounded-circle"><i class="fa-solid fa-minus-circle"></i></span>
            </div>
            <h3 class="fw-bold text-danger mb-0" style="font-size: 1.25rem;">Rs. <?php echo number_format($totalDeductions, 0); ?></h3>
            <small class="text-muted">Absences, LOP & late penalties</small>
        </div>
    </div>

    <!-- Advance Salary Outstanding -->
    <div class="col-6 col-md-3">
        <div class="card kpi-card-gradient shadow-sm h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">ADVANCE BALANCES</span>
                <span class="badge bg-purple-soft text-purple p-2 rounded-circle"><i class="fa-solid fa-hand-holding-dollar"></i></span>
            </div>
            <h3 class="fw-bold text-purple mb-0" style="font-size: 1.25rem;">Rs. <?php echo number_format($outstandingAdvance, 0); ?></h3>
            <small class="text-muted">Staff loans remaining</small>
        </div>
    </div>

    <!-- Pending Disbursement -->
    <div class="col-6 col-md-3">
        <div class="card kpi-card-gradient shadow-sm h-100 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">PENDING DISBURSEMENT</span>
                <span class="badge bg-secondary-soft text-secondary p-2 rounded-circle"><i class="fa-solid fa-hourglass-half"></i></span>
            </div>
            <h3 class="fw-bold text-danger mb-0" style="font-size: 1.25rem;">Rs. <?php echo number_format($totalPending, 0); ?></h3>
            <small class="text-muted">Unpaid salary balance</small>
        </div>
    </div>
</div>

<!-- Cash & Bank Posting Disbursal Breakdown Widget -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-md-6 border-end">
                <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-money-bill-transfer me-2 text-primary"></i>Disbursal Channels Breakdown</h6>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="small text-muted fw-semibold d-block mb-1"><i class="fa-solid fa-cash-register me-1 text-success"></i>Cash Desk Posting</span>
                            <h4 class="fw-bold text-success mb-0">Rs. <?php echo number_format($cashPostingSum, 2); ?></h4>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="small text-muted fw-semibold d-block mb-1"><i class="fa-solid fa-building-columns me-1 text-primary"></i>Bank Transfers</span>
                            <h4 class="fw-bold text-primary mb-0">Rs. <?php echo number_format($bankPostingSum, 2); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 ps-md-4 mt-3 mt-md-0">
                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-chart-pie me-2 text-warning"></i>Disbursal Progress Bar</h6>
                <div class="d-flex justify-content-between text-muted small fw-semibold mb-1">
                    <span>Disbursed: Rs. <?php echo number_format($totalDisbursed, 0); ?></span>
                    <span>Total Net: Rs. <?php echo number_format($totalNetPayable, 0); ?></span>
                </div>
                <?php 
                    $pct = $totalNetPayable > 0 ? min(100, round(($totalDisbursed / $totalNetPayable) * 100)) : 0;
                ?>
                <div class="progress progress-thin mb-3">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $pct; ?>%;" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="small text-muted">
                    <i class="fa-solid fa-circle-info me-1 text-primary"></i>
                    Disbursement progress for <strong><?php echo $monthText; ?></strong> cycle. Paid staff receive salary vouchers automatically.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Consolidated Payroll Submodules Gateway Grid -->
<h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-cubes me-2 text-primary"></i>Payroll Management Submodules</h5>
<div class="row g-3 mb-5">
    <!-- 1. Salary Setup -->
    <div class="col-sm-6 col-md-3">
        <a href="payroll_setup.php" class="submodule-card shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <div class="submodule-icon bg-primary-soft text-primary">
                    <i class="fa-solid fa-user-gear"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">Salary Setup</h6>
                    <small class="text-muted">Assign salary grades & basic rates</small>
                </div>
            </div>
        </a>
    </div>

    <!-- 2. Salary Processing -->
    <div class="col-sm-6 col-md-3">
        <a href="payroll_process.php" class="submodule-card shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <div class="submodule-icon bg-success-soft text-success">
                    <i class="fa-solid fa-gears"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">Salary Processing</h6>
                    <small class="text-muted">Run bulk monthly payroll & post payments</small>
                </div>
            </div>
        </a>
    </div>

    <!-- 3. Shift Deductions & LOP -->
    <div class="col-sm-6 col-md-3">
        <a href="payroll_deductions.php" class="submodule-card shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <div class="submodule-icon bg-danger-soft text-danger">
                    <i class="fa-solid fa-clock-slash"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">Shift Deductions</h6>
                    <small class="text-muted">Late penalties, absences & LOP</small>
                </div>
            </div>
        </a>
    </div>

    <!-- 4. Allowances & Benefits -->
    <div class="col-sm-6 col-md-3">
        <a href="payroll_allowances.php" class="submodule-card shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <div class="submodule-icon bg-warning-soft text-warning">
                    <i class="fa-solid fa-square-plus"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">Allowances</h6>
                    <small class="text-muted">Medical, HRA & conveyance</small>
                </div>
            </div>
        </a>
    </div>

    <!-- 5. Advance Loans -->
    <div class="col-sm-6 col-md-3">
        <a href="payroll_advance.php" class="submodule-card shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <div class="submodule-icon bg-purple-soft text-purple">
                    <i class="fa-solid fa-comments-dollar"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">Advance Loans</h6>
                    <small class="text-muted">Manage employee loans & repayments</small>
                </div>
            </div>
        </a>
    </div>

    <!-- 6. Bonuses & Gifts -->
    <div class="col-sm-6 col-md-3">
        <a href="payroll_bonuses.php" class="submodule-card shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <div class="submodule-icon bg-emerald-soft text-success">
                    <i class="fa-solid fa-gift"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">Bonuses & Gifts</h6>
                    <small class="text-muted">Performance & Eid incentives</small>
                </div>
            </div>
        </a>
    </div>

    <!-- 7. Payroll Slips -->
    <div class="col-sm-6 col-md-3">
        <a href="payroll_slips.php" class="submodule-card shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <div class="submodule-icon bg-info-soft text-info">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">Salary Slips</h6>
                    <small class="text-muted">Print & email payslips to staff</small>
                </div>
            </div>
        </a>
    </div>

    <!-- 8. Payroll Reports -->
    <div class="col-sm-6 col-md-3">
        <a href="payroll_reports.php" class="submodule-card shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <div class="submodule-icon bg-secondary-soft text-secondary">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">Payroll Reports</h6>
                    <small class="text-muted">Export bank sheets, CSV & PDF</small>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Historical Payroll Processing Runs Log -->
<div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
        <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Recent Monthly Payroll Runs</h5>
        <a href="payroll_process.php" class="btn btn-primary btn-sm rounded-pill px-3">
            <i class="fa-solid fa-plus me-1"></i> New Run
        </a>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead>
                    <tr class="bg-light">
                        <th>Billing Cycle</th>
                        <th class="text-center">Staff Count</th>
                        <th class="text-end">Total Disbursed Net</th>
                        <th class="text-center">Disbursal Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($runs)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No processing runs recorded yet. Click "New Run" above.</td></tr>
                    <?php else: foreach ($runs as $r): 
                        $cycleText = date('F Y', mktime(0,0,0, $r['month'], 1, $r['year']));
                        $paidRatio = $r['total_staff'] > 0 ? round(($r['paid_count'] / $r['total_staff']) * 100) : 0;
                        
                        $statusBadge = 'badge-soft-warning';
                        $statusText  = 'Draft / Pending';
                        if ($r['paid_count'] === $r['total_staff'] && $r['total_staff'] > 0) {
                            $statusBadge = 'badge-soft-success';
                            $statusText  = 'Fully Disbursed';
                        } elseif ($r['paid_count'] > 0) {
                            $statusBadge = 'badge-soft-info';
                            $statusText  = "Partial ({$paidRatio}%)";
                        }
                    ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?php echo $cycleText; ?></div>
                                <small class="text-muted"><?php echo !empty($r['created_at']) ? date('d M Y', strtotime($r['created_at'])) : ''; ?></small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border"><?php echo $r['total_staff']; ?> Staff</span>
                            </td>
                            <td class="text-end fw-bold text-success">
                                Rs. <?php echo number_format((float)$r['total_net'], 2); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?php echo $statusBadge; ?> px-3 py-2 rounded-pill"><?php echo $statusText; ?></span>
                            </td>
                            <td class="text-end">
                                <a href="payroll_process.php?month=<?php echo $r['month']; ?>&year=<?php echo $r['year']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1">
                                    <i class="fa-solid fa-eye me-1"></i> View Run
                                </a>
                                <a href="payroll_reports.php?month=<?php echo $r['month']; ?>&year=<?php echo $r['year']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                    <i class="fa-solid fa-file-pdf me-1"></i> Report
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<script>
document.addEventListener("DOMContentLoaded", () => {
    const btnAuto = document.getElementById("btnIssueAutoSalaryNow");
    if (btnAuto) {
        btnAuto.addEventListener("click", () => {
            if (!confirm("Are you sure you want to execute automatic monthly salary issuance now for <?php echo $monthText; ?>?")) return;
            btnAuto.disabled = true;
            btnAuto.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Processing...';
            
            const fd = new FormData();
            fd.append("action", "trigger_auto_payroll");
            fd.append("month", "<?php echo $selectedMonth; ?>");
            fd.append("year", "<?php echo $selectedYear; ?>");
            fd.append("csrf_token", "<?php echo csrfToken(); ?>");

            fetch("../../ajax/payroll.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        location.reload();
                    } else {
                        btnAuto.disabled = false;
                        btnAuto.innerHTML = '<i class="fa-solid fa-play me-1"></i>Issue Salary Auto Now (<?php echo $autoDay; ?>th)';
                    }
                })
                .catch(() => {
                    alert("An error occurred while executing automatic salary issuance.");
                    btnAuto.disabled = false;
                    btnAuto.innerHTML = '<i class="fa-solid fa-play me-1"></i>Issue Salary Auto Now (<?php echo $autoDay; ?>th)';
                });
        });
    }
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
