<?php
/**
 * Indus Grammar School ERP - Student Summary Report Dashboard
 * Version 2.0.0
 */

$pageTitle = 'Student Summary Report';
$breadcrumbActive = 'Student Registration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

// Filter parameters
$search_session = sanitize($_GET['search_session'] ?? '');
$search_class = (int)($_GET['search_class'] ?? 0);
$search_section = sanitize($_GET['search_section'] ?? '');
$search_status = sanitize($_GET['search_status'] ?? '');
$search_academic_type = sanitize($_GET['search_academic_type'] ?? '');

$classes = [];
try {
    $classes = $db->query("SELECT * FROM classes ORDER BY class_name ASC")->fetchAll();
} catch (Exception $e) {}

// Build dynamic WHERE clause
$where = " WHERE 1=1";
$params = [];

if ($search_session) {
    $where .= " AND d.academic_session = :session";
    $params['session'] = $search_session;
}
if ($search_class > 0) {
    $where .= " AND s.class_id = :class_id";
    $params['class_id'] = $search_class;
}
if ($search_section) {
    $where .= " AND c.section = :section";
    $params['section'] = $search_section;
}
if ($search_status) {
    $where .= " AND s.status = :status";
    $params['status'] = $search_status;
}
if ($search_academic_type) {
    $where .= " AND s.academic_type = :academic_type";
    $params['academic_type'] = $search_academic_type;
}

// Calculate metrics
$totalStudents = 0;
$totalBoys = 0;
$totalGirls = 0;
$totalActive = 0;
$totalInactive = 0;
$totalNewAdmissions = 0;
$totalAlumni = 0;

$classBreakdown = [];
$genderChartData = ['Male' => 0, 'Female' => 0, 'Other' => 0];
$classChartLabels = [];
$classChartCounts = [];
$admissionTrendData = [];

