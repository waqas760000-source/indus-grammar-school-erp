<?php
/**
 * Indus Grammar School ERP - Students Directory (Simplified & Categorized)
 * Version 3.0.0
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
    'search' => isset($_GET['search']) ? sanitize($_GET['search']) : null,
    'academic_type' => isset($_GET['academic_type']) ? sanitize($_GET['academic_type']) : null
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
            <a href="registration.php" class="btn btn-primary px-4 py-2"><i class="fa-solid fa-plus me-2"></i>Register Student</a>
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

            <!-- Academic Type Filter -->
            <div class="col-12 col-sm-6 col-md-3">
                <label for="academic_type" class="form-label small fw-semibold text-muted">Academic Type</label>
                <select class="form-select bg-light" id="academic_type" name="academic_type">
                    <option value="">All Types</option>
                    <option value="School" <?php echo ($filters['academic_type'] === 'School') ? 'selected' : ''; ?>>School</option>
                    <option value="Academy" <?php echo ($filters['academic_type'] === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                </select>
            </div>

            <!-- Class Filter -->
            <div class="col-12 col-sm-6 col-md-3">
                <label for="class_id" class="form-label small fw-semibold text-muted">Class & Section</label>
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
            <div class="col-12 col-sm-6 col-md-2">
                <label for="status" class="form-label small fw-semibold text-muted">Status</label>
                <select class="form-select bg-light" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo ($filters['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo ($filters['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Suspended" <?php echo ($filters['status'] === 'Suspended') ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>

            <!-- Actions buttons -->
            <div class="col-12 text-end mt-3">
                <button type="submit" class="btn btn-secondary px-4 py-2">Apply Filters</button>
                <?php if ($filters['class_id'] || $filters['status'] || $filters['search'] || $filters['academic_type']): ?>
                    <a href="list.php" class="btn btn-outline-secondary py-2 px-3" title="Clear Filters"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Students Listing Card -->
<div class="custom-table-card shadow-sm border-0 mb-4">
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle">
            <thead>
                <tr>
                    <th>Admission No</th>
                    <th>Student Name</th>
                    <th>Academic Type</th>
                    <th>Class Details</th>
                    <th>Gender</th>
                    <th>Guardian Contact</th>
                    <th>Status</th>
                    <th class="text-end text-muted d-print-none">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="text-muted">
                                <i class="fa-solid fa-users-slash d-block fs-1 mb-3 text-secondary opacity-50"></i>
                                <h5>No Students Found</h5>
                                <p class="small mb-0">Try clearing filters or register a new student record to begin.</p>
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
                                <?php
                                $type = $student['academic_type'] ?? 'School';
                                $typeBadge = 'bg-primary';
                                if ($type === 'Academy') $typeBadge = 'bg-success';
                                ?>
                                <span class="badge <?php echo $typeBadge; ?> px-2 py-1 rounded"><?php echo sanitize($type); ?></span>
                            </td>
                            <td>
                                <div class="text-dark small"><i class="fa-solid fa-school me-1 text-muted"></i><?php echo sanitize(($student['class_name'] ?? $student['school_class'] ?? '-') . ' - ' . ($student['section'] ?? $student['school_section'] ?? 'A')); ?></div>
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
                            <td class="text-end d-print-none">
                                <div class="btn-group">
                                    <a href="profile_report.php?id=<?php echo $student['id']; ?>" class="btn btn-outline-secondary btn-sm" title="View Profile">
                                        <i class="fa-regular fa-user"></i>
                                    </a>
                                    <?php if (hasPermission('student_edit')): ?>
                                        <a href="registration.php?id=<?php echo $student['id']; ?>" class="btn btn-outline-primary btn-sm" title="Edit Student">
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
                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $filters['class_id'] ? '&class_id='.$filters['class_id'] : ''; ?><?php echo $filters['status'] ? '&status='.$filters['status'] : ''; ?><?php echo $filters['search'] ? '&search='.$filters['search'] : ''; ?><?php echo $filters['academic_type'] ? '&academic_type='.$filters['academic_type'] : ''; ?>">Previous</a>
            </li>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $filters['class_id'] ? '&class_id='.$filters['class_id'] : ''; ?><?php echo $filters['status'] ? '&status='.$filters['status'] : ''; ?><?php echo $filters['search'] ? '&search='.$filters['search'] : ''; ?><?php echo $filters['academic_type'] ? '&academic_type='.$filters['academic_type'] : ''; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            
            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $filters['class_id'] ? '&class_id='.$filters['class_id'] : ''; ?><?php echo $filters['status'] ? '&status='.$filters['status'] : ''; ?><?php echo $filters['search'] ? '&search='.$filters['search'] : ''; ?><?php echo $filters['academic_type'] ? '&academic_type='.$filters['academic_type'] : ''; ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<?php if (hasPermission('student_delete')): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-danger" id="deleteModalLabel"><i class="fa-solid fa-circle-exclamation me-2"></i>Delete Student Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p>Are you sure you want to permanently delete the student file for <strong id="delete-student-name"></strong>?</p>
                <p class="text-muted small mb-0"><i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i>This action cannot be undone and will delete all academic, registration, fee, and documents history.</p>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <form method="POST" action="registration.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="student_id" id="delete-student-id" value="">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">Delete Record</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
    // Attach listener for delete buttons
    const deleteButtons = document.querySelectorAll(".btn-delete-student");
    const deleteStudentName = document.getElementById("delete-student-name");
    const deleteStudentId = document.getElementById("delete-student-id");
    const deleteModal = document.getElementById("deleteModal") ? new bootstrap.Modal(document.getElementById("deleteModal")) : null;

    deleteButtons.forEach(btn => {
        btn.addEventListener("click", () => {
            if (deleteStudentName && deleteStudentId && deleteModal) {
                deleteStudentName.textContent = btn.getAttribute("data-name");
                deleteStudentId.value = btn.getAttribute("data-id");
                deleteModal.show();
            }
        });
    });
});
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
