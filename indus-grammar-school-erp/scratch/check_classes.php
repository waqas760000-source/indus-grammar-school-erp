<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$classes = $db->query("SELECT * FROM classes")->fetchAll(PDO::FETCH_ASSOC);
echo "CURRENT CLASSES IN DB:\n";
print_r($classes);
