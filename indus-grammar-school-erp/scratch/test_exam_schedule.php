<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();
$cols = $db->query("DESCRIBE staff")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . " (" . $c['Type'] . ")\n";
}
