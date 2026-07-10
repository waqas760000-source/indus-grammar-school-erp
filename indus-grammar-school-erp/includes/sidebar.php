<!-- Sidebar Navigation Component -->
<nav id="sidebar">
    <div class="sidebar-header">
        <i class="fa-solid fa-graduation-cap sidebar-logo"></i>
        <span class="sidebar-brand-name">Indus Grammar</span>
    </div>

    <ul class="sidebar-menu">
        <!-- Dashboard -->
        <li class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/dashboard.php">
                <i class="fa-solid fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- Academic Section Title -->
        <?php if (hasPermission('student_view') || hasPermission('admission_view') || hasPermission('attendance_view')): ?>
            <li class="sidebar-section-title">Academics</li>
        <?php endif; ?>

        <?php if (hasPermission('student_view')): ?>
        <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/students/') ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/students/list.php">
                <i class="fa-solid fa-user-graduate"></i>
                <span>Students</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('admission_view')): ?>
        <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/admission/') ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/admission/applications.php">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>Admission</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('attendance_view')): ?>
        <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/attendance/student.php">
                <i class="fa-solid fa-calendar-check"></i>
                <span>Attendance</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('exam_view') || hasPermission('academic_view')): ?>
        <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/examination/') || str_contains($_SERVER['PHP_SELF'], '/modules/exams/') ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/examination/exams.php">
                <i class="fa-solid fa-file-signature"></i>
                <span>Examination</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Administration & Finance Section Title -->
        <?php if (hasPermission('fee_view') || hasPermission('staff_view') || hasPermission('cash_view')): ?>
            <li class="sidebar-section-title">Finance & HR</li>
        <?php endif; ?>

        <?php if (hasPermission('fee_view')): ?>
        <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/fees/') ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/fees/structure.php">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span>Fee & Accounts</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('cash_view')): ?>
        <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/cash/') ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/cash/dashboard.php">
                <i class="fa-solid fa-wallet"></i>
                <span>Cash Desk</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('staff_view') || hasPermission('hr_view')): ?>
        <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/staff/') ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/staff/list.php">
                <i class="fa-solid fa-users-gear"></i>
                <span>HR / Staff</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Communications Section -->
        <?php if (hasPermission('communication_send') || hasPermission('report_view') || hasPermission('system_settings')): ?>
            <li class="sidebar-section-title">Operations</li>
        <?php endif; ?>

        <?php if (hasPermission('communication_send')): ?>
        <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/communication/') ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/communication/announcements.php">
                <i class="fa-regular fa-paper-plane"></i>
                <span>Communication</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('report_view')): ?>
        <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/reports/') ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/reports/students.php">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Reports</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('system_settings')): ?>
        <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/administration/') ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/administration/settings.php">
                <i class="fa-solid fa-sliders"></i>
                <span>Administration</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
