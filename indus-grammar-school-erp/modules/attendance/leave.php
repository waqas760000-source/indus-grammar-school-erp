<?php
/**
 * Indus Grammar School ERP - Student Leave Management Submodule
 * Version 3.0.0
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('attendance_view');

$db = Database::getConnection();
$message = '';
$error = '';

// Load flash messages
if (!empty($_SESSION['flash_success'])) {
    $message = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// 2. Load Lookups (Students and classes)
$studentsList = [];
try {
    $studentsList = $db->query("
        SELECT s.id, s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section,
               c.class_name, c.section, d.father_name, s.class_id
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        WHERE s.status = 'Active'
        ORDER BY s.admission_no ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Check if a specific leave ID is loaded for editing/updating
$leaveId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$leave = null;

if ($leaveId > 0) {
    try {
        $stmt = $db->prepare("
            SELECT la.*, s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section,
                   c.class_name, c.section
            FROM leave_applications la
            JOIN students s ON la.applicant_id = s.id AND la.applicant_type = 'student'
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE la.id = ?
        ");
        $stmt->execute([$leaveId]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$leave) {
            $error = "Leave application not found.";
            $leaveId = 0;
        }
    } catch (Exception $e) {
        $error = "Error loading leave application: " . $e->getMessage();
        $leaveId = 0;
    }
}

// Helper: Sync leave dates with attendance table when approved
function syncApprovedLeaveToAttendance($db, $studentId, $startDate, $endDate, $leaveId) {
    try {
        // Retrieve student's class placement
        $stmtS = $db->prepare("SELECT class_id FROM students WHERE id = ?");
        $stmtS->execute([$studentId]);
        $classId = $stmtS->fetchColumn();
        if (!$classId) return;

        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $end->modify('+1 day'); // Include end date in interval loop
        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($start, $interval ,$end);

        $markedBy = $_SESSION['user_id'] ?? null;
        
        $stmtInsert = $db->prepare("
            INSERT INTO attendance (student_id, class_id, date, status, remarks, marked_by)
            VALUES (:student_id, :class_id, :date, 'Leave', :remarks, :marked_by)
            ON DUPLICATE KEY UPDATE status = 'Leave', remarks = VALUES(remarks), marked_by = VALUES(marked_by)
        ");

        foreach ($daterange as $date) {
            $dateStr = $date->format("Y-m-d");
            $stmtInsert->execute([
                'student_id' => $studentId,
                'class_id'   => $classId,
                'date'       => $dateStr,
                'remarks'    => "Approved Leave App ID: " . $leaveId,
                'marked_by'  => $markedBy
            ]);
        }
    } catch (Exception $e) {
        error_log("Failed to sync approved leave to attendance: " . $e->getMessage());
    }
}

// 4. Handle POST actions (Save / Update / Approve / Reject / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');

    // SAVE OR UPDATE APPLICATION
    if ($action === 'save' || $action === 'update') {
        try {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $start_date = sanitize($_POST['start_date'] ?? '');
            $end_date = sanitize($_POST['end_date'] ?? '');
            $reason = sanitize($_POST['reason'] ?? '');
            $status = sanitize($_POST['status'] ?? 'Pending');
            $remarks = sanitize($_POST['remarks'] ?? '');
            $leave_type = sanitize($_POST['leave_type'] ?? 'Casual');

            if ($student_id <= 0 || empty($start_date) || empty($end_date) || empty($reason)) {
                throw new Exception("Please select student, dates, and provide a reason.");
            }

            $date1 = new DateTime($start_date);
            $date2 = new DateTime($end_date);
            if ($date2 < $date1) {
                throw new Exception("Leave end date cannot be before the start date.");
            }
            $total_days = $date1->diff($date2)->days + 1;

            // Handle optional attachment upload (doc, pdf, images)
            $attachmentPath = $leave['attachment'] ?? null;
            if (!empty($_FILES['attachment']['name'])) {
                $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) {
                    throw new Exception("Invalid file extension. Allowed: PDF, Word (DOC/DOCX), Images.");
                }
                if ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
                    throw new Exception("File size limit exceeded. Max is 5MB.");
                }

                if (!empty($leave['attachment']) && file_exists(__DIR__ . '/../../' . $leave['attachment'])) {
                    unlink(__DIR__ . '/../../' . $leave['attachment']);
                }

                $targetDir = __DIR__ . '/../../uploads/leaves/';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                $filename = 'leave_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetDir . $filename)) {
                    $attachmentPath = 'uploads/leaves/' . $filename;
                } else {
                    throw new Exception("Failed to upload attachment file.");
                }
            }

            if ($action === 'save') {
                $stmt = $db->prepare("
                    INSERT INTO leave_applications (
                        applicant_type, applicant_id, leave_type, start_date, end_date, reason, status, total_days, attachment, remarks
                    ) VALUES (
                        'student', :student_id, :leave_type, :start_date, :end_date, :reason, :status, :total_days, :attachment, :remarks
                    )
                ");
                $stmt->execute([
                    'student_id' => $student_id,
                    'leave_type' => $leave_type,
                    'start_date' => $start_date,
                    'end_date'   => $end_date,
                    'reason'     => $reason,
                    'status'     => $status,
                    'total_days' => $total_days,
                    'attachment' => $attachmentPath,
                    'remarks'    => $remarks
                ]);
                $targetId = $db->lastInsertId();
                $logAction = "Leave Applied";
                $logDesc = "Leave application generated ID $targetId for Student $student_id";
            } else {
                $targetId = (int)$_POST['leave_id'];
                if ($targetId <= 0) throw new Exception("Invalid leave ID.");

                $stmt = $db->prepare("
                    UPDATE leave_applications SET 
                        applicant_id = :student_id, leave_type = :leave_type, start_date = :start_date,
                        end_date = :end_date, reason = :reason, status = :status, total_days = :total_days,
                        attachment = :attachment, remarks = :remarks, updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    'student_id' => $student_id,
                    'leave_type' => $leave_type,
                    'start_date' => $start_date,
                    'end_date'   => $end_date,
                    'reason'     => $reason,
                    'status'     => $status,
                    'total_days' => $total_days,
                    'attachment' => $attachmentPath,
                    'remarks'    => $remarks,
                    'id'         => $targetId
                ]);
                $logAction = "Leave Updated";
                $logDesc = "Leave application ID $targetId updated for Student $student_id";
            }

            // Sync with attendance if Approved
            if ($status === 'Approved') {
                syncApprovedLeaveToAttendance($db, $student_id, $start_date, $end_date, $targetId);
            }

            // Audit log
            $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$_SESSION['user_id'] ?? null, $logAction, $logDesc, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

            $_SESSION['flash_success'] = "Leave application saved successfully!";
            if (isset($_POST['save_and_new'])) {
                header("Location: leave.php#create-tab");
            } else {
                header("Location: leave.php");
            }
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // APPROVE OR REJECT ACTION DIRECT FROM GRID / MODAL
    if ($action === 'approve' || $action === 'reject') {
        try {
            $targetId = (int)($_POST['leave_id'] ?? 0);
            if ($targetId <= 0) throw new Exception("Invalid leave ID.");
            $newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';

            $stmtInfo = $db->prepare("SELECT applicant_id, start_date, end_date FROM leave_applications WHERE id = ?");
            $stmtInfo->execute([$targetId]);
            $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

            if ($info) {
                $stmtUp = $db->prepare("UPDATE leave_applications SET status = ?, approved_by = ?, updated_at = NOW() WHERE id = ?");
                $stmtUp->execute([$newStatus, $_SESSION['user_id'] ?? null, $targetId]);

                if ($newStatus === 'Approved') {
                    syncApprovedLeaveToAttendance($db, $info['applicant_id'], $info['start_date'], $info['end_date'], $targetId);
                }

                // Audit log
                $logDesc = "Leave ID $targetId was marked as $newStatus";
                $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
                $stmtLog->execute([$_SESSION['user_id'] ?? null, "Leave $newStatus", $logDesc, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

                $_SESSION['flash_success'] = "Leave application $newStatus successfully.";
            }
            header("Location: leave.php");
            exit;
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Error updating leave: " . $e->getMessage();
            header("Location: leave.php");
            exit;
        }
    }

    // DELETE ACTION
    if ($action === 'delete') {
        try {
            $targetId = (int)($_POST['leave_id'] ?? 0);
            if ($targetId <= 0) throw new Exception("Invalid leave ID reference.");

            // Unlink attachment
            $stmtFile = $db->prepare("SELECT attachment FROM leave_applications WHERE id = ?");
            $stmtFile->execute([$targetId]);
            $file = $stmtFile->fetchColumn();
            if ($file && file_exists(__DIR__ . '/../../' . $file)) {
                unlink(__DIR__ . '/../../' . $file);
            }

            $stmtDel = $db->prepare("DELETE FROM leave_applications WHERE id = ?");
            $stmtDel->execute([$targetId]);

            // Audit log
            $logDesc = "Deleted Leave Application ID $targetId";
            $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$_SESSION['user_id'] ?? null, 'Leave Deleted', $logDesc, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

            $_SESSION['flash_success'] = "Leave application deleted successfully.";
            header("Location: leave.php");
            exit;
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Error deleting leave: " . $e->getMessage();
            header("Location: leave.php");
            exit;
        }
    }
}

// 5. Retrieve leaves list matching filters
$filter_date = sanitize($_GET['filter_date'] ?? '');
$filter_class = sanitize($_GET['filter_class'] ?? '');
$filter_section = sanitize($_GET['filter_section'] ?? '');
$filter_status = sanitize($_GET['filter_status'] ?? '');

$limit = 10;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$where = " WHERE la.applicant_type = 'student'";
$params = [];

if ($filter_date !== '') {
    $where .= " AND :date BETWEEN la.start_date AND la.end_date";
    $params['date'] = $filter_date;
}
if ($filter_class !== '') {
    $where .= " AND (c.class_name = :class OR s.school_class = :class)";
    $params['class'] = $filter_class;
}
if ($filter_section !== '') {
    $where .= " AND (c.section = :section OR s.school_section = :section)";
    $params['section'] = $filter_section;
}
if ($filter_status !== '') {
    $where .= " AND la.status = :status";
    $params['status'] = $filter_status;
}

$leaves = [];
$totalEntries = 0;

try {
    // Count query
    $stmtCount = $db->prepare("
        SELECT COUNT(*) 
        FROM leave_applications la
        JOIN students s ON la.applicant_id = s.id AND la.applicant_type = 'student'
        LEFT JOIN classes c ON s.class_id = c.id
        $where
    ");
    $stmtCount->execute($params);
    $totalEntries = (int)$stmtCount->fetchColumn();

    // Data query
    $stmtData = $db->prepare("
        SELECT la.*, s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section,
               c.class_name, c.section, d.doc_student_photo
        FROM leave_applications la
        JOIN students s ON la.applicant_id = s.id AND la.applicant_type = 'student'
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY la.created_at DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmtData->bindValue($k, $v);
    }
    $stmtData->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();

    $leaves = $stmtData->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Leave applications query error: " . $e->getMessage());
}

$totalPages = ceil($totalEntries / $limit);
if ($totalPages < 1) $totalPages = 1;

// Load unique dropdown placement listings
$classesList = [];
try {
    $classesList = $db->query("SELECT DISTINCT class_name FROM classes ORDER BY class_name ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Include Header
$pageTitle = ($leaveId > 0) ? 'Modify Leave Application' : 'Student Leave Management';
$breadcrumbActive = 'Leave Management';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Title Banner -->
<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-umbrella-beach me-2 text-primary"></i><?php echo ($leaveId > 0) ? 'Modify Leave application' : 'Student Leave Management'; ?></h3>
    </div>
</div>

<!-- Alerts -->
<?php if (!empty($message)): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4" style="border-radius: 12px;">
        <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius: 12px;">
        <i class="fa-solid fa-circle-xmark me-2"></i><?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if ($leaveId > 0 && $leave): ?>
    <!-- 5. EDIT FORM STATE -->
    <div class="card border-0 shadow-sm bg-white p-4" style="border-radius:12px;">
        <h5 class="fw-bold text-secondary mb-4 border-bottom pb-3"><i class="fa-solid fa-pen me-2 text-primary"></i>Modify Leave Application Details</h5>
        <form id="leaveForm" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted">Select Student (Admission No) *</label>
                    <select class="form-select" name="student_id" id="studentSelect" onchange="autoFillStudentDetails(this)" required>
                        <option value="" data-name="" data-class="" data-section="">— Select Student —</option>
                        <?php foreach ($studentsList as $st): ?>
                            <option value="<?php echo $st['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?>"
                                    data-class="<?php echo htmlspecialchars($st['class_name'] ?? $st['school_class'] ?? '—'); ?>"
                                    data-section="<?php echo htmlspecialchars($st['section'] ?? $st['school_section'] ?? 'A'); ?>"
                                    <?php echo ($leave['applicant_id'] == $st['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($st['admission_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted">Leave Type *</label>
                    <select class="form-select" name="leave_type" required>
                        <option value="Casual" <?php echo ($leave['leave_type'] === 'Casual') ? 'selected' : ''; ?>>Casual</option>
                        <option value="Medical" <?php echo ($leave['leave_type'] === 'Medical') ? 'selected' : ''; ?>>Medical</option>
                        <option value="Sabbatical" <?php echo ($leave['leave_type'] === 'Sabbatical') ? 'selected' : ''; ?>>Sabbatical</option>
                        <option value="Other" <?php echo ($leave['leave_type'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <!-- Readonly student data -->
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">Student Name</label>
                    <input type="text" class="form-control bg-light" id="studentNameInput" readonly value="—">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">Class</label>
                    <input type="text" class="form-control bg-light" id="classInput" readonly value="—">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">Section</label>
                    <input type="text" class="form-control bg-light" id="sectionInput" readonly value="—">
                </div>

                <!-- Date ranges -->
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">Leave From *</label>
                    <input type="date" class="form-control" name="start_date" id="leaveFromInput" value="<?php echo $leave['start_date']; ?>" onchange="calculateTotalDays()" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">Leave To *</label>
                    <input type="date" class="form-control" name="end_date" id="leaveToInput" value="<?php echo $leave['end_date']; ?>" onchange="calculateTotalDays()" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">Total Days</label>
                    <input type="text" class="form-control bg-light font-monospace" id="totalDaysInput" readonly value="<?php echo $leave['total_days'] ?? 0; ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted">Leave Status *</label>
                    <select class="form-select" name="status" required>
                        <option value="Pending" <?php echo ($leave['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo ($leave['status'] === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo ($leave['status'] === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted">Attachment File (Optional)</label>
                    <?php if (!empty($leave['attachment'])): ?>
                        <div class="mb-2 p-2 border rounded bg-light d-flex align-items-center justify-content-between">
                            <span class="small text-secondary"><i class="fa-solid fa-file-pdf text-primary me-2"></i>Current: <strong><?php echo basename($leave['attachment']); ?></strong></span>
                            <a href="<?php echo APP_URL . '/' . $leave['attachment']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">Download</a>
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold text-muted">Reason *</label>
                    <textarea class="form-control" name="reason" rows="3" required placeholder="State descriptive reason for student leave application..."><?php echo htmlspecialchars($leave['reason']); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold text-muted">Remarks / Action Taken Notes</label>
                    <textarea class="form-control" name="remarks" rows="2" placeholder="Administrative remarks..."><?php echo htmlspecialchars($leave['remarks']); ?></textarea>
                </div>

                <div class="col-12 text-end border-top pt-3 mt-4">
                    <button type="submit" id="saveBtn" class="btn btn-primary px-4"><i class="fa-solid fa-circle-check me-2"></i>Update Application</button>
                    <a href="leave.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>

    <script>
    function autoFillStudentDetails(select) {
        const selectedOption = select.options[select.selectedIndex];
        document.getElementById("studentNameInput").value = selectedOption.getAttribute("data-name") || "—";
        document.getElementById("classInput").value = selectedOption.getAttribute("data-class") || "—";
        document.getElementById("sectionInput").value = selectedOption.getAttribute("data-section") || "—";
    }

    function calculateTotalDays() {
        const fromVal = document.getElementById("leaveFromInput").value;
        const toVal = document.getElementById("leaveToInput").value;
        if (fromVal && toVal) {
            const start = new Date(fromVal);
            const end = new Date(toVal);
            if (end >= start) {
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                document.getElementById("totalDaysInput").value = diffDays;
            } else {
                document.getElementById("totalDaysInput").value = 0;
            }
        } else {
            document.getElementById("totalDaysInput").value = 0;
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        autoFillStudentDetails(document.getElementById("studentSelect"));
    });
    </script>

<?php else: ?>
    <!-- 6. REPORT GRID & LIST STATE -->
    
    <!-- Tabbed navigation -->
    <div class="card border border-light shadow-sm bg-white mb-4 d-print-none" style="border-radius: 12px;">
        <div class="card-body p-2">
            <ul class="nav nav-pills nav-fill" id="leaveTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="list-tab-btn" data-bs-toggle="pill" data-bs-target="#list-pane" type="button" role="tab"><i class="fa-solid fa-list me-2"></i>Leave Applications & Reports</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="create-tab-btn" data-bs-toggle="pill" data-bs-target="#create-pane" type="button" role="tab"><i class="fa-solid fa-plus-circle me-2"></i>Log Leave Application</button>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tabs contents -->
    <div class="tab-content">
        
        <!-- TAB 1: REPORTS & LIST -->
        <div class="tab-pane fade show active" id="list-pane" role="tabpanel">
            
            <!-- Filters Card -->
            <div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
                <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Leave Records</h6>
                <form method="GET" action="leave.php" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Leave Date (Within Range)</label>
                        <input type="date" class="form-control form-control-sm" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Class</label>
                        <select class="form-select form-select-sm" name="filter_class">
                            <option value="">All Classes</option>
                            <?php foreach ($classesList as $cls): ?>
                                <option value="<?php echo $cls; ?>" <?php echo ($filter_class === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Section</label>
                        <select class="form-select form-select-sm" name="filter_section">
                            <option value="">All Sections</option>
                            <?php foreach ($sectionsList as $sec): ?>
                                <option value="<?php echo $sec; ?>" <?php echo ($filter_section === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Status</label>
                        <select class="form-select form-select-sm" name="filter_status">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo ($filter_status === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo ($filter_status === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo ($filter_status === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-sm btn-primary px-4"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
                        <a href="leave.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Leaves Table -->
            <div class="custom-table-card shadow-sm border-0 mb-4 bg-white" style="border-radius:12px; overflow:hidden;">
                <div class="table-responsive">
                    <table class="table custom-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Admission No</th>
                                <th>Student Name</th>
                                <th>Class & Sec</th>
                                <th>Leave From</th>
                                <th>Leave To</th>
                                <th>Total Days</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th class="text-end d-print-none">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leaves)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No student leave records found.</td>
                                </tr>
                            <?php else: foreach ($leaves as $row): ?>
                                <tr>
                                    <td><strong class="text-primary font-monospace"><?php echo sanitize($row['admission_no']); ?></strong></td>
                                    <td class="fw-bold"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td><?php echo sanitize(($row['class_name'] ?? $row['school_class'] ?? '—') . ' - ' . ($row['section'] ?? $row['school_section'] ?? 'A')); ?></td>
                                    <td><strong><?php echo date('M d, Y', strtotime($row['start_date'])); ?></strong></td>
                                    <td><strong><?php echo date('M d, Y', strtotime($row['end_date'])); ?></strong></td>
                                    <td class="fw-bold font-monospace"><?php echo $row['total_days']; ?></td>
                                    <td><span class="badge bg-secondary"><?php echo sanitize($row['leave_type']); ?></span></td>
                                    <td>
                                        <?php
                                        $badge = 'bg-secondary';
                                        if ($row['status'] === 'Pending') $badge = 'bg-warning text-dark';
                                        elseif ($row['status'] === 'Approved') $badge = 'bg-success';
                                        elseif ($row['status'] === 'Rejected') $badge = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $badge; ?>"><?php echo sanitize($row['status']); ?></span>
                                    </td>
                                    <td class="text-end d-print-none">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="viewLeaveDetails(<?php echo htmlspecialchars(json_encode($row)); ?>)" title="View Details"><i class="fa-regular fa-eye"></i></button>
                                            <?php if (hasPermission('attendance_mark')): ?>
                                                <a href="leave.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-primary btn-sm" title="Edit application"><i class="fa-regular fa-pen-to-square"></i></a>
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="triggerDelete(<?php echo $row['id']; ?>)" title="Delete"><i class="fa-regular fa-trash-can"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mb-4 d-print-none">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $filter_date ? '&filter_date='.$filter_date : ''; ?><?php echo $filter_class ? '&filter_class='.$filter_class : ''; ?><?php echo $filter_section ? '&filter_section='.$filter_section : ''; ?><?php echo $filter_status ? '&filter_status='.$filter_status : ''; ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $filter_date ? '&filter_date='.$filter_date : ''; ?><?php echo $filter_class ? '&filter_class='.$filter_class : ''; ?><?php echo $filter_section ? '&filter_section='.$filter_section : ''; ?><?php echo $filter_status ? '&filter_status='.$filter_status : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $filter_date ? '&filter_date='.$filter_date : ''; ?><?php echo $filter_class ? '&filter_class='.$filter_class : ''; ?><?php echo $filter_section ? '&filter_section='.$filter_section : ''; ?><?php echo $filter_status ? '&filter_status='.$filter_status : ''; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        </div>
        
        <!-- TAB 2: LOG NEW LEAVE FORM -->
        <div class="tab-pane fade" id="create-pane" role="tabpanel">
            <div class="card border-0 shadow-sm bg-white p-4" style="border-radius:12px;">
                <h5 class="fw-bold text-secondary mb-4 border-bottom pb-3"><i class="fa-solid fa-plus-circle me-2 text-primary"></i>Compose Leave Application</h5>
                <form id="createLeaveForm" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="save">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Select Student (Admission No) *</label>
                            <select class="form-select" name="student_id" id="studentSelectNew" onchange="autoFillStudentDetailsNew(this)" required>
                                <option value="" data-name="" data-class="" data-section="">— Select Student —</option>
                                <?php foreach ($studentsList as $st): ?>
                                    <option value="<?php echo $st['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?>"
                                            data-class="<?php echo htmlspecialchars($st['class_name'] ?? $st['school_class'] ?? '—'); ?>"
                                            data-section="<?php echo htmlspecialchars($st['section'] ?? $st['school_section'] ?? 'A'); ?>">
                                        <?php echo htmlspecialchars($st['admission_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Leave Type *</label>
                            <select class="form-select" name="leave_type" required>
                                <option value="Casual">Casual</option>
                                <option value="Medical">Medical</option>
                                <option value="Sabbatical">Sabbatical</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <!-- Readonly auto fields -->
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Student Name</label>
                            <input type="text" class="form-control bg-light" id="studentNameInputNew" readonly value="—">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Class</label>
                            <input type="text" class="form-control bg-light" id="classInputNew" readonly value="—">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Section</label>
                            <input type="text" class="form-control bg-light" id="sectionInputNew" readonly value="—">
                        </div>

                        <!-- Range -->
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Leave From *</label>
                            <input type="date" class="form-control" name="start_date" id="leaveFromInputNew" onchange="calculateTotalDaysNew()" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Leave To *</label>
                            <input type="date" class="form-control" name="end_date" id="leaveToInputNew" onchange="calculateTotalDaysNew()" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Total Days</label>
                            <input type="text" class="form-control bg-light font-monospace" id="totalDaysInputNew" readonly value="0">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Leave Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Attachment File (Optional)</label>
                            <input type="file" class="form-control" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Reason *</label>
                            <textarea class="form-control" name="reason" rows="3" required placeholder="Reason details..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2" placeholder="Office Remarks..."></textarea>
                        </div>
                        
                        <div class="col-12 text-end border-top pt-3 mt-4">
                            <button type="submit" id="createBtn" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-2"></i>Save</button>
                            <button type="submit" name="save_and_new" id="createNewBtn" class="btn btn-outline-primary"><i class="fa-solid fa-plus-square me-2"></i>Save & New</button>
                            <button type="reset" class="btn btn-outline-secondary">Reset</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
    </div>

    <!-- Modal View Details -->
    <div class="modal fade" id="viewLeaveModal" tabindex="-1" aria-labelledby="viewLeaveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius:12px;">
                <div class="modal-header bg-light border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark" id="viewLeaveModalLabel"><i class="fa-solid fa-umbrella-beach text-primary me-2"></i>Leave Application details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="row g-3" id="printableLeaveArea">
                        <div class="col-md-6"><span class="text-muted small d-block">Student Name</span><strong class="text-dark fs-5" id="v-name"></strong></div>
                        <div class="col-md-6"><span class="text-muted small d-block">Admission No</span><strong class="text-dark fs-5 font-monospace text-primary" id="v-admission"></strong></div>
                        
                        <div class="col-md-4"><span class="text-muted small d-block">Class</span><strong class="text-dark" id="v-class"></strong></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Section</span><strong class="text-dark" id="v-section"></strong></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Academic Type</span><span class="badge bg-secondary" id="v-type"></span></div>
                        
                        <div class="col-md-4"><span class="text-muted small d-block">Leave From</span><strong class="text-dark" id="v-from"></strong></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Leave To</span><strong class="text-dark" id="v-to"></strong></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Total Days</span><strong class="text-dark font-monospace" id="v-days"></strong></div>
                        
                        <div class="col-md-6"><span class="text-muted small d-block">Category</span><span class="badge bg-primary" id="v-category"></span></div>
                        <div class="col-md-6"><span class="text-muted small d-block">Status</span><span class="badge" id="v-status"></span></div>
                        
                        <div class="col-12"><span class="text-muted small d-block mb-1">Reason</span><div class="p-3 border rounded bg-light" id="v-reason"></div></div>
                        <div class="col-12"><span class="text-muted small d-block mb-1">Office Remarks / Actions</span><div class="p-3 border rounded bg-light text-muted" id="v-remarks"></div></div>
                        
                        <div class="col-12" id="v-attachment-row">
                            <span class="text-muted small d-block mb-1">Attachment File</span>
                            <a href="#" target="_blank" class="btn btn-sm btn-outline-primary" id="v-attachment-link"><i class="fa-solid fa-paperclip me-2"></i>Download File</a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <!-- Dynamic inline Approve/Reject action buttons -->
                    <div class="me-auto" id="inlineActionGroup">
                        <form method="POST" action="leave.php" class="d-inline">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="leave_id" id="inline-approve-id">
                            <button type="submit" class="btn btn-success btn-sm me-1"><i class="fa-solid fa-check me-1"></i>Approve</button>
                        </form>
                        <form method="POST" action="leave.php" class="d-inline">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="leave_id" id="inline-reject-id">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-times me-1"></i>Reject</button>
                        </form>
                    </div>
                    <button type="button" class="btn btn-outline-secondary" onclick="printLeaveArea()"><i class="fa-solid fa-print me-2"></i>Print</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Delete Confirmation -->
    <div class="modal fade" id="deleteLeaveModal" tabindex="-1" aria-labelledby="deleteLeaveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius:12px;">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger" id="deleteLeaveModalLabel"><i class="fa-solid fa-circle-exclamation me-2"></i>Delete Leave application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p>Are you sure you want to permanently delete this leave application?</p>
                    <p class="text-muted small mb-0"><i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i>This action cannot be undone and will delete this leave request history permanently.</p>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <form method="POST" action="leave.php">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="leave_id" id="delete-leave-id" value="">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-4">Delete Record</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function autoFillStudentDetailsNew(select) {
        const selectedOption = select.options[select.selectedIndex];
        document.getElementById("studentNameInputNew").value = selectedOption.getAttribute("data-name") || "—";
        document.getElementById("classInputNew").value = selectedOption.getAttribute("data-class") || "—";
        document.getElementById("sectionInputNew").value = selectedOption.getAttribute("data-section") || "—";
    }

    function calculateTotalDaysNew() {
        const fromVal = document.getElementById("leaveFromInputNew").value;
        const toVal = document.getElementById("leaveToInputNew").value;
        if (fromVal && toVal) {
            const start = new Date(fromVal);
            const end = new Date(toVal);
            if (end >= start) {
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                document.getElementById("totalDaysInputNew").value = diffDays;
            } else {
                document.getElementById("totalDaysInputNew").value = 0;
            }
        } else {
            document.getElementById("totalDaysInputNew").value = 0;
        }
    }

    const viewModal = new bootstrap.Modal(document.getElementById("viewLeaveModal"));
    function viewLeaveDetails(d) {
        document.getElementById("v-name").textContent = d.first_name + ' ' + d.last_name;
        document.getElementById("v-admission").textContent = d.admission_no;
        document.getElementById("v-class").textContent = d.class_name ? d.class_name : (d.school_class ? d.school_class : '—');
        document.getElementById("v-section").textContent = d.section ? d.section : (d.school_section ? d.school_section : '—');
        document.getElementById("v-type").textContent = d.academic_type;
        document.getElementById("v-from").textContent = d.start_date;
        document.getElementById("v-to").textContent = d.end_date;
        document.getElementById("v-days").textContent = d.total_days;
        document.getElementById("v-category").textContent = d.leave_type;
        document.getElementById("v-reason").textContent = d.reason;
        document.getElementById("v-remarks").textContent = d.remarks ? d.remarks : 'No administrative actions recorded.';

        // Status badge
        const stat = document.getElementById("v-status");
        stat.textContent = d.status;
        stat.className = "badge " + (d.status === 'Pending' ? 'bg-warning text-dark' : (d.status === 'Approved' ? 'bg-success' : 'bg-danger'));

        // Inline Action Group Visibility
        const inlineGrp = document.getElementById("inlineActionGroup");
        if (d.status === 'Pending') {
            inlineGrp.style.display = "block";
            document.getElementById("inline-approve-id").value = d.id;
            document.getElementById("inline-reject-id").value = d.id;
        } else {
            inlineGrp.style.display = "none";
        }

        // Attach
        const attRow = document.getElementById("v-attachment-row");
        if (d.attachment) {
            attRow.style.display = "block";
            document.getElementById("v-attachment-link").href = "<?php echo APP_URL; ?>/" + d.attachment;
        } else {
            attRow.style.display = "none";
        }

        viewModal.show();
    }

    function printLeaveArea() {
        const printContent = document.getElementById("printableLeaveArea").innerHTML;
        const originalContent = document.body.innerHTML;
        
        document.body.innerHTML = `
            <div style="padding:40px; font-family: sans-serif; background-color: #fff;">
                <h2 style="text-align:center; margin-bottom: 2px;">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
                <h4 style="text-align:center; color: #555; border-bottom: 2px solid #333; padding-bottom: 10px; margin-top: 0;">Student Leave Application Details</h4>
                ${printContent}
            </div>
        `;
        
        window.print();
        document.body.innerHTML = originalContent;
        window.location.reload();
    }

    const delModal = new bootstrap.Modal(document.getElementById("deleteLeaveModal"));
    function triggerDelete(id) {
        document.getElementById("delete-leave-id").value = id;
        delModal.show();
    }

    // Form load spinners
    document.getElementById("createLeaveForm").addEventListener("submit", function(e) {
        const form = this;
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            alert("Please fill in all required fields indicated by *.");
            form.classList.add("was-validated");
        } else {
            const btnSave = document.getElementById("createBtn");
            const btnNew = document.getElementById("createNewBtn");
            if (btnSave) {
                btnSave.disabled = true;
                btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';
            }
            if (btnNew) btnNew.disabled = true;
        }
    });
    </script>
<?php endif; ?>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
