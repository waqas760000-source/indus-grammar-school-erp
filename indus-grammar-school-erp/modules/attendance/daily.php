<?php
/**
 * Indus Grammar School ERP - Daily Attendance Report Submodule
 * Version 3.0.0
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('attendance_view');

$db = Database::getConnection();

// Retrieve filters
$filter_date = sanitize($_GET['date'] ?? date('Y-m-d'));
$filter_academic_type = sanitize($_GET['academic_type'] ?? '');
$filter_class = sanitize($_GET['class'] ?? '');
$filter_section = sanitize($_GET['section'] ?? '');
$filter_status = sanitize($_GET['status'] ?? '');

$limit = 15;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Build query conditions
$where = " WHERE 1=1";
$params = [];

if ($filter_date !== '') {
    $where .= " AND a.date = :date";
    $params['date'] = $filter_date;
}
if ($filter_academic_type !== '') {
    $where .= " AND s.academic_type = :academic_type";
    $params['academic_type'] = $filter_academic_type;
}
if ($filter_class !== '') {
    $where .= " AND (c.class_name = :class OR s.school_class = :class)";
    $params['class'] = $filter_class;
}
if ($filter_section !== '') {
    $where .= " AND (c.section = :section OR s.school_section = :section)";
    $params['section'] = $filter_section;
}
if ($filter_status !== '') {
    $where .= " AND a.status = :status";
    $params['status'] = $filter_status;
}

$records = [];
$totalEntries = 0;

// KPI summaries matching selected filters
$presentCount = 0;
$absentCount = 0;
$leaveCount = 0;
$lateCount = 0;
$attendancePercentage = 0.0;

try {
    // Total count query
    $stmtCount = $db->prepare("
        SELECT COUNT(*) 
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN classes c ON a.class_id = c.id
        $where
    ");
    $stmtCount->execute($params);
    $totalEntries = (int)$stmtCount->fetchColumn();

    // Summaries counters
    $stmtSum = $db->prepare("
        SELECT a.status, COUNT(*) as cnt 
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN classes c ON a.class_id = c.id
        $where
        GROUP BY a.status
    ");
    $stmtSum->execute($params);
    $sums = $stmtSum->fetchAll(PDO::FETCH_KEY_PAIR);

    $presentCount = (int)($sums['Present'] ?? 0);
    $absentCount = (int)($sums['Absent'] ?? 0);
    $lateCount = (int)($sums['Late'] ?? 0);
    $leaveCount = (int)($sums['Leave'] ?? 0);
    $totalSum = $presentCount + $absentCount + $lateCount + $leaveCount;
    
    if ($totalSum > 0) {
        $attendancePercentage = round((($presentCount + $lateCount) / $totalSum) * 100, 1);
    }

    // Main records data query
    $stmtData = $db->prepare("
        SELECT a.*, s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section,
               c.class_name, c.section, d.roll_no
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY s.admission_no ASC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmtData->bindValue($k, $v);
    }
    $stmtData->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();

    $records = $stmtData->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Daily attendance summary query error: " . $e->getMessage());
}

$totalPages = ceil($totalEntries / $limit);
if ($totalPages < 1) $totalPages = 1;

// Load unique sections list
$sectionsList = ['A', 'B', 'C', 'D', 'E'];
try {
    $dbSecs = $db->query("SELECT DISTINCT section FROM classes WHERE section != '' ORDER BY section ASC")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dbSecs as $ds) {
        if (!in_array($ds, $sectionsList)) {
            $sectionsList[] = $ds;
        }
    }
} catch (Exception $e) {}

// Include Layout Header
$pageTitle = 'Daily Attendance Report';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Title Banner -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Daily Attendance Report</h3>
    </div>
</div>

<!-- KPI Summary Cards Section -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm text-center h-100 bg-white" style="border-radius:12px; border-left: 4px solid #1e3a8a !important;">
            <div class="card-body p-3">
                <span class="text-muted small d-block mb-1">Total Marked</span>
                <h4 class="fw-bold text-dark mb-0"><?php echo $totalEntries; ?></h4>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm text-center h-100 bg-white" style="border-radius:12px; border-left: 4px solid #28a745 !important;">
            <div class="card-body p-3">
                <span class="text-muted small d-block mb-1">Present</span>
                <h4 class="fw-bold text-success mb-0"><?php echo $presentCount; ?></h4>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm text-center h-100 bg-white" style="border-radius:12px; border-left: 4px solid #dc3545 !important;">
            <div class="card-body p-3">
                <span class="text-muted small d-block mb-1">Absent</span>
                <h4 class="fw-bold text-danger mb-0"><?php echo $absentCount; ?></h4>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm text-center h-100 bg-white" style="border-radius:12px; border-left: 4px solid #6c757d !important;">
            <div class="card-body p-3">
                <span class="text-muted small d-block mb-1">Leave</span>
                <h4 class="fw-bold text-secondary mb-0"><?php echo $leaveCount; ?></h4>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm text-center h-100 bg-white" style="border-radius:12px; border-left: 4px solid #ffc107 !important;">
            <div class="card-body p-3">
                <span class="text-muted small d-block mb-1">Late</span>
                <h4 class="fw-bold text-warning mb-0"><?php echo $lateCount; ?></h4>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-2">
        <div class="card border-0 shadow-sm text-center h-100 bg-white" style="border-radius:12px; border-left: 4px solid #1e3a8a !important;">
            <div class="card-body p-3">
                <span class="text-muted small d-block mb-1">Attendance Rate</span>
                <h4 class="fw-bold text-primary mb-0"><?php echo $attendancePercentage; ?>%</h4>
            </div>
        </div>
    </div>
</div>

<!-- Filters Panel Card (Hidden in Print) -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Attendance Logs</h6>
    <form method="GET" action="daily.php" id="searchForm" class="row g-3">
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Attendance Date</label>
            <input type="date" class="form-control form-control-sm" name="date" value="<?php echo htmlspecialchars($filter_date); ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Academic Type</label>
            <select class="form-select form-select-sm" name="academic_type">
                <option value="">All Types</option>
                <option value="School" <?php echo ($filter_academic_type === 'School') ? 'selected' : ''; ?>>School</option>
                <option value="Academy" <?php echo ($filter_academic_type === 'Academy') ? 'selected' : ''; ?>>Academy</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Class</label>
            <select class="form-select form-select-sm" name="class">
                <option value="">All Classes</option>
                <?php foreach (['Play Group', 'Nursery', 'Prep', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10', 'Class 11', 'Class 12'] as $cls): ?>
                    <option value="<?php echo $cls; ?>" <?php echo ($filter_class === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Section</label>
            <select class="form-select form-select-sm" name="section">
                <option value="">All Sections</option>
                <?php foreach ($sectionsList as $sec): ?>
                    <option value="<?php echo $sec; ?>" <?php echo ($filter_section === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Status</label>
            <select class="form-select form-select-sm" name="status">
                <option value="">All Statuses</option>
                <option value="Present" <?php echo ($filter_status === 'Present') ? 'selected' : ''; ?>>Present</option>
                <option value="Absent" <?php echo ($filter_status === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                <option value="Late" <?php echo ($filter_status === 'Late') ? 'selected' : ''; ?>>Late</option>
                <option value="Leave" <?php echo ($filter_status === 'Leave') ? 'selected' : ''; ?>>Leave</option>
            </select>
        </div>
        
        <div class="col-12 text-end mt-4">
            <button type="submit" id="searchBtn" class="btn btn-sm btn-primary px-4"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
            <a href="daily.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print</button>
            <button type="button" class="btn btn-sm btn-outline-danger px-3" onclick="window.print()"><i class="fa-solid fa-file-pdf me-2"></i>Export PDF</button>
            <button type="button" class="btn btn-sm btn-outline-success px-3" onclick="exportCSV()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
        </div>
    </form>
</div>

<!-- Print Report Header (Visible in print layout ONLY) -->
<div class="d-none d-print-block text-center mb-4">
    <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
    <h5 class="text-secondary mb-3">Daily Student Attendance Report</h5>
    <div class="small text-muted border-top border-bottom py-2">
        Date: <strong><?php echo date('M d, Y', strtotime($filter_date)); ?></strong>
        <?php if ($filter_class): ?> | Class: <strong><?php echo htmlspecialchars($filter_class); ?></strong><?php endif; ?>
        <?php if ($filter_section): ?> | Section: <strong><?php echo htmlspecialchars($filter_section); ?></strong><?php endif; ?>
        | Present: <strong><?php echo $presentCount; ?></strong>
        | Absent: <strong><?php echo $absentCount; ?></strong>
        | Attendance Rate: <strong><?php echo $attendancePercentage; ?>%</strong>
    </div>
</div>

<!-- Attendance Records Table -->
<div class="card border-0 shadow-sm bg-white mb-4" style="border-radius:12px; overflow:hidden;">
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Admission No</th>
                    <th>Roll No</th>
                    <th>Student Name</th>
                    <th>Class</th>
                    <th>Section</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th>Attendance Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No attendance records found.</td>
                    </tr>
                <?php else: foreach ($records as $row): ?>
                    <tr>
                        <td><strong class="text-primary"><?php echo sanitize($row['admission_no']); ?></strong></td>
                        <td><span class="small font-monospace text-secondary"><?php echo displayValue($row['roll_no']); ?></span></td>
                        <td class="fw-bold"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo sanitize($row['class_name'] ?: $row['school_class'] ?: '—'); ?></td>
                        <td><?php echo sanitize($row['section'] ?: $row['school_section'] ?: '—'); ?></td>
                        <td>
                            <?php
                            $status = $row['status'];
                            $badge = 'bg-secondary';
                            if ($status === 'Present') $badge = 'bg-success';
                            elseif ($status === 'Absent') $badge = 'bg-danger';
                            elseif ($status === 'Late') $badge = 'bg-warning text-dark';
                            elseif ($status === 'Leave') $badge = 'bg-info text-dark';
                            ?>
                            <span class="badge <?php echo $badge; ?>"><?php echo sanitize($status); ?></span>
                        </td>
                        <td><?php echo displayValue($row['remarks']); ?></td>
                        <td><span class="small text-muted"><i class="fa-solid fa-clock me-1 text-primary"></i><?php echo date('h:i A', strtotime($row['created_at'])); ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination layout -->
<?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation" class="mb-4 d-print-none">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&date=<?php echo $filter_date; ?><?php echo $filter_academic_type ? '&academic_type='.$filter_academic_type : ''; ?><?php echo $filter_class ? '&class='.$filter_class : ''; ?><?php echo $filter_section ? '&section='.$filter_section : ''; ?><?php echo $filter_status ? '&status='.$filter_status : ''; ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&date=<?php echo $filter_date; ?><?php echo $filter_academic_type ? '&academic_type='.$filter_academic_type : ''; ?><?php echo $filter_class ? '&class='.$filter_class : ''; ?><?php echo $filter_section ? '&section='.$filter_section : ''; ?><?php echo $filter_status ? '&status='.$filter_status : ''; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&date=<?php echo $filter_date; ?><?php echo $filter_academic_type ? '&academic_type='.$filter_academic_type : ''; ?><?php echo $filter_class ? '&class='.$filter_class : ''; ?><?php echo $filter_section ? '&section='.$filter_section : ''; ?><?php echo $filter_status ? '&status='.$filter_status : ''; ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<!-- Print Report Footer (Visible in print layout ONLY) -->
<div class="d-none d-print-block text-center mt-5 border-top pt-3">
    <span class="small text-muted">Page 1 of 1 | Indus Grammar School ERP System © <?php echo date('Y'); ?></span>
</div>

<script>
// Search button spinner
document.getElementById('searchForm').addEventListener('submit', function() {
    const btn = document.getElementById('searchBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Searching...';
});

// CSV Export logic
function exportCSV() {
    let csv = "Admission No,Roll No,Student Name,Class,Section,Status,Remarks,Time\n";
    <?php if (!empty($records)): foreach ($records as $row): ?>
        csv += "<?php echo $row['admission_no']; ?>,<?php echo $row['roll_no']; ?>,<?php echo $row['first_name'] . ' ' . $row['last_name']; ?>,<?php echo $row['class_name'] ?: $row['school_class']; ?>,<?php echo $row['section'] ?: $row['school_section']; ?>,<?php echo $row['status']; ?>,<?php echo str_replace('"', '""', $row['remarks']); ?>,<?php echo date('h:i A', strtotime($row['created_at'])); ?>\n";
    <?php endforeach; endif; ?>

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "daily_attendance_report_" + new Date().toISOString().slice(0,10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
