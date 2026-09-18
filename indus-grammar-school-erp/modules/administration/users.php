<?php
/**
 * Indus Grammar School ERP - Premium User Management System
 * Version 5.0.0 — Commercial UI/UX Redesign & Security Optimization
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

// Fetch KPI statistics
$totalUsersCount = 0;
$activeUsersCount = 0;
$inactiveUsersCount = 0;
$totalRolesCount = count($roles);

try {
    $totalUsersCount = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $activeUsersCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
    $inactiveUsersCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active = 0")->fetchColumn();
} catch (Exception $e) {}

// Helper function to extract initials from full name or username
function getUserInitials($name) {
    $clean = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
    if (empty($clean)) return 'U';
    $words = explode(' ', $clean);
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($clean, 0, 2));
}
?>

<!-- Custom Premium User Management Styling System -->
<style>
:root {
    --primary-navy: #0F172A;
    --primary-blue: #1D4ED8;
    --secondary-blue: #2563EB;
    --hover-blue: #1E40AF;
    --light-blue: #EFF6FF;
    --page-background: #F1F5F9;
    --card-white: #FFFFFF;
    --border-color: #E2E8F0;
    --main-text: #1E293B;
    --muted-text: #64748B;
    --success: #16A34A;
    --success-bg: #F0FDF4;
    --warning: #D97706;
    --warning-bg: #FFFBEB;
    --danger: #DC2626;
    --danger-bg: #FEF2F2;
    --purple: #7C3AED;
    --purple-bg: #F5F3FF;
}

/* Page Canvas Wrapper */
.user-mgmt-wrapper {
    background-color: var(--page-background);
    border-radius: 18px;
    padding: 1.5rem;
    min-height: calc(100vh - 100px);
}

/* Header Banner (Matches Student Registration Hero Style) */
.adv-hero-banner {
    background: linear-gradient(135deg, var(--primary-navy) 0%, #1e3a8a 55%, var(--secondary-blue) 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.18);
    position: relative;
    overflow: hidden;
}

.adv-hero-banner::after {
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

/* Premium KPI Summary Cards */
.kpi-card {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.25s ease;
}

.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px -4px rgba(15, 23, 42, 0.08);
}

.kpi-card-accent-blue   { border-top: 4px solid var(--primary-blue); }
.kpi-card-accent-green  { border-top: 4px solid var(--success); }
.kpi-card-accent-orange { border-top: 4px solid var(--warning); }
.kpi-card-accent-purple { border-top: 4px solid var(--purple); }

.kpi-icon-container {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.kpi-icon-blue   { background-color: var(--light-blue); color: var(--primary-blue); }
.kpi-icon-green  { background-color: var(--success-bg); color: var(--success); }
.kpi-icon-orange { background-color: var(--warning-bg); color: var(--warning); }
.kpi-icon-purple { background-color: var(--purple-bg); color: var(--purple); }

.kpi-number {
    font-size: 1.85rem;
    font-weight: 800;
    color: var(--primary-navy);
    line-height: 1.2;
    margin-top: 0.3rem;
}

.kpi-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--muted-text);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Filter Card */
.filter-card {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
}

/* Data Table Styling */
.table-card-container {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
}

.user-table th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.95rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
}

.user-table td {
    padding: 0.95rem 1.25rem;
    vertical-align: middle;
    font-size: 0.88rem;
    color: var(--main-text);
    border-bottom: 1px solid #f1f5f9;
}

.user-table tr:last-child td {
    border-bottom: none;
}

/* User Initials Avatar Fallback */
.avatar-initials-box {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--light-blue);
    color: var(--primary-blue);
    font-weight: 700;
    font-size: 0.92rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5 solid #bfdbfe;
    flex-shrink: 0;
}

