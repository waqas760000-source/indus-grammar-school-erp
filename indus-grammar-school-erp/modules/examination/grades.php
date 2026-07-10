<?php
/**
 * Indus Grammar School ERP - Grade Scales View
 * Version 1.0.0
 */

$pageTitle = 'Grade Scales';
$breadcrumbActive = 'Examination';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('academic_view');

// Fetch grade scales from database
$scales = [];
try {
    $db = Database::getConnection();
    $scales = $db->query("SELECT * FROM grade_scales ORDER BY min_percentage DESC")->fetchAll();
} catch (Exception $e) {}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-graduation-cap me-2 text-primary"></i>Grade Scales</h3>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-0 text-secondary">Grading Systems</h5>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Grade</th>
                    <th class="text-center">Min Percentage</th>
                    <th class="text-center">Max Percentage</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($scales)): ?>
                    <tr><td colspan="4" class="text-center py-5 text-muted">No grade scales found.</td></tr>
                <?php else: foreach ($scales as $s): ?>
                    <tr>
                        <td class="fw-bold text-dark fs-5"><?php echo sanitize($s['grade']); ?></td>
                        <td class="text-center fw-semibold text-primary"><?php echo number_format($s['min_percentage'], 2); ?>%</td>
                        <td class="text-center fw-semibold text-secondary"><?php echo number_format($s['max_percentage'], 2); ?>%</td>
                        <td class="fw-semibold"><?php echo sanitize($s['remarks']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
