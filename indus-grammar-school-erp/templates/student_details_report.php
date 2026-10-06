<?php
/**
 * Indus Grammar School ERP - Printable Student Details Report Template
 * Exact Replica of Student Details Report with Per-Class Fee & Discount Breakdown
 * Version 5.0.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/helpers.php';

AuthMiddleware::requireLogin();

$db = Database::getConnection();

// Filter parameters
$search_class = (int)($_GET['class_id'] ?? ($_GET['search_class'] ?? 0));
$search_section = sanitize($_GET['section'] ?? ($_GET['search_section'] ?? ''));
$search_status = sanitize($_GET['status'] ?? 'Active');
$search_academic_type = sanitize($_GET['academic_type'] ?? '');

// Fetch all classes for the selector dropdown
$classes = SchoolClass::all();

$where = " WHERE 1=1";
$params = [];

if ($search_class > 0) {
    $where .= " AND s.class_id = :class_id";
    $params['class_id'] = $search_class;
}

if ($search_section !== '') {
    $where .= " AND (c.section = :sec1 OR s.school_section = :sec2)";
    $params['sec1'] = $search_section;
    $params['sec2'] = $search_section;
}

if ($search_status !== '' && $search_status !== 'All') {
    $where .= " AND s.status = :status";
    $params['status'] = $search_status;
}

if ($search_academic_type !== '') {
    $where .= " AND s.academic_type = :academic_type";
    $params['academic_type'] = $search_academic_type;
}

$students = [];
$selectedClassInfo = null;

try {
    if ($search_class > 0) {
        $stmtCls = $db->prepare("SELECT * FROM classes WHERE id = ?");
        $stmtCls->execute([$search_class]);
        $selectedClassInfo = $stmtCls->fetch(PDO::FETCH_ASSOC);
    }

    $sql = "
        SELECT 
            s.id as std_id,
            s.admission_no,
            s.first_name,
            s.last_name,
            s.gender,
            s.address,
            s.status,
            d.tuition_fee as std_tuition_fee,
            c.id as class_id,
            COALESCE(c.class_name, d.school_class, 'Unassigned') as class_name,
            COALESCE(c.section, d.school_section, 'Red') as section,
            d.father_name,
            d.father_mobile,
            d.student_mobile,
            d.current_address,
            s.id as family_group,
            d.fee_monthly,
            d.fee_discount,
            d.tuition_fee as net_tuition_fee
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN student_registration_details d ON s.id = d.student_id
        $where
        ORDER BY c.class_name ASC, c.section ASC, s.first_name ASC, s.last_name ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error generating Student Details Report: " . $e->getMessage());
}

// Calculations
$totalStudents = count($students);
$sumStdFee = 0;
$sumDiscount = 0;
$sumAcademyFee = 0;
$sumNetFee = 0;

foreach ($students as $st) {
    $monthly = (float)($st['fee_monthly'] ?? 2500);
    $discount = (float)($st['fee_discount'] ?? 0);
    $academy = (float)($st['fee_scholarship'] ?? 0); // or academy fee if applicable
    $net = (float)($st['net_tuition_fee'] ?? ($st['std_tuition_fee'] ?? ($monthly - $discount)));

    $sumStdFee += $monthly;
    $sumDiscount += $discount;
    $sumAcademyFee += $academy;
    $sumNetFee += $net;
}

// Formatting class display header
$classNameDisplay = "ALL CLASSES";
if ($selectedClassInfo) {
    $classNameDisplay = strtoupper(str_replace(' ', '-', $selectedClassInfo['class_name']));
} elseif (!empty($students)) {
    $classNameDisplay = strtoupper(str_replace(' ', '-', $students[0]['class_name']));
}

$sectionNameDisplay = "";
if (!empty($search_section)) {
    $sectionNameDisplay = strtoupper($search_section);
} elseif ($selectedClassInfo) {
    $sectionNameDisplay = strtoupper($selectedClassInfo['section']);
} elseif (!empty($students)) {
    $sectionNameDisplay = strtoupper($students[0]['section']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Details Report - Indus Grammar School</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Arial:wght@400;700&display=swap');

        @page {
            size: A4 landscape;
            margin: 8mm 10mm;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8fafc;
            color: #000000;
            font-size: 11px;
            margin: 0;
            padding: 15px;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Control bar (hidden when printing) */
        .no-print-toolbar {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        /* Report Paper */
        .report-container {
            background: #ffffff;
            padding: 15px 20px;
            border: 1px solid #e2e8f0;
            margin: 0 auto;
        }

        .report-title-main {
            font-size: 22px;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .report-meta-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .class-heading-banner {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        /* Exact Replica Report Table */
        .table-details-report {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        .table-details-report th, 
        .table-details-report td {
            border: 1px solid #bce2f5;
            padding: 5px 6px;
            vertical-align: middle;
            font-family: 'Arial', sans-serif;
        }

        .table-details-report th {
            background-color: #d0e7f7 !important;
            color: #000000;
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
        }

        .table-details-report td {
            background-color: #ffffff;
            color: #000000;
        }

        .table-details-report tr:nth-child(even) td {
            background-color: #f9fbfd;
        }

        .text-center-col { text-align: center; }
        .text-right-col { text-align: right; }
        .text-left-col { text-align: left; }

        .row-totals td {
            font-weight: 700;
            background-color: #ffffff !important;
            border-top: 2px solid #000000;
        }

        .total-label-cell {
            text-align: right;
            font-weight: 700;
            padding-right: 15px;
        }

        .total-count-footer {
            font-size: 14px;
            font-weight: 700;
            text-align: center;
            margin-top: 15px;
        }

        @media print {
            body {
                background-color: #ffffff !important;
                padding: 0 !important;
            }
            .no-print-toolbar, .no-print {
                display: none !important;
            }
            .report-container {
                border: none !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Control Toolbar (No Print) -->
    <div class="no-print-toolbar no-print">
        <form method="GET" action="student_details_report.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <label class="form-label small fw-bold mb-1"><i class="fa-solid fa-graduation-cap text-primary me-1"></i>Select Class & Section</label>
                <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">-- All Classes --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $search_class === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="Active" <?php echo $search_status === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="All" <?php echo $search_status === 'All' ? 'selected' : ''; ?>>All</option>
                    <option value="Inactive" <?php echo $search_status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <div class="col-md-6 text-end">
                <button type="button" class="btn btn-sm btn-primary fw-bold px-3 me-1" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Print Report
                </button>
                <button type="button" class="btn btn-sm btn-success fw-bold px-3 me-1" onclick="exportExcel()">
                    <i class="fa-solid fa-file-excel me-1"></i> Export Excel
                </button>
                <a href="../modules/reports/students.php" class="btn btn-sm btn-outline-secondary px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Panel
                </a>
            </div>
        </form>
    </div>

    <!-- Report Paper Document -->
    <div class="report-container">
        
        <!-- Document Title -->
        <div class="report-title-main">Student Details Report</div>

        <!-- Meta Header -->
        <div class="report-meta-header">
            <div></div>
            <div><?php echo strtoupper($search_status === 'All' ? 'ALL' : 'ACIVE'); ?></div>
            <div><?php echo date('d-m-Y H:i:s'); ?></div>
        </div>

        <!-- Class Banner -->
        <div class="class-heading-banner"><?php echo htmlspecialchars($classNameDisplay); ?></div>

        <!-- Details Data Table -->
        <table class="table-details-report" id="reportTable">
            <thead>
                <tr>
                    <th width="60">Section</th>
                    <th width="40">Sr.#</th>
                    <th width="60">Std Id</th>
                    <th width="160">Student Name</th>
                    <th width="65">Family Group</th>
                    <th>Std Current Add</th>
                    <th width="100">Father Mobile</th>
                    <th width="160">Father Name</th>
                    <th width="70">Std Fee</th>
                    <th width="70">Discount</th>
                    <th width="80">Academy Fee</th>
                    <th width="70">Net Fee</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="12" class="text-center-col py-4">No student records found for the selected class.</td>
                    </tr>
                <?php else: 
                    $sr = 0;
                    foreach ($students as $st): 
                        $sr++;
                        $secName = strtoupper(trim($st['section']));
                        $stdName = strtoupper(trim($st['first_name'] . ' ' . $st['last_name']));
                        $fatherName = strtoupper(trim($st['father_name'] ?: ''));
                        $address = strtoupper(trim($st['current_address'] ?: ($st['address'] ?: '')));
                        $familyGrp = trim($st['family_group'] ?: '');
                        $fatherMob = trim($st['father_mobile'] ?: '');

                        $monthly = (float)($st['fee_monthly'] ?? 2500);
                        $discount = (float)($st['fee_discount'] ?? 0);
                        $academy = (float)($st['fee_scholarship'] ?? 0);
                        $net = (float)($st['net_tuition_fee'] ?? ($st['std_tuition_fee'] ?? ($monthly - $discount)));
                ?>
                    <tr>
                        <td class="text-center-col"><?php echo htmlspecialchars($secName); ?></td>
                        <td class="text-center-col"><?php echo $sr; ?></td>
                        <td class="text-center-col"><?php echo $st['std_id']; ?></td>
                        <td class="text-left-col"><?php echo htmlspecialchars($stdName); ?></td>
                        <td class="text-center-col"><?php echo htmlspecialchars($familyGrp); ?></td>
                        <td class="text-left-col"><?php echo htmlspecialchars($address); ?></td>
                        <td class="text-left-col"><?php echo htmlspecialchars($fatherMob); ?></td>
                        <td class="text-left-col"><?php echo htmlspecialchars($fatherName); ?></td>
                        <td class="text-right-col"><?php echo number_format($monthly); ?></td>
                        <td class="text-right-col"><?php echo number_format($discount); ?></td>
                        <td class="text-right-col"><?php echo number_format($academy); ?></td>
                        <td class="text-right-col"><?php echo number_format($net); ?></td>
                    </tr>
                <?php endforeach; endif; ?>

                <!-- Totals Footer Rows -->
                <tr class="row-totals">
                    <td colspan="8" class="total-label-cell">Class Total:</td>
                    <td class="text-right-col"><?php echo number_format($sumStdFee); ?></td>
                    <td class="text-right-col"><?php echo number_format($sumDiscount); ?></td>
                    <td class="text-right-col"><?php echo number_format($sumAcademyFee); ?></td>
                    <td class="text-right-col"><?php echo number_format($sumNetFee); ?></td>
                </tr>
                <tr class="row-totals">
                    <td colspan="8" class="total-label-cell">Grand Total:</td>
                    <td class="text-right-col"><?php echo number_format($sumStdFee); ?></td>
                    <td class="text-right-col"><?php echo number_format($sumDiscount); ?></td>
                    <td class="text-right-col"><?php echo number_format($sumAcademyFee); ?></td>
                    <td class="text-right-col"><?php echo number_format($sumNetFee); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Student Count Banner -->
        <div class="total-count-footer">
            Total Student : <?php echo $totalStudents; ?>
        </div>

    </div>

    <script>
        function exportExcel() {
            let table = document.getElementById("reportTable");
            if (!table) return;
            let html = table.outerHTML;
            let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
            let link = document.createElement("a");
            link.download = "Student_Details_Report_<?php echo sanitize($classNameDisplay); ?>.xls";
            link.href = url;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
