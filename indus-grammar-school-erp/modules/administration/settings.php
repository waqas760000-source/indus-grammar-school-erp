<?php
/**
 * Indus Grammar School ERP - School Settings Submodule
 * Version 4.0.0
 */

$pageTitle = 'School Settings';
$breadcrumbActive = 'Administration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

$db = Database::getConnection();

// Load current configuration settings from DB
$settings = [];
try {
    $settings = $db->query("SELECT * FROM school_settings LIMIT 1")->fetch();
} catch (Exception $e) {}

if (!$settings) {
    $settings = [
        'school_name'              => SCHOOL_NAME,
        'school_logo'              => null,
        'school_address'           => SCHOOL_ADDRESS,
        'city'                     => 'Karachi',
        'phone_number'             => SCHOOL_PHONE,
        'whatsapp_number'          => SCHOOL_PHONE,
        'email'                    => SCHOOL_EMAIL,
        'website'                  => 'www.indus.edu.pk',
        'principal_name'           => 'Dr. Sajid Ali',
        'owner_name'               => SCHOOL_OWNER,
        'registration_number'      => 'REG-IGS-2026',
        'current_academic_session' => CURRENT_ACADEMIC_YEAR,
        'timezone'                 => 'Asia/Karachi',
        'currency'                 => 'PKR',
        'date_format'              => 'Y-m-d'
    ];
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-sliders me-2 text-primary"></i>School Settings</h3>
        <p class="text-muted small mb-0">Configure metadata, contact info, default formats, and academic sessions of the school.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Configuration Form -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-4"><i class="fa-solid fa-school-flag me-2 text-primary"></i>School Information Profile</h5>
                
                <form id="schoolSettingsForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_school_settings">
                    <input type="hidden" name="existing_logo" value="<?php echo htmlspecialchars($settings['school_logo'] ?? ''); ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">School Name *</label>
                            <input type="text" class="form-control" name="school_name" value="<?php echo htmlspecialchars($settings['school_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">School Registration Number</label>
                            <input type="text" class="form-control" name="registration_number" value="<?php echo htmlspecialchars($settings['registration_number'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Owner / Director *</label>
                            <input type="text" class="form-control" name="owner_name" value="<?php echo htmlspecialchars($settings['owner_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Principal Name *</label>
                            <input type="text" class="form-control" name="principal_name" value="<?php echo htmlspecialchars($settings['principal_name']); ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address *</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($settings['email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone Number *</label>
                            <input type="text" class="form-control" name="phone_number" value="<?php echo htmlspecialchars($settings['phone_number']); ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">WhatsApp Number</label>
                            <input type="text" class="form-control" name="whatsapp_number" value="<?php echo htmlspecialchars($settings['whatsapp_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Website URL</label>
                            <input type="text" class="form-control" name="website" value="<?php echo htmlspecialchars($settings['website'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">City *</label>
                            <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($settings['city']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Current Academic Session *</label>
                            <select class="form-select" name="current_academic_session" required>
                                <?php
                                try {
                                    $sessions = $db->query("SELECT session_name FROM academic_sessions ORDER BY session_name ASC")->fetchAll(PDO::FETCH_COLUMN);
                                    if (empty($sessions)) $sessions = ['2025-2026', '2026-2027', '2027-2028'];
                                    foreach ($sessions as $s) {
                                        $selected = ($settings['current_academic_session'] === $s) ? 'selected' : '';
                                        echo "<option value='$s' $selected>$s</option>";
                                    }
                                } catch (Exception $e) {}
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Timezone *</label>
                            <input type="text" class="form-control" name="timezone" value="<?php echo htmlspecialchars($settings['timezone']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Currency Symbol *</label>
                            <input type="text" class="form-control" name="currency" value="<?php echo htmlspecialchars($settings['currency']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Date Format *</label>
                            <select class="form-select" name="date_format" required>
                                <option value="Y-m-d" <?php echo ($settings['date_format'] === 'Y-m-d') ? 'selected' : ''; ?>>YYYY-MM-DD (e.g. <?php echo date('Y-m-d'); ?>)</option>
                                <option value="d-m-Y" <?php echo ($settings['date_format'] === 'd-m-Y') ? 'selected' : ''; ?>>DD-MM-YYYY (e.g. <?php echo date('d-m-Y'); ?>)</option>
                                <option value="m/d/Y" <?php echo ($settings['date_format'] === 'm/d/Y') ? 'selected' : ''; ?>>MM/DD/YYYY (e.g. <?php echo date('m/d/Y'); ?>)</option>
                                <option value="d M Y" <?php echo ($settings['date_format'] === 'd M Y') ? 'selected' : ''; ?>>DD Mon YYYY (e.g. <?php echo date('d M Y'); ?>)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">School Postal Address *</label>
                        <textarea class="form-control" name="school_address" rows="3" required><?php echo htmlspecialchars($settings['school_address']); ?></textarea>
                    </div>

                    <div class="row g-3 align-items-center mb-4">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">School Logo Image</label>
                            <input type="file" class="form-control" name="school_logo" accept="image/*">
                        </div>
                        <div class="col-md-4 text-center">
                            <?php if (!empty($settings['school_logo'])): ?>
                                <div class="p-2 border rounded bg-light d-inline-block">
                                    <img src="<?php echo APP_URL . '/' . $settings['school_logo']; ?>" alt="Logo Preview" style="max-height: 70px;">
                                </div>
                            <?php else: ?>
                                <span class="text-muted small">No Logo Uploaded</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="text-end border-top pt-4">
                        <button type="reset" class="btn btn-outline-secondary px-4 me-2">Reset</button>
                        <button type="submit" class="btn btn-primary px-5" id="btnSaveSettings"><i class="fa-solid fa-save me-2"></i>Save Configuration</button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- Quick Options & Status -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-4"><i class="fa-solid fa-bolt me-2 text-primary"></i>Administration Menu</h5>
                <div class="d-grid gap-2">
                    <a href="users.php" class="btn btn-outline-primary text-start"><i class="fa-solid fa-users-cog me-2"></i>User Management</a>
                    <a href="roles.php" class="btn btn-outline-primary text-start"><i class="fa-solid fa-user-shield me-2"></i>Roles & Permissions</a>
                    <a href="academic.php" class="btn btn-outline-primary text-start"><i class="fa-solid fa-graduation-cap me-2"></i>Academic Settings</a>
                    <a href="backup.php" class="btn btn-outline-primary text-start"><i class="fa-solid fa-database me-2"></i>Backup & Restore</a>
                    <a href="logs.php" class="btn btn-outline-primary text-start"><i class="fa-solid fa-clipboard-list me-2"></i>Audit Activity Logs</a>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm" style="border-radius:12px; background: linear-gradient(135deg, #fff, #f0fdf4);">
            <div class="card-body p-4 text-center">
                <div class="d-inline-block p-3 rounded-circle bg-success-soft mb-3">
                    <i class="fa-solid fa-circle-check fs-2 text-success"></i>
                </div>
                <h5 class="fw-bold text-success mb-1">System Health</h5>
                <p class="small text-muted mb-3">All services online and fully operational.</p>
                <span class="badge bg-success rounded-pill px-4 py-2">Online</span>
            </div>
        </div>
    </div>
</div>

<!-- Toast Feedback -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="settingsToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="settingsToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("settingsToast");
    const m = document.getElementById("settingsToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("schoolSettingsForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSaveSettings");
            btn.disabled = true; btn.innerHTML = "Saving...";
            
            fetch("../../ajax/admin.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-save me-2\"></i>Save Configuration"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-save me-2\"></i>Save Configuration"; });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
