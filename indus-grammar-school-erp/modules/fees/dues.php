<?php
/**
 * Indus Grammar School ERP - Outstanding Dues & Defaulters Directory
 * Redesigned Premium UI & Defaulters Governance Workspace
 * Version 4.0.0
 */

require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('fee_view');

$pageTitle = 'Outstanding Dues List';
$breadcrumbActive = 'Outstanding Dues';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/navbar.php';

// Fetch outstanding defaulters
$defaulters = [];
$classes = [];
$totalDuesSum = 0.00;
$totalDefaultersCount = 0;
$criticalCount = 0;

try {
    $db = Database::getConnection();
    
    // Fetch classes for filter dropdown
    $classes = $db->query("SELECT * FROM classes ORDER BY id ASC")->fetchAll();

    // Fetch defaulters list
    $sql = "
        SELECT s.id, s.admission_no, s.first_name, s.last_name, s.academic_type, s.status as student_status,
               c.class_name, c.section, d.father_name, d.roll_no, d.doc_student_photo,
               GROUP_CONCAT(fl.month ORDER BY fl.due_date ASC SEPARATOR ', ') as pending_months,
               COUNT(fl.id) as pending_months_count,
               SUM(fl.total_payable - fl.paid_amount) as outstanding_balance
        FROM students s
        JOIN fee_ledger fl ON s.id = fl.student_id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        WHERE s.status = 'Active' AND fl.status IN ('Pending', 'Partial')
        GROUP BY s.id
        HAVING outstanding_balance > 0
        ORDER BY outstanding_balance DESC
    ";
    $defaulters = $db->query($sql)->fetchAll();
    
    $totalDuesSum = array_sum(array_column($defaulters, 'outstanding_balance'));
    $totalDefaultersCount = count($defaulters);

    foreach ($defaulters as $d) {
        if ((int)$d['pending_months_count'] >= 3) {
            $criticalCount++;
        }
    }
} catch (Exception $e) {
    error_log("dues.php query error: " . $e->getMessage());
}

$avgDuePerStudent = $totalDefaultersCount > 0 ? ($totalDuesSum / $totalDefaultersCount) : 0.00;
?>

<!-- Custom Premium ERP Defaulters Theme -->
<style>
:root {
    --primary-navy: #0F172A;
    --primary-blue: #1D4ED8;
    --secondary-blue: #2563EB;
    --hover-blue: #1E40AF;
    --light-blue: #EFF6FF;
    --page-background: #F1F5F9;
    --card-white: #FFFFFF;
    --border-color: #E2E8F0;
    --main-text: #1E293B;
    --muted-text: #64748B;
    --success: #16A34A;
    --success-bg: #F0FDF4;
    --warning: #D97706;
    --warning-bg: #FFFBEB;
    --danger: #DC2626;
    --danger-bg: #FEF2F2;
    --purple: #7C3AED;
    --purple-bg: #F5F3FF;
}

/* Page Canvas Wrapper */
.dues-wrapper {
    background-color: var(--page-background);
    border-radius: 18px;
    padding: 1.5rem;
    min-height: calc(100vh - 90px);
}

/* Hero Banner */
.adv-hero-banner {
    background: linear-gradient(135deg, var(--primary-navy) 0%, #1e3a8a 55%, var(--secondary-blue) 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2);
    position: relative;
    overflow: hidden;
}

.adv-hero-banner::after {
    content: '';
    position: absolute;
    right: -40px;
    bottom: -40px;
    width: 260px;
    height: 260px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
    pointer-events: none;
}

.hero-icon-box {
    width: 58px;
    height: 58px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: #ffffff;
}

/* Top KPI Cards */
.top-kpi-card {
    background: var(--card-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    transition: all 0.25s ease;
    height: 100%;
}

.top-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.07);
}

.kpi-icon-avatar {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}

/* Standard Styled Cards */
.card-custom {
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    background: var(--card-white);
    transition: all 0.25s ease-in-out;
}

.card-header-custom {
    background: transparent;
    border-bottom: 1px solid #f1f5f9;
    padding: 1.25rem 1.5rem;
}

/* Table Custom Styling */
.table-custom-header th {
    background-color: var(--primary-navy);
    color: #f8fafc;
    font-weight: 600;
    font-size: 0.825rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.9rem 1rem;
    border: none;
}

.table-custom-body td {
    padding: 0.9rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.925rem;
}

