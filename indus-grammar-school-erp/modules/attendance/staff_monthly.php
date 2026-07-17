<?php
/**
 * Indus Grammar School ERP - Monthly Staff Attendance Matrix Report
 * Version 4.0.0
 */

$pageTitle = 'Monthly Staff Attendance Report';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('attendance_view');

$db = Database::getConnection();

// Default parameters
$filterMonth = sanitize($_GET['month'] ?? date('m'));
$filterYear  = sanitize($_GET['year'] ?? date('Y'));
$filterDept  = sanitize($_GET['department'] ?? '');
$filterDesig = sanitize($_GET['designation'] ?? '');

$daysInMonth = (int)date('t', strtotime("$filterYear-$filterMonth-01"));

// Fetch unique dropdown values
$departments = [];
$designations = [];
try {
    $departments = $db->query("SELECT DISTINCT department FROM staff ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
    $designations = $db->query("SELECT DISTINCT designation FROM staff ORDER BY designation ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Load active staff members matching department and designation filters
$where = " WHERE s.status = 'Active'";
$params = [];
if ($filterDept !== '') {
    $where .= " AND s.department = :dept";
    $params['dept'] = $filterDept;
}
if ($filterDesig !== '') {
    $where .= " AND s.designation = :desig";
    $params['desig'] = $filterDesig;
}

$staffList = [];
try {
    $stmt = $db->prepare("SELECT s.id, s.first_name, s.last_name, s.employee_no FROM staff s $where ORDER BY s.employee_no ASC");
    $stmt->execute($params);
    $staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Load all attendance records for this month to index in PHP
$attendanceLookup = [];
if (!empty($staffList)) {
    $startDate = "$filterYear-$filterMonth-01";
    $endDate   = "$filterYear-$filterMonth-$daysInMonth";
    
    try {
        $stmt = $db->prepare("
            SELECT staff_id, date, status 
            FROM staff_attendance 
            WHERE date BETWEEN :start AND :end
        ");
        $stmt->execute(['start' => $startDate, 'end' => $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $day = (int)date('d', strtotime($row['date']));
            $attendanceLookup[$row['staff_id']][$day] = $row['status'];
        }
    } catch (Exception $e) {}
}

// Map status symbols
$statusSymbols = [
    'Present' => '<span class="text-success fw-bold">P</span>',
    'Absent' => '<span class="text-danger fw-bold">A</span>',
    'Late' => '<span class="text-warning fw-bold">LT</span>',
    'Leave' => '<span class="text-info fw-bold">L</span>',
    'Half Day' => '<span class="text-primary fw-bold">HD</span>',
];

// Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="monthly_staff_attendance_' . $filterYear . '_' . $filterMonth . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, [SCHOOL_NAME . ' - MONTHLY STAFF ATTENDANCE SUMMARY']);
    fputcsv($output, ['Period:', date('F Y', strtotime("$filterYear-$filterMonth-01"))]);
    fputcsv($output, []);
    
    $headerRow = ['Employee Name'];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $headerRow[] = $d;
    }
    $headerRow = array_merge($headerRow, ['P', 'A', 'L', 'LT', 'HD', 'Rate %']);
    fputcsv($output, $headerRow);
    
    foreach ($staffList as $st) {
        $row = [$st['first_name'] . ' ' . $st['last_name']];
        $p = 0; $a = 0; $l = 0; $lt = 0; $hd = 0;
        
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $status = $attendanceLookup[$st['id']][$day] ?? '';
            if ($status === 'Present') { $row[] = 'P'; $p++; }
            elseif ($status === 'Absent') { $row[] = 'A'; $a++; }
            elseif ($status === 'Leave') { $row[] = 'L'; $l++; }
            elseif ($status === 'Late') { $row[] = 'LT'; $lt++; }
            elseif ($status === 'Half Day') { $row[] = 'HD'; $hd++; }
            else { $row[] = ''; }
        }
        $totalMarked = $p + $a + $l + $lt + $hd;
        $presentDays = $p + $lt + $hd;
        $rate = $totalMarked > 0 ? round(($presentDays / $totalMarked) * 100, 1) : 0;
        
        $row = array_merge($row, [$p, $a, $l, $lt, $hd, $rate . '%']);
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-calendar-week me-2 text-primary"></i>Monthly Staff Attendance Report</h3>
        <p class="text-muted small mb-0">Generate complete monthly calendars, audit employee check-ins, and export registers.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-outline-primary px-3 me-2" id="btnPrintReport">
            <i class="fa-solid fa-print me-1"></i>Print Report
        </button>
        <a href="?action=export&month=<?php echo $filterMonth; ?>&year=<?php echo $filterYear; ?>&department=<?php echo $filterDept; ?>&designation=<?php echo $filterDesig; ?>" class="btn btn-primary px-3">
            <i class="fa-solid fa-file-excel me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- Legend Badge Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:10px;">
    <div class="card-body p-3 d-flex flex-wrap gap-3 align-items-center bg-light">
        <span class="small fw-bold text-secondary">Legend Index:</span>
        <span class="badge bg-success-soft text-success px-3 py-2 rounded-pill"><strong class="me-1">P</strong> Present</span>
        <span class="badge bg-danger-soft text-danger px-3 py-2 rounded-pill"><strong class="me-1">A</strong> Absent</span>
        <span class="badge bg-info-soft text-info px-3 py-2 rounded-pill"><strong class="me-1">L</strong> Leave</span>
        <span class="badge bg-warning-soft text-warning px-3 py-2 rounded-pill"><strong class="me-1">LT</strong> Late</span>
        <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill"><strong class="me-1">HD</strong> Half Day</span>
    </div>
</div>

<!-- Filters Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter parameters</h6>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Select Month</label>
                <select class="form-select form-select-sm" name="month">
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $val = str_pad($m, 2, '0', STR_PAD_LEFT);
                    ?>
                        <option value="<?php echo $val; ?>" <?php echo ($filterMonth === $val) ? 'selected' : ''; ?>><?php echo date('F', strtotime("2026-$val-01")); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Select Year</label>
                <select class="form-select form-select-sm" name="year">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 2; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($filterYear == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Department</label>
                <select class="form-select form-select-sm" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo ($filterDept === $d) ? 'selected' : ''; ?>><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Designation</label>
                <select class="form-select form-select-sm" name="designation">
                    <option value="">All Designations</option>
                    <?php foreach ($designations as $ds): ?>
                        <option value="<?php echo $ds; ?>" <?php echo ($filterDesig === $ds) ? 'selected' : ''; ?>><?php echo $ds; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100 py-2"><i class="fa-solid fa-sync me-2"></i>Generate</button>
            </div>
        </form>
    </div>
</div>

<!-- Matrix Table Grid -->
<div class="custom-table-card shadow-sm border-0 mb-4" id="reportSection">
    <div class="table-responsive">
        <table class="table table-bordered custom-table table-hover align-middle mb-0 text-center" style="font-size:0.75rem;">
            <thead>
                <tr class="table-light">
                    <th class="text-start" style="font-size:0.8rem; min-width: 140px; position: sticky; left: 0; background: #fff; z-index: 10;">Employee Name</th>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                        <th width="30"><?php echo $d; ?></th>
                    <?php endfor; ?>
                    <th class="bg-light fw-bold" width="35">P</th>
                    <th class="bg-light fw-bold" width="35">A</th>
                    <th class="bg-light fw-bold" width="35">L</th>
                    <th class="bg-light fw-bold" width="35">LT</th>
                    <th class="bg-light fw-bold" width="35">HD</th>
                    <th class="bg-primary text-white fw-bold" width="60">Rate %</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staffList)): ?>
                    <tr><td colspan="<?php echo $daysInMonth + 7; ?>" class="text-center py-5 text-muted">No active staff members registered.</td></tr>
                <?php else: foreach ($staffList as $st): 
                    $p = 0; $a = 0; $l = 0; $lt = 0; $hd = 0;
                ?>
                    <tr>
                        <td class="text-start fw-bold text-dark" style="position: sticky; left: 0; background: #fff; z-index: 10; border-right: 2px solid #dee2e6;"><?php echo sanitize($st['first_name'] . ' ' . $st['last_name']); ?></td>
                        <?php for ($day = 1; $day <= $daysInMonth; $day++): 
                            $status = $attendanceLookup[$st['id']][$day] ?? '';
                            if ($status === 'Present') $p++;
                            elseif ($status === 'Absent') $a++;
                            elseif ($status === 'Leave') $l++;
                            elseif ($status === 'Late') $lt++;
                            elseif ($status === 'Half Day') $hd++;
                        ?>
                            <td><?php echo $status ? ($statusSymbols[$status] ?? '') : '—'; ?></td>
                        <?php endfor; 
                            $totalMarked = $p + $a + $l + $lt + $hd;
                            $presentCount = $p + $lt + $hd;
                            $rate = $totalMarked > 0 ? round(($presentCount / $totalMarked) * 100, 1) : 0;
                        ?>
                        <td class="fw-bold text-success bg-light-soft"><?php echo $p; ?></td>
                        <td class="fw-bold text-danger bg-light-soft"><?php echo $a; ?></td>
                        <td class="fw-bold text-info bg-light-soft"><?php echo $l; ?></td>
                        <td class="fw-bold text-warning bg-light-soft"><?php echo $lt; ?></td>
                        <td class="fw-bold text-primary bg-light-soft"><?php echo $hd; ?></td>
                        <td class="fw-bold bg-primary-soft text-primary"><?php echo $rate; ?>%</td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Print view layout -->
<div id="printReportTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ccc; width: 1000px; margin: 0 auto; border-radius: 6px;">
        <div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 15px;">
            <h2 style="margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h2>
            <p style="margin: 4px 0 0 0; font-size: 11px; color:#555;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 10px 0 0 0; background: #eee; padding: 4px; border-radius: 4px; letter-spacing: 1px;">MONTHLY STAFF ATTENDANCE SHEET</h4>
        </div>
        
        <table style="width: 100%; font-size: 11px; margin-bottom: 15px;">
            <tr>
                <td><strong>Month Period:</strong> <?php echo date('F Y', strtotime("$filterYear-$filterMonth-01")); ?></td>
                <td><strong>Department:</strong> <?php echo $filterDept ?: 'All'; ?></td>
                <td style="text-align: right;"><strong>Date Printed:</strong> <?php echo date('d M Y'); ?></td>
            </tr>
        </table>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 9px; text-align: center;" border="1" cellpadding="4">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th style="text-align: left; font-weight: bold;">Employee Name</th>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                        <th><?php echo $d; ?></th>
                    <?php endfor; ?>
                    <th>P</th>
                    <th>A</th>
                    <th>L</th>
                    <th>LT</th>
                    <th>HD</th>
                    <th>Rate %</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffList as $st): 
                    $p = 0; $a = 0; $l = 0; $lt = 0; $hd = 0;
                ?>
                    <tr>
                        <td style="text-align: left; font-weight: bold;"><?php echo $st['first_name'] . ' ' . $st['last_name']; ?></td>
                        <?php for ($day = 1; $day <= $daysInMonth; $day++): 
                            $status = $attendanceLookup[$st['id']][$day] ?? '';
                            if ($status === 'Present') { echo '<td>P</td>'; $p++; }
                            elseif ($status === 'Absent') { echo '<td>A</td>'; $a++; }
                            elseif ($status === 'Leave') { echo '<td>L</td>'; $l++; }
                            elseif ($status === 'Late') { echo '<td>LT</td>'; $lt++; }
                            elseif ($status === 'Half Day') { echo '<td>HD</td>'; $hd++; }
                            else { echo '<td>—</td>'; }
                        endfor;
                        $totalMarked = $p + $a + $l + $lt + $hd;
                        $presentCount = $p + $lt + $hd;
                        $rate = $totalMarked > 0 ? round(($presentCount / $totalMarked) * 100, 1) : 0;
                        ?>
                        <td><strong><?php echo $p; ?></strong></td>
                        <td><strong><?php echo $a; ?></strong></td>
                        <td><strong><?php echo $l; ?></strong></td>
                        <td><strong><?php echo $lt; ?></strong></td>
                        <td><strong><?php echo $hd; ?></strong></td>
                        <td><strong><?php echo $rate; ?>%</strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $extraJS = '<script>
document.addEventListener("DOMContentLoaded", function() {
    const printBtn = document.getElementById("btnPrintReport");
    if (printBtn) {
        printBtn.addEventListener("click", function() {
            const printContent = document.getElementById("printReportTemplate").innerHTML;
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Monthly Staff Attendance</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
