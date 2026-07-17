<?php
/**
 * Indus Grammar School ERP - Audit Logs Panel (Normalized)
 * Version 4.0.0
 */

$pageTitle = 'Audit Logs';
$breadcrumbActive = 'Administration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 30;
$offset = ($page - 1) * $limit;

// Retrieve filters
$filterAction = sanitize($_GET['action_filter'] ?? '');
$filterUser   = sanitize($_GET['user_filter'] ?? '');
$fromDate     = sanitize($_GET['from_date'] ?? '');
$toDate       = sanitize($_GET['to_date'] ?? '');

$logs = [];
$totalLogs = 0;

try {
    $db = Database::getConnection();
    
    // Build query conditions
    $where = " WHERE 1=1";
    $params = [];
    
    if ($filterAction !== '') {
        $where .= " AND a.action LIKE :act";
        $params['act'] = '%' . $filterAction . '%';
    }
    if ($filterUser !== '') {
        $where .= " AND u.username LIKE :usr";
        $params['usr'] = '%' . $filterUser . '%';
    }
    if ($fromDate !== '') {
        $where .= " AND DATE(a.created_at) >= :from";
        $params['from'] = $fromDate;
    }
    if ($toDate !== '') {
        $where .= " AND DATE(a.created_at) <= :to";
        $params['to'] = $toDate;
    }

    // Get total logs for pagination
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id $where");
    $cntStmt->execute($params);
    $totalLogs = (int)$cntStmt->fetchColumn();

    // Query logs
    $sql = "SELECT a.*, u.username, r.name as role_name
            FROM audit_logs a 
            LEFT JOIN users u ON a.user_id = u.id 
            LEFT JOIN roles r ON u.role_id = r.id
            $where
            ORDER BY a.created_at DESC 
            LIMIT :lim OFFSET :off";
            
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue('off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error loading audit logs: " . $e->getMessage());
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-clipboard-list me-2 text-primary"></i>Audit Activity Logs</h3>
        <p class="text-muted small mb-0">Monitor all actions, logins, database updates, and record alterations with IP details.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="../../ajax/admin.php?action=export_audit_logs&action_filter=<?php echo urlencode($filterAction); ?>&user_filter=<?php echo urlencode($filterUser); ?>&from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>" class="btn btn-outline-primary px-4">
            <i class="fa-solid fa-file-csv me-2"></i>Export CSV Ledger
        </a>
    </div>
</div>

<!-- Filters Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Logs Activity</h6>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Filter by Action</label>
                <input type="text" class="form-control form-control-sm" name="action_filter" value="<?php echo htmlspecialchars($filterAction); ?>" placeholder="e.g. Saved, Deleted, Created">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Filter by Operator</label>
                <input type="text" class="form-control form-control-sm" name="user_filter" value="<?php echo htmlspecialchars($filterUser); ?>" placeholder="e.g. saeed, admin">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">From Date</label>
                <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">To Date</label>
                <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i>Filter</button>
                <a href="logs.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Logs History Table -->
<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th width="80">ID</th>
                    <th>User Operator</th>
                    <th>Action</th>
                    <th>Log Description Details</th>
                    <th>IP Address</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">No system activity logs found.</td></tr>
                <?php else: foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-muted small">#<?php echo $log['id']; ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo sanitize($log['username'] ?: 'System'); ?></div>
                            <div class="text-muted small" style="font-size:0.7rem;"><?php echo sanitize($log['role_name'] ?: 'N/A'); ?></div>
                        </td>
                        <td>
                            <?php
                            $badgeClass = 'bg-secondary';
                            $a = $log['action'];
                            if (str_contains($a, 'Success') || str_contains($a, 'Login') || str_contains($a, 'Paid') || str_contains($a, 'Saved') || str_contains($a, 'Created') || str_contains($a, 'Added')) $badgeClass = 'badge-soft-success';
                            elseif (str_contains($a, 'Failed') || str_contains($a, 'Blocked') || str_contains($a, 'Delete') || str_contains($a, 'Archived') || str_contains($a, 'Error')) $badgeClass = 'badge-soft-danger';
                            elseif (str_contains($a, 'Update') || str_contains($a, 'Generated') || str_contains($a, 'Toggled')) $badgeClass = 'badge-soft-primary';
                            ?>
                            <span class="badge <?php echo $badgeClass; ?> px-3 py-2 rounded-pill"><?php echo sanitize($a); ?></span>
                        </td>
                        <td class="text-muted small" style="max-width:350px; white-space:normal;"><?php echo sanitize($log['description']); ?></td>
                        <td><code class="text-muted"><?php echo sanitize($log['ip_address'] ?? '-'); ?></code></td>
                        <td>
                            <div class="small fw-semibold"><?php echo date('d M Y', strtotime($log['created_at'])); ?></div>
                            <div class="text-muted small" style="font-size:0.7rem;"><?php echo date('h:i:s A', strtotime($log['created_at'])); ?></div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalLogs > $limit): ?>
    <div class="p-3 border-top bg-light d-flex justify-content-between align-items-center">
        <span class="text-muted small">Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalLogs); ?> of <?php echo $totalLogs; ?> logs</span>
        <div class="btn-group">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&action_filter=<?php echo urlencode($filterAction); ?>&user_filter=<?php echo urlencode($filterUser); ?>&from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>" class="btn btn-sm btn-outline-secondary">Previous</a>
            <?php endif; ?>
            <?php if ($offset + $limit < $totalLogs): ?>
                <a href="?page=<?php echo $page + 1; ?>&action_filter=<?php echo urlencode($filterAction); ?>&user_filter=<?php echo urlencode($filterUser); ?>&from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>" class="btn btn-sm btn-outline-secondary">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
