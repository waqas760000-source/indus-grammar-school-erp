<?php
/**
 * Indus Grammar School ERP - Fee Ledger Viewer / Generator
 * Version 4.0.0
 */

$pageTitle = 'Fee Ledger';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('fee_view');

// Fetch classes for dropdown filters
$classes = SchoolClass::all();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$filters = [
    'status'        => sanitize($_GET['status'] ?? ''),
    'academic_type' => sanitize($_GET['academic_type'] ?? ''),
    'class_id'      => isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0,
    'month'         => sanitize($_GET['month'] ?? ''),
    'search'        => sanitize($_GET['search'] ?? '')
];

$ledgerEntries = Fee::allChallans($filters, $limit, $offset);
$totalEntries = Fee::countChallans($filters);
$totalPages = ceil($totalEntries / $limit);

$statusBadge = [
    'Pending' => 'danger',
    'Paid'    => 'success',
    'Partial' => 'warning text-dark'
];
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Fee Ledger Sheets</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0 d-flex gap-2 justify-content-sm-end">
        <?php if (hasPermission('fee_manage')): ?>
        <button class="btn btn-outline-primary px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#generateBatchChallanModal">
            <i class="fa-solid fa-files-medical me-2"></i>Batch Class Ledger
        </button>
        <button class="btn btn-primary px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#generateChallanModal">
            <i class="fa-solid fa-plus me-2"></i>Generate Student Ledger
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Search & Filters -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Academic Type</label>
                <select class="form-select" name="academic_type">
                    <option value="">All Types</option>
                    <option value="School" <?php echo ($filters['academic_type'] === 'School') ? 'selected' : ''; ?>>School</option>
                    <option value="Academy" <?php echo ($filters['academic_type'] === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Class</label>
                <select class="form-select" name="class_id">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($filters['class_id'] == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Month</label>
                <input type="text" class="form-control" name="month" placeholder="e.g. July 2026" value="<?php echo $filters['month']; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?php echo ($filters['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="Paid" <?php echo ($filters['status'] === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                    <option value="Partial" <?php echo ($filters['status'] === 'Partial') ? 'selected' : ''; ?>>Partial</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Search Student</label>
                <input type="text" class="form-control" name="search" placeholder="Name, Admission No..." value="<?php echo $filters['search']; ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>
        </form>
    </div>
</div>

<!-- List of Ledger Entries -->
<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Ledger ID</th>
                    <th>Student Details</th>
                    <th>Month / Year</th>
                    <th>Due Date</th>
                    <th>Total Base</th>
                    <th>Discount</th>
                    <th>Net Payable</th>
                    <th>Paid Amount</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ledgerEntries)): ?>
                    <tr><td colspan="10" class="text-center py-5 text-muted">No monthly fee ledger entries matching filters.</td></tr>
                <?php else: foreach ($ledgerEntries as $c): 
                    $totalBase = (float)$c['tuition_fee'] + (float)$c['admission_fee'] + (float)$c['computer_fee'] 
                               + (float)$c['exam_fee'] + (float)$c['transport_fee'] + (float)$c['annual_charges'] 
                               + (float)$c['security_deposit'] + (float)$c['other_charges'];
                ?>
                    <tr>
                        <td><span class="fw-bold text-dark">#<?php echo str_pad($c['id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo sanitize($c['first_name'] . ' ' . $c['last_name']); ?></div>
                            <div class="text-muted small">Admn: <code><?php echo sanitize($c['admission_no']); ?></code> &middot; <?php echo sanitize($c['class_name'] . ' ' . $c['section']); ?> &middot; <?php echo sanitize($c['academic_type']); ?></div>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo sanitize($c['month']); ?></div>
                            <div class="text-muted small"><?php echo sanitize($c['academic_year']); ?></div>
                        </td>
                        <td><?php echo date('d M Y', strtotime($c['due_date'])); ?></td>
                        <td>Rs. <?php echo number_format($totalBase, 2); ?></td>
                        <td class="text-success">- Rs. <?php echo number_format($c['discount_amount'], 2); ?></td>
                        <td class="fw-bold text-dark">Rs. <?php echo number_format($c['total_payable'], 2); ?></td>
                        <td class="fw-bold text-success">Rs. <?php echo number_format($c['paid_amount'], 2); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $statusBadge[$c['status']] ?? 'secondary'; ?> px-3 py-2 rounded-pill fw-semibold">
                                <?php echo sanitize($c['status']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group">
                                <?php if ($c['status'] !== 'Paid' && hasPermission('fee_collect')): ?>
                                    <a href="collection.php?student_id=<?php echo $c['student_id']; ?>" class="btn btn-sm btn-success" title="Collect Payment">
                                        <i class="fa-solid fa-hand-holding-dollar"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="../../templates/challan.php?id=<?php echo $c['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Ledger Slip">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="p-3 border-top bg-light d-flex justify-content-between align-items-center">
            <span class="text-muted small">Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> &middot; (Total: <?php echo $totalEntries; ?>)</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($filters['status']); ?>&academic_type=<?php echo urlencode($filters['academic_type']); ?>&class_id=<?php echo $filters['class_id']; ?>&month=<?php echo urlencode($filters['month']); ?>&search=<?php echo urlencode($filters['search']); ?>">Previous</a></li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filters['status']); ?>&academic_type=<?php echo urlencode($filters['academic_type']); ?>&class_id=<?php echo $filters['class_id']; ?>&month=<?php echo urlencode($filters['month']); ?>&search=<?php echo urlencode($filters['search']); ?>"><?php echo $i; ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($filters['status']); ?>&academic_type=<?php echo urlencode($filters['academic_type']); ?>&class_id=<?php echo $filters['class_id']; ?>&month=<?php echo urlencode($filters['month']); ?>&search=<?php echo urlencode($filters['search']); ?>">Next</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Modal 1: Generate Single Month Student Ledger -->
<?php if (hasPermission('fee_manage')): ?>
<div class="modal fade" id="generateChallanModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Generate Student Ledger Month</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="generateChallanForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="generate_ledger_entry">
                    
                    <div class="mb-3 position-relative">
                        <label class="form-label small fw-semibold">Search Student</label>
                        <input type="text" class="form-control" id="genStudentSearch" placeholder="Enter Student Name or Admission No..." autocomplete="off">
                        <input type="hidden" name="student_id" id="genStudentId">
                        <div id="genSearchResults" class="list-group position-absolute w-100 shadow" style="z-index:1000; display:none; max-height:200px; overflow-y:auto; border-radius: 8px;"></div>
                    </div>
                    
                    <div id="selectedStudentInfo" class="mb-3 d-none">
                        <div class="alert alert-secondary py-2 border-0 mb-0 d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark small" id="genStudentName"></span>
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
                <button type="submit" form="generateChallanForm" class="btn btn-primary px-4" id="btnGenerate">Generate Ledger</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Generate Batch Class Ledger -->
<div class="modal fade" id="generateBatchChallanModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-files-medical me-2 text-primary"></i>Batch Class Ledger</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="generateBatchChallanForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="generate_class_ledger">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Academic Type</label>
                        <select class="form-select" name="academic_type" required>
                            <option value="School">School</option>
                            <option value="Academy">Academy</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Class</label>
                        <select class="form-select" name="class_id" required>
                            <option value="">— Select Class —</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Fee Month</label>
                            <select class="form-select" name="month" required>
                                <?php 
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
                <button type="submit" form="generateBatchChallanForm" class="btn btn-primary px-4" id="btnGenerateBatch">Generate Batch</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast Notification -->
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
    // Search Student inside Single Generation Modal
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

    // Form Submission: Single Ledger Entry
    const singleForm = document.getElementById("generateChallanForm");
    singleForm?.addEventListener("submit", function(e) {
        e.preventDefault();
        if (!studentIdInput.value) { showToast("Please search and select a student.", false); return; }
        
        const btn = document.getElementById("btnGenerate");
        btn.disabled = true;
        btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Generating...\';
        
        fetch("../../ajax/fees.php", { method: "POST", body: new FormData(singleForm) })
        .then(r => r.json())
        .then(data => {
            showToast(data.message, data.success);
            if (data.success) {
                setTimeout(() => location.reload(), 1200);
            } else {
                btn.disabled = false;
                btn.innerHTML = "Generate Ledger";
            }
        })
        .catch(() => {
            showToast("Network connection error.", false);
            btn.disabled = false;
            btn.innerHTML = "Generate Ledger";
        });
    });

    // Form Submission: Batch Ledger Entries
    const batchForm = document.getElementById("generateBatchChallanForm");
    batchForm?.addEventListener("submit", function(e) {
        e.preventDefault();
        const btn = document.getElementById("btnGenerateBatch");
        btn.disabled = true;
        btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-2"></span>Processing...\';
        
        fetch("../../ajax/fees.php", { method: "POST", body: new FormData(batchForm) })
        .then(r => r.json())
        .then(data => {
            showToast(data.message, data.success);
            if (data.success) {
                setTimeout(() => location.reload(), 1500);
            } else {
                btn.disabled = false;
                btn.innerHTML = "Generate Batch";
            }
        })
        .catch(() => {
            showToast("Network connection error.", false);
            btn.disabled = false;
            btn.innerHTML = "Generate Batch";
        });
    });
});
</script>';

include_once __DIR__ . '/../../includes/footer.php';
?>
