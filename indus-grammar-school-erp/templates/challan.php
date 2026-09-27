<?php
/**
 * Indus Grammar School ERP - Printable Fee Challan Template
 * High-Precision 3-Copy Bank Fee Challan Sheet (A4 Landscape)
 * Version 4.5.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

AuthMiddleware::requireLogin();

$challanId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$classId   = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$month     = sanitize($_GET['month'] ?? '');

$challansToPrint = [];

try {
    if ($challanId > 0) {
        // Single Ledger Challan
        $challan = Fee::getLedgerById($challanId);
        if ($challan) {
            $challansToPrint[] = $challan;
        }
    } elseif ($studentId > 0) {
        // Challans for specific student
        $filters = ['student_id' => $studentId];
        if ($month !== '') $filters['month'] = $month;
        $batch = Fee::allChallans($filters, 50, 0);
        foreach ($batch as $ch) {
            $challansToPrint[] = $ch;
        }
    } elseif ($classId > 0 && $month !== '') {
        // Batch Challans for Class & Month
        $filters = [
            'class_id' => $classId,
            'month'    => $month,
            'status'   => 'Pending'
        ];
        $batch = Fee::allChallans($filters, 500, 0);
        foreach ($batch as $ch) {
            $challansToPrint[] = $ch;
        }
    } else {
        // Default: Fetch latest pending ledgers for previewing
        $batch = Fee::allChallans(['status' => 'Pending'], 5, 0);
        if (empty($batch)) {
            $batch = Fee::allChallans([], 5, 0);
        }
        foreach ($batch as $ch) {
            $challansToPrint[] = $ch;
        }
    }
} catch (Exception $e) {
    die("Error retrieving fee ledger details: " . $e->getMessage());
}

if (empty($challansToPrint)) {
    die("No fee ledger records found matching your selection.");
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Challans Print Sheet - Indus Grammar School</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Outfit:wght@400;500;600;700;800&display=swap');

        @page {
            size: A4 landscape;
            margin: 4mm 5mm;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #e2e8f0;
            color: #0f172a;
            margin: 0;
            padding: 15px 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .no-print-toolbar {
            max-width: 290mm;
            margin: 0 auto 15px auto;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .challan-page {
            width: 287mm;
            min-height: 198mm;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 5mm;
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
            border-radius: 4px;
            box-sizing: border-box;
            page-break-after: always;
            position: relative;
        }

        .challan-grid {
            display: flex;
            width: 100%;
            gap: 2.5mm;
        }

        .challan-col {
            flex: 1;
            border: 1.5px solid #1e293b;
            padding: 5px;
            background: #ffffff;
            position: relative;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .cut-line {
            width: 1px;
            border-right: 1.5px dashed #94a3b8;
            position: relative;
        }

        /* Header Layout */
        .school-header-table {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            margin-bottom: 4px;
            padding-bottom: 3px;
        }

        .school-logo-img {
            max-height: 38px;
            max-width: 50px;
            object-fit: contain;
        }

        .school-header-title {
            font-family: 'Cinzel', serif;
            font-size: 0.85rem;
            font-weight: 700;
            color: #881337;
            line-height: 1.1;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .school-header-sub {
            font-size: 0.62rem;
            font-weight: 700;
            color: #1e3a8a;
            line-height: 1.1;
            margin: 1px 0 0 0;
        }

        .school-header-acct {
            font-size: 0.56rem;
            font-weight: 600;
            color: #334155;
            margin: 0;
        }

        .copy-tag {
            border: 1.5px solid #0f172a;
            background: #f8fafc;
            font-weight: 800;
            font-size: 0.6rem;
            padding: 2px 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            color: #0f172a;
            text-align: center;
        }

        .challan-no-badge {
            font-size: 0.62rem;
            font-weight: 800;
            color: #b91c1c;
            margin-top: 2px;
        }

        /* Date & Bank Grid */
        .bank-info-table {
            width: 100%;
            border: 1px solid #1e293b;
            margin-bottom: 4px;
            border-collapse: collapse;
        }

        .bank-info-table td, .bank-info-table th {
            border: 1px solid #334155;
            padding: 2px 4px;
            font-size: 0.6rem;
        }

        .date-box-grid {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1px;
        }

        .date-cell {
            border: 1px solid #475569;
            width: 13px;
            height: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.55rem;
            font-weight: 700;
            background: #fff;
        }

        /* Student Grid */
        .student-info-table {
            width: 100%;
            border: 1px solid #1e293b;
            margin-bottom: 4px;
            border-collapse: collapse;
            background: #fdfbf7;
        }

        .student-info-table td {
            border: 1px solid #cbd5e1;
            padding: 2px 4px;
            font-size: 0.62rem;
            vertical-align: middle;
        }

        .student-info-table td.lbl {
            font-weight: 700;
            color: #0f172a;
            background: #f1f5f9;
            width: 28%;
        }

        .student-info-table td.val {
            font-weight: 600;
            color: #1e293b;
        }

        /* Particulars Breakdown Table */
        .particulars-table {
            width: 100%;
            border: 1px solid #1e293b;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .particulars-table th {
            border: 1px solid #1e293b;
            background: #0f172a;
            color: #ffffff;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 3px 4px;
            text-transform: uppercase;
        }

        .particulars-table td {
            border: 1px solid #cbd5e1;
            padding: 2.5px 4px;
            font-size: 0.62rem;
            color: #0f172a;
        }

        .particulars-table tr.total-row td {
            border-top: 2px solid #0f172a;
            border-bottom: 2px solid #0f172a;
            background: #f8fafc;
            font-weight: 800;
            font-size: 0.68rem;
        }

        /* Rupees in Words */
        .words-box {
            border: 1px solid #1e293b;
            background: #fffdf5;
            padding: 2px 5px;
            font-size: 0.58rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
            line-height: 1.2;
        }

        /* Instructions & Accounts */
        .instructions-box {
            border: 1px solid #334155;
            background: #f8fafc;
            padding: 3px 5px;
            font-size: 0.55rem;
            line-height: 1.25;
            color: #334155;
            margin-bottom: 6px;
        }

        .instructions-box strong {
            color: #0f172a;
        }

        /* Signatures */
        .sig-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 12px;
            padding: 0 4px;
        }

        .sig-block {
            text-align: center;
            width: 45%;
            border-top: 1px solid #475569;
            padding-top: 2px;
            font-size: 0.58rem;
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

            .challan-page {
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
            <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Official Fee Ledger Challans
        </h5>
        <span class="text-muted small">Format: 3-Copy Bank Challan Sheet (A4 Landscape)</span>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary fw-bold px-4 shadow-sm">
            <i class="fa-solid fa-print me-2"></i>Print All Challans
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3">
            Close Window
        </button>
    </div>
</div>

<?php foreach ($challansToPrint as $ch): 
    $totalPayable = (float)$ch['total_payable'];
    $wordsText    = convertNumberToWords($totalPayable);
    $dueDateFormatted = date('d-M-Y', strtotime($ch['due_date']));
    $dueDateParts = explode('-', date('d-m-Y', strtotime($ch['due_date']))); // [DD, MM, YYYY]
    
    // Fee particulars list (Formatted like official Government/School Bank Challan table)
    $particulars = [
        ['code' => '003901', 'name' => 'Tuition Fee',                'amount' => (float)($ch['tuition_fee'] ?? 0)],
        ['code' => '003902', 'name' => 'Admission Fee',              'amount' => (float)($ch['admission_fee'] ?? 0)],
        ['code' => '003903', 'name' => 'Computer / IT Fee',          'amount' => (float)($ch['computer_fee'] ?? 0)],
        ['code' => '003904', 'name' => 'Examination Fee',            'amount' => (float)($ch['exam_fee'] ?? 0)],
        ['code' => '003905', 'name' => 'Transport Fee',              'amount' => (float)($ch['transport_fee'] ?? 0)],
        ['code' => '003906', 'name' => 'Annual Charges / Building',  'amount' => (float)($ch['annual_charges'] ?? 0)],
        ['code' => '003907', 'name' => 'Security Deposit',           'amount' => (float)($ch['security_deposit'] ?? 0)],
        ['code' => '003908', 'name' => 'Fine / Other Charges',       'amount' => (float)(($ch['other_charges'] ?? 0) + ($ch['fine_amount'] ?? 0))]
    ];

    $discountAmt = (float)($ch['discount_amount'] ?? 0);
    $copies = [
        ['title' => 'BANK COPY',    'sub' => 'BANK PORTION'],
        ['title' => 'SCHOOL COPY',  'sub' => 'SCHOOL PORTION'],
        ['title' => 'STUDENT COPY', 'sub' => 'STUDENT PORTION']
    ];
?>
    <div class="challan-page">
        <div class="challan-grid">
            <?php foreach ($copies as $index => $cp): ?>
                <?php if ($index > 0): ?>
                    <div class="cut-line"></div>
                <?php endif; ?>

                <div class="challan-col">
                    <div>
                        <!-- Header Section with School Logo -->
                        <table class="school-header-table">
                            <tr>
                                <td width="50" vertical-align="middle" class="pe-1">
                                    <?php if (!empty($schoolLogoUrl)): ?>
                                        <img src="<?php echo $schoolLogoUrl; ?>" alt="Logo" class="school-logo-img">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px; font-size: 0.7rem;">IGS</div>
                                    <?php endif; ?>
                                </td>
                                <td vertical-align="middle">
                                    <h1 class="school-header-title">INDUS GRAMMAR SCHOOL</h1>
                                    <div class="school-header-sub">MAIN CAMPUS, LAHORE</div>
                                    <div class="school-header-acct">MEEZAN BANK A/C: PK64 MEZN 0001 0203 0405 0607</div>
                                </td>
                                <td width="75" text-align="right" vertical-align="top" class="text-end ps-1">
                                    <div class="copy-tag"><?php echo $cp['title']; ?></div>
                                    <div class="challan-no-badge">#<?php echo str_pad($ch['id'], 5, '0', STR_PAD_LEFT); ?></div>
                                </td>
                            </tr>
                        </table>

                        <!-- Bank & Date Info Row -->
                        <table class="bank-info-table">
                            <tr>
                                <td colspan="2" class="fw-bold text-uppercase bg-light" style="font-size:0.58rem;">
                                    Bank: <span class="text-primary">Meezan Bank Ltd</span>
                                </td>
                                <td class="text-end fw-bold text-danger" style="font-size:0.58rem;">
                                    DUE DATE: <?php echo $dueDateFormatted; ?>
                                </td>
                            </tr>
                            <tr>
                                <td width="40%" class="fw-semibold">DEPOSIT DATE</td>
                                <td width="30%" class="text-center">
                                    <div class="date-box-grid">
                                        <span class="date-cell">D</span>
                                        <span class="date-cell">D</span>
                                        <span class="date-cell">M</span>
                                        <span class="date-cell">M</span>
                                    </div>
                                </td>
                                <td width="30%" class="text-center">
                                    <div class="date-box-grid">
                                        <span class="date-cell">2</span>
                                        <span class="date-cell">0</span>
                                        <span class="date-cell">2</span>
                                        <span class="date-cell">6</span>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <!-- Student Details Grid -->
                        <table class="student-info-table">
                            <tr>
                                <td class="lbl">Roll / Adm No :</td>
                                <td class="val font-monospace fw-bold"><?php echo sanitize($ch['admission_no']); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">Student Name :</td>
                                <td class="val text-uppercase fw-bold"><?php echo sanitize($ch['first_name'] . ' ' . $ch['last_name']); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">Father Name :</td>
                                <td class="val text-uppercase"><?php echo sanitize($ch['father_name'] ?: 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">Class & Sec :</td>
                                <td class="val"><?php echo sanitize(($ch['class_name'] ?? 'Class') . ' - ' . ($ch['section'] ?? 'A')); ?> (<?php echo sanitize($ch['academic_type'] ?? 'School'); ?>)</td>
                            </tr>
                            <tr>
                                <td class="lbl">Fee Month :</td>
                                <td class="val fw-bold text-primary"><?php echo sanitize($ch['month']); ?></td>
                            </tr>
                        </table>

                        <!-- Classification Breakdown Table -->
                        <table class="particulars-table">
                            <thead>
                                <tr>
                                    <th width="30" class="text-center">S.N</th>
                                    <th width="45">G.L A/C</th>
                                    <th>DESCRIPTION / PARTICULARS</th>
                                    <th width="70" class="text-end">AMOUNT (RS.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($particulars as $idx => $p): ?>
                                    <tr>
                                        <td class="text-center text-muted"><?php echo $idx + 1; ?></td>
                                        <td class="font-monospace text-muted" style="font-size:0.55rem;"><?php echo $p['code']; ?></td>
                                        <td><?php echo $p['name']; ?></td>
                                        <td class="text-end font-monospace">
                                            <?php echo ($p['amount'] > 0) ? number_format($p['amount'], 2) : '—'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if ($discountAmt > 0): ?>
                                    <tr>
                                        <td class="text-center text-success"><i class="fa-solid fa-tag"></i></td>
                                        <td class="font-monospace text-success" style="font-size:0.55rem;">DISC</td>
                                        <td class="text-success fw-bold">Applied Concession / Discount</td>
                                        <td class="text-end font-monospace text-success fw-bold">-<?php echo number_format($discountAmt, 2); ?></td>
                                    </tr>
                                <?php endif; ?>

                                <tr class="total-row">
                                    <td colspan="3" class="text-uppercase text-dark">TOTAL PAYABLE AMOUNT</td>
                                    <td class="text-end font-monospace text-dark">Rs. <?php echo number_format($totalPayable, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Rupees in Words -->
                        <div class="words-box">
                            RUPEES: <span class="text-uppercase"><?php echo sanitize($wordsText); ?></span>
                        </div>

                        <!-- Instructions & Mobile Accounts -->
                        <div class="instructions-box">
                            <strong>Payment Instructions & Mobile Wallet Accounts:</strong><br>
                            &bull; <strong>Bank Deposit:</strong> Meezan Bank Ltd A/C: 0001020304050607<br>
                            &bull; <strong>JazzCash / EasyPaisa:</strong> 0306-6544806<br>
                            &bull; Late payments attract penalty fine. Keep safe as official deposit proof.
                        </div>
                    </div>

                    <!-- Signatures -->
                    <div class="sig-container">
                        <div class="sig-block">
                            Depositor Signature
                        </div>
                        <div class="sig-block">
                            Bank Cashier & Stamp
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

</body>
</html>
