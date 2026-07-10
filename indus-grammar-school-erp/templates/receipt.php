<?php
/**
 * Indus Grammar School ERP - Printable Fee Receipt
 * Version 1.0.0
 */

require_once __DIR__ . '/../config/app.php';
AuthMiddleware::requireLogin();

$receiptNo = sanitize($_GET['receipt_no'] ?? '');

if (empty($receiptNo)) {
    die("Receipt number required.");
}

try {
    $db = Database::getConnection();
    // Fetch fee collection by receipt_no
    $stmt = $db->prepare("
        SELECT fc.*, s.first_name, s.last_name, s.admission_no, c.class_name, c.section,
               u.username as collector_name, ch.challan_no, ch.due_date, ch.net_amount
        FROM fee_collections fc
        JOIN students s ON fc.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN fee_challans ch ON fc.challan_id = ch.id
        LEFT JOIN users u ON fc.collected_by = u.id
        WHERE fc.receipt_no = :rno
    ");
    $stmt->execute(['rno' => $receiptNo]);
    $collection = $stmt->fetch();
} catch (Exception $e) {
    die("Error fetching receipt details.");
}

if (!$collection) {
    die("Receipt not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Receipt - <?php echo htmlspecialchars($receiptNo); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #fff; padding: 30px; }
        .receipt-container { max-width: 800px; margin: 0 auto; border: 1px solid #dee2e6; padding: 40px; }
        .divider { border-top: 2px dashed #dee2e6; margin: 30px 0; }
        @media print {
            body { padding: 0; }
            .receipt-container { border: none; padding: 0; }
            .btn-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="text-end mb-4 btn-print">
    <button onclick="window.print()" class="btn btn-primary"><i class="fa-solid fa-print me-2"></i>Print Receipt</button>
</div>

<div class="receipt-container shadow-sm">
    <!-- Header -->
    <div class="row align-items-center mb-4">
        <div class="col-8">
            <h3 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h3>
            <p class="text-muted small mb-0">Excellence in Education</p>
            <p class="text-muted small">Karachi, Pakistan | Ph: +92-300-1234567</p>
        </div>
        <div class="col-4 text-end">
            <div class="border p-2 bg-light d-inline-block text-center">
                <span class="small fw-semibold text-muted text-uppercase d-block">RECEIPT</span>
                <span class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($receiptNo); ?></span>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6">
            <table class="table table-sm table-borderless small mb-0">
                <tr><td class="text-muted" width="120">Student Name:</td><td class="fw-bold"><?php echo htmlspecialchars($collection['first_name'] . ' ' . $collection['last_name']); ?></td></tr>
                <tr><td class="text-muted">Admission No:</td><td class="fw-semibold"><?php echo htmlspecialchars($collection['admission_no']); ?></td></tr>
                <tr><td class="text-muted">Class:</td><td><?php echo htmlspecialchars($collection['class_name'] . ' - ' . $collection['section']); ?></td></tr>
            </table>
        </div>
        <div class="col-6 text-end">
            <table class="table table-sm table-borderless small mb-0 ms-auto" style="width: auto;">
                <tr><td class="text-muted text-start" width="120">Payment Date:</td><td class="fw-semibold text-end"><?php echo date('d M Y', strtotime($collection['payment_date'])); ?></td></tr>
                <tr><td class="text-muted text-start">Challan Ref:</td><td class="fw-semibold text-end"><?php echo htmlspecialchars($collection['challan_no'] ?: 'Direct Payment'); ?></td></tr>
                <tr><td class="text-muted text-start">Collected By:</td><td class="text-end"><?php echo htmlspecialchars($collection['collector_name'] ?: 'System'); ?></td></tr>
            </table>
        </div>
    </div>

    <table class="table table-bordered mb-4">
        <thead class="table-light">
            <tr>
                <th>Description</th>
                <th class="text-end" width="150">Amount Paid</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>School Fee Payment (Challan #<?php echo htmlspecialchars($collection['challan_no'] ?: '-'); ?>)</td>
                <td class="text-end fw-bold">Rs. <?php echo number_format($collection['amount_paid'], 2); ?></td>
            </tr>
            <tr class="table-light">
                <td class="text-end fw-bold">Total Paid:</td>
                <td class="text-end fw-bold text-success fs-5">Rs. <?php echo number_format($collection['amount_paid'], 2); ?></td>
            </tr>
        </tbody>
    </table>

    <div class="row mt-5 pt-4 text-center">
        <div class="col-4 offset-8">
            <hr class="border-dark">
            <span class="small fw-semibold text-muted">Authorized Signature</span>
        </div>
    </div>
</div>

</body>
</html>
