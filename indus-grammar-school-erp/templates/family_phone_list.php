<?php
/**
 * Indus Grammar School ERP - Printable Family Phone Numbers Directory Template
 * Executive A4 Landscape Family Contact Directory
 * Version 4.5.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

AuthMiddleware::requireLogin();

$db = Database::getConnection();

// Filter parameters
$search_class = (int)($_GET['search_class'] ?? 0);
$search_campus = sanitize($_GET['search_campus'] ?? '');
$search_academic_type = sanitize($_GET['search_academic_type'] ?? '');

$where = " WHERE s.status = 'Active'";
$params = [];

if ($search_class > 0) {
    $where .= " AND s.class_id = :search_class";
    $params['search_class'] = $search_class;
}
if ($search_campus !== '') {
    $where .= " AND d.campus = :search_campus";
    $params['search_campus'] = $search_campus;
}
if ($search_academic_type !== '') {
    $where .= " AND (s.academic_type = :search_academic_type OR d.academic_type = :search_academic_type)";
    $params['search_academic_type'] = $search_academic_type;
}

$groupedFamilies = [];
$totalStudentsCount = 0;
$totalFamiliesCount = 0;

try {
    $sqlAll = "
        SELECT s.id, s.admission_no, s.first_name, s.last_name, s.guardian_name, s.guardian_phone, s.academic_type, 
               s.school_class, s.school_section, s.status,
               c.class_name, c.section,
               d.roll_no, d.father_name, d.father_cnic, d.father_mobile, d.mother_name, d.mother_mobile,
               d.guardian_relationship, d.guardian_cnic, d.student_mobile, d.current_address, d.campus
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY d.father_name ASC, s.first_name ASC
    ";
    $stmtAll = $db->prepare($sqlAll);
    $stmtAll->execute($params);
    $allRecords = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

    $totalStudentsCount = count($allRecords);

    // Group into family units
    foreach ($allRecords as $r) {
        $fatherCnicClean = preg_replace('/[^0-9]/', '', $r['father_cnic'] ?? '');
        $fatherMobileClean = preg_replace('/[^0-9]/', '', $r['father_mobile'] ?? '');
        $guardianPhoneClean = preg_replace('/[^0-9]/', '', $r['guardian_phone'] ?? '');
        $fatherNameClean = strtolower(trim($r['father_name'] ?: ($r['guardian_name'] ?: '')));

        if (!empty($fatherCnicClean)) {
            $fKey = 'cnic_' . $fatherCnicClean;
        } elseif (!empty($fatherMobileClean)) {
            $fKey = 'mob_' . $fatherMobileClean . '_' . $fatherNameClean;
        } elseif (!empty($guardianPhoneClean)) {
            $fKey = 'gphone_' . $guardianPhoneClean . '_' . $fatherNameClean;
        } else {
            $fKey = 'std_' . $r['id'];
        }

        if (!isset($groupedFamilies[$fKey])) {
            $primaryPhone = $r['father_mobile'] ?: $r['guardian_phone'];
            $groupedFamilies[$fKey] = [
                'key' => $fKey,
                'father_name' => $r['father_name'] ?: ($r['guardian_name'] ?: 'Family Record'),
                'father_cnic' => $r['father_cnic'] ?: ($r['guardian_cnic'] ?: 'N/A'),
                'primary_phone' => $primaryPhone ?: 'N/A',
                'mother_name' => $r['mother_name'] ?? '',
                'mother_mobile' => $r['mother_mobile'] ?? '',
                'current_address' => $r['current_address'] ?? 'Address not recorded',
                'campus' => $r['campus'] ?: 'Main Campus',
                'students' => []
            ];
        }
        $groupedFamilies[$fKey]['students'][] = $r;
    }

    $totalFamiliesCount = count($groupedFamilies);

} catch (Exception $e) {
    die("Error retrieving family contact directory: " . $e->getMessage());
}

$schoolLogoUrl = getSchoolLogoUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Family Phone Numbers Directory - Indus Grammar School</title>
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

        .directory-page {
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
        .directory-header-table {
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

        .directory-sub-title {
            font-size: 0.82rem;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .directory-contact {
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

        /* Family Contact Directory Table */
        .family-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #0f172a;
            margin-bottom: 10px;
        }

        .family-table th {
            background: #0f172a;
            color: #ffffff;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 6px 8px;
            text-transform: uppercase;
            border: 1px solid #0f172a;
        }

        .family-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            font-size: 0.72rem;
            color: #0f172a;
            vertical-align: top;
        }

        .student-pill-list {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .student-pill-item {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.68rem;
        }

        /* Footer Signatures */
        .directory-footer-row {
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

            .directory-page {
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
            <i class="fa-solid fa-users-viewfinder text-primary me-2"></i>Official Family Phone Directory Sheet
        </h5>
        <span class="text-muted small">Format: A4 Landscape Parent & Family Contact Directory</span>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary fw-bold px-4 shadow-sm">
            <i class="fa-solid fa-print me-2"></i>Print Family Directory
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3">
            Close Window
        </button>
    </div>
</div>

<div class="directory-page">
    <div>
        <!-- Top Header Block -->
        <table class="directory-header-table">
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
                    <div class="directory-sub-title">FAMILY PHONE NUMBERS & PARENT CONTACT DIRECTORY</div>
                    <div class="directory-contact">Main Campus, Lahore &middot; Ph: +92 307 4918603 &middot; info@indusgrammar.edu.pk</div>
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
                Total Family Units
                <strong><?php echo number_format($totalFamiliesCount); ?></strong>
            </div>
            <div class="metric-item text-success">
                Enrolled Children
                <strong><?php echo number_format($totalStudentsCount); ?></strong>
            </div>
            <div class="metric-item text-info">
                Primary Phone Contacts
                <strong><?php echo number_format($totalFamiliesCount); ?></strong>
            </div>
            <div class="metric-item text-dark">
                Campus Filter
                <strong><?php echo htmlspecialchars($search_campus ?: 'All Campuses'); ?></strong>
            </div>
        </div>

        <!-- Directory Table -->
        <table class="family-table">
            <thead>
                <tr>
                    <th width="35" class="text-center">#</th>
                    <th width="160">Father / Guardian Name</th>
                    <th width="120">Father CNIC No</th>
                    <th width="130">Primary Mobile No</th>
                    <th width="140">Mother Name & Mobile</th>
                    <th>Enrolled Children / Students</th>
                    <th>Residential Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($groupedFamilies)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No family phone records match the selected filter criteria.</td>
                    </tr>
                <?php else: 
                    $sr = 1;
                    foreach ($groupedFamilies as $fam):
                ?>
                    <tr>
                        <td class="text-center text-muted fw-bold"><?php echo $sr++; ?></td>
                        <td>
                            <div class="fw-bold text-dark text-uppercase"><?php echo htmlspecialchars($fam['father_name']); ?></div>
                        </td>
                        <td class="font-monospace text-muted"><?php echo htmlspecialchars($fam['father_cnic']); ?></td>
                        <td>
                            <div class="fw-bold text-primary font-monospace fs-6">
                                <i class="fa-solid fa-phone me-1" style="font-size:0.65rem;"></i><?php echo htmlspecialchars($fam['primary_phone']); ?>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($fam['mother_name'])): ?>
                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($fam['mother_name']); ?></div>
                                <div class="text-muted font-monospace small"><?php echo htmlspecialchars($fam['mother_mobile'] ?: '—'); ?></div>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="student-pill-list">
                                <?php foreach ($fam['students'] as $st): ?>
                                    <div class="student-pill-item">
                                        <strong class="text-dark"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></strong>
                                        <span class="text-primary font-monospace small">(<?php echo htmlspecialchars(($st['class_name'] ?? $st['school_class'] ?? 'Class') . ' - ' . ($st['section'] ?? $st['school_section'] ?? 'A')); ?> &middot; Roll: <?php echo htmlspecialchars($st['admission_no']); ?>)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="text-muted" style="font-size: 0.68rem; line-height: 1.2;">
                            <?php echo htmlspecialchars($fam['current_address']); ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Signatures -->
    <div class="directory-footer-row">
        <div class="sig-block">
            Prepared By (Registrar)
        </div>
        <div class="sig-block">
            Administrative Officer
        </div>
        <div class="sig-block">
            Principal Signature & Stamp
        </div>
    </div>
</div>

</body>
</html>
