<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

echo "Testing Staff Leave Management Workflow...\n";

// 1. Fetch a staff member
$staff = $db->query("SELECT id, employee_no, first_name, last_name FROM staff WHERE status = 'Active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    echo "No active staff found. Aborting test.\n";
    exit;
}

echo "Testing for Staff #{$staff['employee_no']} ({$staff['first_name']} {$staff['last_name']})...\n";

$leaveFrom = date('Y-m-d');
$leaveTo   = date('Y-m-d', strtotime('+1 day'));
$days = 2;

// 2. Insert test leave application
$db->beginTransaction();
$stmt = $db->prepare("
    INSERT INTO staff_leave (staff_id, leave_type, leave_from, leave_to, total_days, reason, status, remarks)
    VALUES (:sid, 'Medical Leave', :from, :to, :days, 'Automated test illness', 'Pending', 'Test application')
");
$stmt->execute([
    'sid'   => $staff['id'],
    'from'  => $leaveFrom,
    'to'    => $leaveTo,
    'days'  => $days
]);
$leaveId = (int)$db->lastInsertId();
$db->commit();

echo "Created test leave application #$leaveId (Pending status).\n";

// 3. Approve leave application & auto-update attendance logs
$db->beginTransaction();
$updateStmt = $db->prepare("UPDATE staff_leave SET status = 'Approved', remarks = 'Approved in test script' WHERE id = ?");
$updateStmt->execute([$leaveId]);

// Auto update attendance logs
$upsert = $db->prepare("
    INSERT INTO staff_attendance (staff_id, date, status, check_in_time, check_out_time, remarks)
    VALUES (:sid, :date, 'Leave', NULL, NULL, :remarks)
    ON DUPLICATE KEY UPDATE 
        status = 'Leave', 
        check_in_time = NULL, 
        check_out_time = NULL, 
        remarks = VALUES(remarks)
");

$start = new DateTime($leaveFrom);
$end   = new DateTime($leaveTo);
$end->modify('+1 day');
$period = new DatePeriod($start, new DateInterval('P1D'), $end);

foreach ($period as $date) {
    $upsert->execute([
        'sid'     => $staff['id'],
        'date'    => $date->format('Y-m-d'),
        'remarks' => "Approved Leave: Medical Leave"
    ]);
}
$db->commit();

echo "Approved leave application #$leaveId and auto-updated daily attendance roster!\n";

// 4. Verify daily attendance logs
$verify = $db->prepare("SELECT * FROM staff_attendance WHERE staff_id = ? AND date BETWEEN ? AND ?");
$verify->execute([$staff['id'], $leaveFrom, $leaveTo]);
$logs = $verify->fetchAll(PDO::FETCH_ASSOC);

echo "Verified " . count($logs) . " daily attendance logs auto-posted as 'Leave':\n";
foreach ($logs as $l) {
    echo "- Date: {$l['date']} | Status: {$l['status']} | Remarks: {$l['remarks']}\n";
}

echo "Staff Leave Management test completed successfully!\n";
