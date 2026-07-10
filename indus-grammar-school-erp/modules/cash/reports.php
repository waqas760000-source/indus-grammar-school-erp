<?php
/**
 * Indus Grammar School ERP - Cash Flow History Logs
 * Version 1.0.0
 */

$pageTitle = 'Cash Registers History';
$breadcrumbActive = 'Cash Desk';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

// Fetch historical cash registers
$registers = [];
try {
    $db = Database::getConnection();
    $registers = $db->query("
        SELECT cr.*, u.username as closed_by_user
        FROM cash_register cr
        LEFT JOIN users u ON cr.closed_by = u.id
        ORDER BY cr.date DESC
        LIMIT 50
    ")->fetchAll();
} catch (Exception $e) {}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Cash Registers History</h3>
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
                    <th>Register Date</th>
                    <th class="text-end">Opening Balance</th>
                    <th class="text-end text-success">Collections (+)</th>
                    <th class="text-end text-danger">Expenses (-)</th>
                    <th class="text-end">Closing Balance</th>
                    <th>Status</th>
                    <th>Closed By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($registers)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No historical registers found.</td></tr>
                <?php else: foreach ($registers as $r): 
                    $sysClosing = $r['opening_balance'] + $r['total_collections'] - $r['total_expenses'];
                    $actClosing = $r['closing_balance'] !== null ? $r['closing_balance'] : $sysClosing;
                    $variance = $r['closing_balance'] !== null ? ($r['closing_balance'] - $sysClosing) : 0;
                ?>
                    <tr>
                        <td class="fw-semibold text-dark"><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                        <td class="text-end">Rs. <?php echo number_format($r['opening_balance'], 2); ?></td>
                        <td class="text-end text-success">+ Rs. <?php echo number_format($r['total_collections'], 2); ?></td>
                        <td class="text-end text-danger">- Rs. <?php echo number_format($r['total_expenses'], 2); ?></td>
                        <td class="text-end fw-bold">
                            Rs. <?php echo number_format($actClosing, 2); ?>
                            <?php if ($variance != 0): ?>
                                <br><small class="text-danger">Variance: Rs. <?php echo number_format($variance, 2); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $r['status'] === 'Open' ? 'success' : 'secondary'; ?>-soft px-3 py-2 rounded-pill">
                                <?php echo sanitize($r['status']); ?>
                            </span>
                        </td>
                        <td><span class="text-muted small"><?php echo sanitize($r['closed_by_user'] ?: '-'); ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
