<?php
/**
 * Indus Grammar School ERP - Monthly Attendance Matrix Report Submodule
 * Version 3.0.0
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('attendance_view');

$db = Database::getConnection();

// Retrieve filters
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$selectedYear  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$academic_type = sanitize($_GET['academic_type'] ?? 'School');
$class_name = sanitize($_GET['class'] ?? '');
$section_name = sanitize($_GET['section'] ?? '');

$classesList = [];
try {
    $classesList = $db->query("SELECT class_name FROM classes GROUP BY class_name ORDER BY MIN(id) ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error fetching classes in monthly.php: " . $e->getMessage());
}
if (empty($classesList)) {
    $classesList = ['Playgroup', 'Nursery', 'Prep', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10'];
}

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
$students = [];
$attendanceMap = []; // [student_id][day] => status

$monthsList = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
    7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

if (!empty($class_name) && !empty($section_name)) {
    try {
        // Query matching students
        $qSql = "
            SELECT s.id, s.admission_no, s.first_name, s.last_name
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE s.status = 'Active' 
              AND s.academic_type = :academic_type 
              AND (c.class_name = :class1 OR s.school_class = :class2)
              AND (c.section = :section1 OR s.school_section = :section2)
            ORDER BY s.first_name ASC
        ";
        
        $stmt = $db->prepare($qSql);
        $stmt->execute([
            'academic_type' => $academic_type,
            'class1' => $class_name,
            'class2' => $class_name,
            'section1' => $section_name,
            'section2' => $section_name
        ]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($students)) {
            $studentIds = array_column($students, 'id');
            $inQuery = implode(',', array_fill(0, count($studentIds), '?'));
            
            $monthStart = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
            $monthEnd   = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $daysInMonth);
            
            $sqlAtt = "
                SELECT student_id, date, status 
                FROM attendance
                WHERE date BETWEEN ? AND ? 
                  AND student_id IN ($inQuery)
            ";
            
            $stmtAtt = $db->prepare($sqlAtt);
            $queryArgs = array_merge([$monthStart, $monthEnd], $studentIds);
            $stmtAtt->execute($queryArgs);
            
            foreach ($stmtAtt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $day = (int)date('j', strtotime($row['date']));
                $attendanceMap[$row['student_id']][$day] = $row['status'];
            }
        }
    } catch (Exception $e) {
        error_log("Monthly report grid query error: " . $e->getMessage());
    }
}

// Layout Header
$pageTitle = 'Monthly Attendance Report';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Title Banner -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-calendar-days me-2 text-primary"></i>Monthly Attendance Report</h3>
    </div>
</div>

<!-- Filters Panel (Hidden in print) -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Monthly Grid</h6>
    <form method="GET" action="monthly.php" id="filterForm" class="row g-3">
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Month</label>
            <select class="form-select form-select-sm" name="month" required>
                <?php foreach ($monthsList as $num => $name): ?>
                    <option value="<?php echo $num; ?>" <?php echo ($selectedMonth === $num) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Year</label>
            <select class="form-select form-select-sm" name="year" required>
                <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo ($selectedYear === $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Academic Type</label>
            <select class="form-select form-select-sm" name="academic_type" required>
                <option value="School" <?php echo ($academic_type === 'School') ? 'selected' : ''; ?>>School</option>
                <option value="Academy" <?php echo ($academic_type === 'Academy') ? 'selected' : ''; ?>>Academy</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Class</label>
            <select class="form-select form-select-sm" name="class" required>
                <option value="">— Select Class —</option>
                <?php foreach ($classesList as $cls): ?>
                    <option value="<?php echo $cls; ?>" <?php echo ($class_name === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Section</label>
            <select class="form-select form-select-sm" name="section" required>
                <option value="">— Select Section —</option>
                <?php
                // Unique sections list
                $sectionsList = ['A', 'B', 'C', 'D', 'E'];
                try {
                    $dbSecs = $db->query("SELECT DISTINCT section FROM classes WHERE section != '' ORDER BY section ASC")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($dbSecs as $ds) {
                        if (!in_array($ds, $sectionsList)) {
                            $sectionsList[] = $ds;
                        }
                    }
                } catch (Exception $e) {}
                foreach ($sectionsList as $sec): ?>
                    <option value="<?php echo $sec; ?>" <?php echo ($section_name === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-12 text-end mt-4">
            <button type="submit" id="genBtn" class="btn btn-sm btn-primary px-4"><i class="fa-solid fa-arrows-spin me-2"></i>Generate Report</button>
            <a href="monthly.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print</button>
            <button type="button" class="btn btn-sm btn-outline-danger px-3" onclick="window.print()"><i class="fa-solid fa-file-pdf me-2"></i>Export PDF</button>
            <button type="button" class="btn btn-sm btn-outline-success px-3" onclick="exportCSV()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
        </div>
    </form>
</div>

<!-- Print Report Header (Visible in print layout ONLY) -->
<div class="d-none d-print-block text-center mb-4">
    <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
    <h5 class="text-secondary mb-3">Monthly Attendance Matrix Report</h5>
    <div class="small text-muted border-top border-bottom py-2">
        Month: <strong><?php echo $monthsList[$selectedMonth] . ' ' . $selectedYear; ?></strong> |
        Class: <strong><?php echo htmlspecialchars($class_name . ' - ' . $section_name); ?></strong> |
        Academic Type: <strong><?php echo htmlspecialchars($academic_type); ?></strong>
    </div>
</div>

<!-- Legend Indicators (Hidden in print) -->
<?php if (!empty($students)): ?>
    <div class="d-flex flex-wrap gap-2 mb-3 d-print-none">
        <span class="badge bg-success px-3 py-2">P = Present</span>
        <span class="badge bg-danger px-3 py-2">A = Absent</span>
        <span class="badge bg-secondary px-3 py-2">L = Leave</span>
        <span class="badge bg-warning text-dark px-3 py-2">LT = Late</span>
    </div>
<?php endif; ?>

<!-- Monthly Grid Table Card -->
<div class="card border-0 shadow-sm bg-white mb-4" style="border-radius:12px; overflow:hidden;">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle text-center mb-0 small" style="min-width: 1000px;">
            <thead class="table-light">
                <tr>
                    <th class="text-start" style="position: sticky; left: 0; background: #f8fafc; z-index: 10; min-width: 180px;">Student Name</th>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                        <th style="min-width: 28px;"><?php echo $d; ?></th>
                    <?php endfor; ?>
                    <th class="table-success" style="min-width: 40px;" title="Present Days">P</th>
                    <th class="table-danger" style="min-width: 40px;" title="Absent Days">A</th>
                    <th class="table-secondary" style="min-width: 40px;" title="Leave Days">L</th>
                    <th class="table-warning text-dark" style="min-width: 40px;" title="Late Days">LT</th>
                    <th class="table-primary" style="min-width: 60px;">Rate %</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="<?php echo $daysInMonth + 6; ?>" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-calendar-xmark d-block fs-3 mb-2 opacity-50"></i>
                            No diary records found. Please choose filters and click Generate Report.
                        </td>
                    </tr>
                <?php else: foreach ($students as $st): 
                    $pDays = 0;
                    $aDays = 0;
                    $lDays = 0;
                    $ltDays = 0;
                    $totalMarked = 0;
                ?>
                    <tr>
                        <!-- Sticky name column -->
                        <td class="text-start fw-bold" style="position: sticky; left: 0; background: #fff; z-index: 10; border-right: 2px solid #dee2e6;">
                            <?php echo sanitize($st['first_name'] . ' ' . $st['last_name']); ?>
                            <br><span class="small text-muted font-monospace" style="font-size:0.65rem;"><?php echo sanitize($st['admission_no']); ?></span>
                        </td>
                        
                        <!-- Days values loop -->
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                            $status = $attendanceMap[$st['id']][$d] ?? '';
                            $cellVal = '';
                            $cellClass = '';
                            
                            if ($status === 'Present') {
                                $cellVal = 'P';
                                $cellClass = 'text-success fw-bold';
                                $pDays++;
                                $totalMarked++;
                            } elseif ($status === 'Absent') {
                                $cellVal = 'A';
                                $cellClass = 'text-danger fw-bold';
                                $aDays++;
                                $totalMarked++;
                            } elseif ($status === 'Leave') {
                                $cellVal = 'L';
                                $cellClass = 'text-secondary fw-bold';
                                $lDays++;
                                $totalMarked++;
                            } elseif ($status === 'Late') {
                                $cellVal = 'LT';
                                $cellClass = 'text-warning fw-bold';
                                $ltDays++;
                                $totalMarked++;
                            }
                        ?>
                            <td class="<?php echo $cellClass; ?>" style="font-size: 0.75rem;"><?php echo $cellVal; ?></td>
                        <?php endfor; ?>
                        
                        <!-- Row summaries -->
                        <?php 
                        $rate = 0.0;
                        if ($totalMarked > 0) {
                            $rate = round((($pDays + $ltDays) / $totalMarked) * 100, 1);
                        }
                        ?>
                        <td class="table-success fw-bold"><?php echo $pDays; ?></td>
                        <td class="table-danger fw-bold"><?php echo $aDays; ?></td>
                        <td class="table-secondary fw-bold"><?php echo $lDays; ?></td>
                        <td class="table-warning text-dark fw-bold"><?php echo $ltDays; ?></td>
                        <td class="table-primary fw-bold"><?php echo $rate; ?>%</td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Print Report Footer (Visible in print layout ONLY) -->
<div class="d-none d-print-block mt-5 text-center border-top pt-3">
    <span class="small text-muted">Page 1 of 1 | Indus Grammar School ERP System © <?php echo date('Y'); ?></span>
</div>

<script>
// Search loading spinner activation
document.getElementById('filterForm').addEventListener('submit', function() {
    const btn = document.getElementById('genBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generating...';
});

// CSV / Excel exporter logic
function exportCSV() {
    let csv = "Student Name,Admission No,";
    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
        csv += "<?php echo $d; ?>,";
    <?php endfor; ?>
    csv += "P,A,L,LT,Rate %\n";

    <?php if (!empty($students)): foreach ($students as $st): 
        $pDays = 0; $aDays = 0; $lDays = 0; $ltDays = 0; $totalMarked = 0;
    ?>
        csv += "<?php echo $st['first_name'] . ' ' . $st['last_name']; ?>,<?php echo $st['admission_no']; ?>,";
        <?php for ($d = 1; $d <= $daysInMonth; $d++): 
            $status = $attendanceMap[$st['id']][$d] ?? '';
            $val = '';
            if ($status === 'Present') { $val = 'P'; $pDays++; $totalMarked++; }
            elseif ($status === 'Absent') { $val = 'A'; $aDays++; $totalMarked++; }
            elseif ($status === 'Leave') { $val = 'L'; $lDays++; $totalMarked++; }
            elseif ($status === 'Late') { $val = 'LT'; $ltDays++; $totalMarked++; }
        ?>
            csv += "<?php echo $val; ?>,";
        <?php endfor; 
        $rate = 0.0;
        if ($totalMarked > 0) $rate = round((($pDays + $ltDays) / $totalMarked) * 100, 1);
        ?>
        csv += "<?php echo $pDays; ?>,<?php echo $aDays; ?>,<?php echo $lDays; ?>,<?php echo $ltDays; ?>,<?php echo $rate; ?>%\n";
    <?php endforeach; endif; ?>

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "monthly_attendance_report_" + new Date().toISOString().slice(0,10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
