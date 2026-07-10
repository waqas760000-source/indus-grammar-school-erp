<?php
/**
 * Indus Grammar School ERP - Fee Challans
 * Version 1.0.0
 */

$pageTitle = 'Fee Challans';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('fee_view');

// Handle Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$filters = [
    'status'     => sanitize($_GET['status'] ?? ''),
    'search'     => sanitize($_GET['search'] ?? '')
];

$challans = Fee::allChallans($filters, $limit, $offset);
$statusBadge = [
    'Unpaid'  => 'warning',
    'Paid'    => 'success',
    'Partial' => 'info',
    'Overdue' => 'danger'
];
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Fee Challans</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if (hasPermission('fee_manage')): ?>
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#generateChallanModal">
            <i class="fa-solid fa-plus me-2"></i>Generate Challan
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="Unpaid" <?php echo ($filters['status'] === 'Unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                    <option value="Paid" <?php echo ($filters['status'] === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                    <option value="Overdue" <?php echo ($filters['status'] === 'Overdue') ? 'selected' : ''; ?>>Overdue</option>
                </select>
            </div>
            <div class="col-md-7">
                <input type="text" class="form-control" name="search" placeholder="Search by Student Name, Admission No, or Challan No..." value="<?php echo $filters['search']; ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-search me-2"></i>Search</button>
            </div>
        </form>
    </div>
</div>

<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover">
            <thead>
                <tr>
                    <th>Challan No</th>
                    <th>Student</th>
                    <th>Month / Year</th>
                    <th>Due Date</th>
                    <th>Net Amount</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($challans)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No fee challans found matching your criteria.</td></tr>
                <?php else: foreach ($challans as $c): ?>
                    <tr>
                        <td><span class="fw-bold text-dark"><?php echo sanitize($c['challan_no']); ?></span></td>
                        <td>
                            <div class="fw-semibold text-dark"><?php echo sanitize($c['first_name'] . ' ' . $c['last_name']); ?></div>
                            <div class="text-muted small">Admn: <?php echo sanitize($c['admission_no']); ?> &middot; <?php echo sanitize($c['class_name'] . ' ' . $c['section']); ?></div>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo sanitize($c['month']); ?></div>
                            <div class="text-muted small"><?php echo sanitize($c['academic_year']); ?></div>
                        </td>
                        <td><?php echo date('d M Y', strtotime($c['due_date'])); ?></td>
                        <td class="fw-bold">Rs. <?php echo number_format($c['net_amount'], 2); ?></td>
                        <td>
                            <span class="badge badge-soft-<?php echo $statusBadge[$c['status']] ?? 'secondary'; ?> px-3 py-2 rounded-pill">
                                <?php echo sanitize($c['status']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?php if ($c['status'] !== 'Paid' && hasPermission('fee_collect')): ?>
                                <a href="collection.php?student_id=<?php echo $c['student_id']; ?>" class="btn btn-sm btn-success" title="Collect Payment"><i class="fa-solid fa-hand-holding-dollar"></i></a>
                            <?php endif; ?>
                            <!-- TODO: Add Print Challan view (template not yet designed, placeholder link) -->
                            <a href="#" class="btn btn-sm btn-outline-secondary" title="Print Challan"><i class="fa-solid fa-print"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination (Basic) -->
    <?php if (count($challans) == $limit || $page > 1): ?>
        <div class="p-3 border-top bg-light d-flex justify-content-between align-items-center">
            <span class="text-muted small">Showing page <?php echo $page; ?></span>
            <div class="btn-group">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($filters['status']); ?>&search=<?php echo urlencode($filters['search']); ?>" class="btn btn-sm btn-outline-secondary">Previous</a>
                <?php endif; ?>
                <?php if (count($challans) == $limit): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($filters['status']); ?>&search=<?php echo urlencode($filters['search']); ?>" class="btn btn-sm btn-outline-secondary">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Generate Challan Modal -->
<?php if (hasPermission('fee_manage')): ?>
<div class="modal fade" id="generateChallanModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Generate Challan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="generateChallanForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="generate_challan">
                    
                    <div class="mb-3 position-relative">
                        <label class="form-label small fw-semibold">Search Student</label>
                        <input type="text" class="form-control" id="genStudentSearch" placeholder="Name or Admission No..." autocomplete="off">
                        <input type="hidden" name="student_id" id="genStudentId">
                        <div id="genSearchResults" class="list-group position-absolute w-100 shadow" style="z-index:1000; display:none; max-height:200px; overflow-y:auto;"></div>
                    </div>
                    
                    <div id="selectedStudentInfo" class="mb-3 d-none">
                        <div class="alert alert-secondary py-2 border-0 mb-0 d-flex justify-content-between align-items-center">
                            <span class="fw-semibold text-dark" id="genStudentName"></span>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" id="clearStudentSelection">Clear</button>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Fee Month</label>
                            <select class="form-select" name="month" required>
                                <?php 
                                    $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                                    $currentMonth = date('F');
                                    foreach ($months as $m) {
                                        $selected = ($m === $currentMonth) ? 'selected' : '';
                                        echo "<option value=\"$m " . date('Y') . "\" $selected>$m " . date('Y') . "</option>";
                                    }
                                ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Due Date</label>
                            <input type="date" class="form-control" name="due_date" value="<?php echo date('Y-m-15'); ?>" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="generateChallanForm" class="btn btn-primary px-4" id="btnGenerate">Generate Now</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="challanToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="challanToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("challanToast");
    const m = document.getElementById("challanToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Search Student AJAX for Modal
    const searchInput = document.getElementById("genStudentSearch");
    const searchResults = document.getElementById("genSearchResults");
    const studentIdInput = document.getElementById("genStudentId");
    const selectedInfo = document.getElementById("selectedStudentInfo");
    const studentName = document.getElementById("genStudentName");
    const clearBtn = document.getElementById("clearStudentSelection");
    
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
    const form = document.getElementById("generateChallanForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            if (!studentIdInput.value) { showToast("Please select a student.", false); return; }
            
            const btn = document.getElementById("btnGenerate");
            btn.disabled = true;
            btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Generating...\';
            
            fetch("../../ajax/fees.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1500);
                    else { btn.disabled = false; btn.innerHTML = "Generate Now"; }
                })
                .catch(() => {
                    showToast("Network error.", false);
                    btn.disabled = false; btn.innerHTML = "Generate Now";
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
