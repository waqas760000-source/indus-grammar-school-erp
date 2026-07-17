<?php
/**
 * Indus Grammar School ERP - Dashboard announcements Management console
 * Version 4.0.0
 */

$pageTitle = 'Dashboard Announcements';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('communication_send');

// Load model
require_once __DIR__ . '/../../models/Announcement.php';

$announcements = Announcement::all();

?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-bullhorn text-primary me-2"></i>Announcements console</h3>
        <p class="text-muted small mb-0">Publish target-audience news notices that appear on user dashboards upon logging in.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#announceModal" onclick="clearAnnForm()">
            <i class="fa-solid fa-plus me-2"></i>New Announcement
        </button>
    </div>
</div>

<!-- Grid Table of Announcements -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:12px;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="80">Index</th>
                        <th>Notice Title</th>
                        <th>Audience Target</th>
                        <th class="text-center">Active Range</th>
                        <th class="text-center">Priority</th>
                        <th class="text-center">Status</th>
                        <th>Published By</th>
                        <th class="text-end" width="160">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($announcements)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No announcements published yet.</td></tr>
                    <?php else: foreach ($announcements as $i => $a): ?>
                        <tr>
                            <td class="text-muted fw-bold"><?php echo $i + 1; ?></td>
                            <td class="fw-bold text-dark">
                                <?php echo htmlspecialchars($a['title']); ?>
                                <small class="text-muted d-block text-xs"><?php echo substr(htmlspecialchars($a['content']), 0, 75); ?>...</small>
                            </td>
                            <td><span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($a['audience']); ?></span></td>
                            <td class="text-center small">
                                <span class="fw-bold text-dark"><?php echo date('d-M-Y', strtotime($a['start_date'])); ?></span>
                                <div class="text-xs text-muted">to</div>
                                <span class="fw-bold text-dark"><?php echo date('d-M-Y', strtotime($a['end_date'])); ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?php 
                                    echo $a['priority'] === 'Urgent' ? 'danger' : ($a['priority'] === 'Important' ? 'warning' : 'secondary'); 
                                ?>-soft px-3 py-1 rounded-pill fw-bold text-xs"><?php echo $a['priority']; ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $a['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill fw-bold text-xs"><?php echo $a['status']; ?></span>
                            </td>
                            <td><small class="fw-semibold text-muted"><?php echo htmlspecialchars($a['published_by_name'] ?: 'System Admin'); ?></small></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary me-1" onclick='editAnnouncement(<?php echo json_encode($a); ?>)'><i class="fa-solid fa-pen"></i></button>
                                <button class="btn btn-sm btn-outline-success me-1" onclick='printAnnouncement(<?php echo json_encode($a); ?>)'><i class="fa-solid fa-print"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick='deleteAnnouncement(<?php echo $a['id']; ?>)'><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade d-print-none" id="announceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary" id="modalTitle"><i class="fa-solid fa-bullhorn me-2 text-primary"></i>Post Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="annForm" method="POST" action="../../ajax/communication.php">
                    <input type="hidden" name="action" value="save_announcement">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="id" id="annId">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Title / Subject</label>
                        <input type="text" class="form-control" name="title" id="annTitle" required placeholder="e.g. Eid Holidays announcement">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Message Details</label>
                        <textarea class="form-control" name="content" id="annContent" rows="5" required placeholder="Write details here..."></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Audience</label>
                            <select class="form-select" name="audience" id="annAudience">
                                <option value="Everyone">Everyone</option>
                                <option value="Students">Students Only</option>
                                <option value="Parents">Parents Only</option>
                                <option value="Teachers">Teachers Only</option>
                                <option value="Staff">Staff Only</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Priority</label>
                            <select class="form-select" name="priority" id="annPriority">
                                <option value="Normal">Normal</option>
                                <option value="Important">Important</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Start Date</label>
                            <input type="date" class="form-control" name="start_date" id="annStart" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">End Date</label>
                            <input type="date" class="form-control" name="end_date" id="annEnd" required value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Status</label>
                        <select class="form-select" name="status" id="annStatus">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="text-end pt-2 pb-3">
                        <button type="button" class="btn btn-outline-secondary px-3 me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Publish Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- PRINT-ONLY TEMPLATE ELEMENT -->
<div id="printContainer" class="d-none d-print-block p-5" style="border: 2px solid #333; border-radius: 10px; max-width: 800px; margin: auto;">
    <div class="text-center border-bottom pb-4 mb-4">
        <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h2>
        <h6 class="text-muted text-uppercase letter-spacing-2">Announcement Broadcast Notice</h6>
    </div>
    
    <div class="mb-4">
        <h4 class="fw-bold text-dark" id="pTitle">Notice Title</h4>
        <div class="text-muted small">
            Date Issued: <?php echo date('d-M-Y'); ?> | Priority: <span id="pPriority">Normal</span>
        </div>
    </div>

    <div class="mb-5 text-dark" id="pContent" style="line-height: 1.8; font-size: 14px;">
        Notice body content goes here...
    </div>

    <div class="row pt-5 align-items-end">
        <div class="col-6">
            <div class="small text-muted">Audience Target:</div>
            <div class="fw-bold text-secondary text-uppercase" id="pAudience">Everyone</div>
        </div>
        <div class="col-6 text-end">
            <div style="border-top: 1px solid #777; width: 180px; margin-left: auto;" class="pt-2">
                <span class="small fw-semibold text-dark">Authorized Signature</span>
                <div class="text-muted text-xs">Indus School ERP Dispatch Desk</div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #printContainer, #printContainer * {
        visibility: visible;
    }
    #printContainer {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        border: 0 !important;
    }
}
</style>

<?php $extraJS = '<script>
function clearAnnForm() {
    document.getElementById("annId").value = "";
    document.getElementById("annTitle").value = "";
    document.getElementById("annContent").value = "";
    document.getElementById("annAudience").value = "Everyone";
    document.getElementById("annPriority").value = "Normal";
    document.getElementById("annStatus").value = "Active";
    document.getElementById("modalTitle").innerHTML = "<i class=\"fa-solid fa-bullhorn me-2 text-primary\"></i>Post Announcement";
}

function editAnnouncement(obj) {
    document.getElementById("annId").value = obj.id;
    document.getElementById("annTitle").value = obj.title;
    document.getElementById("annContent").value = obj.content;
    document.getElementById("annAudience").value = obj.audience;
    document.getElementById("annPriority").value = obj.priority;
    document.getElementById("annStatus").value = obj.status;
    document.getElementById("annStart").value = obj.start_date;
    document.getElementById("annEnd").value = obj.end_date;
    
    document.getElementById("modalTitle").innerHTML = "<i class=\"fa-solid fa-pen me-2 text-primary\"></i>Update Announcement";
    
    let modal = new bootstrap.Modal(document.getElementById("announceModal"));
    modal.show();
}

function printAnnouncement(obj) {
    document.getElementById("pTitle").textContent = obj.title;
    document.getElementById("pContent").textContent = obj.content;
    document.getElementById("pPriority").textContent = obj.priority;
    document.getElementById("pAudience").textContent = obj.audience;
    window.print();
}

function deleteAnnouncement(id) {
    if (confirm("Are you sure you want to delete this announcement?")) {
        let form = document.createElement("form");
        form.method = "POST";
        form.action = "../../ajax/communication.php";
        
        let act = document.createElement("input");
        act.type = "hidden";
        act.name = "action";
        act.value = "delete_announcement";
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
