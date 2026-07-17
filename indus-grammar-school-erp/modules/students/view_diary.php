<?php
/**
 * Indus Grammar School ERP - View Diary Details
 * Version 4.0.0
 */

require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$diary = null;
$errorMsg = '';

if ($id <= 0) {
    $errorMsg = "Diary record not found.";
} else {
    try {
        $stmt = $db->prepare("
            SELECT d.*, u.username as creator_name, st.first_name as teacher_first, st.last_name as teacher_last
            FROM daily_diaries d
            LEFT JOIN users u ON d.created_by = u.id
            LEFT JOIN staff st ON d.teacher_id = st.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $diary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$diary) {
            $errorMsg = "Diary record not found.";
        }
    } catch (Exception $e) {
        error_log("Error retrieving diary details: " . $e->getMessage());
        $errorMsg = "Diary record not found.";
    }
}

$pageTitle = 'Daily Diary Detail View';
$breadcrumbActive = 'Diary Report';
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-book-open me-2 text-primary"></i>Diary Task Details</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
        <a href="diary_report.php" class="btn btn-sm btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i>Back to Report</a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger shadow-sm border-0 py-4 text-center" style="border-radius: 12px;">
                <i class="fa-solid fa-triangle-exclamation fs-2 text-danger mb-3 d-block"></i>
                <h5 class="fw-bold text-danger"><?php echo htmlspecialchars($errorMsg); ?></h5>
                <p class="text-muted mb-0 small">Please check the ID or return to the main diary logs register directory.</p>
            </div>
        <?php else: 
            $teacherName = trim(($diary['teacher_first'] ?? '') . ' ' . ($diary['teacher_last'] ?? ''));
            if (empty($teacherName)) $teacherName = $diary['creator_name'] ?: 'System Administrator';
        ?>
            <!-- Diary Details Panel Card -->
            <div class="card border border-light shadow-sm bg-white p-4 mb-4" style="border-radius:12px;">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                    <div>
                        <span class="badge bg-primary-soft text-primary px-3 rounded-pill mb-1"><?php echo htmlspecialchars($diary['diary_type']); ?></span>
                        <h4 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($diary['title']); ?></h4>
                    </div>
                    <div class="text-end d-print-none">
                        <a href="print_diary.php?id=<?php echo $diary['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-print me-1"></i>Print Entry</a>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <span class="text-muted small d-block">Diary Target Date</span>
                        <strong class="text-primary fs-5"><i class="fa-solid fa-calendar me-1"></i><?php echo date('d-M-Y', strtotime($diary['diary_date'])); ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted small d-block">Academic Stream</span>
                        <strong class="text-dark"><i class="fa-solid fa-building-columns me-1"></i><?php echo htmlspecialchars($diary['academic_type']); ?></strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Class Section Target</span>
                        <strong class="text-dark"><?php echo htmlspecialchars($diary['class'] . ' (' . ($diary['section'] ?: 'A') . ')'); ?></strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Subject Name</span>
                        <strong class="text-dark"><?php echo htmlspecialchars($diary['subject'] ?: 'General / Combined'); ?></strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Assigned Teacher</span>
                        <strong class="text-dark"><?php echo htmlspecialchars($teacherName); ?></strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Status</span>
                        <span class="badge bg-<?php echo $diary['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft"><?php echo htmlspecialchars($diary['status']); ?></span>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Created By</span>
                        <strong class="text-muted"><?php echo htmlspecialchars($diary['creator_name'] ?: 'System'); ?></strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Created On</span>
                        <strong class="text-muted"><?php echo date('d-M-Y h:i A', strtotime($diary['created_at'])); ?></strong>
                    </div>
                </div>

                <!-- Diary Body -->
                <div class="border-top pt-4 mb-4">
                    <h5 class="fw-bold text-secondary mb-3">Diary Description & Task Instructions</h5>
                    <div class="p-4 rounded border bg-light text-dark mb-4" style="line-height: 1.6; font-size: 0.95rem; min-height: 120px;">
                        <?php echo nl2br(htmlspecialchars($diary['description'])); ?>
                    </div>
                    
                    <?php if ($diary['diary_type'] === 'Homework' || $diary['diary_type'] === 'Assignment'): ?>
                        <h6 class="fw-bold text-danger mb-2"><i class="fa-solid fa-pencil me-1"></i>Required Submission Details</h6>
                        <p class="text-muted small">Please complete the assignment tasks as instructed and submit to the subject teacher before/on the target date.</p>
                    <?php endif; ?>
                </div>

                <!-- Attachment Details -->
                <?php if (!empty($diary['attachment']) || !empty($diary['attachment_path'])): 
                    $fileUrl = !empty($diary['attachment']) ? $diary['attachment'] : $diary['attachment_path'];
                ?>
                    <div class="border-top pt-3">
                        <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-paperclip me-1 text-primary"></i>Reference Attachment</h6>
                        <a href="<?php echo APP_URL . '/' . htmlspecialchars($fileUrl); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-download me-1"></i>Download Diary Attachment</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
