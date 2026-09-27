<?php
/**
 * Indus Grammar School ERP - Formal Executive Printable Student Dossier Template
 * High-Precision Single-Page / 2-Page A4 Student Profile Record
 * Version 4.5.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

AuthMiddleware::requireLogin();

$studentId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0);

$student = null;
$attendanceLogs = [];
$attendanceStats = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Leave' => 0];
$outstandingBalance = 0.00;
$examMarks = [];

if ($studentId > 0) {
    try {
        $db = Database::getConnection();
        // Fetch core student & registration details
        $stmt = $db->prepare("
            SELECT s.*, 
                   c.class_name, c.section,
                   d.roll_no as reg_roll_no, d.admission_date, d.academic_session, d.campus,
                   d.cnic_no, d.student_mobile, d.student_email,
                   d.father_name, d.father_cnic, d.father_mobile, d.father_occupation,
                   d.mother_name, d.mother_cnic, d.mother_mobile, d.mother_occupation,
                   d.guardian_relationship, d.guardian_cnic, d.guardian_address,
                   d.current_address, d.permanent_address,
                   d.fee_plan, d.fee_admission, d.fee_monthly,
                   d.remarks, d.doc_student_photo,
                   d.blood_group, d.religion, d.nationality,
                   d.prev_school, d.prev_class, d.prev_result, d.leaving_cert_no,
                   d.allergies, d.disability, d.emergency_contact, d.doctor_name,
                   d.transport_required, d.transport_route, d.pickup_point, d.transport_driver
            FROM students s 
            LEFT JOIN classes c ON s.class_id = c.id 
            LEFT JOIN student_registration_details d ON s.id = d.student_id
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            // Attendance Logs
            try {
                $stmtAtt = $db->prepare("SELECT status FROM attendance WHERE student_id = ? ORDER BY date DESC LIMIT 30");
                $stmtAtt->execute([$studentId]);
                $attendanceLogs = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($attendanceLogs as $att) {
                    if (isset($attendanceStats[$att['status']])) {
                        $attendanceStats[$att['status']]++;
                    }
                }
            } catch (Exception $e) {}

            // Outstanding Fee Balance
            try {
                $stmtBal = $db->prepare("SELECT COALESCE(SUM(total_payable - paid_amount), 0) FROM fee_ledger WHERE student_id = ? AND status != 'Paid'");
                $stmtBal->execute([$studentId]);
                $outstandingBalance = (float)$stmtBal->fetchColumn();
            } catch (Exception $e) {}

            // Exam Marks Summary
            try {
                $stmtExam = $db->prepare("
                    SELECT er.obtained_marks, er.grade, er.remarks, sub.subject_name, et.exam_name
                    FROM exam_results er
                    JOIN subjects sub ON er.subject_id = sub.id
                    JOIN exam_types et ON er.exam_type_id = et.id
                    WHERE er.student_id = ?
                    ORDER BY et.id DESC, sub.subject_name ASC
                    LIMIT 10
                ");
                $stmtExam->execute([$studentId]);
                $examMarks = $stmtExam->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }
    } catch (Exception $e) {
        error_log("Error fetching student dossier: " . $e->getMessage());
    }
}

if (!$student) {
    die("Student record not found or invalid student ID.");
}

$schoolLogoUrl = getSchoolLogoUrl();
$studentPhotoUrl = '';

if (!empty($student['doc_student_photo'])) {
    $cleanPath = ltrim(str_replace('\\', '/', $student['doc_student_photo']), '/');
    $fullDiskPath = __DIR__ . '/../' . $cleanPath;
    if (file_exists($fullDiskPath)) {
        $studentPhotoUrl = APP_URL . '/' . $cleanPath;
    }
}

$totalAttDays = count($attendanceLogs);
$attPercent = ($totalAttDays > 0) ? round(($attendanceStats['Present'] / $totalAttDays) * 100, 1) : 100.0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Profile Dossier - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Outfit:wght@400;500;600;700;800&display=swap');

        @page {
            size: A4 portrait;
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
            max-width: 210mm;
            margin: 0 auto 15px auto;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dossier-page {
            width: 200mm;
            min-height: 284mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 5mm 6mm;
            box-shadow: 0 6px 25px rgba(0,0,0,0.1);
            border: 2.5px double #0f172a;
            border-radius: 4px;
            box-sizing: border-box;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Header Table */
        .dossier-header-table {
            width: 100%;
            border-bottom: 2.5px solid #0f172a;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .school-logo-img {
            max-height: 55px;
            max-width: 65px;
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

        .dossier-sub-title {
            font-size: 0.8rem;
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

        /* Photo Box Frame */
        .photo-holder-box {
            width: 32mm;
            height: 38mm;
            border: 1.5px solid #0f172a;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2px;
            box-sizing: border-box;
            float: right;
            border-radius: 4px;
        }

        .photo-holder-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 2px;
        }

        /* Section Banners */
        .section-banner {
            background: #0f172a;
            color: #ffffff;
            font-size: 0.68rem;
            font-weight: 800;
            padding: 3px 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 6px;
            margin-bottom: 5px;
            border-radius: 2px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Grid Tables */
        .grid-data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #1e293b;
            margin-bottom: 4px;
        }

        .grid-data-table td {
            border: 1px solid #cbd5e1;
            padding: 3.5px 6px;
            font-size: 0.72rem;
            vertical-align: middle;
        }

        .grid-data-table td.lbl {
            font-weight: 700;
            color: #0f172a;
            background: #f1f5f9;
            width: 20%;
        }

        .grid-data-table td.val {
            font-weight: 600;
            color: #1e293b;
        }

        /* Stats Badge Pills */
        .stat-badge {
            font-size: 0.62rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 10px;
            display: inline-block;
        }

        .stat-badge-success { background: #dcfce7; color: #166534; }
        .stat-badge-danger  { background: #fee2e2; color: #991b1b; }
        .stat-badge-warning { background: #fef3c7; color: #92400e; }
        .stat-badge-info    { background: #e0f2fe; color: #075985; }

        /* Signatures */
        .sig-row-table {
            width: 100%;
            margin-top: 15px;
        }

        .sig-row-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 10px;
        }

        .sig-line-mark {
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

            .dossier-page {
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
            <i class="fa-solid fa-address-card text-primary me-2"></i>Official Student Profile Dossier
        </h5>
        <span class="text-muted small">Executive A4 Student Cumulative Record</span>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary fw-bold px-4 shadow-sm">
            <i class="fa-solid fa-print me-2"></i>Print Dossier
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3">
            Close Window
        </button>
    </div>
</div>

<div class="dossier-page">
    <div>
        <!-- Top Header Block -->
        <table class="dossier-header-table">
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
                    <div class="dossier-sub-title">CUMULATIVE STUDENT PROFILE DOSSIER</div>
                    <div class="school-contact">Main Campus, Lahore &middot; Ph: +92 306 6544806 &middot; info@indusgrammar.edu.pk</div>
                </td>
                <td width="130" text-align="right" class="text-end" vertical-align="top">
                    <div class="photo-holder-box">
                        <?php if (!empty($studentPhotoUrl)): ?>
                            <img src="<?php echo $studentPhotoUrl; ?>" alt="Student Photo">
                        <?php else: ?>
                            <i class="fa-solid fa-user text-secondary mb-1" style="font-size: 1.2rem;"></i>
                            <span style="font-size: 0.58rem; color: #64748b; font-weight: 700;">Student Photo</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Core Profile Identification Ribbon -->
        <div class="section-banner">
            <span><i class="fa-solid fa-id-card me-1.5"></i> 1. Student Identification Coordinates</span>
            <span>Status: <strong style="color:#4ade80;"><?php echo strtoupper($student['status'] ?? 'ACTIVE'); ?></strong></span>
        </div>
        <table class="grid-data-table">
            <tr>
                <td class="lbl">Student Full Name :</td>
                <td class="val text-uppercase fw-bold" width="30%">
                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                </td>
                <td class="lbl">Roll Number :</td>
                <td class="val font-monospace fw-bold text-primary">
                    <?php echo htmlspecialchars($student['admission_no']); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Class & Section :</td>
                <td class="val fw-bold text-dark">
                    <?php 
                        $clsName = $student['class_name'] ?? ($student['school_class'] ?? 'Class');
                        $secName = $student['section'] ?? ($student['school_section'] ?? 'A');
                        echo htmlspecialchars($clsName . ' - ' . $secName);
                    ?>
                </td>
                <td class="lbl">Academic Type :</td>
                <td class="val fw-semibold">
                    <?php echo htmlspecialchars($student['academic_type'] ?? 'School'); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Academic Session :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['academic_session'] ?? '2026 – 2027'); ?>
                </td>
                <td class="lbl">Admission Date :</td>
                <td class="val">
                    <?php echo !empty($student['admission_date']) ? date('d-M-Y', strtotime($student['admission_date'])) : (!empty($student['enrollment_date']) ? date('d-M-Y', strtotime($student['enrollment_date'])) : 'N/A'); ?>
                </td>
            </tr>
        </table>

        <!-- Section 2: Biological & Personal Information -->
        <div class="section-banner">
            <span><i class="fa-solid fa-user me-1.5"></i> 2. Biological & Personal Data</span>
        </div>
        <table class="grid-data-table">
            <tr>
                <td class="lbl">Gender / Sex :</td>
                <td class="val" width="30%">
                    <?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?>
                </td>
                <td class="lbl">Date of Birth :</td>
                <td class="val">
                    <?php echo !empty($student['date_of_birth']) ? date('d-M-Y', strtotime($student['date_of_birth'])) : 'N/A'; ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">CNIC / B-Form No :</td>
                <td class="val font-monospace fw-bold">
                    <?php echo htmlspecialchars($student['cnic_bform'] ?? $student['cnic_no'] ?? 'N/A'); ?>
                </td>
                <td class="lbl">Blood Group :</td>
                <td class="val fw-bold text-danger">
                    <?php echo htmlspecialchars($student['blood_group'] ?? 'N/A'); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Religion / Faith :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['religion'] ?? 'Islam'); ?>
                </td>
                <td class="lbl">Nationality :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['nationality'] ?? 'Pakistani'); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Student Phone :</td>
                <td class="val font-monospace">
                    <?php echo htmlspecialchars($student['student_mobile'] ?? 'N/A'); ?>
                </td>
                <td class="lbl">Student Email :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['student_email'] ?? 'N/A'); ?>
                </td>
            </tr>
        </table>

        <!-- Section 3: Parent & Family Background -->
        <div class="section-banner">
            <span><i class="fa-solid fa-users me-1.5"></i> 3. Parent & Guardian Coordinates</span>
        </div>
        <table class="grid-data-table">
            <tr>
                <td class="lbl">Father's Name :</td>
                <td class="val text-uppercase fw-bold" width="30%">
                    <?php echo htmlspecialchars($student['father_name'] ?? $student['guardian_name'] ?? 'N/A'); ?>
                </td>
                <td class="lbl">Father CNIC No :</td>
                <td class="val font-monospace">
                    <?php echo htmlspecialchars($student['father_cnic'] ?? $student['guardian_cnic'] ?? 'N/A'); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Father Mobile :</td>
                <td class="val font-monospace">
                    <?php echo htmlspecialchars($student['father_mobile'] ?? $student['guardian_phone'] ?? 'N/A'); ?>
                </td>
                <td class="lbl">Father Occupation :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['father_occupation'] ?? 'N/A'); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Mother's Name :</td>
                <td class="val text-uppercase">
                    <?php echo htmlspecialchars($student['mother_name'] ?? 'N/A'); ?>
                </td>
                <td class="lbl">Mother CNIC No :</td>
                <td class="val font-monospace">
                    <?php echo htmlspecialchars($student['mother_cnic'] ?? 'N/A'); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Emergency Contact :</td>
                <td class="val font-monospace text-danger fw-bold">
                    <?php echo htmlspecialchars($student['emergency_contact'] ?? $student['guardian_phone'] ?? 'N/A'); ?>
                </td>
                <td class="lbl">Guardian Relation :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['guardian_relationship'] ?? 'Father'); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Residential Address :</td>
                <td class="val" colspan="3">
                    <?php echo htmlspecialchars($student['address'] ?? $student['current_address'] ?? 'N/A'); ?>
                </td>
            </tr>
        </table>

        <!-- Section 4: Attendance & Fee Financial Standing -->
        <div class="section-banner">
            <span><i class="fa-solid fa-chart-pie me-1.5"></i> 4. Attendance & Financial Ledger Summary</span>
        </div>
        <table class="grid-data-table">
            <tr>
                <td class="lbl">Attendance Rate :</td>
                <td class="val" width="30%">
                    <span class="stat-badge stat-badge-info"><?php echo $attPercent; ?>% Present</span>
                    <span class="text-muted small">(P: <?php echo $attendanceStats['Present']; ?>, A: <?php echo $attendanceStats['Absent']; ?>, L: <?php echo $attendanceStats['Late']; ?>)</span>
                </td>
                <td class="lbl">Monthly Tuition Fee :</td>
                <td class="val font-monospace fw-bold">
                    Rs. <?php echo number_format((float)($student['fee_monthly'] ?? 3000), 2); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Outstanding Fee Dues :</td>
                <td class="val font-monospace fw-bold text-danger">
                    Rs. <?php echo number_format($outstandingBalance, 2); ?>
                </td>
                <td class="lbl">Transport Required :</td>
                <td class="val">
                    <?php echo (!empty($student['transport_required']) && $student['transport_required'] != '0') ? 'Yes (Route: ' . htmlspecialchars($student['transport_route'] ?? 'Main') . ')' : 'No'; ?>
                </td>
            </tr>
        </table>

        <!-- Section 5: Recent Examination Performance -->
        <?php if (!empty($examMarks)): ?>
            <div class="section-banner">
                <span><i class="fa-solid fa-award me-1.5"></i> 5. Recent Examination Scores</span>
            </div>
            <table class="grid-data-table">
                <thead>
                    <tr style="background:#f1f5f9; font-weight:700;">
                        <td width="35%">Examination Name</td>
                        <td width="35%">Subject Name</td>
                        <td width="15%" class="text-center">Obtained Marks</td>
                        <td width="15%" class="text-center">Grade</td>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($examMarks as $em): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($em['exam_name']); ?></td>
                            <td><?php echo htmlspecialchars($em['subject_name']); ?></td>
                            <td class="text-center font-monospace fw-bold"><?php echo htmlspecialchars($em['obtained_marks']); ?></td>
                            <td class="text-center fw-bold text-primary"><?php echo htmlspecialchars($em['grade'] ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Remarks -->
        <div style="font-size: 0.65rem; font-weight: 700; color: #0f172a; margin-top: 5px;">
            <i class="fa-solid fa-comment-dots me-1 text-primary"></i> Official Remarks & Notes:
        </div>
        <div style="border: 1px solid #cbd5e1; background: #fffdf5; padding: 4px 8px; font-size: 0.65rem; color: #334155; min-height: 25px; border-radius: 2px;">
            <?php echo htmlspecialchars($student['remarks'] ?: 'Student record is verified and active. No adverse disciplinary remarks on record.'); ?>
        </div>
    </div>

    <!-- Official Signatures Row -->
    <div>
        <table class="sig-row-table">
            <tr>
                <td>
                    <div class="sig-line-mark">Class Teacher Signature</div>
                </td>
                <td>
                    <div class="sig-line-mark">Accounts Officer Signature</div>
                </td>
                <td>
                    <div class="sig-line-mark">Principal Signature & Stamp</div>
                </td>
            </tr>
        </table>
        <div class="text-center text-muted mt-2" style="font-size: 0.58rem;">
            Official Cumulative Dossier Sheet &middot; Computerized ERP Document &middot; Indus Grammar School
        </div>
    </div>
</div>

</body>
</html>
