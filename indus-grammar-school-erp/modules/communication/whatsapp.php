<?php
/**
 * Indus Grammar School ERP - WhatsApp Dispatcher Module
 * Version 4.0.0
 */

$pageTitle = 'WhatsApp Dispatcher';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('communication_send');

// Load models
require_once __DIR__ . '/../../models/WhatsAppTemplate.php';
$templates = WhatsAppTemplate::all();
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-brands fa-whatsapp text-success me-2"></i>WhatsApp Dispatcher</h3>
        <p class="text-muted small mb-0">Send instant direct WhatsApp alerts, fee reminders, and attendance notifications via free Click-to-Chat.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="whatsapp_bulk.php" class="btn btn-outline-success px-3 me-2"><i class="fa-solid fa-layer-group me-2"></i>Bulk WhatsApp</a>
        <a href="whatsapp_history.php" class="btn btn-outline-primary px-3 me-2"><i class="fa-solid fa-clock-rotate-left me-2"></i>WhatsApp History</a>
        <a href="dashboard.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Alert Banner Container -->
<div id="waAlertContainer" class="mb-3"></div>

<div class="row g-4">
    <!-- Student Search & Profile Card Column -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-magnifying-glass me-2 text-primary"></i>Search Student</h5>
                <p class="text-muted text-xs mb-0">Find student by Student ID or CNIC / B-Form number.</p>
            </div>
            <div class="card-body p-4 pt-2">
                <form id="studentSearchForm" onsubmit="event.preventDefault(); searchStudent();">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="searchQuery" placeholder="Enter Student ID, Admission No, or CNIC..." required autocomplete="off">
                        <button type="submit" class="btn btn-primary px-4" id="searchBtn"><i class="fa-solid fa-search me-1"></i>Search</button>
                    </div>
                </form>

                <!-- Student Information Display Card (Hidden until student is loaded) -->
                <div id="studentProfileBox" class="border rounded p-3 bg-light mt-3" style="display:none; border-left: 4px solid #10b981 !important;">
                    <div class="d-flex align-items-center mb-3">
                        <img id="stdPhoto" src="../../assets/img/avatar.png" class="rounded-circle border me-3" style="width: 60px; height: 60px; object-fit: cover;" alt="Student Photo">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark" id="stdName">Student Name</h6>
                            <span class="badge bg-success-soft text-success rounded-pill fw-semibold text-xs mb-1" id="stdRegNo">ID: N/A</span>
                            <div class="text-muted text-xs"><i class="fa-solid fa-school me-1"></i><span id="stdClassSection">Class - Section</span> (<span id="stdAtype">School</span>)</div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-borderless small mb-0 text-dark">
                            <tr>
                                <th class="text-muted font-normal" width="40%">Father Name:</th>
                                <td class="fw-semibold" id="stdFather">N/A</td>
                            </tr>
                            <tr>
                                <th class="text-muted font-normal">Guardian Name:</th>
                                <td class="fw-semibold" id="stdGuardian">N/A</td>
                            </tr>
                            <tr>
                                <th class="text-muted font-normal">WhatsApp Mobile:</th>
                                <td class="fw-bold text-success" id="stdMobile">N/A</td>
                            </tr>
                            <tr>
                                <th class="text-muted font-normal">Outstanding Dues:</th>
                                <td class="fw-bold text-danger" id="stdDues">Rs. 0</td>
                            </tr>
                            <tr>
                                <th class="text-muted font-normal">Due Date:</th>
                                <td class="fw-semibold text-dark" id="stdDueDate">N/A</td>
                            </tr>
                            <tr>
                                <th class="text-muted font-normal">Today's Attendance:</th>
                                <td class="fw-semibold" id="stdAttendance">N/A</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- No Student Selected State -->
                <div id="noStudentBox" class="text-center py-4 text-muted border rounded bg-white">
                    <i class="fa-solid fa-id-card fs-1 text-secondary mb-2 opacity-50"></i>
                    <p class="small mb-0">Search a student above to automatically load profile data and calculate dues.</p>
                </div>

            </div>
        </div>
    </div>

    <!-- Message Editor Column -->
    <div class="col-lg-7">
        <form id="whatsappForm">
            <input type="hidden" name="action" value="dispatch_message">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="recipient_type" value="Single Student">
            <input type="hidden" name="recipient_name" id="hiddenRecipientName" value="">
            <input type="hidden" name="phone" id="hiddenPhone" value="">

            <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-pen-to-square me-2 text-success"></i>Message Editor</h5>
                    <div>
                        <select class="form-select form-select-sm" id="templateSelect" onchange="applyTemplate()">
                            <option value="">-- Apply Template --</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?php echo htmlspecialchars($t['name']); ?>" data-body="<?php echo htmlspecialchars($t['body']); ?>"><?php echo htmlspecialchars($t['name'] . ' (' . $t['category'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="card-body p-4 pt-2">
                    
                    <!-- Dynamic Variable Tags Toolbar -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Insert Dynamic Variables</label>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm text-xs py-1 px-2" onclick="insertVariable('{{StudentName}}')">+ StudentName</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-xs py-1 px-2" onclick="insertVariable('{{FatherName}}')">+ FatherName</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-xs py-1 px-2" onclick="insertVariable('{{Class}}')">+ Class</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-xs py-1 px-2" onclick="insertVariable('{{Section}}')">+ Section</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-xs py-1 px-2" onclick="insertVariable('{{StudentID}}')">+ StudentID</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-xs py-1 px-2" onclick="insertVariable('{{OutstandingFee}}')">+ OutstandingFee</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-xs py-1 px-2" onclick="insertVariable('{{DueDate}}')">+ DueDate</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-xs py-1 px-2" onclick="insertVariable('{{Attendance}}')">+ Attendance</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-xs py-1 px-2" onclick="insertVariable('{{SchoolName}}')">+ SchoolName</button>
                        </div>
                    </div>

                    <!-- Message Textarea -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Message Body</label>
                        <textarea class="form-control" name="message" id="messageText" rows="6" required placeholder="Type WhatsApp message body here or select a template..." onkeyup="updateLivePreview()"></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span class="small text-muted" id="charCounter">0 characters</span>
                            <span class="badge bg-success-soft text-success rounded-pill" id="varCountBadge">0 Variables Inserted</span>
                        </div>
                    </div>

                    <!-- Live WhatsApp Handset Preview Box -->
                    <div class="mb-4 border rounded p-3" style="background-color: #efeae2; border-left: 4px solid #25d366 !important;">
                        <h6 class="fw-bold text-xs text-muted mb-2 text-uppercase"><i class="fa-brands fa-whatsapp me-2 text-success fs-6"></i>WhatsApp Handset Live Preview</h6>
                        <div class="bg-white border rounded p-3 text-dark text-xs shadow-sm font-sans" style="min-height: 60px; border-radius: 8px; border-top-left-radius: 0;" id="waPreviewBox">
                            [Rendered message with replaced student variables will display here...]
                        </div>
                    </div>

                    <!-- Submit Actions -->
                    <div class="text-end">
                        <button type="submit" class="btn btn-success px-5 py-2 fw-semibold" id="sendWaBtn" disabled><i class="fa-brands fa-whatsapp me-2 fs-5 align-middle"></i>Send WhatsApp</button>
                    </div>

                </div>
            </div>
        </form>
    </div>
