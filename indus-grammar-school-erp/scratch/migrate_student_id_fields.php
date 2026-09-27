<?php
/**
 * Database Migration Script - Add Unique ID, CNIC/B-Form & Roll Number to Students
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection();

    echo "--- Migrating Students Table for Roll No, Student CNIC/B-Form & Guardian CNIC ---\n";

    $queries = [
        "ALTER TABLE students ADD COLUMN roll_no VARCHAR(20) NULL DEFAULT NULL AFTER admission_no",
        "ALTER TABLE students ADD COLUMN cnic_bform VARCHAR(25) NULL DEFAULT NULL AFTER date_of_birth",
        "ALTER TABLE students ADD COLUMN guardian_cnic VARCHAR(25) NULL DEFAULT NULL AFTER guardian_name",
        "ALTER TABLE students ADD INDEX idx_students_roll_no (roll_no)",
        "ALTER TABLE students ADD INDEX idx_students_cnic (cnic_bform)",
        "ALTER TABLE students ADD INDEX idx_students_guardian_cnic (guardian_cnic)"
    ];

    foreach ($queries as $sql) {
        try {
            $db->exec($sql);
            echo "SUCCESS: $sql\n";
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate column name') || str_contains($e->getMessage(), 'Duplicate key name')) {
                echo "SKIP (already exists): $sql\n";
            } else {
                echo "NOTICE: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "Migration completed successfully!\n";

} catch (Exception $ex) {
    echo "ERROR: " . $ex->getMessage() . "\n";
}
