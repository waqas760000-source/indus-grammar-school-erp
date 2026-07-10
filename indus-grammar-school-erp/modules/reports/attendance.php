<?php
/**
 * Indus Grammar School ERP - Attendance Reports
 * Version 1.0.0
 */

$pageTitle = 'Attendance Analysis Report';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('report_view');

$dateFrom = sanitize($_GET['from'] ?? date('Y-m-01'));
$dateTo   = sanitize($_GET['to'] ?? date('Y-m-d'));

$stats = Report::getAttendanceStats($dateFrom, $dateTo);
$counts = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Leave' => 0];
$total = 0;
foreach ($stats as $row) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = (int)$row['count'];
        $total += (int)$row['count'];
    }
}

$presentPct = $total > 0 ? ($counts['Present'] / $total) * 100 : 0;
$absentPct  = $total > 0 ? ($counts['Absent'] / $total) * 100 : 0;
$latePct    = $total > 0 ? ($counts['Late'] / $total) * 100 : 0;
$leavePct   = $total > 0 ? ($counts['Leave'] / $total) * 100 : 0;
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-calendar-check me-2 text-primary"></i>Attendance Report</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-outline-primary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Report</button>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">From Date</label>
                <input type="date" class="form-control" name="from" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">To Date</label>
                <input type="date" class="form-control" name="to" value="<?php echo $dateTo; ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-sync me-2"></i>Generate Report</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Present Rate -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-4 h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #f0fdf4);">
            <h5 class="text-muted fw-semibold small">Present Rate</h5>
            <h2 class="fw-bold text-success mb-2"><?php echo round($presentPct, 1); ?>%</h2>
            <small class="text-muted"><?php echo $counts['Present']; ?> Total Records</small>
        </div>
    </div>
    <!-- Absent Rate -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-4 h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #fef5f5);">
            <h5 class="text-muted fw-semibold small">Absent Rate</h5>
            <h2 class="fw-bold text-danger mb-2"><?php echo round($absentPct, 1); ?>%</h2>
            <small class="text-muted"><?php echo $counts['Absent']; ?> Total Records</small>
        </div>
    </div>
    <!-- Late Rate -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-4 h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #fffbeb);">
            <h5 class="text-muted fw-semibold small">Late Rate</h5>
            <h2 class="fw-bold text-warning mb-2"><?php echo round($latePct, 1); ?>%</h2>
            <small class="text-muted"><?php echo $counts['Late']; ?> Total Records</small>
        </div>
    </div>
    <!-- Leave Rate -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-4 h-100" style="border-radius:12px; background: linear-gradient(135deg, #fff, #eff6ff);">
            <h5 class="text-muted fw-semibold small">Leave Rate</h5>
            <h2 class="fw-bold text-primary mb-2"><?php echo round($leavePct, 1); ?>%</h2>
            <small class="text-muted"><?php echo $counts['Leave']; ?> Total Records</small>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
