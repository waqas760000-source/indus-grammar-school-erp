<?php
/**
 * Indus Grammar School ERP - Reports Central Dashboard
 * Version 4.0.0
 */

$pageTitle = 'Reports Dashboard';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();
$today = date('Y-m-d');
$currentMonth = date('m');
$currentYear = date('Y');

// 1. Total Students
$totalStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();

// 2. Student Present Today Pct
$attStats = $db->query("
    SELECT 
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
        COUNT(*) as total
    FROM attendance 
    WHERE date = '$today'
")->fetch(PDO::FETCH_ASSOC);
$presentTodayPct = ($attStats && $attStats['total'] > 0) ? round(($attStats['present'] / $attStats['total']) * 100, 1) : 0.00;
$presentTodayCount = $attStats ? (int)$attStats['present'] : 0;

// 3. Fees Collected Today
$feesCollectedToday = (float)$db->query("SELECT SUM(amount_paid) FROM fee_payments WHERE payment_date = '$today'")->fetchColumn();

// 4. Pending Fees Outstanding
$pendingFees = (float)$db->query("SELECT SUM(total_payable - paid_amount) FROM fee_ledger WHERE status IN ('Pending', 'Partial')")->fetchColumn();

// 5. Monthly Income
$monthlyIncome = (float)$db->query("SELECT SUM(amount_paid) FROM fee_payments WHERE MONTH(payment_date) = '$currentMonth' AND YEAR(payment_date) = '$currentYear'")->fetchColumn();

// 6. Monthly Expenses
$monthlyExpenses = (float)$db->query("SELECT SUM(amount) FROM expenses WHERE MONTH(expense_date) = '$currentMonth' AND YEAR(expense_date) = '$currentYear'")->fetchColumn();

// 7. Staff Present Today
$staffPresent = (int)$db->query("SELECT COUNT(*) FROM staff_attendance WHERE date = '$today' AND status = 'Present'")->fetchColumn();

// 8. Payroll Processed Current Month
$payrollProcessed = (float)$db->query("
    SELECT SUM(sd.net_salary) 
    FROM salary_details sd 
    JOIN salary_processing sp ON sd.processing_id = sp.id 
    WHERE sd.payment_status = 'Paid' AND sp.month = '$currentMonth' AND sp.year = '$currentYear'
")->fetchColumn();

// 9. Exam Results Published
$examResultsPublished = (int)$db->query("SELECT COUNT(DISTINCT exam_type_id, class_id) FROM exam_results")->fetchColumn();

?>

<style>
:root {
    --royal-blue: #1e3a8a;
    --royal-blue-light: #3b82f6;
    --royal-blue-soft: #eff6ff;
}
.theme-card-blue {
    background: linear-gradient(135deg, var(--royal-blue) 0%, #2563eb 100%);
    color: white;
}
.report-sub-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: 12px;
    border: 1px solid rgba(226, 232, 240, 0.8);
}
.report-sub-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.1);
    border-color: var(--royal-blue-light);
}
.report-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}
</style>

<!-- Title Header -->
<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Reports Hub</h3>
        <p class="text-muted small mb-0">Centralized academic, financial, attendance, staff and payroll metrics compiled in real time.</p>
    </div>
</div>

<!-- Metrics Cards Row 1 -->
<div class="row g-3 mb-4">
    <!-- Card 1 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-primary-soft p-3 rounded-3 me-3 text-primary">
                    <i class="fa-solid fa-user-graduate fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-1">Total Students</h6>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo $totalStudents; ?></h3>
                </div>
            </div>
        </div>
    </div>
    <!-- Card 2 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-success-soft p-3 rounded-3 me-3 text-success">
                    <i class="fa-solid fa-calendar-check fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-1">Present Today</h6>
                    <h3 class="fw-bold mb-0 text-success"><?php echo $presentTodayPct; ?>%</h3>
                    <small class="text-muted text-xs"><?php echo $presentTodayCount; ?> present</small>
                </div>
            </div>
        </div>
    </div>
    <!-- Card 3 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-info-soft p-3 rounded-3 me-3 text-info">
                    <i class="fa-solid fa-hand-holding-dollar fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-1">Collected Today</h6>
                    <h3 class="fw-bold mb-0 text-dark">Rs. <?php echo number_format($feesCollectedToday, 0); ?></h3>
                </div>
            </div>
        </div>
    </div>
    <!-- Card 4 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-danger-soft p-3 rounded-3 me-3 text-danger">
                    <i class="fa-solid fa-file-invoice-dollar fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-1">Outstanding Fees</h6>
                    <h3 class="fw-bold mb-0 text-danger">Rs. <?php echo number_format($pendingFees, 0); ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Metrics Cards Row 2 -->
