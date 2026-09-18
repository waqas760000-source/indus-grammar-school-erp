<?php
/**
 * Indus Grammar School ERP - Family Phone Numbers List
 * Version 3.0.0
 */

$pageTitle = 'Family Phone Numbers List';
$breadcrumbActive = 'Student Registration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

// Search and filter parameters
$search_class = (int)($_GET['search_class'] ?? 0);
$search_campus = sanitize($_GET['search_campus'] ?? '');
$search_academic_type = sanitize($_GET['search_academic_type'] ?? '');

// Pagination settings
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$limit = 12; // Family cards per page
$offset = ($page - 1) * $limit;

// Fetch class list for dropdown filter
$classes = [];
try {
    $classes = $db->query("SELECT * FROM classes ORDER BY class_name ASC, section ASC")->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch classes: " . $e->getMessage());
}

// Build dynamic WHERE clause safely using PDO
$where = " WHERE s.status = 'Active'";
$params = [];

if ($search_class > 0) {
    $where .= " AND s.class_id = :search_class";
    $params['search_class'] = $search_class;
}
if ($search_campus !== '') {
    $where .= " AND d.campus = :search_campus";
    $params['search_campus'] = $search_campus;
}
if ($search_academic_type !== '') {
    $where .= " AND (s.academic_type = :search_academic_type OR d.academic_type = :search_academic_type)";
    $params['search_academic_type'] = $search_academic_type;
}

$allRecords = [];
$totalStudentsCount = 0;
$totalFamiliesCount = 0;
$totalPhoneContactsCount = 0;
$groupedFamilies = [];

try {
    // Fetch all active matching records for dynamic grouping & metrics calculation
    $sqlAll = "
        SELECT s.id, s.admission_no, s.first_name, s.last_name, s.guardian_name, s.guardian_phone, s.academic_type, 
               s.school_class, s.school_section, s.academy_program, s.academy_batch, s.status,
               c.class_name, c.section,
               d.roll_no, d.father_name, d.father_cnic, d.father_mobile, d.mother_name, d.mother_mobile,
               d.guardian_relationship, d.guardian_cnic, d.student_mobile, d.current_address, d.campus, d.doc_student_photo
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY d.father_name ASC, s.first_name ASC
    ";
    $stmtAll = $db->prepare($sqlAll);
    $stmtAll->execute($params);
    $allRecords = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

    $totalStudentsCount = count($allRecords);

    // Group records into family units
    foreach ($allRecords as $r) {
        $fatherCnicClean = preg_replace('/[^0-9]/', '', $r['father_cnic'] ?? '');
        $fatherMobileClean = preg_replace('/[^0-9]/', '', $r['father_mobile'] ?? '');
        $guardianPhoneClean = preg_replace('/[^0-9]/', '', $r['guardian_phone'] ?? '');
        $fatherNameClean = strtolower(trim($r['father_name'] ?: ($r['guardian_name'] ?: '')));

        if (!empty($fatherCnicClean)) {
            $fKey = 'cnic_' . $fatherCnicClean;
        } elseif (!empty($fatherMobileClean)) {
            $fKey = 'mob_' . $fatherMobileClean . '_' . $fatherNameClean;
        } elseif (!empty($guardianPhoneClean)) {
            $fKey = 'gphone_' . $guardianPhoneClean . '_' . $fatherNameClean;
        } else {
            $fKey = 'std_' . $r['id'];
        }

        if (!isset($groupedFamilies[$fKey])) {
            $primaryPhone = $r['father_mobile'] ?: $r['guardian_phone'];
            $groupedFamilies[$fKey] = [
                'key' => $fKey,
                'father_name' => $r['father_name'] ?: ($r['guardian_name'] ?: 'Family Record'),
                'father_cnic' => $r['father_cnic'] ?: ($r['guardian_cnic'] ?: ''),
                'primary_phone' => $primaryPhone,
                'mother_name' => $r['mother_name'] ?? '',
                'mother_mobile' => $r['mother_mobile'] ?? '',
                'guardian_relationship' => $r['guardian_relationship'] ?: 'Father',
                'current_address' => $r['current_address'] ?? 'Address not recorded',
                'campus' => $r['campus'] ?: 'Main Campus',
                'students' => []
            ];

            if (!empty($primaryPhone)) {
                $totalPhoneContactsCount++;
            }
        }
        $groupedFamilies[$fKey]['students'][] = $r;
    }

    $totalFamiliesCount = count($groupedFamilies);

} catch (Exception $e) {
    error_log("Family Phone List Query Error: " . $e->getMessage());
}

