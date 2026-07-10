<!-- Top Navbar Component -->
<nav class="navbar navbar-expand-lg top-navbar">
    <div class="container-fluid p-0">
        <!-- Toggle Button for Sidebar -->
        <button type="button" id="sidebarCollapse" class="navbar-btn-toggle me-3">
            <i class="fa-solid fa-bars-staggered"></i>
        </button>

        <!-- Breadcrumbs -->
        <?php include_once __DIR__ . '/breadcrumbs.php'; ?>

        <!-- Right hand Navigation Elements -->
        <div class="ms-auto d-flex align-items-center gap-3">
            <!-- User Profile Dropdown -->
            <div class="dropdown profile-dropdown">
                <a href="#" class="dropdown-toggle" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($currentUser['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="d-none d-sm-block text-start">
                        <div class="small fw-semibold leading-tight"><?php echo sanitize($currentUser['username'] ?? 'User'); ?></div>
                        <div class="text-muted small leading-tight" style="font-size: 0.75rem;"><?php echo sanitize($currentUser['role_name'] ?? 'Role'); ?></div>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="profileDropdown">
                    <li class="px-3 py-2 border-bottom">
                        <div class="text-secondary small">Logged in as:</div>
                        <div class="fw-semibold text-dark text-truncate" style="max-width: 180px;"><?php echo sanitize($currentUser['email'] ?? ''); ?></div>
                    </li>
                    <li>
                        <a class="dropdown-item py-2" href="<?php echo APP_URL; ?>/dashboard.php">
                            <i class="fa-solid fa-gauge me-2 text-muted"></i> Dashboard
                        </a>
                    </li>
                    <li><hr class="dropdown-divider m-0"></li>
                    <li>
                        <a class="dropdown-item text-danger py-2" href="<?php echo APP_URL; ?>/logout.php">
                            <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Log Out
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
