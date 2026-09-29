<?php
/**
 * Indus Grammar School ERP - Student Registration Form
 * Version 8.0.0 - Advanced Commercial UI/UX Redesign
 */

// 1. App Bootstrap & Authorization (Processed BEFORE any output/redirects)
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();
$currentUser = currentUser();

// Determine if the current user has Super Admin authority
$isSuperAdmin = (($currentUser['role_name'] ?? '') === 'Super Admin' || ($currentUser['username'] ?? '') === 'waqas7600');

// Alert notifications
$message = '';
$error = '';

// Check if student ID is loaded via GET (for editing existing record)
$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student = [];
$details = [];

if ($studentId > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = :id");
        $stmt->execute(['id' => $studentId]);
        $student = $stmt->fetch();
        
        if ($student) {
            $stmtDetails = $db->prepare("SELECT * FROM student_registration_details WHERE student_id = :id");
            $stmtDetails->execute(['id' => $studentId]);
            $details = $stmtDetails->fetch() ?: [];
        } else {
            $studentId = 0;
            $error = "Selected student record not found.";
        }
    } catch (Exception $e) {
        $error = "Error loading student: " . $e->getMessage();
    }
}

// Generate default sequential values if new registration (can be overwritten)
$nextAdmissionNo = $student['admission_no'] ?? '';
$nextRollNo = $details['roll_no'] ?? '';

// Calculate next serial number sequence for ID generation
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

// Load dynamic sections from classes table
$sections = ['A', 'B', 'C', 'D', 'E'];
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
$classList = ['Prep', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];

