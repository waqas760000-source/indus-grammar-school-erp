<?php
/**
 * Indus Grammar School ERP - Comprehensive Staff & HR Reports
 * Version 4.0.0
 */

$pageTitle = 'Staff Reports Panel';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Selectors
$departments = $db->query("SELECT DISTINCT department FROM staff ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
$designations = $db->query("SELECT DISTINCT designation FROM staff ORDER BY designation ASC")->fetchAll(PDO::FETCH_COLUMN);

// Filter values
$selectedReport   = sanitize($_GET['report_type'] ?? 'employee_list');
$selectedDept     = sanitize($_GET['department'] ?? '');
$selectedDesig    = sanitize($_GET['designation'] ?? '');
$selectedStatus   = sanitize($_GET['status'] ?? '');
$dateFrom         = sanitize($_GET['date_from'] ?? '');
$dateTo           = sanitize($_GET['date_to'] ?? '');

$reportTitle = "Staff Report";
$reportData = [];

try {
    switch ($selectedReport) {
        
        case 'attendance_report':
            $reportTitle = "Staff Attendance Performance Ledger";
            // Counts present, absent, late, leaves per staff member
            $sql = "
                SELECT s.employee_no, s.first_name, s.last_name, s.department, s.designation,
                       SUM(CASE WHEN sa.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                       SUM(CASE WHEN sa.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                       SUM(CASE WHEN sa.status = 'Late' THEN 1 ELSE 0 END) as late_count,
                       SUM(CASE WHEN sa.status = 'Leave' THEN 1 ELSE 0 END) as leave_count
                FROM staff s
                LEFT JOIN staff_attendance sa ON sa.staff_id = s.id
                WHERE 1=1
            ";
            $params = [];
            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($selectedDesig !== '') {
                $sql .= " AND s.designation = :desig";
                $params['desig'] = $selectedDesig;
            }
            if ($dateFrom !== '') {
                $sql .= " AND sa.date >= :from";
                $params['from'] = $dateFrom;
            }
            if ($dateTo !== '') {
                $sql .= " AND sa.date <= :to";
                $params['to'] = $dateTo;
            }
            $sql .= " GROUP BY s.id ORDER BY s.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'leave_report':
            $reportTitle = "Staff Leave Ledger Log";
            $sql = "
                SELECT sl.*, s.first_name, s.last_name, s.employee_no, s.department, s.designation
                FROM staff_leave sl
                JOIN staff s ON sl.staff_id = s.id
                WHERE 1=1
            ";
            $params = [];
            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($selectedDesig !== '') {
                $sql .= " AND s.designation = :desig";
                $params['desig'] = $selectedDesig;
            }
            if ($dateFrom !== '') {
                $sql .= " AND sl.leave_from >= :from";
                $params['from'] = $dateFrom;
            }
            if ($dateTo !== '') {
                $sql .= " AND sl.leave_to <= :to";
                $params['to'] = $dateTo;
            }
            $sql .= " ORDER BY sl.leave_from DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        default:
            // Standard directory listing
            $sql = "SELECT s.* FROM staff s WHERE 1=1";
            $params = [];

            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($selectedDesig !== '') {
                $sql .= " AND s.designation = :desig";
                $params['desig'] = $selectedDesig;
            }
            if ($selectedStatus !== '') {
                $sql .= " AND s.status = :status";
                $params['status'] = $selectedStatus;
            }

            if ($selectedReport === 'joining_report') {
                $reportTitle = "Employee Joining History Register";
                if ($dateFrom !== '') {
                    $sql .= " AND s.joining_date >= :from";
                    $params['from'] = $dateFrom;
                }
                if ($dateTo !== '') {
                    $sql .= " AND s.joining_date <= :to";
                    $params['to'] = $dateTo;
                }
                $sql .= " ORDER BY s.joining_date ASC";
            } else if ($selectedReport === 'inactive_staff') {
                $reportTitle = "Inactive Staff Directory";
                $sql .= " AND s.status = 'Inactive' ORDER BY s.first_name ASC";
            } else {
                $reportTitle = "Staff Roster Directory List";
                $sql .= " ORDER BY s.first_name ASC";
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch (Exception $e) {
    error_log("Staff report error: " . $e->getMessage());
}
?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-users-gear text-primary me-2"></i>Staff Reports</h3>
        <p class="text-muted small mb-0">Construct employee registries, analyze departmental leaves, and track staff attendance summaries.</p>
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
                <select class="form-select" name="report_type" onchange="this.form.submit()">
                    <option value="employee_list" <?php echo $selectedReport === 'employee_list' ? 'selected' : ''; ?>>Employee List</option>
                    <option value="attendance_report" <?php echo $selectedReport === 'attendance_report' ? 'selected' : ''; ?>>Attendance Summary</option>
                    <option value="leave_report" <?php echo $selectedReport === 'leave_report' ? 'selected' : ''; ?>>Leave Report Log</option>
                    <option value="joining_report" <?php echo $selectedReport === 'joining_report' ? 'selected' : ''; ?>>Joining Report</option>
                    <option value="inactive_staff" <?php echo $selectedReport === 'inactive_staff' ? 'selected' : ''; ?>>Inactive Staff Directory</option>
                </select>
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
                <label class="form-label small fw-semibold text-muted">Designation</label>
                <select class="form-select" name="designation">
                    <option value="">All Designations</option>
                    <?php foreach ($designations as $desig): ?>
                        <option value="<?php echo $desig; ?>" <?php echo $selectedDesig === $desig ? 'selected' : ''; ?>><?php echo htmlspecialchars($desig); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select class="form-select" name="status" <?php echo in_array($selectedReport, ['leave_report','inactive_staff']) ? 'disabled' : ''; ?>>
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $selectedStatus === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $selectedStatus === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Date Range</label>
                <div class="input-group">
                    <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>" <?php echo ($selectedReport === 'inactive_staff') ? 'disabled' : ''; ?>>
                    <span class="input-group-text">to</span>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>" <?php echo ($selectedReport === 'inactive_staff') ? 'disabled' : ''; ?>>
                </div>
            </div>

            <div class="col-12 text-end mt-3">
                <a href="staff.php" class="btn btn-outline-secondary px-4 py-2 me-2"><i class="fa-solid fa-rotate-left me-2"></i>Reset</a>
                <button type="submit" class="btn btn-primary px-5 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
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
                
                <!-- 1. GENERAL STAFF LIST / JOINING / INACTIVE -->
                <?php if (in_array($selectedReport, ['employee_list', 'joining_report', 'inactive_staff'])): ?>
                    <thead>
                        <tr>
                            <th>Employee No</th>
                            <th>Staff Name</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th>Mobile Phone</th>
                            <th>Email</th>
                            <th class="text-center">Joining Date</th>
                            <th class="text-end">Base Salary</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="9" class="text-center py-5 text-muted">No staff records found.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['employee_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="fw-semibold text-muted"><?php echo htmlspecialchars($row['department']); ?></td>
                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($row['designation']); ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><?php echo htmlspecialchars($row['email'] ?: '—'); ?></td>
                                <td class="text-center small"><?php echo $row['joining_date'] ? date('d-M-Y', strtotime($row['joining_date'])) : '—'; ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($row['salary'], 2); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill fw-semibold"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 2. ATTENDANCE REPORT SUMMARY -->
                <?php elseif ($selectedReport === 'attendance_report'): ?>
                    <thead>
                        <tr>
                            <th>Employee No</th>
                            <th>Staff Name</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th class="text-center text-success">Presents</th>
                            <th class="text-center text-warning">Lates</th>
                            <th class="text-center text-danger">Absents</th>
                            <th class="text-center text-info">Leaves</th>
                            <th class="text-center">Total Working</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="9" class="text-center py-5 text-muted">No staff attendance records logged.</td></tr>
                        <?php else: foreach ($reportData as $row): 
                            $totDays = $row['present_count'] + $row['absent_count'] + $row['late_count'] + $row['leave_count'];
                            ?>
                            <tr>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['employee_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="fw-semibold text-muted"><?php echo htmlspecialchars($row['department']); ?></td>
                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($row['designation']); ?></td>
                                <td class="text-center fw-bold text-success"><?php echo $row['present_count']; ?> days</td>
                                <td class="text-center fw-bold text-warning"><?php echo $row['late_count']; ?> days</td>
                                <td class="text-center fw-bold text-danger"><?php echo $row['absent_count']; ?> days</td>
                                <td class="text-center fw-bold text-info"><?php echo $row['leave_count']; ?> days</td>
                                <td class="text-center fw-bold text-dark"><?php echo $totDays; ?> days</td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 3. LEAVE REPORT LOG -->
                <?php elseif ($selectedReport === 'leave_report'): ?>
                    <thead>
                        <tr>
                            <th>Employee No</th>
                            <th>Staff Name</th>
                            <th>Department</th>
                            <th>Leave Category</th>
                            <th class="text-center">Date Range</th>
                            <th class="text-center">Days</th>
                            <th>Reason</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">No staff leaves logged.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['employee_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td class="fw-semibold text-muted"><?php echo htmlspecialchars($row['department']); ?></td>
                                <td><span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($row['leave_type']); ?></span></td>
                                <td class="text-center small">
                                    <span class="fw-bold text-dark"><?php echo date('d-M-Y', strtotime($row['leave_from'])); ?></span>
                                    <div class="text-xs text-muted">to</div>
                                    <span class="fw-bold text-dark"><?php echo date('d-M-Y', strtotime($row['leave_to'])); ?></span>
                                </td>
                                <td class="text-center fw-bold text-dark"><?php echo $row['total_days']; ?> days</td>
                                <td class="small"><?php echo htmlspecialchars($row['reason'] ?: '—'); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php 
                                        echo $row['status'] === 'Approved' ? 'success' : ($row['status'] === 'Rejected' ? 'danger' : 'warning'); 
                                    ?>-soft px-3 py-1 rounded-pill fw-bold"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
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
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
