<?php
/**
 * Indus Grammar School ERP - Normalized Fee Collection Cashier Terminal (Ledger V2)
 * Version 4.0.0
 */

$pageTitle = 'Collect Fee';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';

// Check authorization (Super Admin, School Admin, Accountant)
$userRole = $_SESSION['role_code'] ?? '';
$isAuthorized = in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, ROLE_ACCOUNTANT]) || hasPermission('fee_collect');

if (!$isAuthorized) {
    $_SESSION['flash_error'] = 'Unauthorized access: Only authorized cashier staff can access this terminal.';
    redirect(APP_URL . '/dashboard.php');
}

// Initial stats fetch for dashboard counters
$dash = Fee::getCashierDashboard();
$preloadStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-cash-register me-2 text-success"></i>Cashier Collection Panel</h3>
    </div>
</div>

<!-- 1. Dashboard Statistics Cards -->
<div class="row g-3 mb-4">
    <!-- Today Collection -->
    <div class="col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm" style="border-radius:12px; background: linear-gradient(135deg, #ffffff, #f0fdf4);">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-success-soft text-success p-2 rounded-circle me-2"><i class="fa-solid fa-money-bill-trend-up fs-6"></i></span>
                    <span class="small text-muted fw-semibold">Today Collected</span>
                </div>
                <h5 class="fw-bold text-success mb-0" id="dashTodayColl">Rs. <?php echo number_format($dash['today_collection'], 0); ?></h5>
            </div>
        </div>
    </div>
    <!-- Today Pending -->
    <div class="col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm" style="border-radius:12px; background: linear-gradient(135deg, #ffffff, #fff5f5);">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-danger-soft text-danger p-2 rounded-circle me-2"><i class="fa-solid fa-clock-rotate-left fs-6"></i></span>
                    <span class="small text-muted fw-semibold">Today Pending</span>
                </div>
                <h5 class="fw-bold text-danger mb-0" id="dashTodayPending">Rs. <?php echo number_format($dash['today_pending'], 0); ?></h5>
            </div>
        </div>
    </div>
    <!-- Monthly Collection -->
    <div class="col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm" style="border-radius:12px; background: linear-gradient(135deg, #ffffff, #eef2ff);">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-primary-soft text-primary p-2 rounded-circle me-2"><i class="fa-solid fa-wallet fs-6"></i></span>
                    <span class="small text-muted fw-semibold">Month Total</span>
                </div>
                <h5 class="fw-bold text-primary mb-0" id="dashMonthColl">Rs. <?php echo number_format($dash['monthly_collection'], 0); ?></h5>
            </div>
        </div>
    </div>
    <!-- Outstanding Dues -->
    <div class="col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm" style="border-radius:12px; background: linear-gradient(135deg, #ffffff, #fffbeb);">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-warning-soft text-warning p-2 rounded-circle me-2"><i class="fa-solid fa-triangle-exclamation fs-6"></i></span>
                    <span class="small text-muted fw-semibold">Outstanding</span>
                </div>
                <h5 class="fw-bold text-warning mb-0" id="dashOutstanding">Rs. <?php echo number_format($dash['outstanding_dues'], 0); ?></h5>
            </div>
        </div>
    </div>
    <!-- Paid Students -->
    <div class="col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-secondary-soft text-secondary p-2 rounded-circle me-2"><i class="fa-solid fa-user-check fs-6"></i></span>
                    <span class="small text-muted fw-semibold">Paid Students</span>
                </div>
                <h5 class="fw-bold text-dark mb-0" id="dashPaidStudents"><?php echo $dash['paid_students']; ?></h5>
            </div>
        </div>
    </div>
    <!-- Pending Students -->
    <div class="col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-secondary-soft text-secondary p-2 rounded-circle me-2"><i class="fa-solid fa-user-minus fs-6"></i></span>
                    <span class="small text-muted fw-semibold">Pending Students</span>
                </div>
                <h5 class="fw-bold text-secondary mb-0" id="dashPendingStudents"><?php echo $dash['pending_students']; ?></h5>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Search & Student Profile Card -->
    <div class="col-lg-4">
        <!-- Student Search Form -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-body p-4">
                <h6 class="fw-bold text-secondary mb-3">Student Search</h6>
                <div class="position-relative">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" id="cashierStudentSearch" placeholder="Admission No, Roll No, or Name..." autocomplete="off">
                    </div>
                    <div id="searchSpinner" class="spinner-border spinner-border-sm text-primary position-absolute end-0 top-50 translate-middle-y me-3" style="display:none; z-index:10;"></div>
                    <div id="cashierDropdownResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index:1000; display:none; max-height:220px; overflow-y:auto; border-radius: 8px;"></div>
                </div>
            </div>
        </div>

        <!-- Student Profile Information Card -->
        <div class="card border-0 shadow-sm d-none" id="studentProfileInfoCard" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <h6 class="fw-bold text-secondary mb-0">Student Profile Information</h6>
            </div>
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <div class="profile-avatar mx-auto mb-3" style="width:100px; height:100px; border-radius: 12px; overflow:hidden;" id="studentPhotoViewer">
                        <!-- Initial placeholder or student photo -->
                    </div>
                    <h5 class="fw-bold mb-1" id="infoStudentName">—</h5>
                    <div class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill mb-2" id="infoAcademicType">—</div>
                </div>
                
                <ul class="list-group list-group-flush mb-0 small">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Admission Number</span>
                        <span class="fw-bold text-dark" id="infoAdmissionNo">—</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Roll Number</span>
                        <span class="fw-semibold text-dark" id="infoRollNo">—</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Father Name</span>
                        <span class="fw-semibold text-dark" id="infoFatherName">—</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Class</span>
                        <span class="fw-semibold text-dark" id="infoClassName">—</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Section</span>
                        <span class="fw-semibold text-dark" id="infoSectionName">—</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Status</span>
                        <span class="badge bg-success rounded-pill px-2 py-1" id="infoStatus">Active</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="alert alert-info border-0 shadow-sm text-center py-4" style="border-radius:12px;" id="noStudentLoadedAlert">
            <i class="fa-solid fa-circle-info fs-3 text-info mb-2 d-block"></i>
            Search and select a student above to load information.
        </div>
    </div>

    <!-- Right Column: Balance Summary, Dues Table, Form, and History -->
    <div class="col-lg-8">
        <div id="cashierDetailsContainer" class="d-none">
            
            <!-- 2. Outstanding Fee Summary Cards (Redesigned with 9 points) -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-chart-line me-2"></i>Outstanding Fee Summary</h6>
                    <div class="row g-2">
                        <!-- Admission Fee -->
                        <div class="col-6 col-sm-3 col-md-3">
                            <div class="p-2 border rounded text-center bg-light">
                                <div class="text-muted small fw-semibold">Admission Fee</div>
                                <h6 class="fw-bold text-secondary mt-1 mb-0" id="summaryAdmission">Rs. 0.00</h6>
                            </div>
                        </div>
                        <!-- Monthly tuition Fee -->
                        <div class="col-6 col-sm-3 col-md-3">
                            <div class="p-2 border rounded text-center bg-light">
                                <div class="text-muted small fw-semibold">Monthly Fee</div>
                                <h6 class="fw-bold text-secondary mt-1 mb-0" id="summaryMonthlyFee">Rs. 0.00</h6>
                            </div>
                        </div>
                        <!-- Annual charges -->
                        <div class="col-6 col-sm-3 col-md-3">
                            <div class="p-2 border rounded text-center bg-light">
                                <div class="text-muted small fw-semibold">Annual Charges</div>
                                <h6 class="fw-bold text-secondary mt-1 mb-0" id="summaryAnnual">Rs. 0.00</h6>
                            </div>
                        </div>
                        <!-- Prev Balance -->
                        <div class="col-6 col-sm-3 col-md-3">
                            <div class="p-2 border rounded text-center bg-light">
                                <div class="text-muted small fw-semibold">Previous Balance</div>
                                <h6 class="fw-bold text-secondary mt-1 mb-0" id="summaryPrevBalance">Rs. 0.00</h6>
                            </div>
                        </div>
                        
                        <!-- Row 2 -->
                        <!-- Fine -->
                        <div class="col-6 col-sm-3 col-md-3 mt-2">
                            <div class="p-2 border rounded text-center" style="background:#fffbeb;">
                                <div class="text-muted small fw-semibold">Fine</div>
                                <h6 class="fw-bold text-warning mt-1 mb-0" id="summaryFine">Rs. 0.00</h6>
                            </div>
                        </div>
                        <!-- Discount -->
                        <div class="col-6 col-sm-3 col-md-3 mt-2">
                            <div class="p-2 border rounded text-center" style="background:#ecfdf5;">
                                <div class="text-muted small fw-semibold">Discount</div>
                                <h6 class="fw-bold text-success mt-1 mb-0" id="summaryDiscount">Rs. 0.00</h6>
                            </div>
                        </div>
                        <!-- Total Payable -->
                        <div class="col-6 col-sm-3 col-md-3 mt-2">
                            <div class="p-2 border rounded text-center" style="background:#f8fafc;">
                                <div class="text-muted small fw-semibold">Total Payable</div>
                                <h6 class="fw-bold text-dark mt-1 mb-0" id="summaryTotalPayable">Rs. 0.00</h6>
                            </div>
                        </div>
                        <!-- Paid Amount -->
                        <div class="col-6 col-sm-3 col-md-3 mt-2">
                            <div class="p-2 border rounded text-center" style="background:#f0fdf4;">
                                <div class="text-success small fw-semibold">Paid Amount</div>
                                <h6 class="fw-bold text-success mt-1 mb-0" id="summaryPaidAmount">Rs. 0.00</h6>
                            </div>
                        </div>
                        
                        <!-- Remaining Balance -->
                        <div class="col-12 mt-2">
                            <div class="p-3 border rounded text-center" style="background:#fef2f2;">
                                <div class="text-danger fw-bold">Remaining Balance</div>
                                <h4 class="fw-bold text-danger mt-1 mb-0" id="summaryRemainingBalance">Rs. 0.00</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Pending Fee Ledger Table -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h6 class="fw-bold text-secondary mb-0">Monthly Fee Ledger</h6>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle small mb-0" id="pendingDuesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Month</th>
                                    <th>Monthly Fee</th>
                                    <th>Fine</th>
                                    <th>Discount</th>
                                    <th>Paid Amount</th>
                                    <th>Remaining Balance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Loaded dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 4. Fee Collection Form -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h6 class="fw-bold text-secondary mb-0">Fee Collection Form</h6>
                </div>
                <div class="card-body p-4">
                    <form id="cashierCollectionForm">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="action" value="collect_payment">
                        <input type="hidden" name="student_id" id="cashierStudentId">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Fee Month *</label>
                                <select class="form-select border-primary text-primary fw-bold" name="challan_id" id="cashierChallanSelect" required>
                                    <!-- Options populated dynamically -->
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Amount to Collect (Rs.) *</label>
                                <input type="number" class="form-control text-success fw-bold fs-5" name="amount_paid" id="cashierAmountPaid" min="1" step="0.01" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Discount Reference (Read-Only)</label>
                                <input type="text" class="form-control bg-light" id="cashierDiscount" value="0.00" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Fine Billed (Read-Only)</label>
                                <input type="text" class="form-control bg-light" id="cashierFine" value="0.00" readonly>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Payment Method *</label>
                                <select class="form-select" name="payment_method" id="cashierMethod" required>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank">Bank</option>
                                    <option value="Online">Online</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Reference Number</label>
                                <input type="text" class="form-control" name="reference_number" placeholder="Cheque, Slip, or Transaction ID">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Remarks</label>
                                <input type="text" class="form-control" name="remarks" placeholder="Add custom ledger notes...">
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-outline-secondary px-4" id="btnCancelCashier">Cancel</button>
                            <button type="submit" class="btn btn-success px-5 fw-bold" id="btnSubmitCashier">
                                <span class="spinner-border spinner-border-sm me-2" style="display:none;" id="formSpinner"></span>
                                Collect Fee
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 5. Payment History Table -->
            <div class="card border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h6 class="fw-bold text-secondary mb-0">Payment History Logs</h6>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table custom-table table-hover align-middle small mb-0" id="paymentHistoryTable">
                            <thead>
                                <tr>
                                    <th>Receipt Number</th>
                                    <th>Date</th>
                                    <th>Month</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dynamic entries -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Collection Success Receipt Print Modal -->
