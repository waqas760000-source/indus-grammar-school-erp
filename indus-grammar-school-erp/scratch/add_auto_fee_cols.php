<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

$queries = [
    "ALTER TABLE fee_settings ADD COLUMN auto_fee_enabled TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE fee_settings ADD COLUMN auto_fee_day INT NOT NULL DEFAULT 1",
    "ALTER TABLE fee_settings ADD COLUMN last_auto_fee_run VARCHAR(20) DEFAULT NULL"
];

foreach ($queries as $q) {
    try {
        $db->exec($q);
        echo "Executed: $q\n";
    } catch (Exception $e) {
        echo "Note: " . $e->getMessage() . "\n";
    }
}

// Ensure default row exists
$count = (int)$db->query("SELECT COUNT(*) FROM fee_settings WHERE id = 1")->fetchColumn();
if ($count === 0) {
    $db->exec("INSERT INTO fee_settings (id, late_fine_amount, grace_days, fine_type, auto_fee_enabled, auto_fee_day) VALUES (1, 100.00, 5, 'Fixed', 1, 1)");
    echo "Inserted default fee_settings row (id = 1).\n";
}

$cols = $db->query("DESCRIBE fee_settings")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
