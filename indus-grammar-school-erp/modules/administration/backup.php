<?php
/**
 * Indus Grammar School ERP - Database Backup Guide & Tools
 * Version 1.0.0
 */

$pageTitle = 'Database Backup';
$breadcrumbActive = 'Administration';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('system_settings');

$dbName = DB_NAME;
$backupMessage = '';
$backupSuccess = false;

if (isset($_POST['backup_now'])) {
    // Generate a backup file in storage/backups (or a temporary directory inside workspace)
    $backupDir = DIR_STORAGE . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
    }
    
    $filename = $dbName . '_backup_' . date('Ymd_His') . '.sql';
    $filepath = $backupDir . '/' . $filename;
    
    // We are on Windows running XAMPP typically, so mysqldump might not be on system PATH.
    // Let's try to export using standard PHP PDO to generate a structured SQL dump.
    try {
        $db = Database::getConnection();
        $tables = [];
        $result = $db->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        $sqlDump = "-- Indus Grammar School ERP SQL Backup\n";
        $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sqlDump .= "-- Database: " . $dbName . "\n\n";
        $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        foreach ($tables as $table) {
            // Get Create Table query
            $createTableStmt = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $sqlDump .= "\n\n" . $createTableStmt['Create Table'] . ";\n\n";
            
            // Get Table Data
            $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $keys = array_keys($row);
                    $escapedKeys = array_map(fn($k) => "`$k`", $keys);
                    $values = array_values($row);
                    $escapedValues = array_map(function($v) use ($db) {
                        if ($v === null) return 'NULL';
                        return $db->quote($v);
                    }, $values);
                    
                    $sqlDump .= "INSERT INTO `$table` (" . implode(', ', $escapedKeys) . ") VALUES (" . implode(', ', $escapedValues) . ");\n";
                }
            }
        }
        $sqlDump .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
        
        file_put_contents($filepath, $sqlDump);
        
        $backupSuccess = true;
        $backupMessage = "Backup successfully created: <code>" . htmlspecialchars($filename) . "</code> (Size: " . number_format(strlen($sqlDump)/1024, 2) . " KB)";
        auditLog('Database Backup', "Backup generated: " . $filename);
    } catch (Exception $e) {
        $backupSuccess = false;
        $backupMessage = "Backup failed: " . htmlspecialchars($e->getMessage());
    }
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-database me-2 text-primary"></i>Database Backup</h3>
    </div>
</div>

<?php if ($backupMessage): ?>
    <div class="alert alert-<?php echo $backupSuccess ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <i class="fa-solid <?php echo $backupSuccess ? 'fa-circle-check' : 'fa-circle-exclamation'; ?> me-2"></i>
        <?php echo $backupMessage; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-download me-2"></i>Generate Instant Backup</h5>
                <p class="text-muted small">Generate a complete SQL schema and data export of your school ERP database. This backup includes roles, users, students, fee registers, cash logs, and grade transcripts.</p>
                
                <form method="POST">
                    <button type="submit" name="backup_now" class="btn btn-primary px-4 py-2 mt-2">
                        <i class="fa-solid fa-file-export me-2"></i>Generate & Save SQL Backup
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-circle-question me-2"></i>Alternative Backup (phpMyAdmin)</h5>
                <p class="text-muted small">You can also manage backups directly from MySQL database manager:</p>
                <ol class="small text-muted ps-3">
                    <li class="mb-2">Open your XAMPP Control Panel.</li>
                    <li class="mb-2">Click <strong>Admin</strong> next to the MySQL service (or visit <code>http://localhost/phpmyadmin</code>).</li>
                    <li class="mb-2">Select the database <code><?php echo $dbName; ?></code> from the sidebar list.</li>
                    <li class="mb-2">Click the <strong>Export</strong> tab in the top navigation menu.</li>
                    <li class="mb-2">Choose "Quick" export method and click <strong>Go</strong> to download the raw SQL file directly.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
