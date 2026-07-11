/**
 * Indus Grammar School ERP - Dynamic Dashboard Charts Router
 * Version 1.0.0
 */

let charts = {};

function initDashboardCharts(range = 'This Month') {
    const url = 'ajax/dashboard_ajax.php?action=get_analytics&range=' + encodeURIComponent(range);
    
    fetch(url)
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                renderCharts(response.data);
            }
        })
        .catch(err => console.error("Failed to load dashboard chart data:", err));
}

function renderCharts(data) {
    // 1. Admissions Trend Chart
    if (data.admissionsTrend) {
        destroyChart('admissionsChart');
        const ctx = document.getElementById('admissionsChart');
        if (ctx) {
            charts['admissionsChart'] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.admissionsTrend.map(item => item.label),
                    datasets: [{
                        label: 'New Admissions',
                        data: data.admissionsTrend.map(item => item.value),
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    }

    // 2. Attendance Trend Chart
    if (data.attendanceTrend) {
        destroyChart('attendanceChart');
        const ctx = document.getElementById('attendanceChart');
        if (ctx) {
            charts['attendanceChart'] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.attendanceTrend.map(item => item.label),
                    datasets: [{
                        label: 'Attendance Rate (%)',
                        data: data.attendanceTrend.map(item => item.value),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { min: 0, max: 100 } }
                }
            });
        }
    }

    // 3. Gender Distribution Chart
    if (data.genderDistribution) {
        destroyChart('genderChart');
        const ctx = document.getElementById('genderChart');
        if (ctx) {
            charts['genderChart'] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.genderDistribution.map(item => item.gender),
                    datasets: [{
                        data: data.genderDistribution.map(item => item.count),
                        backgroundColor: ['#3b82f6', '#ec4899', '#9b5de5']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    }

    // 4. Classwise Enrollment Chart
    if (data.classwiseEnrollment) {
        destroyChart('classChart');
        const ctx = document.getElementById('classChart');
        if (ctx) {
            charts['classChart'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.classwiseEnrollment.map(item => item.class_name + ' ' + item.section),
                    datasets: [{
                        label: 'Students Count',
                        data: data.classwiseEnrollment.map(item => item.student_count),
                        backgroundColor: '#6366f1',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    }

    // 5. Fee Collection Chart
    if (data.feeCollectionsTrend) {
        destroyChart('feeChart');
        const ctx = document.getElementById('feeChart');
        if (ctx) {
            charts['feeChart'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.feeCollectionsTrend.map(item => item.label),
                    datasets: [{
                        label: 'Fee Collections (PKR)',
                        data: data.feeCollectionsTrend.map(item => item.value),
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    }

    // 6. Income vs Expenses Chart
    if (data.incomeExpensesTrend) {
        destroyChart('incomeExpenseChart');
        const ctx = document.getElementById('incomeExpenseChart');
        if (ctx) {
            charts['incomeExpenseChart'] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.incomeExpensesTrend.labels,
                    datasets: [
                        {
                            label: 'Monthly Income',
                            data: data.incomeExpensesTrend.income,
                            borderColor: '#10b981',
                            fill: false,
                            tension: 0.3
                        },
                        {
                            label: 'Monthly Expenses',
                            data: data.incomeExpensesTrend.expenses,
                            borderColor: '#ef4444',
                            fill: false,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    }
}

function destroyChart(id) {
    if (charts[id]) {
        charts[id].destroy();
        delete charts[id];
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initDashboardCharts('This Month');

    const rangeSelect = document.getElementById('rangeSelect');
    if (rangeSelect) {
        rangeSelect.addEventListener('change', (e) => {
            initDashboardCharts(e.target.value);
        });
    }

    // Mark all notifications read action
    const btnMarkAll = document.getElementById('btn-mark-all');
    if (btnMarkAll) {
        btnMarkAll.addEventListener('click', () => {
            fetch('ajax/dashboard_ajax.php?action=mark_all_read')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.querySelector('.notification-badge');
                        if (badge) badge.remove();
                        btnMarkAll.disabled = true;
                        btnMarkAll.textContent = 'All Caught Up!';
                    }
                });
        });
    }
});
