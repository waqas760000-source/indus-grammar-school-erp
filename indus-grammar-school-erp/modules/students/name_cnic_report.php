<?php
/**
 * Indus Grammar School ERP - Student Name / CNIC Report
 * Version 2.0.0
 */

$pageTitle = 'Student Name / CNIC Report';
$breadcrumbActive = 'Student Registration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

// Filter parameters
$search_name = sanitize($_GET['search_name'] ?? '');
$search_father = sanitize($_GET['search_father'] ?? '');
$search_admission = sanitize($_GET['search_admission'] ?? '');
$search_roll = sanitize($_GET['search_roll'] ?? '');
$search_cnic = sanitize($_GET['search_cnic'] ?? '');
$search_bform = sanitize($_GET['search_bform'] ?? '');
$search_mobile = sanitize($_GET['search_mobile'] ?? '');
$search_class = (int)($_GET['search_class'] ?? 0);
$search_section = sanitize($_GET['search_section'] ?? '');

$classes = [];
try {
    $classes = $db->query("SELECT * FROM classes ORDER BY class_name ASC")->fetchAll();
} catch (Exception $e) {}

// Query matching students
$records = [];
try {
    $sql = "
        SELECT s.id, s.admission_no, s.first_name, s.last_name, s.guardian_phone, s.academic_type, s.school_class, s.school_section, s.academy_program, s.academy_batch, c.class_name, c.section,
               d.roll_no, d.cnic_no, d.birth_cert_no, d.father_name, d.father_cnic, d.mother_cnic, d.student_mobile
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        WHERE s.status = 'Active'
    ";
    $params = [];

    if ($search_name) {
        $sql .= " AND (s.first_name LIKE :name OR s.last_name LIKE :name)";
        $params['name'] = '%' . $search_name . '%';
    }
    if ($search_father) {
        $sql .= " AND d.father_name LIKE :father";
        $params['father'] = '%' . $search_father . '%';
    }
    if ($search_admission) {
        $sql .= " AND s.admission_no = :admission";
        $params['admission'] = $search_admission;
    }
    if ($search_roll) {
        $sql .= " AND d.roll_no = :roll";
        $params['roll'] = $search_roll;
    }
    if ($search_cnic) {
        $sql .= " AND d.cnic_no = :cnic";
        $params['cnic'] = $search_cnic;
    }
    if ($search_bform) {
        $sql .= " AND d.birth_cert_no = :bform";
        $params['bform'] = $search_bform;
    }
    if ($search_mobile) {
        $sql .= " AND (d.student_mobile = :mobile OR s.guardian_phone = :mobile)";
        $params['mobile'] = $search_mobile;
    }
    if ($search_class > 0) {
        $sql .= " AND s.class_id = :class_id";
        $params['class_id'] = $search_class;
    }
    if ($search_section) {
        $sql .= " AND c.section = :section";
        $params['section'] = $search_section;
    }

    $sql .= " ORDER BY s.admission_no DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("CNIC Report query error: " . $e->getMessage());
}
?>

<!-- Title Header -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-passport me-2 text-primary"></i>Student Name / CNIC Report</h3>
    </div>
</div>

<!-- Filters Panel -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Registry</h6>
    <form method="GET" action="name_cnic_report.php" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Student Name</label>
            <input type="text" class="form-control form-control-sm" name="search_name" value="<?php echo $search_name; ?>" placeholder="Name...">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Father Name</label>
            <input type="text" class="form-control form-control-sm" name="search_father" value="<?php echo $search_father; ?>" placeholder="Father Name...">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Admission No</label>
            <input type="text" class="form-control form-control-sm" name="search_admission" value="<?php echo $search_admission; ?>" placeholder="IGS-AD-XXXX-XXXX">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Roll No</label>
            <input type="text" class="form-control form-control-sm" name="search_roll" value="<?php echo $search_roll; ?>" placeholder="IGS-ROLL-XXXX-XXXX">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">B-Form / CNIC</label>
            <input type="text" class="form-control form-control-sm" name="search_cnic" value="<?php echo $search_cnic; ?>" placeholder="35201-XXXXXXX-X">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Birth Cert. No</label>
            <input type="text" class="form-control form-control-sm" name="search_bform" value="<?php echo $search_bform; ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Mobile</label>
            <input type="tel" class="form-control form-control-sm" name="search_mobile" value="<?php echo $search_mobile; ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-muted">Class</label>
            <select class="form-select form-select-sm" name="search_class">
                <option value="">All</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($search_class == $c['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label small fw-semibold text-muted">Sec.</label>
            <input type="text" class="form-control form-control-sm" name="search_section" value="<?php echo $search_section; ?>">
        </div>
        <div class="col-12 text-end">
            <button type="submit" class="btn btn-sm btn-primary px-4"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
            <a href="name_cnic_report.php" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
        </div>
    </form>
</div>

<!-- Report Table Card -->
<div class="card border border-light shadow-sm bg-white p-4" style="border-radius:12px;">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4 d-print-none">
        <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-passport text-primary me-2"></i>CNIC Registry Report</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Report</button>
            <button class="btn btn-sm btn-outline-success px-3" onclick="exportExcel()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle" id="cnicTable">
            <thead>
                <tr>
                    <th>Adm. No</th>
                    <th>Roll No</th>
                    <th>Student Name</th>
                    <th>Father Name</th>
                    <th>Student CNIC / B-Form</th>
                    <th>Father CNIC</th>
                    <th>Mother CNIC</th>
                    <th>Contact Phone</th>
                    <th>Class</th>
                    <th class="text-end d-print-none">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="10" class="text-center py-4 text-muted">No student profiles registered with CNIC info match.</td></tr>
                <?php else: foreach ($records as $r): ?>
                    <tr>
                        <td class="fw-bold text-dark"><?php echo sanitize($r['admission_no']); ?></td>
                        <td><?php echo sanitize($r['roll_no'] ?: '-'); ?></td>
                        <td class="fw-bold"><?php echo sanitize($r['first_name'] . ' ' . $r['last_name']); ?></td>
                        <td><?php echo sanitize($r['father_name'] ?: '-'); ?></td>
                        <td><?php echo sanitize($r['cnic_no'] ?: '-'); ?></td>
                        <td><?php echo sanitize($r['father_cnic'] ?: '-'); ?></td>
                        <td><?php echo sanitize($r['mother_cnic'] ?: '-'); ?></td>
                        <td><?php echo sanitize($r['student_mobile'] ?: $r['guardian_phone']); ?></td>
                        <td><?php echo sanitize(($r['class_name'] ?? $r['school_class'] ?? '-') . ' - ' . ($r['section'] ?? $r['school_section'] ?? 'A')); ?></td>
                        <td class="text-end d-print-none">
                            <a href="detail_report.php?view_id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Profile"><i class="fa-solid fa-eye"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// CSV exporter trigger
function exportExcel() {
    let csv = "Admission No,Roll No,Student Name,Father Name,Student CNIC,Father CNIC,Mother CNIC,Contact Phone,Class\n";
    const rows = document.querySelectorAll("#cnicTable tbody tr");
    
    rows.forEach(tr => {
        const cols = tr.querySelectorAll("td");
        if(cols.length === 10) {
            let rowData = [];
            // Omit the actions cell (index 9)
            for(let i=0; i<9; i++) {
                let text = cols[i].textContent.replace(/"/g, '""').replace(/,/g, ' ').trim();
                rowData.push('"' + text + '"');
            }
            csv += rowData.join(",") + "\n";
        }
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "student_cnic_audit_report.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
