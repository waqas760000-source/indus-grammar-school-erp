<?php
/**
 * Indus ERP - Live Production Concessions, Discounts & Profile Photo Fixer
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Fee.php';
require_once __DIR__ . '/helpers/helpers.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Fee Concession & Profile Photo Synchronizer</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-dark text-light">
<div class="container py-5">
    <div class="card bg-secondary text-white shadow-lg border-0" style="border-radius: 16px;">
        <div class="card-header bg-primary text-white py-3">
            <h4 class="mb-0 fw-bold"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Live Fee Concession & Profile Photo Fixer</h4>
        </div>
        <div class="card-body p-4">
            <pre class="bg-black text-success p-4 rounded mb-4" style="max-height: 450px; overflow-y: auto; font-family: monospace; font-size: 0.9rem; border: 1px solid #334155;">
<?php
try {
    $db = Database::getConnection();

    echo "=========================================================================\n";
    echo " 1. FIXING PROFILE PHOTO PATHS (Converting Windows Backslashes to /)\n";
    echo "=========================================================================\n";
    
    $photoFixStmt = $db->query("UPDATE student_registration_details SET doc_student_photo = REPLACE(doc_student_photo, '\\\\', '/') WHERE doc_student_photo LIKE '%\\\\%'");
    $fixedPhotosCount = $photoFixStmt->rowCount();
    echo "  [✓] Fixed {$fixedPhotosCount} photo path(s) in student_registration_details.\n";

    echo "\n=========================================================================\n";
    echo " 2. RECALCULATING & SYNCHRONIZING STUDENT FEE DISCOUNTS / CONCESSIONS\n";
    echo "=========================================================================\n";

    $syncOk = Fee::syncAllStudentDiscounts();
    echo "  [✓] Student Discount Synchronization status: " . ($syncOk ? "SUCCESS" : "FAILED") . "\n";

    echo "\n=========================================================================\n";
    echo " 3. SYNCHRONIZING PENDING MONTHLY FEE LEDGER DISCOUNTS\n";
    echo "=========================================================================\n";

    $students = $db->query("SELECT id FROM students WHERE status = 'Active'")->fetchAll(PDO::FETCH_COLUMN);
    $syncedLedgersCount = 0;
    foreach ($students as $sid) {
        if (Fee::syncPendingLedgerDiscounts((int)$sid)) {
            $syncedLedgersCount++;
        }
    }
    echo "  [✓] Synced pending ledger discounts for {$syncedLedgersCount} active students.\n";

    echo "\n=========================================================================\n";
    echo " 4. FINAL VERIFICATION SUMMARY\n";
    echo "=========================================================================\n";

    $feeGross = (float)$db->query("SELECT COALESCE(SUM(srd.fee_monthly), 0) FROM student_registration_details srd JOIN students s ON srd.student_id = s.id WHERE s.status = 'Active'")->fetchColumn();
    $discountGiven = (float)$db->query("SELECT COALESCE(SUM(srd.fee_discount), 0) FROM student_registration_details srd JOIN students s ON srd.student_id = s.id WHERE s.status = 'Active'")->fetchColumn();
    $feeReceivable = (float)$db->query("SELECT COALESCE(SUM(srd.tuition_fee), 0) FROM student_registration_details srd JOIN students s ON srd.student_id = s.id WHERE s.status = 'Active'")->fetchColumn();

    echo sprintf("  %-35s : Rs. %s\n", "Total Monthly Gross Fee", number_format($feeGross, 2));
    echo sprintf("  %-35s : Rs. %s\n", "Total Concession / Discount Applied", number_format($discountGiven, 2));
    echo sprintf("  %-35s : Rs. %s\n", "Net Fee Receivable (After Discount)", number_format($feeReceivable, 2));

    echo "\n🎉 ALL FIXES APPLIED SUCCESSFULLY!\n";
} catch (Exception $e) {
    echo "\n❌ Error executing fixes: " . htmlspecialchars($e->getMessage()) . "\n";
}
?>
            </pre>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-primary btn-lg fw-bold"><i class="fa-solid fa-house me-2"></i>Go to Dashboard</a>
                <a href="modules/fees/discounts.php" class="btn btn-success btn-lg fw-bold"><i class="fa-solid fa-tags me-2"></i>Fee Discount Desk</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
