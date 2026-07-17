<?php
/**
 * Indus Grammar School ERP - Attendance Register Submodule
 * Version 3.0.0
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('attendance_view');

$db = Database::getConnection();

// Retrieve filters
$search_admission = sanitize($_GET['search_admission'] ?? '');
$search_name = sanitize($_GET['search_name'] ?? '');
$search_academic_type = sanitize($_GET['search_academic_type'] ?? '');
$search_class = sanitize($_GET['search_class'] ?? '');
$search_section = sanitize($_GET['search_section'] ?? '');
$from_date = sanitize($_GET['from_date'] ?? date('Y-m-01'));
$to_date = sanitize($_GET['to_date'] ?? date('Y-m-d'));
$view_student_id = (int)($_GET['view_student_id'] ?? 0);

// Look up target student if view_student_id or exact admission is selected to render the Profile Summary
$profileStudent = null;
$studentStats = [];

if ($view_student_id > 0 || !empty($search_admission)) {
    try {
        $stQuery = "
            SELECT s.id, s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section,
                   c.class_name, c.section, d.doc_student_photo
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN student_registration_details d ON s.id = d.student_id
            WHERE 1=1
        ";
        if ($view_student_id > 0) {
            $stQuery .= " AND s.id = ?";
            $stStmt = $db->prepare($stQuery);
            $stStmt->execute([$view_student_id]);
        } else {
            $stQuery .= " AND s.admission_no = ?";
            $stStmt = $db->prepare($stQuery);
            $stStmt->execute([$search_admission]);
        }
        $profileStudent = $stStmt->fetch(PDO::FETCH_ASSOC);

        if ($profileStudent) {
            // Calculate stats for this student
            $statStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(status = 'Present') as present,
                    SUM(status = 'Absent') as absent,
                    SUM(status = 'Leave') as leave_days,
                    SUM(status = 'Late') as late
                FROM attendance
                WHERE student_id = ? AND date BETWEEN ? AND ?
            ");
            $statStmt->execute([$profileStudent['id'], $from_date, $to_date]);
            $studentStats = $statStmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}
}

$limit = 10;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Build query conditions for logs
$where = " WHERE 1=1";
$params = [];

if ($view_student_id > 0) {
    $where .= " AND a.student_id = :view_student_id";
    $params['view_student_id'] = $view_student_id;
} else {
    if ($search_admission !== '') {
        $where .= " AND s.admission_no = :admission";
        $params['admission'] = $search_admission;
    }
    if ($search_name !== '') {
        $where .= " AND (s.first_name LIKE :name OR s.last_name LIKE :name)";
        $params['name'] = '%' . $search_name . '%';
    }
    if ($search_academic_type !== '') {
        $where .= " AND s.academic_type = :academic_type";
        $params['academic_type'] = $search_academic_type;
    }
    if ($search_class !== '') {
        $where .= " AND (c.class_name = :class1 OR s.school_class = :class2)";
        $params['class1'] = $search_class;
        $params['class2'] = $search_class;
    }
    if ($search_section !== '') {
        $where .= " AND (c.section = :section1 OR s.school_section = :section2)";
        $params['section1'] = $search_section;
        $params['section2'] = $search_section;
    }
}

if ($from_date !== '' && $to_date !== '') {
    $where .= " AND a.date BETWEEN :from_date AND :to_date";
    $params['from_date'] = $from_date;
    $params['to_date'] = $to_date;
}

$logs = [];
$totalEntries = 0;

try {
    // Count query
    $stmtCount = $db->prepare("
        SELECT COUNT(*) 
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN classes c ON a.class_id = c.id
        $where
    ");
    $stmtCount->execute($params);
    $totalEntries = (int)$stmtCount->fetchColumn();

    // Data query
    $stmtData = $db->prepare("
        SELECT a.*, s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section,
               c.class_name, c.section, d.doc_student_photo, d.roll_no
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY a.date DESC, s.admission_no ASC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmtData->bindValue($k, $v);
    }
    $stmtData->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();

    $logs = $stmtData->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Attendance register querying error: " . $e->getMessage());
}

$totalPages = ceil($totalEntries / $limit);
if ($totalPages < 1) $totalPages = 1;

// Unique classes and sections lookup lists
$classesList = [];
try {
    $classesList = $db->query("SELECT DISTINCT class_name FROM classes ORDER BY class_name ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$sectionsList = ['A', 'B', 'C', 'D', 'E'];
try {
    $dbSecs = $db->query("SELECT DISTINCT section FROM classes WHERE section != '' ORDER BY section ASC")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dbSecs as $ds) {
        if (!in_array($ds, $sectionsList)) {
            $sectionsList[] = $ds;
        }
    }
} catch (Exception $e) {}

// Layout Header
$pageTitle = 'Attendance Register';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Title Banner -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-book me-2 text-primary"></i>Attendance Register</h3>
    </div>
</div>

<!-- Filters Panel (Hidden in print) -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Search Attendance History</h6>
    <form method="GET" action="reports.php" id="searchForm" class="row g-3">
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Admission No.</label>
            <input type="text" class="form-control form-control-sm" name="search_admission" value="<?php echo htmlspecialchars($search_admission); ?>" placeholder="e.g. ADM-2026-0001">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Student Name</label>
            <input type="text" class="form-control form-control-sm" name="search_name" value="<?php echo htmlspecialchars($search_name); ?>" placeholder="Enter Name">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Academic Type</label>
            <select class="form-select form-select-sm" name="search_academic_type">
                <option value="">All</option>
                <option value="School" <?php echo ($search_academic_type === 'School') ? 'selected' : ''; ?>>School</option>
                <option value="Academy" <?php echo ($search_academic_type === 'Academy') ? 'selected' : ''; ?>>Academy</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Class</label>
            <select class="form-select form-select-sm" name="search_class">
                <option value="">All Classes</option>
                <?php foreach ($classesList as $cls): ?>
                    <option value="<?php echo $cls; ?>" <?php echo ($search_class === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Section</label>
            <select class="form-select form-select-sm" name="search_section">
                <option value="">All</option>
                <?php foreach ($sectionsList as $sec): ?>
                    <option value="<?php echo $sec; ?>" <?php echo ($search_section === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">From Date</label>
            <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo $from_date; ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">To Date</label>
            <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo $to_date; ?>">
        </div>
        
        <div class="col-md-10 text-end align-self-end mt-4">
            <button type="submit" id="searchBtn" class="btn btn-sm btn-primary px-4"><i class="fa-solid fa-search me-2"></i>Search</button>
            <a href="reports.php" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Print Header (Visible on print layout ONLY) -->
<div class="d-none d-print-block text-center mb-4">
    <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
    <h5 class="text-secondary mb-3">Attendance History Dossier Register</h5>
    <div class="small text-muted py-2 border-top border-bottom">
        Date range: <strong><?php echo date('M d, Y', strtotime($from_date)); ?> to <?php echo date('M d, Y', strtotime($to_date)); ?></strong>
    </div>
</div>

<!-- 3. Student Profile Summary card -->
<?php if ($profileStudent): 
    $tot = (int)($studentStats['total'] ?? 0);
    $pres = (int)($studentStats['present'] ?? 0);
    $abs = (int)($studentStats['absent'] ?? 0);
    $lve = (int)($studentStats['leave_days'] ?? 0);
    $lat = (int)($studentStats['late'] ?? 0);
    
    $perc = 0.0;
    if ($tot > 0) {
        $perc = round((($pres + $lat) / $tot) * 100, 1);
    }
?>
    <div class="card border border-light shadow-sm bg-white p-4 mb-4" style="border-radius:12px; border-left: 5px solid #1e3a8a !important;">
        <div class="row align-items-center">
            <div class="col-md-2 text-center border-end">
                <div class="avatar-medium border rounded-circle mx-auto mb-2 bg-light d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; overflow:hidden;">
                    <?php if (!empty($profileStudent['doc_student_photo'])): ?>
                        <img src="<?php echo APP_URL . '/' . $profileStudent['doc_student_photo']; ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <i class="fa-solid fa-user fs-2 text-muted"></i>
                    <?php endif; ?>
                </div>
                <h6 class="fw-bold text-dark mb-0"><?php echo sanitize($profileStudent['first_name'] . ' ' . $profileStudent['last_name']); ?></h6>
                <span class="small text-muted font-monospace"><?php echo sanitize($profileStudent['admission_no']); ?></span>
            </div>
            
            <div class="col-md-10">
                <div class="row text-center text-md-start g-3 mt-1">
                    <div class="col-6 col-md-3">
                        <span class="text-muted small d-block">Academic Placement</span>
                        <strong class="text-dark"><?php echo sanitize($profileStudent['class_name'] ?? $profileStudent['school_class'] ?? '—') . ' - ' . sanitize($profileStudent['section'] ?? $profileStudent['school_section'] ?? 'A'); ?></strong>
                        <br><span class="badge bg-secondary mt-1"><?php echo sanitize($profileStudent['academic_type']); ?></span>
                    </div>
                    <div class="col-6 col-md-2">
                        <span class="text-muted small d-block">Attendance %</span>
                        <h4 class="fw-bold text-primary mb-0"><?php echo $perc; ?>%</h4>
                    </div>
                    <div class="col-4 col-md-2">
                        <span class="text-muted small d-block">Present Days</span>
                        <h4 class="fw-bold text-success mb-0"><?php echo $pres; ?></h4>
                    </div>
                    <div class="col-4 col-md-2">
                        <span class="text-muted small d-block">Absent Days</span>
                        <h4 class="fw-bold text-danger mb-0"><?php echo $abs; ?></h4>
                    </div>
                    <div class="col-4 col-md-2">
                        <span class="text-muted small d-block">Leave Days</span>
                        <h4 class="fw-bold text-secondary mb-0"><?php echo $lve; ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Attendance History Log Table Card -->
<div class="card border-0 shadow-sm bg-white mb-4" style="border-radius:12px; overflow:hidden;">
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th width="80">Photo</th>
                    <th>Admission Number</th>
                    <th>Student Name</th>
                    <th>Class</th>
                    <th>Section</th>
                    <th>Attendance Date</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th class="text-end d-print-none">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">No attendance logs found.</td>
                    </tr>
                <?php else: foreach ($logs as $row): ?>
                    <tr>
                        <td>
                            <div class="avatar-small border rounded-circle d-flex align-items-center justify-content-center bg-light" style="width: 38px; height: 38px; overflow:hidden;">
                                <?php if (!empty($row['doc_student_photo'])): ?>
                                    <img src="<?php echo APP_URL . '/' . $row['doc_student_photo']; ?>" style="width:100%; height:100%; object-fit:cover;">
                                <?php else: ?>
                                    <i class="fa-solid fa-user text-muted small"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><strong class="text-primary font-monospace"><?php echo sanitize($row['admission_no']); ?></strong></td>
                        <td class="fw-bold"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo sanitize($row['class_name'] ?: $row['school_class'] ?: '—'); ?></td>
                        <td><?php echo sanitize($row['section'] ?: $row['school_section'] ?: '—'); ?></td>
                        <td><strong><?php echo date('M d, Y', strtotime($row['date'])); ?></strong></td>
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
                        <td class="text-end d-print-none">
                            <div class="btn-group">
                                <a href="reports.php?view_student_id=<?php echo $row['student_id']; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" class="btn btn-outline-secondary btn-sm" title="View Summary"><i class="fa-regular fa-eye"></i></a>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printRowReport(<?php echo htmlspecialchars(json_encode($row)); ?>)" title="Print Row"><i class="fa-solid fa-print"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination (Hidden in print) -->
<?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation" class="mb-4 d-print-none">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?><?php echo $search_admission ? '&search_admission='.$search_admission : ''; ?><?php echo $search_name ? '&search_name='.$search_name : ''; ?><?php echo $search_academic_type ? '&search_academic_type='.$search_academic_type : ''; ?><?php echo $search_class ? '&search_class='.$search_class : ''; ?><?php echo $search_section ? '&search_section='.$search_section : ''; ?><?php echo $view_student_id ? '&view_student_id='.$view_student_id : ''; ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?><?php echo $search_admission ? '&search_admission='.$search_admission : ''; ?><?php echo $search_name ? '&search_name='.$search_name : ''; ?><?php echo $search_academic_type ? '&search_academic_type='.$search_academic_type : ''; ?><?php echo $search_class ? '&search_class='.$search_class : ''; ?><?php echo $search_section ? '&search_section='.$search_section : ''; ?><?php echo $view_student_id ? '&view_student_id='.$view_student_id : ''; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?><?php echo $search_admission ? '&search_admission='.$search_admission : ''; ?><?php echo $search_name ? '&search_name='.$search_name : ''; ?><?php echo $search_academic_type ? '&search_academic_type='.$search_academic_type : ''; ?><?php echo $search_class ? '&search_class='.$search_class : ''; ?><?php echo $search_section ? '&search_section='.$search_section : ''; ?><?php echo $view_student_id ? '&view_student_id='.$view_student_id : ''; ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<!-- Print Report Footer (Visible in print layout ONLY) -->
<div class="d-none d-print-block mt-5 text-center border-top pt-3">
    <span class="small text-muted">Page 1 of 1 | Indus Grammar School ERP System © <?php echo date('Y'); ?></span>
</div>

<script>
// Search loading spinner activation
document.getElementById('searchForm').addEventListener('submit', function() {
    const btn = document.getElementById('searchBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Searching...';
});

// Print single row detail page
function printRowReport(row) {
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <div style="padding:40px; font-family: sans-serif; background-color: #fff;">
            <h2 style="text-align:center; margin-bottom: 2px;">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
            <h4 style="text-align:center; color: #555; border-bottom: 2px solid #333; padding-bottom: 10px; margin-top: 0;">Student Daily Attendance Voucher</h4>
            <div style="margin-top:20px; line-height: 1.8;">
                <p>Admission Number: <strong>${row.admission_no}</strong></p>
                <p>Student Name: <strong>${row.first_name} ${row.last_name}</strong></p>
                <p>Class & Section: <strong>${row.class_name ? row.class_name : (row.school_class ? row.school_class : '—')} - ${row.section ? row.section : (row.school_section ? row.school_section : '—')}</strong></p>
                <p>Attendance Date: <strong>${row.date}</strong></p>
                <p>Attendance Status: <strong>${row.status}</strong></p>
                <p>Remarks: <strong>${row.remarks ? row.remarks : '—'}</strong></p>
                <p>Logged Time: <strong>${new Date(row.created_at).toLocaleString()}</strong></p>
            </div>
            <div style="margin-top: 60px; text-align: right;">
                <span style="border-top: 1px solid #777; padding-top: 5px; font-weight: bold;">Authorized Administrator Signature</span>
            </div>
        </div>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload();
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
