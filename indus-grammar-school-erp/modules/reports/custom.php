<?php
/**
 * Indus Grammar School ERP - Dynamic Custom Reports Builder & Query Constructor
 * Version 4.0.0 (Bespoke Spreadsheet & Tabular Query Suite)
 */

$pageTitle = 'Custom Reports Builder';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Expanded Modules Configuration
$modules = [
    'students' => [
        'name' => 'Students Directory Roster',
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
            'academic_type'  => 'Academic Program',
            'status'         => 'Status'
        ],
        'date_field' => 'enrollment_date'
    ],
    'staff' => [
        'name' => 'Staff & Faculty Directory',
        'table' => 'staff',
        'fields' => [
            'employee_no'     => 'Employee Number',
            'first_name'      => 'First Name',
            'last_name'       => 'Last Name',
            'department'      => 'Department',
            'designation'     => 'Designation',
            'phone'           => 'Contact Phone',
            'email'           => 'Email Address',
            'date_of_joining' => 'Joining Date',
            'salary'          => 'Base Salary (Rs)',
            'status'          => 'Status'
        ],
        'date_field' => 'date_of_joining'
    ],
    'fee_payments' => [
        'name' => 'Student Fee Collections',
        'table' => 'fee_payments',
        'fields' => [
            'payment_date'    => 'Receipt Date',
            'reference_number'=> 'Reference / Slip #',
            'amount_paid'     => 'Amount Paid (Rs)',
            'payment_method'  => 'Payment Method',
            'remarks'         => 'Payment Remarks'
        ],
        'date_field' => 'payment_date'
    ],
    'expenses' => [
        'name' => 'Operational Expenses Vouchers',
        'table' => 'expenses',
        'fields' => [
            'expense_date'   => 'Expense Date',
            'title'          => 'Expense Title',
            'vendor_supplier'=> 'Vendor / Supplier',
            'invoice_number' => 'Invoice / Voucher #',
            'amount'         => 'Disbursed Amount (Rs)',
            'payment_method' => 'Payment Method',
            'remarks'        => 'Remarks'
        ],
        'date_field' => 'expense_date'
    ],
    'income' => [
        'name' => 'General Incomes & Receipts',
        'table' => 'income',
        'fields' => [
            'income_date'    => 'Income Date',
            'source'         => 'Income Source',
            'reference_no'   => 'Reference #',
            'amount'         => 'Realized Amount (Rs)',
            'payment_method' => 'Payment Method',
            'description'    => 'Description'
        ],
        'date_field' => 'income_date'
    ],
    'bank_transactions' => [
        'name' => 'Bank Transfers & Checkbook',
        'table' => 'bank_transactions',
        'fields' => [
            'date'            => 'Transaction Date',
            'bank_name'       => 'Bank Name',
            'account_number'  => 'Account Number',
            'transaction_type'=> 'Type (Deposit/Withdrawal)',
            'reference_number'=> 'Check / Ref #',
            'amount'          => 'Amount (Rs)',
            'description'     => 'Description'
        ],
        'date_field' => 'date'
    ],
    'exam_results' => [
        'name' => 'Consolidated Exam Results',
        'table' => 'exam_results',
        'fields' => [
            'total_marks'   => 'Total Marks Limit',
            'obtained_marks'=> 'Obtained Marks',
            'percentage'    => 'Percentage (%)',
            'grade'         => 'Assigned Grade',
            'position'      => 'Merit Position Rank',
            'status'        => 'Result Status (Pass/Fail)'
        ],
        'date_field' => 'created_at'
    ],
    'salary_details' => [
        'name' => 'Staff Payroll Disbursements',
        'table' => 'salary_details',
        'fields' => [
            'payment_date'   => 'Disbursement Date',
            'basic_salary'   => 'Basic Salary (Rs)',
            'allowances'     => 'Allowances (Rs)',
            'deductions'     => 'Deductions (Rs)',
            'bonus'          => 'Bonus (Rs)',
            'net_salary'     => 'Net Pay (Rs)',
            'payment_status' => 'Status (Paid/Pending)'
        ],
        'date_field' => 'payment_date'
    ]
];

$selectedModule = sanitize($_GET['module'] ?? 'students');
$selectedCols   = $_GET['columns'] ?? [];
$selectedSort   = sanitize($_GET['sort_by'] ?? '');
$selectedOrder  = sanitize($_GET['sort_order'] ?? 'ASC');
$selectedLimit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$dateFrom       = sanitize($_GET['date_from'] ?? '');
$dateTo         = sanitize($_GET['date_to'] ?? '');
$searchKeyword  = sanitize($_GET['q'] ?? '');

$reportTitle = "Bespoke Query Worksheet";
$reportData = [];
$actualColsToDisplay = [];

