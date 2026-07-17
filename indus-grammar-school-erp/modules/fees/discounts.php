<?php
/**
 * Indus Grammar School ERP - Student Fee Assignments & Discounts Management
 * Version 4.0.0
 */

$pageTitle = 'Student Discounts';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('fee_view');

// Fetch recent student assignments
try {
    $db = Database::getConnection();
    
    // Ensure all active students have structure assignments initialized
    $studentsStmt = $db->query("SELECT id FROM students WHERE status = 'Active'");
    $studentIds = $studentsStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($studentIds as $sid) {
        Fee::ensureStudentAssignment($sid);
    }

    $stmt = $db->query("
        SELECT sfa.*, s.first_name, s.last_name, s.admission_no, c.class_name, c.section, fs.academic_type, fs.academic_year
        FROM student_fee_assignments sfa
        JOIN students s ON sfa.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        JOIN fee_structure fs ON sfa.fee_structure_id = fs.id
        ORDER BY sfa.created_at DESC
        LIMIT 50
    ");
    $recentAssignments = $stmt->fetchAll();
} catch (Exception $e) {
    $recentAssignments = [];
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-tags me-2 text-primary"></i>Student Fee Discounts</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if (hasPermission('fee_manage')): ?>
        <button class="btn btn-primary px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#applyDiscountModal">
            <i class="fa-solid fa-plus me-2"></i>Apply / Edit Discount
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-0 text-secondary">Student Assignments Ledger</h5>
        <small class="text-muted">Every registered student must have one fee structure assignment. Custom discounts modify future monthly ledgers.</small>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Admission No</th>
                    <th>Class</th>
                    <th>Fee Structure</th>
                    <th>Discount Pct (%)</th>
                    <th>Discount Flat (Rs.)</th>
                    <th>Discount Reason</th>
                    <th>Status</th>
                    <?php if (hasPermission('fee_manage')): ?>
                    <th class="text-end">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentAssignments)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No student fee structure assignments found.</td></tr>
                <?php else: foreach ($recentAssignments as $d): ?>
                    <tr id="row-<?php echo $d['id']; ?>">
                        <td class="fw-bold text-dark"><?php echo sanitize($d['first_name'] . ' ' . $d['last_name']); ?></td>
                        <td><code><?php echo sanitize($d['admission_no']); ?></code></td>
                        <td><?php echo sanitize($d['class_name'] . ' - ' . $d['section']); ?></td>
                        <td>
                            <span class="badge bg-primary-soft text-primary px-2 py-1 rounded">
                                <?php echo sanitize($d['academic_type'] . ' (' . $d['academic_year'] . ')'); ?>
                            </span>
                        </td>
                        <td class="fw-bold text-success"><?php echo (float)$d['discount_percentage'] > 0 ? (float)$d['discount_percentage'] . '%' : '—'; ?></td>
                        <td class="fw-bold text-success"><?php echo (float)$d['discount_flat'] > 0 ? 'Rs. ' . number_format($d['discount_flat'], 2) : '—'; ?></td>
                        <td><span class="text-muted small" title="<?php echo sanitize($d['discount_reason']); ?>"><?php echo displayValue($d['discount_reason'], '—'); ?></span></td>
                        <td>
                            <span class="badge bg-<?php echo $d['status'] === 'Active' ? 'success' : 'secondary'; ?> rounded-pill small">
                                <?php echo sanitize($d['status']); ?>
                            </span>
                        </td>
                        <?php if (hasPermission('fee_manage')): ?>
                        <td class="text-end">
                            <?php if ((float)$d['discount_percentage'] > 0 || (float)$d['discount_flat'] > 0): ?>
                            <button class="btn btn-sm btn-outline-danger btn-delete-discount" data-id="<?php echo $d['id']; ?>" title="Clear Discount">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                            <?php else: ?>
                            <span class="text-muted small">None</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Apply Discount -->
<?php if (hasPermission('fee_manage')): ?>
<div class="modal fade" id="applyDiscountModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-tag me-2 text-primary"></i>Apply / Edit Student Discount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="applyDiscountForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="apply_discount">
                    
                    <div class="mb-3 position-relative">
                        <label class="form-label small fw-semibold">Search Student</label>
                        <input type="text" class="form-control" id="discStudentSearch" placeholder="Enter Name or Admission No..." autocomplete="off">
                        <input type="hidden" name="student_id" id="discStudentId">
                        <div id="discSearchResults" class="list-group position-absolute w-100 shadow" style="z-index:1000; display:none; max-height:200px; overflow-y:auto; border-radius: 8px;"></div>
                    </div>
                    
                    <div id="discSelectedInfo" class="mb-3 d-none">
                        <div class="alert alert-secondary py-2 border-0 mb-0 d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark small" id="discStudentName"></span>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" id="clearDiscStudent">Clear</button>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Discount Percentage (%)</label>
                            <input type="number" class="form-control" name="percentage" min="0" max="100" step="0.01" placeholder="e.g. 10">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">OR Flat Amount (Rs.)</label>
                            <input type="number" class="form-control" name="flat_amount" min="0" step="0.01" placeholder="e.g. 500">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Discount Reason / Justification</label>
                        <input type="text" class="form-control" name="reason" placeholder="e.g. Sibling discount, scholarship..." required>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="applyDiscountForm" class="btn btn-primary px-4" id="btnApplyDisc">Apply Settings</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="discToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="discToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("discToast");
    const m = document.getElementById("discToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Search Student AJAX
    const searchInput = document.getElementById("discStudentSearch");
    const searchResults = document.getElementById("discSearchResults");
    const studentIdInput = document.getElementById("discStudentId");
    const selectedInfo = document.getElementById("discSelectedInfo");
    const studentName = document.getElementById("discStudentName");
    const clearBtn = document.getElementById("clearDiscStudent");
    
    let searchTimeout;
    searchInput?.addEventListener("input", function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        if (q.length < 2) { searchResults.style.display = "none"; return; }
        
        searchTimeout = setTimeout(() => {
            const fd = new FormData();
            fd.append("action", "search_student");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("q", q);
            
            fetch("../../ajax/fees.php", { method: "POST", body: fd })
            .then(r => r.json())
            .then(data => {
                searchResults.innerHTML = "";
                if (data.success && data.students.length > 0) {
                    data.students.forEach(s => {
                        const a = document.createElement("a");
                        a.href = "#";
                        a.className = "list-group-item list-group-item-action py-2";
                        a.innerHTML = `<div class="fw-semibold text-dark">${s.first_name} ${s.last_name}</div><small class="text-muted">Admn No: ${s.admission_no} &middot; Class: ${s.class_name} ${s.section}</small>`;
                        a.addEventListener("click", function(e) {
                            e.preventDefault();
                            studentIdInput.value = s.id;
                            studentName.textContent = `${s.first_name} ${s.last_name} (${s.admission_no})`;
                            searchInput.parentElement.classList.add("d-none");
                            selectedInfo.classList.remove("d-none");
                            searchResults.style.display = "none";
                        });
                        searchResults.appendChild(a);
                    });
                    searchResults.style.display = "block";
                } else {
                    searchResults.innerHTML = \'<div class="list-group-item text-muted py-2 text-center small">No active students found.</div>\';
                    searchResults.style.display = "block";
                }
            });
        }, 300);
    });

    clearBtn?.addEventListener("click", function() {
        studentIdInput.value = "";
        searchInput.value = "";
        selectedInfo.classList.add("d-none");
        searchInput.parentElement.classList.remove("d-none");
    });

    // Form Submission: Apply Discount
    const form = document.getElementById("applyDiscountForm");
    form?.addEventListener("submit", function(e) {
        e.preventDefault();
        if (!studentIdInput.value) { showToast("Please select a student.", false); return; }

        const btn = document.getElementById("btnApplyDisc");
        btn.disabled = true;
        btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Saving...\';
        
        fetch("../../ajax/fees.php", { method: "POST", body: new FormData(form) })
        .then(r => r.json())
        .then(data => {
            showToast(data.message, data.success);
            if (data.success) {
                setTimeout(() => location.reload(), 1200);
            } else {
                btn.disabled = false;
                btn.innerHTML = "Apply Settings";
            }
        })
        .catch(() => {
            showToast("Network transmission error.", false);
            btn.disabled = false;
            btn.innerHTML = "Apply Settings";
        });
    });

    // Delete Discount
    document.querySelectorAll(".btn-delete-discount").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            const tr = this.closest("tr");
            
            if (!confirm("Are you sure you want to clear this discount? This will reset rates to default structure amounts.")) return;
            
            const fd = new FormData();
            fd.append("action", "delete_discount");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/fees.php", { method: "POST", body: fd })
            .then(r => r.json())
            .then(data => {
                showToast(data.message, data.success);
                if (data.success) {
                    setTimeout(() => location.reload(), 1000);
                }
            });
        });
    });
});
</script>';

include_once __DIR__ . '/../../includes/footer.php';
?>