</div>

<style>
.bg-success-soft { background-color: rgba(16, 185, 129, 0.1); color: #047857; }
.bg-primary-soft { background-color: rgba(30, 58, 138, 0.1); color: #1e3a8a; }
</style>

<?php $extraJS = '<script>
let currentStudent = null;

function searchStudent() {
    const query = document.getElementById("searchQuery").value.trim();
    if (!query) return;

    const btn = document.getElementById("searchBtn");
    btn.disabled = true;
    btn.innerHTML = \'<i class="fa-solid fa-spinner fa-spin"></i>\';

    fetch(`../../ajax/whatsapp.php?action=search_student&query=${encodeURIComponent(query)}`)
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = \'<i class="fa-solid fa-search me-1"></i>Search\';

            if (res.status === "success") {
                currentStudent = res.data;
                populateStudentProfile(currentStudent);
                updateLivePreview();
                showAlert("success", `Student profile loaded: ${currentStudent.student_name}`);
            } else {
                currentStudent = null;
                document.getElementById("studentProfileBox").style.display = "none";
                document.getElementById("noStudentBox").style.display = "block";
                document.getElementById("sendWaBtn").disabled = true;
                showAlert("danger", res.message || "Student record not found.");
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = \'<i class="fa-solid fa-search me-1"></i>Search\';
            showAlert("danger", "Network error searching for student.");
        });
}

function populateStudentProfile(s) {
    document.getElementById("stdName").textContent = s.student_name;
    document.getElementById("stdRegNo").textContent = `ID: ${s.student_id}`;
    document.getElementById("stdClassSection").textContent = `${s.class_name} - ${s.section}`;
    document.getElementById("stdAtype").textContent = s.academic_type;
    document.getElementById("stdFather").textContent = s.father_name;
    document.getElementById("stdGuardian").textContent = s.guardian_name;
    document.getElementById("stdMobile").textContent = s.phone || "No valid number available";
    document.getElementById("stdDues").textContent = `Rs. ${s.outstanding_fee}`;
    document.getElementById("stdDueDate").textContent = s.due_date;
    document.getElementById("stdAttendance").textContent = s.attendance;

    if (s.photo) {
        document.getElementById("stdPhoto").src = `../../${s.photo}`;
    } else {
        document.getElementById("stdPhoto").src = "../../assets/img/avatar.png";
    }

    document.getElementById("hiddenRecipientName").value = s.student_name;
    document.getElementById("hiddenPhone").value = s.phone;

    document.getElementById("noStudentBox").style.display = "none";
    document.getElementById("studentProfileBox").style.display = "block";

    if (s.phone && s.phone.length >= 10) {
        document.getElementById("sendWaBtn").disabled = false;
    } else {
        document.getElementById("sendWaBtn").disabled = true;
        showAlert("warning", "No valid WhatsApp mobile number available for this student.");
    }
}

function insertVariable(tag) {
    const txtArea = document.getElementById("messageText");
    const start = txtArea.selectionStart;
    const end = txtArea.selectionEnd;
    const text = txtArea.value;
    txtArea.value = text.substring(0, start) + tag + text.substring(end);
    txtArea.focus();
    txtArea.selectionStart = txtArea.selectionEnd = start + tag.length;
    updateLivePreview();
}

function updateLivePreview() {
    const rawText = document.getElementById("messageText").value;
    const previewBox = document.getElementById("waPreviewBox");
    const charCounter = document.getElementById("charCounter");
    const varBadge = document.getElementById("varCountBadge");

    charCounter.textContent = `${rawText.length} characters`;

    const varsFound = (rawText.match(/\{\{[^}]+\}\}/g) || []).length;
    varBadge.textContent = `${varsFound} Variable(s) Inserted`;

    if (!rawText.trim()) {
        previewBox.textContent = "[Rendered message with replaced student variables will display here...]";
        return;
    }

    let rendered = rawText;
    if (currentStudent) {
        rendered = rendered
            .replaceAll("{{StudentName}}", currentStudent.student_name)
            .replaceAll("{{FatherName}}", currentStudent.father_name)
            .replaceAll("{{GuardianName}}", currentStudent.guardian_name)
            .replaceAll("{{Class}}", currentStudent.class_name)
            .replaceAll("{{Section}}", currentStudent.section)
            .replaceAll("{{StudentID}}", currentStudent.student_id)
            .replaceAll("{{OutstandingFee}}", currentStudent.outstanding_fee)
            .replaceAll("{{DueDate}}", currentStudent.due_date)
            .replaceAll("{{Attendance}}", currentStudent.today_attendance)
            .replaceAll("{{SchoolName}}", currentStudent.school_name);
    }

    previewBox.textContent = rendered;
}

