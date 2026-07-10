<?php
/**
 * Layout Breadcrumbs component
 */
if (isset($breadcrumbActive)): ?>
    <nav aria-label="breadcrumb" class="d-none d-md-inline-block">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo sanitize($breadcrumbActive); ?></li>
        </ol>
    </nav>
<?php endif; ?>
