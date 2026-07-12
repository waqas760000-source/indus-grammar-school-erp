<?php
/**
 * Indus Grammar School ERP - Printable Fee Payment Receipt (V2)
 * Version 4.0.0
 */

require_once __DIR__ . '/../config/app.php';
AuthMiddleware::requireLogin();

$receiptNo = sanitize($_GET['receipt_no'] ?? '');

if (empty($receiptNo)) {
    die("Receipt number required.");
}

try {
    $db = Database::getConnection();
    // Fetch fee payment by receipt_no
    $stmt = $db->prepare("
        SELECT fp.*, fr.receipt_no, s.first_name, s.last_name, s.admission_no, c.class_name, c.section,
               u.username as collector_name, fl.month
        FROM fee_payments fp
        JOIN fee_receipts fr ON fr.payment_id = fp.id
        JOIN students s ON fp.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        JOIN fee_ledger fl ON fp.ledger_id = fl.id
        LEFT JOIN users u ON fp.collected_by = u.id
        WHERE fr.receipt_no = :rno
    ");
    $stmt->execute(['rno' => $receiptNo]);
    $collection = $stmt->fetch();
} catch (Exception $e) {
    die("Error fetching receipt details: " . $e->getMessage());
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
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap');
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; padding: 30px; }
        .receipt-container { max-width: 800px; margin: 0 auto; border: 1px solid #dee2e6; padding: 40px; background-color: #fff; border-radius: 8px; }
        @media print {
            body { padding: 0; background-color: #fff; }
            .receipt-container { border: none; padding: 0; box-shadow: none !important; }
            .btn-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="text-end mb-4 btn-print" style="max-width: 800px; margin: 0 auto;">
    <button onclick="window.print()" class="btn btn-primary px-4"><i class="fa-solid fa-print me-2"></i>Print Receipt</button>
</div>

<div class="receipt-container shadow-sm">
    <!-- Header -->
    <div class="row align-items-center mb-4">
        <div class="col-8">
            <h3 class="fw-bold text-primary mb-1">INDUS GRAMMAR SCHOOL</h3>
            <p class="text-muted small mb-0">Excellence in Education</p>
            <p class="text-muted small">Main Campus, Karachi | Ph: +92-300-1234567</p>
        </div>
        <div class="col-4 text-end">
            <div class="border p-2 bg-light d-inline-block text-center rounded">
                <span class="small fw-semibold text-muted text-uppercase d-block">RECEIPT</span>
                <span class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($receiptNo); ?></span>
            </div>
        </div>
    </div>

    <!-- Student and Payment Details -->
    <div class="row g-3 mb-4 p-3 bg-light rounded border">
        <div class="col-6">
            <table class="table table-sm table-borderless small mb-0">
                <tr><td class="text-muted font-semibold" width="120">Student Name:</td><td class="fw-bold text-dark"><?php echo htmlspecialchars($collection['first_name'] . ' ' . $collection['last_name']); ?></td></tr>
                <tr><td class="text-muted font-semibold">Admission No:</td><td class="fw-semibold"><code><?php echo htmlspecialchars($collection['admission_no']); ?></code></td></tr>
                <tr><td class="text-muted font-semibold">Class / Section:</td><td class="fw-semibold text-dark"><?php echo htmlspecialchars($collection['class_name'] . ' - ' . $collection['section']); ?></td></tr>
            </table>
        </div>
        <div class="col-6 text-end">
            <table class="table table-sm table-borderless small mb-0 ms-auto" style="width: auto;">
                <tr><td class="text-muted text-start" width="120">Payment Date:</td><td class="fw-semibold text-end text-dark"><?php echo date('d M Y', strtotime($collection['payment_date'])); ?></td></tr>
                <tr><td class="text-muted text-start">Billing Month:</td><td class="fw-bold text-end text-dark"><?php echo htmlspecialchars($collection['month']); ?></td></tr>
                <tr><td class="text-muted text-start">Cashier Name:</td><td class="text-end fw-semibold text-primary"><?php echo htmlspecialchars($collection['collector_name'] ?: 'System Cashier'); ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Payment particulars -->
    <table class="table table-bordered mb-4">
        <thead class="table-light">
            <tr>
                <th>Description</th>
                <th>Payment Method</th>
                <th>Reference Number</th>
                <th class="text-end" width="160">Amount Paid</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div>School Fee Ledger Payment (Month: <?php echo htmlspecialchars($collection['month']); ?>)</div>
                    <small class="text-muted"><?php echo htmlspecialchars($collection['remarks'] ?: 'Paid monthly dues'); ?></small>
                </td>
                <td>
                    <span class="badge bg-light text-dark border px-2 py-1"><i class="fa-solid fa-money-bill me-1"></i><?php echo htmlspecialchars($collection['payment_method']); ?></span>
                </td>
                <td><span class="text-muted"><?php echo htmlspecialchars($collection['reference_number'] ?: '—'); ?></span></td>
                <td class="text-end fw-bold text-success fs-5">Rs. <?php echo number_format($collection['amount_paid'], 2); ?></td>
            </tr>
            <tr class="table-light">
                <td colspan="3" class="text-end fw-bold text-dark">Total Amount Received:</td>
                <td class="text-end fw-bold text-success fs-5">Rs. <?php echo number_format($collection['amount_paid'], 2); ?></td>
            </tr>
        </tbody>
    </table>

    <div class="row mt-5 pt-4 text-center">
        <div class="col-6 text-start">
            <span class="small text-muted">Thank you for depositing the dues on time.</span>
        </div>
        <div class="col-6 text-end">
            <div style="width: 200px; margin-left: auto;">
                <hr class="border-dark my-1">
                <span class="small fw-semibold text-muted">Authorized Signature / Stamp</span>
            </div>
        </div>
    </div>
</div>

</body>
</html>
