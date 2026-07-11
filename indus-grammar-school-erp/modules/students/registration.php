<?php
/**
 * Indus Grammar School ERP - Simplified Student Registration Form
 * Version 5.0.0
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

// Load student list for quick search dropdown
$allStudentsList = [];
try {
    $allStudentsList = $db->query("
        SELECT id, admission_no, first_name, last_name 
        FROM students 
        ORDER BY admission_no DESC
    ")->fetchAll();
} catch (Exception $e) {}

// Check if student ID is loaded via GET (for editing/viewing)
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
            $details = $stmtDetails->fetch();
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

// Load dynamic sections from classes
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

// Handle POST actions (processed before any HTML output is sent)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // File Upload Handler Function
    function uploadStudentDoc($key, $studentId, $prefix, &$err) {
        if (empty($_FILES[$key]['name'])) return null;
        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
        $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $err = "Invalid file extension for " . str_replace('_', ' ', $key) . ". Allowed: JPG, JPEG, PNG, PDF, DOC, DOCX.";
            return null;
        }
        
        if ($_FILES[$key]['size'] > 5 * 1024 * 1024) {
            $err = "File size limit exceeded for " . str_replace('_', ' ', $key) . ". Max: 5MB.";
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
            
            // Basic required fields verification
            $first_name = sanitize($_POST['first_name'] ?? '');
            $last_name = sanitize($_POST['last_name'] ?? '');
            $gender = sanitize($_POST['gender'] ?? 'Male');
            $dob = sanitize($_POST['date_of_birth'] ?? '');
            $guardian_name = sanitize($_POST['guardian_name'] ?? '');
            $guardian_phone = sanitize($_POST['guardian_phone'] ?? '');
            $status = sanitize($_POST['status'] ?? 'Active');
            
            if (!$first_name || !$last_name || !$dob || !$guardian_name || !$guardian_phone) {
                throw new Exception("Please fill in all required basic student fields.");
            }
            
            // Academic Fields
            $academic_type = sanitize($_POST['academic_type'] ?? 'School');
            $school_class = sanitize($_POST['school_class'] ?? '');
            $school_section = sanitize($_POST['school_section'] ?? '');
            
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
            
            // Duplicate checks logic
            if ($action === 'save') {
                $checkAdm = $db->prepare("SELECT COUNT(*) FROM students WHERE admission_no = ?");
                $checkAdm->execute([$adm_no]);
                if ($checkAdm->fetchColumn() > 0) throw new Exception("Admission Number '$adm_no' already exists.");
                
                if ($roll_no) {
                    $checkRoll = $db->prepare("SELECT COUNT(*) FROM student_registration_details WHERE roll_no = ?");
                    $checkRoll->execute([$roll_no]);
                    if ($checkRoll->fetchColumn() > 0) throw new Exception("Roll Number '$roll_no' already exists in details.");
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
                    'addr' => sanitize($_POST['current_address'] ?? ''),
                    'academic_type' => $academic_type,
                    'school_class' => $school_class,
                    'school_section' => $school_section
                ]);
                $currStudentId = (int)$db->lastInsertId();
            } else {
                // Update checks
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
                if (!$isSuperAdmin) {
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
                    'addr' => sanitize($_POST['current_address'] ?? ''),
                    'academic_type' => $academic_type,
                    'school_class' => $school_class,
                    'school_section' => $school_section
                ]);
            }
            
            // Upload student photo doc
            $docErr = '';
            $docStudentPhoto = uploadStudentDoc('doc_student_photo', $currStudentId, 'std', $docErr);
            if ($docErr) throw new Exception($docErr);
            
            if ($action === 'update' && $details) {
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
                'sess' => sanitize($_POST['academic_session'] ?? ''),
                'camp' => sanitize($_POST['campus'] ?? ''),
                'cnic' => $cnic_no,
                'smob' => sanitize($_POST['student_mobile'] ?? ''),
                'sem' => $student_email,
                'fname' => sanitize($_POST['father_name'] ?? ''),
                'fcnic' => $father_cnic,
                'fmob' => sanitize($_POST['father_mobile'] ?? ''),
                'grel' => sanitize($_POST['relationship'] ?? ''),
                'gcnic' => sanitize($_POST['guardian_cnic'] ?? ''),
                'gaddr' => sanitize($_POST['guardian_address'] ?? ''),
                'curr_addr' => sanitize($_POST['current_address'] ?? ''),
                'perm_addr' => sanitize($_POST['permanent_address'] ?? ''),
                'fplan' => sanitize($_POST['fee_plan'] ?? ''),
                'fadm' => (float)($_POST['fee_admission'] ?? 0.00),
                'fmonth' => (float)($_POST['fee_monthly'] ?? 0.00),
                'rem' => sanitize($_POST['remarks'] ?? ''),
                'd_std' => $docStudentPhoto ?: '',
                'academic_type' => $academic_type,
                'school_class' => $school_class,
                'school_section' => $school_section
            ]);
            
            // Record audit logs
            $logAction = ($action === 'save') ? 'Student Registered' : 'Student Updated';
            $logDesc = "Student: $first_name $last_name | Admission No: $adm_no | Type: $academic_type";
            $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$_SESSION['user_id'] ?? null, $logAction, $logDesc, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

            $db->commit();
            
            $successMessage = ($action === 'save') ? "Student Registered Successfully." : "Student profile updated successfully.";
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
                $_SESSION['flash_success'] = "Student file deleted successfully.";
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
$pageTitle = 'Student Registration Form';
$breadcrumbActive = 'Registration';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Header Toolbar -->
<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-address-card me-2 text-primary"></i>Student Registration</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <!-- Quick Search registered files -->
        <form method="GET" class="d-inline-block text-start me-2">
            <select class="form-select form-select-sm" name="id" onchange="this.form.submit()" style="min-width: 250px; border-radius: 8px;">
                <option value="">— Load Registered Student —</option>
                <?php foreach ($allStudentsList as $st): ?>
                    <option value="<?php echo $st['id']; ?>" <?php echo ($studentId == $st['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['admission_no'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="registration.php" class="btn btn-sm btn-outline-primary px-3 py-2"><i class="fa-solid fa-plus me-1"></i>New Registration</a>
    </div>
</div>

<!-- Progressive form layout -->
<form id="registrationForm" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
    <input type="hidden" name="action" id="formAction" value="<?php echo ($studentId > 0) ? 'update' : 'save'; ?>">
    <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">

    <!-- Top Action Buttons Panel -->
    <div class="card border border-light shadow-sm bg-white mb-4" style="border-radius: 12px;">
        <div class="card-body p-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div>
                <span class="badge bg-secondary px-3 py-2">Mode: <?php echo ($studentId > 0) ? 'Edit Profile' : 'New Intake'; ?></span>
            </div>
            <div class="d-flex gap-2">
                <?php if ($studentId === 0): ?>
                    <button type="submit" class="btn btn-primary px-4" id="submitBtn"><i class="fa-solid fa-floppy-disk me-2"></i>Save</button>
                    <button type="submit" name="save_and_new" class="btn btn-outline-primary"><i class="fa-solid fa-plus-square me-2"></i>Save & New</button>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary px-4" id="submitBtn"><i class="fa-solid fa-circle-check me-2"></i>Update File</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()"><i class="fa-solid fa-trash-can me-2"></i>Delete</button>
                    <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print File</button>
                <?php endif; ?>
                <button type="reset" class="btn btn-outline-secondary">Reset</button>
                <a href="list.php" class="btn btn-light"><i class="fa-solid fa-arrow-left me-2"></i>Back</a>
            </div>
        </div>
    </div>

    <!-- 4 Clean Stacked cards layout -->
    <div class="row g-4">
        
        <!-- CARD 1: Academic Placement -->
        <div class="col-md-6 col-lg-6">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-school text-primary me-2"></i> 1. Academic Placement</h5>
                </div>
                <div class="card-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Academic Type *</label>
                            <select class="form-select" name="academic_type" id="academicTypeSelect" required>
                                <option value="School" <?php echo (($student['academic_type'] ?? 'School') === 'School') ? 'selected' : ''; ?>>School</option>
                                <option value="Academy" <?php echo (($student['academic_type'] ?? '') === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Admission Number *</label>
                            <input type="text" class="form-control <?php echo $isSuperAdmin ? '' : 'bg-light'; ?>" name="admission_no" value="<?php echo sanitize($nextAdmissionNo); ?>" placeholder="ADM-2026-0001" <?php echo $isSuperAdmin ? '' : 'readonly'; ?> required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Class *</label>
                            <select class="form-select" name="school_class" id="schoolClassSelect" required>
                                <option value="">— Select Class —</option>
                                <?php foreach (['Play Group', 'Nursery', 'Prep', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10', 'Class 11', 'Class 12'] as $cls): ?>
                                    <option value="<?php echo $cls; ?>" <?php echo (($student['school_class'] ?? '') === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Section *</label>
                            <select class="form-select" name="school_section" id="schoolSectionSelect" required>
                                <option value="">— Select Section —</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?php echo $sec; ?>" <?php echo (($student['school_section'] ?? '') === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Admission Date *</label>
                            <input type="date" class="form-control" name="admission_date" value="<?php echo $student['enrollment_date'] ?? date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Academic Session *</label>
                            <select class="form-select" name="academic_session" required>
                                <option value="2026-2027" <?php echo (($details['academic_session'] ?? '') === '2026-2027') ? 'selected' : ''; ?>>2026-2027</option>
                                <option value="2025-2026" <?php echo (($details['academic_session'] ?? '') === '2025-2026') ? 'selected' : ''; ?>>2025-2026</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Campus *</label>
                            <select class="form-select" name="campus" required>
                                <option value="Main Campus" <?php echo (($details['campus'] ?? '') === 'Main Campus') ? 'selected' : ''; ?>>Main Campus</option>
                                <option value="City Campus" <?php echo (($details['campus'] ?? '') === 'City Campus') ? 'selected' : ''; ?>>City Campus</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="Active" <?php echo (($student['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Left" <?php echo (($student['status'] ?? '') === 'Left') ? 'selected' : ''; ?>>Left</option>
                                <option value="Graduated" <?php echo (($student['status'] ?? '') === 'Graduated') ? 'selected' : ''; ?>>Graduated</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-muted">Roll Number</label>
                            <input type="text" class="form-control" name="roll_no" value="<?php echo sanitize($nextRollNo); ?>" placeholder="e.g. 10-A-001">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 2: Student Personal Info -->
        <div class="col-md-6 col-lg-6">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-user text-primary me-2"></i> 2. Personal Information</h5>
                </div>
                <div class="card-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">First Name *</label>
                            <input type="text" class="form-control" name="first_name" value="<?php echo sanitize($student['first_name'] ?? ''); ?>" required placeholder="e.g. Zain">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" value="<?php echo sanitize($student['last_name'] ?? ''); ?>" required placeholder="e.g. Khan">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Gender *</label>
                            <select class="form-select" name="gender" required>
                                <option value="Male" <?php echo (($student['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (($student['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (($student['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Date of Birth *</label>
                            <input type="date" class="form-control" id="dobInput" name="date_of_birth" value="<?php echo $student['date_of_birth'] ?? ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Age (Auto Calculated)</label>
                            <input type="text" class="form-control bg-light" id="ageInput" readonly value="">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">B-Form / CNIC Number *</label>
                            <input type="text" class="form-control" name="cnic_no" placeholder="35201-1234567-1" value="<?php echo sanitize($details['cnic_no'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Student Mobile</label>
                            <input type="tel" class="form-control" name="student_mobile" value="<?php echo sanitize($details['student_mobile'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Student Email</label>
                            <input type="email" class="form-control" name="student_email" value="<?php echo sanitize($details['student_email'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 3: Parent & Contact Details -->
        <div class="col-md-6 col-lg-6">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-user-tie text-primary me-2"></i> 3. Parents & Contact Details</h5>
                </div>
                <div class="card-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Father Name *</label>
                            <input type="text" class="form-control" name="father_name" value="<?php echo sanitize($details['father_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Father CNIC *</label>
                            <input type="text" class="form-control" name="father_cnic" placeholder="35201-1234567-1" value="<?php echo sanitize($details['father_cnic'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Father Mobile *</label>
                            <input type="tel" class="form-control" name="father_mobile" value="<?php echo sanitize($details['father_mobile'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Guardian Name *</label>
                            <input type="text" class="form-control" name="guardian_name" value="<?php echo sanitize($student['guardian_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Guardian Phone *</label>
                            <input type="tel" class="form-control" name="guardian_phone" value="<?php echo sanitize($student['guardian_phone'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Relationship *</label>
                            <input type="text" class="form-control" name="relationship" placeholder="Father, Uncle, Mother..." value="<?php echo sanitize($details['guardian_relationship'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Current Address *</label>
                            <textarea class="form-control" name="current_address" rows="2" required><?php echo sanitize($details['current_address'] ?? $student['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Permanent Address</label>
                            <textarea class="form-control" name="permanent_address" rows="2"><?php echo sanitize($details['permanent_address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 4: Fees & Photograph -->
        <div class="col-md-6 col-lg-6">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-receipt text-primary me-2"></i> 4. Fees & Document File</h5>
                </div>
                <div class="card-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-muted">Fee Plan *</label>
                            <select class="form-select" name="fee_plan" required>
                                <option value="Regular Plan" <?php echo (($details['fee_plan'] ?? '') === 'Regular Plan') ? 'selected' : ''; ?>>Regular Plan</option>
                                <option value="Sibling Discount Plan" <?php echo (($details['fee_plan'] ?? '') === 'Sibling Discount Plan') ? 'selected' : ''; ?>>Sibling Discount Plan</option>
                                <option value="Scholarship Plan" <?php echo (($details['fee_plan'] ?? '') === 'Scholarship Plan') ? 'selected' : ''; ?>>Scholarship Plan</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Admission Fee (Rs.)</label>
                            <input type="number" class="form-control" name="fee_admission" value="<?php echo (float)($details['fee_admission'] ?? 5000.00); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Monthly Tuition Fee (Rs.)</label>
                            <input type="number" class="form-control" name="fee_monthly" value="<?php echo (float)($details['fee_monthly'] ?? 3000.00); ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-muted">Student Photograph</label>
                            <input type="file" class="form-control" name="doc_student_photo" accept="image/*" onchange="previewImage(this, 'photoPreview')">
                            <div class="mt-2 text-center">
                                <img id="photoPreview" src="<?php echo !empty($details['doc_student_photo']) ? APP_URL . '/' . $details['doc_student_photo'] : 'https://placehold.co/100x120?text=No+Photo'; ?>" class="img-thumbnail" style="max-height: 120px;">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Remarks / Special Notes</label>
                            <textarea class="form-control" name="remarks" rows="2"><?php echo sanitize($details['remarks'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</form>

<!-- Delete Form Submission -->
<?php if ($studentId > 0): ?>
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
</form>
<?php endif; ?>

<script>
// Image previewer helper
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Confirm before deleting student profile record
function confirmDelete() {
    if (confirm("Are you sure you want to delete this student registration file permanently?\nThis will remove all academic history, parent links, and document files!")) {
        document.getElementById("deleteForm").submit();
    }
}

document.addEventListener("DOMContentLoaded", () => {
    // Dynamic Age Calculator from Date of Birth
    const dobInput = document.getElementById("dobInput");
    const ageInput = document.getElementById("ageInput");
    
    function calculateAge() {
        if (!dobInput || !dobInput.value) {
            if (ageInput) ageInput.value = "";
            return;
        }
        const birthDate = new Date(dobInput.value);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        if (ageInput) ageInput.value = age + " Years";
    }
    
    if (dobInput) {
        dobInput.addEventListener("change", calculateAge);
        calculateAge(); // Initial check
    }
    
    // Form validation check & loader spinner trigger
    const form = document.getElementById("registrationForm");
    const submitBtn = document.getElementById("submitBtn");
    
    if (form) {
        form.addEventListener("submit", (e) => {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                
                alert("Please fill in all required fields indicated by *.");
                form.classList.add("was-validated");
            } else {
                // Show loading spinner
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
                }
            }
        });
    }
});
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
