<?php
/**
 * Indus Grammar School ERP - Exam Types & Terms Registry Management
 * Version 5.0.0 - Executive Exam Terms & Passing Standards Console
 */

$pageTitle = 'Exam Terms & Types';
$breadcrumbActive = 'Examination';
include_once __DIR__ . '/../../includes/header.php';

// Restricted to Super Admin, School Admin, Exam Controller, Teachers
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'teacher', 'exam_controller'])) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permissions to access the examination module.';
    redirect(APP_URL . '/dashboard.php');
}

$db = Database::getConnection();

// Parameters
$selectedSession = sanitize($_GET['session'] ?? CURRENT_ACADEMIC_YEAR);
$selectedType    = sanitize($_GET['type'] ?? '');
$selectedStatus  = sanitize($_GET['status'] ?? '');

// Fetch active sessions list dynamically
$sessionsList = [];
try {
    $sessionsList = $db->query("SELECT DISTINCT academic_session FROM exam_types UNION SELECT '" . CURRENT_ACADEMIC_YEAR . "' ORDER BY academic_session DESC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $sessionsList = [CURRENT_ACADEMIC_YEAR];
}

// Fetch exam terms matching filter parameters
$where = " WHERE 1=1";
$params = [];

if ($selectedSession !== 'All' && $selectedSession !== '') {
    $where .= " AND academic_session = :session";
    $params['session'] = $selectedSession;
}
if ($selectedType !== '') {
    $where .= " AND academic_type = :type";
    $params['type'] = $selectedType;
}
if ($selectedStatus !== '') {
    $where .= " AND status = :status";
    $params['status'] = $selectedStatus;
}

$examTypes = [];
try {
    $stmt = $db->prepare("SELECT * FROM exam_types $where ORDER BY start_date DESC");
    $stmt->execute($params);
    $examTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading exam types: " . $e->getMessage());
}

// Summary Metrics
$totalTerms    = count($examTypes);
$activeTerms   = 0;
$inactiveTerms = 0;
$avgPassingPct = 0;
$totalPassingSum = 0;

foreach ($examTypes as $e) {
    if ($e['status'] === 'Active') $activeTerms++;
    else $inactiveTerms++;
    $totalPassingSum += (float)$e['passing_percentage'];
}
if ($totalTerms > 0) {
    $avgPassingPct = round($totalPassingSum / $totalTerms, 1);
}
?>

<style>
.exam-type-row:hover { background-color: rgba(13, 110, 253, 0.025); }
.term-card-kpi { border-radius: 12px; transition: transform 0.15s ease-in-out; }
.term-card-kpi:hover { transform: translateY(-2px); }
</style>

<!-- Top Executive Hero Banner Header -->
<div class="card border-0 shadow-lg mb-4 overflow-hidden" style="border-radius: 16px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f2744 100%);">
    <div class="card-body p-4 p-md-5 position-relative">
        <!-- Decorative glowing orb backdrop -->
        <div class="position-absolute end-0 top-0 translate-middle-y me-5 mt-4" style="width: 300px; height: 300px; background: radial-gradient(circle, rgba(13, 110, 253, 0.25) 0%, rgba(0, 0, 0, 0) 70%); pointer-events: none; filter: blur(40px);"></div>
        
        <div class="row align-items-center position-relative" style="z-index: 2;">
            <div class="col-lg-7 mb-3 mb-lg-0">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(13, 110, 253, 0.15); border: 1px solid rgba(13, 110, 253, 0.3);">
                    <span class="pulse-dot bg-primary rounded-circle d-inline-block" style="width: 8px; height: 8px;"></span>
                    <span class="fw-bold uppercase tracking-wider" style="font-size: 0.72rem; letter-spacing: 1px; color: #60a5fa;">EXAM TERMS CONSOLE</span>
                </div>
                <h2 class="fw-extrabold text-white mb-2 tracking-tight d-flex align-items-center gap-2">
                    <i class="fa-solid fa-calendar-days text-warning fs-2 me-2"></i>Exam Terms & Types Configuration
                </h2>
                <p class="mb-0 leading-relaxed" style="font-size: 0.95rem; color: #94a3b8;">
                    Create and coordinate exam terms, session durations, and passing standards.
                </p>
            </div>
            
            <div class="col-lg-5 text-lg-end">
                <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end">
                    <button class="btn btn-warning px-4 py-2 shadow-sm rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#examModal" onclick="resetForm()">
                        <i class="fa-solid fa-plus me-2"></i>New Exam Term
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-light px-3 py-2 shadow-sm rounded-pill fw-semibold">
                        <i class="fa-solid fa-arrow-left me-1"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 5 Summary KPI Micro-Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 term-card-kpi text-center bg-white border-start border-primary border-4">
            <div class="small text-muted fw-semibold text-uppercase" style="font-size:0.7rem;">Configured Terms</div>
            <div class="fs-3 fw-bold text-dark my-1"><?php echo $totalTerms; ?></div>
            <div class="small text-muted" style="font-size:0.7rem;">Exam Sessions</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 term-card-kpi text-center" style="background-color: #f0fdf4;">
            <div class="small text-success fw-semibold text-uppercase" style="font-size:0.7rem;">Active Terms</div>
            <div class="fs-3 fw-bold text-success my-1"><?php echo $activeTerms; ?></div>
            <div class="small text-success-50" style="font-size:0.7rem;">Available for Schedule</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 term-card-kpi text-center" style="background-color: #fef2f2;">
            <div class="small text-danger fw-semibold text-uppercase" style="font-size:0.7rem;">Inactive Terms</div>
            <div class="fs-3 fw-bold text-danger my-1"><?php echo $inactiveTerms; ?></div>
            <div class="small text-danger-50" style="font-size:0.7rem;">Archived Terms</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 term-card-kpi text-center" style="background-color: #fffbeb;">
            <div class="small text-warning fw-semibold text-uppercase" style="font-size:0.7rem;">Avg Pass Threshold</div>
            <div class="fs-3 fw-bold text-warning my-1"><?php echo $avgPassingPct; ?>%</div>
            <div class="small text-warning-50" style="font-size:0.7rem;">Standard Passing Standard</div>
        </div>
    </div>
    <div class="col-12 col-md-4 col-lg">
        <div class="card border-0 shadow-sm p-3 h-100 term-card-kpi text-center text-white" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6);">
            <div class="small text-white-50 fw-semibold text-uppercase" style="font-size:0.7rem;">Active Session</div>
            <div class="fs-3 fw-bold text-white my-1"><?php echo htmlspecialchars($selectedSession === 'All' ? 'ALL SESSIONS' : $selectedSession); ?></div>
            <div class="small text-white-50" style="font-size:0.7rem;">Academic Year</div>
        </div>
    </div>
