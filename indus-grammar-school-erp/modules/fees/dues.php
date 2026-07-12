<?php
/**
 * Indus Grammar School ERP - Pending Dues Ledger List
 * Version 4.0.0
 */

$pageTitle = 'Outstanding Dues';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('fee_view');

// Fetch outstanding defaulters
$defaulters = [];
$totalDuesSum = 0.00;

try {
    $db = Database::getConnection();
    $sql = "
        SELECT s.id, s.admission_no, s.first_name, s.last_name, c.class_name, c.section,
               GROUP_CONCAT(fl.month ORDER BY fl.due_date ASC SEPARATOR ', ') as pending_months,
               SUM(fl.total_payable - fl.paid_amount) as outstanding_balance
        FROM students s
        JOIN fee_ledger fl ON s.id = fl.student_id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE s.status = 'Active' AND fl.status IN ('Pending', 'Partial')
        GROUP BY s.id
        HAVING outstanding_balance > 0
        ORDER BY outstanding_balance DESC
    ";
    $defaulters = $db->query($sql)->fetchAll();
    $totalDuesSum = array_sum(array_column($defaulters, 'outstanding_balance'));
} catch (Exception $e) {
    error_log("dues.php query error: " . $e->getMessage());
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>Outstanding Dues List</h3>
    </div>
</div>

<!-- Dues Table -->
<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-secondary">Defaulters Ledger</h5>
        <span class="badge bg-danger-soft text-danger px-3 py-2 rounded-pill fs-6 fw-bold">
            Total Outstanding: Rs. <?php echo number_format($totalDuesSum, 2); ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Admission Number</th>
                    <th>Student Name</th>
                    <th>Class</th>
                    <th>Pending Months</th>
                    <th class="text-danger fw-bold">Outstanding Balance</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($defaulters)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-success fw-bold">
                            <i class="fa-solid fa-circle-check fs-2 mb-2 d-block"></i>No outstanding balances found!
                        </td>
                    </tr>
                <?php else: foreach ($defaulters as $d): ?>
                    <tr>
                        <td><code><?php echo sanitize($d['admission_no']); ?></code></td>
                        <td class="fw-bold text-dark"><?php echo sanitize($d['first_name'] . ' ' . $d['last_name']); ?></td>
                        <td><?php echo sanitize($d['class_name'] . ' - ' . $d['section']); ?></td>
                        <td>
                            <span class="text-muted small" title="<?php echo sanitize($d['pending_months']); ?>">
                                <?php echo sanitize(strlen($d['pending_months']) > 60 ? substr($d['pending_months'], 0, 57) . '...' : $d['pending_months']); ?>
                            </span>
                        </td>
                        <td class="fw-bold text-danger fs-6">Rs. <?php echo number_format($d['outstanding_balance'], 2); ?></td>
                        <td class="text-end">
                            <?php if (hasPermission('fee_collect')): ?>
                                <a href="collection.php?student_id=<?php echo $d['id']; ?>" class="btn btn-sm btn-success px-3 shadow-sm fw-bold">
                                    <i class="fa-solid fa-hand-holding-dollar me-1"></i>Collect Fee
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
