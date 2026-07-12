<?php
/**
 * Indus Grammar School ERP - Student Card Report & PVC Generator
 * Version 3.0.0
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();
$message = '';
$error = '';

// Load lookups
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

// Retrieve search filters
$search_academic_type = sanitize($_GET['search_academic_type'] ?? '');
$search_class = sanitize($_GET['search_class'] ?? '');
$search_section = sanitize($_GET['search_section'] ?? '');
$search_admission = sanitize($_GET['search_admission'] ?? '');

$where = " WHERE s.status = 'Active'";
$params = [];

if ($search_academic_type !== '') {
    $where .= " AND s.academic_type = :academic_type";
    $params['academic_type'] = $search_academic_type;
}
if ($search_class !== '') {
    $where .= " AND (c.class_name = :class OR s.school_class = :class)";
    $params['class'] = $search_class;
}
if ($search_section !== '') {
    $where .= " AND (c.section = :section OR s.school_section = :section)";
    $params['section'] = $search_section;
}
if ($search_admission !== '') {
    $where .= " AND s.admission_no = :admission";
    $params['admission'] = $search_admission;
}

$students = [];
try {
    $sql = "
        SELECT s.*, c.class_name, c.section,
               d.roll_no, d.blood_group, d.emergency_contact, d.academic_session, d.doc_student_photo,
               d.father_name, d.father_mobile, d.current_address
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY s.admission_no ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error fetching students for ID Cards: " . $e->getMessage();
}

$pageTitle = 'Student Card Report';
$breadcrumbActive = 'Student Registration';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Premium PVC ID Card Stylesheets -->
<style>
/* PVC Card Dimension specifications: CR80 standard (~86mm x 54mm) -> vertical layout approx 250px x 400px */
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

.pvc-card-front .footer-strip .sign-box img.sign-img {
    height: 15px;
    object-fit: contain;
    margin-bottom: 2px;
    display: block;
}

.pvc-card-front .footer-strip img.qr-img {
    width: 32px;
    height: 32px;
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
    .left-sidebar, .header-navbar, .d-print-none, .breadcrumb-card, .footer-container {
        display: none !important;
    }
    body, .main-content-container, .card, .card-body {
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
    }
    #printZone {
        text-align: center !important;
        background: none !important;
    }
    .id-card-wrap {
        border: none !important;
        margin: 10px !important;
        display: inline-block !important;
        page-break-inside: avoid !important;
    }
    .pvc-card-side {
        border: 1px solid #94a3b8 !important;
        box-shadow: none !important;
        margin: 5px !important;
    }
}
</style>

<!-- Header toolbar (Hidden in Print) -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-id-card me-2 text-primary"></i>Student Card Report</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary px-3 rounded-pill shadow-sm" onclick="printSelectedCards()"><i class="fa-solid fa-print me-2"></i>Print Selected</button>
        <button class="btn btn-outline-primary px-3 rounded-pill shadow-sm ms-1" onclick="printAllCards()"><i class="fa-solid fa-print me-2"></i>Print All</button>
        <button class="btn btn-outline-danger px-3 rounded-pill shadow-sm ms-1" onclick="downloadPDF()"><i class="fa-solid fa-file-pdf me-2"></i>Download PDF</button>
    </div>
</div>

