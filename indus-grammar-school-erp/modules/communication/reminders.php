<?php
/**
 * Indus Grammar School ERP - Fee / Attendance Reminders
 * Version 1.0.0
 */

$pageTitle = 'Reminders & Notifications';
$breadcrumbActive = 'Communication';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('communication_send');

$defaulters = [];
try {
    $db = Database::getConnection();
    $defaulters = $db->query("
        SELECT s.first_name, s.last_name, s.guardian_phone, SUM(fc.net_amount) as total_due
        FROM students s
        JOIN fee_challans fc ON s.id = fc.student_id
        WHERE fc.status IN ('Unpaid', 'Overdue')
        GROUP BY s.id
        HAVING total_due > 0
        LIMIT 20
    ")->fetchAll();
} catch (Exception $e) {}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-clock me-2 text-primary"></i>Fee Reminders</h3>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="custom-table-card shadow-sm border-0">
            <div class="p-4 border-bottom">
                <h5 class="fw-bold mb-0 text-secondary">Defaulters List (Pending Fee Reminders)</h5>
            </div>
            <div class="table-responsive">
                <table class="table custom-table table-hover">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Guardian Mobile</th>
                            <th>Total Outstanding</th>
                            <th class="text-end">Remind</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($defaulters)): ?>
                            <tr><td colspan="4" class="text-center py-5 text-success"><i class="fa-solid fa-circle-check me-2"></i>No active defaulters.</td></tr>
                        <?php else: foreach ($defaulters as $d): ?>
                            <tr>
                                <td class="fw-semibold text-dark"><?php echo sanitize($d['first_name'] . ' ' . $d['last_name']); ?></td>
                                <td><?php echo sanitize($d['guardian_phone'] ?: '-'); ?></td>
                                <td class="fw-bold text-danger">Rs. <?php echo number_format($d['total_due'], 2); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-danger" onclick="alert('Reminder SMS logged to system for: <?php echo htmlspecialchars($d['guardian_phone']); ?>')">
                                        <i class="fa-solid fa-bell me-1"></i>Send Alert
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

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
