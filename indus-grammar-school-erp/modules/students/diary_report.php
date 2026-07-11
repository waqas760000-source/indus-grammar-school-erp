<?php
/**
 * Indus Grammar School ERP - Daily Diary Report (Printable & Filterable)
 * Version 2.0.0
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

// Filter parameters
$filter_date = sanitize($_GET['filter_date'] ?? '');
$filter_academic_type = sanitize($_GET['filter_academic_type'] ?? '');
$filter_class = sanitize($_GET['filter_class'] ?? '');
$filter_section = sanitize($_GET['filter_section'] ?? '');
$filter_subject = sanitize($_GET['filter_subject'] ?? '');
$filter_diary_type = sanitize($_GET['filter_diary_type'] ?? '');
$filter_status = sanitize($_GET['filter_status'] ?? '');

// Unique sections from classes database
$sectionsList = ['A', 'B', 'C', 'D', 'E'];
try {
    $dbSecs = $db->query("SELECT DISTINCT section FROM classes WHERE section != '' ORDER BY section ASC")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dbSecs as $ds) {
        if (!in_array($ds, $sectionsList)) {
            $sectionsList[] = $ds;
        }
    }
} catch (Exception $e) {}

// Query diary entries
$diaries = [];
try {
    $sql = "
        SELECT d.*, u.username as teacher_name
        FROM daily_diaries d
        LEFT JOIN users u ON d.created_by = u.id
        WHERE 1=1
    ";
    $params = [];

    if ($filter_date !== '') {
        $sql .= " AND d.diary_date = :filter_date";
        $params['filter_date'] = $filter_date;
    }
    if ($filter_academic_type !== '') {
        $sql .= " AND d.academic_type = :filter_academic_type";
        $params['filter_academic_type'] = $filter_academic_type;
    }
    if ($filter_class !== '') {
        $sql .= " AND d.class = :filter_class";
        $params['filter_class'] = $filter_class;
    }
    if ($filter_section !== '') {
        $sql .= " AND d.section = :filter_section";
        $params['filter_section'] = $filter_section;
    }
    if ($filter_subject !== '') {
        $sql .= " AND d.subject LIKE :filter_subject";
        $params['filter_subject'] = '%' . $filter_subject . '%';
    }
    if ($filter_diary_type !== '') {
        $sql .= " AND d.diary_type = :filter_diary_type";
        $params['filter_diary_type'] = $filter_diary_type;
    }
    if ($filter_status !== '') {
        $sql .= " AND d.status = :filter_status";
        $params['status'] = $filter_status;
    }

    $sql .= " ORDER BY d.diary_date DESC, d.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $diaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Diary Report query error: " . $e->getMessage());
}

$pageTitle = 'Diary Report';
$breadcrumbActive = 'Daily Diary';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Title Header -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-file-contract me-2 text-primary"></i>Diary Report</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-outline-secondary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Report</button>
        <button class="btn btn-outline-success px-3 ms-1" onclick="exportReportCSV()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
    </div>
</div>

<!-- Filters Panel (Hidden in Print Mode) -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Report Records</h6>
    <form method="GET" action="diary_report.php" class="row g-3 align-items-end">
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Diary Date</label>
            <input type="date" class="form-control form-control-sm" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Academic Type</label>
            <select class="form-select form-select-sm" name="filter_academic_type">
                <option value="">All Types</option>
                <option value="School" <?php echo ($filter_academic_type === 'School') ? 'selected' : ''; ?>>School</option>
                <option value="Academy" <?php echo ($filter_academic_type === 'Academy') ? 'selected' : ''; ?>>Academy</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Class</label>
            <select class="form-select form-select-sm" name="filter_class">
                <option value="">All Classes</option>
                <?php foreach (['Play Group', 'Nursery', 'Prep', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10', 'Class 11', 'Class 12'] as $cls): ?>
                    <option value="<?php echo $cls; ?>" <?php echo ($filter_class === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Section</label>
            <select class="form-select form-select-sm" name="filter_section">
                <option value="">All Sections</option>
                <?php foreach ($sectionsList as $sec): ?>
                    <option value="<?php echo $sec; ?>" <?php echo ($filter_section === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Subject</label>
            <input type="text" class="form-control form-control-sm" name="filter_subject" value="<?php echo htmlspecialchars($filter_subject); ?>" placeholder="e.g. Science">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Diary Type</label>
            <select class="form-select form-select-sm" name="filter_diary_type">
                <option value="">All Types</option>
                <option value="Homework" <?php echo ($filter_diary_type === 'Homework') ? 'selected' : ''; ?>>Homework</option>
                <option value="Classwork" <?php echo ($filter_diary_type === 'Classwork') ? 'selected' : ''; ?>>Classwork</option>
                <option value="Assignment" <?php echo ($filter_diary_type === 'Assignment') ? 'selected' : ''; ?>>Assignment</option>
                <option value="Test Reminder" <?php echo ($filter_diary_type === 'Test Reminder') ? 'selected' : ''; ?>>Test Reminder</option>
                <option value="General Notice" <?php echo ($filter_diary_type === 'General Notice') ? 'selected' : ''; ?>>General Notice</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Status</label>
            <select class="form-select form-select-sm" name="filter_status">
                <option value="">All</option>
                <option value="Active" <?php echo ($filter_status === 'Active') ? 'selected' : ''; ?>>Active</option>
                <option value="Draft" <?php echo ($filter_status === 'Draft') ? 'selected' : ''; ?>>Draft</option>
            </select>
        </div>
        <div class="col-md-10 text-end">
            <button type="submit" class="btn btn-sm btn-primary px-4"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
            <a href="diary_report.php" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Report Printing Header (Visible ONLY on print) -->
<div class="d-none d-print-block text-center mb-4">
    <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
    <h5 class="text-secondary mb-3">Daily Diaries & Homework Log Report</h5>
    <div class="small text-muted border-top border-bottom py-2">
        Report Generated: <strong><?php echo date('M d, Y h:i A'); ?></strong>
        <?php if ($filter_date): ?> | Date: <strong><?php echo date('M d, Y', strtotime($filter_date)); ?></strong><?php endif; ?>
        <?php if ($filter_class): ?> | Class: <strong><?php echo htmlspecialchars($filter_class); ?></strong><?php endif; ?>
        <?php if ($filter_section): ?> | Section: <strong><?php echo htmlspecialchars($filter_section); ?></strong><?php endif; ?>
    </div>
</div>

<!-- Report Table / Grid -->
<div class="card border-0 shadow-sm mb-4 bg-white" style="border-radius:12px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3 d-print-none"><i class="fa-solid fa-list me-2"></i>Report Records (<?php echo count($diaries); ?> found)</h6>
        
        <div class="table-responsive">
            <table class="table align-middle table-bordered text-dark small">
                <thead class="bg-light">
                    <tr>
                        <th width="100">Date</th>
                        <th width="120">Class & Section</th>
                        <th width="120">Subject</th>
                        <th width="120">Diary Type</th>
                        <th>Title / Tasks Details</th>
                        <th width="120" class="d-print-none">Status</th>
                        <th width="120">Created By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($diaries)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No diaries match the active filters.</td>
                        </tr>
                    <?php else: foreach ($diaries as $d): ?>
                        <tr>
                            <td><strong><?php echo date('M d, Y', strtotime($d['diary_date'])); ?></strong></td>
                            <td><?php echo htmlspecialchars($d['class'] . ' - ' . $d['section']); ?></td>
                            <td><strong><?php echo htmlspecialchars($d['subject']); ?></strong></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($d['diary_type']); ?></span></td>
                            <td>
                                <div class="fw-bold mb-1"><?php echo htmlspecialchars($d['title']); ?></div>
                                <div class="small text-muted text-wrap" style="max-width: 500px;"><?php echo strip_tags($d['description']); ?></div>
                                <?php if (!empty($d['attachment'])): ?>
                                    <div class="mt-1 d-print-none"><span class="small text-secondary"><i class="fa-solid fa-paperclip me-1 text-primary"></i>Attachment: <a href="<?php echo APP_URL . '/' . $d['attachment']; ?>" target="_blank"><?php echo basename($d['attachment']); ?></a></span></div>
                                <?php endif; ?>
                            </td>
                            <td class="d-print-none">
                                <span class="badge <?php echo ($d['status'] === 'Active') ? 'bg-success' : 'bg-secondary'; ?>"><?php echo htmlspecialchars($d['status']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($d['teacher_name'] ?: 'System'); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// CSV / Excel exporter logic
function exportReportCSV() {
    let csv = "Date,Class,Section,Subject,Diary Type,Title,Description,Status,Created By\n";
    <?php if (!empty($diaries)): foreach ($diaries as $d): ?>
        csv += "<?php echo $d['diary_date']; ?>,<?php echo $d['class']; ?>,<?php echo $d['section']; ?>,<?php echo $d['subject']; ?>,<?php echo $d['diary_type']; ?>,<?php echo $d['title']; ?>,<?php echo str_replace('"', '""', strip_tags($d['description'])); ?>,<?php echo $d['status']; ?>,<?php echo $d['teacher_name'] ?: 'System'; ?>\n";
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
