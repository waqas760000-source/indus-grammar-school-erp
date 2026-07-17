<?php
/**
 * Indus Grammar School ERP - Communication Dispatch History Log List
 * Version 4.0.0
 */

$pageTitle = 'Communication History';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('communication_send');

// Load models
require_once __DIR__ . '/../../models/CommHistory.php';

// Filters
$selectedType  = sanitize($_GET['comm_type'] ?? 'sms');
$dateFrom      = sanitize($_GET['date_from'] ?? date('Y-m-01'));
$dateTo        = sanitize($_GET['date_to'] ?? date('Y-m-d'));

$historyData = [];
if ($selectedType === 'sms') {
    $historyData = CommHistory::sms($dateFrom, $dateTo);
} else {
    $historyData = CommHistory::email($dateFrom, $dateTo);
}
?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-history text-primary me-2"></i>Communication History</h3>
        <p class="text-muted small mb-0">Trace status and detailed recipient lists for outbound SMS texts and HTML emails.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="dashboard.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Filters Form -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Communication Type</label>
                <select class="form-select" name="comm_type" onchange="this.form.submit()">
                    <option value="sms" <?php echo $selectedType === 'sms' ? 'selected' : ''; ?>>SMS Logs</option>
                    <option value="email" <?php echo $selectedType === 'email' ? 'selected' : ''; ?>>Email Logs</option>
                </select>
            </div>
            
            <div class="col-md-6">
                <label class="form-label small fw-semibold text-muted">Dispatch Date Range</label>
                <div class="input-group">
                    <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                    <span class="input-group-text">to</span>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                </div>
            </div>

            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Filter Logs</button>
            </div>
        </form>
    </div>
</div>

<!-- Logs Output -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0" id="historyTable">
                <thead>
                    <tr>
                        <th width="80">Index</th>
                        <th width="160">Dispatch Time</th>
                        <th>Target Group</th>
                        <th class="text-center">Count</th>
                        <?php if ($selectedType === 'email'): ?>
                            <th>Subject line</th>
                        <?php endif; ?>
                        <th>Message Preview</th>
                        <th>Sent By</th>
                        <th class="text-center">Status</th>
                        <th class="text-end" width="160">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historyData)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted">No outbox logs found matching the filter criteria.</td></tr>
                    <?php else: foreach ($historyData as $i => $row): ?>
                        <tr>
                            <td class="text-muted fw-bold"><?php echo $i + 1; ?></td>
                            <td class="small fw-semibold"><?php echo date('d-M-Y h:i A', strtotime($row['created_at'])); ?></td>
                            <td class="fw-semibold text-dark"><?php echo htmlspecialchars($row['recipient_type']); ?></td>
                            <td class="text-center fw-bold text-muted"><?php echo $row['recipient_count']; ?> recs</td>
                            <?php if ($selectedType === 'email'): ?>
                                <td class="fw-bold small text-dark"><?php echo htmlspecialchars($row['subject']); ?></td>
                            <?php endif; ?>
                            <td class="small">
                                <?php 
                                    $msg = ($selectedType === 'sms') ? $row['message'] : strip_tags($row['body']);
                                    echo substr(htmlspecialchars($msg), 0, 75) . (strlen($msg) > 75 ? '...' : ''); 
                                ?>
                            </td>
                            <td><small class="fw-bold text-muted"><?php echo htmlspecialchars($row['sent_by_name'] ?: 'System'); ?></small></td>
                            <td class="text-center">
                                <span class="badge bg-<?php 
                                    echo $row['status'] === 'Sent' ? 'success' : ($row['status'] === 'Scheduled' ? 'info' : ($row['status'] === 'Draft' ? 'secondary' : 'danger')); 
                                ?>-soft px-3 py-1 rounded-pill fw-bold text-xs"><?php echo $row['status']; ?></span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-info me-1" onclick='viewDetails(<?php echo json_encode($row); ?>, "<?php echo $selectedType; ?>")' title="View Recipients"><i class="fa-solid fa-users"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick='deleteLog(<?php echo $row['id']; ?>, "<?php echo $selectedType; ?>")'><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Details View -->
<div class="modal fade d-print-none" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-circle-info me-2 text-primary"></i>Dispatch recipients List</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <span class="small text-muted d-block">Target Audience:</span>
                        <strong id="detType" class="text-dark">Entire Class</strong>
                    </div>
                    <div class="col-md-6">
                        <span class="small text-muted d-block">Total Recipients:</span>
                        <strong id="detCount" class="text-dark">15</strong>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Recipients Details</label>
                    <div class="border rounded p-3 bg-light" style="max-height: 180px; overflow-y: auto;" id="detRecipientsList">
                        <!-- Mapped items will append here -->
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Message Body Content</label>
                    <div class="border rounded p-3 bg-white text-dark small" id="detBody" style="min-height: 80px; line-height:1.6; white-space: pre-wrap;">
                        Details
                    </div>
                </div>

                <div class="text-end pt-2 pb-3">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function viewDetails(obj, type) {
    document.getElementById("detType").textContent = obj.recipient_type;
    document.getElementById("detCount").textContent = obj.recipient_count + " recipients";
    
    // Resolve Recipients JSON list
    let listContainer = document.getElementById("detRecipientsList");
    listContainer.innerHTML = "";
    try {
        let recs = JSON.parse(obj.recipients_list);
        for (let contact in recs) {
            let item = document.createElement("div");
            item.className = "d-flex justify-content-between border-bottom py-1 small text-dark";
            item.innerHTML = `<span><strong>${recs[contact]}</strong></span> <span><code>${contact}</code></span>`;
            listContainer.appendChild(item);
        }
    } catch(e) {
        listContainer.textContent = obj.recipients_list;
    }

    // Set message body
    let bodyContainer = document.getElementById("detBody");
    if (type === "sms") {
        bodyContainer.textContent = obj.message;
    } else {
        bodyContainer.innerHTML = obj.body;
    }

    let modal = new bootstrap.Modal(document.getElementById("detailsModal"));
    modal.show();
}

function deleteLog(id, type) {
    if (confirm("Are you sure you want to delete this log entry?")) {
        let form = document.createElement("form");
        form.method = "POST";
        form.action = "../../ajax/communication.php";
        
        let act = document.createElement("input");
        act.type = "hidden";
        act.name = "action";
        act.value = (type === "sms") ? "delete_sms_log" : "delete_email_log";
        form.appendChild(act);

        let key = document.createElement("input");
        key.type = "hidden";
        key.name = "id";
        key.value = id;
        form.appendChild(key);

        let csrf = document.createElement("input");
        csrf.type = "hidden";
        csrf.name = "csrf_token";
        csrf.value = "' . csrfToken() . '";
        form.appendChild(csrf);

        document.body.appendChild(form);
        form.submit();
    }
}
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
