<?php
/**
 * Indus Grammar School ERP - Printable Attendance Register & History Dossier Template
 * Executive A4 Landscape Attendance Register Sheet
 * Version 4.5.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

AuthMiddleware::requireLogin();

$db = Database::getConnection();

// Retrieve & Sanitize Filters
$search_admission     = sanitize($_GET['search_admission'] ?? '');
$search_name          = sanitize($_GET['search_name'] ?? '');
$filter_session       = sanitize($_GET['session'] ?? '');
$filter_campus        = sanitize($_GET['campus'] ?? '');
$search_academic_type = sanitize($_GET['search_academic_type'] ?? '');
$search_class         = sanitize($_GET['search_class'] ?? '');
$search_section       = sanitize($_GET['search_section'] ?? '');
$filter_status        = sanitize($_GET['status'] ?? '');
$filter_search        = sanitize($_GET['search'] ?? '');
$from_date            = sanitize($_GET['from_date'] ?? date('Y-m-01'));
$to_date              = sanitize($_GET['to_date'] ?? date('Y-m-d'));
$view_student_id      = (int)($_GET['view_student_id'] ?? 0);

$profileStudent = null;
$studentStats = [];

if ($view_student_id > 0 || !empty($search_admission)) {
    try {
        $stQuery = "
            SELECT s.id, s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section,
                   c.class_name, c.section, d.doc_student_photo, d.roll_no, d.father_name, COALESCE(d.campus, 'Main Campus') as campus_name
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN student_registration_details d ON s.id = d.student_id
            WHERE 1=1
        ";
        if ($view_student_id > 0) {
            $stQuery .= " AND s.id = ?";
            $stStmt = $db->prepare($stQuery);
            $stStmt->execute([$view_student_id]);
        } else {
            $stQuery .= " AND s.admission_no = ?";
            $stStmt = $db->prepare($stQuery);
            $stStmt->execute([$search_admission]);
        }
        $profileStudent = $stStmt->fetch(PDO::FETCH_ASSOC);

        if ($profileStudent) {
            $statStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(status = 'Present') as present,
                    SUM(status = 'Absent') as absent,
                    SUM(status = 'Leave') as leave_days,
                    SUM(status = 'Late') as late
                FROM attendance
                WHERE student_id = ? AND date BETWEEN ? AND ?
            ");
            $statStmt->execute([$profileStudent['id'], $from_date, $to_date]);
            $studentStats = $statStmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error fetching student profile dossier: " . $e->getMessage());
    }
}

// Build Query Conditions
$where = " WHERE 1=1";
$params = [];

if ($view_student_id > 0) {
    $where .= " AND a.student_id = :view_student_id";
    $params['view_student_id'] = $view_student_id;
} else {
    if ($search_admission !== '') {
        $where .= " AND s.admission_no = :admission";
        $params['admission'] = $search_admission;
    }
    if ($search_name !== '') {
        $where .= " AND (s.first_name LIKE :name OR s.last_name LIKE :name)";
        $params['name'] = '%' . $search_name . '%';
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
    if ($search_academic_type !== '') {
        $where .= " AND s.academic_type = :academic_type";
        $params['academic_type'] = $search_academic_type;
    }
    if ($search_class !== '') {
        $where .= " AND (c.class_name = :class1 OR s.school_class = :class2)";
        $params['class1'] = $search_class;
        $params['class2'] = $search_class;
    }
    if ($search_section !== '') {
        $where .= " AND (c.section = :section1 OR s.school_section = :section2)";
        $params['section1'] = $search_section;
        $params['section2'] = $search_section;
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
}

if ($from_date !== '' && $to_date !== '') {
    $where .= " AND a.date BETWEEN :from_date AND :to_date";
    $params['from_date'] = $from_date;
    $params['to_date'] = $to_date;
}

$logs = [];
$totalEntries = 0;
$presentCount = 0;
$absentCount = 0;
$lateCount = 0;
$leaveCount = 0;
$attendancePercentage = 0.0;

try {
    // Summaries
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
    $sums = $stmtSum->fetchAll(PDO::FETCH_KEY_PAIR);

    $presentCount = (int)($sums['Present'] ?? 0);
    $absentCount  = (int)($sums['Absent'] ?? 0);
    $lateCount    = (int)($sums['Late'] ?? 0);
    $leaveCount   = (int)($sums['Leave'] ?? 0);
    $totalEntries = $presentCount + $absentCount + $lateCount + $leaveCount;

    if ($totalEntries > 0) {
        $attendancePercentage = round((($presentCount + $lateCount) / $totalEntries) * 100, 1);
    }

    // Detailed Log Rows
    $stmtData = $db->prepare("
        SELECT a.*, s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section, s.guardian_name, s.guardian_phone,
               c.class_name, c.section, d.doc_student_photo, d.roll_no, COALESCE(d.campus, 'Main Campus') as campus_name, d.father_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY a.date DESC, c.class_name ASC, s.first_name ASC
        LIMIT 500
    ");
    $stmtData->execute($params);
    $logs = $stmtData->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error retrieving attendance register printable report: " . $e->getMessage());
}

$schoolLogoUrl = getSchoolLogoUrl();
$fromFormatted = date('d-M-Y', strtotime($from_date));
$toFormatted = date('d-M-Y', strtotime($to_date));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Register (<?php echo $fromFormatted . ' to ' . $toFormatted; ?>) - Indus Grammar School</title>
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

        .register-print-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
            margin-bottom: 12px;
        }

        .register-print-table th, .register-print-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            vertical-align: middle;
        }

        .register-print-table th {
            background-color: #0f172a !important;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-status {
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 9px;
            text-transform: uppercase;
            display: inline-block;
        }

        .badge-p { background-color: #f0fdf4; color: #16a34a; border: 1px solid rgba(22, 163, 74, 0.3); }
        .badge-a { background-color: #fef2f2; color: #dc2626; border: 1px solid rgba(220, 38, 38, 0.3); }
        .badge-l { background-color: #f5f3ff; color: #7c3aed; border: 1px solid rgba(124, 58, 237, 0.3); }
        .badge-lt { background-color: #fffbeb; color: #d97706; border: 1px solid rgba(217, 119, 6, 0.3); }

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
            .register-print-table th {
                background-color: #0f172a !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .badge-p, .badge-a, .badge-l, .badge-lt {
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
                <i class="fa-solid fa-book-bookmark text-primary fs-4"></i>
                <div>
                    <h6 class="fw-bold mb-0">Attendance Register Sheet Preview</h6>
                    <span class="text-muted small"><?php echo $fromFormatted . ' to ' . $toFormatted; ?> • A4 Landscape Printable Sheet</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary fw-semibold px-4" onclick="window.print()">
                    <i class="fa-solid fa-print me-2"></i>Print Register Sheet
                </button>
                <button type="button" class="btn btn-outline-secondary fw-semibold px-3" onclick="window.close()">
                    <i class="fa-solid fa-xmark me-1"></i>Close
                </button>
            </div>
        </div>
    </div>

    <!-- Printable Container -->
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
                        <span class="report-title-badge">Official Attendance Register & History Log</span>
                    </div>
                </div>
                <div class="col-2 text-end">
                    <div class="border rounded p-1 text-center bg-light" style="font-size:9px;">
                        <div class="fw-bold text-uppercase" style="color:#1e3a8a;">Official Register</div>
                        <div class="fw-semibold text-dark"><?php echo date('Y-m-d'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Single Student Profile Dossier Banner (If viewing single student) -->
        <?php if ($profileStudent): ?>
            <div class="border rounded p-2 mb-2 bg-light d-flex align-items-center justify-content-between" style="font-size:10.5px;">
                <div class="d-flex align-items-center gap-3">
                    <i class="fa-solid fa-user-check text-primary fs-3"></i>
                    <div>
                        <div class="fw-bold text-dark fs-6">
                            <?php echo htmlspecialchars(trim(($profileStudent['first_name'] ?? '') . ' ' . ($profileStudent['last_name'] ?? ''))); ?>
                        </div>
                        <div class="text-muted">
                            <strong>Roll #:</strong> <?php echo htmlspecialchars($profileStudent['roll_no'] ?: $profileStudent['admission_no']); ?> | 
                            <strong>Class & Sec:</strong> <?php echo htmlspecialchars(($profileStudent['class_name'] ?: $profileStudent['school_class'] ?: '-') . ' - ' . ($profileStudent['section'] ?: $profileStudent['school_section'] ?: '')); ?> | 
                            <strong>Father Name:</strong> <?php echo htmlspecialchars($profileStudent['father_name'] ?: 'N/A'); ?>
                        </div>
                    </div>
                </div>
                <?php if (!empty($studentStats)): 
                    $stTot = (int)($studentStats['total'] ?? 0);
                    $stPr = (int)($studentStats['present'] ?? 0) + (int)($studentStats['late'] ?? 0);
                    $stRate = ($stTot > 0) ? round(($stPr / $stTot) * 100, 1) : 0.0;
                ?>
                    <div class="text-end">
                        <span class="badge bg-primary px-3 py-2 fs-6">Attendance: <?php echo $stRate; ?>%</span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Filter Context Line -->
        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2" style="font-size:10px;">
            <div>
                <strong>Period:</strong> <?php echo $fromFormatted . ' to ' . $toFormatted; ?> | 
                <strong>Class & Sec:</strong> <?php echo !empty($search_class) ? htmlspecialchars($search_class . ($search_section ? ' - ' . $search_section : '')) : 'All Classes'; ?> | 
                <strong>Campus:</strong> <?php echo !empty($filter_campus) ? htmlspecialchars($filter_campus) : 'All Campuses'; ?> | 
                <strong>Session:</strong> <?php echo !empty($filter_session) ? htmlspecialchars($filter_session) : 'Current Session'; ?>
                <?php if (!empty($filter_status)): ?>
                    | <strong>Status Filter:</strong> <?php echo htmlspecialchars($filter_status); ?>
                <?php endif; ?>
            </div>
            <div>
                <strong>Printed On:</strong> <?php echo date('d-M-Y h:i A'); ?>
            </div>
        </div>

        <!-- Metrics Ribbon -->
        <div class="metrics-ribbon">
            <div class="row g-0">
                <div class="col metric-box">
                    <div class="metric-val text-dark"><?php echo $totalEntries; ?></div>
                    <div class="metric-lbl">Total Log Entries</div>
                </div>
                <div class="col metric-box">
                    <div class="metric-val text-success"><?php echo $presentCount; ?></div>
                    <div class="metric-lbl">Present Entries</div>
                </div>
                <div class="col metric-box">
                    <div class="metric-val text-danger"><?php echo $absentCount; ?></div>
                    <div class="metric-lbl">Absent Entries</div>
                </div>
                <div class="col metric-box">
                    <div class="metric-val text-warning"><?php echo $lateCount; ?></div>
                    <div class="metric-lbl">Late Entries</div>
                </div>
                <div class="col metric-box">
                    <div class="metric-val text-purple" style="color:#7c3aed;"><?php echo $leaveCount; ?></div>
                    <div class="metric-lbl">Leave Entries</div>
                </div>
                <div class="col metric-box">
                    <div class="metric-val text-primary"><?php echo $attendancePercentage; ?>%</div>
                    <div class="metric-lbl">Overall Att. Rate</div>
                </div>
            </div>
        </div>

        <!-- Attendance Register Logs Table -->
        <table class="register-print-table">
            <thead>
                <tr>
                    <th style="width:30px;" class="text-center">#</th>
                    <th style="width:85px;">Date</th>
                    <th style="width:75px;">Roll / Adm #</th>
                    <th style="width:160px;">Student Name</th>
                    <th style="width:150px;">Father / Guardian Name</th>
                    <th style="width:90px;">Class & Sec</th>
                    <th style="width:90px;">Campus</th>
                    <th style="width:75px;" class="text-center">Status</th>
                    <th style="width:80px;" class="text-center">Check-In</th>
                    <th>Remarks / Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">
                            No attendance register entries found matching the specified parameters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $srNo = 1;
                    foreach ($logs as $log): 
                        $stName = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''));
                        $rollNo = $log['roll_no'] ?: $log['admission_no'];
                        $fatherName = $log['father_name'] ?: $log['guardian_name'] ?: 'N/A';
                        $clsSec = ($log['class_name'] ?: $log['school_class'] ?: '-') . ' ' . ($log['section'] ?: $log['school_section'] ?: '');
                        $attDate = date('d-M-Y (D)', strtotime($log['date']));
                        $checkIn = !empty($log['time']) ? date('h:i A', strtotime($log['time'])) : '-';

                        $stBadge = 'badge-p';
                        if ($log['status'] === 'Absent') $stBadge = 'badge-a';
                        elseif ($log['status'] === 'Late') $stBadge = 'badge-lt';
                        elseif ($log['status'] === 'Leave') $stBadge = 'badge-l';
                    ?>
                        <tr>
                            <td class="text-center"><?php echo $srNo++; ?></td>
                            <td class="fw-bold"><?php echo $attDate; ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($rollNo); ?></td>
                            <td class="fw-semibold text-dark"><?php echo htmlspecialchars($stName); ?></td>
                            <td><?php echo htmlspecialchars($fatherName); ?></td>
                            <td><?php echo htmlspecialchars(trim($clsSec)); ?></td>
                            <td><?php echo htmlspecialchars($log['campus_name'] ?? 'Main Campus'); ?></td>
                            <td class="text-center">
                                <span class="badge-status <?php echo $stBadge; ?>"><?php echo htmlspecialchars($log['status']); ?></span>
                            </td>
                            <td class="text-center font-monospace"><?php echo $checkIn; ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($log['remarks'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Signature Footer -->
        <div class="signature-footer">
            <div class="row text-center">
                <div class="col-4">
                    <div class="sig-box">
                        Prepared By (Register Clerk)<br>
                        <span class="text-muted" style="font-size:8px; font-weight:normal;">Sign & Date</span>
                    </div>
                </div>
                <div class="col-4">
                    <div class="sig-box">
                        Verified By (Attendance Controller)<br>
                        <span class="text-muted" style="font-size:8px; font-weight:normal;">Official Verification</span>
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
