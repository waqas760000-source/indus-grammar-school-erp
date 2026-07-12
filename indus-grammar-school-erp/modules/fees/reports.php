<?php
/**
 * Indus Grammar School ERP - Fee Reports Dashboard (Ledger V2)
 * Version 4.0.0
 */

$pageTitle = 'Fee Reports';
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
?>

<style>
@media print {
    .top-navbar, .sidebar, .breadcrumb-card, .filter-section, .btn-print, .btn-export {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .custom-table-card {
        box-shadow: none !important;
        border: none !important;
    }
    table {
        width: 100% !important;
        border-collapse: collapse !important;
    }
    th, td {
        border: 1px solid #dee2e6 !important;
        padding: 8px !important;
    }
}
</style>

<!-- Summary Cards -->
<div class="row g-3 mb-4 filter-section">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm" style="border-radius:12px; background: linear-gradient(135deg, #fff, #f0fdf4);">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-circle bg-success-soft p-3 me-3 text-success fs-4"><i class="fa-solid fa-arrow-trend-up"></i></div>
                <div>
                    <div class="small text-muted fw-semibold">Today Collection</div>
                    <h5 class="fw-bold text-success mb-0">Rs. <?php echo number_format($summary['today_collection'], 0); ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm" style="border-radius:12px; background: linear-gradient(135deg, #fff, #eef2ff);">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-circle bg-primary-soft p-3 me-3 text-primary fs-4"><i class="fa-solid fa-wallet"></i></div>
                <div>
                    <div class="small text-muted fw-semibold">Period Collection</div>
                    <h5 class="fw-bold text-primary mb-0">Rs. <?php echo number_format($summary['monthly_collection'], 0); ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm" style="border-radius:12px; background: linear-gradient(135deg, #fff, #fef5f5);">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-circle bg-danger-soft p-3 me-3 text-danger fs-4"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div>
                    <div class="small text-muted fw-semibold">Pending Dues</div>
                    <h5 class="fw-bold text-danger mb-0">Rs. <?php echo number_format($summary['pending_dues'], 0); ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-circle bg-secondary-soft p-3 me-3 text-secondary fs-4"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="small text-muted fw-semibold">Paid / Unpaid Count</div>
                    <h6 class="fw-bold text-dark mb-0"><?php echo $summary['students_paid']; ?> <span class="text-muted font-normal">vs</span> <?php echo $summary['students_unpaid']; ?></h6>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Filter Panel -->
<div class="card border-0 shadow-sm mb-4 filter-section" style="border-radius:12px;">
    <div class="card-body p-4">
        <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Report Configuration</h5>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Report Type</label>
                <select class="form-select fw-bold text-primary" name="report_type" onchange="this.form.submit()">
                    <option value="daily_collection" <?php echo ($reportType === 'daily_collection') ? 'selected' : ''; ?>>Daily Collection Report</option>
                    <option value="monthly_collection" <?php echo ($reportType === 'monthly_collection') ? 'selected' : ''; ?>>Monthly Collection Report</option>
                    <option value="class_collection" <?php echo ($reportType === 'class_collection') ? 'selected' : ''; ?>>Class-Wise Collection Report</option>
                    <option value="pending_fee" <?php echo ($reportType === 'pending_fee') ? 'selected' : ''; ?>>Pending / Defaulters Report</option>
                    <option value="discount_report" <?php echo ($reportType === 'discount_report') ? 'selected' : ''; ?>>Discounts Report</option>
                    <option value="fine_report" <?php echo ($reportType === 'fine_report') ? 'selected' : ''; ?>>Fines Report</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">From Date</label>
                <input type="date" class="form-control" name="from" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">To Date</label>
                <input type="date" class="form-control" name="to" value="<?php echo $dateTo; ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Academic Type</label>
                <select class="form-select" name="academic_type">
                    <option value="">All Types</option>
                    <option value="School" <?php echo ($academType === 'School') ? 'selected' : ''; ?>>School</option>
                    <option value="Academy" <?php echo ($academType === 'Academy') ? 'selected' : ''; ?>>Academy</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Class</label>
                <select class="form-select" name="class_id">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($classId == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($c['class_name'] . ' - ' . $c['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Payment Method</label>
                <select class="form-select" name="payment_method" <?php echo (in_array($reportType, ['pending_fee', 'discount_report'])) ? 'disabled' : ''; ?>>
                    <option value="">All Methods</option>
                    <option value="Cash" <?php echo ($payMethod === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                    <option value="Bank" <?php echo ($payMethod === 'Bank') ? 'selected' : ''; ?>>Bank</option>
                    <option value="Online" <?php echo ($payMethod === 'Online') ? 'selected' : ''; ?>>Online</option>
                    <option value="Cheque" <?php echo ($payMethod === 'Cheque') ? 'selected' : ''; ?>>Cheque</option>
                </select>
            </div>

            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-search"></i></button>
            </div>
        </form>
    </div>
</div>

<!-- Report Render Table Card -->
<div class="custom-table-card shadow-sm border-0">
    <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0 text-secondary" id="reportHeading">
                <?php 
                    if ($reportType === 'daily_collection') echo 'Daily Collection Report Details';
                    elseif ($reportType === 'monthly_collection') echo 'Monthly Collection Report Details';
                    elseif ($reportType === 'class_collection') echo 'Class Wise Collection Report Details';
                    elseif ($reportType === 'pending_fee') echo 'Pending Defaulters List Details';
                    elseif ($reportType === 'discount_report') echo 'Applied Discounts Summary';
                    else echo 'Fines Summary Report';
                ?>
            </h5>
            <small class="text-muted filter-section">Period: <?php echo date('d M Y', strtotime($dateFrom)); ?> to <?php echo date('d M Y', strtotime($dateTo)); ?></small>
        </div>
        
        <div class="d-flex gap-2 filter-section">
            <button onclick="window.print()" class="btn btn-sm btn-outline-secondary btn-print"><i class="fa-solid fa-print me-1"></i>Print Report</button>
            <button onclick="exportCSV()" class="btn btn-sm btn-success btn-export"><i class="fa-solid fa-file-csv me-1"></i>CSV Export</button>
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
                        <th>Class</th>
                        <th>Method</th>
                        <th class="text-end">Amount Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No collections recorded during this range.</td></tr>
                    <?php else: foreach ($reportData as $row): ?>
                        <tr>
                            <td><strong><?php echo sanitize($row['receipt_no']); ?></strong></td>
                            <td><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                            <td><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><code><?php echo sanitize($row['admission_no']); ?></code></td>
                            <td><?php echo sanitize($row['month']); ?></td>
                            <td><?php echo sanitize($row['class_name'] . ' ' . $row['section']); ?> &middot; <span class="small text-muted"><?php echo sanitize($row['academic_type']); ?></span></td>
                            <td><span class="badge bg-light text-dark border"><?php echo sanitize($row['payment_method']); ?></span></td>
                            <td class="text-end fw-bold text-success">Rs. <?php echo number_format($row['amount_paid'], 2); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>

            <?php elseif ($reportType === 'monthly_collection'): ?>
                <!-- Monthly Collection Layout -->
                <thead>
                    <tr>
                        <th>Month-Year</th>
                        <th>Transactions Count</th>
                        <th class="text-end">Total Collections</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="3" class="text-center py-4 text-muted">No collections found.</td></tr>
                    <?php else: foreach ($reportData as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo sanitize($row['month_year']); ?></td>
                            <td><?php echo sanitize($row['receipts_count']); ?> Receipts</td>
                            <td class="text-end fw-bold text-success">Rs. <?php echo number_format($row['total_collected'], 2); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>

            <?php elseif ($reportType === 'class_collection'): ?>
                <!-- Class Wise Collection Layout -->
                <thead>
                    <tr>
                        <th>Class Name</th>
                        <th>Section</th>
                        <th>Academic Type</th>
                        <th>Transactions Count</th>
                        <th class="text-end">Total Collected</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No class-wise data.</td></tr>
                    <?php else: foreach ($reportData as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo sanitize($row['class_name'] ?: 'No Class'); ?></td>
                            <td><?php echo sanitize($row['section'] ?: '—'); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo sanitize($row['academic_type']); ?></span></td>
                            <td><?php echo sanitize($row['receipts_count']); ?> Payments</td>
                            <td class="text-end fw-bold text-success">Rs. <?php echo number_format($row['total_collected'], 2); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>

            <?php elseif ($reportType === 'pending_fee'): ?>
                <!-- Pending Fee (Defaulters) Layout -->
                <thead>
                    <tr>
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Father Name</th>
                        <th>Class & Section</th>
                        <th>Type</th>
                        <th>Unpaid Months</th>
                        <th class="text-end text-danger fw-bold">Total Pending Due</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">All clear! No defaulters found.</td></tr>
                    <?php else: foreach ($reportData as $row): ?>
                        <tr>
                            <td><code><?php echo sanitize($row['admission_no']); ?></code></td>
                            <td class="fw-bold"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><?php echo sanitize($row['father_name'] ?: '—'); ?></td>
                            <td><?php echo sanitize($row['class_name'] . ' - ' . $row['section']); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo sanitize($row['academic_type']); ?></span></td>
                            <td class="fw-semibold text-warning"><?php echo sanitize($row['unpaid_challans']); ?> Months</td>
                            <td class="text-end fw-bold text-danger">Rs. <?php echo number_format($row['total_pending'], 2); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>

            <?php elseif ($reportType === 'discount_report'): ?>
                <!-- Discount Details Layout -->
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Admission No</th>
                        <th>Class</th>
                        <th>Type</th>
                        <th>Discount Percentage</th>
                        <th>Discount Flat</th>
                        <th>Reason</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No student discounts currently assigned.</td></tr>
                    <?php else: foreach ($reportData as $row): ?>
                        <tr>
                            <td><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><code><?php echo sanitize($row['admission_no']); ?></code></td>
                            <td><?php echo sanitize($row['class_name'] . ' ' . $row['section']); ?></td>
                            <td><?php echo sanitize($row['academic_type']); ?></td>
                            <td class="fw-bold text-success"><?php echo (float)$row['discount_percentage'] > 0 ? (float)$row['discount_percentage'] . '%' : '—'; ?></td>
                            <td class="fw-bold text-success"><?php echo (float)$row['discount_flat'] > 0 ? 'Rs. ' . number_format($row['discount_flat'], 0) : '—'; ?></td>
                            <td><span class="small"><?php echo sanitize($row['discount_reason'] ?: '—'); ?></span></td>
                            <td><span class="badge bg-<?php echo $row['status'] === 'Active' ? 'success' : 'secondary'; ?> rounded-pill small"><?php echo $row['status']; ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>

            <?php elseif ($reportType === 'fine_report'): ?>
                <!-- Fines Details Layout -->
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Admission No</th>
                        <th>Class</th>
                        <th>Type</th>
                        <th>Month</th>
                        <th>Billed Fine</th>
                        <th>Status</th>
                        <th>Date Billed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No ledger fines applied during this period.</td></tr>
                    <?php else: foreach ($reportData as $row): ?>
                        <tr>
                            <td><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><code><?php echo sanitize($row['admission_no']); ?></code></td>
                            <td><?php echo sanitize($row['class_name'] . ' ' . $row['section']); ?></td>
                            <td><?php echo sanitize($row['academic_type']); ?></td>
                            <td><strong><?php echo sanitize($row['month']); ?></strong></td>
                            <td class="fw-bold text-danger">Rs. <?php echo number_format($row['amount'], 2); ?></td>
                            <td><span class="badge bg-<?php echo $row['status'] === 'Paid' ? 'success' : ($row['status'] === 'Waived' ? 'info' : 'warning'); ?> rounded-pill small"><?php echo $row['status']; ?></span></td>
                            <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php $extraJS = '<script>
function exportCSV() {
    const table = document.getElementById("reportDataTable");
    let csv = "";
    
    // Header
    const headers = Array.from(table.querySelectorAll("thead th")).map(th => `"${th.textContent.trim()}"`);
    csv += headers.join(",") + "\\n";
    
    // Rows
    const rows = Array.from(table.querySelectorAll("tbody tr"));
    rows.forEach(row => {
        const cells = Array.from(row.querySelectorAll("td"));
        if(cells.length === 1 && cells[0].getAttribute("colspan")) return; // skip empty text rows
        
        const line = cells.map(td => {
            let txt = td.textContent.trim().replace(/"/g, \'""\');
            return `"${txt}"`;
        });
        csv += line.join(",") + "\\n";
    });
    
    // Download Link
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", "' . $reportType . '_" + new Date().toISOString().slice(0,10) + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>';

include_once __DIR__ . '/../../includes/footer.php';
?>
