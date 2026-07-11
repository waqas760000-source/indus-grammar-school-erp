<?php
/**
 * Indus Grammar School ERP - Student Complaint Management
 * Version 1.0.0
 */

$pageTitle = 'Student Complaint';
$breadcrumbActive = 'Student Registration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

$message = '';
$error = '';

// Load lookups (registered students, classes, and users)
$studentsList = [];
$classes = [];
$usersList = [];

try {
    $studentsList = $db->query("SELECT id, admission_no, first_name, last_name, class_id FROM students WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll();
    $classes = $db->query("SELECT * FROM classes ORDER BY class_name ASC")->fetchAll();
    $usersList = $db->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();
} catch (Exception $e) {}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // File upload handler
    function uploadComplaintDoc($key, &$err) {
        if (empty($_FILES[$key]['name'])) return null;
        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
        $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $err = "Invalid file type. Allowed: JPG, PNG, PDF, DOC.";
            return null;
        }
        if ($_FILES[$key]['size'] > 5 * 1024 * 1024) {
            $err = "File size limit exceeded (Max 5MB).";
            return null;
        }
        
        $targetDir = __DIR__ . '/../../uploads/complaints/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        
        $filename = 'complaint_' . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES[$key]['tmp_name'], $targetDir . $filename)) {
            return 'uploads/complaints/' . $filename;
        }
        return null;
    }

    if ($action === 'create') {
        try {
            $student_id = (int)$_POST['student_id'];
            $category = sanitize($_POST['category'] ?? 'Academic');
            $priority = sanitize($_POST['priority'] ?? 'Medium');
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $complaint_date = sanitize($_POST['complaint_date'] ?? date('Y-m-d'));
            $status = sanitize($_POST['status'] ?? 'Pending');
            $assigned_to = (int)($_POST['assigned_to'] ?? 0);
            
            if (!$student_id || !$title || !$description) {
                throw new Exception("Please select a student, title and provide a description.");
            }
            
            // Retrieve student's class
            $stmtC = $db->prepare("SELECT class_id FROM students WHERE id = ?");
            $stmtC->execute([$student_id]);
            $class_id = (int)$stmtC->fetchColumn();
            
            $fileErr = '';
            $attach = uploadComplaintDoc('attachment', $fileErr);
            if ($fileErr) throw new Exception($fileErr);
            
            $stmt = $db->prepare("
                INSERT INTO student_complaints (student_id, class_id, category, priority, title, description, complaint_date, status, assigned_to, attachment_path)
                VALUES (:sid, :cid, :cat, :prio, :title, :descr, :cdate, :stat, :assigned, :attach)
            ");
            $stmt->execute([
                'sid' => $student_id,
                'cid' => $class_id,
                'cat' => $category,
                'prio' => $priority,
                'title' => $title,
                'descr' => $description,
                'cdate' => $complaint_date,
                'stat' => $status,
                'assigned' => $assigned_to ?: null,
                'attach' => $attach ?: ''
            ]);

            // Audit log
            $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$_SESSION['user_id'] ?? null, 'Student Complaint Logged', "Complaint logged for student ID $student_id", $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

            $message = "Complaint registered successfully!";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    if ($action === 'update') {
        try {
            $complaint_id = (int)$_POST['complaint_id'];
            $status = sanitize($_POST['status']);
            $resolution = sanitize($_POST['resolution'] ?? '');
            $assigned_to = (int)($_POST['assigned_to'] ?? 0);
            
            if (!$complaint_id) throw new Exception("Invalid complaint reference ID.");
            
            $stmt = $db->prepare("
                UPDATE student_complaints SET 
                    status = :status, resolution = :resolution, assigned_to = :assigned
                WHERE id = :id
            ");
            $stmt->execute([
                'status' => $status,
                'resolution' => $resolution,
                'assigned' => $assigned_to ?: null,
                'id' => $complaint_id
            ]);
            
            $message = "Complaint log updated successfully!";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    if ($action === 'delete') {
        try {
            $complaint_id = (int)$_POST['complaint_id'];
            if ($complaint_id > 0) {
                $stmt = $db->prepare("DELETE FROM student_complaints WHERE id = ?");
                $stmt->execute([$complaint_id]);
                $message = "Complaint log deleted successfully.";
            }
        } catch (Exception $e) {
            $error = "Error deleting complaint: " . $e->getMessage();
        }
    }
}

// Handle Filters
$search_student = (int)($_GET['search_student'] ?? 0);
$search_status = sanitize($_GET['search_status'] ?? '');
$search_class = (int)($_GET['search_class'] ?? 0);
$search_date = sanitize($_GET['search_date'] ?? '');

// Build search queries
$complaints = [];
$pendingCount = 0;
$resolvedCount = 0;
$closedCount = 0;

try {
    // Basic stats count
    $pendingCount = (int)$db->query("SELECT COUNT(*) FROM student_complaints WHERE status = 'Pending'")->fetchColumn();
    $resolvedCount = (int)$db->query("SELECT COUNT(*) FROM student_complaints WHERE status = 'Resolved'")->fetchColumn();
    $closedCount = (int)$db->query("SELECT COUNT(*) FROM student_complaints WHERE status = 'Closed'")->fetchColumn();

    // Query entries
    $sql = "
        SELECT sc.*, s.first_name, s.last_name, s.admission_no, c.class_name, c.section, u.username as assigned_username
        FROM student_complaints sc
        JOIN students s ON sc.student_id = s.id
        JOIN classes c ON sc.class_id = c.id
        LEFT JOIN users u ON sc.assigned_to = u.id
        WHERE 1=1
    ";
    $params = [];
    
    if ($search_student > 0) {
        $sql .= " AND sc.student_id = :student_id";
        $params['student_id'] = $search_student;
    }
    if ($search_status) {
        $sql .= " AND sc.status = :status";
        $params['status'] = $search_status;
    }
    if ($search_class > 0) {
        $sql .= " AND sc.class_id = :class_id";
        $params['class_id'] = $search_class;
    }
    if ($search_date) {
        $sql .= " AND sc.complaint_date = :cdate";
        $params['cdate'] = $search_date;
    }
    
    $sql .= " ORDER BY sc.complaint_date DESC, sc.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Student complaints query error: " . $e->getMessage());
}
?>

<!-- Title & Action Toolbar -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-face-frown me-2 text-primary"></i>Student Complaint</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#logComplaintModal"><i class="fa-solid fa-plus me-2"></i>Log Complaint</button>
        <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Roster</button>
        <button class="btn btn-outline-success ms-1" onclick="exportComplaints()"><i class="fa-solid fa-file-excel me-2"></i>Export</button>
    </div>
</div>

<!-- Output Messages -->
<?php if ($message): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4 d-print-none"><i class="fa-solid fa-circle-check me-2"></i><?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4 d-print-none"><i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $error; ?></div>
<?php endif; ?>

<!-- Summary Report Cards (Pending, Resolved, Closed) -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border border-light shadow-sm bg-white p-4 text-center" style="border-radius:12px;">
            <span class="text-muted small fw-semibold">Pending Complaints</span>
            <h2 class="fw-bold text-warning mb-0 mt-2"><?php echo $pendingCount; ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border border-light shadow-sm bg-white p-4 text-center" style="border-radius:12px;">
            <span class="text-muted small fw-semibold">Resolved Complaints</span>
            <h2 class="fw-bold text-success mb-0 mt-2"><?php echo $resolvedCount; ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border border-light shadow-sm bg-white p-4 text-center" style="border-radius:12px;">
            <span class="text-muted small fw-semibold">Closed Complaints</span>
            <h2 class="fw-bold text-dark mb-0 mt-2"><?php echo $closedCount; ?></h2>
        </div>
    </div>
</div>

<!-- Search Filters Card -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius: 12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Filter Complaint Entries</h6>
    <form method="GET" action="complaint.php" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Search Student</label>
            <select class="form-select form-select-sm" name="search_student">
                <option value="">All Students</option>
                <?php foreach ($studentsList as $st): ?>
                    <option value="<?php echo $st['id']; ?>" <?php echo ($search_student == $st['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['admission_no'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Status</label>
            <select class="form-select form-select-sm" name="search_status">
                <option value="">All Statuses</option>
                <option value="Pending" <?php echo ($search_status === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                <option value="Investigating" <?php echo ($search_status === 'Investigating') ? 'selected' : ''; ?>>Investigating</option>
                <option value="Resolved" <?php echo ($search_status === 'Resolved') ? 'selected' : ''; ?>>Resolved</option>
                <option value="Closed" <?php echo ($search_status === 'Closed') ? 'selected' : ''; ?>>Closed</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Class</label>
            <select class="form-select form-select-sm" name="search_class">
                <option value="">All Classes</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($search_class == $c['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Complaint Date</label>
            <input type="date" class="form-control form-control-sm" name="search_date" value="<?php echo $search_date; ?>">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-sm btn-secondary w-100 py-2">Filter</button>
        </div>
    </form>
</div>

<!-- Complaint Registry List Table -->
<div class="card border border-light shadow-sm bg-white p-4" style="border-radius:12px;">
    <h5 class="fw-bold text-secondary mb-3">Complaint Logs</h5>
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle" id="complaintsTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Student Name</th>
                    <th>Class</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Title & Description</th>
                    <th>Assigned To</th>
                    <th class="text-center">Status</th>
                    <th class="text-end d-print-none">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($complaints)): ?>
                    <tr><td colspan="9" class="text-center py-4 text-muted">No student complaints found.</td></tr>
                <?php else: foreach ($complaints as $c): ?>
                    <tr>
                        <td class="fw-bold text-dark"><?php echo date('d M Y', strtotime($c['complaint_date'])); ?></td>
                        <td class="fw-semibold">
                            <?php echo sanitize($c['first_name'] . ' ' . $c['last_name']); ?>
                            <br><small class="text-muted"><?php echo sanitize($c['admission_no']); ?></small>
                        </td>
                        <td><?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?></td>
                        <td><?php echo sanitize($c['category']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo ($c['priority'] === 'Critical' || $c['priority'] === 'High') ? 'danger' : 'secondary'; ?>-soft">
                                <?php echo sanitize($c['priority']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="fw-bold"><?php echo sanitize($c['title']); ?></div>
                            <small class="text-muted d-block text-truncate" style="max-width:250px;"><?php echo sanitize($c['description']); ?></small>
                        </td>
                        <td><strong><?php echo sanitize($c['assigned_username'] ?: 'Unassigned'); ?></strong></td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo ($c['status'] === 'Resolved' || $c['status'] === 'Closed') ? 'success' : 'warning'; ?>-soft">
                                <?php echo sanitize($c['status']); ?>
                            </span>
                        </td>
                        <td class="text-end d-print-none">
                            <button class="btn btn-sm btn-outline-primary" onclick='modifyComplaint(<?php echo json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-pen-to-square me-1"></i>Resolve</button>
                            <button class="btn btn-sm btn-outline-danger ms-1" onclick="confirmDelete(<?php echo $c['id']; ?>)"><i class="fa-solid fa-trash-can"></i></button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Log Complaint Modal -->
<div class="modal fade d-print-none" id="logComplaintModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-face-frown me-2 text-danger"></i>Register Student Complaint</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body px-4">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Select Student *</label>
                            <select class="form-select" name="student_id" required>
                                <option value="">— Choose Student —</option>
                                <?php foreach ($studentsList as $st): ?>
                                    <option value="<?php echo $st['id']; ?>"><?php echo sanitize($st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['admission_no'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Complaint Category *</label>
                            <select class="form-select" name="category" required>
                                <option value="Academic">Academic</option>
                                <option value="Behavioral">Behavioral</option>
                                <option value="Fees">Fees & Accounts</option>
                                <option value="Transport">Transport</option>
                                <option value="Facilities">Facilities</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Priority *</label>
                            <select class="form-select" name="priority" required>
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Complaint Date *</label>
                            <input type="date" class="form-control" name="complaint_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Assigned Teacher</label>
                            <select class="form-select" name="assigned_to">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($usersList as $usr): ?>
                                    <option value="<?php echo $usr['id']; ?>"><?php echo sanitize($usr['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Complaint Title *</label>
                            <input type="text" class="form-control" name="title" placeholder="Brief subject/title..." required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Details & Description *</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Provide full details of behavioral/academic complaint..." required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Attach Proof File (optional)</label>
                            <input type="file" class="form-control" name="attachment">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Register</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modify Resolution Modal -->
<div class="modal fade d-print-none" id="modifyResolutionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-check-double me-2 text-success"></i>Update Complaint Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body px-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="complaint_id" id="mod_comp_id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Complaint Status *</label>
                            <select class="form-select" name="status" id="mod_status" required>
                                <option value="Pending">Pending</option>
                                <option value="Investigating">Investigating</option>
                                <option value="Resolved">Resolved</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Assigned To</label>
                            <select class="form-select" name="assigned_to" id="mod_assigned_to">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($usersList as $usr): ?>
                                    <option value="<?php echo $usr['id']; ?>"><?php echo sanitize($usr['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Resolution details</label>
                            <textarea class="form-control" name="resolution" id="mod_resolution" rows="3" placeholder="Action taken/investigation notes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="complaint_id" id="del_comp_id">
</form>

<script>
function modifyComplaint(item) {
    document.getElementById("mod_comp_id").value = item.id;
    document.getElementById("mod_status").value = item.status;
    document.getElementById("mod_assigned_to").value = item.assigned_to || "";
    document.getElementById("mod_resolution").value = item.resolution || "";
    
    new bootstrap.Modal(document.getElementById("modifyResolutionModal")).show();
}

function confirmDelete(id) {
    if (confirm("Are you sure you want to delete this complaint record permanently?")) {
        document.getElementById("del_comp_id").value = id;
        document.getElementById("deleteForm").submit();
    }
}

// Client-side CSV/Excel Exporter
function exportComplaints() {
    let csv = "Complaint Date,Student Name,Admission No,Class,Category,Priority,Title,Description,Status,Assigned To\n";
    const rows = document.querySelectorAll("#complaintsTable tbody tr");
    
    rows.forEach(tr => {
        const cols = tr.querySelectorAll("td");
        if(cols.length === 9) {
            let rowData = [];
            // Parse Student name column with inner elements
            let date = cols[0].textContent.trim();
            let rawName = cols[1].innerHTML.split("<br>");
            let name = rawName[0].trim();
            let adm = rawName[1] ? rawName[1].replace(/<[^>]*>/g, "").trim() : "";
            let cls = cols[2].textContent.trim();
            let cat = cols[3].textContent.trim();
            let prio = cols[4].textContent.trim();
            let rawTitle = cols[5].querySelector(".fw-bold").textContent.trim();
            let desc = cols[5].querySelector("small").textContent.trim();
            let assigned = cols[6].textContent.trim();
            let status = cols[7].textContent.trim();
            
            rowData.push('"' + date + '"');
            rowData.push('"' + name + '"');
            rowData.push('"' + adm + '"');
            rowData.push('"' + cls + '"');
            rowData.push('"' + cat + '"');
            rowData.push('"' + prio + '"');
            rowData.push('"' + rawTitle + '"');
            rowData.push('"' + desc.replace(/"/g, '""') + '"');
            rowData.push('"' + status + '"');
            rowData.push('"' + assigned + '"');
            
            csv += rowData.join(",") + "\n";
        }
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "student_complaints_roster.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
