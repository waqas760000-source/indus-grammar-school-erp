<?php
/**
 * Indus Grammar School ERP - Student Enrollment Reports
 * Version 1.0.0
 */

$pageTitle = 'Student Enrollment Report';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('report_view');

$studentStats = Report::getStudentSummary();
$classEnrollment = Report::getClasswiseEnrollment();
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Student Enrollment Report</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-outline-primary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Report</button>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Summary Cards -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4 text-center">
                <h5 class="text-muted fw-semibold small">Active Students</h5>
                <h2 class="fw-bold text-success mb-0"><?php echo $studentStats['active_count'] ?? 0; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4 text-center">
                <h5 class="text-muted fw-semibold small">Suspended</h5>
                <h2 class="fw-bold text-warning mb-0"><?php echo $studentStats['suspended_count'] ?? 0; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4 text-center">
                <h5 class="text-muted fw-semibold small">Graduated</h5>
                <h2 class="fw-bold text-primary mb-0"><?php echo $studentStats['graduated_count'] ?? 0; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4 text-center">
                <h5 class="text-muted fw-semibold small">Withdrawn</h5>
                <h2 class="fw-bold text-danger mb-0"><?php echo $studentStats['withdrawn_count'] ?? 0; ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-0 text-secondary">Class-wise Active Enrollment Breakdown</h5>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Class Name</th>
                    <th>Section</th>
                    <th class="text-center">Active Students</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($classEnrollment)): ?>
                    <tr><td colspan="3" class="text-center py-5 text-muted">No classes config or students found.</td></tr>
                <?php else: foreach ($classEnrollment as $row): ?>
                    <tr>
                        <td class="fw-semibold text-dark"><?php echo sanitize($row['class_name']); ?></td>
                        <td><span class="badge bg-light text-dark border px-3 py-2"><?php echo sanitize($row['section']); ?></span></td>
                        <td class="text-center fw-bold fs-5 text-primary"><?php echo $row['student_count']; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
