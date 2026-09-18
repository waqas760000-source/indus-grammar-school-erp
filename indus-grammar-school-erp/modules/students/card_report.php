<?php
/**
 * Indus Grammar School ERP - Student ID Card Generator
 * Version 5.4.0 - Premium UI/UX Redesign
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
$searchAttempted = false;

/**
 * Image Asset Path Helpers
 */
if (!function_exists('getSchoolLogoUrl')) {
    function getSchoolLogoUrl() {
        $pngPath = __DIR__ . '/../../assets/images/logo.png';
        if (file_exists($pngPath)) {
            return APP_URL . '/assets/images/logo.png';
        }
        $svgPath = __DIR__ . '/../../assets/images/logo.svg';
        if (file_exists($svgPath)) {
            return APP_URL . '/assets/images/logo.svg';
        }
        return APP_URL . '/assets/images/logo.svg';
    }
}

if (!function_exists('getStudentPhotoUrl')) {
    function getStudentPhotoUrl($photoPath) {
        if (!empty($photoPath)) {
            $cleanPath = ltrim(str_replace('\\', '/', $photoPath), '/');
            $fullPath = __DIR__ . '/../../' . $cleanPath;
            if (file_exists($fullPath)) {
                return APP_URL . '/' . $cleanPath;
            }
        }
        $defSvg = __DIR__ . '/../../assets/images/default_student.svg';
        if (file_exists($defSvg)) {
            return APP_URL . '/assets/images/default_student.svg';
        }
        return APP_URL . '/assets/images/default_student.png';
    }
}

