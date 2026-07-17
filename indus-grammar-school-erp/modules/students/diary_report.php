<?php
/**
 * Indus Grammar School ERP - Diary Report (Consolidated & Printable)
 * Version 3.0.0
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

// 2. Retrieve Filter Parameters
$from_date = sanitize($_GET['from_date'] ?? '');
$to_date = sanitize($_GET['to_date'] ?? '');
$filter_academic_type = sanitize($_GET['filter_academic_type'] ?? '');
$filter_class = sanitize($_GET['filter_class'] ?? '');
$filter_section = sanitize($_GET['filter_section'] ?? '');
$filter_subject = sanitize($_GET['filter_subject'] ?? '');
$filter_diary_type = sanitize($_GET['filter_diary_type'] ?? '');
$filter_status = sanitize($_GET['filter_status'] ?? '');

$limit = 10;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Build query conditions
$where = " WHERE 1=1";
$params = [];

if ($from_date !== '') {
    $where .= " AND d.diary_date >= :from_date";
    $params['from_date'] = $from_date;
}
if ($to_date !== '') {
    $where .= " AND d.diary_date <= :to_date";
    $params['to_date'] = $to_date;
}
if ($filter_academic_type !== '') {
    $where .= " AND d.academic_type = :filter_academic_type";
    $params['filter_academic_type'] = $filter_academic_type;
}
if ($filter_class !== '') {
    $where .= " AND d.class = :filter_class";
    $params['filter_class'] = $filter_class;
}
if ($filter_section !== '') {
    $where .= " AND d.section = :filter_section";
    $params['filter_section'] = $filter_section;
}
if ($filter_subject !== '') {
    $where .= " AND d.subject LIKE :filter_subject";
    $params['filter_subject'] = '%' . $filter_subject . '%';
}
if ($filter_diary_type !== '') {
    $where .= " AND d.diary_type = :filter_diary_type";
    $params['filter_diary_type'] = $filter_diary_type;
}
if ($filter_status !== '') {
    $where .= " AND d.status = :filter_status";
    $params['filter_status'] = $filter_status;
}

$diaries = [];
$totalEntries = 0;

// 3. Dynamic Summary counts based on active filters
$todayCount = 0;
$homeworkCount = 0;
$assignmentCount = 0;
$noticeCount = 0;

try {
    // Total count query
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM daily_diaries d $where");
    $stmtCount->execute($params);
    $totalEntries = (int)$stmtCount->fetchColumn();

    // Today count query (matching active filters)
    $stmtToday = $db->prepare("SELECT COUNT(*) FROM daily_diaries d $where AND d.diary_date = CURRENT_DATE");
    $stmtToday->execute($params);
    $todayCount = (int)$stmtToday->fetchColumn();

    // Homework count query (matching active filters)
    $stmtHomework = $db->prepare("SELECT COUNT(*) FROM daily_diaries d $where AND d.diary_type = 'Homework'");
    $stmtHomework->execute($params);
    $homeworkCount = (int)$stmtHomework->fetchColumn();

    // Assignment count query (matching active filters)
    $stmtAssignment = $db->prepare("SELECT COUNT(*) FROM daily_diaries d $where AND d.diary_type = 'Assignment'");
    $stmtAssignment->execute($params);
    $assignmentCount = (int)$stmtAssignment->fetchColumn();

    // Notice count query (matching active filters)
    $stmtNotice = $db->prepare("SELECT COUNT(*) FROM daily_diaries d $where AND d.diary_type = 'General Notice'");
    $stmtNotice->execute($params);
    $noticeCount = (int)$stmtNotice->fetchColumn();

    // Main records query
    $stmtData = $db->prepare("
        SELECT d.*, u.username as creator_name 
        FROM daily_diaries d 
        LEFT JOIN users u ON d.created_by = u.id 
        $where 
        ORDER BY d.diary_date DESC, d.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($params as $k => $v) {
        $stmtData->bindValue($k, $v);
    }
    $stmtData->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    
    $diaries = $stmtData->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Diary Report main queries error: " . $e->getMessage());
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

// Setup Layout Header
$pageTitle = 'Diary Report Panel';
$breadcrumbActive = 'Diary Report';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Custom CSS for Printable Report Styling -->
<style>
@media print {
    /* Hide layout containers */
    .left-sidebar, .header-navbar, .d-print-none, .breadcrumb-card, .footer-container {
        display: none !important;
    }
    body, .main-content-container, .card, .card-body {
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
    }
    .table-responsive {
        overflow: visible !important;
    }
    .custom-table {
        width: 100% !important;
        border-collapse: collapse !important;
    }
    .custom-table th, .custom-table td {
        border: 1px solid #dee2e6 !important;
        padding: 6px !important;
    }
}
</style>

