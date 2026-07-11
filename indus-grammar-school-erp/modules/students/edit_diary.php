<?php
/**
 * Indus Grammar School ERP - Edit Diary & Edit History Management
 * Version 1.0.0
 */

$pageTitle = 'Edit Diary';
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

// Handle POST operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

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

            // Retrieve old values to log history
            $stmtOld = $db->prepare("SELECT title, description FROM daily_diaries WHERE id = ?");
            $stmtOld->execute([$diary_id]);
            $oldDiary = $stmtOld->fetch();

            if ($oldDiary) {
                $db->beginTransaction();

                // Update the diary
                $stmtUp = $db->prepare("
                    UPDATE daily_diaries SET 
                        class_id = :cid, subject_id = :sid, teacher_id = :tid, diary_date = :ddate,
                        title = :title, description = :descr, is_published = :pub
                    WHERE id = :id
                ");
                $stmtUp->execute([
                    'cid' => $class_id,
                    'sid' => $subject_id,
                    'tid' => $teacher_id,
                    'ddate' => $diary_date,
                    'title' => $title,
                    'descr' => $description,
                    'pub' => $is_published,
                    'id' => $diary_id
                ]);

                // Insert into edit history table if any field changed
                if ($oldDiary['title'] !== $title || $oldDiary['description'] !== $description) {
                    $stmtHist = $db->prepare("
                        INSERT INTO diary_edit_history (diary_id, edited_by, old_title, new_title, old_description, new_description)
                        VALUES (:did, :uid, :old_title, :new_title, :old_desc, :new_desc)
                    ");
                    $stmtHist->execute([
                        'did' => $diary_id,
                        'uid' => $_SESSION['user_id'] ?? 1,
                        'old_title' => $oldDiary['title'],
                        'new_title' => $title,
                        'old_desc' => $oldDiary['description'],
                        'new_desc' => $description
                    ]);
                }

                $db->commit();
                $message = "Diary entry updated successfully with edit history logged!";
            } else {
                throw new Exception("Diary entry not found.");
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
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
$search_date = sanitize($_GET['search_date'] ?? '');
$search_teacher = (int)($_GET['search_teacher'] ?? 0);
$search_class = (int)($_GET['search_class'] ?? 0);
$search_section = sanitize($_GET['search_section'] ?? '');
$search_subject = (int)($_GET['search_subject'] ?? 0);

// Load all matching diaries
$diaries = [];
try {
    $sql = "
        SELECT d.*, c.class_name, c.section, s.subject_name, u.username as teacher_name
        FROM daily_diaries d
        JOIN classes c ON d.class_id = c.id
        JOIN subjects s ON d.subject_id = s.id
        JOIN users u ON d.teacher_id = u.id
        WHERE 1=1
    ";
    $params = [];

    if ($search_date) {
        $sql .= " AND d.diary_date = :diary_date";
        $params['diary_date'] = $search_date;
    }
    if ($search_teacher > 0) {
        $sql .= " AND d.teacher_id = :teacher_id";
        $params['teacher_id'] = $search_teacher;
    }
    if ($search_class > 0) {
        $sql .= " AND d.class_id = :class_id";
        $params['class_id'] = $search_class;
    }
    if ($search_section) {
        $sql .= " AND c.section = :section";
        $params['section'] = $search_section;
    }
    if ($search_subject > 0) {
        $sql .= " AND d.subject_id = :subject_id";
        $params['subject_id'] = $search_subject;
    }

    $sql .= " ORDER BY d.diary_date DESC, d.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $diaries = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Load all diaries error: " . $e->getMessage());
}
?>

<!-- Title Header -->
<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit Diary</h3>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4"><i class="fa-solid fa-circle-check me-2"></i><?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $error; ?></div>
<?php endif; ?>

<!-- Search Card -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Filter Diary Entries</h6>
    <form method="GET" action="edit_diary.php" class="row g-3 align-items-end">
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Diary Date</label>
            <input type="date" class="form-control form-control-sm" name="search_date" value="<?php echo $search_date; ?>">
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
            <label class="form-label small fw-semibold text-muted">Section</label>
            <input type="text" class="form-control form-control-sm" name="search_section" value="<?php echo $search_section; ?>" placeholder="e.g. A">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Subject</label>
            <select class="form-select form-select-sm" name="search_subject">
                <option value="">All Subjects</option>
                <?php foreach ($subjects as $sub): ?>
                    <option value="<?php echo $sub['id']; ?>" <?php echo ($search_subject == $sub['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($sub['subject_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-sm btn-secondary w-100 py-2">Filter</button>
        </div>
    </form>
</div>

<!-- Diary Entries List -->
<div class="card border border-light shadow-sm bg-white p-4" style="border-radius:12px;">
    <h5 class="fw-bold text-secondary mb-3">Diary Roster</h5>
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Class</th>
                    <th>Subject</th>
                    <th>Title & Details</th>
                    <th>Teacher</th>
                    <th class="text-center">History</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($diaries)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No diary entries found.</td></tr>
                <?php else: foreach ($diaries as $d): ?>
                    <tr>
                        <td class="fw-bold text-dark"><?php echo date('d M Y', strtotime($d['diary_date'])); ?></td>
                        <td><?php echo sanitize($d['class_name'] . ' - ' . $d['section']); ?></td>
                        <td><?php echo sanitize($d['subject_name']); ?></td>
                        <td>
                            <div class="fw-bold"><?php echo sanitize($d['title']); ?></div>
                            <small class="text-muted d-block text-truncate" style="max-width:300px;"><?php echo sanitize($d['description']); ?></small>
                        </td>
                        <td><?php echo sanitize($d['teacher_name']); ?></td>
                        <td class="text-center">
                            <button class="btn btn-link text-info p-0" onclick="showHistory(<?php echo $d['id']; ?>)" title="View Edit History">
                                <i class="fa-solid fa-clock-rotate-left fs-5"></i>
                            </button>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $d['is_published'] ? 'success' : 'warning'; ?>-soft">
                                <?php echo $d['is_published'] ? 'Published' : 'Draft'; ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" onclick='editDiaryPost(<?php echo json_encode($d, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-edit"></i></button>
                            <button class="btn btn-sm btn-outline-danger ms-1" onclick="confirmDelete(<?php echo $d['id']; ?>)"><i class="fa-solid fa-trash-can"></i></button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-clock-rotate-left me-2 text-info"></i>Diary Change Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4">
                <div class="table-responsive">
                    <table class="table table-sm custom-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Edited By</th>
                                <th>Old Title / Details</th>
                                <th>New Title / Details</th>
                            </tr>
                        </thead>
                        <tbody id="historyRows">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modify Modal -->
<div class="modal fade" id="modifyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Modify Diary Post</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body px-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="diary_id" id="mod_id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Class & Section *</label>
                            <select class="form-select" name="class_id" id="mod_class_id" required>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Subject *</label>
                            <select class="form-select" name="subject_id" id="mod_subject_id" required>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo sanitize($s['subject_name'] . ' (' . $s['class_name'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Teacher *</label>
                            <select class="form-select" name="teacher_id" id="mod_teacher_id" required>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo sanitize($t['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Diary Date *</label>
                            <input type="date" class="form-control" name="diary_date" id="mod_date" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Diary Title *</label>
                            <input type="text" class="form-control" name="title" id="mod_title" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Details & Instructions *</label>
                            <textarea class="form-control" name="description" rows="3" id="mod_description" required></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_published" id="mod_pub">
                                <label class="form-check-label small fw-semibold text-muted" for="mod_pub">Is Published</label>
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
    <input type="hidden" name="diary_id" id="del_id">
</form>

<script>
function editDiaryPost(item) {
    document.getElementById("mod_id").value = item.id;
    document.getElementById("mod_class_id").value = item.class_id;
    document.getElementById("mod_subject_id").value = item.subject_id;
    document.getElementById("mod_teacher_id").value = item.teacher_id;
    document.getElementById("mod_date").value = item.diary_date;
    document.getElementById("mod_title").value = item.title;
    document.getElementById("mod_description").value = item.description;
    document.getElementById("mod_pub").checked = parseInt(item.is_published) === 1;
    
    new bootstrap.Modal(document.getElementById("modifyModal")).show();
}

function confirmDelete(id) {
    if (confirm("Are you sure you want to delete this diary entry permanently?\nThis action cannot be undone!")) {
        document.getElementById("del_id").value = id;
        document.getElementById("deleteForm").submit();
    }
}

// Fetch and show history logs dynamically via AJAX
function showHistory(diaryId) {
    const tbody = document.getElementById("historyRows");
    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading logs...</td></tr>';
    
    new bootstrap.Modal(document.getElementById("historyModal")).show();

    // Fetch from endpoint
    fetch('../../ajax/dashboard_ajax.php?action=diary_history&diary_id=' + diaryId)
        .then(res => res.json())
        .then(data => {
            tbody.innerHTML = '';
            if (!data.success || data.logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No modification logs recorded for this diary post.</td></tr>';
                return;
            }
            
            data.logs.forEach(log => {
                const tr = document.createElement("tr");
                tr.innerHTML = `
                    <td class="small">${log.edited_at}</td>
                    <td class="fw-semibold small">${log.username}</td>
                    <td class="small text-danger" style="max-width:200px; word-wrap:break-word;"><strong>${log.old_title}</strong><br>${log.old_description}</td>
                    <td class="small text-success" style="max-width:200px; word-wrap:break-word;"><strong>${log.new_title}</strong><br>${log.new_description}</td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(err => {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading history logs.</td></tr>';
        });
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