<div class="modal fade" id="receiptTriggerModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm" style="border-radius:12px;">
            <div class="modal-body text-center p-5">
                <div class="d-inline-block bg-success-soft p-4 rounded-circle mb-3">
                    <i class="fa-solid fa-circle-check fs-1 text-success"></i>
                </div>
                <h4 class="fw-bold mb-2">Payment Collected Successfully!</h4>
                <p class="text-muted">Receipt <strong class="text-primary" id="successReceiptNo"></strong> generated successfully.</p>
                <div class="d-flex gap-2 justify-content-center mt-4">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="successPrintLink" target="_blank" class="btn btn-primary px-4"><i class="fa-solid fa-print me-2"></i>Print Receipt</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Box Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="cashierToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="cashierToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showCashierToast(msg, ok) {
    const t = document.getElementById("cashierToast");
    const m = document.getElementById("cashierToastMsg");
    t.classList.remove("bg-success", "bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("cashierStudentSearch");
    const searchSpinner = document.getElementById("searchSpinner");
    const dropdownResults = document.getElementById("cashierDropdownResults");
    
    const detailsContainer = document.getElementById("cashierDetailsContainer");
    const placeholderAlert = document.getElementById("noStudentLoadedAlert");
    const profileCard = document.getElementById("studentProfileInfoCard");

    const cashierStudentId = document.getElementById("cashierStudentId");
    const challanSelect = document.getElementById("cashierChallanSelect");
    const amountPaidInput = document.getElementById("cashierAmountPaid");
    const refDiscInput = document.getElementById("cashierDiscount");
    const refFineInput = document.getElementById("cashierFine");

    let currentStudentId = null;
    let studentChallansList = [];
    let searchTimer = null;

    // Preload Student
    const plId = ' . $preloadStudentId . ';
    if (plId > 0) {
        selectStudent(plId);
    }

    // Dynamic search events
    searchInput?.addEventListener("input", function() {
        clearTimeout(searchTimer);
        const q = this.value.trim();
        if (q.length < 2) {
            dropdownResults.style.display = "none";
            return;
        }

        searchSpinner.style.display = "block";
        searchTimer = setTimeout(() => {
            const fd = new FormData();
            fd.append("action", "search_student");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("q", q);

            fetch("../../ajax/fees.php", { method: "POST", body: fd })
            .then(r => r.json())
            .then(data => {
                searchSpinner.style.display = "none";
                dropdownResults.innerHTML = "";
                if (data.success && data.students.length > 0) {
                    data.students.forEach(st => {
                        const a = document.createElement("a");
                        a.href = "#";
                        a.className = "list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2";
                        a.innerHTML = `
                            <div>
                                <div class="fw-bold text-dark small">${st.first_name} ${st.last_name}</div>
                                <div style="font-size:0.75rem;" class="text-muted">
                                    Admn: <code>${st.admission_no}</code> &middot; Roll: ${st.roll_no || "N/A"} &middot; ${st.class_name} ${st.section}
                                </div>
                            </div>
                            <span class="badge rounded-pill bg-danger-soft text-danger">Rs. ${parseFloat(st.dues).toLocaleString()}</span>
                        `;
                        a.addEventListener("click", function(e) {
                            e.preventDefault();
                            selectStudent(st.id);
                            dropdownResults.style.display = "none";
                            searchInput.value = "";
                        });
                        dropdownResults.appendChild(a);
                    });
                    dropdownResults.style.display = "block";
                } else {
                    dropdownResults.innerHTML = \'<div class="list-group-item text-muted text-center py-2 small">No matches found.</div>\';
                    dropdownResults.style.display = "block";
                }
            })
            .catch(() => {
                searchSpinner.style.display = "none";
            });
        }, 300);
    });

    // Close search dropdown
    document.addEventListener("click", function(e) {
        if (e.target !== searchInput) {
            dropdownResults.style.display = "none";
        }
    });

    // Select and load student profile, calculations, and tables
    function selectStudent(studentId) {
        currentStudentId = studentId;
        
        const fd = new FormData();
        fd.append("action", "load_student_details");
        fd.append("csrf_token", "' . csrfToken() . '");
        fd.append("student_id", studentId);

        fetch("../../ajax/fees.php", { method: "POST", body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showCashierToast(data.message, false);
                return;
            }

            // Bind profile inputs
            cashierStudentId.value = data.student.id;
            document.getElementById("infoStudentName").textContent = `${data.student.first_name} ${data.student.last_name}`;
            document.getElementById("infoAdmissionNo").textContent = data.student.admission_no;
            document.getElementById("infoRollNo").textContent = data.student.roll_no || "—";
            document.getElementById("infoFatherName").textContent = data.student.father_name || "—";
            document.getElementById("infoClassName").textContent = data.student.class_name || "—";
            document.getElementById("infoSectionName").textContent = data.student.section || "—";
            document.getElementById("infoAcademicType").textContent = data.student.academic_type;
            document.getElementById("infoStatus").textContent = data.student.status;

            // Bind photo
            const viewer = document.getElementById("studentPhotoViewer");
            viewer.innerHTML = "";
            if (data.student.doc_student_photo) {
                const img = document.createElement("img");
                img.src = "' . APP_URL . '/" + data.student.doc_student_photo;
                img.style.width = "100%";
                img.style.height = "100%";
                img.style.objectFit = "cover";
                viewer.appendChild(img);
            } else {
                const initials = document.createElement("div");
                initials.className = "bg-primary text-white h-100 w-100 d-flex align-items-center justify-content-center fw-bold fs-4";
                initials.textContent = (data.student.first_name[0] + data.student.last_name[0]).toUpperCase();
                viewer.appendChild(initials);
            }

            // Bind Summaries
            document.getElementById("summaryAdmission").textContent = "Rs. " + data.summary.admission_fee.toLocaleString(undefined, {minimumFractionDigits:2});
            document.getElementById("summaryMonthlyFee").textContent = "Rs. " + data.summary.tuition_fee.toLocaleString(undefined, {minimumFractionDigits:2});
            document.getElementById("summaryAnnual").textContent = "Rs. " + data.summary.annual_charges.toLocaleString(undefined, {minimumFractionDigits:2});
            document.getElementById("summaryPrevBalance").textContent = "Rs. " + data.summary.previous_balance.toLocaleString(undefined, {minimumFractionDigits:2});
            document.getElementById("summaryFine").textContent = "Rs. " + data.summary.fine.toLocaleString(undefined, {minimumFractionDigits:2});
            document.getElementById("summaryDiscount").textContent = "Rs. " + data.summary.discount.toLocaleString(undefined, {minimumFractionDigits:2});
            document.getElementById("summaryTotalPayable").textContent = "Rs. " + data.summary.total_payable.toLocaleString(undefined, {minimumFractionDigits:2});
            document.getElementById("summaryPaidAmount").textContent = "Rs. " + data.summary.paid_amount.toLocaleString(undefined, {minimumFractionDigits:2});
            document.getElementById("summaryRemainingBalance").textContent = "Rs. " + data.summary.remaining_balance.toLocaleString(undefined, {minimumFractionDigits:2});

            // Bind Pending Table
            const tbody = document.querySelector("#pendingDuesTable tbody");
            tbody.innerHTML = "";
            challanSelect.innerHTML = \'<option value="">— Select Fee Month —</option>\';
            studentChallansList = data.pending_fees;

            if (data.pending_fees.length === 0) {
                tbody.innerHTML = \'<tr><td colspan="7" class="text-center py-3 text-success fw-bold">All dues cleared! No pending records.</td></tr>\';
            } else {
                data.pending_fees.forEach(ch => {
                    // Populate Table Row
                    let badgeClass = "bg-danger";
                    if (ch.status === "Partial") badgeClass = "bg-warning text-dark";
                    else if (ch.status === "Paid") badgeClass = "bg-success";
                    
                    const tr = document.createElement("tr");
                    tr.innerHTML = `
                        <td class="fw-bold">${ch.month}</td>
                        <td>Rs. ${ch.monthly_fee.toFixed(2)}</td>
                        <td class="text-danger">+ Rs. ${ch.fine.toFixed(2)}</td>
                        <td class="text-success">- Rs. ${ch.discount.toFixed(2)}</td>
                        <td>Rs. ${ch.paid_amount.toFixed(2)}</td>
                        <td class="fw-bold text-dark">Rs. ${ch.remaining_balance.toFixed(2)}</td>
                        <td><span class="badge ${badgeClass} small rounded-pill">${ch.status}</span></td>
                    `;
                    tbody.appendChild(tr);

                    // Populate Select Options
                    const opt = document.createElement("option");
                    opt.value = ch.id;
                    opt.textContent = `${ch.month} (Payable: Rs. ${ch.remaining_balance.toFixed(2)})`;
                    challanSelect.appendChild(opt);
                });
            }

            // Bind Payment History
            const histBody = document.querySelector("#paymentHistoryTable tbody");
            histBody.innerHTML = "";
            if (data.payment_history.length === 0) {
                histBody.innerHTML = \'<tr><td colspan="7" class="text-center py-3 text-muted">No transactions logged yet.</td></tr>\';
            } else {
                data.payment_history.forEach(log => {
                    const tr = document.createElement("tr");
                    tr.innerHTML = `
                        <td class="fw-semibold">${log.receipt_no}</td>
                        <td>${new Date(log.payment_date).toLocaleDateString("en-GB")}</td>
                        <td>${log.month || "—"}</td>
                        <td class="fw-bold text-success">Rs. ${parseFloat(log.amount_paid).toFixed(2)}</td>
                        <td>${log.payment_method}</td>
                        <td><span class="badge bg-success small rounded-pill">Completed</span></td>
                        <td class="text-end">
                            <a href="../../templates/receipt.php?receipt_no=${encodeURIComponent(log.receipt_no)}" target="_blank" class="btn btn-sm btn-outline-primary py-0" title="Print Receipt">
                                <i class="fa-solid fa-print"></i>
                            </a>
                        </td>
                    `;
                    histBody.appendChild(tr);
                });
            }

            // Open Containers
            placeholderAlert.classList.add("d-none");
            profileCard.classList.remove("d-none");
            detailsContainer.classList.remove("d-none");

            // Reset forms variables
            amountPaidInput.value = "";
            refDiscInput.value = "0.00";
            refFineInput.value = "0.00";
        });
    }

    // Dropdown Selection Month Change
    challanSelect?.addEventListener("change", function() {
        const val = parseInt(this.value);
        if (!val) {
            amountPaidInput.value = "";
            refDiscInput.value = "0.00";
            refFineInput.value = "0.00";
            return;
        }

        const activeCh = studentChallansList.find(c => c.id === val);
        if (activeCh) {
            amountPaidInput.value = activeCh.remaining_balance.toFixed(2);
            refDiscInput.value = activeCh.discount.toFixed(2);
            refFineInput.value = activeCh.fine.toFixed(2);
        }
    });

    // Refresh Dashboard Cards stats
    function refreshDashboard() {
        const fd = new FormData();
        fd.append("action", "get_cashier_dashboard");
        fd.append("csrf_token", "' . csrfToken() . '");

        fetch("../../ajax/fees.php", { method: "POST", body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById("dashTodayColl").textContent = "Rs. " + data.dashboard.today_collection.toLocaleString(undefined, {maximumFractionDigits:0});
                document.getElementById("dashTodayPending").textContent = "Rs. " + data.dashboard.today_pending.toLocaleString(undefined, {maximumFractionDigits:0});
                document.getElementById("dashMonthColl").textContent = "Rs. " + data.dashboard.monthly_collection.toLocaleString(undefined, {maximumFractionDigits:0});
                document.getElementById("dashOutstanding").textContent = "Rs. " + data.dashboard.outstanding_dues.toLocaleString(undefined, {maximumFractionDigits:0});
                document.getElementById("dashPaidStudents").textContent = data.dashboard.paid_students;
                document.getElementById("dashPendingStudents").textContent = data.dashboard.pending_students;
            }
        });
    }

    // Submit Payment Form
    const collectionForm = document.getElementById("cashierCollectionForm");
    collectionForm?.addEventListener("submit", function(e) {
        e.preventDefault();
        
        const challanId = challanSelect.value;
        const enteredAmt = parseFloat(amountPaidInput.value) || 0;
        if (!challanId) {
            showCashierToast("Please choose a fee month.", false);
            return;
        }
        if (enteredAmt <= 0) {
            showCashierToast("Please input a valid positive amount.", false);
            return;
        }

        if (!confirm("Are you sure you want to record this fee payment of Rs. " + enteredAmt.toLocaleString() + "?")) {
            return;
        }

        const btn = document.getElementById("btnSubmitCashier");
        const formSpin = document.getElementById("formSpinner");
        
        btn.disabled = true;
        formSpin.style.display = "inline-block";

        fetch("../../ajax/fees.php", {
            method: "POST",
            body: new FormData(collectionForm)
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            formSpin.style.display = "none";
            
            if (data.success) {
                showCashierToast("Payment processed successfully!", true);
                
                // Show trigger receipt modal
                document.getElementById("successReceiptNo").textContent = data.receipt_no;
                document.getElementById("successPrintLink").href = "../../templates/receipt.php?receipt_no=" + encodeURIComponent(data.receipt_no);
                new bootstrap.Modal(document.getElementById("receiptTriggerModal")).show();
                
                // Redraw tables/ledgers
                selectStudent(currentStudentId);
                // Refresh dashboard cards
                refreshDashboard();
            } else {
                showCashierToast(data.message, false);
            }
        })
        .catch(() => {
            btn.disabled = false;
            formSpin.style.display = "none";
            showCashierToast("An transmission error occurred. Please try again.", false);
        });
    });

    // Cancel triggers
    document.getElementById("btnCancelCashier")?.addEventListener("click", () => {
        location.reload();
    });
});
</script>';

include_once __DIR__ . '/../../includes/footer.php';
?>
