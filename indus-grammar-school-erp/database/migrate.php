<?php
/**
 * Indus Grammar School ERP - Database Migration Engine
 * Version 1.0.0
 *
 * Usage:
 *   php database/migrate.php          (Run all pending migrations)
 *   php database/migrate.php status   (Show migration status)
 *   php database/migrate.php create <migration_name>  (Generate a new migration file)
 */

require_once __DIR__ . '/../config/app.php';

class MigrationRunner {
    private PDO $db;
    private string $migrationsDir;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->migrationsDir = __DIR__ . '/migrations';
        $this->initMigrationsTable();
    }

    /**
     * Ensure the migrations tracking table exists in the database
     */
    private function initMigrationsTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `batch` INT NOT NULL,
            `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $this->db->exec($sql);
    }

    /**
     * Get list of already applied migrations
     *
     * @return array
     */
    private function getAppliedMigrations(): array {
        $stmt = $this->db->query("SELECT migration FROM `migrations` ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Get the next batch number
     *
     * @return int
     */
    private function getNextBatch(): int {
        $stmt = $this->db->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations`");
        return (int)$stmt->fetchColumn();
    }

    /**
     * Run all pending migrations
     */
    public function migrate(): void {
        echo "====================================================\n";
        echo "   Indus Grammar School ERP - Database Migrator     \n";
        echo "====================================================\n";

        $applied = $this->getAppliedMigrations();
        $files = glob($this->migrationsDir . '/*.sql');
        sort($files);

        $pending = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!in_array($name, $applied)) {
                $pending[] = $file;
            }
        }

        if (empty($pending)) {
            echo "[✓] Database is already up to date! Nothing to migrate.\n\n";
            return;
        }

        $batch = $this->getNextBatch();
        echo sprintf("[*] Found %d pending migration(s) for Batch #%d:\n\n", count($pending), $batch);

        foreach ($pending as $filePath) {
            $name = basename($filePath);
            echo sprintf("  -> Migrating: %-40s ", $name);
            $start = microtime(true);

            $sql = file_get_contents($filePath);
            if ($sql === false || trim($sql) === '') {
                echo "[SKIPPED - EMPTY FILE]\n";
                continue;
            }

            try {
                // Execute migration SQL
                $this->db->exec("SET FOREIGN_KEY_CHECKS = 0;");
                $this->db->exec($sql);
                $this->db->exec("SET FOREIGN_KEY_CHECKS = 1;");

                // Record in migrations table
                $stmt = $this->db->prepare("INSERT INTO `migrations` (`migration`, `batch`) VALUES (:migration, :batch)");
                $stmt->execute([
                    'migration' => $name,
                    'batch' => $batch
                ]);

                $time = round((microtime(true) - $start) * 1000, 2);
                echo sprintf("[DONE - %sms]\n", $time);
            } catch (Exception $e) {
                echo "[FAILED]\n";
                echo "\n[!] Error in migration '$name':\n" . $e->getMessage() . "\n\n";
                exit(1);
            }
        }

        echo "\n[✓] All migrations completed successfully!\n\n";
    }

    /**
     * Display status of all migrations
     */
    public function status(): void {
        echo "====================================================\n";
        echo "   Indus Grammar School ERP - Migration Status      \n";
        echo "====================================================\n";

        $stmt = $this->db->query("SELECT migration, batch, applied_at FROM `migrations` ORDER BY id ASC");
        $appliedMap = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $appliedMap[$row['migration']] = $row;
        }

        $files = glob($this->migrationsDir . '/*.sql');
        sort($files);

        if (empty($files)) {
            echo "No migration files found in database/migrations/\n";
            return;
        }

        echo sprintf("%-45s | %-8s | %-20s\n", "Migration File", "Status", "Applied At");
        echo str_repeat("-", 80) . "\n";

        foreach ($files as $file) {
            $name = basename($file);
            if (isset($appliedMap[$name])) {
                echo sprintf("%-45s | \033[32mApplied\033[0m  | %-20s (Batch #%d)\n", $name, $appliedMap[$name]['applied_at'], $appliedMap[$name]['batch']);
            } else {
                echo sprintf("%-45s | \033[33mPending\033[0m  | --\n", $name);
            }
        }
        echo "\n";
    }

    /**
     * Create a new blank migration file
     *
     * @param string $name
     */
    public function create(string $name): void {
        $cleanName = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($name));
        $timestamp = date('Y_m_d_His');
        $filename = sprintf("%s_%s.sql", $timestamp, $cleanName);
        $filepath = $this->migrationsDir . '/' . $filename;

        $template = "-- Migration: $name\n-- Created At: " . date('Y-m-d H:i:s') . "\n\n-- Write your SQL migration queries below:\n\n";

        file_put_contents($filepath, $template);
        echo "[+] Created new migration file: database/migrations/$filename\n";
    }
}

// CLI Command Router
$runner = new MigrationRunner();
$action = $argv[1] ?? 'migrate';

switch ($action) {
    case 'status':
        $runner->status();
        break;
    case 'create':
        $name = $argv[2] ?? 'new_migration';
        $runner->create($name);
        break;
    case 'migrate':
    default:
        $runner->migrate();
        break;
}
