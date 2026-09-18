<?php
/**
 * Indus Grammar School ERP - WhatsApp History Log & Export Module
 * Version 4.0.0
 */

$pageTitle = 'WhatsApp Outbox History';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('communication_send');

// Load model
require_once __DIR__ . '/../../models/WhatsAppHistory.php';

$dateFrom = sanitize($_GET['date_from'] ?? date('Y-m-01'));
$dateTo   = sanitize($_GET['date_to'] ?? date('Y-m-d'));
$search   = sanitize($_GET['search'] ?? '');
$status   = sanitize($_GET['status'] ?? '');

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $logs = WhatsAppHistory::getLogs($dateFrom, $dateTo, $search, $status);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="whatsapp_history_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['#', 'Date & Time', 'Recipient Name', 'Recipient Type', 'Phone Number', 'Template', 'Sent By', 'Status', 'Message Content']);
    foreach ($logs as $i => $r) {
        fputcsv($output, [
            $i + 1,
            date('d-M-Y h:i A', strtotime($r['created_at'])),
            $r['recipient_name'],
            $r['recipient_type'],
            $r['phone'],
            $r['template_name'] ?: 'Custom',
            $r['sent_by_name'] ?: 'System',
            $r['status'],
            $r['message']
        ]);
    }
    fclose($output);
    exit;
}

$logs = WhatsAppHistory::getLogs($dateFrom, $dateTo, $search, $status);
?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-clock-rotate-left text-success me-2"></i>WhatsApp History</h3>
        <p class="text-muted small mb-0">Audit log of outbound Click-to-Chat dispatches and official WhatsApp messages.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button type="button" class="btn btn-outline-secondary px-3 me-2" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print</button>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-outline-success px-3 me-2"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</a>
        <a href="whatsapp.php" class="btn btn-primary px-4"><i class="fa-brands fa-whatsapp me-2"></i>Send WhatsApp</a>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Date Range</label>
                <div class="input-group input-group-sm">
                    <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                    <span class="input-group-text">to</span>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Statuses</option>
                    <option value="Opened" <?php echo $status === 'Opened' ? 'selected' : ''; ?>>Opened (Click-to-Chat)</option>
                    <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending API</option>
                    <option value="Failed" <?php echo $status === 'Failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="Manual" <?php echo $status === 'Manual' ? 'selected' : ''; ?>>Manual</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Search Query</label>
                <input type="text" class="form-control form-control-sm" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by recipient name, phone, or message...">
            </div>

            <div class="col-md-2 text-end">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-filter me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Logs Output Table -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-list me-2 text-primary"></i>WhatsApp Outbox Logs (<?php echo count($logs); ?>)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="50">#</th>
                        <th width="150">Date & Time</th>
                        <th>Recipient</th>
                        <th>Mobile Number</th>
                        <th>Template</th>
                        <th>Message Preview</th>
                        <th>Sent By</th>
                        <th class="text-center">Status</th>
                        <th class="text-end d-print-none" width="100">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No WhatsApp logs found matching the filter criteria.</td></tr>
                    <?php else: foreach ($logs as $i => $row): ?>
                        <tr>
                            <td class="text-muted fw-bold"><?php echo $i + 1; ?></td>
                            <td class="small fw-semibold"><?php echo date('d-M-Y h:i A', strtotime($row['created_at'])); ?></td>
                            <td class="fw-bold text-dark">
                                <?php echo htmlspecialchars($row['recipient_name']); ?>
                                <div class="text-muted text-xs font-normal"><?php echo htmlspecialchars($row['recipient_type']); ?></div>
                            </td>
                            <td class="fw-bold text-success font-monospace"><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td>
                                <?php if ($row['template_name']): ?>
                                    <span class="badge bg-secondary-soft text-dark"><?php echo htmlspecialchars($row['template_name']); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted">Custom</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted text-truncate" style="max-width: 220px;"><?php echo htmlspecialchars($row['message']); ?></td>
                            <td class="small text-dark"><?php echo htmlspecialchars($row['sent_by_name'] ?: 'System'); ?></td>
                            <td class="text-center">
                                <?php
                                    $st = $row['status'];
                                    $badgeClass = 'bg-success';
                                    if ($st === 'Pending') $badgeClass = 'bg-warning text-dark';
                                    if ($st === 'Failed') $badgeClass = 'bg-danger';
                                    if ($st === 'Manual') $badgeClass = 'bg-info text-dark';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?> rounded-pill px-3"><?php echo htmlspecialchars($st); ?></span>
                            </td>
                            <td class="text-end d-print-none">
                                <button type="button" class="btn btn-outline-info btn-xs me-1" onclick='viewMessage(<?php echo json_encode($row); ?>)'><i class="fa-solid fa-eye"></i></button>
                                <button type="button" class="btn btn-outline-danger btn-xs" onclick="deleteLog(<?php echo $row['id']; ?>)"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Message Modal -->
<div class="modal fade d-print-none" id="viewMsgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fa-brands fa-whatsapp me-2"></i>WhatsApp Log Detail</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <table class="table table-sm table-borderless text-xs mb-3">
                    <tr><th width="30%" class="text-muted">Recipient:</th><td class="fw-bold" id="vRecipient"></td></tr>
                    <tr><th class="text-muted">Mobile Number:</th><td class="fw-bold text-success" id="vPhone"></td></tr>
                    <tr><th class="text-muted">Dispatch Date:</th><td id="vDate"></td></tr>
                    <tr><th class="text-muted">Status:</th><td id="vStatus"></td></tr>
                </table>
                <div class="mb-2 fw-semibold text-muted text-xs">Full Message Body:</div>
                <div class="p-3 border rounded bg-light font-monospace text-xs text-dark" id="vMessage" style="white-space: pre-wrap;"></div>
            </div>
            <div class="modal-footer bg-light border-0">
                <a href="#" target="_blank" class="btn btn-success px-4" id="vResendLink"><i class="fa-brands fa-whatsapp me-2"></i>Re-open WhatsApp</a>
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.bg-secondary-soft { background-color: rgba(100, 116, 139, 0.1); color: #334155; }
@media print {
    .d-print-none { display: none !important; }
    body { background: white !important; }
    .card { border: none !important; shadow: none !important; }
}
</style>

<?php $extraJS = '<script>
function viewMessage(row) {
    document.getElementById("vRecipient").textContent = `${row.recipient_name} (${row.recipient_type})`;
    document.getElementById("vPhone").textContent = row.phone;
    document.getElementById("vDate").textContent = row.created_at;
    document.getElementById("vStatus").textContent = row.status;
    document.getElementById("vMessage").textContent = row.message;
    document.getElementById("vResendLink").href = `https://wa.me/${row.phone}?text=${encodeURIComponent(row.message)}`;

    let modal = new bootstrap.Modal(document.getElementById("viewMsgModal"));
    modal.show();
}

function deleteLog(id) {
    if (!confirm("Are you sure you want to delete this WhatsApp log entry?")) return;

    let formData = new FormData();
    formData.append("action", "delete_history");
    formData.append("id", id);

    fetch("../../ajax/whatsapp.php", { method: "POST", body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.status === "success") {
                location.reload();
            } else {
                alert(res.message || "Failed to delete log.");
            }
        });
}
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
