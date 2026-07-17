<?php
/**
 * Indus Grammar School ERP - Merit Rankings Registry
 * Version 4.0.0
 */

$pageTitle = 'Class Merit Rankings';
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

$rankings = [];
if ($selectedExam > 0 && $selectedClass > 0) {
    // Fetch rankings from positions table, ordered by position_no ascending
    $stmt = $db->prepare("
        SELECT p.*, st.first_name, st.last_name, st.admission_no
        FROM positions p
        JOIN students st ON p.student_id = st.id
        WHERE p.exam_type_id = :etid AND p.class_id = :cid
        ORDER BY p.position_no ASC, st.first_name ASC
    ");
    $stmt->execute(['etid' => $selectedExam, 'cid' => $selectedClass]);
    $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-list-ol text-primary me-2"></i>Class Positions</h3>
        <p class="text-muted small mb-0">Display rank listings, class averages, and top achievers.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if ($selectedExam > 0 && $selectedClass > 0): ?>
            <button class="btn btn-outline-success px-4" onclick="exportToExcel()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
            <button class="btn btn-outline-primary px-4 ms-2" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Rankings</button>
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
                <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Display</button>
            </div>
        </form>
    </div>
</div>

<!-- Rankings Output -->
<?php if ($selectedExam > 0 && $selectedClass > 0): ?>
    <div class="card border-0 shadow-sm mb-5" style="border-radius:12px;" id="rankingsArea">
        <div class="card-header bg-white border-0 pt-4 px-4 text-center d-none d-print-block border-bottom pb-3">
            <h3 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h3>
            <h5 class="text-secondary fw-semibold mb-1">Merit Position List</h5>
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
                <table class="table custom-table table-hover align-middle mb-0" id="positionsTable">
                    <thead>
                        <tr>
                            <th width="100" class="text-center">Position</th>
                            <th>Admission No.</th>
                            <th>Student Name</th>
                            <th class="text-center">Percentage</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rankings)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No positions calculated. Go to "Class Results" and calculate results for this class first.</td></tr>
                        <?php else: foreach ($rankings as $r): ?>
                            <tr>
                                <td class="text-center">
                                    <?php 
                                        $rank = (int)$r['position_no'];
                                        if ($r['status'] === 'Pass') {
                                            if ($rank === 1) {
                                                echo '<span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold" style="background-color: #ffd700 !important; color: #000 !important;"><i class="fa-solid fa-trophy me-1"></i>1st</span>';
                                            } else if ($rank === 2) {
                                                echo '<span class="badge bg-secondary text-white px-3 py-1 rounded-pill fw-bold" style="background-color: #c0c0c0 !important;"><i class="fa-solid fa-medal me-1"></i>2nd</span>';
                                            } else if ($rank === 3) {
                                                echo '<span class="badge bg-danger text-white px-3 py-1 rounded-pill fw-bold" style="background-color: #cd7f32 !important;"><i class="fa-solid fa-award me-1"></i>3rd</span>';
                                            } else {
                                                echo '<span class="badge bg-light text-dark border px-3 py-1 rounded-pill fw-semibold">' . $rank . 'th</span>';
                                            }
                                        } else {
                                            echo '<span class="badge bg-light text-muted border px-3 py-1 rounded-pill">—</span>';
                                        }
                                    ?>
                                </td>
                                <td><code class="text-muted"><?php echo htmlspecialchars($r['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo number_format($r['percentage'], 1); ?>%</td>
                                <td class="text-center fw-bold text-secondary fs-5"><?php echo htmlspecialchars($r['grade']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $r['status'] === 'Pass' ? 'success' : 'danger'; ?>-soft px-3 py-1 rounded-pill fw-semibold"><?php echo $r['status']; ?></span>
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
            <i class="fa-solid fa-list-ol fs-1 text-muted opacity-25 mb-3"></i>
            <h5 class="text-muted fw-bold">No Position List Loaded</h5>
            <p class="text-muted small mb-0">Choose an active exam term and class to display student merit standings.</p>
        </div>
    </div>
<?php endif; ?>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #rankingsArea, #rankingsArea * {
        visibility: visible;
    }
    #rankingsArea {
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
function exportToExcel() {
    let table = document.getElementById("positionsTable");
    let html = table.outerHTML;
    let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
    let link = document.createElement("a");
    link.download = "merit_positions_class_' . $selectedClass . '_exam_' . $selectedExam . '.xls";
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
