<?php
/**
 * Indus Grammar School ERP - Audit Logs Viewer
 * Version 1.0.0
 */

$pageTitle = 'Audit Logs';
$breadcrumbActive = 'Administration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 30;
$offset = ($page - 1) * $limit;

// Filters
$filterAction = sanitize($_GET['action_filter'] ?? '');
$filterUser = sanitize($_GET['user_filter'] ?? '');

$logs = [];
try {
    $db = Database::getConnection();
    $sql = "SELECT a.*, u.username, r.name as role_name
            FROM audit_logs a 
            LEFT JOIN users u ON a.user_id = u.id 
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE 1=1";
    $params = [];
    
    if ($filterAction) {
        $sql .= " AND a.action LIKE :act";
        $params['act'] = '%' . $filterAction . '%';
    }
    if ($filterUser) {
        $sql .= " AND u.username LIKE :usr";
        $params['usr'] = '%' . $filterUser . '%';
    }
    
    $sql .= " ORDER BY a.created_at DESC LIMIT :lim OFFSET :off";
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue('off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (Exception $e) {}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-clipboard-list me-2 text-primary"></i>Audit Logs</h3>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Filter by Action</label>
                <input type="text" class="form-control" name="action_filter" value="<?php echo $filterAction; ?>" placeholder="e.g. Login, Marks Saved...">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Filter by Username</label>
                <input type="text" class="form-control" name="user_filter" value="<?php echo $filterUser; ?>" placeholder="e.g. saeed, admin...">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-2"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <a href="logs.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th width="50">#</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP Address</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">No audit logs found.</td></tr>
                <?php else: foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-muted small"><?php echo $log['id']; ?></td>
                        <td>
                            <div class="fw-semibold text-dark"><?php echo sanitize($log['username'] ?? 'System'); ?></div>
                            <div class="text-muted small" style="font-size:0.7rem;"><?php echo sanitize($log['role_name'] ?? 'N/A'); ?></div>
                        </td>
                        <td>
                            <?php
                                $badgeClass = 'bg-secondary';
                                $a = $log['action'];
                                if (str_contains($a, 'Success') || str_contains($a, 'Login') || str_contains($a, 'Paid') || str_contains($a, 'Saved') || str_contains($a, 'Created')) $badgeClass = 'badge-soft-success';
                                elseif (str_contains($a, 'Failed') || str_contains($a, 'Blocked') || str_contains($a, 'Delete') || str_contains($a, 'Error')) $badgeClass = 'badge-soft-danger';
                                elseif (str_contains($a, 'Update') || str_contains($a, 'Generated')) $badgeClass = 'badge-soft-primary';
                            ?>
                            <span class="badge <?php echo $badgeClass; ?> px-3 py-2 rounded-pill"><?php echo sanitize($a); ?></span>
                        </td>
                        <td class="text-muted small"><?php echo sanitize(mb_substr($log['description'], 0, 80)) . (mb_strlen($log['description']) > 80 ? '…' : ''); ?></td>
                        <td><code class="text-muted"><?php echo sanitize($log['ip_address'] ?? '-'); ?></code></td>
                        <td>
                            <div class="small"><?php echo date('d M Y', strtotime($log['created_at'])); ?></div>
                            <div class="text-muted small" style="font-size:0.7rem;"><?php echo date('h:i:s A', strtotime($log['created_at'])); ?></div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if (count($logs) == $limit || $page > 1): ?>
    <div class="p-3 border-top bg-light d-flex justify-content-between align-items-center">
        <span class="text-muted small">Page <?php echo $page; ?></span>
        <div class="btn-group">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&action_filter=<?php echo urlencode($filterAction); ?>&user_filter=<?php echo urlencode($filterUser); ?>" class="btn btn-sm btn-outline-secondary">Previous</a>
            <?php endif; ?>
            <?php if (count($logs) == $limit): ?>
                <a href="?page=<?php echo $page + 1; ?>&action_filter=<?php echo urlencode($filterAction); ?>&user_filter=<?php echo urlencode($filterUser); ?>" class="btn btn-sm btn-outline-secondary">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
