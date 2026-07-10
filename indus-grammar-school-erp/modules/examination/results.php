<?php
/**
 * Indus Grammar School ERP - Class/Student Results View
 * Version 1.0.0
 */

$pageTitle = 'Examination Results';
$breadcrumbActive = 'Examination';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('academic_view');

$exams = Exam::all();
$classes = SchoolClass::all();

$selectedExam = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedSubject = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

$subjectsForClass = [];
$studentsMarks = [];

if ($selectedClass > 0) {
    $subjectsForClass = Subject::all($selectedClass);
}

if ($selectedExam > 0 && $selectedClass > 0 && $selectedSubject > 0) {
    $studentsMarks = Result::getClassMarksBySubject($selectedExam, $selectedClass, $selectedSubject);
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-square-poll-vertical me-2 text-primary"></i>Exam Results</h3>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Select Exam</label>
                <select class="form-select" name="exam_id" required onchange="document.getElementById('filterForm').submit()">
                    <option value="">— Select Exam —</option>
                    <?php foreach ($exams as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo ($selectedExam == $e['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($e['exam_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Select Class</label>
                <select class="form-select" name="class_id" required onchange="document.getElementById('filterForm').submit()">
                    <option value="">— Select Class —</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($selectedClass == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Select Subject</label>
                <select class="form-select" name="subject_id" required onchange="document.getElementById('filterForm').submit()" <?php echo empty($subjectsForClass) ? 'disabled' : ''; ?>>
                    <option value="">— Select Subject —</option>
                    <?php foreach ($subjectsForClass as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo ($selectedSubject == $s['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($s['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedExam > 0 && $selectedClass > 0 && $selectedSubject > 0): ?>
    <div class="custom-table-card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Admission No</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Marks Obtained</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($studentsMarks)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No marks entries found.</td></tr>
                    <?php else: foreach ($studentsMarks as $s): ?>
                        <tr>
                            <td class="fw-semibold text-dark"><?php echo sanitize($s['first_name'] . ' ' . $s['last_name']); ?></td>
                            <td><code class="text-muted"><?php echo sanitize($s['admission_no']); ?></code></td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo ($s['status'] === 'Present' || !$s['status']) ? 'success' : 'danger'; ?>-soft px-3 py-2 rounded-pill">
                                    <?php echo sanitize($s['status'] ?: 'Present'); ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold text-primary">
                                <?php echo $s['marks_obtained'] !== null ? number_format($s['marks_obtained'], 2) : '-'; ?>
                            </td>
                            <td><span class="text-muted small"><?php echo sanitize($s['remarks'] ?: '-'); ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm" style="border-radius:12px; height: 250px;">
        <div class="card-body d-flex flex-column align-items-center justify-content-center text-muted">
            <i class="fa-solid fa-chart-line fs-1 opacity-25 mb-3"></i>
            <h5>Select parameters above to load exam results table.</h5>
        </div>
    </div>
<?php endif; ?>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
