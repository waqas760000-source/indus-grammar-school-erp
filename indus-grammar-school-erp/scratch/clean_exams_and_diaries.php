<?php
/**
 * Clean Exam Terms, Schedules, Subjects, and Diaries for Fresh Production Setup
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "=========================================================\n";
echo " INDUS ERP - CLEANING EXAMS, DIARIES & TEST TERMS       \n";
echo "=========================================================\n\n";

try {
    $db = Database::getConnection();

    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // All tables related to exams, diaries, and academic testing
    $tablesToClean = [
        'exam_types',
        'exams',
        'exam_schedules',
        'exam_results',
        'positions',
        'report_cards',
        'student_marks',
        'marks',
        'results',
        'daily_diary',
        'diary_edit_history',
        'student_diary',
        'subjects' // Clean test benchmark subjects
    ];

    foreach ($tablesToClean as $tbl) {
        try {
            $db->exec("TRUNCATE TABLE `$tbl`;");
            echo "✓ Truncated table: `$tbl`\n";
        } catch (PDOException $e) {
            try {
                $db->exec("DELETE FROM `$tbl`;");
                $db->exec("ALTER TABLE `$tbl` AUTO_INCREMENT = 1;");
                echo "✓ Cleared table: `$tbl`\n";
            } catch (PDOException $ex) {
                echo "NOTICE (`$tbl`): " . $ex->getMessage() . "\n";
            }
        }
    }

    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Reset AUTO_INCREMENT counters
    $db->exec("ALTER TABLE exam_types AUTO_INCREMENT = 1;");
    $db->exec("ALTER TABLE subjects AUTO_INCREMENT = 1;");

    echo "\n=========================================================\n";
    echo " ALL EXAM TERMS, SCHEDULES, DIARIES & RESULTS CLEARED!\n";
    echo "=========================================================\n";

} catch (Exception $ex) {
    if (isset($db)) $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "ERROR: " . $ex->getMessage() . "\n";
}
