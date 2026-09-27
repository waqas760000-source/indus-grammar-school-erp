<?php
/**
 * Indus Grammar School ERP - Payroll Ledger & Salary Audit Reports
 * Version 4.0.0 (Executive Compensation & Salary Intelligence Suite)
 */

$pageTitle = 'Payroll Reports Panel';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Selectors
$departments = [];
$employees = [];
try {
    $departments = $db->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
    $employees = $db->query("SELECT id, employee_no, first_name, last_name FROM staff WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Filter parameters
$selectedReport = sanitize($_GET['report_type'] ?? 'payroll_summary');
$selectedMonth  = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear   = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedDept   = sanitize($_GET['department'] ?? '');
$selectedEmp    = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;
$searchKeyword  = sanitize($_GET['q'] ?? '');

$reportTitle = "Payroll Audit Ledger";
$reportData = [];

// Overall Payroll Range KPIs
$kpiNetSalaryTotal = 0;
$kpiPaidCount = 0;
$kpiPendingCount = 0;
$kpiTotalAllowances = 0;
$kpiTotalBonuses = 0;
$kpiTotalDeductions = 0;

try {
    // Summary metrics for selected month/year
    $pStats = $db->query("
        SELECT 
            COALESCE(SUM(sd.net_salary),0) as total_net,
            COALESCE(SUM(sd.allowances),0) as total_allow,
            COALESCE(SUM(sd.bonus),0) as total_bonus,
            COALESCE(SUM(sd.deductions + sd.advance_salary_deduction),0) as total_deduct,
            SUM(CASE WHEN sd.payment_status = 'Paid' THEN 1 ELSE 0 END) as count_paid,
            SUM(CASE WHEN sd.payment_status = 'Pending' THEN 1 ELSE 0 END) as count_pending
        FROM salary_details sd
        JOIN salary_processing sp ON sd.processing_id = sp.id
        WHERE sp.month = $selectedMonth AND sp.year = $selectedYear
    ")->fetch(PDO::FETCH_ASSOC);

    if ($pStats) {
        $kpiNetSalaryTotal = (float)$pStats['total_net'];
        $kpiTotalAllowances = (float)$pStats['total_allow'];
        $kpiTotalBonuses    = (float)$pStats['total_bonus'];
        $kpiTotalDeductions = (float)$pStats['total_deduct'];
        $kpiPaidCount       = (int)$pStats['count_paid'];
        $kpiPendingCount    = (int)$pStats['count_pending'];
    }

    switch ($selectedReport) {
        
        case 'salary_report':
        case 'paid_salaries':
        case 'pending_salaries':
        case 'payroll_summary':
            $statusFilter = '';
            if ($selectedReport === 'paid_salaries') {
                $reportTitle = "Processed & Disbursed Salaries Ledger (" . date('F', mktime(0, 0, 0, $selectedMonth, 1)) . " $selectedYear)";
                $statusFilter = "AND sd.payment_status = 'Paid'";
            } else if ($selectedReport === 'pending_salaries') {
                $reportTitle = "Pending / Unpaid Salaries Ledger (" . date('F', mktime(0, 0, 0, $selectedMonth, 1)) . " $selectedYear)";
                $statusFilter = "AND sd.payment_status = 'Pending'";
            } else {
                $reportTitle = "Consolidated Monthly Payroll Sheet (" . date('F', mktime(0, 0, 0, $selectedMonth, 1)) . " $selectedYear)";
            }

            $sql = "
                SELECT sd.*, s.first_name, s.last_name, s.employee_no, s.department, s.designation,
                       sp.month, sp.year
                FROM salary_details sd
                JOIN salary_processing sp ON sd.processing_id = sp.id
                JOIN staff s ON sd.staff_id = s.id
                WHERE sp.month = :month AND sp.year = :year
                $statusFilter
            ";
            $params = ['month' => $selectedMonth, 'year' => $selectedYear];

            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($selectedEmp > 0) {
                $sql .= " AND sd.staff_id = :sid";
                $params['sid'] = $selectedEmp;
            }
            if ($searchKeyword !== '') {
                $sql .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.employee_no LIKE :q)";
                $params['q'] = '%' . $searchKeyword . '%';
            }
            $sql .= " ORDER BY s.first_name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'allowance_report':
            $reportTitle = "Staff Allowance Distributions Log";
            $sql = "
                SELECT a.*, s.first_name, s.last_name, s.employee_no, s.department, s.designation
                FROM allowances a
                JOIN staff s ON a.staff_id = s.id
                WHERE a.status = 'Active'
            ";
            $params = [];
            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($selectedEmp > 0) {
                $sql .= " AND a.staff_id = :sid";
                $params['sid'] = $selectedEmp;
            }
            if ($searchKeyword !== '') {
                $sql .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.employee_no LIKE :q OR a.allowance_type LIKE :q)";
                $params['q'] = '%' . $searchKeyword . '%';
            }
            $sql .= " ORDER BY a.amount DESC, s.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'deduction_report':
            $reportTitle = "Payroll Deductions & Penalties Log";
            $sql = "
                SELECT d.*, s.first_name, s.last_name, s.employee_no, s.department, s.designation
                FROM deductions d
                JOIN staff s ON d.staff_id = s.id
                WHERE d.status = 'Active'
            ";
            $params = [];
            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($selectedEmp > 0) {
                $sql .= " AND d.staff_id = :sid";
                $params['sid'] = $selectedEmp;
            }
            if ($searchKeyword !== '') {
                $sql .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.employee_no LIKE :q OR d.deduction_type LIKE :q)";
                $params['q'] = '%' . $searchKeyword . '%';
            }
            $sql .= " ORDER BY d.amount DESC, s.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'advance_report':
            $reportTitle = "Advance Salary Loans & Recovery Ledger";
            $sql = "
                SELECT adv.*, s.first_name, s.last_name, s.employee_no, s.department, s.designation
                FROM advance_salary adv
                JOIN staff s ON adv.staff_id = s.id
                WHERE 1=1
            ";
            $params = [];
            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($selectedEmp > 0) {
                $sql .= " AND adv.staff_id = :sid";
                $params['sid'] = $selectedEmp;
            }
            if ($searchKeyword !== '') {
                $sql .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.employee_no LIKE :q)";
                $params['q'] = '%' . $searchKeyword . '%';
            }
            $sql .= " ORDER BY adv.advance_date DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'bonus_report':
            $reportTitle = "Employee Bonus & Performance Incentives Log";
            $sql = "
                SELECT b.*, s.first_name, s.last_name, s.employee_no, s.department, s.designation
                FROM bonuses b
                JOIN staff s ON b.staff_id = s.id
                WHERE b.status = 'Active'
            ";
            $params = [];
            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($selectedEmp > 0) {
                $sql .= " AND b.staff_id = :sid";
                $params['sid'] = $selectedEmp;
            }
            if ($searchKeyword !== '') {
                $sql .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.employee_no LIKE :q OR b.bonus_type LIKE :q)";
                $params['q'] = '%' . $searchKeyword . '%';
            }
            $sql .= " ORDER BY b.date_earned DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch (Exception $e) {
    error_log("Payroll report error: " . $e->getMessage());
}

