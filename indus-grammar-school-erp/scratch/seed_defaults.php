<?php
/**
 * Seed Default System Essentials for Fresh Installation
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "--- Seeding Default System Essentials ---\n";

try {
    $db = Database::getConnection();

    // 1. Default Grade Setup Scale
    $gradeScales = [
        ['grade' => 'A+', 'min_percentage' => 90.00, 'max_percentage' => 100.00, 'grade_point' => 4.00, 'remarks' => 'Outstanding'],
        ['grade' => 'A',  'min_percentage' => 80.00, 'max_percentage' => 89.99,  'grade_point' => 3.70, 'remarks' => 'Excellent'],
        ['grade' => 'B',  'min_percentage' => 70.00, 'max_percentage' => 79.99,  'grade_point' => 3.00, 'remarks' => 'Very Good'],
        ['grade' => 'C',  'min_percentage' => 60.00, 'max_percentage' => 69.99,  'grade_point' => 2.00, 'remarks' => 'Good / Satisfactory'],
        ['grade' => 'D',  'min_percentage' => 50.00, 'max_percentage' => 59.99,  'grade_point' => 1.00, 'remarks' => 'Fair / Pass'],
        ['grade' => 'F',  'min_percentage' => 0.00,  'max_percentage' => 49.99,  'grade_point' => 0.00, 'remarks' => 'Fail']
    ];

    $stmtGrade = $db->prepare("INSERT INTO grade_setup (grade, min_percentage, max_percentage, grade_point, remarks) VALUES (?, ?, ?, ?, ?)");
    foreach ($gradeScales as $g) {
        $stmtGrade->execute([$g['grade'], $g['min_percentage'], $g['max_percentage'], $g['grade_point'], $g['remarks']]);
    }
    echo "✓ Default Marks Grading Scale (A+, A, B, C, D, F) Seeded.\n";

    // 2. Default School Settings
    try {
        $db->exec("INSERT INTO school_settings (id, school_name, school_tagline, phone, email, address) 
                   VALUES (1, 'Indus Grammar School', 'Excellence in Education', '+92 300 1234567', 'info@indusgrammar.edu.pk', 'Main Campus, Pakistan')
                   ON DUPLICATE KEY UPDATE school_name=VALUES(school_name)");
        echo "✓ Default School Setting Profile Seeded.\n";
    } catch (Exception $e) {
        // Ignore if table schema differs
    }

    echo "Default essentials seeded successfully!\n";

} catch (Exception $ex) {
    echo "ERROR: " . $ex->getMessage() . "\n";
}
