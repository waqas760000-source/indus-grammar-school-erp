<?php
/**
 * Indus Grammar School ERP - Bulk WhatsApp Dispatcher
 * Version 4.0.0
 */

$pageTitle = 'Bulk WhatsApp Dispatcher';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('communication_send');

// Load Classes & Templates
$classes = SchoolClass::all();
require_once __DIR__ . '/../../models/WhatsAppTemplate.php';
$templates = WhatsAppTemplate::all();
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-layer-group text-success me-2"></i>Bulk WhatsApp Dispatcher</h3>
        <p class="text-muted small mb-0">Generate pre-formatted Click-to-Chat links for entire classes, sections, or school programs.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="whatsapp.php" class="btn btn-outline-success px-3 me-2"><i class="fa-brands fa-whatsapp me-2"></i>Single WhatsApp</a>
        <a href="whatsapp_history.php" class="btn btn-outline-primary px-3 me-2"><i class="fa-solid fa-clock-rotate-left me-2"></i>WhatsApp History</a>
        <a href="dashboard.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Notification Container -->
<div id="bulkAlertContainer" class="mb-3"></div>

<div class="row g-4">
    <!-- Filter & Template Selection Column -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-filter me-2 text-primary"></i>1. Filter Target Recipients</h5>
            </div>
            <div class="card-body p-4 pt-2">
                <form id="bulkFilterForm" onsubmit="event.preventDefault(); resolveBulkRecipients();" class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-muted">Recipient Group</label>
                        <select class="form-select" id="bulkRecType" onchange="toggleBulkFilters()">
                            <option value="Whole Class">Whole Class</option>
                            <option value="Whole Section">Whole Section</option>
                            <option value="School Students">School (All Active Students)</option>
                            <option value="Academy Students">Academy (All Active Students)</option>
                            <option value="Teachers">Teachers</option>
                            <option value="Staff">Staff Members</option>
                            <option value="Parents">Parents</option>
                        </select>
                    </div>

                    <div class="col-md-6" id="bulkClassBox">
                        <label class="form-label small fw-semibold text-muted">Class Section</label>
                        <select class="form-select" id="bulkFilterClass" onchange="resolveBulkRecipients()">
                            <option value="0">All Classes</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6" id="bulkAtypeBox">
                        <label class="form-label small fw-semibold text-muted">Academic Type</label>
                        <select class="form-select" id="bulkFilterAtype" onchange="resolveBulkRecipients()">
                            <option value="">All Programs</option>
                            <option value="School">School Only</option>
                            <option value="Academy">Academy Only</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold text-muted">Apply Template</label>
                        <select class="form-select" id="bulkTemplateSelect" onchange="applyBulkTemplate()">
                            <option value="">-- Custom Message Body --</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?php echo htmlspecialchars($t['name']); ?>" data-body="<?php echo htmlspecialchars($t['body']); ?>"><?php echo htmlspecialchars($t['name'] . ' (' . $t['category'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold text-muted">Message Template Text</label>
                        <textarea class="form-control" id="bulkMessageText" rows="5" required placeholder="Type template message here with {{StudentName}}, {{FatherName}}, {{Class}}, {{OutstandingFee}}, etc..." onkeyup="resolveBulkRecipients()"></textarea>
                    </div>

                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-arrows-rotate me-2"></i>Resolve Recipients</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Queue & Action Navigation Column -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold text-secondary mb-0"><i class="fa-brands fa-whatsapp me-2 text-success"></i>2. Recipient Queue</h5>
                    <span class="badge bg-success-soft text-success fw-bold mt-1" id="bulkCountBadge">Recipients Resolved: 0</span>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-success btn-sm me-1" id="btnPrev" onclick="stepRecipient(-1)" disabled><i class="fa-solid fa-chevron-left me-1"></i>Previous</button>
                    <button type="button" class="btn btn-outline-success btn-sm me-1" id="btnNext" onclick="stepRecipient(1)" disabled>Next<i class="fa-solid fa-chevron-right ms-1"></i></button>
                    <button type="button" class="btn btn-success btn-sm px-3" id="btnOpenAll" onclick="confirmOpenAll()" disabled><i class="fa-solid fa-up-right-from-square me-1"></i>Open All</button>
                </div>
            </div>
            <div class="card-body p-4 pt-2">
                
                <!-- Active Step Card -->
                <div id="activeStepCard" class="border rounded p-3 mb-4 bg-light" style="display:none; border-left: 4px solid #10b981 !important;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-secondary-soft text-dark fw-bold" id="stepIndexBadge">Recipient 1 of 10</span>
                        <span class="fw-bold text-success font-monospace" id="stepPhone">923001234567</span>
                    </div>
                    <h6 class="fw-bold text-dark mb-1" id="stepName">Student / Parent Name</h6>
                    <p class="text-muted text-xs mb-2" id="stepInfo">ID / Class info</p>
                    
                    <div class="bg-white border rounded p-2 text-dark font-monospace text-xs mb-3" style="min-height: 50px;" id="stepMessage">
                        [Rendered message will appear here...]
                    </div>

                    <div class="text-end">
                        <a href="#" target="_blank" class="btn btn-success px-4 py-2 text-xs fw-bold" id="stepOpenBtn" onclick="logSingleStep()"><i class="fa-brands fa-whatsapp me-2 fs-6"></i>Open Current WhatsApp</a>
                    </div>
                </div>

                <!-- Recipient Checklist Table -->
                <div class="table-responsive border rounded" style="max-height: 350px; overflow-y: auto;" id="tableContainer">
                    <p class="text-muted text-center small py-4 mb-0">Click "Resolve Recipients" to generate WhatsApp links.</p>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Open All Confirmation Modal -->
