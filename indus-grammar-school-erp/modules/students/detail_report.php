<?php
/**
 * Indus Grammar School ERP - Student Detail Report
 * Version 2.0.0
 */

$pageTitle = 'Student Detail Report';
$breadcrumbActive = 'Student Registration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

// Check search form values
$search_admission = sanitize($_GET['search_admission'] ?? '');
$search_roll = sanitize($_GET['search_roll'] ?? '');
$search_name = sanitize($_GET['search_name'] ?? '');
$search_father = sanitize($_GET['search_father'] ?? '');
$search_cnic = sanitize($_GET['search_cnic'] ?? '');
$search_class = (int)($_GET['search_class'] ?? 0);
$search_section = sanitize($_GET['search_section'] ?? '');
$search_session = sanitize($_GET['search_session'] ?? '');
$search_status = sanitize($_GET['search_status'] ?? '');
$search_academic_type = sanitize($_GET['search_academic_type'] ?? '');
$search_academy_program = sanitize($_GET['search_academy_program'] ?? '');

$classes = [];
try {
    $classes = $db->query("SELECT * FROM classes ORDER BY class_name ASC")->fetchAll();
} catch (Exception $e) {}

// Build dynamic search query
$searchResults = [];
$showResults = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (!empty($_GET) || isset($_GET['search_btn']))) {
    $showResults = true;
    try {
        $sql = "
            SELECT s.id, s.admission_no, s.first_name, s.last_name, s.status, s.academic_type, s.school_class, s.school_section, s.academy_program, s.academy_batch, c.class_name, c.section,
                   d.roll_no, d.father_name, d.cnic_no, d.academic_session
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN student_registration_details d ON s.id = d.student_id
            WHERE 1=1
        ";
        $params = [];

        if ($search_admission) {
            $sql .= " AND s.admission_no = :admission_no";
            $params['admission_no'] = $search_admission;
        }

        if ($search_roll) {
            $sql .= " AND d.roll_no = :roll_no";
            $params['roll_no'] = $search_roll;
        }
        if ($search_name) {
            $sql .= " AND (s.first_name LIKE :name OR s.last_name LIKE :name)";
            $params['name'] = '%' . $search_name . '%';
        }
        if ($search_father) {
            $sql .= " AND d.father_name LIKE :father";
            $params['father'] = '%' . $search_father . '%';
        }
        if ($search_cnic) {
            $sql .= " AND d.cnic_no = :cnic";
            $params['cnic'] = $search_cnic;
        }
        if ($search_class > 0) {
            $sql .= " AND s.class_id = :class_id";
            $params['class_id'] = $search_class;
        }
        if ($search_section) {
            $sql .= " AND c.section = :section";
            $params['section'] = $search_section;
        }
        if ($search_session) {
            $sql .= " AND d.academic_session = :session";
            $params['session'] = $search_session;
        }
        if ($search_status) {
            $sql .= " AND s.status = :status";
            $params['status'] = $search_status;
        }
        if ($search_academic_type) {
            $sql .= " AND s.academic_type = :academic_type";
            $params['academic_type'] = $search_academic_type;
        }
        if ($search_academy_program) {
            $sql .= " AND s.academy_program = :academy_program";
            $params['academy_program'] = $search_academy_program;
        }

        $sql .= " ORDER BY s.admission_no DESC LIMIT 50";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $searchResults = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Student Detail Report search error: " . $e->getMessage());
    }
}

// Load selected student
$selectedStudentId = isset($_GET['view_id']) ? (int)$_GET['view_id'] : 0;
$student = null;
$details = null;