<!-- Search Panel (Hidden in Print) -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Filter Cards</h6>
    <form method="GET" action="card_report.php" id="searchForm" class="row g-3">
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Academic Type</label>
            <select class="form-select form-select-sm" name="search_academic_type">
                <option value="">All Types</option>
                <option value="School" <?php echo ($search_academic_type === 'School') ? 'selected' : ''; ?>>School</option>
                <option value="Academy" <?php echo ($search_academic_type === 'Academy') ? 'selected' : ''; ?>>Academy</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Class</label>
            <select class="form-select form-select-sm" name="search_class">
                <option value="">All Classes</option>
                <?php foreach ($classesList as $cls): ?>
                    <option value="<?php echo $cls; ?>" <?php echo ($search_class === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Section</label>
            <select class="form-select form-select-sm" name="search_section">
                <option value="">All</option>
                <?php foreach ($sectionsList as $sec): ?>
                    <option value="<?php echo $sec; ?>" <?php echo ($search_section === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Admission No.</label>
            <input type="text" class="form-control form-control-sm" name="search_admission" value="<?php echo htmlspecialchars($search_admission); ?>" placeholder="ADM-2026-0001">
        </div>
        <div class="col-md-2 text-end align-self-end mt-4">
            <button type="submit" id="searchBtn" class="btn btn-sm btn-primary w-100 mb-1"><i class="fa-solid fa-search me-1"></i>Search</button>
            <a href="card_report.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
        </div>
    </form>
</div>

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
                    <th>Student Photo</th>
                    <th>Admission No.</th>
                    <th>Student Name</th>
                    <th>Class</th>
                    <th>Section</th>
                    <th>Academic Type</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">No students found.</td>
                    </tr>
                <?php else: foreach ($students as $row): ?>
                    <tr>
                        <td>
                            <input class="form-check-input student-chk" type="checkbox" value="<?php echo $row['id']; ?>" id="chk_<?php echo $row['id']; ?>" checked onchange="toggleCardPreview(<?php echo $row['id']; ?>, this.checked)">
                        </td>
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
                        <td><?php echo displayValue($row['class_name'] ?? $row['school_class']); ?></td>
                        <td><?php echo displayValue($row['section'] ?? $row['school_section']); ?></td>
                        <td><span class="badge bg-secondary"><?php echo sanitize($row['academic_type']); ?></span></td>
                        <td>
                            <span class="badge badge-soft-success"><?php echo sanitize($row['status']); ?></span>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="previewSingleCard(<?php echo htmlspecialchars(json_encode($row)); ?>)"><i class="fa-solid fa-eye me-1"></i>Preview Card</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- PVC ID Cards Print rendering area -->
<div class="bg-light border p-4 shadow-inner text-center" style="border-radius: 12px; min-height: 200px;">
    <h5 class="fw-bold text-secondary mb-4 d-print-none"><i class="fa-solid fa-print me-2"></i>Identity Cards Print Queue Preview</h5>
    <div id="printZone">
        <?php if (empty($students)): ?>
            <div class="text-center py-5 text-muted d-print-none">
                <i class="fa-solid fa-id-card fs-1 opacity-25 mb-2 d-block"></i>
                No cards in render queue.
            </div>
        <?php else: foreach ($students as $row): 
            $qrData = urlencode(APP_URL . "/modules/students/profile_report.php?id=" . $row['id']);
            $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . $qrData;
        ?>
            <!-- ID CARD WRAPPER FRAME (Front and Back side) -->
            <div class="id-card-wrap" id="card_wrapper_<?php echo $row['id']; ?>">
                
                <!-- Front Side -->
                <div class="pvc-card-side pvc-card-front me-2">
                    <div class="header-band">
                        <i class="fa-solid fa-graduation-cap text-warning fs-5"></i>
                        <h6>INDUS GRAMMAR SCHOOL</h6>
                        <p class="subtitle">Student Identity Card</p>
                    </div>
                    <div class="pvc-card-front-body">
                        <div class="avatar-box">
                            <?php if (!empty($row['doc_student_photo'])): ?>
                                <img src="<?php echo APP_URL . '/' . $row['doc_student_photo']; ?>">
                            <?php else: ?>
                                <i class="fa-solid fa-user fs-1 text-secondary opacity-50"></i>
                            <?php endif; ?>
                        </div>
                        <div class="student-name-title"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></div>
                        
                        <table class="info-table">
                            <tr><td class="lbl">Admission No:</td><td class="val"><?php echo sanitize($row['admission_no']); ?></td></tr>
                            <tr><td class="lbl">Class & Section:</td><td class="val"><?php echo sanitize(($row['class_name'] ?? $row['school_class'] ?? '-') . ' - ' . ($row['section'] ?? $row['school_section'] ?? 'A')); ?></td></tr>
                            <tr><td class="lbl">Academic Session:</td><td class="val"><?php echo sanitize($row['academic_session'] ?: '-'); ?></td></tr>
                            <tr><td class="lbl">Blood Group:</td><td class="val"><?php echo sanitize($row['blood_group'] ?: '-'); ?></td></tr>
                            <tr><td class="lbl">Emergency:</td><td class="val"><?php echo sanitize($row['emergency_contact'] ?: '-'); ?></td></tr>
                        </table>
                        
                        <div class="footer-strip">
                            <div class="sign-box">
                                <span class="d-block border-bottom font-monospace" style="font-size:0.55rem; color:#111;">Principal</span>
                                <span class="small text-muted" style="font-size:0.45rem;">Authorized Sign</span>
                            </div>
                            <img src="<?php echo $qrCodeUrl; ?>" class="qr-img" alt="QR Link">
                        </div>
                    </div>
                </div>
                
                <!-- Back Side -->
                <div class="pvc-card-side pvc-card-back">
                    <h6 class="back-title">STUDENT REGISTRY INFO</h6>
                    <div class="back-info">
                        <p>Father Name: <strong><?php echo displayValue($row['father_name'] ?? $row['guardian_name']); ?></strong></p>
                        <p>Parent Contact: <strong><?php echo displayValue($row['father_mobile'] ?? $row['guardian_phone']); ?></strong></p>
                        <p class="text-wrap" style="line-height:1.2;">Address: <strong><?php echo displayValue($row['current_address'] ?? $row['address']); ?></strong></p>
                        <p>Issue Date: <strong><?php echo date('M d, Y'); ?></strong></p>
                    </div>
                    
                    <h6 class="back-title" style="border-top:1px solid #e2e8f0; padding-top:6px; margin-top:10px;">CAMPUS RULES</h6>
                    <div class="rules-box">
                        <ol>
                            <li>Card must be worn prominently inside campus.</li>
                            <li>Loss of card must be reported immediately.</li>
                            <li>Card is non-transferable and belongs to IGS.</li>
                        </ol>
                    </div>
                    
                    <div class="school-address-box">
                        <p class="fw-bold">Indus Grammar School & Academy</p>
                        <p>Main Campus, Lahore | Ph: +92 42 111-222-333</p>
                    </div>
                </div>

            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- Modal Card Preview -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:12px;">
            <div class="modal-header bg-light border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="previewModalLabel"><i class="fa-solid fa-address-card text-primary me-2"></i>ID Card Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4 text-center bg-light">
                <div id="modalCardPreviewZone">
                    <!-- Loaded dynamically via JavaScript cloning -->
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" onclick="printSingleCardModal()"><i class="fa-solid fa-print me-2"></i>Print Card</button>
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
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>...';
});

