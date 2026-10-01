<?php
/**
 * Indus Grammar School ERP - Attendance Reports & Aggregates Suite
 * Version 4.0.0 (Executive Attendance Analytics & Monthly Aggregates)
 */

$pageTitle = 'Attendance Reports Panel';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Selectors
$classes = SchoolClass::all();
$departments = [];
try {
    $departments = $db->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Filter parameters
$selectedMode   = sanitize($_GET['report_mode'] ?? 'student');
$selectedType   = sanitize($_GET['report_type'] ?? 'daily');
$selectedDate   = sanitize($_GET['date'] ?? date('Y-m-d'));
$selectedMonth  = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear   = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedClass  = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedDept   = sanitize($_GET['department'] ?? '');
$searchKeyword  = sanitize($_GET['q'] ?? '');

$reportTitle = "Attendance Register";
$reportData = [];
$monthlyAggregates = [];

$totalRecords = 0;
$presentCount = 0;
$lateCount = 0;
$absentCount = 0;
$leaveCount = 0;

try {
    if ($selectedMode === 'student') {
        
        if ($selectedType === 'monthly') {
            $reportTitle = "Monthly Student Attendance Aggregates (" . date('F', mktime(0, 0, 0, $selectedMonth, 1)) . " $selectedYear)";
            
            $sql = "
                SELECT 
                    st.id as student_id, st.admission_no, st.first_name, st.last_name, c.class_name, c.section,
                    COUNT(a.id) as total_days,
                    SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_days,
                    SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN a.status = 'Leave' THEN 1 ELSE 0 END) as leave_days
                FROM students st
                JOIN classes c ON st.class_id = c.id
                LEFT JOIN attendance a ON st.id = a.student_id AND MONTH(a.date) = :month AND YEAR(a.date) = :year
                WHERE st.status = 'Active'
            ";
            $params = ['month' => $selectedMonth, 'year' => $selectedYear];

            if ($selectedClass > 0) {
                $sql .= " AND st.class_id = :cid";
                $params['cid'] = $selectedClass;
            }
            if ($searchKeyword !== '') {
                $sql .= " AND (st.first_name LIKE :q OR st.last_name LIKE :q OR st.admission_no LIKE :q)";
                $params['q'] = '%' . $searchKeyword . '%';
            }

            $sql .= " GROUP BY st.id ORDER BY c.class_name ASC, st.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $monthlyAggregates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($monthlyAggregates as $row) {
                $presentCount += (int)$row['present_days'];
                $lateCount    += (int)$row['late_days'];
                $absentCount  += (int)$row['absent_days'];
                $leaveCount   += (int)$row['leave_days'];
            }
            $totalRecords = count($monthlyAggregates);

        } else {
            // Daily / Late / Absent / Leave
            $sql = "
                SELECT a.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section
                FROM attendance a
                JOIN students st ON a.student_id = st.id
                JOIN classes c ON a.class_id = c.id
                WHERE 1=1
            ";
            $params = [];

            if ($selectedType === 'daily') {
                $reportTitle = "Daily Student Attendance Register (" . date('d M Y', strtotime($selectedDate)) . ")";
                $sql .= " AND a.date = :date";
                $params['date'] = $selectedDate;
            } else if ($selectedType === 'late') {
                $reportTitle = "Late Student Arrival List";
                $sql .= " AND a.status = 'Late'";
                if (!empty($selectedDate)) {
                    $sql .= " AND a.date = :date";
                    $params['date'] = $selectedDate;
                }
            } else if ($selectedType === 'absent') {
                $reportTitle = "Absent Student Unexcused Roster";
                $sql .= " AND a.status = 'Absent'";
                if (!empty($selectedDate)) {
                    $sql .= " AND a.date = :date";
                    $params['date'] = $selectedDate;
                }
            } else if ($selectedType === 'leave') {
                $reportTitle = "Student Leave Applications Log";
                $sql .= " AND a.status = 'Leave'";
                if (!empty($selectedDate)) {
                    $sql .= " AND a.date = :date";
                    $params['date'] = $selectedDate;
                }
            }

            if ($selectedClass > 0) {
                $sql .= " AND a.class_id = :cid";
                $params['cid'] = $selectedClass;
            }
            if ($searchKeyword !== '') {
                $sql .= " AND (st.first_name LIKE :q OR st.last_name LIKE :q OR st.admission_no LIKE :q)";
                $params['q'] = '%' . $searchKeyword . '%';
            }

            $sql .= " ORDER BY a.date DESC, st.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($reportData as $row) {
                if ($row['status'] === 'Present') $presentCount++;
                elseif ($row['status'] === 'Late') $lateCount++;
                elseif ($row['status'] === 'Absent') $absentCount++;
                elseif ($row['status'] === 'Leave') $leaveCount++;
            }
            $totalRecords = count($reportData);
        }

    } else {
        // Staff Mode
        if ($selectedType === 'monthly') {
            $reportTitle = "Monthly Employee Attendance Aggregates (" . date('F', mktime(0, 0, 0, $selectedMonth, 1)) . " $selectedYear)";

            $sql = "
                SELECT 
                    s.id as staff_id, s.employee_no, s.first_name, s.last_name, s.department, s.designation,
                    COUNT(sa.id) as total_days,
                    SUM(CASE WHEN sa.status = 'Present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN sa.status = 'Late' THEN 1 ELSE 0 END) as late_days,
                    SUM(CASE WHEN sa.status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN sa.status = 'Leave' THEN 1 ELSE 0 END) as leave_days
                FROM staff s
                LEFT JOIN staff_attendance sa ON s.id = sa.staff_id AND MONTH(sa.date) = :month AND YEAR(sa.date) = :year
                WHERE s.status = 'Active'
            ";
            $params = ['month' => $selectedMonth, 'year' => $selectedYear];

            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($searchKeyword !== '') {
                $sql .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.employee_no LIKE :q)";
                $params['q'] = '%' . $searchKeyword . '%';
            }

            $sql .= " GROUP BY s.id ORDER BY s.department ASC, s.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $monthlyAggregates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($monthlyAggregates as $row) {
                $presentCount += (int)$row['present_days'];
                $lateCount    += (int)$row['late_days'];
                $absentCount  += (int)$row['absent_days'];
                $leaveCount   += (int)$row['leave_days'];
            }
            $totalRecords = count($monthlyAggregates);

        } else {
            // Daily / Late / Absent / Leave
            $sql = "
                SELECT sa.*, s.first_name, s.last_name, s.employee_no, s.department, s.designation
                FROM staff_attendance sa
                JOIN staff s ON sa.staff_id = s.id
                WHERE 1=1
            ";
            $params = [];

            if ($selectedType === 'daily') {
                $reportTitle = "Daily Employee Attendance Register (" . date('d M Y', strtotime($selectedDate)) . ")";
                $sql .= " AND sa.date = :date";
                $params['date'] = $selectedDate;
            } else if ($selectedType === 'late') {
                $reportTitle = "Late Employee Arrival List";
                $sql .= " AND sa.status = 'Late'";
                if (!empty($selectedDate)) {
                    $sql .= " AND sa.date = :date";
                    $params['date'] = $selectedDate;
                }
            } else if ($selectedType === 'absent') {
                $reportTitle = "Absent Employee Unexcused Roster";
                $sql .= " AND sa.status = 'Absent'";
                if (!empty($selectedDate)) {
                    $sql .= " AND sa.date = :date";
                    $params['date'] = $selectedDate;
                }
            } else if ($selectedType === 'leave') {
                $reportTitle = "Employee Leave Journal";
                $sql .= " AND sa.status = 'Leave'";
                if (!empty($selectedDate)) {
                    $sql .= " AND sa.date = :date";
                    $params['date'] = $selectedDate;
                }
            }

            if ($selectedDept !== '') {
                $sql .= " AND s.department = :dept";
                $params['dept'] = $selectedDept;
            }
            if ($searchKeyword !== '') {
                $sql .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.employee_no LIKE :q)";
                $params['q'] = '%' . $searchKeyword . '%';
            }

            $sql .= " ORDER BY sa.date DESC, s.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($reportData as $row) {
                if ($row['status'] === 'Present') $presentCount++;
                elseif ($row['status'] === 'Late') $lateCount++;
                elseif ($row['status'] === 'Absent') $absentCount++;
                elseif ($row['status'] === 'Leave') $leaveCount++;
            }
            $totalRecords = count($reportData);
        }
    }
} catch (Exception $e) {
    error_log("Attendance report error: " . $e->getMessage());
}

