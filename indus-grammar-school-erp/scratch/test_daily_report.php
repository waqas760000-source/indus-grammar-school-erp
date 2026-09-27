<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

echo "Testing Daily Staff Attendance Report Console...\n";

$testDate = date('Y-m-d');
$stmt = $db->prepare("
    SELECT sa.id, sa.date, sa.status, sa.check_in_time, sa.check_out_time, sa.remarks,
           s.employee_no, s.first_name, s.last_name, s.department, s.designation
    FROM staff_attendance sa
    JOIN staff s ON sa.staff_id = s.id
    WHERE sa.date = ?
    ORDER BY s.employee_no ASC
");
$stmt->execute([$testDate]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Loaded " . count($records) . " daily attendance logs for date: $testDate\n";

foreach ($records as $r) {
    $cin  = $r['check_in_time'] ? date('h:i A', strtotime($r['check_in_time'])) : '—';
    $cout = $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '—';
    
    $durationStr = '—';
    if ($r['check_in_time'] && $r['check_out_time']) {
        $diff = strtotime($r['check_out_time']) - strtotime($r['check_in_time']);
        if ($diff > 0) {
            $hrs  = floor($diff / 3600);
            $mins = floor(($diff % 3600) / 60);
            $durationStr = "{$hrs}h {$mins}m";
        }
    }
    
    echo "- Emp #{$r['employee_no']} ({$r['first_name']} {$r['last_name']}): Status = {$r['status']}, In = $cin, Out = $cout, Worked = $durationStr\n";
}

echo "Daily Staff Attendance Report test completed successfully!\n";
