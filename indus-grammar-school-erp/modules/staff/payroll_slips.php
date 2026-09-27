<?php
/**
 * Indus Grammar School ERP - Salary Slip Voucher
 * Version 4.0.0 (Premium Redesign)
 */

require_once __DIR__ . '/../../config/app.php';

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

$monthText = date('F Y', mktime(0, 0, 0, $slip['month'], 1, $slip['year']));
$slipNo = !empty($slip['slip_number']) ? $slip['slip_number'] : ('SLIP-' . $slip['year'] . str_pad($slip['month'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($slip['staff_id'], 4, '0', STR_PAD_LEFT));

$grossEarnings = (float)$slip['basic_salary'] + (float)$slip['allowances'] + (float)$slip['bonus'];
$totalDeductions = (float)$slip['deductions'] + (float)$slip['advance_salary_deduction'];
$netPayable = (float)$slip['net_salary'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip - <?php echo htmlspecialchars($slipNo); ?></title>
    <!-- Google Fonts (Outfit) -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            padding-bottom: 40px;
        }
        .slip-wrapper {
            max-width: 850px;
            margin: 30px auto;
            background: #ffffff;
            padding: 45px;
            border-radius: 18px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            position: relative;
        }
        .header-logo {
            font-size: 2.5rem;
            color: #1d4ed8;
        }
        .voucher-stamp {
            border: 2px solid #047857;
            color: #047857;
            padding: 4px 14px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 1px;
            display: inline-block;
            text-transform: uppercase;
        }
        .voucher-stamp-pending {
            border: 2px solid #b45309;
            color: #b45309;
            padding: 4px 14px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 1px;
            display: inline-block;
            text-transform: uppercase;
        }
        .net-summary-box {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border-radius: 14px;
            padding: 1.25rem 1.75rem;
        }
        .table-custom-slip th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .sign-line {
            border-top: 1px dashed #cbd5e1;
            padding-top: 12px;
            margin-top: 45px;
        }
        @media print {
            body {
                background-color: #ffffff;
                padding: 0;
            }
            .slip-wrapper {
                margin: 0;
                padding: 20px;
                box-shadow: none;
                border: none;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Top Floating Action Toolbar -->
<div class="container no-print mt-4 text-center">
    <div class="d-inline-flex gap-2 p-2 bg-white rounded-pill shadow-sm border">
        <button class="btn btn-primary rounded-pill px-4" onclick="window.print()">
            <i class="fa-solid fa-print me-2"></i>Print Payslip
        </button>
        <button class="btn btn-outline-secondary rounded-pill px-4" onclick="window.close()">
            <i class="fa-solid fa-xmark me-2"></i>Close Window
        </button>
    </div>
</div>

<div class="slip-wrapper">
    <!-- Header Branding -->
    <div class="row align-items-center mb-4">
        <div class="col-sm-7 d-flex align-items-center gap-3">
            <i class="fa-solid fa-graduation-cap header-logo text-primary"></i>
            <div>
                <h2 class="fw-bold text-dark mb-0 tracking-tight">INDUS GRAMMAR SCHOOL</h2>
                <span class="text-muted small fw-semibold">Payroll & Human Resource Management System</span>
            </div>
        </div>
        <div class="col-sm-5 text-sm-end mt-3 mt-sm-0">
            <h4 class="fw-bold text-primary mb-1">SALARY PAYSLIP</h4>
            <div class="mb-1">
                <?php if ($slip['payment_status'] === 'Paid'): ?>
                    <span class="voucher-stamp"><i class="fa-solid fa-circle-check me-1"></i>PAID VOUCHER</span>
                <?php else: ?>
                    <span class="voucher-stamp-pending"><i class="fa-solid fa-clock me-1"></i>PENDING PAYMENT</span>
                <?php endif; ?>
            </div>
            <div class="small text-muted">Slip Ref: <strong class="text-dark"><?php echo htmlspecialchars($slipNo); ?></strong></div>
            <div class="small text-muted">Billing Cycle: <strong class="text-dark"><?php echo $monthText; ?></strong></div>
        </div>
    </div>

    <hr class="my-4 text-muted opacity-25">

    <!-- Employee Dossier Information Grid -->
    <h6 class="fw-bold text-secondary mb-3 text-uppercase tracking-wider small">
        <i class="fa-solid fa-id-card me-2 text-primary"></i>Employee Information & Account Details
    </h6>
    <div class="row g-3 mb-4 bg-light p-3 rounded-3 border" style="font-size: 0.9rem;">
        <div class="col-sm-6">
            <div class="row mb-1">
                <div class="col-5 text-muted">Employee No:</div>
                <div class="col-7 fw-bold text-dark"><code><?php echo htmlspecialchars($slip['employee_no']); ?></code></div>
            </div>
            <div class="row mb-1">
                <div class="col-5 text-muted">Staff Name:</div>
                <div class="col-7 fw-bold text-dark"><?php echo htmlspecialchars($slip['first_name'] . ' ' . $slip['last_name']); ?></div>
            </div>
            <div class="row mb-1">
                <div class="col-5 text-muted">Department:</div>
                <div class="col-7 text-dark fw-semibold"><?php echo htmlspecialchars($slip['department']); ?></div>
            </div>
            <div class="row mb-1">
                <div class="col-5 text-muted">Designation:</div>
                <div class="col-7 text-dark"><?php echo htmlspecialchars($slip['designation']); ?></div>
            </div>
            <div class="row">
                <div class="col-5 text-muted">Date of Joining:</div>
                <div class="col-7 text-dark"><?php echo !empty($slip['date_of_joining']) ? date('d-M-Y', strtotime($slip['date_of_joining'])) : '—'; ?></div>
            </div>
        </div>

        <div class="col-sm-6 border-start ps-sm-4">
            <div class="row mb-1">
                <div class="col-5 text-muted">Disbursal Date:</div>
                <div class="col-7 fw-bold text-dark"><?php echo $slip['payment_date'] ? date('d-M-Y', strtotime($slip['payment_date'])) : 'Pending'; ?></div>
            </div>
            <div class="row mb-1">
                <div class="col-5 text-muted">Payment Channel:</div>
                <div class="col-7 text-dark fw-semibold"><?php echo htmlspecialchars($slip['payment_method'] ?? $slip['setup_method'] ?? 'Bank Transfer'); ?></div>
            </div>
            <div class="row mb-1">
                <div class="col-5 text-muted">Bank Name:</div>
                <div class="col-7 text-dark"><?php echo htmlspecialchars($slip['bank_name'] ?? '—'); ?></div>
            </div>
            <div class="row mb-1">
                <div class="col-5 text-muted">Account / IBAN:</div>
                <div class="col-7 text-dark"><code><?php echo htmlspecialchars($slip['account_number'] ?? '—'); ?></code></div>
            </div>
            <div class="row">
                <div class="col-5 text-muted">Contact Phone:</div>
                <div class="col-7 text-dark"><?php echo htmlspecialchars($slip['phone'] ?? '—'); ?></div>
            </div>
        </div>
    </div>

    <!-- Attendance Breakdown Summary -->
    <h6 class="fw-bold text-secondary mb-3 text-uppercase tracking-wider small">
        <i class="fa-solid fa-calendar-check me-2 text-primary"></i>Shift Attendance Summary (<?php echo $monthText; ?>)
    </h6>
    <div class="row g-2 mb-4 text-center" style="font-size: 0.85rem;">
        <div class="col-4 col-sm-2">
            <div class="border p-2 rounded-3 bg-light">
                <div class="text-muted mb-1 small fw-semibold">Working Days</div>
                <strong class="text-dark fs-6"><?php echo $slip['working_days']; ?></strong>
            </div>
        </div>
        <div class="col-4 col-sm-2">
            <div class="border p-2 rounded-3 bg-success bg-opacity-10">
                <div class="text-success mb-1 small fw-semibold">Present</div>
                <strong class="text-success fs-6"><?php echo $slip['present_days']; ?></strong>
            </div>
        </div>
        <div class="col-4 col-sm-2">
            <div class="border p-2 rounded-3 bg-danger bg-opacity-10">
                <div class="text-danger mb-1 small fw-semibold">Absences</div>
                <strong class="text-danger fs-6"><?php echo $slip['absent_days']; ?></strong>
            </div>
        </div>
        <div class="col-4 col-sm-2">
            <div class="border p-2 rounded-3 bg-warning bg-opacity-10">
                <div class="text-warning mb-1 small fw-semibold">Late Arrivals</div>
                <strong class="text-warning fs-6"><?php echo $slip['late_days']; ?></strong>
            </div>
        </div>
        <div class="col-4 col-sm-2">
            <div class="border p-2 rounded-3 bg-light">
                <div class="text-muted mb-1 small fw-semibold">On Leave</div>
                <strong class="text-dark fs-6"><?php echo $slip['leave_days']; ?></strong>
            </div>
        </div>
        <div class="col-4 col-sm-2">
            <div class="border p-2 rounded-3 bg-light">
                <div class="text-muted mb-1 small fw-semibold">Half Days</div>
                <strong class="text-dark fs-6"><?php echo $slip['half_days']; ?></strong>
            </div>
        </div>
    </div>

    <!-- Earnings & Deductions Breakdown Tables -->
    <div class="row g-4 mb-4">
        <!-- Earnings -->
        <div class="col-sm-6">
            <div class="border rounded-3 p-3 h-100">
                <h6 class="fw-bold text-success mb-3 border-bottom pb-2">
                    <i class="fa-solid fa-circle-plus me-2"></i>Earnings & Benefits
                </h6>
                <table class="table table-sm table-custom-slip align-middle mb-0" style="font-size:0.88rem;">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Basic Base Salary</td>
                            <td class="text-end fw-semibold text-dark">Rs. <?php echo number_format($slip['basic_salary'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Allowances (HRA, Med, Conveyance)</td>
                            <td class="text-end text-success fw-semibold">+Rs. <?php echo number_format($slip['allowances'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Bonuses & Performance Rewards</td>
                            <td class="text-end text-success fw-semibold">+Rs. <?php echo number_format($slip['bonus'], 2); ?></td>
                        </tr>
                        <tr class="fw-bold bg-light border-top">
                            <td>Gross Earnings Total</td>
                            <td class="text-end text-success fs-6">Rs. <?php echo number_format($grossEarnings, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Deductions -->
        <div class="col-sm-6">
            <div class="border rounded-3 p-3 h-100">
                <h6 class="fw-bold text-danger mb-3 border-bottom pb-2">
                    <i class="fa-solid fa-circle-minus me-2"></i>Deductions & Recoveries
                </h6>
                <table class="table table-sm table-custom-slip align-middle mb-0" style="font-size:0.88rem;">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Withholdings (Tax, PF, EOBI) & Fines</td>
                            <td class="text-end text-danger fw-semibold">-Rs. <?php echo number_format($slip['deductions'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Advance Loan Installment Recovery</td>
                            <td class="text-end text-purple fw-semibold">-Rs. <?php echo number_format($slip['advance_salary_deduction'], 2); ?></td>
                        </tr>
                        <tr class="fw-bold bg-light border-top">
                            <td>Total Deductions</td>
                            <td class="text-end text-danger fs-6">-Rs. <?php echo number_format($totalDeductions, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Highlighted Net Payable Salary Box -->
    <div class="net-summary-box mb-5 shadow-sm">
        <div class="row align-items-center">
            <div class="col-7">
                <small class="text-white-50 text-uppercase fw-bold tracking-wider d-block mb-1">NET PAYABLE SALARY</small>
                <div class="small text-white-50">Calculated automatically from registered basic rate, allowances, fines, and loan recoveries.</div>
            </div>
            <div class="col-5 text-end">
                <h2 class="fw-bold text-white mb-0">Rs. <?php echo number_format($netPayable, 2); ?></h2>
            </div>
        </div>
    </div>

    <!-- Official Signatures & Seal -->
    <div class="row sign-line text-center" style="font-size:0.85rem;">
        <div class="col-4">
            <div class="text-muted mb-4">Prepared By:</div>
            <div class="fw-bold text-dark border-top pt-2 mx-auto" style="width:75%;"><?php echo htmlspecialchars(ucfirst($slip['prep_name'] ?? 'Accountant Office')); ?></div>
            <small class="text-muted">Accounts Department</small>
        </div>
        <div class="col-4">
            <div class="text-muted mb-4">Employee Signature:</div>
            <div class="fw-bold text-dark border-top pt-2 mx-auto" style="width:75%;"><?php echo htmlspecialchars($slip['first_name'] . ' ' . $slip['last_name']); ?></div>
            <small class="text-muted">Recipient Acknowledgment</small>
        </div>
        <div class="col-4">
            <div class="text-muted mb-4">Approved By:</div>
            <div class="fw-bold text-dark border-top pt-2 mx-auto" style="width:75%;"><?php echo htmlspecialchars(ucfirst($slip['app_name'] ?? 'Principal / Management')); ?></div>
            <small class="text-muted">Authorized School Seal</small>
        </div>
    </div>

    <div class="mt-4 pt-3 border-top text-center text-muted" style="font-size:0.75rem;">
        This is an official computer-generated salary voucher produced by <strong>Indus Grammar School ERP</strong>.
    </div>
</div>

</body>
</html>
