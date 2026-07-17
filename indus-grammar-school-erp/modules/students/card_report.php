<?php
/**
 * Indus Grammar School ERP - Student ID Card Module
 * Version 5.1.0
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('student_view');

// Role-Based Access Control: Only Super Admin, School Admin, and Receptionist can access/print ID cards
$userRole = $_SESSION['role_code'] ?? '';
$allowedRoles = [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'receptionist'];
if (!in_array($userRole, $allowedRoles)) {
    $_SESSION['flash_error'] = "You do not have permission to access the Student ID Card Module.";
    header("Location: " . APP_URL . "/dashboard.php");
    exit;
}

$db = Database::getConnection();
$message = '';
$error = '';

// Load class and section lists for batch printing filters
$classesList = [];
try {
    $classesList = $db->query("SELECT DISTINCT class_name FROM classes ORDER BY class_name ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$sectionsList = ['A', 'B', 'C', 'D', 'E'];
try {
    $dbSecs = $db->query("SELECT DISTINCT section FROM classes WHERE section != '' ORDER BY section ASC")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dbSecs as $ds) {
        if (!in_array($ds, $sectionsList)) {
            $sectionsList[] = $ds;
        }
    }
} catch (Exception $e) {}

// Retrieve search query and batch filters
$singleSearchQuery = sanitize($_GET['single_search'] ?? '');
$batchAcademicType = sanitize($_GET['batch_academic_type'] ?? '');
$batchClass = sanitize($_GET['batch_class'] ?? '');
$batchSection = sanitize($_GET['batch_section'] ?? '');

$where = " WHERE s.status = 'Active'";
$params = [];

// Apply single search query
if ($singleSearchQuery !== '') {
    $cleanQuery = str_replace('-', '', $singleSearchQuery);
    $where .= " AND (s.admission_no = :query OR d.cnic_no = :query OR REPLACE(d.cnic_no, '-', '') = :cleanQuery)";
    $params['query'] = $singleSearchQuery;
    $params['cleanQuery'] = $cleanQuery;
}

// Apply batch filters
if ($batchAcademicType !== '') {
    $where .= " AND s.academic_type = :academic_type";
    $params['academic_type'] = $batchAcademicType;
}
if ($batchClass !== '') {
    $where .= " AND (c.class_name = :class1 OR s.school_class = :class2)";
    $params['class1'] = $batchClass;
    $params['class2'] = $batchClass;
}
if ($batchSection !== '') {
    $where .= " AND (c.section = :section1 OR s.school_section = :section2)";
    $params['section1'] = $batchSection;
    $params['section2'] = $batchSection;
}

// Query students
$students = [];
try {
    $sql = "
        SELECT s.*, c.class_name, c.section,
               d.roll_no, d.blood_group, d.emergency_contact, d.academic_session, d.doc_student_photo,
               d.father_name, d.father_mobile, d.current_address,
               d.admission_date, d.cnic_no
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY s.admission_no ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($students) && $singleSearchQuery !== '') {
        $error = "No student found.";
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

$pageTitle = 'Student ID Card Generator';
$breadcrumbActive = 'ID Cards';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Premium Vertical PVC ID Card Stylesheets -->
<style>
/* PVC Card Dimension specifications: CR80 standard (~86mm x 54mm) -> vertical layout approx 260px x 410px */
.id-card-wrap {
    display: inline-block;
    margin: 15px;
    vertical-align: top;
    text-align: left;
    page-break-inside: avoid;
}

.pvc-card-side {
    width: 260px;
    height: 410px;
    border: 1px solid #c8d2e6;
    border-radius: 12px;
    background-color: #ffffff;
    position: relative;
    overflow: hidden;
    font-family: 'Outfit', 'Inter', sans-serif;
    box-shadow: 0 4px 10px rgba(30, 58, 138, 0.08);
    display: inline-block;
    vertical-align: top;
    background-image: radial-gradient(circle at 10% 20%, rgba(239, 246, 255, 0.5) 0%, rgba(255, 255, 255, 0.5) 90%);
}

/* Front Side Styling */
.pvc-card-front {
    border-top: 6px solid #1e3a8a;
}

.pvc-card-front .header-band {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    color: #ffffff;
    padding: 10px 5px;
    text-align: center;
    position: relative;
}

.pvc-card-front .header-band img.school-logo {
    width: 32px;
    height: 32px;
    object-fit: contain;
    margin-bottom: 2px;
}

.pvc-card-front .header-band h6 {
    margin: 0;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.5px;
}

.pvc-card-front .header-band p.subtitle {
    margin: 0;
    font-size: 0.55rem;
    opacity: 0.9;
    text-transform: uppercase;
    font-weight: 600;
}

