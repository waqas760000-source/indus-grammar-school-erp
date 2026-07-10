<?php
/**
 * Indus Grammar School ERP - Student Attendance Marking
 * Version 1.0.0
 */

$pageTitle      = 'Mark Student Attendance';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('attendance_mark');

$classes = SchoolClass::all();
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedDate  = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');
$students      = [];
$alreadyMarked = false;

if ($selectedClass > 0) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT s.id, s.first_name, s.last_name, s.admission_no,
                   a.status as current_status, a.remarks as current_remarks
            FROM students s
            LEFT JOIN attendance a ON a.student_id = s.id AND a.date = :date
            WHERE s.class_id = :cid AND s.status = 'Active'
            ORDER BY s.first_name ASC
        ");
        $stmt->execute(['cid' => $selectedClass, 'date' => $selectedDate]);
        $students = $stmt->fetchAll();
        // Check if already partially marked
        foreach ($students as $s) {
            if ($s['current_status'] !== null) { $alreadyMarked = true; break; }
        }
    } catch (Exception $e) {
        error_log("Attendance student.php: " . $e->getMessage());
    }
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-calendar-check me-2 text-primary"></i>Mark Student Attendance</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="daily.php" class="btn btn-outline-secondary px-4">
            <i class="fa-solid fa-chart-bar me-2"></i>View Daily Summary
        </a>
    </div>
</div>

<!-- Filter Form -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" action="student.php" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold text-muted">Select Class</label>
                <select class="form-select" name="class_id" id="class_id" required>
                    <option value="">— Choose Class —</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($selectedClass == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold text-muted">Attendance Date</label>
                <input type="date" class="form-control" name="date" value="<?php echo $selectedDate; ?>" max="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="fa-solid fa-users me-2"></i>Load Students
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedClass > 0 && empty($students)): ?>
    <div class="text-center py-5">
        <i class="fa-solid fa-users-slash fs-1 text-muted opacity-50 mb-3 d-block"></i>
        <h5 class="text-muted">No active students found in this class.</h5>
    </div>
<?php elseif (!empty($students)): ?>

<?php if ($alreadyMarked): ?>
    <div class="alert alert-info border-0 shadow-sm mb-4">
        <i class="fa-solid fa-circle-info me-2"></i>
        Attendance has already been marked for this class on <strong><?php echo date('D, d M Y', strtotime($selectedDate)); ?></strong>. You can update it below.
    </div>
<?php endif; ?>

<!-- Quick Actions Bar -->
<div class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
    <div class="card-body p-3 d-flex flex-wrap gap-2 align-items-center">
        <span class="text-muted small fw-semibold me-3">Mark All As:</span>
        <button class="btn btn-sm btn-outline-success" id="markAllPresent"><i class="fa-solid fa-check me-1"></i>Present</button>
        <button class="btn btn-sm btn-outline-danger" id="markAllAbsent"><i class="fa-solid fa-times me-1"></i>Absent</button>
        <button class="btn btn-sm btn-outline-warning" id="markAllLate"><i class="fa-solid fa-clock me-1"></i>Late</button>
        <div class="ms-auto">
            <span class="badge bg-primary rounded-pill fs-6" id="summary-present">0 Present</span>
            <span class="badge bg-danger rounded-pill fs-6 ms-2" id="summary-absent">0 Absent</span>
            <span class="badge bg-warning text-dark rounded-pill fs-6 ms-2" id="summary-late">0 Late</span>
        </div>
    </div>
</div>

