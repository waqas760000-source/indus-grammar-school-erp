<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

echo "Testing Monthly Staff Attendance Report & Audit Engine...\n";

$month = date('m');
$year  = date('Y');
$daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, (int)$month, (int)$year);
$startDate   = sprintf('%04d-%02d-01', (int)$year, (int)$month);
$endDate     = sprintf('%04d-%02d-%02d', (int)$year, (int)$month, $daysInMonth);

// Fetch staff
$staff = $db->query("SELECT id, employee_no, first_name, last_name, department, designation FROM staff WHERE status = 'Active' LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
echo "Loaded " . count($staff) . " active staff members for monthly matrix.\n";

if (empty($staff)) {
    echo "No staff found. Aborting test.\n";
    exit;
}

// Test matrix fetch
$stmt = $db->prepare("
    SELECT staff_id, date, status, check_in_time, check_out_time, remarks
    FROM staff_attendance
    WHERE date BETWEEN :start AND :end
");
$stmt->execute(['start' => $startDate, 'end' => $endDate]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Fetched " . count($logs) . " attendance logs for period $month/$year.\n";

// Test audit log endpoint logic for staff #1
$firstStaff = $staff[0];
$auditStmt = $db->prepare("
    SELECT date, status, check_in_time, check_out_time, remarks
    FROM staff_attendance
    WHERE staff_id = :sid AND date BETWEEN :start AND :end
    ORDER BY date ASC
");
$auditStmt->execute(['sid' => $firstStaff['id'], 'start' => $startDate, 'end' => $endDate]);
$auditLogs = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

echo "Audit log for Emp #{$firstStaff['employee_no']} ({$firstStaff['first_name']} {$firstStaff['last_name']}): " . count($auditLogs) . " days logged.\n";
foreach ($auditLogs as $al) {
    echo "- Date: {$al['date']} | Status: {$al['status']} | In: {$al['check_in_time']} | Out: {$al['check_out_time']}\n";
}

echo "Monthly Staff Attendance Report test completed successfully!\n";
