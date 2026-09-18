<?php
/**
 * Indus Grammar School ERP - Fee Reports Dashboard (Ledger V2)
 * Version 4.0.0
 */

$pageTitle = 'Fee Reports & Revenue Analytics';
$breadcrumbActive = 'Fee & Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('fee_view');

$classes = SchoolClass::all();

// Filter parameters
$reportType = sanitize($_GET['report_type'] ?? 'daily_collection');
$dateFrom   = sanitize($_GET['from'] ?? date('Y-m-01'));
$dateTo     = sanitize($_GET['to'] ?? date('Y-m-d'));
$academType = sanitize($_GET['academic_type'] ?? '');
$classId    = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$payMethod  = sanitize($_GET['payment_method'] ?? '');

// Fetch summary stats metrics
$summary = Fee::getSummaryStats($dateFrom, $dateTo);

// Query report data based on selection
$reportData = [];
try {
    $db = Database::getConnection();
    
    if ($reportType === 'daily_collection') {
        $where = "WHERE fp.payment_date BETWEEN :from AND :to";
        $params = ['from' => $dateFrom, 'to' => $dateTo];
        
        if ($academType !== '') {
            $where .= " AND s.academic_type = :type";
            $params['type'] = $academType;
        }
        if ($classId > 0) {
            $where .= " AND s.class_id = :class_id";
            $params['class_id'] = $classId;
        }
        if ($payMethod !== '') {
            $where .= " AND fp.payment_method = :method";
            $params['method'] = $payMethod;
        }
        
        $sql = "
            SELECT fp.*, fr.receipt_no, s.first_name, s.last_name, s.admission_no, s.academic_type, c.class_name, c.section, fl.month
            FROM fee_payments fp
            JOIN fee_receipts fr ON fr.payment_id = fp.id
            JOIN students s ON fp.student_id = s.id
            JOIN fee_ledger fl ON fp.ledger_id = fl.id
            LEFT JOIN classes c ON s.class_id = c.id
            $where
            ORDER BY fp.payment_date DESC, fp.id DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        
    } elseif ($reportType === 'monthly_collection') {
        $where = "WHERE fp.payment_date BETWEEN :from AND :to";
        $params = ['from' => $dateFrom, 'to' => $dateTo];
        
        if ($academType !== '') {
            $where .= " AND s.academic_type = :type";
            $params['type'] = $academType;
        }
        if ($classId > 0) {
            $where .= " AND s.class_id = :class_id";
            $params['class_id'] = $classId;
        }
        if ($payMethod !== '') {
            $where .= " AND fp.payment_method = :method";
            $params['method'] = $payMethod;
        }
        
        $sql = "
            SELECT DATE_FORMAT(fp.payment_date, '%M %Y') as month_year, 
                   COUNT(fp.id) as receipts_count,
                   SUM(fp.amount_paid) as total_collected
            FROM fee_payments fp
            JOIN students s ON fp.student_id = s.id
            $where
            GROUP BY DATE_FORMAT(fp.payment_date, '%M %Y')
            ORDER BY MIN(fp.payment_date) DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        
    } elseif ($reportType === 'class_collection') {
        $where = "WHERE fp.payment_date BETWEEN :from AND :to";
        $params = ['from' => $dateFrom, 'to' => $dateTo];
        
        if ($academType !== '') {
            $where .= " AND s.academic_type = :type";
            $params['type'] = $academType;
        }
        if ($classId > 0) {
            $where .= " AND s.class_id = :class_id";
            $params['class_id'] = $classId;
        }
        if ($payMethod !== '') {
            $where .= " AND fp.payment_method = :method";
            $params['method'] = $payMethod;
        }
        
        $sql = "
            SELECT c.class_name, c.section, s.academic_type, 
                   COUNT(fp.id) as receipts_count,
                   SUM(fp.amount_paid) as total_collected
            FROM fee_payments fp
            JOIN students s ON fp.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            $where
            GROUP BY c.id, s.academic_type
            ORDER BY c.class_name ASC, c.section ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        
    } elseif ($reportType === 'pending_fee') {
        $where = "WHERE s.status = 'Active' AND fl.status IN ('Pending', 'Partial')";
        $params = [];
        
        if ($academType !== '') {
            $where .= " AND s.academic_type = :type";
            $params['type'] = $academType;
        }
        if ($classId > 0) {
            $where .= " AND s.class_id = :class_id";
            $params['class_id'] = $classId;
        }
        
        $sql = "
            SELECT s.admission_no, s.first_name, s.last_name, s.academic_type,
                   c.class_name, c.section, d.father_name,
                   COUNT(fl.id) as unpaid_challans,
                   SUM(fl.total_payable - fl.paid_amount) as total_pending
            FROM students s
            JOIN fee_ledger fl ON s.id = fl.student_id
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN student_registration_details d ON s.id = d.student_id
            $where
            GROUP BY s.id
            ORDER BY total_pending DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        
    } elseif ($reportType === 'discount_report') {
        $where = "WHERE (sfa.discount_percentage > 0 OR sfa.discount_flat > 0)";
        $params = [];
        
        if ($academType !== '') {
            $where .= " AND s.academic_type = :type";
            $params['type'] = $academType;
        }
        if ($classId > 0) {
            $where .= " AND s.class_id = :class_id";
            $params['class_id'] = $classId;
        }
        
        $sql = "
            SELECT sfa.*, s.first_name, s.last_name, s.admission_no, s.academic_type, c.class_name, c.section
            FROM student_fee_assignments sfa
            JOIN students s ON sfa.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            $where
            ORDER BY sfa.created_at DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        
    } elseif ($reportType === 'fine_report') {
        $where = "WHERE fl.fine_amount > 0 AND fl.created_at BETWEEN :from AND :to";
        $params = ['from' => $dateFrom . ' 00:00:00', 'to' => $dateTo . ' 23:59:59'];
        
        if ($academType !== '') {
            $where .= " AND s.academic_type = :type";
            $params['type'] = $academType;
        }
        if ($classId > 0) {
            $where .= " AND s.class_id = :class_id";
            $params['class_id'] = $classId;
        }
        
        $sql = "
            SELECT fl.month, fl.fine_amount as amount, fl.status, fl.created_at, s.first_name, s.last_name, s.admission_no, s.academic_type, c.class_name, c.section
            FROM fee_ledger fl
            JOIN students s ON fl.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            $where
            ORDER BY fl.created_at DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
    }
    
} catch (Exception $e) {
    error_log("reports.php Query Exception: " . $e->getMessage());
}

