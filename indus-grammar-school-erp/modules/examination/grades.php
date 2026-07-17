<?php
/**
 * Indus Grammar School ERP - Grade Setup Management
 * Version 4.0.0
 */

$pageTitle = 'Grade Setup';
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

// Load grade configurations
$grades = GradeSetup::all();
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-graduation-cap text-primary me-2"></i>Grading System Setup</h3>
        <p class="text-muted small mb-0">Configure grading ranges, GPA parameters, and descriptors used in report cards and position registries.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary fw-semibold px-4" data-bs-toggle="modal" data-bs-target="#gradeModal" onclick="resetForm()">
            <i class="fa-solid fa-plus me-2"></i>Add Grade Level
        </button>
        <a href="dashboard.php" class="btn btn-outline-secondary px-4 ms-2"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Table List -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Grade</th>
                        <th class="text-center">Min Percentage</th>
                        <th class="text-center">Max Percentage</th>
                        <th class="text-center">Grade Point (GPA)</th>
                        <th>Remarks</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($grades)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No grades configured. Click "Add Grade Level" to configure.</td></tr>
                    <?php else: foreach ($grades as $g): ?>
                        <tr>
                            <td class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($g['grade']); ?></td>
                            <td class="text-center fw-semibold text-primary"><?php echo number_format($g['min_percentage'], 2); ?>%</td>
                            <td class="text-center fw-semibold text-secondary"><?php echo number_format($g['max_percentage'], 2); ?>%</td>
                            <td class="text-center fw-bold text-dark"><?php echo number_format($g['grade_point'], 2); ?></td>
                            <td class="fw-semibold text-muted"><?php echo htmlspecialchars($g['remarks'] ?: '—'); ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" onclick='editGrade(<?php echo json_encode($g); ?>)'>
                                    <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                                </button>
                                <?php if (hasPermission('academic_manage')): ?>
                                    <button class="btn btn-sm btn-outline-danger btn-delete rounded-pill px-3" data-id="<?php echo $g['id']; ?>">
                                        <i class="fa-solid fa-trash-can me-1"></i>Delete
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="gradeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary" id="modalTitle"><i class="fa-solid fa-graduation-cap me-2 text-primary"></i>Grade Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="gradeForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_grade">
                    <input type="hidden" name="grade_id" id="gradeId" value="0">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Grade Name *</label>
                            <input type="text" class="form-control fw-bold" name="grade" id="gradeName" required placeholder="e.g. A+, B, C">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Grade Point (GPA) *</label>
                            <input type="number" step="0.05" class="form-control" name="grade_point" id="gradePoint" required min="0" max="4" value="4.00">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Minimum Percentage (%) *</label>
                            <input type="number" step="0.1" class="form-control" name="min_percentage" id="gradeMin" required min="0" max="100" value="80">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Maximum Percentage (%) *</label>
                            <input type="number" step="0.1" class="form-control" name="max_percentage" id="gradeMax" required min="0" max="100" value="89.9">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Remarks / Descriptor</label>
                        <input type="text" class="form-control" name="remarks" id="gradeRemarks" placeholder="e.g. Excellent, Fail">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="gradeForm" class="btn btn-primary px-4" id="btnSave">Save Grade</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="gradeToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="gradeToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
const modalObj = new bootstrap.Modal(document.getElementById("gradeModal"));

function showToast(msg, ok) {
    const t = document.getElementById("gradeToast");
    const m = document.getElementById("gradeToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function resetForm() {
    document.getElementById("gradeForm").reset();
    document.getElementById("gradeId").value = "0";
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-graduation-cap me-2 text-primary"></i>Grade Details\';
    document.getElementById("btnSave").innerHTML = "Save Grade";
}

function editGrade(data) {
    resetForm();
    document.getElementById("gradeId").value = data.id;
    document.getElementById("gradeName").value = data.grade;
    document.getElementById("gradePoint").value = data.grade_point;
    document.getElementById("gradeMin").value = data.min_percentage;
    document.getElementById("gradeMax").value = data.max_percentage;
    document.getElementById("gradeRemarks").value = data.remarks;
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit Grade Levels\';
    document.getElementById("btnSave").innerHTML = "Update Grade";
    modalObj.show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Form Save
    const form = document.getElementById("gradeForm");
    if(form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSave");
            btn.disabled = true; btn.innerHTML = "Saving...";

            fetch("../../ajax/exams.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        modalObj.hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        btn.disabled = false; btn.innerHTML = document.getElementById("gradeId").value !== "0" ? "Update Grade" : "Save Grade";
                    }
                })
                .catch(() => {
                    showToast("System error.", false);
                    btn.disabled = false; btn.innerHTML = "Save Grade";
                });
        });
    }

    // Delete Trigger
    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this grade level? This changes overall calculations!")) return;
            const id = this.dataset.id;
            this.disabled = true;

            const fd = new FormData();
            fd.append("action", "delete_grade");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);

            fetch("../../ajax/exams.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else this.disabled = false;
                })
                .catch(() => {
                    showToast("Error.", false);
                    this.disabled = false;
                });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
