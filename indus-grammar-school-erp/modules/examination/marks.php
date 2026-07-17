<?php
/**
 * Indus Grammar School ERP - Marks Evaluator Panel
 * Version 4.0.0
 */

$pageTitle = 'Marks Entry';
$breadcrumbActive = 'Examination';
include_once __DIR__ . '/../../includes/header.php';

// Restricted to Super Admin, School Admin, Exam Controller, Teachers
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'teacher', 'exam_controller'])) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permissions to access the examination module.';
    redirect(APP_URL . '/dashboard.php');
}

$db = Database::getConnection();

// Selectors
$examTypes = $db->query("SELECT * FROM exam_types WHERE status = 'Active' ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$classes   = SchoolClass::all();

$selectedExam = isset($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : 0;
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedSubject = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$selectedAcademicType = sanitize($_GET['academic_type'] ?? 'School');

$subjectsForClass = [];
$studentsMarks = [];
$subjectInfo = null;

if ($selectedClass > 0) {
    // Filter subjects by class and academic type if specified
    $stmt = $db->prepare("SELECT * FROM subjects WHERE class_id = :cid AND academic_type = :atype AND status = 'Active' ORDER BY subject_name ASC");
    $stmt->execute(['cid' => $selectedClass, 'atype' => $selectedAcademicType]);
    $subjectsForClass = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($selectedExam > 0 && $selectedClass > 0 && $selectedSubject > 0) {
    $studentsMarks = StudentMark::getClassMarksBySubject($selectedExam, $selectedClass, $selectedSubject);
    $subjectInfo = Subject::findById($selectedSubject);
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-pen-nib text-primary me-2"></i>Marks Entry</h3>
        <p class="text-muted small mb-0">Record and update student obtained marks. Real-time boundaries are checked upon saving.</p>
    </div>
</div>

<!-- Filter Selection Form -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Select Exam Term</label>
                <select class="form-select" name="exam_type_id" required>
                    <option value="">— Choose Exam —</option>
                    <?php foreach ($examTypes as $et): ?>
                        <option value="<?php echo $et['id']; ?>" <?php echo $selectedExam === (int)$et['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($et['exam_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Academic Type</label>
                <select class="form-select" name="academic_type" onchange="document.getElementById('filterForm').submit()">
                    <option value="School" <?php echo $selectedAcademicType === 'School' ? 'selected' : ''; ?>>School</option>
                    <option value="Academy" <?php echo $selectedAcademicType === 'Academy' ? 'selected' : ''; ?>>Academy</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Class & Section</label>
                <select class="form-select" name="class_id" required onchange="document.getElementById('filterForm').submit()">
                    <option value="">— Select Class —</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Subject</label>
                <select class="form-select" name="subject_id" required <?php echo empty($subjectsForClass) ? 'disabled' : ''; ?>>
                    <option value="">— Choose Subject —</option>
                    <?php foreach ($subjectsForClass as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $selectedSubject === (int)$s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['subject_name']); ?> (Max: <?php echo $s['total_marks']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-sync me-2"></i>Load Students</button>
            </div>
        </form>
    </div>
</div>

<!-- Evaluator Sheet -->
<?php if ($selectedExam > 0 && $selectedClass > 0 && $selectedSubject > 0): ?>
    <?php if (empty($studentsMarks)): ?>
        <div class="card border-0 shadow-sm p-5 text-center" style="border-radius:12px;">
            <i class="fa-solid fa-users-slash fs-1 text-muted opacity-50 mb-3 d-block"></i>
            <h5 class="text-muted mb-0">No active students registered in this class.</h5>
        </div>
    <?php else: ?>
        <form id="marksForm">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="action" value="save_student_marks">
            <input type="hidden" name="exam_type_id" value="<?php echo $selectedExam; ?>">
            <input type="hidden" name="subject_id" value="<?php echo $selectedSubject; ?>">

            <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-1 text-secondary"><i class="fa-solid fa-pen-fancy me-2 text-primary"></i>Evaluator Sheet: <?php echo htmlspecialchars($subjectInfo['subject_name']); ?></h5>
                        <small class="text-muted">Maximum Limit: <strong class="text-dark"><?php echo $subjectInfo['total_marks']; ?> Marks</strong> | Passing Mark: <strong class="text-danger"><?php echo $subjectInfo['passing_marks']; ?> Marks</strong></small>
                    </div>
                    <span class="badge bg-primary px-3 py-2 rounded-pill"><?php echo count($studentsMarks); ?> Students Loaded</span>
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table custom-table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th width="80">Index</th>
                                    <th>Admission No.</th>
                                    <th>Student Name</th>
                                    <th width="150" class="text-center">Status</th>
                                    <th width="180" class="text-end">Obtained Marks</th>
                                    <th>Teacher Remarks (Optional)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentsMarks as $i => $s): ?>
                                    <tr>
                                        <td class="text-muted fw-bold"><?php echo $i + 1; ?></td>
                                        <td><code class="text-muted"><?php echo htmlspecialchars($s['admission_no']); ?></code></td>
                                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
                                        <td class="text-center">
                                            <select class="form-select form-select-sm status-select" name="marks[<?php echo $s['student_id']; ?>][status]" data-sid="<?php echo $s['student_id']; ?>">
                                                <option value="Present" <?php echo ($s['status'] === 'Present' || !$s['status']) ? 'selected' : ''; ?>>Present</option>
                                                <option value="Absent" <?php echo $s['status'] === 'Absent' ? 'selected' : ''; ?>>Absent</option>
                                                <option value="Leave" <?php echo $s['status'] === 'Leave' ? 'selected' : ''; ?>>Leave</option>
                                                <option value="Exempt" <?php echo $s['status'] === 'Exempt' ? 'selected' : ''; ?>>Exempt</option>
                                            </select>
                                        </td>
                                        <td class="text-end">
                                            <div class="input-group input-group-sm ms-auto" style="width: 140px;">
                                                <input type="number" step="0.1" min="0" max="<?php echo $subjectInfo['total_marks']; ?>" class="form-control text-end fw-bold marks-val-input" name="marks[<?php echo $s['student_id']; ?>][marks]" id="marks_input_<?php echo $s['student_id']; ?>" value="<?php echo $s['marks_obtained'] !== null ? (float)$s['marks_obtained'] : ''; ?>" <?php echo ($s['status'] && $s['status'] !== 'Present') ? 'disabled' : ''; ?>>
                                                <span class="input-group-text">/ <?php echo $subjectInfo['total_marks']; ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" name="marks[<?php echo $s['student_id']; ?>][remarks]" value="<?php echo htmlspecialchars($s['remarks'] ?? ''); ?>" placeholder="Add observations...">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-light border-0 py-3 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Registry</button>
                    <button type="submit" class="btn btn-primary px-5 fw-bold" id="btnSaveMarks"><i class="fa-solid fa-floppy-disk me-2"></i>Save & Calculate results</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
<?php else: ?>
    <div class="card border-0 shadow-sm" style="border-radius:12px; height: 320px;">
        <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-5">
            <i class="fa-solid fa-rectangle-list fs-1 text-muted opacity-25 mb-3"></i>
            <h5 class="text-muted fw-bold">No Evaluator Loaded</h5>
            <p class="text-muted small mb-0">Please choose an active exam term, class section, and subject to begin grading.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="marksToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="marksToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    .card-body, .card-body * {
        visibility: visible;
    }
    .card {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none !important;
        border: 0 !important;
    }
    .form-select, .form-control {
        border: 0 !important;
        background: none !important;
        padding: 0 !important;
        font-weight: bold !important;
    }
    .input-group-text {
        display: none !important;
    }
}
</style>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("marksToast");
    const m = document.getElementById("marksToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Disable/Enable inputs based on status selector
    document.querySelectorAll(".status-select").forEach(select => {
        select.addEventListener("change", function() {
            const sid = this.dataset.sid;
            const marksInput = document.getElementById("marks_input_" + sid);
            if (this.value === "Present") {
                marksInput.disabled = false;
                marksInput.required = true;
            } else {
                marksInput.disabled = true;
                marksInput.value = "";
                marksInput.required = false;
            }
        });
    });

    // Form submission validation & AJAX
    const form = document.getElementById("marksForm");
    if(form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            // Client side validation
            let valid = true;
            document.querySelectorAll(".marks-val-input").forEach(input => {
                if(!input.disabled && input.value !== "") {
                    const val = parseFloat(input.value);
                    const max = parseFloat(input.getAttribute("max"));
                    if(val > max || val < 0) {
                        showToast("Obtained marks cannot exceed maximum limits or fall below 0.", false);
                        input.classList.add("is-invalid");
                        valid = false;
                    } else {
                        input.classList.remove("is-invalid");
                    }
                }
            });

            if(!valid) return;

            const btn = document.getElementById("btnSaveMarks");
            btn.disabled = true; btn.innerHTML = "Saving Marksheet...";

            fetch("../../ajax/exams.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        btn.disabled = false; btn.innerHTML = \'<i class=\"fa-solid fa-floppy-disk me-2\"></i>Save & Calculate results\';
                    }
                })
                .catch(() => {
                    showToast("Failed to save student scores.", false);
                    btn.disabled = false; btn.innerHTML = \'<i class=\"fa-solid fa-floppy-disk me-2\"></i>Save & Calculate results\';
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
