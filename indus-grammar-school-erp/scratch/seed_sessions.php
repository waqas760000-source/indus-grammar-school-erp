<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();

$count = (int)$db->query("SELECT COUNT(*) FROM academic_sessions")->fetchColumn();
echo "Current sessions count: $count\n";

if ($count === 0) {
    $sessions = [
        ['2026-2027', 1],
        ['2025-2026', 0],
        ['2024-2025', 0],
        ['2027-2028', 0]
    ];
    
    $stmt = $db->prepare("INSERT INTO academic_sessions (session_name, is_active) VALUES (?, ?)");
    foreach ($sessions as $s) {
        $stmt->execute($s);
    }
    echo "Seeded 4 default academic sessions.\n";
}

$rows = $db->query("SELECT * FROM academic_sessions ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
