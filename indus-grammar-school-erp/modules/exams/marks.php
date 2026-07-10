<?php
/**
 * Indus Grammar School ERP - Marks Entry
 * Version 1.0.0
 */

$pageTitle = 'Marks Entry';
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
$subjectInfo = null;

if ($selectedClass > 0) {
    $subjectsForClass = Subject::all($selectedClass);
}

if ($selectedExam > 0 && $selectedClass > 0 && $selectedSubject > 0) {
    $studentsMarks = Result::getClassMarksBySubject($selectedExam, $selectedClass, $selectedSubject);
    $subjectInfo = Subject::findById($selectedSubject);
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-pen-nib me-2 text-primary"></i>Marks Entry</h3>
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
                            <?php echo sanitize($s['subject_name']); ?> (Max: <?php echo $s['total_marks']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedExam > 0 && $selectedClass > 0 && $selectedSubject > 0): ?>
    <?php if (empty($studentsMarks)): ?>
        <div class="text-center py-5">
            <i class="fa-solid fa-users-slash fs-1 text-muted opacity-50 mb-3 d-block"></i>
            <h5 class="text-muted">No active students found in this class.</h5>
        </div>
    <?php else: ?>
        <form id="marksForm">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="action" value="save_marks">
            <input type="hidden" name="exam_id" value="<?php echo $selectedExam; ?>">
            <input type="hidden" name="subject_id" value="<?php echo $selectedSubject; ?>">

            <div class="custom-table-card shadow-sm border-0 mb-4">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-secondary"><?php echo sanitize($subjectInfo['subject_name']); ?></h5>
                        <small class="text-muted">Maximum Marks: <strong id="maxMarks"><?php echo $subjectInfo['total_marks']; ?></strong></small>
                    </div>
                    <span class="badge bg-primary rounded-pill"><?php echo count($studentsMarks); ?> Students</span>
                </div>
                <div class="table-responsive">
                    <table class="table custom-table table-hover">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th>Student</th>
                                <th>Admission No</th>
                                <th>Status</th>
                                <th style="width:150px;">Marks Obtained</th>
                                <th>Remarks (Optional)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentsMarks as $i => $s): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td class="fw-semibold"><?php echo sanitize($s['first_name'] . ' ' . $s['last_name']); ?></td>
                                    <td><code class="text-muted"><?php echo sanitize($s['admission_no']); ?></code></td>
                                    <td>
                                        <select class="form-select form-select-sm marks-status" name="marks[<?php echo $s['student_id']; ?>][status]" style="width:120px;">
                                            <option value="Present" <?php echo ($s['status'] === 'Present' || !$s['status']) ? 'selected' : ''; ?>>Present</option>
                                            <option value="Absent" <?php echo ($s['status'] === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                                            <option value="Leave" <?php echo ($s['status'] === 'Leave') ? 'selected' : ''; ?>>Leave</option>
                                            <option value="Exempt" <?php echo ($s['status'] === 'Exempt') ? 'selected' : ''; ?>>Exempt</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm marks-input" name="marks[<?php echo $s['student_id']; ?>][marks]"
                                            value="<?php echo $s['marks_obtained'] !== null ? $s['marks_obtained'] : ''; ?>" 
                                            min="0" max="<?php echo $subjectInfo['total_marks']; ?>" step="0.5"
                                            <?php echo ($s['status'] && $s['status'] !== 'Present') ? 'disabled' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" name="marks[<?php echo $s['student_id']; ?>][remarks]"
                                            value="<?php echo sanitize($s['remarks'] ?? ''); ?>" placeholder="Optional remarks...">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (hasPermission('academic_manage')): ?>
                <div class="p-4 border-top bg-light text-end">
                    <button type="submit" class="btn btn-primary px-5 py-2" id="btnSaveMarks">
                        <i class="fa-solid fa-save me-2"></i>Save Marks
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>
<?php else: ?>
    <div class="card border-0 shadow-sm" style="border-radius:12px; height: 300px;">
        <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-5">
            <i class="fa-solid fa-list-check fs-1 text-muted opacity-25 mb-3"></i>
            <h5 class="text-muted">Select an Exam, Class, and Subject to enter marks.</h5>
        </div>
    </div>
<?php endif; ?>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="mkToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="mkToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("mkToast");
    const m = document.getElementById("mkToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Disable marks input if status is not Present
    document.querySelectorAll(".marks-status").forEach(select => {
        select.addEventListener("change", function() {
            const row = this.closest("tr");
            const marksInput = row.querySelector(".marks-input");
            if (this.value === "Present") {
                marksInput.disabled = false;
            } else {
                marksInput.disabled = true;
                marksInput.value = "";
            }
        });
    });

    // Form submission
    const form = document.getElementById("marksForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            // Validate Max Marks
            const maxMarks = parseFloat(document.getElementById("maxMarks").textContent);
            let hasError = false;
            document.querySelectorAll(".marks-input:not([disabled])").forEach(input => {
                const val = parseFloat(input.value);
                if (!isNaN(val) && (val < 0 || val > maxMarks)) {
                    input.classList.add("is-invalid");
                    hasError = true;
                } else {
                    input.classList.remove("is-invalid");
                }
            });

            if (hasError) {
                showToast("Please fix the highlighted marks. Marks cannot exceed " + maxMarks + ".", false);
                return;
            }

            const btn = document.getElementById("btnSaveMarks");
            btn.disabled = true;
            btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Saving...\';

            fetch("../../ajax/exams.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    btn.disabled = false;
                    btn.innerHTML = \'<i class="fa-solid fa-save me-2"></i>Save Marks\';
                })
                .catch(() => {
                    showToast("Network error. Please try again.", false);
                    btn.disabled = false;
                    btn.innerHTML = \'<i class="fa-solid fa-save me-2"></i>Save Marks\';
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
