<?php
/**
 * Indus Grammar School ERP - Staff Attendance Shift Configurations
 * Version 5.0.0 - Executive Shift Policies & Lock Limits Console
 */

$pageTitle = 'Attendance Configurations';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';

// Shift settings are Admin only
SchoolAdminMiddleware::handle();

$db = Database::getConnection();

// Fetch settings row
$settings = [];
try {
    $settings = $db->query("SELECT * FROM staff_attendance_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading attendance settings: " . $e->getMessage());
}

$workingDays = explode(',', $settings['working_days'] ?? 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
$weekendDays = explode(',', $settings['weekend_days'] ?? 'Sunday');

$startTime = $settings['office_start_time'] ?? '08:00:00';
$endTime   = $settings['office_end_time'] ?? '14:00:00';
$lateTime  = $settings['late_arrival_time'] ?? '08:15:00';
$halfTime  = $settings['half_day_time'] ?? '11:00:00';
$lockTime  = $settings['attendance_lock_time'] ?? '23:59:59';
$allowEdit = $settings['allow_attendance_editing'] ?? 1;

// Calculate grace period minutes
$graceMins = max(0, round((strtotime($lateTime) - strtotime($startTime)) / 60));

// Calculate total shift duration hours
$shiftSecs = strtotime($endTime) - strtotime($startTime);
$shiftHrs  = $shiftSecs > 0 ? round($shiftSecs / 3600, 1) : 0;
?>

<style>
.config-card { border-radius: 14px; transition: transform 0.15s ease-in-out; }
.config-card:hover { transform: translateY(-2px); }
.day-pill-check .form-check-input { display: none; }
.day-pill-check .form-check-label {
    cursor: pointer;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid #cbd5e1;
    background-color: #f8fafc;
    color: #475569;
    transition: all 0.15s ease-in-out;
}
.day-pill-check .form-check-input:checked + .form-check-label.work-label {
    background-color: #e8f5e9;
    color: #2e7d32;
    border-color: #a5d6a7;
}
.day-pill-check .form-check-input:checked + .form-check-label.week-label {
    background-color: #ffebee;
    color: #c62828;
    border-color: #ef9a9a;
}
</style>

<!-- Top Executive Hero Banner Header -->
<div class="card border-0 shadow-lg mb-4 overflow-hidden" style="border-radius: 16px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f2744 100%);">
    <div class="card-body p-4 p-md-5 position-relative">
        <!-- Decorative glowing orb backdrop -->
        <div class="position-absolute end-0 top-0 translate-middle-y me-5 mt-4" style="width: 300px; height: 300px; background: radial-gradient(circle, rgba(13, 110, 253, 0.25) 0%, rgba(0, 0, 0, 0) 70%); pointer-events: none; filter: blur(40px);"></div>
        
        <div class="row align-items-center position-relative" style="z-index: 2;">
            <div class="col-lg-8 mb-3 mb-lg-0">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(13, 110, 253, 0.15); border: 1px solid rgba(13, 110, 253, 0.3);">
                    <span class="pulse-dot bg-primary rounded-circle d-inline-block" style="width: 8px; height: 8px;"></span>
                    <span class="fw-bold uppercase tracking-wider" style="font-size: 0.72rem; letter-spacing: 1px; color: #60a5fa;">POLICY & TIMING PARAMETERS</span>
                </div>
                <h2 class="fw-extrabold text-white mb-2 tracking-tight d-flex align-items-center gap-2">
                    <i class="fa-solid fa-gears text-warning fs-2 me-2"></i>Attendance Configurations
                </h2>
                <p class="mb-0 leading-relaxed" style="font-size: 0.95rem; color: #94a3b8;">
                    Establish shift timing policies, late thresholds, half day timing parameters, and lock limits.
                </p>
            </div>
            
            <div class="col-lg-4 text-lg-end">
                <div class="d-inline-flex align-items-center gap-3 p-3 rounded-3 shadow-sm border" style="background: rgba(255, 255, 255, 0.07); backdrop-filter: blur(12px); border-color: rgba(255, 255, 255, 0.15) !important;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-warning" style="width: 42px; height: 42px; background: rgba(245, 158, 11, 0.15);">
                        <i class="fa-solid fa-business-time fs-4"></i>
                    </div>
                    <div class="text-start">
                        <div class="text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.8px; color: #94a3b8;">ACTIVE SHIFT DURATION</div>
                        <div class="fw-bold text-white fs-5 mb-0"><?php echo $shiftHrs; ?> Hours / Day</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 4 Policy Summary KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 h-100 config-card text-center bg-white border-start border-primary border-4">
            <div class="small text-muted fw-semibold text-uppercase" style="font-size:0.7rem;">Shift Window</div>
            <div class="fs-4 fw-bold text-dark my-1"><?php echo date('h:i A', strtotime($startTime)); ?> - <?php echo date('h:i A', strtotime($endTime)); ?></div>
            <div class="small text-muted" style="font-size:0.7rem;"><?php echo $shiftHrs; ?> Hours Standard Shift</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 h-100 config-card text-center" style="background-color: #fffbeb;">
            <div class="small text-warning fw-semibold text-uppercase" style="font-size:0.7rem;">Late Threshold</div>
            <div class="fs-4 fw-bold text-warning my-1"><?php echo date('h:i A', strtotime($lateTime)); ?></div>
            <div class="small text-warning-50" style="font-size:0.7rem;"><?php echo $graceMins; ?> Mins Grace Period</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 h-100 config-card text-center" style="background-color: #faf5ff;">
            <div class="small text-primary fw-semibold text-uppercase" style="font-size:0.7rem;">Half Day Threshold</div>
            <div class="fs-4 fw-bold text-primary my-1"><?php echo date('h:i A', strtotime($halfTime)); ?></div>
            <div class="small text-primary-50" style="font-size:0.7rem;">Partial Hours Cutoff</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 h-100 config-card text-center text-white" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6);">
            <div class="small text-white-50 fw-semibold text-uppercase" style="font-size:0.7rem;">Daily Auto-Lock Limit</div>
            <div class="fs-4 fw-bold text-white my-1"><?php echo date('h:i A', strtotime($lockTime)); ?></div>
            <div class="small text-white-50" style="font-size:0.7rem;"><?php echo $allowEdit ? 'Editing Permitted' : 'Sheet Locked'; ?></div>
        </div>
    </div>
</div>

<!-- Main Settings Form Card -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
    <div class="card-header bg-white p-3 border-bottom">
        <h6 class="fw-bold text-secondary mb-0">
            <i class="fa-solid fa-clock me-2 text-primary"></i>Shift Policy, Thresholds & Auto-Lock Parameters
        </h6>
    </div>
    <div class="card-body p-4">
        <form id="settingsForm">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="action" value="save_attendance_settings">

            <!-- Section 1: Shift Hours -->
            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-business-time me-2 text-primary"></i>1. Standard Shift Timing Policies</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Office Shift Start Time *</label>
                    <input type="time" class="form-control" name="office_start_time" value="<?php echo htmlspecialchars($startTime); ?>" required>
                    <div class="form-text small text-muted">Standard morning check-in time for school employees (Default: 08:00 AM).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Office Shift End Time *</label>
                    <input type="time" class="form-control" name="office_end_time" value="<?php echo htmlspecialchars($endTime); ?>" required>
                    <div class="form-text small text-muted">Standard afternoon check-out time for school employees (Default: 02:00 PM).</div>
                </div>
            </div>

            <!-- Section 2: Late & Half Day Thresholds -->
            <h6 class="fw-bold text-dark mb-3 border-top pt-3"><i class="fa-solid fa-user-clock me-2 text-warning"></i>2. Grace Period & Threshold Parameters</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Late Arrival Threshold Time *</label>
                    <input type="time" class="form-control" name="late_arrival_time" value="<?php echo htmlspecialchars($lateTime); ?>" required>
                    <div class="form-text small text-muted">Arrivals after this time are automatically flagged as <strong>"Late"</strong> (Default: 08:15 AM).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Half Day Cutoff Time *</label>
                    <input type="time" class="form-control" name="half_day_time" value="<?php echo htmlspecialchars($halfTime); ?>" required>
                    <div class="form-text small text-muted">Arrivals after this time are logged as <strong>"Half Day"</strong> shift (Default: 11:00 AM).</div>
                </div>
            </div>

            <!-- Section 3: Lock Limits & Administrative Permissions -->
            <h6 class="fw-bold text-dark mb-3 border-top pt-3"><i class="fa-solid fa-lock me-2 text-danger"></i>3. Daily Auto-Lock Limits & Editing Controls</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Daily Attendance Auto-Lock Time *</label>
                    <input type="time" class="form-control" name="attendance_lock_time" value="<?php echo htmlspecialchars($lockTime); ?>" required>
                    <div class="form-text small text-muted">Cutoff time of day after which attendance modification locks for non-admins (Default: 11:59 PM).</div>
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <div class="form-check form-switch p-3 bg-light rounded-3 w-100 border mt-2">
                        <input class="form-check-input ms-0 me-3" type="checkbox" name="allow_attendance_editing" id="allow_edit" value="1" <?php echo $allowEdit ? 'checked' : ''; ?>>
                        <label class="form-check-label small fw-bold text-dark" for="allow_edit">
                            Allow Administrators to Edit Past Roster Logs
                            <span class="d-block small text-muted fw-normal mt-1">Enables editing and updating previously saved attendance roster sheets.</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Section 4: Working & Weekend Days Configurator -->
            <h6 class="fw-bold text-dark mb-3 border-top pt-3"><i class="fa-solid fa-calendar-days me-2 text-info"></i>4. Weekly Working Days & Weekend Schedule</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary mb-2">Configure Official Working Days</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): 
                            $isWork = in_array($day, $workingDays);
                        ?>
                            <div class="form-check day-pill-check ps-0">
                                <input class="form-check-input working-check" type="checkbox" name="working_days[]" value="<?php echo $day; ?>" id="work_<?php echo $day; ?>" <?php echo $isWork ? 'checked' : ''; ?>>
                                <label class="form-check-label work-label" for="work_<?php echo $day; ?>"><?php echo substr($day, 0, 3); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary mb-2">Configure Weekend Days</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): 
                            $isWeek = in_array($day, $weekendDays);
                        ?>
                            <div class="form-check day-pill-check ps-0">
                                <input class="form-check-input weekend-check" type="checkbox" name="weekend_days[]" value="<?php echo $day; ?>" id="week_<?php echo $day; ?>" <?php echo $isWeek ? 'checked' : ''; ?>>
                                <label class="form-check-label week-label" for="week_<?php echo $day; ?>"><?php echo substr($day, 0, 3); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Submit Controls -->
            <div class="row border-top pt-4 align-items-center">
                <div class="col-sm-6">
                    <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" onclick="location.reload();">
                        <i class="fa-solid fa-rotate-left me-1"></i>Reset Parameters
                    </button>
                </div>
                <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
                    <button type="submit" class="btn btn-primary px-5 py-2 fw-bold shadow-sm rounded-pill" id="btnSave">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Save Shift Configurations
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="setToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert">
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
            if (this.checked) {
                const day = this.value;
                const wk = document.getElementById("week_" + day);
                if (wk) wk.checked = false;
            }
        });
    });

    document.querySelectorAll(".weekend-check").forEach(chk => {
        chk.addEventListener("change", function() {
            if (this.checked) {
                const day = this.value;
                const work = document.getElementById("work_" + day);
                if (work) work.checked = false;
            }
        });
    });

    // Save Configurations via AJAX
    const form = document.getElementById("settingsForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSave");
            btn.disabled = true; 
            btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Saving Timing Configurations...\';
            
            fetch("../../ajax/staff_attendance.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) {
                        setTimeout(() => location.reload(), 1000);
                    } else { 
                        btn.disabled = false; 
                        btn.innerHTML = \'<i class="fa-solid fa-floppy-disk me-2"></i>Save Shift Configurations\'; 
                    }
                })
                .catch(() => { 
                    showToast("Network communication error.", false); 
                    btn.disabled = false; 
                    btn.innerHTML = \'<i class="fa-solid fa-floppy-disk me-2"></i>Save Shift Configurations\'; 
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
