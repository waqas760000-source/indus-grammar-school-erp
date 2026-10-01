<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

echo "=== Updating 21 Students to Pre 9th Bio Red ===\n";

// 1. Locate Class ID for Pre 9th Bio Red
$stmtCls = $db->prepare("SELECT id FROM classes WHERE class_name = 'Pre 9th Bio' AND section = 'Red' LIMIT 1");
$stmtCls->execute();
$classId = $stmtCls->fetchColumn();

if (!$classId) {
    $db->exec("INSERT INTO classes (class_name, section) VALUES ('Pre 9th Bio', 'Red')");
    $classId = (int)$db->lastInsertId();
}

echo "Using Class ID: {$classId} (Pre 9th Bio / Red)\n";

// Update students table
$stmt1 = $db->prepare("
    UPDATE students SET 
        class_id = :cid,
        school_class = 'Pre 9th Bio',
        school_section = 'Red'
    WHERE id IN (338,341,342,346,350,351,368,369,552,596,631,632,661,720,877,906,1213,1314,1335,1462,1504)
");
$stmt1->execute(['cid' => $classId]);
echo "Updated " . $stmt1->rowCount() . " records in students table.\n";

// Update student_registration_details table
$stmt2 = $db->prepare("
    UPDATE student_registration_details SET 
        school_class = 'Pre 9th Bio',
        school_section = 'Red'
    WHERE student_id IN (338,341,342,346,350,351,368,369,552,596,631,632,661,720,877,906,1213,1314,1335,1462,1504)
");
$stmt2->execute();
echo "Updated " . $stmt2->rowCount() . " records in student_registration_details table.\n";

echo "=== Success! All 21 student records updated to Pre 9th Bio Red. ===\n";
