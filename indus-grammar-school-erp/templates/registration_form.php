<?php
/**
 * Indus Grammar School ERP - High-Precision Commercial Printable Student Registration Form (A4)
 * Version 6.0.0 - State-of-the-Art Commercial Print Redesign
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

$currentDateFormatted = strtoupper(date('d-M-Y'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Registration Form - Indus Grammar School</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@700;800;900&family=Outfit:wght@400;500;600;700;800;900&display=swap');

        @page {
            size: A4 portrait;
            margin: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #cbd5e1;
            color: #0f172a;
            margin: 0;
            padding: 20px 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .no-print-toolbar {
            max-width: 210mm;
            margin: 0 auto 15px auto;
            background: #ffffff;
            padding: 14px 24px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .form-page {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 10mm 12mm;
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            box-sizing: border-box;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Outer Frame Gold Accent */
        .frame-border {
            position: absolute;
            top: 5mm;
            left: 5mm;
            right: 5mm;
            bottom: 5mm;
            border: 2px solid #0f172a;
            border-radius: 6px;
            pointer-events: none;
        }

        .frame-border-inner {
            position: absolute;
            top: 6.5mm;
            left: 6.5mm;
            right: 6.5mm;
            bottom: 6.5mm;
            border: 1px dashed #2563eb;
            border-radius: 4px;
            pointer-events: none;
        }

        /* Header Layout */
        .header-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #2563eb 100%);
            color: #ffffff;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 14px;
            position: relative;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.15);
        }

        .header-logo-container {
            width: 70px;
            height: 70px;
            background: #ffffff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .school-logo-img {
            max-height: 62px;
            max-width: 62px;
            object-fit: contain;
            border-radius: 50%;
        }

        .school-title {
            font-family: 'Cinzel', serif;
            font-size: 1.55rem;
            font-weight: 900;
            color: #ffffff;
            margin: 0;
            line-height: 1.1;
            letter-spacing: 0.8px;
        }

        .sub-header-title {
            font-size: 0.82rem;
            font-weight: 800;
            color: #fbbf24;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-top: 3px;
        }

        .school-contact-info {
            font-size: 0.68rem;
            color: #e2e8f0;
            margin-top: 4px;
            font-weight: 500;
        }

        /* Photo Frame Area */
        .photo-card {
            width: 38mm;
            height: 46mm;
            border: 2px solid #1e3a8a;
            border-radius: 8px;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 3px;
            box-sizing: border-box;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            position: relative;
        }

        .photo-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px;
        }

        .photo-placeholder-text {
            font-size: 0.6rem;
            color: #475569;
            font-weight: 700;
            margin-top: 4px;
            line-height: 1.2;
        }

        /* Section Headings */
        .section-heading-bar {
            background: #f1f5f9;
            border-left: 5px solid #2563eb;
            color: #0f172a;
            font-size: 0.85rem;
            font-weight: 800;
            padding: 6px 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 12px;
            margin-bottom: 8px;
            border-radius: 0 6px 6px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Data Fields Grid Table */
        .data-card-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .data-card-grid td {
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            padding: 8px 12px;
            font-size: 0.88rem;
            vertical-align: middle;
        }

        .data-card-grid tr:last-child td {
            border-bottom: none;
        }

        .data-card-grid td:last-child {
            border-right: none;
        }

        .data-card-grid td.lbl {
            font-weight: 700;
            color: #1e3a8a;
            background: #f8fafc;
            width: 24%;
            font-size: 0.82rem;
        }

        .data-card-grid td.val {
            font-weight: 700;
            width: 24%;
            white-space: nowrap;
        }

        .val-highlight {
            color: #2563eb !important;
            font-size: 1.05rem !important;
            font-weight: 800 !important;
        }

        .val-name {
            font-size: 1.1rem !important;
            font-weight: 800 !important;
            color: #0f172a !important;
            letter-spacing: 0.5px;
        }

        /* Undertaking Box */
        .policy-card {
            background: #eff6ff;
            border: 1.5px solid #bfdbfe;
            border-left: 5px solid #2563eb;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.72rem;
            line-height: 1.45;
            color: #1e3a8a;
            margin-top: 14px;
        }

        /* Signatures Grid */
        .signatures-container {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-top: 35px;
            padding: 0 10px;
        }

        .sig-block {
            text-align: center;
            width: 28%;
        }

        .sig-line {
            border-top: 2px solid #0f172a;
            padding-top: 5px;
            font-size: 0.75rem;
            font-weight: 800;
            color: #0f172a;
        }

        .stamp-box-area {
            width: 75px;
            height: 75px;
            border: 2px dashed #cbd5e1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px auto;
            color: #94a3b8;
            font-size: 0.62rem;
            font-weight: 700;
            text-align: center;
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
                width: 210mm;
                height: 297mm;
                padding: 10mm 12mm;
                page-break-after: always;
                border-radius: 0;
            }

            .frame-border, .frame-border-inner {
                display: block !important;
            }
        }
    </style>
