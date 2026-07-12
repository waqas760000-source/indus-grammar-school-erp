<?php
/**
 * Indus Grammar School ERP - Printable Fee Ledger Challan Coupon Template (V2)
 * Version 4.0.0
 */

require_once __DIR__ . '/../config/app.php';
AuthMiddleware::requireLogin();

$challanId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$classId   = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$month     = sanitize($_GET['month'] ?? '');

$challansToPrint = [];

try {
    $db = Database::getConnection();
    
    if ($challanId > 0) {
        // Print Single Challan
        $challan = Fee::getLedgerById($challanId);
        if ($challan) {
            $challansToPrint[] = $challan;
        }
    } elseif ($classId > 0 && $month !== '') {
        // Print Class Batch Challans (Pending/Partial dues)
        $filters = [
            'class_id' => $classId,
            'month'    => $month,
            'status'   => 'Pending'
        ];
        $batch = Fee::allChallans($filters, 500, 0);
        foreach ($batch as $ch) {
            $challansToPrint[] = $ch;
        }
    }
} catch (Exception $e) {
    die("Error retrieving ledger details: " . $e->getMessage());
}

if (empty($challansToPrint)) {
    die("No pending fee ledger entries found matching your selection.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Challans Print Sheet</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap');
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8f9fa;
            color: #212529;
            padding: 20px 0;
        }
        .challan-page {
            width: 210mm; /* A4 width */
            margin: 0 auto 30px auto;
            background: #fff;
            padding: 20px 30px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            border-radius: 8px;
            page-break-after: always;
        }
        .coupon {
            padding: 15px 0;
            border-bottom: 2px dashed #bbb;
        }
        .coupon:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .school-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: #0d6efd;
            letter-spacing: 0.5px;
        }
        .coupon-header-badge {
            border: 2px solid #212529;
            font-weight: 700;
            font-size: 0.75rem;
            padding: 4px 10px;
            display: inline-block;
            text-transform: uppercase;
        }
        .meta-table td {
            font-size: 0.75rem;
            padding: 2px 5px;
        }
        .items-table th, .items-table td {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        .instructions-text {
            font-size: 0.65rem;
            line-height: 1.3;
            color: #555;
        }
        .sig-line {
            border-top: 1px solid #777;
            width: 85%;
            margin: 20px auto 0 auto;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        /* Print styling overrides */
        @media print {
            body {
                background-color: #fff;
                padding: 0;
                margin: 0;
            }
            .challan-page {
                box-shadow: none;
                margin: 0;
                padding: 10px 15px;
                border-radius: 0;
                width: auto;
            }
            .no-print {
                display: none !important;
            }
            .coupon {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<div class="container text-end mb-4 no-print" style="max-width: 210mm;">
    <button onclick="window.print()" class="btn btn-primary px-4 shadow-sm">
        <i class="fa-solid fa-print me-2"></i>Print All Challans
    </button>
</div>

<?php foreach ($challansToPrint as $ch): 
    // Construct items array from columns
    $items = [];
    if ((float)$ch['tuition_fee'] > 0)       $items[] = ['name' => 'Tuition Fee', 'amount' => (float)$ch['tuition_fee']];
    if ((float)$ch['admission_fee'] > 0)     $items[] = ['name' => 'Admission Fee', 'amount' => (float)$ch['admission_fee']];
    if ((float)$ch['computer_fee'] > 0)      $items[] = ['name' => 'Computer Fee', 'amount' => (float)$ch['computer_fee']];
    if ((float)$ch['exam_fee'] > 0)          $items[] = ['name' => 'Examination Fee', 'amount' => (float)$ch['exam_fee']];
    if ((float)$ch['transport_fee'] > 0)     $items[] = ['name' => 'Transport Fee', 'amount' => (float)$ch['transport_fee']];
    if ((float)$ch['annual_charges'] > 0)    $items[] = ['name' => 'Annual Charges', 'amount' => (float)$ch['annual_charges']];
    if ((float)$ch['security_deposit'] > 0)  $items[] = ['name' => 'Security Deposit', 'amount' => (float)$ch['security_deposit']];
    if ((float)$ch['other_charges'] > 0)     $items[] = ['name' => 'Other Charges', 'amount' => (float)$ch['other_charges']];
    if ((float)$ch['fine_amount'] > 0)       $items[] = ['name' => 'Manual Fine', 'amount' => (float)$ch['fine_amount']];
?>
    <div class="challan-page">
        <?php 
        $copies = ['Bank Copy', 'School Copy', 'Student Copy'];
        foreach ($copies as $copyName): 
        ?>
            <div class="coupon">
                <!-- Coupon Header -->
                <div class="row align-items-center mb-2">
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <span class="school-title">INDUS GRAMMAR SCHOOL</span>
                        </div>
                        <span class="text-muted" style="font-size: 0.65rem; display:block;">Main Campus, Karachi | Ph: +92 300 1234567</span>
                    </div>
                    <div class="col-6 text-end">
                        <div class="coupon-header-badge mb-1"><?php echo $copyName; ?></div>
                        <div class="text-dark fw-bold" style="font-size: 0.85rem;">Ledger Invoice: #<?php echo str_pad($ch['id'], 5, '0', STR_PAD_LEFT); ?></div>
                    </div>
                </div>

                <!-- Student Details Grid -->
                <div class="row g-2 mb-2 bg-light p-2 rounded" style="border: 1px solid #e3e6f0;">
                    <div class="col-8">
                        <table class="table table-sm table-borderless mb-0 meta-table">
                            <tr>
                                <td class="text-muted" width="100">Student Name:</td>
                                <td class="fw-bold text-dark"><?php echo sanitize($ch['first_name'] . ' ' . $ch['last_name']); ?></td>
                                <td class="text-muted" width="80">Father Name:</td>
                                <td><?php echo sanitize($ch['father_name'] ?: '—'); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Admission No:</td>
                                <td class="fw-semibold"><code><?php echo sanitize($ch['admission_no']); ?></code></td>
                                <td class="text-muted">Class/Section:</td>
                                <td><?php echo sanitize($ch['class_name'] . ' - ' . $ch['section']); ?> (<?php echo sanitize($ch['academic_type']); ?>)</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-4 text-end">
                        <table class="table table-sm table-borderless mb-0 meta-table ms-auto" style="width: auto;">
                            <tr>
                                <td class="text-muted text-start" width="70">Bill Month:</td>
                                <td class="fw-semibold text-end"><?php echo sanitize($ch['month']); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted text-start text-danger">Due Date:</td>
                                <td class="fw-bold text-end text-danger"><?php echo date('d M Y', strtotime($ch['due_date'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Challan Fee Breakdown -->
                <div class="row g-3 mb-2">
                    <div class="col-7">
                        <table class="table table-sm table-bordered items-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fee Description / Particulars</th>
                                    <th class="text-end" width="120">Amount (Rs.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo sanitize($item['name']); ?></td>
                                        <td class="text-end">Rs. <?php echo number_format($item['amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if ((float)$ch['discount_amount'] > 0): ?>
                                    <tr>
                                        <td class="text-success fw-semibold"><i class="fa-solid fa-tag me-1"></i>Applied Discounts</td>
                                        <td class="text-end text-success fw-semibold">- Rs. <?php echo number_format($ch['discount_amount'], 2); ?></td>
                                    </tr>
                                <?php endif; ?>
                                
                                <tr class="table-light fw-bold text-dark">
                                    <td>Net Payable Total</td>
                                    <td class="text-end">Rs. <?php echo number_format($ch['total_payable'], 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Instructions and Signatures -->
                    <div class="col-5 d-flex flex-column justify-content-between">
                        <div class="instructions-text bg-light p-2 rounded mb-2" style="border: 1px dashed #ccc;">
                            <strong>Instructions:</strong>
                            <ul class="ps-2 mb-0" style="list-style-type: square;">
                                <li>Late payments will attract dynamic fine calculations.</li>
                                <li>All fees once paid are non-refundable and non-transferable.</li>
                                <li>Keep this printed coupon safe as proof of deposit.</li>
                            </ul>
                        </div>
                        
                        <div class="row text-center mt-auto">
                            <div class="col-6">
                                <div class="sig-line">Depositor Sign</div>
                            </div>
                            <div class="col-6">
                                <div class="sig-line">Bank/Cashier Sign</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

</body>
</html>