function applyTemplate() {
    const select = document.getElementById("templateSelect");
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption.value !== "") {
        const body = selectedOption.getAttribute("data-body");
        document.getElementById("messageText").value = body;
        updateLivePreview();
    }
}

function showAlert(type, message) {
    const container = document.getElementById("waAlertContainer");
    container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid ${type === "success" ? "fa-circle-check" : "fa-triangle-exclamation"} me-2 fs-5"></i>
            <strong>${type === "success" ? "Success!" : "Notice:"}</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    window.scrollTo({ top: 0, behavior: "smooth" });
}

document.getElementById("whatsappForm").addEventListener("submit", function(e) {
    e.preventDefault();
    if (!currentStudent || !currentStudent.phone) {
        showAlert("danger", "No valid WhatsApp number available.");
        return;
    }

    const msg = document.getElementById("messageText").value.trim();
    if (!msg) {
        showAlert("warning", "Please enter message body text.");
        return;
    }

    const templateSelect = document.getElementById("templateSelect");

    let formData = new FormData();
    formData.append("action", "dispatch_message");
    formData.append("csrf_token", document.querySelector("input[name=\'csrf_token\']").value);
    formData.append("recipient_type", "Single Student");
    formData.append("recipient_name", currentStudent.student_name);
    formData.append("phone", currentStudent.phone);
    formData.append("message", document.getElementById("waPreviewBox").textContent);
    formData.append("template_name", templateSelect.value || "");

    fetch("../../ajax/whatsapp.php", {
        method: "POST",
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === "success") {
            showAlert("success", "WhatsApp Click-to-Chat link generated and logged!");
            if (data.url) {
                window.open(data.url, "_blank");
            }
        } else {
            showAlert("danger", data.message || "Failed to dispatch WhatsApp message.");
        }
    })
    .catch(err => {
        showAlert("danger", "Network error while sending WhatsApp message.");
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
