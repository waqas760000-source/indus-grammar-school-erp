<?php
/**
 * Indus Grammar School ERP - Printable Student Summary Report Template
 * Executive A4 Landscape Student Statistics & Enrollment Sheet
 * Version 4.5.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

AuthMiddleware::requireLogin();

$db = Database::getConnection();

// Retrieve filter parameters safely
$search_session = sanitize($_GET['search_session'] ?? '');
$search_campus = sanitize($_GET['search_campus'] ?? '');
$search_academic_type = sanitize($_GET['search_academic_type'] ?? '');
$search_class = (int)($_GET['search_class'] ?? 0);
$search_section = sanitize($_GET['search_section'] ?? '');
$search_status = sanitize($_GET['search_status'] ?? '');

$where = " WHERE 1=1";
$params = [];

if ($search_session !== '') {
    $where .= " AND d.academic_session = :session";
    $params['session'] = $search_session;
}
if ($search_campus !== '') {
    $where .= " AND d.campus = :campus";
    $params['campus'] = $search_campus;
}
if ($search_academic_type !== '') {
    $where .= " AND s.academic_type = :academic_type";
    $params['academic_type'] = $search_academic_type;
}
if ($search_class > 0) {
    $where .= " AND s.class_id = :class_id";
    $params['class_id'] = $search_class;
}
if ($search_section !== '') {
    $where .= " AND (c.section = :section1 OR s.school_section = :section2)";
    $params['section1'] = $search_section;
    $params['section2'] = $search_section;
}
if ($search_status !== '') {
    $where .= " AND s.status = :status";
    $params['status'] = $search_status;
}

$totalStudents = 0;
$totalActive = 0;
$totalBoys = 0;
$totalGirls = 0;
$totalSchool = 0;
$totalAcademy = 0;

$classBreakdown = [];

try {
    $sqlMetrics = "
        SELECT 
            COUNT(DISTINCT s.id) as total_students,
            SUM(CASE WHEN s.status = 'Active' THEN 1 ELSE 0 END) as total_active,
            SUM(CASE WHEN s.gender = 'Male' THEN 1 ELSE 0 END) as total_boys,
            SUM(CASE WHEN s.gender = 'Female' THEN 1 ELSE 0 END) as total_girls,
            SUM(CASE WHEN s.academic_type = 'School' THEN 1 ELSE 0 END) as total_school,
            SUM(CASE WHEN s.academic_type = 'Academy' THEN 1 ELSE 0 END) as total_academy
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
    ";
    $stmtMetrics = $db->prepare($sqlMetrics);
    $stmtMetrics->execute($params);
    $metrics = $stmtMetrics->fetch(PDO::FETCH_ASSOC);

    if ($metrics) {
        $totalStudents = (int)($metrics['total_students'] ?? 0);
        $totalActive = (int)($metrics['total_active'] ?? 0);
        $totalBoys = (int)($metrics['total_boys'] ?? 0);
        $totalGirls = (int)($metrics['total_girls'] ?? 0);
        $totalSchool = (int)($metrics['total_school'] ?? 0);
        $totalAcademy = (int)($metrics['total_academy'] ?? 0);
    }

    $sqlBreakdown = "
        SELECT 
            COALESCE(c.class_name, s.school_class, 'Unassigned') as class_name,
            COALESCE(c.section, s.school_section, 'A') as section_name,
            COALESCE(d.campus, 'Main Campus') as campus_name,
            COALESCE(s.academic_type, 'School') as academic_type,
            COUNT(DISTINCT s.id) as total_students,
            SUM(CASE WHEN s.gender = 'Male' THEN 1 ELSE 0 END) as boys_count,
            SUM(CASE WHEN s.gender = 'Female' THEN 1 ELSE 0 END) as girls_count,
            SUM(CASE WHEN s.status = 'Active' THEN 1 ELSE 0 END) as active_count
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        GROUP BY 
            COALESCE(c.class_name, s.school_class, 'Unassigned'),
            COALESCE(c.section, s.school_section, 'A'),
            COALESCE(d.campus, 'Main Campus'),
            COALESCE(s.academic_type, 'School')
        ORDER BY class_name ASC, section_name ASC
    ";
    $stmtBreakdown = $db->prepare($sqlBreakdown);
    $stmtBreakdown->execute($params);
    $classBreakdown = $stmtBreakdown->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error generating summary report: " . $e->getMessage());
}

$schoolLogoUrl = getSchoolLogoUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Summary Report - Indus Grammar School</title>
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

        .summary-page {
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
        .summary-header-table {
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

        .summary-sub-title {
            font-size: 0.82rem;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .summary-contact {
            font-size: 0.65rem;
            color: #475569;
            font-weight: 600;
        }

        /* Metric Highlights Ribbon */
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

        /* Breakdown Table */
        .summary-breakdown-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #0f172a;
            margin-bottom: 10px;
        }

        .summary-breakdown-table th {
            background: #0f172a;
            color: #ffffff;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 6px 8px;
            text-transform: uppercase;
            border: 1px solid #0f172a;
        }

        .summary-breakdown-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 8px;
            font-size: 0.72rem;
            color: #0f172a;
        }

        .summary-breakdown-table tr.total-row td {
            border-top: 2px solid #0f172a;
            border-bottom: 2px solid #0f172a;
            background: #f8fafc;
            font-weight: 800;
            font-size: 0.78rem;
        }

        /* Footer Signatures */
        .summary-footer-row {
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

            .summary-page {
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
            <i class="fa-solid fa-chart-pie text-primary me-2"></i>Official Student Summary Report
        </h5>
        <span class="text-muted small">Format: A4 Landscape Enrollment Summary Sheet</span>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary fw-bold px-4 shadow-sm">
            <i class="fa-solid fa-print me-2"></i>Print Summary Report
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3">
            Close Window
        </button>
    </div>
</div>

<div class="summary-page">
    <div>
        <!-- Top Header Block -->
        <table class="summary-header-table">
            <tr>
                <td width="65" vertical-align="middle">
                    <?php if (!empty($schoolLogoUrl)): ?>
                        <img src="<?php echo $schoolLogoUrl; ?>" alt="School Logo" class="school-logo-img">
                    <?php else: ?>
                        <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center fw-bold" style="width: 45px; height: 45px; font-size: 0.8rem;">IGS</div>
                    <?php endif; ?>
                </td>
                <td vertical-align="middle" class="ps-2">
                    <h1 class="school-title">INDUS GRAMMAR SCHOOL & ACADEMY</h1>
                    <div class="summary-sub-title">STUDENT ENROLLMENT & CLASS SUMMARY REPORT</div>
                    <div class="summary-contact">Main Campus, Lahore &middot; Ph: +92 307 4918603 &middot; info@indusgrammar.edu.pk</div>
                </td>
                <td width="180" text-align="right" class="text-end" vertical-align="top">
                    <div class="border p-2 bg-light text-center rounded">
                        <span class="small fw-bold text-muted text-uppercase d-block" style="font-size:0.58rem;">GENERATED ON</span>
                        <span class="fw-bold text-dark" style="font-size:0.75rem;"><?php echo date('d-M-Y h:i A'); ?></span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Metrics Ribbon -->
        <div class="metrics-ribbon">
            <div class="metric-item text-primary">
                Total Strength
                <strong><?php echo number_format($totalStudents); ?></strong>
            </div>
            <div class="metric-item text-success">
                Active Enrolled
                <strong><?php echo number_format($totalActive); ?></strong>
            </div>
            <div class="metric-item text-info">
                Boys (Male)
                <strong><?php echo number_format($totalBoys); ?></strong>
            </div>
            <div class="metric-item text-danger">
                Girls (Female)
                <strong><?php echo number_format($totalGirls); ?></strong>
            </div>
            <div class="metric-item text-dark">
                School Program
                <strong><?php echo number_format($totalSchool); ?></strong>
            </div>
            <div class="metric-item text-secondary">
                Academy Program
                <strong><?php echo number_format($totalAcademy); ?></strong>
            </div>
        </div>

        <!-- Breakdown Table -->
        <table class="summary-breakdown-table">
            <thead>
                <tr>
                    <th width="35" class="text-center">#</th>
                    <th>Class Name</th>
                    <th width="70" class="text-center">Section</th>
                    <th>Campus</th>
                    <th width="110">Academic Type</th>
                    <th width="90" class="text-center">Boys (M)</th>
                    <th width="90" class="text-center">Girls (F)</th>
                    <th width="100" class="text-center">Active Enrolled</th>
                    <th width="110" class="text-center">Total Strength</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($classBreakdown)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">No student records found matching the filter criteria.</td>
                    </tr>
                <?php else: 
                    $sr = 1;
                    $sumBoys = 0; $sumGirls = 0; $sumActive = 0; $sumTotal = 0;
                    foreach ($classBreakdown as $row): 
                        $sumBoys += (int)$row['boys_count'];
                        $sumGirls += (int)$row['girls_count'];
                        $sumActive += (int)$row['active_count'];
                        $sumTotal += (int)$row['total_students'];
                ?>
                    <tr>
                        <td class="text-center text-muted fw-bold"><?php echo $sr++; ?></td>
                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['class_name']); ?></td>
                        <td class="text-center"><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['section_name']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['campus_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['academic_type']); ?></td>
                        <td class="text-center font-monospace fw-bold text-primary"><?php echo number_format((int)$row['boys_count']); ?></td>
                        <td class="text-center font-monospace fw-bold text-danger"><?php echo number_format((int)$row['girls_count']); ?></td>
                        <td class="text-center font-monospace fw-bold text-success"><?php echo number_format((int)$row['active_count']); ?></td>
                        <td class="text-center font-monospace fw-bold text-dark bg-light"><?php echo number_format((int)$row['total_students']); ?></td>
                    </tr>
                <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="5" class="text-uppercase text-dark font-monospace">Grand Total:</td>
                        <td class="text-center font-monospace text-primary fs-6"><?php echo number_format($sumBoys); ?></td>
                        <td class="text-center font-monospace text-danger fs-6"><?php echo number_format($sumGirls); ?></td>
                        <td class="text-center font-monospace text-success fs-6"><?php echo number_format($sumActive); ?></td>
                        <td class="text-center font-monospace text-dark bg-light fs-6"><?php echo number_format($sumTotal); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Signatures -->
    <div class="summary-footer-row">
        <div class="sig-block">
            Prepared By (Incharge)
        </div>
        <div class="sig-block">
            Data Analyst / Accounts
        </div>
        <div class="sig-block">
            Principal Signature & Stamp
        </div>
    </div>
</div>

</body>
</html>
