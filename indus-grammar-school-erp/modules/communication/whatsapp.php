<?php
/**
 * Indus Grammar School ERP - Communication Module (Disabled)
 */
require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requireLogin();

$pageTitle = 'Module Unavailable';
$breadcrumbActive = 'Dashboard';
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center my-5 d-print-none">
    <div class="col-md-8 text-center py-5">
        <div class="card border-0 shadow-sm bg-white p-5" style="border-radius: 16px;">
            <div class="mb-3">
                <i class="fa-solid fa-comments text-secondary fs-1 opacity-25"></i>
            </div>
            <h3 class="fw-bold text-dark mb-2">Communication Module Disabled</h3>
            <p class="text-muted mb-4">This communication feature has been removed from the navigation and system interface.</p>
            <div>
                <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold">
                    <i class="fa-solid fa-house me-2"></i>Return to Main Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
