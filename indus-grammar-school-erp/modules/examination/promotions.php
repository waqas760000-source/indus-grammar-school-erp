<?php
/**
 * Indus Grammar School ERP - Academic Promotions & Transition Console
 * Version 4.0.0
 */

$pageTitle = 'Academic Student Promotions';
$breadcrumbActive = 'Examination';
include_once __DIR__ . '/../../includes/header.php';

// Restricted to Super Admin, School Admin
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN])) {
    $_SESSION['flash_error'] = 'Access denied. Only school administrators can manage student promotions.';
    redirect(APP_URL . '/modules/examination/dashboard.php');
}

$db = Database::getConnection();

// Selectors
$sessions = $db->query("SELECT DISTINCT academic_year FROM fee_structure UNION SELECT '" . CURRENT_ACADEMIC_YEAR . "'")->fetchAll(PDO::FETCH_COLUMN);
$classes  = SchoolClass::all();

$selectedSession = sanitize($_GET['academic_session'] ?? CURRENT_ACADEMIC_YEAR);
$selectedClass   = isset($_GET['current_class_id']) ? (int)$_GET['current_class_id'] : 0;
$selectedTarget  = isset($_GET['target_class_id']) ? (int)$_GET['target_class_id'] : 0;

$students       = [];
$pastPromotions = [];

