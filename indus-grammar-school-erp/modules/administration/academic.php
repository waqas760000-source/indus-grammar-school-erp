<?php
/**
 * Indus Grammar School ERP - Academic Settings Tabbed Portal
 * Version 4.0.0
 */

$pageTitle = 'Academic Settings';
$breadcrumbActive = 'Administration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

$db = Database::getConnection();

// Fetch Sessions
$sessions = [];
try {
    $sessions = $db->query("SELECT * FROM academic_sessions ORDER BY session_name DESC")->fetchAll();
} catch (Exception $e) {}

// Fetch Classes & Sections
$classes = [];
try {
    $classes = $db->query("SELECT * FROM classes ORDER BY class_name ASC, section ASC")->fetchAll();
} catch (Exception $e) {}

// Fetch Subjects
$subjects = [];
try {
    $subjects = $db->query("SELECT s.*, c.class_name, c.section FROM subjects s JOIN classes c ON s.class_id = c.id ORDER BY c.class_name ASC, s.subject_name ASC")->fetchAll();
} catch (Exception $e) {}

// Fetch Departments
$departments = [];
try {
    $departments = $db->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}

// Fetch School Houses
$houses = [];
try {
    $houses = $db->query("SELECT * FROM school_houses ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-graduation-cap me-2 text-primary"></i>Academic Settings</h3>
        <p class="text-muted small mb-0">Configure academic sessions, register classes, sections, subjects, staff departments, and school houses.</p>
    </div>
</div>

<!-- Tab Navigation Header -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-0">
        <ul class="nav nav-tabs nav-fill border-0 px-3 pt-3" id="academicTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold text-secondary py-3" id="sessions-tab" data-bs-toggle="tab" data-bs-target="#sessionsPane" type="button" role="tab">
                    <i class="fa-solid fa-calendar-days me-2"></i>Academic Sessions
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-secondary py-3" id="classes-tab" data-bs-toggle="tab" data-bs-target="#classesPane" type="button" role="tab">
                    <i class="fa-solid fa-school me-2"></i>Classes & Sections
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-secondary py-3" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#subjectsPane" type="button" role="tab">
                    <i class="fa-solid fa-book me-2"></i>Subjects Registry
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-secondary py-3" id="departments-tab" data-bs-toggle="tab" data-bs-target="#departmentsPane" type="button" role="tab">
                    <i class="fa-solid fa-building-user me-2"></i>Departments
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-secondary py-3" id="houses-tab" data-bs-toggle="tab" data-bs-target="#housesPane" type="button" role="tab">
                    <i class="fa-solid fa-house-flag me-2"></i>School Houses
                </button>
            </li>
        </ul>
    </div>
</div>

<!-- Tab Content Body -->
<div class="tab-content" id="academicTabsContent">

    <!-- TAB 1: ACADEMIC SESSIONS -->
    <div class="tab-pane fade show active" id="sessionsPane" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold text-secondary mb-0">Academic Sessions</h5>
                    <button class="btn btn-sm btn-primary px-3" data-bs-toggle="modal" data-bs-target="#addSessionModal">
                        <i class="fa-solid fa-plus me-1"></i>Add Session
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="table custom-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Session Name</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $s): ?>
                                <tr>
                                    <td>#<?php echo $s['id']; ?></td>
                                    <td class="fw-bold text-dark"><?php echo sanitize($s['session_name']); ?></td>
                                    <td>
                                        <span class="badge badge-soft-<?php echo $s['is_active'] ? 'success' : 'secondary'; ?> px-3 py-2 rounded-pill">
                                            <?php echo $s['is_active'] ? 'Active Session' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if (!$s['is_active']): ?>
                                            <button class="btn btn-sm btn-outline-success btn-activate-session me-1" data-id="<?php echo $s['id']; ?>" title="Set Active">
                                                <i class="fa-solid fa-toggle-on me-1"></i>Set Active
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger btn-delete-session" data-id="<?php echo $s['id']; ?>" title="Delete">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small">Current Active</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: CLASSES & SECTIONS -->
    <div class="tab-pane fade" id="classesPane" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold text-secondary mb-0">Registered Classes & Sections</h5>
                    <button class="btn btn-sm btn-primary px-3" data-bs-toggle="modal" data-bs-target="#addClassModal">
                        <i class="fa-solid fa-plus me-1"></i>Add Class Section
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="table custom-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Class Name</th>
                                <th>Section Code</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $c): ?>
                                <tr>
                                    <td>#<?php echo $c['id']; ?></td>
                                    <td class="fw-bold text-dark"><?php echo sanitize($c['class_name']); ?></td>
                                    <td><span class="badge bg-light text-dark border px-3 py-2"><?php echo sanitize($c['section']); ?></span></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-danger btn-delete-class" data-id="<?php echo $c['id']; ?>" title="Delete">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: SUBJECTS REGISTRY -->
    <div class="tab-pane fade" id="subjectsPane" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold text-secondary mb-0">Subjects Directory</h5>
                    <button class="btn btn-sm btn-primary px-3" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                        <i class="fa-solid fa-plus me-1"></i>Add Subject
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="table custom-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Assigned Class</th>
                                <th class="text-center">Total Marks</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subjects)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No subjects registered.</td></tr>
                            <?php else: foreach ($subjects as $sb): ?>
                                <tr>
                                    <td>#<?php echo $sb['id']; ?></td>
                                    <td><code class="text-muted fw-bold"><?php echo sanitize($sb['subject_code']); ?></code></td>
                                    <td class="fw-bold text-dark"><?php echo sanitize($sb['subject_name']); ?></td>
                                    <td><?php echo sanitize($sb['class_name'] . ' (' . $sb['section'] . ')'); ?></td>
                                    <td class="text-center fw-semibold"><?php echo (int)$sb['total_marks']; ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-danger btn-delete-subject" data-id="<?php echo $sb['id']; ?>" title="Delete">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 4: DEPARTMENTS -->
    <div class="tab-pane fade" id="departmentsPane" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold text-secondary mb-0">Staff Departments</h5>
                    <button class="btn btn-sm btn-primary px-3" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                        <i class="fa-solid fa-plus me-1"></i>Add Department
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="table custom-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Department Name</th>
                                <th>Code</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($departments)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No departments created.</td></tr>
                            <?php else: foreach ($departments as $d): ?>
                                <tr>
                                    <td>#<?php echo $d['id']; ?></td>
                                    <td class="fw-bold text-dark"><?php echo sanitize($d['name']); ?></td>
                                    <td><code><?php echo sanitize($d['code']); ?></code></td>
                                    <td>
                                        <span class="badge badge-soft-<?php echo ($d['status'] === 'Active') ? 'success' : 'secondary'; ?> px-3 py-2 rounded-pill">
                                            <?php echo sanitize($d['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-danger btn-delete-department" data-id="<?php echo $d['id']; ?>" title="Delete">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 5: SCHOOL HOUSES -->
    <div class="tab-pane fade" id="housesPane" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold text-secondary mb-0">School Houses</h5>
                    <button class="btn btn-sm btn-primary px-3" data-bs-toggle="modal" data-bs-target="#addHouseModal">
                        <i class="fa-solid fa-plus me-1"></i>Add House
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="table custom-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>House Name</th>
                                <th>House Color</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($houses)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No school houses registered.</td></tr>
                            <?php else: foreach ($houses as $h): ?>
                                <tr>
                                    <td>#<?php echo $h['id']; ?></td>
                                    <td class="fw-bold text-dark"><?php echo sanitize($h['name']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded border me-2" style="width: 25px; height: 25px; background-color: <?php echo $h['house_color']; ?>;"></div>
                                            <small class="text-muted"><?php echo sanitize($h['house_color']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-soft-<?php echo ($h['status'] === 'Active') ? 'success' : 'secondary'; ?> px-3 py-2 rounded-pill">
                                            <?php echo sanitize($h['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-danger btn-delete-house" data-id="<?php echo $h['id']; ?>" title="Delete">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ─────────────────────────────────────────────────────────────────────────
     MODALS
     ───────────────────────────────────────────────────────────────────────── -->

<!-- Add Session Modal -->
<div class="modal fade" id="addSessionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-plus me-2 text-primary"></i>Add Academic Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="addSessionForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_session">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Session Name *</label>
                        <input type="text" class="form-control" name="session_name" placeholder="e.g. 2027-2028" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addSessionForm" class="btn btn-sm btn-primary px-4">Add</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-school me-2 text-primary"></i>Add Class & Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="addClassForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_class">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Class Name *</label>
                        <input type="text" class="form-control" name="class_name" placeholder="e.g. Class 6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Section Letter *</label>
                        <input type="text" class="form-control" name="section" value="A" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addClassForm" class="btn btn-sm btn-primary px-4">Add</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-book me-2 text-primary"></i>Register Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="addSubjectForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_subject">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Subject Code *</label>
                            <input type="text" class="form-control form-control-sm" name="subject_code" placeholder="e.g. ENG-101" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Subject Name *</label>
                            <input type="text" class="form-control form-control-sm" name="subject_name" placeholder="e.g. English Grammar" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Target Class *</label>
                            <select class="form-select form-select-sm" name="class_id" required>
                                <?php foreach ($classes as $cl): ?>
                                    <option value="<?php echo $cl['id']; ?>"><?php echo sanitize($cl['class_name'] . ' (' . $cl['section'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Total Marks</label>
                            <input type="number" class="form-control form-control-sm" name="total_marks" value="100" min="1" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addSubjectForm" class="btn btn-sm btn-primary px-4">Register</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-plus me-2 text-primary"></i>Add Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="addDepartmentForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_department">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Department Name *</label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Science" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Department Code *</label>
                        <input type="text" class="form-control" name="code" placeholder="e.g. SCI" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addDepartmentForm" class="btn btn-sm btn-primary px-4">Add</button>
            </div>
        </div>
    </div>
</div>

<!-- Add House Modal -->
<div class="modal fade" id="addHouseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-plus me-2 text-primary"></i>Add School House</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="addHouseForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_house">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">House Name *</label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Iqbal House" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">House Color Theme</label>
                        <input type="color" class="form-control form-control-color w-100" name="house_color" value="#0d6efd">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addHouseForm" class="btn btn-sm btn-primary px-4">Add</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Feedback -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="acadToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="acadToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("acadToast");
    const m = document.getElementById("acadToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function handleFormSubmit(formId, modalId) {
    const form = document.getElementById(formId);
    if(form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            fetch("../../ajax/admin.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
                        setTimeout(() => location.reload(), 1000);
                    }
                })
                .catch(() => showToast("Network error occurred.", false));
        });
    }
}

function handleDeleteAction(buttonClass, actionName) {
    document.querySelectorAll("." + buttonClass).forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this item? This action is permanent and might fail if linked to other student records.")) return;
            const id = this.dataset.id;
            const fd = new FormData();
            fd.append("action", actionName);
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/admin.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 1000);
                });
        });
    });
}

document.addEventListener("DOMContentLoaded", function() {
    // Session Active Toggle
    document.querySelectorAll(".btn-activate-session").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            const fd = new FormData();
            fd.append("action", "toggle_session");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/admin.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 1000);
                });
        });
    });

    // Form Submissions
    handleFormSubmit("addSessionForm", "addSessionModal");
    handleFormSubmit("addClassForm", "addClassModal");
    handleFormSubmit("addSubjectForm", "addSubjectModal");
    handleFormSubmit("addDepartmentForm", "addDepartmentModal");
    handleFormSubmit("addHouseForm", "addHouseModal");

    // Delete Operations
    handleDeleteAction("btn-delete-session", "delete_session");
    handleDeleteAction("btn-delete-class", "delete_class");
    handleDeleteAction("btn-delete-subject", "delete_subject");
    handleDeleteAction("btn-delete-department", "delete_department");
    handleDeleteAction("btn-delete-house", "delete_house");
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
