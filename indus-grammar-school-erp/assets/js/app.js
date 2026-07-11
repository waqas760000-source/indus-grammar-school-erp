/**
 * Indus Grammar School ERP - Global Layout & Interaction Handler
 * Version 2.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');
    const sidebarCollapseBtn = document.getElementById('sidebarCollapse');
    const overlay = document.getElementById('sidebarOverlay');

    // Load persisted sidebar state (for desktop only)
    const isSidebarCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
    if (isSidebarCollapsed && sidebar && content && window.innerWidth > 992) {
        sidebar.classList.add('active');
        content.classList.add('active');
    }

    // Toggle button handler
    if (sidebarCollapseBtn && sidebar && content) {
        sidebarCollapseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (window.innerWidth <= 992) {
                // Mobile behavior: Off-canvas sliding drawer
                sidebar.classList.toggle('show-mobile');
                if (overlay) overlay.classList.toggle('active');
            } else {
                // Desktop behavior: Collapse to mini-sidebar
                sidebar.classList.toggle('active');
                content.classList.toggle('active');
                
                // Collapse all open submenus if sidebar is collapsed
                if (sidebar.classList.contains('active')) {
                    const openSubmenus = sidebar.querySelectorAll('ul.submenu.show');
                    openSubmenus.forEach(sub => {
                        const bsCollapse = bootstrap.Collapse.getInstance(sub);
                        if (bsCollapse) bsCollapse.hide();
                    });
                }
                
                const collapsed = sidebar.classList.contains('active');
                localStorage.setItem('sidebar-collapsed', collapsed);
            }
        });
    }

    // Close mobile drawer on overlay click
    if (overlay && sidebar) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show-mobile');
            overlay.classList.remove('active');
        });
    }

    // Submenu click highlight recovery
    const currentPath = window.location.pathname;
    const sidebarLinks = sidebar ? sidebar.querySelectorAll('.sidebar-menu a') : [];

    sidebarLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (!href) return;

        // Strip queries/fragments or match standard pathnames
        const linkPath = new URL(link.href).pathname;
        if (currentPath.includes(linkPath)) {
            // Found matching link! Set active styles
            const li = link.closest('li');
            if (li) li.classList.add('active');

            // If it is inside a submenu, expand parent menu
            const parentCollapse = link.closest('.submenu');
            if (parentCollapse) {
                parentCollapse.classList.add('show');
                const parentToggle = sidebar.querySelector(`a[href="#${parentCollapse.id}"]`);
                if (parentToggle) {
                    parentToggle.setAttribute('aria-expanded', 'true');
                    parentToggle.classList.remove('collapsed');
                    
                    // Highlight the parent item wrapper li
                    const parentLi = parentToggle.closest('li');
                    if (parentLi) parentLi.classList.add('active');
                }
            }
        }
    });

    // Persistent accordion expansion tracker (across page loads)
    const toggles = sidebar ? sidebar.querySelectorAll('.sidebar-link-toggle') : [];
    toggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.getAttribute('href');
            // If opening this, persist target in localStorage
            setTimeout(() => {
                const target = document.querySelector(targetId);
                if (target && target.classList.contains('show')) {
                    localStorage.setItem('open-menu', targetId);
                } else {
                    localStorage.removeItem('open-menu');
                }
            }, 350);
        });
    });

    // Recover open accordion menu on refresh if not already open by path
    const persistedOpenMenu = localStorage.getItem('open-menu');
    if (persistedOpenMenu) {
        const targetMenu = document.querySelector(persistedOpenMenu);
        if (targetMenu && !targetMenu.classList.contains('show')) {
            const parentToggle = sidebar.querySelector(`a[href="${persistedOpenMenu}"]`);
            if (parentToggle) {
                const bsCollapse = new bootstrap.Collapse(targetMenu, { toggle: false });
                bsCollapse.show();
                parentToggle.setAttribute('aria-expanded', 'true');
                parentToggle.classList.remove('collapsed');
            }
        }
    }

    // Auto-fade dismissible alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert-dismissible').forEach(function(el) {
            const btn = el.querySelector('.btn-close');
            if (btn) btn.click();
        });
    }, 5000);
});
