<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

echo "Testing Staff Attendance Register Ledger...\n";

$fromDate = date('Y-m-01');
$toDate   = date('Y-m-d');

$stmt = $db->prepare("
    SELECT sa.id, sa.date, sa.status, sa.check_in_time, sa.check_out_time, sa.remarks,
           s.id as staff_record_id, s.employee_no, s.first_name, s.last_name, s.department, s.designation, s.date_of_joining
    FROM staff_attendance sa
    JOIN staff s ON sa.staff_id = s.id
    WHERE sa.date BETWEEN :from AND :to
    ORDER BY sa.date DESC, s.employee_no ASC
");
$stmt->execute(['from' => $fromDate, 'to' => $toDate]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Loaded " . count($records) . " cumulative register entries between $fromDate and $toDate.\n";

$cumSecs = 0;
$clockIns = 0;
$clockOuts = 0;

foreach ($records as $r) {
    if ($r['check_in_time'])  $clockIns++;
    if ($r['check_out_time']) $clockOuts++;
    if ($r['check_in_time'] && $r['check_out_time']) {
        $diff = strtotime($r['check_out_time']) - strtotime($r['check_in_time']);
        if ($diff > 0) $cumSecs += $diff;
    }
}

$cumHours = round($cumSecs / 3600, 1);
echo "Verification Summary:\n";
echo "- Physical Clock-Ins: $clockIns\n";
echo "- Physical Clock-Outs: $clockOuts\n";
echo "- Cumulative Hours Worked: $cumHours hrs\n";

echo "Staff Attendance Register test completed successfully!\n";
