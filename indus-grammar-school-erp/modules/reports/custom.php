<?php
/**
 * Indus Grammar School ERP - Dynamic Custom Reports Builder
 * Version 4.0.0
 */

$pageTitle = 'Custom Reports Builder';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Available modules configuration
$modules = [
    'students' => [
        'name' => 'Students Directory',
        'table' => 'students',
        'fields' => [
            'admission_no'   => 'Admission Number',
            'first_name'     => 'First Name',
            'last_name'      => 'Last Name',
            'gender'         => 'Gender',
            'date_of_birth'  => 'Date of Birth',
            'enrollment_date'=> 'Enrollment Date',
            'guardian_name'  => 'Guardian Name',
            'guardian_phone' => 'Guardian Phone',
            'status'         => 'Status',
            'academic_type'  => 'Academic Type'
        ],
        'date_field' => 'enrollment_date'
    ],
    'staff' => [
        'name' => 'Staff Directory',
        'table' => 'staff',
        'fields' => [
            'employee_no'   => 'Employee Number',
            'first_name'    => 'First Name',
            'last_name'     => 'Last Name',
            'department'    => 'Department',
            'designation'   => 'Designation',
            'phone'         => 'Phone Mobile',
            'email'         => 'Email Address',
            'joining_date'  => 'Joining Date',
            'salary'        => 'Base Salary',
            'status'        => 'Status'
        ],
        'date_field' => 'joining_date'
    ],
    'fee_payments' => [
        'name' => 'Fee Collection Payments',
        'table' => 'fee_payments',
        'fields' => [
            'payment_date'    => 'Receipt Date',
            'reference_number'=> 'Reference Number',
            'amount_paid'     => 'Amount Paid (Rs)',
            'payment_method'  => 'Payment Method',
            'remarks'         => 'Transaction Remarks'
        ],
        'date_field' => 'payment_date'
    ],
    'expenses' => [
        'name' => 'School Operation Expenses',
        'table' => 'expenses',
        'fields' => [
            'expense_date'   => 'Expense Date',
            'title'          => 'Expense Title',
            'vendor_supplier'=> 'Vendor Supplier',
            'invoice_number' => 'Invoice Number',
            'amount'         => 'Disbursed Amount (Rs)',
            'payment_method' => 'Payment Method',
            'remarks'        => 'Remarks'
        ],
        'date_field' => 'expense_date'
    ],
    'exam_results' => [
        'name' => 'Exam Consolidated Results',
        'table' => 'exam_results',
        'fields' => [
            'total_marks'   => 'Total Marks Limit',
            'obtained_marks'=> 'Obtained Marks',
            'percentage'    => 'Obtained Percentage (%)',
            'grade'         => 'Assigned Grade',
            'position'      => 'Merit Rank / Position',
            'status'        => 'Status (Pass/Fail)'
        ],
        'date_field' => 'created_at'
    ]
];

$selectedModule = sanitize($_GET['module'] ?? 'students');
$selectedCols   = $_GET['columns'] ?? [];
$selectedSort   = sanitize($_GET['sort_by'] ?? '');
$selectedOrder  = sanitize($_GET['sort_order'] ?? 'ASC');
$dateFrom       = sanitize($_GET['date_from'] ?? '');
$dateTo         = sanitize($_GET['date_to'] ?? '');