</head>
<body>

<!-- Non-Printable Action Bar -->
<div class="no-print-toolbar">
    <div class="d-flex align-items-center gap-3">
        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
            <i class="fa-solid fa-print fs-5"></i>
        </div>
        <div>
            <h5 class="fw-bold text-dark mb-0">Official Student Admission Form (A4)</h5>
            <span class="text-muted small">High Quality Commercial ERP Document Preview</span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary fw-bold px-4 rounded-pill shadow-sm">
            <i class="fa-solid fa-print me-2"></i>Print Form
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3 rounded-pill">
            Close Window
        </button>
    </div>
</div>

<!-- Main A4 Document -->
<div class="form-page">
    <div class="frame-border"></div>
    <div class="frame-border-inner"></div>

    <div style="position: relative; z-index: 2;">
        
        <!-- Header Banner Block -->
        <div class="header-box">
            <div class="d-flex align-items-center gap-3">
                <div class="header-logo-container">
                    <?php if (!empty($schoolLogoUrl)): ?>
                        <img src="<?php echo $schoolLogoUrl; ?>" alt="School Logo" class="school-logo-img">
                    <?php else: ?>
                        <div class="fw-bold text-primary fs-3">IGS</div>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="school-title">INDUS GRAMMAR SCHOOL</h1>
                    <div class="sub-header-title"><i class="fa-solid fa-id-card me-1"></i> Student Registration & Admission Form</div>
                    <div class="school-contact-info"><i class="fa-solid fa-location-dot me-1 text-warning"></i>Main Campus, Lahore &nbsp;|&nbsp; <i class="fa-solid fa-phone me-1 text-warning"></i>+92 307 4918603 &nbsp;|&nbsp; <i class="fa-solid fa-envelope me-1 text-warning"></i>info@indusgrammar.edu.pk</div>
                </div>
            </div>

            <!-- Student Photo Frame -->
            <div class="photo-card">
                <?php if (!empty($studentPhotoUrl)): ?>
                    <img src="<?php echo $studentPhotoUrl; ?>" alt="Student Photo">
                <?php else: ?>
                    <i class="fa-solid fa-camera text-primary mb-1 fs-3"></i>
                    <span class="photo-placeholder-text">Affix Student<br>Passport Photo</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- SECTION 1: STUDENT PROFILE -->
        <div class="section-heading-bar">
            <span><i class="fa-solid fa-user-graduate me-2 text-primary"></i> 1. Student Information</span>
            <span class="badge bg-primary text-white px-2 py-1 small">Date: <?php echo !empty($student['admission_date']) ? date('d-M-Y', strtotime($student['admission_date'])) : date('d-M-Y'); ?></span>
        </div>

        <table class="data-card-grid">
            <tr style="height: 42px;">
                <td class="lbl"><i class="fa-solid fa-fingerprint me-1.5 text-primary"></i>Student ID (Reg No) :</td>
                <td class="val font-monospace val-highlight" style="width: 26%;">
                    <?php echo htmlspecialchars($student['admission_no'] ?? 'IGS-REG-2026-____'); ?>
                </td>
                <td class="lbl"><i class="fa-solid fa-hashtag me-1.5 text-primary"></i>Roll Number :</td>
                <td class="val font-monospace fs-6" style="width: 26%;">
                    <?php echo htmlspecialchars($student['roll_no'] ?? $student['reg_roll_no'] ?? '__________'); ?>
                </td>
            </tr>
            <tr style="height: 48px;">
                <td class="lbl"><i class="fa-solid fa-user me-1.5 text-primary"></i>Student Full Name :</td>
                <td class="val text-uppercase val-name" colspan="3">
                    <?php echo htmlspecialchars(($student ? trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) : '________________________________________')); ?>
                </td>
            </tr>
            <tr style="height: 42px;">
                <td class="lbl"><i class="fa-solid fa-school me-1.5 text-primary"></i>Class :</td>
                <td class="val fs-6">
                    <span class="badge bg-light text-dark border px-3 py-1.5 fw-bold"><?php echo htmlspecialchars($student['class_name'] ?? ($student['school_class'] ?? '________')); ?></span>
                </td>
                <td class="lbl"><i class="fa-solid fa-layer-group me-1.5 text-primary"></i>Section :</td>
                <td class="val fs-6">
                    <span class="badge bg-light text-dark border px-3 py-1.5 fw-bold"><?php echo htmlspecialchars($student['section'] ?? ($student['school_section'] ?? 'A')); ?></span>
                </td>
            </tr>
            <tr style="height: 44px;">
                <td class="lbl"><i class="fa-solid fa-id-card me-1.5 text-primary"></i>Student B-Form / CNIC :</td>
                <td class="val font-monospace fs-6" colspan="3">
                    <?php echo htmlspecialchars($student['cnic_bform'] ?? $student['cnic_no'] ?? '_____-_______-_'); ?>
                </td>
            </tr>
        </table>

        <!-- SECTION 2: FATHER & CONTACT DETAILS -->
        <div class="section-heading-bar">
            <span><i class="fa-solid fa-users me-2 text-primary"></i> 2. Father Information & Contact Details</span>
        </div>

        <table class="data-card-grid">
            <tr style="height: 48px;">
                <td class="lbl"><i class="fa-solid fa-user-tie me-1.5 text-primary"></i>Father Full Name :</td>
                <td class="val text-uppercase val-name" colspan="3">
                    <?php echo htmlspecialchars($student['father_name'] ?? $student['guardian_name'] ?? '________________________________________'); ?>
                </td>
            </tr>
            <tr style="height: 44px;">
                <td class="lbl"><i class="fa-solid fa-id-card-clip me-1.5 text-primary"></i>Father CNIC Number :</td>
                <td class="val font-monospace fs-6" style="width: 26%;">
                    <?php echo htmlspecialchars($student['father_cnic'] ?? $student['guardian_cnic'] ?? '_____-_______-_'); ?>
                </td>
                <td class="lbl"><i class="fa-solid fa-phone me-1.5 text-primary"></i>Phone Number :</td>
                <td class="val font-monospace fs-6 text-primary" style="width: 26%;">
                    <?php echo htmlspecialchars($student['father_mobile'] ?? $student['guardian_phone'] ?? '03XX-XXXXXXX'); ?>
                </td>
            </tr>
            <tr style="height: 58px;">
                <td class="lbl"><i class="fa-solid fa-location-dot me-1.5 text-primary"></i>Residential Address :</td>
                <td class="val" colspan="3" style="font-size: 0.9rem; line-height: 1.4;">
                    <?php echo htmlspecialchars($student['address'] ?? $student['current_address'] ?? '____________________________________________________________________________________'); ?>
                </td>
            </tr>
        </table>

        <!-- UNDERTAKING BOX -->
        <div class="policy-card">
            <strong class="text-primary fs-6 d-block mb-1"><i class="fa-solid fa-file-contract me-1.5"></i> PARENT / GUARDIAN UNDERTAKING & DECLARATION:</strong>
            I hereby solemnly declare that all the information provided in this registration form is true, correct, and complete to the best of my knowledge. I promise to strictly abide by all rules, discipline, code of conduct, and fee regulations of Indus Grammar School.
        </div>

    </div>

    <!-- SIGNATURES & VERIFICATION ROW -->
    <div style="position: relative; z-index: 2;">
        <div class="signatures-container">
            <div class="sig-block">
                <div class="sig-line">Parent / Guardian Signature</div>
            </div>
            <div class="sig-block">
                <div class="sig-line">Admission Incharge Signature</div>
            </div>
            <div class="sig-block">
                <div class="stamp-box-area">
                    Official<br>Stamp
                </div>
                <div class="sig-line">Principal Signature & Stamp</div>
            </div>
        </div>
        <div class="text-center text-muted mt-3" style="font-size: 0.65rem; font-weight: 600;">
            Computerized ERP Record Sheet &middot; Indus Grammar School &middot; System Verified
        </div>
    </div>
</div>

</body>
</html>