$reportTitles = [
    'daily_collection'   => 'Daily Fee Payments Collection Log',
    'monthly_collection' => 'Monthly Fee Collection Summary',
    'class_collection'   => 'Class-Wise Fee Collection Audit',
    'pending_fee'        => 'Outstanding Fee Defaulters Roster',
    'discount_report'    => 'Student Concessions & Discounts Log',
    'fine_report'        => 'Late Fee Fines & Penalty Summary'
];
$currentReportTitle = $reportTitles[$reportType] ?? 'Fee Financial Report';
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
    .d-print-none, .top-navbar, .sidebar, .breadcrumb-card, .filter-section, .btn-print, .btn-export {
        display: none !important;
    }
    body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 12px !important;
    }
    .container-fluid, .main-content {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }
    .custom-table-container {
        border: none !important;
        box-shadow: none !important;
    }
    .table {
        width: 100% !important;
        border-collapse: collapse !important;
    }
    .table th, .table td {
        border: 1px solid #cbd5e1 !important;
        padding: 6px 10px !important;
    }
    .print-header {
        display: block !important;
    }
}
</style>

<!-- Printable Header for Hardcopy Reports -->
<div class="d-none print-header text-center mb-4 pt-2">
    <h2 class="fw-bold text-dark mb-0">INDUS GRAMMAR SCHOOL</h2>
    <h5 class="fw-semibold text-secondary mb-1"><?php echo htmlspecialchars($currentReportTitle); ?></h5>
    <p class="text-muted small">
        Period: <strong><?php echo date('d M Y', strtotime($dateFrom)); ?></strong> to <strong><?php echo date('d M Y', strtotime($dateTo)); ?></strong> | 
        Generated: <?php echo date('d-M-Y H:i A'); ?>
    </p>
    <hr style="border-top: 2px solid #000;">
