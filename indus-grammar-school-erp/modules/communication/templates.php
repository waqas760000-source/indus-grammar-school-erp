<?php
/**
 * Indus Grammar School ERP - Communication Templates Management
 * Version 4.0.0
 */

$pageTitle = 'Message Templates';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('communication_send');

// Load model
require_once __DIR__ . '/../../models/CommTemplate.php';

$templates = CommTemplate::all();
$templateTypes = [
    'Fee Reminder',
    'Attendance Alert',
    'Exam Notification',
    'Holiday Notice',
    'Meeting Invitation',
    'Emergency Notice',
    'General Information'
];

?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-paste text-primary me-2"></i>Templates Console</h3>
        <p class="text-muted small mb-0">Create reusable message templates for fee reminders, attendance, and emergency alerts.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#tempModal" onclick="clearTempForm()">
            <i class="fa-solid fa-plus me-2"></i>New Template
        </button>
    </div>
</div>

<!-- Grid Table of Templates -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="80">Index</th>
                        <th>Template Title</th>
                        <th>Category Type</th>
                        <th>Subject Line preset</th>
                        <th>Message Template Outline</th>
                        <th class="text-end" width="180">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($templates)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No templates configured yet.</td></tr>
                    <?php else: foreach ($templates as $i => $t): ?>
                        <tr>
                            <td class="text-muted fw-bold"><?php echo $i + 1; ?></td>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($t['name']); ?></td>
                            <td><span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($t['type']); ?></span></td>
                            <td class="small text-muted"><?php echo htmlspecialchars($t['subject'] ?: '—'); ?></td>
                            <td class="small text-muted"><?php echo htmlspecialchars(substr($t['message'], 0, 80)); ?>...</td>
                            <td class="text-end">
                                <a href="sms.php?recipient_type=Single+Student&template_id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-success me-1" title="Use in SMS"><i class="fa-solid fa-mobile-screen"></i></a>
                                <a href="email.php?recipient_type=Student&template_id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-info me-1" title="Use in Email"><i class="fa-solid fa-envelope"></i></a>
                                <button class="btn btn-sm btn-outline-primary me-1" onclick='editTemplate(<?php echo json_encode($t); ?>)'><i class="fa-solid fa-pen"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick='deleteTemplate(<?php echo $t['id']; ?>)'><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="tempModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary" id="modalTitle"><i class="fa-solid fa-paste me-2 text-primary"></i>Create Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="tempForm" method="POST" action="../../ajax/communication.php">
                    <input type="hidden" name="action" value="save_template">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="id" id="tempId">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Template Identifier Name</label>
                        <input type="text" class="form-control" name="name" id="tempName" required placeholder="e.g. Exam Schedule Alert">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Template Type / Category</label>
                        <select class="form-select" name="type" id="tempType">
                            <?php foreach ($templateTypes as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Email Subject Preset (Optional)</label>
                        <input type="text" class="form-control" name="subject" id="tempSubject" placeholder="e.g. Schedule Date Sheets Alert">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Template Message Body Text</label>
                        <textarea class="form-control" name="message" id="tempMessage" rows="6" required placeholder="Type template alert context here..."></textarea>
                    </div>

                    <div class="text-end pt-2 pb-3">
                        <button type="button" class="btn btn-outline-secondary px-3 me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Save Template</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function clearTempForm() {
    document.getElementById("tempId").value = "";
    document.getElementById("tempName").value = "";
    document.getElementById("tempSubject").value = "";
    document.getElementById("tempMessage").value = "";
    document.getElementById("modalTitle").innerHTML = "<i class=\"fa-solid fa-paste me-2 text-primary\"></i>Create Template";
}

function editTemplate(obj) {
    document.getElementById("tempId").value = obj.id;
    document.getElementById("tempName").value = obj.name;
    document.getElementById("tempType").value = obj.type;
    document.getElementById("tempSubject").value = obj.subject || "";
    document.getElementById("tempMessage").value = obj.message;
    
    document.getElementById("modalTitle").innerHTML = "<i class=\"fa-solid fa-pen me-2 text-primary\"></i>Update Template";
    
    let modal = new bootstrap.Modal(document.getElementById("tempModal"));
    modal.show();
}

function deleteTemplate(id) {
    if (confirm("Are you sure you want to delete this template?")) {
        let form = document.createElement("form");
        form.method = "POST";
        form.action = "../../ajax/communication.php";
        
        let act = document.createElement("input");
        act.type = "hidden";
        act.name = "action";
        act.value = "delete_template";
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
