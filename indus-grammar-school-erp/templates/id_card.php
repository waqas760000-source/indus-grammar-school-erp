<?php
/**
 * Indus Grammar School ERP - Printable Student ID Card
 * Version 5.0.0
 */

require_once __DIR__ . '/../config/app.php';
AuthMiddleware::requireLogin();

// Role check
$userRole = $_SESSION['role_code'] ?? '';
$allowedRoles = [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'receptionist'];
if (!in_array($userRole, $allowedRoles)) {
    die("Unauthorized access to ID cards.");
}

$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if ($studentId <= 0) {
    die("Student ID required.");
}

try {
    $db = Database::getConnection();
    $stmt = $db->prepare("
        SELECT s.*, c.class_name, c.section,
               d.roll_no, d.blood_group, d.emergency_contact, d.academic_session, d.doc_student_photo,
               d.father_name, d.father_mobile, d.current_address,
               d.admission_date
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        WHERE s.id = :sid
    ");
    $stmt->execute(['sid' => $studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

if (!$student) {
    die("Student record not found.");
}

$qrRawData = "Student ID: " . $student['admission_no'] . "\n" .
             "Name: " . $student['first_name'] . " " . $student['last_name'] . "\n" .
             "Class: " . ($student['class_name'] ?? $student['school_class'] ?? '—') . "\n" .
             "Section: " . ($student['section'] ?? $student['school_section'] ?? '—') . "\n" .
             "Academic Type: " . $student['academic_type'];
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrRawData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student ID Card - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: 'Outfit', 'Inter', sans-serif;
        }

        .id-card-wrap {
            display: inline-block;
            margin: 15px;
            vertical-align: top;
            text-align: left;
            page-break-inside: avoid;
        }

        .pvc-card-side {
            width: 260px;
            height: 410px;
            border: 1px solid #c8d2e6;
            border-radius: 12px;
            background-color: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(30, 58, 138, 0.08);
            display: inline-block;
            vertical-align: top;
            background-image: radial-gradient(circle at 10% 20%, rgba(239, 246, 255, 0.5) 0%, rgba(255, 255, 255, 0.5) 90%);
        }

        /* Front Side Styling */
        .pvc-card-front {
            border-top: 6px solid #1e3a8a;
        }

        .pvc-card-front .header-band {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: #ffffff;
            padding: 10px 5px;
            text-align: center;
            position: relative;
        }

        .pvc-card-front .header-band img.school-logo {
            width: 32px;
            height: 32px;
            object-fit: contain;
            margin-bottom: 2px;
        }

        .pvc-card-front .header-band h6 {
            margin: 0;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .pvc-card-front .header-band p.subtitle {
            margin: 0;
            font-size: 0.55rem;
            opacity: 0.9;
            text-transform: uppercase;
            font-weight: 600;
        }

        .pvc-card-front .avatar-box {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            border: 3px solid #3b82f6;
            overflow: hidden;
            margin: 12px auto 8px auto;
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pvc-card-front .avatar-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .pvc-card-front .student-name-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #1e3a8a;
            text-align: center;
            margin-bottom: 8px;
            padding: 0 10px;
            text-transform: capitalize;
        }

        .pvc-card-front .info-table {
            width: 100%;
            padding: 0 12px;
            font-size: 0.65rem;
            margin-bottom: 5px;
        }

        .pvc-card-front .info-table td {
            padding: 2px 0;
        }

        .pvc-card-front .info-table td.lbl {
            color: #6b7280;
            font-weight: 600;
            width: 45%;
        }

        .pvc-card-front .info-table td.val {
            color: #1f2937;
            font-weight: 700;
        }

        .pvc-card-front .footer-strip {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .pvc-card-front .footer-strip .sign-box {
            text-align: center;
            font-size: 0.45rem;
            color: #6b7280;
            font-weight: 600;
        }

        .pvc-card-front .footer-strip .sign-box .sign-line {
            border-bottom: 0.5px solid #6b7280;
            width: 50px;
            height: 10px;
            margin-bottom: 2px;
        }

        .pvc-card-front .status-badge {
            background-color: #dcfce7;
            color: #15803d;
            font-size: 0.55rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 50px;
            border: 0.5px solid #bbf7d0;
        }

        /* Back Side Styling */
        .pvc-card-back {
            border-top: 6px solid #fbbf24;
            padding: 12px;
        }

        .pvc-card-back h6.back-title {
            color: #1e3a8a;
            font-weight: 700;
            font-size: 0.75rem;
            margin-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }

        .pvc-card-back .back-info {
            font-size: 0.65rem;
            margin-bottom: 10px;
        }

        .pvc-card-back .back-info p {
            margin: 0 0 4px 0;
        }

        .pvc-card-back .back-info strong {
            color: #111827;
        }

        .pvc-card-back .qr-frame-box {
            text-align: center;
            margin-top: 5px;
            margin-bottom: 8px;
        }

        .pvc-card-back .qr-frame-box img {
            width: 60px;
            height: 60px;
            border: 1px solid #e2e8f0;
            padding: 2px;
            background-color: #ffffff;
        }

        .pvc-card-back .rules-box {
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            border-radius: 6px;
            padding: 8px;
            font-size: 0.55rem;
            color: #4b5563;
            margin-bottom: 12px;
        }

        .pvc-card-back .rules-box ol {
            margin: 0;
            padding-left: 12px;
        }

        .pvc-card-back .rules-box li {
            margin-bottom: 3px;
        }

        .pvc-card-back .school-address-box {
            position: absolute;
            bottom: 8px;
            left: 12px;
            right: 12px;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            font-size: 0.5rem;
            color: #6b7280;
            text-align: center;
        }

        .pvc-card-back .school-address-box p {
            margin: 0;
            line-height: 1.2;
        }

        .btn-print-toolbar {
            margin-bottom: 20px;
        }

        @media print {
            .btn-print-toolbar {
                display: none !important;
            }
            body {
                background-color: transparent !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .id-card-wrap {
                margin: 10px !important;
            }
            .pvc-card-side {
                width: 53.98mm !important;
                height: 85.60mm !important;
                box-shadow: none !important;
                border: 1px solid #cbd5e1 !important;
            }
        }
    </style>
</head>
<body>

<div class="btn-print-toolbar">
    <button onclick="window.print()" class="btn btn-primary px-4 rounded-pill shadow-sm">Print ID Card</button>
</div>

<div class="d-flex flex-wrap justify-content-center">
    <div class="id-card-wrap">
        <!-- FRONT -->
        <div class="pvc-card-side pvc-card-front me-3">
            <div class="header-band">
                <img src="<?php echo APP_URL; ?>/assets/images/logo.png" class="school-logo" onerror="this.onerror=null; this.src='https://placehold.co/100x100?text=IGS'">
                <h6>INDUS GRAMMAR SCHOOL</h6>
                <p class="subtitle">Student ID Card</p>
            </div>
            <div class="avatar-box">
                <?php if (!empty($student['doc_student_photo'])): ?>
                    <img src="<?php echo APP_URL . '/' . $student['doc_student_photo']; ?>" alt="Photo" onerror="this.onerror=null; this.src='<?php echo APP_URL; ?>/assets/images/default_student.png';">
                <?php else: ?>
                    <img src="<?php echo APP_URL; ?>/assets/images/default_student.png" alt="Avatar" onerror="this.onerror=null; this.src='https://placehold.co/150x200?text=No+Photo';">
                <?php endif; ?>
            </div>
            <div class="student-name-title"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
            <table class="info-table">
                <tr><td class="lbl">Student ID:</td><td class="val font-monospace"><?php echo htmlspecialchars($student['admission_no']); ?></td></tr>
                <tr><td class="lbl">Roll Number:</td><td class="val"><?php echo htmlspecialchars($student['roll_no'] ?: '—'); ?></td></tr>
                <tr><td class="lbl">Class & Section:</td><td class="val"><?php echo htmlspecialchars(($student['class_name'] ?? $student['school_class'] ?? '—') . ' - ' . ($student['section'] ?? $student['school_section'] ?? 'A')); ?></td></tr>
                <tr><td class="lbl">Academic Type:</td><td class="val"><?php echo htmlspecialchars($student['academic_type']); ?></td></tr>
                <tr><td class="lbl">Session:</td><td class="val"><?php echo htmlspecialchars($student['academic_session'] ?: '—'); ?></td></tr>
                <tr><td class="lbl">Issue Date:</td><td class="val"><?php echo htmlspecialchars(date('d-M-Y', strtotime($student['admission_date'] ?: 'now'))); ?></td></tr>
            </table>
            <div class="footer-strip">
                <span class="status-badge"><?php echo htmlspecialchars($student['status']); ?></span>
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <span class="small text-muted" style="font-size:0.45rem;">Principal Sign</span>
                </div>
            </div>
        </div>

        <!-- BACK -->
        <div class="pvc-card-side pvc-card-back">
            <h6 class="back-title">STUDENT REGISTRY INFO</h6>
            <div class="back-info">
                <p>Father / Guardian Name: <strong><?php echo htmlspecialchars($student['father_name'] ?: $student['guardian_name'] ?: '—'); ?></strong></p>
                <p>Guardian Contact: <strong><?php echo htmlspecialchars($student['father_mobile'] ?: $student['guardian_phone'] ?: '—'); ?></strong></p>
                <p>Emergency Contact: <strong><?php echo htmlspecialchars($student['emergency_contact'] ?: $student['father_mobile'] ?: $student['guardian_phone'] ?: '—'); ?></strong></p>
            </div>
            <div class="qr-frame-box">
                <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code" onerror="this.onerror=null; this.src='https://placehold.co/150x150?text=QR+Code';">
            </div>
            <div class="rules-box">
                <ol>
                    <li>Always display this card while on campus premises.</li>
                    <li>Loss must be reported immediately to school admin.</li>
                    <li>Card is property of Indus Grammar School & Academy.</li>
                </ol>
            </div>
            <div class="school-address-box">
                <p class="fw-bold">Indus Grammar School & Academy</p>
                <p><?php echo SCHOOL_ADDRESS; ?> | Ph: <?php echo SCHOOL_PHONE; ?></p>
                <p>Email: <?php echo SCHOOL_EMAIL; ?> | Web: www.indus.edu.pk</p>
            </div>
        </div>
    </div>
</div>

</body>
</html>
