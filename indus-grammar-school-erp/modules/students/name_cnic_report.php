<?php
/**
 * Indus Grammar School ERP - Student Name / CNIC Report Module
 * Version 3.0.0
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();
$message = '';
$error = '';

// Retrieve Search Filters
$search_name = sanitize($_GET['search_name'] ?? '');
$search_cnic = sanitize($_GET['search_cnic'] ?? '');

$limit = 10;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$where = " WHERE 1=1";
$params = [];

if ($search_name !== '') {
    $where .= " AND (s.first_name LIKE :name_first OR s.last_name LIKE :name_last)";
    $params['name_first'] = '%' . $search_name . '%';
    $params['name_last'] = '%' . $search_name . '%';
}
if ($search_cnic !== '') {
    $where .= " AND d.cnic_no = :cnic";
    $params['cnic'] = $search_cnic;
}

$students = [];
$totalEntries = 0;

try {
    // Count query
    $stmtCount = $db->prepare("
        SELECT COUNT(*) 
        FROM students s
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
    ");
    $stmtCount->execute($params);
    $totalEntries = (int)$stmtCount->fetchColumn();

    // Data query
    $stmtData = $db->prepare("
        SELECT s.*, c.class_name, c.section,
               d.roll_no, d.cnic_no, d.father_name, d.father_cnic, d.father_mobile, d.mother_name, d.mother_mobile,
               d.current_address, d.city, d.blood_group, d.religion, d.nationality, d.doc_student_photo, d.academic_session,
               d.fee_admission, d.fee_monthly, d.fee_discount, d.remarks as reg_remarks, d.admission_date
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY s.admission_no ASC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmtData->bindValue($k, $v);
    }
    $stmtData->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();

    $students = $stmtData->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error querying Student Registry: " . $e->getMessage();
}

$totalPages = ceil($totalEntries / $limit);
if ($totalPages < 1) $totalPages = 1;

$pageTitle = 'Name / CNIC Report';
$breadcrumbActive = 'Student Registration';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Title Banner -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-address-book me-2 text-primary"></i>Student Name & CNIC Directory</h3>
    </div>
</div>

<!-- Alerts -->
<?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius: 12px;">
        <i class="fa-solid fa-circle-xmark me-2"></i><?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Search Card Panel -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Locate Student File</h6>
    <form method="GET" action="name_cnic_report.php" id="searchForm" class="row g-3">
        <div class="col-md-5">
            <label class="form-label small fw-semibold text-muted">Student Name</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-user"></i></span>
                <input type="text" class="form-control" name="search_name" value="<?php echo htmlspecialchars($search_name); ?>" placeholder="Enter Student Name">
            </div>
        </div>
        <div class="col-md-5">
            <label class="form-label small fw-semibold text-muted">Student B-Form / CNIC Number</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-secondary"><i class="fa-solid fa-passport"></i></span>
                <input type="text" class="form-control" name="search_cnic" value="<?php echo htmlspecialchars($search_cnic); ?>" placeholder="e.g. 35201-1234567-1">
            </div>
        </div>
        <div class="col-md-2 align-self-end text-end mt-4">
            <button type="submit" id="searchBtn" class="btn btn-primary btn-sm w-100 mb-1 px-4"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</button>
            <a href="name_cnic_report.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
        </div>
    </form>
</div>

<!-- Search Results Table -->
<div class="card border-0 shadow-sm bg-white d-print-none" style="border-radius:12px; overflow:hidden;">
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Admission Number</th>
                    <th>Student Name</th>
                    <th>Father Name</th>
                    <th>Student B-Form / CNIC</th>
                    <th>Academic Type</th>
                    <th>Class</th>
                    <th>Section</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-user-slash d-block fs-3 mb-2 opacity-50"></i>
                            No student found.
                        </td>
                    </tr>
                <?php else: foreach ($students as $row): ?>
                    <tr>
                        <td>
                            <div class="avatar-small border rounded-circle d-flex align-items-center justify-content-center bg-light" style="width: 38px; height: 38px; overflow:hidden;">
                                <?php if (!empty($row['doc_student_photo'])): ?>
                                    <img src="<?php echo APP_URL . '/' . $row['doc_student_photo']; ?>" style="width:100%; height:100%; object-fit:cover;">
                                <?php else: ?>
                                    <i class="fa-solid fa-user text-muted small"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><strong class="text-primary"><?php echo sanitize($row['admission_no']); ?></strong></td>
                        <td class="fw-bold"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo displayValue($row['father_name'] ?? $row['guardian_name']); ?></td>
                        <td class="font-monospace"><?php echo displayValue($row['cnic_no']); ?></td>
                        <td><span class="badge bg-secondary"><?php echo sanitize($row['academic_type']); ?></span></td>
                        <td><?php echo displayValue($row['class_name'] ?? $row['school_class']); ?></td>
                        <td><?php echo displayValue($row['section'] ?? $row['school_section']); ?></td>
                        <td>
                            <span class="badge badge-soft-success"><?php echo sanitize($row['status']); ?></span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="viewStudentDetails(<?php echo htmlspecialchars(json_encode($row)); ?>)" title="View Details">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printStudentDossier(<?php echo htmlspecialchars(json_encode($row)); ?>)" title="Print Dossier">
                                    <i class="fa-solid fa-print"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination (Hidden in print) -->
<?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4 mb-4 d-print-none">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search_name ? '&search_name='.$search_name : ''; ?><?php echo $search_cnic ? '&search_cnic='.$search_cnic : ''; ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search_name ? '&search_name='.$search_name : ''; ?><?php echo $search_cnic ? '&search_cnic='.$search_cnic : ''; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search_name ? '&search_name='.$search_name : ''; ?><?php echo $search_cnic ? '&search_cnic='.$search_cnic : ''; ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<!-- View Details Modal Panel -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:12px;">
            <div class="modal-header bg-light border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="detailsModalLabel"><i class="fa-solid fa-address-card text-primary me-2"></i>Student Registration Profile Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4" id="printableDossierArea">
                <!-- PRINT HEADER (ONLY visible during print output) -->
                <div class="d-none d-print-block text-center border-bottom pb-3 mb-4">
                    <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
                    <h5 class="text-secondary mb-2">Student Registration Profile Dossier</h5>
                    <span class="small text-muted">Print Date: <strong><?php echo date('M d, Y h:i A'); ?></strong></span>
                </div>

                <div class="row g-3">
                    <!-- Photo & Core placement -->
                    <div class="col-md-3 text-center border-end">
                        <div class="avatar-large border rounded mx-auto mb-3 bg-light d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; overflow: hidden;">
                            <img id="v-photo" src="" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                            <i id="v-photo-placeholder" class="fa-solid fa-user fs-1 text-secondary opacity-50"></i>
                        </div>
                        <h6 class="fw-bold text-primary mb-1" id="v-name"></h6>
                        <span class="badge bg-secondary mb-2" id="v-type"></span>
                    </div>
                    
                    <!-- Details rows -->
                    <div class="col-md-9">
                        <div class="row g-2">
                            <!-- Admission info header -->
                            <div class="col-12 border-bottom pb-1 mb-2"><h6 class="fw-bold text-secondary mb-0">Admission Information</h6></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Admission Number</span><strong class="text-dark" id="v-admission"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Admission Date</span><strong class="text-dark" id="v-admission-date"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Academic Session</span><strong class="text-dark" id="v-session"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Class</span><strong class="text-dark" id="v-class"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Section</span><strong class="text-dark" id="v-section"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Status</span><span class="badge bg-success" id="v-status"></span></div>

                            <!-- Student Info -->
                            <div class="col-12 border-bottom pb-1 mb-2 mt-3"><h6 class="fw-bold text-secondary mb-0">Student Information</h6></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Gender</span><strong class="text-dark" id="v-gender"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Date of Birth</span><strong class="text-dark" id="v-dob"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Blood Group</span><strong class="text-dark" id="v-blood"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Religion</span><strong class="text-dark" id="v-religion"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Nationality</span><strong class="text-dark" id="v-nationality"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Student B-Form / CNIC</span><strong class="text-dark font-monospace" id="v-cnic"></strong></div>

                            <!-- Parent Info -->
                            <div class="col-12 border-bottom pb-1 mb-2 mt-3"><h6 class="fw-bold text-secondary mb-0">Parent Information</h6></div>
                            <div class="col-md-6"><span class="text-muted small d-block">Father Name</span><strong class="text-dark" id="v-father-name"></strong></div>
                            <div class="col-md-6"><span class="text-muted small d-block">Father CNIC</span><strong class="text-dark font-monospace" id="v-father-cnic"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Father Mobile</span><strong class="text-dark" id="v-father-mobile"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Mother Name</span><strong class="text-dark" id="v-mother-name"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Mother Mobile</span><strong class="text-dark" id="v-mother-mobile"></strong></div>

                            <!-- Address Info -->
                            <div class="col-12 border-bottom pb-1 mb-2 mt-3"><h6 class="fw-bold text-secondary mb-0">Address Information</h6></div>
                            <div class="col-md-8"><span class="text-muted small d-block">Current Address</span><strong class="text-dark" id="v-address"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">City</span><strong class="text-dark" id="v-city"></strong></div>

                            <!-- Fee Info -->
                            <div class="col-12 border-bottom pb-1 mb-2 mt-3"><h6 class="fw-bold text-secondary mb-0">Fee Structure Details</h6></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Admission Fee</span><strong class="text-dark" id="v-fee-admission"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Monthly Fee</span><strong class="text-dark" id="v-fee-monthly"></strong></div>
                            <div class="col-md-4"><span class="text-muted small d-block">Discount</span><strong class="text-dark" id="v-fee-discount"></strong></div>

                            <!-- Remarks -->
                            <div class="col-12 border-bottom pb-1 mb-2 mt-3"><h6 class="fw-bold text-secondary mb-0">Remarks / Special Notes</h6></div>
                            <div class="col-12"><strong class="text-dark" id="v-remarks"></strong></div>
                        </div>
                    </div>
                </div>

                <!-- PRINT FOOTER (ONLY visible during print output) -->
                <div class="d-none d-print-block text-center mt-5 pt-3 border-top">
                    <span class="small text-muted">IGS Registry Profile © <?php echo date('Y'); ?></span>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" onclick="printDossierModal()"><i class="fa-solid fa-print me-2"></i>Print Dossier</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Search loading spinner activation
document.getElementById('searchForm').addEventListener('submit', function() {
    const btn = document.getElementById('searchBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Searching...';
});

const dModal = new bootstrap.Modal(document.getElementById("detailsModal"));
function viewStudentDetails(student) {
    document.getElementById("v-name").textContent = student.first_name + ' ' + student.last_name;
    document.getElementById("v-type").textContent = student.academic_type;
    document.getElementById("v-admission").textContent = student.admission_no;
    document.getElementById("v-admission-date").textContent = student.admission_date ? student.admission_date : '—';
    document.getElementById("v-session").textContent = student.academic_session ? student.academic_session : '—';
    document.getElementById("v-class").textContent = student.class_name ? student.class_name : (student.school_class ? student.school_class : '—');
    document.getElementById("v-section").textContent = student.section ? student.section : (student.school_section ? student.school_section : '—');
    document.getElementById("v-status").textContent = student.status;
    document.getElementById("v-gender").textContent = student.gender;
    document.getElementById("v-dob").textContent = student.date_of_birth;
    document.getElementById("v-blood").textContent = student.blood_group ? student.blood_group : '—';
    document.getElementById("v-religion").textContent = student.religion ? student.religion : '—';
    document.getElementById("v-nationality").textContent = student.nationality ? student.nationality : '—';
    document.getElementById("v-cnic").textContent = student.cnic_no ? student.cnic_no : '—';
    document.getElementById("v-father-name").textContent = student.father_name ? student.father_name : '—';
    document.getElementById("v-father-cnic").textContent = student.father_cnic ? student.father_cnic : '—';
    document.getElementById("v-father-mobile").textContent = student.father_mobile ? student.father_mobile : '—';
    document.getElementById("v-mother-name").textContent = student.mother_name ? student.mother_name : '—';
    document.getElementById("v-mother-mobile").textContent = student.mother_mobile ? student.mother_mobile : '—';
    document.getElementById("v-address").textContent = student.current_address ? student.current_address : (student.address ? student.address : '—');
    document.getElementById("v-city").textContent = student.city ? student.city : '—';
    
    // Fee structures
    document.getElementById("v-fee-admission").textContent = student.fee_admission ? 'Rs. ' + parseFloat(student.fee_admission).toLocaleString() : 'Rs. 0';
    document.getElementById("v-fee-monthly").textContent = student.fee_monthly ? 'Rs. ' + parseFloat(student.fee_monthly).toLocaleString() : 'Rs. 0';
    document.getElementById("v-fee-discount").textContent = student.fee_discount ? 'Rs. ' + parseFloat(student.fee_discount).toLocaleString() : 'Rs. 0';
    document.getElementById("v-remarks").textContent = student.reg_remarks ? student.reg_remarks : 'No special notes logged.';

    // Avatar setup
    const photoEl = document.getElementById("v-photo");
    const photoPlaceholderEl = document.getElementById("v-photo-placeholder");
    if (student.doc_student_photo) {
        photoEl.src = "<?php echo APP_URL; ?>/" + student.doc_student_photo;
        photoEl.style.display = "block";
        photoPlaceholderEl.style.display = "none";
    } else {
        photoEl.style.display = "none";
        photoPlaceholderEl.style.display = "block";
    }

    dModal.show();
}

// Print single student dossier
function printDossierModal() {
    const printContent = document.getElementById("printableDossierArea").innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <div style="padding:40px; font-family: sans-serif; background-color: #fff;">
            ${printContent}
        </div>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload();
}

// Direct action row print handler
function printStudentDossier(student) {
    viewStudentDetails(student);
    setTimeout(printDossierModal, 300);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
