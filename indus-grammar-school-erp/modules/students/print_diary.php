<?php
/**
 * Indus Grammar School ERP - Print Single Diary Entry
 * Version 4.0.0
 */

require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$diary = null;
$errorMsg = '';

if ($id <= 0) {
    $errorMsg = "Diary record not found.";
} else {
    try {
        $stmt = $db->prepare("
            SELECT d.*, u.username as creator_name, st.first_name as teacher_first, st.last_name as teacher_last
            FROM daily_diaries d
            LEFT JOIN users u ON d.created_by = u.id
            LEFT JOIN staff st ON d.teacher_id = st.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $diary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$diary) {
            $errorMsg = "Diary record not found.";
        }
    } catch (Exception $e) {
        error_log("Error retrieving diary print details: " . $e->getMessage());
        $errorMsg = "Diary record not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Diary #<?php echo $id; ?></title>
    <!-- Include Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        #diary-print {
            padding: 15mm;
            font-family: Arial, sans-serif;
            background: #fff;
            color: #000;
            max-width: 800px;
            margin: auto;
        }

        @media print {
            body {
                background: white !important;
                color: black !important;
            }
            /* Hide all default web layout elements during printing */
            body * {
                visibility: hidden;
            }
            #diary-print,
            #diary-print * {
                visibility: visible;
            }
            #diary-print {
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 15mm;
                box-sizing: border-box;
            }
            .no-print {
                display: none !important;
            }
            @page {
                size: A4 portrait;
                margin: 0;
            }
        }
    </style>
</head>
<body>

<div class="container py-4 no-print text-center">
    <button class="btn btn-primary px-4 me-2" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Now</button>
    <button class="btn btn-outline-secondary px-3" onclick="window.close()"><i class="fa-solid fa-xmark me-1"></i>Close Page</button>
</div>

<?php if (!empty($errorMsg)): ?>
    <div class="container mt-5">
        <div class="alert alert-danger py-4 text-center border-0 shadow-sm" style="border-radius:12px;">
            <i class="fa-solid fa-triangle-exclamation fs-1 text-danger mb-3 d-block"></i>
            <h5 class="fw-bold"><?php echo htmlspecialchars($errorMsg); ?></h5>
        </div>
    </div>
<?php else: 
    $teacherName = trim(($diary['teacher_first'] ?? '') . ' ' . ($diary['teacher_last'] ?? ''));
    if (empty($teacherName)) $teacherName = $diary['creator_name'] ?: 'System Administrator';
?>
    <div id="diary-print">
        <!-- Header Section -->
        <div class="d-flex align-items-center justify-content-between border-bottom border-dark pb-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 50px; height: 50px; background-color: #1e3a8a; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white;">
                    <i class="fa-solid fa-graduation-cap fa-2x"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0 text-dark" style="font-family: Arial, sans-serif; font-size: 1.5rem;"><?php echo SCHOOL_NAME; ?></h3>
                    <p class="text-muted small mb-0" style="font-size: 0.75rem;"><i class="fa-solid fa-location-dot me-1"></i><?php echo SCHOOL_ADDRESS; ?></p>
                </div>
            </div>
            <div class="text-end">
                <h4 class="fw-bold text-secondary mb-0" style="font-size: 1.25rem;">DIARY REPORT</h4>
                <span class="small text-muted" style="font-size: 0.7rem;">Generated: <?php echo date('d-M-Y H:i'); ?></span>
            </div>
        </div>

        <!-- Meta Details Table -->
        <table class="table table-bordered border-dark mb-4" style="font-size: 11px;">
            <tr>
                <th width="20%">Diary Date:</th>
                <td width="30%"><strong><?php echo date('d-M-Y', strtotime($diary['diary_date'])); ?></strong></td>
                <th width="20%">Academic Type:</th>
                <td width="30%"><?php echo htmlspecialchars($diary['academic_type']); ?></td>
            </tr>
            <tr>
                <th>Class:</th>
                <td><?php echo htmlspecialchars($diary['class']); ?></td>
                <th>Section:</th>
                <td><?php echo htmlspecialchars($diary['section'] ?: 'A'); ?></td>
            </tr>
            <tr>
                <th>Subject Name:</th>
                <td><strong><?php echo htmlspecialchars($diary['subject'] ?: 'General'); ?></strong></td>
                <th>Teacher Name:</th>
                <td><?php echo htmlspecialchars($teacherName); ?></td>
            </tr>
            <tr>
                <th>Diary Type / Status:</th>
                <td colspan="3"><strong class="text-primary"><?php echo htmlspecialchars($diary['diary_type']); ?></strong> (<?php echo htmlspecialchars($diary['status']); ?>)</td>
            </tr>
        </table>

        <!-- Diary Title -->
        <div class="mb-4">
            <h5 class="fw-bold text-dark border-bottom border-dark pb-2 mb-2" style="font-size: 13px;">Diary Title: <?php echo htmlspecialchars($diary['title']); ?></h5>
        </div>

        <!-- Diary Content (Description / Homework / Instructions) -->
        <div class="mb-5">
            <h6 class="fw-bold text-dark mb-2" style="font-size: 12px;">Diary Details / Homework Instructions:</h6>
            <div class="p-3 border border-dark rounded bg-light" style="font-size: 11px; line-height: 1.5; min-height: 150px; white-space: pre-line;">
                <?php echo htmlspecialchars($diary['description']); ?>
            </div>
        </div>

        <!-- Authorized Signature area -->
        <div class="row pt-5 mt-5 align-items-end" style="font-size: 11px;">
            <div class="col-6">
                <div style="border-top: 1px solid #000; width: 180px;" class="pt-2 text-center">
                    <strong>Class Teacher Signature</strong>
                </div>
            </div>
            <div class="col-6 text-end">
                <div style="border-top: 1px solid #000; width: 180px; margin-left: auto;" class="pt-2 text-center">
                    <strong>Authorized Signature Area</strong>
                </div>
            </div>
        </div>

        <!-- Generated Date and Page footer -->
        <div class="text-center text-muted small mt-5 border-top border-light pt-3" style="font-size: 9px;">
            Generated Date: <?php echo date('d-M-Y H:i:s'); ?> | Page Number: 1 of 1 | System: Indus Grammar School ERP
        </div>
    </div>
<?php endif; ?>

<script>
    // Automatically trigger browser print dialog on load
    window.onload = function() {
        <?php if (empty($errorMsg)): ?>
            window.print();
        <?php endif; ?>
    }
</script>

</body>
</html>
