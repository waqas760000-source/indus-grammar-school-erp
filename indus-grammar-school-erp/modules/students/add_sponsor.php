<?php
/**
 * Indus Grammar School ERP - Sponsor Management
 * Version 1.0.0
 */

$pageTitle = 'Add Sponsor';
$breadcrumbActive = 'Student Registration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

$message = '';
$error = '';

// Load active students for dropdown selection
$studentsList = [];
try {
    $studentsList = $db->query("SELECT id, admission_no, first_name, last_name FROM students WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll();
} catch (Exception $e) {}

// Handle POST CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        try {
            $sponsor_name = sanitize($_POST['sponsor_name'] ?? '');
            $organization = sanitize($_POST['organization'] ?? '');
            $contact_person = sanitize($_POST['contact_person'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $address = sanitize($_POST['address'] ?? '');
            $student_id = (int)($_POST['student_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0.00);
            $duration = sanitize($_POST['duration'] ?? '');
            $remarks = sanitize($_POST['remarks'] ?? '');

            if (!$sponsor_name || !$phone || !$student_id || $amount <= 0) {
                throw new Exception("Please specify Sponsor Name, Phone, Sponsored Student, and Sponsor Amount.");
            }

            $stmt = $db->prepare("
                INSERT INTO student_sponsors (sponsor_name, organization, contact_person, phone, email, address, student_id, amount, duration, remarks)
                VALUES (:name, :org, :cp, :phone, :email, :addr, :sid, :amt, :dur, :rem)
            ");
            $stmt->execute([
                'name' => $sponsor_name,
                'org' => $organization,
                'cp' => $contact_person,
                'phone' => $phone,
                'email' => $email,
                'addr' => $address,
                'sid' => $student_id,
                'amt' => $amount,
                'dur' => $duration,
                'rem' => $remarks
            ]);

            // Audit log
            $stmtLog = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$_SESSION['user_id'] ?? null, 'Sponsor Created', "Sponsor: $sponsor_name for student ID $student_id", $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

            $message = "Sponsor record registered successfully!";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    if ($action === 'update') {
        try {
            $sponsor_id = (int)$_POST['sponsor_id'];
            $sponsor_name = sanitize($_POST['sponsor_name'] ?? '');
            $organization = sanitize($_POST['organization'] ?? '');
            $contact_person = sanitize($_POST['contact_person'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $address = sanitize($_POST['address'] ?? '');
            $student_id = (int)($_POST['student_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0.00);
            $duration = sanitize($_POST['duration'] ?? '');
            $remarks = sanitize($_POST['remarks'] ?? '');

            if (!$sponsor_id || !$sponsor_name || !$phone || !$student_id || $amount <= 0) {
                throw new Exception("Please specify Sponsor Name, Phone, Sponsored Student, and Sponsor Amount.");
            }

            $stmt = $db->prepare("
                UPDATE student_sponsors SET 
                    sponsor_name = :name, organization = :org, contact_person = :cp, phone = :phone,
                    email = :email, address = :addr, student_id = :sid, amount = :amt, duration = :dur, remarks = :rem
                WHERE id = :id
            ");
            $stmt->execute([
                'name' => $sponsor_name,
                'org' => $organization,
                'cp' => $contact_person,
                'phone' => $phone,
                'email' => $email,
                'addr' => $address,
                'sid' => $student_id,
                'amt' => $amount,
                'dur' => $duration,
                'rem' => $remarks,
                'id' => $sponsor_id
            ]);

            $message = "Sponsor record updated successfully!";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    if ($action === 'delete') {
        try {
            $sponsor_id = (int)$_POST['sponsor_id'];
            if ($sponsor_id > 0) {
                $stmt = $db->prepare("DELETE FROM student_sponsors WHERE id = ?");
                $stmt->execute([$sponsor_id]);
                $message = "Sponsor record deleted successfully.";
            }
        } catch (Exception $e) {
            $error = "Error deleting sponsor: " . $e->getMessage();
        }
    }
}

// Handle Searches
$search_name = sanitize($_GET['search_name'] ?? '');
$search_org = sanitize($_GET['search_org'] ?? '');
$search_student = (int)($_GET['search_student'] ?? 0);

// Fetch sponsors & Stats
$sponsors = [];
$totalSponsorsCount = 0;
$totalSponsoredStudents = 0;
$totalSponsoredAmount = 0.00;

try {
    // Stats calculation
    $totalSponsorsCount = (int)$db->query("SELECT COUNT(DISTINCT sponsor_name) FROM student_sponsors")->fetchColumn();
    $totalSponsoredStudents = (int)$db->query("SELECT COUNT(DISTINCT student_id) FROM student_sponsors")->fetchColumn();
    $totalSponsoredAmount = (float)$db->query("SELECT SUM(amount) FROM student_sponsors")->fetchColumn();

    // Query roster
    $sql = "
        SELECT sp.*, s.first_name, s.last_name, s.admission_no, c.class_name, c.section
        FROM student_sponsors sp
        JOIN students s ON sp.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE 1=1
    ";
    $params = [];

    if ($search_name) {
        $sql .= " AND sp.sponsor_name LIKE :name";
        $params['name'] = '%' . $search_name . '%';
    }
    if ($search_org) {
        $sql .= " AND sp.organization LIKE :org";
        $params['org'] = '%' . $search_org . '%';
    }
    if ($search_student > 0) {
        $sql .= " AND sp.student_id = :student_id";
        $params['student_id'] = $search_student;
    }

    $sql .= " ORDER BY sp.sponsor_name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $sponsors = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Student sponsors query error: " . $e->getMessage());
}
?>

<!-- Title & Action Toolbar -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-handshake-angle me-2 text-primary"></i>Add Sponsor</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary animate-hover" data-bs-toggle="modal" data-bs-target="#addSponsorModal"><i class="fa-solid fa-plus me-2"></i>Add Sponsor Record</button>
        <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print</button>
        <button class="btn btn-outline-success ms-1" onclick="exportSponsors()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
    </div>
</div>

<!-- Output Messages -->
<?php if ($message): ?>
    <div class="alert alert-success border-0 shadow-sm mb-4 d-print-none"><i class="fa-solid fa-circle-check me-2"></i><?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4 d-print-none"><i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $error; ?></div>
<?php endif; ?>

<!-- Summary Report Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border border-light shadow-sm bg-white p-4 text-center" style="border-radius:12px;">
            <span class="text-muted small fw-semibold">Total Unique Sponsors</span>
            <h2 class="fw-bold text-dark mb-0 mt-2"><?php echo $totalSponsorsCount; ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border border-light shadow-sm bg-white p-4 text-center" style="border-radius:12px;">
            <span class="text-muted small fw-semibold">Sponsored Students</span>
            <h2 class="fw-bold text-primary mb-0 mt-2"><?php echo $totalSponsoredStudents; ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border border-light shadow-sm bg-white p-4 text-center" style="border-radius:12px;">
            <span class="text-muted small fw-semibold">Total Sponsorship Amount</span>
            <h2 class="fw-bold text-success mb-0 mt-2">Rs. <?php echo number_format($totalSponsoredAmount, 2); ?></h2>
        </div>
    </div>
</div>

<!-- Search Panel -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Search Sponsor Records</h6>
    <form method="GET" action="add_sponsor.php" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-semibold text-muted">Sponsor Name</label>
            <input type="text" class="form-control form-control-sm" name="search_name" value="<?php echo $search_name; ?>" placeholder="e.g. NGO, Trust, Person...">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold text-muted">Organization</label>
            <input type="text" class="form-control form-control-sm" name="search_org" value="<?php echo $search_org; ?>" placeholder="Organization...">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold text-muted">Sponsored Student</label>
            <select class="form-select form-select-sm" name="search_student">
                <option value="">All Students</option>
                <?php foreach ($studentsList as $st): ?>
                    <option value="<?php echo $st['id']; ?>" <?php echo ($search_student == $st['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['admission_no'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-sm btn-secondary w-100 py-2">Filter</button>
        </div>
    </form>
</div>

<!-- Sponsor list Table -->
<div class="card border border-light shadow-sm bg-white p-4" style="border-radius:12px;">
    <h5 class="fw-bold text-secondary mb-3">Sponsors Registry</h5>
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle" id="sponsorsTable">
            <thead>
                <tr>
                    <th>Sponsor Name</th>
                    <th>Organization</th>
                    <th>Contact Info</th>
                    <th>Sponsored Student</th>
                    <th>Class</th>
                    <th>Amount (Rs.)</th>
                    <th>Duration</th>
                    <th>Remarks</th>
                    <th class="text-end d-print-none">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sponsors)): ?>
                    <tr><td colspan="9" class="text-center py-4 text-muted">No sponsor records found matching your filters.</td></tr>
                <?php else: foreach ($sponsors as $sp): ?>
                    <tr>
                        <td class="fw-bold text-dark"><?php echo sanitize($sp['sponsor_name']); ?></td>
                        <td><?php echo sanitize($sp['organization'] ?: '-'); ?></td>
                        <td>
                            <div class="small fw-semibold"><?php echo sanitize($sp['contact_person'] ?: 'N/A'); ?></div>
                            <small class="text-muted"><?php echo sanitize($sp['phone']); ?> | <?php echo sanitize($sp['email']); ?></small>
                        </td>
                        <td class="fw-bold">
                            <?php echo sanitize($sp['first_name'] . ' ' . $sp['last_name']); ?>
                            <br><small class="text-muted"><?php echo sanitize($sp['admission_no']); ?></small>
                        </td>
                        <td><?php echo sanitize($sp['class_name'] . ' - ' . $sp['section']); ?></td>
                        <td class="fw-bold text-success">Rs. <?php echo number_format($sp['amount'], 2); ?></td>
                        <td><?php echo sanitize($sp['duration'] ?: '-'); ?></td>
                        <td class="small text-muted"><?php echo sanitize($sp['remarks'] ?: '-'); ?></td>
                        <td class="text-end d-print-none">
                            <button class="btn btn-sm btn-outline-primary" onclick='editSponsor(<?php echo json_encode($sp, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-edit"></i></button>
                            <button class="btn btn-sm btn-outline-danger ms-1" onclick="confirmDelete(<?php echo $sp['id']; ?>)"><i class="fa-solid fa-trash-can"></i></button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Sponsor Modal -->
<div class="modal fade d-print-none" id="addSponsorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-handshake-angle me-2 text-primary"></i>Register Sponsor Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body px-4">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Sponsor Name *</label>
                            <input type="text" class="form-control" name="sponsor_name" placeholder="Sponsor individual/organization name..." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Organization</label>
                            <input type="text" class="form-control" name="organization" placeholder="NGO, Trust, Business name...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Contact Person</label>
                            <input type="text" class="form-control" name="contact_person" placeholder="Representative name...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Phone *</label>
                            <input type="tel" class="form-control" name="phone" placeholder="Contact number..." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Email</label>
                            <input type="email" class="form-control" name="email" placeholder="Email address...">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Sponsored Student *</label>
                            <select class="form-select" name="student_id" required>
                                <option value="">— Select Registered Student —</option>
                                <?php foreach ($studentsList as $st): ?>
                                    <option value="<?php echo $st['id']; ?>"><?php echo sanitize($st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['admission_no'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Sponsorship Amount (Rs.) *</label>
                            <input type="number" class="form-control" name="amount" min="1" placeholder="e.g. 5000" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Duration (e.g. 1 Year)</label>
                            <input type="text" class="form-control" name="duration" placeholder="e.g. 1 Year, Monthly...">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Address</label>
                            <textarea class="form-control" name="address" rows="2" placeholder="Sponsor street address..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2" placeholder="Special terms..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Register Sponsor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Sponsor Modal -->
<div class="modal fade d-print-none" id="editSponsorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-edit me-2 text-primary"></i>Modify Sponsor Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body px-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="sponsor_id" id="edit_sp_id">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Sponsor Name *</label>
                            <input type="text" class="form-control" name="sponsor_name" id="edit_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Organization</label>
                            <input type="text" class="form-control" name="organization" id="edit_org">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Contact Person</label>
                            <input type="text" class="form-control" name="contact_person" id="edit_cp">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Phone *</label>
                            <input type="tel" class="form-control" name="phone" id="edit_phone" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Sponsored Student *</label>
                            <select class="form-select" name="student_id" id="edit_student_id" required>
                                <?php foreach ($studentsList as $st): ?>
                                    <option value="<?php echo $st['id']; ?>"><?php echo sanitize($st['first_name'] . ' ' . $st['last_name'] . ' (' . $st['admission_no'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Sponsorship Amount (Rs.) *</label>
                            <input type="number" class="form-control" name="amount" id="edit_amount" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Duration</label>
                            <input type="text" class="form-control" name="duration" id="edit_duration">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Address</label>
                            <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Remarks</label>
                            <textarea class="form-control" name="remarks" id="edit_remarks" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="sponsor_id" id="del_sp_id">
</form>

<script>
function editSponsor(item) {
    document.getElementById("edit_sp_id").value = item.id;
    document.getElementById("edit_name").value = item.sponsor_name;
    document.getElementById("edit_org").value = item.organization || "";
    document.getElementById("edit_cp").value = item.contact_person || "";
    document.getElementById("edit_phone").value = item.phone;
    document.getElementById("edit_email").value = item.email || "";
    document.getElementById("edit_student_id").value = item.student_id;
    document.getElementById("edit_amount").value = item.amount;
    document.getElementById("edit_duration").value = item.duration || "";
    document.getElementById("edit_address").value = item.address || "";
    document.getElementById("edit_remarks").value = item.remarks || "";
    
    new bootstrap.Modal(document.getElementById("editSponsorModal")).show();
}

function confirmDelete(id) {
    if (confirm("Are you sure you want to delete this sponsor record permanently?")) {
        document.getElementById("del_sp_id").value = id;
        document.getElementById("deleteForm").submit();
    }
}

// Client-side CSV Exporter
function exportSponsors() {
    let csv = "Sponsor Name,Organization,Contact Person,Phone,Email,Sponsored Student,Class,Amount,Duration,Remarks\n";
    const rows = document.querySelectorAll("#sponsorsTable tbody tr");
    
    rows.forEach(tr => {
        const cols = tr.querySelectorAll("td");
        if(cols.length === 9) {
            let rowData = [];
            // Parse columns
            let name = cols[0].textContent.trim();
            let org = cols[1].textContent.trim();
            let rawContact = cols[2].innerHTML.split("<br>");
            let cp = rawContact[0] ? rawContact[0].replace(/<[^>]*>/g, "").trim() : "";
            let contact = rawContact[1] ? rawContact[1].replace(/<[^>]*>/g, "").trim() : "";
            let rawStud = cols[3].innerHTML.split("<br>");
            let student = rawStud[0] ? rawStud[0].trim() : "";
            let adm = rawStud[1] ? rawStud[1].replace(/<[^>]*>/g, "").trim() : "";
            let cls = cols[4].textContent.trim();
            let amt = cols[5].textContent.replace(/Rs. /g, "").replace(/,/g, "").trim();
            let dur = cols[6].textContent.trim();
            let rem = cols[7].textContent.trim();
            
            rowData.push('"' + name + '"');
            rowData.push('"' + org + '"');
            rowData.push('"' + cp + '"');
            rowData.push('"' + contact + '"');
            rowData.push('"' + student + ' (' + adm + ')"');
            rowData.push('"' + cls + '"');
            rowData.push('"' + amt + '"');
            rowData.push('"' + dur + '"');
            rowData.push('"' + rem.replace(/"/g, '""') + '"');
            
            csv += rowData.join(",") + "\n";
        }
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "student_sponsorships_roster.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