$reportTitle = "Custom Query Worksheet";
$reportData = [];
$actualColsToDisplay = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($selectedCols) && isset($modules[$selectedModule])) {
    $modConf = $modules[$selectedModule];
    
    // Whitelist and map columns
    $selectFields = [];
    foreach ($selectedCols as $col) {
        if (isset($modConf['fields'][$col])) {
            $selectFields[] = 't.' . $col;
            $actualColsToDisplay[$col] = $modConf['fields'][$col];
        }
    }

    if (!empty($selectFields)) {
        // Special joins for relation mapping (e.g. show student names on fee_payments or exam_results)
        $join = '';
        $extraSelect = '';
        
        if ($selectedModule === 'fee_payments') {
            $extraSelect = ", st.first_name as student_first, st.last_name as student_last, st.admission_no";
            $join = " JOIN students st ON t.student_id = st.id";
        } else if ($selectedModule === 'exam_results') {
            $extraSelect = ", st.first_name as student_first, st.last_name as student_last, st.admission_no, c.class_name, c.section";
            $join = " JOIN students st ON t.student_id = st.id JOIN classes c ON t.class_id = c.id";
        }

        $sql = "SELECT " . implode(', ', $selectFields) . " $extraSelect FROM `" . $modConf['table'] . "` t $join WHERE 1=1";
        $params = [];

        // Apply date filters if field is set
        if (!empty($modConf['date_field'])) {
            if ($dateFrom !== '') {
                $sql .= " AND t." . $modConf['date_field'] . " >= :from";
                $params['from'] = $dateFrom;
            }
            if ($dateTo !== '') {
                $sql .= " AND t." . $modConf['date_field'] . " <= :to";
                $params['to'] = $dateTo;
            }
        }

        // Apply sorting
        if (isset($modConf['fields'][$selectedSort])) {
            $sql .= " ORDER BY t." . $selectedSort . " " . ($selectedOrder === 'DESC' ? 'DESC' : 'ASC');
        }

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $reportTitle = "Custom " . $modConf['name'] . " Report Sheet";
        } catch (Exception $e) {
            error_log("Custom builder run error: " . $e->getMessage());
        }
    }
}
?>

<div class="row mb-4 align-items-center d-print-none">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-sliders text-primary me-2"></i>Custom Report Builder</h3>
        <p class="text-muted small mb-0">Build bespoke tabular registers, select specific metrics columns and apply sorting orders dynamically.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <?php if (!empty($reportData)): ?>
            <button class="btn btn-outline-primary px-3" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print Ledger</button>
            <button class="btn btn-outline-success px-3 ms-2" onclick="exportToExcel()"><i class="fa-solid fa-file-excel me-2"></i>Export Excel</button>
        <?php endif; ?>
        <a href="dashboard.php" class="btn btn-outline-secondary px-3 ms-2"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div>
</div>

