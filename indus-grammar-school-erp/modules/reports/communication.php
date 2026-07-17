<?php
/**
 * Indus Grammar School ERP - Communication Dispatch History Log Reports
 * Version 4.0.0
 */

$pageTitle = 'Communication Reports Panel';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Filter values
$selectedReport = sanitize($_GET['report_type'] ?? 'sms_history');
$dateFrom        = sanitize($_GET['date_from'] ?? date('Y-m-01'));
$dateTo          = sanitize($_GET['date_to'] ?? date('Y-m-d'));

$reportTitle = "Communication Report";
$reportData = [];

try {
    $sql = "
        SELECT al.*, u.username as sent_by_name
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.created_at >= :from AND al.created_at <= :to
    ";
    $params = [
        'from' => $dateFrom . ' 00:00:00',
        'to'   => $dateTo . ' 23:59:59'
    ];

    switch ($selectedReport) {
        case 'sms_history':
            $reportTitle = "SMS Dispatch History Logs";
            $sql .= " AND al.action = 'SMS Sent'";
            break;
        case 'email_history':
            $reportTitle = "Email Dispatch History Logs";
            $sql .= " AND al.action = 'Email Sent'";
            break;
        case 'announcements':
            $reportTitle = "School-wide Announcement Broadcast Logs";
            $sql .= " AND al.action = 'Announcement Send'";
            break;
        case 'circular_history':
            $reportTitle = "Circulars & Reminders Log";
            $sql .= " AND al.action = 'Reminder Sent'";
            break;
    }

    $sql .= " ORDER BY al.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Communication report error: " . $e->getMessage());
}

?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-comments text-primary me-2"></i>Communication Reports</h3>
        <p class="text-muted small mb-0">Trace outbound SMS dispatch lists, system email history logs and broadcasted announcements.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-outline-primary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Ledger</button>
        <button class="btn btn-outline-success px-3 ms-2" onclick="exportToExcel()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
        <a href="dashboard.php" class="btn btn-outline-secondary px-3 ms-2"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Filters Form -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Communication Type</label>
                <select class="form-select" name="report_type" onchange="this.form.submit()">
                    <option value="sms_history" <?php echo $selectedReport === 'sms_history' ? 'selected' : ''; ?>>SMS History</option>
                    <option value="email_history" <?php echo $selectedReport === 'email_history' ? 'selected' : ''; ?>>Email History</option>
                    <option value="announcements" <?php echo $selectedReport === 'announcements' ? 'selected' : ''; ?>>Announcements</option>
                    <option value="circular_history" <?php echo $selectedReport === 'circular_history' ? 'selected' : ''; ?>>Circular History</option>
                </select>
            </div>

            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted">Dispatch Date Range</label>
                <div class="input-group">
                    <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                    <span class="input-group-text">to</span>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                </div>
            </div>

            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Search Logs</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Output -->
<div class="card border-0 shadow-sm" style="border-radius:12px;" id="reportPrintArea">
    <!-- Print Header -->
    <div class="card-header bg-white border-0 pt-4 px-4 text-center d-none d-print-block border-bottom pb-3">
        <h3 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h3>
        <h5 class="text-secondary fw-semibold mb-1"><?php echo htmlspecialchars($reportTitle); ?></h5>
        <div class="text-muted small">
            Date: <?php echo date('d-M-Y H:i'); ?> | Generated By: <?php echo htmlspecialchars($_SESSION['username'] ?? 'ERP Admin'); ?>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                <thead>
                    <tr>
                        <th width="80">Index</th>
                        <th width="180">Dispatch Time</th>
                        <th>Sent By</th>
                        <th>Message Content Detail</th>
                        <th class="text-center" width="120">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No communication logs recorded for this range.</td></tr>
                    <?php else: foreach ($reportData as $i => $row): ?>
                        <tr>
                            <td class="text-muted fw-bold"><?php echo $i + 1; ?></td>
                            <td class="small fw-semibold"><?php echo date('d-M-Y, h:i A', strtotime($row['created_at'])); ?></td>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['sent_by_name'] ?: 'System Manager'); ?></td>
                            <td class="small text-muted"><?php echo htmlspecialchars($row['description']); ?></td>
                            <td class="text-center">
                                <span class="badge bg-success-soft px-3 py-1 rounded-pill fw-bold text-xs"><i class="fa-solid fa-circle-check me-1"></i>Delivered</span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #reportPrintArea, #reportPrintArea * {
        visibility: visible;
    }
    #reportPrintArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none !important;
        border: 0 !important;
    }
    .custom-table th, .custom-table td {
        border: 1px solid #ddd !important;
        padding: 8px !important;
        font-size: 11px !important;
    }
}
</style>

<?php $extraJS = '<script>
function exportToExcel() {
    let table = document.getElementById("reportDataTable");
    let html = table.outerHTML;
    let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
    let link = document.createElement("a");
    link.download = "' . strtolower(str_replace(' ', '_', $reportTitle)) . '_' . date('Ymd') . '.xls";
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