.pvc-card-front .avatar-box {
    width: 85px;
    height: 85px;
    border-radius: 50%;
    border: 3px solid #3b82f6;
    overflow: hidden;
    margin: 12px auto 8px auto;
    background-color: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pvc-card-front .avatar-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pvc-card-front .student-name-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e3a8a;
    text-align: center;
    margin-bottom: 8px;
    padding: 0 10px;
    text-transform: capitalize;
}

.pvc-card-front .info-table {
    width: 100%;
    padding: 0 12px;
    font-size: 0.65rem;
    margin-bottom: 5px;
}

.pvc-card-front .info-table td {
    padding: 2px 0;
}

.pvc-card-front .info-table td.lbl {
    color: #6b7280;
    font-weight: 600;
    width: 45%;
}

.pvc-card-front .info-table td.val {
    color: #1f2937;
    font-weight: 700;
}

.pvc-card-front .footer-strip {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: #f8fafc;
    border-top: 1px solid #e2e8f0;
    padding: 8px 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pvc-card-front .footer-strip .sign-box {
    text-align: center;
    font-size: 0.45rem;
    color: #6b7280;
    font-weight: 600;
}

.pvc-card-front .footer-strip .sign-box .sign-line {
    border-bottom: 0.5px solid #6b7280;
    width: 50px;
    height: 10px;
    margin-bottom: 2px;
}

.pvc-card-front .status-badge {
    background-color: #dcfce7;
    color: #15803d;
    font-size: 0.55rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 50px;
    border: 0.5px solid #bbf7d0;
}

/* Back Side Styling */
.pvc-card-back {
    border-top: 6px solid #fbbf24;
    padding: 12px;
}

.pvc-card-back h6.back-title {
    color: #1e3a8a;
    font-weight: 700;
    font-size: 0.75rem;
    margin-bottom: 8px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 4px;
}

.pvc-card-back .back-info {
    font-size: 0.65rem;
    margin-bottom: 10px;
}

.pvc-card-back .back-info p {
    margin: 0 0 4px 0;
}

.pvc-card-back .back-info strong {
    color: #111827;
}

.pvc-card-back .qr-frame-box {
    text-align: center;
    margin-top: 5px;
    margin-bottom: 8px;
}

.pvc-card-back .qr-frame-box img {
    width: 60px;
    height: 60px;
    border: 1px solid #e2e8f0;
    padding: 2px;
    background-color: #ffffff;
}

.pvc-card-back .rules-box {
    border: 1px solid #e2e8f0;
    background-color: #f8fafc;
    border-radius: 6px;
    padding: 8px;
    font-size: 0.55rem;
    color: #4b5563;
    margin-bottom: 12px;
}

.pvc-card-back .rules-box ol {
    margin: 0;
    padding-left: 12px;
}

.pvc-card-back .rules-box li {
    margin-bottom: 3px;
}

.pvc-card-back .school-address-box {
    position: absolute;
    bottom: 8px;
    left: 12px;
    right: 12px;
    border-top: 1px solid #e2e8f0;
    padding-top: 6px;
    font-size: 0.5rem;
    color: #6b7280;
    text-align: center;
}

.pvc-card-back .school-address-box p {
    margin: 0;
    line-height: 1.2;
}

/* Printing styles */
@media print {
    body > *:not(#printSection) {
        display: none !important;
    }
    #printSection, #printSection * {
        display: block !important;
    }
    #printSection {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        text-align: center;
        background: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .id-card-wrap {
        border: none !important;
        margin: 10px !important;
        display: inline-block !important;
        page-break-inside: avoid !important;
    }
    .pvc-card-side {
        width: 53.98mm !important;
        height: 85.60mm !important;
        border: 1px solid #cbd5e1 !important;
        box-shadow: none !important;
    }
}
</style>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-id-card me-2 text-primary"></i>Student ID Card Generator</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <span class="badge bg-light text-dark border p-2"><i class="fa-solid fa-user-shield me-1 text-primary"></i>Role: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $userRole))); ?></span>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4 alert-dismissible fade show d-print-none" role="alert" style="border-radius:10px;">
        <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="this.parentElement.remove()"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4 alert-dismissible fade show d-print-none" role="alert" style="border-radius:10px;">
        <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="this.parentElement.remove()"></button>
    </div>
<?php endif; ?>

