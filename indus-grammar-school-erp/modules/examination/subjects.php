<?php
/**
 * Indus Grammar School ERP - Subjects & Curriculum Management
 * Version 4.0.0
 */

$pageTitle = 'Subjects Setup';
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

// Load filters
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

$classes = SchoolClass::all();
$subjects = Subject::all($selectedClass);

// Load teachers list (Active staff directory)
$teachers = $db->query("
    SELECT id, employee_no, first_name, last_name, designation 
    FROM staff 
    WHERE status = 'Active' 
    ORDER BY first_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-book-open text-primary me-2"></i>Subjects Management</h3>
        <p class="text-muted small mb-0">Define class subjects, set passing parameters, and designate teachers.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary fw-semibold px-4" data-bs-toggle="modal" data-bs-target="#subjectModal" onclick="resetForm()">
            <i class="fa-solid fa-plus me-2"></i>Add Subject
        </button>
        <a href="dashboard.php" class="btn btn-outline-secondary px-4 ms-2"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Class Filter Card -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Filter by Class</label>
                <select class="form-select" name="class_id" onchange="this.form.submit()">
                    <option value="0">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Table List -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Subject Name</th>
                        <th>Subject Code</th>
                        <th>Class & Section</th>
                        <th class="text-center">Academic Type</th>
                        <th class="text-end">Max Marks</th>
                        <th class="text-end">Passing Marks</th>
                        <th>Assigned Teacher</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No subjects assigned. Click "Add Subject" to log one.</td></tr>
                    <?php else: foreach ($subjects as $s): ?>
                        <tr>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($s['subject_name']); ?></td>
                            <td><code class="text-muted"><?php echo htmlspecialchars($s['subject_code'] ?: '—'); ?></code></td>
                            <td><span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($s['class_name'] . ' - ' . $s['section']); ?></span></td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $s['academic_type'] === 'Academy' ? 'warning text-dark' : 'info text-white'; ?> px-3 py-1 rounded-pill fw-bold">
                                    <?php echo htmlspecialchars($s['academic_type']); ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold text-dark"><?php echo $s['total_marks']; ?></td>
                            <td class="text-end fw-bold text-danger"><?php echo $s['passing_marks']; ?></td>
                            <td>
                                <?php if ($s['teacher_id']): ?>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($s['teacher_first'] . ' ' . $s['teacher_last']); ?></div>
                                <?php else: ?>
                                    <span class="text-muted small">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $s['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill fw-semibold"><?php echo $s['status']; ?></span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" onclick='editSubject(<?php echo json_encode($s); ?>)'>
                                    <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                                </button>
                                <?php if (hasPermission('academic_manage')): ?>
                                    <button class="btn btn-sm btn-outline-danger btn-delete rounded-pill px-3" data-id="<?php echo $s['id']; ?>">
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
<div class="modal fade" id="subjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary" id="modalTitle"><i class="fa-solid fa-book-open me-2 text-primary"></i>Assign Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="subjectForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_subject">
                    <input type="hidden" name="subject_id" id="subjectId" value="0">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Class & Section *</label>
                        <select class="form-select" name="class_id" id="subjectClassId" required>
                            <option value="">-- Choose Class --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Subject Name *</label>
                            <input type="text" class="form-control" name="subject_name" id="subjectName" required placeholder="e.g. English Literature">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Subject Code</label>
                            <input type="text" class="form-control" name="subject_code" id="subjectCode" placeholder="e.g. ENG-10">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Maximum Marks *</label>
                            <input type="number" class="form-control" name="total_marks" id="subjectMaxMarks" required min="1" value="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Passing Marks *</label>
                            <input type="number" class="form-control" name="passing_marks" id="subjectPassingMarks" required min="1" value="40">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Academic Type</label>
                            <select class="form-select" name="academic_type" id="subjectAcademicType">
                                <option value="School">School</option>
                                <option value="Academy">Academy</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Assigned Teacher</label>
                            <select class="form-select" name="teacher_id" id="subjectTeacherId">
                                <option value="0">-- Not Assigned --</option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name'] . ' (' . $t['designation'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status" id="subjectStatus">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
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

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="subjectToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="subjectToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
const modalObj = new bootstrap.Modal(document.getElementById("subjectModal"));

function showToast(msg, ok) {
    const t = document.getElementById("subjectToast");
    const m = document.getElementById("subjectToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function resetForm() {
    document.getElementById("subjectForm").reset();
    document.getElementById("subjectId").value = "0";
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-book-open me-2 text-primary"></i>Assign Subject\';
    document.getElementById("btnSave").innerHTML = "Save Subject";
}

function editSubject(data) {
    resetForm();
    document.getElementById("subjectId").value = data.id;
    document.getElementById("subjectClassId").value = data.class_id;
    document.getElementById("subjectName").value = data.subject_name;
    document.getElementById("subjectCode").value = data.subject_code;
    document.getElementById("subjectMaxMarks").value = data.total_marks;
    document.getElementById("subjectPassingMarks").value = data.passing_marks;
    document.getElementById("subjectAcademicType").value = data.academic_type;
    document.getElementById("subjectTeacherId").value = data.teacher_id ? data.teacher_id : "0";
    document.getElementById("subjectStatus").value = data.status;
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit Subject Details\';
    document.getElementById("btnSave").innerHTML = "Update Subject";
    modalObj.show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Form Save
    const form = document.getElementById("subjectForm");
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
                        btn.disabled = false; btn.innerHTML = document.getElementById("subjectId").value !== "0" ? "Update Subject" : "Save Subject";
                    }
                })
                .catch(() => {
                    showToast("System error.", false);
                    btn.disabled = false; btn.innerHTML = "Save Subject";
                });
        });
    }

    // Delete Trigger
    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this subject? Result records linked to this subject will be purged!")) return;
            const id = this.dataset.id;
            this.disabled = true;

            const fd = new FormData();
            fd.append("action", "delete_subject");
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
