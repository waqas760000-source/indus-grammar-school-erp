<?php
/**
 * Indus Grammar School ERP - Student Detail Report (CNIC Search Only)
 * Version 6.0.0
 */

$pageTitle = 'Student Detail Report';
$breadcrumbActive = 'Student Registration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

// Check search CNIC form value safely
$search_cnic = sanitize($_GET['search_cnic'] ?? '');

// Build dynamic search query
$searchResults = [];
$showResults = true;
$error_message = '';

try {
    $sql = "
        SELECT s.id, s.admission_no, s.first_name, s.last_name, s.status, s.academic_type, s.school_class, s.school_section, c.class_name, c.section,
               d.roll_no, d.father_name, d.cnic_no, d.father_cnic, d.doc_student_photo, d.academic_session
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        WHERE 1=1
    ";
    $params = [];

    if ($search_cnic !== '') {
        // Validate CNIC / B-Form format exactly (optional, but robust)
        if (!preg_match('/^\d{5}-\d{7}-\d{1}$/', $search_cnic)) {
            $error_message = "B-Form / CNIC format must be XXXXX-XXXXXXX-X.";
            $sql .= " AND 1=0"; // Prevent query execution from returning invalid matches
        } else {
            $sql .= " AND d.cnic_no = :cnic_no";
            $params['cnic_no'] = $search_cnic;
        }
    }

    $sql .= " ORDER BY s.admission_no DESC LIMIT 50";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $searchResults = $stmt->fetchAll();

    if ($search_cnic !== '' && empty($searchResults) && empty($error_message)) {
        $error_message = "No student found with this B-Form / CNIC Number.";
    }
} catch (Exception $e) {
    error_log("Student Detail Report search error: " . $e->getMessage());
}

// Load selected student's complete profile
$selectedStudentId = isset($_GET['view_id']) ? (int)$_GET['view_id'] : 0;

// Auto-open dossier if exactly one result is matched on CNIC search
if ($selectedStudentId === 0 && count($searchResults) === 1 && $search_cnic !== '') {
    $selectedStudentId = $searchResults[0]['id'];
}

$student = null;
if ($selectedStudentId > 0) {
    $student = StudentRepository::getCompleteProfile($selectedStudentId);
}
?>

<!-- Header -->
<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-file-invoice me-2 text-primary"></i>Student Detail Report</h3>
    </div>
</div>

<!-- Search Panel Grid (B-Form / CNIC Only) -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4" style="border-radius: 12px;">
    <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Search Registered Students</h5>
    <form method="GET" action="detail_report.php" id="searchForm" class="row g-3 align-items-end">
        <div class="col-md-9">
            <label class="form-label small fw-semibold text-muted">Student B-Form / CNIC Number</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0" style="border-top-left-radius: 8px; border-bottom-left-radius: 8px;"><i class="fa-solid fa-address-card text-muted"></i></span>
                <input type="text" class="form-control bg-light border-start-0" name="search_cnic" value="<?php echo htmlspecialchars($search_cnic); ?>" placeholder="Enter Student B-Form / CNIC Number (e.g. 35201-1234567-1)" style="border-top-right-radius: 8px; border-bottom-right-radius: 8px;" required>
            </div>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" id="searchBtn" class="btn btn-primary w-100 py-2" style="border-radius: 8px;"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
            <a href="detail_report.php" class="btn btn-outline-secondary w-100 py-2" style="border-radius: 8px;">Reset</a>
        </div>
    </form>
</div>

<!-- Error/Status alert messages -->
<?php if (!empty($error_message)): ?>
    <div class="alert alert-warning border-0 shadow-sm mb-4" style="border-radius: 12px;">
        <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<!-- Search Results Table -->
