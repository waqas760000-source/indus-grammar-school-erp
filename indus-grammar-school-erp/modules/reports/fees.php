<?php
/**
 * Indus Grammar School ERP - Fee Collection & Dues Reports Panel
 * Version 4.0.0
 */

$pageTitle = 'Centralized Fee Reports Panel';
$breadcrumbActive = 'Reports';
include_once __DIR__ . '/../../includes/header.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

// Selectors
$classes = SchoolClass::all();
$paymentMethods = ['Cash', 'Bank Deposit', 'EasyPaisa', 'JazzCash', 'Cheque'];

// Filter values
$selectedReport = sanitize($_GET['report_type'] ?? 'daily_collection');
$selectedClass  = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedType    = sanitize($_GET['academic_type'] ?? '');
$selectedMethod  = sanitize($_GET['payment_method'] ?? '');
$dateFrom        = sanitize($_GET['date_from'] ?? date('Y-m-01'));
$dateTo          = sanitize($_GET['date_to'] ?? date('Y-m-d'));

// Fetch global stats summary
$summary = Fee::getSummaryStats($dateFrom, $dateTo);

$reportTitle = "Fee Report";
$reportData = [];

try {
    switch ($selectedReport) {
        
        case 'daily_collection':
            $reportTitle = "Daily Fee Payments Collection List";
            $sql = "
                SELECT fp.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section, st.academic_type
                FROM fee_payments fp
                JOIN students st ON fp.student_id = st.id
                LEFT JOIN classes c ON st.class_id = c.id
                WHERE fp.payment_date BETWEEN :from AND :to
            ";
            $params = ['from' => $dateFrom, 'to' => $dateTo];
            if ($selectedClass > 0) {
                $sql .= " AND st.class_id = :cid";
                $params['cid'] = $selectedClass;
            }
            if ($selectedType !== '') {
                $sql .= " AND st.academic_type = :atype";
                $params['atype'] = $selectedType;
            }
            if ($selectedMethod !== '') {
                $sql .= " AND fp.payment_method = :method";
                $params['method'] = $selectedMethod;
            }
            $sql .= " ORDER BY fp.payment_date DESC, fp.id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'monthly_collection':
            $reportTitle = "Monthly Fee Collection Summary";
            $sql = "
                SELECT DATE_FORMAT(fp.payment_date, '%M %Y') as month_name,
                       COUNT(fp.id) as payment_count,
                       SUM(fp.amount_paid) as total_collected,
                       fp.payment_method
                FROM fee_payments fp
                JOIN students st ON fp.student_id = st.id
                WHERE fp.payment_date BETWEEN :from AND :to
            ";
            $params = ['from' => $dateFrom, 'to' => $dateTo];
            if ($selectedClass > 0) {
                $sql .= " AND st.class_id = :cid";
                $params['cid'] = $selectedClass;
            }
            if ($selectedType !== '') {
                $sql .= " AND st.academic_type = :atype";
                $params['atype'] = $selectedType;
            }
            $sql .= " GROUP BY DATE_FORMAT(fp.payment_date, '%Y-%m'), fp.payment_method ORDER BY fp.payment_date DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'pending_fees':
            $reportTitle = "Outstanding Fee Ledgers & Defaulters";
            $sql = "
                SELECT fl.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section, st.academic_type,
                       (fl.total_payable - fl.paid_amount) as outstanding
                FROM fee_ledger fl
                JOIN students st ON fl.student_id = st.id
                LEFT JOIN classes c ON st.class_id = c.id
                WHERE fl.status IN ('Pending', 'Partial')
            ";
            $params = [];
            if ($selectedClass > 0) {
                $sql .= " AND st.class_id = :cid";
                $params['cid'] = $selectedClass;
            }
            if ($selectedType !== '') {
                $sql .= " AND st.academic_type = :atype";
                $params['atype'] = $selectedType;
            }
            $sql .= " ORDER BY outstanding DESC, st.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'paid_students':
            $reportTitle = "Fully Cleared Paid Ledgers";
            $sql = "
                SELECT fl.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section, st.academic_type
                FROM fee_ledger fl
                JOIN students st ON fl.student_id = st.id
                LEFT JOIN classes c ON st.class_id = c.id
                WHERE fl.status = 'Paid'
            ";
            $params = [];
            if ($selectedClass > 0) {
                $sql .= " AND st.class_id = :cid";
                $params['cid'] = $selectedClass;
            }
            if ($selectedType !== '') {
                $sql .= " AND st.academic_type = :atype";
                $params['atype'] = $selectedType;
            }
            $sql .= " ORDER BY fl.due_date DESC, st.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'unpaid_students':
            $reportTitle = "Defaulters / Unpaid Students Roster";
            $sql = "
                SELECT fl.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section, st.academic_type
                FROM fee_ledger fl
                JOIN students st ON fl.student_id = st.id
                LEFT JOIN classes c ON st.class_id = c.id
                WHERE fl.status = 'Pending' AND fl.paid_amount = 0
            ";
            $params = [];
            if ($selectedClass > 0) {
                $sql .= " AND st.class_id = :cid";
                $params['cid'] = $selectedClass;
            }
            if ($selectedType !== '') {
                $sql .= " AND st.academic_type = :atype";
                $params['atype'] = $selectedType;
            }
            $sql .= " ORDER BY fl.due_date ASC, st.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'partial_payments':
            $reportTitle = "Partial Payments Summary Report";
            $sql = "
                SELECT fl.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section, st.academic_type,
                       (fl.total_payable - fl.paid_amount) as outstanding
                FROM fee_ledger fl
                JOIN students st ON fl.student_id = st.id
                LEFT JOIN classes c ON st.class_id = c.id
                WHERE fl.status = 'Partial'
            ";
            $params = [];
            if ($selectedClass > 0) {
                $sql .= " AND st.class_id = :cid";
                $params['cid'] = $selectedClass;
            }
            if ($selectedType !== '') {
                $sql .= " AND st.academic_type = :atype";
                $params['atype'] = $selectedType;
            }
            $sql .= " ORDER BY outstanding DESC, st.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'discount_report':
            $reportTitle = "Fee Concessions & Discounts Log";
            $sql = "
                SELECT fl.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section, st.academic_type
                FROM fee_ledger fl
                JOIN students st ON fl.student_id = st.id
                LEFT JOIN classes c ON st.class_id = c.id
                WHERE fl.discount_amount > 0
            ";
            $params = [];
            if ($selectedClass > 0) {
                $sql .= " AND st.class_id = :cid";
                $params['cid'] = $selectedClass;
            }
            if ($selectedType !== '') {
                $sql .= " AND st.academic_type = :atype";
                $params['atype'] = $selectedType;
            }
            $sql .= " ORDER BY fl.discount_amount DESC, st.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'fine_report':
            $reportTitle = "Late Fee Fines & Penalty Log";
            $sql = "
                SELECT fl.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section, st.academic_type
                FROM fee_ledger fl
                JOIN students st ON fl.student_id = st.id
                LEFT JOIN classes c ON st.class_id = c.id
                WHERE fl.fine_amount > 0
            ";
            $params = [];
            if ($selectedClass > 0) {
                $sql .= " AND st.class_id = :cid";
                $params['cid'] = $selectedClass;
            }
            if ($selectedType !== '') {
                $sql .= " AND st.academic_type = :atype";
                $params['atype'] = $selectedType;
            }
            $sql .= " ORDER BY fl.fine_amount DESC, st.first_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'collection_summary':
            $reportTitle = "Class-Wise Fee Collections Summary";
            $sql = "
                SELECT c.class_name, c.section, st.academic_type,
                       SUM(fl.total_payable) as committed_total,
                       SUM(fl.paid_amount) as realized_total,
                       SUM(fl.discount_amount) as concessions_total,
                       SUM(fl.fine_amount) as penalties_total,
                       SUM(fl.total_payable - fl.paid_amount) as outstanding_total
                FROM fee_ledger fl
                JOIN students st ON fl.student_id = st.id
                JOIN classes c ON st.class_id = c.id
                GROUP BY c.id
                ORDER BY c.class_name ASC
            ";
            $reportData = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch (Exception $e) {
    error_log("Fee report error: " . $e->getMessage());
}
?>

<style>
/* ERP Theme Custom Styling */
.hero-report-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e40af 100%);
    border-radius: 16px;
    position: relative;
    overflow: hidden;
}
.hero-report-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(59,130,246,0.15) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}
.kpi-report-card {
    border-radius: 14px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid rgba(226, 232, 240, 0.8);
    background: #ffffff;
}
.kpi-report-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08) !important;
}
.icon-shape {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}
.custom-table-container {
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    background: #ffffff;
}
.custom-table-container thead th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 14px 16px;
    border-bottom: 2px solid #e2e8f0;
}
.custom-table-container tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
}
.custom-table-container tbody tr:last-child td {
    border-bottom: none;
}
.form-select, .form-control {
    border-radius: 8px;
    border: 1px solid #cbd5e1;
}
.form-select:focus, .form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
}

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
        font-size: 11px !important;
    }
}
</style>

