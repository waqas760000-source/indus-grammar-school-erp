<?php
/**
 * Indus Grammar School ERP - Centralized Reports Hub Dashboard
 * Version 4.0.0 (Real-Time Executive Intelligence Suite)
 */

$pageTitle = 'Reports Hub Dashboard';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

$today = date('Y-m-d');
$currentMonth = date('m');
$currentYear = date('Y');

// ── Real-Time Metrics Aggregation ──────────────────────────────────
// 1. Academic Metrics
$totalStudents = 0;
$totalClasses = 0;
$totalSections = 0;
$examPassRate = 0;
try {
    $totalStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
    $totalClasses  = (int)$db->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $totalSections = (int)$db->query("SELECT COUNT(*) FROM sections")->fetchColumn();
    
    // Exam pass rate estimation
    $examStats = $db->query("
        SELECT 
            COUNT(*) as total_marks,
            SUM(CASE WHEN obtained_marks >= (total_marks * 0.4) THEN 1 ELSE 0 END) as passed_marks
        FROM student_marks
    ")->fetch(PDO::FETCH_ASSOC);
    if ($examStats && $examStats['total_marks'] > 0) {
        $examPassRate = round(($examStats['passed_marks'] / $examStats['total_marks']) * 100, 1);
    }
} catch (Exception $e) {}

// 2. Attendance Metrics
$studentPresentPct = 0;
$studentPresentCount = 0;
$studentTotalAtt = 0;
$staffPresentCount = 0;
$staffTotalCount = 0;
$staffPresentPct = 0;
try {
    $stAtt = $db->query("
        SELECT 
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
            COUNT(*) as total
        FROM attendance 
        WHERE date = '$today'
    ")->fetch(PDO::FETCH_ASSOC);
    if ($stAtt && $stAtt['total'] > 0) {
        $studentTotalAtt = (int)$stAtt['total'];
        $studentPresentCount = (int)$stAtt['present'];
        $studentPresentPct = round(($studentPresentCount / $studentTotalAtt) * 100, 1);
    }

    $sfAtt = $db->query("
        SELECT 
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
            COUNT(*) as total
        FROM staff_attendance 
        WHERE date = '$today'
    ")->fetch(PDO::FETCH_ASSOC);
    if ($sfAtt && $sfAtt['total'] > 0) {
        $staffTotalCount = (int)$sfAtt['total'];
        $staffPresentCount = (int)$sfAtt['present'];
        $staffPresentPct = round(($staffPresentCount / $staffTotalCount) * 100, 1);
    } else {
        $staffTotalCount = (int)$db->query("SELECT COUNT(*) FROM staff WHERE status = 'Active'")->fetchColumn();
    }
} catch (Exception $e) {}

// 3. Financial Metrics
$feesCollectedToday = 0;
$pendingFeesTotal = 0;
$totalCollectedYTD = 0;
$monthlyIncome = 0;
$monthlyExpenses = 0;
$netMonthlyProfit = 0;
$feeEfficiencyPct = 0;
try {
    $feesCollectedToday = (float)$db->query("SELECT COALESCE(SUM(amount_paid),0) FROM fee_payments WHERE payment_date = '$today'")->fetchColumn();
    $pendingFeesTotal   = (float)$db->query("SELECT COALESCE(SUM(total_payable - paid_amount),0) FROM fee_ledger WHERE status IN ('Pending', 'Partial')")->fetchColumn();
    $totalCollectedYTD  = (float)$db->query("SELECT COALESCE(SUM(amount_paid),0) FROM fee_payments WHERE YEAR(payment_date) = '$currentYear'")->fetchColumn();
    
    $monthlyIncome   = (float)$db->query("SELECT COALESCE(SUM(amount_paid),0) FROM fee_payments WHERE MONTH(payment_date) = '$currentMonth' AND YEAR(payment_date) = '$currentYear'")->fetchColumn();
    $monthlyExpenses = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE MONTH(expense_date) = '$currentMonth' AND YEAR(expense_date) = '$currentYear'")->fetchColumn();
    $netMonthlyProfit = $monthlyIncome - $monthlyExpenses;

    $totalFeeBilled = (float)$db->query("SELECT COALESCE(SUM(total_payable),0) FROM fee_ledger")->fetchColumn();
    $totalFeePaid   = (float)$db->query("SELECT COALESCE(SUM(paid_amount),0) FROM fee_ledger")->fetchColumn();
    if ($totalFeeBilled > 0) {
        $feeEfficiencyPct = round(($totalFeePaid / $totalFeeBilled) * 100, 1);
    }
} catch (Exception $e) {}

// 4. Staff & Payroll Metrics
$activeStaffCount = 0;
$payrollMonthlyCost = 0;
$payrollPaidCount = 0;
try {
    $activeStaffCount = (int)$db->query("SELECT COUNT(*) FROM staff WHERE status = 'Active'")->fetchColumn();
    
    $payrollStats = $db->query("
        SELECT 
            COALESCE(SUM(sd.net_salary),0) as total_paid,
            COUNT(sd.id) as count_paid
        FROM salary_details sd 
        JOIN salary_processing sp ON sd.processing_id = sp.id 
        WHERE sd.payment_status = 'Paid' AND sp.month = '$currentMonth' AND sp.year = '$currentYear'
    ")->fetch(PDO::FETCH_ASSOC);

    if ($payrollStats) {
        $payrollMonthlyCost = (float)$payrollStats['total_paid'];
        $payrollPaidCount   = (int)$payrollStats['count_paid'];
    }
} catch (Exception $e) {}

// 5. Category-wise Expense Breakdown for Hub Visuals
$topExpenseCategories = [];
try {
    $topExpenseCategories = $db->query("
        SELECT c.name, COALESCE(SUM(e.amount),0) as total
        FROM expense_categories c
        JOIN expenses e ON c.id = e.category_id
        GROUP BY c.id
        ORDER BY total DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 6. Class-wise Student Distribution
$classStudentCounts = [];
try {
    $classStudentCounts = $db->query("
        SELECT c.class_name, COUNT(s.id) as student_count
        FROM classes c
        LEFT JOIN students s ON c.id = s.class_id AND s.status = 'Active'
        GROUP BY c.id
        ORDER BY c.id ASC
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

:root {
    --hub-font: 'Outfit', sans-serif;
    --hub-primary: #3b82f6;
    --hub-indigo: #4f46e5;
    --hub-dark: #0f172a;
    --hub-card-bg: #ffffff;
    --hub-border: #e2e8f0;
    --hub-radius: 16px;
    --hub-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.04), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
}

body {
    font-family: var(--hub-font);
    background-color: #f8fafc;
}

/* Hero Header */
.hub-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
    border-radius: var(--hub-radius);
    padding: 2.25rem 2rem;
    color: #ffffff;
    box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.3);
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
}

.hub-hero-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 450px;
    height: 450px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.hub-hero-title {
    font-weight: 700;
    font-size: 2rem;
    letter-spacing: -0.025em;
}

.hub-kpi-card {
    background: var(--hub-card-bg);
    border: 1px solid var(--hub-border);
    border-radius: var(--hub-radius);
    padding: 1.35rem 1.25rem;
    box-shadow: var(--hub-shadow);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    height: 100%;
}

.hub-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 30px -5px rgba(15, 23, 42, 0.08);
}

.hub-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}

.hub-kpi-val {
    font-weight: 700;
    font-size: 1.6rem;
    color: var(--hub-dark);
    line-height: 1.2;
    margin-top: 0.35rem;
}

/* Submodule Cards */
.sub-report-card {
    background: #ffffff;
    border: 1px solid var(--hub-border);
    border-radius: var(--hub-radius);
    padding: 1.25rem;
    box-shadow: var(--hub-shadow);
    transition: all 0.2s ease;
    text-decoration: none !important;
    display: block;
    height: 100%;
}

.sub-report-card:hover {
    transform: translateY(-4px);
    border-color: #a5b4fc;
    box-shadow: 0 15px 25px -5px rgba(79, 70, 229, 0.12);
}

.sub-report-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.progress-thin {
    height: 6px;
    border-radius: 4px;
    background-color: #e2e8f0;
}

.nav-tabs-custom {
    border-bottom: 2px solid #e2e8f0;
}

.nav-tabs-custom .nav-link {
    border: none;
    color: #64748b;
    font-weight: 600;
    padding: 0.75rem 1.25rem;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
}

.nav-tabs-custom .nav-link.active {
    color: var(--hub-indigo);
    background: transparent;
    border-bottom-color: var(--hub-indigo);
}

@media print {
    body { background: #fff !important; }
    .no-print, .btn, nav, header, sidebar { display: none !important; }
    .hub-hero-card { background: #0f172a !important; color: #fff !important; }
    .hub-kpi-card, .card { break-inside: avoid; border: 1px solid #ccc !important; }
}
</style>

<div class="container-fluid px-0">

    <!-- Hero Header -->
    <div class="hub-hero-card">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small fw-semibold">
                        <i class="fa-solid fa-signal text-success me-1"></i> Compiled in Real-Time
                    </span>
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small">
                        Session <?php echo date('Y'); ?>
                    </span>
                </div>
                <h1 class="hub-hero-title mb-2">Reports & Intelligence Hub</h1>
                <p class="text-white-50 mb-0 leading-relaxed" style="max-width: 680px;">
                    Centralized command metrics aggregating academic evaluations, revenue & expenditure balances, daily attendance rates, staff payroll, and custom audit queries.
                </p>
            </div>
            <div class="col-lg-5 text-lg-end mt-4 mt-lg-0 no-print">
                <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end">
                    <button class="btn btn-light fw-bold text-dark px-4 py-2 rounded-3 shadow-sm" onclick="exportHubSummaryCSV()">
                        <i class="fa-solid fa-file-csv text-primary me-2"></i>Export Master CSV
                    </button>
                    <button class="btn btn-outline-light px-3 py-2 rounded-3" onclick="window.print()">
                        <i class="fa-solid fa-print me-2"></i>Print Dossier
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Search & Hub Navigation -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-7">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="hubSearchInput" class="form-control border-start-0 ps-0" placeholder="Search report metrics, modules (e.g., Fees, Attendance, P&L, Pass Rate)...">
                    </div>
                </div>
                <div class="col-md-5 text-md-end">
                    <span class="text-muted small me-2">Shortcuts:</span>
                    <a href="custom.php" class="btn btn-sm btn-outline-primary rounded-pill me-1"><i class="fa-solid fa-sliders me-1"></i> Custom Builder</a>
                    <a href="accounts.php" class="btn btn-sm btn-outline-success rounded-pill me-1"><i class="fa-solid fa-scale-balanced me-1"></i> Financials</a>
                    <a href="examination.php" class="btn btn-sm btn-outline-purple rounded-pill" style="color:#8b5cf6; border-color:#8b5cf6;"><i class="fa-solid fa-graduation-cap me-1"></i> Exams</a>
                </div>
            </div>
        </div>
    </div>

    <!-- 4 Core Performance Pillars (KPI Cards) -->
    <div class="row g-3 mb-4">
        <!-- 1. ACADEMIC PILLAR -->
        <div class="col-xl-3 col-md-6">
            <div class="hub-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Academic Pillar</span>
                    <div class="hub-icon-box bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                </div>
                <div class="hub-kpi-val"><?php echo number_format($totalStudents); ?> <span class="fs-6 fw-normal text-muted">Students</span></div>
                <div class="mt-2 text-muted small">
                    <span class="fw-bold text-dark"><?php echo $totalClasses; ?> Classes</span> | <?php echo $totalSections; ?> Sections
                </div>
                <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Exam Pass Rate</span>
                    <span class="badge bg-primary bg-opacity-10 text-primary fw-bold"><?php echo $examPassRate; ?>% Passed</span>
                </div>
            </div>
        </div>

        <!-- 2. FINANCIAL PILLAR -->
        <div class="col-xl-3 col-md-6">
            <div class="hub-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Financial Pillar</span>
                    <div class="hub-icon-box bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                </div>
                <div class="hub-kpi-val text-success">Rs. <?php echo number_format($monthlyIncome, 0); ?></div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-arrow-down-long me-1 text-danger"></i> Monthly Exp: <span class="fw-bold text-danger">Rs. <?php echo number_format($monthlyExpenses, 0); ?></span>
                </div>
                <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Collection Rate</span>
                    <span class="badge bg-success bg-opacity-10 text-success fw-bold"><?php echo $feeEfficiencyPct; ?>% Fee Paid</span>
                </div>
            </div>
        </div>

        <!-- 3. ATTENDANCE PILLAR -->
        <div class="col-xl-3 col-md-6">
            <div class="hub-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Attendance Pillar</span>
                    <div class="hub-icon-box bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                </div>
                <div class="hub-kpi-val text-info"><?php echo $studentPresentPct; ?>% <span class="fs-6 fw-normal text-muted">Students</span></div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-user-check me-1 text-success"></i> <?php echo number_format($studentPresentCount); ?> Present Today
                </div>
                <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Staff Today</span>
                    <span class="badge bg-info bg-opacity-10 text-info fw-bold"><?php echo $staffPresentCount; ?> / <?php echo $staffTotalCount; ?> Present</span>
                </div>
            </div>
        </div>

        <!-- 4. STAFF & PAYROLL PILLAR -->
        <div class="col-xl-3 col-md-6">
            <div class="hub-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Staff & Payroll</span>
                    <div class="hub-icon-box bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-users-gear"></i>
                    </div>
                </div>
                <div class="hub-kpi-val"><?php echo number_format($activeStaffCount); ?> <span class="fs-6 fw-normal text-muted">Active Staff</span></div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-money-check-dollar me-1 text-warning"></i> Payroll: <span class="fw-bold text-dark">Rs. <?php echo number_format($payrollMonthlyCost, 0); ?></span>
                </div>
                <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Disbursed Staff</span>
                    <span class="badge bg-warning bg-opacity-10 text-warning-dark fw-bold"><?php echo $payrollPaidCount; ?> Salaries Paid</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Interactive Visual Breakdown Panels -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <ul class="nav nav-tabs nav-tabs-custom card-header-tabs" id="hubTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="academic-tab" data-bs-toggle="tab" data-bs-target="#academic-panel" type="button">
                        <i class="fa-solid fa-graduation-cap me-2"></i>Academic & Exam Breakdown
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="financial-tab" data-bs-toggle="tab" data-bs-target="#financial-panel" type="button">
                        <i class="fa-solid fa-scale-balanced me-2"></i>Financial & Cashflow
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance-panel" type="button">
                        <i class="fa-solid fa-clock-rotate-left me-2"></i>Attendance & Staff
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content" id="hubTabsContent">
                
                <!-- TAB 1: ACADEMIC -->
                <div class="tab-pane fade show active" id="academic-panel" role="tabpanel">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-6">
                            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-users-line text-primary me-2"></i>Class Student Enrollment Density</h6>
                            <?php if (empty($classStudentCounts)): ?>
                                <p class="text-muted small">No class enrollment data logged.</p>
                            <?php else: foreach ($classStudentCounts as $cls): 
                                $cCount = (int)$cls['student_count'];
                                $cPct = $totalStudents > 0 ? ($cCount / $totalStudents) * 100 : 0;
                            ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-semibold text-dark small"><?php echo sanitize($cls['class_name']); ?></span>
                                        <span class="small text-muted fw-bold"><?php echo $cCount; ?> Students (<?php echo number_format($cPct, 1); ?>%)</span>
                                    </div>
                                    <div class="progress progress-thin">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo min(100, max(5, $cPct)); ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>

                        <div class="col-lg-6">
                            <div class="bg-light p-4 rounded-4 border">
                                <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-award text-warning me-2"></i>Academic Audit Highlights</h6>
                                <div class="d-flex align-items-center justify-content-between p-3 bg-white rounded-3 border mb-2">
                                    <div>
                                        <div class="fw-bold text-dark">Overall Exam Pass Rate</div>
                                        <small class="text-muted">Based on evaluation thresholds</small>
                                    </div>
                                    <span class="badge bg-success px-3 py-2 fs-6"><?php echo $examPassRate; ?>%</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between p-3 bg-white rounded-3 border mb-2">
                                    <div>
                                        <div class="fw-bold text-dark">Total Registered Classes</div>
                                        <small class="text-muted">Active educational grade tiers</small>
                                    </div>
                                    <span class="fw-bold text-primary fs-6"><?php echo $totalClasses; ?> Classes</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between p-3 bg-white rounded-3 border">
                                    <div>
                                        <div class="fw-bold text-dark">Class Sections Count</div>
                                        <small class="text-muted">Configured campus divisions</small>
                                    </div>
                                    <span class="fw-bold text-dark fs-6"><?php echo $totalSections; ?> Sections</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: FINANCIAL -->
                <div class="tab-pane fade" id="financial-panel" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-chart-pie text-danger me-2"></i>Top Expense Categories Breakdown</h6>
                            <?php if (empty($topExpenseCategories)): ?>
                                <p class="text-muted small">No category expense records found.</p>
                            <?php else: foreach ($topExpenseCategories as $expCat): 
                                $catAmt = (float)$expCat['total'];
                                $expPct = $monthlyExpenses > 0 ? ($catAmt / $monthlyExpenses) * 100 : 0;
                            ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-semibold text-dark small"><?php echo sanitize($expCat['name']); ?></span>
                                        <span class="fw-bold text-danger small">Rs. <?php echo number_format($catAmt, 2); ?></span>
                                    </div>
                                    <div class="progress progress-thin">
                                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo min(100, max(5, $expPct)); ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>

                        <div class="col-lg-6">
                            <div class="bg-light p-4 rounded-4 border">
                                <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>Financial Liquidity Overview</h6>
                                <div class="d-flex justify-content-between align-items-center p-3 bg-white rounded-3 border mb-2">
                                    <span class="text-muted small fw-semibold">Net Operating Balance (This Month):</span>
                                    <span class="fw-bold <?php echo $netMonthlyProfit >= 0 ? 'text-success' : 'text-danger'; ?> fs-6">
                                        Rs. <?php echo number_format($netMonthlyProfit, 2); ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-3 bg-white rounded-3 border mb-2">
                                    <span class="text-muted small fw-semibold">Pending Outstanding Dues:</span>
                                    <span class="fw-bold text-danger fs-6">Rs. <?php echo number_format($pendingFeesTotal, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-3 bg-white rounded-3 border">
                                    <span class="text-muted small fw-semibold">Total Revenue Collected (YTD):</span>
                                    <span class="fw-bold text-dark fs-6">Rs. <?php echo number_format($totalCollectedYTD, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: ATTENDANCE & STAFF -->
                <div class="tab-pane fade" id="attendance-panel" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="p-4 bg-light rounded-4 border">
                                <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-user-check text-info me-2"></i>Student Attendance Status (Today)</h6>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">Present Students Rate</span>
                                    <span class="fw-bold text-success fs-5"><?php echo $studentPresentPct; ?>%</span>
                                </div>
                                <div class="progress progress-thin mb-3" style="height: 10px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $studentPresentPct; ?>%"></div>
                                </div>
                                <div class="row text-center g-2 pt-2 border-top">
                                    <div class="col-6">
                                        <div class="fw-bold text-dark"><?php echo number_format($studentPresentCount); ?></div>
                                        <small class="text-muted">Present</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="fw-bold text-danger"><?php echo number_format(max(0, $studentTotalAtt - $studentPresentCount)); ?></div>
                                        <small class="text-muted">Absent / Leave</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="p-4 bg-light rounded-4 border">
                                <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-users-gear text-warning me-2"></i>Staff & Payroll Status</h6>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">Staff Presence Rate</span>
                                    <span class="fw-bold text-info fs-5"><?php echo $staffPresentPct; ?>%</span>
                                </div>
                                <div class="progress progress-thin mb-3" style="height: 10px;">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $staffPresentPct; ?>%"></div>
                                </div>
                                <div class="row text-center g-2 pt-2 border-top">
                                    <div class="col-6">
                                        <div class="fw-bold text-dark"><?php echo $staffPresentCount; ?> / <?php echo $staffTotalCount; ?></div>
                                        <small class="text-muted">Staff Present</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="fw-bold text-warning-dark">Rs. <?php echo number_format($payrollMonthlyCost, 0); ?></div>
                                        <small class="text-muted">Payroll Disbursed</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Submodules Report Launcher Cards -->
    <div class="mb-4">
        <h5 class="fw-bold text-dark mb-3">
            <i class="fa-solid fa-cubes text-primary me-2"></i>Specialized Report Modules
        </h5>

        <div class="row g-3" id="submoduleGrid">

            <!-- 1. Student Reports -->
            <div class="col-xl-4 col-md-6 hub-item" data-title="student reports demographics list alumni directory">
                <a href="students.php" class="sub-report-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="sub-report-icon bg-primary bg-opacity-10 text-primary">
                                <i class="fa-solid fa-user-graduate"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Student Reports</h6>
                                <span class="text-muted small">Demographics, enrollment & alumni</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-arrow-right text-muted"></i>
                    </div>
                </a>
            </div>

            <!-- 2. Attendance Reports -->
            <div class="col-xl-4 col-md-6 hub-item" data-title="attendance reports history daily monthly student staff">
                <a href="attendance.php" class="sub-report-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="sub-report-icon bg-success bg-opacity-10 text-success">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Attendance Reports</h6>
                                <span class="text-muted small">Student & staff daily history logs</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-arrow-right text-muted"></i>
                    </div>
                </a>
            </div>

            <!-- 3. Fee & Collection Reports -->
            <div class="col-xl-4 col-md-6 hub-item" data-title="fee reports collection pending dues concessions ledger">
                <a href="fees.php" class="sub-report-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="sub-report-icon bg-info bg-opacity-10 text-info">
                                <i class="fa-solid fa-money-bill-wave"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Fee & Collection Reports</h6>
                                <span class="text-muted small">Paid receipts, arrears & concessions</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-arrow-right text-muted"></i>
                    </div>
                </a>
            </div>

            <!-- 4. Accounts & Cash Reports -->
            <div class="col-xl-4 col-md-6 hub-item" data-title="accounts reports p&l profit loss cashbook expenses audit">
                <a href="accounts.php" class="sub-report-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="sub-report-icon bg-danger bg-opacity-10 text-danger">
                                <i class="fa-solid fa-scale-balanced"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Accounts & Cash Reports</h6>
                                <span class="text-muted small">P&L statements, cashbook & audits</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-arrow-right text-muted"></i>
                    </div>
                </a>
            </div>

            <!-- 5. Examination Reports -->
            <div class="col-xl-4 col-md-6 hub-item" data-title="examination reports marks pass rate class positions merit list">
                <a href="examination.php" class="sub-report-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="sub-report-icon bg-purple bg-opacity-10 text-purple" style="color:#8b5cf6;">
                                <i class="fa-solid fa-graduation-cap"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Examination Reports</h6>
                                <span class="text-muted small">Merit ranks, subject pass rates & grades</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-arrow-right text-muted"></i>
                    </div>
                </a>
            </div>

            <!-- 6. Staff Directory Reports -->
            <div class="col-xl-4 col-md-6 hub-item" data-title="staff reports hr directory designations teaching non teaching">
                <a href="staff.php" class="sub-report-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="sub-report-icon bg-dark bg-opacity-10 text-dark">
                                <i class="fa-solid fa-users-gear"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Staff & HR Reports</h6>
                                <span class="text-muted small">Faculty lists, designations & tenure</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-arrow-right text-muted"></i>
                    </div>
                </a>
            </div>

            <!-- 7. Payroll & Salary Reports -->
            <div class="col-xl-4 col-md-6 hub-item" data-title="payroll reports salaries allowances deductions bonuses processing">
                <a href="payroll.php" class="sub-report-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="sub-report-icon bg-warning bg-opacity-10 text-warning-dark">
                                <i class="fa-solid fa-wallet"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Payroll & Salary Reports</h6>
                                <span class="text-muted small">Disbursed salaries, bonuses & tax logs</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-arrow-right text-muted"></i>
                    </div>
                </a>
            </div>

            <!-- 8. Custom Query Builder -->
            <div class="col-xl-4 col-md-6 hub-item" data-title="custom query builder reports custom excel constructor">
                <a href="custom.php" class="sub-report-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="sub-report-icon bg-indigo bg-opacity-10 text-indigo" style="color:#4f46e5;">
                                <i class="fa-solid fa-sliders"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Custom Query Builder</h6>
                                <span class="text-muted small">Build dynamic spreadsheet exports</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-arrow-right text-muted"></i>
                    </div>
                </a>
            </div>

        </div>
    </div>

</div>

<script>
function exportHubSummaryCSV() {
    let csv = "Indus Grammar School ERP - Master Executive Reports Hub\n";
    csv += "Metric Category,Value,Details\n";
    csv += `Total Active Students,<?php echo $totalStudents; ?>,Registered enrolled students\n`;
    csv += `Exam Pass Rate,<?php echo $examPassRate; ?>%,Overall pass percentage\n`;
    csv += `Monthly Income,PKR <?php echo number_format($monthlyIncome, 2); ?>,Current month fee collections\n`;
    csv += `Monthly Expenses,PKR <?php echo number_format($monthlyExpenses, 2); ?>,Current month operational expenditure\n`;
    csv += `Net Operating Balance,PKR <?php echo number_format($netMonthlyProfit, 2); ?>,Monthly net position\n`;
    csv += `Student Present Today,<?php echo $studentPresentPct; ?>%,Count: <?php echo $studentPresentCount; ?>\n`;
    csv += `Staff Present Today,<?php echo $staffPresentCount; ?> / <?php echo $staffTotalCount; ?>,Presence rate: <?php echo $staffPresentPct; ?>%\n`;
    csv += `Active Staff Count,<?php echo $activeStaffCount; ?>,Registered active faculty & staff\n`;
    csv += `Current Month Payroll,PKR <?php echo number_format($payrollMonthlyCost, 2); ?>,Disbursed salaries\n`;

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", `Executive_Reports_Hub_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("hubSearchInput");
    if (searchInput) {
        searchInput.addEventListener("input", function() {
            const q = this.value.toLowerCase().trim();
            const items = document.querySelectorAll(".hub-item");
            items.forEach(item => {
                const title = item.dataset.title || "";
                if (!q || title.includes(q)) {
                    item.classList.remove("d-none");
                } else {
                    item.classList.add("d-none");
                }
            });
        });
    }
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
