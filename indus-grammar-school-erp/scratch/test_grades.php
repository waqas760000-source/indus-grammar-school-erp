<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();
$grades = GradeSetup::all();
echo "Total Grade Levels in Database: " . count($grades) . "\n";
foreach ($grades as $g) {
    echo "ID: {$g['id']} | Grade: {$g['grade']} | Range: {$g['min_percentage']}% - {$g['max_percentage']}% | GPA: {$g['grade_point']} | Remarks: {$g['remarks']}\n";
}

// Test grade percentage matcher function
$testPcts = [95, 85, 75, 65, 55, 45, 35];
echo "\n--- Testing Grade Lookup Function ---\n";
foreach ($testPcts as $pct) {
    $info = GradeSetup::getGradeByPercentage($pct);
    echo "Percentage: {$pct}% => Grade: {$info['grade']} (GPA: {$info['grade_point']}, Remarks: {$info['remarks']})\n";
}