</div>

<!-- Hero Banner Header -->
<div class="hero-report-banner text-white p-4 p-lg-5 mb-4 shadow-sm d-print-none">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <div class="d-flex align-items-center mb-2">
                <div class="p-3 bg-white bg-opacity-10 rounded-3 me-3 text-warning">
                    <i class="fa-solid fa-chart-line fs-2"></i>
                </div>
                <div>
                    <h2 class="fw-bold mb-1 text-white">Fee Reports & Revenue Analytics</h2>
                    <p class="mb-0 text-white-50 fs-6">
                        Real-time revenue monitoring, daily collections, class balances, defaulters rosters, and concession audits.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex gap-2 justify-content-lg-end">
                <button onclick="window.print()" class="btn btn-light fw-bold text-dark px-3 py-2 shadow-sm rounded-3">
                    <i class="fa-solid fa-print me-2 text-primary"></i>Print Report
                </button>
                <button onclick="exportCSV()" class="btn btn-success fw-bold px-3 py-2 shadow-sm rounded-3">
                    <i class="fa-solid fa-file-csv me-2"></i>Export CSV
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Top KPI Overview Cards -->
<div class="row g-3 mb-4 filter-section d-print-none">
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
                    <div class="text-uppercase text-muted fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Payment Clearance</div>
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

