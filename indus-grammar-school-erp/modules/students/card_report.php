<?php
/**
 * Indus Grammar School ERP - Student ID Card Generator
 * Version 1.0.0
 */

$pageTitle = 'Student Card Report';
$breadcrumbActive = 'Student Registration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('student_view');

$db = Database::getConnection();

// Load classes and students
$classes = [];
$students = [];
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

try {
    $classes = $db->query("SELECT * FROM classes ORDER BY class_name ASC")->fetchAll();
    
    // Query matching students with registered details
    $sql = "
        SELECT s.id, s.admission_no, s.first_name, s.last_name, s.gender, s.class_id, s.academic_type, s.school_class, s.school_section, s.academy_program, s.academy_batch, c.class_name, c.section,
               d.roll_no, d.blood_group, d.emergency_contact, d.academic_session, d.doc_student_photo
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        WHERE s.status = 'Active'
    ";
    $params = [];
    
    if ($selectedClassId > 0) {
        $sql .= " AND s.class_id = :class_id";
        $params['class_id'] = $selectedClassId;
    }
    if ($selectedStudentId > 0) {
        $sql .= " AND s.id = :student_id";
        $params['student_id'] = $selectedStudentId;
    }
    
    $sql .= " ORDER BY s.first_name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Load ID Cards error: " . $e->getMessage());
}
?>

<!-- Custom CSS style block for PVC ID Card formatting -->
<style>
.id-card-container {
    width: 320px;
    height: 480px;
    border: 1px solid #ddd;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    background-color: #ffffff;
    position: relative;
    overflow: hidden;
    font-family: 'Outfit', sans-serif;
    display: inline-block;
    margin: 15px;
    vertical-align: top;
    text-align: left;
}

.id-card-header {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
    color: #ffffff;
    padding: 15px;
    text-align: center;
    border-bottom: 4px solid #fbbf24;
}

.id-card-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 0.95rem;
    letter-spacing: 0.5px;
}

.id-card-header small {
    font-size: 0.65rem;
    opacity: 0.9;
    text-transform: uppercase;
}

.id-card-body {
    padding: 15px;
    text-align: center;
}

.id-card-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    border: 3px solid #2563eb;
    object-fit: cover;
    margin: 0 auto 12px auto;
    background-color: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
}

.id-card-name {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 2px;
}

.id-card-role {
    font-size: 0.75rem;
    font-weight: 600;
    color: #2563eb;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 12px;
}

.id-card-info-table {
    width: 100%;
    font-size: 0.75rem;
    text-align: left;
    margin-bottom: 12px;
}

.id-card-info-table td {
    padding: 3px 0;
}

.id-card-info-table td.label {
    color: #6b7280;
    font-weight: 500;
    width: 45%;
}

.id-card-info-table td.value {
    color: #111827;
    font-weight: 700;
}

.id-card-footer {
    border-top: 1px dashed #e5e7eb;
    padding-top: 10px;
    margin-top: 5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.barcode-placeholder {
    width: 130px;
    height: 30px;
    background: repeating-linear-gradient(90deg, #000, #000 2px, #fff 2px, #fff 6px);
}

.qrcode-box img {
    width: 45px;
    height: 45px;
}

/* Print CSS variables stylesheet rules */
@media print {
    body * {
        visibility: hidden;
    }
    #printZone, #printZone * {
        visibility: visible;
    }
    #printZone {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        text-align: center;
        background: none;
    }
    .id-card-container {
        border: 1px solid #000 !important;
        box-shadow: none !important;
        page-break-inside: avoid;
        margin: 10px;
    }
    .d-print-none {
        display: none !important;
    }
}
</style>

<!-- Header Toolbar -->
<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-id-card me-2 text-primary"></i>Student Card Report</h3>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary" onclick="printAllCards()"><i class="fa-solid fa-print me-2"></i>Print Selected Cards</button>
        <button class="btn btn-outline-danger ms-1" onclick="downloadPDF()"><i class="fa-solid fa-file-pdf me-2"></i>Export PDF</button>
    </div>
</div>

