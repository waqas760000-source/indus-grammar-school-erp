<?php
/**
 * Indus Grammar School ERP - Academic Promotion Management
 * Version 4.0.0
 */

$pageTitle = 'Academic Promotions';
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

$students = [];
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
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-angles-up text-primary me-2"></i>Promotion Management</h3>
        <p class="text-muted small mb-0">Promote students to their next classes based on examination summaries.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="dashboard.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<div class="row">
    <!-- Left Column: Promotions Form -->
    <div class="col-lg-7 mb-4">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-arrows-split-up-and-left text-primary me-2"></i>Promotions Panel</h5>
            </div>
            
            <div class="card-body p-4">
                <form method="GET" class="row g-3 align-items-end mb-4" id="promotionFilterForm">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted">Academic Session</label>
                        <select class="form-select" name="academic_session" id="promoSession">
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $selectedSession === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted">Current Class</label>
                        <select class="form-select" name="current_class_id" required onchange="document.getElementById('promotionFilterForm').submit()">
                            <option value="">— Select Class —</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted">Target Class (Promote to)</label>
                        <select class="form-select" name="target_class_id" required>
                            <option value="">— Target Class —</option>
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

                        <div class="table-responsive mb-4">
                            <table class="table custom-table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" class="form-check-input" id="checkAll">
                                        </th>
                                        <th>Admission No.</th>
                                        <th>Student Name</th>
                                        <th class="text-center">Exam Result</th>
                                        <th class="text-center">Avg %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($students)): ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted">No students in current class section.</td></tr>
                                    <?php else: foreach ($students as $s): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input student-check" name="student_ids[]" value="<?php echo $s['id']; ?>">
                                            </td>
                                            <td><code class="text-muted"><?php echo htmlspecialchars($s['admission_no']); ?></code></td>
                                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
                                            <td class="text-center">
                                                <?php if ($s['result_status'] === 'Pass'): ?>
                                                    <span class="badge bg-success-soft rounded-pill px-3 py-1 fw-bold text-xs"><i class="fa-solid fa-circle-check me-1"></i>Pass</span>
                                                <?php elseif ($s['result_status'] === 'Fail'): ?>
                                                    <span class="badge bg-danger-soft rounded-pill px-3 py-1 fw-bold text-xs"><i class="fa-solid fa-circle-xmark me-1"></i>Fail</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border px-3 py-1 text-xs">No result</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center fw-bold text-primary"><?php echo $s['result_pct'] ? number_format($s['result_pct'], 1) . '%' : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between pt-2 border-top">
                            <button type="button" class="btn btn-outline-primary" id="btnSelectAllPass"><i class="fa-solid fa-check-double me-2"></i>Select All Passed</button>
                            <button type="submit" class="btn btn-success px-5 fw-bold" id="btnPromote"><i class="fa-solid fa-circle-up me-2"></i>Promote Selected</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="py-5 text-center text-muted">
                        <i class="fa-solid fa-angles-up fs-1 opacity-25 mb-2"></i>
                        <h6 class="mb-0">Choose current class above to load student registry.</h6>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Promotion Log / Revert registry -->
    <div class="col-lg-5 mb-4">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Promotion Logs</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table custom-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th class="text-center">Transition</th>
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
                                        <code class="text-muted small"><?php echo htmlspecialchars($p['admission_no']); ?></code>
                                    </td>
                                    <td class="text-center small">
                                        <span class="badge bg-light text-muted border"><?php echo htmlspecialchars($p['from_class'] . '-' . $p['from_section']); ?></span>
                                        <i class="fa-solid fa-arrow-right text-success mx-1 text-xs"></i>
                                        <span class="badge bg-primary text-white"><?php echo htmlspecialchars($p['to_class'] . '-' . $p['to_section']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-danger btn-revert rounded-pill px-3" data-id="<?php echo $p['id']; ?>">
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

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="promoToast" class="toast align-items-center text-white border-0" role="alert">
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

document.addEventListener("DOMContentLoaded", function() {
    // Select All
    const checkAll = document.getElementById("checkAll");
    if(checkAll) {
        checkAll.addEventListener("change", function() {
            document.querySelectorAll(".student-check").forEach(chk => {
                chk.checked = this.checked;
            });
        });
    }

    // Select All Passed
    const btnSelectAllPass = document.getElementById("btnSelectAllPass");
    if(btnSelectAllPass) {
        btnSelectAllPass.addEventListener("click", function() {
            document.querySelectorAll(".student-check").forEach(chk => {
                const tr = chk.closest("tr");
                const badge = tr.querySelector(".bg-success-soft");
                chk.checked = !!badge;
            });
        });
    }

    // Promotion Submission
    const form = document.getElementById("promotionForm");
    if(form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            // Check if target class is chosen
            const targetSelect = document.querySelector(\'[name="target_class_id"]\');
            if(!targetSelect.value) {
                showToast("Please choose a target class to promote students to.", false);
                return;
            }
            
            document.getElementById("toClassId").value = targetSelect.value;
            
            // Check checks
            const checked = document.querySelectorAll(".student-check:checked");
            if(checked.length === 0) {
                showToast("Please select at least one student to promote.", false);
                return;
            }

            if(!confirm(`Are you sure you want to promote ${checked.length} selected students to the new class?`)) return;

            const btn = document.getElementById("btnPromote");
            btn.disabled = true; btn.innerHTML = "Processing Promotions...";

            fetch("../../ajax/exams.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        btn.disabled = false; btn.innerHTML = \'<i class=\"fa-solid fa-circle-up me-2\"></i>Promote Selected\';
                    }
                })
                .catch(() => {
                    showToast("Failed to promote student batch.", false);
                    btn.disabled = false; btn.innerHTML = \'<i class=\"fa-solid fa-circle-up me-2\"></i>Promote Selected\';
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
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else this.disabled = false;
                })
                .catch(() => {
                    showToast("Error.", false);
                    this.disabled = false;
                });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
