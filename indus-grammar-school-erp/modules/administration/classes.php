<?php
/**
 * Indus Grammar School ERP - Class Management Module
 * Version 5.0.0
 * Features: Complete Class Registry with Teacher, Monthly Fee, Class Group & Active Status Management
 */

$pageTitle = 'Class Management';
$breadcrumbActive = 'Class Management';

require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();
AuthMiddleware::requirePermission('system_settings');

$db = Database::getConnection();
$message = '';
$error = '';

// Handle Form Submissions (Add, Edit, Delete, Toggle Active)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    
    if ($action === 'add' || $action === 'edit') {
        $className = strtoupper(trim(sanitize($_POST['class_name'] ?? '')));
        $teacherName = trim(sanitize($_POST['teacher_name'] ?? 'ZAINAB'));
        $monthlyFee = (float)($_POST['monthly_fee'] ?? 2500);
        $classGroup = trim(sanitize($_POST['class_group'] ?? '0'));
        $status = (strtoupper(trim(sanitize($_POST['status'] ?? 'YES'))) === 'NO') ? 'NO' : 'YES';
        $section = trim(sanitize($_POST['section'] ?? 'Red'));

        if (empty($className)) {
            $error = "Class name cannot be empty.";
        } else {
            try {
                if ($action === 'add') {
                    $stmtIns = $db->prepare("
                        INSERT INTO classes (class_name, section, teacher_name, monthly_fee, class_group, status) 
                        VALUES (:name, :sec, :tname, :fee, :grp, :stat)
                    ");
                    $stmtIns->execute([
                        'name' => $className,
                        'sec' => $section ?: 'Red',
                        'tname' => $teacherName,
                        'fee' => $monthlyFee,
                        'grp' => $classGroup,
                        'stat' => $status
                    ]);
                    $_SESSION['flash_success'] = "Class '{$className}' added successfully!";
                } else {
                    $classId = (int)$_POST['class_id'];
                    $stmtUpd = $db->prepare("
                        UPDATE classes 
                        SET class_name = :name, section = :sec, teacher_name = :tname, monthly_fee = :fee, class_group = :grp, status = :stat 
                        WHERE id = :id
                    ");
                    $stmtUpd->execute([
                        'name' => $className,
                        'sec' => $section ?: 'Red',
                        'tname' => $teacherName,
                        'fee' => $monthlyFee,
                        'grp' => $classGroup,
                        'stat' => $status,
                        'id' => $classId
                    ]);
                    $_SESSION['flash_success'] = "Class #{$classId} updated successfully!";
                }
                header("Location: classes.php");
                exit;
            } catch (Exception $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }

    if ($action === 'delete') {
        $classId = (int)$_POST['class_id'];
        if ($classId > 0) {
            try {
                // Check if students are enrolled in this class
                $stmtCheck = $db->prepare("SELECT COUNT(*) FROM students WHERE class_id = ?");
                $stmtCheck->execute([$classId]);
                $count = (int)$stmtCheck->fetchColumn();
                
                if ($count > 0) {
                    $_SESSION['flash_error'] = "Cannot delete class. There are {$count} student(s) enrolled in this class.";
                } else {
                    $stmtDel = $db->prepare("DELETE FROM classes WHERE id = ?");
                    $stmtDel->execute([$classId]);
                    $_SESSION['flash_success'] = "Class deleted successfully.";
                }
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Error deleting class: " . $e->getMessage();
            }
        }
        header("Location: classes.php");
        exit;
    }

    if ($action === 'toggle_status') {
        $classId = (int)$_POST['class_id'];
        if ($classId > 0) {
            try {
                $stmtT = $db->prepare("UPDATE classes SET status = IF(status = 'YES', 'NO', 'YES') WHERE id = ?");
                $stmtT->execute([$classId]);
                $_SESSION['flash_success'] = "Class status updated.";
            } catch (Exception $e) {
                $_SESSION['flash_error'] = $e->getMessage();
            }
        }
        header("Location: classes.php");
        exit;
    }
}

// Fetch all classes sorted by ID / Class Name
$classList = [];
try {
    $classList = $db->query("SELECT * FROM classes ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Failed to fetch classes: " . $e->getMessage();
}

include_once __DIR__ . '/../../includes/header.php';
?>

<style>
.class-mgmt-wrapper {
    background-color: #f8fafc;
    border-radius: 18px;
    padding: 1.5rem;
    min-height: calc(100vh - 100px);
}

.class-hero-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 55%, #2563eb 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.5rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.18);
    margin-bottom: 1.5rem;
}

.hero-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: #ffffff;
}

.table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.custom-class-table th {
    background-color: #f1f5f9;
    color: #334155;
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.85rem 1rem;
    border-bottom: 2px solid #cbd5e1;
}

.custom-class-table td {
    padding: 0.8rem 1rem;
    vertical-align: middle;
    font-size: 0.9rem;
    color: #0f172a;
    border-bottom: 1px solid #e2e8f0;
}

.custom-class-table tr:hover {
    background-color: #f8fafc;
}

.badge-yes {
    background-color: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
    font-weight: 700;
    padding: 0.35rem 0.75rem;
    border-radius: 50px;
    font-size: 0.8rem;
}

.badge-no {
    background-color: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
    font-weight: 700;
    padding: 0.35rem 0.75rem;
    border-radius: 50px;
    font-size: 0.8rem;
}
</style>

<div class="class-mgmt-wrapper">

    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item text-muted">Admin Panel</li>
            <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Class Management</li>
        </ol>
    </nav>

    <!-- Hero Header Banner -->
    <div class="class-hero-banner">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-box">
                    <i class="fa-solid fa-school"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h2 class="fw-bold mb-0 text-white fs-3">Class Management</h2>
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill small fw-semibold">Total: <?php echo count($classList); ?> Classes</span>
                    </div>
                    <p class="text-white-50 small mb-0">Manage registered school classes, assigned class teachers, monthly fees, and active status.</p>
                </div>
            </div>
            <div>
                <button class="btn btn-light btn-sm fw-bold px-4 py-2 rounded-3 text-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#classModal" onclick="prepareAddModal()">
                    <i class="fa-solid fa-plus-circle me-1.5"></i> Add New Class
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo sanitize($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo sanitize($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Classes Directory Table -->
    <div class="table-card">
        <div class="p-4 border-bottom bg-white d-flex align-items-center justify-content-between">
            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-list-check me-2 text-primary"></i>School Class Registry</h5>
            <span class="text-muted small">Showing <?php echo count($classList); ?> Class Entries</span>
        </div>
        <div class="table-responsive">
            <table class="table custom-class-table align-middle mb-0">
                <thead>
                    <tr>
                        <th width="70" class="text-center">Sr.</th>
                        <th>Class</th>
                        <th>Teacher</th>
                        <th class="text-center">MonthlyFee</th>
                        <th class="text-center">ClassGroup</th>
                        <th class="text-center">Active</th>
                        <th class="text-end" width="160">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($classList)): ?>
                        <?php foreach ($classList as $idx => $cls): ?>
                            <tr>
                                <td class="text-center fw-bold text-secondary"><?php echo ($idx + 1); ?></td>
                                <td class="fw-bold text-dark fs-6">
                                    <i class="fa-solid fa-graduation-cap me-2 text-primary small"></i><?php echo sanitize($cls['class_name']); ?>
                                </td>
                                <td class="fw-semibold text-secondary">
                                    <i class="fa-solid fa-user-tie me-1.5 text-muted small"></i><?php echo sanitize($cls['teacher_name'] ?: 'ZAINAB'); ?>
                                </td>
                                <td class="text-center fw-bold text-dark">
                                    Rs. <?php echo number_format($cls['monthly_fee'] ?? 2500); ?>
                                </td>
                                <td class="text-center fw-semibold text-muted">
                                    <?php echo sanitize($cls['class_group'] ?? '0'); ?>
                                </td>
                                <td class="text-center">
                                    <?php if (strtoupper($cls['status']) === 'YES'): ?>
                                        <span class="badge-yes"><i class="fa-solid fa-check me-1"></i>YES</span>
                                    <?php else: ?>
                                        <span class="badge-no"><i class="fa-solid fa-xmark me-1"></i>NO</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary fw-bold px-3 py-1 me-1 rounded-2" 
                                                onclick="prepareEditModal(<?php echo htmlspecialchars(json_encode($cls)); ?>)"
                                                title="Edit Class">
                                            <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger px-2 py-1 rounded-2 btn-delete-class" 
                                                data-id="<?php echo $cls['id']; ?>" 
                                                data-name="<?php echo sanitize($cls['class_name']); ?>"
                                                title="Delete Class">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No classes found in system registry.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Add / Edit Class Modal -->
<div class="modal fade" id="classModal" tabindex="-1" aria-labelledby="classModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="classes.php">
                <input type="hidden" name="action" id="modal_action" value="add">
                <input type="hidden" name="class_id" id="modal_class_id" value="">
                
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-primary" id="classModalLabel">
                        <i class="fa-solid fa-school me-2"></i><span id="modal_title_text">Add New Class</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body py-4">
                    <!-- Class Name -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Class Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="class_name" id="modal_class_name" placeholder="e.g. PLAY GROUP, NURSERY, CLASS-1" required>
                    </div>

                    <!-- Teacher Name -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Class Teacher Name</label>
                        <input type="text" class="form-control" name="teacher_name" id="modal_teacher_name" placeholder="e.g. SYEDA SAMANA BUKHARI, ZAINAB">
                    </div>

                    <!-- Monthly Fee & Class Group -->
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Monthly Fee (Rs.)</label>
                            <input type="number" step="50" class="form-control" name="monthly_fee" id="modal_monthly_fee" value="2500">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Class Group</label>
                            <input type="text" class="form-control" name="class_group" id="modal_class_group" value="0">
                        </div>
                    </div>

                    <!-- Active Status -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Active Status</label>
                        <select class="form-select" name="status" id="modal_status">
                            <option value="YES">YES (Active)</option>
                            <option value="NO">NO (Inactive)</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Save Class Details</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Delete Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p class="mb-0">Are you sure you want to delete class <strong id="delete_class_name"></strong>?</p>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <form method="POST" action="classes.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="class_id" id="delete_class_id" value="">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">Delete Record</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function prepareAddModal() {
    document.getElementById('modal_action').value = 'add';
    document.getElementById('modal_class_id').value = '';
    document.getElementById('modal_title_text').textContent = 'Add New Class';
    document.getElementById('modal_class_name').value = '';
    document.getElementById('modal_teacher_name').value = 'ZAINAB';
    document.getElementById('modal_monthly_fee').value = '2500';
    document.getElementById('modal_class_group').value = '0';
    document.getElementById('modal_status').value = 'YES';
}

function prepareEditModal(data) {
    document.getElementById('modal_action').value = 'edit';
    document.getElementById('modal_class_id').value = data.id;
    document.getElementById('modal_title_text').textContent = 'Edit Class #' + data.id;
    document.getElementById('modal_class_name').value = data.class_name;
    document.getElementById('modal_teacher_name').value = data.teacher_name || 'ZAINAB';
    document.getElementById('modal_monthly_fee').value = data.monthly_fee || '2500';
    document.getElementById('modal_class_group').value = data.class_group || '0';
    document.getElementById('modal_status').value = data.status || 'YES';
    
    var modal = new bootstrap.Modal(document.getElementById('classModal'));
    modal.show();
}

document.addEventListener("DOMContentLoaded", () => {
    const deleteButtons = document.querySelectorAll(".btn-delete-class");
    const deleteModal = new bootstrap.Modal(document.getElementById("deleteClassModal"));
    
    deleteButtons.forEach(btn => {
        btn.addEventListener("click", () => {
            document.getElementById("delete_class_name").textContent = btn.getAttribute("data-name");
            document.getElementById("delete_class_id").value = btn.getAttribute("data-id");
            deleteModal.show();
        });
    });
});
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