// Fetch all registered students for quick edit lookup dropdown
$allStudentsList = [];
try {
    $allStudentsList = $db->query("
        SELECT id, admission_no, first_name, last_name, school_class, school_section 
        FROM students 
        ORDER BY first_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Function to normalize class value for compatibility with existing records
function isClassSelected($currentVal, $targetVal) {
    if ($currentVal === $targetVal) return true;
    if ($targetVal === '1' && ($currentVal === 'Class 1' || $currentVal === '1')) return true;
    if ($targetVal === '2' && ($currentVal === 'Class 2' || $currentVal === '2')) return true;
    if ($targetVal === '3' && ($currentVal === 'Class 3' || $currentVal === '3')) return true;
    if ($targetVal === '4' && ($currentVal === 'Class 4' || $currentVal === '4')) return true;
    if ($targetVal === '5' && ($currentVal === 'Class 5' || $currentVal === '5')) return true;
    if ($targetVal === '6' && ($currentVal === 'Class 6' || $currentVal === '6')) return true;
    if ($targetVal === '7' && ($currentVal === 'Class 7' || $currentVal === '7')) return true;
    if ($targetVal === '8' && ($currentVal === 'Class 8' || $currentVal === '8')) return true;
    if ($targetVal === '9' && ($currentVal === 'Class 9' || $currentVal === '9')) return true;
    if ($targetVal === '10' && ($currentVal === 'Class 10' || $currentVal === '10')) return true;
    if ($targetVal === '11' && ($currentVal === 'Class 11' || $currentVal === '11')) return true;
    if ($targetVal === '12' && ($currentVal === 'Class 12' || $currentVal === '12')) return true;
    return false;
}

// Handle POST actions (processed before any HTML output is sent)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
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
        
        $filename = $prefix . '_' . $studentId . '_' . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES[$key]['tmp_name'], $targetDir . $filename)) {
            return 'uploads/students/' . $filename;
        }
        return null;
    }

    if ($action === 'save' || $action === 'update') {
        try {
            $db->beginTransaction();
            
            // 1. Campus selection (Defaulting to Main Campus)
            $campus = sanitize($_POST['campus'] ?? 'Main Campus');

            // Basic required fields verification
            $first_name = sanitize($_POST['first_name'] ?? '');
            $last_name = sanitize($_POST['last_name'] ?? '');
            $gender = sanitize($_POST['gender'] ?? 'Male');
            $dob = sanitize($_POST['date_of_birth'] ?? '2010-01-01');
            $guardian_name = sanitize($_POST['father_name'] ?? '');
            $guardian_phone = sanitize($_POST['father_mobile'] ?? '');
            $status = sanitize($_POST['status'] ?? 'Active');
            
            if (!$first_name || !$guardian_name || !$guardian_phone) {
                throw new Exception("Please fill in Student Name, Father's Name, and Father's Mobile.");
            }
            
            // Academic Fields
            $academic_type = sanitize($_POST['academic_type'] ?? 'School');
            $school_class = sanitize($_POST['school_class'] ?? '');
            $school_section = sanitize($_POST['school_section'] ?? '');
            $academic_session = sanitize($_POST['academic_session'] ?? '2026-2027');
            
            if (!$school_class || !$school_section) {
                throw new Exception("Class and Section are required fields.");
            }
            
            // Dynamic Lookup/Insert of selected class and section combination
            $stmtCls = $db->prepare("SELECT id FROM classes WHERE class_name = ? AND section = ?");
            $stmtCls->execute([$school_class, $school_section]);
            $class_id = $stmtCls->fetchColumn();
            
            if (!$class_id) {
                $stmtInsert = $db->prepare("INSERT INTO classes (class_name, section) VALUES (?, ?)");
                $stmtInsert->execute([$school_class, $school_section]);
                $class_id = (int)$db->lastInsertId();
            }
            
            // Capture identifiers
            $adm_no = sanitize($_POST['admission_no'] ?? '');
            $roll_no = sanitize($_POST['roll_no'] ?? '');
            $cnic_no = sanitize($_POST['cnic_no'] ?? '');
            
            if (!$adm_no) {
                throw new Exception("Admission Number is a required field.");
            }
            
            // Convert empty inputs to NULL for unique database index mapping compatibility
            $roll_no = ($roll_no === '') ? null : $roll_no;
            $cnic_no = ($cnic_no === '') ? null : $cnic_no;
            
            // Format validations
            if ($cnic_no && !preg_match('/^\d{5}-\d{7}-\d{1}$/', $cnic_no)) {
                throw new Exception("B-Form / CNIC format must be XXXXX-XXXXXXX-X.");
            }
            $father_cnic = sanitize($_POST['father_cnic'] ?? '');
            if ($father_cnic && !preg_match('/^\d{5}-\d{7}-\d{1}$/', $father_cnic)) {
                throw new Exception("Father CNIC format must be XXXXX-XXXXXXX-X.");
            }
            
            $guardian_email = sanitize($_POST['guardian_email'] ?? '');
            if ($guardian_email && !filter_var($guardian_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Guardian Email address format is invalid.");
            }
            $student_email = sanitize($_POST['student_email'] ?? '');
            if ($student_email && !filter_var($student_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Student Email address format is invalid.");
            }
            
            // Unique validations duplicate checks
            $currStudentId = $studentId;
            
            if ($action === 'save') {
                $checkAdm = $db->prepare("SELECT COUNT(*) FROM students WHERE admission_no = ?");
                $checkAdm->execute([$adm_no]);
                if ($checkAdm->fetchColumn() > 0) throw new Exception("Admission Number '$adm_no' already exists.");
                
                if ($roll_no) {
                    $checkRoll = $db->prepare("SELECT COUNT(*) FROM student_registration_details WHERE roll_no = ?");
                    $checkRoll->execute([$roll_no]);
                    if ($checkRoll->fetchColumn() > 0) throw new Exception("Roll Number '$roll_no' already exists.");
                }
                
                if ($cnic_no) {
                    $checkCnic = $db->prepare("SELECT COUNT(*) FROM student_registration_details WHERE cnic_no = ?");
                    $checkCnic->execute([$cnic_no]);
                    if ($checkCnic->fetchColumn() > 0) throw new Exception("B-Form / CNIC Number '$cnic_no' is already registered.");
                }
                
                // 1. Insert into core students table
                $stmtSt = $db->prepare("
                    INSERT INTO students (
                        admission_no, first_name, last_name, gender, date_of_birth, enrollment_date, class_id, status, guardian_name, guardian_phone, guardian_email, address,
                        academic_type, school_class, school_section
                    ) VALUES (
                        :adm, :fn, :ln, :gen, :dob, :enr, :cid, :stat, :gname, :gphone, :gemail, :addr,
                        :academic_type, :school_class, :school_section
                    )
                ");
                $stmtSt->execute([
                    'adm' => $adm_no,
                    'fn' => $first_name,
                    'ln' => $last_name,
                    'gen' => $gender,
                    'dob' => $dob,
                    'enr' => sanitize($_POST['admission_date'] ?? date('Y-m-d')),
                    'cid' => $class_id,
                    'stat' => $status,
                    'gname' => $guardian_name,
                    'gphone' => $guardian_phone,
                    'gemail' => $guardian_email,
                    'addr' => 'Thokar Niaz Baig',
                    'academic_type' => $academic_type,
                    'school_class' => $school_class,
                    'school_section' => $school_section
                ]);
                $currStudentId = (int)$db->lastInsertId();
            } else {
                // Update duplicate checks
                $checkAdm = $db->prepare("SELECT COUNT(*) FROM students WHERE admission_no = ? AND id != ?");
                $checkAdm->execute([$adm_no, $currStudentId]);
                if ($checkAdm->fetchColumn() > 0) throw new Exception("Admission Number '$adm_no' already exists.");
                
                if ($roll_no) {
                    $checkRoll = $db->prepare("SELECT COUNT(*) FROM student_registration_details WHERE roll_no = ? AND student_id != ?");
                    $checkRoll->execute([$roll_no, $currStudentId]);
                    if ($checkRoll->fetchColumn() > 0) throw new Exception("Roll Number '$roll_no' already exists.");
                }
                
                if ($cnic_no) {
                    $checkCnic = $db->prepare("SELECT COUNT(*) FROM student_registration_details WHERE cnic_no = ? AND student_id != ?");
                    $checkCnic->execute([$cnic_no, $currStudentId]);
                    if ($checkCnic->fetchColumn() > 0) throw new Exception("B-Form / CNIC Number '$cnic_no' is already registered.");
                }
                
                // For non-Super Admins, force the Admission Number to remain unchanged
                if (!$isSuperAdmin && !empty($student['admission_no'])) {
                    $adm_no = $student['admission_no'];
                }
                
                // 1. Update core students table
                $stmtSt = $db->prepare("
                    UPDATE students SET 
                        admission_no = :adm, first_name = :fn, last_name = :ln, gender = :gen, date_of_birth = :dob, 
                        class_id = :cid, status = :stat, guardian_name = :gname, guardian_phone = :gphone, 
                        guardian_email = :gemail, address = :addr,
                        academic_type = :academic_type, school_class = :school_class, school_section = :school_section
                    WHERE id = :id
                ");
                $stmtSt->execute([
                    'id' => $currStudentId,
                    'adm' => $adm_no,
                    'fn' => $first_name,
                    'ln' => $last_name,
                    'gen' => $gender,
                    'dob' => $dob,
                    'cid' => $class_id,
                    'stat' => $status,
                    'gname' => $guardian_name,
                    'gphone' => $guardian_phone,
                    'gemail' => $guardian_email,
                    'addr' => 'Thokar Niaz Baig',
                    'academic_type' => $academic_type,
                    'school_class' => $school_class,
                    'school_section' => $school_section
                ]);
            }
            
            // Upload student photo doc
            $docErr = '';
            $docStudentPhoto = uploadStudentDoc('doc_student_photo', $currStudentId, 'std', $docErr);
            if ($docErr) throw new Exception($docErr);
            
            if ($action === 'update' && !empty($details['doc_student_photo'])) {
                $docStudentPhoto = $docStudentPhoto ?: $details['doc_student_photo'];
            }
            
            // 2. Insert/Update student_registration_details
            $stmtDet = $db->prepare("
                INSERT INTO student_registration_details (
                    student_id, roll_no, admission_date, academic_session, campus,
                    cnic_no, student_mobile, student_email,
                    father_name, father_cnic, father_mobile,
                    guardian_relationship, guardian_cnic, guardian_address, current_address, permanent_address,
                    fee_plan, fee_admission, fee_monthly,
                    remarks, doc_student_photo,
                    academic_type, school_class, school_section
                ) VALUES (
                    :sid, :roll, :adate, :sess, :camp,
                    :cnic, :smob, :sem,
                    :fname, :fcnic, :fmob,
                    :grel, :gcnic, :gaddr, :curr_addr, :perm_addr,
                    :fplan, :fadm, :fmonth,
                    :rem, :d_std,
                    :academic_type, :school_class, :school_section
                ) ON DUPLICATE KEY UPDATE 
                    roll_no = VALUES(roll_no), admission_date = VALUES(admission_date), academic_session = VALUES(academic_session),
                    campus = VALUES(campus), cnic_no = VALUES(cnic_no), student_mobile = VALUES(student_mobile), student_email = VALUES(student_email),
                    father_name = VALUES(father_name), father_cnic = VALUES(father_cnic), father_mobile = VALUES(father_mobile),
                    guardian_relationship = VALUES(guardian_relationship), guardian_cnic = VALUES(guardian_cnic), guardian_address = VALUES(guardian_address),
                    current_address = VALUES(current_address), permanent_address = VALUES(permanent_address),
                    fee_plan = VALUES(fee_plan), fee_admission = VALUES(fee_admission), fee_monthly = VALUES(fee_monthly),
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
                'fname' => sanitize($_POST['father_name'] ?? ''),
                'fcnic' => $father_cnic,
                'fmob' => sanitize($_POST['father_mobile'] ?? ''),
                'grel' => sanitize($_POST['relationship'] ?? ''),
                'gcnic' => sanitize($_POST['guardian_cnic'] ?? ''),
                'gaddr' => 'Thokar Niaz Baig',
                'curr_addr' => 'Thokar Niaz Baig',
                'perm_addr' => 'Thokar Niaz Baig',
                'fplan' => sanitize($_POST['fee_plan'] ?? 'Regular Plan'),
                'fadm' => (float)($_POST['fee_admission'] ?? 5000.00),
                'fmonth' => (float)($_POST['fee_monthly'] ?? 3000.00),
                'rem' => sanitize($_POST['remarks'] ?? ''),
                'd_std' => $docStudentPhoto ?: '',
                'academic_type' => $academic_type,
                'school_class' => $school_class,
                'school_section' => $school_section
            ]);
            
            // Record audit logs
            $logAction = ($action === 'save') ? 'Student Registered' : 'Student Updated';
            $logDesc = "Student: $first_name $last_name | Admission No: $adm_no | Campus: $campus | Type: $academic_type";
            $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$_SESSION['user_id'] ?? null, $logAction, $logDesc, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

            $db->commit();
            
            $successMessage = ($action === 'save') ? "Student registered successfully." : "Student profile updated successfully.";
            $_SESSION['flash_success'] = $successMessage;
            
            if ($action === 'save' && isset($_POST['save_and_new'])) {
                header("Location: registration.php");
                exit;
            } else {
                header("Location: registration.php?id=" . $currStudentId);
                exit;
            }
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
                
                // Fetch student details to log
                $stInfo = $db->query("SELECT first_name, last_name, admission_no FROM students WHERE id = $idToDelete")->fetch();
                
                $stmtDel = $db->prepare("DELETE FROM students WHERE id = ?");
                $stmtDel->execute([$idToDelete]);
                
                // Audit log
                $logDesc = "Deleted student ID: $idToDelete" . ($stInfo ? " ({$stInfo['first_name']} {$stInfo['last_name']} - {$stInfo['admission_no']})" : "");
                $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
                $stmtLog->execute([$_SESSION['user_id'] ?? null, 'Student Deleted', $logDesc, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                
                $db->commit();
                $_SESSION['flash_success'] = "Student record deleted successfully.";
                header("Location: registration.php");
                exit;
            }
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = "Error deleting student: " . $e->getMessage();
            header("Location: registration.php" . ($studentId > 0 ? "?id=$studentId" : ""));
            exit;
        }
    }
}

// 2. Load layout headers AFTER redirect processing has concluded
if ($studentId > 0 && !empty($student)) {
    $pageTitle = 'Edit Student Details - ' . sanitize(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
    $breadcrumbActive = 'Edit Student Profile';
} else {
    $pageTitle = 'Student Registration';
    $breadcrumbActive = 'Student Registration';
}
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Scoped Advanced ERP Workspace Styles -->
<style>
/* Page Canvas Background */
.adv-page-wrapper {
    background-color: #f5f7fb;
    border-radius: 18px;
    padding: 1.5rem;
    min-height: calc(100vh - 110px);
}

/* Hero Gradient Header Banner */
.adv-hero-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 55%, #2563eb 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.75rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.18);
    position: relative;
    overflow: hidden;
}

.adv-hero-banner::after {
    content: '';
    position: absolute;
    right: -40px;
    bottom: -40px;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
    pointer-events: none;
}

.hero-icon-box {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.25);
}

/* Quick Summary Panel Cards */
.summary-mini-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem 1.25rem;
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.04);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.summary-mini-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(15, 23, 42, 0.08);
}