if ($selectedClass > 0) {
    // Fetch all active students in current class and check their latest exam result status
    $stmt = $db->prepare("
        SELECT st.id, st.admission_no, st.first_name, st.last_name, 
               MAX(er.status) as result_status, MAX(er.percentage) as result_pct, MAX(er.grade) as result_grade
        FROM students st
        LEFT JOIN exam_results er ON er.student_id = st.id AND er.class_id = :cid1
        WHERE st.class_id = :cid2 AND st.status = 'Active'
        GROUP BY st.id, st.admission_no, st.first_name, st.last_name
        ORDER BY st.first_name ASC
    ");
    $stmt->execute(['cid1' => $selectedClass, 'cid2' => $selectedClass]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch past promotions log for display
$pastPromotions = Promotion::all($selectedSession);

// Compute Stats for current selection
$totalInClass = count($students);
$passedInClass = 0;
$failedInClass = 0;
foreach ($students as $s) {
    if (strcasecmp($s['result_status'] ?? '', 'Pass') === 0) $passedInClass++;
    elseif (strcasecmp($s['result_status'] ?? '', 'Fail') === 0) $failedInClass++;
}
$promotionsCountThisSession = count($pastPromotions);
?>

<!-- Custom CSS Styling -->
<style>
.promotions-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #065f46 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 2.2rem 2rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
    border-bottom: 3px solid #10b981;
    position: relative;
    overflow: hidden;
}
.promotions-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
}
.kpi-card-promo {
    border: none;
    border-radius: 14px;
    background: #ffffff;
    transition: all 0.25s ease-in-out;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
}
.kpi-card-promo:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
}
.kpi-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}
.badge-soft-emerald { background-color: rgba(16, 185, 129, 0.12); color: #059669; }
.badge-soft-blue { background-color: rgba(37, 99, 235, 0.12); color: #2563eb; }
.badge-soft-amber { background-color: rgba(245, 158, 11, 0.12); color: #d97706; }
.badge-soft-rose { background-color: rgba(244, 63, 94, 0.12); color: #e11d48; }

.table-promo thead th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    font-weight: 700;
    padding: 0.9rem;
    border-bottom: 2px solid #e2e8f0;
}
.table-promo tbody td {
    padding: 0.85rem 0.9rem;
    vertical-align: middle;
}
</style>

<!-- Executive Hero Header Banner -->
<div class="promotions-hero mb-4 d-print-none">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-emerald bg-opacity-25 text-emerald px-3 py-1 rounded-pill fw-semibold small" style="color:#6ee7b7;">
                    <i class="fa-solid fa-angles-up me-1"></i> Student Progression Manager
                </span>
                <span class="badge bg-info bg-opacity-25 text-info px-3 py-1 rounded-pill fw-semibold small">
                    <i class="fa-solid fa-arrows-split-up-and-left me-1"></i> Class Batch Transitions
                </span>
            </div>
            <h2 class="fw-bold text-white mb-2">
                <i class="fa-solid fa-graduation-cap text-emerald me-2" style="color:#34d399;"></i>Academic Student Promotions
            </h2>
            <p class="text-white-50 mb-0">
                Promote students to their next academic classes based on examination summaries, or revert past promotion logs.
            </p>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                <a href="dashboard.php" class="btn btn-outline-secondary text-white border-secondary px-3 py-2 rounded-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- KPI Metrics Overview -->
<div class="row g-3 mb-4 d-print-none">
    <!-- KPI 1: Class Roster Size -->
    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card-promo p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Current Class Roster</span>
                    <h3 class="fw-bold text-dark mb-0"><?php echo $totalInClass; ?></h3>
                    <small class="text-blue fw-semibold" style="color:#2563eb;"><i class="fa-solid fa-users me-1"></i>Students Loaded</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-blue">
                    <i class="fa-solid fa-user-group"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI 2: Passed Students Ready -->
    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card-promo p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Passed & Eligible</span>
                    <h3 class="fw-bold text-emerald mb-0" style="color:#059669;"><?php echo $passedInClass; ?></h3>
                    <small class="text-emerald fw-semibold" style="color:#059669;"><i class="fa-solid fa-circle-check me-1"></i>Passed Exams</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-emerald">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI 3: Failed / Pending -->
    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card-promo p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Failed / Under Review</span>
                    <h3 class="fw-bold text-rose mb-0" style="color:#e11d48;"><?php echo $failedInClass; ?></h3>
                    <small class="text-rose fw-semibold" style="color:#e11d48;"><i class="fa-solid fa-triangle-exclamation me-1"></i>Needs Audit</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-rose">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI 4: Promoted This Session -->
    <div class="col-xl-3 col-md-6">
        <div class="card kpi-card-promo p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Session Logged</span>
                    <h3 class="fw-bold text-amber mb-0" style="color:#d97706;"><?php echo $promotionsCountThisSession; ?></h3>
                    <small class="text-amber fw-semibold" style="color:#d97706;"><i class="fa-solid fa-clock-rotate-left me-1"></i>Promotions Logged</small>
                </div>
                <div class="kpi-icon-wrapper badge-soft-amber">
                    <i class="fa-solid fa-angles-up"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left Column: Batch Promotions Console -->
    <div class="col-lg-7 mb-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:14px;">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                <h5 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-arrows-split-up-and-left text-primary me-2"></i>Batch Promotions Panel
                </h5>
                <p class="text-muted small mb-0">Select current class and target class to execute student batch promotions.</p>
            </div>
            
            <div class="card-body p-4">
                <form method="GET" class="row g-3 align-items-end mb-4" id="promotionFilterForm">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-dark">Academic Session</label>
                        <select class="form-select form-select-sm" name="academic_session" id="promoSession" onchange="this.form.submit()">
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $selectedSession === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-dark">Current Class *</label>
                        <select class="form-select form-select-sm" name="current_class_id" required onchange="this.form.submit()">
                            <option value="">-- Choose Class --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-dark">Target Class (Promote to) *</label>
                        <select class="form-select form-select-sm" name="target_class_id" required>
                            <option value="">-- Target Class --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $selectedTarget === (int)$c['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if ($selectedClass > 0): ?>
                    <form id="promotionForm">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action" value="promote_students">
                        <input type="hidden" name="from_class_id" value="<?php echo $selectedClass; ?>">
                        <input type="hidden" name="to_class_id" id="toClassId" value="<?php echo $selectedTarget; ?>">
                        <input type="hidden" name="academic_session" value="<?php echo $selectedSession; ?>">

                        <!-- Batch Action Tool Bar -->
                        <div class="d-flex align-items-center justify-content-between mb-3 bg-light p-2.5 rounded-3 border">
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-outline-success btn-sm fw-semibold" id="btnSelectAllPass">
                                    <i class="fa-solid fa-check-double me-1"></i> Select All Passed
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold" id="btnSelectAll">
                                    <i class="fa-solid fa-list-check me-1"></i> Select All
                                </button>
                            </div>
                            <span class="badge bg-primary px-3 py-1.5 rounded-pill fw-bold" id="selectedCountBadge">0 Selected</span>
                        </div>

                        <div class="table-responsive mb-4">
                            <table class="table table-promo table-hover align-middle mb-0" id="promoTable">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;" class="text-center">
                                            <input type="checkbox" class="form-check-input" id="checkAll">
                                        </th>
                                        <th>Admission No.</th>
                                        <th>Student Full Name</th>
                                        <th class="text-center">Exam Result</th>
                                        <th class="text-center">Score %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($students)): ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted">No students found in selected class.</td></tr>
                                    <?php else: foreach ($students as $s): 
                                        $resSt = $s['result_status'] ?? '';
                                        $isPass = (strcasecmp($resSt, 'Pass') === 0);
                                        $isFail = (strcasecmp($resSt, 'Fail') === 0);
                                    ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input student-check" name="student_ids[]" value="<?php echo $s['id']; ?>" onchange="updateSelectedCount()">
                                            </td>
                                            <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($s['admission_no']); ?></code></td>
                                            <td class="fw-bold text-dark">
                                                <i class="fa-solid fa-user-graduate text-primary me-2 opacity-75"></i>
                                                <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($isPass): ?>
                                                    <span class="badge bg-success bg-opacity-15 text-success px-3 py-1 rounded-pill fw-bold status-pass-indicator">
                                                        <i class="fa-solid fa-check me-1"></i>PASS
                                                    </span>
                                                <?php elseif ($isFail): ?>
                                                    <span class="badge bg-danger bg-opacity-15 text-danger px-3 py-1 rounded-pill fw-bold">
                                                        <i class="fa-solid fa-xmark me-1"></i>FAIL
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border px-2.5 py-1">No Result</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center fw-bold text-primary">
                                                <?php echo $s['result_pct'] ? number_format((float)$s['result_pct'], 1) . '%' : '—'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end pt-3 border-top">
                            <button type="submit" class="btn btn-emerald text-white px-5 fw-bold" style="background-color:#10b981; border:none;" id="btnPromote">
                                <i class="fa-solid fa-circle-up me-2"></i> Promote Selected Students
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="py-5 text-center text-muted">
                        <i class="fa-solid fa-angles-up fa-3x opacity-25 mb-3"></i>
                        <h6 class="fw-bold text-dark mb-1">No Current Class Selected</h6>
                        <p class="text-muted small mb-0">Choose a current class section above to load student registry.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Promotion Log / Audit Trail -->
    <div class="col-lg-5 mb-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:14px;">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                <h5 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Promotions Journal & Revert Log
                </h5>
                <p class="text-muted small mb-0">Audit past promotions in session <?php echo htmlspecialchars($selectedSession); ?>.</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 540px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Student</th>
                                <th class="text-center">Class Transition</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pastPromotions)): ?>
                                <tr><td colspan="3" class="text-center py-5 text-muted">No promotions processed in this session.</td></tr>
                            <?php else: foreach ($pastPromotions as $p): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></div>
                                        <code class="text-muted text-xs"><?php echo htmlspecialchars($p['admission_no']); ?></code>
                                    </td>
                                    <td class="text-center small">
                                        <span class="badge bg-light text-muted border px-2 py-1"><?php echo htmlspecialchars($p['from_class'] . '-' . $p['from_section']); ?></span>
                                        <i class="fa-solid fa-arrow-right text-success mx-1 text-xs"></i>
                                        <span class="badge bg-primary text-white px-2 py-1"><?php echo htmlspecialchars($p['to_class'] . '-' . $p['to_section']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-danger btn-revert rounded-pill px-3" data-id="<?php echo $p['id']; ?>" title="Revert Promotion">
                                            <i class="fa-solid fa-rotate-left me-1"></i>Revert
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="promoToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="promoToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("promoToast");
    const m = document.getElementById("promoToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll(".student-check:checked").length;
    const badge = document.getElementById("selectedCountBadge");
    if (badge) badge.textContent = `${checked} Selected`;
}

document.addEventListener("DOMContentLoaded", function() {
    updateSelectedCount();

    // Check All
    const checkAll = document.getElementById("checkAll");
    if(checkAll) {
        checkAll.addEventListener("change", function() {
            document.querySelectorAll(".student-check").forEach(chk => {
                chk.checked = this.checked;
            });
            updateSelectedCount();
        });
    }

    // Select All
    const btnSelectAll = document.getElementById("btnSelectAll");
    if(btnSelectAll) {
        btnSelectAll.addEventListener("click", function() {
            document.querySelectorAll(".student-check").forEach(chk => {
                chk.checked = true;
            });
            if(checkAll) checkAll.checked = true;
            updateSelectedCount();
        });
    }

    // Select All Passed
    const btnSelectAllPass = document.getElementById("btnSelectAllPass");
    if(btnSelectAllPass) {
        btnSelectAllPass.addEventListener("click", function() {
            document.querySelectorAll(".student-check").forEach(chk => {
                const tr = chk.closest("tr");
                const badge = tr.querySelector(".status-pass-indicator");
                chk.checked = !!badge;
            });
            updateSelectedCount();
        });
    }

    // Promotion Submission
    const form = document.getElementById("promotionForm");
    if(form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const targetSelect = document.querySelector(\'[name="target_class_id"]\');
            if(!targetSelect.value) {
                showToast("Please choose a target class to promote students to.", false);
                return;
            }
            
            document.getElementById("toClassId").value = targetSelect.value;
            
            const checked = document.querySelectorAll(".student-check:checked");
            if(checked.length === 0) {
                showToast("Please select at least one student to promote.", false);
                return;
            }

            if(!confirm(`Are you sure you want to promote ${checked.length} selected student(s) to the new class?`)) return;

            const btn = document.getElementById("btnPromote");
            btn.disabled = true; btn.innerHTML = \'<i class="fa-solid fa-spinner fa-spin me-1"></i> Processing Promotions...\';

            fetch("../../ajax/exams.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        setTimeout(() => location.reload(), 800);
                    } else {
                        btn.disabled = false; btn.innerHTML = \'<i class="fa-solid fa-circle-up me-2"></i> Promote Selected Students\';
                    }
                })
                .catch(() => {
                    showToast("Failed to promote student batch.", false);
                    btn.disabled = false; btn.innerHTML = \'<i class="fa-solid fa-circle-up me-2"></i> Promote Selected Students\';
                });
        });
    }

    // Revert Trigger
    document.querySelectorAll(".btn-revert").forEach(btn => {
        btn.addEventListener("click", function() {
            if(!confirm("Are you sure you want to revert this promotion log? The student will be returned to their previous class section.")) return;
            const id = this.dataset.id;
            this.disabled = true;

            const fd = new FormData();
            fd.append("action", "rollback_promotion");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);

            fetch("../../ajax/exams.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 800);
                    else this.disabled = false;
                })
                .catch(() => {
                    showToast("System error on revert.", false);
                    this.disabled = false;
                });
        });
    });
});
</script>';

include_once __DIR__ . '/../../includes/footer.php'; ?>
