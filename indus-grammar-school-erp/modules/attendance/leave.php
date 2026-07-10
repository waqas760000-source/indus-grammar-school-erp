<?php
/**
 * Indus Grammar School ERP - Leave Applications
 * Version 1.0.0
 */

$pageTitle      = 'Leave Management';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('attendance_view');

try {
    $db = Database::getConnection();
    $stmt = $db->query("
        SELECT la.*,
            CASE la.applicant_type
                WHEN 'student' THEN CONCAT(s.first_name, ' ', s.last_name, ' (', s.admission_no, ')')
                WHEN 'staff'   THEN u.username
                ELSE 'Unknown'
            END as applicant_name
        FROM leave_applications la
        LEFT JOIN students s ON la.applicant_type = 'student' AND la.applicant_id = s.id
        LEFT JOIN users u   ON la.applicant_type = 'staff'   AND la.applicant_id = u.id
        ORDER BY la.created_at DESC
        LIMIT 50
    ");
    $leaves = $stmt->fetchAll();

    // Students and users for new application form
    $allStudents = Student::all([], 200, 0);
    $allUsers    = $db->query("SELECT id, username FROM users WHERE is_active = 1")->fetchAll();
} catch (Exception $e) {
    $leaves = []; $allStudents = []; $allUsers = [];
}

$statusBadge = ['Pending' => 'warning', 'Approved' => 'success', 'Rejected' => 'danger'];
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-umbrella-beach me-2 text-primary"></i>Leave Applications</h3>
    </div>
    <div class="col-sm-6 text-sm-end">
        <?php if (hasPermission('attendance_mark')): ?>
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#newLeaveModal">
            <i class="fa-solid fa-plus me-2"></i>New Application
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Type</th>
                    <th>Leave Type</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Days</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <?php if (hasPermission('attendance_mark')): ?><th class="text-end">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaves)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No leave applications found.</td></tr>
                <?php else: foreach ($leaves as $l):
                    $days = (int)((strtotime($l['end_date']) - strtotime($l['start_date'])) / 86400) + 1;
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo sanitize($l['applicant_name']); ?></div>
                            <div class="text-muted small"><?php echo date('d M Y', strtotime($l['created_at'])); ?></div>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?php echo ucfirst($l['applicant_type']); ?></span></td>
                        <td><?php echo sanitize($l['leave_type']); ?></td>
                        <td><?php echo date('d M Y', strtotime($l['start_date'])); ?></td>
                        <td><?php echo date('d M Y', strtotime($l['end_date'])); ?></td>
                        <td class="fw-bold"><?php echo $days; ?></td>
                        <td><span class="text-muted" title="<?php echo sanitize($l['reason']); ?>"><?php echo sanitize(mb_substr($l['reason'], 0, 40)) . (mb_strlen($l['reason']) > 40 ? '…' : ''); ?></span></td>
                        <td><span class="badge badge-soft-<?php echo $statusBadge[$l['status']] ?? 'secondary'; ?> px-3 py-2 rounded-pill"><?php echo $l['status']; ?></span></td>
                        <?php if (hasPermission('attendance_mark')): ?>
                        <td class="text-end">
                            <?php if ($l['status'] === 'Pending'): ?>
                                <button class="btn btn-sm btn-success btn-leave-action" data-id="<?php echo $l['id']; ?>" data-action="Approved" title="Approve"><i class="fa-solid fa-check"></i></button>
                                <button class="btn btn-sm btn-danger btn-leave-action" data-id="<?php echo $l['id']; ?>" data-action="Rejected" title="Reject"><i class="fa-solid fa-times"></i></button>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New Leave Application Modal -->
<div class="modal fade" id="newLeaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-calendar-plus me-2 text-primary"></i>New Leave Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Applicant Type</label>
                    <select class="form-select" id="lv-type" onchange="toggleApplicant()">
                        <option value="student">Student</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div class="mb-3" id="student-select">
                    <label class="form-label small fw-semibold">Student</label>
                    <select class="form-select" id="lv-student-id">
                        <?php foreach ($allStudents as $st): ?>
                            <option value="<?php echo $st['id']; ?>"><?php echo sanitize($st['first_name'].' '.$st['last_name'].' ('.$st['admission_no'].')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3 d-none" id="staff-select">
                    <label class="form-label small fw-semibold">Staff Member</label>
                    <select class="form-select" id="lv-staff-id">
                        <?php foreach ($allUsers as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo sanitize($u['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Leave Type</label>
                    <select class="form-select" id="lv-leave-type">
                        <option>Sick Leave</option><option>Casual</option><option>Emergency</option><option>Earned Leave</option><option>Other</option>
                    </select>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6"><label class="form-label small fw-semibold">Start Date</label><input type="date" class="form-control" id="lv-start"></div>
                    <div class="col-6"><label class="form-label small fw-semibold">End Date</label><input type="date" class="form-control" id="lv-end"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Reason</label>
                    <textarea class="form-control" id="lv-reason" rows="3" placeholder="Reason for leave..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="submitLeaveBtn">Submit Application</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="leaveToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="leaveToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function toggleApplicant() {
    const t = document.getElementById("lv-type").value;
    document.getElementById("student-select").classList.toggle("d-none", t === "staff");
    document.getElementById("staff-select").classList.toggle("d-none", t === "student");
}

function showToast(msg, ok) {
    const t = document.getElementById("leaveToast");
    const m = document.getElementById("leaveToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Submit new leave
    document.getElementById("submitLeaveBtn")?.addEventListener("click", function() {
        const type = document.getElementById("lv-type").value;
        const appId = type === "student"
            ? document.getElementById("lv-student-id").value
            : document.getElementById("lv-staff-id").value;

        const fd = new FormData();
        fd.append("action", "apply_leave");
        fd.append("csrf_token", "' . csrfToken() . '");
        fd.append("applicant_type", type);
        fd.append("applicant_id", appId);
        fd.append("leave_type", document.getElementById("lv-leave-type").value);
        fd.append("start_date", document.getElementById("lv-start").value);
        fd.append("end_date", document.getElementById("lv-end").value);
        fd.append("reason", document.getElementById("lv-reason").value);

        this.disabled = true;
        fetch("../../ajax/attendance.php", { method:"POST", body:fd })
            .then(r => r.json())
            .then(data => {
                showToast(data.message, data.success);
                this.disabled = false;
                if (data.success) setTimeout(() => location.reload(), 1200);
            })
            .catch(() => { showToast("Network error.", false); this.disabled = false; });
    });

    // Approve / Reject
    document.querySelectorAll(".btn-leave-action").forEach(function(btn) {
        btn.addEventListener("click", function() {
            const fd = new FormData();
            fd.append("action", "update_leave");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", this.dataset.id);
            fd.append("new_status", this.dataset.action);
            fetch("../../ajax/attendance.php", { method:"POST", body:fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 1200);
                });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