// Toggle all student check indicators
function toggleAllCheckboxes(master) {
    document.querySelectorAll(".student-chk").forEach(chk => {
        chk.checked = master.checked;
        toggleCardPreview(chk.value, master.checked);
    });
}

function toggleCardPreview(id, visible) {
    const wrapper = document.getElementById("card_wrapper_" + id);
    if (wrapper) {
        wrapper.style.display = visible ? "inline-block" : "none";
    }
}

// Single card preview modal loader
const pModal = new bootstrap.Modal(document.getElementById("previewModal"));
let activePreviewCardId = 0;

function previewSingleCard(student) {
    activePreviewCardId = student.id;
    const originalWrapper = document.getElementById("card_wrapper_" + student.id);
    if (originalWrapper) {
        const previewZone = document.getElementById("modalCardPreviewZone");
        previewZone.innerHTML = originalWrapper.innerHTML;
        pModal.show();
    }
}

// Print single card
function printSingleCardModal() {
    if (activePreviewCardId > 0) {
        const originalContent = document.body.innerHTML;
        const targetCardHtml = document.getElementById("card_wrapper_" + activePreviewCardId).innerHTML;
        
        document.body.innerHTML = `
            <div style="padding:20px; text-align:center;">
                ${targetCardHtml}
            </div>
        `;
        window.print();
        document.body.innerHTML = originalContent;
        window.location.reload();
    }
}

function printSelectedCards() {
    window.print();
}

function printAllCards() {
    // Select all checkboxes and trigger print
    document.querySelectorAll(".student-chk").forEach(chk => {
        chk.checked = true;
        toggleCardPreview(chk.value, true);
    });
    document.getElementById("selectAll").checked = true;
    setTimeout(() => {
        window.print();
    }, 200);
}

function downloadPDF() {
    alert("PVC ID Card templates are optimized for PDF print layouts. Please select PDF printer in print dialogue.");
    window.print();
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
