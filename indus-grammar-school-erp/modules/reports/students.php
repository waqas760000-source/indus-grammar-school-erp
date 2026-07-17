<?php
/**
 * Indus Grammar School ERP - Comprehensive Student Reports
 * Version 4.0.0
 */

$pageTitle = 'Student Reports Panel';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Selectors
$classes = SchoolClass::all();
$sessions = $db->query("SELECT DISTINCT academic_year FROM fee_structure UNION SELECT '" . CURRENT_ACADEMIC_YEAR . "'")->fetchAll(PDO::FETCH_COLUMN);

// Filter values
$selectedReport = sanitize($_GET['report_type'] ?? 'student_list');
$selectedClass  = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedSession = sanitize($_GET['academic_session'] ?? CURRENT_ACADEMIC_YEAR);
$selectedType    = sanitize($_GET['academic_type'] ?? '');
$selectedStatus  = sanitize($_GET['status'] ?? '');
$dateFrom        = sanitize($_GET['date_from'] ?? '');
$dateTo          = sanitize($_GET['date_to'] ?? '');

// Build optimized SQL query
$sql = "
    SELECT st.*, c.class_name, c.section,
           TIMESTAMPDIFF(YEAR, st.date_of_birth, CURDATE()) as age
    FROM students st
    LEFT JOIN classes c ON st.class_id = c.id
    WHERE 1=1
";
$params = [];

if ($selectedClass > 0) {
    $sql .= " AND st.class_id = :cid";
    $params['cid'] = $selectedClass;
}
if ($selectedType !== '') {
    $sql .= " AND st.academic_type = :atype";
    $params['atype'] = $selectedType;
}
if ($selectedStatus !== '') {
    $sql .= " AND st.status = :status";
    $params['status'] = $selectedStatus;
}
if ($dateFrom !== '') {
    $sql .= " AND st.enrollment_date >= :from";
    $params['from'] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= " AND st.enrollment_date <= :to";
    $params['to'] = $dateTo;
}