<!-- Hero Banner Header -->
<div class="hero-report-banner text-white p-4 p-lg-5 mb-4 shadow-sm d-print-none">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <div class="d-flex align-items-center mb-2">
                <div class="p-3 bg-white bg-opacity-10 rounded-3 me-3 text-warning">
                    <i class="fa-solid fa-chart-line fs-2"></i>
                </div>
                <div>
                    <h2 class="fw-bold mb-1 text-white">Centralized Fee Reports Panel</h2>
                    <p class="mb-0 text-white-50 fs-6">
                        Evaluate fee ledger balances, daily collections, defaulters rosters, and class revenue breakdowns.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex gap-2 justify-content-lg-end">
                <button onclick="window.print()" class="btn btn-light fw-bold text-dark px-3 py-2 shadow-sm rounded-3">
                    <i class="fa-solid fa-print me-2 text-primary"></i>Print Ledger
                </button>
                <button onclick="exportToExcel()" class="btn btn-success fw-bold px-3 py-2 shadow-sm rounded-3">
                    <i class="fa-solid fa-file-excel me-2"></i>Export Excel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Top KPI Overview Cards -->
<div class="row g-3 mb-4 d-print-none">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-report-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-success-soft text-success me-3 fs-4">
                    <i class="fa-solid fa-cash-register"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Today's Collection</div>
                    <h4 class="fw-bold text-success mb-0">Rs. <?php echo number_format($summary['today_collection'], 0); ?></h4>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-report-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-primary-soft text-primary me-3 fs-4">
                    <i class="fa-solid fa-wallet"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Period Realized Total</div>
                    <h4 class="fw-bold text-primary mb-0">Rs. <?php echo number_format($summary['monthly_collection'], 0); ?></h4>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-report-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-danger-soft text-danger me-3 fs-4">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Pending Deficit Dues</div>
                    <h4 class="fw-bold text-danger mb-0">Rs. <?php echo number_format($summary['pending_dues'], 0); ?></h4>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card kpi-report-card shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="icon-shape bg-info-soft text-info me-3 fs-4">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Student Clearance Rate</div>
                    <h5 class="fw-bold text-dark mb-0">
                        <span class="text-success"><?php echo $summary['students_paid']; ?> Paid</span> 
                        <span class="text-muted fw-normal">/</span> 
                        <span class="text-danger"><?php echo $summary['students_unpaid']; ?> Unpaid</span>
                    </h5>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Form -->
