<?php
/**
 * Indus Grammar School ERP - Subjects Management
 * Version 1.0.0
 */

$pageTitle = 'Class Subjects';
$breadcrumbActive = 'Examination';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('academic_view');

$classes = SchoolClass::all();
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$subjects = [];

if ($selectedClass > 0) {
    $subjects = Subject::all($selectedClass);
} else {
    // Show all if no class selected
    $subjects = Subject::all();
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-book-open me-2 text-primary"></i>Subjects Management</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if (hasPermission('academic_manage')): ?>
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#subjectModal" onclick="openSubjectModal()">
            <i class="fa-solid fa-plus me-2"></i>Add Subject
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Filter by Class</label>
                <select class="form-select" name="class_id" onchange="this.form.submit()">
                    <option value="0">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($selectedClass == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Subject Name</th>
                    <th>Subject Code</th>
                    <th>Class</th>
                    <th class="text-center">Total Marks</th>
                    <?php if (hasPermission('academic_manage')): ?>
                    <th class="text-end">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subjects)): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">No subjects found.</td></tr>
                <?php else: foreach ($subjects as $s): ?>
                    <tr>
                        <td class="fw-semibold text-dark"><?php echo sanitize($s['subject_name']); ?></td>
                        <td><code class="text-muted"><?php echo sanitize($s['subject_code'] ?: '-'); ?></code></td>
                        <td><?php echo sanitize($s['class_name'] . ' - ' . $s['section']); ?></td>
                        <td class="text-center fw-bold"><?php echo $s['total_marks']; ?></td>
                        <?php if (hasPermission('academic_manage')): ?>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" title="Edit" 
                                onclick="openSubjectModal(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['subject_name']); ?>', '<?php echo htmlspecialchars($s['subject_code']); ?>', <?php echo $s['class_id']; ?>, <?php echo $s['total_marks']; ?>)">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo $s['id']; ?>" title="Delete">
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

<!-- Subject Modal -->
<?php if (hasPermission('academic_manage')): ?>
<div class="modal fade" id="subjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="modalTitle"><i class="fa-solid fa-book-open me-2 text-primary"></i>Add Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="subjectForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_subject">
                    <input type="hidden" name="subject_id" id="subjectId" value="0">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Class</label>
                        <select class="form-select" name="class_id" id="modalClassId" required>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Subject Name</label>
                        <input type="text" class="form-control" name="subject_name" id="modalSubjectName" required placeholder="e.g. Mathematics">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Subject Code (Optional)</label>
                            <input type="text" class="form-control" name="subject_code" id="modalSubjectCode" placeholder="e.g. MATH-101">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Total Marks</label>
                            <input type="number" class="form-control fw-bold" name="total_marks" id="modalTotalMarks" value="100" min="1" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="subjectForm" class="btn btn-primary px-4" id="btnSave">Save Subject</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="subToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="subToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("subToast");
    const m = document.getElementById("subToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function openSubjectModal(id = 0, name = "", code = "", classId = "", marks = 100) {
    document.getElementById("modalTitle").innerHTML = id ? `<i class="fa-solid fa-pen me-2 text-primary"></i>Edit Subject` : `<i class="fa-solid fa-book-open me-2 text-primary"></i>Add Subject`;
    document.getElementById("subjectId").value = id;
    document.getElementById("modalSubjectName").value = name;
    document.getElementById("modalSubjectCode").value = code;
    document.getElementById("modalTotalMarks").value = marks;
    if (classId) document.getElementById("modalClassId").value = classId;
    if (id) new bootstrap.Modal(document.getElementById("subjectModal")).show();
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("subjectForm");
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
                    else { btn.disabled = false; btn.innerHTML = "Save Subject"; }
                });
        });
    }

    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this subject? Result data linked to it will also be affected.")) return;
            const fd = new FormData();
            fd.append("action", "delete_subject");
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
