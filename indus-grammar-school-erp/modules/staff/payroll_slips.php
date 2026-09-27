<?php
/**
 * Indus Grammar School ERP - Executive Salary Slip Voucher
 * Version 5.0.0 (Commercial ERP Redesign with School Logo & Premium Styling)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../helpers/helpers.php';

// Access Control
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'accountant'])) {
    die("Access denied. You do not have permission to view salary slips.");
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid salary slip record selected.");
}

$db = Database::getConnection();

// Fetch salary details, staff profile, setup config, and voucher references
$slip = null;
try {
    $stmt = $db->prepare("
        SELECT d.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department, s.phone, s.email, s.date_of_joining,
               p.month, p.year,
               ss.bank_name, ss.account_number, ss.payment_method as setup_method,
               sl.slip_number,
               u1.username as prep_name, u2.username as app_name
        FROM salary_details d
        JOIN salary_processing p ON d.processing_id = p.id
        JOIN staff s ON d.staff_id = s.id
        LEFT JOIN salary_setup ss ON s.id = ss.staff_id
        LEFT JOIN salary_slips sl ON d.id = sl.salary_detail_id
        LEFT JOIN users u1 ON sl.prepared_by = u1.id
        LEFT JOIN users u2 ON sl.approved_by = u2.id
        WHERE d.id = ?
    ");
    $stmt->execute([$id]);
    $slip = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Database error loading slip: " . $e->getMessage());
}

if (!$slip) {
    die("Salary slip record not found.");
}

// Fetch school settings info
$schoolInfo = [];
try {
    $schoolInfo = $db->query("SELECT * FROM school_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$schoolName    = !empty($schoolInfo['school_name']) ? $schoolInfo['school_name'] : 'INDUS GRAMMAR SCHOOL';
$schoolAddress = !empty($schoolInfo['school_address']) ? $schoolInfo['school_address'] : 'Main Campus, Educational Complex';
$schoolPhone   = !empty($schoolInfo['phone_number']) ? $schoolInfo['phone_number'] : '+92 300 1234567';
$schoolEmail   = !empty($schoolInfo['email']) ? $schoolInfo['email'] : 'info@indusgrammarschool.edu.pk';
$schoolLogoUrl = getSchoolLogoUrl();
if (empty($schoolLogoUrl)) {
    $schoolLogoUrl = APP_URL . '/assets/images/logo.svg';
}

$monthText = date('F Y', mktime(0, 0, 0, $slip['month'], 1, $slip['year']));
$slipNo = !empty($slip['slip_number']) ? $slip['slip_number'] : ('SLIP-' . $slip['year'] . str_pad($slip['month'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($slip['staff_id'], 4, '0', STR_PAD_LEFT));

$grossEarnings   = (float)$slip['basic_salary'] + (float)$slip['allowances'] + (float)$slip['bonus'];
$totalDeductions = (float)$slip['deductions'] + (float)$slip['advance_salary_deduction'];
$netPayable      = (float)$slip['net_salary'];

// Number to Words Converter
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
$amountWords = convertNumberToWords($netPayable);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip - <?php echo htmlspecialchars($slipNo); ?></title>
    <!-- Google Fonts (Outfit & Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --primary-emerald: #059669;
            --accent-gold: #d97706;
            --text-dark: #0f172a;
            --bg-page: #f1f5f9;
        }

        body {
            font-family: 'Outfit', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-dark);
            padding-bottom: 50px;
            -webkit-font-smoothing: antialiased;
        }

        /* Executive Payslip Container */
        .slip-wrapper {
            max-width: 900px;
            margin: 35px auto;
            background: #ffffff;
            padding: 45px 50px;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.12);
            border: 1px solid #e2e8f0;
            position: relative;
        }

        /* School Branding Header */
        .school-branding-box {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .school-logo-img {
            height: 75px;
            width: auto;
            max-width: 110px;
            object-fit: contain;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.08));
        }

        .school-name-heading {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
            line-height: 1.15;
            margin-bottom: 2px;
        }

        .school-subtext {
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Voucher Ref Status Badge */
        .voucher-title-tag {
            font-size: 1.35rem;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: 0.5px;
        }

        .voucher-stamp-paid {
            background-color: #d1fae5;
            color: #047857;
            border: 1.5px solid #059669;
            padding: 5px 16px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.82rem;
            letter-spacing: 0.8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
        }

        .voucher-stamp-pending {
            background-color: #fef3c7;
            color: #b45309;
            border: 1.5px solid #d97706;
            padding: 5px 16px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.82rem;
            letter-spacing: 0.8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
        }

        /* Employee Profile Card Grid */
        .emp-profile-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
        }

        .info-label {
            color: #64748b;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .info-value {
            color: #0f172a;
            font-size: 0.92rem;
            font-weight: 700;
        }

        /* Attendance Metric Mini Badges */
        .att-stat-box {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.65rem;
            background: #ffffff;
            transition: transform 0.15s ease;
        }

        .att-stat-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .att-stat-num {
            font-size: 1.15rem;
            font-weight: 800;
        }

        /* Earnings & Deductions Tables */
        .table-slip {
            margin-bottom: 0;
        }

        .table-slip th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.65rem 0.85rem;
            border-bottom: 2px solid #cbd5e1;
        }

        .table-slip td {
            padding: 0.65rem 0.85rem;
            font-size: 0.88rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        /* Net Salary Highlight Box */
        .net-payable-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #047857 100%);
            color: #ffffff;
            border-radius: 16px;
            padding: 1.5rem 2rem;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2);
            position: relative;
            overflow: hidden;
        }

        .net-payable-hero::after {
            content: '';
            position: absolute;
            right: -30px;
            bottom: -30px;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.06);
            pointer-events: none;
        }

        /* Signature Section */
        .sign-area {
            border-top: 1.5px dashed #cbd5e1;
            padding-top: 2rem;
            margin-top: 2.5rem;
        }

        .sign-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 2.5rem;
        }

        .sign-name {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
            border-top: 1px solid #94a3b8;
            padding-top: 6px;
            width: 80%;
            margin: 0 auto;
        }

        /* Print Mode Formatting */
        @media print {
            body {
                background-color: #ffffff;
                padding: 0;
            }
            .slip-wrapper {
                margin: 0;
                padding: 30px;
                box-shadow: none;
                border: none;
                max-width: 100%;
                border-radius: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Top Floating Action Toolbar (Screen Only) -->
<div class="container no-print mt-4 text-center">
    <div class="d-inline-flex gap-2 p-2 bg-white rounded-pill shadow-sm border">
        <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="window.print()">
            <i class="fa-solid fa-print me-2"></i>Print Salary Voucher
        </button>
        <button class="btn btn-outline-secondary rounded-pill px-4" onclick="window.close()">
            <i class="fa-solid fa-xmark me-2"></i>Close Window
        </button>
    </div>
</div>

<div class="slip-wrapper">
    <!-- Header Branding Bar -->
    <div class="row align-items-center mb-4">
        <div class="col-sm-7">
            <div class="school-branding-box">
                <img src="<?php echo htmlspecialchars($schoolLogoUrl); ?>" alt="School Logo" class="school-logo-img" onerror="this.onerror=null; this.src='https://placehold.co/100x100/1e3a8a/ffffff?text=IGS';">
                <div>
                    <h1 class="school-name-heading"><?php echo htmlspecialchars($schoolName); ?></h1>
                    <div class="school-subtext"><i class="fa-solid fa-building-columns me-1 text-primary"></i><?php echo htmlspecialchars($schoolAddress); ?></div>
                    <div class="school-subtext text-muted" style="font-size: 0.78rem;">
                        <i class="fa-solid fa-phone me-1"></i><?php echo htmlspecialchars($schoolPhone); ?> &nbsp;|&nbsp; 
                        <i class="fa-solid fa-envelope me-1"></i><?php echo htmlspecialchars($schoolEmail); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-5 text-sm-end mt-3 mt-sm-0">
            <div class="voucher-title-tag">SALARY PAYSLIP</div>
            <div class="my-1">
                <?php if ($slip['payment_status'] === 'Paid'): ?>
                    <span class="voucher-stamp-paid"><i class="fa-solid fa-circle-check"></i> OFFICIAL PAID VOUCHER</span>
                <?php else: ?>
                    <span class="voucher-stamp-pending"><i class="fa-solid fa-clock"></i> PENDING DISBURSAL</span>
                <?php endif; ?>
            </div>
            <div class="small text-muted">Voucher Ref: <strong class="text-dark font-monospace"><?php echo htmlspecialchars($slipNo); ?></strong></div>
            <div class="small text-muted">Pay Cycle: <strong class="text-dark fw-bold"><?php echo $monthText; ?></strong></div>
        </div>
    </div>

    <hr class="my-3 text-muted opacity-25">

    <!-- Employee & Account Dossier Information -->
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-bold text-primary mb-0 text-uppercase tracking-wider small">
            <i class="fa-solid fa-id-card me-2"></i>Employee & Payment Account Information
        </h6>
        <span class="badge bg-light text-muted border">System Employee Record</span>
    </div>

    <div class="emp-profile-card mb-4">
        <div class="row g-3">
            <div class="col-sm-6">
                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom border-light">
                    <span class="info-label"><i class="fa-solid fa-fingerprint me-1 text-primary"></i>Employee Code:</span>
                    <span class="info-value text-primary font-monospace"><?php echo htmlspecialchars($slip['employee_no']); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom border-light">
                    <span class="info-label"><i class="fa-solid fa-user me-1 text-primary"></i>Employee Name:</span>
                    <span class="info-value text-dark"><?php echo htmlspecialchars($slip['first_name'] . ' ' . $slip['last_name']); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom border-light">
                    <span class="info-label"><i class="fa-solid fa-sitemap me-1 text-primary"></i>Department:</span>
                    <span class="info-value text-dark"><?php echo htmlspecialchars($slip['department']); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom border-light">
                    <span class="info-label"><i class="fa-solid fa-user-tag me-1 text-primary"></i>Designation:</span>
                    <span class="info-value text-dark"><?php echo htmlspecialchars($slip['designation']); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="info-label"><i class="fa-solid fa-calendar-day me-1 text-primary"></i>Date of Joining:</span>
                    <span class="info-value text-muted"><?php echo !empty($slip['date_of_joining']) ? date('d-M-Y', strtotime($slip['date_of_joining'])) : '—'; ?></span>
                </div>
            </div>

            <div class="col-sm-6 border-start ps-sm-4">
                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom border-light">
                    <span class="info-label"><i class="fa-solid fa-calendar-check me-1 text-success"></i>Disbursal Date:</span>
                    <span class="info-value text-success"><?php echo $slip['payment_date'] ? date('d-M-Y', strtotime($slip['payment_date'])) : 'Pending'; ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom border-light">
                    <span class="info-label"><i class="fa-solid fa-money-bill-transfer me-1 text-primary"></i>Payment Channel:</span>
                    <span class="info-value text-dark"><?php echo htmlspecialchars($slip['payment_method'] ?? $slip['setup_method'] ?? 'Bank Transfer'); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom border-light">
                    <span class="info-label"><i class="fa-solid fa-building-columns me-1 text-primary"></i>Bank Name:</span>
                    <span class="info-value text-dark"><?php echo htmlspecialchars($slip['bank_name'] ?? '—'); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom border-light">
                    <span class="info-label"><i class="fa-solid fa-hashtag me-1 text-primary"></i>Account / IBAN:</span>
                    <span class="info-value text-dark font-monospace"><?php echo htmlspecialchars($slip['account_number'] ?? '—'); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="info-label"><i class="fa-solid fa-phone me-1 text-primary"></i>Mobile Phone:</span>
                    <span class="info-value text-muted"><?php echo htmlspecialchars($slip['phone'] ?? '—'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Breakdown Summary -->
    <h6 class="fw-bold text-primary mb-2 text-uppercase tracking-wider small">
        <i class="fa-solid fa-chart-pie me-2"></i>Shift Attendance & Duty Ledger (<?php echo $monthText; ?>)
    </h6>
    <div class="row g-2 mb-4 text-center">
        <div class="col-4 col-sm-2">
            <div class="att-stat-box">
                <div class="att-stat-label text-secondary">Working Days</div>
                <div class="att-stat-num text-dark"><?php echo $slip['working_days']; ?></div>
            </div>
        </div>
        <div class="col-4 col-sm-2">
            <div class="att-stat-box bg-success-subtle border-success-subtle">
                <div class="att-stat-label text-success">Present</div>
                <div class="att-stat-num text-success"><?php echo $slip['present_days']; ?></div>
            </div>
        </div>
        <div class="col-4 col-sm-2">
            <div class="att-stat-box bg-danger-subtle border-danger-subtle">
                <div class="att-stat-label text-danger">Absences</div>
                <div class="att-stat-num text-danger"><?php echo $slip['absent_days']; ?></div>
            </div>
        </div>
        <div class="col-4 col-sm-2">
            <div class="att-stat-box bg-warning-subtle border-warning-subtle">
                <div class="att-stat-label text-warning">Late Check-Ins</div>
                <div class="att-stat-num text-warning"><?php echo $slip['late_days']; ?></div>
            </div>
        </div>
        <div class="col-4 col-sm-2">
            <div class="att-stat-box">
                <div class="att-stat-label text-info">Approved Leave</div>
                <div class="att-stat-num text-info"><?php echo $slip['leave_days']; ?></div>
            </div>
        </div>
        <div class="col-4 col-sm-2">
            <div class="att-stat-box">
                <div class="att-stat-label text-secondary">Half Days</div>
                <div class="att-stat-num text-dark"><?php echo $slip['half_days']; ?></div>
            </div>
        </div>
    </div>

    <!-- Earnings & Deductions Itemized Breakdown -->
    <div class="row g-4 mb-4">
        <!-- Earnings Column -->
        <div class="col-sm-6">
            <div class="border rounded-3 p-3 h-100 bg-white">
                <h6 class="fw-bold text-success mb-3 border-bottom pb-2 d-flex align-items-center justify-content-between">
                    <span><i class="fa-solid fa-circle-plus me-2"></i>Earnings & Allowances</span>
                    <span class="badge bg-success-subtle text-success small">Credit</span>
                </h6>
                <table class="table table-sm table-slip align-middle">
                    <thead>
                        <tr>
                            <th>Earning Item</th>
                            <th class="text-end">Amount (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Basic Salary</td>
                            <td class="text-end fw-semibold text-dark">Rs. <?php echo number_format($slip['basic_salary'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Allowances (HRA, Med, Conveyance)</td>
                            <td class="text-end text-success fw-semibold">+ Rs. <?php echo number_format($slip['allowances'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Bonuses & Rewards</td>
                            <td class="text-end text-success fw-semibold">+ Rs. <?php echo number_format($slip['bonus'], 2); ?></td>
                        </tr>
                        <tr class="fw-bold bg-light">
                            <td class="text-dark">Gross Earnings Total</td>
                            <td class="text-end text-success fs-6">Rs. <?php echo number_format($grossEarnings, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Deductions Column -->
        <div class="col-sm-6">
            <div class="border rounded-3 p-3 h-100 bg-white">
                <h6 class="fw-bold text-danger mb-3 border-bottom pb-2 d-flex align-items-center justify-content-between">
                    <span><i class="fa-solid fa-circle-minus me-2"></i>Deductions & Recoveries</span>
                    <span class="badge bg-danger-subtle text-danger small">Debit</span>
                </h6>
                <table class="table table-sm table-slip align-middle">
                    <thead>
                        <tr>
                            <th>Deduction Item</th>
                            <th class="text-end">Amount (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Withholdings (Tax, PF, EOBI) & Fines</td>
                            <td class="text-end text-danger fw-semibold">- Rs. <?php echo number_format($slip['deductions'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Advance Loan Installment</td>
                            <td class="text-end text-purple fw-semibold">- Rs. <?php echo number_format($slip['advance_salary_deduction'], 2); ?></td>
                        </tr>
                        <tr class="fw-bold bg-light">
                            <td class="text-dark">Total Deductions</td>
                            <td class="text-end text-danger fs-6">- Rs. <?php echo number_format($totalDeductions, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Executive Net Salary Summary Hero Box -->
    <div class="net-payable-hero mb-4">
        <div class="row align-items-center position-relative" style="z-index: 1;">
            <div class="col-md-7">
                <span class="text-white-50 text-uppercase fw-bold tracking-wider small d-block mb-1">NET PAYABLE SALARY AMOUNT</span>
                <div class="fw-semibold text-white fs-5"><?php echo htmlspecialchars($amountWords); ?></div>
            </div>
            <div class="col-md-5 text-md-end mt-2 mt-md-0">
                <div class="display-6 fw-bold text-white mb-0">Rs. <?php echo number_format($netPayable, 2); ?></div>
            </div>
        </div>
    </div>

    <!-- Official Signatures & Verification Area -->
    <div class="row sign-area text-center">
        <div class="col-4">
            <div class="sign-title">Prepared By (Accounts):</div>
            <div class="sign-name"><?php echo htmlspecialchars(ucfirst($slip['prep_name'] ?? 'Finance Officer')); ?></div>
            <small class="text-muted text-xs">Accounts Department</small>
        </div>
        <div class="col-4">
            <div class="sign-title">Employee Acknowledgment:</div>
            <div class="sign-name"><?php echo htmlspecialchars($slip['first_name'] . ' ' . $slip['last_name']); ?></div>
            <small class="text-muted text-xs">Recipient Signature</small>
        </div>
        <div class="col-4">
            <div class="sign-title">Approved & Authorized By:</div>
            <div class="sign-name"><?php echo htmlspecialchars(ucfirst($slip['app_name'] ?? 'Principal / Admin')); ?></div>
            <small class="text-muted text-xs">Official School Seal</small>
        </div>
    </div>

    <div class="mt-4 pt-3 border-top text-center text-muted" style="font-size: 0.75rem;">
        This is an official computer-generated salary voucher produced by <strong>Indus Grammar School ERP System</strong> on <?php echo date('d-M-Y h:i A'); ?>.
    </div>
</div>

</body>
</html>
