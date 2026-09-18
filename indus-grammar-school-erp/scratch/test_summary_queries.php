<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();

echo "=== TESTING MYSQL ONLY_FULL_GROUP_BY COMPLIANT BREAKDOWN QUERY ===\n\n";

$sqlBreakdown = "
    SELECT 
        COALESCE(c.class_name, s.school_class, 'Unassigned') as class_name,
        COALESCE(c.section, s.school_section, 'A') as section_name,
        COALESCE(d.campus, 'Main Campus') as campus_name,
        COALESCE(s.academic_type, 'School') as academic_type,
        COUNT(DISTINCT s.id) as total_students,
        SUM(CASE WHEN s.gender = 'Male' THEN 1 ELSE 0 END) as boys_count,
        SUM(CASE WHEN s.gender = 'Female' THEN 1 ELSE 0 END) as girls_count,
        SUM(CASE WHEN s.status = 'Active' THEN 1 ELSE 0 END) as active_count
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN student_registration_details d ON s.id = d.student_id
    WHERE 1=1
    GROUP BY 
        COALESCE(c.class_name, s.school_class, 'Unassigned'),
        COALESCE(c.section, s.school_section, 'A'),
        COALESCE(d.campus, 'Main Campus'),
        COALESCE(s.academic_type, 'School')
    ORDER BY class_name ASC, section_name ASC
";

$breakdown = $db->query($sqlBreakdown)->fetchAll(PDO::FETCH_ASSOC);
echo "Class Breakdown rows count: " . count($breakdown) . "\n\n";
foreach ($breakdown as $b) {
    echo "Class: {$b['class_name']} - {$b['section_name']} | Campus: {$b['campus_name']} | Type: {$b['academic_type']} | Boys: {$b['boys_count']} | Girls: {$b['girls_count']} | Active: {$b['active_count']} | Total: {$b['total_students']}\n";
}
