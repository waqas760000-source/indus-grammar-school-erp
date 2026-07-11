<?php
/**
 * Indus Grammar School ERP - Family Wise Phone List
 * Version 2.0.0
 */

$pageTitle = 'Family Wise Phone List';
$breadcrumbActive = 'Student Registration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

// Filter parameters
$search_student = sanitize($_GET['search_student'] ?? '');
$search_family = sanitize($_GET['search_family'] ?? '');
$search_phone = sanitize($_GET['search_phone'] ?? '');
$search_class = (int)($_GET['search_class'] ?? 0);

$classes = [];
try {
    $classes = $db->query("SELECT * FROM classes ORDER BY class_name ASC")->fetchAll();
} catch (Exception $e) {}

// Query matching students and parent details
$records = [];
try {
    $sql = "
        SELECT s.id, s.admission_no, s.first_name, s.last_name, s.guardian_name, s.guardian_phone, c.class_name, c.section,
               d.father_name, d.mother_name, d.guardian_relationship, d.father_mobile, d.student_mobile, d.current_address
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        WHERE s.status = 'Active'
    ";
    $params = [];

    if ($search_student) {
        $sql .= " AND (s.first_name LIKE :student OR s.last_name LIKE :student OR s.admission_no = :student_raw)";
        $params['student'] = '%' . $search_student . '%';
        $params['student_raw'] = $search_student;
    }
    if ($search_family) {
        $sql .= " AND (d.father_name LIKE :family OR s.guardian_name LIKE :family OR d.mother_name LIKE :family)";
        $params['family'] = '%' . $search_family . '%';
    }
    if ($search_phone) {
        $sql .= " AND (s.guardian_phone = :phone OR d.father_mobile = :phone OR d.student_mobile = :phone)";
        $params['phone'] = $search_phone;
    }
    if ($search_class > 0) {
        $sql .= " AND s.class_id = :class_id";
        $params['class_id'] = $search_class;
    }

    $sql .= " ORDER BY s.first_name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Family Phone List query error: " . $e->getMessage());
}
?>

<!-- Title Header -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-phone-volume me-2 text-primary"></i>Family Wise Phone List</h3>
    </div>
</div>

<!-- Filters Panel -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Family Contacts</h6>
    <form method="GET" action="family_phone_list.php" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Student Name</label>
            <input type="text" class="form-control form-control-sm" name="search_student" value="<?php echo $search_student; ?>" placeholder="Name or Admission No...">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Family (Father/Guardian)</label>
            <input type="text" class="form-control form-control-sm" name="search_family" value="<?php echo $search_family; ?>" placeholder="Father or Guardian Name...">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Phone / Mobile No</label>
            <input type="text" class="form-control form-control-sm" name="search_phone" value="<?php echo $search_phone; ?>" placeholder="Contact phone number...">
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
            <button type="submit" class="btn btn-sm btn-primary w-100 py-2">Search</button>
        </div>
    </form>
</div>

<!-- Family Phone Table Card -->
<div class="card border border-light shadow-sm bg-white p-4" style="border-radius:12px;">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4 d-print-none">
        <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-address-book text-primary me-2"></i>Family Contact Directory</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print List</button>
            <button class="btn btn-sm btn-outline-danger px-3" onclick="downloadPDF()"><i class="fa-solid fa-file-pdf me-2"></i>Export PDF</button>
            <button class="btn btn-sm btn-outline-success px-3" onclick="exportExcel()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle" id="familyTable">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Father</th>
                    <th>Mother</th>
                    <th>Guardian</th>
                    <th>Relation</th>
                    <th>Phone (Father/Guardian)</th>
                    <th>Mobile (Student)</th>
                    <th>Address</th>
                    <th>Class</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="9" class="text-center py-4 text-muted">No family contacts matched your filters.</td></tr>
                <?php else: foreach ($records as $r): ?>
                    <tr>
                        <td class="fw-bold text-dark">
                            <?php echo sanitize($r['first_name'] . ' ' . $r['last_name']); ?>
                            <br><small class="text-muted"><?php echo sanitize($r['admission_no']); ?></small>
                        </td>
                        <td><?php echo sanitize($r['father_name'] ?: '-'); ?></td>
                        <td><?php echo sanitize($r['mother_name'] ?: '-'); ?></td>
                        <td><?php echo sanitize($r['guardian_name'] ?: '-'); ?></td>
                        <td><span class="badge bg-primary-soft rounded-pill"><?php echo sanitize($r['guardian_relationship'] ?: 'Father'); ?></span></td>
                        <td><strong><?php echo sanitize($r['father_mobile'] ?: $r['guardian_phone']); ?></strong></td>
                        <td><?php echo sanitize($r['student_mobile'] ?: '-'); ?></td>
                        <td class="small text-muted" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?php echo sanitize($r['current_address'] ?: '-'); ?>
                        </td>
                        <td><?php echo sanitize($r['class_name'] . ' - ' . $r['section']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function downloadPDF() {
    alert("Reformatting contact directory for PDF print. Please select PDF printer in dialogue.");
    window.print();
}

// Client-side CSV/Excel exporter
function exportExcel() {
    let csv = "Student Name,Admission No,Father Name,Mother Name,Guardian Name,Relationship,Phone,Mobile,Address,Class\n";
    const rows = document.querySelectorAll("#familyTable tbody tr");
    
    rows.forEach(tr => {
        const cols = tr.querySelectorAll("td");
        if(cols.length === 9) {
            let rowData = [];
            // Parse columns
            let rawStud = cols[0].innerHTML.split("<br>");
            let student = rawStud[0] ? rawStud[0].trim() : "";
            let adm = rawStud[1] ? rawStud[1].replace(/<[^>]*>/g, "").trim() : "";
            let father = cols[1].textContent.trim();
            let mother = cols[2].textContent.trim();
            let guardian = cols[3].textContent.trim();
            let rel = cols[4].textContent.trim();
            let phone = cols[5].textContent.trim();
            let mob = cols[6].textContent.trim();
            let addr = cols[7].textContent.replace(/"/g, '""').replace(/,/g, ' ').trim();
            let cls = cols[8].textContent.trim();
            
            rowData.push('"' + student + '"');
            rowData.push('"' + adm + '"');
            rowData.push('"' + father + '"');
            rowData.push('"' + mother + '"');
            rowData.push('"' + guardian + '"');
            rowData.push('"' + rel + '"');
            rowData.push('"' + phone + '"');
            rowData.push('"' + mob + '"');
            rowData.push('"' + addr + '"');
            rowData.push('"' + cls + '"');
            
            csv += rowData.join(",") + "\n";
        }
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "family_phone_directory.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
