<?php
/**
 * Indus Grammar School ERP - Student Profile Report (Tabbed dossier)
 * Version 2.0.0
 */

$pageTitle = 'Student Profile Report';
$breadcrumbActive = 'Student Registration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$students = [];
try {
    $students = $db->query("SELECT id, admission_no, first_name, last_name FROM students ORDER BY admission_no DESC")->fetchAll();
} catch (Exception $e) {}

$student = null;
$details = null;

// Dynamic categories arrays
$attendanceLogs = [];
$attendanceStats = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Leave' => 0];
$feeChallans = [];
$feeCollections = [];
$examMarks = [];
$homeworkDiaries = [];
$complaints = [];
$sponsors = [];

if ($selectedId > 0) {
    try {
        $stmt = $db->prepare("SELECT s.*, c.class_name, c.section FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
        $stmt->execute([$selectedId]);
        $student = $stmt->fetch();
        
        if ($student) {
            $stmtDet = $db->prepare("SELECT * FROM student_registration_details WHERE student_id = ?");
            $stmtDet->execute([$selectedId]);
            $details = $stmtDet->fetch();

            // 1. Related Attendance
            $stmtAtt = $db->prepare("SELECT date, status, remarks FROM attendance WHERE student_id = ? ORDER BY date DESC LIMIT 30");
            $stmtAtt->execute([$selectedId]);
            $attendanceLogs = $stmtAtt->fetchAll();
            foreach ($attendanceLogs as $att) {
                if (isset($attendanceStats[$att['status']])) {
                    $attendanceStats[$att['status']]++;
                }
            }

            // 2. Related Fees
            $stmtFee = $db->prepare("SELECT * FROM fee_ledger WHERE student_id = ? ORDER BY due_date DESC");
            $stmtFee->execute([$selectedId]);
            $feeChallans = $stmtFee->fetchAll();

            $stmtColl = $db->prepare("SELECT fp.*, fr.receipt_no FROM fee_payments fp LEFT JOIN fee_receipts fr ON fp.id = fr.payment_id WHERE fp.student_id = ? ORDER BY fp.payment_date DESC");
            $stmtColl->execute([$selectedId]);
            $feeCollections = $stmtColl->fetchAll();

            // 3. Related Exams
            $stmtEx = $db->prepare("
                SELECT m.*, ex.exam_name, sub.subject_name 
                FROM marks m 
                JOIN exams ex ON m.exam_id = ex.id 
                JOIN subjects sub ON m.subject_id = sub.id 
                WHERE m.student_id = ? 
                ORDER BY ex.exam_name ASC, sub.subject_name ASC
            ");
            $stmtEx->execute([$selectedId]);
            $examMarks = $stmtEx->fetchAll();

            // 4. Related Homework Diaries
            $stmtDiary = $db->prepare("
                SELECT d.*, sub.subject_name, u.username as teacher_name
                FROM daily_diaries d
                JOIN subjects sub ON d.subject_id = sub.id
                JOIN users u ON d.teacher_id = u.id
                WHERE d.class_id = ? AND d.is_published = 1
                ORDER BY d.diary_date DESC LIMIT 15
            ");
            $stmtDiary->execute([$student['class_id']]);
            $homeworkDiaries = $stmtDiary->fetchAll();

            // 5. Related Complaints
            $stmtComp = $db->prepare("
                SELECT sc.*, u.username as assigned_username 
                FROM student_complaints sc 
                LEFT JOIN users u ON sc.assigned_to = u.id 
                WHERE sc.student_id = ? 
                ORDER BY sc.complaint_date DESC
            ");
            $stmtComp->execute([$selectedId]);
            $complaints = $stmtComp->fetchAll();

            // 6. Related Sponsors
            $stmtSpons = $db->prepare("SELECT * FROM student_sponsors WHERE student_id = ?");
            $stmtSpons->execute([$selectedId]);
            $sponsors = $stmtSpons->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Load student profile data links error: " . $e->getMessage());
    }
}
?>

<!-- Header -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-address-card me-2 text-primary"></i>Student Profile Report</h3>
    </div>
</div>

<!-- Select Panel -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h5 class="fw-bold text-dark mb-0">Search Profile Dossier</h5>
            <p class="text-muted small mb-0">Choose a student file to render the complete academic profile dossier.</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <form method="GET" class="d-inline-block">
                <select class="form-select" name="id" onchange="this.form.submit()" style="min-width: 300px; border-radius: 8px;">
                    <option value="">— Choose Student Profile —</option>
                    <?php foreach ($students as $st): ?>
                        <option value="<?php echo $st['id']; ?>" <?php echo ($selectedId == $st['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($st['admission_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
</div>

<!-- Render Area -->
<?php if ($student): ?>
<div class="card border border-light shadow-sm bg-white p-4" style="border-radius:12px;" id="printableDossier">
    
    <!-- Top summary panel banner -->
    <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom pb-4 mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="border rounded bg-light p-2" style="width: 110px; height: 110px; display: flex; align-items: center; justify-content: center;">
                <?php if (!empty($details['doc_student_photo'])): ?>
                    <img src="<?php echo APP_URL . '/' . $details['doc_student_photo']; ?>" class="img-fluid rounded" alt="Student Photo" style="max-height: 100%;">
                <?php else: ?>
                    <i class="fa-solid fa-user-graduate fs-1 text-muted"></i>
                <?php endif; ?>
            </div>
            <div>
                <h3 class="fw-bold text-dark mb-1"><?php echo sanitize($student['first_name'] . ' ' . $student['last_name']); ?></h3>
                <span class="badge bg-secondary px-3 rounded-pill">Type: <?php echo sanitize($student['academic_type'] ?? 'School'); ?></span>
                <span class="badge bg-info px-3 rounded-pill">Class: <?php echo sanitize(($student['class_name'] ?? $student['school_class'] ?? '-') . ' - ' . ($student['section'] ?? $student['school_section'] ?? 'A')); ?></span>
            </div>
        </div>
        <div class="mt-3 mt-md-0 d-print-none">
            <button class="btn btn-outline-secondary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Profile Dossier</button>
            <button class="btn btn-primary px-3 ms-1" onclick="window.print()"><i class="fa-solid fa-file-pdf me-2"></i>Export PDF</button>
        </div>
    </div>

    <!-- 12 Tabs Nav Menu list (Horizontal Scrollable on mobile) -->
    <ul class="nav nav-tabs border-bottom mb-4 d-print-none" id="profileTabs" role="tablist" style="overflow-x: auto; flex-wrap: nowrap; white-space: nowrap;">
        <li class="nav-item"><button class="nav-link active fw-semibold" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personalPane" type="button">Personal</button></li>
        <li class="nav-item"><button class="nav-link fw-semibold" id="parents-tab" data-bs-toggle="tab" data-bs-target="#parentsPane" type="button">Parents</button></li>
        <li class="nav-item"><button class="nav-link fw-semibold" id="academic-tab" data-bs-toggle="tab" data-bs-target="#academicPane" type="button">Academic</button></li>
        <li class="nav-item"><button class="nav-link fw-semibold" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendancePane" type="button">Attendance</button></li>
        <li class="nav-item"><button class="nav-link fw-semibold" id="fee-tab" data-bs-toggle="tab" data-bs-target="#feePane" type="button">Fees</button></li>
        <li class="nav-item"><button class="nav-link fw-semibold" id="exam-tab" data-bs-toggle="tab" data-bs-target="#examPane" type="button">Examination</button></li>
        <li class="nav-item"><button class="nav-link fw-semibold" id="medical-tab" data-bs-toggle="tab" data-bs-target="#medicalPane" type="button">Medical</button></li>
        <li class="nav-item"><button class="nav-link fw-semibold" id="transport-tab" data-bs-toggle="tab" data-bs-target="#transportPane" type="button">Transport</button></li>
        <li class="nav-item"><button class="nav-link fw-semibold" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documentsPane" type="button">Documents</button></li>
        <li class="nav-item"><button class="nav-link fw-semibold" id="diary-tab" data-bs-toggle="tab" data-bs-target="#diaryPane" type="button">Diary</button></li>
        <li class="nav-item"><button class="nav-link fw-semibold" id="complaints-tab" data-bs-toggle="tab" data-bs-target="#complaintsPane" type="button">Complaints</button></li>
        <li class="nav-item"><button class="nav-link fw-semibold" id="sponsor-tab" data-bs-toggle="tab" data-bs-target="#sponsorPane" type="button">Sponsor</button></li>
    </ul>

    <!-- 12 Tab Panels -->
    <div class="tab-content" id="profileTabsContent">
        
        <!-- Tab 1: Personal -->
        <div class="tab-pane fade show active" id="personalPane" role="tabpanel">
            <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-user me-2 text-primary"></i>Personal Information</h5>
            <div class="row g-3">
                <div class="col-md-4"><span class="text-muted small d-block">Gender</span><strong class="text-dark"><?php echo sanitize($student['gender']); ?></strong></div>
                <div class="col-md-4"><span class="text-muted small d-block">Date of Birth</span><strong class="text-dark"><?php echo sanitize($student['date_of_birth']); ?></strong></div>
                <div class="col-md-4"><span class="text-muted small d-block">Blood Group</span><strong class="text-dark"><?php echo sanitize($details['blood_group'] ?: '-'); ?></strong></div>
                <div class="col-md-4"><span class="text-muted small d-block">Religion</span><strong class="text-dark"><?php echo sanitize($details['religion'] ?: 'Islam'); ?></strong></div>
                <div class="col-md-4"><span class="text-muted small d-block">Nationality</span><strong class="text-dark"><?php echo sanitize($details['nationality'] ?: 'Pakistani'); ?></strong></div>
                <div class="col-md-4"><span class="text-muted small d-block">CNIC / B-Form</span><strong class="text-dark"><?php echo sanitize($details['cnic_no'] ?: '-'); ?></strong></div>
                <div class="col-12"><span class="text-muted small d-block">Current Address</span><strong class="text-dark"><?php echo sanitize($details['current_address'] ?: '-'); ?></strong></div>
                <div class="col-12"><span class="text-muted small d-block">Permanent Address</span><strong class="text-dark"><?php echo sanitize($details['permanent_address'] ?: '-'); ?></strong></div>
            </div>
        </div>

        <!-- Tab 2: Parents -->
        <div class="tab-pane fade" id="parentsPane" role="tabpanel">
            <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-user-group me-2 text-primary"></i>Parental Coordinates</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="fw-bold text-dark border-bottom pb-2">Father Information</h6>
                    <p class="mb-1 text-muted small">Name: <strong class="text-dark"><?php echo sanitize($details['father_name'] ?: '-'); ?></strong></p>
                    <p class="mb-1 text-muted small">CNIC: <strong class="text-dark"><?php echo sanitize($details['father_cnic'] ?: '-'); ?></strong></p>
                    <p class="mb-1 text-muted small">Mobile: <strong class="text-dark"><?php echo sanitize($details['father_mobile'] ?: '-'); ?></strong></p>
                    <p class="mb-1 text-muted small">Occupation: <strong class="text-dark"><?php echo sanitize($details['father_occupation'] ?: '-'); ?></strong></p>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold text-dark border-bottom pb-2">Mother Information</h6>
                    <p class="mb-1 text-muted small">Name: <strong class="text-dark"><?php echo sanitize($details['mother_name'] ?: '-'); ?></strong></p>
                    <p class="mb-1 text-muted small">CNIC: <strong class="text-dark"><?php echo sanitize($details['mother_cnic'] ?: '-'); ?></strong></p>
                    <p class="mb-1 text-muted small">Mobile: <strong class="text-dark"><?php echo sanitize($details['mother_mobile'] ?: '-'); ?></strong></p>
                    <p class="mb-1 text-muted small">Occupation: <strong class="text-dark"><?php echo sanitize($details['mother_occupation'] ?: '-'); ?></strong></p>
                </div>
            </div>
        </div>

        <!-- Tab 3: Academic -->
        <div class="tab-pane fade" id="academicPane" role="tabpanel">
            <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-graduation-cap me-2 text-primary"></i>Academic Profile</h5>
            <div class="row g-3">
                <div class="col-md-4"><span class="text-muted small d-block">Academic Type</span><strong class="text-dark"><?php echo sanitize($student['academic_type'] ?? 'School'); ?></strong></div>
                <div class="col-md-4"><span class="text-muted small d-block">Class & Section</span><strong class="text-dark"><?php echo sanitize(($student['class_name'] ?? $student['school_class'] ?? '-') . ' - ' . ($student['section'] ?? $student['school_section'] ?? 'A')); ?></strong></div>
                <div class="col-md-4"><span class="text-muted small d-block">Previous School class</span><strong class="text-dark"><?php echo sanitize($details['prev_school'] ?: '-'); ?> (Class: <?php echo sanitize($details['prev_class'] ?: '-'); ?>)</strong></div>
                <div class="col-md-4"><span class="text-muted small d-block">Result / Obtained marks</span><strong class="text-dark"><?php echo sanitize($details['prev_result'] ?: '-'); ?></strong></div>
                <div class="col-md-4"><span class="text-muted small d-block">Leaving Certificate No</span><strong class="text-dark"><?php echo sanitize($details['leaving_cert_no'] ?: '-'); ?></strong></div>
                <div class="col-md-4"><span class="text-muted small d-block">Active Session</span><strong class="text-dark"><?php echo sanitize($details['academic_session'] ?: '-'); ?></strong></div>
            </div>
        </div>

        <!-- Tab 4: Attendance -->
        <div class="tab-pane fade" id="attendancePane" role="tabpanel">
            <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-calendar-check me-2 text-primary"></i>Attendance Ledger</h5>
            <div class="row mb-3 g-2">
                <div class="col-6 col-md-3"><div class="p-2 border rounded text-center bg-success-soft">Present: <strong class="d-block fs-5"><?php echo $attendanceStats['Present']; ?></strong></div></div>
                <div class="col-6 col-md-3"><div class="p-2 border rounded text-center bg-danger-soft">Absent: <strong class="d-block fs-5"><?php echo $attendanceStats['Absent']; ?></strong></div></div>
                <div class="col-6 col-md-3"><div class="p-2 border rounded text-center bg-warning-soft">Leave: <strong class="d-block fs-5"><?php echo $attendanceStats['Leave']; ?></strong></div></div>
                <div class="col-6 col-md-3"><div class="p-2 border rounded text-center bg-secondary-soft">Late: <strong class="d-block fs-5"><?php echo $attendanceStats['Late']; ?></strong></div></div>
            </div>
            <div class="table-responsive">
                <table class="table custom-table table-hover table-sm">
                    <thead><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead>
                    <tbody>
                        <?php if (empty($attendanceLogs)): ?>
                            <tr><td colspan="3" class="text-center text-muted">No attendance logs recorded.</td></tr>
                        <?php else: foreach ($attendanceLogs as $att): ?>
                            <tr>
                                <td><?php echo sanitize($att['date']); ?></td>
                                <td><span class="badge bg-<?php echo ($att['status'] === 'Present') ? 'success' : (($att['status'] === 'Absent') ? 'danger' : 'warning'); ?>-soft"><?php echo sanitize($att['status']); ?></span></td>
                                <td><?php echo sanitize($att['remarks'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 5: Fee -->
        <div class="tab-pane fade" id="feePane" role="tabpanel">
            <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-receipt me-2 text-primary"></i>Financial Statements</h5>
            <div class="table-responsive mb-4">
                <h6 class="fw-bold text-dark mb-2">Generated Fee Challans</h6>
                <table class="table custom-table table-hover table-sm">
                    <thead><tr><th>Challan No</th><th>Month</th><th>Amount</th><th>Status</th><th>Due Date</th></tr></thead>
                    <tbody>
                        <?php if (empty($feeChallans)): ?>
                            <tr><td colspan="5" class="text-center text-muted">No monthly fee ledger sheets generated.</td></tr>
                        <?php else: foreach ($feeChallans as $challan): ?>
                            <tr>
                                <td>#<?php echo str_pad($challan['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo sanitize($challan['month']); ?></td>
                                <td>Rs. <?php echo number_format($challan['total_payable'], 2); ?></td>
                                <td><span class="badge bg-<?php echo ($challan['status'] === 'Paid') ? 'success' : 'danger'; ?>-soft"><?php echo sanitize($challan['status']); ?></span></td>
                                <td><?php echo sanitize($challan['due_date']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-responsive">
                <h6 class="fw-bold text-dark mb-2">Payment Collections History</h6>
                <table class="table custom-table table-hover table-sm">
                    <thead><tr><th>Receipt No</th><th>Paid Amount</th><th>Payment Date</th><th>Method</th></tr></thead>
                    <tbody>
                        <?php if (empty($feeCollections)): ?>
                            <tr><td colspan="4" class="text-center text-muted">No receipt logs recorded.</td></tr>
                        <?php else: foreach ($feeCollections as $coll): ?>
                            <tr>
                                <td><?php echo sanitize($coll['receipt_no']); ?></td>
                                <td>Rs. <?php echo number_format($coll['amount_paid'], 2); ?></td>
                                <td><?php echo sanitize($coll['payment_date']); ?></td>
                                <td><?php echo sanitize($coll['payment_method']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 6: Examination -->
        <div class="tab-pane fade" id="examPane" role="tabpanel">
            <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-list-check me-2 text-primary"></i>Exam Marks Ledger</h5>
            <div class="table-responsive">
                <table class="table custom-table table-hover table-sm">
                    <thead><tr><th>Exam Name</th><th>Subject</th><th>Obtained Marks</th><th>Total</th><th>Remarks</th></tr></thead>
                    <tbody>
                        <?php if (empty($examMarks)): ?>
                            <tr><td colspan="5" class="text-center text-muted">No exam scores recorded for this student file.</td></tr>
                        <?php else: foreach ($examMarks as $marks): ?>
                            <tr>
                                <td><?php echo sanitize($marks['exam_name']); ?></td>
                                <td><?php echo sanitize($marks['subject_name']); ?></td>
                                <td class="fw-bold"><?php echo (float)$marks['marks_obtained']; ?></td>
                                <td><?php echo (int)$marks['total_marks']; ?></td>
                                <td><?php echo sanitize($marks['remarks'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 7: Medical -->
        <div class="tab-pane fade" id="medicalPane" role="tabpanel">
            <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-heart-pulse me-2 text-primary"></i>Medical Information</h5>
            <div class="row g-3">
                <div class="col-md-6"><span class="text-muted small d-block">Allergies</span><strong class="text-danger"><?php echo sanitize($details['allergies'] ?: 'None recorded'); ?></strong></div>
                <div class="col-md-6"><span class="text-muted small d-block">Physical Disabilities</span><strong class="text-danger"><?php echo sanitize($details['disability'] ?: 'None recorded'); ?></strong></div>
                <div class="col-md-6"><span class="text-muted small d-block">Emergency Contact Person</span><strong class="text-dark"><?php echo sanitize($details['emergency_contact'] ?: '-'); ?></strong></div>
                <div class="col-md-6"><span class="text-muted small d-block">Family Doctor Details</span><strong class="text-dark"><?php echo sanitize($details['doctor_name'] ?: '-'); ?></strong></div>
            </div>
        </div>

        <!-- Tab 8: Transport -->
        <div class="tab-pane fade" id="transportPane" role="tabpanel">
            <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-bus me-2 text-primary"></i>Transport Configuration</h5>
            <div class="row g-3">
                <div class="col-md-6"><span class="text-muted small d-block">Transport Service required</span><strong class="text-dark"><?php echo (!empty($details['transport_required'])) ? 'Yes' : 'No'; ?></strong></div>
                <div class="col-md-6"><span class="text-muted small d-block">Route Details</span><strong class="text-dark"><?php echo sanitize($details['transport_route'] ?: '-'); ?></strong></div>
                <div class="col-md-6"><span class="text-muted small d-block">Pickup & Drop point</span><strong class="text-dark"><?php echo sanitize($details['pickup_point'] ?: '-'); ?></strong></div>
                <div class="col-md-6"><span class="text-muted small d-block">Assigned Driver Name</span><strong class="text-dark"><?php echo sanitize($details['transport_driver'] ?: '-'); ?></strong></div>
            </div>
        </div>

        <!-- Tab 9: Documents -->
        <div class="tab-pane fade" id="documentsPane" role="tabpanel">
            <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-file-arrow-down me-2 text-primary"></i>Uploaded Document files</h5>
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
                        echo '<a href="' . APP_URL . '/' . $details[$key] . '" target="_blank" class="btn btn-outline-primary"><i class="fa-solid fa-cloud-arrow-down me-1"></i>' . $label . '</a>';
                    }
                }
                if (!$hasDocs) {
                    echo '<p class="text-muted small">No profile document files uploaded.</p>';
                }
                ?>
            </div>
        </div>

        <!-- Tab 10: Diary -->
        <div class="tab-pane fade" id="diaryPane" role="tabpanel">
            <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-book-open me-2 text-primary"></i>Daily Diary Homework (Class: <?php echo sanitize($student['class_name'] . ' - ' . $student['section']); ?>)</h5>
            <div class="table-responsive">
                <table class="table custom-table table-hover table-sm">
                    <thead><tr><th>Date</th><th>Subject</th><th>Title & Instructions</th><th>Published By</th></tr></thead>
                    <tbody>
                        <?php if (empty($homeworkDiaries)): ?>
                            <tr><td colspan="4" class="text-center text-muted">No published homework diaries active for this class.</td></tr>
                        <?php else: foreach ($homeworkDiaries as $diary): ?>
                            <tr>
                                <td><?php echo sanitize($diary['diary_date']); ?></td>
                                <td><?php echo sanitize($diary['subject_name']); ?></td>
                                <td><strong><?php echo sanitize($diary['title']); ?></strong><br><small><?php echo sanitize($diary['description']); ?></small></td>
                                <td><?php echo sanitize($diary['teacher_name']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 11: Complaints -->
        <div class="tab-pane fade" id="complaintsPane" role="tabpanel">
            <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-face-frown me-2 text-primary"></i>Registered Complaint logs</h5>
            <div class="table-responsive">
                <table class="table custom-table table-hover table-sm">
                    <thead><tr><th>Date</th><th>Category</th><th>Title & Details</th><th>Resolution details</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if (empty($complaints)): ?>
                            <tr><td colspan="5" class="text-center text-muted">No complaint logs recorded.</td></tr>
                        <?php else: foreach ($complaints as $comp): ?>
                            <tr>
                                <td><?php echo sanitize($comp['complaint_date']); ?></td>
                                <td><?php echo sanitize($comp['category']); ?></td>
                                <td><strong><?php echo sanitize($comp['title']); ?></strong><br><small class="text-muted"><?php echo sanitize($comp['description']); ?></small></td>
                                <td><?php echo sanitize($comp['resolution'] ?: 'Under investigation'); ?></td>
                                <td><span class="badge bg-<?php echo ($comp['status'] === 'Resolved' || $comp['status'] === 'Closed') ? 'success' : 'warning'; ?>-soft"><?php echo sanitize($comp['status']); ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 12: Sponsor -->
        <div class="tab-pane fade" id="sponsorPane" role="tabpanel">
            <h5 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-handshake-angle me-2 text-primary"></i>Sponsor & Scholarship Details</h5>
            <div class="table-responsive">
                <table class="table custom-table table-hover table-sm">
                    <thead><tr><th>Sponsor Name</th><th>Organization</th><th>Amount (Rs.)</th><th>Duration</th><th>Remarks</th></tr></thead>
                    <tbody>
                        <?php if (empty($sponsors)): ?>
                            <tr><td colspan="5" class="text-center text-muted">No charity sponsorships linked.</td></tr>
                        <?php else: foreach ($sponsors as $spons): ?>
                            <tr>
                                <td class="fw-bold"><?php echo sanitize($spons['sponsor_name']); ?></td>
                                <td><?php echo sanitize($spons['organization'] ?: '-'); ?></td>
                                <td class="fw-bold text-success">Rs. <?php echo number_format($spons['amount'], 2); ?></td>
                                <td><?php echo sanitize($spons['duration'] ?: '-'); ?></td>
                                <td><?php echo sanitize($spons['remarks'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
<?php else: ?>
    <div class="text-center py-5 bg-white shadow-sm border border-light" style="border-radius:12px;">
        <i class="fa-solid fa-address-card fs-1 text-muted opacity-50 mb-3 d-block"></i>
        <h5 class="text-muted">Choose a student profile above to load dynamic tabs.</h5>
    </div>
<?php endif; ?>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