<div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius:14px;">
    <div class="card-body p-4">
        <div class="d-flex align-items-center mb-3">
            <i class="fa-solid fa-sliders text-primary fs-5 me-2"></i>
            <h5 class="fw-bold text-dark mb-0">Report Generator Parameters</h5>
        </div>
        
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-bold text-muted text-uppercase">Report Type</label>
                <select class="form-select fw-bold text-primary" name="report_type" onchange="this.form.submit()">
                    <option value="daily_collection" <?php echo $selectedReport === 'daily_collection' ? 'selected' : ''; ?>>Daily Collection</option>
                    <option value="monthly_collection" <?php echo $selectedReport === 'monthly_collection' ? 'selected' : ''; ?>>Monthly Collection</option>
                    <option value="pending_fees" <?php echo $selectedReport === 'pending_fees' ? 'selected' : ''; ?>>Outstanding Pending Fees</option>
                    <option value="paid_students" <?php echo $selectedReport === 'paid_students' ? 'selected' : ''; ?>>Fully Paid Students</option>
                    <option value="unpaid_students" <?php echo $selectedReport === 'unpaid_students' ? 'selected' : ''; ?>>Unpaid Defaulters Roster</option>
                    <option value="partial_payments" <?php echo $selectedReport === 'partial_payments' ? 'selected' : ''; ?>>Partial Payments Summary</option>
                    <option value="discount_report" <?php echo $selectedReport === 'discount_report' ? 'selected' : ''; ?>>Fee Discounts & Concessions</option>
                    <option value="fine_report" <?php echo $selectedReport === 'fine_report' ? 'selected' : ''; ?>>Late Penalty Fines Log</option>
                    <option value="collection_summary" <?php echo $selectedReport === 'collection_summary' ? 'selected' : ''; ?>>Class Collection Summary</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-muted text-uppercase">Academic Stream</label>
                <select class="form-select" name="academic_type">
                    <option value="">All Streams</option>
                    <option value="School" <?php echo $selectedType === 'School' ? 'selected' : ''; ?>>School</option>
                    <option value="Academy" <?php echo $selectedType === 'Academy' ? 'selected' : ''; ?>>Academy</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-muted text-uppercase">Class Section</label>
                <select class="form-select" name="class_id">
                    <option value="0">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-muted text-uppercase">Payment Method</label>
                <select class="form-select" name="payment_method" <?php echo ($selectedReport !== 'daily_collection') ? 'disabled' : ''; ?>>
                    <option value="">All Methods</option>
                    <?php foreach ($paymentMethods as $pm): ?>
                        <option value="<?php echo $pm; ?>" <?php echo $selectedMethod === $pm ? 'selected' : ''; ?>><?php echo htmlspecialchars($pm); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label class="form-label small fw-bold text-muted text-uppercase">Date Range</label>
                <div class="input-group">
                    <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>" <?php echo in_array($selectedReport, ['pending_fees','paid_students','unpaid_students','partial_payments','discount_report','fine_report','collection_summary']) ? 'disabled' : ''; ?>>
                    <span class="input-group-text bg-light border-start-0 border-end-0">to</span>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>" <?php echo in_array($selectedReport, ['pending_fees','paid_students','unpaid_students','partial_payments','discount_report','fine_report','collection_summary']) ? 'disabled' : ''; ?>>
                </div>
            </div>

            <div class="col-12 text-end mt-3">
                <a href="fees.php" class="btn btn-outline-secondary px-4 me-2"><i class="fa-solid fa-rotate-left me-2"></i>Reset</a>
                <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm"><i class="fa-solid fa-magnifying-glass me-2"></i>Apply Filters</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Output Container Card -->
