<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

echo "=== PAYROLL SETTINGS COLS ===\n";
try {
    $cols = $db->query("DESCRIBE payroll_settings")->fetchAll(PDO::FETCH_ASSOC);
    print_r($cols);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "=== SALARY PROCESSING COLS ===\n";
try {
    $cols = $db->query("DESCRIBE salary_processing")->fetchAll(PDO::FETCH_ASSOC);
    print_r($cols);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
