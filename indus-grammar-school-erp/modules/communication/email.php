<?php
/**
 * Indus Grammar School ERP - Professional Email Composer
 * Version 4.0.0
 */

$pageTitle = 'Email Dispatcher';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('communication_send');

$db = Database::getConnection();

// Load Classes
$classes = SchoolClass::all();

// Load staff list for selection
$staff = $db->query("SELECT id, first_name, last_name, employee_no FROM staff WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Load students list for selection
$students = $db->query("SELECT id, first_name, last_name, admission_no FROM students WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Load templates for email subject/body populate
$templates = $db->query("SELECT id, name, subject, message FROM communication_templates ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-envelope text-primary me-2"></i>Email Broadcast</h3>
        <p class="text-muted small mb-0">Compose professional announcements, reminders, and notifications with file attachments.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="dashboard.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<div class="row g-4">
    <!-- Email Composer panel -->
    <div class="col-lg-8">
        <form id="emailForm" method="POST" action="../../ajax/communication.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="send_email">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

            <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-pen-fancy me-2 text-primary"></i>Email Composer</h5>
                    <div>
                        <select class="form-select form-select-sm" id="templateSelect" onchange="applyTemplate()">
                            <option value="">-- Load Template --</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?php echo $t['id']; ?>" data-subject="<?php echo htmlspecialchars($t['subject']); ?>" data-message="<?php echo htmlspecialchars($t['message']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="card-body p-4 pt-2">
                    
                    <!-- Recipient Selection -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Send to Category</label>
                            <select class="form-select" name="recipient_type" id="recipientType" onchange="toggleRecipientInput()">
                                <option value="Student">Single Student (Guardian)</option>
                                <option value="Parent">Parent</option>
                                <option value="Teacher">Single Teacher</option>
                                <option value="Staff">Single Staff</option>
                                <option value="Entire Class">Entire Class Section</option>
                                <option value="Entire School">Entire School</option>
                                <option value="Custom Email">Custom Email Address</option>
                            </select>
                        </div>

                        <!-- Dynamic recipient search results select -->
                        <div class="col-md-6 rec-input" id="studentInput">
                            <label class="form-label small fw-semibold text-muted">Select Student</label>
                            <select class="form-select" name="student_id">
                                <option value="0">-- Choose Student --</option>
                                <?php foreach ($students as $st): ?>
                                    <option value="<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['admission_no'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 rec-input" id="staffInput" style="display:none;">
                            <label class="form-label small fw-semibold text-muted">Select Employee</label>
                            <select class="form-select" name="staff_id">
                                <option value="0">-- Choose Employee --</option>
                                <?php foreach ($staff as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['employee_no'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 rec-input" id="classInput" style="display:none;">
                            <label class="form-label small fw-semibold text-muted">Select Class Section</label>
                            <select class="form-select" name="class_id">
                                <option value="0">-- Choose Class --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 rec-input" id="customInput" style="display:none;">
                            <label class="form-label small fw-semibold text-muted">Recipient Email Address</label>
                            <input type="email" class="form-control" name="custom_email" placeholder="e.g. parent@example.com">
                        </div>
                    </div>

                    <!-- Subject -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Subject / Topic</label>
                        <input type="text" class="form-control" name="subject" id="emailSubject" required placeholder="Type subject here...">
                    </div>

                    <!-- Email Body -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Email Message Body</label>
                        <textarea class="form-control" name="body" id="emailBody" rows="8" required placeholder="Write email details here..."></textarea>
                    </div>

                    <!-- Attachments -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Attachments (PDF/Image - Optional)</label>
                        <input type="file" class="form-control" name="attachment">
                        <small class="text-muted text-xs">Maximum upload size: 5MB.</small>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="Normal">Normal Priority</option>
                                <option value="High">High Priority</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Dispatch Mode</label>
                            <select class="form-select" id="schedType" onchange="toggleScheduleInput()">
                                <option value="now">Send Now</option>
                                <option value="later">Schedule Later</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="scheduleInputBox" style="display:none;">
                            <label class="form-label small fw-semibold text-muted">Scheduled Date & Time</label>
                            <input type="datetime-local" class="form-control" name="scheduled_time" id="scheduledTime">
                        </div>
                    </div>

                    <!-- Submit Actions -->
                    <div class="text-end">
                        <button type="button" class="btn btn-outline-success px-4 me-2" onclick="saveDraft()"><i class="fa-solid fa-file-invoice me-2"></i>Save Draft</button>
                        <button type="submit" class="btn btn-primary px-5"><i class="fa-solid fa-paper-plane me-2"></i>Send Email (Simulated)</button>
                    </div>

                </div>
            </div>
        </form>
    </div>

    <!-- Info Board panel -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius:12px; background: linear-gradient(135deg, #fff, #eff6ff);">
            <div class="card-body p-4">
                <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-circle-info me-2"></i>Email Guidelines</h5>
                <p class="small text-muted mb-2">Professional school broadcasts should contain header styling and clear notices.</p>
                <ul class="small text-muted ps-3 mb-0">
                    <li class="mb-1">Select a template to auto-populate template placeholders.</li>
                    <li class="mb-1">High priority emails are flagged inside the recipient inbox header.</li>
                    <li>Files up to 5MB are copied to school storage and attached securely.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function toggleRecipientInput() {
    const type = document.getElementById("recipientType").value;
    const student = document.getElementById("studentInput");
    const staff = document.getElementById("staffInput");
    const classInput = document.getElementById("classInput");
    const custom = document.getElementById("customInput");

    student.style.display = "none";
    staff.style.display = "none";
    classInput.style.display = "none";
    custom.style.display = "none";

    if (["Student", "Parent"].includes(type)) {
        student.style.display = "block";
    } else if (["Teacher", "Staff"].includes(type)) {
        staff.style.display = "block";
    } else if (type === "Entire Class") {
        classInput.style.display = "block";
    } else if (type === "Custom Email") {
        custom.style.display = "block";
    }
}

function applyTemplate() {
    const select = document.getElementById("templateSelect");
    const subBox = document.getElementById("emailSubject");
    const msgBox = document.getElementById("emailBody");
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value !== "") {
        subBox.value = selectedOption.getAttribute("data-subject") || selectedOption.text;
        msgBox.value = selectedOption.getAttribute("data-message");
    }
}

function toggleScheduleInput() {
    const sched = document.getElementById("schedType").value;
    const input = document.getElementById("scheduleInputBox");
    input.style.display = (sched === "later") ? "block" : "none";
}

function saveDraft() {
    const sub = document.getElementById("emailSubject").value;
    if (!sub) {
        alert("Please enter a subject line before saving draft.");
        return;
    }
    let form = document.getElementById("emailForm");
    let schedTime = document.getElementById("scheduledTime");
    schedTime.value = ""; // Clear schedule

    // Inject draft input status
    let draftInput = document.createElement("input");
    draftInput.type = "hidden";
    draftInput.name = "status";
    draftInput.value = "Draft";
    form.appendChild(draftInput);

    form.submit();
}

document.addEventListener("DOMContentLoaded", function() {
    toggleRecipientInput();
    toggleScheduleInput();
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
