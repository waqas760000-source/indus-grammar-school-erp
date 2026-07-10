<?php
/**
 * Indus Grammar School ERP - Monthly Attendance View
 * Version 1.0.0
 */

$pageTitle      = 'Monthly Attendance';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('attendance_view');

$classes       = SchoolClass::all();
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$selectedYear  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
$students    = [];
$attendanceMap = []; // [student_id][date] => status

if ($selectedClass > 0) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT id, first_name, last_name, admission_no FROM students
            WHERE class_id = :cid AND status = 'Active'
            ORDER BY first_name ASC
        ");
        $stmt->execute(['cid' => $selectedClass]);
        $students = $stmt->fetchAll();

        $monthStart = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
        $monthEnd   = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $daysInMonth);
        $stmt2 = $db->prepare("
            SELECT student_id, date, status FROM attendance
            WHERE class_id = :cid AND date BETWEEN :start AND :end
        ");
        $stmt2->execute(['cid' => $selectedClass, 'start' => $monthStart, 'end' => $monthEnd]);
        foreach ($stmt2->fetchAll() as $row) {
            $day = (int)date('j', strtotime($row['date']));
            $attendanceMap[$row['student_id']][$day] = $row['status'];
        }
    } catch (Exception $e) {
        error_log("monthly.php: " . $e->getMessage());
    }
}

$statusColors = ['Present' => 'success', 'Absent' => 'danger', 'Late' => 'warning', 'Leave' => 'secondary'];
$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-calendar-days me-2 text-primary"></i>Monthly Attendance Register</h3>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold text-muted">Class</label>
                <select class="form-select" name="class_id">
                    <option value="">— Select Class —</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($selectedClass == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold text-muted">Month</label>
                <select class="form-select" name="month">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo ($selectedMonth == $m) ? 'selected' : ''; ?>><?php echo $months[$m-1]; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold text-muted">Year</label>
                <select class="form-select" name="year">
                    <?php for ($y = 2024; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($selectedYear == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-primary w-100 py-2">View Register</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedClass > 0 && !empty($students)): ?>
<!-- Legend -->
<div class="d-flex gap-3 mb-3 flex-wrap">
    <?php foreach ($statusColors as $s => $c): ?>
        <span class="badge bg-<?php echo $c; ?> px-3 py-2"><?php echo substr($s, 0, 1); ?> = <?php echo $s; ?></span>
    <?php endforeach; ?>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-0 text-secondary">
            <?php $ci = SchoolClass::findById($selectedClass); echo sanitize($ci['class_name'].' - '.$ci['section']); ?>
            &nbsp;·&nbsp; <?php echo $months[$selectedMonth - 1] . ' ' . $selectedYear; ?>
        </h5>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered custom-table" style="font-size:0.8rem; min-width:900px;">
            <thead>
                <tr class="bg-light">
                    <th style="min-width:160px;">Student</th>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                        <th class="text-center px-1" style="width:32px;">
                            <div><?php echo $d; ?></div>
                            <div class="text-muted" style="font-size:0.65rem;"><?php echo date('D', mktime(0,0,0,$selectedMonth,$d,$selectedYear))[0]; ?></div>
                        </th>
                    <?php endfor; ?>
                    <th class="text-center">P</th>
                    <th class="text-center">A</th>
                    <th class="text-center">%</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $s): ?>
                    <?php
                        $p = 0; $a = 0;
                        for ($d = 1; $d <= $daysInMonth; $d++) {
                            $st = $attendanceMap[$s['id']][$d] ?? null;
                            if ($st === 'Present' || $st === 'Late') $p++;
                            elseif ($st === 'Absent') $a++;
                        }
                        $total = $p + $a;
                        $pct = $total > 0 ? round($p / $total * 100) : 0;
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark" style="font-size:0.85rem;"><?php echo sanitize($s['first_name'].' '.$s['last_name']); ?></div>
                            <div class="text-muted" style="font-size:0.7rem;"><?php echo sanitize($s['admission_no']); ?></div>
                        </td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                            <?php
                                $dayOfWeek = date('N', mktime(0,0,0,$selectedMonth,$d,$selectedYear));
                                $isWeekend = $dayOfWeek >= 6;
                                $st = $attendanceMap[$s['id']][$d] ?? null;
                                $color = $isWeekend ? 'bg-light text-muted' : ($st ? 'bg-'.$statusColors[$st].' text-white' : '');
                                $label = $isWeekend ? '–' : ($st ? substr($st,0,1) : '');
                            ?>
                            <td class="text-center px-0 <?php echo $color; ?>" title="<?php echo $st ?? ($isWeekend ? 'Weekend' : 'Not Marked'); ?>">
                                <?php echo $label; ?>
                            </td>
                        <?php endfor; ?>
                        <td class="text-center fw-bold text-success"><?php echo $p; ?></td>
                        <td class="text-center fw-bold text-danger"><?php echo $a; ?></td>
                        <td class="text-center fw-bold <?php echo $pct >= 85 ? 'text-success' : 'text-danger'; ?>"><?php echo $pct; ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($selectedClass > 0): ?>
    <div class="text-center py-5"><p class="text-muted">No students found in this class.</p></div>
<?php endif; ?>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