try {
    // Total matching Students
    $stmtTotal = $db->prepare("
        SELECT COUNT(*) 
        FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        LEFT JOIN student_registration_details d ON s.id = d.student_id 
        $where
    ");
    $stmtTotal->execute($params);
    $totalStudents = (int)$stmtTotal->fetchColumn();

    // Boys
    $stmtBoys = $db->prepare("
        SELECT COUNT(*) FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        LEFT JOIN student_registration_details d ON s.id = d.student_id 
        $where AND s.gender = 'Male'
    ");
    $stmtBoys->execute($params);
    $totalBoys = (int)$stmtBoys->fetchColumn();
    $genderChartData['Male'] = $totalBoys;

    // Girls
    $stmtGirls = $db->prepare("
        SELECT COUNT(*) FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        LEFT JOIN student_registration_details d ON s.id = d.student_id 
        $where AND s.gender = 'Female'
    ");
    $stmtGirls->execute($params);
    $totalGirls = (int)$stmtGirls->fetchColumn();
    $genderChartData['Female'] = $totalGirls;

    // Active
    $stmtActive = $db->prepare("
        SELECT COUNT(*) FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        LEFT JOIN student_registration_details d ON s.id = d.student_id 
        $where AND s.status = 'Active'
    ");
    $stmtActive->execute($params);
    $totalActive = (int)$stmtActive->fetchColumn();

    // Inactive
    $stmtInactive = $db->prepare("
        SELECT COUNT(*) FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        LEFT JOIN student_registration_details d ON s.id = d.student_id 
        $where AND s.status = 'Inactive'
    ");
    $stmtInactive->execute($params);
    $totalInactive = (int)$stmtInactive->fetchColumn();

    // New Admissions (registered in 2026/current academic session)
    $stmtNew = $db->prepare("
        SELECT COUNT(*) FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        LEFT JOIN student_registration_details d ON s.id = d.student_id 
        $where AND s.enrollment_date >= '2026-01-01'
    ");
    $stmtNew->execute($params);
    $totalNewAdmissions = (int)$stmtNew->fetchColumn();

    // Alumni/Transferred (Left status or graduated status)
    $stmtAlumni = $db->prepare("
        SELECT COUNT(*) FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        LEFT JOIN student_registration_details d ON s.id = d.student_id 
        $where AND (s.status = 'Inactive' OR s.status = 'Suspended')
    ");
    $stmtAlumni->execute($params);
    $totalAlumni = (int)$stmtAlumni->fetchColumn();

    // Class wise & Section wise Breakdown
    $stmtBreakdown = $db->prepare("
        SELECT c.class_name, c.section, COUNT(s.id) as total,
               SUM(CASE WHEN s.gender = 'Male' THEN 1 ELSE 0 END) as boys,
               SUM(CASE WHEN s.gender = 'Female' THEN 1 ELSE 0 END) as girls
        FROM classes c
        LEFT JOIN students s ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        GROUP BY c.id
        ORDER BY c.class_name ASC, c.section ASC
    ");
    // Remove class/section specific filters to get class breakdown totals
    $stmtBreakdown->execute($params);
    $classBreakdown = $stmtBreakdown->fetchAll();

    foreach ($classBreakdown as $cb) {
        $classChartLabels[] = $cb['class_name'] . ' - ' . $cb['section'];
        $classChartCounts[] = (int)$cb['total'];
    }

    // Admission trend stats (grouped by month of enrollment date)
    $stmtTrend = $db->prepare("
        SELECT DATE_FORMAT(s.enrollment_date, '%b %Y') as month_year, COUNT(s.id) as count
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        GROUP BY DATE_FORMAT(s.enrollment_date, '%Y-%m')
        ORDER BY s.enrollment_date ASC
        LIMIT 12
    ");
    $stmtTrend->execute($params);
    $admissionTrendData = $stmtTrend->fetchAll();

} catch (Exception $e) {
    error_log("Summary Report calculations error: " . $e->getMessage());
}
?>

<!-- Include Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Title Header -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Student Summary Report</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-outline-secondary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Report</button>
        <button class="btn btn-outline-success px-3 ms-1" onclick="exportExcel()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
    </div>
</div>

<!-- Filters Panel -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Summary Dashboard</h6>
    <form method="GET" action="summary_report.php" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Academic Session</label>
            <select class="form-select form-select-sm" name="search_session">
                <option value="">All</option>
                <option value="2026-2027" <?php echo ($search_session === '2026-2027') ? 'selected' : ''; ?>>2026-2027</option>
                <option value="2025-2026" <?php echo ($search_session === '2025-2026') ? 'selected' : ''; ?>>2025-2026</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Class</label>
            <select class="form-select form-select-sm" name="search_class">
                <option value="">All</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($search_class == $c['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Academic Type</label>
            <select class="form-select form-select-sm" name="search_academic_type">
                <option value="">All</option>
                <option value="School" <?php echo ($search_academic_type === 'School') ? 'selected' : ''; ?>>School</option>
                <option value="Academy" <?php echo ($search_academic_type === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                <option value="School + Academy" <?php echo ($search_academic_type === 'School + Academy') ? 'selected' : ''; ?>>School + Academy</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Status</label>
            <select class="form-select form-select-sm" name="search_status">
                <option value="">All</option>
                <option value="Active" <?php echo ($search_status === 'Active') ? 'selected' : ''; ?>>Active</option>
                <option value="Inactive" <?php echo ($search_status === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-sm btn-primary w-100 py-2">Filter</button>
        </div>
    </form>
</div>

<!-- Metrics Cards Summary (Dashboard Style) -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border border-light shadow-sm bg-white p-3 text-center" style="border-radius:10px;">
            <span class="text-muted small fw-semibold">Total Strength</span>
            <h3 class="fw-bold text-dark mb-0 mt-1"><?php echo $totalStudents; ?></h3>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border border-light shadow-sm bg-white p-3 text-center" style="border-radius:10px;">
            <span class="text-muted small fw-semibold">Active Enrolled</span>
            <h3 class="fw-bold text-success mb-0 mt-1"><?php echo $totalActive; ?></h3>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border border-light shadow-sm bg-white p-3 text-center" style="border-radius:10px;">
            <span class="text-muted small fw-semibold">Boys</span>
            <h3 class="fw-bold text-primary mb-0 mt-1"><?php echo $totalBoys; ?></h3>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border border-light shadow-sm bg-white p-3 text-center" style="border-radius:10px;">
            <span class="text-muted small fw-semibold">Girls</span>
            <h3 class="fw-bold text-danger mb-0 mt-1"><?php echo $totalGirls; ?></h3>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card border border-light shadow-sm bg-white p-3 text-center" style="border-radius:10px;">
            <span class="text-muted small fw-semibold">New Admissions (2026 Session)</span>
            <h4 class="fw-bold text-info mb-0 mt-1"><?php echo $totalNewAdmissions; ?></h4>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card border border-light shadow-sm bg-white p-3 text-center" style="border-radius:10px;">
            <span class="text-muted small fw-semibold">Inactive / Transferred</span>
            <h4 class="fw-bold text-secondary mb-0 mt-1"><?php echo $totalInactive; ?></h4>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card border border-light shadow-sm bg-white p-3 text-center" style="border-radius:10px;">
            <span class="text-muted small fw-semibold">Alumni / Left Strength</span>
            <h4 class="fw-bold text-dark mb-0 mt-1"><?php echo $totalAlumni; ?></h4>
        </div>
    </div>
</div>

<!-- Charts Grid -->
<div class="row g-4 mb-4">
    <!-- Chart 1: Gender Distribution -->
    <div class="col-md-6 col-lg-4">
        <div class="card border border-light shadow-sm bg-white p-4" style="border-radius: 12px; height: 100%;">
            <h6 class="fw-bold text-secondary mb-3 text-center">Gender Ratio</h6>
            <div style="max-height: 250px; display: flex; justify-content: center;">
                <canvas id="genderChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 2: Class Distribution -->
    <div class="col-md-6 col-lg-4">
        <div class="card border border-light shadow-sm bg-white p-4" style="border-radius: 12px; height: 100%;">
            <h6 class="fw-bold text-secondary mb-3 text-center">Class Enrollment Strength</h6>
            <div style="max-height: 250px; display: flex; justify-content: center;">
                <canvas id="classChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 3: Admission Trend -->
    <div class="col-12 col-lg-4">
        <div class="card border border-light shadow-sm bg-white p-4" style="border-radius: 12px; height: 100%;">
            <h6 class="fw-bold text-secondary mb-3 text-center">Registration Admission Trend</h6>
            <div style="max-height: 250px; display: flex; justify-content: center;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Roster Breakdown Table Card -->
<div class="card border border-light shadow-sm bg-white p-4" style="border-radius:12px;">
    <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-table-list text-primary me-2"></i>Class Wise Enrollment Breakdown</h5>
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle" id="summaryTable">
            <thead>
                <tr>
                    <th>Class Name</th>
                    <th>Section Name</th>
                    <th class="text-center text-primary">Boys (M)</th>
                    <th class="text-center text-danger">Girls (F)</th>
                    <th class="text-center fw-bold text-dark">Total Registered</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($classBreakdown)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">No student enrollment logs found.</td></tr>
                <?php else: foreach ($classBreakdown as $row): ?>
                    <tr>
                        <td class="fw-bold text-dark"><?php echo sanitize($row['class_name']); ?></td>
                        <td><?php echo sanitize($row['section']); ?></td>
                        <td class="text-center text-primary fw-semibold"><?php echo (int)$row['boys']; ?></td>
                        <td class="text-center text-danger fw-semibold"><?php echo (int)$row['girls']; ?></td>
                        <td class="text-center fw-bold text-dark bg-light"><?php echo (int)$row['total']; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart scripts implementation -->
<script>
// Gender Chart (Pie)
const ctxGender = document.getElementById('genderChart').getContext('2d');
new Chart(ctxGender, {
    type: 'pie',
    data: {
        labels: ['Boys', 'Girls', 'Other'],
        datasets: [{
            data: [<?php echo $genderChartData['Male']; ?>, <?php echo $genderChartData['Female']; ?>, <?php echo $genderChartData['Other']; ?>],
            backgroundColor: ['#2563eb', '#db2777', '#9ca3af'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Class Strength Chart (Doughnut)
const ctxClass = document.getElementById('classChart').getContext('2d');
new Chart(ctxClass, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($classChartLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($classChartCounts); ?>,
            backgroundColor: ['#fbbf24', '#f59e0b', '#3b82f6', '#10b981', '#6366f1', '#ec4899', '#14b8a6'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Trend Line Chart
const trendLabels = [];
const trendCounts = [];
<?php foreach ($admissionTrendData as $tr): ?>
    trendLabels.push("<?php echo $tr['month_year']; ?>");
    trendCounts.push(<?php echo $tr['count']; ?>);
<?php endforeach; ?>

const ctxTrend = document.getElementById('trendChart').getContext('2d');
new Chart(ctxTrend, {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Admissions Logged',
            data: trendCounts,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        },
        plugins: {
            legend: { display: false }
        }
    }
});

// Export Excel functionality
function exportExcel() {
    let csv = "Class Name,Section Name,Boys (M),Girls (F),Total Strength\n";
    const rows = document.querySelectorAll("#summaryTable tbody tr");
    rows.forEach(tr => {
        const cols = tr.querySelectorAll("td");
        if (cols.length === 5) {
            let rowData = [];
            cols.forEach(td => {
                rowData.push('"' + td.textContent.trim() + '"');
            });
            csv += rowData.join(",") + "\n";
        }
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "classwise_student_strength_report.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