.summary-mini-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: #eff6ff;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}

/* Sidebar Navigation Panel */
.adv-nav-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.04);
    position: sticky;
    top: 90px;
}

.nav-section-link {
    display: flex;
    align-items: center;
    padding: 0.65rem 0.9rem;
    color: #475569;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 500;
    border-radius: 10px;
    transition: all 0.2s ease;
    margin-bottom: 0.2rem;
}

.nav-section-link i {
    width: 24px;
    color: #94a3b8;
    transition: color 0.2s ease;
}

.nav-section-link:hover {
    background-color: #f1f5f9;
    color: #2563eb;
}

.nav-section-link:hover i {
    color: #2563eb;
}

/* Highlighted Student ID Area */
.highlight-id-box {
    background-color: #eff6ff;
    border: 1px solid #bfdbfe;
    border-left: 4px solid #2563eb;
    border-radius: 12px;
    padding: 1.1rem 1.4rem;
}

/* Main Section Cards */
.adv-section-card {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background-color: #ffffff;
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.04);
    transition: box-shadow 0.2s ease;
}

.adv-section-card:hover {
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
}

.adv-card-header {
    background-color: #ffffff;
    border-bottom: 1px solid #f1f5f9;
    padding: 1.1rem 1.5rem;
    border-top-left-radius: 14px;
    border-top-right-radius: 14px;
}

