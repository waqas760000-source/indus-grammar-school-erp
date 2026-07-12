<?php
/**
 * Indus Grammar School ERP - Student Attendance Marking Submodule
 * Version 3.0.0
 */

// 1. Bootstrap App & Authorization Check
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('attendance_mark');

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

// Retrieve filter selections
$selectedDate = sanitize($_GET['date'] ?? date('Y-m-d'));
$academic_type = sanitize($_GET['academic_type'] ?? 'School');
$class_name = sanitize($_GET['class'] ?? '');
$section_name = sanitize($_GET['section'] ?? '');

// Fetch unique class names and sections dynamically from classes table
$classesList = [];
$sectionsList = [];
try {
    $classesList = $db->query("SELECT DISTINCT class_name FROM classes ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
    $sectionsList = $db->query("SELECT DISTINCT section FROM classes ORDER BY section ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error fetching classes/sections in student.php: " . $e->getMessage());
}

if (empty($classesList)) {
    $classesList = ['Playgroup', 'Nursery', 'Prep', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10'];
}
if (empty($sectionsList)) {
    $sectionsList = ['A', 'B', 'C', 'D'];
}

$students = [];
$alreadyMarked = false;

// 2. Handle POST Request to Save/Update Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    try {
        $postDate = sanitize($_POST['date'] ?? date('Y-m-d'));
        $postType = sanitize($_POST['academic_type'] ?? 'School');
        $postClass = sanitize($_POST['class'] ?? '');
        $postSection = sanitize($_POST['section'] ?? '');
        $records = $_POST['records'] ?? [];
        $markedBy = $_SESSION['user_id'] ?? null;

        if (empty($postClass) || empty($postSection)) {
            throw new Exception("Class and Section are required to save attendance.");
        }

        // Look up class_id from classes database
        $stmtCls = $db->prepare("SELECT id FROM classes WHERE class_name = ? AND section = ?");
        $stmtCls->execute([$postClass, $postSection]);
        $class_id = $stmtCls->fetchColumn();
        if (!$class_id) {
            $stmtInsertCls = $db->prepare("INSERT INTO classes (class_name, section) VALUES (?, ?)");
            $stmtInsertCls->execute([$postClass, $postSection]);
            $class_id = (int)$db->lastInsertId();
        }

        if (empty($records)) {
            throw new Exception("No student attendance rows detected.");
        }

        $db->beginTransaction();

        $successCount = 0;
        foreach ($records as $r) {
            $studentId = (int)($r['student_id'] ?? 0);
            $status = sanitize($r['status'] ?? 'Present');
            $remarks = sanitize($r['remarks'] ?? '');

            if ($studentId <= 0) continue;

            // Save / Update Attendance
            $stmtMark = $db->prepare("
                INSERT INTO attendance (student_id, class_id, date, status, remarks, marked_by)
                VALUES (:student_id, :class_id, :date, :status, :remarks, :marked_by)
                ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), marked_by = VALUES(marked_by)
            ");
            $stmtMark->execute([
                'student_id' => $studentId,
                'class_id' => $class_id,
                'date' => $postDate,
                'status' => $status,
                'remarks' => $remarks ?: null,
                'marked_by' => $markedBy
            ]);
            $successCount++;
        }

        // Log audit trail
        $logDesc = "Marked Attendance for Class $postClass ($postSection) on $postDate ($successCount students)";
        $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmtLog->execute([$markedBy, 'Attendance Saved', $logDesc, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

        $db->commit();
        $_SESSION['flash_success'] = "Attendance saved successfully for $successCount students.";
        header("Location: student.php?date=$postDate&academic_type=$postType&class=" . urlencode($postClass) . "&section=" . urlencode($postSection));
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION['flash_error'] = $e->getMessage();
        header("Location: student.php?date=$selectedDate&academic_type=$academic_type&class=" . urlencode($class_name) . "&section=" . urlencode($section_name));
        exit;
    }
}

// 3. Load students list if Class and Section filters are set
if (!empty($class_name) && !empty($section_name)) {
    try {
        // Look up class_id from classes lookup database
        $stmtCls = $db->prepare("SELECT id FROM classes WHERE class_name = ? AND section = ?");
        $stmtCls->execute([$class_name, $section_name]);
        $class_id = $stmtCls->fetchColumn();

        $qSql = "
            SELECT s.id, s.admission_no, s.first_name, s.last_name, s.status as student_status,
                   d.roll_no, d.father_name, d.doc_student_photo,
                   a.status as current_status, a.remarks as current_remarks
            FROM students s
            LEFT JOIN student_registration_details d ON s.id = d.student_id
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN attendance a ON a.student_id = s.id AND a.date = :date
            WHERE s.status = 'Active' 
              AND s.academic_type = :academic_type 
              AND (c.class_name = :class OR s.school_class = :class)
              AND (c.section = :section OR s.school_section = :section)
            ORDER BY s.admission_no ASC
        ";
        
        $stmt = $db->prepare($qSql);
        $stmt->execute([
            'date' => $selectedDate,
            'academic_type' => $academic_type,
            'class' => $class_name,
            'section' => $section_name
        ]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if already marked
        foreach ($students as $st) {
            if ($st['current_status'] !== null) {
                $alreadyMarked = true;
                break;
            }
        }
    } catch (Exception $e) {
        $error = "Error querying Student List: " . $e->getMessage();
    }
}

// Calculate Top Summary counts for active load list
$totalStudents = count($students);
$presentCount = 0;
$absentCount = 0;
$leaveCount = 0;
$lateCount = 0;

foreach ($students as $st) {
    $cur = $st['current_status'] ?? 'Present';
    if ($cur === 'Present') $presentCount++;
    elseif ($cur === 'Absent') $absentCount++;
    elseif ($cur === 'Leave') $leaveCount++;
    elseif ($cur === 'Late') $lateCount++;
}

// Layout Header
$pageTitle = 'Mark Student Attendance';
$breadcrumbActive = 'Attendance';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Title Banner -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-calendar-check me-2 text-primary"></i>Mark Attendance</h3>
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

<!-- Filters Form -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Select Placement & Date</h6>
    <form method="GET" action="student.php" id="filterForm" class="row g-3">
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Attendance Date</label>
            <input type="date" class="form-control form-control-sm" name="date" value="<?php echo $selectedDate; ?>" max="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Academic Type</label>
            <select class="form-select form-select-sm" name="academic_type" required>
                <option value="School" <?php echo ($academic_type === 'School') ? 'selected' : ''; ?>>School</option>
                <option value="Academy" <?php echo ($academic_type === 'Academy') ? 'selected' : ''; ?>>Academy</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Class</label>
            <select class="form-select form-select-sm" name="class" required>
                <option value="">— Select Class —</option>
                <?php foreach ($classesList as $cls): ?>
                    <option value="<?php echo $cls; ?>" <?php echo ($class_name === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Section</label>
            <select class="form-select form-select-sm" name="section" required>
                <option value="">— Select Section —</option>
                <?php foreach ($sectionsList as $sec): ?>
                    <option value="<?php echo $sec; ?>" <?php echo ($section_name === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 align-self-end text-end mt-4">
            <button type="submit" id="loadBtn" class="btn btn-primary btn-sm w-100 py-2"><i class="fa-solid fa-users me-2"></i>Load Students</button>
        </div>
    </form>
</div>

<!-- Print Report Header (Visible in print layout ONLY) -->
<div class="d-none d-print-block text-center mb-4">
    <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL & ACADEMY</h2>
    <h5 class="text-secondary mb-3">Daily Attendance Marking Log</h5>
    <div class="small text-muted border-top border-bottom py-2">
        Date: <strong><?php echo date('M d, Y', strtotime($selectedDate)); ?></strong> |
        Class: <strong><?php echo htmlspecialchars($class_name . ' - ' . $section_name); ?></strong> |
        Academic Type: <strong><?php echo htmlspecialchars($academic_type); ?></strong>
    </div>
</div>

<?php if (!empty($class_name) && !empty($section_name)): ?>
    
    <!-- Top Summary Cards Section -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center bg-white" style="border-radius:12px; border-left: 4px solid #1e3a8a !important;">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Total Students</span>
                    <h3 class="fw-bold text-dark mb-0" id="stat-total"><?php echo $totalStudents; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center bg-white" style="border-radius:12px; border-left: 4px solid #28a745 !important;">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Present</span>
                    <h3 class="fw-bold text-dark mb-0" id="stat-present"><?php echo $presentCount; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm text-center bg-white" style="border-radius:12px; border-left: 4px solid #dc3545 !important;">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Absent</span>
                    <h3 class="fw-bold text-dark mb-0" id="stat-absent"><?php echo $absentCount; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm text-center bg-white" style="border-radius:12px; border-left: 4px solid #ffc107 !important;">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Late</span>
                    <h3 class="fw-bold text-dark mb-0" id="stat-late"><?php echo $lateCount; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm text-center bg-white" style="border-radius:12px; border-left: 4px solid #6c757d !important;">
                <div class="card-body p-3">
                    <span class="text-muted small d-block mb-1">Leave</span>
                    <h3 class="fw-bold text-dark mb-0" id="stat-leave"><?php echo $leaveCount; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Form -->
    <form id="attendanceForm" method="POST" action="student.php">
        <input type="hidden" name="action" value="save_attendance">
        <input type="hidden" name="date" value="<?php echo $selectedDate; ?>">
        <input type="hidden" name="academic_type" value="<?php echo $academic_type; ?>">
        <input type="hidden" name="class" value="<?php echo $class_name; ?>">
        <input type="hidden" name="section" value="<?php echo $section_name; ?>">

        <!-- Batch commands bar (Hidden in Print) -->
        <div class="card border-0 shadow-sm mb-3 bg-white d-print-none" style="border-radius: 12px;">
            <div class="card-body p-3 d-flex flex-wrap gap-2 align-items-center">
                <span class="text-muted small fw-semibold me-3">Mark All List As:</span>
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="markAllStatus('Present')"><i class="fa-solid fa-check me-1"></i>Present</button>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="markAllStatus('Absent')"><i class="fa-solid fa-times me-1"></i>Absent</button>
                <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3" onclick="markAllStatus('Late')"><i class="fa-solid fa-clock me-1"></i>Late</button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="markAllStatus('Leave')"><i class="fa-solid fa-plane-departure me-1"></i>Leave</button>
            </div>
        </div>

        <!-- Student list table card -->
        <div class="custom-table-card shadow-sm border-0 mb-4 bg-white" style="border-radius:12px; overflow:hidden;">
            <div class="table-responsive">
                <table class="table custom-table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th width="80">Photo</th>
                            <th>Admission No</th>
                            <th>Roll No</th>
                            <th>Student Name</th>
                            <th>Father Name</th>
                            <th>Status</th>
                            <th width="300">Attendance Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No students found.</td>
                            </tr>
                        <?php else: foreach ($students as $idx => $st): 
                            $statusVal = $st['current_status'] ?? 'Present';
                        ?>
                            <tr>
                                <td>
                                    <div class="avatar-small border rounded-circle d-flex align-items-center justify-content-center bg-light" style="width: 38px; height: 38px; overflow:hidden;">
                                        <?php if (!empty($st['doc_student_photo'])): ?>
                                            <img src="<?php echo APP_URL . '/' . $st['doc_student_photo']; ?>" style="width:100%; height:100%; object-fit:cover;">
                                        <?php else: ?>
                                            <i class="fa-solid fa-user text-muted small"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong class="text-primary"><?php echo sanitize($st['admission_no']); ?></strong></td>
                                <td><span class="small font-monospace text-secondary"><?php echo displayValue($st['roll_no']); ?></span></td>
                                <td class="fw-bold"><?php echo sanitize($st['first_name'] . ' ' . $st['last_name']); ?></td>
                                <td><?php echo displayValue($st['father_name']); ?></td>
                                <td><span class="badge badge-soft-success"><?php echo sanitize($st['student_status']); ?></span></td>
                                
                                <td>
                                    <!-- Embedded input student references -->
                                    <input type="hidden" name="records[<?php echo $idx; ?>][student_id]" value="<?php echo $st['id']; ?>">
                                    
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input att-radio-btn" type="radio" name="records[<?php echo $idx; ?>][status]" id="p_<?php echo $st['id']; ?>" value="Present" <?php echo ($statusVal === 'Present') ? 'checked' : ''; ?> onchange="calculateRealtimeStats()">
                                            <label class="form-check-label text-success fw-bold" for="p_<?php echo $st['id']; ?>">Present</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input att-radio-btn" type="radio" name="records[<?php echo $idx; ?>][status]" id="a_<?php echo $st['id']; ?>" value="Absent" <?php echo ($statusVal === 'Absent') ? 'checked' : ''; ?> onchange="calculateRealtimeStats()">
                                            <label class="form-check-label text-danger fw-bold" for="a_<?php echo $st['id']; ?>">Absent</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input att-radio-btn" type="radio" name="records[<?php echo $idx; ?>][status]" id="l_<?php echo $st['id']; ?>" value="Late" <?php echo ($statusVal === 'Late') ? 'checked' : ''; ?> onchange="calculateRealtimeStats()">
                                            <label class="form-check-label text-warning fw-bold" for="l_<?php echo $st['id']; ?>">Late</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input att-radio-btn" type="radio" name="records[<?php echo $idx; ?>][status]" id="lv_<?php echo $st['id']; ?>" value="Leave" <?php echo ($statusVal === 'Leave') ? 'checked' : ''; ?> onchange="calculateRealtimeStats()">
                                            <label class="form-check-label text-secondary fw-bold" for="lv_<?php echo $st['id']; ?>">Leave</label>
                                        </div>
                                    </div>
                                </td>
                                
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="records[<?php echo $idx; ?>][remarks]" value="<?php echo htmlspecialchars($st['current_remarks'] ?? ''); ?>" placeholder="Remarks (optional)">
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Bottom Buttons (Hidden in print) -->
            <div class="p-4 border-top bg-light d-flex justify-content-between align-items-center d-print-none">
                <button type="button" class="btn btn-outline-secondary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Attendance Sheet</button>
                <div class="text-end">
                    <button type="submit" id="saveAttBtn" class="btn btn-primary px-4 me-1"><i class="fa-solid fa-floppy-disk me-2"></i>Save Attendance</button>
                    <a href="student.php?date=<?php echo $selectedDate; ?>&academic_type=<?php echo $academic_type; ?>&class=<?php echo urlencode($class_name); ?>&section=<?php echo urlencode($section_name); ?>" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </div>
    </form>

<?php endif; ?>

<script>
// Load button spinner
document.getElementById('filterForm').addEventListener('submit', function() {
    const btn = document.getElementById('loadBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading...';
});

// Submit button spinner
const attForm = document.getElementById('attendanceForm');
if (attForm) {
    attForm.addEventListener('submit', function() {
        const btn = document.getElementById('saveAttBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';
        }
    });
}

// Bulk mark list change state helper
function markAllStatus(status) {
    document.querySelectorAll(".att-radio-btn[value='" + status + "']").forEach(radio => {
        radio.checked = true;
    });
    calculateRealtimeStats();
}

// Re-calculate top statistics dynamically
function calculateRealtimeStats() {
    let present = 0;
    let absent = 0;
    let late = 0;
    let leave = 0;

    document.querySelectorAll(".att-radio-btn:checked").forEach(radio => {
        const val = radio.value;
        if (val === 'Present') present++;
        else if (val === 'Absent') absent++;
        else if (val === 'Late') late++;
        else if (val === 'Leave') leave++;
    });

    const presentEl = document.getElementById('stat-present');
    const absentEl = document.getElementById('stat-absent');
    const lateEl = document.getElementById('stat-late');
    const leaveEl = document.getElementById('stat-leave');

    if (presentEl) presentEl.textContent = present;
    if (absentEl) absentEl.textContent = absent;
    if (lateEl) lateEl.textContent = late;
    if (leaveEl) leaveEl.textContent = leave;
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
