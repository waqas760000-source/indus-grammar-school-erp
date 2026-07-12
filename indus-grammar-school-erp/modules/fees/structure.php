<?php
/**
 * Indus Grammar School ERP - Fee Structure Submodule
 * Version 3.0.0
 */

$pageTitle = 'Fee Structure';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('fee_view');

$classes = SchoolClass::all();

// Filter values
$filterType = sanitize($_GET['filter_type'] ?? '');
$filterClass = isset($_GET['filter_class']) ? (int)$_GET['filter_class'] : 0;

$filters = [];
if ($filterType !== '') $filters['academic_type'] = $filterType;
if ($filterClass > 0) $filters['class_id'] = $filterClass;

$structures = Fee::allStructures($filters);
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-list-ul me-2 text-primary"></i>Fee Structure</h3>
    </div>
</div>

<div class="row g-4">
    <!-- Form Side: Add/Edit Structure -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-3" id="formHeader">Add Fee Structure</h5>
                <form id="feeStructureForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_structure">
                    <input type="hidden" name="id" id="structureId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Academic Type</label>
                        <select class="form-select" name="academic_type" id="structType" required>
                            <option value="School">School</option>
                            <option value="Academy">Academy</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Class</label>
                        <select class="form-select" name="class_id" id="structClass" required>
                            <option value="">— Select Class —</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold text-muted mb-3">Fee Heads (Rs.)</h6>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Admission Fee</label>
                            <input type="number" class="form-control" name="admission_fee" id="valAdmission" min="0" value="0" step="0.01">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Tuition Fee</label>
                            <input type="number" class="form-control fw-bold text-primary" name="tuition_fee" id="valTuition" min="0" value="0" step="0.01" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Computer Fee</label>
                            <input type="number" class="form-control" name="computer_fee" id="valComputer" min="0" value="0" step="0.01">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Exam Fee</label>
                            <input type="number" class="form-control" name="exam_fee" id="valExam" min="0" value="0" step="0.01">
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Transport Fee</label>
                            <input type="number" class="form-control" name="transport_fee" id="valTransport" min="0" value="0" step="0.01">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Annual Charges</label>
                            <input type="number" class="form-control" name="annual_charges" id="valAnnual" min="0" value="0" step="0.01">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Security Deposit</label>
                            <input type="number" class="form-control" name="security_deposit" id="valSecurity" min="0" value="0" step="0.01">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Other Charges</label>
                            <input type="number" class="form-control" name="other_charges" id="valOther" min="0" value="0" step="0.01">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status" id="structStatus">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100" id="btnSave">
                            <i class="fa-solid fa-check me-2"></i>Save Plan
                        </button>
                        <button type="button" class="btn btn-outline-secondary w-50" id="btnReset">
                            Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Table Side: Filter & List -->
    <div class="col-lg-8">
        <!-- Filter Card -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-body p-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold text-muted">Academic Type</label>
                        <select class="form-select" name="filter_type">
                            <option value="">All Types</option>
                            <option value="School" <?php echo ($filterType === 'School') ? 'selected' : ''; ?>>School</option>
                            <option value="Academy" <?php echo ($filterType === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold text-muted">Class</label>
                        <select class="form-select" name="filter_class">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($filterClass == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-search me-1"></i>Search</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table List -->
        <div class="custom-table-card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table custom-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Class</th>
                            <th>Tuition Fee</th>
                            <th>Admission Fee</th>
                            <th>Computer Fee</th>
                            <th>Exam Fee</th>
                            <th>Transport Fee</th>
                            <th>Status</th>
                            <?php if (hasPermission('fee_manage')): ?>
                            <th class="text-end">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($structures)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">No fee structures defined.</td>
                            </tr>
                        <?php else: foreach ($structures as $s): 
                            $totalAmount = (float)$s['tuition_fee'] + (float)$s['admission_fee'] + (float)$s['computer_fee'] 
                                         + (float)$s['exam_fee'] + (float)$s['transport_fee'] + (float)$s['annual_charges'] 
                                         + (float)$s['security_deposit'] + (float)$s['other_charges'];
                        ?>
                            <tr id="row-<?php echo $s['id']; ?>"
                                data-id="<?php echo $s['id']; ?>"
                                data-type="<?php echo sanitize($s['academic_type']); ?>"
                                data-class="<?php echo $s['class_id']; ?>"
                                data-admission="<?php echo $s['admission_fee']; ?>"
                                data-tuition="<?php echo $s['tuition_fee']; ?>"
                                data-computer="<?php echo $s['computer_fee']; ?>"
                                data-exam="<?php echo $s['exam_fee']; ?>"
                                data-transport="<?php echo $s['transport_fee']; ?>"
                                data-annual="<?php echo $s['annual_charges']; ?>"
                                data-security="<?php echo $s['security_deposit']; ?>"
                                data-other="<?php echo $s['other_charges']; ?>"
                                data-status="<?php echo sanitize($s['status']); ?>"
                            >
                                <td>
                                    <span class="badge bg-<?php echo $s['academic_type'] === 'School' ? 'primary-soft text-primary' : 'success-soft text-success'; ?> px-2 py-1 rounded">
                                        <?php echo sanitize($s['academic_type']); ?>
                                    </span>
                                </td>
                                <td class="fw-semibold text-dark"><?php echo sanitize($s['class_name'] . ' - ' . $s['section']); ?></td>
                                <td class="fw-bold">Rs. <?php echo number_format($s['tuition_fee'], 0); ?></td>
                                <td>Rs. <?php echo number_format($s['admission_fee'], 0); ?></td>
                                <td>Rs. <?php echo number_format($s['computer_fee'], 0); ?></td>
                                <td>Rs. <?php echo number_format($s['exam_fee'], 0); ?></td>
                                <td>Rs. <?php echo number_format($s['transport_fee'], 0); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $s['status'] === 'Active' ? 'success' : 'secondary'; ?> rounded-pill small">
                                        <?php echo sanitize($s['status']); ?>
                                    </span>
                                </td>
                                <?php if (hasPermission('fee_manage')): ?>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary btn-edit-structure" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger btn-delete-structure" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notifications -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="structToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="structToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("structToast");
    const m = document.getElementById("structToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("feeStructureForm");
    const formHeader = document.getElementById("formHeader");
    const structureId = document.getElementById("structureId");
    const btnSave = document.getElementById("btnSave");
    const btnReset = document.getElementById("btnReset");

    // Form Save/Update Submit
    form?.addEventListener("submit", function(e) {
        e.preventDefault();
        btnSave.disabled = true;
        btnSave.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Saving...\';
        
        fetch("../../ajax/fees.php", {
            method: "POST",
            body: new FormData(form)
        })
        .then(r => r.json())
        .then(data => {
            showToast(data.message, data.success);
            if (data.success) {
                setTimeout(() => location.reload(), 1200);
            } else {
                btnSave.disabled = false;
                btnSave.innerHTML = \'<i class="fa-solid fa-check me-2"></i>Save Plan\';
            }
        })
        .catch(() => {
            showToast("Network connection error.", false);
            btnSave.disabled = false;
            btnSave.innerHTML = \'<i class="fa-solid fa-check me-2"></i>Save Plan\';
        });
    });

    // Populate Edit values
    document.querySelectorAll(".btn-edit-structure").forEach(btn => {
        btn.addEventListener("click", function() {
            const tr = this.closest("tr");
            
            formHeader.textContent = "Edit Fee Structure";
            structureId.value = tr.dataset.id;
            
            document.getElementById("structType").value = tr.dataset.type;
            document.getElementById("structClass").value = tr.dataset.class;
            document.getElementById("valAdmission").value = tr.dataset.admission;
            document.getElementById("valTuition").value = tr.dataset.tuition;
            document.getElementById("valComputer").value = tr.dataset.computer;
            document.getElementById("valExam").value = tr.dataset.exam;
            document.getElementById("valTransport").value = tr.dataset.transport;
            document.getElementById("valAnnual").value = tr.dataset.annual;
            document.getElementById("valSecurity").value = tr.dataset.security;
            document.getElementById("valOther").value = tr.dataset.other;
            document.getElementById("structStatus").value = tr.dataset.status;
            
            btnSave.innerHTML = \'<i class="fa-solid fa-floppy-disk me-2"></i>Update Plan\';
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
    });

    // Reset Form
    btnReset?.addEventListener("click", function() {
        form.reset();
        formHeader.textContent = "Add Fee Structure";
        structureId.value = "";
        btnSave.innerHTML = \'<i class="fa-solid fa-check me-2"></i>Save Plan\';
    });

    // Delete Structure
    document.querySelectorAll(".btn-delete-structure").forEach(btn => {
        btn.addEventListener("click", function() {
            const tr = this.closest("tr");
            const id = tr.dataset.id;
            
            if (!confirm("Are you sure you want to delete this fee structure? This cannot be undone.")) return;
            
            const fd = new FormData();
            fd.append("action", "delete_structure");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/fees.php", {
                method: "POST",
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                showToast(data.message, data.success);
                if (data.success) {
                    tr.style.transition = "all 0.5s ease";
                    tr.style.opacity = 0;
                    setTimeout(() => tr.remove(), 500);
                }
            });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php';
?>
