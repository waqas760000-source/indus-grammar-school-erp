<?php
/**
 * Indus Grammar School ERP - Official Printable Student Profile Report Template
 * Exact Replica of Student Profile Card (Single & Batch Class Printing)
 * Version 1.0.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

// Input filter parameters
$studentId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0);
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$sectionFilter = sanitize($_GET['section'] ?? '');
$searchQuery = sanitize($_GET['search'] ?? '');
$academicType = sanitize($_GET['academic_type'] ?? '');

// Fetch active classes for toolbar selector
$classes = [];
try {
    $classes = $db->query("SELECT id, class_name, section FROM classes ORDER BY class_name ASC, section ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch student dropdown list for toolbar selector
$allStudentsList = [];
try {
    $allStudentsList = $db->query("
        SELECT s.id, s.admission_no, s.first_name, s.last_name, c.class_name, c.section 
        FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        WHERE s.status = 'Active' 
        ORDER BY s.first_name ASC, s.last_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Build SQL query to fetch student record(s)
$whereClause = " WHERE 1=1";
$queryParams = [];

if ($studentId > 0) {
    $whereClause .= " AND s.id = :std_id";
    $queryParams['std_id'] = $studentId;
} elseif ($classId > 0) {
    $whereClause .= " AND s.class_id = :class_id";
    $queryParams['class_id'] = $classId;
    if ($sectionFilter !== '') {
        $whereClause .= " AND (c.section = :sec OR s.school_section = :sec)";
        $queryParams['sec'] = $sectionFilter;
    }
} elseif (!empty($searchQuery)) {
    $whereClause .= " AND (s.admission_no LIKE :q OR s.first_name LIKE :q OR s.last_name LIKE :q OR CONCAT(s.first_name, ' ', s.last_name) LIKE :q OR d.father_name LIKE :q)";
    $queryParams['q'] = '%' . $searchQuery . '%';
} else {
    // Default fallback to first active student if no parameter passed
    $whereClause .= " AND s.status = 'Active'";
}

if (!empty($academicType)) {
    $whereClause .= " AND s.academic_type = :ac_type";
    $queryParams['ac_type'] = $academicType;
}

$students = [];
try {
    $sql = "
        SELECT 
            s.id as std_id, 
            s.admission_no, 
            s.first_name, 
            s.last_name, 
            s.gender, 
            s.date_of_birth, 
            s.cnic_bform, 
            s.enrollment_date, 
            s.status, 
            s.guardian_name, 
            s.guardian_cnic, 
            s.guardian_phone, 
            s.guardian_email, 
            s.address, 
            s.academic_type, 
            s.school_class, 
            s.school_section, 
            s.tuition_fee, 
            s.academy_program, 
            s.academy_batch,
            c.class_name, 
            c.section,
            d.roll_no, 
            d.admission_date, 
            d.academic_session, 
            d.campus, 
            d.blood_group, 
            d.religion, 
            d.nationality, 
            d.cnic_no, 
            d.birth_cert_no,
            d.student_mobile, 
            d.student_email, 
            d.father_name, 
            d.father_cnic, 
            d.father_mobile, 
            d.father_occupation, 
            d.father_email, 
            d.current_address, 
            d.permanent_address, 
            d.city, 
            d.country, 
            d.doc_student_photo, 
            d.sponsor_name,
            d.emergency_contact
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $whereClause
        ORDER BY c.class_name ASC, c.section ASC, s.first_name ASC, s.last_name ASC
    ";
    
    // Limit to 200 records to prevent memory exhaustion in huge batch queries
    if ($studentId === 0 && $classId === 0 && empty($searchQuery)) {
        $sql .= " LIMIT 50";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($queryParams);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error retrieving student profile report: " . $e->getMessage());
}

if (empty($students)) {
    die("<div style='padding:40px; font-family:sans-serif; text-align:center;'><h2>No Student Record Found</h2><p>Please select a valid student or class from the toolbar.</p><a href='../modules/students/registration.php' style='display:inline-block; padding:10px 20px; background:#2563eb; color:#fff; text-decoration:none; border-radius:6px;'>Return to Registration</a></div>");
}

// Current print timestamp
$printTimestamp = date('d-m-Y H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile Report - Indus Grammar School</title>
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 8mm 8mm 8mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            background-color: #eef2f6;
            margin: 0;
            padding: 0;
            font-size: 11px;
        }

        /* Top Executive Control Toolbar (Non-printable) */
        .toolbar-container {
            background: #0f172a;
            color: #ffffff;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .toolbar-title {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toolbar-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toolbar-select {
            background: #1e293b;
            color: #f8fafc;
            border: 1px solid #334155;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            max-width: 250px;
        }

        .btn-toolbar {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 7px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-toolbar:hover {
            background: #1d4ed8;
            color: #ffffff;
        }

        .btn-toolbar-secondary {
            background: #475569;
        }
        .btn-toolbar-secondary:hover {
            background: #334155;
        }

        /* Main Printable Page Wrapper */
        .profile-card-wrapper {
            width: 210mm;
            max-width: 100%;
            margin: 20px auto;
            background: #ffffff;
            padding: 20px 25px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            position: relative;
            box-sizing: border-box;
        }

        /* Header Layout */
        .report-header {
            position: relative;
            text-align: center;
            margin-bottom: 12px;
            padding-top: 4px;
        }

        .header-timestamp {
            position: absolute;
            left: 0;
            top: 0;
            font-size: 13px;
            color: #000000;
            font-weight: normal;
        }

        .school-name {
            font-size: 24px;
            font-weight: bold;
            color: #000000;
            margin: 0;
            line-height: 1.2;
            letter-spacing: 0.2px;
        }

        .school-address {
            font-size: 14px;
            color: #000000;
            margin: 3px 0 1px 0;
        }

        .school-phone {
            font-size: 14px;
            color: #000000;
            margin: 1px 0 6px 0;
        }

        .report-title {
            font-size: 18px;
            font-weight: bold;
            color: #000000;
            margin: 6px 0 0 0;
        }

        /* Profile Tables & Layout Grid */
        .grid-row {
            display: flex;
            width: 100%;
            gap: 8px;
            margin-bottom: 6px;
        }

        .left-col {
            flex: 1 1 73%;
            width: 73%;
        }

        .right-col {
            flex: 0 0 26.5%;
            width: 26.5%;
        }

        /* Tables Styling */
        table.profile-tbl {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        table.profile-tbl th.section-hdr {
            background-color: #841b1b !important; /* Maroon bar matching sample */
            color: #ffffff !important;
            font-weight: bold;
            font-size: 12px;
            padding: 4px 8px;
            text-align: left;
            border: 1px solid #841b1b;
        }

        table.profile-tbl td.lbl {
            background-color: #f2f2f2 !important;
            font-weight: bold;
            font-size: 11px;
            color: #000000;
            padding: 3px 6px;
            border: 1px solid #c0c0c0;
            width: 22%;
            white-space: nowrap;
        }

        table.profile-tbl td.val {
            background-color: #ffffff;
            font-size: 11px;
            color: #000000;
            padding: 3px 6px;
            border: 1px solid #c0c0c0;
            width: 28%;
            word-break: break-word;
        }

        /* Photo Frame Container */
        .photo-frame-box {
            width: 100%;
            height: 100%;
            min-height: 245px;
            border: 1px solid #777777;
            background-color: #ffffff;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .photo-frame-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Print Media Styles */
        @media print {
            body {
                background-color: #ffffff;
            }

            .d-print-none {
                display: none !important;
            }

            .profile-card-wrapper {
                box-shadow: none;
                padding: 0;
                margin: 0;
                width: 100%;
                page-break-after: always;
                break-after: page;
            }

            .profile-card-wrapper:last-child {
                page-break-after: avoid;
                break-after: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- Non-printable Executive Toolbar -->
    <div class="toolbar-container d-print-none">
        <div class="toolbar-title">
            <i class="fa-solid fa-address-card text-primary"></i>
            Student Profile Report
            <span style="font-size: 12px; font-weight: normal; opacity: 0.8;">(Total: <?php echo count($students); ?> Student Cards)</span>
        </div>
        <div class="toolbar-controls">
            <!-- Select Student -->
            <select class="toolbar-select" onchange="if(this.value) window.location.href='student_profile_report.php?id=' + this.value;">
                <option value="">-- Choose Student --</option>
                <?php foreach ($allStudentsList as $sItem): ?>
                    <option value="<?php echo $sItem['id']; ?>" <?php echo ($studentId == $sItem['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sItem['first_name'] . ' ' . $sItem['last_name'] . ' (' . ($sItem['admission_no'] ?: 'ID:' . $sItem['id']) . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Select Class Batch -->
            <select class="toolbar-select" onchange="if(this.value) window.location.href='student_profile_report.php?class_id=' + this.value;">
                <option value="">-- Print Class Batch --</option>
                <?php foreach ($classes as $cItem): ?>
                    <option value="<?php echo $cItem['id']; ?>" <?php echo ($classId == $cItem['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cItem['class_name'] . ' - ' . $cItem['section']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button onclick="window.print();" class="btn-toolbar">
                <i class="fa-solid fa-print"></i> Print Report
            </button>
            <a href="../modules/students/registration.php<?php echo ($studentId > 0) ? '?id='.$studentId : ''; ?>" class="btn-toolbar btn-toolbar-secondary">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Render Each Student Profile Card -->
    <?php foreach ($students as $index => $std): ?>
        <?php
        // Resolve student photo
        $photoSrc = '';
        if (!empty($std['doc_student_photo'])) {
            $cleanPhotoPath = ltrim(str_replace('\\', '/', $std['doc_student_photo']), '/');
            $fullDiskPhotoPath = __DIR__ . '/../' . $cleanPhotoPath;
            if (file_exists($fullDiskPhotoPath)) {
                $photoSrc = APP_URL . '/' . $cleanPhotoPath;
            }
        }
        if (empty($photoSrc)) {
            $photoSrc = APP_URL . '/assets/images/default_student.svg';
            if (!file_exists(__DIR__ . '/../assets/images/default_student.svg')) {
                $photoSrc = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="240" viewBox="0 0 200 240"><rect width="100%" height="100%" fill="%23f1f5f9"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="14" fill="%2394a3b8">No Student Photo</text></svg>';
            }
        }

        // Family Group Query
        $familyGroupId = $std['std_id'];
        try {
            if (!empty($std['father_cnic'])) {
                $stmtFg = $db->prepare("SELECT MIN(student_id) FROM student_registration_details WHERE father_cnic = ?");
                $stmtFg->execute([$std['father_cnic']]);
                $fgVal = $stmtFg->fetchColumn();
                if ($fgVal) $familyGroupId = $fgVal;
            } elseif (!empty($std['father_mobile'])) {
                $stmtFg = $db->prepare("SELECT MIN(student_id) FROM student_registration_details WHERE father_mobile = ?");
                $stmtFg->execute([$std['father_mobile']]);
                $fgVal = $stmtFg->fetchColumn();
                if ($fgVal) $familyGroupId = $fgVal;
            }
        } catch (Exception $e) {}

        // Format dates
        $dobFormatted = (!empty($std['date_of_birth']) && $std['date_of_birth'] !== '0000-00-00') ? date('d-m-Y', strtotime($std['date_of_birth'])) : '';
        $admDateFormatted = (!empty($std['admission_date']) && $std['admission_date'] !== '0000-00-00') ? date('d-m-Y', strtotime($std['admission_date'])) : (!empty($std['enrollment_date']) ? date('d-m-Y', strtotime($std['enrollment_date'])) : '');
        ?>

        <div class="profile-card-wrapper">
            
            <!-- Header -->
            <div class="report-header">
                <div class="header-timestamp"><?php echo $printTimestamp; ?></div>
                <h1 class="school-name">INDUS Grammar School</h1>
                <div class="school-address">Main Mureedwal, Niaz Baig Road, Thoker Lahore.</div>
                <div class="school-phone">0333-4916860</div>
                <h2 class="report-title">Student Profile</h2>
            </div>

            <!-- Top Grid: Student Information (Left) + Student Photo (Right) -->
            <div class="grid-row">
                <!-- Left: Student Information Table -->
                <div class="left-col">
                    <table class="profile-tbl">
                        <thead>
                            <tr>
                                <th colspan="4" class="section-hdr">Student Information</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="lbl">Student Id:</td>
                                <td class="val"><?php echo htmlspecialchars($std['std_id']); ?></td>
                                <td class="lbl">Student Code:</td>
                                <td class="val"><?php echo htmlspecialchars($std['admission_no'] ?: 'AAS' . $std['std_id']); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">Student Name:</td>
                                <td class="val"><?php echo htmlspecialchars(strtoupper(trim($std['first_name'] . ' ' . $std['last_name']))); ?></td>
                                <td class="lbl">Student Mobile:</td>
                                <td class="val"><?php echo htmlspecialchars($std['student_mobile'] ?: $std['father_mobile']); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">Current Address:</td>
                                <td class="val" colspan="3"><?php echo htmlspecialchars(strtoupper($std['current_address'] ?: $std['address'])); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">Postal Address:</td>
                                <td class="val" colspan="3"><?php echo htmlspecialchars(strtoupper($std['permanent_address'] ?? '')); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">City:</td>
                                <td class="val"><?php echo htmlspecialchars(strtoupper($std['city'] ?? '')); ?></td>
                                <td class="lbl">Std Country:</td>
                                <td class="val"><?php echo htmlspecialchars(strtoupper($std['country'] ?? '')); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">Home Phone:</td>
                                <td class="val"><?php echo htmlspecialchars($std['emergency_contact'] ?? ''); ?></td>
                                <td class="lbl">Std Email:</td>
                                <td class="val"><?php echo htmlspecialchars($std['student_email'] ?? ''); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">Birthdate:</td>
                                <td class="val"><?php echo htmlspecialchars($dobFormatted); ?></td>
                                <td class="lbl">Std CNIC:</td>
                                <td class="val"><?php echo htmlspecialchars($std['cnic_no'] ?: $std['cnic_bform']); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">Passport No:</td>
                                <td class="val"><?php echo htmlspecialchars($std['birth_cert_no'] ?? ''); ?></td>
                                <td class="lbl">Gender:</td>
                                <td class="val"><?php echo htmlspecialchars(strtoupper($std['gender'] ?? '')); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">BloodGroup:</td>
                                <td class="val"><?php echo htmlspecialchars(strtoupper($std['blood_group'] ?? '')); ?></td>
                                <td class="lbl">Religion:</td>
                                <td class="val"><?php echo htmlspecialchars(strtoupper($std['religion'] ?: 'Islam')); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">Roll No:</td>
                                <td class="val"><?php echo htmlspecialchars($std['roll_no'] ?: '0'); ?></td>
                                <td class="lbl">Dicipline:</td>
                                <td class="val"><?php echo htmlspecialchars($std['academic_type'] ?? ''); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Right: Photo Frame -->
                <div class="right-col">
                    <div class="photo-frame-box">
                        <img src="<?php echo $photoSrc; ?>" alt="Student Photo">
                    </div>
                </div>
            </div>

            <!-- Middle Grid: Parent Information (Left) + Class Information (Right) -->
            <div class="grid-row">
                <!-- Parent Information Table -->
                <div class="left-col">
                    <table class="profile-tbl">
                        <thead>
                            <tr>
                                <th colspan="4" class="section-hdr">Parent Information</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="lbl">Father Name:</td>
                                <td class="val"><?php echo htmlspecialchars(strtoupper($std['father_name'] ?: $std['guardian_name'])); ?></td>
                                <td class="lbl">Father CNIC:</td>
                                <td class="val"><?php echo htmlspecialchars($std['father_cnic'] ?: $std['guardian_cnic']); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">Father Mobile:</td>
                                <td class="val"><?php echo htmlspecialchars($std['father_mobile'] ?: $std['guardian_phone']); ?></td>
                                <td class="lbl">Father Occupation:</td>
                                <td class="val"><?php echo htmlspecialchars(strtoupper($std['father_occupation'] ?? '')); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">Father Email:</td>
                                <td class="val" colspan="3"><?php echo htmlspecialchars($std['father_email'] ?: $std['guardian_email']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Class Information Table -->
                <div class="right-col">
                    <table class="profile-tbl">
                        <thead>
                            <tr>
                                <th colspan="2" class="section-hdr">Class Information</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="lbl" style="width:45%;">Class Name:</td>
                                <td class="val" style="width:55%;"><?php echo htmlspecialchars(strtoupper($std['class_name'] ?: $std['school_class'])); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl" style="width:45%;">Section Name:</td>
                                <td class="val" style="width:55%;"><?php echo htmlspecialchars(strtoupper($std['section'] ?: $std['school_section'])); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl" style="width:45%;">Course Title:</td>
                                <td class="val" style="width:55%;"><?php echo htmlspecialchars(strtoupper($std['academy_program'] ?? '')); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bottom Table: Other Information -->
            <table class="profile-tbl">
                <thead>
                    <tr>
                        <th colspan="4" class="section-hdr">Other Information</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="lbl">Adminssion Date:</td>
                        <td class="val"><?php echo htmlspecialchars($admDateFormatted); ?></td>
                        <td class="lbl">Dept Group:</td>
                        <td class="val"><?php echo htmlspecialchars($std['academic_type'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Shift Code:</td>
                        <td class="val"><?php echo htmlspecialchars($std['campus'] ?? ''); ?></td>
                        <td class="lbl">Std Reference:</td>
                        <td class="val"><?php echo htmlspecialchars($std['sponsor_name'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Ness Loccod:</td>
                        <td class="val"></td>
                        <td class="lbl">Std Family Group:</td>
                        <td class="val"><?php echo htmlspecialchars($familyGroupId); ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Std Exit Date:</td>
                        <td class="val"></td>
                        <td class="lbl">Active:</td>
                        <td class="val"><?php echo (strtoupper($std['status']) === 'ACTIVE') ? 'YES' : 'NO'; ?></td>
                    </tr>
                </tbody>
            </table>

        </div>
    <?php endforeach; ?>

</body>
</html>