$evaluatedRows = count($reportData);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

:root {
    --pr-font: 'Outfit', sans-serif;
    --pr-primary: #f59e0b;
    --pr-dark: #0f172a;
    --pr-card-bg: #ffffff;
    --pr-border: #e2e8f0;
    --pr-radius: 16px;
    --pr-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.04), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
}

body {
    font-family: var(--pr-font);
    background-color: #f8fafc;
}

.pr-hero-card {
    background: linear-gradient(135deg, #78350f 0%, #b45309 50%, #d97706 100%);
    border-radius: var(--pr-radius);
    padding: 2.25rem 2rem;
    color: #ffffff;
    box-shadow: 0 20px 25px -5px rgba(180, 83, 9, 0.25);
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
}

.pr-hero-card::before {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.pr-kpi-card {
    background: var(--pr-card-bg);
    border: 1px solid var(--pr-border);
    border-radius: var(--pr-radius);
    padding: 1.35rem 1.25rem;
    box-shadow: var(--pr-shadow);
    height: 100%;
    transition: transform 0.2s ease;
}

.pr-kpi-card:hover {
    transform: translateY(-3px);
}

.pr-kpi-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.pr-kpi-val {
    font-weight: 700;
    font-size: 1.55rem;
    color: var(--pr-dark);
    line-height: 1.2;
    margin-top: 0.35rem;
}

.custom-table-card {
    background: var(--pr-card-bg);
    border: 1px solid var(--pr-border);
    border-radius: var(--pr-radius);
    box-shadow: var(--pr-shadow);
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
    border-bottom: 1px solid var(--pr-border);
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

@media print {
    body { background: #fff !important; }
    .no-print, .btn, nav, header, sidebar { display: none !important; }
    .pr-hero-card { background: #78350f !important; color: #fff !important; }
    #reportPrintArea { position: static !important; }
}
</style>

<div class="container-fluid px-0">

    <!-- Executive Hero Header -->
    <div class="pr-hero-card">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small fw-semibold">
                        <i class="fa-solid fa-wallet me-1 text-warning"></i> Compensation Audit Suite
                    </span>
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small">
                        <?php echo date('F', mktime(0, 0, 0, $selectedMonth, 1)); ?> <?php echo $selectedYear; ?>
                    </span>
                </div>
                <h1 class="fw-bold mb-2" style="font-size:1.95rem; letter-spacing:-0.02em;"><?php echo sanitize($reportTitle); ?></h1>
                <p class="text-white-50 mb-0 leading-relaxed" style="max-width: 650px;">
                    Construct employee payroll summaries, check processed salaries, allowance distributions, advance recovery balances, and bonus incentives.
                </p>
            </div>
            <div class="col-lg-5 text-lg-end mt-4 mt-lg-0 no-print">
                <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end">
                    <button class="btn btn-light fw-bold text-dark px-4 py-2 rounded-3 shadow-sm" onclick="exportToExcel()">
                        <i class="fa-solid fa-file-excel text-success me-2"></i>Export Excel / CSV
                    </button>
                    <button class="btn btn-outline-light px-3 py-2 rounded-3" onclick="window.print()">
                        <i class="fa-solid fa-print me-2"></i>Print Ledger
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-light px-3 py-2 rounded-3">
                        <i class="fa-solid fa-arrow-left me-2"></i>Hub
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end" id="filterForm">
                
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-dark">Report Focus / Category</label>
                    <select class="form-select" name="report_type" id="reportSelect" onchange="toggleFilterFields(); this.form.submit();">
                        <option value="payroll_summary" <?php echo $selectedReport === 'payroll_summary' ? 'selected' : ''; ?>>Monthly Payroll Summary</option>
                        <option value="paid_salaries" <?php echo $selectedReport === 'paid_salaries' ? 'selected' : ''; ?>>Disbursed & Paid Salaries</option>
                        <option value="pending_salaries" <?php echo $selectedReport === 'pending_salaries' ? 'selected' : ''; ?>>Pending / Unpaid Salaries</option>
                        <option value="allowance_report" <?php echo $selectedReport === 'allowance_report' ? 'selected' : ''; ?>>Allowance Distributions</option>
                        <option value="deduction_report" <?php echo $selectedReport === 'deduction_report' ? 'selected' : ''; ?>>Deductions & Penalties Log</option>
                        <option value="advance_report" <?php echo $selectedReport === 'advance_report' ? 'selected' : ''; ?>>Advance Salary Loans & Recovery</option>
                        <option value="bonus_report" <?php echo $selectedReport === 'bonus_report' ? 'selected' : ''; ?>>Bonus Incentives Statement</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-3 filter-field" id="monthField">
                    <label class="form-label small fw-bold text-dark">Payroll Month</label>
                    <select class="form-select" name="month">
                        <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $selectedMonth === $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-lg-1 col-md-3 filter-field" id="yearField">
                    <label class="form-label small fw-bold text-dark">Year</label>
                    <input type="number" class="form-control" name="year" value="<?php echo $selectedYear; ?>" min="2020" max="2035">
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-bold text-dark">Department</label>
                    <select class="form-select" name="department">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $selectedDept === $dept ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-bold text-dark">Employee Staff</label>
                    <select class="form-select" name="staff_id">
                        <option value="0">All Staff Members</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo $selectedEmp === (int)$emp['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (#' . $emp['employee_no'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-bold text-dark">Search Keyword</label>
                    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="Employee #, name...">
                </div>

                <div class="col-12 text-end">
                    <a href="payroll.php" class="btn btn-outline-secondary px-4 me-2"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
                    <button type="submit" class="btn btn-warning text-dark fw-bold px-5"><i class="fa-solid fa-magnifying-glass me-2"></i>Compile Payroll Ledger</button>
                </div>

            </form>
        </div>
    </div>

    <!-- Payroll Range KPI Analytics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="pr-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Net Payroll Disbursed</span>
                    <div class="pr-kpi-icon bg-warning bg-opacity-10 text-warning-dark">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                </div>
                <div class="pr-kpi-val text-dark">Rs. <?php echo number_format($kpiNetSalaryTotal, 0); ?></div>
                <div class="mt-2 text-muted small">
                    <span class="badge bg-success bg-opacity-10 text-success fw-bold"><?php echo $kpiPaidCount; ?> Paid</span>
                    <?php if ($kpiPendingCount > 0): ?>
                        <span class="badge bg-warning bg-opacity-10 text-warning-dark fw-bold ms-1"><?php echo $kpiPendingCount; ?> Pending</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="pr-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Allowances & Bonuses</span>
                    <div class="pr-kpi-icon bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-circle-plus"></i>
                    </div>
                </div>
                <div class="pr-kpi-val text-success">Rs. <?php echo number_format($kpiTotalAllowances + $kpiTotalBonuses, 0); ?></div>
                <div class="mt-2 text-muted small">
                    Allow: <span class="fw-bold">Rs. <?php echo number_format($kpiTotalAllowances, 0); ?></span> | Bonus: <span class="fw-bold">Rs. <?php echo number_format($kpiTotalBonuses, 0); ?></span>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="pr-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Deductions & Recoveries</span>
                    <div class="pr-kpi-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fa-solid fa-circle-minus"></i>
                    </div>
                </div>
                <div class="pr-kpi-val text-danger">Rs. <?php echo number_format($kpiTotalDeductions, 0); ?></div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-scissors me-1 text-danger"></i> Penalties & advance loan installments
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="pr-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Processed Employees</span>
                    <div class="pr-kpi-icon bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-users-gear"></i>
                    </div>
                </div>
                <div class="pr-kpi-val text-info"><?php echo number_format($evaluatedRows); ?></div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-receipt me-1"></i> Records in active view
                </div>
            </div>
        </div>
    </div>

    <!-- Main Output Card -->
    <div class="custom-table-card shadow-sm mb-4" id="reportPrintArea">
        
        <!-- Print Header -->
        <div class="p-4 text-center d-none d-print-block border-bottom">
            <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h2>
            <h4 class="text-secondary fw-semibold mb-1"><?php echo sanitize($reportTitle); ?></h4>
            <div class="text-muted small">
                Printed Date: <?php echo date('d-M-Y H:i'); ?> | Month: <?php echo date('F', mktime(0, 0, 0, $selectedMonth, 1)); ?> <?php echo $selectedYear; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                
                <!-- 1. PAYROLL SUMMARY / SALARY / PAID / PENDING -->
                <?php if (in_array($selectedReport, ['payroll_summary', 'paid_salaries', 'pending_salaries'])): ?>
                    <thead>
                        <tr>
                            <th width="110">Employee #</th>
                            <th>Staff Member Name</th>
                            <th>Department</th>
                            <th class="text-end">Basic Salary</th>
                            <th class="text-end text-success">Allowances</th>
                            <th class="text-end text-danger">Deductions</th>
                            <th class="text-end text-info">Bonuses</th>
                            <th class="text-end fw-bold">Net Salary</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): $totBasic = $totAllow = $totDeduct = $totBonus = $totNet = 0; ?>
                            <tr><td colspan="9" class="text-center py-5 text-muted">No processed payroll records found matching the criteria.</td></tr>
                        <?php else: 
                            $totBasic = $totAllow = $totDeduct = $totBonus = $totNet = 0; 
                            foreach ($reportData as $row): 
                                $totBasic  += (float)$row['basic_salary'];
                                $totAllow  += (float)$row['allowances'];
                                $totDeduct += (float)($row['deductions'] + $row['advance_salary_deduction']);
                                $totBonus  += (float)$row['bonus'];
                                $totNet    += (float)$row['net_salary'];
                            ?>
                            <tr>
                                <td><code class="fw-bold text-primary">#<?php echo sanitize($row['employee_no']); ?></code></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                    <small class="text-muted text-xs"><?php echo sanitize($row['designation']); ?></small>
                                </td>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill"><?php echo sanitize($row['department']); ?></span></td>
                                <td class="text-end text-muted">Rs. <?php echo number_format((float)$row['basic_salary'], 2); ?></td>
                                <td class="text-end text-success fw-semibold">Rs. <?php echo number_format((float)$row['allowances'], 2); ?></td>
                                <td class="text-end text-danger fw-semibold">Rs. <?php echo number_format((float)($row['deductions'] + $row['advance_salary_deduction']), 2); ?></td>
                                <td class="text-end text-info fw-semibold">Rs. <?php echo number_format((float)$row['bonus'], 2); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format((float)$row['net_salary'], 2); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['payment_status'] === 'Paid' ? 'success' : 'warning'; ?> bg-opacity-10 text-<?php echo $row['payment_status'] === 'Paid' ? 'success' : 'warning-dark'; ?> px-3 py-1 rounded-pill fw-bold">
                                        <?php echo sanitize($row['payment_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="3">Total Realized Payroll:</td>
                                <td class="text-end">Rs. <?php echo number_format($totBasic, 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($totAllow, 2); ?></td>
                                <td class="text-end text-danger">Rs. <?php echo number_format($totDeduct, 2); ?></td>
                                <td class="text-end text-info">Rs. <?php echo number_format($totBonus, 2); ?></td>
                                <td class="text-end text-primary fs-5">Rs. <?php echo number_format($totNet, 2); ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 2. ALLOWANCES LIST -->
                <?php elseif ($selectedReport === 'allowance_report'): ?>
                    <thead>
                        <tr>
                            <th width="110">Employee #</th>
                            <th>Staff Member Name</th>
                            <th>Department</th>
                            <th>Allowance Category</th>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): $total = 0; ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No allowance disbursements logged.</td></tr>
                        <?php else: $total = 0; foreach ($reportData as $row): $total += (float)$row['amount']; ?>
                            <tr>
                                <td><code class="fw-bold text-primary">#<?php echo sanitize($row['employee_no']); ?></code></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                    <small class="text-muted text-xs"><?php echo sanitize($row['designation']); ?></small>
                                </td>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill"><?php echo sanitize($row['department']); ?></span></td>
                                <td><span class="badge bg-success bg-opacity-10 text-success px-3 py-1 rounded-pill fw-semibold"><?php echo sanitize($row['allowance_type']); ?></span></td>
                                <td class="small text-muted"><?php echo sanitize($row['description'] ?: '—'); ?></td>
                                <td class="text-end fw-bold text-success">Rs. <?php echo number_format((float)$row['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="5">Total Realized Allowances:</td>
                                <td class="text-end text-success fs-5">Rs. <?php echo number_format($total, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 3. DEDUCTIONS LIST -->
                <?php elseif ($selectedReport === 'deduction_report'): ?>
                    <thead>
                        <tr>
                            <th width="110">Employee #</th>
                            <th>Staff Member Name</th>
                            <th>Department</th>
                            <th>Deduction Category</th>
                            <th>Reason / Explanation</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): $total = 0; ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No deductions logs found.</td></tr>
                        <?php else: $total = 0; foreach ($reportData as $row): $total += (float)$row['amount']; ?>
                            <tr>
                                <td><code class="fw-bold text-primary">#<?php echo sanitize($row['employee_no']); ?></code></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                    <small class="text-muted text-xs"><?php echo sanitize($row['designation']); ?></small>
                                </td>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill"><?php echo sanitize($row['department']); ?></span></td>
                                <td><span class="badge bg-danger bg-opacity-10 text-danger px-3 py-1 rounded-pill fw-semibold"><?php echo sanitize($row['deduction_type']); ?></span></td>
                                <td class="small text-muted"><?php echo sanitize($row['reason'] ?: '—'); ?></td>
                                <td class="text-end fw-bold text-danger">Rs. <?php echo number_format((float)$row['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="5">Total Realized Deductions:</td>
                                <td class="text-end text-danger fs-5">Rs. <?php echo number_format($total, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 4. ADVANCES LIST -->
                <?php elseif ($selectedReport === 'advance_report'): ?>
                    <thead>
                        <tr>
                            <th width="110">Employee #</th>
                            <th>Staff Member Name</th>
                            <th class="text-center">Issued Date</th>
                            <th class="text-end">Disbursed Loan Amount</th>
                            <th class="text-end">Recovered Amount</th>
                            <th class="text-end text-danger">Outstanding Balance</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No advance salary loans configured.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td><code class="fw-bold text-primary">#<?php echo sanitize($row['employee_no']); ?></code></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                    <small class="text-muted text-xs"><?php echo sanitize($row['department']); ?></small>
                                </td>
                                <td class="text-center small fw-semibold"><?php echo date('d-M-Y', strtotime($row['advance_date'])); ?></td>
                                <td class="text-end text-muted">Rs. <?php echo number_format((float)$row['amount'], 2); ?></td>
                                <td class="text-end text-success fw-semibold">Rs. <?php echo number_format((float)$row['paid_amount'], 2); ?></td>
                                <td class="text-end fw-bold text-danger">Rs. <?php echo number_format((float)$row['remaining_balance'], 2); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Recovered' ? 'success' : 'warning'; ?> bg-opacity-10 text-<?php echo $row['status'] === 'Recovered' ? 'success' : 'warning-dark'; ?> px-3 py-1 rounded-pill fw-bold">
                                        <?php echo sanitize($row['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 5. BONUSES LIST -->
                <?php elseif ($selectedReport === 'bonus_report'): ?>
                    <thead>
                        <tr>
                            <th width="110">Employee #</th>
                            <th>Staff Member Name</th>
                            <th>Department</th>
                            <th>Bonus Category</th>
                            <th class="text-center">Date Earned</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): $total = 0; ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No bonuses configured.</td></tr>
                        <?php else: $total = 0; foreach ($reportData as $row): $total += (float)$row['amount']; ?>
                            <tr>
                                <td><code class="fw-bold text-primary">#<?php echo sanitize($row['employee_no']); ?></code></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                    <small class="text-muted text-xs"><?php echo sanitize($row['designation']); ?></small>
                                </td>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill"><?php echo sanitize($row['department']); ?></span></td>
                                <td><span class="badge bg-success bg-opacity-10 text-success px-3 py-1 rounded-pill fw-semibold"><?php echo sanitize($row['bonus_type']); ?></span></td>
                                <td class="text-center small fw-semibold"><?php echo date('d-M-Y', strtotime($row['date_earned'])); ?></td>
                                <td class="text-end fw-bold text-success">Rs. <?php echo number_format((float)$row['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="5">Total Incentives Disbursed:</td>
                                <td class="text-end text-success fs-5">Rs. <?php echo number_format($total, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                <?php endif; ?>

            </table>
        </div>
    </div>

</div>

<script>
function toggleFilterFields() {
    const reportType = document.getElementById("reportSelect").value;
    const monthField = document.getElementById("monthField");
    const yearField = document.getElementById("yearField");

    if (["payroll_summary", "paid_salaries", "pending_salaries"].includes(reportType)) {
        monthField.style.display = "block";
        yearField.style.display = "block";
    } else {
        monthField.style.display = "none";
        yearField.style.display = "none";
    }
}

function exportToExcel() {
    let table = document.getElementById("reportDataTable");
    if (!table) return;
    let html = table.outerHTML;
    let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
    let link = document.createElement("a");
    link.download = "<?php echo strtolower(str_replace(' ', '_', $reportTitle)); ?>_<?php echo date('Ymd'); ?>.xls";
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

document.addEventListener("DOMContentLoaded", function() {
    toggleFilterFields();
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>