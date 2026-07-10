<?php
/**
 * Indus Grammar School ERP - Permissions Management Grid
 * Version 1.0.0
 */

$pageTitle = 'Role Permissions';
$breadcrumbActive = 'Administration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

$roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;
if ($roleId <= 0) {
    echo "<div class='alert alert-danger'>Invalid Role Selection.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch selected role info
$role = [];
try {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = :id");
    $stmt->execute(['id' => $roleId]);
    $role = $stmt->fetch();
} catch (Exception $e) {}

if (!$role) {
    echo "<div class='alert alert-danger'>Role not found.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch all permissions grouped by module
$permissionsGrouped = [];
try {
    $db = Database::getConnection();
    $all = $db->query("SELECT * FROM permissions ORDER BY module, code")->fetchAll();
    foreach ($all as $p) {
        $permissionsGrouped[$p['module']][] = $p;
    }
} catch (Exception $e) {}

// Fetch active permission IDs for this role
$activePermissionIds = [];
try {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = :rid");
    $stmt->execute(['rid' => $roleId]);
    $activePermissionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Handle Update Request
$message = '';
$messageOk = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    if (!validateCsrf($_POST['csrf_token'] ?? '')) {
        $message = "CSRF Token expired.";
    } else {
        $submittedPerms = $_POST['perms'] ?? []; // Array of permission IDs
        try {
            $db = Database::getConnection();
            $db->beginTransaction();
            
            // Clear current role mappings
            $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = :rid");
            $stmt->execute(['rid' => $roleId]);
            
            // Insert updated mappings
            if (!empty($submittedPerms)) {
                $insert = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)");
                foreach ($submittedPerms as $pid) {
                    $insert->execute(['rid' => $roleId, 'pid' => (int)$pid]);
                }
            }
            
            $db->commit();
            $activePermissionIds = array_map('intval', $submittedPerms);
            $message = "Permissions updated successfully.";
            $messageOk = true;
            auditLog('Permissions Updated', "Updated permissions for role: {$role['name']}");
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            $message = "Failed to update permissions: " . $e->getMessage();
        }
    }
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-key me-2 text-primary"></i>Role Permissions</h3>
        <p class="text-muted small mb-0">Configure access permissions for <strong><?php echo sanitize($role['name']); ?></strong></p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="roles.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>Back to Roles</a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageOk ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <i class="fa-solid <?php echo $messageOk ? 'fa-circle-check' : 'fa-circle-exclamation'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
    
    <div class="row g-4">
        <?php foreach ($permissionsGrouped as $module => $perms): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
                    <div class="card-header border-0 bg-light py-3 px-4 fw-bold text-secondary">
                        <i class="fa-solid fa-cubes me-2 text-primary"></i><?php echo sanitize($module); ?>
                    </div>
                    <div class="card-body px-4 py-3">
                        <?php foreach ($perms as $p): 
                            $checked = in_array($p['id'], $activePermissionIds) ? 'checked' : '';
                        ?>
                            <div class="form-check py-2 border-bottom border-light">
                                <input class="form-check-input" type="checkbox" name="perms[]" value="<?php echo $p['id']; ?>" id="perm_<?php echo $p['id']; ?>" <?php echo $checked; ?> <?php echo ($role['code'] === ROLE_SUPER_ADMIN) ? 'disabled checked' : ''; ?>>
                                <label class="form-check-label fw-semibold text-dark small" for="perm_<?php echo $p['id']; ?>">
                                    <?php echo sanitize($p['description']); ?>
                                    <br><code class="text-muted" style="font-size: 0.7rem;"><?php echo sanitize($p['code']); ?></code>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($role['code'] !== ROLE_SUPER_ADMIN): ?>
        <div class="mt-4 text-end">
            <button type="submit" name="save_permissions" class="btn btn-primary px-5 py-2">
                <i class="fa-solid fa-save me-2"></i>Save Permissions Mapping
            </button>
        </div>
    <?php else: ?>
        <div class="alert alert-warning border-0 shadow-sm small py-2 mt-4">
            <i class="fa-solid fa-circle-exclamation me-2"></i>Super Admin has all system permissions by default. Checkboxes are disabled.
        </div>
    <?php endif; ?>
</form>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