<!-- Search Panel -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Filter Cards</h6>
    <form method="GET" action="card_report.php" class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label small fw-semibold text-muted">Class & Section</label>
            <select class="form-select form-select-sm" name="class_id" onchange="this.form.submit()">
                <option value="">— Choose Class —</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($selectedClassId == $c['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label small fw-semibold text-muted">Student Name</label>
            <select class="form-select form-select-sm" name="student_id" onchange="this.form.submit()">
                <option value="">— Search Student —</option>
                <?php
                // Search list filtered by selected class if any
                $quickStudents = [];
                try {
                    $qSql = "SELECT id, first_name, last_name, admission_no FROM students WHERE status = 'Active'";
                    if ($selectedClassId > 0) $qSql .= " AND class_id = " . $selectedClassId;
                    $qSql .= " ORDER BY first_name ASC";
                    $quickStudents = $db->query($qSql)->fetchAll();
                } catch(Exception $e) {}
                
                foreach ($quickStudents as $qs): ?>
                    <option value="<?php echo $qs['id']; ?>" <?php echo ($selectedStudentId == $qs['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($qs['first_name'] . ' ' . $qs['last_name'] . ' (' . $qs['admission_no'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 text-end">
            <a href="card_report.php" class="btn btn-sm btn-outline-secondary w-100 py-2">Reset</a>
        </div>
    </form>
</div>

<!-- Card Selection List -->
<div class="card border border-light shadow-sm bg-white p-4 mb-4 d-print-none" style="border-radius:12px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-list me-2"></i>Select Students for Generation (<?php echo count($students); ?> found)</h6>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)">
            <label class="form-check-label small fw-semibold text-muted" for="selectAllCheckbox">Select All</label>
        </div>
    </div>
    
    <div class="row g-2">
        <?php foreach ($students as $row): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="p-2 border rounded bg-light">
                    <div class="form-check">
                        <input class="form-check-input student-card-checkbox" type="checkbox" value="<?php echo $row['id']; ?>" id="chk_<?php echo $row['id']; ?>" checked>
                        <label class="form-check-label small text-dark text-truncate d-inline-block" style="max-width: 90%;" for="chk_<?php echo $row['id']; ?>">
                            <strong><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></strong>
                            <br><span class="text-muted small" style="font-size:0.7rem;"><?php echo sanitize($row['admission_no']); ?></span>
                        </label>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ID Card Rendering Area -->
<div class="text-center bg-light border p-4 shadow-inner" style="border-radius: 12px; min-height: 400px;">
    <div id="printZone">
        <?php if (empty($students)): ?>
            <div class="text-center py-5 text-muted d-print-none">
                <i class="fa-solid fa-id-card fs-1 opacity-50 mb-3 d-block"></i>
                <h5>Choose a Class or Student in search panel to render ID cards.</h5>
            </div>
        <?php else: foreach ($students as $row): 
            $qrData = urlencode(APP_URL . "/modules/students/detail_report.php?view_id=" . $row['id']);
            $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . $qrData;
        ?>
            <!-- ID Card Frame -->
            <div class="id-card-container student-card-preview" id="card_frame_<?php echo $row['id']; ?>">
                <div class="id-card-header">
                    <i class="fa-solid fa-graduation-cap fs-4 text-warning mb-1"></i>
                    <h5>INDUS GRAMMAR SCHOOL</h5>
                    <small>Student Identity Card</small>
                </div>
                <div class="id-card-body">
                    <!-- Photo -->
                    <div class="id-card-avatar">
                        <?php if (!empty($row['doc_student_photo'])): ?>
                            <img src="<?php echo APP_URL . '/' . $row['doc_student_photo']; ?>" class="rounded-circle" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <i class="fa-solid fa-user fs-1 text-muted"></i>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Identity -->
                    <div class="id-card-name"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></div>
                    <div class="id-card-role">Student</div>
                    
                    <!-- Technical Fields -->
                    <table class="id-card-info-table">
                        <tr><td class="label">Admission No:</td><td class="value"><?php echo sanitize($row['admission_no']); ?></td></tr>
                        <tr><td class="label">Roll Number:</td><td class="value"><?php echo sanitize($row['roll_no'] ?: '-'); ?></td></tr>
                        <?php if (($row['academic_type'] ?? 'School') !== 'Academy'): ?>
                            <tr><td class="label">Class & Sec:</td><td class="value"><?php echo sanitize(($row['class_name'] ?? $row['school_class'] ?? '-') . ' - ' . ($row['section'] ?? $row['school_section'] ?? 'A')); ?></td></tr>
                        <?php endif; ?>
                        <?php if (($row['academic_type'] ?? 'School') !== 'School'): ?>
                            <tr><td class="label">Program:</td><td class="value"><?php echo sanitize($row['academy_program'] ?? '-'); ?></td></tr>
                            <tr><td class="label">Batch:</td><td class="value"><?php echo sanitize($row['academy_batch'] ?? '-'); ?></td></tr>
                        <?php endif; ?>
                        <tr><td class="label">Blood Group:</td><td class="value"><?php echo sanitize($row['blood_group'] ?: '-'); ?></td></tr>
                        <tr><td class="label">Emergency:</td><td class="value"><?php echo sanitize($row['emergency_contact'] ?: '-'); ?></td></tr>
                        <tr><td class="label">Academic Session:</td><td class="value"><?php echo sanitize($row['academic_session'] ?: '-'); ?></td></tr>
                    </table>
                    
                    <!-- Footer graphics: QR and Barcode -->
                    <div class="id-card-footer">
                        <div class="barcode-placeholder" title="MOCK BARCODE"></div>
                        <div class="qrcode-box" title="SCAN PROFILE">
                            <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code">
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script>
// Toggle select all checkboxes
function toggleSelectAll(master) {
    document.querySelectorAll(".student-card-checkbox").forEach(chk => {
        chk.checked = master.checked;
        toggleCardFrame(chk.value, master.checked);
    });
}

// Bind manual checkbox triggers
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".student-card-checkbox").forEach(chk => {
        chk.addEventListener("change", function() {
            toggleCardFrame(this.value, this.checked);
        });
    });
});

function toggleCardFrame(id, visible) {
    const frame = document.getElementById("card_frame_" + id);
    if (frame) {
        frame.style.display = visible ? "inline-block" : "none";
    }
}

// Trigger browser print
function printAllCards() {
    window.print();
}

function downloadPDF() {
    alert("PVC ID Card templates formatted for PDF print layouts. Please select PDF printer in print dialogue.");
    window.print();
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
