<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();
$stmt = $db->query("DESCRIBE staff");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
