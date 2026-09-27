<?php
/**
 * Indus Grammar School ERP - Printable Daily Attendance Report Template
 * Executive A4 Landscape Daily Attendance Sheet
 * Version 4.5.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

AuthMiddleware::requireLogin();

$db = Database::getConnection();

// Retrieve Filters
$filter_date          = sanitize($_GET['date'] ?? date('Y-m-d'));
$filter_session       = sanitize($_GET['academic_session'] ?? '');
$filter_campus        = sanitize($_GET['campus'] ?? '');
$filter_academic_type = sanitize($_GET['academic_type'] ?? '');
$filter_class         = sanitize($_GET['class'] ?? '');
$filter_section       = sanitize($_GET['section'] ?? '');
$filter_status        = sanitize($_GET['status'] ?? '');
$filter_search        = sanitize($_GET['search'] ?? '');

$where = " WHERE 1=1";
$params = [];

if ($filter_date !== '') {
    $where .= " AND a.date = :date";
    $params['date'] = $filter_date;
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

if ($filter_status !== '') {
    $where .= " AND a.status = :status";
    $params['status'] = $filter_status;
}

if ($filter_search !== '') {
    $where .= " AND (s.first_name LIKE :search1 OR s.last_name LIKE :search2 OR CONCAT(s.first_name, ' ', s.last_name) LIKE :search3 OR s.admission_no LIKE :search4 OR d.roll_no LIKE :search5)";
    $params['search1'] = '%' . $filter_search . '%';
    $params['search2'] = '%' . $filter_search . '%';
    $params['search3'] = '%' . $filter_search . '%';
    $params['search4'] = '%' . $filter_search . '%';
    $params['search5'] = '%' . $filter_search . '%';
}

$records = [];
$totalEntries = 0;
$presentCount = 0;
$absentCount = 0;
$lateCount = 0;
$leaveCount = 0;

try {
    // Summary Query
    $stmtSum = $db->prepare("
        SELECT a.status, COUNT(*) as cnt 
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        GROUP BY a.status
    ");
    $stmtSum->execute($params);
    $sumRows = $stmtSum->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sumRows as $sr) {
        $st = $sr['status'];
        $cn = (int)$sr['cnt'];
        if ($st === 'Present') $presentCount = $cn;
        elseif ($st === 'Absent') $absentCount = $cn;
        elseif ($st === 'Late') $lateCount = $cn;
        elseif ($st === 'Leave') $leaveCount = $cn;
    }

    $totalEntries = $presentCount + $absentCount + $lateCount + $leaveCount;
    $attendancePct = ($totalEntries > 0) ? round((($presentCount + $lateCount) / $totalEntries) * 100, 1) : 0.0;

    // Detailed Records Query
    $stmtRecords = $db->prepare("
        SELECT a.*, 
               s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section,
               c.class_name, c.section,
               d.roll_no, d.father_name, d.campus
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY c.class_name ASC, c.section ASC, s.first_name ASC
        LIMIT 500
    ");
    $stmtRecords->execute($params);
    $records = $stmtRecords->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error retrieving daily attendance report: " . $e->getMessage());
}

$schoolLogoUrl = getSchoolLogoUrl();
$formattedDate = date('d-M-Y', strtotime($filter_date));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Attendance Report - <?php echo $formattedDate; ?> - Indus Grammar School</title>
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
            margin: 0;
            padding: 15px 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .no-print-toolbar {
            max-width: 287mm;
            margin: 0 auto 15px auto;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .attendance-page {
            width: 287mm;
            min-height: 198mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 6mm;
            box-shadow: 0 6px 25px rgba(0,0,0,0.1);
            border: 2px solid #0f172a;
            border-radius: 4px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Header Table */
        .header-table {
            width: 100%;
            border-bottom: 2.5px solid #0f172a;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }

        .school-logo-img {
            max-height: 48px;
            max-width: 60px;
            object-fit: contain;
        }

        .school-title {
            font-family: 'Cinzel', serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #881337;
            margin: 0;
            line-height: 1.1;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .sub-title {
            font-size: 0.82rem;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .school-contact {
            font-size: 0.65rem;
            color: #475569;
            font-weight: 600;
        }

        /* Metrics Ribbon */
        .metrics-ribbon {
            display: flex;
            justify-content: space-around;
            background: #f8fafc;
            border: 1.5px solid #0f172a;
            padding: 8px;
            margin-bottom: 10px;
            border-radius: 4px;
            font-size: 0.72rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .metrics-ribbon .metric-item {
            text-align: center;
        }

        .metrics-ribbon .metric-item strong {
            display: block;
            font-size: 0.95rem;
            color: #0f172a;
        }

        /* Attendance Table */
        .att-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #0f172a;
            margin-bottom: 10px;
        }

        .att-table th {
            background: #0f172a;
            color: #ffffff;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 6px 8px;
            text-transform: uppercase;
            border: 1px solid #0f172a;
        }

        .att-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 8px;
            font-size: 0.72rem;
            color: #0f172a;
            vertical-align: middle;
        }

        .status-badge {
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 10px;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-present { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .status-absent  { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .status-late    { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .status-leave   { background: #e0f2fe; color: #075985; border: 1px solid #bae6fd; }

        /* Footer Signatures */
        .footer-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 15px;
            padding: 0 5px;
        }

        .sig-block {
            text-align: center;
            width: 30%;
            border-top: 1.5px solid #0f172a;
            padding-top: 3px;
            font-size: 0.65rem;
            font-weight: 700;
            color: #0f172a;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
                margin: 0;
            }

            .no-print-toolbar {
                display: none !important;
            }

            .attendance-page {
                box-shadow: none;
                margin: 0;
                padding: 4mm 6mm;
                width: 100%;
                height: 100vh;
                page-break-after: always;
            }
        }
    </style>
</head>
<body>

<div class="no-print-toolbar">
    <div>
        <h5 class="fw-bold text-dark mb-0">
            <i class="fa-solid fa-clipboard-user text-primary me-2"></i>Official Daily Attendance Report Sheet
        </h5>
        <span class="text-muted small">Format: A4 Landscape Daily Attendance Register</span>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary fw-bold px-4 shadow-sm">
            <i class="fa-solid fa-print me-2"></i>Print Attendance Sheet
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3">
            Close Window
        </button>
    </div>
</div>

<div class="attendance-page">
    <div>
        <!-- Top Header Block -->
        <table class="header-table">
            <tr>
                <td width="65" vertical-align="middle">
                    <?php if (!empty($schoolLogoUrl)): ?>
                        <img src="<?php echo $schoolLogoUrl; ?>" alt="School Logo" class="school-logo-img">
                    <?php else: ?>
                        <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center fw-bold" style="width: 45px; height: 45px; font-size: 0.8rem;">IGS</div>
                    <?php endif; ?>
                </td>
                <td vertical-align="middle" class="ps-2">
                    <h1 class="school-title">INDUS GRAMMAR SCHOOL</h1>
                    <div class="sub-title">DAILY STUDENT ATTENDANCE REPORT</div>
                    <div class="school-contact">Main Campus, Lahore &middot; Ph: +92 306 6544806 &middot; info@indusgrammar.edu.pk</div>
                </td>
                <td width="200" text-align="right" class="text-end" vertical-align="top">
                    <div class="border p-2 bg-light text-center rounded">
                        <span class="small fw-bold text-muted text-uppercase d-block" style="font-size:0.58rem;">ATTENDANCE DATE</span>
                        <span class="fw-bold text-primary fs-6"><?php echo $formattedDate; ?></span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Metrics Ribbon -->
        <div class="metrics-ribbon">
            <div class="metric-item text-primary">
                Total Records
                <strong><?php echo number_format($totalEntries); ?></strong>
            </div>
            <div class="metric-item text-success">
                Present
                <strong><?php echo number_format($presentCount); ?></strong>
            </div>
            <div class="metric-item text-danger">
                Absent
                <strong><?php echo number_format($absentCount); ?></strong>
            </div>
            <div class="metric-item text-warning">
                Late Arrival
                <strong><?php echo number_format($lateCount); ?></strong>
            </div>
            <div class="metric-item text-info">
                On Leave
                <strong><?php echo number_format($leaveCount); ?></strong>
            </div>
            <div class="metric-item text-dark">
                Attendance Rate
                <strong style="color: #16a34a;"><?php echo $attendancePct; ?>%</strong>
            </div>
        </div>

        <!-- Attendance Table -->
        <table class="att-table">
            <thead>
                <tr>
                    <th width="35" class="text-center">#</th>
                    <th width="110">Roll / Adm No</th>
                    <th>Student Name</th>
                    <th>Father Name</th>
                    <th width="110">Class & Sec</th>
                    <th width="120">Campus</th>
                    <th width="90" class="text-center">Status</th>
                    <th width="80" class="text-center">Time</th>
                    <th>Remarks / Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">No attendance records found matching the filter criteria for <?php echo $formattedDate; ?>.</td>
                    </tr>
                <?php else: 
                    $sr = 1;
                    foreach ($records as $r):
                        $st = $r['status'];
                        $stClass = match($st) {
                            'Present' => 'status-present',
                            'Absent'  => 'status-absent',
                            'Late'    => 'status-late',
                            'Leave'   => 'status-leave',
                            default   => 'status-present'
                        };
                        $clsName = $r['class_name'] ?? ($r['school_class'] ?? 'Class');
                        $secName = $r['section'] ?? ($r['school_section'] ?? 'A');
                ?>
                    <tr>
                        <td class="text-center text-muted fw-bold"><?php echo $sr++; ?></td>
                        <td class="font-monospace fw-bold text-primary"><?php echo htmlspecialchars($r['admission_no']); ?></td>
                        <td class="fw-bold text-dark text-uppercase"><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></td>
                        <td class="text-uppercase text-muted"><?php echo htmlspecialchars($r['father_name'] ?: 'N/A'); ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($clsName . ' - ' . $secName); ?></td>
                        <td><?php echo htmlspecialchars($r['campus'] ?: 'Main Campus'); ?></td>
                        <td class="text-center">
                            <span class="status-badge <?php echo $stClass; ?>"><?php echo htmlspecialchars($st); ?></span>
                        </td>
                        <td class="text-center font-monospace text-muted" style="font-size:0.65rem;">
                            <?php echo !empty($r['time_marked']) ? date('h:i A', strtotime($r['time_marked'])) : '—'; ?>
                        </td>
                        <td class="text-muted small">
                            <?php echo htmlspecialchars($r['remarks'] ?: '—'); ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Signatures -->
    <div class="footer-row">
        <div class="sig-block">
            Attendance Incharge Signature
        </div>
        <div class="sig-block">
            Class Teacher Signature
        </div>
        <div class="sig-block">
            Principal Signature & Stamp
        </div>
    </div>
</div>

</body>
</html>
