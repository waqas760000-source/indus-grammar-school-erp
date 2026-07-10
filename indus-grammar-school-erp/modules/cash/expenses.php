<?php
/**
 * Indus Grammar School ERP - Expenses Today list
 * Version 1.0.0
 */

$pageTitle = 'Cash Desk Expenses';
$breadcrumbActive = 'Cash Desk';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$register = Cash::getOpenRegister();
$expenses = [];

if ($register) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM expenses
            WHERE expense_date = :dt
            ORDER BY created_at DESC
        ");
        $stmt->execute(['dt' => $register['date']]);
        $expenses = $stmt->fetchAll();
    } catch (Exception $e) {}
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-money-bill-transfer me-2 text-primary"></i>Daily Office Expenses</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="dashboard.php" class="btn btn-outline-secondary px-3 me-2"><i class="fa-solid fa-arrow-left me-2"></i>Back to Desk</a>
        <a href="../fees/expenses.php" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Record Expense</a>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Paid To</th>
                    <th>Ref / Receipt</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">No expenses recorded today.</td></tr>
                <?php else: foreach ($expenses as $e): ?>
                    <tr>
                        <td><span class="badge bg-light text-dark border"><?php echo sanitize($e['category']); ?></span></td>
                        <td class="fw-semibold text-dark"><?php echo sanitize($e['description']); ?></td>
                        <td><?php echo sanitize($e['paid_to'] ?: '-'); ?></td>
                        <td><code><?php echo sanitize($e['receipt_reference'] ?: '-'); ?></code></td>
                        <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($e['amount'], 2); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
