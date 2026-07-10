<?php
/**
 * Indus Grammar School ERP - Custom Report Builder Guide
 * Version 1.0.0
 */

$pageTitle = 'Custom Reports Builder';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('report_view');
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-sliders me-2 text-primary"></i>Custom Reports</h3>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:12px;">
    <div class="card-body p-5 text-center">
        <div class="d-inline-block p-4 rounded-circle bg-primary-soft mb-3 text-primary fs-1">
            <i class="fa-solid fa-toolbox"></i>
        </div>
        <h4 class="fw-bold text-secondary">Custom Report Generator</h4>
        <p class="text-muted small mx-auto" style="max-width: 500px;">To construct custom criteria queries and download CSV datasets, please utilize the pre-constructed Student, Attendance, Finance, or Staff report views and export options.</p>
        <div class="mt-4 d-flex justify-content-center gap-2">
            <a href="students.php" class="btn btn-primary px-4">Students Directory Report</a>
            <a href="finance.php" class="btn btn-outline-secondary px-4">Financial Ledger Report</a>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