/* Role Badges */
.badge-role-super_admin { background-color: var(--purple-bg); color: var(--purple); border: 1px solid #e9d5ff; }
.badge-role-school_admin { background-color: var(--light-blue); color: var(--primary-blue); border: 1px solid #bfdbfe; }
.badge-role-accountant  { background-color: var(--success-bg); color: var(--success); border: 1px solid #bbf7d0; }
.badge-role-receptionist { background-color: var(--warning-bg); color: var(--warning); border: 1px solid #fef08a; }
.badge-role-default      { background-color: #f1f5f9; color: var(--muted-text); border: 1px solid var(--border-color); }

/* Status Badges */
.badge-status-active   { background-color: var(--success-bg); color: var(--success); font-weight: 600; padding: 0.35em 0.8em; border-radius: 20px; }
.badge-status-disabled { background-color: var(--danger-bg); color: var(--danger); font-weight: 600; padding: 0.35em 0.8em; border-radius: 20px; }
</style>

<div class="user-mgmt-wrapper">

    <!-- 1. Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item"><a href="#" class="text-decoration-none text-muted">Administration</a></li>
            <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">User Management</li>
        </ol>
    </nav>

    <!-- 2. Hero Header Banner (Matches Student Registration Header) -->
    <div class="adv-hero-banner mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid fa-users-gear"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h2 class="fw-bold mb-0 text-white fs-3">User Management</h2>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">Security Console</span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">Manage system users, roles, access permissions, account status, and administrative access from one secure dashboard.</p>
                </div>
            </div>
            <div>
                <button class="btn btn-light text-primary rounded-3 px-4 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fa-solid fa-user-plus me-2"></i>Add New User
                </button>
            </div>
        </div>
    </div>

    <!-- 3. KPI Summary Cards (Real Database Data) -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Total Users -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="kpi-card kpi-card-accent-blue">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="kpi-title">Total Users</span>
                    <div class="kpi-icon-container kpi-icon-blue"><i class="fa-solid fa-users"></i></div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format($totalUsersCount); ?></div>
                    <span class="text-muted small" style="font-size: 0.78rem;">Registered system operators</span>
                </div>
            </div>
        </div>

        <!-- Card 2: Active Accounts -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="kpi-card kpi-card-accent-green">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="kpi-title">Active Accounts</span>
                    <div class="kpi-icon-container kpi-icon-green"><i class="fa-solid fa-user-check"></i></div>
                </div>
                <div>
                    <div class="kpi-number text-success"><?php echo number_format($activeUsersCount); ?></div>
                    <span class="text-muted small" style="font-size: 0.78rem;">Enabled active logins</span>
                </div>
            </div>
        </div>

        <!-- Card 3: Inactive Accounts -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="kpi-card kpi-card-accent-orange">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="kpi-title">Inactive Accounts</span>
                    <div class="kpi-icon-container kpi-icon-orange"><i class="fa-solid fa-user-slash"></i></div>
                </div>
                <div>
                    <div class="kpi-number text-warning"><?php echo number_format($inactiveUsersCount); ?></div>
                    <span class="text-muted small" style="font-size: 0.78rem;">Disabled/archived accounts</span>
                </div>
            </div>
        </div>

        <!-- Card 4: System Roles -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="kpi-card kpi-card-accent-purple">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="kpi-title">System Roles</span>
                    <div class="kpi-icon-container kpi-icon-purple"><i class="fa-solid fa-user-shield"></i></div>
                </div>
                <div>
                    <div class="kpi-number text-purple"><?php echo number_format($totalRolesCount); ?></div>
                    <span class="text-muted small" style="font-size: 0.78rem;">Defined access role levels</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Search & Filter Toolbar -->
    <div class="filter-card p-4 mb-4">
        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-filter me-2 text-primary"></i>Filter System Users</h6>
        <form method="GET" class="row g-3">
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-semibold text-muted">Full Name</label>
                <input type="text" class="form-control form-control-sm rounded-3" name="search_name" value="<?php echo htmlspecialchars($searchName); ?>" placeholder="Search by name...">
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-semibold text-muted">Username</label>
                <input type="text" class="form-control form-control-sm rounded-3" name="search_username" value="<?php echo htmlspecialchars($searchUser); ?>" placeholder="Search username...">
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-semibold text-muted">System Role</label>
                <select class="form-select form-select-sm rounded-3" name="search_role">
                    <option value="">All Roles</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r['id']; ?>" <?php echo ($searchRole == $r['id']) ? 'selected' : ''; ?>><?php echo sanitize($r['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-semibold text-muted">Account Status</label>
                <select class="form-select form-select-sm rounded-3" name="search_status">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo ($searchStatus === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo ($searchStatus === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-12 text-end">
                <a href="users.php" class="btn btn-sm btn-outline-secondary rounded-3 px-3 me-2">Reset Filters</a>
                <button type="submit" class="btn btn-sm btn-primary rounded-3 px-4"><i class="fa-solid fa-magnifying-glass me-2"></i>Search Users</button>
            </div>
        </form>
    </div>

    <!-- 5. User Management Table Card -->
    <div class="table-card-container mb-4">
        <div class="table-responsive">
            <table class="table user-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="70">Avatar</th>
                        <th>User Profile</th>
                        <th>Email Address</th>
                        <th>Mobile Number</th>
                        <th>Role Assigned</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th class="text-end">Action Controls</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-users-slash fs-2 text-secondary d-block mb-2"></i>
                                No system user accounts match your criteria.
                            </td>
                        </tr>
                    <?php else: foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <?php if (!empty($u['profile_photo']) && file_exists(DIR_ROOT . '/' . $u['profile_photo'])): ?>
                                    <img src="<?php echo APP_URL . '/' . $u['profile_photo']; ?>" class="rounded-circle border" width="42" height="42" alt="Avatar" style="object-fit:cover;">
                                <?php else: ?>
                                    <div class="avatar-initials-box">
                                        <?php echo getUserInitials($u['full_name'] ?: $u['username']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark mb-0"><?php echo sanitize($u['full_name'] ?: 'N/A'); ?></div>
                                <span class="text-muted small">@<?php echo sanitize($u['username']); ?></span>
                            </td>
                            <td class="small fw-semibold text-dark"><?php echo sanitize($u['email']); ?></td>
                            <td class="small text-muted"><?php echo sanitize($u['mobile_no'] ?: '—'); ?></td>
                            <td>
                                <?php
                                $roleCode = $u['role_code'] ?? '';
                                $badgeClass = 'badge-role-' . ($roleCode ?: 'default');
                                ?>
                                <span class="badge <?php echo $badgeClass; ?> px-3 py-2 rounded-pill font-monospace" style="font-size:0.75rem;">
                                    <i class="fa-solid fa-shield-halved me-1"></i><?php echo sanitize($u['role_name']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-status-<?php echo $u['is_active'] ? 'active' : 'disabled'; ?>">
                                    <i class="fa-solid <?php echo $u['is_active'] ? 'fa-circle-check' : 'fa-circle-xmark'; ?> me-1"></i>
                                    <?php echo $u['is_active'] ? 'Active' : 'Disabled'; ?>
                                </span>
                            </td>
                            <td class="text-muted small"><?php echo $u['last_login'] ? date('d M Y, h:i A', strtotime($u['last_login'])) : 'Never'; ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn btn-sm btn-outline-primary btn-edit-user rounded-2" 
                                            data-id="<?php echo $u['id']; ?>"
                                            data-fullname="<?php echo htmlspecialchars($u['full_name'] ?? ''); ?>"
                                            data-email="<?php echo htmlspecialchars($u['email']); ?>"
                                            data-mobile="<?php echo htmlspecialchars($u['mobile_no'] ?? ''); ?>"
                                            data-role="<?php echo $u['role_id']; ?>"
                                            data-active="<?php echo $u['is_active']; ?>"
                                            title="Edit Profile">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning btn-reset-pass rounded-2" data-id="<?php echo $u['id']; ?>" title="Reset Password">
                                        <i class="fa-solid fa-key"></i>
                                    </button>
                                    <?php if ($u['id'] !== 1): ?>
                                        <button class="btn btn-sm btn-outline-<?php echo $u['is_active'] ? 'danger' : 'success'; ?> btn-toggle-status rounded-2" data-id="<?php echo $u['id']; ?>" data-active="<?php echo $u['is_active']; ?>" title="<?php echo $u['is_active'] ? 'Disable User Account' : 'Enable User Account'; ?>">
                                            <i class="fa-solid <?php echo $u['is_active'] ? 'fa-user-xmark' : 'fa-user-check'; ?>"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-bottom pt-4 px-4 pb-3" style="background:#f8fafc; border-top-left-radius:16px; border-top-right-radius:16px;">
                <h5 class="modal-title fw-bold text-dark mb-0"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Register New System Operator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="addUserForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="create_user">
                    
                    <h6 class="fw-bold text-primary small text-uppercase tracking-wider mb-3"><i class="fa-solid fa-id-badge me-1"></i>Section A: Personal & Account Credentials</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm rounded-3" name="username" required placeholder="e.g. jdoe">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Full Name</label>
                            <input type="text" class="form-control form-control-sm rounded-3" name="full_name" placeholder="e.g. John Doe">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-sm rounded-3" name="email" required placeholder="e.g. user@indusgrammar.edu.pk">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Mobile Number</label>
                            <input type="text" class="form-control form-control-sm rounded-3" name="mobile_no" placeholder="e.g. +92 300 1234567">
                        </div>
                    </div>

                    <h6 class="fw-bold text-primary small text-uppercase tracking-wider mb-3 pt-2 border-top"><i class="fa-solid fa-lock me-1"></i>Section B: Security & Role Settings</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control form-control-sm rounded-3" name="password" minlength="8" required placeholder="At least 8 characters">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Assign Role <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm rounded-3" name="role_id" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"><?php echo sanitize($r['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Account Status</label>
                            <select class="form-select form-select-sm rounded-3" name="is_active">
                                <option value="1">Active / Enabled</option>
                                <option value="0">Inactive / Disabled</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Profile Avatar Photo (Optional)</label>
                            <input type="file" class="form-control form-control-sm rounded-3" name="profile_photo" accept="image/*">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top pt-3 px-4 pb-4" style="background:#f8fafc; border-bottom-left-radius:16px; border-bottom-right-radius:16px;">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addUserForm" class="btn btn-sm btn-primary rounded-3 px-4" id="btnAddUser"><i class="fa-solid fa-user-plus me-1"></i>Create User Account</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-bottom pt-4 px-4 pb-3" style="background:#f8fafc; border-top-left-radius:16px; border-top-right-radius:16px;">
                <h5 class="modal-title fw-bold text-dark mb-0"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Modify User Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="editUserForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Full Name</label>
                            <input type="text" class="form-control form-control-sm rounded-3" name="full_name" id="edit_fullname">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-sm rounded-3" name="email" id="edit_email" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Mobile Number</label>
                            <input type="text" class="form-control form-control-sm rounded-3" name="mobile_no" id="edit_mobile">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Assign Role <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm rounded-3" name="role_id" id="edit_role_id" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"><?php echo sanitize($r['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Account Status</label>
                            <select class="form-select form-select-sm rounded-3" name="is_active" id="edit_active">
                                <option value="1">Active / Enabled</option>
                                <option value="0">Inactive / Disabled</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Update Avatar Photo</label>
                            <input type="file" class="form-control form-control-sm rounded-3" name="profile_photo" accept="image/*">
                        </div>
                    </div>
                    <div class="mb-3 pt-2 border-top">
                        <label class="form-label small fw-semibold text-dark">Change Password (Optional)</label>
                        <input type="password" class="form-control form-control-sm rounded-3" name="password" minlength="8" placeholder="Leave blank to keep current password">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top pt-3 px-4 pb-4" style="background:#f8fafc; border-bottom-left-radius:16px; border-bottom-right-radius:16px;">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editUserForm" class="btn btn-sm btn-primary rounded-3 px-4" id="btnEditUser"><i class="fa-solid fa-floppy-disk me-1"></i>Save Profile Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-bottom pt-4 px-4 pb-3" style="background:#fffbeb; border-top-left-radius:16px; border-top-right-radius:16px;">
                <h5 class="modal-title fw-bold text-dark mb-0"><i class="fa-solid fa-key me-2 text-warning"></i>Reset Credentials</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="resetPassForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="id" id="reset_user_id">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control form-control-sm rounded-3" name="password" id="reset_password_field" minlength="8" required placeholder="Min 8 chars">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control form-control-sm rounded-3" id="reset_confirm_password_field" minlength="8" required placeholder="Repeat password">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top pt-3 px-4 pb-4" style="background:#f8fafc; border-bottom-left-radius:16px; border-bottom-right-radius:16px;">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="resetPassForm" class="btn btn-sm btn-warning rounded-3 px-4" id="btnResetPass"><i class="fa-solid fa-key me-1"></i>Reset Password</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Feedback -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="usrToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert" style="border-radius:12px;">
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
            btn.disabled = true; btn.innerHTML = "<i class=\"fa-solid fa-spinner fa-spin me-1\"></i>Creating...";
            
            fetch("../../ajax/admin.php", { method: "POST", body: new FormData(addForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-user-plus me-1\"></i>Create User Account"; }
                })
                .catch(() => { showToast("Network communication error.", false); btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-user-plus me-1\"></i>Create User Account"; });
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
            btn.disabled = true; btn.innerHTML = "<i class=\"fa-solid fa-spinner fa-spin me-1\"></i>Saving...";
            
            fetch("../../ajax/admin.php", { method: "POST", body: new FormData(editForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-floppy-disk me-1\"></i>Save Profile Changes"; }
                })
                .catch(() => { showToast("Network communication error.", false); btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-floppy-disk me-1\"></i>Save Profile Changes"; });
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
            btn.disabled = true; btn.innerHTML = "<i class=\"fa-solid fa-spinner fa-spin me-1\"></i>Resetting...";
            
            fetch("../../ajax/admin.php", { method: "POST", body: new FormData(resetForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-key me-1\"></i>Reset Password"; }
                })
                .catch(() => { showToast("Network communication error.", false); btn.disabled = false; btn.innerHTML = "<i class=\"fa-solid fa-key me-1\"></i>Reset Password"; });
        });
    }

    // Toggle status button action
    document.querySelectorAll(".btn-toggle-status").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            const isActive = this.dataset.active;
            const actionVerb = isActive == "1" ? "disable" : "enable";
            if (!confirm("Are you sure you want to " + actionVerb + " this user account?")) return;
            
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
>
