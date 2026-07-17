<?php
/**
 * Indus Grammar School ERP - Payroll Central Dashboard
 * Version 4.0.0
 */

$pageTitle = 'Payroll Dashboard';
$breadcrumbActive = 'HR & Staff';
include_once __DIR__ . '/../../includes/header.php';

// Restricted to Super Admin, School Admin, and Accountant
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'accountant'])) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permissions to access the payroll module.';
    redirect(APP_URL . '/dashboard.php');
}

$db = Database::getConnection();

$currentMonth = (int)date('m');
$currentYear  = (int)date('Y');
$monthText = date('F Y');

// ── 1. Calculate Metrics ──
// Total active employees
$totalEmployees = (int)$db->query("SELECT COUNT(*) FROM staff WHERE status = 'Active'")->fetchColumn();

// Fetch processing entry ID for current month
$procId = $db->query("SELECT id FROM salary_processing WHERE month = $currentMonth AND year = $currentYear")->fetchColumn();
$procId = $procId ? (int)$procId : 0;

// Processed, Pending, Paid counts/sums
$processedCount = 0;
$pendingCount = $totalEmployees;
$totalPaid = 0.00;
$totalAllowances = 0.00;
$totalDeductions = 0.00;
$currentMonthSum = 0.00;

if ($procId > 0) {
    $processedCount = (int)$db->query("SELECT COUNT(*) FROM salary_details WHERE processing_id = $procId")->fetchColumn();
    $pendingCount   = max(0, $totalEmployees - $processedCount);
    
    $totalPaid       = (float)$db->query("SELECT COALESCE(SUM(net_salary),0) FROM salary_details WHERE processing_id = $procId AND payment_status = 'Paid'")->fetchColumn();
    $totalAllowances = (float)$db->query("SELECT COALESCE(SUM(allowances),0) FROM salary_details WHERE processing_id = $procId")->fetchColumn();
    $totalDeductions = (float)$db->query("SELECT COALESCE(SUM(deductions + advance_salary_deduction),0) FROM salary_details WHERE processing_id = $procId")->fetchColumn();
    $currentMonthSum = (float)$db->query("SELECT COALESCE(SUM(net_salary),0) FROM salary_details WHERE processing_id = $procId")->fetchColumn();
}

// Outstanding advance salary
$outstandingAdvance = (float)$db->query("SELECT COALESCE(SUM(remaining_balance),0) FROM advance_salary WHERE status = 'Pending'")->fetchColumn();

