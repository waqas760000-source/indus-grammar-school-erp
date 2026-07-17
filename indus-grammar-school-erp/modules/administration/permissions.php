<?php
/**
 * Indus Grammar School ERP - Permissions Management Grid Matrix
 * Version 4.0.0
 */

$pageTitle = 'Role Permissions';
$breadcrumbActive = 'Administration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

$roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;
if ($roleId <= 0) {
    echo "<div class='alert alert-danger p-4 shadow-sm' style='border-radius:12px;'><i class='fa-solid fa-circle-exclamation me-2'></i>Invalid Role Selection.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db = Database::getConnection();

// Fetch selected role info
$role = [];
try {
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = :id");
    $stmt->execute(['id' => $roleId]);
    $role = $stmt->fetch();
} catch (Exception $e) {}

if (!$role) {
    echo "<div class='alert alert-danger p-4 shadow-sm' style='border-radius:12px;'><i class='fa-solid fa-circle-exclamation me-2'></i>Role not found.</div>";
    include_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch all permissions in the system
$allPermissions = [];
try {
    $allPermissions = $db->query("SELECT * FROM permissions ORDER BY module ASC, code ASC")->fetchAll();
} catch (Exception $e) {}

// Organize permissions into a Matrix structured by [Module][Action] => Permission ID
$modulesList = [
    'Dashboard' => 'dashboard',
    'Student Registration' => 'student',
    'Student Attendance' => 'attendance',
    'Fee Collection' => 'fee',
    'Examination' => 'exam',
    'Payroll' => 'payroll',
    'Accounts' => 'accounts',
    'Reports' => 'report',
    'Communication' => 'communication',
    'Settings' => 'settings'
];

$actionsList = [
    'View'   => 'view',
    'Add'    => 'add',
    'Edit'   => 'edit',
    'Delete' => 'delete',
    'Print'  => 'print',
    'Export' => 'export'
];

$permissionsMatrix = [];
foreach ($allPermissions as $p) {
    // Determine the action key based on the suffix of the permission code
    $actionKey = 'view';
    foreach ($actionsList as $aName => $aCode) {
        // Match both 'create' and 'add' to 'Add'
        if (str_ends_with($p['code'], '_' . $aCode) || ($aCode === 'add' && str_ends_with($p['code'], '_create')) || ($aCode === 'edit' && str_contains($p['code'], 'manage'))) {
            $actionKey = $aCode;
            break;
        }
    }
    
    // Standardize legacy permissions that don't match the suffix format
    if ($p['code'] === 'system_settings') {
        $permissionsMatrix['Settings']['edit'] = $p['id'];
    } elseif ($p['code'] === 'attendance_mark') {
        $permissionsMatrix['Student Attendance']['add'] = $p['id'];
    } elseif ($p['code'] === 'fee_collect') {
        $permissionsMatrix['Fee Collection']['add'] = $p['id'];
    } elseif ($p['code'] === 'fee_invoice') {
        $permissionsMatrix['Fee Collection']['edit'] = $p['id'];
    } elseif ($p['code'] === 'exam_marks_entry') {
        $permissionsMatrix['Examination']['add'] = $p['id'];
    } elseif ($p['code'] === 'staff_manage') {
        $permissionsMatrix['Payroll']['edit'] = $p['id'];
    } elseif ($p['code'] === 'communication_send') {
        $permissionsMatrix['Communication']['add'] = $p['id'];
    } elseif ($p['code'] === 'cash_transaction') {
        $permissionsMatrix['Accounts']['add'] = $p['id'];
    }
    
    $permissionsMatrix[$p['module']][$actionKey] = $p['id'];
}

// Fetch currently active permission IDs for this role
$activePermissionIds = [];
try {
    $stmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = :rid");
    $stmt->execute(['rid' => $roleId]);
    $activePermissionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Handle Save Action
$message = '';
$messageOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    if (!validateCsrf($_POST['csrf_token'] ?? '')) {
        $message = "CSRF security token expired.";
    } else {
        $submittedPerms = $_POST['perms'] ?? [];
        try {
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
            $message = "Permissions matrix successfully updated for '{$role['name']}'.";
            $messageOk = true;
            auditLog('Permissions Updated', "Updated permissions matrix configuration for Role: {$role['name']}");
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            $message = "Failed to save configurations: " . $e->getMessage();
        }
    }
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-key me-2 text-primary"></i>Role Permissions Matrix</h3>
        <p class="text-muted small mb-0">Configure operational controls for <strong><?php echo sanitize($role['name']); ?></strong></p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="roles.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>Back to Roles</a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageOk ? 'success' : 'danger'; ?> alert-dismissible fade show shadow-sm border-0" role="alert" style="border-radius:10px;">
        <i class="fa-solid <?php echo $messageOk ? 'fa-circle-check' : 'fa-circle-exclamation'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
    
    <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Module Name</th>
                        <?php foreach (array_keys($actionsList) as $actionName): ?>
                            <th class="text-center" width="120"><?php echo $actionName; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modulesList as $moduleName => $moduleCode): ?>
                        <tr>
                            <td class="fw-bold text-secondary ps-4">
                                <i class="fa-solid fa-cubes me-2 text-primary"></i><?php echo $moduleName; ?>
                            </td>
                            <?php foreach ($actionsList as $actionName => $actionCode): 
                                $permId = $permissionsMatrix[$moduleName][$actionCode] ?? null;
                                $checked = ($permId && in_array($permId, $activePermissionIds)) ? 'checked' : '';
                            ?>
                                <td class="text-center">
                                    <?php if ($permId): ?>
                                        <input class="form-check-input" type="checkbox" name="perms[]" value="<?php echo $permId; ?>" <?php echo $checked; ?> <?php echo ($role['code'] === ROLE_SUPER_ADMIN) ? 'disabled checked' : ''; ?>>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($role['code'] !== ROLE_SUPER_ADMIN): ?>
        <div class="text-end mb-4">
            <button type="submit" name="save_permissions" class="btn btn-primary px-5 py-2">
                <i class="fa-solid fa-save me-2"></i>Save Permissions Matrix
            </button>
        </div>
    <?php else: ?>
        <div class="alert alert-warning border-0 shadow-sm small py-2 mt-3" style="border-radius:8px;">
            <i class="fa-solid fa-circle-exclamation me-2"></i>Super Admin has unrestricted permissions across all system modules by default. Configuration checkboxes are disabled.
        </div>
    <?php endif; ?>
</form>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