</div>

<!-- Filters Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-sliders me-2 text-primary"></i>Filter Exam Terms</h6>
        <form method="GET" class="row g-3 align-items-end" id="examTermsFilterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Academic Session</label>
                <select class="form-select form-select-sm" name="session">
                    <option value="All" <?php echo ($selectedSession === 'All') ? 'selected' : ''; ?>>All Academic Sessions</option>
                    <?php foreach ($sessionsList as $s): ?>
                        <option value="<?php echo htmlspecialchars($s); ?>" <?php echo ($selectedSession === $s) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Academic Category</label>
                <select class="form-select form-select-sm" name="type">
                    <option value="">All Categories</option>
                    <option value="School" <?php echo ($selectedType === 'School') ? 'selected' : ''; ?>>School</option>
                    <option value="Academy" <?php echo ($selectedType === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo ($selectedStatus === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo ($selectedStatus === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary w-100 py-1 fw-semibold">
                    <i class="fa-solid fa-filter me-1"></i>Filter Terms
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Table List Register Card -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px; overflow:hidden;">
    <div class="card-header bg-white p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span class="fw-bold text-dark"><i class="fa-solid fa-list-check me-2 text-primary"></i>Configured Exam Terms (<span id="visibleTermsCount"><?php echo count($examTypes); ?></span> Terms)</span>
        <div class="position-relative" style="width: 250px;">
            <input type="text" class="form-control form-control-sm ps-4 rounded-pill" id="termsSearchInput" placeholder="Filter terms by keyword...">
            <i class="fa-solid fa-magnifying-glass position-absolute start-0 top-50 translate-middle-y ms-2 text-muted small"></i>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0" id="examTermsTable">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Exam Term Name</th>
                    <th>Academic Session</th>
                    <th class="text-center">Category</th>
                    <th class="text-center">Term Dates & Duration</th>
                    <th class="text-end">Total Marks</th>
                    <th class="text-center">Passing Standard</th>
                    <th class="text-center">Status</th>
                    <th width="140" class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($examTypes)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-folder-open fs-1 d-block mb-2 opacity-50"></i>
                            <h6 class="fw-bold mb-1">No Exam Terms Configured</h6>
                            <p class="small mb-0">Click "New Exam Term" button to create an examination session.</p>
                        </td>
                    </tr>
                <?php else: foreach ($examTypes as $e): 
                    $startT = strtotime($e['start_date']);
                    $endT   = strtotime($e['end_date']);
                    $days   = (int)(ceil(($endT - $startT) / 86400) + 1);
                    $durationText = $days > 0 ? "{$days} Days Duration" : "Single Day";
                ?>
                    <tr class="exam-type-row" data-search="<?php echo htmlspecialchars(strtolower($e['exam_name'] . ' ' . $e['academic_session'] . ' ' . $e['academic_type'] . ' ' . $e['status'])); ?>">
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary-soft text-primary d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width:32px; height:32px;">
                                    <i class="fa-solid fa-calendar-check"></i>
                                </div>
                                <span class="fw-bold text-dark"><?php echo htmlspecialchars($e['exam_name']); ?></span>
                            </div>
                        </td>
                        <td><span class="badge bg-light text-dark border font-monospace px-3 py-1 rounded-pill"><?php echo htmlspecialchars($e['academic_session']); ?></span></td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $e['academic_type'] === 'Academy' ? 'warning text-dark' : 'info text-white'; ?> px-3 py-1 rounded-pill fw-bold" style="font-size:0.75rem;">
                                <?php echo htmlspecialchars($e['academic_type']); ?>
                            </span>
                        </td>
                        <td class="text-center small">
                            <div class="fw-bold text-dark"><?php echo date('d M Y', $startT); ?> → <?php echo date('d M Y', $endT); ?></div>
                            <span class="badge bg-light text-secondary border font-monospace" style="font-size:0.68rem;"><?php echo $durationText; ?></span>
                        </td>
                        <td class="text-end fw-bold text-dark fs-6"><?php echo $e['total_marks']; ?> <span class="fs-6 text-muted font-monospace">pts</span></td>
                        <td class="text-center">
                            <span class="badge bg-success-soft text-success px-3 py-2 rounded-pill fw-bold" style="font-size:0.78rem;">
                                <?php echo number_format($e['passing_percentage'], 1); ?>% Pass
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $e['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-2 rounded-pill fw-bold" style="font-size:0.75rem;">
                                <?php echo $e['status']; ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-inline-flex gap-1">
                                <button class="btn btn-sm btn-outline-primary rounded-circle" title="Edit Exam Term" onclick='editExam(<?php echo json_encode($e); ?>)'>
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <?php if (hasPermission('academic_manage')): ?>
                                    <button class="btn btn-sm btn-outline-danger btn-delete rounded-circle" data-id="<?php echo $e['id']; ?>" title="Delete Exam Term">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form for Create / Edit -->
<div class="modal fade" id="examModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-top-left-radius:16px; border-top-right-radius:16px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary-soft p-2 d-flex align-items-center justify-content-center text-primary" style="width:40px; height:40px;">
                        <i class="fa-solid fa-calendar-days fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="modalTitle">Configure Exam Term</h5>
                        <p class="small text-slate-300 mb-0">Set session duration & passing criteria</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <form id="examForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_exam">
                    <input type="hidden" name="exam_id" id="examId" value="0">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Exam Term Name *</label>
                        <input type="text" class="form-control" name="exam_name" id="examName" required placeholder="e.g. Mid Term Examination 2026">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Academic Session *</label>
                            <select class="form-select" name="academic_session" id="examSession" required>
                                <?php foreach ($sessionsList as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Academic Category</label>
                            <select class="form-select" name="academic_type" id="examAcademicType">
                                <option value="School">School</option>
                                <option value="Academy">Academy</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" id="examStart" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">End Date *</label>
                            <input type="date" class="form-control" name="end_date" id="examEnd" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Term Duration Calculation</label>
                        <input type="text" class="form-control text-center fw-bold bg-light" id="examDurationPreview" readonly value="Select dates to compute duration">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Total Base Marks *</label>
                            <input type="number" class="form-control" name="total_marks" id="examTotalMarks" min="1" value="100" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Passing Percentage (%) *</label>
                            <input type="number" step="0.5" class="form-control" name="passing_percentage" id="examPassingPercentage" min="1" max="100" value="40.0" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                        <select class="form-select" name="status" id="examStatus">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer bg-light border-top-0" style="border-bottom-left-radius:16px; border-bottom-right-radius:16px;">
                <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="examForm" class="btn btn-primary px-5 rounded-pill fw-bold shadow-sm" id="btnSave">
                    Save Exam Term
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="examToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="examToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
const modalObj = new bootstrap.Modal(document.getElementById("examModal"));

function showToast(msg, ok) {
    const t = document.getElementById("examToast");
    const m = document.getElementById("examToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function resetForm() {
    document.getElementById("examForm").reset();
    document.getElementById("examId").value = "0";
    document.getElementById("examDurationPreview").value = "Select dates to compute duration";
    document.getElementById("modalTitle").textContent = "Configure Exam Term";
    document.getElementById("btnSave").innerHTML = "Save Exam Term";
}

function editExam(data) {
    resetForm();
    document.getElementById("examId").value = data.id;
    document.getElementById("examName").value = data.exam_name;
    document.getElementById("examSession").value = data.academic_session;
    document.getElementById("examAcademicType").value = data.academic_type;
    document.getElementById("examStart").value = data.start_date;
    document.getElementById("examEnd").value = data.end_date;
    document.getElementById("examTotalMarks").value = data.total_marks;
    document.getElementById("examPassingPercentage").value = data.passing_percentage;
    document.getElementById("examStatus").value = data.status;
    
    calcDuration();
    
    document.getElementById("modalTitle").textContent = "Edit Exam Term";
    document.getElementById("btnSave").innerHTML = "Update Exam Term";
    modalObj.show();
}

function calcDuration() {
    const startVal = document.getElementById("examStart").value;
    const endVal   = document.getElementById("examEnd").value;
    const preview  = document.getElementById("examDurationPreview");
    if (startVal && endVal) {
        const d1 = new Date(startVal);
        const d2 = new Date(endVal);
        const diffMs = d2 - d1;
        if (diffMs >= 0) {
            const days = Math.ceil(diffMs / (1000 * 60 * 60 * 24)) + 1;
            preview.value = days + " Day(s) Session Duration";
        } else {
            preview.value = "Invalid date range";
        }
    }
}

document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("examStart").addEventListener("change", calcDuration);
    document.getElementById("examEnd").addEventListener("change", calcDuration);

    // Search instant filter
    const searchInput = document.getElementById("termsSearchInput");
    if (searchInput) {
        searchInput.addEventListener("keyup", function() {
            const query = this.value.toLowerCase().trim();
            let visible = 0;
            document.querySelectorAll(".exam-type-row").forEach(row => {
                const searchData = row.dataset.search || "";
                if (searchData.includes(query)) {
                    row.style.display = "";
                    visible++;
                } else {
                    row.style.display = "none";
                }
            });
            const visCount = document.getElementById("visibleTermsCount");
            if (visCount) visCount.textContent = visible;
        });
    }

    // Form Save AJAX
    const form = document.getElementById("examForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSave");
            btn.disabled = true; 
            btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Saving Term...\';

            fetch("../../ajax/exams.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) {
                        modalObj.hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        btn.disabled = false; 
                        btn.innerHTML = document.getElementById("examId").value !== "0" ? "Update Exam Term" : "Save Exam Term";
                    }
                })
                .catch(() => {
                    showToast("System communication error.", false);
                    btn.disabled = false; 
                    btn.innerHTML = "Save Exam Term";
                });
        });
    }

    // Delete Trigger
    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if (!confirm("Are you sure you want to delete this exam term? This deletes all associated timetables, marks logs, and results!")) return;
            const id = this.dataset.id;
            this.disabled = true;

            const fd = new FormData();
            fd.append("action", "delete_exam");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);

            fetch("../../ajax/exams.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 1000);
                    else this.disabled = false;
                })
                .catch(() => {
                    showToast("Communication error.", false);
                    this.disabled = false;
                });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
