<!-- Sidebar Navigation Component -->
<nav id="sidebar">
    <div class="sidebar-header">
        <i class="fa-solid fa-graduation-cap sidebar-logo text-warning"></i>
        <span class="sidebar-brand-name">Indus Grammar</span>
    </div>

    <!-- Scrollable container inside sidebar -->
    <div class="sidebar-scroll">
        <ul class="sidebar-menu" id="sidebarMenu">
            
            <!-- 1. Dashboard -->
            <li class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <a href="<?php echo APP_URL; ?>/dashboard.php">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- 2. Student Registration -->
            <?php if (hasPermission('student_view')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/students/') ? 'active' : ''; ?>">
                <a href="#studentSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/students/') ? '' : 'collapsed'; ?>" aria-expanded="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/students/') ? 'true' : 'false'; ?>">
                    <span>
                        <i class="fa-solid fa-user-graduate"></i>
                        <span>Student Registration</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/students/') ? 'show' : ''; ?>" id="studentSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/registration.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/registration.php">Registration</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/detail_report.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/detail_report.php">Student Detail Report</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/daily_diary.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/daily_diary.php">Daily Diary</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/edit_diary.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/edit_diary.php">Edit Diary</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/diary_report.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/diary_report.php">Diary Report</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/complaint.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/complaint.php">Student Complaint</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/card_report.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/card_report.php">Student Card Report</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/name_cnic_report.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/name_cnic_report.php">Student Name / CNIC Report</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/add_sponsor.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/add_sponsor.php">Add Sponsor</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/family_phone_list.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/family_phone_list.php">Family Wise Phone List</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/profile_report.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/profile_report.php">Student Profile Report</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/summary_report.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/students/summary_report.php">Student Summary Report</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- 3. Student Attendance -->
            <?php if (hasPermission('attendance_view')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && !str_contains($_SERVER['PHP_SELF'], '/staff') ? 'active' : ''; ?>">
                <a href="#attendanceSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && !str_contains($_SERVER['PHP_SELF'], '/staff')) ? '' : 'collapsed'; ?>" aria-expanded="<?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && !str_contains($_SERVER['PHP_SELF'], '/staff')) ? 'true' : 'false'; ?>">
                    <span>
                        <i class="fa-solid fa-calendar-days"></i>
                        <span>Student Attendance</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && !str_contains($_SERVER['PHP_SELF'], '/staff')) ? 'show' : ''; ?>" id="attendanceSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/student.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/student.php">Mark Attendance</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/daily.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/daily.php">Daily Report</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/monthly.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/monthly.php">Monthly Report</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/reports.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/reports.php">Attendance Register</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/leave.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/leave.php">Leave Management</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/settings.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/settings.php">Attendance Settings</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- 4. Fee Collection -->
            <?php if (hasPermission('fee_view')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/fees/') && !str_contains($_SERVER['PHP_SELF'], '/expenses') ? 'active' : ''; ?>">
                <a href="#feeSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/fees/') && !str_contains($_SERVER['PHP_SELF'], '/expenses')) ? '' : 'collapsed'; ?>" aria-expanded="<?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/fees/') && !str_contains($_SERVER['PHP_SELF'], '/expenses')) ? 'true' : 'false'; ?>">
                    <span>
                        <i class="fa-solid fa-money-bill-wave"></i>
                        <span>Fee Collection</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/fees/') && !str_contains($_SERVER['PHP_SELF'], '/expenses')) ? 'show' : ''; ?>" id="feeSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/structure.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/fees/structure.php">Fee Structure</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/collection.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/fees/collection.php">Collect Fee</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/challan.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/fees/challan.php">Fee Challan</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/dues.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/fees/dues.php">Pending Dues</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/discounts.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/fees/discounts.php">Discounts</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/fines.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/fees/fines.php">Fine Management</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/receipts.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/fees/receipts.php">Fee Receipts</a></li>
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/reports.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/fees/reports.php">Fee Reports</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- 5. Admin Panel -->
            <?php if (hasPermission('system_settings')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/administration/') ? 'active' : ''; ?>">
                <a href="#adminSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/administration/') ? '' : 'collapsed'; ?>" aria-expanded="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/administration/') ? 'true' : 'false'; ?>">
                    <span>
                        <i class="fa-solid fa-sliders"></i>
                        <span>Admin Panel</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/administration/') ? 'show' : ''; ?>" id="adminSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/settings.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/administration/settings.php">Global Settings</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- 6. Accounts -->
            <?php if (hasPermission('cash_view')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/cash/') || str_contains($_SERVER['PHP_SELF'], '/expenses') ? 'active' : ''; ?>">
                <a href="#accountsSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/cash/') || str_contains($_SERVER['PHP_SELF'], '/expenses')) ? '' : 'collapsed'; ?>" aria-expanded="<?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/cash/') || str_contains($_SERVER['PHP_SELF'], '/expenses')) ? 'true' : 'false'; ?>">
                    <span>
                        <i class="fa-solid fa-wallet"></i>
                        <span>Accounts</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/cash/') || str_contains($_SERVER['PHP_SELF'], '/expenses')) ? 'show' : ''; ?>" id="accountsSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/dashboard.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/cash/dashboard.php">Cash Dashboard</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- 7. Staff Attendance -->
            <?php if (hasPermission('attendance_view')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && str_contains($_SERVER['PHP_SELF'], '/staff') ? 'active' : ''; ?>">
                <a href="#staffAttendanceSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && str_contains($_SERVER['PHP_SELF'], '/staff')) ? '' : 'collapsed'; ?>" aria-expanded="<?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && str_contains($_SERVER['PHP_SELF'], '/staff')) ? 'true' : 'false'; ?>">
                    <span>
                        <i class="fa-solid fa-user-clock"></i>
                        <span>Staff Attendance</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/attendance/') && str_contains($_SERVER['PHP_SELF'], '/staff')) ? 'show' : ''; ?>" id="staffAttendanceSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/staff.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/attendance/staff.php">Staff Log Sheets</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- 8. Payroll -->
            <?php if (hasPermission('hr_view')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/staff/payroll.php') || str_contains($_SERVER['PHP_SELF'], '/payroll') ? 'active' : ''; ?>">
                <a href="#payrollSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/staff/payroll.php') || str_contains($_SERVER['PHP_SELF'], '/payroll')) ? '' : 'collapsed'; ?>" aria-expanded="<?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/staff/payroll.php') || str_contains($_SERVER['PHP_SELF'], '/payroll')) ? 'true' : 'false'; ?>">
                    <span>
                        <i class="fa-solid fa-money-check-dollar"></i>
                        <span>Payroll</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo (str_contains($_SERVER['PHP_SELF'], '/modules/staff/payroll.php') || str_contains($_SERVER['PHP_SELF'], '/payroll')) ? 'show' : ''; ?>" id="payrollSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/payroll.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/staff/payroll.php">Staff Payrolls</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- 9. Examination -->
            <?php if (hasPermission('exam_view')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/examination/') ? 'active' : ''; ?>">
                <a href="#examinationSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/examination/') ? '' : 'collapsed'; ?>" aria-expanded="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/examination/') ? 'true' : 'false'; ?>">
                    <span>
                        <i class="fa-solid fa-file-signature"></i>
                        <span>Examination</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/examination/') ? 'show' : ''; ?>" id="examinationSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/exams.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/examination/exams.php">Exams Registry</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- 10. Reports -->
            <?php if (hasPermission('report_view')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/reports/') ? 'active' : ''; ?>">
                <a href="#reportsSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/reports/') ? '' : 'collapsed'; ?>" aria-expanded="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/reports/') ? 'true' : 'false'; ?>">
                    <span>
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Reports</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/reports/') ? 'show' : ''; ?>" id="reportsSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/students.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/reports/students.php">Academic Reports</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- 11. Communication -->
            <?php if (hasPermission('communication_send')): ?>
            <li class="menu-item-has-children <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/communication/') ? 'active' : ''; ?>">
                <a href="#communicationSubmenu" data-bs-toggle="collapse" class="sidebar-link-toggle <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/communication/') ? '' : 'collapsed'; ?>" aria-expanded="<?php echo str_contains($_SERVER['PHP_SELF'], '/modules/communication/') ? 'true' : 'false'; ?>">
                    <span>
                        <i class="fa-solid fa-comments"></i>
                        <span>Communication</span>
                    </span>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="collapse list-unstyled submenu <?php echo str_contains($_SERVER['PHP_SELF'], '/modules/communication/') ? 'show' : ''; ?>" id="communicationSubmenu" data-bs-parent="#sidebarMenu">
                    <li class="<?php echo str_contains($_SERVER['PHP_SELF'], '/announcements.php') ? 'active' : ''; ?>"><a href="<?php echo APP_URL; ?>/modules/communication/announcements.php">Announcements Log</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- 12. Logout -->
            <li class="border-top border-secondary pt-2 mt-2">
                <a href="<?php echo APP_URL; ?>/logout.php" class="text-danger">
                    <i class="fa-solid fa-arrow-right-from-bracket text-danger"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
</nav>

<!-- Mobile Overlay Layer -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
