<?php
/**
 * Indus Grammar School ERP - Examination Reports
 * Version 1.0.0
 */

$pageTitle = 'Examination Performance Report';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('report_view');

// Fetch recent exams list for filtering
$exams = Exam::all();
$selectedExam = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$results = [];

if ($selectedExam > 0) {
    try {
        $db = Database::getConnection();
        // Class-wise pass/fail counts
        $stmt = $db->prepare("
            SELECT c.class_name, c.section,
                   SUM(CASE WHEN r.status = 'Present' AND (r.marks_obtained / s.total_marks * 100) >= 40 THEN 1 ELSE 0 END) as passed,
                   SUM(CASE WHEN r.status = 'Present' AND (r.marks_obtained / s.total_marks * 100) < 40 THEN 1 ELSE 0 END) as failed,
                   COUNT(r.id) as total_entries
            FROM results r
            JOIN subjects s ON r.subject_id = s.id
            JOIN classes c ON s.class_id = c.id
            WHERE r.exam_id = :eid
            GROUP BY c.id
        ");
        $stmt->execute(['eid' => $selectedExam]);
        $results = $stmt->fetchAll();
    } catch (Exception $e) {}
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-graduation-cap me-2 text-primary"></i>Exam Performance Report</h3>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label small fw-semibold text-muted">Select Exam Term</label>
                <select class="form-select" name="exam_id" onchange="this.form.submit()" required>
                    <option value="">— Select Exam —</option>
                    <?php foreach ($exams as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo ($selectedExam == $e['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($e['exam_name']); ?> (<?php echo sanitize($e['academic_year']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-sync me-2"></i>Generate Report</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedExam > 0): ?>
<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Class</th>
                    <th class="text-center">Total Graded Papers</th>
                    <th class="text-center text-success">Passed (>=40%)</th>
                    <th class="text-center text-danger">Failed (<40%)</th>
                    <th class="text-center">Passing Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">No marks entries recorded yet for this exam term.</td></tr>
                <?php else: foreach ($results as $row): 
                    $rate = $row['total_entries'] > 0 ? ($row['passed'] / $row['total_entries']) * 100 : 0;
                ?>
                    <tr>
                        <td class="fw-semibold text-dark"><?php echo sanitize($row['class_name'] . ' - ' . $row['section']); ?></td>
                        <td class="text-center fw-bold"><?php echo $row['total_entries']; ?></td>
                        <td class="text-center fw-bold text-success"><?php echo $row['passed']; ?></td>
                        <td class="text-center fw-bold text-danger"><?php echo $row['failed']; ?></td>
                        <td class="text-center fw-bold fs-6 text-<?php echo $rate >= 60 ? 'success' : 'warning'; ?>">
                            <?php echo round($rate, 1); ?>%
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
    <div class="card border-0 shadow-sm" style="border-radius:12px; height: 250px;">
        <div class="card-body d-flex flex-column align-items-center justify-content-center text-muted">
            <i class="fa-solid fa-graduation-cap fs-1 opacity-25 mb-3"></i>
            <h5>Select an exam term to load class performance reports.</h5>
        </div>
    </div>
<?php endif; ?>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
