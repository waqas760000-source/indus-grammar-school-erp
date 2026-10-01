<?php
/**
 * Indus Grammar School ERP - One-Click Production Database Seeder
 * Access via: https://indusschoolacadmy.space/seed_live.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/database/migrate.php';

header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html><html><head><title>Database Seeder & Migration</title>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">';
echo '</head><body class="bg-light"><div class="container py-5"><div class="card shadow-sm">';
echo '<div class="card-header bg-primary text-white"><h4 class="mb-0">🚀 Indus ERP Database Seeder & Migrator</h4></div>';
echo '<div class="card-body"><pre class="bg-dark text-light p-4 rounded" style="max-height: 500px; overflow-y: auto;">';

try {
    // 1. Run migrations engine
    $runner = new MigrationRunner();
    $runner->migrate();

    // 2. Run student seeder scripts
    echo "\n=== Running 9th Computer Green Seeder ===\n";
    ob_start();
    include __DIR__ . '/scratch/import_9th_computer_green.php';
    echo htmlspecialchars(ob_get_clean());

    echo "\n=== Running 9th Biology Red Seeder ===\n";
    ob_start();
    include __DIR__ . '/scratch/import_9th_bio_red.php';
    echo htmlspecialchars(ob_get_clean());

    echo "\n=== Database sync & student seeding completed successfully! ===\n";
    echo '</pre>';
    echo '<div class="alert alert-success mt-3">✅ All student records & database migrations are synced on live!</div>';
    echo '<a href="dashboard.php" class="btn btn-success btn-lg mt-2">Go to Dashboard</a>';
} catch (Exception $e) {
    echo '</pre>';
    echo '<div class="alert alert-danger mt-3">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

echo '</div></div></div></body></html>';