// Load class and section lists for batch printing filters
$classesList = [];
try {
    $classesList = $db->query("SELECT DISTINCT class_name FROM classes WHERE class_name IS NOT NULL AND class_name != '' ORDER BY class_name ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$sectionsList = ['A', 'B', 'C', 'D', 'E'];
try {
    $dbSecs = $db->query("SELECT DISTINCT section FROM classes WHERE section IS NOT NULL AND section != '' ORDER BY section ASC")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dbSecs as $ds) {
        if (!in_array($ds, $sectionsList)) {
            $sectionsList[] = $ds;
        }
    }
} catch (Exception $e) {}

// Retrieve search query and batch filters
$singleSearchQuery = isset($_GET['single_search']) ? trim($_GET['single_search']) : '';
$batchAcademicType = sanitize($_GET['batch_academic_type'] ?? '');
$batchClass = sanitize($_GET['batch_class'] ?? '');
$batchSection = sanitize($_GET['batch_section'] ?? '');

$students = [];

if (isset($_GET['search']) || $singleSearchQuery !== '' || $batchAcademicType !== '' || $batchClass !== '' || $batchSection !== '') {
    $searchAttempted = true;
    
    if (isset($_GET['single_search']) && $singleSearchQuery === '' && $batchAcademicType === '' && $batchClass === '' && $batchSection === '') {
        $error = "Please enter a supported student identifier.";
    } else {
        $where = " WHERE s.status = 'Active'";
        $params = [];

        // Single search query: Admission No, Roll No, CNIC/B-Form
        if ($singleSearchQuery !== '') {
            $cleanQuery = str_replace('-', '', $singleSearchQuery);
            $where .= " AND (s.admission_no = :q_adm OR d.roll_no = :q_roll OR d.cnic_no = :q_cnic OR REPLACE(d.cnic_no, '-', '') = :q_clean_cnic)";
            $params['q_adm'] = $singleSearchQuery;
            $params['q_roll'] = $singleSearchQuery;
            $params['q_cnic'] = $singleSearchQuery;
            $params['q_clean_cnic'] = $cleanQuery;
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

        try {
            $sql = "
                SELECT s.*, c.class_name, c.section,
                       d.roll_no, d.blood_group, d.emergency_contact, d.academic_session, d.doc_student_photo,
                       d.father_name, d.father_mobile, d.current_address,
                       d.admission_date, d.cnic_no, d.campus
                FROM students s
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN student_registration_details d ON s.id = d.student_id
                $where
                ORDER BY s.admission_no ASC
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($students)) {
                $error = "Student record not found. Please verify the entered information.";
            }
        } catch (Exception $e) {
            $error = "The student record could not be loaded due to a system error. Please try again.";
        }
    }
}

$pageTitle = 'Student ID Card Generator';
$breadcrumbActive = 'Student ID Card Generator';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Premium Educational ERP Styling Tokens -->
<style>
:root {
    --igs-navy: #0F172A;
    --igs-royal: #1D4ED8;
    --igs-blue: #2563EB;
    --igs-light-bg: #F8FAFC;
    --igs-border: #E2E8F0;
    --igs-text: #0F172A;
    --igs-muted: #64748B;
    --igs-success: #16A34A;
    --igs-error: #DC2626;
    --igs-card-width: 260px;
    --igs-card-height: 415px;
}

body {
    background-color: var(--igs-light-bg);
    color: var(--igs-text);
}

/* Page Header */
.page-header-banner {
    background: linear-gradient(135deg, #0F172A 0%, #1D4ED8 60%, #2563EB 100%);
    color: #ffffff;
    border-radius: 12px;
    padding: 24px 28px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.12);
    margin-bottom: 24px;
}

.search-workspace-card {
    border-radius: 12px;
    border: 1px solid var(--igs-border);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    background-color: #ffffff;
}

/* Student Summary Panel */
.student-preview-panel {
    background: #ffffff;
    border: 1px solid var(--igs-border);
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.student-preview-panel .preview-photo-wrap {
    width: 105px;
    height: 105px;
    border-radius: 50%;
    border: 3px solid #dbeafe;
    overflow: hidden;
    margin: 0 auto 14px auto;
    background-color: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(29, 78, 216, 0.12);
}

.student-preview-panel .preview-photo-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.student-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.student-summary-item {
    background-color: var(--igs-light-bg);
    border: 1px solid var(--igs-border);
    border-radius: 8px;
    padding: 10px 12px;
}

.student-summary-item .lbl {
    font-size: 0.72rem;
    color: var(--igs-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 2px;
}

.student-summary-item .val {
    font-size: 0.88rem;
    color: var(--igs-text);
    font-weight: 700;
    word-break: break-word;
}

/* CR80 PVC Standard ID Card Layout */
.id-card-wrap {
    display: inline-block;
    margin: 10px;
    vertical-align: top;
    text-align: left;
    page-break-inside: avoid;
}

.pvc-card-side {
    width: var(--igs-card-width);
    height: var(--igs-card-height);
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    background-color: #ffffff;
    position: relative;
    overflow: hidden;
    font-family: 'Outfit', 'Inter', sans-serif;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.12);
    display: inline-block;
    vertical-align: top;
    background-image: radial-gradient(circle at 10% 10%, rgba(239, 246, 255, 0.7) 0%, rgba(255, 255, 255, 0.9) 100%);
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

/* Front Side Styling */
.pvc-card-front {
    border-top: 6px solid var(--igs-navy);
}

.pvc-card-front .header-band {
    background: linear-gradient(135deg, #0F172A 0%, #1D4ED8 100%);
    color: #ffffff;
    padding: 10px 6px;
    text-align: center;
    position: relative;
    border-bottom: 2px solid #fbbf24;
}

.pvc-card-front .header-band img.school-logo {
    width: 34px;
    height: 34px;
    object-fit: contain;
    margin-bottom: 2px;
}

.pvc-card-front .header-band h6 {
    margin: 0;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.pvc-card-front .header-band p.subtitle {
    margin: 0;
    font-size: 0.55rem;
    opacity: 0.95;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.4px;
    color: #fef08a;
}

.pvc-card-front .avatar-box {
    width: 86px;
    height: 86px;
    border-radius: 50%;
    border: 3px solid #1D4ED8;
    overflow: hidden;
    margin: 12px auto 6px auto;
    background-color: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.pvc-card-front .avatar-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pvc-card-front .student-name-title {
    font-size: 0.92rem;
    font-weight: 800;
    color: #0F172A;
    text-align: center;
    margin-bottom: 2px;
    padding: 0 8px;
    text-transform: uppercase;
    line-height: 1.2;
}

.pvc-card-front .student-role-badge {
    text-align: center;
    margin-bottom: 8px;
}

.pvc-card-front .student-role-badge span {
    background-color: #dbeafe;
    color: #1e40af;
    font-size: 0.55rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
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
    color: #64748b;
    font-weight: 600;
    width: 44%;
}

.pvc-card-front .info-table td.val {
    color: #0f172a;
    font-weight: 700;
}

.pvc-card-front .footer-strip {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: #f8fafc;
    border-top: 1px solid #e2e8f0;
    padding: 6px 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pvc-card-front .footer-strip .sign-box {
    text-align: center;
    font-size: 0.45rem;
    color: #64748b;
    font-weight: 700;
}

.pvc-card-front .footer-strip .sign-box .sign-line {
    border-bottom: 1px solid #64748b;
    width: 55px;
    height: 10px;
    margin-bottom: 2px;
}

.pvc-card-front .status-badge {
    background-color: #dcfce7;
    color: #16a34a;
    font-size: 0.55rem;
    font-weight: 800;
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
    color: #0F172A;
    font-weight: 800;
    font-size: 0.72rem;
    margin-bottom: 6px;
    border-bottom: 1px dashed #cbd5e1;
    padding-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.pvc-card-back .back-info {
    font-size: 0.62rem;
    margin-bottom: 8px;
    line-height: 1.35;
}

.pvc-card-back .back-info p {
    margin: 0 0 3px 0;
    color: #334155;
}

.pvc-card-back .back-info strong {
    color: #0f172a;
}

.pvc-card-back .qr-frame-box {
    text-align: center;
    margin-top: 4px;
    margin-bottom: 6px;
}

.pvc-card-back .qr-frame-box img {
    width: 58px;
    height: 58px;
    border: 1px solid #e2e8f0;
    padding: 2px;
    background-color: #ffffff;
    border-radius: 4px;
}

.pvc-card-back .rules-box {
    border: 1px solid #e2e8f0;
    background-color: #f8fafc;
    border-radius: 6px;
    padding: 6px 8px;
    font-size: 0.54rem;
    color: #475569;
    margin-bottom: 8px;
    line-height: 1.3;
}

.pvc-card-back .rules-box p.return-msg {
    margin: 0 0 4px 0;
    font-weight: 700;
    color: #0F172A;
    text-align: center;
}

.pvc-card-back .rules-box ol {
    margin: 0;
    padding-left: 12px;
}

.pvc-card-back .rules-box li {
    margin-bottom: 2px;
}

.pvc-card-back .school-address-box {
    position: absolute;
    bottom: 6px;
    left: 10px;
    right: 10px;
    border-top: 1px solid #e2e8f0;
    padding-top: 5px;
    font-size: 0.48rem;
    color: #64748b;
    text-align: center;
}

.pvc-card-back .school-address-box p {
    margin: 0;
    line-height: 1.25;
}

/* Dedicated Print Stylesheet */
@media print {
    body > *:not(#printSection),
    .d-print-none,
    .page-header-banner,
    .search-workspace-card,
    .student-preview-panel,
    header, footer, nav, sidebar, .sidebar, .main-header, .breadcrumb {
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
        margin: 8px !important;
        display: inline-block !important;
        page-break-inside: avoid !important;
    }
    
    .pvc-card-side {
        width: 53.98mm !important;
        height: 85.60mm !important;
        border: 1px solid #cbd5e1 !important;
        box-shadow: none !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>

<!-- Modern Page Header Banner -->
<div class="page-header-banner d-print-none">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <span class="badge bg-white text-primary fw-bold text-uppercase px-2 py-1 mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                <i class="fa-solid fa-school me-1"></i>INDUS GRAMMAR SCHOOL
            </span>
            <h2 class="fw-bold mb-1 text-white"><i class="fa-solid fa-id-card me-2"></i>Student ID Card Generator</h2>
            <p class="mb-0 text-white-50" style="font-size: 0.95rem;">Create, preview, and download student identification cards.</p>
        </div>
        <div class="text-md-end">
            <span class="badge bg-white bg-opacity-10 text-white border border-white border-opacity-25 px-3 py-2 rounded-pill">
                <i class="fa-solid fa-user-shield me-1"></i>Role: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $userRole))); ?>
            </span>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4 alert-dismissible fade show d-print-none" role="alert" style="border-radius:10px;">
        <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4 alert-dismissible fade show d-print-none" role="alert" style="border-radius:10px;">
        <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Search Workspace Card (Hidden in Print) -->
<div class="card search-workspace-card p-4 mb-4 d-print-none">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-magnifying-glass me-2 text-primary"></i>Find Student</h5>
        <span class="badge bg-light text-muted border">Identifier Search</span>
    </div>
    <p class="text-muted small mb-3">Search for a registered student to generate an ID card.</p>
    
    <form method="GET" action="card_report.php" id="filterForm">
        <input type="hidden" name="search" value="1">
        <div class="row g-3">
            <div class="col-lg-5 col-md-6">
                <label class="form-label small fw-bold text-secondary">Student CNIC / B-Form, Roll Number, or Admission Number</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-user-tag"></i></span>
                    <input type="text" class="form-control" name="single_search" value="<?php echo htmlspecialchars($singleSearchQuery); ?>" placeholder="Enter CNIC/B-Form, Roll Number, or Admission Number">
                </div>
            </div>
            
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small fw-bold text-secondary">Academic Type</label>
                <select class="form-select" name="batch_academic_type">
                    <option value="">All Types</option>
                    <option value="School" <?php echo ($batchAcademicType === 'School') ? 'selected' : ''; ?>>School</option>
                    <option value="Academy" <?php echo ($batchAcademicType === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small fw-bold text-secondary">Class</label>
                <select class="form-select" name="batch_class">
                    <option value="">All Classes</option>
                    <?php foreach ($classesList as $cls): ?>
                        <option value="<?php echo htmlspecialchars($cls); ?>" <?php echo ($batchClass === $cls) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cls); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-3 col-md-12 d-flex align-items-end gap-2">
                <button type="submit" id="searchBtn" class="btn btn-primary px-3 flex-grow-1"><i class="fa-solid fa-search me-1"></i>Search Student</button>
                <a href="card_report.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-rotate-left me-1"></i>Clear</a>
            </div>
        </div>
    </form>
</div>

<!-- Main Display Workspace -->
<?php if (!$searchAttempted && empty($students)): ?>
    <!-- Empty State Before Searching -->
    <div class="card border border-light shadow-sm text-center py-5 mb-4 d-print-none" style="border-radius:12px; background-color:#ffffff;">
        <div class="card-body py-4">
            <div class="avatar-lg bg-light text-primary rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3" style="width: 76px; height: 76px;">
                <i class="fa-solid fa-id-card fa-3x text-primary opacity-75"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Search for a registered student to preview an ID card.</h5>
            <p class="text-muted small mx-auto mb-0" style="max-width: 480px;">Enter a student CNIC/B-Form number, Roll Number, or Admission Number above to load student verification details and generate a print-ready ID card.</p>
        </div>
    </div>

<?php elseif (!empty($students)): ?>
    
    <!-- Single Student Detailed View (When exact 1 match found) -->
    <?php if (count($students) === 1): 
        $st = $students[0];
        $stName = trim(($st['first_name'] ?? '') . ' ' . ($st['last_name'] ?? ''));
        $stPhoto = getStudentPhotoUrl($st['doc_student_photo'] ?? '');
        $logoUrl = getSchoolLogoUrl();
        $stClass = $st['class_name'] ?? $st['school_class'] ?? '—';
        $stSection = $st['section'] ?? $st['school_section'] ?? 'A';
    ?>
        <div class="row g-4 mb-4 d-print-none">
            <!-- Left: Student Information Summary Panel -->
            <div class="col-lg-5">
                <div class="student-preview-panel h-100">
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-circle-user me-2 text-primary"></i>Student Information Summary</h6>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">Verified Record</span>
                    </div>

                    <div class="preview-photo-wrap">
                        <img src="<?php echo $stPhoto; ?>" alt="Student Photo" onerror="this.onerror=null; this.src='<?php echo APP_URL; ?>/assets/images/default_student.svg';">
                    </div>

                    <h5 class="text-center fw-bold text-dark mb-1"><?php echo htmlspecialchars($stName); ?></h5>
                    <p class="text-center text-muted small mb-3"><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 me-1">STUDENT</span> <?php echo htmlspecialchars($st['admission_no'] ?? '—'); ?></p>

                    <div class="student-summary-grid mb-3">
                        <div class="student-summary-item">
                            <div class="lbl">Admission No</div>
                            <div class="val text-primary font-monospace"><?php echo htmlspecialchars($st['admission_no'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="student-summary-item">
                            <div class="lbl">Roll Number</div>
                            <div class="val"><?php echo htmlspecialchars($st['roll_no'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="student-summary-item">
                            <div class="lbl">Class & Section</div>
                            <div class="val"><?php echo htmlspecialchars($stClass . ' - ' . $stSection); ?></div>
                        </div>
                        <div class="student-summary-item">
                            <div class="lbl">Campus</div>
                            <div class="val"><?php echo htmlspecialchars($st['campus'] ?: 'Main Campus'); ?></div>
                        </div>
                        <div class="student-summary-item">
                            <div class="lbl">Academic Session</div>
                            <div class="val"><?php echo htmlspecialchars($st['academic_session'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="student-summary-item">
                            <div class="lbl">Academic Type</div>
                            <div class="val"><?php echo htmlspecialchars($st['academic_type'] ?? 'School'); ?></div>
                        </div>
                    </div>

                    <div class="border-top pt-3 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1 rounded-pill" onclick="downloadSinglePDF(<?php echo $st['id']; ?>)">
                            <i class="fa-solid fa-download me-2"></i>Download Card
                        </button>
                        <button class="btn btn-outline-primary rounded-pill px-3" onclick="printSingleStudentCard(<?php echo $st['id']; ?>)">
                            <i class="fa-solid fa-print me-1"></i>Print
                        </button>
                        <a href="card_report.php" class="btn btn-outline-secondary rounded-pill px-3">
                            <i class="fa-solid fa-rotate-left me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right: Interactive ID Card Preview (Front & Back) -->
            <div class="col-lg-7">
                <div class="card border border-light shadow-sm p-4 h-100 bg-white" style="border-radius:12px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-id-card me-2 text-primary"></i>ID Card Preview</h6>
                            <p class="text-muted small mb-0">CR80 PVC Standard Format</p>
                        </div>
                        <span class="badge bg-light text-dark border">Front & Back Preview</span>
                    </div>
                    
                    <div class="d-flex flex-wrap justify-content-center gap-3 align-items-center py-2">
                        <?php 
                            $qrRawData = "Student ID: " . ($st['admission_no'] ?? '') . "\n" .
                                         "Name: " . $stName . "\n" .
                                         "Class: " . $stClass . "\n" .
                                         "Section: " . $stSection . "\n" .
                                         "Academic Type: " . ($st['academic_type'] ?? 'School');
                            $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrRawData);
                        ?>

                        <!-- Front Card Preview -->
                        <div class="pvc-card-side pvc-card-front" id="front_<?php echo $st['id']; ?>">
                            <div class="header-band">
                                <img src="<?php echo $logoUrl; ?>" class="school-logo" onerror="this.onerror=null; this.src='<?php echo APP_URL; ?>/assets/images/logo.svg';">
                                <h6>INDUS GRAMMAR SCHOOL</h6>
                                <p class="subtitle">Student Identification Card</p>
                            </div>
                            
                            <div class="avatar-box">
                                <img src="<?php echo $stPhoto; ?>" alt="Photo" onerror="this.onerror=null; this.src='<?php echo APP_URL; ?>/assets/images/default_student.svg';">
                            </div>
                            
                            <div class="student-name-title"><?php echo htmlspecialchars($stName); ?></div>
                            <div class="student-role-badge"><span>STUDENT</span></div>
                            
                            <table class="info-table">
                                <tr><td class="lbl">Student ID:</td><td class="val font-monospace"><?php echo htmlspecialchars($st['admission_no'] ?? 'N/A'); ?></td></tr>
                                <tr><td class="lbl">Roll Number:</td><td class="val"><?php echo htmlspecialchars($st['roll_no'] ?: 'N/A'); ?></td></tr>
                                <tr><td class="lbl">Campus:</td><td class="val"><?php echo htmlspecialchars($st['campus'] ?: 'Main Campus'); ?></td></tr>
                                <tr><td class="lbl">Class & Sec:</td><td class="val"><?php echo htmlspecialchars($stClass . ' - ' . $stSection); ?></td></tr>
                                <tr><td class="lbl">Academic Session:</td><td class="val"><?php echo htmlspecialchars($st['academic_session'] ?: 'N/A'); ?></td></tr>
                                <tr><td class="lbl">Academic Type:</td><td class="val"><?php echo htmlspecialchars($st['academic_type'] ?? 'School'); ?></td></tr>
                            </table>
                            
                            <div class="footer-strip">
                                <span class="status-badge"><?php echo htmlspecialchars($st['status'] ?? 'Active'); ?></span>
                                <div class="sign-box">
                                    <div class="sign-line"></div>
                                    <span style="font-size:0.45rem;">Principal Signature</span>
                                </div>
                            </div>
                        </div>

                        <!-- Back Card Preview -->
                        <div class="pvc-card-side pvc-card-back" id="back_<?php echo $st['id']; ?>">
                            <h6 class="back-title">STUDENT REGISTRY INFO</h6>
                            <div class="back-info">
                                <p>Father/Guardian: <strong><?php echo htmlspecialchars($st['father_name'] ?: $st['guardian_name'] ?: 'N/A'); ?></strong></p>
                                <p>Guardian Contact: <strong><?php echo htmlspecialchars($st['father_mobile'] ?: $st['guardian_phone'] ?: 'N/A'); ?></strong></p>
                                <p>Emergency Contact: <strong><?php echo htmlspecialchars($st['emergency_contact'] ?: $st['father_mobile'] ?: $st['guardian_phone'] ?: 'N/A'); ?></strong></p>
                            </div>
                            
                            <div class="qr-frame-box">
                                <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code" onerror="this.onerror=null; this.src='https://placehold.co/150x150?text=QR+Code';">
                            </div>
                            
                            <div class="rules-box">
                                <p class="return-msg">If found, please return this card to Indus Grammar School.</p>
                                <ol>
                                    <li>Always display card on school premises.</li>
                                    <li>Loss must be reported to administration.</li>
                                    <li>Property of Indus Grammar School.</li>
                                </ol>
                            </div>
                            
                            <div class="school-address-box">
                                <p class="fw-bold">Indus Grammar School & Academy</p>
                                <p><?php echo SCHOOL_ADDRESS; ?> | Ph: <?php echo SCHOOL_PHONE; ?></p>
                                <p>Email: <?php echo SCHOOL_EMAIL; ?></p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Batch Selection & Bulk Printing List (When multiple students found) -->
    <div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-users me-2 text-primary"></i>Matching Registered Students (<?php echo count($students); ?> found)</h6>
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
                        <th>Roll No</th>
                        <th>Class & Sec</th>
                        <th>Academic Type</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $row): 
                        $rName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                        $rPhoto = getStudentPhotoUrl($row['doc_student_photo'] ?? '');
                        $rClass = $row['class_name'] ?? $row['school_class'] ?? '—';
                        $rSection = $row['section'] ?? $row['school_section'] ?? 'A';
                    ?>
                        <tr>
                            <td>
                                <input class="form-check-input student-chk" type="checkbox" value="<?php echo $row['id']; ?>" id="chk_<?php echo $row['id']; ?>" checked>
                            </td>
                            <td>
                                <div class="avatar-small border rounded-circle d-flex align-items-center justify-content-center bg-light" style="width: 36px; height: 36px; overflow:hidden;">
                                    <img src="<?php echo $rPhoto; ?>" style="width:100%; height:100%; object-fit:cover;" onerror="this.onerror=null; this.src='<?php echo APP_URL; ?>/assets/images/default_student.svg';">
                                </div>
                            </td>
                            <td><strong class="text-primary font-monospace"><?php echo htmlspecialchars($row['admission_no'] ?? 'N/A'); ?></strong></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($rName); ?></td>
                            <td><?php echo htmlspecialchars($row['roll_no'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($rClass . ' - ' . $rSection); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['academic_type'] ?? 'School'); ?></span></td>
                            <td><span class="badge bg-success">Active</span></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" onclick="previewSingleCard(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                    <i class="fa-solid fa-eye me-1"></i>Preview Card
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Batch Action Bar -->
        <div class="border-top pt-3 mt-4 d-flex justify-content-between align-items-center">
            <span class="text-muted small"><i class="fa-solid fa-info-circle me-1"></i>Select students using checkboxes to print or download multiple ID cards simultaneously.</span>
            <div>
                <button class="btn btn-primary px-4 rounded-pill shadow-sm me-2" onclick="downloadSelectedPDF()">
                    <i class="fa-solid fa-download me-2"></i>Download Selected Cards
                </button>
                <button class="btn btn-outline-primary px-4 rounded-pill shadow-sm me-2" onclick="printSelectedCards()">
                    <i class="fa-solid fa-print me-2"></i>Print Selected Cards
                </button>
                <a href="card_report.php" class="btn btn-outline-secondary px-3 rounded-pill">Clear</a>
            </div>
        </div>
    </div>

    <!-- Hidden Queue Render Container for Batch Cards -->
    <div class="d-none" id="hiddenBatchQueue">
        <?php foreach ($students as $row): 
            $qName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $qPhoto = getStudentPhotoUrl($row['doc_student_photo'] ?? '');
            $logoUrl = getSchoolLogoUrl();
            $qClass = $row['class_name'] ?? $row['school_class'] ?? '—';
            $qSection = $row['section'] ?? $row['school_section'] ?? 'A';
            $qrRawData = "Student ID: " . ($row['admission_no'] ?? '') . "\n" .
                         "Name: " . $qName . "\n" .
                         "Class: " . $qClass . "\n" .
                         "Section: " . $qSection . "\n" .
                         "Academic Type: " . ($row['academic_type'] ?? 'School');
            $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrRawData);
        ?>
            <div class="id-card-wrap" id="batch_card_wrapper_<?php echo $row['id']; ?>">
                <!-- Front Side -->
                <div class="pvc-card-side pvc-card-front" id="batch_front_<?php echo $row['id']; ?>">
                    <div class="header-band">
                        <img src="<?php echo $logoUrl; ?>" class="school-logo" onerror="this.onerror=null; this.src='<?php echo APP_URL; ?>/assets/images/logo.svg';">
                        <h6>INDUS GRAMMAR SCHOOL</h6>
                        <p class="subtitle">Student Identification Card</p>
                    </div>
                    
                    <div class="avatar-box">
                        <img src="<?php echo $qPhoto; ?>" alt="Photo" onerror="this.onerror=null; this.src='<?php echo APP_URL; ?>/assets/images/default_student.svg';">
                    </div>
                    
                    <div class="student-name-title"><?php echo htmlspecialchars($qName); ?></div>
                    <div class="student-role-badge"><span>STUDENT</span></div>
                    
                    <table class="info-table">
                        <tr><td class="lbl">Student ID:</td><td class="val font-monospace"><?php echo htmlspecialchars($row['admission_no'] ?? 'N/A'); ?></td></tr>
                        <tr><td class="lbl">Roll Number:</td><td class="val"><?php echo htmlspecialchars($row['roll_no'] ?: 'N/A'); ?></td></tr>
                        <tr><td class="lbl">Campus:</td><td class="val"><?php echo htmlspecialchars($row['campus'] ?: 'Main Campus'); ?></td></tr>
                        <tr><td class="lbl">Class & Sec:</td><td class="val"><?php echo htmlspecialchars($qClass . ' - ' . $qSection); ?></td></tr>
                        <tr><td class="lbl">Academic Session:</td><td class="val"><?php echo htmlspecialchars($row['academic_session'] ?: 'N/A'); ?></td></tr>
                        <tr><td class="lbl">Academic Type:</td><td class="val"><?php echo htmlspecialchars($row['academic_type'] ?? 'School'); ?></td></tr>
                    </table>
                    
                    <div class="footer-strip">
                        <span class="status-badge"><?php echo htmlspecialchars($row['status'] ?? 'Active'); ?></span>
                        <div class="sign-box">
                            <div class="sign-line"></div>
                            <span style="font-size:0.45rem;">Principal Signature</span>
                        </div>
                    </div>
                </div>

                <!-- Back Side -->
                <div class="pvc-card-side pvc-card-back" id="batch_back_<?php echo $row['id']; ?>">
                    <h6 class="back-title">STUDENT REGISTRY INFO</h6>
                    <div class="back-info">
                        <p>Father/Guardian: <strong><?php echo htmlspecialchars($row['father_name'] ?: $row['guardian_name'] ?: 'N/A'); ?></strong></p>
                        <p>Guardian Contact: <strong><?php echo htmlspecialchars($row['father_mobile'] ?: $row['guardian_phone'] ?: 'N/A'); ?></strong></p>
                        <p>Emergency Contact: <strong><?php echo htmlspecialchars($row['emergency_contact'] ?: $row['father_mobile'] ?: $row['guardian_phone'] ?: 'N/A'); ?></strong></p>
                    </div>
                    
                    <div class="qr-frame-box">
                        <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code" onerror="this.onerror=null; this.src='https://placehold.co/150x150?text=QR+Code';">
                    </div>
                    
                    <div class="rules-box">
                        <p class="return-msg">If found, please return this card to Indus Grammar School.</p>
                        <ol>
                            <li>Always display card on school premises.</li>
                            <li>Loss must be reported to administration.</li>
                            <li>Property of Indus Grammar School.</li>
                        </ol>
                    </div>
                    
                    <div class="school-address-box">
                        <p class="fw-bold">Indus Grammar School & Academy</p>
                        <p><?php echo SCHOOL_ADDRESS; ?> | Ph: <?php echo SCHOOL_PHONE; ?></p>
                        <p>Email: <?php echo SCHOOL_EMAIL; ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<!-- Isolated Container for Browser Print Output -->
<div id="printSection"></div>

<!-- Modal Dialog for Single Card Interactive Preview -->
<div class="modal fade d-print-none" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" style="max-width:640px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius:12px;">
            <div class="modal-header bg-light border-bottom-0 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="previewModalLabel"><i class="fa-solid fa-address-card text-primary me-2"></i>Student ID Card Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4 text-center bg-light">
                <div id="modalCardPreviewZone" class="d-flex justify-content-center gap-3 flex-wrap">
                    <!-- Dynamic Clone of Front and Back Card -->
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-primary rounded-pill px-4" onclick="downloadSinglePDF(activePreviewStudentId)"><i class="fa-solid fa-download me-2"></i>Download Card</button>
                <button type="button" class="btn btn-outline-primary rounded-pill px-4" id="modalPrintBtn"><i class="fa-solid fa-print me-2"></i>Print Card</button>
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- html2canvas and jsPDF Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
    // Search Button Spinner Feedback
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', () => {
            const btn = document.getElementById('searchBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Searching...';
            }
        });
    }

    // Single Student Print Helper
    function printSingleStudentCard(studentId) {
        const printSec = document.getElementById("printSection");
        const front = document.getElementById("front_" + studentId) || document.getElementById("batch_front_" + studentId);
        const back = document.getElementById("back_" + studentId) || document.getElementById("batch_back_" + studentId);
        
        if (!front || !back) {
            alert("Card preview not available.");
            return;
        }

        printSec.innerHTML = `
            <div class="id-card-wrap">${front.outerHTML}</div>
            <div class="id-card-wrap">${back.outerHTML}</div>
        `;
        window.print();
        printSec.innerHTML = "";
    }

    // Modal Card Preview Trigger
    const previewModalEl = document.getElementById("previewModal");
    const previewModal = previewModalEl ? new bootstrap.Modal(previewModalEl) : null;
    let activePreviewStudentId = 0;

    function previewSingleCard(student) {
        activePreviewStudentId = student.id;
        const front = document.getElementById("front_" + student.id) || document.getElementById("batch_front_" + student.id);
        const back = document.getElementById("back_" + student.id) || document.getElementById("batch_back_" + student.id);
        
        if (front && back && previewModal) {
            document.getElementById("modalCardPreviewZone").innerHTML = `
                <div class="id-card-wrap">${front.outerHTML}</div>
                <div class="id-card-wrap">${back.outerHTML}</div>
            `;
            previewModal.show();
        }
    }

    const modalPrintBtn = document.getElementById("modalPrintBtn");
    if (modalPrintBtn) {
        modalPrintBtn.addEventListener("click", () => {
            if (activePreviewStudentId > 0) {
                printSingleStudentCard(activePreviewStudentId);
            }
        });
    }

    // Toggle All Batch Checkboxes
    function toggleAllCheckboxes(master) {
        document.querySelectorAll(".student-chk").forEach(chk => {
            chk.checked = master.checked;
        });
    }

    // Print Multiple Selected Cards
    function printSelectedCards() {
        const printSec = document.getElementById("printSection");
        let html = '';
        
        document.querySelectorAll(".student-chk:checked").forEach(chk => {
            const id = chk.value;
            const front = document.getElementById("front_" + id) || document.getElementById("batch_front_" + id);
            const back = document.getElementById("back_" + id) || document.getElementById("batch_back_" + id);
            if (front && back) {
                html += `
                    <div class="id-card-wrap">${front.outerHTML}</div>
                    <div class="id-card-wrap">${back.outerHTML}</div>
                `;
            }
        });
        
        if (html === '') {
            alert("Please select at least one student card to print.");
            return;
        }
        
        printSec.innerHTML = html;
        window.print();
        printSec.innerHTML = "";
    }

    // Single PDF Download
    function downloadSinglePDF(studentId) {
        const front = document.getElementById("front_" + studentId) || document.getElementById("batch_front_" + studentId);
        const back = document.getElementById("back_" + studentId) || document.getElementById("batch_back_" + studentId);
        
        if (!front || !back) {
            alert("Card content not available.");
            return;
        }
        generatePDFFromElements([front, back], `Student_ID_Card_${studentId}.pdf`);
    }

    // Bulk PDF Download
    function downloadSelectedPDF() {
        const checked = document.querySelectorAll(".student-chk:checked");
        if (checked.length === 0) {
            alert("Please select at least one student.");
            return;
        }

        const elements = [];
        checked.forEach(chk => {
            const id = chk.value;
            const front = document.getElementById("front_" + id) || document.getElementById("batch_front_" + id);
            const back = document.getElementById("back_" + id) || document.getElementById("batch_back_" + id);
            if (front && back) {
                elements.push(front, back);
            }
        });

        generatePDFFromElements(elements, `Student_ID_Cards_${Date.now()}.pdf`);
    }

    // Canvas & PDF Generator Core (Fixed for zero-blank PDF rendering)
    function generatePDFFromElements(elements, filename) {
        const { jsPDF } = window.jspdf;
        const btn = window.event ? window.event.target.closest('button') : null;
        let originalText = '';
        if (btn) {
            originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';
        }

        // 1. Create a temporary, visible off-screen container outside viewport
        const renderContainer = document.createElement('div');
        renderContainer.id = 'pdf_offscreen_render_zone';
        renderContainer.style.cssText = 'position:fixed; left:-9999px; top:0; width:auto; height:auto; opacity:1; visibility:visible; display:block; z-index:-9999; background:#ffffff;';
        document.body.appendChild(renderContainer);

        // 2. Clone each target card element into the off-screen container
        const clonedElements = [];
        elements.forEach(el => {
            if (!el) return;
            const wrap = document.createElement('div');
            wrap.className = 'id-card-wrap';
            wrap.style.cssText = 'display:inline-block !important; visibility:visible !important; opacity:1 !important; margin:0; padding:0;';
            
            const clone = el.cloneNode(true);
            clone.style.cssText = 'display:inline-block !important; visibility:visible !important; opacity:1 !important; margin:0; padding:0;';
            
            wrap.appendChild(clone);
            renderContainer.appendChild(wrap);
            clonedElements.push(clone);
        });

        if (clonedElements.length === 0) {
            alert("No card elements found for PDF generation.");
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
            if (renderContainer.parentNode) renderContainer.parentNode.removeChild(renderContainer);
            return;
        }

        // 3. Helper to wait for all images inside an element to complete loading
        function waitForImages(container) {
            const imgs = Array.from(container.querySelectorAll('img'));
            const promises = imgs.map(img => {
                if (img.complete && img.naturalHeight !== 0) {
                    return Promise.resolve();
                }
                return new Promise(resolve => {
                    img.onload = resolve;
                    img.onerror = resolve;
                });
            });
            return Promise.all(promises);
        }

        // 4. Wait for images to load, then execute html2canvas on visible offscreen clones
        waitForImages(renderContainer).then(() => {
            const promises = clonedElements.map(clone => {
                return html2canvas(clone, {
                    scale: 3,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#ffffff',
                    logging: false
                });
            });

            return Promise.all(promises);
        }).then(canvases => {
            // CR80 PVC Standard Dimension in mm: 53.98 x 85.60
            const pdf = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: [53.98, 85.60]
            });

            canvases.forEach((canvas, index) => {
                if (index > 0) {
                    pdf.addPage([53.98, 85.60], 'portrait');
                }
                const imgData = canvas.toDataURL('image/jpeg', 0.98);
                pdf.addImage(imgData, 'JPEG', 0, 0, 53.98, 85.60);
            });

            pdf.save(filename);
        }).catch(err => {
            console.error("PDF generation error:", err);
            alert("Failed to generate PDF document. Please try again.");
        }).finally(() => {
            // Clean up temporary container
            if (renderContainer && renderContainer.parentNode) {
                renderContainer.parentNode.removeChild(renderContainer);
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    }
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