<!-- Attendance Table -->
<form id="attendanceForm">
    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
    <input type="hidden" name="action" value="mark_daily">
    <input type="hidden" name="class_id" value="<?php echo $selectedClass; ?>">
    <input type="hidden" name="date" value="<?php echo $selectedDate; ?>">

    <div class="custom-table-card shadow-sm border-0 mb-4">
        <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-secondary">
                <i class="fa-solid fa-list-check me-2"></i>
                <?php
                    $classInfo = SchoolClass::findById($selectedClass);
                    echo sanitize(($classInfo['class_name'] ?? '') . ' - Section ' . ($classInfo['section'] ?? ''));
                ?> &nbsp;·&nbsp; <?php echo date('D, d M Y', strtotime($selectedDate)); ?>
            </h5>
            <span class="badge bg-secondary rounded-pill"><?php echo count($students); ?> Students</span>
        </div>
        <div class="table-responsive">
            <table class="table custom-table table-hover" id="attendanceTable">
                <thead>
                    <tr>
                        <th width="50">#</th>
                        <th>Student</th>
                        <th>Admission No</th>
                        <th>Status</th>
                        <th>Remarks (Optional)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $i => $student): ?>
                        <?php $cur = $student['current_status'] ?? 'Present'; ?>
                        <tr class="att-row" data-status="<?php echo $cur; ?>">
                            <td><?php echo $i + 1; ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo sanitize($student['first_name'] . ' ' . $student['last_name']); ?></div>
                            </td>
                            <td><code class="text-muted"><?php echo sanitize($student['admission_no']); ?></code></td>
                            <td>
                                <input type="hidden" name="records[<?php echo $i; ?>][student_id]" value="<?php echo $student['id']; ?>">
                                <div class="btn-group att-btn-group" role="group">
                                    <input type="radio" class="btn-check att-radio" name="records[<?php echo $i; ?>][status]" id="p<?php echo $student['id']; ?>" value="Present" autocomplete="off" <?php echo ($cur === 'Present') ? 'checked' : ''; ?>>
                                    <label class="btn btn-sm btn-outline-success" for="p<?php echo $student['id']; ?>">P</label>

                                    <input type="radio" class="btn-check att-radio" name="records[<?php echo $i; ?>][status]" id="a<?php echo $student['id']; ?>" value="Absent" autocomplete="off" <?php echo ($cur === 'Absent') ? 'checked' : ''; ?>>
                                    <label class="btn btn-sm btn-outline-danger" for="a<?php echo $student['id']; ?>">A</label>

                                    <input type="radio" class="btn-check att-radio" name="records[<?php echo $i; ?>][status]" id="l<?php echo $student['id']; ?>" value="Late" autocomplete="off" <?php echo ($cur === 'Late') ? 'checked' : ''; ?>>
                                    <label class="btn btn-sm btn-outline-warning" for="l<?php echo $student['id']; ?>">L</label>

                                    <input type="radio" class="btn-check att-radio" name="records[<?php echo $i; ?>][status]" id="lv<?php echo $student['id']; ?>" value="Leave" autocomplete="off" <?php echo ($cur === 'Leave') ? 'checked' : ''; ?>>
                                    <label class="btn btn-sm btn-outline-secondary" for="lv<?php echo $student['id']; ?>">Lv</label>
                                </div>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm" name="records[<?php echo $i; ?>][remarks]"
                                    value="<?php echo sanitize($student['current_remarks'] ?? ''); ?>" placeholder="Remarks...">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="p-4 border-top bg-light d-flex justify-content-between align-items-center">
            <small class="text-muted">P = Present &nbsp;·&nbsp; A = Absent &nbsp;·&nbsp; L = Late &nbsp;·&nbsp; Lv = Leave</small>
            <button type="submit" class="btn btn-primary px-5 py-2" id="submitBtn">
                <i class="fa-solid fa-save me-2"></i>Save Attendance
            </button>
        </div>
    </div>
</form>
<?php endif; ?>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="attToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="attToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php
$extraJS = '<script>
document.addEventListener("DOMContentLoaded", function () {

    function updateSummary() {
        let p = 0, a = 0, l = 0;
        document.querySelectorAll(".att-radio:checked").forEach(function(r) {
            if (r.value === "Present") p++;
            else if (r.value === "Absent") a++;
            else if (r.value === "Late") l++;
        });
        document.getElementById("summary-present").textContent = p + " Present";
        document.getElementById("summary-absent").textContent  = a + " Absent";
        document.getElementById("summary-late").textContent    = l + " Late";
    }

    document.querySelectorAll(".att-radio").forEach(function(r) {
        r.addEventListener("change", updateSummary);
    });
    updateSummary();

    function markAll(status) {
        document.querySelectorAll(".att-row").forEach(function(row) {
            let radio = row.querySelector(".att-radio[value=\"" + status + "\"]");
            if (radio) radio.checked = true;
        });
        updateSummary();
    }

    document.getElementById("markAllPresent")?.addEventListener("click", function(e){ e.preventDefault(); markAll("Present"); });
    document.getElementById("markAllAbsent")?.addEventListener("click",  function(e){ e.preventDefault(); markAll("Absent"); });
    document.getElementById("markAllLate")?.addEventListener("click",    function(e){ e.preventDefault(); markAll("Late"); });

    // Show toast helper
    function showToast(msg, success) {
        const toast  = document.getElementById("attToast");
        const msgEl  = document.getElementById("attToastMsg");
        toast.classList.remove("bg-success","bg-danger");
        toast.classList.add(success ? "bg-success" : "bg-danger");
        msgEl.textContent = msg;
        new bootstrap.Toast(toast).show();
    }

    // Form submission
    const form = document.getElementById("attendanceForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("submitBtn");
            btn.disabled = true;
            btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Saving...\';

            const formData = new FormData(form);
            fetch("../../ajax/attendance.php", { method: "POST", body: formData })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    btn.disabled = false;
                    btn.innerHTML = \'<i class="fa-solid fa-save me-2"></i>Save Attendance\';
                })
                .catch(() => {
                    showToast("Network error. Please try again.", false);
                    btn.disabled = false;
                    btn.innerHTML = \'<i class="fa-solid fa-save me-2"></i>Save Attendance\';
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php';
?>
