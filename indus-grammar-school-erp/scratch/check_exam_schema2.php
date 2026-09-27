<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

$stmt = $db->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $t) {
    if (strpos($t, 'exam') !== false || strpos($t, 'result') !== false || strpos($t, 'mark') !== false || strpos($t, 'grade') !== false || strpos($t, 'promot') !== false) {
        echo "- $t\n";
    }
}
