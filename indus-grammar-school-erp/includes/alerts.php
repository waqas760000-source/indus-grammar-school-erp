<?php
/**
 * Layout Alerts component
 */
if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i>
        <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i>
        <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
