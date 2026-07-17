<?php
/**
 * Indus Grammar School ERP - Payroll Ledger Reports
 * Version 4.0.0
 */

$pageTitle = 'Payroll Reports Panel';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Selectors
$departments = $db->query("SELECT DISTINCT department FROM staff ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
$employees = $db->query("SELECT id, employee_no, first_name, last_name FROM staff ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Filter values
$selectedReport = sanitize($_GET['report_type'] ?? 'payroll_summary');
$selectedMonth  = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear   = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedDept   = sanitize($_GET['department'] ?? '');
$selectedEmp    = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;

$reportTitle = "Payroll Report";
$reportData = [];

try {
    switch ($selectedReport) {
        
        case 'salary_report':
        case 'paid_salaries':
        case 'pending_salaries':
        case 'payroll_summary':
            $statusFilter = '';
            if ($selectedReport === 'paid_salaries') {
                $reportTitle = "Processed & Paid Salaries Ledger";
                $statusFilter = "AND sd.payment_status = 'Paid'";
            } else if ($selectedReport === 'pending_salaries') {
                $reportTitle = "Pending / Unpaid Salaries Ledger";
                $statusFilter = "AND sd.payment_status = 'Pending'";
            } else {
                $reportTitle = "General Monthly Payroll Ledger";
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
            $sql .= " ORDER BY s.first_name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'allowance_report':
            $reportTitle = "Salary Allowances Concessions Report";
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
            $sql .= " ORDER BY a.amount DESC, s.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'deduction_report':
            $reportTitle = "Payroll Deductions penalty Report";
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
            $sql .= " ORDER BY d.amount DESC, s.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'advance_report':
            $reportTitle = "Advance Salaries Disbursements Ledger";
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
            $sql .= " ORDER BY adv.advance_date DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'bonus_report':
            $reportTitle = "Employee Bonuses & Incentives Statement";
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
            $sql .= " ORDER BY b.date_earned DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch (Exception $e) {
    error_log("Payroll report error: " . $e->getMessage());
}

?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-wallet text-primary me-2"></i>Payroll Reports</h3>
        <p class="text-muted small mb-0">Construct employee payroll summaries, check processed salaries, allowance distributions and bonus incentives.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-outline-primary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Ledger</button>
        <button class="btn btn-outline-success px-3 ms-2" onclick="exportToExcel()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
        <a href="dashboard.php" class="btn btn-outline-secondary px-3 ms-2"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Filters Form -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Select Report Type</label>
                <select class="form-select" name="report_type" id="reportSelect" onchange="toggleFilterFields(); this.form.submit();">
                    <option value="payroll_summary" <?php echo $selectedReport === 'payroll_summary' ? 'selected' : ''; ?>>Payroll Summary</option>
                    <option value="paid_salaries" <?php echo $selectedReport === 'paid_salaries' ? 'selected' : ''; ?>>Paid Salaries</option>
                    <option value="pending_salaries" <?php echo $selectedReport === 'pending_salaries' ? 'selected' : ''; ?>>Pending Salaries</option>
                    <option value="allowance_report" <?php echo $selectedReport === 'allowance_report' ? 'selected' : ''; ?>>Allowance Report</option>
                    <option value="deduction_report" <?php echo $selectedReport === 'deduction_report' ? 'selected' : ''; ?>>Deduction Report</option>
                    <option value="advance_report" <?php echo $selectedReport === 'advance_report' ? 'selected' : ''; ?>>Advance Salary Report</option>
                    <option value="bonus_report" <?php echo $selectedReport === 'bonus_report' ? 'selected' : ''; ?>>Bonus Report</option>
                </select>
            </div>

            <div class="col-md-2 filter-field" id="monthField">
                <label class="form-label small fw-semibold text-muted">Month</label>
                <select class="form-select" name="month">
                    <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $selectedMonth === $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="col-md-1 filter-field" id="yearField">
                <label class="form-label small fw-semibold text-muted">Year</label>
                <input type="number" class="form-control" name="year" value="<?php echo $selectedYear; ?>" min="2020" max="2035">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Department</label>
                <select class="form-select" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept; ?>" <?php echo $selectedDept === $dept ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Employee</label>
                <select class="form-select" name="staff_id">
                    <option value="0">All Staff</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php echo $selectedEmp === (int)$emp['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_no'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Output -->
<div class="card border-0 shadow-sm" style="border-radius:12px;" id="reportPrintArea">
    <!-- Print Header -->
    <div class="card-header bg-white border-0 pt-4 px-4 text-center d-none d-print-block border-bottom pb-3">
        <h3 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h3>
        <h5 class="text-secondary fw-semibold mb-1"><?php echo htmlspecialchars($reportTitle); ?></h5>
        <div class="text-muted small">
            Date: <?php echo date('d-M-Y H:i'); ?> | Generated By: <?php echo htmlspecialchars($_SESSION['username'] ?? 'ERP Admin'); ?>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                
                <!-- 1. PAYROLL SUMMARY / SALARY / PAID / PENDING -->
                <?php if (in_array($selectedReport, ['payroll_summary', 'paid_salaries', 'pending_salaries'])): ?>
                    <thead>
                        <tr>
                            <th>Employee No</th>
                            <th>Staff Name</th>
                            <th>Department</th>
                            <th class="text-end">Basic Salary</th>
                            <th class="text-end text-success">Allowances</th>
                            <th class="text-end text-danger">Deductions</th>
                            <th class="text-end text-info">Bonuses</th>
                            <th class="text-end">Net Pay</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): $totBasic = $totAllow = $totDeduct = $totBonus = $totNet = 0; ?>
                            <tr><td colspan="9" class="text-center py-5 text-muted">No processed payroll records found matching the criteria.</td></tr>
                        <?php else: 
                            $totBasic = $totAllow = $totDeduct = $totBonus = $totNet = 0; 
                            foreach ($reportData as $row): 
                                $totBasic += (float)$row['basic_salary'];
                                $totAllow += (float)$row['allowances'];
                                $totDeduct += (float)($row['deductions'] + $row['advance_salary_deduction']);
                                $totBonus += (float)$row['bonus'];
                                $totNet += (float)$row['net_salary'];
                            ?>
                            <tr>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['employee_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="fw-semibold text-muted"><?php echo htmlspecialchars($row['department']); ?></td>
                                <td class="text-end text-muted">Rs. <?php echo number_format($row['basic_salary'], 2); ?></td>
                                <td class="text-end text-success">Rs. <?php echo number_format($row['allowances'], 2); ?></td>
                                <td class="text-end text-danger">Rs. <?php echo number_format($row['deductions'] + $row['advance_salary_deduction'], 2); ?></td>
                                <td class="text-end text-info">Rs. <?php echo number_format($row['bonus'], 2); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($row['net_salary'], 2); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['payment_status'] === 'Paid' ? 'success' : 'warning'; ?>-soft px-3 py-1 rounded-pill fw-bold text-xs"><?php echo $row['payment_status']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="3">Totals realized:</td>
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
                            <th>Employee No</th>
                            <th>Staff Name</th>
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
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['employee_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="fw-semibold text-muted"><?php echo htmlspecialchars($row['department']); ?></td>
                                <td><span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($row['allowance_type']); ?></span></td>
                                <td class="small"><?php echo htmlspecialchars($row['description'] ?: '—'); ?></td>
                                <td class="text-end fw-bold text-success">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="5">Total realized allowances:</td>
                                <td class="text-end text-success fs-5">Rs. <?php echo number_format($total, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 3. DEDUCTIONS LIST -->
                <?php elseif ($selectedReport === 'deduction_report'): ?>
                    <thead>
                        <tr>
                            <th>Employee No</th>
                            <th>Staff Name</th>
                            <th>Department</th>
                            <th>Deduction Category</th>
                            <th>Reason</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): $total = 0; ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No deductions logs found.</td></tr>
                        <?php else: $total = 0; foreach ($reportData as $row): $total += (float)$row['amount']; ?>
                            <tr>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['employee_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="fw-semibold text-muted"><?php echo htmlspecialchars($row['department']); ?></td>
                                <td><span class="badge bg-danger-soft text-danger px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($row['deduction_type']); ?></span></td>
                                <td class="small"><?php echo htmlspecialchars($row['reason'] ?: '—'); ?></td>
                                <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="5">Total deductions realized:</td>
                                <td class="text-end text-danger fs-5">Rs. <?php echo number_format($total, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- 4. ADVANCES LIST -->
                <?php elseif ($selectedReport === 'advance_report'): ?>
                    <thead>
                        <tr>
                            <th>Employee No</th>
                            <th>Staff Name</th>
                            <th class="text-center">Issued Date</th>
                            <th class="text-end">Disbursed Amount</th>
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
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['employee_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="text-center small"><?php echo date('d-M-Y', strtotime($row['advance_date'])); ?></td>
                                <td class="text-end fw-semibold text-muted">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                                <td class="text-end text-success fw-semibold">Rs. <?php echo number_format($row['paid_amount'], 2); ?></td>
                                <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($row['remaining_balance'], 2); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Recovered' ? 'success' : 'warning'; ?>-soft px-3 py-1 rounded-pill fw-bold text-xs"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 5. BONUSES LIST -->
                <?php elseif ($selectedReport === 'bonus_report'): ?>
                    <thead>
                        <tr>
                            <th>Employee No</th>
                            <th>Staff Name</th>
                            <th>Department</th>
                            <th>Bonus Category</th>
                            <th>Date Issued</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): $total = 0; ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No bonuses configured.</td></tr>
                        <?php else: $total = 0; foreach ($reportData as $row): $total += (float)$row['amount']; ?>
                            <tr>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['employee_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="fw-semibold text-muted"><?php echo htmlspecialchars($row['department']); ?></td>
                                <td><span class="badge bg-success-soft text-success px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($row['bonus_type']); ?></span></td>
                                <td class="text-center small"><?php echo date('d-M-Y', strtotime($row['date_earned'])); ?></td>
                                <td class="text-end fw-bold text-success">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="5">Total incentives disbursed:</td>
                                <td class="text-end text-success fs-5">Rs. <?php echo number_format($total, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                <?php endif; ?>

            </table>
        </div>
    </div>
</div>

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
    .custom-table th, .custom-table td {
        border: 1px solid #ddd !important;
        padding: 8px !important;
        font-size: 11px !important;
    }
}
</style>

<?php $extraJS = '<script>
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
    let html = table.outerHTML;
    let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
    let link = document.createElement("a");
    link.download = "' . strtolower(str_replace(' ', '_', $reportTitle)) . '_' . date('Ymd') . '.xls";
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
