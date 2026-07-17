<?php
/**
 * Indus Grammar School ERP - Circulars notices Management console
 * Version 4.0.0
 */

$pageTitle = 'School Circulars';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('communication_send');

// Load models
require_once __DIR__ . '/../../models/Circular.php';

$circulars = Circular::all();
$classes   = SchoolClass::all();

?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-file-pdf text-primary me-2"></i>School Circulars</h3>
        <p class="text-muted small mb-0">Create formal school notice circulars, attach printable guides and target classes.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#circularModal" onclick="clearCircForm()">
            <i class="fa-solid fa-plus me-2"></i>New Circular Notice
        </button>
    </div>
</div>

<!-- Grid Table of Circulars -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:12px;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="80">Index</th>
                        <th>Notice / Circular Title</th>
                        <th>Program / Class Target</th>
                        <th class="text-center">Issued Date</th>
                        <th class="text-center">Expiry Date</th>
                        <th>Attachment File</th>
                        <th>Published By</th>
                        <th class="text-end" width="160">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($circulars)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No circular notices issued yet.</td></tr>
                    <?php else: foreach ($circulars as $i => $c): ?>
                        <tr>
                            <td class="text-muted fw-bold"><?php echo $i + 1; ?></td>
                            <td class="fw-bold text-dark">
                                <?php echo htmlspecialchars($c['title']); ?>
                                <small class="text-muted d-block text-xs"><?php echo substr(htmlspecialchars($c['description']), 0, 75); ?>...</small>
                            </td>
                            <td>
                                <?php if ($c['class_name']): ?>
                                    <span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['class_section']); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-dark-soft text-dark px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($c['audience']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center small fw-semibold text-dark"><?php echo date('d-M-Y', strtotime($c['issue_date'])); ?></td>
                            <td class="text-center small fw-semibold text-muted"><?php echo date('d-M-Y', strtotime($c['expiry_date'])); ?></td>
                            <td>
                                <?php if ($c['attachment_path']): ?>
                                    <a href="../../<?php echo $c['attachment_path']; ?>" target="_blank" class="btn btn-xs btn-outline-info rounded-pill px-3"><i class="fa-solid fa-paperclip me-1"></i>Open File</a>
                                <?php else: ?>
                                    <span class="text-muted text-xs">—</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="fw-semibold text-muted"><?php echo htmlspecialchars($c['published_by_name'] ?: 'System Admin'); ?></small></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary me-1" onclick='editCircular(<?php echo json_encode($c); ?>)'><i class="fa-solid fa-pen"></i></button>
                                <button class="btn btn-sm btn-outline-success me-1" onclick='printCircular(<?php echo json_encode($c); ?>)'><i class="fa-solid fa-print"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick='deleteCircular(<?php echo $c['id']; ?>)'><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade d-print-none" id="circularModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary" id="modalTitle"><i class="fa-solid fa-file-invoice me-2 text-primary"></i>Issue Circular Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="circForm" method="POST" action="../../ajax/communication.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_circular">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="id" id="circId">
                    <input type="hidden" name="existing_attachment" id="circExistingAttachment">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Title / Subject</label>
                        <input type="text" class="form-control" name="title" id="circTitle" required placeholder="e.g. Sports Day Schedule details">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Notice Description</label>
                        <textarea class="form-control" name="description" id="circDesc" rows="4" required placeholder="Write details here..."></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Audience</label>
                            <select class="form-select" name="audience" id="circAudience">
                                <option value="Everyone">Everyone</option>
                                <option value="School">School Program</option>
                                <option value="Academy">Academy Program</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Class Specific (Optional)</label>
                            <select class="form-select" name="class_id" id="circClass">
                                <option value="0">All Classes</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Issue Date</label>
                            <input type="date" class="form-control" name="issue_date" id="circIssue" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Expiry Date</label>
                            <input type="date" class="form-control" name="expiry_date" id="circExpiry" required value="<?php echo date('Y-m-d', strtotime('+15 days')); ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Attachment File (PDF/Image)</label>
                        <input type="file" class="form-control" name="attachment">
                        <div id="existingFileLabel" class="small text-muted mt-1" style="display:none;"></div>
                    </div>

                    <div class="text-end pt-2 pb-3">
                        <button type="button" class="btn btn-outline-secondary px-3 me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Publish Circular</button>
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
        <h6 class="text-muted text-uppercase letter-spacing-2">Official School Circular Notice</h6>
    </div>
    
    <div class="mb-4">
        <h4 class="fw-bold text-dark" id="pTitle">Notice Title</h4>
        <div class="text-muted small">
            Date of Issue: <?php echo date('d-M-Y'); ?> | Expiry Date: <span id="pExpiry">Expiry</span>
        </div>
    </div>

    <div class="mb-5 text-dark" id="pDescription" style="line-height: 1.8; font-size: 14px;">
        Notice description content goes here...
    </div>

    <div class="row pt-5 align-items-end">
        <div class="col-6">
            <div class="small text-muted">Audience Target Class:</div>
            <div class="fw-bold text-secondary text-uppercase" id="pClass">Everyone</div>
        </div>
        <div class="col-6 text-end">
            <div style="border-top: 1px solid #777; width: 180px; margin-left: auto;" class="pt-2">
                <span class="small fw-semibold text-dark">Authorized Signature</span>
                <div class="text-muted text-xs">Principal Secretariat Desk</div>
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
function clearCircForm() {
    document.getElementById("circId").value = "";
    document.getElementById("circTitle").value = "";
    document.getElementById("circDesc").value = "";
    document.getElementById("circAudience").value = "Everyone";
    document.getElementById("circClass").value = "0";
    document.getElementById("circExistingAttachment").value = "";
    document.getElementById("existingFileLabel").style.display = "none";
    document.getElementById("modalTitle").innerHTML = "<i class=\"fa-solid fa-file-invoice me-2 text-primary\"></i>Issue Circular Notice";
}

function editCircular(obj) {
    document.getElementById("circId").value = obj.id;
    document.getElementById("circTitle").value = obj.title;
    document.getElementById("circDesc").value = obj.description;
    document.getElementById("circAudience").value = obj.audience;
    document.getElementById("circClass").value = obj.class_id || "0";
    document.getElementById("circIssue").value = obj.issue_date;
    document.getElementById("circExpiry").value = obj.expiry_date;
    
    document.getElementById("circExistingAttachment").value = obj.attachment_path || "";
    if (obj.attachment_path) {
        let lbl = document.getElementById("existingFileLabel");
        lbl.textContent = `Current File: ${obj.attachment_path.split("/").pop()}`;
        lbl.style.display = "block";
    } else {
        document.getElementById("existingFileLabel").style.display = "none";
    }

    document.getElementById("modalTitle").innerHTML = "<i class=\"fa-solid fa-pen me-2 text-primary\"></i>Update Circular Details";
    
    let modal = new bootstrap.Modal(document.getElementById("circularModal"));
    modal.show();
}

function printCircular(obj) {
    document.getElementById("pTitle").textContent = obj.title;
    document.getElementById("pDescription").textContent = obj.description;
    document.getElementById("pExpiry").textContent = obj.expiry_date;
    
    let cls = "Everyone";
    if (obj.class_name) {
        cls = obj.class_name + " - " + obj.class_section;
    } else {
        cls = obj.audience;
    }
    document.getElementById("pClass").textContent = cls;
    window.print();
}

function deleteCircular(id) {
    if (confirm("Are you sure you want to delete this circular notice?")) {
        let form = document.createElement("form");
        form.method = "POST";
        form.action = "../../ajax/communication.php";
        
        let act = document.createElement("input");
        act.type = "hidden";
        act.name = "action";
        act.value = "delete_circular";
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
