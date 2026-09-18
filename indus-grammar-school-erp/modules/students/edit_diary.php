<?php
/**
 * Indus Grammar School ERP - Edit Diary Management Module
 * Version 4.0.0 (Premium UI Redesign)
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

// Check if a specific record ID is loaded for editing/updating
$diaryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$diary = null;

if ($diaryId > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM daily_diaries WHERE id = ?");
        $stmt->execute([$diaryId]);
        $diary = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$diary) {
            $error = "Diary entry record not found.";
            $diaryId = 0;
        }
    } catch (Exception $e) {
        $error = "Error loading diary record: " . $e->getMessage();
        $diaryId = 0;
    }
}

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

// 2. Handle POST Request Actions (Update / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    
    // UPDATE ACTION
    if ($action === 'update') {
        try {
            $targetId = (int)($_POST['diary_id'] ?? 0);
            if ($targetId <= 0) throw new Exception("Invalid diary entry ID reference.");

            $diary_date = sanitize($_POST['diary_date'] ?? date('Y-m-d'));
            $academic_type = sanitize($_POST['academic_type'] ?? 'School');
            $class_name = sanitize($_POST['class'] ?? '');
            $section_name = sanitize($_POST['section'] ?? '');
            $subject = sanitize($_POST['subject'] ?? '');
            $diary_type = sanitize($_POST['diary_type'] ?? 'Homework');
            $title = sanitize($_POST['title'] ?? '');
            $description = $_POST['description'] ?? '';
            $status = sanitize($_POST['status'] ?? 'Active');

            if (empty($class_name) || empty($section_name) || empty($subject) || empty($diary_type) || empty($title) || empty($description)) {
                throw new Exception("Please fill in all required fields indicated by *.");
            }

            // Fetch old record details
            $stmtOld = $db->prepare("SELECT * FROM daily_diaries WHERE id = ?");
            $stmtOld->execute([$targetId]);
            $oldDiary = $stmtOld->fetch(PDO::FETCH_ASSOC);
            if (!$oldDiary) throw new Exception("Diary entry not found in database.");

            // Handle attachment file upload replacement
            $attachmentPath = $oldDiary['attachment'];
            if (!empty($_FILES['attachment']['name'])) {
                $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) {
                    throw new Exception("Invalid file extension. Allowed formats: PDF, Word (DOC/DOCX), Images.");
                }
                if ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
                    throw new Exception("File size limit exceeded. Max allowed: 5MB.");
                }

                // Unlink old attachment file if exists
                if (!empty($oldDiary['attachment']) && file_exists(__DIR__ . '/../../' . $oldDiary['attachment'])) {
                    unlink(__DIR__ . '/../../' . $oldDiary['attachment']);
                }

                $targetDir = __DIR__ . '/../../uploads/diaries/';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                $filename = 'diary_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetDir . $filename)) {
                    $attachmentPath = 'uploads/diaries/' . $filename;
                } else {
                    throw new Exception("Failed to upload attachment file.");
                }
            }

            // Find or Insert class/section in classes lookup
            $stmtCls = $db->prepare("SELECT id FROM classes WHERE class_name = ? AND section = ?");
            $stmtCls->execute([$class_name, $section_name]);
            $class_id = $stmtCls->fetchColumn();
            if (!$class_id) {
                $stmtInsertCls = $db->prepare("INSERT INTO classes (class_name, section) VALUES (?, ?)");
                $stmtInsertCls->execute([$class_name, $section_name]);
                $class_id = (int)$db->lastInsertId();
            }

            // Update database entry
            $stmtUp = $db->prepare("
                UPDATE daily_diaries SET 
                    class_id = :class_id, diary_date = :diary_date, academic_type = :academic_type,
                    class = :class, section = :section, subject = :subject, diary_type = :diary_type,
                    title = :title, description = :description, attachment = :attachment, status = :status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmtUp->execute([
                'class_id' => $class_id,
                'diary_date' => $diary_date,
                'academic_type' => $academic_type,
                'class' => $class_name,
                'section' => $section_name,
                'subject' => $subject,
                'diary_type' => $diary_type,
                'title' => $title,
                'description' => $description,
                'attachment' => $attachmentPath,
                'status' => $status,
                'id' => $targetId
            ]);

            // Save history log
            if ($oldDiary['title'] !== $title || $oldDiary['description'] !== $description) {
                $stmtHist = $db->prepare("
                    INSERT INTO diary_edit_history (diary_id, edited_by, old_title, new_title, old_description, new_description)
                    VALUES (:did, :uid, :old_title, :new_title, :old_desc, :new_desc)
                ");
                $stmtHist->execute([
                    'did' => $targetId,
                    'uid' => $_SESSION['user_id'] ?? null,
                    'old_title' => $oldDiary['title'],
                    'new_title' => $title,
                    'old_desc' => $oldDiary['description'],
                    'new_desc' => $description
                ]);
            }

            // Audit log
            $logDesc = "Edited & Updated Diary ID $targetId: $title | Class: $class_name";
            $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$_SESSION['user_id'] ?? null, 'Diary Updated', $logDesc, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

            $_SESSION['flash_success'] = "Diary entry updated successfully!";
            header("Location: edit_diary.php");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // DELETE ACTION
    if ($action === 'delete') {
        try {
            $targetId = (int)($_POST['diary_id'] ?? 0);
            if ($targetId <= 0) throw new Exception("Invalid diary entry ID reference.");

            // Delete attachment if exists
            $stmtFile = $db->prepare("SELECT attachment, title FROM daily_diaries WHERE id = ?");
            $stmtFile->execute([$targetId]);
            $rowFile = $stmtFile->fetch(PDO::FETCH_ASSOC);

            if ($rowFile) {
                if (!empty($rowFile['attachment']) && file_exists(__DIR__ . '/../../' . $rowFile['attachment'])) {
                    unlink(__DIR__ . '/../../' . $rowFile['attachment']);
                }

                $stmtDel = $db->prepare("DELETE FROM daily_diaries WHERE id = ?");
                $stmtDel->execute([$targetId]);

                // Audit log
                $logDesc = "Deleted Diary Entry ID $targetId: {$rowFile['title']}";
                $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
                $stmtLog->execute([$_SESSION['user_id'] ?? null, 'Diary Deleted', $logDesc, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

                $_SESSION['flash_success'] = "Diary entry deleted successfully.";
            }
            header("Location: edit_diary.php");
            exit;
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Error deleting diary: " . $e->getMessage();
            header("Location: edit_diary.php");
            exit;
        }
    }
}

// 3. Search & Filters variables
$search_date = sanitize($_GET['search_date'] ?? '');
$search_academic_type = sanitize($_GET['search_academic_type'] ?? '');
$search_class = sanitize($_GET['search_class'] ?? '');
$search_section = sanitize($_GET['search_section'] ?? '');
$search_subject = sanitize($_GET['search_subject'] ?? '');
$search_diary_type = sanitize($_GET['search_diary_type'] ?? '');
$search_status = sanitize($_GET['search_status'] ?? '');

$limit = 10;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$where = " WHERE 1=1";
$params = [];

if ($search_date !== '') {
    $where .= " AND d.diary_date = :search_date";
    $params['search_date'] = $search_date;
}
if ($search_academic_type !== '') {
    $where .= " AND d.academic_type = :search_academic_type";
    $params['search_academic_type'] = $search_academic_type;
}
if ($search_class !== '') {
    $where .= " AND d.class = :search_class";
    $params['search_class'] = $search_class;
}
if ($search_section !== '') {
    $where .= " AND d.section = :search_section";
    $params['search_section'] = $search_section;
}
if ($search_subject !== '') {
    $where .= " AND d.subject LIKE :search_subject";
    $params['search_subject'] = '%' . $search_subject . '%';
}
if ($search_diary_type !== '') {
    $where .= " AND d.diary_type = :search_diary_type";
    $params['search_diary_type'] = $search_diary_type;
}
if ($search_status !== '') {
    $where .= " AND d.status = :search_status";
    $params['search_status'] = $search_status;
}

$diaries = [];
$totalEntries = 0;

try {
    // Total count query
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM daily_diaries d $where");
    $stmtCount->execute($params);
    $totalEntries = (int)$stmtCount->fetchColumn();

    // Data query
    $stmtData = $db->prepare("
        SELECT d.*, u.username as creator_name 
        FROM daily_diaries d 
        LEFT JOIN users u ON d.created_by = u.id 
        $where 
        ORDER BY d.diary_date DESC, d.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($params as $k => $v) {
        $stmtData->bindValue($k, $v);
    }
    $stmtData->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    
    $diaries = $stmtData->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Edit Diary search list query error: " . $e->getMessage());
}

$totalPages = ceil($totalEntries / $limit);
if ($totalPages < 1) $totalPages = 1;

// Load Layout Headers
$pageTitle = ($diaryId > 0) ? 'Modify Diary Record' : 'Edit Diary Panel';
$breadcrumbActive = 'Edit Diary';
include_once __DIR__ . '/../../includes/header.php';
?>

<!-- Custom Premium Styling -->
<style>
:root {
    --erp-navy: #0F172A;
    --erp-blue: #1D4ED8;
    --erp-light-bg: #F8FAFC;
    --erp-card-bg: #FFFFFF;
    --erp-border: #E2E8F0;
    --erp-text-dark: #1E293B;
    --erp-text-muted: #64748B;
}

.diary-hero-card {
    background: linear-gradient(135deg, #0F172A 0%, #1E293B 55%, #1D4ED8 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 24px 30px;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15);
}

.form-card-premium {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
}

.form-section-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: #1D4ED8;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding-bottom: 8px;
    border-bottom: 2px solid #F1F5F9;
    margin-bottom: 18px;
}

.guidelines-card {
    background: #F8FAFC;
    border: 1px dashed #CBD5E1;
    border-radius: 14px;
    padding: 22px;
}

.table-custom-premium {
    border-collapse: separate;
    border-spacing: 0;
}

.table-custom-premium thead th {
    background-color: #F8FAFC;
    color: #475569;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 14px 16px;
    border-bottom: 2px solid #E2E8F0;
}

.table-custom-premium tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid #F1F5F9;
    vertical-align: middle;
}
</style>

<!-- Hero Title Banner -->
<div class="diary-hero-card mb-4 d-print-none">
    <div class="row align-items-center">
        <div class="col-lg-8 mb-3 mb-lg-0">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white bg-opacity-10 p-3 rounded-3">
                    <i class="fa-solid fa-pen-to-square fs-3 text-warning"></i>
                </div>
                <div>
                    <h2 class="fw-bold mb-1 text-white"><?php echo ($diaryId > 0) ? 'Modify Diary Entry #' . $diaryId : 'Edit Diary Dashboard'; ?></h2>
                    <p class="text-white-50 mb-0 small">Search, review, and update existing student daily diary entries</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 text-lg-end">
            <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end align-items-center">
                <span class="badge bg-white bg-opacity-10 text-white px-3 py-2 border border-white border-opacity-20 rounded-pill small">
                    <i class="fa-regular fa-calendar me-1"></i><?php echo date('M d, Y'); ?>
                </span>
                <span class="badge bg-white bg-opacity-10 text-white px-3 py-2 border border-white border-opacity-20 rounded-pill small">
                    <i class="fa-solid fa-graduation-cap me-1"></i>Session 2026-2027
                </span>
                <a href="diary_report.php?view=dashboard" class="btn btn-warning text-dark fw-bold btn-sm px-3 shadow-sm rounded-pill mt-1">
                    <i class="fa-solid fa-gauge-high me-1"></i>Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Alerts Panel -->
<?php if (!empty($message)): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-center" style="border-radius: 12px; background: #DCFCE7; color: #166534;">
        <i class="fa-solid fa-circle-check fs-5 me-2"></i>
        <div><strong>Success:</strong> <?php echo htmlspecialchars($message); ?></div>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-center" style="border-radius: 12px; background: #FEE2E2; color: #991B1B;">
        <i class="fa-solid fa-circle-xmark fs-5 me-2"></i>
        <div><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></div>
    </div>
<?php endif; ?>

<?php if ($diaryId > 0 && $diary): ?>
    <!-- 4. EDIT DIARY FORM STATE -->
    <div class="row g-4 mb-4">
        <!-- Form Column -->
        <div class="col-lg-8">
            <div class="form-card-premium p-4">
                <!-- Record Metadata Summary Badge Header -->
                <div class="p-3 bg-light rounded-3 border mb-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <span class="badge bg-primary px-3 py-2 fs-6">Editing Record #<?php echo $diary['id']; ?></span>
                        <span class="ms-2 text-muted small"><i class="fa-regular fa-calendar me-1"></i>Created: <?php echo date('M d, Y', strtotime($diary['created_at'])); ?></span>
                    </div>
                    <div>
                        <span class="badge <?php echo ($diary['status'] === 'Active') ? 'bg-success bg-opacity-10 text-success border border-success' : 'bg-secondary'; ?>">
                            Status: <?php echo sanitize($diary['status']); ?>
                        </span>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-pen me-2 text-primary"></i>Update Diary Task Details</h5>
                    <span class="badge bg-light text-muted border">* Mandatory fields</span>
                </div>

                <form id="editDiaryForm" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="diary_id" value="<?php echo $diary['id']; ?>">

                    <!-- SECTION A: ACADEMIC CONTEXT -->
                    <div class="form-section-title"><i class="fa-solid fa-graduation-cap me-2"></i>Section A: Academic Information</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Diary Date *</label>
                            <input type="date" class="form-control" name="diary_date" value="<?php echo $diary['diary_date']; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Academic Type *</label>
                            <select class="form-select" name="academic_type" required>
                                <option value="School" <?php echo ($diary['academic_type'] === 'School') ? 'selected' : ''; ?>>School</option>
                                <option value="Academy" <?php echo ($diary['academic_type'] === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Class *</label>
                            <select class="form-select" name="class" required>
                                <option value="">— Select Class —</option>
                                <?php foreach (['Play Group', 'Nursery', 'Prep', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10', 'Class 11', 'Class 12'] as $cls): ?>
                                    <option value="<?php echo $cls; ?>" <?php echo ($diary['class'] === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Section *</label>
                            <select class="form-select" name="section" required>
                                <option value="">— Select Section —</option>
                                <?php foreach ($sectionsList as $sec): ?>
                                    <option value="<?php echo $sec; ?>" <?php echo ($diary['section'] === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold text-muted">Subject *</label>
                            <input type="text" class="form-control" name="subject" value="<?php echo htmlspecialchars($diary['subject']); ?>" required>
                        </div>
                    </div>

                    <!-- SECTION B: DIARY CONTENT & TYPE -->
                    <div class="form-section-title"><i class="fa-solid fa-file-lines me-2"></i>Section B: Diary Content & Category</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Diary Category / Type *</label>
                            <select class="form-select" name="diary_type" required>
                                <option value="Homework" <?php echo ($diary['diary_type'] === 'Homework') ? 'selected' : ''; ?>>Homework</option>
                                <option value="Classwork" <?php echo ($diary['diary_type'] === 'Classwork') ? 'selected' : ''; ?>>Classwork</option>
                                <option value="Assignment" <?php echo ($diary['diary_type'] === 'Assignment') ? 'selected' : ''; ?>>Assignment</option>
                                <option value="Test Reminder" <?php echo ($diary['diary_type'] === 'Test Reminder') ? 'selected' : ''; ?>>Test Reminder</option>
                                <option value="General Notice" <?php echo ($diary['diary_type'] === 'General Notice') ? 'selected' : ''; ?>>General Notice</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Publish Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="Active" <?php echo ($diary['status'] === 'Active') ? 'selected' : ''; ?>>Active (Published)</option>
                                <option value="Draft" <?php echo ($diary['status'] === 'Draft') ? 'selected' : ''; ?>>Draft (Hidden)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Title / Topic Summary *</label>
                            <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($diary['title']); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Detailed Instructions & Lesson Tasks *</label>
                            <!-- CKEditor 5 Container -->
                            <textarea class="form-control" id="editor" name="description" rows="5"><?php echo htmlspecialchars($diary['description']); ?></textarea>
                        </div>
                    </div>

                    <!-- SECTION C: MEDIA & ATTACHMENTS -->
                    <div class="form-section-title"><i class="fa-solid fa-paperclip me-2"></i>Section C: File Attachment</div>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <?php if (!empty($diary['attachment'])): ?>
                                <div class="mb-3 p-3 border rounded-3 bg-light d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-file-arrow-down fs-4 text-primary"></i>
                                        <div>
                                            <strong class="d-block small text-dark"><?php echo basename($diary['attachment']); ?></strong>
                                            <span class="text-muted" style="font-size:0.75rem;">Currently uploaded file attachment</span>
                                        </div>
                                    </div>
                                    <a href="<?php echo APP_URL . '/' . $diary['attachment']; ?>" class="btn btn-sm btn-outline-primary px-3 rounded-pill" target="_blank">
                                        <i class="fa-solid fa-download me-1"></i>Download File
                                    </a>
                                </div>
                            <?php endif; ?>
                            <label class="form-label small fw-semibold text-muted">Replace Attachment File (Optional)</label>
                            <input type="file" class="form-control" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <span class="small text-muted d-block mt-2"><i class="fa-solid fa-circle-info me-1 text-primary"></i>Leave blank to retain current file. Max file size: 5MB.</span>
                        </div>
                    </div>

                    <!-- ACTION BUTTONS -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between border-top pt-3 mt-4 gap-2">
                        <a href="edit_diary.php" class="btn btn-outline-secondary btn-sm px-3">
                            <i class="fa-solid fa-arrow-left me-1"></i>Cancel & Back to List
                        </a>
                        <div class="d-flex gap-2">
                            <a href="edit_diary.php" class="btn btn-outline-secondary">Reset Changes</a>
                            <button type="submit" id="updateBtn" class="btn btn-primary px-4 fw-semibold"><i class="fa-solid fa-circle-check me-2"></i>Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Side Details Column -->
        <div class="col-lg-4">
            <div class="guidelines-card mb-4">
                <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-lightbulb text-warning me-2"></i>Editing Recommendations</h6>
                <ul class="list-unstyled mb-0 small text-muted d-flex flex-column gap-3">
                    <li class="d-flex align-items-start gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-primary mt-1"></i>
                        <div><strong>Change Audit:</strong> All modifications to titles or description tasks are automatically saved into history logs.</div>
                    </li>
                    <li class="d-flex align-items-start gap-2">
                        <i class="fa-solid fa-file-shield text-success mt-1"></i>
                        <div><strong>Attachment Safety:</strong> Uploading a new attachment file safely unlinks and replaces the old file on server storage.</div>
                    </li>
                    <li class="d-flex align-items-start gap-2">
                        <i class="fa-solid fa-lock text-info mt-1"></i>
                        <div><strong>Permissions:</strong> Edit actions require active <code>student_edit</code> authorization rights.</div>
                    </li>
                </ul>
            </div>

            <div class="card border-0 shadow-sm bg-white p-4" style="border-radius: 14px;">
                <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-bolt text-primary me-2"></i>Quick Navigation</h6>
                <div class="d-flex flex-column gap-2">
                    <a href="daily_diary.php" class="btn btn-sm btn-outline-secondary text-start p-2 px-3">
                        <i class="fa-solid fa-plus-circle me-2 text-primary"></i>Create New Daily Diary
                    </a>
                    <a href="diary_report.php" class="btn btn-sm btn-outline-secondary text-start p-2 px-3">
                        <i class="fa-solid fa-file-invoice me-2 text-info"></i>View Full Diary Report
                    </a>
                    <a href="diary_report.php?view=dashboard" class="btn btn-sm btn-outline-secondary text-start p-2 px-3">
                        <i class="fa-solid fa-gauge-high me-2 text-warning"></i>Diary Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- CKEditor 5 Setup -->
    <script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
    <script>
    ClassicEditor
        .create(document.querySelector('#editor'), {
            toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo']
        })
        .catch(error => {
            console.error(error);
        });

    document.getElementById("editDiaryForm").addEventListener("submit", function(e) {
        const form = this;
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            alert("Please fill in all required fields indicated by *.");
            form.classList.add("was-validated");
        } else {
            const btn = document.getElementById("updateBtn");
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Updating Record...';
            }
        }
    });
    </script>

<?php else: ?>
    <!-- 5. SEARCH DASHBOARD & TABLE LIST STATE -->
    
    <!-- Find Diary Entries Search Workspace Card -->
    <div class="card border-0 shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:14px;">
        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-magnifying-glass me-2 text-primary"></i>Find Diary Entries</h6>
        <form method="GET" action="edit_diary.php" class="row g-3">
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Diary Date</label>
                <input type="date" class="form-control form-control-sm" name="search_date" value="<?php echo htmlspecialchars($search_date); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Academic Type</label>
                <select class="form-select form-select-sm" name="search_academic_type">
                    <option value="">All Types</option>
                    <option value="School" <?php echo ($search_academic_type === 'School') ? 'selected' : ''; ?>>School</option>
                    <option value="Academy" <?php echo ($search_academic_type === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Class</label>
                <select class="form-select form-select-sm" name="search_class">
                    <option value="">All Classes</option>
                    <?php foreach (['Play Group', 'Nursery', 'Prep', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10', 'Class 11', 'Class 12'] as $cls): ?>
                        <option value="<?php echo $cls; ?>" <?php echo ($search_class === $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Section</label>
                <select class="form-select form-select-sm" name="search_section">
                    <option value="">All Sections</option>
                    <?php foreach ($sectionsList as $sec): ?>
                        <option value="<?php echo $sec; ?>" <?php echo ($search_section === $sec) ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Subject</label>
                <input type="text" class="form-control form-control-sm" name="search_subject" value="<?php echo htmlspecialchars($search_subject); ?>" placeholder="e.g. English">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Diary Type</label>
                <select class="form-select form-select-sm" name="search_diary_type">
                    <option value="">All Categories</option>
                    <option value="Homework" <?php echo ($search_diary_type === 'Homework') ? 'selected' : ''; ?>>Homework</option>
                    <option value="Classwork" <?php echo ($search_diary_type === 'Classwork') ? 'selected' : ''; ?>>Classwork</option>
                    <option value="Assignment" <?php echo ($search_diary_type === 'Assignment') ? 'selected' : ''; ?>>Assignment</option>
                    <option value="Test Reminder" <?php echo ($search_diary_type === 'Test Reminder') ? 'selected' : ''; ?>>Test Reminder</option>
                    <option value="General Notice" <?php echo ($search_diary_type === 'General Notice') ? 'selected' : ''; ?>>General Notice</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select class="form-select form-select-sm" name="search_status">
                    <option value="">All</option>
                    <option value="Active" <?php echo ($search_status === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Draft" <?php echo ($search_status === 'Draft') ? 'selected' : ''; ?>>Draft</option>
                </select>
            </div>
            <div class="col-md-10 text-end mt-4">
                <button type="submit" class="btn btn-sm btn-primary px-4"><i class="fa-solid fa-magnifying-glass me-2"></i>Search Entries</button>
                <a href="edit_diary.php" class="btn btn-sm btn-outline-secondary px-3">Reset Filters</a>
            </div>
        </form>
    </div>

    <!-- Diary Records Table Card -->
    <div class="card border-0 shadow-sm bg-white" style="border-radius: 14px; overflow: hidden;">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
            <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-list-check me-2 text-primary"></i>Matching Diary Records</h6>
            <span class="badge bg-light text-muted border"><?php echo number_format($totalEntries); ?> entries found</span>
        </div>
        <div class="table-responsive">
            <table class="table table-custom-premium table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Academic Type</th>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Subject</th>
                        <th>Diary Type</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Last Updated</th>
                        <th class="text-end d-print-none">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($diaries)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                <div class="py-4">
                                    <i class="fa-solid fa-book-open-reader d-block fs-1 mb-3 text-secondary opacity-50"></i>
                                    <h6 class="fw-bold text-dark">No Diary Entries Found</h6>
                                    <p class="small text-muted mb-3">No diary records match the selected search criteria.</p>
                                    <a href="edit_diary.php" class="btn btn-sm btn-outline-secondary px-3 rounded-pill">Reset Filters</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: foreach ($diaries as $d): ?>
                        <tr>
                            <td><strong class="text-primary"><?php echo date('M d, Y', strtotime($d['diary_date'])); ?></strong></td>
                            <td><span class="badge bg-light text-dark border"><?php echo sanitize($d['academic_type']); ?></span></td>
                            <td><?php echo displayValue($d['class']); ?></td>
                            <td><?php echo displayValue($d['section']); ?></td>
                            <td><strong class="text-dark"><?php echo displayValue($d['subject']); ?></strong></td>
                            <td>
                                <?php
                                $type = $d['diary_type'];
                                $badge = 'bg-secondary';
                                if ($type === 'Homework') $badge = 'bg-primary';
                                elseif ($type === 'Assignment') $badge = 'bg-info text-dark';
                                elseif ($type === 'Test Reminder') $badge = 'bg-warning text-dark';
                                elseif ($type === 'General Notice') $badge = 'bg-danger';
                                ?>
                                <span class="badge <?php echo $badge; ?>"><?php echo sanitize($type); ?></span>
                            </td>
                            <td><strong class="text-dark"><?php echo sanitize($d['title']); ?></strong></td>
                            <td>
                                <span class="badge <?php echo ($d['status'] === 'Active') ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-light text-secondary border'; ?>">
                                    <?php echo sanitize($d['status']); ?>
                                </span>
                            </td>
                            <td><span class="small text-muted"><i class="fa-regular fa-user me-1"></i><?php echo displayValue($d['creator_name']); ?></span></td>
                            <td><span class="small text-muted"><?php echo date('M d, Y h:i A', strtotime($d['updated_at'])); ?></span></td>
                            <td class="text-end d-print-none">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="viewDiaryDetails(<?php echo htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8'); ?>)" title="View details">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                    <?php if (hasPermission('student_edit')): ?>
                                        <a href="edit_diary.php?id=<?php echo $d['id']; ?>" class="btn btn-outline-primary btn-sm" title="Edit entry">
                                            <i class="fa-regular fa-pen-to-square"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('student_delete')): ?>
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="triggerDelete(<?php echo $d['id']; ?>)" title="Delete entry">
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
        <nav aria-label="Page navigation" class="mt-4 mb-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search_date ? '&search_date='.$search_date : ''; ?><?php echo $search_academic_type ? '&search_academic_type='.$search_academic_type : ''; ?><?php echo $search_class ? '&search_class='.$search_class : ''; ?><?php echo $search_section ? '&search_section='.$search_section : ''; ?><?php echo $search_subject ? '&search_subject='.$search_subject : ''; ?><?php echo $search_diary_type ? '&search_diary_type='.$search_diary_type : ''; ?><?php echo $search_status ? '&search_status='.$search_status : ''; ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search_date ? '&search_date='.$search_date : ''; ?><?php echo $search_academic_type ? '&search_academic_type='.$search_academic_type : ''; ?><?php echo $search_class ? '&search_class='.$search_class : ''; ?><?php echo $search_section ? '&search_section='.$search_section : ''; ?><?php echo $search_subject ? '&search_subject='.$search_subject : ''; ?><?php echo $search_diary_type ? '&search_diary_type='.$search_diary_type : ''; ?><?php echo $search_status ? '&search_status='.$search_status : ''; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search_date ? '&search_date='.$search_date : ''; ?><?php echo $search_academic_type ? '&search_academic_type='.$search_academic_type : ''; ?><?php echo $search_class ? '&search_class='.$search_class : ''; ?><?php echo $search_section ? '&search_section='.$search_section : ''; ?><?php echo $search_subject ? '&search_subject='.$search_subject : ''; ?><?php echo $search_diary_type ? '&search_diary_type='.$search_diary_type : ''; ?><?php echo $search_status ? '&search_status='.$search_status : ''; ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

    <!-- Modal View Details -->
    <div class="modal fade" id="viewDiaryModal" tabindex="-1" aria-labelledby="viewDiaryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius:14px;">
                <div class="modal-header bg-light border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark" id="viewDiaryModalLabel"><i class="fa-solid fa-book-open text-primary me-2"></i>Diary Entry Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="row g-3">
                        <div class="col-md-6"><span class="text-muted small d-block">Diary Date</span><strong class="text-dark" id="v-date"></strong></div>
                        <div class="col-md-6"><span class="text-muted small d-block">Academic Type</span><strong class="text-dark" id="v-type"></strong></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Class</span><strong class="text-dark" id="v-class"></strong></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Section</span><strong class="text-dark" id="v-section"></strong></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Subject</span><strong class="text-dark" id="v-subject"></strong></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Diary Type</span><span class="badge bg-primary" id="v-category"></span></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Status</span><span class="badge" id="v-status"></span></div>
                        <div class="col-md-4"><span class="text-muted small d-block">Created By</span><strong class="text-muted" id="v-author"></strong></div>
                        <div class="col-md-6"><span class="text-muted small d-block">Created Date</span><span class="text-dark" id="v-created"></span></div>
                        <div class="col-md-6"><span class="text-muted small d-block">Last Updated</span><span class="text-dark" id="v-updated"></span></div>
                        <div class="col-12"><span class="text-muted small d-block">Title</span><strong class="text-dark fs-5" id="v-title"></strong></div>
                        <div class="col-12">
                            <span class="text-muted small d-block mb-1">Description</span>
                            <div class="p-3 border rounded bg-light" id="v-desc" style="min-height: 100px; max-height: 400px; overflow-y: auto;"></div>
                        </div>
                        <div class="col-12" id="v-attachment-row">
                            <span class="text-muted small d-block mb-1">Attachment File</span>
                            <a href="#" target="_blank" class="btn btn-sm btn-outline-primary" id="v-attachment-link"><i class="fa-solid fa-paperclip me-2"></i>Download File</a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Delete Confirmation -->
    <div class="modal fade" id="deleteDiaryModal" tabindex="-1" aria-labelledby="deleteDiaryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius:14px;">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger" id="deleteDiaryModalLabel"><i class="fa-solid fa-circle-exclamation me-2"></i>Delete Diary Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p>Are you sure you want to permanently delete this daily diary entry record?</p>
                    <p class="text-muted small mb-0"><i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i>This action cannot be undone and will delete any associated student homework files or reports.</p>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <form method="POST" action="edit_diary.php">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="diary_id" id="delete-diary-id" value="">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-4">Delete Record</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    const viewModal = new bootstrap.Modal(document.getElementById("viewDiaryModal"));
    function viewDiaryDetails(d) {
        document.getElementById("v-date").textContent = d.diary_date;
        document.getElementById("v-type").textContent = d.academic_type;
        document.getElementById("v-class").textContent = d.class;
        document.getElementById("v-section").textContent = d.section;
        document.getElementById("v-subject").textContent = d.subject;
        document.getElementById("v-title").textContent = d.title;
        document.getElementById("v-author").textContent = d.creator_name ? d.creator_name : 'System';
        document.getElementById("v-created").textContent = d.created_at;
        document.getElementById("v-updated").textContent = d.updated_at;
        
        // Category badge
        const cat = document.getElementById("v-category");
        cat.textContent = d.diary_type;
        cat.className = "badge " + (d.diary_type === 'Homework' ? 'bg-primary' : (d.diary_type === 'General Notice' ? 'bg-danger' : 'bg-info text-dark'));

        // Status badge
        const stat = document.getElementById("v-status");
        stat.textContent = d.status;
        stat.className = "badge " + (d.status === 'Active' ? 'bg-success' : 'bg-secondary');

        // Description HTML inject
        document.getElementById("v-desc").innerHTML = d.description;

        // Attachment row
        const attRow = document.getElementById("v-attachment-row");
        if (d.attachment) {
            attRow.style.display = "block";
            document.getElementById("v-attachment-link").href = "<?php echo APP_URL; ?>/" + d.attachment;
        } else {
            attRow.style.display = "none";
        }

        viewModal.show();
    }

    const delModal = new bootstrap.Modal(document.getElementById("deleteDiaryModal"));
    function triggerDelete(id) {
        document.getElementById("delete-diary-id").value = id;
        delModal.show();
    }
    </script>

<?php endif; ?>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
