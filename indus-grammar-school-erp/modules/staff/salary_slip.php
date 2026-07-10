<?php
/**
 * Indus Grammar School ERP - Salary Slip
 * Version 1.0.0
 */

$pageTitle = 'Salary Slip';
$breadcrumbActive = 'HR & Staff';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('hr_view');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Invalid Salary ID.</div></div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$salary = Payroll::findById($id);

if (!$salary) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Salary record not found.</div></div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$monthName = date('F', mktime(0, 0, 0, $salary['month'], 10));
$gross = $salary['basic_salary'] + $salary['allowances'];
?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Salary Slip</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="payroll.php" class="btn btn-outline-secondary px-3 me-2"><i class="fa-solid fa-arrow-left me-2"></i>Back to Payroll</a>
        <button class="btn btn-primary px-4" onclick="window.print()">
            <i class="fa-solid fa-print me-2"></i>Print Slip
        </button>
    </div>
</div>

<div class="card border-0 shadow" style="border-radius:0; border-top: 5px solid var(--primary-color) !important;" id="slipPrintArea">
    <div class="card-body p-5">
        <!-- Header -->
        <div class="row mb-5 border-bottom pb-4 align-items-center">
            <div class="col-8">
                <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h2>
                <p class="text-muted mb-0">Excellence in Education</p>
                <p class="small text-muted mt-1">123 Education Lane, Cityville, Country | Ph: +92-123-4567890</p>
            </div>
            <div class="col-4 text-end">
                <h3 class="fw-bold text-secondary text-uppercase mb-2 border d-inline-block px-4 py-2 bg-light">PAY SLIP</h3>
                <h5 class="fw-semibold text-dark mb-0"><?php echo $monthName . ' ' . $salary['year']; ?></h5>
            </div>
        </div>

        <!-- Staff Info -->
        <div class="row mb-5 g-4">
            <div class="col-sm-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted fw-semibold" width="130">Employee Name:</td><td class="fw-bold fs-5"><?php echo sanitize($salary['first_name'] . ' ' . $salary['last_name']); ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Employee ID:</td><td class="fw-semibold text-dark"><?php echo sanitize($salary['employee_no']); ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Designation:</td><td><?php echo sanitize($salary['designation']); ?></td></tr>
                </table>
            </div>
            <div class="col-sm-6 text-sm-end">
                <table class="table table-borderless table-sm mb-0 ms-auto text-sm-end" style="width: auto;">
                    <tr><td class="text-muted fw-semibold text-start">Department:</td><td class="fw-bold text-end"><?php echo sanitize($salary['department']); ?></td></tr>
                    <tr><td class="text-muted fw-semibold text-start">Slip No:</td><td class="text-end">#<?php echo str_pad($salary['id'], 6, '0', STR_PAD_LEFT); ?></td></tr>
                    <tr>
                        <td class="text-muted fw-semibold text-start">Status:</td>
                        <td class="text-end text-<?php echo $salary['payment_status'] === 'Paid' ? 'success' : 'danger'; ?> fw-bold">
                            <?php echo sanitize($salary['payment_status']); ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Salary Details -->
        <div class="row mb-5">
            <div class="col-6 pe-4 border-end border-dark">
                <h5 class="fw-bold mb-3 border-bottom border-dark pb-2 text-success"><i class="fa-solid fa-plus-circle me-2"></i>Earnings</h5>
                <table class="table table-borderless table-sm custom-table">
                    <tr>
                        <td>Basic Salary</td>
                        <td class="text-end fw-semibold">Rs. <?php echo number_format($salary['basic_salary'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Allowances / Bonus</td>
                        <td class="text-end fw-semibold">Rs. <?php echo number_format($salary['allowances'], 2); ?></td>
                    </tr>
                    <tr class="border-top border-dark">
                        <td class="fw-bold text-uppercase pt-3">Gross Earnings</td>
                        <td class="text-end fw-bold pt-3 fs-5">Rs. <?php echo number_format($gross, 2); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-6 ps-4">
                <h5 class="fw-bold mb-3 border-bottom border-dark pb-2 text-danger"><i class="fa-solid fa-minus-circle me-2"></i>Deductions</h5>
                <table class="table table-borderless table-sm custom-table">
                    <tr>
                        <td>Tax / Leave Deductions</td>
                        <td class="text-end fw-semibold text-danger">Rs. <?php echo number_format($salary['deductions'], 2); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted opacity-0">Placeholder</td>
                        <td class="text-end text-muted opacity-0">0.00</td>
                    </tr>
                    <tr class="border-top border-dark">
                        <td class="fw-bold text-uppercase pt-3">Total Deductions</td>
                        <td class="text-end fw-bold pt-3 fs-5 text-danger">Rs. <?php echo number_format($salary['deductions'], 2); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Net Payable -->
        <div class="bg-light p-4 rounded mb-5 border border-dark d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-uppercase mb-0 text-dark">Net Salary Payable</h4>
            <h2 class="fw-bold text-primary mb-0">Rs. <?php echo number_format($salary['net_salary'], 2); ?></h2>
        </div>

        <?php if ($salary['payment_status'] === 'Paid'): ?>
        <div class="alert alert-success border border-success d-flex align-items-center p-3 mb-5">
            <i class="fa-solid fa-circle-check fs-3 me-3"></i>
            <div>
                <strong>Payment Successful</strong><br>
                This salary was disbursed on <?php echo date('d M Y', strtotime($salary['payment_date'])); ?>.
            </div>
        </div>
        <?php endif; ?>

        <!-- Signatures -->
        <div class="row mt-5 pt-5 text-center d-print-flex">
            <div class="col-4">
                <hr class="border-dark mx-auto" style="width:70%; opacity:1;">
                <div class="fw-semibold">Employee Signature</div>
            </div>
            <div class="col-4 offset-4">
                <hr class="border-dark mx-auto" style="width:70%; opacity:1;">
                <div class="fw-semibold">Authorized Signatory</div>
            </div>
        </div>
        
        <div class="mt-5 pt-3 border-top text-center small text-muted">
            This is a computer generated document and does not require a physical signature unless required for official purposes.
        </div>
    </div>
</div>

<!-- Print Styles -->
<style>
    @media print {
        body * { visibility: hidden; }
        #slipPrintArea, #slipPrintArea * { visibility: visible; }
        #slipPrintArea { position: absolute; left: 0; top: 0; width: 100%; border: none !important; box-shadow: none !important; }
        .card-body { padding: 0 !important; }
        .bg-light { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; }
        .text-primary, .text-success, .text-danger { color: #000 !important; }
        .alert-success { border-color: #000 !important; color: #000 !important; background-color: transparent !important; }
        .d-print-none { display: none !important; }
        .d-print-flex { display: flex !important; }
    }
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