<!-- Report Interactive Filter Control Toolbar -->
<div class="card border-0 shadow-sm mb-4 filter-section d-print-none" style="border-radius:14px;">
    <div class="card-body p-4">
        <div class="d-flex align-items-center mb-3">
            <i class="fa-solid fa-sliders text-primary fs-5 me-2"></i>
            <h5 class="fw-bold text-dark mb-0">Report Generator & Parameters</h5>
        </div>
        
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-bold text-muted text-uppercase">Report Module</label>
                <select class="form-select fw-bold text-primary" name="report_type" onchange="this.form.submit()">
                    <option value="daily_collection" <?php echo ($reportType === 'daily_collection') ? 'selected' : ''; ?>>Daily Fee Payments Log</option>
                    <option value="monthly_collection" <?php echo ($reportType === 'monthly_collection') ? 'selected' : ''; ?>>Monthly Collection Summary</option>
                    <option value="class_collection" <?php echo ($reportType === 'class_collection') ? 'selected' : ''; ?>>Class-Wise Collection Audit</option>
                    <option value="pending_fee" <?php echo ($reportType === 'pending_fee') ? 'selected' : ''; ?>>Outstanding Defaulters Report</option>
                    <option value="discount_report" <?php echo ($reportType === 'discount_report') ? 'selected' : ''; ?>>Discounts & Concessions Log</option>
                    <option value="fine_report" <?php echo ($reportType === 'fine_report') ? 'selected' : ''; ?>>Late Fee Penalty Fines Log</option>
                </select>
            </div>
            
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-muted text-uppercase">From Date</label>
                <input type="date" class="form-control" name="from" value="<?php echo $dateFrom; ?>">
            </div>
            
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-muted text-uppercase">To Date</label>
                <input type="date" class="form-control" name="to" value="<?php echo $dateTo; ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-muted text-uppercase">Academic Stream</label>
                <select class="form-select" name="academic_type">
                    <option value="">All Streams</option>
                    <option value="School" <?php echo ($academType === 'School') ? 'selected' : ''; ?>>School</option>
                    <option value="Academy" <?php echo ($academType === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-muted text-uppercase">Class Section</label>
                <select class="form-select" name="class_id">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($classId == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-1">
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Apply
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Report Results Data Table Card -->
<div class="custom-table-container shadow-sm mb-5">
    <div class="p-3 p-md-4 bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary-soft text-primary px-3 py-1 rounded-pill fw-bold text-uppercase" style="font-size: 0.75rem;">
                    <?php echo strtoupper(str_replace('_', ' ', $reportType)); ?>
                </span>
                <h5 class="fw-bold mb-0 text-dark" id="reportHeading">
                    <?php echo htmlspecialchars($currentReportTitle); ?>
                </h5>
            </div>
            <small class="text-muted d-block mt-1">
                Period: <strong><?php echo date('d M Y', strtotime($dateFrom)); ?></strong> to <strong><?php echo date('d M Y', strtotime($dateTo)); ?></strong>
            </small>
        </div>
        
        <div class="d-flex align-items-center gap-2 filter-section">
            <div class="input-group input-group-sm" style="max-width: 260px;">
                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                <input type="text" id="liveSearchInput" class="form-control border-start-0 bg-light" placeholder="Filter rows in view..." onkeyup="filterReportTable()">
            </div>
            <span class="badge bg-light text-dark border px-3 py-2 fw-semibold">
                Total Rows: <?php echo count($reportData); ?>
            </span>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0" id="reportDataTable">
            <?php if ($reportType === 'daily_collection'): ?>
                <!-- Daily Collection Layout -->
                <thead>
                    <tr>
                        <th>Receipt No</th>
                        <th>Payment Date</th>
                        <th>Student Name</th>
                        <th>Admission No</th>
                        <th>Billing Month</th>
                        <th>Class Section</th>
                        <th>Method</th>
                        <th class="text-end">Amount Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="fa-solid fa-folder-open text-muted fs-1 mb-2 d-block opacity-50"></i>
                                <span class="text-muted fw-semibold">No collection payments found for the selected criteria.</span>
                            </td>
                        </tr>
                    <?php else: $grandTotal = 0; foreach ($reportData as $row): $grandTotal += (float)$row['amount_paid']; ?>
                        <tr>
                            <td><strong class="text-primary"><?php echo sanitize($row['receipt_no']); ?></strong></td>
                            <td><i class="fa-regular fa-calendar-check me-1 text-muted"></i><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                            <td class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><code><?php echo sanitize($row['admission_no']); ?></code></td>
                            <td><span class="badge bg-light text-dark border fw-semibold"><?php echo sanitize($row['month']); ?></span></td>
                            <td>
                                <span class="fw-semibold text-secondary"><?php echo sanitize($row['class_name'] . ' - ' . $row['section']); ?></span>
                                <small class="text-muted d-block" style="font-size:0.75rem;"><?php echo sanitize($row['academic_type']); ?></small>
                            </td>
                            <td><span class="badge bg-info-soft text-info rounded-pill px-3 py-1 fw-bold"><?php echo sanitize($row['payment_method']); ?></span></td>
                            <td class="text-end fw-bold text-success fs-6">Rs. <?php echo number_format($row['amount_paid'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="table-light fw-bold text-dark">
                            <td colspan="7" class="text-end text-uppercase" style="letter-spacing:0.05em;">Total Collection Realized:</td>
                            <td class="text-end text-success fs-5">Rs. <?php echo number_format($grandTotal, 2); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            <?php elseif ($reportType === 'monthly_collection'): ?>
                <!-- Monthly Collection Layout -->
                <thead>
                    <tr>
                        <th>Billing Month & Year</th>
                        <th class="text-center">Total Receipts Issued</th>
                        <th class="text-end">Total Realized Collections</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-5 text-muted">No monthly summaries match the selected range.</td>
                        </tr>
                    <?php else: $grandTotal = 0; foreach ($reportData as $row): $grandTotal += (float)$row['total_collected']; ?>
                        <tr>
                            <td class="fw-bold text-dark fs-6"><i class="fa-solid fa-calendar-days text-primary me-2"></i><?php echo sanitize($row['month_year']); ?></td>
                            <td class="text-center"><span class="badge bg-light text-dark border px-3 py-1 rounded-pill fw-bold"><?php echo sanitize($row['receipts_count']); ?> Receipts</span></td>
                            <td class="text-end fw-bold text-success fs-6">Rs. <?php echo number_format($row['total_collected'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="table-light fw-bold text-dark">
                            <td colspan="2" class="text-end text-uppercase" style="letter-spacing:0.05em;">Period Total:</td>
                            <td class="text-end text-success fs-5">Rs. <?php echo number_format($grandTotal, 2); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            <?php elseif ($reportType === 'class_collection'): ?>
                <!-- Class Wise Collection Layout -->
                <thead>
                    <tr>
                        <th>Class Name</th>
                        <th>Section</th>
                        <th>Academic Stream</th>
                        <th class="text-center">Transactions Count</th>
                        <th class="text-end">Total Collected Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No class-wise data available for this range.</td></tr>
                    <?php else: $grandTotal = 0; foreach ($reportData as $row): $grandTotal += (float)$row['total_collected']; ?>
                        <tr>
                            <td class="fw-bold text-dark"><?php echo sanitize($row['class_name'] ?: 'Unassigned'); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo sanitize($row['section'] ?: '—'); ?></span></td>
                            <td><span class="badge bg-primary-soft text-primary rounded-pill px-3 py-1 fw-bold"><?php echo sanitize($row['academic_type']); ?></span></td>
                            <td class="text-center fw-semibold text-muted"><?php echo sanitize($row['receipts_count']); ?> Payments</td>
                            <td class="text-end fw-bold text-success fs-6">Rs. <?php echo number_format($row['total_collected'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="table-light fw-bold text-dark">
                            <td colspan="4" class="text-end text-uppercase" style="letter-spacing:0.05em;">Grand Total Realized:</td>
                            <td class="text-end text-success fs-5">Rs. <?php echo number_format($grandTotal, 2); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            <?php elseif ($reportType === 'pending_fee'): ?>
                <!-- Pending Fee (Defaulters) Layout -->
                <thead>
                    <tr>
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Father Name</th>
                        <th>Class & Section</th>
                        <th>Stream</th>
                        <th class="text-center">Unpaid Months</th>
                        <th class="text-end text-danger fw-bold">Total Pending Deficit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fa-solid fa-circle-check text-success fs-1 mb-2 d-block opacity-75"></i>
                                <span class="text-success fw-bold">All Clear! No student fee defaulters found for this selection.</span>
                            </td>
                        </tr>
                    <?php else: $grandPending = 0; foreach ($reportData as $row): $grandPending += (float)$row['total_pending']; ?>
                        <tr>
                            <td><code><?php echo sanitize($row['admission_no']); ?></code></td>
                            <td class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td class="text-secondary"><?php echo sanitize($row['father_name'] ?: '—'); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo sanitize($row['class_name'] . ' - ' . $row['section']); ?></span></td>
                            <td><span class="badge bg-primary-soft text-primary rounded-pill px-2 py-1 small"><?php echo sanitize($row['academic_type']); ?></span></td>
                            <td class="text-center"><span class="badge bg-warning-soft text-warning fw-bold px-3 py-1 rounded-pill"><?php echo sanitize($row['unpaid_challans']); ?> Months</span></td>
                            <td class="text-end fw-bold text-danger fs-6">Rs. <?php echo number_format($row['total_pending'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="table-light fw-bold text-dark">
                            <td colspan="6" class="text-end text-uppercase" style="letter-spacing:0.05em;">Total Pending Deficit Dues:</td>
                            <td class="text-end text-danger fs-5">Rs. <?php echo number_format($grandPending, 2); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            <?php elseif ($reportType === 'discount_report'): ?>
                <!-- Discount Details Layout -->
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Admission No</th>
                        <th>Class Section</th>
                        <th>Stream</th>
                        <th>Discount Percentage</th>
                        <th>Discount Flat</th>
                        <th>Reason / Concession Policy</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No student fee concessions or discounts recorded.</td></tr>
                    <?php else: foreach ($reportData as $row): ?>
                        <tr>
                            <td class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><code><?php echo sanitize($row['admission_no']); ?></code></td>
                            <td><span class="badge bg-light text-dark border"><?php echo sanitize($row['class_name'] . ' ' . $row['section']); ?></span></td>
                            <td><span class="badge bg-primary-soft text-primary rounded-pill px-2 py-1 small"><?php echo sanitize($row['academic_type']); ?></span></td>
                            <td class="fw-bold text-success"><?php echo (float)$row['discount_percentage'] > 0 ? (float)$row['discount_percentage'] . '%' : '—'; ?></td>
                            <td class="fw-bold text-success"><?php echo (float)$row['discount_flat'] > 0 ? 'Rs. ' . number_format($row['discount_flat'], 0) : '—'; ?></td>
                            <td class="text-muted small"><?php echo sanitize($row['discount_reason'] ?: 'Standard Concession'); ?></td>
                            <td><span class="badge bg-<?php echo $row['status'] === 'Active' ? 'success' : 'secondary'; ?>-soft px-3 py-1 rounded-pill fw-bold"><?php echo $row['status']; ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>

            <?php elseif ($reportType === 'fine_report'): ?>
                <!-- Fines Details Layout -->
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Admission No</th>
                        <th>Class Section</th>
                        <th>Stream</th>
                        <th>Billing Month</th>
                        <th>Penalty Fine Amount</th>
                        <th>Status</th>
                        <th>Date Applied</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No fine penalty records applied during this period.</td></tr>
                    <?php else: $totalFines = 0; foreach ($reportData as $row): $totalFines += (float)$row['amount']; ?>
                        <tr>
                            <td class="fw-bold text-dark"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><code><?php echo sanitize($row['admission_no']); ?></code></td>
                            <td><span class="badge bg-light text-dark border"><?php echo sanitize($row['class_name'] . ' ' . $row['section']); ?></span></td>
                            <td><span class="badge bg-primary-soft text-primary rounded-pill px-2 py-1 small"><?php echo sanitize($row['academic_type']); ?></span></td>
                            <td><strong class="text-dark"><?php echo sanitize($row['month']); ?></strong></td>
                            <td class="fw-bold text-danger fs-6">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                            <td><span class="badge bg-<?php echo $row['status'] === 'Paid' ? 'success' : ($row['status'] === 'Waived' ? 'info' : 'warning'); ?>-soft px-3 py-1 rounded-pill fw-bold"><?php echo $row['status']; ?></span></td>
                            <td><i class="fa-regular fa-clock me-1 text-muted"></i><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="table-light fw-bold text-dark">
                            <td colspan="5" class="text-end text-uppercase" style="letter-spacing:0.05em;">Total Fines Billed:</td>
                            <td colspan="3" class="text-danger fs-5">Rs. <?php echo number_format($totalFines, 2); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php $extraJS = '<script>
function filterReportTable() {
    const input = document.getElementById("liveSearchInput");
    const filter = input.value.toLowerCase();
    const table = document.getElementById("reportDataTable");
    const trs = table.querySelectorAll("tbody tr");

    trs.forEach(tr => {
        if (tr.classList.contains("table-light")) return; // skip summary total row
        const text = tr.textContent.toLowerCase();
        tr.style.display = text.includes(filter) ? "" : "none";
    });
}

function exportCSV() {
    const table = document.getElementById("reportDataTable");
    let csv = [];
    
    // Headers
    const headers = Array.from(table.querySelectorAll("thead th")).map(th => `"${th.textContent.trim().replace(/"/g, \'""\')}"`);
    csv.push(headers.join(","));
    
    // Rows
    const rows = Array.from(table.querySelectorAll("tbody tr"));
    rows.forEach(row => {
        const cells = Array.from(row.querySelectorAll("td"));
        if(cells.length === 1 && cells[0].getAttribute("colspan")) return; // skip empty state rows
        
        const line = cells.map(td => {
            let txt = td.textContent.trim().replace(/\s+/g, " ").replace(/"/g, \'""\');
            return `"${txt}"`;
        });
        csv.push(line.join(","));
    });
    
    const blob = new Blob([csv.join("\\n")], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "' . $reportType . '_report_" + new Date().toISOString().slice(0,10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>';

include_once __DIR__ . '/../../includes/footer.php';
?>

