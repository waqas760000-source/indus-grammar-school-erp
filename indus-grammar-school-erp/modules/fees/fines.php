<?php
/**
 * Indus Grammar School ERP - Fee Fines Management
 * Version 1.0.0
 */

$pageTitle = 'Fee Fines';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('fee_view');

// Fetch recent active fines
try {
    $db = Database::getConnection();
    $stmt = $db->query("
        SELECT ff.*, s.first_name, s.last_name, s.admission_no, c.class_name, c.section
        FROM fee_fines ff
        JOIN students s ON ff.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        WHERE ff.status = 'Pending'
        ORDER BY ff.created_at DESC
        LIMIT 50
    ");
    $recentFines = $stmt->fetchAll();
} catch (Exception $e) {
    $recentFines = [];
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>Fee Fines</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if (hasPermission('fee_manage')): ?>
        <button class="btn btn-danger px-4" data-bs-toggle="modal" data-bs-target="#applyFineModal">
            <i class="fa-solid fa-plus me-2"></i>Apply Fine
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-0 text-secondary">Pending Fines (Unbilled)</h5>
        <small class="text-muted">These fines will be added to the student's next fee challan automatically.</small>
    </div>
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Admission No</th>
                    <th>Class</th>
                    <th>Fine Type</th>
                    <th>Amount</th>
                    <th>Reason</th>
                    <th>Date Applied</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentFines)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No pending fines found.</td></tr>
                <?php else: foreach ($recentFines as $f): ?>
                    <tr>
                        <td class="fw-semibold text-dark"><?php echo sanitize($f['first_name'] . ' ' . $f['last_name']); ?></td>
                        <td><code class="text-muted"><?php echo sanitize($f['admission_no']); ?></code></td>
                        <td><?php echo sanitize($f['class_name'] . ' ' . $f['section']); ?></td>
                        <td><span class="badge bg-danger-soft text-danger px-3 py-2 rounded-pill"><?php echo sanitize($f['fine_type']); ?></span></td>
                        <td class="fw-bold text-danger">Rs. <?php echo number_format($f['amount'], 2); ?></td>
                        <td><span class="text-muted" title="<?php echo sanitize($f['reason']); ?>"><?php echo sanitize(mb_substr($f['reason'],0,30)) . (mb_strlen($f['reason']) > 30 ? '…' : ''); ?></span></td>
                        <td><?php echo date('d M Y', strtotime($f['created_at'])); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Apply Fine Modal -->
<?php if (hasPermission('fee_manage')): ?>
<div class="modal fade" id="applyFineModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>Apply Fine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="applyFineForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="apply_fine">
                    
                    <div class="mb-3 position-relative">
                        <label class="form-label small fw-semibold">Search Student</label>
                        <input type="text" class="form-control" id="fineStudentSearch" placeholder="Name or Admission No..." autocomplete="off">
                        <input type="hidden" name="student_id" id="fineStudentId">
                        <div id="fineSearchResults" class="list-group position-absolute w-100 shadow" style="z-index:1000; display:none; max-height:200px; overflow-y:auto;"></div>
                    </div>
                    
                    <div id="fineSelectedInfo" class="mb-3 d-none">
                        <div class="alert alert-secondary py-2 border-0 mb-0 d-flex justify-content-between align-items-center">
                            <span class="fw-semibold text-dark" id="fineStudentName"></span>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" id="clearFineStudent">Clear</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Fine Type</label>
                        <select class="form-select" name="fine_type" required>
                            <option value="Late Payment">Late Payment</option>
                            <option value="Disciplinary">Disciplinary</option>
                            <option value="Damage to Property">Damage to Property</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Amount (Rs.)</label>
                        <input type="number" class="form-control text-danger fw-bold" name="amount" min="1" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reason</label>
                        <textarea class="form-control" name="reason" rows="2" placeholder="Describe the reason for the fine..." required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="applyFineForm" class="btn btn-danger px-4" id="btnApplyFine">Apply Fine</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="fineToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="fineToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("fineToast");
    const m = document.getElementById("fineToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Search Student AJAX for Modal
    const searchInput = document.getElementById("fineStudentSearch");
    const searchResults = document.getElementById("fineSearchResults");
    const studentIdInput = document.getElementById("fineStudentId");
    const selectedInfo = document.getElementById("fineSelectedInfo");
    const studentName = document.getElementById("fineStudentName");
    const clearBtn = document.getElementById("clearFineStudent");
    
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
    const form = document.getElementById("applyFineForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            if (!studentIdInput.value) { showToast("Please select a student.", false); return; }

            const btn = document.getElementById("btnApplyFine");
            btn.disabled = true;
            btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Applying...\';
            
            fetch("../../ajax/fees.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Apply Fine"; }
                })
                .catch(() => {
                    showToast("Network error.", false);
                    btn.disabled = false; btn.innerHTML = "Apply Fine";
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
