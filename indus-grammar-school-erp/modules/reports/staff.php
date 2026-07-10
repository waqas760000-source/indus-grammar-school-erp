<?php
/**
 * Indus Grammar School ERP - Staff Reports
 * Version 1.0.0
 */

$pageTitle = 'Staff Directory Report';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('report_view');

$summary = Report::getStaffSummary();
$totalSalary = array_sum(array_column($summary, 'total_salary'));
$totalStaff = array_sum(array_column($summary, 'count'));
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-users-gear me-2 text-primary"></i>Staff Report</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-outline-primary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Report</button>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius:12px;">
            <h5 class="text-muted fw-semibold small mb-2">Total Staff Members</h5>
            <h2 class="fw-bold text-primary mb-0"><?php echo $totalStaff; ?></h2>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius:12px;">
            <h5 class="text-muted fw-semibold small mb-2">Total Monthly Payroll Commited</h5>
            <h2 class="fw-bold text-success mb-0">Rs. <?php echo number_format($totalSalary, 2); ?></h2>
        </div>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-0 text-secondary">Departmental Summary Breakdown</h5>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Status</th>
                    <th class="text-center">Staff Count</th>
                    <th class="text-end">Total Salary Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($summary)): ?>
                    <tr><td colspan="4" class="text-center py-5 text-muted">No staff records found.</td></tr>
                <?php else: foreach ($summary as $row): ?>
                    <tr>
                        <td class="fw-semibold text-dark"><?php echo sanitize($row['department']); ?></td>
                        <td>
                            <span class="badge badge-soft-<?php echo $row['status'] === 'Active' ? 'success' : 'secondary'; ?> px-3 py-2 rounded-pill">
                                <?php echo sanitize($row['status']); ?>
                            </span>
                        </td>
                        <td class="text-center fw-bold"><?php echo $row['count']; ?></td>
                        <td class="text-end fw-bold">Rs. <?php echo number_format($row['total_salary'], 2); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
