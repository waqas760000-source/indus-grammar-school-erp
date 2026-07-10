<?php
/**
 * Indus Grammar School ERP - Role Management
 * Version 1.0.0
 */

$pageTitle = 'Role Management';
$breadcrumbActive = 'Administration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

// Fetch roles
$roles = [];
try {
    $db = Database::getConnection();
    $roles = $db->query("SELECT r.*, COUNT(u.id) as user_count FROM roles r LEFT JOIN users u ON r.id = u.role_id GROUP BY r.id ORDER BY r.id ASC")->fetchAll();
} catch (Exception $e) {}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-user-shield me-2 text-primary"></i>Role Management</h3>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-0 text-secondary">System Roles</h5>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Role Name</th>
                    <th>Role Code</th>
                    <th>Description</th>
                    <th class="text-center">Assigned Users</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $r): ?>
                    <tr>
                        <td class="fw-semibold">#<?php echo $r['id']; ?></td>
                        <td class="fw-semibold text-dark"><?php echo sanitize($r['name']); ?></td>
                        <td><code class="text-muted"><?php echo sanitize($r['code']); ?></code></td>
                        <td><?php echo sanitize($r['description']); ?></td>
                        <td class="text-center fw-bold"><?php echo $r['user_count']; ?></td>
                        <td class="text-end">
                            <a href="permissions.php?role_id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-key me-1"></i>Permissions
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
