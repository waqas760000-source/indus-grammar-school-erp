<?php
/**
 * Indus Grammar School ERP - Staff Directory
 * Version 4.0.0 (Premium Redesign)
 */

$pageTitle = 'Staff Directory';
$breadcrumbActive = 'HR & Staff';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('hr_view');

// Fetch staff data with linked user accounts
$staffList = Staff::all([], 500, 0);
$empNo = Staff::generateEmployeeNo();

// Calculate Directory Metrics
$totalStaff = count($staffList);
$activeStaff = 0;
$inactiveStaff = 0;
$totalSalary = 0.0;
$linkedUsers = 0;
$deptCounts = [
    'Academic' => 0,
    'Administration' => 0,
    'Accounts' => 0,
    'Support Staff' => 0
];

foreach ($staffList as $s) {
    if (($s['status'] ?? '') === 'Active') $activeStaff++;
    else $inactiveStaff++;
    
    $totalSalary += (float)($s['salary'] ?? 0);
    if (!empty($s['user_id'])) $linkedUsers++;
    
    $dept = $s['department'] ?? 'Academic';
    if (isset($deptCounts[$dept])) $deptCounts[$dept]++;
    else $deptCounts[$dept] = 1;
}

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
.staff-hero-card {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border-radius: 16px;
    color: #fff;
    padding: 1.5rem 1.75rem;
    position: relative;
    overflow: hidden;
}
.staff-hero-card::after {
    content: "";
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.kpi-mini-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.15rem;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.kpi-mini-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08);
}

.staff-grid-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    transition: all 0.25s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
}
.staff-grid-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.09);
    border-color: #cbd5e1;
}

.staff-avatar-lg {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.4rem;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}

.staff-avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.95rem;
    color: #ffffff;
}

