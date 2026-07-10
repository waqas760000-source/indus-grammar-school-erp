<?php
/**
 * Indus Grammar School ERP - Daily Collections list
 * Version 1.0.0
 */

$pageTitle = 'Collections Today';
$breadcrumbActive = 'Cash Desk';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$register = Cash::getOpenRegister();
$collections = [];

if ($register) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT fc.*, s.first_name, s.last_name, s.admission_no
            FROM fee_collections fc
            JOIN students s ON fc.student_id = s.id
            WHERE fc.payment_date = :dt
            ORDER BY fc.created_at DESC
        ");
        $stmt->execute(['dt' => $register['date']]);
        $collections = $stmt->fetchAll();
    } catch (Exception $e) {}
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-receipt me-2 text-primary"></i>Fee Collections Today</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="dashboard.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>Back to Desk</a>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Student Name</th>
                    <th>Admission No</th>
                    <th>Payment Method</th>
                    <th class="text-end">Amount Paid</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($collections)): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">No fee collections recorded today.</td></tr>
                <?php else: foreach ($collections as $c): ?>
                    <tr>
                        <td class="fw-bold text-dark"><?php echo sanitize($c['receipt_no']); ?></td>
                        <td class="fw-semibold"><?php echo sanitize($c['first_name'] . ' ' . $c['last_name']); ?></td>
                        <td><code class="text-muted"><?php echo sanitize($c['admission_no']); ?></code></td>
                        <td><span class="badge bg-light text-dark border"><?php echo sanitize($c['payment_method']); ?></span></td>
                        <td class="text-end fw-bold text-success">Rs. <?php echo number_format($c['amount_paid'], 2); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
