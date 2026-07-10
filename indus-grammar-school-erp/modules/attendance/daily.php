<?php
/**
 * Indus Grammar School ERP - Daily Attendance Summary
 * Version 1.0.0
 */

$pageTitle      = 'Daily Attendance Overview';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('attendance_view');

$selectedDate = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');
$summary      = Attendance::getDailySummary($selectedDate);
$markedClasses = Attendance::getMarkedClassesForDate($selectedDate);
$allClasses    = SchoolClass::all();
$rate = ($summary['total'] > 0)
    ? round(($summary['Present'] + $summary['Late']) / $summary['total'] * 100, 1)
    : 0;

// Per-class breakdown
$classBreakdown = [];
foreach ($allClasses as $c) {
    $s = Attendance::getDailySummary($selectedDate, $c['id']);
    if ($s['total'] > 0) {
        $classBreakdown[] = array_merge($c, $s, [
            'rate' => round(($s['Present'] + $s['Late']) / $s['total'] * 100, 1)
        ]);
    }
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-clipboard-list me-2 text-primary"></i>Daily Attendance</h3>
    </div>
    <div class="col-sm-6 text-sm-end">
        <form method="GET" class="d-inline-flex gap-2 align-items-center">
            <input type="date" name="date" class="form-control form-control-sm" value="<?php echo $selectedDate; ?>" max="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()">
            <a href="student.php" class="btn btn-primary btn-sm px-3"><i class="fa-solid fa-plus me-1"></i>Mark Attendance</a>
        </form>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-4 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-card-icon bg-primary-soft mx-auto mb-2"><i class="fa-solid fa-users"></i></div>
            <div class="stat-card-value"><?php echo $summary['total']; ?></div>
            <div class="stat-card-label">Total Marked</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-card-icon bg-success-soft mx-auto mb-2"><i class="fa-solid fa-user-check"></i></div>
            <div class="stat-card-value text-success"><?php echo $summary['Present']; ?></div>
            <div class="stat-card-label">Present</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-card-icon bg-danger-soft mx-auto mb-2"><i class="fa-solid fa-user-xmark"></i></div>
            <div class="stat-card-value text-danger"><?php echo $summary['Absent']; ?></div>
            <div class="stat-card-label">Absent</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-card-icon bg-warning-soft mx-auto mb-2"><i class="fa-solid fa-chart-pie"></i></div>
            <div class="stat-card-value <?php echo $rate >= 85 ? 'text-success' : 'text-danger'; ?>"><?php echo $rate; ?>%</div>
            <div class="stat-card-label">Attendance Rate</div>
        </div>
    </div>
</div>

<!-- Rate Progress Bar -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between mb-2">
            <span class="fw-semibold text-secondary">Overall Attendance Rate on <?php echo date('D, d M Y', strtotime($selectedDate)); ?></span>
            <span class="fw-bold <?php echo $rate >= 85 ? 'text-success' : 'text-danger'; ?>"><?php echo $rate; ?>%</span>
        </div>
        <div class="progress" style="height:12px; border-radius:6px;">
            <div class="progress-bar <?php echo $rate >= 85 ? 'bg-success' : ($rate >= 70 ? 'bg-warning' : 'bg-danger'); ?>"
                 style="width:<?php echo $rate; ?>%; border-radius:6px;" role="progressbar">
            </div>
        </div>
        <div class="d-flex gap-4 mt-2 small text-muted">
            <span><i class="fa-solid fa-circle text-success me-1"></i>Present: <?php echo $summary['Present']; ?></span>
            <span><i class="fa-solid fa-circle text-warning me-1"></i>Late: <?php echo $summary['Late']; ?></span>
            <span><i class="fa-solid fa-circle text-danger me-1"></i>Absent: <?php echo $summary['Absent']; ?></span>
            <span><i class="fa-solid fa-circle text-secondary me-1"></i>Leave: <?php echo $summary['Leave']; ?></span>
        </div>
    </div>
</div>

<!-- Class-wise Breakdown -->
<?php if (empty($classBreakdown)): ?>
    <div class="text-center py-5">
        <i class="fa-solid fa-calendar-xmark fs-1 text-muted opacity-50 mb-3 d-block"></i>
        <h5 class="text-muted">No attendance marked for <?php echo date('D, d M Y', strtotime($selectedDate)); ?></h5>
        <a href="student.php?date=<?php echo $selectedDate; ?>" class="btn btn-primary mt-2">Mark Attendance Now</a>
    </div>
<?php else: ?>
    <div class="custom-table-card shadow-sm border-0">
        <div class="p-4 border-bottom">
            <h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-table me-2"></i>Class-wise Breakdown</h5>
        </div>
        <div class="table-responsive">
            <table class="table custom-table table-hover">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th class="text-center">Total</th>
                        <th class="text-center text-success">Present</th>
                        <th class="text-center text-warning">Late</th>
                        <th class="text-center text-danger">Absent</th>
                        <th class="text-center">Leave</th>
                        <th>Rate</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classBreakdown as $cb): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark border px-3 py-2 rounded-pill"><?php echo sanitize($cb['class_name'] . ' - ' . $cb['section']); ?></span></td>
                            <td class="text-center fw-semibold"><?php echo $cb['total']; ?></td>
                            <td class="text-center text-success fw-bold"><?php echo $cb['Present']; ?></td>
                            <td class="text-center text-warning fw-bold"><?php echo $cb['Late']; ?></td>
                            <td class="text-center text-danger fw-bold"><?php echo $cb['Absent']; ?></td>
                            <td class="text-center text-muted"><?php echo $cb['Leave']; ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:8px;">
                                        <div class="progress-bar <?php echo $cb['rate'] >= 85 ? 'bg-success' : 'bg-warning'; ?>"
                                            style="width:<?php echo $cb['rate']; ?>%"></div>
                                    </div>
                                    <span class="small fw-semibold"><?php echo $cb['rate']; ?>%</span>
                                </div>
                            </td>
                            <td class="text-end">
                                <a href="student.php?class_id=<?php echo $cb['id']; ?>&date=<?php echo $selectedDate; ?>"
                                   class="btn btn-outline-primary btn-sm">
                                   <i class="fa-solid fa-pen-to-square me-1"></i>Update
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
