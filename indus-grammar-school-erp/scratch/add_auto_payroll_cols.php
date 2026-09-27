<?php
require_once __DIR__ . '/../config/app.php';
$db = Database::getConnection();

$queries = [
    "ALTER TABLE payroll_settings ADD COLUMN auto_issue_enabled TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE payroll_settings ADD COLUMN auto_issue_day INT NOT NULL DEFAULT 10",
    "ALTER TABLE payroll_settings ADD COLUMN auto_issue_status VARCHAR(20) NOT NULL DEFAULT 'Pending'",
    "ALTER TABLE payroll_settings ADD COLUMN last_auto_issue_run VARCHAR(20) DEFAULT NULL"
];

foreach ($queries as $q) {
    try {
        $db->exec($q);
        echo "Executed: $q\n";
    } catch (Exception $e) {
        echo "Note/Error: " . $e->getMessage() . "\n";
    }
}

$cols = $db->query("DESCRIBE payroll_settings")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
