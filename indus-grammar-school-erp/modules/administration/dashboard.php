<?php
/**
 * Indus Grammar School ERP - Admin Dashboard (Administration Submodule)
 * Version 7.0.0 — Commercial Redesign (System Users, Health Diagnostics & Administrative Gateways)
 */

$pageTitle = 'Admin Dashboard';
$breadcrumbActive = 'Admin Dashboard';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

$db = Database::getConnection();

// -------------------------------------------------------------
// Database Metrics & System Diagnostics Aggregation
// -------------------------------------------------------------
$totalUsers = 0;
$activeUsers = 0;
$inactiveUsers = 0;
$todayLogins = 0;
$totalRoles = 0;
$latestBackupStr = 'No backups recorded';
$latestBackupSize = 'N/A';
$mysqlVersion = 'Unknown';
$auditLogs = [];

try {
    // 1. User & Account Statistics
    $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $activeUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
    $inactiveUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active = 0")->fetchColumn();
    
    // Roles count
    try {
        $totalRoles = (int)$db->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    } catch (Exception $e) {
        $totalRoles = 0;
    }

    // 2. Today's Login Activity Count
    try {
        $todayLogins = (int)$db->query("
            SELECT COUNT(*) FROM audit_logs 
            WHERE (action = 'User Login' OR action LIKE '%login%') 
              AND DATE(created_at) = CURRENT_DATE
        ")->fetchColumn();
    } catch (Exception $e) {
        $todayLogins = 0;
    }
    
    // 3. System Backup Details
    try {
        $latestBackup = $db->query("SELECT created_at, backup_name, file_size FROM backup_history ORDER BY id DESC LIMIT 1")->fetch();
        if ($latestBackup) {
            $latestBackupStr = date('d M Y, h:i A', strtotime($latestBackup['created_at']));
            if (!empty($latestBackup['file_size'])) {
                $latestBackupSize = round($latestBackup['file_size'] / (1024 * 1024), 2) . ' MB';
            }
        }
    } catch (Exception $e) {
        // Table might not exist or be empty
    }

    // 4. MySQL Database Server Version
    try {
        $mysqlVersion = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
        // Shorten long version strings if necessary
        if (preg_match('/^(\d+\.\d+\.\d+)/', $mysqlVersion, $matches)) {
            $mysqlVersion = 'MySQL ' . $matches[1];
        }
    } catch (Exception $e) {
        $mysqlVersion = 'MySQL 8.0+';
    }

    // 5. Recent Audit Activity Logs (Top 6)
    try {
        $auditLogs = $db->query("
            SELECT a.*, u.username, u.full_name 
            FROM audit_logs a 
            LEFT JOIN users u ON a.user_id = u.id 
            ORDER BY a.created_at DESC 
            LIMIT 6
        ")->fetchAll();
    } catch (Exception $e) {
        $auditLogs = [];
    }

} catch (Exception $e) {
    error_log("Error in admin dashboard queries: " . $e->getMessage());
}

$activeAcademicYear = defined('CURRENT_ACADEMIC_YEAR') ? CURRENT_ACADEMIC_YEAR : date('Y');
$phpVersion = PHP_VERSION;
$memoryLimit = ini_get('memory_limit');
$uploadMax = ini_get('upload_max_filesize');
$timezone = date_default_timezone_get();
?>

<!-- Custom Styling for Admin Submodule Dashboard -->
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

/* Dashboard Outer Canvas */
.admin-dashboard-container {
    background-color: var(--page-background);
    border-radius: 18px;
    padding: 1.5rem;
    min-height: calc(100vh - 100px);
}

/* Hero Header Banner (Standard ERP Navy/Royal Blue Gradient) */
.admin-hero-banner {
    background: linear-gradient(135deg, var(--primary-navy) 0%, #1e3a8a 55%, var(--secondary-blue) 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.18);
    position: relative;
    overflow: hidden;
}

.admin-hero-banner::after {
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
.admin-kpi-card {
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

.admin-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -6px rgba(15, 23, 42, 0.09);
    border-color: #cbd5e1;
}

.kpi-accent-blue   { border-top: 4px solid var(--primary-blue); }
.kpi-accent-green  { border-top: 4px solid var(--success-color); }
.kpi-accent-red    { border-top: 4px solid var(--danger-color); }
.kpi-accent-amber  { border-top: 4px solid var(--warning-color); }
.kpi-accent-purple { border-top: 4px solid var(--purple-color); }

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
.kpi-icon-green  { background-color: #f0fdf4; color: var(--success-color); }
.kpi-icon-red    { background-color: #fef2f2; color: var(--danger-color); }
.kpi-icon-amber  { background-color: #fffbeb; color: var(--warning-color); }
.kpi-icon-purple { background-color: #f3e8ff; color: var(--purple-color); }

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

/* Gateway Shortcut Cards */
.gateway-card {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 4px 10px -2px rgba(15, 23, 42, 0.03);
    display: flex;
    align-items: center;
    gap: 1rem;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s ease;
}

.gateway-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(29, 78, 216, 0.12);
    border-color: #bfdbfe;
    color: inherit;
}

.gateway-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--light-blue);
    color: var(--primary-blue);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.gateway-card:hover .gateway-icon {
    background: var(--primary-blue);
    color: #ffffff;
}

.gateway-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--primary-navy);
    margin: 0;
}

.gateway-desc {
    font-size: 0.78rem;
    color: var(--muted-text);
    margin: 0;
}

/* System Health Card & Tables */
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

.panel-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--primary-navy);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.health-table td {
    padding: 0.85rem 1.25rem;
    font-size: 0.88rem;
    color: var(--main-text);
    border-bottom: 1px solid #f1f5f9;
}

.health-table tr:last-child td {
    border-bottom: none;
}

.audit-table th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
}

.audit-table td {
    padding: 0.85rem 1.25rem;
    vertical-align: middle;
    font-size: 0.85rem;
    color: var(--main-text);
    border-bottom: 1px solid #f1f5f9;
}
</style>

<div class="admin-dashboard-container">

    <!-- 1. Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item text-muted">Administration</li>
            <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Admin Dashboard</li>
        </ol>
    </nav>

    <!-- 2. Hero Header Banner -->
    <div class="admin-hero-banner mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid fa-gauge-high"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h2 class="fw-bold mb-0 text-white fs-3">Admin Dashboard</h2>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">System Administration</span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">Overview of the system users, health status, and administrative configurations.</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="<?php echo APP_URL; ?>/modules/administration/settings.php" class="btn btn-light btn-sm fw-bold shadow-sm rounded-3 text-primary px-3 py-2">
                    <i class="fa-solid fa-gear me-1"></i> System Settings
                </a>
                <a href="<?php echo APP_URL; ?>/modules/administration/backup.php" class="btn btn-outline-light btn-sm fw-bold rounded-3 px-3 py-2">
                    <i class="fa-solid fa-database me-1"></i> Create Backup
                </a>
            </div>
        </div>
    </div>

    <!-- 3. KPI Summary Row (Users & System Activity) -->
    <div class="row g-3 mb-4">
        <!-- Total Users -->
        <div class="col-sm-6 col-lg-3">
            <div class="admin-kpi-card kpi-accent-blue">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Total Users</span>
                    <div class="kpi-icon kpi-icon-blue">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format($totalUsers); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-user-shield text-primary"></i>
                        <span>Registered accounts</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Users -->
        <div class="col-sm-6 col-lg-3">
            <div class="admin-kpi-card kpi-accent-green">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Active Accounts</span>
                    <div class="kpi-icon kpi-icon-green">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format($activeUsers); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-circle-check text-success"></i>
                        <span>Active logins enabled</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inactive Users -->
        <div class="col-sm-6 col-lg-3">
            <div class="admin-kpi-card kpi-accent-red">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Inactive Users</span>
                    <div class="kpi-icon kpi-icon-red">
                        <i class="fa-solid fa-user-xmark"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format($inactiveUsers); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-ban text-danger"></i>
                        <span>Disabled / suspended</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Logins -->
        <div class="col-sm-6 col-lg-3">
            <div class="admin-kpi-card kpi-accent-amber">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Today's Logins</span>
                    <div class="kpi-icon kpi-icon-amber">
                        <i class="fa-solid fa-right-to-bracket"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format($todayLogins); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-clock-rotate-left text-warning"></i>
                        <span>Unique authentication activity</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Main Diagnostic & Gateways Split Section -->
    <div class="row g-4 mb-4">
        
        <!-- Left Side: System Health & Server Status Diagnostics -->
        <div class="col-lg-6">
            <div class="glass-panel h-100">
                <div class="panel-header">
                    <h5 class="panel-title">
                        <i class="fa-solid fa-server text-primary me-1"></i>
                        System Health & Server Diagnostics
                    </h5>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">
                        <i class="fa-solid fa-circle me-1" style="font-size: 0.55rem;"></i> System Operational
                    </span>
                </div>
                <div class="p-0">
                    <table class="table health-table mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted fw-medium" style="width: 40%;">Database Engine Status</td>
                                <td class="text-end fw-bold text-dark">
                                    <span class="text-success me-2"><i class="fa-solid fa-circle-check"></i> Connected</span>
                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($mysqlVersion); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-medium">PHP Runtime Environment</td>
                                <td class="text-end fw-semibold text-dark">
                                    <i class="fa-brands fa-php me-1 text-primary"></i> v<?php echo htmlspecialchars($phpVersion); ?> (<?php echo PHP_OS_FAMILY; ?>)
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-medium">Active Academic Session</td>
                                <td class="text-end fw-bold text-primary">
                                    <span class="badge bg-primary-subtle text-primary px-3 py-1 rounded-pill fw-bold"><?php echo htmlspecialchars($activeAcademicYear); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-medium">PHP Memory Limit / Max Upload</td>
                                <td class="text-end fw-semibold text-dark">
                                    Memory: <strong><?php echo htmlspecialchars($memoryLimit); ?></strong> | Upload: <strong><?php echo htmlspecialchars($uploadMax); ?></strong>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-medium">System Timezone</td>
                                <td class="text-end fw-semibold text-dark">
                                    <i class="fa-regular fa-clock me-1 text-muted"></i> <?php echo htmlspecialchars($timezone); ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-medium">Latest Snapshot Backup</td>
                                <td class="text-end fw-semibold text-dark">
                                    <?php if ($latestBackupStr !== 'No backups recorded'): ?>
                                        <span class="text-dark fw-bold"><?php echo $latestBackupStr; ?></span>
                                        <span class="badge bg-secondary-subtle text-secondary ms-1"><?php echo $latestBackupSize; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted italic">No snapshot recorded</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Side: Administrative Gateways Grid -->
        <div class="col-lg-6">
            <div class="glass-panel h-100">
                <div class="panel-header">
                    <h5 class="panel-title">
                        <i class="fa-solid fa-network-wired text-primary me-1"></i>
                        Administrative Configurations & Controls
                    </h5>
                </div>
                <div class="p-3">
                    <div class="row g-3">
                        
                        <!-- User Management -->
                        <div class="col-sm-6">
                            <a href="<?php echo APP_URL; ?>/modules/administration/users.php" class="gateway-card">
                                <div class="gateway-icon">
                                    <i class="fa-solid fa-users-gear"></i>
                                </div>
                                <div>
                                    <h6 class="gateway-title">User Management</h6>
                                    <p class="gateway-desc">User accounts & status</p>
                                </div>
                            </a>
                        </div>

                        <!-- Roles & Permissions -->
                        <div class="col-sm-6">
                            <a href="<?php echo APP_URL; ?>/modules/administration/roles.php" class="gateway-card">
                                <div class="gateway-icon" style="background-color: #fee2e2; color: var(--danger-color);">
                                    <i class="fa-solid fa-user-shield"></i>
                                </div>
                                <div>
                                    <h6 class="gateway-title">Roles & Access</h6>
                                    <p class="gateway-desc">Role definitions & levels</p>
                                </div>
                            </a>
                        </div>

                        <!-- System Permissions -->
                        <div class="col-sm-6">
                            <a href="<?php echo APP_URL; ?>/modules/administration/permissions.php" class="gateway-card">
                                <div class="gateway-icon" style="background-color: #f3e8ff; color: var(--purple-color);">
                                    <i class="fa-solid fa-key"></i>
                                </div>
                                <div>
                                    <h6 class="gateway-title">System Permissions</h6>
                                    <p class="gateway-desc">Module level access</p>
                                </div>
                            </a>
                        </div>

                        <!-- School Settings -->
                        <div class="col-sm-6">
                            <a href="<?php echo APP_URL; ?>/modules/administration/settings.php" class="gateway-card">
                                <div class="gateway-icon" style="background-color: #f0fdf4; color: var(--success-color);">
                                    <i class="fa-solid fa-sliders"></i>
                                </div>
                                <div>
                                    <h6 class="gateway-title">School Settings</h6>
                                    <p class="gateway-desc">Identity & info details</p>
                                </div>
                            </a>
                        </div>

                        <!-- Academic Settings -->
                        <div class="col-sm-6">
                            <a href="<?php echo APP_URL; ?>/modules/administration/academic.php" class="gateway-card">
                                <div class="gateway-icon" style="background-color: #fffbeb; color: var(--warning-color);">
                                    <i class="fa-solid fa-graduation-cap"></i>
                                </div>
                                <div>
                                    <h6 class="gateway-title">Academic Setup</h6>
                                    <p class="gateway-desc">Sessions & terms setup</p>
                                </div>
                            </a>
                        </div>

                        <!-- Database Backup -->
                        <div class="col-sm-6">
                            <a href="<?php echo APP_URL; ?>/modules/administration/backup.php" class="gateway-card">
                                <div class="gateway-icon" style="background-color: #e0f2fe; color: #0284c7;">
                                    <i class="fa-solid fa-database"></i>
                                </div>
                                <div>
                                    <h6 class="gateway-title">Database Backup</h6>
                                    <p class="gateway-desc">SQL dumps & restore</p>
                                </div>
                            </a>
                        </div>

                        <!-- Audit Logs -->
                        <div class="col-12">
                            <a href="<?php echo APP_URL; ?>/modules/administration/logs.php" class="gateway-card">
                                <div class="gateway-icon" style="background-color: #f8fafc; color: var(--primary-navy);">
                                    <i class="fa-solid fa-clipboard-list"></i>
                                </div>
                                <div class="d-flex align-items-center justify-content-between flex-grow-1">
                                    <div>
                                        <h6 class="gateway-title">Security & Audit Logs</h6>
                                        <p class="gateway-desc">Trace user activities, login attempts & administrative modifications</p>
                                    </div>
                                    <i class="fa-solid fa-chevron-right text-muted small me-2"></i>
                                </div>
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- 5. Recent System Security Audit Activity Table -->
    <div class="glass-panel mb-2">
        <div class="panel-header">
            <h5 class="panel-title">
                <i class="fa-solid fa-shield-halved text-primary me-1"></i>
                Recent Administrative Activity Audit Log
            </h5>
            <a href="<?php echo APP_URL; ?>/modules/administration/logs.php" class="btn btn-outline-primary btn-sm fw-bold rounded-pill px-3">
                View Full Audit Logs <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="table-responsive">
            <table class="table audit-table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>User</th>
                        <th>Action Performed</th>
                        <th>Details / Module</th>
                        <th>IP Address</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($auditLogs)): ?>
                        <?php foreach ($auditLogs as $idx => $log): ?>
                            <tr>
                                <td class="fw-bold text-muted"><?php echo $idx + 1; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-primary-subtle text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                            <?php echo strtoupper(substr($log['username'] ?? 'U', 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark leading-none"><?php echo htmlspecialchars($log['full_name'] ?: ($log['username'] ?? 'System')); ?></div>
                                            <div class="text-muted small leading-none mt-1">@<?php echo htmlspecialchars($log['username'] ?? 'system'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $actionStr = strtolower($log['action'] ?? '');
                                    $badgeClass = 'bg-secondary-subtle text-secondary';
                                    if (str_contains($actionStr, 'login') || str_contains($actionStr, 'auth')) {
                                        $badgeClass = 'bg-primary-subtle text-primary';
                                    } elseif (str_contains($actionStr, 'create') || str_contains($actionStr, 'add')) {
                                        $badgeClass = 'bg-success-subtle text-success';
                                    } elseif (str_contains($actionStr, 'update') || str_contains($actionStr, 'edit')) {
                                        $badgeClass = 'bg-warning-subtle text-warning';
                                    } elseif (str_contains($actionStr, 'delete') || str_contains($actionStr, 'remove')) {
                                        $badgeClass = 'bg-danger-subtle text-danger';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?> px-2.5 py-1 rounded-2 fw-semibold">
                                        <?php echo htmlspecialchars($log['action'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-dark small"><?php echo htmlspecialchars($log['details'] ?? ($log['module'] ?? 'System Action')); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-muted border font-monospace small"><?php echo htmlspecialchars($log['ip_address'] ?? '127.0.0.1'); ?></span>
                                </td>
                                <td>
                                    <span class="text-muted small">
                                        <i class="fa-regular fa-clock me-1"></i>
                                        <?php echo !empty($log['created_at']) ? date('d M Y, h:i A', strtotime($log['created_at'])) : 'N/A'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-inbox fs-3 mb-2 d-block text-muted"></i>
                                No audit activities recorded yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