if (empty($selectedCols) && isset($modules[$selectedModule])) {
    // Default select first 4 columns if none checked
    $selectedCols = array_slice(array_keys($modules[$selectedModule]['fields']), 0, 5);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($selectedCols) && isset($modules[$selectedModule])) {
    $modConf = $modules[$selectedModule];
    
    $selectFields = [];
    foreach ($selectedCols as $col) {
        if (isset($modConf['fields'][$col])) {
            $selectFields[] = 't.`' . $col . '`';
            $actualColsToDisplay[$col] = $modConf['fields'][$col];
        }
    }

    if (!empty($selectFields)) {
        $join = '';
        $extraSelect = '';
        
        if ($selectedModule === 'fee_payments') {
            $extraSelect = ", st.first_name as student_first, st.last_name as student_last, st.admission_no";
            $join = " JOIN students st ON t.student_id = st.id";
        } else if ($selectedModule === 'exam_results') {
            $extraSelect = ", st.first_name as student_first, st.last_name as student_last, st.admission_no, c.class_name, c.section";
            $join = " JOIN students st ON t.student_id = st.id JOIN classes c ON t.class_id = c.id";
        } else if ($selectedModule === 'salary_details') {
            $extraSelect = ", s.first_name as staff_first, s.last_name as staff_last, s.employee_no, s.department";
            $join = " JOIN staff s ON t.staff_id = s.id";
        }

        $sql = "SELECT " . implode(', ', $selectFields) . " $extraSelect FROM `" . $modConf['table'] . "` t $join WHERE 1=1";
        $params = [];

        // Apply date range
        if (!empty($modConf['date_field'])) {
            if ($dateFrom !== '') {
                $sql .= " AND t.`" . $modConf['date_field'] . "` >= :from";
                $params['from'] = $dateFrom;
            }
            if ($dateTo !== '') {
                $sql .= " AND t.`" . $modConf['date_field'] . "` <= :to";
                $params['to'] = $dateTo;
            }
        }

        // Apply keyword search
        if ($searchKeyword !== '') {
            $searchClauses = [];
            foreach ($selectedCols as $sc) {
                $searchClauses[] = "t.`" . $sc . "` LIKE :q";
            }
            if (!empty($searchClauses)) {
                $sql .= " AND (" . implode(' OR ', $searchClauses) . ")";
                $params['q'] = '%' . $searchKeyword . '%';
            }
        }

        // Apply sorting
        if (!empty($selectedSort) && isset($modConf['fields'][$selectedSort])) {
            $sql .= " ORDER BY t.`" . $selectedSort . "` " . ($selectedOrder === 'DESC' ? 'DESC' : 'ASC');
        }

        // Apply limit
        if ($selectedLimit > 0) {
            $sql .= " LIMIT " . (int)$selectedLimit;
        }

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $reportTitle = "Bespoke " . $modConf['name'] . " Sheet";
        } catch (Exception $e) {
            error_log("Custom builder error: " . $e->getMessage());
        }
    }
}

$resolvedRowsCount = count($reportData);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

:root {
    --cb-font: 'Outfit', sans-serif;
    --cb-indigo: #4f46e5;
    --cb-dark: #0f172a;
    --cb-card-bg: #ffffff;
    --cb-border: #e2e8f0;
    --cb-radius: 16px;
    --cb-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.04), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
}

body {
    font-family: var(--cb-font);
    background-color: #f8fafc;
}

.cb-hero-card {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4338ca 100%);
    border-radius: var(--cb-radius);
    padding: 2.25rem 2rem;
    color: #ffffff;
    box-shadow: 0 20px 25px -5px rgba(49, 46, 129, 0.25);
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
}

.cb-hero-card::before {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.custom-table-card {
    background: var(--cb-card-bg);
    border: 1px solid var(--cb-border);
    border-radius: var(--cb-radius);
    box-shadow: var(--cb-shadow);
    overflow: hidden;
}

.custom-table th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--cb-border);
}

.custom-table td {
    padding: 1.1rem 1.25rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.925rem;
}

.custom-table tbody tr:hover {
    background-color: #f8fafc;
}

