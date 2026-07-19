<?php
/**
 * Indus Grammar School ERP - Advanced SMS Dispatcher
 * Version 4.0.0
 */

$pageTitle = 'SMS Dispatcher';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('communication_send');

$db = Database::getConnection();

// Load Classes
$classes = SchoolClass::all();

// Load templates for quick populate
$templates = $db->query("SELECT id, name, message FROM communication_templates ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$selectedType = sanitize($_GET['recipient_type'] ?? 'Single Student');
$filterClass  = isset($_GET['filter_class']) ? (int)$_GET['filter_class'] : 0;
$filterAtype  = sanitize($_GET['filter_atype'] ?? '');
$filterStatus = sanitize($_GET['filter_status'] ?? 'Active');

?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-mobile-screen text-primary me-2"></i>SMS Composer</h3>
        <p class="text-muted small mb-0">Dispatch bulk announcements or alerts. Connect to templates and schedule for later delivery.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="history.php?comm_type=sms" class="btn btn-outline-primary px-3 me-2"><i class="fa-solid fa-clock-rotate-left me-2"></i>SMS History</a>
        <a href="dashboard.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Alert Banner Container for User Notifications -->
<div id="smsAlertContainer" class="mb-3"></div>

<div class="row g-4">
    <!-- Configuration panel -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-users me-2 text-primary"></i>1. Resolve Recipients</h5>
            </div>
            <div class="card-body p-4 pt-2">
                <form id="resolveForm" onsubmit="event.preventDefault(); loadRecipients();" class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-muted">Recipient Category</label>
                        <select class="form-select" name="recipient_type" id="recipientType" onchange="onCategoryChange()">
                            <option value="Single Student" <?php echo $selectedType === 'Single Student' ? 'selected' : ''; ?>>Single Student</option>
                            <option value="Multiple Students" <?php echo $selectedType === 'Multiple Students' ? 'selected' : ''; ?>>Multiple Students</option>
                            <option value="Entire Class" <?php echo $selectedType === 'Entire Class' ? 'selected' : ''; ?>>Entire Class</option>
                            <option value="Entire School" <?php echo $selectedType === 'Entire School' ? 'selected' : ''; ?>>Entire School (Active)</option>
                            <option value="Entire Academy" <?php echo $selectedType === 'Entire Academy' ? 'selected' : ''; ?>>Entire Academy (Active)</option>
                            <option value="Teachers" <?php echo $selectedType === 'Teachers' ? 'selected' : ''; ?>>Teachers</option>
                            <option value="Staff" <?php echo $selectedType === 'Staff' ? 'selected' : ''; ?>>Staff Members</option>
                            <option value="Parents" <?php echo $selectedType === 'Parents' ? 'selected' : ''; ?>>All Parents</option>
                            <option value="Custom Mobile Number" <?php echo $selectedType === 'Custom Mobile Number' ? 'selected' : ''; ?>>Custom Mobile Number</option>
                        </select>
                    </div>

                    <!-- Filter Options -->
                    <div class="col-md-6 filter-opt" id="classFilterBox">
                        <label class="form-label small fw-semibold text-muted">Class Section</label>
                        <select class="form-select" name="filter_class" id="filterClass" onchange="loadRecipients()">
                            <option value="0">All Classes</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $filterClass === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 filter-opt" id="atypeFilterBox">
                        <label class="form-label small fw-semibold text-muted">Academic Type</label>
                        <select class="form-select" name="filter_atype" id="filterAtype" onchange="loadRecipients()">
                            <option value="">All Programs</option>
                            <option value="School" <?php echo $filterAtype === 'School' ? 'selected' : ''; ?>>School Only</option>
                            <option value="Academy" <?php echo $filterAtype === 'Academy' ? 'selected' : ''; ?>>Academy Only</option>
                        </select>
                    </div>

                    <div class="col-md-6 filter-opt" id="statusFilterBox">
                        <label class="form-label small fw-semibold text-muted">Status</label>
                        <select class="form-select" name="filter_status" id="filterStatus" onchange="loadRecipients()">
                            <option value="Active" <?php echo $filterStatus === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $filterStatus === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-12 text-end mt-3">
                        <button type="button" class="btn btn-outline-secondary px-3 me-2" onclick="resetFilters()"><i class="fa-solid fa-rotate-left me-2"></i>Reset</button>
                        <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-search me-2"></i>Resolve</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Custom number direct composer if Custom Mobile is picked -->
        <div class="card border-0 shadow-sm mb-4" id="customPhoneCard" style="border-radius:12px; display:none;">
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Target Mobile Number</label>
                    <input type="text" class="form-control" name="custom_mobile" id="customPhone" placeholder="e.g. 03215551234" oninput="validateCustomPhone()">
                    <small class="text-muted text-xs">Direct input mobile number bypassing student/staff database lookup.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Composer panel -->
    <div class="col-lg-7">
        <form id="smsForm" method="POST" action="../../ajax/communication.php">
            <input type="hidden" name="action" value="send_sms">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="recipient_type" id="hiddenRecipientType" value="<?php echo htmlspecialchars($selectedType); ?>">
            <input type="hidden" name="filter_class" id="hiddenFilterClass" value="<?php echo $filterClass; ?>">
            <input type="hidden" name="filter_atype" id="hiddenFilterAtype" value="<?php echo htmlspecialchars($filterAtype); ?>">
            <input type="hidden" name="filter_status" id="hiddenFilterStatus" value="<?php echo htmlspecialchars($filterStatus); ?>">

            <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-pen-nib me-2 text-primary"></i>2. Compose Message</h5>
                    <div>
                        <select class="form-select form-select-sm" id="templateSelect" onchange="applyTemplate()">
                            <option value="">-- Apply Template --</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?php echo $t['id']; ?>" data-message="<?php echo htmlspecialchars($t['message']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="card-body p-4 pt-2">
                    
                    <!-- Resolved recipients display checklist -->
                    <div class="mb-3" id="recipientsListSection">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-primary-soft text-primary fw-bold px-3 py-2" id="recipientBadge">Recipients Found: 0</span>
                            <div class="form-check" id="selectAllBox" style="display:none;">
                                <input class="form-check-input" type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" checked>
                                <label class="form-check-label small text-muted" for="selectAllCheckbox">Select All</label>
                            </div>
                        </div>
                        
                        <div class="border rounded p-3 bg-light" style="max-height: 160px; overflow-y: auto;" id="recipientsContainer">
                            <p class="text-muted text-center small mb-0 py-2">Loading recipient list...</p>
                        </div>
                    </div>

                    <!-- No Valid Mobile Numbers Warning Alert -->
                    <div class="alert alert-warning border-0 shadow-sm mb-3 text-xs" id="noRecipientsAlert" style="display:none;">
                        <i class="fa-solid fa-circle-exclamation me-2 fs-6"></i>
                        <strong>Notice:</strong> No valid mobile numbers are available for the selected recipients. Please update the student's or guardian's contact information.
                    </div>

                    <!-- Message Body -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Message Text</label>
                        <textarea class="form-control" name="message" id="messageText" rows="5" maxlength="320" required placeholder="Type SMS message body here..." onkeyup="countChars(this)"></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span class="small text-muted" id="charCounter">0 / 160 characters (1 SMS Page)</span>
                            <span class="badge bg-secondary-soft text-secondary rounded-pill" id="smsPages">Page 1</span>
                        </div>
                    </div>

                    <!-- SMS Live Preview Box -->
                    <div class="mb-3 border rounded p-3 bg-light" style="border-left: 4px solid var(--royal-blue-light) !important;">
                        <h6 class="fw-bold text-xs text-muted mb-2 text-uppercase"><i class="fa-solid fa-mobile-button me-2"></i>Handset SMS Preview</h6>
                        <div class="bg-white border rounded p-2 text-dark font-monospace text-xs" style="min-height: 50px;" id="smsPreviewBox">
                            [SMS message body text will render here...]
                        </div>
                    </div>

                    <!-- Scheduling -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Dispatch Time</label>
                            <select class="form-select" id="schedType" onchange="toggleScheduleInput()">
                                <option value="now">Send Now</option>
                                <option value="later">Schedule Later</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="scheduleInputBox" style="display:none;">
                            <label class="form-label small fw-semibold text-muted">Scheduled Date & Time</label>
                            <input type="datetime-local" class="form-control" name="scheduled_time" id="scheduledTime">
                        </div>
                    </div>

                    <!-- Submit Actions -->
                    <div class="text-end">
                        <button type="button" class="btn btn-outline-success px-4 me-2" onclick="saveDraft()"><i class="fa-solid fa-file-invoice me-2"></i>Save Draft</button>
                        <button type="submit" class="btn btn-primary px-5" id="sendSmsBtn"><i class="fa-solid fa-paper-plane me-2"></i>Send SMS</button>
                    </div>

                </div>
            </div>
        </form>
    </div>
</div>

<style>
.bg-primary-soft { background-color: rgba(30, 58, 138, 0.1); color: #1e3a8a; }
.bg-success-soft { background-color: rgba(16, 185, 129, 0.1); color: #047857; }
.bg-warning-soft { background-color: rgba(245, 158, 11, 0.1); color: #b45309; }
</style>

<?php $extraJS = '<script>
function onCategoryChange() {
    toggleFilterFields();
    loadRecipients();
}

function toggleFilterFields() {
    const type = document.getElementById("recipientType").value;
    const classBox = document.getElementById("classFilterBox");
    const atypeBox = document.getElementById("atypeFilterBox");
    const statusBox = document.getElementById("statusFilterBox");
    const customCard = document.getElementById("customPhoneCard");
    const recSection = document.getElementById("recipientsListSection");

    classBox.style.display = "none";
    atypeBox.style.display = "none";
    statusBox.style.display = "none";
    customCard.style.display = "none";
    recSection.style.display = "block";

    if (["Single Student", "Multiple Students", "Entire Class", "Parents"].includes(type)) {
        classBox.style.display = "block";
    }
    if (["Single Student", "Multiple Students", "Parents"].includes(type)) {
        atypeBox.style.display = "block";
    }
    if (type !== "Custom Mobile Number") {
        statusBox.style.display = "block";
    } else {
        customCard.style.display = "block";
        recSection.style.display = "none";
    }

    // Update hidden input fields
    document.getElementById("hiddenRecipientType").value = type;
}

function loadRecipients() {
    const type = document.getElementById("recipientType").value;
    const filterClass = document.getElementById("filterClass").value;
    const filterAtype = document.getElementById("filterAtype").value;
    const filterStatus = document.getElementById("filterStatus").value;

    document.getElementById("hiddenFilterClass").value = filterClass;
    document.getElementById("hiddenFilterAtype").value = filterAtype;
    document.getElementById("hiddenFilterStatus").value = filterStatus;

    if (type === "Custom Mobile Number") {
        validateCustomPhone();
        return;
    }

    const container = document.getElementById("recipientsContainer");
    const badge = document.getElementById("recipientBadge");
    const selectAllBox = document.getElementById("selectAllBox");
    const alertBox = document.getElementById("noRecipientsAlert");
    const sendBtn = document.getElementById("sendSmsBtn");

    container.innerHTML = \'<p class="text-muted text-center small mb-0 py-2"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading recipient numbers...</p>\';

    const url = `../../ajax/communication.php?action=resolve_recipients&recipient_type=${encodeURIComponent(type)}&filter_class=${filterClass}&filter_atype=${encodeURIComponent(filterAtype)}&filter_status=${encodeURIComponent(filterStatus)}`;

    fetch(url)
        .then(r => r.json())
        .then(res => {
            if (res.status === "success") {
                const list = res.data || [];
                const count = list.length;
                badge.textContent = `Recipients Found: ${count}`;

                if (count > 0) {
                    alertBox.style.display = "none";
                    sendBtn.disabled = false;
                    selectAllBox.style.display = "block";

                    let html = "";
                    list.forEach(r => {
                        html += `
                            <div class="form-check mb-1">
                                <input class="form-check-input recipient-checkbox" type="checkbox" name="student_ids[]" value="${r.id}" id="rec_${r.id}" checked onchange="updateSelectedCount()">
                                <label class="form-check-label small text-dark" for="rec_${r.id}">
                                    <strong>${r.first_name} ${r.last_name}</strong> (${r.reg_no}) - Contact: ${r.contact_person} - <span class="badge bg-secondary-soft text-dark">${r.phone}</span>
                                </label>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                } else {
                    selectAllBox.style.display = "none";
                    container.innerHTML = \'<p class="text-muted text-center small mb-0 py-2">No matching recipients found in database.</p>\';
                    alertBox.style.display = "block";
                    sendBtn.disabled = true;
                }
            } else {
                container.innerHTML = `<p class="text-danger text-center small mb-0 py-2">${res.message || "Failed to load recipients."}</p>`;
                sendBtn.disabled = true;
            }
        })
        .catch(err => {
            container.innerHTML = \'<p class="text-danger text-center small mb-0 py-2">Error connecting to server to resolve recipients.</p>\';
            sendBtn.disabled = true;
        });
}

function updateSelectedCount() {
    const checked = document.querySelectorAll(".recipient-checkbox:checked");
    const badge = document.getElementById("recipientBadge");
    const sendBtn = document.getElementById("sendSmsBtn");
    const alertBox = document.getElementById("noRecipientsAlert");

    const count = checked.length;
    badge.textContent = `Recipients Selected: ${count}`;

    if (count > 0) {
        sendBtn.disabled = false;
        alertBox.style.display = "none";
    } else {
        sendBtn.disabled = true;
        alertBox.style.display = "block";
    }
}

function validateCustomPhone() {
    const phone = document.getElementById("customPhone").value.trim();
    const sendBtn = document.getElementById("sendSmsBtn");
    const alertBox = document.getElementById("noRecipientsAlert");

    if (phone.length >= 7) {
        sendBtn.disabled = false;
        alertBox.style.display = "none";
    } else {
        sendBtn.disabled = true;
        alertBox.style.display = "block";
    }
}

function toggleSelectAll(master) {
    const checkboxes = document.querySelectorAll(".recipient-checkbox");
    checkboxes.forEach(c => c.checked = master.checked);
    updateSelectedCount();
}

function countChars(textarea) {
    const len = textarea.value.length;
    const preview = document.getElementById("smsPreviewBox");
    const counter = document.getElementById("charCounter");
    const pageBadge = document.getElementById("smsPages");

    preview.textContent = len > 0 ? textarea.value : "[SMS message body text will render here...]";

    let pages = 1;
    if (len > 160) {
        pages = Math.ceil(len / 153);
    }
    
    counter.textContent = `${len} / ${pages * 160} characters (${pages} SMS Page(s))`;
    pageBadge.textContent = `Page ${pages}`;
}

function applyTemplate() {
    const select = document.getElementById("templateSelect");
    const msgBox = document.getElementById("messageText");
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value !== "") {
        msgBox.value = selectedOption.getAttribute("data-message");
        countChars(msgBox);
    }
}

function toggleScheduleInput() {
    const sched = document.getElementById("schedType").value;
    const input = document.getElementById("scheduleInputBox");
    input.style.display = (sched === "later") ? "block" : "none";
}

function resetFilters() {
    document.getElementById("recipientType").value = "Single Student";
    document.getElementById("filterClass").value = "0";
    document.getElementById("filterAtype").value = "";
    document.getElementById("filterStatus").value = "Active";
    onCategoryChange();
}

function showAlert(type, message) {
    const container = document.getElementById("smsAlertContainer");
    container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid ${type === "success" ? "fa-circle-check" : "fa-triangle-exclamation"} me-2 fs-5"></i>
            <strong>${type === "success" ? "Success!" : "Notice:"}</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    window.scrollTo({ top: 0, behavior: "smooth" });
}

document.getElementById("smsForm").addEventListener("submit", function(e) {
    e.preventDefault();
    const sendBtn = document.getElementById("sendSmsBtn");
    const originalText = sendBtn.innerHTML;
    sendBtn.disabled = true;
    sendBtn.innerHTML = \'<i class="fa-solid fa-spinner fa-spin me-2"></i>Processing...\';

    // Inject custom mobile into form data if selected
    let formData = new FormData(this);
    if (document.getElementById("recipientType").value === "Custom Mobile Number") {
        formData.append("custom_mobile", document.getElementById("customPhone").value.trim());
    }

    fetch(this.action, {
        method: "POST",
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = originalText;
        if (data.status === "success") {
            showAlert("success", data.message);
            document.getElementById("messageText").value = "";
            countChars(document.getElementById("messageText"));
        } else {
            showAlert("danger", data.message || "Failed to process SMS request.");
        }
    })
    .catch(err => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = originalText;
        showAlert("danger", "An unexpected network error occurred while sending SMS.");
    });
});

function saveDraft() {
    const msg = document.getElementById("messageText").value.trim();
    if (!msg) {
        showAlert("warning", "Please enter message body before saving draft.");
        return;
    }
    
    let form = document.getElementById("smsForm");
    let formData = new FormData(form);
    formData.set("status", "Draft");

    if (document.getElementById("recipientType").value === "Custom Mobile Number") {
        formData.append("custom_mobile", document.getElementById("customPhone").value.trim());
    }

    fetch(form.action, {
        method: "POST",
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === "success") {
            showAlert("success", "SMS Draft saved successfully.");
        } else {
            showAlert("danger", data.message || "Failed to save draft.");
        }
    })
    .catch(err => {
        showAlert("danger", "Network error while saving draft.");
    });
}

document.addEventListener("DOMContentLoaded", function() {
    toggleFilterFields();
    toggleScheduleInput();
    loadRecipients();
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