<!-- Search and Batch Filter Panel (Unified, Hidden in Print) -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Search & Filter ID Cards</h6>
    <form method="GET" action="card_report.php" id="filterForm" class="row g-3">
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Academic Type</label>
            <select class="form-select form-select-sm" name="batch_academic_type">
                <option value="">All Types</option>
                <option value="School" <?php echo ($batchAcademicType === 'School') ? 'selected' : ''; ?>>School</option>
                <option value="Academy" <?php echo ($batchAcademicType === 'Academy') ? 'selected' : ''; ?>>Academy</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Class</label>
            <select class="form-select form-select-sm" name="batch_class">
                <option value="">All Classes</option>
                <?php foreach ($classesList as $cls): ?>
                    <option value="<?php echo $cls; ?>" <?php echo ($batchClass === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Section</label>
            <select class="form-select form-select-sm" name="batch_section">
                <option value="">All Sections</option>
                <?php foreach ($sectionsList as $sec): ?>
                    <option value="<?php echo $sec; ?>" <?php echo ($batchSection === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Search Student ID / CNIC</label>
            <input type="text" class="form-control form-control-sm" name="single_search" value="<?php echo htmlspecialchars($singleSearchQuery); ?>" placeholder="e.g. IGS-2026-0001">
        </div>
        <div class="col-md-2 text-end align-self-end">
            <button type="submit" id="searchBtn" class="btn btn-sm btn-primary w-100 mb-1"><i class="fa-solid fa-search me-1"></i>Search</button>
            <a href="card_report.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
        </div>
    </form>
</div>

<?php if (!empty($students)): ?>
    
    <!-- Student selection table (Hidden in Print) -->
    <div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-list me-2"></i>Select Students for PVC printing (<?php echo count($students); ?> found)</h6>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="selectAll" checked onchange="toggleAllCheckboxes(this)">
                <label class="form-check-label small fw-bold text-muted" for="selectAll">Select All</label>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="50">Select</th>
                        <th>Photo</th>
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $row): ?>
                        <tr>
                            <td>
                                <input class="form-check-input student-chk" type="checkbox" value="<?php echo $row['id']; ?>" id="chk_<?php echo $row['id']; ?>" checked onchange="toggleCardPreview(<?php echo $row['id']; ?>, this.checked)">
                            </td>
                            <td>
                                <div class="avatar-small border rounded-circle d-flex align-items-center justify-content-center bg-light" style="width: 36px; height: 36px; overflow:hidden;">
                                    <?php if (!empty($row['doc_student_photo'])): ?>
                                        <img src="<?php echo APP_URL . '/' . $row['doc_student_photo']; ?>" style="width:100%; height:100%; object-fit:cover;" onerror="this.onerror=null; this.src='<?php echo APP_URL; ?>/assets/images/default_student.png';">
                                    <?php else: ?>
                                        <img src="<?php echo APP_URL; ?>/assets/images/default_student.png" style="width:100%; height:100%; object-fit:cover;" onerror="this.onerror=null; this.src='https://placehold.co/100x100?text=No+Photo';">
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><strong class="text-primary"><?php echo sanitize($row['admission_no']); ?></strong></td>
                            <td class="fw-bold"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><?php echo sanitize($row['class_name'] ?? $row['school_class'] ?? '—'); ?></td>
                            <td><?php echo sanitize($row['section'] ?? $row['school_section'] ?? '—'); ?></td>
                            <td><span class="badge bg-secondary"><?php echo sanitize($row['academic_type']); ?></span></td>
                            <td><span class="badge bg-success">Active</span></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="previewSingleCard(<?php echo htmlspecialchars(json_encode($row)); ?>)"><i class="fa-solid fa-eye me-1"></i>Preview Card</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Action Buttons -->
        <div class="border-top pt-3 mt-4 text-end">
            <button class="btn btn-primary px-4 rounded-pill shadow-sm me-2" onclick="printSelectedCards()"><i class="fa-solid fa-print me-2"></i>Print Selected Cards</button>
            <button class="btn btn-outline-danger px-4 rounded-pill shadow-sm" onclick="downloadSelectedPDF()"><i class="fa-solid fa-file-pdf me-2"></i>Download Selected PDF</button>
        </div>
    </div>

    <!-- PVC ID Cards Print rendering queue area -->
    <div class="bg-light border p-4 shadow-inner text-center d-print-none" style="border-radius: 12px; min-height: 200px;">
        <h5 class="fw-bold text-secondary mb-4"><i class="fa-solid fa-print me-2"></i>Identity Cards Print Queue Preview</h5>
        <div id="queueZone">
            <?php foreach ($students as $row): 
                $qrRawData = "Student ID: " . $row['admission_no'] . "\n" .
                             "Name: " . $row['first_name'] . " " . $row['last_name'] . "\n" .
                             "Class: " . ($row['class_name'] ?? $row['school_class'] ?? '—') . "\n" .
                             "Section: " . ($row['section'] ?? $row['school_section'] ?? '—') . "\n" .
                             "Academic Type: " . $row['academic_type'];
                $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrRawData);
            ?>
                <!-- ID CARD WRAPPER FRAME (Front and Back side) -->
                <div class="id-card-wrap" id="card_wrapper_<?php echo $row['id']; ?>" data-student='<?php echo htmlspecialchars(json_encode($row)); ?>'>
                    
                    <!-- Front Side -->
                    <div class="pvc-card-side pvc-card-front me-3" id="front_<?php echo $row['id']; ?>">
                        <div class="header-band">
                            <img src="<?php echo APP_URL; ?>/assets/images/logo.png" class="school-logo" onerror="this.onerror=null; this.src='https://placehold.co/100x100?text=IGS'">
                            <h6>INDUS GRAMMAR SCHOOL</h6>
                            <p class="subtitle">Student ID Card</p>
                        </div>
                        
                        <div class="avatar-box">
                            <?php if (!empty($row['doc_student_photo'])): ?>
                                <img src="<?php echo APP_URL . '/' . $row['doc_student_photo']; ?>" alt="Photo" onerror="this.onerror=null; this.src='<?php echo APP_URL; ?>/assets/images/default_student.png';">
                            <?php else: ?>
                                <img src="<?php echo APP_URL; ?>/assets/images/default_student.png" alt="Avatar" onerror="this.onerror=null; this.src='https://placehold.co/150x200?text=No+Photo';">
                            <?php endif; ?>
                        </div>
                        
                        <div class="student-name-title"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                        
                        <table class="info-table">
                            <tr><td class="lbl">Student ID:</td><td class="val font-monospace"><?php echo htmlspecialchars($row['admission_no']); ?></td></tr>
                            <tr><td class="lbl">Roll Number:</td><td class="val"><?php echo htmlspecialchars($row['roll_no'] ?: '—'); ?></td></tr>
                            <tr><td class="lbl">Class & Section:</td><td class="val"><?php echo htmlspecialchars(($row['class_name'] ?? $row['school_class'] ?? '—') . ' - ' . ($row['section'] ?? $row['school_section'] ?? 'A')); ?></td></tr>
                            <tr><td class="lbl">Academic Type:</td><td class="val"><?php echo htmlspecialchars($row['academic_type']); ?></td></tr>
                            <tr><td class="lbl">Session:</td><td class="val"><?php echo htmlspecialchars($row['academic_session'] ?: '—'); ?></td></tr>
                            <tr><td class="lbl">Issue Date:</td><td class="val"><?php echo htmlspecialchars(date('d-M-Y', strtotime($row['admission_date'] ?: 'now'))); ?></td></tr>
                        </table>
                        
                        <div class="footer-strip">
                            <span class="status-badge"><?php echo htmlspecialchars($row['status']); ?></span>
                            <div class="sign-box">
                                <div class="sign-line"></div>
                                <span class="small text-muted" style="font-size:0.45rem;">Principal Sign</span>
                            </div>
                        </div>
                    </div>

                    <!-- Back Side -->
                    <div class="pvc-card-side pvc-card-back" id="back_<?php echo $row['id']; ?>">
                        <h6 class="back-title">STUDENT REGISTRY INFO</h6>
                        <div class="back-info">
                            <p>Father / Guardian Name: <strong><?php echo htmlspecialchars($row['father_name'] ?: $row['guardian_name'] ?: '—'); ?></strong></p>
                            <p>Guardian Contact: <strong><?php echo htmlspecialchars($row['father_mobile'] ?: $row['guardian_phone'] ?: '—'); ?></strong></p>
                            <p>Emergency Contact: <strong><?php echo htmlspecialchars($row['emergency_contact'] ?: $row['father_mobile'] ?: $row['guardian_phone'] ?: '—'); ?></strong></p>
                        </div>
                        <div class="qr-frame-box">
                            <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code" onerror="this.onerror=null; this.src='https://placehold.co/150x150?text=QR+Code';">
                        </div>
                        <div class="rules-box">
                            <ol>
                                <li>Always display this card while on campus premises.</li>
                                <li>Loss must be reported immediately to school admin.</li>
                                <li>Card is property of Indus Grammar School & Academy.</li>
                            </ol>
                        </div>
                        <div class="school-address-box">
                            <p class="fw-bold">Indus Grammar School & Academy</p>
                            <p><?php echo SCHOOL_ADDRESS; ?> | Ph: <?php echo SCHOOL_PHONE; ?></p>
                            <p>Email: <?php echo SCHOOL_EMAIL; ?> | Web: www.indus.edu.pk</p>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php endif; ?>

