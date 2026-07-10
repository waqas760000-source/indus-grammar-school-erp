<?php
/**
 * Indus Grammar School ERP - Staff Attendance
 * Version 1.0.0
 */

$pageTitle      = 'Staff Attendance';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('attendance_mark');

$selectedDate = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');

try {
    $db = Database::getConnection();
    // Fetch all users with their today's attendance
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.email, r.name as role_name,
               sa.status as current_status, sa.check_in_time, sa.check_out_time, sa.remarks
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN staff_attendance sa ON sa.user_id = u.id AND sa.date = :date
        WHERE u.is_active = 1
        ORDER BY r.id ASC, u.username ASC
    ");
    $stmt->execute(['date' => $selectedDate]);
    $staffList = $stmt->fetchAll();
} catch (Exception $e) {
    $staffList = [];
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-user-tie me-2 text-primary"></i>Staff Attendance</h3>
    </div>
    <div class="col-sm-6 text-sm-end">
        <form method="GET" class="d-inline-flex gap-2">
            <input type="date" name="date" class="form-control form-control-sm" value="<?php echo $selectedDate; ?>" max="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()">
        </form>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-0 text-secondary">
            <i class="fa-solid fa-list-check me-2"></i>Staff Attendance — <?php echo date('D, d M Y', strtotime($selectedDate)); ?>
        </h5>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Remarks</th>
                    <th class="text-end">Save</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffList as $i => $s): ?>
                    <?php $cur = $s['current_status'] ?? 'Present'; ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark"><?php echo sanitize(ucfirst($s['username'])); ?></div>
                            <div class="text-muted small"><?php echo sanitize($s['email']); ?></div>
                        </td>
                        <td><span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill"><?php echo sanitize($s['role_name']); ?></span></td>
                        <td>
                            <select class="form-select form-select-sm staff-status" data-uid="<?php echo $s['id']; ?>" style="width:auto;">
                                <?php foreach (['Present','Absent','Late','Leave'] as $st): ?>
                                    <option value="<?php echo $st; ?>" <?php echo ($cur === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="time" class="form-control form-control-sm staff-checkin"
                                data-uid="<?php echo $s['id']; ?>"
                                value="<?php echo $s['check_in_time'] ?? ''; ?>" style="width:120px;">
                        </td>
                        <td>
                            <input type="time" class="form-control form-control-sm staff-checkout"
                                data-uid="<?php echo $s['id']; ?>"
                                value="<?php echo $s['check_out_time'] ?? ''; ?>" style="width:120px;">
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm staff-remarks"
                                data-uid="<?php echo $s['id']; ?>"
                                value="<?php echo sanitize($s['remarks'] ?? ''); ?>" placeholder="Optional..." style="min-width:150px;">
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-primary btn-save-staff" data-uid="<?php echo $s['id']; ?>"
                                data-date="<?php echo $selectedDate; ?>">
                                <i class="fa-solid fa-save"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="staffToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="staffToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
document.addEventListener("DOMContentLoaded", function() {
    function showToast(msg, ok) {
        const t = document.getElementById("staffToast");
        const m = document.getElementById("staffToastMsg");
        t.classList.remove("bg-success","bg-danger");
        t.classList.add(ok ? "bg-success" : "bg-danger");
        m.textContent = msg;
        new bootstrap.Toast(t).show();
    }

    document.querySelectorAll(".btn-save-staff").forEach(function(btn) {
        btn.addEventListener("click", function() {
            const uid   = this.dataset.uid;
            const date  = this.dataset.date;
            const row   = this.closest("tr");
            const status   = row.querySelector(".staff-status").value;
            const checkIn  = row.querySelector(".staff-checkin").value;
            const checkOut = row.querySelector(".staff-checkout").value;
            const remarks  = row.querySelector(".staff-remarks").value;

            const fd = new FormData();
            fd.append("action",      "mark_staff");
            fd.append("csrf_token",  "' . csrfToken() . '");
            fd.append("user_id",     uid);
            fd.append("date",        date);
            fd.append("status",      status);
            fd.append("check_in",    checkIn);
            fd.append("check_out",   checkOut);
            fd.append("remarks",     remarks);

            this.disabled = true;
            this.innerHTML = \'<span class="spinner-border spinner-border-sm"></span>\';

            fetch("../../ajax/attendance.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    this.disabled = false;
                    this.innerHTML = \'<i class="fa-solid fa-save"></i>\';
                })
                .catch(() => {
                    showToast("Network error.", false);
                    this.disabled = false;
                    this.innerHTML = \'<i class="fa-solid fa-save"></i>\';
                });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
