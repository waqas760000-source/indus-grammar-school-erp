<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();
$classes = $db->query("
    SELECT c.id, c.class_name, c.section, 
           (SELECT COUNT(*) FROM subjects s WHERE s.class_id = c.id AND s.status = 'Active') as sub_count,
           (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active') as stud_count
    FROM classes c
    HAVING sub_count > 0 AND stud_count > 0
")->fetchAll(PDO::FETCH_ASSOC);

print_r($classes);
