<?php
/**
 * Indus Grammar School ERP - Staff & HR Intelligence Reports
 * Version 4.0.0 (Faculty & Employee HR Analytics Suite)
 */

$pageTitle = 'Staff Reports Panel';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Selectors
$departments = [];
$designations = [];
try {
    $departments = $db->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
    $designations = $db->query("SELECT DISTINCT designation FROM staff WHERE designation IS NOT NULL AND designation != '' ORDER BY designation ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Filter parameters
$selectedReport = sanitize($_GET['report_type'] ?? 'employee_list');
$selectedDept   = sanitize($_GET['department'] ?? '');
$selectedDesig  = sanitize($_GET['designation'] ?? '');
$selectedStatus = sanitize($_GET['status'] ?? '');
$dateFrom       = sanitize($_GET['date_from'] ?? date('Y-01-01'));
$dateTo         = sanitize($_GET['date_to'] ?? date('Y-m-d'));
$searchKeyword  = sanitize($_GET['q'] ?? '');

$reportTitle = "Staff & HR Roster Report";
$reportData = [];

// Overall Scope KPIs
$kpiTotalStaff = 0;
$kpiActiveStaff = 0;
$kpiInactiveStaff = 0;
$kpiDeptCount = count($departments);
$kpiTotalLeaves = 0;
$kpiAttRatePct = 0;

try {
    // Total staff count in system
    $kpiTotalStaff    = (int)$db->query("SELECT COUNT(*) FROM staff")->fetchColumn();
    $kpiActiveStaff   = (int)$db->query("SELECT COUNT(*) FROM staff WHERE status = 'Active'")->fetchColumn();
    $kpiInactiveStaff = (int)$db->query("SELECT COUNT(*) FROM staff WHERE status = 'Inactive'")->fetchColumn();

    // Range Leaves Count
    $kpiTotalLeaves = (int)$db->query("SELECT COALESCE(SUM(total_days),0) FROM staff_leave WHERE status = 'Approved' AND leave_from BETWEEN '$dateFrom' AND '$dateTo'")->fetchColumn();

    // Attendance Rate in Range
    $attStats = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('Present', 'Late') THEN 1 ELSE 0 END) as present
        FROM staff_attendance 
        WHERE date BETWEEN '$dateFrom' AND '$dateTo'
    ")->fetch(PDO::FETCH_ASSOC);
    if ($attStats && $attStats['total'] > 0) {
        $kpiAttRatePct = round(($attStats['present'] / $attStats['total']) * 100, 1);
    }

    switch ($selectedReport) {
        
        case 'attendance_report':
            $reportTitle = "Staff Attendance Performance Summary";
            $sql = "
                SELECT s.employee_no, s.first_name, s.last_name, s.department, s.designation,
                       SUM(CASE WHEN sa.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                       SUM(CASE WHEN sa.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                       SUM(CASE WHEN sa.status = 'Late' THEN 1 ELSE 0 END) as late_count,
                       SUM(CASE WHEN sa.status = 'Leave' THEN 1 ELSE 0 END) as leave_count,
                       COUNT(sa.id) as total_working_days
                FROM staff s
                LEFT JOIN staff_attendance sa ON sa.staff_id = s.id AND sa.date BETWEEN :from AND :to
                WHERE 1=1
            ";
            $params = ['from' => $dateFrom, 'to' => $dateTo];
            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($selectedDesig !== '') {
                $sql .= " AND s.designation = :desig";
                $params['desig'] = $selectedDesig;
            }
            if ($searchKeyword !== '') {
                $sql .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.employee_no LIKE :q)";
                $params['q'] = '%' . $searchKeyword . '%';
            }
            $sql .= " GROUP BY s.id ORDER BY s.department ASC, s.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'leave_report':
            $reportTitle = "Departmental Staff Leave Ledger Log";
            $sql = "
                SELECT sl.*, s.first_name, s.last_name, s.employee_no, s.department, s.designation
                FROM staff_leave sl
                JOIN staff s ON sl.staff_id = s.id
                WHERE sl.leave_from BETWEEN :from AND :to
            ";
            $params = ['from' => $dateFrom, 'to' => $dateTo];
            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($selectedDesig !== '') {
                $sql .= " AND s.designation = :desig";
                $params['desig'] = $selectedDesig;
            }
            if ($searchKeyword !== '') {
                $sql .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.employee_no LIKE :q OR sl.leave_type LIKE :q)";
                $params['q'] = '%' . $searchKeyword . '%';
            }
            $sql .= " ORDER BY sl.leave_from DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        default:
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
            if ($searchKeyword !== '') {
                $sql .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.employee_no LIKE :q OR s.phone LIKE :q)";
                $params['q'] = '%' . $searchKeyword . '%';
            }

            if ($selectedReport === 'joining_report') {
                $reportTitle = "Employee Joining History Register";
                if ($dateFrom !== '') {
                    $sql .= " AND s.date_of_joining >= :from";
                    $params['from'] = $dateFrom;
                }
                if ($dateTo !== '') {
                    $sql .= " AND s.date_of_joining <= :to";
                    $params['to'] = $dateTo;
                }
                $sql .= " ORDER BY s.date_of_joining ASC";
            } else if ($selectedReport === 'inactive_staff') {
                $reportTitle = "Inactive Staff Directory";
                $sql .= " AND s.status = 'Inactive' ORDER BY s.first_name ASC";
            } else {
                $reportTitle = "Employee Master Directory Roster";
                $sql .= " ORDER BY s.department ASC, s.first_name ASC";
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch (Exception $e) {
    error_log("Staff report error: " . $e->getMessage());
}

$evaluatedCount = count($reportData);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

:root {
    --sf-font: 'Outfit', sans-serif;
    --sf-dark: #0f172a;
    --sf-card-bg: #ffffff;
    --sf-border: #e2e8f0;
    --sf-radius: 16px;
    --sf-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.04), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
}

body {
    font-family: var(--sf-font);
    background-color: #f8fafc;
}

.sf-hero-card {
    background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #475569 100%);
    border-radius: var(--sf-radius);
    padding: 2.25rem 2rem;
    color: #ffffff;
    box-shadow: 0 20px 25px -5px rgba(51, 65, 85, 0.25);
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
}

.sf-hero-card::before {
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

.sf-kpi-card {
    background: var(--sf-card-bg);
    border: 1px solid var(--sf-border);
    border-radius: var(--sf-radius);
    padding: 1.35rem 1.25rem;
    box-shadow: var(--sf-shadow);
    height: 100%;
    transition: transform 0.2s ease;
}

.sf-kpi-card:hover {
    transform: translateY(-3px);
}

.sf-kpi-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.sf-kpi-val {
    font-weight: 700;
    font-size: 1.55rem;
    color: var(--sf-dark);
    line-height: 1.2;
    margin-top: 0.35rem;
}

.custom-table-card {
    background: var(--sf-card-bg);
    border: 1px solid var(--sf-border);
    border-radius: var(--sf-radius);
    box-shadow: var(--sf-shadow);
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
    border-bottom: 1px solid var(--sf-border);
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
    .sf-hero-card { background: #1e293b !important; color: #fff !important; }
    #reportPrintArea { position: static !important; }
}
</style>

<div class="container-fluid px-0">

    <!-- Executive Hero Header -->
    <div class="sf-hero-card">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small fw-semibold">
                        <i class="fa-solid fa-users-gear me-1 text-warning"></i> Faculty & Staff HR Intelligence
                    </span>
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small">
                        <?php echo number_format($evaluatedCount); ?> Records Compiled
                    </span>
                </div>
                <h1 class="fw-bold mb-2" style="font-size:1.95rem; letter-spacing:-0.02em;"><?php echo sanitize($reportTitle); ?></h1>
                <p class="text-white-50 mb-0 leading-relaxed" style="max-width: 650px;">
                    Construct employee registries, analyze departmental leave applications, track staff attendance performance summaries, and audit HR records.
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
                    <select class="form-select" name="report_type" onchange="this.form.submit()">
                        <option value="employee_list" <?php echo $selectedReport === 'employee_list' ? 'selected' : ''; ?>>Master Employee Directory</option>
                        <option value="attendance_report" <?php echo $selectedReport === 'attendance_report' ? 'selected' : ''; ?>>Staff Attendance Summary</option>
                        <option value="leave_report" <?php echo $selectedReport === 'leave_report' ? 'selected' : ''; ?>>Departmental Leave Ledger</option>
                        <option value="joining_report" <?php echo $selectedReport === 'joining_report' ? 'selected' : ''; ?>>Joining History Register</option>
                        <option value="inactive_staff" <?php echo $selectedReport === 'inactive_staff' ? 'selected' : ''; ?>>Inactive Staff Directory</option>
                    </select>
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
                    <label class="form-label small fw-bold text-dark">Designation</label>
                    <select class="form-select" name="designation">
                        <option value="">All Designations</option>
                        <?php foreach ($designations as $desig): ?>
                            <option value="<?php echo htmlspecialchars($desig); ?>" <?php echo $selectedDesig === $desig ? 'selected' : ''; ?>><?php echo htmlspecialchars($desig); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <label class="form-label small fw-bold text-dark">Employment Status</label>
                    <select class="form-select" name="status" <?php echo in_array($selectedReport, ['leave_report','inactive_staff']) ? 'disabled' : ''; ?>>
                        <option value="">All Statuses</option>
                        <option value="Active" <?php echo $selectedStatus === 'Active' ? 'selected' : ''; ?>>Active Only</option>
                        <option value="Inactive" <?php echo $selectedStatus === 'Inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-dark">Search Keyword</label>
                    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="Employee #, name, designation...">
                </div>

                <div class="col-lg-6 col-md-6">
                    <label class="form-label small fw-bold text-dark">Date Range Filter</label>
                    <div class="input-group">
                        <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                        <span class="input-group-text bg-white">to</span>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                </div>

                <div class="col-lg-6 col-md-6 text-lg-end">
                    <a href="staff.php" class="btn btn-outline-secondary px-4 me-2"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
                    <button type="submit" class="btn btn-dark px-5 fw-bold"><i class="fa-solid fa-magnifying-glass me-2"></i>Compile HR Report</button>
                </div>

            </form>
        </div>
    </div>

    <!-- HR KPI Analytics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="sf-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Faculty & Staff Count</span>
                    <div class="sf-kpi-icon bg-dark bg-opacity-10 text-dark">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
                <div class="sf-kpi-val"><?php echo number_format($kpiActiveStaff); ?> <span class="fs-6 fw-normal text-muted">Active</span></div>
                <div class="mt-2 text-muted small">
                    <span class="badge bg-success bg-opacity-10 text-success fw-bold"><?php echo $kpiActiveStaff; ?> Active</span>
                    <?php if ($kpiInactiveStaff > 0): ?>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold ms-1"><?php echo $kpiInactiveStaff; ?> Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="sf-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Active Departments</span>
                    <div class="sf-kpi-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-sitemap"></i>
                    </div>
                </div>
                <div class="sf-kpi-val text-primary"><?php echo $kpiDeptCount; ?> <span class="fs-6 fw-normal text-muted">Departments</span></div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-building me-1 text-primary"></i> Categorized HR divisions
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="sf-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Staff Attendance Rate</span>
                    <div class="sf-kpi-icon bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                </div>
                <div class="sf-kpi-val text-info"><?php echo $kpiAttRatePct; ?>%</div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-clock-rotate-left me-1 text-info"></i> Range presence percentage
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="sf-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Approved Leave Days</span>
                    <div class="sf-kpi-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-plane-departure"></i>
                    </div>
                </div>
                <div class="sf-kpi-val text-warning-dark"><?php echo number_format($kpiTotalLeaves); ?> <span class="fs-6 fw-normal text-muted">Days</span></div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-calendar-minus me-1 text-warning"></i> Approved leave applications
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
                Printed Date: <?php echo date('d-M-Y H:i'); ?> | Total Records: <?php echo number_format($evaluatedCount); ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                
                <!-- 1. GENERAL EMPLOYEE DIRECTORY / JOINING / INACTIVE -->
                <?php if (in_array($selectedReport, ['employee_list', 'joining_report', 'inactive_staff'])): ?>
                    <thead>
                        <tr>
                            <th width="110">Employee #</th>
                            <th>Staff Member Name</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th>Contact Phone</th>
                            <th>Email Address</th>
                            <th class="text-center">Joining Date</th>
                            <th class="text-end">Base Salary</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="9" class="text-center py-5 text-muted">No employee records found matching the specified criteria.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td><code class="fw-bold text-primary">#<?php echo sanitize($row['employee_no']); ?></code></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                </td>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill"><?php echo sanitize($row['department'] ?: 'General'); ?></span></td>
                                <td class="fw-semibold text-dark"><?php echo sanitize($row['designation'] ?: 'Staff'); ?></td>
                                <td><i class="fa-solid fa-phone me-1 text-muted small"></i><?php echo sanitize($row['phone'] ?: '—'); ?></td>
                                <td class="small text-muted"><?php echo sanitize($row['email'] ?: '—'); ?></td>
                                <td class="text-center small fw-semibold"><?php $jDate = $row['date_of_joining'] ?? $row['joining_date'] ?? null; echo !empty($jDate) ? date('d-M-Y', strtotime($jDate)) : '—'; ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format((float)($row['salary'] ?? 0), 2); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $row['status'] === 'Active' ? 'success' : 'secondary'; ?> bg-opacity-10 text-<?php echo $row['status'] === 'Active' ? 'success' : 'secondary'; ?> px-3 py-1 rounded-pill fw-semibold">
                                        <?php echo sanitize($row['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 2. STAFF ATTENDANCE SUMMARY -->
                <?php elseif ($selectedReport === 'attendance_report'): ?>
                    <thead>
                        <tr>
                            <th width="110">Employee #</th>
                            <th>Staff Member Name</th>
                            <th>Department & Designation</th>
                            <th class="text-center text-success">Present Days</th>
                            <th class="text-center text-warning">Late Days</th>
                            <th class="text-center text-danger">Absent Days</th>
                            <th class="text-center text-info">Leave Days</th>
                            <th class="text-center">Total Evaluated</th>
                            <th class="text-end">Attendance Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="9" class="text-center py-5 text-muted">No staff attendance logs found for this date range.</td></tr>
                        <?php else: foreach ($reportData as $row): 
                            $p = (int)$row['present_count'];
                            $l = (int)$row['late_count'];
                            $a = (int)$row['absent_count'];
                            $v = (int)$row['leave_count'];
                            $totDays = (int)$row['total_working_days'];
                            $ratePct = $totDays > 0 ? round((($p + $l) / $totDays) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><code class="fw-bold text-primary">#<?php echo sanitize($row['employee_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td>
                                    <div class="fw-semibold small text-dark"><?php echo sanitize($row['department']); ?></div>
                                    <div class="text-muted text-xs"><?php echo sanitize($row['designation']); ?></div>
                                </td>
                                <td class="text-center fw-bold text-success"><?php echo $p; ?> Days</td>
                                <td class="text-center fw-bold text-warning-dark"><?php echo $l; ?> Days</td>
                                <td class="text-center fw-bold text-danger"><?php echo $a; ?> Days</td>
                                <td class="text-center fw-bold text-info"><?php echo $v; ?> Days</td>
                                <td class="text-center fw-bold text-dark"><?php echo $totDays; ?> Days</td>
                                <td class="text-end">
                                    <span class="badge bg-<?php echo $ratePct >= 80 ? 'success' : ($ratePct >= 60 ? 'warning' : 'danger'); ?> bg-opacity-10 text-<?php echo $ratePct >= 80 ? 'success' : ($ratePct >= 60 ? 'warning-dark' : 'danger'); ?> px-3 py-1 rounded-pill fw-bold">
                                        <?php echo $ratePct; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- 3. DEPARTMENTAL LEAVE REPORT LOG -->
                <?php elseif ($selectedReport === 'leave_report'): ?>
                    <thead>
                        <tr>
                            <th width="110">Employee #</th>
                            <th>Staff Member Name</th>
                            <th>Department</th>
                            <th>Leave Category</th>
                            <th class="text-center">Leave Duration</th>
                            <th class="text-center">Total Days</th>
                            <th>Leave Reason</th>
                            <th class="text-center">Approval Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">No departmental leave logs found for this date range.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td><code class="fw-bold text-primary">#<?php echo sanitize($row['employee_no']); ?></code></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                    <small class="text-muted text-xs"><?php echo sanitize($row['designation']); ?></small>
                                </td>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill"><?php echo sanitize($row['department']); ?></span></td>
                                <td><span class="badge bg-info bg-opacity-10 text-info px-3 py-1 rounded-pill fw-semibold"><?php echo sanitize($row['leave_type']); ?></span></td>
                                <td class="text-center small">
                                    <span class="fw-bold text-dark"><?php echo date('d-M-Y', strtotime($row['leave_from'])); ?></span>
                                    <div class="text-xs text-muted">to</div>
                                    <span class="fw-bold text-dark"><?php echo date('d-M-Y', strtotime($row['leave_to'])); ?></span>
                                </td>
                                <td class="text-center fw-bold text-dark"><?php echo (int)$row['total_days']; ?> Days</td>
                                <td class="small text-muted"><?php echo sanitize($row['reason'] ?: '—'); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php 
                                        echo $row['status'] === 'Approved' ? 'success' : ($row['status'] === 'Rejected' ? 'danger' : 'warning'); 
                                    ?> bg-opacity-10 text-<?php 
                                        echo $row['status'] === 'Approved' ? 'success' : ($row['status'] === 'Rejected' ? 'danger' : 'warning-dark'); 
                                    ?> px-3 py-1 rounded-pill fw-bold">
                                        <?php echo sanitize($row['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                <?php endif; ?>

            </table>
        </div>
    </div>

</div>

<script>
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
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>