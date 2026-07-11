<?php
/**
 * Indus Grammar School ERP - Master Student Registration Form
 * Version 4.0.0 (School & Academy Hybrid Support)
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

// Classes checklist
$classes = [];
try {
    $classes = $db->query("SELECT * FROM classes ORDER BY class_name ASC, section ASC")->fetchAll();
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
            
            // Academic Type required field & specific validations
            $academic_type = sanitize($_POST['academic_type'] ?? 'School');
            $class_id = (int)($_POST['class_id'] ?? 0);
            $academy_program = sanitize($_POST['academy_program'] ?? '');
            $academy_batch = sanitize($_POST['academy_batch'] ?? '');
            
            $school_class = null;
            $school_section = null;
            
            if ($academic_type === 'School' || $academic_type === 'School + Academy') {
                if ($class_id <= 0) {
                    throw new Exception("School Class and Section are required for School students.");
                }
                // Resolve Class Name and Section Name from classes table
                $stmtCls = $db->prepare("SELECT class_name, section FROM classes WHERE id = ?");
                $stmtCls->execute([$class_id]);
                $clsRow = $stmtCls->fetch();
                if ($clsRow) {
                    $school_class = $clsRow['class_name'];
                    $school_section = $clsRow['section'];
                }
            }
            
            if ($academic_type === 'Academy' || $academic_type === 'School + Academy') {
                if (!$academy_program || !$academy_batch) {
                    throw new Exception("Academy Program and Batch are required for Academy students.");
                }
            }
            
            // Capture identifiers
            $adm_no = sanitize($_POST['admission_no'] ?? '');
            $roll_no = sanitize($_POST['roll_no'] ?? '');
            $cnic_no = sanitize($_POST['cnic_no'] ?? '');
            
            if (!$adm_no) {
                throw new Exception("Admission Number is a required field.");
            }
            
            // Convert empty inputs to NULL for unique database index mapping compat
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
            $mother_cnic = sanitize($_POST['mother_cnic'] ?? '');
            if ($mother_cnic && !preg_match('/^\d{5}-\d{7}-\d{1}$/', $mother_cnic)) {
                throw new Exception("Mother CNIC format must be XXXXX-XXXXXXX-X.");
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
                        academic_type, school_class, school_section, academy_program, academy_batch
                    ) VALUES (
                        :adm, :fn, :ln, :gen, :dob, :enr, :cid, :stat, :gname, :gphone, :gemail, :addr,
                        :academic_type, :school_class, :school_section, :academy_program, :academy_batch
                    )
                ");
                $stmtSt->execute([
                    'adm' => $adm_no,
                    'fn' => $first_name,
                    'ln' => $last_name,
                    'gen' => $gender,
                    'dob' => $dob,
                    'enr' => sanitize($_POST['admission_date'] ?? date('Y-m-d')),
                    'cid' => ($class_id > 0) ? $class_id : null,
                    'stat' => $status,
                    'gname' => $guardian_name,
                    'gphone' => $guardian_phone,
                    'gemail' => $guardian_email,
                    'addr' => sanitize($_POST['current_address'] ?? ''),
                    'academic_type' => $academic_type,
                    'school_class' => $school_class,
                    'school_section' => $school_section,
                    'academy_program' => $academy_program ?: null,
                    'academy_batch' => $academy_batch ?: null
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
                        academic_type = :academic_type, school_class = :school_class, school_section = :school_section, 
                        academy_program = :academy_program, academy_batch = :academy_batch
                    WHERE id = :id
                ");
                $stmtSt->execute([
                    'id' => $currStudentId,
                    'adm' => $adm_no,
                    'fn' => $first_name,
                    'ln' => $last_name,
                    'gen' => $gender,
                    'dob' => $dob,
                    'cid' => ($class_id > 0) ? $class_id : null,
                    'stat' => $status,
                    'gname' => $guardian_name,
                    'gphone' => $guardian_phone,
                    'gemail' => $guardian_email,
                    'addr' => sanitize($_POST['current_address'] ?? ''),
                    'academic_type' => $academic_type,
                    'school_class' => $school_class,
                    'school_section' => $school_section,
                    'academy_program' => $academy_program ?: null,
                    'academy_batch' => $academy_batch ?: null
                ]);
            }
            
            // Upload documents
            $docErr = '';
            $docStudentPhoto = uploadStudentDoc('doc_student_photo', $currStudentId, 'std', $docErr);
            $docFatherCnic = uploadStudentDoc('doc_father_cnic', $currStudentId, 'fcnic', $docErr);
            $docMotherCnic = uploadStudentDoc('doc_mother_cnic', $currStudentId, 'mcnic', $docErr);
            $docBForm = uploadStudentDoc('doc_bform', $currStudentId, 'bform', $docErr);
            $docBirthCert = uploadStudentDoc('doc_birth_cert', $currStudentId, 'birth', $docErr);
            $docLeavingCert = uploadStudentDoc('doc_leaving_cert', $currStudentId, 'leaving', $docErr);
            $docPrevResult = uploadStudentDoc('doc_prev_result', $currStudentId, 'result', $docErr);
            $docMedicalCert = uploadStudentDoc('doc_medical_cert', $currStudentId, 'medical', $docErr);
            $docOther = uploadStudentDoc('doc_other', $currStudentId, 'other', $docErr);
            
            if ($docErr) throw new Exception($docErr);
            
            // Load existing files if updating and no new upload was provided
            if ($action === 'update' && $details) {
                $docStudentPhoto = $docStudentPhoto ?: $details['doc_student_photo'];
                $docFatherCnic = $docFatherCnic ?: $details['doc_father_cnic'];
                $docMotherCnic = $docMotherCnic ?: $details['doc_mother_cnic'];
                $docBForm = $docBForm ?: $details['doc_bform'];
                $docBirthCert = $docBirthCert ?: $details['doc_birth_cert'];
                $docLeavingCert = $docLeavingCert ?: $details['doc_leaving_cert'];
                $docPrevResult = $docPrevResult ?: $details['doc_prev_result'];
                $docMedicalCert = $docMedicalCert ?: $details['doc_medical_cert'];
                $docOther = $docOther ?: $details['doc_other'];
            }
            
            // 2. Insert/Update student_registration_details
            $stmtDet = $db->prepare("
                INSERT INTO student_registration_details (
                    student_id, roll_no, admission_date, academic_session, campus,
                    blood_group, religion, nationality, cnic_no, birth_cert_no, birth_place, student_mobile, student_email,
                    father_name, father_cnic, father_mobile, father_occupation, father_office, father_income, father_email, father_photo,
                    mother_name, mother_cnic, mother_mobile, mother_occupation, mother_email, mother_photo,
                    guardian_relationship, guardian_cnic, guardian_address, current_address, permanent_address, city, province, postal_code, country,
                    prev_school, prev_class, prev_roll_no, prev_result, leaving_cert_no, test_marks,
                    medical_condition, allergies, disability, emergency_contact, doctor_name, doctor_contact,
                    transport_required, transport_route, pickup_point, drop_point, transport_vehicle, transport_driver,
                    sponsor_required, sponsor_name, sponsor_org, sponsor_contact, sponsor_amount,
                    fee_plan, fee_admission, fee_monthly, fee_discount, fee_scholarship, fee_fine, fee_security,
                    remarks, special_notes, doc_student_photo, doc_father_cnic, doc_mother_cnic, doc_bform, doc_birth_cert,
                    doc_leaving_cert, doc_prev_result, doc_medical_cert, doc_other,
                    academic_type, school_class, school_section, academy_program, academy_batch
                ) VALUES (
                    :sid, :roll, :adate, :sess, :camp,
                    :bg, :rel, :nat, :cnic, :bcert, :bplace, :smob, :sem,
                    :fname, :fcnic, :fmob, :focc, :foff, :finc, :fem, :fphoto,
                    :mname, :mcnic, :mmob, :mocc, :mem, :mphoto,
                    :grel, :gcnic, :gaddr, :curr_addr, :perm_addr, :city, :prov, :pcode, :country,
                    :pschool, :pclass, :proll, :presult, :lcert, :tmarks,
                    :mcond, :aller, :disab, :econtact, :docname, :doccontact,
                    :treq, :troute, :tpick, :tdrop, :tveh, :tdrv,
                    :sreq, :sname, :sorg, :scont, :samt,
                    :fplan, :fadm, :fmonth, :fdisc, :fschol, :ffine, :fsec,
                    :rem, :notes, :d_std, :d_fcnic, :d_mcnic, :d_bform, :d_bcert,
                    :d_lcert, :d_pres, :d_med, :d_oth,
                    :academic_type, :school_class, :school_section, :academy_program, :academy_batch
                ) ON DUPLICATE KEY UPDATE 
                    roll_no = VALUES(roll_no), admission_date = VALUES(admission_date), academic_session = VALUES(academic_session),
                    campus = VALUES(campus), blood_group = VALUES(blood_group), religion = VALUES(religion),
                    nationality = VALUES(nationality), cnic_no = VALUES(cnic_no), birth_cert_no = VALUES(birth_cert_no),
                    birth_place = VALUES(birth_place), student_mobile = VALUES(student_mobile), student_email = VALUES(student_email),
                    father_name = VALUES(father_name), father_cnic = VALUES(father_cnic), father_mobile = VALUES(father_mobile),
                    father_occupation = VALUES(father_occupation), father_office = VALUES(father_office), father_income = VALUES(father_income),
                    father_email = VALUES(father_email), father_photo = VALUES(father_photo), mother_name = VALUES(mother_name),
                    mother_cnic = VALUES(mother_cnic), mother_mobile = VALUES(mother_mobile), mother_occupation = VALUES(mother_occupation),
                    mother_email = VALUES(mother_email), mother_photo = VALUES(mother_photo), guardian_relationship = VALUES(guardian_relationship),
                    guardian_cnic = VALUES(guardian_cnic), guardian_address = VALUES(guardian_address), current_address = VALUES(current_address),
                    permanent_address = VALUES(permanent_address), city = VALUES(city), province = VALUES(province), postal_code = VALUES(postal_code),
                    country = VALUES(country), prev_school = VALUES(prev_school), prev_class = VALUES(prev_class), prev_roll_no = VALUES(prev_roll_no),
                    prev_result = VALUES(prev_result), leaving_cert_no = VALUES(leaving_cert_no), test_marks = VALUES(test_marks),
                    medical_condition = VALUES(medical_condition), allergies = VALUES(allergies), disability = VALUES(disability),
                    emergency_contact = VALUES(emergency_contact), doctor_name = VALUES(doctor_name), doctor_contact = VALUES(doctor_contact),
                    transport_required = VALUES(transport_required), transport_route = VALUES(transport_route), pickup_point = VALUES(pickup_point),
                    drop_point = VALUES(drop_point), transport_vehicle = VALUES(transport_vehicle), transport_driver = VALUES(transport_driver),
                    sponsor_required = VALUES(sponsor_required), sponsor_name = VALUES(sponsor_name), sponsor_org = VALUES(sponsor_org),
                    sponsor_contact = VALUES(sponsor_contact), sponsor_amount = VALUES(sponsor_amount), fee_plan = VALUES(fee_plan),
                    fee_admission = VALUES(fee_admission), fee_monthly = VALUES(fee_monthly), fee_discount = VALUES(fee_discount),
                    fee_scholarship = VALUES(fee_scholarship), fee_fine = VALUES(fee_fine), fee_security = VALUES(fee_security),
                    remarks = VALUES(remarks), special_notes = VALUES(special_notes), doc_student_photo = VALUES(doc_student_photo),
                    doc_father_cnic = VALUES(doc_father_cnic), doc_mother_cnic = VALUES(doc_mother_cnic), doc_bform = VALUES(doc_bform),
                    doc_birth_cert = VALUES(doc_birth_cert), doc_leaving_cert = VALUES(doc_leaving_cert), doc_prev_result = VALUES(doc_prev_result),
                    doc_medical_cert = VALUES(doc_medical_cert), doc_other = VALUES(doc_other),
                    academic_type = VALUES(academic_type), school_class = VALUES(school_class), school_section = VALUES(school_section),
                    academy_program = VALUES(academy_program), academy_batch = VALUES(academy_batch)
            ");
            
            $stmtDet->execute([
                'sid' => $currStudentId,
                'roll' => $roll_no,
                'adate' => sanitize($_POST['admission_date'] ?? date('Y-m-d')),
                'sess' => sanitize($_POST['academic_session'] ?? ''),
                'camp' => sanitize($_POST['campus'] ?? ''),
                'bg' => sanitize($_POST['blood_group'] ?? ''),
                'rel' => sanitize($_POST['religion'] ?? ''),
                'nat' => sanitize($_POST['nationality'] ?? ''),
                'cnic' => $cnic_no,
                'bcert' => sanitize($_POST['birth_certificate_number'] ?? ''),
                'bplace' => sanitize($_POST['place_of_birth'] ?? ''),
                'smob' => sanitize($_POST['student_mobile'] ?? ''),
                'sem' => $student_email,
                'fname' => sanitize($_POST['father_name'] ?? ''),
                'fcnic' => $father_cnic,
                'fmob' => sanitize($_POST['father_mobile'] ?? ''),
                'focc' => sanitize($_POST['father_occupation'] ?? ''),
                'foff' => sanitize($_POST['office_address'] ?? ''),
                'finc' => (float)($_POST['monthly_income'] ?? 0.00),
                'fem' => sanitize($_POST['father_email'] ?? ''),
                'fphoto' => $docFatherCnic ?: '',
                'mname' => sanitize($_POST['mother_name'] ?? ''),
                'mcnic' => $mother_cnic,
                'mmob' => sanitize($_POST['mother_mobile'] ?? ''),
                'mocc' => sanitize($_POST['mother_occupation'] ?? ''),
                'mem' => sanitize($_POST['mother_email'] ?? ''),
                'mphoto' => $docMotherCnic ?: '',
                'grel' => sanitize($_POST['relationship'] ?? ''),
                'gcnic' => sanitize($_POST['guardian_cnic'] ?? ''),
                'gaddr' => sanitize($_POST['guardian_address'] ?? ''),
                'curr_addr' => sanitize($_POST['current_address'] ?? ''),
                'perm_addr' => sanitize($_POST['permanent_address'] ?? ''),
                'city' => sanitize($_POST['city'] ?? ''),
                'prov' => sanitize($_POST['province'] ?? ''),
                'pcode' => sanitize($_POST['postal_code'] ?? ''),
                'country' => sanitize($_POST['country'] ?? ''),
                'pschool' => sanitize($_POST['previous_school'] ?? ''),
                'pclass' => sanitize($_POST['previous_class'] ?? ''),
                'proll' => sanitize($_POST['previous_roll_number'] ?? ''),
                'presult' => sanitize($_POST['previous_result'] ?? ''),
                'lcert' => sanitize($_POST['leaving_certificate_number'] ?? ''),
                'tmarks' => (float)($_POST['admission_test_marks'] ?? 0.00),
                'mcond' => sanitize($_POST['medical_condition'] ?? ''),
                'aller' => sanitize($_POST['allergies'] ?? ''),
                'disab' => sanitize($_POST['disability'] ?? ''),
                'econtact' => sanitize($_POST['emergency_contact'] ?? ''),
                'docname' => sanitize($_POST['doctor_name'] ?? ''),
                'doccontact' => sanitize($_POST['doctor_contact'] ?? ''),
                'treq' => isset($_POST['transport_required']) ? 1 : 0,
                'troute' => sanitize($_POST['transport_route'] ?? ''),
                'tpick' => sanitize($_POST['pickup_point'] ?? ''),
                'tdrop' => sanitize($_POST['drop_point'] ?? ''),
                'tveh' => sanitize($_POST['transport_vehicle'] ?? ''),
                'tdrv' => sanitize($_POST['transport_driver'] ?? ''),
                'sreq' => isset($_POST['sponsor_required']) ? 1 : 0,
                'sname' => sanitize($_POST['sponsor_name'] ?? ''),
                'sorg' => sanitize($_POST['sponsor_org'] ?? ''),
                'scont' => sanitize($_POST['sponsor_contact'] ?? ''),
                'samt' => (float)($_POST['sponsor_amount'] ?? 0.00),
                'fplan' => sanitize($_POST['fee_plan'] ?? ''),
                'fadm' => (float)($_POST['fee_admission'] ?? 0.00),
                'fmonth' => (float)($_POST['fee_monthly'] ?? 0.00),
                'fdisc' => (float)($_POST['fee_discount'] ?? 0.00),
                'fschol' => (float)($_POST['fee_scholarship'] ?? 0.00),
                'ffine' => (float)($_POST['fee_fine'] ?? 0.00),
                'fsec' => (float)($_POST['fee_security'] ?? 0.00),
                'rem' => sanitize($_POST['remarks'] ?? ''),
                'notes' => sanitize($_POST['special_notes'] ?? ''),
                'd_std' => $docStudentPhoto ?: '',
                'd_fcnic' => $docFatherCnic ?: '',
                'd_mcnic' => $docMotherCnic ?: '',
                'd_bform' => $docBForm ?: '',
                'd_bcert' => $docBirthCert ?: '',
                'd_lcert' => $docLeavingCert ?: '',
                'd_pres' => $docPrevResult ?: '',
                'd_med' => $docMedicalCert ?: '',
                'd_oth' => $docOther ?: '',
                'academic_type' => $academic_type,
                'school_class' => $school_class,
                'school_section' => $school_section,
                'academy_program' => $academy_program ?: null,
                'academy_batch' => $academy_batch ?: null
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

<!-- Progressive Wizard form layout -->
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

    <!-- Stepper Accordion Sections -->
    <div class="accordion" id="registrationStepper">
        
        <!-- SECTION 1: Admission & Program Identifiers -->
        <div class="accordion-item border-0 mb-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#secAdmission">
                    <i class="fa-solid fa-school text-primary me-2"></i> 1. Admission & System Identifiers
                </button>
            </h2>
            <div id="secAdmission" class="accordion-collapse collapse show" data-bs-parent="#registrationStepper">
                <div class="accordion-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Admission Number (Required) *</label>
                            <input type="text" class="form-control <?php echo $isSuperAdmin ? '' : 'bg-light'; ?>" name="admission_no" value="<?php echo sanitize($nextAdmissionNo); ?>" placeholder="ADM-2026-0001" <?php echo $isSuperAdmin ? '' : 'readonly'; ?> required>
                            <?php if (!$isSuperAdmin): ?>
                                <small class="text-muted d-block mt-1"><i class="fa-solid fa-circle-info me-1"></i>Admission Number edits are restricted to Super Admins.</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Academic Type *</label>
                            <select class="form-select" name="academic_type" id="academicTypeSelect" required>
                                <option value="School" <?php echo (($student['academic_type'] ?? 'School') === 'School') ? 'selected' : ''; ?>>School</option>
                                <option value="Academy" <?php echo (($student['academic_type'] ?? '') === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                                <option value="School + Academy" <?php echo (($student['academic_type'] ?? '') === 'School + Academy') ? 'selected' : ''; ?>>School + Academy</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Roll Number</label>
                            <input type="text" class="form-control" id="rollNumberInput" name="roll_no" value="<?php echo sanitize($nextRollNo); ?>" placeholder="10-A-001">
                        </div>

                        <!-- School Class Group -->
                        <div class="col-md-6 school-group-field" style="transition: all 0.3s ease;">
                            <label class="form-label small fw-semibold text-muted">School Class *</label>
                            <select class="form-select" name="class_id" id="classSelect">
                                <option value="">— Select School Class —</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" data-section="<?php echo sanitize($c['section']); ?>" <?php echo (($student['class_id'] ?? 0) == $c['id']) ? 'selected' : ''; ?>>
                                        <?php echo sanitize($c['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 school-group-field" style="transition: all 0.3s ease;">
                            <label class="form-label small fw-semibold text-muted">School Section</label>
                            <input type="text" class="form-control bg-light" id="sectionInput" name="section_name" readonly value="" placeholder="Auto Selected">
                        </div>

                        <!-- Academy Program Group -->
                        <div class="col-md-6 academy-group-field" style="transition: all 0.3s ease; display: none;">
                            <label class="form-label small fw-semibold text-muted">Academy Program *</label>
                            <select class="form-select" name="academy_program" id="academyProgramSelect">
                                <option value="">— Select Academy Program —</option>
                                <?php foreach(['9th Entry Test', '10th Entry Test', '1st Year Entry Test', '2nd Year Entry Test', 'MDCAT', 'ECAT', 'ICS Preparation', 'Pre-Medical', 'Pre-Engineering', 'Computer Courses', 'English Language', 'Spoken English', 'IELTS', 'Other'] as $prog): ?>
                                    <option value="<?php echo $prog; ?>" <?php echo (($student['academy_program'] ?? '') === $prog) ? 'selected' : ''; ?>><?php echo $prog; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 academy-group-field" style="transition: all 0.3s ease; display: none;">
                            <label class="form-label small fw-semibold text-muted">Academy Batch *</label>
                            <input type="text" class="form-control" name="academy_batch" id="academyBatchInput" value="<?php echo sanitize($student['academy_batch'] ?? ''); ?>" placeholder="e.g. Morning Batch A">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Admission Date *</label>
                            <input type="date" class="form-control" name="admission_date" value="<?php echo $student['enrollment_date'] ?? date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Academic Session *</label>
                            <select class="form-select" name="academic_session" required>
                                <option value="2026-2027" <?php echo (($details['academic_session'] ?? '') === '2026-2027') ? 'selected' : ''; ?>>2026-2027</option>
                                <option value="2025-2026" <?php echo (($details['academic_session'] ?? '') === '2025-2026') ? 'selected' : ''; ?>>2025-2026</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Campus *</label>
                            <select class="form-select" name="campus" required>
                                <option value="Main Campus" <?php echo (($details['campus'] ?? '') === 'Main Campus') ? 'selected' : ''; ?>>Main Campus</option>
                                <option value="City Campus" <?php echo (($details['campus'] ?? '') === 'City Campus') ? 'selected' : ''; ?>>City Campus</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="Active" <?php echo (($student['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Left" <?php echo (($student['status'] ?? '') === 'Left') ? 'selected' : ''; ?>>Left</option>
                                <option value="Graduated" <?php echo (($student['status'] ?? '') === 'Graduated') ? 'selected' : ''; ?>>Graduated</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: Student Personal Information -->
        <div class="accordion-item border-0 mb-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secStudent">
                    <i class="fa-solid fa-user text-primary me-2"></i> 2. Student Personal Information
                </button>
            </h2>
            <div id="secStudent" class="accordion-collapse collapse" data-bs-parent="#registrationStepper">
                <div class="accordion-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">First Name *</label>
                            <input type="text" class="form-control" name="first_name" value="<?php echo sanitize($student['first_name'] ?? $_POST['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" value="<?php echo sanitize($student['last_name'] ?? $_POST['last_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Gender *</label>
                            <select class="form-select" name="gender" required>
                                <option value="Male" <?php echo (($student['gender'] ?? $_POST['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (($student['gender'] ?? $_POST['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (($student['gender'] ?? $_POST['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Date of Birth *</label>
                            <input type="date" class="form-control" id="dobInput" name="date_of_birth" value="<?php echo $student['date_of_birth'] ?? $_POST['date_of_birth'] ?? ''; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Age (Auto Calculated)</label>
                            <input type="text" class="form-control bg-light" id="ageInput" readonly value="">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Blood Group</label>
                            <select class="form-select" name="blood_group">
                                <option value="">— Select —</option>
                                <?php foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                    <option value="<?php echo $bg; ?>" <?php echo (($details['blood_group'] ?? $_POST['blood_group'] ?? '') === $bg) ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Religion</label>
                            <input type="text" class="form-control" name="religion" value="<?php echo sanitize($details['religion'] ?? $_POST['religion'] ?? 'Islam'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Nationality</label>
                            <input type="text" class="form-control" name="nationality" value="<?php echo sanitize($details['nationality'] ?? $_POST['nationality'] ?? 'Pakistani'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">B-Form / CNIC Number *</label>
                            <input type="text" class="form-control" name="cnic_no" placeholder="35201-1234567-1" value="<?php echo sanitize($details['cnic_no'] ?? $_POST['cnic_no'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Birth Certificate Number</label>
                            <input type="text" class="form-control" name="birth_certificate_number" value="<?php echo sanitize($details['birth_cert_no'] ?? $_POST['birth_certificate_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Place of Birth</label>
                            <input type="text" class="form-control" name="place_of_birth" value="<?php echo sanitize($details['birth_place'] ?? $_POST['place_of_birth'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Student Mobile</label>
                            <input type="tel" class="form-control" name="student_mobile" value="<?php echo sanitize($details['student_mobile'] ?? $_POST['student_mobile'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Student Email</label>
                            <input type="email" class="form-control" name="student_email" value="<?php echo sanitize($details['student_email'] ?? $_POST['student_email'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 3: Father Information -->
        <div class="accordion-item border-0 mb-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secFather">
                    <i class="fa-solid fa-user-tie text-primary me-2"></i> 3. Father Information
                </button>
            </h2>
            <div id="secFather" class="accordion-collapse collapse" data-bs-parent="#registrationStepper">
                <div class="accordion-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Father Name *</label>
                            <input type="text" class="form-control" name="father_name" value="<?php echo sanitize($details['father_name'] ?? $_POST['father_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Father CNIC *</label>
                            <input type="text" class="form-control" name="father_cnic" placeholder="35201-1234567-1" value="<?php echo sanitize($details['father_cnic'] ?? $_POST['father_cnic'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Father Mobile *</label>
                            <input type="tel" class="form-control" name="father_mobile" value="<?php echo sanitize($details['father_mobile'] ?? $_POST['father_mobile'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Father Occupation</label>
                            <input type="text" class="form-control" name="father_occupation" value="<?php echo sanitize($details['father_occupation'] ?? $_POST['father_occupation'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Monthly Income (Rs.)</label>
                            <input type="number" class="form-control" name="monthly_income" value="<?php echo (float)($details['father_income'] ?? $_POST['monthly_income'] ?? 0.00); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Office Address</label>
                            <textarea class="form-control" name="office_address" rows="2"><?php echo sanitize($details['father_office'] ?? $_POST['office_address'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Father Email</label>
                            <input type="email" class="form-control" name="father_email" value="<?php echo sanitize($details['father_email'] ?? $_POST['father_email'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 4: Mother Information -->
        <div class="accordion-item border-0 mb-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secMother">
                    <i class="fa-solid fa-person-breastfeeding text-primary me-2"></i> 4. Mother Information
                </button>
            </h2>
            <div id="secMother" class="accordion-collapse collapse" data-bs-parent="#registrationStepper">
                <div class="accordion-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Mother Name</label>
                            <input type="text" class="form-control" name="mother_name" value="<?php echo sanitize($details['mother_name'] ?? $_POST['mother_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Mother CNIC</label>
                            <input type="text" class="form-control" name="mother_cnic" placeholder="35201-1234567-1" value="<?php echo sanitize($details['mother_cnic'] ?? $_POST['mother_cnic'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Mother Mobile</label>
                            <input type="tel" class="form-control" name="mother_mobile" value="<?php echo sanitize($details['mother_mobile'] ?? $_POST['mother_mobile'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Occupation</label>
                            <input type="text" class="form-control" name="mother_occupation" value="<?php echo sanitize($details['mother_occupation'] ?? $_POST['mother_occupation'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Mother Email</label>
                            <input type="email" class="form-control" name="mother_email" value="<?php echo sanitize($details['mother_email'] ?? $_POST['mother_email'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 5: Guardian Information -->
        <div class="accordion-item border-0 mb-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secGuardian">
                    <i class="fa-solid fa-user-shield text-primary me-2"></i> 5. Guardian Information
                </button>
            </h2>
            <div id="secGuardian" class="accordion-collapse collapse" data-bs-parent="#registrationStepper">
                <div class="accordion-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Guardian Name *</label>
                            <input type="text" class="form-control" name="guardian_name" value="<?php echo sanitize($student['guardian_name'] ?? $_POST['guardian_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Relationship *</label>
                            <input type="text" class="form-control" name="relationship" placeholder="Father, Uncle, Mother..." value="<?php echo sanitize($details['guardian_relationship'] ?? $_POST['relationship'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Guardian CNIC</label>
                            <input type="text" class="form-control" name="guardian_cnic" placeholder="35201-1234567-1" value="<?php echo sanitize($details['guardian_cnic'] ?? $_POST['guardian_cnic'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Guardian Mobile *</label>
                            <input type="tel" class="form-control" name="guardian_phone" value="<?php echo sanitize($student['guardian_phone'] ?? $_POST['guardian_phone'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Guardian Address</label>
                            <textarea class="form-control" name="guardian_address" rows="2"><?php echo sanitize($details['guardian_address'] ?? $_POST['guardian_address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 6: Address Information -->
        <div class="accordion-item border-0 mb-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secAddress">
                    <i class="fa-solid fa-location-dot text-primary me-2"></i> 6. Address Information
                </button>
            </h2>
            <div id="secAddress" class="accordion-collapse collapse" data-bs-parent="#registrationStepper">
                <div class="accordion-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Current Address *</label>
                            <textarea class="form-control" name="current_address" rows="2" required><?php echo sanitize($details['current_address'] ?? $student['address'] ?? $_POST['current_address'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Permanent Address</label>
                            <textarea class="form-control" name="permanent_address" rows="2"><?php echo sanitize($details['permanent_address'] ?? $_POST['permanent_address'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">City</label>
                            <input type="text" class="form-control" name="city" value="<?php echo sanitize($details['city'] ?? $_POST['city'] ?? 'Lahore'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Province</label>
                            <input type="text" class="form-control" name="province" value="<?php echo sanitize($details['province'] ?? $_POST['province'] ?? 'Punjab'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code" value="<?php echo sanitize($details['postal_code'] ?? $_POST['postal_code'] ?? '54000'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Country</label>
                            <input type="text" class="form-control" name="country" value="<?php echo sanitize($details['country'] ?? $_POST['country'] ?? 'Pakistan'); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 7: Prior Academic History -->
        <div class="accordion-item border-0 mb-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secAcademic">
                    <i class="fa-solid fa-graduation-cap text-primary me-2"></i> 7. Prior Academic History
                </button>
            </h2>
            <div id="secAcademic" class="accordion-collapse collapse" data-bs-parent="#registrationStepper">
                <div class="accordion-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Previous School</label>
                            <input type="text" class="form-control" name="previous_school" value="<?php echo sanitize($details['prev_school'] ?? $_POST['previous_school'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Previous Class</label>
                            <input type="text" class="form-control" name="previous_class" value="<?php echo sanitize($details['prev_class'] ?? $_POST['previous_class'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Previous Roll Number</label>
                            <input type="text" class="form-control" name="previous_roll_number" value="<?php echo sanitize($details['prev_roll_no'] ?? $_POST['previous_roll_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Previous Result</label>
                            <input type="text" class="form-control" name="previous_result" value="<?php echo sanitize($details['prev_result'] ?? $_POST['previous_result'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">School Leaving Certificate Number</label>
                            <input type="text" class="form-control" name="leaving_certificate_number" value="<?php echo sanitize($details['leaving_cert_no'] ?? $_POST['leaving_certificate_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Admission Test Marks (%)</label>
                            <input type="number" class="form-control" name="admission_test_marks" value="<?php echo (float)($details['test_marks'] ?? $_POST['admission_test_marks'] ?? 0.00); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 8: Medical Profile -->
        <div class="accordion-item border-0 mb-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secMedical">
                    <i class="fa-solid fa-heart-pulse text-primary me-2"></i> 8. Medical Profile
                </button>
            </h2>
            <div id="secMedical" class="accordion-collapse collapse" data-bs-parent="#registrationStepper">
                <div class="accordion-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Medical Condition</label>
                            <input type="text" class="form-control" name="medical_condition" value="<?php echo sanitize($details['medical_condition'] ?? $_POST['medical_condition'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Allergies</label>
                            <input type="text" class="form-control" name="allergies" value="<?php echo sanitize($details['allergies'] ?? $_POST['allergies'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Disability</label>
                            <input type="text" class="form-control" name="disability" value="<?php echo sanitize($details['disability'] ?? $_POST['disability'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Emergency Contact Phone</label>
                            <input type="tel" class="form-control" name="emergency_contact" value="<?php echo sanitize($details['emergency_contact'] ?? $_POST['emergency_contact'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Doctor Name</label>
                            <input type="text" class="form-control" name="doctor_name" value="<?php echo sanitize($details['doctor_name'] ?? $_POST['doctor_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Doctor Contact Phone</label>
                            <input type="tel" class="form-control" name="doctor_contact" value="<?php echo sanitize($details['doctor_contact'] ?? $_POST['doctor_contact'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 9: School Transport -->
        <div class="accordion-item border-0 mb-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secTransport">
                    <i class="fa-solid fa-bus text-primary me-2"></i> 9. School Transport
                </button>
            </h2>
            <div id="secTransport" class="accordion-collapse collapse" data-bs-parent="#registrationStepper">
                <div class="accordion-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-12 mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="transport_required" name="transport_required" <?php echo (!empty($details['transport_required']) || !empty($_POST['transport_required'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold text-dark" for="transport_required">Transport Service Required</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Route</label>
                            <input type="text" class="form-control" name="transport_route" value="<?php echo sanitize($details['transport_route'] ?? $_POST['transport_route'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Pickup Point</label>
                            <input type="text" class="form-control" name="pickup_point" value="<?php echo sanitize($details['pickup_point'] ?? $_POST['pickup_point'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Drop Point</label>
                            <input type="text" class="form-control" name="drop_point" value="<?php echo sanitize($details['drop_point'] ?? $_POST['drop_point'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Vehicle Details</label>
                            <input type="text" class="form-control" name="transport_vehicle" value="<?php echo sanitize($details['transport_vehicle'] ?? $_POST['transport_vehicle'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Driver Contact / Name</label>
                            <input type="text" class="form-control" name="transport_driver" value="<?php echo sanitize($details['transport_driver'] ?? $_POST['transport_driver'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 10: Sponsor Details -->
        <div class="accordion-item border-0 mb-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secSponsor">
                    <i class="fa-solid fa-handshake-angle text-primary me-2"></i> 10. Sponsor Details
                </button>
            </h2>
            <div id="secSponsor" class="accordion-collapse collapse" data-bs-parent="#registrationStepper">
                <div class="accordion-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-12 mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="sponsor_required" name="sponsor_required" <?php echo (!empty($details['sponsor_required']) || !empty($_POST['sponsor_required'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold text-dark" for="sponsor_required">Sponsored Registration</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Sponsor Name</label>
                            <input type="text" class="form-control" name="sponsor_name" value="<?php echo sanitize($details['sponsor_name'] ?? $_POST['sponsor_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Organization</label>
                            <input type="text" class="form-control" name="sponsor_org" value="<?php echo sanitize($details['sponsor_org'] ?? $_POST['sponsor_org'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Sponsor Contact</label>
                            <input type="text" class="form-control" name="sponsor_contact" value="<?php echo sanitize($details['sponsor_contact'] ?? $_POST['sponsor_contact'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Sponsor Amount (Rs.)</label>
                            <input type="number" class="form-control" name="sponsor_amount" value="<?php echo (float)($details['sponsor_amount'] ?? $_POST['sponsor_amount'] ?? 0.00); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 11: Document Attachments -->
        <div class="accordion-item border-0 mb-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secDocuments">
                    <i class="fa-solid fa-file-arrow-up text-primary me-2"></i> 11. Document Attachments
                </button>
            </h2>
            <div id="secDocuments" class="accordion-collapse collapse" data-bs-parent="#registrationStepper">
                <div class="accordion-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Student Photograph</label>
                            <input type="file" class="form-control" name="doc_student_photo" accept="image/*" onchange="previewImage(this, 'photoPreview')">
                            <?php if (!empty($details['doc_student_photo'])): ?>
                                <small class="text-success d-block mt-1"><i class="fa-solid fa-circle-check me-1"></i>Photo present (<?php echo basename($details['doc_student_photo']); ?>)</small>
                            <?php endif; ?>
                            <div class="mt-2">
                                <img id="photoPreview" src="<?php echo !empty($details['doc_student_photo']) ? APP_URL . '/' . $details['doc_student_photo'] : 'https://placehold.co/120x150?text=No+Photo'; ?>" class="img-thumbnail" style="max-height: 150px;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Father CNIC Doc</label>
                            <input type="file" class="form-control" name="doc_father_cnic" accept=".jpg,.jpeg,.png,.pdf">
                            <?php if (!empty($details['doc_father_cnic'])): ?>
                                <small class="text-success d-block mt-1"><i class="fa-solid fa-circle-check me-1"></i>Doc present</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Mother CNIC Doc</label>
                            <input type="file" class="form-control" name="doc_mother_cnic" accept=".jpg,.jpeg,.png,.pdf">
                            <?php if (!empty($details['doc_mother_cnic'])): ?>
                                <small class="text-success d-block mt-1"><i class="fa-solid fa-circle-check me-1"></i>Doc present</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">B-Form Doc</label>
                            <input type="file" class="form-control" name="doc_bform" accept=".jpg,.jpeg,.png,.pdf">
                            <?php if (!empty($details['doc_bform'])): ?>
                                <small class="text-success d-block mt-1"><i class="fa-solid fa-circle-check me-1"></i>Doc present</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Birth Certificate Doc</label>
                            <input type="file" class="form-control" name="doc_birth_cert" accept=".jpg,.jpeg,.png,.pdf">
                            <?php if (!empty($details['doc_birth_cert'])): ?>
                                <small class="text-success d-block mt-1"><i class="fa-solid fa-circle-check me-1"></i>Doc present</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Leaving Certificate Doc</label>
                            <input type="file" class="form-control" name="doc_leaving_cert" accept=".jpg,.jpeg,.png,.pdf">
                            <?php if (!empty($details['doc_leaving_cert'])): ?>
                                <small class="text-success d-block mt-1"><i class="fa-solid fa-circle-check me-1"></i>Doc present</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Previous Result Doc</label>
                            <input type="file" class="form-control" name="doc_prev_result" accept=".jpg,.jpeg,.png,.pdf">
                            <?php if (!empty($details['doc_prev_result'])): ?>
                                <small class="text-success d-block mt-1"><i class="fa-solid fa-circle-check me-1"></i>Doc present</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Medical Certificate Doc</label>
                            <input type="file" class="form-control" name="doc_medical_cert" accept=".jpg,.jpeg,.png,.pdf">
                            <?php if (!empty($details['doc_medical_cert'])): ?>
                                <small class="text-success d-block mt-1"><i class="fa-solid fa-circle-check me-1"></i>Doc present</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Other Documents</label>
                            <input type="file" class="form-control" name="doc_other" accept=".jpg,.jpeg,.png,.pdf">
                            <?php if (!empty($details['doc_other'])): ?>
                                <small class="text-success d-block mt-1"><i class="fa-solid fa-circle-check me-1"></i>Doc present</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 12: Fee Configuration -->
        <div class="accordion-item border-0 mb-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secFees">
                    <i class="fa-solid fa-receipt text-primary me-2"></i> 12. Fee Configuration
                </button>
            </h2>
            <div id="secFees" class="accordion-collapse collapse" data-bs-parent="#registrationStepper">
                <div class="accordion-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Fee Plan *</label>
                            <select class="form-select" name="fee_plan" required>
                                <option value="Regular Plan" <?php echo (($details['fee_plan'] ?? $_POST['fee_plan'] ?? '') === 'Regular Plan') ? 'selected' : ''; ?>>Regular Plan</option>
                                <option value="Sibling Discount Plan" <?php echo (($details['fee_plan'] ?? $_POST['fee_plan'] ?? '') === 'Sibling Discount Plan') ? 'selected' : ''; ?>>Sibling Discount Plan</option>
                                <option value="Scholarship Plan" <?php echo (($details['fee_plan'] ?? $_POST['fee_plan'] ?? '') === 'Scholarship Plan') ? 'selected' : ''; ?>>Scholarship Plan</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Admission Fee (Rs.)</label>
                            <input type="number" class="form-control" name="fee_admission" value="<?php echo (float)($details['fee_admission'] ?? $_POST['fee_admission'] ?? 5000.00); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Monthly Tuition Fee (Rs.)</label>
                            <input type="number" class="form-control" name="fee_monthly" value="<?php echo (float)($details['fee_monthly'] ?? $_POST['fee_monthly'] ?? 3000.00); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Discount (Rs.)</label>
                            <input type="number" class="form-control" name="fee_discount" value="<?php echo (float)($details['fee_discount'] ?? $_POST['fee_discount'] ?? 0.00); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Scholarship (Rs.)</label>
                            <input type="number" class="form-control" name="fee_scholarship" value="<?php echo (float)($details['fee_scholarship'] ?? $_POST['fee_scholarship'] ?? 0.00); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Fine Amount</label>
                            <input type="number" class="form-control" name="fee_fine" value="<?php echo (float)($details['fee_fine'] ?? $_POST['fee_fine'] ?? 0.00); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Security Deposit Fee (Rs.)</label>
                            <input type="number" class="form-control" name="fee_security" value="<?php echo (float)($details['fee_security'] ?? $_POST['fee_security'] ?? 0.00); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 13: Remarks & Special Instructions -->
        <div class="accordion-item border-0 mb-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secRemarks">
                    <i class="fa-solid fa-notes-medical text-primary me-2"></i> 13. Remarks & Special Instructions
                </button>
            </h2>
            <div id="secRemarks" class="accordion-collapse collapse" data-bs-parent="#registrationStepper">
                <div class="accordion-body bg-white p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3"><?php echo sanitize($details['remarks'] ?? $_POST['remarks'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Special Notes / Warnings</label>
                            <textarea class="form-control" name="special_notes" rows="3"><?php echo sanitize($details['special_notes'] ?? $_POST['special_notes'] ?? ''); ?></textarea>
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
    // Academic Type toggle controls
    const academicTypeSelect = document.getElementById("academicTypeSelect");
    const classSelect = document.getElementById("classSelect");
    const academyProgramSelect = document.getElementById("academyProgramSelect");
    const academyBatchInput = document.getElementById("academyBatchInput");
    
    const schoolGroupFields = document.querySelectorAll(".school-group-field");
    const academyGroupFields = document.querySelectorAll(".academy-group-field");
    
    function toggleAcademicFields() {
        if (!academicTypeSelect) return;
        const type = academicTypeSelect.value;
        
        if (type === "School") {
            // Show school, hide academy
            schoolGroupFields.forEach(f => f.style.display = "block");
            academyGroupFields.forEach(f => f.style.display = "none");
            
            // Set requirements
            if (classSelect) classSelect.required = true;
            if (academyProgramSelect) academyProgramSelect.required = false;
            if (academyBatchInput) academyBatchInput.required = false;
        } else if (type === "Academy") {
            // Show academy, hide school
            schoolGroupFields.forEach(f => f.style.display = "none");
            academyGroupFields.forEach(f => f.style.display = "block");
            
            // Set requirements
            if (classSelect) classSelect.required = false;
            if (academyProgramSelect) academyProgramSelect.required = true;
            if (academyBatchInput) academyBatchInput.required = true;
        } else if (type === "School + Academy") {
            // Show both
            schoolGroupFields.forEach(f => f.style.display = "block");
            academyGroupFields.forEach(f => f.style.display = "block");
            
            // Set requirements
            if (classSelect) classSelect.required = true;
            if (academyProgramSelect) academyProgramSelect.required = true;
            if (academyBatchInput) academyBatchInput.required = true;
        }
    }
    
    if (academicTypeSelect) {
        academicTypeSelect.addEventListener("change", toggleAcademicFields);
        toggleAcademicFields(); // Initial load check
    }

    // Dynamic Section populator based on Class Selection
    const sectionInput = document.getElementById("sectionInput");
    
    function updateSection() {
        if (!classSelect || !sectionInput) return;
        const selectedOption = classSelect.options[classSelect.selectedIndex];
        const section = selectedOption ? selectedOption.getAttribute('data-section') : '';
        sectionInput.value = section || '';
    }
    
    if (classSelect) {
        classSelect.addEventListener("change", updateSection);
        updateSection(); // initial check
    }

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
                
                // Expand the first accordion card that has invalid fields
                const invalidEl = form.querySelector(':invalid');
                if (invalidEl) {
                    const accordionItem = invalidEl.closest('.accordion-collapse');
                    if (accordionItem) {
                        const bsCollapse = bootstrap.Collapse.getOrCreateInstance(accordionItem);
                        bsCollapse.show();
                    }
                }
                
                alert("Please fill in all required fields indicated in accordion cards.");
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
