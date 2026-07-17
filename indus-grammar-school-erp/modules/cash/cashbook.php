<?php
/**
 * Indus Grammar School ERP - Cash Book Daily Ledger
 * Version 4.0.0
 */

require_once 'e:/Xampo/htdocs/indus-grammar-school-erp/indus-grammar-school-erp/config/app.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// Date Filters
$fromDate = sanitize($_GET['from_date'] ?? date('Y-m-01'));
$toDate   = sanitize($_GET['to_date'] ?? date('Y-m-d'));

// Query Cash Book Data
$records = [];
try {
    $stmt = $db->prepare("
        SELECT cb.* 
        FROM cash_book cb
        WHERE cb.date BETWEEN :from AND :to
        ORDER BY cb.date ASC
    ");
    $stmt->execute(['from' => $fromDate, 'to' => $toDate]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading cash book: " . $e->getMessage());
}

// Handle Export Action
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cashbook_ledger_' . $fromDate . '_to_' . $toDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, [SCHOOL_NAME . ' - DAILY CASH BOOK LEDGER']);
    fputcsv($output, ['Period:', $fromDate . ' to ' . $toDate]);
    fputcsv($output, []);
    fputcsv($output, ['Date', 'Opening Cash (Rs.)', 'Income / Collections (Rs.)', 'Expenses / Debits (Rs.)', 'Expected Closing Cash (Rs.)', 'Physical Closing Balance (Rs.)', 'Reconciliation Balance (Rs.)']);
    
    foreach ($records as $r) {
        $expectedClosing = ($r['opening_cash'] + $r['income']) - $r['expenses'];
        fputcsv($output, [
            date('d M Y', strtotime($r['date'])),
            number_format($r['opening_cash'], 2, '.', ''),
            number_format($r['income'], 2, '.', ''),
            number_format($r['expenses'], 2, '.', ''),
            number_format($expectedClosing, 2, '.', ''),
            number_format($r['closing_cash'], 2, '.', ''),
            number_format($r['balance'], 2, '.', '')
        ]);
    }
    fclose($output);
    exit;
}

$pageTitle = 'Cash Book Ledger';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-book-bookmark me-2 text-primary"></i>Cash Book Daily Ledger</h3>
        <p class="text-muted small mb-0">Track chronological opening cash, inflows, outflows, ending cash balances, and counter drawer audits.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-outline-primary px-3 me-2" id="btnPrintLedger">
            <i class="fa-solid fa-print me-1"></i>Print Report
        </button>
        <a href="?action=export&from_date=<?php echo $fromDate; ?>&to_date=<?php echo $toDate; ?>" class="btn btn-primary px-3">
            <i class="fa-solid fa-file-excel me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- Date Range Filters Panel -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Date Period</h6>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted">From Date</label>
                <input type="date" class="form-control form-control-sm" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" required>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted">To Date</label>
                <input type="date" class="form-control form-control-sm" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Search Ledger</button>
            </div>
        </form>
    </div>
</div>

<!-- Ledger Grid -->
<div class="custom-table-card shadow-sm border-0 mb-4" id="ledgerReportSection">
    <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-light">
        <h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-list-ol me-2 text-primary"></i>Daily Balance Statement</h5>
        <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill fw-semibold"><?php echo date('d M Y', strtotime($fromDate)) . ' - ' . date('d M Y', strtotime($toDate)); ?></span>
    </div>
    
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Opening Cash</th>
                    <th>Cash Income / Inflows</th>
                    <th>Cash Expenses / Outflows</th>
                    <th>Expected closing</th>
                    <th>Counter Closing Balance</th>
                    <th>Difference</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No transactions registered in this period.</td></tr>
                <?php else: foreach ($records as $r): 
                    $expected = ($r['opening_cash'] + $r['income']) - $r['expenses'];
                    $diff = $r['closing_cash'] - $expected;
                ?>
                    <tr>
                        <td class="fw-bold text-dark"><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                        <td>Rs. <?php echo number_format($r['opening_cash'], 2); ?></td>
                        <td class="text-success fw-bold">+ Rs. <?php echo number_format($r['income'], 2); ?></td>
                        <td class="text-danger fw-bold">- Rs. <?php echo number_format($r['expenses'], 2); ?></td>
                        <td class="fw-semibold text-primary">Rs. <?php echo number_format($expected, 2); ?></td>
                        <td class="fw-bold text-dark">Rs. <?php echo number_format($r['closing_cash'], 2); ?></td>
                        <td>
                            <?php if ($diff < 0): ?>
                                <span class="badge bg-danger-soft text-danger px-3 py-2 rounded-pill fw-bold">Short: Rs. <?php echo number_format(abs($diff), 2); ?></span>
                            <?php elseif ($diff > 0): ?>
                                <span class="badge bg-success-soft text-success px-3 py-2 rounded-pill fw-bold">Surplus: Rs. <?php echo number_format($diff, 2); ?></span>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border px-3 py-2 rounded-pill">Balanced</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Print Only layout template structure -->
<div id="printLedgerTemplate" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 25px; border: 1px solid #ccc; border-radius:10px; width: 800px; margin: 0 auto;">
        <div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px;">
            <h2 style="margin: 0; text-transform: uppercase;"><?php echo SCHOOL_NAME; ?></h2>
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #555;"><?php echo SCHOOL_ADDRESS; ?></p>
            <h4 style="margin: 15px 0 0 0; background: #f0f0f0; padding: 6px; border-radius: 4px; letter-spacing: 1px;">DAILY CASH BOOK STATEMENT SUMMARY</h4>
        </div>
        
        <table style="width:100%; font-size:13px; margin-bottom: 20px;">
            <tr>
                <td><strong>Period Range:</strong> <?php echo date('d M Y', strtotime($fromDate)) . ' to ' . date('d M Y', strtotime($toDate)); ?></td>
                <td style="text-align:right;"><strong>Generated On:</strong> <?php echo date('d M Y, h:i A'); ?></td>
            </tr>
        </table>
        
        <table style="width:100%; border-collapse: collapse; font-size: 12px; text-align: left;" border="1" cellpadding="8">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th>Date</th>
                    <th>Opening Cash</th>
                    <th>Cash Income</th>
                    <th>Cash Expenses</th>
                    <th>Expected Closing</th>
                    <th>Closing Counted</th>
                    <th>Difference</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="7" style="text-align:center;">No records available for this period.</td></tr>
                <?php else: foreach ($records as $r): 
                    $expected = ($r['opening_cash'] + $r['income']) - $r['expenses'];
                    $diff = $r['closing_cash'] - $expected;
                ?>
                    <tr>
                        <td><strong><?php echo date('d M Y', strtotime($r['date'])); ?></strong></td>
                        <td>Rs. <?php echo number_format($r['opening_cash'], 2); ?></td>
                        <td style="color:#2e7d32;">+ Rs. <?php echo number_format($r['income'], 2); ?></td>
                        <td style="color:#c62828;">- Rs. <?php echo number_format($r['expenses'], 2); ?></td>
                        <td>Rs. <?php echo number_format($expected, 2); ?></td>
                        <td><strong>Rs. <?php echo number_format($r['closing_cash'], 2); ?></strong></td>
                        <td style="font-weight:bold; color: <?php echo ($diff < 0) ? '#c62828' : (($diff > 0) ? '#2e7d32' : '#333'); ?>;">
                            <?php echo ($diff < 0) ? 'Short: Rs. ' . number_format(abs($diff), 2) : (($diff > 0) ? 'Surplus: Rs. ' . number_format($diff, 2) : 'Balanced'); ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        
        <table style="width: 100%; margin-top: 60px; font-size: 11px; text-align: center;">
            <tr>
                <td style="width: 33%;"><div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">Prepared By Accountant</div></td>
                <td style="width: 33%;"><div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">Internal School Auditor</div></td>
                <td style="width: 33%;"><div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">Principal / Director Approval</div></td>
            </tr>
        </table>
    </div>
</div>

<?php $extraJS = '<script>
document.addEventListener("DOMContentLoaded", function() {
    const printBtn = document.getElementById("btnPrintLedger");
    if (printBtn) {
        printBtn.addEventListener("click", function() {
            const printContent = document.getElementById("printLedgerTemplate").innerHTML;
            const w = window.open("", "_blank");
            w.document.write("<html><head><title>Cash Book Statement Summary</title></head><body onload=\"window.print(); window.close();\">");
            w.document.write(printContent);
            w.document.write("</body></html>");
            w.document.close();
        });
    }
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
