<?php
/**
 * Indus Grammar School ERP - Role Management Panel
 * Version 7.0.0 — Commercial Redesign (User Groups, Permission Linking & Access Control)
 */

$pageTitle = 'Role Management';
$breadcrumbActive = 'Role Management';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

$db = Database::getConnection();

// -------------------------------------------------------------
// Database Role Metrics & Aggregation
// -------------------------------------------------------------
$roles = [];
$totalRolesCount = 0;
$coreRolesCount = 0;
$customRolesCount = 0;
$totalAssignedUsers = 0;

try {
    // 1. Fetch all system roles with assigned user count
    $roles = $db->query("
        SELECT r.*, COUNT(u.id) as user_count 
        FROM roles r 
        LEFT JOIN users u ON r.id = u.role_id 
        GROUP BY r.id 
        ORDER BY r.id ASC
    ")->fetchAll();

    $totalRolesCount = count($roles);
    foreach ($roles as $r) {
        if ($r['id'] <= 3) {
            $coreRolesCount++;
        } else {
            $customRolesCount++;
        }
        $totalAssignedUsers += (int)$r['user_count'];
    }

} catch (Exception $e) {
    error_log("Error loading roles: " . $e->getMessage());
}
?>

<!-- Custom Styling for Role Management Module -->
<style>
:root {
    --primary-navy: #0F172A;
    --primary-blue: #1D4ED8;
    --secondary-blue: #2563EB;
    --light-blue: #EFF6FF;
    --page-background: #F1F5F9;
    --card-white: #FFFFFF;
    --border-color: #E2E8F0;
    --main-text: #1E293B;
    --muted-text: #64748B;
    --success-color: #16A34A;
    --warning-color: #D97706;
    --danger-color: #DC2626;
    --purple-color: #7C3AED;
}

/* Page Canvas Wrapper */
.roles-page-container {
    background-color: var(--page-background);
    border-radius: 18px;
    padding: 1.5rem;
    min-height: calc(100vh - 100px);
}

/* Hero Header Banner (Navy/Royal Blue Gradient) */
.roles-hero-banner {
    background: linear-gradient(135deg, var(--primary-navy) 0%, #1e3a8a 55%, var(--secondary-blue) 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.18);
    position: relative;
    overflow: hidden;
}

.roles-hero-banner::after {
    content: '';
    position: absolute;
    right: -40px;
    bottom: -40px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
    pointer-events: none;
}

.hero-icon-box {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.25);
}

/* KPI Stat Cards */
.roles-kpi-card {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.35rem 1.5rem;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.roles-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -6px rgba(15, 23, 42, 0.09);
    border-color: #cbd5e1;
}

.kpi-accent-navy   { border-top: 4px solid var(--primary-navy); }
.kpi-accent-blue   { border-top: 4px solid var(--primary-blue); }
.kpi-accent-purple { border-top: 4px solid var(--purple-color); }
.kpi-accent-green  { border-top: 4px solid var(--success-color); }

.kpi-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.kpi-icon-navy   { background-color: #f8fafc; color: var(--primary-navy); }
.kpi-icon-blue   { background-color: var(--light-blue); color: var(--primary-blue); }
.kpi-icon-purple { background-color: #f3e8ff; color: var(--purple-color); }
.kpi-icon-green  { background-color: #f0fdf4; color: var(--success-color); }

.kpi-number {
    font-size: 1.95rem;
    font-weight: 800;
    color: var(--primary-navy);
    line-height: 1.2;
    margin-top: 0.4rem;
    margin-bottom: 0.2rem;
}

.kpi-label-text {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--muted-text);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Glass Card & Table Styling */
.glass-panel {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.panel-header {
    padding: 1.1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--card-white);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.roles-table th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.95rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
}

.roles-table td {
    padding: 0.95rem 1.25rem;
    vertical-align: middle;
    font-size: 0.88rem;
    color: var(--main-text);
    border-bottom: 1px solid #f1f5f9;
}

.roles-table tr:last-child td {
    border-bottom: none;
}

.role-avatar-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.badge-code {
    background-color: #f1f5f9;
    color: #475569;
    font-family: var(--bs-font-monospace);
    font-size: 0.8rem;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}
</style>

<div class="roles-page-container">

    <!-- 1. Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item text-muted">Administration</li>
            <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Role Management</li>
        </ol>
    </nav>

    <!-- 2. Hero Header Banner -->
    <div class="roles-hero-banner mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h2 class="fw-bold mb-0 text-white fs-3">Role Management</h2>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">Access Governance</span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">Create new user groups, modify descriptions, and link custom permission profiles.</p>
                </div>
            </div>
            <div>
                <button type="button" class="btn btn-light btn-md fw-bold shadow-sm rounded-3 text-primary px-4 py-2" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                    <i class="fa-solid fa-plus-circle me-1.5"></i> Create New Role
                </button>
            </div>
        </div>
    </div>

    <!-- 3. KPI Summary Row -->
    <div class="row g-3 mb-4">
        <!-- Total Roles -->
        <div class="col-sm-6 col-lg-3">
            <div class="roles-kpi-card kpi-accent-navy">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Total System Roles</span>
                    <div class="kpi-icon kpi-icon-navy">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format($totalRolesCount); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-layer-group text-secondary"></i>
                        <span>Configured access groups</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Core Roles -->
        <div class="col-sm-6 col-lg-3">
            <div class="roles-kpi-card kpi-accent-blue">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Core System Roles</span>
                    <div class="kpi-icon kpi-icon-blue">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format($coreRolesCount); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-circle-check text-primary"></i>
                        <span>Built-in protected roles</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom User Groups -->
        <div class="col-sm-6 col-lg-3">
            <div class="roles-kpi-card kpi-accent-purple">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Custom User Groups</span>
                    <div class="kpi-icon kpi-icon-purple">
                        <i class="fa-solid fa-users-gear"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format($customRolesCount); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-pen-ruler text-purple"></i>
                        <span>Custom admin roles</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Assigned Users -->
        <div class="col-sm-6 col-lg-3">
            <div class="roles-kpi-card kpi-accent-green">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Assigned User Accounts</span>
                    <div class="kpi-icon kpi-icon-green">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format($totalAssignedUsers); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-user-group text-success"></i>
                        <span>Active user assignments</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Search & Filter Toolbar -->
    <div class="glass-panel p-3 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-7 col-lg-8">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="roleSearchInput" class="form-control border-start-0 bg-light" placeholder="Search roles by title, code, or description...">
                </div>
            </div>
            <div class="col-md-5 col-lg-4">
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted fw-semibold text-nowrap">Filter Type:</label>
                    <select id="roleTypeFilter" class="form-select bg-light">
                        <option value="all">All Roles</option>
                        <option value="core">Core System Roles</option>
                        <option value="custom">Custom User Groups</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Roles Governance Data Table -->
    <div class="glass-panel mb-4">
        <div class="panel-header">
            <h5 class="fw-bold mb-0 text-dark fs-6 d-flex align-items-center gap-2">
                <i class="fa-solid fa-list-check text-primary"></i> System Access Roles & Permission Profiles
            </h5>
            <span class="badge bg-primary-subtle text-primary px-3 py-1.5 rounded-pill fw-semibold small">
                Total: <?php echo count($roles); ?> Groups
            </span>
        </div>
        <div class="table-responsive">
            <table class="table roles-table table-hover align-middle mb-0" id="rolesTable">
                <thead>
                    <tr>
                        <th width="70">ID</th>
                        <th>Role Name & Details</th>
                        <th>System Code</th>
                        <th>Description</th>
                        <th class="text-center" width="160">Assigned Users</th>
                        <th class="text-end" width="280">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($roles)): ?>
                        <?php foreach ($roles as $r): ?>
                            <?php 
                            $isCore = ($r['id'] <= 3);
                            $roleType = $isCore ? 'core' : 'custom';
                            
                            // Determine role icon styling based on ID or Code
                            $iconBg = 'bg-primary-subtle text-primary';
                            $roleIcon = 'fa-user-shield';
                            if ($r['id'] == 1) {
                                $iconBg = 'bg-danger-subtle text-danger';
                                $roleIcon = 'fa-user-ninja';
                            } elseif ($r['id'] == 2) {
                                $iconBg = 'bg-primary-subtle text-primary';
                                $roleIcon = 'fa-user-gear';
                            } elseif ($r['id'] == 3) {
                                $iconBg = 'bg-success-subtle text-success';
                                $roleIcon = 'fa-calculator';
                            } elseif ($r['id'] == 8 || str_contains(strtolower($r['name']), 'recept')) {
                                $iconBg = 'bg-warning-subtle text-warning';
                                $roleIcon = 'fa-headset';
                            } else {
                                $iconBg = 'bg-purple-subtle text-purple';
                                $roleIcon = 'fa-users';
                            }
                            ?>
                            <tr class="role-row" data-type="<?php echo $roleType; ?>" data-name="<?php echo htmlspecialchars(strtolower($r['name'])); ?>" data-code="<?php echo htmlspecialchars(strtolower($r['code'])); ?>" data-desc="<?php echo htmlspecialchars(strtolower($r['description'] ?? '')); ?>">
                                <td class="fw-bold text-muted">#<?php echo $r['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="role-avatar-icon <?php echo $iconBg; ?>">
                                            <i class="fa-solid <?php echo $roleIcon; ?>"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark fs-6"><?php echo sanitize($r['name']); ?></div>
                                            <div class="small">
                                                <?php if ($isCore): ?>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5" style="font-size: 0.7rem;">System Protected</span>
                                                <?php else: ?>
                                                    <span class="badge bg-purple-subtle text-purple border border-purple-subtle rounded-pill px-2 py-0.5" style="font-size: 0.7rem;">Custom Group</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-code"><?php echo sanitize($r['code']); ?></span>
                                </td>
                                <td>
                                    <span class="text-muted small"><?php echo sanitize($r['description'] ?: 'No description specified.'); ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-3 py-1.5 rounded-pill fw-bold fs-7 d-inline-flex align-items-center gap-1.5">
                                        <i class="fa-solid fa-users text-primary"></i> <?php echo (int)$r['user_count']; ?> Users
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex align-items-center justify-content-end gap-1.5">
                                        <!-- Link Custom Permission Profile -->
                                        <a href="permissions.php?role_id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary fw-semibold px-3 rounded-2" title="Configure Permissions">
                                            <i class="fa-solid fa-key me-1"></i> Permissions
                                        </a>

                                        <!-- Edit Role Modal Trigger -->
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-role rounded-2" 
                                                data-id="<?php echo $r['id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($r['name']); ?>" 
                                                data-desc="<?php echo htmlspecialchars($r['description'] ?? ''); ?>" 
                                                title="Modify Role Details">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>

                                        <!-- Delete Custom Role -->
                                        <?php if (!$isCore): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-role rounded-2" data-id="<?php echo $r['id']; ?>" title="Delete Custom Role">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-light text-muted border-0 rounded-2" disabled title="System Core Role Protected">
                                                <i class="fa-solid fa-lock text-muted"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-user-shield fs-1 text-muted mb-2"></i>
                                <div>No system roles found.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Add Custom Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light" style="border-top-left-radius:16px; border-top-right-radius:16px;">
                <h5 class="modal-title fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-user-shield text-primary"></i> Create New Custom Role Group
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="addRoleForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_role">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Role Group Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required placeholder="e.g., Exam Coordinator, Campus Accountant">
                        <div class="form-text small text-muted">A short unique title for this user group.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Role Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Briefly describe what responsibilities and privileges this user group oversees..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top bg-light px-4 py-3" style="border-bottom-left-radius:16px; border-bottom-right-radius:16px;">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addRoleForm" class="btn btn-sm btn-primary px-4 fw-bold" id="btnAddRole">
                    <i class="fa-solid fa-plus-circle me-1"></i> Create Role Group
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light" style="border-top-left-radius:16px; border-top-right-radius:16px;">
                <h5 class="modal-title fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-primary"></i> Modify Role Group Information
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="editRoleForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="edit_role">
                    <input type="hidden" name="id" id="edit_role_id">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Role Group Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="edit_role_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Role Description</label>
                        <textarea class="form-control" name="description" id="edit_role_desc" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top bg-light px-4 py-3" style="border-bottom-left-radius:16px; border-bottom-right-radius:16px;">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editRoleForm" class="btn btn-sm btn-primary px-4 fw-bold" id="btnEditRole">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="roleToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="roleToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
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
    // Client-side search and live filter
    const searchInput = document.getElementById("roleSearchInput");
    const typeFilter = document.getElementById("roleTypeFilter");
    const rows = document.querySelectorAll("#rolesTable .role-row");

    function filterRoles() {
        const q = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const type = typeFilter ? typeFilter.value : "all";

        rows.forEach(r => {
            const name = r.dataset.name || "";
            const code = r.dataset.code || "";
            const desc = r.dataset.desc || "";
            const rType = r.dataset.type || "all";

            const matchesText = !q || name.includes(q) || code.includes(q) || desc.includes(q);
            const matchesType = (type === "all") || (rType === type);

            r.style.display = (matchesText && matchesType) ? "" : "none";
        });
    }

    if (searchInput) searchInput.addEventListener("input", filterRoles);
    if (typeFilter) typeFilter.addEventListener("change", filterRoles);

    // Add Role AJAX Submission
    const addForm = document.getElementById("addRoleForm");
    if (addForm) {
        addForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnAddRole");
            btn.disabled = true; 
            btn.innerHTML = "<i class=\"fa-solid fa-spinner fa-spin me-1\"></i> Creating...";
            
            fetch("../../ajax/admin.php", { method: "POST", body: new FormData(addForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        setTimeout(() => location.reload(), 900);
                    } else { 
                        btn.disabled = false; 
                        btn.innerHTML = "<i class=\"fa-solid fa-plus-circle me-1\"></i> Create Role Group"; 
                    }
                })
                .catch(() => { 
                    showToast("Network connection error.", false); 
                    btn.disabled = false; 
                    btn.innerHTML = "<i class=\"fa-solid fa-plus-circle me-1\"></i> Create Role Group"; 
                });
        });
    }

    // Bind Edit Modal Data
    document.querySelectorAll(".btn-edit-role").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("edit_role_id").value = this.dataset.id;
            document.getElementById("edit_role_name").value = this.dataset.name;
            document.getElementById("edit_role_desc").value = this.dataset.desc;
            new bootstrap.Modal(document.getElementById("editRoleModal")).show();
        });
    });

    // Save Edit Role AJAX Submission
    const editForm = document.getElementById("editRoleForm");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnEditRole");
            btn.disabled = true; 
            btn.innerHTML = "<i class=\"fa-solid fa-spinner fa-spin me-1\"></i> Saving...";
            
            fetch("../../ajax/admin.php", { method: "POST", body: new FormData(editForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        setTimeout(() => location.reload(), 900);
                    } else { 
                        btn.disabled = false; 
                        btn.innerHTML = "<i class=\"fa-solid fa-floppy-disk me-1\"></i> Save Changes"; 
                    }
                })
                .catch(() => { 
                    showToast("Network connection error.", false); 
                    btn.disabled = false; 
                    btn.innerHTML = "<i class=\"fa-solid fa-floppy-disk me-1\"></i> Save Changes"; 
                });
        });
    }

    // Delete Custom Role AJAX Trigger
    document.querySelectorAll(".btn-delete-role").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            if(!confirm("Are you sure you want to permanently delete this custom role group? All associated permissions will be unlinked.")) return;
            
            const fd = new FormData();
            fd.append("action", "delete_role");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/admin.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        setTimeout(() => location.reload(), 900);
                    }
                })
                .catch(() => showToast("Network connection error.", false));
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
