<?php
/**
 * Indus Grammar School ERP - Database Backup & Restore Panel
 * Version 7.0.0 — Commercial Redesign (System Snapshots, Exports & Restoration)
 */

$pageTitle = 'Backup & Restore';
$breadcrumbActive = 'Backup & Restore';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

$db = Database::getConnection();

// Fetch previous backups registered in database
$backups = [];
$totalSnapshots = 0;
$latestBackupDate = 'No backups recorded';
$totalStorageKb = 0;

try {
    $backups = $db->query("
        SELECT bh.*, u.username as creator_name 
        FROM backup_history bh 
        LEFT JOIN users u ON bh.created_by = u.id 
        ORDER BY bh.created_at DESC
    ")->fetchAll();

    $totalSnapshots = count($backups);
    if (!empty($backups)) {
        $latestBackupDate = date('d M Y, h:i A', strtotime($backups[0]['created_at']));
        foreach ($backups as $b) {
            $totalStorageKb += (float)($b['size_kb'] ?? 0);
        }
    }
} catch (Exception $e) {
    error_log("Error loading backups list: " . $e->getMessage());
}

$storageMbStr = number_format($totalStorageKb / 1024, 2) . ' MB';
?>

<!-- Custom Styling for Backup & Restore Module -->
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

/* Page Outer Canvas */
.backup-page-container {
    background-color: var(--page-background);
    border-radius: 18px;
    padding: 1.5rem;
    min-height: calc(100vh - 100px);
}

/* Hero Header Banner (Navy/Royal Blue Gradient) */
.backup-hero-banner {
    background: linear-gradient(135deg, var(--primary-navy) 0%, #1e3a8a 55%, var(--secondary-blue) 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.18);
    position: relative;
    overflow: hidden;
}

.backup-hero-banner::after {
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
.backup-kpi-card {
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

.backup-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -6px rgba(15, 23, 42, 0.09);
    border-color: #cbd5e1;
}

.kpi-accent-blue   { border-top: 4px solid var(--primary-blue); }
.kpi-accent-amber  { border-top: 4px solid var(--warning-color); }
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

.kpi-icon-blue   { background-color: var(--light-blue); color: var(--primary-blue); }
.kpi-icon-amber  { background-color: #fffbeb; color: var(--warning-color); }
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

.kpi-value-text {
    font-size: 1.25rem;
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

.backup-table th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.95rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
}

.backup-table td {
    padding: 0.95rem 1.25rem;
    vertical-align: middle;
    font-size: 0.88rem;
    color: var(--main-text);
    border-bottom: 1px solid #f1f5f9;
}

.backup-table tr:last-child td {
    border-bottom: none;
}

.sql-file-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background-color: #e0f2fe;
    color: #0284c7;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}
</style>

<div class="backup-page-container">

    <!-- 1. Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item text-muted">Administration</li>
            <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Backup & Restore</li>
        </ol>
    </nav>

    <!-- 2. Hero Header Banner -->
    <div class="backup-hero-banner mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid fa-database"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h2 class="fw-bold mb-0 text-white fs-3">Backup & Restore</h2>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">Disaster Recovery</span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">Generate database backups, download SQL archives, or restore the ERP database state from past snapshots.</p>
                </div>
            </div>
            <div>
                <button type="button" class="btn btn-light btn-md fw-bold shadow-sm rounded-3 text-primary px-4 py-2" id="btnBackupNow">
                    <i class="fa-solid fa-file-export me-1.5"></i> Create SQL Backup
                </button>
            </div>
        </div>
    </div>

    <!-- 3. KPI Summary Row -->
    <div class="row g-3 mb-4">
        <!-- Total Snapshots -->
        <div class="col-sm-6 col-lg-3">
            <div class="backup-kpi-card kpi-accent-blue">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Total System Snapshots</span>
                    <div class="kpi-icon kpi-icon-blue">
                        <i class="fa-solid fa-box-archive"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo number_format($totalSnapshots); ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-file-code text-primary"></i>
                        <span>Registered SQL dumps</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Latest Snapshot -->
        <div class="col-sm-6 col-lg-3">
            <div class="backup-kpi-card kpi-accent-amber">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Latest Backup Created</span>
                    <div class="kpi-icon kpi-icon-amber">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-value-text text-truncate"><?php echo $latestBackupDate; ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-calendar text-warning"></i>
                        <span>Most recent snapshot</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Storage Used -->
        <div class="col-sm-6 col-lg-3">
            <div class="backup-kpi-card kpi-accent-purple">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Total Archive Storage</span>
                    <div class="kpi-icon kpi-icon-purple">
                        <i class="fa-solid fa-hard-drive"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-number"><?php echo $storageMbStr; ?></div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-server text-purple"></i>
                        <span>Accumulated snapshot size</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Health State -->
        <div class="col-sm-6 col-lg-3">
            <div class="backup-kpi-card kpi-accent-green">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-label-text">Database State</span>
                    <div class="kpi-icon kpi-icon-green">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                </div>
                <div>
                    <div class="kpi-value-text text-success">Healthy / Online</div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-circle-check text-success"></i>
                        <span>MySQL InnoDB Engine</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Historical System Snapshots Data Table -->
    <div class="glass-panel mb-4">
        <div class="panel-header">
            <h5 class="fw-bold mb-0 text-dark fs-6 d-flex align-items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-primary"></i> Historical Database System Snapshots
            </h5>
            <span class="badge bg-primary-subtle text-primary px-3 py-1.5 rounded-pill fw-semibold small">
                <?php echo count($backups); ?> SQL Archives
            </span>
        </div>
        <div class="table-responsive">
            <table class="table backup-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="70">ID</th>
                        <th>Snapshot File Name</th>
                        <th>Export Date & Time</th>
                        <th>File Size</th>
                        <th>Generated By</th>
                        <th class="text-end" width="300">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($backups)): ?>
                        <?php foreach ($backups as $b): ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?php echo $b['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="sql-file-icon">
                                            <i class="fa-solid fa-file-code"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark font-monospace small"><?php echo sanitize($b['backup_name']); ?></div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">Full Schema & Database Data Dump</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?php echo date('d M Y', strtotime($b['created_at'])); ?></div>
                                    <div class="text-muted small" style="font-size:0.75rem;"><i class="fa-regular fa-clock me-1"></i><?php echo date('h:i A', strtotime($b['created_at'])); ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-3 py-1.5 rounded-pill font-monospace fw-bold">
                                        <?php echo number_format($b['size_kb'], 2); ?> KB
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary px-2.5 py-1 rounded-2">
                                        <i class="fa-solid fa-user me-1"></i><?php echo sanitize($b['creator_name'] ?: 'System'); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex align-items-center justify-content-end gap-1.5">
                                        <!-- Download SQL File -->
                                        <a href="../../ajax/admin.php?action=download_backup&id=<?php echo $b['id']; ?>" class="btn btn-sm btn-outline-primary fw-semibold px-3 rounded-2" title="Download SQL Dump Archive">
                                            <i class="fa-solid fa-download me-1"></i> Download
                                        </a>

                                        <!-- Restore Snapshot -->
                                        <button type="button" class="btn btn-sm btn-outline-warning fw-semibold btn-restore-backup rounded-2" data-id="<?php echo $b['id']; ?>" title="Restore System State">
                                            <i class="fa-solid fa-rotate-left me-1"></i> Restore
                                        </button>

                                        <!-- Delete Backup -->
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-backup rounded-2" data-id="<?php echo $b['id']; ?>" title="Delete Backup Archive">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-database fs-1 text-muted mb-2 d-block"></i>
                                <div>No database snapshots generated yet. Click "Create SQL Backup" to export a system dump.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 5. Disaster Recovery Guidelines Card -->
    <div class="glass-panel p-4 bg-light border-0">
        <div class="d-flex align-items-start gap-3">
            <div class="p-3 bg-primary-subtle text-primary rounded-circle">
                <i class="fa-solid fa-shield-halved fs-3"></i>
            </div>
            <div>
                <h6 class="fw-bold text-dark mb-1">Disaster Recovery & Backup Best Practices</h6>
                <p class="text-muted small mb-0 fs-7">
                    Generating regular database snapshot backups protects school student registers, attendance records, fee challans, and transaction ledgers from accidental data loss. Always store offline copies of SQL archives on secure offsite drives. Restoring a snapshot overwrites all active database tables to that exact point in time.
                </p>
            </div>
        </div>
    </div>

</div>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="bkpToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="bkpToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- Backdrop Loading Spinner Overlay -->
<div id="loadingOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 align-items-center justify-content-center" style="z-index: 10000; backdrop-filter: blur(4px);">
    <div class="bg-white p-4 rounded-4 text-center shadow-lg border" style="max-width: 360px;">
        <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
        <h6 class="fw-bold text-dark mb-1" id="loadingMsg">Executing process...</h6>
        <p class="text-muted small mb-0">Please do not close or refresh your browser page.</p>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("bkpToast");
    const m = document.getElementById("bkpToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function showLoading(show, msg = "Processing...") {
    const loader = document.getElementById("loadingOverlay");
    const label = document.getElementById("loadingMsg");
    if (show) {
        label.textContent = msg;
        loader.classList.remove("d-none");
        loader.classList.add("d-flex");
    } else {
        loader.classList.remove("d-flex");
        loader.classList.add("d-none");
    }
}

document.addEventListener("DOMContentLoaded", function() {
    
    // Create Backup Action
    const btnBackup = document.getElementById("btnBackupNow");
    if (btnBackup) {
        btnBackup.addEventListener("click", function() {
            showLoading(true, "Generating SQL schema & data export dump...");
            const fd = new FormData();
            fd.append("action", "create_backup");
            fd.append("csrf_token", "' . csrfToken() . '");
            
            fetch("../../ajax/admin.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showLoading(false);
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 900);
                })
                .catch(() => { showLoading(false); showToast("Network communication error.", false); });
        });
    }

    // Delete Backup
    document.querySelectorAll(".btn-delete-backup").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            if(!confirm("Are you sure you want to permanently delete this backup archive from storage? This action cannot be undone.")) return;
            
            showLoading(true, "Removing snapshot backup file...");
            const fd = new FormData();
            fd.append("action", "delete_backup");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/admin.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showLoading(false);
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 900);
                })
                .catch(() => { showLoading(false); showToast("Network communication error.", false); });
        });
    });

    // Restore Backup
    document.querySelectorAll(".btn-restore-backup").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            if(!confirm("WARNING: Restoring the database will overwrite all active tables, students, marks, registers, and transactions with the selected snapshot. Are you sure you want to proceed?")) return;
            
            showLoading(true, "Restoring database state... Please wait.");
            const fd = new FormData();
            fd.append("action", "restore_backup");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/admin.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showLoading(false);
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => {
                        window.location.href = "dashboard.php";
                    }, 1800);
                })
                .catch(() => { showLoading(false); showToast("Network error during restoration.", false); });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
