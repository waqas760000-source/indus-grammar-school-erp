<?php
/**
 * Indus Grammar School ERP - Exam Terms Management
 * Version 1.0.0
 */

$pageTitle = 'Exam Terms';
$breadcrumbActive = 'Examination';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('academic_view');

$exams = Exam::all();
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-calendar-days me-2 text-primary"></i>Exam Terms</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if (hasPermission('academic_manage')): ?>
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#examModal" onclick="openExamModal()">
            <i class="fa-solid fa-plus me-2"></i>New Exam Term
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-0 text-secondary">Registered Exams (<?php echo CURRENT_ACADEMIC_YEAR; ?>)</h5>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Exam Name</th>
                    <th>Academic Year</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <?php if (hasPermission('academic_manage')): ?>
                    <th class="text-end">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($exams)): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">No exams configured for the current academic year.</td></tr>
                <?php else: foreach ($exams as $e): ?>
                    <tr>
                        <td class="fw-bold text-dark"><?php echo sanitize($e['exam_name']); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo sanitize($e['academic_year']); ?></span></td>
                        <td><?php echo date('d M Y', strtotime($e['start_date'])); ?></td>
                        <td><?php echo date('d M Y', strtotime($e['end_date'])); ?></td>
                        <?php if (hasPermission('academic_manage')): ?>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" title="Edit"
                                onclick="openExamModal(<?php echo $e['id']; ?>, '<?php echo htmlspecialchars($e['exam_name']); ?>', '<?php echo $e['start_date']; ?>', '<?php echo $e['end_date']; ?>')">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo $e['id']; ?>" title="Delete">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Exam Modal -->
<?php if (hasPermission('academic_manage')): ?>
<div class="modal fade" id="examModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="modalTitle"><i class="fa-solid fa-calendar-days me-2 text-primary"></i>Exam Term</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="examForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_exam">
                    <input type="hidden" name="exam_id" id="examId" value="0">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Exam Name</label>
                        <input type="text" class="form-control" name="exam_name" id="modalExamName" required placeholder="e.g. Mid Term Examination">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Start Date</label>
                            <input type="date" class="form-control" name="start_date" id="modalStart" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">End Date</label>
                            <input type="date" class="form-control" name="end_date" id="modalEnd" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="examForm" class="btn btn-primary px-4" id="btnSave">Save Exam</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="exToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="exToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("exToast");
    const m = document.getElementById("exToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function openExamModal(id = 0, name = "", start = "", end = "") {
    document.getElementById("modalTitle").innerHTML = id ? `<i class="fa-solid fa-pen me-2 text-primary"></i>Edit Exam` : `<i class="fa-solid fa-calendar-days me-2 text-primary"></i>New Exam`;
    document.getElementById("examId").value = id;
    document.getElementById("modalExamName").value = name;
    document.getElementById("modalStart").value = start;
    document.getElementById("modalEnd").value = end;
    if (id) new bootstrap.Modal(document.getElementById("examModal")).show();
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("examForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSave");
            btn.disabled = true; btn.innerHTML = "Saving...";
            
            fetch("../../ajax/exams.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Save Exam"; }
                });
        });
    }

    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this exam? Result data associated with it will be permanently lost.")) return;
            const fd = new FormData();
            fd.append("action", "delete_exam");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", this.dataset.id);
            fetch("../../ajax/exams.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => { showToast(data.message, data.success); if(data.success) setTimeout(() => location.reload(), 1000); });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