$sumEntries = ($presentCount + $lateCount + $absentCount + $leaveCount);
$presentRatePct = $sumEntries > 0 ? round((($presentCount + $lateCount) / $sumEntries) * 100, 1) : 0;
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

:root {
    --att-font: 'Outfit', sans-serif;
    --att-primary: #10b981;
    --att-dark: #0f172a;
    --att-card-bg: #ffffff;
    --att-border: #e2e8f0;
    --att-radius: 16px;
    --att-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.04), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
}

body {
    font-family: var(--att-font);
    background-color: #f8fafc;
}

.att-hero-card {
    background: linear-gradient(135deg, #064e3b 0%, #047857 50%, #10b981 100%);
    border-radius: var(--att-radius);
    padding: 2.25rem 2rem;
    color: #ffffff;
    box-shadow: 0 20px 25px -5px rgba(16, 185, 129, 0.25);
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
}

.att-hero-card::before {
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

.att-kpi-card {
    background: var(--att-card-bg);
    border: 1px solid var(--att-border);
    border-radius: var(--att-radius);
    padding: 1.35rem 1.25rem;
    box-shadow: var(--att-shadow);
    height: 100%;
    transition: transform 0.2s ease;
}

.att-kpi-card:hover {
    transform: translateY(-3px);
}

.att-kpi-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.att-kpi-val {
    font-weight: 700;
    font-size: 1.55rem;
    color: var(--att-dark);
    line-height: 1.2;
    margin-top: 0.35rem;
}

.custom-table-card {
    background: var(--att-card-bg);
    border: 1px solid var(--att-border);
    border-radius: var(--att-radius);
    box-shadow: var(--att-shadow);
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
    border-bottom: 1px solid var(--att-border);
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
    .att-hero-card { background: #064e3b !important; color: #fff !important; }
    #reportPrintArea { position: static !important; }
}
</style>

<div class="container-fluid px-0">

    <!-- Executive Hero Header -->
    <div class="att-hero-card">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small fw-semibold">
                        <i class="fa-solid fa-clock-rotate-left me-1 text-warning"></i> Attendance Analytics & Audit
                    </span>
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small text-capitalize">
                        <?php echo $selectedMode; ?> Mode • <?php echo number_format($totalRecords); ?> Records
                    </span>
                </div>
                <h1 class="fw-bold mb-2" style="font-size:1.95rem; letter-spacing:-0.02em;"><?php echo sanitize($reportTitle); ?></h1>
                <p class="text-white-50 mb-0 leading-relaxed" style="max-width: 650px;">
                    Review student and employee registers, check late lists, audit unexcused absentees, and extract monthly attendance aggregates.
                </p>
            </div>
            <div class="col-lg-5 text-lg-end mt-4 mt-lg-0 no-print">
                <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end">
                    <button class="btn btn-light fw-bold text-dark px-4 py-2 rounded-3 shadow-sm" onclick="exportToExcel()">
                        <i class="fa-solid fa-file-excel text-success me-2"></i>Export Excel / CSV
                    </button>
                    <button class="btn btn-outline-light px-3 py-2 rounded-3" onclick="window.print()">
                        <i class="fa-solid fa-print me-2"></i>Print Sheet
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
                    <label class="form-label small fw-bold text-dark">Module Target</label>
                    <select class="form-select" name="report_mode" id="reportMode" onchange="toggleFormFields(); this.form.submit();">
                        <option value="student" <?php echo $selectedMode === 'student' ? 'selected' : ''; ?>>Student Attendance</option>
                        <option value="staff" <?php echo $selectedMode === 'staff' ? 'selected' : ''; ?>>Staff / Employee Attendance</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-dark">Report Category</label>
                    <select class="form-select" name="report_type" id="reportType" onchange="toggleFormFields()">
                        <option value="daily" <?php echo $selectedType === 'daily' ? 'selected' : ''; ?>>Daily Attendance Register</option>
                        <option value="monthly" <?php echo $selectedType === 'monthly' ? 'selected' : ''; ?>>Monthly Aggregates Summary</option>
                        <option value="late" <?php echo $selectedType === 'late' ? 'selected' : ''; ?>>Late Arrivals List</option>
                        <option value="absent" <?php echo $selectedType === 'absent' ? 'selected' : ''; ?>>Absentees Roster</option>
                        <option value="leave" <?php echo $selectedType === 'leave' ? 'selected' : ''; ?>>Approved Leave Journal</option>
                    </select>
                </div>

                <!-- Class Field (Students Only) -->
                <div class="col-lg-3 col-md-6 filter-field" id="classField">
                    <label class="form-label small fw-bold text-dark">Class & Section</label>
                    <select class="form-select" name="class_id">
                        <option value="0">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Dept Field (Staff Only) -->
                <div class="col-lg-3 col-md-6 filter-field" id="deptField" style="display:none;">
                    <label class="form-label small fw-bold text-dark">Department</label>
                    <select class="form-select" name="department">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $selectedDept === $d ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date Field (Daily/Late/Absent/Leave) -->
                <div class="col-lg-3 col-md-6 filter-field" id="dateField">
                    <label class="form-label small fw-bold text-dark">Target Date</label>
                    <input type="date" class="form-control" name="date" value="<?php echo $selectedDate; ?>">
                </div>

                <!-- Month/Year Fields (Monthly Only) -->
                <div class="col-lg-2 col-md-4 filter-field" id="monthField" style="display:none;">
                    <label class="form-label small fw-bold text-dark">Month</label>
                    <select class="form-select" name="month">
                        <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $selectedMonth === $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-lg-1 col-md-2 filter-field" id="yearField" style="display:none;">
                    <label class="form-label small fw-bold text-dark">Year</label>
                    <input type="number" class="form-control" name="year" value="<?php echo $selectedYear; ?>" min="2020" max="2035">
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-dark">Search Person</label>
                    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="Name, Roll / Admission #...">
                </div>

                <div class="col-12 text-end">
                    <a href="attendance.php" class="btn btn-outline-secondary px-4 me-2"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
                    <button type="submit" class="btn btn-success px-5 fw-bold"><i class="fa-solid fa-magnifying-glass me-2"></i>Compile Attendance</button>
                </div>

            </form>
        </div>
    </div>

    <!-- Executive KPI Analytics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="att-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Evaluated People / Entries</span>
                    <div class="att-kpi-icon bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
                <div class="att-kpi-val"><?php echo number_format($totalRecords); ?></div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-clipboard-check me-1 text-success"></i> <?php echo ucfirst($selectedMode); ?> records compiled
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="att-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Attendance Rate</span>
                    <div class="att-kpi-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                </div>
                <div class="att-kpi-val text-primary"><?php echo $presentRatePct; ?>%</div>
                <div class="mt-2 text-muted small">
                    <span class="fw-bold text-success"><?php echo number_format($presentCount); ?> Present</span> | <?php echo number_format($lateCount); ?> Late
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="att-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Late Arrivals</span>
                    <div class="att-kpi-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                </div>
                <div class="att-kpi-val text-warning-dark"><?php echo number_format($lateCount); ?></div>
                <div class="mt-2 text-muted small">
                    <i class="fa-solid fa-hourglass-half me-1"></i> Check-in after threshold
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="att-kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Absences & Leaves</span>
                    <div class="att-kpi-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fa-solid fa-user-xmark"></i>
                    </div>
                </div>
                <div class="att-kpi-val text-danger"><?php echo number_format($absentCount); ?> <span class="fs-6 fw-normal text-muted">Absent</span></div>
                <div class="mt-2 text-muted small">
                    <span class="fw-bold text-info"><?php echo number_format($leaveCount); ?> Approved Leaves</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Roster Output Card -->
    <div class="custom-table-card shadow-sm mb-4" id="reportPrintArea">
        
        <!-- Print Header -->
        <div class="p-4 text-center d-none d-print-block border-bottom">
            <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h2>
            <h4 class="text-secondary fw-semibold mb-1"><?php echo sanitize($reportTitle); ?></h4>
            <div class="text-muted small">
                Printed Date: <?php echo date('d-M-Y H:i'); ?> | Total Entries: <?php echo number_format($totalRecords); ?>
            </div>
        </div>

        <div class="table-responsive">
            <?php if ($selectedType === 'monthly'): ?>
                <!-- MONTHLY AGGREGATES MATRIX TABLE -->
                <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                    <thead>
                        <tr>
                            <th width="70">#</th>
                            <?php if ($selectedMode === 'student'): ?>
                                <th>Admission #</th>
                                <th>Student Full Name</th>
                                <th>Class & Section</th>
                            <?php else: ?>
                                <th>Employee #</th>
                                <th>Employee Full Name</th>
                                <th>Department & Designation</th>
                            <?php endif; ?>
                            <th class="text-center">Days Evaluated</th>
                            <th class="text-center text-success">Present</th>
                            <th class="text-center text-warning">Late</th>
                            <th class="text-center text-danger">Absent</th>
                            <th class="text-center text-info">Leave</th>
                            <th class="text-end">Monthly Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($monthlyAggregates)): ?>
                            <tr><td colspan="9" class="text-center py-5 text-muted">No monthly attendance records found for this period.</td></tr>
                        <?php else: foreach ($monthlyAggregates as $idx => $row): 
                            $tDays = (int)$row['total_days'];
                            $pDays = (int)$row['present_days'];
                            $lDays = (int)$row['late_days'];
                            $aDays = (int)$row['absent_days'];
                            $vDays = (int)$row['leave_days'];
                            
                            $ratePct = $tDays > 0 ? round((($pDays + $lDays) / $tDays) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td class="text-muted fw-bold"><?php echo $idx + 1; ?></td>
                                <?php if ($selectedMode === 'student'): ?>
                                    <td><code class="fw-bold text-primary">#<?php echo sanitize($row['admission_no']); ?></code></td>
                                    <td class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td><span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill"><?php echo sanitize($row['class_name'] . ' - ' . $row['section']); ?></span></td>
                                <?php else: ?>
                                    <td><code class="fw-bold text-primary">#<?php echo sanitize($row['employee_no']); ?></code></td>
                                    <td class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td>
                                        <div class="fw-semibold small text-dark"><?php echo sanitize($row['department']); ?></div>
                                        <div class="text-muted text-xs"><?php echo sanitize($row['designation']); ?></div>
                                    </td>
                                <?php endif; ?>
                                <td class="text-center fw-bold text-dark"><?php echo $tDays; ?> Days</td>
                                <td class="text-center fw-bold text-success"><?php echo $pDays; ?></td>
                                <td class="text-center fw-bold text-warning-dark"><?php echo $lDays; ?></td>
                                <td class="text-center fw-bold text-danger"><?php echo $aDays; ?></td>
                                <td class="text-center fw-bold text-info"><?php echo $vDays; ?></td>
                                <td class="text-end">
                                    <span class="badge bg-<?php echo $ratePct >= 80 ? 'success' : ($ratePct >= 60 ? 'warning' : 'danger'); ?> bg-opacity-10 text-<?php echo $ratePct >= 80 ? 'success' : ($ratePct >= 60 ? 'warning-dark' : 'danger'); ?> px-3 py-1 rounded-pill fw-bold">
                                        <?php echo $ratePct; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <!-- DAILY / LATE / ABSENT / LEAVE TABLE -->
                <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                    <thead>
                        <tr>
                            <th width="70">#</th>
                            <?php if ($selectedMode === 'student'): ?>
                                <th>Admission #</th>
                                <th>Student Full Name</th>
                                <th>Class & Section</th>
                            <?php else: ?>
                                <th>Employee #</th>
                                <th>Staff Full Name</th>
                                <th>Department & Designation</th>
                            <?php endif; ?>
                            <th class="text-center">Date</th>
                            <?php if ($selectedMode === 'staff'): ?>
                                <th class="text-center">Time Logs</th>
                            <?php endif; ?>
                            <th class="text-center">Status</th>
                            <th>Remarks / Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">No attendance entries found matching the criteria.</td></tr>
                        <?php else: foreach ($reportData as $i => $row): ?>
                            <tr>
                                <td class="text-muted fw-bold"><?php echo $i + 1; ?></td>
                                <?php if ($selectedMode === 'student'): ?>
                                    <td><code class="fw-bold text-primary">#<?php echo sanitize($row['admission_no']); ?></code></td>
                                    <td class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td><span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill"><?php echo sanitize($row['class_name'] . ' - ' . $row['section']); ?></span></td>
                                <?php else: ?>
                                    <td><code class="fw-bold text-primary">#<?php echo sanitize($row['employee_no']); ?></code></td>
                                    <td class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td>
                                        <div class="fw-semibold small text-dark"><?php echo sanitize($row['department']); ?></div>
                                        <div class="text-muted text-xs"><?php echo sanitize($row['designation']); ?></div>
                                    </td>
                                <?php endif; ?>
                                <td class="text-center small fw-semibold"><?php echo date('d-M-Y', strtotime($row['date'])); ?></td>
                                <?php if ($selectedMode === 'staff'): ?>
                                    <td class="text-center small">
                                        <?php if (!empty($row['check_in_time'])): ?>
                                            <i class="fa-regular fa-clock me-1 text-muted"></i><?php echo date('h:i A', strtotime($row['check_in_time'])); ?> - <?php echo !empty($row['check_out_time']) ? date('h:i A', strtotime($row['check_out_time'])) : '—'; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <span class="badge bg-<?php 
                                        echo $row['status'] === 'Present' ? 'success' : ($row['status'] === 'Absent' ? 'danger' : ($row['status'] === 'Late' ? 'warning' : 'info')); 
                                    ?> bg-opacity-10 text-<?php 
                                        echo $row['status'] === 'Present' ? 'success' : ($row['status'] === 'Absent' ? 'danger' : ($row['status'] === 'Late' ? 'warning-dark' : 'info')); 
                                    ?> px-3 py-1 rounded-pill fw-bold">
                                        <?php echo sanitize($row['status']); ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?php echo sanitize($row['remarks'] ?: '—'); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
function toggleFormFields() {
    const mode = document.getElementById("reportMode").value;
    const type = document.getElementById("reportType").value;

    const classField = document.getElementById("classField");
    const deptField = document.getElementById("deptField");
    const dateField = document.getElementById("dateField");
    const monthField = document.getElementById("monthField");
    const yearField = document.getElementById("yearField");

    if (mode === "student") {
        classField.style.display = "block";
        deptField.style.display = "none";
    } else {
        classField.style.display = "none";
        deptField.style.display = "block";
    }

    if (type === "monthly") {
        dateField.style.display = "none";
        monthField.style.display = "block";
        yearField.style.display = "block";
    } else {
        dateField.style.display = "block";
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
    toggleFormFields();
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
