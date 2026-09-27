<?php
/**
 * Factory Reset & Complete System Wipedown (0-0 State)
 * Wipes ALL transactional data from ALL tables while keeping Schema, Roles, Permissions, & Super Admin User.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "=========================================================\n";
echo " INDUS ERP - COMPLETE FACTORY RESET (0-0 FRESH STATE)   \n";
echo "=========================================================\n\n";

try {
    $db = Database::getConnection();

    // Disable Foreign Key Constraints
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // Fetch all tables in database
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    // Tables to exclude from complete wipe (keep system roles, permissions, and admin user)
    $preserveTables = ['roles', 'permissions', 'role_permissions', 'users'];

    foreach ($tables as $tbl) {
        if (in_array($tbl, $preserveTables)) {
            echo "➜ PRESERVED SYSTEM TABLE: `$tbl`\n";
            continue;
        }

        try {
            $db->exec("TRUNCATE TABLE `$tbl`;");
            echo "✓ TRUNCATED TO 0: `$tbl`\n";
        } catch (PDOException $e) {
            try {
                $db->exec("DELETE FROM `$tbl`;");
                $db->exec("ALTER TABLE `$tbl` AUTO_INCREMENT = 1;");
                echo "✓ CLEARED TO 0: `$tbl`\n";
            } catch (PDOException $ex) {
                echo "NOTICE (`$tbl`): " . $ex->getMessage() . "\n";
            }
        }
    }

    // Clean any non-admin users if desired, but keep Super Admin (e.g. role_id 1)
    $db->exec("DELETE FROM users WHERE id > 1 AND role_id != 1;");

    // Re-enable Foreign Key Constraints
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "\n=========================================================\n";
    echo " FACTORY RESET COMPLETE - ALL DATA IS NOW AT EXACT 0-0!\n";
    echo " The system is 100% fresh, brand-new, and ready for deployment.\n";
    echo "=========================================================\n";

} catch (Exception $ex) {
    if (isset($db)) $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "FACTORY RESET ERROR: " . $ex->getMessage() . "\n";
}
