<?php
/**
 * Indus Grammar School ERP - Salary Slip Voucher
 * Version 4.0.0
 */

require_once __DIR__ . '/../../config/app.php';

// Restricted to Super Admin, School Admin, and Accountant
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'accountant'])) {
    die("Access denied.");
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid salary record selected.");
}

$db = Database::getConnection();

// Fetch salary details and user references
try {
    $stmt = $db->prepare("
        SELECT d.*, s.first_name, s.last_name, s.employee_no, s.designation, s.department, s.phone, s.email,
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

$monthText = date('F Y', mktime(0,0,0,$slip['month'], 1, $slip['year']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip - <?php echo htmlspecialchars($slip['slip_number'] ?? 'VOUCHER'); ?></title>
    <!-- Google Fonts (Outfit) -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        .slip-container {
            max-width: 800px;
            margin: 30px auto;
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
            border: 1px solid #eaeaea;
        }
        .header-logo {
            font-size: 2.2rem;
            color: #0d6efd;
        }
        .table-summary {
            border: 1px solid #dee2e6;
        }
        .table-summary th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 600;
        }
        .sign-area {
            margin-top: 50px;
            border-top: 1px dashed #ccc;
            padding-top: 15px;
        }
        @media print {
            body {
                background-color: #fff;
            }
            .slip-container {
                margin: 0;
                padding: 0;
                box-shadow: none;
                border: none;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="container no-print mt-4 text-center">
    <button class="btn btn-primary px-4 py-2" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Salary Slip</button>
    <button class="btn btn-outline-secondary px-4 py-2 ms-2" onclick="window.close()"><i class="fa-solid fa-xmark me-2"></i>Close Window</button>
</div>

<div class="slip-container">
    <!-- Header Branding -->
    <div class="row align-items-center mb-4">
        <div class="col-sm-8 d-flex align-items-center gap-3">
            <i class="fa-solid fa-graduation-cap header-logo text-primary"></i>
            <div>
                <h3 class="fw-bold text-dark mb-0">Indus Grammar School</h3>
                <span class="text-muted small">Quality Education for a Brighter Future</span>
            </div>
        </div>
        <div class="col-sm-4 text-sm-end mt-3 mt-sm-0">
            <h5 class="fw-bold text-primary mb-1">SALARY SLIP</h5>
            <small class="text-muted">Slip No: <strong><?php echo htmlspecialchars($slip['slip_number'] ?? 'N/A'); ?></strong></small><br>
            <small class="text-muted">Pay Period: <strong><?php echo $monthText; ?></strong></small>
        </div>
    </div>

    <hr class="mb-4">

    <!-- Employee Information -->
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-user me-2 text-primary"></i>Employee Information</h6>
    <div class="row g-3 mb-4 bg-light p-3 rounded" style="font-size: 0.9rem;">
        <div class="col-sm-6">
            <div class="row mb-1">
                <div class="col-5 text-muted">Employee ID:</div>
                <div class="col-7 fw-bold text-dark"><?php echo htmlspecialchars($slip['employee_no']); ?></div>
            </div>
            <div class="row mb-1">
                <div class="col-5 text-muted">Full Name:</div>
                <div class="col-7 fw-bold text-dark"><?php echo htmlspecialchars($slip['first_name'] . ' ' . $slip['last_name']); ?></div>
            </div>
            <div class="row mb-1">
                <div class="col-5 text-muted">Department:</div>
                <div class="col-7 text-dark"><?php echo htmlspecialchars($slip['department']); ?></div>
            </div>
            <div class="row mb-1">
                <div class="col-5 text-muted">Designation:</div>
                <div class="col-7 text-dark"><?php echo htmlspecialchars($slip['designation']); ?></div>
            </div>
        </div>
        <div class="col-sm-6 border-start ps-sm-4">
            <div class="row mb-1">
                <div class="col-5 text-muted">Payment Date:</div>
                <div class="col-7 text-dark"><?php echo $slip['payment_date'] ? date('d-M-Y', strtotime($slip['payment_date'])) : 'Pending'; ?></div>
            </div>
            <div class="row mb-1">
                <div class="col-5 text-muted">Payment Method:</div>
                <div class="col-7 text-dark"><?php echo htmlspecialchars($slip['payment_method'] ?? '—'); ?></div>
            </div>
            <div class="row mb-1">
                <div class="col-5 text-muted">Bank Name:</div>
                <div class="col-7 text-dark"><?php echo htmlspecialchars($slip['bank_name'] ?? '—'); ?></div>
            </div>
            <div class="row mb-1">
                <div class="col-5 text-muted">Account No:</div>
                <div class="col-7 text-dark"><?php echo htmlspecialchars($slip['account_number'] ?? '—'); ?></div>
            </div>
        </div>
    </div>

    <!-- Attendance Breakdown Summary -->
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-calendar-check me-2 text-primary"></i>Attendance Summary</h6>
    <div class="row g-2 mb-4 text-center" style="font-size: 0.85rem;">
        <div class="col-4 col-sm-2">
            <div class="border p-2 rounded">
                <div class="text-muted mb-1">Working Days</div>
                <strong class="text-dark fs-6"><?php echo $slip['working_days']; ?></strong>
            </div>
        </div>
        <div class="col-4 col-sm-2">
            <div class="border p-2 rounded bg-success-soft">
                <div class="text-success mb-1">Present</div>
                <strong class="text-success fs-6"><?php echo $slip['present_days']; ?></strong>
            </div>
        </div>
        <div class="col-4 col-sm-2">
            <div class="border p-2 rounded bg-danger-soft">
                <div class="text-danger mb-1">Absent</div>
                <strong class="text-danger fs-6"><?php echo $slip['absent_days']; ?></strong>
            </div>
        </div>
        <div class="col-4 col-sm-2">
            <div class="border p-2 rounded bg-warning-soft">
                <div class="text-warning mb-1">Late Arrivals</div>
                <strong class="text-warning fs-6"><?php echo $slip['late_days']; ?></strong>
            </div>
        </div>
        <div class="col-4 col-sm-2">
            <div class="border p-2 rounded">
                <div class="text-muted mb-1">On Leave</div>
                <strong class="text-dark fs-6"><?php echo $slip['leave_days']; ?></strong>
            </div>
        </div>
        <div class="col-4 col-sm-2">
            <div class="border p-2 rounded">
                <div class="text-muted mb-1">Half Days</div>
                <strong class="text-dark fs-6"><?php echo $slip['half_days']; ?></strong>
            </div>
        </div>
    </div>

    <!-- Earnings & Deductions Breakdown -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6">
            <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-circle-plus me-2"></i>Earnings & Allowances</h6>
            <table class="table table-bordered table-sm" style="font-size:0.9rem;">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Basic Base Salary</td>
                        <td class="text-end">Rs. <?php echo number_format($slip['basic_salary'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Allowances (HRA, Med, Trans)</td>
                        <td class="text-end text-success">Rs. <?php echo number_format($slip['allowances'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Bonus & Special Incentives</td>
                        <td class="text-end text-success">Rs. <?php echo number_format($slip['bonus'], 2); ?></td>
                    </tr>
                    <tr class="fw-bold bg-light">
                        <td>Total Earnings (Gross)</td>
                        <td class="text-end text-success">Rs. <?php echo number_format($slip['basic_salary'] + $slip['allowances'] + $slip['bonus'], 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="col-sm-6">
            <h6 class="fw-bold text-danger mb-3"><i class="fa-solid fa-circle-minus me-2"></i>Deductions & Recoveries</h6>
            <table class="table table-bordered table-sm" style="font-size:0.9rem;">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Withholdings (Tax, PF, EOBI) & Fines</td>
                        <td class="text-end text-danger">Rs. <?php echo number_format($slip['deductions'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Advance Salary Recovery</td>
                        <td class="text-end text-danger">Rs. <?php echo number_format($slip['advance_salary_deduction'], 2); ?></td>
                    </tr>
                    <tr class="fw-bold bg-light">
                        <td>Total Deductions</td>
                        <td class="text-end text-danger">Rs. <?php echo number_format($slip['deductions'] + $slip['advance_salary_deduction'], 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Summary Total Paid -->
    <div class="row align-items-center bg-primary text-white p-3 rounded mb-5 mx-0">
        <div class="col-8">
            <h5 class="fw-bold mb-0">NET PAYABLE SALARY</h5>
            <small class="text-light">Computed based on registered attendance values and settings</small>
        </div>
        <div class="col-4 text-end">
            <h3 class="fw-bold mb-0">Rs. <?php echo number_format($slip['net_salary'], 2); ?></h3>
        </div>
    </div>

    <!-- Signatures -->
    <div class="row sign-area text-center" style="font-size:0.85rem;">
        <div class="col-sm-4 offset-sm-1">
            <div class="mb-4 text-muted">Prepared By:</div>
            <div class="fw-bold text-dark border-top pt-2"><?php echo htmlspecialchars(ucfirst($slip['prep_name'] ?? 'Accountant Office')); ?></div>
            <small class="text-muted">Authorized Signature</small>
        </div>
        <div class="col-sm-4 offset-sm-2 mt-4 mt-sm-0">
            <div class="mb-4 text-muted">Approved By:</div>
            <div class="fw-bold text-dark border-top pt-2"><?php echo htmlspecialchars(ucfirst($slip['app_name'] ?? 'Super Admin')); ?></div>
            <small class="text-muted">Management Approval Stamp</small>
        </div>
    </div>
</div>

</body>
</html>
