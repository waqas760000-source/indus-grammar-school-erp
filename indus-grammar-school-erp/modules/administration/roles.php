<?php
/**
 * Indus Grammar School ERP - Role Management Panel
 * Version 4.0.0
 */

$pageTitle = 'Role Management';
$breadcrumbActive = 'Administration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

$db = Database::getConnection();

// Fetch all system roles with assigned user count
$roles = [];
try {
    $roles = $db->query("
        SELECT r.*, COUNT(u.id) as user_count 
        FROM roles r 
        LEFT JOIN users u ON r.id = u.role_id 
        GROUP BY r.id 
        ORDER BY r.id ASC
    ")->fetchAll();
} catch (Exception $e) {
    error_log("Error loading roles: " . $e->getMessage());
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-user-shield me-2 text-primary"></i>Role Management</h3>
        <p class="text-muted small mb-0">Create new user groups, modify descriptions, and link custom permission profiles.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#addRoleModal">
            <i class="fa-solid fa-plus me-2"></i>Add Role
        </button>
    </div>
</div>

<!-- Roles Table -->
<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-0 text-secondary">System Security Roles</h5>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th width="80">ID</th>
                    <th>Role Name</th>
                    <th>Role Code</th>
                    <th>Description</th>
                    <th class="text-center" width="150">Assigned Users</th>
                    <th class="text-end" width="250">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $r): ?>
                    <tr>
                        <td class="fw-semibold">#<?php echo $r['id']; ?></td>
                        <td class="fw-bold text-dark"><?php echo sanitize($r['name']); ?></td>
                        <td><code class="text-muted"><?php echo sanitize($r['code']); ?></code></td>
                        <td class="text-muted small"><?php echo sanitize($r['description'] ?: '—'); ?></td>
                        <td class="text-center fw-bold"><?php echo $r['user_count']; ?></td>
                        <td class="text-end">
                            <a href="permissions.php?role_id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary px-3 me-1">
                                <i class="fa-solid fa-key me-1"></i>Permissions
                            </a>
                            <button class="btn btn-sm btn-outline-secondary btn-edit-role me-1" 
                                    data-id="<?php echo $r['id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($r['name']); ?>" 
                                    data-desc="<?php echo htmlspecialchars($r['description'] ?? ''); ?>" 
                                    title="Edit Role">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <?php if ($r['id'] > 3): ?>
                                <button class="btn btn-sm btn-outline-danger btn-delete-role" data-id="<?php echo $r['id']; ?>" title="Delete Role">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-user-shield me-2 text-primary"></i>Add Custom Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="addRoleForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_role">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Role Name *</label>
                        <input type="text" class="form-control" name="name" required placeholder="e.g. Receptionist">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Role Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Briefly describe what this role oversees..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addRoleForm" class="btn btn-sm btn-primary px-4" id="btnAddRole">Add Role</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Modify Role Info</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="editRoleForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="edit_role">
                    <input type="hidden" name="id" id="edit_role_id">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Role Name *</label>
                        <input type="text" class="form-control" name="name" id="edit_role_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Role Description</label>
                        <textarea class="form-control" name="description" id="edit_role_desc" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editRoleForm" class="btn btn-sm btn-primary px-4" id="btnEditRole">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="roleToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="roleToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("roleToast");
    const m = document.getElementById("roleToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Add Role
    const addForm = document.getElementById("addRoleForm");
    if (addForm) {
        addForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnAddRole");
            btn.disabled = true; btn.innerHTML = "Creating...";
            fetch("../../ajax/admin.php", { method: "POST", body: new FormData(addForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Add Role"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "Add Role"; });
        });
    }

    // Edit Modal Bind
    document.querySelectorAll(".btn-edit-role").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("edit_role_id").value = this.dataset.id;
            document.getElementById("edit_role_name").value = this.dataset.name;
            document.getElementById("edit_role_desc").value = this.dataset.desc;
            new bootstrap.Modal(document.getElementById("editRoleModal")).show();
        });
    });

    // Save Edit Role
    const editForm = document.getElementById("editRoleForm");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnEditRole");
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

    // Delete Role
    document.querySelectorAll(".btn-delete-role").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            if(!confirm("Are you sure you want to permanently delete this custom role? This action cannot be undone.")) return;
            const fd = new FormData();
            fd.append("action", "delete_role");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            fetch("../../ajax/admin.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
