<?php
/**
 * Indus Grammar School ERP - WhatsApp Templates Manager
 * Version 4.0.0
 */

$pageTitle = 'WhatsApp Templates';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('communication_send');

// Load model
require_once __DIR__ . '/../../models/WhatsAppTemplate.php';
$templates = WhatsAppTemplate::all();
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-file-code text-success me-2"></i>WhatsApp Templates</h3>
        <p class="text-muted small mb-0">Create, customize, and manage reusable WhatsApp text templates with dynamic variables.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button type="button" class="btn btn-success px-4 me-2" onclick="openTemplateModal()"><i class="fa-solid fa-plus me-2"></i>New Template</button>
        <a href="whatsapp.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>WhatsApp Dispatcher</a>
    </div>
</div>

<div id="tplAlertContainer" class="mb-3"></div>

<div class="row g-4">
    <!-- Templates Table Column -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table custom-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="60">#</th>
                                <th>Template Name</th>
                                <th>Category</th>
                                <th>Message Body Preview</th>
                                <th class="text-end" width="130">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($templates)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No WhatsApp templates configured yet.</td></tr>
                            <?php else: foreach ($templates as $idx => $t): ?>
                                <tr>
                                    <td class="fw-bold text-muted"><?php echo $idx + 1; ?></td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($t['name']); ?></td>
                                    <td><span class="badge bg-success-soft text-success rounded-pill px-3"><?php echo htmlspecialchars($t['category']); ?></span></td>
                                    <td class="small text-muted text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($t['body']); ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-primary btn-xs me-1" onclick='editTemplate(<?php echo json_encode($t); ?>)'><i class="fa-solid fa-pen"></i></button>
                                        <button type="button" class="btn btn-outline-danger btn-xs" onclick="deleteTemplate(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?>')"><i class="fa-solid fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Dynamic Variable Cheatsheet Column -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-code me-2 text-primary"></i>Variable Tag Keys</h5>
            </div>
            <div class="card-body p-4 pt-2">
                <p class="text-muted text-xs mb-3">Copy and paste these variable placeholders into template bodies to auto-fill student details on dispatch:</p>
                <div class="table-responsive">
                    <table class="table table-sm table-borderless text-xs mb-0">
                        <tr>
                            <td><code class="user-select-all text-primary fw-bold">{{StudentName}}</code></td>
                            <td class="text-muted">Student's Full Name</td>
                        </tr>
                        <tr>
                            <td><code class="user-select-all text-primary fw-bold">{{FatherName}}</code></td>
                            <td class="text-muted">Father's Name</td>
                        </tr>
                        <tr>
                            <td><code class="user-select-all text-primary fw-bold">{{GuardianName}}</code></td>
                            <td class="text-muted">Guardian's Name</td>
                        </tr>
                        <tr>
                            <td><code class="user-select-all text-primary fw-bold">{{Class}}</code></td>
                            <td class="text-muted">Class Name</td>
                        </tr>
                        <tr>
                            <td><code class="user-select-all text-primary fw-bold">{{Section}}</code></td>
                            <td class="text-muted">Section Letter</td>
                        </tr>
                        <tr>
                            <td><code class="user-select-all text-primary fw-bold">{{StudentID}}</code></td>
                            <td class="text-muted">Admission Roll Number</td>
                        </tr>
                        <tr>
                            <td><code class="user-select-all text-primary fw-bold">{{OutstandingFee}}</code></td>
                            <td class="text-muted">Calculated Dues (Rs.)</td>
                        </tr>
                        <tr>
                            <td><code class="user-select-all text-primary fw-bold">{{DueDate}}</code></td>
                            <td class="text-muted">Earliest Dues Due Date</td>
                        </tr>
                        <tr>
                            <td><code class="user-select-all text-primary fw-bold">{{Attendance}}</code></td>
                            <td class="text-muted">Today's Attendance Status</td>
                        </tr>
                        <tr>
                            <td><code class="user-select-all text-primary fw-bold">{{SchoolName}}</code></td>
                            <td class="text-muted">Institute Name</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template Add/Edit Modal -->
<div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="templateForm">
                <input type="hidden" name="action" value="save_template">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="id" id="tplId" value="0">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="tplModalTitle"><i class="fa-solid fa-plus me-2"></i>Create WhatsApp Template</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Template Name</label>
                        <input type="text" class="form-control" name="name" id="tplName" required placeholder="e.g. Monthly Fee Reminder">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Category</label>
                        <select class="form-select" name="category" id="tplCategory">
                            <option value="Fee Reminder">Fee Reminder</option>
                            <option value="Attendance Alert">Attendance Alert</option>
                            <option value="Exam Reminder">Exam Reminder</option>
                            <option value="Holiday Notice">Holiday Notice</option>
                            <option value="Admission Confirmation">Admission Confirmation</option>
                            <option value="General Announcement">General Announcement</option>
                            <option value="Birthday Wish">Birthday Wish</option>
                            <option value="Custom Template">Custom Template</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Template Body Text</label>
                        <textarea class="form-control" name="body" id="tplBody" rows="5" required placeholder="Enter template text body with {{StudentName}}, {{OutstandingFee}}, etc..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4"><i class="fa-solid fa-save me-2"></i>Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.bg-success-soft { background-color: rgba(16, 185, 129, 0.1); color: #047857; }
</style>

<?php $extraJS = '<script>
function openTemplateModal() {
    document.getElementById("tplId").value = "0";
    document.getElementById("tplName").value = "";
    document.getElementById("tplCategory").value = "Fee Reminder";
    document.getElementById("tplBody").value = "";
    document.getElementById("tplModalTitle").innerHTML = \'<i class="fa-solid fa-plus me-2"></i>Create WhatsApp Template\';
    let modal = new bootstrap.Modal(document.getElementById("templateModal"));
    modal.show();
}

function editTemplate(t) {
    document.getElementById("tplId").value = t.id;
    document.getElementById("tplName").value = t.name;
    document.getElementById("tplCategory").value = t.category;
    document.getElementById("tplBody").value = t.body;
    document.getElementById("tplModalTitle").innerHTML = \'<i class="fa-solid fa-pen me-2"></i>Edit WhatsApp Template\';
    let modal = new bootstrap.Modal(document.getElementById("templateModal"));
    modal.show();
}

function deleteTemplate(id, name) {
    if (!confirm(`Are you sure you want to delete template "${name}"?`)) return;

    let formData = new FormData();
    formData.append("action", "delete_template");
    formData.append("id", id);

    fetch("../../ajax/whatsapp.php", { method: "POST", body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.status === "success") {
                location.reload();
            } else {
                alert(res.message || "Failed to delete template.");
            }
        });
}

document.getElementById("templateForm").addEventListener("submit", function(e) {
    e.preventDefault();
    let formData = new FormData(this);

    fetch("../../ajax/whatsapp.php", { method: "POST", body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.status === "success") {
                location.reload();
            } else {
                alert(res.message || "Failed to save template.");
            }
        })
        .catch(err => {
            alert("Network error saving template.");
        });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