if ($selectedStudentId > 0) {
    try {
        $stmt = $db->prepare("SELECT s.*, c.class_name, c.section FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
        $stmt->execute([$selectedStudentId]);
        $student = $stmt->fetch();
        
        if ($student) {
            $stmtDet = $db->prepare("SELECT * FROM student_registration_details WHERE student_id = ?");
            $stmtDet->execute([$selectedStudentId]);
            $details = $stmtDet->fetch();
        }
    } catch (Exception $e) {
        error_log("Student Detail Report view load error: " . $e->getMessage());
    }
}
?>

<!-- Header -->
<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-file-invoice me-2 text-primary"></i>Student Detail Report</h3>
    </div>
</div>

<!-- Search Panel Grid -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4" style="border-radius: 12px;">
    <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Search Registered Students</h5>
    <form method="GET" action="detail_report.php" class="row g-3">
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Admission Number</label>
            <input type="text" class="form-select-sm form-control" name="search_admission" value="<?php echo $search_admission; ?>" placeholder="e.g. IGS-AD-2026-0001">
        </div>

        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Roll Number</label>
            <input type="text" class="form-select-sm form-control" name="search_roll" value="<?php echo $search_roll; ?>" placeholder="e.g. IGS-ROLL-2026-0001">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Student Name</label>
            <input type="text" class="form-select-sm form-control" name="search_name" value="<?php echo $search_name; ?>" placeholder="Search name...">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Father Name</label>
            <input type="text" class="form-select-sm form-control" name="search_father" value="<?php echo $search_father; ?>" placeholder="Father's name...">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">CNIC / B-Form</label>
            <input type="text" class="form-select-sm form-control" name="search_cnic" value="<?php echo $search_cnic; ?>" placeholder="35201-XXXXXXX-X">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Academic Type</label>
            <select class="form-select form-select-sm" name="search_academic_type">
                <option value="">All</option>
                <option value="School" <?php echo ($search_academic_type === 'School') ? 'selected' : ''; ?>>School</option>
                <option value="Academy" <?php echo ($search_academic_type === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                <option value="School + Academy" <?php echo ($search_academic_type === 'School + Academy') ? 'selected' : ''; ?>>School + Academy</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Academy Program</label>
            <select class="form-select form-select-sm" name="search_academy_program">
                <option value="">All</option>
                <?php foreach(['9th Entry Test', '10th Entry Test', '1st Year Entry Test', '2nd Year Entry Test', 'MDCAT', 'ECAT', 'ICS Preparation', 'Pre-Medical', 'Pre-Engineering', 'Computer Courses', 'English Language', 'Spoken English', 'IELTS', 'Other'] as $prog): ?>
                    <option value="<?php echo $prog; ?>" <?php echo ($search_academy_program === $prog) ? 'selected' : ''; ?>><?php echo $prog; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">School Class</label>
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
            <label class="form-label small fw-semibold text-muted">School Section</label>
            <input type="text" class="form-select-sm form-control" name="search_section" value="<?php echo $search_section; ?>" placeholder="A, B, C...">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Session</label>
            <select class="form-select form-select-sm" name="search_session">
                <option value="">All</option>
                <option value="2026-2027" <?php echo ($search_session === '2026-2027') ? 'selected' : ''; ?>>2026-2027</option>
                <option value="2025-2026" <?php echo ($search_session === '2025-2026') ? 'selected' : ''; ?>>2025-2026</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Status</label>
            <select class="form-select form-select-sm" name="search_status">
                <option value="">All</option>
                <option value="Active" <?php echo ($search_status === 'Active') ? 'selected' : ''; ?>>Active</option>
                <option value="Left" <?php echo ($search_status === 'Left') ? 'selected' : ''; ?>>Left</option>
                <option value="Graduated" <?php echo ($search_status === 'Graduated') ? 'selected' : ''; ?>>Graduated</option>
            </select>
        </div>
        <div class="col-12 text-end">
            <button type="submit" name="search_btn" class="btn btn-primary px-4"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
            <a href="detail_report.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Search Results Table Grid -->
<?php if ($showResults): ?>
<div class="card border border-light shadow-sm bg-white p-4 mb-4" style="border-radius:12px;">
    <h5 class="fw-bold text-secondary mb-3">Search Results (<?php echo count($searchResults); ?> matching)</h5>
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle">
            <thead>
                <tr>
                    <th>Adm. No</th>
                    <th>Name</th>
                    <th>Father Name</th>
                    <th>Class</th>
                    <th>Roll No</th>
                    <th>CNIC / B-Form</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($searchResults)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No student profiles match your search criteria.</td></tr>
                <?php else: foreach ($searchResults as $row): ?>
                    <tr>
                        <td class="fw-bold text-dark"><?php echo sanitize($row['admission_no']); ?></td>
                        <td><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo sanitize($row['father_name'] ?: '-'); ?></td>
                        <td><?php echo sanitize($row['class_name'] . ' - ' . $row['section']); ?></td>
                        <td><?php echo sanitize($row['roll_no'] ?: '-'); ?></td>
                        <td><?php echo sanitize($row['cnic_no'] ?: '-'); ?></td>
                        <td><span class="badge bg-<?php echo ($row['status'] === 'Active') ? 'success' : 'secondary'; ?>-soft"><?php echo sanitize($row['status']); ?></span></td>
                        <td class="text-end">
                            <a href="detail_report.php?view_id=<?php echo $row['id']; ?>&search_admission=<?php echo urlencode($search_admission); ?>&search_name=<?php echo urlencode($search_name); ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-eye me-1"></i>View Profile</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
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
            <button class="btn btn-outline-danger" onclick="downloadPDF()"><i class="fa-solid fa-file-pdf me-2"></i>Download PDF</button>
            <button class="btn btn-outline-success" onclick="exportExcel()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
        </div>
    </div>

    <!-- Student Photo & Identity Overview Section -->
    <div class="row align-items-center g-4 border-bottom pb-4 mb-4">
        <div class="col-md-2 text-center">
            <div class="border rounded bg-light p-2 mx-auto" style="width: 140px; height: 140px; display: flex; align-items: center; justify-content: center;">
                <?php if (!empty($details['doc_student_photo'])): ?>
                    <img src="<?php echo APP_URL . '/' . $details['doc_student_photo']; ?>" class="img-fluid rounded" alt="Student Photo" style="max-height: 100%;">
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
                    <div class="small text-muted">Academic Session: <strong><?php echo sanitize($details['academic_session'] ?: '-'); ?></strong></div>
                    <div class="small text-muted">Campus Location: <strong><?php echo sanitize($details['campus'] ?: '-'); ?></strong></div>
                    <div class="small text-muted">Active Status: <span class="badge bg-success-soft"><?php echo sanitize($student['status']); ?></span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 12 detailed Read-Only sections grid -->
    <div class="row g-4">
        
        <!-- Admission Details -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-school me-2"></i>Academic Placement</h6>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Academic Type:</td><td class="fw-semibold"><span class="badge bg-secondary"><?php echo sanitize($student['academic_type'] ?? 'School'); ?></span></td></tr>
                    <tr><td class="text-muted">Admission Date:</td><td class="fw-semibold"><?php echo sanitize($details['admission_date'] ?: '-'); ?></td></tr>
                    <tr><td class="text-muted">Roll Number:</td><td class="fw-semibold"><?php echo sanitize($details['roll_no'] ?: '-'); ?></td></tr>
                    <?php if (($student['academic_type'] ?? 'School') !== 'Academy'): ?>
                        <tr><td class="text-muted">School Class:</td><td class="fw-semibold"><?php echo sanitize(($student['class_name'] ?? $student['school_class'] ?? '-') . ' - ' . ($student['section'] ?? $student['school_section'] ?? 'A')); ?></td></tr>
                    <?php endif; ?>
                    <?php if (($student['academic_type'] ?? 'School') !== 'School'): ?>
                        <tr><td class="text-muted">Academy Program:</td><td class="fw-semibold"><?php echo sanitize($student['academy_program'] ?? '-'); ?></td></tr>
                        <tr><td class="text-muted">Academy Batch:</td><td class="fw-semibold"><?php echo sanitize($student['academy_batch'] ?? '-'); ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Personal info -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-user me-2"></i>Personal Information</h6>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Gender:</td><td class="fw-semibold"><?php echo sanitize($student['gender']); ?></td></tr>
                    <tr><td class="text-muted">DOB:</td><td class="fw-semibold"><?php echo sanitize($student['date_of_birth']); ?></td></tr>
                    <tr><td class="text-muted">Blood Group:</td><td class="fw-semibold"><?php echo sanitize($details['blood_group'] ?: '-'); ?></td></tr>
                    <tr><td class="text-muted">Religion:</td><td class="fw-semibold"><?php echo sanitize($details['religion'] ?: '-'); ?></td></tr>
                    <tr><td class="text-muted">CNIC / B-Form:</td><td class="fw-semibold"><?php echo sanitize($details['cnic_no'] ?: '-'); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Parent Info -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-user-group me-2"></i>Parent Details</h6>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Father Name:</td><td class="fw-semibold"><?php echo sanitize($details['father_name'] ?: '-'); ?></td></tr>
                    <tr><td class="text-muted">Father CNIC:</td><td class="fw-semibold"><?php echo sanitize($details['father_cnic'] ?: '-'); ?></td></tr>
                    <tr><td class="text-muted">Father Mobile:</td><td class="fw-semibold"><?php echo sanitize($details['father_mobile'] ?: '-'); ?></td></tr>
                    <tr><td class="text-muted">Mother Name:</td><td class="fw-semibold"><?php echo sanitize($details['mother_name'] ?: '-'); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Guardian Info -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-user-shield me-2"></i>Guardian Information</h6>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Guardian Name:</td><td class="fw-semibold"><?php echo sanitize($student['guardian_name']); ?></td></tr>
                    <tr><td class="text-muted">Relationship:</td><td class="fw-semibold"><?php echo sanitize($details['guardian_relationship'] ?: '-'); ?></td></tr>
                    <tr><td class="text-muted">Guardian Mobile:</td><td class="fw-semibold"><?php echo sanitize($student['guardian_phone']); ?></td></tr>
                    <tr><td class="text-muted">Guardian CNIC:</td><td class="fw-semibold"><?php echo sanitize($details['guardian_cnic'] ?: '-'); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Addresses -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-location-dot me-2"></i>Contact Addresses</h6>
                <p class="small text-muted mb-1">Current Address: <strong class="text-dark d-block"><?php echo sanitize($details['current_address'] ?: '-'); ?></strong></p>
                <p class="small text-muted mb-0">Permanent Address: <strong class="text-dark d-block"><?php echo sanitize($details['permanent_address'] ?: '-'); ?></strong></p>
            </div>
        </div>

        <!-- Prior Academic -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-graduation-cap me-2"></i>Prior Academic History</h6>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Prev School:</td><td class="fw-semibold"><?php echo sanitize($details['prev_school'] ?: '-'); ?></td></tr>
                    <tr><td class="text-muted">Prev Class:</td><td class="fw-semibold"><?php echo sanitize($details['prev_class'] ?: '-'); ?></td></tr>
                    <tr><td class="text-muted">Prev Result:</td><td class="fw-semibold"><?php echo sanitize($details['prev_result'] ?: '-'); ?></td></tr>
                    <tr><td class="text-muted">Leaving Cert:</td><td class="fw-semibold"><?php echo sanitize($details['leaving_cert_no'] ?: '-'); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Medical Details -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-heart-pulse me-2"></i>Medical Profile</h6>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Allergies:</td><td class="fw-semibold text-danger"><?php echo sanitize($details['allergies'] ?: 'None'); ?></td></tr>
                    <tr><td class="text-muted">Disability:</td><td class="fw-semibold text-danger"><?php echo sanitize($details['disability'] ?: 'None'); ?></td></tr>
                    <tr><td class="text-muted">Emergency Cont.:</td><td class="fw-semibold"><?php echo sanitize($details['emergency_contact'] ?: '-'); ?></td></tr>
                    <tr><td class="text-muted">Doctor Details:</td><td class="fw-semibold"><?php echo sanitize($details['doctor_name'] ?: '-'); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Transport details -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-bus me-2"></i>School Transport Details</h6>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Transport Active:</td><td class="fw-semibold"><?php echo (!empty($details['transport_required'])) ? 'Yes' : 'No'; ?></td></tr>
                    <tr><td class="text-muted">Route Details:</td><td class="fw-semibold"><?php echo sanitize($details['transport_route'] ?: '-'); ?></td></tr>
                    <tr><td class="text-muted">Pickup Point:</td><td class="fw-semibold"><?php echo sanitize($details['pickup_point'] ?: '-'); ?></td></tr>
                    <tr><td class="text-muted">Driver Details:</td><td class="fw-semibold"><?php echo sanitize($details['transport_driver'] ?: '-'); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Fee Plan -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-receipt me-2"></i>Financial & Fee Plan</h6>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Tuition Fee Plan:</td><td class="fw-semibold"><?php echo sanitize($details['fee_plan'] ?: 'Regular Plan'); ?></td></tr>
                    <tr><td class="text-muted">Monthly Fee:</td><td class="fw-semibold">Rs. <?php echo number_format($details['fee_monthly'] ?? 0); ?></td></tr>
                    <tr><td class="text-muted">Discount given:</td><td class="fw-semibold">Rs. <?php echo number_format($details['fee_discount'] ?? 0); ?></td></tr>
                    <tr><td class="text-muted">Scholarships:</td><td class="fw-semibold">Rs. <?php echo number_format($details['fee_scholarship'] ?? 0); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Sponsors details -->
        <div class="col-md-6 col-xl-4">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-handshake-angle me-2"></i>Sponsor & Charity Support</h6>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Sponsorship:</td><td class="fw-semibold"><?php echo (!empty($details['sponsor_required'])) ? 'Yes' : 'No'; ?></td></tr>
                    <tr><td class="text-muted">Sponsor Name:</td><td class="fw-semibold"><?php echo sanitize($details['sponsor_name'] ?: '-'); ?></td></tr>
                    <tr><td class="text-muted">Org / Contact:</td><td class="fw-semibold"><?php echo sanitize($details['sponsor_org'] ?: '-'); ?></td></tr>
                    <tr><td class="text-muted">Sponsor Amount:</td><td class="fw-semibold">Rs. <?php echo number_format($details['sponsor_amount'] ?? 0); ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Attached Documents Uploaded links -->
        <div class="col-md-6 col-xl-8">
            <div class="p-3 border rounded bg-light h-100">
                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-file-arrow-down me-2"></i>Attached Files & Documents</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php
                    $docs = [
                        'doc_father_cnic' => 'Father CNIC Card',
                        'doc_mother_cnic' => 'Mother CNIC Card',
                        'doc_bform' => 'B-Form Doc',
                        'doc_birth_cert' => 'Birth Certificate',
                        'doc_leaving_cert' => 'School Leaving Cert',
                        'doc_prev_result' => 'Previous Result',
                        'doc_medical_cert' => 'Medical Certificate',
                        'doc_other' => 'Other Attachment'
                    ];
                    
                    $hasDocs = false;
                    foreach ($docs as $key => $label) {
                        if (!empty($details[$key])) {
                            $hasDocs = true;
                            echo '<a href="' . APP_URL . '/' . $details[$key] . '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-cloud-arrow-down me-1"></i>' . $label . '</a>';
                        }
                    }
                    if (!$hasDocs) {
                        echo '<p class="small text-muted mb-0">No document files uploaded for this student registration profile.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>

    </div>
</div>
<?php elseif ($selectedStudentId > 0): ?>
    <div class="text-center py-5 bg-white border border-light shadow-sm" style="border-radius:12px;">
        <i class="fa-solid fa-circle-exclamation fs-1 text-muted opacity-50 mb-3 d-block"></i>
        <h5 class="text-muted">Student profile dossier failed to load.</h5>
    </div>
<?php endif; ?>

<!-- JavaScript utilities for PDF download and Excel exporting -->
<script>
// Mock PDF downloading trigger
function downloadPDF() {
    alert("Dossier profile formatting for PDF download. Press Ctrl+P (Cmd+P) to print to local system PDF printer.");
    window.print();
}

// Client-side export to Excel converter
function exportExcel() {
    const studentName = "<?php echo $student ? sanitize($student['first_name'] . '_' . $student['last_name']) : 'student'; ?>";
    let csv = "Field,Registered Value\n";
    
    // Select detail rows inside the view panel
    const rows = document.querySelectorAll("#printableDossier table tr");
    rows.forEach(tr => {
        const cols = tr.querySelectorAll("td");
        if(cols.length === 2) {
            let label = cols[0].textContent.replace(/,/g, " ").trim();
            let value = cols[1].textContent.replace(/,/g, " ").trim();
            csv += label + "," + value + "\n";
        }
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", studentName + "_dossier.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
