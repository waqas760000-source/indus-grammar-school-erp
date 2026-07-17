<?php
/**
 * Indus Grammar School ERP - Exam Types Registry Management
 * Version 4.0.0
 */

$pageTitle = 'Exam Terms';
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

// Fetch active sessions from database or default to current year
$sessions = $db->query("SELECT DISTINCT academic_year FROM fee_structure UNION SELECT '" . CURRENT_ACADEMIC_YEAR . "'")->fetchAll(PDO::FETCH_COLUMN);

// Fetch exam types list
$examTypes = ExamType::all();
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-calendar-days text-primary me-2"></i>Exam Types</h3>
        <p class="text-muted small mb-0">Create and coordinate exam terms, session durations, and passing standards.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary fw-semibold px-4" data-bs-toggle="modal" data-bs-target="#examModal" onclick="resetForm()">
            <i class="fa-solid fa-plus me-2"></i>New Exam Type
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
                        <th>Exam Name</th>
                        <th>Academic Session</th>
                        <th class="text-center">Academic Type</th>
                        <th class="text-center">Dates</th>
                        <th class="text-end">Total Marks</th>
                        <th class="text-center">Passing %</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($examTypes)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No exam types configured. Click "New Exam Type" to add one.</td></tr>
                    <?php else: foreach ($examTypes as $e): ?>
                        <tr>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($e['exam_name']); ?></td>
                            <td><span class="badge bg-light text-dark border px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($e['academic_session']); ?></span></td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $e['academic_type'] === 'Academy' ? 'warning text-dark' : 'info text-white'; ?> px-3 py-1 rounded-pill fw-bold">
                                    <?php echo htmlspecialchars($e['academic_type']); ?>
                                </span>
                            </td>
                            <td class="text-center small">
                                <div class="fw-bold text-dark"><?php echo date('d-M-Y', strtotime($e['start_date'])); ?></div>
                                <div class="text-muted text-xs">to</div>
                                <div class="fw-bold text-dark"><?php echo date('d-M-Y', strtotime($e['end_date'])); ?></div>
                            </td>
                            <td class="text-end fw-bold text-dark"><?php echo $e['total_marks']; ?></td>
                            <td class="text-center fw-bold text-primary"><?php echo number_format($e['passing_percentage'], 1); ?>%</td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $e['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill fw-semibold"><?php echo $e['status']; ?></span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" onclick='editExam(<?php echo json_encode($e); ?>)'>
                                    <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                                </button>
                                <?php if (hasPermission('academic_manage')): ?>
                                    <button class="btn btn-sm btn-outline-danger btn-delete rounded-pill px-3" data-id="<?php echo $e['id']; ?>">
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
<div class="modal fade" id="examModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary" id="modalTitle"><i class="fa-solid fa-calendar-days me-2 text-primary"></i>Configure Exam Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="examForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_exam">
                    <input type="hidden" name="exam_id" id="examId" value="0">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Exam Name *</label>
                        <input type="text" class="form-control" name="exam_name" id="examName" required placeholder="e.g. Mid Term Examination">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Academic Session *</label>
                            <select class="form-select" name="academic_session" id="examSession" required>
                                <?php foreach ($sessions as $s): ?>
                                    <option value="<?php echo $s; ?>"><?php echo htmlspecialchars($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Academic Type</label>
                            <select class="form-select" name="academic_type" id="examAcademicType">
                                <option value="School">School</option>
                                <option value="Academy">Academy</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" id="examStart" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">End Date *</label>
                            <input type="date" class="form-control" name="end_date" id="examEnd" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Total Marks</label>
                            <input type="number" class="form-control" name="total_marks" id="examTotalMarks" min="1" value="100" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Passing Percentage (%)</label>
                            <input type="number" step="0.5" class="form-control" name="passing_percentage" id="examPassingPercentage" min="1" max="100" value="40.0" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status" id="examStatus">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="examForm" class="btn btn-primary px-4" id="btnSave">Save Exam Type</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="examToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="examToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
const modalObj = new bootstrap.Modal(document.getElementById("examModal"));

function showToast(msg, ok) {
    const t = document.getElementById("examToast");
    const m = document.getElementById("examToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function resetForm() {
    document.getElementById("examForm").reset();
    document.getElementById("examId").value = "0";
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-calendar-days me-2 text-primary"></i>Configure Exam Type\';
    document.getElementById("btnSave").innerHTML = "Save Exam Type";
}

function editExam(data) {
    resetForm();
    document.getElementById("examId").value = data.id;
    document.getElementById("examName").value = data.exam_name;
    document.getElementById("examSession").value = data.academic_session;
    document.getElementById("examAcademicType").value = data.academic_type;
    document.getElementById("examStart").value = data.start_date;
    document.getElementById("examEnd").value = data.end_date;
    document.getElementById("examTotalMarks").value = data.total_marks;
    document.getElementById("examPassingPercentage").value = data.passing_percentage;
    document.getElementById("examStatus").value = data.status;
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit Exam Type\';
    document.getElementById("btnSave").innerHTML = "Update Exam Type";
    modalObj.show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Form Save
    const form = document.getElementById("examForm");
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
                        btn.disabled = false; btn.innerHTML = document.getElementById("examId").value !== "0" ? "Update Exam Type" : "Save Exam Type";
                    }
                })
                .catch(() => {
                    showToast("System error.", false);
                    btn.disabled = false; btn.innerHTML = "Save Exam Type";
                });
        });
    }

    // Delete Trigger
    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this exam term? This deletes all associated schedules, marks logs, and results!")) return;
            const id = this.dataset.id;
            this.disabled = true;

            const fd = new FormData();
            fd.append("action", "delete_exam");
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
