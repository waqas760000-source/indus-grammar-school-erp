<?php
/**
 * Indus Grammar School ERP - Comprehensive Attendance Reports Panel
 * Version 4.0.0
 */

$pageTitle = 'Attendance Reports';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Selectors
$classes = SchoolClass::all();
$departments = $db->query("SELECT DISTINCT department FROM staff ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);

// Filter values
$selectedMode   = sanitize($_GET['report_mode'] ?? 'student');
$selectedType   = sanitize($_GET['report_type'] ?? 'daily');
$selectedDate   = sanitize($_GET['date'] ?? date('Y-m-d'));
$selectedMonth  = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear   = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedClass  = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedDept   = sanitize($_GET['department'] ?? '');

$reportTitle = "Attendance Report";
$reportData = [];

try {
    if ($selectedMode === 'student') {
        
        $sql = "
            SELECT a.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section
            FROM attendance a
            JOIN students st ON a.student_id = st.id
            JOIN classes c ON a.class_id = c.id
            WHERE 1=1
        ";
        $params = [];

        if ($selectedType === 'daily') {
            $reportTitle = "Daily Student Attendance Register ($selectedDate)";
            $sql .= " AND a.date = :date";
            $params['date'] = $selectedDate;
        } else if ($selectedType === 'monthly') {
            $reportTitle = "Monthly Student Attendance Sheet ($selectedMonth/$selectedYear)";
            $sql .= " AND MONTH(a.date) = :month AND YEAR(a.date) = :year";
            $params['month'] = $selectedMonth;
            $params['year'] = $selectedYear;
        } else if ($selectedType === 'late') {
            $reportTitle = "Late Student Roster";
            $sql .= " AND a.status = 'Late'";
            if ($selectedType === 'late' && !empty($selectedDate)) {
                $sql .= " AND a.date = :date";
                $params['date'] = $selectedDate;
            }
        } else if ($selectedType === 'absent') {
            $reportTitle = "Absent Student Roster";
            $sql .= " AND a.status = 'Absent'";
            if (!empty($selectedDate)) {
                $sql .= " AND a.date = :date";
                $params['date'] = $selectedDate;
            }
        } else if ($selectedType === 'leave') {
            $reportTitle = "Student Leave Applications Log";
            $sql .= " AND a.status = 'Leave'";
            if (!empty($selectedDate)) {
                $sql .= " AND a.date = :date";
                $params['date'] = $selectedDate;
            }
        }

        if ($selectedClass > 0) {
            $sql .= " AND a.class_id = :cid";
            $params['cid'] = $selectedClass;
        }

        $sql .= " ORDER BY a.date DESC, st.first_name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else { // staff mode
        
        $sql = "
            SELECT sa.*, s.first_name, s.last_name, s.employee_no, s.department, s.designation
            FROM staff_attendance sa
            JOIN staff s ON sa.staff_id = s.id
            WHERE 1=1
        ";
        $params = [];

        if ($selectedType === 'daily') {
            $reportTitle = "Daily Staff Attendance Register ($selectedDate)";
            $sql .= " AND sa.date = :date";
            $params['date'] = $selectedDate;
        } else if ($selectedType === 'monthly') {
            $reportTitle = "Monthly Staff Attendance Summary ($selectedMonth/$selectedYear)";
            $sql .= " AND MONTH(sa.date) = :month AND YEAR(sa.date) = :year";
            $params['month'] = $selectedMonth;
            $params['year'] = $selectedYear;
        } else if ($selectedType === 'late') {
            $reportTitle = "Late Staff Roster";
            $sql .= " AND sa.status = 'Late'";
            if (!empty($selectedDate)) {
                $sql .= " AND sa.date = :date";
                $params['date'] = $selectedDate;
            }
        } else if ($selectedType === 'absent') {
            $reportTitle = "Absent Staff Roster";
            $sql .= " AND sa.status = 'Absent'";
            if (!empty($selectedDate)) {
                $sql .= " AND sa.date = :date";
                $params['date'] = $selectedDate;
            }
        } else if ($selectedType === 'leave') {
            $reportTitle = "Staff Leave Journal";
            $sql .= " AND sa.status = 'Leave'";
            if (!empty($selectedDate)) {
                $sql .= " AND sa.date = :date";
                $params['date'] = $selectedDate;
            }
        }

        if ($selectedDept !== '') {
            $sql .= " AND s.department = :dept";
            $params['dept'] = $selectedDept;
        }

        $sql .= " ORDER BY sa.date DESC, s.first_name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Attendance report error: " . $e->getMessage());
}
?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-calendar-check text-primary me-2"></i>Attendance Reports</h3>
        <p class="text-muted small mb-0">Review student and employee registers, check late lists and extract monthly aggregates.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-outline-primary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Report</button>
        <button class="btn btn-outline-success px-3 ms-2" onclick="exportToExcel()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
        <a href="dashboard.php" class="btn btn-outline-secondary px-3 ms-2"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<!-- Filters Form -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Module Type</label>
                <select class="form-select" name="report_mode" id="reportMode" onchange="toggleFormFields(); this.form.submit();">
                    <option value="student" <?php echo $selectedMode === 'student' ? 'selected' : ''; ?>>Student Attendance</option>
                    <option value="staff" <?php echo $selectedMode === 'staff' ? 'selected' : ''; ?>>Staff Attendance</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Report Category</label>
                <select class="form-select" name="report_type" onchange="toggleFormFields()">
                    <option value="daily" <?php echo $selectedType === 'daily' ? 'selected' : ''; ?>>Daily Attendance</option>
                    <option value="monthly" <?php echo $selectedType === 'monthly' ? 'selected' : ''; ?>>Monthly Attendance</option>
                    <option value="late" <?php echo $selectedType === 'late' ? 'selected' : ''; ?>>Late Roster</option>
                    <option value="absent" <?php echo $selectedType === 'absent' ? 'selected' : ''; ?>>Absent List</option>
                    <option value="leave" <?php echo $selectedType === 'leave' ? 'selected' : ''; ?>>Leave Report</option>
                </select>
            </div>

            <!-- Class Field (Students Only) -->
            <div class="col-md-3 filter-field" id="classField">
                <label class="form-label small fw-semibold text-muted">Class & Section</label>
                <select class="form-select" name="class_id">
                    <option value="0">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Dept Field (Staff Only) -->
            <div class="col-md-3 filter-field" id="deptField" style="display:none;">
                <label class="form-label small fw-semibold text-muted">Department</label>
                <select class="form-select" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo $selectedDept === $d ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date Field (Daily/Late/Absent/Leave) -->
            <div class="col-md-3 filter-field" id="dateField">
                <label class="form-label small fw-semibold text-muted">Date</label>
                <input type="date" class="form-control" name="date" value="<?php echo $selectedDate; ?>">
            </div>

            <!-- Month/Year Fields (Monthly Only) -->
            <div class="col-md-2 filter-field" id="monthField" style="display:none;">
                <label class="form-label small fw-semibold text-muted">Month</label>
                <select class="form-select" name="month">
                    <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $selectedMonth === $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="col-md-1 filter-field" id="yearField" style="display:none;">
                <label class="form-label small fw-semibold text-muted">Year</label>
                <input type="number" class="form-control" name="year" value="<?php echo $selectedYear; ?>" min="2020" max="2035">
            </div>

            <div class="col-12 text-end mt-3">
                <a href="attendance.php" class="btn btn-outline-secondary px-4 py-2 me-2"><i class="fa-solid fa-rotate-left me-2"></i>Reset</a>
                <button type="submit" class="btn btn-primary px-5 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Output -->
<div class="card border-0 shadow-sm" style="border-radius:12px;" id="reportPrintArea">
    <!-- Print Header -->
    <div class="card-header bg-white border-0 pt-4 px-4 text-center d-none d-print-block border-bottom pb-3">
        <h3 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h3>
        <h5 class="text-secondary fw-semibold mb-1"><?php echo htmlspecialchars($reportTitle); ?></h5>
        <div class="text-muted small">
            Date: <?php echo date('d-M-Y H:i'); ?> | Generated By: <?php echo htmlspecialchars($_SESSION['username'] ?? 'ERP Admin'); ?>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                <thead>
                    <tr>
                        <th width="80">Index</th>
                        <?php if ($selectedMode === 'student'): ?>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th>Class Section</th>
                        <?php else: ?>
                            <th>Employee No</th>
                            <th>Staff Name</th>
                            <th>Department / Designation</th>
                        <?php endif; ?>
                        <th class="text-center">Date</th>
                        <?php if ($selectedMode === 'staff'): ?>
                            <th class="text-center">Timings</th>
                        <?php endif; ?>
                        <th class="text-center">Status</th>
                        <th>Remarks / Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No attendance logs found matching the filter options.</td></tr>
                    <?php else: foreach ($reportData as $i => $row): ?>
                        <tr>
                            <td class="text-muted fw-bold"><?php echo $i + 1; ?></td>
                            <?php if ($selectedMode === 'student'): ?>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($row['class_name'] . ' - ' . $row['section']); ?></span></td>
                            <?php else: ?>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['employee_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td>
                                    <div class="fw-semibold small text-dark"><?php echo htmlspecialchars($row['department']); ?></div>
                                    <div class="text-muted text-xs"><?php echo htmlspecialchars($row['designation']); ?></div>
                                </td>
                            <?php endif; ?>
                            <td class="text-center small"><?php echo date('d-M-Y', strtotime($row['date'])); ?></td>
                            <?php if ($selectedMode === 'staff'): ?>
                                <td class="text-center small">
                                    <?php if ($row['check_in_time']): ?>
                                        <i class="fa-regular fa-clock me-1 text-muted"></i><?php echo date('h:i A', strtotime($row['check_in_time'])); ?> - <?php echo $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '—'; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="text-center">
                                <span class="badge bg-<?php 
                                    echo $row['status'] === 'Present' ? 'success' : ($row['status'] === 'Absent' ? 'danger' : ($row['status'] === 'Late' ? 'warning' : 'info')); 
                                ?>-soft px-3 py-1 rounded-pill fw-bold"><?php echo $row['status']; ?></span>
                            </td>
                            <td class="small"><?php echo htmlspecialchars($row['remarks'] ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #reportPrintArea, #reportPrintArea * {
        visibility: visible;
    }
    #reportPrintArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none !important;
        border: 0 !important;
    }
    .custom-table th, .custom-table td {
        border: 1px solid #ddd !important;
        padding: 8px !important;
        font-size: 11px !important;
    }
}
</style>

