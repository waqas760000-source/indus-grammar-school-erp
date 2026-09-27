<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();
echo "--- STAFF ATTENDANCE ---\n";
$stmt = $db->query("DESCRIBE staff_attendance");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "--- STAFF ATTENDANCE SETTINGS ---\n";
try {
    $stmt = $db->query("DESCRIBE staff_attendance_settings");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}
