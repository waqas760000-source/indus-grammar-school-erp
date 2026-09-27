<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();
$sessions = $db->query("SELECT session_name FROM academic_sessions ORDER BY is_active DESC, session_name DESC")->fetchAll(PDO::FETCH_COLUMN);
echo "Academic Sessions in DB: " . implode(', ', $sessions) . "\n";

$structures = Fee::allStructures();
echo "Structures in DB: " . count($structures) . "\n";
