<?php
/**
 * Indus Grammar School ERP - Outstanding Dues / Defaulters List
 * Version 1.0.0
 */

$pageTitle = 'Outstanding Dues';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('fee_view');

$classes = SchoolClass::all();
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$defaulters = [];
$totalDues = 0;

if ($selectedClass > 0) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT s.id, s.first_name, s.last_name, s.admission_no, s.guardian_phone,
                   COUNT(fc.id) as unpaid_months,
                   SUM(fc.net_amount) as total_due
            FROM students s
            JOIN fee_challans fc ON s.id = fc.student_id
            WHERE s.class_id = :cid AND s.status = 'Active' AND fc.status IN ('Unpaid', 'Overdue')
            GROUP BY s.id
            HAVING total_due > 0
            ORDER BY total_due DESC
        ");
        $stmt->execute(['cid' => $selectedClass]);
        $defaulters = $stmt->fetchAll();
        $totalDues = array_sum(array_column($defaulters, 'total_due'));
    } catch (Exception $e) {
        error_log("dues.php error: " . $e->getMessage());
    }
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>Outstanding Dues</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if (!empty($defaulters)): ?>
            <!-- Placeholder for SMS sending feature (Phase 7 Communication module integration) -->
            <button class="btn btn-outline-primary px-3" onclick="alert('SMS feature will be available in Phase 7.')">
                <i class="fa-solid fa-comment-sms me-2"></i>Send Reminders
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Select Class</label>
                <select class="form-select" name="class_id" onchange="this.form.submit()">
                    <option value="">— Choose Class —</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($selectedClass == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selectedClass > 0): ?>
            <div class="col-md-8 text-md-end">
                <h5 class="fw-bold text-danger mb-0 mt-3 mt-md-0">Total Dues: Rs. <?php echo number_format($totalDues, 2); ?></h5>
                <small class="text-muted"><?php echo count($defaulters); ?> Defaulters</small>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($selectedClass > 0): ?>
    <div class="custom-table-card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Admission No</th>
                        <th>Guardian Phone</th>
                        <th class="text-center">Pending Months</th>
                        <th class="text-end">Total Due Amount</th>
                        <?php if (hasPermission('fee_collect')): ?>
                        <th class="text-end">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($defaulters)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-success fw-bold"><i class="fa-solid fa-check-circle me-2"></i>No outstanding dues for this class!</td></tr>
                    <?php else: foreach ($defaulters as $d): ?>
                        <tr>
                            <td class="fw-semibold text-dark"><?php echo sanitize($d['first_name'] . ' ' . $d['last_name']); ?></td>
                            <td><code class="text-muted"><?php echo sanitize($d['admission_no']); ?></code></td>
                            <td><?php echo sanitize($d['guardian_phone'] ?: '-'); ?></td>
                            <td class="text-center fw-bold text-warning"><?php echo $d['unpaid_months']; ?></td>
                            <td class="text-end fw-bold text-danger fs-5">Rs. <?php echo number_format($d['total_due'], 2); ?></td>
                            <?php if (hasPermission('fee_collect')): ?>
                            <td class="text-end">
                                <a href="collection.php?student_id=<?php echo $d['id']; ?>" class="btn btn-sm btn-success">
                                    <i class="fa-solid fa-hand-holding-dollar me-1"></i>Collect
                                </a>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm" style="border-radius:12px; min-height: 400px;">
        <div class="card-body d-flex flex-column justify-content-center align-items-center text-center">
            <i class="fa-solid fa-filter fs-1 text-muted opacity-25 mb-3"></i>
            <h5 class="text-muted">Select a class to view defaulters.</h5>
        </div>
    </div>
<?php endif; ?>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