// Slice grouped families for current page
$familyKeys = array_keys($groupedFamilies);
$totalFamilyPages = ceil($totalFamiliesCount / $limit);
if ($totalFamilyPages < 1) $totalFamilyPages = 1;

$currentPageKeys = array_slice($familyKeys, $offset, $limit);
$pageFamilies = [];
foreach ($currentPageKeys as $k) {
    $pageFamilies[] = $groupedFamilies[$k];
}

// Slice table records for current page directory view
$totalTablePages = ceil($totalStudentsCount / $limit);
if ($totalTablePages < 1) $totalTablePages = 1;
$pageRecords = array_slice($allRecords, $offset, $limit);
?>

<!-- Custom CSS Styling for Premium ERP UI -->
<style>
.family-header-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
}

.stat-card-custom {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    transition: all 0.25s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.stat-card-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.06);
    border-color: #cbd5e1;
}

.stat-icon-wrapper {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}

.search-workspace-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);
}

.family-group-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.25s ease;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);
}

.family-group-card:hover {
    box-shadow: 0 10px 20px -5px rgba(0,0,0,0.08);
    border-color: #93c5fd;
}

.family-card-top {
    background: linear-gradient(90deg, #f8fafc 0%, #eff6ff 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 1.1rem 1.25rem;
}

.phone-badge-pill {
    background: #ffffff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    padding: 0.35rem 0.75rem;
    border-radius: 50rem;
    font-weight: 600;
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.phone-badge-pill a {
    color: inherit;
    text-decoration: none;
}

.phone-badge-pill a:hover {
    color: #2563eb;
    text-decoration: underline;
}

.copy-btn {
    border: none;
    background: transparent;
    color: #64748b;
    padding: 0 0.2rem;
    cursor: pointer;
    transition: color 0.15s;
}

.copy-btn:hover {
    color: #2563eb;
}

.student-subtable {
    margin-bottom: 0;
}

.student-subtable th {
    background: #f8fafc;
    color: #475569;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.6rem 0.8rem;
    border-bottom: 1px solid #e2e8f0;
}

.student-subtable td {
    padding: 0.65rem 0.8rem;
    vertical-align: middle;
    font-size: 0.875rem;
    border-bottom: 1px solid #f1f5f9;
}

.student-subtable tr:last-child td {
    border-bottom: none;
}

.avatar-circle {
    width: 34px;
    height: 34px;
    background: #e0e7ff;
    color: #3730a3;
    font-weight: 700;
    font-size: 0.85rem;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* Custom Table View Styling */
.custom-table {
    border-collapse: separate;
    border-spacing: 0;
}

.custom-table thead th {
    background: #0f172a;
    color: #f8fafc;
    font-weight: 600;
    font-size: 0.825rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.85rem 1rem;
    border: none;
}

.custom-table thead th:first-child {
    border-top-left-radius: 10px;
}

.custom-table thead th:last-child {
    border-top-right-radius: 10px;
}

.custom-table tbody td {
    padding: 0.85rem 1rem;
    font-size: 0.885rem;
    border-bottom: 1px solid #e2e8f0;
    background: #ffffff;
}

.custom-table tbody tr:hover td {
    background: #f8fafc;
}

/* Toast Message */
#copyToast {
    position: fixed;
    bottom: 25px;
    right: 25px;
    z-index: 9999;
    background: #0f172a;
    color: #ffffff;
    padding: 0.75rem 1.25rem;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    display: none;
    font-size: 0.875rem;
}

/* Print Layout */
@media print {
    .d-print-none, #sidebar, header, nav, footer, .btn-view-toggle {
        display: none !important;
    }
    body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 11pt;
    }
    .container-fluid {
        padding: 0 !important;
    }
    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 20px;
        border-bottom: 2px solid #000000;
        padding-bottom: 10px;
    }
    .print-table {
        width: 100% !important;
        border-collapse: collapse !important;
    }
    .print-table th, .print-table td {
        border: 1px solid #444444 !important;
        padding: 6px 8px !important;
        font-size: 10pt !important;
    }
    .print-table th {
        background-color: #f1f5f9 !important;
        color: #000000 !important;
    }
}
@media (min-width: 992px) {
    .print-header {
        display: none;
    }
}
</style>

