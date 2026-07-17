<?php
/**
 * Indus Grammar School ERP - Staff Attendance Shift Configurations
 * Version 4.0.0
 */

$pageTitle = 'Attendance Settings';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';

// Shift settings are Admin only
SchoolAdminMiddleware::handle();

$db = Database::getConnection();

// Fetch settings row
$settings = [];
try {
    $settings = $db->query("SELECT * FROM staff_attendance_settings WHERE id = 1")->fetch();
} catch (Exception $e) {
    error_log("Error loading attendance settings: " . $e->getMessage());
}

$workingDays = explode(',', $settings['working_days'] ?? 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
$weekendDays = explode(',', $settings['weekend_days'] ?? 'Sunday');
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-gears me-2 text-primary"></i>Attendance Configurations</h3>
        <p class="text-muted small mb-0">Establish shift timing policies, late thresholds, half day timing parameters, and lock limits.</p>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:12px; max-width: 800px;">
    <div class="card-body p-4">
        <h5 class="fw-bold text-secondary mb-4 border-bottom pb-2">Shift Policy & Lock Timings</h5>
        
        <form id="settingsForm">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="action" value="save_attendance_settings">

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Office Shift Start Time *</label>
                    <input type="time" class="form-control" name="office_start_time" value="<?php echo htmlspecialchars($settings['office_start_time'] ?? '08:00:00'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Office Shift End Time *</label>
                    <input type="time" class="form-control" name="office_end_time" value="<?php echo htmlspecialchars($settings['office_end_time'] ?? '14:00:00'); ?>" required>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Late Arrival Threshold Time *</label>
                    <input type="time" class="form-control" name="late_arrival_time" value="<?php echo htmlspecialchars($settings['late_arrival_time'] ?? '08:15:00'); ?>" required>
                    <div class="form-text small text-muted">Time after which staff members are automatically logged as "Late".</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Half Day Threshold Time *</label>
                    <input type="time" class="form-control" name="half_day_time" value="<?php echo htmlspecialchars($settings['half_day_time'] ?? '11:00:00'); ?>" required>
                    <div class="form-text small text-muted">Time after which check-in is logged as "Half Day".</div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Daily Attendance Auto-Lock Time *</label>
                    <input type="time" class="form-control" name="attendance_lock_time" value="<?php echo htmlspecialchars($settings['attendance_lock_time'] ?? '23:59:59'); ?>" required>
                    <div class="form-text small text-muted">Time of day after which attendance cannot be saved or edited.</div>
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="allow_attendance_editing" id="allow_edit" value="1" <?php echo ($settings['allow_attendance_editing'] ?? 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label small fw-semibold text-secondary" for="allow_edit">Allow administrators to edit past attendance sheets</label>
                    </div>
                </div>
            </div>

            <!-- Working & Holiday Days -->
            <div class="row g-3 mb-4 border-top pt-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary mb-2">Configure Working Days</label>
                    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                        <div class="form-check py-1">
                            <input class="form-check-input working-check" type="checkbox" name="working_days[]" value="<?php echo $day; ?>" id="work_<?php echo $day; ?>" <?php echo in_array($day, $workingDays) ? 'checked' : ''; ?>>
                            <label class="form-check-label small" for="work_<?php echo $day; ?>"><?php echo $day; ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary mb-2">Configure Weekend Days</label>
                    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                        <div class="form-check py-1">
                            <input class="form-check-input weekend-check" type="checkbox" name="weekend_days[]" value="<?php echo $day; ?>" id="week_<?php echo $day; ?>" <?php echo in_array($day, $weekendDays) ? 'checked' : ''; ?>>
                            <label class="form-check-label small text-danger" for="week_<?php echo $day; ?>"><?php echo $day; ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="row border-top pt-4">
                <div class="col-sm-6">
                    <button type="button" class="btn btn-outline-secondary px-4" onclick="location.reload();">Reset Parameters</button>
                </div>
                <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
                    <button type="submit" class="btn btn-primary px-5 py-2 fw-semibold" id="btnSave">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Save Shift Configurations
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="setToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="setToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("setToast");
    const m = document.getElementById("setToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    
    // Prevent selecting a day as both working day and weekend day
    document.querySelectorAll(".working-check").forEach(chk => {
        chk.addEventListener("change", function() {
            if(this.checked) {
                const day = this.value;
                const wk = document.getElementById("week_" + day);
                if(wk) wk.checked = false;
            }
        });
    });

    document.querySelectorAll(".weekend-check").forEach(chk => {
        chk.addEventListener("change", function() {
            if(this.checked) {
                const day = this.value;
                const work = document.getElementById("work_" + day);
                if(work) work.checked = false;
            }
        });
    });

    // Form Submission
    const form = document.getElementById("settingsForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSave");
            btn.disabled = true; btn.innerHTML = "Saving timing configurations...";
            
            fetch("../../ajax/staff_attendance.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-floppy-disk me-2\"></i>Save Shift Configurations"; }
                })
                .catch(() => { showToast("Communication error.", false); btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-floppy-disk me-2\"></i>Save Shift Configurations"; });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
