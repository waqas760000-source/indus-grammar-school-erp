<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

echo "--- STUDENTS ---\n";
$st = $db->query('DESCRIBE students');
while($r = $st->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}

echo "\n--- STUDENT_REGISTRATION_DETAILS ---\n";
$st2 = $db->query('DESCRIBE student_registration_details');
while($r = $st2->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}
