<?php
/**
 * Indus Grammar School ERP - Dashboard Page
 * Version 2.0.0 — Live KPI Integration
 */

$pageTitle = 'Dashboard';
$breadcrumbActive = 'Dashboard';
include_once __DIR__ . '/includes/header.php';

$currentUser = currentUser();
$userRole = $currentUser['role_code'] ?? '';

// ── Live KPI Data ──
$reportService = new ReportService();
$kpis = $reportService->getDashboardKPIs();

$financeData = Report::getFinanceSummary(date('Y-m-01'), date('Y-m-d'));
$classEnrollment = Report::getClasswiseEnrollment();

// Staff count
$staffCount = 0;
try {
    $db = Database::getConnection();
    $staffCount = (int)$db->query("SELECT COUNT(*) FROM staff WHERE status = 'Active'")->fetchColumn();
} catch (Exception $e) {}

// Audit Logs
$auditLogs = [];
try {
    $db = Database::getConnection();
    $stmt = $db->query("
        SELECT a.*, u.username, r.name as role_name
        FROM audit_logs a 
        LEFT JOIN users u ON a.user_id = u.id 
        LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY a.created_at DESC 
        LIMIT 5
    ");
    $auditLogs = $stmt->fetchAll();
} catch (Exception $e) {}

$academicYear = CURRENT_ACADEMIC_YEAR;
?>

<!-- Welcome Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 bg-primary text-white shadow-sm overflow-hidden" style="border-radius: 16px; background: linear-gradient(135deg, var(--primary-color) 0%, #6366f1 100%) !important;">
            <div class="card-body p-4 p-md-5 position-relative">
                <div class="row align-items-center">
                    <div class="col-md-8 position-relative" style="z-index: 2;">
                        <h2 class="fw-bold mb-2">Welcome Back, <?php echo sanitize(ucfirst($currentUser['username'])); ?>!</h2>
                        <p class="mb-0 opacity-75">Here is what's happening at Indus Grammar School today. You are logged in as a <strong><?php echo sanitize($currentUser['role_name']); ?></strong>.</p>
                    </div>
                    <div class="col-md-4 text-end d-none d-md-block opacity-25">
                        <i class="fa-solid fa-school" style="font-size: 8rem; margin-top: -20px;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic KPIs Grids based on User Roles -->
<div class="row g-4 mb-4">
    
    <!-- KPI CARD 1: Total Students -->
    <?php if ($userRole === ROLE_SUPER_ADMIN || $userRole === ROLE_SCHOOL_ADMIN): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="stat-card-label">Total Students</div>
                        <div class="stat-card-value"><?php echo number_format($kpis['total_students']); ?></div>
                    </div>
                    <div class="stat-card-icon bg-primary-soft">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                </div>
                <div class="small text-success">
                    <i class="fa-solid fa-circle-check me-1"></i> Active enrollment <span class="text-secondary"><?php echo $academicYear; ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPI CARD 2: Active Staff -->
    <?php if ($userRole === ROLE_SUPER_ADMIN || $userRole === ROLE_SCHOOL_ADMIN): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="stat-card-label">Active Staff</div>
                        <div class="stat-card-value"><?php echo $staffCount; ?></div>
                    </div>
                    <div class="stat-card-icon bg-info-soft">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
                <div class="small text-secondary">
                    Academic & Administrative
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPI CARD 3: Fee Collection (Monthly) -->
    <?php if ($userRole === ROLE_SUPER_ADMIN || $userRole === ROLE_ACCOUNTANT): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="stat-card-label">Collections (Monthly)</div>
                        <div class="stat-card-value">Rs. <?php echo number_format($kpis['monthly_collection']); ?></div>
                    </div>
                    <div class="stat-card-icon bg-success-soft">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </div>
                </div>
                <div class="small text-success">
                    <i class="fa-solid fa-circle-check me-1"></i> <?php echo date('F Y'); ?> <span class="text-secondary">so far</span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPI CARD 4: Outstanding Fees -->
    <?php if ($userRole === ROLE_SUPER_ADMIN || $userRole === ROLE_ACCOUNTANT): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="stat-card-label">Outstanding Fees</div>
                        <div class="stat-card-value">Rs. <?php echo number_format($financeData['outstanding']); ?></div>
                    </div>
                    <div class="stat-card-icon bg-danger-soft">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>
                <div class="small text-<?php echo $financeData['outstanding'] > 0 ? 'danger' : 'success'; ?>">
                    <?php if ($financeData['outstanding'] > 0): ?>
                        <i class="fa-solid fa-circle-exclamation me-1"></i> Action required <span class="text-secondary">from Cash Desk</span>
                    <?php else: ?>
                        <i class="fa-solid fa-circle-check me-1"></i> All fees collected
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPI CARD 5: Daily Attendance Rate -->
    <?php if ($userRole === ROLE_SUPER_ADMIN || $userRole === ROLE_SCHOOL_ADMIN): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="stat-card-label">Attendance Today</div>
                        <div class="stat-card-value"><?php echo $kpis['attendance_rate']; ?>%</div>
                    </div>
                    <div class="stat-card-icon bg-warning-soft">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                </div>
                <div class="small text-success">
                    <i class="fa-solid fa-check-double me-1"></i> <?php echo date('d M Y'); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPI CARD 6: Monthly Expenses -->
    <?php if ($userRole === ROLE_SUPER_ADMIN || $userRole === ROLE_ACCOUNTANT): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="stat-card-label">Expenses (Monthly)</div>
                        <div class="stat-card-value">Rs. <?php echo number_format($kpis['monthly_expense']); ?></div>
                    </div>
                    <div class="stat-card-icon bg-danger-soft">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                    </div>
                </div>
                <div class="small text-secondary">
                    <?php echo date('F Y'); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Interactive Analytics Dashboard Charts -->
<div class="row g-4 mb-4">
    <!-- Chart block 1: Class-wise Enrollment -->
    <?php if ($userRole === ROLE_SUPER_ADMIN || $userRole === ROLE_SCHOOL_ADMIN): ?>
        <div class="<?php echo ($userRole === ROLE_SUPER_ADMIN) ? 'col-lg-6' : 'col-12'; ?>">
            <div class="chart-card">
                <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-chart-bar me-2"></i>Class-wise Enrollment</h5>
                <div style="height: 320px; position: relative;">
                    <canvas id="enrollmentChart"></canvas>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Chart block 2: Fee & Revenue Breakdown -->
    <?php if ($userRole === ROLE_SUPER_ADMIN || $userRole === ROLE_ACCOUNTANT): ?>
        <div class="<?php echo ($userRole === ROLE_SUPER_ADMIN) ? 'col-lg-6' : 'col-12'; ?>">
            <div class="chart-card">
                <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-chart-column me-2"></i>Financial Summary (<?php echo date('F Y'); ?>)</h5>
                <div style="height: 320px; position: relative;">
                    <canvas id="financeChart"></canvas>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Auditing and Action Panels -->
<div class="row g-4">
    <!-- Recent Activity Log (Visible to Super Admin/Owner only) -->
    <?php if ($userRole === ROLE_SUPER_ADMIN): ?>
        <div class="col-lg-8">
            <div class="custom-table-card">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-fingerprint me-2"></i>Security Audit Trail (Real-Time)</h5>
                    <a href="<?php echo APP_URL; ?>/modules/administration/logs.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table custom-table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($auditLogs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No security logs recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($auditLogs as $log): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo sanitize($log['username'] ?? 'System / Guest'); ?></div>
                                            <div class="text-muted small" style="font-size: 0.75rem;"><?php echo sanitize($log['role_name'] ?? 'Visitor'); ?></div>
                                        </td>
                                        <td>
                                            <?php
                                            $action = $log['action'];
                                            $badgeClass = 'bg-secondary';
                                            if (str_contains($action, 'Success') || str_contains($action, 'Login') || str_contains($action, 'Paid') || str_contains($action, 'Saved')) {
                                                $badgeClass = 'badge-soft-success';
                                            } elseif (str_contains($action, 'Failed') || str_contains($action, 'Blocked') || str_contains($action, 'Delete')) {
                                                $badgeClass = 'badge-soft-danger';
                                            }
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo sanitize($action); ?></span>
                                        </td>
                                        <td><span class="text-muted"><?php echo sanitize(mb_substr($log['description'], 0, 60)) . (mb_strlen($log['description']) > 60 ? '…' : ''); ?></span></td>
                                        <td><code class="text-muted"><?php echo sanitize($log['ip_address'] ?? '-'); ?></code></td>
                                        <td>
                                            <div class="small text-secondary"><?php echo date('M d, Y', strtotime($log['created_at'])); ?></div>
                                            <div class="text-muted small" style="font-size: 0.75rem;"><?php echo date('h:i A', strtotime($log['created_at'])); ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- System Context / Calendar -->
    <div class="<?php echo ($userRole === ROLE_SUPER_ADMIN) ? 'col-lg-4' : 'col-12'; ?>">
        <div class="stat-card">
            <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-clock-rotate-left me-2"></i>Academic Information</h5>
            
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3 bg-transparent">
                    <div>
                        <div class="fw-semibold text-dark">Current Term</div>
                        <div class="text-muted small">Academic session year</div>
                    </div>
                    <span class="badge bg-dark rounded-pill"><?php echo $academicYear; ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3 bg-transparent">
                    <div>
                        <div class="fw-semibold text-dark">Term Status</div>
                        <div class="text-muted small">Mid-term examinations upcoming</div>
                    </div>
                    <span class="badge bg-warning text-dark rounded-pill">Active</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3 bg-transparent">
                    <div>
                        <div class="fw-semibold text-dark">ERP Status</div>
                        <div class="text-muted small">System integrity & databases</div>
                    </div>
                    <span class="badge bg-success rounded-pill">Operational</span>
                </li>
            </ul>

            <div class="alert bg-primary-soft text-primary border-0 mt-4 mb-0 d-flex gap-3 align-items-start" style="border-radius: 8px;">
                <i class="fa-solid fa-lightbulb mt-1" style="font-size: 1.2rem;"></i>
                <div class="small">
                    <strong>Tip:</strong> You can navigate between student management, accounting cash sheets, and settings using the sidebar links panel.
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// ── Chart Data (Dynamic) ──
$enrollmentLabels = array_map(fn($c) => $c['class_name'] . ' ' . $c['section'], $classEnrollment);
$enrollmentData = array_map(fn($c) => $c['student_count'], $classEnrollment);

$extraJS = '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
';

// Enrollment Chart
if ($userRole === ROLE_SUPER_ADMIN || $userRole === ROLE_SCHOOL_ADMIN) {
    $extraJS .= '
    const ctxEnrollment = document.getElementById("enrollmentChart");
    if (ctxEnrollment) {
        new Chart(ctxEnrollment, {
            type: "bar",
            data: {
                labels: ' . json_encode($enrollmentLabels) . ',
                datasets: [{
                    label: "Students",
                    data: ' . json_encode($enrollmentData) . ',
                    backgroundColor: "#4f46e5",
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: "#f1f5f9" } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
    ';
}

// Financial Chart
if ($userRole === ROLE_SUPER_ADMIN || $userRole === ROLE_ACCOUNTANT) {
    $extraJS .= '
    const ctxFinance = document.getElementById("financeChart");
    if (ctxFinance) {
        new Chart(ctxFinance, {
            type: "doughnut",
            data: {
                labels: ["Collections", "Expenses", "Outstanding"],
                datasets: [{
                    data: [' . $financeData['collections'] . ', ' . $financeData['expenses'] . ', ' . $financeData['outstanding'] . '],
                    backgroundColor: ["#16a34a", "#dc2626", "#f59e0b"],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: "bottom" },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ": Rs. " + context.parsed.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    ';
}

$extraJS .= '
});
</script>
';

include_once __DIR__ . '/includes/footer.php';
?>