<div class="custom-table-container shadow-sm mb-5" id="reportPrintArea">
    <!-- Print Header -->
    <div class="card-header bg-white border-0 pt-4 px-4 text-center d-none d-print-block border-bottom pb-3">
        <h3 class="fw-bold text-dark mb-1">INDUS GRAMMAR SCHOOL</h3>
        <h5 class="text-secondary fw-semibold mb-1"><?php echo htmlspecialchars($reportTitle); ?></h5>
        <div class="text-muted small">
            Date: <?php echo date('d-M-Y H:i'); ?> | Generated By: <?php echo htmlspecialchars($_SESSION['username'] ?? 'ERP Admin'); ?>
        </div>
    </div>

    <div class="p-3 p-md-4 bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3 d-print-none">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill fw-bold text-uppercase" style="font-size: 0.75rem;">
                    <?php echo strtoupper(str_replace('_', ' ', $selectedReport)); ?>
                </span>
                <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($reportTitle); ?></h5>
            </div>
            <small class="text-muted d-block mt-1">
                Period: <strong><?php echo date('d M Y', strtotime($dateFrom)); ?></strong> to <strong><?php echo date('d M Y', strtotime($dateTo)); ?></strong>
            </small>
        </div>
        
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="max-width: 260px;">
                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                <input type="text" id="centralSearchInput" class="form-control border-start-0 bg-light" placeholder="Filter rows in view..." onkeyup="filterCentralTable()">
            </div>
            <span class="badge bg-light text-dark border px-3 py-2 fw-semibold">
                Total Rows: <?php echo count($reportData); ?>
            </span>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
                
                <!-- DAILY COLLECTION TABLE -->
                <?php if ($selectedReport === 'daily_collection'): ?>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Class Section</th>
                            <th class="text-center">Academic Stream</th>
                            <th class="text-center">Receipt Date</th>
                            <th>Method</th>
                            <th>Ref Number</th>
                            <th class="text-end">Amount Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): $totalCol = 0; ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">No receipts found for this range.</td></tr>
                        <?php else: $totalCol = 0; foreach ($reportData as $row): $totalCol += (float)$row['amount_paid']; ?>
                            <tr>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($row['class_name'] . ' - ' . $row['section']); ?></span></td>
                                <td class="text-center small fw-semibold text-secondary"><?php echo htmlspecialchars($row['academic_type']); ?></td>
                                <td class="text-center small"><i class="fa-regular fa-calendar-check me-1 text-muted"></i><?php echo date('d-M-Y', strtotime($row['payment_date'])); ?></td>
                                <td><span class="badge bg-info-soft text-info rounded-pill px-3 py-1 fw-bold"><?php echo htmlspecialchars($row['payment_method']); ?></span></td>
                                <td><code class="text-dark small"><?php echo htmlspecialchars($row['reference_number'] ?: '—'); ?></code></td>
                                <td class="text-end fw-bold text-success fs-6">Rs. <?php echo number_format($row['amount_paid'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="7" class="text-end text-uppercase" style="letter-spacing:0.05em;">Total Collections realized:</td>
                                <td class="text-end text-success fs-5">Rs. <?php echo number_format($totalCol, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- MONTHLY COLLECTION TABLE -->
                <?php elseif ($selectedReport === 'monthly_collection'): ?>
                    <thead>
                        <tr>
                            <th>Billing Month</th>
                            <th>Method</th>
                            <th class="text-center">Transactions Count</th>
                            <th class="text-end">Total Realized</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): $totalCol = 0; ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">No collections records resolved.</td></tr>
                        <?php else: $totalCol = 0; foreach ($reportData as $row): $totalCol += (float)$row['total_collected']; ?>
                            <tr>
                                <td class="fw-bold text-dark fs-6"><i class="fa-solid fa-calendar-days text-primary me-2"></i><?php echo htmlspecialchars($row['month_name']); ?></td>
                                <td><span class="badge bg-info-soft text-info px-3 py-1 rounded-pill fw-bold"><?php echo htmlspecialchars($row['payment_method']); ?></span></td>
                                <td class="text-center fw-bold text-muted"><?php echo $row['payment_count']; ?> transactions</td>
                                <td class="text-end fw-bold text-success fs-6">Rs. <?php echo number_format($row['total_collected'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="table-light fw-bold text-dark">
                                <td colspan="3" class="text-end text-uppercase" style="letter-spacing:0.05em;">Total Realized Monthly:</td>
                                <td class="text-end text-success fs-5">Rs. <?php echo number_format($totalCol, 2); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <!-- PENDING / PAID / UNPAID LEDGERS -->
                <?php elseif (in_array($selectedReport, ['pending_fees','paid_students','unpaid_students','partial_payments','discount_report','fine_report'])): ?>
                    <thead>
                        <tr>
                            <th>Admission No</th>
                            <th>Student</th>
                            <th>Class Section</th>
                            <th class="text-center">Billing Period</th>
                            <th class="text-end">Total Payable</th>
                            <th class="text-end">Paid Amount</th>
                            <?php if ($selectedReport === 'discount_report'): ?>
                                <th class="text-end text-info">Discount</th>
                            <?php elseif ($selectedReport === 'fine_report'): ?>
                                <th class="text-end text-danger">Fine Penalty</th>
                            <?php else: ?>
                                <th class="text-end text-danger">Pending Balance</th>
                            <?php endif; ?>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">No records found.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td><code class="text-muted fw-bold"><?php echo htmlspecialchars($row['admission_no']); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill fw-semibold"><?php echo htmlspecialchars($row['class_name'] . ' - ' . $row['section']); ?></span></td>
                                <td class="text-center fw-semibold text-muted"><?php echo htmlspecialchars($row['month'] . ' (' . $row['academic_year'] . ')'); ?></td>
                                <td class="text-end text-muted">Rs. <?php echo number_format($row['total_payable'], 2); ?></td>
                                <td class="text-end text-success fw-bold">Rs. <?php echo number_format($row['paid_amount'], 2); ?></td>
                                <?php if ($selectedReport === 'discount_report'): ?>
                                    <td class="text-end fw-bold text-info">Rs. <?php echo number_format($row['discount_amount'], 2); ?></td>
                                <?php elseif ($selectedReport === 'fine_report'): ?>
                                    <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($row['fine_amount'], 2); ?></td>
                                <?php else: ?>
                                    <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($row['total_payable'] - $row['paid_amount'], 2); ?></td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <span class="badge bg-<?php 
                                        echo $row['status'] === 'Paid' ? 'success' : ($row['status'] === 'Partial' ? 'warning' : 'danger'); 
                                    ?>-soft px-3 py-1 rounded-pill fw-bold"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>

                <!-- COLLECTION SUMMARY (CLASS WISE) -->
                <?php elseif ($selectedReport === 'collection_summary'): ?>
                    <thead>
                        <tr>
                            <th>Class Section</th>
                            <th>Academic Stream</th>
                            <th class="text-end">Committed Total</th>
                            <th class="text-end text-success">Realized Paid</th>
                            <th class="text-end text-info">Concessions (Discounts)</th>
                            <th class="text-end text-warning">Penalties (Fines)</th>
                            <th class="text-end text-danger">Outstanding Balances</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No classes ledger summaries found.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['class_name'] . ' - ' . $row['section']); ?></td>
                                <td class="small fw-semibold"><?php echo htmlspecialchars($row['academic_type']); ?></td>
                                <td class="text-end fw-bold text-secondary">Rs. <?php echo number_format($row['committed_total'], 2); ?></td>
                                <td class="text-end fw-bold text-success">Rs. <?php echo number_format($row['realized_total'], 2); ?></td>
                                <td class="text-end fw-bold text-info">Rs. <?php echo number_format($row['concessions_total'], 2); ?></td>
                                <td class="text-end fw-bold text-warning">Rs. <?php echo number_format($row['penalties_total'], 2); ?></td>
                                <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($row['outstanding_total'], 2); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                <?php endif; ?>

            </table>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function filterCentralTable() {
    const input = document.getElementById("centralSearchInput");
    const filter = input.value.toLowerCase();
    const table = document.getElementById("reportDataTable");
    const trs = table.querySelectorAll("tbody tr");

    trs.forEach(tr => {
        if (tr.classList.contains("table-light")) return;
        const text = tr.textContent.toLowerCase();
        tr.style.display = text.includes(filter) ? "" : "none";
    });
}

function exportToExcel() {
    let table = document.getElementById("reportDataTable");
    let html = table.outerHTML;
    let url = "data:application/vnd.ms-excel;charset=utf-8," + encodeURIComponent(html);
    let link = document.createElement("a");
    link.download = "' . strtolower(str_replace(' ', '_', $reportTitle)) . '_' . date('Ymd') . '.xls";
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
