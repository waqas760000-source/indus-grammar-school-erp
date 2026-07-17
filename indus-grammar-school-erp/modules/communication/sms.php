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

// Search filtered recipients if post/get request occurs
$resolvedRecipients = [];
$selectedType = sanitize($_GET['recipient_type'] ?? 'Single Student');
$filterClass  = isset($_GET['filter_class']) ? (int)$_GET['filter_class'] : 0;
$filterAtype  = sanitize($_GET['filter_atype'] ?? '');
$filterStatus = sanitize($_GET['filter_status'] ?? 'Active');

if (isset($_GET['search_recipients'])) {
    try {
        switch ($selectedType) {
            case 'Single Student':
            case 'Multiple Students':
            case 'Parents':
                $sql = "SELECT id, admission_no as reg_no, first_name, last_name, guardian_phone as phone, guardian_name as contact_person FROM students WHERE status = :status";
                $params = ['status' => $filterStatus];
                if ($filterClass > 0) {
                    $sql .= " AND class_id = :cid";
                    $params['cid'] = $filterClass;
                }
                if ($filterAtype !== '') {
                    $sql .= " AND academic_type = :atype";
                    $params['atype'] = $filterAtype;
                }
                $sql .= " ORDER BY first_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $resolvedRecipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'Entire Class':
                if ($filterClass > 0) {
                    $sql = "SELECT id, admission_no as reg_no, first_name, last_name, guardian_phone as phone, guardian_name as contact_person FROM students WHERE class_id = :cid AND status = :status ORDER BY first_name ASC";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(['cid' => $filterClass, 'status' => $filterStatus]);
                    $resolvedRecipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                break;

            case 'Entire School':
                $sql = "SELECT id, admission_no as reg_no, first_name, last_name, guardian_phone as phone, guardian_name as contact_person FROM students WHERE academic_type = 'School' AND status = :status ORDER BY first_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute(['status' => $filterStatus]);
                $resolvedRecipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'Entire Academy':
                $sql = "SELECT id, admission_no as reg_no, first_name, last_name, guardian_phone as phone, guardian_name as contact_person FROM students WHERE academic_type = 'Academy' AND status = :status ORDER BY first_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute(['status' => $filterStatus]);
                $resolvedRecipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'Teachers':
                $sql = "SELECT id, employee_no as reg_no, first_name, last_name, phone, designation as contact_person FROM staff WHERE status = :status AND designation LIKE '%Teacher%' ORDER BY first_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute(['status' => $filterStatus]);
                $resolvedRecipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'Staff':
                $sql = "SELECT id, employee_no as reg_no, first_name, last_name, phone, designation as contact_person FROM staff WHERE status = :status ORDER BY first_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute(['status' => $filterStatus]);
                $resolvedRecipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
        }
    } catch (Exception $e) {
        error_log("Recipients resolve error: " . $e->getMessage());
    }
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-mobile-screen text-primary me-2"></i>SMS Composer</h3>
        <p class="text-muted small mb-0">Dispatch bulk announcements or alerts. Connect to templates and schedule for later delivery.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="dashboard.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<div class="row g-4">
    <!-- Configuration panel -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-users me-2 text-primary"></i>1. Resolve Recipients</h5>
            </div>
            <div class="card-body p-4 pt-2">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="search_recipients" value="1">
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-muted">Recipient Category</label>
                        <select class="form-select" name="recipient_type" id="recipientType" onchange="toggleFilterFields()">
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
                        <select class="form-select" name="filter_class">
                            <option value="0">All Classes</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $filterClass === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 filter-opt" id="atypeFilterBox">
                        <label class="form-label small fw-semibold text-muted">Academic Type</label>
                        <select class="form-select" name="filter_atype">
                            <option value="">All Programs</option>
                            <option value="School" <?php echo $filterAtype === 'School' ? 'selected' : ''; ?>>School Only</option>
                            <option value="Academy" <?php echo $filterAtype === 'Academy' ? 'selected' : ''; ?>>Academy Only</option>
                        </select>
                    </div>

                    <div class="col-md-6 filter-opt" id="statusFilterBox">
                        <label class="form-label small fw-semibold text-muted">Status</label>
                        <select class="form-select" name="filter_status">
                            <option value="Active" <?php echo $filterStatus === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $filterStatus === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-12 text-end mt-3">
                        <a href="sms.php" class="btn btn-outline-secondary px-3 me-2"><i class="fa-solid fa-rotate-left me-2"></i>Reset</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-search me-2"></i>Resolve</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Custom number direct composer if Custom Mobile is picked -->
        <?php if ($selectedType === 'Custom Mobile Number'): ?>
            <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Target Mobile Number</label>
                        <input type="text" class="form-control" id="customPhone" placeholder="e.g. 03215551234">
                        <small class="text-muted text-xs">Direct input mobile number bypassing student/staff database lookup.</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Message Composer panel -->
    <div class="col-lg-7">
        <form id="smsForm" method="POST" action="../../ajax/communication.php">
            <input type="hidden" name="action" value="send_sms">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="recipient_type" value="<?php echo htmlspecialchars($selectedType); ?>">

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
                    <?php if ($selectedType !== 'Custom Mobile Number'): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label small fw-semibold text-muted mb-0">Recipients (<?php echo count($resolvedRecipients); ?> found)</label>
                                <?php if (!empty($resolvedRecipients)): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" checked>
                                        <label class="form-check-label small text-muted" for="selectAllCheckbox">Select All</label>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="border rounded p-3 bg-light" style="max-height: 140px; overflow-y: auto;">
                                <?php if (empty($resolvedRecipients)): ?>
                                    <p class="text-muted text-center small mb-0 py-2">Click "Resolve" to load recipient list.</p>
                                <?php else: foreach ($resolvedRecipients as $r): ?>
                                    <div class="form-check mb-1">
                                        <!-- Keep checked by default so they can be unselected -->
                                        <input class="form-check-input recipient-checkbox" type="checkbox" name="student_ids[]" value="<?php echo $r['id']; ?>" id="rec_<?php echo $r['id']; ?>" checked>
                                        <label class="form-check-label small text-dark" for="rec_<?php echo $r['id']; ?>">
                                            <?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name'] . ' (' . $r['reg_no'] . ') - Phone: ' . $r['phone']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

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
                        <button type="submit" class="btn btn-primary px-5"><i class="fa-solid fa-paper-plane me-2"></i>Send SMS (Simulated)</button>
                    </div>

                </div>
            </div>
        </form>
    </div>
</div>

<style>
.bg-primary-soft { background-color: rgba(30, 58, 138, 0.1); }
</style>

<?php $extraJS = '<script>
function toggleFilterFields() {
    const type = document.getElementById("recipientType").value;
    const classBox = document.getElementById("classFilterBox");
    const atypeBox = document.getElementById("atypeFilterBox");
    const statusBox = document.getElementById("statusFilterBox");

    classBox.style.display = "none";
    atypeBox.style.display = "none";
    statusBox.style.display = "none";

    if (["Single Student", "Multiple Students", "Entire Class", "Parents"].includes(type)) {
        classBox.style.display = "block";
    }
    if (["Single Student", "Multiple Students", "Parents"].includes(type)) {
        atypeBox.style.display = "block";
    }
    if (type !== "Custom Mobile Number") {
        statusBox.style.display = "block";
    }
}

function toggleSelectAll(master) {
    const checkboxes = document.querySelectorAll(".recipient-checkbox");
    checkboxes.forEach(c => c.checked = master.checked);
}

function countChars(textarea) {
    const len = textarea.value.length;
    const preview = document.getElementById("smsPreviewBox");
    const counter = document.getElementById("charCounter");
    const pageBadge = document.getElementById("smsPages");

    preview.textContent = len > 0 ? textarea.value : "[SMS message body text will render here...]";

    let pages = 1;
    if (len > 160) {
        pages = Math.ceil(len / 153); // SMS protocol splits message body at 153 chars for multi-page
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

function saveDraft() {
    const msg = document.getElementById("messageText").value;
    if (!msg) {
        alert("Please enter message body before saving draft.");
        return;
    }
    // Modify form action to mark as Draft
    let form = document.getElementById("smsForm");
    let schedTime = document.getElementById("scheduledTime");
    schedTime.value = ""; // Clear schedule

    // Inject draft input status
    let draftInput = document.createElement("input");
    draftInput.type = "hidden";
    draftInput.name = "status";
    draftInput.value = "Draft";
    form.appendChild(draftInput);

    // Submit form via standard ajax controller request
    form.submit();
}

document.addEventListener("DOMContentLoaded", function() {
    toggleFilterFields();
    toggleScheduleInput();
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