<?php if ($showResults && !empty($searchResults)): ?>
<div class="card border border-light shadow-sm bg-white p-4 mb-4" style="border-radius: 12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-list me-2"></i>Search Results (<?php echo count($searchResults); ?> found)</h6>
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle">
            <thead>
                <tr>
                    <th width="60">Photo</th>
                    <th>Admission No</th>
                    <th>Roll No</th>
                    <th>Student Name</th>
                    <th>Father Name</th>
                    <th>Class</th>
                    <th>Section</th>
                    <th>Academic Type</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($searchResults as $row): ?>
                    <tr class="<?php echo ($selectedStudentId === (int)$row['id']) ? 'table-primary' : ''; ?>">
                        <td>
                            <div class="rounded-circle overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <?php if (!empty($row['doc_student_photo'])): ?>
                                    <img src="<?php echo APP_URL . '/' . $row['doc_student_photo']; ?>" class="img-fluid" alt="Student Photo">
                                <?php else: ?>
                                    <i class="fa-solid fa-user-graduate text-muted" style="font-size: 1.1rem;"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="fw-bold text-primary"><?php echo sanitize($row['admission_no']); ?></td>
                        <td><?php echo sanitize($row['roll_no'] ?: '-'); ?></td>
                        <td class="fw-bold"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo sanitize($row['father_name'] ?: '-'); ?></td>
                        <td><?php echo sanitize($row['class_name'] ?? $row['school_class'] ?? '-'); ?></td>
                        <td><?php echo sanitize($row['section'] ?? $row['school_section'] ?? 'A'); ?></td>
                        <td>
                            <?php
                            $type = $row['academic_type'] ?? 'School';
                            $badgeType = ($type === 'Academy') ? 'bg-success' : 'bg-primary';
                            ?>
                            <span class="badge <?php echo $badgeType; ?>"><?php echo sanitize($type); ?></span>
                        </td>
                        <td>
                            <?php
                            $status = $row['status'] ?? 'Active';
                            $badgeStatus = ($status === 'Active') ? 'badge-soft-success' : 'bg-light text-secondary border';
                            ?>
                            <span class="badge <?php echo $badgeStatus; ?> px-2 py-1 rounded-pill"><?php echo sanitize($status); ?></span>
                        </td>
                        <td class="text-end">
                            <a href="detail_report.php?view_id=<?php echo $row['id']; ?>&search_cnic=<?php echo urlencode($search_cnic); ?>" class="btn btn-sm btn-primary">
                                <i class="fa-solid fa-eye me-1"></i>View Details
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Detailed Dossier Panel (Read-only) -->
<?php if ($student): ?>
<div id="printableDossier" class="card border border-light shadow bg-white p-4" style="border-radius:12px;">
    
    <!-- Top Action Toolbar (Print / PDF / Excel) -->
    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4 d-print-none">
        <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-folder-open text-primary me-2"></i>Student Dossier File</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Profile</button>
            <button class="btn btn-outline-success" onclick="exportExcel()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
        </div>
    </div>

    <!-- Student Photo & Identity Overview Section -->
    <div class="row align-items-center g-4 border-bottom pb-4 mb-4">
        <div class="col-md-2 text-center">
            <div class="border rounded bg-light p-2 mx-auto" style="width: 140px; height: 140px; display: flex; align-items: center; justify-content: center;">
                <?php if (!empty($student['doc_student_photo'])): ?>
                    <img src="<?php echo APP_URL . '/' . $student['doc_student_photo']; ?>" class="img-fluid rounded" alt="Student Photo" style="max-height: 100%;">
                <?php else: ?>
                    <i class="fa-solid fa-user-graduate fs-1 text-muted"></i>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-10">
            <div class="row g-3">
                <div class="col-md-6">
                    <h3 class="fw-bold text-dark mb-1"><?php echo sanitize($student['first_name'] . ' ' . $student['last_name']); ?></h3>
                    <span class="badge bg-primary px-3 rounded-pill">Adm. No: <?php echo sanitize($student['admission_no']); ?></span>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="small text-muted">Academic Session: <strong><?php echo displayValue($student['academic_session'] ?? null); ?></strong></div>
                    <div class="small text-muted">Campus Location: <strong><?php echo displayValue($student['campus'] ?? null); ?></strong></div>
                    <div class="small text-muted">Active Status: <span class="badge bg-success-soft"><?php echo displayValue($student['status'] ?? null); ?></span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 12 detailed Read-Only sections grid -->
    <div class="row g-4">
        
        <!-- Academic Placement -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-school me-2"></i>Academic Placement</h6>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Academic Type:</td><td class="fw-semibold"><span class="badge bg-secondary"><?php echo displayValue($student['academic_type'] ?? null, 'School'); ?></span></td></tr>
                    <tr><td class="text-muted">Admission Date:</td><td class="fw-semibold"><?php echo displayValue($student['admission_date'] ?? $student['enrollment_date'] ?? null); ?></td></tr>
                    <tr><td class="text-muted">Roll Number:</td><td class="fw-semibold"><?php echo displayValue($student['roll_no'] ?? null); ?></td></tr>
                    <tr><td class="text-muted">Class & Section:</td><td class="fw-semibold"><?php echo sanitize(($student['class_name'] ?? $student['school_class'] ?? '-') . ' - ' . ($student['section'] ?? $student['school_section'] ?? 'A')); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Personal info -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-user me-2"></i>Personal Information</h6>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Gender:</td><td class="fw-semibold"><?php echo displayValue($student['gender'] ?? null); ?></td></tr>
                    <tr><td class="text-muted">DOB:</td><td class="fw-semibold"><?php echo displayValue($student['date_of_birth'] ?? null); ?></td></tr>
                    <tr><td class="text-muted">Student Mobile:</td><td class="fw-semibold"><?php echo displayValue($student['student_mobile'] ?? null); ?></td></tr>
                    <tr><td class="text-muted">Student Email:</td><td class="fw-semibold"><?php echo displayValue($student['student_email'] ?? null); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Family Info -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-users me-2"></i>Family Coordinates</h6>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Father Name:</td><td class="fw-semibold"><?php echo displayValue($student['father_name'] ?? null); ?></td></tr>
                    <tr><td class="text-muted">Father CNIC:</td><td class="fw-semibold"><?php echo displayValue($student['father_cnic'] ?? null); ?></td></tr>
                    <tr><td class="text-muted">Father Mobile:</td><td class="fw-semibold"><?php echo displayValue($student['father_mobile'] ?? null); ?></td></tr>
                    <tr><td class="text-muted">CNIC / B-Form:</td><td class="fw-semibold"><?php echo displayValue($student['cnic_no'] ?? null); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Guardian Info -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-user-shield me-2"></i>Guardian Coordinates</h6>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Guardian Name:</td><td class="fw-semibold"><?php echo displayValue($student['guardian_name'] ?? null); ?></td></tr>
                    <tr><td class="text-muted">Guardian Phone:</td><td class="fw-semibold"><?php echo displayValue($student['guardian_phone'] ?? null); ?></td></tr>
                    <tr><td class="text-muted">Relationship:</td><td class="fw-semibold"><?php echo displayValue($student['guardian_relationship'] ?? null); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Address details -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-location-dot me-2"></i>Postal Address</h6>
                <div class="small text-muted mb-1">Current Address:</div>
                <div class="small fw-semibold mb-2"><?php echo displayValue($student['current_address'] ?? $student['address'] ?? null); ?></div>
                <div class="small text-muted mb-1">Permanent Address:</div>
                <div class="small fw-semibold"><?php echo displayValue($student['permanent_address'] ?? null); ?></div>
            </div>
        </div>

        <!-- Fee Plan Settings -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-receipt me-2"></i>Financial & Fee Structure</h6>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Selected Plan:</td><td class="fw-semibold"><?php echo displayValue($student['fee_plan'] ?? null, 'Regular Plan'); ?></td></tr>
                    <tr><td class="text-muted">Admission Fee:</td><td class="fw-semibold">Rs. <?php echo number_format((float)($student['fee_admission'] ?? 0.00), 2); ?></td></tr>
                    <tr><td class="text-muted">Monthly Tuition:</td><td class="fw-semibold">Rs. <?php echo number_format((float)($student['fee_monthly'] ?? 0.00), 2); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Remarks -->
        <div class="col-12">
            <div class="p-3 border rounded bg-light">
                <h6 class="fw-bold text-primary mb-2"><i class="fa-solid fa-notes-medical me-2"></i>Office Remarks</h6>
                <p class="small mb-0 text-dark"><?php echo nl2br(displayValue($student['remarks'] ?? null, 'No special notes recorded.')); ?></p>
            </div>
        </div>

    </div>
</div>
<?php endif; ?>

<script>
// Search loading spinner activation
document.getElementById('searchForm').addEventListener('submit', function() {
    const btn = document.getElementById('searchBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Searching...';
});

// Client-side CSV/Excel exporter
function exportExcel() {
    let csv = "Admission No,Roll No,Student Name,Academic Type,Class,Father Name,CNIC\n";
    <?php if (!empty($searchResults)): foreach ($searchResults as $r): ?>
        csv += "<?php echo $r['admission_no']; ?>,<?php echo $r['roll_no']; ?>,<?php echo $r['first_name'] . ' ' . $r['last_name']; ?>,<?php echo $r['academic_type']; ?>,<?php echo ($r['class_name'] ?? $r['school_class'] ?? '-') . ' - ' . ($r['section'] ?? $r['school_section'] ?? 'A'); ?>,<?php echo $r['father_name']; ?>,<?php echo $r['cnic_no']; ?>\n";
    <?php endforeach; endif; ?>

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "student_detail_dossier_report.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
