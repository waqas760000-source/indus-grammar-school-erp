<?php
/**
 * Indus Grammar School ERP - Fee Collection
 * Version 1.0.0
 */

$pageTitle = 'Collect Fee';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('fee_collect');

// Pre-load student if ID passed in URL
$student = null;
$unpaidChallans = [];
if (isset($_GET['student_id'])) {
    $sid = (int)$_GET['student_id'];
    $student = Student::findById($sid);
    if ($student) {
        $challans = Fee::allChallans(['student_id' => $sid]);
        $unpaidChallans = array_filter($challans, fn($c) => in_array($c['status'], ['Unpaid', 'Overdue']));
    }
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-hand-holding-dollar me-2 text-success"></i>Collect Fee</h3>
    </div>
</div>

<div class="row g-4">
    <!-- Search / Select Student -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-body p-4">
                <h6 class="fw-bold text-secondary mb-3">Find Student</h6>
                <div class="position-relative">
                    <input type="text" class="form-control" id="studentSearch" placeholder="Name or Admission No..." autocomplete="off">
                    <div id="searchResults" class="list-group position-absolute w-100 shadow" style="z-index:1000; display:none; max-height:300px; overflow-y:auto;"></div>
                </div>
            </div>
        </div>

        <?php if ($student): ?>
            <!-- Student Info Card -->
            <div class="card border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <div class="profile-avatar mx-auto mb-2" style="width:64px; height:64px; font-size:1.5rem;">
                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                        </div>
                        <h5 class="fw-bold mb-0"><?php echo sanitize($student['first_name'] . ' ' . $student['last_name']); ?></h5>
                        <div class="text-muted small">Admn No: <?php echo sanitize($student['admission_no']); ?></div>
                    </div>
                    <ul class="list-group list-group-flush mb-0">
                        <?php $class = SchoolClass::findById($student['class_id']); ?>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Class</span>
                            <span class="fw-semibold"><?php echo sanitize($class['class_name'] . ' - ' . $class['section']); ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Status</span>
                            <span class="badge bg-success rounded-pill"><?php echo sanitize($student['status']); ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Unpaid Challans</span>
                            <span class="badge bg-danger rounded-pill"><?php echo count($unpaidChallans); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info border-0 shadow-sm" style="border-radius:12px;">
                <i class="fa-solid fa-circle-info me-2"></i>Search for a student to view their dues and collect fees.
            </div>
        <?php endif; ?>
    </div>

    <!-- Collection Form -->
    <div class="col-lg-8">
        <?php if ($student): ?>
            <div class="card border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header bg-white border-bottom p-4">
                    <h5 class="fw-bold text-secondary mb-0">Record Payment</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($unpaidChallans)): ?>
                        <div class="text-center py-5">
                            <i class="fa-solid fa-circle-check fs-1 text-success mb-3"></i>
                            <h5 class="text-success fw-bold">All Clear!</h5>
                            <p class="text-muted mb-0">This student has no outstanding fee challans.</p>
                        </div>
                    <?php else: ?>
                        <form id="collectionForm">
                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action" value="collect_payment">
                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Select Challan to Pay</label>
                                <select class="form-select form-select-lg border-primary" name="challan_id" id="challanSelect" required>
                                    <option value="">— Select Pending Challan —</option>
                                    <?php foreach ($unpaidChallans as $c): ?>
                                        <option value="<?php echo $c['id']; ?>" data-amount="<?php echo $c['net_amount']; ?>" data-fine="<?php echo $c['fine_amount']; ?>">
                                            <?php echo sanitize($c['month']); ?> (Challan #<?php echo sanitize($c['challan_no']); ?>) — Due: <?php echo date('d M Y', strtotime($c['due_date'])); ?> — Rs. <?php echo number_format($c['net_amount'], 2); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Amount to Collect (Rs.)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 text-muted">Rs.</span>
                                        <input type="number" class="form-control border-start-0 ps-0 fw-bold text-success fs-5" name="amount_paid" id="amountPaid" min="1" step="0.01" required readonly>
                                    </div>
                                    <small class="text-muted d-block mt-1">Amount is locked to full challan amount.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Payment Date</label>
                                    <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Payment Method</label>
                                    <select class="form-select" name="payment_method" required>
                                        <option value="Cash">Cash</option>
                                        <option value="Bank">Bank Transfer / Deposit</option>
                                        <option value="Online">Online Payment</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Remarks (Optional)</label>
                                    <input type="text" class="form-control" name="remarks" placeholder="e.g. Paid by father">
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <div>
                                    <a href="challan.php" class="text-decoration-none small text-primary"><i class="fa-solid fa-eye me-1"></i>View All Challans</a>
                                </div>
                                <button type="submit" class="btn btn-success px-5 py-2 fw-bold" id="btnCollect">
                                    <i class="fa-solid fa-check-circle me-2"></i>Confirm Collection
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm" style="border-radius:12px; height: 100%; min-height: 300px;">
                <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-5">
                    <i class="fa-solid fa-magnifying-glass fs-1 text-muted opacity-25 mb-3"></i>
                    <h5 class="text-muted">No Student Selected</h5>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-body text-center p-5">
                <i class="fa-solid fa-circle-check fs-1 text-success mb-3"></i>
                <h4 class="fw-bold mb-2">Payment Successful!</h4>
                <p class="text-muted mb-4">Receipt <strong id="receiptNoTxt"></strong> generated.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="collection.php" class="btn btn-outline-secondary px-4">New Collection</a>
                    <a href="#" id="btnPrintReceipt" class="btn btn-primary px-4"><i class="fa-solid fa-print me-2"></i>Print Receipt</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="colToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="colToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("colToast");
    const m = document.getElementById("colToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Search Student AJAX
    const searchInput = document.getElementById("studentSearch");
    const searchResults = document.getElementById("searchResults");
    
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
                            a.href = "collection.php?student_id=" + s.id;
                            a.className = "list-group-item list-group-item-action d-flex justify-content-between align-items-center";
                            const duesBadge = s.dues > 0 ? `<span class="badge bg-danger rounded-pill">Dues: Rs. ${parseFloat(s.dues).toLocaleString()}</span>` : `<span class="badge bg-success rounded-pill">Clear</span>`;
                            a.innerHTML = `
                                <div>
                                    <div class="fw-semibold text-dark">${s.first_name} ${s.last_name}</div>
                                    <small class="text-muted">${s.admission_no} &middot; ${s.class_name} ${s.section}</small>
                                </div>
                                ${duesBadge}
                            `;
                            searchResults.appendChild(a);
                        });
                        searchResults.style.display = "block";
                    } else {
                        searchResults.innerHTML = `<div class="list-group-item text-muted text-center py-3">No active students found.</div>`;
                        searchResults.style.display = "block";
                    }
                });
        }, 300);
    });

    // Hide search on document click
    document.addEventListener("click", function(e) {
        if (searchInput && e.target !== searchInput) searchResults.style.display = "none";
    });

    // Auto-fill amount based on selected challan
    const challanSelect = document.getElementById("challanSelect");
    const amountInput = document.getElementById("amountPaid");
    challanSelect?.addEventListener("change", function() {
        const option = this.options[this.selectedIndex];
        if (option.value) {
            amountInput.value = option.dataset.amount;
        } else {
            amountInput.value = "";
        }
    });

    // Form Submission
    const form = document.getElementById("collectionForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnCollect");
            btn.disabled = true;
            btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Processing...\';
            
            fetch("../../ajax/fees.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        document.getElementById("receiptNoTxt").textContent = data.receipt_no;
                        document.getElementById("btnPrintReceipt").href = "../../templates/receipt.php?receipt_no=" + data.receipt_no;
                        new bootstrap.Modal(document.getElementById("receiptModal")).show();
                    } else {
                        showToast(data.message, false);
                        btn.disabled = false;
                        btn.innerHTML = \'<i class="fa-solid fa-check-circle me-2"></i>Confirm Collection\';
                    }
                })
                .catch(() => {
                    showToast("Network error.", false);
                    btn.disabled = false;
                    btn.innerHTML = \'<i class="fa-solid fa-check-circle me-2"></i>Confirm Collection\';
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
