<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$db->exec("DELETE FROM classes WHERE id = 18");
echo "Cleaned benchmark class ID 18.\n";
