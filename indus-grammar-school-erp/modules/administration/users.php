<?php
/**
 * Indus Grammar School ERP - User Management
 * Version 1.0.0
 */

$pageTitle = 'User Management';
$breadcrumbActive = 'Administration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

// Fetch all users
$users = [];
try {
    $db = Database::getConnection();
    $stmt = $db->query("SELECT u.*, r.name as role_name, r.code as role_code FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.id ASC");
    $users = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch roles
$roles = [];
try {
    $db = Database::getConnection();
    $roles = $db->query("SELECT * FROM roles ORDER BY id")->fetchAll();
} catch (Exception $e) {}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-users-cog me-2 text-primary"></i>User Management</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fa-solid fa-user-plus me-2"></i>Add New User
        </button>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-0 text-secondary">Registered System Users</h5>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="fw-semibold">#<?php echo $u['id']; ?></td>
                        <td class="fw-semibold text-dark"><?php echo sanitize($u['username']); ?></td>
                        <td><?php echo sanitize($u['email']); ?></td>
                        <td>
                            <?php
                                $roleBg = ['super_admin' => 'danger', 'school_admin' => 'primary', 'accountant' => 'success'];
                                $c = $roleBg[$u['role_code']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $c; ?> px-3 py-2 rounded-pill"><?php echo sanitize($u['role_name']); ?></span>
                        </td>
                        <td>
                            <span class="badge badge-soft-<?php echo $u['is_active'] ? 'success' : 'danger'; ?> px-3 py-2 rounded-pill">
                                <?php echo $u['is_active'] ? 'Active' : 'Disabled'; ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?php echo $u['last_login'] ? date('d M Y, h:i A', strtotime($u['last_login'])) : 'Never'; ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-<?php echo $u['is_active'] ? 'warning' : 'success'; ?> btn-toggle-status" data-id="<?php echo $u['id']; ?>" data-active="<?php echo $u['is_active']; ?>" title="<?php echo $u['is_active'] ? 'Disable' : 'Enable'; ?>">
                                <i class="fa-solid <?php echo $u['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="addUserForm" method="POST" action="../../ajax/dashboard.php">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="create_user">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password</label>
                        <input type="password" class="form-control" name="password" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Role</label>
                        <select class="form-select" name="role_id" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id']; ?>"><?php echo sanitize($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addUserForm" class="btn btn-primary px-4" id="btnAddUser">Create User</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="usrToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="usrToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("usrToast");
    const m = document.getElementById("usrToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Add User
    const form = document.getElementById("addUserForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnAddUser");
            btn.disabled = true; btn.innerHTML = "Creating...";
            fetch("../../ajax/dashboard.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Create User"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "Create User"; });
        });
    }

    // Toggle Status
    document.querySelectorAll(".btn-toggle-status").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            const isActive = this.dataset.active;
            if(!confirm("Are you sure you want to " + (isActive == "1" ? "disable" : "enable") + " this user?")) return;
            const fd = new FormData();
            fd.append("action", "toggle_user_status");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("user_id", id);
            fd.append("is_active", isActive == "1" ? "0" : "1");
            fetch("../../ajax/dashboard.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => { showToast(data.message, data.success); if(data.success) setTimeout(() => location.reload(), 1000); });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
