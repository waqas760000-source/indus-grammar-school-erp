<?php
/**
 * Indus Grammar School ERP - Payroll Reports Console
 * Version 4.0.0 (Premium Redesign)
 */

$pageTitle = 'Payroll Reports';
$breadcrumbActive = 'Payroll';
include_once __DIR__ . '/../../includes/header.php';

// Access Control
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'accountant'])) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to access the payroll reports module.';
    redirect(APP_URL . '/dashboard.php');
}

$db = Database::getConnection();

// Default Filter Settings
$reportType    = sanitize($_GET['report_type'] ?? 'monthly_payroll');
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedDept  = sanitize($_GET['department'] ?? '');
$selectedEmp   = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;

// Load lists for filters
$departments = $db->query("SELECT DISTINCT department FROM staff WHERE status = 'Active' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
$staffList   = $db->query("SELECT id, employee_no, first_name, last_name, designation FROM staff WHERE status = 'Active' ORDER BY employee_no ASC")->fetchAll(PDO::FETCH_ASSOC);

// Perform query based on selected report type
$reportData = [];
$reportTitle = "Payroll Report";

try {
    switch ($reportType) {
        case 'monthly_payroll':
            $reportTitle = "Monthly Payroll Journal — " . date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear));
            $sql = "SELECT d.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
                    FROM salary_details d
                    JOIN salary_processing p ON d.processing_id = p.id
                    JOIN staff s ON d.staff_id = s.id
                    WHERE p.month = :month AND p.year = :year";
            $params = ['month' => $selectedMonth, 'year' => $selectedYear];
            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($selectedEmp > 0) {
                $sql .= " AND s.id = :emp";
                $params['emp'] = $selectedEmp;
            }
            $sql .= " ORDER BY s.employee_no ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'dept_wise':
            $reportTitle = "Department-Wise Payroll Summary — " . date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear));
            $sql = "SELECT s.department, 
                           COUNT(d.id) as staff_count,
                           SUM(d.basic_salary) as total_basic,
                           SUM(d.allowances) as total_allowances,
                           SUM(d.deductions) as total_deductions,
                           SUM(d.advance_salary_deduction) as total_advance,
                           SUM(d.bonus) as total_bonus,
                           SUM(d.net_salary) as total_net
                    FROM salary_details d
                    JOIN salary_processing p ON d.processing_id = p.id
                    JOIN staff s ON d.staff_id = s.id
                    WHERE p.month = :month AND p.year = :year";
            $params = ['month' => $selectedMonth, 'year' => $selectedYear];
            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            $sql .= " GROUP BY s.department ORDER BY s.department ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'salary_summary':
            $reportTitle = "Annual Salary Disbursement Ledger ($selectedYear)";
            $sql = "SELECT p.month, p.year,
                           COUNT(d.id) as staff_count,
                           SUM(d.basic_salary) as total_basic,
                           SUM(d.allowances) as total_allowances,
                           SUM(d.deductions) as total_deductions,
                           SUM(d.net_salary) as total_net,
                           SUM(CASE WHEN d.payment_status = 'Paid' THEN d.net_salary ELSE 0 END) as total_paid,
                           SUM(CASE WHEN d.payment_status = 'Pending' THEN d.net_salary ELSE 0 END) as total_pending
                    FROM salary_details d
                    JOIN salary_processing p ON d.processing_id = p.id
                    WHERE p.year = :year
                    GROUP BY p.month, p.year
                    ORDER BY p.month ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute(['year' => $selectedYear]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'allowances':
            $reportTitle = "Staff Benefits & Allowances Roster";
            $sql = "SELECT a.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
                    FROM allowances a
                    JOIN staff s ON a.staff_id = s.id
                    WHERE 1=1";
            $params = [];
            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($selectedEmp > 0) {
                $sql .= " AND s.id = :emp";
                $params['emp'] = $selectedEmp;
            }
            $sql .= " ORDER BY a.id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'deductions':
            $reportTitle = "Staff Withholdings & Deductions Log";
            $sql = "SELECT d.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
                    FROM deductions d
                    JOIN staff s ON d.staff_id = s.id
                    WHERE 1=1";
            $params = [];
            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($selectedEmp > 0) {
                $sql .= " AND s.id = :emp";
                $params['emp'] = $selectedEmp;
            }
            $sql .= " ORDER BY d.id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'advances':
            $reportTitle = "Advance Salary Loans & Recovery Ledger";
            $sql = "SELECT a.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
                    FROM advance_salary a
                    JOIN staff s ON a.staff_id = s.id
                    WHERE 1=1";
            $params = [];
            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($selectedEmp > 0) {
                $sql .= " AND s.id = :emp";
                $params['emp'] = $selectedEmp;
            }
            $sql .= " ORDER BY a.id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'bonuses':
            $reportTitle = "Bonuses & Rewards Register";
            $sql = "SELECT b.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department
                    FROM bonuses b
                    JOIN staff s ON b.staff_id = s.id
                    WHERE 1=1";
            $params = [];
            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($selectedEmp > 0) {
                $sql .= " AND s.id = :emp";
                $params['emp'] = $selectedEmp;
            }
            $sql .= " ORDER BY b.id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch (Exception $e) {
    error_log("Report loading failed: " . $e->getMessage());
}

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
.report-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.5rem 1.75rem;
    position: relative;
    overflow: hidden;
}
.report-hero-card::after {
    content: "";
    position: absolute;
    top: -50%;
    right: -10%;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.staff-avatar-sm {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    color: #ffffff;
}

.nav-report-pills .nav-link {
    color: #475569;
    font-weight: 600;
    border-radius: 20px;
    padding: 0.5rem 1.15rem;
    font-size: 0.88rem;
    transition: all 0.2s ease;
}
.nav-report-pills .nav-link.active {
    background-color: #1e293b;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}
</style>

<!-- Hero Banner Header -->
<div class="report-hero-card shadow-sm mb-4 d-print-none">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-primary bg-opacity-20 rounded-3 text-warning">
                    <i class="fa-solid fa-chart-pie fs-2"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-1">Payroll Reports Console</h3>
                    <p class="text-white-50 mb-0 small">
                        Generate monthly payroll journals, department summaries, annual disbursement ledgers, advance recovery audits, and bonus registers.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <a href="payroll.php" class="btn btn-outline-light btn-sm px-3 rounded-pill me-1">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
            </a>
            <button class="btn btn-warning btn-sm px-3 rounded-pill text-dark fw-semibold" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Print Report
            </button>
            <button class="btn btn-emerald btn-sm px-3 rounded-pill text-white bg-success fw-semibold ms-1" onclick="exportToExcel()">
                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
            </button>
        </div>
    </div>
</div>

<!-- Quick Navigation Tabs for 7 Report Modules -->
<div class="mb-4 d-print-none">
    <ul class="nav nav-pills nav-report-pills bg-white p-2 shadow-sm rounded-pill border">
        <li class="nav-item">
            <a href="?report_type=monthly_payroll&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>" class="nav-link <?php echo $reportType==='monthly_payroll' ? 'active' : ''; ?>">
                <i class="fa-solid fa-receipt me-1"></i> Monthly Journal
            </a>
        </li>
        <li class="nav-item">
            <a href="?report_type=dept_wise&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>" class="nav-link <?php echo $reportType==='dept_wise' ? 'active' : ''; ?>">
                <i class="fa-solid fa-building me-1"></i> Dept Summary
            </a>
        </li>
        <li class="nav-item">
            <a href="?report_type=salary_summary&year=<?php echo $selectedYear; ?>" class="nav-link <?php echo $reportType==='salary_summary' ? 'active' : ''; ?>">
                <i class="fa-solid fa-calendar-days me-1"></i> Annual Ledger
            </a>
        </li>
        <li class="nav-item">
            <a href="?report_type=allowances" class="nav-link <?php echo $reportType==='allowances' ? 'active' : ''; ?>">
                <i class="fa-solid fa-circle-plus me-1"></i> Allowances
            </a>
        </li>
        <li class="nav-item">
            <a href="?report_type=deductions" class="nav-link <?php echo $reportType==='deductions' ? 'active' : ''; ?>">
                <i class="fa-solid fa-circle-minus me-1"></i> Deductions
            </a>
        </li>
        <li class="nav-item">
            <a href="?report_type=advances" class="nav-link <?php echo $reportType==='advances' ? 'active' : ''; ?>">
                <i class="fa-solid fa-comments-dollar me-1"></i> Advance Loans
            </a>
        </li>
        <li class="nav-item">
            <a href="?report_type=bonuses" class="nav-link <?php echo $reportType==='bonuses' ? 'active' : ''; ?>">
                <i class="fa-solid fa-gift me-1"></i> Bonuses & Gifts
            </a>
        </li>
    </ul>
</div>

<!-- Smart Filters Card -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:14px;">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center" id="reportFilters">
            <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($reportType); ?>">
            
            <!-- Month Field -->
            <div class="col-md-2 filter-field" id="monthField">
                <select class="form-select bg-light" name="month" onchange="document.getElementById('reportFilters').submit()">
                    <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $selectedMonth === $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Year Field -->
            <div class="col-md-2 filter-field" id="yearField">
                <select class="form-select bg-light" name="year" onchange="document.getElementById('reportFilters').submit()">
                    <?php for($y=date('Y'); $y>=2024; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $selectedYear === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Department Field -->
            <div class="col-md-3 filter-field" id="deptField">
                <select class="form-select bg-light" name="department" onchange="document.getElementById('reportFilters').submit()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo $selectedDept === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Employee Field -->
            <div class="col-md-3 filter-field" id="empField">
                <select class="form-select bg-light" name="staff_id" onchange="document.getElementById('reportFilters').submit()">
                    <option value="0">All Staff Members</option>
                    <?php foreach ($staffList as $st): ?>
                        <option value="<?php echo $st['id']; ?>" <?php echo $selectedEmp === $st['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($st['employee_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 text-end ms-auto">
                <a href="payroll_reports.php?report_type=<?php echo $reportType; ?>" class="btn btn-outline-secondary w-100 rounded-pill">
                    <i class="fa-solid fa-rotate-left me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Printable Report Box -->
<div class="card border-0 shadow-sm mb-5" style="border-radius:14px;" id="reportPrintArea">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-1 text-secondary" id="reportOutputTitle"><?php echo htmlspecialchars($reportTitle); ?></h5>
            <small class="text-muted d-print-none">Generated on <?php echo date('d M Y, h:i A'); ?></small>
        </div>
        <div class="d-print-none">
            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
                <i class="fa-solid fa-table me-1"></i><?php echo count($reportData); ?> Records Found
            </span>
        </div>
    </div>
    
    <div class="card-body p-4 pt-2">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                
                <!-- 1. MONTHLY PAYROLL REPORT TABLE -->
                <?php if ($reportType === 'monthly_payroll'): ?>
                    <thead class="bg-light">
                        <tr>
                            <th>EMP ID</th>
                            <th>Staff Member</th>
                            <th>Department</th>
                            <th class="text-end">Base Salary</th>
                            <th class="text-end text-success">+ Allow.</th>
                            <th class="text-end text-danger">- Deduct.</th>
                            <th class="text-end text-purple">- Advance</th>
                            <th class="text-end text-success">+ Bonus</th>
                            <th class="text-end fw-bold text-dark">Net Salary</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="10" class="text-center py-5 text-muted">No processed payroll details found matching filter criteria.</td></tr>
                        <?php else: 
                            $totBasic = $totAllow = $totDeduct = $totAdv = $totBonus = $totNet = 0;
                            foreach ($reportData as $row): 
                                $fullName = trim($row['first_name'] . ' ' . $row['last_name']);
                                $initials = strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1));
                                $bgColor  = getAvatarColor($fullName);
                                
                                $totBasic += (float)$row['basic_salary'];
                                $totAllow += (float)$row['allowances'];
                                $totDeduct += (float)$row['deductions'];
                                $totAdv   += (float)$row['advance_salary_deduction'];
                                $totBonus += (float)$row['bonus'];
                                $totNet   += (float)$row['net_salary'];
                            ?>
                            <tr>
                                <td><code><?php echo sanitize($row['employee_no']); ?></code></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="staff-avatar-sm" style="background-color: <?php echo $bgColor; ?>;">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark mb-0"><?php echo sanitize($fullName); ?></div>
                                            <small class="text-muted"><?php echo sanitize($row['designation']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo sanitize($row['department']); ?></span></td>
                                <td class="text-end fw-semibold text-dark">Rs. <?php echo number_format($row['basic_salary'], 2); ?></td>
                                <td class="text-end text-success">+Rs. <?php echo number_format($row['allowances'], 2); ?></td>
                                <td class="text-end text-danger">-Rs. <?php echo number_format($row['deductions'], 2); ?></td>
                                <td class="text-end text-purple">-Rs. <?php echo number_format($row['advance_salary_deduction'], 2); ?></td>
                                <td class="text-end text-success">+Rs. <?php echo number_format($row['bonus'], 2); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($row['net_salary'], 2); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['payment_status'] === 'Paid' ? 'success' : 'warning text-dark'; ?>-soft px-3 py-1 rounded-pill">
                                        <?php echo $row['payment_status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark border-top">
                                <td colspan="3" class="text-end">Summary Totals:</td>
                                <td class="text-end">Rs. <?php echo number_format($totBasic, 2); ?></td>
                                <td class="text-end text-success">+Rs. <?php echo number_format($totAllow, 2); ?></td>
                                <td class="text-end text-danger">-Rs. <?php echo number_format($totDeduct, 2); ?></td>
                                <td class="text-end text-purple">-Rs. <?php echo number_format($totAdv, 2); ?></td>
                                <td class="text-end text-success">+Rs. <?php echo number_format($totBonus, 2); ?></td>
                                <td class="text-end text-primary fs-6">Rs. <?php echo number_format($totNet, 2); ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 2. DEPARTMENT WISE REPORT TABLE -->
                <?php elseif ($reportType === 'dept_wise'): ?>
                    <thead class="bg-light">
                        <tr>
                            <th>Department Name</th>
                            <th class="text-center">Staff Count</th>
                            <th class="text-end">Total Basic Base</th>
                            <th class="text-end text-success">Total Allowances</th>
                            <th class="text-end text-danger">Total Deductions</th>
                            <th class="text-end text-purple">Total Advance Recoveries</th>
                            <th class="text-end text-success">Total Bonuses</th>
                            <th class="text-end fw-bold text-dark">Total Net Disbursed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">No processed payroll details found for department summaries.</td></tr>
                        <?php else:
                            $totCount = $totBasic = $totAllow = $totDeduct = $totAdv = $totBonus = $totNet = 0;
                            foreach ($reportData as $row):
                                $totCount += (int)$row['staff_count'];
                                $totBasic += (float)$row['total_basic'];
                                $totAllow += (float)$row['total_allowances'];
                                $totDeduct += (float)$row['total_deductions'];
                                $totAdv   += (float)$row['total_advance'];
                                $totBonus += (float)$row['total_bonus'];
                                $totNet   += (float)$row['total_net'];
                            ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border px-3 py-2 fs-6 fw-bold"><?php echo sanitize($row['department']); ?></span></td>
                                <td class="text-center fw-semibold"><?php echo $row['staff_count']; ?> Employees</td>
                                <td class="text-end fw-semibold">Rs. <?php echo number_format($row['total_basic'], 2); ?></td>
                                <td class="text-end text-success">+Rs. <?php echo number_format($row['total_allowances'], 2); ?></td>
                                <td class="text-end text-danger">-Rs. <?php echo number_format($row['total_deductions'], 2); ?></td>
                                <td class="text-end text-purple">-Rs. <?php echo number_format($row['total_advance'], 2); ?></td>
                                <td class="text-end text-success">+Rs. <?php echo number_format($row['total_bonus'], 2); ?></td>
                                <td class="text-end fw-bold text-dark fs-6">Rs. <?php echo number_format($row['total_net'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark border-top">
                                <td>Department Totals:</td>
                                <td class="text-center"><?php echo $totCount; ?> Employees</td>
                                <td class="text-end">Rs. <?php echo number_format($totBasic, 2); ?></td>
                                <td class="text-end text-success">+Rs. <?php echo number_format($totAllow, 2); ?></td>
                                <td class="text-end text-danger">-Rs. <?php echo number_format($totDeduct, 2); ?></td>
                                <td class="text-end text-purple">-Rs. <?php echo number_format($totAdv, 2); ?></td>
                                <td class="text-end text-success">+Rs. <?php echo number_format($totBonus, 2); ?></td>
                                <td class="text-end text-primary fs-6">Rs. <?php echo number_format($totNet, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 3. SALARY SUMMARY (ANNUAL) TABLE -->
                <?php elseif ($reportType === 'salary_summary'): ?>
                    <thead class="bg-light">
                        <tr>
                            <th>Billing Period</th>
                            <th class="text-center">Staff Count</th>
                            <th class="text-end">Gross Basic Base</th>
                            <th class="text-end text-success">Gross Allowances</th>
                            <th class="text-end text-danger">Gross Deductions</th>
                            <th class="text-end fw-bold text-dark">Gross Net Payable</th>
                            <th class="text-end text-success">Paid Disbursed</th>
                            <th class="text-end text-danger">Pending Disbursed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">No processed payroll history found for Year <?php echo $selectedYear; ?>.</td></tr>
                        <?php else:
                            $totBasic = $totAllow = $totDeduct = $totNet = $totPaid = $totPend = 0;
                            foreach ($reportData as $row):
                                $totBasic += (float)$row['total_basic'];
                                $totAllow += (float)$row['total_allowances'];
                                $totDeduct += (float)$row['total_deductions'];
                                $totNet   += (float)$row['total_net'];
                                $totPaid  += (float)$row['total_paid'];
                                $totPend  += (float)$row['total_pending'];
                            ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo date('F Y', mktime(0,0,0,$row['month'], 1, $row['year'])); ?></td>
                                <td class="text-center fw-semibold"><?php echo $row['staff_count']; ?> Staff</td>
                                <td class="text-end">Rs. <?php echo number_format($row['total_basic'], 2); ?></td>
                                <td class="text-end text-success">+Rs. <?php echo number_format($row['total_allowances'], 2); ?></td>
                                <td class="text-end text-danger">-Rs. <?php echo number_format($row['total_deductions'], 2); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($row['total_net'], 2); ?></td>
                                <td class="text-end text-success fw-bold">Rs. <?php echo number_format($row['total_paid'], 2); ?></td>
                                <td class="text-end text-danger fw-bold">Rs. <?php echo number_format($row['total_pending'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark border-top">
                                <td colspan="2" class="text-end">Annual Totals:</td>
                                <td class="text-end">Rs. <?php echo number_format($totBasic, 2); ?></td>
                                <td class="text-end text-success">+Rs. <?php echo number_format($totAllow, 2); ?></td>
                                <td class="text-end text-danger">-Rs. <?php echo number_format($totDeduct, 2); ?></td>
                                <td class="text-end text-dark">Rs. <?php echo number_format($totNet, 2); ?></td>
                                <td class="text-end text-success fs-6">Rs. <?php echo number_format($totPaid, 2); ?></td>
                                <td class="text-end text-danger fs-6">Rs. <?php echo number_format($totPend, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 4. ALLOWANCES REPORT TABLE -->
                <?php elseif ($reportType === 'allowances'): ?>
                    <thead class="bg-light">
                        <tr>
                            <th>EMP ID</th>
                            <th>Staff Member</th>
                            <th>Allowance Category</th>
                            <th class="text-end">Monthly Benefit Amount</th>
                            <th>Description / Remarks</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No custom allowances logged matching parameters.</td></tr>
                        <?php else:
                            $totAmt = 0;
                            foreach ($reportData as $row):
                                $fullName = trim($row['first_name'] . ' ' . $row['last_name']);
                                $initials = strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1));
                                $bgColor  = getAvatarColor($fullName);
                                $totAmt += (float)$row['amount'];
                            ?>
                            <tr>
                                <td><code><?php echo sanitize($row['employee_no']); ?></code></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="staff-avatar-sm" style="background-color: <?php echo $bgColor; ?>;">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark mb-0"><?php echo sanitize($fullName); ?></div>
                                            <small class="text-muted"><?php echo sanitize($row['designation'] . ' | ' . $row['department']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-success-soft text-success px-3 py-1 rounded-pill fw-semibold"><?php echo sanitize($row['allowance_type']); ?></span></td>
                                <td class="text-end fw-bold text-success fs-6">+Rs. <?php echo number_format($row['amount'], 2); ?></td>
                                <td class="small text-muted"><?php echo sanitize($row['description'] ?: '—'); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark border-top">
                                <td colspan="3" class="text-end">Total Active Allowances:</td>
                                <td class="text-end text-success fs-6">+Rs. <?php echo number_format($totAmt, 2); ?></td>
                                <td colspan="2"></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 5. DEDUCTIONS REPORT TABLE -->
                <?php elseif ($reportType === 'deductions'): ?>
                    <thead class="bg-light">
                        <tr>
                            <th>EMP ID</th>
                            <th>Staff Member</th>
                            <th>Deduction Category</th>
                            <th class="text-end">Deduction Amount</th>
                            <th>Reason / Description</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No custom deductions logged matching parameters.</td></tr>
                        <?php else:
                            $totAmt = 0;
                            foreach ($reportData as $row):
                                $fullName = trim($row['first_name'] . ' ' . $row['last_name']);
                                $initials = strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1));
                                $bgColor  = getAvatarColor($fullName);
                                $totAmt += (float)$row['amount'];
                            ?>
                            <tr>
                                <td><code><?php echo sanitize($row['employee_no']); ?></code></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="staff-avatar-sm" style="background-color: <?php echo $bgColor; ?>;">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark mb-0"><?php echo sanitize($fullName); ?></div>
                                            <small class="text-muted"><?php echo sanitize($row['designation'] . ' | ' . $row['department']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-danger-soft text-danger px-3 py-1 rounded-pill fw-semibold"><?php echo sanitize($row['deduction_type']); ?></span></td>
                                <td class="text-end fw-bold text-danger fs-6">-Rs. <?php echo number_format($row['amount'], 2); ?></td>
                                <td class="small text-muted"><?php echo sanitize($row['reason'] ?: '—'); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark border-top">
                                <td colspan="3" class="text-end">Total Active Deductions:</td>
                                <td class="text-end text-danger fs-6">-Rs. <?php echo number_format($totAmt, 2); ?></td>
                                <td colspan="2"></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 6. ADVANCE SALARY REPORT TABLE -->
                <?php elseif ($reportType === 'advances'): ?>
                    <thead class="bg-light">
                        <tr>
                            <th>EMP ID</th>
                            <th>Staff Member</th>
                            <th class="text-center">Advance Date</th>
                            <th class="text-end">Principal Disbursed</th>
                            <th class="text-center">Installments</th>
                            <th class="text-end">Monthly Recovery</th>
                            <th class="text-end text-success">Recovered Amount</th>
                            <th class="text-end text-danger fw-bold">Outstanding Balance</th>
                            <th class="text-center">Recovery Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="9" class="text-center py-5 text-muted">No advance salary loans logged in system.</td></tr>
                        <?php else:
                            $totDis = $totPaid = $totRem = 0;
                            foreach ($reportData as $row):
                                $fullName = trim($row['first_name'] . ' ' . $row['last_name']);
                                $initials = strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1));
                                $bgColor  = getAvatarColor($fullName);
                                
                                $totDis  += (float)$row['amount'];
                                $totPaid += (float)$row['paid_amount'];
                                $totRem  += (float)$row['remaining_balance'];
                            ?>
                            <tr>
                                <td><code><?php echo sanitize($row['employee_no']); ?></code></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="staff-avatar-sm" style="background-color: <?php echo $bgColor; ?>;">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark mb-0"><?php echo sanitize($fullName); ?></div>
                                            <small class="text-muted"><?php echo sanitize($row['designation'] . ' | ' . $row['department']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center small fw-semibold"><?php echo date('d M Y', strtotime($row['advance_date'])); ?></td>
                                <td class="text-end fw-semibold text-dark">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                                <td class="text-center"><span class="badge bg-light text-dark border"><?php echo $row['installments']; ?> Months</span></td>
                                <td class="text-end text-purple fw-semibold">Rs. <?php echo number_format($row['installment_amount'], 2); ?></td>
                                <td class="text-end text-success fw-semibold">Rs. <?php echo number_format($row['paid_amount'], 2); ?></td>
                                <td class="text-end text-danger fw-bold">Rs. <?php echo number_format($row['remaining_balance'], 2); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Recovered' ? 'success' : 'warning text-dark'; ?>-soft px-3 py-1 rounded-pill">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark border-top">
                                <td colspan="3" class="text-end">Loan Totals:</td>
                                <td class="text-end">Rs. <?php echo number_format($totDis, 2); ?></td>
                                <td colspan="2"></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($totPaid, 2); ?></td>
                                <td class="text-end text-danger fs-6">Rs. <?php echo number_format($totRem, 2); ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 7. BONUSES REPORT TABLE -->
                <?php elseif ($reportType === 'bonuses'): ?>
                    <thead class="bg-light">
                        <tr>
                            <th>EMP ID</th>
                            <th>Staff Member</th>
                            <th>Reward Category</th>
                            <th class="text-center">Date Earned</th>
                            <th class="text-end">Reward Amount</th>
                            <th>Reason / Description</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No bonuses or incentives logged matching criteria.</td></tr>
                        <?php else:
                            $totAmt = 0;
                            foreach ($reportData as $row):
                                $fullName = trim($row['first_name'] . ' ' . $row['last_name']);
                                $initials = strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1));
                                $bgColor  = getAvatarColor($fullName);
                                $totAmt += (float)$row['amount'];
                            ?>
                            <tr>
                                <td><code><?php echo sanitize($row['employee_no']); ?></code></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="staff-avatar-sm" style="background-color: <?php echo $bgColor; ?>;">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark mb-0"><?php echo sanitize($fullName); ?></div>
                                            <small class="text-muted"><?php echo sanitize($row['designation'] . ' | ' . $row['department']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-warning-soft text-warning px-3 py-1 rounded-pill fw-semibold"><i class="fa-solid fa-gift me-1"></i><?php echo sanitize($row['bonus_type']); ?></span></td>
                                <td class="text-center small fw-semibold"><?php echo date('d M Y', strtotime($row['date_earned'])); ?></td>
                                <td class="text-end fw-bold text-success fs-6">+Rs. <?php echo number_format($row['amount'], 2); ?></td>
                                <td class="small text-muted"><?php echo sanitize($row['description'] ?: '—'); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark border-top">
                                <td colspan="4" class="text-end">Total Bonuses Disbursed:</td>
                                <td class="text-end text-success fs-6">+Rs. <?php echo number_format($totAmt, 2); ?></td>
                                <td colspan="2"></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                <?php endif; ?>

            </table>
        </div>
    </div>
</div>

<!-- Print Formatting Stylesheet -->
<style>
@media print {
    body * {
        visibility: hidden;
    }
    #reportPrintArea, #reportPrintArea * {
        visibility: visible;
    }
    #reportPrintArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none !important;
        border: 0 !important;
    }
    .custom-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .custom-table th, .custom-table td {
        border: 1px solid #ddd !important;
        padding: 8px !important;
        font-size: 11px !important;
    }
    .badge {
        border: none !important;
        background: transparent !important;
        color: #000 !important;
        font-weight: bold !important;
        padding: 0 !important;
    }
}
</style>

<?php $extraJS = '<script>
function toggleFilterFields() {
    const reportType = document.getElementById("reportTypeSelect").value;
    
    const month = document.getElementById("monthField");
    const year = document.getElementById("yearField");
    const dept = document.getElementById("deptField");
    const emp = document.getElementById("empField");

    month.style.display = "block";
    year.style.display = "block";
    dept.style.display = "block";
    emp.style.display = "block";

    if (reportType === "salary_summary") {
        month.style.display = "none";
        dept.style.display = "none";
        emp.style.display = "none";
    } else if (reportType === "dept_wise") {
        emp.style.display = "none";
    } else if (reportType === "allowances" || reportType === "deductions" || reportType === "advances" || reportType === "bonuses") {
        month.style.display = "none";
        year.style.display = "none";
    }
}

function exportToExcel() {
    let table = document.getElementById("reportDataTable");
    let html = table.outerHTML;
    
    let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
    let link = document.createElement("a");
    link.download = document.getElementById("reportOutputTitle").innerText.replace(/\s+/g, "_").toLowerCase() + ".xls";
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

document.addEventListener("DOMContentLoaded", function() {
    toggleFilterFields();
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
