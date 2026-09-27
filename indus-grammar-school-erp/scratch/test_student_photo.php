<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();
$cols = $db->query("DESCRIBE students")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . " (" . $c['Type'] . ")\n";
}

$s = $db->query("SELECT * FROM students LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "\nSample Student Record:\n";
print_r($s);
