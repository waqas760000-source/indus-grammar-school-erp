<?php
/**
 * Indus Grammar School ERP - Student Complaint Management Module
 * Version 2.0.0
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();
$message = '';
$error = '';

// Load flash messages from redirects
if (!empty($_SESSION['flash_success'])) {
    $message = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// 2. Load Lookups (Active students with registration details)
$studentsList = [];
try {
    $studentsList = $db->query("
        SELECT s.id, s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section,
               c.class_name, c.section, d.father_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        WHERE s.status = 'Active'
        ORDER BY s.admission_no ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Check if a specific complaint ID is loaded for editing
$complaintId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$complaint = null;

if ($complaintId > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM student_complaints WHERE id = ?");
        $stmt->execute([$complaintId]);
        $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$complaint) {
            $error = "Complaint record not found.";
            $complaintId = 0;
        }
    } catch (Exception $e) {
        $error = "Error loading complaint: " . $e->getMessage();
        $complaintId = 0;
    }
}

// Generate sequential Complaint Number if new
$nextComplaintNo = $complaint['complaint_number'] ?? '';
if ($complaintId === 0) {
    $year = date('Y');
    try {
        $maxId = (int)$db->query("SELECT MAX(id) FROM student_complaints")->fetchColumn();
        $nextComplaintNo = sprintf("COM-%s-%04d", $year, $maxId + 1);
    } catch (Exception $e) {
        $nextComplaintNo = "COM-" . $year . "-" . rand(1000, 9999);
    }
}

// 3. Handle POST Actions (Create / Update / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');

    // CREATE / UPDATE ACTION
    if ($action === 'save' || $action === 'update') {
        try {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $complaint_date = sanitize($_POST['complaint_date'] ?? date('Y-m-d'));
            $category = sanitize($_POST['category'] ?? 'Discipline');
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $action_taken = sanitize($_POST['action_taken'] ?? '');
            $status = sanitize($_POST['status'] ?? 'Pending');
            $remarks = sanitize($_POST['remarks'] ?? '');
            $created_by = $_SESSION['user_id'] ?? null;

            if ($student_id <= 0 || empty($title) || empty($description)) {
                throw new Exception("Please select a student and fill in all required fields.");
            }

            // Retrieve class_id associated with student
            $stmtC = $db->prepare("SELECT class_id FROM students WHERE id = ?");
            $stmtC->execute([$student_id]);
            $class_id = $stmtC->fetchColumn();

            if ($action === 'save') {
                // Ensure unique complaint number
                $stmtCheck = $db->prepare("SELECT COUNT(*) FROM student_complaints WHERE complaint_number = ?");
                $stmtCheck->execute([$nextComplaintNo]);
                if ($stmtCheck->fetchColumn() > 0) {
                    // Regenerate
                    $maxId = (int)$db->query("SELECT MAX(id) FROM student_complaints")->fetchColumn();
                    $nextComplaintNo = sprintf("COM-%s-%04d", date('Y'), $maxId + 1);
                }

                $stmt = $db->prepare("
                    INSERT INTO student_complaints (
                        complaint_number, student_id, class_id, complaint_date, category, title, description, action_taken, status, remarks, created_by
                    ) VALUES (
                        :complaint_number, :student_id, :class_id, :complaint_date, :category, :title, :description, :action_taken, :status, :remarks, :created_by
                    )
                ");
                $stmt->execute([
                    'complaint_number' => $nextComplaintNo,
                    'student_id' => $student_id,
                    'class_id' => $class_id ?: null,
                    'complaint_date' => $complaint_date,
                    'category' => $category,
                    'title' => $title,
                    'description' => $description,
                    'action_taken' => $action_taken,
                    'status' => $status,
                    'remarks' => $remarks,
                    'created_by' => $created_by
                ]);

                $targetId = $db->lastInsertId();
                $logDesc = "Logged Complaint $nextComplaintNo for Student ID $student_id | Category: $category";
                $logAction = "Complaint Logged";
            } else {
                $targetId = (int)$_POST['complaint_id'];
                if ($targetId <= 0) throw new Exception("Invalid complaint ID.");

                $stmt = $db->prepare("
                    UPDATE student_complaints SET 
                        student_id = :student_id, class_id = :class_id, complaint_date = :complaint_date,
                        category = :category, title = :title, description = :description,
                        action_taken = :action_taken, status = :status, remarks = :remarks,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $stmt->execute([
                    'student_id' => $student_id,
                    'class_id' => $class_id ?: null,
                    'complaint_date' => $complaint_date,
                    'category' => $category,
                    'title' => $title,
                    'description' => $description,
                    'action_taken' => $action_taken,
                    'status' => $status,
                    'remarks' => $remarks,
                    'id' => $targetId
                ]);

                $logDesc = "Updated Complaint ID $targetId | Category: $category";
                $logAction = "Complaint Updated";
            }

            // Audit log
            $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$created_by, $logAction, $logDesc, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

            $_SESSION['flash_success'] = ($action === 'save') ? "Complaint file generated successfully!" : "Complaint records updated successfully.";
            if (isset($_POST['save_and_new'])) {
                header("Location: complaint.php#create-tab");
            } else {
                header("Location: complaint.php");
            }
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // DELETE ACTION
    if ($action === 'delete') {
        try {
            $targetId = (int)($_POST['complaint_id'] ?? 0);
            if ($targetId <= 0) throw new Exception("Invalid complaint ID reference.");

            $stmtInfo = $db->prepare("SELECT complaint_number FROM student_complaints WHERE id = ?");
            $stmtInfo->execute([$targetId]);
            $compNo = $stmtInfo->fetchColumn();

            $stmtDel = $db->prepare("DELETE FROM student_complaints WHERE id = ?");
            $stmtDel->execute([$targetId]);

            // Audit log
            $logDesc = "Deleted Complaint entry $compNo (ID: $targetId)";
            $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$_SESSION['user_id'] ?? null, 'Complaint Deleted', $logDesc, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

            $_SESSION['flash_success'] = "Complaint record deleted successfully.";
            header("Location: complaint.php");
            exit;
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Error deleting complaint: " . $e->getMessage();
            header("Location: complaint.php");
            exit;
        }
    }
}

// 4. Retrieve list filters
$search_number = sanitize($_GET['search_number'] ?? '');
$search_admission = sanitize($_GET['search_admission'] ?? '');
$search_date = sanitize($_GET['search_date'] ?? '');
$search_class = sanitize($_GET['search_class'] ?? '');
$search_section = sanitize($_GET['search_section'] ?? '');
$search_category = sanitize($_GET['search_category'] ?? '');
$search_status = sanitize($_GET['search_status'] ?? '');

$limit = 10;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$where = " WHERE 1=1";
$params = [];

if ($search_number !== '') {
    $where .= " AND c.complaint_number LIKE :search_number";
    $params['search_number'] = '%' . $search_number . '%';
}
if ($search_admission !== '') {
    $where .= " AND s.admission_no = :search_admission";
    $params['search_admission'] = $search_admission;
}
if ($search_date !== '') {
    $where .= " AND c.complaint_date = :search_date";
    $params['search_date'] = $search_date;
}
if ($search_class !== '') {
    $where .= " AND (cls.class_name = :search_class OR s.school_class = :search_class)";
    $params['search_class'] = $search_class;
}
if ($search_section !== '') {
    $where .= " AND (cls.section = :search_section OR s.school_section = :search_section)";
    $params['search_section'] = $search_section;
}
if ($search_category !== '') {
    $where .= " AND c.category = :search_category";
    $params['search_category'] = $search_category;
}
if ($search_status !== '') {
    $where .= " AND c.status = :search_status";
    $params['search_status'] = $search_status;
}

$complaints = [];
$totalEntries = 0;

// Counts for Summary Cards based on matching filters
$pendingCount = 0;
$progressCount = 0;
$resolvedCount = 0;

try {
    // Counts queries
    $stmtCount = $db->prepare("
        SELECT COUNT(*) 
        FROM student_complaints c 
        LEFT JOIN students s ON c.student_id = s.id 
        LEFT JOIN classes cls ON c.class_id = cls.id 
        $where
    ");
    $stmtCount->execute($params);
    $totalEntries = (int)$stmtCount->fetchColumn();

    $stmtPend = $db->prepare("
        SELECT COUNT(*) 
        FROM student_complaints c 
        LEFT JOIN students s ON c.student_id = s.id 
        LEFT JOIN classes cls ON c.class_id = cls.id 
        $where AND c.status = 'Pending'
    ");
    $stmtPend->execute($params);
    $pendingCount = (int)$stmtPend->fetchColumn();

    $stmtProg = $db->prepare("
        SELECT COUNT(*) 
        FROM student_complaints c 
        LEFT JOIN students s ON c.student_id = s.id 
        LEFT JOIN classes cls ON c.class_id = cls.id 
        $where AND c.status = 'In Progress'
    ");
    $stmtProg->execute($params);
    $progressCount = (int)$stmtProg->fetchColumn();

    $stmtRes = $db->prepare("
        SELECT COUNT(*) 
        FROM student_complaints c 
        LEFT JOIN students s ON c.student_id = s.id 
        LEFT JOIN classes cls ON c.class_id = cls.id 
        $where AND c.status = 'Resolved'
    ");
    $stmtRes->execute($params);
    $resolvedCount = (int)$stmtRes->fetchColumn();

    // Data query
    $stmtData = $db->prepare("
        SELECT c.*, s.admission_no, s.first_name, s.last_name, s.academic_type, s.school_class, s.school_section,
               cls.class_name, cls.section, d.father_name
        FROM student_complaints c
        LEFT JOIN students s ON c.student_id = s.id
        LEFT JOIN classes cls ON c.class_id = cls.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY c.complaint_date DESC, c.created_at DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmtData->bindValue($k, $v);
    }
    $stmtData->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    
    $complaints = $stmtData->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Complaints list querying error: " . $e->getMessage());
}

$totalPages = ceil($totalEntries / $limit);
if ($totalPages < 1) $totalPages = 1;

// Load unique sections list
$sectionsList = ['A', 'B', 'C', 'D', 'E'];
try {
    $dbSecs = $db->query("SELECT DISTINCT section FROM classes WHERE section != '' ORDER BY section ASC")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dbSecs as $ds) {
        if (!in_array($ds, $sectionsList)) {
            $sectionsList[] = $ds;
        }
    }
} catch (Exception $e) {}

$pageTitle = ($complaintId > 0) ? 'Modify Complaint File' : 'Student Complaints';
$breadcrumbActive = 'Complaints';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Title Banner -->
<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-circle-exclamation me-2 text-primary"></i><?php echo ($complaintId > 0) ? 'Modify Complaint Log' : 'Student Complaints'; ?></h3>
    </div>
    <?php if ($complaintId > 0): ?>
        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
            <a href="complaint.php" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left me-2"></i>Back to Complaints list</a>
        </div>
    <?php endif; ?>
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

<?php if ($complaintId > 0 && $complaint): ?>
    <!-- 5. EDIT COMPLAINT FORM STATE -->
    <div class="card border-0 shadow-sm bg-white p-4" style="border-radius:12px;">
        <h5 class="fw-bold text-secondary mb-4 border-bottom pb-3"><i class="fa-solid fa-pen me-2 text-primary"></i>Modify Student Complaint Details</h5>
        <form id="complaintForm" method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
            
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted">Complaint Number</label>
                    <input type="text" class="form-control bg-light" name="complaint_number" value="<?php echo htmlspecialchars($complaint['complaint_number']); ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted">Complaint Date *</label>
                    <input type="date" class="form-control" name="complaint_date" value="<?php echo $complaint['complaint_date']; ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted">Select Student (Admission No) *</label>
                    <select class="form-select" name="student_id" id="studentSelect" onchange="autoFillStudentDetails(this)" required>
                        <option value="" data-name="" data-father="" data-type="" data-class="" data-section="">— Select Student —</option>
                        <?php foreach ($studentsList as $st): ?>
                            <option value="<?php echo $st['id']; ?>"
                                    data-admission="<?php echo htmlspecialchars($st['admission_no']); ?>"
                                    data-name="<?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?>"
                                    data-father="<?php echo htmlspecialchars($st['father_name'] ?? '—'); ?>"
                                    data-type="<?php echo htmlspecialchars($st['academic_type'] ?? 'School'); ?>"
                                    data-class="<?php echo htmlspecialchars($st['class_name'] ?? $st['school_class'] ?? '—'); ?>"
                                    data-section="<?php echo htmlspecialchars($st['section'] ?? $st['school_section'] ?? 'A'); ?>"
                                    <?php echo ($complaint['student_id'] == $st['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($st['admission_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Auto-filled readonly student information -->
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">Student Name</label>
                    <input type="text" class="form-control bg-light" id="studentNameInput" readonly value="—">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">Father Name</label>
                    <input type="text" class="form-control bg-light" id="fatherNameInput" readonly value="—">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">Academic Type</label>
                    <input type="text" class="form-control bg-light" id="academicTypeInput" readonly value="—">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted">Class</label>
                    <input type="text" class="form-control bg-light" id="classInput" readonly value="—">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-muted">Section</label>
                    <input type="text" class="form-control bg-light" id="sectionInput" readonly value="—">
                </div>

                <!-- Complaint parameters details -->
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">Complaint Category *</label>
                    <select class="form-select" name="category" required>
                        <option value="Discipline" <?php echo ($complaint['category'] === 'Discipline') ? 'selected' : ''; ?>>Discipline</option>
                        <option value="Homework" <?php echo ($complaint['category'] === 'Homework') ? 'selected' : ''; ?>>Homework</option>
                        <option value="Attendance" <?php echo ($complaint['category'] === 'Attendance') ? 'selected' : ''; ?>>Attendance</option>
                        <option value="Uniform" <?php echo ($complaint['category'] === 'Uniform') ? 'selected' : ''; ?>>Uniform</option>
                        <option value="Misconduct" <?php echo ($complaint['category'] === 'Misconduct') ? 'selected' : ''; ?>>Misconduct</option>
                        <option value="Fee" <?php echo ($complaint['category'] === 'Fee') ? 'selected' : ''; ?>>Fee</option>
                        <option value="Other" <?php echo ($complaint['category'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">Complaint Status *</label>
                    <select class="form-select" name="status" required>
                        <option value="Pending" <?php echo ($complaint['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="In Progress" <?php echo ($complaint['status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                        <option value="Resolved" <?php echo ($complaint['status'] === 'Resolved') ? 'selected' : ''; ?>>Resolved</option>
                        <option value="Closed" <?php echo ($complaint['status'] === 'Closed') ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">Complaint Title *</label>
                    <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($complaint['title']); ?>" required placeholder="e.g. Incomplete Homework">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold text-muted">Complaint Description *</label>
                    <textarea class="form-control" name="description" rows="4" required placeholder="Provide descriptive details of the complaint..."><?php echo htmlspecialchars($complaint['description']); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold text-muted">Action Taken</label>
                    <textarea class="form-control" name="action_taken" rows="2" placeholder="Write any actions taken by administration..."><?php echo htmlspecialchars($complaint['action_taken']); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold text-muted">Remarks</label>
                    <textarea class="form-control" name="remarks" rows="2" placeholder="Office remarks..."><?php echo htmlspecialchars($complaint['remarks']); ?></textarea>
                </div>
                
                <div class="col-12 text-end border-top pt-3 mt-4">
                    <button type="submit" id="saveBtn" class="btn btn-primary px-4"><i class="fa-solid fa-circle-check me-2"></i>Update</button>
                    <a href="complaint.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>

    <script>
    function autoFillStudentDetails(select) {
        const selectedOption = select.options[select.selectedIndex];
        document.getElementById("studentNameInput").value = selectedOption.getAttribute("data-name") || "—";
        document.getElementById("fatherNameInput").value = selectedOption.getAttribute("data-father") || "—";
        document.getElementById("academicTypeInput").value = selectedOption.getAttribute("data-type") || "—";
        document.getElementById("classInput").value = selectedOption.getAttribute("data-class") || "—";
        document.getElementById("sectionInput").value = selectedOption.getAttribute("data-section") || "—";
    }
    
    document.addEventListener("DOMContentLoaded", () => {
        autoFillStudentDetails(document.getElementById("studentSelect"));
    });
    </script>

<?php else: ?>
    <!-- 6. MASTER COMPLAINTS LIST STATE -->
    
    <!-- Tabbed navigation -->
    <div class="card border border-light shadow-sm bg-white mb-4" style="border-radius: 12px;">
        <div class="card-body p-2">
            <ul class="nav nav-pills nav-fill" id="complaintTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="list-tab-btn" data-bs-toggle="pill" data-bs-target="#list-pane" type="button" role="tab"><i class="fa-solid fa-list me-2"></i>Complaints & Summary Dashboard</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="create-tab-btn" data-bs-toggle="pill" data-bs-target="#create-pane" type="button" role="tab"><i class="fa-solid fa-plus-circle me-2"></i>Log New Complaint</button>
                </li>
            </ul>
        </div>
    </div>

    <!-- Summary Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center h-100 bg-white" style="border-radius:12px; border-left: 4px solid #0056b3 !important;">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Total Complaints</span>
                    <h3 class="fw-bold text-dark mb-0"><?php echo $totalEntries; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center h-100 bg-white" style="border-radius:12px; border-left: 4px solid #ffc107 !important;">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Pending</span>
                    <h3 class="fw-bold text-dark mb-0"><?php echo $pendingCount; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center h-100 bg-white" style="border-radius:12px; border-left: 4px solid #17a2b8 !important;">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">In Progress</span>
                    <h3 class="fw-bold text-dark mb-0"><?php echo $progressCount; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center h-100 bg-white" style="border-radius:12px; border-left: 4px solid #28a745 !important;">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Resolved</span>
                    <h3 class="fw-bold text-dark mb-0"><?php echo $resolvedCount; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Content Container -->
    <div class="tab-content">
        
        <!-- TAB 1: LIST & SEARCH PANEL -->
        <div class="tab-pane fade show active" id="list-pane" role="tabpanel">
            
            <!-- Filters Panel -->
            <div class="card border border-light shadow-sm bg-white p-4 mb-4" style="border-radius:12px;">
                <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Complaint Logs</h6>
                <form method="GET" action="complaint.php" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted">Complaint No.</label>
                        <input type="text" class="form-control form-control-sm" name="search_number" value="<?php echo htmlspecialchars($search_number); ?>" placeholder="e.g. COM-2026-0001">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted">Admission No.</label>
                        <input type="text" class="form-control form-control-sm" name="search_admission" value="<?php echo htmlspecialchars($search_admission); ?>" placeholder="e.g. ADM-2026-0001">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted">Complaint Date</label>
                        <input type="date" class="form-control form-control-sm" name="search_date" value="<?php echo htmlspecialchars($search_date); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted">Class</label>
                        <select class="form-select form-select-sm" name="search_class">
                            <option value="">All</option>
                            <?php foreach (['Play Group', 'Nursery', 'Prep', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10', 'Class 11', 'Class 12'] as $cls): ?>
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
                        <label class="form-label small fw-semibold text-muted">Category</label>
                        <select class="form-select form-select-sm" name="search_category">
                            <option value="">All</option>
                            <option value="Discipline" <?php echo ($search_category === 'Discipline') ? 'selected' : ''; ?>>Discipline</option>
                            <option value="Homework" <?php echo ($search_category === 'Homework') ? 'selected' : ''; ?>>Homework</option>
                            <option value="Attendance" <?php echo ($search_category === 'Attendance') ? 'selected' : ''; ?>>Attendance</option>
                            <option value="Uniform" <?php echo ($search_category === 'Uniform') ? 'selected' : ''; ?>>Uniform</option>
                            <option value="Misconduct" <?php echo ($search_category === 'Misconduct') ? 'selected' : ''; ?>>Misconduct</option>
                            <option value="Fee" <?php echo ($search_category === 'Fee') ? 'selected' : ''; ?>>Fee</option>
                            <option value="Other" <?php echo ($search_category === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted">Status</label>
                        <select class="form-select form-select-sm" name="search_status">
                            <option value="">All</option>
                            <option value="Pending" <?php echo ($search_status === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo ($search_status === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Resolved" <?php echo ($search_status === 'Resolved') ? 'selected' : ''; ?>>Resolved</option>
                            <option value="Closed" <?php echo ($search_status === 'Closed') ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    <div class="col-md-10 text-end">
                        <button type="submit" class="btn btn-sm btn-primary px-4"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
                        <a href="complaint.php" class="btn btn-sm btn-outline-secondary px-3">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Complaints Table list -->
            <div class="custom-table-card shadow-sm border-0 mb-4 bg-white" style="border-radius:12px;">
                <div class="table-responsive">
                    <table class="table custom-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Complaint No.</th>
                                <th>Date</th>
                                <th>Admission No.</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Section</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($complaints)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No student complaint records found match active criteria.</td>
                                </tr>
                            <?php else: foreach ($complaints as $row): ?>
                                <tr>
                                    <td><strong class="text-primary font-monospace"><?php echo sanitize($row['complaint_number']); ?></strong></td>
                                    <td><strong><?php echo date('M d, Y', strtotime($row['complaint_date'])); ?></strong></td>
                                    <td class="fw-bold"><?php echo sanitize($row['admission_no']); ?></td>
                                    <td><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td><?php echo sanitize($row['class_name'] ?? $row['school_class'] ?? '—'); ?></td>
                                    <td><?php echo sanitize($row['section'] ?? $row['school_section'] ?? 'A'); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo sanitize($row['category']); ?></span></td>
                                    <td>
                                        <?php
                                        $statusBadge = 'bg-secondary';
                                        if ($row['status'] === 'Pending') $statusBadge = 'bg-warning text-dark';
                                        elseif ($row['status'] === 'In Progress') $statusBadge = 'bg-info text-dark';
                                        elseif ($row['status'] === 'Resolved') $statusBadge = 'bg-success';
                                        elseif ($row['status'] === 'Closed') $statusBadge = 'bg-dark';
                                        ?>
                                        <span class="badge <?php echo $statusBadge; ?>"><?php echo sanitize($row['status']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="viewComplaintDetails(<?php echo htmlspecialchars(json_encode($row)); ?>)" title="View Details">
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                            <?php if (hasPermission('student_edit')): ?>
                                                <a href="complaint.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-primary btn-sm" title="Edit Complaint">
                                                    <i class="fa-regular fa-pen-to-square"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (hasPermission('student_delete')): ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="triggerDelete(<?php echo $row['id']; ?>)" title="Delete Complaint">
                                                    <i class="fa-regular fa-trash-can"></i>
                                                </button>
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
                <nav aria-label="Page navigation" class="mb-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search_number ? '&search_number='.$search_number : ''; ?><?php echo $search_admission ? '&search_admission='.$search_admission : ''; ?><?php echo $search_date ? '&search_date='.$search_date : ''; ?><?php echo $search_class ? '&search_class='.$search_class : ''; ?><?php echo $search_section ? '&search_section='.$search_section : ''; ?><?php echo $search_category ? '&search_category='.$search_category : ''; ?><?php echo $search_status ? '&search_status='.$search_status : ''; ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                               <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search_number ? '&search_number='.$search_number : ''; ?><?php echo $search_admission ? '&search_admission='.$search_admission : ''; ?><?php echo $search_date ? '&search_date='.$search_date : ''; ?><?php echo $search_class ? '&search_class='.$search_class : ''; ?><?php echo $search_section ? '&search_section='.$search_section : ''; ?><?php echo $search_category ? '&search_category='.$search_category : ''; ?><?php echo $search_status ? '&search_status='.$search_status : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search_number ? '&search_number='.$search_number : ''; ?><?php echo $search_admission ? '&search_admission='.$search_admission : ''; ?><?php echo $search_date ? '&search_date='.$search_date : ''; ?><?php echo $search_class ? '&search_class='.$search_class : ''; ?><?php echo $search_section ? '&search_section='.$search_section : ''; ?><?php echo $search_category ? '&search_category='.$search_category : ''; ?><?php echo $search_status ? '&search_status='.$search_status : ''; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        </div>
        
        <!-- TAB 2: LOG NEW COMPLAINT FORM -->
        <div class="tab-pane fade" id="create-pane" role="tabpanel">
            <div class="card border-0 shadow-sm bg-white p-4" style="border-radius:12px;">
                <h5 class="fw-bold text-secondary mb-4 border-bottom pb-3"><i class="fa-solid fa-plus-circle me-2 text-primary"></i>Record Student Complaint Log</h5>
                <form id="createComplaintForm" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="save">
                    
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Complaint Number (Auto-Generated)</label>
                            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($nextComplaintNo); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Complaint Date *</label>
                            <input type="date" class="form-control" name="complaint_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Select Student (Admission No) *</label>
                            <select class="form-select" name="student_id" id="studentSelectNew" onchange="autoFillStudentDetailsNew(this)" required>
                                <option value="" data-name="" data-father="" data-type="" data-class="" data-section="">— Select Student —</option>
                                <?php foreach ($studentsList as $st): ?>
                                    <option value="<?php echo $st['id']; ?>"
                                            data-admission="<?php echo htmlspecialchars($st['admission_no']); ?>"
                                            data-name="<?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?>"
                                            data-father="<?php echo htmlspecialchars($st['father_name'] ?? '—'); ?>"
                                            data-type="<?php echo htmlspecialchars($st['academic_type'] ?? 'School'); ?>"
                                            data-class="<?php echo htmlspecialchars($st['class_name'] ?? $st['school_class'] ?? '—'); ?>"
                                            data-section="<?php echo htmlspecialchars($st['section'] ?? $st['school_section'] ?? 'A'); ?>">
                                        <?php echo htmlspecialchars($st['admission_no'] . ' - ' . $st['first_name'] . ' ' . $st['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Auto-filled student indicators -->
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Student Name</label>
                            <input type="text" class="form-control bg-light" id="studentNameInputNew" readonly value="—">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Father Name</label>
                            <input type="text" class="form-control bg-light" id="fatherNameInputNew" readonly value="—">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Academic Type</label>
                            <input type="text" class="form-control bg-light" id="academicTypeInputNew" readonly value="—">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Class</label>
                            <input type="text" class="form-control bg-light" id="classInputNew" readonly value="—">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Section</label>
                            <input type="text" class="form-control bg-light" id="sectionInputNew" readonly value="—">
                        </div>

                        <!-- Parameters Details -->
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Complaint Category *</label>
                            <select class="form-select" name="category" required>
                                <option value="Discipline">Discipline</option>
                                <option value="Homework">Homework</option>
                                <option value="Attendance">Attendance</option>
                                <option value="Uniform">Uniform</option>
                                <option value="Misconduct">Misconduct</option>
                                <option value="Fee">Fee</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Complaint Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="Pending">Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Resolved">Resolved</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Complaint Title *</label>
                            <input type="text" class="form-control" name="title" required placeholder="e.g. Unruly Classroom Behavior">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Complaint Description *</label>
                            <textarea class="form-control" name="description" rows="4" required placeholder="Details of student misconduct..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Action Taken</label>
                            <textarea class="form-control" name="action_taken" rows="2" placeholder="Action taken details..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2" placeholder="Office remarks..."></textarea>
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
    <div class="modal fade" id="viewComplaintModal" tabindex="-1" aria-labelledby="viewComplaintModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius:12px;">
                <div class="modal-header bg-light border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark" id="viewComplaintModalLabel"><i class="fa-solid fa-circle-exclamation text-primary me-2"></i>Complaint File Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="row g-3" id="printableSingleArea">
                        <div class="col-md-6"><span class="text-muted small d-block">Complaint Number</span><strong class="text-dark fs-5 font-monospace text-primary" id="v-number"></strong></div>
                        <div class="col-md-6"><span class="text-muted small d-block">Log Date</span><strong class="text-dark fs-5" id="v-date"></strong></div>
                        
                        <!-- Student details section header -->
                        <div class="col-12 border-bottom pb-2 mt-4"><h6 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-graduation-cap me-2"></i>Student Details</h6></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Admission No.</span><strong class="text-dark" id="v-admission"></strong></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Student Name</span><strong class="text-dark" id="v-name"></strong></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Father Name</span><strong class="text-dark" id="v-father"></strong></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Academic Type</span><span class="badge bg-secondary" id="v-type"></span></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Class</span><strong class="text-dark" id="v-class"></strong></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Section</span><strong class="text-dark" id="v-section"></strong></div>
                        
                        <!-- Complaint details header -->
                        <div class="col-12 border-bottom pb-2 mt-4"><h6 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-circle-info me-2"></i>Complaint Specifics</h6></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Category</span><span class="badge bg-primary text-wrap" id="v-category"></span></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Status</span><span class="badge" id="v-status"></span></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Created At</span><span class="text-muted small" id="v-created"></span></div>
                        <div class="col-12"><span class="text-muted small d-block">Title Summary</span><strong class="text-dark" id="v-title"></strong></div>
                        <div class="col-12">
                            <span class="text-muted small d-block mb-1">Description</span>
                            <div class="p-3 border rounded bg-light text-wrap" id="v-desc" style="min-height: 80px; max-height: 250px; overflow-y: auto;"></div>
                        </div>
                        <div class="col-12">
                            <span class="text-muted small d-block mb-1">Action Taken</span>
                            <div class="p-3 border rounded bg-light text-wrap text-success" id="v-action-taken" style="min-height: 50px;"></div>
                        </div>
                        <div class="col-12">
                            <span class="text-muted small d-block mb-1">Remarks</span>
                            <div class="p-3 border rounded bg-light text-wrap text-muted" id="v-remarks" style="min-height: 50px;"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" onclick="printSingleArea()"><i class="fa-solid fa-print me-2"></i>Print File</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Delete Confirmation -->
    <div class="modal fade" id="deleteComplaintModal" tabindex="-1" aria-labelledby="deleteComplaintModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius:12px;">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger" id="deleteComplaintModalLabel"><i class="fa-solid fa-circle-exclamation me-2"></i>Delete Complaint record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p>Are you sure you want to permanently delete this student complaint file?</p>
                    <p class="text-muted small mb-0"><i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i>This action cannot be undone and will delete this complaint log history permanently.</p>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <form method="POST" action="complaint.php">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="complaint_id" id="delete-complaint-id" value="">
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
        document.getElementById("fatherNameInputNew").value = selectedOption.getAttribute("data-father") || "—";
        document.getElementById("academicTypeInputNew").value = selectedOption.getAttribute("data-type") || "—";
        document.getElementById("classInputNew").value = selectedOption.getAttribute("data-class") || "—";
        document.getElementById("sectionInputNew").value = selectedOption.getAttribute("data-section") || "—";
    }

    const viewModal = new bootstrap.Modal(document.getElementById("viewComplaintModal"));
    function viewComplaintDetails(d) {
        document.getElementById("v-number").textContent = d.complaint_number;
        document.getElementById("v-date").textContent = d.complaint_date;
        document.getElementById("v-admission").textContent = d.admission_no;
        document.getElementById("v-name").textContent = d.first_name + ' ' + d.last_name;
        document.getElementById("v-father").textContent = d.father_name ? d.father_name : '—';
        document.getElementById("v-class").textContent = d.class_name ? d.class_name : (d.school_class ? d.school_class : '—');
        document.getElementById("v-section").textContent = d.section ? d.section : (d.school_section ? d.school_section : '—');
        document.getElementById("v-title").textContent = d.title;
        document.getElementById("v-desc").textContent = d.description;
        document.getElementById("v-action-taken").textContent = d.action_taken ? d.action_taken : 'No action recorded yet.';
        document.getElementById("v-remarks").textContent = d.remarks ? d.remarks : '—';
        document.getElementById("v-created").textContent = d.created_at;

        // badges
        const typeBadge = document.getElementById("v-type");
        typeBadge.textContent = d.academic_type;
        typeBadge.className = "badge " + (d.academic_type === 'Academy' ? 'bg-success' : 'bg-primary');

        const cat = document.getElementById("v-category");
        cat.textContent = d.category;

        const stat = document.getElementById("v-status");
        stat.textContent = d.status;
        stat.className = "badge " + (d.status === 'Pending' ? 'bg-warning text-dark' : (d.status === 'In Progress' ? 'bg-info text-dark' : (d.status === 'Resolved' ? 'bg-success' : 'bg-dark')));

        viewModal.show();
    }

    function printSingleArea() {
        const printContent = document.getElementById("printableSingleArea").innerHTML;
        const originalContent = document.body.innerHTML;
        
        document.body.innerHTML = `
            <div style="padding:40px; font-family: sans-serif;">
                <h2 style="text-align:center; margin-bottom: 2px;">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
                <h4 style="text-align:center; color: #555; border-bottom: 2px solid #333; padding-bottom: 10px; margin-top: 0;">Student Complaint Dossier Record</h4>
                ${printContent}
            </div>
        `;
        
        window.print();
        document.body.innerHTML = originalContent;
        window.location.reload();
    }

    const delModal = new bootstrap.Modal(document.getElementById("deleteComplaintModal"));
    function triggerDelete(id) {
        document.getElementById("delete-complaint-id").value = id;
        delModal.show();
    }

    // Form validation loader triggers
    document.getElementById("createComplaintForm").addEventListener("submit", function(e) {
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

    // Auto navigate to create tab if requested by hash link
    document.addEventListener("DOMContentLoaded", () => {
        if (window.location.hash === "#create-tab") {
            const trigger = document.getElementById("create-tab-btn");
            if (trigger) trigger.click();
        }
    });
    </script>
<?php endif; ?>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
