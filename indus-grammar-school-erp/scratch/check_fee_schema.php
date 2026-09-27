<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

echo "=== FEE SETTINGS COLS ===\n";
try {
    $cols = $db->query("DESCRIBE fee_settings")->fetchAll(PDO::FETCH_ASSOC);
    print_r($cols);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
