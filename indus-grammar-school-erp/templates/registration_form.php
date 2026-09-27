<?php
/**
 * Indus Grammar School ERP - Formal Printable Student Registration & Admission Form
 * High-Precision Single-Page A4 Printable Document
 * Version 4.5.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

AuthMiddleware::requireLogin();

$studentId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0);

$student = null;

if ($studentId > 0) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT s.*, c.class_name, c.section,
                   d.*
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN student_registration_details d ON s.id = d.student_id
            WHERE s.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching student registration details: " . $e->getMessage());
    }
}

$schoolLogoUrl = getSchoolLogoUrl();
$studentPhotoUrl = '';

if ($student && !empty($student['doc_student_photo'])) {
    $cleanPath = ltrim(str_replace('\\', '/', $student['doc_student_photo']), '/');
    $fullDiskPath = __DIR__ . '/../' . $cleanPath;
    if (file_exists($fullDiskPath)) {
        $studentPhotoUrl = APP_URL . '/' . $cleanPath;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Registration & Admission Form - Indus Grammar School</title>
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

        .form-page {
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
        .form-header-table {
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

        .school-main-name {
            font-family: 'Cinzel', serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #881337;
            margin: 0;
            line-height: 1.1;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .form-sub-title {
            font-size: 0.8rem;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .school-sub-contact {
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
        }

        .photo-holder-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-placeholder-text {
            font-size: 0.58rem;
            color: #64748b;
            font-weight: 700;
            line-height: 1.2;
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

        /* Form Fields Grid Table */
        .grid-data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #1e293b;
            margin-bottom: 4px;
        }

        .grid-data-table td {
            border: 1px solid #cbd5e1;
            padding: 3px 6px;
            font-size: 0.72rem;
            vertical-align: middle;
        }

        .grid-data-table td.lbl {
            font-weight: 700;
            color: #0f172a;
            background: #f1f5f9;
            width: 18%;
        }

        .grid-data-table td.val {
            font-weight: 600;
            color: #1e293b;
        }

        .blank-field-line {
            display: inline-block;
            width: 100%;
            min-height: 14px;
            border-bottom: 1px dotted #64748b;
        }

        /* Checkbox Box */
        .chk-box {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1.5px solid #0f172a;
            margin-right: 4px;
            vertical-align: middle;
            text-align: center;
            line-height: 10px;
            font-size: 0.6rem;
            font-weight: 800;
        }

        /* Declaration & Signature Box */
        .declaration-text {
            font-size: 0.65rem;
            line-height: 1.3;
            color: #334155;
            background: #fffdf5;
            border: 1px solid #e2e8f0;
            padding: 5px 8px;
            margin: 6px 0;
            border-left: 3px solid #0f172a;
        }

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

            .form-page {
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
            <i class="fa-solid fa-id-card text-primary me-2"></i>Official Student Registration Form (A4)
        </h5>
        <span class="text-muted small">Standard Formal Admission Application Sheet</span>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary fw-bold px-4 shadow-sm">
            <i class="fa-solid fa-print me-2"></i>Print Registration Form
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3">
            Close Window
        </button>
    </div>
</div>

<div class="form-page">
    <div>
        <!-- Top Header Block -->
        <table class="form-header-table">
            <tr>
                <td width="65" vertical-align="middle">
                    <?php if (!empty($schoolLogoUrl)): ?>
                        <img src="<?php echo $schoolLogoUrl; ?>" alt="School Logo" class="school-logo-img">
                    <?php else: ?>
                        <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center fw-bold" style="width: 45px; height: 45px; font-size: 0.8rem;">IGS</div>
                    <?php endif; ?>
                </td>
                <td vertical-align="middle" class="ps-2">
                    <h1 class="school-main-name">INDUS GRAMMAR SCHOOL</h1>
                    <div class="form-sub-title">STUDENT REGISTRATION & ADMISSION FORM</div>
                    <div class="school-sub-contact">Main Campus, Lahore &middot; Ph: +92 306 6544806 &middot; info@indusgrammar.edu.pk</div>
                </td>
                <td width="130" text-align="right" class="text-end" vertical-align="top">
                    <div class="photo-holder-box">
                        <?php if (!empty($studentPhotoUrl)): ?>
                            <img src="<?php echo $studentPhotoUrl; ?>" alt="Student Photo">
                        <?php else: ?>
                            <i class="fa-solid fa-camera text-secondary mb-1" style="font-size: 1.1rem;"></i>
                            <span class="photo-placeholder-text">Affix Passport Size Photo</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Section 1: Academic & Registration Category -->
        <div class="section-banner">
            <span><i class="fa-solid fa-graduation-cap me-1.5"></i> 1. Enrollment & Academic Details</span>
            <span>Session: <?php echo htmlspecialchars($student['academic_session'] ?? '2026 – 2027'); ?></span>
        </div>
        <table class="grid-data-table">
            <tr>
                <td class="lbl">Registration / Form No :</td>
                <td class="val font-monospace fw-bold text-primary" width="32%">
                    <?php echo htmlspecialchars($student['admission_no'] ?? 'IGS-REG-2026-____'); ?>
                </td>
                <td class="lbl">Date of Admission :</td>
                <td class="val">
                    <?php echo !empty($student['admission_date']) ? date('d-M-Y', strtotime($student['admission_date'])) : date('d-M-Y'); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Academic Type :</td>
                <td class="val fw-bold">
                    <?php echo htmlspecialchars($student['academic_type'] ?? 'School'); ?>
                </td>
                <td class="lbl">Class & Section :</td>
                <td class="val fw-bold text-dark">
                    <?php 
                        $clsName = $student['class_name'] ?? ($student['school_class'] ?? '________');
                        $secName = $student['section'] ?? ($student['school_section'] ?? 'A');
                        echo htmlspecialchars($clsName . ' - ' . $secName);
                    ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Roll Number :</td>
                <td class="val font-monospace">
                    <?php echo htmlspecialchars($student['roll_no'] ?? $student['reg_roll_no'] ?? '__________'); ?>
                </td>
                <td class="lbl">Campus Name :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['campus'] ?? 'Main Campus, Lahore'); ?>
                </td>
            </tr>
        </table>

        <!-- Section 2: Student Personal Information -->
        <div class="section-banner">
            <span><i class="fa-solid fa-user me-1.5"></i> 2. Student Personal Profile</span>
        </div>
        <table class="grid-data-table">
            <tr>
                <td class="lbl">Student Full Name :</td>
                <td class="val text-uppercase fw-bold" colspan="3">
                    <?php echo htmlspecialchars(($student ? ($student['first_name'] . ' ' . $student['last_name']) : '')); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Gender / Sex :</td>
                <td class="val" width="32%">
                    <?php echo htmlspecialchars($student['gender'] ?? 'Male / Female'); ?>
                </td>
                <td class="lbl">Date of Birth :</td>
                <td class="val">
                    <?php echo !empty($student['date_of_birth']) ? date('d-M-Y', strtotime($student['date_of_birth'])) : '____ / ____ / ________'; ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">B-Form / CNIC No :</td>
                <td class="val font-monospace">
                    <?php echo htmlspecialchars($student['cnic_bform'] ?? $student['cnic_no'] ?? '_____-_______-_'); ?>
                </td>
                <td class="lbl">Religion / Faith :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['religion'] ?? 'Islam'); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Blood Group :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['blood_group'] ?? '____'); ?>
                </td>
                <td class="lbl">Nationality :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['nationality'] ?? 'Pakistani'); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Student Phone :</td>
                <td class="val font-monospace">
                    <?php echo htmlspecialchars($student['student_mobile'] ?? '03XX-XXXXXXX'); ?>
                </td>
                <td class="lbl">Student Email :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['student_email'] ?? '____________________'); ?>
                </td>
            </tr>
        </table>

        <!-- Section 3: Parent & Guardian Details -->
        <div class="section-banner">
            <span><i class="fa-solid fa-users me-1.5"></i> 3. Parent / Guardian Information</span>
        </div>
        <table class="grid-data-table">
            <tr>
                <td class="lbl">Father's Name :</td>
                <td class="val text-uppercase fw-bold" width="32%">
                    <?php echo htmlspecialchars($student['father_name'] ?? $student['guardian_name'] ?? ''); ?>
                </td>
                <td class="lbl">Father's CNIC No :</td>
                <td class="val font-monospace">
                    <?php echo htmlspecialchars($student['father_cnic'] ?? $student['guardian_cnic'] ?? '_____-_______-_'); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Father's Cell / WhatsApp :</td>
                <td class="val font-monospace">
                    <?php echo htmlspecialchars($student['father_mobile'] ?? $student['guardian_phone'] ?? '03XX-XXXXXXX'); ?>
                </td>
                <td class="lbl">Father Occupation :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['father_occupation'] ?? '____________________'); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Mother's Name :</td>
                <td class="val text-uppercase">
                    <?php echo htmlspecialchars($student['mother_name'] ?? ''); ?>
                </td>
                <td class="lbl">Mother's CNIC No :</td>
                <td class="val font-monospace">
                    <?php echo htmlspecialchars($student['mother_cnic'] ?? '_____-_______-_'); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Guardian (If Any) :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['guardian_name'] ?? ''); ?> (<?php echo htmlspecialchars($student['guardian_relationship'] ?? 'Guardian'); ?>)
                </td>
                <td class="lbl">Emergency Contact :</td>
                <td class="val font-monospace">
                    <?php echo htmlspecialchars($student['emergency_contact'] ?? $student['guardian_phone'] ?? '03XX-XXXXXXX'); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Current Address :</td>
                <td class="val" colspan="3">
                    <?php echo htmlspecialchars($student['address'] ?? $student['current_address'] ?? '____________________________________________________________________________________'); ?>
                </td>
            </tr>
        </table>

        <!-- Section 4: Academic Background & Previous School -->
        <div class="section-banner">
            <span><i class="fa-solid fa-school me-1.5"></i> 4. Academic History & Documents</span>
        </div>
        <table class="grid-data-table">
            <tr>
                <td class="lbl">Previous School :</td>
                <td class="val" width="40%">
                    <?php echo htmlspecialchars($student['prev_school'] ?? '________________________________'); ?>
                </td>
                <td class="lbl">Class Passed :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['prev_class'] ?? '________'); ?>
                </td>
            </tr>
            <tr>
                <td class="lbl">Leaving Cert (SLC) :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['leaving_cert_no'] ?? 'Attached / Pending'); ?>
                </td>
                <td class="lbl">Test Marks / Grade :</td>
                <td class="val">
                    <?php echo htmlspecialchars($student['test_marks'] ?? 'Pass'); ?>
                </td>
            </tr>
        </table>

        <!-- Attached Documents Checklist -->
        <div style="font-size: 0.68rem; font-weight: 700; color: #0f172a; margin: 4px 0 2px 0;">
            <i class="fa-solid fa-paperclip me-1 text-primary"></i> Required Documents Attached Checklist:
        </div>
        <div class="d-flex flex-wrap justify-content-between p-2 border bg-light rounded mb-2" style="font-size: 0.65rem;">
            <div><span class="chk-box"><?php echo (!empty($student['doc_bform']) || !empty($student['cnic_bform'])) ? '✓' : ''; ?></span> Student B-Form / Birth Cert</div>
            <div><span class="chk-box"><?php echo (!empty($student['doc_father_cnic']) || !empty($student['father_cnic'])) ? '✓' : ''; ?></span> Father CNIC Copy</div>
            <div><span class="chk-box"><?php echo (!empty($student['doc_mother_cnic']) || !empty($student['mother_cnic'])) ? '✓' : ''; ?></span> Mother CNIC Copy</div>
            <div><span class="chk-box"><?php echo (!empty($student['doc_student_photo'])) ? '✓' : ''; ?></span> 4 Passport Photographs</div>
            <div><span class="chk-box"><?php echo (!empty($student['doc_leaving_cert'])) ? '✓' : ''; ?></span> School Leaving Certificate (SLC)</div>
        </div>

        <!-- Declaration Text -->
        <div class="declaration-text">
            <strong><i class="fa-solid fa-file-contract me-1"></i> PARENT / GUARDIAN UNDERTAKING & DECLARATION:</strong><br>
            I hereby solemnly declare that the information provided above is complete, true, and correct to the best of my knowledge. I promise to strictly abide by all rules, discipline, code of conduct, and fee regulations of Indus Grammar School. I understand that admission may be cancelled if any information is found incorrect.
        </div>
    </div>

    <!-- Official Signatures Row -->
    <div>
        <table class="sig-row-table">
            <tr>
                <td>
                    <div class="sig-line-mark">Parent / Guardian Signature</div>
                </td>
                <td>
                    <div class="sig-line-mark">Admission Incharge Signature</div>
                </td>
                <td>
                    <div class="sig-line-mark">Principal Signature & Stamp</div>
                </td>
            </tr>
        </table>
        <div class="text-center text-muted mt-2" style="font-size: 0.58rem;">
            Official Record Sheet &middot; Computerized ERP Document &middot; Indus Grammar School
        </div>
    </div>
</div>

</body>
</html>