.adv-card-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
}

.adv-card-title i {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background-color: #eff6ff;
    color: #2563eb;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    font-size: 0.98rem;
}

/* Input Fields & Labels */
.adv-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 0.45rem;
}

.adv-required {
    color: #ef4444;
    font-weight: 700;
    margin-left: 2px;
}

.adv-control, .adv-select {
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    padding: 0.6rem 0.9rem;
    font-size: 0.9rem;
    color: #0f172a;
    background-color: #ffffff;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.adv-control:focus, .adv-select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.adv-control-readonly {
    background-color: #f8fafc !important;
    border-color: #e2e8f0;
    color: #1e3a8a;
    font-weight: 700;
    letter-spacing: 0.5px;
}

/* Photo Upload Box */
.photo-upload-box {
    width: 140px;
    height: 160px;
    border: 2px dashed #cbd5e1;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background-color: #f8fafc;
    margin: 0 auto;
    position: relative;
    transition: border-color 0.2s ease;
}

.photo-upload-box:hover {
    border-color: #2563eb;
}

.photo-upload-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Action Buttons */
.btn-adv-primary {
    background-color: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
    font-weight: 600;
    border-radius: 10px;
    padding: 0.68rem 1.75rem;
    transition: all 0.2s ease;
    box-shadow: 0 4px 8px -1px rgba(37, 99, 235, 0.25);
}