<?php $extraJS = '<script>
function toggleFormFields() {
    const mode = document.getElementById("reportMode").value;
    const type = document.querySelector(\'[name="report_type"]\').value;

    const classField = document.getElementById("classField");
    const deptField = document.getElementById("deptField");
    const dateField = document.getElementById("dateField");
    const monthField = document.getElementById("monthField");
    const yearField = document.getElementById("yearField");

    // Default student vs staff
    if (mode === "student") {
        classField.style.display = "block";
        deptField.style.display = "none";
    } else {
        classField.style.display = "none";
        deptField.style.display = "block";
    }

    // Monthly vs others
    if (type === "monthly") {
        dateField.style.display = "none";
        monthField.style.display = "block";
        yearField.style.display = "block";
    } else {
        dateField.style.display = "block";
        monthField.style.display = "none";
        yearField.style.display = "none";
    }
}

function exportToExcel() {
    let table = document.getElementById("reportDataTable");
    let html = table.outerHTML;
    let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
    let link = document.createElement("a");
    link.download = "' . strtolower(str_replace(' ', '_', $reportTitle)) . '_' . date('Ymd') . '.xls";
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

document.addEventListener("DOMContentLoaded", function() {
    toggleFormFields();
});
</script>';
include_once __DIR__ . '/../../includes/header.php'; // Wait, footer must be included at the end!
// Ah, line 10 includes header.php, line 316 includes footer.php. So we must use footer.php
include_once __DIR__ . '/../../includes/footer.php'; ?>
