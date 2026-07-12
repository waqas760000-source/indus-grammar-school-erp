<?php
/**
 * Indus Grammar School ERP - Attendance Settings Submodule
 * Version 3.0.0
 */

// 1. Bootstrap App & Authorization Check (Admin Only)
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('system_settings'); // Only Admins can modify settings

$db = Database::getConnection();
$message = '';
$error = '';

// Load flash messages
if (!empty($_SESSION['flash_success'])) {
    $message = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// 2. Fetch active settings row
$settings = null;
try {
    $settings = $db->query("SELECT * FROM attendance_settings ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        // Fallback seed
        $db->exec("
            INSERT INTO attendance_settings (school_start_time, late_arrival_time, working_days, weekend_days, default_status, allow_edit, lock_hours)
            VALUES ('08:00:00', '08:15:00', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', 'Sunday', 'Present', 1, 24)
        ");
        $settings = $db->query("SELECT * FROM attendance_settings ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = "Error loading settings database: " . $e->getMessage();
}

$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$workingDaysArray = explode(',', $settings['working_days'] ?? 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
$weekendDaysArray = explode(',', $settings['weekend_days'] ?? 'Sunday');

// 3. Handle POST request to Update Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    try {
        $school_start_time = sanitize($_POST['school_start_time'] ?? '08:00:00');
        $late_arrival_time = sanitize($_POST['late_arrival_time'] ?? '08:15:00');
        $default_status = sanitize($_POST['default_status'] ?? 'Present');
        $allow_edit = isset($_POST['allow_edit']) ? 1 : 0;
        $lock_hours = (int)($_POST['lock_hours'] ?? 24);

        $postedWorking = $_POST['working_days'] ?? [];
        $postedWeekend = $_POST['weekend_days'] ?? [];

        if (empty($postedWorking)) {
            throw new Exception("Please select at least one working day.");
        }

        $working_days_str = implode(',', array_map('sanitize', $postedWorking));
        $weekend_days_str = implode(',', array_map('sanitize', $postedWeekend));

        // Update database row
        $stmtUp = $db->prepare("
            UPDATE attendance_settings SET
                school_start_time = :school_start_time,
                late_arrival_time = :late_arrival_time,
                working_days = :working_days,
                weekend_days = :weekend_days,
                default_status = :default_status,
                allow_edit = :allow_edit,
                lock_hours = :lock_hours
            WHERE id = :id
        ");
        $stmtUp->execute([
            'school_start_time' => $school_start_time,
            'late_arrival_time' => $late_arrival_time,
            'working_days' => $working_days_str,
            'weekend_days' => $weekend_days_str,
            'default_status' => $default_status,
            'allow_edit' => $allow_edit,
            'lock_hours' => $lock_hours,
            'id' => $settings['id']
        ]);

        // Audit Log
        $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmtLog->execute([$_SESSION['user_id'] ?? null, 'Settings Updated', 'Updated global student attendance settings configurations.', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

        $_SESSION['flash_success'] = "Attendance settings updated successfully!";
        header("Location: settings.php");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Include Layout Header
$pageTitle = 'Attendance Settings';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Title Banner -->
<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-sliders me-2 text-primary"></i>Attendance Settings</h3>
    </div>
</div>

<!-- Alerts -->
<?php if (!empty($message)): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4" style="border-radius: 12px;">
        <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius: 12px;">
        <i class="fa-solid fa-circle-xmark me-2"></i><?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Settings form card layout -->
<div class="card border-0 shadow-sm bg-white p-4" style="border-radius:12px;">
    <h5 class="fw-bold text-secondary mb-4 border-bottom pb-3"><i class="fa-solid fa-gear text-primary me-2"></i>Configure Student Attendance Policies</h5>
    <form id="settingsForm" method="POST">
        <input type="hidden" name="action" value="save_settings">
        
        <div class="row g-4">
            
            <!-- School timings -->
            <div class="col-md-6 border-end pr-md-4">
                <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-clock text-primary me-2"></i>Timings & Penalties</h6>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">School Start Time *</label>
                    <input type="time" class="form-control" name="school_start_time" value="<?php echo htmlspecialchars($settings['school_start_time']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Late Arrival Threshold *</label>
                    <input type="time" class="form-control" name="late_arrival_time" value="<?php echo htmlspecialchars($settings['late_arrival_time']); ?>" required>
                    <span class="small text-muted d-block mt-1">Arrivals logged after this time will default to 'Late'.</span>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Default Attendance Status *</label>
                    <select class="form-select" name="default_status" required>
                        <option value="Present" <?php echo ($settings['default_status'] === 'Present') ? 'selected' : ''; ?>>Present</option>
                        <option value="Absent" <?php echo ($settings['default_status'] === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                        <option value="Late" <?php echo ($settings['default_status'] === 'Late') ? 'selected' : ''; ?>>Late</option>
                        <option value="Leave" <?php echo ($settings['default_status'] === 'Leave') ? 'selected' : ''; ?>>Leave</option>
                    </select>
                </div>
            </div>

            <!-- Locking policies -->
            <div class="col-md-6">
                <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-lock text-primary me-2"></i>Locks & Permissions</h6>
                <div class="mb-4">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="allow_edit" id="allowEditSwitch" <?php echo ($settings['allow_edit'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold text-dark" for="allowEditSwitch">Allow Attendance Editing</label>
                    </div>
                    <span class="small text-muted d-block mt-1">Toggles whether teachers/staff can overwrite past attendance records.</span>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Attendance Lock Period (Hours) *</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="lock_hours" value="<?php echo $settings['lock_hours']; ?>" min="1" required>
                        <span class="input-group-text bg-light text-secondary">Hours</span>
                    </div>
                    <span class="small text-muted d-block mt-1">Period after which marked daily sheets lock from edits.</span>
                </div>
            </div>

            <div class="col-12 border-top pt-4"></div>

            <!-- Calendar selection -->
            <div class="col-md-6 border-end pr-md-4">
                <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-briefcase text-primary me-2"></i>Working Days Selection</h6>
                <div class="row g-2">
                    <?php foreach ($daysOfWeek as $day): ?>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="working_days[]" value="<?php echo $day; ?>" id="work_<?php echo $day; ?>" <?php echo in_array($day, $workingDaysArray) ? 'checked' : ''; ?>>
                                <label class="form-check-label text-dark small" for="work_<?php echo $day; ?>"><?php echo $day; ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-md-6">
                <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-umbrella-beach text-primary me-2"></i>Weekend / Off Days</h6>
                <div class="row g-2">
                    <?php foreach ($daysOfWeek as $day): ?>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="weekend_days[]" value="<?php echo $day; ?>" id="week_<?php echo $day; ?>" <?php echo in_array($day, $weekendDaysArray) ? 'checked' : ''; ?>>
                                <label class="form-check-label text-dark small" for="week_<?php echo $day; ?>"><?php echo $day; ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-12 text-end border-top pt-3 mt-4">
                <button type="submit" id="saveBtn" class="btn btn-primary px-4"><i class="fa-solid fa-circle-check me-2"></i>Save Settings</button>
                <a href="settings.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>
</div>

<script>
// Save spinner loader
document.getElementById("settingsForm").addEventListener("submit", function() {
    const btn = document.getElementById("saveBtn");
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';
});
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
