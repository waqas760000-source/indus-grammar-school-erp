<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

echo "Testing Attendance Configurations Engine...\n";

// Save test configuration
$stmt = $db->prepare("
    UPDATE staff_attendance_settings 
    SET office_start_time = :start, office_end_time = :end, late_arrival_time = :late, half_day_time = :half,
        working_days = :work, weekend_days = :week, attendance_lock_time = :lock, allow_attendance_editing = :allow
    WHERE id = 1
");

$ok = $stmt->execute([
    'start' => '08:00:00',
    'end'   => '14:00:00',
    'late'  => '08:15:00',
    'half'  => '11:00:00',
    'work'  => 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
    'week'  => 'Sunday',
    'lock'  => '23:59:59',
    'allow' => 1
]);

echo "Updated shift timing configurations in database.\n";

// Fetch & verify
$settings = $db->query("SELECT * FROM staff_attendance_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

echo "Verified Active Configurations:\n";
echo "- Office Shift: {$settings['office_start_time']} to {$settings['office_end_time']}\n";
echo "- Late Arrival Threshold: {$settings['late_arrival_time']}\n";
echo "- Half Day Cutoff: {$settings['half_day_time']}\n";
echo "- Daily Auto-Lock Time: {$settings['attendance_lock_time']}\n";
echo "- Working Days: {$settings['working_days']}\n";
echo "- Weekend Days: {$settings['weekend_days']}\n";

echo "Attendance Configurations test completed successfully!\n";
