<?php
/**
 * Indus Grammar School ERP - Student Profile Dossier
 * Version 3.2.1 (Fix: Undefined Array Keys & Unique Family Query Parameters)
 */

$pageTitle = 'Student Profile Dossier';
$breadcrumbActive = 'Student Registration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

// Input search parameters
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$searchQuery = trim(sanitize($_GET['search_query'] ?? $_GET['search'] ?? ''));
$searchError = null;
$searchNotice = null;
$searchSystemError = null;

// Fetch all active students for fallback quick chooser dropdown
$allStudents = [];
try {
    $allStudents = $db->query("
        SELECT s.id, s.admission_no, s.first_name, s.last_name, d.roll_no, d.cnic_no 
        FROM students s 
        LEFT JOIN student_registration_details d ON s.id = d.student_id 
        WHERE s.status = 'Active' 
        ORDER BY s.first_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch student dropdown list: " . $e->getMessage());
}

// Process search by CNIC / B-Form or Roll Number
if (isset($_GET['search_query']) || isset($_GET['search'])) {
    if (empty($searchQuery) && $selectedId === 0) {
        $searchNotice = "Please enter a CNIC/B-Form number or Roll Number.";
    } elseif (!empty($searchQuery)) {
        $cleanQuery = preg_replace('/[^a-zA-Z0-9]/', '', $searchQuery);
        try {
            // Use unique parameter names for every placeholder to prevent PDO SQLSTATE[HY093]
            $stmtSearch = $db->prepare("
                SELECT s.id 
                FROM students s 
                LEFT JOIN student_registration_details d ON s.id = d.student_id 
                WHERE d.cnic_no = :q_cnic_raw 
                   OR REPLACE(d.cnic_no, '-', '') = :q_cnic_clean
                   OR d.roll_no = :q_roll
                   OR s.admission_no = :q_adm
                   OR s.first_name LIKE :q_fname
                   OR s.last_name LIKE :q_lname
                   OR CONCAT(s.first_name, ' ', s.last_name) LIKE :q_fullname
                ORDER BY s.id DESC 
                LIMIT 1
            ");
            $likeVal = '%' . $searchQuery . '%';
            $stmtSearch->execute([
                'q_cnic_raw' => $searchQuery,
                'q_cnic_clean' => $cleanQuery,
                'q_roll' => $searchQuery,
                'q_adm' => $searchQuery,
                'q_fname' => $likeVal,
                'q_lname' => $likeVal,
                'q_fullname' => $likeVal
            ]);
            $found = $stmtSearch->fetch(PDO::FETCH_ASSOC);

            if ($found && !empty($found['id'])) {
                $selectedId = (int)$found['id'];
            } else {
                $searchError = "No student profile was found for the entered information.";
            }
        } catch (Exception $e) {
            error_log("Student Dossier Search Exception: " . $e->getMessage());
            $searchSystemError = "The student profile could not be loaded because of a system issue. Please try again.";
        }
    }
}

// Data containers
$student = null;
$details = [];
$attendanceLogs = [];
$attendanceStats = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Leave' => 0];
$feeChallans = [];
$feeCollections = [];
$outstandingBalance = 0.00;
$examMarks = [];
$homeworkDiaries = [];
$sponsors = [];
$familySiblings = [];

if ($selectedId > 0) {
    // Core Student Record Retrieval with ALL fields joined from student_registration_details
    try {
        $stmt = $db->prepare("
            SELECT s.*, 
                   c.class_name, c.section,
                   d.roll_no, d.admission_date, d.academic_session, d.campus,
                   d.cnic_no, d.student_mobile, d.student_email,
                   d.father_name, d.father_cnic, d.father_mobile, d.father_occupation,
                   d.mother_name, d.mother_cnic, d.mother_mobile, d.mother_occupation,
                   d.guardian_relationship, d.guardian_cnic, d.guardian_address,
                   d.current_address, d.permanent_address,
                   d.fee_plan, d.fee_admission, d.fee_monthly,
                   d.remarks, d.doc_student_photo,
                   d.blood_group, d.religion, d.nationality,
                   d.prev_school, d.prev_class, d.prev_result, d.leaving_cert_no,
                   d.allergies, d.disability, d.emergency_contact, d.doctor_name,
                   d.transport_required, d.transport_route, d.pickup_point, d.transport_driver,
                   d.doc_father_cnic, d.doc_mother_cnic, d.doc_bform, d.doc_birth_cert,
                   d.doc_leaving_cert, d.doc_prev_result, d.doc_medical_cert, d.doc_other
            FROM students s 
            LEFT JOIN classes c ON s.class_id = c.id 
            LEFT JOIN student_registration_details d ON s.id = d.student_id
            WHERE s.id = ?
        ");
        $stmt->execute([$selectedId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Main Student Query Exception: " . $e->getMessage());
        $searchSystemError = "The student profile could not be loaded because of a system issue. Please try again.";
    }

    if ($student) {
        // Details array mirrors student record to ensure complete key mapping
        $details = $student;

        // Isolated Optional Queries - missing optional data will NOT break student profile rendering
        
        // 1. Attendance Records
        try {
            $stmtAtt = $db->prepare("SELECT date, status, remarks FROM attendance WHERE student_id = ? ORDER BY date DESC LIMIT 30");
            $stmtAtt->execute([$selectedId]);
            $attendanceLogs = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($attendanceLogs as $att) {
                if (isset($attendanceStats[$att['status']])) {
                    $attendanceStats[$att['status']]++;
                }
            }
        } catch (Exception $e) {
            error_log("Attendance Query Exception: " . $e->getMessage());
        }

        // 2. Fee Ledger & Payments
        try {
            $stmtFee = $db->prepare("SELECT * FROM fee_ledger WHERE student_id = ? ORDER BY due_date DESC LIMIT 12");
            $stmtFee->execute([$selectedId]);
            $feeChallans = $stmtFee->fetchAll(PDO::FETCH_ASSOC);

            $stmtColl = $db->prepare("SELECT fp.*, fr.receipt_no FROM fee_payments fp LEFT JOIN fee_receipts fr ON fp.id = fr.payment_id WHERE fp.student_id = ? ORDER BY fp.payment_date DESC LIMIT 12");
            $stmtColl->execute([$selectedId]);
            $feeCollections = $stmtColl->fetchAll(PDO::FETCH_ASSOC);

            $stmtBal = $db->prepare("SELECT COALESCE(SUM(total_payable - paid_amount), 0) FROM fee_ledger WHERE student_id = ? AND status IN ('Pending', 'Partial')");
            $stmtBal->execute([$selectedId]);
            $outstandingBalance = (float)$stmtBal->fetchColumn();
        } catch (Exception $e) {
            error_log("Fee Ledger Query Exception: " . $e->getMessage());
        }

        // 3. Examination & Marks
        try {
            $stmtEx = $db->prepare("
                SELECT m.*, ex.exam_name, sub.subject_name 
                FROM marks m 
                JOIN exams ex ON m.exam_id = ex.id 
                JOIN subjects sub ON m.subject_id = sub.id 
                WHERE m.student_id = ? 
                ORDER BY ex.exam_name ASC, sub.subject_name ASC
            ");
            $stmtEx->execute([$selectedId]);
            $examMarks = $stmtEx->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Exam Marks Query Exception: " . $e->getMessage());
        }

        // 4. Family Siblings (matched by father CNIC or guardian phone) using unique parameter placeholders
        try {
            $fatherCnic = $student['father_cnic'] ?? '';
            $guardianPhone = $student['guardian_phone'] ?? '';
            if (!empty($fatherCnic) || !empty($guardianPhone)) {
                $stmtFam = $db->prepare("
                    SELECT s.id, s.first_name, s.last_name, s.admission_no, s.academic_type, c.class_name, c.section, d.roll_no
                    FROM students s
                    LEFT JOIN classes c ON s.class_id = c.id
                    LEFT JOIN student_registration_details d ON s.id = d.student_id
                    WHERE s.id != :curr_id 
                      AND s.status = 'Active'
                      AND (
                        (:fcnic1 != '' AND d.father_cnic = :fcnic2) OR 
                        (:gphone1 != '' AND (s.guardian_phone = :gphone2 OR d.father_mobile = :gphone3))
                      )
                    ORDER BY s.first_name ASC
                ");
                $stmtFam->execute([
                    'curr_id' => $selectedId,
                    'fcnic1' => $fatherCnic,
                    'fcnic2' => $fatherCnic,
                    'gphone1' => $guardianPhone,
                    'gphone2' => $guardianPhone,
                    'gphone3' => $guardianPhone
                ]);
                $familySiblings = $stmtFam->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Family Siblings Query Exception: " . $e->getMessage());
        }

        // 5. Daily Diaries Homework
        try {
            if (!empty($student['class_id'])) {
                $stmtDiary = $db->prepare("
                    SELECT d.*, sub.subject_name, u.username as teacher_name
                    FROM daily_diaries d
                    JOIN subjects sub ON d.subject_id = sub.id
                    JOIN users u ON d.teacher_id = u.id
                    WHERE d.class_id = ? AND d.is_published = 1
                    ORDER BY d.diary_date DESC LIMIT 10
                ");
                $stmtDiary->execute([$student['class_id']]);
                $homeworkDiaries = $stmtDiary->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Homework Diaries Query Exception: " . $e->getMessage());
        }

        // 6. Sponsors
        try {
            $stmtSpons = $db->prepare("SELECT * FROM student_sponsors WHERE student_id = ?");
            $stmtSpons->execute([$selectedId]);
            $sponsors = $stmtSpons->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Sponsors Query Exception: " . $e->getMessage());
        }
    }
}
?>

<!-- Custom Styling for Premium Student Profile Dossier -->
<style>
.dossier-header-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
}

.dossier-search-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);
}

.dossier-main-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    overflow: hidden;
}

.dossier-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #2563eb 100%);
    color: #ffffff;
    padding: 2rem;
    position: relative;
}

.student-avatar-box {
    width: 120px;
    height: 120px;
    border-radius: 14px;
    border: 4px solid #ffffff;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
    overflow: hidden;
}

.student-avatar-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.dossier-section-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.dossier-section-title {
    color: #1e3a8a;
    font-weight: 700;
    font-size: 1.05rem;
    border-bottom: 2px solid #eff6ff;
    padding-bottom: 0.6rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-label {
    color: #64748b;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 0.2rem;
    display: block;
}

.info-value {
    color: #0f172a;
    font-weight: 700;
    font-size: 0.95rem;
    word-break: break-word;
}

.custom-dossier-table {
    margin-bottom: 0;
}

.custom-dossier-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid #e2e8f0;
}

.custom-dossier-table td {
    padding: 0.7rem 0.85rem;
    font-size: 0.875rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

/* Print Specific Rules */
#student-dossier-print {
    display: none;
}

@media print {
    .d-print-none, #sidebar, header, nav, footer {
        display: none !important;
    }
    body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 10pt;
    }
    .container-fluid {
        padding: 0 !important;
        margin: 0 !important;
    }
    #student-dossier-print {
        display: block !important;
        width: 100% !important;
        background: #ffffff !important;
        color: #000000 !important;
    }
    @page {
        size: A4 portrait;
        margin: 12mm;
    }
    .print-table {
        width: 100% !important;
        border-collapse: collapse !important;
        margin-bottom: 12px;
    }
    .print-table th, .print-table td {
        border: 1px solid #333333 !important;
        padding: 5px 8px !important;
        font-size: 9.5pt !important;
    }
    .print-table th {
        background-color: #f1f5f9 !important;
        color: #000000 !important;
        font-weight: bold;
    }
    .print-section-header {
        font-weight: 800;
        font-size: 10.5pt;
        text-transform: uppercase;
        border-bottom: 2px solid #000;
        padding-bottom: 3px;
        margin-top: 12px;
        margin-bottom: 8px;
    }
}
</style>

<!-- Page Header Banner (Screen Only) -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3 d-print-none">
    <div class="d-flex align-items-center">
        <div class="dossier-header-icon me-3">
            <i class="fa-solid fa-address-card fa-lg"></i>
        </div>
        <div>
            <h3 class="fw-bold text-dark mb-1">Student Profile Dossier</h3>
            <p class="text-muted small mb-0">Search a student by CNIC/B-Form number or Roll Number to view their complete registered profile and generate a professional printable dossier.</p>
        </div>
    </div>
    <?php if ($student): ?>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary px-3 shadow-sm" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Print Profile
            </button>
            <a href="profile_report.php" class="btn btn-sm btn-light border text-secondary px-3">
                <i class="fa-solid fa-rotate-left me-1"></i> Clear Search
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Search Profile Dossier Workspace Panel (Screen Only) -->
<div class="card dossier-search-card p-4 mb-4 d-print-none">
    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-magnifying-glass text-primary me-2"></i>Search Profile Dossier Workspace</h6>
        <span class="badge bg-light text-muted border">Enter CNIC/B-Form or Roll Number</span>
    </div>

    <form method="GET" action="profile_report.php" class="row g-3 align-items-end">
        <div class="col-md-6 col-lg-7">
            <label class="form-label small fw-semibold text-secondary mb-1">Enter Student CNIC/B-Form or Roll Number</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-id-card"></i></span>
                <input type="text" class="form-control" name="search_query" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="e.g. 35201-1234567-1 or Roll No 1024 or Adm No...">
            </div>
        </div>

        <div class="col-md-6 col-lg-5 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary px-4 shadow-sm w-100">
                <i class="fa-solid fa-magnifying-glass me-1"></i> Search Profile
            </button>
            <a href="profile_report.php" class="btn btn-sm btn-light border text-secondary px-3" title="Clear Search">
                <i class="fa-solid fa-xmark me-1"></i> Clear
            </a>
        </div>
    </form>

    <!-- Quick Chooser Fallback Dropdown -->
    <div class="mt-3 pt-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span class="small text-muted"><i class="fa-solid fa-list-ul me-1 text-secondary"></i>Or select directly from registered student list:</span>
        <form method="GET" action="profile_report.php" class="d-inline-block">
            <select class="form-select form-select-sm" name="id" onchange="this.form.submit()" style="min-width: 280px;">
                <option value="">— Select Registered Student File —</option>
                <?php foreach ($allStudents as $st): ?>
                    <option value="<?php echo $st['id']; ?>" <?php echo ($selectedId == $st['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(($st['first_name'] ?? '') . ' ' . ($st['last_name'] ?? '') . ' (Adm: ' . ($st['admission_no'] ?? '-') . ' | Roll: ' . ($st['roll_no'] ?: '-') . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<!-- Validation & Error Messages -->
<?php if ($searchSystemError): ?>
    <div class="alert alert-danger border-danger shadow-sm rounded-4 p-4 mb-4 d-print-none">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-triangle-exclamation fa-2x text-danger me-3"></i>
            <div>
                <h6 class="fw-bold text-dark mb-1">System Issue</h6>
                <p class="mb-0 text-secondary small"><?php echo htmlspecialchars($searchSystemError); ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($searchError): ?>
    <div class="alert alert-warning border-warning shadow-sm rounded-4 p-4 mb-4 d-print-none">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-circle-xmark fa-2x text-warning me-3"></i>
            <div>
                <h6 class="fw-bold text-dark mb-1">Student Record Not Found</h6>
                <p class="mb-0 text-secondary small"><?php echo htmlspecialchars($searchError); ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($searchNotice): ?>
    <div class="alert alert-info border-info shadow-sm rounded-4 p-4 mb-4 d-print-none">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-circle-info fa-2x text-info me-3"></i>
            <div>
                <h6 class="fw-bold text-dark mb-1">Search Notice</h6>
                <p class="mb-0 text-secondary small"><?php echo htmlspecialchars($searchNotice); ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- No Selection Default State -->
<?php if (!$student && !$searchError && !$searchSystemError): ?>
    <div class="card border-0 shadow-sm p-5 text-center bg-white rounded-4 mb-4 d-print-none">
        <div class="mb-3 text-muted">
            <i class="fa-solid fa-address-card fa-4x text-primary opacity-25"></i>
        </div>
        <h5 class="fw-bold text-dark mb-1">No Student Profile Loaded</h5>
        <p class="text-muted small max-width-400 mx-auto mb-3">Enter a student's CNIC/B-Form number or Roll Number in the search bar above to generate their complete profile dossier.</p>
    </div>
<?php endif; ?>

<!-- Render Area: Student Profile Dossier (Screen View) -->
<?php if ($student): ?>
<div class="dossier-main-card mb-4 d-print-none">
    
    <!-- SECTION A: PROFILE HEADER BANNER -->
    <div class="dossier-banner d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="student-avatar-box">
                <?php if (!empty($student['doc_student_photo'])): ?>
                    <img src="<?php echo APP_URL . '/' . htmlspecialchars($student['doc_student_photo']); ?>" alt="Student Photo">
                <?php else: ?>
                    <div class="text-center text-muted p-2">
                        <i class="fa-solid fa-user-graduate fa-3x text-secondary opacity-50 mb-1"></i>
                        <span class="d-block text-xs fw-semibold">No Photo Available</span>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <span class="badge bg-white bg-opacity-20 text-white border border-white border-opacity-20 px-3 py-1 rounded-pill mb-2">
                    <i class="fa-solid fa-graduation-cap me-1"></i><?php echo SCHOOL_NAME; ?>
                </span>
                <h2 class="fw-bold text-white mb-1"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></h2>
                <div class="d-flex flex-wrap align-items-center gap-2 text-white-50 small">
                    <span><i class="fa-solid fa-hashtag me-1"></i>Adm No: <strong class="text-white"><?php echo htmlspecialchars($student['admission_no'] ?? '—'); ?></strong></span>
                    <span>•</span>
                    <span><i class="fa-solid fa-id-badge me-1"></i>Roll No: <strong class="text-white"><?php echo htmlspecialchars($student['roll_no'] ?? '—'); ?></strong></span>
                    <span>•</span>
                    <span><i class="fa-solid fa-layer-group me-1"></i>Class: <strong class="text-white"><?php echo htmlspecialchars(($student['class_name'] ?? $student['school_class'] ?? '-') . ' - ' . ($student['section'] ?? $student['school_section'] ?? 'A')); ?></strong></span>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 align-self-stretch align-self-md-auto">
            <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-20 px-3 py-2 rounded-pill fs-6 fw-semibold align-self-center">
                <i class="fa-solid fa-circle-check me-1"></i><?php echo htmlspecialchars($student['status'] ?? 'Active'); ?>
            </span>
            <span class="badge bg-info bg-opacity-20 text-info border border-info border-opacity-20 px-3 py-2 rounded-pill fs-6 fw-semibold align-self-center">
                <?php echo htmlspecialchars($student['academic_type'] ?? 'School'); ?>
            </span>
        </div>
    </div>

    <!-- Dossier Content Body -->
    <div class="p-4 bg-light">
        
        <!-- Navigation Section Tabs -->
        <ul class="nav nav-pills mb-4 bg-white p-2 rounded-3 border gap-1" id="dossierTabs" role="tablist" style="overflow-x: auto; flex-wrap: nowrap; white-space: nowrap;">
            <li class="nav-item"><button class="nav-link active btn-sm fw-semibold" id="tab-overview" data-bs-toggle="tab" data-bs-target="#pane-overview" type="button"><i class="fa-solid fa-user me-1"></i>Personal & Academic</button></li>
            <li class="nav-item"><button class="nav-link btn-sm fw-semibold" id="tab-parents" data-bs-toggle="tab" data-bs-target="#pane-parents" type="button"><i class="fa-solid fa-users me-1"></i>Parents & Guardian</button></li>
            <li class="nav-item"><button class="nav-link btn-sm fw-semibold" id="tab-contact" data-bs-toggle="tab" data-bs-target="#pane-contact" type="button"><i class="fa-solid fa-phone me-1"></i>Contact & Address</button></li>
            <li class="nav-item"><button class="nav-link btn-sm fw-semibold" id="tab-family" data-bs-toggle="tab" data-bs-target="#pane-family" type="button"><i class="fa-solid fa-people-roof me-1"></i>Family & Siblings</button></li>
            <li class="nav-item"><button class="nav-link btn-sm fw-semibold" id="tab-attendance" data-bs-toggle="tab" data-bs-target="#pane-attendance" type="button"><i class="fa-solid fa-calendar-check me-1"></i>Attendance</button></li>
            <li class="nav-item"><button class="nav-link btn-sm fw-semibold" id="tab-fees" data-bs-toggle="tab" data-bs-target="#pane-fees" type="button"><i class="fa-solid fa-receipt me-1"></i>Fee Ledger</button></li>
            <li class="nav-item"><button class="nav-link btn-sm fw-semibold" id="tab-exams" data-bs-toggle="tab" data-bs-target="#pane-exams" type="button"><i class="fa-solid fa-square-poll-vertical me-1"></i>Exams</button></li>
            <li class="nav-item"><button class="nav-link btn-sm fw-semibold" id="tab-medical" data-bs-toggle="tab" data-bs-target="#pane-medical" type="button"><i class="fa-solid fa-shield-heart me-1"></i>Medical & Transport</button></li>
            <li class="nav-item"><button class="nav-link btn-sm fw-semibold" id="tab-docs" data-bs-toggle="tab" data-bs-target="#pane-docs" type="button"><i class="fa-solid fa-folder-open me-1"></i>Documents & Remarks</button></li>
        </ul>

        <div class="tab-content" id="dossierTabsContent">
            
            <!-- Tab 1: Personal & Academic Overview -->
            <div class="tab-pane fade show active" id="pane-overview" role="tabpanel">
                <div class="row g-3">
                    <!-- SECTION B: PERSONAL INFORMATION -->
                    <div class="col-12 col-lg-6">
                        <div class="dossier-section-card h-100">
                            <div class="dossier-section-title">
                                <i class="fa-solid fa-user-circle text-primary"></i>Personal Information
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <span class="info-label">Full Name</span>
                                    <span class="info-value"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Gender</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['gender'] ?? '—'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Date of Birth</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['date_of_birth'] ?? '—'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">CNIC / B-Form No</span>
                                    <span class="info-value text-primary font-monospace"><?php echo htmlspecialchars($student['cnic_no'] ?? '—'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Blood Group</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['blood_group'] ?? '—'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Religion</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['religion'] ?? 'Islam'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Nationality</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['nationality'] ?? 'Pakistani'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Birth Certificate No</span>
                                    <span class="info-value"><?php echo htmlspecialchars(!empty($student['doc_birth_cert']) ? 'Available' : '—'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION C: ACADEMIC INFORMATION -->
                    <div class="col-12 col-lg-6">
                        <div class="dossier-section-card h-100">
                            <div class="dossier-section-title">
                                <i class="fa-solid fa-graduation-cap text-primary"></i>Academic Information
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <span class="info-label">Admission Number</span>
                                    <span class="info-value text-primary font-monospace"><?php echo htmlspecialchars($student['admission_no'] ?? '—'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Roll Number</span>
                                    <span class="info-value font-monospace"><?php echo htmlspecialchars($student['roll_no'] ?? '—'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Academic Type</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['academic_type'] ?? 'School'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Campus Location</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['campus'] ?? 'Main Campus'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Class & Section</span>
                                    <span class="info-value"><?php echo htmlspecialchars(($student['class_name'] ?? $student['school_class'] ?? '-') . ' - ' . ($student['section'] ?? $student['school_section'] ?? 'A')); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Academic Session</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['academic_session'] ?? date('Y')); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Admission Date</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['enrollment_date'] ?? ($student['admission_date'] ?? '—')); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Previous School</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['prev_school'] ?? '—'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Parents & Guardian Information -->
            <div class="tab-pane fade" id="pane-parents" role="tabpanel">
                <!-- SECTION D: PARENT / GUARDIAN INFORMATION -->
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <div class="dossier-section-card h-100">
                            <div class="dossier-section-title">
                                <i class="fa-solid fa-user-tie text-primary"></i>Father Information
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <span class="info-label">Father's Name</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['father_name'] ?? '—'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Father's CNIC</span>
                                    <span class="info-value font-monospace"><?php echo htmlspecialchars($student['father_cnic'] ?? '—'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Father Mobile</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['father_mobile'] ?? '—'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Father Occupation</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['father_occupation'] ?? '—'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="dossier-section-card h-100">
                            <div class="dossier-section-title">
                                <i class="fa-solid fa-person-breastfeeding text-primary"></i>Mother & Guardian Details
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <span class="info-label">Mother's Name</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['mother_name'] ?? '—'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Mother's CNIC</span>
                                    <span class="info-value font-monospace"><?php echo htmlspecialchars($student['mother_cnic'] ?? '—'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Guardian Name</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['guardian_name'] ?? '—'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Guardian Relationship</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['guardian_relationship'] ?? 'Father'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Contact and Address Information -->
            <div class="tab-pane fade" id="pane-contact" role="tabpanel">
                <!-- SECTION E: CONTACT AND ADDRESS INFORMATION -->
                <div class="dossier-section-card">
                    <div class="dossier-section-title">
                        <i class="fa-solid fa-location-dot text-primary"></i>Contact & Address Coordinates
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <span class="info-label">Primary Guardian Phone</span>
                            <span class="info-value text-primary"><i class="fa-solid fa-phone me-1"></i><?php echo htmlspecialchars($student['guardian_phone'] ?? ($student['father_mobile'] ?? '—')); ?></span>
                        </div>
                        <div class="col-md-4">
                            <span class="info-label">Student Mobile No</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['student_mobile'] ?? '—'); ?></span>
                        </div>
                        <div class="col-md-4">
                            <span class="info-label">Emergency Contact</span>
                            <span class="info-value text-danger"><i class="fa-solid fa-truck-medical me-1"></i><?php echo htmlspecialchars($student['emergency_contact'] ?? '—'); ?></span>
                        </div>
                        <div class="col-md-6">
                            <span class="info-label">Current Residential Address</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['current_address'] ?? '—'); ?></span>
                        </div>
                        <div class="col-md-6">
                            <span class="info-label">Permanent Address</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['permanent_address'] ?? '—'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Family & Siblings Information -->
            <div class="tab-pane fade" id="pane-family" role="tabpanel">
                <!-- SECTION F: FAMILY INFORMATION -->
                <div class="dossier-section-card">
                    <div class="dossier-section-title">
                        <i class="fa-solid fa-people-roof text-primary"></i>Verified Family & Sibling Details
                    </div>
                    <?php if (empty($familySiblings)): ?>
                        <p class="text-muted small mb-0">No additional verified sibling records are linked to this family in the system database.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table custom-dossier-table align-middle">
                                <thead>
                                    <tr>
                                        <th>Sibling Name</th>
                                        <th>Admission No</th>
                                        <th>Roll No</th>
                                        <th>Class & Section</th>
                                        <th>Academic Type</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($familySiblings as $sib): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars(($sib['first_name'] ?? '') . ' ' . ($sib['last_name'] ?? '')); ?></strong></td>
                                            <td><code class="text-primary"><?php echo htmlspecialchars($sib['admission_no'] ?? '—'); ?></code></td>
                                            <td><?php echo htmlspecialchars($sib['roll_no'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars(($sib['class_name'] ?? '-') . ' ' . ($sib['section'] ?? '')); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($sib['academic_type'] ?? 'School'); ?></span></td>
                                            <td>
                                                <a href="profile_report.php?id=<?php echo $sib['id']; ?>" class="btn btn-xs btn-outline-primary py-1 px-2">
                                                    <i class="fa-solid fa-arrow-right me-1"></i>View Profile
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab 5: Attendance Information -->
            <div class="tab-pane fade" id="pane-attendance" role="tabpanel">
                <!-- SECTION G: ATTENDANCE INFORMATION -->
                <div class="dossier-section-card">
                    <div class="dossier-section-title">
                        <i class="fa-solid fa-calendar-days text-primary"></i>Attendance Ledger Summary
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-3">
                            <div class="p-3 border rounded-3 bg-success bg-opacity-10 text-center">
                                <span class="info-label text-success">Present Days</span>
                                <span class="fs-4 fw-bold text-success"><?php echo $attendanceStats['Present']; ?></span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 border rounded-3 bg-danger bg-opacity-10 text-center">
                                <span class="info-label text-danger">Absent Days</span>
                                <span class="fs-4 fw-bold text-danger"><?php echo $attendanceStats['Absent']; ?></span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 border rounded-3 bg-warning bg-opacity-10 text-center">
                                <span class="info-label text-warning">Leave Days</span>
                                <span class="fs-4 fw-bold text-warning"><?php echo $attendanceStats['Leave']; ?></span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 border rounded-3 bg-secondary bg-opacity-10 text-center">
                                <span class="info-label text-secondary">Late Days</span>
                                <span class="fs-4 fw-bold text-secondary"><?php echo $attendanceStats['Late']; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table custom-dossier-table">
                            <thead>
                                <tr><th>Date</th><th>Status</th><th>Remarks</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($attendanceLogs)): ?>
                                    <tr><td colspan="3" class="text-center text-muted">Attendance information is not available.</td></tr>
                                <?php else: foreach ($attendanceLogs as $att): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($att['date'] ?? '—'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo (($att['status'] ?? '') === 'Present') ? 'success' : ((($att['status'] ?? '') === 'Absent') ? 'danger' : 'warning'); ?>-subtle text-<?php echo (($att['status'] ?? '') === 'Present') ? 'success' : ((($att['status'] ?? '') === 'Absent') ? 'danger' : 'warning'); ?> rounded-pill">
                                                <?php echo htmlspecialchars($att['status'] ?? '—'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($att['remarks'] ?? '—'); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 6: Fee Information -->
            <div class="tab-pane fade" id="pane-fees" role="tabpanel">
                <!-- SECTION H: FEE INFORMATION -->
                <div class="dossier-section-card">
                    <div class="dossier-section-title">
                        <i class="fa-solid fa-money-bill-wave text-primary"></i>Fee & Financial Ledger Summary
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="p-3 border rounded-3 bg-white">
                                <span class="info-label">Monthly Tuition Fee</span>
                                <span class="fs-5 fw-bold text-dark">Rs. <?php echo number_format((float)($student['fee_monthly'] ?? 3000), 2); ?></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 border rounded-3 bg-white">
                                <span class="info-label">Admission Fee</span>
                                <span class="fs-5 fw-bold text-dark">Rs. <?php echo number_format((float)($student['fee_admission'] ?? 5000), 2); ?></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 border rounded-3 bg-danger bg-opacity-10">
                                <span class="info-label text-danger">Outstanding Dues Balance</span>
                                <span class="fs-5 fw-bold text-danger">Rs. <?php echo number_format($outstandingBalance, 2); ?></span>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark mb-2">Recent Monthly Fee Ledger Statements</h6>
                    <div class="table-responsive mb-4">
                        <table class="table custom-dossier-table">
                            <thead>
                                <tr><th>Challan ID</th><th>Month</th><th>Payable Amount</th><th>Status</th><th>Due Date</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($feeChallans)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">Fee ledger records are not available.</td></tr>
                                <?php else: foreach ($feeChallans as $ch): ?>
                                    <tr>
                                        <td><code>#<?php echo str_pad($ch['id'], 5, '0', STR_PAD_LEFT); ?></code></td>
                                        <td><?php echo htmlspecialchars($ch['month'] ?? '—'); ?></td>
                                        <td>Rs. <?php echo number_format((float)($ch['total_payable'] ?? 0), 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo (($ch['status'] ?? '') === 'Paid') ? 'success' : 'danger'; ?>-subtle text-<?php echo (($ch['status'] ?? '') === 'Paid') ? 'success' : 'danger'; ?> rounded-pill">
                                                <?php echo htmlspecialchars($ch['status'] ?? 'Pending'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($ch['due_date'] ?? '—'); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 7: Examination / Result Information -->
            <div class="tab-pane fade" id="pane-exams" role="tabpanel">
                <!-- SECTION I: EXAMINATION / RESULT INFORMATION -->
                <div class="dossier-section-card">
                    <div class="dossier-section-title">
                        <i class="fa-solid fa-list-check text-primary"></i>Examination Results & Marks Summary
                    </div>
                    <div class="table-responsive">
                        <table class="table custom-dossier-table">
                            <thead>
                                <tr><th>Exam Name</th><th>Subject</th><th>Obtained Marks</th><th>Total Marks</th><th>Remarks</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($examMarks)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">Examination result records are not available for this student.</td></tr>
                                <?php else: foreach ($examMarks as $m): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($m['exam_name'] ?? '—'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($m['subject_name'] ?? '—'); ?></td>
                                        <td><strong class="text-primary"><?php echo (float)($m['marks_obtained'] ?? 0); ?></strong></td>
                                        <td><?php echo (int)($m['total_marks'] ?? 100); ?></td>
                                        <td><?php echo htmlspecialchars($m['remarks'] ?? '—'); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 8: Medical & Transport Information -->
            <div class="tab-pane fade" id="pane-medical" role="tabpanel">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <div class="dossier-section-card h-100">
                            <div class="dossier-section-title">
                                <i class="fa-solid fa-heart-pulse text-primary"></i>Medical Profile
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <span class="info-label">Allergies</span>
                                    <span class="info-value text-danger"><?php echo htmlspecialchars($student['allergies'] ?? 'None recorded'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Physical Disability</span>
                                    <span class="info-value text-danger"><?php echo htmlspecialchars($student['disability'] ?? 'None recorded'); ?></span>
                                </div>
                                <div class="col-12">
                                    <span class="info-label">Family Doctor / Clinic</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['doctor_name'] ?? '—'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="dossier-section-card h-100">
                            <div class="dossier-section-title">
                                <i class="fa-solid fa-bus text-primary"></i>Transport Details
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <span class="info-label">Transport Service</span>
                                    <span class="info-value"><?php echo (!empty($student['transport_required'])) ? 'Subscribed' : 'Not Subscribed'; ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Route Details</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['transport_route'] ?? '—'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Pickup Point</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['pickup_point'] ?? '—'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="info-label">Driver Name</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['transport_driver'] ?? '—'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 9: Documents & Remarks -->
            <div class="tab-pane fade" id="pane-docs" role="tabpanel">
                <!-- SECTION J: DOCUMENTS AND ADDITIONAL INFORMATION -->
                <div class="dossier-section-card mb-3">
                    <div class="dossier-section-title">
                        <i class="fa-solid fa-folder-open text-primary"></i>Uploaded Verification Documents
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php
                        $docs = [
                            'doc_father_cnic' => 'Father CNIC Card',
                            'doc_mother_cnic' => 'Mother CNIC Card',
                            'doc_bform' => 'B-Form Copy',
                            'doc_birth_cert' => 'Birth Certificate',
                            'doc_leaving_cert' => 'School Leaving Cert',
                            'doc_prev_result' => 'Previous Result',
                            'doc_medical_cert' => 'Medical Certificate',
                            'doc_other' => 'Other Attachment'
                        ];
                        $hasDocs = false;
                        foreach ($docs as $key => $label) {
                            if (!empty($student[$key])) {
                                $hasDocs = true;
                                echo '<a href="' . APP_URL . '/' . htmlspecialchars($student[$key]) . '" target="_blank" class="btn btn-sm btn-outline-primary px-3 rounded-pill"><i class="fa-solid fa-file-arrow-down me-1"></i>' . $label . '</a>';
                            }
                        }
                        if (!$hasDocs) {
                            echo '<p class="text-muted small mb-0">No official document files attached to this student record.</p>';
                        }
                        ?>
                    </div>
                </div>

                <div class="dossier-section-card">
                    <div class="dossier-section-title">
                        <i class="fa-solid fa-clipboard text-primary"></i>Registration Remarks & Special Notes
                    </div>
                    <p class="mb-0 text-dark font-monospace bg-light p-3 rounded-3 border"><?php echo htmlspecialchars($student['remarks'] ?? 'No special registration remarks logged for this student file.'); ?></p>
                </div>
            </div>

        </div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================================= -->
<!-- PRINT-READY DOSSIER CONTAINER (VISIBLE ONLY IN PRINT)     -->
<!-- ========================================================= -->
<?php if ($student): ?>
<div id="student-dossier-print">
    <!-- Official Print Header -->
    <div style="border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="font-weight: 800; margin: 0; font-size: 1.6rem; color: #000; font-family: 'Outfit', Arial, sans-serif;"><?php echo SCHOOL_NAME; ?></h2>
            <h4 style="margin: 2px 0 0 0; font-size: 1.1rem; color: #333;">STUDENT PROFILE DOSSIER</h4>
            <span style="font-size: 8.5pt; color: #555;"><?php echo SCHOOL_ADDRESS; ?></span>
        </div>
        <div style="text-align: right;">
            <div style="width: 90px; height: 90px; border: 2px solid #000; border-radius: 6px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-left: auto;">
                <?php if (!empty($student['doc_student_photo'])): ?>
                    <img src="<?php echo APP_URL . '/' . htmlspecialchars($student['doc_student_photo']); ?>" style="max-width: 100%; max-height: 100%;">
                <?php else: ?>
                    <span style="font-size: 8pt; color: #666; text-align: center;">No Photo Available</span>
                <?php endif; ?>
            </div>
            <span style="font-size: 7.5pt; color: #666; display: block; margin-top: 3px;">Printed: <?php echo date('d-M-Y h:i A'); ?></span>
        </div>
    </div>

    <!-- 1. Metadata Summary Table -->
    <table class="print-table">
        <tr>
            <th width="18%">Student Name:</th>
            <td width="32%"><strong><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></strong></td>
            <th width="18%">Admission No:</th>
            <td width="32%"><strong><?php echo htmlspecialchars($student['admission_no'] ?? '—'); ?></strong></td>
        </tr>
        <tr>
            <th>Roll Number:</th>
            <td><?php echo htmlspecialchars($student['roll_no'] ?? '—'); ?></td>
            <th>Academic Type:</th>
            <td><?php echo htmlspecialchars($student['academic_type'] ?? 'School'); ?></td>
        </tr>
        <tr>
            <th>Class & Section:</th>
            <td><?php echo htmlspecialchars(($student['class_name'] ?? $student['school_class'] ?? '-') . ' - ' . ($student['section'] ?? $student['school_section'] ?? 'A')); ?></td>
            <th>Campus Location:</th>
            <td><?php echo htmlspecialchars($student['campus'] ?? 'Main Campus'); ?></td>
        </tr>
        <tr>
            <th>Academic Session:</th>
            <td><?php echo htmlspecialchars($student['academic_session'] ?? date('Y')); ?></td>
            <th>Student Status:</th>
            <td><strong><?php echo htmlspecialchars($student['status'] ?? 'Active'); ?></strong></td>
        </tr>
    </table>

    <!-- 2. Personal Information -->
    <div class="print-section-header">1. Personal Information</div>
    <table class="print-table">
        <tr>
            <th width="20%">Gender:</th>
            <td width="30%"><?php echo htmlspecialchars($student['gender'] ?? '—'); ?></td>
            <th width="20%">Date of Birth:</th>
            <td width="30%"><?php echo htmlspecialchars($student['date_of_birth'] ?? '—'); ?></td>
        </tr>
        <tr>
            <th>CNIC / B-Form No:</th>
            <td><strong><?php echo htmlspecialchars($student['cnic_no'] ?? '—'); ?></strong></td>
            <th>Blood Group:</th>
            <td><?php echo htmlspecialchars($student['blood_group'] ?? '—'); ?></td>
        </tr>
        <tr>
            <th>Religion:</th>
            <td><?php echo htmlspecialchars($student['religion'] ?? 'Islam'); ?></td>
            <th>Nationality:</th>
            <td><?php echo htmlspecialchars($student['nationality'] ?? 'Pakistani'); ?></td>
        </tr>
    </table>

    <!-- 3. Parent & Guardian Details -->
    <div class="print-section-header">2. Parent & Guardian Coordinates</div>
    <table class="print-table">
        <tr>
            <th width="20%">Father Name:</th>
            <td width="30%"><?php echo htmlspecialchars($student['father_name'] ?? '—'); ?></td>
            <th width="20%">Father CNIC:</th>
            <td width="30%"><?php echo htmlspecialchars($student['father_cnic'] ?? '—'); ?></td>
        </tr>
        <tr>
            <th>Father Mobile:</th>
            <td><?php echo htmlspecialchars($student['father_mobile'] ?? '—'); ?></td>
            <th>Father Occupation:</th>
            <td><?php echo htmlspecialchars($student['father_occupation'] ?? '—'); ?></td>
        </tr>
        <tr>
            <th>Mother Name:</th>
            <td><?php echo htmlspecialchars($student['mother_name'] ?? '—'); ?></td>
            <th>Mother CNIC:</th>
            <td><?php echo htmlspecialchars($student['mother_cnic'] ?? '—'); ?></td>
        </tr>
        <tr>
            <th>Guardian Name:</th>
            <td><?php echo htmlspecialchars($student['guardian_name'] ?? '—'); ?></td>
            <th>Relationship:</th>
            <td><?php echo htmlspecialchars($student['guardian_relationship'] ?? 'Father'); ?></td>
        </tr>
    </table>

    <!-- 4. Contact & Address Information -->
    <div class="print-section-header">3. Contact & Address Details</div>
    <table class="print-table">
        <tr>
            <th width="20%">Guardian Phone:</th>
            <td width="30%"><?php echo htmlspecialchars($student['guardian_phone'] ?? ($student['father_mobile'] ?? '—')); ?></td>
            <th width="20%">Emergency Contact:</th>
            <td width="30%"><?php echo htmlspecialchars($student['emergency_contact'] ?? '—'); ?></td>
        </tr>
        <tr>
            <th>Current Address:</th>
            <td colspan="3"><?php echo htmlspecialchars($student['current_address'] ?? '—'); ?></td>
        </tr>
        <tr>
            <th>Permanent Address:</th>
            <td colspan="3"><?php echo htmlspecialchars($student['permanent_address'] ?? '—'); ?></td>
        </tr>
    </table>

    <!-- 5. Financial Summary -->
    <div class="print-section-header">4. Fee & Financial Ledger Summary</div>
    <table class="print-table">
        <tr>
            <th width="20%">Monthly Tuition Fee:</th>
            <td width="30%">Rs. <?php echo number_format((float)($student['fee_monthly'] ?? 3000), 2); ?></td>
            <th width="20%">Outstanding Dues:</th>
            <td width="30%"><strong>Rs. <?php echo number_format($outstandingBalance, 2); ?></strong></td>
        </tr>
    </table>

    <!-- 6. Remarks & Special Instructions -->
    <div class="print-section-header">5. Remarks & Notes</div>
    <div style="border: 1px solid #333; padding: 6px 10px; font-size: 9pt; min-height: 40px; margin-bottom: 25px;">
        <?php echo htmlspecialchars($student['remarks'] ?? 'No official remarks logged for this student record.'); ?>
    </div>

    <!-- Official Signatures & Stamp Area -->
    <div style="margin-top: 30px; display: flex; justify-content: space-between; align-items: flex-end; font-size: 9pt;">
        <div style="text-align: center; border-top: 1px solid #000; width: 180px; padding-top: 4px;">
            <strong>Parent / Guardian Signature</strong>
        </div>
        <div style="border: 1px dashed #666; width: 110px; height: 60px; display: flex; align-items: center; justify-content: center; font-size: 7pt; color: #666;">
            OFFICE STAMP AREA
        </div>
        <div style="text-align: center; border-top: 1px solid #000; width: 180px; padding-top: 4px;">
            <strong>Principal Signature</strong>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
