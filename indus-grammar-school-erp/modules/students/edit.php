<?php
/**
 * Indus Grammar School ERP - Edit Student Details
 * Version 1.0.0
 */

$pageTitle = 'Edit Student Details';
$breadcrumbActive = 'Edit Student';

// Boot application layout
include_once __DIR__ . '/../../includes/header.php';

// Verify permission
AuthMiddleware::requirePermission('student_edit');

// Fetch student detail
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student = Student::findById($id);

if (!$student) {
    $_SESSION['flash_error'] = "Student record not found.";
    redirect('list.php');
}

$classes = SchoolClass::all();
?>

<div class="row mb-4 align-items-center">
    <div class="col-12">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-user-pen me-2 text-primary"></i>Modify Student Profile</h3>
        <p class="text-muted mb-0">Update information for student <strong class="text-dark"><?php echo sanitize($student['first_name'] . ' ' . $student['last_name']); ?></strong> (Admission: <?php echo sanitize($student['admission_no']); ?>).</p>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 12px;">
    <div class="card-body p-4 p-md-5">
        <!-- Alert feedback -->
        <div id="form-alert" class="alert d-none" role="alert"></div>

        <form id="edit-student-form">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $student['id']; ?>">

            <!-- Section: Personal Information -->
            <h5 class="fw-bold text-secondary mb-3 border-bottom pb-2"><i class="fa-solid fa-user me-2 text-muted"></i>Personal Information</h5>
            <div class="row g-4 mb-4">
                <!-- First Name -->
                <div class="col-md-6">
                    <label for="first_name" class="form-label fw-semibold small text-muted">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-light" id="first_name" name="first_name" value="<?php echo sanitize($student['first_name']); ?>" required>
                </div>
                <!-- Last Name -->
                <div class="col-md-6">
                    <label for="last_name" class="form-label fw-semibold small text-muted">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-light" id="last_name" name="last_name" value="<?php echo sanitize($student['last_name']); ?>" required>
                </div>
                <!-- Gender -->
                <div class="col-md-4">
                    <label for="gender" class="form-label fw-semibold small text-muted">Gender <span class="text-danger">*</span></label>
                    <select class="form-select bg-light" id="gender" name="gender" required>
                        <option value="Male" <?php echo ($student['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($student['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($student['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <!-- DOB -->
                <div class="col-md-4">
                    <label for="date_of_birth" class="form-label fw-semibold small text-muted">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" class="form-control bg-light" id="date_of_birth" name="date_of_birth" value="<?php echo $student['date_of_birth']; ?>" required>
                </div>
                <!-- Enrollment Date -->
                <div class="col-md-4">
                    <label for="enrollment_date" class="form-label fw-semibold small text-muted">Enrollment Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control bg-light" id="enrollment_date" name="enrollment_date" value="<?php echo $student['enrollment_date']; ?>" required>
                </div>
            </div>

            <!-- Section: Class Information -->
            <h5 class="fw-bold text-secondary mb-3 border-bottom pb-2"><i class="fa-solid fa-graduation-cap me-2 text-muted"></i>Academic Placement</h5>
            <div class="row g-4 mb-4">
                <!-- Class Selection -->
                <div class="col-md-6">
                    <label for="class_id" class="form-label fw-semibold small text-muted">Assigned Class <span class="text-danger">*</span></label>
                    <select class="form-select bg-light" id="class_id" name="class_id" required>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($student['class_id'] == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Status -->
                <div class="col-md-6">
                    <label for="status" class="form-label fw-semibold small text-muted">Student Status</label>
                    <select class="form-select bg-light" id="status" name="status">
                        <option value="Active" <?php echo ($student['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo ($student['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="Suspended" <?php echo ($student['status'] === 'Suspended') ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
            </div>

            <!-- Section: Guardian Information -->
            <h5 class="fw-bold text-secondary mb-3 border-bottom pb-2"><i class="fa-solid fa-users me-2 text-muted"></i>Guardian Details</h5>
            <div class="row g-4 mb-4">
                <!-- Guardian Name -->
                <div class="col-md-4">
                    <label for="guardian_name" class="form-label fw-semibold small text-muted">Guardian Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-light" id="guardian_name" name="guardian_name" value="<?php echo sanitize($student['guardian_name']); ?>" required>
                </div>
                <!-- Guardian Phone -->
                <div class="col-md-4">
                    <label for="guardian_phone" class="form-label fw-semibold small text-muted">Guardian Mobile Phone <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-light" id="guardian_phone" name="guardian_phone" value="<?php echo sanitize($student['guardian_phone']); ?>" required>
                </div>
                <!-- Guardian Email -->
                <div class="col-md-4">
                    <label for="guardian_email" class="form-label fw-semibold small text-muted">Guardian Email (Optional)</label>
                    <input type="email" class="form-control bg-light" id="guardian_email" name="guardian_email" value="<?php echo sanitize($student['guardian_email'] ?? ''); ?>">
                </div>
                <!-- Address -->
                <div class="col-12">
                    <label for="address" class="form-label fw-semibold small text-muted">Residential Address <span class="text-danger">*</span></label>
                    <textarea class="form-control bg-light" id="address" name="address" rows="3" required><?php echo sanitize($student['address']); ?></textarea>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="d-flex justify-content-end gap-3 mt-5 border-top pt-4">
                <a href="list.php" class="btn btn-outline-secondary px-4 py-2">Cancel</a>
                <button type="submit" class="btn btn-primary px-5 py-2 d-flex align-items-center gap-2" id="save-btn">
                    <span>Save Changes</span>
                    <div class="spinner-border spinner-border-sm d-none" role="status" id="btn-spinner">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Inject AJAX submit scripts
$extraJS = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const editForm = document.getElementById("edit-student-form");
    const saveBtn = document.getElementById("save-btn");
    const btnSpinner = document.getElementById("btn-spinner");
    const formAlert = document.getElementById("form-alert");

    editForm.addEventListener("submit", function(e) {
        e.preventDefault();

        // Clear alerts
        formAlert.classList.add("d-none");
        formAlert.classList.remove("alert-danger", "alert-success");
        formAlert.textContent = "";

        // Set loading states
        saveBtn.disabled = true;
        btnSpinner.classList.remove("d-none");

        const formData = new FormData(editForm);

        fetch("../../ajax/students.php", {
            method: "POST",
            body: formData
        })
        .then(res => {
            if(!res.ok) throw new Error("Server error.");
            return res.json();
        })
        .then(data => {
            if(data.success) {
                formAlert.classList.remove("d-none");
                formAlert.classList.add("alert-success");
                formAlert.innerHTML = `<i class="fa-solid fa-circle-check me-2"></i>` + data.message;
                
                // Redirect back to list
                setTimeout(() => {
                    window.location.href = "list.php";
                }, 1000);
            } else {
                formAlert.classList.remove("d-none");
                formAlert.classList.add("alert-danger");
                formAlert.innerHTML = `<i class="fa-solid fa-circle-exclamation me-2"></i>` + data.message;
                
                // Reset button
                saveBtn.disabled = false;
                btnSpinner.classList.add("d-none");
                window.scrollTo({ top: 0, behavior: "smooth" });
            }
        })
        .catch(err => {
            console.error("Error:", err);
            formAlert.classList.remove("d-none");
            formAlert.classList.add("alert-danger");
            formAlert.innerHTML = `<i class="fa-solid fa-circle-exclamation me-2"></i>An unexpected network error occurred. Please try again.`;
            
            saveBtn.disabled = false;
            btnSpinner.classList.add("d-none");
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
    });
});
</script>
';

// Render footer
include_once __DIR__ . '/../../includes/footer.php';
?>