/* Student Row Avatar */
.student-avatar-sm {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e2e8f0;
}
.student-avatar-placeholder-sm {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e3a8a, #2563eb);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    font-weight: 700;
}

@media print {
    body { background: #ffffff !important; }
    .dues-wrapper { padding: 0 !important; background: transparent !important; }
    .adv-hero-banner, .top-kpi-card, nav, .search-filter-card, .action-buttons, .btn-print-hide { display: none !important; }
    .card-custom { border: none !important; box-shadow: none !important; }
}
</style>

<div class="dues-wrapper">
    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3 btn-print-hide">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/index.php" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item text-muted">Fee Management</li>
            <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Outstanding Dues List</li>
        </ol>
    </nav>

    <!-- Hero Header Banner -->
    <div class="adv-hero-banner mb-4 btn-print-hide">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold mb-0 text-white fs-3">Outstanding Dues Directory</h3>
                        <span class="badge bg-danger text-white px-3 py-1 rounded-pill small fw-semibold">Defaulters Ledger</span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">Monitor student fee defaulters, track unpaid monthly ledgers, and issue fee collection notices.</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center action-buttons">
                <a href="collection.php" class="btn btn-sm btn-light text-primary rounded-2 px-3 py-2 fw-semibold shadow-sm"><i class="fa-solid fa-cash-register me-1"></i>Cashier Collection Desk</a>
                <button type="button" id="btnExportCSV" class="btn btn-sm btn-outline-light rounded-2 px-3 py-2 fw-semibold"><i class="fa-solid fa-file-csv me-1"></i>Export CSV</button>
                <button type="button" onclick="window.print();" class="btn btn-sm btn-outline-light rounded-2 px-3 py-2 fw-semibold"><i class="fa-solid fa-print me-1"></i>Print Report</button>
            </div>
        </div>
    </div>

    <!-- Top KPI Executive Metric Cards -->
    <div class="row g-3 mb-4 btn-print-hide">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="top-kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Total Defaulters</span>
                    <h3 class="fw-bold text-dark mb-0 fs-4" id="statTotalDefaulters"><?php echo $totalDefaultersCount; ?> Students</h3>
                    <span class="badge bg-warning-subtle text-warning px-2 py-0.5 rounded-pill small mt-1 fw-semibold"><i class="fa-solid fa-user-clock me-1"></i>Pending Ledger</span>
                </div>
                <div class="kpi-icon-avatar bg-warning-subtle text-warning">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="top-kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Total Outstanding Dues</span>
                    <h3 class="fw-bold text-danger mb-0 fs-4" id="statTotalDuesSum">Rs. <?php echo number_format($totalDuesSum, 2); ?></h3>
                    <span class="badge bg-danger-subtle text-danger px-2 py-0.5 rounded-pill small mt-1 fw-semibold"><i class="fa-solid fa-coins me-1"></i>Uncollected Amount</span>
                </div>
                <div class="kpi-icon-avatar bg-danger-subtle text-danger">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="top-kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Critical (3+ Months)</span>
                    <h3 class="fw-bold text-dark mb-0 fs-4" id="statCriticalDefaulters"><?php echo $criticalCount; ?> Students</h3>
                    <span class="badge bg-danger text-white px-2 py-0.5 rounded-pill small mt-1 fw-semibold"><i class="fa-solid fa-circle-exclamation me-1"></i>High Priority</span>
                </div>
                <div class="kpi-icon-avatar bg-danger-subtle text-danger">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="top-kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Avg Due / Defaulter</span>
                    <h3 class="fw-bold text-dark mb-0 fs-4" id="statAvgDue">Rs. <?php echo number_format($avgDuePerStudent, 2); ?></h3>
                    <span class="badge bg-primary-subtle text-primary px-2 py-0.5 rounded-pill small mt-1 fw-semibold"><i class="fa-solid fa-calculator me-1"></i>Per Student Avg</span>
                </div>
                <div class="kpi-icon-avatar bg-primary-subtle text-primary">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls Card -->
    <div class="card card-custom mb-4 search-filter-card btn-print-hide">
        <div class="card-body p-4">
            <div class="row g-3 align-items-center">
                <!-- Search Bar -->
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold text-dark mb-1">Search Defaulter</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" id="duesSearchInput" class="form-control border-start-0 ps-0" placeholder="Student name, admission #, roll #...">
                    </div>
                </div>

                <!-- Class Filter -->
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label small fw-bold text-dark mb-1">Filter by Class</label>
                    <select id="classFilterSelect" class="form-select">
                        <option value="">— All Classes —</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>">
                                <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Academic Type Filter -->
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label small fw-bold text-dark mb-1">Academic Type</label>
                    <select id="typeFilterSelect" class="form-select">
                        <option value="">— All Types —</option>
                        <option value="School">School</option>
                        <option value="Academy">Academy</option>
                    </select>
                </div>

                <!-- Reset Filter Button -->
                <div class="col-12 col-md-2 text-md-end mt-md-4">
                    <button type="button" id="btnResetFilters" class="btn btn-outline-secondary w-100 fw-semibold">
                        <i class="fa-solid fa-rotate-right me-1"></i>Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Defaulters Data Table -->
    <div class="card card-custom">
        <div class="card-header-custom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-users-viewfinder me-2 text-primary"></i>Fee Defaulters Directory</h5>
                <span class="badge bg-danger-subtle text-danger px-3 py-1 rounded-pill fw-semibold" id="tableRecordBadge"><?php echo $totalDefaultersCount; ?> Defaulters</span>
            </div>
            <div class="text-end text-muted small btn-print-hide">
                Sorted by highest outstanding balance
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="defaultersTable">
                    <thead class="table-custom-header">
                        <tr>
                            <th>Student Record</th>
                            <th>Admission No</th>
                            <th>Class & Section</th>
                            <th>Academic Type</th>
                            <th>Pending Fee Months</th>
                            <th class="text-end">Outstanding Balance</th>
                            <th class="text-center">Severity Status</th>
                            <th class="text-end btn-print-hide">Action</th>
                        </tr>
                    </thead>
                    <tbody class="table-custom-body" id="defaultersTbody">
                        <?php if (empty($defaulters)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-success fw-bold">
                                    <i class="fa-solid fa-circle-check fs-1 text-success d-block mb-3"></i>
                                    <h5 class="fw-bold text-dark mb-1">Zero Outstanding Defaulters!</h5>
                                    <p class="text-muted small mb-0">All active student fee ledgers are fully paid up to date.</p>
                                </td>
                            </tr>
                        <?php else: foreach ($defaulters as $d): ?>
                            <?php
                                $mCount = (int)$d['pending_months_count'];
                                $severityBadge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2.5 py-1 rounded-pill fw-semibold">1 Month Overdue</span>';
                                if ($mCount == 2) {
                                    $severityBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill fw-semibold">2 Months Overdue</span>';
                                } else if ($mCount >= 3) {
                                    $severityBadge = '<span class="badge bg-danger text-white px-2.5 py-1 rounded-pill fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Critical (' . $mCount . ' Months)</span>';
                                }

                                $firstLetter1 = !empty($d['first_name']) ? strtoupper(substr($d['first_name'], 0, 1)) : '';
                                $firstLetter2 = !empty($d['last_name']) ? strtoupper(substr($d['last_name'], 0, 1)) : '';
                                $initials = $firstLetter1 . $firstLetter2;
                                if (empty($initials)) $initials = 'ST';
                            ?>
                            <tr data-name="<?php echo htmlspecialchars(strtolower($d['first_name'] . ' ' . $d['last_name'])); ?>"
                                data-admission="<?php echo htmlspecialchars(strtolower($d['admission_no'])); ?>"
                                data-class="<?php echo htmlspecialchars($d['class_name'] . ' - ' . $d['section']); ?>"
                                data-type="<?php echo htmlspecialchars($d['academic_type']); ?>"
                                data-balance="<?php echo $d['outstanding_balance']; ?>">
                                
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if (!empty($d['doc_student_photo'])): ?>
                                            <img src="<?php echo APP_URL . '/' . htmlspecialchars($d['doc_student_photo']); ?>" class="student-avatar-sm" alt="Student Photo">
                                        <?php else: ?>
                                            <div class="student-avatar-placeholder-sm"><?php echo $initials; ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($d['first_name'] . ' ' . $d['last_name']); ?></div>
                                            <div class="text-muted small">Father: <strong><?php echo htmlspecialchars($d['father_name'] ?? '—'); ?></strong> &middot; Roll: <strong><?php echo htmlspecialchars($d['roll_no'] ?? '—'); ?></strong></div>
                                        </div>
                                    </div>
                                </td>

                                <td><code class="fw-bold text-primary fs-6"><?php echo htmlspecialchars($d['admission_no']); ?></code></td>

                                <td>
                                    <span class="badge bg-light text-dark border px-2.5 py-1 fw-semibold">
                                        <?php echo htmlspecialchars(($d['class_name'] ?? '') . ' - ' . ($d['section'] ?? '')); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 fw-semibold">
                                        <?php echo htmlspecialchars($d['academic_type'] ?? 'School'); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="text-dark small fw-semibold" title="<?php echo htmlspecialchars($d['pending_months']); ?>">
                                        <i class="fa-solid fa-calendar-days text-danger me-1"></i>
                                        <?php echo htmlspecialchars(strlen($d['pending_months']) > 45 ? substr($d['pending_months'], 0, 42) . '...' : $d['pending_months']); ?>
                                    </span>
                                </td>

                                <td class="text-end">
                                    <span class="fw-bold text-danger fs-5">Rs. <?php echo number_format($d['outstanding_balance'], 2); ?></span>
                                </td>

                                <td class="text-center">
                                    <?php echo $severityBadge; ?>
                                </td>

                                <td class="text-end btn-print-hide">
                                    <div class="d-flex align-items-center justify-content-end gap-1">
                                        <?php if (hasPermission('fee_collect')): ?>
                                            <a href="collection.php?student_id=<?php echo $d['id']; ?>" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm fw-bold">
                                                <i class="fa-solid fa-hand-holding-dollar me-1"></i>Collect Fee
                                            </a>
                                        <?php endif; ?>
                                        <a href="challan.php?student_id=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5" title="View Ledger Sheets">
                                            <i class="fa-solid fa-file-invoice"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("duesSearchInput");
    const classFilter = document.getElementById("classFilterSelect");
    const typeFilter = document.getElementById("typeFilterSelect");
    const btnReset = document.getElementById("btnResetFilters");
    const tableBody = document.getElementById("defaultersTbody");
    const recordBadge = document.getElementById("tableRecordBadge");
    
    // Client-side filtering handler
    function filterDefaulters() {
        const query = searchInput.value.trim().toLowerCase();
        const selectedClass = classFilter.value;
        const selectedType = typeFilter.value;

        const rows = tableBody.querySelectorAll("tr[data-name]");
        let visibleCount = 0;
        let visibleSum = 0;

        rows.forEach(row => {
            const name = row.dataset.name || "";
            const admission = row.dataset.admission || "";
            const cls = row.dataset.class || "";
            const type = row.dataset.type || "";
            const bal = parseFloat(row.dataset.balance || 0);

            const matchQuery = !query || name.includes(query) || admission.includes(query) || cls.toLowerCase().includes(query);
            const matchClass = !selectedClass || cls === selectedClass;
            const matchType = !selectedType || type === selectedType;

            if (matchQuery && matchClass && matchType) {
                row.style.display = "";
                visibleCount++;
                visibleSum += bal;
            } else {
                row.style.display = "none";
            }
        });

        if (recordBadge) {
            recordBadge.textContent = `${visibleCount} Defaulters`;
        }
    }

    searchInput?.addEventListener("input", filterDefaulters);
    classFilter?.addEventListener("change", filterDefaulters);
    typeFilter?.addEventListener("change", filterDefaulters);

    btnReset?.addEventListener("click", function() {
        searchInput.value = "";
        classFilter.value = "";
        typeFilter.value = "";
        filterDefaulters();
    });

    // CSV Export Handler
    document.getElementById("btnExportCSV")?.addEventListener("click", function() {
        const table = document.getElementById("defaultersTable");
        if (!table) return;

        let csv = [];
        const rows = table.querySelectorAll("tr");
        
        for (let i = 0; i < rows.length; i++) {
            if (rows[i].style.display === "none") continue;
            const row = [], cols = rows[i].querySelectorAll("td, th");
            // Skip action column (index 7)
            for (let j = 0; j < cols.length - 1; j++) {
                let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/\s+/g, " ").trim();
                text = text.replace(/"/g, '""');
                row.push('"' + text + '"');
            }
            if (row.length > 0) csv.push(row.join(","));
        }

        const csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
        const downloadLink = document.createElement("a");
        downloadLink.download = "outstanding_dues_report_" + new Date().toISOString().slice(0,10) + ".csv";
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = "none";
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
