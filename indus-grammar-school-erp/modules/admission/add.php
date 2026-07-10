<?php
/**
 * Indus Grammar School ERP - Submit Admission Application
 * Version 1.0.0
 */

$pageTitle = 'Submit Admission Application';
$breadcrumbActive = 'New Application';

// Boot application layout
include_once __DIR__ . '/../../includes/header.php';

// Verify permission
AuthMiddleware::requirePermission('admission_create');

$classes = SchoolClass::all();
?>

<div class="row mb-4 align-items-center">
    <div class="col-12">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-file-signature me-2 text-primary"></i>Record Admission Application</h3>
        <p class="text-muted mb-0">Record a new student admission request for review and entry exams.</p>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 12px;">
    <div class="card-body p-4 p-md-5">
        <!-- Alert feedback -->
        <div id="form-alert" class="alert d-none" role="alert"></div>

        <form id="add-applicant-form">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="action" value="create">

            <!-- Section: Personal Information -->
            <h5 class="fw-bold text-secondary mb-3 border-bottom pb-2"><i class="fa-solid fa-user me-2 text-muted"></i>Applicant Information</h5>
            <div class="row g-4 mb-4">
                <!-- First Name -->
                <div class="col-md-6">
                    <label for="first_name" class="form-label fw-semibold small text-muted">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-light" id="first_name" name="first_name" required placeholder="e.g. Fatima">
                </div>
                <!-- Last Name -->
                <div class="col-md-6">
                    <label for="last_name" class="form-label fw-semibold small text-muted">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-light" id="last_name" name="last_name" required placeholder="e.g. Bilal">
                </div>
                <!-- Gender -->
                <div class="col-md-4">
                    <label for="gender" class="form-label fw-semibold small text-muted">Gender <span class="text-danger">*</span></label>
                    <select class="form-select bg-light" id="gender" name="gender" required>
                        <option value="">Choose...</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <!-- DOB -->
                <div class="col-md-4">
                    <label for="date_of_birth" class="form-label fw-semibold small text-muted">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" class="form-control bg-light" id="date_of_birth" name="date_of_birth" required>
                </div>
                <!-- Class to Apply -->
                <div class="col-md-4">
                    <label for="class_id" class="form-label fw-semibold small text-muted">Class to Apply For <span class="text-danger">*</span></label>
                    <select class="form-select bg-light" id="class_id" name="class_id" required>
                        <option value="">Select Class...</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>">
                                <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Section: Guardian Information -->
            <h5 class="fw-bold text-secondary mb-3 border-bottom pb-2"><i class="fa-solid fa-users me-2 text-muted"></i>Guardian Details</h5>
            <div class="row g-4 mb-4">
                <!-- Guardian Name -->
                <div class="col-md-4">
                    <label for="guardian_name" class="form-label fw-semibold small text-muted">Guardian Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-light" id="guardian_name" name="guardian_name" required placeholder="e.g. Bilal Tariq">
                </div>
                <!-- Guardian Phone -->
                <div class="col-md-4">
                    <label for="guardian_phone" class="form-label fw-semibold small text-muted">Guardian Mobile Phone <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-light" id="guardian_phone" name="guardian_phone" required placeholder="e.g. 03451112233">
                </div>
                <!-- Guardian Email -->
                <div class="col-md-4">
                    <label for="guardian_email" class="form-label fw-semibold small text-muted">Guardian Email (Optional)</label>
                    <input type="email" class="form-control bg-light" id="guardian_email" name="guardian_email" placeholder="e.g. bilal@gmail.com">
                </div>
                <!-- Address -->
                <div class="col-12">
                    <label for="address" class="form-label fw-semibold small text-muted">Residential Address <span class="text-danger">*</span></label>
                    <textarea class="form-control bg-light" id="address" name="address" rows="3" required placeholder="Complete home address..."></textarea>
                </div>
            </div>

            <!-- Section: Notes -->
            <h5 class="fw-bold text-secondary mb-3 border-bottom pb-2"><i class="fa-solid fa-note-sticky me-2 text-muted"></i>Additional Context</h5>
            <div class="row g-4">
                <div class="col-12">
                    <label for="notes" class="form-label fw-semibold small text-muted">Special Notes / Medical Context (Optional)</label>
                    <textarea class="form-control bg-light" id="notes" name="notes" rows="2" placeholder="e.g. Parents requested morning shift, transfer certificate attached..."></textarea>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="d-flex justify-content-end gap-3 mt-5 border-top pt-4">
                <a href="applications.php" class="btn btn-outline-secondary px-4 py-2">Cancel</a>
                <button type="submit" class="btn btn-primary px-5 py-2 d-flex align-items-center gap-2" id="save-btn">
                    <span>Submit Application</span>
                    <div class="spinner-border spinner-border-sm d-none" role="status" id="btn-spinner">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Inject AJAX scripts
$extraJS = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const addForm = document.getElementById("add-applicant-form");
    const saveBtn = document.getElementById("save-btn");
    const btnSpinner = document.getElementById("btn-spinner");
    const formAlert = document.getElementById("form-alert");

    addForm.addEventListener("submit", function(e) {
        e.preventDefault();

        // Clear alerts
        formAlert.classList.add("d-none");
        formAlert.classList.remove("alert-danger", "alert-success");
        formAlert.textContent = "";

        // Set loading states
        saveBtn.disabled = true;
        btnSpinner.classList.remove("d-none");

        const formData = new FormData(addForm);

        fetch("../../ajax/admission.php", {
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
                    window.location.href = "applications.php";
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