<div class="modal fade" id="openAllModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fa-brands fa-whatsapp me-2"></i>Confirm Open All WhatsApp Tabs</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-2">Are you sure you want to open <strong id="openAllCount">0</strong> WhatsApp Click-to-Chat browser tabs?</p>
                <div class="alert alert-warning border-0 small text-xs mb-0">
                    <i class="fa-solid fa-circle-exclamation me-1"></i>Please ensure popup windows are allowed in your browser settings for this domain.
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success px-4" onclick="executeOpenAll()"><i class="fa-solid fa-check me-2"></i>Proceed Open All</button>
            </div>
        </div>
    </div>
</div>

<style>
.bg-success-soft { background-color: rgba(16, 185, 129, 0.1); color: #047857; }
.bg-secondary-soft { background-color: rgba(100, 116, 139, 0.1); color: #334155; }
</style>

<?php $extraJS = '<script>
let bulkList = [];
let currentIndex = 0;

function toggleBulkFilters() {
    const type = document.getElementById("bulkRecType").value;
    const classBox = document.getElementById("bulkClassBox");
    const atypeBox = document.getElementById("bulkAtypeBox");

    classBox.style.display = (["Whole Class", "Whole Section", "Parents"].includes(type)) ? "block" : "none";
    atypeBox.style.display = (["School Students", "Academy Students", "Parents"].includes(type)) ? "block" : "none";

    resolveBulkRecipients();
}

function resolveBulkRecipients() {
    const type = document.getElementById("bulkRecType").value;
    const filterClass = document.getElementById("bulkFilterClass").value;
    const filterAtype = document.getElementById("bulkFilterAtype").value;
    const msgText = document.getElementById("bulkMessageText").value;
    const templateSelect = document.getElementById("bulkTemplateSelect");

    const badge = document.getElementById("bulkCountBadge");
    const container = document.getElementById("tableContainer");

    if (!msgText.trim()) {
        badge.textContent = "Recipients Resolved: 0";
        container.innerHTML = \'<p class="text-muted text-center small py-4 mb-0">Please type a message or select a template to generate links.</p>\';
        disableBulkNav();
        return;
    }

    const url = `../../ajax/whatsapp.php?action=resolve_bulk_recipients&recipient_type=${encodeURIComponent(type)}&filter_class=${filterClass}&filter_atype=${encodeURIComponent(filterAtype)}&message=${encodeURIComponent(msgText)}&template_name=${encodeURIComponent(templateSelect.value)}`;

    fetch(url)
        .then(r => r.json())
        .then(res => {
            if (res.status === "success") {
                bulkList = res.data || [];
                const count = bulkList.length;
                badge.textContent = `Recipients Resolved: ${count}`;

                if (count > 0) {
                    currentIndex = 0;
                    renderBulkTable();
                    renderActiveStep();
                    enableBulkNav();
                } else {
                    disableBulkNav();
                    container.innerHTML = \'<p class="text-muted text-center small py-4 mb-0">No valid WhatsApp phone numbers resolved for selected group.</p>\';
                }
            } else {
                disableBulkNav();
                container.innerHTML = `<p class="text-danger text-center small py-4 mb-0">${res.message || "Failed to resolve recipients."}</p>`;
            }
        })
        .catch(err => {
            disableBulkNav();
            container.innerHTML = \'<p class="text-danger text-center small py-4 mb-0">Error connecting to server.</p>\';
        });
}

function renderBulkTable() {
    const container = document.getElementById("tableContainer");
    let html = `
        <table class="table custom-table table-hover align-middle mb-0 text-xs">
            <thead>
                <tr>
                    <th width="40">#</th>
                    <th>Recipient Name</th>
                    <th>Phone</th>
                    <th>Message Preview</th>
                    <th class="text-end" width="110">Action</th>
                </tr>
            </thead>
            <tbody>
    `;

    bulkList.forEach((r, idx) => {
        const isCurrent = idx === currentIndex ? "table-success" : "";
        html += `
            <tr class="${isCurrent}">
                <td class="fw-bold">${idx + 1}</td>
                <td class="fw-semibold text-dark">${r.name} <div class="text-muted text-xs font-normal">${r.info}</div></td>
                <td class="fw-bold text-success font-monospace">${r.phone}</td>
                <td class="text-truncate" style="max-width: 200px;">${r.rendered_msg}</td>
                <td class="text-end">
                    <a href="${r.click_url}" target="_blank" class="btn btn-outline-success btn-xs" onclick="logSingleIndex(${idx})"><i class="fa-brands fa-whatsapp me-1"></i>Open</a>
                </td>
            </tr>
        `;
    });

    html += \'</tbody></table>\';
    container.innerHTML = html;
}

function renderActiveStep() {
    if (bulkList.length === 0) {
        document.getElementById("activeStepCard").style.display = "none";
        return;
    }

    const item = bulkList[currentIndex];
    document.getElementById("stepIndexBadge").textContent = `Recipient ${currentIndex + 1} of ${bulkList.length}`;
    document.getElementById("stepPhone").textContent = item.phone;
    document.getElementById("stepName").textContent = item.name;
    document.getElementById("stepInfo").textContent = item.info;
    document.getElementById("stepMessage").textContent = item.rendered_msg;
    document.getElementById("stepOpenBtn").href = item.click_url;

    document.getElementById("activeStepCard").style.display = "block";
    document.getElementById("btnPrev").disabled = (currentIndex === 0);
    document.getElementById("btnNext").disabled = (currentIndex >= bulkList.length - 1);

    renderBulkTable();
}

function stepRecipient(dir) {
    currentIndex += dir;
    if (currentIndex < 0) currentIndex = 0;
    if (currentIndex >= bulkList.length) currentIndex = bulkList.length - 1;
    renderActiveStep();
}

function enableBulkNav() {
    document.getElementById("btnOpenAll").disabled = false;
}

function disableBulkNav() {
    bulkList = [];
    currentIndex = 0;
    document.getElementById("activeStepCard").style.display = "none";
    document.getElementById("btnPrev").disabled = true;
    document.getElementById("btnNext").disabled = true;
    document.getElementById("btnOpenAll").disabled = true;
}

function applyBulkTemplate() {
    const select = document.getElementById("bulkTemplateSelect");
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption.value !== "") {
        document.getElementById("bulkMessageText").value = selectedOption.getAttribute("data-body");
        resolveBulkRecipients();
    }
}

function logSingleIndex(idx) {
    const item = bulkList[idx];
    if (!item) return;
    logMessage(item.name, item.phone, item.rendered_msg);
}

function logSingleStep() {
    logSingleIndex(currentIndex);
}

function logMessage(name, phone, msg) {
    let formData = new FormData();
    formData.append("action", "dispatch_message");
    formData.append("csrf_token", "<?php echo csrfToken(); ?>");
    formData.append("recipient_type", document.getElementById("bulkRecType").value);
    formData.append("recipient_name", name);
    formData.append("phone", phone);
    formData.append("message", msg);
    formData.append("template_name", document.getElementById("bulkTemplateSelect").value || "");

    fetch("../../ajax/whatsapp.php", { method: "POST", body: formData });
}

function confirmOpenAll() {
    document.getElementById("openAllCount").textContent = bulkList.length;
    let modal = new bootstrap.Modal(document.getElementById("openAllModal"));
    modal.show();
}

function executeOpenAll() {
    let modalEl = document.getElementById("openAllModal");
    let modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    bulkList.forEach((item, i) => {
        setTimeout(() => {
            window.open(item.click_url, "_blank");
            logSingleIndex(i);
        }, i * 400); // 400ms delay between tabs
    });

    showAlert("success", `Initiated opening ${bulkList.length} WhatsApp Click-to-Chat tabs.`);
}

function showAlert(type, message) {
    const container = document.getElementById("bulkAlertContainer");
    container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid ${type === "success" ? "fa-circle-check" : "fa-triangle-exclamation"} me-2 fs-5"></i>
            <strong>${type === "success" ? "Success!" : "Notice:"}</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
}

document.addEventListener("DOMContentLoaded", function() {
    toggleBulkFilters();
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
