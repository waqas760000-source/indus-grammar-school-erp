<?php
/**
 * Indus Grammar School ERP - Result Process & Ledgers
 * Version 4.0.0
 */

$pageTitle = 'Examination Results';
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

// Selectors
$examTypes = $db->query("SELECT * FROM exam_types WHERE status = 'Active' ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$classes   = SchoolClass::all();

$selectedExam = isset($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : 0;
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

$results = [];
if ($selectedExam > 0 && $selectedClass > 0) {
    $results = ExamResult::getClassResults($selectedExam, $selectedClass);
}
?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-square-poll-vertical text-primary me-2"></i>Class Results</h3>
        <p class="text-muted small mb-0">Compile marks, evaluate class rankings, and publish formal student result sheets.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if ($selectedExam > 0 && $selectedClass > 0): ?>
            <button class="btn btn-warning text-dark fw-bold px-4" id="btnCompile" onclick="compileResults()">
                <i class="fa-solid fa-sync me-2"></i>Calculate & Rank Results
            </button>
            <button class="btn btn-outline-success px-4 ms-2" onclick="exportToExcel()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
            <button class="btn btn-outline-primary px-4 ms-2" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Ledger</button>
        <?php endif; ?>
        <a href="dashboard.php" class="btn btn-outline-secondary px-4 ms-2"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted">Select Exam Term</label>
                <select class="form-select" name="exam_type_id" required>
                    <option value="">— Choose Exam —</option>
                    <?php foreach ($examTypes as $et): ?>
                        <option value="<?php echo $et['id']; ?>" <?php echo $selectedExam === (int)$et['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($et['exam_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted">Select Class & Section</label>
                <select class="form-select" name="class_id" required>
                    <option value="">— Choose Class —</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Load Ledger</button>
            </div>
        </form>
    </div>
</div>

<!-- Results Output -->
<?php if ($selectedExam > 0 && $selectedClass > 0): ?>
    <div class="card border-0 shadow-sm mb-5" style="border-radius:12px;" id="resultLedgerArea">
        <div class="card-header bg-white border-0 pt-4 px-4 text-center d-none d-print-block border-bottom pb-3">
            <h3 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h3>
            <h5 class="text-secondary fw-semibold mb-1">Examination Result Ledger</h5>
            <div class="text-muted small">
                Exam: <?php 
                    $currExam = array_filter($examTypes, fn($e) => (int)$e['id'] === $selectedExam);
                    $currExam = reset($currExam);
                    echo htmlspecialchars($currExam['exam_name']);
                ?> | Class: <?php 
                    $currClass = array_filter($classes, fn($c) => (int)$c['id'] === $selectedClass);
                    $currClass = reset($currClass);
                    echo htmlspecialchars($currClass['class_name'] . '-' . $currClass['section']);
                ?>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table custom-table table-hover align-middle mb-0" id="resultTable">
                    <thead>
                        <tr>
                            <th width="80" class="text-center">Position</th>
                            <th>Admission No.</th>
                            <th>Student Name</th>
                            <th class="text-end">Total Marks</th>
                            <th class="text-end">Obtained Marks</th>
                            <th class="text-center">Percentage</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Status</th>
                            <th class="text-end d-print-none">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)): ?>
                            <tr><td colspan="9" class="text-center py-5 text-muted">No result records processed yet. Click "Calculate & Rank Results" to generate the ledger.</td></tr>
                        <?php else: foreach ($results as $r): ?>
                            <tr>
                                <td class="text-center">
                                    <?php if ($r['status'] === 'Pass'): ?>
                                        <span class="badge bg-success text-white px-3 py-1 rounded-pill fw-bold">Rank <?php echo $r['position']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary text-white px-3 py-1 rounded-pill fw-bold">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><code class="text-muted"><?php echo htmlspecialchars($r['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></td>
                                <td class="text-end fw-bold text-muted"><?php echo number_format($r['total_marks'], 1); ?></td>
                                <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($r['obtained_marks'], 1); ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format($r['percentage'], 1); ?>%</td>
                                <td class="text-center fw-bold fs-5 text-secondary"><?php echo htmlspecialchars($r['grade']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $r['status'] === 'Pass' ? 'success' : 'danger'; ?>-soft px-3 py-1 rounded-pill fw-bold"><?php echo $r['status']; ?></span>
                                </td>
                                <td class="text-end d-print-none">
                                    <a href="report_cards.php?exam_type_id=<?php echo $selectedExam; ?>&class_id=<?php echo $selectedClass; ?>&student_id=<?php echo $r['student_id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1">
                                        <i class="fa-solid fa-id-card me-1"></i>Report Card
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm" style="border-radius:12px; height: 320px;">
        <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-5">
            <i class="fa-solid fa-square-poll-vertical fs-1 text-muted opacity-25 mb-3"></i>
            <h5 class="text-muted fw-bold">No Results Loaded</h5>
            <p class="text-muted small mb-0">Choose an active exam term and class to display or process examination result summaries.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="resultsToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="resultsToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-toast"></button>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #resultLedgerArea, #resultLedgerArea * {
        visibility: visible;
    }
    #resultLedgerArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none !important;
        border: 0 !important;
    }
    .custom-table th, .custom-table td {
        border: 1px solid #ddd !important;
        padding: 8px !important;
        font-size: 11px !important;
    }
}
</style>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("resultsToast");
    const m = document.getElementById("resultsToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function compileResults() {
    const btn = document.getElementById("btnCompile");
    btn.disabled = true; btn.innerHTML = "Processing Rankings...";

    const fd = new FormData();
    fd.append("action", "generate_results");
    fd.append("csrf_token", "' . csrfToken() . '");
    fd.append("exam_type_id", "' . $selectedExam . '");
    fd.append("class_id", "' . $selectedClass . '");

    fetch("../../ajax/exams.php", { method: "POST", body: fd })
        .then(r => r.json())
        .then(data => {
            showToast(data.message, data.success);
            if(data.success) {
                setTimeout(() => location.reload(), 1000);
            } else {
                btn.disabled = false; btn.innerHTML = \'<i class=\"fa-solid fa-sync me-2\"></i>Calculate & Rank Results\';
            }
        })
        .catch(() => {
            showToast("System error processing results.", false);
            btn.disabled = false; btn.innerHTML = \'<i class=\"fa-solid fa-sync me-2\"></i>Calculate & Rank Results\';
        });
}

function exportToExcel() {
    let table = document.getElementById("resultTable");
    let html = table.outerHTML;
    let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
    let link = document.createElement("a");
    link.download = "result_ledger_class_' . $selectedClass . '_exam_' . $selectedExam . '.xls";
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
