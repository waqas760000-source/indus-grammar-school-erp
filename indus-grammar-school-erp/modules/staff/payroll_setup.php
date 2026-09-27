<?php
/**
 * Indus Grammar School ERP - Salary Setup & Compensation Configurations
 * Version 4.0.0 (Premium Redesign)
 */

$pageTitle = 'Salary Setup';
$breadcrumbActive = 'Payroll';
include_once __DIR__ . '/../../includes/header.php';

// Access Control
AuthMiddleware::requireLogin();
$userRole = $_SESSION['role_code'] ?? '';
if (!in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_SCHOOL_ADMIN, 'accountant'])) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to access the payroll setup module.';
    redirect(APP_URL . '/dashboard.php');
}

$db = Database::getConnection();

// Search and filter parameters
$search = sanitize($_GET['search'] ?? '');
$dept   = sanitize($_GET['department'] ?? '');
$methodFilter = sanitize($_GET['method'] ?? '');

$where = " WHERE s.status = 'Active'";
$params = [];
if (!empty($search)) {
    $where .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.employee_no LIKE :search OR s.designation LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if (!empty($dept)) {
    $where .= " AND s.department = :dept";
    $params['dept'] = $dept;
}
if (!empty($methodFilter)) {
    $where .= " AND ss.payment_method = :method";
    $params['method'] = $methodFilter;
}

// Load staff list with setup configurations
$staff = $db->prepare("
    SELECT s.id, s.employee_no, s.first_name, s.last_name, s.designation, s.department, s.phone, s.salary as base_emp_salary,
           ss.basic_salary, ss.hra, ss.medical_allowance, ss.transport_allowance, ss.other_allowances,
           ss.provident_fund, ss.tax_deduction, ss.eobi, ss.other_deductions,
           ss.payment_method, ss.bank_name, ss.account_number, ss.status as setup_status
    FROM staff s
    LEFT JOIN salary_setup ss ON s.id = ss.staff_id
    $where
    ORDER BY s.employee_no ASC
");
$staff->execute($params);
$staffList = $staff->fetchAll(PDO::FETCH_ASSOC);

// Calculate Executive Setup Metrics
$totalStaff = count($staffList);
$configuredCount = 0;
$sumBasic = 0.0;
$sumAllowances = 0.0;
$sumDeductions = 0.0;

foreach ($staffList as $st) {
    if (!empty($st['basic_salary'])) $configuredCount++;
    $b = (float)($st['basic_salary'] ?? $st['base_emp_salary'] ?? 0);
    $a = (float)($st['hra'] ?? 0) + (float)($st['medical_allowance'] ?? 0) + (float)($st['transport_allowance'] ?? 0) + (float)($st['other_allowances'] ?? 0);
    $d = (float)($st['provident_fund'] ?? 0) + (float)($st['tax_deduction'] ?? 0) + (float)($st['eobi'] ?? 0) + (float)($st['other_deductions'] ?? 0);
    
    $sumBasic += $b;
    $sumAllowances += $a;
    $sumDeductions += $d;
}
$netCommitment = ($sumBasic + $sumAllowances) - $sumDeductions;

// Fetch departments for filter
$departments = $db->query("SELECT DISTINCT department FROM staff WHERE status = 'Active' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);

// Color generator helper for avatar badges
if (!function_exists('getAvatarColor')) {
    function getAvatarColor($name) {
        $colors = ['#1d4ed8', '#0d9488', '#b91c1c', '#c2410c', '#6d28d9', '#0369a1', '#be185d', '#4d7c0f'];
        $hash = crc32($name);
        return $colors[abs($hash) % count($colors)];
    }
}
?>

<style>
.setup-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px;
    color: #ffffff;
    padding: 1.5rem 1.75rem;
    position: relative;
    overflow: hidden;
}
.setup-hero-card::after {
    content: "";
    position: absolute;
    top: -50%;
    right: -10%;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.kpi-setup-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.15rem;
    transition: all 0.25s ease;
}
.kpi-setup-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

.staff-avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.95rem;
    color: #ffffff;
}

.net-preview-card {
    background: linear-gradient(135deg, #065f46 0%, #047857 100%);
    color: #ffffff;
    border-radius: 12px;
    padding: 1.15rem;
}
</style>

<!-- Hero Title Header -->
<div class="setup-hero-card shadow-sm mb-4">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-primary bg-opacity-20 rounded-3 text-warning">
                    <i class="fa-solid fa-user-gear fs-2"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-1">Salary Setup & Compensation Profiles</h3>
                    <p class="text-white-50 mb-0 small">
                        Assign structural base salaries, benefits allowances (HRA/Medical), tax withholdings (EOBI/PF), and disbursement channels to staff members.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
            <a href="payroll.php" class="btn btn-outline-light btn-sm px-3 rounded-pill me-1">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
            </a>
            <button class="btn btn-warning btn-sm px-3 rounded-pill text-dark fw-semibold" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Print Roster
            </button>
        </div>
    </div>
</div>

<!-- 5 KPI Executive Micro-Cards -->
<div class="row g-3 mb-4">
    <!-- Configured Ratio -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-setup-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">CONFIGURED STAFF</span>
                <span class="badge bg-primary-soft text-primary p-2 rounded-circle"><i class="fa-solid fa-sliders"></i></span>
            </div>
            <h4 class="fw-bold text-dark mb-0"><?php echo $configuredCount; ?> / <?php echo $totalStaff; ?></h4>
            <small class="text-muted"><?php echo $totalStaff > 0 ? round(($configuredCount/$totalStaff)*100) : 0; ?>% setup complete</small>
        </div>
    </div>

    <!-- Total Basic Base -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-setup-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">TOTAL BASIC BASE</span>
                <span class="badge bg-info-soft text-info p-2 rounded-circle"><i class="fa-solid fa-wallet"></i></span>
            </div>
            <h4 class="fw-bold text-info mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumBasic, 0); ?></h4>
            <small class="text-muted">Structural basic salaries</small>
        </div>
    </div>

    <!-- Total Allowances -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-setup-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">ALLOWANCES TOTAL</span>
                <span class="badge bg-success-soft text-success p-2 rounded-circle"><i class="fa-solid fa-plus-circle"></i></span>
            </div>
            <h4 class="fw-bold text-success mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumAllowances, 0); ?></h4>
            <small class="text-muted">HRA, Medical & Conveyance</small>
        </div>
    </div>

    <!-- Total Withholdings -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-setup-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">WITHHOLDINGS TOTAL</span>
                <span class="badge bg-danger-soft text-danger p-2 rounded-circle"><i class="fa-solid fa-minus-circle"></i></span>
            </div>
            <h4 class="fw-bold text-danger mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($sumDeductions, 0); ?></h4>
            <small class="text-muted">Tax, EOBI & Provident Fund</small>
        </div>
    </div>

    <!-- Net Structural Commitment -->
    <div class="col-6 col-md-2-4" style="flex: 0 0 20%; max-width: 20%;">
        <div class="kpi-setup-card shadow-sm h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold">NET COMMITMENT</span>
                <span class="badge bg-purple-soft text-purple p-2 rounded-circle"><i class="fa-solid fa-file-invoice-dollar"></i></span>
            </div>
            <h4 class="fw-bold text-purple mb-0" style="font-size: 1.15rem;">Rs. <?php echo number_format($netCommitment, 0); ?></h4>
            <small class="text-muted">Monthly net payable</small>
        </div>
    </div>
</div>

<!-- Search & Filtering Toolbar -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center" id="filterForm">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control bg-light border-start-0" name="search" placeholder="Search by name, EMP ID, designation..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select bg-light" name="department" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo $dept === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select class="form-select bg-light" name="method" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Methods</option>
                    <option value="Bank Transfer" <?php echo $methodFilter === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                    <option value="Cash" <?php echo $methodFilter === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                    <option value="Cheque" <?php echo $methodFilter === 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                </select>
            </div>
            <div class="col-md-2 text-end">
                <a href="payroll_setup.php" class="btn btn-outline-secondary w-100 rounded-pill"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Responsive Setup Table Roster -->
<div class="card border-0 shadow-sm mb-5" style="border-radius:14px;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Staff Member</th>
                        <th>EMP ID</th>
                        <th>Department</th>
                        <th class="text-end">Basic Salary</th>
                        <th class="text-end text-success">Allowances</th>
                        <th class="text-end text-danger">Withholdings</th>
                        <th class="text-end text-dark">Net Payable</th>
                        <th class="text-center">Disbursal Method</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffList)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted">No employees matching search criteria.</td></tr>
                    <?php else: foreach ($staffList as $st): 
                        $fullName = trim($st['first_name'] . ' ' . $st['last_name']);
                        $initials = strtoupper(substr($st['first_name'], 0, 1) . substr($st['last_name'], 0, 1));
                        $bgColor  = getAvatarColor($fullName);
                        
                        $basic = (float)($st['basic_salary'] ?? $st['base_emp_salary'] ?? 0);
                        $allow = (float)($st['hra'] ?? 0) + (float)($st['medical_allowance'] ?? 0) + (float)($st['transport_allowance'] ?? 0) + (float)($st['other_allowances'] ?? 0);
                        $deduct = (float)($st['provident_fund'] ?? 0) + (float)($st['tax_deduction'] ?? 0) + (float)($st['eobi'] ?? 0) + (float)($st['other_deductions'] ?? 0);
                        $netVal = ($basic + $allow) - $deduct;

                        $method = $st['payment_method'] ?? 'Bank Transfer';
                        $methodIcon = $method === 'Cash' ? 'fa-cash-register text-success' : ($method === 'Cheque' ? 'fa-money-check text-warning' : 'fa-building-columns text-primary');
                        $isSetup = !empty($st['basic_salary']);
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="staff-avatar-sm" style="background-color: <?php echo $bgColor; ?>;">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark mb-0"><?php echo sanitize($fullName); ?></div>
                                        <small class="text-muted"><?php echo sanitize($st['designation']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><code><?php echo sanitize($st['employee_no']); ?></code></td>
                            <td><span class="badge bg-light text-dark border"><?php echo sanitize($st['department']); ?></span></td>
                            <td class="text-end fw-bold text-dark">Rs. <?php echo number_format($basic, 2); ?></td>
                            <td class="text-end text-success fw-semibold">
                                +Rs. <?php echo number_format($allow, 2); ?>
                            </td>
                            <td class="text-end text-danger fw-semibold">
                                -Rs. <?php echo number_format($deduct, 2); ?>
                            </td>
                            <td class="text-end fw-bold text-dark">
                                Rs. <?php echo number_format($netVal, 2); ?>
                            </td>
                            <td class="text-center small">
                                <span class="badge bg-light text-secondary border">
                                    <i class="fa-solid <?php echo $methodIcon; ?> me-1"></i><?php echo sanitize($method); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($isSetup): ?>
                                    <span class="badge bg-success-soft text-success px-3 py-1 rounded-pill"><i class="fa-solid fa-circle-check me-1"></i>Configured</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-soft text-secondary px-3 py-1 rounded-pill"><i class="fa-solid fa-clock me-1"></i>Default</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick='editSetup(<?php echo json_encode($st); ?>)'>
                                    <i class="fa-solid fa-sliders me-1"></i>Configure
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form Setup with Live Net Calculation Preview -->
<div class="modal fade" id="setupModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;">
            <div class="modal-header border-0 bg-dark text-white pt-4 px-4" style="border-top-left-radius:18px; border-top-right-radius:18px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 bg-primary bg-opacity-20 rounded text-warning fs-3">
                        <i class="fa-solid fa-sack-dollar"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0">Configure Staff Salary Structure</h5>
                        <div class="small text-white-50" id="setupStaffSub">Employee Profile Configuration</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="setupForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_salary_setup">
                    <input type="hidden" name="staff_id" id="setupStaffId">

                    <!-- Live Salary Calculator Preview Card -->
                    <div class="net-preview-card mb-4 shadow-sm">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <small class="text-uppercase tracking-wider fw-bold text-white-50 d-block mb-1">Calculated Monthly Net Commitment</small>
                                <h2 class="fw-bold text-white mb-0" id="previewNetVal">Rs. 0.00</h2>
                            </div>
                            <div class="col-md-5 text-md-end mt-2 mt-md-0 border-start border-white border-opacity-25 ps-md-3">
                                <div class="small text-white-50">Basic: <span class="fw-bold text-white" id="previewBasic">Rs. 0</span></div>
                                <div class="small text-white-50">Allowances: <span class="fw-bold text-warning" id="previewAllow">+Rs. 0</span></div>
                                <div class="small text-white-50">Withholdings: <span class="fw-bold text-danger-light text-white" id="previewDeduct">-Rs. 0</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- 1. Monthly Basic & Allowances -->
                    <h6 class="fw-bold text-primary mb-3 border-bottom pb-2"><i class="fa-solid fa-circle-plus me-2 text-success"></i>Monthly Basic Salary & Structural Allowances</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Basic Monthly Salary <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light small">Rs.</span>
                                <input type="number" step="0.01" class="form-control fw-bold text-success" name="basic_salary" id="basic_salary" required placeholder="25000.00" oninput="recalcSalaryPreview()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">House Rent Allowance (HRA)</label>
                            <input type="number" step="0.01" class="form-control" name="hra" id="hra" value="0.00" oninput="recalcSalaryPreview()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Medical Allowance</label>
                            <input type="number" step="0.01" class="form-control" name="medical_allowance" id="medical_allowance" value="0.00" oninput="recalcSalaryPreview()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Transport / Conveyance</label>
                            <input type="number" step="0.01" class="form-control" name="transport_allowance" id="transport_allowance" value="0.00" oninput="recalcSalaryPreview()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Other Allowances</label>
                            <input type="number" step="0.01" class="form-control" name="other_allowances" id="other_allowances" value="0.00" oninput="recalcSalaryPreview()">
                        </div>
                    </div>

                    <!-- 2. Deductions & Withholdings -->
                    <h6 class="fw-bold text-danger mb-3 border-bottom pb-2"><i class="fa-solid fa-circle-minus me-2 text-danger"></i>Structural Withholdings & Deductions</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Provident Fund (PF)</label>
                            <input type="number" step="0.01" class="form-control" name="provident_fund" id="provident_fund" value="0.00" oninput="recalcSalaryPreview()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Income Tax Withholding</label>
                            <input type="number" step="0.01" class="form-control" name="tax_deduction" id="tax_deduction" value="0.00" oninput="recalcSalaryPreview()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">EOBI Contribution</label>
                            <input type="number" step="0.01" class="form-control" name="eobi" id="eobi" value="0.00" oninput="recalcSalaryPreview()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Other Deductions</label>
                            <input type="number" step="0.01" class="form-control" name="other_deductions" id="other_deductions" value="0.00" oninput="recalcSalaryPreview()">
                        </div>
                    </div>

                    <!-- 3. Disbursement Settings -->
                    <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2"><i class="fa-solid fa-credit-card me-2 text-primary"></i>Disbursement Settings & Bank Information</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Payment Method</label>
                            <select class="form-select" name="payment_method" id="payment_method">
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cash">Cash</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Bank Name / Title</label>
                            <input type="text" class="form-control" name="bank_name" id="bank_name" placeholder="e.g. Habib Bank Limited">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Account Number / IBAN</label>
                            <input type="text" class="form-control" name="account_number" id="account_number" placeholder="PK00 HABB 0000 0000 0000">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Setup Registry Status</label>
                            <select class="form-select" name="status" id="setup_status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 bg-light" style="border-bottom-left-radius:18px; border-bottom-right-radius:18px;">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="setupForm" class="btn btn-primary rounded-pill px-4" id="btnSave">
                    <i class="fa-solid fa-check me-1"></i> Save Salary Structure
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="setupToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="setupToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
const modalObj = new bootstrap.Modal(document.getElementById("setupModal"));

function showToast(msg, ok) {
    const t = document.getElementById("setupToast");
    const m = document.getElementById("setupToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

function recalcSalaryPreview() {
    const basic = parseFloat(document.getElementById("basic_salary").value) || 0;
    const hra   = parseFloat(document.getElementById("hra").value) || 0;
    const med   = parseFloat(document.getElementById("medical_allowance").value) || 0;
    const trans = parseFloat(document.getElementById("transport_allowance").value) || 0;
    const othA  = parseFloat(document.getElementById("other_allowances").value) || 0;
    
    const pf    = parseFloat(document.getElementById("provident_fund").value) || 0;
    const tax   = parseFloat(document.getElementById("tax_deduction").value) || 0;
    const eobi  = parseFloat(document.getElementById("eobi").value) || 0;
    const othD  = parseFloat(document.getElementById("other_deductions").value) || 0;

    const totalAllow = hra + med + trans + othA;
    const totalDeduct = pf + tax + eobi + othD;
    const net = (basic + totalAllow) - totalDeduct;

    document.getElementById("previewBasic").textContent = "Rs. " + basic.toLocaleString("en-US", {minimumFractionDigits:2});
    document.getElementById("previewAllow").textContent = "+Rs. " + totalAllow.toLocaleString("en-US", {minimumFractionDigits:2});
    document.getElementById("previewDeduct").textContent = "-Rs. " + totalDeduct.toLocaleString("en-US", {minimumFractionDigits:2});
    document.getElementById("previewNetVal").textContent = "Rs. " + net.toLocaleString("en-US", {minimumFractionDigits:2});
}

function editSetup(st) {
    document.getElementById("setupStaffId").value = st.id;
    document.getElementById("setupStaffSub").textContent = st.first_name + " " + st.last_name + " \u2022 " + st.employee_no + " (" + st.designation + ")";

    const defaultBase = st.base_emp_salary > 0 ? st.base_emp_salary : 25000.00;
    document.getElementById("basic_salary").value = st.basic_salary !== null ? st.basic_salary : defaultBase;
    document.getElementById("hra").value = st.hra !== null ? st.hra : "0.00";
    document.getElementById("medical_allowance").value = st.medical_allowance !== null ? st.medical_allowance : "0.00";
    document.getElementById("transport_allowance").value = st.transport_allowance !== null ? st.transport_allowance : "0.00";
    document.getElementById("other_allowances").value = st.other_allowances !== null ? st.other_allowances : "0.00";
    
    document.getElementById("provident_fund").value = st.provident_fund !== null ? st.provident_fund : "0.00";
    document.getElementById("tax_deduction").value = st.tax_deduction !== null ? st.tax_deduction : "0.00";
    document.getElementById("eobi").value = st.eobi !== null ? st.eobi : "0.00";
    document.getElementById("other_deductions").value = st.other_deductions !== null ? st.other_deductions : "0.00";
    
    document.getElementById("payment_method").value = st.payment_method !== null ? st.payment_method : "Bank Transfer";
    document.getElementById("bank_name").value = st.bank_name !== null ? st.bank_name : "";
    document.getElementById("account_number").value = st.account_number !== null ? st.account_number : "";
    document.getElementById("setup_status").value = st.setup_status !== null ? st.setup_status : "Active";

    recalcSalaryPreview();
    modalObj.show();
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("setupForm");
    if(form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSave");
            btn.disabled = true; 
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...`;

            fetch("../../ajax/payroll.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) {
                        modalObj.hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        btn.disabled = false; 
                        btn.innerHTML = `<i class="fa-solid fa-check me-1"></i> Save Salary Structure`;
                    }
                })
                .catch(() => {
                    showToast("System connection error.", false);
                    btn.disabled = false; 
                    btn.innerHTML = `<i class="fa-solid fa-check me-1"></i> Save Salary Structure`;
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
