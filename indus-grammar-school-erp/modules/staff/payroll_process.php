<?php
/**
 * Indus Grammar School ERP - Automated Salary Processing Ledger
 * Version 4.0.0 (Premium Redesign)
 */

$pageTitle = 'Salary Processing';
$breadcrumbActive = 'Payroll';
include_once __DIR__ . '/../../includes/header.php';

// Access Control
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'accountant'])) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to access the payroll processing module.';
    redirect(APP_URL . '/dashboard.php');
}

$db = Database::getConnection();

// Include PayrollService & trigger 10th date automatic salary issuance check
require_once __DIR__ . '/../../services/PayrollService.php';
PayrollService::checkAndRunAutoPayroll();

// Default target filters
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedDept  = sanitize($_GET['department'] ?? '');
$selectedStatus = sanitize($_GET['status'] ?? '');
$monthText = date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));

$where = " WHERE p.month = :month AND p.year = :year";
$params = [
    'month' => $selectedMonth,
    'year'  => $selectedYear
];
if ($selectedDept !== '') {
    $where .= " AND s.department = :dept";
    $params['dept'] = $selectedDept;
}
if ($selectedStatus !== '') {
    $where .= " AND d.payment_status = :status";
    $params['status'] = $selectedStatus;
}

// Fetch processed salary rows for month & year
$salaries = [];
try {
    $stmt = $db->prepare("
        SELECT d.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department, s.phone
        FROM salary_details d
        JOIN salary_processing p ON d.processing_id = p.id
        JOIN staff s ON d.staff_id = s.id
        $where
        ORDER BY s.employee_no ASC
    ");
    $stmt->execute($params);
    $salaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading processed salaries: " . $e->getMessage());
}

// Calculate Cycle Executive Summary
$totalProcessed = count($salaries);
$sumBasic = 0.0;
$sumAllowances = 0.0;
$sumDeductions = 0.0;
$sumAdvances = 0.0;
$sumBonuses = 0.0;
$sumNetPayable = 0.0;
$sumPaidAmount = 0.0;
$paidCount = 0;

foreach ($salaries as $s) {
    $sumBasic += (float)$s['basic_salary'];
    $sumAllowances += (float)$s['allowances'];
    $sumDeductions += (float)$s['deductions'];
    $sumAdvances += (float)$s['advance_salary_deduction'];
    $sumBonuses += (float)$s['bonus'];
    $sumNetPayable += (float)$s['net_salary'];
    
    if ($s['payment_status'] === 'Paid') {
        $paidCount++;
        $sumPaidAmount += (float)$s['net_salary'];
    }
}
$sumPendingAmount = $sumNetPayable - $sumPaidAmount;

$departments = $db->query("SELECT DISTINCT department FROM staff WHERE status = 'Active' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);

// Color generator helper for avatar badges
if (!function_exists('getAvatarColor')) {
    function getAvatarColor($name) {
        $colors = ['#1d4ed8', '#0d9488', '#b91c1c', '#c2410c', '#6d28d9', '#0369a1', '#be185d', '#4d7c0f'];
        $hash = crc32($name);
        return $colors[abs($hash) % count($colors)];
    }
}
?>

<style>
.proc-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.5rem 1.75rem;
    position: relative;
    overflow: hidden;
}
.proc-hero-card::after {
    content: "";
    position: absolute;
    top: -50%;
    right: -10%;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.kpi-proc-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.15rem;
    transition: all 0.25s ease;
}
.kpi-proc-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

.staff-avatar-sm {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    color: #ffffff;
}

.badge-soft-success { background-color: #dcfce7; color: #15803d; }
.badge-soft-warning { background-color: #fef9c3; color: #a16207; }
.badge-soft-danger  { background-color: #fee2e2; color: #b91c1c; }
.badge-soft-info    { background-color: #e0f2fe; color: #0369a1; }
.badge-soft-purple  { background-color: #f3e8ff; color: #6b21a8; }
</style>

<!-- Hero Title Header -->
<div class="proc-hero-card shadow-sm mb-4">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-success bg-opacity-20 rounded-3 text-warning">
                    <i class="fa-solid fa-gears fs-2"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-1">Automated Salary Processing</h3>
                    <p class="text-white-50 mb-0 small">
                        Calculates monthly net compensation automatically based on staff base structures, shift attendance metrics (absences/lates), loan recoveries, and performance bonuses.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <a href="payroll.php" class="btn btn-outline-light btn-sm px-3 rounded-pill me-1">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
            </a>
            <a href="payroll_reports.php?month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>" class="btn btn-warning btn-sm px-3 rounded-pill text-dark fw-semibold">
                <i class="fa-solid fa-file-pdf me-1"></i> Cycle Summary Report
            </a>
        </div>
    </div>
</div>

<!-- 5 Cycle Executive Micro-Cards -->
<div class="row g-3 mb-4">
    <!-- Staff Count in Run -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-proc-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">PROCESSED STAFF</span>
                <span class="badge bg-primary-soft text-primary p-2 rounded-circle"><i class="fa-solid fa-users"></i></span>
            </div>
            <h4 class="fw-bold text-dark mb-0"><?php echo $totalProcessed; ?> Staff</h4>
            <small class="text-muted"><?php echo $paidCount; ?> paid / <?php echo $totalProcessed - $paidCount; ?> pending</small>
        </div>
    </div>

    <!-- Total Gross Base -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-proc-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">CYCLE BASE SALARY</span>
                <span class="badge bg-info-soft text-info p-2 rounded-circle"><i class="fa-solid fa-wallet"></i></span>
            </div>
            <h4 class="fw-bold text-info mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumBasic, 0); ?></h4>
            <small class="text-muted">Basic salaries before adjustments</small>
        </div>
    </div>

    <!-- Allowances & Bonuses -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-proc-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">ALLOWANCES & BONUSES</span>
                <span class="badge bg-success-soft text-success p-2 rounded-circle"><i class="fa-solid fa-plus-circle"></i></span>
            </div>
            <h4 class="fw-bold text-success mb-0" style="font-size: 1.15rem;">+Rs. <?php echo number_format($sumAllowances + $sumBonuses, 0); ?></h4>
            <small class="text-muted">Benefits & incentives</small>
        </div>
    </div>

    <!-- Deductions & Loans -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-proc-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">DEDUCTIONS & LOANS</span>
                <span class="badge bg-danger-soft text-danger p-2 rounded-circle"><i class="fa-solid fa-minus-circle"></i></span>
            </div>
            <h4 class="fw-bold text-danger mb-0" style="font-size: 1.15rem;">-Rs. <?php echo number_format($sumDeductions + $sumAdvances, 0); ?></h4>
            <small class="text-muted">Lates, Tax & advance recoveries</small>
        </div>
    </div>

    <!-- Cycle Net Disbursal -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-proc-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">CYCLE NET PAYABLE</span>
                <span class="badge bg-purple-soft text-purple p-2 rounded-circle"><i class="fa-solid fa-file-invoice-dollar"></i></span>
            </div>
            <h4 class="fw-bold text-purple mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumNetPayable, 0); ?></h4>
            <small class="text-muted">Rs. <?php echo number_format($sumPaidAmount, 0); ?> paid</small>
        </div>
    </div>
</div>

<!-- Run Control Toolbar -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center" id="filterForm">
            <div class="col-md-3">
                <select class="form-select bg-light" name="month" onchange="document.getElementById('filterForm').submit()">
                    <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $selectedMonth === $m ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0,0,0,$m,1)); ?> Cycle
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select bg-light" name="year" onchange="document.getElementById('filterForm').submit()">
                    <?php for($y=date('Y'); $y>=2024; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $selectedYear === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select bg-light" name="department" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo $selectedDept === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select bg-light" name="status" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Statuses</option>
                    <option value="Paid" <?php echo $selectedStatus === 'Paid' ? 'selected' : ''; ?>>Paid Only</option>
                    <option value="Pending" <?php echo $selectedStatus === 'Pending' ? 'selected' : ''; ?>>Pending Only</option>
                </select>
            </div>
            
            <div class="col-md-2 text-end">
                <?php if (hasPermission('hr_manage')): ?>
                <button type="button" class="btn btn-success w-100 rounded-pill fw-semibold" id="btnProcess">
                    <i class="fa-solid fa-calculator me-1"></i>Run Payroll
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Processed Salary Ledger Roster Table -->
<div class="card border-0 shadow-sm mb-5" style="border-radius:14px;">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
        <h5 class="fw-bold text-secondary mb-0">
            <i class="fa-solid fa-receipt me-2 text-primary"></i>Processed Salary Ledger (<?php echo $monthText; ?>)
        </h5>
        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
            <i class="fa-solid fa-circle-check text-success me-1"></i><?php echo $paidCount; ?> / <?php echo $totalProcessed; ?> Paid
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Staff Member</th>
                        <th>EMP ID</th>
                        <th class="text-center">Shift Attendance</th>
                        <th class="text-end">Base Basic</th>
                        <th class="text-end text-success">+ Allow.</th>
                        <th class="text-end text-danger">- Deduct.</th>
                        <th class="text-end text-purple">- Advance</th>
                        <th class="text-end text-success">+ Bonus</th>
                        <th class="text-end fw-bold text-dark">Net Salary</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($salaries)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-calculator fs-1 mb-2 text-muted d-block opacity-50"></i>
                                No processed payroll records found for <strong><?php echo $monthText; ?></strong>.<br>
                                Click <strong>"Run Payroll"</strong> above to calculate automatically from parameters.
                            </td>
                        </tr>
                    <?php else: foreach ($salaries as $s): 
                        $fullName = trim($s['first_name'] . ' ' . $s['last_name']);
                        $initials = strtoupper(substr($s['first_name'], 0, 1) . substr($s['last_name'], 0, 1));
                        $bgColor  = getAvatarColor($fullName);
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="staff-avatar-sm" style="background-color: <?php echo $bgColor; ?>;">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark mb-0"><?php echo sanitize($fullName); ?></div>
                                        <small class="text-muted"><?php echo sanitize($s['designation'] . ' | ' . $s['department']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><code><?php echo sanitize($s['employee_no']); ?></code></td>
                            <td class="text-center small">
                                <span class="badge bg-success-soft text-success px-2 py-1 rounded-pill"><?php echo $s['present_days']; ?> Pres</span>
                                <span class="badge bg-danger-soft text-danger px-2 py-1 rounded-pill"><?php echo $s['absent_days']; ?> Abs</span>
                                <?php if($s['late_days'] > 0): ?>
                                    <br><small class="text-warning fw-semibold"><i class="fa-solid fa-clock me-1"></i><?php echo $s['late_days']; ?> Late</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold text-dark">Rs. <?php echo number_format($s['basic_salary'], 2); ?></td>
                            <td class="text-end text-success fw-semibold">+Rs. <?php echo number_format($s['allowances'], 2); ?></td>
                            <td class="text-end text-danger fw-semibold">-Rs. <?php echo number_format($s['deductions'], 2); ?></td>
                            <td class="text-end text-purple fw-semibold">-Rs. <?php echo number_format($s['advance_salary_deduction'], 2); ?></td>
                            <td class="text-end text-success fw-semibold">+Rs. <?php echo number_format($s['bonus'], 2); ?></td>
                            <td class="text-end fw-bold text-dark fs-6">
                                Rs. <?php echo number_format($s['net_salary'], 2); ?>
                            </td>
                            <td class="text-center">
                                <?php if ($s['payment_status'] === 'Paid'): ?>
                                    <span class="badge bg-success-soft text-success px-3 py-1 rounded-pill"><i class="fa-solid fa-check me-1"></i>Paid</span>
                                    <br><small class="text-muted" style="font-size:0.7rem;"><?php echo !empty($s['payment_date']) ? date('d M Y', strtotime($s['payment_date'])) : ''; ?></small>
                                <?php else: ?>
                                    <span class="badge bg-warning-soft text-warning px-3 py-1 rounded-pill"><i class="fa-solid fa-hourglass-half me-1"></i>Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="payroll_slips.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill me-1" target="_blank" title="Preview Salary Slip">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                                <?php if ($s['payment_status'] === 'Pending' && hasPermission('hr_manage')): ?>
                                    <button class="btn btn-sm btn-success rounded-pill px-3 btn-pay" 
                                            data-id="<?php echo $s['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($fullName); ?>" 
                                            data-net="<?php echo number_format($s['net_salary'], 2); ?>" 
                                            title="Disburse Payment">
                                        <i class="fa-solid fa-hand-holding-dollar me-1"></i>Pay
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pay Salary Disbursal Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;">
            <div class="modal-header border-0 bg-dark text-white pt-4 px-4" style="border-top-left-radius:18px; border-top-right-radius:18px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 bg-success bg-opacity-20 rounded text-warning fs-3">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0">Disburse Salary Payment</h5>
                        <div class="small text-white-50">Post Cash/Bank Payment to Accounts Ledger</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="payForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="pay_salary">
                    <input type="hidden" name="salary_id" id="paySalaryId">

                    <div class="alert alert-info border-0 shadow-sm small py-3 mb-4">
                        <i class="fa-solid fa-circle-info me-2 text-primary fs-5 align-middle"></i>
                        Processing this disbursement automatically logs an operational expense in the <strong>Accounts Module</strong> and updates cash drawer / bank balances.
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Staff Member</label>
                        <input type="text" class="form-control bg-light fw-bold text-dark" id="payStaffName" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Net Calculated Salary</label>
                        <div class="input-group">
                            <span class="input-group-text small bg-light">Rs.</span>
                            <input type="text" class="form-control bg-light fw-bold text-success fs-5" id="payNetSalary" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Disbursal Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Disbursal Channel <span class="text-danger">*</span></label>
                        <select class="form-select" name="payment_method" required>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cash">Cash</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 bg-light" style="border-bottom-left-radius:18px; border-bottom-right-radius:18px;">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="payForm" class="btn btn-success rounded-pill px-4" id="btnConfirmPay">
                    <i class="fa-solid fa-check me-1"></i> Confirm & Disburse
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="procToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="procToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
const payModalObj = new bootstrap.Modal(document.getElementById("payModal"));

function showToast(msg, ok) {
    const t = document.getElementById("procToast");
    const m = document.getElementById("procToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Generate Payroll trigger
    const btnProcess = document.getElementById("btnProcess");
    if(btnProcess) {
        btnProcess.addEventListener("click", function() {
            if(!confirm("Are you sure you want to run automatic payroll for ' . $monthText . '? This will recalculate salaries, attendance fines, advance loan repayments, and bonuses.")) return;
            this.disabled = true; 
            this.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-2"></i>Calculating...`;

            const fd = new FormData();
            fd.append("action", "generate_payroll_bulk");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("month", "' . $selectedMonth . '");
            fd.append("year", "' . $selectedYear . '");
            fd.append("department", "' . $selectedDept . '");

            fetch("../../ajax/payroll.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1200);
                    else { 
                        this.disabled = false; 
                        this.innerHTML = `<i class="fa-solid fa-calculator me-1"></i>Run Payroll`; 
                    }
                })
                .catch(() => {
                    showToast("Communication error.", false);
                    this.disabled = false; 
                    this.innerHTML = `<i class="fa-solid fa-calculator me-1"></i>Run Payroll`;
                });
        });
    }

    // Modal Pay triggers
    document.querySelectorAll(".btn-pay").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("paySalaryId").value = this.dataset.id;
            document.getElementById("payStaffName").value = this.dataset.name;
            document.getElementById("payNetSalary").value = this.dataset.net;
            payModalObj.show();
        });
    });

    // Form Pay submission
    const payForm = document.getElementById("payForm");
    if(payForm) {
        payForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnConfirmPay");
            btn.disabled = true; 
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-1"></i> Disbursing...`;

            fetch("../../ajax/payroll.php", { method: "POST", body: new FormData(payForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        payModalObj.hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        btn.disabled = false; 
                        btn.innerHTML = `<i class="fa-solid fa-check me-1"></i> Confirm & Disburse`;
                    }
                })
                .catch(() => {
                    showToast("System error.", false);
                    btn.disabled = false; 
                    btn.innerHTML = `<i class="fa-solid fa-check me-1"></i> Confirm & Disburse`;
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