<!-- Printable Only Header -->
<div class="print-header d-none d-print-block">
    <h2 style="font-weight: 800; margin-bottom: 2px; color: #0f172a;">INDUS GRAMMAR SCHOOL</h2>
    <h4 style="margin-bottom: 5px; color: #334155;">Family Phone Numbers List</h4>
    <p style="font-size: 9pt; color: #64748b; margin-bottom: 0;">
        Generated On: <?php echo date('F j, Y - g:i A'); ?> | Total Records: <?php echo $totalStudentsCount; ?>
    </p>
</div>

<!-- Page Title Header Banner -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3 d-print-none">
    <div class="d-flex align-items-center">
        <div class="family-header-icon me-3">
            <i class="fa-solid fa-phone-volume fa-lg"></i>
        </div>
        <div>
            <h3 class="fw-bold text-dark mb-1">Family Phone Numbers List</h3>
            <p class="text-muted small mb-0">View and manage family phone numbers and student contact information in an organized format.</p>
        </div>
    </div>
    
    <div class="d-flex flex-wrap gap-2">
        <!-- View Toggle Buttons -->
        <div class="btn-group me-1" role="group" aria-label="View switch">
            <button type="button" class="btn btn-sm btn-primary active" id="btnCardsView" onclick="switchView('cards')">
                <i class="fa-solid fa-border-all me-1"></i> Family Cards
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnTableView" onclick="switchView('table')">
                <i class="fa-solid fa-table-list me-1"></i> Directory Table
            </button>
        </div>

        <button class="btn btn-sm btn-outline-primary px-3 shadow-sm" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print List
        </button>
        <button class="btn btn-sm btn-outline-success px-3 shadow-sm" onclick="exportFamilyCSV()">
            <i class="fa-solid fa-file-excel me-1"></i> Export CSV
        </button>
    </div>
</div>

<!-- Summary Statistics Metrics Cards -->
<div class="row g-3 mb-4 d-print-none">
    <div class="col-6 col-md-3">
        <div class="stat-card-custom d-flex align-items-center">
            <div class="stat-icon-wrapper bg-primary bg-opacity-10 text-primary me-3">
                <i class="fa-solid fa-people-roof"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold">Total Families</div>
                <div class="fs-4 fw-bold text-dark"><?php echo number_format($totalFamiliesCount); ?></div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card-custom d-flex align-items-center">
            <div class="stat-icon-wrapper bg-info bg-opacity-10 text-info me-3">
                <i class="fa-solid fa-user-graduate"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold">Active Students</div>
                <div class="fs-4 fw-bold text-dark"><?php echo number_format($totalStudentsCount); ?></div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card-custom d-flex align-items-center">
            <div class="stat-icon-wrapper bg-success bg-opacity-10 text-success me-3">
                <i class="fa-solid fa-phone"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold">Primary Contacts</div>
                <div class="fs-4 fw-bold text-dark"><?php echo number_format($totalPhoneContactsCount); ?></div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card-custom d-flex align-items-center">
            <div class="stat-icon-wrapper bg-warning bg-opacity-10 text-warning me-3">
                <i class="fa-solid fa-filter"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold">Current Matches</div>
                <div class="fs-4 fw-bold text-dark"><?php echo number_format($totalStudentsCount); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Workspace Panel -->
