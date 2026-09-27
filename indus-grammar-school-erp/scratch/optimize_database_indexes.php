<?php
/**
 * Database Indexing Optimizer for Indus ERP (1,000+ Students Performance)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection();

    $indexes = [
        "ALTER TABLE students ADD INDEX idx_students_class_status (class_id, status)",
        "ALTER TABLE student_marks ADD INDEX idx_marks_exam_student (exam_type_id, student_id)",
        "ALTER TABLE exam_results ADD INDEX idx_er_exam_class_pos (exam_type_id, class_id, position)",
        "ALTER TABLE exam_results ADD INDEX idx_er_exam_class_status (exam_type_id, class_id, status)",
        "ALTER TABLE positions ADD INDEX idx_pos_exam_class (exam_type_id, class_id, position_no)",
        "ALTER TABLE promotions ADD INDEX idx_prom_session_from (academic_session, from_class_id)",
        "ALTER TABLE report_cards ADD INDEX idx_rc_exam_student (exam_type_id, student_id)"
    ];

    echo "--- Optimizing Database Indexes ---\n";
    foreach ($indexes as $sql) {
        try {
            $db->exec($sql);
            echo "SUCCESS: $sql\n";
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate key name')) {
                echo "SKIP (already exists): $sql\n";
            } else {
                echo "NOTICE: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "All database performance indexes optimized!\n";

} catch (Exception $ex) {
    echo "ERROR: " . $ex->getMessage() . "\n";
}
