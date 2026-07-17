<?php
/**
 * Indus Grammar School ERP - Examination Schedules Coordinator
 * Version 4.0.0
 */

$pageTitle = 'Exam Timetable';
$breadcrumbActive = 'Examination';
include_once __DIR__ . '/../../includes/header.php';

// Restricted to Super Admin, School Admin, Exam Controller, Teachers, Students
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
$isStaff = in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'teacher', 'exam_controller']);

$db = Database::getConnection();

// Fetch filter values
$selectedExam = isset($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : 0;
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// Load selectors
$examTypes = $db->query("SELECT * FROM exam_types WHERE status = 'Active' ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$classes   = SchoolClass::all();

// Load schedules matching filters
$schedules = ExamSchedule::all($selectedExam, $selectedClass);

// Pre-load all subjects mapped by class_id to feed the dynamic selector in modal
$rawSubjects = Subject::all();
$subjectsMap = [];
foreach ($rawSubjects as $s) {
    $subjectsMap[$s['class_id']][] = [
        'id'   => $s['id'],
        'name' => $s['subject_name'] . ' (' . ($s['subject_code'] ?: 'No Code') . ')'
    ];
}
?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-calendar-check text-primary me-2"></i>Exam Schedule</h3>
        <p class="text-muted small mb-0">Create and display examination dates, room settings, and supervision tables.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if ($isStaff): ?>
            <button class="btn btn-primary fw-semibold px-4" data-bs-toggle="modal" data-bs-target="#scheduleModal" onclick="resetForm()">
                <i class="fa-solid fa-plus me-2"></i>Arrange Exam Date
            </button>
        <?php endif; ?>
        <button class="btn btn-outline-primary px-4 ms-2" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Schedule</button>
        <a href="dashboard.php" class="btn btn-outline-secondary px-4 ms-2"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Search Filters Card -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted">Filter by Exam Type</label>
                <select class="form-select" name="exam_type_id">
                    <option value="0">All Exams</option>
                    <?php foreach ($examTypes as $et): ?>
                        <option value="<?php echo $et['id']; ?>" <?php echo $selectedExam === (int)$et['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($et['exam_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted">Filter by Class</label>
                <select class="form-select" name="class_id">
                    <option value="0">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
            </div>
        </form>
    </div>
</div>

<!-- Printable Area -->
<div class="card border-0 shadow-sm" style="border-radius:12px;" id="schedulePrintArea">
    <div class="card-header bg-white border-0 pt-4 px-4 d-none d-print-block text-center border-bottom pb-3">
        <h3 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h3>
        <h5 class="text-secondary fw-semibold mb-0">Examination Schedule / Timetable</h5>
        <small class="text-muted">Issued Date: <?php echo date('d-M-Y'); ?></small>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Exam Term</th>
                        <th>Class & Section</th>
                        <th>Subject</th>
                        <th class="text-center">Exam Date</th>
                        <th class="text-center">Timing</th>
                        <th class="text-center">Room No.</th>
                        <th>Supervisor</th>
                        <?php if ($isStaff): ?>
                            <th class="text-end d-print-none">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($schedules)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No timetables mapped. Please construct schedules using the modal button.</td></tr>
                    <?php else: foreach ($schedules as $s): ?>
                        <tr>
                            <td class="fw-bold text-secondary"><?php echo htmlspecialchars($s['exam_name']); ?></td>
                            <td><span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($s['class_name'] . ' - ' . $s['section']); ?></span></td>
                            <td class="fw-bold text-dark">
                                <?php echo htmlspecialchars($s['subject_name']); ?>
                                <small class="text-muted d-block text-xs"><?php echo htmlspecialchars($s['subject_code']); ?></small>
                            </td>
                            <td class="text-center fw-semibold text-dark"><?php echo date('d-M-Y', strtotime($s['exam_date'])); ?></td>
                            <td class="text-center small">
                                <i class="fa-regular fa-clock text-muted me-1"></i>
                                <?php echo date('h:i A', strtotime($s['start_time'])); ?> - <?php echo date('h:i A', strtotime($s['end_time'])); ?>
                            </td>
                            <td class="text-center fw-bold text-dark"><?php echo htmlspecialchars($s['room'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($s['supervisor'] ?: '—'); ?></td>
                            <?php if ($isStaff): ?>
                                <td class="text-end d-print-none">
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" onclick='editSchedule(<?php echo json_encode($s); ?>)'>
                                        <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-delete rounded-pill px-3" data-id="<?php echo $s['id']; ?>">
                                        <i class="fa-solid fa-trash-can me-1"></i>Delete
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form -->
<?php if ($isStaff): ?>
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary" id="modalTitle"><i class="fa-solid fa-calendar-check me-2 text-primary"></i>Arrange Exam Date</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="scheduleForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_exam_schedule">
                    <input type="hidden" name="schedule_id" id="scheduleId" value="0">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Exam Term *</label>
                        <select class="form-select" name="exam_type_id" id="scheduleExamTypeId" required>
                            <option value="">-- Choose Exam Term --</option>
                            <?php foreach ($examTypes as $et): ?>
                                <option value="<?php echo $et['id']; ?>"><?php echo htmlspecialchars($et['exam_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Class & Section *</label>
                            <select class="form-select" name="class_id" id="scheduleClassId" required onchange="populateSubjects()">
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Subject *</label>
                            <select class="form-select" name="subject_id" id="scheduleSubjectId" required disabled>
                                <option value="">-- Select Class First --</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Exam Date *</label>
                        <input type="date" class="form-control" name="exam_date" id="scheduleDate" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Start Time *</label>
                            <input type="time" class="form-control" name="start_time" id="scheduleStart" required value="09:00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">End Time *</label>
                            <input type="time" class="form-control" name="end_time" id="scheduleEnd" required value="12:00">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Room / Hall</label>
                            <input type="text" class="form-control" name="room" id="scheduleRoom" placeholder="e.g. Hall A, Room 10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Supervisor</label>
                            <input type="text" class="form-control" name="supervisor" id="scheduleSupervisor" placeholder="e.g. Mr. Aslam">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="scheduleForm" class="btn btn-primary px-4" id="btnSave">Save Schedule</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="scheduleToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="scheduleToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #schedulePrintArea, #schedulePrintArea * {
        visibility: visible;
    }
    #schedulePrintArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none !important;
        border: 0 !important;
    }
    .custom-table th, .custom-table td {
        border: 1px solid #ddd !important;
        padding: 10px !important;
        font-size: 11px !important;
    }
}
</style>

<?php 
// Convert subject preloads to JSON string for JS usage
$subjectsJson = json_encode($subjectsMap);

$extraJS = '<script>
const subjectsData = ' . $subjectsJson . ';
const modalObj = new bootstrap.Modal(document.getElementById("scheduleModal"));

function showToast(msg, ok) {
    const t = document.getElementById("scheduleToast");
    const m = document.getElementById("scheduleToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function populateSubjects(selectedSubId = 0) {
    const classId = document.getElementById("scheduleClassId").value;
    const subSelect = document.getElementById("scheduleSubjectId");
    subSelect.innerHTML = \'<option value="">-- Choose Subject --</option>\';
    
    if (classId && subjectsData[classId]) {
        subSelect.disabled = false;
        subjectsData[classId].forEach(sub => {
            const opt = document.createElement("option");
            opt.value = sub.id;
            opt.textContent = sub.name;
            if(parseInt(sub.id) === parseInt(selectedSubId)) opt.selected = true;
            subSelect.appendChild(opt);
        });
    } else {
        subSelect.disabled = true;
    }
}

function resetForm() {
    document.getElementById("scheduleForm").reset();
    document.getElementById("scheduleId").value = "0";
    document.getElementById("scheduleSubjectId").disabled = true;
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-calendar-check me-2 text-primary"></i>Arrange Exam Date\';
    document.getElementById("btnSave").innerHTML = "Save Schedule";
}

function editSchedule(data) {
    resetForm();
    document.getElementById("scheduleId").value = data.id;
    document.getElementById("scheduleExamTypeId").value = data.exam_type_id;
    document.getElementById("scheduleClassId").value = data.class_id;
    
    populateSubjects(data.subject_id);
    
    document.getElementById("scheduleDate").value = data.exam_date;
    document.getElementById("scheduleStart").value = data.start_time;
    document.getElementById("scheduleEnd").value = data.end_time;
    document.getElementById("scheduleRoom").value = data.room;
    document.getElementById("scheduleSupervisor").value = data.supervisor;
    
    document.getElementById("modalTitle").innerHTML = \'<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit Exam Schedule\';
    document.getElementById("btnSave").innerHTML = "Update Schedule";
    modalObj.show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Form Save
    const form = document.getElementById("scheduleForm");
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
                        btn.disabled = false; btn.innerHTML = document.getElementById("scheduleId").value !== "0" ? "Update Schedule" : "Save Schedule";
                    }
                })
                .catch(() => {
                    showToast("System error.", false);
                    btn.disabled = false; btn.innerHTML = "Save Schedule";
                });
        });
    }

    // Delete Trigger
    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to delete this schedule date?")) return;
            const id = this.dataset.id;
            this.disabled = true;

            const fd = new FormData();
            fd.append("action", "delete_exam_schedule");
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
