<?php
/**
 * Indus Grammar School ERP - Fee Discounts Management
 * Version 1.0.0
 */

$pageTitle = 'Fee Discounts';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('fee_view');

// Fetch recent active discounts
try {
    $db = Database::getConnection();
    $stmt = $db->query("
        SELECT fd.*, s.first_name, s.last_name, s.admission_no, c.class_name, c.section
        FROM fee_discounts fd
        JOIN students s ON fd.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        WHERE fd.is_active = 1
        ORDER BY fd.created_at DESC
        LIMIT 50
    ");
    $recentDiscounts = $stmt->fetchAll();
} catch (Exception $e) {
    $recentDiscounts = [];
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-tags me-2 text-primary"></i>Fee Discounts</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if (hasPermission('fee_manage')): ?>
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#applyDiscountModal">
            <i class="fa-solid fa-plus me-2"></i>Apply Discount
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-0 text-secondary">Active Discounts</h5>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Admission No</th>
                    <th>Class</th>
                    <th>Discount Type</th>
                    <th>Value</th>
                    <th>Reason</th>
                    <th>Date Applied</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentDiscounts)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No active discounts found.</td></tr>
                <?php else: foreach ($recentDiscounts as $d): ?>
                    <tr>
                        <td class="fw-semibold text-dark"><?php echo sanitize($d['first_name'] . ' ' . $d['last_name']); ?></td>
                        <td><code class="text-muted"><?php echo sanitize($d['admission_no']); ?></code></td>
                        <td><?php echo sanitize($d['class_name'] . ' ' . $d['section']); ?></td>
                        <td><span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill"><?php echo sanitize($d['discount_type']); ?></span></td>
                        <td class="fw-bold">
                            <?php 
                                if ($d['percentage'] > 0) echo $d['percentage'] . '%';
                                elseif ($d['flat_amount'] > 0) echo 'Rs. ' . number_format($d['flat_amount'], 2);
                            ?>
                        </td>
                        <td><span class="text-muted" title="<?php echo sanitize($d['reason']); ?>"><?php echo sanitize(mb_substr($d['reason'],0,30)) . (mb_strlen($d['reason']) > 30 ? '…' : ''); ?></span></td>
                        <td><?php echo date('d M Y', strtotime($d['created_at'])); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Apply Discount Modal -->
<?php if (hasPermission('fee_manage')): ?>
<div class="modal fade" id="applyDiscountModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-tag me-2 text-primary"></i>Apply Discount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="applyDiscountForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="apply_discount">
                    
                    <div class="mb-3 position-relative">
                        <label class="form-label small fw-semibold">Search Student</label>
                        <input type="text" class="form-control" id="discStudentSearch" placeholder="Name or Admission No..." autocomplete="off">
                        <input type="hidden" name="student_id" id="discStudentId">
                        <div id="discSearchResults" class="list-group position-absolute w-100 shadow" style="z-index:1000; display:none; max-height:200px; overflow-y:auto;"></div>
                    </div>
                    
                    <div id="discSelectedInfo" class="mb-3 d-none">
                        <div class="alert alert-secondary py-2 border-0 mb-0 d-flex justify-content-between align-items-center">
                            <span class="fw-semibold text-dark" id="discStudentName"></span>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" id="clearDiscStudent">Clear</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Discount Type</label>
                        <select class="form-select" name="discount_type" required>
                            <option value="Sibling">Sibling Discount</option>
                            <option value="Staff Child">Staff Child</option>
                            <option value="Scholarship">Scholarship</option>
                            <option value="Special">Special Concession</option>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Percentage (%)</label>
                            <input type="number" class="form-control" name="percentage" min="0" max="100" step="0.01" placeholder="e.g. 50">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">OR Flat Amount (Rs.)</label>
                            <input type="number" class="form-control" name="flat_amount" min="0" step="0.01" placeholder="e.g. 1500">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reason (Optional)</label>
                        <input type="text" class="form-control" name="reason">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="applyDiscountForm" class="btn btn-primary px-4" id="btnApplyDisc">Apply</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast -->
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
    // Search Student AJAX for Modal
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
                            a.className = "list-group-item list-group-item-action";
                            a.innerHTML = `<div class="fw-semibold">${s.first_name} ${s.last_name}</div><small class="text-muted">${s.admission_no} &middot; ${s.class_name} ${s.section}</small>`;
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

    // Form Submission
    const form = document.getElementById("applyDiscountForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            if (!studentIdInput.value) { showToast("Please select a student.", false); return; }
            
            const pct = parseFloat(form.percentage.value) || 0;
            const flat = parseFloat(form.flat_amount.value) || 0;
            if (pct <= 0 && flat <= 0) {
                showToast("Please provide either a percentage or a flat amount.", false);
                return;
            }

            const btn = document.getElementById("btnApplyDisc");
            btn.disabled = true;
            btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Applying...\';
            
            fetch("../../ajax/fees.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Apply"; }
                })
                .catch(() => {
                    showToast("Network error.", false);
                    btn.disabled = false; btn.innerHTML = "Apply";
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
