<?php
/**
 * Indus Grammar School ERP - Attendance Reports
 * Version 1.0.0
 */

$pageTitle      = 'Attendance Reports';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('attendance_view');

$classes       = SchoolClass::all();
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$dateFrom      = isset($_GET['from']) ? sanitize($_GET['from']) : date('Y-m-01');
$dateTo        = isset($_GET['to'])   ? sanitize($_GET['to'])   : date('Y-m-d');
$reportData    = [];
$overallSummary = [];

if ($selectedClass > 0) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT
                s.id, s.first_name, s.last_name, s.admission_no,
                COUNT(a.id) as total_days,
                SUM(a.status = 'Present') as present_days,
                SUM(a.status = 'Absent') as absent_days,
                SUM(a.status = 'Late') as late_days,
                SUM(a.status = 'Leave') as leave_days
            FROM students s
            LEFT JOIN attendance a ON a.student_id = s.id AND a.date BETWEEN :from AND :to
            WHERE s.class_id = :cid AND s.status = 'Active'
            GROUP BY s.id
            ORDER BY s.first_name ASC
        ");
        $stmt->execute(['cid' => $selectedClass, 'from' => $dateFrom, 'to' => $dateTo]);
        $reportData = $stmt->fetchAll();

        // Overall summary for the class
        $stmt2 = $db->prepare("
            SELECT
                COUNT(*) as total,
                SUM(status='Present') as present,
                SUM(status='Absent') as absent,
                SUM(status='Late') as late,
                SUM(status='Leave') as lv
            FROM attendance
            WHERE class_id = :cid AND date BETWEEN :from AND :to
        ");
        $stmt2->execute(['cid' => $selectedClass, 'from' => $dateFrom, 'to' => $dateTo]);
        $overallSummary = $stmt2->fetch();
    } catch (Exception $e) {
        error_log("Attendance reports.php: " . $e->getMessage());
    }
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Attendance Reports</h3>
    </div>
</div>

<!-- Filter Card -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold text-muted">Class</label>
                <select class="form-select" name="class_id">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($selectedClass == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($c['class_name'].' - '.$c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold text-muted">From</label>
                <input type="date" class="form-control" name="from" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold text-muted">To</label>
                <input type="date" class="form-control" name="to" value="<?php echo $dateTo; ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-primary w-100 py-2">Generate Report</button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($overallSummary) && $overallSummary['total'] > 0): ?>
<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <?php
    $total = (int)$overallSummary['total'];
    $present = (int)$overallSummary['present'];
    $absent  = (int)$overallSummary['absent'];
    $late    = (int)$overallSummary['late'];
    $rate    = $total > 0 ? round(($present + $late) / $total * 100, 1) : 0;
    ?>
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="stat-card-value"><?php echo $total; ?></div><div class="stat-card-label">Total Records</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="stat-card-value text-success"><?php echo $present; ?></div><div class="stat-card-label">Present</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="stat-card-value text-danger"><?php echo $absent; ?></div><div class="stat-card-label">Absent</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="stat-card-value <?php echo $rate >= 85 ? 'text-success' : 'text-danger'; ?>"><?php echo $rate; ?>%</div><div class="stat-card-label">Avg. Rate</div></div></div>
</div>
<?php endif; ?>

<?php if (!empty($reportData)): ?>
<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-secondary">Student-wise Breakdown &nbsp;·&nbsp; <?php echo date('d M Y', strtotime($dateFrom)); ?> to <?php echo date('d M Y', strtotime($dateTo)); ?></h5>
        <span class="badge bg-primary rounded-pill"><?php echo count($reportData); ?> Students</span>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Student</th>
                    <th class="text-center">Total Days</th>
                    <th class="text-center text-success">Present</th>
                    <th class="text-center text-warning">Late</th>
                    <th class="text-center text-danger">Absent</th>
                    <th class="text-center">Leave</th>
                    <th style="min-width:150px;">Attendance Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportData as $row):
                    $total = (int)$row['total_days'];
                    $p = (int)$row['present_days'];
                    $l = (int)$row['late_days'];
                    $a = (int)$row['absent_days'];
                    $lv = (int)$row['leave_days'];
                    $pct = $total > 0 ? round(($p + $l) / $total * 100, 1) : 0;
                    $pctColor = $pct >= 85 ? 'success' : ($pct >= 70 ? 'warning' : 'danger');
                ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo sanitize($row['first_name'].' '.$row['last_name']); ?></div>
                            <div class="text-muted small"><?php echo sanitize($row['admission_no']); ?></div>
                        </td>
                        <td class="text-center fw-bold"><?php echo $total; ?></td>
                        <td class="text-center text-success fw-bold"><?php echo $p; ?></td>
                        <td class="text-center text-warning fw-bold"><?php echo $l; ?></td>
                        <td class="text-center text-danger fw-bold"><?php echo $a; ?></td>
                        <td class="text-center text-muted"><?php echo $lv; ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:8px;">
                                    <div class="progress-bar bg-<?php echo $pctColor; ?>" style="width:<?php echo $pct; ?>%"></div>
                                </div>
                                <span class="small fw-bold text-<?php echo $pctColor; ?>"><?php echo $pct; ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($selectedClass > 0): ?>
    <div class="text-center py-5">
        <i class="fa-solid fa-chart-simple fs-1 text-muted opacity-50 d-block mb-3"></i>
        <h5 class="text-muted">No attendance data for the selected period.</h5>
    </div>
<?php endif; ?>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