<div class="row g-3 mb-4">
    <!-- Card 5 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-success-soft p-3 rounded-3 me-3 text-success">
                    <i class="fa-solid fa-scale-balanced fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-1">Monthly Income</h6>
                    <h3 class="fw-bold mb-0 text-success">Rs. <?php echo number_format($monthlyIncome, 0); ?></h3>
                </div>
            </div>
        </div>
    </div>
    <!-- Card 6 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-danger-soft p-3 rounded-3 me-3 text-danger">
                    <i class="fa-solid fa-receipt fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-1">Monthly Expense</h6>
                    <h3 class="fw-bold mb-0 text-danger">Rs. <?php echo number_format($monthlyExpenses, 0); ?></h3>
                </div>
            </div>
        </div>
    </div>
    <!-- Card 7 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-primary-soft p-3 rounded-3 me-3 text-primary">
                    <i class="fa-solid fa-users fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-1">Staff Present</h6>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo $staffPresent; ?></h3>
                </div>
            </div>
        </div>
    </div>
    <!-- Card 8 -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 p-lg-4 d-flex align-items-center">
                <div class="flex-shrink-0 bg-warning-soft p-3 rounded-3 me-3 text-warning">
                    <i class="fa-solid fa-money-check-dollar fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted small fw-semibold text-uppercase mb-1">Payroll Paid</h6>
                    <h3 class="fw-bold mb-0 text-dark">Rs. <?php echo number_format($payrollProcessed, 0); ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Submodules Grid Section -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <h5 class="fw-bold text-secondary mb-4"><i class="fa-solid fa-list-check me-2 text-primary"></i>Report Submodules</h5>
        
        <div class="row g-3">
            <!-- 1. Student Reports -->
            <div class="col-md-6 col-xl-4">
                <div class="card report-sub-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="report-icon-box bg-primary-soft text-primary me-3">
                            <i class="fa-solid fa-user-graduate"></i>
                        </div>
                        <div>
                            <a href="students.php" class="fw-bold text-dark text-decoration-none d-block">Student Reports</a>
                            <small class="text-muted text-xs">Directories, demographics & alumni</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 2. Attendance Reports -->
            <div class="col-md-6 col-xl-4">
                <div class="card report-sub-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="report-icon-box bg-success-soft text-success me-3">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <div>
                            <a href="attendance.php" class="fw-bold text-dark text-decoration-none d-block">Attendance Reports</a>
                            <small class="text-muted text-xs">Student and staff attendance history</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Fee Reports -->
            <div class="col-md-6 col-xl-4">
                <div class="card report-sub-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="report-icon-box bg-info-soft text-info me-3">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                        <div>
                            <a href="fees.php" class="fw-bold text-dark text-decoration-none d-block">Fee Reports</a>
                            <small class="text-muted text-xs">Collections, pending dues & discounts</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Accounts Reports -->
            <div class="col-md-6 col-xl-4">
                <div class="card report-sub-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="report-icon-box bg-danger-soft text-danger me-3">
                            <i class="fa-solid fa-scale-balanced"></i>
                        </div>
                        <div>
                            <a href="accounts.php" class="fw-bold text-dark text-decoration-none d-block">Accounts Reports</a>
                            <small class="text-muted text-xs">General cashbook, P&L & expenses</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Examination Reports -->
            <div class="col-md-6 col-xl-4">
                <div class="card report-sub-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="report-icon-box bg-purple-soft text-purple me-3">
                            <i class="fa-solid fa-graduation-cap"></i>
                        </div>
                        <div>
                            <a href="examination.php" class="fw-bold text-dark text-decoration-none d-block">Examination Reports</a>
                            <small class="text-muted text-xs">Merit ranks, subject pass rates</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. Staff Reports -->
            <div class="col-md-6 col-xl-4">
                <div class="card report-sub-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="report-icon-box bg-dark-soft text-dark me-3">
                            <i class="fa-solid fa-users-gear"></i>
                        </div>
                        <div>
                            <a href="staff.php" class="fw-bold text-dark text-decoration-none d-block">Staff Reports</a>
                            <small class="text-muted text-xs">Directory lists & designations</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 7. Payroll Reports -->
            <div class="col-md-6 col-xl-4">
                <div class="card report-sub-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="report-icon-box bg-warning-soft text-warning-dark me-3">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                        <div>
                            <a href="payroll.php" class="fw-bold text-dark text-decoration-none d-block">Payroll Reports</a>
                            <small class="text-muted text-xs">Salaries, allowances & bonuses logs</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 8. Communication Reports -->
            <div class="col-md-6 col-xl-4">
                <div class="card report-sub-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="report-icon-box bg-primary-soft text-primary me-3">
                            <i class="fa-solid fa-comments"></i>
                        </div>
                        <div>
                            <a href="communication.php" class="fw-bold text-dark text-decoration-none d-block">Communication Reports</a>
                            <small class="text-muted text-xs">SMS dispatch history & announcement logs</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 9. Custom Reports -->
            <div class="col-md-6 col-xl-4">
                <div class="card report-sub-card h-100">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="report-icon-box bg-secondary-soft text-secondary me-3">
                            <i class="fa-solid fa-sliders"></i>
                        </div>
                        <div>
                            <a href="custom.php" class="fw-bold text-dark text-decoration-none d-block">Custom Reports</a>
                            <small class="text-muted text-xs">Dynamic query spreadsheet constructor</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