// Sub-report logic customization
$reportTitle = "Student Roster Report";
switch ($selectedReport) {
    case 'admission_register':
        $reportTitle = "Student Admission Register Log";
        $sql .= " ORDER BY st.enrollment_date ASC, st.admission_no ASC";
        break;
    case 'class_wise':
        $reportTitle = "Class-Wise Students Breakdown";
        $sql .= " ORDER BY c.class_name ASC, st.first_name ASC";
        break;
    case 'school_students':
        $reportTitle = "School Program Students List";
        $sql .= " AND st.academic_type = 'School' ORDER BY st.first_name ASC";
        break;
    case 'academy_students':
        $reportTitle = "Academy Program Students List";
        $sql .= " AND st.academic_type = 'Academy' ORDER BY st.first_name ASC";
        break;
    case 'gender_report':
        $reportTitle = "Gender Distribution Report";
        $sql .= " ORDER BY st.gender ASC, st.first_name ASC";
        break;
    case 'age_report':
        $reportTitle = "Age-Demographics Report";
        $sql .= " ORDER BY age DESC, st.first_name ASC";
        break;
    case 'new_admissions':
        $reportTitle = "New Student Admissions Log";
        $sql .= " AND st.enrollment_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) ORDER BY st.enrollment_date DESC";
        break;
    case 'inactive_students':
        $reportTitle = "Inactive Students Directory";
        $sql .= " AND st.status = 'Inactive' ORDER BY st.first_name ASC";
        break;
    case 'suspended_students':
        $reportTitle = "Suspended Students Registry";
        $sql .= " AND st.status = 'Suspended' ORDER BY st.first_name ASC";
        break;
    default:
        $reportTitle = "Student Directory List";
        $sql .= " ORDER BY st.first_name ASC";
        break;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-user-graduate text-primary me-2"></i>Student Reports</h3>
        <p class="text-muted small mb-0">Construct demographics summaries, admissions lists, age profiles and class breakdowns.</p>
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
                <label class="form-label small fw-semibold text-muted">Report Sub-type</label>
                <select class="form-select" name="report_type" onchange="this.form.submit()">
                    <option value="student_list" <?php echo $selectedReport === 'student_list' ? 'selected' : ''; ?>>Student List</option>
                    <option value="admission_register" <?php echo $selectedReport === 'admission_register' ? 'selected' : ''; ?>>Admission Register</option>
                    <option value="class_wise" <?php echo $selectedReport === 'class_wise' ? 'selected' : ''; ?>>Class Wise Students</option>
                    <option value="school_students" <?php echo $selectedReport === 'school_students' ? 'selected' : ''; ?>>School Students</option>
                    <option value="academy_students" <?php echo $selectedReport === 'academy_students' ? 'selected' : ''; ?>>Academy Students</option>
                    <option value="gender_report" <?php echo $selectedReport === 'gender_report' ? 'selected' : ''; ?>>Gender Report</option>
                    <option value="age_report" <?php echo $selectedReport === 'age_report' ? 'selected' : ''; ?>>Age Report</option>
                    <option value="new_admissions" <?php echo $selectedReport === 'new_admissions' ? 'selected' : ''; ?>>New Admissions</option>
                    <option value="inactive_students" <?php echo $selectedReport === 'inactive_students' ? 'selected' : ''; ?>>Inactive Students</option>
                    <option value="suspended_students" <?php echo $selectedReport === 'suspended_students' ? 'selected' : ''; ?>>Suspended Students</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Academic Type</label>
                <select class="form-select" name="academic_type">
                    <option value="">All Types</option>
                    <option value="School" <?php echo $selectedType === 'School' ? 'selected' : ''; ?>>School</option>
                    <option value="Academy" <?php echo $selectedType === 'Academy' ? 'selected' : ''; ?>>Academy</option>
                </select>
            </div>

            <div class="col-md-2">
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

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $selectedStatus === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $selectedStatus === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Suspended" <?php echo $selectedStatus === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Enrollment Date Range</label>
                <div class="input-group">
                    <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                    <span class="input-group-text">to</span>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                </div>
            </div>

            <div class="col-12 text-end mt-3">
                <a href="students.php" class="btn btn-outline-secondary px-4 py-2 me-2"><i class="fa-solid fa-rotate-left me-2"></i>Reset</a>
                <button type="submit" class="btn btn-primary px-5 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Output -->
<div class="card border-0 shadow-sm" style="border-radius:12px;" id="reportPrintArea">
    <!-- Print Specific Header -->
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
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Class Section</th>
                        <th>Academic Type</th>
                        <th class="text-center">Gender</th>
                        <th class="text-center">Age</th>
                        <th>Enrollment Date</th>
                        <th>Guardian Details</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No student records found matching the criteria.</td></tr>
                    <?php else: foreach ($reportData as $row): ?>
                        <tr>
                            <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td>
                                <?php if ($row['class_name']): ?>
                                    <span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($row['class_name'] . ' - ' . $row['section']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="small fw-semibold"><?php echo htmlspecialchars($row['academic_type'] ?: 'School'); ?></td>
                            <td class="text-center small"><?php echo htmlspecialchars($row['gender']); ?></td>
                            <td class="text-center fw-bold text-dark"><?php echo $row['age']; ?> yrs</td>
                            <td class="small"><?php echo $row['enrollment_date'] ? date('d-M-Y', strtotime($row['enrollment_date'])) : '—'; ?></td>
                            <td>
                                <div class="fw-semibold small text-dark"><?php echo htmlspecialchars($row['guardian_name']); ?></div>
                                <div class="text-muted text-xs"><?php echo htmlspecialchars($row['guardian_phone']); ?></div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?php 
                                    echo $row['status'] === 'Active' ? 'success' : ($row['status'] === 'Suspended' ? 'warning' : 'secondary'); 
                                ?>-soft px-3 py-1 rounded-pill fw-semibold"><?php echo $row['status']; ?></span>
                            </td>
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
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
