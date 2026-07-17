<?php
/**
 * Indus Grammar School ERP - User Management Panel (Normalized)
 * Version 4.0.0
 */

$pageTitle = 'User Management';
$breadcrumbActive = 'Administration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

$db = Database::getConnection();

// Retrieve filters
$searchName = sanitize($_GET['search_name'] ?? '');
$searchUser = sanitize($_GET['search_username'] ?? '');
$searchRole = (int)($_GET['search_role'] ?? 0);
$searchStatus = $_GET['search_status'] ?? '';

// Build database query based on filters
$where = " WHERE 1=1";
$params = [];

if ($searchName !== '') {
    $where .= " AND u.full_name LIKE :name";
    $params['name'] = '%' . $searchName . '%';
}
if ($searchUser !== '') {
    $where .= " AND u.username LIKE :username";
    $params['username'] = '%' . $searchUser . '%';
}
if ($searchRole > 0) {
    $where .= " AND u.role_id = :role";
    $params['role'] = $searchRole;
}
if ($searchStatus !== '') {
    $where .= " AND u.is_active = :active";
    $params['active'] = ($searchStatus === 'Active') ? 1 : 0;
}

$users = [];
try {
    $stmt = $db->prepare("
        SELECT u.*, r.name as role_name, r.code as role_code 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        $where 
        ORDER BY u.id ASC
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error loading users: " . $e->getMessage());
}

// Fetch all available roles
$roles = [];
try {
    $roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
} catch (Exception $e) {}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-users-cog me-2 text-primary"></i>User Management</h3>
        <p class="text-muted small mb-0">Add, edit, manage permissions, and reset credentials for system operators.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fa-solid fa-user-plus me-2"></i>Add User
        </button>
    </div>
</div>

<!-- Search Filters Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Search System Users</h6>
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Full Name</label>
                <input type="text" class="form-control form-control-sm" name="search_name" value="<?php echo htmlspecialchars($searchName); ?>" placeholder="Search Name">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Username</label>
                <input type="text" class="form-control form-control-sm" name="search_username" value="<?php echo htmlspecialchars($searchUser); ?>" placeholder="Search Username">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">System Role</label>
                <select class="form-select form-select-sm" name="search_role">
                    <option value="">All Roles</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r['id']; ?>" <?php echo ($searchRole == $r['id']) ? 'selected' : ''; ?>><?php echo sanitize($r['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select class="form-select form-select-sm" name="search_status">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo ($searchStatus === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo ($searchStatus === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-12 text-end">
                <a href="users.php" class="btn btn-sm btn-outline-secondary px-3 me-2">Reset Filters</a>
                <button type="submit" class="btn btn-sm btn-primary px-4"><i class="fa-solid fa-magnifying-glass me-2"></i>Search User</button>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th width="80">Photo</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No system users found.</td></tr>
                <?php else: foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <?php if (!empty($u['profile_photo'])): ?>
                                <img src="<?php echo APP_URL . '/' . $u['profile_photo']; ?>" class="rounded-circle border" width="40" height="40" alt="Avatar" style="object-fit:cover;">
                            <?php else: ?>
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center border" style="width:40px; height:40px;">
                                    <i class="fa-solid fa-user text-secondary"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold text-dark"><?php echo sanitize($u['full_name'] ?: 'N/A'); ?></td>
                        <td><code><?php echo sanitize($u['username']); ?></code></td>
                        <td><?php echo sanitize($u['email']); ?></td>
                        <td><?php echo sanitize($u['mobile_no'] ?: '—'); ?></td>
                        <td>
                            <?php
                            $roleBg = ['super_admin' => 'danger', 'school_admin' => 'primary', 'accountant' => 'success', 'teacher' => 'info'];
                            $cls = $roleBg[$u['role_code']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $cls; ?>-soft px-3 py-2 rounded-pill"><?php echo sanitize($u['role_name']); ?></span>
                        </td>
                        <td>
                            <span class="badge badge-soft-<?php echo $u['is_active'] ? 'success' : 'danger'; ?> px-3 py-2 rounded-pill">
                                <?php echo $u['is_active'] ? 'Active' : 'Disabled'; ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?php echo $u['last_login'] ? date('d M Y, h:i A', strtotime($u['last_login'])) : 'Never'; ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary btn-edit-user" 
                                    data-id="<?php echo $u['id']; ?>"
                                    data-fullname="<?php echo htmlspecialchars($u['full_name'] ?? ''); ?>"
                                    data-email="<?php echo htmlspecialchars($u['email']); ?>"
                                    data-mobile="<?php echo htmlspecialchars($u['mobile_no'] ?? ''); ?>"
                                    data-role="<?php echo $u['role_id']; ?>"
                                    data-active="<?php echo $u['is_active']; ?>"
                                    title="Edit User">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning btn-reset-pass" data-id="<?php echo $u['id']; ?>" title="Reset Password">
                                <i class="fa-solid fa-key"></i>
                            </button>
                            <?php if ($u['id'] !== 1): ?>
                                <button class="btn btn-sm btn-outline-<?php echo $u['is_active'] ? 'danger' : 'success'; ?> btn-toggle-status" data-id="<?php echo $u['id']; ?>" data-active="<?php echo $u['is_active']; ?>" title="<?php echo $u['is_active'] ? 'Disable User' : 'Enable User'; ?>">
                                    <i class="fa-solid <?php echo $u['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="addUserForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="create_user">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Username *</label>
                            <input type="text" class="form-control form-control-sm" name="username" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Full Name</label>
                            <input type="text" class="form-control form-control-sm" name="full_name">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Email Address *</label>
                            <input type="email" class="form-control form-control-sm" name="email" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Mobile Number</label>
                            <input type="text" class="form-control form-control-sm" name="mobile_no" placeholder="e.g. +92 300 1234567">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password *</label>
                        <input type="password" class="form-control form-control-sm" name="password" minlength="8" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Assign Role *</label>
                            <select class="form-select form-select-sm" name="role_id" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"><?php echo sanitize($r['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Status</label>
                            <select class="form-select form-select-sm" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Avatar Photo</label>
                        <input type="file" class="form-control form-control-sm" name="profile_photo" accept="image/*">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addUserForm" class="btn btn-sm btn-primary px-4" id="btnAddUser">Create User</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Modify User Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="editUserForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Full Name</label>
                        <input type="text" class="form-control form-control-sm" name="full_name" id="edit_fullname">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Email Address *</label>
                            <input type="email" class="form-control form-control-sm" name="email" id="edit_email" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Mobile Number</label>
                            <input type="text" class="form-control form-control-sm" name="mobile_no" id="edit_mobile">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Assign Role *</label>
                            <select class="form-select form-select-sm" name="role_id" id="edit_role_id" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"><?php echo sanitize($r['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Account Status</label>
                            <select class="form-select form-select-sm" name="is_active" id="edit_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Change Password (Optional)</label>
                        <input type="password" class="form-control form-control-sm" name="password" minlength="8" placeholder="Leave blank to keep current">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Update Avatar Photo</label>
                        <input type="file" class="form-control form-control-sm" name="profile_photo" accept="image/*">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editUserForm" class="btn btn-sm btn-primary px-4" id="btnEditUser">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPassModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-key me-2 text-warning"></i>Reset Credentials</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="resetPassForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="id" id="reset_user_id">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">New Password</label>
                        <input type="password" class="form-control" name="password" id="reset_password_field" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Confirm New Password</label>
                        <input type="password" class="form-control" id="reset_confirm_password_field" minlength="8" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="resetPassForm" class="btn btn-sm btn-warning px-4" id="btnResetPass">Reset</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Feedback -->
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
    // Add User Form Submission
    const addForm = document.getElementById("addUserForm");
    if (addForm) {
        addForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnAddUser");
            btn.disabled = true; btn.innerHTML = "Creating...";
            
            fetch("../../ajax/admin.php", { method: "POST", body: new FormData(addForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Create User"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "Create User"; });
        });
    }

    // Bind Edit Modal Values
    document.querySelectorAll(".btn-edit-user").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("edit_id").value = this.dataset.id;
            document.getElementById("edit_fullname").value = this.dataset.fullname;
            document.getElementById("edit_email").value = this.dataset.email;
            document.getElementById("edit_mobile").value = this.dataset.mobile;
            document.getElementById("edit_role_id").value = this.dataset.role;
            document.getElementById("edit_active").value = this.dataset.active;
            
            new bootstrap.Modal(document.getElementById("editUserModal")).show();
        });
    });

    // Save Edit Form
    const editForm = document.getElementById("editUserForm");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnEditUser");
            btn.disabled = true; btn.innerHTML = "Saving...";
            
            fetch("../../ajax/admin.php", { method: "POST", body: new FormData(editForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Save Changes"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "Save Changes"; });
        });
    }

    // Reset Password Bind
    document.querySelectorAll(".btn-reset-pass").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("reset_user_id").value = this.dataset.id;
            document.getElementById("reset_password_field").value = "";
            document.getElementById("reset_confirm_password_field").value = "";
            new bootstrap.Modal(document.getElementById("resetPassModal")).show();
        });
    });

    // Save Reset Password Form
    const resetForm = document.getElementById("resetPassForm");
    if (resetForm) {
        resetForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const p1 = document.getElementById("reset_password_field").value;
            const p2 = document.getElementById("reset_confirm_password_field").value;
            if (p1 !== p2) {
                showToast("Passwords do not match.", false);
                return;
            }
            
            const btn = document.getElementById("btnResetPass");
            btn.disabled = true; btn.innerHTML = "Resetting...";
            
            fetch("../../ajax/admin.php", { method: "POST", body: new FormData(resetForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Reset"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "Reset"; });
        });
    }

    // Toggle status button action
    document.querySelectorAll(".btn-toggle-status").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            const isActive = this.dataset.active;
            const actionVerb = isActive == "1" ? "disable" : "enable";
            if (!confirm("Are you sure you want to " + actionVerb + " this user?")) return;
            
            const fd = new FormData();
            fd.append("action", "toggle_user_status");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("user_id", id);
            fd.append("is_active", isActive == "1" ? "0" : "1");
            
            fetch("../../ajax/admin.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 1000);
                });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