<!-- Title banner header -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-chart-line me-2 text-primary"></i>Diary Report Analysis</h3>
    </div>
</div>

<!-- Summary Cards Section -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm text-center h-100 bg-white" style="border-radius:12px; border-left: 4px solid #0056b3 !important;">
            <div class="card-body p-3">
                <span class="text-muted small d-block mb-1">Total Entries</span>
                <h3 class="fw-bold text-dark mb-0"><?php echo $totalEntries; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm text-center h-100 bg-white" style="border-radius:12px; border-left: 4px solid #28a745 !important;">
            <div class="card-body p-3">
                <span class="text-muted small d-block mb-1">Today's Diaries</span>
                <h3 class="fw-bold text-dark mb-0"><?php echo $todayCount; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm text-center h-100 bg-white" style="border-radius:12px; border-left: 4px solid #17a2b8 !important;">
            <div class="card-body p-3">
                <span class="text-muted small d-block mb-1">Homework</span>
                <h3 class="fw-bold text-dark mb-0"><?php echo $homeworkCount; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm text-center h-100 bg-white" style="border-radius:12px; border-left: 4px solid #ffc107 !important;">
            <div class="card-body p-3">
                <span class="text-muted small d-block mb-1">Assignments</span>
                <h3 class="fw-bold text-dark mb-0"><?php echo $assignmentCount; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm text-center h-100 bg-white" style="border-radius:12px; border-left: 4px solid #dc3545 !important;">
            <div class="card-body p-3">
                <span class="text-muted small d-block mb-1">Notices</span>
                <h3 class="fw-bold text-dark mb-0"><?php echo $noticeCount; ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Filters Panel Card -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Diary Entries</h6>
    <form method="GET" action="diary_report.php" id="filterForm" class="row g-3">
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">From Date</label>
            <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">To Date</label>
            <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Academic Type</label>
            <select class="form-select form-select-sm" name="filter_academic_type">
                <option value="">All Types</option>
                <option value="School" <?php echo ($filter_academic_type === 'School') ? 'selected' : ''; ?>>School</option>
                <option value="Academy" <?php echo ($filter_academic_type === 'Academy') ? 'selected' : ''; ?>>Academy</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Class</label>
            <select class="form-select form-select-sm" name="filter_class">
                <option value="">All Classes</option>
                <?php foreach (['Play Group', 'Nursery', 'Prep', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10', 'Class 11', 'Class 12'] as $cls): ?>
                    <option value="<?php echo $cls; ?>" <?php echo ($filter_class === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Section</label>
            <select class="form-select form-select-sm" name="filter_section">
                <option value="">All Sections</option>
                <?php foreach ($sectionsList as $sec): ?>
                    <option value="<?php echo $sec; ?>" <?php echo ($filter_section === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Subject</label>
            <input type="text" class="form-control form-control-sm" name="filter_subject" value="<?php echo htmlspecialchars($filter_subject); ?>" placeholder="e.g. Mathematics">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Diary Type</label>
            <select class="form-select form-select-sm" name="filter_diary_type">
                <option value="">All Categories</option>
                <option value="Homework" <?php echo ($filter_diary_type === 'Homework') ? 'selected' : ''; ?>>Homework</option>
                <option value="Classwork" <?php echo ($filter_diary_type === 'Classwork') ? 'selected' : ''; ?>>Classwork</option>
                <option value="Assignment" <?php echo ($filter_diary_type === 'Assignment') ? 'selected' : ''; ?>>Assignment</option>
                <option value="Test Reminder" <?php echo ($filter_diary_type === 'Test Reminder') ? 'selected' : ''; ?>>Test Reminder</option>
                <option value="General Notice" <?php echo ($filter_diary_type === 'General Notice') ? 'selected' : ''; ?>>General Notice</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Status</label>
            <select class="form-select form-select-sm" name="filter_status">
                <option value="">All</option>
                <option value="Active" <?php echo ($filter_status === 'Active') ? 'selected' : ''; ?>>Active</option>
                <option value="Draft" <?php echo ($filter_status === 'Draft') ? 'selected' : ''; ?>>Draft</option>
            </select>
        </div>
        
        <div class="col-12 text-end mt-4">
            <button type="submit" id="searchBtn" class="btn btn-sm btn-primary px-4"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
            <a href="diary_report.php" class="btn btn-sm btn-outline-secondary px-3">Reset</a>
            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Report</button>
            <button type="button" class="btn btn-sm btn-outline-danger px-3" onclick="window.print()"><i class="fa-solid fa-file-pdf me-2"></i>Export PDF</button>
            <button type="button" class="btn btn-sm btn-outline-success px-3" onclick="exportExcel()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
        </div>
    </form>
</div>

<!-- Report Printing Header (Visible ONLY on print layout) -->
<div class="d-none d-print-block text-center mb-4 border-bottom pb-3">
    <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
    <h5 class="text-secondary mb-3">Daily Diaries & Homework Log Report</h5>
    <div class="small text-muted py-2">
        Report Generated: <strong><?php echo date('M d, Y h:i A'); ?></strong>
        <?php if ($from_date || $to_date): ?> | Date Range: <strong><?php echo $from_date ?: 'Start'; ?> to <?php echo $to_date ?: 'End'; ?></strong><?php endif; ?>
        <?php if ($filter_class): ?> | Class: <strong><?php echo htmlspecialchars($filter_class); ?></strong><?php endif; ?>
        <?php if ($filter_section): ?> | Section: <strong><?php echo htmlspecialchars($filter_section); ?></strong><?php endif; ?>
        <?php if ($filter_subject): ?> | Subject: <strong><?php echo htmlspecialchars($filter_subject); ?></strong><?php endif; ?>
    </div>
</div>

<!-- Diary Report Records Table -->
<div class="card border-0 shadow-sm bg-white" style="border-radius: 12px; overflow: hidden;">
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Diary Date</th>
                    <th>Academic Type</th>
                    <th>Class</th>
                    <th>Section</th>
                    <th>Subject</th>
                    <th>Diary Type</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Created Date</th>
                    <th class="text-end d-print-none">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($diaries)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-folder-open d-block fs-3 mb-2 text-secondary opacity-50"></i>
                            No diary records found.
                        </td>
                    </tr>
                <?php else: foreach ($diaries as $row): ?>
                    <tr>
                        <td><strong class="text-primary"><?php echo date('M d, Y', strtotime($row['diary_date'])); ?></strong></td>
                        <td><?php echo sanitize($row['academic_type']); ?></td>
                        <td><?php echo displayValue($row['class']); ?></td>
                        <td><?php echo displayValue($row['section']); ?></td>
                        <td><strong class="text-dark"><?php echo displayValue($row['subject']); ?></strong></td>
                        <td>
                            <?php
                            $type = $row['diary_type'];
                            $badge = 'bg-secondary';
                            if ($type === 'Homework') $badge = 'bg-primary';
                            elseif ($type === 'Assignment') $badge = 'bg-info text-dark';
                            elseif ($type === 'Test Reminder') $badge = 'bg-warning text-dark';
                            elseif ($type === 'General Notice') $badge = 'bg-danger';
                            ?>
                            <span class="badge <?php echo $badge; ?>"><?php echo sanitize($type); ?></span>
                        </td>
                        <td><?php echo sanitize($row['title']); ?></td>
                        <td>
                            <span class="badge <?php echo ($row['status'] === 'Active') ? 'badge-soft-success' : 'bg-light text-secondary border'; ?>">
                                <?php echo sanitize($row['status']); ?>
                            </span>
                        </td>
                        <td><span class="small text-muted"><i class="fa-solid fa-user me-1"></i><?php echo displayValue($row['creator_name']); ?></span></td>
                        <td><span class="small text-muted"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></span></td>
                        <td class="text-end d-print-none">
                            <div class="btn-group">
                                <a href="view_diary.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-secondary btn-sm" title="View Details">
                                    <i class="fa-regular fa-eye"></i>
                                </a>
                                <a href="print_diary.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-outline-primary btn-sm" title="Print Entry">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination layout -->
<?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4 mb-4 d-print-none">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $from_date ? '&from_date='.$from_date : ''; ?><?php echo $to_date ? '&to_date='.$to_date : ''; ?><?php echo $filter_academic_type ? '&filter_academic_type='.$filter_academic_type : ''; ?><?php echo $filter_class ? '&filter_class='.$filter_class : ''; ?><?php echo $filter_section ? '&filter_section='.$filter_section : ''; ?><?php echo $filter_subject ? '&filter_subject='.$filter_subject : ''; ?><?php echo $filter_diary_type ? '&filter_diary_type='.$filter_diary_type : ''; ?><?php echo $filter_status ? '&filter_status='.$filter_status : ''; ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $from_date ? '&from_date='.$from_date : ''; ?><?php echo $to_date ? '&to_date='.$to_date : ''; ?><?php echo $filter_academic_type ? '&filter_academic_type='.$filter_academic_type : ''; ?><?php echo $filter_class ? '&filter_class='.$filter_class : ''; ?><?php echo $filter_section ? '&filter_section='.$filter_section : ''; ?><?php echo $filter_subject ? '&filter_subject='.$filter_subject : ''; ?><?php echo $filter_diary_type ? '&filter_diary_type='.$filter_diary_type : ''; ?><?php echo $filter_status ? '&filter_status='.$filter_status : ''; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $from_date ? '&from_date='.$from_date : ''; ?><?php echo $to_date ? '&to_date='.$to_date : ''; ?><?php echo $filter_academic_type ? '&filter_academic_type='.$filter_academic_type : ''; ?><?php echo $filter_class ? '&filter_class='.$filter_class : ''; ?><?php echo $filter_section ? '&filter_section='.$filter_section : ''; ?><?php echo $filter_subject ? '&filter_subject='.$filter_subject : ''; ?><?php echo $filter_diary_type ? '&filter_diary_type='.$filter_diary_type : ''; ?><?php echo $filter_status ? '&filter_status='.$filter_status : ''; ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<!-- Report Printing Footer (Visible ONLY on print layout) -->
<div class="d-none d-print-block mt-5 text-center border-top pt-3">
    <span class="small text-muted">Page 1 of 1 | Indus Grammar School ERP System © <?php echo date('Y'); ?></span>
</div>

<!-- Modal View Details Panel -->
<div class="modal fade" id="viewDiaryModal" tabindex="-1" aria-labelledby="viewDiaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:12px;">
            <div class="modal-header bg-light border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="viewDiaryModalLabel"><i class="fa-solid fa-book-open text-primary me-2"></i>Diary Entry Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="row g-3" id="printableSingleArea">
                    <div class="col-md-6"><span class="text-muted small d-block">Diary Date</span><strong class="text-dark" id="v-date"></strong></div>
                    <div class="col-md-6"><span class="text-muted small d-block">Academic Type</span><strong class="text-dark" id="v-type"></strong></div>
                    <div class="col-md-4"><span class="text-muted small d-block">Class</span><strong class="text-dark" id="v-class"></strong></div>
                    <div class="col-md-4"><span class="text-muted small d-block">Section</span><strong class="text-dark" id="v-section"></strong></div>
                    <div class="col-md-4"><span class="text-muted small d-block">Subject</span><strong class="text-dark" id="v-subject"></strong></div>
                    <div class="col-md-4"><span class="text-muted small d-block">Diary Type</span><span class="badge bg-primary" id="v-category"></span></div>
                    <div class="col-md-4"><span class="text-muted small d-block">Status</span><span class="badge" id="v-status"></span></div>
                    <div class="col-md-4"><span class="text-muted small d-block">Created By</span><strong class="text-muted" id="v-author"></strong></div>
                    <div class="col-md-6"><span class="text-muted small d-block">Created Date</span><span class="text-dark" id="v-created"></span></div>
                    <div class="col-md-6"><span class="text-muted small d-block">Last Updated</span><span class="text-dark" id="v-updated"></span></div>
                    <div class="col-12"><span class="text-muted small d-block mb-1">Title</span><strong class="text-dark fs-5" id="v-title"></strong></div>
                    <div class="col-12">
                        <span class="text-muted small d-block mb-1">Description</span>
                        <div class="p-3 border rounded bg-light" id="v-desc" style="min-height: 100px; max-height: 400px; overflow-y: auto;"></div>
                    </div>
                    <div class="col-12" id="v-attachment-row">
                        <span class="text-muted small d-block mb-1">Attachment File</span>
                        <a href="#" target="_blank" class="btn btn-sm btn-outline-primary" id="v-attachment-link"><i class="fa-solid fa-paperclip me-2"></i>Download File</a>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" onclick="printSingleArea()"><i class="fa-solid fa-print me-2"></i>Print Entry</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Search loading spinner activation
document.getElementById('filterForm').addEventListener('submit', function() {
    const btn = document.getElementById('searchBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Searching...';
});

const viewModal = new bootstrap.Modal(document.getElementById("viewDiaryModal"));
let activeDiaryObject = null;

function viewDiaryDetails(d) {
    activeDiaryObject = d;
    document.getElementById("v-date").textContent = d.diary_date;
    document.getElementById("v-type").textContent = d.academic_type;
    document.getElementById("v-class").textContent = d.class;
    document.getElementById("v-section").textContent = d.section;
    document.getElementById("v-subject").textContent = d.subject;
    document.getElementById("v-title").textContent = d.title;
    document.getElementById("v-author").textContent = d.creator_name ? d.creator_name : 'System';
    document.getElementById("v-created").textContent = d.created_at;
    document.getElementById("v-updated").textContent = d.updated_at;
    
    // Category badge
    const cat = document.getElementById("v-category");
    cat.textContent = d.diary_type;
    cat.className = "badge " + (d.diary_type === 'Homework' ? 'bg-primary' : (d.diary_type === 'General Notice' ? 'bg-danger' : 'bg-info text-dark'));

    // Status badge
    const stat = document.getElementById("v-status");
    stat.textContent = d.status;
    stat.className = "badge " + (d.status === 'Active' ? 'bg-success' : 'bg-secondary');

    // Description HTML inject
    document.getElementById("v-desc").innerHTML = d.description;

    // Attachment row
    const attRow = document.getElementById("v-attachment-row");
    if (d.attachment) {
        attRow.style.display = "block";
        document.getElementById("v-attachment-link").href = "<?php echo APP_URL; ?>/" + d.attachment;
    } else {
        attRow.style.display = "none";
    }

    viewModal.show();
}

// Print single entry from modal
function printSingleArea() {
    const printContent = document.getElementById("printableSingleArea").innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <div style="padding:40px; font-family: sans-serif;">
            <h2 style="text-align:center; margin-bottom: 2px;">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
            <h4 style="text-align:center; color: #555; border-bottom: 2px solid #333; padding-bottom: 10px; margin-top: 0;">Daily Diary Task Details</h4>
            ${printContent}
        </div>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload(); // Reload to restore JavaScript click bindings
}

// Print single entry from action row directly
function printSingleDiary(d) {
    viewDiaryDetails(d);
    setTimeout(printSingleArea, 300);
}

// CSV / Excel exporter logic
function exportExcel() {
    let csv = "Diary Date,Academic Type,Class,Section,Subject,Diary Type,Title,Status,Created By,Created Date\n";
    <?php if (!empty($diaries)): foreach ($diaries as $row): ?>
        csv += "<?php echo $row['diary_date']; ?>,<?php echo $row['academic_type']; ?>,<?php echo $row['class']; ?>,<?php echo $row['section']; ?>,<?php echo $row['subject']; ?>,<?php echo $row['diary_type']; ?>,<?php echo $row['title']; ?>,<?php echo $row['status']; ?>,<?php echo $row['creator_name'] ?: 'System'; ?>,<?php echo $row['created_at']; ?>\n";
    <?php endforeach; endif; ?>

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "daily_diary_report_" + new Date().toISOString().slice(0,10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