.badge-soft-success { background-color: #dcfce7; color: #15803d; }
.badge-soft-warning { background-color: #fef9c3; color: #a16207; }
.badge-soft-danger  { background-color: #fee2e2; color: #b91c1c; }
.badge-soft-info    { background-color: #e0f2fe; color: #0369a1; }
.badge-soft-purple  { background-color: #f3e8ff; color: #6b21a8; }
.badge-soft-secondary { background-color: #f1f5f9; color: #475569; }

.search-toolbar {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem 1.25rem;
}
</style>

<!-- Top Executive Banner & Hero -->
<div class="staff-hero-card shadow-sm mb-4">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-primary bg-opacity-20 rounded-3 text-warning">
                    <i class="fa-solid fa-users-gear fs-2"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-1">Staff Directory</h3>
                    <p class="text-white-50 mb-0 small">
                        Manage academic faculties, administrative officers, accounts, and support personnel in real time.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0 d-flex gap-2 justify-content-lg-end">
            <button class="btn btn-outline-light btn-sm px-3 rounded-pill" onclick="exportStaffCSV()">
                <i class="fa-solid fa-file-csv me-1"></i> Export Roster
            </button>
            <button class="btn btn-outline-light btn-sm px-3 rounded-pill" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Print Roster
            </button>
            <?php if (hasPermission('hr_manage')): ?>
            <button class="btn btn-warning px-4 fw-semibold text-dark rounded-pill" data-bs-toggle="modal" data-bs-target="#staffModal" onclick="openStaffModal()">
                <i class="fa-solid fa-user-plus me-1"></i> Add Staff
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Key Performance Indicators (KPIs) -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-mini-card shadow-sm">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold text-uppercase tracking-wider">Total Staff</span>
                <span class="badge bg-primary-soft text-primary rounded-circle p-2"><i class="fa-solid fa-users fs-6"></i></span>
            </div>
            <h3 class="fw-bold text-dark mb-1"><?php echo $totalStaff; ?></h3>
            <div class="small text-muted">
                <span class="text-success fw-semibold"><i class="fa-solid fa-check me-1"></i><?php echo $activeStaff; ?> Active</span> &bull; 
                <span class="text-warning fw-semibold"><?php echo $inactiveStaff; ?> Off</span>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="kpi-mini-card shadow-sm">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold text-uppercase tracking-wider">Monthly Base Payroll</span>
                <span class="badge bg-success-soft text-success rounded-circle p-2"><i class="fa-solid fa-money-bill-wave fs-6"></i></span>
            </div>
            <h3 class="fw-bold text-success mb-1">Rs. <?php echo number_format($totalSalary, 0); ?></h3>
            <div class="small text-muted">Gross monthly commitments</div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="kpi-mini-card shadow-sm">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold text-uppercase tracking-wider">System Users</span>
                <span class="badge bg-purple-soft text-purple rounded-circle p-2"><i class="fa-solid fa-user-shield fs-6"></i></span>
            </div>
            <h3 class="fw-bold text-purple mb-1"><?php echo $linkedUsers; ?></h3>
            <div class="small text-muted"><?php echo $totalStaff > 0 ? round(($linkedUsers / $totalStaff) * 100) : 0; ?>% with portal accounts</div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="kpi-mini-card shadow-sm">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted fw-semibold text-uppercase tracking-wider">Academic Staff</span>
                <span class="badge bg-info-soft text-info rounded-circle p-2"><i class="fa-solid fa-chalkboard-user fs-6"></i></span>
            </div>
            <h3 class="fw-bold text-info mb-1"><?php echo $deptCounts['Academic'] ?? 0; ?></h3>
            <div class="small text-muted">Teaching & faculty staff</div>
        </div>
    </div>
</div>

<!-- Search & Filtering Toolbar -->
<div class="search-toolbar shadow-sm mb-4">
    <div class="row g-3 align-items-center">
        <!-- Live Search -->
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="staffSearchInput" class="form-control bg-light border-start-0" placeholder="Search by name, EMP ID, designation or phone..." onkeyup="filterStaff()">
            </div>
        </div>

        <!-- Filter Department -->
        <div class="col-6 col-md-3">
            <select id="deptFilter" class="form-select bg-light" onchange="filterStaff()">
                <option value="">All Departments</option>
                <option value="Academic">Academic</option>
                <option value="Administration">Administration</option>
                <option value="Accounts">Accounts</option>
                <option value="Support Staff">Support Staff</option>
            </select>
        </div>

        <!-- Filter Status -->
        <div class="col-6 col-md-3">
            <select id="statusFilter" class="form-select bg-light" onchange="filterStaff()">
                <option value="">All Statuses</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Terminated">Terminated</option>
            </select>
        </div>

        <!-- View Mode Switcher -->
        <div class="col-md-2 text-end">
            <div class="btn-group w-100" role="group">
                <button type="button" class="btn btn-outline-secondary active" id="btnViewGrid" onclick="toggleView('grid')">
                    <i class="fa-solid fa-grip me-1"></i> Cards
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnViewTable" onclick="toggleView('table')">
                    <i class="fa-solid fa-list me-1"></i> Roster
                </button>
            </div>
        </div>
    </div>
</div>

<!-- STAFF CONTAINER: GRID VIEW -->
<div id="staffGridContainer" class="row g-3 mb-5">
    <?php if (empty($staffList)): ?>
        <div class="col-12 text-center py-5">
            <div class="p-5 bg-light rounded-4">
                <i class="fa-solid fa-users-slash fs-1 text-muted mb-3"></i>
                <h5 class="fw-bold text-secondary">No Staff Members Registered</h5>
                <p class="text-muted small">Get started by clicking the "Add Staff" button above.</p>
            </div>
        </div>
    <?php else: foreach ($staffList as $s): 
        $fullName = trim($s['first_name'] . ' ' . $s['last_name']);
        $initials = strtoupper(substr($s['first_name'], 0, 1) . substr($s['last_name'], 0, 1));
        $bgColor = getAvatarColor($fullName);
        $statusClass = [
            'Active' => 'badge-soft-success',
            'Inactive' => 'badge-soft-warning',
            'Terminated' => 'badge-soft-danger'
        ][$s['status']] ?? 'badge-soft-secondary';
        
        // Calculate years of service
        $joiningDate = !empty($s['date_of_joining']) ? new DateTime($s['date_of_joining']) : new DateTime();
        $now = new DateTime();
        $interval = $joiningDate->diff($now);
        $tenureText = $interval->y > 0 ? $interval->y . ' yrs ' . $interval->m . ' mos' : $interval->m . ' months';
    ?>
        <div class="col-md-6 col-lg-4 staff-card-item" 
             data-name="<?php echo strtolower(htmlspecialchars($fullName)); ?>"
             data-emp="<?php echo strtolower(htmlspecialchars($s['employee_no'])); ?>"
             data-dept="<?php echo htmlspecialchars($s['department']); ?>"
             data-status="<?php echo htmlspecialchars($s['status']); ?>"
             data-phone="<?php echo htmlspecialchars($s['phone']); ?>"
             data-desig="<?php echo strtolower(htmlspecialchars($s['designation'])); ?>">
            
            <div class="staff-grid-card shadow-sm p-4">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="staff-avatar-lg" style="background-color: <?php echo $bgColor; ?>;">
                        <?php echo $initials; ?>
                    </div>
                    <div class="text-end">
                        <span class="badge <?php echo $statusClass; ?> px-3 py-2 rounded-pill fw-semibold mb-1 d-block">
                            <?php echo sanitize($s['status']); ?>
                        </span>
                        <code class="small text-muted fw-bold d-block"><?php echo sanitize($s['employee_no']); ?></code>
                    </div>
                </div>

                <h5 class="fw-bold text-dark mb-1"><?php echo sanitize($fullName); ?></h5>
                <p class="text-primary small fw-semibold mb-2">
                    <i class="fa-solid fa-briefcase me-1"></i><?php echo sanitize($s['designation']); ?>
                </p>

                <div class="d-flex gap-2 flex-wrap mb-3">
                    <span class="badge bg-light text-secondary border">
                        <i class="fa-solid fa-building me-1"></i><?php echo sanitize($s['department']); ?>
                    </span>
                    <?php if ($s['user_id']): ?>
                        <span class="badge badge-soft-purple">
                            <i class="fa-solid fa-user-shield me-1"></i>Portal User #<?php echo $s['user_id']; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <hr class="my-3 text-muted opacity-25">

                <div class="row g-2 small text-muted mb-3">
                    <div class="col-6">
                        <i class="fa-solid fa-phone me-1 text-secondary"></i>
                        <a href="tel:<?php echo sanitize($s['phone']); ?>" class="text-decoration-none text-muted"><?php echo sanitize($s['phone']); ?></a>
                    </div>
                    <div class="col-6 text-end">
                        <i class="fa-solid fa-calendar-day me-1 text-secondary"></i>Tenure: <?php echo $tenureText; ?>
                    </div>
                    <div class="col-12 mt-1">
                        <i class="fa-solid fa-money-bill-wave me-1 text-success"></i>Base Salary: <strong class="text-dark">Rs. <?php echo number_format($s['salary'], 2); ?></strong>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 pt-2 border-top">
                    <button class="btn btn-sm btn-outline-primary flex-grow-1" onclick="viewStaffDossier(<?php echo htmlspecialchars(json_encode($s)); ?>, '<?php echo $tenureText; ?>')">
                        <i class="fa-solid fa-address-card me-1"></i> View Dossier
                    </button>
                    <?php if (hasPermission('hr_manage')): ?>
                        <button class="btn btn-sm btn-light border text-dark" title="Edit Staff" onclick="openStaffModal(<?php echo htmlspecialchars(json_encode($s)); ?>)">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>

<!-- STAFF CONTAINER: TABLE VIEW (HIDDEN BY DEFAULT) -->
<div id="staffTableContainer" class="custom-table-card shadow-sm border-0 mb-5 d-none">
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th>Staff Member</th>
                    <th>EMP No</th>
                    <th>Designation</th>
                    <th>Department</th>
                    <th>Phone / Email</th>
                    <th>Base Salary</th>
                    <th>Tenure</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($staffList)): foreach ($staffList as $s): 
                    $fullName = trim($s['first_name'] . ' ' . $s['last_name']);
                    $initials = strtoupper(substr($s['first_name'], 0, 1) . substr($s['last_name'], 0, 1));
                    $bgColor = getAvatarColor($fullName);
                    $statusClass = [
                        'Active' => 'badge-soft-success',
                        'Inactive' => 'badge-soft-warning',
                        'Terminated' => 'badge-soft-danger'
                    ][$s['status']] ?? 'badge-soft-secondary';
                    $joiningDate = !empty($s['date_of_joining']) ? new DateTime($s['date_of_joining']) : new DateTime();
                    $interval = $joiningDate->diff(new DateTime());
                    $tenureText = $interval->y > 0 ? $interval->y . 'y ' . $interval->m . 'm' : $interval->m . 'm';
                ?>
                    <tr class="staff-table-row" 
                        data-name="<?php echo strtolower(htmlspecialchars($fullName)); ?>"
                        data-emp="<?php echo strtolower(htmlspecialchars($s['employee_no'])); ?>"
                        data-dept="<?php echo htmlspecialchars($s['department']); ?>"
                        data-status="<?php echo htmlspecialchars($s['status']); ?>"
                        data-phone="<?php echo htmlspecialchars($s['phone']); ?>"
                        data-desig="<?php echo strtolower(htmlspecialchars($s['designation'])); ?>">
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="staff-avatar-sm" style="background-color: <?php echo $bgColor; ?>;">
                                    <?php echo $initials; ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark mb-0"><?php echo sanitize($fullName); ?></div>
                                    <?php if ($s['user_id']): ?>
                                        <small class="text-purple"><i class="fa-solid fa-user-shield me-1"></i>Linked Account (#<?php echo $s['user_id']; ?>)</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><code><?php echo sanitize($s['employee_no']); ?></code></td>
                        <td class="fw-semibold text-secondary"><?php echo sanitize($s['designation']); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo sanitize($s['department']); ?></span></td>
                        <td>
                            <div class="small fw-semibold"><?php echo sanitize($s['phone']); ?></div>
                            <small class="text-muted"><?php echo sanitize($s['email'] ?: 'No Email'); ?></small>
                        </td>
                        <td class="fw-bold text-success">Rs. <?php echo number_format($s['salary'], 2); ?></td>
                        <td class="small text-muted"><?php echo $tenureText; ?></td>
                        <td>
                            <span class="badge <?php echo $statusClass; ?> px-3 py-2 rounded-pill"><?php echo sanitize($s['status']); ?></span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary me-1" title="View Dossier" onclick="viewStaffDossier(<?php echo htmlspecialchars(json_encode($s)); ?>, '<?php echo $tenureText; ?>')">
                                <i class="fa-solid fa-address-card"></i>
                            </button>
                            <?php if (hasPermission('hr_manage')): ?>
                            <button class="btn btn-sm btn-outline-secondary" title="Edit" onclick="openStaffModal(<?php echo htmlspecialchars(json_encode($s)); ?>)">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- STAFF DOSSIER INSPECTOR MODAL -->
<div class="modal fade" id="dossierModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;">
            <div class="modal-header border-0 bg-dark text-white p-4" style="border-top-left-radius:18px; border-top-right-radius:18px;">
                <div class="d-flex align-items-center gap-3">
                    <div id="dossierAvatar" class="staff-avatar-lg bg-warning text-dark fs-2"></div>
                    <div>
                        <h4 class="fw-bold text-white mb-0" id="dossierName">Staff Name</h4>
                        <div class="small text-white-50" id="dossierSub">Designation &bull; Department</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3">
                            <small class="text-uppercase text-muted fw-bold tracking-wider d-block mb-2">Employment Information</small>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Employee No:</span>
                                <code class="fw-bold text-dark" id="dossierEmpNo">--</code>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Department:</span>
                                <span class="fw-semibold text-dark" id="dossierDept">--</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Designation:</span>
                                <span class="fw-semibold text-dark" id="dossierDesig">--</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Date of Joining:</span>
                                <span class="fw-semibold text-dark" id="dossierDoj">--</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Tenure:</span>
                                <span class="badge bg-primary text-white" id="dossierTenure">--</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3">
                            <small class="text-uppercase text-muted fw-bold tracking-wider d-block mb-2">Compensation & Access</small>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Basic Monthly Salary:</span>
                                <strong class="text-success fs-5" id="dossierSalary">Rs. 0.00</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Account Status:</span>
                                <span id="dossierStatus">--</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Linked System User:</span>
                                <span class="fw-semibold text-dark" id="dossierUser">--</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Direct Phone:</span>
                                <a href="#" id="dossierPhone" class="fw-bold text-primary text-decoration-none">--</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 mb-3">
                    <small class="text-uppercase text-muted fw-bold tracking-wider d-block mb-1">Residential Address</small>
                    <div class="text-dark fw-semibold" id="dossierAddress">--</div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 bg-light" style="border-bottom-left-radius:18px; border-bottom-right-radius:18px;">
                <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" data-bs-dismiss="modal">Close</button>
                <a href="payroll_setup.php" class="btn btn-primary px-4 rounded-pill">
                    <i class="fa-solid fa-sliders me-1"></i> Manage Payroll Setup
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ADD/EDIT STAFF MODAL -->
<?php if (hasPermission('hr_manage')): ?>
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="modalTitle"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Add Staff Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="staffForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="save_staff">
                    <input type="hidden" name="staff_id" id="staffId" value="0">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" id="f_first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" id="f_last_name" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Employee No</label>
                            <input type="text" class="form-control bg-light" name="employee_no" id="f_employee_no" value="<?php echo $empNo; ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Date of Joining <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_of_joining" id="f_date_of_joining" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Designation <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="designation" id="f_designation" required placeholder="e.g. Senior Lecturer / Accountant">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Department <span class="text-danger">*</span></label>
                            <select class="form-select" name="department" id="f_department" required>
                                <option value="Academic">Academic</option>
                                <option value="Administration">Administration</option>
                                <option value="Accounts">Accounts</option>
                                <option value="Support Staff">Support Staff</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="phone" id="f_phone" required placeholder="03xx-xxxxxxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address</label>
                            <input type="email" class="form-control" name="email" id="f_email" placeholder="staff@indus.edu.pk">
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Basic Monthly Salary (Rs.) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control fw-bold text-success fs-5" name="salary" id="f_salary" min="0" step="0.01" required placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Employment Status</label>
                            <select class="form-select" name="status" id="f_status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Terminated">Terminated</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Residential Address</label>
                        <textarea class="form-control" name="address" id="f_address" rows="2" placeholder="Full postal address..."></textarea>
                    </div>

                    <!-- Connect to System User -->
                    <div class="card border border-primary-subtle bg-primary-soft shadow-sm mt-4">
                        <div class="card-body p-3">
                            <label class="form-label small fw-bold text-primary mb-1"><i class="fa-solid fa-link me-2"></i>Link to System User (Optional)</label>
                            <p class="small text-muted mb-2">Connect this staff profile with a system user login account ID.</p>
                            <input type="number" class="form-control form-control-sm" name="user_id" id="f_user_id" placeholder="User ID (e.g. 5)">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="staffForm" class="btn btn-primary rounded-pill px-4" id="btnSave">
                    <i class="fa-solid fa-check me-1"></i> Save Staff
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="stToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="stToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("stToast");
    const m = document.getElementById("stToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

const defaultEmpNo = "' . $empNo . '";
const defaultDate = "' . date('Y-m-d') . '";

function openStaffModal(data = null) {
    if (data) {
        document.getElementById("modalTitle").innerHTML = `<i class="fa-solid fa-pen me-2 text-primary"></i>Edit Staff Member`;
        document.getElementById("staffId").value = data.id;
        document.getElementById("f_first_name").value = data.first_name;
        document.getElementById("f_last_name").value = data.last_name;
        document.getElementById("f_employee_no").value = data.employee_no;
        document.getElementById("f_date_of_joining").value = data.date_of_joining;
        document.getElementById("f_designation").value = data.designation;
        document.getElementById("f_department").value = data.department;
        document.getElementById("f_phone").value = data.phone;
        document.getElementById("f_email").value = data.email || "";
        document.getElementById("f_salary").value = data.salary;
        document.getElementById("f_status").value = data.status;
        document.getElementById("f_address").value = data.address || "";
        document.getElementById("f_user_id").value = data.user_id || "";
    } else {
        document.getElementById("modalTitle").innerHTML = `<i class="fa-solid fa-user-plus me-2 text-primary"></i>Add Staff Member`;
        document.getElementById("staffForm").reset();
        document.getElementById("staffId").value = "0";
        document.getElementById("f_employee_no").value = defaultEmpNo;
        document.getElementById("f_date_of_joining").value = defaultDate;
    }
    new bootstrap.Modal(document.getElementById("staffModal")).show();
}

function viewStaffDossier(s, tenure) {
    const name = s.first_name + " " + s.last_name;
    const initials = (s.first_name.charAt(0) + s.last_name.charAt(0)).toUpperCase();
    
    document.getElementById("dossierAvatar").textContent = initials;
    document.getElementById("dossierName").textContent = name;
    document.getElementById("dossierSub").textContent = s.designation + " \u2022 " + s.department + " Department";
    document.getElementById("dossierEmpNo").textContent = s.employee_no;
    document.getElementById("dossierDept").textContent = s.department;
    document.getElementById("dossierDesig").textContent = s.designation;
    document.getElementById("dossierDoj").textContent = s.date_of_joining;
    document.getElementById("dossierTenure").textContent = tenure;
    document.getElementById("dossierSalary").textContent = "Rs. " + parseFloat(s.salary).toLocaleString("en-US", {minimumFractionDigits: 2});
    document.getElementById("dossierStatus").innerHTML = `<span class="badge bg-success-soft text-success px-3 py-1 rounded-pill">${s.status}</span>`;
    document.getElementById("dossierUser").textContent = s.user_id ? "Linked (ID #" + s.user_id + ")" : "Not Linked";
    document.getElementById("dossierPhone").textContent = s.phone;
    document.getElementById("dossierPhone").href = "tel:" + s.phone;
    document.getElementById("dossierAddress").textContent = s.address || "No address provided.";
    
    new bootstrap.Modal(document.getElementById("dossierModal")).show();
}

function toggleView(mode) {
    const grid = document.getElementById("staffGridContainer");
    const table = document.getElementById("staffTableContainer");
    const btnGrid = document.getElementById("btnViewGrid");
    const btnTable = document.getElementById("btnViewTable");

    if (mode === "grid") {
        grid.classList.remove("d-none");
        table.classList.add("d-none");
        btnGrid.classList.add("active");
        btnTable.classList.remove("active");
    } else {
        grid.classList.add("d-none");
        table.classList.remove("d-none");
        btnTable.classList.add("active");
        btnGrid.classList.remove("active");
    }
}

function filterStaff() {
    const q = document.getElementById("staffSearchInput").value.toLowerCase();
    const dept = document.getElementById("deptFilter").value;
    const status = document.getElementById("statusFilter").value;

    // Filter Grid
    const cards = document.querySelectorAll(".staff-card-item");
    cards.forEach(card => {
        const name = card.dataset.name;
        const emp = card.dataset.emp;
        const cardDept = card.dataset.dept;
        const cardStatus = card.dataset.status;
        const phone = card.dataset.phone;
        const desig = card.dataset.desig;

        const matchQ = !q || name.includes(q) || emp.includes(q) || phone.includes(q) || desig.includes(q);
        const matchDept = !dept || cardDept === dept;
        const matchStatus = !status || cardStatus === status;

        if (matchQ && matchDept && matchStatus) card.classList.remove("d-none");
        else card.classList.add("d-none");
    });

    // Filter Table
    const rows = document.querySelectorAll(".staff-table-row");
    rows.forEach(row => {
        const name = row.dataset.name;
        const emp = row.dataset.emp;
        const rowDept = row.dataset.dept;
        const rowStatus = row.dataset.status;
        const phone = row.dataset.phone;
        const desig = row.dataset.desig;

        const matchQ = !q || name.includes(q) || emp.includes(q) || phone.includes(q) || desig.includes(q);
        const matchDept = !dept || rowDept === dept;
        const matchStatus = !status || rowStatus === status;

        if (matchQ && matchDept && matchStatus) row.classList.remove("d-none");
        else row.classList.add("d-none");
    });
}

function exportStaffCSV() {
    window.location.href = "../../modules/reports/export.php?type=staff_roster";
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("staffForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSave");
            btn.disabled = true; 
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...`;
            
            fetch("../../ajax/staff.php", { method: "POST", body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { 
                        btn.disabled = false; 
                        btn.innerHTML = `<i class="fa-solid fa-check me-1"></i> Save Staff`; 
                    }
                });
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
