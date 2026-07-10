<?php
/**
 * Indus Grammar School ERP - Student Profile
 * Version 1.0.0
 */

$pageTitle = 'Student Profile';
$breadcrumbActive = 'Student Profile';

// Boot application layout
include_once __DIR__ . '/../../includes/header.php';

// Verify permission
AuthMiddleware::requirePermission('student_view');

// Fetch student detail
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student = Student::findById($id);

if (!$student) {
    $_SESSION['flash_error'] = "Student record not found.";
    redirect('list.php');
}

// Calculate age
$dob = new DateTime($student['date_of_birth']);
$today = new DateTime('today');
$age = $dob->diff($today)->y;
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-regular fa-id-card me-2 text-primary"></i>Student Profile</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <a href="list.php" class="btn btn-outline-secondary px-3 py-2 me-2"><i class="fa-solid fa-arrow-left me-2"></i>Back to List</a>
        <?php if (hasPermission('student_edit')): ?>
            <a href="edit.php?id=<?php echo $student['id']; ?>" class="btn btn-primary px-4 py-2"><i class="fa-solid fa-user-gear me-2"></i>Edit Details</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Quick Profile Card -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm text-center p-4 mb-4" style="border-radius: 12px;">
            <div class="card-body">
                <!-- Large Initial Avatar -->
                <div class="mx-auto bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm mb-3" style="width: 100px; height: 100px; font-size: 3.5rem; background: linear-gradient(135deg, var(--primary-color) 0%, #818cf8 100%) !important;">
                    <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                </div>

                <h4 class="fw-bold text-dark mb-1"><?php echo sanitize($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                <p class="font-monospace text-primary fw-bold mb-3"><?php echo sanitize($student['admission_no']); ?></p>

                <div class="mb-3">
                    <?php
                    $status = $student['status'];
                    $badgeClass = 'badge-soft-success';
                    if ($status === 'Inactive') {
                        $badgeClass = 'bg-light text-secondary border';
                    } elseif ($status === 'Suspended') {
                        $badgeClass = 'badge-soft-warning';
                    }
                    ?>
                    <span class="badge <?php echo $badgeClass; ?> px-3 py-2 rounded-pill fs-6"><?php echo $status; ?></span>
                </div>

                <hr class="text-light-muted">

                <div class="text-start">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-secondary small">Placement:</span>
                        <strong class="text-dark small"><?php echo sanitize($student['class_name'] . ' - ' . $student['section']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-secondary small">Enrollment Date:</span>
                        <strong class="text-dark small"><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span class="text-secondary small">Attendance Rate:</span>
                        <strong class="text-success small">96.5% (Mocked)</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Details Tabs -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius: 12px;">
            <div class="card-header bg-transparent border-bottom p-4">
                <h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-circle-info me-2 text-muted"></i>Profile Documentation</h5>
            </div>
            <div class="card-body p-4">
                <!-- Personal Info -->
                <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">Student Information</h6>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-4">
                        <div class="text-secondary small">First Name</div>
                        <div class="fw-semibold text-dark"><?php echo sanitize($student['first_name']); ?></div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="text-secondary small">Last Name</div>
                        <div class="fw-semibold text-dark"><?php echo sanitize($student['last_name']); ?></div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="text-secondary small">Gender</div>
                        <div class="fw-semibold text-dark"><?php echo sanitize($student['gender']); ?></div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="text-secondary small">Date of Birth</div>
                        <div class="fw-semibold text-dark"><?php echo date('M d, Y', strtotime($student['date_of_birth'])); ?></div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="text-secondary small">Current Age</div>
                        <div class="fw-semibold text-dark"><?php echo $age; ?> Years</div>
                    </div>
                </div>

                <!-- Guardian Info -->
                <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">Guardian Contact Information</h6>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="text-secondary small">Guardian Name</div>
                        <div class="fw-semibold text-dark"><?php echo sanitize($student['guardian_name']); ?></div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="text-secondary small">Mobile Phone</div>
                        <div class="fw-semibold text-dark"><?php echo sanitize($student['guardian_phone']); ?></div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="text-secondary small">Email Address</div>
                        <div class="fw-semibold text-dark"><?php echo sanitize($student['guardian_email'] ?? 'Not Provided'); ?></div>
                    </div>
                    <div class="col-12">
                        <div class="text-secondary small">Home Address</div>
                        <div class="fw-semibold text-dark"><?php echo sanitize($student['address']); ?></div>
                    </div>
                </div>

                <!-- Finance Status -->
                <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">Finance Overview (Mocked)</h6>
                <div class="row g-3">
                    <div class="col-6 col-md-4">
                        <div class="text-secondary small">Tuition Fee Status</div>
                        <span class="badge bg-success rounded-pill px-3 py-2 mt-1">Paid</span>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="text-secondary small">Outstanding Balances</div>
                        <div class="fw-semibold text-dark mt-1">Rs. 0.00</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Render footer
include_once __DIR__ . '/../../includes/footer.php';
?>
