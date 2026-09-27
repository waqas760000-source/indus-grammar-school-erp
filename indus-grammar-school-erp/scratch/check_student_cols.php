<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$cols = $db->query("DESCRIBE students")->fetchAll(PDO::FETCH_COLUMN);
echo "STUDENTS TABLE COLUMNS:\n";
print_r($cols);
