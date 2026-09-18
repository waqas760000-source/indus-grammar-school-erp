<?php
/**
 * Indus Grammar School ERP - Audit Logs Panel
 * Version 7.0.0 — Commercial Redesign (System Activity, Auth Logins & Record Alterations)
 */

$pageTitle = 'Audit Logs';
$breadcrumbActive = 'Audit Logs';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

// Pagination setup
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
$totalAuthEvents = 0;
$totalDataMods = 0;
$uniqueIpCount = 0;

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

    // Query logs data
    $sql = "SELECT a.*, u.username, u.full_name, r.name as role_name
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

    // Stats calculations
    try {
        $totalAuthEvents = (int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%login%' OR action LIKE '%auth%'")->fetchColumn();
        $totalDataMods   = (int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%saved%' OR action LIKE '%update%' OR action LIKE '%create%' OR action LIKE '%delete%'")->fetchColumn();
        $uniqueIpCount   = (int)$db->query("SELECT COUNT(DISTINCT ip_address) FROM audit_logs")->fetchColumn();
    } catch (Exception $e) {}

} catch (Exception $e) {
    error_log("Error loading audit logs: " . $e->getMessage());
}
?>

<!-- Custom Styling for Audit Activity Logs Module -->
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
.logs-page-container {
    background-color: var(--page-background);
    border-radius: 18px;
    padding: 1.5rem;
    min-height: calc(100vh - 100px);
}

/* Hero Header Banner (Navy/Royal Blue Gradient) */
.logs-hero-banner {
    background: linear-gradient(135deg, var(--primary-navy) 0%, #1e3a8a 55%, var(--secondary-blue) 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.18);
    position: relative;
    overflow: hidden;
}

.logs-hero-banner::after {
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

/* KPI Summary Cards */
.logs-kpi-card {
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

.logs-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -6px rgba(15, 23, 42, 0.09);
    border-color: #cbd5e1;
}

.kpi-accent-blue   { border-top: 4px solid var(--primary-blue); }
.kpi-accent-purple { border-top: 4px solid var(--purple-color); }
.kpi-accent-green  { border-top: 4px solid var(--success-color); }
.kpi-accent-amber  { border-top: 4px solid var(--warning-color); }

.kpi-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.kpi-icon-blue   { background-color: var(--light-blue); color: var(--primary-blue); }
.kpi-icon-purple { background-color: #f3e8ff; color: var(--purple-color); }
.kpi-icon-green  { background-color: #f0fdf4; color: var(--success-color); }
.kpi-icon-amber  { background-color: #fffbeb; color: var(--warning-color); }

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

/* Glass Panel & Table Styling */
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

.logs-table th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.95rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
}

.logs-table td {
    padding: 0.95rem 1.25rem;
    vertical-align: middle;
    font-size: 0.88rem;
    color: var(--main-text);
    border-bottom: 1px solid #f1f5f9;
}

.logs-table tr:last-child td {
    border-bottom: none;
}

.ip-badge {
    background-color: #f1f5f9;
    color: #334155;
    font-family: var(--bs-font-monospace);
    font-size: 0.8rem;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}
</style>

<div class="logs-page-container">

    <!-- 1. Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item text-muted">Administration</li>
            <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Audit Activity Logs</li>
        </ol>
    </nav>

    <!-- 2. Hero Header Banner -->
    <div class="logs-hero-banner mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h2 class="fw-bold mb-0 text-white fs-3">Audit Activity Logs</h2>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">Security Governance</span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">Monitor all actions, logins, database updates, and record alterations with IP details.</p>
                </div>
            </div>
            <div>
                <a href="../../ajax/admin.php?action=export_audit_logs&action_filter=<?php echo urlencode($filterAction); ?>&user_filter=<?php echo urlencode($filterUser); ?>&from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>" class="btn btn-light btn-md fw-bold shadow-sm rounded-3 text-primary px-4 py-2">
                    <i class="fa-solid fa-file-csv me-1.5"></i> Export CSV Ledger
                </a>
            </div>
        </div>
    </div>

    <!-- 3. KPI Summary Row -->
    <div class="row g-3 mb-4">
        <!-- Total Logged Events -->
        <div class="col-sm-6 col-lg-3">
            <div class="logs-kpi-card kpi-accent-blue">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Total System Events</span>
                    <div class="kpi-icon kpi-icon-blue">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format($totalLogs); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-filter text-primary"></i>
                        <span>Filtered activity records</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Auth & Logins -->
        <div class="col-sm-6 col-lg-3">
            <div class="logs-kpi-card kpi-accent-purple">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Authentication Events</span>
                    <div class="kpi-icon kpi-icon-purple">
                        <i class="fa-solid fa-right-to-bracket"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format($totalAuthEvents); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-user-lock text-purple"></i>
                        <span>User logins & auth attempts</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Modifications -->
        <div class="col-sm-6 col-lg-3">
            <div class="logs-kpi-card kpi-accent-green">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Data Modifications</span>
                    <div class="kpi-icon kpi-icon-green">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format($totalDataMods); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-database text-success"></i>
                        <span>Insert, update & delete actions</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unique IP Locations -->
        <div class="col-sm-6 col-lg-3">
            <div class="logs-kpi-card kpi-accent-amber">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Unique Operator IPs</span>
                    <div class="kpi-icon kpi-icon-amber">
                        <i class="fa-solid fa-network-wired"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format($uniqueIpCount); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-globe text-warning"></i>
                        <span>Distinct client IP addresses</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Filter Toolbar -->
    <div class="glass-panel p-4 mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-filter text-primary"></i>
            <h6 class="fw-bold mb-0 text-dark">Filter Audit Activity Ledger</h6>
        </div>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-dark">Filter by Action</label>
                <input type="text" class="form-control bg-light" name="action_filter" value="<?php echo htmlspecialchars($filterAction); ?>" placeholder="e.g. Saved, Deleted, Login">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-dark">Filter by Operator Username</label>
                <input type="text" class="form-control bg-light" name="user_filter" value="<?php echo htmlspecialchars($filterUser); ?>" placeholder="e.g. admin, cashier">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-dark">From Date</label>
                <input type="date" class="form-control bg-light" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-dark">To Date</label>
                <input type="date" class="form-control bg-light" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Filter
                </button>
                <a href="logs.php" class="btn btn-outline-secondary w-100 fw-semibold">Reset</a>
            </div>
        </form>
    </div>

    <!-- 5. Audit Log History Table -->
    <div class="glass-panel mb-4">
        <div class="panel-header">
            <h5 class="fw-bold mb-0 text-dark fs-6 d-flex align-items-center gap-2">
                <i class="fa-solid fa-list-ul text-primary"></i> System Activity Timeline Ledger
            </h5>
            <span class="badge bg-primary-subtle text-primary px-3 py-1.5 rounded-pill fw-semibold small">
                Showing <?php echo count($logs); ?> Records
            </span>
        </div>
        <div class="table-responsive">
            <table class="table logs-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="70">ID</th>
                        <th>User Operator</th>
                        <th>Action Category</th>
                        <th>Description & Alteration Details</th>
                        <th>Client IP</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?php echo $log['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2.5">
                                        <div class="bg-primary-subtle text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; font-size: 0.8rem;">
                                            <?php echo strtoupper(substr($log['username'] ?? 'U', 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark fs-6"><?php echo sanitize($log['full_name'] ?: ($log['username'] ?: 'System')); ?></div>
                                            <div class="text-muted small">@<?php echo sanitize($log['username'] ?: 'system'); ?> • <span class="badge bg-light text-muted border"><?php echo sanitize($log['role_name'] ?: 'System'); ?></span></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $a = strtolower($log['action'] ?? '');
                                    $badgeClass = 'bg-secondary-subtle text-secondary';
                                    if (str_contains($a, 'success') || str_contains($a, 'login') || str_contains($a, 'paid') || str_contains($a, 'saved') || str_contains($a, 'created') || str_contains($a, 'add')) {
                                        $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                                    } elseif (str_contains($a, 'failed') || str_contains($a, 'block') || str_contains($a, 'delete') || str_contains($a, 'remove') || str_contains($a, 'error')) {
                                        $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                                    } elseif (str_contains($a, 'update') || str_contains($a, 'edit') || str_contains($a, 'toggle')) {
                                        $badgeClass = 'bg-warning-subtle text-warning border border-warning-subtle';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?> px-3 py-1.5 rounded-2 fw-bold">
                                        <?php echo sanitize($log['action']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-dark small" style="max-width: 380px; line-height: 1.4;">
                                        <?php echo sanitize($log['description'] ?: ($log['details'] ?? 'System Event')); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="ip-badge"><?php echo sanitize($log['ip_address'] ?? '127.0.0.1'); ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?php echo date('d M Y', strtotime($log['created_at'])); ?></div>
                                    <div class="text-muted small" style="font-size:0.75rem;"><i class="fa-regular fa-clock me-1"></i><?php echo date('h:i:s A', strtotime($log['created_at'])); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-clipboard-list fs-1 text-muted mb-2 d-block"></i>
                                <div>No system activity audit logs matching your search criteria.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <?php if ($totalLogs > $limit): ?>
            <div class="p-3 border-top bg-light d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <span class="text-muted small fw-semibold">
                    Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalLogs); ?> of <?php echo number_format($totalLogs); ?> logged activity events
                </span>
                <div class="btn-group">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&action_filter=<?php echo urlencode($filterAction); ?>&user_filter=<?php echo urlencode($filterUser); ?>&from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>" class="btn btn-sm btn-outline-primary fw-semibold px-3">
                            <i class="fa-solid fa-chevron-left me-1"></i> Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($offset + $limit < $totalLogs): ?>
                        <a href="?page=<?php echo $page + 1; ?>&action_filter=<?php echo urlencode($filterAction); ?>&user_filter=<?php echo urlencode($filterUser); ?>&from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>" class="btn btn-sm btn-outline-primary fw-semibold px-3">
                            Next <i class="fa-solid fa-chevron-right ms-1"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
