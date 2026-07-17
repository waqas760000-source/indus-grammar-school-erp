<?php
/**
 * Indus Grammar School ERP - Payroll Reports Console
 * Version 4.0.0
 */

$pageTitle = 'Payroll Reports';
$breadcrumbActive = 'Payroll';
include_once __DIR__ . '/../../includes/header.php';

// Restricted to Super Admin, School Admin, and Accountant
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'accountant'])) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permissions to access the payroll module.';
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
$departments = $db->query("SELECT DISTINCT department FROM staff ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
$staffList   = $db->query("SELECT id, employee_no, first_name, last_name, designation FROM staff WHERE status = 'Active' ORDER BY employee_no ASC")->fetchAll(PDO::FETCH_ASSOC);

// Perform query based on selected report type
$reportData = [];
$reportTitle = "Payroll Report";

try {
    switch ($reportType) {
        case 'monthly_payroll':
            $reportTitle = "Monthly Payroll Report — " . date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear));
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
            $reportTitle = "Annual Salary Disbursement Summary ($selectedYear)";
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
            $reportTitle = "Staff Custom Allowances Log";
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
            $reportTitle = "Staff Custom Deductions Log";
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
            $reportTitle = "Advance Salary Outstanding Report";
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
            $reportTitle = "Bonuses & Gifts Registry Report";
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
?>

<!-- Title Header -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Payroll Reports Console</h3>
        <p class="text-muted small mb-0">Generate monthly journals, department summaries, outstanding advances ledger, and bonus details.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="payroll.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="reportFilters">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Select Report Type</label>
                <select class="form-select" name="report_type" id="reportTypeSelect" onchange="toggleFilterFields()">
                    <option value="monthly_payroll" <?php echo $reportType === 'monthly_payroll' ? 'selected' : ''; ?>>Monthly Payroll Report</option>
                    <option value="dept_wise" <?php echo $reportType === 'dept_wise' ? 'selected' : ''; ?>>Department Wise Payroll</option>
                    <option value="salary_summary" <?php echo $reportType === 'salary_summary' ? 'selected' : ''; ?>>Salary Summary (Annual)</option>
                    <option value="allowances" <?php echo $reportType === 'allowances' ? 'selected' : ''; ?>>Allowance Report</option>
                    <option value="deductions" <?php echo $reportType === 'deductions' ? 'selected' : ''; ?>>Deduction Report</option>
                    <option value="advances" <?php echo $reportType === 'advances' ? 'selected' : ''; ?>>Advance Salary Report</option>
                    <option value="bonuses" <?php echo $reportType === 'bonuses' ? 'selected' : ''; ?>>Bonus Report</option>
                </select>
            </div>

            <!-- Month Field -->
            <div class="col-md-2 filter-field" id="monthField">
                <label class="form-label small fw-semibold text-muted">Month</label>
                <select class="form-select" name="month">
                    <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $selectedMonth === $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Year Field -->
            <div class="col-md-2 filter-field" id="yearField">
                <label class="form-label small fw-semibold text-muted">Year</label>
                <select class="form-select" name="year">
                    <?php for($y=date('Y'); $y>=2024; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $selectedYear === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Department Field -->
            <div class="col-md-2 filter-field" id="deptField">
                <label class="form-label small fw-semibold text-muted">Department</label>
                <select class="form-select" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo $selectedDept === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Employee Field -->
            <div class="col-md-3 filter-field" id="empField">
                <label class="form-label small fw-semibold text-muted">Employee</label>
                <select class="form-select" name="staff_id">
                    <option value="0">All Employees</option>
                    <?php foreach ($staffList as $st): ?>
                        <option value="<?php echo $st['id']; ?>" <?php echo $selectedEmp === $st['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($st['employee_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 ms-auto">
                <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Output Box -->
<div class="card border-0 shadow-sm mb-5" style="border-radius:12px;" id="reportPrintArea">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-1 text-secondary" id="reportOutputTitle"><?php echo htmlspecialchars($reportTitle); ?></h5>
            <small class="text-muted d-print-none">Report generated on <?php echo date('d-M-Y H:i'); ?></small>
        </div>
        <div class="d-print-none">
            <button class="btn btn-outline-secondary btn-sm px-3 me-2" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print Report</button>
            <button class="btn btn-outline-success btn-sm px-3" onclick="exportToExcel()"><i class="fa-solid fa-file-excel me-1"></i>Export Excel</button>
        </div>
    </div>
    
    <div class="card-body p-4 pt-2">
        <div class="table-responsive">
            <table class="table table-bordered custom-table align-middle" id="reportDataTable">
                
                <!-- 1. MONTHLY PAYROLL REPORT TABLE -->
                <?php if ($reportType === 'monthly_payroll'): ?>
                    <thead>
                        <tr class="table-light">
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th class="text-end">Basic Salary</th>
                            <th class="text-end">Allowances</th>
                            <th class="text-end">Deductions</th>
                            <th class="text-end">Advance Ded.</th>
                            <th class="text-end">Bonus</th>
                            <th class="text-end fw-bold">Net Salary</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="11" class="text-center py-4 text-muted">No processed payroll details found matching criteria.</td></tr>
                        <?php else: 
                            $totBasic = $totAllow = $totDeduct = $totAdv = $totBonus = $totNet = 0;
                            foreach ($reportData as $row): 
                                $totBasic += $row['basic_salary'];
                                $totAllow += $row['allowances'];
                                $totDeduct += $row['deductions'];
                                $totAdv   += $row['advance_salary_deduction'];
                                $totBonus += $row['bonus'];
                                $totNet   += $row['net_salary'];
                            ?>
                            <tr>
                                <td class="fw-bold text-secondary"><?php echo htmlspecialchars($row['employee_no']); ?></td>
                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['department']); ?></td>
                                <td><?php echo htmlspecialchars($row['designation']); ?></td>
                                <td class="text-end">Rs. <?php echo number_format($row['basic_salary'], 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($row['allowances'], 2); ?></td>
                                <td class="text-end text-danger">Rs. <?php echo number_format($row['deductions'], 2); ?></td>
                                <td class="text-end text-purple">Rs. <?php echo number_format($row['advance_salary_deduction'], 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($row['bonus'], 2); ?></td>
                                <td class="text-end fw-bold text-primary">Rs. <?php echo number_format($row['net_salary'], 2); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['payment_status'] === 'Paid' ? 'success' : 'warning text-dark'; ?>-soft px-3 py-1 rounded-pill"><?php echo $row['payment_status']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="4" class="text-end">Totals:</td>
                                <td class="text-end">Rs. <?php echo number_format($totBasic, 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($totAllow, 2); ?></td>
                                <td class="text-end text-danger">Rs. <?php echo number_format($totDeduct, 2); ?></td>
                                <td class="text-end text-purple">Rs. <?php echo number_format($totAdv, 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($totBonus, 2); ?></td>
                                <td class="text-end text-primary">Rs. <?php echo number_format($totNet, 2); ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 2. DEPARTMENT WISE REPORT TABLE -->
                <?php elseif ($reportType === 'dept_wise'): ?>
                    <thead>
                        <tr class="table-light">
                            <th>Department Name</th>
                            <th class="text-center">Staff Count</th>
                            <th class="text-end">Total Basic</th>
                            <th class="text-end text-success">Total Allowances</th>
                            <th class="text-end text-danger">Total Deductions</th>
                            <th class="text-end text-purple">Total Advance Ded.</th>
                            <th class="text-end text-success">Total Bonus</th>
                            <th class="text-end fw-bold">Total Net Salary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No processed payroll details found for department summaries.</td></tr>
                        <?php else:
                            $totCount = $totBasic = $totAllow = $totDeduct = $totAdv = $totBonus = $totNet = 0;
                            foreach ($reportData as $row):
                                $totCount += $row['staff_count'];
                                $totBasic += $row['total_basic'];
                                $totAllow += $row['total_allowances'];
                                $totDeduct += $row['total_deductions'];
                                $totAdv   += $row['total_advance'];
                                $totBonus += $row['total_bonus'];
                                $totNet   += $row['total_net'];
                            ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['department']); ?></td>
                                <td class="text-center fw-semibold"><?php echo $row['staff_count']; ?> Employees</td>
                                <td class="text-end">Rs. <?php echo number_format($row['total_basic'], 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($row['total_allowances'], 2); ?></td>
                                <td class="text-end text-danger">Rs. <?php echo number_format($row['total_deductions'], 2); ?></td>
                                <td class="text-end text-purple">Rs. <?php echo number_format($row['total_advance'], 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($row['total_bonus'], 2); ?></td>
                                <td class="text-end fw-bold text-primary">Rs. <?php echo number_format($row['total_net'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td>Totals:</td>
                                <td class="text-center"><?php echo $totCount; ?> Employees</td>
                                <td class="text-end">Rs. <?php echo number_format($totBasic, 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($totAllow, 2); ?></td>
                                <td class="text-end text-danger">Rs. <?php echo number_format($totDeduct, 2); ?></td>
                                <td class="text-end text-purple">Rs. <?php echo number_format($totAdv, 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($totBonus, 2); ?></td>
                                <td class="text-end text-primary">Rs. <?php echo number_format($totNet, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 3. SALARY SUMMARY (ANNUAL) TABLE -->
                <?php elseif ($reportType === 'salary_summary'): ?>
                    <thead>
                        <tr class="table-light">
                            <th>Payroll Period</th>
                            <th class="text-center">Active Employees</th>
                            <th class="text-end">Gross Basic</th>
                            <th class="text-end text-success">Gross Allowances</th>
                            <th class="text-end text-danger">Gross Deductions</th>
                            <th class="text-end fw-bold">Gross Net Salary</th>
                            <th class="text-end text-success">Paid Salaries</th>
                            <th class="text-end text-warning">Pending Salaries</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No processed payroll history found for Year <?php echo $selectedYear; ?>.</td></tr>
                        <?php else:
                            $totBasic = $totAllow = $totDeduct = $totNet = $totPaid = $totPend = 0;
                            foreach ($reportData as $row):
                                $totBasic += $row['total_basic'];
                                $totAllow += $row['total_allowances'];
                                $totDeduct += $row['total_deductions'];
                                $totNet   += $row['total_net'];
                                $totPaid  += $row['total_paid'];
                                $totPend  += $row['total_pending'];
                            ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo date('F Y', mktime(0,0,0,$row['month'], 1, $row['year'])); ?></td>
                                <td class="text-center fw-semibold"><?php echo $row['staff_count']; ?> Employees</td>
                                <td class="text-end">Rs. <?php echo number_format($row['total_basic'], 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($row['total_allowances'], 2); ?></td>
                                <td class="text-end text-danger">Rs. <?php echo number_format($row['total_deductions'], 2); ?></td>
                                <td class="text-end fw-bold text-primary">Rs. <?php echo number_format($row['total_net'], 2); ?></td>
                                <td class="text-end text-success fw-bold">Rs. <?php echo number_format($row['total_paid'], 2); ?></td>
                                <td class="text-end text-warning-dark fw-bold">Rs. <?php echo number_format($row['total_pending'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="2" class="text-end">Totals:</td>
                                <td class="text-end">Rs. <?php echo number_format($totBasic, 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($totAllow, 2); ?></td>
                                <td class="text-end text-danger">Rs. <?php echo number_format($totDeduct, 2); ?></td>
                                <td class="text-end text-primary">Rs. <?php echo number_format($totNet, 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($totPaid, 2); ?></td>
                                <td class="text-end text-warning-dark">Rs. <?php echo number_format($totPend, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 4. ALLOWANCES REPORT TABLE -->
                <?php elseif ($reportType === 'allowances'): ?>
                    <thead>
                        <tr class="table-light">
                            <th>Employee ID</th>
                            <th>Name & Department</th>
                            <th>Allowance Type</th>
                            <th class="text-end">Amount</th>
                            <th>Description</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No custom allowances logged matching parameters.</td></tr>
                        <?php else:
                            $totAmt = 0;
                            foreach ($reportData as $row):
                                $totAmt += $row['amount'];
                            ?>
                            <tr>
                                <td class="fw-bold text-secondary"><?php echo htmlspecialchars($row['employee_no']); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['designation'] . ' | ' . $row['department']); ?></small>
                                </td>
                                <td class="fw-semibold text-success"><?php echo htmlspecialchars($row['allowance_type']); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($row['description'] ?: '—'); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="3" class="text-end">Total Allowances:</td>
                                <td class="text-end">Rs. <?php echo number_format($totAmt, 2); ?></td>
                                <td colspan="2"></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 5. DEDUCTIONS REPORT TABLE -->
                <?php elseif ($reportType === 'deductions'): ?>
                    <thead>
                        <tr class="table-light">
                            <th>Employee ID</th>
                            <th>Name & Department</th>
                            <th>Deduction Type</th>
                            <th class="text-end">Amount</th>
                            <th>Reason / Description</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No custom deductions logged matching parameters.</td></tr>
                        <?php else:
                            $totAmt = 0;
                            foreach ($reportData as $row):
                                $totAmt += $row['amount'];
                            ?>
                            <tr>
                                <td class="fw-bold text-secondary"><?php echo htmlspecialchars($row['employee_no']); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['designation'] . ' | ' . $row['department']); ?></small>
                                </td>
                                <td class="fw-semibold text-danger"><?php echo htmlspecialchars($row['deduction_type']); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($row['reason'] ?: '—'); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="3" class="text-end">Total Deductions:</td>
                                <td class="text-end">Rs. <?php echo number_format($totAmt, 2); ?></td>
                                <td colspan="2"></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 6. ADVANCE SALARY REPORT TABLE -->
                <?php elseif ($reportType === 'advances'): ?>
                    <thead>
                        <tr class="table-light">
                            <th>Employee ID</th>
                            <th>Name & Department</th>
                            <th class="text-center">Advance Date</th>
                            <th class="text-end">Advance Disbursed</th>
                            <th class="text-center">Installments</th>
                            <th class="text-end">Installment Value</th>
                            <th class="text-end text-success">Recovered Amount</th>
                            <th class="text-end text-danger fw-bold">Outstanding Balance</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted">No advance salary loans logged in system.</td></tr>
                        <?php else:
                            $totDis = $totPaid = $totRem = 0;
                            foreach ($reportData as $row):
                                $totDis  += $row['amount'];
                                $totPaid += $row['paid_amount'];
                                $totRem  += $row['remaining_balance'];
                            ?>
                            <tr>
                                <td class="fw-bold text-secondary"><?php echo htmlspecialchars($row['employee_no']); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['designation'] . ' | ' . $row['department']); ?></small>
                                </td>
                                <td class="text-center small"><?php echo date('d-M-Y', strtotime($row['advance_date'])); ?></td>
                                <td class="text-end">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                                <td class="text-center"><?php echo $row['installments']; ?> Mos</td>
                                <td class="text-end text-muted">Rs. <?php echo number_format($row['installment_amount'], 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($row['paid_amount'], 2); ?></td>
                                <td class="text-end text-danger fw-bold">Rs. <?php echo number_format($row['remaining_balance'], 2); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Recovered' ? 'success' : 'warning text-dark'; ?>-soft px-3 py-1 rounded-pill"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="3" class="text-end">Totals:</td>
                                <td class="text-end">Rs. <?php echo number_format($totDis, 2); ?></td>
                                <td colspan="2"></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($totPaid, 2); ?></td>
                                <td class="text-end text-danger">Rs. <?php echo number_format($totRem, 2); ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 7. BONUSES REPORT TABLE -->
                <?php elseif ($reportType === 'bonuses'): ?>
                    <thead>
                        <tr class="table-light">
                            <th>Employee ID</th>
                            <th>Name & Department</th>
                            <th>Bonus Type</th>
                            <th class="text-center">Date Earned</th>
                            <th class="text-end">Amount</th>
                            <th>Reason / Description</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No bonuses or incentives logged matching criteria.</td></tr>
                        <?php else:
                            $totAmt = 0;
                            foreach ($reportData as $row):
                                $totAmt += $row['amount'];
                            ?>
                            <tr>
                                <td class="fw-bold text-secondary"><?php echo htmlspecialchars($row['employee_no']); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['designation'] . ' | ' . $row['department']); ?></small>
                                </td>
                                <td class="fw-semibold text-warning-dark"><?php echo htmlspecialchars($row['bonus_type']); ?></td>
                                <td class="text-center small"><?php echo date('d-M-Y', strtotime($row['date_earned'])); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($row['description'] ?: '—'); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="4" class="text-end">Total Bonuses & Incentives:</td>
                                <td class="text-end">Rs. <?php echo number_format($totAmt, 2); ?></td>
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
    
    // Show/hide based on report type selection
    const month = document.getElementById("monthField");
    const year = document.getElementById("yearField");
    const dept = document.getElementById("deptField");
    const emp = document.getElementById("empField");

    // Defaults
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
    
    // Clean up html styling references for excel parsing
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
