<?php
/**
 * Indus Grammar School ERP - Visual Charts Dashboard
 * Version 1.0.0
 */

$pageTitle = 'Visual Chart Reports';
$breadcrumbActive = 'Dashboard';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('dashboard_view');

$classEnrollment = Report::getClasswiseEnrollment();
$financeData = Report::getFinanceSummary(date('Y-m-01'), date('Y-m-d'));

$enrollmentLabels = array_map(fn($c) => $c['class_name'] . ' ' . $c['section'], $classEnrollment);
$enrollmentData = array_map(fn($c) => $c['student_count'], $classEnrollment);
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Visual Reports</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="../../dashboard.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-2"></i>Back to Main</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Chart block 1: Class Enrollment -->
    <div class="col-lg-6">
        <div class="chart-card card border-0 shadow-sm p-4">
            <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-chart-bar me-2"></i>Class-wise Student Enrollment</h5>
            <div style="height: 320px; position: relative;">
                <canvas id="enrollmentChartDetailed"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart block 2: Collections vs Expenses -->
    <div class="col-lg-6">
        <div class="chart-card card border-0 shadow-sm p-4">
            <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-chart-pie me-2"></i>Collections vs Expenses (PKR)</h5>
            <div style="height: 320px; position: relative;">
                <canvas id="financeChartDetailed"></canvas>
            </div>
        </div>
    </div>
</div>

<?php
$extraJS = '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Detailed Enrollment Chart
    const ctxEnrollment = document.getElementById("enrollmentChartDetailed");
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
                    y: { beginAtZero: true, grid: { color: "#f1f5f9" } }
                }
            }
        });
    }

    // Detailed Finance Chart
    const ctxFinance = document.getElementById("financeChartDetailed");
    if (ctxFinance) {
        new Chart(ctxFinance, {
            type: "doughnut",
            data: {
                labels: ["Collections", "Expenses", "Outstanding"],
                datasets: [{
                    data: [' . $financeData['collections'] . ', ' . $financeData['expenses'] . ', ' . $financeData['outstanding'] . '],
                    backgroundColor: ["#16a34a", "#dc2626", "#f59e0b"],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: "bottom" } }
            }
        });
    }
});
</script>
';
include_once __DIR__ . '/../../includes/footer.php';
?>
