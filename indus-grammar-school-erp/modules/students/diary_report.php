<?php
/**
 * Indus Grammar School ERP - Diary Report
 * Version 1.0.0
 */

$pageTitle = 'Diary Report';
$breadcrumbActive = 'Student Registration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

// Filter parameters
$filter_date = sanitize($_GET['filter_date'] ?? '');
$start_date = sanitize($_GET['start_date'] ?? '');
$end_date = sanitize($_GET['end_date'] ?? '');
$teacher_id = (int)($_GET['teacher_id'] ?? 0);
$class_id = (int)($_GET['class_id'] ?? 0);
$section = sanitize($_GET['section'] ?? '');
$subject_id = (int)($_GET['subject_id'] ?? 0);

// Load filters lookup data
$classes = [];
$subjects = [];
$teachers = [];

try {
    $classes = $db->query("SELECT * FROM classes ORDER BY class_name ASC")->fetchAll();
    $subjects = $db->query("SELECT * FROM subjects ORDER BY subject_name ASC")->fetchAll();
    $teachers = $db->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();
} catch (Exception $e) {}

// Query diary entries
$diaries = [];
try {
    $sql = "
        SELECT d.*, c.class_name, c.section, s.subject_name, u.username as teacher_name
        FROM daily_diaries d
        JOIN classes c ON d.class_id = c.id
        JOIN subjects s ON d.subject_id = s.id
        JOIN users u ON d.teacher_id = u.id
        WHERE d.is_published = 1
    ";
    $params = [];

    if ($filter_date) {
        $sql .= " AND d.diary_date = :filter_date";
        $params['filter_date'] = $filter_date;
    }
    if ($start_date && $end_date) {
        $sql .= " AND d.diary_date BETWEEN :start_date AND :end_date";
        $params['start_date'] = $start_date;
        $params['end_date'] = $end_date;
    }
    if ($teacher_id > 0) {
        $sql .= " AND d.teacher_id = :teacher_id";
        $params['teacher_id'] = $teacher_id;
    }
    if ($class_id > 0) {
        $sql .= " AND d.class_id = :class_id";
        $params['class_id'] = $class_id;
    }
    if ($section) {
        $sql .= " AND c.section = :section";
        $params['section'] = $section;
    }
    if ($subject_id > 0) {
        $sql .= " AND d.subject_id = :subject_id";
        $params['subject_id'] = $subject_id;
    }

    $sql .= " ORDER BY d.diary_date DESC, d.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $diaries = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Diary Report query error: " . $e->getMessage());
}
?>

<!-- Title Header -->
<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Diary Report</h3>
    </div>
</div>

<!-- Filters Panel -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Report Records</h6>
    <form method="GET" action="diary_report.php" class="row g-3 align-items-end">
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Specific Date</label>
            <input type="date" class="form-control form-control-sm" name="filter_date" value="<?php echo $filter_date; ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Date From</label>
            <input type="date" class="form-control form-control-sm" name="start_date" value="<?php echo $start_date; ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Date To</label>
            <input type="date" class="form-control form-control-sm" name="end_date" value="<?php echo $end_date; ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Teacher</label>
            <select class="form-select form-select-sm" name="teacher_id">
                <option value="">All</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo ($teacher_id == $t['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($t['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Class</label>
            <select class="form-select form-select-sm" name="class_id">
                <option value="">All</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($class_id == $c['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label small fw-semibold text-muted">Sec.</label>
            <input type="text" class="form-control form-control-sm" name="section" value="<?php echo $section; ?>" placeholder="A, B...">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Subject</label>
            <select class="form-select form-select-sm" name="subject_id">
                <option value="">All</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo ($subject_id == $s['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($s['subject_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 text-end">
            <button type="submit" class="btn btn-sm btn-primary px-4"><i class="fa-solid fa-calculator me-2"></i>Generate Report</button>
            <a href="diary_report.php" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
        </div>
    </form>
</div>

<!-- Report Table Grid Card -->
<div id="reportCard" class="card border border-light shadow-sm bg-white p-4" style="border-radius:12px;">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4 d-print-none">
        <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-list-check text-primary me-2"></i>Published Homework Diaries</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print</button>
            <button class="btn btn-sm btn-outline-danger px-3" onclick="downloadPDF()"><i class="fa-solid fa-file-pdf me-2"></i>PDF</button>
            <button class="btn btn-sm btn-outline-success px-3" onclick="exportCSV()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle" id="reportTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Class</th>
                    <th>Subject</th>
                    <th>Diary Title</th>
                    <th>Diary Description</th>
                    <th>Published By</th>
                    <th>Created Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($diaries)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No published diary records found matching your filters.</td></tr>
                <?php else: foreach ($diaries as $row): ?>
                    <tr>
                        <td class="fw-bold text-dark"><?php echo date('d M Y', strtotime($row['diary_date'])); ?></td>
                        <td><?php echo sanitize($row['class_name'] . ' - ' . $row['section']); ?></td>
                        <td><?php echo sanitize($row['subject_name']); ?></td>
                        <td class="fw-semibold"><?php echo sanitize($row['title']); ?></td>
                        <td><?php echo sanitize($row['description']); ?></td>
                        <td><strong><?php echo sanitize($row['teacher_name']); ?></strong></td>
                        <td class="small text-muted"><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Mock PDF printer trigger
function downloadPDF() {
    alert("Reformatting report columns for PDF print. Press Ctrl+P (Cmd+P) to print to system PDF printer.");
    window.print();
}

// Client-side CSV/Excel exporter
function exportCSV() {
    let csv = "Date,Class,Subject,Diary Title,Description,Published By,Created Date\n";
    const rows = document.querySelectorAll("#reportTable tbody tr");
    
    rows.forEach(tr => {
        const cols = tr.querySelectorAll("td");
        if(cols.length === 7) {
            let rowData = [];
            cols.forEach(td => {
                let text = td.textContent.replace(/"/g, '""').replace(/,/g, ' ').trim();
                rowData.push('"' + text + '"');
            });
            csv += rowData.join(",") + "\n";
        }
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "diary_publication_report.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
