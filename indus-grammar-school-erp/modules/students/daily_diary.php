<?php
/**
 * Indus Grammar School ERP - Daily Diary Management
 * Version 1.0.0
 */

$pageTitle = 'Daily Diary';
$breadcrumbActive = 'Student Registration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

$message = '';
$error = '';

// Load classes, subjects, and teachers (users)
$classes = [];
$subjects = [];
$teachers = [];

try {
    $classes = $db->query("SELECT * FROM classes ORDER BY class_name ASC")->fetchAll();
    $subjects = $db->query("SELECT s.*, c.class_name, c.section FROM subjects s JOIN classes c ON s.class_id = c.id ORDER BY s.subject_name ASC")->fetchAll();
    $teachers = $db->query("SELECT u.id, u.username, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.username ASC")->fetchAll();
} catch (Exception $e) {}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // File upload helper
    function uploadDiaryFile($key, &$err) {
        if (empty($_FILES[$key]['name'])) return null;
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
        $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $err = "Invalid file type. Allowed: PDF, Word, images, TXT.";
            return null;
        }
        
        if ($_FILES[$key]['size'] > 5 * 1024 * 1024) {
            $err = "File size limit exceeded (Max 5MB).";
            return null;
        }
        
        $targetDir = __DIR__ . '/../../uploads/diaries/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        
        $filename = 'diary_' . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES[$key]['tmp_name'], $targetDir . $filename)) {
            return 'uploads/diaries/' . $filename;
        }
        return null;
    }

    if ($action === 'create') {
        try {
            $class_id = (int)$_POST['class_id'];
            $subject_id = (int)$_POST['subject_id'];
            $teacher_id = (int)$_POST['teacher_id'];
            $diary_date = sanitize($_POST['diary_date']);
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            $is_published = isset($_POST['is_published']) ? 1 : 0;
            
            if (!$class_id || !$subject_id || !$teacher_id || !$diary_date || !$title || !$description) {
                throw new Exception("Please fill in all required fields.");
            }
            
            $fileErr = '';
            $attach = uploadDiaryFile('attachment', $fileErr);
            if ($fileErr) throw new Exception($fileErr);
            
            $stmt = $db->prepare("
                INSERT INTO daily_diaries (class_id, subject_id, teacher_id, diary_date, title, description, attachment_path, is_published)
                VALUES (:cid, :sid, :tid, :ddate, :title, :descr, :attach, :pub)
            ");
            $stmt->execute([
                'cid' => $class_id,
                'sid' => $subject_id,
                'tid' => $teacher_id,
                'ddate' => $diary_date,
                'title' => $title,
                'descr' => $description,
                'attach' => $attach ?: '',
                'pub' => $is_published
            ]);
            
            // Audit log
            $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$_SESSION['user_id'] ?? null, 'Daily Diary Created', "Diary: $title for class ID $class_id", $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

            $message = "Diary entry published successfully!";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    if ($action === 'update') {
        try {
            $diary_id = (int)$_POST['diary_id'];
            $class_id = (int)$_POST['class_id'];
            $subject_id = (int)$_POST['subject_id'];
            $teacher_id = (int)$_POST['teacher_id'];
            $diary_date = sanitize($_POST['diary_date']);
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            $is_published = isset($_POST['is_published']) ? 1 : 0;
            
            if (!$diary_id || !$class_id || !$subject_id || !$teacher_id || !$diary_date || !$title || !$description) {
                throw new Exception("Please fill in all required fields.");
            }
            
            $fileErr = '';
            $attach = uploadDiaryFile('attachment', $fileErr);
            if ($fileErr) throw new Exception($fileErr);
            
            if ($attach) {
                $stmt = $db->prepare("
                    UPDATE daily_diaries SET 
                        class_id = :cid, subject_id = :sid, teacher_id = :tid, diary_date = :ddate,
                        title = :title, description = :descr, attachment_path = :attach, is_published = :pub
                    WHERE id = :id
                ");
                $stmt->execute([
                    'cid' => $class_id,
                    'sid' => $subject_id,
                    'tid' => $teacher_id,
                    'ddate' => $diary_date,
                    'title' => $title,
                    'descr' => $description,
                    'attach' => $attach,
                    'pub' => $is_published,
                    'id' => $diary_id
                ]);
            } else {
                $stmt = $db->prepare("
                    UPDATE daily_diaries SET 
                        class_id = :cid, subject_id = :sid, teacher_id = :tid, diary_date = :ddate,
                        title = :title, description = :descr, is_published = :pub
                    WHERE id = :id
                ");
                $stmt->execute([
                    'cid' => $class_id,
                    'sid' => $subject_id,
                    'tid' => $teacher_id,
                    'ddate' => $diary_date,
                    'title' => $title,
                    'descr' => $description,
                    'pub' => $is_published,
                    'id' => $diary_id
                ]);
            }

            $message = "Diary entry updated successfully!";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    if ($action === 'delete') {
        try {
            $diary_id = (int)$_POST['diary_id'];
            if ($diary_id > 0) {
                $stmt = $db->prepare("DELETE FROM daily_diaries WHERE id = ?");
                $stmt->execute([$diary_id]);
                $message = "Diary entry deleted successfully.";
            }
        } catch (Exception $e) {
            $error = "Error deleting diary: " . $e->getMessage();
        }
    }
}

// Handle Searches
$search_class = (int)($_GET['search_class'] ?? 0);
$search_section = sanitize($_GET['search_section'] ?? '');
$search_teacher = (int)($_GET['search_teacher'] ?? 0);
$search_date = sanitize($_GET['search_date'] ?? '');

// Fetch Diaries categories
$todayDiaries = [];
$prevDiaries = [];
$upcomingDiaries = [];

try {
    $where = "";
    $params = [];
    
    if ($search_class > 0) {
        $where .= " AND d.class_id = :class_id";
        $params['class_id'] = $search_class;
    }
    if ($search_section) {
        $where .= " AND c.section = :section";
        $params['section'] = $search_section;
    }
    if ($search_teacher > 0) {
        $where .= " AND d.teacher_id = :teacher_id";
        $params['teacher_id'] = $search_teacher;
    }
    if ($search_date) {
        $where .= " AND d.diary_date = :diary_date";
        $params['diary_date'] = $search_date;
    }

    // Today's
    $stmt1 = $db->prepare("
        SELECT d.*, c.class_name, c.section, s.subject_name, u.username as teacher_name
        FROM daily_diaries d
        JOIN classes c ON d.class_id = c.id
        JOIN subjects s ON d.subject_id = s.id
        JOIN users u ON d.teacher_id = u.id
        WHERE d.diary_date = CURRENT_DATE $where
        ORDER BY d.created_at DESC
    ");
    $stmt1->execute($params);
    $todayDiaries = $stmt1->fetchAll();

    // Previous
    $stmt2 = $db->prepare("
        SELECT d.*, c.class_name, c.section, s.subject_name, u.username as teacher_name
        FROM daily_diaries d
        JOIN classes c ON d.class_id = c.id
        JOIN subjects s ON d.subject_id = s.id
        JOIN users u ON d.teacher_id = u.id
        WHERE d.diary_date < CURRENT_DATE $where
        ORDER BY d.diary_date DESC, d.created_at DESC
        LIMIT 30
    ");
    $stmt2->execute($params);
    $prevDiaries = $stmt2->fetchAll();

    // Upcoming
    $stmt3 = $db->prepare("
        SELECT d.*, c.class_name, c.section, s.subject_name, u.username as teacher_name
        FROM daily_diaries d
        JOIN classes c ON d.class_id = c.id
        JOIN subjects s ON d.subject_id = s.id
        JOIN users u ON d.teacher_id = u.id
        WHERE d.diary_date > CURRENT_DATE $where
        ORDER BY d.diary_date ASC, d.created_at DESC
    ");
    $stmt3->execute($params);
    $upcomingDiaries = $stmt3->fetchAll();

} catch (Exception $e) {
    error_log("Load diaries error: " . $e->getMessage());
}
?>

<!-- Title & Action Toolbar -->
<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-book-open me-2 text-primary"></i>Daily Diary</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDiaryModal"><i class="fa-solid fa-plus me-2"></i>Publish Diary</button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4"><i class="fa-solid fa-circle-check me-2"></i><?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $error; ?></div>
<?php endif; ?>

<!-- Search Filter Card -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4" style="border-radius: 12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Filter Diaries</h6>
    <form method="GET" action="daily_diary.php" class="row g-3 align-items-end">
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
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Section</label>
            <input type="text" class="form-control form-control-sm" name="search_section" value="<?php echo $search_section; ?>" placeholder="A, B, C...">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Teacher</label>
            <select class="form-select form-select-sm" name="search_teacher">
                <option value="">All Teachers</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo ($search_teacher == $t['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($t['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Diary Date</label>
            <input type="date" class="form-control form-control-sm" name="search_date" value="<?php echo $search_date; ?>">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-sm btn-secondary w-100 py-2">Filter</button>
        </div>
    </form>
</div>

<!-- Diary Tabs (Today's, Previous, Upcoming) -->
<ul class="nav nav-tabs border-bottom-0 mb-4" id="diaryTab" role="tablist">
    <li class="nav-item">
        <button class="nav-link active fw-semibold" id="today-tab" data-bs-toggle="tab" data-bs-target="#todayPane" type="button"><i class="fa-solid fa-calendar-day me-2"></i>Today's Diary</button>
    </li>
    <li class="nav-item">
        <button class="nav-link fw-semibold" id="prev-tab" data-bs-toggle="tab" data-bs-target="#prevPane" type="button"><i class="fa-solid fa-clock-rotate-left me-2"></i>Previous Diary</button>
    </li>
    <li class="nav-item">
        <button class="nav-link fw-semibold" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcomingPane" type="button"><i class="fa-solid fa-calendar-plus me-2"></i>Upcoming Diary</button>
    </li>
</ul>

<div class="tab-content" id="diaryTabContent">
    
    <!-- Tab 1: Today's -->
    <div class="tab-pane fade show active" id="todayPane" role="tabpanel">
        <div class="card border border-light shadow-sm bg-white p-4" style="border-radius:12px;">
            <?php renderDiariesTable($todayDiaries, $teachers, $classes, $subjects); ?>
        </div>
    </div>

    <!-- Tab 2: Previous -->
    <div class="tab-pane fade" id="prevPane" role="tabpanel">
        <div class="card border border-light shadow-sm bg-white p-4" style="border-radius:12px;">
            <?php renderDiariesTable($prevDiaries, $teachers, $classes, $subjects); ?>
        </div>
    </div>

    <!-- Tab 3: Upcoming -->
    <div class="tab-pane fade" id="upcomingPane" role="tabpanel">
        <div class="card border border-light shadow-sm bg-white p-4" style="border-radius:12px;">
            <?php renderDiariesTable($upcomingDiaries, $teachers, $classes, $subjects); ?>
        </div>
    </div>

</div>

<!-- Create Diary Modal -->
<div class="modal fade" id="createDiaryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-book-open me-2 text-primary"></i>Publish Daily Diary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body px-4">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Class & Section *</label>
                            <select class="form-select" name="class_id" required>
                                <option value="">— Choose Class —</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Subject *</label>
                            <select class="form-select" name="subject_id" required>
                                <option value="">— Choose Subject —</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo sanitize($s['subject_name'] . ' (' . $s['class_name'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Teacher *</label>
                            <select class="form-select" name="teacher_id" required>
                                <option value="<?php echo $_SESSION['user_id'] ?? 0; ?>"><?php echo sanitize($_SESSION['username'] ?? 'Me'); ?></option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo sanitize($t['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Diary Date *</label>
                            <input type="date" class="form-control" name="diary_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Diary Title *</label>
                            <input type="text" class="form-control" name="title" placeholder="Homework assignment description..." required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Details & Instructions *</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Write class instructions details here..." required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Attach File (optional)</label>
                            <input type="file" class="form-control" name="attachment">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_published" id="pubSwitch" checked>
                                <label class="form-check-label small fw-semibold text-muted" for="pubSwitch">Publish Immediately</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Publish</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Diary Modal -->
<div class="modal fade" id="editDiaryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Modify Diary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body px-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="diary_id" id="edit_id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Class & Section *</label>
                            <select class="form-select" name="class_id" id="edit_class_id" required>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Subject *</label>
                            <select class="form-select" name="subject_id" id="edit_subject_id" required>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo sanitize($s['subject_name'] . ' (' . $s['class_name'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Teacher *</label>
                            <select class="form-select" name="teacher_id" id="edit_teacher_id" required>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo sanitize($t['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Diary Date *</label>
                            <input type="date" class="form-control" name="diary_date" id="edit_date" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Diary Title *</label>
                            <input type="text" class="form-control" name="title" id="edit_title" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Details & Instructions *</label>
                            <textarea class="form-control" name="description" rows="3" id="edit_description" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Replace File Attachment (optional)</label>
                            <input type="file" class="form-control" name="attachment">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_published" id="edit_pub" checked>
                                <label class="form-check-label small fw-semibold text-muted" for="edit_pub">Is Published</label>
                            </div>
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
    <input type="hidden" name="diary_id" id="delete_diary_id">
</form>

<script>
function editDiary(item) {
    document.getElementById("edit_id").value = item.id;
    document.getElementById("edit_class_id").value = item.class_id;
    document.getElementById("edit_subject_id").value = item.subject_id;
    document.getElementById("edit_teacher_id").value = item.teacher_id;
    document.getElementById("edit_date").value = item.diary_date;
    document.getElementById("edit_title").value = item.title;
    document.getElementById("edit_description").value = item.description;
    document.getElementById("edit_pub").checked = parseInt(item.is_published) === 1;
    
    new bootstrap.Modal(document.getElementById("editDiaryModal")).show();
}

function confirmDeleteDiary(id) {
    if (confirm("Are you sure you want to delete this diary post permanently?")) {
        document.getElementById("delete_diary_id").value = id;
        document.getElementById("deleteForm").submit();
    }
}
</script>

<?php
// Function helper to render table lists
function renderDiariesTable($diaries, $teachers, $classes, $subjects) {
    if (empty($diaries)) {
        echo '<div class="text-center py-4 text-muted"><i class="fa-solid fa-folder-open fs-2 mb-2 d-block"></i>No diaries found for this date status range.</div>';
        return;
    }
    ?>
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Class</th>
                    <th>Subject</th>
                    <th>Diary Title</th>
                    <th>Teacher</th>
                    <th class="text-center">Attachment</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($diaries as $d): ?>
                    <tr>
                        <td class="fw-bold"><?php echo date('d M Y', strtotime($d['diary_date'])); ?></td>
                        <td><?php echo sanitize($d['class_name'] . ' - ' . $d['section']); ?></td>
                        <td><?php echo sanitize($d['subject_name']); ?></td>
                        <td>
                            <div class="fw-semibold text-dark"><?php echo sanitize($d['title']); ?></div>
                            <small class="text-muted text-truncate d-inline-block" style="max-width: 250px;"><?php echo sanitize($d['description']); ?></small>
                        </td>
                        <td><?php echo sanitize($d['teacher_name']); ?></td>
                        <td class="text-center">
                            <?php if ($d['attachment_path']): ?>
                                <a href="<?php echo APP_URL . '/' . $d['attachment_path']; ?>" target="_blank" class="text-primary" title="Download attachment"><i class="fa-solid fa-paperclip fs-5"></i></a>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $d['is_published'] ? 'success' : 'warning'; ?>-soft">
                                <?php echo $d['is_published'] ? 'Published' : 'Draft'; ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" onclick='editDiary(<?php echo json_encode($d, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-edit"></i></button>
                            <button class="btn btn-sm btn-outline-danger ms-1" onclick="confirmDeleteDiary(<?php echo $d['id']; ?>)"><i class="fa-solid fa-trash-can"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

include_once __DIR__ . '/../../includes/footer.php';
?>
