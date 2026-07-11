<!-- Top Navbar Component -->
<nav class="navbar navbar-expand-lg top-navbar px-4 py-2 border-bottom shadow-sm bg-white">
    <div class="container-fluid p-0 d-flex align-items-center justify-content-between">
        
        <!-- Left Side: Logo & School Branding -->
        <div class="d-flex align-items-center gap-3">
            <button type="button" id="sidebarCollapse" class="navbar-btn-toggle me-2 btn btn-link text-dark p-0">
                <i class="fa-solid fa-bars-staggered fs-5"></i>
            </button>
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-graduation-cap text-primary fs-3"></i>
                <div class="d-flex flex-column">
                    <span class="fw-bold text-dark leading-none" style="font-size: 1.1rem; letter-spacing: 0.5px;">Indus Grammar School</span>
                    <span class="text-muted small leading-none" style="font-size: 0.75rem;">Academic Session: <strong><?php echo CURRENT_ACADEMIC_YEAR; ?></strong></span>
                </div>
            </div>
        </div>

        <!-- Center: Date-Time Display -->
        <div class="d-none d-lg-flex align-items-center gap-4 text-muted small">
            <div>
                <i class="fa-regular fa-calendar me-2 text-primary"></i>
                <span><?php echo date('l, d F Y'); ?></span>
            </div>
            <div>
                <i class="fa-regular fa-clock me-2 text-primary"></i>
                <span id="nav-live-time"><?php echo date('h:i A'); ?></span>
            </div>
        </div>

        <!-- Right Side: User profile & notification triggers -->
        <div class="d-flex align-items-center gap-3">
            
            <!-- Notification Icon Dropdown -->
            <div class="dropdown">
                <a href="#" class="text-secondary position-relative p-2" id="notificationMenu" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-regular fa-bell fs-5"></i>
                    <span class="position-absolute top-0 start-50 translate-middle-y badge rounded-pill bg-danger notification-badge" style="font-size: 0.6rem; padding: 3px 6px;">
                        !
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-3 p-3" style="width: 280px; border-radius: 12px;" aria-labelledby="notificationMenu">
                    <li class="dropdown-header fw-bold text-dark border-bottom pb-2 mb-2 px-0">Alert Notifications</li>
                    <li><p class="text-muted small mb-0 px-0">System backup checks completed successfully.</p></li>
                </ul>
            </div>

            <!-- Profile Menu Dropdown -->
            <div class="dropdown profile-dropdown">
                <a href="#" class="dropdown-toggle d-flex align-items-center gap-2 text-decoration-none" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="profile-avatar bg-primary-soft text-primary fw-bold d-flex align-items-center justify-content-center" style="width:36px; height:36px; border-radius:50%;">
                        <?php echo strtoupper(substr($currentUser['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="d-none d-sm-block text-start">
                        <div class="small fw-bold text-dark leading-none"><?php echo sanitize($currentUser['username'] === 'waqas7600' ? 'Waqas Ali' : ucfirst($currentUser['username'])); ?></div>
                        <div class="text-muted small leading-none mt-1" style="font-size: 0.7rem;"><?php echo sanitize($currentUser['role_name'] ?? 'Staff'); ?></div>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-3" style="border-radius:12px;" aria-labelledby="profileDropdown">
                    <li class="px-3 py-2 border-bottom">
                        <div class="text-secondary small">Logged in:</div>
                        <div class="fw-semibold text-dark text-truncate" style="max-width: 160px;"><?php echo sanitize($currentUser['email'] ?? ''); ?></div>
                    </li>
                    <li>
                        <a class="dropdown-item py-2" href="<?php echo APP_URL; ?>/dashboard.php">
                            <i class="fa-solid fa-gauge me-2 text-muted"></i> Dashboard
                        </a>
                    </li>
                    <li><hr class="dropdown-divider m-0"></li>
                    <li>
                        <a class="dropdown-item text-danger py-2" href="<?php echo APP_URL; ?>/logout.php">
                            <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<script>
// Simple live time script
document.addEventListener("DOMContentLoaded", () => {
    const timeEl = document.getElementById("nav-live-time");
    if (timeEl) {
        setInterval(() => {
            const now = new Date();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            timeEl.textContent = hours + ':' + minutes + ' ' + ampm;
        }, 30000);
    }
});
</script>
