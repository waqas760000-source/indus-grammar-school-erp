<?php
/**
 * Indus Grammar School ERP - Student Registration & Editing Workspace
 * Single-Column PDF Form UI with Search & Select Registered Student by ID (Press Enter)
 */

// 1. App Bootstrap & Authorization
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();
$currentUser = currentUser();
$isSuperAdmin = (($currentUser['role_name'] ?? '') === 'Super Admin' || ($currentUser['username'] ?? '') === 'waqas7600');

// Handle inline AJAX search endpoint if requested by autocomplete drawer
if (isset($_GET['ajax_search'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    $q = sanitize(trim($_GET['q'] ?? ''));
    $results = [];
    if (!empty($q)) {
        try {
            $stmt = $db->prepare("
                SELECT s.id, s.admission_no, s.first_name, s.last_name, s.school_class, s.school_section, d.father_name, d.doc_student_photo
                FROM students s
                LEFT JOIN student_registration_details d ON s.id = d.student_id
                WHERE s.id = :num 
                   OR s.admission_no LIKE :like 
                   OR s.first_name LIKE :like 
                   OR s.last_name LIKE :like 
                   OR CONCAT(s.first_name, ' ', s.last_name) LIKE :like
                   OR d.father_name LIKE :like
                ORDER BY s.id DESC LIMIT 8
            ");
            $numVal = is_numeric($q) ? (int)$q : 0;
            $stmt->execute(['num' => $numVal, 'like' => '%' . $q . '%']);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $r) {
                $results[] = [
                    'id' => $r['id'],
                    'adm' => $r['admission_no'],
                    'name' => trim($r['first_name'] . ' ' . $r['last_name']),
                    'class' => $r['school_class'] ?: 'Class',
                    'sec' => $r['school_section'] ?: 'Sec',
                    'father' => $r['father_name'] ?: '-',
                    'photo' => $r['doc_student_photo'] ?: ''
                ];
            }
        } catch (Exception $e) {}
    }
    echo json_encode($results);
    exit;
}

$message = '';
$error = '';

// Handle direct Search Query via GET/POST (Search & Edit by Student ID / Admission Code / Name)
$searchQuery = sanitize(trim($_REQUEST['search_query'] ?? ''));
$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($studentId === 0 && !empty($searchQuery)) {
    try {
        if (is_numeric($searchQuery)) {
            $stmtSearch = $db->prepare("SELECT id FROM students WHERE id = ? LIMIT 1");
            $stmtSearch->execute([(int)$searchQuery]);
            $foundId = $stmtSearch->fetchColumn();
            if ($foundId) {
                header("Location: registration.php?id=" . $foundId);
                exit;
            }
        }
        
        $stmtSearch = $db->prepare("
            SELECT id FROM students 
            WHERE admission_no = :sq 
               OR admission_no LIKE :like 
               OR first_name LIKE :like 
               OR last_name LIKE :like 
               OR CONCAT(first_name, ' ', last_name) LIKE :like
               OR guardian_name LIKE :like
            ORDER BY id DESC LIMIT 1
        ");
        $stmtSearch->execute(['sq' => $searchQuery, 'like' => '%' . $searchQuery . '%']);
        $foundId = $stmtSearch->fetchColumn();
        
        if ($foundId) {
            header("Location: registration.php?id=" . $foundId);
            exit;
        } else {
            $error = "No student record found matching search query: '" . htmlspecialchars($searchQuery) . "'";
        }
    } catch (Exception $e) {
        $error = "Search error: " . $e->getMessage();
    }
}

$student = [];
$details = [];

if ($studentId > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = :id");
        $stmt->execute(['id' => $studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
        if (!empty($student)) {
            $stmtDetails = $db->prepare("SELECT * FROM student_registration_details WHERE student_id = :id");
            $stmtDetails->execute(['id' => $studentId]);
            $details = $stmtDetails->fetch(PDO::FETCH_ASSOC) ?: [];
        } else {
            $studentId = 0;
            $error = "Selected student record not found.";
        }
    } catch (Exception $e) {
        $error = "Error loading student: " . $e->getMessage();
    }
}

// Generate default sequential values if new registration
$nextAdmissionNo = $student['admission_no'] ?? '';
$nextRollNo = $details['roll_no'] ?? '';

$nextNum = 1;
try {
    $maxId = (int)$db->query("SELECT MAX(id) FROM students")->fetchColumn();
    $nextNum = $maxId + 1;
} catch (Exception $e) {}

if ($studentId === 0) {
    $year = date('Y');
    $nextAdmissionNo = sprintf("%s-%s-%04d", PREFIX_ADMISSION_NO, $year, $nextNum);
    $nextRollNo = sprintf("%s-%s-%04d", PREFIX_ROLL_NO, $year, $nextNum);
}

// Fetch sections
$sections = ['Green', 'Red', 'A', 'B', 'C', 'D'];
try {
    $stmtSec = $db->query("SELECT DISTINCT section FROM classes WHERE section != '' ORDER BY section ASC");
    $dbSections = $stmtSec->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dbSections as $ds) {
        if (!in_array($ds, $sections)) {
            $sections[] = $ds;
        }
    }
} catch (Exception $e) {}

// Standard class list
$classList = ['Pre 9th Bio', 'Pre 9th Comp', '9th Bio', '9th Com', 'Prep', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10', 'Class 11', 'Class 12'];

// Class selector helper function
function isClassSelected($currentVal, $targetVal) {
    if (trim($currentVal) === trim($targetVal)) return true;
    if ($targetVal === '1' && ($currentVal === 'Class 1' || $currentVal === '1')) return true;
    if ($targetVal === '9th Bio' && ($currentVal === '9th Bio' || $currentVal === '9th Biology')) return true;
    if ($targetVal === 'Pre 9th Bio' && ($currentVal === 'Pre 9th Bio')) return true;
    if ($targetVal === 'Pre 9th Comp' && ($currentVal === 'Pre 9th Comp' || $currentVal === 'Pre-9th-COMP')) return true;
    return false;
}

// File Upload Handler Function
function uploadStudentDoc($key, $studentId, $prefix, &$err) {
    if (empty($_FILES[$key]['name'])) return null;
    $allowed = ['jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        $err = "Invalid photo format. Please upload a JPG or PNG image.";
        return null;
    }
    
    if ($_FILES[$key]['size'] > 5 * 1024 * 1024) {
        $err = "Maximum allowed photo file size is 5MB.";
        return null;
    }
    
    $targetDir = __DIR__ . '/../../uploads/students/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $filename = $prefix . '_' . $studentId . '_photo.jpeg';
    if (move_uploaded_file($_FILES[$key]['tmp_name'], $targetDir . $filename)) {
        return 'uploads/students/' . $filename;
    }
    return null;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save' || $action === 'update') {
        try {
            $db->beginTransaction();
            
            $campus = sanitize($_POST['campus'] ?? 'Main Campus');
            $first_name = sanitize($_POST['first_name'] ?? '');
            $last_name = sanitize($_POST['last_name'] ?? '');
            $father_name = sanitize($_POST['father_name'] ?? '');
            $father_mobile = sanitize($_POST['father_mobile'] ?? '');
            $current_address = sanitize($_POST['current_address'] ?? 'Thokar Niaz Baig LHR');
            
            $guardian_name = $father_name ?: ($first_name . ' Guardian');
            $guardian_phone = $father_mobile ?: '0300-0000000';
            $gender = sanitize($_POST['gender'] ?? 'Male');
            $dob = sanitize($_POST['date_of_birth'] ?? '2010-01-01');
            $status = sanitize($_POST['status'] ?? 'Active');
            
            if (!$first_name || !$father_name || !$father_mobile) {
                throw new Exception("Please fill in Student Name, Father's Name, and Father's Mobile.");
            }
            
            $academic_type = 'School';
            $school_class = sanitize($_POST['school_class'] ?? '');
            $school_section = sanitize($_POST['school_section'] ?? '');
            $academic_session = sanitize($_POST['academic_session'] ?? '2026-2027');
            
            if (!$school_class || !$school_section) {
                throw new Exception("Class and Section are required fields.");
            }
            
            $stmtCls = $db->prepare("SELECT id FROM classes WHERE class_name = ? AND section = ?");
            $stmtCls->execute([$school_class, $school_section]);
            $class_id = $stmtCls->fetchColumn();
            
            if (!$class_id) {
                $stmtInsert = $db->prepare("INSERT INTO classes (class_name, section) VALUES (?, ?)");
                $stmtInsert->execute([$school_class, $school_section]);
                $class_id = (int)$db->lastInsertId();
            }
            
            $adm_no = sanitize($_POST['admission_no'] ?? '');
            $roll_no = sanitize($_POST['roll_no'] ?? '');
            $cnic_no = sanitize($_POST['cnic_no'] ?? '');
            $father_cnic = sanitize($_POST['father_cnic'] ?? '');
            $student_email = sanitize($_POST['student_email'] ?? '');

            if (!$adm_no) {
                throw new Exception("Admission Number is a required field.");
            }
            
            $roll_no = ($roll_no === '') ? null : $roll_no;
            $cnic_no = ($cnic_no === '') ? null : $cnic_no;
            $father_cnic = ($father_cnic === '') ? null : $father_cnic;

            $tuition_fee = isset($_POST['tuition_fee']) ? (float)$_POST['tuition_fee'] : 0.00;
            $fee_discount = isset($_POST['fee_discount']) ? (float)$_POST['fee_discount'] : 0.00;
            $base_fee = isset($_POST['fee_monthly']) ? (float)$_POST['fee_monthly'] : 2500.00;

            if ($action === 'save') {
                $stmtIns = $db->prepare("
                    INSERT INTO students (
                        admission_no, first_name, last_name, gender, date_of_birth, cnic_bform, 
                        enrollment_date, class_id, status, guardian_name, guardian_phone, guardian_email, address,
                        academic_type, school_class, school_section, tuition_fee
                    ) VALUES (
                        :adm, :fn, :ln, :gen, :dob, :cnic,
                        :adate, :cid, :stat, :gname, :gphone, :gemail, :addr,
                        :academic_type, :school_class, :school_section, :tfee
                    )
                ");
                $stmtIns->execute([
                    'adm' => $adm_no,
                    'fn' => $first_name,
                    'ln' => $last_name,
                    'gen' => $gender,
                    'dob' => $dob,
                    'cnic' => $cnic_no,
                    'adate' => sanitize($_POST['admission_date'] ?? date('Y-m-d')),
                    'cid' => $class_id,
                    'stat' => $status,
                    'gname' => $guardian_name,
                    'gphone' => $guardian_phone,
                    'gemail' => $student_email,
                    'addr' => $current_address,
                    'academic_type' => $academic_type,
                    'school_class' => $school_class,
                    'school_section' => $school_section,
                    'tfee' => $tuition_fee
                ]);
                $currStudentId = (int)$db->lastInsertId();
            } else {
                $currStudentId = (int)$_POST['student_id'];
                
                $stmtSt = $db->prepare("
                    UPDATE students SET 
                        admission_no = :adm, first_name = :fn, last_name = :ln, gender = :gen, date_of_birth = :dob, 
                        cnic_bform = :cnic, class_id = :cid, status = :stat, guardian_name = :gname, guardian_phone = :gphone, 
                        guardian_email = :gemail, address = :addr,
                        academic_type = :academic_type, school_class = :school_class, school_section = :school_section,
                        tuition_fee = :tfee
                    WHERE id = :id
                ");
                $stmtSt->execute([
                    'id' => $currStudentId,
                    'adm' => $adm_no,
                    'fn' => $first_name,
                    'ln' => $last_name,
                    'gen' => $gender,
                    'dob' => $dob,
                    'cnic' => $cnic_no,
                    'cid' => $class_id,
                    'stat' => $status,
                    'gname' => $guardian_name,
                    'gphone' => $guardian_phone,
                    'gemail' => $student_email,
                    'addr' => $current_address,
                    'academic_type' => $academic_type,
                    'school_class' => $school_class,
                    'school_section' => $school_section,
                    'tfee' => $tuition_fee
                ]);
            }
            
            $docErr = '';
            $docStudentPhoto = uploadStudentDoc('doc_student_photo', $currStudentId, 'std', $docErr);
            if ($docErr) throw new Exception($docErr);
            
            if ($action === 'update' && !empty($details['doc_student_photo'])) {
                $docStudentPhoto = $docStudentPhoto ?: $details['doc_student_photo'];
            }
            
            $stmtDet = $db->prepare("
                INSERT INTO student_registration_details (
                    student_id, roll_no, admission_date, academic_session, campus,
                    cnic_no, student_mobile, student_email,
                    father_name, father_cnic, father_mobile,
                    guardian_relationship, guardian_cnic, guardian_address, current_address, permanent_address,
                    fee_plan, fee_admission, fee_monthly, fee_discount, tuition_fee,
                    remarks, doc_student_photo,
                    academic_type, school_class, school_section
                ) VALUES (
                    :sid, :roll, :adate, :sess, :camp,
                    :cnic, :smob, :sem,
                    :fname, :fcnic, :fmob,
                    'Father', :gcnic, :gaddr, :curr_addr, :perm_addr,
                    'Regular Plan', 0, :fmonth, :fdisc, :tfee,
                    :rem, :d_std,
                    :academic_type, :school_class, :school_section
                ) ON DUPLICATE KEY UPDATE 
                    roll_no = VALUES(roll_no), admission_date = VALUES(admission_date), academic_session = VALUES(academic_session),
                    campus = VALUES(campus), cnic_no = VALUES(cnic_no), student_mobile = VALUES(student_mobile), student_email = VALUES(student_email),
                    father_name = VALUES(father_name), father_cnic = VALUES(father_cnic), father_mobile = VALUES(father_mobile),
                    guardian_cnic = VALUES(guardian_cnic), current_address = VALUES(current_address),
                    fee_monthly = VALUES(fee_monthly), fee_discount = VALUES(fee_discount), tuition_fee = VALUES(tuition_fee),
                    remarks = VALUES(remarks), doc_student_photo = VALUES(doc_student_photo),
                    academic_type = VALUES(academic_type), school_class = VALUES(school_class), school_section = VALUES(school_section)
            ");
            
            $stmtDet->execute([
                'sid' => $currStudentId,
                'roll' => $roll_no,
                'adate' => sanitize($_POST['admission_date'] ?? date('Y-m-d')),
                'sess' => $academic_session,
                'camp' => $campus,
                'cnic' => $cnic_no,
                'smob' => sanitize($_POST['student_mobile'] ?? ''),
                'sem' => $student_email,
                'fname' => $father_name,
                'fcnic' => $father_cnic,
                'fmob' => $father_mobile,
                'gcnic' => $father_cnic,
                'gaddr' => $current_address,
                'curr_addr' => $current_address,
                'perm_addr' => $current_address,
                'fmonth' => $base_fee,
                'fdisc' => $fee_discount,
                'tfee' => $tuition_fee,
                'rem' => sanitize($_POST['remarks'] ?? ''),
                'd_std' => $docStudentPhoto ?: '',
                'academic_type' => $academic_type,
                'school_class' => $school_class,
                'school_section' => $school_section
            ]);
            
            $db->commit();
            
            $successMessage = ($action === 'save') ? "Student registered successfully!" : "Student record #{$currStudentId} updated successfully!";
            $_SESSION['flash_success'] = $successMessage;
            
            header("Location: registration.php?id=" . $currStudentId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = $e->getMessage();
            $error = $e->getMessage();
        }
    }

    if ($action === 'delete') {
        try {
            $idToDelete = (int)$_POST['student_id'];
            if ($idToDelete > 0) {
                $db->beginTransaction();
                $stmtDel = $db->prepare("DELETE FROM students WHERE id = ?");
                $stmtDel->execute([$idToDelete]);
                $db->commit();
                $_SESSION['flash_success'] = "Student record deleted successfully.";
                header("Location: registration.php");
                exit;
            }
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = "Error deleting student: " . $e->getMessage();
            header("Location: registration.php");
            exit;
        }
    }
}

// Load Page Header
if ($studentId > 0 && !empty($student)) {
    $pageTitle = 'Edit Student Details - ' . sanitize(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
} else {
    $pageTitle = 'Student Registration Form';
}

include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Scoped Styles for Single-Column Form & Instant Student ID Search Bar -->
<style>
.adv-page-wrapper {
    background-color: #f5f7fb;
    border-radius: 18px;
    padding: 1.5rem;
    min-height: calc(100vh - 110px);
}

.adv-hero-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 55%, #2563eb 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.5rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.18);
    position: relative;
    overflow: hidden;
}

.hero-icon-box {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.25);
}

/* Instant Search Bar Card */
.instant-search-card {
    background: #ffffff;
    border: 2px solid #2563eb;
    border-radius: 14px;
    padding: 1rem 1.25rem;
    box-shadow: 0 6px 18px -3px rgba(37, 99, 235, 0.12);
    position: relative;
    max-width: 900px;
    margin: 0 auto 1.5rem auto;
}

.search-input-group {
    position: relative;
}

.search-input-group input {
    border-radius: 10px;
    border: 2px solid #93c5fd;
    padding: 0.65rem 1rem 0.65rem 2.75rem;
    font-size: 0.95rem;
    font-weight: 600;
    color: #0f172a;
    transition: all 0.2s ease;
}

.search-input-group input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
}

.search-icon-left {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #2563eb;
    font-size: 1.05rem;
    pointer-events: none;
}

/* Auto-suggest dropdown drawer */
.suggest-results-box {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1050;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.18);
    max-height: 300px;
    overflow-y: auto;
    display: none;
    margin-top: 4px;
}

.suggest-item {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.65rem 0.9rem;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background 0.15s ease;
    text-decoration: none;
    color: inherit;
}

.suggest-item:hover {
    background-color: #eff6ff;
}

.suggest-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    background: #e2e8f0;
}
</style>

<div class="adv-page-wrapper">
    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php" class="text-decoration-none"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item"><a href="list.php" class="text-decoration-none">Student Management</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo ($studentId > 0) ? 'Edit Student Details' : 'Student Registration'; ?></li>
        </ol>
    </nav>

    <!-- Hero Header Banner -->
    <div class="adv-hero-banner mb-4" style="max-width: 900px; margin: 0 auto;">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid <?php echo ($studentId > 0) ? 'fa-user-pen' : 'fa-graduation-cap'; ?>"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold mb-0 text-white fs-4"><?php echo ($studentId > 0 && !empty($student)) ? 'Edit Student Profile' : 'Student Registration Form'; ?></h3>
                        <span class="badge <?php echo ($studentId > 0) ? 'bg-warning text-dark' : 'bg-white text-primary'; ?> px-3 py-1 rounded-pill small fw-semibold">
                            <?php echo ($studentId > 0 && !empty($student)) ? 'Editing Record #' . sanitize($student['admission_no'] ?? '') : 'Indus Grammar School'; ?>
                        </span>
                    </div>
                    <p class="text-white-50 small mb-0">
                        <?php echo ($studentId > 0 && !empty($student)) ? 'Update personal details, class placement, guardian contact info, and tuition fee.' : 'Complete official single-column student registration form.'; ?>
                    </p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if (!empty($studentId) && $studentId > 0): ?>
                    <a href="../../templates/student_profile_report.php?id=<?php echo $studentId; ?>" target="_blank" class="btn btn-sm btn-success text-white rounded-2 px-3 py-1.5 fw-bold shadow-sm" title="Print/View Official Student Profile Report">
                        <i class="fa-solid fa-address-card me-1"></i>Student Profile Report
                    </a>
                    <a href="profile_report.php?id=<?php echo $studentId; ?>" class="btn btn-sm btn-info text-white rounded-2 px-3 py-1.5 fw-bold shadow-sm">
                        <i class="fa-solid fa-circle-user me-1"></i>View Profile
                    </a>
                <?php else: ?>
                    <a href="../../templates/student_profile_report.php" target="_blank" class="btn btn-sm btn-success text-white rounded-2 px-3 py-1.5 fw-bold shadow-sm" title="Print/View Student Profile Reports">
                        <i class="fa-solid fa-address-card me-1"></i>Student Profile Report
                    </a>
                <?php endif; ?>
                <a href="../../templates/registration_form.php<?php echo ($studentId > 0) ? '?id=' . $studentId : ''; ?>" target="_blank" class="btn btn-sm btn-warning text-dark rounded-2 px-3 py-1.5 fw-bold shadow-sm">
                    <i class="fa-solid fa-print me-1"></i>Print A4 Form
                </a>
                <a href="registration.php" class="btn btn-sm btn-light text-primary rounded-2 px-3 py-1.5 fw-semibold shadow-sm"><i class="fa-solid fa-plus me-1"></i>New Registration</a>
                <a href="list.php" class="btn btn-sm btn-outline-light rounded-2 px-3 py-1.5 fw-semibold"><i class="fa-solid fa-arrow-left me-1"></i>Student Directory</a>
            </div>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (!empty($error) || !empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4" style="max-width: 900px; margin: 0 auto 1.5rem auto;" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <strong>Error:</strong> <?php echo sanitize($error ?: $_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" style="max-width: 900px; margin: 0 auto 1.5rem auto;" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>
            <strong>Success:</strong> <?php echo sanitize($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- SEARCH & SELECT REGISTERED STUDENT TO EDIT (Input Text + Enter Key) -->
    <div class="instant-search-card">
        <form method="GET" action="registration.php" id="instantSearchForm" onsubmit="return handleInstantSearch(event);">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-warning fs-5"></i>
                    <div>
                        <strong class="text-dark small d-block">Search & Select Registered Student to Edit</strong>
                        <span class="text-muted small" style="font-size: 0.78rem;">Type Student ID (e.g. 332, 1043, 1531), Admission Code, or Name and press <strong>ENTER</strong> to edit</span>
                    </div>
                </div>
                <?php if ($studentId > 0): ?>
                    <a href="registration.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 text-nowrap fw-bold" style="font-size: 0.78rem;">
                        <i class="fa-solid fa-xmark me-1"></i>New Registration Form
                    </a>
                <?php endif; ?>
            </div>

            <div class="search-input-group">
                <i class="fa-solid fa-magnifying-glass search-icon-left"></i>
                <input type="text" name="search_query" id="studentSearchInput" class="form-control" 
                       placeholder="Enter Student ID (e.g. 332, 1043, 1531), Admission Code, or Name and press Enter..." 
                       value="<?php echo sanitize($searchQuery); ?>" 
                       autocomplete="off" 
                       oninput="triggerAutoSuggest(this.value)">
                <button type="submit" class="btn btn-primary position-absolute end-0 top-0 bottom-0 px-4 fw-bold m-1" style="border-radius: 8px;">
                    <i class="fa-solid fa-arrow-right me-1"></i>Load Student
                </button>
                
                <!-- Live Auto-Suggest Drawer -->
                <div id="suggestResultsBox" class="suggest-results-box"></div>
            </div>
        </form>
    </div>

    <!-- PREVIOUS SINGLE-COLUMN FORM MATCHING PDF LAYOUT -->
    <form id="registrationForm" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
        <input type="hidden" name="action" id="formAction" value="<?php echo ($studentId > 0) ? 'update' : 'save'; ?>">
        <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
        
        <div class="card border-0 shadow rounded-4 mb-4 overflow-hidden" style="border: 2px solid #1e3a8a !important; max-width: 900px; margin: 0 auto;">
            <!-- PDF-Style Header -->
            <div class="d-flex align-items-center justify-content-between p-4 border-bottom" style="background: #ffffff;">
                <div class="d-flex align-items-center gap-3">
                    <div class="school-logo-box shadow-sm" style="width: 70px; height: 70px; border-radius: 50%; background: linear-gradient(135deg, #1e3a8a, #2563eb); display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; overflow: hidden;">
                        <?php $logoUrl = getSchoolLogoUrl(); if (!empty($logoUrl)): ?>
                            <img src="<?php echo $logoUrl; ?>" alt="School Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%; padding: 2px; background: white;">
                        <?php else: ?>
                            <i class="fa-solid fa-graduation-cap"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="mb-0 fw-bold" style="color: #1e3a8a; font-family: 'Cinzel', serif; letter-spacing: 0.5px;">INDUS GRAMMAR SCHOOL</h3>
                        <div class="badge bg-primary text-uppercase mt-2 px-3 py-2 rounded-pill shadow-sm"><i class="fa-solid fa-id-card me-2"></i> <?php echo ($studentId > 0) ? 'EDIT STUDENT REGISTRATION' : 'STUDENT REGISTRATION FORM'; ?></div>
                    </div>
                </div>
                <!-- Photo Upload Box -->
                <div class="text-center">
                    <div style="width: 100px; height: 120px; border: 2px dashed #cbd5e1; border-radius: 8px; margin: 0 auto; overflow: hidden; position: relative; background: #f8fafc; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;" onclick="document.getElementById('photoInput').click()" onmouseover="this.style.borderColor='#2563eb';" onmouseout="this.style.borderColor='#cbd5e1';">
                        <img id="photoPreview" src="<?php echo !empty($details['doc_student_photo']) ? APP_URL . '/' . $details['doc_student_photo'] : ''; ?>" style="width: 100%; height: 100%; object-fit: cover; <?php echo empty($details['doc_student_photo']) ? 'display:none;' : ''; ?>">
                        <div id="photoPlaceholder" style="<?php echo !empty($details['doc_student_photo']) ? 'display:none;' : ''; ?>">
                            <i class="fa-solid fa-camera text-secondary fs-3 mb-1"></i>
                            <div class="small fw-bold text-muted" style="font-size: 0.7rem; line-height: 1.2;">Affix Student<br>Photo</div>
                        </div>
                    </div>
                    <input type="file" name="doc_student_photo" id="photoInput" class="d-none" accept="image/jpeg,image/png" onchange="previewStudentImage(this)">
                </div>
            </div>

            <!-- 1. STUDENT INFORMATION -->
            <div class="p-0">
                <div class="d-flex justify-content-between align-items-center px-4 py-2" style="background: #2563eb; color: white; font-weight: 700; text-transform: uppercase; font-size: 0.9rem;">
                    <span><i class="fa-solid fa-user-graduate me-2"></i>1. Student Information</span>
                </div>
                <div class="row g-0">
                    <!-- Row 1 -->
                    <div class="col-md-6 border-bottom border-end p-3 d-flex align-items-center bg-light">
                        <label class="mb-0 me-3 text-muted fw-bold text-nowrap" style="width: 160px; font-size: 0.9rem;"><i class="fa-solid fa-id-card me-2 text-primary"></i> Student ID:</label>
                        <input type="text" class="form-control border-0 bg-transparent fw-bold text-primary fs-5 p-0" name="admission_no" value="<?php echo sanitize($nextAdmissionNo); ?>" readonly required>
                    </div>
                    <div class="col-md-6 border-bottom p-3 d-flex align-items-center bg-light">
                        <label class="mb-0 me-3 text-muted fw-bold text-nowrap" style="width: 160px; font-size: 0.9rem;"><i class="fa-solid fa-hashtag me-2 text-primary"></i> Roll Number:</label>
                        <input type="text" class="form-control border-0 border-bottom rounded-0 px-2 fw-bold" style="border-color: #000 !important; background: transparent;" name="roll_no" value="<?php echo sanitize($nextRollNo); ?>">
                    </div>
                    <!-- Row 2 -->
                    <div class="col-12 border-bottom p-3 d-flex align-items-center">
                        <label class="mb-0 me-3 text-muted fw-bold text-nowrap" style="width: 160px; font-size: 0.9rem;"><i class="fa-solid fa-user me-2 text-primary"></i> Student Name:</label>
                        <input type="text" class="form-control border-0 border-bottom rounded-0 px-2 fw-bold text-uppercase fs-5" style="border-color: #2563eb !important; border-style: dashed !important; color: #0f172a;" name="first_name" value="<?php echo sanitize($student['first_name'] ?? ''); ?>" required placeholder="e.g. ABDULLAH">
                    </div>
                    <!-- Row 3 -->
                    <div class="col-md-6 border-bottom border-end p-3 d-flex align-items-center">
                        <label class="mb-0 me-3 text-muted fw-bold text-nowrap" style="width: 160px; font-size: 0.9rem;"><i class="fa-solid fa-building-columns me-2 text-primary"></i> Class:</label>
                        <select class="form-select border-0 border-bottom rounded-0 px-2 fw-bold" style="border-color: #000 !important;" name="school_class" required>
                            <option value="">— Select —</option>
                            <?php foreach ($classList as $cls): ?>
                                <option value="<?php echo $cls; ?>" <?php echo isClassSelected($student['school_class'] ?? '', $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 border-bottom p-3 d-flex align-items-center">
                        <label class="mb-0 me-3 text-muted fw-bold text-nowrap" style="width: 160px; font-size: 0.9rem;"><i class="fa-solid fa-layer-group me-2 text-primary"></i> Section:</label>
                        <select class="form-select border-0 border-bottom rounded-0 px-2 fw-bold" style="border-color: #000 !important;" name="school_section" required>
                            <option value="">— Select —</option>
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?php echo $sec; ?>" <?php echo (($student['school_section'] ?? '') === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Row 4 -->
                    <div class="col-md-6 border-bottom border-end p-3 d-flex align-items-center">
                        <label class="mb-0 me-3 text-muted fw-bold text-nowrap" style="width: 160px; font-size: 0.9rem;"><i class="fa-solid fa-address-card me-2 text-primary"></i> B-Form / CNIC:</label>
                        <input type="text" class="form-control border-0 border-bottom rounded-0 px-2 fw-bold font-monospace" style="border-color: #000 !important;" id="cnicInput" name="cnic_no" value="<?php echo sanitize($details['cnic_no'] ?? ''); ?>" placeholder="XXXXX-XXXXXXX-X">
                    </div>
                    <!-- Row 5 (Student Mobile & Tuition Fee) -->
                    <div class="col-md-6 border-bottom border-end p-3 d-flex align-items-center">
                        <label class="mb-0 me-3 text-muted fw-bold text-nowrap" style="width: 160px; font-size: 0.9rem;"><i class="fa-solid fa-mobile-screen me-2 text-primary"></i> Student Mobile:</label>
                        <input type="text" class="form-control border-0 border-bottom rounded-0 px-2 fw-bold font-monospace" style="border-color: #000 !important;" name="student_mobile" value="<?php echo sanitize($details['student_mobile'] ?? ''); ?>" placeholder="03XX-XXXXXXX">
                    </div>
                    <div class="col-md-6 border-bottom p-3 d-flex align-items-center bg-light">
                        <label class="mb-0 me-3 text-muted fw-bold text-nowrap" style="width: 160px; font-size: 0.9rem;"><i class="fa-solid fa-money-bill-wave me-2 text-primary"></i> Tuition Fee:</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text border-0 bg-transparent fw-bold text-primary">Rs.</span>
                            <input type="number" step="0.01" min="0" class="form-control border-0 border-bottom rounded-0 px-2 fw-bold text-dark fs-5" style="border-color: #2563eb !important; background: transparent;" name="tuition_fee" value="<?php echo sanitize($student['tuition_fee'] ?? $details['tuition_fee'] ?? '0.00'); ?>" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. FATHER & CONTACT DETAILS -->
            <div class="p-0">
                <div class="d-flex justify-content-between align-items-center px-4 py-2" style="background: #2563eb; color: white; font-weight: 700; text-transform: uppercase; font-size: 0.9rem;">
                    <span><i class="fa-solid fa-user-tie me-2"></i>2. Father & Contact Details</span>
                </div>
                <div class="row g-0">
                    <!-- Row 1 -->
                    <div class="col-12 border-bottom p-3 d-flex align-items-center">
                        <label class="mb-0 me-3 text-muted fw-bold text-nowrap" style="width: 160px; font-size: 0.9rem;"><i class="fa-solid fa-user-shield me-2 text-primary"></i> Father Name:</label>
                        <input type="text" class="form-control border-0 border-bottom rounded-0 px-2 fw-bold text-uppercase fs-5" style="border-color: #000 !important; border-style: dashed !important; color: #0f172a;" name="father_name" value="<?php echo sanitize($details['father_name'] ?? ''); ?>" required placeholder="e.g. TARIQ MEHMOOD">
                    </div>
                    <!-- Row 2 -->
                    <div class="col-md-6 border-bottom border-end p-3 d-flex align-items-center">
                        <label class="mb-0 me-3 text-muted fw-bold text-nowrap" style="width: 160px; font-size: 0.9rem;"><i class="fa-solid fa-id-card me-2 text-primary"></i> Father CNIC:</label>
                        <input type="text" class="form-control border-0 border-bottom rounded-0 px-2 fw-bold font-monospace" style="border-color: #000 !important;" id="fatherCnicInput" name="father_cnic" value="<?php echo sanitize($details['father_cnic'] ?? ''); ?>" placeholder="XXXXX-XXXXXXX-X">
                    </div>
                    <div class="col-md-6 border-bottom p-3 d-flex align-items-center">
                        <label class="mb-0 me-3 text-muted fw-bold text-nowrap" style="width: 160px; font-size: 0.9rem;"><i class="fa-solid fa-phone me-2 text-primary"></i> Phone Number:</label>
                        <input type="text" class="form-control border-0 border-bottom rounded-0 px-2 fw-bold font-monospace" style="border-color: #000 !important;" name="father_mobile" value="<?php echo sanitize($details['father_mobile'] ?? ''); ?>" required placeholder="03XX-XXXXXXX">
                    </div>
                    <!-- Row 3 -->
                    <div class="col-12 p-3 bg-light d-flex align-items-center border-bottom">
                        <label class="mb-0 me-3 text-muted fw-bold text-nowrap" style="width: 160px; font-size: 0.9rem;"><i class="fa-solid fa-location-dot me-2 text-primary"></i> Address:</label>
                        <input type="text" class="form-control border-0 border-bottom rounded-0 px-2 fw-bold text-uppercase fs-5" style="border-color: #2563eb !important; border-style: dashed !important; color: #0f172a;" name="current_address" value="<?php echo sanitize($details['current_address'] ?? $student['address'] ?? ''); ?>" placeholder="e.g. HOUSE #, STREET, CITY / AREA">
                    </div>
                </div>
            </div>
            
            <!-- Declaration -->
            <div class="p-4" style="background: #f8fafc;">
                <div class="p-3" style="border-left: 4px solid #2563eb; background: #ffffff; border-radius: 0 8px 8px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                    <h6 class="fw-bold text-primary text-uppercase mb-2" style="font-size: 0.8rem;"><i class="fa-solid fa-file-contract me-1"></i> Parent / Guardian Undertaking & Declaration:</h6>
                    <p class="text-muted small mb-0" style="line-height: 1.6;">I hereby solemnly declare that the information provided above is complete, true, and correct to the best of my knowledge. I promise to strictly abide by all rules, discipline, code of conduct, and fee regulations of Indus Grammar School.</p>
                </div>
            </div>

            <!-- Submit Button Bar -->
            <div class="bg-white p-4 border-top d-flex justify-content-between align-items-center gap-3">
                <a href="list.php" class="text-secondary text-decoration-none fw-bold hover-primary"><i class="fa-solid fa-arrow-left me-1"></i>Cancel</a>
                <div class="d-flex gap-2 align-items-center">
                    <?php if ($studentId > 0): ?>
                        <button type="button" class="btn btn-outline-danger fw-bold rounded-pill px-4" onclick="confirmDelete()">
                            <i class="fa-solid fa-trash me-1"></i>Delete Student
                        </button>
                    <?php endif; ?>
                    <a href="../../templates/registration_form.php<?php echo ($studentId > 0) ? '?id=' . $studentId : ''; ?>" target="_blank" class="btn btn-warning text-dark fw-bold shadow-sm rounded-pill px-4 py-2">
                        <i class="fa-solid fa-print me-2"></i>Print A4 Form
                    </a>
                    <button type="submit" class="btn btn-primary fw-bold shadow rounded-pill px-5 py-2 fs-5" id="submitBtnBottom">
                        <i class="fa-solid <?php echo ($studentId > 0) ? 'fa-check' : 'fa-plus'; ?> me-2"></i>
                        <?php echo ($studentId > 0) ? 'Update Student Record' : 'Register Student'; ?>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Hidden Form for Deletion -->
    <?php if ($studentId > 0): ?>
    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
    </form>
    <?php endif; ?>
</div>

<script>
// Handle Student Search Input (Type ID / Admission Code & press ENTER)
function handleInstantSearch(e) {
    const val = document.getElementById('studentSearchInput').value.trim();
    if (!val) {
        e.preventDefault();
        return false;
    }
    return true; // Submit form to load student by ID
}

// Auto-suggest live drawer logic as user types
let suggestTimeout = null;
function triggerAutoSuggest(val) {
    clearTimeout(suggestTimeout);
    const resultsBox = document.getElementById('suggestResultsBox');
    
    if (!val || val.length < 1) {
        resultsBox.style.display = 'none';
        resultsBox.innerHTML = '';
        return;
    }
    
    suggestTimeout = setTimeout(() => {
        fetch('<?php echo APP_URL; ?>/modules/students/registration.php?ajax_search=1&q=' + encodeURIComponent(val))
            .then(res => res.json())
            .then(data => {
                if (data && data.length > 0) {
                    let html = '';
                    data.forEach(item => {
                        const photo = item.photo ? '<?php echo APP_URL; ?>/' + item.photo : '<?php echo APP_URL; ?>/assets/images/default-avatar.png';
                        html += `
                            <a href="registration.php?id=${item.id}" class="suggest-item">
                                <img src="${photo}" class="suggest-avatar" onerror="this.src='<?php echo APP_URL; ?>/assets/images/default-avatar.png'">
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-dark small">#${item.id} - ${item.name}</div>
                                    <div class="text-muted small" style="font-size: 0.78rem;">Code: <strong>${item.adm}</strong> | Class: ${item.class} (${item.sec}) | Father: ${item.father}</div>
                                </div>
                                <span class="badge bg-primary rounded-pill"><i class="fa-solid fa-pen me-1"></i>Edit</span>
                            </a>
                        `;
                    });
                    resultsBox.innerHTML = html;
                    resultsBox.style.display = 'block';
                } else {
                    resultsBox.innerHTML = '<div class="p-3 text-muted small text-center"><i class="fa-solid fa-folder-open me-1"></i>No student found matching "' + val + '". Press Enter to search.</div>';
                    resultsBox.style.display = 'block';
                }
            }).catch(() => {
                resultsBox.style.display = 'none';
            });
    }, 200);
}

// Hide suggest box on click outside
document.addEventListener('click', (e) => {
    const resultsBox = document.getElementById('suggestResultsBox');
    const searchInput = document.getElementById('studentSearchInput');
    if (resultsBox && !resultsBox.contains(e.target) && e.target !== searchInput) {
        resultsBox.style.display = 'none';
    }
});

// Live Image Preview Handler
function previewStudentImage(input) {
    const preview = document.getElementById('photoPreview');
    const placeholder = document.getElementById('photoPlaceholder');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 5 * 1024 * 1024) {
            alert("File size exceeds 5MB limit.");
            input.value = "";
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
        }
        reader.readAsDataURL(file);
    }
}

function confirmDelete() {
    if (confirm("Are you sure you want to delete this student file permanently?\nAll registration details will be removed.")) {
        document.getElementById("deleteForm").submit();
    }
}

document.addEventListener("DOMContentLoaded", () => {
    // Auto-Format CNIC fields
    function formatCnic(input) {
        input.addEventListener("input", (e) => {
            let val = e.target.value.replace(/\D/g, "");
            if (val.length > 13) val = val.substring(0, 13);
            let formatted = val;
            if (val.length > 5 && val.length <= 12) {
                formatted = val.substring(0, 5) + "-" + val.substring(5);
            } else if (val.length > 12) {
                formatted = val.substring(0, 5) + "-" + val.substring(5, 12) + "-" + val.substring(12);
            }
            e.target.value = formatted;
        });
    }

    const cnicInput = document.getElementById("cnicInput");
    const fatherCnicInput = document.getElementById("fatherCnicInput");
    if (cnicInput) formatCnic(cnicInput);
    if (fatherCnicInput) formatCnic(fatherCnicInput);
});
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
