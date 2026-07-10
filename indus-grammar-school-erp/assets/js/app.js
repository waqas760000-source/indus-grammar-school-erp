/**
 * Indus Grammar School ERP - Global Layout & Interaction Handler
 * Version 1.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggler Logic
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');
    const sidebarCollapseBtn = document.getElementById('sidebarCollapse');

    // Load persisted state
    const isSidebarCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
    if (isSidebarCollapsed && sidebar && content) {
        sidebar.classList.add('active');
        content.classList.add('active');
    }

    if (sidebarCollapseBtn && sidebar && content) {
        sidebarCollapseBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            content.classList.toggle('active');
            const collapsed = sidebar.classList.contains('active');
            localStorage.setItem('sidebar-collapsed', collapsed);
        });
    }

    // Auto-fade dismissible alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert-dismissible').forEach(function(el) {
            const btn = el.querySelector('.btn-close');
            if (btn) btn.click();
        });
    }, 5000);
});
