<?php
/**
 * Indus Grammar School ERP - Printable Monthly Attendance & Analytics Report Template
 * Executive A4 Landscape Monthly Attendance Sheet & Analytics
 * Version 4.5.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

AuthMiddleware::requireLogin();

$db = Database::getConnection();

// Retrieve & Sanitize Filters
$selectedMonth        = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if ($selectedMonth < 1 || $selectedMonth > 12) $selectedMonth = (int)date('n');

$selectedYear         = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($selectedYear < 2000 || $selectedYear > 2100) $selectedYear = (int)date('Y');

$filter_session       = sanitize($_GET['session'] ?? '');
$filter_campus        = sanitize($_GET['campus'] ?? '');
$filter_academic_type = sanitize($_GET['academic_type'] ?? '');
$filter_class         = sanitize($_GET['class'] ?? '');
$filter_section       = sanitize($_GET['section'] ?? '');
$filter_status        = sanitize($_GET['student_status'] ?? 'Active');
$filter_search        = sanitize($_GET['search'] ?? '');

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
$monthStart  = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
$monthEnd    = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $daysInMonth);

$monthsList = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
    7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$monthName = $monthsList[$selectedMonth];

// Build Filter Clause for Students
$where = " WHERE 1=1";
$params = [];

if ($filter_status !== '' && $filter_status !== 'All') {
    $where .= " AND s.status = :student_status";
    $params['student_status'] = $filter_status;
}

if ($filter_session !== '') {
    $where .= " AND d.academic_session = :session";
    $params['session'] = $filter_session;
}

if ($filter_campus !== '') {
    $where .= " AND (d.campus = :campus OR (:campus_check = 'Main Campus' AND (d.campus IS NULL OR d.campus = '')))";
    $params['campus'] = $filter_campus;
    $params['campus_check'] = $filter_campus;
}

if ($filter_academic_type !== '') {
    $where .= " AND s.academic_type = :academic_type";
    $params['academic_type'] = $filter_academic_type;
}

if ($filter_class !== '') {
    $where .= " AND (c.class_name = :class1 OR s.school_class = :class2)";
    $params['class1'] = $filter_class;
    $params['class2'] = $filter_class;
}

if ($filter_section !== '') {
    $where .= " AND (c.section = :section1 OR s.school_section = :section2)";
    $params['section1'] = $filter_section;
    $params['section2'] = $filter_section;
}

if ($filter_search !== '') {
    $where .= " AND (s.first_name LIKE :search1 OR s.last_name LIKE :search2 OR CONCAT(s.first_name, ' ', s.last_name) LIKE :search3 OR s.admission_no LIKE :search4 OR d.roll_no LIKE :search5)";
    $params['search1'] = '%' . $filter_search . '%';
    $params['search2'] = '%' . $filter_search . '%';
    $params['search3'] = '%' . $filter_search . '%';
    $params['search4'] = '%' . $filter_search . '%';
    $params['search5'] = '%' . $filter_search . '%';
}

$allMatchedStudents = [];
$attendanceMap = [];
$dailyTrends = [];
$workingDaysSet = [];

$overallPresent = 0;
$overallAbsent = 0;
$overallLate = 0;
$overallLeave = 0;
$overallRate = 0.0;
$lowAttendanceRiskCount = 0;

try {
    $stmtAllSt = $db->prepare("
        SELECT s.id, s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section,
               c.class_name, c.section, d.roll_no, COALESCE(d.campus, 'Main Campus') as campus_name, d.father_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY c.class_name ASC, c.section ASC, d.roll_no ASC, s.first_name ASC
    ");
    $stmtAllSt->execute($params);
    $allMatchedStudents = $stmtAllSt->fetchAll(PDO::FETCH_ASSOC);

    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dailyTrends[$d] = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Leave' => 0, 'TotalMarked' => 0];
    }

    if (!empty($allMatchedStudents)) {
        $allStudentIds = array_column($allMatchedStudents, 'id');
        $inQuery = implode(',', array_fill(0, count($allStudentIds), '?'));

        $sqlAtt = "
            SELECT student_id, date, status 
            FROM attendance
            WHERE date BETWEEN ? AND ? 
              AND student_id IN ($inQuery)
            ORDER BY date ASC
        ";

        $stmtAtt = $db->prepare($sqlAtt);
        $queryArgs = array_merge([$monthStart, $monthEnd], $allStudentIds);
        $stmtAtt->execute($queryArgs);
        $attRows = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($attRows as $r) {
            $stId = (int)$r['student_id'];
            $day = (int)date('j', strtotime($r['date']));
            $status = $r['status'];

            $attendanceMap[$stId][$day] = $status;
            $workingDaysSet[$r['date']] = true;

            if (isset($dailyTrends[$day])) {
                if (isset($dailyTrends[$day][$status])) {
                    $dailyTrends[$day][$status]++;
                }
                $dailyTrends[$day]['TotalMarked']++;
            }

            if ($status === 'Present') $overallPresent++;
            elseif ($status === 'Absent') $overallAbsent++;
            elseif ($status === 'Late') $overallLate++;
            elseif ($status === 'Leave') $overallLeave++;
        }

        foreach ($allMatchedStudents as $st) {
            $stId = $st['id'];
            $p = 0; $lt = 0; $marked = 0;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $stStatus = $attendanceMap[$stId][$d] ?? '';
                if ($stStatus !== '') {
                    $marked++;
                    if ($stStatus === 'Present') $p++;
                    elseif ($stStatus === 'Late') $lt++;
                }
            }
            if ($marked > 0) {
                $stRate = (($p + $lt) / $marked) * 100;
                if ($stRate < 75.0) {
                    $lowAttendanceRiskCount++;
                }
            }
        }
    }
} catch (Exception $e) {
    die("Error generating monthly attendance printable report: " . $e->getMessage());
}

$workingDaysCount = count($workingDaysSet);
$totalStudentsCount = count($allMatchedStudents);
$totalRecordsMarked = $overallPresent + $overallAbsent + $overallLate + $overallLeave;

if ($totalRecordsMarked > 0) {
    $overallRate = round((($overallPresent + $overallLate) / $totalRecordsMarked) * 100, 1);
}

$schoolLogoUrl = getSchoolLogoUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Attendance Report - <?php echo $monthName . ' ' . $selectedYear; ?> - Indus Grammar School</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Outfit:wght@400;500;600;700;800&display=swap');

        @page {
            size: A4 landscape;
            margin: 4mm 6mm;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            font-size: 11px;
            margin: 0;
            padding: 0;
        }

        .action-toolbar {
            background: #ffffff;
            border-bottom: 2px solid #e2e8f0;
            padding: 10px 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .print-container {
            max-width: 1400px;
            margin: 15px auto;
            background: #ffffff;
            border-radius: 12px;
            padding: 18px 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            border: 1px solid #cbd5e1;
        }

        .school-header-banner {
            border-bottom: 3px double #1e3a8a;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .school-logo {
            max-height: 65px;
            width: auto;
            object-fit: contain;
        }

        .school-title {
            font-family: 'Cinzel', serif;
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .report-title-badge {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            color: #ffffff;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 11px;
            padding: 4px 14px;
            border-radius: 20px;
            display: inline-block;
        }

        .metrics-ribbon {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 12px;
            margin-bottom: 12px;
        }

        .metric-box {
            text-align: center;
            border-right: 1px solid #cbd5e1;
        }

        .metric-box:last-child {
            border-right: none;
        }

        .metric-val {
            font-size: 14px;
            font-weight: 800;
            line-height: 1.1;
        }

        .metric-lbl {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 700;
        }

        .matrix-print-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 12px;
        }

        .matrix-print-table th, .matrix-print-table td {
            border: 1px solid #cbd5e1;
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
        }

        .matrix-print-table th {
            background-color: #0f172a !important;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 8.5px;
            text-transform: uppercase;
        }

        .matrix-print-table td.st-name {
            text-align: left;
            padding-left: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
            font-weight: 600;
        }

        .att-cell-p { background-color: #f0fdf4 !important; color: #16a34a !important; font-weight: 700; }
        .att-cell-a { background-color: #fef2f2 !important; color: #dc2626 !important; font-weight: 700; }
        .att-cell-l { background-color: #f5f3ff !important; color: #7c3aed !important; font-weight: 700; }
        .att-cell-lt { background-color: #fffbeb !important; color: #d97706 !important; font-weight: 700; }
        .att-cell-off { background-color: #f8fafc !important; color: #94a3b8 !important; }

        .signature-footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px dashed #cbd5e1;
        }

        .sig-box {
            border-top: 1px solid #0f172a;
            padding-top: 4px;
            margin-top: 35px;
            text-align: center;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @media print {
            .action-toolbar {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .print-container {
                box-shadow: none !important;
                border: none !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .matrix-print-table th {
                background-color: #0f172a !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .att-cell-p, .att-cell-a, .att-cell-l, .att-cell-lt, .att-cell-off {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- Floating Action Toolbar -->
    <div class="action-toolbar d-print-none">
        <div class="d-flex justify-content-between align-items-center max-w-1400 mx-auto">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-calendar-check text-primary fs-4"></i>
                <div>
                    <h6 class="fw-bold mb-0">Monthly Attendance Report Preview</h6>
                    <span class="text-muted small"><?php echo $monthName . ' ' . $selectedYear; ?> • A4 Landscape Printable Format</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary fw-semibold px-4" onclick="window.print()">
                    <i class="fa-solid fa-print me-2"></i>Print Report Sheet
                </button>
                <button type="button" class="btn btn-outline-secondary fw-semibold px-3" onclick="window.close()">
                    <i class="fa-solid fa-xmark me-1"></i>Close
                </button>
            </div>
        </div>
    </div>

    <!-- Printable Content Sheet -->
    <div class="print-container">
        
        <!-- Header Banner -->
        <div class="school-header-banner">
            <div class="row align-items-center">
                <div class="col-2 text-start">
                    <img src="<?php echo $schoolLogoUrl; ?>" alt="Indus Grammar School Logo" class="school-logo">
                </div>
                <div class="col-8 text-center">
                    <div class="school-title">INDUS GRAMMAR SCHOOL</div>
                    <div class="text-muted small fw-semibold" style="font-size:10px;">
                        Main Campus • Helpline: +92 307 4918603 • Email: info@indusgrammar.edu.pk
                    </div>
                    <div class="mt-1">
                        <span class="report-title-badge">Monthly Student Attendance Matrix & Analytics</span>
                    </div>
                </div>
                <div class="col-2 text-end">
                    <div class="border rounded p-1 text-center bg-light" style="font-size:9px;">
                        <div class="fw-bold text-uppercase" style="color:#1e3a8a;">Academic Report</div>
                        <div class="fw-semibold text-dark"><?php echo $monthName . ' ' . $selectedYear; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Context & Parameters Line -->
        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2" style="font-size:10px;">
            <div>
                <strong>Month/Year:</strong> <?php echo $monthName . ' ' . $selectedYear; ?> | 
                <strong>Class & Sec:</strong> <?php echo !empty($filter_class) ? htmlspecialchars($filter_class . ($filter_section ? ' - ' . $filter_section : '')) : 'All Classes'; ?> | 
                <strong>Campus:</strong> <?php echo !empty($filter_campus) ? htmlspecialchars($filter_campus) : 'All Campuses'; ?> | 
                <strong>Session:</strong> <?php echo !empty($filter_session) ? htmlspecialchars($filter_session) : 'Current Session'; ?>
            </div>
            <div>
                <strong>Printed On:</strong> <?php echo date('d-M-Y h:i A'); ?>
            </div>
        </div>

        <!-- KPI Metrics Ribbon -->
        <div class="metrics-ribbon">
            <div class="row g-0">
                <div class="col metric-box">
                    <div class="metric-val text-dark"><?php echo $totalStudentsCount; ?></div>
                    <div class="metric-lbl">Total Students</div>
                </div>
                <div class="col metric-box">
                    <div class="metric-val text-primary"><?php echo $workingDaysCount; ?></div>
                    <div class="metric-lbl">Working Days</div>
                </div>
                <div class="col metric-box">
                    <div class="metric-val text-success"><?php echo $overallPresent; ?></div>
                    <div class="metric-lbl">Total Present</div>
                </div>
                <div class="col metric-box">
                    <div class="metric-val text-danger"><?php echo $overallAbsent; ?></div>
                    <div class="metric-lbl">Total Absent</div>
                </div>
                <div class="col metric-box">
                    <div class="metric-val text-warning"><?php echo $overallLate; ?></div>
                    <div class="metric-lbl">Total Late</div>
                </div>
                <div class="col metric-box">
                    <div class="metric-val text-purple" style="color:#7c3aed;"><?php echo $overallLeave; ?></div>
                    <div class="metric-lbl">Total Leave</div>
                </div>
                <div class="col metric-box">
                    <div class="metric-val text-primary"><?php echo $overallRate; ?>%</div>
                    <div class="metric-lbl">Attendance Rate</div>
                </div>
                <div class="col metric-box">
                    <div class="metric-val text-danger"><?php echo $lowAttendanceRiskCount; ?></div>
                    <div class="metric-lbl">Low Att. Risk (<75%)</div>
                </div>
            </div>
        </div>

        <!-- Monthly Attendance Matrix Table -->
        <table class="matrix-print-table">
            <thead>
                <tr>
                    <th style="width:25px;">#</th>
                    <th style="width:45px;">Roll #</th>
                    <th style="width:130px;" class="text-start ps-2">Student Name</th>
                    <th style="width:65px;">Class-Sec</th>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                        <th style="width:20px;"><?php echo sprintf('%02d', $d); ?></th>
                    <?php endfor; ?>
                    <th style="width:25px;" class="bg-success text-white">P</th>
                    <th style="width:25px;" class="bg-danger text-white">A</th>
                    <th style="width:25px;" class="bg-warning text-dark">Lt</th>
                    <th style="width:25px;" class="bg-info text-dark">L</th>
                    <th style="width:40px;" class="bg-primary text-white">%</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allMatchedStudents)): ?>
                    <tr>
                        <td colspan="<?php echo 9 + $daysInMonth; ?>" class="text-center py-4 text-muted">
                            No student attendance records found for the selected month and filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $srNo = 1;
                    foreach ($allMatchedStudents as $st): 
                        $stId = (int)$st['id'];
                        $stName = trim(($st['first_name'] ?? '') . ' ' . ($st['last_name'] ?? ''));
                        $rollNo = $st['roll_no'] ?: $st['admission_no'];
                        $clsSec = ($st['class_name'] ?: $st['school_class'] ?: '-') . ' ' . ($st['section'] ?: $st['school_section'] ?: '');
                        
                        $pCount = 0; $aCount = 0; $ltCount = 0; $lCount = 0; $markedCount = 0;
                    ?>
                        <tr>
                            <td><?php echo $srNo++; ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($rollNo); ?></td>
                            <td class="st-name"><?php echo htmlspecialchars($stName); ?></td>
                            <td><?php echo htmlspecialchars(trim($clsSec)); ?></td>

                            <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                                $status = $attendanceMap[$stId][$d] ?? '';
                                $cellClass = 'att-cell-off';
                                $symbol = '-';

                                if ($status === 'Present') {
                                    $cellClass = 'att-cell-p';
                                    $symbol = 'P';
                                    $pCount++;
                                    $markedCount++;
                                } elseif ($status === 'Absent') {
                                    $cellClass = 'att-cell-a';
                                    $symbol = 'A';
                                    $aCount++;
                                    $markedCount++;
                                } elseif ($status === 'Late') {
                                    $cellClass = 'att-cell-lt';
                                    $symbol = 'Lt';
                                    $ltCount++;
                                    $markedCount++;
                                } elseif ($status === 'Leave') {
                                    $cellClass = 'att-cell-l';
                                    $symbol = 'L';
                                    $lCount++;
                                    $markedCount++;
                                }
                            ?>
                                <td class="<?php echo $cellClass; ?>"><?php echo $symbol; ?></td>
                            <?php endfor; ?>

                            <?php 
                            $stRate = ($markedCount > 0) ? round((($pCount + $ltCount) / $markedCount) * 100, 1) : 0.0;
                            $rateBadgeClass = ($stRate >= 80) ? 'text-success' : (($stRate >= 70) ? 'text-warning' : 'text-danger');
                            ?>
                            <td class="fw-bold text-success"><?php echo $pCount; ?></td>
                            <td class="fw-bold text-danger"><?php echo $aCount; ?></td>
                            <td class="fw-bold text-warning"><?php echo $ltCount; ?></td>
                            <td class="fw-bold text-purple" style="color:#7c3aed;"><?php echo $lCount; ?></td>
                            <td class="fw-bold <?php echo $rateBadgeClass; ?>"><?php echo $stRate; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Daily Attendance Trend Breakdown Table -->
        <div class="mb-3">
            <h6 class="fw-bold mb-2 text-uppercase" style="font-size:10px; color:#0f172a; letter-spacing:0.5px;">
                <i class="fa-solid fa-chart-line me-1"></i>Daily Attendance Trends Breakdown (<?php echo $monthName . ' ' . $selectedYear; ?>)
            </h6>
            <table class="matrix-print-table" style="font-size:8.5px;">
                <thead>
                    <tr>
                        <th>Metric / Day</th>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                            <th>Day <?php echo $d; ?></th>
                        <?php endfor; ?>
                        <th>Total Month</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="st-name text-success">Present Count</td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                            <td><?php echo $dailyTrends[$d]['Present']; ?></td>
                        <?php endfor; ?>
                        <td class="fw-bold text-success"><?php echo $overallPresent; ?></td>
                    </tr>
                    <tr>
                        <td class="st-name text-danger">Absent Count</td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                            <td><?php echo $dailyTrends[$d]['Absent']; ?></td>
                        <?php endfor; ?>
                        <td class="fw-bold text-danger"><?php echo $overallAbsent; ?></td>
                    </tr>
                    <tr>
                        <td class="st-name text-warning">Late Count</td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                            <td><?php echo $dailyTrends[$d]['Late']; ?></td>
                        <?php endfor; ?>
                        <td class="fw-bold text-warning"><?php echo $overallLate; ?></td>
                    </tr>
                    <tr>
                        <td class="st-name text-purple" style="color:#7c3aed;">Leave Count</td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                            <td><?php echo $dailyTrends[$d]['Leave']; ?></td>
                        <?php endfor; ?>
                        <td class="fw-bold text-purple" style="color:#7c3aed;"><?php echo $overallLeave; ?></td>
                    </tr>
                    <tr class="fw-bold bg-light">
                        <td class="st-name text-primary">Daily Att. %</td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                            $tm = $dailyTrends[$d]['TotalMarked'];
                            $pr = $dailyTrends[$d]['Present'] + $dailyTrends[$d]['Late'];
                            $dRate = ($tm > 0) ? round(($pr / $tm) * 100, 0) : 0;
                        ?>
                            <td><?php echo ($tm > 0) ? $dRate . '%' : '-'; ?></td>
                        <?php endfor; ?>
                        <td class="text-primary"><?php echo $overallRate; ?>%</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Verification Signature Blocks -->
        <div class="signature-footer">
            <div class="row text-center">
                <div class="col-4">
                    <div class="sig-box">
                        Prepared By / Class Teacher<br>
                        <span class="text-muted" style="font-size:8px; font-weight:normal;">Sign & Date</span>
                    </div>
                </div>
                <div class="col-4">
                    <div class="sig-box">
                        Attendance Incharge<br>
                        <span class="text-muted" style="font-size:8px; font-weight:normal;">Verified Stamp</span>
                    </div>
                </div>
                <div class="col-4">
                    <div class="sig-box">
                        Principal / Director<br>
                        <span class="text-muted" style="font-size:8px; font-weight:normal;">Indus Grammar School</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

</body>
</html>