<div class="row d-print-none">
    <!-- Configuration panel -->
    <div class="col-lg-4 mb-4">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-gear me-2 text-primary"></i>Report Schema Designer</h5>
            </div>
            <div class="card-body p-4 pt-2">
                <form method="GET" id="builderForm">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Target Module</label>
                        <select class="form-select" name="module" id="moduleSelect" onchange="this.form.submit()">
                            <?php foreach ($modules as $key => $m): ?>
                                <option value="<?php echo $key; ?>" <?php echo $selectedModule === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Column selection boxes -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted d-block">Select Columns to Display</label>
                        <div class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                            <?php 
                                $currConf = $modules[$selectedModule];
                                foreach ($currConf['fields'] as $fKey => $fVal):
                                    $checked = in_array($fKey, $selectedCols) ? 'checked' : '';
                            ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="columns[]" value="<?php echo $fKey; ?>" id="chk_<?php echo $fKey; ?>" <?php echo $checked; ?>>
                                    <label class="form-check-label small fw-semibold text-dark" for="chk_<?php echo $fKey; ?>">
                                        <?php echo htmlspecialchars($fVal); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold text-muted">Sort By</label>
                            <select class="form-select" name="sort_by">
                                <option value="">-- No Sort --</option>
                                <?php foreach ($currConf['fields'] as $fKey => $fVal): ?>
                                    <option value="<?php echo $fKey; ?>" <?php echo $selectedSort === $fKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($fVal); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold text-muted">Order</label>
                            <select class="form-select" name="sort_order">
                                <option value="ASC" <?php echo $selectedOrder === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                                <option value="DESC" <?php echo $selectedOrder === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Date Bounds Range</label>
                        <div class="input-group">
                            <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                            <span class="input-group-text">to</span>
                            <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Generate custom Report</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Output preview panel -->
    <div class="col-lg-8 mb-4">
        <?php if (empty($selectedCols)): ?>
            <div class="card border-0 shadow-sm" style="border-radius:12px; height: 100%;">
                <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-5">
                    <i class="fa-solid fa-rectangle-list fs-1 text-muted opacity-25 mb-3"></i>
                    <h5 class="text-muted fw-bold">Design Worksheet Empty</h5>
                    <p class="text-muted small mb-0">Select your module type, check desired columns and click "Generate Custom Report" to render dynamic data.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;" id="reportPrintArea">
                <!-- Print Header -->
                <div class="card-header bg-white border-0 pt-4 px-4 text-center d-none d-print-block border-bottom pb-3">
                    <h3 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h3>
                    <h5 class="text-secondary fw-semibold mb-1"><?php echo htmlspecialchars($reportTitle); ?></h5>
                    <div class="text-muted small">
                        Date: <?php echo date('d-M-Y H:i'); ?> | Generated By: <?php echo htmlspecialchars($_SESSION['username'] ?? 'ERP Admin'); ?>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                            <thead>
                                <tr>
                                    <th width="60">Index</th>
                                    <?php 
                                        // Extra default name headings if mapped
                                        if (in_array($selectedModule, ['fee_payments', 'exam_results'])) {
                                            echo '<th>Admission No</th>';
                                            echo '<th>Student Name</th>';
                                        }
                                        if ($selectedModule === 'exam_results') {
                                            echo '<th>Class Section</th>';
                                        }
                                        
                                        foreach ($actualColsToDisplay as $head) {
                                            echo '<th>' . htmlspecialchars($head) . '</th>';
                                        }
                                    ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reportData)): ?>
                                    <tr><td colspan="15" class="text-center py-5 text-muted">No custom records resolved matching the current filter constraints.</td></tr>
                                <?php else: foreach ($reportData as $i => $row): ?>
                                    <tr>
                                        <td class="text-muted fw-bold"><?php echo $i + 1; ?></td>
                                        <?php 
                                            if (in_array($selectedModule, ['fee_payments', 'exam_results'])) {
                                                echo '<td><code class="text-muted fw-bold">' . htmlspecialchars($row['admission_no'] ?? '') . '</code></td>';
                                                echo '<td class="fw-bold text-dark">' . htmlspecialchars($row['student_first'] . ' ' . $row['student_last']) . '</td>';
                                            }
                                            if ($selectedModule === 'exam_results') {
                                                echo '<td><span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill">' . htmlspecialchars($row['class_name'] . '-' . $row['section']) . '</span></td>';
                                            }
                                            
                                            foreach ($actualColsToDisplay as $fKey => $fVal) {
                                                $val = $row[$fKey];
                                                // Prettify formatting
                                                if (str_contains($fKey, 'date') && $val) {
                                                    echo '<td class="small fw-semibold">' . date('d-M-Y', strtotime($val)) . '</td>';
                                                } else if (str_contains($fKey, 'salary') || str_contains($fKey, 'amount') || str_contains($fKey, 'payable')) {
                                                    echo '<td class="fw-bold text-dark">Rs. ' . number_format((float)$val, 2) . '</td>';
                                                } else if ($fKey === 'status' && $val) {
                                                    echo '<td><span class="badge bg-' . ($val === 'Active' || $val === 'Paid' || $val === 'Pass' || $val === 'Present' ? 'success' : 'danger') . '-soft rounded-pill px-3 py-1 fw-bold text-xs">' . htmlspecialchars($val) . '</span></td>';
                                                } else {
                                                    echo '<td>' . htmlspecialchars($val ?? '—') . '</td>';
                                                }
                                            }
                                        ?>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #reportPrintArea, #reportPrintArea * {
        visibility: visible;
    }
    #reportPrintArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none !important;
        border: 0 !important;
    }
    .custom-table th, .custom-table td {
        border: 1px solid #ddd !important;
        padding: 8px !important;
        font-size: 10px !important;
    }
}
</style>

<?php $extraJS = '<script>
function exportToExcel() {
    let table = document.getElementById("reportDataTable");
    let html = table.outerHTML;
    let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
    let link = document.createElement("a");
    link.download = "custom_report_' . date('Ymd_His') . '.xls";
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
