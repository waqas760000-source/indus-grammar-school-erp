<?php
/**
 * Indus Grammar School ERP - Formal Printable Student Registration & Admission Form
 * High-Precision Single-Page A4 Printable Document
 * Version 5.0.0
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
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@700;800&family=Outfit:wght@400;500;600;700;800;900&display=swap');

        @page {
            size: A4 portrait;
            margin: 5mm;
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
            min-height: 282mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 7mm 8mm;
            box-shadow: 0 6px 25px rgba(0,0,0,0.1);
            border: 2px solid #1d4ed8;
            border-radius: 12px;
            box-sizing: border-box;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Top Header */
        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 12px;
        }

        .brand-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .school-logo-box {
            width: 62px;
            height: 62px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 24px;
            box-shadow: 0 3px 8px rgba(30, 58, 138, 0.25);
            flex-shrink: 0;
        }

        .school-logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .school-title {
            font-family: 'Cinzel', serif;
            font-size: 1.45rem;
            font-weight: 800;
            color: #1e3a8a;
            margin: 0 0 4px 0;
            letter-spacing: 0.5px;
            line-height: 1;
        }

        .registration-badge {
            display: inline-block;
            background: #1e3a8a;
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .school-contact-info {
            font-size: 0.68rem;
            color: #475569;
            font-weight: 600;
            margin-top: 5px;
        }

        /* Passport Photo Frame */
        .photo-box {
            width: 34mm;
            height: 40mm;
            border: 2px solid #1e3a8a;
            border-radius: 8px;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 4px;
            box-sizing: border-box;
        }

        .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }

        .photo-box-text {
            font-size: 0.6rem;
            color: #475569;
            font-weight: 700;
            line-height: 1.2;
            margin-top: 4px;
        }

        /* Section Banners */
        .section-header {
            background: #2563eb;
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 800;
            padding: 6px 12px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 10px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Form Grid Tables */
        .info-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .info-table td {
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            padding: 7px 10px;
            font-size: 0.74rem;
            vertical-align: middle;
        }

        .info-table tr:last-child td {
            border-bottom: none;
        }

        .info-table td:last-child {
            border-right: none;
        }

        .info-table td.lbl {
            background: #ffffff;
            color: #1e3a8a;
            font-weight: 700;
            width: 24%;
            white-space: nowrap;
        }

        .info-table td.val {
            color: #0f172a;
            font-weight: 600;
        }

        .dashed-line {
            color: #2563eb;
            font-weight: 700;
            letter-spacing: 1px;
        }

        /* Declaration Box */
        .declaration-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-left: 5px solid #2563eb;
            border-radius: 8px;
            padding: 10px 14px;
            margin-top: 15px;
            font-size: 0.72rem;
            line-height: 1.5;
            color: #334155;
        }

        .declaration-title {
            color: #1e3a8a;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-size: 0.75rem;
        }

        /* Signatures */
        .signatures-table {
            width: 100%;
            margin-top: 50px;
            margin-bottom: 15px;
        }

        .signatures-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 15px;
        }

        .signature-line {
            border-top: 2px solid #0f172a;
            padding-top: 5px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #0f172a;
        }

        .footer-note {
            text-align: center;
            font-size: 0.65rem;
            color: #64748b;
            margin-top: 10px;
            font-weight: 500;
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
                padding: 10mm;
                width: 100%;
                min-height: 275mm; /* A4 height approx minus margins */
                border: 2px solid #1d4ed8;
                page-break-after: always;
                border-radius: 0; /* Printers usually do better with square borders */
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
        <div class="header-container">
            <div class="brand-left">
                <div class="school-logo-box">
                    <?php if (!empty($schoolLogoUrl)): ?>
                        <img src="<?php echo $schoolLogoUrl; ?>" alt="School Logo">
                    <?php else: ?>
                        <i class="fa-solid fa-graduation-cap"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="school-title">INDUS GRAMMAR SCHOOL</h1>
                    <div class="registration-badge">
                        <i class="fa-solid fa-id-card me-1"></i> STUDENT REGISTRATION FORM
                    </div>
                    <div class="school-contact-info">
                        <i class="fa-solid fa-location-dot me-1 text-primary"></i> Main Campus, Lahore &bull; <i class="fa-solid fa-phone me-1 text-primary"></i> +92 306 6544806
                    </div>
                </div>
            </div>
            <div class="photo-box">
                <?php if (!empty($studentPhotoUrl)): ?>
                    <img src="<?php echo $studentPhotoUrl; ?>" alt="Student Photo">
                <?php else: ?>
                    <i class="fa-solid fa-camera text-secondary mb-1" style="font-size: 1.4rem;"></i>
                    <span class="photo-box-text">Affix Student<br>Passport Photo</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section 1: Student Information -->
        <div class="section-header">
            <span><i class="fa-solid fa-graduation-cap me-1.5"></i> 1. STUDENT INFORMATION</span>
            <span>DATE: <?php echo $currentDateFormatted; ?></span>
        </div>

        <table class="info-table">
            <tr>
                <td class="lbl"><i class="fa-solid fa-id-card me-1"></i> Student ID (Reg No) :</td>
                <td class="val fw-bold text-primary" width="30%">
                    <?php echo htmlspecialchars($student['admission_no'] ?? 'IGS-REG-2026-____'); ?>
                </td>
                <td class="lbl"><i class="fa-solid fa-hashtag me-1"></i> Roll Number :</td>
                <td class="val font-monospace">
                    <?php 
                        $roll = $student['roll_no'] ?? ($student['reg_roll_no'] ?? '');
                        echo !empty($roll) ? htmlspecialchars($roll) : '<span class="dashed-line">---------------------</span>'; 
                    ?>
                </td>
            </tr>
            <tr>
                <td class="lbl"><i class="fa-solid fa-user me-1"></i> Student Full Name :</td>
                <td class="val text-uppercase fw-bold" colspan="3">
                    <?php 
                        $name = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
                        echo !empty($name) ? htmlspecialchars($name) : '<span class="dashed-line">--------------------------------------------------</span>'; 
                    ?>
                </td>
            </tr>
            <tr>
                <td class="lbl"><i class="fa-solid fa-building-columns me-1"></i> Class :</td>
                <td class="val fw-bold" width="30%">
                    <?php 
                        $cls = $student['class_name'] ?? ($student['school_class'] ?? '');
                        echo !empty($cls) ? htmlspecialchars($cls) : '<span class="dashed-line">--------</span>'; 
                    ?>
                </td>
                <td class="lbl"><i class="fa-solid fa-layer-group me-1"></i> Section :</td>
                <td class="val fw-bold text-dark">
                    <?php 
                        $sec = $student['section'] ?? ($student['school_section'] ?? 'A');
                        echo htmlspecialchars($sec); 
                    ?>
                </td>
            </tr>
            <tr>
                <td class="lbl"><i class="fa-solid fa-address-card me-1"></i> Student B-Form / CNIC :</td>
                <td class="val font-monospace" colspan="3">
                    <?php 
                        $bform = $student['cnic_bform'] ?? ($student['cnic_no'] ?? '');
                        echo !empty($bform) ? htmlspecialchars($bform) : '<span class="dashed-line">_____-_______-_</span>'; 
                    ?>
                </td>
            </tr>
        </table>

        <!-- Section 2: Father & Contact Details -->
        <div class="section-header">
            <span><i class="fa-solid fa-users me-1.5"></i> 2. FATHER & CONTACT DETAILS</span>
        </div>

        <table class="info-table">
            <tr>
                <td class="lbl"><i class="fa-solid fa-user-tie me-1"></i> Father Name :</td>
                <td class="val text-uppercase fw-bold" colspan="3">
                    <?php 
                        $father = $student['father_name'] ?? ($student['guardian_name'] ?? '');
                        echo !empty($father) ? htmlspecialchars($father) : '<span class="dashed-line">--------------------------------------------------</span>'; 
                    ?>
                </td>
            </tr>
            <tr>
                <td class="lbl"><i class="fa-solid fa-id-card me-1"></i> Father CNIC No :</td>
                <td class="val font-monospace" width="30%">
                    <?php 
                        $fcnic = $student['father_cnic'] ?? ($student['guardian_cnic'] ?? '');
                        echo !empty($fcnic) ? htmlspecialchars($fcnic) : '<span class="dashed-line">_____-_______-_</span>'; 
                    ?>
                </td>
                <td class="lbl"><i class="fa-solid fa-phone me-1"></i> Phone Number :</td>
                <td class="val font-monospace">
                    <?php 
                        $phone = $student['father_mobile'] ?? ($student['guardian_phone'] ?? '');
                        echo !empty($phone) ? htmlspecialchars($phone) : '<span class="dashed-line">03XX-XXXXXXX</span>'; 
                    ?>
                </td>
            </tr>
            <tr>
                <td class="lbl"><i class="fa-solid fa-location-dot me-1"></i> Residential Address :</td>
                <td class="val" colspan="3">
                    <?php 
                        echo "Thokar Niaz Baig"; 
                    ?>
                </td>
            </tr>
        </table>

        <!-- Parent Undertaking Declaration -->
        <div class="declaration-box">
            <div class="declaration-title">
                <i class="fa-solid fa-file-contract me-1"></i> PARENT / GUARDIAN UNDERTAKING & DECLARATION:
            </div>
            I hereby solemnly declare that the information provided above is complete, true, and correct to the best of my knowledge. I promise to strictly abide by all rules, discipline, code of conduct, and fee regulations of Indus Grammar School.
        </div>
    </div>

    <!-- Signatures & Footer -->
    <div>
        <table class="signatures-table">
            <tr>
                <td>
                    <div class="signature-line">Parent / Guardian Signature</div>
                </td>
                <td>
                    <div class="signature-line">Admission Incharge Signature</div>
                </td>
                <td>
                    <div class="signature-line">Principal Signature & Stamp</div>
                </td>
            </tr>
        </table>
        <div class="footer-note">
            Official Record Sheet &bull; Computerized ERP System &bull; Indus Grammar School
        </div>
    </div>
</div>

</body>
</html>
