<?php
/**
 * Indus Grammar School ERP - Students Directory
 * Version 1.0.0
 */

$pageTitle = 'Student Management';
$breadcrumbActive = 'Students';

// Boot application layout
include_once __DIR__ . '/../../includes/header.php';

// Verify permission
AuthMiddleware::requirePermission('student_view');

// Handle filters
$filters = [
    'class_id' => isset($_GET['class_id']) ? (int)$_GET['class_id'] : null,
    'status' => isset($_GET['status']) ? sanitize($_GET['status']) : null,
    'search' => isset($_GET['search']) ? sanitize($_GET['search']) : null
];

// Pagination variables
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Fetch data
$totalStudents = Student::count($filters);
$students = Student::all($filters, $limit, $offset);
$classes = SchoolClass::all();

$totalPages = ceil($totalStudents / $limit);
if ($totalPages < 1) $totalPages = 1;
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-user-graduate me-2 text-primary"></i>Student Directory</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if (hasPermission('student_create')): ?>
            <a href="add.php" class="btn btn-primary px-4 py-2"><i class="fa-solid fa-plus me-2"></i>Register Student</a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Bar Card -->
<div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
    <div class="card-body p-4">
        <form method="GET" action="list.php" class="row g-3">
            <!-- Search field -->
            <div class="col-12 col-md-4">
                <label for="search" class="form-label small fw-semibold text-muted">Search Student</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" class="form-control bg-light border-start-0" id="search" name="search" value="<?php echo sanitize($filters['search'] ?? ''); ?>" placeholder="Name, admission no, or guardian...">
                </div>
            </div>

            <!-- Class Filter -->
            <div class="col-12 col-sm-6 col-md-3">
                <label for="class_id" class="form-label small fw-semibold text-muted">Filter by Class</label>
                <select class="form-select bg-light" id="class_id" name="class_id">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($filters['class_id'] == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="col-12 col-sm-6 col-md-3">
                <label for="status" class="form-label small fw-semibold text-muted">Status</label>
                <select class="form-select bg-light" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo ($filters['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo ($filters['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Suspended" <?php echo ($filters['status'] === 'Suspended') ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>

            <!-- Actions buttons -->
            <div class="col-12 col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-secondary w-100 py-2">Filter</button>
                <?php if ($filters['class_id'] || $filters['status'] || $filters['search']): ?>
                    <a href="list.php" class="btn btn-outline-secondary py-2 px-3" title="Clear Filters"><i class="fa-solid fa-filter-circle-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Students Listing Card -->
<div class="custom-table-card shadow-sm border-0 mb-4">
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Admission No</th>
                    <th>Student Name</th>
                    <th>Class / Section</th>
                    <th>Gender</th>
                    <th>Guardian Contact</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="text-muted">
                                <i class="fa-solid fa-users-slash d-block fs-1 mb-3 text-secondary opacity-50"></i>
                                <h5>No Students Found</h5>
                                <p class="small mb-0">Try clearing filters or add a new student record to begin.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><strong class="text-primary font-monospace"><?php echo sanitize($student['admission_no']); ?></strong></td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo sanitize($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                <div class="text-muted small" style="font-size: 0.75rem;">Born: <?php echo date('M d, Y', strtotime($student['date_of_birth'])); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill"><?php echo sanitize($student['class_name'] . ' - ' . $student['section']); ?></span>
                            </td>
                            <td><?php echo sanitize($student['gender']); ?></td>
                            <td>
                                <div class="fw-semibold text-secondary"><i class="fa-solid fa-phone me-2 text-muted small"></i><?php echo sanitize($student['guardian_phone']); ?></div>
                                <div class="text-muted small" style="font-size: 0.75rem;"><?php echo sanitize($student['guardian_name']); ?></div>
                            </td>
                            <td>
                                <?php
                                $status = $student['status'];
                                $badgeClass = 'badge-soft-success';
                                if ($status === 'Inactive') {
                                    $badgeClass = 'bg-light text-secondary border';
                                } elseif ($status === 'Suspended') {
                                    $badgeClass = 'badge-soft-warning';
                                }
                                ?>
                                <span class="badge <?php echo $badgeClass; ?> px-3 py-2 rounded-pill"><?php echo $status; ?></span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <a href="profile.php?id=<?php echo $student['id']; ?>" class="btn btn-outline-secondary btn-sm" title="View Profile">
                                        <i class="fa-regular fa-user"></i>
                                    </a>
                                    <?php if (hasPermission('student_edit')): ?>
                                        <a href="edit.php?id=<?php echo $student['id']; ?>" class="btn btn-outline-primary btn-sm" title="Edit Student">
                                            <i class="fa-regular fa-pen-to-square"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('student_delete')): ?>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-delete-student" data-id="<?php echo $student['id']; ?>" data-name="<?php echo sanitize($student['first_name'] . ' ' . $student['last_name']); ?>" title="Delete Student">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination layout -->
<?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation" class="mb-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $filters['class_id'] ? '&class_id='.$filters['class_id'] : ''; ?><?php echo $filters['status'] ? '&status='.$filters['status'] : ''; ?><?php echo $filters['search'] ? '&search='.$filters['search'] : ''; ?>">Previous</a>
            </li>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $filters['class_id'] ? '&class_id='.$filters['class_id'] : ''; ?><?php echo $filters['status'] ? '&status='.$filters['status'] : ''; ?><?php echo $filters['search'] ? '&search='.$filters['search'] : ''; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            
            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $filters['class_id'] ? '&class_id='.$filters['class_id'] : ''; ?><?php echo $filters['status'] ? '&status='.$filters['status'] : ''; ?><?php echo $filters['search'] ? '&search='.$filters['search'] : ''; ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<?php if (hasPermission('student_delete')): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 12px;">
            <div class="modal-header border-bottom-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-danger" id="deleteModalLabel"><i class="fa-solid fa-circle-exclamation me-2"></i>Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <p>Are you sure you want to delete student profile for <strong id="delete-student-name"></strong>?</p>
                <div class="alert alert-danger mb-0 small">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><strong>Warning:</strong> This action will permanently remove this student record from databases.
                </div>
            </div>
            <div class="modal-footer border-top-0 pb-4 px-4 gap-2">
                <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4" id="confirm-delete-btn">Delete Record</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Inject delete confirmation logic via footer scripts
$extraJS = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const deleteButtons = document.querySelectorAll(".btn-delete-student");
    const confirmDeleteBtn = document.getElementById("confirm-delete-btn");
    const deleteStudentNameSpan = document.getElementById("delete-student-name");
    
    let deleteId = null;
    let deleteModalInstance = null;

    if (deleteButtons.length > 0) {
        const deleteModal = document.getElementById("deleteModal");
        deleteModalInstance = new bootstrap.Modal(deleteModal);

        deleteButtons.forEach(btn => {
            btn.addEventListener("click", function() {
                deleteId = this.getAttribute("data-id");
                deleteStudentNameSpan.textContent = this.getAttribute("data-name");
                deleteModalInstance.show();
            });
        });
    }

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener("click", function() {
            if (!deleteId) return;

            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status"></span>Deleting...`;

            const formData = new FormData();
            formData.append("action", "delete");
            formData.append("id", deleteId);
            formData.append("csrf_token", "' . csrfToken() . '");

            fetch("../../ajax/students.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    deleteModalInstance.hide();
                    // Alert and reload
                    window.location.reload();
                } else {
                    alert(data.message || "Failed to delete student record.");
                    confirmDeleteBtn.disabled = false;
                    confirmDeleteBtn.textContent = "Delete Record";
                }
            })
            .catch(err => {
                console.error("Error:", err);
                alert("An unexpected network error occurred.");
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = "Delete Record";
            });
        });
    }
});
</script>
';

// Render footer
include_once __DIR__ . '/../../includes/footer.php';
?>