<!-- Print Zone Area (Always empty on screen, dynamically populated for clean print output) -->
<div id="printSection"></div>

<!-- Modal Card Preview -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" style="max-width:650px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius:12px;">
            <div class="modal-header bg-light border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="previewModalLabel"><i class="fa-solid fa-address-card text-primary me-2"></i>ID Card Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4 text-center bg-light">
                <div id="modalCardPreviewZone">
                    <!-- Cloned dynamic content -->
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-primary" id="modalPrintBtn"><i class="fa-solid fa-print me-2"></i>Print Card</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- html2canvas and jsPDF libraries CDNs -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
    // Search loaders
    const fForm = document.getElementById('filterForm');
    if (fForm) {
        fForm.addEventListener('submit', () => {
            const btn = document.getElementById('searchBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>...';
        });
    }

    // Toggle Checkboxes
    function toggleAllCheckboxes(master) {
        document.querySelectorAll(".student-chk").forEach(chk => {
            chk.checked = master.checked;
            toggleCardPreview(chk.value, master.checked);
        });
    }

    function toggleCardPreview(id, visible) {
        const wrap = document.getElementById("card_wrapper_" + id);
        if (wrap) {
            wrap.style.display = visible ? "inline-block" : "none";
        }
    }

    // Modal Card Preview triggers
    const pModal = new bootstrap.Modal(document.getElementById("previewModal"));
    let activePreviewStudentId = 0;

    function previewSingleCard(student) {
        activePreviewStudentId = student.id;
        const front = document.getElementById("front_" + student.id).outerHTML;
        const back = document.getElementById("back_" + student.id).outerHTML;
        
        document.getElementById("modalCardPreviewZone").innerHTML = `
            <div class="d-flex justify-content-center gap-3">
                ${front} ${back}
            </div>
        `;
        pModal.show();
    }

    document.getElementById("modalPrintBtn").addEventListener("click", () => {
        if (activePreviewStudentId > 0) {
            const front = document.getElementById("front_" + activePreviewStudentId).outerHTML;
            const back = document.getElementById("back_" + activePreviewStudentId).outerHTML;
            
            const printSec = document.getElementById("printSection");
            printSec.innerHTML = `
                <div class="id-card-wrap">${front}</div>
                <div class="id-card-wrap">${back}</div>
            `;
            window.print();
            printSec.innerHTML = "";
        }
    });

    // Print Selected Cards
    function printSelectedCards() {
        const printSec = document.getElementById("printSection");
        let html = '';
        
        document.querySelectorAll(".student-chk:checked").forEach(chk => {
            const id = chk.value;
            const front = document.getElementById("front_" + id).outerHTML;
            const back = document.getElementById("back_" + id).outerHTML;
            html += `
                <div class="id-card-wrap">${front}</div>
                <div class="id-card-wrap">${back}</div>
            `;
        });
        
        if (html === '') {
            alert("Please select at least one card to print.");
            return;
        }
        
        printSec.innerHTML = html;
        window.print();
        printSec.innerHTML = "";
    }

    // Download Selected PDF
    function downloadSelectedPDF() {
        const { jsPDF } = window.jspdf;
        const checked = document.querySelectorAll(".student-chk:checked");
        
        if (checked.length === 0) {
            alert("Please select at least one student.");
            return;
        }

        const btn = document.querySelector('button[onclick="downloadSelectedPDF()"]');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Generating PDF...';

        // Render target canvases
        const promises = [];
        checked.forEach(chk => {
            const id = chk.value;
            const front = document.getElementById("front_" + id);
            const back = document.getElementById("back_" + id);
            
            promises.push(
                html2canvas(front, { scale: 3, useCORS: true, allowTaint: true }),
                html2canvas(back, { scale: 3, useCORS: true, allowTaint: true })
            );
        });

        Promise.all(promises).then(canvases => {
            // Portrait CR80 Size in mm: 53.98 x 85.60
            const pdf = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: [53.98, 85.60]
            });

            canvases.forEach((canvas, index) => {
                if (index > 0) {
                    pdf.addPage([53.98, 85.60], 'portrait');
                }
                const imgData = canvas.toDataURL('image/jpeg', 1.0);
                pdf.addImage(imgData, 'JPEG', 0, 0, 53.98, 85.60);
            });

            pdf.save(`Student_ID_Cards_${Date.now()}.pdf`);
            btn.disabled = false;
            btn.innerHTML = originalText;
        }).catch(err => {
            console.error("PDF generation failed:", err);
            alert("Unable to generate PDF document.");
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