@media print {
    body { background: #fff !important; }
    .no-print, .btn, nav, header, sidebar { display: none !important; }
    .cb-hero-card { background: #1e1b4b !important; color: #fff !important; }
    #reportPrintArea { position: static !important; }
}
</style>

<div class="container-fluid px-0">

    <!-- Executive Hero Header -->
    <div class="cb-hero-card">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small fw-semibold">
                        <i class="fa-solid fa-sliders me-1 text-warning"></i> Dynamic Query Constructor
                    </span>
                    <span class="badge bg-white bg-opacity-20 text-white px-3 py-1 rounded-pill small">
                        <?php echo number_format($resolvedRowsCount); ?> Rows Rendered
                    </span>
                </div>
                <h1 class="fw-bold mb-2" style="font-size:1.95rem; letter-spacing:-0.02em;"><?php echo sanitize($reportTitle); ?></h1>
                <p class="text-white-50 mb-0 leading-relaxed" style="max-width: 650px;">
                    Build bespoke tabular registers, select specific metric columns, apply sorting orders dynamically, and export custom spreadsheets.
                </p>
            </div>
            <div class="col-lg-5 text-lg-end mt-4 mt-lg-0 no-print">
                <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end">
                    <?php if (!empty($reportData)): ?>
                        <button class="btn btn-light fw-bold text-dark px-4 py-2 rounded-3 shadow-sm" onclick="exportToExcel()">
                            <i class="fa-solid fa-file-excel text-success me-2"></i>Export Excel / CSV
                        </button>
                        <button class="btn btn-outline-light px-3 py-2 rounded-3" onclick="window.print()">
                            <i class="fa-solid fa-print me-2"></i>Print Worksheet
                        </button>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn btn-outline-light px-3 py-2 rounded-3">
                        <i class="fa-solid fa-arrow-left me-2"></i>Hub
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Builder Configuration & Results Layout -->
    <div class="row d-print-none">
        
        <!-- Left Column: Schema Controls -->
        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-gears text-primary me-2"></i>Schema Constructor
                    </h5>
                </div>
                <div class="card-body p-4 pt-2">
                    <form method="GET" id="builderForm">
                        
                        <!-- 1. Select Target Module -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">Target Entity / Module *</label>
                            <select class="form-select" name="module" id="moduleSelect" onchange="this.form.submit()">
                                <?php foreach ($modules as $key => $m): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $selectedModule === $key ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($m['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 2. Column Checkboxes -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label small fw-bold text-dark mb-0">Select Columns to Include</label>
                                <button type="button" class="btn btn-link p-0 small text-decoration-none text-primary fw-semibold" onclick="toggleAllCheckboxes()">Toggle All</button>
                            </div>
                            <div class="border rounded-3 p-3 bg-light" style="max-height: 220px; overflow-y: auto;">
                                <?php 
                                    $currConf = $modules[$selectedModule];
                                    foreach ($currConf['fields'] as $fKey => $fVal):
                                        $checked = in_array($fKey, $selectedCols) ? 'checked' : '';
                                ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input chk-col" type="checkbox" name="columns[]" value="<?php echo $fKey; ?>" id="chk_<?php echo $fKey; ?>" <?php echo $checked; ?>>
                                        <label class="form-check-label small fw-semibold text-dark" for="chk_<?php echo $fKey; ?>">
                                            <?php echo htmlspecialchars($fVal); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 3. Dynamic Sorting -->
                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label small fw-bold text-dark">Sort By Column</label>
                                <select class="form-select" name="sort_by">
                                    <option value="">— Default Sort —</option>
                                    <?php foreach ($currConf['fields'] as $fKey => $fVal): ?>
                                        <option value="<?php echo $fKey; ?>" <?php echo $selectedSort === $fKey ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($fVal); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-5">
                                <label class="form-label small fw-bold text-dark">Sort Order</label>
                                <select class="form-select" name="sort_order">
                                    <option value="ASC" <?php echo $selectedOrder === 'ASC' ? 'selected' : ''; ?>>A to Z (Asc)</option>
                                    <option value="DESC" <?php echo $selectedOrder === 'DESC' ? 'selected' : ''; ?>>Z to A (Desc)</option>
                                </select>
                            </div>
                        </div>

                        <!-- 4. Date Filter Bounds -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">Date Bounds Filter</label>
                            <div class="input-group">
                                <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                                <span class="input-group-text bg-white">to</span>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                            </div>
                        </div>

                        <!-- 5. Search Keyword & Limit -->
                        <div class="row g-2 mb-4">
                            <div class="col-7">
                                <label class="form-label small fw-bold text-dark">Search Keyword</label>
                                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="Search fields...">
                            </div>
                            <div class="col-5">
                                <label class="form-label small fw-bold text-dark">Row Limit</label>
                                <select class="form-select" name="limit">
                                    <option value="50" <?php echo $selectedLimit === 50 ? 'selected' : ''; ?>>50 Rows</option>
                                    <option value="100" <?php echo $selectedLimit === 100 ? 'selected' : ''; ?>>100 Rows</option>
                                    <option value="500" <?php echo $selectedLimit === 500 ? 'selected' : ''; ?>>500 Rows</option>
                                    <option value="1000" <?php echo $selectedLimit === 1000 ? 'selected' : ''; ?>>1000 Rows</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-indigo text-white w-100 py-3 fw-bold rounded-3" style="background:#4f46e5; border-color:#4f46e5;">
                            <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Render Custom Worksheet
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Live Rendered Worksheet Preview -->
        <div class="col-xl-8 col-lg-7 mb-4">
            <?php if (empty($selectedCols)): ?>
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-5">
                        <i class="fa-solid fa-table-cells fa-4x text-muted mb-3 opacity-25"></i>
                        <h5 class="text-dark fw-bold mb-1">Worksheet Canvas Empty</h5>
                        <p class="text-muted small mb-0" style="max-width: 420px;">
                            Select columns from the Schema Constructor on the left and click "Render Custom Worksheet" to build your custom tabular register.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="custom-table-card shadow-sm mb-4" id="reportPrintArea">
                    
                    <!-- Print Header -->
                    <div class="p-4 text-center d-none d-print-block border-bottom">
                        <h2 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h2>
                        <h4 class="text-secondary fw-semibold mb-1"><?php echo sanitize($reportTitle); ?></h4>
                        <div class="text-muted small">
                            Printed Date: <?php echo date('d-M-Y H:i'); ?> | Rendered Rows: <?php echo number_format($resolvedRowsCount); ?>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                            <thead>
                                <tr>
                                    <th width="60">Index</th>
                                    <?php 
                                        if (in_array($selectedModule, ['fee_payments', 'exam_results'])) {
                                            echo '<th>Admission #</th>';
                                            echo '<th>Student Name</th>';
                                        }
                                        if ($selectedModule === 'exam_results') {
                                            echo '<th>Class Section</th>';
                                        }
                                        if ($selectedModule === 'salary_details') {
                                            echo '<th>Employee #</th>';
                                            echo '<th>Staff Name</th>';
                                        }
                                        
                                        foreach ($actualColsToDisplay as $head) {
                                            echo '<th>' . htmlspecialchars($head) . '</th>';
                                        }
                                    ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reportData)): ?>
                                    <tr><td colspan="15" class="text-center py-5 text-muted">No records resolved matching the current filter constraints.</td></tr>
                                <?php else: foreach ($reportData as $i => $row): ?>
                                    <tr>
                                        <td class="text-muted fw-bold"><?php echo $i + 1; ?></td>
                                        <?php 
                                            if (in_array($selectedModule, ['fee_payments', 'exam_results'])) {
                                                echo '<td><code class="fw-bold text-primary">#' . htmlspecialchars($row['admission_no'] ?? '') . '</code></td>';
                                                echo '<td class="fw-bold text-dark">' . htmlspecialchars(($row['student_first'] ?? '') . ' ' . ($row['student_last'] ?? '')) . '</td>';
                                            }
                                            if ($selectedModule === 'exam_results') {
                                                echo '<td><span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill">' . htmlspecialchars(($row['class_name'] ?? '') . '-' . ($row['section'] ?? '')) . '</span></td>';
                                            }
                                            if ($selectedModule === 'salary_details') {
                                                echo '<td><code class="fw-bold text-primary">#' . htmlspecialchars($row['employee_no'] ?? '') . '</code></td>';
                                                echo '<td class="fw-bold text-dark">' . htmlspecialchars(($row['staff_first'] ?? '') . ' ' . ($row['staff_last'] ?? '')) . '</td>';
                                            }
                                            
                                            foreach ($actualColsToDisplay as $fKey => $fVal) {
                                                $val = $row[$fKey] ?? null;
                                                
                                                if (str_contains($fKey, 'date') && !empty($val)) {
                                                    echo '<td class="small fw-semibold">' . date('d-M-Y', strtotime($val)) . '</td>';
                                                } else if (str_contains($fKey, 'salary') || str_contains($fKey, 'amount') || str_contains($fKey, 'payable') || str_contains($fKey, 'allowances') || str_contains($fKey, 'deductions') || str_contains($fKey, 'bonus')) {
                                                    echo '<td class="fw-bold text-dark">Rs. ' . number_format((float)$val, 2) . '</td>';
                                                } else if ($fKey === 'status' && !empty($val)) {
                                                    echo '<td><span class="badge bg-' . ($val === 'Active' || $val === 'Paid' || $val === 'Pass' || $val === 'Present' || $val === 'Recovered' ? 'success' : 'danger') . ' bg-opacity-10 text-' . ($val === 'Active' || $val === 'Paid' || $val === 'Pass' || $val === 'Present' || $val === 'Recovered' ? 'success' : 'danger') . ' rounded-pill px-3 py-1 fw-bold">' . htmlspecialchars($val) . '</span></td>';
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
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
function toggleAllCheckboxes() {
    const chks = document.querySelectorAll(".chk-col");
    let allChecked = true;
    chks.forEach(c => { if (!c.checked) allChecked = false; });

    chks.forEach(c => { c.checked = !allChecked; });
}

function exportToExcel() {
    let table = document.getElementById("reportDataTable");
    if (!table) return;
    let html = table.outerHTML;
    let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
    let link = document.createElement("a");
    link.download = "custom_query_worksheet_<?php echo date('Ymd_His'); ?>.xls";
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>