// Fetch payroll processing runs list
$runs = $db->query("
    SELECT p.*, 
           (SELECT COUNT(*) FROM salary_details WHERE processing_id = p.id) as total_staff,
           (SELECT SUM(net_salary) FROM salary_details WHERE processing_id = p.id) as total_net,
           (SELECT COUNT(*) FROM salary_details WHERE processing_id = p.id AND payment_status = 'Paid') as paid_count
    FROM salary_processing p
    ORDER BY p.year DESC, p.month DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Title & Header -->
<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-money-check-dollar me-2 text-primary"></i>School Payroll Dashboard</h3>
        <p class="text-muted small mb-0">Consolidated overview of active employee salaries, shift attendance deductions, cash postings, and payment structures.</p>
    </div>
</div>

<!-- Grid of 8 Metrics Cards -->
<div class="row g-3 mb-4">
    <!-- Total Staff -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #f8f9fa);">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-primary-soft text-primary p-2 fs-6 rounded-pill"><i class="fa-solid fa-users"></i></span>
                    <span class="small text-muted fw-semibold">Total Employees</span>
                </div>
                <h4 class="fw-bold text-dark mb-0"><?php echo $totalEmployees; ?></h4>
            </div>
        </div>
    </div>
    <!-- Payroll Processed -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #f0fdf4);">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-success-soft text-success p-2 fs-6 rounded-pill"><i class="fa-solid fa-circle-check"></i></span>
                    <span class="small text-muted fw-semibold">Runs Processed</span>
                </div>
                <h4 class="fw-bold text-success mb-0"><?php echo $processedCount; ?></h4>
            </div>
        </div>
    </div>
    <!-- Payroll Pending -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #fffbeb);">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-warning-soft text-warning p-2 fs-6 rounded-pill"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    <span class="small text-muted fw-semibold">Runs Pending</span>
                </div>
                <h4 class="fw-bold text-warning mb-0"><?php echo $pendingCount; ?></h4>
            </div>
        </div>
    </div>
    <!-- Total Salary Paid -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #fdf2f8);">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-danger-soft text-danger p-2 fs-6 rounded-pill"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                    <span class="small text-muted fw-semibold">Total Paid</span>
                </div>
                <h4 class="fw-bold text-danger mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($totalPaid, 2); ?></h4>
            </div>
        </div>
    </div>
    <!-- Total Allowances -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #ecfdf5);">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-success-soft text-success p-2 fs-6 rounded-pill"><i class="fa-solid fa-plus"></i></span>
                    <span class="small text-muted fw-semibold">Total Allowances</span>
                </div>
                <h4 class="fw-bold text-success mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($totalAllowances, 2); ?></h4>
            </div>
        </div>
    </div>
    <!-- Total Deductions -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #fff5f5);">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-danger-soft text-danger p-2 fs-6 rounded-pill"><i class="fa-solid fa-minus"></i></span>
                    <span class="small text-muted fw-semibold">Total Deductions</span>
                </div>
                <h4 class="fw-bold text-danger mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($totalDeductions, 2); ?></h4>
            </div>
        </div>
    </div>
    <!-- Advance Outstanding -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #f5f3ff);">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-purple-soft text-purple p-2 fs-6 rounded-pill"><i class="fa-solid fa-comments-dollar"></i></span>
                    <span class="small text-muted fw-semibold">Advances Balance</span>
                </div>
                <h4 class="fw-bold text-purple mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($outstandingAdvance, 2); ?></h4>
            </div>
        </div>
    </div>
    <!-- Current Month Net -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #eff6ff);">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-primary-soft text-primary p-2 fs-6 rounded-pill"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                    <span class="small text-muted fw-semibold"><?php echo $monthText; ?> Net</span>
                </div>
                <h4 class="fw-bold text-primary mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($currentMonthSum, 2); ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Consolidated Submodule Gateways (Navigation Roster) -->
<h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-cubes me-2 text-primary"></i>Payroll Submodules</h5>
<div class="row g-3 mb-5">
    <!-- 1. Salary Setup -->
    <div class="col-md-3">
        <a href="payroll_setup.php" class="card border-0 shadow-sm text-decoration-none h-100 gateway-card" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="gateway-icon text-primary bg-primary-soft rounded p-3"><i class="fa-solid fa-user-gear fs-4"></i></div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Salary Setup</h6>
                    <small class="text-muted text-xs">Assign structures & details</small>
                </div>
            </div>
        </a>
    </div>
    <!-- 2. Salary Processing -->
    <div class="col-md-3">
        <a href="payroll_process.php" class="card border-0 shadow-sm text-decoration-none h-100 gateway-card" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="gateway-icon text-success bg-success-soft rounded p-3"><i class="fa-solid fa-gears fs-4"></i></div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Salary Processing</h6>
                    <small class="text-muted text-xs">Run bulk monthly payrolls</small>
                </div>
            </div>
        </a>
    </div>
    <!-- 3. Allowances -->
    <div class="col-md-3">
        <a href="payroll_allowances.php" class="card border-0 shadow-sm text-decoration-none h-100 gateway-card" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="gateway-icon text-success bg-success-soft rounded p-3"><i class="fa-solid fa-plus fs-4"></i></div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Allowances</h6>
                    <small class="text-muted text-xs">Manage structural bonuses</small>
                </div>
            </div>
        </a>
    </div>
    <!-- 4. Deductions -->
    <div class="col-md-3">
        <a href="payroll_deductions.php" class="card border-0 shadow-sm text-decoration-none h-100 gateway-card" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="gateway-icon text-danger bg-danger-soft rounded p-3"><i class="fa-solid fa-minus fs-4"></i></div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Deductions</h6>
                    <small class="text-muted text-xs">Configure tax, provident funds</small>
                </div>
            </div>
        </a>
    </div>
    <!-- 5. Advance Salary -->
    <div class="col-md-3">
        <a href="payroll_advance.php" class="card border-0 shadow-sm text-decoration-none h-100 gateway-card" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="gateway-icon text-purple bg-purple-soft rounded p-3"><i class="fa-solid fa-comments-dollar fs-4"></i></div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Advance Salary</h6>
                    <small class="text-muted text-xs">Log loans & auto-installments</small>
                </div>
            </div>
        </a>
    </div>
    <!-- 6. Bonus & Incentives -->
    <div class="col-md-3">
        <a href="payroll_bonuses.php" class="card border-0 shadow-sm text-decoration-none h-100 gateway-card" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="gateway-icon text-warning bg-warning-soft rounded p-3"><i class="fa-solid fa-gift fs-4"></i></div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Bonuses & Gifts</h6>
                    <small class="text-muted text-xs">Eid, performance incentives</small>
                </div>
            </div>
        </a>
    </div>
    <!-- 7. Payroll Reports -->
    <div class="col-md-3">
        <a href="payroll_reports.php" class="card border-0 shadow-sm text-decoration-none h-100 gateway-card" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="gateway-icon text-primary bg-primary-soft rounded p-3"><i class="fa-solid fa-file-invoice-dollar fs-4"></i></div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Payroll Reports</h6>
                    <small class="text-muted text-xs">Summaries & exports PDF/CSV</small>
                </div>
            </div>
        </a>
    </div>
    <!-- 8. Payroll Settings -->
    <div class="col-md-3">
        <a href="payroll_settings.php" class="card border-0 shadow-sm text-decoration-none h-100 gateway-card" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="gateway-icon text-secondary bg-secondary-soft rounded p-3"><i class="fa-solid fa-sliders fs-4"></i></div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Payroll Settings</h6>
                    <small class="text-muted text-xs">Time values & lock criteria</small>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Monthly Runs Roster -->
<div class="card border-0 shadow-sm" style="border-radius:12px;">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
        <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-receipt me-2 text-primary"></i>Recent Payroll Runs</h5>
        <a href="payroll_process.php" class="btn btn-primary btn-sm px-3 rounded-pill"><i class="fa-solid fa-gears me-1"></i>New Processing Run</a>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Billing Cycle</th>
                        <th class="text-center">Staff Count</th>
                        <th class="text-end">Total Salary Disbursed</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($runs)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No processing runs exist. Click "New Processing Run" to get started.</td></tr>
                    <?php else: foreach ($runs as $r): ?>
                        <tr>
                            <td class="fw-bold text-dark"><?php echo date('F Y', mktime(0,0,0,$r['month'],1,$r['year'])); ?></td>
                            <td class="text-center fw-semibold"><?php echo $r['total_staff']; ?> Employees</td>
                            <td class="text-end fw-bold text-dark">Rs. <?php echo number_format((float)$r['total_net'], 2); ?></td>
                            <td class="text-center">
                                <?php 
                                $status = $r['status'];
                                if ($r['total_staff'] > 0 && $r['paid_count'] === $r['total_staff']) $status = 'Paid';
                                else if ($r['paid_count'] > 0) $status = 'Processed'; // partially paid

                                $badge = 'warning text-dark';
                                if ($status === 'Paid') $badge = 'success';
                                else if ($status === 'Processed') $badge = 'info';
                                ?>
                                <span class="badge bg-<?php echo $badge; ?>-soft px-3 py-2 rounded-pill"><?php echo $status; ?></span>
                            </td>
                            <td class="text-end">
                                <a href="payroll_process.php?month=<?php echo $r['month']; ?>&year=<?php echo $r['year']; ?>" class="btn btn-sm btn-outline-primary px-3 rounded-pill">
                                    <i class="fa-solid fa-eye me-1"></i>Manage Run
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.gateway-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.gateway-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.08)!important;
}
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