.btn-adv-primary:hover {
    background-color: #1d4ed8;
    border-color: #1d4ed8;
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 6px 14px -2px rgba(37, 99, 235, 0.35);
}

.adv-breadcrumb {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 0.6rem;
}

.adv-breadcrumb a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
}
</style>

<div class="adv-page-wrapper">
    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="adv-breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item"><a href="list.php">Student Management</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo ($studentId > 0 && !empty($student)) ? 'Edit Student Details' : 'Student Registration'; ?></li>
        </ol>
    </nav>

    <!-- Hero Header Banner -->
    <div class="adv-hero-banner mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid <?php echo ($studentId > 0) ? 'fa-user-pen' : 'fa-graduation-cap'; ?>"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold mb-0 text-white fs-3"><?php echo ($studentId > 0 && !empty($student)) ? 'Edit Student Profile' : 'Student Registration'; ?></h3>
                        <span class="badge <?php echo ($studentId > 0) ? 'bg-warning text-dark' : 'bg-white text-primary'; ?> px-3 py-1 rounded-pill small fw-semibold">
                            <?php echo ($studentId > 0 && !empty($student)) ? 'Editing Record #' . sanitize($student['admission_no'] ?? '') : 'Admissions Workspace'; ?>
                        </span>
                    </div>
                    <p class="text-white-50 small mb-0 fs-6">
                        <?php echo ($studentId > 0 && !empty($student)) ? 'Update personal details, academic placement, guardian contact info, and fee structure for ' . sanitize(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) . '.' : 'Create and manage student records with confidence for Indus Grammar School.'; ?>
                    </p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if (!empty($currStudentId) && $currStudentId > 0): ?>
                    <a href="profile_report.php?id=<?php echo $currStudentId; ?>" class="btn btn-sm btn-info text-white rounded-2 px-3 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-address-card me-1"></i>View Profile
                    </a>
                    <a href="../../templates/registration_form.php?id=<?php echo $currStudentId; ?>" target="_blank" class="btn btn-sm btn-warning text-dark rounded-2 px-3 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-print me-1"></i>Print A4 Form
                    </a>
                <?php else: ?>
                    <a href="../../templates/registration_form.php" target="_blank" class="btn btn-sm btn-outline-light rounded-2 px-3 py-2 fw-semibold">
                        <i class="fa-solid fa-file-lines me-1"></i>Blank A4 Form
                    </a>
                <?php endif; ?>
                <a href="registration.php" class="btn btn-sm btn-light text-primary rounded-2 px-3 py-2 fw-semibold shadow-sm"><i class="fa-solid fa-plus me-1"></i>New Registration</a>
                <a href="list.php" class="btn btn-sm btn-outline-light rounded-2 px-3 py-2 fw-semibold"><i class="fa-solid fa-arrow-left me-1"></i>Student Directory</a>
            </div>
        </div>
    </div>

    <!-- Quick Search & Select Student to Edit -->
    <div class="card border-0 shadow-sm bg-white mb-4 rounded-3 p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-magnifying-glass text-primary fs-5"></i>
                <div>
                    <strong class="text-dark small d-block"><i class="fa-solid fa-pen-to-square me-1 text-warning"></i>Search & Select Registered Student to Edit</strong>
                    <span class="text-muted small" style="font-size: 0.78rem;">Choose any existing student from the database below to instantly edit their details</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0" style="min-width: 340px;">
                <select class="form-select form-select-sm border-primary" id="quickEditSelect" onchange="if(this.value) window.location.href='registration.php?id=' + this.value;">
                    <option value="">— Select Student to Edit Details —</option>
                    <?php foreach ($allStudentsList as $stItem): ?>
                        <option value="<?php echo $stItem['id']; ?>" <?php echo ($studentId == $stItem['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($stItem['first_name'] . ' ' . $stItem['last_name'] . ' (' . $stItem['admission_no'] . ' - Class ' . ($stItem['school_class'] ?: '-') . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($studentId > 0): ?>
                    <a href="registration.php" class="btn btn-sm btn-outline-primary text-nowrap" title="Register New Student"><i class="fa-solid fa-plus me-1"></i>New</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- New Single-Column Minimal Registration Form matching PDF layout -->
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
                        <input type="text" class="form-control border-0 border-bottom rounded-0 px-2 fw-bold text-uppercase fs-5" style="border-color: #2563eb !important; border-style: dashed !important; color: #0f172a;" name="first_name" value="<?php echo sanitize($student['first_name'] ?? ''); ?>" required placeholder="e.g. MUHAMMAD ALI">
                    </div>
                    <!-- Row 3 -->
                    <div class="col-md-6 border-bottom border-end p-3 d-flex align-items-center">
                        <label class="mb-0 me-3 text-muted fw-bold text-nowrap" style="width: 160px; font-size: 0.9rem;"><i class="fa-solid fa-building-columns me-2 text-primary"></i> Class:</label>
                        <select class="form-select border-0 border-bottom rounded-0 px-2 fw-bold" style="border-color: #000 !important;" name="school_class" required>
                            <option value="">— Select —</option>
                            <?php foreach ($classList as $cls): ?>
                                <option value="<?php echo $cls; ?>" <?php echo isClassSelected($student['school_class'] ?? '', $cls) ? 'selected' : ''; ?>><?php echo ($cls === 'Prep' ? 'Prep' : 'Class ' . $cls); ?></option>
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
                    <!-- Row 5 (Student Mobile) -->
                    <div class="col-md-6 border-bottom p-3 d-flex align-items-center">
                        <label class="mb-0 me-3 text-muted fw-bold text-nowrap" style="width: 160px; font-size: 0.9rem;"><i class="fa-solid fa-mobile-screen me-2 text-primary"></i> Student Mobile:</label>
                        <input type="text" class="form-control border-0 border-bottom rounded-0 px-2 fw-bold font-monospace" style="border-color: #000 !important;" name="student_mobile" value="<?php echo sanitize($details['student_mobile'] ?? ''); ?>" placeholder="03XX-XXXXXXX">
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
                    <div class="col-12 p-4 bg-light d-flex align-items-center border-bottom">
                        <label class="mb-0 me-4 text-muted fw-bold text-nowrap" style="width: 150px; font-size: 0.9rem;"><i class="fa-solid fa-location-dot me-2 text-primary"></i> Address:</label>
                        <div class="fw-bold text-dark border-bottom border-primary w-100 pb-1 fs-5" style="border-style: dashed !important;">Thokar Niaz Baig</div>
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
            <div class="bg-white p-4 border-top d-flex justify-content-end gap-3 align-items-center">
                <a href="list.php" class="text-secondary text-decoration-none fw-bold me-2 hover-primary"><i class="fa-solid fa-arrow-left me-1"></i>Cancel</a>
                <button type="submit" class="btn btn-primary fw-bold shadow rounded-pill px-5 py-2 fs-5" id="submitBtnBottom">
                    <i class="fa-solid <?php echo ($studentId > 0) ? 'fa-check' : 'fa-plus'; ?> me-2"></i>
                    <?php echo ($studentId > 0) ? 'Update Student Record' : 'Register Student'; ?>
                </button>
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
// Live Image Preview Handler
function previewStudentImage(input) {
    const preview = document.getElementById('photoPreview');
    const placeholder = document.getElementById('photoPlaceholder');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Client file size validation (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert("File size exceeds 5MB limit. Please select a smaller photo.");
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

// Confirm Delete Dialog
function confirmDelete() {
    if (confirm("Are you sure you want to delete this student file permanently?\nThis action will remove all registration details and cannot be undone.")) {
        document.getElementById("deleteForm").submit();
    }
}

document.addEventListener("DOMContentLoaded", () => {
    // Dynamic Age Calculation based on Date of Birth
    const dobInput = document.getElementById("dobInput");
    const ageInput = document.getElementById("ageInput");
    
    function updateAge() {
        if (!dobInput || !dobInput.value) {
            if (ageInput) ageInput.value = "";
            return;
        }
        const birthDate = new Date(dobInput.value);
        const today = new Date();
        if (isNaN(birthDate.getTime())) {
            if (ageInput) ageInput.value = "";
            return;
        }
        
        let ageYears = today.getFullYear() - birthDate.getFullYear();
        let ageMonths = today.getMonth() - birthDate.getMonth();
        
        if (ageMonths < 0 || (ageMonths === 0 && today.getDate() < birthDate.getDate())) {
            ageYears--;
            ageMonths += 12;
        }
        
        if (ageYears < 0) {
            if (ageInput) ageInput.value = "Invalid DoB";
            return;
        }
        
        if (ageInput) {
            ageInput.value = ageYears + " Yrs " + (ageMonths > 0 ? ageMonths + " Mos" : "");
        }
    }
    
    if (dobInput) {
        dobInput.addEventListener("change", updateAge);
        updateAge(); // Initial check
    }

    // Auto-Format CNIC fields (XXXXX-XXXXXXX-X)
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
    
    // Form Validation Check & Button Spinner Trigger
    const form = document.getElementById("registrationForm");
    const submitBtnBottom = document.getElementById("submitBtnBottom");
    
    if (form) {
        form.addEventListener("submit", (e) => {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                
                form.classList.add("was-validated");
                
                // Focus first invalid field
                const firstInvalid = form.querySelector(":invalid");
                if (firstInvalid) {
                    firstInvalid.focus();
                    if (firstInvalid.scrollIntoView) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            } else {
                // Prevent duplicate form submissions by disabling submit buttons
                if (submitBtnBottom) {
                    submitBtnBottom.disabled = true;
                    submitBtnBottom.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
                }
            }
        });
    }

    // Smooth Scroll for Navigation Sidebar Links
    document.querySelectorAll('.nav-section-link').forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId.startsWith('#')) {
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    e.preventDefault();
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    });
});
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
