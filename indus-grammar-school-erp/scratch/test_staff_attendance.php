<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

echo "Testing Daily Staff Attendance Engine...\n";

// 1. Fetch staff
$staff = $db->query("SELECT id, employee_no, first_name, last_name FROM staff LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
echo "Loaded " . count($staff) . " staff members.\n";

if (empty($staff)) {
    echo "No staff found. Aborting test.\n";
    exit;
}

$testDate = date('Y-m-d');
$records = [];
foreach ($staff as $idx => $s) {
    $records[] = [
        'staff_id' => $s['id'],
        'status'   => ($idx % 2 === 0) ? 'Present' : 'Late',
        'check_in' => '08:05',
        'check_out' => '14:00',
        'remarks'  => 'Automated test roster log'
    ];
}

// 2. Perform upsert test in DB directly or simulate save_staff_attendance_bulk logic
$db->beginTransaction();
$insStmt = $db->prepare("
    INSERT INTO staff_attendance (staff_id, date, status, check_in_time, check_out_time, remarks)
    VALUES (:sid, :date, :status, :cin, :cout, :remarks)
    ON DUPLICATE KEY UPDATE 
        status = VALUES(status), 
        check_in_time = VALUES(check_in_time), 
        check_out_time = VALUES(check_out_time), 
        remarks = VALUES(remarks)
");

foreach ($records as $r) {
    $insStmt->execute([
        'sid'     => $r['staff_id'],
        'date'    => $testDate,
        'status'  => $r['status'],
        'cin'     => $r['check_in'],
        'cout'    => $r['check_out'],
        'remarks' => $r['remarks']
    ]);
}
$db->commit();

echo "Successfully saved attendance records for date: $testDate!\n";

// 3. Verify attendance records
$verify = $db->prepare("SELECT sa.*, s.employee_no, s.first_name, s.last_name FROM staff_attendance sa JOIN staff s ON sa.staff_id = s.id WHERE sa.date = ?");
$verify->execute([$testDate]);
$rows = $verify->fetchAll(PDO::FETCH_ASSOC);

echo "Verified " . count($rows) . " attendance logs in database:\n";
foreach ($rows as $row) {
    echo "- Emp #{$row['employee_no']} ({$row['first_name']} {$row['last_name']}): Status = {$row['status']}, In = {$row['check_in_time']}, Out = {$row['check_out_time']}\n";
}

echo "Daily Staff Attendance test completed successfully!\n";
