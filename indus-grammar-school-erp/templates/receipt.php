<?php
/**
 * Indus Grammar School ERP - Executive Printable Fee Receipt Template (V3)
 * Dual-Copy Official Receipt Sheet (Student Copy + Office Copy)
 * Version 4.5.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

AuthMiddleware::requireLogin();

$receiptNo = sanitize($_GET['receipt_no'] ?? ($_GET['id'] ?? ''));

$collection = null;

try {
    $db = Database::getConnection();
    
    if (!empty($receiptNo)) {
        $stmt = $db->prepare("
            SELECT fp.*, fr.receipt_no, 
                   s.first_name, s.last_name, s.admission_no, s.academic_type, 
                   c.class_name, c.section,
                   d.father_name, d.roll_no,
                   u.username as collector_name, 
                   fl.month, fl.tuition_fee, fl.admission_fee, fl.computer_fee, fl.exam_fee, fl.transport_fee, fl.annual_charges, fl.security_deposit, fl.other_charges, fl.fine_amount, fl.discount_amount, fl.total_payable
            FROM fee_receipts fr
            JOIN fee_payments fp ON fr.payment_id = fp.id
            JOIN students s ON fp.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN student_registration_details d ON s.id = d.student_id
            LEFT JOIN fee_ledger fl ON fp.ledger_id = fl.id
            LEFT JOIN users u ON fp.collected_by = u.id
            WHERE fr.receipt_no = :rno OR fr.id = :rno_id OR fp.id = :rno_pay
            LIMIT 1
        ");
        $stmt->execute(['rno' => $receiptNo, 'rno_id' => (int)$receiptNo, 'rno_pay' => (int)$receiptNo]);
        $collection = $stmt->fetch();
    }

    // Fallback: Fetch latest receipt if none specified or not found
    if (!$collection) {
        $stmt = $db->prepare("
            SELECT fp.*, fr.receipt_no, 
                   s.first_name, s.last_name, s.admission_no, s.academic_type, 
                   c.class_name, c.section,
                   d.father_name, d.roll_no,
                   u.username as collector_name, 
                   fl.month, fl.tuition_fee, fl.admission_fee, fl.computer_fee, fl.exam_fee, fl.transport_fee, fl.annual_charges, fl.security_deposit, fl.other_charges, fl.fine_amount, fl.discount_amount, fl.total_payable
            FROM fee_receipts fr
            JOIN fee_payments fp ON fr.payment_id = fp.id
            JOIN students s ON fp.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN student_registration_details d ON s.id = d.student_id
            LEFT JOIN fee_ledger fl ON fp.ledger_id = fl.id
            LEFT JOIN users u ON fp.collected_by = u.id
            ORDER BY fr.id DESC
            LIMIT 1
        ");
        $stmt->execute();
        $collection = $stmt->fetch();
    }
} catch (Exception $e) {
    die("Error fetching fee receipt details: " . $e->getMessage());
}

if (!$collection) {
    die("No fee receipt records found in database.");
}

$schoolLogoUrl = getSchoolLogoUrl();

// Helper to convert numeric amounts to words (PKR format)
if (!function_exists('convertNumberToWords')) {
    function convertNumberToWords(float $amount): string {
        $number = (int)round($amount);
        if ($number <= 0) return 'Zero Rupees Only';
        
        $ones = [
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
        ];
        $tens = [
            0 => '', 2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
            6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'
        ];
        
        $numToWords = function($n) use (&$numToWords, $ones, $tens) {
            if ($n < 20) return $ones[$n];
            if ($n < 100) return $tens[(int)($n / 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
            if ($n < 1000) return $ones[(int)($n / 100)] . ' Hundred' . ($n % 100 ? ' ' . $numToWords($n % 100) : '');
            if ($n < 100000) return $numToWords((int)($n / 1000)) . ' Thousand' . ($n % 1000 ? ' ' . $numToWords($n % 1000) : '');
            if ($n < 10000000) return $numToWords((int)($n / 100000)) . ' Lakh' . ($n % 100000 ? ' ' . $numToWords($n % 100000) : '');
            return $numToWords((int)($n / 10000000)) . ' Crore' . ($n % 10000000 ? ' ' . $numToWords($n % 10000000) : '');
        };
        
        return trim($numToWords($number)) . ' Rupees Only';
    }
}

$amountPaid = (float)$collection['amount_paid'];
$amountWords = convertNumberToWords($amountPaid);
$paymentDateFormatted = date('d M Y, h:i A', strtotime($collection['payment_date']));

$copies = [
    ['tag' => 'STUDENT COPY', 'color' => '#1e3a8a'],
    ['tag' => 'OFFICE / SCHOOL COPY', 'color' => '#881337']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official Fee Receipt - <?php echo htmlspecialchars($collection['receipt_no']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Outfit:wght@400;500;600;700;800&display=swap');

        @page {
            size: A4 portrait;
            margin: 5mm 6mm;
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

        .receipt-sheet {
            width: 200mm;
            min-height: 282mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 5mm;
            box-shadow: 0 6px 25px rgba(0,0,0,0.1);
            border-radius: 4px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .receipt-card {
            border: 2px solid #0f172a;
            padding: 12px 14px;
            background: #ffffff;
            position: relative;
            box-sizing: border-box;
            border-radius: 6px;
        }

        .cut-divider {
            text-align: center;
            margin: 10px 0;
            position: relative;
        }

        .cut-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            border-top: 1.5px dashed #94a3b8;
            z-index: 1;
        }

        .cut-badge {
            position: relative;
            z-index: 2;
            background: #ffffff;
            padding: 2px 12px;
            font-size: 0.65rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
        }

        /* Header Layout */
        .receipt-header-table {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .logo-box-img {
            max-height: 48px;
            max-width: 60px;
            object-fit: contain;
        }

        .school-title {
            font-family: 'Cinzel', serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: #881337;
            margin: 0;
            line-height: 1.1;
            letter-spacing: 0.5px;
        }

        .school-sub {
            font-size: 0.7rem;
            font-weight: 700;
            color: #1e3a8a;
            margin-top: 2px;
        }

        .school-contact {
            font-size: 0.62rem;
            color: #475569;
            font-weight: 600;
        }

        .receipt-tag-box {
            border: 2px solid #0f172a;
            background: #f8fafc;
            padding: 3px 8px;
            font-weight: 800;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
        }

        .receipt-no-highlight {
            font-size: 0.8rem;
            font-weight: 800;
            color: #0f172a;
            margin-top: 3px;
        }

        /* Paid Seal Badge */
        .paid-seal {
            border: 2px solid #15803d;
            background: #f0fdf4;
            color: #166534;
            font-size: 0.62rem;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 4px;
            text-transform: uppercase;
            box-shadow: 0 2px 5px rgba(21, 128, 61, 0.12);
        }

        /* Info Grid Tables */
        .info-grid-table {
            width: 100%;
            border: 1px solid #1e293b;
            border-collapse: collapse;
            margin-bottom: 8px;
            background: #fcfbf9;
        }

        .info-grid-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            font-size: 0.72rem;
            vertical-align: middle;
        }

        .info-grid-table td.lbl {
            font-weight: 700;
            color: #0f172a;
            background: #f1f5f9;
            width: 20%;
        }

        .info-grid-table td.val {
            font-weight: 600;
            color: #1e293b;
        }

        /* Items Breakdown Table */
        .items-breakdown-table {
            width: 100%;
            border: 1.5px solid #0f172a;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .items-breakdown-table th {
            background: #0f172a;
            color: #ffffff;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 5px 8px;
            text-transform: uppercase;
            border: 1px solid #0f172a;
        }

        .items-breakdown-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            font-size: 0.72rem;
            color: #0f172a;
        }

        .items-breakdown-table tr.total-row td {
            border-top: 2px solid #0f172a;
            background: #f8fafc;
            font-weight: 800;
            font-size: 0.8rem;
        }

        /* Words & Payment Info Box */
        .words-info-bar {
            border: 1px solid #1e293b;
            background: #fffdf5;
            padding: 5px 8px;
            font-size: 0.68rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Signature Row */
        .receipt-footer-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 14px;
            padding: 0 4px;
        }

        .sig-box {
            text-align: center;
            width: 42%;
            border-top: 1.5px solid #475569;
            padding-top: 3px;
            font-size: 0.65rem;
            font-weight: 700;
            color: #1e293b;
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

            .receipt-sheet {
                box-shadow: none;
                margin: 0;
                padding: 0;
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
            <i class="fa-solid fa-receipt text-success me-2"></i>Official Fee Payment Receipt
        </h5>
        <span class="text-muted small">Dual Copy Printable Voucher (Student Copy & Office Copy)</span>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary fw-bold px-4 shadow-sm">
            <i class="fa-solid fa-print me-2"></i>Print Official Receipt
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3">
            Close Window
        </button>
    </div>
</div>

<div class="receipt-sheet">
    <?php foreach ($copies as $index => $cp): ?>
        <?php if ($index > 0): ?>
            <div class="cut-divider">
                <span class="cut-badge"><i class="fa-solid fa-scissors me-1"></i> Cut / Detach Here</span>
            </div>
        <?php endif; ?>

        <div class="receipt-card">
            <!-- Header Table -->
            <table class="receipt-header-table">
                <tr>
                    <td width="60" vertical-align="middle">
                        <?php if (!empty($schoolLogoUrl)): ?>
                            <img src="<?php echo $schoolLogoUrl; ?>" alt="Logo" class="logo-box-img">
                        <?php else: ?>
                            <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center fw-bold" style="width: 42px; height: 42px; font-size: 0.8rem;">IGS</div>
                        <?php endif; ?>
                    </td>
                    <td vertical-align="middle" class="ps-2">
                        <h1 class="school-title">INDUS GRAMMAR SCHOOL</h1>
                        <div class="school-sub">EXCELLENCE IN EDUCATION &middot; MAIN CAMPUS</div>
                        <div class="school-contact">Lahore, Pakistan &middot; Ph: +92 306 6544806 &middot; info@indusgrammar.edu.pk</div>
                    </td>
                    <td width="160" text-align="right" class="text-end ps-2" vertical-align="top">
                        <div class="receipt-tag-box" style="border-color: <?php echo $cp['color']; ?>; color: <?php echo $cp['color']; ?>;">
                            <?php echo $cp['tag']; ?>
                        </div>
                        <div class="receipt-no-highlight">
                            #<?php echo htmlspecialchars($collection['receipt_no']); ?>
                        </div>
                        <div>
                            <span class="paid-seal">
                                <i class="fa-solid fa-circle-check"></i> PAID & VERIFIED
                            </span>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Student & Collection Info Grid -->
            <table class="info-grid-table">
                <tr>
                    <td class="lbl">Student Name :</td>
                    <td class="val text-uppercase fw-bold"><?php echo htmlspecialchars($collection['first_name'] . ' ' . $collection['last_name']); ?></td>
                    <td class="lbl">Father Name :</td>
                    <td class="val text-uppercase"><?php echo htmlspecialchars($collection['father_name'] ?: 'N/A'); ?></td>
                </tr>
                <tr>
                    <td class="lbl">Roll / Adm No :</td>
                    <td class="val font-monospace fw-bold text-primary"><?php echo htmlspecialchars($collection['admission_no']); ?></td>
                    <td class="lbl">Class & Sec :</td>
                    <td class="val"><?php echo htmlspecialchars(($collection['class_name'] ?? 'Class') . ' - ' . ($collection['section'] ?? 'A')); ?> (<?php echo htmlspecialchars($collection['academic_type'] ?? 'School'); ?>)</td>
                </tr>
                <tr>
                    <td class="lbl">Payment Date :</td>
                    <td class="val fw-semibold"><?php echo $paymentDateFormatted; ?></td>
                    <td class="lbl">Billing Month :</td>
                    <td class="val fw-bold text-primary"><?php echo htmlspecialchars($collection['month'] ?? 'Monthly Fee'); ?></td>
                </tr>
                <tr>
                    <td class="lbl">Pay Method :</td>
                    <td class="val fw-bold text-success">
                        <i class="fa-solid fa-wallet me-1"></i><?php echo htmlspecialchars($collection['payment_method'] ?? 'Cash'); ?>
                        <?php if (!empty($collection['reference_number'])): ?>
                            <span class="text-muted font-monospace small">(Ref: <?php echo htmlspecialchars($collection['reference_number']); ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td class="lbl">Collected By :</td>
                    <td class="val fw-semibold text-dark"><?php echo htmlspecialchars($collection['collector_name'] ?: 'Accounts Officer'); ?></td>
                </tr>
            </table>

            <!-- Particulars & Received Table -->
            <table class="items-breakdown-table">
                <thead>
                    <tr>
                        <th width="35" class="text-center">S.N</th>
                        <th>DESCRIPTION / FEE PARTICULARS</th>
                        <th width="120" class="text-center">BILLING MONTH</th>
                        <th width="140" class="text-end">AMOUNT RECEIVED</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center text-muted">1</td>
                        <td>
                            <div class="fw-bold text-dark">School Fee Payment & Ledger Deposit</div>
                            <div class="text-muted small"><?php echo htmlspecialchars($collection['remarks'] ?: 'Monthly fee installment received against official ledger statement.'); ?></div>
                        </td>
                        <td class="text-center fw-semibold text-primary"><?php echo htmlspecialchars($collection['month'] ?? '—'); ?></td>
                        <td class="text-end font-monospace fw-bold text-success fs-6">Rs. <?php echo number_format($amountPaid, 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3" class="text-end text-uppercase text-dark">NET AMOUNT RECEIVED TOTAL</td>
                        <td class="text-end font-monospace text-success fs-6">Rs. <?php echo number_format($amountPaid, 2); ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Words & Footer Bar -->
            <div class="words-info-bar">
                <div>
                    AMOUNT IN WORDS: <span class="text-uppercase text-primary fw-bold"><?php echo htmlspecialchars($amountWords); ?></span>
                </div>
                <div class="text-muted small">
                    <i class="fa-solid fa-shield-halved me-1 text-success"></i>Computerized System Generated Slip
                </div>
            </div>

            <!-- Footer Signatures -->
            <div class="receipt-footer-row">
                <div class="sig-box">
                    Student / Depositor Signature
                </div>
                <div class="sig-box">
                    Authorized Cashier / Stamp
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>