<div class="card search-workspace-card p-3 mb-4 d-print-none">
    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-sliders text-primary me-2"></i>Filter Family Phone Numbers</h6>
        <span class="badge bg-light text-muted border">DB Schema Validated</span>
    </div>

    <form method="GET" action="family_phone_list.php" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-secondary mb-1">Class & Section</label>
            <select class="form-select form-select-sm" name="search_class">
                <option value="">All Classes</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($search_class == $c['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(($c['class_name'] ?? '') . ' - ' . ($c['section'] ?? '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label small fw-semibold text-secondary mb-1">Campus Location</label>
            <select class="form-select form-select-sm" name="search_campus">
                <option value="">All Campuses</option>
                <option value="Main Campus" <?php echo ($search_campus === 'Main Campus') ? 'selected' : ''; ?>>Main Campus</option>
                <option value="Boys Campus" <?php echo ($search_campus === 'Boys Campus') ? 'selected' : ''; ?>>Boys Campus</option>
                <option value="Junior Campus" <?php echo ($search_campus === 'Junior Campus') ? 'selected' : ''; ?>>Junior Campus</option>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label small fw-semibold text-secondary mb-1">Academic Type</label>
            <select class="form-select form-select-sm" name="search_academic_type">
                <option value="">All Academic Types</option>
                <option value="School" <?php echo ($search_academic_type === 'School') ? 'selected' : ''; ?>>School</option>
                <option value="Academy" <?php echo ($search_academic_type === 'Academy') ? 'selected' : ''; ?>>Academy</option>
            </select>
        </div>

        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary w-100 shadow-sm">
                <i class="fa-solid fa-filter me-1"></i> Apply Filters
            </button>
            <a href="family_phone_list.php" class="btn btn-sm btn-light border text-secondary px-3" title="Reset Filters">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
        </div>
    </form>
</div>

<!-- ========================================================= -->
<!-- PRESENTATION VIEW 1: FAMILY CARDS VIEW (DEFAULT)          -->
<!-- ========================================================= -->
<div id="familyCardsContainer" class="d-print-none">
    <?php if (empty($pageFamilies)): ?>
        <div class="card border-0 shadow-sm p-5 text-center bg-white rounded-4">
            <div class="mb-3 text-muted">
                <i class="fa-solid fa-users-slash fa-3x text-secondary opacity-50"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">No Family Records Found</h5>
            <p class="text-muted small max-width-400 mx-auto mb-3">No family records match the selected filter criteria.</p>
            <div>
                <a href="family_phone_list.php" class="btn btn-sm btn-primary px-4"><i class="fa-solid fa-rotate-left me-1"></i> Reset Filters</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($pageFamilies as $fam): ?>
                <div class="col-12 col-xl-6 mb-4">
                    <div class="family-group-card h-100">
                        <!-- Family Top Header -->
                        <div class="family-card-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <h5 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($fam['father_name']); ?></h5>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2">
                                        <?php echo htmlspecialchars($fam['guardian_relationship']); ?>
                                    </span>
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2">
                                        <?php echo count($fam['students']); ?> Student<?php echo count($fam['students']) > 1 ? 's' : ''; ?>
                                    </span>
                                </div>
                                <?php if (!empty($fam['father_cnic'])): ?>
                                    <div class="small text-muted">
                                        <i class="fa-solid fa-id-card me-1"></i>CNIC: <strong><?php echo htmlspecialchars($fam['father_cnic']); ?></strong>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Phone Badge Pill -->
                            <div>
                                <?php if (!empty($fam['primary_phone'])): ?>
                                    <span class="phone-badge-pill shadow-xs">
                                        <i class="fa-solid fa-phone text-primary"></i>
                                        <a href="tel:<?php echo htmlspecialchars($fam['primary_phone']); ?>"><?php echo htmlspecialchars($fam['primary_phone']); ?></a>
                                        <button class="copy-btn" onclick="copyPhone('<?php echo htmlspecialchars($fam['primary_phone']); ?>')" title="Copy Phone Number">
                                            <i class="fa-regular fa-copy"></i>
                                        </button>
                                        <a href="https://wa.me/92<?php echo ltrim(preg_replace('/[^0-9]/', '', $fam['primary_phone']), '0'); ?>" target="_blank" class="text-success ms-1" title="Open WhatsApp">
                                            <i class="fa-brands fa-whatsapp"></i>
                                        </a>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">No Phone Recorded</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Family Details Subheader -->
                        <div class="px-3 py-2 bg-white border-bottom small text-muted d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <?php if (!empty($fam['mother_name'])): ?>
                                    <span class="me-3"><i class="fa-solid fa-person-breastfeeding me-1 text-secondary"></i>Mother: <strong><?php echo htmlspecialchars($fam['mother_name']); ?></strong><?php echo !empty($fam['mother_mobile']) ? ' (' . htmlspecialchars($fam['mother_mobile']) . ')' : ''; ?></span>
                                <?php endif; ?>
                                <span><i class="fa-solid fa-location-dot me-1 text-secondary"></i><?php echo htmlspecialchars($fam['current_address']); ?></span>
                            </div>
                            <div>
                                <span class="badge bg-light text-dark border">
                                    <i class="fa-solid fa-building-columns me-1 text-primary"></i><?php echo htmlspecialchars($fam['campus']); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Associated Students List -->
                        <div class="table-responsive">
                            <table class="table student-subtable align-middle">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Roll / Adm No</th>
                                        <th>Class & Section</th>
                                        <th>Academic Type</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fam['students'] as $st): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-2">
                                                        <?php echo strtoupper(substr($st['first_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong class="text-dark"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></strong>
                                                        <?php if (!empty($st['student_mobile'])): ?>
                                                            <div class="small text-muted"><i class="fa-solid fa-mobile-screen me-1"></i><?php echo htmlspecialchars($st['student_mobile']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <code class="text-primary fw-semibold"><?php echo htmlspecialchars($st['admission_no'] ?: ($st['roll_no'] ?: '-')); ?></code>
                                            </td>
                                            <td>
                                                <span class="fw-semibold text-secondary">
                                                    <?php echo htmlspecialchars(($st['class_name'] ?? $st['school_class'] ?? 'N/A') . ' ' . ($st['section'] ?? $st['school_section'] ?? '')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (($st['academic_type'] ?? '') === 'Academy'): ?>
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">Academy</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">School</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="profile_report.php?id=<?php echo $st['id']; ?>" class="btn btn-xs btn-outline-primary py-1 px-2 rounded-2" title="View Profile">
                                                    <i class="fa-solid fa-arrow-right-long me-1"></i>Profile
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Family Cards Pagination -->
        <?php if ($totalFamilyPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-3 mb-4 bg-white p-3 rounded-3 border">
                <div class="text-muted small">
                    Showing Page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalFamilyPages; ?></strong> (<?php echo $totalFamiliesCount; ?> Total Families)
                </div>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                    </li>
                    <?php for ($p = 1; $p <= $totalFamilyPages; $p++): ?>
                        <?php if ($p == 1 || $p == $totalFamilyPages || abs($p - $page) <= 2): ?>
                            <li class="page-item <?php echo ($p == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php elseif (abs($p - $page) == 3): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $totalFamilyPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ========================================================= -->
<!-- PRESENTATION VIEW 2: DIRECTORY TABLE VIEW                 -->
<!-- ========================================================= -->
<div id="familyTableContainer" class="d-print-none" style="display: none;">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table custom-table align-middle mb-0" id="directoryTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name & ID</th>
                        <th>Father Name & CNIC</th>
                        <th>Guardian / Rel</th>
                        <th>Father / Guardian Phone</th>
                        <th>Mother & Contact</th>
                        <th>Campus & Class</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pageRecords)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No family contacts matched your filter criteria.</td>
                        </tr>
                    <?php else: foreach ($pageRecords as $idx => $r): ?>
                        <tr>
                            <td class="text-muted small"><?php echo $offset + $idx + 1; ?></td>
                            <td>
                                <strong class="text-dark d-block"><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></strong>
                                <small class="text-primary font-monospace"><?php echo htmlspecialchars($r['admission_no'] ?: ($r['roll_no'] ?: '-')); ?></small>
                            </td>
                            <td>
                                <div><strong><?php echo htmlspecialchars($r['father_name'] ?: '-'); ?></strong></div>
                                <?php if (!empty($r['father_cnic'])): ?>
                                    <small class="text-muted"><i class="fa-solid fa-id-card me-1"></i><?php echo htmlspecialchars($r['father_cnic']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($r['guardian_name'] ?: '-'); ?></div>
                                <span class="badge bg-light text-dark border rounded-pill small"><?php echo htmlspecialchars($r['guardian_relationship'] ?: 'Father'); ?></span>
                            </td>
                            <td>
                                <?php $ph = $r['father_mobile'] ?: $r['guardian_phone']; ?>
                                <?php if (!empty($ph)): ?>
                                    <span class="phone-badge-pill py-1 px-2">
                                        <a href="tel:<?php echo htmlspecialchars($ph); ?>"><?php echo htmlspecialchars($ph); ?></a>
                                        <button class="copy-btn" onclick="copyPhone('<?php echo htmlspecialchars($ph); ?>')"><i class="fa-regular fa-copy"></i></button>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($r['mother_name'] ?: '-'); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($r['mother_mobile'] ?: '-'); ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars(($r['class_name'] ?? $r['school_class'] ?? '-') . ' - ' . ($r['section'] ?? $r['school_section'] ?? 'A')); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($r['campus'] ?: 'Main Campus'); ?></small>
                            </td>
                            <td>
                                <a href="profile_report.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="View Profile">
                                    <i class="fa-solid fa-user me-1"></i>Profile
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Table View Pagination -->
    <?php if ($totalTablePages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded-3 border shadow-sm">
            <div class="text-muted small">
                Showing Page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalTablePages; ?></strong> (<?php echo $totalStudentsCount; ?> Total Students)
            </div>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                </li>
                <?php for ($p = 1; $p <= $totalTablePages; $p++): ?>
                    <?php if ($p == 1 || $p == $totalTablePages || abs($p - $page) <= 2): ?>
                        <li class="page-item <?php echo ($p == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php elseif (abs($p - $page) == 3): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $totalTablePages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================================= -->
<!-- PRINTABLE FULL DIRECTORY TABLE (VISIBLE ONLY IN PRINT)     -->
<!-- ========================================================= -->
<div class="d-none d-print-block">
    <table class="print-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Student Name</th>
                <th>Admission No</th>
                <th>Class & Section</th>
                <th>Father / Guardian Name</th>
                <th>Guardian Rel</th>
                <th>Primary Contact Phone</th>
                <th>Address</th>
                <th>Campus</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allRecords as $idx => $r): ?>
                <tr>
                    <td><?php echo $idx + 1; ?></td>
                    <td><strong><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($r['admission_no'] ?: ($r['roll_no'] ?: '-')); ?></td>
                    <td><?php echo htmlspecialchars(($r['class_name'] ?? $r['school_class'] ?? '-') . ' - ' . ($r['section'] ?? $r['school_section'] ?? 'A')); ?></td>
                    <td><?php echo htmlspecialchars($r['father_name'] ?: ($r['guardian_name'] ?: '-')); ?></td>
                    <td><?php echo htmlspecialchars($r['guardian_relationship'] ?: 'Father'); ?></td>
                    <td><strong><?php echo htmlspecialchars($r['father_mobile'] ?: ($r['guardian_phone'] ?: '-')); ?></strong></td>
                    <td><?php echo htmlspecialchars($r['current_address'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['campus'] ?: 'Main Campus'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Toast Popup Notification -->
<div id="copyToast">
    <i class="fa-solid fa-circle-check text-success me-2"></i>Phone number copied to clipboard!
</div>

<!-- Client-side JavaScript Functionality -->
<script>
// View switcher between Cards and Table view
function switchView(mode) {
    const cardsCont = document.getElementById('familyCardsContainer');
    const tableCont = document.getElementById('familyTableContainer');
    const btnCards = document.getElementById('btnCardsView');
    const btnTable = document.getElementById('btnTableView');

    if (mode === 'table') {
        cardsCont.style.display = 'none';
        tableCont.style.display = 'block';
        btnTable.classList.add('btn-primary', 'active');
        btnTable.classList.remove('btn-outline-secondary');
        btnCards.classList.remove('btn-primary', 'active');
        btnCards.classList.add('btn-outline-secondary');
        localStorage.setItem('family_phone_view_mode', 'table');
    } else {
        tableCont.style.display = 'none';
        cardsCont.style.display = 'block';
        btnCards.classList.add('btn-primary', 'active');
        btnCards.classList.remove('btn-outline-secondary');
        btnTable.classList.remove('btn-primary', 'active');
        btnTable.classList.add('btn-outline-secondary');
        localStorage.setItem('family_phone_view_mode', 'cards');
    }
}

// Load saved view preference on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedMode = localStorage.getItem('family_phone_view_mode');
    if (savedMode === 'table') {
        switchView('table');
    }
});

// Copy Phone number to clipboard with Toast message
function copyPhone(phone) {
    if (!phone) return;
    navigator.clipboard.writeText(phone).then(() => {
        const toast = document.getElementById('copyToast');
        toast.style.display = 'block';
        setTimeout(() => {
            toast.style.display = 'none';
        }, 2500);
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
}

// Export All Filtered Family Data as CSV
function exportFamilyCSV() {
    let csv = "Student Name,Admission No,Class,Father Name,Father CNIC,Guardian Rel,Primary Phone,Mother Name,Address,Campus\n";
    
    <?php 
    $csvData = [];
    foreach ($allRecords as $r) {
        $stName = addslashes($r['first_name'] . ' ' . $r['last_name']);
        $admNo = addslashes($r['admission_no'] ?: ($r['roll_no'] ?: ''));
        $cls = addslashes(($r['class_name'] ?? $r['school_class'] ?? '-') . ' ' . ($r['section'] ?? $r['school_section'] ?? ''));
        $fName = addslashes($r['father_name'] ?: ($r['guardian_name'] ?: ''));
        $fCnic = addslashes($r['father_cnic'] ?: ($r['guardian_cnic'] ?: ''));
        $rel = addslashes($r['guardian_relationship'] ?: 'Father');
        $phone = addslashes($r['father_mobile'] ?: ($r['guardian_phone'] ?: ''));
        $mName = addslashes($r['mother_name'] ?? '');
        $addr = addslashes(str_replace(["\r", "\n", ","], " ", $r['current_address'] ?? ''));
        $campus = addslashes($r['campus'] ?: 'Main Campus');

        $csvData[] = "\"$stName\",\"$admNo\",\"$cls\",\"$fName\",\"$fCnic\",\"$rel\",\"$phone\",\"$mName\",\"$addr\",\"$campus\"";
    }
    ?>
    
    const rows = [
        <?php echo implode(",\n", $csvData); ?>
    ];

    rows.forEach(row => {
        csv += row + "\n";
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "family_phone_directory_<?php echo date('Ymd'); ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>

